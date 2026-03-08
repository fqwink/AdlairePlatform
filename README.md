# AdlairePlatform（略：AP／翻訳：アドレイル・プラットホーム）

> ⚠️ **本リポジトリのソースコードは参照目的で公開されています。**
> **Adlaire License Ver.2.0 に基づき、改変・再配布・商用利用は禁止されています。**
> 詳細は [docs/Licenses/LICENSE_Ver.2.0.md](docs/Licenses/LICENSE_Ver.2.0.md) を参照してください。

APは、デザインテンプレートエンジンを搭載したフラットファイルベースの軽量 CMS フレームワークです。
データベース不要で動作し、各機能を小さなエンジン単位として設計することで、段階的なシステム拡張が可能です。

> **現在のバージョン**: Ver.1.2-26（Ver.1.2系最終リビジョン）

---

## 実装済み機能

### コンテンツ管理
- **フラットファイル JSON ストレージ** — `data/settings/settings.json` / `data/content/pages.json` / `data/settings/auth.json` によるデータベース不要の構成
- **インプレイス編集** — ログイン中は任意のコンテンツ領域をクリックしてその場で編集・保存（Fetch API）
- **マルチページ対応** — スラッグベースの URL ルーティングにより複数ページを管理
- **設定管理** — サイトタイトル・説明・キーワード・著作権表示・メニュー・テーマをブラウザ上から変更

### テーマエンジン
- **テーマ切替** — `themes/` ディレクトリに配置したテーマを管理画面からリアルタイムで切替
- **同梱テーマ** — `AP-Default`（シンプル）、`AP-Adlaire`（Adlaire デザイン）の 2 種類
- **テーマ構造** — `theme.html`（テンプレートエンジン方式・PHP フリー）＋ `style.css`（`theme.php` レガシーフォールバック対応）
- **テンプレートエンジン** — `engines/TemplateEngine.php` による軽量テンプレートエンジン（`{{var}}` / `{{{raw}}}` / `{{#if}}` / `{{#each}}` / `{{> partial}}`）
- **エンジン分離** — `engines/ThemeEngine.php` によるテーマ検証・ロード・コンテキスト構築

### WYSIWYGエディタ
- **依存ライブラリなし** — `engines/JsEngine/wysiwyg.js` による独自実装（ES5 互換）
- **ツールバー** — B/I/U/リンク/H2/H3/箇条書き/番号リスト/引用/コードブロック/区切り線/テーブル/Undo/Redo
- **画像挿入** — D&D / クリップボード貼付 / ボタン選択、JPEG/PNG/GIF/WebP 対応
- **画像リサイズ** — 4コーナーハンドル・アスペクト比維持・alt 属性インライン編集
- **フローティングツールバー** — テキスト選択時に B/I/U/リンク/✕ を浮かせる
- **テーブルサポート** — 8×8 グリッドピッカー・行/列の追加削除バー
- **スラッシュコマンドメニュー** — 空行で `/` 入力 → ブロック種類選択・インクリメンタル絞り込み・キーボード操作
- **ブロックハンドル** — ブロックホバー時に左端へ `⠿` を表示・クリックでタイプ変換ポップアップ
- **ドラッグ並べ替え** — ハンドルドラッグでブロック順序変更・シアン色ドロップラインインジケータ
- **自動保存** — 30秒定期自動保存・Ctrl+Enter/blur で即時保存
- **HTML サニタイザー** — ホワイトリスト方式（保存前に不正タグを除去）

### アップデートエンジン
- **バージョン管理** — `AP_VERSION` 定数・`data/settings/version.json` に更新履歴を保存
- **GitHub Releases 連携** — 管理画面から最新バージョンを確認・ワンクリックで適用
- **自動バックアップ** — 更新前に `backup/YYYYMMDD_His/` へ自動バックアップ（`meta.json` 付き）
- **ロールバック** — バックアップ一覧から任意バージョンに復元
- **世代管理** — 最大 5 世代まで自動管理（`AP_BACKUP_GENERATIONS` 定数で変更可能）
- **環境チェック** — ZipArchive / allow_url_fopen / ディスク容量を事前確認

### 認証・セキュリティ
- **セッション認証** — シングルパスワード認証、bcrypt ハッシュ化保存
- **CSRF 保護** — `random_bytes(32)` による CSRF トークン生成・全フォーム検証
- **XSS 対策** — 全出力に `htmlspecialchars(ENT_QUOTES)` を適用
- **セキュアセッション** — `HttpOnly` / `SameSite=Lax` クッキー、ログイン時の `session_regenerate_id()`
- **レート制限** — 5回失敗で15分ロックアウト（IP ベース）
- **ディレクトリ保護** — `.htaccess` による `data/` / `backup/` / `engines/*.php` への直接アクセス拒否
- **CSP** — `Content-Security-Policy: default-src 'self'` ヘッダー
- **セキュリティヘッダー** — `X-Content-Type-Options: nosniff` / `X-Frame-Options: SAMEORIGIN` / `Referrer-Policy: same-origin`
- **パストラバーサル防止** — テーマ名・フィールド名・バックアップ名の正規表現バリデーション
- **画像アップロード保護** — MIME 検証（`finfo`）、2MB 制限、ランダムファイル名、`uploads/` 内 PHP 実行不可

