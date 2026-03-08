# StaticEngine 仕様書

> **ドキュメントバージョン**: Ver.0.2-1
> **ステータス**: ✅ 確定（設計承認済み）
> **作成日**: 2026-03-06
> **最終更新**: 2026-03-08
> **所有者**: Adlaire Group
> **分類**: 社内限り

---

## 目次

1. [概要](#1-概要)
2. [アーキテクチャ方針](#2-アーキテクチャ方針)
3. [ディレクトリ構成](#3-ディレクトリ構成)
4. [差分ビルド設計](#4-差分ビルド設計)
5. [公開設定 — .htaccess による静的優先配信](#5-公開設定--htaccess-による静的優先配信)
6. [クラス設計](#6-クラス設計)
7. [レンダリング戦略](#7-レンダリング戦略)
8. [アセット管理](#8-アセット管理)
9. [静的サイト向け PHP API 連携](#9-静的サイト向け-php-api-連携)
10. [クライアントサイド JS](#10-クライアントサイド-js)
11. [管理パネル UI](#11-管理パネル-ui)
12. [index.php への統合](#12-indexphp-への統合)
13. [セキュリティ](#13-セキュリティ)
14. [未確定事項](#14-未確定事項)
15. [実装ステップ](#15-実装ステップ)
16. [変更履歴](#16-変更履歴)

---

## 1. 概要

StaticEngine は AdlairePlatform のコンテンツを静的 HTML ファイルとして書き出すエンジンです。

**採用アーキテクチャ: 静的優先ハイブリッド（Static-First Hybrid）**

同一サーバー上で静的配信と動的 PHP API を共存させます。一般閲覧者には静的 HTML を直接配信し（PHP 不要）、動的機能（フォーム・検索等）は `ap_api` エンドポイント経由で PHP が処理します。

```
【静的配信（一般閲覧者）】
  /about → .htaccess → static/about/index.html を直接配信（高速・PHP不要）

【動的 API（静的サイトの JS から呼び出す）】
  /?ap_api=contact → .htaccess → index.php → ApiEngine → JSON レスポンス

【管理者アクセス（動的 CMS）】
  /?admin=1 → .htaccess → index.php → 動的 CMS 管理画面（従来通り）
```

### 生成物の仕様

- **HTML**: セマンティック HTML5（管理者 UI なし・PHP なし）
- **CSS**: テーマの `style.css` をそのままコピー
- **JavaScript**: バニラ JS のみ（`ap-api-client.js` のみ配置）
- **画像**: `uploads/` のミラーコピー

---

## 2. アーキテクチャ方針

| 方針 | 内容 | ステータス |
|------|------|-----------|
| 追加機能として提供 | 動的 PHP 動作を壊さず、静的書き出しのみ追加する | ✅ 確定 |
| PHP のみで完結 | Node.js 等の外部ツール不要。`StaticEngine.php` 単体で動作 | ✅ 確定 |
| テーマエンジン流用 | `ThemeEngine::load()` の出力を `ob_start()` でキャプチャ | ✅ 確定 |
| 管理画面から操作 | 管理パネルの「静的書き出し」パネルで実行・状態確認 | ✅ 確定 |
| 管理者 UI の除去 | 生成 HTML に CSRF トークン・編集 UI・管理スクリプトは含めない | ✅ 確定 |
| 差分ビルド | 変更のあったページのみ再生成（状態は `static_build.json` で管理） | ✅ 確定 |
| ハイブリッド仕様 | 動的機能は `ap_api` エンドポイント経由で PHP が処理 | ✅ 確定 |
| `ap_api` 名前空間 | 公開 API は `?ap_api=` パラメータで呼び出す（既存 `ap_action` と共存） | ✅ 確定 |

---

## 3. ディレクトリ構成

```
AdlairePlatform/
├── engines/
│   ├── StaticEngine.php              # 書き出しエンジン本体（新規）
│   ├── ApiEngine.php                 # 公開 REST API エンジン（新規・HEADLESS_CMS.md 参照）
│   └── JsEngine/
│       ├── static_builder.js         # 書き出し管理 UI（新規）
│       └── ap-api-client.js          # 静的サイト向け API クライアント（新規）
│
├── static/                           # 書き出し先（.htaccess で静的優先配信）
│   ├── .htaccess                     # 静的サイト内ルール（PHP 実行禁止・PHP フォールバック）
│   ├── index.html                    # トップページ
│   ├── about/
│   │   └── index.html
│   ├── contact/
│   │   └── index.html
│   └── assets/
│       ├── style.css                 # テーマ CSS
│       ├── ap-api-client.js          # API クライアント（コピー）
│       └── uploads/                  # アップロード画像のミラー
│           ├── *.jpg
│           └── *.png
│
└── data/
    └── settings/
        └── static_build.json         # 差分ビルド状態（新規）
```

---

## 4. 差分ビルド設計

### 4.1 状態ファイル `data/settings/static_build.json`

```json
{
  "schema_version": 1,
  "last_full_build": "2026-03-08T12:00:00+09:00",
  "theme": "AP-Default",
  "settings_hash": "a1b2c3d4",
  "assets_copied_at": "2026-03-08T12:00:00+09:00",
  "pages": {
    "index": {
      "content_hash": "e5f6a7b8",
      "built_at": "2026-03-08T12:00:01+09:00"
    },
    "about": {
      "content_hash": "c9d0e1f2",
      "built_at": "2026-03-08T12:00:02+09:00"
    }
  }
}
```

### 4.2 ハッシュ計算ルール

| ハッシュ種別 | 計算式 | 変更時の影響範囲 |
|-------------|--------|----------------|
| `settings_hash` | `md5(json_encode($settings))` | **全ページ**再ビルド（メニュー・タイトルが全ページに影響するため） |
| `pages[slug].content_hash` | `md5($slug . $content . $settings_hash)` | そのページのみ再ビルド |
| テーマ変更検出 | `$settings['themeSelect']` の変化を比較 | 全ページ再ビルド + アセット再コピー |

`settings_hash` にはメニュー・タイトル・著作権表記が含まれるため、設定変更時は全ページが対象になります。

### 4.3 差分ビルドフロー（`buildDiff()`）

```
buildDiff() 呼び出し
    ↓
static_build.json を読み込む
    ↓
settings_hash を再計算して比較
    ├─ 変化あり → all_pages_dirty = true、settings_hash を更新
    └─ 変化なし → all_pages_dirty = false
    ↓
テーマが変わった → copyAssets() 実行
    ↓
pages.json の全スラッグをループ:
    ├─ all_pages_dirty = true         → buildPage($slug)
    ├─ content_hash が変化            → buildPage($slug)
    ├─ static/{slug}/index.html 欠落  → buildPage($slug)
    └─ 変化なし                       → スキップ
    ↓
pages.json に存在しない static/ ファイルを削除
    ↓
static_build.json を更新して json_write()
    ↓
['built' => N, 'skipped' => M, 'deleted' => L, 'elapsed_ms' => T] を返す
```

### 4.4 フルビルド（`buildAll()`）

`static_build.json` を無視して全ページを強制再生成します。クリーンビルド（`clean()` 後の `buildAll()`）も同等です。

---

## 5. 公開設定 — .htaccess による静的優先配信

### 5.1 公開設定モード

| モード | 説明 | 適用場面 |
|--------|------|---------|
| **Static-First（推奨）** | 同一サーバー・`.htaccess` で静的ファイルを優先配信 | セルフホスト・小〜中規模 |
| **Static-Only** | `static/` を別途 CDN / 静的ホスティングにデプロイ | 大規模・高トラフィック |
| **無効** | 静的書き出しを使用しない（動的 PHP のみ） | デフォルト・開発時 |

設定は `data/settings/settings.json` の `static_mode` キー（`"static-first"` / `"static-only"` / `"disabled"`）に保存します。

### 5.2 ルートの `.htaccess` 変更案（Static-First モード）

```apache
RewriteEngine on
Options -Indexes

# ── 静的ファイル優先配信 ─────────────────────────────────────
# /slug → static/slug/index.html が存在すれば直接配信（PHP 不要）
RewriteCond %{DOCUMENT_ROOT}/static/%1/index.html -f
RewriteRule ^([a-zA-Z0-9_-]+)/?$ static/$1/index.html [L]

# トップページ → static/index.html
RewriteCond %{DOCUMENT_ROOT}/static/index.html -f
RewriteRule ^$ static/index.html [L]

# static/assets/ への直接アクセスはそのまま通す
RewriteRule ^static/ - [L]

# ── 動的 PHP（常に index.php へ） ─────────────────────────────
# ap_api= パラメータ（公開 API）
RewriteCond %{QUERY_STRING} (^|&)ap_api=
RewriteRule ^ index.php [L,QSA]

# ap_action= パラメータ（管理アクション）・admin= パラメータ
RewriteCond %{QUERY_STRING} (^|&)(ap_action|admin)=
RewriteRule ^ index.php [L,QSA]

# 通常のスラッグルーティング（静的ファイルがない場合のフォールバック）
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^([^.]+)$ index.php?page=$1 [QSA,L]
```

### 5.3 `static/` 内の `.htaccess`

```apache
Options -Indexes -ExecCGI

# PHP 実行禁止（static/ 内に PHP を置かない）
<FilesMatch "\.php$">
    Require all denied
</FilesMatch>

# 静的サイト内で見つからないパスは index.php にフォールバック
# （まだビルドされていないページ等）
ErrorDocument 404 /index.php
```

### 5.4 Static-Only モード（別ホストデプロイ）

`static/` を CDN や静的ホスティング（Netlify / S3 / GitHub Pages 等）に別途アップロードします。この場合、静的 HTML の `ap-api-client.js` が向く API オリジンを `settings.json` の `api_origin` キーで指定します。

```json
{
  "static_mode": "static-only",
  "api_origin": "https://cms.example.com"
}
```

CORS 設定が必要になります（詳細は [セクション 9.3](#93-cors-設定)）。

---

## 6. クラス設計

```php
// engines/StaticEngine.php

class StaticEngine {
    private string $outputDir  = 'static';
    private array  $settings   = [];
    private array  $pages      = [];
    private string $themeDir   = '';
    private array  $buildState = [];  // static_build.json の内容

    // ─── 公開メソッド ─────────────────────────────────────────

    /** ap_action/ap_api ディスパッチャから呼び出すエントリーポイント */
    public static function handle(): void

    /** 差分ビルド: 変更のあったページのみ再生成 */
    public function buildDiff(): array
    // 返値: ['built' => N, 'skipped' => M, 'deleted' => L, 'elapsed_ms' => T]

    /** フルビルド: 全ページを強制再生成 */
    public function buildAll(): array
    // 返値: 同上

    /** static/ ディレクトリを完全削除して再作成 */
    public function clean(): void

    /** テーマ CSS・JS・uploads/ を static/assets/ にコピー */
    public function copyAssets(): void
    // uploads/ は差分コピー（filemtime 比較）

    /** static/ を ZIP に圧縮してダウンロード用パスを返す */
    public function buildZip(): string

    // ─── 内部メソッド ─────────────────────────────────────────

    /** 単一ページを HTML 文字列として生成し static/{slug}/index.html に書き出す */
    private function buildPage(string $slug, string $content): void

    /**
     * ob_start() でテーマ出力をキャプチャ
     * $GLOBALS 経由でコンテキストを静的モードに偽装
     */
    private function renderPage(string $slug, string $content): string

    /**
     * キャプチャした HTML から管理者 UI を除去し
     * 静的アセットの相対パスを書き換える
     */
    private function stripAdminUI(string $html): string

    /** settings_hash・content_hash を計算して差分を判定 */
    private function isDirty(string $slug, string $content): bool

    /** static_build.json を読み込んで $this->buildState にセット */
    private function loadBuildState(): void

    /** $this->buildState を static_build.json に書き出す */
    private function saveBuildState(): void

    /** pages.json に存在しないスラッグの static/ ファイルを削除 */
    private function deleteOrphanedFiles(): int

    /** ディレクトリを再帰作成 */
    private function ensureDir(string $path): void

    /** uploads/ を static/assets/uploads/ に差分コピー */
    private function syncUploads(): void

    /** static/ 配下を再帰的に ZIP に追加 */
    private function addDirToZip(\ZipArchive $zip, string $dir, string $base): void
}
```

---

## 7. レンダリング戦略

独自の HTML 生成は行いません。既存のテーマ出力を PHP の出力バッファでキャプチャし、管理者 UI を除去する方式を採用します。

```
renderPage($slug, $content)
    ↓
$GLOBALS を静的モード用に一時差し替え
  is_loggedin() → false として動作させる
  $c['content'] = $content（対象ページのコンテンツ）
    ↓
ob_start()
    ↓
ThemeEngine::load($themeSelect)  ← 実際のテーマをそのままコール
    ↓
$html = ob_get_clean()
    ↓
$html = stripAdminUI($html)      ← 管理者 UI の除去 + パス書き換え
    ↓
static/{slug}/index.html に書き込み
```

### 7.1 `stripAdminUI()` の除去対象

実装は正規表現を使用します（PHP の DOMDocument はマルチバイト HTML で不安定なため）。

| 除去・変換対象 | 処理 |
|--------------|------|
| `<span class="editText ..." ...>` → `<span>` | クラス・属性を除去、コンテンツは維持 |
| `<span class="editRich ..." ...>` → `<span>` | 同上 |
| `<div id="ap-settings">...</div>` 全体 | タグごと除去（管理パネル） |
| `<meta name="csrf-token" ...>` | タグごと除去 |
| `<script src="engines/JsEngine/...">` | タグごと除去（管理スクリプト） |
| `<script src="engines/JsEngine/ap-api-client.js">` | → `<script src="/assets/ap-api-client.js">` に書き換え（維持） |
| `href="themes/{name}/style.css"` | → `href="/assets/style.css"` |
| `src="uploads/{file}"` | → `src="/assets/uploads/{file}"` |
| `href="/?page={slug}"` または `href="/{slug}"` | → `href="/{slug}/"` |

---

## 8. アセット管理

### 8.1 コピー対象とパス

| 元パス | 書き出し先 | タイミング |
|--------|-----------|-----------|
| `themes/{active}/style.css` | `static/assets/style.css` | テーマ変更時・フルビルド時 |
| `themes/{active}/*.js` | `static/assets/*.js` | 同上 |
| `engines/JsEngine/ap-api-client.js` | `static/assets/ap-api-client.js` | 常に最新版をコピー |
| `uploads/*` | `static/assets/uploads/*` | 差分コピー（filemtime 比較） |

### 8.2 `uploads/` 差分コピーのルール

```
syncUploads()
    ↓
uploads/ 内の全ファイルをループ:
    ├─ static/assets/uploads/ に存在しない → コピー
    ├─ ソース側の filemtime が新しい      → 上書きコピー
    └─ 同じまたは古い                     → スキップ

※ pages.json に参照がないファイルは削除しない（安全側）
※ uploads/.htaccess はコピーしない
```

### 8.3 `static/.htaccess` の自動生成

`copyAssets()` 時に `static/.htaccess` を自動生成します（手動配置は不要）。

---

## 9. 静的サイト向け PHP API 連携

静的 HTML に埋め込んだ `ap-api-client.js` が `index.php` の `ap_api` エンドポイントを呼び出します。詳細な API 仕様は `docs/HEADLESS_CMS.md`（ApiEngine）を参照してください。

### 9.1 公開エンドポイント（認証不要）

| `ap_api` 値 | メソッド | 説明 |
|------------|---------|------|
| `contact` | POST | お問い合わせフォーム送信（PHP `mail()` によるメール送信） |
| `search` | GET + `?q=` | ページ全文検索（`stripos` ベース・依存なし） |
| `page` | GET + `?slug=` | 単一ページの JSON（SPA 的遷移オプション用） |
| `settings` | GET | 公開サイト設定（title, description） |

### 9.2 レスポンス形式（統一）

```json
// 成功
{ "ok": true, "data": { ... } }

// エラー
{ "ok": false, "error": "エラーメッセージ" }
```

### 9.3 CORS 設定

**Static-First モード**（同一オリジン）では CORS は不要です。

**Static-Only モード**（別ホスト）では以下を `.htaccess` に追加します:

```apache
<IfModule mod_headers.c>
    SetEnvIf Origin "^https://your-static-site\.com$" ORIGIN_OK
    Header always set Access-Control-Allow-Origin "%{HTTP_ORIGIN}e" env=ORIGIN_OK
    Header always set Access-Control-Allow-Methods "GET, POST, OPTIONS"
    Header always set Access-Control-Allow-Headers "Content-Type"
    Header always set Access-Control-Max-Age "3600"
</IfModule>
```

---

## 10. クライアントサイド JS

`engines/JsEngine/ap-api-client.js` — 静的サイトに配置する軽量バニラ JS。

WYSIWYG や管理 UI は含みません。依存ライブラリなし・ES5 互換。

### 10.1 機能

| 機能 | 説明 |
|------|------|
| フォーム自動バインド | `<form class="ap-contact">` を検出して送信をインターセプト・Fetch API で送信 |
| `window.AP.api()` | 公開 API 汎用呼び出し関数 |
| APIオリジン自動解決 | `currentScript.src` から API オリジンを自動取得（Static-Only モード対応） |

### 10.2 静的 HTML への組み込み

テーマの `theme.php` に以下を追記します（`editTags()` とは別に静的版でも出力）:

```html
<script src="/assets/ap-api-client.js"></script>
```

お問い合わせフォームの記述例:

```html
<form class="ap-contact">
  <input name="name" placeholder="お名前">
  <input name="email" type="email" placeholder="メールアドレス">
  <textarea name="message" placeholder="お問い合わせ内容"></textarea>
  <button type="submit">送信</button>
  <p class="ap-result"></p>
</form>

<script>
document.querySelector('form.ap-contact').addEventListener('ap:done', function(e) {
  this.querySelector('.ap-result').textContent =
    e.detail.ok ? '送信しました。' : 'エラー: ' + e.detail.error;
});
</script>
```

---

## 11. 管理パネル UI

`settings()` 関数に「⬇ 静的書き出し ⬇」折りたたみパネルを追加します。
`engines/JsEngine/static_builder.js` が AJAX で操作します。

```
┌──────────────────────────────────────────────────────────┐
│ ⬇ 静的書き出し ⬇                                        │
├──────────────────────────────────────────────────────────┤
│ 公開モード: [● Static-First  ○ Static-Only  ○ 無効]     │
│                                                          │
│ 最終フルビルド: 2026-03-08 12:34:56                      │
│ 最終差分ビルド: 2026-03-08 14:22:01  (3ページ更新)       │
│                                                          │
│ ページ状態:                                              │
│   ✅ index（最新）  ✅ about（最新）  ⚠️ contact（未建） │
│                                                          │
│ [差分ビルド]  [フルビルド]  [クリーン]  [ZIP DL]        │
└──────────────────────────────────────────────────────────┘
```

### ボタン操作フロー

```
[差分ビルド] クリック
    ↓ POST ap_action=generate_static_diff
    ↓ JSON レスポンス { ok, built, skipped, deleted, elapsed_ms, pages }
    ↓ ページ状態を更新表示

[フルビルド] クリック
    ↓ POST ap_action=generate_static_full
    ↓ JSON レスポンス { ok, built, elapsed_ms }

[クリーン] クリック → 確認ダイアログ
    ↓ POST ap_action=clean_static
    ↓ static/ を削除・static_build.json をリセット

[ZIP DL] クリック
    ↓ POST ap_action=build_zip
    ↓ バイナリレスポンス（Content-Disposition: attachment; filename=static-YYYYMMDD.zip）
    ↓ ブラウザがダウンロード
```

---

## 12. index.php への統合

### 12.1 `ap_action` への追加

`handle_update_action()` 内のディスパッチャに以下を追加します:

| `ap_action` 値 | 処理内容 |
|----------------|---------|
| `generate_static_diff` | `StaticEngine::handle()` → `buildDiff()` |
| `generate_static_full` | `StaticEngine::handle()` → `buildAll()` |
| `clean_static` | `StaticEngine::handle()` → `clean()` |
| `build_zip` | `StaticEngine::handle()` → `buildZip()` → バイナリ出力 |

### 12.2 `ap_api` のルーティング追加

`upload_image()` の直後、`handle_update_action()` の前に追記します:

```php
// engines/ApiEngine.php の require は ThemeEngine/UpdateEngine と同列に追加
require_once __DIR__ . '/engines/ApiEngine.php';
require_once __DIR__ . '/engines/StaticEngine.php';

// index.php のメイン処理内
ApiEngine::handle();    // ?ap_api= があれば処理して exit
```

### 12.3 `registerCoreHooks()` への追加

```php
$hook['admin-head'][] = "<script src='engines/JsEngine/static_builder.js'>";
```

---

## 13. セキュリティ

| 脅威 | 対策 |
|------|------|
| 管理者 UI の漏洩 | `stripAdminUI()` で CSRF トークン・編集属性・管理スクリプトを除去 |
| PHP コードの混入 | `static/` に PHP ファイルを配置しない・`.htaccess` で実行禁止 |
| パストラバーサル（書き出し先） | スラッグを `^[a-zA-Z0-9_-]+$` でバリデーション後にパスを構築 |
| プロジェクトルート外への書き出し | 出力先を `realpath()` でチェックしてプロジェクト内に限定 |
| ZIP 生成のパストラバーサル | ZipArchive の追加パスを `basename()` でサニタイズ |
| 未認証の書き出し操作 | `ap_action` は `verify_csrf()` + `is_loggedin()` で保護 |
| CORS の過度な許可 | Static-Only 時のみ特定オリジンのみ許可（ワイルドカード `*` 禁止） |
| `contact` API のスパム | ハニーポットフィールド（hidden input）＋レート制限（`login_attempts.json` の仕組みを流用） |

---

## 14. 未確定事項

| 事項 | ステータス | 内容 |
|------|-----------|------|
| `contact` API のメール送信 | ⚠️ 暫定 | `mail()` を使用（SMTP ライブラリは依存なし原則に反するため採用しない） |
| 検索インデックス | ⚠️ 暫定 | `stripos` によるリアルタイム全文検索。ページ数が増えた場合は事前インデックス生成（`search_index.json`）を検討 |
| 複数テーマの CSS パス | ❓ 未定 | テーマ固有のサブディレクトリ（`assets/fonts/` 等）を含む場合のコピーロジックを要確認 |
| 管理者の動線 | ✅ 確定 | 静的サイトにログインリンクは置かない。管理者は `/?admin=1` を直接入力する |
| メニューのアクティブクラス | ❓ 未定 | テーマが PHP で動的に付与している場合、`renderPage()` のコンテキスト偽装で解決できるが確認要 |
| `api_origin` の管理画面設定 | 🔜 将来予定 | Static-Only モード選択時に管理パネルで入力欄を表示 |

---

## 15. 実装ステップ

| ステップ | 内容 | 優先度 |
|---------|------|--------|
| Step 1 | `data/settings/static_build.json` スキーマ確定・`json_read/write` で管理 | 高 |
| Step 2 | `StaticEngine::renderPage()` — テーマ出力キャプチャ + `stripAdminUI()` の正規表現実装 | 高 |
| Step 3 | `StaticEngine::buildDiff()` — 差分ビルド本体・`static_build.json` の読み書き | 高 |
| Step 4 | `.htaccess` の静的優先ルール追加・動作確認 | 高 |
| Step 5 | `ApiEngine::handle()` — `ap_api` ルーティング・`contact` エンドポイント | 中 |
| Step 6 | `engines/JsEngine/ap-api-client.js` — フォーム自動バインド実装 | 中 |
| Step 7 | `engines/JsEngine/static_builder.js` — 管理パネル UI（差分ビルド結果表示） | 中 |
| Step 8 | `StaticEngine::copyAssets()` — アセット差分コピー・`static/.htaccess` 自動生成 | 中 |
| Step 9 | `StaticEngine::buildZip()` — ZIP ダウンロード機能 | 低 |
| Step 10 | `ApiEngine` — `search` エンドポイント（`stripos` 全文検索） | 低 |
| Step 11 | Static-Only モード — CORS 設定の自動生成・`api_origin` 設定 UI | 低 |

---

## 16. 変更履歴

| バージョン | 日付 | 変更内容 |
|------------|------|---------|
| Ver.0.2-1 | 2026-03-08 | 設計承認。差分ビルド・静的優先ハイブリッド・ap_api 連携・アセット管理・クラス設計を全面具体化 |
| Ver.0.1-1 | 2026-03-06 | 初版草稿（機能要件・クラス骨格・出力構造・未解決事項のみ） |

---

*Adlaire License Ver.2.0 — 社内限り*
