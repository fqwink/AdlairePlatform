# AdlairePlatform フレームワークルールブック v3.0

Ver.0.1-1 | 最終更新: 2026-03-16

---

## §1 目的

本書は、AdlairePlatform におけるフレームワークの設計・開発・運用に適用する統一規則を定める。

---

## §2 エンジン駆動モデル

### §2.1 基本原則

エンジン駆動モデルは、AdlairePlatform 独自のフレームワーク設計パターンである。

#### 構成規則

- **1 エンジン 5 ファイル原則**
  - 1 つのフレームワークを 1 つのエンジンとして構成する
  - 各エンジンは、必須の Core を含む最大 5 つのコンポーネントで構成する
  - Core のみを必須とし、他のコンポーネントの有無と役割は各エンジンが自由に決定できる
- **モジュラーコンポーネント**
  - 各ファイルは、エンジンを構成する部品として機能する
  - 1 つのコンポーネント内に複数のクラスを配置してよい
  - Core 以外のコンポーネントは、固定の役割名や責務に縛られず、エンジン全体の設計意図に沿う範囲で自由に構成できる
- **自己完結**
  - 各エンジンはすべてのコンポーネントを含めて自己完結しなければならない。他エンジンの存在を前提としてはならない

#### 依存規則

- **エンジン内協調** — 同一エンジン内のコンポーネントが相互参照する場合、Core を介さなければならない。Core 以外のコンポーネント同士が直接参照することを禁止する
- **外部依存ゼロ** — サードパーティライブラリおよび外部パッケージへの依存を禁止する。言語標準ライブラリのみを使用できる
- **フレームワーク間依存ゼロ** — ACS を含め、フレームワーク間の直接 `import` を一切禁止する。サーバとの通信には `globalThis.__acs` を経由しなければならない（§3 参照）
- **共有型ファイル** — `Framework/types.ts` 等の共有型ファイルを新規に作成してはならない。各フレームワークは必要な型を自ら定義するか、ACS が公開する `ACS.d.ts` から `import type` で参照しなければならない

#### ACS アクセス規則

- ACS は `globalThis.__acs` にシングルトンとして公開する
- 各フレームワークは ACS を直接 `import` せず、`globalThis.__acs` を通じて機能を利用しなければならない
- `globalThis.__acs` の参照は **Core コンポーネントのみに許可する**。他のコンポーネントからの直接参照を禁止する
- 型の参照には `ACS/ACS.d.ts` からの `import type` のみを許可する

### §2.2 構成の例外規定

| 条件 | 許容内容 | 承認 |
|------|---------|------|
| 1 ファイル | 単一ファイルで完結するフレームワークを許容する | 不要 |
| 2〜5 ファイル | 明確な責務分離の根拠がある場合に許容する | 不要 |
| 6〜7 ファイル | 明確な責務分離の根拠がある場合に許容する | Adlaire Group の承認を要する |
| 8 ファイル以上 | 禁止する。新規フレームワークとして分割しなければならない | Adlaire Group の承認を要し、倉田和宏が最終決定する |

---

## §3 ACS グローバルシングルトン仕様

### §3.1 概要

ACS（Adlaire Client Services）は、サーバ（ASS）との通信を一元的に担うエンジンである。他のフレームワークは ACS を直接 `import` せず、`globalThis.__acs` を通じて機能を利用する。

### §3.2 責務一覧

ACS の責務は以下の 16 項目である。

#### 通信基盤

| # | 責務 | 概要 |
|---|------|------|
| 1 | **サーバ通信** | ASS との HTTP API 通信（GET / POST / PUT / DELETE）を実行する |
| 2 | **インターセプター** | リクエストおよびレスポンスをパイプラインで加工する |
| 3 | **リクエスト制御** | リクエストのキャンセル（AbortController）とタイムアウトを管理する |
| 4 | **障害復旧** | 指数バックオフ付きリトライとエラーハンドリングを実行する |

#### 認証・セキュリティ

