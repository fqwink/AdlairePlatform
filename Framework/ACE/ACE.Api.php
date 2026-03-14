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
