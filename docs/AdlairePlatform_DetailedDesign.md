# AdlairePlatform 詳細設計

Ver.0.1-5 | 最終更新: 2026-03-18

---

## 1. 概要

本書は AdlairePlatform の詳細設計を定める。設計・開発・運用の規則については `FRAMEWORK_RULEBOOK_v3.0.md` を参照のこと。

### 1.1 設計方針

- エンジン駆動モデル（FRAMEWORK_RULEBOOK §2）に基づき、全フレームワークを設計する
- 各エンジンは自己完結し、フレームワーク間の直接 `import` は禁止する
- フレームワークがサーバと通信する際は、すべて ACS グローバルシングルトン `globalThis.__acs`（FRAMEWORK_RULEBOOK §3）を経由する
- DI コンテナパターンは禁止する。サービス間の依存解決は、ACS については `globalThis.__acs`、それ以外については `ApplicationFacade` のプロパティ直接参照により行う
- 外部依存ゼロ — サードパーティライブラリおよび外部パッケージへの依存は禁止し、言語標準ライブラリのみを使用する
- 複数のフレームワークから参照される共有型ファイルの作成は禁止する。各フレームワークが必要とする型はそのフレームワーク自身が定義するか、ACS が `ACS.d.ts` で公開している型を `import type` により参照する
- フラットファイル JSON ストレージを採用し、データベースを不要とする
- FTP アップロードのみで動作する設計とする

### 1.2 プロダクト構成

| プロダクト | 言語 | 動作環境 |
|-----------|------|---------|
| Adlaire Server System（ASS） | PHP 8.4 以上 | 共有レンタルサーバ |
| Adlaire Client Services（ACS） | TypeScript | ブラウザおよび Deno |
| AdlairePlatform（AFE / ACE / AIS / ASG / AEB / ADS） | TypeScript / CSS | Deno サーバおよびブラウザ |

---

## 2. 型定義方針

FRAMEWORK_RULEBOOK §2.1「共有型ファイルの作成禁止」に準拠する。複数のフレームワークから参照される共有型ファイル（`Framework/types.ts` 等）の作成は禁止する。

### 2.1 基本規則

- 各フレームワークが必要とする型は、そのフレームワーク自身が定義する
- ACS が `ACS.d.ts` で公開している型を参照する場合は、`import type` により参照する
- フレームワーク間の直接 `import` は禁止する

### 2.2 ACS 型参照

ACS のインターフェース型（`AdlaireClient`, `AuthModule`, `StorageModule`, `FileModule`, `HttpModule` 等）は `ACS/ACS.d.ts` に定義される。各フレームワークがこれらの型を必要とする場合は、以下の形式で参照する。

```typescript
// 許可: 型のみの参照
import type { AdlaireClient } from "../ACS/ACS.d.ts";

// 禁止: 実装の import
import { AuthService } from "../ACS/ACS.Core.ts";
```

値を伴う `import` は禁止し、`import type` による型定義のみの参照に限定する。

### 2.3 フレームワーク固有型

各フレームワークが必要とする型は、そのエンジン内のコンポーネントで定義する。他のフレームワークの型定義を直接参照してはならない。

---

## 3. AFE — Adlaire Foundation Engine

APF（Adlaire Platform Foundation）を廃止し、コア基盤を継承する新設フレームワークである。Router、Request、Response、EventBus、MiddlewarePipeline 等の基盤を提供する。

### 3.1 AFE.Interface.ts

| インターフェース | 主要メソッド |
|----------------|-------------|
| `RouterInterface` | `get`, `post`, `put`, `patch`, `delete`, `any`, `group`, `middleware`, `dispatch`, `name`, `route` |
| `RequestInterface` | `method`, `httpMethod`, `uri`, `query`, `post`, `json`, `header`, `cookie`, `ip`, `isJson`, `isAjax`, `isPost`, `param`, `requestId` |
| `ResponseInterface` | `getContent`, `getStatusCode`, `getHeaders`, `withHeader` |
| `MiddlewareInterface` | `handle(request, next)` |
| `EventBusInterface` | `listen`, `dispatch`, `remove`, `clear` |
| `ValidatorInterface` | `validate`, `fails`, `errors` |
| `ConfigInterface` | `get`, `setDefaults`, `clearCache` |
| `FileSystemInterface` | `readFile`, `writeFile`, `exists`, `mkdir`, `readDir` |
| `JsonStorageInterface` | `read`, `write`, `delete`, `exists`, `list` |

### 3.2 AFE.Class.ts

| クラス / 型 | 役割 |
|------------|------|
| `HttpMethod` | HTTP メソッド列挙（GET / POST / PUT / PATCH / DELETE / OPTIONS / HEAD）。`static readonly` パターンで定義する |
| `LogLevel` | ログレベル列挙（DEBUG / INFO / WARN / ERROR / FATAL）。`fromName()`, `label` を提供する |
| `FrameworkError` | 基底エラークラス。`context` プロパティを持つ |
| `ValidationError` | バリデーションエラー。`errors` プロパティを持つ |
| `NotFoundError` | 404 エラー |

### 3.3 AFE.Core.ts

