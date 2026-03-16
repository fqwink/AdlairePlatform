/**
 * Adlaire Platform Foundation (APF) — Interface Definitions
 *
 * DI コンテナ、Router、Request/Response、Middleware、イベントバスなど
 * APF が公開する全インターフェースを定義する。
 *
 * @package APF
 * @version 2.0.0
 * @license Adlaire License Ver.2.0
 */

import type {
  HttpMethodValue,
  RequestContext,
  ResponseData,
  RouteDefinition,
  ValidationErrors,
  ValidationRules,
  LogLevelValue,
  DatabaseConfig,
  PaginatedResponse,
  RateLimitConfig,
  CorsConfig,
} from "../types.ts";

// ============================================================================
// DI Container
// ============================================================================

export interface ContainerInterface {
  bind(name: string, factory: (...args: unknown[]) => unknown): void;
  singleton(name: string, factory: (...args: unknown[]) => unknown): void;
  make<T = unknown>(name: string): T;
  has(name: string): boolean;
}

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
// Database
// ============================================================================

export interface ConnectionInterface {
  connect(): Promise<void>;
  query<T = Record<string, unknown>>(sql: string, bindings?: unknown[]): Promise<T[]>;
  queryOne<T = Record<string, unknown>>(sql: string, bindings?: unknown[]): Promise<T | null>;
  execute(sql: string, bindings?: unknown[]): Promise<number>;
  insert(sql: string, bindings?: unknown[]): Promise<number>;
  update(sql: string, bindings?: unknown[]): Promise<number>;
  transaction<T>(callback: () => Promise<T>): Promise<T>;
  enableQueryLog(): void;
  getQueryLog(): Array<{ sql: string; bindings: unknown[]; time: number }>;
}

export interface QueryBuilderInterface<T = Record<string, unknown>> {
  table(name: string): QueryBuilderInterface<T>;
  select(...columns: string[]): QueryBuilderInterface<T>;
  where(column: string, operator: string, value?: unknown): QueryBuilderInterface<T>;
  orWhere(column: string, operator: string, value?: unknown): QueryBuilderInterface<T>;
  whereIn(column: string, values: unknown[]): QueryBuilderInterface<T>;
  whereNull(column: string): QueryBuilderInterface<T>;
  whereNotNull(column: string): QueryBuilderInterface<T>;
  whereBetween(column: string, range: [unknown, unknown]): QueryBuilderInterface<T>;
  whereLike(column: string, pattern: string): QueryBuilderInterface<T>;
  join(table: string, first: string, operator: string, second: string): QueryBuilderInterface<T>;
  leftJoin(table: string, first: string, operator: string, second: string): QueryBuilderInterface<T>;
  orderBy(column: string, direction?: "asc" | "desc"): QueryBuilderInterface<T>;
  groupBy(...columns: string[]): QueryBuilderInterface<T>;
  having(condition: string, bindings?: unknown[]): QueryBuilderInterface<T>;
  limit(limit: number): QueryBuilderInterface<T>;
  offset(offset: number): QueryBuilderInterface<T>;
  get(): Promise<T[]>;
  first(): Promise<T | null>;
  count(): Promise<number>;
  exists(): Promise<boolean>;
  paginate(page?: number, perPage?: number): Promise<PaginatedResponse<T>>;
  insert(data: Record<string, unknown>): Promise<number>;
  insertGetId(data: Record<string, unknown>): Promise<number>;
  insertBatch(records: Record<string, unknown>[]): Promise<number>;
  update(data: Record<string, unknown>): Promise<number>;
  delete(): Promise<number>;
  toSql(): string;
}

export interface ModelInterface {
  save(): Promise<boolean>;
  delete(): Promise<boolean>;
  toArray(): Record<string, unknown>;
  toJson(): string;
  setAttribute(key: string, value: unknown): void;
  getAttribute(key: string): unknown;
}

export interface SchemaInterface {
  create(table: string, callback: (blueprint: BlueprintInterface) => void): Promise<void>;
  drop(table: string): Promise<void>;
  hasTable(table: string): Promise<boolean>;
}

export interface BlueprintInterface {
  id(): void;
  string(name: string, length?: number): void;
  text(name: string): void;
  integer(name: string): void;
  bigInteger(name: string): void;
  float(name: string): void;
  double(name: string): void;
  decimal(name: string, total?: number, places?: number): void;
  boolean(name: string): void;
  date(name: string): void;
  datetime(name: string): void;
  timestamp(name: string): void;
  timestamps(): void;
}

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
// Cache
// ============================================================================

export interface CacheInterface {
  get<T = unknown>(key: string, defaultValue?: T): Promise<T | null>;
  set(key: string, value: unknown, ttl?: number): Promise<boolean>;
  has(key: string): Promise<boolean>;
  delete(key: string): Promise<boolean>;
  clear(): Promise<boolean>;
  remember<T>(key: string, ttl: number, callback: () => Promise<T>): Promise<T>;
  forever(key: string, value: unknown): Promise<boolean>;
}

// ============================================================================
// Logger
// ============================================================================

export interface LoggerInterface {
  debug(message: string, context?: Record<string, unknown>): void;
  info(message: string, context?: Record<string, unknown>): void;
  warning(message: string, context?: Record<string, unknown>): void;
  error(message: string, context?: Record<string, unknown>): void;
  critical(message: string, context?: Record<string, unknown>): void;
  setLevel(level: LogLevelValue): void;
  setFormat(format: "text" | "json"): void;
  channel(name: string): LoggerInterface;
}

// ============================================================================
// Session
// ============================================================================

export interface SessionInterface {
  start(): void;
  get<T = unknown>(key: string, defaultValue?: T): T | null;
  set(key: string, value: unknown): void;
  has(key: string): boolean;
  delete(key: string): void;
  clear(): void;
  destroy(): void;
  regenerate(): void;
  flash(key: string, value: unknown): void;
  getFlash<T = unknown>(key: string, defaultValue?: T): T | null;
  id(): string;
}

// ============================================================================
// Security
// ============================================================================

export interface SecurityInterface {
  hash(value: string): Promise<string>;
  verify(value: string, hash: string): Promise<boolean>;
  randomString(length?: number): string;
  csrfToken(): string;
  verifyCsrf(token: string): boolean;
  escape(value: string): string;
  sanitize(value: string): string;
  encrypt(data: string, key: string): string;
  decrypt(data: string, key: string): string;
  rateLimit(key: string, maxAttempts: number, decayMinutes: number): boolean;
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