| # | 責務 | 概要 |
|---|------|------|
| 5 | **認証** | ログイン、ログアウト、セッション管理、および自動認証を実行する |
| 6 | **セキュリティ** | CSRF トークン、認証ヘッダー、および資格情報を管理する |

#### データ操作

| # | 責務 | 概要 |
|---|------|------|
| 7 | **ストレージ** | JSON ファイルの読み書き、削除、存在確認、および一覧取得を実行する |
| 8 | **ファイル操作** | ファイルのアップロード、ダウンロード、削除、および画像最適化を実行する |

#### コンテンツ配信

| # | 責務 | 概要 |
|---|------|------|
| 9 | **静的コンテンツ配信** | 静的ファイル（HTML / CSS / JS / 画像）を配信する |
| 10 | **動的データ取得** | API レスポンスから動的データを取得し変換する |

#### アーキテクチャ

| # | 責務 | 概要 |
|---|------|------|
| 11 | **ブートストラップ** | bootstrap 時に初期化処理を実行し、`globalThis.__acs` を公開する |
| 12 | **グローバルシングルトン** | `globalThis.__acs` としてシングルトンを提供する |
| 13 | **フレームワーク間仲介** | すべてのフレームワークのサーバ通信を一元的に仲介する |
| 14 | **ルーティング・定数管理** | API エンドポイントの定義とストレージディレクトリ定数の管理を担う |
| 15 | **型定義公開** | `ACS.d.ts` を通じて公開型定義を提供し、`import type` による参照を許可する |
| 16 | **診断** | ヘルスチェック、接続状態の監視、およびエラー分類を実行する |

### §3.3 公開インターフェース

ACS は bootstrap 時に以下の名前空間で `globalThis` 上に公開する。

```typescript
globalThis.__acs = {
  auth,     // 認証（login / logout / session / verify / csrf）
  storage,  // ストレージ（read / write / delete / exists / list）
  files,    // ファイル操作（upload / download / delete / info）
  http,     // HTTP トランスポート（get / post / put / delete）
};
```

### §3.4 型定義

- ACS の公開型定義は `Framework/ACS/ACS.d.ts` に配置する
- 各フレームワークは `import type` のみで `ACS.d.ts` を参照できる
- 実装の `import`（値の import）を禁止する

```typescript
// 許可: 型のみの参照
import type { AuthModule, StorageModule } from "../ACS/ACS.d.ts";

// 禁止: 実装の import
import { AuthService } from "../ACS/ACS.Core.ts";
```

### §3.5 利用ルール

| ルール | 内容 |
|--------|------|
| **import 禁止** | 各フレームワークは ACS の実装ファイルを `import` してはならない |
| **globalThis 経由** | サーバとの通信は `globalThis.__acs` を経由しなければならない |
| **Core 限定参照** | `globalThis.__acs` の参照は各エンジンの Core のみに許可する。他のコンポーネントからの直接参照を禁止する |
| **型参照のみ許可** | `ACS.d.ts` からの `import type` のみを許可する |
| **直接 fetch 禁止** | 各フレームワークは `fetch()` を直接呼び出してはならない |
| **初期化順序** | ACS の初期化（`globalThis.__acs` の公開）は、bootstrap 処理の最初に実行しなければならない |

---

## §4 フレームワーク一覧

### §4.1 一覧表

AdlairePlatform を構成するフレームワークは以下のとおりである。

