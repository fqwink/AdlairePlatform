# AdlairePlatform — エンジン技術設計書リファレンス

> **ドキュメントバージョン**: Ver.1.0-2  
> **作成日**: 2026-03-10  
> **最終更新**: 2026-03-10（改名・相互参照追加）  
> **所有者**: Adlaire Group  
> **分類**: 社内限り  
> **対象バージョン**: Ver.1.4-pre  
> **バージョニング規則**: [AFE/VERSIONING.md](https://github.com/fqwink/AdlaireGroup-Documents-Repository/blob/main/AFE/VERSIONING.md)

> **関連ドキュメント**:  
> - 設計方針: [AdlairePlatform_Design.md](AdlairePlatform_Design.md)  
> - アーキテクチャ: [ARCHITECTURE.md](ARCHITECTURE.md)  
> - 機能仕様: [SPECIFICATION.md](SPECIFICATION.md)  
> - セキュリティ: [SECURITY_POLICY.md](SECURITY_POLICY.md)

---

## 📋 統合ドキュメント目的

本ドキュメントは、AdlairePlatform の既存エンジン設計書を1つのファイルに統合したものです。
実装済みで専用設計書が存在するエンジンのみを収録しています。

**統合対象エンジン**:
1. StaticEngine（静的サイト生成）
2. ApiEngine（ヘッドレスCMS / REST API）
3. DiagnosticEngine（診断・テレメトリ）
4. GitEngine（Git連携）
5. CollectionEngine（コレクション管理）
6. MarkdownEngine（Markdown処理）
7. WorkflowEngine（レビューワークフロー）

**除外エンジン**: AdminEngine, TemplateEngine, ThemeEngine, UpdateEngine, WebhookEngine, CacheEngine, ImageOptimizer, AppContext, Logger, MailerEngine（実装済みだが専用設計書なし）

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

## 変更履歴

| バージョン | 日付 | 変更内容 |
|------------|------|---------|
| Ver.1.0-1 | 2026-03-10 | 初版。StaticEngine, ApiEngine, DiagnosticEngine, Git連携エンジン群の設計書を統合 |

---

*Adlaire License Ver.2.0 — 社内限り*