| クラス | 役割 |
|--------|------|
| `Router` | ルーター。`RouterInterface` を実装する。`reduceRight` によるミドルウェアパイプラインを構築し、パスパラメータの解決を行う |
| `Request` | リクエスト。`RequestInterface` を実装する。Deno 標準 Request からの変換を行う |
| `Response` | レスポンス。`ResponseInterface` を実装する。JSON / HTML / テキスト / リダイレクトの各レスポンス生成を担う。`json`, `html`, `redirect`, `jsonError` の静的ファクトリメソッドを持つ |
| `EventBus` | 優先度付きイベントの発行・購読を管理する。`EventBusInterface` を実装する |
| `MiddlewarePipeline` | ミドルウェアチェーンの実行を担う |

### 3.4 AFE.Api.ts

| 内容 | 役割 |
|------|------|
| システムルート登録 | ヘルスチェック（`/api/health`）、バージョン情報等のシステムルートを登録する。`Deno.version.deno` によるランタイム情報を返す |
| レスポンスヘルパー | `jsonError`、`jsonSuccess`、`jsonValidationError` のレスポンスヘルパーを提供する |

### 3.5 AFE.Utilities.ts

| クラス | 役割 |
|--------|------|
| `Str` | 文字列ユーティリティ。`safePath`, `slug`, `truncate`, `random` を提供する |
| `Arr` | 配列ユーティリティ。`get<T>`, `set`, `has`, `flatten`, `only`, `except` を提供する |
| `Config` | 設定管理。環境変数 → 設定ファイル → デフォルト値の優先順で解決する。`ConfigInterface` を実装する |
| `Validator` | バリデーション。`Result<ValidatedData>` を返す。`ValidatorInterface` を実装する |
| `Security` | HTML エスケープ等のセキュリティユーティリティを提供する |
| `FileSystem` | ファイルシステム操作。`Deno.*` API を使用する。`FileSystemInterface` を実装する |
| `JsonStorage` | JSON ファイルの永続化。`Map` ベースのキャッシュを備える。`JsonStorageInterface` を実装する |

---

## 4. ACS — Adlaire Client Services

サーバ（ASS）との通信を一元的に担うクライアントエンジンである。詳細仕様は FRAMEWORK_RULEBOOK §3 を参照のこと。

### 4.1 ACS.Interface.ts

| インターフェース | 用途 |
|----------------|------|
| `ClientFactoryInterface` | `AdlaireClient` インスタンスの生成 |
| `ClientConfig` | クライアント設定（`baseUrl`, `apiPrefix`, `timeout`, `credentials`） |
| `HttpModuleInterface` | HTTP 通信のインターフェース（`get`, `post`, `put`, `delete`） |
| `StorageModuleInterface` | ストレージ操作のインターフェース（`read`, `write`, `delete`, `exists`, `list`） |
| `FileModuleInterface` | ファイル操作のインターフェース（`upload`, `download`, `delete`, `info`） |
| `EventSourceInterface` | Server-Sent Events のインターフェース |
| `RetryConfig` | リトライ設定（指数バックオフ） |

### 4.2 ACS.Class.ts

| クラス / 型 | 役割 |
|------------|------|
| `ConnectionState` | 接続状態の列挙（CONNECTED / DISCONNECTED / RECONNECTING） |
| `STORAGE_DIRS` | ストレージディレクトリ定数 |
| `API_ENDPOINTS` | API エンドポイント定数 |
| `NetworkError` | ネットワークエラー |
| `TimeoutError` | タイムアウトエラー |
| `ServerError` | サーバエラー |

### 4.3 ACS.Core.ts

| クラス | 役割 |
|--------|------|
| `ClientFactory` | `ClientFactoryInterface` を実装する。`AdlaireClient` のインスタンスを生成し、`globalThis.__acs` として公開する |
| `HttpTransport` | 低レベル HTTP 通信層。`HttpModuleInterface` を実装する。インターセプター、`AbortController` によるキャンセル、指数バックオフ付きリトライを提供する |
| `StorageService` | JSON ストレージ操作の実装。ファイルの読み書き、削除、存在確認、および一覧取得を行う |
| `FileService` | ファイル操作の実装。アップロード、ダウンロード、削除、および画像情報取得を行う |
| `EventSourceService` | Server-Sent Events の受信を行う |

#### globalThis.__acs の初期化

ACS はモジュールキャッシュ方式（FRAMEWORK_RULEBOOK §3.5）に従い、エントリモジュールのトップレベルでインスタンスを生成する。

```typescript
// ACS エントリモジュール（モジュールキャッシュ方式）

// 1. モジュールスコープでインスタンスを生成（一度だけ評価される）
const acs: ACSInstance = createACSInstance();

// 2. モジュール評価の副作用として globalThis に代入
globalThis.__acs = Object.freeze({
  auth: acs.auth,
  storage: acs.storage,
  files: acs.files,
  http: acs.http,
});

// 3. 型安全なアクセスのためにエクスポート（ACS 内部でのみ使用）
export { acs };
```

### 4.4 ACS.Api.ts

| 内容 | 役割 |
|------|------|
| `createClient` | 本番クライアントの生成を提供する |
| `createMockClient` | テスト用モッククライアントの生成を提供する |
| `withRetry` | 指数バックオフによるリトライを提供する |

### 4.5 ACS.Utilities.ts

| 内容 | 役割 |
|------|------|
| `joinUrl` | URL 結合 |
| `buildQueryString` | クエリ文字列構築 |
| `bearerHeader` | Bearer トークンヘッダー生成 |
| `csrfHeader` | CSRF ヘッダー生成 |
| `jsonHeaders` | JSON ヘッダー生成 |
| `extractData` | レスポンスデータ抽出 |
| `objectToFormData` | FormData 変換 |
| `calculateBackoff` | バックオフ時間計算 |

