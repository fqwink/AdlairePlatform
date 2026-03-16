<?php

declare(strict_types=1);

/**
 * ASS.Class.php — Adlaire Server System / Engine 5: Class
 *
 * データモデル・型定義。
 * ACS の ApiResponse<T> 構造に準拠した値オブジェクトおよびリクエスト/レスポンスの具象クラス。
 */

namespace ASS\Class;

use ASS\Interface\RequestInterface;
use ASS\Interface\ResponseInterface;

// ─────────────────────────────────────────────
// ApiResponse ビルダー
// ─────────────────────────────────────────────

/**
 * ACS 契約に準拠した ApiResponse 構造を生成するヘルパー。
 *
 * 全レスポンスは { ok: bool, data?: T, error?: string, errors?: Record<string, string[]> } 形式。
 */
final class ApiResponse
{
    /**
     * @param mixed $data レスポンスペイロード
     * @return array{ok: true, data: mixed}
     */
    public static function ok(mixed $data = null): array
    {
        $response = ['ok' => true];
        if ($data !== null) {
            $response['data'] = $data;
        }
        return $response;
    }

    /**
     * @param string $error エラーメッセージ
     * @param array<string, string[]>|null $errors フィールドレベルのバリデーションエラー
     * @return array{ok: false, error: string, errors?: array<string, string[]>}
     */
    public static function error(string $error, ?array $errors = null): array
    {
        $response = ['ok' => false, 'error' => $error];
        if ($errors !== null) {
            $response['errors'] = $errors;
        }
        return $response;
    }
}

// ─────────────────────────────────────────────
// Request 具象クラス
// ─────────────────────────────────────────────

final class Request implements RequestInterface
{
    private string $method;
    private string $path;
    private array $query;
    private array $body;
    private array $headers;
    private array $cookies;
    private array $files;

    private function __construct(
        string $method,
        string $path,
        array $query,
        array $body,
        array $headers,
        array $cookies,
        array $files,
    ) {
        $this->method = $method;
        $this->path = $path;
        $this->query = $query;
        $this->body = $body;
        $this->headers = $headers;
        $this->cookies = $cookies;
        $this->files = $files;
    }

    /**
     * PHP スーパーグローバルからリクエストを生成する。
     */
    public static function fromGlobals(): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = rawurldecode($path);

        $query = $_GET;

        $body = [];
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input');
            if ($raw !== false && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $body = $decoded;
                }
            }
        } else {
            $body = $_POST;
        }

        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = $value;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
        }

        return new self($method, $path, $query, $body, $headers, $_COOKIE, $_FILES);
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getQuery(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function getBody(): array
    {
        return $this->body;
    }

    public function getHeader(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function getCookie(string $name): ?string
    {
        return $this->cookies[$name] ?? null;
    }

    public function getFile(string $name): ?array
    {
        return $this->files[$name] ?? null;
    }
}

// ─────────────────────────────────────────────
// Response 具象クラス
// ─────────────────────────────────────────────

final class Response implements ResponseInterface
{
    private int $statusCode = 200;
    /** @var array<string, string> */
    private array $headers = ['Content-Type' => 'application/json'];
    /** @var array<string, array{value: string, options: array}> */
    private array $cookies = [];
    /** @var string[] */
    private array $clearCookies = [];
    private bool $sent = false;

    public function json(array $body, int $status = 200): void
    {
        $this->statusCode = $status;
        $this->send(json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function file(string $path, string $contentType): void
    {
        $this->headers['Content-Type'] = $contentType;
        $this->headers['Content-Length'] = (string) filesize($path);
        $this->sendHeaders();
        readfile($path);
        $this->sent = true;
    }

    public function setCookie(string $name, string $value, array $options = []): static
    {
        $this->cookies[$name] = ['value' => $value, 'options' => $options];
        return $this;
    }

    public function clearCookie(string $name): static
    {
        $this->clearCookies[] = $name;
        return $this;
    }

    public function setHeader(string $name, string $value): static
    {
        $this->headers[$name] = $value;
        return $this;
    }

    private function sendHeaders(): void
    {
        if ($this->sent) {
            return;
        }

        http_response_code($this->statusCode);

        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        foreach ($this->cookies as $name => $cookie) {
            $opts = $cookie['options'];
            setcookie($name, $cookie['value'], [
                'expires' => $opts['expires'] ?? 0,
                'path' => $opts['path'] ?? '/',
                'domain' => $opts['domain'] ?? '',
                'secure' => $opts['secure'] ?? false,
                'httponly' => $opts['httponly'] ?? true,
                'samesite' => $opts['samesite'] ?? 'Strict',
            ]);
        }

        foreach ($this->clearCookies as $name) {
            setcookie($name, '', ['expires' => 1, 'path' => '/']);
        }
    }

    private function send(string $body): void
    {
        $this->sendHeaders();
        echo $body;
        $this->sent = true;
    }
}

// ─────────────────────────────────────────────
// ルーター具象クラス
// ─────────────────────────────────────────────

final class Router implements \ASS\Interface\RouterInterface
{
    /** @var array<string, array<string, callable>> method => [path => handler] */
    private array $routes = [];

    public function get(string $path, callable $handler): static
    {
        $this->routes['GET'][$path] = $handler;
        return $this;
    }

    public function post(string $path, callable $handler): static
    {
        $this->routes['POST'][$path] = $handler;
        return $this;
    }

    public function dispatch(RequestInterface $request, ResponseInterface $response): void
    {
        $method = $request->getMethod();
        $path = $request->getPath();

        // OPTIONS プリフライト
        if ($method === 'OPTIONS') {
            $response->json(ApiResponse::ok());
            return;
        }

        $routes = $this->routes[$method] ?? [];

        foreach ($routes as $pattern => $handler) {
            if ($pattern === $path) {
                $handler($request, $response);
                return;
            }
        }

        $response->json(ApiResponse::error('Not Found'), 404);
    }
}
