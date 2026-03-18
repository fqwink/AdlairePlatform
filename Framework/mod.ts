/**
 * Adlaire Platform — Barrel Export
 *
 * 全フレームワークモジュールの公開 API を一箇所から re-export する。
 * main.ts, bootstrap.ts, routes.ts が使用するエクスポートのみ含む。
 *
 * FRAMEWORK_RULEBOOK v3.0 §2.1 準拠:
 * - DI コンテナパターンは廃止（Container は削除）
 * - APF → AFE リネーム
 *
 * @package AdlairePlatform
 * @version 3.0.0
 * @license Adlaire License Ver.2.0
 */

// ── Types ──
// FRAMEWORK_RULEBOOK v3.0 §2.1: 共有型ファイル禁止
// 各フレームワークは ACS.d.ts から直接 import type する
export type * from "./ACS/ACS.d.ts";

// ── AFE (Adlaire Foundation Engine) ──
export {
  EventBus,
  MiddlewarePipeline,
  Request,
  Response,
  Router,
} from "./AFE/AFE.Core.ts";
export { FileSystem } from "./AFE/AFE.Utilities.ts";
export { registerSystemRoutes } from "./AFE/AFE.Api.ts";

// ── ACS (Adlaire Client Services) ──
export { ClientFactory, createBasicClient } from "./ACS/ACS.Core.ts";
export { createClient, createMockClient } from "./ACS/ACS.Api.ts";

// ── ACE (Content Engine) ──
export {
  CollectionManager,
  ContentManager,
  MetaManager,
} from "./ACE/ACE.Core.ts";
export { WebhookService } from "./ACE/ACE.Utilities.ts";
export { registerCollectionRoutes } from "./ACE/ACE.Api.ts";

// ── AIS (Infrastructure Services) ──
export { AppContext, I18n, RequestLoggingMiddleware } from "./AIS/AIS.Core.ts";
export { ApiCache, DiagnosticsManager } from "./AIS/AIS.Utilities.ts";
export { registerInfraRoutes } from "./AIS/AIS.Api.ts";

// ── ASG (Static Generator) ──
export { Builder, MarkdownService, TemplateRenderer } from "./ASG/ASG.Utilities.ts";
export { registerGeneratorRoutes } from "./ASG/ASG.Api.ts";

// ── AP (Platform Controllers — 段階的廃止予定) ──
export {
  AuthMiddleware,
  CorsMiddleware,
  CsrfMiddleware,
  RateLimitMiddleware,
  SecurityHeadersMiddleware,
} from "./AP/AP.Utilities.ts";
export { registerPlatformRoutes } from "./AP/AP.Api.ts";

// ── AEB (Editor & Blocks) ──
export { BlockRegistry, Editor } from "./AEB/AEB.Core.ts";