### 4.6 ACS.d.ts

ACS が外部に公開する型定義を集約する。各フレームワークは `import type` 構文のみでこのファイルを参照する。

### 4.7 ClientEngine/（サブディレクトリ）

| ファイル | 用途 |
|---------|------|
| `acs-api-client.ts` | API クライアントラッパー |
| `acs-api-keys.ts` | API キー管理 UI |

---

## 5. ACE — Adlaire Content Engine

コレクション、コンテンツ、メタデータの管理、および管理画面 UI を提供する。

### 5.1 ACE.Interface.ts

| インターフェース | 主要メソッド |
|----------------|-------------|
| `CollectionManagerInterface` | `create`, `delete`, `listCollections`, `getSchema`, `isEnabled` |
| `ContentManagerInterface` | `getItem`, `saveItem`, `deleteItem`, `listItems`, `search` |
| `ItemSaveData` | コンテンツアイテムの保存データ型 |
| `ListItemsOptions` | コンテンツ一覧取得のオプション型 |
| `MetaManagerInterface` | `extractMeta`, `buildMeta`, `mergeMeta` |
| `ContentValidatorInterface` | `validate` |
| `CollectionServiceInterface` | コレクションサービスのインターフェース |
| `ApiRouterInterface` | `registerEndpoint`, `dispatch` |
| `WebhookServiceInterface` | `register`, `delete`, `list`, `dispatch` |
| `RevisionServiceInterface` | `saveRevision`, `getRevisions`, `restoreRevision` |

### 5.2 ACE.Class.ts

| クラス / 型 | 役割 |
|------------|------|
| `CollectionError` | コレクションエラー |
| `ContentNotFoundError` | コンテンツ未検出エラー |
| `DuplicateSlugError` | スラグ重複エラー |
| `SLUG_PATTERN` | スラグの正規表現パターン |

### 5.3 ACE.Core.ts

| クラス | 役割 |
|--------|------|
| `CollectionManager` | コレクションの CRUD およびスキーマバリデーションを行う。`CollectionManagerInterface` を実装する |
| `ContentManager` | コンテンツの CRUD を行う。ソート、フィルタ、およびページネーションを提供する。`ContentManagerInterface` を実装する |
| `MetaManager` | メタデータの管理および検索インデックスの構築を行う。`MetaManagerInterface` を実装する |
| `ContentValidator` | コレクションスキーマに基づくバリデーションを行う。`ContentValidatorInterface` を実装する |

#### ACS 利用パターン

ACE.Core.ts は `globalThis.__acs` を参照する唯一のコンポーネントである（FRAMEWORK_RULEBOOK §3.4「Core 限定参照」）。ACE 内の他のコンポーネントが ACS の機能を必要とする場合は、Core を介して間接的に利用する。

```typescript
// ACE.Core.ts 内での ACS 利用例
const collections = await globalThis.__acs.storage.read("collections.json", "content");
```

### 5.4 ACE.Api.ts

| 内容 | 役割 |
|------|------|
| コレクションルート登録 | コレクション CRUD、コンテンツアイテム CRUD、およびメタデータ取得のルートを登録する |

### 5.5 ACE.Utilities.ts

| クラス | 役割 |
|--------|------|
| `WebhookService` | コンテンツ変更時の Webhook 配信を行う |
| `RevisionService` | コンテンツのリビジョン管理を行う |
| `ApiRouter` | ACE 専用の API ルーティングを提供する |

### 5.6 ClientEngine/（サブディレクトリ）

| ファイル | 用途 |
|---------|------|
| `ace-dashboard.ts` | ダッシュボード UI |
| `ace-collection-manager.ts` | コレクション管理 UI |
| `ace-edit-inplace.ts` | インプレース編集 |
| `ace-search.ts` | コンテンツ検索 |
| `ace-webhook-manager.ts` | Webhook 管理 UI |

---

## 6. AIS — Adlaire Infrastructure Services

アプリケーションコンテキスト、i18n、API キャッシュ、診断、およびリクエストロギングを提供する。AP（Adlaire Platform）の RequestLoggingMiddleware を統合する。

### 6.1 AIS.Interface.ts

| インターフェース | 主要メソッド |
|----------------|-------------|
| `AppContextInterface` | `resolveHost`, `dataDir`, `settingsDir`, `contentDir` |
| `AppContextPaths` | アプリケーションパスの型定義 |
| `HostInfo` | ホスト情報の型定義 |
| `I18nInterface` | `init`, `t`, `locale`, `setLocale` |
| `DiagnosticsManagerInterface` | `log`, `startTimer`, `stopTimer`, `collect` |
| `DiagnosticsConfig` | 診断設定の型定義 |
| `ApiCacheInterface` | `get`, `set`, `delete`, `has`, `flush`, `stats` |
| `GitServiceInterface` | `status`, `log`, `pull`, `diff` |
| `GitServiceConfig` | Git サービス設定の型定義 |
| `UpdateServiceInterface` | `checkForUpdate`, `applyUpdate` |
| `EnvironmentCheck` | 環境チェック結果の型定義 |
| `UpdateApplyResult` | アップデート適用結果の型定義 |

### 6.2 AIS.Class.ts

| クラス / 型 | 役割 |
|------------|------|
| `InfrastructureError` | インフラストラクチャエラー |
| `ConfigValidationError` | 設定バリデーションエラー |

### 6.3 AIS.Core.ts

