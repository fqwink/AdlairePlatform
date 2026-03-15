<?php
/**
 * Adlaire Platform Foundation (APF) - Utilities Module
 *
 * APF = Adlaire Platform Foundation
 *
 * @package APF
 * @version 1.0.0
 * @license Adlaire License Ver.2.0
 */

namespace APF\Utilities;

// ============================================================================
// Config - アプリケーション設定管理
// ============================================================================

/**
 * 環境変数・設定ファイルからの統一的な設定読み込み。
 * ハードコード値を排除し、外部設定による制御を実現する。
 * @since Ver.1.9
 */
class Config {
    private static array $cache = [];
    private static array $defaults = [
        'app.default_password'    => 'admin',
        'session.timeout'         => 1800,
        'session.cookie_lifetime' => 0,
        'session.cookie_httponly' => true,
        'session.cookie_samesite' => 'Lax',
        'log.level'              => 'info',
        'log.format'             => 'text',
        'backup.generations'     => 5,
        'revision.limit'         => 30,
    ];

    /**
     * 設定値を取得する。環境変数 → 設定ファイル → デフォルト値の優先順で解決。
     */
    public static function get(string $key, mixed $default = null): mixed {
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        /* 環境変数を優先（ドット区切りを AP_ プレフィックス + アンダースコアに変換） */
        $envKey = 'AP_' . strtoupper(str_replace('.', '_', $key));
        $envVal = getenv($envKey) ?: ($_ENV[$envKey] ?? null);
        if ($envVal !== null && $envVal !== false) {
            $resolved = self::castEnvValue($envVal);
            self::$cache[$key] = $resolved;
            return $resolved;
        }

        return self::$defaults[$key] ?? $default;
    }

    /**
     * デフォルト値を一括設定する（起動時に使用）。
     */
    public static function setDefaults(array $defaults): void {
        self::$defaults = array_merge(self::$defaults, $defaults);
    }

    /**
     * キャッシュをクリアする。
     */
    public static function clearCache(): void {
        self::$cache = [];
    }

    /**
     * 環境変数の文字列を適切な型にキャストする。
     */
    private static function castEnvValue(string $value): mixed {
        return match (strtolower($value)) {
            'true'  => true,
            'false' => false,
            'null'  => null,
            default => is_numeric($value) ? (str_contains($value, '.') ? (float)$value : (int)$value) : $value,
        };
    }
}

// ============================================================================
// Validator - バリデーション
// ============================================================================

/**
 * @updated Ver.1.9 Request バリデーション統合メソッド追加
 */
class Validator {
    private readonly array $data;
    private readonly array $rules;
    private array $errors = [];
    private readonly array $messages;

    public function __construct(array $data, array $rules, array $messages = []) {
        $this->data = $data;
        $this->rules = $rules;
        $this->messages = $messages;
    }

    public static function make(array $data, array $rules, array $messages = []): self {
        return new self($data, $rules, $messages);
    }

    /**
     * Request オブジェクトから直接バリデーションする。
     * 失敗時は ValidationException をスローする。
     * @since Ver.1.9
     */
    public static function request(\APF\Core\Request $request, array $rules, array $messages = []): array {
        $data = array_merge($request->query() ?? [], $request->post() ?? []);
        $validator = new self($data, $rules, $messages);
        if ($validator->fails()) {
            throw new \APF\Core\ValidationException('Validation failed', $validator->errors());
        }
        /* バリデーション済みデータのみ返す */
        return array_intersect_key($data, $rules);
    }

    private function applyRule(string $field, string $rule): void {
        $value = $this->data[$field] ?? null;
        
        if (str_contains($rule, ':')) {
            [$ruleName, $parameter] = explode(':', $rule, 2);
        } else {
            $ruleName = $rule;
            $parameter = null;
        }

        $method = 'validate' . ucfirst($ruleName);
        
        if (method_exists($this, $method)) {
            $this->$method($field, $value, $parameter);
        }
    }

    private function validateRequired(string $field, $value): void {
        if (is_null($value) || $value === '') {
            $this->addError($field, 'required', "{$field} is required");
        }
    }

