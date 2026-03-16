/**
 * Adlaire Client Services (ACS) — Interface Definitions
 *
 * サーバ通信の抽象化レイヤー。全フレームワークはこのインターフェースを
 * 通じて ASS（PHP サーバ）と通信する。直接 HTTP 呼び出しは禁止。
 *
 * @package ACS
 * @version 2.0.0
 * @license Adlaire License Ver.2.0
 */

import type {
  AdlaireClient,
  ApiResponse,
  AuthModule,
  AuthResult,
  FileModule,
  HttpModule,
  SessionInfo,
  StorageModule,
  WriteOperation,
} from "../types.ts";

// ============================================================================
// Client Factory
// ============================================================================

/**
 * AdlaireClient ファクトリ
 *
 * 環境に応じて適切な実装を返す。
 * - ブラウザ: fetch ベース
 * - Deno: Deno.HttpClient ベース
 * - テスト: モック実装
 */
export interface ClientFactoryInterface {
  create(config: ClientConfig): AdlaireClient;
}

export interface ClientConfig {
  readonly baseUrl: string;
  readonly apiPrefix?: string;
  readonly timeout?: number;
  readonly headers?: Record<string, string>;
  readonly credentials?: "include" | "omit" | "same-origin";
}

// ============================================================================
// Auth Module (Extended)
// ============================================================================

export interface AuthModuleInterface extends AuthModule {
  /** セッションクッキーからの自動認証 */
  autoLogin(): Promise<AuthResult>;

  /** CSRF トークンの取得 */
  csrfToken(): Promise<string>;

  /** CSRF トークンの検証 */
  verifyCsrf(token: string): Promise<boolean>;

  /** 認証状態の監視 */
  onAuthChange(callback: AuthChangeCallback): void;
}

export type AuthChangeCallback = (authenticated: boolean, user: SessionInfo | null) => void;

// ============================================================================
// Storage Module (Extended)
// ============================================================================

export interface StorageModuleInterface extends StorageModule {
  /** 複数ファイルの一括読み込み */
  readMany<T = unknown>(
    files: Array<{ file: string; directory?: string }>,
  ): Promise<Array<T | null>>;

  /** ファイルの変更監視 */
  watch(file: string, callback: (data: unknown) => void): () => void;
}

// ============================================================================
// File Module (Extended)
// ============================================================================

export interface FileModuleInterface extends FileModule {
  /** 画像の最適化アップロード（リサイズ + WebP 変換） */
  uploadImage(
    file: File | Blob,
    path: string,
    options?: ImageUploadOptions,
  ): Promise<WriteOperation>;

  /** サムネイル URL の取得 */
  thumbnailUrl(path: string): string;

  /** WebP URL の取得 */
  webpUrl(path: string): string;
}

export interface ImageUploadOptions {
  readonly maxWidth?: number;
  readonly maxHeight?: number;
  readonly quality?: number;
  readonly generateThumbnail?: boolean;
  readonly generateWebP?: boolean;
}

// ============================================================================
// HTTP Module (Extended)
// ============================================================================

export interface HttpModuleInterface extends HttpModule {
  /** リクエストインターセプター */
  onRequest(interceptor: RequestInterceptor): void;

  /** レスポンスインターセプター */
  onResponse(interceptor: ResponseInterceptor): void;

  /** リクエストのキャンセル */
  abort(requestId: string): void;
}

export type RequestInterceptor = (config: RequestConfig) => RequestConfig | Promise<RequestConfig>;
export type ResponseInterceptor = (response: ApiResponse) => ApiResponse | Promise<ApiResponse>;

export interface RequestConfig {
  readonly method: string;
  readonly url: string;
  readonly headers: Record<string, string>;
  readonly body?: unknown;
  readonly requestId?: string;
}

// ============================================================================
// Event Source (Server-Sent Events)
// ============================================================================

export interface EventSourceInterface {
  connect(endpoint: string): void;
  disconnect(): void;
  on(event: string, callback: (data: unknown) => void): void;
  off(event: string, callback: (data: unknown) => void): void;
  isConnected(): boolean;
}

// ============================================================================
// Retry & Error Handling
// ============================================================================

export interface RetryConfig {
  readonly maxRetries: number;
  readonly baseDelay: number;
  readonly maxDelay: number;
  readonly backoffFactor: number;
  readonly retryableStatuses: number[];
}

export const DEFAULT_RETRY_CONFIG: RetryConfig = {
  maxRetries: 3,
  baseDelay: 1000,
  maxDelay: 10000,
  backoffFactor: 2,
  retryableStatuses: [408, 429, 500, 502, 503, 504],
};