| 接頭辞 | 正式名称 | 用途 | 言語 | ファイル数 |
|--------|---------|------|------|-----------|
| **APF** | Adlaire Platform Foundation | DI コンテナ、EventBus、Router 等のプラットフォーム基盤 | TypeScript | 5 |
| **ACS** | Adlaire Client Services | サーバとの通信を一元的に担うクライアントエンジン（§3 参照） | TypeScript | 5 |
| **ACE** | Adlaire Content Engine | コレクション、コンテンツ、およびメタデータの管理 | TypeScript | 5 + アセット |
| **AIS** | Adlaire Infrastructure Services | アプリケーションコンテキスト、i18n、API キャッシュ、および診断 | TypeScript | 5 |
| **AP** | Adlaire Platform | 認証、ロギング、セキュリティヘッダー等のミドルウェアとクライアントモジュール | TypeScript | 5 + JsEngine |
| **ASG** | Adlaire Static Generator | Markdown パース、テンプレートレンダリング、および静的サイトビルド | TypeScript | 5 |
| **ASS** | Adlaire Server System | 認証、ストレージ、ファイル操作、および Git 等のサーバサイドサービス | PHP | 5 |
| **ADS** | Adlaire Design System | ベーススタイル、コンポーネントスタイル、およびエディタスタイル | CSS | 3 |
| **AEB** | Adlaire Editor & Blocks | エディタのブロックシステム | TypeScript | 3 |

### §4.2 各フレームワークの構成

#### APF — Adlaire Platform Foundation

| ファイル | 概要 |
|---------|------|
| `APF.Core.ts` | Container、EventBus、Request、Response、および Router を定義する |
| `APF.Interface.ts` | プラットフォーム基盤のインターフェースを定義する |
| `APF.Class.ts` | 定数およびクラスを定義する |
| `APF.Api.ts` | システムルートを登録する |
| `APF.Utilities.ts` | FileSystem ユーティリティを提供する |

#### ACS — Adlaire Client Services

ACS の詳細仕様は §3 を参照すること。

| ファイル | 概要 |
|---------|------|
| `ACS.Core.ts` | HTTP クライアント、認証モジュール、およびストレージモジュールを実装する |
| `ACS.Interface.ts` | 公開インターフェースを定義する |
| `ACS.Class.ts` | API エンドポイント定数およびストレージディレクトリ定数を定義する |
| `ACS.Api.ts` | API ルートを登録する |
| `ACS.Utilities.ts` | ユーティリティ関数を提供する |

#### ACE — Adlaire Content Engine

| ファイル | 概要 |
|---------|------|
| `ACE.Core.ts` | CollectionManager、ContentManager、および MetaManager を実装する |
| `ACE.Interface.ts` | コンテンツ管理のインターフェースを定義する |
| `ACE.Class.ts` | 定数およびクラスを定義する |
| `ACE.Api.ts` | コレクションルートを登録する |
| `ACE.Utilities.ts` | ユーティリティ関数を提供する |
| `AdminEngine/` | 管理画面の HTML および CSS アセットを格納する |

#### AIS — Adlaire Infrastructure Services

| ファイル | 概要 |
|---------|------|
| `AIS.Core.ts` | AppContext および I18n を実装する |
| `AIS.Interface.ts` | インフラストラクチャのインターフェースを定義する |
| `AIS.Class.ts` | 定数およびクラスを定義する |
| `AIS.Api.ts` | インフラルートを登録する |
| `AIS.Utilities.ts` | ApiCache および DiagnosticsManager を提供する |

#### AP — Adlaire Platform

| ファイル | 概要 |
|---------|------|
| `AP.Core.ts` | AuthMiddleware、RequestLoggingMiddleware、および SecurityHeadersMiddleware を実装する |
| `AP.Interface.ts` | プラットフォームのインターフェースを定義する |
| `AP.Class.ts` | 定数およびクラスを定義する |
| `AP.Api.ts` | プラットフォームルートを登録する |
| `AP.Utilities.ts` | ユーティリティ関数を提供する |
| `JsEngine/` | ブラウザ向けクライアントモジュール群を格納する |

#### ASG — Adlaire Static Generator

| ファイル | 概要 |
|---------|------|
| `ASG.Core.ts` | Builder、MarkdownService、および TemplateRenderer を実装する |
| `ASG.Interface.ts` | 静的生成のインターフェースを定義する |
| `ASG.Class.ts` | 定数およびクラスを定義する |
| `ASG.Api.ts` | ジェネレータルートを登録する |
| `ASG.Utilities.ts` | ユーティリティ関数を提供する |

#### ASS — Adlaire Server System