| クラス | 役割 |
|--------|------|
| `AppContext` | アプリケーション設定の読み込みおよびパス管理を行う。`AppContextInterface` を実装する |
| `I18n` | 多言語対応のロケール管理および翻訳ファイルの読み込みを行う。`I18nInterface` を実装する |
| `RequestLoggingMiddleware` | リクエスト / レスポンスのログ記録を行う。AP から統合された |

### 6.4 AIS.Api.ts

| 内容 | 役割 |
|------|------|
| インフラルート登録 | 診断情報取得、キャッシュ制御、i18n リソース取得等のルートを登録する |

### 6.5 AIS.Utilities.ts

| クラス | 役割 |
|--------|------|
| `DiagnosticsManager` | 診断ログの記録、環境チェック、パフォーマンス計測を行う |
| `ApiCache` | `Map` ベースの API レスポンスキャッシュおよび無効化を行う |
| `GitService` | Git リポジトリ操作を行う |
| `UpdateService` | アプリケーションの更新チェックおよび適用を行う |

### 6.6 ClientEngine/（サブディレクトリ）

| ファイル | 用途 |
|---------|------|
| `ais-diagnostics.ts` | 診断 UI |
| `ais-updater.ts` | アップデート管理 UI |
| `ais-i18n.ts` | クライアントサイド i18n |

---

## 7. ASG — Adlaire Static Generator

Markdown パース、テンプレートレンダリング、および静的サイトビルドを行う。

### 7.1 ASG.Interface.ts

| インターフェース | 主要メソッド |
|----------------|-------------|
| `GeneratorInterface` | `buildAll`, `buildDiff`, `clean`, `getStatus` |
| `GeneratorConfig` | ジェネレータ設定の型定義 |
| `BuilderInterface` | `buildPage`, `buildIndex` |
| `TemplateRendererInterface` | `render`, `registerHelper`, `registerPartial` |
| `MarkdownServiceInterface` | `parse`, `addRule` |
| `StaticServiceInterface` | 静的ファイル配信のインターフェース |
| `BuildCacheInterface` | ビルドキャッシュのインターフェース |
| `DiffBuilderInterface` | 差分ビルドのインターフェース |
| `SiteRouterInterface` | `resolveSlug`, `buildUrl`, `generateSitemap` |
| `StaticFileSystemInterface` | 静的ファイルシステム操作のインターフェース |
| `DeployerInterface` | デプロイ処理のインターフェース |
| `ImageProcessorInterface` | 画像処理のインターフェース |
| `ThemeManagerInterface` | `load`, `getTemplate`, `listThemes`, `buildContext` |

### 7.2 ASG.Class.ts

| クラス / 型 | 役割 |
|------------|------|
| `BuildStatus` | ビルド状態列挙（PENDING / BUILDING / SUCCESS / FAILED） |
| `UrlStyle` | URL スタイル列挙（PRETTY / RAW） |
| `BuildError` | ビルドエラー |
| `TemplateError` | テンプレートエラー |
| `ThemeError` | テーマエラー |
| `PARTIAL_MAX_DEPTH` | パーシャルの最大ネスト深度 |

### 7.3 ASG.Core.ts

| クラス | 役割 |
|--------|------|
| `Generator` | 静的サイト生成の統括を行う。`GeneratorInterface` を実装する |
| `HybridResolver` | 動的 / 静的コンテンツの解決を行う |
| `BuildCache` | コンテンツハッシュによるビルドキャッシュの管理を行う |
| `SiteRouter` | 静的サイトのルーティングを行う |
| `Deployer` | デプロイ処理を行う |
| `HtmlMinifier` | HTML の最小化を行う |
| `CssMinifier` | CSS の最小化を行う |

### 7.4 ASG.Api.ts

| 内容 | 役割 |
|------|------|
| ジェネレータルート登録 | ビルド実行、ビルドステータス取得、プレビュー生成等のルートを登録する |

### 7.5 ASG.Utilities.ts

| クラス | 役割 |
|--------|------|
| `TemplateRenderer` | テンプレートのレンダリングおよびパーシャル展開を行う。`TemplateRendererInterface` を実装する |
| `MarkdownService` | Markdown から HTML への変換および Front Matter の解析を行う。`MarkdownServiceInterface` を実装する |
| `ThemeManager` | テーマの読み込みおよび切り替えを行う。`ThemeManagerInterface` を実装する |
| `Builder` | 差分ビルドの実行を行う。`BuilderInterface` を実装する |

### 7.6 ClientEngine/（サブディレクトリ）

| ファイル | 用途 |
|---------|------|
| `asg-static-builder.ts` | 静的ビルド UI |

---

## 8. AEB — Adlaire Editor & Blocks

AP（Adlaire Platform）の JsEngine/ から WYSIWYG エディタ関連を統合する。ブロックエディタおよび WYSIWYG を提供する。

### 8.1 AEB.Core.ts

| クラス | 役割 |
|--------|------|
| `EventBus` | エディタ内イベント管理を行う |
| `BlockRegistry` | ブロックタイプの登録・取得を行う |
| `StateManager` | エディタ状態の管理を行う |
| `HistoryManager` | Undo / Redo 履歴の管理を行う |
| `Editor` | ブロックエディタの初期化・レンダリング・保存を行う |

### 8.2 AEB.Blocks.ts

