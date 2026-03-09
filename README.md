# AdlairePlatform（略：AP／翻訳：アドレイル・プラットホーム）

> ⚠️ **本リポジトリのソースコードは参照目的で公開されています。**
> **Adlaire License Ver.2.0 に基づき、改変・再配布・商用利用は禁止されています。**
> 詳細は [docs/Licenses/LICENSE_Ver.2.0.md](docs/Licenses/LICENSE_Ver.2.0.md) を参照してください。

APは、デザインテンプレートエンジンを搭載したフラットファイルベースの軽量 CMS フレームワークです。
データベース不要で動作し、各機能を小さなエンジン単位として設計することで、段階的なシステム拡張が可能です。

> **現在のバージョン**: Ver.1.3-28

---

## 実装済み機能

### コンテンツ管理
- **フラットファイル JSON ストレージ** — `data/settings/settings.json` / `data/content/pages.json` / `data/settings/auth.json` によるデータベース不要の構成
- **インプレイス編集** — ログイン中は任意のコンテンツ領域をクリックしてその場で編集・保存（Fetch API）
- **マルチページ対応** — スラッグベースの URL ルーティングにより複数ページを管理
- **設定管理** — サイトタイトル・説明・キーワード・著作権表示・メニュー・テーマをブラウザ上から変更
- **コレクション管理** — Markdown ベースのコレクション（ブログ・ニュース等）を管理（`engines/CollectionEngine.php`）
- **Markdown エンジン** — フロントマター付き Markdown をパース・HTML 変換（`engines/MarkdownEngine.php`）

### テーマエンジン
- **テーマ切替** — `themes/` ディレクトリに配置したテーマを管理画面からリアルタイムで切替
- **同梱テーマ** — `AP-Default`（シンプル）、`AP-Adlaire`（Adlaire デザイン）の 2 種類
- **テーマ構造** — `theme.html`（テンプレートエンジン方式・PHP フリー）＋ `style.css`
- **テンプレートエンジン** — `engines/TemplateEngine.php` による軽量テンプレートエンジン（`{{var}}` / `{{{raw}}}` / `{{#if}}` / `{{#each}}` / `{{> partial}}`）
- **エンジン分離** — `engines/ThemeEngine.php` によるテーマ検証・ロード・コンテキスト構築

### WYSIWYGエディタ
- **依存ライブラリなし** — `engines/JsEngine/wysiwyg.js` による独自実装（ES2020+）
- **ツールバー** — B/I/U/リンク/H2/H3/箇条書き/番号リスト/引用/コードブロック/区切り線/テーブル/Undo/Redo
- **画像挿入** — D&D / クリップボード貼付 / ボタン選択、JPEG/PNG/GIF/WebP 対応
- **画像リサイズ** — サイズプリセット（25%/50%/75%/100%）・alt 属性・キャプション
- **フローティングツールバー** — テキスト選択時に B/I/U/S/Code/Marker/Link を浮かせる
- **テーブルサポート** — 3×3 初期テーブル・各セル個別 contenteditable・行/列の追加削除
- **スラッシュコマンドメニュー** — 空行で `/` 入力 → ブロック種類選択・インクリメンタル絞り込み・キーボード操作
- **ブロックハンドル** — ブロックホバー時に左端へ `⠿` を表示・クリックでタイプ変換ポップアップ
- **ドラッグ並べ替え** — ハンドルドラッグでブロック順序変更・シアン色ドロップラインインジケータ
- **自動保存** — 30秒定期自動保存・Ctrl+Enter/blur で即時保存
- **HTML サニタイザー** — ホワイトリスト方式（保存前に不正タグを除去）

### 静的サイト生成
- **StaticEngine** — 静的 HTML 書き出し・差分ビルド・フルビルド・クリーン・ZIP ダウンロード（`engines/StaticEngine.php`）
- **Static-First Hybrid** — `.htaccess` で静的ファイル優先配信、管理・API は PHP にルーティング
- **コレクション対応** — コレクション一覧・個別ページ・タグページ・ページネーションの静的生成
- **sitemap.xml / robots.txt** — ビルド時に自動生成
- **検索インデックス** — `search-index.json` をビルド時に生成（クライアントサイド検索用）
- **OGP / JSON-LD** — Open Graph メタタグ・構造化データの自動生成

