# AdlairePlatform（略：AP／翻訳：アドレイル・プラットホーム）

> ⚠️ **本リポジトリのソースコードは参照目的で公開されています。**
> **Adlaire License Ver.2.0 に基づき、改変・再配布・商用利用は禁止されています。**
> 詳細は [Licenses/LICENSE_Ver.2.0.md](Licenses/LICENSE_Ver.2.0.md) を参照してください。

APは、デザインテンプレートエンジンを搭載したフラットファイルベースの軽量 CMS フレームワークです。
データベース不要で動作し、各機能を小さなモジュール単位として設計することで、プラグインによる段階的なシステム拡張が可能です。

---

## 実装済み機能

### コンテンツ管理
- **フラットファイル JSON ストレージ** — `data/settings.json` / `data/pages.json` / `data/auth.json` によるデータベース不要の構成
- **インプレイス編集** — ログイン中は任意のコンテンツ領域をクリックしてその場で編集・保存
- **マルチページ対応** — スラッグベースの URL ルーティングにより複数ページを管理
- **設定管理** — サイトタイトル・説明・キーワード・著作権表示・メニュー・テーマをブラウザ上から変更

### テーマエンジン
- **テーマ切替** — `themes/` ディレクトリに配置したテーマを管理画面からリアルタイムで切替
- **同梱テーマ** — `AP-Default`（シンプル）、`AP-Adlaire`（Adlaire デザイン）の 2 種類
- **テーマ構造** — `theme.php`（HTML テンプレート）＋ `style.css` の 2 ファイル構成

### プラグインシステム
- **フック機構** — `plugins/` ディレクトリにプラグインを配置するだけで自動ロード
- **利用可能フック** — `admin-head`（管理画面 `<head>` への挿入）、`admin-richText`（リッチテキストエディタ差し替え）

### 認証・セキュリティ
- **セッション認証** — シングルパスワード認証、bcrypt ハッシュ化保存
- **CSRF 保護** — `random_bytes(32)` による CSRF トークン生成・全フォーム検証
- **XSS 対策** — 全出力に `htmlspecialchars(ENT_QUOTES)` を適用
- **セキュアセッション** — `HttpOnly` / `SameSite=Lax` クッキー、ログイン時の `session_regenerate_id()`
- **ディレクトリ保護** — `.htaccess` による `data/` および `files/` ディレクトリへの直接アクセス拒否
- **セキュリティヘッダー** — `X-Content-Type-Options: nosniff` / `X-Frame-Options: SAMEORIGIN` / `Referrer-Policy: same-origin`
- **パストラバーサル防止** — テーマ名・フィールド名の正規表現バリデーション
- **MD5 → bcrypt 自動移行** — 旧ハッシュを検出した場合に管理者へ警告を表示

### フロントエンド
- **AJAX 保存** — フォーム送信なしで変更を即時保存（jQuery 3.7.1）
- **テキストエリア自動拡張** — autosize プラグイン内蔵
- **リッチテキストエディタ対応** — `js/rte.php` フックによる外部 RTE の差し込みが可能
- **URL クリーン化** — Apache mod_rewrite による拡張子なし URL

---

## ディレクトリ構成

```
AdlairePlatform/
├── index.php             # アプリケーション本体（ルーティング・認証・API・レンダリング）
├── .htaccess             # Apache リライト・セキュリティ設定
├── js/
│   ├── editInplace.php   # インプレイス編集用 JavaScript（PHP フック対応）
│   └── rte.php           # リッチテキストエディタ差し込みフック
├── themes/
│   ├── AP-Default/       # デフォルトテーマ
│   │   ├── theme.php
│   │   └── style.css
│   └── AP-Adlaire/       # Adlaire テーマ
│       ├── theme.php
│       └── style.css
├── plugins/              # プラグイン配置ディレクトリ（自動生成）
├── data/                 # JSON データストレージ（自動生成）
│   ├── settings.json
│   ├── pages.json
│   └── auth.json
├── Licenses/
│   ├── LICENSE_Ver.1.0   # 旧ライセンス（参照用）
│   └── LICENSE_Ver.2.0.md  # 現行ライセンス
└── CHANGES.md / RELEASE-NOTES.md / README.md
```

---

## 主要関数リファレンス（`index.php`）

| 関数 | 説明 |
|------|------|
| `host()` | リクエスト URI からホスト URL・ページスラッグを解析 |
| `edit()` | POST リクエストによるコンテンツ・設定の保存処理 |
| `login()` | パスワード認証・セッション生成・パスワード変更 |
| `savePassword(string $p)` | bcrypt ハッシュ化して `auth.json` に保存 |
| `loadPlugins()` | `plugins/` を走査してプラグインを自動ロード |
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

---

## 動作要件

| 項目 | バージョン |
|------|-----------|
| PHP | 8.0 以上推奨（5.3 以上で動作）|
| Web サーバー | Apache（mod_rewrite・mod_headers 有効）|
| JavaScript | jQuery 3.7.1（CDN 経由で自動読み込み）|
| データベース | 不要 |

---

## インストール

1. リポジトリをサーバーの公開ディレクトリに配置
2. Apache の `AllowOverride All` または `AllowOverride FileInfo Options` を確認
3. ブラウザでアクセス — 初回起動時に `data/` と `plugins/` が自動生成
4. 画面下部の「Login」リンクからログイン（初期パスワード: `admin`）
5. **ログイン後、直ちにパスワードを変更してください**

---

## プラグイン開発

`plugins/<プラグイン名>/index.php` を作成するだけで自動ロードされます。

```php
<?php
// plugins/my-plugin/index.php
global $hook;
$hook['admin-head'][] = "<script src='plugins/my-plugin/script.js'></script>";
```

---

## ライセンス

> ⚠️ **本リポジトリのソースコードは参照目的で公開されています。**
> **Adlaire License Ver.2.0 に基づき、改変・再配布・商用利用は禁止されています。**

本ソフトウェアは **Adlaire License Ver.2.0** に基づきます。
ライセンス全文は [Licenses/LICENSE_Ver.2.0.md](Licenses/LICENSE_Ver.2.0.md) を参照してください。

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
