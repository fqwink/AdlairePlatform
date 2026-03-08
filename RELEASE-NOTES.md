# RELEASE-NOTES — リリースノート

---

## AdlairePlatform Ver.1.3-27（2026-03-08）

管理ツールをエンジン駆動モデルに再設計し、専用ダッシュボード（`?admin`）を導入。
index.php に集約されていた管理ロジック（認証・CSRF・フィールド保存・画像アップロード・リビジョン管理）を `engines/AdminEngine.php` に分離。
フロントエンド上の settings.html パーシャル（クイック設定パネル）を廃止し、ダッシュボードに統合。

### 新機能

- **AdminEngine** — 管理機能を集約する新エンジン（`engines/AdminEngine.php`）。認証・CSRF・フィールド保存・画像アップロード・リビジョン管理・ダッシュボードレンダリングを担当
- **管理ダッシュボード** — `?admin` でアクセスする専用管理画面。テーマ非依存の独自テンプレート・スタイルシートを使用
  - サイト設定（タイトル・説明・キーワード・著作権・テーマ・メニュー）の一括編集
  - ページ一覧（クリックで編集ページへ移動）
  - アップデート確認・適用
  - システム情報（PHP バージョン・ディスク空き・プラットフォーム）
- **`ap_action` 統一** — 全管理 POST アクションを `ap_action` パラメータで統一（`edit_field` / `upload_image` / リビジョン系）。後方互換: `fieldname` のみの POST も `edit_field` として処理

### アーキテクチャ改善

- `index.php` を約 670 行から約 250 行に簡素化（管理ロジックを AdminEngine に移動）
- `ThemeEngine.php` から `buildSettingsContext()` / `renderContent()` を削除し、AdminEngine に委譲
- フロントエンドの settings.html パーシャルを廃止 → ダッシュボードに統合
- ログイン時にフロントエンドのフッターにダッシュボードリンクを表示

### 新規ファイル

| ファイル | 説明 |
|---------|------|
| `engines/AdminEngine.php` | 管理エンジン本体 |
| `engines/AdminEngine/dashboard.html` | ダッシュボードテンプレート（TemplateEngine 方式） |
| `engines/AdminEngine/dashboard.css` | ダッシュボード専用スタイルシート |
| `engines/JsEngine/dashboard.js` | ダッシュボード固有のインタラクション |

### 削除ファイル

| ファイル | 理由 |
|---------|------|
| `themes/AP-Default/settings.html` | ダッシュボードに統合 |
| `themes/AP-Adlaire/settings.html` | ダッシュボードに統合 |

### 後方互換性

- 既存の `theme.php` テーマ用レガシーラッパー関数（`is_loggedin()`, `csrf_token()`, `verify_csrf()`, `content()`, `editTags()`, `menu()`）は index.php に維持。AdminEngine に委譲
- `fieldname` のみの POST リクエスト（`ap_action` なし）も引き続き動作
- フロントエンドのインプレイス編集（editRich / editText）は従来通り動作

---

## AdlairePlatform Ver.1.2-26 — Ver.1.2系終了（2026-03-08）

> **Ver.1.2系は本リビジョン（Ver.1.2-26）をもって開発終了とします。**
> Ver.1.3系では StaticEngine・ApiEngine の実装、管理ツールのダッシュボード化・エンジン駆動モデル化を予定しています。

### Ver.1.2系の総括

Ver.1.2系（Ver.1.2-13 〜 Ver.1.2-26）は、AdlairePlatform のアーキテクチャ刷新・セキュリティ多層強化・WYSIWYG エディタの独自実装・テンプレートエンジン導入を完了したシリーズです。

#### 主要な成果

**アーキテクチャ刷新**
- PHP 8.2+ 必須化・jQuery 全廃・バニラ JS（ES2020+）全面移行
- エンジン分離: ThemeEngine / UpdateEngine / TemplateEngine
- データ層分割: `data/settings/` / `data/content/`
- プラグインシステム廃止 → 内部コアフック統合

