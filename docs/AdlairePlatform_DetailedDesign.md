# AdlairePlatform 詳細設計

Ver.0.1-1 | 最終更新: 2026-03-16

---

## 1. 概要

本書は AdlairePlatform の詳細設計を定める。設計・開発・運用の規則については `FRAMEWORK_RULEBOOK_v3.0.md` を参照のこと。

### 1.1 設計方針

- エンジン駆動モデル（FRAMEWORK_RULEBOOK §2）に基づき、全フレームワークを設計する
- フレームワーク間の直接 `import` は禁止し、ACS グローバルシングルトン（FRAMEWORK_RULEBOOK §3）を経由する
- 外部依存ゼロ — サードパーティライブラリおよび外部パッケージへの依存は禁止する
- フラットファイル JSON ストレージを採用し、データベースを不要とする
- FTP アップロードのみで動作する設計とする

### 1.2 プロダクト構成

| プロダクト | 言語 | 動作環境 |
|-----------|------|---------|
| Adlaire Server System（ASS） | PHP 8.4 以上 | 共有レンタルサーバ |
| Adlaire Client Services（ACS） | TypeScript | ブラウザおよび Deno |
| AdlairePlatform（APF / ACE / AIS / ASG / AP / AEB / ADS） | TypeScript / CSS | Deno サーバおよびブラウザ |

---

## 2. 共有型定義（`Framework/types.ts`）

全フレームワークが参照する唯一の共通型定義ファイルである。フレームワーク間の直接 `import` は禁止し、型の共有は本ファイルを通じて行う。

### 2.1 汎用型

| 型 | 用途 |
|---|------|
| `ApiResponse<T>` | API レスポンスの標準形式（`ok`, `data`, `error`, `errors`） |
| `PaginatedResponse<T>` | ページネーション付きレスポンス |

### 2.2 HTTP・ルーティング型

| 型 | 用途 |
|---|------|
| `HttpMethodValue` | HTTP メソッドの文字列リテラル型（`GET` / `POST` / `PUT` / `PATCH` / `DELETE` / `HEAD` / `OPTIONS`） |
| `RouteDefinition` | ルート定義（`method`, `path`, `handler`, `middleware`, `name`） |
| `RequestContext` | リクエストコンテキスト（`method`, `path`, `query`, `postData`, `headers`, `body`, `ip`, `requestId`） |
| `ResponseData` | レスポンスデータ（`statusCode`, `headers`, `body`, `contentType`） |
| `MiddlewareId` | ミドルウェア識別子（`auth` / `csrf` / `rate_limit` / `security_headers` / `request_logging` / `cors`） |

### 2.3 認証・セッション型

| 型 | 用途 |
|---|------|
| `AuthResult` | 認証結果（`authenticated`, `user`, `token`, `error`） |
| `SessionInfo` | セッション情報（`id`, `userId`, `role`, `createdAt`, `expiresAt`, `data`） |
| `UserInfo` | ユーザー情報（`id`, `username`, `role`, `createdAt`, `lastLogin`） |
| `UserRole` | ユーザーロール（`admin` / `editor` / `viewer`） |

### 2.4 コンテンツ型

| 型 | 用途 |
|---|------|
| `PageData` | ページデータ（`slug`, `title`, `content`, `html`, `meta`） |
| `PageMeta` | ページメタ情報（`title`, `description`, `keywords`, `template`, `draft`, `date`, `author`） |
| `RedirectRule` | リダイレクト定義（`from`, `to`, `status`） |

### 2.5 コレクション型

| 型 | 用途 |
|---|------|
| `CollectionSchema` | コレクション定義スキーマ（`name`, `label`, `directory`, `format`, `fields`, `sortBy`, `sortOrder`） |
| `FieldDef` | フィールド定義（`type`, `required`, `default`, `min`, `max`, `label`, `options`） |
| `FieldType` | フィールドタイプ（`string` / `text` / `number` / `boolean` / `date` / `datetime` / `array` / `image` / `select`） |
| `CollectionItem` | コレクションアイテム（`slug`, `collection`, `meta`, `body`, `html`） |
| `ItemMeta` | アイテムメタデータ（`title`, `date`, `draft`, `tags`） |
| `CollectionSummary` | コレクション要約情報（`name`, `label`, `directory`, `format`, `count`） |

