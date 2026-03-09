<?php
/**
 * DiagnosticEngine - リアルタイム診断・テレメトリエンジン
 *
 * 本番サーバ環境でシステムエラー・ログデータ・環境情報を集約し、
 * 開発元へ定期送信する。デフォルト有効・エンドユーザーで無効化可能。
 *
 * 収集レベル:
 *   basic    — 環境情報・エラー件数のみ（デフォルト）
 *   extended — エラーメッセージ・パフォーマンス情報
 *   debug    — スタックトレース・エンジン別実行時間
 *
 * プライバシー:
 *   - パスワード・トークン・APIキー・コンテンツ本文は絶対に収集しない
 *   - IPアドレス・ドメイン名は送信しない
 *   - 匿名インストールID（UUID v4）で識別
 */
class DiagnosticEngine {

	private const CONFIG_FILE = 'diagnostics.json';
	private const LOG_FILE    = 'diagnostics_log.json';
	private const ENDPOINT    = 'https://telemetry.adlaire.com/v1/report';
	private const INTERVAL    = 86400; /* 24時間 */
	private const MAX_LOG_ENTRIES   = 500;
	private const MAX_BUFFER_ITEMS  = 100;
	private const VALID_LEVELS = ['basic', 'extended', 'debug'];

	/* 除外すべきセンシティブキー */
	private const SENSITIVE_KEYS = [
		'password', 'password_hash', 'token', 'secret',
		'api_key', 'apikey', 'authorization', 'cookie',
		'session', 'csrf', 'private_key', 'credentials',
	];

	/* ══════════════════════════════════════════════
	   設定管理
	   ══════════════════════════════════════════════ */

	/** デフォルト設定 */
	private static function defaults(): array {
		return [
			'enabled'                => true,
			'level'                  => 'basic',
			'install_id'             => '',
			'last_sent'              => '',
			'first_run_notice_shown' => false,
			'send_interval'          => self::INTERVAL,
		];
	}

	/** 設定を読み込み（初回は自動生成） */
	public static function loadConfig(): array {
		$config = json_read(self::CONFIG_FILE, settings_dir());
		$defaults = self::defaults();
		foreach ($defaults as $k => $v) {
			if (!array_key_exists($k, $config)) {
				$config[$k] = $v;
			}
		}
		/* インストールID 初回生成 */
		if ($config['install_id'] === '') {
			$config['install_id'] = self::generateUuid();
			self::saveConfig($config);
		}
		return $config;
	}

	/** 設定を保存 */
	public static function saveConfig(array $config): void {
		json_write(self::CONFIG_FILE, $config, settings_dir());
	}

	/** 有効かどうか */
	public static function isEnabled(): bool {
		$config = self::loadConfig();
		return !empty($config['enabled']);
	}

	/** 収集レベルを取得 */
	public static function getLevel(): string {
		$config = self::loadConfig();
		$level = $config['level'] ?? 'basic';
		return in_array($level, self::VALID_LEVELS, true) ? $level : 'basic';
	}

	/** 有効/無効を設定 */
	public static function setEnabled(bool $enabled): void {
		$config = self::loadConfig();
		$config['enabled'] = $enabled;
		self::saveConfig($config);
	}

	/** 収集レベルを設定 */
	public static function setLevel(string $level): void {
		if (!in_array($level, self::VALID_LEVELS, true)) return;
		$config = self::loadConfig();
		$config['level'] = $level;
		self::saveConfig($config);
	}

	/** インストールID を取得 */
	public static function getInstallId(): string {
		$config = self::loadConfig();
		return $config['install_id'];
	}

	/* ══════════════════════════════════════════════
	   エラー・ログ収集
	   ══════════════════════════════════════════════ */