**テンプレートエンジン（Ver.1.2-26）**
- `engines/TemplateEngine.php` 新規実装（`{{var}}` / `{{{raw}}}` / `{{#if}}` / `{{#each}}` / `{{> partial}}`）
- テーマ PHP フリー化（`theme.html` 方式・PHP 知識不要でテーマ作成可能）
- パーシャル・ループメタ変数・未処理タグ検出

**WYSIWYG エディタ（Ver.1.2-20 〜 Ver.1.2-25）**
- Editor.js スタイルのブロックベースアーキテクチャ（依存ライブラリなし）
- 11種のブロックタイプ・7種のインラインツール
- スラッシュコマンド・ドラッグ並べ替え・ARIA アクセシビリティ
- リビジョン管理・変更差分表示・ピン留め・検索

**セキュリティ多層強化**
- CSP ヘッダー・CSRF トークン・XSS エスケープ・レート制限
- `engines/` 直接アクセス禁止・ディレクトリ保護
- 画像アップロード保護（MIME 検証・ランダムファイル名・PHP 実行不可）
- defense-in-depth バリデーション

#### Ver.1.3系で実装予定

以下の機能は設計確定済みですが、Ver.1.2系では未実装のまま終了します。Ver.1.3系にて実装予定です。

| モジュール | 設計書 | 設計バージョン |
|----------|--------|-------------|
| StaticEngine（静的サイト生成） | `docs/STATIC_GENERATOR.md` | Ver.0.3-1 |
| ApiEngine（ヘッドレス CMS REST API） | `docs/HEADLESS_CMS.md` | Ver.0.3-1 |
| 管理ツールのダッシュボード化 | — | — |
| 管理ツールのエンジン駆動モデル化 | — | — |

---

### Ver.1.2-26 リリース内容

テーマシステムのアーキテクチャを刷新。自前の軽量テンプレートエンジンを導入し、テーマファイルから PHP コードを排除。さらにパーシャル・ループ変数・エラー検出の改良を実施。

### 新機能

- **TemplateEngine** — `{{var}}` / `{{{raw}}}` / `{{#if}}` / `{{#each}}` / `{{> partial}}` の5構文による軽量テンプレートエンジン（外部依存なし）
- **パーシャル（部分テンプレート）** — `{{> name}}` 構文でテーマ内の `.html` ファイルを読み込み。循環参照防止（最大深度10）
- **ループメタ変数** — `{{#each}}` 内で `@index`（インデックス）・`@first`（最初の要素）・`@last`（最後の要素）が使用可能
- **未処理タグ検出** — レンダリング後に残存する `{{...}}` タグを `error_log()` で警告。テーマ開発時のデバッグを支援
- **PHP フリーテーマ** — テーマを `theme.html`（HTML/CSS のみ）で作成可能に。PHP 知識不要
- **StaticEngine 対応設計** — `buildStaticContext()` で `admin=false` を渡すだけで管理者 UI が自動除外。`stripAdminUI()` の正規表現操作が不要に

### アーキテクチャ改善

- `ThemeEngine` に `buildContext()` / `buildStaticContext()` / `parseMenu()` / `buildSettingsContext()` メソッドを追加
- 管理者設定パネルを `settings.html` パーシャルに分離（テーマごとにカスタマイズ可能）
- テーマロード時に `theme.html` を優先、なければ `theme.php` にフォールバック（後方互換維持）
- テーマ内での任意 PHP コード実行リスクを排除（`theme.html` 方式使用時）

### 後方互換性

- 既存の `theme.php` テーマは引き続き動作します
- `theme.html` が存在しない場合、自動的に `theme.php` にフォールバックします

---

## AdlairePlatform Ver.1.2-25（2026-03-08）

WYSIWYG エディタの編集履歴機能を改良。セキュリティ修正・堅牢性強化・UX 改善・機能拡張の全 16 項目。

### セキュリティ修正

- CSRF トークンを GET パラメータから除去し、POST ボディ + ヘッダーに統一
- リビジョン API にセッション単位のレート制限を追加（60 秒あたり 30 リクエスト）

### バグ修正・堅牢性

- リビジョン保存・削除にファイルロックを追加（並行アクセス時の競合状態防止）
- リビジョン復元時のコンテンツ検証強化（解析エラー時のフォールバック）
- 復元前に未保存の変更がある場合の明示的警告
- 大容量コンテンツ（2000 行超）での diff 処理に簡易比較フォールバック

