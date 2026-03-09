<?php
/**
 * ApiEngine - ヘッドレスCMS / 公開・管理 REST API エンジン
 *
 * 公開エンドポイント（認証不要）:
 *   ?ap_api=pages        — 全ページ一覧
 *   ?ap_api=page         — 単一ページ取得
 *   ?ap_api=settings     — 公開設定取得
 *   ?ap_api=search       — 全文検索
 *   ?ap_api=contact      — お問い合わせフォーム送信
 *   ?ap_api=collections  — コレクション定義一覧
 *   ?ap_api=collection   — 特定コレクションの全アイテム
 *   ?ap_api=item         — コレクション内の単一アイテム取得
 *
 * 管理エンドポイント（API キーまたはセッション認証）:
 *   ?ap_api=item_upsert  — アイテム作成・更新 (POST)
 *   ?ap_api=item_delete  — アイテム削除 (POST)
 *   ?ap_api=page_upsert  — ページ作成・更新 (POST)
 *   ?ap_api=page_delete  — ページ削除 (POST)
 *
 * Webhook:
 *   ?ap_api=webhook       — GitHub Webhook 受信 (POST)
 *
 * メディア管理（API キー認証）:
 *   ?ap_api=media_list    — メディアファイル一覧 (GET)
 *   ?ap_api=media_upload  — メディアアップロード (POST)
 *   ?ap_api=media_delete  — メディア削除 (POST)
 *
 * プレビュー（認証必須）:
 *   ?ap_api=preview       — 下書きアイテムプレビュー (GET)
 *
 * インポート/エクスポート（認証必須）:
 *   ?ap_api=export        — コレクションエクスポート (GET)
 *   ?ap_api=import        — コレクションインポート (POST)
 *
 * キャッシュ管理（認証必須）:
 *   ?ap_api=cache_clear   — キャッシュクリア (POST)
 *   ?ap_api=cache_stats   — キャッシュ統計 (GET)
 *
 * Webhook 管理（認証必須）:
 *   ?ap_api=webhooks      — Webhook 一覧 (GET)
 */
class ApiEngine {

	private const SLUG_PATTERN   = '/^[a-zA-Z0-9_\-]+$/';
	private const PREVIEW_LENGTH = 120;
	private const SEARCH_MAX_LEN = 100;
	private const NAME_MAX_LEN   = 100;
	private const MSG_MAX_LEN    = 5000;
	private const API_KEYS_FILE  = 'api_keys.json';

	/* レート制限 */
	private const CONTACT_MAX_ATTEMPTS = 5;
	private const CONTACT_LOCKOUT_SEC  = 900; /* 15分 */
	private const API_RATE_MAX         = 60;  /* 公開API: 60リクエスト/分 */
	private const API_RATE_WINDOW      = 60;  /* 秒 */
	private static bool $authenticatedViaApiKey = false;

	/* ══════════════════════════════════════════════
	   エントリーポイント
	   ══════════════════════════════════════════════ */

	/**
	 * ?ap_api= パラメータがあれば処理して exit。
	 * なければ何もせず return。
	 */
	public static function handle(): void {
		$action = $_GET['ap_api'] ?? null;
		if ($action === null) return;

		/* CORS ヘッダー（ヘッドレス CMS: 外部フロントエンドからの API 呼び出し対応） */
		$allowedOrigin = self::getCorsOrigin();
		header('Access-Control-Allow-Origin: ' . $allowedOrigin);
		/* R16 fix: オリジン別レスポンスのキャッシュ分離（CORS キャッシュポイズニング防止） */
		if ($allowedOrigin !== '*') {
			header('Vary: Origin');
		}
		header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
		header('Access-Control-Allow-Headers: Content-Type, Authorization');
		header('Access-Control-Max-Age: 86400');

		/* OPTIONS プリフライトリクエスト */
		if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
			http_response_code(204);
			exit;
		}

		header('Content-Type: application/json; charset=UTF-8');

		/* 公開エンドポイントにレート制限を適用 */
		$publicActions = ['pages', 'page', 'settings', 'search', 'collections', 'collection', 'item'];
		if (in_array($action, $publicActions, true)) {
			self::checkApiRate();
			/* API キャッシュチェック（公開エンドポイントのみ） */
			if (class_exists('CacheEngine') && $action !== 'search') {
				CacheEngine::serve($action, $_GET);
			}
		}

