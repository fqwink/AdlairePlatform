# AdlairePlatform — エンジン駆動モデルアーキテクチャ基本設計書

<!-- ⚠️ 削除禁止: 本ドキュメントはエンジン駆動モデルアーキテクチャに関する最上位ドキュメントです -->

> **Ver.1.8-38**: 本ドキュメントは Ver.1.8 時点のアーキテクチャを記録しています。
> 全エンジンを Framework モジュール（APF/ACE/AIS/ASG/AP）に統合済み。
> **最終更新**: 2026-03-15（Ver.1.8 Framework 統合）
> **分類**: 社内限り

> **関連ドキュメント**:
> - 基本設計書・基本方針 → [AdlairePlatform_Design.md](AdlairePlatform_Design.md)（最上位ドキュメント）
> - 実装レベルの技術仕様（詳細設計） → [DETAILED_DESIGN.md](DETAILED_DESIGN.md)
> - セキュリティ → [SECURITY_POLICY.md](SECURITY_POLICY.md)
> - 機能一覧・関数リファレンス → [features.md](features.md)
> - フレームワークルールブック → [FRAMEWORK_RULEBOOK.md](FRAMEWORK_RULEBOOK.md)
> - 本ドキュメントは **エンジン駆動モデルアーキテクチャに関する基本設計書・方針** を定めています。

---

## 目次

