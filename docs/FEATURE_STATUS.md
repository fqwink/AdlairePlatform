# 実装機能ステータス一覧

Ver.2.1-41 | 最終更新: 2026-03-16

## 凡例

| 記号 | 意味 |
|------|------|
| ✅ | 実装済み — 動作するロジックが存在 |
| ⚠️ | 部分実装 — 一部機能のみ動作、または簡易実装 |
| 🔧 | スタブ/プレースホルダ — メソッド存在するがダミーデータ返却 or 例外送出 |
| ❌ | 未実装（インターフェースのみ） — Interface.ts に定義あるが実装クラスなし |

---

## モジュール別ステータス

### APF — Adlaire Platform Foundation

| 機能 | ステータス | 備考 |
|------|-----------|------|
| **Container (DIコンテナ)** | ✅ | `bind`, `singleton`, `make`, `has` 全実装 |
| **Router** | ✅ | GET/POST/PUT/PATCH/DELETE/ANY/match、グループ、ミドルウェア、パスパラメータ、クエリマッピング全対応 |
| **Request** | ✅ | `fromDenoRequest` でDeno標準Request変換、全メソッド実装 |
| **Response** | ✅ | json/html/text/redirect/notFound/error、`toDenoResponse()` でDeno変換 |
| **MiddlewarePipeline** | ✅ | チェーン実行、ネスト対応 |
| **Application** | ✅ | Router + Middleware統合、Denoリクエストハンドラ |
| **EventBus** | ✅ | listen/dispatch/hasListeners/removeListener、優先度対応 |
| **Str (文字列ユーティリティ)** | ✅ | slug/safePath/random/limit/camel/snake/kebab/title/startsWith/endsWith/contains |
| **Arr (配列ユーティリティ)** | ✅ | get/set/has/only/except/flatten/pluck/where（ドット記法対応） |
| **Config (設定管理)** | ✅ | get/set/setDefaults/clearCache/all |
| **Validator** | ✅ | required/string/number/boolean/email/min/max/in/url/slug ルール対応 |
| **Security** | ⚠️ | escape/sanitize/randomString/generateCsrfToken/sha256 実装。`encrypt`/`decrypt`/`rateLimit`/`verify`/`hash` は SecurityInterface に定義あるが未実装 |
| **FileSystem** | ✅ | read/write/readJson/writeJson/exists/delete/ensureDir（Deno API使用） |
| **JsonStorage** | ✅ | read/write/clearCache（キャッシュ付きJSONファイルストレージ） |
| **ConnectionInterface (DB接続)** | ❌ | connect/query/queryOne/execute/insert/update/transaction — インターフェースのみ |
| **QueryBuilderInterface** | ❌ | table/select/where/join/orderBy/limit/paginate/insert/update/delete — インターフェースのみ |
| **ModelInterface (ORM)** | ❌ | save/delete/toArray/toJson — インターフェースのみ |
| **SchemaInterface (マイグレーション)** | ❌ | create/drop/hasTable — インターフェースのみ |
| **BlueprintInterface** | ❌ | id/string/text/integer/boolean/timestamps — インターフェースのみ |
| **CacheInterface** | ❌ | get/set/has/delete/clear/remember/forever — インターフェースのみ |
| **LoggerInterface** | ❌ | debug/info/warning/error/critical/channel — インターフェースのみ |
| **SessionInterface** | ❌ | start/get/set/has/delete/destroy/regenerate/flash — インターフェースのみ |
| **SecurityInterface (完全版)** | ❌ | hash/verify/encrypt/decrypt/rateLimit — インターフェースのみ（Securityクラスは部分実装） |
| **ResponseFactory.file()** | ❌ | ファイルダウンロードレスポンス — インターフェースのみ |
| **ResponseFactory.download()** | ❌ | ダウンロードレスポンス — インターフェースのみ |

### ACE — Adlaire Content Engine

