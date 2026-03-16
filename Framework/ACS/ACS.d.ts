/**
 * Adlaire Client Services (ACS) — Public Type Definitions
 *
 * ACS が公開する型定義ファイル。
 * 各フレームワークは import type のみでこのファイルを参照できる。
 * 実装の import（値の import）は禁止する。
 *
 * FRAMEWORK_RULEBOOK §3.4 準拠
 *
 * @package ACS
 * @version 2.0.0
 * @license Adlaire License Ver.2.0
 */

// ============================================================================
// AdlaireClient — ACS の中核インターフェース
// ============================================================================

/**
 * AdlaireClient — ACS の中核インターフェース
 *
 * 全フレームワークはこのインターフェースを通じてサーバ（ASS）と通信する。
 * 直接 HTTP 呼び出しは禁止。
 */
export interface AdlaireClient {
  readonly auth: AuthModule;
  readonly storage: StorageModule;
  readonly files: FileModule;
  readonly http: HttpModule;
}

// ============================================================================
// Auth Module
// ============================================================================

/**
 * 認証モジュール
 */
export interface AuthModule {
  login(username: string, password: string): Promise<AuthResult>;
  logout(): Promise<void>;
  session(): Promise<SessionInfo | null>;
  verify(token: string): Promise<AuthResult>;
}

/**
 * 認証結果
 */
export interface AuthResult {
  readonly authenticated: boolean;
  readonly user: UserInfo | null;
  readonly token?: string;
  readonly error?: string;
}

/**
 * セッション情報
 */
export interface SessionInfo {
  readonly id: string;
  readonly userId: string;
  readonly role: UserRole;
  readonly createdAt: string;
  readonly expiresAt: string;
  readonly data: Record<string, unknown>;
}

/**
 * ユーザー情報
 */
export interface UserInfo {
  readonly id: string;
  readonly username: string;
  readonly role: UserRole;
  readonly createdAt?: string;
  readonly lastLogin?: string;
}

/**
 * ユーザーロール
 */
export type UserRole = "admin" | "editor" | "viewer";

// ============================================================================
// Storage Module
// ============================================================================

/**
 * ストレージモジュール — JSON ファイルの読み書き
 */
export interface StorageModule {
  read<T = unknown>(file: string, directory?: string): Promise<T | null>;
  write(file: string, data: unknown, directory?: string): Promise<boolean>;
  delete(file: string, directory?: string): Promise<boolean>;
  exists(file: string, directory?: string): Promise<boolean>;
  list(directory: string, extension?: string): Promise<string[]>;
}

// ============================================================================
// File Module
// ============================================================================

/**
 * ファイルモジュール — バイナリファイル操作
 */
export interface FileModule {
  upload(file: File | Blob, path: string): Promise<WriteOperation>;
  download(path: string): Promise<Blob>;
  delete(path: string): Promise<boolean>;
  exists(path: string): Promise<boolean>;
  info(path: string): Promise<ImageInfo | null>;
}

/**
 * 書き込み操作結果
 */
export interface WriteOperation {
  readonly success: boolean;
  readonly path: string;
  readonly size?: number;
  readonly error?: string;
}

/**
 * 画像情報
 */
export interface ImageInfo {
  readonly width: number;
  readonly height: number;
  readonly mime: string;
  readonly size: number;
  readonly aspect: number;
}

// ============================================================================
// HTTP Module
// ============================================================================

/**
 * HTTP モジュール — 低レベル HTTP アクセス（ASS エンドポイント向け）
 */
export interface HttpModule {
  get<T = unknown>(endpoint: string, params?: Record<string, string>): Promise<ApiResponse<T>>;
  post<T = unknown>(endpoint: string, body?: unknown): Promise<ApiResponse<T>>;
  put<T = unknown>(endpoint: string, body?: unknown): Promise<ApiResponse<T>>;
  delete<T = unknown>(endpoint: string): Promise<ApiResponse<T>>;
}

/**
 * API レスポンスの標準形式
 */
export interface ApiResponse<T = unknown> {
  readonly ok: boolean;
  readonly data?: T;
  readonly error?: string;
  readonly errors?: Record<string, string[]>;
}
