# AdlairePlatform フレームワークルールブック v3.0

Ver.0.1-4 | 最終更新: 2026-03-18

---

## §1 目的

本書は、AdlairePlatform におけるフレームワークの設計・開発・運用に適用する統一規則を定める。すべての設計・実装は、本ルールブックに厳格に準拠しなければならない。

---

## §2 エンジン駆動モデル

### §2.1 基本原則

エンジン駆動モデルは、AdlairePlatform が採用する独自のフレームワーク設計パターンである。本モデルでは、各フレームワークを「エンジン」として抽象化し、その構成と依存関係を統一的に管理する。

#### 構成規則

**1 エンジン 5 ファイル原則**

1 つのフレームワークは、1 つのエンジンとして構成しなければならない。各エンジンは、必須の Core を含む最大 5 つのコンポーネントで構成される。Core のみが必須であり、それ以外のコンポーネントの有無と役割は各エンジンが自由に決定できる。

**モジュラーコンポーネント**

各ファイルは、エンジンの独立した構成要素として機能する。1 つのコンポーネント内には複数のクラスを配置してよい。Core 以外のコンポーネントは、固定の役割名や責務に縛られず、エンジン全体の設計意図に沿う範囲で自由に構成できる。

**自己完結**

各エンジンは、すべてのコンポーネントを含めて自己完結しなければならない。他のエンジンの存在を前提とした設計は禁止する。

#### 依存規則

**エンジン内協調**

同一エンジン内のコンポーネント間で参照が必要な場合は、必ず Core を介さなければならない。Core 以外のコンポーネント同士が直接参照することは禁止する。

**外部依存ゼロ**

サードパーティライブラリおよび外部パッケージへの依存は禁止する。使用できるのは言語標準ライブラリのみとする。

**フレームワーク間依存ゼロ**

あるフレームワークが別のフレームワークの実装を直接 `import` することは、ACS を含めて一切禁止する。各フレームワークがサーバと通信する際は、必ず `globalThis.__acs` を経由しなければならない（§3 参照）。

**DI コンテナパターンの禁止**

DI コンテナパターン（サービスロケータを含む）の使用を禁止する。`Container` クラスは廃止とし、すべてのコードから削除しなければならない。サービス間の依存解決は、ACS については `globalThis.__acs`（§3 参照）、それ以外については `ApplicationFacade` のプロパティ直接参照により行う。

**共有型ファイルの作成禁止**

複数のフレームワークから参照される共有型ファイル（`Framework/types.ts` 等）を新規に作成してはならない。各フレームワークが必要とする型は、そのフレームワーク自身が定義するか、もしくは ACS が `ACS.d.ts` で公開している型を `import type` により参照しなければならない。

#### ACS アクセス規則

ACS へのアクセスに関する詳細な規則は §3.4 に定める。

### §2.2 構成の例外規定

§2.1 の「1 エンジン 5 ファイル原則」に対する例外を以下のとおり定める。

| 条件 | 許容内容 | 承認 |
|------|---------|------|
| 1 ファイル | 単一ファイルで完結するフレームワークを許容する | 不要 |
| 2〜5 ファイル | 明確な責務分離の根拠がある場合に許容する | 不要 |
| 6〜7 ファイル | 明確な責務分離の根拠がある場合に限り許容する | Adlaire Group の承認を要する |
| 8 ファイル以上 | 原則として禁止する。新規フレームワークとして分割しなければならない | Adlaire Group の承認を要し、倉田和宏が最終決定する |

---

## §3 ACS グローバルシングルトン仕様

### §3.1 概要

ACS（Adlaire Client Services）は、サーバ（ASS）との通信を一元的に担うクライアントサイドのエンジンである。すべてのフレームワークが個別にサーバ通信を実装すると、通信ロジックが分散し保守性が低下する。この問題を防ぐため、ACS は通信ロジックを単一のエンジンに集約し、各フレームワークに対して `globalThis.__acs` という統一的なアクセス手段を提供する。各フレームワークは ACS の実装ファイルを直接 `import` することなく、この `globalThis.__acs` を通じて ACS の機能を利用する。

シングルトンの保証には **モジュールキャッシュ方式** を採用する。ES モジュールのランタイムキャッシュ機構により、ACS のエントリモジュールは初回評価時に一度だけインスタンスを生成し、以降のすべての参照で同一のインスタンスを返す。`globalThis.__acs` への代入はこのモジュール評価の副作用として行われるため、二重初期化が構造的に排除される（§3.5 参照）。

### §3.2 責務一覧

ACS が担う責務は以下の 16 項目である。各責務をカテゴリごとに分類して示す。

#### 通信基盤

| # | 責務 | 概要 |
|---|------|------|
| 1 | **サーバ通信** | ASS との HTTP API 通信（GET / POST / PUT / DELETE）を実行する |
| 2 | **インターセプター** | リクエストおよびレスポンスをパイプライン処理により加工する |
| 3 | **リクエスト制御** | `AbortController` によるリクエストのキャンセル、およびタイムアウトの管理を行う |
| 4 | **障害復旧** | 指数バックオフによるリトライ、およびエラーハンドリングを実行する |

