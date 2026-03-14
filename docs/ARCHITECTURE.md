# AdlairePlatform — エンジン駆動モデルアーキテクチャ基本設計書

<!-- ⚠️ 削除禁止: 本ドキュメントはエンジン駆動モデルアーキテクチャに関する最上位ドキュメントです -->

> **Ver.1.4-pre**: 本ドキュメントは Ver.1.4-pre 時点のアーキテクチャを記録しています。
> 全 16 エンジン実装完了。Ver.1.4-pre で AppContext・Logger・MailerEngine・DiagnosticEngine を追加。
> **Ver.1.4-pre**: 本ドキュメントは Ver.1.4-pre 時点のアーキテクチャを記録しています。  
> 全 15 エンジン実装完了。Ver.1.4-pre で AppContext・Logger・MailerEngine を追加。  
> **最終更新**: 2026-03-10（5文書構成整理）  
> **分類**: 社内限り

> **関連ドキュメント**:
> - 基本設計書・基本方針 → [AdlairePlatform_Design.md](AdlairePlatform_Design.md)（最上位ドキュメント）
> - 実装レベルの技術仕様（詳細設計） → [DETAILED_DESIGN.md](DETAILED_DESIGN.md)
> - セキュリティ → [SECURITY_POLICY.md](SECURITY_POLICY.md)
> - 機能一覧・関数リファレンス → [features.md](features.md)
> - 本ドキュメントは **エンジン駆動モデルアーキテクチャに関する基本設計書・方針** を定めています。

---

## 目次

