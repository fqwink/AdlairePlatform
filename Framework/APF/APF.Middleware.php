<?php
/**
 * Adlaire Platform Foundation (APF) - Middleware Module
 *
 * Ver.1.7: Controller ルーティング用ミドルウェア群。
 * 認証・CSRF・レート制限・セキュリティヘッダーを提供。
 *
 * @package APF
 * @since Ver.1.7-36
 * @license Adlaire License Ver.2.0
 */

namespace APF\Middleware;

use APF\Core\{Middleware, Request, Response};

// ============================================================================
// AuthMiddleware - 認証チェック
// ============================================================================

class AuthMiddleware extends Middleware {

    public function handle(Request $request, \Closure $next): Response {
        if (!isset($_SESSION['l']) || $_SESSION['l'] !== true) {
            /* API リクエスト（JSON 期待）は JSON レスポンス */
            if ($request->isJson() || $request->isAjax()) {
                return Response::json(['error' => 'Unauthorized'], 401);
            }
            /* ブラウザリクエストはログインページへリダイレクト */
            return Response::redirect('./?login');
        }
        return $next($request);
    }
}

// ============================================================================
// CsrfMiddleware - CSRF トークン検証
// ============================================================================

class CsrfMiddleware extends Middleware {

    public function handle(Request $request, \Closure $next): Response {
        /* GET/HEAD/OPTIONS は CSRF 検証不要 */
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        $token = $request->post('csrf') ?? $request->header('X-Csrf-Token', '');
        $sessionCsrf = $_SESSION['csrf'] ?? '';
        if ($sessionCsrf === '' || $token === '' || !hash_equals($sessionCsrf, (string)$token)) {
            return Response::json(['error' => 'CSRF verification failed'], 403);
        }

        return $next($request);
    }
}

// ============================================================================
// RateLimitMiddleware - レート制限
// ============================================================================

class RateLimitMiddleware extends Middleware {

    public function __construct(
        private readonly int $maxRequests = 60,
        private readonly int $windowSeconds = 60,
        private readonly string $sessionKey = '_rate_requests',
    ) {}

    public function handle(Request $request, \Closure $next): Response {
        $now = time();
        $_SESSION[$this->sessionKey] = array_filter(
            $_SESSION[$this->sessionKey] ?? [],
            fn($t) => $t > $now - $this->windowSeconds
        );

        if (count($_SESSION[$this->sessionKey]) >= $this->maxRequests) {
            $reset = min($_SESSION[$this->sessionKey]) + $this->windowSeconds;
            return (new Response(json_encode(['error' => 'Too Many Requests']), 429, [
                'Content-Type'       => 'application/json',
                'Retry-After'        => (string)($reset - $now),
                'X-RateLimit-Limit'  => (string)$this->maxRequests,
                'X-RateLimit-Remaining' => '0',
                'X-RateLimit-Reset'  => (string)$reset,
            ]));
        }

        $_SESSION[$this->sessionKey][] = $now;

        $response = $next($request);
        $remaining = $this->maxRequests - count($_SESSION[$this->sessionKey]);
        $response->withHeader('X-RateLimit-Limit', (string)$this->maxRequests);
        $response->withHeader('X-RateLimit-Remaining', (string)max(0, $remaining));

        return $response;
    }
}

// ============================================================================
// SecurityHeadersMiddleware - セキュリティヘッダー付与
// ============================================================================

class SecurityHeadersMiddleware extends Middleware {

    public function handle(Request $request, \Closure $next): Response {
        $response = $next($request);
        $response->withHeader('X-Content-Type-Options', 'nosniff');
        $response->withHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        /* Ver.1.9: リクエスト相関IDをレスポンスヘッダーに付与 */
        $response->withHeader('X-Request-Id', $request->requestId());
        return $response;
    }
}

// ============================================================================
// RequestLoggingMiddleware - リクエストログ (Ver.1.9)
// ============================================================================

/**
 * 全リクエスト/レスポンスを構造化ログに記録する。
 * @since Ver.1.9
 */
class RequestLoggingMiddleware extends Middleware {

    public function handle(Request $request, \Closure $next): Response {
        $start = hrtime(true);

        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            $elapsed = (hrtime(true) - $start) / 1_000_000;
            \APF\Utilities\Logger::error('HTTP request failed', [
                'request_id' => $request->requestId(),
                'method'     => $request->method(),
                'uri'        => $request->uri(),
                'elapsed_ms' => round($elapsed, 2),
                'ip'         => $request->ip(),
                'error'      => $e->getMessage(),
            ]);
            throw $e;
        }

        $elapsed = (hrtime(true) - $start) / 1_000_000; /* ms */

        \APF\Utilities\Logger::info('HTTP request', [
            'request_id' => $request->requestId(),
            'method'     => $request->method(),
            'uri'        => $request->uri(),
            'status'     => $response->getStatusCode(),
            'elapsed_ms' => round($elapsed, 2),
            'ip'         => $request->ip(),
        ]);

        /* レスポンスタイムヘッダー付与 */
        $response->withHeader('X-Response-Time', round($elapsed, 2) . 'ms');

        return $response;
    }
}

// ============================================================================
// CorsMiddleware - CORS 制御 (Ver.1.9)
// ============================================================================

/**
 * Cross-Origin Resource Sharing (CORS) ヘッダーを制御する。
 * API エンドポイントで外部オリジンからのアクセスを許可する場合に使用。
 * @since Ver.1.9
 */
class CorsMiddleware extends Middleware {

    public function __construct(
        private readonly array $allowedOrigins = ['*'],
        private readonly array $allowedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
        private readonly array $allowedHeaders = ['Content-Type', 'Authorization', 'X-Csrf-Token', 'X-Request-Id'],
        private readonly int $maxAge = 86400,
    ) {}

    public function handle(Request $request, \Closure $next): Response {
        /* プリフライトリクエスト */
        if ($request->method() === 'OPTIONS') {
            $response = new Response('', 204);
        } else {
            $response = $next($request);
        }

        $origin = $request->header('Origin', '');
        if (in_array('*', $this->allowedOrigins, true)) {
            /* ワイルドカード時は実オリジンを返す（credentials 互換） */
            $allowOrigin = $origin !== '' ? $origin : '*';
        } else {
            $allowOrigin = in_array($origin, $this->allowedOrigins, true) ? $origin : '';
        }

        if ($allowOrigin !== '') {
            $response->withHeader('Access-Control-Allow-Origin', $allowOrigin);
            $response->withHeader('Access-Control-Allow-Methods', implode(', ', $this->allowedMethods));
            $response->withHeader('Access-Control-Allow-Headers', implode(', ', $this->allowedHeaders));
            $response->withHeader('Access-Control-Max-Age', (string)$this->maxAge);
            if ($allowOrigin !== '*') {
                $response->withHeader('Vary', 'Origin');
            }
        }

        return $response;
    }
}
