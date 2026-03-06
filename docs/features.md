# 実装機能一覧

AdlairePlatform の実装済み機能をソースコード解析に基づいて整理したドキュメントです。

---

## 目次

1. [コンテンツ管理機能](#1-コンテンツ管理機能)
2. [テーマエンジン](#2-テーマエンジン)
3. [プラグインシステム](#3-プラグインシステム)
4. [認証・セキュリティ機能](#4-認証セキュリティ機能)
5. [フロントエンド機能](#5-フロントエンド機能)
6. [サーバー設定（.htaccess）](#6-サーバー設定htaccess)
7. [関数リファレンス](#7-関数リファレンス)

---

## 1. コンテンツ管理機能

| 機能 | 説明 | 実装場所 |
|------|------|---------|
| JSON フラットファイルストレージ | データベース不要。`data/settings.json` / `data/pages.json` / `data/auth.json` に全データを保存 | `index.php:238–255` |
| インプレイス編集 | ログイン中はコンテンツ領域をクリックしてその場で編集・AJAX 保存 | `js/editInplace.php` |
| マルチページ管理 | スラッグベースの URL でページを識別・管理（例: `/about` → `pages.json["about"]`） | `index.php:134, 295` |
| 設定管理 | タイトル・説明・キーワード・著作権・メニュー・テーマ選択をブラウザから編集 | `index.php:308` |
| サイドコンテンツ | サイドバー用の独立したコンテンツ領域（`subside`）を管理 | `index.php:308` |
| レガシーデータ自動移行 | 旧フラットファイル形式（`files/` ディレクトリ）から JSON 形式への一回限りの自動移行 | `index.php:257` |
| データディレクトリ自動生成 | 初回アクセス時に `data/` および `plugins/` ディレクトリを自動作成 | `index.php:232` |

### ストレージファイル構成

```
data/
├── settings.json   # サイト設定（title, menu, description, keywords, copyright, themeSelect）
├── pages.json      # ページコンテンツ（スラッグ → コンテンツ の連想配列）
└── auth.json       # 認証情報（bcrypt ハッシュ化パスワード）
```

---

## 2. テーマエンジン

| 機能 | 説明 | 実装場所 |
|------|------|---------|
| テーマ切替 | 管理パネルのドロップダウンからリアルタイムでテーマを切替 | `index.php:308`, `js/editInplace.php` |
| テーマ自動検出 | `themes/` ディレクトリを走査して利用可能なテーマを自動列挙 | `index.php:315` |
| パストラバーサル防止 | テーマ名を正規表現 `[a-zA-Z0-9_-]` でバリデーション | `index.php:165` |

### 同梱テーマ

| テーマ名 | ディレクトリ | 説明 |
|---------|------------|------|
| AP-Default | `themes/AP-Default/` | シンプルなデフォルトテーマ |
| AP-Adlaire | `themes/AP-Adlaire/` | Adlaire デザインテーマ |

### テーマファイル構成

```
themes/<テーマ名>/
├── theme.php   # HTML テンプレート（PHP 埋め込み）
└── style.css   # スタイルシート
```

---

## 3. プラグインシステム

| 機能 | 説明 | 実装場所 |
|------|------|---------|
| プラグイン自動ロード | `plugins/<名前>/index.php` を配置するだけで起動時に自動ロード | `index.php:120` |
| フック機構 | グローバル `$hook` 配列にコールバックを登録して出力を拡張 | `index.php:120` |

### 利用可能フックポイント

| フック名 | タイミング | 用途例 |
|---------|-----------|-------|
| `admin-head` | 管理画面 `<head>` 出力時 | 追加 CSS / JS の挿入 |
| `admin-richText` | インライン編集テキストエリア生成時 | リッチテキストエディタの差し替え |

### プラグイン開発例

```php
<?php
// plugins/my-plugin/index.php
global $hook;
$hook['admin-head'][] = "<script src='plugins/my-plugin/script.js'></script>";
```

---

## 4. 認証・セキュリティ機能

### 認証

| 機能 | 説明 | 実装場所 |
|------|------|---------|
| シングルパスワード認証 | `auth.json` に保存された bcrypt ハッシュと照合 | `index.php:208` |
| パスワード変更 | ログイン済み状態でパスワードを変更・即時 bcrypt 保存 | `index.php:226` |
| MD5 → bcrypt 自動移行 | 旧 MD5 ハッシュを検出した場合に管理者へ警告を表示 | `index.php:208` |
| セッション認証 | `$_SESSION['loggedin']` によるログイン状態管理 | `index.php:138` |

### セキュリティ対策

| 対策 | 説明 | 実装場所 |
|------|------|---------|
| bcrypt ハッシュ化 | `password_hash($p, PASSWORD_BCRYPT)` によるパスワード保護 | `index.php:226` |
| CSRF トークン | `random_bytes(32)` 生成トークンを全フォーム・全 AJAX に付与、`hash_equals()` で検証 | `index.php:280, 287` |
| XSS エスケープ | `h()` 関数（`htmlspecialchars(ENT_QUOTES, UTF-8)`）を全出力に適用 | `index.php:152` |
| セッション固定攻撃対策 | ログイン成功時に `session_regenerate_id(true)` を実行 | `index.php:208` |
| セキュアクッキー | `HttpOnly`・`SameSite=Lax` をセッションクッキーに設定 | `index.php` 冒頭 |
| パストラバーサル防止 | テーマ名・フィールド名を正規表現でバリデーション | `index.php:165, 308` |
| ディレクトリ保護 | `data/`・`files/` への直接 HTTP アクセスを `.htaccess` で拒否 | `.htaccess` |
| セキュリティヘッダー | `X-Content-Type-Options` / `X-Frame-Options` / `Referrer-Policy` を付与 | `.htaccess` |

---

## 5. フロントエンド機能

| 機能 | 説明 | 実装場所 |
|------|------|---------|
| AJAX インライン保存 | コンテンツ編集を画面遷移なしに保存（jQuery POST + CSRF トークン） | `js/editInplace.php` |
| テキストエリア自動拡張 | autosize プラグイン内蔵により入力量に応じてテキストエリアが自動伸長 | `js/editInplace.php` |
| `<br>` ↔ 改行 変換 | 保存時に改行 → `<br />`、編集時に `<br />` → 改行 を変換（ラウンドトリップ保証） | `js/editInplace.php` |
| テーマ即時反映 | テーマ選択変更後にページをリロードして CSS を即時適用 | `js/editInplace.php` |
| 設定パネル開閉 | `.toggle` クラスによる設定セクションの折りたたみ | `js/editInplace.php` |
| RTE 差し込みフック | `js/rte.php` フックにより外部リッチテキストエディタを差し替え可能 | `js/rte.php` |
| クリーン URL | Apache mod_rewrite により拡張子なし URL（`/page-slug`）を実現 | `.htaccess` |
| jQuery CDN 読み込み | jQuery 3.7.1 を Google CDN から自動読み込み | `themes/*/theme.php` |

---

## 6. サーバー設定（.htaccess）

### URL リライト

| ルール | 説明 |
|-------|------|
| `^([^\.]+)$` → `index.php?page=$1` | 拡張子を含まない全 URL を `index.php` にルーティング |
| `Options -Indexes` | ディレクトリ一覧を無効化 |

### アクセス制御

| 対象 | 設定 |
|------|------|
| `/data/` | すべてのリクエストを 403 Forbidden で拒否 |
| `/files/` | すべてのリクエストを 403 Forbidden で拒否（レガシー互換） |

### セキュリティヘッダー

| ヘッダー | 値 | 目的 |
|---------|-----|------|
| `X-Content-Type-Options` | `nosniff` | MIME タイプスニッフィング防止 |
| `X-Frame-Options` | `SAMEORIGIN` | クリックジャッキング防止 |
| `Referrer-Policy` | `same-origin` | リファラ情報の外部漏洩防止 |

---

## 7. 関数リファレンス

`index.php` に定義された全関数の一覧です。

| 関数名 | 行番号 | 戻り値 | 説明 |
|-------|--------|--------|------|
| `loadPlugins()` | 120 | `void` | `plugins/` を走査してプラグインを自動ロード |
| `getSlug(string $p)` | 134 | `string` | スペース → ハイフン変換・小文字化でスラッグを生成 |
| `is_loggedin()` | 138 | `bool` | セッションのログイン状態を返す |
| `editTags()` | 143 | `void` | 管理者向け `<script>` タグを `<head>` に出力 |
| `h(string $s)` | 152 | `string` | `htmlspecialchars(ENT_QUOTES, UTF-8)` で XSS エスケープ |
| `content($id, $content)` | 156 | `void` | ログイン時は編集可能 `<span>`、非ログイン時はそのまま出力 |
| `edit()` | 165 | `void` | POST リクエストによるコンテンツ・設定の保存処理 |
| `menu()` | 193 | `void` | `$c['menu']` 設定から `<ul>` ナビゲーションを生成 |
| `login()` | 208 | `void` | パスワード認証・セッション生成・パスワード変更処理 |
| `savePassword(string $p)` | 226 | `string` | bcrypt ハッシュ化して `auth.json` に保存 |
| `data_dir()` | 232 | `string` | `data/` ディレクトリのパスを返す（なければ自動作成） |
| `json_read(string $file)` | 238 | `array` | `data/` 内の JSON ファイルを読み込んで配列を返す |
| `json_write(string $file, array $data)` | 245 | `void` | 配列を JSON ファイルへ書き出す |
| `migrate_from_files()` | 257 | `void` | 旧フラットファイルから JSON への一回限りの自動移行 |
| `csrf_token()` | 280 | `string` | セッション CSRF トークンを生成・取得 |
| `verify_csrf()` | 287 | `void` | POST / ヘッダーの CSRF トークンを検証（失敗時 403 終了） |
| `host()` | 295 | `void` | リクエスト URI からホスト URL・ページスラッグを解析 |
| `settings()` | 308 | `void` | テーマ選択・各種設定フォームを出力 |

---

## 未実装（将来予定）

| モジュール | 略称 | 説明 |
|----------|------|------|
| Core modules | CM | コアモジュール |
| SubCore modules | SCM | サブコアモジュール |
| Adlaire account authentication system | A3S | アドレイルアカウント認証システム |

---

*このドキュメントは `index.php`・`js/editInplace.php`・`themes/*/theme.php`・`.htaccess` のソースコード解析に基づいて生成されました。*