    private function validateEmail(string $field, $value): void {
        if (!is_null($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, 'email', "{$field} must be a valid email address");
        }
    }

    private function validateMin(string $field, $value, ?string $parameter): void {
        if (is_null($value)) {
            return;
        }

        $min = (int)$parameter;
        
        if (is_string($value) && mb_strlen($value) < $min) {
            $this->addError($field, 'min', "{$field} must be at least {$min} characters");
        } elseif (is_numeric($value) && $value < $min) {
            $this->addError($field, 'min', "{$field} must be at least {$min}");
        }
    }

    private function validateMax(string $field, $value, ?string $parameter): void {
        if (is_null($value)) {
            return;
        }

        $max = (int)$parameter;
        
        if (is_string($value) && mb_strlen($value) > $max) {
            $this->addError($field, 'max', "{$field} must not exceed {$max} characters");
        } elseif (is_numeric($value) && $value > $max) {
            $this->addError($field, 'max', "{$field} must not exceed {$max}");
        }
    }

    private function validateNumeric(string $field, $value): void {
        if (!is_null($value) && !is_numeric($value)) {
            $this->addError($field, 'numeric', "{$field} must be a number");
        }
    }

    private function validateInteger(string $field, $value): void {
        if (!is_null($value) && !filter_var($value, FILTER_VALIDATE_INT)) {
            $this->addError($field, 'integer', "{$field} must be an integer");
        }
    }

    private function validateAlpha(string $field, $value): void {
        if (!is_null($value) && !ctype_alpha($value)) {
            $this->addError($field, 'alpha', "{$field} must contain only letters");
        }
    }

    private function validateAlphaNum(string $field, $value): void {
        if (!is_null($value) && !ctype_alnum($value)) {
            $this->addError($field, 'alpha_num', "{$field} must contain only letters and numbers");
        }
    }

    private function validateUrl(string $field, $value): void {
        if (!is_null($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
            $this->addError($field, 'url', "{$field} must be a valid URL");
        }
    }

    private function validateIn(string $field, $value, ?string $parameter): void {
        if (is_null($value)) {
            return;
        }

        $allowed = explode(',', $parameter);
        
        if (!in_array($value, $allowed, true)) {
            $this->addError($field, 'in', "{$field} must be one of: " . implode(', ', $allowed));
        }
    }

    private function validateDate(string $field, $value): void {
        if (!is_null($value) && strtotime($value) === false) {
            $this->addError($field, 'date', "{$field} must be a valid date");
        }
    }

    private function validateBoolean(string $field, $value): void {
        if (!is_null($value) && !in_array($value, [true, false, 0, 1, '0', '1'], true)) {
            $this->addError($field, 'boolean', "{$field} must be true or false");
        }
    }

    private function validateConfirmed(string $field, $value): void {
        if (is_null($value)) return;
        $confirmKey = $field . '_confirmation';
        if (!array_key_exists($confirmKey, $this->data) || $value !== $this->data[$confirmKey]) {
            $this->addError($field, 'confirmed', "{$field} confirmation does not match");
        }
    }

    private function validateRegex(string $field, $value, ?string $parameter): void {
        if (!is_null($value) && !preg_match($parameter, (string)$value)) {
            $this->addError($field, 'regex', "{$field} format is invalid");
        }
    }

    private function validateBetween(string $field, $value, ?string $parameter): void {
        if (is_null($value)) return;
        [$min, $max] = explode(',', $parameter);
        $len = is_string($value) ? mb_strlen($value) : $value;
        if ($len < (int)$min || $len > (int)$max) {
            $this->addError($field, 'between', "{$field} must be between {$min} and {$max}");
        }
    }

    private function validateSize(string $field, $value, ?string $parameter): void {
        if (is_null($value)) return;
        $size = (int)$parameter;
        $len = is_string($value) ? mb_strlen($value) : (is_array($value) ? count($value) : $value);
        if ($len !== $size) {
            $this->addError($field, 'size', "{$field} must be exactly {$size}");
        }
    }

    private function validateArray(string $field, $value): void {
        if (!is_null($value) && !is_array($value)) {
            $this->addError($field, 'array', "{$field} must be an array");
        }
    }

    private function validateJson(string $field, $value): void {
        if (!is_null($value)) {
            json_decode((string)$value);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->addError($field, 'json', "{$field} must be valid JSON");
            }
        }
    }