	/**
	 * カスタムエラーハンドラを登録
	 * PHP エラーをキャプチャしてログバッファに蓄積
	 */
	public static function registerErrorHandler(): void {
		if (!self::isEnabled()) return;

		set_error_handler(function (int $errno, string $errstr, string $errfile, string $errline) {
			/* E_NOTICE 以下は basic レベルでは無視 */
			$level = self::getLevel();
			if ($level === 'basic' && in_array($errno, [E_NOTICE, E_DEPRECATED, E_USER_NOTICE, E_USER_DEPRECATED], true)) {
				return false; /* デフォルトハンドラに委譲 */
			}

			self::logError([
				'type'      => self::errorTypeString($errno),
				'message'   => self::sanitizeMessage($errstr),
				'file'      => self::sanitizePath($errfile),
				'line'      => $errline,
				'timestamp' => date('c'),
			]);

			return false; /* PHP 標準のエラー処理も実行 */
		});

		/* シャットダウン時の Fatal Error キャプチャ */
		register_shutdown_function(function () {
			$error = error_get_last();
			if ($error === null) return;
			if (!in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) return;

			self::logError([
				'type'      => 'FATAL: ' . self::errorTypeString($error['type']),
				'message'   => self::sanitizeMessage($error['message']),
				'file'      => self::sanitizePath($error['file']),
				'line'      => $error['line'],
				'timestamp' => date('c'),
			]);
		});
	}

	/**
	 * エラーをログバッファに追加
	 */
	public static function logError(array $entry): void {
		$log = json_read(self::LOG_FILE, settings_dir());
		if (!isset($log['errors'])) $log['errors'] = [];

		$log['errors'][] = $entry;

		/* 上限を超えたら古いものから削除 */
		if (count($log['errors']) > self::MAX_LOG_ENTRIES) {
			$log['errors'] = array_slice($log['errors'], -self::MAX_LOG_ENTRIES);
		}

		json_write(self::LOG_FILE, $log, settings_dir());
	}

	/**
	 * 手動でカスタムログを記録（エンジン内から呼び出し可能）
	 *
	 * @param string $category カテゴリ (engine, security, performance 等)
	 * @param string $message  メッセージ
	 * @param array  $context  追加コンテキスト
	 */
	public static function log(string $category, string $message, array $context = []): void {
		if (!self::isEnabled()) return;

		$log = json_read(self::LOG_FILE, settings_dir());
		if (!isset($log['custom'])) $log['custom'] = [];

		$entry = [
			'category'  => $category,
			'message'   => self::sanitizeMessage($message),
			'timestamp' => date('c'),
		];
		if (!empty($context)) {
			$entry['context'] = self::stripSensitiveKeys($context);
		}

		$log['custom'][] = $entry;
		if (count($log['custom']) > self::MAX_LOG_ENTRIES) {
			$log['custom'] = array_slice($log['custom'], -self::MAX_LOG_ENTRIES);
		}

		json_write(self::LOG_FILE, $log, settings_dir());
	}

	/* ══════════════════════════════════════════════
	   データ収集
	   ══════════════════════════════════════════════ */

	/**
	 * 現在のレベルに応じて診断データを収集
	 */
	public static function collect(): array {
		$level = self::getLevel();
		$data = self::collectBasic();

		if ($level === 'extended' || $level === 'debug') {
			$data = array_merge($data, self::collectExtended());
		}
		if ($level === 'debug') {
			$data = array_merge($data, self::collectDebug());
		}

		$data['collect_level'] = $level;
		$data['collected_at']  = date('c');

		return $data;
	}

