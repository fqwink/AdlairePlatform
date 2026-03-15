# AdlairePlatform 詳細設計

## 1. 方針

- サーバ関係は変更しない
- その他全機能を TypeScript に移植
- 全フレームワークは5ファイル構成（Core / Api / Utils / Interface / Class）
- フレームワーク間の直接依存はゼロ。参照先は `Framework/types.ts` のみ
- Adlaire Server System + Adlaire Client SDK を並行開発
- 外部依存ゼロ
- FTP アップロードだけで動く

## 2. 3プロダクト構成

| プロダクト | 言語 | 動作場所 |
|-----------|------|---------|
| Adlaire Server System (ASS) | PHP | 共有レンタルサーバ |
| Adlaire Client Services (ACS) | TypeScript | ブラウザ + Deno |
| AdlairePlatform (APF/ACE/AIS/ASG/AP) | TypeScript | ローカルビルド + ブラウザ SPA |

## 3. 5ファイル構成の役割

| エンジン | 役割 |
|---------|------|
| Core | ビジネスロジック |
| Api | ACS（Client SDK）との接続を担うアダプター層 |
| Utils | ユーティリティ・補助機能 |
| Interface | インターフェース定義 |
| Class | データモデル・型定義 |

## 4. 共有層（`Framework/types.ts`）

全フレームワークが参照する唯一の共通ファイル。フレームワーク間の直接 import は禁止。

```typescript
// 認証
export interface AuthResult { ok: boolean; error?: string; user?: UserInfo }
export interface SessionInfo { loggedIn: boolean; username: string; role: string; lastActivity: number }
export interface UserInfo { username: string; role: string; createdAt?: string }

// コンテンツ
export interface PageData { slug: string; content: string; title?: string; meta?: PageMeta }
export interface PageMeta { description?: string; keywords?: string[]; draft?: boolean; publishedAt?: string }

// コレクション
export interface CollectionSchema { name: string; label: string; fields: FieldDef[]; createdAt: string }
export interface FieldDef { name: string; type: FieldType; required?: boolean; default?: unknown }
export type FieldType = 'string' | 'text' | 'number' | 'boolean' | 'date' | 'datetime' | 'array' | 'image';
export interface CollectionItem { slug: string; collection: string; fields: Record<string, unknown>; meta: ItemMeta; createdAt: string; updatedAt: string }
export interface ItemMeta { title?: string; description?: string; tags?: string[]; draft?: boolean }

// サイト設定
export interface SiteSettings { title: string; description: string; keywords: string; copyright: string; menu: string; themeSelect: string; subside: string }

// ビルド
export interface BuildResult { status: 'complete' | 'error'; pagesBuilt: number; elapsed: number; errors: string[] }

// テーマ
export interface ThemeConfig { name: string; template: string; style: string; directory: string }

// 診断
export interface DiagnosticsReport { totalTime: number; memoryPeak: number; events: DiagEvent[]; queries: QueryLog[] }
export interface DiagEvent { time: number; category: string; message: string; context: Record<string, unknown> }
export interface QueryLog { sql: string; bindings: unknown[]; time: number }

// Webhook
export interface WebhookConfig { url: string; events: string[]; secret?: string; active: boolean }

// API キー
export interface ApiKeyInfo { key: string; label: string; permissions: string[]; createdAt: string; lastUsed?: string }

// リビジョン
export interface RevisionEntry { field: string; content: string; timestamp: string; restored?: boolean }

// ヘルスチェック
export interface HealthCheckResult { status: 'ok' | 'warning' | 'error'; checks: Record<string, { ok: boolean; detail?: string }> }

// エラーハンドリング
export type Result<T, E = Error> = { ok: true; value: T } | { ok: false; error: E };

// ストレージ操作
export interface WriteOperation { action: 'write' | 'delete'; file: string; dir?: string; data?: Record<string, unknown> }

// ACS インターフェース
export interface AdlaireClient {
  auth: {
    login(username: string, password: string): Promise<AuthResult>;
    logout(): Promise<void>;
    session(): Promise<SessionInfo | null>;
    csrfToken(): Promise<string>;
    hashPassword(plain: string): Promise<string>;
    verifyPassword(plain: string, hash: string): Promise<boolean>;
  };
  storage: {
    read(file: string, dir?: string): Promise<Record<string, unknown>>;
    write(file: string, data: Record<string, unknown>, dir?: string): Promise<void>;
    delete(file: string, dir?: string): Promise<void>;
    exists(file: string, dir?: string): Promise<boolean>;
    list(dir: string): Promise<string[]>;
    batch(operations: WriteOperation[]): Promise<void>;
    optimisticWrite(file: string, data: Record<string, unknown>, dir?: string): void;
  };
  files: {
    upload(file: File | Uint8Array, path: string): Promise<{ ok: boolean; path?: string }>;
    delete(path: string): Promise<void>;
    list(dir: string): Promise<string[]>;
  };
  http: {
    fetch(url: string, init?: RequestInit): Promise<Response>;
  };
}
```