		/* 公開エンドポイント（認証不要） */
		match ($action) {
			'pages'        => self::handlePages(),
			'page'         => self::handleGetPage(),
			'settings'     => self::jsonResponse(true, self::getSettings()),
			'search'       => self::handleSearch(),
			'contact'      => self::handleContact(),
			'collections'  => self::handleCollections(),
			'collection'   => self::handleCollection(),
			'item'         => self::handleItem(),
			'webhook'      => self::handleWebhook(),
			/* 管理エンドポイント（要認証） */
			'item_upsert'  => self::requireAuth('handleItemUpsert'),
			'item_delete'  => self::requireAuth('handleItemDelete'),
			'page_upsert'  => self::requireAuth('handlePageUpsert'),
			'page_delete'  => self::requireAuth('handlePageDelete'),
			'api_keys'     => self::requireAuth('handleApiKeys'),
			'media_list'   => self::requireAuth('handleMediaList'),
			'media_upload' => self::requireAuth('handleMediaUpload'),
			'media_delete' => self::requireAuth('handleMediaDelete'),
			'preview'      => self::requireAuth('handlePreview'),
			'export'       => self::requireAuth('handleExport'),
			'import'       => self::requireAuth('handleImport'),
			'cache_clear'  => self::requireAuth('handleCacheClear'),
			'cache_stats'  => self::requireAuth('handleCacheStats'),
			'webhooks'     => self::requireAuth('handleWebhooks'),
			default        => self::jsonError('不明な API エンドポイントです', 400),
		};
	}

	/* ══════════════════════════════════════════════
	   API キー認証
	   ══════════════════════════════════════════════ */

	/**
	 * API キーまたはセッション認証を要求。
	 * 認証成功時に指定メソッドを実行。
	 */
	private static function requireAuth(string $method): void {
		if (!self::isAuthenticated()) {
			self::jsonError('認証が必要です。Authorization ヘッダーに Bearer <API_KEY> を設定するか、セッションでログインしてください。', 401);
		}
		/* C17 fix: セッション認証時は CSRF 検証を要求（APIキー認証時は不要） */
		if (class_exists('AdminEngine') && AdminEngine::isLoggedIn() && !self::$authenticatedViaApiKey) {
			AdminEngine::verifyCsrf();
		}
		self::$method();
	}

	/** API キーまたはセッションで認証済みか判定 */
	private static function isAuthenticated(): bool {
		/* セッション認証（ダッシュボードからの呼び出し） */
		if (class_exists('AdminEngine') && AdminEngine::isLoggedIn()) {
			return true;
		}

		/* Bearer トークン認証（外部フロントエンドからの呼び出し） */
		$authHeader = $_SERVER['HTTP_AUTHORIZATION']
			?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
			?? '';

		if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $m)) {
			$valid = self::validateApiKey($m[1]);
			if ($valid) self::$authenticatedViaApiKey = true;
			return $valid;
		}

		return false;
	}

	/** API キーを検証 */
	private static function validateApiKey(string $key): bool {
		$keys = json_read(self::API_KEYS_FILE, settings_dir());
		foreach ($keys as $entry) {
			if (!is_array($entry)) continue;
			if (!isset($entry['key_hash'])) continue;
			if (!($entry['active'] ?? true)) continue;
			if (password_verify($key, $entry['key_hash'])) {
				return true;
			}
		}
		return false;
	}

	/**
	 * API キーを生成して保存。
	 * @return string 生成された平文キー（一度しか表示されない）
	 */
	public static function generateApiKey(string $label = ''): string {
		$key = 'ap_' . bin2hex(random_bytes(24)); /* 48文字のランダムキー */
		$keys = json_read(self::API_KEYS_FILE, settings_dir());

		$keys[] = [
			'label'      => $label ?: 'API Key ' . (count($keys) + 1),
			'key_hash'   => password_hash($key, PASSWORD_BCRYPT),
			'key_prefix' => substr($key, 0, 7) . '...',
			'created_at' => date('c'),
			'active'     => true,
		];

		json_write(self::API_KEYS_FILE, $keys, settings_dir());
		return $key;
	}

	/** API キー一覧を取得（ハッシュは除外） */
	public static function listApiKeys(): array {
		$keys = json_read(self::API_KEYS_FILE, settings_dir());
		$result = [];
		foreach ($keys as $i => $entry) {
			if (!is_array($entry)) continue;
			$result[] = [
				'index'      => $i,
				'label'      => $entry['label'] ?? '',
				'key_prefix' => $entry['key_prefix'] ?? '',
				'created_at' => $entry['created_at'] ?? '',
				'active'     => $entry['active'] ?? true,
			];
		}
		return $result;
	}

	/** API キーを削除 */
	public static function deleteApiKey(int $index): bool {
		$keys = json_read(self::API_KEYS_FILE, settings_dir());
		if (!isset($keys[$index])) return false;
		array_splice($keys, $index, 1);
		json_write(self::API_KEYS_FILE, $keys, settings_dir());
		return true;
	}

	/* ══════════════════════════════════════════════
	   公開エンドポイント: pages
	   ══════════════════════════════════════════════ */

	private static function handlePages(): void {
		$pages = json_read('pages.json', content_dir());
		$list  = [];
		foreach ($pages as $slug => $content) {
			$list[] = [
				'slug'    => $slug,
				'preview' => self::makePreview((string)$content),
			];
		}
		$p = self::getPagination();
		self::paginatedResponse($list, $p['limit'], $p['offset']);
	}

	/* ══════════════════════════════════════════════
	   公開エンドポイント: page
	   ══════════════════════════════════════════════ */

	private static function handleGetPage(): void {
		$slug = $_GET['slug'] ?? '';
		if (!preg_match(self::SLUG_PATTERN, $slug)) {
			self::jsonError('不正な slug パラメータです', 400);
		}
		$pages = json_read('pages.json', content_dir());
		if (!isset($pages[$slug])) {
			self::jsonError('ページが見つかりません', 404);
		}
		self::jsonResponse(true, [
			'slug'    => $slug,
			'content' => $pages[$slug],
		]);
	}

	/* ══════════════════════════════════════════════
	   公開エンドポイント: settings
	   ══════════════════════════════════════════════ */

	private static function getSettings(): array {
		$settings = json_read('settings.json', settings_dir());
		/* 公開情報のみ返す（auth, contact_email, themeSelect 等は含めない） */
		return [
			'title'       => $settings['title'] ?? '',
			'description' => $settings['description'] ?? '',
			'keywords'    => $settings['keywords'] ?? '',
		];
	}

	/* ══════════════════════════════════════════════
	   公開エンドポイント: search
	   ══════════════════════════════════════════════ */

	private static function handleSearch(): void {
		$q = trim($_GET['q'] ?? '');
		if ($q === '' || mb_strlen($q, 'UTF-8') > self::SEARCH_MAX_LEN) {
			self::jsonError('検索クエリを入力してください（' . self::SEARCH_MAX_LEN . '文字以内）', 400);
		}

		$results = [];
		$query   = mb_strtolower($q, 'UTF-8');

		/* レガシーページ検索 */
		$pages = json_read('pages.json', content_dir());
		foreach ($pages as $slug => $content) {
			$text = mb_strtolower(strip_tags((string)$content), 'UTF-8');
			$pos  = mb_strpos($text, $query, 0, 'UTF-8');
			if ($pos === false) continue;

			$start   = max(0, $pos - 30);
			$preview = mb_substr($text, $start, 100, 'UTF-8');
			if ($start > 0) $preview = '...' . $preview;
			if ($start + 100 < mb_strlen($text, 'UTF-8')) $preview .= '...';

			$results[] = ['slug' => $slug, 'type' => 'page', 'preview' => $preview];
		}

		/* コレクション検索 */
		if (class_exists('CollectionEngine') && CollectionEngine::isEnabled()) {
			$collections = CollectionEngine::listCollections();
			foreach ($collections as $col) {
				$colName = $col['name'];
				$items = CollectionEngine::getItems($colName);
				foreach ($items as $itemSlug => $item) {
					$title = mb_strtolower($item['meta']['title'] ?? '', 'UTF-8');
					$text  = mb_strtolower(strip_tags($item['html']), 'UTF-8');

					$pos = mb_strpos($title, $query, 0, 'UTF-8');
					if ($pos === false) {
						$pos = mb_strpos($text, $query, 0, 'UTF-8');
					}
					if ($pos === false) continue;

					$start   = max(0, $pos - 30);
					$preview = mb_substr($text, $start, 100, 'UTF-8');
					if ($start > 0) $preview = '...' . $preview;
					if ($start + 100 < mb_strlen($text, 'UTF-8')) $preview .= '...';

					$results[] = [
						'slug'       => $colName . '/' . $itemSlug,
						'type'       => 'collection',
						'collection' => $colName,
						'title'      => $item['meta']['title'] ?? $itemSlug,
						'preview'    => $preview,
					];
				}
			}
		}

		$p = self::getPagination();
		$total = count($results);
		$paged = array_slice($results, $p['offset'], $p['limit']);

		echo json_encode([
			'ok'   => true,
			'data' => [
				'query'   => $q,
				'results' => array_values($paged),
			],
			'meta' => [
				'total'  => $total,
				'limit'  => $p['limit'],
				'offset' => $p['offset'],
			],
		], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
		exit;
	}

	/* ══════════════════════════════════════════════
	   公開エンドポイント: contact
	   ══════════════════════════════════════════════ */

	private static function handleContact(): void {
		if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
			self::jsonError('POST メソッドが必要です', 405);
		}

		/* ハニーポット検出（ボットには成功を装う） */
		if (!empty($_POST['website'])) {
			self::jsonResponse(true, ['message' => '送信しました。']);
		}

		/* バリデーション */
		$name    = trim($_POST['name'] ?? '');
		$email   = trim($_POST['email'] ?? '');
		$message = trim($_POST['message'] ?? '');

		if ($name === '' || mb_strlen($name, 'UTF-8') > self::NAME_MAX_LEN) {
			self::jsonError('名前を入力してください（' . self::NAME_MAX_LEN . '文字以内）');
		}
		if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			self::jsonError('有効なメールアドレスを入力してください');
		}
		if ($message === '' || mb_strlen($message, 'UTF-8') > self::MSG_MAX_LEN) {
			self::jsonError('メッセージを入力してください（' . self::MSG_MAX_LEN . '文字以内）');
		}

		/* レート制限 */
		self::checkContactRate();

		/* メール送信 */
		$settings = json_read('settings.json', settings_dir());
		$to       = $settings['contact_email'] ?? '';
		if ($to === '') {
			self::jsonError('送信先が設定されていません', 500);
		}

		/* メールヘッダインジェクション対策 */
		$safeName  = str_replace(["\r", "\n"], '', $name);
		$safeEmail = str_replace(["\r", "\n"], '', $email);

		/* R17 fix: サブジェクトのヘッダインジェクション対策 */
		$safeTitle = str_replace(["\r", "\n"], '', $settings['title'] ?? 'AP');
		$subject = '【' . $safeTitle . '】お問い合わせ: ' . $safeName;
		$body    = "名前: {$safeName}\nメール: {$safeEmail}\n\n{$message}";
		$headers = "From: {$safeEmail}\r\nReply-To: {$safeEmail}\r\nContent-Type: text/plain; charset=UTF-8";

		if (!@mail($to, $subject, $body, $headers)) {
			self::jsonError('メール送信に失敗しました', 500);
		}

		/* アクティビティログに記録 */
		if (class_exists('AdminEngine') && method_exists('AdminEngine', 'logActivity')) {
			AdminEngine::logActivity('お問い合わせ受信: ' . $safeName . ' <' . $safeEmail . '>');
		}

		/* GitHub Issue として保存（Git 連携 + Issue 有効時） */
		if (class_exists('GitEngine') && GitEngine::isEnabled()) {
			$gitCfg = GitEngine::loadConfig();
			if (!empty($gitCfg['issues_enabled'])) {
				GitEngine::createIssue(
					'お問い合わせ: ' . $safeName,
					"**名前**: {$safeName}\n**メール**: {$safeEmail}\n\n{$message}",
					['contact']
				);
			}
		}

		self::jsonResponse(true, ['message' => '送信しました。']);
	}

	/* ══════════════════════════════════════════════
	   レート制限（contact 用）
	   ══════════════════════════════════════════════ */

	private static function checkContactRate(): void {
		$ip  = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
		$key = 'contact_' . $ip;

		$data     = json_read('login_attempts.json', settings_dir());
		$attempts = $data[$key] ?? ['count' => 0, 'locked_until' => 0];

		if (time() < (int)$attempts['locked_until']) {
			http_response_code(429);
			echo json_encode([
				'ok'    => false,
				'error' => '送信回数が上限を超えました。しばらくしてから再度お試しください。',
			], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
			exit;
		}

		$attempts['count']++;
		if ($attempts['count'] >= self::CONTACT_MAX_ATTEMPTS) {
			$attempts['locked_until'] = time() + self::CONTACT_LOCKOUT_SEC;
			$attempts['count']        = 0;
		}

		$data[$key] = $attempts;
		json_write('login_attempts.json', $data, settings_dir());
	}

	/* ══════════════════════════════════════════════
	   公開エンドポイント: collections
	   ══════════════════════════════════════════════ */

	private static function handleCollections(): void {
		if (!class_exists('CollectionEngine') || !CollectionEngine::isEnabled()) {
			self::jsonResponse(true, []);
		}
		self::jsonResponse(true, CollectionEngine::listCollections());
	}

	/* ══════════════════════════════════════════════
	   公開エンドポイント: collection
	   ══════════════════════════════════════════════ */

	private static function handleCollection(): void {
		$name = $_GET['name'] ?? '';
		if (!preg_match(self::SLUG_PATTERN, $name)) {
			self::jsonError('不正なコレクション名です', 400);
		}
		if (!class_exists('CollectionEngine')) {
			self::jsonError('コレクション機能が無効です', 400);
		}
		$items = CollectionEngine::getItems($name);
		$list = [];
		foreach ($items as $slug => $item) {
			$list[] = [
				'slug'    => $slug,
				'meta'    => $item['meta'],
				'preview' => self::makePreview($item['html']),
			];
		}
		$p = self::getPagination();
		self::paginatedResponse($list, $p['limit'], $p['offset']);
	}

	/* ══════════════════════════════════════════════
	   公開エンドポイント: item
	   ══════════════════════════════════════════════ */

	private static function handleItem(): void {
		$collection = $_GET['collection'] ?? '';
		$slug = $_GET['slug'] ?? '';
		if (!preg_match(self::SLUG_PATTERN, $collection) || !preg_match(self::SLUG_PATTERN, $slug)) {
			self::jsonError('不正なパラメータです', 400);
		}
		if (!class_exists('CollectionEngine')) {
			self::jsonError('コレクション機能が無効です', 400);
		}
		$item = CollectionEngine::getItem($collection, $slug);
		if ($item === null) {
			self::jsonError('アイテムが見つかりません', 404);
		}
		self::jsonResponse(true, [
			'collection' => $collection,
			'slug'       => $slug,
			'meta'       => $item['meta'],
			'content'    => $item['html'],
			'markdown'   => $item['body'],
		]);
	}

	/* ══════════════════════════════════════════════
	   管理エンドポイント: item_upsert（要認証）
	   ══════════════════════════════════════════════ */

	private static function handleItemUpsert(): void {
		if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
			self::jsonError('POST メソッドが必要です', 405);
		}
		if (!class_exists('CollectionEngine')) {
			self::jsonError('コレクション機能が無効です', 400);
		}

		$input = self::getJsonInput();
		$collection = $input['collection'] ?? '';
		$slug = $input['slug'] ?? '';
		$title = $input['title'] ?? $slug;
		$body = $input['body'] ?? '';
		$meta = $input['meta'] ?? [];

		if (!preg_match(self::SLUG_PATTERN, $collection) || !preg_match(self::SLUG_PATTERN, $slug)) {
			self::jsonError('collection と slug は必須です（英数字・ハイフン・アンダースコアのみ）');
		}

		if (!is_array($meta)) $meta = [];
		$meta['title'] = $title;

		if (!CollectionEngine::saveItem($collection, $slug, $meta, $body)) {
			self::jsonError('アイテムの保存に失敗しました');
		}

		if (class_exists('AdminEngine') && method_exists('AdminEngine', 'logActivity')) {
			AdminEngine::logActivity("API: アイテム保存 {$collection}/{$slug}");
		}
		if (class_exists('WebhookEngine')) {
			WebhookEngine::dispatch('item.updated', ['collection' => $collection, 'slug' => $slug]);
		}
		if (class_exists('CacheEngine')) CacheEngine::invalidateContent();

		self::jsonResponse(true, [
			'collection' => $collection,
			'slug'       => $slug,
			'message'    => '保存しました',
		]);
	}

	/* ══════════════════════════════════════════════
	   管理エンドポイント: item_delete（要認証）
	   ══════════════════════════════════════════════ */

	private static function handleItemDelete(): void {
		if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
			self::jsonError('POST メソッドが必要です', 405);
		}
		if (!class_exists('CollectionEngine')) {
			self::jsonError('コレクション機能が無効です', 400);
		}

		$input = self::getJsonInput();
		$collection = $input['collection'] ?? '';
		$slug = $input['slug'] ?? '';

		if (!preg_match(self::SLUG_PATTERN, $collection) || !preg_match(self::SLUG_PATTERN, $slug)) {
			self::jsonError('collection と slug は必須です');
		}

		if (!CollectionEngine::deleteItem($collection, $slug)) {
			self::jsonError('アイテムの削除に失敗しました', 404);
		}

		if (class_exists('AdminEngine') && method_exists('AdminEngine', 'logActivity')) {
			AdminEngine::logActivity("API: アイテム削除 {$collection}/{$slug}");
		}
		if (class_exists('WebhookEngine')) {
			WebhookEngine::dispatch('item.deleted', ['collection' => $collection, 'slug' => $slug]);
		}
		if (class_exists('CacheEngine')) CacheEngine::invalidateContent();

		self::jsonResponse(true, ['deleted' => "{$collection}/{$slug}"]);
	}

	/* ══════════════════════════════════════════════
	   管理エンドポイント: page_upsert（要認証）
	   ══════════════════════════════════════════════ */

	private static function handlePageUpsert(): void {
		if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
			self::jsonError('POST メソッドが必要です', 405);
		}

		$input = self::getJsonInput();
		$slug = $input['slug'] ?? '';
		$content = $input['content'] ?? '';

		if (!preg_match(self::SLUG_PATTERN, $slug)) {
			self::jsonError('不正な slug です（英数字・ハイフン・アンダースコアのみ）');
		}

		$pages = json_read('pages.json', content_dir());
		$pages[$slug] = $content;
		json_write('pages.json', $pages, content_dir());

		if (class_exists('AdminEngine') && method_exists('AdminEngine', 'logActivity')) {
			AdminEngine::logActivity("API: ページ保存 {$slug}");
		}
		if (class_exists('WebhookEngine')) {
			WebhookEngine::dispatch('page.updated', ['slug' => $slug]);
		}
		if (class_exists('CacheEngine')) CacheEngine::invalidateContent();

		self::jsonResponse(true, ['slug' => $slug, 'message' => '保存しました']);
	}

	/* ══════════════════════════════════════════════
	   管理エンドポイント: page_delete（要認証）
	   ══════════════════════════════════════════════ */

	private static function handlePageDelete(): void {
		if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
			self::jsonError('POST メソッドが必要です', 405);
		}

		$input = self::getJsonInput();
		$slug = $input['slug'] ?? '';

		if (!preg_match(self::SLUG_PATTERN, $slug)) {
			self::jsonError('不正な slug です');
		}

		$pages = json_read('pages.json', content_dir());
		if (!isset($pages[$slug])) {
			self::jsonError('ページが見つかりません', 404);
		}
		unset($pages[$slug]);
		json_write('pages.json', $pages, content_dir());

		if (class_exists('AdminEngine') && method_exists('AdminEngine', 'logActivity')) {
			AdminEngine::logActivity("API: ページ削除 {$slug}");
		}
		/* キャッシュ無効化 */
		if (class_exists('CacheEngine')) CacheEngine::invalidateContent();
		/* Webhook 通知 */
		if (class_exists('WebhookEngine')) {
			WebhookEngine::dispatch('page.deleted', ['slug' => $slug]);
		}

		self::jsonResponse(true, ['deleted' => $slug]);
	}

	/* ══════════════════════════════════════════════
	   管理エンドポイント: api_keys（要認証）
	   ══════════════════════════════════════════════ */

	private static function handleApiKeys(): void {
		$method = $_SERVER['REQUEST_METHOD'];

		if ($method === 'GET') {
			self::jsonResponse(true, self::listApiKeys());
		}

		if ($method !== 'POST') {
			self::jsonError('GET または POST メソッドが必要です', 405);
		}

		$input = self::getJsonInput();
		$subAction = $input['action'] ?? 'generate';

		if ($subAction === 'generate') {
			$label = $input['label'] ?? '';
			$key = self::generateApiKey($label);
			self::jsonResponse(true, [
				'key'     => $key,
				'message' => 'API キーを生成しました。このキーは一度しか表示されません。安全な場所に保存してください。',
			]);
		}

		if ($subAction === 'delete') {
			$index = (int)($input['index'] ?? -1);
			if (!self::deleteApiKey($index)) {
				self::jsonError('API キーの削除に失敗しました');
			}
			self::jsonResponse(true, ['message' => 'API キーを削除しました']);
		}

		self::jsonError('不明なアクションです');
	}

	/* ══════════════════════════════════════════════
	   Webhook: GitHub Push イベント受信
	   ══════════════════════════════════════════════ */

	private static function handleWebhook(): void {
		if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
			self::jsonError('POST メソッドが必要です', 405);
		}

		$payload = file_get_contents('php://input');
		if ($payload === false || $payload === '') {
			self::jsonError('ペイロードが空です', 400);
		}

		/* Webhook シークレット検証（シークレット未設定時は拒否） */
		$cfg = class_exists('GitEngine') ? GitEngine::loadConfig() : [];
		$secret = $cfg['webhook_secret'] ?? '';

		if ($secret === '') {
			self::jsonError('Webhook シークレットが設定されていません。ダッシュボードで設定してください。', 403);
		}

		$sigHeader = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
		if ($sigHeader === '') {
			self::jsonError('署名がありません', 403);
		}
		$expected = 'sha256=' . hash_hmac('sha256', $payload, $secret);
		if (!hash_equals($expected, $sigHeader)) {
			self::jsonError('署名が不正です', 403);
		}

		$data = json_decode($payload, true);
		if (!is_array($data)) {
			self::jsonError('無効な JSON ペイロードです', 400);
		}

		$event = $_SERVER['HTTP_X_GITHUB_EVENT'] ?? '';

		/* Push イベント: 自動 Pull */
		if ($event === 'push') {
			$branch = $cfg['branch'] ?? 'main';
			$ref = $data['ref'] ?? '';
			if ($ref !== 'refs/heads/' . $branch) {
				self::jsonResponse(true, ['message' => '対象外のブランチです', 'skipped' => true]);
			}

			if (class_exists('GitEngine') && GitEngine::isEnabled()) {
				$result = GitEngine::pull();
				if (class_exists('AdminEngine') && method_exists('AdminEngine', 'logActivity')) {
					AdminEngine::logActivity('Webhook: Push 受信 → 自動 Pull 実行');
				}
				self::jsonResponse(true, $result);
			}
			self::jsonResponse(true, ['message' => 'Git 連携が無効です', 'skipped' => true]);
		}

		/* Ping イベント */
		if ($event === 'ping') {
			self::jsonResponse(true, ['message' => 'pong']);
		}

		self::jsonResponse(true, ['message' => '未対応のイベントです: ' . $event, 'skipped' => true]);
	}

	/* ══════════════════════════════════════════════
	   管理エンドポイント: メディア管理
	   ══════════════════════════════════════════════ */

	private static function handleMediaList(): void {
		$dir = 'uploads/';
		if (!is_dir($dir)) {
			self::jsonResponse(true, ['files' => [], 'total' => 0]);
		}
		$files = [];
		foreach (glob($dir . '*') ?: [] as $f) {
			if (is_dir($f)) continue;
			$base = basename($f);
			if ($base === '.htaccess') continue;
			$finfo = new finfo(FILEINFO_MIME_TYPE);
			$files[] = [
				'name'       => $base,
				'url'        => $dir . $base,
				'size'       => filesize($f),
				'mime'       => $finfo->file($f),
				'created_at' => date('c', filectime($f) ?: time()),
			];
		}
		/* 作成日降順ソート */
		usort($files, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));

		$p = self::getPagination();
		$total = count($files);
		$paged = array_slice($files, $p['offset'], $p['limit']);

		echo json_encode([
			'ok'   => true,
			'data' => ['files' => $paged],
			'meta' => ['total' => $total, 'limit' => $p['limit'], 'offset' => $p['offset']],
		], JSON_UNESCAPED_UNICODE);
		exit;
	}

	private static function handleMediaUpload(): void {
		if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
			self::jsonError('POST メソッドが必要です', 405);
		}
		if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
			self::jsonError('ファイルエラー', 400);
		}
		$file = $_FILES['file'];
		if ($file['size'] > 5 * 1024 * 1024) {
			self::jsonError('ファイルサイズが上限（5MB）を超えています', 400);
		}
		$finfo = new finfo(FILEINFO_MIME_TYPE);
		$mime  = $finfo->file($file['tmp_name']);
		/* SVG は JavaScript 実行が可能なため除外（XSS 防止） */
		$ext_map = [
			'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif',
			'image/webp' => 'webp',
			'application/pdf' => 'pdf',
		];
		if (!isset($ext_map[$mime])) {
			self::jsonError('許可されていないファイル形式です', 400);
		}
		$dir = 'uploads/';
		if (!is_dir($dir)) mkdir($dir, 0755, true);
		$filename = bin2hex(random_bytes(12)) . '.' . $ext_map[$mime];
		if (!move_uploaded_file($file['tmp_name'], $dir . $filename)) {
			self::jsonError('ファイル保存に失敗しました', 500);
		}

		/* 画像最適化（提案8） */
		if (class_exists('ImageOptimizer')) {
			ImageOptimizer::optimize($dir . $filename);
		}

		if (class_exists('AdminEngine') && method_exists('AdminEngine', 'logActivity')) {
			AdminEngine::logActivity("API: メディアアップロード {$filename}");
		}
		self::jsonResponse(true, [
			'name' => $filename,
			'url'  => $dir . $filename,
			'mime' => $mime,
			'size' => filesize($dir . $filename),
		]);
	}

	private static function handleMediaDelete(): void {
		if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
			self::jsonError('POST メソッドが必要です', 405);
		}
		$input = self::getJsonInput();
		$name = $input['name'] ?? '';
		if ($name === '' || str_contains($name, '/') || str_contains($name, '..')) {
			self::jsonError('不正なファイル名です', 400);
		}
		$path = 'uploads/' . $name;
		if (!file_exists($path)) {
			self::jsonError('ファイルが見つかりません', 404);
		}
		if (!unlink($path)) {
			self::jsonError('ファイルの削除に失敗しました', 500);
		}
		/* サムネイルも削除 */
		$thumbPath = 'uploads/thumb/' . $name;
		if (file_exists($thumbPath)) @unlink($thumbPath);

		if (class_exists('AdminEngine') && method_exists('AdminEngine', 'logActivity')) {
			AdminEngine::logActivity("API: メディア削除 {$name}");
		}
		self::jsonResponse(true, ['deleted' => $name]);
	}

	/* ══════════════════════════════════════════════
	   管理エンドポイント: プレビューモード
	   ══════════════════════════════════════════════ */

	private static function handlePreview(): void {
		$collection = $_GET['collection'] ?? '';
		$slug = $_GET['slug'] ?? '';
		if (!preg_match(self::SLUG_PATTERN, $collection) || !preg_match(self::SLUG_PATTERN, $slug)) {
			self::jsonError('collection と slug は必須です', 400);
		}
		if (!class_exists('CollectionEngine')) {
			self::jsonError('コレクション機能が無効です', 400);
		}

		/* ドラフト含む全アイテムから取得 */
		$item = CollectionEngine::getItem($collection, $slug);
		if ($item === null) {
			self::jsonError('アイテムが見つかりません', 404);
		}

		self::jsonResponse(true, [
			'collection' => $collection,
			'slug'       => $slug,
			'meta'       => $item['meta'],
			'content'    => $item['html'],
			'markdown'   => $item['body'],
			'status'     => $item['meta']['status'] ?? 'published',
			'preview'    => true,
		]);
	}

	/* ══════════════════════════════════════════════
	   管理エンドポイント: エクスポート
	   ══════════════════════════════════════════════ */

	private static function handleExport(): void {
		$collection = $_GET['collection'] ?? '';
		$format = $_GET['format'] ?? 'json';
		if (!preg_match(self::SLUG_PATTERN, $collection)) {
			self::jsonError('コレクション名を指定してください', 400);
		}
		if (!class_exists('CollectionEngine')) {
			self::jsonError('コレクション機能が無効です', 400);
		}

		$items = CollectionEngine::getAllItems($collection);
		if ($format === 'csv') {
			self::exportCsv($collection, $items);
		}
		/* JSON エクスポート */
		$exportData = [];
		foreach ($items as $slug => $item) {
			$exportData[] = [
				'slug'     => $slug,
				'meta'     => $item['meta'],
				'body'     => $item['body'],
			];
		}
		header('Content-Disposition: attachment; filename="' . $collection . '.json"');
		self::jsonResponse(true, [
			'collection' => $collection,
			'count'      => count($exportData),
			'items'      => $exportData,
		]);
	}

	private static function exportCsv(string $collection, array $items): never {
		header('Content-Type: text/csv; charset=UTF-8');
		header('Content-Disposition: attachment; filename="' . $collection . '.csv"');

		$output = fopen('php://output', 'w');
		if ($output === false) {
			self::jsonError('CSV 出力に失敗しました', 500);
		}
		/* BOM for Excel */
		fwrite($output, "\xEF\xBB\xBF");

		/* ヘッダー行を収集 */
		$allKeys = ['slug'];
		foreach ($items as $item) {
			foreach (array_keys($item['meta']) as $k) {
				if (!in_array($k, $allKeys, true)) $allKeys[] = $k;
			}
		}
		$allKeys[] = 'body';
		fputcsv($output, $allKeys);

		foreach ($items as $slug => $item) {
			$row = [$slug];
			foreach (array_slice($allKeys, 1, -1) as $key) {
				$val = $item['meta'][$key] ?? '';
				if (is_array($val)) $val = json_encode($val, JSON_UNESCAPED_UNICODE);
				if (is_bool($val)) $val = $val ? 'true' : 'false';
				$row[] = (string)$val;
			}
			$row[] = $item['body'];
			fputcsv($output, $row);
		}
		fclose($output);
		exit;
	}

	/* ══════════════════════════════════════════════
	   管理エンドポイント: インポート
	   ══════════════════════════════════════════════ */

	private static function handleImport(): void {
		if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
			self::jsonError('POST メソッドが必要です', 405);
		}
		if (!class_exists('CollectionEngine')) {
			self::jsonError('コレクション機能が無効です', 400);
		}

		$input = self::getJsonInput();
		$collection = $input['collection'] ?? '';
		$items = $input['items'] ?? [];

		if (!preg_match(self::SLUG_PATTERN, $collection)) {
			self::jsonError('コレクション名を指定してください', 400);
		}
		if (!is_array($items) || empty($items)) {
			self::jsonError('インポートするアイテムが空です', 400);
		}

		$def = CollectionEngine::getCollectionDef($collection);
		if ($def === null) {
			self::jsonError('コレクションが存在しません', 404);
		}

		$imported = 0;
		$errors = [];
		foreach ($items as $item) {
			$slug = $item['slug'] ?? '';
			$meta = $item['meta'] ?? [];
			$body = $item['body'] ?? '';
			if (!preg_match(self::SLUG_PATTERN, $slug)) {
				$errors[] = "不正なスラッグ: {$slug}";
				continue;
			}
			if (CollectionEngine::saveItem($collection, $slug, $meta, $body)) {
				$imported++;
			} else {
				$errors[] = "保存失敗: {$slug}";
			}
		}

		if (class_exists('AdminEngine') && method_exists('AdminEngine', 'logActivity')) {
			AdminEngine::logActivity("API: インポート {$collection} ({$imported}件)");
		}

		/* Webhook 発火 */
		if (class_exists('WebhookEngine') && $imported > 0) {
			WebhookEngine::dispatch('item.updated', ['collection' => $collection, 'imported' => $imported]);
		}
		/* キャッシュ無効化 */
		if (class_exists('CacheEngine')) CacheEngine::invalidateContent();

		self::jsonResponse(true, [
			'collection' => $collection,
			'imported'   => $imported,
			'errors'     => $errors,
		]);
	}

	/* ══════════════════════════════════════════════
	   管理エンドポイント: キャッシュ管理
	   ══════════════════════════════════════════════ */

	private static function handleCacheClear(): void {
		if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
			self::jsonError('POST メソッドが必要です', 405);
		}
		if (class_exists('CacheEngine')) {
			CacheEngine::clear();
		}
		self::jsonResponse(true, ['message' => 'キャッシュをクリアしました']);
	}

	private static function handleCacheStats(): void {
		if (!class_exists('CacheEngine')) {
			self::jsonResponse(true, ['files' => 0, 'size' => 0]);
		}
		self::jsonResponse(true, CacheEngine::getStats());
	}

	/* ══════════════════════════════════════════════
	   管理エンドポイント: Webhook 管理
	   ══════════════════════════════════════════════ */

	private static function handleWebhooks(): void {
		if (!class_exists('WebhookEngine')) {
			self::jsonResponse(true, []);
		}
		self::jsonResponse(true, WebhookEngine::listWebhooks());
	}

	/* ══════════════════════════════════════════════
	   ユーティリティ
	   ══════════════════════════════════════════════ */

	/** JSON リクエストボディまたは POST パラメータを取得 */
	private static function getJsonInput(): array {
		$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
		if (str_contains($contentType, 'application/json')) {
			$raw = file_get_contents('php://input');
			$data = json_decode($raw ?: '', true);
			return is_array($data) ? $data : [];
		}
		return $_POST;
	}

	private static function makePreview(string $html, int $length = self::PREVIEW_LENGTH): string {
		$text = strip_tags($html);
		$text = preg_replace('/\s+/', ' ', trim($text));
		if (mb_strlen($text, 'UTF-8') <= $length) return $text;
		return mb_substr($text, 0, $length, 'UTF-8') . '...';
	}

	private static function jsonResponse(bool $ok, mixed $data): never {
		echo json_encode(
			['ok' => $ok, 'data' => $data],
			JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
		);
		exit;
	}

	private static function jsonError(string $message, int $status = 400): never {
		http_response_code($status);
		echo json_encode(
			['ok' => false, 'error' => $message],
			JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
		);
		exit;
	}

	/**
	 * CORS 許可オリジンを取得。
	 * settings.json の cors_origins 設定があればホワイトリスト判定、
	 * なければ '*'（全許可）。
	 */
	private static function getCorsOrigin(): string {
		$settings = json_read('settings.json', settings_dir());
		$allowed = $settings['cors_origins'] ?? [];
		if (empty($allowed) || !is_array($allowed)) {
			return '*';
		}
		$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
		if ($origin !== '' && in_array($origin, $allowed, true)) {
			return $origin;
		}
		return $allowed[0];
	}

	/**
	 * 公開 API レート制限チェック。
	 * IP あたり API_RATE_MAX リクエスト/API_RATE_WINDOW 秒。
	 */
	private static function checkApiRate(): void {
		$ip   = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
		$key  = 'api_rate_' . $ip;
		$data = json_read('login_attempts.json', settings_dir());

		$entry = $data[$key] ?? ['count' => 0, 'window_start' => 0];
		$now   = time();

		/* ウィンドウリセット */
		if ($now - (int)$entry['window_start'] > self::API_RATE_WINDOW) {
			$entry = ['count' => 0, 'window_start' => $now];
		}

		$entry['count']++;
		$data[$key] = $entry;
		json_write('login_attempts.json', $data, settings_dir());

		if ($entry['count'] > self::API_RATE_MAX) {
			http_response_code(429);
			echo json_encode([
				'ok'    => false,
				'error' => 'リクエスト制限を超えました。しばらくしてから再度お試しください。',
			], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
			exit;
		}
	}

	/**
	 * ページネーションパラメータを取得。
	 * @return array{limit: int, offset: int}
	 */
	private static function getPagination(int $defaultLimit = 50, int $maxLimit = 100): array {
		$limit  = min(max((int)($_GET['limit'] ?? $defaultLimit), 1), $maxLimit);
		$offset = max((int)($_GET['offset'] ?? 0), 0);
		return ['limit' => $limit, 'offset' => $offset];
	}

	/**
	 * ページネーション付きレスポンス。
	 */
	private static function paginatedResponse(array $allItems, int $limit, int $offset): never {
		$total = count($allItems);
		$items = array_slice($allItems, $offset, $limit);
		echo json_encode([
			'ok'   => true,
			'data' => array_values($items),
			'meta' => [
				'total'  => $total,
				'limit'  => $limit,
				'offset' => $offset,
			],
		], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
		exit;
	}
}
