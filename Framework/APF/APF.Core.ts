/**
 * Adlaire Platform Foundation (APF) — Core Module
 *
 * DI コンテナ、Router、Request/Response、Middleware パイプラインを提供する。
 * PHP APF.Core.php + APF.Middleware.php からの移植。
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
} from "../types.ts";

import type {
  ContainerInterface,
  RouterInterface,
  RouteBuilder,
  RouteGroupOptions,
  ResolvedRoute,
  RouteHandler,
  RequestInterface,
  ResponseInterface,
  ResponseFactory,
  MiddlewareInterface,
  EventBusInterface,
  EventListener,
} from "./APF.Interface.ts";

import { HttpMethod, NotFoundError } from "./APF.Class.ts";

// ============================================================================
// Container — DI コンテナ
// ============================================================================

export class Container implements ContainerInterface {
  private factories = new Map<string, (...args: unknown[]) => unknown>();
  private singletons = new Map<string, unknown>();
  private isSingleton = new Set<string>();

  bind(name: string, factory: (...args: unknown[]) => unknown): void {
    this.factories.set(name, factory);
    this.isSingleton.delete(name);
    this.singletons.delete(name);
  }

  singleton(name: string, factory: (...args: unknown[]) => unknown): void {
    this.factories.set(name, factory);
    this.isSingleton.add(name);
  }

  make<T = unknown>(name: string): T {
    if (this.singletons.has(name)) {
      return this.singletons.get(name) as T;
    }

    const factory = this.factories.get(name);
    if (!factory) {
      throw new Error(`No binding for: ${name}`);
    }

    const instance = factory();

    if (this.isSingleton.has(name)) {
      this.singletons.set(name, instance);
    }

    return instance as T;
  }

  has(name: string): boolean {
    return this.factories.has(name);
  }
}

// ============================================================================
// Router
// ============================================================================

interface RouteEntry {
  methods: HttpMethodValue[];
  pattern: RegExp;
  paramNames: string[];
  handler: RouteHandler;
  middleware: MiddlewareInterface[];
  name?: string;
  rawPath: string;
}

export class Router implements RouterInterface {
  private routes: RouteEntry[] = [];
  private globalMiddleware: MiddlewareInterface[] = [];
  private groupStack: RouteGroupOptions[] = [];
  private queryMappings: Array<{ param: string; path: string; valueParam?: string }> = [];
  private postMappings: Array<{ param: string; path: string }> = [];

  get(path: string, handler: RouteHandler): RouteBuilder {
    return this.addRoute(["GET", "HEAD"], path, handler);
  }

  post(path: string, handler: RouteHandler): RouteBuilder {
    return this.addRoute(["POST"], path, handler);
  }

  put(path: string, handler: RouteHandler): RouteBuilder {
    return this.addRoute(["PUT"], path, handler);
  }

  patch(path: string, handler: RouteHandler): RouteBuilder {
    return this.addRoute(["PATCH"], path, handler);
  }

  delete(path: string, handler: RouteHandler): RouteBuilder {
    return this.addRoute(["DELETE"], path, handler);
  }

  any(path: string, handler: RouteHandler): RouteBuilder {
    return this.addRoute(["GET", "HEAD", "POST", "PUT", "PATCH", "DELETE", "OPTIONS"], path, handler);
  }

  match(methods: HttpMethodValue[], path: string, handler: RouteHandler): RouteBuilder {
    return this.addRoute(methods, path, handler);
  }

  group(options: RouteGroupOptions, callback: (router: RouterInterface) => void): void {
    this.groupStack.push(options);
    callback(this);
    this.groupStack.pop();
  }

  middleware(mw: MiddlewareInterface): void {
    this.globalMiddleware.push(mw);
  }

  resolve(method: HttpMethodValue, path: string): ResolvedRoute | null {
    for (const route of this.routes) {
      if (!route.methods.includes(method)) continue;

      const match = route.pattern.exec(path);
      if (!match) continue;

      const params: Record<string, string> = {};
      for (let i = 0; i < route.paramNames.length; i++) {
        params[route.paramNames[i]] = match[i + 1] ?? "";
      }

      return {
        handler: route.handler,
        params,
        middleware: [...this.globalMiddleware, ...route.middleware],
      };
    }

    return null;
  }

  mapQuery(param: string, path: string, valueParam?: string): void {
    this.queryMappings.push({ param, path, valueParam });
  }

  mapPost(param: string, path: string): void {
    this.postMappings.push({ param, path });
  }

  /**
   * クエリパラメータベースの URL を新しいパスに変換する
   */
  resolveQueryMapping(query: Record<string, string>, method: string): string | null {
    if (method === "POST") {
      for (const { param, path } of this.postMappings) {
        if (param in query) return path;
      }
    }
    for (const { param, path, valueParam } of this.queryMappings) {
      if (param in query) {
        if (valueParam && query[param]) {
          return path.replace(`{${valueParam}}`, query[param]);
        }
        return path;
      }
    }
    return null;
  }

  listRoutes(): RouteDefinition[] {
    return this.routes.map((r) => ({
      method: r.methods.length === 1 ? r.methods[0] : r.methods,
      path: r.rawPath,
      handler: typeof r.handler === "function" ? "(closure)" : `${r.handler[0]}.${r.handler[1]}`,
      middleware: r.middleware.map((m) => m.constructor.name),
      name: r.name,
    }));
  }

  // ── Internal ──

  private addRoute(
    methods: HttpMethodValue[],
    path: string,
    handler: RouteHandler,
  ): RouteBuilder {
    const fullPath = this.buildFullPath(path);
    const { pattern, paramNames } = this.compilePath(fullPath);
    const mw = this.collectGroupMiddleware();

    const entry: RouteEntry = {
      methods,
      pattern,
      paramNames,
      handler,
      middleware: mw,
      rawPath: fullPath,
    };
    this.routes.push(entry);

    return {
      name(name: string): RouteBuilder {
        entry.name = name;
        return this;
      },
      middleware(m: MiddlewareInterface): RouteBuilder {
        entry.middleware.push(m);
        return this;
      },
    };
  }

  private buildFullPath(path: string): string {
    const prefixes = this.groupStack
      .map((g) => g.prefix ?? "")
      .filter(Boolean);
    const full = [...prefixes, path].join("");
    return full.replace(/\/+/g, "/");
  }

  private collectGroupMiddleware(): MiddlewareInterface[] {
    const mw: MiddlewareInterface[] = [];
    for (const group of this.groupStack) {
      if (group.middleware) {
        mw.push(...group.middleware);
      }
    }
    return mw;
  }

  private compilePath(path: string): { pattern: RegExp; paramNames: string[] } {
    const paramNames: string[] = [];
    const regexStr = path.replace(/\{(\w+)\}/g, (_match, name: string) => {
      paramNames.push(name);
      return "([^/]+)";
    });
    return {
      pattern: new RegExp(`^${regexStr}$`),
      paramNames,
    };
  }
}

