# AdlairePlatform アーキテクチャ設計書

<!-- ⚠️ 削除禁止: 本ドキュメントはプロジェクトの正式なアーキテクチャ設計書です -->

> **Ver.1.4-pre**: 本ドキュメントは Ver.1.4-pre 時点のアーキテクチャを記録しています。  
> 全 15 エンジン実装完了。Ver.1.4-pre で AppContext・Logger・MailerEngine を追加。  
> **最終更新**: 2026-03-10（5文書構成整理）  
> **分類**: 社内限り

> **関連ドキュメント**:  
> - 設計方針・設計思想: [AdlairePlatform_Design.md](AdlairePlatform_Design.md)  
> - 機能仕様: [SPECIFICATION.md](SPECIFICATION.md)  
> - セキュリティ: [SECURITY_POLICY.md](SECURITY_POLICY.md)  
> - エンジン技術設計: [ENGINE_DESIGN.md](ENGINE_DESIGN.md)

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

---

## 1. ディレクトリ構成

```
AdlairePlatform/
├─ index.php                    # エントリーポイント（Router・ユーティリティ・レガシーラッパー）
├─ .htaccess                    # URL rewrite・セキュリティヘッダー・アクセス制限
├─ docs/
│  ├─ AdlairePlatform_Design.md # 基本設計・設計方針
│  ├─ ARCHITECTURE.md           # 本ドキュメント（アーキテクチャ設計）
│  ├─ SPECIFICATION.md          # 機能仕様リファレンス
│  ├─ SECURITY_POLICY.md        # セキュリティ方針（社内限定）
│  ├─ ENGINE_DESIGN.md          # エンジン技術設計書リファレンス
│  ├─ VERSIONING.md             # バージョン規則
│  ├─ features.md               # 実装機能一覧
│  ├─ nginx.conf.example        # Nginx 設定リファレンス
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

## 2. ファイル責務

### index.php（エントリーポイント）

| 機能グループ | 関数 | 説明 |
|------------|------|------|
| 起動制御 | ─ | PHP バージョン確認・定数定義・全 15 エンジン require・Logger 初期化 |
| ルーティング | `host()`, `getSlug()` | URL 解析・スラッグ生成・`?admin` ダッシュボードルーティング |
| データ層 | `json_read()`, `json_write()`, `data_dir()`, `settings_dir()`, `content_dir()` | JSON ファイル読み書き（JsonCache 付き） |
| キャッシュ | `JsonCache::get/set/invalidate/clear()` | リクエスト内 JSON I/O キャッシュ ⭐ Ver.1.4-pre |
| マイグレーション | `migrate_from_files()` | 旧データ構造からの自動移行 |
| ユーティリティ | `h()` | HTMLエスケープ |
| ラッパー | `is_loggedin()`, `csrf_token()`, `verify_csrf()` | AdminEngine に委譲 |

### engines/AdminEngine.php

```
AdminEngine::handle(): void
  └─ ap_action POST パラメータでディスパッチ（後方互換: fieldname のみでも edit_field として処理）
     ├─ 'edit_field'        → handleEditField()
     ├─ 'upload_image'      → handleUploadImage()
     ├─ 'list_revisions'    → listRevisions()
     ├─ 'get_revision'      → getRevision()
     ├─ 'restore_revision'  → restoreRevision()
     ├─ 'pin_revision'      → pinRevision()
     └─ 'search_revisions'  → searchRevisions()

AdminEngine::isLoggedIn(): bool
AdminEngine::login(string $storedHash): string
AdminEngine::savePassword(string $p): string
AdminEngine::csrfToken(): string
AdminEngine::verifyCsrf(): void

AdminEngine::saveRevision(string $fieldname, string $content, bool $restored = false): void
AdminEngine::renderEditableContent(string $id, string $content): string
AdminEngine::registerHooks(): void
AdminEngine::getAdminScripts(): string

AdminEngine::renderDashboard(): string
  └─ TemplateEngine で AdminEngine/dashboard.html をレンダリング
