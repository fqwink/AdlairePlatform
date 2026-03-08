# ApiEngine（ヘッドレスCMS / 公開REST API）設計書

> **ドキュメントバージョン**: Ver.0.2-1
> **ステータス**: ✅ 確定（設計承認済み）
> **作成日**: 2026-03-06
> **最終更新**: 2026-03-08
> **所有者**: Adlaire Group
> **分類**: 社内限り

---

## 目次

1. [概要](#1-概要)
2. [StaticEngine との関係](#2-staticengine-との関係)
3. [エンドポイント設計](#3-エンドポイント設計)
4. [レスポンス形式](#4-レスポンス形式)
5. [認証方式](#5-認証方式)
6. [クラス設計](#6-クラス設計)
7. [index.php への統合](#7-indexphp-への統合)
8. [CORS 設定](#8-cors-設定)
9. [セキュリティ](#9-セキュリティ)
10. [未解決事項](#10-未解決事項)
11. [変更履歴](#11-変更履歴)

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
| 別オリジン（Static-Only モード）での CORS 設定 | `.htaccess` に手動追記（セクション 8 参照） |

---

## 3. エンドポイント設計

### 3.1 パラメータ名前空間

公開 API は `?ap_api=` パラメータで呼び出します（既存の `?ap_action=` と共存・衝突なし）。

### 3.2 公開エンドポイント（認証不要・静的サイトから呼び出す）

| メソッド | `ap_api` 値 | 追加パラメータ | 説明 |
|---------|------------|--------------|------|
| POST | `contact` | `name`, `email`, `message` | お問い合わせフォーム送信（PHP `mail()` 使用） |
| GET | `search` | `q=<検索語>` | ページ全文検索（`stripos` ベース） |
| GET | `page` | `slug=<スラッグ>` | 単一ページコンテンツの JSON 取得 |
| GET | `pages` | — | 全ページ一覧（slug + 先頭テキスト） |
| GET | `settings` | — | 公開サイト設定（title, description, keywords）のみ |

### 3.3 管理エンドポイント（要認証・将来実装）

| メソッド | `ap_api` 値 | 説明 | ステータス |
|---------|------------|------|-----------|
| POST | `page_upsert` | ページ作成・更新 | 🔜 将来予定 |
| DELETE | `page_delete` | ページ削除（`slug` パラメータ） | 🔜 将来予定 |

---

## 4. レスポンス形式

すべてのレスポンスは JSON 形式で統一します。

```json
// 成功
{ "ok": true, "data": { ... } }

// エラー
{ "ok": false, "error": "エラーメッセージ" }
```

### レスポンス例

```json
// GET ?ap_api=page&slug=about
{
  "ok": true,
  "data": {
    "slug": "about",
    "content": "<p>概要ページのコンテンツ</p>",
    "updated_at": "2026-03-08"
  }
}

// GET ?ap_api=pages
{
  "ok": true,
  "data": [
    { "slug": "index",   "preview": "トップページのコンテンツ" },
    { "slug": "about",   "preview": "概要ページのコンテンツ" },
    { "slug": "contact", "preview": "お問い合わせページ" }
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

// POST ?ap_api=contact（成功）
{ "ok": true, "data": { "message": "送信しました。" } }

// エラー
{ "ok": false, "error": "ページが見つかりません" }
```

---

## 5. 認証方式

### 5.1 公開エンドポイント（認証不要）

セクション 3.2 の公開エンドポイントは認証不要です。ただし以下の保護を適用します:

- **レート制限**: `contact` エンドポイントにはスパム対策として IP ベースのレート制限を適用する（`login_attempts.json` の仕組みを流用）
- **ハニーポット**: お問い合わせフォームに hidden input を配置してボット送信を検出
- **入力バリデーション**: `email` の形式検証・`message` の最大文字数制限

### 5.2 管理エンドポイント（将来）

| 方式 | ステータス | 説明 |
|------|-----------|------|
| セッション Cookie（現行） | ⚠️ 暫定 | 現行認証をそのまま流用。SPA との相性が悪い |
| API キー（静的） | 🔜 検討中 | シンプル。`data/settings/api_keys.json` に保管 |
| Bearer トークン | ❓ 未定 | SPA・外部サービスから利用しやすいが実装コストあり |

管理エンドポイント実装時に認証方式を確定します。

---

## 6. クラス設計

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

    /** GET ?ap_api=search&q= — stripos による全文検索 */
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
}
```

---

## 7. index.php への統合

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

---

## 8. CORS 設定

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

## 9. セキュリティ

| 脅威 | 対策 |
|------|------|
| スパムフォーム送信 | IP レート制限 + ハニーポットフィールド |
| 機密情報の漏洩 | `settings` エンドポイントは `title`・`description`・`keywords` のみ返し、`auth.json` 等は絶対に含めない |
| パストラバーサル | `slug` パラメータを `^[a-zA-Z0-9_-]+$` でバリデーション |
| XSS（JSON インジェクション） | `json_encode()` の `JSON_HEX_TAG \| JSON_HEX_AMP` オプションを使用 |
| 過剰な CORS 許可 | Static-Only 時のみ特定オリジンを許可。ワイルドカード `*` は禁止 |
| 未認証の管理操作 | 管理エンドポイント（将来）には必ず `is_loggedin()` + CSRF 検証を適用 |

---

## 10. 未解決事項

| 事項 | ステータス | 内容 |
|------|-----------|------|
| 管理エンドポイントの認証方式 | ❓ 未定 | API キー vs Bearer トークン。実装時に確定 |
| `contact` のメール送信設定 | ⚠️ 暫定 | PHP `mail()` を使用。宛先アドレスを `settings.json` の `contact_email` キーに保存 |
| 検索の日本語対応 | ⚠️ 暫定 | `stripos` は半角スペース区切りのみ。`mb_strpos` で多バイト対応するが、形態素解析は行わない |
| API バージョニング | ❓ 未定 | `?ap_api_v=1&...` 等のバージョン管理は当面不要。必要になれば検討 |
| メディアアップロード API | 🔜 将来予定 | 外部フロントエンドから画像をアップロードするエンドポイント |
| `api_origin` の管理画面設定 | 🔜 将来予定 | Static-Only モード選択時に管理パネルで入力欄を表示 |

---

## 11. 変更履歴

| バージョン | 日付 | 変更内容 |
|------------|------|---------|
| Ver.0.2-1 | 2026-03-08 | 設計承認。StaticEngine との連携を主用途として明確化。`?ap_api=` 名前空間確定。`contact`・`search` エンドポイント追加。認証方式・CORS・セキュリティを具体化 |
| Ver.0.1-1 | 2026-03-06 | 初版草稿（ヘッドレス CMS 単体の草稿。`?api=` パラメータ、クラス骨格のみ） |

---

*Adlaire License Ver.2.0 — 社内限り*