### 2.6 静的サイト生成型

| 型 | 用途 |
|---|------|
| `BuildStatusValue` | ビルドステータス（`pending` / `building` / `complete` / `error`） |
| `BuildResult` | ビルド結果（`status`, `stats`, `changedFiles`, `warnings`, `elapsed`） |
| `BuildStats` | ビルド統計情報（`total`, `built`, `skipped`, `deleted`, `errors`, `assets`） |
| `BuildManifest` | ビルドマニフェスト — 差分ビルドの判断材料（`changed`, `added`, `deleted`, `unchanged`） |
| `BuildState` | ビルド状態の永続化形式（`hashes`, `settings_hash`, `theme_hash`, `timestamp`） |
| `ThemeConfig` | テーマ設定（`name`, `directory`, `templates`, `assets`, `partials`） |
| `TemplateContext` | テンプレートコンテキスト（`site`, `page`, `pages`, `collections`, `navigation`） |
| `NavigationItem` | ナビゲーション項目（`label`, `url`, `active`, `children`） |
| `SitemapEntry` | サイトマップエントリ（`url`, `lastmod`, `changefreq`, `priority`） |

### 2.7 サイト設定・診断型

| 型 | 用途 |
|---|------|
| `SiteSettings` | サイト設定（`title`, `description`, `url`, `language`, `theme`） |
| `DiagnosticsReport` | 診断レポート（`events`, `summary`, `collectedAt`） |
| `DiagEvent` | 診断イベント（`channel`, `level`, `message`, `context`, `timestamp`） |
| `DiagLevel` | 診断レベル（`debug` / `info` / `warning` / `error` / `critical`） |
| `HealthCheckResult` | ヘルスチェック結果（`status`, `version`, `runtime`, `time`, `checks`） |

### 2.8 デプロイ・更新型

| 型 | 用途 |
|---|------|
| `GitResult` | Git 操作結果（`success`, `output`, `error`） |
| `GitStatus` | Git ステータス（`branch`, `clean`, `modified`, `untracked`, `ahead`, `behind`） |
| `GitLogEntry` | Git コミットログ（`hash`, `message`, `author`, `date`） |
| `UpdateInfo` | アップデート情報（`available`, `currentVersion`, `latestVersion`, `releaseNotes`） |
| `BackupEntry` | バックアップエントリ（`name`, `createdAt`, `size`, `version`） |

### 2.9 Webhook・リビジョン型

| 型 | 用途 |
|---|------|
| `WebhookConfig` | Webhook 設定（`url`, `label`, `events`, `secret`, `enabled`） |
| `WebhookEvent` | Webhook イベント種別（`content.created` / `content.updated` / `content.deleted` / `build.started` / `build.completed` / `deploy.completed`） |
| `RevisionEntry` | リビジョンエントリ（`file`, `timestamp`, `size`, `user`, `restored`, `pinned`） |

### 2.10 ACS インターフェース型

| 型 | 用途 |
|---|------|
| `AdlaireClient` | ACS の中核インターフェース（`auth`, `storage`, `files`, `http`） |
| `AuthModule` | 認証モジュール（`login`, `logout`, `session`, `verify`） |
| `StorageModule` | ストレージモジュール（`read`, `write`, `delete`, `exists`, `list`） |
| `FileModule` | ファイルモジュール（`upload`, `download`, `delete`, `exists`, `info`） |
| `HttpModule` | HTTP モジュール（`get`, `post`, `put`, `delete`） |
| `WriteOperation` | 書き込み操作結果（`success`, `path`, `size`, `error`） |

### 2.11 その他の型

