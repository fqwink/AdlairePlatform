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
8. [アップデートエンジン](#8-アップデートエンジン)

---

## 1. コンテンツ管理機能

| 機能 | 説明 | 実装場所 |
|------|------|---------|
| JSON フラットファイルストレージ | データベース不要。`data/settings.json` / `data/pages.json` / `data/auth.json` に全データを保存 | `index.php:243–260` |
| インプレイス編集 | ログイン中はコンテンツ領域をクリックしてその場で編集・AJAX 保存 | `js/editInplace.php` |
| マルチページ管理 | スラッグベースの URL でページを識別・管理（例: `/about` → `pages.json["about"]`） | `index.php:139, 493` |
| 設定管理 | タイトル・説明・キーワード・著作権・メニュー・テーマ選択をブラウザから編集 | `index.php:506` |
| サイドコンテンツ | サイドバー用の独立したコンテンツ領域（`subside`）を管理 | `index.php:506` |
| レガシーデータ自動移行 | 旧フラットファイル形式（`files/` ディレクトリ）から JSON 形式への一回限りの自動移行 | `index.php:262` |
| データディレクトリ自動生成 | 初回アクセス時に `data/` および `plugins/` ディレクトリを自動作成 | `index.php:237` |

### ストレージファイル構成

```
data/
├── settings.json      # サイト設定（title, menu, description, keywords, copyright, themeSelect）
├── pages.json         # ページコンテンツ（スラッグ → コンテンツ の連想配列）
├── auth.json          # 認証情報（bcrypt ハッシュ化パスワード）
├── version.json       # アップデート履歴（version, updated_at, history[]）
└── update_cache.json  # 更新確認 API レスポンスキャッシュ（有効期限1時間）
```

---

## 2. テーマエンジン

| 機能 | 説明 | 実装場所 |
|------|------|---------|
| テーマ切替 | 管理パネルのドロップダウンからリアルタイムでテーマを切替 | `index.php:506`, `js/editInplace.php` |
| テーマ自動検出 | `themes/` ディレクトリを走査して利用可能なテーマを自動列挙 | `index.php:514` |
| パストラバーサル防止 | テーマ名を正規表現 `[a-zA-Z0-9_-]` でバリデーション | `index.php:119` |

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
| プラグイン自動ロード | `plugins/<名前>/index.php` を配置するだけで起動時に自動ロード | `index.php:124` |
| フック機構 | グローバル `$hook` 配列にコールバックを登録して出力を拡張 | `index.php:124` |

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
| シングルパスワード認証 | `auth.json` に保存された bcrypt ハッシュと照合 | `index.php:213` |
| パスワード変更 | ログイン済み状態でパスワードを変更・即時 bcrypt 保存 | `index.php:231` |
| MD5 → bcrypt 自動移行 | 旧 MD5 ハッシュを検出した場合に管理者へ警告を表示 | `index.php:51–57` |
| セッション認証 | `$_SESSION['l']` によるログイン状態管理 | `index.php:143` |

### セキュリティ対策

| 対策 | 説明 | 実装場所 |
|------|------|---------|
| bcrypt ハッシュ化 | `password_hash($p, PASSWORD_BCRYPT)` によるパスワード保護 | `index.php:231` |
| CSRF トークン | `random_bytes(32)` 生成トークンを全フォーム・全 AJAX に付与、`hash_equals()` で検証 | `index.php:478, 485` |
| XSS エスケープ | `h()` 関数（`htmlspecialchars(ENT_QUOTES, UTF-8)`）を全出力に適用 | `index.php:157` |
| セッション固定攻撃対策 | ログイン成功時に `session_regenerate_id(true)` を実行 | `index.php:225` |
| セキュアクッキー | `HttpOnly`・`SameSite=Lax` をセッションクッキーに設定 | `index.php:14–15` |
| パストラバーサル防止 | テーマ名・フィールド名を正規表現でバリデーション | `index.php:119, 173` |
| ディレクトリ保護 | `data/`・`files/`・`backup/` への直接 HTTP アクセスを `.htaccess` で拒否 | `.htaccess` |
| セキュリティヘッダー | `X-Content-Type-Options` / `X-Frame-Options` / `Referrer-Policy` を付与 | `.htaccess` |
| アップデート URL 検証 | `apply_update()` は GitHub ドメインの URL のみ受け入れ | `index.php:301` |
| バックアップ名バリデーション | `rollback_to_backup()` は `[0-9_]+` のみ許可（パストラバーサル防止） | `index.php:313` |

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
| アップデート UI | 更新確認・適用・ロールバック操作を管理パネルから実行 | `js/updater.js` |

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
| `/backup/` | すべてのリクエストを 403 Forbidden で拒否 |
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
| `loadPlugins()` | 124 | `void` | `plugins/` を走査してプラグインを自動ロード、`updater.js` フックを追加 |
| `getSlug(string $p)` | 139 | `string` | スペース → ハイフン変換・小文字化でスラッグを生成 |
| `is_loggedin()` | 143 | `bool` | セッションのログイン状態を返す |
| `editTags()` | 148 | `void` | 管理者向け `<script>` タグを `<head>` に出力 |
| `h(string $s)` | 157 | `string` | `htmlspecialchars(ENT_QUOTES, UTF-8)` で XSS エスケープ |
| `content($id, $content)` | 161 | `void` | ログイン時は編集可能 `<span>`、非ログイン時はそのまま出力 |
| `edit()` | 170 | `void` | POST リクエストによるコンテンツ・設定の保存処理 |
| `menu()` | 198 | `void` | `$c['menu']` 設定から `<ul>` ナビゲーションを生成 |
| `login()` | 213 | `void` | パスワード認証・セッション生成・パスワード変更処理 |
| `savePassword(string $p)` | 231 | `string` | bcrypt ハッシュ化して `auth.json` に保存 |
| `data_dir()` | 237 | `string` | `data/` ディレクトリのパスを返す（なければ自動作成） |
| `json_read(string $file)` | 243 | `array` | `data/` 内の JSON ファイルを読み込んで配列を返す |
| `json_write(string $file, array $data)` | 250 | `void` | 配列を JSON ファイルへ書き出す |
| `migrate_from_files()` | 262 | `void` | 旧フラットファイルから JSON への一回限りの自動移行 |
| `check_environment()` | 286 | `array` | ZipArchive・allow_url_fopen・書き込み権限・ディスク空き容量を確認して結果配列を返す |
| `prune_old_backups()` | 301 | `void` | `AP_BACKUP_GENERATIONS` を超えた古いバックアップを古い順に削除 |
| `delete_backup(string $name)` | 319 | `void` | 指定バックアップディレクトリを再帰削除（認証・バリデーション済み） |
| `handle_update_action()` | 336 | `void` | POST `ap_action` をディスパッチ（認証・CSRF 検証済み）、JSON を返して終了。対応アクション: `check` / `check_env` / `apply` / `list_backups` / `rollback` / `delete_backup` |
| `check_update()` | 409 | `array` | GitHub Releases API から最新バージョン情報を取得・AP_VERSION と比較。レスポンスを `update_cache.json` に1時間キャッシュ、HTTP 403/429 をレート制限エラーとして返す |
| `backup_current()` | 459 | `string` | `backup/YYYYMMDD_His/` に全ファイルをコピー（`data/`・`backup/`・`.git/` 除外）し、`meta.json` にバックアップ情報を記録 |
| `apply_update(string $zip_url, string $new_version)` | 493 | `void` | ZIP ダウンロード → 展開 → `data/`・`backup/` 保護して上書き → `prune_old_backups()` → `version.json` 更新 |
| `rollback_to_backup(string $backup_name)` | 570 | `void` | 指定バックアップから `data/`・`meta.json` を除いてファイルを復元。`realpath()` 失敗時はエラー返却 |
| `csrf_token()` | 601 | `string` | セッション CSRF トークンを生成・取得 |
| `verify_csrf()` | 608 | `void` | POST / ヘッダーの CSRF トークンを検証（失敗時 403 終了） |
| `host()` | 616 | `void` | リクエスト URI からホスト URL・ページスラッグを解析 |
| `settings()` | 629 | `void` | テーマ選択・各種設定フォーム・アップデートパネルを出力 |

---

## 8. アップデートエンジン

### 概要

管理画面からワンクリックで CMS 本体を更新できるエンジン。
更新前に自動バックアップを作成し、失敗時はロールバックで復元できる。

### 機能一覧

| 機能 | 説明 |
|------|------|
| バージョン表示 | 管理パネルに現在のバージョン（`AP_VERSION` 定数）を表示 |
| 更新確認 | GitHub Releases API から最新バージョンを取得して比較 |
| API キャッシュ | GitHub API レスポンスを `data/update_cache.json` に1時間キャッシュ（レート制限対策） |
| 事前環境チェック | 更新適用前に ZipArchive / allow_url_fopen / 書き込み権限 / ディスク容量を確認し、問題があれば中止 |
| 自動バックアップ | 更新適用前に `backup/YYYYMMDD_His/` へ全ファイルをコピー、`meta.json` にメタデータを記録 |
| バックアップ世代管理 | `AP_BACKUP_GENERATIONS`（デフォルト5）を超えた古い世代を自動削除 |
| バックアップメタデータ | 各バックアップに `meta.json`（更新前バージョン / 作成日時 / ファイル数 / サイズ）を記録し、管理画面でテーブル表示 |
| 更新適用 | ZIP をダウンロード・展開して `data/`・`backup/` を保護したままファイルを上書き |
| ロールバック | バックアップ一覧から任意のバージョンに復元（`data/` は変更しない） |
| バックアップ削除 | 管理パネルから個別のバックアップを削除（確認ダイアログ付き） |
| バージョン履歴 | `data/version.json` に適用履歴とバックアップ名を記録 |

### version.json の構造

```json
{
  "version": "1.0.0",
  "updated_at": "2026-03-06",
  "history": [
    {
      "version": "1.0.0",
      "applied_at": "2026-03-06 12:00:00",
      "backup": "20260306_120000"
    }
  ]
}
```

### update_cache.json の構造

```json
{
  "result": {
    "current": "1.0.0",
    "latest": "1.0.1",
    "update_available": true,
    "zip_url": "https://codeload.github.com/..."
  },
  "expires_at": 1741234567
}
```

`expires_at` は UNIX タイムスタンプ。`time() < expires_at` の間はキャッシュを返し GitHub API を呼び出さない。

### backup/\<name\>/meta.json の構造

```json
{
  "version_before": "1.0.0",
  "created_at": "2026-03-06 12:00:00",
  "file_count": 42,
  "size_bytes": 524288
}
```

### 更新フロー

```
[管理画面] 更新を確認ボタン
    ↓ AJAX POST ap_action=check
[PHP] check_update() → update_cache.json キャッシュ確認 or GitHub API → バージョン比較
    ↓ JSON レスポンス
[管理画面] 「バージョン X.Y.Z に更新」ボタン表示
    ↓ クリック → AJAX POST ap_action=check_env
[PHP] check_environment() → ZipArchive / allow_url_fopen / 書き込み権限 / ディスク容量を確認
    ↓ ok=true の場合のみ続行
    ↓ AJAX POST ap_action=apply
[PHP] handle_update_action()
    → backup_current()      # backup/YYYYMMDD_His/ を作成・meta.json 生成
    → prune_old_backups()   # AP_BACKUP_GENERATIONS 超過分を削除
    → ZIP ダウンロード
    → ZipArchive 展開
    → ファイルコピー（data/, backup/ 除外）
    → version.json 更新
    ↓ JSON レスポンス（success）
[管理画面] ページリロード
```

### セキュリティ対策

| 脅威 | 対策 |
|------|------|
| 未認証操作 | `$_SESSION['l']` チェックを最初に実施 |
| CSRF | `verify_csrf()` を全アクションに適用 |
| 任意 URL への接続 | `zip_url` を GitHub ドメイン（`github.com` / `api.github.com` / `codeload.github.com`）のみ許可 |
| パストラバーサル | `backup_name` を `^[0-9_]+$` でバリデーション（`rollback` / `delete_backup` 共通） |
| バックアップ上書き | `apply_update()` の除外リストに `backup` を含む |
| バックアップ直接閲覧 | `.htaccess` で `backup/` を 403 拒否 |
| パス解決失敗 | `rollback_to_backup()` の `realpath()` が `false` を返した場合に 500 エラーを返して中止 |
| meta.json のルート混入 | `rollback_to_backup()` が `meta.json` をコピー対象から除外 |

---

## 未実装（将来予定）

| モジュール | 略称 | 説明 |
|----------|------|------|
| Core modules | CM | コアモジュール |
| SubCore modules | SCM | サブコアモジュール |
| Adlaire account authentication system | A3S | アドレイルアカウント認証システム |

---

*このドキュメントは `index.php`・`js/editInplace.php`・`js/updater.js`・`themes/*/theme.php`・`.htaccess` のソースコード解析に基づいて生成されました。*
