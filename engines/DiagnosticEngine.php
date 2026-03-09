<?php
/**
 * DiagnosticEngine - リアルタイム診断・テレメトリエンジン
 *
 * 本番サーバ環境でシステムエラー・ログデータ・環境情報を集約し、
 * 開発元へリアルタイム送信する。デフォルト有効・エンドユーザーで無効化可能。
 * 全ログデータは作成から14日間サーバ内に強制保存し、14日経過後に古い順に削除。
 *
 * 収集レベル:
 *   basic    — 環境情報・エラー件数のみ（デフォルト）
 *   extended — エラーメッセージ・パフォーマンス情報
 *   debug    — 12カテゴリ別集計・スタックトレース・エンジン別実行時間
 *
 * デバッグ診断カテゴリ（12分類）:
 *   syntax, runtime, logic, semantic, off_by_one, race_condition,
 *   memory, performance, security, environment, timing, integration
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
	private const INTERVAL    = 0; /* リアルタイム送信（毎リクエスト） */
	private const LOG_RETENTION_DAYS = 14; /* ログ強制保存期間（日） */
	private const MAX_BUFFER_ITEMS   = 100;
	private const VALID_LEVELS = ['basic', 'extended', 'debug'];
	private const RETRY_MAX        = 3;
	private const RETRY_BACKOFF    = [1, 2, 4]; /* 秒 */
	private const RETRYABLE_CODES  = [429, 502, 503];
	private const CIRCUIT_BREAKER_THRESHOLD = 5;
	private const CIRCUIT_BREAKER_DURATION  = 86400; /* 24時間 */
	private const MAX_LOG_SIZE_BYTES       = 524288; /* 512 KB */
	private const LOG_ARCHIVE_GENERATIONS  = 3;

	/* ── デバッグ診断カテゴリ（12分類） ── */
	private const DEBUG_CATEGORIES = [
		'syntax',       /* 構文エラー: E_PARSE, E_COMPILE_ERROR */
		'runtime',      /* 実行時エラー: E_ERROR, E_WARNING, E_NOTICE 等 */
		'logic',        /* 論理エラー: アサーション失敗・不正な戻り値 */
		'semantic',     /* 意味エラー: 型不整合・未定義変数 */
		'off_by_one',   /* オフバイワン: 配列境界違反・範囲外アクセス */
		'race_condition', /* 競合状態: ファイルロック失敗・同時アクセス */
		'memory',       /* メモリ関連: メモリ上限・リーク・確保失敗 */
		'performance',  /* パフォーマンス: 低速処理・ボトルネック */
		'security',     /* セキュリティ: 不正アクセス・インジェクション */
		'environment',  /* 環境依存: PHP バージョン差異・拡張不足・OS 固有 */
		'timing',       /* タイミング: タイムアウト・応答遅延 */
		'integration',  /* 統合: 外部 API 障害・Webhook 失敗 */
	];

	/* ── 計測対象エンジン一覧 ── */
	private const ENGINE_CLASSES = [
		'AdminEngine', 'TemplateEngine', 'ThemeEngine', 'UpdateEngine',
		'StaticEngine', 'ApiEngine', 'CollectionEngine', 'MarkdownEngine',
		'GitEngine', 'WebhookEngine', 'CacheEngine', 'ImageOptimizer',
		'DiagnosticEngine',
	];

	/* ── パフォーマンスプロファイラ（リクエスト内メモリ） ── */
	private static array $timers = [];
	private static array $timings = [];
	private static int $memoryStart = 0;

	/* ── エンジン別実行時間トラッカー ── */
	private static array $engineTimers = [];       /* 実行中タイマー */
	private static array $engineTimings = [];      /* 計測結果（ms） */
	private static array $engineMemoryBefore = []; /* エンジン起動前メモリ */
	private static array $engineCallCounts = [];   /* 呼び出し回数 */

	/* ── スタックトレース蓄積（リクエスト内） ── */
	private static array $capturedTraces = [];

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
			'last_env_sent'          => '',
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
	   パフォーマンスプロファイラ
	   ══════════════════════════════════════════════ */

	/** タイマー開始 */
	public static function startTimer(string $label): void {
		self::$timers[$label] = hrtime(true);
		if ($label === 'request_total') {
			self::$memoryStart = memory_get_usage(true);
		}
	}

	/** タイマー停止・記録 */
	public static function stopTimer(string $label): void {
		if (!isset(self::$timers[$label])) return;
		$elapsed = (hrtime(true) - self::$timers[$label]) / 1_000_000; /* ms */
		self::$timings[$label] = round($elapsed, 2);
		unset(self::$timers[$label]);
	}

	/** 計測結果を取得 */
	public static function getTimings(): array {
		return [
			'timings_ms'        => self::$timings,
			'memory_start'      => self::$memoryStart,
			'memory_peak'       => memory_get_peak_usage(true),
			'memory_peak_human' => self::humanSize(memory_get_peak_usage(true)),
		];
	}

	/* ══════════════════════════════════════════════
	   エンジン別実行時間トラッカー
	   ══════════════════════════════════════════════ */

	/**
	 * エンジン処理の計測を開始
	 *
	 * @param string $engine エンジン名（例: 'TemplateEngine'）
	 * @param string $method メソッド名（例: 'render'）
	 */
	public static function startEngineTimer(string $engine, string $method = ''): void {
		$key = $method !== '' ? "{$engine}::{$method}" : $engine;
		self::$engineTimers[$key] = hrtime(true);
		self::$engineMemoryBefore[$key] = memory_get_usage(true);
	}

	/**
	 * エンジン処理の計測を停止・記録
	 *
	 * @return float|null 経過時間（ms）。タイマー未開始なら null
	 */
	public static function stopEngineTimer(string $engine, string $method = ''): ?float {
		$key = $method !== '' ? "{$engine}::{$method}" : $engine;
		if (!isset(self::$engineTimers[$key])) return null;

		$elapsed = (hrtime(true) - self::$engineTimers[$key]) / 1_000_000; /* ms */
		$elapsed = round($elapsed, 2);
		$memoryDelta = memory_get_usage(true) - (self::$engineMemoryBefore[$key] ?? 0);

		/* 累積記録 */
		if (!isset(self::$engineTimings[$key])) {
			self::$engineTimings[$key] = [
				'total_ms'    => 0.0,
				'calls'       => 0,
				'max_ms'      => 0.0,
				'min_ms'      => PHP_FLOAT_MAX,
				'memory_delta_total' => 0,
			];
		}
		self::$engineTimings[$key]['total_ms'] += $elapsed;
		self::$engineTimings[$key]['calls']++;
		self::$engineTimings[$key]['max_ms'] = max(self::$engineTimings[$key]['max_ms'], $elapsed);
		self::$engineTimings[$key]['min_ms'] = min(self::$engineTimings[$key]['min_ms'], $elapsed);
		self::$engineTimings[$key]['memory_delta_total'] += $memoryDelta;

		/* 汎用タイマーにも記録 */
		self::$timings[$key] = $elapsed;

		/* 呼び出し回数 */
		$engineName = explode('::', $key)[0];
		self::$engineCallCounts[$engineName] = (self::$engineCallCounts[$engineName] ?? 0) + 1;

		unset(self::$engineTimers[$key], self::$engineMemoryBefore[$key]);

		return $elapsed;
	}

	/**
	 * エンジン別実行時間の詳細を取得
	 */
	public static function getEngineTimings(): array {
		$result = [];
		foreach (self::$engineTimings as $key => $data) {
			$result[$key] = [
				'total_ms'     => round($data['total_ms'], 2),
				'calls'        => $data['calls'],
				'avg_ms'       => $data['calls'] > 0 ? round($data['total_ms'] / $data['calls'], 2) : 0,
				'max_ms'       => round($data['max_ms'], 2),
				'min_ms'       => $data['min_ms'] === PHP_FLOAT_MAX ? 0 : round($data['min_ms'], 2),
				'memory_delta' => $data['memory_delta_total'],
				'memory_delta_human' => self::humanSize(abs($data['memory_delta_total'])),
			];
		}

		/* エンジン単位の合計 */
		$engineTotals = [];
		foreach ($result as $key => $data) {
			$engineName = explode('::', $key)[0];
			if (!isset($engineTotals[$engineName])) {
				$engineTotals[$engineName] = ['total_ms' => 0.0, 'calls' => 0, 'methods' => []];
			}
			$engineTotals[$engineName]['total_ms'] += $data['total_ms'];
			$engineTotals[$engineName]['calls'] += $data['calls'];
			$engineTotals[$engineName]['methods'][$key] = $data;
		}

		return [
			'detail'  => $result,
			'engines' => $engineTotals,
			'summary' => [
				'total_engines_tracked' => count($engineTotals),
				'total_calls'           => array_sum(self::$engineCallCounts),
				'engine_call_counts'    => self::$engineCallCounts,
			],
		];
	}

	/* ══════════════════════════════════════════════
	   スタックトレース収集
	   ══════════════════════════════════════════════ */

	/**
	 * 現在のスタックトレースをキャプチャして蓄積
	 *
	 * @param string $label トレースの識別ラベル
	 * @param int    $depth 最大フレーム数
	 */
	public static function captureTrace(string $label, int $depth = 15): void {
		if (!self::isEnabled()) return;
		$level = self::getLevel();
		if ($level !== 'debug' && $level !== 'extended') return;

		$trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $depth);
		self::$capturedTraces[] = [
			'label'     => $label,
			'trace'     => self::sanitizeStackTrace($trace),
			'timestamp' => date('c'),
			'memory'    => memory_get_usage(true),
		];

		/* メモリ保護: リクエスト内50件まで */
		if (count(self::$capturedTraces) > 50) {
			self::$capturedTraces = array_slice(self::$capturedTraces, -50);
		}
	}

	/**
	 * 蓄積されたスタックトレースを取得
	 */
	public static function getCapturedTraces(): array {
		return self::$capturedTraces;
	}

	/* ══════════════════════════════════════════════
	   エラー・ログ収集
	   ══════════════════════════════════════════════ */

	/**
	 * カスタムエラーハンドラを登録
	 * PHP エラーをキャプチャし、12カテゴリに自動分類してログバッファに蓄積
	 */
	public static function registerErrorHandler(): void {
		if (!self::isEnabled()) return;

		set_error_handler(function (int $errno, string $errstr, string $errfile, string $errline) {
			/* E_NOTICE 以下は basic レベルでは無視 */
			$level = self::getLevel();
			if ($level === 'basic' && in_array($errno, [E_NOTICE, E_DEPRECATED, E_USER_NOTICE, E_USER_DEPRECATED], true)) {
				return false; /* デフォルトハンドラに委譲 */
			}

			$debugCategory = self::classifyError($errno, $errstr);

			$entry = [
				'type'           => self::errorTypeString($errno),
				'debug_category' => $debugCategory,
				'message'        => self::sanitizeMessage($errstr),
				'file'           => self::sanitizePath($errfile),
				'line'           => $errline,
				'timestamp'      => date('c'),
			];

			/* extended 以上でスタックトレースを収集 */
			if ($level === 'debug' || $level === 'extended') {
				$depth = $level === 'debug' ? 20 : 10;
				$entry['stack_trace'] = self::sanitizeStackTrace(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $depth));
			}

			self::logError($entry);

			return false; /* PHP 標準のエラー処理も実行 */
		});

		/* 未キャッチ例外ハンドラ — 例外のスタックトレースを完全収集 */
		$previousHandler = set_exception_handler(function (\Throwable $exception) use (&$previousHandler) {
			$debugCategory = self::classifyException($exception);

			$entry = [
				'type'           => 'EXCEPTION: ' . get_class($exception),
				'debug_category' => $debugCategory,
				'message'        => self::sanitizeMessage($exception->getMessage()),
				'file'           => self::sanitizePath($exception->getFile()),
				'line'           => $exception->getLine(),
				'code'           => $exception->getCode(),
				'timestamp'      => date('c'),
				'stack_trace'    => self::sanitizeExceptionTrace($exception),
			];

			/* チェーンされた例外がある場合も収集 */
			$previous = $exception->getPrevious();
			if ($previous !== null) {
				$entry['previous_exception'] = [
					'type'        => get_class($previous),
					'message'     => self::sanitizeMessage($previous->getMessage()),
					'file'        => self::sanitizePath($previous->getFile()),
					'line'        => $previous->getLine(),
					'stack_trace' => self::sanitizeExceptionTrace($previous),
				];
			}

			self::logError($entry);

			/* 元のハンドラがあれば委譲 */
			if ($previousHandler !== null) {
				($previousHandler)($exception);
			}
		});

		/* シャットダウン時の Fatal Error キャプチャ */
		register_shutdown_function(function () {
			$error = error_get_last();
			if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
				$debugCategory = self::classifyError($error['type'], $error['message']);

				self::logError([
					'type'           => 'FATAL: ' . self::errorTypeString($error['type']),
					'debug_category' => $debugCategory,
					'message'        => self::sanitizeMessage($error['message']),
					'file'           => self::sanitizePath($error['file']),
					'line'           => $error['line'],
					'timestamp'      => date('c'),
				]);
			}

			/* シャットダウン時にランタイムスナップショット（エンジン別実行時間含む）を記録 */
			self::captureRuntimeSnapshot();
		});
	}

	/**
	 * PHP エラーを12カテゴリに自動分類
	 */
	private static function classifyError(int $errno, string $message): string {
		$msg = strtolower($message);

		/* 構文エラー */
		if (in_array($errno, [E_PARSE, E_COMPILE_ERROR, E_COMPILE_WARNING], true)) {
			return 'syntax';
		}

		/* メモリ関連 */
		if (str_contains($msg, 'allowed memory size') || str_contains($msg, 'out of memory')
			|| str_contains($msg, 'memory allocation')) {
			return 'memory';
		}

		/* タイミング */
		if (str_contains($msg, 'maximum execution time') || str_contains($msg, 'timed out')
			|| str_contains($msg, 'timeout')) {
			return 'timing';
		}

		/* オフバイワン・配列境界 */
		if (str_contains($msg, 'undefined offset') || str_contains($msg, 'undefined index')
			|| str_contains($msg, 'out of range') || str_contains($msg, 'array_slice')
			|| str_contains($msg, 'undefined array key')) {
			return 'off_by_one';
		}

		/* 意味エラー（型関連） */
		if (str_contains($msg, 'type error') || str_contains($msg, 'typeerror')
			|| str_contains($msg, 'cannot assign') || str_contains($msg, 'must be of type')
			|| str_contains($msg, 'undefined variable') || str_contains($msg, 'undefined property')) {
			return 'semantic';
		}

		/* 競合状態 */
		if (str_contains($msg, 'lock') || str_contains($msg, 'deadlock')
			|| str_contains($msg, 'resource temporarily unavailable')
			|| str_contains($msg, 'concurrent') || str_contains($msg, 'flock')) {
			return 'race_condition';
		}

		/* セキュリティ */
		if (str_contains($msg, 'permission denied') || str_contains($msg, 'access denied')
			|| str_contains($msg, 'csrf') || str_contains($msg, 'injection')
			|| str_contains($msg, 'xss') || str_contains($msg, 'unauthorized')) {
			return 'security';
		}

		/* 統合（外部連携） */
		if (str_contains($msg, 'curl') || str_contains($msg, 'http') || str_contains($msg, 'api')
			|| str_contains($msg, 'connection refused') || str_contains($msg, 'name resolution')
			|| str_contains($msg, 'webhook') || str_contains($msg, 'socket')) {
			return 'integration';
		}

		/* 環境依存 */
		if (str_contains($msg, 'extension') || str_contains($msg, 'function not found')
			|| str_contains($msg, 'class not found') || str_contains($msg, 'not supported')
			|| str_contains($msg, 'php version') || str_contains($msg, 'call to undefined function')) {
			return 'environment';
		}

		/* 論理エラー */
		if ($errno === E_USER_ERROR || str_contains($msg, 'assertion')
			|| str_contains($msg, 'invalid argument') || str_contains($msg, 'unexpected value')
			|| str_contains($msg, 'logic error') || str_contains($msg, 'invariant')) {
			return 'logic';
		}

		/* それ以外は実行時エラー */
		return 'runtime';
	}

	/**
	 * 例外を12カテゴリに分類
	 */
	private static function classifyException(\Throwable $e): string {
		$class = strtolower(get_class($e));
		$msg   = strtolower($e->getMessage());

		/* TypeError → semantic */
		if ($e instanceof \TypeError || str_contains($class, 'typeerror')) {
			return 'semantic';
		}
		/* ParseError → syntax */
		if ($e instanceof \ParseError) {
			return 'syntax';
		}
		/* LogicException 系 → logic */
		if ($e instanceof \LogicException || $e instanceof \DomainException
			|| $e instanceof \InvalidArgumentException || $e instanceof \LengthException) {
			return 'logic';
		}
		/* OutOfRangeException / OutOfBoundsException → off_by_one */
		if ($e instanceof \OutOfRangeException || $e instanceof \OutOfBoundsException) {
			return 'off_by_one';
		}
		/* OverflowException → memory */
		if ($e instanceof \OverflowException || str_contains($msg, 'memory')) {
			return 'memory';
		}
		/* RuntimeException のメッセージベース分類 */
		if (str_contains($msg, 'timeout') || str_contains($msg, 'timed out')) {
			return 'timing';
		}
		if (str_contains($msg, 'lock') || str_contains($msg, 'deadlock') || str_contains($msg, 'concurrent')) {
			return 'race_condition';
		}
		if (str_contains($msg, 'curl') || str_contains($msg, 'connection') || str_contains($msg, 'webhook')
			|| str_contains($msg, 'api') || str_contains($msg, 'http')) {
			return 'integration';
		}
		if (str_contains($msg, 'permission') || str_contains($msg, 'unauthorized') || str_contains($msg, 'forbidden')) {
			return 'security';
		}
		if (str_contains($msg, 'extension') || str_contains($msg, 'not supported') || str_contains($msg, 'class not found')) {
			return 'environment';
		}

		return 'runtime';
	}

	/**
	 * 例外のスタックトレースをサニタイズ
	 */
	private static function sanitizeExceptionTrace(\Throwable $e): array {
		$result = [];
		foreach ($e->getTrace() as $i => $frame) {
			if ($i >= 20) break; /* 最大20フレーム */
			$entry = [];
			if (isset($frame['file'])) {
				$entry['file'] = self::sanitizePath($frame['file']);
			}
			if (isset($frame['line'])) {
				$entry['line'] = $frame['line'];
			}
			if (isset($frame['class'])) {
				$entry['class'] = $frame['class'];
			}
			if (isset($frame['function'])) {
				$entry['function'] = $frame['function'];
			}
			if (isset($frame['type'])) {
				$entry['type'] = $frame['type']; /* -> or :: */
			}
			$result[] = $entry;
		}
		return $result;
	}

	/**
	 * デバッグイベントを12カテゴリ分類付きで記録
	 *
	 * @param string $category DEBUG_CATEGORIES のいずれか
	 * @param string $message  イベントメッセージ
	 * @param array  $context  追加コンテキスト（スタックトレース等）
	 */
	public static function logDebugEvent(string $category, string $message, array $context = []): void {
		if (!self::isEnabled()) return;
		if (!in_array($category, self::DEBUG_CATEGORIES, true)) {
			$category = 'runtime'; /* 不明カテゴリはランタイムへ */
		}

		$entry = [
			'debug_category' => $category,
			'message'        => self::sanitizeMessage($message),
			'timestamp'      => date('c'),
		];
		if (!empty($context)) {
			$entry['context'] = self::stripSensitiveKeys($context);
		}
		if (self::getLevel() === 'debug') {
			$entry['stack_trace'] = self::sanitizeStackTrace(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 8));
		}

		self::log($category, $message, array_merge($context, ['debug_category' => $category]));
	}

	/**
	 * パフォーマンス異常を検知して記録
	 *
	 * @param string $label     処理名
	 * @param float  $elapsed   経過時間（ミリ秒）
	 * @param float  $threshold 閾値（ミリ秒）
	 */
	public static function logSlowExecution(string $label, float $elapsed, float $threshold = 1000.0): void {
		if ($elapsed < $threshold) return;
		self::logDebugEvent('performance', "低速処理検知: {$label} ({$elapsed}ms > {$threshold}ms)", [
			'label'     => $label,
			'elapsed_ms' => $elapsed,
			'threshold_ms' => $threshold,
		]);
	}

	/**
	 * メモリ使用量を監視して異常時に記録
	 */
	public static function checkMemoryUsage(): void {
		$limit = self::parseMemoryLimit(ini_get('memory_limit'));
		if ($limit <= 0) return;
		$usage = memory_get_usage(true);
		$ratio = $usage / $limit;

		if ($ratio > 0.85) {
			self::logDebugEvent('memory', 'メモリ使用量が上限の' . round($ratio * 100) . '%に到達', [
				'usage_bytes' => $usage,
				'limit_bytes' => $limit,
				'usage_human' => self::humanSize($usage),
				'limit_human' => self::humanSize($limit),
				'ratio'       => round($ratio, 3),
			]);
		}
	}

	/**
	 * 競合状態（ファイルロック失敗等）を記録
	 */
	public static function logRaceCondition(string $resource, string $detail = ''): void {
		self::logDebugEvent('race_condition', "競合状態検知: {$resource}" . ($detail !== '' ? " — {$detail}" : ''), [
			'resource' => $resource,
			'detail'   => $detail,
			'pid'      => getmypid(),
		]);
	}

	/**
	 * 統合エラー（外部 API/Webhook 失敗）を記録
	 */
	public static function logIntegrationError(string $service, int $httpCode = 0, string $detail = ''): void {
		self::logDebugEvent('integration', "外部連携エラー: {$service} (HTTP {$httpCode})" . ($detail !== '' ? " — {$detail}" : ''), [
			'service'   => $service,
			'http_code' => $httpCode,
			'detail'    => $detail,
		]);
	}

	/**
	 * 環境依存エラーを記録
	 */
	public static function logEnvironmentIssue(string $issue, array $context = []): void {
		self::logDebugEvent('environment', "環境依存: {$issue}", $context);
	}

	/**
	 * タイミングエラー（タイムアウト等）を記録
	 */
	public static function logTimingIssue(string $operation, float $elapsed, float $limit): void {
		self::logDebugEvent('timing', "タイミング異常: {$operation} ({$elapsed}s / 上限{$limit}s)", [
			'operation'  => $operation,
			'elapsed_s'  => $elapsed,
			'limit_s'    => $limit,
		]);
	}

	/**
	 * シャットダウン時のランタイムスナップショット
	 * エンジン別実行時間・メモリ・スタックトレース数を含む
	 */
	private static function captureRuntimeSnapshot(): void {
		$engineTimings = self::getEngineTimings();

		$snapshot = [
			'memory_usage'       => memory_get_usage(true),
			'memory_peak'        => memory_get_peak_usage(true),
			'memory_usage_human' => self::humanSize(memory_get_usage(true)),
			'memory_peak_human'  => self::humanSize(memory_get_peak_usage(true)),
			'timings'            => self::$timings,
			'engine_timings'     => $engineTimings['engines'] ?? [],
			'engine_summary'     => $engineTimings['summary'] ?? [],
			'traced_count'       => count(self::$capturedTraces),
		];
		self::log('runtime_snapshot', 'シャットダウン時スナップショット', $snapshot);
	}

	/**
	 * スタックトレースをサニタイズ
	 */
	private static function sanitizeStackTrace(array $trace): array {
		$result = [];
		foreach ($trace as $frame) {
			$entry = [];
			if (isset($frame['file'])) {
				$entry['file'] = self::sanitizePath($frame['file']);
			}
			if (isset($frame['line'])) {
				$entry['line'] = $frame['line'];
			}
			if (isset($frame['function'])) {
				$entry['function'] = $frame['function'];
			}
			if (isset($frame['class'])) {
				$entry['class'] = $frame['class'];
			}
			$result[] = $entry;
		}
		return $result;
	}

	/**
	 * memory_limit 文字列をバイト数に変換
	 */
	private static function parseMemoryLimit(string $limit): int {
		$limit = trim($limit);
		if ($limit === '-1') return 0;
		$last = strtolower(substr($limit, -1));
		$value = (int)$limit;
		return match ($last) {
			'g' => $value * 1024 * 1024 * 1024,
			'm' => $value * 1024 * 1024,
			'k' => $value * 1024,
			default => $value,
		};
	}

	/**
	 * エラーをログバッファに追加
	 */
	public static function logError(array $entry): void {
		self::rotateIfNeeded();
		$log = self::safeJsonRead(self::LOG_FILE, settings_dir());
		if (!isset($log['errors'])) $log['errors'] = [];

		$log['errors'][] = $entry;

		/* 14日超のエントリを削除 */
		self::purgeExpiredEntries($log['errors']);

		self::recordDailySummary($log, 'errors');
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

		self::rotateIfNeeded();
		$log = self::safeJsonRead(self::LOG_FILE, settings_dir());
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

		/* 14日超のエントリを削除 */
		self::purgeExpiredEntries($log['custom']);

		self::recordDailySummary($log, $category);
		json_write(self::LOG_FILE, $log, settings_dir());
	}

	/* ══════════════════════════════════════════════
	   14日間保持ポリシー
	   ══════════════════════════════════════════════ */

	/**
	 * 14日超のエントリを配列から削除（参照渡し）
	 */
	private static function purgeExpiredEntries(array &$entries): void {
		$cutoff = date('c', time() - self::LOG_RETENTION_DAYS * 86400);
		$entries = array_values(array_filter($entries, function (array $e) use ($cutoff) {
			return ($e['timestamp'] ?? '') >= $cutoff;
		}));
	}

	/**
	 * 全ログファイルの期限切れエントリを一括削除
	 */
	public static function purgeExpiredLogs(): void {
		$log = self::safeJsonRead(self::LOG_FILE, settings_dir());
		$changed = false;

		if (!empty($log['errors'])) {
			$before = count($log['errors']);
			self::purgeExpiredEntries($log['errors']);
			if (count($log['errors']) !== $before) $changed = true;
		}
		if (!empty($log['custom'])) {
			$before = count($log['custom']);
			self::purgeExpiredEntries($log['custom']);
			if (count($log['custom']) !== $before) $changed = true;
		}

		if ($changed) {
			json_write(self::LOG_FILE, $log, settings_dir());
		}
	}

	/* ══════════════════════════════════════════════
	   ログローテーション・破損検知
	   ══════════════════════════════════════════════ */

	/**
	 * ログファイルが上限サイズを超えていたらローテーション
	 */
	public static function rotateIfNeeded(): void {
		$path = settings_dir() . '/' . self::LOG_FILE;
		if (!file_exists($path)) return;
		$size = @filesize($path);
		if ($size === false || $size < self::MAX_LOG_SIZE_BYTES) return;

		/* 世代シフト: _3削除、_2→_3、_1→_2、current→_1 */
		for ($i = self::LOG_ARCHIVE_GENERATIONS; $i >= 1; $i--) {
			$dst = $path . '.' . $i;
			if ($i === self::LOG_ARCHIVE_GENERATIONS && file_exists($dst)) {
				@unlink($dst);
			}
			$src = ($i === 1) ? $path : $path . '.' . ($i - 1);
			if (file_exists($src)) {
				rename($src, $dst);
			}
		}

		/* 空のログファイルを作成（daily_summary は保持） */
		$oldLog = self::safeJsonRead(self::LOG_FILE . '.1', settings_dir());
		$newLog = ['errors' => [], 'custom' => []];
		if (!empty($oldLog['daily_summary'])) {
			$newLog['daily_summary'] = $oldLog['daily_summary'];
		}
		file_put_contents($path, json_encode($newLog, JSON_UNESCAPED_UNICODE), LOCK_EX);
	}

	/**
	 * JSON ファイルを安全に読み込み（破損検知・自動復旧）
	 */
	private static function safeJsonRead(string $file, string $dir): array {
		$path = $dir . '/' . $file;
		if (!file_exists($path)) return ['errors' => [], 'custom' => []];

		$content = @file_get_contents($path);
		if ($content === false) return ['errors' => [], 'custom' => []];

		$data = json_decode($content, true);
		if (json_last_error() !== JSON_ERROR_NONE) {
			error_log('DiagnosticEngine: JSON parse error in ' . $file . ': ' . json_last_error_msg());
			/* 破損ファイルをバックアップ */
			@rename($path, $path . '.corrupt.' . time());
			return ['errors' => [], 'custom' => []];
		}
		return is_array($data) ? $data : ['errors' => [], 'custom' => []];
	}

	/* ══════════════════════════════════════════════
	   エラートレンド追跡
	   ══════════════════════════════════════════════ */

	/**
	 * 日別サマリーにカウントを記録（12デバッグカテゴリ対応）
	 */
	private static function recordDailySummary(array &$log, string $type): void {
		$today = date('Y-m-d');
		if (!isset($log['daily_summary'])) $log['daily_summary'] = [];
		if (!isset($log['daily_summary'][$today])) {
			$defaults = ['errors' => 0, 'security' => 0, 'engine' => 0, 'other' => 0];
			foreach (self::DEBUG_CATEGORIES as $cat) {
				$defaults['debug_' . $cat] = 0;
			}
			$log['daily_summary'][$today] = $defaults;
		}

		/* 従来カテゴリのカウンター */
		$key = match ($type) {
			'errors'   => 'errors',
			'security' => 'security',
			'engine'   => 'engine',
			default    => 'other',
		};
		$log['daily_summary'][$today][$key] = ($log['daily_summary'][$today][$key] ?? 0) + 1;

		/* デバッグカテゴリのカウンター */
		if (in_array($type, self::DEBUG_CATEGORIES, true)) {
			$debugKey = 'debug_' . $type;
			$log['daily_summary'][$today][$debugKey] = ($log['daily_summary'][$today][$debugKey] ?? 0) + 1;
		}

		/* 30日超のエントリを削除 */
		$cutoff = date('Y-m-d', strtotime('-30 days'));
		foreach (array_keys($log['daily_summary']) as $date) {
			if ($date < $cutoff) {
				unset($log['daily_summary'][$date]);
			}
		}
	}

	/**
	 * 直近N日分のトレンドデータを返す
	 */
	public static function getTrends(int $days = 7): array {
		$log = self::safeJsonRead(self::LOG_FILE, settings_dir());
		$summary = $log['daily_summary'] ?? [];
		$result = [];

		for ($i = $days - 1; $i >= 0; $i--) {
			$date = date('Y-m-d', strtotime("-{$i} days"));
			$result[] = [
				'date'   => $date,
				'errors' => $summary[$date]['errors'] ?? 0,
				'security' => $summary[$date]['security'] ?? 0,
				'engine' => $summary[$date]['engine'] ?? 0,
				'other'  => $summary[$date]['other'] ?? 0,
				'total'  => ($summary[$date]['errors'] ?? 0)
				          + ($summary[$date]['security'] ?? 0)
				          + ($summary[$date]['engine'] ?? 0)
				          + ($summary[$date]['other'] ?? 0),
			];
		}

		/* トレンド方向判定: 直近3日平均 vs 前4日平均 */
		$recent = array_slice($result, -3);
		$older  = array_slice($result, 0, max($days - 3, 1));
		$recentAvg = count($recent) > 0 ? array_sum(array_column($recent, 'total')) / count($recent) : 0;
		$olderAvg  = count($older) > 0 ? array_sum(array_column($older, 'total')) / count($older) : 0;

		$direction = 'stable';
		if ($olderAvg > 0 && $recentAvg > $olderAvg * 1.5) {
			$direction = 'increasing';
		} elseif ($recentAvg > 0 && $olderAvg > $recentAvg * 1.5) {
			$direction = 'decreasing';
		}

		return [
			'days'            => $result,
			'trend_direction' => $direction,
			'spike_detected'  => self::detectSpike($summary),
		];
	}

	/**
	 * エラー急増を検知（当日 > 7日平均×3）
	 */
	private static function detectSpike(array $summary = []): bool {
		if (empty($summary)) {
			$log = self::safeJsonRead(self::LOG_FILE, settings_dir());
			$summary = $log['daily_summary'] ?? [];
		}
		$today = date('Y-m-d');
		$todayTotal = ($summary[$today]['errors'] ?? 0) + ($summary[$today]['security'] ?? 0)
		            + ($summary[$today]['engine'] ?? 0) + ($summary[$today]['other'] ?? 0);
		if ($todayTotal === 0) return false;

		/* 過去7日間の平均 */
		$sum = 0;
		$count = 0;
		for ($i = 1; $i <= 7; $i++) {
			$date = date('Y-m-d', strtotime("-{$i} days"));
			if (isset($summary[$date])) {
				$sum += ($summary[$date]['errors'] ?? 0) + ($summary[$date]['security'] ?? 0)
				      + ($summary[$date]['engine'] ?? 0) + ($summary[$date]['other'] ?? 0);
				$count++;
			}
		}
		$avg = $count > 0 ? $sum / $count : 0;
		return $avg > 0 && $todayTotal > $avg * 3;
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
		$debugCategorySummary = array_fill_keys(self::DEBUG_CATEGORIES, 0);
		foreach (($log['errors'] ?? []) as $e) {
			$type = $e['type'] ?? 'unknown';
			$errorSummary[$type] = ($errorSummary[$type] ?? 0) + 1;
			$cat = $e['debug_category'] ?? 'runtime';
			if (isset($debugCategorySummary[$cat])) {
				$debugCategorySummary[$cat]++;
			}
		}

		/* 有効エンジン一覧（環境依存カテゴリ） */
		$engines = [];
		foreach (self::ENGINE_CLASSES as $cls) {
			if (class_exists($cls)) $engines[] = $cls;
		}

		return [
			'install_id'             => self::getInstallId(),
			'ap_version'             => defined('AP_VERSION') ? AP_VERSION : 'unknown',
			'php_version'            => PHP_VERSION,
			'os'                     => PHP_OS_FAMILY,
			'sapi'                   => PHP_SAPI,
			'engines'                => $engines,
			'error_count'            => $errorCount,
			'error_summary'          => $errorSummary,
			'debug_category_summary' => $debugCategorySummary,
			'custom_log_count'       => $customLogCount,
			'security_summary'       => self::getSecuritySummary(),
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

		/* メモリ・パフォーマンス情報 */
		$perf = [
			'memory_peak'       => memory_get_peak_usage(true),
			'memory_peak_human' => self::humanSize(memory_get_peak_usage(true)),
			'disk_free'         => @disk_free_space('.') ?: 0,
		];

		/* エンジン別実行時間 */
		$engineTimings = self::getEngineTimings();

		return [
			'recent_errors'  => $recentErrors,
			'recent_logs'    => $recentLogs,
			'performance'    => $perf,
			'timings'        => self::getTimings(),
			'engine_timings' => $engineTimings['engines'] ?? [],
			'security'       => self::getSecuritySummary(),
		];
	}

	/**
	 * Level 3: debug — 12カテゴリ別集計・スタックトレース・エンジン別実行時間・詳細設定
	 */
	public static function collectDebug(): array {
		$log = json_read(self::LOG_FILE, settings_dir());

		/* 直近20件のフルエラー（ファイルパス含む） */
		$fullErrors = array_slice($log['errors'] ?? [], -20);

		/* 12カテゴリ別エラー集計 */
		$categoryBreakdown = array_fill_keys(self::DEBUG_CATEGORIES, 0);
		foreach (($log['errors'] ?? []) as $e) {
			$cat = $e['debug_category'] ?? 'runtime';
			if (isset($categoryBreakdown[$cat])) {
				$categoryBreakdown[$cat]++;
			} else {
				$categoryBreakdown['runtime']++;
			}
		}
		/* カスタムログのカテゴリも集計 */
		foreach (($log['custom'] ?? []) as $c) {
			$ctx = $c['context'] ?? [];
			$cat = $ctx['debug_category'] ?? ($c['category'] ?? 'runtime');
			if (isset($categoryBreakdown[$cat])) {
				$categoryBreakdown[$cat]++;
			}
		}

		/* カテゴリ別直近エラー（各カテゴリ最新5件） */
		$categoryRecent = [];
		foreach (self::DEBUG_CATEGORIES as $cat) {
			$categoryRecent[$cat] = [];
		}
		foreach (array_reverse($log['errors'] ?? []) as $e) {
			$cat = $e['debug_category'] ?? 'runtime';
			if (isset($categoryRecent[$cat]) && count($categoryRecent[$cat]) < 5) {
				$categoryRecent[$cat][] = $e;
			}
		}

		/* PHP 設定の主要項目（環境依存カテゴリ） */
		$phpConfig = [
			'max_execution_time'  => ini_get('max_execution_time'),
			'memory_limit'        => ini_get('memory_limit'),
			'upload_max_filesize' => ini_get('upload_max_filesize'),
			'post_max_size'       => ini_get('post_max_size'),
			'display_errors'      => ini_get('display_errors'),
			'error_reporting'     => error_reporting(),
			'session.gc_maxlifetime' => ini_get('session.gc_maxlifetime'),
		];

		/* 環境依存情報 */
		$envInfo = [
			'php_version'     => PHP_VERSION,
			'php_version_id'  => PHP_VERSION_ID,
			'os'              => PHP_OS,
			'os_family'       => PHP_OS_FAMILY,
			'sapi'            => PHP_SAPI,
			'extensions'      => get_loaded_extensions(),
			'zend_version'    => zend_version(),
			'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
		];

		/* メモリ詳細 */
		$memoryInfo = [
			'current'       => memory_get_usage(true),
			'current_human' => self::humanSize(memory_get_usage(true)),
			'peak'          => memory_get_peak_usage(true),
			'peak_human'    => self::humanSize(memory_get_peak_usage(true)),
			'limit'         => ini_get('memory_limit'),
			'limit_bytes'   => self::parseMemoryLimit(ini_get('memory_limit')),
		];
		$limitBytes = $memoryInfo['limit_bytes'];
		if ($limitBytes > 0) {
			$memoryInfo['usage_ratio'] = round(memory_get_usage(true) / $limitBytes, 3);
		}

		/* エンジン別実行時間（全詳細） */
		$engineTimings = self::getEngineTimings();

		/* 蓄積されたスタックトレース */
		$traces = self::getCapturedTraces();

		/* スタックトレース付きエラーの抽出（直近20件） */
		$tracedErrors = [];
		foreach (array_reverse($log['errors'] ?? []) as $e) {
			if (!empty($e['stack_trace'])) {
				$tracedErrors[] = $e;
				if (count($tracedErrors) >= 20) break;
			}
		}

		return [
			'full_errors'          => $fullErrors,
			'category_breakdown'   => $categoryBreakdown,
			'category_recent'      => $categoryRecent,
			'traced_errors'        => $tracedErrors,
			'captured_traces'      => $traces,
			'engine_timings'       => $engineTimings,
			'php_config'           => $phpConfig,
			'environment'          => $envInfo,
			'memory_detail'        => $memoryInfo,
			'debug_categories'     => self::DEBUG_CATEGORIES,
		];
	}

	/* ══════════════════════════════════════════════
	   送信
	   ══════════════════════════════════════════════ */

	/**
	 * リアルタイム送信: 毎リクエストで未送信ログを開発元へ送信
	 * ログはサーバ内に14日間保持（送信後も削除しない）
	 */
	public static function maybeSend(): void {
		if (!self::isEnabled()) return;

		/* 期限切れログの自動削除 */
		self::purgeExpiredLogs();

		$config = self::loadConfig();

		/* サーキットブレーカーチェック */
		$breakerUntil = $config['circuit_breaker_until'] ?? 0;
		if ($breakerUntil > 0 && time() < $breakerUntil) {
			return; /* ブレーカー発動中 */
		}

		/* 送信間隔チェック（INTERVAL=0 でリアルタイム） */
		$lastSent = $config['last_sent'] ?? '';
		$interval = $config['send_interval'] ?? self::INTERVAL;
		if ($interval > 0 && $lastSent !== '') {
			$lastTime = strtotime($lastSent);
			if ($lastTime !== false && (time() - $lastTime) < $interval) {
				return;
			}
		}

		/* 未送信のログエントリのみを抽出して送信 */
		$data = self::collectWithUnsent($lastSent);

		/* 新しいログがなければ環境データのみ送信（1日1回） */
		$hasNewLogs = !empty($data['unsent_errors']) || !empty($data['unsent_logs']);
		if (!$hasNewLogs) {
			/* 最終環境送信から24時間未満なら送信スキップ */
			$lastEnv = $config['last_env_sent'] ?? '';
			if ($lastEnv !== '') {
				$lastEnvTime = strtotime($lastEnv);
				if ($lastEnvTime !== false && (time() - $lastEnvTime) < 86400) {
					return;
				}
			}
		}

		if (self::send($data)) {
			$config = self::loadConfig();
			$config['last_sent'] = date('c');
			$config['consecutive_failures'] = 0;
			$config['circuit_breaker_until'] = 0;
			if (!$hasNewLogs) {
				$config['last_env_sent'] = date('c');
			}
			self::saveConfig($config);
			/* ログは削除しない — 14日間サーバ内に保持 */
		} else {
			/* 連続失敗カウント → サーキットブレーカー */
			$config = self::loadConfig();
			$failures = ($config['consecutive_failures'] ?? 0) + 1;
			$config['consecutive_failures'] = $failures;
			if ($failures >= self::CIRCUIT_BREAKER_THRESHOLD) {
				$config['circuit_breaker_until'] = time() + self::CIRCUIT_BREAKER_DURATION;
				error_log('DiagnosticEngine: サーキットブレーカー発動（' . $failures . '回連続失敗）。24時間送信停止。');
			}
			self::saveConfig($config);
		}
	}

	/**
	 * 未送信分を含むデータを収集
	 */
	private static function collectWithUnsent(string $lastSent): array {
		$data = self::collect();
		$log = self::safeJsonRead(self::LOG_FILE, settings_dir());

		/* 前回送信以降のエントリを抽出 */
		$unsentErrors = [];
		$unsentLogs   = [];
		if ($lastSent !== '') {
			foreach (($log['errors'] ?? []) as $e) {
				if (($e['timestamp'] ?? '') > $lastSent) {
					$unsentErrors[] = $e;
				}
			}
			foreach (($log['custom'] ?? []) as $c) {
				if (($c['timestamp'] ?? '') > $lastSent) {
					$unsentLogs[] = $c;
				}
			}
		} else {
			/* 初回送信: 全エントリ */
			$unsentErrors = $log['errors'] ?? [];
			$unsentLogs   = $log['custom'] ?? [];
		}

		$data['unsent_errors']     = $unsentErrors;
		$data['unsent_logs']       = $unsentLogs;
		$data['total_errors_held'] = count($log['errors'] ?? []);
		$data['total_logs_held']   = count($log['custom'] ?? []);
		$data['retention_days']    = self::LOG_RETENTION_DAYS;

		return $data;
	}

	/**
	 * 診断データを開発元へ送信（指数バックオフリトライ付き）
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

		for ($attempt = 0; $attempt <= self::RETRY_MAX; $attempt++) {
			if ($attempt > 0) {
				$delay = self::RETRY_BACKOFF[$attempt - 1] ?? 4;
				sleep($delay);
			}

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
			$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);

			if ($httpCode >= 200 && $httpCode < 300) {
				return true;
			}

			/* リトライ対象外のコードは即座に失敗 */
			if (!in_array($httpCode, self::RETRYABLE_CODES, true)) {
				error_log("DiagnosticEngine: 送信失敗 (HTTP {$httpCode}) — リトライ対象外");
				return false;
			}

			error_log("DiagnosticEngine: 送信失敗 (HTTP {$httpCode}) — リトライ " . ($attempt + 1) . '/' . self::RETRY_MAX);
		}

		return false;
	}

	/**
	 * 送信後の処理: ログは14日間保持のため削除しない。
	 * 期限切れエントリのみ削除。
	 */
	private static function clearSentLogs(): void {
		self::purgeExpiredLogs();
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
			'trends'          => self::getTrends(7),
		];
	}

	/**
	 * セキュリティイベントのサマリーを取得
	 */
	public static function getSecuritySummary(): array {
		$log = json_read(self::LOG_FILE, settings_dir());
		$custom = $log['custom'] ?? [];
		$summary = [
			'login_failure'  => 0,
			'lockout'        => 0,
			'rate_limit'     => 0,
			'ssrf_blocked'   => 0,
		];
		foreach ($custom as $entry) {
			if (($entry['category'] ?? '') !== 'security') continue;
			$msg = $entry['message'] ?? '';
			if (str_contains($msg, 'ログイン失敗')) $summary['login_failure']++;
			elseif (str_contains($msg, 'ロックアウト')) $summary['lockout']++;
			elseif (str_contains($msg, 'レート制限')) $summary['rate_limit']++;
			elseif (str_contains($msg, 'SSRF')) $summary['ssrf_blocked']++;
		}
		return $summary;
	}

	/**
	 * ヘルスチェック結果を返す
	 */
	public static function healthCheck(bool $detailed = false): array {
		$config = self::loadConfig();
		$log = json_read(self::LOG_FILE, settings_dir());

		/* 直近24時間のエラー件数 */
		$errors24h = 0;
		$cutoff = date('c', time() - 86400);
		foreach (($log['errors'] ?? []) as $e) {
			if (($e['timestamp'] ?? '') >= $cutoff) $errors24h++;
		}

		$diskFree = @disk_free_space('.');
		$status = 'ok';
		if ($errors24h > 50) $status = 'critical';
		elseif ($errors24h > 10) $status = 'warning';
		if ($diskFree !== false && $diskFree < 100 * 1024 * 1024) $status = 'critical';

		/* エラー急増検知 */
		if ($status !== 'critical' && self::detectSpike($log['daily_summary'] ?? [])) {
			$status = 'warning';
		}

		/* ログファイルサイズ */
		$logPath = settings_dir() . '/' . self::LOG_FILE;
		$logSize = file_exists($logPath) ? @filesize($logPath) : 0;

		$result = [
			'status'       => $status,
			'version'      => defined('AP_VERSION') ? AP_VERSION : 'unknown',
			'php'          => PHP_VERSION,
			'uptime_check' => true,
			'diagnostics'  => [
				'errors_24h'           => $errors24h,
				'disk_free_mb'         => $diskFree !== false ? round($diskFree / 1024 / 1024) : null,
				'memory_peak_mb'       => round(memory_get_peak_usage(true) / 1024 / 1024, 1),
				'last_diagnostic_sent' => $config['last_sent'] ?? '',
				'log_file_size_kb'     => $logSize !== false ? round($logSize / 1024, 1) : 0,
			],
		];

		if ($detailed) {
			$result['security'] = self::getSecuritySummary();
			$result['timings']  = self::getTimings();
			$result['diagnostics']['error_count'] = count($log['errors'] ?? []);
			$result['diagnostics']['log_count']   = count($log['custom'] ?? []);
		}

		return $result;
	}

	/**
	 * 全ログを取得（ダッシュボード詳細表示用）
	 */
	public static function getAllLogs(): array {
		return json_read(self::LOG_FILE, settings_dir());
	}

	/**
	 * ログをクリア（daily_summary は保持）
	 */
	public static function clearLogs(): void {
		$log = self::safeJsonRead(self::LOG_FILE, settings_dir());
		$newLog = ['errors' => [], 'custom' => []];
		if (!empty($log['daily_summary'])) {
			$newLog['daily_summary'] = $log['daily_summary'];
		}
		json_write(self::LOG_FILE, $newLog, settings_dir());
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
			'diag_get_summary', 'diag_health',
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
			'diag_health'      => self::handleHealth(),
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
		$config = self::loadConfig();
		$data = self::collectWithUnsent($config['last_sent'] ?? '');
		$success = self::send($data);
		if ($success) {
			$config = self::loadConfig();
			$config['last_sent'] = date('c');
			$config['consecutive_failures'] = 0;
			$config['circuit_breaker_until'] = 0;
			self::saveConfig($config);
			/* ログは14日間保持 — 削除しない */
			self::purgeExpiredLogs();
			AdminEngine::logActivity('診断データを手動送信');
			self::jsonOk(['message' => '送信しました（ログは14日間保持）', 'sent_at' => date('c')]);
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
		$summary['security'] = self::getSecuritySummary();
		$summary['timings'] = self::getTimings();
		$summary['circuit_breaker'] = ($config['circuit_breaker_until'] ?? 0) > time();
		$summary['retention_days'] = self::LOG_RETENTION_DAYS;
		$summary['realtime_send'] = (self::INTERVAL === 0);
		self::jsonOk($summary);
	}

	private static function handleHealth(): never {
		$health = self::healthCheck(true);
		self::jsonOk($health);
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