| 型 | 用途 |
|---|------|
| `ValidationRules` | バリデーションルール定義 |
| `ValidationErrors` | バリデーションエラー |
| `LogLevelValue` | ログレベル |
| `SearchResult` | 検索結果（`collection`, `slug`, `title`, `preview`, `score`） |
| `LocaleId` | ロケール識別子 |
| `TranslationDict` | 翻訳辞書 |
| `RateLimitConfig` | レートリミット設定 |
| `CorsConfig` | CORS 設定 |
| `ImageInfo` | 画像情報（`width`, `height`, `mime`, `size`, `aspect`） |
| `FrontMatterResult` | Front matter パース結果（`meta`, `body`） |
| `ActionDefinition` | アクション定義（`name`, `handler`, `requiresAuth`, `requiresCsrf`） |

---

## 3. APF — Adlaire Platform Foundation

DI コンテナ、EventBus、Router 等のプラットフォーム基盤を提供する。

### 3.1 APF.Interface.ts

| インターフェース | 主要メソッド |
|----------------|-------------|
| `ContainerInterface` | `bind`, `singleton`, `make`, `instance`, `lazy`, `has`, `alias`, `bindIf`, `flush`, `getBindings` |
| `RouterInterface` | `get`, `post`, `put`, `patch`, `delete`, `any`, `group`, `middleware`, `dispatch`, `name`, `route` |
| `RequestInterface` | `method`, `httpMethod`, `uri`, `query`, `post`, `json`, `header`, `cookie`, `ip`, `isJson`, `isAjax`, `isPost`, `param`, `requestId` |
| `ResponseInterface` | `getContent`, `getStatusCode`, `getHeaders`, `withHeader` |
| `MiddlewareInterface` | `handle(request, next)` |
| `ValidatorInterface` | `validate`, `fails`, `errors` |
| `CacheInterface` | `get`, `set`, `delete`, `has`, `flush`, `stats` |
| `LoggerInterface` | `debug`, `info`, `warning`, `error`, `critical` |
| `ConfigInterface` | `get`, `setDefaults`, `clearCache` |

### 3.2 APF.Class.ts

| クラス / 型 | 役割 |
|------------|------|
| `HttpMethod` | HTTP メソッド列挙（`static readonly` パターン）。`isSafe()`, `isIdempotent()`, `from()` を提供する |
| `LogLevel` | ログレベル列挙。`fromName()`, `label` を提供する |
| `FrameworkError` | 基底エラークラス。`context` プロパティを持つ |
| `ContainerError` | DI 解決エラー |
| `NotFoundError` | 404 エラー |
| `RoutingError` | ルーティングエラー |
| `ValidationError` | バリデーションエラー。`errors` プロパティを持つ |
| `MiddlewareError` | ミドルウェアエラー |
| `RequestInit` | Request コンストラクタ引数の型定義 |

### 3.3 APF.Core.ts

| クラス | 役割 |
|--------|------|
| `Container` | DI コンテナ。`ContainerInterface` を実装する。クロージャベースの依存解決を行い、循環依存を検出する |
| `Router` | ルーター。`RouterInterface` を実装する。`reduceRight` によるミドルウェアパイプラインを構築する |
| `Request` | リクエスト。`RequestInterface` を実装する。コンストラクタ注入によりスーパーグローバルを使用しない |
| `Response` | レスポンス。`ResponseInterface` を実装する。`json`, `html`, `redirect`, `jsonError` の静的ファクトリメソッドを持つ |
| `HookManager` | フック管理。プライオリティソートおよびキャッシュを行う。`register`, `run`, `filter`, `has`, `remove`, `clear` を提供する |
| `PluginManager` | プラグイン管理。トポロジカルソートにより依存順序を解決し、循環依存を検出する |
| `DebugCollector` | デバッグ情報収集。`performance.now()` ベースで計測を行う。`enterScope` / `exitScope` を提供する |
| `ErrorBoundary` | エラー境界。`wrap()`, `wrapResponse()`, `wrapResult()` を提供する |

### 3.4 APF.Api.ts

| 内容 | 役割 |
|------|------|
| システムルート登録 | ヘルスチェック（`/api/health`）等のシステムルートを登録する。`Deno.version.deno` によるランタイム情報を返す |