| クラス | 役割 |
|--------|------|
| `BaseBlock` | ブロック基底クラス |
| `ParagraphBlock` | 段落ブロック |
| `HeadingBlock` | 見出しブロック |
| `ListBlock` | リストブロック |
| `QuoteBlock` | 引用ブロック |
| `CodeBlock` | コードブロック |
| `ImageBlock` | 画像ブロック |
| `TableBlock` | テーブルブロック |
| `ChecklistBlock` | チェックリストブロック |
| `DelimiterBlock` | 区切り線ブロック |

### 8.3 AEB.Utils.ts

| 内容 | 役割 |
|------|------|
| `sanitizer` | HTML サニタイズ |
| `dom` | DOM 操作ユーティリティ |
| `selection` | 選択範囲の保存・復元・判定 |
| `keyboard` | キーボードショートカットの管理 |

### 8.4 ClientEngine/（サブディレクトリ）

| ファイル | 用途 |
|---------|------|
| `aeb-wysiwyg.ts` | WYSIWYG エディタのブラウザ統合 |
| `aeb-autosize.ts` | テキストエリアの自動サイズ調整 |

---

## 9. ADS — Adlaire Design System

ACE（Adlaire Content Engine）の AdminEngine/ から管理画面スタイルを統合する。ベーススタイル、コンポーネントスタイル、エディタスタイル、および管理画面スタイルを提供する。

### 9.1 ADS.Base.css

CSS リセット、CSS カスタムプロパティ（カラー・タイポグラフィ・スペーシング）、およびベースタイポグラフィを定義する。

### 9.2 ADS.Components.css

ボタン、フォーム、カード、テーブル、ナビゲーション、モーダル等のコンポーネント固有のスタイルを定義する。

### 9.3 ADS.Editor.css

エディタ、ブロック、ツールバー、および管理画面（ダッシュボード、サイドバー、ヘッダー）向けのスタイルを定義する。

### 9.4 AdminEngine/（サブディレクトリ）

| ファイル | 用途 |
|---------|------|
| `dashboard.css` | ダッシュボードレイアウト |

---

## 10. ASS — Adlaire Server System

AP（Adlaire Platform）の AuthMiddleware および SecurityHeadersMiddleware を統合し、認証・セキュリティのすべてを担う。PHP で実装する。

### 10.1 ASS.Interface.php

| インターフェース | 主要メソッド |
|----------------|-------------|
| `AuthServiceInterface` | `login`, `logout`, `session`, `csrfToken`, `hashPassword`, `verifyPassword` |
| `UserManagerInterface` | ユーザーの CRUD 操作 |
| `SessionAdminInterface` | セッション管理の管理者操作 |
| `StorageServiceInterface` | `read`, `write`, `delete`, `exists`, `list` |
| `FileServiceInterface` | `upload`, `delete`, `list`, `info` |
| `GitServiceInterface` | `status`, `log`, `pull`, `diff` |
| `RequestInterface` | HTTP リクエストの抽象化 |
| `ResponseInterface` | HTTP レスポンスの生成 |

### 10.2 ASS.Class.php

| クラス | 役割 |
|--------|------|
| `ApiResponse` | 統一レスポンス形式 |
| `Request` | HTTP リクエストの抽象化 |
| `Response` | HTTP レスポンスの生成 |

### 10.3 ASS.Core.php

| クラス | 役割 |
|--------|------|
| `AuthService` | ログイン・ログアウト・セッション管理・トークン検証を行う。bcrypt によるパスワードハッシュおよびログイン試行制限を実装する |
| `UserManager` | ユーザーの CRUD 操作を行う |
| `SessionAdmin` | セッション管理の管理者操作を行う |
| `StorageService` | JSON ファイルの読み書き・削除・存在確認・一覧取得を行う。`data/` 配下の CRUD 操作を提供する |
| `FileService` | ファイルのアップロード・ダウンロード・削除・画像最適化を行う。`uploads/` 配下のファイル操作を提供する |
| `GitService` | Git リポジトリのコミット・プッシュ・プル・ログ取得を行う |
| `ServerDiagnostics` | サーバサイド診断を行う |

### 10.4 ASS.Api.php

| 内容 | 役割 |
|------|------|
| エンドポイント登録 | 認証（ログイン / ログアウト / セッション検証）、ストレージ CRUD、ファイル操作、Git 操作、API キー管理、および管理者操作の各エンドポイントを登録する |

### 10.5 ASS.Utilities.php

| クラス | 役割 |
|--------|------|
| `PathSecurity` | パストラバーサル防止 |
| `SessionManager` | PHP セッション管理 |
| `CsrfManager` | CSRF トークンの発行・検証 |
| `Token` | トークンの生成・検証 |
| `MimeType` | MIME タイプ判定 |
| `GitCommand` | Git コマンドの安全な実行 |
| `AdminTemplate` | 管理画面テンプレートのレンダリング |

---

## 11. エントリポイントおよび初期化

### 11.1 main.ts

HTTP サーバのエントリポイントである。`Deno.serve` により HTTP リクエストを受け付け、`bootstrap.ts` で初期化した `ApplicationFacade` およびルーターを通じてリクエストを処理する。

### 11.2 bootstrap.ts

`ApplicationFacade` の初期化を行う。DI コンテナは使用せず、各サービスのインスタンスを直接生成して `ApplicationFacade` のプロパティとして公開する。ACS の初期化（`globalThis.__acs` の公開）を bootstrap 処理の最初に実行する（FRAMEWORK_RULEBOOK §3.4「初期化順序」）。