// ============================================================================
// Request
// ============================================================================

export class Request implements RequestInterface {
  private readonly _method: HttpMethodValue;
  private readonly _uri: string;
  private readonly _path: string;
  private readonly _query: Record<string, string>;
  private readonly _postData: Record<string, unknown>;
  private readonly _headers: Record<string, string>;
  private readonly _serverVars: Record<string, unknown>;
  private readonly _body: string;
  private readonly _requestId: string;
  private _params: Record<string, string> = {};

  constructor(init: {
    method: HttpMethodValue;
    uri: string;
    path: string;
    query?: Record<string, string>;
    postData?: Record<string, unknown>;
    headers?: Record<string, string>;
    server?: Record<string, unknown>;
    body?: string;
  }) {
    this._method = init.method;
    this._uri = init.uri;
    this._path = init.path;
    this._query = init.query ?? {};
    this._postData = init.postData ?? {};
    this._headers = init.headers ?? {};
    this._serverVars = init.server ?? {};
    this._body = init.body ?? "";
    this._requestId = crypto.randomUUID();
  }

  /**
   * Deno の標準 Request から変換する
   */
  static fromDenoRequest(req: globalThis.Request): Request {
    const url = new URL(req.url);
    const query: Record<string, string> = {};
    url.searchParams.forEach((v, k) => {
      query[k] = v;
    });

    const headers: Record<string, string> = {};
    req.headers.forEach((v, k) => {
      headers[k.toLowerCase()] = v;
    });

    return new Request({
      method: req.method.toUpperCase() as HttpMethodValue,
      uri: req.url,
      path: url.pathname,
      query,
      headers,
      body: "", // body は必要時に別途読み込み
    });
  }

