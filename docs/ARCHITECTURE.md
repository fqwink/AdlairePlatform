# AdlairePlatform アーキテクチャ設計書

> **Ver.1.3-28**: 本ドキュメントは Ver.1.3-28 時点のアーキテクチャを記録しています。
> StaticEngine 実装完了。theme.php レガシーフォールバックを廃止。ApiEngine は Ver.1.3系で引き続き実装予定。

> 分類: 社内限り

---

## 1. 設計思想

AdlairePlatform はデータベース不要・単一エントリーポイントの **エンジン駆動型 CMS** です。

- **シンプル優先**: 依存ライブラリなし、フレームワークなし、composer 不要
- **セルフホスト**: JSON ファイルをストレージとして使用
- **エンジン分離**: 機能ごとに `engines/` ディレクトリ下の単一ファイルへ集約
- **外部プラグイン廃止**: フック機構は内部コアフックのみ（`registerCoreHooks()`）

---

## 2. ディレクトリ構成

```
AdlairePlatform/
├─ index.php                    # エントリーポイント（Router・ユーティリティ・レガシーラッパー）
├─ .htaccess                    # URL rewrite・セキュリティヘッダー・アクセス制限
├─ docs/
│  ├─ ARCHITECTURE.md           # 本ドキュメント
│  ├─ VERSIONING.md             # バージョン規則
│  ├─ STATIC_GENERATOR.md       # StaticEngine 設計草稿
│  ├─ HEADLESS_CMS.md           # ApiEngine 設計草稿
│  ├─ nginx.conf.example        # Nginx 設定リファレンス
│  └─ Licenses/
│     └─ LICENSE_Ver.2.0.md
├─ engines/
│  ├─ AdminEngine.php           # 認証・CSRF・管理アクション・ダッシュボード
│  ├─ AdminEngine/
│  │  ├─ dashboard.html         # ダッシュボードテンプレート（テーマ非依存）
│  │  └─ dashboard.css          # ダッシュボード専用スタイル
│  ├─ TemplateEngine.php        # 軽量テンプレートエンジン（{{var}}, {{{raw}}}, #if, #each）
│  ├─ ThemeEngine.php           # テーマ検証・読み込み・コンテキスト構築
│  ├─ UpdateEngine.php          # アップデート・バックアップ・ロールバック
│  ├─ StaticEngine.php          # 静的書き出し・差分ビルド・アセット管理
│  ├─ ApiEngine.php             # 公開 REST API（ヘッドレス CMS）（計画）
│  └─ JsEngine/
│     ├─ autosize.js            # テキストエリア自動リサイズ
│     ├─ editInplace.js         # インプレイス編集（バニラJS・plain text）
│     ├─ dashboard.js           # ダッシュボード固有のインタラクション

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
├─ static/                      # StaticEngine が書き出す静的 HTML（計画）
│  ├─ .htaccess                 # Static-First: HTML 優先・PHP フォールバック
│  ├─ index.html                # 書き出し済みページ（例）
│  └─ uploads/                  # uploads/ のミラー（filemtime 差分コピー）
└─ backup/                      # 自動バックアップ（最大5世代）
   └─ YYYYMMDD_HHmmss/
      └─ meta.json              # バックアップメタ情報
```

---

## 3. ファイル責務

### index.php（エントリーポイント）

| 機能グループ | 関数 | 説明 |
|------------|------|------|
| 起動制御 | ─ | PHP バージョン確認・定数定義・エンジン require（TemplateEngine, ThemeEngine, UpdateEngine, AdminEngine） |
| ルーティング | `host()`, `getSlug()` | URL 解析・スラッグ生成・`?admin` ダッシュボードルーティング |
| データ層 | `json_read()`, `json_write()`, `data_dir()`, `settings_dir()`, `content_dir()` | JSON ファイル読み書き |
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
  ├─ processRawVars()  — {{{var}}} 生 HTML 出力
  ├─ processVars()     — {{var}} エスケープ出力（htmlspecialchars）
  └─ warnUnprocessed() — 未処理テンプレートタグを error_log() で警告
```

### engines/ThemeEngine.php

```
ThemeEngine::load(string $themeSelect): void
  ├─ テーマ名バリデーション（英数字・-・_のみ）
  ├─ theme.html を TemplateEngine::render() でレンダリング
  └─ theme.html がなければ AP-Default にフォールバック

ThemeEngine::buildContext(): array
  ├─ 動的 CMS 用テンプレートコンテキストを構築（$c, $host 等からマッピング）
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

### engines/ApiEngine.php（計画・設計確定 Ver.0.3-1 — Ver.1.3系で実装予定）

