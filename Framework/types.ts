/**
 * Adlaire Platform — Shared Type Definitions
 *
 * 全9フレームワーク間で参照される型・インターフェースの唯一の定義場所。
 * 各フレームワークは直接 import せず、このファイルのみを参照する。
 *
 * @package AdlairePlatform
 * @version 2.0.0
 * @license Adlaire License Ver.2.0
 */

// ============================================================================
// Generic Result Types
// ============================================================================

/**
 * API レスポンスの標準形式
 */
export interface ApiResponse<T = unknown> {
  readonly ok: boolean;
  readonly data?: T;
  readonly error?: string;
  readonly errors?: Record<string, string[]>;
}

/**
 * ページネーション付きレスポンス
 */
export interface PaginatedResponse<T> {
  readonly data: T[];
  readonly total: number;
  readonly page: number;
  readonly per_page: number;
  readonly total_pages: number;
}

// ============================================================================
// HTTP / Routing
// ============================================================================

/**
 * HTTP メソッド列挙
 *
 * FRAMEWORK_RULEBOOK: PHP enum → TypeScript static readonly クラスパターン
 * ただし types.ts では軽量な文字列リテラル型を使用
 */
export type HttpMethodValue =
  | "GET"
  | "POST"
  | "PUT"
  | "PATCH"
  | "DELETE"
  | "HEAD"
  | "OPTIONS";

/**
 * ルート定義
 */
export interface RouteDefinition {
  readonly method: HttpMethodValue | HttpMethodValue[];
  readonly path: string;
  readonly handler: string;
  readonly middleware?: string[];
  readonly name?: string;
}

/**
 * リクエストコンテキスト — フレームワーク間で受け渡すリクエスト情報
 */
export interface RequestContext {
  readonly method: HttpMethodValue;
  readonly path: string;
  readonly query: Record<string, string>;
  readonly headers: Record<string, string>;
  readonly body: string;
  readonly ip: string;
  readonly requestId: string;
  readonly isJson: boolean;
  readonly isAjax: boolean;
}

/**
 * レスポンスデータ
 */
export interface ResponseData {
  readonly statusCode: number;
  readonly headers: Record<string, string>;
  readonly body: string;
  readonly contentType: string;
}

/**
 * ミドルウェア識別子
 */
export type MiddlewareId =
  | "auth"
  | "csrf"
  | "rate_limit"
  | "security_headers"
  | "request_logging"
  | "cors";

// ============================================================================
// Authentication & Session
// ============================================================================

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
// Content / Pages
// ============================================================================

/**
 * ページデータ — CMS の基本コンテンツ単位
 */
export interface PageData {
  readonly slug: string;
  readonly title: string;
  readonly content: string;
  readonly html?: string;
  readonly meta: PageMeta;
  readonly updatedAt?: string;
  readonly createdAt?: string;
}

/**
 * ページメタ情報
 */
export interface PageMeta {
  readonly title?: string;
  readonly description?: string;
  readonly keywords?: string;
  readonly template?: string;
  readonly draft?: boolean;
  readonly date?: string;
  readonly author?: string;
  readonly image?: string;
  readonly order?: number;
  readonly redirect?: string;
  readonly [key: string]: unknown;
}

/**
 * リダイレクト定義
 */
export interface RedirectRule {
  readonly from: string;
  readonly to: string;
  readonly status: 301 | 302;
}

// ============================================================================
// Collections (ACE)
// ============================================================================

/**
 * コレクション定義スキーマ
 */
export interface CollectionSchema {
  readonly name: string;
  readonly label: string;
  readonly directory: string;
  readonly format: "markdown";
  readonly fields: Record<string, FieldDef>;
  readonly sortBy?: string;
  readonly sortOrder?: "asc" | "desc";
  readonly perPage?: number;
  readonly template?: string;
  readonly indexTemplate?: string;
  readonly createdAt?: string;
}

/**
 * フィールド定義
 */
export interface FieldDef {
  readonly type: FieldType;
  readonly required?: boolean;
  readonly default?: unknown;
  readonly min?: number | string;
  readonly max?: number | string;
  readonly label?: string;
  readonly description?: string;
  readonly options?: string[];
}

/**
 * フィールドタイプ
 */
export type FieldType =
  | "string"
  | "text"
  | "number"
  | "boolean"
  | "date"
  | "datetime"
  | "array"
  | "image"
  | "select";

/**
 * コレクションアイテム — Markdown ファイル1件分
 */
export interface CollectionItem {
  readonly slug: string;
  readonly collection: string;
  readonly meta: ItemMeta;
  readonly body: string;
  readonly html?: string;
}

/**
 * アイテムメタデータ
 */
