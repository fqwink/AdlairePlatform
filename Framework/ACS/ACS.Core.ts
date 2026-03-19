/**
 * Adlaire Client Services (ACS) — Core Module
 *
 * サーバ通信抽象化レイヤーの実装。
 * 全フレームワークは AdlaireClient を通じて ASS (PHP) と通信する。
 *
 * @package ACS
 * @version 2.0.0
 * @license Adlaire License Ver.2.0
 */

import type {
  AdlaireClient,
  ApiResponse,
  AuthResult,
  ImageInfo,
  SessionInfo,
  WriteOperation,
} from "./ACS.d.ts";

import type {
  AuthChangeCallback,
  AuthModuleInterface,
  ClientConfig,
  ClientFactoryInterface,
  EventSourceInterface,
  FileModuleInterface,
  HttpModuleInterface,
  ImageUploadOptions,
  RequestConfig,
  RequestInterceptor,
  ResponseInterceptor,
  RetryConfig,
  StorageModuleInterface,
} from "./ACS.Interface.ts";

import {
  API_ENDPOINTS,
  AuthError,
  ConnectionState,
  NetworkError,
  ServerError,
  TimeoutError,
} from "./ACS.Class.ts";

// ============================================================================
// Client Factory
// ============================================================================

/**
 * AdlaireClient ファクトリ — 環境に応じた実装を返す
 */
export class ClientFactory implements ClientFactoryInterface {
  create(config: ClientConfig): AdlaireClient {
    const httpModule = new HttpTransport(config);
    return {
      auth: new AuthService(httpModule, config),
      storage: new StorageService(httpModule),
      files: new FileService(httpModule, config),
      http: httpModule,
    };
  }
}

// ============================================================================
// HTTP Transport — 低レベル通信層
// ============================================================================

export class HttpTransport implements HttpModuleInterface {
  private readonly baseUrl: string;
  private readonly apiPrefix: string;
  private readonly timeout: number;
  private readonly defaultHeaders: Record<string, string>;
  private readonly credentials: RequestCredentials;
  private requestInterceptors: RequestInterceptor[] = [];
  private responseInterceptors: ResponseInterceptor[] = [];
  private abortControllers = new Map<string, AbortController>();

  constructor(config: ClientConfig) {
    this.baseUrl = config.baseUrl.replace(/\/$/, "");
    this.apiPrefix = config.apiPrefix ?? "";
    this.timeout = config.timeout ?? 30_000;
    this.defaultHeaders = {
      "Content-Type": "application/json",
      Accept: "application/json",
      ...config.headers,
    };
    this.credentials = config.credentials ?? "same-origin";
  }

  get<T = unknown>(
    endpoint: string,
    params?: Record<string, string>,
  ): Promise<ApiResponse<T>> {
    let url = this.buildUrl(endpoint);
    if (params) {
      const qs = new URLSearchParams(params).toString();
      url += (url.includes("?") ? "&" : "?") + qs;
    }
    return this.request<T>("GET", url);
  }

  post<T = unknown>(
    endpoint: string,
    body?: unknown,
  ): Promise<ApiResponse<T>> {
    return this.request<T>("POST", this.buildUrl(endpoint), body);
  }

  put<T = unknown>(
    endpoint: string,
    body?: unknown,
  ): Promise<ApiResponse<T>> {
    return this.request<T>("PUT", this.buildUrl(endpoint), body);
  }

  delete<T = unknown>(endpoint: string): Promise<ApiResponse<T>> {
    return this.request<T>("DELETE", this.buildUrl(endpoint));
  }

  onRequest(interceptor: RequestInterceptor): void {
    this.requestInterceptors.push(interceptor);
  }

  onResponse(interceptor: ResponseInterceptor): void {
    this.responseInterceptors.push(interceptor);
  }

  abort(requestId: string): void {
    const controller = this.abortControllers.get(requestId);
    if (controller) {
      controller.abort();
      this.abortControllers.delete(requestId);
    }
  }

  // ── Internal ──

  private buildUrl(endpoint: string): string {
    if (endpoint.startsWith("http://") || endpoint.startsWith("https://")) {
      return endpoint;
    }
    return `${this.baseUrl}${this.apiPrefix}${endpoint}`;
  }