## 5. 変換パターン

### 5.1 言語構造

| PHP | TypeScript |
|-----|-----------|
| `enum Foo: string { case A = 'a'; method(){} }` | `class Foo { static readonly A = new Foo('a'); method(){} }` |
| `private readonly array $x;` | `private readonly x: Record<string, unknown>;` |
| `?string` | `string \| null` |
| `mixed` | 型定義に置換。不明な場合のみ `unknown` |
| `\Closure` | `(...args: unknown[]) => unknown` |
| `class Foo extends \RuntimeException` | `class Foo extends Error` |
| `public const NAME = '';` | `static readonly NAME = '';` |
| `array` 返却型 | `Framework/types.ts` の型に置換 |

### 5.2 PHP 固有 API

| PHP | TypeScript |
|-----|-----------|
| `$_SESSION` | `client.auth.session()` |
| `password_hash` / `password_verify` | `client.auth.hashPassword` / `verifyPassword` |
| `json_read` / `json_write` | `client.storage.read` / `write` |
| `move_uploaded_file` | `client.files.upload` |
| `curl_*` | `client.http.fetch` |
| `header()` / `http_response_code` | `Response` オブジェクト |
| `htmlspecialchars` | 自前エスケープ関数 |
| `preg_match` / `preg_replace` | `RegExp` / `String.replace()` |
| `json_encode` / `json_decode` | `JSON.stringify` / `JSON.parse` |
| `hrtime(true)` | `performance.now() * 1_000_000` |
| `memory_get_usage` | `Deno.memoryUsage().heapUsed` / `0` |
| `bin2hex(random_bytes(8))` | `crypto.randomUUID()` |
| `getenv()` | `Deno.env.get()` / `undefined` |
| `ReflectionClass` | 削除（クロージャ DI） |
| `strtotime` / `date` | `new Date()` / `Intl.DateTimeFormat` |

## 6. APF — Adlaire Platform Foundation

### APF.Interface.ts

| インターフェース | メソッド |
|----------------|---------|
| `ContainerInterface` | `bind`, `singleton`, `make`, `instance`, `lazy`, `has`, `alias`, `bindIf`, `flush`, `getBindings` |
| `RouterInterface` | `get`, `post`, `put`, `patch`, `delete`, `any`, `group`, `middleware`, `dispatch`, `name`, `route` |
| `RequestInterface` | `method`, `httpMethod`, `uri`, `query`, `post`, `json`, `header`, `cookie`, `ip`, `isJson`, `isAjax`, `isPost`, `param`, `requestId` |
| `ResponseInterface` | `getContent`, `getStatusCode`, `getHeaders`, `withHeader` |
| `MiddlewareInterface` | `handle(request, next)` |
| `ValidatorInterface` | `validate`, `fails`, `errors` |
| `CacheInterface` | `get`, `set`, `delete`, `has`, `flush`, `stats` |
| `LoggerInterface` | `debug`, `info`, `warning`, `error`, `critical` |
| `ConfigInterface` | `get`, `setDefaults`, `clearCache` |