#### 認証・セキュリティ

| # | 責務 | 概要 |
|---|------|------|
| 5 | **認証** | ログイン、ログアウト、セッション管理、および自動認証の各処理を実行する |
| 6 | **セキュリティ** | CSRF トークンの発行・検証、認証ヘッダーの付与、および資格情報の管理を行う |

#### データ操作

| # | 責務 | 概要 |
|---|------|------|
| 7 | **ストレージ** | JSON ファイルの読み書き、削除、存在確認、および一覧取得を実行する |
| 8 | **ファイル操作** | ファイルのアップロード、ダウンロード、削除、および画像の最適化を実行する |

#### コンテンツ配信

| # | 責務 | 概要 |
|---|------|------|
| 9 | **静的コンテンツ配信** | 静的ファイル（HTML / CSS / JS / 画像）を配信する |
| 10 | **動的データ取得** | API レスポンスから動的データを取得し、必要に応じて変換する |

#### アーキテクチャ

| # | 責務 | 概要 |
|---|------|------|
| 11 | **ブートストラップ** | アプリケーション起動時に初期化処理を実行し、`globalThis.__acs` としてインスタンスを公開する |
| 12 | **グローバルシングルトン** | ES モジュールキャッシュ方式により単一インスタンスの生成を保証し、`globalThis.__acs` としてアプリケーション全体に公開する（§3.5 参照） |
| 13 | **フレームワーク間仲介** | すべてのフレームワークが行うサーバ通信を一元的に仲介する |
| 14 | **ルーティング・定数管理** | API エンドポイントの定義、およびストレージディレクトリ定数の管理を担う |
| 15 | **型定義公開** | `ACS.d.ts` を通じて公開型定義を提供し、各フレームワークからの `import type` による参照を許可する |
| 16 | **診断** | ヘルスチェックの実行、接続状態の監視、およびエラーの分類を行う |

### §3.3 公開インターフェース

ACS はブートストラップ時に、グローバル変数 `globalThis.__acs` を公開する。このグローバル変数は `auth`・`storage`・`files`・`http` の 4 つの名前空間で構成されており、各フレームワークはこれらの名前空間を通じて ACS の機能にアクセスする。具体的な構造を以下に示す。

```typescript
globalThis.__acs = {
  auth,     // 認証（login / logout / session / verify / csrf）
  storage,  // ストレージ（read / write / delete / exists / list）
  files,    // ファイル操作（upload / download / delete / info）
  http,     // HTTP トランスポート（get / post / put / delete）
};
```

### §3.4 利用ルール

ACS を利用する際に各フレームワークが遵守すべきルールを以下に定める。

| ルール | 内容 |
|--------|------|
| **実装の import 禁止** | 各フレームワークは、ACS の実装ファイルに対して通常の `import` 文を使用してはならない |
| **globalThis 経由** | 各フレームワークがサーバと通信する際は、すべてグローバル変数 `globalThis.__acs` を経由しなければならない |
| **Core 限定参照** | グローバル変数 `globalThis.__acs` を参照してよいのは、各エンジンの Core コンポーネントのみとする。Core 以外のコンポーネントが ACS の機能を必要とする場合は、Core を介して間接的に利用しなければならない |
| **型参照のみ許可** | ACS が外部に公開する型定義は、すべて `Framework/ACS/ACS.d.ts` に集約して配置する。各フレームワークが ACS の型情報を参照する際は、`import type` 構文のみを使用しなければならない。実行時に値を伴う通常の `import` 文は禁止する |
| **直接 fetch 禁止** | 各フレームワークは、ブラウザ標準の `fetch()` 関数を直接呼び出してはならない。HTTP 通信が必要な場合は、すべて ACS が提供するグローバル変数 `globalThis.__acs` の `http` 名前空間を経由しなければならない |
| **初期化順序** | ACS がグローバル変数 `globalThis.__acs` を公開する初期化処理は、ブートストラップ処理において他のすべてのフレームワークよりも先に実行しなければならない |

```typescript
// 許可: 型のみの参照
import type { AuthModule, StorageModule } from "../ACS/ACS.d.ts";

// 禁止: 実装の import
import { AuthService } from "../ACS/ACS.Core.ts";
```

### §3.5 モジュールキャッシュ方式

#### 概要

モジュールキャッシュ方式とは、ES モジュールのランタイムキャッシュ機構を利用してシングルトンを実現する設計パターンである。Deno（および標準的な ES モジュールランタイム）は、同一モジュール指定子に対する `import` を初回評価時に一度だけ実行し、その結果をモジュールキャッシュに保持する。以降の `import` は、キャッシュされたモジュールインスタンスを返す。

ACS はこの仕組みを活用し、エントリモジュールのトップレベルでインスタンスを生成する。これにより、明示的なシングルトンガード（`if (!instance)` 等）を実装することなく、ランタイムレベルで単一インスタンスが保証される。

#### 実装パターン

ACS のエントリモジュールは、以下のパターンに従って実装しなければならない。

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

