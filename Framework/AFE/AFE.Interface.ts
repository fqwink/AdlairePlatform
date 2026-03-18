/**
 * Adlaire Foundation Engine (AFE) — Interface Definitions
 *
 * Router、Request/Response、Middleware、イベントバスなど
 * AFE が公開する全インターフェースを定義する。
 *
 * FRAMEWORK_RULEBOOK v3.0 §2.1 準拠:
 * - DI コンテナパターンは廃止（ContainerInterface は削除）
 *
 * @package AFE
 * @version 3.0.0
 * @license Adlaire License Ver.2.0
 */

import type {
  HttpMethodValue,
  RequestContext,
  ResponseData,
  RouteDefinition,
  ValidationErrors,
} from "../types.ts";

// ============================================================================
// Router
// ============================================================================

export interface RouterInterface {
  get(path: string, handler: RouteHandler): RouteBuilder;
  post(path: string, handler: RouteHandler): RouteBuilder;
  put(path: string, handler: RouteHandler): RouteBuilder;
  patch(path: string, handler: RouteHandler): RouteBuilder;
  delete(path: string, handler: RouteHandler): RouteBuilder;
  any(path: string, handler: RouteHandler): RouteBuilder;
  match(methods: HttpMethodValue[], path: string, handler: RouteHandler): RouteBuilder;
  group(options: RouteGroupOptions, callback: (router: RouterInterface) => void): void;
  middleware(mw: MiddlewareInterface): void;
  resolve(method: HttpMethodValue, path: string): ResolvedRoute | null;

  /** クエリパラメータ → URI パスのマッピング（後方互換） */
  mapQuery(param: string, path: string, valueParam?: string): void;
  mapPost(param: string, path: string): void;

  listRoutes(): RouteDefinition[];
}

export interface RouteBuilder {
  name(name: string): RouteBuilder;
  middleware(mw: MiddlewareInterface): RouteBuilder;
}

export interface ResolvedRoute {
  readonly handler: RouteHandler;
  readonly params: Record<string, string>;
  readonly middleware: MiddlewareInterface[];
}

export interface RouteGroupOptions {
  readonly prefix?: string;
  readonly middleware?: MiddlewareInterface[];
}

export type RouteHandler =
  | ((request: RequestInterface) => ResponseInterface | Promise<ResponseInterface>)
  | [string, string]; // [ControllerClass, method]

// ============================================================================
// Request / Response
// ============================================================================

export interface RequestInterface {
  method(): string;
  httpMethod(): HttpMethodValue;
  uri(): string;
  path(): string;
  query(key?: string, defaultValue?: unknown): unknown;
  post(key?: string, defaultValue?: unknown): unknown;
  header(key: string, defaultValue?: string): string | null;
  server(key: string, defaultValue?: unknown): unknown;
  param(key: string, defaultValue?: unknown): unknown;
  body(): string;
  isJson(): boolean;
  isAjax(): boolean;
  ip(): string;
  requestId(): string;
  toContext(): RequestContext;
}

export interface ResponseInterface {
  getStatusCode(): number;
  getHeaders(): Record<string, string>;
  getBody(): string;
  withHeader(key: string, value: string): ResponseInterface;
  toData(): ResponseData;
}

export interface ResponseFactory {
  json(data: unknown, status?: number, headers?: Record<string, string>): ResponseInterface;
  html(content: string, status?: number, headers?: Record<string, string>): ResponseInterface;
  text(content: string, status?: number, headers?: Record<string, string>): ResponseInterface;
  redirect(url: string, status?: number): ResponseInterface;
  file(path: string, filename?: string, mime?: string): ResponseInterface;
  download(path: string, filename?: string): ResponseInterface;
}

// ============================================================================
// Middleware
// ============================================================================

export interface MiddlewareInterface {
  handle(
    request: RequestInterface,
    next: (request: RequestInterface) => ResponseInterface | Promise<ResponseInterface>,
  ): ResponseInterface | Promise<ResponseInterface>;
}

// ============================================================================
// Event Bus
// ============================================================================

export interface EventBusInterface {
  listen(event: string, listener: EventListener, priority?: number): void;
  dispatch(event: string, data?: Record<string, unknown>): unknown[];
  hasListeners(event: string): boolean;
  removeListener(event: string, listener: EventListener): void;
}

export type EventListener = (data: Record<string, unknown>) => unknown;

// ============================================================================
// Validation
// ============================================================================

export interface ValidatorInterface {
  validate(): boolean;
  fails(): boolean;
  errors(): ValidationErrors;
  first(field: string): string | null;
  hasError(field: string): boolean;
}

// ============================================================================
// Utility Interfaces
// ============================================================================

export interface ConfigInterface {
  get<T = unknown>(key: string, defaultValue?: T): T;
  setDefaults(defaults: Record<string, unknown>): void;
  clearCache(): void;
}

export interface FileSystemInterface {
  read(path: string): Promise<string | null>;
  write(path: string, content: string): Promise<boolean>;
  readJson<T = unknown>(path: string): Promise<T | null>;
  writeJson(path: string, data: unknown): Promise<boolean>;
  exists(path: string): Promise<boolean>;
  delete(path: string): Promise<boolean>;
  ensureDir(dir: string): Promise<boolean>;
}

export interface JsonStorageInterface {
  read<T = unknown>(file: string, dir?: string): Promise<T>;
  write(file: string, data: unknown, dir?: string): Promise<void>;
  clearCache(): void;
}