### APF.Class.ts

| クラス | 役割 |
|--------|------|
| `HttpMethod` | HTTP メソッド列挙（`class + static readonly`）。`isSafe()`, `isIdempotent()`, `from()` |
| `LogLevel` | ログレベル列挙。`fromName()`, `label` |
| `FrameworkError` | 基底エラー。`context` プロパティ |
| `ContainerError` | DI 解決エラー |
| `NotFoundError` | 404 |
| `RoutingError` | ルーティングエラー |
| `ValidationError` | バリデーションエラー。`errors` プロパティ |
| `MiddlewareError` | ミドルウェアエラー |
| `RequestInit` | Request コンストラクタ引数の型 |

### APF.Core.ts

| クラス | 役割 |
|--------|------|
| `Container` | DI コンテナ。`ContainerInterface` 実装。クロージャベース（ReflectionClass 不使用）。循環依存検出 |
| `Router` | ルーター。`RouterInterface` 実装。ミドルウェアパイプライン（`reduceRight`）。`mapQuery`, `mapPost` |
| `Request` | リクエスト。`RequestInterface` 実装。コンストラクタ注入（スーパーグローバル不使用） |
| `Response` | レスポンス。`ResponseInterface` 実装。静的ファクトリ（`json`, `html`, `redirect`, `jsonError`） |
| `HookManager` | フック管理。プライオリティソート + キャッシュ。`register`, `run`, `filter`, `has`, `remove`, `clear` |
| `PluginManager` | プラグイン管理。トポロジカルソート。循環依存検出 |
| `DebugCollector` | デバッグ収集。`performance.now()` ベース。`enterScope`/`exitScope` |
| `ErrorBoundary` | エラー境界。`wrap()`, `wrapResponse()`, `wrapResult()` |

### APF.Api.ts

| クラス | 役割 |
|--------|------|
| `APFAdapter` | ACS 経由で APF の設定・キャッシュ・ログを読み書きするアダプター |

### APF.Utils.ts

| クラス | 役割 |
|--------|------|
| `Config` | 設定管理。環境変数 → 設定ファイル → デフォルトの優先順 |
| `Validator` | バリデーション。`Result<ValidatedData>` を返す |
| `Cache` | `Map` ベースキャッシュ。`CacheInterface` 実装 |
| `Logger` | `console.*` + ファイル出力。`LoggerInterface` 実装 |
| `Security` | `escape()` 自前。認証系は `AdlaireClient.auth` 経由 |
| `Str` | 文字列ユーティリティ。`safePath`, `slug`, `truncate`, `random` |
| `Arr` | 配列ユーティリティ。`get<T>`, `set`, `has`, `flatten`, `only`, `except` |
| `FileSystem` | `AdlaireClient` + `Deno.*` 環境分岐 |
| `JsonStorage` | `AdlaireClient.storage` 委譲。`Map` キャッシュ |

## 7. ACE — Adlaire Content Engine

### ACE.Interface.ts

| インターフェース | メソッド |
|----------------|---------|
| `CollectionManagerInterface` | `create`, `delete`, `listCollections`, `getSchema`, `isEnabled` |
| `ContentManagerInterface` | `getItem`, `saveItem`, `deleteItem`, `listItems`, `search` |
| `MetaManagerInterface` | `extractMeta`, `buildMeta`, `mergeMeta` |
| `ContentValidatorInterface` | `validate` |
| `AuthManagerInterface` | `isLoggedIn`, `currentUser`, `hasRole`, `csrfToken`, `login`, `logout` |
| `RevisionManagerInterface` | `saveRevision`, `getRevisions`, `restoreRevision` |
| `WebhookManagerInterface` | `register`, `delete`, `list`, `dispatch` |
| `ApiRouterInterface` | `registerEndpoint`, `dispatch` |

### ACE.Class.ts

| クラス | 役割 |
|--------|------|
| `CollectionField` | フィールド定義モデル |
| `ContentItem` | コンテンツアイテムモデル |
| `Revision` | リビジョンモデル |
| `Webhook` | Webhook 設定モデル |
| `ApiKey` | API キーモデル |
| `User` | ユーザーモデル |