#### ルール

| ルール | 内容 |
|--------|------|
| **トップレベル生成** | ACS のインスタンスは、エントリモジュールのトップレベル（関数やクラスの外側）で生成しなければならない。遅延初期化（lazy initialization）は禁止する |
| **凍結** | `globalThis.__acs` に代入するオブジェクトは `Object.freeze()` で凍結し、外部からのプロパティの変更・追加・削除を防止しなければならない |
| **再代入禁止** | `globalThis.__acs` への代入は、ACS のエントリモジュール評価時の 1 回のみとする。他のいかなるモジュールも `globalThis.__acs` を再代入してはならない |
| **動的 import の禁止** | ACS のエントリモジュールを `import()` 式（動的 import）で読み込むことは禁止する。動的 import はモジュール指定子の解決タイミングがランタイム依存となり、キャッシュの一貫性を損なう可能性がある |

---

## §4 フレームワーク一覧

### §4.1 一覧表

AdlairePlatform を構成するフレームワークは以下のとおりである。

| 接頭辞 | 正式名称 | 用途 | 言語 | ファイル数 |
|--------|---------|------|------|-----------|
| **AFE** | Adlaire Foundation Engine | Router、Request、Response、EventBus、MiddlewarePipeline 等の基盤 | TypeScript | 5 |
| **ACS** | Adlaire Client Services | サーバとの通信を一元的に担うクライアントエンジン（§3 参照） | TypeScript | 5 |
| **ACE** | Adlaire Content Engine | コレクション、コンテンツ、メタデータの管理、および管理画面 UI | TypeScript | 5 + クライアントモジュール |
| **AIS** | Adlaire Infrastructure Services | アプリケーションコンテキスト、i18n、API キャッシュ、診断、およびリクエストロギング | TypeScript | 5 |
| **ASG** | Adlaire Static Generator | Markdown パース、テンプレートレンダリング、および静的サイトビルド | TypeScript | 5 |
| **ASS** | Adlaire Server System | 認証、セキュリティ、ストレージ、ファイル操作、および Git 等のサーバサイドサービス | PHP | 5 |
| **ADS** | Adlaire Design System | ベーススタイル、コンポーネントスタイル、エディタスタイル、および管理画面スタイル | CSS | 3 + アセット |
| **AEB** | Adlaire Editor & Blocks | ブロックエディタおよび WYSIWYG | TypeScript | 3 + クライアントモジュール |

### §4.2 各フレームワークの構成

#### AFE — Adlaire Foundation Engine

APF（Adlaire Platform Foundation）を廃止し、コア基盤を継承する新設フレームワークである。

| ファイル | 概要 |
|---------|------|
| `AFE.Core.ts` | Router（ルーティングおよびパスパラメータ解決）、Request（HTTP リクエストの抽象化および Deno 標準 Request からの変換）、Response（JSON / HTML / テキスト / リダイレクトの各レスポンス生成）、EventBus（優先度付きイベントの発行・購読）、および MiddlewarePipeline（ミドルウェアチェーンの実行）を定義する |
| `AFE.Interface.ts` | RouterInterface、RequestInterface、ResponseInterface、MiddlewareInterface、EventBusInterface、ValidatorInterface、ConfigInterface、FileSystemInterface、および JsonStorageInterface を定義する |
| `AFE.Class.ts` | HttpMethod（GET / POST / PUT / PATCH / DELETE / OPTIONS / HEAD）、LogLevel（DEBUG / INFO / WARN / ERROR / FATAL）の列挙定数、および FrameworkError、ValidationError、NotFoundError のエラークラスを定義する |
| `AFE.Api.ts` | システムルート（ヘルスチェック、バージョン情報等）を登録し、jsonError、jsonSuccess、jsonValidationError のレスポンスヘルパーを提供する |
| `AFE.Utilities.ts` | Str（文字列操作）、Arr（配列操作）、Config（設定値管理）、Validator（入力検証）、Security（セキュリティユーティリティ）、FileSystem（ファイルシステム操作）、および JsonStorage（JSON ファイルの永続化）を提供する |

#### ACS — Adlaire Client Services

ACS の詳細仕様は §3 を参照すること。

| ファイル | 概要 |
|---------|------|
| `ACS.Core.ts` | ClientFactory（クライアントインスタンスの生成）、HttpTransport（HTTP 通信の実行）、StorageService（JSON ストレージの CRUD 操作）、FileService（ファイルのアップロード・ダウンロード・削除・情報取得）、EventSourceService（Server-Sent Events の受信）、および withRetry（指数バックオフによるリトライ）を実装する |
| `ACS.Interface.ts` | ClientFactoryInterface、ClientConfig、HttpModuleInterface、StorageModuleInterface、FileModuleInterface、EventSourceInterface、および RetryConfig を定義する |
| `ACS.Class.ts` | ConnectionState（CONNECTED / DISCONNECTED / RECONNECTING）の列挙定数、STORAGE_DIRS（ストレージディレクトリ定数）、API_ENDPOINTS（API エンドポイント定数）、および NetworkError、TimeoutError、ServerError のエラークラスを定義する |
| `ACS.Api.ts` | createClient（本番クライアントの生成）および createMockClient（テスト用モッククライアントの生成）を提供する |
| `ACS.Utilities.ts` | joinUrl（URL 結合）、buildQueryString（クエリ文字列構築）、bearerHeader（Bearer トークンヘッダー生成）、csrfHeader（CSRF ヘッダー生成）、jsonHeaders（JSON ヘッダー生成）、extractData（レスポンスデータ抽出）、objectToFormData（FormData 変換）、および calculateBackoff（バックオフ時間計算）を提供する |
| `ClientEngine/` | クライアントモジュール（`acs-api-client.ts`：API クライアントラッパー、`acs-api-keys.ts`：API キー管理 UI）を格納する |