```
ApiEngine::handle(): void
  └─ ?ap_api= があれば JSON を返して exit
     ├─ 'pages'    → getPages()
     ├─ 'page'     → slug バリデーション → getPage($slug)
     ├─ 'settings' → getSettings()
     ├─ 'search'   → q バリデーション（1〜100文字） → search($q)
     ├─ 'contact'  → POST 検証 → checkContactRate() → sendContact()
     └─ default    → jsonError('不明な API エンドポイントです', 400)

ApiEngine::getPages(): array
  └─ 全ページの slug + makePreview() によるプレビュー（120文字）を返す

ApiEngine::getPage(string $slug): array
  └─ 単一ページの slug + content を返す（404: ページ不在）

ApiEngine::getSettings(): array
  └─ title / description / keywords のみ返す（auth.json・contact_email は含めない）

ApiEngine::search(string $query): array
  └─ mb_stripos による全文検索（マッチ箇所前後のプレビュー付き）

ApiEngine::sendContact(): array
  ├─ ハニーポット検出（website フィールド・ボットには 200 を装う）
  ├─ name（1〜100文字）・email（FILTER_VALIDATE_EMAIL）・message（1〜5000文字）バリデーション
  ├─ checkContactRate() — IP レート制限（5回/15分・識別キー: contact_<IP>）
  ├─ settings.json の contact_email を宛先として取得
  └─ PHP mail() で送信（メールヘッダインジェクション対策: 改行除去）

ApiEngine::jsonResponse(bool $ok, mixed $data): void
  └─ JSON_HEX_TAG | JSON_HEX_AMP で encode して exit

ApiEngine::jsonError(string $message, int $status = 400): void
  └─ HTTP ステータスコード（400/404/405/429/500）付きでエラーレスポンスを出力して exit

ApiEngine::checkContactRate(): void
  └─ login_attempts.json を流用した IP ベースレート制限（contact_<IP> キー）

ApiEngine::makePreview(string $html, int $length = 120): string
  └─ strip_tags → mb_substr で先頭 $length 文字を切り出し
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
| `ap-api-client.js` | 静的サイト向け軽量 API クライアント（計画・設計確定）。`window.AP.api(action, params)` で公開 API を呼び出し、`<form class="ap-contact">` を自動バインド。API オリジンは `currentScript.src` から自動解決（Static-Only モード対応）。依存なし・ES5 互換 |

---

## 4. リクエストフロー

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
    ├─ require engines/ApiEngine.php    ─ （計画）
    ├─ ob_start() ─ 出力バッファ開始
    ├─ session_start()
    ├─ migrate_from_files() ─ データ自動移行（Phase1: files/, Phase2: data/*.json）
    ├─ host() ─ URL・スラッグ解析
    ├─ AdminEngine::handle() ─ ap_action POST をディスパッチ（edit_field / upload_image / revision系）→ exit
    ├─ StaticEngine::handle() ─ ap_action=generate_static_* / build_zip / clean_static / static_status → exit
    ├─ ApiEngine::handle() ─ ap_api= があれば JSON を返して exit（計画）
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

## 5. データ層

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

## 6. セキュリティ

| 機能 | 実装 |
|-----|------|
| パスワードハッシュ | bcrypt (`PASSWORD_BCRYPT`) |
| セッション | HttpOnly + SameSite=Lax |
| CSRF | `random_bytes(32)` トークン、POST と X-CSRF-TOKEN ヘッダーで検証 |
| XSS | `h()` = `htmlspecialchars(ENT_QUOTES)` による出力エスケープ |
| レート制限 | 5回失敗で15分ロックアウト（IP ベース、`login_attempts.json`） |
| ディレクトリ保護 | `.htaccess` で `data/`, `backup/`, `files/`, `engines/*.php` を 403 |
| 画像アップロード | MIME 検証（`finfo`）、2MB 制限、ランダムファイル名（`random_bytes(12)`）、`uploads/` 内 PHP 実行不可 |
| CSP | `script-src 'self'` を含む包括的 Content-Security-Policy |
| engines/ 保護 | `RedirectMatch 403 ^.*/engines/.*\.php$` で直接アクセス禁止 |

---

## 7. フック機構

```php
// AdminEngine::registerHooks() が admin-head フックに JsEngine スクリプトを登録
AdminEngine::registerHooks();

// AdminEngine::getAdminScripts() がフック内容を文字列で返却
// ThemeEngine::buildContext() 内で使用（theme.html 方式）
// editTags() はレガシー theme.php フォールバック用ラッパーとして維持
```

外部プラグインによるフック登録は廃止。内部コアフックのみ使用。

---

## 8. 定数

| 定数 | 値 | 説明 |
|-----|---|------|
| `AP_VERSION` | `'1.3.28'` | 現在のバージョン |
| `AP_UPDATE_URL` | GitHub API URL | 最新リリース確認先 |
| `AP_BACKUP_GENERATIONS` | `5` | 保持するバックアップ世代数 |

---

*Adlaire License Ver.2.0 — 社内限り*
