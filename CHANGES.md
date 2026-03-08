# CHANGES — 変更履歴

---

## 2026-03-08（Ver.1.2-26 — TemplateEngine 導入・テーマ PHP フリー化）

- **[Architecture]** `engines/TemplateEngine.php` 新規作成 — 軽量テンプレートエンジン（変数展開・条件分岐・ループの3構文）
- **[Architecture]** `engines/ThemeEngine.php` 改修 — `theme.html` 優先ロード・`buildContext()` / `buildStaticContext()` / `parseMenu()` メソッド追加
- **[Architecture]** テーマ形式に `theme.html`（テンプレートエンジン方式・PHP フリー）を追加、`theme.php` はレガシーフォールバックとして維持
- **[Security]** テーマファイル内での任意 PHP コード実行を排除（`theme.html` 方式使用時）
- **[Enhancement]** テーマ作成に PHP 知識が不要に — HTML/CSS のみでテーマ作成可能
- **[Enhancement]** StaticEngine 向け設計改善 — `ob_start()` + `stripAdminUI()` 方式を廃止、`TemplateEngine::render()` + `ThemeEngine::buildStaticContext()` 方式に変更
- **[Theme]** `themes/AP-Default/theme.html` 新規追加
- **[Theme]** `themes/AP-Adlaire/theme.html` 新規追加
- **[Docs]** STATIC_GENERATOR.md Ver.0.3-1 — レンダリング戦略を TemplateEngine ベースに更新
- **[Docs]** ARCHITECTURE.md — TemplateEngine 追加・ThemeEngine 改修を反映
- **[Docs]** SPEC.md Ver.0.2-5 — テンプレート構文・コンテキスト変数・テーマ構造を追加

---

## 2026-03-08（Ver.1.2-25 — WYSIWYG エディタ 編集履歴機能改良）

- **[Security]** CSRF トークンの GET パラメータ露出修正 — `list_revisions` API を POST に統一、トークンはヘッダーで送信
- **[Security]** リビジョン API レート制限 — セッション単位で 60 秒あたり 30 リクエスト上限
- **[Fix]** リビジョン削除の競合状態修正 — `save_revision()` にディレクトリ単位ファイルロック追加
- **[Fix]** 復元時のエラーハンドリング強化 — `_parseHtmlToBlocks()` の結果検証、HTTP エラーコードの適切な処理
- **[Fix]** 復元前の未保存変更警告 — 未保存の編集がある場合に明示的な警告メッセージ表示
- **[Fix]** 大容量コンテンツの diff ガード — 2000 行超のコンテンツで LCS フォールバックに切替（ブラウザフリーズ防止）
- **[UX]** 履歴パネルのキーボード操作 — Escape で閉じる、←→ でタブ切替、↑↓ でリスト移動、Enter で選択
- **[UX]** diff 等行折りたたみ — 変更のない行が 4 行超の場合に折りたたみ表示（クリックで展開）
- **[UX]** 色覚多様性対応 — `+`/`-` プレフィックス記号と左ボーダーで差分を視覚的に区別
- **[UX]** 復元リビジョンのマーキング — 復元操作で作成されたリビジョンに「復元」バッジ表示
- **[UX]** タブ説明テキスト追加 — 「セッション（ブラウザ内の操作履歴）」「リビジョン（サーバー保存の版管理）」
- **[Feature]** リビジョン間の比較 — 任意の 2 つのリビジョンを選択して差分を表示
- **[Feature]** ユーザー帰属情報 — リビジョンに保存ユーザー名を記録・表示
- **[Feature]** リビジョン検索・フィルタ — キーワードでリビジョン内容を全文検索
- **[Feature]** リビジョンのピン留め — 重要なバージョンに★マークを付けて自動削除対象から除外
- **[Feature]** リビジョンコンテンツ取得専用 API — `ap_action=get_revision` エンドポイント追加
- **[Quality]** fetch エラーの console.warn ロギング追加
- **[Accessibility]** ARIA role/label 属性追加（dialog, tablist, tab, button, log）
- **[Accessibility]** フォーカストラップ・フォーカス管理の実装