### ヘッドレス CMS API
- **ApiEngine** — 公開 REST API エンドポイント（`engines/ApiEngine.php`）
- **コンテンツ API** — ページ・コレクション・検索の JSON 取得
- **お問い合わせフォーム** — ハニーポット・レート制限付きメール送信
- **API キー認証** — Bearer トークン + bcrypt による認証
- **CORS 対応** — 設定可能なオリジン許可
- **レスポンスキャッシュ** — `engines/CacheEngine.php` による API レスポンスキャッシュ

### Git 連携
- **GitEngine** — GitHub リポジトリとのコンテンツ同期（`engines/GitEngine.php`）
- **Pull / Push** — コンテンツの取り込み・書き出し
- **Webhook 連携** — `engines/WebhookEngine.php` によるビルド完了通知・外部連携

### 画像最適化
- **ImageOptimizer** — JPEG/PNG/WebP の品質調整・リサイズ（`engines/ImageOptimizer.php`）

### アップデートエンジン
- **バージョン管理** — `AP_VERSION` 定数・`data/settings/version.json` に更新履歴を保存
- **GitHub Releases 連携** — 管理画面から最新バージョンを確認・ワンクリックで適用
- **自動バックアップ** — 更新前に `backup/YYYYMMDD_His/` へ自動バックアップ（`meta.json` 付き）
- **ロールバック** — バックアップ一覧から任意バージョンに復元
- **世代管理** — 最大 5 世代まで自動管理（`AP_BACKUP_GENERATIONS` 定数で変更可能）
- **環境チェック** — ZipArchive / allow_url_fopen / ディスク容量を事前確認

### 認証・セキュリティ
- **マルチユーザー認証** — admin / editor / viewer ロールベースアクセス制御
- **CSRF 保護** — `random_bytes(32)` による CSRF トークン生成・全フォーム検証（セッション + ヘッダー `X-CSRF-TOKEN` + POST フィールド）
- **XSS 対策** — 全出力に `htmlspecialchars(ENT_QUOTES)` を適用
- **セキュアセッション** — `HttpOnly` / `SameSite=Lax` クッキー、ログイン時の `session_regenerate_id()`
- **レート制限** — 5回失敗で15分ロックアウト（IP ベース）
- **ディレクトリ保護** — `.htaccess` による `data/` / `backup/` / `engines/*.php` への直接アクセス拒否
- **CSP** — `Content-Security-Policy: default-src 'self'` ヘッダー
- **セキュリティヘッダー** — `X-Content-Type-Options: nosniff` / `X-Frame-Options: SAMEORIGIN` / `Referrer-Policy: same-origin`
- **パストラバーサル防止** — テーマ名・フィールド名・バックアップ名・スラッグの正規表現バリデーション（再帰的 `../` 除去）
- **画像アップロード保護** — MIME 検証（`finfo`）、2MB 制限、ランダムファイル名、`uploads/` 内 PHP 実行不可
- **SSRF 防止** — Webhook 送信時の DNS リバインディング対策・プライベート IP ブロック
- **JSON-LD XSS 防止** — `JSON_UNESCAPED_SLASHES` 除去による `</script>` インジェクション防止

---

## ディレクトリ構成