  private async request<T>(
    method: string,
    url: string,
    body?: unknown,
    requestId?: string,
  ): Promise<ApiResponse<T>> {
    const controller = new AbortController();
    const id = requestId ?? crypto.randomUUID();
    this.abortControllers.set(id, controller);

    const timeoutId = setTimeout(() => controller.abort(), this.timeout);

    try {
      let config: RequestConfig = {
        method,
        url,
        headers: { ...this.defaultHeaders },
        body,
        requestId: id,
      };

      for (const interceptor of this.requestInterceptors) {
        config = await interceptor(config);
      }

      const fetchInit: RequestInit = {
        method: config.method,
        headers: config.headers,
        credentials: this.credentials,
        signal: controller.signal,
      };

      if (config.body !== undefined && method !== "GET" && method !== "HEAD") {
        if (config.body instanceof FormData) {
          fetchInit.body = config.body;
          // Remove Content-Type to let browser set multipart boundary
          delete (fetchInit.headers as Record<string, string>)["Content-Type"];
        } else {
          fetchInit.body = typeof config.body === "string"
            ? config.body
            : JSON.stringify(config.body);
        }
      }

      const response = await fetch(config.url, fetchInit);

      if (!response.ok) {
        if (response.status === 401 || response.status === 403) {
          throw new AuthError(
            `Authentication failed: ${response.status}`,
            response.status,
            url,
          );
        }
        if (response.status >= 500) {
          const text = await response.text().catch(() => "");
          throw new ServerError(
            `Server error: ${response.status}`,
            response.status,
            url,
            text,
          );
        }
        throw new NetworkError(
          `HTTP ${response.status}: ${response.statusText}`,
          response.status,
          url,
        );
      }

      const contentType = response.headers.get("content-type") ?? "";
      let result: ApiResponse<T>;

      if (contentType.includes("application/json")) {
        result = await response.json() as ApiResponse<T>;
      } else {
        const text = await response.text();
        result = { ok: true, data: text as unknown as T };
      }

      for (const interceptor of this.responseInterceptors) {
        result = await interceptor(result) as ApiResponse<T>;
      }

      return result;
    } catch (error: unknown) {
      if (error instanceof DOMException && error.name === "AbortError") {
        throw new TimeoutError(this.timeout, url);
      }
      if (
        error instanceof NetworkError || error instanceof AuthError || error instanceof ServerError
      ) {
        throw error;
      }
      throw new NetworkError(
        error instanceof Error ? error.message : String(error),
        undefined,
        url,
      );
    } finally {
      clearTimeout(timeoutId);
      this.abortControllers.delete(id);
    }
  }
}

// ============================================================================
// Auth Service
// ============================================================================

export class AuthService implements AuthModuleInterface {
  private currentSession: SessionInfo | null = null;
  private sessionCachedAt: number | null = null;
  private changeCallbacks: AuthChangeCallback[] = [];

  constructor(
    private readonly http: HttpModuleInterface,
    private readonly config: ClientConfig,
  ) {}

  async login(username: string, password: string): Promise<AuthResult> {
    const result = await this.http.post<AuthResult>(API_ENDPOINTS.AUTH_LOGIN, {
      username,
      password,
    });

    if (result.ok && result.data) {
      const authResult = result.data;
      if (authResult.authenticated && authResult.user) {
        const expiresAt = new Date(Date.now() + 24 * 60 * 60 * 1000).toISOString();
        this.currentSession = {
          id: crypto.randomUUID(),
          userId: authResult.user.id,
          role: authResult.user.role,
          createdAt: new Date().toISOString(),
          expiresAt,
          data: {},
        };
        this.sessionCachedAt = Date.now();
        this.notifyChange(true);
      }
      return authResult;
    }
    return { authenticated: false, user: null, error: result.error };
  }

  async logout(): Promise<void> {
    await this.http.post(API_ENDPOINTS.AUTH_LOGOUT);
    this.currentSession = null;
    this.notifyChange(false);
  }

  async session(): Promise<SessionInfo | null> {
    if (this.currentSession && this.sessionCachedAt && Date.now() - this.sessionCachedAt < 300000) {
      // Check if the cached session has expired
      if (this.currentSession.expiresAt && new Date(this.currentSession.expiresAt).getTime() < Date.now()) {
        this.currentSession = null;
        this.sessionCachedAt = null;
      } else {
        return this.currentSession;
      }
    }
    const result = await this.http.get<SessionInfo>(API_ENDPOINTS.AUTH_SESSION);
    if (result.ok && result.data) {
      this.currentSession = result.data;
      this.sessionCachedAt = Date.now();
    }
    return this.currentSession;
  }

  async verify(token: string): Promise<AuthResult> {
    const result = await this.http.post<AuthResult>(`${API_ENDPOINTS.AUTH_SESSION}/verify`, {
      token,
    });
    if (result.ok && result.data) {
      return result.data;
    }
    return { authenticated: false, user: null };
  }