| ファイル | 概要 |
|---------|------|
| `ASS.Core.php` | AuthService、StorageService、FileService、および GitService を実装する |
| `ASS.Interface.php` | サーバサイドのインターフェースを定義する |
| `ASS.Class.php` | 定数およびクラスを定義する |
| `ASS.Api.php` | API ハンドラを登録する |
| `ASS.Utilities.php` | ユーティリティ関数を提供する |

#### ADS — Adlaire Design System

| ファイル | 概要 |
|---------|------|
| `ADS.Base.css` | ベーススタイルおよびリセットを定義する |
| `ADS.Components.css` | コンポーネント固有のスタイルを定義する |
| `ADS.Editor.css` | エディタおよび管理画面向けのスタイルを定義する |

#### AEB — Adlaire Editor & Blocks

| ファイル | 概要 |
|---------|------|
| `AEB.Core.ts` | エディタのブロックシステムを実装する |
| `AEB.Blocks.ts` | ブロックの型定義および実装を提供する |
| `AEB.Utils.ts` | ユーティリティ関数を提供する |

### §4.3 言語別フレームワーク

#### TypeScript

| 接頭辞 | 正式名称 | ファイル数 |
|--------|---------|-----------|
| **APF** | Adlaire Platform Foundation | 5 |
| **ACS** | Adlaire Client Services | 5 |
| **ACE** | Adlaire Content Engine | 5 + アセット |
| **AIS** | Adlaire Infrastructure Services | 5 |
| **AP** | Adlaire Platform | 5 + JsEngine |
| **ASG** | Adlaire Static Generator | 5 |
| **AEB** | Adlaire Editor & Blocks | 3 |

#### PHP

| 接頭辞 | 正式名称 | ファイル数 |
|--------|---------|-----------|
| **ASS** | Adlaire Server System | 5 |

#### CSS

| 接頭辞 | 正式名称 | ファイル数 |
|--------|---------|-----------|
| **ADS** | Adlaire Design System | 3 |

### §4.4 開発言語とランタイム

AdlairePlatform で使用する言語およびランタイムは以下のとおりである。

| 言語 / ランタイム | バージョン | 用途 | 対象フレームワーク |
|------------------|-----------|------|-------------------|
| **Deno** | 2.x 以上 | TypeScript ランタイムおよびツールチェーン（型チェック、リント、フォーマット、テスト） | APF, ACS, ACE, AIS, AP, ASG, AEB |
| **TypeScript** | Deno 内蔵（Strict モード） | サーバサイドおよびクライアントサイドのフレームワーク実装 | APF, ACS, ACE, AIS, AP, ASG, AEB |
| **PHP** | 8.4 以上 | サーバサイドのビジネスロジック実装 | ASS |
| **CSS** | CSS3 | デザインシステムのスタイル定義 | ADS |
| **HTML** | HTML5 | 管理画面等のアセット | ACE（AdminEngine） |

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
| **Strict モード必須** | TypeScript コンパイラオプションとして `strict: true` および `noImplicitAny: true` を有効にしなければならない |
| **ブラウザ向け設定** | クライアントサイドのコード（JsEngine、AEB）は `browser.deno.json` に従い、DOM 型定義（`dom`、`dom.iterable`、`dom.asynciterable`、`esnext`）を使用しなければならない |
| **Node.js 禁止** | Node.js ランタイムおよび npm パッケージの使用を禁止する（§2.1「外部依存ゼロ」に準ずる） |
| **PHP バージョン** | ASS は PHP 8.4 以上で動作しなければならない。すべてのファイルで `declare(strict_types=1)` を宣言しなければならない |

---

## §5 ファイル構成規則

### §5.1 ディレクトリ構成

