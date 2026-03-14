<?php
/**
 * Adlaire Infrastructure Services (AIS) - System Module
 *
 * AIS = Adlaire Infrastructure Services
 *
 * システム運用に必要な基盤サービスを提供するモジュール。
 * CacheStore（ファイルキャッシュ）、AppLogger（PSR-3風ロガー）、
 * DiagnosticsCollector（診断情報収集）、HealthMonitor（ヘルスチェック）を含む。
 *
 * @package AIS
 * @version 1.0.0
 * @license Adlaire License Ver.2.0
 */

namespace AIS\System;

// ============================================================================
// CacheStore - ファイルベースキャッシュシステム
// ============================================================================

/**
 * ファイルベースのキャッシュストア。
 *
 * CacheEngine を汎用化し、任意のアプリケーションで再利用可能にしたもの。
 * TTL（有効期限）管理、統計情報取得、remember パターンに対応。
 */
class CacheStore {

    /** @var string キャッシュディレクトリ */
    private string $cacheDir;

    /**
     * コンストラクタ
     *
     * キャッシュディレクトリが存在しない場合は自動作成する。
     *
     * @param string $cacheDir キャッシュファイルの保存先ディレクトリ
     */
    public function __construct(string $cacheDir) {
        $this->cacheDir = rtrim($cacheDir, '/\\');

        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    /**
     * キャッシュから値を取得する
     *
     * 存在しないキー、または有効期限切れの場合は null を返す。
     *
     * @param string $key キャッシュキー
     * @return mixed キャッシュ値、または null
     */
    public function get(string $key): mixed {
        $path = $this->filePath($key);

        if (!is_file($path)) {
            return null;
        }

        $content = file_get_contents($path);
        if ($content === false) {
            return null;
        }

        $data = @unserialize($content);
        if ($data === false || !is_array($data)) {
            return null;
        }

        /* TTL チェック */
        if ($data['expires_at'] > 0 && $data['expires_at'] < time()) {
            $this->delete($key);
            return null;
        }

        return $data['value'];
    }

    /**
     * キャッシュに値を保存する
     *
     * @param string $key   キャッシュキー
     * @param mixed  $value 保存する値（シリアライズ可能な型）
     * @param int    $ttl   有効期限（秒）。0 の場合は無期限
     */
    public function set(string $key, mixed $value, int $ttl = 3600): void {
        $path = $this->filePath($key);

        $data = [
            'value'      => $value,
            'created_at' => time(),
            'expires_at' => $ttl > 0 ? time() + $ttl : 0,
        ];

        file_put_contents($path, serialize($data), LOCK_EX);
    }

    /**
     * 指定キーのキャッシュを削除する
     *
     * @param string $key キャッシュキー
     * @return bool 削除に成功した場合 true
     */
    public function delete(string $key): bool {
        $path = $this->filePath($key);

        if (is_file($path)) {
            return @unlink($path);
        }

        return false;
    }

    /**
     * 指定キーのキャッシュが存在するか確認する
     *
     * 有効期限切れのキャッシュは存在しないものとして扱う。
     *
     * @param string $key キャッシュキー
     * @return bool 有効なキャッシュが存在する場合 true
     */
    public function has(string $key): bool {
        return $this->get($key) !== null;
    }

    /**
     * 全キャッシュをクリアする
     *
     * キャッシュディレクトリ内の全 .cache ファイルを削除する。
     */
    public function clear(): void {
        $files = glob($this->cacheDir . '/*.cache');
        if (!is_array($files)) {
            return;
        }

        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    /**
     * キャッシュの統計情報を取得する
     *
     * @return array{files: int, total_size: int, total_size_human: string}
     */
    public function getStats(): array {
        $files = glob($this->cacheDir . '/*.cache');
        if (!is_array($files)) {
            return ['files' => 0, 'total_size' => 0, 'total_size_human' => '0 B'];
        }

        $totalSize = 0;
        foreach ($files as $file) {
            $totalSize += filesize($file) ?: 0;
        }

        return [
            'files'           => count($files),
            'total_size'      => $totalSize,
            'total_size_human' => $this->humanFileSize($totalSize),
        ];
    }

    /**
     * キャッシュの取得・生成を一体化する remember パターン
     *
     * キャッシュが存在すればそれを返し、なければコールバックで生成して保存する。
     *
     * @param string   $key      キャッシュキー
     * @param int      $ttl      有効期限（秒）
     * @param callable $callback 値を生成するコールバック (): mixed
     * @return mixed キャッシュ値またはコールバックの戻り値
     */
    public function remember(string $key, int $ttl, callable $callback): mixed {
        $cached = $this->get($key);
        if ($cached !== null) {
            return $cached;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }

    /**
     * キャッシュキーからファイルパスを生成する
     *
     * @param string $key キャッシュキー
     * @return string ファイルパス
     */
    private function filePath(string $key): string {
        return $this->cacheDir . '/' . md5($key) . '.cache';
    }

    /**
     * バイト数を人間可読な形式に変換する
     *
     * @param int $bytes バイト数
     * @return string 人間可読な文字列
     */
    private function humanFileSize(int $bytes): string {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1_048_576) return round($bytes / 1024, 1) . ' KB';
        if ($bytes < 1_073_741_824) return round($bytes / 1_048_576, 1) . ' MB';
        return round($bytes / 1_073_741_824, 1) . ' GB';
    }
}

// ============================================================================
// AppLogger - PSR-3 インスパイアのログシステム
// ============================================================================

/**
 * PSR-3 に準拠したインターフェースを持つファイルベースのロガー。
 *
 * APF の Logger および DiagnosticEngine のログ機能を汎用化。
 * レベルフィルタリング、ログローテーション、直近ログ取得に対応。
 */
class AppLogger {

    /** @var string ログディレクトリ */
    private string $logDir;

    /** @var string 現在のログレベル */
    private string $level;

    /** @var array<string> ログレベルの順序定義（低 → 高） */
    private const LEVELS = ['debug', 'info', 'warning', 'error', 'critical'];

    /** @var int ローテーション閾値（バイト）。デフォルト 5MB */
    private const MAX_LOG_SIZE = 5_242_880;

    /** @var int ローテーション世代数 */
    private const MAX_GENERATIONS = 5;

    /**
     * コンストラクタ
     *
     * @param string $logDir ログファイルの保存先ディレクトリ
     * @param string $level  最低ログレベル（これ以上のレベルのみ記録）
     */
    public function __construct(string $logDir, string $level = 'info') {
        $this->logDir = rtrim($logDir, '/\\');
        $this->level = in_array($level, self::LEVELS, true) ? $level : 'info';

        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0755, true);
        }
    }

