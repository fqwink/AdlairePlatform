# ApiEngine（ヘッドレスCMS）設計草稿

> ステータス: 草稿（Phase 2 将来実装）
> 分類: 社内限り

---

## 概要

ApiEngine は AdlairePlatform を REST API バックエンドとして機能させるエンジンです。
フロントエンドをフレームワーク（Next.js, Nuxt, SvelteKit 等）で構築し、
AdlairePlatform をコンテンツ管理バックエンドとして利用するヘッドレスCMS構成を実現します。

---

## 想定ファイルパス

```
engines/ApiEngine.php
```

---

## エンドポイント設計（仮）

### 公開エンドポイント（認証不要）

| メソッド | パス | 説明 |
|---------|------|------|
| GET | `?api=pages` | 全ページ一覧（slug + タイトル） |
| GET | `?api=page&slug={slug}` | 単一ページのコンテンツ |
| GET | `?api=settings` | 公開サイト設定（title, description 等） |

### 管理エンドポイント（要認証）

| メソッド | パス | 説明 |
|---------|------|------|
| POST | `?api=page` | ページ作成・更新 |
| DELETE | `?api=page&slug={slug}` | ページ削除 |

---

## レスポンス形式

```json
// GET ?api=page&slug=home
{
  "slug": "home",
  "content": "<h3>Your website...</h3>",
  "updated_at": "2026-03-07"
}

// エラー
{
  "error": "ページが見つかりません"
}
```

---

## クラス設計（仮）

```php
class ApiEngine {
    public static function handle(): void
    // ?api= パラメータを解析してルーティング

    private static function getPages(): array
    // 全ページ一覧を返す

    private static function getPage(string $slug): array
    // 単一ページを返す（404 対応）

    private static function upsertPage(string $slug, string $content): void
    // ページ作成・更新（要認証・CSRF）
}
```

---

## index.php への統合

```php
// handle_update_action() の前後に追加
ApiEngine::handle();  // ?api= があれば処理して exit
```

---

## CORS 対応

フロントエンドが別オリジンからアクセスする場合は `.htaccess` に追加：

```apache
Header always set Access-Control-Allow-Origin "https://your-frontend.com"
Header always set Access-Control-Allow-Methods "GET, POST, DELETE, OPTIONS"
Header always set Access-Control-Allow-Headers "Content-Type, X-CSRF-TOKEN"
```

---

## 認証方式の検討

| 方式 | メリット | デメリット |
|-----|---------|-----------|
| セッション Cookie（現行） | 実装済み | SPA との相性が悪い |
| Bearer トークン | SPA・外部サービスから利用しやすい | 実装コストあり |
| API キー（静的） | シンプル | キー管理が必要 |

Phase 2 では Bearer トークンまたは API キー方式を採用予定。

---

## 未解決事項

- 認証方式の確定
- レート制限の API エンドポイントへの適用
- バージョニング（`?api=v1/page` 等）の要否
- メディア（画像等）のアップロード API

---

*このドキュメントは草稿です。実装開始前に要件を再確認してください。*
