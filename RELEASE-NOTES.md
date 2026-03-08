# RELEASE-NOTES — リリースノート

---

## AdlairePlatform Ver.1.2.21（2026-03-08）

Ph3 完了後のバグ修正・著作権更新版。

### バグ修正

**wysiwyg.js**
- `_startDrag` — mousedown 直後の opacity フラッシュ防止（初回 mousemove まで視覚効果を遅延、純粋クリックはスキップ）
- `_applySlashCmd` — `editor.focus()` を selection 設定前に移動（focus() によるカーソルリセット修正）
- `_applySlashCmd` hr ケース — HR 挿入後のカーソル再設定（カーソル消失修正）
- `_showSlashMenu` — DOM 追加後に実寸でビューポートクランプ（画面外はみ出し修正）
- `_showTypePopup` — 同様にビューポートクランプ適用
- `_changeBlockType` — `editor.focus()` を selection 設定前に移動

**index.php**
- `upload_image()` を `handle_update_action()` より前に呼び出すよう順序修正（致命的バグ修正）
- `verify_csrf()` に `empty()` ガード追加（CSRF バイパス防止）
- `AP_VERSION` を `1.2.21` に更新

**updater.js**
- CSRF メタタグ未検出時の null 参照エラー防止ガードを追加

**著作権**
- `index.php` ファイルヘッダーを最新情報に更新（`Adlaire Group`・2026・`Adlaire License Ver.2.0`）

### 動作要件
- PHP 8.2+ **必須**
- Apache（mod_rewrite・mod_headers 有効）または Nginx（php-fpm）
- ZipArchive 拡張（アップデート機能に必要）
- データベース不要

---

## AdlairePlatform Ver.1.2.20（2026-03-08）

Ph3: Editor.js スタイル ブロック体験 完成版。

### 新機能

**Ph2-2 設計レビュー修正**
- `enableObjectResizing` / `enableInlineTableEditing` を `false` に設定（Chromium ネイティブリサイズハンドルを無効化）
- alt 入力欄の `keydown` で `stopPropagation()` 追加（Enter/Escape 誤伝播修正）
- テーブル挿入 HTML に末尾 `<p><br></p>` 追加（テーブル直後カーソル移動修正）

**新ブロックタイプ（Ph3-E）**
- `blockquote` / `pre` / `hr` を新ブロックタイプとして追加
- ツールバーに `❝`・`{}`・`—` ボタンを追加
- `_allowedTags` に `BLOCKQUOTE / PRE / CODE / HR` を追加

**"/" スラッシュコマンドメニュー（Ph3-F）**
- 空行で `/` 入力時にブロックタイプ選択メニューを表示
- ArrowDown/Up で選択移動、Enter で確定、Escape で閉じる
- インクリメンタル絞り込みフィルタ対応

**ブロックハンドル・タイプ変換（Ph3-G）**
- ブロックホバー時に左端へ `⠿` ハンドルを表示
- ハンドルクリックでブロックタイプ変換ポップアップを表示

**ドラッグ並べ替え（Ph3-H）**
- `⠿` ハンドルをドラッグしてブロック順序を変更
- シアン色のドロップラインインジケータ

### 動作要件
- PHP 8.2+ **必須**
- Apache（mod_rewrite・mod_headers 有効）または Nginx（php-fpm）
- ZipArchive 拡張（アップデート機能に必要）
- データベース不要

---

## AdlairePlatform Ver.1.2.16（2026-03-07）

現在の安定バージョン。Ver.1.0 以降に P1〜P4 フェーズで実施したアーキテクチャ刷新・
セキュリティ強化・ドキュメント整備に加え、バグ修正・defense-in-depth 強化を施した版。

### 主な変更点（Ver.1.0 → Ver.1.2.16）

**アーキテクチャ刷新（P1〜P2）**
- PHP 8.2+ 必須化（起動時バージョンチェック）
- jQuery を全廃しバニラJS (ES2020+) に全面移行
- `engines/` ディレクトリを導入しエンジンを分離
  - `engines/ThemeEngine.php` — テーマ検証・切替
  - `engines/UpdateEngine.php` — アップデート・バックアップ・ロールバック
  - `engines/JsEngine/` — フロントエンドスクリプト集約
- データ層を `data/settings/` と `data/content/` に分割
- `plugins/` / `loadPlugins()` を廃止し `registerCoreHooks()` に統合
- RTE (`rte.php` / `rte.js`) を廃止、`class="editText"` に統合

**セキュリティ強化（P3）**
- CSP ヘッダー追加（`default-src 'self'`）
- `engines/` 直接アクセス禁止（.htaccess）
- `nginx.conf.example` 追加（Nginx 向け設定リファレンス）

**ドキュメント整備（P4）**
- `docs/ARCHITECTURE.md` 新規作成
- `docs/STATIC_GENERATOR.md` 草稿
- `docs/HEADLESS_CMS.md` 草稿

**バグ修正・defense-in-depth（Ver.1.2-16）**
- `delete_backup()` / `rollback_to_backup()` に内部バリデーション追加（basename + 正規表現）
- `content()` 関数を型安全に修正（PHP 8.2 Deprecation 防止）
- `editInplace.js` の CSRF エラーを明示的なコンソールログへ変更

### 実装済み機能