```
AdlairePlatform/
├── index.php                     # エントリーポイント（ルーティング・初期化・ユーティリティ）
├── .htaccess                     # Apache リライト・セキュリティ設定・Static-First 配信
├── nginx.conf.example            # Nginx 設定リファレンス
├── engines/
│   ├── AdminEngine.php           # 管理エンジン（認証・CSRF・管理アクション・ダッシュボード）
│   ├── AdminEngine/
│   │   ├── dashboard.html        # ダッシュボードテンプレート
│   │   └── dashboard.css         # ダッシュボード専用スタイル
│   ├── TemplateEngine.php        # 軽量テンプレートエンジン（PHP フリーテーマ用）
│   ├── ThemeEngine.php           # テーマ検証・読み込み・コンテキスト構築
│   ├── UpdateEngine.php          # アップデート・バックアップ・ロールバック
│   ├── StaticEngine.php          # 静的サイト生成（差分ビルド・フルビルド・ZIP）
│   ├── ApiEngine.php             # 公開 REST API（ヘッドレス CMS）
│   ├── CollectionEngine.php      # コレクション管理（ブログ・ニュース等）
│   ├── MarkdownEngine.php        # Markdown パーサー（フロントマター対応）
│   ├── GitEngine.php             # GitHub リポジトリ連携（Pull/Push）
│   ├── WebhookEngine.php         # Webhook 管理・送信（SSRF 防止付き）
│   ├── CacheEngine.php           # API レスポンスキャッシュ
│   ├── ImageOptimizer.php        # 画像最適化（リサイズ・品質調整）
│   └── JsEngine/
│       ├── autosize.js           # テキストエリア自動リサイズ
│       ├── editInplace.js        # インプレイス編集（バニラJS・plain text）
│       ├── wysiwyg.js            # WYSIWYGエディタ（依存なし）
│       ├── updater.js            # アップデートUI
│       ├── dashboard.js          # ダッシュボード固有インタラクション
│       ├── static_builder.js     # 静的書き出し管理 UI
│       ├── collection_manager.js # コレクション管理 UI
│       ├── git_manager.js        # Git 連携 UI
│       ├── webhook_manager.js    # Webhook 管理 UI
│       ├── api_keys.js           # API キー管理 UI
│       ├── ap-api-client.js      # 静的サイト向け API クライアント
│       └── ap-search.js          # クライアントサイド検索
├── themes/
│   ├── AP-Default/
│   │   ├── theme.html            # テンプレートエンジン方式
│   │   └── style.css
│   └── AP-Adlaire/
│       ├── theme.html
│       └── style.css
├── data/
│   ├── settings/
│   │   ├── settings.json         # サイト設定
│   │   ├── auth.json             # 認証情報（bcrypt）
│   │   ├── update_cache.json     # GitHub API キャッシュ
│   │   ├── login_attempts.json   # ログイン試行記録
│   │   ├── version.json          # アップデート履歴
│   │   └── static_build.json     # 静的ビルド差分状態
│   └── content/
│       ├── pages.json            # ページコンテンツ
│       └── collections/          # コレクションデータ（Markdown）
├── uploads/                      # アップロード済み画像（PHP実行不可）
├── static/                       # 静的サイト出力先（StaticEngine が生成）
├── backup/                       # 自動バックアップ（最大5世代）
├── docs/
│   ├── ARCHITECTURE.md
│   ├── AdlairePlatform_Design.md
│   ├── SPEC.md
│   ├── features.md
│   ├── VERSIONING.md
│   ├── STATIC_GENERATOR.md
│   ├── HEADLESS_CMS.md
│   ├── HEADLESS_CMS_ROADMAP.md
│   └── Licenses/
│       ├── LICENSE_Ver.1.0.md    # 旧ライセンス（参照用・アーカイブ）
│       ├── LICENSE_Ver.2.0.md    # 現行ライセンス
│       └── RELEASE-NOTES.md
├── CHANGES.md
├── RELEASE-NOTES.md
└── README.md
```

---

## 主要関数・エンジンリファレンス

### index.php（エントリーポイント）

| 関数 | 説明 |
|------|------|
| `host()` | リクエスト URI からホスト URL・ページスラッグを解析 |
| `json_read(string $file)` | `data/` 内の JSON ファイルを読み込んで配列を返す |
| `json_write(string $file, array $data)` | 配列を JSON ファイルへ書き出す |
| `h(string $s)` | `htmlspecialchars(ENT_QUOTES, UTF-8)` による XSS エスケープ |
| `getSlug(string $p)` | スペースをハイフンに変換して小文字スラッグを生成 |
| `data_dir()` | `data/` ディレクトリのパスを返す（存在しない場合は作成） |
| `settings_dir()` | `data/settings/` のパスを返す |
| `content_dir()` | `data/content/` のパスを返す |
| `migrate_from_files()` | レガシーフラットファイルから JSON への一回限りの移行 |
| `is_loggedin()` | AdminEngine::isLoggedIn() へ委譲（レガシーラッパー） |
| `csrf_token()` | AdminEngine::csrfToken() へ委譲（レガシーラッパー） |
| `verify_csrf()` | AdminEngine::verifyCsrf() へ委譲（レガシーラッパー） |