  async autoLogin(): Promise<AuthResult> {
    const session = await this.session();
    if (session) {
      return {
        authenticated: true,
        user: {
          id: session.userId,
          username: "",
          role: session.role,
        },
      };
    }
    return { authenticated: false, user: null };
  }

  async csrfToken(): Promise<string> {
    const result = await this.http.get<{ token: string }>("/api/csrf");
    return result.data?.token ?? "";
  }

  async verifyCsrf(token: string): Promise<boolean> {
    const result = await this.http.post<{ valid: boolean }>("/api/csrf/verify", { token });
    return result.data?.valid ?? false;
  }

  onAuthChange(callback: AuthChangeCallback): void {
    this.changeCallbacks.push(callback);
  }

  private notifyChange(authenticated: boolean): void {
    for (const cb of this.changeCallbacks) {
      cb(authenticated, this.currentSession);
    }
  }
}

// ============================================================================
// Storage Service
// ============================================================================

export class StorageService implements StorageModuleInterface {
  constructor(private readonly http: HttpModuleInterface) {}

  async read<T = unknown>(file: string, directory?: string): Promise<T | null> {
    const params: Record<string, string> = { file };
    if (directory) params.dir = directory;
    const result = await this.http.get<T>("/api/storage/read", params);
    return result.ok ? (result.data ?? null) : null;
  }

  async write(file: string, data: unknown, directory?: string): Promise<boolean> {
    const result = await this.http.post("/api/storage/write", {
      file,
      data,
      dir: directory,
    });
    return result.ok;
  }

  async delete(file: string, directory?: string): Promise<boolean> {
    const result = await this.http.post("/api/storage/delete", {
      file,
      dir: directory,
    });
    return result.ok;
  }

  async exists(file: string, directory?: string): Promise<boolean> {
    const params: Record<string, string> = { file };
    if (directory) params.dir = directory;
    const result = await this.http.get<{ exists: boolean }>("/api/storage/exists", params);
    return result.data?.exists ?? false;
  }

  async list(directory: string, extension?: string): Promise<string[]> {
    const params: Record<string, string> = { dir: directory };
    if (extension) params.ext = extension;
    const result = await this.http.get<string[]>("/api/storage/list", params);
    return result.data ?? [];
  }

  readMany<T = unknown>(
    files: Array<{ file: string; directory?: string }>,
  ): Promise<Array<T | null>> {
    return Promise.all(
      files.map(({ file, directory }) => this.read<T>(file, directory)),
    );
  }

  watch(_file: string, _callback: (data: unknown) => void): () => void {
    // SSE ベースの実装は EventSourceService で行う
    // ここではスタブとして即座に unsubscribe を返す
    return () => {};
  }
}

// ============================================================================
// File Service
// ============================================================================

export class FileService implements FileModuleInterface {
  private readonly baseUrl: string;

  constructor(
    private readonly http: HttpModuleInterface,
    config: ClientConfig,
  ) {
    this.baseUrl = config.baseUrl.replace(/\/$/, "");
  }

  async upload(file: File | Blob, path: string): Promise<WriteOperation> {
    const formData = new FormData();
    formData.append("file", file);
    formData.append("path", path);

    // FormData は Content-Type を自動設定するため、JSON ヘッダーを除外
    const result = await this.http.post<WriteOperation>("/api/files/upload", formData);
    if (result.ok && result.data) {
      return result.data;
    }
    return { success: false, path, error: result.error ?? "Upload failed" };
  }

  async download(path: string): Promise<Blob> {
    const response = await fetch(
      `${this.baseUrl}/api/files/download?path=${encodeURIComponent(path)}`,
      {
        credentials: "same-origin",
      },
    );
    if (!response.ok) {
      throw new NetworkError(`Download failed: ${response.status}`, response.status, path);
    }
    return response.blob();
  }

  async delete(path: string): Promise<boolean> {
    const result = await this.http.post("/api/files/delete", { path });
    return result.ok;
  }

  async exists(path: string): Promise<boolean> {
    const result = await this.http.get<{ exists: boolean }>("/api/files/exists", {
      path,
    });
    return result.data?.exists ?? false;
  }

  async info(path: string): Promise<ImageInfo | null> {
    const result = await this.http.get<ImageInfo>("/api/files/info", { path });
    return result.ok ? (result.data ?? null) : null;
  }

  async uploadImage(
    file: File | Blob,
    path: string,
    options?: ImageUploadOptions,
  ): Promise<WriteOperation> {
    const formData = new FormData();
    formData.append("file", file);
    formData.append("path", path);
    if (options) {
      formData.append("options", JSON.stringify(options));
    }
    const result = await this.http.post<WriteOperation>("/api/files/upload-image", formData);
    if (result.ok && result.data) {
      return result.data;
    }
    return { success: false, path, error: result.error ?? "Image upload failed" };
  }