**アップデートエンジン**
- `AP_VERSION` 定数によるバージョン管理（`data/settings/version.json` に更新履歴を保存）
- 管理画面から GitHub Releases を参照して最新バージョンを確認
- ワンクリックで ZIP をダウンロード・展開・適用（`data/`・`backup/` は保護）
- 更新前に `backup/YYYYMMDD_His/` へ自動バックアップ（`meta.json` 付き）
- バックアップ一覧からロールバック（ブラウザから操作可能）
- 更新適用前の環境チェック（ZipArchive / allow_url_fopen / ディスク容量）
- GitHub API レスポンスを1時間キャッシュ（レート制限対策）
- バックアップを最大 5 世代まで自動管理（`AP_BACKUP_GENERATIONS` 定数で変更可能）
- バックアップの個別削除機能

**コンテンツ管理**
- JSON フラットファイルストレージ（`data/settings/` / `data/content/`）
- インプレイス編集（クリックしてその場で編集・Fetch API 保存）
- スラッグベースのマルチページ管理
- サイト設定のブラウザ内編集（タイトル・説明・キーワード・著作権・メニュー）
- 旧パス（`data/*.json`）からの自動マイグレーション

**テーマエンジン**
- `themes/` ディレクトリへの配置によるテーマ追加
- ブラウザからのリアルタイムテーマ切替
- 同梱テーマ：`AP-Default`、`AP-Adlaire`
- `engines/ThemeEngine.php` に分離

**セキュリティ**
- bcrypt パスワードハッシュ化
- CSRF トークン保護（全フォーム・全 AJAX）
- セッション固定攻撃対策
- XSS エスケープ（全出力 `h()` 関数）
- CSP ヘッダー（`default-src 'self'`）
- ディレクトリアクセス制御・パストラバーサル防止（`data/`・`backup/`・`engines/`）
- セキュリティヘッダー（X-Frame-Options, X-Content-Type-Options, Referrer-Policy）
- バックアップ名の defense-in-depth バリデーション

**動作要件**
- PHP 8.2+ **必須**
- Apache（mod_rewrite・mod_headers 有効）または Nginx（php-fpm）
- ZipArchive 拡張（アップデート機能に必要）
- データベース不要

---

## AdlairePlatform Ver.1.0.0（2026-03-06）

初回リリース（Ver.β）以降に積み重ねられたセキュリティ強化・
ストレージ移行・PHP 8.2 対応・バグ修正の総まとめ版。

### 実装済み機能

**アップデートエンジン**
- `AP_VERSION` 定数によるバージョン管理（`data/version.json` に更新履歴を保存）
- 管理画面から GitHub Releases を参照して最新バージョンを確認
- ワンクリックで ZIP をダウンロード・展開・適用（`data/`・`backup/` は保護）
- 更新前に `backup/YYYYMMDD_His/` へ自動バックアップ
- バックアップ一覧からロールバック（ブラウザから操作可能）
- 更新適用前の環境チェック（ZipArchive / allow_url_fopen / ディスク容量）
- GitHub API レスポンスを1時間キャッシュ（レート制限対策）
- バックアップを最大 5 世代まで自動管理（`AP_BACKUP_GENERATIONS` 定数で変更可能）
- バックアップに `meta.json`（更新前バージョン / 作成日時 / ファイル数 / サイズ）を記録
- バックアップの個別削除機能

**コンテンツ管理**
- JSON フラットファイルストレージ（`data/settings.json` / `data/pages.json` / `data/auth.json`）
- インプレイス編集（クリックしてその場で編集・AJAX 保存）
- スラッグベースのマルチページ管理
- サイト設定のブラウザ内編集（タイトル・説明・キーワード・著作権・メニュー）

**テーマエンジン**
- `themes/` ディレクトリへの配置によるテーマ追加
- ブラウザからのリアルタイムテーマ切替
- 同梱テーマ：`AP-Default`、`AP-Adlaire`

**セキュリティ**
- bcrypt パスワードハッシュ化
- CSRF トークン保護（全フォーム・全 AJAX）
- セッション固定攻撃対策
- XSS エスケープ（全出力）
- ディレクトリアクセス制御・パストラバーサル防止（`data/`・`backup/`・`files/`）
- セキュリティヘッダー（`X-Frame-Options` 等）
- アップデート URL を GitHub ドメインのみ許可・バックアップ名をバリデーション

**動作要件**
- PHP 8.0+ 推奨（PHP 8.2 完全対応）
- Apache（mod_rewrite・mod_headers 有効）
- jQuery 3.7.1（CDN）
- データベース不要

### バグ修正（Ver.β 以降）

- `glob()` が `false` を返した際の `foreach` エラーを修正
- `migrate_from_files()` の `file_get_contents` 失敗時の誤保存を修正
- メニューの空エントリ出力を修正
- JavaScript グローバル変数汚染を修正
- `<br />` のテキストエリア変換ミスを修正（改行が消失する問題）
- `$_REQUEST` → `$_POST` / `$_GET` への統一
- ログイン済みリダイレクト後の `exit` 漏れを修正
- MD5 ハッシュ検出時の bcrypt 自動移行と管理者警告を追加

---

## AdlairePlatform Ver.β（2014-10-10）

- 初期リリース（DolphinsValley-Ver.β）
- フラットファイルベース CMS の基本実装
- インプレイス編集・テーマエンジン・プラグインフックの基礎
