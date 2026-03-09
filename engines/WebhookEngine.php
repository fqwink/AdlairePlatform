<?php
/**
 * WebhookEngine - Outgoing Webhook 配信エンジン
 *
 * コンテンツ変更時に外部サービスへ HTTP POST 通知を送信。
 * Vercel / Netlify デプロイフック等の Jamstack 連携に使用。
 *
 * イベント:
 *   item.created, item.updated, item.deleted,
 *   page.updated, build.completed
 *
 * 署名: HMAC-SHA256（X-AP-Signature ヘッダー）
 */
class WebhookEngine {

	private const CONFIG_FILE = 'webhooks.json';
	private const SLUG_PATTERN = '/^[a-zA-Z0-9_\-]+$/';

	/**
	 * Webhook 設定を読み込み
	 * @return array{webhooks: array}
	 */
	public static function loadConfig(): array {
		$config = json_read(self::CONFIG_FILE, settings_dir());
		if (empty($config['webhooks'])) {
			$config['webhooks'] = [];
		}
		return $config;
	}

	/** 設定を保存 */
	public static function saveConfig(array $config): void {
		json_write(self::CONFIG_FILE, $config, settings_dir());
	}

	/**
	 * Webhook エンドポイントを追加
	 */
	public static function addWebhook(string $url, string $label = '', array $events = [], string $secret = ''): bool {
		if (filter_var($url, FILTER_VALIDATE_URL) === false) return false;
		/* SSRF 防止: http/https のみ許可、プライベート IP をブロック */
		$scheme = strtolower(parse_url($url, PHP_URL_SCHEME) ?? '');
		if (!in_array($scheme, ['http', 'https'], true)) return false;
		$host = parse_url($url, PHP_URL_HOST);
		if ($host !== null && self::isPrivateHost($host)) return false;

		$config = self::loadConfig();
		$config['webhooks'][] = [
			'url'        => $url,
			'label'      => $label ?: parse_url($url, PHP_URL_HOST),
			'events'     => $events ?: ['item.created', 'item.updated', 'item.deleted', 'page.updated', 'build.completed'],
			'secret'     => $secret,
			'active'     => true,
			'created_at' => date('c'),
		];
		self::saveConfig($config);
		return true;
	}

	/** Webhook を削除 */
	public static function deleteWebhook(int $index): bool {
		$config = self::loadConfig();
		if (!isset($config['webhooks'][$index])) return false;
		array_splice($config['webhooks'], $index, 1);
		self::saveConfig($config);
		return true;
	}

	/** Webhook の有効/無効を切り替え */
	public static function toggleWebhook(int $index): bool {
		$config = self::loadConfig();
		if (!isset($config['webhooks'][$index])) return false;
		$config['webhooks'][$index]['active'] = !($config['webhooks'][$index]['active'] ?? true);
		self::saveConfig($config);
		return true;
	}

	/** Webhook 一覧を取得（シークレットは伏字） */
	public static function listWebhooks(): array {
		$config = self::loadConfig();
		$result = [];
		foreach ($config['webhooks'] as $i => $wh) {
			$result[] = [
				'index'      => $i,
				'url'        => $wh['url'] ?? '',
				'label'      => $wh['label'] ?? '',
				'events'     => $wh['events'] ?? [],
				'has_secret' => !empty($wh['secret']),
				'active'     => $wh['active'] ?? true,
				'created_at' => $wh['created_at'] ?? '',
			];
		}
		return $result;
	}

	/**
	 * イベントを発火し、該当する Webhook に非同期通知を送信。
	 *
	 * @param string $event   イベント名
	 * @param array  $payload ペイロードデータ
	 */
	public static function dispatch(string $event, array $payload = []): void {
		$config = self::loadConfig();
		if (empty($config['webhooks'])) return;

		$body = json_encode([
			'event'     => $event,
			'timestamp' => date('c'),
			'data'      => $payload,
		], JSON_UNESCAPED_UNICODE);

		foreach ($config['webhooks'] as $wh) {
			if (!($wh['active'] ?? true)) continue;
			$events = $wh['events'] ?? [];
			if (!empty($events) && !in_array($event, $events, true)) continue;

			$url = $wh['url'] ?? '';
			if ($url === '') continue;

			$headers = [
				'Content-Type: application/json',
				'User-Agent: AdlairePlatform/' . (defined('AP_VERSION') ? AP_VERSION : '1.0'),
				'X-AP-Event: ' . $event,
			];

			/* HMAC-SHA256 署名 */
			$secret = $wh['secret'] ?? '';
			if ($secret !== '') {
				$sig = hash_hmac('sha256', $body, $secret);
				$headers[] = 'X-AP-Signature: sha256=' . $sig;
			}

			self::sendAsync($url, $body, $headers);
		}
	}