| 機能 | ステータス | 備考 |
|------|-----------|------|
| **CollectionManager** | ✅ | create/delete/listCollections/getSchema/isEnabled — ACS経由でストレージ操作 |
| **ContentManager** | ✅ | getItem/saveItem/deleteItem/listItems（ソート・ページネーション・ドラフトフィルタ対応） |
| **ContentManager.search()** | ✅ | 全文検索（簡易トークンマッチング） |
| **MetaManager** | ✅ | extractMeta/buildMeta/mergeMeta/validateMeta — 簡易YAMLパーサー内蔵 |
| **ContentValidator** | ✅ | validate/validateSlug/sanitizeSlug — 型チェック・min/max対応 |
| **WebhookService** | ✅ | listWebhooks/addWebhook/deleteWebhook/toggleWebhook/dispatch — fetch()で実配信 |
| **RevisionService** | ✅ | list/get/restore/pin/search — ACS経由で全操作 |
| **ApiRouter** | ✅ | registerEndpoint/dispatch/listEndpoints |
| **ACE REST API** | ✅ | /api/collections, /api/collections/{name}, /api/collections/{name}/items, /api/search |
| **CollectionServiceInterface (ファサード)** | ❌ | loadAllAsPages/migrateFromPagesJson 等 — インターフェースのみ、実装クラスなし |

### AIS — Adlaire Infrastructure Services

| 機能 | ステータス | 備考 |
|------|-----------|------|
| **AppContext** | ✅ | get/set/has/all/loadFromFile/saveToFile/validate/dataDir/settingsDir/contentDir/resolveHost |
| **I18n (国際化)** | ✅ | init/getLocale/setLocale/t(パラメータ置換対応)/all/allNested/htmlLang — JSONファイルベース |
| **ServiceContainer** | ✅ | bind/singleton/make/has/register(Provider)/boot |
| **EventDispatcher** | ✅ | listen/dispatch/hasListeners/removeListener（優先度対応） |
| **DiagnosticsManager** | ✅ | log/logEnvironmentIssue/logSlowExecution/healthCheck/collectWithUnsent/send/loadConfig/saveConfig/clearLogs/purgeExpiredLogs/getTimings |
| **ApiCache** | ✅ | remember(TTL対応)/invalidateContent |
| **GitService** | ✅ | loadConfig/saveConfig/testConnection/pull/push/log/status/createPreviewBranch — `Deno.Command`でgitコマンド実行 |
| **UpdateService.checkUpdate()** | 🔧 | 常に `available: false` を返すスタブ |
| **UpdateService.checkEnvironment()** | 🔧 | Denoバージョンを返すが `diskSpace: 0` 固定 |
| **UpdateService.executeApplyUpdate()** | 🔧 | 常に `success: false, error: "No update available"` を返すスタブ |
| **UpdateService.executeRollback()** | ⚠️ | バックアップ存在チェックのみ、実際のロールバック処理なし |
| **UpdateService.executeDeleteBackup()** | ✅ | ACS経由でファイル削除 |
| **UpdateService.listBackups()** | ⚠️ | ファイル一覧取得可能だが `createdAt`/`size`/`version` は空値 |
| **AIS REST API** | ✅ | /api/settings, /api/settings/{key}, /api/i18n, /api/i18n/{key} |
| **UpdaterInterface (低レベル)** | ❌ | checkForUpdate/applyUpdate/rollback/getLastError — インターフェースのみ |

### ASG — Adlaire Static Generator

