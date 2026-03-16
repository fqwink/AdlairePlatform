/**
 * Adlaire Platform — Barrel Export
 *
 * 全フレームワークモジュールの公開 API を一箇所から re-export する。
 * main.ts, bootstrap.ts, routes.ts が使用するエクスポートのみ含む。
 *
 * @package AdlairePlatform
 * @version 2.0.0
 * @license Adlaire License Ver.2.0
 */

// ── Types ──
export type * from "./types.ts";

// ── APF (Platform Foundation) ──
export {
  Container,
  EventBus,
  Request,
  Response,
  Router,
} from "./APF/APF.Core.ts";
export { FileSystem } from "./APF/APF.Utilities.ts";
export { registerSystemRoutes } from "./APF/APF.Api.ts";

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
export { AppContext, I18n } from "./AIS/AIS.Core.ts";
export { ApiCache, DiagnosticsManager } from "./AIS/AIS.Utilities.ts";
export { registerInfraRoutes } from "./AIS/AIS.Api.ts";

// ── ASG (Static Generator) ──
export { Builder, MarkdownService, TemplateRenderer } from "./ASG/ASG.Utilities.ts";
export { registerGeneratorRoutes } from "./ASG/ASG.Api.ts";

// ── AP (Platform Controllers) ──
export {
  AuthMiddleware,
  CorsMiddleware,
  CsrfMiddleware,
  RateLimitMiddleware,
  RequestLoggingMiddleware,
  SecurityHeadersMiddleware,
} from "./AP/AP.Utilities.ts";
export { registerPlatformRoutes } from "./AP/AP.Api.ts";

// ── AEB (Editor & Blocks) ──
export { BlockRegistry, Editor } from "./AEB/AEB.Core.ts";