    private function validateIp(string $field, $value): void {
        if (!is_null($value) && !filter_var($value, FILTER_VALIDATE_IP)) {
            $this->addError($field, 'ip', "{$field} must be a valid IP address");
        }
    }

    private function validateUuid(string $field, $value): void {
        if (!is_null($value) && !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', (string)$value)) {
            $this->addError($field, 'uuid', "{$field} must be a valid UUID");
        }
    }

    private function validateSlug(string $field, $value): void {
        if (!is_null($value) && !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', (string)$value)) {
            $this->addError($field, 'slug', "{$field} must be a valid slug");
        }
    }

    private function addError(string $field, string $rule, string $message): void {
        $key = "{$field}.{$rule}";
        $this->errors[$field][] = $this->messages[$key] ?? $message;
    }

    /** @var bool バリデーション実行済みフラグ @since Ver.1.9 */
    private bool $validated = false;

    private function ensureValidated(): void {
        if (!$this->validated) {
            $this->validate();
        }
    }

    public function validate(): bool {
        $this->validated = true;
        foreach ($this->rules as $field => $rules) {
            $rulesArray = is_string($rules) ? explode('|', $rules) : $rules;

            foreach ($rulesArray as $rule) {
                $this->applyRule($field, $rule);
            }
        }

        return empty($this->errors);
    }

    public function fails(): bool {
        return !$this->validate();
    }

    public function errors(): array {
        $this->ensureValidated();
        return $this->errors;
    }

    public function first(string $field): ?string {
        $this->ensureValidated();
        return $this->errors[$field][0] ?? null;
    }

    public function hasError(string $field): bool {
        $this->ensureValidated();
        return isset($this->errors[$field]);
    }
}

// ============================================================================
// Cache - キャッシュシステム
// ============================================================================

class Cache {
    private readonly string $directory;
    private int $defaultTtl = 3600;

    public function __construct(string $directory) {
        $this->directory = rtrim($directory, '/');

        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0755, true);
        }
    }

    /**
     * @updated Ver.1.9 serialize → JSON に変更（セキュリティ強化）
     */
    public function get(string $key, mixed $default = null): mixed {
        $file = $this->getFilePath($key);

        if (!file_exists($file)) {
            return $default;
        }

        $raw = file_get_contents($file);
        if ($raw === false) return $default;

        $data = json_decode($raw, true);
        if (!is_array($data) || !array_key_exists('value', $data)) {
            Logger::warning('Cache entry corrupted, removing', ['key' => $key]);
            $this->delete($key);
            return $default;
        }

        if (($data['expires'] ?? 0) > 0 && $data['expires'] < time()) {
            $this->delete($key);
            return $default;
        }

        return $data['value'];
    }

    /**
     * @updated Ver.1.9 serialize → JSON に変更（セキュリティ強化）
     */
    public function set(string $key, $value, ?int $ttl = null): bool {
        $ttl = $ttl ?? $this->defaultTtl;
        $file = $this->getFilePath($key);

        $data = [
            'value' => $value,
            'expires' => $ttl > 0 ? time() + $ttl : 0,
        ];

        return file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX) !== false;
    }

    public function has(string $key): bool {
        return $this->get($key) !== null;
    }

    public function delete(string $key): bool {
        $file = $this->getFilePath($key);
        
        if (file_exists($file)) {
            return unlink($file);
        }

        return false;
    }

    /**
     * 期限切れキャッシュエントリを削除する（ガベージコレクション）。
     * @since Ver.1.9
     * @return int 削除されたエントリ数
     */
    public function gc(): int {
        $files = glob($this->directory . '/*.cache') ?: [];
        $now = time();
        $removed = 0;

        foreach ($files as $file) {
            if (!is_file($file)) continue;
            $raw = file_get_contents($file);
            if ($raw === false) continue;
            $data = json_decode($raw, true);
            if (is_array($data) && isset($data['expires']) && $data['expires'] > 0 && $data['expires'] < $now) {
                unlink($file);
                $removed++;
            }
        }

        return $removed;
    }

    public function clear(): bool {
        $files = glob($this->directory . '/*.cache');
        
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        return true;
    }

    public function remember(string $key, int $ttl, \Closure $callback): mixed {
        $value = $this->get($key);
        
        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);
        
        return $value;
    }

    public function forever(string $key, $value): bool {
        return $this->set($key, $value, 0);
    }

    private function getFilePath(string $key): string {
        return $this->directory . '/' . md5($key) . '.cache';
    }

    public function setDefaultTtl(int $ttl): void {
        $this->defaultTtl = $ttl;
    }
}

