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
export {
  Application,
  Container,
  EventBus,
  MiddlewarePipeline,
  Request,
  Response,
  Router,
} from "./APF/APF.Core.ts";
export { HttpMethod, LogLevel, NotFoundError, ValidationError } from "./APF/APF.Class.ts";
export { Arr, Config, FileSystem, Security, Str, Validator } from "./APF/APF.Utilities.ts";
export {
  jsonError,
  jsonSuccess,
  jsonValidationError,
  registerSystemRoutes,
} from "./APF/APF.Api.ts";

// ── ACE (Content Engine) ──
export {
  CollectionManager,
  ContentManager,
  ContentValidator,
  MetaManager,
} from "./ACE/ACE.Core.ts";
export { CollectionError, ContentNotFoundError, DuplicateSlugError } from "./ACE/ACE.Class.ts";
export { ApiRouter, RevisionService, WebhookService } from "./ACE/ACE.Utilities.ts";
export { registerCollectionRoutes } from "./ACE/ACE.Api.ts";

// ── AIS (Infrastructure Services) ──
export { AppContext, EventDispatcher, I18n, ServiceContainer } from "./AIS/AIS.Core.ts";
export { ConfigValidationError } from "./AIS/AIS.Class.ts";
export { ApiCache, DiagnosticsManager, GitService, UpdateService } from "./AIS/AIS.Utilities.ts";
export { registerInfraRoutes } from "./AIS/AIS.Api.ts";

// ── ASG (Static Generator) ──
export { BuildCache, Deployer, Generator, HybridResolver, SiteRouter } from "./ASG/ASG.Core.ts";
export { Builder, MarkdownService, TemplateRenderer, ThemeManager } from "./ASG/ASG.Utilities.ts";
export { BuildError, TemplateError, ThemeError } from "./ASG/ASG.Class.ts";
export { registerGeneratorRoutes } from "./ASG/ASG.Api.ts";

// ── AP (Platform Controllers) ──
export {
  ActionDispatcher,
  AdminController,
  ApiController,
  AuthController,
  BaseController,
  CollectionController,
  DashboardController,
  DiagnosticController,
  GitController,
  StaticController,
  UpdateController,
  WebhookController,
} from "./AP/AP.Core.ts";
export { ACTION_MAP, ControllerError, ForbiddenError, UnknownActionError } from "./AP/AP.Class.ts";
export {
  AuthMiddleware,
  CorsMiddleware,
  CsrfMiddleware,
  RateLimitMiddleware,
  RequestLoggingMiddleware,
  SecurityHeadersMiddleware,
} from "./AP/AP.Utilities.ts";
export { registerPlatformRoutes } from "./AP/AP.Api.ts";

// ── ACS (Client Services) ──
export {
  AuthService,
  ClientFactory,
  EventSourceService,
  FileService,
  HttpTransport,
  StorageService,
} from "./ACS/ACS.Core.ts";
export {
  AuthError,
  ConnectionState,
  NetworkError,
  ServerError,
  TimeoutError,
} from "./ACS/ACS.Class.ts";
export {
  bearerHeader,
  buildQueryString,
  calculateBackoff,
  csrfHeader,
  extractData,
  joinUrl,
  jsonHeaders,
  objectToFormData,
} from "./ACS/ACS.Utilities.ts";
export { createClient, createMockClient } from "./ACS/ACS.Api.ts";