---

## ディレクトリ構成

```
AdlairePlatform/
├── index.php                     # アプリケーション本体（ルーティング・認証・API・レンダリング）
├── .htaccess                     # Apache リライト・セキュリティ設定
├── nginx.conf.example            # Nginx 設定リファレンス
├── engines/
│   ├── TemplateEngine.php        # 軽量テンプレートエンジン（PHP フリーテーマ用）
│   ├── ThemeEngine.php           # テーマ検証・読み込み・コンテキスト構築
│   ├── UpdateEngine.php          # アップデート・バックアップ・ロールバック
│   └── JsEngine/
│       ├── autosize.js           # テキストエリア自動リサイズ
│       ├── editInplace.js        # インプレイス編集（バニラJS・plain text）
│       ├── wysiwyg.js            # WYSIWYGエディタ（依存なし）
│       └── updater.js            # アップデートUI
├── themes/
│   ├── AP-Default/
│   │   ├── theme.html            # テンプレートエンジン方式（推奨）
│   │   ├── settings.html         # 管理者設定パネル（パーシャル）
│   │   ├── theme.php             # レガシー PHP 方式（フォールバック）
│   │   └── style.css
│   └── AP-Adlaire/
│       ├── theme.html
│       ├── settings.html
│       ├── theme.php
│       └── style.css
├── data/
│   ├── settings/
│   │   ├── settings.json         # サイト設定
│   │   ├── auth.json             # 認証情報（bcrypt）
│   │   ├── update_cache.json     # GitHub API キャッシュ
│   │   ├── login_attempts.json   # ログイン試行記録
│   │   └── version.json          # アップデート履歴
│   └── content/
│       └── pages.json            # ページコンテンツ
├── uploads/                      # アップロード済み画像（PHP実行不可）
├── backup/                       # 自動バックアップ（最大5世代）
├── docs/
│   ├── ARCHITECTURE.md
│   ├── AdlairePlatform_Design.md
│   ├── SPEC.md
│   ├── features.md
│   ├── VERSIONING.md
│   ├── STATIC_GENERATOR.md
│   ├── HEADLESS_CMS.md
│   └── Licenses/
│       ├── LICENSE_Ver.1.0.md    # 旧ライセンス（参照用・アーカイブ）
│       └── LICENSE_Ver.2.0.md    # 現行ライセンス
├── CHANGES.md
├── RELEASE-NOTES.md
└── README.md
```

---

## 主要関数リファレンス（`index.php`）

| 関数 | 説明 |
|------|------|
| `host()` | リクエスト URI からホスト URL・ページスラッグを解析 |
| `edit()` | POST リクエストによるコンテンツ・設定の保存処理 |
| `upload_image()` | 認証済みユーザーの画像アップロード（JPEG/PNG/GIF/WebP、2MB制限） |
| `login()` | パスワード認証・セッション生成・パスワード変更 |
| `savePassword(string $p)` | bcrypt ハッシュ化して `auth.json` に保存 |
| `check_login_rate()` | IP ベースのログイン試行レート制限チェック |
| `registerCoreHooks()` | `admin-head` フックに JsEngine スクリプトを登録 |
| `migrate_from_files()` | レガシーフラットファイルから JSON への一回限りの移行 |
| `content($id, $content)` | ログイン時は編集可能 `<span>`、非ログイン時はそのまま出力 |
| `menu()` | `$c['menu']` 設定から `<ul>` ナビゲーションを生成 |
| `settings()` | テーマ選択・各種設定フォームを出力 |
| `json_read(string $file)` | `data/` 内の JSON ファイルを読み込んで配列を返す |
| `json_write(string $file, array $data)` | 配列を JSON ファイルへ書き出す |
| `csrf_token()` | セッション CSRF トークンを生成・取得 |
| `verify_csrf()` | POST / ヘッダーの CSRF トークンを検証（失敗時 403 終了） |
| `h(string $s)` | `htmlspecialchars(ENT_QUOTES, UTF-8)` による XSS エスケープ |
| `getSlug(string $p)` | スペースをハイフンに変換して小文字スラッグを生成 |
| `is_loggedin()` | セッションのログイン状態を返す |
| `editTags()` | 管理者向けの `<script>` タグを `<head>` に出力 |
| `data_dir()` | `data/` ディレクトリのパスを返す（存在しない場合は作成） |
| `settings_dir()` | `data/settings/` のパスを返す |
| `content_dir()` | `data/content/` のパスを返す |

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