AdminEngine::buildDashboardContext(): array
  └─ ページ一覧・設定フィールド・テーマ選択・システム情報を構築
```

### engines/TemplateEngine.php

```
TemplateEngine::render(string $template, array $context, string $partialsDir = ''): string
  ├─ processPartials() — {{> name}} 部分テンプレート読み込み（最大深度10・循環参照防止）
  ├─ processEach()     — {{#each items}}...{{/each}} ループ処理（@index, @first, @last 対応）
  ├─ processIf()       — {{#if var}}...{{else}}...{{/if}} 条件分岐（ネスト対応・@変数対応）
  ├─ processRawVars()  — {{{var}}} / {{{user.name}}} 生 HTML 出力（ネストプロパティ対応）
  ├─ processVars()     — {{var}} / {{var|filter}} エスケープ出力（フィルター対応）
  ├─ resolveValue()    — ドット記法でネスト配列を解決（例: "user.name" → $ctx['user']['name']） ⭐ Ver.1.4-pre
  ├─ applyFilter()     — フィルター適用（upper, lower, capitalize, trim, nl2br, length, truncate:N, default:value） ⭐ Ver.1.4-pre
  └─ warnUnprocessed() — 未処理テンプレートタグを Logger::warning() で警告
```

### engines/ThemeEngine.php

```
ThemeEngine::load(string $themeSelect): void
  ├─ テーマ名バリデーション（英数字・-・_のみ）
  ├─ theme.html を TemplateEngine::render() でレンダリング
  └─ theme.html がなければ AP-Default にフォールバック

ThemeEngine::buildContext(): array
  ├─ 動的 CMS 用テンプレートコンテキストを構築（AppContext から取得） ⭐ Ver.1.4-pre
  └─ AdminEngine::isLoggedIn() / AdminEngine::getAdminScripts() / AdminEngine::renderEditableContent() に委譲

ThemeEngine::buildStaticContext(string $slug, string $content, array $settings): array
  └─ StaticEngine 用コンテキスト（admin=false、管理者 UI 除外）

ThemeEngine::parseMenu(string $menuStr, string $currentPage): array
  └─ メニュー文字列を [{slug, label, active}] 配列にパース

ThemeEngine::listThemes(): array
  └─ themes/ ディレクトリ内のサブディレクトリ一覧を返す
```

### engines/UpdateEngine.php

```
handle_update_action()    ─ POST ap_action のルーティング（要認証・CSRF検証）
check_update()            ─ GitHub Releases API チェック（1時間キャッシュ）
check_environment()       ─ ZipArchive / allow_url_fopen / 書き込み権限 / ディスク容量
backup_current()          ─ 現在ファイルをバックアップ（data/, backup/ 除外）
prune_old_backups()       ─ バックアップ世代管理（AP_BACKUP_GENERATIONS = 5）
apply_update()            ─ ZIP ダウンロード・展開・適用
rollback_to_backup()      ─ バックアップからの復元（data/ 除外）
delete_backup()           ─ 指定バックアップの削除
```

### engines/StaticEngine.php

```
StaticEngine::handle(): void
  └─ ap_action POST パラメータでディスパッチ（要認証・CSRF検証）
     ├─ 'generate_static_diff' → buildDiff()
     ├─ 'generate_static_full' → buildAll()
     ├─ 'clean_static'         → clean()
     ├─ 'build_zip'            → serveZip()
     └─ 'static_status'        → getStatus()

StaticEngine::buildDiff(): array
  ├─ loadBuildState() — static_build.json 読み込み
  ├─ settings_hash 変化 → 全ページ dirty
  └─ content_hash 変化ページのみ buildPage() → saveBuildState()

StaticEngine::buildAll(): array
  └─ 全ページを buildPage() → deleteOrphanedFiles() → copyAssets()

StaticEngine::renderPage(string $slug, string $content): string
  ├─ ThemeEngine::buildStaticContext() でコンテキスト構築（admin=false）
  ├─ TemplateEngine::render() で HTML 生成（{{#if admin}} ブロックは自動除外）
  └─ rewriteAssetPaths() でアセットパスを静的配信向けに補正

StaticEngine::copyAssets(): void
  ├─ テーマ CSS/JS → static/assets/ にコピー
  ├─ uploads/ → static/assets/uploads/ を filemtime 差分コピー
  └─ static/.htaccess を自動生成（PHP 実行禁止）

StaticEngine::getStatus(): array
  └─ ビルド状態・ページ別ステータス（current/outdated/not_built）を返す

StaticEngine::clean(): void
  └─ static/ ディレクトリを再帰削除 + static_build.json をリセット
```

### engines/ApiEngine.php（実装済み）

```
ApiEngine::handle(): void
  └─ ?ap_api= があれば JSON を返して exit
     ├─ 'pages'      → getPages()
     ├─ 'page'       → slug バリデーション → getPage($slug)
     ├─ 'settings'   → getSettings()
     ├─ 'search'     → q バリデーション（1〜100文字） → search($q)
     ├─ 'contact'    → POST 検証 → checkContactRate() → sendContact()
     ├─ 'collection' → コレクション API
     └─ default      → jsonError('不明な API エンドポイントです', 400)

CORS: 設定可能なオリジン許可 + Vary: Origin ヘッダー
API キー認証: Bearer トークン + bcrypt
レスポンスキャッシュ: CacheEngine 連携
```

### engines/CollectionEngine.php（実装済み）

```
CollectionEngine::handle(): void
  └─ ap_action POST パラメータでディスパッチ
     ├─ コレクション CRUD（作成・読取・更新・削除）
     ├─ アイテム CRUD（Markdown ファイル管理）
     └─ スキーマ定義（ap-collections.json）

スラグパターン検証: /^[a-zA-Z0-9][a-zA-Z0-9_-]*$/
directory フィールド検証: スラグパターン準拠（R14 fix）
コレクション名検証: 全操作でスラグパターン検証（R15 fix）
```

### engines/MarkdownEngine.php（実装済み）

```
MarkdownEngine::parse(string $markdown): array
  ├─ parseFrontmatter() — YAML フロントマター解析（重複キーは最初の値を優先: R8 fix）
  └─ toHtml() — Markdown → HTML 変換

MarkdownEngine::parseYamlValue(string $val): mixed
  └─ YAML 値のパース（文字列・数値・真偽値・配列）
```

### engines/GitEngine.php（実装済み）

```
GitEngine::handle(): void
  └─ GitHub リポジトリとのコンテンツ同期
     ├─ pull()  — リポジトリからコンテンツを取り込み（パストラバーサル防止: R12 fix）
     └─ push()  — コンテンツをリポジトリに書き出し
```

### engines/WebhookEngine.php（実装済み）

```
WebhookEngine::handle(): void
  └─ Webhook 管理・送信
     ├─ register()    — Webhook URL 登録（プライベート IP ブロック）
     ├─ sendAsync()   — 非同期送信（DNS リバインディング防止: R19 fix）
     └─ isPrivateHost() — プライベート IP 判定（DNS 解決失敗時ブロック: R20 fix）
```

### engines/CacheEngine.php（実装済み）

```
CacheEngine::get(string $endpoint, string $key): mixed
CacheEngine::set(string $endpoint, string $key, mixed $data, int $ttl): void
CacheEngine::clear(string $endpoint): void

エンドポイント名サニタイズ: /[^a-zA-Z0-9_\-]/ を除去（R18 fix）
```

### engines/ImageOptimizer.php（実装済み）

```
ImageOptimizer::optimize(string $path, array $options): bool
  └─ JPEG/PNG/WebP の品質調整・リサイズ（GD ライブラリ使用）
```

### engines/AppContext.php ⭐ Ver.1.4-pre

```
AppContext — グローバル変数（$c, $d, $host, $lstatus, $apcredit, $hook）を静的クラスに集約
  ├─ syncFromGlobals() — 初期化完了後にグローバル変数から状態を取り込み（後方互換）
  ├─ syncToGlobals()   — AppContext の変更をグローバル変数に書き戻し（後方互換）
  ├─ config($key, $default) / setConfig($key, $value) — 設定値アクセサ
  ├─ defaults()        — デフォルト値配列の取得
  ├─ host()            — ホスト URL の取得
  ├─ loginStatus()     — ログインステータス HTML の取得
  ├─ credit()          — クレジット HTML の取得
  └─ addHook($name, $content) / getHooks($name) — フック管理
```

### engines/Logger.php ⭐ Ver.1.4-pre

```
Logger — PSR-3 互換の集中ログ管理
  ├─ init($minLevel, $logDir)  — ログ出力の初期化
  ├─ debug() / info() / warning() / error() — レベル別ログ出力
  ├─ log()          — コアログ処理（レベルフィルタ・JSON 構造化・リクエスト ID 付与）
  ├─ writeToFile()  — 日別ファイルへの書き込み（data/logs/ap-YYYY-MM-DD.log）
  ├─ rotate()       — サイズベースローテーション（5MB 上限・最大 5 世代）
  └─ cleanup()      — 古いログの削除（30 日以上経過）
```

### engines/MailerEngine.php ⭐ Ver.1.4-pre

```
MailerEngine — メール送信の抽象化
  ├─ send($to, $subject, $body, $replyTo, $extraHeaders) — リトライ付きメール送信（最大 2 回）
  ├─ sendContact($to, $name, $email, $message, $siteTitle) — お問い合わせフォーム用ヘルパー
  ├─ sanitizeHeader($value) — ヘッダインジェクション対策（CR/LF/ヌルバイト除去）
  ├─ enableTestMode() / disableTestMode() — テストモード制御
  └─ getSentMails() — テストモード時の送信メール一覧取得
```

### engines/JsEngine/

| ファイル | 役割 |
|---------|------|
| `autosize.js` | `apAutosize(el)` 関数を提供。`textarea[data-autosize]` を DOMContentLoaded で自動初期化 |
| `editInplace.js` | `.editText` スパンのクリックで textarea に変換、blur 時に Fetch API で保存（plain text 用）。`ap_action=edit_field` を送信 |
| `wysiwyg.js` | `.editRich` スパンのクリックで contenteditable + ツールバーを起動。画像 D&D/貼り付け/ボタン挿入、30秒定期自動保存、Ctrl+Enter/blur で手動保存。`ap_action=edit_field` を送信 |
| `updater.js` | アップデート確認・適用・バックアップ一覧・ロールバック・削除 UI |
| `dashboard.js` | ダッシュボード固有のインタラクション（テーマ選択変更時に `ap_action=edit_field` で保存） |
| `static_builder.js` | 静的書き出し管理 UI（差分ビルド・全件ビルド・クリア・ZIP ダウンロード） |
| `collection_manager.js` | コレクション管理 UI（CRUD・プレビュー・XSS サニタイズ済み） |
| `git_manager.js` | Git 連携 UI（Pull/Push 操作） |
| `webhook_manager.js` | Webhook 管理 UI（登録・削除・テスト送信） |
| `api_keys.js` | API キー管理 UI（生成・削除・CSRF トークン付き） |
| `ap-api-client.js` | 静的サイト向け軽量 API クライアント。`window.AP.api(action, params)` で公開 API を呼び出し。依存なし・ES5 互換 |
| `ap-search.js` | クライアントサイド検索（search-index.json を使用） |

---

## 3. リクエストフロー

```
HTTP Request
    │
    ▼
index.php
    ├─ PHP 8.2 バージョンチェック
    ├─ require engines/TemplateEngine.php
    ├─ require engines/ThemeEngine.php
    ├─ require engines/UpdateEngine.php
    ├─ require engines/AdminEngine.php
    ├─ require engines/StaticEngine.php
    ├─ require engines/ApiEngine.php
    ├─ require engines/MarkdownEngine.php
    ├─ require engines/CollectionEngine.php
    ├─ require engines/GitEngine.php
    ├─ require engines/WebhookEngine.php
    ├─ require engines/CacheEngine.php
    ├─ require engines/ImageOptimizer.php
    ├─ ob_start() ─ 出力バッファ開始
    ├─ session_start()
    ├─ migrate_from_files() ─ データ自動移行（Phase1: files/, Phase2: data/*.json）
    ├─ host() ─ URL・スラッグ解析
    ├─ AdminEngine::handle() ─ ap_action POST をディスパッチ（edit_field / upload_image / revision系）→ exit
    ├─ StaticEngine::handle() ─ ap_action=generate_static_* / build_zip / clean_static / static_status → exit
    ├─ CollectionEngine::handle() ─ コレクション管理 POST アクション → exit
    ├─ GitEngine::handle() ─ Git 連携 POST アクション → exit
    ├─ WebhookEngine::handle() ─ Webhook 管理 POST アクション → exit
    ├─ ApiEngine::handle() ─ ap_api= があれば JSON を返して exit
    ├─ handle_update_action() ─ ap_action POST があれば処理して exit
    │
    ├─ $c / $d 初期値設定
    ├─ json_read() ─ settings, auth, pages を読み込み
    ├─ foreach $c ─ 設定値マージ・認証処理・ページコンテンツ取得
    │
    ├─ ?admin ルーティング → AdminEngine::renderDashboard() → exit
    │
    ├─ AdminEngine::registerHooks() ─ admin-head に JsEngine スクリプトを登録
    │
    ▼
ThemeEngine::load()
    ├─ theme.html あり → buildContext() → TemplateEngine::render()
    └─ theme.html なし → AP-Default にフォールバック
    │
    ▼
ob_end_flush() ─ バッファ出力
```

---

## 4. データ層

### ファイルパスマッピング

| キー | パス | 読み書き関数 |
|-----|------|------------|
| サイト設定 | `data/settings/settings.json` | `json_read/write('settings.json', settings_dir())` |
| 認証情報 | `data/settings/auth.json` | `json_read/write('auth.json', settings_dir())` |
| APIキャッシュ | `data/settings/update_cache.json` | `json_read/write('update_cache.json', settings_dir())` |
| ログイン試行 | `data/settings/login_attempts.json` | `json_read/write('login_attempts.json', settings_dir())` |
| バージョン履歴 | `data/settings/version.json` | `json_read/write('version.json', settings_dir())` |
| ページ | `data/content/pages.json` | `json_read/write('pages.json', content_dir())` |
| 静的ビルド状態 | `data/settings/static_build.json` | `json_read/write('static_build.json', settings_dir())` |

### マイグレーションパス

```
Phase 1: files/{key} → data/settings/{key}.json / data/content/pages.json
Phase 2: data/{file}.json → data/settings/{file}.json / data/content/pages.json
```

Phase 2 は起動時に毎回チェックするが、移行済みの場合は `file_exists` で早期 skip される。

---

## 5. フック機構

```php
// AdminEngine::registerHooks() が admin-head フックに JsEngine スクリプトを登録
AdminEngine::registerHooks();

// AdminEngine::getAdminScripts() がフック内容を文字列で返却
// ThemeEngine::buildContext() 内で使用（theme.html 方式）
// editTags() はレガシー theme.php フォールバック用ラッパーとして維持
```

外部プラグインによるフック登録は廃止。内部コアフックのみ使用。

---

## 6. 定数

| 定数 | 値 | 説明 |
|-----|---|------|
| `AP_VERSION` | `'1.3.29'` | 現在のバージョン |
| `AP_UPDATE_URL` | GitHub API URL | 最新リリース確認先 |
| `AP_BACKUP_GENERATIONS` | `5` | 保持するバックアップ世代数 |
| `AP_REVISION_LIMIT` | `30` | リビジョン保持数上限 |

---

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

---

## 📝 変更履歴

本ドキュメントの変更履歴は、以下のファイルで一元管理されています:

👉 **[docs/DOCUMENT_CHANGELOG.md](./DOCUMENT_CHANGELOG.md#-architecturemd)** を参照してください。

---

*Adlaire License Ver.2.0 — 社内限り*