### UX 改善

- 履歴パネルのキーボード操作（Escape/←→/↑↓/Enter）
- diff の等行折りたたみ（4 行超の未変更行を折りたたみ、クリックで展開）
- 色覚多様性対応（+/- プレフィックスと左ボーダーによる視覚的区別）
- 復元リビジョンに「復元」バッジ表示
- タブにサブテキスト説明を追加

### 新機能

- **リビジョン間比較** — 任意の 2 つのリビジョンを A/B 選択して差分表示
- **ユーザー帰属** — リビジョンに保存ユーザー名を記録・表示
- **リビジョン検索** — キーワードでリビジョン内容を全文検索
- **ピン留め** — 重要なリビジョンに★マークで自動削除対象から除外
- **コンテンツ取得 API** — `get_revision` 専用エンドポイント追加

### アクセシビリティ

- ARIA role/label 属性（dialog, tablist, tab, button, log）
- フォーカストラップとフォーカス管理

---

## AdlairePlatform Ver.1.2-24（2026-03-08）

WYSIWYG エディタに編集履歴機能を追加。リビジョン管理・変更差分表示・セッション内履歴 UI の 3 機能。

### 新機能

- **リビジョン管理** — コンテンツ保存ごとにサーバーサイドでリビジョンを自動保存（最大30世代、`data/content/revisions/` に JSON 形式で保存）
- **リビジョン復元** — 履歴パネルの「リビジョン」タブから過去のバージョンを選択して復元（復元前に確認ダイアログ表示）
- **変更差分表示** — 現在の内容と前回保存時、または任意のリビジョンとの差分を視覚的に表示（追加=緑背景、削除=赤背景+取り消し線）
- **セッション内履歴 UI** — Undo/Redo スタックをリスト形式で可視化し、任意のスナップショットにクリックでジャンプ可能
- **編集履歴パネル** — ツールバーの📋ボタンからモーダルパネルを表示（「セッション」「リビジョン」2タブ構成）

### 技術詳細

- リビジョン API: `ap_action=list_revisions`（一覧取得）/ `restore_revision`（復元）— 認証・CSRF・入力バリデーション付き
- LCS ベースの簡易 diff アルゴリズム（外部ライブラリ不使用）
- スナップショットに `{ snap, time, blockCount }` メタデータを付与

---

## AdlairePlatform Ver.1.2-23（2026-03-08）

WYSIWYG エディタ改良版（セキュリティ・バグ修正・新機能・UX 改善）。

### セキュリティ修正

- SVG data URI 経由の XSS 防止（`data:image/svg+xml` をブロック、png/jpeg/gif/webp のみ許可）
- リンク URL バリデーション（`javascript:` / `data:` スキーム拒否、`_isSafeUrl()` ヘルパー追加）
- リッチテキスト貼り付け時の HTML サニタイズ（`_cleanHtml()` 経由で浄化後に挿入）

### バグ修正

- ブロック結合時のカーソル位置が結合点に正しく配置されるよう修正（HTML オフセットベース）
- スラッシュメニューのインデックス範囲外エラー防止
- 画像アップロード時の `_getFocusedBlock()` null フォールバック修正
- テーブル最終セルで Tab → 新行追加、最初のセルで Shift+Tab → 前ブロックへ移動
- チェックリスト最初の空項目 + Backspace → 段落に変換

### 新機能

- **Undo/Redo** — Ctrl+Z / Ctrl+Shift+Z / Ctrl+Y（最大50操作、構造変更時にスナップショット保存）
- **空ブロックプレースホルダ** — 「/ を入力してコマンド...」を CSS `::before` で表示
- **インラインツールバーアクティブ状態** — 太字/斜体/下線/取消線のアクティブ表示
- **タイプ変換ポップアップキーボード操作** — ArrowDown/Up で選択、Enter で確定、Escape で閉じ
- **タッチデバイスドラッグ** — touchstart/touchmove/touchend でブロック並べ替え対応

### UX 改善

- `alert()` をステータスバー通知に置換（5秒後自動消去）
- インラインツールバーのビューポートクランプ
- サイレントエラー catch を `console.warn` に改善
- RAF 重複設定を削除（パフォーマンス改善）

