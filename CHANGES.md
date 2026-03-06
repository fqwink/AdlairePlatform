# CHANGES — 変更履歴

---

## 2026-03-06（バグ修正 #5）

- **[Fix]** `settings()` の `glob()` 戻り値に `is_array()` ガードを追加（`false` 返却時の PHP warning を防止）
- **[Fix]** `migrate_from_files()` の `file_get_contents()` 失敗時に `false` が保存されるバグを修正
- **[Fix]** `menu()` でメニュー末尾の空エントリが `<li>` として出力されるバグを修正
- **[Fix]** `js/editInplace.php` — `a`・`title` 変数の `var` 宣言漏れ（グローバル汚染）を修正
- **[Fix]** `js/editInplace.php` — `<br>` 正規表現を `/<br\s*\/?>/gi` に修正して `<br />` をテキストエリア展開時に正しく改行へ変換

---

## 2026-03-05（バグ修正 #4・セキュリティ修正）

- **[Fix]** `editInplace.php` — `$_REQUEST` を `$_GET` に変更（POST データが誤って混入する問題を修正）
- **[Fix]** `settings()` の `chdir()` 失敗時に CWD が復元されないバグを修正
- **[Fix]** `edit()` の CSRF 検証位置を認証チェックの後に移動
- **[Security]** `themeSelect` フィールド名のパストラバーサル防止を強化
- **[Security]** `$rp`（リクエストページ）の出力エスケープを追加
- **[Fix]** PHP 8 の非推奨警告（`count()` への非配列渡し）を修正

---

## 2026-03-04（バグ修正 #3）

- **[Fix]** `editTags()` の `$_REQUEST['login']` を `$_GET['login']` に修正
- **[Fix]** ログイン済みでログインページへアクセスした際のリダイレクト後に `exit` を追加

---

## 2026-03-03（セキュリティ強化・型安全性・jQuery 更新）

- **[Security]** `verify_csrf()` で `hash_equals()` による定数時間比較を採用
- **[Security]** `json_write()` に書き込み失敗時の 500 エラーと `error_log()` を追加
- **[Security]** フィールド名バリデーションの正規表現を厳格化
- **[Type]** 全関数に PHP 型宣言（`string`・`bool`・`void`・`array`）を追加
- **[Update]** jQuery を 3.x 系の最新版（3.7.1）に更新
- **[Security]** ログアウト処理を GET リンクから CSRF 付き POST フォームに変更
- **[Security]** ログイン状態表示に `h()` エスケープを適用

---

## 2026-03-02（バグ修正 #2）

- **[Fix]** `$_REQUEST` を `$_POST` に統一（CSRF 保護の抜け道となりうる GET パラメータ混入を排除）
- **[Fix]** `migrate_from_files()` の実行順序を修正（設定ファイル存在チェック前にデータを読み込む問題）
- **[Fix]** テーマ HTML の `<sb>` タグ誤記を `<b>` に修正

---

## 2026-03-01（セキュリティ強化）

- **[Security]** パスワードハッシュを MD5 から **bcrypt**（`PASSWORD_BCRYPT`）に移行
- **[Security]** **CSRF トークン**保護を全フォーム・全 AJAX リクエストに導入
- **[Security]** ログイン時の `session_regenerate_id(true)` によるセッション固定攻撃対策
- **[Security]** テーマ名のパストラバーサル対策（正規表現バリデーション）
- **[Security]** セッションクッキーに `HttpOnly`・`SameSite=Lax` を設定
- **[Security]** `X-Content-Type-Options`・`X-Frame-Options`・`Referrer-Policy` ヘッダーを `.htaccess` に追加

---

## 2026-02-28（JSON ストレージへの移行）

- **[Feature]** データストレージを個別フラットファイル（`files/` ディレクトリ）から **JSON 形式**に移行
  - `data/settings.json` — サイト設定
  - `data/pages.json` — ページコンテンツ
  - `data/auth.json` — 認証情報（パスワードハッシュ）
- **[Feature]** `migrate_from_files()` — 旧フラットファイルから JSON への自動移行機能を実装
- **[Feature]** `json_read()` / `json_write()` — JSON 読み書きユーティリティ関数を追加
- **[Feature]** `data_dir()` — データディレクトリの自動生成機能を追加

---

## 2026-02-27（PHP 8.2+ 対応）

- **[Compat]** PHP 8.2 非推奨構文を修正（動的プロパティ・各種警告）
- **[Compat]** `@` エラー抑制演算子を `file_exists()` + 条件式に置き換え
- **[Fix]** `continue` 文のスコープ指定（`continue 2`）を適切な位置に修正
- **[Fix]** 三項演算子の変数代入バグ（`$c['content']` の二重代入）を修正

---

## 2014-10-10（初回リリース Ver.β）

- **[Release]** DolphinsValley-Ver.β として初期リリース
- フラットファイルベース CMS の基本機能を実装
- インプレイス編集・テーマエンジン・プラグインフックの基礎を実装