各フレームワークは `Framework/` 配下に接頭辞と同名のディレクトリを持つ。

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
├── types.ts                  # 共通型定義
└── browser.deno.json         # コンパイラ設定
```

#### ディレクトリ配置ルール

| ルール | 内容 |
|--------|------|
| **1 フレームワーク 1 ディレクトリ** | 各フレームワークは `Framework/{PREFIX}/` に配置しなければならない |
| **接頭辞一致** | ディレクトリ名はフレームワークの接頭辞と一致させなければならない（例: ACS → `Framework/ACS/`） |
| **フラット原則** | コンポーネントファイルはディレクトリ直下に配置しなければならない。ネストを禁止する |
| **サブディレクトリ** | アセットやクライアントモジュール等、コンポーネント以外のファイルはサブディレクトリに配置してよい（例: `AP/JsEngine/`、`ACE/AdminEngine/`） |

### §5.2 命名規則

#### コンポーネントファイル名

コンポーネントファイルは `{PREFIX}.{ComponentName}.{ext}` の形式で命名する。

| 要素 | 規則 |
|------|------|
| **接頭辞（PREFIX）** | フレームワークの略称を大文字で記述する（例: `ACS`、`AP`、`ASS`） |
| **コンポーネント名** | PascalCase で記述する（例: `Core`、`Interface`、`Utilities`） |
| **区切り文字** | ドット（`.`）で接頭辞とコンポーネント名を区切る |
| **拡張子** | TypeScript は `.ts`、PHP は `.php`、CSS は `.css` とする |

**例:**

```
ACS.Core.ts         # TypeScript コンポーネント
ASS.Api.php         # PHP コンポーネント
ADS.Components.css  # CSS コンポーネント
```

#### ディレクトリ名

| 要素 | 規則 |
|------|------|
| **フレームワークディレクトリ** | フレームワーク接頭辞と同一の大文字表記とする（例: `ACS/`、`APF/`） |
| **サブディレクトリ** | PascalCase で命名する（例: `JsEngine/`、`AdminEngine/`） |

#### サブディレクトリ内ファイル名

サブディレクトリ内のファイルは、コンポーネントファイルとは異なる命名規則に従う。

| 要素 | 規則 |
|------|------|
| **クライアントモジュール** | kebab-case で命名する（例: `ap-api-client.ts`、`aeb-adapter.ts`） |
| **HTML アセット** | 小文字で命名する（例: `dashboard.html`、`login.html`） |
| **CSS アセット** | 小文字で命名する（例: `dashboard.css`） |
| **型定義ファイル** | kebab-case で命名し、拡張子を `.d.ts` とする（例: `browser-types.d.ts`） |

### §5.3 コンポーネント配置

#### 言語別の構成パターン

各フレームワークは、使用する言語に応じて以下のいずれかの構成パターンに従う。

**TypeScript フレームワーク（標準パターン）**

| ファイル | 配置 |
|---------|------|
| `{PREFIX}.Core.ts` | 必須 |
| `{PREFIX}.Interface.ts` | 任意 |
| `{PREFIX}.Class.ts` | 任意 |
| `{PREFIX}.Api.ts` | 任意 |
| `{PREFIX}.Utilities.ts` | 任意 |

**PHP フレームワーク（ASS）**

| ファイル | 配置 |
|---------|------|
| `ASS.Core.php` | 必須 |
| `ASS.Interface.php` | 任意 |
| `ASS.Class.php` | 任意 |
| `ASS.Api.php` | 任意 |
| `ASS.Utilities.php` | 任意 |

**CSS フレームワーク（ADS）**

| ファイル | 配置 |
|---------|------|
| `ADS.Base.css` | 必須 |
| `ADS.Components.css` | 任意 |
| `ADS.Editor.css` | 任意 |

#### サブディレクトリの配置ルール

| ルール | 内容 |
|--------|------|
| **目的限定** | サブディレクトリは、クライアントモジュールやアセット等のコンポーネント以外のファイルを格納する場合にのみ使用する |
| **5 ファイル上限の対象外** | サブディレクトリ内のファイルは §2.1「1 エンジン 5 ファイル原則」の上限に含めない |
| **命名の独立性** | サブディレクトリ内のファイルはコンポーネントの命名規則（`{PREFIX}.{Name}.{ext}`）に従わなくてよい |

---

*本ルールブックは Adlaire Group に帰属し、同グループの承認なく変更または再配布することを禁ずる。*