---

## 2026-03-08（Ver.1.2-24 — WYSIWYG エディタ 編集履歴機能）

- **[Feature]** リビジョン管理 — コンテンツ保存ごとにサーバーサイドでリビジョンを `data/content/revisions/` に保存（最大30世代）
- **[Feature]** リビジョン復元 — 管理画面から過去リビジョンの内容を選択して復元可能
- **[Feature]** リビジョン API — `ap_action=list_revisions` / `restore_revision` エンドポイント追加（認証・CSRF・入力バリデーション付き）
- **[Feature]** 変更差分表示 — LCS ベースの簡易 diff アルゴリズムによるテキスト差分表示（追加=緑、削除=赤）
- **[Feature]** セッション内履歴 UI — Undo/Redo スタックを可視化するパネル（操作番号・タイムスタンプ・ブロック数表示）
- **[Feature]** 編集履歴パネル — 「セッション」「リビジョン」2タブのモーダル UI（ツールバーの📋ボタンから表示）
- **[Feature]** スナップショットジャンプ — セッション履歴の任意のスナップショットにクリックで移動
- **[Enhancement]** スナップショットメタデータ — Undo/Redo スタックにタイムスタンプ・ブロック数を記録

---

## 2026-03-08（Ver.1.2-23 — WYSIWYG エディタ改良）

- **[Security]** SVG data URI XSS 防止 — `data:image/svg+xml` を img src で拒否（png/jpeg/gif/webp のみ許可）
- **[Security]** リンク URL バリデーション — `_isSafeUrl()` ヘルパー追加、`javascript:` / `data:` スキームを拒否
- **[Security]** リッチテキスト貼り付けサニタイズ — HTML ペースト時に `_cleanHtml()` でサニタイズ後に挿入
- **[Fix]** ブロック結合時のカーソル復元 — `savedRange` ベースから HTML オフセットベースに変更（`_setCursorAtOffset` 新設）
- **[Fix]** スラッシュメニューインデックス範囲外防止 — `_slashIdx` をフィルタ結果長で制限
- **[Fix]** 画像アップロードフォールバック — `_getFocusedBlock()` null 時に最後のブロック後に `_addBlockAfter` を使用
- **[Fix]** テーブル Tab 端のエッジケース — 最後のセルで Tab → 行追加、最初のセルで Shift+Tab → 前ブロックへ
- **[Fix]** チェックリスト最初の項目削除 — idx=0 で空 + Backspace → 段落に変換
- **[Feature]** Undo/Redo スタック — Ctrl+Z / Ctrl+Shift+Z / Ctrl+Y（上限50操作、ブロック追加/削除/タイプ変更/ドラッグ完了時にスナップショット保存）
- **[Feature]** 空ブロックプレースホルダ — CSS `::before` で「/ を入力してコマンド...」を表示
- **[Feature]** インラインツールバーアクティブ状態 — `queryCommandState` で B/I/U/S のアクティブ表示
- **[Feature]** タイプ変換ポップアップのキーボード操作 — ArrowDown/Up/Enter/Escape 対応
- **[Feature]** タッチデバイスドラッグ対応 — touchstart/touchmove/touchend リスナー追加
- **[UX]** `alert()` をステータスバー通知に置換（画像アップロード失敗等）
- **[UX]** インラインツールバーのビューポートクランプ（画面端での位置補正）
- **[Quality]** サイレント `catch (_)` を `catch (e) { console.warn() }` に改善
- **[Quality]** RAF 重複設定を削除（スラッシュメニュー・タイプ変換ポップアップ）

---

## 2026-03-08（Ver.1.2-22 — Ph3: Editor.js スタイル ブロックベースエディタ）