### エンジン一覧

| エンジン | ファイル | 説明 |
|---------|---------|------|
| AdminEngine | `engines/AdminEngine.php` | 管理機能（認証・CSRF・フィールド保存・画像アップロード・リビジョン・ダッシュボード） |
| TemplateEngine | `engines/TemplateEngine.php` | 軽量テンプレートエンジン |
| ThemeEngine | `engines/ThemeEngine.php` | テーマ検証・読み込み・コンテキスト構築 |
| UpdateEngine | `engines/UpdateEngine.php` | アップデート・バックアップ・ロールバック |
| StaticEngine | `engines/StaticEngine.php` | 静的サイト生成（差分ビルド・フルビルド・ZIP） |
| ApiEngine | `engines/ApiEngine.php` | 公開 REST API（ヘッドレス CMS） |
| CollectionEngine | `engines/CollectionEngine.php` | コレクション管理（ブログ・ニュース等） |
| MarkdownEngine | `engines/MarkdownEngine.php` | Markdown パーサー（フロントマター対応） |
| GitEngine | `engines/GitEngine.php` | GitHub リポジトリ連携 |
| WebhookEngine | `engines/WebhookEngine.php` | Webhook 管理・送信 |
| CacheEngine | `engines/CacheEngine.php` | API レスポンスキャッシュ |
| ImageOptimizer | `engines/ImageOptimizer.php` | 画像最適化 |

---

## 動作要件

| 項目 | バージョン |
|------|-----------|
| PHP | **8.2 以上（必須）** |
| Web サーバー | Apache（mod_rewrite・mod_headers 有効）または Nginx（php-fpm） |
| PHP 拡張 | `json`, `mbstring`, `ZipArchive`（アップデート機能に必要） |
| PHP 設定 | `allow_url_fopen = On`（アップデートチェックに推奨） |
| JavaScript | バニラJS（外部ライブラリ不要） |
| データベース | 不要 |

---

## インストール

1. リポジトリをサーバーの公開ディレクトリに配置
2. Apache の `AllowOverride All` または `AllowOverride FileInfo Options` を確認
3. ブラウザでアクセス — 初回起動時に `data/` が自動生成
4. 画面下部の「Login」リンクからログイン（初期パスワード: `admin`）
5. **ログイン後、直ちにパスワードを変更してください**

---

## ライセンス

> ⚠️ **本リポジトリのソースコードは参照目的で公開されています。**
> **Adlaire License Ver.2.0 に基づき、改変・再配布・商用利用は禁止されています。**

本ソフトウェアは **Adlaire License Ver.2.0** に基づきます。
ライセンス全文は [docs/Licenses/LICENSE_Ver.2.0.md](docs/Licenses/LICENSE_Ver.2.0.md) を参照してください。

本ソフトウェアのソースコードは参照目的で公開されており、オープンソースではありません（**"Source Available, Not Open Source"**）。
ソースコードの閲覧・参照という事実は、いかなる使用権・改変権・配布権も付与しません。

### 許可される行為
- 個人の非商用目的での使用
- 内部業務目的での使用（第三者への提供・再配布を除く）
- 学習・研究目的でのソースコード閲覧
- 権利者が承認したコントリビュート（提供されたコードの著作権は権利者に帰属）

### 禁止される行為
- ソースコード・バイナリの再配布
- 権利者の書面による許諾なしの改変・二次著作物の作成
- 第三者へのサブライセンス
- 権利者との別途契約なしの商用利用
- リバースエンジニアリング・逆コンパイル・逆アセンブル
- 「Adlaire」「Adlaire Group」「AdlairePlatform」「AP」商標の無断使用
- 本ソフトウェアを参考にした競合製品・サービスの開発

提供プログラムは、予告なく変更・ライセンス変更・提供終了等を行う場合があります。

---

## Copyright

Copyright (c) 2014 - 2026 Adlaire Group
最高権限者：倉田和宏
All Rights Reserved.
