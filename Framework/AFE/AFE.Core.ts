/**
 * Adlaire Foundation Engine (AFE) — Core Module
 *
 * Router、Request/Response、Middleware パイプライン、EventBus を提供する。
 *
 * FRAMEWORK_RULEBOOK v3.0 §2.1 準拠:
 * - DI コンテナパターンは廃止
 * - サービス間の依存解決は ApplicationFacade のプロパティ直接参照により行う
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
} from "../ACS/ACS.d.ts";

import type {
  EventBusInterface,
  EventListener,
  MiddlewareInterface,
  RequestInterface,
  ResolvedRoute,
  ResponseInterface,
  RouteBuilder,
  RouteGroupOptions,
  RouteHandler,
  RouterInterface,
} from "./AFE.Interface.ts";

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
    return this.addRoute(
      ["GET", "HEAD", "POST", "PUT", "PATCH", "DELETE", "OPTIONS"],
      path,
      handler,
    );
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
        params[route.paramNames[i]] = decodeURIComponent(match[i + 1] ?? "");
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
          const value = query[param];
          if (!value || value.includes("..") || value.includes("/")) return null;
          return path.replace(`{${valueParam}}`, value);
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
  static async fromDenoRequest(req: globalThis.Request): Promise<Request> {
    const url = new URL(req.url);
    const query: Record<string, string> = {};
    url.searchParams.forEach((v, k) => {
      query[k] = v;
    });

    const headers: Record<string, string> = {};
    req.headers.forEach((v, k) => {
      headers[k.toLowerCase()] = v;
    });

    const method = req.method.toUpperCase() as HttpMethodValue;
    let body = "";
    const MAX_BODY_SIZE = 10 * 1024 * 1024; // 10MB
    if (method !== "GET" && method !== "HEAD") {
      const contentLength = parseInt(headers["content-length"] ?? "0", 10);
      if (contentLength > MAX_BODY_SIZE) {
        body = "";
      } else {
        try {
          body = await req.text();
          if (body.length > MAX_BODY_SIZE) body = "";
        } catch {
          // body not available
        }
      }
    }

    let postData: Record<string, unknown> = {};
    if (body && headers["content-type"]?.includes("application/json")) {
      try {
        postData = JSON.parse(body);
      } catch { /* non-JSON body */ }
    } else if (body && headers["content-type"]?.includes("application/x-www-form-urlencoded")) {
      const params = new URLSearchParams(body);
      params.forEach((v, k) => {
        postData[k] = v;
      });
    } else if (headers["content-type"]?.includes("multipart/form-data")) {
      // Re-create the request to parse FormData (body was consumed as text above)
      try {
        const formReq = new globalThis.Request(req.url, {
          method: req.method,
          headers: req.headers,
          body: new TextEncoder().encode(body),
        });
        const formData = await formReq.formData();
        for (const [k, v] of formData.entries()) {
          if (typeof v === "string") {
            postData[k] = v;
          } else {
            // File objects are kept as-is for upload handlers
            postData[k] = v;
          }
        }
      } catch { /* FormData parse failed */ }
    }

    return new Request({
      method,
      uri: req.url,
      path: url.pathname,
      query,
      postData,
      headers,
      body,
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
      postData: this._postData,
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
      contentType:
        Object.entries(this._headers).find(([k]) => k.toLowerCase() === "content-type")?.[1] ??
          "text/plain",
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
  static run(
    request: Request,
    middleware: MiddlewareInterface[],
    handler: (req: RequestInterface) => ResponseInterface | Promise<ResponseInterface>,
  ): Promise<ResponseInterface> {
    if (middleware.length === 0) {
      return Promise.resolve(handler(request));
    }

    let index = 0;

    const dispatch = (req: RequestInterface): Promise<ResponseInterface> => {
      if (index >= middleware.length) {
        return Promise.resolve(handler(req));
      }
      const mw = middleware[index++];
      let called = false;
      const next = (r: RequestInterface): Promise<ResponseInterface> => {
        if (called) throw new Error("next() called multiple times");
        called = true;
        return dispatch(r);
      };
      return Promise.resolve(mw.handle(req, next));
    };

    return Promise.resolve(dispatch(request));
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
    // Binary insertion to maintain sorted order (descending priority)
    let lo = 0;
    let hi = list.length;
    while (lo < hi) {
      const mid = (lo + hi) >>> 1;
      if (list[mid].priority >= priority) lo = mid + 1;
      else hi = mid;
    }
    list.splice(lo, 0, { fn: listener, priority });
  }

  dispatch(event: string, data?: Record<string, unknown>): unknown[] {
    const list = this.listeners.get(event);
    if (!list) return [];
    const results: unknown[] = [];
    for (const { fn } of list) {
      try {
        results.push(fn(data ?? {}));
      } catch (e) {
        console.error("Event listener error:", e);
      }
    }
    return results;
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
