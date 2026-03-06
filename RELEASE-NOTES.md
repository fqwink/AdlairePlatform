# RELEASE-NOTES — リリースノート

---

## AdlairePlatform Ver.1.0.0（2026-03-06）

現在の安定バージョン。初回リリース（Ver.β）以降に積み重ねられたセキュリティ強化・
ストレージ移行・PHP 8.2 対応・バグ修正の総まとめ版。

### 実装済み機能

**アップデートエンジン**
- `AP_VERSION` 定数によるバージョン管理（`data/version.json` に更新履歴を保存）
- 管理画面から GitHub Releases を参照して最新バージョンを確認
- ワンクリックで ZIP をダウンロード・展開・適用（`data/`・`backup/` は保護）
- 更新前に `backup/YYYYMMDD_His/` へ自動バックアップ
- バックアップ一覧からロールバック（ブラウザから操作可能）

**コンテンツ管理**
- JSON フラットファイルストレージ（`data/settings.json` / `data/pages.json` / `data/auth.json`）
- インプレイス編集（クリックしてその場で編集・AJAX 保存）
- スラッグベースのマルチページ管理
- サイト設定のブラウザ内編集（タイトル・説明・キーワード・著作権・メニュー）

**テーマエンジン**
- `themes/` ディレクトリへの配置によるテーマ追加
- ブラウザからのリアルタイムテーマ切替
- 同梱テーマ：`AP-Default`、`AP-Adlaire`

**プラグインシステム**
- `plugins/` 自動ロード機構
- `admin-head` / `admin-richText` フックポイント

**セキュリティ**
- bcrypt パスワードハッシュ化
- CSRF トークン保護（全フォーム・全 AJAX）
- セッション固定攻撃対策
- XSS エスケープ（全出力）
- ディレクトリアクセス制御・パストラバーサル防止（`data/`・`backup/`・`files/`）
- セキュリティヘッダー（`X-Frame-Options` 等）
- アップデート URL を GitHub ドメインのみ許可・バックアップ名をバリデーション

**動作要件**
- PHP 8.0+（PHP 5.3 以上で動作）
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