### ACE.Core.ts

| クラス | 役割 |
|--------|------|
| `CollectionManager` | スキーマバリデーション、CRUD。`Result<CollectionSchema>` を返す |
| `ContentManager` | コンテンツ CRUD。ソート・フィルタ・ページネーション。`Promise<CollectionItem \| null>` |
| `MetaManager` | YAML frontmatter パース |
| `ContentValidator` | スキーマ定義に基づくバリデーション。`Result<Record<string, unknown>>` |
| `AuthManager` | `AdlaireClient.auth` 全面委譲 |
| `UserManager` | `AdlaireClient.auth` + `AdlaireClient.storage` |
| `RevisionManager` | 差分比較。`RevisionEntry[]` |
| `ActivityLogger` | `AdlaireClient.storage` でログ記録 |
| `ApiRouter` | エンドポイント登録・ディスパッチ |
| `WebhookManager` | Webhook CRUD。送信 → `AdlaireClient.http.fetch` |
| `RateLimiter` | `Map` ベース + `AdlaireClient.storage` 永続化 |
| `ApiService` | API キー管理 |

### ACE.Api.ts

| クラス | 役割 |
|--------|------|
| `ACEAdapter` | ACS 経由でコンテンツ・コレクション・認証・Webhook を操作するアダプター |

### ACE.Utils.ts

| クラス | 役割 |
|--------|------|
| `SlugGenerator` | スラッグ生成・バリデーション |
| `ContentFormatter` | コンテンツ整形・サニタイズ |
| `SchemaValidator` | コレクションスキーマのバリデーション |

## 8. AIS — Adlaire Infrastructure Services

### AIS.Interface.ts

| インターフェース | メソッド |
|----------------|---------|
| `AppContextInterface` | `resolveHost`, `dataDir`, `settingsDir`, `contentDir` |
| `I18nInterface` | `init`, `t`, `locale`, `setLocale` |
| `HealthMonitorInterface` | `healthCheck`, `checkDisk`, `checkMemory` |
| `DiagnosticsInterface` | `log`, `startTimer`, `stopTimer`, `collect` |
| `UpdaterInterface` | `checkForUpdate`, `applyUpdate`, `rollback` |
| `GitSyncInterface` | `clone`, `pull`, `push`, `status`, `diff`, `log` |
| `BackupManagerInterface` | `create`, `restore`, `list`, `purge` |

### AIS.Class.ts

| クラス | 役割 |
|--------|------|
| `AppConfig` | アプリケーション設定モデル |
| `DiagnosticEntry` | 診断エントリモデル |
| `BackupInfo` | バックアップ情報モデル |
| `UpdateInfo` | アップデート情報モデル |
| `GitStatus` | Git ステータスモデル |

### AIS.Core.ts

| クラス | 役割 |
|--------|------|
| `AppContext` | アプリケーション状態管理シングルトン |
| `I18n` | 翻訳。`AdlaireClient.storage` or `Deno.readTextFile` |
| `CacheStore` | `Map` ベース + `AdlaireClient.storage` 永続化。`CacheInterface` 実装 |
| `AppLogger` | `LoggerInterface` 実装。構造化ログ（JSON） |
| `DiagnosticsCollector` | `performance.now()` 計測 |
| `HealthMonitor` | `AdlaireClient` 経由。`HealthCheckResult` |
| `ApiCache` | `Map` ベース。`CacheInterface` 実装 |
| `DiagnosticsManager` | 静的ファサード。全 API 維持 |
| `Updater` | GitHub API → `AdlaireClient.http.fetch` |
| `GitSync` | `Deno.Command("git", { args })` |
| `Mailer` | `AdlaireClient.http.fetch` |
| `BackupManager` | `AdlaireClient.storage` + `AdlaireClient.files` |

### AIS.Api.ts

| クラス | 役割 |
|--------|------|
| `AISAdapter` | ACS 経由で診断・更新・Git・バックアップを操作するアダプター |

