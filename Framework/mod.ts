/**
 * Adlaire Platform — Barrel Export
 *
 * 全フレームワークモジュールの公開 API を一箇所から re-export する。
 *
 * @example
 * ```ts
 * import { Router, Request, Response, Container } from "./Framework/mod.ts";
 * import { TemplateRenderer, MarkdownService } from "./Framework/mod.ts";
 * ```
 *
 * @package AdlairePlatform
 * @version 2.0.0
 * @license Adlaire License Ver.2.0
 */

// ── Types ──
export type * from "./types.ts";

// ── APF (Platform Foundation) ──
export { Container, Router, Request, Response, MiddlewarePipeline, Application, EventBus } from "./APF/APF.Core.ts";
export { HttpMethod, LogLevel, NotFoundError, ValidationError } from "./APF/APF.Class.ts";
export { Str, Arr, Config, Validator, Security, FileSystem } from "./APF/APF.Utilities.ts";
export { registerSystemRoutes, jsonError, jsonSuccess, jsonValidationError } from "./APF/APF.Api.ts";

// ── ACE (Content Engine) ──
export { CollectionManager, ContentManager, MetaManager, ContentValidator } from "./ACE/ACE.Core.ts";
export { CollectionError, ContentNotFoundError, DuplicateSlugError } from "./ACE/ACE.Class.ts";
export { WebhookService, RevisionService, ApiRouter } from "./ACE/ACE.Utilities.ts";
export { registerCollectionRoutes } from "./ACE/ACE.Api.ts";

// ── AIS (Infrastructure Services) ──
export { AppContext, I18n, ServiceContainer, EventDispatcher } from "./AIS/AIS.Core.ts";
export { ConfigValidationError } from "./AIS/AIS.Class.ts";
export { DiagnosticsManager, ApiCache, GitService, UpdateService } from "./AIS/AIS.Utilities.ts";
export { registerInfraRoutes } from "./AIS/AIS.Api.ts";

// ── ASG (Static Generator) ──
export { Generator, HybridResolver, BuildCache, SiteRouter, Deployer } from "./ASG/ASG.Core.ts";
export { TemplateRenderer, MarkdownService, ThemeManager, Builder } from "./ASG/ASG.Utilities.ts";
export { TemplateError, ThemeError, BuildError } from "./ASG/ASG.Class.ts";
export { registerGeneratorRoutes } from "./ASG/ASG.Api.ts";

// ── AP (Platform Controllers) ──
export {
  BaseController,
  AuthController,
  DashboardController,
  ApiController,
  AdminController,
  CollectionController,
  GitController,
  WebhookController,
  StaticController,
  UpdateController,
  DiagnosticController,
  ActionDispatcher,
} from "./AP/AP.Core.ts";
export { ACTION_MAP, ControllerError, UnknownActionError, ForbiddenError } from "./AP/AP.Class.ts";
export {
  CsrfMiddleware,
  AuthMiddleware,
  RateLimitMiddleware,
  CorsMiddleware,
  SecurityHeadersMiddleware,
  RequestLoggingMiddleware,
} from "./AP/AP.Utilities.ts";
export { registerPlatformRoutes } from "./AP/AP.Api.ts";

// ── ACS (Client Services) ──
export { ClientFactory, HttpTransport, AuthService, StorageService, FileService, EventSourceService } from "./ACS/ACS.Core.ts";
export { ApiError, AuthenticationError, NetworkError, StorageError } from "./ACS/ACS.Class.ts";
export { buildUrl, buildHeaders, parseApiResponse } from "./ACS/ACS.Utilities.ts";
export { createClient, createMockClient } from "./ACS/ACS.Api.ts";
