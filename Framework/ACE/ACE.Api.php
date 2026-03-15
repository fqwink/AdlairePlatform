<?php
/**
 * Adlaire Content Engine (ACE) - API Module
 *
 * ACE = Adlaire Content Engine
 *
 * REST API ルーティング・Webhook 管理・リクエスト/レスポンスヘルパー・
 * レート制限を提供する再利用可能なフレームワークモジュール。
 * Adlaire Platform の ApiEngine / WebhookEngine から汎用化・抽出された
 * 独立コンポーネント。
 *
 * @package ACE
 * @version 1.0.0
 * @license Adlaire License Ver.2.0
 */

namespace ACE\Api;

use ACE\Admin\AuthManager;

// ============================================================================
// ApiRouter - REST API ルーティング
// ============================================================================

/**
 * REST API エンドポイントの登録とディスパッチを提供する。
 *
 * エンドポイントをハンドラとして登録し、認証要否を個別に設定可能。
 * AuthManager と連携して認証チェックを自動的に行う。
 */
class ApiRouter
{
    /** @var AuthManager 認証マネージャー */
    private AuthManager $auth;

    /** @var array<string, array{handler: callable, requires_auth: bool}> 登録済みエンドポイント */
    private array $endpoints = [];

    /**
     * コンストラクタ
     *
     * @param AuthManager $auth 認証マネージャーインスタンス
     */
    public function __construct(AuthManager $auth)
    {
        $this->auth = $auth;
    }

    /**
     * API エンドポイントを登録する。
     *
     * @param string   $name         エンドポイント名
     * @param callable $handler      ハンドラ関数 (array $params) => array
     * @param bool     $requiresAuth 認証が必要か（デフォルト: false）
     */
    public function registerEndpoint(
        string $name,
        callable $handler,
        bool $requiresAuth = false
    ): void {
        $this->endpoints[$name] = [
            'handler'       => $handler,
            'requires_auth' => $requiresAuth,
        ];
    }

    /**
     * 指定エンドポイントにリクエストをディスパッチする。
     *
     * 認証が必要なエンドポイントでは、AuthManager を使用してログイン状態を
     * チェックする。未登録のエンドポイントにはエラーレスポンスを返す。
     *
     * @param string $endpoint エンドポイント名
     * @param array  $params   リクエストパラメータ
     * @return array レスポンスデータ [ok => bool, data|error => mixed]
     */
    public function dispatch(string $endpoint, array $params): array
    {
        if (!isset($this->endpoints[$endpoint])) {
            return [
                'ok'    => false,
                'error' => '不明な API エンドポイントです: ' . $endpoint,
            ];
        }

        $config = $this->endpoints[$endpoint];

        /* 認証チェック */
        if ($config['requires_auth'] && !$this->auth->isLoggedIn()) {
            return [
                'ok'    => false,
                'error' => '認証が必要です',
            ];
        }

        try {
            $result = ($config['handler'])($params);
            return [
                'ok'   => true,
                'data' => $result,
            ];
        } catch (\Throwable $e) {
            return [
                'ok'    => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * 登録済みエンドポイントの一覧を取得する。
     *
     * @return array<int, array{name: string, requires_auth: bool}>
     */
    public function listEndpoints(): array
    {
        $result = [];

        foreach ($this->endpoints as $name => $config) {
            $result[] = [
                'name'          => $name,
                'requires_auth' => $config['requires_auth'],
            ];
        }

        return $result;
    }
}

// ============================================================================
// WebhookManager - Webhook 管理
// ============================================================================

/**
 * Outgoing Webhook の登録・管理・配信を提供する。
 *
 * コンテンツ変更イベント発生時に外部サービスへ HTTP POST で通知を送信する。
 * HMAC-SHA256 署名、SSRF 防止機能を備える。
 */
class WebhookManager
{
    /** @var string データディレクトリのパス */
    private string $dataDir;

    /** @var string Webhook 設定ファイル名 */
    private const CONFIG_FILE = 'webhooks.json';

    /** @var int HTTP 接続タイムアウト（秒） */
    private const CONNECT_TIMEOUT = 3;

    /** @var int HTTP リクエストタイムアウト（秒） */
    private const REQUEST_TIMEOUT = 5;

    /**
     * コンストラクタ
     *
     * @param string $dataDir Webhook 設定を保存するディレクトリパス
     */
    public function __construct(string $dataDir)
    {
        $this->dataDir = rtrim($dataDir, '/');

        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0755, true);
        }
    }

    /**
     * Webhook エンドポイントを追加する。
     *
     * URL バリデーションと SSRF 防止チェックを行った上で登録する。
     *
     * @param string $url    Webhook URL（http/https のみ）
     * @param string $label  表示ラベル（省略時はホスト名を使用）
     * @param string $secret HMAC-SHA256 署名用シークレット
     * @return bool 追加成功時 true
     */
    public function add(string $url, string $label = '', string $secret = ''): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        /* SSRF 防止: http/https のみ許可 */
        $scheme = strtolower(parse_url($url, PHP_URL_SCHEME) ?? '');
        if (!in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        /* SSRF 防止: プライベート IP をブロック */
        $host = parse_url($url, PHP_URL_HOST);
        if ($host !== null && $this->isPrivateHost($host)) {
            return false;
        }

        $config = $this->loadConfig();

        $config['webhooks'][] = [
            'url'        => $url,
            'label'      => $label ?: (parse_url($url, PHP_URL_HOST) ?? $url),
            'secret'     => $secret,
            'active'     => true,
            'created_at' => date('c'),
        ];

        $this->saveConfig($config);

        return true;
    }

    /**
     * Webhook を削除する。
     *
     * @param int $index 削除する Webhook のインデックス
     * @return bool 削除成功時 true
     */
    public function delete(int $index): bool
    {
        $config = $this->loadConfig();

        if (!isset($config['webhooks'][$index])) {
            return false;
        }

        array_splice($config['webhooks'], $index, 1);
        $this->saveConfig($config);

        return true;
    }

    /**
     * Webhook の有効/無効を切り替える。
     *
     * @param int $index 切り替える Webhook のインデックス
     * @return bool 切り替え成功時 true
     */
    public function toggle(int $index): bool
    {
        $config = $this->loadConfig();

        if (!isset($config['webhooks'][$index])) {
            return false;
        }

        $config['webhooks'][$index]['active']
            = !($config['webhooks'][$index]['active'] ?? true);

        $this->saveConfig($config);

        return true;
    }

    /**
     * Webhook 一覧を取得する（シークレットは伏字化）。
     *
     * @return array<int, array{index: int, url: string, label: string, has_secret: bool, active: bool, created_at: string}>
     */
    public function listWebhooks(): array
    {
        $config = $this->loadConfig();
        $result = [];

        foreach ($config['webhooks'] as $i => $wh) {
            $result[] = [
                'index'      => $i,
                'url'        => $wh['url'] ?? '',
                'label'      => $wh['label'] ?? '',
                'has_secret' => !empty($wh['secret']),
                'active'     => $wh['active'] ?? true,
                'created_at' => $wh['created_at'] ?? '',
            ];
        }

        return $result;
    }

    /**
     * イベントを全アクティブ Webhook に配信する。
     *
     * @param string $event   イベント名（例: item.created, page.updated）
     * @param array  $payload ペイロードデータ
     * @return array 配信結果の配列 [url, status, success]
     */
    public function dispatch(string $event, array $payload): array
    {
        $config = $this->loadConfig();

        if (empty($config['webhooks'])) {
            return [];
        }

        $body = json_encode([
            'event'     => $event,
            'timestamp' => date('c'),
            'data'      => $payload,
        ], JSON_UNESCAPED_UNICODE);

        $results = [];

        foreach ($config['webhooks'] as $wh) {
            if (!($wh['active'] ?? true)) {
                continue;
            }

            $url = $wh['url'] ?? '';
            if ($url === '') {
                continue;
            }

            $headers = [
                'Content-Type: application/json',
                'User-Agent: ACE-WebhookManager/1.0',
                'X-ACE-Event: ' . $event,
            ];

            /* HMAC-SHA256 署名を付与 */
            $secret = $wh['secret'] ?? '';
            if ($secret !== '') {
                $sig = hash_hmac('sha256', $body, $secret);
                $headers[] = 'X-ACE-Signature: sha256=' . $sig;
            }

            $result = $this->sendRequest($url, $body, $headers);
            $results[] = [
                'url'     => $url,
                'status'  => $result['http_code'],
                'success' => $result['http_code'] >= 200
                    && $result['http_code'] < 300,
            ];
        }

        return $results;
    }

    /**
     * 単一の Webhook をテスト配信する。
     *
     * @param int $index テスト対象の Webhook インデックス
     * @return array テスト結果 [url, status, success, time_ms]
     */
    public function test(int $index): array
    {
        $config = $this->loadConfig();

        if (!isset($config['webhooks'][$index])) {
            return [
                'url'     => '',
                'status'  => 0,
                'success' => false,
                'error'   => 'Webhook が見つかりません',
            ];
        }

        $wh = $config['webhooks'][$index];
        $url = $wh['url'] ?? '';

        if ($url === '') {
            return [
                'url'     => '',
                'status'  => 0,
                'success' => false,
                'error'   => 'Webhook URL が未設定です',
            ];
        }

        $body = json_encode([
            'event'     => 'webhook.test',
            'timestamp' => date('c'),
            'data'      => ['message' => 'テスト配信', 'index' => $index],
        ], JSON_UNESCAPED_UNICODE);

        $headers = [
            'Content-Type: application/json',
            'User-Agent: ACE-WebhookManager/1.0',
            'X-ACE-Event: webhook.test',
        ];

        $secret = $wh['secret'] ?? '';
        if ($secret !== '') {
            $headers[] = 'X-ACE-Signature: sha256='
                . hash_hmac('sha256', $body, $secret);
        }

        $result = $this->sendRequest($url, $body, $headers);

        return [
            'url'     => $url,
            'status'  => $result['http_code'],
            'success' => $result['http_code'] >= 200
                && $result['http_code'] < 300,
            'time_ms' => $result['time_ms'],
        ];
    }

    /**
     * HTTP POST リクエストを送信する。
     *
     * @param string $url     送信先 URL
     * @param string $body    リクエストボディ
     * @param array  $headers HTTP ヘッダー
     * @return array{http_code: int, time_ms: float, error: string}
     */
    private function sendRequest(string $url, string $body, array $headers): array
    {
        /* DNS リバインディング防止: 送信時にもホスト IP を再検証 */
        $host = parse_url($url, PHP_URL_HOST);
        if ($host !== null && $this->isPrivateHost($host)) {
            return ['http_code' => 0, 'time_ms' => 0, 'error' => 'SSRF blocked'];
        }

        $ch = curl_init($url);

        if ($ch === false) {
            return ['http_code' => 0, 'time_ms' => 0, 'error' => 'cURL init failed'];
        }

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::REQUEST_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_FOLLOWLOCATION => false, /* SSRF 防止: リダイレクト無効 */
        ]);

        curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $totalTime = round(
            curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000,
            2
        );

        curl_close($ch);

        return [
            'http_code' => $httpCode,
            'time_ms'   => $totalTime,
            'error'     => $error,
        ];
    }

    /**
     * ホストがプライベート/内部 IP かどうかチェックする（SSRF 防止）。
     *
     * @param string $host ホスト名または IP アドレス
     * @return bool プライベート IP の場合 true
     */
    private function isPrivateHost(string $host): bool
    {
        /* localhost 系をブロック */
        $localHosts = ['localhost', '127.0.0.1', '::1', '0.0.0.0'];
        if (in_array(strtolower($host), $localHosts, true)) {
            return true;
        }

        /* DNS 解決して IP を確認 */
        $ip = gethostbyname($host);

        /* DNS 解決失敗時はブロック（安全側に倒す） */
        if ($ip === $host) {
            return true;
        }

        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
    }

    /**
     * Webhook 設定ファイルを読み込む。
     *
     * @return array{webhooks: array}
     */
    private function loadConfig(): array
    {
        $path = $this->dataDir . '/' . self::CONFIG_FILE;

        if (!file_exists($path)) {
            return ['webhooks' => []];
        }

        $content = file_get_contents($path);
        $data = json_decode($content ?: '', true);

        if (!is_array($data)) {
            return ['webhooks' => []];
        }

        if (!isset($data['webhooks'])) {
            $data['webhooks'] = [];
        }

        return $data;
    }

    /**
     * Webhook 設定ファイルを保存する。
     *
     * @param array $config 設定データ
     */
    private function saveConfig(array $config): void
    {
        $path = $this->dataDir . '/' . self::CONFIG_FILE;
        file_put_contents(
            $path,
            json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }
}

// ============================================================================
// RestHandler - REST リクエスト/レスポンスヘルパー
// ============================================================================

/**
 * REST API のリクエスト解析とレスポンス出力を支援するスタティックヘルパー。
 *
 * JSON レスポンスの送信、エラーレスポンス、HTTP メソッドチェック、
 * リクエストボディの解析、API キーの検証、CORS ヘッダーの設定を提供する。
 */
class RestHandler
{
    /**
     * JSON 形式の成功レスポンスを出力して終了する。
     *
     * @param array $data   レスポンスデータ
     * @param int   $status HTTP ステータスコード（デフォルト: 200）
     */
    public static function jsonResponse(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');

        echo json_encode(
            ['ok' => true, 'data' => $data],
            JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
        );

        exit;
    }

    /**
     * JSON 形式のエラーレスポンスを出力して終了する。
     *
     * @param string $message エラーメッセージ
     * @param int    $status  HTTP ステータスコード（デフォルト: 400）
     */
    public static function errorResponse(string $message, int $status = 400): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');

        echo json_encode(
            ['ok' => false, 'error' => $message],
            JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
        );