### AIS.Utils.ts

| クラス | 役割 |
|--------|------|
| `TimerUtils` | タイマー管理ユーティリティ |
| `LogFormatter` | ログフォーマット・ローテーション |
| `VersionComparator` | セマンティックバージョン比較 |

## 9. ASG — Adlaire Static Generator

### ASG.Interface.ts

| インターフェース | メソッド |
|----------------|---------|
| `GeneratorInterface` | `buildAll`, `buildDiff`, `clean`, `getStatus` |
| `BuilderInterface` | `buildPage`, `buildIndex` |
| `TemplateRendererInterface` | `render`, `registerHelper`, `registerPartial` |
| `MarkdownParserInterface` | `parse`, `addRule` |
| `ThemeManagerInterface` | `load`, `getTemplate`, `listThemes`, `buildContext` |
| `SiteRouterInterface` | `resolveSlug`, `buildUrl`, `generateSitemap` |

### ASG.Class.ts

| クラス | 役割 |
|--------|------|
| `BuildStatus` | ビルド状態列挙（`Pending`, `Building`, `Complete`, `Error`） |
| `BuildConfig` | ビルド設定モデル |
| `PageOutput` | 生成済みページモデル |
| `SitemapEntry` | サイトマップエントリモデル |

### ASG.Core.ts

| クラス | 役割 |
|--------|------|
| `Generator` | ビルドオーケストレーター。`Result<BuildResult>` |
| `Builder` | ページ単位ビルド |
| `SiteRouter` | URL → スラッグ解決。サイトマップ生成 |
| `StaticFileSystem` | `Deno.writeTextFile` / `Deno.readTextFile` / `Deno.remove` |
| `TemplateRenderer` | Handlebars ライク構文パーサー。コンパイルキャッシュ。フィルターチェーン |
| `ThemeManager` | テーマ読み込み・バリデーション。`ThemeConfig` |
| `MarkdownParser` | Markdown → HTML。`addRule` でプラガブル |

### ASG.Api.ts

| クラス | 役割 |
|--------|------|
| `ASGAdapter` | ACS 経由でビルド・テーマ操作するアダプター |

### ASG.Utils.ts

| クラス | 役割 |
|--------|------|
| `BuildCache` | コンテンツハッシュ差分検出。`CacheInterface` 実装 |
| `ImageProcessor` | 画像最適化。`AdlaireClient.files` / `Deno.Command` |
| `DiffBuilder` | 差分検出 |
| `Deployer` | 静的ファイルデプロイ |

## 10. AP — Adlaire Platform Controllers

### AP.Interface.ts

| インターフェース | メソッド |
|----------------|---------|
| `ControllerInterface` | `ok`, `error`, `requireRole`, `validate` |
| `ActionDispatcherInterface` | `handle` |

### AP.Class.ts

| クラス | 役割 |
|--------|------|
| `ActionType` | アクション種別列挙 |
| `ControllerContext` | コントローラー実行コンテキストモデル |

### AP.Core.ts

| クラス | 役割 |
|--------|------|
| `BaseController` | 基底。`ok()`, `error()`, `requireRole()`, `validate()` → `Result<ValidatedData>` |
| `AuthController` | `AdlaireClient.auth.login()` / `logout()` |
| `DashboardController` | SPA データ取得 |
| `ApiController` | `AdlaireClient.storage` / `AdlaireClient.auth` 経由 |
| `AdminController` | `AdlaireClient.storage` 経由 |
| `CollectionController` | コレクション CRUD（自フレームワーク内で完結） |
| `GitController` | Git 操作（自フレームワーク内で完結） |
| `WebhookController` | Webhook 操作（自フレームワーク内で完結） |
| `StaticController` | 静的ビルド操作（自フレームワーク内で完結） |
| `UpdateController` | 更新操作（自フレームワーク内で完結） |
| `DiagnosticController` | 診断操作（自フレームワーク内で完結） |
| `ActionDispatcher` | SPA イベントハンドラ |

### AP.Api.ts

