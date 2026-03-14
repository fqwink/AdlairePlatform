# AdlairePlatform — エンジン技術設計書リファレンス

---
ファイル名: docs/ENGINE_DESIGN.md
バージョン: Ver.1.0-3
作成日: 2026-03-10
最終更新日: 2026-03-12
ステータス: 🔧 策定中
分類: 社内限り
---

> **バージョニング規則**: [APF/VERSIONING.md](https://github.com/fqwink/AdlaireGroup-Documents-Repository/blob/main/APF/VERSIONING.md)

> **関連ドキュメント**:  
> - 設計方針: [AdlairePlatform_Design.md](AdlairePlatform_Design.md)  
> - アーキテクチャ: [ARCHITECTURE.md](ARCHITECTURE.md)  
> - 機能仕様: [SPECIFICATION.md](SPECIFICATION.md)  
> - セキュリティ: [SECURITY_POLICY.md](SECURITY_POLICY.md)

---

## 📋 統合ドキュメント目的

本ドキュメントは、AdlairePlatform の既存エンジン設計書を1つのファイルに統合したものです。
実装済みで専用設計書が存在するエンジンのみを収録しています。

**統合対象エンジン（全17エンジン）**:
1. StaticEngine（静的サイト生成）
2. ApiEngine（ヘッドレスCMS / REST API）
3. DiagnosticEngine（診断・テレメトリ）
4. GitEngine（Git連携）
5. CollectionEngine（コレクション管理）
6. MarkdownEngine（Markdown処理）
7. WorkflowEngine（レビューワークフロー）
8. AdminEngine（管理画面バックエンド）
9. TemplateEngine（テンプレートエンジン）
10. ThemeEngine（テーマ管理）
11. UpdateEngine（更新・バックアップ管理）
12. WebhookEngine（Webhook管理）
13. CacheEngine（APIキャッシュ）
14. ImageOptimizer（画像最適化）
15. AppContext（アプリケーション状態管理）
16. Logger（ログ管理）
17. MailerEngine（メール送信）

---

## 目次

1. [StaticEngine 設計書](#1-staticengine-設計書)
2. [ApiEngine 設計書](#2-apiengine-設計書)
3. [DiagnosticEngine 設計書](#3-diagnosticengine-設計書)
4. [Git連携エンジン群設計書](#4-git連携エンジン群設計書)
   - 4.1 GitEngine
   - 4.2 CollectionEngine
   - 4.3 MarkdownEngine
   - 4.4 WorkflowEngine
5. [AdminEngine 設計書](#5-adminengine-設計書)
6. [TemplateEngine 設計書](#6-templateengine-設計書)
7. [ThemeEngine 設計書](#7-themeengine-設計書)
8. [UpdateEngine 設計書](#8-updateengine-設計書)
9. [WebhookEngine 設計書](#9-webhookengine-設計書)
10. [CacheEngine 設計書](#10-cacheengine-設計書)
11. [ImageOptimizer 設計書](#11-imageoptimizer-設計書)
12. [AppContext 設計書](#12-appcontext-設計書)
13. [Logger 設計書](#13-logger-設計書)
14. [MailerEngine 設計書](#14-mailerengine-設計書)

---

# 1. StaticEngine 設計書

> **元ドキュメント**: `docs/STATIC_GENERATOR.md`  
> **バージョン**: Ver.0.3-2  
> **実装状態**: ✅ 実装済み（Ver.1.3-28）  
> **最終更新**: 2026-03-08

## 1.1 概要

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

## 1.2 ディレクトリ構成

```
AdlairePlatform/
├── engines/
│   ├── StaticEngine.php              # 書き出しエンジン本体
│   ├── ApiEngine.php                 # 公開 REST API エンジン
│   └── JsEngine/
│       ├── static_builder.js         # 書き出し管理 UI
│       └── ap-api-client.js          # 静的サイト向け API クライアント
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
        └── static_build.json         # 差分ビルド状態
```

## 1.3 差分ビルド設計

### 状態ファイル `data/settings/static_build.json`

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

### ハッシュ計算ルール

| ハッシュ種別 | 計算式 | 変更時の影響範囲 |
|-------------|--------|----------------|
| `settings_hash` | `md5(json_encode($settings))` | **全ページ**再ビルド（メニュー・タイトルが全ページに影響するため） |
| `pages[slug].content_hash` | `md5($slug . $content . $settings_hash)` | そのページのみ再ビルド |
| テーマ変更検出 | `$settings['themeSelect']` の変化を比較 | 全ページ再ビルド + アセット再コピー |

### 差分ビルドフロー（`buildDiff()`）

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

## 1.4 クラス設計

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
     * TemplateEngine + ThemeEngine::buildStaticContext() で
     * 管理者 UI なしの HTML を生成
     */
    private function renderPage(string $slug, string $content): string

    /** 静的 HTML 内のアセットパスを書き換え */
    private function rewriteAssetPaths(string $html): string

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

## 1.5 レンダリング戦略

`TemplateEngine` + `ThemeEngine::buildStaticContext()` により、テンプレートに渡すコンテキストを切り替えるだけで管理者 UI を除外します。

```
renderPage($slug, $content)
    ↓
ThemeEngine::buildStaticContext($slug, $content, $settings)
  admin = false（管理者 UI を完全除外）
  content = 生コンテンツ（editRich span なし）
    ↓
file_get_contents('themes/{themeSelect}/theme.html')
    ↓
TemplateEngine::render($template, $context)
  {{#if admin}}...{{/if}} ブロックが自動的に除外される
    ↓
static/{slug}/index.html に書き込み
```

### 管理者 UI の除外方式

テーマテンプレート（`theme.html`）内の管理者専用要素は `{{#if admin}}...{{/if}}` で囲まれています。`buildStaticContext()` は `admin = false` を渡すため、テンプレートエンジンが自動的にこれらのブロックを除外します。

| 除外対象 | テンプレート内の表現 |
|---------|-------------------|
| CSRF メタタグ | `{{#if admin}}<meta name="csrf-token" ...>{{/if}}` |
| 管理スクリプト | `{{#if admin}}{{{admin_scripts}}}{{/if}}` |
| 設定パネル | `{{#if admin}}{{{settings_panel}}}{{/if}}` |
| 編集可能属性 | `buildStaticContext()` が `editRich` span を付与しない |

## 1.6 .htaccess による静的優先配信

### ルートの `.htaccess` 変更案（Static-First モード）

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

### `static/` 内の `.htaccess`

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

## 1.7 アセット管理

### コピー対象とパス

| 元パス | 書き出し先 | タイミング |
|--------|-----------|-----------|
| `themes/{active}/style.css` | `static/assets/style.css` | テーマ変更時・フルビルド時 |
| `themes/{active}/*.js` | `static/assets/*.js` | 同上 |
| `engines/JsEngine/ap-api-client.js` | `static/assets/ap-api-client.js` | 常に最新版をコピー |
| `uploads/*` | `static/assets/uploads/*` | 差分コピー（filemtime 比較） |

### `uploads/` 差分コピーのルール

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

## 1.8 セキュリティ

| 脅威 | 対策 |
|------|------|
| 管理者 UI の漏洩 | `buildStaticContext()` で `admin=false` を渡し、テンプレートエンジンが `{{#if admin}}` ブロックを除外 |
| PHP コードの混入 | `static/` に PHP ファイルを配置しない・`.htaccess` で実行禁止 |
| パストラバーサル（書き出し先） | スラッグを `^[a-zA-Z0-9_-]+$` でバリデーション後にパスを構築 |
| プロジェクトルート外への書き出し | 出力先を `realpath()` でチェックしてプロジェクト内に限定 |
| ZIP 生成のパストラバーサル | ZipArchive の追加パスを `basename()` でサニタイズ |
| 未認証の書き出し操作 | `ap_action` は `verify_csrf()` + `is_loggedin()` で保護 |
| CORS の過度な許可 | Static-Only 時のみ特定オリジンのみ許可（ワイルドカード `*` 禁止） |
| `contact` API のスパム | ハニーポットフィールド（hidden input）＋レート制限（`login_attempts.json` の仕組みを流用） |

---

# 2. ApiEngine 設計書

> **元ドキュメント**: `docs/HEADLESS_CMS.md`  
> **バージョン**: Ver.0.3-1  
> **実装状態**: ✅ 実装済み（Ver.1.3-28）  
> **最終更新**: 2026-03-08

## 2.1 概要

ApiEngine は AdlairePlatform に公開 REST API エンドポイントを追加するエンジンです。

**主な用途:**

1. **StaticEngine との連携（主用途）**: 静的書き出しされたサイトの HTML から Fetch API で呼び出し、お問い合わせフォーム・検索・ページデータ取得等の動的機能を提供する
2. **ヘッドレス CMS（将来）**: Next.js / Nuxt / SvelteKit 等の外部フロントエンドから AdlairePlatform をコンテンツ管理バックエンドとして利用する

```
【現在の主用途】
  静的HTML（static/）
      ↓ ap-api-client.js が Fetch API で呼び出す
  /?ap_api=contact  → ApiEngine → JSON レスポンス

【将来の用途（ヘッドレスCMS）】
  外部フロントエンド（Next.js 等）
      ↓ HTTP リクエスト
  /?ap_api=page&slug=about  → ApiEngine → JSON レスポンス
```

## 2.2 エンドポイント設計

### パラメータ名前空間

公開 API は `?ap_api=` パラメータで呼び出します（既存の `?ap_action=` と共存・衝突なし）。

### 公開エンドポイント（認証不要・静的サイトから呼び出す）

| メソッド | `ap_api` 値 | 追加パラメータ | 説明 |
|---------|------------|--------------|------|
| POST | `contact` | `name`, `email`, `message` | お問い合わせフォーム送信（`MailerEngine::sendContact()` 使用） |
| GET | `search` | `q=<検索語>` | ページ全文検索（`mb_stripos` ベース） |
| GET | `page` | `slug=<スラッグ>` | 単一ページコンテンツの JSON 取得 |
| GET | `pages` | — | 全ページ一覧（slug + 先頭テキスト） |
| GET | `settings` | — | 公開サイト設定（title, description, keywords）のみ |

### コレクションエンドポイント（認証不要・Ver.1.4 追加）

| メソッド | `ap_api` 値 | 追加パラメータ | 説明 |
|---------|------------|--------------|------|
| GET | `collections` | — | コレクション定義一覧 |
| GET | `collection` | `name=<コレクション名>` | 特定コレクションの全アイテム |
| GET | `item` | `collection=<名>&slug=<スラッグ>` | 単一アイテム取得（HTML + Markdown） |

### 管理エンドポイント（API キーまたはセッション認証）

認証方式: `Authorization: Bearer <API_KEY>` ヘッダー、またはセッション Cookie。

| メソッド | `ap_api` 値 | リクエストボディ (JSON) | 説明 |
|---------|------------|----------------------|------|
| POST | `item_upsert` | `{collection, slug, title, body, meta?}` | コレクションアイテム作成・更新 |
| POST | `item_delete` | `{collection, slug}` | コレクションアイテム削除 |
| POST | `page_upsert` | `{slug, content}` | ページ作成・更新（pages.json） |
| POST | `page_delete` | `{slug}` | ページ削除（pages.json） |
| GET/POST | `api_keys` | `{action: "generate"\|"delete", label?, index?}` | API キー管理 |

### Webhook エンドポイント

| メソッド | `ap_api` 値 | 説明 |
|---------|------------|------|
| POST | `webhook` | GitHub Webhook 受信。Push イベント時に自動 Pull を実行。`X-Hub-Signature-256` による署名検証に対応。 |

### パラメータバリデーション

| パラメータ | バリデーション規則 |
|-----------|-----------------|
| `slug` | `^[a-zA-Z0-9_-]+$`（正規表現・パストラバーサル防止） |
| `q`（検索語） | `mb_strlen($q) >= 1 && mb_strlen($q) <= 100`・空文字は 400 エラー |
| `name` | `mb_strlen($name) >= 1 && mb_strlen($name) <= 100` |
| `email` | `filter_var($email, FILTER_VALIDATE_EMAIL)` |
| `message` | `mb_strlen($message) >= 1 && mb_strlen($message) <= 5000` |

## 2.3 レスポンス形式

すべてのレスポンスは JSON 形式で統一します。

```json
// 成功
{ "ok": true, "data": { ... } }

// エラー
{ "ok": false, "error": "エラーメッセージ" }
```

### HTTP ステータスコード

| コード | 用途 |
|--------|------|
| `200` | 正常レスポンス |
| `400` | バリデーションエラー（不正パラメータ・必須項目不足） |
| `404` | 指定リソースが存在しない（`page` エンドポイントでスラッグ不在） |
| `429` | レート制限超過（`contact` エンドポイント） |
| `500` | サーバー内部エラー（メール送信失敗等） |

## 2.4 レート制限

### contact エンドポイント

`login_attempts.json` の仕組みを流用した IP ベースのレート制限を適用します。

| パラメータ | 値 | 説明 |
|-----------|-----|------|
| 最大試行回数 | 5回 / 15分 | 15分あたり5回まで送信可能 |
| ロックアウト期間 | 15分 | 超過時に 429 レスポンスを返す |
| 記録ファイル | `data/settings/login_attempts.json` | 既存のレート制限ファイルを共有 |
| 識別キー | `contact_<IP>` | ログイン試行と区別するためプレフィックスを付与 |

### ハニーポットフィールド

```html
<!-- フォームに不可視の hidden input を配置 -->
<input type="text" name="website" style="display:none" tabindex="-1" autocomplete="off">
```

`website` フィールドに値が入っていた場合、ボットと判定してリクエストを無視します（200 レスポンスを返すがメールは送信しない）。

## 2.5 クラス設計

```php
// engines/ApiEngine.php

class ApiEngine {

    /** index.php から呼び出すエントリーポイント。?ap_api= があれば処理して exit */
    public static function handle(): void

    // ── 公開エンドポイント ────────────────────────────────────

    /** GET ?ap_api=pages — 全ページ一覧を返す */
    private static function getPages(): array

    /** GET ?ap_api=page&slug= — 単一ページコンテンツを返す */
    private static function getPage(string $slug): array

    /** GET ?ap_api=settings — 公開設定のみ返す（auth 情報は含めない） */
    private static function getSettings(): array

    /** GET ?ap_api=search&q= — mb_stripos による全文検索 */
    private static function search(string $query): array

    /** POST ?ap_api=contact — お問い合わせメール送信 */
    private static function sendContact(): array

    // ── ユーティリティ ────────────────────────────────────────

    /** JSON レスポンスを出力して exit */
    private static function jsonResponse(bool $ok, mixed $data): void

    /** エラーレスポンスを出力して exit */
    private static function jsonError(string $message, int $status = 400): void

    /** IP ベースのレート制限チェック（contact 用） */
    private static function checkContactRate(): void

    /** HTML コンテンツからプレビューテキストを生成 */
    private static function makePreview(string $html, int $length = 120): string
}
```

### `handle()` メソッドの処理フロー

```
handle()
    ↓
$action = $_GET['ap_api'] ?? null
    ├─ null → return（ApiEngine 不関与・次の処理へ）
    ↓
header('Content-Type: application/json; charset=UTF-8')
    ↓
switch ($action)
    ├─ 'pages'    → jsonResponse(true, getPages())
    ├─ 'page'     → slug バリデーション → jsonResponse(true, getPage($slug))
    ├─ 'settings' → jsonResponse(true, getSettings())
    ├─ 'search'   → q バリデーション → jsonResponse(true, search($q))
    ├─ 'contact'  → POST 検証 → checkContactRate() → sendContact()
    └─ default    → jsonError('不明な API エンドポイントです', 400)
    ↓
exit  （全分岐で exit）
```

## 2.6 クライアントサイド JS（ap-api-client.js）

`engines/JsEngine/ap-api-client.js` は静的サイトに配置する軽量バニラ JS クライアントです。
WYSIWYG や管理 UI は含みません。依存ライブラリなし・ES5 互換。

### API

| API | 説明 |
|-----|------|
| `window.AP.api(action, params)` | 公開 API 汎用呼び出し関数。`Promise` を返す |
| `window.AP.origin` | API オリジン（`currentScript.src` から自動取得・Static-Only 対応） |

### フォーム自動バインド

`<form class="ap-contact">` を検出して送信をインターセプトし、Fetch API で `?ap_api=contact` に送信します。

```html
<form class="ap-contact">
  <input name="name" placeholder="お名前" required>
  <input name="email" type="email" placeholder="メールアドレス" required>
  <!-- ハニーポット（不可視） -->
  <input type="text" name="website" style="display:none" tabindex="-1" autocomplete="off">
  <textarea name="message" placeholder="お問い合わせ内容" required></textarea>
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

## 2.7 セキュリティ

| 脅威 | 対策 |
|------|------|
| スパムフォーム送信 | IP レート制限（5回/15分）+ ハニーポットフィールド（`website`） |
| 機密情報の漏洩 | `settings` エンドポイントは `title`・`description`・`keywords` のみ返し、`auth.json`・`contact_email`・`themeSelect` 等は絶対に含めない |
| パストラバーサル | `slug` パラメータを `^[a-zA-Z0-9_-]+$` でバリデーション |
| XSS（JSON インジェクション） | `json_encode()` の `JSON_HEX_TAG \| JSON_HEX_AMP` オプションを使用 |
| 過剰な CORS 許可 | Static-Only 時のみ特定オリジンを許可。ワイルドカード `*` は禁止 |
| 未認証の管理操作 | 管理エンドポイント（将来）には必ず `is_loggedin()` + CSRF 検証を適用 |
| メールヘッダインジェクション | `name`・`email` の改行文字（`\r`・`\n`）を除去してからヘッダーに使用 |
| 大量検索リクエスト | 検索クエリ長を 100 文字に制限。将来的にはキャッシュを検討 |

---

# 3. DiagnosticEngine 設計書

> **元ドキュメント**: `docs/DIAGNOSTIC_ENGINE.md`  
> **バージョン**: Ver.1.4.x  
> **実装状態**: 🚧 未実装（Ver.1.4.x で実装予定）  
> **最終更新**: 2026-03-10

## 3.1 概要

DiagnosticEngine は AdlairePlatform のリアルタイム診断・テレメトリエンジンである。
本番サーバ上でシステムエラー・カスタムログ・環境情報を集約し、開発元へリアルタイム送信する。

### 設計目標

| # | 目標 | 実現手段 |
|---|------|----------|
| 1 | 障害の早期検知 | PHP エラー/例外/Fatal を自動キャプチャし12カテゴリに分類 |
| 2 | パフォーマンス可視化 | エンジン別実行時間・メモリ使用量をリクエスト単位で計測 |
| 3 | セキュリティ監視 | ログイン失敗・ロックアウト・レート制限・SSRF ブロックを集計 |
| 4 | プライバシー保護 | PII マスク・センシティブキー除外・匿名 UUID 識別 |
| 5 | 運用安定性 | ログローテーション・破損自動復旧・サーキットブレーカー |

### 動作原則

- **デフォルト有効** — エンドユーザーがダッシュボードから無効化可能
- **リアルタイム送信** — 毎リクエストで未送信分を開発元エンドポイントへ POST
- **14日間強制保存** — 送信後もサーバ内にログを保持、14日経過で古い順に自動削除
- **データベース不要** — JSON ファイルのみで完結（プラットフォーム方針準拠）

## 3.2 ファイル構成

```
engines/
├─ DiagnosticEngine.php          # エンジン本体（単一クラス）
├─ AdminEngine/
│  ├─ dashboard.html             # ダッシュボード UI（診断セクション含む）
│  └─ dashboard.css              # 診断 UI 用スタイル
└─ JsEngine/
   └─ diagnostics.js             # 診断ダッシュボード制御スクリプト

data/settings/
├─ diagnostics.json              # 設定ファイル（有効/レベル/インストールID等）
└─ diagnostics_log.json          # ログファイル（errors/custom/daily_summary）
   ├─ diagnostics_log.json.1     # アーカイブ世代1
   ├─ diagnostics_log.json.2     # アーカイブ世代2
   └─ diagnostics_log.json.3     # アーカイブ世代3
```

## 3.3 クラス設計

### 定数定義

```
┌─────────────────────────────────────────────────────────────┐
│ DiagnosticEngine (static class)                             │
├─────────────────────────────────────────────────────────────┤
│ CONFIG_FILE         = 'diagnostics.json'                    │
│ LOG_FILE            = 'diagnostics_log.json'                │
│ ENDPOINT            = 'https://telemetry.adlaire.com/…'    │
│ INTERVAL            = 0  (リアルタイム)                       │
│ LOG_RETENTION_DAYS  = 14                                    │
│ MAX_BUFFER_ITEMS    = 100                                   │
│ MAX_LOG_SIZE_BYTES  = 524288  (512KB)                       │
│ LOG_ARCHIVE_GENERATIONS = 3                                 │
│ RETRY_MAX           = 3                                     │
│ RETRY_BACKOFF       = [1, 2, 4]  秒                         │
│ RETRYABLE_CODES     = [429, 502, 503]                       │
│ CIRCUIT_BREAKER_THRESHOLD = 5                               │
│ CIRCUIT_BREAKER_DURATION  = 86400  (24h)                    │
│ VALID_LEVELS        = ['basic','extended','debug']          │
│ DEBUG_CATEGORIES    = [12分類]                               │
│ ENGINE_CLASSES      = [13エンジン]                            │
│ SENSITIVE_KEYS      = [12キー]                               │
└─────────────────────────────────────────────────────────────┘
```

## 3.4 デバッグ診断カテゴリ（12分類）

PHPエラー・例外を自動分類するルールベースのカテゴリシステム。

| カテゴリ | 分類条件（エラー） | 分類条件（例外） |
|---|---|---|
| `syntax` | E_PARSE, E_COMPILE_ERROR/WARNING | ParseError |
| `runtime` | 上記以外のデフォルト | デフォルト |
| `logic` | E_USER_ERROR, assertion, invalid argument | LogicException, DomainException, InvalidArgumentException, LengthException |
| `semantic` | type error, undefined variable/property, must be of type | TypeError |
| `off_by_one` | undefined offset/index/array key, out of range | OutOfRangeException, OutOfBoundsException |
| `race_condition` | lock, deadlock, concurrent, flock | メッセージに lock/deadlock/concurrent |
| `memory` | allowed memory size, out of memory | OverflowException, メッセージに memory |
| `performance` | — (logSlowExecution経由) | — |
| `security` | permission denied, csrf, injection, xss | メッセージに permission/unauthorized/forbidden |
| `environment` | extension, function not found, class not found | メッセージに extension/not supported/class not found |
| `timing` | maximum execution time, timeout | メッセージに timeout/timed out |
| `integration` | curl, http, api, connection refused, webhook | メッセージに curl/connection/webhook/api/http |

### 分類優先順位

`classifyError()` は上から順に評価し、最初にマッチしたカテゴリを返す:

```
syntax → memory → timing → off_by_one → semantic → race_condition
→ security → integration → environment → logic → runtime(default)
```

## 3.5 収集レベル設計

### レベル階層

```
┌────────────────────────────────────────────────┐
│ debug                                          │
│  ┌──────────────────────────────────────────┐  │
│  │ extended                                 │  │
│  │  ┌───────────────────────────────────┐   │  │
│  │  │ basic (デフォルト)                  │   │  │
│  │  │  install_id, versions, engines    │   │  │
│  │  │  error_count, error_summary       │   │  │
│  │  │  debug_category_summary           │   │  │
│  │  │  security_summary                 │   │  │
│  │  └───────────────────────────────────┘   │  │
│  │  + recent_errors (50件, PIIマスク済)      │  │
│  │  + recent_logs (30件)                    │  │
│  │  + performance (memory, disk)            │  │
│  │  + timings, engine_timings               │  │
│  └──────────────────────────────────────────┘  │
│  + full_errors (20件, ファイルパス含む)          │
│  + category_breakdown (12カテゴリ全件集計)       │
│  + category_recent (各カテゴリ最新5件)           │
│  + traced_errors (スタックトレース付き20件)       │
│  + captured_traces (手動キャプチャ)              │
│  + engine_timings (全詳細)                     │
│  + php_config, environment, memory_detail      │
└────────────────────────────────────────────────┘
```

## 3.6 データフロー

### エラーキャプチャフロー

```
PHP Error/Exception/Fatal
        │
        ▼
registerErrorHandler()
        │
        ├─ set_error_handler()        ─→ classifyError()   ─→ 12カテゴリ判定
        ├─ set_exception_handler()    ─→ classifyException()─→ 12カテゴリ判定
        └─ register_shutdown_function()─→ classifyError()   ─→ 12カテゴリ判定
                                         captureRuntimeSnapshot()
        │
        ▼
   logError(entry)
        │
        ├─ rotateIfNeeded()          ─→ 512KB超でローテーション
        ├─ safeJsonRead()            ─→ JSON破損時は .corrupt バックアップ
        ├─ purgeExpiredEntries()      ─→ 14日超エントリ削除
        ├─ recordDailySummary()       ─→ 日別カウンター更新（30日保持）
        └─ json_write()              ─→ diagnostics_log.json に永続化
```

### 送信フロー

```
maybeSend()  ←── 毎リクエスト呼び出し
    │
    ├─ isEnabled() → false → 終了
    ├─ purgeExpiredLogs()
    ├─ サーキットブレーカー発動中 → 終了
    ├─ 送信間隔チェック
    │
    ├─ collectWithUnsent(lastSent)
    │   ├─ collect()  ─→ レベルに応じて basic/extended/debug
    │   └─ 未送信エントリ抽出（lastSent 以降の errors/custom）
    │
    ├─ 新規ログなし & 環境データ24h以内送信済 → 終了
    │
    └─ send(data)
        │
        ├─ JSON エンコード
        ├─ cURL POST → ENDPOINT
        │   ├─ 成功(2xx) → last_sent更新・失敗カウントリセット
        │   ├─ 429/502/503 → 指数バックオフリトライ (1s→2s→4s, 最大3回)
        │   └─ その他エラー → 即失敗
        │
        └─ 失敗時: consecutive_failures++
            └─ 5回連続失敗 → サーキットブレーカー発動（24h送信停止）
```

## 3.7 ストレージ設計

### diagnostics.json（設定ファイル）

```json
{
    "enabled": true,
    "level": "basic",
    "install_id": "550e8400-e29b-41d4-a716-446655440000",
    "last_sent": "2026-03-10T12:00:00+09:00",
    "last_env_sent": "2026-03-10T00:00:00+09:00",
    "first_run_notice_shown": true,
    "send_interval": 0,
    "consecutive_failures": 0,
    "circuit_breaker_until": 0
}
```

### diagnostics_log.json（ログファイル）

```json
{
    "errors": [
        {
            "type": "E_WARNING",
            "debug_category": "integration",
            "message": "curl: connection refused to [IP]",
            "file": "/engines/WebhookEngine.php",
            "line": 142,
            "timestamp": "2026-03-10T10:30:00+09:00",
            "stack_trace": [
                {"file": "/engines/WebhookEngine.php", "line": 142, "function": "send", "class": "WebhookEngine"}
            ]
        }
    ],
    "custom": [
        {
            "category": "engine",
            "message": "CacheEngine 書き込み失敗",
            "timestamp": "2026-03-10T10:35:00+09:00",
            "context": {"endpoint": "/api/pages"}
        }
    ],
    "daily_summary": {
        "2026-03-10": {
            "errors": 5,
            "security": 1,
            "engine": 2,
            "other": 0,
            "debug_syntax": 0,
            "debug_runtime": 3,
            "debug_logic": 0,
            "debug_semantic": 0,
            "debug_off_by_one": 0,
            "debug_race_condition": 0,
            "debug_memory": 0,
            "debug_performance": 0,
            "debug_security": 1,
            "debug_environment": 0,
            "debug_timing": 0,
            "debug_integration": 1
        }
    }
}
```

## 3.8 プライバシー・セキュリティ設計

### PII マスク処理（sanitizeMessage）

| 対象 | 正規表現 | 置換後 |
|---|---|---|
| メールアドレス | `/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/` | `[EMAIL]` |
| IPv4 アドレス | `/\b\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\b/` | `[IP]` |
| Unix ユーザーパス | `#/home/[^/]+/#` | `/home/[USER]/` |
| Windows ユーザーパス | `#C:\\Users\\[^\\]+\\#i` | `C:\Users\[USER]\` |

### センシティブキー除外（stripSensitiveKeys）

再帰的に以下のキーを含むフィールドを `[REDACTED]` に置換:

```
password, password_hash, token, secret, api_key, apikey,
authorization, cookie, session, csrf, private_key, credentials
```

### 識別方式

- 匿名 UUID v4（`install_id`）のみで識別
- IP アドレス・ドメイン名は送信しない
- cURL ヘッダに `X-AP-Install-ID` を付与

## 3.9 制約・制限事項

| 項目 | 制限値 |
|---|---|
| ログファイルサイズ上限 | 512KB（超過でローテーション） |
| アーカイブ世代数 | 3世代 |
| ログ保持期間 | 14日間（強制） |
| 日別サマリー保持期間 | 30日間 |
| スタックトレース蓄積上限 | リクエストあたり50件 |
| スタックトレース深度 | extended: 10フレーム / debug: 20フレーム |
| recent_errors 件数 | 50件（extended） / 20件（debug full） |
| recent_logs 件数 | 30件 |
| category_recent 件数 | 各カテゴリ5件 |
| cURL 接続タイムアウト | 3秒 |
| cURL 応答タイムアウト | 5秒 |
| サーキットブレーカー閾値 | 5回連続失敗 |
| サーキットブレーカー停止期間 | 24時間 |
| 環境データ送信間隔 | 24時間に1回（新規ログなし時） |

---

# 4. Git連携エンジン群設計書

> **元ドキュメント**: `docs/HEADLESS_CMS_ROADMAP.md`  
> **バージョン**: Ver.1.3-final  
> **最終更新**: 2026-03-09

## 4.1 GitEngine — Git/GitHub 連携

### 概要

pitcms の核心機能。コンテンツを GitHub リポジトリと双方向同期する。

### クラス設計

```php
class GitEngine {
    /** GitHub リポジトリとの接続設定を管理 */
    public static function configure(string $repo, string $token): void

    /** リポジトリからコンテンツを pull（同期） */
    public static function pull(): array

    /** ローカル変更を commit + push */
    public static function push(string $message): bool

    /** プレビュー用ブランチを作成 */
    public static function createPreviewBranch(string $name): string

    /** プレビューブランチを main にマージ */
    public static function mergeToMain(string $branch): bool

    /** コミット履歴を取得 */
    public static function log(int $limit = 20): array

    /** 差分を取得 */
    public static function diff(string $from, string $to): string
}
```

### 実装方針

- PHP の `exec()` で `git` コマンドを直接実行（サーバーに Git がインストールされている前提）
- Git がない環境では GitHub REST API（v3）を HTTP クライアントで直接呼び出す
- Personal Access Token (PAT) で認証
- `data/settings/git_config.json` に接続情報を保存

### 設定ファイル

```json
// data/settings/git_config.json
{
  "enabled": true,
  "provider": "github",
  "repository": "user/my-site-content",
  "branch": "main",
  "token": "ghp_xxxxxxxxxxxx",
  "content_dir": "content",
  "auto_sync": false,
  "last_sync": "2026-03-08T12:00:00+09:00"
}
```

### 実装状態

✅ **Ver.1.3-28 で実装完了**

---

## 4.2 CollectionEngine — コレクション管理

### 概要

pitcms の `pitcms.jsonc` に相当するスキーマ定義機能。

### ディレクトリ構造

```
content/
├── ap-collections.json      # コレクション定義（pitcms.jsonc 相当）
├── posts/                   # コレクション: ブログ記事
│   ├── hello-world.md
│   └── second-post.md
├── pages/                   # コレクション: 固定ページ
│   ├── about.md
│   └── contact.md
└── news/                    # コレクション: お知らせ
    └── 2026-03-launch.md
```

### コレクション定義

```json
// content/ap-collections.json
{
  "collections": {
    "posts": {
      "label": "ブログ記事",
      "directory": "posts",
      "format": "markdown",
      "fields": {
        "title": { "type": "string", "required": true },
        "date": { "type": "date", "required": true },
        "tags": { "type": "array", "items": "string" },
        "draft": { "type": "boolean", "default": false },
        "thumbnail": { "type": "image" }
      },
      "sortBy": "date",
      "sortOrder": "desc"
    },
    "pages": {
      "label": "固定ページ",
      "directory": "pages",
      "format": "markdown",
      "fields": {
        "title": { "type": "string", "required": true },
        "order": { "type": "number", "default": 0 }
      }
    }
  }
}
```

### 実装状態

✅ **Ver.1.3-28 で実装完了**

---

## 4.3 MarkdownEngine — Markdown 処理

### 概要

pitcms が Markdown ベースであるため、Markdown パーサーが必要。

### 実装方針

- PHP のみで Markdown → HTML 変換（外部ライブラリ不使用）
- フロントマター（YAML 形式）のパース
- GFM（GitHub Flavored Markdown）互換を目指す

### クラス設計

```php
class MarkdownEngine {
    /** Markdown テキストを HTML に変換 */
    public static function toHtml(string $markdown): string

    /** フロントマターを抽出 */
    public static function parseFrontmatter(string $content): array
    // 返値: ['meta' => [...], 'body' => '本文...']

    /** コレクション内の全 .md ファイルを読み込み */
    public static function loadCollection(string $dir): array
}
```

### フロントマター例

```markdown
---
title: はじめてのブログ記事
date: 2026-03-08
tags: [announcement, launch]
draft: false
---

# はじめまして

ブログを始めました。
```

### 実装状態

✅ **Ver.1.3-28 で実装完了**

---

## 4.4 WorkflowEngine — レビュー・承認

### 概要

pitcms のレビュー機能に相当。Git ブランチ + PR の仕組みを活用。

### クラス設計

```php
class WorkflowEngine {
    /** 編集セッションを開始（プレビューブランチ作成） */
    public static function startSession(string $editor): string

    /** 変更をコミット（プレビューブランチ上） */
    public static function saveChanges(string $session, array $changes): bool

    /** レビューを依頼（PR 作成相当） */
    public static function requestReview(string $session): bool

    /** レビューを承認 */
    public static function approve(string $session, string $reviewer): bool

    /** 変更を反映（main にマージ） */
    public static function publish(string $session): bool

    /** セッション一覧 */
    public static function listSessions(): array
}
```

### 実装状態

🚧 **未実装（Phase 3 計画）**

---

# 5. AdminEngine 設計書

> **実装状態**: ✅ Ver.1.3-27  
> **ファイルパス**: `engines/AdminEngine.php`

## 5.1 概要

管理画面のバックエンドを実装。POST アクションのルーティング、認証、CSRF 検証、フィールド編集、画像アップロード、リビジョン管理、ユーザー管理、リダイレクト管理を担当。

## 5.2 主要機能

- **POST アクション処理**: `handle()` メソッドが `ap_action` パラメータでディスパッチ
- **認証・権限管理**: セッションベース、ロールチェック（`hasRole()`）、マルチユーザー対応（`data/settings/users.json`）
- **CSRF 保護**: トークン生成（`csrfToken()`）と検証（`verifyCsrf()`）
- **フィールド編集**: 設定/ページコンテンツ更新、リビジョン保存、アクティビティログ、キャッシュ無効化
- **画像アップロード**: サイズ制限 2MB、MIME 検証、オプション最適化、アクティビティログ
- **ページ削除**: 管理者専用、削除前にリビジョンを保存
- **リビジョン管理**: 保存、枝刈り、一覧、取得、復元、ピン留め、検索（レート制限: 30 req/min/session）
- **ユーザー管理**: ユーザー追加・削除（管理者専用）
- **リダイレクト管理**: リダイレクト追加・削除、検証、重複チェック（管理者専用）
- **ログイン画面レンダリング**: `themes/AP-Default/login.html` テンプレート使用
- **ダッシュボードレンダリング**: テーマ一覧、設定フィールド、ページ、ディスク空き容量、コレクション、Git 情報、ユーザー、Webhook、キャッシュ統計、リダイレクト、診断通知を集約し、`TemplateEngine` で描画

## 5.3 API/メソッド一覧

```php
AdminEngine::handle()                    // POSTアクション処理
AdminEngine::isLoggedIn()                // ログイン状態確認
AdminEngine::hasRole(string $role)       // ロール確認
AdminEngine::csrfToken()                 // CSRFトークン生成
AdminEngine::verifyCsrf()                // CSRF検証
AdminEngine::renderLogin()               // ログイン画面描画
AdminEngine::renderDashboard()           // ダッシュボード描画
AdminEngine::registerHooks()             // フック登録（JsEngine スクリプト注入）
AdminEngine::getAdminScripts()           // 管理画面用スクリプト集約
AdminEngine::renderEditableContent()     // 編集可能コンテンツHTML生成

// 内部ハンドラ
handleEditField()                        // フィールド編集
handleUploadImage()                      // 画像アップロード
handleDeletePage()                       // ページ削除
handleRevisionAction()                   // リビジョン操作
handleAddUser()                          // ユーザー追加
handleDeleteUser()                       // ユーザー削除
handleAddRedirect()                      // リダイレクト追加
handleDeleteRedirect()                   // リダイレクト削除
```

## 5.4 設定ファイル

- `data/settings/users.json` – ユーザー情報（ユーザー名、bcrypt パスワードハッシュ、ロール）
- `data/settings/settings.json` – サイト設定
- `data/content/pages.json` – ページコンテンツ
- `data/settings/redirects.json` – リダイレクトルール
- `data/revisions/` – リビジョン保存ディレクトリ

## 5.5 セキュリティ考慮事項

- セッションベース認証、ロール制御
- CSRF トークン必須
- XSS 対策: `htmlspecialchars()` によるエスケープ
- レート制限: リビジョン操作 30 req/min/session
- 画像アップロード: MIME 検証、サイズ制限 2MB
- ファイルシステム安全性: パストラバーサル防止

## 5.6 連携エンジン

- `TemplateEngine` – ダッシュボードレンダリング
- `AppContext` – 設定・状態管理
- `Logger` – アクティビティログ
- `DiagnosticEngine` – エラー通知
- `CacheEngine` – キャッシュ無効化
- `ImageOptimizer` – 画像最適化（オプション）

---

# 6. TemplateEngine 設計書

> **実装状態**: ✅ Ver.1.2-26（Ver.1.4-pre でドット記法・フィルタ構文拡張）  
> **ファイルパス**: `engines/TemplateEngine.php`

## 6.1 概要

軽量 PHP テンプレートエンジン。変数展開、フィルタ、条件分岐、ループ、パーシャル読み込みをサポート。

## 6.2 主要機能

### 変数展開
- `{{variable}}` – エスケープ出力
- `{{{variable}}}` – 生出力
- ネストプロパティアクセス: `{{user.name}}`

### フィルタ
`upper`, `lower`, `capitalize`, `truncate:N`, `default:value`, `nl2br`, `trim`, `length`

### 条件分岐
`{{#if var}}...{{else}}...{{/if}}`

### ループ
`{{#each items}}` – 特殊変数 `@index`, `@first`, `@last`

### パーシャル読み込み
`{{> partial}}` – 最大ネスト深度 10

### 診断
未処理タグの警告を `Logger` と `DiagnosticEngine` に記録

## 6.3 API/メソッド一覧

```php
TemplateEngine::render(string $template, array $context) : string

// 内部メソッド
TemplateEngine::processPartials()        // パーシャル処理
TemplateEngine::processEach()            // ループ処理
TemplateEngine::processIf()              // 条件分岐処理
TemplateEngine::processRawVars()         // 生変数処理
TemplateEngine::processVars()            // エスケープ変数処理
TemplateEngine::resolveValue()           // ドット記法解決
TemplateEngine::applyFilter()            // フィルタ適用
TemplateEngine::warnUnprocessed()        // 未処理タグ警告
```

## 6.4 設定

- パーシャルディレクトリ: `themes/{themeSelect}/partials/`
- 最大ネスト深度: 10

## 6.5 セキュリティ考慮事項

- デフォルトで `htmlspecialchars()` によるエスケープ
- 生出力 `{{{...}}}` 使用時は XSS リスクに注意
- ネスト深度制限によるスタックオーバーフロー防止

## 6.6 連携エンジン

- `Logger` – 警告ログ
- `DiagnosticEngine` – 未処理タグ通知

---

# 7. ThemeEngine 設計書

> **実装状態**: ✅ Ver.1.2-13  
> **ファイルパス**: `engines/ThemeEngine.php`

## 7.1 概要

テーマの読み込みと描画を担当。CMS コンテキストと静的ページコンテキストの構築、SEO/OGP タグ生成、メニューパース機能を提供。

## 7.2 主要機能

- **テーマ読み込み**: `load(string $themeSelect)` – テーマ名検証、フォールバック（AP-Default）、`theme.html` 読み込み、`TemplateEngine` で描画
- **テーマ一覧**: `listThemes()` – 利用可能テーマディレクトリを取得
- **CMS コンテキスト構築**: `buildContext()` – `AppContext` から設定取得、管理画面状態、CSRF トークン、編集可能コンテンツ、メニューパースを統合
- **静的ページコンテキスト構築**: `buildStaticContext(string $slug, string $content, array $settings, array $meta = [])` – 管理 UI なし、OGP メタタグ、Twitter Card、JSON-LD 構造化データ（記事/Web ページ）、パンくず JSON-LD、カノニカル URL、画像 URL 処理
- **メニューパース**: `parseMenu(string $menuStr, string $currentPage)` – `<br />\n` 区切りメニュー文字列を配列化（slug, label, active フラグ）

## 7.3 API/メソッド一覧

```php
ThemeEngine::load(string $themeSelect) : void
ThemeEngine::listThemes() : array
ThemeEngine::buildContext() : array
ThemeEngine::buildStaticContext(string $slug, string $content, array $settings, array $meta = []) : array
ThemeEngine::parseMenu(string $menuStr, string $currentPage) : array
```

## 7.4 定数

- `FALLBACK_THEME = 'AP-Default'`
- `THEMES_DIR = 'themes'`

## 7.5 設定ファイル

- `themes/{themeSelect}/theme.html` – テーマテンプレート
- `themes/{themeSelect}/partials/` – パーシャルテンプレート

## 7.6 セキュリティ考慮事項

- テーマ名検証: ディレクトリトラバーサル防止
- フォールバック機構: 無効テーマ時の安全性
- JSON-LD 構造化データ: XSS 対策のためエスケープ必須

## 7.7 連携エンジン

- `TemplateEngine` – テンプレート描画
- `AppContext` – 設定取得
- `AdminEngine` – CSRF トークン、編集可能コンテンツ生成
- `DiagnosticEngine` – エラー通知

---

# 8. UpdateEngine 設計書

> **実装状態**: ✅ Ver.1.0-11  
> **ファイルパス**: `engines/UpdateEngine.php`

## 8.1 概要

プラットフォームの自動更新とバックアップ管理を実装。環境チェック、バックアップ枝刈り、ロールバック、削除機能を提供。

## 8.2 主要機能

- **環境チェック**: `check_environment()` – ZipArchive クラス存在、`allow_url_fopen` 設定、ディレクトリ書き込み可否、空きディスク容量、総合 OK フラグ
- **バックアップ枝刈り**: `prune_old_backups()` – `backup/` ディレクトリをスキャン、最新 `AP_BACKUP_GENERATIONS` 件のみ保持、古いバックアップを再帰的削除、失敗時は `DiagnosticEngine` にログ
- **バックアップ削除**: `delete_backup(string $name)` – バックアップ名検証、ディレクトリ存在確認、再帰的削除、削除エラーカウントと `DiagnosticEngine` ログ
- **更新アクション処理**: `handle_update_action()` – POST `ap_action` ルーティング（check, check_env, apply, list_backups, rollback, delete_backup）、`AdminEngine` ログイン確認、CSRF 検証、JSON レスポンス

## 8.3 API/メソッド一覧

```php
check_environment() : array              // 環境チェック
prune_old_backups() : void               // バックアップ枝刈り
delete_backup(string $name) : array      // バックアップ削除
handle_update_action() : void            // 更新アクション処理

// 想定される他のメソッド（ソースに未記載）
check_update() : array                   // 更新確認
apply_update() : array                   // 更新適用
list_backups() : array                   // バックアップ一覧
rollback(string $name) : array           // ロールバック
```

## 8.4 定数

- `AP_BACKUP_GENERATIONS = 5` – 保持するバックアップ世代数

## 8.5 設定ファイル

- `backup/` – バックアップディレクトリ
- `data/settings/update_cache.json` – 更新キャッシュ（想定）

## 8.6 セキュリティ考慮事項

- ログイン状態確認必須
- CSRF トークン検証
- バックアップ名検証: ディレクトリトラバーサル防止
- ファイル削除エラー処理とログ記録

## 8.7 連携エンジン

- `AdminEngine` – 認証、CSRF 検証
- `DiagnosticEngine` – エラーログ

---

# 9. WebhookEngine 設計書

> **実装状態**: ✅ Ver.1.3-28（イベント発火・非同期通知は実装途中）  
> **ファイルパス**: `engines/WebhookEngine.php`

## 9.1 概要

外向き Webhook の管理を実装。URL 検証、Webhook 追加・削除・有効/無効切替、一覧取得、イベント発火・非同期通知送信（計画中）を提供。

## 9.2 主要機能

- **設定読み込み**: `loadConfig()` – `data/settings/webhooks.json` を読み込み
- **設定保存**: `saveConfig($config)` – JSON 書き込み
- **Webhook 追加**: `addWebhook($url, $label, $events, $secret)` – URL 検証（HTTP/HTTPS、プライベート IP ブロック）、デフォルトイベント（item.created, item.updated, item.deleted, page.updated, build.completed）、タイムスタンプ付与
- **Webhook 削除**: `deleteWebhook($index)` – インデックス指定で削除
- **Webhook 有効/無効切替**: `toggleWebhook($index)` – enabled フラグ切替
- **Webhook 一覧**: `listWebhooks()` – シークレットをマスク（最初4文字のみ表示）
- **イベント発火・通知送信**: 計画中（コード部分実装）

## 9.3 API/メソッド一覧

```php
WebhookEngine::loadConfig() : array
WebhookEngine::saveConfig(array $config) : void
WebhookEngine::addWebhook(string $url, string $label, array $events, string $secret) : bool
WebhookEngine::deleteWebhook(int $index) : bool
WebhookEngine::toggleWebhook(int $index) : bool
WebhookEngine::listWebhooks() : array

// 計画中
WebhookEngine::fire(string $event, array $payload) : void
WebhookEngine::send(string $url, string $secret, array $payload) : void
```

## 9.4 設定ファイル

- `data/settings/webhooks.json` – Webhook 設定（URL, ラベル, イベント, シークレット, enabled, created_at）

## 9.5 セキュリティ考慮事項

- URL スキーム検証: HTTP/HTTPS のみ許可
- プライベート IP ブロック: SSRF 防止
- シークレットマスキング: 一覧取得時に最初4文字のみ表示
- イベント検証: 許可されたイベントのみ

## 9.6 連携エンジン

なし（独立動作）

---

# 10. CacheEngine 設計書

> **実装状態**: ✅ Ver.1.3-28  
> **ファイルパス**: `engines/CacheEngine.php`

## 10.1 概要

ファイルベースの API レスポンスキャッシュを実装。エンドポイント別 TTL 管理、ETag 生成、条件付きリクエスト（304 Not Modified）、キャッシュ無効化を提供。

## 10.2 主要機能

- **キャッシュディレクトリ**: `CACHE_DIR = 'data/cache/api'`
- **TTL 管理**: エンドポイント別（pages, page: 300s; settings: 3600s; collections, collection, item: 60s; search: 120s; デフォルト: 60s）
- **キャッシュ配信**: `serve($endpoint, $key = '')` – キャッシュキー生成、ファイル読み込み、TTL チェック、期限切れ処理、ETag 生成（`hash('xxh128', $content)`）、`Last-Modified` 設定、304 レスポンス対応、キャッシュヒットログ（`DiagnosticEngine`）、適切なキャッシングヘッダー出力
- **キャッシュ保存**: `store($endpoint, $key, $content)` – ディレクトリ作成、アトミック書き込み
- **キャッシュ無効化**: `invalidate($endpoint = null)` – 全キャッシュまたは特定エンドポイントのキャッシュクリア

## 10.3 API/メソッド一覧

```php
CacheEngine::serve(string $endpoint, string $key = '') : bool
CacheEngine::store(string $endpoint, string $key, string $content) : void
CacheEngine::invalidate(?string $endpoint = null) : void
```

## 10.4 定数

- `CACHE_DIR = 'data/cache/api'`
- `TTL` 配列（エンドポイント別秒数）

## 10.5 設定ファイル

- `data/cache/api/{endpoint}/{key}.cache` – キャッシュファイル

## 10.6 セキュリティ考慮事項

- ディレクトリトラバーサル防止: キャッシュキー検証
- アトミック書き込み: 部分的キャッシュファイル防止
- TTL 管理: 古いデータの自動削除

## 10.7 連携エンジン

- `DiagnosticEngine` – キャッシュヒットログ

---

# 11. ImageOptimizer 設計書

> **実装状態**: ✅ Ver.1.3-28  
> **ファイルパス**: `engines/ImageOptimizer.php`

## 11.1 概要

画像最適化エンジン。GD 拡張を使用して画像リサイズ、サムネイル生成、WebP 変換、メモリ使用量チェック、パフォーマンス診断を実装。

## 11.2 主要機能

- **画像リサイズ**: 最大幅 1920px、最大高さ 1920px
- **サムネイル生成**: 幅 400px、高さ 400px、`dirname($path)/thumb/` に保存
- **WebP 変換**: GD サポート時に WebP バージョンを生成
- **メモリチェック**: 必要メモリ = 幅 × 高さ × 4 × 2 バイト、PHP `memory_limit` と比較
- **パフォーマンス診断**: 実行時間 > 1000ms、メモリ使用量 > 10MB 時に `DiagnosticEngine` へログ
- **品質設定**: JPEG 品質 85、WebP 品質 80

## 11.3 API/メソッド一覧

```php
ImageOptimizer::optimize(string $path) : bool

// 内部メソッド（ソースに参照あり、実装未表示）
ImageOptimizer::loadImage(string $path, string $mime)
ImageOptimizer::fitDimensions(int $width, int $height) : array
ImageOptimizer::preserveTransparency($image)
ImageOptimizer::saveImage($image, string $path, string $mime)
ImageOptimizer::generateThumbnail(string $path, string $mime)
ImageOptimizer::generateWebP(string $path, string $mime)
```

## 11.4 定数

- `MAX_WIDTH = 1920`
- `MAX_HEIGHT = 1920`
- `THUMB_WIDTH = 400`
- `THUMB_HEIGHT = 400`
- `JPEG_QUALITY = 85`
- `WEBP_QUALITY = 80`

## 11.5 制限

- GD 拡張必須
- ファイルサイズ上限: 50MB
- サポートされる形式: JPEG, PNG, GIF, WEBP

## 11.6 セキュリティ考慮事項

- ファイル存在確認
- `getimagesize()` による画像検証
- メモリ使用量事前チェック
- ファイルサイズ制限（50MB）

## 11.7 連携エンジン

- `DiagnosticEngine` – パフォーマンスログ、エラー通知

---

# 12. AppContext 設計書

> **実装状態**: ✅ Ver.1.4-pre  
> **ファイルパス**: `engines/AppContext.php`

## 12.1 概要

アプリケーション状態を一元管理するクラス。従来のグローバル変数（`$c`, `$d`, `$host`, `$rp`, `$lstatus`, `$apcredit`, `$hook`）をクラスベースの静的プロパティに置き換え、全エンジンからアクセス可能にする。

## 12.2 主要機能

- **設定管理**: `$config` 配列、`config($key, $default)`, `setConfig($key, $value)`, `getAllConfig()`, `setAllConfig($config)`
- **デフォルト値管理**: `$defaults` 配列、`defaults($section, $key, $default)`, `setDefaults($section, $key, $value)`, `getAllDefaults()`, `setAllDefaults($defaults)`
- **ホスト名**: `$host` 文字列、`host()`, `setHost($host)`
- **リクエストパス**: `$requestPath` 文字列、`requestPath()`, `setRequestPath($path)`
- **ログイン状態**: `$loginStatus` 文字列、`loginStatus()`, `setLoginStatus($status)`
- **クレジット**: `$credit` 文字列、`credit()`, `setCredit($credit)`
- **フック**: `$hooks` 配列（将来拡張用）

## 12.3 API/メソッド一覧

```php
AppContext::config(string $key, $default = null)
AppContext::setConfig(string $key, $value) : void
AppContext::getAllConfig() : array
AppContext::setAllConfig(array $config) : void

AppContext::defaults(string $section, string $key, $default = null)
AppContext::setDefaults(string $section, string $key, $value) : void
AppContext::getAllDefaults() : array
AppContext::setAllDefaults(array $defaults) : void

AppContext::host() : string
AppContext::setHost(string $host) : void

AppContext::requestPath() : string
AppContext::setRequestPath(string $path) : void

AppContext::loginStatus() : string
AppContext::setLoginStatus(string $status) : void

AppContext::credit() : string
AppContext::setCredit(string $credit) : void
```

## 12.4 設定ファイル

なし（メモリ内状態管理）

## 12.5 セキュリティ考慮事項

- グローバル状態の制御: アクセサメソッド経由で管理
- タイプセーフ: 型ヒント付きメソッド

## 12.6 連携エンジン

全エンジン（`ThemeEngine`, `AdminEngine`, `TemplateEngine` など）

---

# 13. Logger 設計書

> **実装状態**: ✅ Ver.1.4-pre  
> **ファイルパス**: `engines/Logger.php`

## 13.1 概要

PSR-3 互換の集中ログ管理クラス。ログレベル（DEBUG, INFO, WARNING, ERROR）、リクエストスコープ ID、ログローテーション（最大 5MB、5 世代）を実装。

## 13.2 主要機能

- **ログレベル**: DEBUG, INFO, WARNING, ERROR（優先度順）
- **最小ログレベル**: 設定可能（デフォルト INFO）
- **リクエスト ID**: トレーサビリティのためリクエスト単位で自動生成
- **ログローテーション**: ファイルサイズ上限 5MB、最大 5 世代
- **ログディレクトリ**: デフォルト `data/logs`
- **ログエントリ**: タイムスタンプ、ログレベル、リクエスト ID、メッセージ、オプションのコンテキスト配列

## 13.3 API/メソッド一覧

```php
Logger::init(string $minLevel = 'INFO', string $logDir = 'data/logs') : void
Logger::debug(string $message, array $context = []) : void
Logger::info(string $message, array $context = []) : void
Logger::warning(string $message, array $context = []) : void
Logger::error(string $message, array $context = []) : void

// 内部メソッド
Logger::log(string $level, string $message, array $context = []) : void
Logger::rotate(string $logFile) : void
```

## 13.4 定数

- `LEVELS = ['DEBUG' => 10, 'INFO' => 20, 'WARNING' => 30, 'ERROR' => 40]`
- デフォルト最小レベル: `INFO`
- デフォルトログディレクトリ: `data/logs`
- ローテーション: 最大ファイルサイズ 5MB、最大世代数 5

## 13.5 設定ファイル

- `data/logs/app.log` – メインログファイル
- `data/logs/app.log.1` ～ `app.log.5` – ローテーションファイル

## 13.6 セキュリティ考慮事項

- ログファイルアクセス制限: `.htaccess` で保護推奨
- 機密情報のログ記録禁止: パスワード、トークン等
- ログローテーション: ディスク容量管理

## 13.7 連携エンジン

全エンジン（`TemplateEngine`, `ImageOptimizer`, `AdminEngine` など）

---

# 14. MailerEngine 設計書

> **実装状態**: ✅ Ver.1.4-pre  
> **ファイルパス**: `engines/MailerEngine.php`

## 14.1 概要

メール送信を抽象化するクラス。ヘッダーサニタイゼーション、リトライ機能、テストモードを実装。

## 14.2 主要機能

- **メール送信**: `send($to, $subject, $message, $from, $replyTo = '', $extraHeaders = [])` – ヘッダーサニタイゼーション、ヘッダー構築、リトライ（最大 2 回、1 秒遅延）、成功/失敗ログ
- **テストモード**: `enableTestMode()`, `disableTestMode()`, `getTestEmails()` – テスト時にメール実送信せず、配列に保存
- **ヘッダーインジェクション対策**: 改行文字除去

## 14.3 API/メソッド一覧

```php
MailerEngine::send(string $to, string $subject, string $message, string $from, string $replyTo = '', array $extraHeaders = []) : bool
MailerEngine::enableTestMode() : void
MailerEngine::disableTestMode() : void
MailerEngine::getTestEmails() : array
```

## 14.4 定数

- `MAX_RETRIES = 2`
- `RETRY_DELAY = 1` (秒)

## 14.5 設定ファイル

なし（メモリ内状態管理）

## 14.6 セキュリティ考慮事項

- ヘッダーインジェクション対策: `\r\n` 除去
- 入力検証: メールアドレス形式チェック推奨
- SMTP 認証: PHP `mail()` 関数の制限に注意

## 14.7 連携エンジン

- `AdminEngine` – お問い合わせフォーム、通知メール送信

---

## 変更履歴

本ドキュメントの変更履歴は、以下のファイルで一元管理されています:

👉 **[docs/DOCUMENT_CHANGELOG.md](./DOCUMENT_CHANGELOG.md#-engine_designmd)** を参照してください。

---

*Adlaire License Ver.2.0 — 社内限り*