    /**
     * デバッグレベルのログを記録する
     *
     * @param string              $msg メッセージ
     * @param array<string,mixed> $ctx コンテキストデータ
     */
    public function debug(string $msg, array $ctx = []): void {
        $this->log('debug', $msg, $ctx);
    }

    /**
     * 情報レベルのログを記録する
     *
     * @param string              $msg メッセージ
     * @param array<string,mixed> $ctx コンテキストデータ
     */
    public function info(string $msg, array $ctx = []): void {
        $this->log('info', $msg, $ctx);
    }

    /**
     * 警告レベルのログを記録する
     *
     * @param string              $msg メッセージ
     * @param array<string,mixed> $ctx コンテキストデータ
     */
    public function warning(string $msg, array $ctx = []): void {
        $this->log('warning', $msg, $ctx);
    }

    /**
     * エラーレベルのログを記録する
     *
     * @param string              $msg メッセージ
     * @param array<string,mixed> $ctx コンテキストデータ
     */
    public function error(string $msg, array $ctx = []): void {
        $this->log('error', $msg, $ctx);
    }

    /**
     * 致命的エラーレベルのログを記録する
     *
     * @param string              $msg メッセージ
     * @param array<string,mixed> $ctx コンテキストデータ
     */
    public function critical(string $msg, array $ctx = []): void {
        $this->log('critical', $msg, $ctx);
    }