| クラス | 役割 |
|--------|------|
| `APAdapter` | ACS 経由で全 Controller のアクションを中継するアダプター |

### AP.Utils.ts

| クラス | 役割 |
|--------|------|
| `BridgeFunctions` | `json_read`, `json_write`, `h`, `getSlug`, `host` 等のユーティリティ関数 |

## 11. AEB — Adlaire Editor & Blocks

### AEB.Interface.js

| インターフェース（JSDoc） | メソッド |
|-------------------------|---------|
| `EditorInterface` | `init`, `destroy`, `getContent`, `setContent` |
| `BlockInterface` | `render`, `serialize`, `deserialize` |

### AEB.Class.js

| クラス | 役割 |
|--------|------|
| `BlockType` | ブロック種別定義 |
| `EditorState` | エディタ状態モデル |
| `BlockData` | ブロックデータモデル |

### AEB.Core.js

| クラス | 役割 |
|--------|------|
| `Editor` | エディタ本体 |
| `EventBus` | エディタ内イベント管理 |
| `BlockRegistry` | ブロック登録・生成 |
| `StateManager` | 状態管理 |
| `HistoryManager` | Undo/Redo |

### AEB.Api.js

| クラス | 役割 |
|--------|------|
| `AEBAdapter` | ACS 経由でエディタコンテンツを保存・読み込みするアダプター |

### AEB.Utils.js

| クラス | 役割 |
|--------|------|
| `Sanitizer` | HTML サニタイズ |
| `DomHelper` | DOM 操作ユーティリティ |
| `SelectionHelper` | テキスト選択ユーティリティ |
| `KeyboardHelper` | キーボードショートカット |

## 12. ADS — Adlaire Design System

### ADS.Interface.css

CSS カスタムプロパティ（変数）の定義。全コンポーネントが参照する設計トークン。

### ADS.Class.css

タイポグラフィ、リセット、基本要素のスタイル定義。

### ADS.Core.css

コンポーネントスタイル（buttons, forms, cards, modals, alerts, badges）。

### ADS.Api.css

エディタ・管理画面向けのスタイル定義。

### ADS.Utils.css

ユーティリティクラス（spacing, display, alignment, visibility）。

## 13. ACS — Adlaire Client Services

### ACS.Interface.ts

| インターフェース | メソッド |
|----------------|---------|
| `AdlaireClient` | `auth`, `storage`, `files`, `http`（§4 の定義と同一） |
| `AdapterInterface` | `request`, `configure` |

### ACS.Class.ts

| クラス | 役割 |
|--------|------|
| `ClientConfig` | クライアント設定モデル（エンドポイント URL、タイムアウト等） |
| `BatchQueue` | バッチ書き込みキューモデル |
| `OptimisticCache` | 楽観的更新のローカルキャッシュモデル |

### ACS.Core.ts

| クラス | 役割 |
|--------|------|
| `AdlaireClientImpl` | `AdlaireClient` インターフェースの実装。`fetch()` で ASS と通信 |
| `AuthClient` | 認証操作の実装 |
| `StorageClient` | JSON ストレージ操作の実装。バッチ処理・楽観的更新 |
| `FileClient` | ファイルアップロード操作の実装 |
| `HttpClient` | 外部 HTTP 通信の実装 |

### ACS.Api.ts

| クラス | 役割 |
|--------|------|
| `ACSAdapter` | ASS の PHP API エンドポイントへの HTTP リクエスト構築・送信 |
| `RequestBuilder` | リクエスト組み立て（認証ヘッダー、CSRF、JSON シリアライズ） |
| `ResponseParser` | レスポンス解析（エラーハンドリング、JSON パース） |

### ACS.Utils.ts

| クラス | 役割 |
|--------|------|
| `CookieParser` | Cookie パース（ブラウザ環境） |
| `Debouncer` | 楽観的更新の debounce |
| `RetryHandler` | 通信失敗時のリトライ |

## 14. ASS — Adlaire Server System

### ASS.Interface.php

