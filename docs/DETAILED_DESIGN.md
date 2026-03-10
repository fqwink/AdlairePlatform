# AdlairePlatform — 詳細設計書

<!-- ⚠️ 削除禁止: 本ドキュメントは実装詳細に関する最上位ドキュメントです -->

> **ドキュメントバージョン**: Ver.0.1-1
> **ステータス**: 🔧 開発中（Ver.1.4-pre）
> **作成日**: 2026-03-10
> **最終更新**: 2026-03-10
> **所有者**: Adlaire Group

> **関連ドキュメント**:
> - 基本設計書・基本方針 → [AdlairePlatform_Design.md](AdlairePlatform_Design.md)
> - エンジン駆動モデルアーキテクチャ基本設計書 → [ARCHITECTURE.md](ARCHITECTURE.md)
> - 機能一覧・関数リファレンス → [features.md](features.md)
> - 本ドキュメントは **実装レベルの技術仕様（詳細設計）** を定めています。

---

## 目次

1. [概要](#1-概要)
2. [エンジン実装仕様](#2-エンジン実装仕様)
   - 2.1 [index.php（エントリーポイント）](#21-indexphpエントリーポイント)
   - 2.2 [AdminEngine](#22-adminengine)
   - 2.3 [TemplateEngine](#23-templateengine)
   - 2.4 [ThemeEngine](#24-themeengine)
   - 2.5 [UpdateEngine](#25-updateengine)
   - 2.6 [StaticEngine](#26-staticengine)
   - 2.7 [ApiEngine](#27-apiengine)
   - 2.8 [CollectionEngine](#28-collectionengine)
   - 2.9 [MarkdownEngine](#29-markdownengine)
   - 2.10 [GitEngine](#210-gitengine)
   - 2.11 [WebhookEngine](#211-webhookengine)
   - 2.12 [CacheEngine](#212-cacheengine)
   - 2.13 [ImageOptimizer](#213-imageoptimizer)
   - 2.14 [AppContext](#214-appcontext)
   - 2.15 [Logger](#215-logger)
   - 2.16 [MailerEngine](#216-mailerengine)
   - 2.17 [JsEngine](#217-jsengine)
3. [データ層実装仕様](#3-データ層実装仕様)
4. [セキュリティ実装仕様](#4-セキュリティ実装仕様)
5. [フック機構実装](#5-フック機構実装)
6. [定数定義](#6-定数定義)
7. [変更履歴](#7-変更履歴)

---

## 1. 概要

本ドキュメントは AdlairePlatform の **詳細設計書** です。
基本設計（[AdlairePlatform_Design.md](AdlairePlatform_Design.md)）およびアーキテクチャ基本設計（[ARCHITECTURE.md](ARCHITECTURE.md)）で定められた方針に基づく、実装レベルの技術仕様を記録します。

- **エンジン実装仕様**: 全 15 エンジン + JsEngine の関数シグネチャ・内部フロー
- **データ層実装仕様**: ファイルパスマッピング・マイグレーションパス
- **セキュリティ実装仕様**: 脅威対策マトリクス（脅威 → 対策 → 実装場所）
- **フック機構実装**: コアフックの実装パターン
- **定数定義**: アプリケーション定数の値と用途

---

## 2. エンジン実装仕様

### 2.1 index.php（エントリーポイント）

| 機能グループ | 関数 | 説明 |
|------------|------|------|
| 起動制御 | ─ | PHP バージョン確認・定数定義・全 15 エンジン require・Logger 初期化 |
| ルーティング | `host()`, `getSlug()` | URL 解析・スラッグ生成・`?admin` ダッシュボードルーティング |
| データ層 | `json_read()`, `json_write()`, `data_dir()`, `settings_dir()`, `content_dir()` | JSON ファイル読み書き（JsonCache 付き） |
| キャッシュ | `JsonCache::get/set/invalidate/clear()` | リクエスト内 JSON I/O キャッシュ ⭐ Ver.1.4-pre |
| マイグレーション | `migrate_from_files()` | 旧データ構造からの自動移行 |
| ユーティリティ | `h()` | HTMLエスケープ |
| ラッパー | `is_loggedin()`, `csrf_token()`, `verify_csrf()` | AdminEngine に委譲 |

### 2.2 AdminEngine

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

### 2.3 TemplateEngine

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

### 2.4 ThemeEngine

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

### 2.5 UpdateEngine

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

### 2.6 StaticEngine

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

### 2.7 ApiEngine

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

### 2.8 CollectionEngine

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

### 2.9 MarkdownEngine

```
MarkdownEngine::parse(string $markdown): array
  ├─ parseFrontmatter() — YAML フロントマター解析（重複キーは最初の値を優先: R8 fix）
  └─ toHtml() — Markdown → HTML 変換

MarkdownEngine::parseYamlValue(string $val): mixed
  └─ YAML 値のパース（文字列・数値・真偽値・配列）
```

### 2.10 GitEngine

```
GitEngine::handle(): void
  └─ GitHub リポジトリとのコンテンツ同期
     ├─ pull()  — リポジトリからコンテンツを取り込み（パストラバーサル防止: R12 fix）
     └─ push()  — コンテンツをリポジトリに書き出し
```

### 2.11 WebhookEngine

```
WebhookEngine::handle(): void
  └─ Webhook 管理・送信
     ├─ register()    — Webhook URL 登録（プライベート IP ブロック）
     ├─ sendAsync()   — 非同期送信（DNS リバインディング防止: R19 fix）
     └─ isPrivateHost() — プライベート IP 判定（DNS 解決失敗時ブロック: R20 fix）
```

### 2.12 CacheEngine

```
CacheEngine::get(string $endpoint, string $key): mixed
CacheEngine::set(string $endpoint, string $key, mixed $data, int $ttl): void
CacheEngine::clear(string $endpoint): void

エンドポイント名サニタイズ: /[^a-zA-Z0-9_\-]/ を除去（R18 fix）
```

### 2.13 ImageOptimizer

```
ImageOptimizer::optimize(string $path, array $options): bool
  └─ JPEG/PNG/WebP の品質調整・リサイズ（GD ライブラリ使用）
```

### 2.14 AppContext ⭐ Ver.1.4-pre

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

### 2.15 Logger ⭐ Ver.1.4-pre

```
Logger — PSR-3 互換の集中ログ管理
  ├─ init($minLevel, $logDir)  — ログ出力の初期化
  ├─ debug() / info() / warning() / error() — レベル別ログ出力
  ├─ log()          — コアログ処理（レベルフィルタ・JSON 構造化・リクエスト ID 付与）
  ├─ writeToFile()  — 日別ファイルへの書き込み（data/logs/ap-YYYY-MM-DD.log）
  ├─ rotate()       — サイズベースローテーション（5MB 上限・最大 5 世代）
  └─ cleanup()      — 古いログの削除（30 日以上経過）
```

### 2.16 MailerEngine ⭐ Ver.1.4-pre

```
MailerEngine — メール送信の抽象化
  ├─ send($to, $subject, $body, $replyTo, $extraHeaders) — リトライ付きメール送信（最大 2 回）
  ├─ sendContact($to, $name, $email, $message, $siteTitle) — お問い合わせフォーム用ヘルパー
  ├─ sanitizeHeader($value) — ヘッダインジェクション対策（CR/LF/ヌルバイト除去）
  ├─ enableTestMode() / disableTestMode() — テストモード制御
  └─ getSentMails() — テストモード時の送信メール一覧取得
```

### 2.17 JsEngine

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

## 3. データ層実装仕様

### 3.1 ファイルパスマッピング

| キー | パス | 読み書き関数 |
|-----|------|------------|
| サイト設定 | `data/settings/settings.json` | `json_read/write('settings.json', settings_dir())` |
| 認証情報 | `data/settings/auth.json` | `json_read/write('auth.json', settings_dir())` |
| APIキャッシュ | `data/settings/update_cache.json` | `json_read/write('update_cache.json', settings_dir())` |
| ログイン試行 | `data/settings/login_attempts.json` | `json_read/write('login_attempts.json', settings_dir())` |
| バージョン履歴 | `data/settings/version.json` | `json_read/write('version.json', settings_dir())` |
| ページ | `data/content/pages.json` | `json_read/write('pages.json', content_dir())` |
| 静的ビルド状態 | `data/settings/static_build.json` | `json_read/write('static_build.json', settings_dir())` |

### 3.2 マイグレーションパス

```
Phase 1: files/{key} → data/settings/{key}.json / data/content/pages.json
Phase 2: data/{file}.json → data/settings/{file}.json / data/content/pages.json
```

Phase 2 は起動時に毎回チェックするが、移行済みの場合は `file_exists` で早期 skip される。

---

## 4. セキュリティ実装仕様

### 4.1 脅威対策マトリクス

| 脅威 | 対策 | 実装場所 |
|------|------|---------|
| XSS | `h()` 関数による出力エスケープ | `index.php` / `AdminEngine` 全出力箇所 |
| CSRF | 32バイトトークン + `empty()` + `hash_equals()` | `AdminEngine::verifyCsrf()` |
| セッションハイジャック | `session_regenerate_id(true)` | `AdminEngine::login()` |
| セッション固定 | HttpOnly + SameSite=Lax クッキー | `index.php` |
| パストラバーサル | 正規表現バリデーション | フィールド名・テーマ名・バックアップ名 |
| クリックジャッキング | `X-Frame-Options: SAMEORIGIN` | `.htaccess` / server block |
| MIME スニッフィング | `X-Content-Type-Options: nosniff` | `.htaccess` / server block |
| ディレクトリ列挙 | `Options -Indexes` | `.htaccess` / `autoindex off` |
| データ漏洩 | 保護ディレクトリへのアクセス拒否 | `.htaccess` / server block |
| ブルートフォース | 5回失敗で15分ロックアウト（IP ベース） | `check_login_rate()` |
| パスワード平文保存 | bcrypt ハッシュ化 | `savePassword()` |
| コンテンツインジェクション | CSP ヘッダー（`default-src 'self'`） | `.htaccess` / server block |
| 画像アップロード悪用 | MIME 検証・PHP 実行禁止・ランダムファイル名 | `upload_image()` |
| SSRF | プライベート IP ブロック・DNS リバインディング防止 | `WebhookEngine` |
| JSON-LD XSS | `JSON_UNESCAPED_SLASHES` 除去 | `ThemeEngine` |
| メールヘッダインジェクション | Subject の改行除去 | `ApiEngine` / `MailerEngine` |
| CORS キャッシュポイズニング | `Vary: Origin` ヘッダー | `ApiEngine` |
| オープンリダイレクト | リダイレクト先 URL 検証 | `AdminEngine` |
| API 認証 | Bearer トークン + bcrypt | `ApiEngine` |
| engines/ 直接アクセス | `RedirectMatch 403 ^.*/engines/.*\.php$` | `.htaccess` |

---

## 5. フック機構実装

```php
// AdminEngine::registerHooks() が admin-head フックに JsEngine スクリプトを登録
AdminEngine::registerHooks();

// AdminEngine::getAdminScripts() がフック内容を文字列で返却
// ThemeEngine::buildContext() 内で使用（theme.html 方式）
// editTags() はレガシー theme.php フォールバック用ラッパーとして維持
```

---

## 6. 定数定義

| 定数 | 値 | 説明 |
|-----|---|------|
| `AP_VERSION` | `'1.4-pre'` | 現在のバージョン |
| `AP_UPDATE_URL` | GitHub API URL | 最新リリース確認先 |
| `AP_BACKUP_GENERATIONS` | `5` | 保持するバックアップ世代数 |
| `AP_REVISION_LIMIT` | `30` | リビジョン保持数上限 |

---

## 7. 変更履歴

| バージョン | 日付 | 変更内容 | 担当 |
|------------|------|----------|------|
| Ver.0.1-1 | 2026-03-10 | 初版。ARCHITECTURE.md §3/§5/§7/§8 および Design.md §6.2 から実装仕様を統合し新規作成 | Adlaire Group |

---

*本ドキュメントは AdlairePlatform の公式詳細設計書です。*
*内容は Adlaire Group の承認なく変更・転載することを禁じます。*