	/**
	 * Level 1: basic — 環境情報・エラー件数
	 */
	public static function collectBasic(): array {
		$log = json_read(self::LOG_FILE, settings_dir());
		$errorCount = count($log['errors'] ?? []);
		$customLogCount = count($log['custom'] ?? []);

		/* エラー種別ごとの件数集計 */
		$errorSummary = [];
		foreach (($log['errors'] ?? []) as $e) {
			$type = $e['type'] ?? 'unknown';
			$errorSummary[$type] = ($errorSummary[$type] ?? 0) + 1;
		}

		/* 有効エンジン一覧 */
		$engines = [];
		$engineClasses = [
			'AdminEngine', 'TemplateEngine', 'ThemeEngine', 'UpdateEngine',
			'StaticEngine', 'ApiEngine', 'CollectionEngine', 'MarkdownEngine',
			'GitEngine', 'WebhookEngine', 'CacheEngine', 'ImageOptimizer',
			'DiagnosticEngine',
		];
		foreach ($engineClasses as $cls) {
			if (class_exists($cls)) $engines[] = $cls;
		}

		/* コンテンツ件数 */
		$pages = json_read('pages.json', content_dir());
		$collectionCount = 0;
		$collectionItemCount = 0;
		if (class_exists('CollectionEngine') && CollectionEngine::isEnabled()) {
			$collections = CollectionEngine::listCollections();
			$collectionCount = count($collections);
			foreach ($collections as $col) {
				$collectionItemCount += $col['count'] ?? 0;
			}
		}

		/* テーマ名 */
		$settings = json_read('settings.json', settings_dir());
		$theme = $settings['themeSelect'] ?? 'AP-Default';

		return [
			'install_id'          => self::getInstallId(),
			'ap_version'          => defined('AP_VERSION') ? AP_VERSION : 'unknown',
			'php_version'         => PHP_VERSION,
			'os'                  => PHP_OS_FAMILY,
			'sapi'                => PHP_SAPI,
			'engines'             => $engines,
			'theme'               => $theme,
			'page_count'          => count($pages),
			'collection_count'    => $collectionCount,
			'collection_item_count' => $collectionItemCount,
			'error_count'         => $errorCount,
			'error_summary'       => $errorSummary,
			'custom_log_count'    => $customLogCount,
		];
	}

	/**
	 * Level 2: extended — エラーメッセージ・パフォーマンス情報
	 */
	public static function collectExtended(): array {
		$log = json_read(self::LOG_FILE, settings_dir());

		/* 直近50件のエラーメッセージ（PII マスク済） */
		$recentErrors = array_slice($log['errors'] ?? [], -50);
		foreach ($recentErrors as &$e) {
			$e['message'] = self::sanitizeMessage($e['message'] ?? '');
			unset($e['file']); /* extended ではファイルパスを除外 */
		}
		unset($e);

		/* 直近30件のカスタムログ */
		$recentLogs = array_slice($log['custom'] ?? [], -30);

		/* パフォーマンス情報 */
		$perf = [
			'memory_peak'       => memory_get_peak_usage(true),
			'memory_peak_human' => self::humanSize(memory_get_peak_usage(true)),
			'disk_free'         => @disk_free_space('.') ?: 0,
			'php_extensions'    => get_loaded_extensions(),
		];

		/* キャッシュ統計 */
		$cacheStats = class_exists('CacheEngine') ? CacheEngine::getStats() : [];

		return [
			'recent_errors' => $recentErrors,
			'recent_logs'   => $recentLogs,
			'performance'   => $perf,
			'cache_stats'   => $cacheStats,
		];
	}

	/**
	 * Level 3: debug — スタックトレース・詳細設定（手動有効化のみ）
	 */
	public static function collectDebug(): array {
		$log = json_read(self::LOG_FILE, settings_dir());

		/* 直近20件のフルエラー（ファイルパス含む） */
		$fullErrors = array_slice($log['errors'] ?? [], -20);

		/* 設定値ダンプ（センシティブキー除外） */
		$settings = json_read('settings.json', settings_dir());
		$safeSettings = self::stripSensitiveKeys($settings);

		/* PHP 設定の主要項目 */
		$phpConfig = [
			'max_execution_time'  => ini_get('max_execution_time'),
			'memory_limit'        => ini_get('memory_limit'),
			'upload_max_filesize' => ini_get('upload_max_filesize'),
			'post_max_size'       => ini_get('post_max_size'),
			'display_errors'      => ini_get('display_errors'),
			'error_reporting'     => error_reporting(),
			'session.gc_maxlifetime' => ini_get('session.gc_maxlifetime'),
		];

		return [
			'full_errors'  => $fullErrors,
			'settings'     => $safeSettings,
			'php_config'   => $phpConfig,
		];
	}

	/* ══════════════════════════════════════════════
	   送信
	   ══════════════════════════════════════════════ */

