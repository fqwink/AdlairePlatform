# テストカバレッジ分析 — AdlairePlatform

> 最終更新: 2026-03-10（Ver.1.4-pre 対応）

## 現状

**テストカバレッジ: 0%。** PHP・JavaScript 合わせて約 5,000 行超のコードに対し、自動テストが一切存在しません — PHPUnit なし、Jest なし、CI/CD パイプラインなし、テスト設定ファイルなし。

---

## 推奨テストインフラ

### PHP（バックエンド）
- **フレームワーク:** [PHPUnit](https://phpunit.de/)（PHP テストの標準）
- **セットアップ:** `composer.json` に `phpunit/phpunit` を dev 依存として追加
- **設定:** プロジェクトルートに `phpunit.xml` を配置

### JavaScript（フロントエンド）
- **フレームワーク:** [Jest](https://jestjs.io/) または [Vitest](https://vitest.dev/)
- **セットアップ:** `package.json` にテストランナーを dev 依存として追加
- **備考:** JS ファイルはバニラ JS（ES2020+）でモジュールシステム未使用のため、DOM モック（jsdom 等）が必要

---

## テスト優先度

### 優先度 1 — セキュリティクリティカル（高影響・高リスク）

攻撃からアプリケーションを保護する機能群。バグの影響が深刻。

#### 1. 認証・ログイン（`engines/AdminEngine.php`）
- `login()` — パスワード検証、セッション生成、パスワード変更フロー
- `checkLoginRate()` — レート制限（5 回失敗で 15 分ロックアウト）
- `isLoggedIn()` — セッションベース認証チェック
- `savePassword()` — bcrypt ハッシュ生成

**必要なテストケース:**
- 正しいパスワードでアクセス許可 + `$_SESSION['l'] = true` 設定
- 不正パスワードでアクセス拒否 + 失敗記録
- 5 回連続失敗で 15 分ロックアウト発動
- ロックアウトが 15 分後に解除される
- ログイン成功で失敗履歴がクリアされる
- パスワード変更に旧パスワード確認が必要
- ログイン時にセッション再生成（セッション固定攻撃防止）

#### 2. CSRF 保護（`engines/AdminEngine.php`）
- `csrfToken()` — トークン生成
- `verifyCsrf()` — トークン検証（セッション + ヘッダー + POST の3重チェック）

**必要なテストケース:**
- トークンがセッションに生成・保存される
- 有効なトークンで検証通過
- トークン欠如で 403 拒否
- 不正トークンで 403 拒否
- 空セッション CSRF + 空 POST CSRF で通過しない（`hash_equals` バイパス防止）

#### 3. 入力バリデーション・サニタイズ
- `AdminEngine::handleEditField()` — フィールド名の正規表現検証
- `h()` (`index.php`) — HTML エスケープ
- `getSlug()` (`index.php`) — スラッグ生成（パストラバーサル再帰的除去）
- `host()` (`index.php`) — URL 解析・危険文字の除去

**必要なテストケース:**
- `handleEditField()` がパストラバーサル文字（`../` 等）を含むフィールド名を拒否
- `handleEditField()` が未認証リクエストを拒否
- `h()` が `<`, `>`, `"`, `'`, `&` を正しくエスケープ
- `getSlug()` がスペースをハイフンに変換・小文字化
- `getSlug()` がマルチバイト（UTF-8）文字列を正しく処理
- `getSlug()` が `....//` パターンの再帰的パストラバーサルを除去
- `host()` が `index.php`、クォート、山括弧等の危険文字を除去

#### 4. 画像アップロード検証（`engines/AdminEngine.php`）
- `handleUploadImage()` — ファイルタイプ検査、サイズ制限、ランダムファイル名生成

**必要なテストケース:**
- 未認証ユーザーのアップロードを拒否（401）
- 2MB 超のファイルを拒否
- 非画像 MIME タイプ（`application/php`, `text/html` 等）を拒否
- JPEG, PNG, GIF, WebP を受理
- 生成ファイル名がランダム（ユーザー制御不可）
- CSRF トークンが検証される

### 優先度 2 — データ整合性（中影響）

#### 5. JSON 読み書き + キャッシュ（`index.php`）
- `json_read()` — ファイル読み込み（JsonCache 対応）
- `json_write()` — `LOCK_EX` によるアトミック書き込み（JsonCache 更新）
- `JsonCache` — リクエスト内キャッシュ管理

**必要なテストケース:**
- `json_read()` が存在しないファイルで `[]` を返す
- `json_read()` が破損/非 JSON ファイルで `[]` を返す
- `json_read()` が有効な JSON を正しくデコード
- `json_read()` がキャッシュヒット時にファイル I/O をスキップ
- `json_write()` が適切な JSON エンコーディングでファイルを作成
- `json_write()` が Unicode 文字を保持（`JSON_UNESCAPED_UNICODE`）
- `json_write()` が書き込み後にキャッシュを更新
- `json_write()` が書き込み失敗時に 500 を返す
- `JsonCache::invalidate()` が特定パスのキャッシュを無効化

#### 6. リビジョンシステム（`engines/AdminEngine.php`）
- `saveRevision()` — タイムスタンプ付きリビジョンファイル作成
- `pruneRevisions()` — 30 リビジョン上限の適用
- `handleRevisionAction()` — 一覧取得/復元エンドポイント

**必要なテストケース:**
- リビジョンが正しいタイムスタンプとコンテンツで保存される
- プルーニングで `AP_REVISION_LIMIT`（30）超過時に最古のリビジョンが削除される
- 一覧取得が逆時系列順で返される
- リビジョン復元でページコンテンツが更新される
- 無効なフィールド名/リビジョン名が拒否される

#### 7. コンテンツ編集（`engines/AdminEngine.php`）
- `handleEditField()` — 設定 vs ページコンテンツの保存

**必要なテストケース:**
- 設定キー（`title`, `description` 等）が `settings.json` に保存される
- 非設定キーが `pages.json` に保存される
- コンテンツが保存前にトリムされる
- ページコンテンツ保存時にリビジョンが作成される
- 未認証の編集が拒否される（401）

### 優先度 3 — インフラストラクチャ（中影響）

#### 8. AppContext（`engines/AppContext.php`） ⭐ 新規
- `syncFromGlobals()` — グローバル変数からの状態同期
- `config()` / `setConfig()` — 設定値の取得・更新
- `host()` / `loginStatus()` / `credit()` — 状態アクセサ

**必要なテストケース:**
- `syncFromGlobals()` が全グローバル変数を正しく取り込む
- `syncFromGlobals()` が `$hook` が null の場合にデフォルト `[]` を使用
- `config()` が存在するキーの値を返す
- `config()` が存在しないキーでデフォルト値を返す
- `setConfig()` が値を正しく更新

#### 9. Logger（`engines/Logger.php`） ⭐ 新規
- `init()` — ログ初期化
- `debug()` / `info()` / `warning()` / `error()` — ログレベル別出力
- `writeToFile()` — ファイルベースログ出力
- `rotate()` — ログローテーション
- `cleanup()` — 古いログのクリーンアップ

**必要なテストケース:**
- 最小レベル未満のログが無視される
- ログエントリが正しい JSON 形式で出力される
- リクエスト ID が全エントリに含まれる
- ERROR レベルが `error_log()` にも出力される
- 5MB 超過時にローテーションが発動
- 最大 5 世代のログファイルが保持される
- `cleanup()` が 30 日以上経過したファイルを削除

#### 10. MailerEngine（`engines/MailerEngine.php`） ⭐ 新規
- `send()` — リトライ付きメール送信
- `sendContact()` — お問い合わせフォーム用ヘルパー
- `sanitizeHeader()` — ヘッダインジェクション対策

**必要なテストケース:**
- テストモードでメール送信がスキップされる
- テストモードで `getSentMails()` が送信内容を返す
- `sanitizeHeader()` が CR/LF/ヌルバイトを除去
- リトライが最大 2 回実行される
- 送信成功/失敗がログに記録される
- `sendContact()` が正しい件名・本文を構築

#### 11. ThemeEngine（`engines/ThemeEngine.php`）
- `ThemeEngine::load()` — テーマロード（フォールバック付き）
- `ThemeEngine::listThemes()` — ディレクトリ一覧

**必要なテストケース:**
- 有効なテーマ名で正しい `theme.html` をロード
- 無効なテーマ名（特殊文字）で `AP-Default` にフォールバック
- 存在しないテーマで `AP-Default` にフォールバック
- `listThemes()` が `themes/` からディレクトリ名を返す

#### 12. TemplateEngine（`engines/TemplateEngine.php`）
- `render()` — テンプレートレンダリング
- `resolveValue()` — ネストプロパティ解決 ⭐ 新規
- `applyFilter()` — フィルター処理 ⭐ 新規

**必要なテストケース:**
- `{{variable}}` がエスケープ出力される
- `{{{variable}}}` が生 HTML 出力される
- `{{#if var}}...{{/if}}` が条件分岐する
- `{{#each items}}...{{/each}}` がループする
- `{{> partial}}` がパーシャルを読み込む
- `{{user.name}}` がネストプロパティを解決する ⭐ 新規
- `{{var|upper}}` が大文字変換する ⭐ 新規
- `{{var|truncate:10}}` が指定文字数で切り詰める ⭐ 新規
- `{{var|default:fallback}}` が空値でデフォルトを返す ⭐ 新規
- フィルターチェーン `{{var|trim|upper}}` が連鎖適用される ⭐ 新規
- 循環パーシャル参照が最大深度 10 で停止

#### 13. UpdateEngine（`engines/UpdateEngine.php`）
- `check_environment()` — 環境チェック
- `check_update()` — GitHub API 連携（キャッシュ付き）
- `backup_current()` — バックアップ作成
- `apply_update()` — アップデート適用
- `rollback_to_backup()` — ロールバック

**必要なテストケース:**
- `check_environment()` が正しい環境情報を返す
- `check_update()` がキャッシュ未期限時にキャッシュを使用
- `backup_current()` が `data/`・`backup/`・`.git/` を除外
- `apply_update()` が GitHub ドメインのみの URL を許可
- `rollback_to_backup()` が非数値文字を含む名前を拒否

#### 14. マイグレーション（`index.php`）
- `migrate_from_files()` — 2 フェーズデータ移行

**必要なテストケース:**
- Phase 1: `files/` のフラットファイルが `data/` の JSON に移行される
- Phase 2: `data/*.json` が `data/settings/` と `data/content/` に移動される
- 対象ファイルが既存の場合はスキップ
- パスワードハッシュが移行時に保持される

### 優先度 4 — JavaScript（フロントエンド）

#### 15. WYSIWYG エディタ（`engines/JsEngine/wysiwyg.js` — 約 2,890 行）
最大の単一コンポーネント。主要テスト対象:
- HTML サニタイザー（ホワイトリスト方式）— **セキュリティクリティカル**
- ブロック作成/削除/並べ替え
- 画像挿入・リサイズ
- 自動保存メカニズム
- Undo/Redo スタック
- スラッシュコマンドメニュー

#### 16. Webhook 管理（`engines/JsEngine/webhook_manager.js`）
- POST リクエストの CSRF トークンヘッダー送信
- Webhook の追加・削除・テスト送信

#### 17. その他 JS ファイル（`updater.js`, `editInplace.js`, `dashboard.js` 等）
- アップデート確認・適用 UI
- インプレイス編集の保存フロー
- API キー管理・Git 連携・コレクション管理 UI

---

## カバレッジギャップ一覧

| 領域 | 推定行数 | テスト | リスク |
|------|------:|:-----:|:-----:|
| 認証・ログイン | ~80 | 0 | **重大** |
| CSRF 保護 | ~30 | 0 | **重大** |
| 入力バリデーション | ~40 | 0 | **重大** |
| 画像アップロード | ~50 | 0 | **高** |
| JSON I/O + キャッシュ | ~60 | 0 | **高** |
| リビジョンシステム | ~100 | 0 | **高** |
| コンテンツ編集 | ~40 | 0 | **中** |
| AppContext ⭐ | ~150 | 0 | **中** |
| Logger ⭐ | ~180 | 0 | **中** |
| MailerEngine ⭐ | ~155 | 0 | **中** |
| TemplateEngine | ~360 | 0 | **中** |
| ThemeEngine | ~150 | 0 | **中** |
| UpdateEngine | ~330 | 0 | **中** |
| マイグレーション | ~40 | 0 | **中** |
| WYSIWYG エディタ (JS) | ~2,890 | 0 | **中** |
| その他 JS | ~500 | 0 | **低** |

---

## 推奨実装手順

1. **PHPUnit をセットアップ** — `composer.json`、`phpunit.xml`、`tests/` ディレクトリを作成
2. **セキュリティテストを最優先** — 認証、CSRF、入力バリデーション、アップロード検証
3. **新エンジンのテスト** — AppContext、Logger、MailerEngine（テストモード活用）
4. **データ整合性テスト** — JSON I/O + JsonCache、リビジョンシステム、コンテンツ編集
5. **インフラテスト** — TemplateEngine（フィルター・ネストプロパティ含む）、ThemeEngine、UpdateEngine
6. **Jest/Vitest をセットアップ** — `package.json` で JS テスト環境を構築
7. **JS テスト** — WYSIWYG の HTML サニタイザーを最優先、次に自動保存、ブロック操作
8. **CI/CD 追加** — GitHub Actions ワークフローで PHPUnit + Jest を PR/push ごとに実行
