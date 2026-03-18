/**
 * Adlaire Foundation Engine (AFE) — API Adapter Layer
 *
 * AFE のサービスを ACS 経由で公開する REST エンドポイントアダプター。
 * Router 登録用のルートファクトリを提供する。
 *
 * FRAMEWORK_RULEBOOK v3.0 §2.1 準拠
 *
 * @package AFE
 * @version 3.0.0
 * @license Adlaire License Ver.2.0
 */

import type { ResponseInterface } from "./AFE.Interface.ts";
import type { RouterInterface } from "./AFE.Interface.ts";
import { Response } from "./AFE.Core.ts";

// ============================================================================
// Health / System Endpoints
// ============================================================================

/**
 * AFE 標準ルートを Router に登録する
 */
export function registerSystemRoutes(router: RouterInterface): void {
  router.get("/api/health", () =>
    Response.json({
      ok: true,
      data: {
        status: "healthy",
        timestamp: new Date().toISOString(),
        runtime: "deno",
        version: Deno.version.deno,
      },
    }));

  router.get("/api/version", () =>
    Response.json({
      ok: true,
      data: {
        platform: "Adlaire",
        version: "Ver.2.3-44",
        framework: "AFE",
      },
    }));
}

// ============================================================================
// Error Response Helpers
// ============================================================================

export function jsonError(message: string, status: number = 400): ResponseInterface {
  return Response.json({ ok: false, error: message }, status);
}

export function jsonSuccess<T>(data: T, status: number = 200): ResponseInterface {
  return Response.json({ ok: true, data }, status);
}

export function jsonValidationError(errors: Record<string, string[]>): ResponseInterface {
  return Response.json({ ok: false, errors }, 422);
}
