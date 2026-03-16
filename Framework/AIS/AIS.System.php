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

        /* unserialize → JSON に置換（オブジェクトインジェクション防止） */
        $data = json_decode($content, true);
        if (!is_array($data)) {
            /* レガシー serialize 形式のフォールバック読み取り（読み取り専用、次回保存時にJSON化） */
            $data = @unserialize($content, ['allowed_classes' => false]);
            if ($data === false || !is_array($data)) {
                return null;
            }
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

        /* JSON 形式で保存（serialize 廃止） */
        file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), LOCK_EX);
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
                $reportJson = json_encode($collector->getReport(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                if ($reportJson !== false) {
                    @file_put_contents($reportPath, $reportJson);
                }

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

// ============================================================================
// ApiCache - API レスポンスキャッシュ
// ============================================================================

/**
 * ファイルベースの API レスポンスキャッシュ。
 *
 * ETag / Last-Modified / 304 Not Modified 対応。
 * CacheEngine のロジックを Framework 化したもの。
 *
 * @since Ver.1.8
 */
class ApiCache {

    private const CACHE_DIR = 'data/cache/api';

    private const TTL = [
        'pages'       => 300,
        'page'        => 300,
        'settings'    => 3600,
        'collections' => 60,
        'collection'  => 60,
        'item'        => 60,
        'search'      => 120,
    ];

    /**
     * キャッシュからレスポンスを返す。ヒットすれば Response、ミスなら null。
     */
    public static function serve(string $endpoint, array $params = []): ?\APF\Core\Response {
        $key = self::cacheKey($endpoint, $params);
        $path = self::cachePath($key);

        $content = \APF\Utilities\FileSystem::read($path);
        if ($content === false) return null;

        $ttl = self::TTL[$endpoint] ?? 60;
        $mtime = filemtime($path);
        if ($mtime === false || (time() - $mtime) > $ttl) {
            @unlink($path);
            return null;
        }

        $etag = '"' . hash('xxh128', $content) . '"';
        $lastModified = gmdate('D, d M Y H:i:s', $mtime) . ' GMT';

        $ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
        $ifModifiedSince = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '';

        /* 304 Not Modified (ETag) */
        if ($ifNoneMatch !== '' && $ifNoneMatch === $etag) {
            return (new \APF\Core\Response('', 304))
                ->withHeader('ETag', $etag)
                ->withHeader('Cache-Control', 'max-age=' . $ttl . ', must-revalidate');
        }
        /* 304 Not Modified (Last-Modified) */
        if ($ifModifiedSince !== '' && strtotime($ifModifiedSince) >= $mtime) {
            return (new \APF\Core\Response('', 304))
                ->withHeader('ETag', $etag)
                ->withHeader('Last-Modified', $lastModified)
                ->withHeader('Cache-Control', 'max-age=' . $ttl . ', must-revalidate');
        }

        return (new \APF\Core\Response($content, 200))
            ->withHeader('Content-Type', 'application/json; charset=UTF-8')
            ->withHeader('ETag', $etag)
            ->withHeader('Last-Modified', $lastModified)
            ->withHeader('Cache-Control', 'max-age=' . $ttl . ', must-revalidate')
            ->withHeader('X-Cache', 'HIT');
    }

    /**
     * レスポンスをキャッシュに保存
     */
    public static function store(string $endpoint, array $params, string $content): void {
        $key = self::cacheKey($endpoint, $params);
        $path = self::cachePath($key);
        \APF\Utilities\FileSystem::write($path, $content);
    }

    /**
     * 指定エンドポイントのキャッシュを無効化（空文字で全クリア）
     */
    public static function invalidate(string $endpoint = ''): void {
        $dir = self::CACHE_DIR;
        if (!is_dir($dir)) return;
        if ($endpoint === '') {
            self::clearDir($dir);
            return;
        }
        $files = glob($dir . '/' . $endpoint . '_*.json') ?: [];
        foreach ($files as $f) { @unlink($f); }
    }

    /**
     * コンテンツ変更時に関連キャッシュを一括無効化
     */
    public static function invalidateContent(): void {
        foreach (['pages', 'page', 'collections', 'collection', 'item', 'search'] as $ep) {
            self::invalidate($ep);
        }
    }

    /**
     * キャッシュ統計情報を取得
     */
    public static function getStats(): array {
        $dir = self::CACHE_DIR;
        if (!is_dir($dir)) return ['files' => 0, 'size' => 0, 'size_human' => '0 B'];
        $files = glob($dir . '/*.json') ?: [];
        $size = 0;
        foreach ($files as $f) { $size += filesize($f) ?: 0; }
        return ['files' => count($files), 'size' => $size, 'size_human' => self::humanSize($size)];
    }

    /** 全キャッシュクリア */
    public static function clear(): void { self::invalidate(''); }

    /**
     * remember パターン — キャッシュがあれば返し、なければ callback 実行してキャッシュ
     */
    public static function remember(string $key, int $ttl, callable $callback): mixed {
        $safeKey = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key);
        $path = self::CACHE_DIR . '/mem_' . $safeKey . '.json';
        $content = \APF\Utilities\FileSystem::read($path);
        if ($content !== false) {
            $mtime = filemtime($path);
            if ($mtime !== false && (time() - $mtime) <= $ttl) {
                $decoded = json_decode($content, true);
                if ($decoded !== null) return $decoded;
            }
            @unlink($path);
        }
        $result = $callback();
        \APF\Utilities\FileSystem::ensureDir(self::CACHE_DIR);
        $json = json_encode($result, JSON_UNESCAPED_UNICODE);
        if ($json !== false) {
            \APF\Utilities\FileSystem::write($path, $json);
        }
        return $result;
    }

    private static function cacheKey(string $endpoint, array $params): string {
        $sanitized = preg_replace('/[^a-zA-Z0-9_\-]/', '', $endpoint);
        ksort($params);
        return $sanitized . '_' . md5(json_encode($params));
    }

    private static function cachePath(string $key): string {
        return self::CACHE_DIR . '/' . $key . '.json';
    }

    private static function clearDir(string $dir): void {
        foreach (glob($dir . '/*.json') ?: [] as $f) { @unlink($f); }
    }

    private static function humanSize(int $bytes): string {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1_048_576) return round($bytes / 1024, 1) . ' KB';
        return round($bytes / 1_048_576, 1) . ' MB';
    }
}