  thumbnailUrl(path: string): string {
    const dir = path.substring(0, path.lastIndexOf("/"));
    const file = path.substring(path.lastIndexOf("/") + 1);
    return `${this.baseUrl}/${dir}/thumb/${file}`;
  }

  webpUrl(path: string): string {
    return `${this.baseUrl}/${path.replace(/\.\w+$/, ".webp")}`;
  }
}

// ============================================================================
// Event Source Service (SSE)
// ============================================================================

export class EventSourceService implements EventSourceInterface {
  private source: EventSource | null = null;
  private listeners = new Map<string, Set<(data: unknown) => void>>();
  private nativeListeners = new Map<string, Map<(data: unknown) => void, EventListener>>();
  private state = ConnectionState.DISCONNECTED;

  constructor(private readonly baseUrl: string) {}

  connect(endpoint: string): void {
    if (this.source) {
      this.disconnect();
    }

    this.state = ConnectionState.CONNECTING;
    this.source = new EventSource(`${this.baseUrl}${endpoint}`, {
      withCredentials: true,
    });

    this.source.onopen = () => {
      this.state = ConnectionState.CONNECTED;
    };

    this.source.onerror = () => {
      this.state = ConnectionState.RECONNECTING;
    };

    this.source.onmessage = (event) => {
      const callbacks = this.listeners.get("message");
      if (callbacks) {
        try {
          const data = JSON.parse(event.data);
          for (const cb of callbacks) cb(data);
        } catch {
          // Non-JSON message data — skip
        }
      }
    };

    // Re-register listeners added before connect()
    for (const [event, callbacks] of this.listeners) {
      if (event === "message") continue;
      for (const callback of callbacks) {
        const wrapper = (e: Event) => {
          try {
            const data = JSON.parse((e as MessageEvent).data);
            callback(data);
          } catch {
            // Non-JSON message data — skip
          }
        };
        if (!this.nativeListeners.has(event)) {
          this.nativeListeners.set(event, new Map());
        }
        this.nativeListeners.get(event)!.set(callback, wrapper);
        this.source!.addEventListener(event, wrapper);
      }
    }
  }

  disconnect(): void {
    if (this.source) {
      this.source.close();
      this.source = null;
    }
    this.nativeListeners.clear();
    this.state = ConnectionState.DISCONNECTED;
  }

  on(event: string, callback: (data: unknown) => void): void {
    if (!this.listeners.has(event)) {
      this.listeners.set(event, new Set());
    }
    this.listeners.get(event)!.add(callback);

    if (this.source && event !== "message") {
      const wrapper = (e: Event) => {
        try {
          const data = JSON.parse((e as MessageEvent).data);
          callback(data);
        } catch {
          // Non-JSON message data — skip
        }
      };
      if (!this.nativeListeners.has(event)) {
        this.nativeListeners.set(event, new Map());
      }
      this.nativeListeners.get(event)!.set(callback, wrapper);
      this.source.addEventListener(event, wrapper);
    }
  }

  off(event: string, callback: (data: unknown) => void): void {
    this.listeners.get(event)?.delete(callback);

    const eventMap = this.nativeListeners.get(event);
    if (eventMap && this.source) {
      const wrapper = eventMap.get(callback);
      if (wrapper) {
        this.source.removeEventListener(event, wrapper);
        eventMap.delete(callback);
      }
    }
  }

  isConnected(): boolean {
    return this.state.isActive();
  }
}

// ============================================================================
// Retry Helper
// ============================================================================

export async function withRetry<T>(
  fn: () => Promise<T>,
  config: RetryConfig,
): Promise<T> {
  let lastError: unknown;

  for (let attempt = 0; attempt <= config.maxRetries; attempt++) {
    try {
      return await fn();
    } catch (error: unknown) {
      lastError = error;

      if (attempt === config.maxRetries) break;

      const isRetryable = error instanceof NetworkError &&
        error.statusCode !== undefined &&
        config.retryableStatuses.includes(error.statusCode);

      if (!isRetryable && !(error instanceof TimeoutError)) {
        break;
      }

      const delay = Math.min(
        config.baseDelay * Math.pow(config.backoffFactor, attempt),
        config.maxDelay,
      );
      await new Promise((resolve) => setTimeout(resolve, delay));
    }
  }

  throw lastError;
}

// ============================================================================
// Convenience: createBasicClient
// ============================================================================

/**
 * AdlaireClient のショートカット生成関数（基本構成）
 */
export function createBasicClient(config: ClientConfig): AdlaireClient {
  return new ClientFactory().create(config);
}