export interface ItemMeta {
  readonly title: string;
  readonly date?: string;
  readonly draft?: boolean;
  readonly tags?: string[];
  readonly [key: string]: unknown;
}

/**
 * コレクション一覧の要約情報
 */
export interface CollectionSummary {
  readonly name: string;
  readonly label: string;
  readonly directory: string;
  readonly format: string;
  readonly count: number;
}

// ============================================================================
// Static Site Generation (ASG)
// ============================================================================

/**
 * ビルドステータス
 */
export type BuildStatusValue = "pending" | "building" | "complete" | "error";

/**
 * ビルド結果
 */
export interface BuildResult {
  readonly status: BuildStatusValue;
  readonly stats: BuildStats;
  readonly changedFiles: string[];
  readonly warnings: string[];
  readonly elapsed: number;
}

/**
 * ビルド統計情報
 */
export interface BuildStats {
  readonly total: number;
  readonly built: number;
  readonly skipped: number;
  readonly deleted: number;
  readonly errors: number;
  readonly assets: number;
}

/**
 * ビルドマニフェスト — 差分ビルドの判断材料
 */
export interface BuildManifest {
  readonly changed: string[];
  readonly added: string[];
  readonly deleted: string[];
  readonly unchanged: string[];
  readonly stats: {
    readonly total: number;
    readonly changed: number;
    readonly added: number;
    readonly deleted: number;
    readonly unchanged: number;
    readonly needs_build: number;
  };
}

/**
 * ビルド状態の永続化形式
 */
export interface BuildState {
  readonly hashes: Record<string, string>;
  readonly settings_hash: string;
  readonly theme_hash: string;
  readonly timestamp: string;
  readonly version: string;
}

/**
 * テーマ設定
 */
export interface ThemeConfig {
  readonly name: string;
  readonly directory: string;
  readonly templates: Record<string, string>;
  readonly assets: string[];
  readonly partials?: Record<string, string>;
}

/**
 * テンプレートコンテキスト — テンプレートレンダリング時の変数群
 */
export interface TemplateContext {
  readonly site: SiteSettings;
  readonly page: PageData;
  readonly pages?: PageData[];
  readonly collections?: Record<string, CollectionItem[]>;
  readonly navigation?: NavigationItem[];
  readonly [key: string]: unknown;
}

/**
 * ナビゲーションアイテム
 */
export interface NavigationItem {
  readonly label: string;
  readonly url: string;
  readonly active?: boolean;
  readonly children?: NavigationItem[];
}

// ============================================================================
// Site Settings (AIS)
// ============================================================================

/**
 * サイト設定
 */
export interface SiteSettings {
  readonly title: string;
  readonly description: string;
  readonly url: string;
  readonly language: string;
  readonly theme: string;
  readonly timezone?: string;
  readonly perPage?: number;
  readonly cleanUrls?: boolean;
  readonly minifyHtml?: boolean;
  readonly [key: string]: unknown;
}

// ============================================================================
// Diagnostics & Health (AIS)
// ============================================================================

/**
 * 診断レポート
 */
export interface DiagnosticsReport {
  readonly events: DiagEvent[];
  readonly summary: {
    readonly total: number;
    readonly byChannel: Record<string, number>;
    readonly byLevel: Record<string, number>;
  };
  readonly collectedAt: string;
}

/**
 * 診断イベント
 */
export interface DiagEvent {
  readonly channel: string;
  readonly level: DiagLevel;
  readonly message: string;
  readonly context: Record<string, unknown>;
  readonly timestamp: string;
}

/**
 * 診断レベル
 */
export type DiagLevel = "debug" | "info" | "warning" | "error" | "critical";

/**
 * ヘルスチェック結果
 */
export interface HealthCheckResult {
  readonly status: "ok" | "degraded" | "error";
  readonly version: string;
  readonly runtime: string;
  readonly time: string;
  readonly checks?: Record<
    string,
    {
      readonly status: "ok" | "warning" | "error";
      readonly message: string;
      readonly value?: unknown;
    }
  >;
}

// ============================================================================
// Deployment & Updates (AIS)
// ============================================================================

/**
 * Git 操作結果
 */
export interface GitResult {
  readonly success: boolean;
  readonly output: string;
  readonly error?: string;
}

/**
 * Git ステータス
 */
export interface GitStatus {
  readonly branch: string;
  readonly clean: boolean;
  readonly modified: string[];
  readonly untracked: string[];
  readonly ahead: number;
  readonly behind: number;
}

/**
 * Git コミットログ
 */
export interface GitLogEntry {
  readonly hash: string;
  readonly message: string;
  readonly author: string;
  readonly date: string;
}

/**
 * アップデート情報
 */
