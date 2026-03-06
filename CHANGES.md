# CHANGES — 変更履歴

---

## 2026-03-06（アップデートエンジン改良 #7）

- **[Feature]** `AP_BACKUP_GENERATIONS=5` 定数を追加
- **[Feature]** `check_environment()` 追加（ZipArchive / allow_url_fopen / 書き込み権限 / ディスク空き容量を確認して結果配列を返す）
- **[Feature]** `ap_action=check_env` エンドポイントを追加
- **[Feature]** 更新適用ボタン押下時に環境チェックを実施し、問題があれば適用を中止してエラーを表示
- **[Feature]** `check_update()` に GitHub API レスポンスを `data/update_cache.json` へ1時間キャッシュする機能を追加
- **[Feature]** `check_update()` に GitHub API レート制限（HTTP 403/429）の検出と専用エラーメッセージを追加（`ignore_errors=true` で HTTP ステータスコードを取得）
- **[Feature]** `prune_old_backups()` 追加：`apply_update()` 実行後に `AP_BACKUP_GENERATIONS` を超えた古い世代を自動削除
- **[Feature]** `backup_current()` にメタデータ生成を追加（`backup/<name>/meta.json` へ `version_before` / `created_at` / `file_count` / `size_bytes` を記録）
- **[Feature]** `delete_backup(string $name)` 追加（バックアップディレクトリを再帰削除）
- **[Feature]** `ap_action=delete_backup` エンドポイントを追加
- **[Feature]** `js/updater.js` のバックアップ一覧をテーブル形式に変更（作成日時・更新前バージョン・サイズを表示）
- **[Feature]** `js/updater.js` に「削除」ボタンを追加（確認ダイアログ後にフェードアウト削除）
- **[Fix]** `rollback_to_backup()` に `realpath()` の `false` チェックを追加
- **[Fix]** `rollback_to_backup()` が `meta.json` をサイトルートへコピーしないよう除外
## 2026-03-06（ライセンス変更 Ver.1.0 → Ver.2.0）

- **[License]** ライセンスを **Adlaire License Ver.1.0** から **Adlaire License Ver.2.0** へ改訂
  - **[License]** ライセンス種別をオープン寄りからクローズドソースライセンスへ全面転換
  - **[License]** ソースコードの位置付けを **"Source Available, Not Open Source"** として明示
    - ソースコードの公開は参照目的に限られ、閲覧・参照はいかなる権利・ライセンスも移転しない旨を条文化（第 3 条）
    - ⚠️ 警告文をライセンス本文（第 3 条）および `README.md` 冒頭・ライセンスセクションに明記
  - **[License]** **権利者** を旧 `IEAS Group および AIZM` から **`Adlaire Group`** へ変更（組織再編による権利承継）
    - 旧 IEAS Group・AIZM が保有していた一切の著作権・知的財産権・ライセンス上の権利は Adlaire Group に承継（第 1 条に明記）
  - **[License]** Adlaire Group の **100% 株主** であり、全プロジェクトの開発方針決定権・ライセンス決定権その他最終決定権の保有者として **倉田和宏氏**（最高権限者）を条文に明記（第 1 条）
  - **[License]** 適用対象を AdlairePlatform 単体から **Adlaire Group が開発・保守・所有するすべてのプロジェクト** へ拡大（第 1 条）
  - **[License]** 改変・二次著作物の作成を **禁止**（旧 Ver.1.0 では許可）
  - **[License]** 再配布・サブライセンスを **禁止**（旧 Ver.1.0 では許可）
  - **[License]** 商用利用を **原則禁止**（内部利用のみ無許諾可・商用は別途許諾契約が必要）に変更（旧 Ver.1.0 では無条件許可）
  - **[License]** **リバースエンジニアリング・逆コンパイル・逆アセンブル・難読化解除**の禁止を新規追加（第 5 条）
  - **[License]** **競合利用**（本ソフトウェアの設計・ロジック・実装を参考にした競合製品・サービスの開発）の禁止を新規追加（第 5 条）
  - **[License]** ライセンス変更通知について、**予告・通知・理由の開示を要することなくいつでも変更・改訂・廃止できる**旨を明示（旧 Ver.1.0 の「予告なし変更可」を条文として明文化）（第 8 条）
  - **[License]** 準拠法（日本法）・専属合意管轄を新規追加（第 11 条）
  - **[License]** 分離可能性条項を新規追加（第 12 条）
  - **[License]** 完全合意条項を新規追加（第 13 条）
- **[Docs]** `Licenses/LICENSE_Ver.2.0.md` を新規追加（旧 `LICENSE_Ver.1.0` は参照用として保持）
- **[Docs]** `README.md` — 以下を更新
  - ファイル冒頭に ⚠️ ソース参照可能警告バナーを追加
  - `## ライセンス` セクションを Ver.2.0 仕様へ全面刷新（許可・禁止事項の箇条書き・ライセンス全文リンクを追記）
  - ディレクトリ構成の `Licenses/` 表記に `LICENSE_Ver.2.0.md` を追加
  - `## Copyright` を `IEAS Group & AIZM` から `Adlaire Group`・`最高権限者：倉田和宏` へ更新

---

## 2026-03-06（アップデートエンジン追加・バグ修正 #6）

- **[Feature]** `AP_VERSION` / `AP_UPDATE_URL` 定数を `index.php` 先頭に追加
- **[Feature]** `handle_update_action()` — POST `ap_action` ディスパッチャを追加（認証・CSRF 検証済み）
  - `check`: GitHub Releases API からバージョン情報を取得
  - `apply`: ZIP ダウンロード → 展開 → `data/`・`backup/` 保護して上書き適用
  - `list_backups`: `backup/` ディレクトリの一覧を返す
  - `rollback`: 指定バックアップから `data/` を除いてファイルを復元
- **[Feature]** `check_update()` — GitHub API レスポンスを解析してバージョン比較結果を返す
- **[Feature]** `backup_current()` — `backup/YYYYMMDD_His/` に全ファイルを再帰コピー（`data/`・`backup/`・`.git/` 除外）
- **[Feature]** `apply_update()` — ZIP 取得・ZipArchive 展開・ファイル上書き・`data/version.json` 更新
- **[Feature]** `rollback_to_backup()` — バックアップから復元（`data/` 除外・バックアップ名を `[0-9_]+` でバリデーション）
- **[Feature]** `settings()` に「↕ アップデート ↕」折りたたみパネルを追加
- **[Feature]** `js/updater.js` を新規作成（更新確認・適用・ロールバック AJAX UI、`esc()` で XSS エスケープ）
- **[Feature]** `loadPlugins()` に `updater.js` を `admin-head` フックとして登録
- **[Security]** `.htaccess` に `RedirectMatch 403 ^.*/backup/` を追加
- **[Fix]** `apply_update()` — `ZipArchive::extractTo()` の戻り値未検査バグを修正（展開失敗時に処理続行していた）
- **[Fix]** `apply_update()` — `realpath($src)` がループ内で毎回評価され `false` 返却時に `$rel` が壊れるバグを修正（`$real_src` を事前計算）
- **[Fix]** `apply_update()` — 除外リストに `'backup'` を追加（ZIP 内の `backup/` ディレクトリが既存バックアップを上書きする問題を防止）
- **[Fix]** `js/updater.js` — HTTP 4xx/5xx エラー時の `.fail()` ハンドラで `xhr.responseJSON.error` を読んでサーバーエラー詳細を表示

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