#### ACE — Adlaire Content Engine

| ファイル | 概要 |
|---------|------|
| `ACE.Core.ts` | CollectionManager（コレクションの作成・更新・削除・一覧取得）、ContentManager（コンテンツアイテムの CRUD 操作およびスラグベースのルックアップ）、MetaManager（メタデータの管理および検索インデックスの構築）、および ContentValidator（コンテンツの入力検証）を実装する |
| `ACE.Interface.ts` | CollectionManagerInterface、ContentManagerInterface、ItemSaveData、ListItemsOptions、MetaManagerInterface、ContentValidatorInterface、CollectionServiceInterface、ApiRouterInterface、WebhookServiceInterface、および RevisionServiceInterface を定義する |
| `ACE.Class.ts` | CollectionError、ContentNotFoundError、DuplicateSlugError のエラークラス、および SLUG_PATTERN（スラグの正規表現パターン）を定義する |
| `ACE.Api.ts` | コレクション CRUD、コンテンツアイテム CRUD、およびメタデータ取得のルートを登録する |
| `ACE.Utilities.ts` | WebhookService（コンテンツ変更時の Webhook 配信）、RevisionService（コンテンツのリビジョン管理）、および ApiRouter（ACE 専用の API ルーティング）を提供する |
| `ClientEngine/` | クライアントモジュール（`ace-dashboard.ts`：ダッシュボード UI、`ace-collection-manager.ts`：コレクション管理 UI、`ace-edit-inplace.ts`：インプレース編集、`ace-search.ts`：コンテンツ検索、`ace-webhook-manager.ts`：Webhook 管理 UI）を格納する |

#### AIS — Adlaire Infrastructure Services

AP（Adlaire Platform）の RequestLoggingMiddleware を統合する。

| ファイル | 概要 |
|---------|------|
| `AIS.Core.ts` | AppContext（アプリケーション設定の読み込みおよびパス管理）、I18n（多言語対応のロケール管理および翻訳ファイルの読み込み）、および RequestLoggingMiddleware（リクエスト / レスポンスのログ記録）を実装する |
| `AIS.Interface.ts` | AppContextInterface、AppContextPaths、HostInfo、I18nInterface、DiagnosticsManagerInterface、DiagnosticsConfig、ApiCacheInterface、GitServiceInterface、GitServiceConfig、UpdateServiceInterface、EnvironmentCheck、および UpdateApplyResult を定義する |
| `AIS.Class.ts` | InfrastructureError および ConfigValidationError のエラークラスを定義する |
| `AIS.Api.ts` | インフラルート（診断情報取得、キャッシュ制御、i18n リソース取得等）を登録する |
| `AIS.Utilities.ts` | DiagnosticsManager（診断ログの記録、環境チェック、パフォーマンス計測）、ApiCache（API レスポンスのキャッシュおよび無効化）、GitService（Git リポジトリ操作）、および UpdateService（アプリケーションの更新チェックおよび適用）を提供する |
| `ClientEngine/` | クライアントモジュール（`ais-diagnostics.ts`：診断 UI、`ais-updater.ts`：アップデート管理 UI、`ais-i18n.ts`：クライアントサイド i18n）を格納する |

#### ASG — Adlaire Static Generator

| ファイル | 概要 |
|---------|------|
| `ASG.Core.ts` | Generator（静的サイト生成の統括）、HybridResolver（動的 / 静的コンテンツの解決）、BuildCache（ビルドキャッシュの管理）、SiteRouter（静的サイトのルーティング）、Deployer（デプロイ処理）、HtmlMinifier（HTML の最小化）、および CssMinifier（CSS の最小化）を実装する |
| `ASG.Interface.ts` | GeneratorInterface、GeneratorConfig、BuilderInterface、TemplateRendererInterface、MarkdownServiceInterface、StaticServiceInterface、BuildCacheInterface、DiffBuilderInterface、SiteRouterInterface、StaticFileSystemInterface、DeployerInterface、ImageProcessorInterface、および ThemeManagerInterface を定義する |
| `ASG.Class.ts` | BuildStatus（PENDING / BUILDING / SUCCESS / FAILED）、UrlStyle（PRETTY / RAW）の列挙定数、BuildError、TemplateError、ThemeError のエラークラス、および PARTIAL_MAX_DEPTH（パーシャルの最大ネスト深度）を定義する |
| `ASG.Api.ts` | ビルド実行、ビルドステータス取得、プレビュー生成等のジェネレータルートを登録する |
| `ASG.Utilities.ts` | TemplateRenderer（テンプレートのレンダリングおよびパーシャル展開）、MarkdownService（Markdown から HTML への変換および Front Matter の解析）、ThemeManager（テーマの読み込みおよび切り替え）、および Builder（差分ビルドの実行）を提供する |
| `ClientEngine/` | クライアントモジュール（`asg-static-builder.ts`：静的ビルド UI）を格納する |

