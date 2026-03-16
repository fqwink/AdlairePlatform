# AdlairePlatform — 詳細設計書

<!-- ⚠️ 削除禁止: 本ドキュメントは実装詳細に関する最上位ドキュメントです -->

> **ドキュメントバージョン**: Ver.2.0-1
> **ステータス**: 🔧 開発中（Ver.2.0）
> **作成日**: 2026-03-10
> **最終更新**: 2026-03-15
> **所有者**: Adlaire Group

> **関連ドキュメント**:
> - 基本設計書・基本方針 → [AdlairePlatform_Design.md](AdlairePlatform_Design.md)
> - エンジン駆動モデルアーキテクチャ基本設計書 → [ARCHITECTURE.md](ARCHITECTURE.md)
> - 機能一覧・関数リファレンス → [features.md](features.md)
> - フレームワークルールブック → [FRAMEWORK_RULEBOOK.md](FRAMEWORK_RULEBOOK.md)
> - Ver.2.0 補遺 → [FRAMEWORK_RULEBOOK_v2.0.md](FRAMEWORK_RULEBOOK_v2.0.md)
> - 本ドキュメントは **実装レベルの技術仕様（詳細設計）** を定めています。

---

## 目次

1. [概要](#1-概要)
2. [アプリケーション起動フロー](#2-アプリケーション起動フロー)
   - 2.1 [index.php（エントリーポイント）](#21-indexphpエントリーポイント)
   - 2.2 [autoload.php（名前空間解決）](#22-autoloadphp名前空間解決)
   - 2.3 [bootstrap.php（アプリケーション初期化）](#23-bootstrapphpアプリケーション初期化)
   - 2.4 [routes.php（ルート定義）](#24-routesphpルート定義)
3. [Framework モジュール実装仕様](#3-framework-モジュール実装仕様)
   - 3.1 [APF — Adlaire Platform Foundation](#31-apf--adlaire-platform-foundation)
   - 3.2 [ACE — Adlaire Content Engine](#32-ace--adlaire-content-engine)
   - 3.3 [AIS — Adlaire Infrastructure Services](#33-ais--adlaire-infrastructure-services)
   - 3.4 [ASG — Adlaire Static Generator](#34-asg--adlaire-static-generator)
   - 3.5 [AP — Adlaire Platform Controllers](#35-ap--adlaire-platform-controllers)
   - 3.6 [AEB — Adlaire Editor & Blocks](#36-aeb--adlaire-editor--blocks)
   - 3.7 [ADS — Adlaire Design System](#37-ads--adlaire-design-system)
4. [データ層実装仕様](#4-データ層実装仕様)
5. [セキュリティ実装仕様](#5-セキュリティ実装仕様)
6. [イベント・フック機構](#6-イベントフック機構)
7. [定数定義](#7-定数定義)
8. [Ver.2.0 破壊的変更](#8-ver20-破壊的変更)
9. [変更履歴](#9-変更履歴)

---

## 1. 概要

本ドキュメントは AdlairePlatform の **詳細設計書** です。
基本設計（[AdlairePlatform_Design.md](AdlairePlatform_Design.md)）およびアーキテクチャ基本設計（[ARCHITECTURE.md](ARCHITECTURE.md)）で定められた方針に基づく、実装レベルの技術仕様を記録します。

### 1.1 対象バージョン

| 項目 | 値 |
|------|-----|
| プラットフォームバージョン | Ver.2.0-40 |
| AP_VERSION 定数 | `'2.0.40'` |
| PHP 動作要件 | 8.3+ |
| アーキテクチャ | Router + Controller + Framework モジュール |
| フレームワーク構成 | 7 フレームワーク・18 エンジン |

### 1.2 ドキュメント範囲

- **アプリケーション起動フロー**: エントリーポイント → オートロード → DI → ルーティング
- **Framework モジュール実装仕様**: 全 7 フレームワーク（APF/ACE/AIS/ASG/AP/AEB/ADS）のクラス・メソッドシグネチャ
- **データ層実装仕様**: ファイルパスマッピング
- **セキュリティ実装仕様**: 脅威対策マトリクス
- **イベント・フック機構**: イベント駆動パターン
- **定数定義**: アプリケーション定数

---

## 2. アプリケーション起動フロー

### 2.1 index.php（エントリーポイント）

```
index.php 起動フロー:

1. PHP バージョンチェック（8.3+ 必須）
2. 定数定義（AP_VERSION, AP_UPDATE_URL, AP_BACKUP_GENERATIONS, AP_REVISION_LIMIT）
3. autoload.php 読み込み
4. AP.Bridge.php 読み込み
5. bootstrap.php 読み込み（Application::boot()）
6. routes.php 読み込み（ルート登録）
7. セッション初期化（Config 外部化済み）
8. セキュリティヘッダー設定
9. Router ディスパッチ（ErrorBoundary ラップ）
   ├─ ルート一致 → Response::send() → exit
   └─ 404 → ページレンダリングへフォールスルー
10. データ読み込み（settings.json, auth.json, pages.json）
11. 認証チェック（ACE\Admin\AdminManager::isLoggedIn()）
12. スラッグ解決 → コンテンツ取得
13. AppContext グローバルコンテキスト同期
14. テーマレンダリング（ASG\Template\ThemeService::load()）
```

| 機能グループ | 実装 | 委譲先 |
|------------|------|--------|
| URL 解析 | `host()` | `AIS\Core\AppContext::resolveHost()` |
| スラッグ生成 | `getSlug()` | `APF\Utilities\Str::safePath()` |
| JSON 読み書き | `json_read()`, `json_write()` | `APF\Utilities\JsonStorage` |
| HTML エスケープ | `h()` | `APF\Utilities\Security::escape()` |
| ディレクトリパス | `data_dir()`, `settings_dir()`, `content_dir()` | `AIS\Core\AppContext` |
| 認証チェック | — | `ACE\Admin\AdminManager::isLoggedIn()` |
| テーマ読み込み | — | `ASG\Template\ThemeService::load()` |

### 2.2 autoload.php（名前空間解決）

カスタム名前空間 → ファイルマッピング方式（PSR-4 非準拠、1 ファイル = 複数クラスに対応）。

```php
static $map = [
    /* APF - Adlaire Platform Foundation */
    'APF\\Core\\'       => 'Framework/APF/APF.Core.php',
    'APF\\Middleware\\'  => 'Framework/APF/APF.Middleware.php',
    'APF\\Database\\'   => 'Framework/APF/APF.Database.php',
    'APF\\Utilities\\'  => 'Framework/APF/APF.Utilities.php',

    /* ACE - Adlaire Content Engine */
    'ACE\\Core\\'       => 'Framework/ACE/ACE.Core.php',
    'ACE\\Admin\\'      => 'Framework/ACE/ACE.Admin.php',
    'ACE\\Api\\'        => 'Framework/ACE/ACE.Api.php',

    /* AIS - Adlaire Infrastructure Services */
    'AIS\\Core\\'       => 'Framework/AIS/AIS.Core.php',
    'AIS\\System\\'     => 'Framework/AIS/AIS.System.php',
    'AIS\\Deployment\\' => 'Framework/AIS/AIS.Deployment.php',

    /* ASG - Adlaire Static Generator */
    'ASG\\Core\\'       => 'Framework/ASG/ASG.Core.php',
    'ASG\\Template\\'   => 'Framework/ASG/ASG.Template.php',
    'ASG\\Utilities\\'  => 'Framework/ASG/ASG.Utilities.php',

    /* AP - Adlaire Platform Controllers */
    'AP\\Controllers\\' => 'Framework/AP/AP.Controllers.php',

    /* AEB/ADS - Asset Bindings */
    'AEB\\Assets\\'     => 'Framework/AEB/AEB.Assets.php',
    'ADS\\Assets\\'     => 'Framework/ADS/ADS.Assets.php',
];
```

二重読み込み防止: `$loaded` 配列で require 済みファイルを追跡。

### 2.3 bootstrap.php（アプリケーション初期化）

```php
class Application {
    private static ?Container $container = null;
    private static ?HookManager $hooks = null;
    private static ?EventDispatcher $events = null;
    private static bool $booted = false;
    private static array $providers = [];

    public static function boot(): void
    public static function container(): Container
    public static function hooks(): HookManager
    public static function events(): EventDispatcher
    public static function make(string $abstract, array $parameters = []): mixed
    public static function isBooted(): bool
    public static function registerProvider(ServiceProvider $provider): void
    public static function bootProviders(): void
    public static function reset(): void  // テスト用
}
```

**DI コンテナ登録:**

```php
singleton(Router::class)          // ルーター
singleton(Request::class)         // リクエスト
lazy(Session::class)              // セッション管理
lazy(HealthMonitor::class)        // 診断ウィジェット
lazy(RateLimiter::class)          // API レート制限
```

**イベントリスナー登録:**

| イベント | リスナー |
|---------|---------|
| `content.changed` | WebhookService ディスパッチ |
| `auth.login` | Diagnostics ログ記録 |
| `cache.invalidate` | ApiCache 無効化 |

### 2.4 routes.php（ルート定義）

**クエリ/POST パラメータマッピング:**

```php
$router->mapQuery('login', '/login')
$router->mapQuery('admin', '/admin')
$router->mapQuery('ap_api', '/api/{endpoint}', 'endpoint')
$router->mapPost('ap_action', '/dispatch')
```

**ルート定義:**

| メソッド | パス | ハンドラ | ミドルウェア |
|---------|------|---------|------------|
| GET | `/health` | インライン（バージョン + ステータス） | SecurityHeaders |
| GET | `/login` | `AuthController::showLogin` | SecurityHeaders |
| POST | `/login` | `AuthController::authenticate` | SecurityHeaders |
| ANY | `/api/{endpoint}` | `ApiController::dispatch` | SecurityHeaders |
| GET | `/admin` | `DashboardController::index` | SecurityHeaders, Auth |
| POST | `/logout` | `AuthController::logout` | SecurityHeaders, Auth |
| POST | `/dispatch` | `ActionDispatcher::handle` | SecurityHeaders, Auth, CSRF |

---

## 3. Framework モジュール実装仕様

### 3.1 APF — Adlaire Platform Foundation

#### APF.Core.php

**Enum・インターフェース:**

```php
enum HttpMethod: string           // GET, POST, PUT, PATCH, DELETE, HEAD, OPTIONS
  ├─ isSafe(): bool
  └─ isIdempotent(): bool

enum LogLevel: string             // DEBUG, INFO, WARNING, ERROR, CRITICAL
  └─ fromName(string $name): self

interface EventBusInterface       // listen(), dispatch(), hasListeners()

abstract class Event              // stopPropagation(), isPropagationStopped()
  ├─ PluginLoadedEvent
  ├─ ContentSavedEvent
  ├─ AuthEvent
  └─ SettingsChangedEvent
```

**DI コンテナ:**

```php
class Container
  ├─ bind(string $abstract, Closure $concrete): void
  ├─ singleton(string $abstract, Closure $concrete): void
  ├─ instance(string $abstract, mixed $instance): void
  ├─ alias(string $alias, string $abstract): void
  ├─ lazy(string $abstract, Closure $factory): void
  ├─ bindIf(string $abstract, Closure $concrete): void
  ├─ make(string $abstract, array $parameters = []): mixed  // 循環依存検出付き
  ├─ has(string $abstract): bool
  ├─ getBindings(): array          // デバッグ用
  └─ flush(): void                 // テスト用
```

**ルーター:**

```php
class Router
  ├─ get/post/put/patch/delete/any(string $uri, callable $action): self
  ├─ group(array $attributes, Closure $callback): void
  ├─ middleware(string ...$middleware): self
  ├─ name(string $name): self
  ├─ route(string $name): ?string
  ├─ mapQuery(string $param, string $path, ?string $targetParam): void
  ├─ mapPost(string $param, string $path): void
  ├─ dispatch(Request $request): Response
  └─ count(): int
```

**HTTP オブジェクト:**

```php
class Request
  ├─ method(): string
  ├─ httpMethod(): HttpMethod
  ├─ uri(): string
  ├─ query/post/input/json/cookie/header/param/server(string $key, mixed $default): mixed
  ├─ file(string $key): ?array
  ├─ ip(): string
  ├─ userAgent(): string
  ├─ requestId(): string           // X-Request-Id 連携
  └─ isAjax(): bool

class Response
  ├─ json(mixed $data, int $status): static
  ├─ html(string $content, int $status): static
  ├─ redirect(string $url, int $status): static
  ├─ file(string $path, string $filename, string $contentType): static
  ├─ withHeader(string $name, string $value): self
  ├─ withCookie(string $name, string $value, array $options): self  // SameSite/Secure/HttpOnly デフォルト有効
  ├─ getContent(): mixed
  └─ send(): void
```

**イベント・フック:**

```php
class HookManager implements EventBusInterface
  ├─ register(string $name, callable $callback, int $priority): void
  ├─ run(string $name, ...$args): void
  ├─ filter(string $name, mixed $value, ...$args): mixed
  ├─ has(string $name): bool
  ├─ remove(string $name): void
  ├─ clear(): void
  └─ dispatchEvent(Event $event): Event

class EventDispatcher implements EventBusInterface
class PluginManager     // 循環依存検出（DFS トポロジカルソート）
class DebugCollector    // スコーププロファイリング、メモリデルタ計測
class ErrorBoundary     // グローバル例外ハンドラ、registerGlobal()
```

**例外クラス:**

```
FrameworkException
├─ ContainerException
├─ NotFoundException
├─ RoutingException
├─ ValidationException  // errors(): array, first(): ?string
└─ MiddlewareException
```

#### APF.Middleware.php

```php
class AuthMiddleware              // セッション認証チェック
class CsrfMiddleware              // CSRF トークン検証（POST/PUT/PATCH/DELETE）
class RateLimitMiddleware         // リクエストレート制限
class SecurityHeadersMiddleware   // X-Content-Type-Options, X-Frame-Options 等
class RequestLoggingMiddleware    // 構造化リクエストログ + X-Response-Time
class CorsMiddleware              // CORS ヘッダー制御 + プリフライト対応
```

#### APF.Database.php

```php
class Connection
  ├─ query(string $sql, array $bindings): PDOStatement
  ├─ insert/update/delete(string $table, array $data, ...): int|bool
  ├─ transaction(Closure $callback): mixed    // ネスト対応（SAVEPOINT）
  └─ getQueryLog(): array

class QueryBuilder
  ├─ select/where/whereBetween/whereNotBetween/whereLike/join/orderBy/limit/offset
  ├─ having(string $column, string $operator, mixed $value): self  // パラメータ化
  ├─ paginate(int $page, int $perPage): array   // {data, total, page, per_page, total_pages}
  ├─ insertBatch(array $rows): int              // 一括 INSERT
  ├─ get/first/count/exists
  └─ リクエストスコープクエリキャッシュ（書き込み操作で自動無効化）

class Model          // ORM 基底（all, find, create, save, delete, toArray, toJson）
class Schema         // スキーマビルダー（create, drop, hasTable）
class Blueprint      // テーブル定義（id, string, text, integer, timestamps）
```

#### APF.Utilities.php

```php
class Config
  └─ get(string $key, mixed $default): mixed    // 環境変数 → 設定ファイル → デフォルト

class Validator
  ├─ validate(array $data, array $rules): bool
  ├─ request(Request $request, array $rules): array  // 失敗時 ValidationException
  ├─ errors/first/hasError
  └─ ルール: required, email, min, max, numeric, url, date, confirmed, regex, between, size, array, json, ip, uuid, slug, in

class Cache          // ファイルキャッシュ（get, set, delete, remember, forever, gc）、JSON シリアライズ
class Logger         // 構造化ログ（JSON対応）、チャネル分離、リクエストコンテキスト自動付与
class Session        // セッション管理（get, set, flash, regenerate）
class Security       // hash(Argon2id優先), verify, csrf, encrypt, decrypt, rateLimit, escape
class Str            // slug, camel, snake, kebab, random(CSPRNG), limit, safePath
class Arr            // get, set, only, except, flatten, pluck, where
class JsonStorage    // JSON ファイル I/O（リクエスト内キャッシュ付き）
```

### 3.2 ACE — Adlaire Content Engine

#### ACE.Core.php

```php
class CollectionManager
  ├─ create(string $name, array $schema): bool
  ├─ delete(string $name): bool
  ├─ list(): array
  ├─ getSchema(string $name): array
  └─ getItems(string $name, array $filters): array

class ContentManager
  ├─ getPage(string $slug): ?array
  ├─ savePage(string $slug, string $content): bool
  ├─ deletePage(string $slug): bool
  └─ listPages(): array

class CollectionService
  └─ mergeCollectionPages(): array    // Markdown コレクションをページに統合
```

#### ACE.Admin.php

```php
class AdminManager
  ├─ isLoggedIn(): bool
  ├─ login(string $password, string $storedHash): bool
  ├─ savePassword(string $password): string
  ├─ csrfToken(): string
  ├─ verifyCsrf(): void
  └─ renderDashboard(): string

class RevisionManager
  ├─ save(string $fieldname, string $content, bool $restored): void
  ├─ list(string $fieldname, int $page, int $limit): array
  ├─ get(string $fieldname, string $id): ?array
  ├─ restore(string $fieldname, string $id): bool
  ├─ pin(string $fieldname, string $id): bool
  └─ search(string $fieldname, string $query): array

class UserManager
  ├─ authenticate(string $username, string $password): ?array
  ├─ add(string $username, string $password, string $role): bool
  ├─ delete(string $username): bool
  └─ list(): array
```

#### ACE.Api.php

```php
class ApiRouter
  ├─ handle(): void
  │   ├─ 'pages'      → getPages()
  │   ├─ 'page'       → getPage(slug)
  │   ├─ 'settings'   → getSettings()
  │   ├─ 'search'     → search(query)
  │   ├─ 'contact'    → sendContact()
  │   └─ 'collection' → コレクション API
  ├─ CORS 対応（設定可能オリジン + Vary: Origin）
  ├─ API キー認証（Bearer + bcrypt、プレフィックス 12 文字照合）
  └─ レスポンスキャッシュ（ETag / Last-Modified）

class WebhookManager
  ├─ register(string $url, array $events): bool
  ├─ delete(string $id): bool
  ├─ sendAsync(string $event, array $payload): void  // 指数バックオフリトライ（最大3回）
  ├─ verifySignature(string $payload, string $signature, string $secret): bool  // HMAC-SHA256
  └─ isPrivateHost(string $host): bool               // SSRF 防止

class ApiCache
  ├─ get(string $endpoint, string $key): mixed
  ├─ set(string $endpoint, string $key, mixed $data, int $ttl): void
  └─ clear(string $endpoint): void

class RateLimiter
  └─ check(string $key, int $limit, int $window): bool
```

### 3.3 AIS — Adlaire Infrastructure Services

#### AIS.Core.php

```php
class AppContext
  ├─ syncFromGlobals(): void       // グローバル変数から状態取り込み
  ├─ syncToGlobals(): void         // 変更をグローバル変数に書き戻し
  ├─ config(string $key, mixed $default): mixed
  ├─ setConfig(string $key, mixed $value): void
  ├─ resolveHost(): array          // ホスト URL・リクエストページ解決
  ├─ dataDir/settingsDir/contentDir(): string
  ├─ validate(array $schema): array  // スキーマ検証（型・必須・範囲・許可値）
  └─ host/loginStatus/credit/addHook/getHooks

class ServiceProvider              // DI サービス登録基底クラス
class ServiceContainer             // サービスコンテナ
```

#### AIS.System.php

```php
class DiagnosticsCollector
  ├─ registerErrorHandler(): void
  ├─ log(string $category, string $message, array $context): void
  ├─ startTimer/stopTimer(string $label): void
  ├─ getHealthStatus(): array
  └─ sendDiagnostics(): void       // サーキットブレーカー付き

class HealthMonitor
  └─ check(bool $detailed): array  // ディスク・メモリ・PHP・権限

class AppLogger
  ├─ init(string $minLevel, string $logDir): void
  ├─ debug/info/warning/error/critical(string $message, array $context): void
  ├─ channel(string $name): self
  └─ rotate/cleanup(): void        // サイズ/日付ベースローテーション

class CrashReporter
class ActivityLogger
```

#### AIS.Deployment.php

```php
class Updater
  ├─ checkUpdate(): array          // GitHub Releases API（1 時間キャッシュ）
  ├─ checkEnvironment(): array     // ZipArchive / allow_url_fopen / ディスク容量
  ├─ apply(string $url): bool      // ZIP ダウンロード・展開・適用
  ├─ backup(): string              // 自動バックアップ（data/, backup/ 除外）
  ├─ rollback(string $name): bool
  ├─ deleteBackup(string $name): bool
  └─ pruneOldBackups(): void       // 世代管理（デフォルト 5 世代）

class GitSync
  ├─ configure(array $config): bool
  ├─ test(): array
  ├─ pull(): array                 // パストラバーサル防止付き
  ├─ push(): array
  ├─ log(int $limit): array
  └─ status(): array

class Mailer
  ├─ send(string $to, string $subject, string $body, ...): bool  // リトライ（最大 2 回）
  ├─ sendContact(...): bool
  └─ sanitizeHeader(string $value): string  // ヘッダインジェクション対策
```

### 3.4 ASG — Adlaire Static Generator

#### ASG.Core.php

```php
class Generator
  ├─ buildDiff(): array            // 差分ビルド（ハッシュベース変更検出）
  ├─ buildAll(): array             // フルビルド
  ├─ clean(): void                 // static/ 再帰削除
  ├─ getStatus(): array            // ページ別ステータス（current/outdated/not_built）
  ├─ copyAssets(): void            // テーマ CSS/JS + uploads を差分コピー
  └─ deleteOrphanedFiles(): void

class BuildCache
  ├─ buildManifest(): array        // ハッシュベース差分検出
  ├─ needsFullRebuild(): bool      // 設定・テーマ変更判定
  └─ commitManifest(): void        // ビルド後状態更新
```

#### ASG.Template.php

```php
class TemplateRenderer
  ├─ render(string $template, array $context, string $partialsDir): string
  │   ├─ processPartials()         // {{> name}} 部分テンプレート（最大深度 10・循環参照防止）
  │   ├─ processEach()             // {{#each items}}...{{/each}}（@index, @first, @last）
  │   ├─ processIf()               // {{#if var}}...{{else}}...{{/if}}（ネスト対応）
  │   ├─ processRawVars()          // {{{var}}} 生 HTML 出力
  │   ├─ processVars()             // {{var|filter}} エスケープ出力
  │   ├─ resolveValue()            // ドット記法ネスト解決
  │   ├─ applyFilter()             // upper, lower, capitalize, trim, nl2br, length, truncate:N, default:value
  │   └─ warnUnprocessed()         // 未処理タグ警告
  └─ renderPage(string $slug, string $content): string

class ThemeService
  ├─ load(string $themeSelect): void
  ├─ buildContext(): array          // 動的 CMS 用テンプレートコンテキスト
  ├─ buildStaticContext(...): array // StaticEngine 用（admin=false）
  ├─ parseMenu(string $menuStr, string $currentPage): array
  └─ listThemes(): array

class MarkdownParser
  ├─ parse(string $markdown): array  // フロントマター + HTML
  └─ parseYamlValue(string $val): mixed
```

#### ASG.Utilities.php

```php
class ImageOptimizer
  └─ optimize(string $path, array $options): bool  // JPEG/PNG/WebP（GD ライブラリ）

class FileSystem
  ├─ read(string $path): ?string    // is_file() / is_readable() 事前チェック
  ├─ write(string $path, string $content): bool  // Logger 連携エラー記録
  ├─ ensureDir(string $path): bool  // レースコンディション安全
  └─ writeJson(string $path, mixed $data): bool  // JSON_THROW_ON_ERROR

class BuildCache                    // ビルド状態管理
class PathResolver                  // URL 生成・パス解決
```

### 3.5 AP — Adlaire Platform Controllers

#### AP.Controllers.php

**基底コントローラー:**

```php
abstract class BaseController
  ├─ ok(mixed $data = null): Response
  ├─ error(string $message, int $status = 400): Response
  ├─ requireRole(string $role): ?Response
  ├─ validateParam(Request $request, string $key, string $pattern, string $errorMsg): ?Response
  └─ validate(Request $request, array $rules, array $messages = []): array|Response
```

**コントローラー一覧:**

| コントローラー | 主要メソッド | 用途 |
|--------------|------------|------|
| `AuthController` | `showLogin`, `authenticate`, `logout` | 認証（マルチユーザー対応、Argon2id/bcrypt） |
| `DashboardController` | `index` | ダッシュボード表示 |
| `ApiController` | `dispatch` | REST API ルーティング → ACE.Api に委譲 |
| `AdminController` | `editField`, `uploadImage`, `deletePage`, `listRevisions`, `getRevision`, `restoreRevision`, `pinRevision`, `searchRevisions`, `userAdd`, `userDelete`, `redirectAdd`, `redirectDelete` | コンテンツ管理・ユーザー管理 |
| `CollectionController` | `create`, `delete`, `itemSave`, `itemDelete`, `migrate` | コレクション CRUD |
| `GitController` | `configure`, `test`, `pull`, `push`, `log`, `status`, `previewBranch` | Git/GitHub 連携 |
| `WebhookController` | `add`, `delete`, `toggle`, `test` | Webhook 管理 |
| `StaticController` | `buildDiff`, `buildAll`, `clean`, `buildZip`, `status`, `deployDiff` | 静的サイト生成 |
| `UpdateController` | `check`, `checkEnv`, `apply`, `listBackups`, `rollback`, `deleteBackup` | アップデート・バックアップ |
| `DiagnosticController` | `setEnabled`, `setLevel`, `preview`, `sendNow`, `clearLogs`, `getLogs`, `getSummary`, `health` | 診断・テレメトリ |

**ActionDispatcher（POST ap_action ルーター）:**

```php
class ActionDispatcher
  ├─ handle(Request $request): Response
  └─ registeredActions(): array    // 40+ 登録済みアクション
```

全 `ap_action` POST 値を対応する Controller メソッドに明示的にルーティング。

#### AP.Bridge.php — 廃止済み（Ver.2.0-40）

> **Ver.2.0-40 で削除**。全グローバル関数を Framework クラスメソッドに直接置換済み。

| 旧グローバル関数 | 置換先 |
|-----------------|--------|
| `json_read(file, dir)` | `\APF\Utilities\JsonStorage::read(file, dir)` |
| `json_write(file, data, dir)` | `\APF\Utilities\JsonStorage::write(file, data, dir)` |
| `h(str)` | `\APF\Utilities\Security::escape(str)` |
| `getSlug(p)` | `\APF\Utilities\Str::safePath(p)` |
| `host()` | `\AIS\Core\AppContext::resolveHost()` |
| `data_dir()` | `\AIS\Core\AppContext::dataDir()` |
| `settings_dir()` | `\AIS\Core\AppContext::settingsDir()` |
| `content_dir()` | `\AIS\Core\AppContext::contentDir()` |
| `JsonCache` クラス | `\APF\Utilities\JsonStorage` |

#### AP/JsEngine/（フロントエンド JavaScript モジュール群）

| ファイル | 役割 |
|---------|------|
| `wysiwyg.js` (2,889行) | WYSIWYG エディタ（ブロックベース・9 ブロックタイプ・7 インラインツール） |
| `editInplace.js` | `.editText` プレーンテキスト編集 |
| `dashboard.js` | ダッシュボード UI |
| `updater.js` | アップデート管理 UI |
| `static_builder.js` | 静的書き出し管理 UI |
| `collection_manager.js` | コレクション管理 UI |
| `git_manager.js` | Git 連携 UI |
| `webhook_manager.js` | Webhook 管理 UI |
| `api_keys.js` | API キー管理 UI |
| `diagnostics.js` | 診断データ管理 UI |
| `ap-api-client.js` | 静的サイト向け API クライアント |
| `ap-utils.js` | 共通ユーティリティ（`AP.getCsrf`, `AP.escHtml`, `AP.post`） |
| `ap-events.js` | イベントシステム |
| `ap-i18n.js` | 国際化 |
| `ap-search.js` | クライアントサイド検索 |
| `aeb-adapter.js` | AEB ES6 モジュール → グローバルスコープブリッジ |
| `autosize.js` | Textarea 自動リサイズ |

### 3.6 AEB — Adlaire Editor & Blocks

#### AEB.Core.js

```javascript
class Editor          // メインコントローラー
class EventBus        // Pub/Sub イベントシステム
class BlockRegistry   // ブロック型管理
class StateManager    // リアクティブ状態管理
class HistoryManager  // Undo/Redo（最大 50 操作）
```

#### AEB.Blocks.js

10 ブロックタイプ: `BaseBlock`（抽象）, `ParagraphBlock`, `HeadingBlock`(H2/H3), `ListBlock`, `QuoteBlock`, `CodeBlock`, `ImageBlock`, `TableBlock`, `ChecklistBlock`, `DelimiterBlock`

#### AEB.Utils.js

```javascript
sanitizer   // HTML サニタイゼーション（XSS 防御）
dom         // DOM 操作ヘルパー
selection   // テキスト選択ユーティリティ
keyboard    // キーボードショートカット
```

### 3.7 ADS — Adlaire Design System

#### ADS.Base.css

CSS カスタムプロパティ（`--ads-primary`, `--ads-bg`, `--ads-text` 等）、モダン CSS リセット、タイポグラフィ、ユーティリティクラス

#### ADS.Components.css

`.ads-btn`, `.ads-card`, `.ads-form`, `.ads-modal`, `.ads-alert`, `.ads-badge`, `.ads-tooltip`, `.ads-spinner`

#### ADS.Editor.css

`.aeb-editor`, `.aeb-block`, `.aeb-toolbar` 等エディタ特化スタイル

---

## 4. データ層実装仕様

### 4.1 ファイルパスマッピング

| データ | パス | 読み書き |
|-------|------|---------|
| サイト設定 | `data/settings/settings.json` | `JsonStorage` |
| 認証情報 | `data/settings/auth.json` | `JsonStorage` |
| ユーザー一覧 | `data/settings/users.json` | `JsonStorage` |
| API キャッシュ | `data/settings/update_cache.json` | `JsonStorage` |
| ログイン試行 | `data/settings/login_attempts.json` | `JsonStorage` |
| バージョン履歴 | `data/settings/version.json` | `JsonStorage` |
| ページ | `data/content/pages.json` | `JsonStorage` |
| 静的ビルド状態 | `data/settings/static_build.json` | `JsonStorage` |
| コレクション定義 | `data/content/ap-collections.json` | `JsonStorage` |
| コレクションアイテム | `data/content/collections/{name}/*.md` | `MarkdownParser` |
| リビジョン | `data/content/revisions/{fieldname}/*.json` | `RevisionManager` |
| ログ | `data/logs/ap-YYYY-MM-DD.log` | `AppLogger` |
| バックアップ | `backup/YYYYMMDD_His/` | `Updater` |

### 4.2 ディレクトリ構造

```
data/
├── settings/          # 設定ファイル群
├── content/           # コンテンツファイル群
│   ├── pages.json
│   ├── ap-collections.json
│   ├── collections/   # Markdown コレクション
│   └── revisions/     # リビジョン履歴
├── logs/              # アプリケーションログ
└── cache/             # ファイルキャッシュ
```

---

## 5. セキュリティ実装仕様

### 5.1 脅威対策マトリクス

| 脅威 | 対策 | 実装場所 |
|------|------|---------|
| XSS | `Security::escape()` による出力エスケープ | 全出力箇所 |
| CSRF | 32 バイトトークン + `hash_equals()` | `CsrfMiddleware`, `AdminManager` |
| セッションハイジャック | `session_regenerate_id(true)` | `AuthController::authenticate()` |
| セッション固定 | HttpOnly + SameSite=Lax + Secure(HTTPS) | `index.php` セッション設定 |
| パストラバーサル | 正規表現 + `Str::safePath()` | フィールド名・テーマ名・バックアップ名 |
| クリックジャッキング | `X-Frame-Options: SAMEORIGIN` | `.htaccess` + `SecurityHeadersMiddleware` |
| MIME スニッフィング | `X-Content-Type-Options: nosniff` | `.htaccess` + `SecurityHeadersMiddleware` |
| ブルートフォース | 5 回失敗 → 15 分ロックアウト（IP） | `AuthController`, `RateLimiter` |
| パスワード保存 | Argon2id 優先 / bcrypt フォールバック | `Security::hash()` |
| CSP | `default-src 'self'` | `.htaccess` |
| 画像アップロード悪用 | MIME 検証・PHP 実行禁止・ランダムファイル名 | `AdminController::uploadImage()` |
| SSRF | プライベート IP ブロック・DNS リバインディング防止 | `WebhookManager::isPrivateHost()` |
| SQL インジェクション | パラメータバインド・ソート方向制限 | `QueryBuilder` |
| CORS ポイズニング | `Vary: Origin` ヘッダー | `CorsMiddleware`, `ApiRouter` |
| オープンリダイレクト | リダイレクト先 URL 検証 | `AuthController` |
| Framework 直接アクセス | `RedirectMatch 403 ^.*/Framework/.*\.php$` | `.htaccess` |
| Permissions-Policy | `geolocation=(), microphone=(), camera=()` | `index.php`, `.htaccess` |
| HSTS | `Strict-Transport-Security` (HTTPS 時) | `index.php` |

---

## 6. イベント・フック機構

### 6.1 イベント駆動パターン

```php
// 型付きイベントディスパッチ
Application::events()->dispatch('content.changed', $payload);

// フック登録
Application::hooks()->register('admin-head', function() {
    return '<script src="..."></script>';
}, priority: 10);

// フック実行
$scripts = Application::hooks()->filter('admin-head', '');
```

### 6.2 登録済みイベント

| イベント名 | トリガー | リスナー |
|-----------|---------|---------|
| `content.changed` | コンテンツ保存時 | WebhookService |
| `auth.login` | ログイン成功時 | DiagnosticsCollector |
| `cache.invalidate` | キャッシュ無効化時 | ApiCache |

### 6.3 型付きイベントクラス

| クラス | プロパティ |
|-------|-----------|
| `PluginLoadedEvent` | `name`, `version` |
| `ContentSavedEvent` | `slug`, `content`, `user` |
| `AuthEvent` | `username`, `action`, `ip` |
| `SettingsChangedEvent` | `key`, `oldValue`, `newValue` |

---

## 7. 定数定義

| 定数 | 値 | 説明 |
|-----|---|------|
| `AP_VERSION` | `'2.0.40'` | 現在のバージョン |
| `AP_UPDATE_URL` | GitHub API URL | 最新リリース確認先 |
| `AP_BACKUP_GENERATIONS` | `5` | 保持するバックアップ世代数 |
| `AP_REVISION_LIMIT` | `30` | リビジョン保持数上限 |

---

## 8. Ver.2.0 破壊的変更

### 8.1 廃止対象

| 対象 | 状態 | 代替 |
|------|------|------|
| `migrate_from_files()` | 廃止 | — （Ver.1.3 以前のデータ構造は非サポート） |
| グローバル関数 `is_loggedin()` | 廃止 | `ACE\Admin\AdminManager::isLoggedIn()` |
| グローバル関数 `csrf_token()` | 廃止 | `ACE\Admin\AdminManager::csrfToken()` |
| グローバル関数 `verify_csrf()` | 廃止 | `CsrfMiddleware` |
| MD5 パスワード自動移行 | 廃止 | — （Argon2id/bcrypt のみサポート） |
| 単一パスワード認証（auth.json） | 廃止予定 | `UserManager`（users.json）に統一 |
| AP.Bridge.php | 廃止・削除済み | Framework クラスメソッドに直接置換（Ver.2.0-40） |

### 8.2 保留事項

[FRAMEWORK_RULEBOOK_v2.0.md](FRAMEWORK_RULEBOOK_v2.0.md) §4 を参照。

---

## 9. 変更履歴

| バージョン | 日付 | 変更内容 | 担当 |
|------------|------|----------|------|
| Ver.0.1-1 | 2026-03-10 | 初版。ARCHITECTURE.md §3/§5/§7/§8 および Design.md §6.2 から実装仕様を統合し新規作成 | Adlaire Group |
| Ver.2.0-1 | 2026-03-15 | Ver.2.0 全面改訂。旧エンジン名を Framework モジュール名に統一。Controller アーキテクチャ・ルーティング・DI・イベント駆動を反映。Ver.1.9 機能（構造化ログ・Config 外部化・マルチユーザー等）を統合。Ver.2.0 破壊的変更セクション追加 | Adlaire Group |

---

*本ドキュメントは AdlairePlatform の公式詳細設計書です。*
*内容は Adlaire Group の承認なく変更・転載することを禁じます。*