### 3.5 APF.Utilities.ts

| クラス | 役割 |
|--------|------|
| `Config` | 設定管理。環境変数 → 設定ファイル → デフォルト値の優先順で解決する |
| `Validator` | バリデーション。`Result<ValidatedData>` を返す |
| `Cache` | `Map` ベースのインメモリキャッシュ。`CacheInterface` を実装する |
| `Logger` | `console.*` およびファイル出力。`LoggerInterface` を実装する |
| `Security` | HTML エスケープ等のセキュリティユーティリティを提供する |
| `Str` | 文字列ユーティリティ。`safePath`, `slug`, `truncate`, `random` を提供する |
| `Arr` | 配列ユーティリティ。`get<T>`, `set`, `has`, `flatten`, `only`, `except` を提供する |
| `FileSystem` | ファイルシステム操作。`Deno.*` API を使用する |
| `JsonStorage` | JSON ストレージ。`Map` ベースのキャッシュを備える |

---

## 4. ACS — Adlaire Client Services

サーバ（ASS）との通信を一元的に担うクライアントエンジンである。詳細仕様は FRAMEWORK_RULEBOOK §3 を参照のこと。

### 4.1 ACS.Interface.ts

| インターフェース | 用途 |
|----------------|------|
| `ClientFactoryInterface` | `AdlaireClient` インスタンスの生成 |
| `ClientConfig` | クライアント設定（`baseUrl`, `apiPrefix`, `timeout`, `credentials`） |
| `AuthModuleInterface` | 認証操作のインターフェース |
| `StorageModuleInterface` | ストレージ操作のインターフェース |
| `FileModuleInterface` | ファイル操作のインターフェース |
| `HttpModuleInterface` | HTTP 通信のインターフェース |
| `RequestInterceptor` | リクエストインターセプター |
| `ResponseInterceptor` | レスポンスインターセプター |
| `RetryConfig` | リトライ設定（指数バックオフ） |
| `RequestConfig` | リクエスト設定 |
| `EventSourceInterface` | Server-Sent Events のインターフェース |

### 4.2 ACS.Class.ts

| クラス / 型 | 役割 |
|------------|------|
| `API_ENDPOINTS` | API エンドポイント定数を定義する |
| `ConnectionState` | 接続状態の列挙 |
| `NetworkError` | ネットワークエラー |
| `ServerError` | サーバエラー |
| `AuthError` | 認証エラー |
| `TimeoutError` | タイムアウトエラー |

### 4.3 ACS.Core.ts

| クラス | 役割 |
|--------|------|
| `ClientFactory` | `ClientFactoryInterface` を実装する。`AdlaireClient` のインスタンスを生成し、`globalThis.__acs` として公開する |
| `HttpTransport` | 低レベル HTTP 通信層。`HttpModuleInterface` を実装する。インターセプター、AbortController によるキャンセル、指数バックオフ付きリトライを提供する |
| `AuthService` | 認証操作の実装。ログイン、ログアウト、セッション管理、および CSRF トークン取得を行う |
| `StorageService` | JSON ストレージ操作の実装。ファイルの読み書き、削除、存在確認、および一覧取得を行う |
| `FileService` | ファイル操作の実装。アップロード、ダウンロード、削除、および画像情報取得を行う |

### 4.4 ACS.Api.ts

| 内容 | 役割 |
|------|------|
| API ルート登録 | ACS 関連の API ルートを登録する |

### 4.5 ACS.Utilities.ts

| 内容 | 役割 |
|------|------|
| ユーティリティ関数 | Cookie パース、リトライハンドラ等のユーティリティを提供する |

---

## 5. ACE — Adlaire Content Engine

コレクション、コンテンツ、およびメタデータの管理を行う。

### 5.1 ACE.Interface.ts