#### ASS — Adlaire Server System

AP（Adlaire Platform）の AuthMiddleware および SecurityHeadersMiddleware を統合し、認証・セキュリティのすべてを担う。

| ファイル | 概要 |
|---------|------|
| `ASS.Core.php` | AuthService（ログイン・ログアウト・セッション管理・トークン検証）、UserManager（ユーザーの CRUD 操作）、SessionAdmin（セッション管理の管理者操作）、StorageService（JSON ファイルの読み書き・削除・存在確認・一覧取得）、FileService（ファイルのアップロード・ダウンロード・削除・画像最適化）、GitService（Git リポジトリのコミット・プッシュ・プル・ログ取得）、および ServerDiagnostics（サーバサイド診断）を実装する |
| `ASS.Interface.php` | AuthServiceInterface、UserManagerInterface、SessionAdminInterface、StorageServiceInterface、FileServiceInterface、GitServiceInterface、RequestInterface、および ResponseInterface を定義する |
| `ASS.Class.php` | ApiResponse（統一レスポンス形式）、Request（HTTP リクエストの抽象化）、および Response（HTTP レスポンスの生成）を定義する |
| `ASS.Api.php` | 認証（ログイン / ログアウト / セッション検証）、ストレージ CRUD、ファイル操作、Git 操作、API キー管理、および管理者操作の各エンドポイントを登録する |
| `ASS.Utilities.php` | PathSecurity（パストラバーサル防止）、SessionManager（PHP セッション管理）、CsrfManager（CSRF トークンの発行・検証）、Token（トークンの生成・検証）、MimeType（MIME タイプ判定）、GitCommand（Git コマンドの安全な実行）、および AdminTemplate（管理画面テンプレートのレンダリング）を提供する |

#### ADS — Adlaire Design System

ACE（Adlaire Content Engine）の AdminEngine/ から管理画面スタイルを統合する。

| ファイル | 概要 |
|---------|------|
| `ADS.Base.css` | CSS リセット、CSS カスタムプロパティ（カラー・タイポグラフィ・スペーシング）、およびベースタイポグラフィを定義する |
| `ADS.Components.css` | ボタン、フォーム、カード、テーブル、ナビゲーション、モーダル等のコンポーネント固有のスタイルを定義する |
| `ADS.Editor.css` | エディタ、ブロック、ツールバー、および管理画面（ダッシュボード、サイドバー、ヘッダー）向けのスタイルを定義する |
| `AdminEngine/` | 管理画面固有の CSS アセット（`dashboard.css`：ダッシュボードレイアウト）を格納する |

#### AEB — Adlaire Editor & Blocks

AP（Adlaire Platform）の JsEngine/ から WYSIWYG エディタ関連を統合する。

| ファイル | 概要 |
|---------|------|
| `AEB.Core.ts` | EventBus（エディタ内イベント管理）、BlockRegistry（ブロックタイプの登録・取得）、StateManager（エディタ状態の管理）、HistoryManager（Undo / Redo 履歴の管理）、および Editor（ブロックエディタの初期化・レンダリング・保存）を実装する |
| `AEB.Blocks.ts` | BaseBlock（ブロック基底クラス）、ParagraphBlock、HeadingBlock、ListBlock、QuoteBlock、CodeBlock、ImageBlock、TableBlock、ChecklistBlock、および DelimiterBlock の各ブロック実装を提供する |
| `AEB.Utils.ts` | sanitizer（HTML サニタイズ）、dom（DOM 操作ユーティリティ）、selection（選択範囲の保存・復元・判定）、および keyboard（キーボードショートカットの管理）を提供する |
| `ClientEngine/` | クライアントモジュール（`aeb-wysiwyg.ts`：WYSIWYG エディタのブラウザ統合、`aeb-autosize.ts`：テキストエリアの自動サイズ調整）を格納する |

### §4.3 開発言語とランタイム

AdlairePlatform で使用する言語およびランタイムは以下のとおりである。

| 言語 / ランタイム | バージョン | 用途 | 対象フレームワーク |
|------------------|-----------|------|-------------------|
| **Deno** | 2.x 以上 | TypeScript ランタイムおよびツールチェーン（型チェック、リント、フォーマット、テスト） | AFE, ACS, ACE, AIS, ASG, AEB |
| **TypeScript** | Deno 内蔵（Strict モード） | サーバサイドおよびクライアントサイドのフレームワーク実装 | AFE, ACS, ACE, AIS, ASG, AEB |
| **PHP** | 8.4 以上 | サーバサイドのビジネスロジック実装 | ASS |
| **CSS** | CSS3 | デザインシステムのスタイル定義 | ADS |