        exit;
    }

    /**
     * HTTP メソッドが指定値であることを検証する。
     *
     * 一致しない場合は 405 エラーレスポンスを送信して終了する。
     *
     * @param string $method 期待する HTTP メソッド（GET, POST, PUT, DELETE 等）
     * @throws \RuntimeException メソッドが一致しない場合（exit で終了するため実際にはスローされない）
     */
    public static function requireMethod(string $method): void
    {
        $currentMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $expectedMethod = strtoupper($method);

        if ($currentMethod !== $expectedMethod) {
            self::errorResponse(
                $expectedMethod . ' メソッドが必要です',
                405
            );
        }
    }

    /**
     * リクエストの入力データを取得する。
     *
     * Content-Type が application/json の場合は JSON ボディを解析し、
     * それ以外の場合は POST パラメータを返す。
     *
     * @return array 入力データの連想配列
     */
    public static function getInput(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input');
            $data = json_decode($raw ?: '', true);
            return is_array($data) ? $data : [];
        }

        return $_POST;
    }

    /**
     * API キーを検証する。
     *
     * Authorization ヘッダーの Bearer トークンまたは直接指定されたキーを、
     * 有効なキーのリストに照合する。bcrypt ハッシュ比較に対応。
     *
     * @param string $key       検証するキー
     * @param array  $validKeys 有効なキーの配列 [['key_hash' => string, 'active' => bool], ...]
     * @return bool 有効なキーの場合 true
     */
    public static function validateApiKey(string $key, array $validKeys): bool
    {
        if ($key === '') {
            return false;
        }

        foreach ($validKeys as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            if (!isset($entry['key_hash'])) {
                continue;
            }

            if (!($entry['active'] ?? true)) {
                continue;
            }

            if (password_verify($key, $entry['key_hash'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * CORS (Cross-Origin Resource Sharing) ヘッダーを設定する。
     *
     * OPTIONS プリフライトリクエストには 204 レスポンスを返して終了する。
     *
     * @param string $allowedOrigin 許可するオリジン（デフォルト: '*'）
     */
    public static function cors(string $allowedOrigin = '*'): void
    {
        if ($allowedOrigin !== '') {
            header('Access-Control-Allow-Origin: ' . $allowedOrigin);

            /* オリジン別レスポンスのキャッシュ分離（CORS キャッシュポイズニング防止） */
            if ($allowedOrigin !== '*') {
                header('Vary: Origin');
            }
        }

        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        header('Access-Control-Max-Age: 86400');

        /* OPTIONS プリフライトリクエスト */
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }
}

// ============================================================================
// RateLimiter - API レート制限
// ============================================================================

/**
 * 識別子（IP アドレス等）ベースの API レート制限を提供する。
 *
 * スライディングウィンドウ方式で一定期間内のリクエスト数を制限する。
 * レート制限データはファイルベースで永続化される。
 */
class RateLimiter
{
    /** @var string データディレクトリのパス */
    private string $dataDir;

    /** @var int ウィンドウ内の最大リクエスト数 */
    private int $maxRequests;

    /** @var int ウィンドウの秒数 */
    private int $windowSeconds;

    /** @var string レート制限データファイル名 */
    private const DATA_FILE = 'rate_limits.json';

    /**
     * コンストラクタ
     *
     * @param string $dataDir       レート制限データを保存するディレクトリパス
     * @param int    $maxRequests   ウィンドウ内の最大リクエスト数（デフォルト: 60）
     * @param int    $windowSeconds ウィンドウの秒数（デフォルト: 60）
     */
    public function __construct(
        string $dataDir,
        int $maxRequests = 60,
        int $windowSeconds = 60
    ) {
        $this->dataDir = rtrim($dataDir, '/');
        $this->maxRequests = max(1, $maxRequests);
        $this->windowSeconds = max(1, $windowSeconds);

        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0755, true);
        }
    }

    /**
     * リクエストが許可されるかチェックし、カウントをインクリメントする。
     *
     * @param string $identifier 識別子（IP アドレス等）
     * @return bool 許可される場合 true
     */
    public function check(string $identifier): bool
    {
        $data = $this->loadData();
        $key = $this->sanitizeKey($identifier);
        $now = time();

        $entry = $data[$key] ?? ['count' => 0, 'window_start' => $now];

        /* ウィンドウの有効期限をチェック */
        if ($now - (int)$entry['window_start'] > $this->windowSeconds) {
            $entry = ['count' => 0, 'window_start' => $now];
        }

        $entry['count']++;
        $data[$key] = $entry;

        $this->saveData($data);

        return $entry['count'] <= $this->maxRequests;
    }

    /**
     * 残りのリクエスト可能回数を取得する。
     *
     * @param string $identifier 識別子（IP アドレス等）
     * @return int 残りリクエスト数（0 以上）
     */
    public function remaining(string $identifier): int
    {
        $data = $this->loadData();
        $key = $this->sanitizeKey($identifier);
        $now = time();

        $entry = $data[$key] ?? ['count' => 0, 'window_start' => $now];

        /* ウィンドウ期限切れの場合は最大値を返す */
        if ($now - (int)$entry['window_start'] > $this->windowSeconds) {
            return $this->maxRequests;
        }

        return max(0, $this->maxRequests - (int)$entry['count']);
    }

    /**
     * 指定識別子のレート制限カウントをリセットする。
     *
     * @param string $identifier 識別子（IP アドレス等）
     */
    public function reset(string $identifier): void
    {
        $data = $this->loadData();
        $key = $this->sanitizeKey($identifier);

        unset($data[$key]);

        $this->saveData($data);
    }

    /**
     * 識別子をファイルシステム安全なキーに変換する。
     *
     * @param string $identifier 元の識別子
     * @return string サニタイズ済みキー
     */
    private function sanitizeKey(string $identifier): string
    {
        return preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $identifier) ?? $identifier;
    }

    /**
     * レート制限データを読み込む。
     *
     * @return array レート制限データ
     */
    private function loadData(): array
    {
        $path = $this->dataDir . '/' . self::DATA_FILE;

        if (!file_exists($path)) {
            return [];
        }

        $content = file_get_contents($path);
        $data = json_decode($content ?: '', true);

        return is_array($data) ? $data : [];
    }

    /**
     * レート制限データを保存する。
     *
     * 保存前に期限切れエントリのクリーンアップを行う。
     *
     * @param array $data レート制限データ
     */
    private function saveData(array $data): void
    {
        $now = time();

        /* 期限切れエントリをクリーンアップ */
        $data = array_filter(
            $data,
            fn(array $entry): bool =>
                ($now - (int)($entry['window_start'] ?? 0)) <= $this->windowSeconds * 2
        );

        $path = $this->dataDir . '/' . self::DATA_FILE;
        file_put_contents(
            $path,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }
}

// ============================================================================
// WebhookService - Webhook エンジン統合ファサード
// ============================================================================

/**
 * WebhookEngine のロジックを Framework 化した静的ファサード。
 *
 * @since Ver.1.8
 */
class WebhookService {

    private const CONFIG_FILE = 'webhooks.json';

    public static function loadConfig(): array {
        $config = \APF\Utilities\JsonStorage::read(self::CONFIG_FILE, \AIS\Core\AppContext::settingsDir());
        if (empty($config['webhooks'])) $config['webhooks'] = [];
        return $config;
    }

    public static function saveConfig(array $config): void {
        \APF\Utilities\JsonStorage::write(self::CONFIG_FILE, $config, \AIS\Core\AppContext::settingsDir());
    }

    public static function addWebhook(string $url, string $label = '', array $events = [], string $secret = ''): bool {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) return false;
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

    public static function deleteWebhook(int $index): bool {
        $config = self::loadConfig();
        if (!isset($config['webhooks'][$index])) return false;
        array_splice($config['webhooks'], $index, 1);
        self::saveConfig($config);
        return true;
    }

    public static function toggleWebhook(int $index): bool {
        $config = self::loadConfig();
        if (!isset($config['webhooks'][$index])) return false;
        $config['webhooks'][$index]['active'] = !($config['webhooks'][$index]['active'] ?? true);
        self::saveConfig($config);
        return true;
    }

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

    public static function dispatch(string $event, array $payload = []): void {
        $config = self::loadConfig();
        if (empty($config['webhooks'])) return;

        $body = json_encode([
            'event' => $event, 'timestamp' => date('c'), 'data' => $payload,
        ], JSON_UNESCAPED_UNICODE);

        foreach ($config['webhooks'] as $wh) {
            if (!($wh['active'] ?? true)) {
                \AIS\System\DiagnosticsManager::log('debug', 'Webhook スキップ（非アクティブ）', ['label' => $wh['label'] ?? '', 'event' => $event]);
                continue;
            }
            $events = $wh['events'] ?? [];
            if (!empty($events) && !in_array($event, $events, true)) {
                \AIS\System\DiagnosticsManager::log('debug', 'Webhook スキップ（イベント不一致）', ['label' => $wh['label'] ?? '', 'event' => $event]);
                continue;
            }
            $url = $wh['url'] ?? '';
            if ($url === '') continue;

            $headers = [
                'Content-Type: application/json',
                'User-Agent: AdlairePlatform/' . (defined('AP_VERSION') ? AP_VERSION : '1.0'),
                'X-AP-Event: ' . $event,
            ];
            $secret = $wh['secret'] ?? '';
            if ($secret !== '') {
                $headers[] = 'X-AP-Signature: sha256=' . hash_hmac('sha256', $body, $secret);
            }
            self::sendAsync($url, $body, $headers);
        }
    }

    private static function sendAsync(string $url, string $body, array $headers): void {
        $host = parse_url($url, PHP_URL_HOST);
        if ($host !== null && self::isPrivateHost($host)) {
            \APF\Utilities\Logger::critical('Webhook SSRF blocked (DNS rebinding)', ['url_host' => parse_url($url, PHP_URL_HOST) ?? '']);
            \AIS\System\DiagnosticsManager::log('security', 'SSRF ブロック (DNS rebinding)', ['url_host' => parse_url($url, PHP_URL_HOST) ?? '']);
            return;
        }
        $ch = curl_init($url);
        if ($ch === false) return;
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5, CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $totalTime = round(curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000, 2);
        curl_close($ch);

        \AIS\System\DiagnosticsManager::log('debug', 'Webhook 配信完了', ['url_host' => parse_url($url, PHP_URL_HOST) ?? '', 'http_code' => $httpCode, 'payload_bytes' => strlen($body), 'total_time_ms' => $totalTime]);
        if ($httpCode < 200 || $httpCode >= 300) {
            \APF\Utilities\Logger::error('WebhookService: delivery failed', ['url_host' => parse_url($url, PHP_URL_HOST) ?? '', 'http_code' => $httpCode]);
            \AIS\System\DiagnosticsManager::logIntegrationError('Webhook', $httpCode, $curlError ?: ('配信失敗: ' . parse_url($url, PHP_URL_HOST)));
        } elseif ($totalTime > 3000) {
            \AIS\System\DiagnosticsManager::logSlowExecution('Webhook::sendAsync(' . parse_url($url, PHP_URL_HOST) . ')', $totalTime, 3000);
        }
    }

    public static function verifySignature(string $payload, string $signature, string $secret): bool {
        if ($secret === '' || $signature === '') return false;
        $expected = 'sha256=' . hash_hmac('sha256', $payload, $secret);
        return hash_equals($expected, $signature);
    }

    private static function isPrivateHost(string $host): bool {
        if (in_array(strtolower($host), ['localhost', '127.0.0.1', '::1', '0.0.0.0'], true)) return true;
        $ip = gethostbyname($host);
        if ($ip === $host) return true;
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }
}

// ============================================================================
// ApiService - REST API エンジン統合ファサード
// ============================================================================

/**
 * ApiEngine のロジックを Framework 化した静的ファサード。
 *
 * ヘッドレス CMS API エントリーポイント・認証・レート制限・
 * 22+ エンドポイントハンドラを統合する。
 *
 * @since Ver.1.8
 */
class ApiService {

    private const SLUG_PATTERN       = '/^[a-zA-Z0-9_\-]+$/';
    private const PREVIEW_LENGTH     = 120;
    private const SEARCH_MAX_LEN     = 100;
    private const NAME_MAX_LEN       = 100;
    private const MSG_MAX_LEN        = 5000;
    private const API_KEYS_FILE      = 'api_keys.json';
    private const CONTACT_MAX_ATTEMPTS = 5;
    private const CONTACT_LOCKOUT_SEC  = 900;
    private const API_RATE_MAX       = 60;
    private const API_RATE_WINDOW    = 60;
    private const WRITE_RATE_MAX     = 30;
    private const WRITE_RATE_WINDOW  = 60;
    private static bool $authenticatedViaApiKey = false;

    /* ── エントリーポイント ── */

    public static function handle(): void {
        $action = $_GET['ap_api'] ?? null;
        if ($action === null) return;

        $allowedOrigin = self::getCorsOrigin();
        if ($allowedOrigin !== '') {
            header('Access-Control-Allow-Origin: ' . $allowedOrigin);
            if ($allowedOrigin !== '*') header('Vary: Origin');
        }
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        header('Access-Control-Max-Age: 86400');

        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        header('Content-Type: application/json; charset=UTF-8');

        $publicActions = ['pages', 'page', 'settings', 'search', 'collections', 'collection', 'item'];
        if (in_array($action, $publicActions, true)) {
            self::checkApiRate();
            if ($action !== 'search') {
                $response = \AIS\System\ApiCache::serve($action, $_GET);
                if ($response !== null) { $response->send(); exit; }
            }
        }

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
            'health'       => self::handleHealthCheck(),
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
            default        => self::jsonError(\AIS\Core\I18n::t('api.error.unknown_endpoint'), 400),
        };
    }

    /* ── ヘルスチェック ── */

    private static function handleHealthCheck(): never {
        $detailed = false;
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $m)) {
            $keys = \APF\Utilities\JsonStorage::read('api_keys.json', \AIS\Core\AppContext::settingsDir());
            foreach ($keys as $k) {
                if (password_verify($m[1], $k['key_hash'] ?? '')) { $detailed = true; break; }
            }
        }
        $health = \AIS\System\DiagnosticsManager::healthCheck($detailed);
        self::jsonResponse(true, $health);
    }

    /* ── API キー認証 ── */

    private static function requireAuth(string $method): void {
        if (!self::isAuthenticated()) {
            self::jsonError(\AIS\Core\I18n::t('api.error.auth_required'), 401);
        }
        self::checkWriteApiRate();
        if (\ACE\Admin\AdminManager::isLoggedIn() && !self::$authenticatedViaApiKey) {
            \ACE\Admin\AdminManager::verifyCsrf();
        }
        self::$method();
    }

    private static function isAuthenticated(): bool {
        if (\ACE\Admin\AdminManager::isLoggedIn()) return true;
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $m)) {
            $valid = self::validateApiKey($m[1]);
            if ($valid) self::$authenticatedViaApiKey = true;
            return $valid;
        }
        return false;
    }

    private static function validateApiKey(string $key): bool {
        $keys = \APF\Utilities\JsonStorage::read(self::API_KEYS_FILE, \AIS\Core\AppContext::settingsDir());
        $inputPrefix12 = substr($key, 0, 12) . '...';
        $inputPrefix7  = substr($key, 0, 7) . '...';
        foreach ($keys as $entry) {
            if (!is_array($entry) || !isset($entry['key_hash']) || !($entry['active'] ?? true)) continue;
            if (($entry['key_prefix'] ?? '') === $inputPrefix12 && password_verify($key, $entry['key_hash'])) return true;
        }
        foreach ($keys as $entry) {
            if (!is_array($entry) || !isset($entry['key_hash']) || !($entry['active'] ?? true)) continue;
            $prefix = $entry['key_prefix'] ?? '';
            if ($prefix === $inputPrefix7 && $prefix !== $inputPrefix12 && password_verify($key, $entry['key_hash'])) return true;
        }
        foreach ($keys as $entry) {
            if (!is_array($entry) || !isset($entry['key_hash']) || !($entry['active'] ?? true)) continue;
            if (isset($entry['key_prefix']) && $entry['key_prefix'] !== '') continue;
            if (password_verify($key, $entry['key_hash'])) return true;
        }
        return false;
    }

    public static function generateApiKey(string $label = ''): string {
        $key = 'ap_' . bin2hex(random_bytes(24));
        $dir = \AIS\Core\AppContext::settingsDir();
        $keys = \APF\Utilities\JsonStorage::read(self::API_KEYS_FILE, $dir);
        $keys[] = [
            'label' => $label ?: 'API Key ' . (count($keys) + 1),
            'key_hash' => password_hash($key, PASSWORD_BCRYPT),
            'key_prefix' => substr($key, 0, 12) . '...',
            'created_at' => date('c'), 'active' => true,
        ];
        \APF\Utilities\JsonStorage::write(self::API_KEYS_FILE, $keys, $dir);
        return $key;
    }

    public static function listApiKeys(): array {
        $keys = \APF\Utilities\JsonStorage::read(self::API_KEYS_FILE, \AIS\Core\AppContext::settingsDir());
        $result = [];
        foreach ($keys as $i => $entry) {
            if (!is_array($entry)) continue;
            $result[] = [
                'index' => $i, 'label' => $entry['label'] ?? '',
                'key_prefix' => $entry['key_prefix'] ?? '',
                'created_at' => $entry['created_at'] ?? '', 'active' => $entry['active'] ?? true,
            ];
        }
        return $result;
    }

    public static function deleteApiKey(int $index): bool {
        $dir = \AIS\Core\AppContext::settingsDir();
        $keys = \APF\Utilities\JsonStorage::read(self::API_KEYS_FILE, $dir);
        if (!isset($keys[$index])) return false;
        array_splice($keys, $index, 1);
        \APF\Utilities\JsonStorage::write(self::API_KEYS_FILE, $keys, $dir);
        return true;
    }

    /* ── 公開エンドポイント ── */

    private static function handlePages(): void {
        $pages = \APF\Utilities\JsonStorage::read('pages.json', \AIS\Core\AppContext::contentDir());
        $list = [];
        foreach ($pages as $slug => $content) {
            $list[] = ['slug' => $slug, 'preview' => self::makePreview((string)$content)];
        }
        $p = self::getPagination();
        self::paginatedResponse($list, $p['limit'], $p['offset']);
    }

    private static function handleGetPage(): void {
        $slug = $_GET['slug'] ?? '';
        if (!preg_match(self::SLUG_PATTERN, $slug)) self::jsonError(\AIS\Core\I18n::t('api.error.invalid_slug'), 400);
        $pages = \APF\Utilities\JsonStorage::read('pages.json', \AIS\Core\AppContext::contentDir());
        if (!isset($pages[$slug])) self::jsonError(\AIS\Core\I18n::t('api.error.page_not_found'), 404);
        self::jsonResponse(true, ['slug' => $slug, 'content' => $pages[$slug]]);
    }

    private static function getSettings(): array {
        $settings = \APF\Utilities\JsonStorage::read('settings.json', \AIS\Core\AppContext::settingsDir());
        return ['title' => $settings['title'] ?? '', 'description' => $settings['description'] ?? '', 'keywords' => $settings['keywords'] ?? ''];
    }

    private static function handleSearch(): void {
        $q = trim($_GET['q'] ?? '');
        if ($q === '' || mb_strlen($q, 'UTF-8') > self::SEARCH_MAX_LEN) {
            self::jsonError(\AIS\Core\I18n::t('api.error.search_query', ['max' => self::SEARCH_MAX_LEN]), 400);
        }
        $results = [];
        $query = mb_strtolower($q, 'UTF-8');

        $pages = \APF\Utilities\JsonStorage::read('pages.json', \AIS\Core\AppContext::contentDir());
        foreach ($pages as $slug => $content) {
            $text = mb_strtolower(strip_tags((string)$content), 'UTF-8');
            $pos = mb_strpos($text, $query, 0, 'UTF-8');
            if ($pos === false) continue;
            $start = max(0, $pos - 30);
            $preview = mb_substr($text, $start, 100, 'UTF-8');
            if ($start > 0) $preview = '...' . $preview;
            if ($start + 100 < mb_strlen($text, 'UTF-8')) $preview .= '...';
            $results[] = ['slug' => $slug, 'type' => 'page', 'preview' => $preview];
        }

        if (\ACE\Core\CollectionService::isEnabled()) {
            $collections = \ACE\Core\CollectionService::listCollections();
            foreach ($collections as $col) {
                $items = \ACE\Core\CollectionService::getItems($col['name']);
                foreach ($items as $itemSlug => $item) {
                    $title = mb_strtolower($item['meta']['title'] ?? '', 'UTF-8');
                    $text = mb_strtolower(strip_tags($item['html']), 'UTF-8');
                    $pos = mb_strpos($title, $query, 0, 'UTF-8');
                    if ($pos === false) $pos = mb_strpos($text, $query, 0, 'UTF-8');
                    if ($pos === false) continue;
                    $start = max(0, $pos - 30);
                    $preview = mb_substr($text, $start, 100, 'UTF-8');
                    if ($start > 0) $preview = '...' . $preview;
                    if ($start + 100 < mb_strlen($text, 'UTF-8')) $preview .= '...';
                    $results[] = ['slug' => $col['name'] . '/' . $itemSlug, 'type' => 'collection', 'collection' => $col['name'], 'title' => $item['meta']['title'] ?? $itemSlug, 'preview' => $preview];
                }
            }
        }

        \AIS\System\DiagnosticsManager::log('debug', '検索実行サマリー', ['query_length' => mb_strlen($q, 'UTF-8'), 'total_results' => count($results)]);

        $p = self::getPagination();
        $total = count($results);
        $paged = array_slice($results, $p['offset'], $p['limit']);
        echo json_encode(['ok' => true, 'data' => ['query' => $q, 'results' => array_values($paged)], 'meta' => ['total' => $total, 'limit' => $p['limit'], 'offset' => $p['offset']]], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
        exit;
    }

    private static function handleContact(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') self::jsonError('POST メソッドが必要です', 405);
        if (!empty($_POST['website'])) {
            \AIS\System\DiagnosticsManager::log('security', 'ハニーポット検知（ボット疑い）');
            self::jsonResponse(true, ['message' => '送信しました。']);
        }
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $message = trim($_POST['message'] ?? '');
        if ($name === '' || mb_strlen($name, 'UTF-8') > self::NAME_MAX_LEN) self::jsonError(\AIS\Core\I18n::t('api.error.name_required', ['max' => self::NAME_MAX_LEN]));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) self::jsonError(\AIS\Core\I18n::t('api.error.invalid_email'));
        if ($message === '' || mb_strlen($message, 'UTF-8') > self::MSG_MAX_LEN) self::jsonError(\AIS\Core\I18n::t('api.error.message_required', ['max' => self::MSG_MAX_LEN]));
        self::checkContactRate();

        $settings = \APF\Utilities\JsonStorage::read('settings.json', \AIS\Core\AppContext::settingsDir());
        $to = $settings['contact_email'] ?? '';
        if ($to === '') self::jsonError(\AIS\Core\I18n::t('api.error.no_contact_email'), 500);

        $safeName = str_replace(["\r", "\n"], '', $name);
        $safeEmail = str_replace(["\r", "\n"], '', $email);
        if ($safeName !== $name || $safeEmail !== $email) {
            \AIS\System\DiagnosticsManager::log('security', 'メールヘッダインジェクション試行検出');
        }

        try {
            if (!\AIS\Deployment\MailerService::sendContact($to, $name, $email, $message, $settings['title'] ?? 'AP')) {
                $safeTitle = str_replace(["\r", "\n"], '', $settings['title'] ?? 'AP');
                $subject = '【' . $safeTitle . '】お問い合わせ: ' . $safeName;
                $body = "名前: {$safeName}\nメール: {$safeEmail}\n\n{$message}";
                $headers = "From: {$safeEmail}\r\nReply-To: {$safeEmail}\r\nContent-Type: text/plain; charset=UTF-8";
                if (!@mail($to, $subject, $body, $headers)) {
                    \AIS\System\DiagnosticsManager::logIntegrationError('mail()', 0, 'コンタクトフォームメール送信失敗');
                    self::jsonError(\AIS\Core\I18n::t('api.error.mail_failed'), 500);
                }
            }
        } catch (\Throwable $e) {
            \APF\Utilities\Logger::error('コンタクトフォーム送信中にエラー', ['engine' => 'ApiService', 'error' => $e->getMessage()]);
            self::jsonError('送信処理中にエラーが発生しました', 500);
        }

        \ACE\Admin\AdminManager::logActivity('お問い合わせ受信: ' . $safeName . ' <' . $safeEmail . '>');

        if (\AIS\Deployment\GitService::isEnabled()) {
            $gitCfg = \AIS\Deployment\GitService::loadConfig();
            if (!empty($gitCfg['issues_enabled'])) {
                \AIS\Deployment\GitService::createIssue('お問い合わせ: ' . $safeName, "**名前**: {$safeName}\n**メール**: {$safeEmail}\n\n{$message}", ['contact']);
            }
        }
        self::jsonResponse(true, ['message' => '送信しました。']);
    }

    private static function handleCollections(): void {
        if (!\ACE\Core\CollectionService::isEnabled()) self::jsonResponse(true, []);
        self::jsonResponse(true, \ACE\Core\CollectionService::listCollections());
    }

    private static function handleCollection(): void {
        $name = $_GET['name'] ?? '';
        if (!preg_match(self::SLUG_PATTERN, $name)) self::jsonError('不正なコレクション名です', 400);
        $items = \ACE\Core\CollectionService::getItems($name);
        $list = [];
        foreach ($items as $slug => $item) {
            $list[] = ['slug' => $slug, 'meta' => $item['meta'], 'preview' => self::makePreview($item['html'])];
        }
        $p = self::getPagination();
        self::paginatedResponse($list, $p['limit'], $p['offset']);
    }

    private static function handleItem(): void {
        $collection = $_GET['collection'] ?? '';
        $slug = $_GET['slug'] ?? '';
        if (!preg_match(self::SLUG_PATTERN, $collection) || !preg_match(self::SLUG_PATTERN, $slug)) self::jsonError('不正なパラメータです', 400);
        $item = \ACE\Core\CollectionService::getItem($collection, $slug);
        if ($item === null) self::jsonError('アイテムが見つかりません', 404);
        self::jsonResponse(true, ['collection' => $collection, 'slug' => $slug, 'meta' => $item['meta'], 'content' => $item['html'], 'markdown' => $item['body']]);
    }

    /* ── 管理エンドポイント ── */

    private static function handleItemUpsert(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') self::jsonError('POST メソッドが必要です', 405);
        $input = self::getJsonInput();
        $collection = $input['collection'] ?? '';
        $slug = $input['slug'] ?? '';
        $title = $input['title'] ?? $slug;
        $body = $input['body'] ?? '';
        $meta = $input['meta'] ?? [];
        if (!preg_match(self::SLUG_PATTERN, $collection) || !preg_match(self::SLUG_PATTERN, $slug)) self::jsonError('collection と slug は必須です（英数字・ハイフン・アンダースコアのみ）');
        if (!is_array($meta)) $meta = [];
        $meta['title'] = $title;
        if (!\ACE\Core\CollectionService::saveItem($collection, $slug, $meta, $body)) self::jsonError('アイテムの保存に失敗しました');
        \ACE\Admin\AdminManager::logActivity("API: アイテム保存 {$collection}/{$slug}");
        WebhookService::dispatch('item.updated', ['collection' => $collection, 'slug' => $slug]);
        \AIS\System\ApiCache::invalidateContent();
        self::jsonResponse(true, ['collection' => $collection, 'slug' => $slug, 'message' => '保存しました']);
    }

    private static function handleItemDelete(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') self::jsonError('POST メソッドが必要です', 405);
        $input = self::getJsonInput();
        $collection = $input['collection'] ?? '';
        $slug = $input['slug'] ?? '';
        if (!preg_match(self::SLUG_PATTERN, $collection) || !preg_match(self::SLUG_PATTERN, $slug)) self::jsonError('collection と slug は必須です');
        if (!\ACE\Core\CollectionService::deleteItem($collection, $slug)) self::jsonError('アイテムの削除に失敗しました', 404);
        \ACE\Admin\AdminManager::logActivity("API: アイテム削除 {$collection}/{$slug}");
        WebhookService::dispatch('item.deleted', ['collection' => $collection, 'slug' => $slug]);
        \AIS\System\ApiCache::invalidateContent();
        self::jsonResponse(true, ['deleted' => "{$collection}/{$slug}"]);
    }

    private static function handlePageUpsert(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') self::jsonError('POST メソッドが必要です', 405);
        $input = self::getJsonInput();
        $slug = $input['slug'] ?? '';
        $content = $input['content'] ?? '';
        if (!preg_match(self::SLUG_PATTERN, $slug)) self::jsonError('不正な slug です（英数字・ハイフン・アンダースコアのみ）');
        $dir = \AIS\Core\AppContext::contentDir();
        $pages = \APF\Utilities\JsonStorage::read('pages.json', $dir);
        $pages[$slug] = $content;
        \APF\Utilities\JsonStorage::write('pages.json', $pages, $dir);
        \ACE\Admin\AdminManager::logActivity("API: ページ保存 {$slug}");
        WebhookService::dispatch('page.updated', ['slug' => $slug]);
        \AIS\System\ApiCache::invalidateContent();
        self::jsonResponse(true, ['slug' => $slug, 'message' => '保存しました']);
    }

    private static function handlePageDelete(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') self::jsonError('POST メソッドが必要です', 405);
        $input = self::getJsonInput();
        $slug = $input['slug'] ?? '';
        if (!preg_match(self::SLUG_PATTERN, $slug)) self::jsonError('不正な slug です');
        $dir = \AIS\Core\AppContext::contentDir();
        $pages = \APF\Utilities\JsonStorage::read('pages.json', $dir);
        if (!isset($pages[$slug])) self::jsonError('ページが見つかりません', 404);
        unset($pages[$slug]);
        \APF\Utilities\JsonStorage::write('pages.json', $pages, $dir);
        \ACE\Admin\AdminManager::logActivity("API: ページ削除 {$slug}");
        \AIS\System\ApiCache::invalidateContent();
        WebhookService::dispatch('page.deleted', ['slug' => $slug]);
        self::jsonResponse(true, ['deleted' => $slug]);
    }

    private static function handleApiKeys(): void {
        $method = $_SERVER['REQUEST_METHOD'];
        if ($method === 'GET') self::jsonResponse(true, self::listApiKeys());
        if ($method !== 'POST') self::jsonError('GET または POST メソッドが必要です', 405);
        $input = self::getJsonInput();
        $subAction = $input['action'] ?? 'generate';
        if ($subAction === 'generate') {
            $key = self::generateApiKey($input['label'] ?? '');
            self::jsonResponse(true, ['key' => $key, 'message' => 'API キーを生成しました。このキーは一度しか表示されません。安全な場所に保存してください。']);
        }
        if ($subAction === 'delete') {
            if (!self::deleteApiKey((int)($input['index'] ?? -1))) self::jsonError('API キーの削除に失敗しました');
            self::jsonResponse(true, ['message' => 'API キーを削除しました']);
        }
        self::jsonError('不明なアクションです');
    }

    private static function handleWebhook(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') self::jsonError('POST メソッドが必要です', 405);
        $payload = file_get_contents('php://input');
        if ($payload === false || $payload === '') self::jsonError('ペイロードが空です', 400);
        $cfg = \AIS\Deployment\GitService::loadConfig();
        $secret = $cfg['webhook_secret'] ?? '';
        if ($secret === '') self::jsonError('Webhook シークレットが設定されていません。ダッシュボードで設定してください。', 403);
        $sigHeader = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
        if ($sigHeader === '') self::jsonError('署名がありません', 403);
        if (!hash_equals('sha256=' . hash_hmac('sha256', $payload, $secret), $sigHeader)) self::jsonError('署名が不正です', 403);
        $data = json_decode($payload, true);
        if (!is_array($data)) self::jsonError('無効な JSON ペイロードです', 400);
        $event = $_SERVER['HTTP_X_GITHUB_EVENT'] ?? '';
        if ($event === 'push') {
            $branch = $cfg['branch'] ?? 'main';
            if (($data['ref'] ?? '') !== 'refs/heads/' . $branch) self::jsonResponse(true, ['message' => '対象外のブランチです', 'skipped' => true]);
            if (\AIS\Deployment\GitService::isEnabled()) {
                $result = \AIS\Deployment\GitService::pull();
                \ACE\Admin\AdminManager::logActivity('Webhook: Push 受信 → 自動 Pull 実行');
                self::jsonResponse(true, $result);
            }
            self::jsonResponse(true, ['message' => 'Git 連携が無効です', 'skipped' => true]);
        }
        if ($event === 'ping') self::jsonResponse(true, ['message' => 'pong']);
        self::jsonResponse(true, ['message' => '未対応のイベントです: ' . $event, 'skipped' => true]);
    }

    /* ── メディア管理 ── */

    private static function handleMediaList(): void {
        $dir = 'uploads/';
        if (!is_dir($dir)) { self::jsonResponse(true, ['files' => [], 'total' => 0]); }
        $files = [];
        foreach (glob($dir . '*') ?: [] as $f) {
            if (is_dir($f)) continue;
            $base = basename($f);
            if ($base === '.htaccess') continue;
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $files[] = ['name' => $base, 'url' => $dir . $base, 'size' => filesize($f), 'mime' => $finfo->file($f), 'created_at' => date('c', filectime($f) ?: time())];
        }
        usort($files, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
        $p = self::getPagination();
        echo json_encode(['ok' => true, 'data' => ['files' => array_slice($files, $p['offset'], $p['limit'])], 'meta' => ['total' => count($files), 'limit' => $p['limit'], 'offset' => $p['offset']]], JSON_UNESCAPED_UNICODE);
        exit;
    }

    private static function handleMediaUpload(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') self::jsonError('POST メソッドが必要です', 405);
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) self::jsonError('ファイルエラー', 400);
        $file = $_FILES['file'];
        if ($file['size'] > 5 * 1024 * 1024) self::jsonError('ファイルサイズが上限（5MB）を超えています', 400);
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        $ext_map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];
        if (!isset($ext_map[$mime])) self::jsonError('許可されていないファイル形式です', 400);
        $dir = 'uploads/';
        if (!\APF\Utilities\FileSystem::ensureDir($dir)) self::jsonError('アップロードディレクトリの作成に失敗しました', 500);
        $filename = bin2hex(random_bytes(12)) . '.' . $ext_map[$mime];
        if (!move_uploaded_file($file['tmp_name'], $dir . $filename)) self::jsonError('ファイル保存に失敗しました', 500);
        try {
            \ASG\Utilities\ImageService::optimize($dir . $filename);
        } catch (\Throwable $e) {
            \APF\Utilities\Logger::error('メディアアップロード最適化中にエラー', ['engine' => 'ApiService', 'file' => $filename, 'error' => $e->getMessage()]);
        }
        \ACE\Admin\AdminManager::logActivity("API: メディアアップロード {$filename}");
        self::jsonResponse(true, ['name' => $filename, 'url' => $dir . $filename, 'mime' => $mime, 'size' => filesize($dir . $filename)]);
    }

    private static function handleMediaDelete(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') self::jsonError('POST メソッドが必要です', 405);
        $input = self::getJsonInput();
        $name = $input['name'] ?? '';
        if ($name === '' || str_contains($name, '/') || str_contains($name, '..')) self::jsonError('不正なファイル名です', 400);
        $path = 'uploads/' . $name;
        if (!file_exists($path)) self::jsonError('ファイルが見つかりません', 404);
        if (!unlink($path)) self::jsonError('ファイルの削除に失敗しました', 500);
        $thumbPath = 'uploads/thumb/' . $name;
        if (file_exists($thumbPath)) @unlink($thumbPath);
        \ACE\Admin\AdminManager::logActivity("API: メディア削除 {$name}");
        self::jsonResponse(true, ['deleted' => $name]);
    }

    /* ── プレビュー・エクスポート・インポート ── */

    private static function handlePreview(): void {
        $collection = $_GET['collection'] ?? '';
        $slug = $_GET['slug'] ?? '';
        if (!preg_match(self::SLUG_PATTERN, $collection) || !preg_match(self::SLUG_PATTERN, $slug)) self::jsonError('collection と slug は必須です', 400);
        $item = \ACE\Core\CollectionService::getItem($collection, $slug);
        if ($item === null) self::jsonError('アイテムが見つかりません', 404);
        self::jsonResponse(true, ['collection' => $collection, 'slug' => $slug, 'meta' => $item['meta'], 'content' => $item['html'], 'markdown' => $item['body'], 'status' => $item['meta']['status'] ?? 'published', 'preview' => true]);
    }

    private static function handleExport(): void {
        $collection = $_GET['collection'] ?? '';
        $format = $_GET['format'] ?? 'json';
        if (!preg_match(self::SLUG_PATTERN, $collection)) self::jsonError('コレクション名を指定してください', 400);
        $items = \ACE\Core\CollectionService::getAllItems($collection);
        if ($format === 'csv') self::exportCsv($collection, $items);
        $exportData = [];
        foreach ($items as $slug => $item) {
            $exportData[] = ['slug' => $slug, 'meta' => $item['meta'], 'body' => $item['body']];
        }
        header('Content-Disposition: attachment; filename="' . $collection . '.json"');
        self::jsonResponse(true, ['collection' => $collection, 'count' => count($exportData), 'items' => $exportData]);
    }

    private static function exportCsv(string $collection, array $items): never {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $collection . '.csv"');
        $output = fopen('php://output', 'w');
        if ($output === false) self::jsonError('CSV 出力に失敗しました', 500);
        fwrite($output, "\xEF\xBB\xBF");
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

    private static function handleImport(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') self::jsonError('POST メソッドが必要です', 405);
        $input = self::getJsonInput();
        $collection = $input['collection'] ?? '';
        $items = $input['items'] ?? [];
        if (!preg_match(self::SLUG_PATTERN, $collection)) self::jsonError('コレクション名を指定してください', 400);
        if (!is_array($items) || empty($items)) self::jsonError('インポートするアイテムが空です', 400);
        $def = \ACE\Core\CollectionService::getCollectionDef($collection);
        if ($def === null) self::jsonError('コレクションが存在しません', 404);
        $imported = 0;
        $errors = [];
        foreach ($items as $item) {
            $slug = $item['slug'] ?? '';
            if (!preg_match(self::SLUG_PATTERN, $slug)) { $errors[] = "不正なスラッグ: {$slug}"; continue; }
            if (\ACE\Core\CollectionService::saveItem($collection, $slug, $item['meta'] ?? [], $item['body'] ?? '')) { $imported++; } else { $errors[] = "保存失敗: {$slug}"; }
        }
        \ACE\Admin\AdminManager::logActivity("API: インポート {$collection} ({$imported}件)");
        if ($imported > 0) WebhookService::dispatch('item.updated', ['collection' => $collection, 'imported' => $imported]);
        \AIS\System\ApiCache::invalidateContent();
        self::jsonResponse(true, ['collection' => $collection, 'imported' => $imported, 'errors' => $errors]);
    }

    /* ── キャッシュ・Webhook 管理 ── */

    private static function handleCacheClear(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') self::jsonError('POST メソッドが必要です', 405);
        \AIS\System\ApiCache::clear();
        self::jsonResponse(true, ['message' => 'キャッシュをクリアしました']);
    }

    private static function handleCacheStats(): void {
        self::jsonResponse(true, \AIS\System\ApiCache::getStats());
    }

    private static function handleWebhooks(): void {
        self::jsonResponse(true, WebhookService::listWebhooks());
    }

    /* ── ユーティリティ ── */

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
        $json = json_encode(['ok' => $ok, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
        if ($ok) {
            $action = $_GET['ap_api'] ?? '';
            $cacheable = ['pages', 'page', 'settings', 'collections', 'collection', 'item'];
            if (in_array($action, $cacheable, true)) {
                \AIS\System\ApiCache::store($action, $_GET, $json);
            }
        }
        echo $json;
        exit;
    }

    private static function jsonError(string $message, int $status = 400): never {
        http_response_code($status);
        echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
        exit;
    }

    private static function getCorsOrigin(): string {
        $settings = \APF\Utilities\JsonStorage::read('settings.json', \AIS\Core\AppContext::settingsDir());
        $allowed = $settings['cors_origins'] ?? [];
        if (empty($allowed) || !is_array($allowed)) return '*';
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if ($origin !== '' && in_array($origin, $allowed, true)) return $origin;
        return '';
    }

    private static function checkApiRate(): void {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = 'api_rate_' . $ip;
        $dir = \AIS\Core\AppContext::settingsDir();
        $data = \APF\Utilities\JsonStorage::read('login_attempts.json', $dir);
        $entry = $data[$key] ?? ['count' => 0, 'window_start' => 0];
        $now = time();
        if ($now - (int)$entry['window_start'] > self::API_RATE_WINDOW) {
            $entry = ['count' => 0, 'window_start' => $now];
        }
        $entry['count']++;
        $data[$key] = $entry;
        \APF\Utilities\JsonStorage::write('login_attempts.json', $data, $dir);
        $remaining = max(0, self::API_RATE_MAX - $entry['count']);
        header('X-RateLimit-Limit: ' . self::API_RATE_MAX);
        header('X-RateLimit-Remaining: ' . $remaining);
        header('X-RateLimit-Reset: ' . ((int)$entry['window_start'] + self::API_RATE_WINDOW));
        if ($entry['count'] > self::API_RATE_MAX) {
            \AIS\System\DiagnosticsManager::log('security', 'API レート制限発動', ['endpoint' => $_GET['ap_api'] ?? '', 'count' => $entry['count']]);
            http_response_code(429);
            header('Retry-After: ' . self::API_RATE_WINDOW);
            echo json_encode(['ok' => false, 'error' => 'リクエスト制限を超えました。しばらくしてから再度お試しください。'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
            exit;
        }
    }

    private static function checkWriteApiRate(): void {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = 'write_rate_' . $ip;
        $dir = \AIS\Core\AppContext::settingsDir();
        $data = \APF\Utilities\JsonStorage::read('login_attempts.json', $dir);
        $entry = $data[$key] ?? ['count' => 0, 'window_start' => 0];
        $now = time();
        if ($now - (int)$entry['window_start'] > self::WRITE_RATE_WINDOW) {
            $entry = ['count' => 0, 'window_start' => $now];
        }
        $entry['count']++;
        $data[$key] = $entry;
        \APF\Utilities\JsonStorage::write('login_attempts.json', $data, $dir);
        if ($entry['count'] > self::WRITE_RATE_MAX) {
            \AIS\System\DiagnosticsManager::log('security', 'Write API レート制限発動', ['endpoint' => $_GET['ap_api'] ?? '', 'ip' => $ip]);
            http_response_code(429);
            header('Retry-After: ' . self::WRITE_RATE_WINDOW);
            echo json_encode(['ok' => false, 'error' => '書き込みリクエスト制限を超えました。しばらくしてから再度お試しください。'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
            exit;
        }
    }

    private static function checkContactRate(): void {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = 'contact_' . $ip;
        $dir = \AIS\Core\AppContext::settingsDir();
        $data = \APF\Utilities\JsonStorage::read('login_attempts.json', $dir);
        $attempts = $data[$key] ?? ['count' => 0, 'locked_until' => 0];
        if (time() < (int)$attempts['locked_until']) {
            http_response_code(429);
            echo json_encode(['ok' => false, 'error' => '送信回数が上限を超えました。しばらくしてから再度お試しください。'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
            exit;
        }
        $attempts['count']++;
        if ($attempts['count'] >= self::CONTACT_MAX_ATTEMPTS) {
            $attempts['locked_until'] = time() + self::CONTACT_LOCKOUT_SEC;
            $attempts['count'] = 0;
        }
        $data[$key] = $attempts;
        \APF\Utilities\JsonStorage::write('login_attempts.json', $data, $dir);
    }

    private static function getPagination(int $defaultLimit = 50, int $maxLimit = 100): array {
        $limit = min(max((int)($_GET['limit'] ?? $defaultLimit), 1), $maxLimit);
        $offset = max((int)($_GET['offset'] ?? 0), 0);
        return ['limit' => $limit, 'offset' => $offset];
    }

    private static function paginatedResponse(array $allItems, int $limit, int $offset): never {
        $total = count($allItems);
        echo json_encode(['ok' => true, 'data' => array_values(array_slice($allItems, $offset, $limit)), 'meta' => ['total' => $total, 'limit' => $limit, 'offset' => $offset]], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
        exit;
    }
}
