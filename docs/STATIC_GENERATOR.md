# StaticEngine 設計草稿

> ステータス: 草稿（Phase 2 将来実装）
> 分類: 社内限り

---

## 概要

StaticEngine は AdlairePlatform のコンテンツを静的 HTML ファイルとして出力するエンジンです。
動的 PHP を排除することで、CDN・静的ホスティングへのデプロイを可能にします。

---

## 想定ファイルパス

```
engines/StaticEngine.php
```

---

## 機能要件

### 必須

- 全ページを `static/{slug}/index.html` として出力
- テーマの CSS・JS をそのまま `static/` にコピー
- メニューの静的生成（アクティブクラスは各ページごとに差し込み）
- サイト設定（タイトル・説明等）を各 HTML に反映

### オプション

- 差分ビルド（更新されたページのみ再生成）
- `static/` ディレクトリのクリーンビルド
- ビルド完了後の ZIP 生成（デプロイパッケージ）

---

## クラス設計（仮）

```php
class StaticEngine {
    private string $outputDir = 'static';

    public function buildAll(): array
    // 全ページをビルドし、生成ファイル数を返す

    public function buildPage(string $slug, string $content): void
    // 単一ページの HTML を生成

    public function copyAssets(): void
    // themes/{active}/style.css などを static/ にコピー

    public function clean(): void
    // static/ ディレクトリをクリア
}
```

---

## 出力構造（案）

```
static/
├─ index.html           # home ページ
├─ example/
│  └─ index.html        # example ページ
└─ assets/
   └─ style.css
```

---

## 管理画面への統合

`settings()` 関数に「静的書き出し」ボタンを追加し、`ap_action=generate_static` で
`StaticEngine::buildAll()` を呼び出す。

```
POST ap_action=generate_static
  → handle_update_action() で分岐
  → StaticEngine::buildAll()
  → JSON レスポンス
```

---

## 未解決事項

- 動的要素（インプレイス編集・管理パネル）の扱い
  - ログイン時のみ動的版を提供し、静的版は閲覧専用にする方針が妥当
- URL スキーム（`/slug/` vs `/slug.html`）の統一
- 外部リソース（CDN 画像等）の扱い

---

*このドキュメントは草稿です。実装開始前に要件を再確認してください。*