> **バージョン情報の取得元**
>
> | 言語 / ランタイム | 取得元 |
> |------------------|--------|
> | Deno | `README.md` の Prerequisites（`Deno 2.x or later`）および `main.ts` 内の `Deno.version.deno` による動的取得 |
> | TypeScript | `deno.json` の `compilerOptions`（`strict: true`、`noImplicitAny: true`） |
> | PHP | 実行環境（`PHP 8.4.18`）。すべてのファイルで `declare(strict_types=1)` を使用し、`match` 式（PHP 8.0 以上の機能）を使用する |

#### ランタイム規定

| ルール | 内容 |
|--------|------|
| **Deno 必須** | TypeScript フレームワークの実行、型チェック、リント、フォーマット、およびテストはすべて Deno で実施しなければならない |
| **Strict モード必須** | TypeScript のコンパイラオプションにおいて、厳密な型チェックを行う `strict: true` と、暗黙的な `any` 型を禁止する `noImplicitAny: true` の両方を有効にしなければならない |
| **ブラウザ向け設定** | クライアントサイドで動作するコード（各フレームワークの ClientEngine/ および AEB）は、ブラウザ向けコンパイラ設定ファイル `browser.deno.json` に従わなければならない。この設定では、DOM 型定義として `dom`・`dom.iterable`・`dom.asynciterable`・`esnext` の 4 つのライブラリを使用する |
| **Node.js 禁止** | Node.js ランタイムおよび npm パッケージの使用を禁止する（§2.1「外部依存ゼロ」に準ずる） |
| **PHP バージョン** | ASS は PHP 8.4 以上の環境で動作しなければならない。また、厳密な型チェックを有効にするため、すべての PHP ファイルの先頭に `declare(strict_types=1)` 宣言を記述しなければならない |

---

## §5 エラーハンドリング規定

### §5.1 エラークラスの定義

各エンジンが独自のエラークラスを必要とする場合は、そのエンジンの Class コンポーネントに定義しなければならない。他のエンジンのエラークラスを直接参照することは禁止する。

### §5.2 エラーレスポンス形式

API エンドポイントが返すエラーレスポンスは、以下の形式に統一しなければならない。

| フィールド | 型 | 必須 | 内容 |
|-----------|-----|------|------|
| `ok` | `boolean` | はい | 常に `false` |
| `error` | `string` | はい | エラーメッセージ |
| `errors` | `Record<string, string[]>` | いいえ | フィールド単位のバリデーションエラー |

#### HTTP ステータスコードの使い分け

| ステータスコード | 用途 |
|-----------------|------|
| `400 Bad Request` | リクエストパラメータの不正、バリデーションエラー |
| `401 Unauthorized` | 認証が必要、またはトークンが無効 |
| `403 Forbidden` | 認証済みだがアクセス権限がない |
| `404 Not Found` | リソースが存在しない |
| `500 Internal Server Error` | サーバ内部エラー |

### §5.3 例外の方針

| ルール | 内容 |
|--------|------|
| **結果型の必須化** | エンジン外部に公開する関数は、例外をスローしてはならない。成功と失敗を表す結果型で返却しなければならない |
| **エンジン内例外** | エンジン内部の処理においては例外のスローを許容する。ただし、公開境界で必ず捕捉しなければならない |
| **ACS 通信エラー** | ACS が通信エラーを検出した場合、ACS 自身がリトライ処理を実行する（§3.2 責務 #4「障害復旧」）。呼び出し側のエンジンが独自にリトライを実装することは禁止する |

---

## §6 ファイル構成規則

### §6.1 ディレクトリ構成

各フレームワークは、プロジェクトルートの `Framework/` ディレクトリ配下に、自身の接頭辞と同名のディレクトリを持たなければならない。

```
Framework/
├── {PREFIX}/                 # 各フレームワークディレクトリ
│   ├── {PREFIX}.Core.ts      # Core コンポーネント（必須）
│   ├── {PREFIX}.Interface.ts # その他コンポーネント（任意）
│   ├── {PREFIX}.Class.ts
│   ├── {PREFIX}.Api.ts
│   ├── {PREFIX}.Utilities.ts
│   └── {SubDir}/             # サブディレクトリ（任意）
├── mod.ts                    # バレルエクスポート（エントリポイント）
└── browser.deno.json         # コンパイラ設定
```

#### ディレクトリ配置ルール