export interface UpdateInfo {
  readonly available: boolean;
  readonly currentVersion: string;
  readonly latestVersion: string;
  readonly releaseNotes?: string;
  readonly downloadUrl?: string;
}

/**
 * バックアップエントリ
 */
export interface BackupEntry {
  readonly name: string;
  readonly createdAt: string;
  readonly size: number;
  readonly version: string;
}

// ============================================================================
// Webhooks (ACE)
// ============================================================================

/**
 * Webhook 設定
 */
export interface WebhookConfig {
  readonly url: string;
  readonly label: string;
  readonly events: WebhookEvent[];
  readonly secret?: string;
  readonly enabled: boolean;
}

/**
 * Webhook イベント種別
 */
export type WebhookEvent =
  | "content.created"
  | "content.updated"
  | "content.deleted"
  | "build.started"
  | "build.completed"
  | "deploy.completed";

// ============================================================================
// Revisions (ACE)
// ============================================================================

/**
 * リビジョンエントリ
 */
export interface RevisionEntry {
  readonly file: string;
  readonly timestamp: string;
  readonly size: number;
  readonly user: string;
  readonly restored: boolean;
  readonly pinned: boolean;
}

// ============================================================================
// Image Processing (ASG)
// ============================================================================

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
// Validation
// ============================================================================

/**
 * バリデーションルール定義
 *
 * PHP: `['field' => 'required|string|max:255']`
 * TS:  `{ field: 'required|string|max:255' }`
 */
export type ValidationRules = Record<string, string>;

/**
 * バリデーションエラー
 */
export type ValidationErrors = Record<string, string[]>;

// ============================================================================
// Log Levels (APF)
// ============================================================================

/**
 * ログレベル
 */
export type LogLevelValue = "debug" | "info" | "warning" | "error" | "critical";

// ============================================================================
// ACS — Adlaire Client Services (サーバ通信抽象化)
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
 * ストレージモジュール — JSON ファイルの読み書き
 */
export interface StorageModule {
  read<T = unknown>(file: string, directory?: string): Promise<T | null>;
  write(file: string, data: unknown, directory?: string): Promise<boolean>;
  delete(file: string, directory?: string): Promise<boolean>;
  exists(file: string, directory?: string): Promise<boolean>;
  list(directory: string, extension?: string): Promise<string[]>;
}

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
 * HTTP モジュール — 低レベル HTTP アクセス（ASS エンドポイント向け）
 */
export interface HttpModule {
  get<T = unknown>(endpoint: string, params?: Record<string, string>): Promise<ApiResponse<T>>;
  post<T = unknown>(endpoint: string, body?: unknown): Promise<ApiResponse<T>>;
  put<T = unknown>(endpoint: string, body?: unknown): Promise<ApiResponse<T>>;
  delete<T = unknown>(endpoint: string): Promise<ApiResponse<T>>;
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

// ============================================================================
// Search
// ============================================================================

/**
 * 検索結果
 */
export interface SearchResult {
  readonly collection: string;
  readonly slug: string;
  readonly title: string;
  readonly preview: string;
  readonly score?: number;
}

// ============================================================================
// i18n (AIS)
// ============================================================================

/**
 * ロケール識別子
 */
export type LocaleId = "ja" | "en" | string;

/**
 * 翻訳辞書
 */
export interface TranslationDict {
  [key: string]: string | TranslationDict;
}

// ============================================================================
// Rate Limiting
// ============================================================================

/**
 * レートリミット設定
 */
export interface RateLimitConfig {
  readonly maxRequests: number;
  readonly windowSeconds: number;
  readonly key?: string;
}

// ============================================================================
// CORS
// ============================================================================

/**
 * CORS 設定
 */
export interface CorsConfig {
  readonly allowedOrigins: string[];
  readonly allowedMethods: HttpMethodValue[];
  readonly allowedHeaders: string[];
  readonly maxAge: number;
}

// ============================================================================
// Sitemap
// ============================================================================

/**
 * サイトマップエントリ
 */
export interface SitemapEntry {
  readonly url: string;
  readonly lastmod?: string;
  readonly changefreq?: "always" | "hourly" | "daily" | "weekly" | "monthly" | "yearly" | "never";
  readonly priority?: number;
}

// ============================================================================
// Action Dispatcher (AP)
// ============================================================================

/**
 * アクション定義 — POST アクションディスパッチャー用
 */
export interface ActionDefinition {
  readonly name: string;
  readonly handler: string;
  readonly requiresAuth: boolean;
  readonly requiresCsrf: boolean;
}

// ============================================================================
// Markdown Front Matter
// ============================================================================

/**
 * Front matter パース結果
 */
export interface FrontMatterResult {
  readonly meta: Record<string, unknown>;
  readonly body: string;
}