### 動作要件
- PHP 8.2+ **必須**
- Apache（mod_rewrite・mod_headers 有効）または Nginx（php-fpm）
- ZipArchive 拡張（アップデート機能に必要）
- データベース不要

---

## AdlairePlatform Ver.1.2-22（2026-03-08）

Ph3: Editor.js スタイル ブロックベースエディタ 完成版（全面改修）。

### 新機能

**ブロックベースアーキテクチャ（Ph3-Core）**
- 単一 contenteditable から各ブロック独立 contenteditable に全面移行
- 内部データモデル: `{ id, type, data }` 配列、HTML 互換入出力
- ブロック間カーソル移動（Enter で分割、Backspace で結合、ArrowUp/Down で移動）

**新ブロックタイプ（Ph3-E）**
- `blockquote` / `pre` / `hr` を新ブロックタイプとして追加
- ツールバーに `❝`・`{}`・`—` ボタンを追加
- `_allowedTags` に `BLOCKQUOTE / PRE / CODE / HR` 追加

**インラインツール拡張（Ph3-L）**
- 取消線 `<s>` (Ctrl+Shift+S)、インラインコード `<code>` (Ctrl+E)
- マーカー `<mark>` (Ctrl+Shift+M)、リンク (Ctrl+K)
- フローティングインラインツールバー（テキスト選択時自動表示）

**"/" スラッシュコマンドメニュー（Ph3-F）**
- 空ブロックで `/` 入力時にブロックタイプ選択メニューを表示
- ArrowDown/Up で選択移動、Enter で確定、Escape で閉じる
- インクリメンタル絞り込みフィルタ対応
- ビューポートクランプ（画面端での自動位置調整）

**ブロックハンドル・タイプ変換（Ph3-G）**
- ブロックホバー時に左端へ `⠿` ハンドルを表示
- ハンドルクリックでブロックタイプ変換ポップアップを表示
- Block Tunes: テキスト配置（左/中央/右）をポップアップから選択
- ブロック削除機能

**ドラッグ並べ替え（Ph3-H）**
- `⠿` ハンドルをドラッグしてブロック順序を変更
- シアン色のドロップラインインジケータ
- 初回 mousemove まで視覚効果を遅延（純粋クリックとの区別）

**テーブルブロック（Ph3-I）**
- 3×3 初期テーブル挿入（ツールバー / スラッシュコマンド）
- 各セルが個別 contenteditable
- Tab / Shift+Tab でセル間移動
- 行列の追加・削除ボタン

**画像ブロック強化（Ph3-J）**
- サイズプリセット: 25% / 50% / 75% / 100%
- Alt テキスト入力欄（即時反映）
- キャプション入力欄（`<figcaption>`）

**チェックリスト（Ph3-K）**
- スラッシュコマンド `/checklist` で挿入
- チェックボックスクリックで状態切替
- Enter で新項目追加、空項目 + Backspace で削除

**ARIA アクセシビリティ（Ph3-N）**
- ツールバー: `role="toolbar"` + `aria-label`
- ブロック: `role="textbox"` + `aria-label`
- ステータス: `aria-live="polite"`
- スラッシュメニュー: `role="listbox"` / `role="option"`

### 動作要件
- PHP 8.2+ **必須**
- Apache（mod_rewrite・mod_headers 有効）または Nginx（php-fpm）
- ZipArchive 拡張（アップデート機能に必要）
- データベース不要

---

## AdlairePlatform Ver.1.2-21（2026-03-08）

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
- `AP_VERSION` を `'1.2.21'` に更新

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

## AdlairePlatform Ver.1.2-20（2026-03-08）

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

## AdlairePlatform Ver.1.2-16（2026-03-07）

現在の安定バージョン。Ver.1.0 以降に P1〜P4 フェーズで実施したアーキテクチャ刷新・
セキュリティ強化・ドキュメント整備に加え、バグ修正・defense-in-depth 強化を施した版。

### 主な変更点（Ver.1.0 → Ver.1.2-16）

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

## AdlairePlatform Ver.1.0-11（2026-03-06）

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