1. [設計思想](#1-設計思想)
2. [ディレクトリ構成](#2-ディレクトリ構成)
3. [エンジン基本設計・方針](#3-エンジン基本設計方針)
4. [リクエストフロー](#4-リクエストフロー)
5. [データ層設計方針](#5-データ層設計方針)
6. [セキュリティ設計方針](#6-セキュリティ設計方針)
7. [フック機構設計方針](#7-フック機構設計方針)
8. [エンジン一覧](#8-エンジン一覧)

---

## 目次

1. [ディレクトリ構成](#1-ディレクトリ構成)
2. [ファイル責務](#2-ファイル責務)
3. [リクエストフロー](#3-リクエストフロー)
4. [データ層](#4-データ層)
5. [フック機構](#5-フック機構)
6. [定数](#6-定数)
7. [エンジン一覧](#7-エンジン一覧)

> **注**: 設計思想は [AdlairePlatform_Design.md](AdlairePlatform_Design.md#設計思想) を、  
> セキュリティ方針は [SECURITY_POLICY.md](SECURITY_POLICY.md) を参照してください。

> プロジェクト全体の基本設計・基本方針は [AdlairePlatform_Design.md](AdlairePlatform_Design.md) を参照してください。

---

## 1. ディレクトリ構成

```
AdlairePlatform/
├─ index.php                    # エントリーポイント（Router・ユーティリティ・レガシーラッパー）
├─ .htaccess                    # URL rewrite・セキュリティヘッダー・アクセス制限
├─ docs/
│  ├─ AdlairePlatform_Design.md # 基本設計・設計方針
│  ├─ ARCHITECTURE.md           # 本ドキュメント（アーキテクチャ設計）
│  ├─ DETAILED_DESIGN.md        # 詳細設計書（実装レベルの技術仕様）
│  ├─ SECURITY_POLICY.md        # セキュリティ方針（社内限定）
│  ├─ VERSIONING.md             # バージョン規則
│  ├─ features.md               # 実装機能一覧
│  └─ Licenses/
│     └─ LICENSE_Ver.2.0.md
├─ engines/
│  ├─ AdminEngine.php           # 認証・CSRF・管理アクション・ダッシュボード
│  ├─ AdminEngine/
│  │  ├─ dashboard.html         # ダッシュボードテンプレート（テーマ非依存）
│  │  └─ dashboard.css          # ダッシュボード専用スタイル
│  ├─ TemplateEngine.php        # 軽量テンプレートエンジン（{{var}}, {{{raw}}}, #if, #each, ネストプロパティ, フィルター）
│  ├─ ThemeEngine.php           # テーマ検証・読み込み・コンテキスト構築
│  ├─ UpdateEngine.php          # アップデート・バックアップ・ロールバック
│  ├─ StaticEngine.php          # 静的書き出し・差分ビルド・アセット管理
│  ├─ ApiEngine.php             # 公開 REST API（ヘッドレス CMS）
│  ├─ CollectionEngine.php      # コレクション管理（ブログ・ニュース等）
│  ├─ MarkdownEngine.php        # Markdown パーサー（フロントマター対応）
│  ├─ GitEngine.php             # GitHub リポジトリ連携（Pull/Push）
│  ├─ WebhookEngine.php         # Webhook 管理・送信（SSRF 防止付き）
│  ├─ CacheEngine.php           # API レスポンスキャッシュ
│  ├─ ImageOptimizer.php        # 画像最適化（リサイズ・品質調整）
│  ├─ AppContext.php            # 集中状態管理（グローバル変数代替） ⭐ Ver.1.4-pre
│  ├─ Logger.php                # 構造化ログ（PSR-3 互換・ファイルローテーション） ⭐ Ver.1.4-pre
│  ├─ MailerEngine.php          # メール送信抽象化（リトライ・テストモード） ⭐ Ver.1.4-pre
│  └─ JsEngine/
│     ├─ autosize.js            # テキストエリア自動リサイズ
│     ├─ editInplace.js         # インプレイス編集（バニラJS・plain text）
│     ├─ dashboard.js           # ダッシュボード固有のインタラクション
│     ├─ static_builder.js      # 静的書き出し管理 UI
│     ├─ collection_manager.js  # コレクション管理 UI
│     ├─ git_manager.js         # Git 連携 UI
│     ├─ webhook_manager.js     # Webhook 管理 UI
│     ├─ api_keys.js            # API キー管理 UI
│     ├─ ap-api-client.js       # 静的サイト向け API クライアント
│     └─ ap-search.js           # クライアントサイド検索
│
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

## 3. エンジン基本設計・方針

> 本セクションは各エンジンの**アーキテクチャレベルの設計・方針**を記録します。
> 関数シグネチャ・内部フローなどの実装仕様は [DETAILED_DESIGN.md §2](DETAILED_DESIGN.md) を参照してください。
## 2. ファイル責務

### index.php（エントリーポイント）

単一エントリーポイントとして全リクエストを受け付ける。PHP バージョン検証・全 15 エンジンの require・Logger 初期化を起動時に実行。ルーティング・データ層アクセス・ユーティリティ関数を提供し、各エンジンの `handle()` メソッドにディスパッチする。

- `JsonCache` クラスによるリクエスト内 JSON I/O キャッシュ（Ver.1.4-pre）
- `migrate_from_files()` によるレガシーデータの自動移行
- AdminEngine へのレガシーラッパー関数（`is_loggedin()`, `csrf_token()`, `verify_csrf()`）を維持

### AdminEngine

認証・CSRF 検証・管理アクション・ダッシュボードを担当するコアエンジン。

- シングルパスワード認証モデルを採用（マルチユーザー管理の複雑性を排除）
- `ap_action` POST パラメータによるアクションディスパッチパターン
- TemplateEngine 連携によるテーマ非依存ダッシュボード
- リビジョン管理（フィールド単位の履歴保存・上限 30 件）
- bcrypt によるパスワードハッシュ化・MD5 からの自動移行

### TemplateEngine

PHP フリーの軽量テンプレートエンジン。

- Handlebars ライクな独自構文: `{{var}}` / `{{{raw}}}` / `{{#if}}` / `{{#each}}` / `{{> partial}}`
- フィルター構文: `{{var|upper}}` / `{{var|trim|upper}}`（Ver.1.4-pre）
- ドット記法: `{{user.name}}` でネスト配列を解決（Ver.1.4-pre）
- パーシャル循環参照防止（最大深度 10）
- 未処理タグの Logger 警告

### ThemeEngine

テーマの検証・読み込み・テンプレートコンテキスト構築を担当。

- テーマ名バリデーション（`[a-zA-Z0-9_-]`）によるパストラバーサル防止
- `theme.html` → TemplateEngine 方式（`theme.php` は Ver.1.3-28 で廃止）
- デフォルトテーマ `AP-Default` へのフォールバック
- 動的 CMS 用コンテキスト（`buildContext()`）と静的生成用コンテキスト（`buildStaticContext()`）の分離
- AppContext からの状態取得（Ver.1.4-pre）

### UpdateEngine

GitHub Releases API を利用したアップデート・バックアップ・ロールバックを担当。

- 更新前自動バックアップ（`backup/YYYYMMDD_HHmmss/` + `meta.json`）
- バックアップ世代管理（デフォルト 5 世代）
- ZIP ダウンロード・展開・適用（`data/` / `backup/` 保護）
- 事前環境チェック（ZipArchive / allow_url_fopen / 書き込み権限 / ディスク容量）
- GitHub ドメインのみ URL 許可

### StaticEngine

静的サイト生成エンジン。

- `content_hash` / `settings_hash` による差分ビルド
- ThemeEngine 連携（`buildStaticContext()` で admin=false コンテキスト構築）
- アセット管理（テーマ CSS/JS + uploads/ の filemtime 差分コピー）
- Static-First 配信（`.htaccess` で静的ファイル優先・PHP フォールバック）
- コレクション静的生成（一覧・個別・タグ・ページネーション）
- sitemap.xml / robots.txt / search-index.json の自動生成
- HTML / CSS ミニファイ

### ApiEngine

ヘッドレス CMS 向け公開 REST API。

- `?ap_api=` クエリパラメータによるエンドポイントルーティング
- 設定可能な CORS オリジン許可（`Vary: Origin` ヘッダー）
- Bearer トークン + bcrypt による API キー認証
- CacheEngine 連携によるレスポンスキャッシュ
- お問い合わせフォーム（ハニーポット + レート制限）

### CollectionEngine

Markdown ベースのコレクション管理（ブログ・ニュース等）。

- `ap-collections.json` によるスキーマ定義
- コレクション・アイテムの CRUD 操作
- スラグパターン検証（`/^[a-zA-Z0-9][a-zA-Z0-9_-]*$/`）
- MarkdownEngine 連携によるフロントマター解析

### MarkdownEngine

フロントマター対応の Markdown パーサー。

- YAML フロントマター解析（`---` 区切り）
- Markdown → HTML 変換
- YAML 値パース（文字列・数値・真偽値・配列）
- 重複キーは最初の値を優先

### GitEngine

GitHub リポジトリとのコンテンツ同期。

- Pull（リポジトリからコンテンツ取り込み）/ Push（コンテンツ書き出し）
- パストラバーサル防止（悪意あるリポジトリパスのブロック）

### WebhookEngine

Webhook の管理・送信。

- Webhook URL 登録時のプライベート IP ブロック
- 非同期送信
- DNS リバインディング防止（送信時の DNS 再解決チェック）
- DNS 解決失敗時のブロック

### CacheEngine

API レスポンスのファイルベースキャッシュ。

- エンドポイント + キーの組み合わせでキャッシュ管理
- TTL ベースの有効期限
- エンドポイント名サニタイズ（`/[^a-zA-Z0-9_\-]/` 除去）

### ImageOptimizer

GD ライブラリを使用した画像最適化。

- JPEG / PNG / WebP の品質調整
- リサイズ処理

### AppContext ⭐ Ver.1.4-pre

グローバル変数（`$c`, `$d`, `$host` 等）の静的クラスへの集約。

- `syncFromGlobals()` / `syncToGlobals()` による後方互換
- 型安全な設定値アクセサ（`config()` / `setConfig()`）
- フック管理（`addHook()` / `getHooks()`）

### Logger ⭐ Ver.1.4-pre

PSR-3 互換の集中ログ管理。

- レベル別ログ出力（DEBUG / INFO / WARNING / ERROR）
- JSON 構造化ログ・リクエスト ID 追跡
- 日別ファイル出力（`data/logs/ap-YYYY-MM-DD.log`）
- サイズベースローテーション（5MB 上限・最大 5 世代）
- 古いログの自動削除（30 日以上経過）

### MailerEngine ⭐ Ver.1.4-pre

メール送信の抽象化レイヤー。

- リトライ付きメール送信（最大 2 回）
- ヘッダインジェクション対策（CR/LF/ヌルバイト除去）
- お問い合わせフォーム用ヘルパー（`sendContact()`）
- テストモード（モック対応・送信メール一覧取得）

### JsEngine

フロントエンド JavaScript モジュール群。`engines/JsEngine/` ディレクトリに集約。

- バニラ JavaScript（ES5+）で外部依存なし（autosize のみセルフホスト）
- `admin-head` フック経由で管理画面にスクリプトを注入
- 各 JS ファイルが特定のエンジン UI を担当

---

## 3. リクエストフロー

```
HTTP Request
    │
    ▼
index.php
    ├─ PHP 8.2 バージョンチェック
    ├─ require engines/* （全 15 エンジン）
    ├─ ob_start() ─ 出力バッファ開始
    ├─ session_start()
    ├─ migrate_from_files() ─ データ自動移行
    ├─ host() ─ URL・スラッグ解析
    ├─ AdminEngine::handle() ─ 管理アクション → exit
    ├─ StaticEngine::handle() ─ 静的生成アクション → exit
    ├─ CollectionEngine::handle() ─ コレクション管理 → exit
    ├─ GitEngine::handle() ─ Git 連携 → exit
    ├─ WebhookEngine::handle() ─ Webhook 管理 → exit
    ├─ ApiEngine::handle() ─ REST API → exit
    ├─ handle_update_action() ─ アップデート → exit
    │
    ├─ 設定・コンテンツ読み込み
    ├─ ?admin → AdminEngine::renderDashboard() → exit
    ├─ AdminEngine::registerHooks()
    │
    ▼
ThemeEngine::load()
    ├─ theme.html → TemplateEngine::render()
    └─ フォールバック → AP-Default
    │
    ▼
ob_end_flush()
```

---

## 5. データ層設計方針
## 4. データ層

- `data/settings/`（設定系）と `data/content/`（コンテンツ系）に分離
- JSON ファイルをストレージとして使用（データベース不要）
- 初回アクセス時にディレクトリを自動生成
- `JsonCache` クラスによるリクエスト内キャッシュでファイル I/O を最小化（Ver.1.4-pre）

> ファイルパスマッピング・マイグレーション仕様は [DETAILED_DESIGN.md §3](DETAILED_DESIGN.md) を参照してください。

---

## 6. セキュリティ設計方針

多層防御アーキテクチャを採用し、認証・CSRF・XSS・CSP・パストラバーサル対策を標準装備する。

- AdminEngine がセキュリティゲートウェイとして機能（認証・CSRF 検証の一元管理）
- `.htaccess` / Nginx server block による HTTP レイヤーのセキュリティヘッダー付与
- `engines/*.php` への直接アクセス禁止
- `data/` / `backup/` への HTTP アクセス拒否
## 5. フック機構

> セキュリティ設計原則（5 原則）は [AdlairePlatform_Design.md §6](AdlairePlatform_Design.md) を参照してください。
> 脅威対策マトリクス（実装仕様）は [DETAILED_DESIGN.md §4](DETAILED_DESIGN.md) を参照してください。

---

## 7. フック機構設計方針

- 外部プラグインによるフック登録は廃止（`loadPlugins()` / `plugins/` ディレクトリ削除済み）
- 内部コアフックのみに限定
- `admin-head` フックで JsEngine スクリプトを登録
- `AdminEngine::registerHooks()` → `AdminEngine::getAdminScripts()` パターン
## 6. 定数

> フック機構の実装コードは [DETAILED_DESIGN.md §5](DETAILED_DESIGN.md) を参照してください。

---

## 8. エンジン一覧
## 7. エンジン一覧

| エンジン | ファイル | ステータス | 説明 |
|---------|---------|-----------|------|
| AdminEngine | `engines/AdminEngine.php` | ✅ 実装済み（Ver.1.3-27） | 管理エンジン・ダッシュボード |
| TemplateEngine | `engines/TemplateEngine.php` | ✅ 実装済み（Ver.1.2-26） | 軽量テンプレートエンジン |
| ThemeEngine | `engines/ThemeEngine.php` | ✅ 実装済み（Ver.1.2-13） | テーマ検証・読み込み |
| UpdateEngine | `engines/UpdateEngine.php` | ✅ 実装済み（Ver.1.0-11） | アップデート・バックアップ |
| StaticEngine | `engines/StaticEngine.php` | ✅ 実装済み（Ver.1.3-28） | 静的サイト生成 |
| ApiEngine | `engines/ApiEngine.php` | ✅ 実装済み（Ver.1.3-28） | ヘッドレス CMS REST API |
| CollectionEngine | `engines/CollectionEngine.php` | ✅ 実装済み（Ver.1.3-28） | コレクション管理 |
| MarkdownEngine | `engines/MarkdownEngine.php` | ✅ 実装済み（Ver.1.3-28） | Markdown パーサー |
| GitEngine | `engines/GitEngine.php` | ✅ 実装済み（Ver.1.3-28） | GitHub リポジトリ連携 |
| WebhookEngine | `engines/WebhookEngine.php` | ✅ 実装済み（Ver.1.3-28） | Webhook 管理・送信 |
| CacheEngine | `engines/CacheEngine.php` | ✅ 実装済み（Ver.1.3-28） | API レスポンスキャッシュ |
| ImageOptimizer | `engines/ImageOptimizer.php` | ✅ 実装済み（Ver.1.3-28） | 画像最適化 |
| AppContext | `engines/AppContext.php` | ✅ 実装済み（Ver.1.4-pre） | 集中状態管理 ⭐ |
| Logger | `engines/Logger.php` | ✅ 実装済み（Ver.1.4-pre） | 構造化ログ（PSR-3 互換） ⭐ |
| MailerEngine | `engines/MailerEngine.php` | ✅ 実装済み（Ver.1.4-pre） | メール送信抽象化 ⭐ |
| DiagnosticEngine | `engines/DiagnosticEngine.php` | ✅ 実装済み（Ver.1.4-pre） | リアルタイム診断・テレメトリ ⭐ |

---

## 📝 変更履歴

本ドキュメントの変更履歴は、以下のファイルで一元管理されています:

👉 **[docs/DOCUMENT_CHANGELOG.md](./DOCUMENT_CHANGELOG.md#-architecturemd)** を参照してください。

---

*Adlaire License Ver.2.0 — 社内限り*