// ============================================================================
// Logger - ログシステム
// ============================================================================

/**
 * 構造化ログ対応ロガー。
 *
 * - JSON / テキスト 2形式出力（format プロパティで切替）
 * - 環境変数 AP_LOG_LEVEL / AP_LOG_FORMAT による制御
 * - チャネル別ログ分離
 * - リクエストコンテキスト自動付与
 * @since Ver.1.8
 * @updated Ver.1.9 構造化ログ（JSON形式）・ログレベル環境変数制御を追加
 */
class Logger {
    private static ?self $instance = null;

    private readonly string $logFile;
    private string $level;
    private string $format;
    private string $channel;

    private const LEVELS = [
        'debug'    => 0,
        'info'     => 1,
        'warning'  => 2,
        'error'    => 3,
        'critical' => 4,
    ];

    public function __construct(string $logFile, string $channel = 'app') {
        $this->logFile = $logFile;
        $this->channel = $channel;

        /* 環境変数によるレベル・フォーマット制御 */
        $this->level  = self::resolveLevel();
        $this->format = self::resolveFormat();

        $dir = dirname($logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    /**
     * 静的ファサード: デフォルトロガーを初期化する
     */
    public static function init(string $logFile = ''): void {
        if ($logFile === '') {
            $logFile = (defined('AP_DATA_DIR') ? AP_DATA_DIR : 'data') . '/logs/app.log';
        }
        self::$instance = new self($logFile);
    }

    private static function getInstance(): self {
        if (self::$instance === null) {
            self::init();
        }
        return self::$instance;
    }

    /**
     * チャネル別ロガーを生成する。
     * 例: Logger::channel('security')->warning('...');
     * @since Ver.1.9
     */
    public static function channel(string $name): self {
        $base = self::getInstance();
        $dir  = dirname($base->logFile);
        return new self($dir . '/' . $name . '.log', $name);
    }

    /**
     * 静的ファサードメソッド群
     * 全プロジェクトから \APF\Utilities\Logger::info() 等で呼び出し可能
     */
    public static function __callStatic(string $name, array $arguments): void {
        $instance = self::getInstance();
        if (isset(self::LEVELS[$name])) {
            $instance->log($name, ...$arguments);
        }
    }

    public function debug(string $message, array $context = []): void {
        $this->log('debug', $message, $context);
    }

    public function info(string $message, array $context = []): void {
        $this->log('info', $message, $context);
    }

    public function warning(string $message, array $context = []): void {
        $this->log('warning', $message, $context);
    }

    public function error(string $message, array $context = []): void {
        $this->log('error', $message, $context);
    }

    public function critical(string $message, array $context = []): void {
        $this->log('critical', $message, $context);
    }

    /**
     * 統一ログ書き込み。
     * @since Ver.1.9
     */
    public function log(string $level, string $message, array $context = []): void {
        if (!$this->shouldLog($level)) {
            return;
        }

        $entry = match ($this->format) {
            'json'  => $this->buildJsonEntry($level, $message, $context),
            default => $this->buildTextEntry($level, $message, $context),
        };

        file_put_contents($this->logFile, $entry . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    /**
     * JSON 構造化ログエントリを生成。
     * @since Ver.1.9
     */
    private function buildJsonEntry(string $level, string $message, array $context): string {
        $entry = [
            'timestamp' => date('c'),
            'level'     => $level,
            'channel'   => $this->channel,
            'message'   => $message,
        ];

        if (!empty($context)) {
            $entry['context'] = $context;
        }

        /* リクエストコンテキスト（CLI 実行時は付与しない） */
        if (PHP_SAPI !== 'cli' && isset($_SERVER['REQUEST_METHOD'])) {
            $entry['request'] = [
                'request_id' => $_SERVER['HTTP_X_REQUEST_ID'] ?? substr(bin2hex(random_bytes(4)), 0, 8),
                'method' => $_SERVER['REQUEST_METHOD'],
                'uri'    => $_SERVER['REQUEST_URI'] ?? '/',
                'ip'     => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            ];
        }

        return json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * テキスト形式ログエントリを生成（従来互換）。
     */
    private function buildTextEntry(string $level, string $message, array $context): string {
        $timestamp = date('Y-m-d H:i:s');
        $contextString = empty($context) ? '' : ' ' . json_encode($context, JSON_UNESCAPED_UNICODE);
        return "[{$timestamp}] [{$this->channel}.{$level}] {$message}{$contextString}";
    }

    private function shouldLog(string $level): bool {
        return (self::LEVELS[$level] ?? 0) >= (self::LEVELS[$this->level] ?? 0);
    }

    public function setLevel(string $level): void {
        if (isset(self::LEVELS[$level])) {
            $this->level = $level;
        }
    }

    public function setFormat(string $format): void {
        if (in_array($format, ['text', 'json'], true)) {
            $this->format = $format;
        }
    }

    /**
     * 環境変数 AP_LOG_LEVEL からログレベルを解決。
     * @since Ver.1.9
     */
    private static function resolveLevel(): string {
        $env = getenv('AP_LOG_LEVEL') ?: ($_ENV['AP_LOG_LEVEL'] ?? '');
        $env = strtolower($env);
        return isset(self::LEVELS[$env]) ? $env : 'info';
    }

    /**
     * 環境変数 AP_LOG_FORMAT からフォーマットを解決。
     * @since Ver.1.9
     */
    private static function resolveFormat(): string {
        $env = getenv('AP_LOG_FORMAT') ?: ($_ENV['AP_LOG_FORMAT'] ?? '');
        return $env === 'json' ? 'json' : 'text';
    }
}

// ============================================================================
// Session - セッション管理
// ============================================================================

class Session {
    private bool $started = false;

    public function __construct() {
        $this->start();
    }

    public function start(): void {
        if (!$this->started && session_status() === PHP_SESSION_NONE) {
            session_start();
            $this->started = true;
        }
    }

    public function get(string $key, mixed $default = null): mixed {
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void {
        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool {
        return isset($_SESSION[$key]);
    }

    public function delete(string $key): void {
        unset($_SESSION[$key]);
    }

    public function clear(): void {
        $_SESSION = [];
    }

    public function destroy(): void {
        session_destroy();
        $this->started = false;
    }

    public function regenerate(): void {
        session_regenerate_id(true);
    }

    public function flash(string $key, $value): void {
        $this->set($key, $value);
        $this->set('_flash.' . $key, true);
    }

    public function getFlash(string $key, mixed $default = null): mixed {
        $value = $this->get($key, $default);
        
        if ($this->has('_flash.' . $key)) {
            $this->delete($key);
            $this->delete('_flash.' . $key);
        }

        return $value;
    }

    public function id(): string {
        return session_id();
    }
}

// ============================================================================
// Security - セキュリティユーティリティ
// ============================================================================

class Security {
    public static function hash(string $value): string {
        return password_hash($value, PASSWORD_BCRYPT);
    }

    public static function verify(string $value, string $hash): bool {
        return password_verify($value, $hash);
    }

    public static function randomString(int $length = 32): string {
        return bin2hex(random_bytes($length / 2));
    }

    public static function csrfToken(): string {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = self::randomString(64);
        }

        return $_SESSION['csrf_token'];
    }

    public static function verifyCsrf(string $token): bool {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function escape(string $value): string {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    public static function sanitize(string $value): string {
        return filter_var($value, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    }

    public static function encrypt(string $data, string $key): string {
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }

    public static function decrypt(string $data, string $key): string {
        $data = base64_decode($data);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }

    public static function rateLimit(string $key, int $maxAttempts, int $decayMinutes): bool {
        $cache = new Cache(sys_get_temp_dir() . '/rate_limit');
        $attempts = (int)$cache->get($key, 0);
        
        if ($attempts >= $maxAttempts) {
            return false;
        }

        $cache->set($key, $attempts + 1, $decayMinutes * 60);
        return true;
    }
}

// ============================================================================
// Str - 文字列ヘルパー
// ============================================================================

class Str {
    public static function slug(string $string): string {
        $string = mb_strtolower($string);
        $string = preg_replace('/[^a-z0-9\s-]/', '', $string);
        $string = preg_replace('/[\s-]+/', '-', $string);
        return trim($string, '-');
    }

    /**
     * パストラバーサルを除去した安全なパスを生成する。
     * engines/Bridge.php の getSlug() 相当。
     * @since Ver.1.8
     */
    public static function safePath(string $path): string {
        $slug = mb_convert_case(str_replace(' ', '-', $path), MB_CASE_LOWER, 'UTF-8');
        $slug = str_replace("\0", '', $slug);
        do {
            $prev = $slug;
            $slug = str_replace(['../', '..\\'], '', $slug);
        } while ($slug !== $prev);
        $slug = preg_replace('#/+#', '/', $slug);
        return ltrim($slug, '/');
    }

    public static function startsWith(string $haystack, string $needle): bool {
        return str_starts_with($haystack, $needle);
    }

    public static function endsWith(string $haystack, string $needle): bool {
        return str_ends_with($haystack, $needle);
    }

    public static function contains(string $haystack, string $needle): bool {
        return str_contains($haystack, $needle);
    }

    public static function random(int $length = 16): string {
        $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $max = strlen($chars) - 1;
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $result .= $chars[random_int(0, $max)];
        }
        return $result;
    }

    public static function limit(string $string, int $limit = 100, string $end = '...'): string {
        if (mb_strlen($string) <= $limit) {
            return $string;
        }

        return mb_substr($string, 0, $limit) . $end;
    }

    public static function camel(string $string): string {
        return lcfirst(str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $string))));
    }

    public static function snake(string $string): string {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $string));
    }

    public static function kebab(string $string): string {
        return str_replace('_', '-', self::snake($string));
    }

    public static function title(string $string): string {
        return mb_convert_case($string, MB_CASE_TITLE, 'UTF-8');
    }
}

// ============================================================================
// Arr - 配列ヘルパー
// ============================================================================

class Arr {
    public static function get(array $array, string $key, $default = null) {
        if (isset($array[$key])) {
            return $array[$key];
        }

        foreach (explode('.', $key) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return $default;
            }
            $array = $array[$segment];
        }

        return $array;
    }

    public static function set(array &$array, string $key, $value): void {
        $keys = explode('.', $key);

        while (count($keys) > 1) {
            $key = array_shift($keys);

            if (!isset($array[$key]) || !is_array($array[$key])) {
                $array[$key] = [];
            }

            $array = &$array[$key];
        }

        $array[array_shift($keys)] = $value;
    }

    public static function has(array $array, string $key): bool {
        return self::get($array, $key) !== null;
    }

    public static function only(array $array, array $keys): array {
        return array_intersect_key($array, array_flip($keys));
    }

    public static function except(array $array, array $keys): array {
        return array_diff_key($array, array_flip($keys));
    }

    public static function flatten(array $array): array {
        $result = [];

        foreach ($array as $value) {
            if (is_array($value)) {
                $result = array_merge($result, self::flatten($value));
            } else {
                $result[] = $value;
            }
        }

        return $result;
    }

    public static function pluck(array $array, string $key): array {
        return array_column($array, $key);
    }

    public static function where(array $array, \Closure $callback): array {
        return array_filter($array, $callback, ARRAY_FILTER_USE_BOTH);
    }
}

// ============================================================================
// FileSystem - ファイル I/O 抽象化
// ============================================================================

/**
 * ファイル I/O の安全な抽象化レイヤー。
 * engines/FileSystem.php のロジックを Framework に移植。
 * @since Ver.1.8
 */
class FileSystem {

    /**
     * ファイルを安全に読み込む。
     * @return string|false 成功時は内容、失敗時は false
     */
    public static function read(string $path): string|false {
        return @file_get_contents($path);
    }

    /**
     * ファイルを安全に書き込む（LOCK_EX 付き）。
     */
    public static function write(string $path, string $content): bool {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0755, true)) {
                return false;
            }
        }
        return @file_put_contents($path, $content, LOCK_EX) !== false;
    }

    /**
     * JSON ファイルを読み込んで配列として返す。
     */
    public static function readJson(string $path): ?array {
        $content = self::read($path);
        if ($content === false) return null;
        $data = json_decode($content, true);
        return is_array($data) ? $data : null;
    }

    /**
     * 配列を JSON ファイルとして書き込む。
     */
    public static function writeJson(string $path, array $data): bool {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json === false) return false;
        return self::write($path, $json);
    }

    /**
     * ファイルが存在するか確認。
     */
    public static function exists(string $path): bool {
        return file_exists($path);
    }

    /**
     * ファイルを安全に削除。
     */
    public static function delete(string $path): bool {
        if (!file_exists($path)) return true;
        return @unlink($path);
    }

    /**
     * ディレクトリが存在しなければ作成。
     */
    public static function ensureDir(string $dir): bool {
        if (is_dir($dir)) return true;
        return @mkdir($dir, 0755, true);
    }
}