| インターフェース | 主要メソッド |
|----------------|-------------|
| `CollectionManagerInterface` | `create`, `delete`, `listCollections`, `getSchema`, `isEnabled` |
| `ContentManagerInterface` | `getItem`, `saveItem`, `deleteItem`, `listItems`, `search` |
| `MetaManagerInterface` | `extractMeta`, `buildMeta`, `mergeMeta` |
| `ContentValidatorInterface` | `validate` |
| `AuthManagerInterface` | `isLoggedIn`, `currentUser`, `hasRole`, `csrfToken`, `login`, `logout` |
| `RevisionManagerInterface` | `saveRevision`, `getRevisions`, `restoreRevision` |
| `WebhookManagerInterface` | `register`, `delete`, `list`, `dispatch` |
| `ApiRouterInterface` | `registerEndpoint`, `dispatch` |

### 5.2 ACE.Class.ts

| クラス | 役割 |
|--------|------|
| `CollectionField` | フィールド定義モデル |
| `ContentItem` | コンテンツアイテムモデル |
| `Revision` | リビジョンモデル |
| `Webhook` | Webhook 設定モデル |
| `ApiKey` | API キーモデル |
| `User` | ユーザーモデル |

### 5.3 ACE.Core.ts

| クラス | 役割 |
|--------|------|
| `CollectionManager` | コレクションの CRUD およびスキーマバリデーションを行う。`Result<CollectionSchema>` を返す |
| `ContentManager` | コンテンツの CRUD を行う。ソート、フィルタ、およびページネーションを提供する |
| `MetaManager` | YAML front matter のパースおよびメタデータ管理を行う |
| `ContentValidator` | コレクションスキーマに基づくバリデーションを行う。`Result<Record<string, unknown>>` を返す |
| `AuthManager` | `globalThis.__acs.auth` に全面委譲する |
| `UserManager` | `globalThis.__acs.auth` および `globalThis.__acs.storage` を使用する |
| `RevisionManager` | リビジョンの差分比較および管理を行う |
| `ActivityLogger` | `globalThis.__acs.storage` によりアクティビティログを記録する |
| `ApiRouter` | エンドポイントの登録およびディスパッチを行う |
| `WebhookManager` | Webhook の CRUD を行う。送信は `globalThis.__acs.http` を経由する |
| `RateLimiter` | `Map` ベースのレートリミッター。`globalThis.__acs.storage` により永続化する |
| `ApiService` | API キーの管理を行う |

### 5.4 ACE.Api.ts

| 内容 | 役割 |
|------|------|
| コレクションルート登録 | コレクション、コンテンツ、認証、および Webhook の API ルートを登録する |

### 5.5 ACE.Utilities.ts

| クラス | 役割 |
|--------|------|
| `SlugGenerator` | スラッグの生成およびバリデーションを行う |
| `ContentFormatter` | コンテンツの整形およびサニタイズを行う |
| `SchemaValidator` | コレクションスキーマのバリデーションを行う |

### 5.6 AdminEngine/（サブディレクトリ）

| ファイル | 用途 |
|---------|------|
| `dashboard.html` | 管理画面ダッシュボード |
| `login.html` | ログイン画面 |
| `dashboard.css` | 管理画面用スタイル |

---

## 6. AIS — Adlaire Infrastructure Services

アプリケーションコンテキスト、i18n、API キャッシュ、および診断を提供する。

### 6.1 AIS.Interface.ts

| インターフェース | 主要メソッド |
|----------------|-------------|
| `AppContextInterface` | `resolveHost`, `dataDir`, `settingsDir`, `contentDir` |
| `I18nInterface` | `init`, `t`, `locale`, `setLocale` |
| `HealthMonitorInterface` | `healthCheck`, `checkDisk`, `checkMemory` |
| `DiagnosticsInterface` | `log`, `startTimer`, `stopTimer`, `collect` |
| `UpdaterInterface` | `checkForUpdate`, `applyUpdate`, `rollback` |
| `GitSyncInterface` | `clone`, `pull`, `push`, `status`, `diff`, `log` |
| `BackupManagerInterface` | `create`, `restore`, `list`, `purge` |

### 6.2 AIS.Class.ts