```typescript
class ApplicationFacade {
  readonly router: Router;
  readonly events: EventBus;
  readonly context: AppContext;
  readonly i18n: I18n;

  static boot(basePath: string): ApplicationFacade;
  static get(): ApplicationFacade;
  static isBooted(): boolean;
  static reset(): void;
}
```

#### 初期化順序

1. ACS の初期化（`globalThis.__acs` の公開）
2. `ApplicationFacade` のインスタンス生成
3. 設定の読み込み（`globalThis.__acs.storage` 経由）
4. i18n の初期化
5. イベントリスナーの登録

### 11.3 routes.ts

ルートおよびミドルウェアの登録を行う。セキュリティヘッダー、リクエストロギングのミドルウェアパイプラインを構築する。

### 11.4 Framework/mod.ts

全フレームワークモジュールのバレルエクスポート（エントリポイント）を提供する。

---

## 12. 非同期設計

### 12.1 基本原則

- `globalThis.__acs` 経由の I/O はすべて `async` / `Promise` で処理する
- 純粋ロジック（バリデーション、テンプレート処理、文字列操作）は同期処理とする
- 独立した I/O は `Promise.all()` で並列化する

### 12.2 並列化の例

```typescript
const [settings, pages] = await Promise.all([
  globalThis.__acs.storage.read("settings.json", "settings"),
  globalThis.__acs.storage.read("pages.json", "content"),
]);
```

---

## 13. テンプレートエンジン

`ASG.Utilities.ts` の `TemplateRenderer` は Handlebars ライクな構文をサポートする。

### 13.1 サポート構文

| 構文 | 用途 |
|------|------|
| `{{variable}}` | 変数展開（HTML エスケープ付き） |
| `{{{variable}}}` | 変数展開（エスケープなし） |
| `{{#if condition}}...{{/if}}` | 条件分岐 |
| `{{#each items}}...{{/each}}` | ループ |
| `{{> partial}}` | パーシャル挿入 |
| `{{variable \| filter}}` | フィルターチェーン |

### 13.2 コンパイルキャッシュ

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

## 14. 準拠文書

本書は以下の文書に準拠する。矛盾がある場合は上位の文書が優先される。

1. `docs/FRAMEWORK_RULEBOOK_v3.0.md` — フレームワーク規約
2. `docs/VERSIONING.md` — バージョニング規則
3. `docs/DOC_RULEBOOK.md` — ドキュメント管理ルール

---

## 15. 開発保留リスト

以下は今後の開発候補として精査済みの提案である。優先度・スケジュールが確定次第、順次着手する。

### 15.1 先送り項目

以下の 5 件は明示的に先送りとする。

1. プラグインシステム
2. Embed ブロック（YouTube / X / CodePen）
3. ヘルスチェックの外部通知
4. CDN キャッシュパージ連携
5. キーボードショートカットのカスタマイズ

### 15.2 機能追加（70 件）

#### ACS（通信基盤）— 12 件

| # | 機能名 | 概要 | 対象ファイル |
|---|--------|------|-------------|
| B-1 | HttpTransport に自動リトライ統合 | `withRetry` を `ClientConfig.retryConfig` に統合し 5xx/タイムアウト時に自動リトライ | `ACS.Core.ts` `ACS.Interface.ts` |
| B-2 | SSE 自動再接続の実装 | `EventSourceService.onerror` の指数バックオフ付き自動再接続 | `ACS.Core.ts` |
| B-3 | StorageService.watch の実装 | 現在スタブ。SSE ベースのファイル変更通知を実装 | `ACS.Core.ts` |
| B-4 | リクエスト/レスポンスのデバッグロガー | 全通信の構造化ログ記録（メソッド、URL、ステータス、所要時間） | `ACS.Core.ts` |
| B-5 | StorageService にバッチ書き込み（writeMany） | `readMany` はあるが一括書き込みがない | `ACS.Core.ts` `ACS.Interface.ts` |
| B-6 | HttpTransport にリクエストキューイング | 同時リクエスト数上限と超過分のキュー待機 | `ACS.Core.ts` `ACS.Interface.ts` |
| B-7 | オフライン検出と自動復帰 | `navigator.onLine` + SSE 接続状態でオフライン検出、復帰時に保留リクエスト再送 | `ACS.Core.ts` `ACS.Class.ts` |
| B-8 | リクエストデデュプリケーション | 同一 URL+パラメータの同時 GET で Promise を共有し重複排除 | `ACS.Core.ts` |
| B-9 | レスポンスキャッシュ層（SWR パターン） | Stale-While-Revalidate キャッシュを `HttpTransport` に追加 | `ACS.Core.ts` `ACS.Interface.ts` |
| B-10 | 通信メトリクス収集 | エンドポイント別のリクエスト数、平均レイテンシ、エラー率、帯域幅の自動計測 | `ACS.Core.ts` |
| B-11 | ファイルアップロードのプログレス通知 | `FileService.upload` に `onProgress` コールバック追加 | `ACS.Core.ts` `ACS.Interface.ts` |
| B-12 | リクエストのプライオリティキュー | `priority: "high" | "normal" | "low"` で同時接続数制限下の優先処理 | `ACS.Core.ts` `ACS.Interface.ts` |

#### AFE（プラットフォーム基盤）— 7 件