  method(): string {
    return this._method;
  }

  httpMethod(): HttpMethodValue {
    return this._method;
  }

  uri(): string {
    return this._uri;
  }

  path(): string {
    return this._path;
  }

  query(key?: string, defaultValue?: unknown): unknown {
    if (key === undefined) return this._query;
    return this._query[key] ?? defaultValue;
  }

  post(key?: string, defaultValue?: unknown): unknown {
    if (key === undefined) return this._postData;
    return this._postData[key] ?? defaultValue;
  }

  header(key: string, defaultValue?: string): string | null {
    return this._headers[key.toLowerCase()] ?? defaultValue ?? null;
  }

  server(key: string, defaultValue?: unknown): unknown {
    return this._serverVars[key] ?? defaultValue;
  }

  param(key: string, defaultValue?: unknown): unknown {
    return this._params[key] ?? this._query[key] ?? this._postData[key] ?? defaultValue;
  }

  body(): string {
    return this._body;
  }

  isJson(): boolean {
    const ct = this.header("content-type") ?? "";
    return ct.includes("application/json");
  }

  isAjax(): boolean {
    return this.header("x-requested-with")?.toLowerCase() === "xmlhttprequest";
  }

  ip(): string {
    return (
      (this.header("x-forwarded-for") ?? "").split(",")[0]?.trim() ||
      (this._serverVars["REMOTE_ADDR"] as string) ||
      "127.0.0.1"
    );
  }

  requestId(): string {
    return this._requestId;
  }

  toContext(): RequestContext {
    return {
      method: this._method,
      path: this._path,
      query: this._query,
      headers: this._headers,
      body: this._body,
      ip: this.ip(),
      requestId: this._requestId,
      isJson: this.isJson(),
      isAjax: this.isAjax(),
    };
  }

  /** ルーターが解決したパスパラメータをセットする */
  setParams(params: Record<string, string>): void {
    this._params = params;
  }
}

// ============================================================================
// Response
// ============================================================================

export class Response implements ResponseInterface {
  constructor(
    private readonly _body: string,
    private readonly _statusCode: number,
    private _headers: Record<string, string>,
  ) {}

  static json(
    data: unknown,
    status: number = 200,
    headers: Record<string, string> = {},
  ): Response {
    return new Response(
      JSON.stringify(data),
      status,
      { "Content-Type": "application/json; charset=utf-8", ...headers },
    );
  }

  static html(
    content: string,
    status: number = 200,
    headers: Record<string, string> = {},
  ): Response {
    return new Response(
      content,
      status,
      { "Content-Type": "text/html; charset=utf-8", ...headers },
    );
  }

  static text(
    content: string,
    status: number = 200,
    headers: Record<string, string> = {},
  ): Response {
    return new Response(
      content,
      status,
      { "Content-Type": "text/plain; charset=utf-8", ...headers },
    );
  }

  static redirect(url: string, status: number = 302): Response {
    return new Response("", status, { Location: url });
  }

  static notFound(message: string = "Not Found"): Response {
    return Response.json({ ok: false, error: message }, 404);
  }

  static error(message: string, status: number = 500): Response {
    return Response.json({ ok: false, error: message }, status);
  }

  getStatusCode(): number {
    return this._statusCode;
  }

  getHeaders(): Record<string, string> {
    return { ...this._headers };
  }

  getBody(): string {
    return this._body;
  }

  withHeader(key: string, value: string): ResponseInterface {
    const newHeaders = { ...this._headers, [key]: value };
    return new Response(this._body, this._statusCode, newHeaders);
  }

  toData(): ResponseData {
    return {
      statusCode: this._statusCode,
      headers: { ...this._headers },
      body: this._body,
      contentType: this._headers["Content-Type"] ?? "text/plain",
    };
  }

  /**
   * Deno の標準 Response に変換する
   */
  toDenoResponse(): globalThis.Response {
    return new globalThis.Response(this._body || null, {
      status: this._statusCode,
      headers: this._headers,
    });
  }
}