| クラス | 役割 |
|--------|------|
| `AppConfig` | アプリケーション設定モデル |
| `DiagnosticEntry` | 診断エントリモデル |
| `BackupInfo` | バックアップ情報モデル |
| `UpdateInfo` | アップデート情報モデル |
| `GitStatus` | Git ステータスモデル |

### 6.3 AIS.Core.ts

| クラス | 役割 |
|--------|------|
| `AppContext` | アプリケーション状態管理シングルトン。ホスト解決およびディレクトリパスの管理を行う |
| `I18n` | 翻訳機能。ロケールファイルの読み込みおよび翻訳キーの解決を行う |

### 6.4 AIS.Api.ts

| 内容 | 役割 |
|------|------|
| インフラルート登録 | 診断、更新、Git、およびバックアップの API ルートを登録する |

### 6.5 AIS.Utilities.ts

| クラス | 役割 |
|--------|------|
| `ApiCache` | `Map` ベースの API レスポンスキャッシュ。`CacheInterface` を実装する |
| `DiagnosticsManager` | 診断情報の収集および管理を行う静的ファサードを提供する |

---

## 7. ASG — Adlaire Static Generator

Markdown パース、テンプレートレンダリング、および静的サイトビルドを行う。

### 7.1 ASG.Interface.ts

| インターフェース | 主要メソッド |
|----------------|-------------|
| `GeneratorInterface` | `buildAll`, `buildDiff`, `clean`, `getStatus` |
| `BuilderInterface` | `buildPage`, `buildIndex` |
| `TemplateRendererInterface` | `render`, `registerHelper`, `registerPartial` |
| `MarkdownParserInterface` | `parse`, `addRule` |
| `ThemeManagerInterface` | `load`, `getTemplate`, `listThemes`, `buildContext` |
| `SiteRouterInterface` | `resolveSlug`, `buildUrl`, `generateSitemap` |

### 7.2 ASG.Class.ts

| クラス / 型 | 役割 |
|------------|------|
| `BuildStatus` | ビルド状態列挙（`Pending` / `Building` / `Complete` / `Error`） |
| `BuildConfig` | ビルド設定モデル |
| `PageOutput` | 生成済みページモデル |
| `SitemapEntry` | サイトマップエントリモデル |

### 7.3 ASG.Core.ts

| クラス | 役割 |
|--------|------|
| `Builder` | ビルドオーケストレーター。全体ビルドおよび差分ビルドを制御し、`Result<BuildResult>` を返す |
| `MarkdownService` | Markdown から HTML への変換を行う。`addRule` によりカスタムルールをプラガブルに追加できる |
| `TemplateRenderer` | Handlebars ライク構文のテンプレートエンジン。コンパイルキャッシュおよびフィルターチェーンを備える |

### 7.4 ASG.Api.ts

| 内容 | 役割 |
|------|------|
| ジェネレータルート登録 | ビルドおよびテーマ操作の API ルートを登録する |

### 7.5 ASG.Utilities.ts

| クラス | 役割 |
|--------|------|
| `BuildCache` | コンテンツハッシュによる差分検出を行う |
| `DiffBuilder` | 差分の検出および比較を行う |

---

## 8. AP — Adlaire Platform

認証、ロギング、セキュリティヘッダー等のミドルウェアとクライアントモジュールを提供する。

### 8.1 AP.Interface.ts

| インターフェース | 主要メソッド |
|----------------|-------------|
| `ControllerInterface` | `ok`, `error`, `requireRole`, `validate` |
| `ActionDispatcherInterface` | `handle` |

### 8.2 AP.Class.ts

| クラス / 型 | 役割 |
|------------|------|
| `ActionType` | アクション種別の列挙 |
| `ControllerContext` | コントローラー実行コンテキストモデル |

### 8.3 AP.Core.ts

| クラス | 役割 |
|--------|------|
| `AuthMiddleware` | 認証ミドルウェア。未認証リクエストを拒否する |
| `RequestLoggingMiddleware` | リクエストロギングミドルウェア。リクエスト情報をログに記録する |
| `SecurityHeadersMiddleware` | セキュリティヘッダーミドルウェア。CSP、X-Frame-Options 等のヘッダーを付与する |