- **[Feature]** `engines/JsEngine/wysiwyg.js` — Editor.js スタイルのブロックベースアーキテクチャに全面改修（393行 → 1905行）
- **[Feature]** ブロックベース contenteditable — 各ブロックが独立した contenteditable 要素を持つ設計
- **[Feature]** 新ブロックタイプ追加 — blockquote / code(pre) / delimiter(hr) / table / image(figure+figcaption) / checklist
- **[Feature]** インラインツール拡張 — S（取消線）/ Code（インラインコード）/ Marker / Link をツールバー・ショートカットに追加
- **[Feature]** フローティングインラインツールバー — テキスト選択時に B/I/U/S/Code/Marker/Link ボタンを自動表示
- **[Feature]** "/" スラッシュコマンドメニュー — 空ブロックで / 入力時にブロックタイプ選択（インクリメンタル絞り込み・ビューポートクランプ）
- **[Feature]** ブロックハンドル ⠿ — ホバー時表示、クリックでタイプ変換ポップアップ（Block Tunes: テキスト配置含む）
- **[Feature]** ドラッグ並べ替え — ⠿ ハンドルでブロック順序変更、シアン色ドロップラインインジケータ
- **[Feature]** テーブルブロック — セル個別 contenteditable、Tab 移動、行列追加削除
- **[Feature]** 画像ブロック強化 — サイズプリセット(25%/50%/75%/100%)、Alt テキスト入力、キャプション(figcaption)
- **[Feature]** チェックリスト — チェックボックス + テキスト、Enter で項目追加、Backspace で削除
- **[Feature]** Block Tunes — テキスト配置（左/中央/右）をブロックハンドルポップアップから選択
- **[A11y]** ARIA 対応 — toolbar/textbox/listbox ロール、aria-label、aria-live="polite"
- **[Fix]** Chromium ネイティブリサイズハンドル無効化（enableObjectResizing/enableInlineTableEditing）
- **[Docs]** `docs/SPEC.md` Ver.0.2-5 — WYSIWYG セクション（§5.5）をブロックベースアーキテクチャに更新
- **[Docs]** `docs/features.md` — WYSIWYG セクション全面更新
- **[Docs]** `docs/AdlairePlatform_Design.md` — エディタ設計セクション更新（Phase 3 追加）

---

## 2026-03-07（Ver.1.2-16 — defense-in-depth・バグ修正）

- **[Security]** `delete_backup()` に内部バリデーションを追加（`basename()` + `/^[0-9_]+$/` 正規表現検証）— handle_update_action() 側の検証に依存せず defense-in-depth として各関数内でも入力を検証
- **[Security]** `rollback_to_backup()` に同様の内部バリデーションを追加
- **[Fix]** `content()` 関数の引数を型安全に修正（`string` 型宣言 + `(string)($content ?? '')` null強制変換）— PHP 8.2 で null 連結が Deprecation になる問題を防止
- **[Fix]** `editInplace.js` — CSRF メタタグが未検出の場合に `console.error('[AdlairePlatform] CSRF token meta tag not found')` を出力（サイレントフェイルから明示的なデバッグログへ）
- **[Docs]** `AP_VERSION` を `'1.2.0'` から `'1.2.16'` へ更新
- **[Docs]** `docs/AdlairePlatform_Design.md` を現在の実装状態（Ver.1.2-16）に完全同期（設計書ヘッダー・ディレクトリ構成・バージョン計画・タスクリスト・機能リストを更新）

## 2026-03-07（Ver.1.2-15 — P4: ドキュメント整備）

- **[Docs]** `docs/ARCHITECTURE.md` 新規作成（設計概念・ファイル責務・アーキテクチャ方針）
- **[Docs]** `docs/STATIC_GENERATOR.md` 草稿作成（StaticEngine 設計）
- **[Docs]** `docs/HEADLESS_CMS.md` 草稿作成（ApiEngine 設計）
- **[Fix]** 各ドキュメントのバージョン表記を `docs/VERSIONING.md` 規則（`Ver.{Major}.{Minor}-{Revision}`）に準拠させる

## 2026-03-07（Ver.1.2-14 — P3: セキュリティ強化）

- **[Security]** `.htaccess` に `engines/` 内 PHP ファイルへの直接アクセス禁止ルールを追加
- **[Security]** `.htaccess` に CSP ヘッダー（`default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'`）を追加
- **[Docs]** `nginx.conf.example` 新規作成（CSP・`engines/` 保護・`data/` / `backup/` 保護・URL rewrite を含む Nginx 設定リファレンス）

