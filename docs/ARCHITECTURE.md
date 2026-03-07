# AdlairePlatform アーキテクチャ設計書

> バージョン: Ver.1.2-17
> 最終更新: 2026-03-07
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
├─ index.php                    # エントリーポイント（Router・Auth・Content・Hook）
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
│  ├─ ThemeEngine.php           # テーマ検証・読み込み
│  ├─ UpdateEngine.php          # アップデート・バックアップ・ロールバック
│  └─ JsEngine/
│     ├─ autosize.js            # テキストエリア自動リサイズ
│     ├─ editInplace.js         # インプレイス編集（バニラJS・plain text）
│     ├─ wysiwyg.js             # WYSIWYG エディタ（Ph2-1・依存なし）
│     └─ updater.js             # アップデートUI
├─ themes/
│  ├─ AP-Default/
│  │  ├─ theme.php
│  │  └─ style.css
│  └─ AP-Adlaire/
│     ├─ theme.php
│     └─ style.css
├─ data/
│  ├─ settings/
│  │  ├─ settings.json          # サイト設定
│  │  ├─ auth.json              # 認証情報（bcrypt）
│  │  ├─ update_cache.json      # GitHub API キャッシュ（1時間）
│  │  ├─ login_attempts.json    # ログイン試行記録（レート制限）
│  │  └─ version.json           # アップデート履歴
│  └─ content/
│     └─ pages.json             # ページコンテンツ
└─ backup/                      # 自動バックアップ（最大5世代）
   └─ YYYYMMDD_HHmmss/
      └─ meta.json              # バックアップメタ情報
```

---

## 3. ファイル責務

### index.php（エントリーポイント）

| 機能グループ | 関数 | 説明 |
|------------|------|------|
| 起動制御 | ─ | PHP バージョン確認・定数定義・エンジン require |
| ルーティング | `host()`, `getSlug()` | URL 解析・スラッグ生成 |
| 認証 | `login()`, `savePassword()`, `is_loggedin()` | セッション・bcrypt 認証 |
| レート制限 | `check_login_rate()`, `record_login_failure()`, `clear_login_rate()` | IP ベースのログイン試行制限 |
| CSRF | `csrf_token()`, `verify_csrf()` | トークン生成・検証 |
| コンテンツ | `content()`, `menu()`, `edit()` | 表示・インライン保存 |
| フック | `registerCoreHooks()`, `editTags()` | JsEngine スクリプト注入 |
| データ層 | `json_read()`, `json_write()`, `data_dir()`, `settings_dir()`, `content_dir()` | JSON ファイル読み書き |
| マイグレーション | `migrate_from_files()` | 旧データ構造からの自動移行 |
| UI | `settings()`, `h()` | 管理パネル出力・HTMLエスケープ |

### engines/ThemeEngine.php

```
ThemeEngine::load(string $themeSelect): void
  ├─ テーマ名バリデーション（英数字・-・_のみ）
  ├─ themes/{name}/theme.php の存在確認
  ├─ 存在しない場合は AP-Default にフォールバック
  └─ require でテーマをロード

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

### engines/JsEngine/

| ファイル | 役割 |
|---------|------|
| `autosize.js` | `apAutosize(el)` 関数を提供。`textarea[data-autosize]` を DOMContentLoaded で自動初期化 |
| `editInplace.js` | `.editText` スパンのクリックで textarea に変換、blur 時に Fetch API で保存（plain text 用） |
| `wysiwyg.js` | `.editRich` スパンのクリックで contenteditable + ツールバーを起動、Ctrl+Enter/blur で保存（HTML コンテンツ用） |
| `updater.js` | アップデート確認・適用・バックアップ一覧・ロールバック・削除 UI |

---

## 4. リクエストフロー

```
HTTP Request
    │
    ▼
index.php
    ├─ PHP 8.2 バージョンチェック
    ├─ require engines/ThemeEngine.php
    ├─ require engines/UpdateEngine.php
    ├─ ob_start() ─ 出力バッファ開始
    ├─ session_start()
    ├─ migrate_from_files() ─ データ自動移行（Phase1: files/, Phase2: data/*.json）
    ├─ host() ─ URL・スラッグ解析
    ├─ handle_update_action() ─ ap_action POST があれば処理して exit
    ├─ edit() ─ fieldname POST があれば保存して exit
    │
    ├─ $c / $d 初期値設定
    ├─ json_read() ─ settings, auth, pages を読み込み
    ├─ foreach $c ─ 設定値マージ・認証処理・ページコンテンツ取得
    ├─ registerCoreHooks() ─ admin-head に JsEngine スクリプトを登録
    │
    ▼
ThemeEngine::load()
    └─ themes/{themeSelect}/theme.php
            ├─ HTML head 出力（editTags() → JsEngine スクリプト）
            ├─ content() ─ ページコンテンツ出力（ログイン時は editRich span → WYSIWYG）
            ├─ menu() ─ ナビゲーション出力
            └─ settings() ─ 管理パネル出力（ログイン時のみ）
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
| CSP | `script-src 'self'` を含む包括的 Content-Security-Policy |
| engines/ 保護 | `RedirectMatch 403 ^.*/engines/.*\.php$` で直接アクセス禁止 |

---

## 7. フック機構

```php
// registerCoreHooks() が admin-head フックに JsEngine スクリプトを登録
$hook['admin-head'][] = "<script src='engines/JsEngine/autosize.js'>";
$hook['admin-head'][] = "<script src='engines/JsEngine/editInplace.js'>";
$hook['admin-head'][] = "<script src='engines/JsEngine/wysiwyg.js'>";
$hook['admin-head'][] = "<script src='engines/JsEngine/updater.js'>";

// editTags() がログイン時のみ <head> 内で echo
function editTags() {
    if (!is_loggedin()) return;
    foreach ($hook['admin-head'] as $o) { echo $o; }
}
```

外部プラグインによるフック登録は廃止。内部コアフックのみ使用。

---

## 8. 定数

| 定数 | 値 | 説明 |
|-----|---|------|
| `AP_VERSION` | `'1.2.0'` | 現在のバージョン（SemVer） |
| `AP_UPDATE_URL` | GitHub API URL | 最新リリース確認先 |
| `AP_BACKUP_GENERATIONS` | `5` | 保持するバックアップ世代数 |

---

*Adlaire License Ver.2.0 — 社内限り*