### 8.4 AP.Api.ts

| 内容 | 役割 |
|------|------|
| プラットフォームルート登録 | 認証、ダッシュボード、管理、および API のルートを登録する |

### 8.5 AP.Utilities.ts

| 内容 | 役割 |
|------|------|
| ユーティリティ関数 | `json_read`, `json_write`, `h`（HTML エスケープ）, `getSlug`, `host` 等のヘルパー関数を提供する |

### 8.6 JsEngine/（サブディレクトリ）

ブラウザ向けクライアントモジュール群を格納する。

| ファイル | 用途 |
|---------|------|
| `ap-api-client.ts` | API クライアント |
| `dashboard.ts` | ダッシュボードモジュール |
| `collection_manager.ts` | コレクション管理モジュール |
| `wysiwyg.ts` | WYSIWYG エディタ連携モジュール |
| `git_manager.ts` | Git 管理モジュール |
| `static_builder.ts` | 静的ビルドモジュール |
| `webhook_manager.ts` | Webhook 管理モジュール |
| `api_keys.ts` | API キー管理モジュール |
| `diagnostics.ts` | 診断モジュール |
| `updater.ts` | 更新モジュール |
| `ap-events.ts` | イベント管理モジュール |
| `ap-i18n.ts` | 多言語対応モジュール |
| `ap-search.ts` | 検索モジュール |
| `ap-utils.ts` | ユーティリティモジュール |
| `aeb-adapter.ts` | AEB アダプターモジュール |
| `editInplace.ts` | インプレース編集モジュール |
| `autosize.ts` | テキストエリア自動リサイズモジュール |
| `browser-types.d.ts` | ブラウザ向け型定義 |

---

## 9. AEB — Adlaire Editor & Blocks

エディタのブロックシステムを提供する。

### 9.1 AEB.Core.ts

| 内容 | 役割 |
|------|------|
| エディタエンジン | ブロックベースの WYSIWYG エディタの中核実装。ブロックの登録、状態管理、Undo / Redo、およびイベント管理を行う |

### 9.2 AEB.Blocks.ts

| 内容 | 役割 |
|------|------|
| ブロック定義 | 各ブロックタイプ（テキスト、見出し、画像、コード等）の型定義および実装を提供する |

### 9.3 AEB.Utils.ts

| 内容 | 役割 |
|------|------|
| ユーティリティ | HTML サニタイズ、DOM 操作、テキスト選択、およびキーボードショートカットのユーティリティを提供する |

---

## 10. ADS — Adlaire Design System

ベーススタイル、コンポーネントスタイル、およびエディタスタイルを定義する。

### 10.1 ADS.Base.css

ベーススタイルおよびリセットを定義する。CSS カスタムプロパティ（設計トークン）、タイポグラフィ、および基本要素のスタイルを含む。

### 10.2 ADS.Components.css

コンポーネント固有のスタイルを定義する。ボタン、フォーム、カード、モーダル、アラート、バッジ等のコンポーネントスタイルを含む。

### 10.3 ADS.Editor.css

エディタおよび管理画面向けのスタイルを定義する。

---

## 11. ASS — Adlaire Server System

認証、ストレージ、ファイル操作、および Git 等のサーバサイドサービスを PHP で実装する。

### 11.1 ASS.Interface.php

| インターフェース | 主要メソッド |
|----------------|-------------|
| `AuthServiceInterface` | `login`, `logout`, `session`, `csrfToken`, `hashPassword`, `verifyPassword` |
| `StorageServiceInterface` | `read`, `write`, `delete`, `exists`, `list` |
| `FileServiceInterface` | `upload`, `delete`, `list`, `info` |
| `GitServiceInterface` | `status`, `log`, `pull`, `diff` |

### 11.2 ASS.Class.php

| クラス | 役割 |
|--------|------|
| `ApiRequest` | リクエストモデル |
| `ApiResponse` | レスポンスモデル |
| `SessionData` | セッションデータモデル |