| # | 機能名 | 概要 | 対象ファイル |
|---|--------|------|-------------|
| B-13 | Router に名前付きルートの逆引き（URL 生成） | `url(name, params)` メソッド追加 | `AFE.Core.ts` `AFE.Interface.ts` |
| B-14 | EventBus に非同期リスナーサポート | `dispatchAsync` で async リスナーの結果を `Promise.all` で待機 | `AFE.Core.ts` `AFE.Interface.ts` |
| B-15 | EventBus にワイルドカードリスナー | `listen("*", fn)` で全イベント横断監視 | `AFE.Core.ts` |
| B-16 | MiddlewarePipeline にタイムアウト制御 | 個々のミドルウェアに処理時間上限を設定 | `AFE.Core.ts` |
| B-17 | Router にルートレベルのレスポンスキャッシュ | `.cache(ttl)` で同一パス+メソッドのレスポンスを TTL 付きキャッシュ | `AFE.Core.ts` `AFE.Interface.ts` |
| B-18 | Request にファイルアップロードアクセサ | `Request.files()` で FormData 内の File に型安全アクセス | `AFE.Core.ts` `AFE.Interface.ts` |
| B-19 | Response にストリーミングレスポンス | `Response.stream(readableStream)` でチャンク送信対応 | `AFE.Core.ts` `AFE.Interface.ts` |

#### ACE（コンテンツ管理）— 8 件

| # | 機能名 | 概要 | 対象ファイル |
|---|--------|------|-------------|
| B-20 | ContentManager.listItems の N+1 問題解消 | `StorageService.readMany` によるバッチ取得に変更 | `ACE.Core.ts` |
| B-21 | MetaManager YAML パーサーのネスト対応 | インデント付きマップやマルチライン文字列のパース | `ACE.Core.ts` |
| B-22 | コレクションスキーマのマイグレーション | スタブ状態の `migrate` 実装。フィールドのリネーム・型変換・デフォルト値適用 | `ACE.Core.ts` |
| B-23 | コンテンツのバージョニング（自動リビジョン保存） | `saveItem` 時に旧版を `revisions/` へ自動保存、差分表示・復元可能に | `ACE.Core.ts` |
| B-24 | コンテンツのワークフロー（下書き→レビュー→公開） | `status` フィールドとロール別遷移権限制御 | `ACE.Core.ts` `ACE.Interface.ts` |
| B-25 | フィールドバリデーションルール拡張 | `pattern`、`enum`、`unique` を `FieldDef` に追加 | `ACE.Core.ts` `ACE.Interface.ts` |
| B-26 | WebhookService の ContentManager 統合 | アイテム作成・更新・削除時の自動 Webhook 送信 | `ACE.Core.ts` `ACE.Utilities.ts` |
| B-27 | コンテンツの差分比較（Diff） | `diffItems(itemA, itemB)` で行単位の追加/削除/変更を構造化返却 | `ACE.Core.ts` |

#### ASG（静的サイト生成）— 10 件

| # | 機能名 | 概要 | 対象ファイル |
|---|--------|------|-------------|
| B-28 | Deployer.createZip の実装 | `Deno.Command` で zip/tar を呼ぶ具体的実装 | `ASG.Core.ts` |
| B-29 | MarkdownService の具体実装 | Front Matter パース、見出し・リスト・コードブロック・テーブルの HTML 変換 | `ASG.Utilities.ts` |
| B-30 | HybridResolver にキャッシュ TTL | 自動期限切れ機能の追加 | `ASG.Core.ts` |
| B-31 | RSS/Atom フィード生成 | ブログ用途のフィード自動生成 | `ASG.Core.ts` |
| B-32 | ビルド進捗のリアルタイム通知 | SSE/コールバックでクライアントに進捗配信 | `ASG.Core.ts` `ASG.Interface.ts` |
| B-33 | 画像の自動 WebP 変換とレスポンシブ srcset 生成 | ビルド時にマルチサイズ WebP 生成、`<picture>` + `srcset` 出力 | `ASG.Core.ts` |
| B-34 | CSS/JS のバンドルとフィンガープリント | 結合・圧縮、コンテンツハッシュ付きファイル名出力 | `ASG.Core.ts` `ASG.Interface.ts` |
| B-35 | テンプレートの継承（レイアウト / ブロック） | `{% extends %}` `{% block %}` 形式の実装 | `ASG.Utilities.ts` |
| B-36 | ページネーション付き一覧ページの自動生成 | N 件ごと分割の `PaginationBuilder` | `ASG.Core.ts` |
| B-37 | ビルドのドライラン（プレビュー） | ファイル書き込みなしで差分結果のみ返す `dryRun` オプション | `ASG.Core.ts` `ASG.Interface.ts` |

#### AIS（インフラサービス）— 7 件

| # | 機能名 | 概要 | 対象ファイル |
|---|--------|------|-------------|
| B-38 | DiagnosticsManager のログ永続化 | メモリのみ → `StorageService` 経由でファイルに永続化 | `AIS.Utilities.ts` |
| B-39 | ApiCache に LRU eviction | 最大エントリ数設定と古いエントリの自動削除 | `AIS.Utilities.ts` |
| B-40 | I18n に複数ロケール同時ロードと動的切り替え | プリロードして即座にロケール切り替え | `AIS.Core.ts` |
| B-41 | I18n に複数形・性別対応（ICU MessageFormat） | `{count, plural, ...}` 形式のサポート | `AIS.Core.ts` |
| B-42 | 設定のバリデーション付きスキーマ定義 | URL 形式、ポート範囲、パス存在確認等のドメイン固有バリデーション | `AIS.Core.ts` |
| B-43 | Feature Flag（機能フラグ）管理 | `features.json` による ON/OFF 管理、管理画面から動的切り替え | `AIS.Core.ts` `AIS.Interface.ts` |
| B-44 | 設定の変更履歴と差分表示 | 旧値の自動記録、タイムライン表示、任意時点へのロールバック | `AIS.Core.ts` |