	/**
	 * 送信間隔をチェックし、条件を満たせば送信
	 */
	public static function maybeSend(): void {
		if (!self::isEnabled()) return;

		$config = self::loadConfig();
		$lastSent = $config['last_sent'] ?? '';
		$interval = $config['send_interval'] ?? self::INTERVAL;

		if ($lastSent !== '') {
			$lastTime = strtotime($lastSent);
			if ($lastTime !== false && (time() - $lastTime) < $interval) {
				return; /* まだ送信間隔に達していない */
			}
		}

		$data = self::collect();
		if (self::send($data)) {
			$config['last_sent'] = date('c');
			self::saveConfig($config);

			/* 送信済みログをクリア（バッファリセット） */
			self::clearSentLogs();
		}
	}

	/**
	 * 診断データを開発元へ送信
	 *
	 * @return bool 送信成功したか
	 */
	public static function send(array $data): bool {
		if (!function_exists('curl_init')) {
			error_log('DiagnosticEngine: cURL が利用できないため送信をスキップ');
			return false;
		}

		$body = json_encode($data, JSON_UNESCAPED_UNICODE);
		if ($body === false) return false;

		$headers = [
			'Content-Type: application/json',
			'User-Agent: AdlairePlatform/' . (defined('AP_VERSION') ? AP_VERSION : '1.0'),
			'X-AP-Install-ID: ' . ($data['install_id'] ?? ''),
		];

		$ch = curl_init(self::ENDPOINT);
		if ($ch === false) return false;

		curl_setopt_array($ch, [
			CURLOPT_POST           => true,
			CURLOPT_POSTFIELDS     => $body,
			CURLOPT_HTTPHEADER     => $headers,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT        => 5,
			CURLOPT_CONNECTTIMEOUT => 3,
			CURLOPT_FOLLOWLOCATION => false,
		]);

		$result = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($httpCode >= 200 && $httpCode < 300) {
			return true;
		}

		error_log("DiagnosticEngine: 送信失敗 (HTTP {$httpCode})");
		return false;
	}

	/**
	 * 送信済みログをクリア
	 */
	private static function clearSentLogs(): void {
		$log = ['errors' => [], 'custom' => []];
		json_write(self::LOG_FILE, $log, settings_dir());
	}

	/* ══════════════════════════════════════════════
	   UI プレビュー・統計
	   ══════════════════════════════════════════════ */

	/**
	 * 送信されるデータのプレビューを取得（ダッシュボード表示用）
	 */
	public static function preview(): array {
		return self::collect();
	}

	/**
	 * ログ統計サマリーを取得（ダッシュボード表示用）
	 */
	public static function getLogSummary(): array {
		$log = json_read(self::LOG_FILE, settings_dir());
		$errors = $log['errors'] ?? [];
		$custom = $log['custom'] ?? [];

		/* エラー種別ごとの件数 */
		$errorTypes = [];
		foreach ($errors as $e) {
			$type = $e['type'] ?? 'unknown';
			$errorTypes[$type] = ($errorTypes[$type] ?? 0) + 1;
		}

		/* カスタムログのカテゴリごとの件数 */
		$logCategories = [];
		foreach ($custom as $c) {
			$cat = $c['category'] ?? 'other';
			$logCategories[$cat] = ($logCategories[$cat] ?? 0) + 1;
		}

		/* 直近のエラー（最新10件） */
		$recentErrors = array_slice($errors, -10);

		/* 直近のカスタムログ（最新10件） */
		$recentLogs = array_slice($custom, -10);

		return [
			'error_count'     => count($errors),
			'log_count'       => count($custom),
			'error_types'     => $errorTypes,
			'log_categories'  => $logCategories,
			'recent_errors'   => $recentErrors,
			'recent_logs'     => $recentLogs,
		];
	}

	/**
	 * 全ログを取得（ダッシュボード詳細表示用）
	 */
	public static function getAllLogs(): array {
		return json_read(self::LOG_FILE, settings_dir());
	}

	/**
	 * ログをクリア
	 */
	public static function clearLogs(): void {
		json_write(self::LOG_FILE, ['errors' => [], 'custom' => []], settings_dir());
	}

