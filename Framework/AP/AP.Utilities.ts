/**
 * Adlaire Platform (AP) — Utilities Module
 *
 * コントローラーユーティリティ、ルート生成ヘルパー、
 * CSRF 保護ミドルウェアを提供する。
 *
 * @package AP
 * @version 2.0.0
 * @license Adlaire License Ver.2.0
 */

import type {
  MiddlewareInterface,
  RequestInterface,
  ResponseInterface,
} from "../APF/APF.Interface.ts";

import { Response } from "../APF/APF.Core.ts";

// ============================================================================
// CsrfMiddleware — CSRF 保護
// ============================================================================

export class CsrfMiddleware implements MiddlewareInterface {
  private readonly tokenName = "csrf_token";
  private tokens = new Map<string, number>();

  handle(
    request: RequestInterface,
    next: (req: RequestInterface) => Promise<ResponseInterface>,
  ): ResponseInterface | Promise<ResponseInterface> {
    const method = request.method();

    // GET/HEAD/OPTIONS は検証スキップ
    if (method === "GET" || method === "HEAD" || method === "OPTIONS") {
      return next(request);
    }

    // トークン検証
    const token = request.header("x-csrf-token") ??
      String(request.post(this.tokenName) ?? "");

    if (!token || !this.validateToken(token)) {
      return Response.json({ ok: false, error: "CSRF token mismatch" }, 403);
    }

    return next(request);
  }

  generateToken(): string {
    const token = crypto.randomUUID();
    this.tokens.set(token, Date.now());
    this.cleanup();
    return token;
  }

  private validateToken(token: string): boolean {
    const created = this.tokens.get(token);
    if (!created) return false;

    // 1時間で失効
    if (Date.now() - created > 3600000) {
      this.tokens.delete(token);
      return false;
    }

    // 使い捨て
    this.tokens.delete(token);
    return true;
  }

  private cleanup(): void {
    const cutoff = Date.now() - 3600000;
    for (const [token, created] of this.tokens) {
      if (created < cutoff) this.tokens.delete(token);
    }
  }
}

// ============================================================================
// AuthMiddleware — 認証ミドルウェア
// ============================================================================

export class AuthMiddleware implements MiddlewareInterface {
  constructor(
    private readonly verifyToken: (token: string) => Promise<boolean>,
  ) {}

  async handle(
    request: RequestInterface,
    next: (req: RequestInterface) => Promise<ResponseInterface>,
  ): Promise<ResponseInterface> {
    const authHeader = request.header("authorization") ?? "";
    const token = authHeader.startsWith("Bearer ") ? authHeader.slice(7) : null;

    if (!token) {
      return Response.json({ ok: false, error: "Unauthorized" }, 401);
    }

    const valid = await this.verifyToken(token);
    if (!valid) {
      return Response.json({ ok: false, error: "Invalid token" }, 401);
    }

    return next(request);
  }
}

// ============================================================================
// RateLimitMiddleware — レートリミット
// ============================================================================

export class RateLimitMiddleware implements MiddlewareInterface {
  private requests = new Map<string, { count: number; resetAt: number }>();

  constructor(
    private readonly maxRequests: number = 60,
    private readonly windowSeconds: number = 60,
  ) {}

  handle(
    request: RequestInterface,
    next: (req: RequestInterface) => Promise<ResponseInterface>,
  ): ResponseInterface | Promise<ResponseInterface> {
    const key = request.ip();
    const now = Date.now();
    const entry = this.requests.get(key);

    if (!entry || now > entry.resetAt) {
      this.requests.set(key, {
        count: 1,
        resetAt: now + this.windowSeconds * 1000,
      });
      return next(request);
    }

    entry.count++;
    if (entry.count > this.maxRequests) {
      const retryAfter = Math.ceil((entry.resetAt - now) / 1000);
      const response = Response.json(
        { ok: false, error: "Rate limit exceeded" },
        429,
      );
      return response.withHeader("Retry-After", String(retryAfter));
    }

    return next(request);
  }
}

// ============================================================================
// CorsMiddleware — CORS
// ============================================================================

export class CorsMiddleware implements MiddlewareInterface {
  constructor(
    private readonly allowedOrigins: string[] = ["*"],
    private readonly allowedMethods: string[] = ["GET", "POST", "PUT", "DELETE", "OPTIONS"],
  ) {}

  async handle(
    request: RequestInterface,
    next: (req: RequestInterface) => Promise<ResponseInterface>,
  ): Promise<ResponseInterface> {
    const origin = request.header("origin") ?? "";

    // Preflight
    if (request.method() === "OPTIONS") {
      return Response.text("", 204)
        .withHeader("Access-Control-Allow-Origin", this.resolveOrigin(origin))
        .withHeader("Access-Control-Allow-Methods", this.allowedMethods.join(", "))
        .withHeader("Access-Control-Allow-Headers", "Content-Type, Authorization, X-CSRF-Token")
        .withHeader("Access-Control-Max-Age", "86400");
    }

    const response = await next(request);
    return response
      .withHeader("Access-Control-Allow-Origin", this.resolveOrigin(origin));
  }

  private resolveOrigin(origin: string): string {
    if (this.allowedOrigins.includes("*")) return "*";
    if (this.allowedOrigins.includes(origin)) return origin;
    return "";
  }
}

// ============================================================================
// SecurityHeadersMiddleware — セキュリティヘッダー
// ============================================================================

export class SecurityHeadersMiddleware implements MiddlewareInterface {
  async handle(
    request: RequestInterface,
    next: (req: RequestInterface) => Promise<ResponseInterface>,
  ): Promise<ResponseInterface> {
    const response = await next(request);
    return response
      .withHeader("X-Content-Type-Options", "nosniff")
      .withHeader("X-Frame-Options", "DENY")
      .withHeader("X-XSS-Protection", "1; mode=block")
      .withHeader("Referrer-Policy", "strict-origin-when-cross-origin");
  }
}

// ============================================================================
// RequestLoggingMiddleware — リクエストログ
// ============================================================================

export class RequestLoggingMiddleware implements MiddlewareInterface {
  constructor(
    private readonly logger?: (entry: Record<string, unknown>) => void,
  ) {}

  async handle(
    request: RequestInterface,
    next: (req: RequestInterface) => Promise<ResponseInterface>,
  ): Promise<ResponseInterface> {
    const start = performance.now();
    const response = await next(request);
    const elapsed = performance.now() - start;

    const entry = {
      method: request.method(),
      path: request.path(),
      status: response.getStatusCode(),
      elapsed: Math.round(elapsed * 100) / 100,
      ip: request.ip(),
      requestId: request.requestId(),
      timestamp: new Date().toISOString(),
    };

    if (this.logger) {
      this.logger(entry);
    }

    return response;
  }
}