## 2026-03-07（Ver.1.2-13 — P2: エンジン分離・データ層分割・サードパーティ排除）

- **[Refactor]** `loadPlugins()` を廃止し `registerCoreHooks()` へ置き換え（内部専用フック管理）
- **[Refactor]** `plugins/` ディレクトリを廃止
- **[Feature]** `engines/ThemeEngine.php` 新規作成・分離（テーマ自動検出・切替ロジック）
- **[Feature]** `engines/UpdateEngine.php` 新規作成・分離（アップデート・バックアップ・ロールバック・環境チェック）
- **[Feature]** `engines/JsEngine/` ディレクトリ新規作成（`editInplace.js`, `autosize.js`, `updater.js` を集約）
- **[Feature]** `data/settings/` サブディレクトリ新規作成（`settings.json`, `auth.json`, `update_cache.json`, `version.json` を格納）
- **[Feature]** `data/content/` サブディレクトリ新規作成（`pages.json` を格納）
- **[Refactor]** `index.php` のパス参照を `settings_dir()` / `content_dir()` ユーティリティへ更新
- **[Feature]** `migrate_from_files()` に旧パス（`data/*.json`）から新パスへの自動移行ロジックを追加
- **[Refactor]** `js/` ディレクトリを廃止（`engines/JsEngine/` へ移行）
- **[Refactor]** `admin-richText` フック削除（Phase 1 完了により不要）

## 2026-03-07（Ver.1.1-12 — P1: PHP 8.2 必須化・jQuery廃止・JsEngine・RTE廃止）

- **[Compat]** PHP 8.2+ 必須チェックを `index.php` 先頭に追加（`PHP_VERSION_ID < 80200` で HTTP 500 エラーを返す）
- **[Refactor]** jQuery CDN タグを `themes/AP-Default/theme.php` および `themes/AP-Adlaire/theme.php` から削除
- **[Feature]** `engines/JsEngine/editInplace.js` 新規作成（jQuery依存を全廃・バニラJS ES2020+ で完全リライト・CSRF対応・Fetch API保存）
- **[Feature]** `engines/JsEngine/autosize.js` セルフホスト配置（CDN 依存排除）
- **[Refactor]** `rte.php` / `rte.js` 削除・`richText` クラスを廃止し `editText` に統合
- **[Refactor]** `admin-richText` フックを Phase 1 で廃止（Phase 2 で WYSIWYG採用時に復活予定）

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
- **[Feature]** `engines/JsEngine/updater.js` のバックアップ一覧をテーブル形式に変更（作成日時・更新前バージョン・サイズを表示）
- **[Feature]** `engines/JsEngine/updater.js` に「削除」ボタンを追加（確認ダイアログ後にフェードアウト削除）
- **[Fix]** `rollback_to_backup()` に `realpath()` の `false` チェックを追加
- **[Fix]** `rollback_to_backup()` が `meta.json` をサイトルートへコピーしないよう除外

---

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
- **[Feature]** `engines/JsEngine/updater.js` を新規作成（更新確認・適用・ロールバック AJAX UI、`esc()` で XSS エスケープ）
- **[Feature]** `loadPlugins()` に `updater.js` を `admin-head` フックとして登録
- **[Security]** `.htaccess` に `RedirectMatch 403 ^.*/backup/` を追加
- **[Fix]** `apply_update()` — `ZipArchive::extractTo()` の戻り値未検査バグを修正（展開失敗時に処理続行していた）
- **[Fix]** `apply_update()` — `realpath($src)` がループ内で毎回評価され `false` 返却時に `$rel` が壊れるバグを修正（`$real_src` を事前計算）
- **[Fix]** `apply_update()` — 除外リストに `'backup'` を追加（ZIP 内の `backup/` ディレクトリが既存バックアップを上書きする問題を防止）
- **[Fix]** `engines/JsEngine/updater.js` — HTTP 4xx/5xx エラー時の `.fail()` ハンドラで `xhr.responseJSON.error` を読んでサーバーエラー詳細を表示

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