    /**
     * 直近のログエントリを取得する
     *
     * ログファイルの末尾から指定行数を返す。
     *
     * @param int $limit 取得する最大行数
     * @return array<string> ログ行の配列（新しい順）
     */
    public function getRecent(int $limit = 50): array {
        $logFile = $this->currentLogFile();
        if (!is_file($logFile)) {
            return [];
        }

        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return [];
        }

        $recent = array_slice($lines, -$limit);
        return array_reverse($recent);
    }

    /**
     * ログファイルのローテーションを実行する
     *
     * 現在のログファイルが MAX_LOG_SIZE を超えている場合、
     * 世代管理しながらファイルを回転させる。
     */
    public function rotate(): void {
        $logFile = $this->currentLogFile();
        if (!is_file($logFile)) {
            return;
        }

        $size = filesize($logFile);
        if ($size === false || $size < self::MAX_LOG_SIZE) {
            return;
        }

        /* 古い世代を削除 */
        for ($i = self::MAX_GENERATIONS; $i >= 1; $i--) {
            $older = $logFile . '.' . $i;
            $newer = $i === 1 ? $logFile : $logFile . '.' . ($i - 1);

            if ($i === self::MAX_GENERATIONS && is_file($older)) {
                @unlink($older);
            }

            if (is_file($newer)) {
                @rename($newer, $logFile . '.' . $i);
            }
        }
    }

    /**
     * ログレベルを変更する
     *
     * @param string $level 新しいログレベル
     */
    public function setLevel(string $level): void {
        if (in_array($level, self::LEVELS, true)) {
            $this->level = $level;
        }
    }

    /**
     * ログエントリを書き込む
     *
     * @param string              $level ログレベル
     * @param string              $msg   メッセージ
     * @param array<string,mixed> $ctx   コンテキストデータ
     */
    private function log(string $level, string $msg, array $ctx = []): void {
        if (!$this->shouldLog($level)) {
            return;
        }

        /* ローテーション確認 */
        $this->rotate();

        $timestamp = date('Y-m-d H:i:s');
        $contextStr = empty($ctx) ? '' : ' ' . json_encode($ctx, JSON_UNESCAPED_UNICODE);
        $entry = "[{$timestamp}] [{$level}] {$msg}{$contextStr}" . PHP_EOL;

        file_put_contents($this->currentLogFile(), $entry, FILE_APPEND | LOCK_EX);
    }

    /**
     * 指定レベルのログを記録すべきか判定する
     *
     * @param string $level 判定対象のログレベル
     * @return bool 記録すべき場合 true
     */
    private function shouldLog(string $level): bool {
        $currentIndex = array_search($this->level, self::LEVELS, true);
        $requestedIndex = array_search($level, self::LEVELS, true);

        if ($currentIndex === false || $requestedIndex === false) {
            return false;
        }

        return $requestedIndex >= $currentIndex;
    }

    /**
     * 現在のログファイルパスを取得する
     *
     * 日付ベースのファイル名を使用する。
     *
     * @return string ログファイルのパス
     */
    private function currentLogFile(): string {
        return $this->logDir . '/app-' . date('Y-m-d') . '.log';
    }
}

// ============================================================================
// DiagnosticsCollector - システム診断情報収集
// ============================================================================

/**
 * システムの診断情報を収集するコレクター。
 *
 * DiagnosticEngine を汎用化し、任意のアプリケーションで利用可能にしたもの。
 * カテゴリ別ログ、タイマー計測、ヘルスチェック、エラーハンドラー登録に対応。
 */
class DiagnosticsCollector {

    /** @var string 診断データの保存先ディレクトリ */
    private string $dataDir;

    /** @var bool 診断収集の有効/無効 */
    private bool $enabled = false;

    /** @var array<array{category: string, message: string, data: array, time: float, memory: int}> 診断ログバッファ */
    private array $logs = [];

    /** @var array<string, float> 実行中タイマー（名前 => 開始時刻） */
    private array $timers = [];

    /** @var array<string, float> 完了タイマーの結果（名前 => 経過ミリ秒） */
    private array $timings = [];

    /** @var float コレクター起動時刻（ナノ秒） */
    private float $startTime;