#### ASS（サーバサイド）— 9 件

| # | 機能名 | 概要 | 対象ファイル |
|---|--------|------|-------------|
| B-45 | FileService に MIME タイプ検証 | magic bytes による実際のファイル内容検証 | `ASS.Core.php` |
| B-46 | StorageService にファイルロック付きトランザクション | アトミックな複数ファイル同時書き込み | `ASS.Core.php` |
| B-47 | GitService に commit 機能 | ステージングとコミットメッセージ付きコミット | `ASS.Core.php` `ASS.Interface.php` |
| B-48 | StorageService.write のエラーハンドリング | `file_put_contents` 失敗時の例外送出 | `ASS.Core.php` |
| B-49 | セッション固定攻撃対策（セッション再生成） | ログイン成功時に `SessionManager.regenerate(oldId)` | `ASS.Utilities.php` |
| B-50 | StorageService のファイル変更監視（inotify） | データディレクトリの変更検知、SSE 経由でクライアント通知 | `ASS.Core.php` |
| B-51 | 画像のオンザフライリサイズ | パラメータで動的リサイズ・キャッシュする画像 API | `ASS.Core.php` |
| B-52 | ログファイルのローテーションと圧縮 | 日付ローテーション、gzip 圧縮、N 日自動削除 | `ASS.Utilities.php` |
| B-53 | IP ホワイトリスト / ブラックリストミドルウェア | CIDR 対応の IP フィルタリング | `ASS.Core.php` |

#### AFE（ミドルウェア拡張）— 2 件

| # | 機能名 | 概要 | 対象ファイル |
|---|--------|------|-------------|
| B-54 | レスポンス圧縮ミドルウェア（gzip / Brotli） | `Accept-Encoding` 対応の `CompressionMiddleware` | `AFE.Core.ts` |
| B-55 | リクエストバリデーションミドルウェア | スキーマ定義による自動 422 拒否 | `AFE.Core.ts` |

#### AEB（エディタ）— 3 件

| # | 機能名 | 概要 | 対象ファイル |
|---|--------|------|-------------|
| B-56 | Editor にブロック並び替え機能 | `moveBlock(id, direction)` + ドラッグ&ドロップ UI | `AEB.Core.ts` |
| B-57 | Editor にブロック単位の削除・挿入機能 | `removeBlock(id)` / `insertBlock(type, data, afterId?)` | `AEB.Core.ts` |
| B-58 | ブロック変換（Block Conversion） | 既存ブロックのデータ保持まま別型に変換する `convertBlock(id, newType)` | `AEB.Core.ts` |

### 15.3 新機能（16 件）

| # | 機能名 | 概要 | 関連 FW |
|---|--------|------|--------|
| C-1 | WebSocket リアルタイム通信層 | SSE に加え双方向通信。同時編集通知、ビルド進捗プッシュ、ライブプレビュー | ACS, ASS |
| C-2 | タスクキュー / バックグラウンドジョブ | 重い処理の非同期実行。登録・実行・進捗取得・リトライ | AFE, ASS |
| C-3 | メディアライブラリ | アップロード画像・ファイルの一覧・検索・タグ付け・使用箇所追跡・未使用検出 | ACE, ASS |
| C-4 | コンテンツのスケジュール公開 | `publishAt`/`unpublishAt` で自動公開・非公開。ASG ビルドトリガー連携 | ACE, ASG |
| C-5 | コンテンツのインポート/エクスポート | WordPress XML, Hugo Markdown, JSON からの一括インポートと ZIP エクスポート | ACE, ASS |
| C-6 | リレーション（コンテンツ間の参照） | `belongsTo`/`hasMany` のリレーション定義、関連コンテンツ取得・逆引き | ACE |
| C-7 | インラインツールバー | テキスト選択時のフローティングツールバー（太字、斜体、リンク、コード） | AEB |
| C-8 | コラボレーティブ編集（OT/CRDT ベース） | WebSocket + 操作変換による複数ユーザー同時編集 | AEB, ACS |
| C-9 | 全文検索インデックスの自動生成 | ビルド時に bigram 解析で `search-index.json` 自動生成 | ASG |
| C-10 | サーバサイド検索 API | ASS でインデックスクエリ、ページネーション付き結果返却 | ASS, ACS |
| C-11 | アクセス解析ダッシュボード | ファーストパーティの軽量ログ収集、PV・リファラー・人気ページのグラフ表示 | ASS |
| C-12 | 二要素認証（TOTP） | Google Authenticator 互換、QR コード生成、リカバリーコード | ASS |
| C-13 | API キー認証 | 長寿命 API キーによる認証。生成・失効・スコープ制限（read-only/write） | ASS |
| C-14 | 監査ログ | 全管理操作の時系列記録。誰が・いつ・何を。30 日自動ローテーション | ASS, AIS |
| C-15 | プレビュー環境の自動生成 | Git ブランチごとにプレビュー URL 発行。一時ディレクトリにビルド → 配信 | ASG, ASS |
| C-16 | 定期バックアップのスケジューラ | 日次/週次の自動 ZIP 保存。世代管理（最新 N 件保持） | ASS |

---

*本書は Adlaire Group に帰属し、同グループの承認なく変更または再配布することを禁ずる。*
