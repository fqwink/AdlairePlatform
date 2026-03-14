<?php
/**
 * Logger - 集中ログ管理エンジン
 *
 * B-6 fix: error_log() のみに依存していたログ出力を構造化ログに統一。
 * ファイルベースのログ出力 + PSR-3 互換のログレベルをサポート。
 *
 * Ver.1.5: AIS\System\AppLogger に内部委譲。既存 static API は完全維持。
 *
 * 使用例:
 *   Logger::info('ページ保存', ['slug' => 'home']);
 *   Logger::error('JSON書き込み失敗', ['file' => 'settings.json']);
 *   Logger::warning('レガシー API 使用検出');
 */
class Logger {

	/** ログレベル定数（PSR-3 互換） */
	public const DEBUG   = 'DEBUG';
	public const INFO    = 'INFO';
	public const WARNING = 'WARNING';
	public const ERROR   = 'ERROR';

	/** ログレベル優先度 */
	private const LEVEL_PRIORITY = [
		self::DEBUG   => 0,
		self::INFO    => 1,
		self::WARNING => 2,
		self::ERROR   => 3,
	];

	/** 最小出力レベル（これ未満のログは無視） */
	private static string $minLevel = self::INFO;

	/** ログファイルディレクトリ */
	private static string $logDir = 'data/logs';

	/** 現在のリクエスト固有ID（トレーサビリティ用） */
	private static string $requestId = '';

	/** @var \AIS\System\AppLogger|null Ver.1.5 Framework ロガーインスタンス */
	private static ?\AIS\System\AppLogger $appLogger = null;

	/** ログローテーション: 最大ファイルサイズ（5MB） */
	private const MAX_FILE_SIZE = 5 * 1024 * 1024;

	/** ログローテーション: 保持世代数 */
	private const MAX_GENERATIONS = 5;

	/**
	 * ログ出力の初期化
	 */
	public static function init(string $minLevel = self::INFO, string $logDir = ''): void {
		if (isset(self::LEVEL_PRIORITY[$minLevel])) {
			self::$minLevel = $minLevel;
		}
		if ($logDir !== '') {
			self::$logDir = $logDir;
		}
		if (self::$requestId === '') {
			self::$requestId = substr(bin2hex(random_bytes(4)), 0, 8);
		}

		/* Ver.1.5: Framework AppLogger を初期化 */
		$levelMap = [self::DEBUG => 'debug', self::INFO => 'info', self::WARNING => 'warning', self::ERROR => 'error'];
		self::$appLogger = new \AIS\System\AppLogger(
			self::$logDir,
			$levelMap[self::$minLevel] ?? 'info'
		);
	}

	/**
	 * Ver.1.5: Framework AppLogger インスタンスを取得する
	 */
	public static function getAppLogger(): \AIS\System\AppLogger {
		if (self::$appLogger === null) {
			self::$appLogger = new \AIS\System\AppLogger(self::$logDir, 'info');
		}
		return self::$appLogger;
	}

	/* ── 公開ログメソッド ── */

	public static function debug(string $message, array $context = []): void {
		self::log(self::DEBUG, $message, $context);
	}

	public static function info(string $message, array $context = []): void {
		self::log(self::INFO, $message, $context);
	}

	public static function warning(string $message, array $context = []): void {
		self::log(self::WARNING, $message, $context);
	}

	public static function error(string $message, array $context = []): void {
		self::log(self::ERROR, $message, $context);
	}

	/* ── コアログ処理 ── */

	private static function log(string $level, string $message, array $context): void {
		/* レベルフィルタ */
		if ((self::LEVEL_PRIORITY[$level] ?? 0) < (self::LEVEL_PRIORITY[self::$minLevel] ?? 0)) {
			return;
		}

		/* リクエストID初期化（init未呼び出し対応） */
		if (self::$requestId === '') {
			self::$requestId = substr(bin2hex(random_bytes(4)), 0, 8);
		}

		$entry = [
			'time'       => date('c'),
			'level'      => $level,
			'request_id' => self::$requestId,
			'message'    => $message,
		];

		if (!empty($context)) {
			$entry['context'] = $context;
		}

		/* IP / URI（INFO以上のみ、プライバシー配慮） */
		if ($level !== self::DEBUG) {
			$entry['ip']  = $_SERVER['REMOTE_ADDR'] ?? '';
			$entry['uri'] = $_SERVER['REQUEST_URI'] ?? '';
		}

		$line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";

		/* ファイル出力 */
		self::writeToFile($line);

		/* ERROR レベルは PHP error_log にも出力（既存監視ツールとの互換性） */
		if ($level === self::ERROR) {
			error_log("AP [{$level}] {$message}" . ($context ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : ''));
		}
	}

	/**
	 * ログファイルに書き込み（日付ベースのファイル分割 + ローテーション）
	 */
	private static function writeToFile(string $line): void {
		$dir = self::$logDir;
		if (!is_dir($dir)) {
			if (!@mkdir($dir, 0755, true)) {
				/* ディレクトリ作成失敗時は error_log にフォールバック */
				error_log('Logger: ログディレクトリ作成失敗: ' . $dir);
				return;
			}
			/* .htaccess でログディレクトリを保護 */
			$htaccess = $dir . '/.htaccess';
			if (!file_exists($htaccess)) {
				@file_put_contents($htaccess, "Deny from all\n");
			}
		}

		$filename = $dir . '/ap-' . date('Y-m-d') . '.log';

		/* ローテーション: サイズ超過時 */
		if (file_exists($filename) && filesize($filename) > self::MAX_FILE_SIZE) {
			self::rotate($filename);
		}

		@file_put_contents($filename, $line, FILE_APPEND | LOCK_EX);
	}

	/**
	 * ログファイルをローテーション
	 */
	private static function rotate(string $filename): void {
		for ($i = self::MAX_GENERATIONS - 1; $i >= 1; $i--) {
			$old = $filename . '.' . $i;
			$new = $filename . '.' . ($i + 1);
			if (file_exists($old)) {
				@rename($old, $new);
			}
		}
		@rename($filename, $filename . '.1');

		/* 最古の世代を削除 */
		$oldest = $filename . '.' . (self::MAX_GENERATIONS + 1);
		if (file_exists($oldest)) {
			@unlink($oldest);
		}
	}

	/**
	 * 古いログファイルをクリーンアップ（30日以上経過）
	 */
	public static function cleanup(int $daysToKeep = 30): int {
		$dir = self::$logDir;
		if (!is_dir($dir)) return 0;

		$cutoff = time() - ($daysToKeep * 86400);
		$deleted = 0;
		foreach (glob($dir . '/ap-*.log*') ?: [] as $file) {
			if (filemtime($file) < $cutoff) {
				if (@unlink($file)) $deleted++;
			}
		}
		return $deleted;
	}
}
