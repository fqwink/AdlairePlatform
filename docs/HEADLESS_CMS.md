# ApiEngine（ヘッドレスCMS / 公開REST API）設計書

> **ドキュメントバージョン**: Ver.0.3-1
> **ステータス**: ✅ 確定（設計承認済み）
> **作成日**: 2026-03-06
> **最終更新**: 2026-03-08
> **所有者**: Adlaire Group
> **分類**: 社内限り
>
> **⚠️ Ver.1.2系終了通知**: Ver.1.2系は Ver.1.2-26 をもって終了しました。
> **Ver.1.3系にて ApiEngine の実装を予定しています。**
> 本設計書（Ver.0.3-1）を基に実装を開始します。

---

## 目次

1. [概要](#1-概要)
2. [StaticEngine との関係](#2-staticengine-との関係)
3. [エンドポイント設計](#3-エンドポイント設計)
4. [レスポンス形式](#4-レスポンス形式)
5. [認証方式](#5-認証方式)
6. [レート制限](#6-レート制限)
7. [クラス設計](#7-クラス設計)
8. [index.php への統合](#8-indexphp-への統合)
9. [クライアントサイド JS（ap-api-client.js）](#9-クライアントサイド-jsap-api-clientjs)
10. [CORS 設定](#10-cors-設定)
11. [セキュリティ](#11-セキュリティ)
12. [未解決事項](#12-未解決事項)
13. [変更履歴](#13-変更履歴)

---

## 1. 概要

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

---

## 2. StaticEngine との関係

StaticEngine（`docs/STATIC_GENERATOR.md`）と ApiEngine は連携して動作します。

| 役割 | 担当エンジン |
|------|------------|
| 静的 HTML の書き出し | StaticEngine |
| 静的サイトへの `ap-api-client.js` 配置 | StaticEngine（assets コピー時） |
| フォーム送信・検索 等の動的処理 | ApiEngine |
| 同一オリジン（Static-First モード）での CORS 不要 | StaticEngine の .htaccess が静的配信・PHP は同一オリジン |
| 別オリジン（Static-Only モード）での CORS 設定 | `.htaccess` に手動追記（セクション 10 参照） |

---

## 3. エンドポイント設計

### 3.1 パラメータ名前空間

公開 API は `?ap_api=` パラメータで呼び出します（既存の `?ap_action=` と共存・衝突なし）。

### 3.2 公開エンドポイント（認証不要・静的サイトから呼び出す）

| メソッド | `ap_api` 値 | 追加パラメータ | 説明 |
|---------|------------|--------------|------|
| POST | `contact` | `name`, `email`, `message` | お問い合わせフォーム送信（PHP `mail()` 使用） |
| GET | `search` | `q=<検索語>` | ページ全文検索（`mb_stripos` ベース） |
| GET | `page` | `slug=<スラッグ>` | 単一ページコンテンツの JSON 取得 |
| GET | `pages` | — | 全ページ一覧（slug + 先頭テキスト） |
| GET | `settings` | — | 公開サイト設定（title, description, keywords）のみ |

### 3.3 管理エンドポイント（要認証・将来実装）

| メソッド | `ap_api` 値 | 説明 | ステータス |
|---------|------------|------|-----------|
| POST | `page_upsert` | ページ作成・更新 | 🔜 将来予定 |
| DELETE | `page_delete` | ページ削除（`slug` パラメータ） | 🔜 将来予定 |

### 3.4 パラメータバリデーション

| パラメータ | バリデーション規則 |
|-----------|-----------------|
| `slug` | `^[a-zA-Z0-9_-]+$`（正規表現・パストラバーサル防止） |
| `q`（検索語） | `mb_strlen($q) >= 1 && mb_strlen($q) <= 100`・空文字は 400 エラー |
| `name` | `mb_strlen($name) >= 1 && mb_strlen($name) <= 100` |
| `email` | `filter_var($email, FILTER_VALIDATE_EMAIL)` |
| `message` | `mb_strlen($message) >= 1 && mb_strlen($message) <= 5000` |

---

## 4. レスポンス形式

すべてのレスポンスは JSON 形式で統一します。

```json
// 成功
{ "ok": true, "data": { ... } }

// エラー
{ "ok": false, "error": "エラーメッセージ" }
```

### 4.1 HTTP ステータスコード

| コード | 用途 |
|--------|------|
| `200` | 正常レスポンス |
| `400` | バリデーションエラー（不正パラメータ・必須項目不足） |
| `404` | 指定リソースが存在しない（`page` エンドポイントでスラッグ不在） |
| `429` | レート制限超過（`contact` エンドポイント） |
| `500` | サーバー内部エラー（メール送信失敗等） |

### 4.2 レスポンス例

```json
// GET ?ap_api=page&slug=about
{
  "ok": true,
  "data": {
    "slug": "about",
    "content": "<p>概要ページのコンテンツ</p>"
  }
}

// GET ?ap_api=pages
{
  "ok": true,
  "data": [
    { "slug": "index",   "preview": "トップページのコンテンツ..." },
    { "slug": "about",   "preview": "概要ページのコンテンツ..." },
    { "slug": "contact", "preview": "お問い合わせページ..." }
  ]
}

// GET ?ap_api=search&q=サービス
{
  "ok": true,
  "data": {
    "query": "サービス",
    "results": [
      { "slug": "index", "preview": "...サービスの概要..." },
      { "slug": "about", "preview": "...サービス提供..." }
    ]
  }
}

// GET ?ap_api=settings
{
  "ok": true,
  "data": {
    "title": "サイトタイトル",
    "description": "サイト説明文",
    "keywords": "キーワード1, キーワード2"
  }
}

// POST ?ap_api=contact（成功）
{ "ok": true, "data": { "message": "送信しました。" } }

// エラー
{ "ok": false, "error": "ページが見つかりません" }
```

### 4.3 `pages` エンドポイントの preview 生成

`preview` フィールドは以下のロジックで生成します:

```php
// HTML タグを除去 → 先頭 120 文字を切り出し → 末尾に「...」を付与
$text = mb_substr(strip_tags($content), 0, 120, 'UTF-8');
$preview = (mb_strlen(strip_tags($content), 'UTF-8') > 120) ? $text . '...' : $text;
```

---

## 5. 認証方式

### 5.1 公開エンドポイント（認証不要）

セクション 3.2 の公開エンドポイントは認証不要です。ただし以下の保護を適用します:

- **レート制限**: `contact` エンドポイントにはスパム対策として IP ベースのレート制限を適用する（セクション 6 参照）
- **ハニーポット**: お問い合わせフォームに hidden input（`website` フィールド）を配置してボット送信を検出
- **入力バリデーション**: セクション 3.4 のバリデーション規則を適用

### 5.2 管理エンドポイント（将来）

| 方式 | ステータス | 説明 |
|------|-----------|------|
| セッション Cookie（現行） | ⚠️ 暫定 | 現行認証をそのまま流用。SPA との相性が悪い |
| API キー（静的） | 🔜 検討中 | シンプル。`data/settings/api_keys.json` に保管 |
| Bearer トークン | ❓ 未定 | SPA・外部サービスから利用しやすいが実装コストあり |

管理エンドポイント実装時に認証方式を確定します。

---

## 6. レート制限

### 6.1 contact エンドポイント

`login_attempts.json` の仕組みを流用した IP ベースのレート制限を適用します。

| パラメータ | 値 | 説明 |
|-----------|-----|------|
| 最大試行回数 | 5回 / 15分 | 15分あたり5回まで送信可能 |
| ロックアウト期間 | 15分 | 超過時に 429 レスポンスを返す |
| 記録ファイル | `data/settings/login_attempts.json` | 既存のレート制限ファイルを共有 |
| 識別キー | `contact_<IP>` | ログイン試行と区別するためプレフィックスを付与 |

### 6.2 ハニーポットフィールド

```html
<!-- フォームに不可視の hidden input を配置 -->
<input type="text" name="website" style="display:none" tabindex="-1" autocomplete="off">
```

`website` フィールドに値が入っていた場合、ボットと判定してリクエストを無視します（200 レスポンスを返すがメールは送信しない）。

---

## 7. クラス設計

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

### 7.1 `handle()` メソッドの処理フロー

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

### 7.2 `search()` の実装方針

```php
private static function search(string $query): array {
    $pages   = json_read('pages.json', content_dir());
    $results = [];
    $query   = mb_strtolower($query, 'UTF-8');

    foreach ($pages as $slug => $content) {
        $text = mb_strtolower(strip_tags($content), 'UTF-8');
        if (mb_strpos($text, $query, 0, 'UTF-8') !== false) {
            // マッチ箇所の前後を含むプレビューを生成
            $pos   = mb_strpos($text, $query, 0, 'UTF-8');
            $start = max(0, $pos - 30);
            $preview = mb_substr($text, $start, 100, 'UTF-8');
            if ($start > 0) $preview = '...' . $preview;
            if ($start + 100 < mb_strlen($text, 'UTF-8')) $preview .= '...';

            $results[] = ['slug' => $slug, 'preview' => $preview];
        }
    }

    return ['query' => $query, 'results' => $results];
}
```

### 7.3 `sendContact()` の実装方針

```php
private static function sendContact(): array {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        self::jsonError('POST メソッドが必要です', 405);
    }

    // ハニーポット検出（ボットには成功を装う）
    if (!empty($_POST['website'])) {
        self::jsonResponse(true, ['message' => '送信しました。']);
    }

    // バリデーション
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($name === '' || mb_strlen($name, 'UTF-8') > 100) {
        self::jsonError('名前を入力してください（100文字以内）');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        self::jsonError('有効なメールアドレスを入力してください');
    }
    if ($message === '' || mb_strlen($message, 'UTF-8') > 5000) {
        self::jsonError('メッセージを入力してください（5000文字以内）');
    }

    // レート制限
    self::checkContactRate();

    // メール送信
    $settings = json_read('settings.json', settings_dir());
    $to       = $settings['contact_email'] ?? '';
    if ($to === '') {
        self::jsonError('送信先が設定されていません', 500);
    }

    $subject = '【' . ($settings['title'] ?? 'AP') . '】お問い合わせ: ' . $name;
    $body    = "名前: {$name}\nメール: {$email}\n\n{$message}";
    $headers = "From: {$email}\r\nReply-To: {$email}\r\nContent-Type: text/plain; charset=UTF-8";

    if (!mail($to, $subject, $body, $headers)) {
        self::jsonError('メール送信に失敗しました', 500);
    }

    return ['message' => '送信しました。'];
}
```

---

## 8. index.php への統合

`upload_image()` の直後、`handle_update_action()` の前に追記します:

```php
// index.php（require 部分）
require_once __DIR__ . '/engines/ApiEngine.php';
require_once __DIR__ . '/engines/StaticEngine.php';

// index.php（メイン処理、upload_image() の直後）
ApiEngine::handle();     // ?ap_api= があれば JSON を返して exit
StaticEngine::handle();  // ?ap_action=generate_static_* があれば処理して exit
```

### 処理順序の設計意図

```
HTTP Request
    ↓
upload_image()          ← ap_action=upload_image（画像アップロード）
    ↓
ApiEngine::handle()     ← ap_api= が あれば処理して exit（公開 API・認証不要）
    ↓
StaticEngine::handle()  ← ap_action=generate_static_* があれば処理して exit
    ↓
handle_update_action()  ← ap_action= の残り（要認証・管理操作）
    ↓
edit()                  ← フィールド保存
    ↓
通常ページレンダリング
```

### settings.json への追加キー

`contact` エンドポイントの宛先設定として `settings.json` に以下のキーを追加します:

```json
{
  "title": "サイトタイトル",
  "description": "サイト説明文",
  "keywords": "キーワード1, キーワード2",
  "copyright": "© 2026 Adlaire Group",
  "menu": "ページ名1\nページ名2",
  "themeSelect": "AP-Default",
  "contact_email": "admin@example.com"
}
```

管理パネル（`settings()` 関数）に `contact_email` の入力欄を追加する必要があります。

---

## 9. クライアントサイド JS（ap-api-client.js）

`engines/JsEngine/ap-api-client.js` は静的サイトに配置する軽量バニラ JS クライアントです。
WYSIWYG や管理 UI は含みません。依存ライブラリなし・ES5 互換。

### 9.1 API

| API | 説明 |
|-----|------|
| `window.AP.api(action, params)` | 公開 API 汎用呼び出し関数。`Promise` を返す |
| `window.AP.origin` | API オリジン（`currentScript.src` から自動取得・Static-Only 対応） |

### 9.2 フォーム自動バインド

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

### 9.3 API オリジン解決

| モード | オリジン | 解決方法 |
|--------|---------|---------|
| Static-First（同一オリジン） | 同一ホスト | `currentScript.src` のオリジン部分を使用 |
| Static-Only（別ホスト） | `settings.json` の `api_origin` | `<script src="https://cms.example.com/engines/JsEngine/ap-api-client.js">` のオリジン |

### 9.4 実装概要

```javascript
(function() {
  'use strict';
  var script = document.currentScript;
  var origin = script ? script.src.replace(/\/engines\/.*$|\/assets\/.*$/, '') : '';

  window.AP = {
    origin: origin,
    api: function(action, params) {
      var url = origin + '/?ap_api=' + encodeURIComponent(action);
      var isPost = (action === 'contact');
      var opts = { method: isPost ? 'POST' : 'GET' };

      if (isPost) {
        opts.headers = { 'Content-Type': 'application/x-www-form-urlencoded' };
        opts.body = new URLSearchParams(params).toString();
      } else if (params) {
        Object.keys(params).forEach(function(k) {
          url += '&' + encodeURIComponent(k) + '=' + encodeURIComponent(params[k]);
        });
      }

      return fetch(url, opts).then(function(r) { return r.json(); });
    }
  };

  // フォーム自動バインド
  document.addEventListener('DOMContentLoaded', function() {
    var forms = document.querySelectorAll('form.ap-contact');
    forms.forEach(function(form) {
      form.addEventListener('submit', function(e) {
        e.preventDefault();
        var fd = new FormData(form);
        var params = {};
        fd.forEach(function(v, k) { params[k] = v; });

        AP.api('contact', params).then(function(res) {
          form.dispatchEvent(new CustomEvent('ap:done', { detail: res }));
        }).catch(function(err) {
          form.dispatchEvent(new CustomEvent('ap:done', {
            detail: { ok: false, error: '通信エラーが発生しました' }
          }));
        });
      });
    });
  });
})();
```

---

## 10. CORS 設定

### Static-First モード（同一オリジン・推奨）

同一サーバー上で静的配信と PHP API を共存させる場合、CORS は**不要**です。
静的サイトの HTML と PHP API が同一オリジン上にあるためです。

### Static-Only モード（別ホストデプロイ）

静的サイトを CDN や別ホストにデプロイする場合のみ CORS 設定が必要です。
`.htaccess` に以下を追加します（`api_origin` は管理パネルで設定）:

```apache
<IfModule mod_headers.c>
    # 特定オリジンのみ許可（ワイルドカード * は使用しない）
    SetEnvIf Origin "^https://your-static-site\.com$" ORIGIN_OK
    Header always set Access-Control-Allow-Origin "%{HTTP_ORIGIN}e" env=ORIGIN_OK
    Header always set Access-Control-Allow-Methods "GET, POST, OPTIONS"
    Header always set Access-Control-Allow-Headers "Content-Type"
    Header always set Access-Control-Max-Age "3600"

    # OPTIONS プリフライトリクエストへの対応
    RewriteCond %{REQUEST_METHOD} OPTIONS
    RewriteRule ^ - [R=204,L]
</IfModule>
```

---

## 11. セキュリティ

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

## 12. 未解決事項

| 事項 | ステータス | 内容 |
|------|-----------|------|
| 管理エンドポイントの認証方式 | ❓ 未定 | API キー vs Bearer トークン。実装時に確定 |
| `contact` のメール送信設定 | ⚠️ 暫定 | PHP `mail()` を使用。宛先アドレスを `settings.json` の `contact_email` キーに保存。管理パネルに入力欄を追加予定 |
| 検索の日本語対応 | ⚠️ 暫定 | `mb_stripos` / `mb_strpos` で多バイト対応するが、形態素解析は行わない。半角・全角スペース両対応 |
| API バージョニング | ❓ 未定 | `?ap_api_v=1&...` 等のバージョン管理は当面不要。必要になれば検討 |
| メディアアップロード API | 🔜 将来予定 | 外部フロントエンドから画像をアップロードするエンドポイント |
| `api_origin` の管理画面設定 | 🔜 将来予定 | Static-Only モード選択時に管理パネルで入力欄を表示 |
| `pages` のページネーション | ❓ 未定 | ページ数が大量になった場合に `?limit=&offset=` 対応を検討 |

---

## 13. 変更履歴

| バージョン | 日付 | 変更内容 |
|------------|------|---------|
| Ver.0.3-1 | 2026-03-08 | レート制限パラメータ具体化（セクション6新設）。パラメータバリデーション規則を追加（セクション3.4）。HTTP ステータスコード体系を整理（セクション4.1）。preview 生成ロジック明記（セクション4.3）。search() / sendContact() の実装方針をコード付きで追加（セクション7.2-7.3）。ap-api-client.js 仕様をセクション9に統合。settings.json への contact_email 追加を明記（セクション8）。メールヘッダインジェクション対策を追加（セクション11）。ページネーション検討事項を追加（セクション12） |
| Ver.0.2-1 | 2026-03-08 | 設計承認。StaticEngine との連携を主用途として明確化。`?ap_api=` 名前空間確定。`contact`・`search` エンドポイント追加。認証方式・CORS・セキュリティを具体化 |
| Ver.0.1-1 | 2026-03-06 | 初版草稿（ヘッドレス CMS 単体の草稿。`?api=` パラメータ、クラス骨格のみ） |

---

*Adlaire License Ver.2.0 — 社内限り*