| 機能 | ステータス | 備考 |
|------|-----------|------|
| **Generator.buildAll()** | ✅ | Markdownコンテンツ読み込み→テンプレートレンダリング→HTML出力→状態保存 |
| **Generator.buildDiff()** | ✅ | ハッシュベース差分検出、変更/追加のみビルド、削除ファイル除去 |
| **Generator.clean()** | ✅ | 出力ディレクトリのHTMLファイル全削除 |
| **Generator.getStatus()** | ✅ | ビルドステータス・設定・最終ビルド統計情報 |
| **HybridResolver** | ✅ | Static-First配信：静的ファイル確認→動的フォールバック→Write-Throughキャッシュ |
| **HybridResolver.invalidate/invalidateAll** | ✅ | 個別/全キャッシュ無効化 |
| **BuildCache** | ✅ | get/set(TTL対応)/getContentHash/hasChanged/saveState/loadState/buildManifest/needsFullRebuild/commitManifest |
| **SiteRouter** | ✅ | resolveSlug/buildUrl/generateSitemap（XML形式） |
| **Deployer.generateHtaccess()** | ✅ | Apache .htaccess生成（リダイレクト、圧縮、キャッシュ設定） |
| **Deployer.generateRedirectsFile()** | ✅ | Netlify形式の_redirectsファイル生成 |
| **Deployer.createZip()** | 🔧 | `throw new Error("ZIP creation requires platform-specific implementation")` |
| **Deployer.createDiffZip()** | 🔧 | `throw new Error("ZIP creation requires platform-specific implementation")` |
| **HtmlMinifier** | ✅ | コメント除去、空白圧縮、pre/code/script/style保護 |
| **CssMinifier** | ✅ | コメント除去、空白圧縮、calc()保護 |
| **TemplateRenderer** | ✅ | {{変数}}/{{{生HTML}}}/{{#if}}/{{#each}}/{{>パーシャル}}/フィルター/ドット記法/ネスト対応 |
| **MarkdownService** | ✅ | parseFrontmatter(簡易YAML)/toHtml(見出し/リスト/コード/画像/リンク/太字/引用/打消線)/loadDirectory |
| **ThemeManager** | ✅ | loadTheme/getTemplate/getPartials/getAssets — テーマJSON設定+テンプレート読み込み |
| **Builder** | ✅ | buildPage/buildIndex/buildPermalink/buildMetaTags(OGP対応) |
| **ASG REST API** | ✅ | /api/build/status, /api/build/diff, /api/build/full, /api/build/clean |
| **StaticServiceInterface (高レベルファサード)** | ❌ | buildZipFile/buildDiffZipFile/generateSitemap/generateRobotsTxt/generate404Page/generateSearchIndex/generateRedirects/copyAssets/syncUploads/runHook — インターフェースのみ |
| **DiffBuilderInterface** | ❌ | detectChanges/markBuilt — インターフェースのみ |
| **ImageProcessorInterface** | ❌ | resize/thumbnail/toWebP/getInfo — インターフェースのみ |

### AP — Adlaire Platform Controllers

| 機能 | ステータス | 備考 |
|------|-----------|------|
| **BaseController** | ✅ | hasAction/getAction/ok/error/validationError/parseBody/getParam |
| **AuthController.showLogin()** | ⚠️ | 空bodyを返す（テンプレートエンジン依存） |
| **AuthController.authenticate()** | ✅ | ACS経由でログイン認証 |
| **AuthController.logout()** | ✅ | ACS経由でログアウト |
| **DashboardController.index()** | ✅ | ページ数・設定情報返却 |
| **ApiController.dispatch()** | ✅ | ActionDispatcher経由で POST アクション振り分け |
| **ActionDispatcher** | ✅ | ACTION_MAP によるアクション名→コントローラーメソッド解決 |
| **AdminController.editField()** | ✅ | フィールド単位の更新（パストラバーサル防止付き） |
| **AdminController.uploadImage()** | ✅ | ACS経由でファイルアップロード |
| **AdminController.deletePage()** | ✅ | ACS経由でページ削除 |
| **AdminController.listRevisions()** | ✅ | リビジョン一覧取得 |
| **AdminController.getRevision()** | ✅ | リビジョン詳細取得 |
| **AdminController.restoreRevision()** | ✅ | リビジョン復元 |
| **AdminController.pinRevision()** | ✅ | リビジョン固定/解除 |
| **AdminController.searchRevisions()** | ⚠️ | ファイル名の簡易マッチングのみ（コンテンツ検索なし） |
| **AdminController.userAdd()** | ✅ | SHA-256パスワードハッシュ、重複チェック |
| **AdminController.userDelete()** | ✅ | ユーザー削除 |
| **AdminController.redirectAdd()** | ✅ | リダイレクトルール追加 |
| **AdminController.redirectDelete()** | ✅ | リダイレクトルール削除 |
| **CollectionController.create()** | ✅ | コレクション作成 |
| **CollectionController.delete()** | ✅ | コレクション削除 |
| **CollectionController.itemSave()** | ✅ | アイテム保存 |
| **CollectionController.itemDelete()** | ✅ | アイテム削除 |
| **CollectionController.migrate()** | 🔧 | アイテム数を返すのみ、実際のマイグレーション処理なし |
| **GitController.configure()** | ✅ | Git設定保存 |
| **GitController.test()** | ⚠️ | 設定存在チェックのみ、実際の接続テストなし（`reachable: true` 固定） |
| **GitController.pull()** | 🔧 | `{ pulled: true }` 固定レスポンス |
| **GitController.push()** | 🔧 | `{ pushed: true }` 固定レスポンス |
| **GitController.log()** | 🔧 | `{ commits: [] }` 空配列固定 |
| **GitController.status()** | 🔧 | `{ clean: true, changes: [] }` 固定レスポンス |
| **GitController.previewBranch()** | 🔧 | `{ branch, preview: true }` 固定レスポンス |
| **WebhookController.add()** | ✅ | UUID生成、Webhook登録 |
| **WebhookController.delete()** | ✅ | Webhook削除 |
| **WebhookController.toggle()** | ✅ | 有効/無効切替 |
| **WebhookController.test()** | 🔧 | `{ tested: true, status: 200 }` 固定レスポンス（実際のHTTPテストなし） |
| **StaticController.buildDiff()** | 🔧 | `{ built: true, mode: "diff", pages: 0 }` 固定 |
| **StaticController.buildAll()** | 🔧 | `{ built: true, mode: "all", pages: 0 }` 固定 |
| **StaticController.clean()** | 🔧 | `{ cleaned: true }` 固定 |
| **StaticController.buildZip()** | 🔧 | `{ zip: true, path: "" }` 固定 |
| **StaticController.status()** | 🔧 | `{ lastBuild: null, status: "idle" }` 固定 |
| **StaticController.deployDiff()** | 🔧 | `{ deployed: true, mode: "diff" }` 固定 |
| **UpdateController.check()** | 🔧 | `{ available: false }` 固定 |
| **UpdateController.checkEnv()** | ✅ | Denoバージョン情報を返却 |
| **UpdateController.apply()** | 🔧 | `{ applied: false, message: "No update available" }` 固定 |
| **UpdateController.listBackups()** | ✅ | ACS経由でバックアップ一覧 |
| **UpdateController.rollback()** | 🔧 | `{ rolledBack: backup }` 固定（実際のロールバック処理なし） |
| **UpdateController.deleteBackup()** | ✅ | ACS経由でバックアップ削除 |
| **DiagnosticController.setEnabled()** | ✅ | 診断有効/無効設定保存 |
| **DiagnosticController.setLevel()** | ✅ | 診断レベル設定保存 |
| **DiagnosticController.preview()** | 🔧 | `{ preview: "Diagnostic report preview" }` 固定 |
| **DiagnosticController.sendNow()** | 🔧 | `{ sent: true }` 固定（実際の送信なし） |
| **DiagnosticController.clearLogs()** | 🔧 | `{ cleared: true }` 固定（実際のログクリアなし） |
| **DiagnosticController.getLogs()** | 🔧 | `{ logs: [], level }` 空配列固定 |
| **DiagnosticController.getSummary()** | 🔧 | `{ errors: 0, warnings: 0, info: 0, uptime: 0 }` 固定 |
| **DiagnosticController.health()** | ✅ | ランタイム情報・タイムスタンプ返却 |
| **AP ミドルウェア群** | — | 以下参照 |
| **CsrfMiddleware** | ✅ | トークン生成、検証（1時間TTL、使い捨て）、GET/HEAD/OPTIONS スキップ |
| **AuthMiddleware** | ✅ | Bearerトークン抽出、コールバック検証 |
| **RateLimitMiddleware** | ✅ | IPベース、ウィンドウ方式、Retry-Afterヘッダー |
| **CorsMiddleware** | ✅ | プリフライト対応、オリジン許可リスト |
| **SecurityHeadersMiddleware** | ✅ | X-Content-Type-Options/X-Frame-Options/X-XSS-Protection/Referrer-Policy |
| **RequestLoggingMiddleware** | ✅ | メソッド/パス/ステータス/レスポンス時間/IP/リクエストID記録 |
| **AP ルート登録** | ✅ | /login, /logout, /admin/*, /admin/api, /admin/git/*, /admin/static/*, /admin/update/*, /admin/diagnostic/* |

### ACS — Adlaire Client Services

| 機能 | ステータス | 備考 |
|------|-----------|------|
| **ClientFactory** | ✅ | 設定に基づきAdlaireClient生成 |
| **createClient()** | ✅ | ショートカット関数 |
| **createMockClient()** | ✅ | テスト用モッククライアント（全メソッドnoop） |
| **HttpTransport** | ✅ | GET/POST/PUT/DELETE、タイムアウト、AbortController、リクエスト/レスポンスインターセプター |
| **AuthService** | ✅ | login/logout/session/verify/autoLogin/csrfToken/verifyCsrf/onAuthChange |
| **StorageService** | ✅ | read/write/delete/exists/list/readMany |
| **StorageService.watch()** | 🔧 | 即座にunsubscribe返却のスタブ（「SSEベースの実装はEventSourceServiceで行う」コメント） |
| **FileService** | ✅ | upload(FormData)/download/delete/exists/info/uploadImage/thumbnailUrl/webpUrl |
| **EventSourceService (SSE)** | ✅ | connect/disconnect/on/off/isConnected — EventSource API使用 |
| **withRetry (リトライヘルパー)** | ✅ | 指数バックオフ、リトライ可能ステータスコード判定 |
| **エラークラス群** | ✅ | NetworkError/AuthError/ServerError/TimeoutError |
| **ConnectionState** | ✅ | DISCONNECTED/CONNECTING/CONNECTED/RECONNECTING |
| **ユーティリティ関数** | ✅ | joinUrl/buildQueryString/bearerHeader/csrfHeader/jsonHeaders/extractData/objectToFormData/calculateBackoff |

### AEB — Adlaire Editor & Blocks（ブラウザサイド）

| 機能 | ステータス | 備考 |
|------|-----------|------|
| **Editor** | ✅ | 初期化、ブロックレンダリング、保存（シリアライズ）、Ctrl+Z/Y/S キーバインド |
| **EventBus** | ✅ | on/once/off/emit/clear/listenerCount |
| **BlockRegistry** | ✅ | register/unregister/has/get/create/getTypes/getAll/clear |
| **StateManager** | ✅ | get/set/update/subscribe/unsubscribe/getAll/reset/has/delete（リアクティブ通知） |
| **HistoryManager** | ✅ | push/undo/redo/canUndo/canRedo/getCurrent/clear/getInfo/setLimit/replaceCurrent/getAt |
| **ParagraphBlock** | ✅ | contentEditable、アライメント、サニタイズ |
| **HeadingBlock** | ✅ | H2/H3、レベル変更、アライメント |
| **ListBlock** | ✅ | 順序あり/なし、動的アイテム更新 |
| **QuoteBlock** | ✅ | blockquote、contentEditable |
| **CodeBlock** | ✅ | pre/code、言語属性 |
| **ImageBlock** | ✅ | 画像表示、キャプション編集 |
| **TableBlock** | ✅ | 編集可能テーブル、ヘッダー行対応 |
| **ChecklistBlock** | ✅ | チェックボックス、テキスト編集 |
| **DelimiterBlock** | ✅ | 水平線 |
| **sanitizer** | ✅ | HTMLサニタイズ（許可タグ/属性制御、javascript:ブロック） |
| **dom** | ✅ | create/setContent/append/prepend/remove/closest/findAll/find/matches/offset/position/on(委譲)/trigger/addClass/removeClass/toggleClass/hasClass |
| **selection** | ✅ | get/getRange/save/restore/clear/selectAll/getText/isInsideElement/getSelectedElement/hasSelection/isCollapsed/getRect/setRange/collapse/wrap/insertNode/insertHTML |
| **keyboard** | ✅ | isModifier/isMod/isMac/matches/on/isCursorAtStart/isCursorAtEnd/getShortcutString/preventEditorDefaults |

### JsEngine — ブラウザサイド管理UI

| 機能 | ステータス | 備考 |
|------|-----------|------|
| **ap-utils.ts** | ✅ | getCsrf/escHtml/post(FormData)/postAction(URLSearchParams+Promise)/apiPost(JSON) |
| **ap-api-client.ts** | ✅ | 公開API呼び出し (`window.AP.api`)、フォーム自動バインド (ap-contact) |
| **ap-events.ts** | ✅ | イベントバス（ファイル存在確認済み） |
| **ap-i18n.ts** | ✅ | i18nクライアントサイド対応（ファイル存在確認済み） |
| **ap-search.ts** | ✅ | クライアントサイド検索（search-index.json読み込み、トークンスコアリング、デバウンス） |
| **editInplace.ts** | ✅ | インライン編集（contentEditable→textarea→Fetch保存）、テーマ選択、設定パネル開閉、XSSサニタイズ |
| **dashboard.ts** | ✅ | ページ削除UI（確認ダイアログ→postAction） |
| **autosize.ts** | ✅ | textarea自動リサイズ（ファイル存在確認済み） |
| **aeb-adapter.ts** | ✅ | AEBエディタとダッシュボードの統合アダプター（ファイル存在確認済み） |
| **collection_manager.ts** | ✅ | コレクション作成/削除/移行、アイテムCRUD、Markdownプレビュー、スラッグ自動生成 |
| **git_manager.ts** | ✅ | Git設定保存/接続テスト/Pull/Push/コミット履歴表示 |
| **static_builder.ts** | ✅ | 差分ビルド/フルビルド/クリーン/ZIPダウンロード/ステータス表示 |
| **updater.ts** | ✅ | アップデート管理UI（ファイル存在確認済み） |
| **api_keys.ts** | ✅ | APIキー管理UI（ファイル存在確認済み） |
| **webhook_manager.ts** | ✅ | Webhook管理UI（ファイル存在確認済み） |
| **diagnostics.ts** | ✅ | 診断管理UI（ファイル存在確認済み） |

---

## サーバ・エントリポイント

| 機能 | ステータス | 備考 |
|------|-----------|------|
| **main.ts (HTTPサーバ)** | ✅ | `Deno.serve()` でHTTPサーバ起動 |
| **Routerディスパッチ** | ✅ | ルート解決→ミドルウェアパイプライン→ハンドラ実行 |
| **静的ファイル配信** | ✅ | CSS/JS/画像/フォント、Content-Type自動判定、Cache-Control、パストラバーサル防止 |
| **ページレンダリング** | ✅ | pages.json + Markdownコレクション読み込み→テンプレートレンダリング |
| **クエリ→パスマッピング** | ✅ | `?login`→`/login`, `?admin`→`/admin`, `?ap_api=X`→`/api/{endpoint}`, POST `ap_action`→`/dispatch` |
| **bootstrap.ts** | ✅ | ApplicationFacade(シングルトン)、DI登録、設定読み込み、i18n初期化、イベントリスナー登録 |
| **routes.ts** | ✅ | グローバルミドルウェア、ヘルスチェック、全モジュールルート登録、StaticFileSystemアダプター |

---

## 型定義(types.ts)の利用状況

| 型 | 使用箇所 | ステータス |
|----|---------|-----------|
| `Result<T, E>` | 直接使用箇所なし | ❌ 未使用 |
| `ApiResponse<T>` | ACS/AP/ACE 全域で使用 | ✅ |
| `PaginatedResponse<T>` | QueryBuilderInterface.paginate のみ | ❌ DB未実装のため未使用 |
| `HttpMethodValue` | Router/Request 全域で使用 | ✅ |
| `RouteDefinition` | Router.listRoutes() で使用 | ✅ |
| `RequestContext` | AP コントローラー全域で使用 | ✅ |
| `ResponseData` | AP コントローラー全域で使用 | ✅ |
| `MiddlewareId` | 型定義のみ、直接参照箇所なし | ❌ 未使用 |
| `AuthResult` | ACS AuthService で使用 | ✅ |
| `SessionInfo` | ACS AuthService で使用 | ✅ |
| `UserInfo` | AuthResult 経由で使用 | ✅ |
| `UserRole` | UserInfo/SessionInfo 経由 | ✅ |
| `PageData` | ASG Generator/Builder で使用 | ✅ |
| `PageMeta` | PageData 経由で使用 | ✅ |
| `RedirectRule` | ASG Deployer で使用 | ✅ |
| `CollectionSchema` | ACE CollectionManager で使用 | ✅ |
| `FieldDef` | ACE ContentValidator/MetaManager で使用 | ✅ |
| `FieldType` | FieldDef 経由で使用 | ✅ |
| `CollectionItem` | ACE ContentManager で使用 | ✅ |
| `ItemMeta` | CollectionItem 経由で使用 | ✅ |
| `CollectionSummary` | ACE CollectionManager.listCollections() | ✅ |
| `BuildStatusValue` | ASG BuildStatus/GeneratorStatus で使用 | ✅ |
| `BuildResult` | ASG Generator で使用 | ✅ |
| `BuildStats` | BuildResult 経由で使用 | ✅ |
| `BuildManifest` | ASG BuildCache で使用 | ✅ |
| `BuildState` | ASG BuildCache で使用 | ✅ |
| `ThemeConfig` | ASG ThemeManager で使用 | ✅ |
| `TemplateContext` | ASG Generator/Builder で使用 | ✅ |
| `NavigationItem` | TemplateContext で定義、直接生成箇所なし | ⚠️ 型のみ使用 |
| `SiteSettings` | TemplateContext.site で参照 | ⚠️ 型のみ（空オブジェクトで生成） |
| `DiagnosticsReport` | AIS DiagnosticsManager で使用 | ✅ |
| `DiagEvent` | DiagnosticsManager で使用 | ✅ |
| `DiagLevel` | DiagEvent 経由で使用 | ✅ |
| `HealthCheckResult` | AIS DiagnosticsManager で使用 | ✅ |
| `QueryLog` | ConnectionInterface に関連、DB未実装 | ❌ 未使用 |
| `GitResult` | AIS GitService で使用 | ✅ |
| `GitStatus` | AIS GitService で使用 | ✅ |
| `GitLogEntry` | AIS GitService で使用 | ✅ |
| `UpdateInfo` | AIS UpdateService で使用 | ✅ |
| `BackupEntry` | AIS UpdateService で使用 | ✅ |
| `WebhookConfig` | ACE WebhookService で使用 | ✅ |
| `WebhookEvent` | WebhookService で使用 | ✅ |
| `ApiKeyInfo` | 直接使用箇所なし | ❌ 未使用 |
| `RevisionEntry` | ACE RevisionService で使用 | ✅ |
| `ImageInfo` | ACS FileService で使用 | ✅ |
| `ValidationRules` | APF Validator で使用 | ✅ |
| `ValidationErrors` | APF Validator/ACE で使用 | ✅ |
| `DatabaseConfig` | DB未実装のため未使用 | ❌ 未使用 |
| `MigrationDefinition` | DB未実装のため未使用 | ❌ 未使用 |
| `LogLevelValue` | LoggerInterface で参照、実装なし | ❌ 未使用 |
| `AdlaireClient` | ACS/bootstrap 全域で使用 | ✅ |
| `AuthModule` | AdlaireClient 経由で使用 | ✅ |
| `StorageModule` | AdlaireClient 経由で使用 | ✅ |
| `FileModule` | AdlaireClient 経由で使用 | ✅ |
| `HttpModule` | AdlaireClient 経由で使用 | ✅ |
| `WriteOperation` | ACS FileService で使用 | ✅ |
| `SearchResult` | ACE ContentManager.search() で使用 | ✅ |
| `SearchIndexEntry` | 直接使用箇所なし（ap-search.tsは独自型） | ❌ 未使用 |
| `LocaleId` | AIS I18n で使用 | ✅ |
| `TranslationDict` | AIS I18n で使用 | ✅ |
| `RateLimitConfig` | 直接使用なし（RateLimitMiddlewareはコンストラクタ引数） | ❌ 未使用 |
| `CorsConfig` | 直接使用なし（CorsMiddlewareはコンストラクタ引数） | ❌ 未使用 |
| `SitemapEntry` | ASG SiteRouter.generateSitemap() で使用 | ✅ |
| `BuildHook` | 直接使用箇所なし | ❌ 未使用 |
| `ActionDefinition` | AP ActionDispatcher.registeredActions() で使用 | ✅ |
| `FrontMatterResult` | ACE MetaManager/ASG MarkdownService で使用 | ✅ |

---

## 未実装機能一覧（優先度順）

### 優先度: 高（コア機能の欠落）

| # | 機能 | モジュール | 説明 |
|---|------|-----------|------|
| 1 | **データベースレイヤー** | APF | `ConnectionInterface`, `QueryBuilderInterface`, `ModelInterface`, `SchemaInterface`, `BlueprintInterface` — 全てインターフェースのみ。現在は全データをJSONファイル/ACS経由で管理 |
| 2 | **セッション管理** | APF | `SessionInterface` — start/get/set/has/flash/regenerate等。現在はトークンベースの認証のみ |
| 3 | **ロガー** | APF | `LoggerInterface` — debug/info/warning/error/critical/channel。console.log以外のログ出力基盤なし |
| 4 | **キャッシュ基盤** | APF | `CacheInterface` — get/set/has/delete/clear/remember/forever。ApiCacheは存在するが汎用キャッシュなし |
| 5 | **GitController実装** | AP | pull/push/log/status/previewBranch が全てスタブ。GitServiceは実装済みだがControllerと未接続 |

### 優先度: 中（機能拡張）

| # | 機能 | モジュール | 説明 |
|---|------|-----------|------|
| 6 | **StaticController実装** | AP | buildDiff/buildAll/clean/buildZip/status/deployDiff が全てスタブ。Generatorは実装済みだがControllerと未接続 |
| 7 | **ZIP生成** | ASG | `Deployer.createZip()` / `createDiffZip()` が「platform-specific implementation required」で例外送出 |
| 8 | **画像処理** | ASG | `ImageProcessorInterface` — resize/thumbnail/toWebP/getInfo が全てインターフェースのみ |
| 9 | **暗号化/復号** | APF | `SecurityInterface.encrypt()` / `decrypt()` 未実装 |
| 10 | **パスワードハッシュ検証** | APF | `SecurityInterface.hash()` / `verify()` 未実装（bcrypt等）。AdminController.userAdd()はSHA-256を直接使用 |
| 11 | **UpdateService実体** | AIS | checkUpdate/checkEnvironment/executeApplyUpdate 全てスタブ。実際のアップデート配信基盤なし |
| 12 | **StaticServiceInterface** | ASG | 高レベルファサード（サイトマップ生成、robots.txt、404ページ、検索インデックス、アセットコピー、ビルドフック）全て未実装 |
| 13 | **DiagnosticControllerの実データ連携** | AP | preview/sendNow/clearLogs/getLogs/getSummary が全てスタブ。DiagnosticsManagerは実装済みだがControllerと未接続 |
| 14 | **CollectionServiceInterface** | ACE | loadAllAsPages/migrateFromPagesJson — 高レベルファサード未実装 |
| 15 | **StorageService.watch()** | ACS | ファイル変更のリアルタイム監視（SSEベース）が未実装 |

### 優先度: 低（将来機能）

| # | 機能 | モジュール | 説明 |
|---|------|-----------|------|
| 16 | **DiffBuilderInterface** | ASG | detectChanges/markBuilt — Generator内に類似ロジックあるが独立インターフェースは未実装 |
| 17 | **UpdaterInterface (低レベル)** | AIS | checkForUpdate/applyUpdate/rollback/getLastError — UpdateServiceとは別の低レベルインターフェース |
| 18 | **ResponseFactory.file/download** | APF | ファイルダウンロードレスポンス生成 |
| 19 | **WebhookController.test() 実装** | AP | 実際のHTTPリクエスト送信によるWebhookテスト |
| 20 | **CollectionController.migrate() 実装** | AP | pages.json→Markdownコレクション移行の実処理 |

---

## 未使用型定義一覧

以下の型は `types.ts` に定義されているが、コードベースで直接使用されていない。

| 型 | 関連機能 |
|----|---------|
| `Result<T, E>` | 汎用結果型 — 使用可能だが採用されていない |
| `PaginatedResponse<T>` | ページネーション — DB層未実装のため |
| `MiddlewareId` | ミドルウェア識別子 — 文字列直接使用で代替 |
| `QueryLog` | クエリログ — DB層未実装のため |
| `ApiKeyInfo` | APIキー管理 — サーバサイド実装なし |
| `SearchIndexEntry` | 検索インデックス — ap-search.tsは独自型を使用 |
| `RateLimitConfig` | レートリミット設定 — ミドルウェアはコンストラクタ引数で直接受取 |
| `CorsConfig` | CORS設定 — ミドルウェアはコンストラクタ引数で直接受取 |
| `BuildHook` | ビルドフック — StaticServiceInterface未実装のため |
| `DatabaseConfig` | DB接続設定 — DB層未実装のため |
| `MigrationDefinition` | マイグレーション — DB層未実装のため |
| `LogLevelValue` | ログレベル — Logger未実装のため |
| `NavigationItem` | ナビゲーション — 型定義のみでデータ生成なし |

---

## 統計サマリー

| カテゴリ | 件数 |
|---------|------|
| ✅ 実装済み機能 | 約 130 |
| ⚠️ 部分実装 | 約 10 |
| 🔧 スタブ/プレースホルダ | 約 25 |
| ❌ 未実装（インターフェースのみ） | 約 15 |
| 未使用型定義 | 13 |

### モジュール別完成度（概算）

| モジュール | 完成度 | 備考 |
|-----------|--------|------|
| APF | 70% | コア機能は完成、DB/Cache/Logger/Session未実装 |
| ACE | 95% | CollectionServiceInterfaceのファサード除き全実装 |
| AIS | 80% | UpdateService大半がスタブ、他は完成 |
| ASG | 85% | ZIP生成・画像処理・高レベルファサード未実装、コアビルド機能は完成 |
| AP | 60% | GitController/StaticController/DiagnosticController大半がスタブ（サービス層は実装済みだが未接続） |
| ACS | 95% | StorageService.watch()のスタブ除きほぼ完成 |
| AEB | 100% | ブロックエディタ完全実装 |
| JsEngine | 100% | ブラウザサイドUI完全実装 |
