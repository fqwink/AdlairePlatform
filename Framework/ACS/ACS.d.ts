/**
 * Adlaire Client Services (ACS) — Public Type Definitions
 *
 * ACS が公開する型定義ファイル。
 * 各フレームワークは import type のみでこのファイルを参照できる。
 * 実装の import（値の import）は禁止する。
 *
 * FRAMEWORK_RULEBOOK v3.0 §2.1, §3.4 準拠:
 * - 共有型ファイル（Framework/types.ts）の作成禁止
 * - 複数フレームワークから参照される型は ACS.d.ts で公開する
 *
 * @package ACS
 * @version 3.0.0
 * @license Adlaire License Ver.2.0
 */

// ============================================================================
// HTTP / Routing — 基盤型定義
// ============================================================================

/**
 * HTTP メソッド列挙
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
  readonly postData: Record<string, unknown>;
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
 * バリデーションルール定義
 */
export type ValidationRules = Record<string, string>;

/**
 * バリデーションエラー
 */
export type ValidationErrors = Record<string, string[]>;

/**
 * ログレベル
 */
export type LogLevelValue = "debug" | "info" | "warning" | "error" | "critical";

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
// Router / Request / Response / Middleware — AFE公開インターフェース
// ============================================================================

/**
 * ルーターインターフェース
 */
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
  | [string, string];

/**
 * リクエストインターフェース
 */
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

/**
 * レスポンスインターフェース
 */
export interface ResponseInterface {
  getStatusCode(): number;
  getHeaders(): Record<string, string>;
  getBody(): string;
  withHeader(key: string, value: string): ResponseInterface;
  toData(): ResponseData;
}

/**
 * ミドルウェアインターフェース
 */
export interface MiddlewareInterface {
  handle(
    request: RequestInterface,
    next: (request: RequestInterface) => ResponseInterface | Promise<ResponseInterface>,
  ): ResponseInterface | Promise<ResponseInterface>;
}

/**
 * レスポンスコンストラクタ — Response クラスを DI で受け渡すための型
 */
export interface ResponseConstructor {
  json(data: unknown, status?: number, headers?: Record<string, string>): ResponseInterface;
  html(content: string, status?: number, headers?: Record<string, string>): ResponseInterface;
  text(content: string, status?: number, headers?: Record<string, string>): ResponseInterface;
  redirect(url: string, status?: number): ResponseInterface;
  notFound(message?: string): ResponseInterface;
  error(message: string, status?: number): ResponseInterface;
  new (body: string, statusCode: number, headers: Record<string, string>): ResponseInterface;
}

// ============================================================================
// Content / Pages — コンテンツ型定義
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
 * Front matter パース結果
 */
export interface FrontMatterResult {
  readonly meta: Record<string, unknown>;
  readonly body: string;
}

// ============================================================================
// Collections — コレクション型定義（ACE/ASG 共通）
// ============================================================================

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

// ============================================================================
// Site Settings — サイト設定（AIS/ASG 共通）
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
  readMany<T = unknown>(requests: ReadManyRequest[]): Promise<(T | null)[]>;
  write(file: string, data: unknown, directory?: string): Promise<boolean>;
  delete(file: string, directory?: string): Promise<boolean>;
  exists(file: string, directory?: string): Promise<boolean>;
  list(directory: string, extension?: string): Promise<string[]>;
}

/**
 * readMany 用リクエスト
 */
export interface ReadManyRequest {
  readonly file: string;
  readonly directory?: string;
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
