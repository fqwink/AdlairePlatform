# AdlairePlatform — 仕様リファレンス

<!-- ⚠️ 削除可能: 本ドキュメントは機能仕様のリファレンスです -->

> **ドキュメントバージョン**: Ver.1.0-1  
> **ステータス**: 🔧 開発中（Ver.1.4-pre）  
> **作成日**: 2026-03-10  
> **最終更新**: 2026-03-10（新規作成）  
> **所有者**: Adlaire Group  
> **バージョニング規則**: [AFE/VERSIONING.md](https://github.com/fqwink/AdlaireGroup-Documents-Repository/blob/main/AFE/VERSIONING.md)

> **関連ドキュメント**:  
> - 設計方針: [AdlairePlatform_Design.md](AdlairePlatform_Design.md)  
> - アーキテクチャ: [ARCHITECTURE.md](ARCHITECTURE.md)  
> - セキュリティ: [SECURITY_POLICY.md](SECURITY_POLICY.md)  
> - エンジン技術設計: [ENGINE_DESIGN.md](ENGINE_DESIGN.md)

---

## 目次

1. [コンテンツ管理仕様](#1-コンテンツ管理仕様)
2. [テーマエンジン仕様](#2-テーマエンジン仕様)
3. [フロントエンド仕様](#3-フロントエンド仕様)
4. [WYSIWYGエディタ仕様](#4-wysiwygエディタ仕様)
5. [アップデートエンジン仕様](#5-アップデートエンジン仕様)
6. [管理エンジン・ダッシュボード仕様](#6-管理エンジンダッシュボード仕様)
7. [静的サイト生成エンジン仕様](#7-静的サイト生成エンジン仕様)
8. [ヘッドレス CMS / API エンジン仕様](#8-ヘッドレス-cms--api-エンジン仕様)
9. [フック機構仕様](#9-フック機構仕様)
10. [データ層仕様](#10-データ層仕様)
11. [データ仕様](#11-データ仕様)
12. [変更履歴](#12-変更履歴)

---

## 1. コンテンツ管理仕様

### 1.1 データストレージ

| ファイル | 用途 | 自動生成 |
|----------|------|----------|
| `data/settings/settings.json` | サイト設定（タイトル・説明・テーマ等） | ✅ |
| `data/content/pages.json` | 全ページコンテンツ（スラッグをキーとする） | ✅ |
| `data/settings/auth.json` | 認証情報（bcrypt ハッシュ） | ✅ |
| `data/settings/update_cache.json` | アップデート確認キャッシュ（TTL: 1時間） | ✅ |
| `data/settings/version.json` | アップデート履歴 | ✅ |
| `data/settings/login_attempts.json` | ログイン試行記録（レート制限） | ✅ |
| `data/settings/static_build.json` | 静的ビルド差分状態 | ✅ |

### 1.2 ページ管理

- URL スラッグはページ識別子として使用する
- スラッグ生成: スペース→ハイフン変換、`mb_convert_case` による小文字化（UTF-8対応）
- 存在しないスラッグへのアクセス: HTTP 404 を返す
  - ログイン中: 新規ページ作成画面を表示
  - 非ログイン: 404 コンテンツを表示
- ページコンテンツは `pages.json` にスラッグをキーとして格納する

### 1.3 インプレイス編集

- ログイン中にのみ編集可能な `<span>` タグをコンテンツに付与する
- `class="editText"` によってバニラ JS がバインドする（jQuery 依存なし）
- リッチテキスト対象要素には `class="editRich"` を追加する（WYSIWYGエディタ起動）
- 編集内容は `fetch` API により非同期保存する
- 保存 API エンドポイント: `POST /`（`ap_action=edit_field` + フィールド名を POST パラメータとして送信）

### 1.4 設定管理

管理パネルから以下の設定を編集可能とする:

| 設定キー | 内容 | 型 |
|----------|------|----|
| `title` | サイトタイトル | string |
| `description` | サイト説明文 | string |
| `keywords` | メタキーワード | string |
| `copyright` | 著作権表記 | string |
| `menu` | メニュー項目（改行区切り） | string |
| `themeSelect` | 使用テーマ名 | string |

### 1.5 画像アップロード

- エンドポイント: `POST /`（`ap_action=upload_image`）
- 認証必須・CSRF 検証必須
- 許可形式: JPEG / PNG / GIF / WebP（`finfo` による MIME 検証）
- 最大サイズ: 2MB
- 保存先: `uploads/` ディレクトリ（PHP 実行不可・ランダムファイル名）

---

## 2. テーマエンジン仕様

### 2.1 テーマ構造

```
themes/
└── <テーマ名>/
    ├── theme.html      （テンプレートエンジン方式・PHP フリー）
    └── style.css       （必須: スタイルシート）
```

> **注**: `theme.php`（レガシー PHP テーマ方式）は Ver.1.3-28 で廃止されました。  
> `settings.html`（管理者設定パネル パーシャル）は Ver.1.3-27 でダッシュボードに統合され、廃止されました。

### 2.2 テーマ仕様

- テーマ名の許可文字: `[a-zA-Z0-9_-]`（パストラバーサル防止）
- テーマの自動検出: `themes/` ディレクトリを走査し、有効なテーマを一覧表示
- テーマ切替: 管理パネルからリアルタイムに切替可能
- デフォルトテーマ: `AP-Default`（存在しないテーマが指定された場合のフォールバック）
- エンジン: `engines/ThemeEngine.php`（`ThemeEngine::load()` / `ThemeEngine::listThemes()` / `ThemeEngine::buildContext()` / `ThemeEngine::buildStaticContext()`）
- テンプレートエンジン: `engines/TemplateEngine.php`（`{{var}}` / `{{{raw}}}` / `{{#if}}` / `{{#each}}` / `{{> partial}}` / `{{obj.prop}}` ドット記法 / `{{var|filter}}` フィルター）

### 2.3 テンプレート構文（theme.html 方式）

| 構文 | 説明 | 例 |
|------|------|----|
| `{{variable}}` | エスケープ出力（`htmlspecialchars`） | `{{title}}` |
| `{{{variable}}}` | 生 HTML 出力 | `{{{content}}}` |
| `{{#if var}}...{{else}}...{{/if}}` | 条件分岐（`!var` で否定可） | `{{#if admin}}...{{/if}}` |
| `{{#each items}}...{{/each}}` | ループ（配列要素のキーが変数として使用可能） | `{{#each menu_items}}...{{/each}}` |
| `{{> partial}}` | 部分テンプレートの読み込み（同ディレクトリの `partial.html`） | `{{> settings}}` |
| `{{obj.prop}}` | ネストされたプロパティへのドット記法アクセス（`$ctx['obj']['prop']`） | `{{user.name}}` |
| `{{var\|filter}}` | フィルター適用（値を変換して出力） | `{{name\|upper}}` |
| `{{var\|filter1\|filter2}}` | フィルターチェーン（複数フィルターを左から順に適用） | `{{name\|trim\|upper}}` |

### 2.4 組み込みフィルター

| フィルター | 説明 | 例 |
|-----------|------|----|
| `upper` | 大文字に変換（`mb_strtoupper`） | `{{name\|upper}}` → `TANAKA` |
| `lower` | 小文字に変換（`mb_strtolower`） | `{{name\|lower}}` → `tanaka` |
| `capitalize` | 先頭文字を大文字に変換 | `{{name\|capitalize}}` → `Tanaka` |
| `trim` | 前後の空白を除去 | `{{name\|trim}}` |
| `nl2br` | 改行を `<br>` に変換 | `{{desc\|nl2br}}` |
| `length` | 文字列長または配列要素数を返す | `{{items\|length}}` → `5` |
| `truncate:N` | N 文字で切り詰め、末尾に `...` を付与 | `{{title\|truncate:20}}` |
| `default:value` | 値が空の場合にデフォルト値を使用 | `{{name\|default:ゲスト}}` |

### 2.5 ループ内メタ変数

| 変数 | 型 | 説明 |
|------|-----|------|
| `{{@index}}` | int | 現在のループインデックス（0 始まり） |
| `{{@first}}` | bool | 最初の要素で `true` |
| `{{@last}}` | bool | 最後の要素で `true` |

### 2.6 未処理タグ検出

テンプレートエンジンはレンダリング後に未処理のテンプレートタグ（`{{...}}`）を検出し、`error_log()` で警告を出力します。テーマ開発時のデバッグに活用できます。

### 2.7 パーシャル（部分テンプレート）

- パーシャルはテーマディレクトリ内の `*.html` ファイルを参照します（例: `{{> settings}}` → `themes/<テーマ名>/settings.html`）
- 循環参照防止: 最大ネスト深度 10

### 2.8 テンプレートコンテキスト変数

| 変数名 | 型 | 説明 |
|--------|-----|------|
| `title` | string | サイトタイトル |
| `page` | string | 現在のページスラッグ |
| `host` | string | サイトベース URL |
| `themeSelect` | string | 現在のテーマ名 |
| `description` | string | サイト説明文 |
| `keywords` | string | メタキーワード |
| `admin` | bool | ログイン状態（管理者 UI の表示制御） |
| `csrf_token` | string | CSRF トークン |
| `admin_scripts` | string | 管理スクリプトタグ（HTML） |
| `content` | string | ページコンテンツ HTML |
| `subside` | string | サイドバーコンテンツ HTML |
| `copyright` | string | 著作権表記 |
| `login_status` | string | ログイン/ログアウトリンク HTML |
| `credit` | string | Adlaire クレジット HTML |
| `menu_items` | array | メニュー項目（各要素: `slug`, `label`, `active`） |

### 2.9 管理者ログイン時のみ追加される変数（ダッシュボード用）

> 以下の変数は `AdminEngine::buildDashboardContext()` がダッシュボードテンプレート用に構築します。  
> フロントエンドテーマテンプレートでは使用しません。

| 変数名 | 型 | 説明 |
|--------|-----|------|
| `migrate_warning` | bool | パスワード移行警告の表示フラグ |
| `theme_select_html` | string | テーマ選択 `<select>` HTML |
| `menu_raw` | string | メニュー生テキスト（編集用） |
| `settings_fields` | array | 設定フィールド（各要素: `key`, `default_value`, `value`） |
| `ap_version` | string | 現在の AP バージョン |
| `pages` | array | ページ一覧（各要素: `slug`, `preview`） |
| `has_pages` | bool | ページが1件以上存在するか |
| `php_version` | string | PHP バージョン |
| `disk_free` | string | ディスク空き容量 |

### 2.10 同梱テーマ

| テーマ名 | 用途 |
|----------|------|
| `AP-Default` | 標準・汎用テーマ |
| `AP-Adlaire` | Adlaire デザインテーマ |

### 2.11 テーマ内利用可能関数（theme.php レガシー方式のみ）

| 関数 | 説明 |
|------|------|
| `content(string $id, string $content)` | コンテンツ出力（ログイン中は編集可能タグ付与）— AdminEngine に委譲 |
| `menu()` | ナビゲーションメニュー出力 |
| `h(string $s): string` | XSS エスケープ出力 |
| `editTags()` | 管理用スクリプト・スタイルの出力 — AdminEngine に委譲 |
| `is_loggedin(): bool` | ログイン状態の確認 — AdminEngine に委譲 |

> **注**: `theme.html` 方式ではこれらの関数は使用しません。代わりにテンプレート変数（`{{{content}}}`, `{{#each menu_items}}` 等）でデータにアクセスします。

---

## 3. フロントエンド仕様

### 3.1 基本方針

- **jQuery は使用しない（廃止済み）**
- DOM 操作・イベント処理はすべてバニラ JavaScript（ES5+）で実装する
- HTTP 通信は `fetch` API を使用する
- テキストエリアの自動拡張は `autosize` ライブラリを使用する（セルフホスト）

### 3.2 インプレイス編集（バニラ JS 仕様）

```
【イベントバインド】
  document.querySelectorAll('.editText') でバインド
  → click イベントで編集モードへ移行（textarea 変換）

【保存処理】
  fetch('/index.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ fieldname: value, csrf: token })
  })

【autosize 適用】
  apAutosize(textarea)  // engines/JsEngine/autosize.js
```

### 3.3 AJAX 保存仕様

- 保存成功時: 画面遷移なし、UIフィードバック（視覚的インジケーター）
- 保存失敗時: エラーメッセージをインライン表示
- CSRF トークンは POST ボディまたは `X-CSRF-TOKEN` ヘッダーで送信する

### 3.4 URL 設計（クリーン URL）

**Apache（mod_rewrite / .htaccess）:**
```apache
RewriteEngine on
Options -Indexes
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond $1#%{REQUEST_URI} ([^#]*)#(.*)$
RewriteRule ^([^\.]+)$ %2?page=$1 [QSA,L]
```

**Nginx（server block）:**
```nginx
location / {
    try_files $uri $uri/ @php_rewrite;
}

location @php_rewrite {
    rewrite ^/([^.]+)$ /index.php?page=$1 last;
}

location ~ \.php$ {
    fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    fastcgi_index index.php;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    include fastcgi_params;
}
```

---

## 4. WYSIWYGエディタ仕様

`engines/JsEngine/wysiwyg.js` による依存ライブラリなしの独自実装（ES2020+）。  
Editor.js スタイルのブロックベースアーキテクチャを採用。

### 4.1 起動条件

- `class="editRich"` を持つ `<span>` 要素をクリックすると WYSIWYG モードで起動する
- `editInplace.js` とは独立したモジュールとして動作する

### 4.2 アーキテクチャ

- 各ブロックが独立した contenteditable 要素を持つブロックベース設計
- 内部データモデル: `{ id, type, data }` の配列
- 入力: 既存 HTML をブロック配列にパース（後方互換性を維持）
- 出力: ブロック配列を HTML にシリアライズ（AP の既存保存フローと完全互換）

### 4.3 ブロックタイプ

| タイプ | ツールバー | HTML 出力 |
|--------|-----------|-----------|
| paragraph | ¶ | `<p>` |
| heading | H2 / H3 | `<h2>` / `<h3>` |
| list | •≡ / 1≡ | `<ul><li>` / `<ol><li>` |
| blockquote | ❝ | `<blockquote>` |
| code | {} | `<pre><code>` |
| delimiter | — | `<hr>` |
| table | 📊 | `<table>` |
| image | 🖼 | `<figure><img><figcaption>` |
| checklist | — | `<ul class="ap-checklist">` |

### 4.4 インラインツール

| ツール | ショートカット | HTML |
|--------|---------------|------|
| Bold | Ctrl+B | `<b>` |
| Italic | Ctrl+I | `<i>` |
| Underline | Ctrl+U | `<u>` |
| Strikethrough | Ctrl+Shift+S | `<s>` |
| Inline Code | Ctrl+E | `<code>` |
| Marker | Ctrl+Shift+M | `<mark>` |
| Link | Ctrl+K | `<a href>` |

### 4.5 フローティングインラインツールバー

テキスト選択時に B/I/U/S/Code/Marker/Link ボタンを浮かせる

### 4.6 画像ブロック

D&D / クリップボード / ボタン / スラッシュコマンドで挿入。サイズプリセット・Alt・キャプション対応

### 4.7 テーブル

3×3 初期テーブル、Tab/Shift+Tab セル間移動、行列追加・削除

### 4.8 チェックリスト

`/checklist` で挿入、Enter で新項目、空項目 + Backspace で削除

### 4.9 スラッシュコマンド

空ブロック `/` でメニュー表示、インクリメンタル絞り込み

### 4.10 ブロックハンドル

`⠿` ホバー表示、タイプ変換ポップアップ、Block Tunes（配置）

### 4.11 ドラッグ並べ替え

`⠿` ドラッグでブロック順序変更（シアン色ドロップライン）

### 4.12 ARIA

`role="toolbar"` / `role="textbox"` / `aria-live="polite"` / `role="listbox"`

### 4.13 保存

30秒自動保存、Ctrl+Enter / blur で即時保存、ホワイトリスト方式サニタイザー

### 4.14 HTML サニタイザー

許可タグ: `P / H1-H6 / BR / B / I / U / S / A / UL / OL / LI / BLOCKQUOTE / PRE / CODE / HR / TABLE / TBODY / THEAD / TFOOT / TR / TD / TH / IMG`

---

## 5. アップデートエンジン仕様

`engines/UpdateEngine.php` による実装。

### 5.1 API エンドポイント一覧

| `ap_action` 値 | 処理内容 | 認証必須 |
|----------------|----------|----------|
| `check` | 最新バージョンを GitHub API で確認（TTL: 1時間キャッシュ） | ✅ |
| `check_env` | 環境確認（ZipArchive・allow_url_fopen・書込権限・ディスク容量） | ✅ |
| `apply` | アップデート適用（ZIP DL → 展開 → バックアップ → 上書き） | ✅ |
| `list_backups` | バックアップ一覧取得 | ✅ |
| `rollback` | 指定バックアップへのロールバック | ✅ |
| `delete_backup` | バックアップの削除 | ✅ |

### 5.2 バックアップ仕様

- 保存先: `backup/YYYYMMDD_His/`
- 世代管理: 最大 **5 世代**（`AP_BACKUP_GENERATIONS` 定数で変更可能）
- 保護対象: `data/`・`backup/`・`.git/` ディレクトリはバックアップ・アップデート時に除外
- メタデータ: 各バックアップに `meta.json`（更新前バージョン・作成日時・ファイル数・サイズ）を記録

---

## 6. 管理エンジン・ダッシュボード仕様

`engines/AdminEngine.php` による実装（Ver.1.3-27 導入）。

### 6.1 AdminEngine 概要

index.php に集約されていた管理機能を単一エンジンに分離。静的クラスメソッドによるエンジン駆動モデル。

| 機能グループ | メソッド | 説明 |
|------------|---------|------|
| POSTディスパッチ | `handle()` | `ap_action` パラメータでルーティング。後方互換: `fieldname` のみでも動作 |
| 認証 | `isLoggedIn()`, `login()`, `savePassword()` | セッション・bcrypt 認証・パスワード変更 |
| CSRF | `csrfToken()`, `verifyCsrf()` | トークン生成・検証 |
| フィールド保存 | `handleEditField()` | サイト設定・ページコンテンツの保存 |
| 画像アップロード | `handleUploadImage()` | MIME 検証・ランダムファイル名 |
| リビジョン管理 | `saveRevision()`, `listRevisions()` 等 | 保存・一覧・復元・ピン留め・検索 |
| コンテンツ出力 | `renderEditableContent()` | ログイン時は editRich span 付与 |
| フック | `registerHooks()`, `getAdminScripts()` | JsEngine スクリプト登録 |
| ダッシュボード | `renderDashboard()`, `buildDashboardContext()` | 管理画面レンダリング |

### 6.2 管理ダッシュボード

| 項目 | 仕様 |
|------|------|
| アクセス URL | `?admin` |
| 認証 | 必須（未ログインは `?login` にリダイレクト） |
| テンプレート | `engines/AdminEngine/dashboard.html`（TemplateEngine 方式・テーマ非依存） |
| スタイル | `engines/AdminEngine/dashboard.css`（テーマ CSS に依存しない） |
| JS | `engines/JsEngine/dashboard.js`（テーマ選択変更の保存） |

### 6.3 `ap_action` パラメータ（AdminEngine 管轄）

| `ap_action` 値 | 処理内容 | 認証必須 |
|----------------|----------|----------|
| `edit_field` | フィールド保存（設定・ページコンテンツ） | ✅ |
| `upload_image` | 画像アップロード | ✅ |
| `list_revisions` | リビジョン一覧取得 | ✅ |
| `get_revision` | リビジョンコンテンツ取得 | ✅ |
| `restore_revision` | リビジョン復元 | ✅ |
| `pin_revision` | リビジョンピン留め | ✅ |
| `search_revisions` | リビジョン検索 | ✅ |

---

## 7. 静的サイト生成エンジン仕様

> ✅ **ステータス: 実装済み（Ver.1.3-28）**

`engines/StaticEngine.php` による静的サイト生成エンジン。コンテンツを静的 HTML として書き出し、`.htaccess` で静的ファイルを優先配信する Static-First Hybrid アーキテクチャを実現。

### 7.1 主要機能

| 機能 | メソッド | 説明 |
|------|---------|------|
| 差分ビルド | `buildDiff()` | `content_hash` / `settings_hash` に基づき変更ページのみ再生成 |
| フルビルド | `buildAll()` | 全ページ強制再生成 |
| クリーン | `clean()` | `static/` を完全削除 |
| ZIP ダウンロード | `serveZip()` | 静的ファイル一式を ZIP 圧縮 |
| アセット管理 | `copyAssets()` | テーマ CSS/JS + uploads/ を `static/assets/` に差分コピー |
| ステータス取得 | `getStatus()` | ページ別ビルド状態（current/outdated/not_built） |

### 7.2 追加機能

- コレクション一覧・個別ページ・タグページの静的生成
- ページネーション（`perPage` 設定に基づく複数ページ分割）
- `sitemap.xml` / `robots.txt` 自動生成
- `search-index.json` 生成（クライアントサイド検索用、ドラフト記事除外）
- OGP メタタグ / JSON-LD 構造化データ自動生成
- 前後記事ナビゲーション
- HTML / CSS ミニファイ

---

## 8. ヘッドレス CMS / API エンジン仕様

> ✅ **ステータス: 実装済み（Ver.1.3-28）**

`engines/ApiEngine.php` により、公開 REST API エンドポイントを提供します。

- **主用途**: StaticEngine が生成した静的 HTML から Fetch API で呼び出す動的機能（フォーム・検索等）
- **将来用途**: 外部フロントエンド（Next.js / Nuxt / SvelteKit）からのヘッドレス CMS 利用

### 8.1 関連エンジン

| エンジン | 説明 |
|---------|------|
| `CollectionEngine` | Markdown ベースのコレクション（ブログ・ニュース等）管理 |
| `MarkdownEngine` | フロントマター付き Markdown パース・HTML 変換 |
| `GitEngine` | GitHub リポジトリとのコンテンツ同期 |
| `WebhookEngine` | ビルド完了通知・外部連携（SSRF 防止付き） |
| `CacheEngine` | API レスポンスキャッシュ |
| `ImageOptimizer` | 画像最適化（リサイズ・品質調整） |
| `AppContext` | アプリケーションコンテキスト管理（リクエスト情報・設定の一元管理） |
| `Logger` | 構造化ログ出力（レベル別・ファイルローテーション対応） |
| `MailerEngine` | メール送信エンジン（`mail()` 直接呼び出しを置換・テンプレート対応） |

### 8.2 公開エンドポイント

| メソッド | `ap_api` 値 | 説明 |
|---------|------------|------|
| POST | `contact` | お問い合わせフォーム送信（`MailerEngine::sendContact()` 使用） |
| GET | `search` | ページ全文検索（`mb_stripos` ベース） |
| GET | `page` | 単一ページコンテンツの JSON 取得 |
| GET | `pages` | 全ページ一覧（slug + プレビュー） |
| GET | `settings` | 公開サイト設定（title, description, keywords）のみ |
| GET | `collection` | コレクション API |

### 8.3 認証

- 公開エンドポイント: 認証不要
- 管理エンドポイント: API キー認証（Bearer トークン + bcrypt）
- CORS: 設定可能なオリジン許可（`Vary: Origin` ヘッダー付き）

### 8.4 セキュリティ

- IP ベースレート制限（`contact`: 5回/15分）
- ハニーポットフィールドによるボット検出
- パラメータバリデーション（slug: `^[a-zA-Z0-9_-]+$`、検索語: 100文字制限）
- `settings` エンドポイントは `auth.json` 等の機密情報を含めない
- メール Subject ヘッダインジェクション対策

---

## 9. フック機構仕様

### 9.1 存続フック（内部専用）

| フック名 | 用途 |
|---------|-----|
| `admin-head` | 管理画面 `<head>` 内スクリプト挿入 |
| `admin-richText` | Phase 1では廃止済み、Phase 2でWYSIWYG用に復活予定 |

### 9.2 廃止済み

- `loadPlugins()` による外部プラグイン走査
- `plugins/` ディレクトリ全体
- 外部からのフック登録機能

### 9.3 現行実装

```php
// AdminEngine::registerHooks() が admin-head フックに JsEngine スクリプトを登録
AdminEngine::registerHooks();

// AdminEngine::getAdminScripts() がフック内容を文字列で返却（theme.html 方式用）
// editTags() はレガシー theme.php フォールバック用ラッパーとして維持
```

外部プラグインによるフック登録は廃止。内部コアフックのみ使用。

---

## 10. データ層仕様

### 10.1 移行パス

| 旧パス | 新パス |
|-------|-------|
| `data/settings.json` | `data/settings/settings.json` |
| `data/auth.json` | `data/settings/auth.json` |
| `data/update_cache.json` | `data/settings/update_cache.json` |
| `data/pages.json` | `data/content/pages.json` |

### 10.2 自動マイグレーション

`migrate_from_files()` 関数が旧パスから新パスへ自動移行する。  
起動時に旧ファイルが存在すれば新ディレクトリに移動する。（実装済み）

```
Phase 1: files/{key} → data/settings/{key}.json / data/content/pages.json
Phase 2: data/{file}.json → data/settings/{file}.json / data/content/pages.json
```

Phase 2 は起動時に毎回チェックするが、移行済みの場合は `file_exists` で早期 skip される。

---

## 11. データ仕様

### 11.1 data/settings/settings.json

```json
{
  "title": "サイトタイトル",
  "description": "サイト説明文",
  "keywords": "キーワード1, キーワード2",
  "copyright": "© 2026 Adlaire Group",
  "menu": "ページ名1\nページ名2",
  "themeSelect": "AP-Default"
}
```

### 11.2 data/content/pages.json

```json
{
  "index": "<p>トップページのコンテンツ</p>",
  "about": "<p>概要ページのコンテンツ</p>",
  "contact": "<p>お問い合わせページのコンテンツ</p>"
}
```

### 11.3 data/settings/auth.json

```json
{
  "password_hash": "$2y$10$..."
}
```

> ⚠️ `password_hash` の値は必ず bcrypt ハッシュ（`$2y$` で始まる文字列）でなければならない。  
> MD5 ハッシュ（32文字の16進数）が検出された場合は警告を表示し、パスワードリセットを促す。

### 11.4 data/settings/version.json

```json
{
  "version": "1.2.20",
  "updated_at": "2026-03-08",
  "history": [
    {
      "version": "1.2.20",
      "applied_at": "2026-03-08 12:00:00",
      "backup": "20260308_120000"
    }
  ]
}
```

---

## 12. 変更履歴

| バージョン | 日付 | 変更内容 | 担当 |
|------------|------|----------|------|
| Ver.1.0-1 | 2026-03-10 | 新規作成。AdlairePlatform_Design.md から機能仕様セクション（5-13）を統合 | Adlaire Group |

---

*本ドキュメントは AdlairePlatform の公式仕様リファレンスです。*  
*内容は Adlaire Group の承認なく変更・転載することを禁じます。*