	/* ══════════════════════════════════════════════
	   POST アクションハンドラ
	   ══════════════════════════════════════════════ */

	public static function handle(): void {
		$action = $_POST['ap_action'] ?? '';
		$valid = [
			'diag_set_enabled', 'diag_set_level',
			'diag_preview', 'diag_send_now',
			'diag_clear_logs', 'diag_get_logs',
			'diag_get_summary',
		];
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
			'diag_set_enabled' => self::handleSetEnabled(),
			'diag_set_level'   => self::handleSetLevel(),
			'diag_preview'     => self::handlePreview(),
			'diag_send_now'    => self::handleSendNow(),
			'diag_clear_logs'  => self::handleClearLogs(),
			'diag_get_logs'    => self::handleGetLogs(),
			'diag_get_summary' => self::handleGetSummary(),
		};
	}

	private static function handleSetEnabled(): never {
		if (!AdminEngine::hasRole('admin')) {
			self::jsonError('管理者権限が必要です', 403);
		}
		$enabled = filter_var($_POST['enabled'] ?? 'true', FILTER_VALIDATE_BOOLEAN);
		self::setEnabled($enabled);
		AdminEngine::logActivity('診断データ送信を' . ($enabled ? '有効' : '無効') . 'に変更');
		self::jsonOk(['enabled' => $enabled]);
	}

	private static function handleSetLevel(): never {
		if (!AdminEngine::hasRole('admin')) {
			self::jsonError('管理者権限が必要です', 403);
		}
		$level = $_POST['level'] ?? 'basic';
		if (!in_array($level, self::VALID_LEVELS, true)) {
			self::jsonError('無効なレベルです: ' . $level);
		}
		self::setLevel($level);
		AdminEngine::logActivity('診断データ収集レベルを ' . $level . ' に変更');
		self::jsonOk(['level' => $level]);
	}

	private static function handlePreview(): never {
		$data = self::preview();
		self::jsonOk($data);
	}

	private static function handleSendNow(): never {
		if (!AdminEngine::hasRole('admin')) {
			self::jsonError('管理者権限が必要です', 403);
		}
		$data = self::collect();
		$success = self::send($data);
		if ($success) {
			$config = self::loadConfig();
			$config['last_sent'] = date('c');
			self::saveConfig($config);
			self::clearSentLogs();
			AdminEngine::logActivity('診断データを手動送信');
			self::jsonOk(['message' => '送信しました', 'sent_at' => date('c')]);
		} else {
			self::jsonError('送信に失敗しました（エンドポイントに到達できない可能性があります）');
		}
	}

	private static function handleClearLogs(): never {
		if (!AdminEngine::hasRole('admin')) {
			self::jsonError('管理者権限が必要です', 403);
		}
		self::clearLogs();
		AdminEngine::logActivity('診断ログをクリア');
		self::jsonOk(['message' => 'ログをクリアしました']);
	}

	private static function handleGetLogs(): never {
		$logs = self::getAllLogs();
		self::jsonOk($logs);
	}

	private static function handleGetSummary(): never {
		$summary = self::getLogSummary();
		$config = self::loadConfig();
		$summary['enabled'] = !empty($config['enabled']);
		$summary['level'] = $config['level'] ?? 'basic';
		$summary['install_id'] = $config['install_id'] ?? '';
		$summary['last_sent'] = $config['last_sent'] ?? '';
		self::jsonOk($summary);
	}

	/* ══════════════════════════════════════════════
	   初回通知
	   ══════════════════════════════════════════════ */

	/**
	 * 初回ダッシュボードアクセス時にバナー表示が必要か
	 */
	public static function shouldShowNotice(): bool {
		$config = self::loadConfig();
		return $config['enabled'] && !$config['first_run_notice_shown'];
	}

	/**
	 * 通知表示済みとしてマーク
	 */
	public static function markNoticeShown(): void {
		$config = self::loadConfig();
		$config['first_run_notice_shown'] = true;
		self::saveConfig($config);
	}

	/* ══════════════════════════════════════════════
	   プライバシー・サニタイズ
	   ══════════════════════════════════════════════ */

	/**
	 * メッセージ内の個人情報をマスク
	 */
	private static function sanitizeMessage(string $msg): string {
		/* メールアドレス */
		$msg = preg_replace('/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/', '[EMAIL]', $msg);
		/* IP アドレス (IPv4) */
		$msg = preg_replace('/\b\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\b/', '[IP]', $msg);
		/* ファイルパスのユーザー名部分 */
		$msg = preg_replace('#/home/[^/]+/#', '/home/[USER]/', $msg);
		$msg = preg_replace('#C:\\\\Users\\\\[^\\\\]+\\\\#i', 'C:\\Users\\[USER]\\', $msg);
		return $msg;
	}

	/**
	 * ファイルパスからユーザー固有部分を除去
	 */
	private static function sanitizePath(string $path): string {
		/* ドキュメントルートからの相対パスに変換 */
		$docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
		if ($docRoot !== '' && str_starts_with($path, $docRoot)) {
			return substr($path, strlen($docRoot));
		}
		/* ホームディレクトリ部分をマスク */
		$path = preg_replace('#/home/[^/]+/#', '/home/[USER]/', $path);
		$path = preg_replace('#C:\\\\Users\\\\[^\\\\]+\\\\#i', 'C:\\Users\\[USER]\\', $path);
		return $path;
	}

	/**
	 * 連想配列からセンシティブなキーを再帰的に除去
	 */
	private static function stripSensitiveKeys(array $data): array {
		$result = [];
		foreach ($data as $key => $value) {
			$lowerKey = strtolower((string)$key);
			$isSensitive = false;
			foreach (self::SENSITIVE_KEYS as $sk) {
				if (str_contains($lowerKey, $sk)) {
					$isSensitive = true;
					break;
				}
			}
			if ($isSensitive) {
				$result[$key] = '[REDACTED]';
			} elseif (is_array($value)) {
				$result[$key] = self::stripSensitiveKeys($value);
			} else {
				$result[$key] = $value;
			}
		}
		return $result;
	}

	/* ══════════════════════════════════════════════
	   ユーティリティ
	   ══════════════════════════════════════════════ */

	/** UUID v4 生成 */
	private static function generateUuid(): string {
		$bytes = random_bytes(16);
		$bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40); /* version 4 */
		$bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80); /* variant */
		return sprintf(
			'%s-%s-%s-%s-%s',
			bin2hex(substr($bytes, 0, 4)),
			bin2hex(substr($bytes, 4, 2)),
			bin2hex(substr($bytes, 6, 2)),
			bin2hex(substr($bytes, 8, 2)),
			bin2hex(substr($bytes, 10, 6))
		);
	}

	/** エラータイプ定数を文字列に変換 */
	private static function errorTypeString(int $type): string {
		return match ($type) {
			E_ERROR             => 'E_ERROR',
			E_WARNING           => 'E_WARNING',
			E_PARSE             => 'E_PARSE',
			E_NOTICE            => 'E_NOTICE',
			E_CORE_ERROR        => 'E_CORE_ERROR',
			E_CORE_WARNING      => 'E_CORE_WARNING',
			E_COMPILE_ERROR     => 'E_COMPILE_ERROR',
			E_COMPILE_WARNING   => 'E_COMPILE_WARNING',
			E_USER_ERROR        => 'E_USER_ERROR',
			E_USER_WARNING      => 'E_USER_WARNING',
			E_USER_NOTICE       => 'E_USER_NOTICE',
			E_STRICT            => 'E_STRICT',
			E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
			E_DEPRECATED        => 'E_DEPRECATED',
			E_USER_DEPRECATED   => 'E_USER_DEPRECATED',
			default             => 'E_UNKNOWN(' . $type . ')',
		};
	}

	/** ファイルサイズを人間が読める形式に変換 */
	private static function humanSize(int $bytes): string {
		if ($bytes < 1024) return $bytes . ' B';
		if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
		return round($bytes / 1048576, 1) . ' MB';
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
}
