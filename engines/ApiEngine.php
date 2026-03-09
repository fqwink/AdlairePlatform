<?php
/**
 * ApiEngine - ヘッドレスCMS / 公開 REST API エンジン
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
 */
class ApiEngine {

	private const SLUG_PATTERN   = '/^[a-zA-Z0-9_\-]+$/';
	private const PREVIEW_LENGTH = 120;
	private const SEARCH_MAX_LEN = 100;
	private const NAME_MAX_LEN   = 100;
	private const MSG_MAX_LEN    = 5000;

	/* レート制限: contact エンドポイント */
	private const CONTACT_MAX_ATTEMPTS = 5;
	private const CONTACT_LOCKOUT_SEC  = 900; /* 15分 */

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

		header('Content-Type: application/json; charset=UTF-8');

		match ($action) {
			'pages'       => self::jsonResponse(true, self::getPages()),
			'page'        => self::handleGetPage(),
			'settings'    => self::jsonResponse(true, self::getSettings()),
			'search'      => self::handleSearch(),
			'contact'     => self::handleContact(),
			'collections' => self::handleCollections(),
			'collection'  => self::handleCollection(),
			'item'        => self::handleItem(),
			default       => self::jsonError('不明な API エンドポイントです', 400),
		};
	}

	/* ══════════════════════════════════════════════
	   公開エンドポイント: pages
	   ══════════════════════════════════════════════ */

	private static function getPages(): array {
		$pages = json_read('pages.json', content_dir());
		$list  = [];
		foreach ($pages as $slug => $content) {
			$list[] = [
				'slug'    => $slug,
				'preview' => self::makePreview((string)$content),
			];
		}
		return $list;
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

		$pages   = json_read('pages.json', content_dir());
		$results = [];
		$query   = mb_strtolower($q, 'UTF-8');

		foreach ($pages as $slug => $content) {
			$text = mb_strtolower(strip_tags((string)$content), 'UTF-8');
			$pos  = mb_strpos($text, $query, 0, 'UTF-8');
			if ($pos === false) continue;

			/* マッチ箇所の前後を含むプレビューを生成 */
			$start   = max(0, $pos - 30);
			$preview = mb_substr($text, $start, 100, 'UTF-8');
			if ($start > 0) $preview = '...' . $preview;
			if ($start + 100 < mb_strlen($text, 'UTF-8')) $preview .= '...';

			$results[] = ['slug' => $slug, 'preview' => $preview];
		}

		self::jsonResponse(true, [
			'query'   => $q,
			'results' => $results,
		]);
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

		$subject = '【' . ($settings['title'] ?? 'AP') . '】お問い合わせ: ' . $safeName;
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
		self::jsonResponse(true, ['collection' => $name, 'items' => $list]);
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
	   ユーティリティ
	   ══════════════════════════════════════════════ */

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
}