// ============================================================================
// DiagnosticsManager - 診断・テレメトリ管理
// ============================================================================

/**
 * リアルタイム診断・テレメトリ管理。
 *
 * DiagnosticEngine のロジックを Framework 化したもの。
 * 12カテゴリ別エラー分類、パフォーマンスプロファイラ、
 * テレメトリ送信（サーキットブレーカー付き）に対応。
 *
 * @since Ver.1.8
 */
class DiagnosticsManager {

    private const CONFIG_FILE = 'diagnostics.json';
    private const LOG_FILE    = 'diagnostics_log.json';
    private const ENDPOINT    = 'https://telemetry.adlaire.com/v1/report';
    private const INTERVAL    = 0;
    private const LOG_RETENTION_DAYS = 14;
    private const MAX_BUFFER_ITEMS   = 100;
    private const VALID_LEVELS = ['basic', 'extended', 'debug'];
    private const RETRY_MAX        = 3;
    private const RETRY_BACKOFF    = [1, 2, 4];
    private const RETRYABLE_CODES  = [429, 502, 503];
    private const CIRCUIT_BREAKER_THRESHOLD = 5;
    private const CIRCUIT_BREAKER_DURATION  = 86400;
    private const MAX_LOG_SIZE_BYTES       = 524288;
    private const LOG_ARCHIVE_GENERATIONS  = 3;

    private const DEBUG_CATEGORIES = [
        'syntax', 'runtime', 'logic', 'semantic', 'off_by_one', 'race_condition',
        'memory', 'performance', 'security', 'environment', 'timing', 'integration',
    ];

    private const ENGINE_CLASSES = [
        'AdminEngine', 'TemplateEngine', 'ThemeEngine', 'UpdateEngine',
        'StaticEngine', 'ApiEngine', 'CollectionEngine', 'MarkdownEngine',
        'GitEngine', 'WebhookEngine', 'CacheEngine', 'ImageOptimizer',
        'DiagnosticEngine',
    ];

    private const SENSITIVE_KEYS = [
        'password', 'password_hash', 'token', 'secret',
        'api_key', 'apikey', 'authorization', 'cookie',
        'session', 'csrf', 'private_key', 'credentials',
    ];

    /* パフォーマンスプロファイラ */
    private static array $timers = [];
    private static array $timings = [];
    private static int $memoryStart = 0;

    /* エンジン別実行時間トラッカー */
    private static array $engineTimers = [];
    private static array $engineTimings = [];
    private static array $engineMemoryBefore = [];
    private static array $engineCallCounts = [];

    /* スタックトレース蓄積 */
    private static array $capturedTraces = [];

    /* ── 設定管理 ── */

    private static function defaults(): array {
        return [
            'enabled' => true, 'level' => 'basic', 'install_id' => '',
            'last_sent' => '', 'last_env_sent' => '',
            'first_run_notice_shown' => false, 'send_interval' => self::INTERVAL,
        ];
    }

    public static function loadConfig(): array {
        $dir = \AIS\Core\AppContext::settingsDir();
        $config = \APF\Utilities\JsonStorage::read(self::CONFIG_FILE, $dir);
        $defaults = self::defaults();
        foreach ($defaults as $k => $v) {
            if (!array_key_exists($k, $config)) $config[$k] = $v;
        }
        if ($config['install_id'] === '') {
            $config['install_id'] = self::generateUuid();
            self::saveConfig($config);
        }
        return $config;
    }

    public static function saveConfig(array $config): void {
        \APF\Utilities\JsonStorage::write(self::CONFIG_FILE, $config, \AIS\Core\AppContext::settingsDir());
    }

    public static function isEnabled(): bool {
        return !empty(self::loadConfig()['enabled']);
    }

    public static function getLevel(): string {
        $level = self::loadConfig()['level'] ?? 'basic';
        return in_array($level, self::VALID_LEVELS, true) ? $level : 'basic';
    }

    public static function setEnabled(bool $enabled): void {
        $config = self::loadConfig();
        $config['enabled'] = $enabled;
        self::saveConfig($config);
    }

    public static function setLevel(string $level): void {
        if (!in_array($level, self::VALID_LEVELS, true)) return;
        $config = self::loadConfig();
        $config['level'] = $level;
        self::saveConfig($config);
    }

    public static function getInstallId(): string {
        return self::loadConfig()['install_id'];
    }

    /* ── パフォーマンスプロファイラ ── */

    public static function startTimer(string $label): void {
        self::$timers[$label] = hrtime(true);
        if ($label === 'request_total') {
            self::$memoryStart = memory_get_usage(true);
        }
    }

    public static function stopTimer(string $label): void {
        if (!isset(self::$timers[$label])) return;
        self::$timings[$label] = round((hrtime(true) - self::$timers[$label]) / 1_000_000, 2);
        unset(self::$timers[$label]);
    }

    public static function getTimings(): array {
        return [
            'timings_ms'        => self::$timings,
            'memory_start'      => self::$memoryStart,
            'memory_peak'       => memory_get_peak_usage(true),
            'memory_peak_human' => self::humanSize(memory_get_peak_usage(true)),
        ];
    }

    /* ── エンジン別実行時間トラッカー ── */

    public static function startEngineTimer(string $engine, string $method = ''): void {
        $key = $method !== '' ? "{$engine}::{$method}" : $engine;
        self::$engineTimers[$key] = hrtime(true);
        self::$engineMemoryBefore[$key] = memory_get_usage(true);
    }

    public static function stopEngineTimer(string $engine, string $method = ''): ?float {
        $key = $method !== '' ? "{$engine}::{$method}" : $engine;
        if (!isset(self::$engineTimers[$key])) return null;

        $elapsed = round((hrtime(true) - self::$engineTimers[$key]) / 1_000_000, 2);
        $memoryDelta = memory_get_usage(true) - (self::$engineMemoryBefore[$key] ?? 0);

        if (!isset(self::$engineTimings[$key])) {
            self::$engineTimings[$key] = [
                'total_ms' => 0.0, 'calls' => 0, 'max_ms' => 0.0,
                'min_ms' => PHP_FLOAT_MAX, 'memory_delta_total' => 0,
            ];
        }
        self::$engineTimings[$key]['total_ms'] += $elapsed;
        self::$engineTimings[$key]['calls']++;
        self::$engineTimings[$key]['max_ms'] = max(self::$engineTimings[$key]['max_ms'], $elapsed);
        self::$engineTimings[$key]['min_ms'] = min(self::$engineTimings[$key]['min_ms'], $elapsed);
        self::$engineTimings[$key]['memory_delta_total'] += $memoryDelta;

        self::$timings[$key] = $elapsed;
        $engineName = explode('::', $key)[0];
        self::$engineCallCounts[$engineName] = (self::$engineCallCounts[$engineName] ?? 0) + 1;
        unset(self::$engineTimers[$key], self::$engineMemoryBefore[$key]);
        return $elapsed;
    }

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