### 11.3 ASS.Core.php

| クラス | 役割 |
|--------|------|
| `AuthService` | 認証およびセッション管理を行う。bcrypt によるパスワードハッシュ、CSRF トークン管理、およびログイン試行制限を実装する |
| `StorageService` | JSON ファイルの読み書きを行う。`data/` 配下の CRUD 操作を提供する |
| `FileService` | ファイルのアップロードおよび画像最適化を行う。`uploads/` 配下のファイル操作を提供する |
| `GitService` | Git 操作を行う。ステータス取得、コミットログ取得、および差分取得を提供する |

### 11.4 ASS.Api.php

| クラス | 役割 |
|--------|------|
| ルーター / ディスパッチャ | HTTP リクエストを受信し、ルーティングおよびハンドラへのディスパッチを行う。ヘルスチェックエンドポイント、セキュリティヘッダー付与、およびレート制限を含む |

### 11.5 ASS.Utilities.php

| クラス | 役割 |
|--------|------|
| `SessionManager` | セッション管理ユーティリティ |
| `CsrfManager` | CSRF トークン管理ユーティリティ |
| `Token` | トークン生成ユーティリティ |
| `PathSecurity` | パス解決およびパストラバーサル防止 |
| `MimeType` | MIME タイプ判定 |
| `GitCommand` | Git コマンド実行ラッパー |
| `AdminTemplate` | 管理画面テンプレート |

---

## 12. エントリポイントおよび初期化

### 12.1 main.ts

HTTP サーバのエントリポイントである。`Deno.serve` により HTTP リクエストを受け付け、`bootstrap.ts` で初期化した DI コンテナおよびルーターを通じてリクエストを処理する。

### 12.2 bootstrap.ts

DI コンテナの初期化を行う。各フレームワークのサービスを Container に登録し、イベントリスナーを設定する。ACS の初期化（`globalThis.__acs` の公開）を bootstrap 処理の最初に実行する。

### 12.3 routes.ts

ルートおよびミドルウェアの登録を行う。認証、CSRF、レート制限、セキュリティヘッダー、およびリクエストロギングのミドルウェアパイプラインを構築する。

### 12.4 Framework/mod.ts

全フレームワークモジュールのバレルエクスポート（エントリポイント）を提供する。

---

## 13. 非同期設計

### 13.1 基本原則

- `globalThis.__acs` 経由の I/O はすべて `async` / `Promise` で処理する
- 純粋ロジック（バリデーション、テンプレート処理、文字列操作）は同期処理とする
- 独立した I/O は `Promise.all()` で並列化する

### 13.2 並列化の例

```typescript
const [settings, pages] = await Promise.all([
  globalThis.__acs.storage.read("settings.json", "settings"),
  globalThis.__acs.storage.read("pages.json", "content"),
]);
```

---

## 14. テンプレートエンジン

`ASG.Core.ts` の `TemplateRenderer` は Handlebars ライクな構文をサポートする。

### 14.1 サポート構文

| 構文 | 用途 |
|------|------|
| `{{variable}}` | 変数展開（HTML エスケープ付き） |
| `{{{variable}}}` | 変数展開（エスケープなし） |
| `{{#if condition}}...{{/if}}` | 条件分岐 |
| `{{#each items}}...{{/each}}` | ループ |
| `{{> partial}}` | パーシャル挿入 |
| `{{variable \| filter}}` | フィルターチェーン |

### 14.2 コンパイルキャッシュ

同一テンプレートの 2 回目以降のレンダリングを高速化するため、コンパイル済み関数を `Map` にキャッシュする。

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
}
```

---

## 15. 準拠文書

本書は以下の文書に準拠する。矛盾がある場合は上位の文書が優先される。

1. `docs/FRAMEWORK_RULEBOOK_v3.0.md` — フレームワーク規約
2. `docs/VERSIONING.md` — バージョニング規則
3. `docs/DOC_RULEBOOK.md` — ドキュメント管理ルール