// ============================================================================
// Middleware Pipeline — ミドルウェアパイプライン実行
// ============================================================================

export class MiddlewarePipeline {
  /**
   * ミドルウェアチェーンを実行し、最終ハンドラの結果を返す
   */
  static async run(
    request: Request,
    middleware: MiddlewareInterface[],
    handler: (req: RequestInterface) => ResponseInterface | Promise<ResponseInterface>,
  ): Promise<ResponseInterface> {
    if (middleware.length === 0) {
      return handler(request);
    }

    let index = 0;

    const next = async (req: RequestInterface): Promise<ResponseInterface> => {
      if (index >= middleware.length) {
        return handler(req);
      }
      const mw = middleware[index++];
      return mw.handle(req, next);
    };

    return next(request);
  }
}

// ============================================================================
// Application — リクエスト処理のエントリポイント
// ============================================================================

/**
 * Adlaire Application
 *
 * Deno HTTP サーバとの統合ポイント。
 * Router + Middleware + HybridResolver を統合する。
 */
export class Application {
  private readonly container: Container;
  private readonly router: Router;

  constructor() {
    this.container = new Container();
    this.router = new Router();

    this.container.singleton("router", () => this.router);
    this.container.singleton("container", () => this.container);
  }

  getContainer(): Container {
    return this.container;
  }

  getRouter(): Router {
    return this.router;
  }

  /**
   * Deno HTTP サーバ向けのリクエストハンドラ
   */
  async handleRequest(denoReq: globalThis.Request): Promise<globalThis.Response> {
    const request = Request.fromDenoRequest(denoReq);

    // POST body の読み込み
    if (request.httpMethod() === "POST" || request.httpMethod() === "PUT" || request.httpMethod() === "PATCH") {
      // body は Request 構築時に渡す必要があるため、
      // 実運用では fromDenoRequest を拡張して body を読み込む
    }

    // クエリパラメータマッピング（後方互換）
    const queryMap = this.router.resolveQueryMapping(
      request.query() as Record<string, string>,
      request.method(),
    );
    const resolvedPath = queryMap ?? request.path();

    // ルート解決
    const route = this.router.resolve(request.httpMethod(), resolvedPath);
    if (!route) {
      return Response.notFound().toDenoResponse();
    }

    // パラメータをリクエストにセット
    request.setParams(route.params);

    try {
      // ミドルウェアパイプライン実行
      const handler = route.handler;
      const handlerFn = typeof handler === "function"
        ? handler
        : (_req: RequestInterface) => {
            // [ControllerClass, method] パターンは AP.Core.ts で解決
            throw new Error(`Controller handler not resolved: ${handler[0]}.${handler[1]}`);
          };

      const response = await MiddlewarePipeline.run(
        request,
        route.middleware,
        handlerFn as (req: RequestInterface) => ResponseInterface | Promise<ResponseInterface>,
      );

      return (response as Response).toDenoResponse();
    } catch (error: unknown) {
      if (error instanceof NotFoundError) {
        return Response.notFound(error.message).toDenoResponse();
      }
      const message = error instanceof Error ? error.message : "Internal Server Error";
      return Response.error(message, 500).toDenoResponse();
    }
  }
}

// ============================================================================
// EventBus — イベントバス
// ============================================================================

export class EventBus implements EventBusInterface {
  private listeners = new Map<string, Array<{ fn: EventListener; priority: number }>>();

  listen(event: string, listener: EventListener, priority: number = 0): void {
    if (!this.listeners.has(event)) {
      this.listeners.set(event, []);
    }
    const list = this.listeners.get(event)!;
    list.push({ fn: listener, priority });
    list.sort((a, b) => b.priority - a.priority);
  }

  dispatch(event: string, data?: Record<string, unknown>): unknown[] {
    const list = this.listeners.get(event);
    if (!list) return [];
    return list.map(({ fn }) => fn(data ?? {}));
  }

  hasListeners(event: string): boolean {
    return (this.listeners.get(event)?.length ?? 0) > 0;
  }

  removeListener(event: string, listener: EventListener): void {
    const list = this.listeners.get(event);
    if (!list) return;
    const idx = list.findIndex((entry) => entry.fn === listener);
    if (idx !== -1) list.splice(idx, 1);
  }
}