    /* ── スタックトレース収集 ── */

    public static function captureTrace(string $label, int $depth = 15): void {
        if (!self::isEnabled()) return;
        $level = self::getLevel();
        if ($level !== 'debug' && $level !== 'extended') return;

        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $depth);
        self::$capturedTraces[] = [
            'label' => $label, 'trace' => self::sanitizeStackTrace($trace),
            'timestamp' => date('c'), 'memory' => memory_get_usage(true),
        ];
        if (count(self::$capturedTraces) > 50) {
            self::$capturedTraces = array_slice(self::$capturedTraces, -50);
        }
    }

    public static function getCapturedTraces(): array {
        return self::$capturedTraces;
    }

    /* ── エラー・ログ収集 ── */

    public static function registerErrorHandler(): void {
        if (!self::isEnabled()) return;

        set_error_handler(function (int $errno, string $errstr, string $errfile, string $errline) {
            $level = self::getLevel();
            if ($level === 'basic' && in_array($errno, [E_NOTICE, E_DEPRECATED, E_USER_NOTICE, E_USER_DEPRECATED], true)) {
                return false;
            }
            $entry = [
                'type'           => self::errorTypeString($errno),
                'debug_category' => self::classifyError($errno, $errstr),
                'message'        => self::sanitizeMessage($errstr),
                'file'           => self::sanitizePath($errfile),
                'line'           => $errline,
                'timestamp'      => date('c'),
            ];
            if ($level === 'debug' || $level === 'extended') {
                $depth = $level === 'debug' ? 20 : 10;
                $entry['stack_trace'] = self::sanitizeStackTrace(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $depth));
            }
            self::logError($entry);
            return false;
        });

        $previousHandler = set_exception_handler(function (\Throwable $exception) use (&$previousHandler) {
            $entry = [
                'type'           => 'EXCEPTION: ' . get_class($exception),
                'debug_category' => self::classifyException($exception),
                'message'        => self::sanitizeMessage($exception->getMessage()),
                'file'           => self::sanitizePath($exception->getFile()),
                'line'           => $exception->getLine(),
                'code'           => $exception->getCode(),
                'timestamp'      => date('c'),
                'stack_trace'    => self::sanitizeExceptionTrace($exception),
            ];
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
            if ($previousHandler !== null) ($previousHandler)($exception);
        });

        register_shutdown_function(function () {
            $error = error_get_last();
            if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                self::logError([
                    'type'           => 'FATAL: ' . self::errorTypeString($error['type']),
                    'debug_category' => self::classifyError($error['type'], $error['message']),
                    'message'        => self::sanitizeMessage($error['message']),
                    'file'           => self::sanitizePath($error['file']),
                    'line'           => $error['line'],
                    'timestamp'      => date('c'),
                ]);
            }
            self::captureRuntimeSnapshot();
        });
    }

    /* ── エラー分類 ── */

    private static function classifyError(int $errno, string $message): string {
        $msg = strtolower($message);
        if (in_array($errno, [E_PARSE, E_COMPILE_ERROR, E_COMPILE_WARNING], true)) return 'syntax';
        if (str_contains($msg, 'allowed memory size') || str_contains($msg, 'out of memory')) return 'memory';
        if (str_contains($msg, 'maximum execution time') || str_contains($msg, 'timed out')) return 'timing';
        if (str_contains($msg, 'undefined offset') || str_contains($msg, 'undefined index')
            || str_contains($msg, 'out of range') || str_contains($msg, 'undefined array key')) return 'off_by_one';
        if (str_contains($msg, 'type error') || str_contains($msg, 'must be of type')
            || str_contains($msg, 'undefined variable') || str_contains($msg, 'undefined property')) return 'semantic';
        if (str_contains($msg, 'lock') || str_contains($msg, 'deadlock')
            || str_contains($msg, 'resource temporarily unavailable') || str_contains($msg, 'flock')) return 'race_condition';
        if (str_contains($msg, 'permission denied') || str_contains($msg, 'access denied')
            || str_contains($msg, 'csrf') || str_contains($msg, 'unauthorized')) return 'security';
        if (str_contains($msg, 'curl') || str_contains($msg, 'connection refused')
            || str_contains($msg, 'name resolution') || str_contains($msg, 'webhook')) return 'integration';
        if (str_contains($msg, 'extension') || str_contains($msg, 'function not found')
            || str_contains($msg, 'class not found') || str_contains($msg, 'call to undefined function')) return 'environment';
        if ($errno === E_USER_ERROR || str_contains($msg, 'assertion')
            || str_contains($msg, 'invalid argument') || str_contains($msg, 'logic error')) return 'logic';
        return 'runtime';
    }

    private static function classifyException(\Throwable $e): string {
        $msg = strtolower($e->getMessage());
        if ($e instanceof \TypeError) return 'semantic';
        if ($e instanceof \ParseError) return 'syntax';
        if ($e instanceof \LogicException || $e instanceof \DomainException
            || $e instanceof \InvalidArgumentException || $e instanceof \LengthException) return 'logic';
        if ($e instanceof \OutOfRangeException || $e instanceof \OutOfBoundsException) return 'off_by_one';
        if ($e instanceof \OverflowException || str_contains($msg, 'memory')) return 'memory';
        if (str_contains($msg, 'timeout') || str_contains($msg, 'timed out')) return 'timing';
        if (str_contains($msg, 'lock') || str_contains($msg, 'deadlock')) return 'race_condition';
        if (str_contains($msg, 'curl') || str_contains($msg, 'connection') || str_contains($msg, 'webhook')) return 'integration';
        if (str_contains($msg, 'permission') || str_contains($msg, 'unauthorized')) return 'security';
        if (str_contains($msg, 'extension') || str_contains($msg, 'class not found')) return 'environment';
        return 'runtime';
    }

    private static function sanitizeExceptionTrace(\Throwable $e): array {
        $result = [];
        foreach ($e->getTrace() as $i => $frame) {
            if ($i >= 20) break;
            $entry = [];
            if (isset($frame['file'])) $entry['file'] = self::sanitizePath($frame['file']);
            if (isset($frame['line'])) $entry['line'] = $frame['line'];
            if (isset($frame['class'])) $entry['class'] = $frame['class'];
            if (isset($frame['function'])) $entry['function'] = $frame['function'];
            if (isset($frame['type'])) $entry['type'] = $frame['type'];
            $result[] = $entry;
        }
        return $result;
    }

    /* ── デバッグイベント ── */

    public static function logDebugEvent(string $category, string $message, array $context = []): void {
        if (!self::isEnabled()) return;
        if (!in_array($category, self::DEBUG_CATEGORIES, true)) $category = 'runtime';
        $entry = [
            'debug_category' => $category,
            'message' => self::sanitizeMessage($message),
            'timestamp' => date('c'),
        ];
        if (!empty($context)) $entry['context'] = self::stripSensitiveKeys($context);
        if (self::getLevel() === 'debug') {
            $entry['stack_trace'] = self::sanitizeStackTrace(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 8));
        }
        self::log($category, $message, array_merge($context, ['debug_category' => $category]));
    }

    public static function logSlowExecution(string $label, float $elapsed, float $threshold = 1000.0): void {
        if ($elapsed < $threshold) return;
        self::logDebugEvent('performance', "低速処理検知: {$label} ({$elapsed}ms > {$threshold}ms)", [
            'label' => $label, 'elapsed_ms' => $elapsed, 'threshold_ms' => $threshold,
        ]);
    }

    public static function checkMemoryUsage(): void {
        $limit = self::parseMemoryLimit(ini_get('memory_limit'));
        if ($limit <= 0) return;
        $usage = memory_get_usage(true);
        $ratio = $usage / $limit;
        if ($ratio > 0.85) {
            self::logDebugEvent('memory', 'メモリ使用量が上限の' . round($ratio * 100) . '%に到達', [
                'usage_bytes' => $usage, 'limit_bytes' => $limit, 'ratio' => round($ratio, 3),
            ]);
        }
    }

    public static function logRaceCondition(string $resource, string $detail = ''): void {
        self::logDebugEvent('race_condition', "競合状態検知: {$resource}" . ($detail !== '' ? " — {$detail}" : ''), [
            'resource' => $resource, 'detail' => $detail, 'pid' => getmypid(),
        ]);
    }

    public static function logIntegrationError(string $service, int $httpCode = 0, string $detail = ''): void {
        self::logDebugEvent('integration', "外部連携エラー: {$service} (HTTP {$httpCode})" . ($detail !== '' ? " — {$detail}" : ''), [
            'service' => $service, 'http_code' => $httpCode, 'detail' => $detail,
        ]);
    }

    public static function logEnvironmentIssue(string $issue, array $context = []): void {
        self::logDebugEvent('environment', "環境依存: {$issue}", $context);
    }

    public static function logTimingIssue(string $operation, float $elapsed, float $limit): void {
        self::logDebugEvent('timing', "タイミング異常: {$operation} ({$elapsed}s / 上限{$limit}s)", [
            'operation' => $operation, 'elapsed_s' => $elapsed, 'limit_s' => $limit,
        ]);
    }

    private static function captureRuntimeSnapshot(): void {
        $engineTimings = self::getEngineTimings();
        self::log('runtime_snapshot', 'シャットダウン時スナップショット', [
            'memory_usage' => memory_get_usage(true),
            'memory_peak'  => memory_get_peak_usage(true),
            'timings'      => self::$timings,
            'engine_timings' => $engineTimings['engines'] ?? [],
            'engine_summary' => $engineTimings['summary'] ?? [],
            'traced_count'   => count(self::$capturedTraces),
        ]);
    }

    private static function sanitizeStackTrace(array $trace): array {
        $result = [];
        foreach ($trace as $frame) {
            $entry = [];
            if (isset($frame['file'])) $entry['file'] = self::sanitizePath($frame['file']);
            if (isset($frame['line'])) $entry['line'] = $frame['line'];
            if (isset($frame['function'])) $entry['function'] = $frame['function'];
            if (isset($frame['class'])) $entry['class'] = $frame['class'];
            $result[] = $entry;
        }
        return $result;
    }

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

    /* ── ログ管理 ── */

    public static function logError(array $entry): void {
        self::rotateIfNeeded();
        $dir = \AIS\Core\AppContext::settingsDir();
        $log = self::safeJsonRead(self::LOG_FILE, $dir);
        if (!isset($log['errors'])) $log['errors'] = [];
        $log['errors'][] = $entry;
        self::purgeExpiredEntries($log['errors']);
        self::recordDailySummary($log, 'errors');
        \APF\Utilities\JsonStorage::write(self::LOG_FILE, $log, $dir);
    }

    public static function log(string $category, string $message, array $context = []): void {
        if (!self::isEnabled()) return;
        self::rotateIfNeeded();
        $dir = \AIS\Core\AppContext::settingsDir();
        $log = self::safeJsonRead(self::LOG_FILE, $dir);
        if (!isset($log['custom'])) $log['custom'] = [];
        $entry = [
            'category' => $category,
            'message'  => self::sanitizeMessage($message),
            'timestamp' => date('c'),
        ];
        if (!empty($context)) $entry['context'] = self::stripSensitiveKeys($context);
        $log['custom'][] = $entry;
        self::purgeExpiredEntries($log['custom']);
        self::recordDailySummary($log, $category);
        \APF\Utilities\JsonStorage::write(self::LOG_FILE, $log, $dir);
    }

    private static function safeJsonRead(string $file, string $dir): array {
        $path = $dir . '/' . $file;
        if (!file_exists($path)) return ['errors' => [], 'custom' => []];
        $content = @file_get_contents($path);
        if ($content === false) return ['errors' => [], 'custom' => []];
        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            \APF\Utilities\Logger::error('DiagnosticsManager: JSON parse error', ['file' => $file, 'error' => json_last_error_msg()]);
            @rename($path, $path . '.corrupt.' . time());
            return ['errors' => [], 'custom' => []];
        }
        return is_array($data) ? $data : ['errors' => [], 'custom' => []];
    }

    /* ── ログローテーション・保持ポリシー ── */

    public static function rotateIfNeeded(): void {
        $path = \AIS\Core\AppContext::settingsDir() . '/' . self::LOG_FILE;
        if (!file_exists($path)) return;
        $size = @filesize($path);
        if ($size === false || $size < self::MAX_LOG_SIZE_BYTES) return;

        for ($i = self::LOG_ARCHIVE_GENERATIONS; $i >= 1; $i--) {
            $dst = $path . '.' . $i;
            if ($i === self::LOG_ARCHIVE_GENERATIONS && file_exists($dst)) @unlink($dst);
            $src = ($i === 1) ? $path : $path . '.' . ($i - 1);
            if (file_exists($src)) rename($src, $dst);
        }

        $oldLog = self::safeJsonRead(self::LOG_FILE . '.1', \AIS\Core\AppContext::settingsDir());
        $newLog = ['errors' => [], 'custom' => []];
        if (!empty($oldLog['daily_summary'])) $newLog['daily_summary'] = $oldLog['daily_summary'];
        \APF\Utilities\FileSystem::writeJson($path, $newLog);
    }

    private static function purgeExpiredEntries(array &$entries): void {
        $cutoff = date('c', time() - self::LOG_RETENTION_DAYS * 86400);
        $entries = array_values(array_filter($entries, fn(array $e) => ($e['timestamp'] ?? '') >= $cutoff));
    }

    public static function purgeExpiredLogs(): void {
        $dir = \AIS\Core\AppContext::settingsDir();
        $log = self::safeJsonRead(self::LOG_FILE, $dir);
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
        if ($changed) \APF\Utilities\JsonStorage::write(self::LOG_FILE, $log, $dir);
    }

    /* ── 日別サマリー ── */

    private static function recordDailySummary(array &$log, string $type): void {
        $today = date('Y-m-d');
        if (!isset($log['daily_summary'])) $log['daily_summary'] = [];
        if (!isset($log['daily_summary'][$today])) {
            $defaults = ['errors' => 0, 'security' => 0, 'engine' => 0, 'other' => 0];
            foreach (self::DEBUG_CATEGORIES as $cat) $defaults['debug_' . $cat] = 0;
            $log['daily_summary'][$today] = $defaults;
        }
        $key = match ($type) {
            'errors' => 'errors', 'security' => 'security', 'engine' => 'engine', default => 'other',
        };
        $log['daily_summary'][$today][$key] = ($log['daily_summary'][$today][$key] ?? 0) + 1;
        if (in_array($type, self::DEBUG_CATEGORIES, true)) {
            $debugKey = 'debug_' . $type;
            $log['daily_summary'][$today][$debugKey] = ($log['daily_summary'][$today][$debugKey] ?? 0) + 1;
        }
        $cutoff = date('Y-m-d', strtotime('-30 days'));
        foreach (array_keys($log['daily_summary']) as $date) {
            if ($date < $cutoff) unset($log['daily_summary'][$date]);
        }
    }

    /* ── トレンド分析 ── */

    public static function getTrends(int $days = 7): array {
        $dir = \AIS\Core\AppContext::settingsDir();
        $log = self::safeJsonRead(self::LOG_FILE, $dir);
        $summary = $log['daily_summary'] ?? [];
        $result = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $result[] = [
                'date' => $date,
                'errors' => $summary[$date]['errors'] ?? 0,
                'security' => $summary[$date]['security'] ?? 0,
                'engine' => $summary[$date]['engine'] ?? 0,
                'other' => $summary[$date]['other'] ?? 0,
                'total' => ($summary[$date]['errors'] ?? 0) + ($summary[$date]['security'] ?? 0)
                         + ($summary[$date]['engine'] ?? 0) + ($summary[$date]['other'] ?? 0),
            ];
        }
        $recent = array_slice($result, -3);
        $older = array_slice($result, 0, max($days - 3, 1));
        $recentAvg = count($recent) > 0 ? array_sum(array_column($recent, 'total')) / count($recent) : 0;
        $olderAvg = count($older) > 0 ? array_sum(array_column($older, 'total')) / count($older) : 0;
        $direction = 'stable';
        if ($olderAvg > 0 && $recentAvg > $olderAvg * 1.5) $direction = 'increasing';
        elseif ($recentAvg > 0 && $olderAvg > $recentAvg * 1.5) $direction = 'decreasing';

        return [
            'days' => $result, 'trend_direction' => $direction,
            'spike_detected' => self::detectSpike($summary),
        ];
    }

    private static function detectSpike(array $summary = []): bool {
        if (empty($summary)) {
            $dir = \AIS\Core\AppContext::settingsDir();
            $log = self::safeJsonRead(self::LOG_FILE, $dir);
            $summary = $log['daily_summary'] ?? [];
        }
        $today = date('Y-m-d');
        $todayTotal = ($summary[$today]['errors'] ?? 0) + ($summary[$today]['security'] ?? 0)
                    + ($summary[$today]['engine'] ?? 0) + ($summary[$today]['other'] ?? 0);
        if ($todayTotal === 0) return false;
        $sum = 0; $count = 0;
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

    /* ── データ収集 ── */

    public static function collect(): array {
        $level = self::getLevel();
        $data = self::collectBasic();
        if ($level === 'extended' || $level === 'debug') $data = array_merge($data, self::collectExtended());
        if ($level === 'debug') $data = array_merge($data, self::collectDebug());
        $data['collect_level'] = $level;
        $data['collected_at'] = date('c');
        return $data;
    }

    public static function collectBasic(): array {
        $dir = \AIS\Core\AppContext::settingsDir();
        $log = \APF\Utilities\JsonStorage::read(self::LOG_FILE, $dir);
        $errorCount = count($log['errors'] ?? []);
        $errorSummary = [];
        $debugCategorySummary = array_fill_keys(self::DEBUG_CATEGORIES, 0);
        foreach (($log['errors'] ?? []) as $e) {
            $type = $e['type'] ?? 'unknown';
            $errorSummary[$type] = ($errorSummary[$type] ?? 0) + 1;
            $cat = $e['debug_category'] ?? 'runtime';
            if (isset($debugCategorySummary[$cat])) $debugCategorySummary[$cat]++;
        }
        $engines = [];
        foreach (self::ENGINE_CLASSES as $cls) {
            if (class_exists($cls)) $engines[] = $cls;
        }
        return [
            'install_id' => self::getInstallId(),
            'ap_version' => defined('AP_VERSION') ? AP_VERSION : 'unknown',
            'php_version' => PHP_VERSION, 'os' => PHP_OS_FAMILY, 'sapi' => PHP_SAPI,
            'engines' => $engines, 'error_count' => $errorCount,
            'error_summary' => $errorSummary,
            'debug_category_summary' => $debugCategorySummary,
            'custom_log_count' => count($log['custom'] ?? []),
            'security_summary' => self::getSecuritySummary(),
            'collections_summary' => self::collectCollectionsSummary(),
        ];
    }

    private static function collectCollectionsSummary(): array {
        if (!\ACE\Core\CollectionService::isEnabled()) {
            return ['enabled' => false];
        }
        $collections = \ACE\Core\CollectionService::listCollections();
        $totalItems = 0;
        foreach ($collections as $col) $totalItems += $col['count'] ?? 0;
        return ['enabled' => true, 'collection_count' => count($collections), 'total_items' => $totalItems];
    }

    public static function collectExtended(): array {
        $dir = \AIS\Core\AppContext::settingsDir();
        $log = \APF\Utilities\JsonStorage::read(self::LOG_FILE, $dir);
        $recentErrors = array_slice($log['errors'] ?? [], -50);
        foreach ($recentErrors as &$e) {
            $e['message'] = self::sanitizeMessage($e['message'] ?? '');
            unset($e['file']);
        }
        unset($e);
        return [
            'recent_errors'  => $recentErrors,
            'recent_logs'    => array_slice($log['custom'] ?? [], -30),
            'performance'    => [
                'memory_peak' => memory_get_peak_usage(true),
                'memory_peak_human' => self::humanSize(memory_get_peak_usage(true)),
                'disk_free' => @disk_free_space('.') ?: 0,
            ],
            'timings' => self::getTimings(),
            'engine_timings' => (self::getEngineTimings())['engines'] ?? [],
            'security' => self::getSecuritySummary(),
        ];
    }

    public static function collectDebug(): array {
        $dir = \AIS\Core\AppContext::settingsDir();
        $log = \APF\Utilities\JsonStorage::read(self::LOG_FILE, $dir);
        $fullErrors = array_slice($log['errors'] ?? [], -20);

        $categoryBreakdown = array_fill_keys(self::DEBUG_CATEGORIES, 0);
        foreach (($log['errors'] ?? []) as $e) {
            $cat = $e['debug_category'] ?? 'runtime';
            $categoryBreakdown[$cat] = ($categoryBreakdown[$cat] ?? 0) + 1;
        }
        foreach (($log['custom'] ?? []) as $c) {
            $cat = $c['context']['debug_category'] ?? ($c['category'] ?? 'runtime');
            if (isset($categoryBreakdown[$cat])) $categoryBreakdown[$cat]++;
        }

        $categoryRecent = array_fill_keys(self::DEBUG_CATEGORIES, []);
        foreach (array_reverse($log['errors'] ?? []) as $e) {
            $cat = $e['debug_category'] ?? 'runtime';
            if (isset($categoryRecent[$cat]) && count($categoryRecent[$cat]) < 5) $categoryRecent[$cat][] = $e;
        }

        $phpConfig = [
            'max_execution_time' => ini_get('max_execution_time'),
            'memory_limit' => ini_get('memory_limit'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'display_errors' => ini_get('display_errors'),
            'error_reporting' => error_reporting(),
            'session.gc_maxlifetime' => ini_get('session.gc_maxlifetime'),
            'file_uploads' => ini_get('file_uploads'),
            'open_basedir' => ini_get('open_basedir') ?: '(none)',
            'disable_functions' => ini_get('disable_functions') ?: '(none)',
            'allow_url_fopen' => ini_get('allow_url_fopen'),
        ];

        $opcacheInfo = [];
        if (function_exists('opcache_get_status')) {
            $opcStatus = @opcache_get_status(false);
            if (is_array($opcStatus)) {
                $opcacheInfo = [
                    'enabled' => $opcStatus['opcache_enabled'] ?? false,
                    'used_memory_mb' => isset($opcStatus['memory_usage']['used_memory']) ? round($opcStatus['memory_usage']['used_memory'] / 1048576, 1) : null,
                    'hit_rate' => $opcStatus['opcache_statistics']['opcache_hit_rate'] ?? null,
                ];
            }
        }

        $writableChecks = [];
        foreach (['data', 'data/settings', 'data/content', 'uploads', 'backup'] as $d) {
            $writableChecks[$d] = is_dir($d) ? is_writable($d) : null;
        }

        $envInfo = [
            'php_version' => PHP_VERSION, 'php_version_id' => PHP_VERSION_ID,
            'os' => PHP_OS, 'os_family' => PHP_OS_FAMILY, 'sapi' => PHP_SAPI,
            'extensions' => get_loaded_extensions(), 'zend_version' => zend_version(),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
            'opcache' => $opcacheInfo,
            'gd' => extension_loaded('gd') ? gd_info() : ['GD Version' => 'not installed'],
            'writable_dirs' => $writableChecks,
        ];

        $memoryInfo = [
            'current' => memory_get_usage(true),
            'current_human' => self::humanSize(memory_get_usage(true)),
            'peak' => memory_get_peak_usage(true),
            'peak_human' => self::humanSize(memory_get_peak_usage(true)),
            'limit' => ini_get('memory_limit'),
            'limit_bytes' => self::parseMemoryLimit(ini_get('memory_limit')),
        ];
        if ($memoryInfo['limit_bytes'] > 0) {
            $memoryInfo['usage_ratio'] = round(memory_get_usage(true) / $memoryInfo['limit_bytes'], 3);
        }

        $tracedErrors = [];
        foreach (array_reverse($log['errors'] ?? []) as $e) {
            if (!empty($e['stack_trace'])) {
                $tracedErrors[] = $e;
                if (count($tracedErrors) >= 20) break;
            }
        }

        return [
            'full_errors' => $fullErrors, 'category_breakdown' => $categoryBreakdown,
            'category_recent' => $categoryRecent, 'traced_errors' => $tracedErrors,
            'captured_traces' => self::getCapturedTraces(),
            'engine_timings' => self::getEngineTimings(),
            'php_config' => $phpConfig, 'environment' => $envInfo,
            'memory_detail' => $memoryInfo, 'debug_categories' => self::DEBUG_CATEGORIES,
            'data_storage' => self::collectDataStorageInfo(),
        ];
    }

    private static function collectDataStorageInfo(): array {
        $dataDir = 'data';
        if (!is_dir($dataDir)) return ['error' => 'data/ ディレクトリなし'];
        $totalSize = 0; $fileCount = 0; $dirCount = 0; $jsonSizes = [];
        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dataDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iter as $item) {
            if ($item->isDir()) { $dirCount++; } else {
                $fileCount++;
                $size = $item->getSize();
                $totalSize += $size;
                if ($item->getExtension() === 'json') $jsonSizes[basename($item->getPathname())] = $size;
            }
        }
        $diskFree = @disk_free_space('.');
        $diskTotal = @disk_total_space('.');
        return [
            'total_size' => $totalSize, 'total_size_human' => self::humanSize($totalSize),
            'file_count' => $fileCount, 'dir_count' => $dirCount, 'json_files' => $jsonSizes,
            'disk_free' => $diskFree !== false ? $diskFree : null,
            'disk_free_human' => $diskFree !== false ? self::humanSize((int)$diskFree) : 'unknown',
            'disk_total' => $diskTotal !== false ? $diskTotal : null,
            'disk_usage_ratio' => ($diskFree !== false && $diskTotal !== false && $diskTotal > 0)
                ? round(1 - ($diskFree / $diskTotal), 3) : null,
        ];
    }

    public static function collectWithUnsent(string $lastSent): array {
        $data = self::collect();
        $dir = \AIS\Core\AppContext::settingsDir();
        $log = self::safeJsonRead(self::LOG_FILE, $dir);
        $unsentErrors = []; $unsentLogs = [];
        if ($lastSent !== '') {
            foreach (($log['errors'] ?? []) as $e) {
                if (($e['timestamp'] ?? '') > $lastSent) $unsentErrors[] = $e;
            }
            foreach (($log['custom'] ?? []) as $c) {
                if (($c['timestamp'] ?? '') > $lastSent) $unsentLogs[] = $c;
            }
        } else {
            $unsentErrors = $log['errors'] ?? [];
            $unsentLogs = $log['custom'] ?? [];
        }
        $data['unsent_errors'] = $unsentErrors;
        $data['unsent_logs'] = $unsentLogs;
        $data['total_errors_held'] = count($log['errors'] ?? []);
        $data['total_logs_held'] = count($log['custom'] ?? []);
        $data['retention_days'] = self::LOG_RETENTION_DAYS;
        return $data;
    }

    /* ── 送信 ── */

    public static function maybeSend(): void {
        if (!self::isEnabled()) return;
        self::purgeExpiredLogs();
        $config = self::loadConfig();
        $breakerUntil = $config['circuit_breaker_until'] ?? 0;
        if ($breakerUntil > 0 && time() < $breakerUntil) return;
        $lastSent = $config['last_sent'] ?? '';
        $interval = $config['send_interval'] ?? self::INTERVAL;
        if ($interval > 0 && $lastSent !== '') {
            $lastTime = strtotime($lastSent);
            if ($lastTime !== false && (time() - $lastTime) < $interval) return;
        }
        $data = self::collectWithUnsent($lastSent);
        $hasNewLogs = !empty($data['unsent_errors']) || !empty($data['unsent_logs']);
        if (!$hasNewLogs) {
            $lastEnv = $config['last_env_sent'] ?? '';
            if ($lastEnv !== '') {
                $lastEnvTime = strtotime($lastEnv);
                if ($lastEnvTime !== false && (time() - $lastEnvTime) < 86400) return;
            }
        }
        if (self::send($data)) {
            $config = self::loadConfig();
            $config['last_sent'] = date('c');
            $config['consecutive_failures'] = 0;
            $config['circuit_breaker_until'] = 0;
            if (!$hasNewLogs) $config['last_env_sent'] = date('c');
            self::saveConfig($config);
        } else {
            $config = self::loadConfig();
            $failures = ($config['consecutive_failures'] ?? 0) + 1;
            $config['consecutive_failures'] = $failures;
            if ($failures >= self::CIRCUIT_BREAKER_THRESHOLD) {
                $config['circuit_breaker_until'] = time() + self::CIRCUIT_BREAKER_DURATION;
                \APF\Utilities\Logger::critical('DiagnosticsManager: サーキットブレーカー発動', ['failures' => $failures, 'duration' => '24h']);
            }
            self::saveConfig($config);
        }
    }

    public static function send(array $data): bool {
        if (!function_exists('curl_init')) return false;
        $body = json_encode($data, JSON_UNESCAPED_UNICODE);
        if ($body === false) return false;
        $headers = [
            'Content-Type: application/json',
            'User-Agent: AdlairePlatform/' . (defined('AP_VERSION') ? AP_VERSION : '1.0'),
            'X-AP-Install-ID: ' . ($data['install_id'] ?? ''),
        ];
        for ($attempt = 0; $attempt <= self::RETRY_MAX; $attempt++) {
            if ($attempt > 0) sleep(self::RETRY_BACKOFF[$attempt - 1] ?? 4);
            $ch = curl_init(self::ENDPOINT);
            if ($ch === false) return false;
            curl_setopt_array($ch, [
                CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body,
                CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5, CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_FOLLOWLOCATION => false,
            ]);
            $result = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($httpCode >= 200 && $httpCode < 300) return true;
            if (!in_array($httpCode, self::RETRYABLE_CODES, true)) return false;
        }
        return false;
    }

    /* ── UI / 統計 ── */

    public static function preview(): array { return self::collect(); }

    public static function getLogSummary(): array {
        $dir = \AIS\Core\AppContext::settingsDir();
        $log = \APF\Utilities\JsonStorage::read(self::LOG_FILE, $dir);
        $errors = $log['errors'] ?? [];
        $custom = $log['custom'] ?? [];
        $errorTypes = []; $logCategories = [];
        foreach ($errors as $e) {
            $type = $e['type'] ?? 'unknown';
            $errorTypes[$type] = ($errorTypes[$type] ?? 0) + 1;
        }
        foreach ($custom as $c) {
            $cat = $c['category'] ?? 'other';
            $logCategories[$cat] = ($logCategories[$cat] ?? 0) + 1;
        }
        return [
            'error_count' => count($errors), 'log_count' => count($custom),
            'error_types' => $errorTypes, 'log_categories' => $logCategories,
            'recent_errors' => array_slice($errors, -10),
            'recent_logs' => array_slice($custom, -10),
            'trends' => self::getTrends(7),
        ];
    }

    public static function getSecuritySummary(): array {
        $dir = \AIS\Core\AppContext::settingsDir();
        $log = \APF\Utilities\JsonStorage::read(self::LOG_FILE, $dir);
        $summary = ['login_failure' => 0, 'lockout' => 0, 'rate_limit' => 0, 'ssrf_blocked' => 0];
        foreach (($log['custom'] ?? []) as $entry) {
            if (($entry['category'] ?? '') !== 'security') continue;
            $msg = $entry['message'] ?? '';
            if (str_contains($msg, 'ログイン失敗')) $summary['login_failure']++;
            elseif (str_contains($msg, 'ロックアウト')) $summary['lockout']++;
            elseif (str_contains($msg, 'レート制限')) $summary['rate_limit']++;
            elseif (str_contains($msg, 'SSRF')) $summary['ssrf_blocked']++;
        }
        return $summary;
    }

    public static function healthCheck(bool $detailed = false): array {
        $config = self::loadConfig();
        $dir = \AIS\Core\AppContext::settingsDir();
        $log = \APF\Utilities\JsonStorage::read(self::LOG_FILE, $dir);
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
        if ($status !== 'critical' && self::detectSpike($log['daily_summary'] ?? [])) $status = 'warning';

        $logPath = $dir . '/' . self::LOG_FILE;
        $logSize = file_exists($logPath) ? @filesize($logPath) : 0;

        $result = [
            'status' => $status,
            'version' => defined('AP_VERSION') ? AP_VERSION : 'unknown',
            'php' => PHP_VERSION, 'uptime_check' => true,
            'diagnostics' => [
                'errors_24h' => $errors24h,
                'disk_free_mb' => $diskFree !== false ? round($diskFree / 1024 / 1024) : null,
                'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 1),
                'last_diagnostic_sent' => $config['last_sent'] ?? '',
                'log_file_size_kb' => $logSize !== false ? round($logSize / 1024, 1) : 0,
            ],
        ];
        if ($detailed) {
            $result['security'] = self::getSecuritySummary();
            $result['timings'] = self::getTimings();
            $result['diagnostics']['error_count'] = count($log['errors'] ?? []);
            $result['diagnostics']['log_count'] = count($log['custom'] ?? []);
            $result['system'] = [
                'disk'        => HealthMonitor::checkDisk(),
                'memory'      => HealthMonitor::checkMemory(),
                'php'         => HealthMonitor::checkPhp(),
                'permissions' => HealthMonitor::checkPermissions([
                    'data', 'data/settings', 'data/content', 'data/logs', 'uploads',
                ]),
            ];
        }
        return $result;
    }

    public static function getAllLogs(): array {
        $dir = \AIS\Core\AppContext::settingsDir();
        return \APF\Utilities\JsonStorage::read(self::LOG_FILE, $dir);
    }

    public static function clearLogs(): void {
        $dir = \AIS\Core\AppContext::settingsDir();
        $log = self::safeJsonRead(self::LOG_FILE, $dir);
        $newLog = ['errors' => [], 'custom' => []];
        if (!empty($log['daily_summary'])) $newLog['daily_summary'] = $log['daily_summary'];
        \APF\Utilities\JsonStorage::write(self::LOG_FILE, $newLog, $dir);
    }

    /* ── 通知 ── */

    public static function shouldShowNotice(): bool {
        $config = self::loadConfig();
        return $config['enabled'] && !$config['first_run_notice_shown'];
    }

    public static function markNoticeShown(): void {
        $config = self::loadConfig();
        $config['first_run_notice_shown'] = true;
        self::saveConfig($config);
    }

    /* ── プライバシー・サニタイズ ── */

    private static function sanitizeMessage(string $msg): string {
        $msg = preg_replace('/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/', '[EMAIL]', $msg);
        $msg = preg_replace('/\b\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\b/', '[IP]', $msg);
        $msg = preg_replace('#/home/[^/]+/#', '/home/[USER]/', $msg);
        $msg = preg_replace('#C:\\\\Users\\\\[^\\\\]+\\\\#i', 'C:\\Users\\[USER]\\', $msg);
        return $msg;
    }

    private static function sanitizePath(string $path): string {
        $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
        if ($docRoot !== '' && str_starts_with($path, $docRoot)) {
            return substr($path, strlen($docRoot));
        }
        $path = preg_replace('#/home/[^/]+/#', '/home/[USER]/', $path);
        $path = preg_replace('#C:\\\\Users\\\\[^\\\\]+\\\\#i', 'C:\\Users\\[USER]\\', $path);
        return $path;
    }

    private static function stripSensitiveKeys(array $data): array {
        $result = [];
        foreach ($data as $key => $value) {
            $lowerKey = strtolower((string)$key);
            $isSensitive = false;
            foreach (self::SENSITIVE_KEYS as $sk) {
                if (str_contains($lowerKey, $sk)) { $isSensitive = true; break; }
            }
            if ($isSensitive) $result[$key] = '[REDACTED]';
            elseif (is_array($value)) $result[$key] = self::stripSensitiveKeys($value);
            else $result[$key] = $value;
        }
        return $result;
    }

    /* ── ユーティリティ ── */

    private static function generateUuid(): string {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return sprintf('%s-%s-%s-%s-%s',
            bin2hex(substr($bytes, 0, 4)), bin2hex(substr($bytes, 4, 2)),
            bin2hex(substr($bytes, 6, 2)), bin2hex(substr($bytes, 8, 2)),
            bin2hex(substr($bytes, 10, 6)));
    }

    private static function errorTypeString(int $type): string {
        return match ($type) {
            E_ERROR => 'E_ERROR', E_WARNING => 'E_WARNING', E_PARSE => 'E_PARSE',
            E_NOTICE => 'E_NOTICE', E_CORE_ERROR => 'E_CORE_ERROR',
            E_COMPILE_ERROR => 'E_COMPILE_ERROR', E_USER_ERROR => 'E_USER_ERROR',
            E_USER_WARNING => 'E_USER_WARNING', E_USER_NOTICE => 'E_USER_NOTICE',
            E_STRICT => 'E_STRICT', E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED => 'E_DEPRECATED', E_USER_DEPRECATED => 'E_USER_DEPRECATED',
            default => 'E_UNKNOWN(' . $type . ')',
        };
    }

    private static function humanSize(int $bytes): string {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1_048_576) return round($bytes / 1024, 1) . ' KB';
        return round($bytes / 1_048_576, 1) . ' MB';
    }
}