| ルール | 内容 |
|--------|------|
| **1 フレームワーク 1 ディレクトリ** | 各フレームワークは、`Framework/` 配下にフレームワークごとの専用ディレクトリ `Framework/{PREFIX}/` を作成し、そこにすべてのファイルを配置しなければならない |
| **接頭辞一致** | フレームワークのディレクトリ名は、そのフレームワークの接頭辞と一致させなければならない。たとえば、ACS のディレクトリは `Framework/ACS/` となる |
| **フラット原則** | コンポーネントファイルは、フレームワークディレクトリの直下に配置しなければならない。サブディレクトリへのネストは禁止する |
| **サブディレクトリ** | アセットやクライアントモジュール等、コンポーネントに該当しないファイルについては、フレームワークディレクトリ内にサブディレクトリを作成して配置してよい。たとえば `ACE/ClientEngine/` や `ADS/AdminEngine/` がこれに該当する |

### §6.2 命名規則

#### コンポーネントファイル名

コンポーネントファイルは `{PREFIX}.{ComponentName}.{ext}` の形式で命名する。この形式は、接頭辞、コンポーネント名、拡張子の 3 要素で構成される。

接頭辞（PREFIX）には、フレームワークの略称を大文字で記述する。たとえば `AFE`、`ACS`、`ASS` などがこれに該当する。コンポーネント名は PascalCase で記述しなければならない。たとえば `Core`、`Interface`、`Utilities` のように、各単語の先頭を大文字にする。接頭辞とコンポーネント名の間は、ドット（`.`）で区切る。拡張子は言語に応じて決まっており、TypeScript は `.ts`、PHP は `.php`、CSS は `.css` とする。

**例:**

```
ACS.Core.ts         # TypeScript コンポーネント
ASS.Api.php         # PHP コンポーネント
ADS.Components.css  # CSS コンポーネント
```

#### ディレクトリ名

フレームワークディレクトリは、フレームワーク接頭辞と同一の大文字表記で命名する。たとえば `AFE/` や `ACS/` のように、接頭辞をそのままディレクトリ名とする。サブディレクトリは PascalCase で命名しなければならない。たとえば `ClientEngine/` や `AdminEngine/` のように、各単語の先頭を大文字にする。

#### サブディレクトリ内ファイル名

サブディレクトリ内のファイルは、コンポーネントファイルとは異なる命名規則に従う。

クライアントモジュールは kebab-case で命名する。たとえば `acs-api-client.ts` や `aeb-wysiwyg.ts` のように、単語をハイフンで繋げて小文字で記述する。HTML アセットは小文字で命名しなければならない。たとえば `dashboard.html` や `login.html` がこれに該当する。CSS アセットも同様に小文字で命名する。たとえば `dashboard.css` のように記述する。型定義ファイルは kebab-case で命名し、拡張子を `.d.ts` としなければならない。たとえば `browser-types.d.ts` のように記述する。

### §6.3 コンポーネント配置

各フレームワークの具体的なファイル構成は §4.2 に定める。すべてのフレームワークにおいて、Core コンポーネントのみが必須であり、それ以外のコンポーネントは任意である（§2.1「構成規則」参照）。

#### サブディレクトリの配置ルール

| ルール | 内容 |
|--------|------|
| **目的限定** | サブディレクトリは、クライアントモジュールやアセット等のコンポーネント以外のファイルを格納する場合にのみ使用する |
| **5 ファイル上限の対象外** | サブディレクトリ内のファイルは §2.1「1 エンジン 5 ファイル原則」の上限に含めない |
| **命名の独立性** | サブディレクトリ内のファイルは、コンポーネントの命名規則である `{PREFIX}.{Name}.{ext}` 形式に従う必要はなく、各ファイル種別ごとの命名規則に従えばよい |

---

## §7 変更管理

変更履歴は git log で管理する。

### §7.1 ルールのライフサイクル

ルールブックに記載されるルールは、以下のいずれかのステータスを持つ。

| ステータス | 意味 |
|-----------|------|
| **有効** | 現在適用されるルールである。すべての設計・実装が準拠しなければならない |
| **非推奨** | 将来廃止予定のルールである。新規実装での使用を禁止し、既存実装は次回改修時に移行しなければならない |
| **廃止** | 無効化されたルールである。準拠する必要はない |

### §7.2 ルール変更時の義務

ルールの新設・変更・廃止を行う場合、以下の義務を課す。

| 義務 | 内容 |
|------|------|
| **矛盾の排除** | 新設または変更するルールが、既存のルールと矛盾する場合、矛盾するすべての箇所を同時に修正しなければならない |
| **波及確認** | ルールの変更により影響を受ける他の条項・構成図・例示をすべて特定し、整合性を確保しなければならない |
| **ドキュメント同期** | ルールブックの変更に伴い、関連ドキュメント（詳細設計書等）に矛盾が生じる場合、同時に修正しなければならない |

### §7.3 ルール一覧

本ルールブックに定めるすべてのルールを以下に列挙する。各ルールには一意の ID を付与し、ステータスを §7.1 に従い管理する。

#### §2 エンジン駆動モデル