1. [ディレクトリ構成](#1-ディレクトリ構成)
2. [ファイル責務](#2-ファイル責務)
3. [エンジン基本設計・方針](#3-エンジン基本設計方針)
4. [リクエストフロー](#4-リクエストフロー)
5. [データ層設計方針](#5-データ層設計方針)
6. [セキュリティ設計方針](#6-セキュリティ設計方針)
7. [フック機構設計方針](#7-フック機構設計方針)
8. [Framework モジュール一覧](#8-framework-モジュール一覧)

> **注**: 設計思想は [AdlairePlatform_Design.md](AdlairePlatform_Design.md#設計思想) を、
> セキュリティ方針は [SECURITY_POLICY.md](SECURITY_POLICY.md) を参照してください。

---

## 1. ディレクトリ構成

```
AdlairePlatform/
├─ index.php                    # エントリーポイント（Router ディスパッチ）
├─ autoload.php                 # 名前空間マッピングオートローダー
├─ bootstrap.php                # DI コンテナ・イベント初期化
├─ routes.php                   # ルート定義・ミドルウェア登録
├─ .htaccess                    # URL rewrite・セキュリティヘッダー・アクセス制限
├─ docs/
│  ├─ AdlairePlatform_Design.md # 基本設計・設計方針
│  ├─ ARCHITECTURE.md           # 本ドキュメント（アーキテクチャ設計）
│  ├─ DETAILED_DESIGN.md        # 詳細設計書（実装レベルの技術仕様）
│  ├─ SECURITY_POLICY.md        # セキュリティ方針（社内限定）
│  ├─ VERSIONING.md             # バージョン規則
│  ├─ FRAMEWORK_RULEBOOK.md     # フレームワークルールブック
│  ├─ features.md               # 実装機能一覧
│  └─ Licenses/
│     └─ LICENSE_Ver.2.0.md
├─ Framework/
│  ├─ AP/                       # Adlaire Platform Controllers
│  │  ├─ AP.Controllers.php     # Controller 統合モジュール（Ver.1.8）
│  │  ├─ AP.Bridge.php          # グローバルユーティリティ関数（Ver.1.8）
│  │  └─ JsEngine/              # フロントエンド JavaScript モジュール群
│  │     ├─ aeb-adapter.js      # AEB アダプター
│  │     ├─ ap-api-client.js    # 静的サイト向け API クライアント
│  │     ├─ ap-events.js        # イベントシステム
│  │     ├─ ap-i18n.js          # 国際化
│  │     ├─ ap-search.js        # クライアントサイド検索
│  │     ├─ ap-utils.js         # ユーティリティ
│  │     ├─ api_keys.js         # API キー管理 UI
│  │     ├─ autosize.js         # テキストエリア自動リサイズ
│  │     ├─ collection_manager.js # コレクション管理 UI
│  │     ├─ dashboard.js        # ダッシュボード UI
│  │     ├─ diagnostics.js      # 診断 UI
│  │     ├─ editInplace.js      # インプレイス編集
│  │     ├─ git_manager.js      # Git 連携 UI
│  │     ├─ static_builder.js   # 静的書き出し UI
│  │     ├─ updater.js          # アップデーター UI
│  │     ├─ webhook_manager.js  # Webhook 管理 UI
│  │     └─ wysiwyg.js          # WYSIWYG エディタ
│  ├─ APF/                      # Adlaire Platform Foundation
│  │  ├─ APF.Core.php           # Container, Router, Request, Response, HookManager
│  │  ├─ APF.Middleware.php     # Middleware パイプライン
│  │  ├─ APF.Database.php       # JSON ファイルストレージ
│  │  └─ APF.Utilities.php      # Security, Str, Cache, Logger
│  ├─ ACE/                      # Adlaire Content Engine
│  │  ├─ ACE.Core.php           # TemplateEngine, ThemeEngine, CollectionEngine, MarkdownEngine
│  │  ├─ ACE.Admin.php          # AdminEngine（認証・ダッシュボード）
│  │  ├─ ACE.Api.php            # ApiEngine, WebhookService, RateLimiter
│  │  └─ AdminEngine/           # 管理画面テンプレート
│  │     ├─ dashboard.html      # ダッシュボードテンプレート
│  │     ├─ dashboard.css       # ダッシュボード専用スタイル
│  │     └─ login.html          # ログインテンプレート
│  ├─ AIS/                      # Adlaire Infrastructure Services
│  │  ├─ AIS.Core.php           # AppContext, I18n, EventDispatcher
│  │  ├─ AIS.System.php         # HealthMonitor, DiagnosticsManager, ApiCache
│  │  └─ AIS.Deployment.php     # UpdateEngine, GitEngine, ImageOptimizer
│  ├─ ASG/                      # Adlaire Static Generator
│  │  ├─ ASG.Core.php           # StaticEngine
│  │  ├─ ASG.Template.php       # テンプレート処理
│  │  └─ ASG.Utilities.php      # ユーティリティ
│  ├─ AEB/                      # Adlaire Editor & Blocks
│  │  └─ AEB.Core.js            # WYSIWYG エディタ
│  └─ ADS/                      # Adlaire Design System
│     ├─ ADS.Base.css            # ベーススタイル
│     ├─ ADS.Components.css      # コンポーネントスタイル
│     └─ ADS.Editor.css          # エディタスタイル
├─ themes/
│  ├─ AP-Default/
│  │  ├─ theme.html             # テンプレートエンジン方式
│  │  └─ style.css
│  └─ AP-Adlaire/
│     ├─ theme.html
│     └─ style.css
├─ data/
│  ├─ settings/
│  │  ├─ settings.json          # サイト設定
│  │  ├─ auth.json              # 認証情報（bcrypt）
│  │  ├─ update_cache.json      # GitHub API キャッシュ（1時間）
│  │  ├─ login_attempts.json    # ログイン試行記録（レート制限）
│  │  ├─ version.json           # アップデート履歴
│  │  └─ static_build.json      # 静的ビルド差分状態
│  └─ content/
│     └─ pages.json             # ページコンテンツ
├─ uploads/                     # アップロード済み画像（公開・PHP実行不可）
│  └─ .htaccess                 # Options -Indexes + PHP 禁止
├─ static/                      # StaticEngine が書き出す静的 HTML
│  ├─ .htaccess                 # Static-First: HTML 優先・PHP フォールバック
│  ├─ index.html                # 書き出し済みページ（例）
│  └─ uploads/                  # uploads/ のミラー（filemtime 差分コピー）
└─ backup/                      # 自動バックアップ（最大5世代）
   └─ YYYYMMDD_HHmmss/
      └─ meta.json              # バックアップメタ情報
```

---

## 2. ファイル責務

### index.php（エントリーポイント）

単一エントリーポイントとして全リクエストを受け付ける。PHP バージョン検証 → autoload.php → AP.Bridge.php → bootstrap.php → routes.php の順に読み込み、Router にディスパッチする。

- `autoload.php` で名前空間→ファイルマッピングを登録
- `AP.Bridge.php` でグローバルユーティリティ関数（`data_dir()`, `json_read()`, `h()` 等）を定義
- `bootstrap.php` で DI コンテナ・イベントシステムを初期化
- `routes.php` で Router にルートとミドルウェアを登録
- Router が `dispatch()` で適切な Controller にディスパッチ

### Framework モジュール

各 Framework モジュールはエンジン駆動モデルに基づき、3〜7 ファイルに集約。
名前空間マッピング方式のオートローダーにより、使用時に自動読み込みされる。

---

## 3. エンジン基本設計・方針

> 本セクションは各エンジンの**アーキテクチャレベルの設計・方針**を記録します。
> 関数シグネチャ・内部フローなどの実装仕様は [DETAILED_DESIGN.md §2](DETAILED_DESIGN.md) を参照してください。

### AdminEngine（ACE.Admin.php）

認証・CSRF 検証・管理アクション・ダッシュボードを担当するコアエンジン。

- シングルパスワード認証モデルを採用（マルチユーザー管理の複雑性を排除）
- `ap_action` POST パラメータによるアクションディスパッチパターン
- TemplateEngine 連携によるテーマ非依存ダッシュボード
- リビジョン管理（フィールド単位の履歴保存・上限 30 件）
- bcrypt によるパスワードハッシュ化・MD5 からの自動移行

### TemplateEngine（ACE.Core.php）

PHP フリーの軽量テンプレートエンジン。

- Handlebars ライクな独自構文: `{{var}}` / `{{{raw}}}` / `{{#if}}` / `{{#each}}` / `{{> partial}}`
- フィルター構文: `{{var|upper}}` / `{{var|trim|upper}}`
- ドット記法: `{{user.name}}` でネスト配列を解決
- パーシャル循環参照防止（最大深度 10）
- 未処理タグの Logger 警告

### ThemeEngine（ACE.Core.php）

テーマの検証・読み込み・テンプレートコンテキスト構築を担当。

- テーマ名バリデーション（`[a-zA-Z0-9_-]`）によるパストラバーサル防止
- `theme.html` → TemplateEngine 方式（`theme.php` は Ver.1.3-28 で廃止）
- デフォルトテーマ `AP-Default` へのフォールバック
- 動的 CMS 用コンテキスト（`buildContext()`）と静的生成用コンテキスト（`buildStaticContext()`）の分離
- AppContext からの状態取得

### UpdateEngine（AIS.Deployment.php）

GitHub Releases API を利用したアップデート・バックアップ・ロールバックを担当。

- 更新前自動バックアップ（`backup/YYYYMMDD_HHmmss/` + `meta.json`）
- バックアップ世代管理（デフォルト 5 世代）
- ZIP ダウンロード・展開・適用（`data/` / `backup/` 保護）
- 事前環境チェック（ZipArchive / allow_url_fopen / 書き込み権限 / ディスク容量）
- GitHub ドメインのみ URL 許可

### StaticEngine（ASG.Core.php）

静的サイト生成エンジン。

- `content_hash` / `settings_hash` による差分ビルド
- ThemeEngine 連携（`buildStaticContext()` で admin=false コンテキスト構築）
- アセット管理（テーマ CSS/JS + uploads/ の filemtime 差分コピー）
- Static-First 配信（`.htaccess` で静的ファイル優先・PHP フォールバック）
- コレクション静的生成（一覧・個別・タグ・ページネーション）
- sitemap.xml / robots.txt / search-index.json の自動生成
- HTML / CSS ミニファイ

### ApiEngine（ACE.Api.php）

ヘッドレス CMS 向け公開 REST API。

- `?ap_api=` クエリパラメータによるエンドポイントルーティング
- 設定可能な CORS オリジン許可（`Vary: Origin` ヘッダー）
- Bearer トークン + bcrypt による API キー認証
- レスポンスキャッシュ連携
- お問い合わせフォーム（ハニーポット + レート制限）

### CollectionEngine（ACE.Core.php）

Markdown ベースのコレクション管理（ブログ・ニュース等）。

- `ap-collections.json` によるスキーマ定義
- コレクション・アイテムの CRUD 操作
- スラグパターン検証（`/^[a-zA-Z0-9][a-zA-Z0-9_-]*$/`）
- MarkdownEngine 連携によるフロントマター解析

### MarkdownEngine（ACE.Core.php）

フロントマター対応の Markdown パーサー。

- YAML フロントマター解析（`---` 区切り）
- Markdown → HTML 変換
- YAML 値パース（文字列・数値・真偽値・配列）
- 重複キーは最初の値を優先

### GitEngine（AIS.Deployment.php）

GitHub リポジトリとのコンテンツ同期。

- Pull（リポジトリからコンテンツ取り込み）/ Push（コンテンツ書き出し）
- パストラバーサル防止（悪意あるリポジトリパスのブロック）

### WebhookEngine（ACE.Api.php）

Webhook の管理・送信。

- Webhook URL 登録時のプライベート IP ブロック
- 非同期送信
- DNS リバインディング防止（送信時の DNS 再解決チェック）
- DNS 解決失敗時のブロック

### CacheEngine（AIS.System.php）

API レスポンスのファイルベースキャッシュ。

- エンドポイント + キーの組み合わせでキャッシュ管理
- TTL ベースの有効期限
- エンドポイント名サニタイズ（`/[^a-zA-Z0-9_\-]/` 除去）

### ImageOptimizer（AIS.Deployment.php）

GD ライブラリを使用した画像最適化。

- JPEG / PNG / WebP の品質調整
- リサイズ処理

### AppContext（AIS.Core.php）

グローバル変数（`$c`, `$d`, `$host` 等）の静的クラスへの集約。

- `syncFromGlobals()` / `syncToGlobals()` による後方互換
- 型安全な設定値アクセサ（`config()` / `setConfig()`）
- フック管理（`addHook()` / `getHooks()`）

### Logger（APF.Utilities.php）

PSR-3 互換の集中ログ管理。静的ファサードパターンで全プロジェクトから `Logger::info()` 等で呼び出し可能。

- レベル別ログ出力（DEBUG / INFO / WARNING / ERROR / CRITICAL）
- `__callStatic` / `init()` / `getInstance()` による静的ファサード
- ファイル出力（`data/logs/app.log`）

### MailerEngine（AIS.Core.php）

メール送信の抽象化レイヤー。

- リトライ付きメール送信（最大 2 回）
- ヘッダインジェクション対策（CR/LF/ヌルバイト除去）
- お問い合わせフォーム用ヘルパー（`sendContact()`）
- テストモード（モック対応・送信メール一覧取得）

### JsEngine（Framework/AP/JsEngine/）

フロントエンド JavaScript モジュール群。`Framework/AP/JsEngine/` ディレクトリに集約。

- バニラ JavaScript（ES5+）で外部依存なし（autosize のみセルフホスト）
- `admin-head` フック経由で管理画面にスクリプトを注入
- 各 JS ファイルが特定のエンジン UI を担当

---

## 4. リクエストフロー

```
HTTP Request
    │
    ▼
index.php
    ├─ PHP 8.3 バージョンチェック
    ├─ require autoload.php（名前空間マッピング）
    ├─ require AP.Bridge.php（グローバルユーティリティ）
    ├─ require bootstrap.php（DI コンテナ・イベント）
    ├─ require routes.php（ルート定義）
    ├─ session_start()
    ├─ I18n::init()
    ├─ Logger::init()
    │
    ▼
Router::dispatch()
    ├─ Middleware パイプライン実行
    ├─ Controller メソッド呼び出し
    │  ├─ AdminController → ACE\Admin（認証・管理）
    │  ├─ ApiController → ACE\Api（REST API）
    │  ├─ StaticController → ASG\Core（静的生成）
    │  ├─ UpdateController → AIS\Deployment（アップデート）
    │  └─ DiagnosticController → AIS\System（診断）
    │
    ▼
ThemeEngine::load()
    ├─ theme.html → TemplateEngine::render()
    └─ フォールバック → AP-Default
```

---

## 5. データ層設計方針

- `data/settings/`（設定系）と `data/content/`（コンテンツ系）に分離
- JSON ファイルをストレージとして使用（データベース不要）
- 初回アクセス時にディレクトリを自動生成
- `JsonCache` クラスによるリクエスト内キャッシュでファイル I/O を最小化

> ファイルパスマッピング・マイグレーション仕様は [DETAILED_DESIGN.md §3](DETAILED_DESIGN.md) を参照してください。

---

## 6. セキュリティ設計方針

多層防御アーキテクチャを採用し、認証・CSRF・XSS・CSP・パストラバーサル対策を標準装備する。

- AdminEngine がセキュリティゲートウェイとして機能（認証・CSRF 検証の一元管理）
- `.htaccess` / Nginx server block による HTTP レイヤーのセキュリティヘッダー付与
- `Framework/*.php` への直接アクセス禁止
- `data/` / `backup/` への HTTP アクセス拒否

> セキュリティ設計原則（5 原則）は [AdlairePlatform_Design.md §6](AdlairePlatform_Design.md) を参照してください。
> 脅威対策マトリクス（実装仕様）は [DETAILED_DESIGN.md §4](DETAILED_DESIGN.md) を参照してください。

---

## 7. フック機構設計方針

- 外部プラグインによるフック登録は廃止（`loadPlugins()` / `plugins/` ディレクトリ削除済み）
- 内部コアフックのみに限定
- `admin-head` フックで JsEngine スクリプトを登録
- `AdminEngine::registerHooks()` → `AdminEngine::getAdminScripts()` パターン

> フック機構の実装コードは [DETAILED_DESIGN.md §5](DETAILED_DESIGN.md) を参照してください。

---

## 8. Framework モジュール一覧

| モジュール | ファイル | 説明 |
|-----------|---------|------|
| **APF** — Adlaire Platform Foundation | `Framework/APF/APF.Core.php` | Container, Router, Request, Response, HookManager |
| | `Framework/APF/APF.Middleware.php` | Middleware パイプライン |
| | `Framework/APF/APF.Database.php` | JSON ファイルストレージ |
| | `Framework/APF/APF.Utilities.php` | Security, Str, Cache, Logger |
| **ACE** — Adlaire Content Engine | `Framework/ACE/ACE.Core.php` | TemplateEngine, ThemeEngine, CollectionEngine, MarkdownEngine |
| | `Framework/ACE/ACE.Admin.php` | AdminEngine（認証・ダッシュボード） |
| | `Framework/ACE/ACE.Api.php` | ApiEngine, WebhookService, RateLimiter |
| | `Framework/ACE/AdminEngine/` | 管理画面テンプレート（HTML/CSS） |
| **AIS** — Adlaire Infrastructure Services | `Framework/AIS/AIS.Core.php` | AppContext, I18n, EventDispatcher |
| | `Framework/AIS/AIS.System.php` | HealthMonitor, DiagnosticsManager, ApiCache |
| | `Framework/AIS/AIS.Deployment.php` | UpdateEngine, GitEngine, ImageOptimizer |
| **ASG** — Adlaire Static Generator | `Framework/ASG/ASG.Core.php` | StaticEngine |
| | `Framework/ASG/ASG.Template.php` | テンプレート処理 |
| | `Framework/ASG/ASG.Utilities.php` | ユーティリティ |
| **AP** — Adlaire Platform Controllers | `Framework/AP/AP.Controllers.php` | Controller 統合モジュール |
| | `Framework/AP/AP.Bridge.php` | グローバルユーティリティ関数 |
| | `Framework/AP/JsEngine/` | フロントエンド JavaScript（17ファイル） |
| **AEB** — Adlaire Editor & Blocks | `Framework/AEB/AEB.Core.js` | WYSIWYG エディタ |
| **ADS** — Adlaire Design System | `Framework/ADS/ADS.Base.css` | ベーススタイル |
| | `Framework/ADS/ADS.Components.css` | コンポーネントスタイル |
| | `Framework/ADS/ADS.Editor.css` | エディタスタイル |

---

## 📝 変更履歴

本ドキュメントの変更履歴は、以下のファイルで一元管理されています:

👉 **[docs/DOCUMENT_CHANGELOG.md](./DOCUMENT_CHANGELOG.md#-architecturemd)** を参照してください。

---

*Adlaire License Ver.2.0 — 社内限り*