    /**
     * コンストラクタ
     *
     * @param string $dataDir 診断データの保存先ディレクトリ
     */
    public function __construct(string $dataDir) {
        $this->dataDir = rtrim($dataDir, '/\\');
        $this->startTime = hrtime(true);

        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0755, true);
        }
    }

    /**
     * 診断収集を有効化する
     */
    public function enable(): void {
        $this->enabled = true;
    }

    /**
     * 診断収集を無効化する
     */
    public function disable(): void {
        $this->enabled = false;
    }

    /**
     * 診断収集が有効かどうかを返す
     *
     * @return bool 有効な場合 true
     */
    public function isEnabled(): bool {
        return $this->enabled;
    }

    /**
     * 診断ログを記録する
     *
     * 無効状態でも呼び出し自体は許可されるが記録しない。
     *
     * @param string              $category カテゴリ名（例: 'performance', 'security'）
     * @param string              $message  ログメッセージ
     * @param array<string,mixed> $data     付属データ
     */
    public function log(string $category, string $message, array $data = []): void {
        if (!$this->enabled) {
            return;
        }

        $this->logs[] = [
            'category' => $category,
            'message'  => $message,
            'data'     => $data,
            'time'     => (hrtime(true) - $this->startTime) / 1_000_000,
            'memory'   => memory_get_usage(true),
        ];
    }

    /**
     * 名前付きタイマーを開始する
     *
     * @param string $name タイマー名
     */
    public function startTimer(string $name): void {
        if (!$this->enabled) {
            return;
        }

        $this->timers[$name] = hrtime(true);
    }

    /**
     * 名前付きタイマーを停止し、経過時間を返す
     *
     * @param string $name タイマー名
     * @return float 経過ミリ秒（タイマーが見つからない場合は 0.0）
     */
    public function stopTimer(string $name): float {
        if (!$this->enabled || !isset($this->timers[$name])) {
            return 0.0;
        }

        $elapsed = (hrtime(true) - $this->timers[$name]) / 1_000_000;
        $this->timings[$name] = $elapsed;
        unset($this->timers[$name]);

        $this->log('timer', "{$name}: {$elapsed}ms", ['elapsed_ms' => $elapsed]);

        return $elapsed;
    }

    /**
     * 完了済みタイマーの計測結果を取得する
     *
     * @return array<string, float> タイマー名 => 経過ミリ秒
     */
    public function getTimings(): array {
        return $this->timings;
    }

    /**
     * システムヘルスチェックを実行する
     *
     * PHP バージョン、ディスク空き容量、メモリ使用状況、
     * 主要拡張モジュールの有無を確認する。
     *
     * @return array{php_version: string, disk_free: int, disk_total: int,
     *               memory_usage: int, memory_peak: int, memory_limit: string,
     *               extensions: array<string, bool>, status: string}
     */
    public function healthCheck(): array {
        $diskFree = @disk_free_space($this->dataDir);
        $diskTotal = @disk_total_space($this->dataDir);

        $requiredExtensions = ['json', 'mbstring', 'openssl', 'curl', 'fileinfo'];
        $extensions = [];
        foreach ($requiredExtensions as $ext) {
            $extensions[$ext] = extension_loaded($ext);
        }

        $allExtLoaded = !in_array(false, $extensions, true);
        $diskOk = $diskFree !== false && $diskFree > 104_857_600; /* 100MB */

        return [
            'php_version'  => PHP_VERSION,
            'disk_free'    => $diskFree !== false ? (int)$diskFree : -1,
            'disk_total'   => $diskTotal !== false ? (int)$diskTotal : -1,
            'memory_usage' => memory_get_usage(true),
            'memory_peak'  => memory_get_peak_usage(true),
            'memory_limit' => ini_get('memory_limit') ?: 'unknown',
            'extensions'   => $extensions,
            'status'       => ($allExtLoaded && $diskOk) ? 'healthy' : 'degraded',
        ];
    }

    /**
     * 包括的な診断レポートを生成する
     *
     * 収集されたログ、タイマー結果、ヘルスチェック結果を統合して返す。
     *
     * @return array{total_time_ms: float, memory_peak: int,
     *               logs: array, timings: array, health: array,
     *               log_count: int, generated_at: string}
     */
    public function getReport(): array {
        return [
            'total_time_ms' => (hrtime(true) - $this->startTime) / 1_000_000,
            'memory_peak'   => memory_get_peak_usage(true),
            'logs'          => $this->logs,
            'timings'       => $this->timings,
            'health'        => $this->healthCheck(),
            'log_count'     => count($this->logs),
            'generated_at'  => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * グローバルエラーハンドラーを登録する
     *
     * PHP エラーを診断ログとして自動収集する。
     * 既存のエラーハンドラーは維持され、チェーン呼び出しされる。
     */
    public function registerErrorHandler(): void {
        $collector = $this;
        $previousHandler = set_error_handler(
            function (int $errno, string $errstr, string $errfile, int $errline) use ($collector, &$previousHandler): bool {
                $collector->log('runtime', $errstr, [
                    'errno' => $errno,
                    'file'  => basename($errfile),
                    'line'  => $errline,
                ]);

                /* 以前のハンドラーを呼び出す */
                if (is_callable($previousHandler)) {
                    return $previousHandler($errno, $errstr, $errfile, $errline);
                }

                return false; /* PHP 標準エラーハンドラーに委譲 */
            }
        );

        /* 未捕捉例外ハンドラーも登録 */
        $previousExHandler = set_exception_handler(
            function (\Throwable $e) use ($collector, &$previousExHandler): void {
                $collector->log('runtime', '未捕捉例外: ' . $e->getMessage(), [
                    'class' => get_class($e),
                    'file'  => basename($e->getFile()),
                    'line'  => $e->getLine(),
                    'code'  => $e->getCode(),
                ]);

                /* レポートをファイルに保存 */
                $reportPath = $collector->getDataDir() . '/crash-' . date('Ymd-His') . '.json';
                @file_put_contents(
                    $reportPath,
                    json_encode($collector->getReport(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                );

                if (is_callable($previousExHandler)) {
                    $previousExHandler($e);
                }
            }
        );
    }

    /**
     * データディレクトリのパスを取得する
     *
     * @return string データディレクトリのパス
     */
    public function getDataDir(): string {
        return $this->dataDir;
    }
}

// ============================================================================
// HealthMonitor - システムヘルスモニタリング
// ============================================================================

/**
 * システムの健全性を確認するためのモニタリングクラス。
 *
 * ディスク容量、メモリ使用量、PHP 環境、ディレクトリ権限を
 * 個別にチェックする静的メソッド群を提供する。
 */
class HealthMonitor {

    /**
     * ディスクの空き容量をチェックする
     *
     * @param string $path         チェック対象のパス（デフォルト: カレントディレクトリ）
     * @param int    $minFreeBytes 最小空き容量の閾値（バイト）。デフォルト 100MB
     * @return array{status: string, free: int, total: int, free_human: string,
     *               total_human: string, usage_percent: float}
     */
    public static function checkDisk(string $path = '.', int $minFreeBytes = 104_857_600): array {
        $free = @disk_free_space($path);
        $total = @disk_total_space($path);

        if ($free === false || $total === false) {
            return [
                'status'        => 'error',
                'free'          => -1,
                'total'         => -1,
                'free_human'    => 'unknown',
                'total_human'   => 'unknown',
                'usage_percent' => -1.0,
            ];
        }

        $usagePercent = $total > 0 ? round((1 - $free / $total) * 100, 1) : 0.0;

        return [
            'status'        => $free >= $minFreeBytes ? 'ok' : 'warning',
            'free'          => (int)$free,
            'total'         => (int)$total,
            'free_human'    => self::humanSize((int)$free),
            'total_human'   => self::humanSize((int)$total),
            'usage_percent' => $usagePercent,
        ];
    }

    /**
     * メモリ使用状況をチェックする
     *
     * @return array{status: string, usage: int, peak: int, limit: string,
     *               limit_bytes: int, usage_human: string, peak_human: string,
     *               usage_percent: float}
     */
    public static function checkMemory(): array {
        $usage = memory_get_usage(true);
        $peak = memory_get_peak_usage(true);
        $limitStr = ini_get('memory_limit') ?: '-1';
        $limitBytes = self::parseMemoryLimit($limitStr);

        $usagePercent = $limitBytes > 0 ? round(($usage / $limitBytes) * 100, 1) : 0.0;
        $status = match (true) {
            $limitBytes <= 0       => 'ok',
            $usagePercent >= 90.0  => 'critical',
            $usagePercent >= 75.0  => 'warning',
            default                => 'ok',
        };

        return [
            'status'        => $status,
            'usage'         => $usage,
            'peak'          => $peak,
            'limit'         => $limitStr,
            'limit_bytes'   => $limitBytes,
            'usage_human'   => self::humanSize($usage),
            'peak_human'    => self::humanSize($peak),
            'usage_percent' => $usagePercent,
        ];
    }

    /**
     * PHP 環境をチェックする
     *
     * バージョン、主要拡張モジュール、セキュリティ関連設定を確認する。
     *
     * @return array{status: string, version: string, version_id: int,
     *               sapi: string, extensions: array<string, bool>,
     *               settings: array<string, mixed>}
     */
    public static function checkPhp(): array {
        $requiredExtensions = [
            'json', 'mbstring', 'openssl', 'curl',
            'fileinfo', 'dom', 'xml', 'zip',
        ];

        $extensions = [];
        foreach ($requiredExtensions as $ext) {
            $extensions[$ext] = extension_loaded($ext);
        }

        $settings = [
            'display_errors'   => ini_get('display_errors'),
            'error_reporting'  => (int)ini_get('error_reporting'),
            'max_execution_time' => (int)ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size'    => ini_get('post_max_size'),
            'memory_limit'     => ini_get('memory_limit'),
            'allow_url_fopen'  => (bool)ini_get('allow_url_fopen'),
        ];

        $allExtLoaded = !in_array(false, $extensions, true);
        $versionOk = version_compare(PHP_VERSION, '8.2.0', '>=');

        $status = match (true) {
            !$versionOk    => 'critical',
            !$allExtLoaded => 'warning',
            default        => 'ok',
        };

        return [
            'status'     => $status,
            'version'    => PHP_VERSION,
            'version_id' => PHP_VERSION_ID,
            'sapi'       => PHP_SAPI,
            'extensions' => $extensions,
            'settings'   => $settings,
        ];
    }

    /**
     * 指定ディレクトリの書き込み権限をチェックする
     *
     * @param array<string> $dirs チェック対象のディレクトリパス一覧
     * @return array{status: string, results: array<string, array{writable: bool, exists: bool}>}
     */
    public static function checkPermissions(array $dirs): array {
        $results = [];
        $allOk = true;

        foreach ($dirs as $dir) {
            $exists = is_dir($dir);
            $writable = $exists && is_writable($dir);

            $results[$dir] = [
                'exists'   => $exists,
                'writable' => $writable,
            ];

            if (!$writable) {
                $allOk = false;
            }
        }

        return [
            'status'  => $allOk ? 'ok' : 'warning',
            'results' => $results,
        ];
    }

    /**
     * バイト数を人間可読な形式に変換する
     *
     * @param int $bytes バイト数
     * @return string 人間可読な文字列
     */
    private static function humanSize(int $bytes): string {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1_048_576) return round($bytes / 1024, 1) . ' KB';
        if ($bytes < 1_073_741_824) return round($bytes / 1_048_576, 1) . ' MB';
        return round($bytes / 1_073_741_824, 1) . ' GB';
    }

    /**
     * PHP の memory_limit 文字列をバイト数にパースする
     *
     * @param string $limit memory_limit の値（例: '128M', '2G'）
     * @return int バイト数（-1 は無制限を示す）
     */
    private static function parseMemoryLimit(string $limit): int {
        $limit = trim($limit);
        if ($limit === '-1') {
            return -1;
        }

        $value = (int)$limit;
        $unit = strtoupper(substr($limit, -1));

        return match ($unit) {
            'G' => $value * 1_073_741_824,
            'M' => $value * 1_048_576,
            'K' => $value * 1024,
            default => $value,
        };
    }
}