| ID | ルール名 | 条項 | ステータス |
|----|---------|------|-----------|
| R-2.1-1 | 1 エンジン 5 ファイル原則 | §2.1 構成規則 | 有効 |
| R-2.1-2 | モジュラーコンポーネント | §2.1 構成規則 | 有効 |
| R-2.1-3 | 自己完結 | §2.1 構成規則 | 有効 |
| R-2.1-4 | エンジン内協調（Core 経由） | §2.1 依存規則 | 有効 |
| R-2.1-5 | 外部依存ゼロ | §2.1 依存規則 | 有効 |
| R-2.1-6 | フレームワーク間依存ゼロ | §2.1 依存規則 | 有効 |
| R-2.1-7 | 共有型ファイルの作成禁止 | §2.1 依存規則 | 有効 |
| R-2.1-8 | DI コンテナパターンの禁止 | §2.1 依存規則 | 有効 |
| R-2.2-1 | ファイル数の例外規定 | §2.2 | 有効 |

#### §3 ACS グローバルシングルトン仕様

| ID | ルール名 | 条項 | ステータス |
|----|---------|------|-----------|
| R-3.4-1 | 実装の import 禁止 | §3.4 利用ルール | 有効 |
| R-3.4-2 | globalThis 経由 | §3.4 利用ルール | 有効 |
| R-3.4-3 | Core 限定参照 | §3.4 利用ルール | 有効 |
| R-3.4-4 | 型参照のみ許可 | §3.4 利用ルール | 有効 |
| R-3.4-5 | 直接 fetch 禁止 | §3.4 利用ルール | 有効 |
| R-3.4-6 | 初期化順序 | §3.4 利用ルール | 有効 |
| R-3.5-1 | トップレベル生成 | §3.5 モジュールキャッシュ方式 | 有効 |
| R-3.5-2 | 凍結 | §3.5 モジュールキャッシュ方式 | 有効 |
| R-3.5-3 | 再代入禁止 | §3.5 モジュールキャッシュ方式 | 有効 |
| R-3.5-4 | 動的 import の禁止 | §3.5 モジュールキャッシュ方式 | 有効 |

#### §4 フレームワーク一覧

| ID | ルール名 | 条項 | ステータス |
|----|---------|------|-----------|
| R-4.3-1 | Deno 必須 | §4.3 ランタイム規定 | 有効 |
| R-4.3-2 | Strict モード必須 | §4.3 ランタイム規定 | 有効 |
| R-4.3-3 | ブラウザ向け設定 | §4.3 ランタイム規定 | 有効 |
| R-4.3-4 | Node.js 禁止 | §4.3 ランタイム規定 | 有効 |
| R-4.3-5 | PHP バージョン | §4.3 ランタイム規定 | 有効 |

#### §5 エラーハンドリング規定

| ID | ルール名 | 条項 | ステータス |
|----|---------|------|-----------|
| R-5.1-1 | エラークラスは Class コンポーネントに定義 | §5.1 | 有効 |
| R-5.1-2 | 他エンジンのエラークラス直接参照禁止 | §5.1 | 有効 |
| R-5.2-1 | エラーレスポンス形式の統一 | §5.2 | 有効 |
| R-5.3-1 | 結果型の必須化 | §5.3 例外の方針 | 有効 |
| R-5.3-2 | エンジン内例外の公開境界捕捉 | §5.3 例外の方針 | 有効 |
| R-5.3-3 | 呼び出し側の独自リトライ禁止 | §5.3 例外の方針 | 有効 |

#### §6 ファイル構成規則

| ID | ルール名 | 条項 | ステータス |
|----|---------|------|-----------|
| R-6.1-1 | 1 フレームワーク 1 ディレクトリ | §6.1 ディレクトリ配置ルール | 有効 |
| R-6.1-2 | 接頭辞一致 | §6.1 ディレクトリ配置ルール | 有効 |
| R-6.1-3 | フラット原則 | §6.1 ディレクトリ配置ルール | 有効 |
| R-6.1-4 | サブディレクトリ許可 | §6.1 ディレクトリ配置ルール | 有効 |
| R-6.2-1 | コンポーネントファイル命名規則 | §6.2 命名規則 | 有効 |
| R-6.2-2 | ディレクトリ命名規則 | §6.2 命名規則 | 有効 |
| R-6.2-3 | サブディレクトリ内ファイル命名規則 | §6.2 命名規則 | 有効 |
| R-6.3-1 | Core のみ必須 | §6.3 コンポーネント配置 | 有効 |
| R-6.3-2 | サブディレクトリ目的限定 | §6.3 サブディレクトリの配置ルール | 有効 |
| R-6.3-3 | 5 ファイル上限の対象外 | §6.3 サブディレクトリの配置ルール | 有効 |
| R-6.3-4 | 命名の独立性 | §6.3 サブディレクトリの配置ルール | 有効 |

#### §7 変更管理

| ID | ルール名 | 条項 | ステータス |
|----|---------|------|-----------|
| R-7.2-1 | 矛盾の排除 | §7.2 ルール変更時の義務 | 有効 |
| R-7.2-2 | 波及確認 | §7.2 ルール変更時の義務 | 有効 |
| R-7.2-3 | ドキュメント同期 | §7.2 ルール変更時の義務 | 有効 |

---

*本ルールブックは Adlaire Group に帰属し、同グループの承認なく変更または再配布することを禁ずる。*