	/**
	 * 非同期 cURL で POST 送信（レスポンス待ちなし、タイムアウト5秒）
	 */
	private static function sendAsync(string $url, string $body, array $headers): void {
		/* R19 fix: DNS リバインディング防止 — 送信時にもホストの IP を再検証 */
		$host = parse_url($url, PHP_URL_HOST);
		if ($host !== null && self::isPrivateHost($host)) {
			error_log('AdlairePlatform: Webhook SSRF blocked (DNS rebinding): ' . $url);
			if (class_exists('DiagnosticEngine')) DiagnosticEngine::log('security', 'SSRF ブロック (DNS rebinding)', ['url_host' => parse_url($url, PHP_URL_HOST) ?? '']);
			return;
		}

		$ch = curl_init($url);
		if ($ch === false) return;

		curl_setopt_array($ch, [
			CURLOPT_POST           => true,
			CURLOPT_POSTFIELDS     => $body,
			CURLOPT_HTTPHEADER     => $headers,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT        => 5,
			CURLOPT_CONNECTTIMEOUT => 3,
			/* SSRF 防止: リダイレクトを無効化 */
			CURLOPT_FOLLOWLOCATION => false,
		]);

		$result = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		/* 配信ログ（デバッグ用） */
		if ($httpCode < 200 || $httpCode >= 300) {
			error_log("WebhookEngine: delivery failed to {$url} (HTTP {$httpCode})");
			if (class_exists('DiagnosticEngine')) DiagnosticEngine::log('engine', 'Webhook 配信失敗', ['http_code' => $httpCode]);
		}
	}

	/* ══════════════════════════════════════════════
	   POST アクションハンドラ
	   ══════════════════════════════════════════════ */

	public static function handle(): void {
		$action = $_POST['ap_action'] ?? '';
		$valid = ['webhook_add', 'webhook_delete', 'webhook_toggle', 'webhook_test'];
		if (!in_array($action, $valid, true)) return;

		if (!AdminEngine::isLoggedIn()) {
			http_response_code(401);
			header('Content-Type: application/json; charset=UTF-8');
			echo json_encode(['error' => '未ログイン']);
			exit;
		}
		AdminEngine::verifyCsrf();
		header('Content-Type: application/json; charset=UTF-8');

		match ($action) {
			'webhook_add'    => self::handleAdd(),
			'webhook_delete' => self::handleDelete(),
			'webhook_toggle' => self::handleToggle(),
			'webhook_test'   => self::handleTest(),
		};
	}

	private static function handleAdd(): never {
		$url    = trim($_POST['url'] ?? '');
		$label  = trim($_POST['label'] ?? '');
		$events = !empty($_POST['events']) ? json_decode($_POST['events'], true) : [];
		$secret = trim($_POST['secret'] ?? '');

		if (!self::addWebhook($url, $label, is_array($events) ? $events : [], $secret)) {
			self::jsonError('不正な URL です');
		}
		AdminEngine::logActivity('Webhook 追加: ' . $url);
		self::jsonOk(self::listWebhooks());
	}

	private static function handleDelete(): never {
		$index = (int)($_POST['index'] ?? -1);
		if (!self::deleteWebhook($index)) {
			self::jsonError('Webhook の削除に失敗しました');
		}
		AdminEngine::logActivity('Webhook 削除');
		self::jsonOk(self::listWebhooks());
	}

	private static function handleToggle(): never {
		$index = (int)($_POST['index'] ?? -1);
		if (!self::toggleWebhook($index)) {
			self::jsonError('切り替えに失敗しました');
		}
		self::jsonOk(self::listWebhooks());
	}

	private static function handleTest(): never {
		$index = (int)($_POST['index'] ?? -1);
		$config = self::loadConfig();
		if (!isset($config['webhooks'][$index])) {
			self::jsonError('Webhook が見つかりません');
		}
		self::dispatch('webhook.test', ['message' => 'テスト配信', 'index' => $index]);
		self::jsonOk(['message' => 'テスト送信しました']);
	}

	private static function jsonOk(mixed $data): never {
		echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
		exit;
	}

	private static function jsonError(string $msg, int $status = 400): never {
		http_response_code($status);
		echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
		exit;
	}

	/**
	 * ホストがプライベート/内部 IP かどうかチェック（SSRF 防止）
	 */
	private static function isPrivateHost(string $host): bool {
		/* localhost 系 */
		if (in_array(strtolower($host), ['localhost', '127.0.0.1', '::1', '0.0.0.0'], true)) {
			return true;
		}
		/* DNS 解決して IP を確認 */
		$ip = gethostbyname($host);
		/* R20 fix: DNS 解決失敗時はブロック（安全側に倒す） */
		if ($ip === $host) return true;
		return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
	}
}