| インターフェース | メソッド |
|----------------|---------|
| `AuthHandlerInterface` | `login`, `logout`, `session`, `csrfToken`, `hashPassword`, `verifyPassword` |
| `StorageHandlerInterface` | `read`, `write`, `delete`, `exists`, `list`, `batch` |
| `FileHandlerInterface` | `upload`, `delete`, `list` |

### ASS.Class.php

| クラス | 役割 |
|--------|------|
| `ApiRequest` | リクエストモデル |
| `ApiResponse` | レスポンスモデル |
| `SessionData` | セッションデータモデル |

### ASS.Core.php

| クラス | 役割 |
|--------|------|
| `Router` | API ルーティング。エンドポイント → ハンドラのマッピング |
| `AuthHandler` | 認証・セッション管理。bcrypt、CSRF、ログイン試行制限 |
| `StorageHandler` | JSON ファイル読み書き。`data/` 配下の CRUD |
| `FileHandler` | 画像アップロード。`uploads/` 配下 |
| `CorsHandler` | CORS ヘッダー制御 |

### ASS.Api.php

| クラス | 役割 |
|--------|------|
| `Dispatcher` | HTTP リクエスト受信 → Router → Handler → レスポンス送信 |
| `SecurityMiddleware` | セキュリティヘッダー、レート制限 |

### ASS.Utils.php

| クラス | 役割 |
|--------|------|
| `JsonFile` | JSON ファイル読み書きユーティリティ |
| `PathResolver` | パス解決・パストラバーサル防止 |
| `ErrorHandler` | エラーハンドリング・ログ出力 |

## 15. 非同期設計

- `AdlaireClient` 経由の I/O は全て `async` / `Promise`
- 純粋ロジック（バリデーション、テンプレート処理、文字列操作）は同期
- 独立した I/O は `Promise.all()` で並列化

```typescript
const [settings, auth, pages] = await Promise.all([
  client.storage.read('settings.json', 'settings'),
  client.storage.read('auth.json', 'settings'),
  client.storage.read('pages.json', 'content'),
]);
```

## 16. テンプレートコンパイルキャッシュ

`ASG.Core.ts` の `TemplateRenderer` に導入。同一テンプレートの2回目以降の `render()` を高速化。

```typescript
class TemplateRenderer {
  private cache = new Map<string, (ctx: Record<string, unknown>) => string>();

  render(template: string, context: Record<string, unknown>): string {
    let fn = this.cache.get(template);
    if (!fn) {
      fn = this.compile(template);
      this.cache.set(template, fn);
    }
    return fn(context);
  }

  private compile(template: string): (ctx: Record<string, unknown>) => string {
    const partials = this.extractPartials(template);
    const eachBlocks = this.extractEachBlocks(template);
    const ifBlocks = this.extractIfBlocks(template);
    return (ctx) => {
      let html = template;
      html = this.applyPartials(html, partials, ctx);
      html = this.applyEach(html, eachBlocks, ctx);
      html = this.applyIf(html, ifBlocks, ctx);
      html = this.processRawVars(html, ctx);
      html = this.processVars(html, ctx);
      return html;
    };
  }
}
```

## 17. 移植スケジュール

| Phase | 内容 | 工数 |
|-------|------|------|
| 0 | 共有層（`Framework/types.ts`） | 5h |
| 1 | APF（Interface, Class, Core, Api, Utils） | 38h |
| 2 | ACE（Interface, Class, Core, Api, Utils） | 33h |
| 3 | ASG（Interface, Class, Core, Api, Utils） | 35h |
| 4 | AIS（Interface, Class, Core, Api, Utils） | 27h |
| 5 | AP（Interface, Class, Core, Api, Utils） | 13h |
| 6 | AEB（Interface, Class, Core, Api, Utils） | 10h |
| 7 | ADS（Interface, Class, Core, Api, Utils） | 5h |
| 8 | ACS（Interface, Class, Core, Api, Utils） | 18h |
| 9 | ASS（Interface, Class, Core, Api, Utils） | 別途 |
| 10 | テスト（全フレームワーク Deno.test） | 20h |
| 合計 | ASS 除く | 204h |
