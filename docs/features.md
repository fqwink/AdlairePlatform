# 実装機能一覧

AdlairePlatform Ver.1.2-20 の実装済み機能をソースコード解析に基づいて整理したドキュメントです。

> 最終更新: 2026-03-08

---

## 目次

1. [コンテンツ管理機能](#1-コンテンツ管理機能)
2. [テーマエンジン](#2-テーマエンジン)
3. [WYSIWYGエディタ](#3-wysiwygエディタ)
4. [認証・セキュリティ機能](#4-認証セキュリティ機能)
5. [フロントエンド機能](#5-フロントエンド機能)
6. [サーバー設定（.htaccess）](#6-サーバー設定htaccess)
7. [関数リファレンス](#7-関数リファレンス)
8. [アップデートエンジン](#8-アップデートエンジン)

---

## 1. コンテンツ管理機能

| 機能 | 説明 | 実装場所 |
|------|------|---------|
| JSON フラットファイルストレージ | データベース不要。`data/settings/` / `data/content/` に全データを保存 | `index.php` |
| インプレイス編集（plain text） | ログイン中はコンテンツ領域をクリックしてその場で編集・Fetch API 保存 | `engines/JsEngine/editInplace.js` |
| インプレイス編集（WYSIWYG） | `class="editRich"` 要素クリックで WYSIWYG エディタを起動 | `engines/JsEngine/wysiwyg.js` |
| マルチページ管理 | スラッグベースの URL でページを識別・管理 | `index.php` |
| 設定管理 | タイトル・説明・キーワード・著作権・メニュー・テーマ選択をブラウザから編集 | `index.php` |
| サイドコンテンツ | サイドバー用の独立したコンテンツ領域（`subside`）を管理 | `index.php` |
| 画像アップロード | 認証済みユーザーが JPEG/PNG/GIF/WebP 画像をアップロード（最大 2MB） | `index.php: upload_image()` |
| レガシーデータ自動移行 | 旧フラットファイル・旧 data/ 構造から新構造への自動移行 | `index.php: migrate_from_files()` |
| データディレクトリ自動生成 | 初回アクセス時に `data/settings/` / `data/content/` を自動作成 | `index.php: data_dir()` 等 |

### ストレージファイル構成

```
data/
├── settings/
│   ├── settings.json      # サイト設定（title, menu, description, keywords, copyright, themeSelect）
│   ├── auth.json          # 認証情報（bcrypt ハッシュ化パスワード）
│   ├── version.json       # アップデート履歴（version, updated_at, history[]）
│   ├── update_cache.json  # 更新確認 API レスポンスキャッシュ（有効期限1時間）
│   └── login_attempts.json # ログイン試行記録（IP ベースレート制限）
└── content/
    └── pages.json         # ページコンテンツ（スラッグ → コンテンツ の連想配列）
```

---

## 2. テーマエンジン

| 機能 | 説明 | 実装場所 |
|------|------|---------|
| テーマ切替 | 管理パネルのドロップダウンからリアルタイムでテーマを切替 | `index.php`, `engines/JsEngine/editInplace.js` |
| テーマ自動検出 | `themes/` ディレクトリを走査して利用可能なテーマを自動列挙 | `engines/ThemeEngine.php: listThemes()` |
| テーマバリデーション | テーマ名を正規表現 `[a-zA-Z0-9_-]` でバリデーション（パストラバーサル防止） | `engines/ThemeEngine.php: load()` |
| デフォルトフォールバック | 指定テーマが存在しない場合 `AP-Default` にフォールバック | `engines/ThemeEngine.php: load()` |

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

## 3. WYSIWYGエディタ

`engines/JsEngine/wysiwyg.js` による独自実装（依存ライブラリなし・ES2020+）。
Editor.js スタイルのブロックベースアーキテクチャ。各ブロックが独立した contenteditable を持ち、
ブロック単位の操作（タイプ変換・ドラッグ並べ替え）を実現する。

### アーキテクチャ

- **ブロックベース**: 各ブロックが独立した contenteditable 要素
- **HTML 互換**: 入力時に既存 HTML をブロックにパース、保存時にブロックを HTML にシリアライズ
- **内部モデル**: `{ id, type, data }` の配列でブロックを管理

### ブロックタイプ

| タイプ | ツールバー | スラッシュコマンド | HTML 出力 |
|--------|-----------|-----------------|-----------|
| paragraph | ¶ | `/paragraph` | `<p>` |
| heading(h2) | H2 | `/h2` | `<h2>` |
| heading(h3) | H3 | `/h3` | `<h3>` |
| list(ul) | •≡ | `/ul` | `<ul><li>` |
| list(ol) | 1≡ | `/ol` | `<ol><li>` |
| blockquote | ❝ | `/quote` | `<blockquote>` |
| code | {} | `/code` | `<pre><code>` |
| delimiter | — | `/hr` | `<hr>` |
| table | 📊 | `/table` | `<table>` |
| image | 🖼 | `/image` | `<figure><img><figcaption>` |
| checklist | — | `/checklist` | `<ul class="ap-checklist">` |

### インラインツール

| ボタン | 機能 | ショートカット |
|--------|------|---------------|
| B | 太字 | Ctrl+B |
| I | 斜体 | Ctrl+I |
| U | 下線 | Ctrl+U |
| S | 取消線 | Ctrl+Shift+S |
| `<>` | インラインコード | Ctrl+E |
| M | マーカー | Ctrl+Shift+M |
| 🔗 | リンク挿入 | Ctrl+K |
| ✕ | 書式クリア | — |

### フローティングインラインツールバー

- テキスト選択時に選択範囲上部へ B/I/U/S/Code/Marker/Link ボタンを浮かせる
- コードブロック内では非表示

### 画像ブロック機能

| 機能 | 説明 |
|------|------|
| 挿入方法 | D&D / クリップボード貼付 / ボタン選択 / スラッシュコマンド の4通り |
| 許可形式 | JPEG / PNG / GIF / WebP |
| サイズプリセット | 25% / 50% / 75% / 100% から選択 |
| Alt テキスト | 画像下部に alt 属性入力欄（即時反映） |
| キャプション | `<figcaption>` として画像下部に入力欄 |

### テーブル機能

| 機能 | 説明 |
|------|------|
| 挿入 | ツールバーボタン / スラッシュコマンドで 3×3 テーブル挿入 |
| セル編集 | 各セルが個別 contenteditable |
| Tab 移動 | Tab で次セル、Shift+Tab で前セルへ移動 |
| 行列操作 | `+ 行` / `+ 列` / `- 行` / `- 列` ボタン |

### チェックリスト機能

| 機能 | 説明 |
|------|------|
| 挿入 | スラッシュコマンド `/checklist` で挿入 |
| チェック切替 | チェックボックスクリックで状態切替 |
| 項目追加 | Enter で新項目追加 |
| 項目削除 | 空項目で Backspace で削除 |

### スラッシュコマンドメニュー

| 機能 | 説明 |
|------|------|
| 起動 | 空ブロックで `/` を入力するとブロックタイプ選択メニューが表示される |
| 絞り込み | `/co` のようにタイプすることでインクリメンタル絞り込みが可能 |
| キーボード操作 | ArrowDown/Up で移動・Enter で確定・Escape で閉じる |
| 対象ブロック | 段落・H2・H3・引用・コード・箇条書き・番号リスト・区切り線・テーブル・画像・チェックリスト |
| ビューポートクランプ | メニューが画面端に収まるよう自動位置調整 |

### ブロックハンドル・タイプ変換

| 機能 | 説明 |
|------|------|
| ハンドル表示 | ブロックにホバーすると左端へ `⠿` ハンドルを表示 |
| タイプ変換 | ハンドルクリックでブロックタイプ変換ポップアップを表示 |
| Block Tunes | テキスト配置（左揃え / 中央揃え / 右揃え）をポップアップから選択 |
| ブロック削除 | ポップアップ内の削除ボタンでブロックを削除 |
| 位置調整 | ポップアップはビューポートからのはみ出しをクランプして表示 |

### ドラッグ並べ替え

- `⠿` ハンドルをドラッグしてブロックの順序を変更できる
- ドロップ位置にシアン色のインジケータラインが表示される
- 初回 mousemove まで視覚効果を遅延（純粋クリックとの区別）

### ARIA アクセシビリティ

- ツールバー: `role="toolbar"` + 各ボタンに `aria-label`
- ブロック: `role="textbox"` + `aria-label`（ブロックタイプ表示）
- ステータス: `aria-live="polite"` でスクリーンリーダー通知
- スラッシュメニュー: `role="listbox"` + `role="option"`

### 保存機能

| 機能 | 説明 |
|------|------|
| 自動保存 | 30秒間隔での定期自動保存 |
| 即時保存 | Ctrl+Enter または blur で即時保存 |
| サニタイザー | 保存前にホワイトリスト方式で不正タグを除去 |
| 許可タグ | `b,i,u,s,strong,em,mark,code,h2,h3,p,br,blockquote,pre,hr,ul,ol,li,a,img,table,thead,tbody,tr,th,td,figure,figcaption` |

---

## 4. 認証・セキュリティ機能

### 認証

| 機能 | 説明 | 実装場所 |
|------|------|---------|
| シングルパスワード認証 | `auth.json` に保存された bcrypt ハッシュと照合 | `index.php: login()` |
| パスワード変更 | ログイン済み状態でパスワードを変更・即時 bcrypt 保存 | `index.php: savePassword()` |
| MD5 → bcrypt 自動移行 | 旧 MD5 ハッシュを検出した場合に管理者へ警告を表示 | `index.php: login()` |
| セッション認証 | `$_SESSION['l']` によるログイン状態管理 | `index.php: is_loggedin()` |
| レート制限 | 5回失敗で15分ロックアウト（IP ベース・`login_attempts.json`） | `index.php: check_login_rate()` |

### セキュリティ対策

| 対策 | 説明 | 実装場所 |
|------|------|---------|
| bcrypt ハッシュ化 | `password_hash($p, PASSWORD_BCRYPT)` によるパスワード保護 | `index.php: savePassword()` |
| CSRF トークン | `random_bytes(32)` 生成・`empty()` + `hash_equals()` で検証 | `index.php: verify_csrf()` |
| XSS エスケープ | `h()` 関数（`htmlspecialchars(ENT_QUOTES, UTF-8)`）を全出力に適用 | `index.php: h()` |
| セッション固定攻撃対策 | ログイン成功時に `session_regenerate_id(true)` を実行 | `index.php: login()` |
| セキュアクッキー | `HttpOnly`・`SameSite=Lax` をセッションクッキーに設定 | `index.php` |
| パストラバーサル防止 | テーマ名・フィールド名・バックアップ名を正規表現でバリデーション | `index.php`, `engines/UpdateEngine.php` |
| ディレクトリ保護 | `data/`・`files/`・`backup/`・`engines/*.php` への直接 HTTP アクセスを拒否 | `.htaccess` |
| セキュリティヘッダー | CSP / `X-Content-Type-Options` / `X-Frame-Options` / `Referrer-Policy` を付与 | `.htaccess` |
| 画像アップロード保護 | MIME 検証（`finfo`）・2MB 制限・ランダムファイル名・PHP 実行不可 | `index.php: upload_image()` |
| アップデート URL 検証 | `apply_update()` は GitHub ドメインの URL のみ受け入れ | `engines/UpdateEngine.php` |
| バックアップ名バリデーション | `^[0-9_]+$` のみ許可（二重防御） | `engines/UpdateEngine.php` |
| WYSIWYG サニタイザー | 保存前にホワイトリスト方式で不正タグを除去 | `engines/JsEngine/wysiwyg.js` |

---

## 5. フロントエンド機能

| 機能 | 説明 | 実装場所 |
|------|------|---------|
| Fetch API インライン保存 | コンテンツ編集を画面遷移なしに保存 | `engines/JsEngine/editInplace.js` |
| テキストエリア自動拡張 | autosize により入力量に応じてテキストエリアが自動伸長 | `engines/JsEngine/autosize.js` |
| `<br>` ↔ 改行 変換 | 保存時に改行 → `<br>`、編集時に `<br>` → 改行 を変換 | `engines/JsEngine/editInplace.js` |
| テーマ即時反映 | テーマ選択変更後にページをリロードして CSS を即時適用 | `engines/JsEngine/editInplace.js` |
| 設定パネル開閉 | `.toggle` クラスによる設定セクションの折りたたみ | `engines/JsEngine/editInplace.js` |
| WYSIWYGエディタ | 依存ライブラリなしの完全独自実装 | `engines/JsEngine/wysiwyg.js` |
| アップデート UI | 更新確認・適用・ロールバック操作を管理パネルから実行 | `engines/JsEngine/updater.js` |
| クリーン URL | Apache mod_rewrite により拡張子なし URL（`/page-slug`）を実現 | `.htaccess` |

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
| `engines/*.php` | 直接アクセスを 403 Forbidden で拒否 |

### セキュリティヘッダー

| ヘッダー | 値 | 目的 |
|---------|-----|------|
| `Content-Security-Policy` | `default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'` | コンテンツインジェクション防止 |
| `X-Content-Type-Options` | `nosniff` | MIME タイプスニッフィング防止 |
| `X-Frame-Options` | `SAMEORIGIN` | クリックジャッキング防止 |
| `Referrer-Policy` | `same-origin` | リファラ情報の外部漏洩防止 |

---

## 7. 関数リファレンス

`index.php` に定義された主要関数の一覧です。

| 関数名 | 戻り値 | 説明 |
|-------|--------|------|
| `getSlug(string $p)` | `string` | スペース → ハイフン変換・小文字化でスラッグを生成 |
| `is_loggedin()` | `bool` | セッションのログイン状態を返す |
| `editTags()` | `void` | 管理者向け `<script>` タグを `<head>` に出力 |
| `registerCoreHooks()` | `void` | `admin-head` フックに JsEngine スクリプトを登録 |
| `h(string $s)` | `string` | `htmlspecialchars(ENT_QUOTES, UTF-8)` で XSS エスケープ |
| `content(string $id, string $content)` | `void` | ログイン時は編集可能 `<span>`、非ログイン時はそのまま出力 |
| `edit()` | `void` | POST リクエストによるコンテンツ・設定の保存処理 |
| `upload_image()` | `void` | 認証済みユーザーの画像アップロード（JPEG/PNG/GIF/WebP、2MB制限） |
| `menu()` | `void` | `$c['menu']` 設定から `<ul>` ナビゲーションを生成 |
| `login()` | `void` | パスワード認証・セッション生成・パスワード変更処理 |
| `savePassword(string $p)` | `string` | bcrypt ハッシュ化して `auth.json` に保存 |
| `check_login_rate()` | `bool` | IP ベースのログイン試行レート制限チェック |
| `record_login_failure()` | `void` | ログイン失敗を記録 |
| `clear_login_rate()` | `void` | ログイン試行記録をクリア |
| `data_dir()` | `string` | `data/` ディレクトリのパスを返す（なければ自動作成） |
| `settings_dir()` | `string` | `data/settings/` のパスを返す（なければ自動作成） |
| `content_dir()` | `string` | `data/content/` のパスを返す（なければ自動作成） |
| `json_read(string $file, string $dir)` | `array` | 指定ディレクトリの JSON ファイルを読み込んで配列を返す |
| `json_write(string $file, array $data, string $dir)` | `void` | 配列を JSON ファイルへ書き出す |
| `migrate_from_files()` | `void` | 旧フラットファイルから JSON への一回限りの自動移行 |
| `csrf_token()` | `string` | セッション CSRF トークンを生成・取得 |
| `verify_csrf()` | `void` | POST / ヘッダーの CSRF トークンを検証（失敗時 403 終了） |
| `host()` | `void` | リクエスト URI からホスト URL・ページスラッグを解析 |
| `settings()` | `void` | テーマ選択・各種設定フォーム・アップデートパネルを出力 |

`engines/UpdateEngine.php` に定義された主要関数:

| 関数名 | 戻り値 | 説明 |
|-------|--------|------|
| `handle_update_action()` | `void` | POST `ap_action` をディスパッチ（認証・CSRF 検証済み） |
| `check_update()` | `array` | GitHub Releases API から最新バージョン情報を取得・比較（1時間キャッシュ） |
| `check_environment()` | `array` | ZipArchive・allow_url_fopen・書き込み権限・ディスク容量を確認 |
| `backup_current()` | `string` | `backup/YYYYMMDD_His/` に全ファイルをコピー・`meta.json` 生成 |
| `prune_old_backups()` | `void` | `AP_BACKUP_GENERATIONS` を超えた古いバックアップを削除 |
| `apply_update(string $zip_url, string $new_version)` | `void` | ZIP ダウンロード → 展開 → ファイル上書き → `version.json` 更新 |
| `rollback_to_backup(string $backup_name)` | `void` | 指定バックアップから復元（`data/`・`meta.json` 除外） |
| `delete_backup(string $name)` | `void` | 指定バックアップディレクトリを再帰削除 |

---

## 8. アップデートエンジン

### 概要

管理画面からワンクリックで CMS 本体を更新できるエンジン（`engines/UpdateEngine.php`）。
更新前に自動バックアップを作成し、失敗時はロールバックで復元できる。

### 機能一覧

| 機能 | 説明 |
|------|------|
| バージョン表示 | 管理パネルに現在のバージョン（`AP_VERSION` 定数）を表示 |
| 更新確認 | GitHub Releases API から最新バージョンを取得して比較 |
| API キャッシュ | GitHub API レスポンスを `data/settings/update_cache.json` に1時間キャッシュ |
| 事前環境チェック | 更新適用前に ZipArchive / allow_url_fopen / 書き込み権限 / ディスク容量を確認 |
| 自動バックアップ | 更新適用前に `backup/YYYYMMDD_His/` へ全ファイルをコピー（`meta.json` 付き） |
| バックアップ世代管理 | `AP_BACKUP_GENERATIONS`（デフォルト5）を超えた古い世代を自動削除 |
| バックアップメタデータ | 各バックアップに `meta.json`（更新前バージョン / 作成日時 / ファイル数 / サイズ）を記録 |
| 更新適用 | ZIP をダウンロード・展開して `data/`・`backup/` を保護したままファイルを上書き |
| ロールバック | バックアップ一覧から任意のバージョンに復元（`data/` は変更しない） |
| バックアップ削除 | 管理パネルから個別のバックアップを削除（確認ダイアログ付き） |
| バージョン履歴 | `data/settings/version.json` に適用履歴とバックアップ名を記録 |

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
| 任意 URL への接続 | `zip_url` を GitHub ドメインのみ許可 |
| パストラバーサル | `backup_name` を `^[0-9_]+$` でバリデーション（内部二重防御） |
| バックアップ上書き | `apply_update()` の除外リストに `backup` を含む |
| バックアップ直接閲覧 | `.htaccess` で `backup/` を 403 拒否 |
| パス解決失敗 | `rollback_to_backup()` の `realpath()` が `false` を返した場合に 500 エラーを返して中止 |
| meta.json のルート混入 | `rollback_to_backup()` が `meta.json` をコピー対象から除外 |

---

## 未実装（設計確定・実装予定）

| モジュール | 設計書 | ステータス | 説明 |
|----------|--------|-----------|------|
| StaticEngine | `docs/STATIC_GENERATOR.md` Ver.0.2-1 | ✅ 設計確定 | 静的サイト生成エンジン（`engines/StaticEngine.php`） |
| ApiEngine | `docs/HEADLESS_CMS.md` Ver.0.3-1 | ✅ 設計確定 | ヘッドレス CMS REST API エンジン（`engines/ApiEngine.php`） |

## 未実装（未検討）

| モジュール | 略称 | 説明 |
|----------|------|------|
| Core modules | CM | コアモジュール |
| SubCore modules | SCM | サブコアモジュール |
| Adlaire account authentication system | A3S | アドレイルアカウント認証システム |

---

*このドキュメントは `index.php`・`engines/` 配下のファイル・`themes/*/theme.php`・`.htaccess` のソースコード解析に基づいて生成されました。*