// ============================================================================
// JsonStorage - JSON I/O + リクエスト内キャッシュ
// ============================================================================

/**
 * JSON ファイルの読み書きとリクエスト内キャッシュを提供する。
 * engines/Bridge.php の json_read/json_write/JsonCache を統合。
 * @since Ver.1.8
 */
class JsonStorage {

    /** @var array<string, array> パス → データの読み込みキャッシュ */
    private static array $cache = [];

    /**
     * JSON ファイルを読み込む。キャッシュヒット時はキャッシュから返す。
     */
    public static function read(string $file, string $dir = ''): array {
        $path = ($dir ?: 'data') . '/' . $file;

        if (isset(self::$cache[$path])) {
            return self::$cache[$path];
        }

        if (!file_exists($path)) return [];
        $raw = file_get_contents($path);
        if ($raw === false) return [];
        $decoded = json_decode($raw, true);
        $result = is_array($decoded) ? $decoded : [];

        self::$cache[$path] = $result;
        return $result;
    }

    /**
     * JSON ファイルに書き込む。キャッシュも同時に更新。
     */
    public static function write(string $file, array $data, string $dir = ''): void {
        $path = ($dir ?: 'data') . '/' . $file;

        $dirPath = dirname($path);
        if (!is_dir($dirPath)) {
            @mkdir($dirPath, 0755, true);
        }

        $result = file_put_contents(
            $path,
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            LOCK_EX
        );
        if ($result === false) {
            http_response_code(500);
            exit;
        }

        self::$cache[$path] = $data;
    }

    /**
     * キャッシュをクリアする。
     */
    public static function clearCache(): void {
        self::$cache = [];
    }

    /**
     * キャッシュからエントリを取得する（直接アクセス用）。
     */
    public static function getCached(string $path): ?array {
        return self::$cache[$path] ?? null;
    }

    /**
     * キャッシュにエントリをセットする（直接アクセス用）。
     */
    public static function setCached(string $path, array $data): void {
        self::$cache[$path] = $data;
    }
}
