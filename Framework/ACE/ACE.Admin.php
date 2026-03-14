<?php
/**
 * Adlaire Content Engine (ACE) - Admin Module
 *
 * ACE = Adlaire Content Engine
 *
 * 管理パネル・ユーザー管理・認証・アクティビティログを提供する
 * 再利用可能なフレームワークモジュール。Adlaire Platform の AdminEngine から
 * 汎用化・抽出された独立コンポーネント。
 *
 * @package ACE
 * @version 1.0.0
 * @license Adlaire License Ver.2.0
 */

namespace ACE\Admin;

// ============================================================================
// AdminPanel - 管理ダッシュボード
// ============================================================================

/**
 * 管理ダッシュボードの描画とアクションハンドリングを提供する。
 *
 * テンプレートベースのダッシュボード・ログイン画面の描画、
 * 管理アクションのディスパッチ機能を備える。
 */
class AdminPanel
{
    /** @var AuthManager 認証マネージャー */
    private AuthManager $auth;

    /** @var string テンプレートディレクトリのパス */
    private string $templateDir;

    /**
     * コンストラクタ
     *
     * @param AuthManager $auth        認証マネージャーインスタンス
     * @param string      $templateDir テンプレートファイルを格納するディレクトリパス
     */
    public function __construct(AuthManager $auth, string $templateDir)
    {
        $this->auth = $auth;
        $this->templateDir = rtrim($templateDir, '/');
    }

    /**
     * ダッシュボード画面をレンダリングする。
     *
     * テンプレートファイル `dashboard.html` を読み込み、コンテキスト変数を
     * 展開してHTML文字列を返す。
     *
     * @param array $context テンプレートに渡すコンテキスト変数
     * @return string レンダリング済みHTML
     */
    public function renderDashboard(array $context): string
    {
        $templatePath = $this->templateDir . '/dashboard.html';

        if (!file_exists($templatePath)) {
            return '<h1>Dashboard template not found</h1>';
        }

        $template = file_get_contents($templatePath);
        if ($template === false) {
            return '<h1>Dashboard template read error</h1>';
        }

        /* 基本コンテキストをマージ */
        $defaultContext = [
            'csrf_token'   => $this->auth->csrfToken(),
            'current_user' => $this->auth->currentUser(),
            'is_logged_in' => $this->auth->isLoggedIn(),
        ];

        $context = array_merge($defaultContext, $context);

        return $this->renderTemplate($template, $context);
    }

    /**
     * ログイン画面をレンダリングする。
     *
     * テンプレートファイル `login.html` を読み込み、ログインメッセージと
     * CSRF トークンを展開してHTML文字列を返す。
     *
     * @param string $message ログイン画面に表示するメッセージ（エラー等）
     * @return string レンダリング済みHTML
     */
    public function renderLogin(string $message = ''): string
    {
        $templatePath = $this->templateDir . '/login.html';

        if (!file_exists($templatePath)) {
            return '<h1>Login template not found</h1>';
        }

        $template = file_get_contents($templatePath);
        if ($template === false) {
            return '<h1>Login template read error</h1>';
        }

        $context = [
            'csrf_token'    => $this->auth->csrfToken(),
            'login_message' => $message,
        ];

        return $this->renderTemplate($template, $context);
    }

    /**
     * 管理アクションをハンドリングする。
     *
     * アクション名とパラメータに基づいて処理を実行し、JSON レスポンス形式の
     * 配列を返す。
     *
     * @param string $action アクション名
     * @param array  $params アクションパラメータ
     * @return array レスポンスデータ [ok => bool, data|error => mixed]
     */
    public function handleAction(string $action, array $params): array
    {
        /* 認証チェック */
        if (!$this->auth->isLoggedIn()) {
            return ['ok' => false, 'error' => '認証が必要です'];
        }

        /* CSRFトークン検証 */
        $csrfToken = $params['csrf'] ?? '';
        if (!$this->auth->verifyCsrf($csrfToken)) {
            return ['ok' => false, 'error' => 'CSRF トークンが無効です'];
        }

        return match ($action) {
            'dashboard' => [
                'ok'   => true,
                'data' => $this->buildContext(),
            ],
            default => [
                'ok'    => false,
                'error' => '不明なアクションです: ' . $action,
            ],
        };
    }

    /**
     * ダッシュボードのテンプレートコンテキストを構築する。
     *
     * @return array コンテキスト変数の連想配列
     */
    public function buildContext(): array
    {
        $user = $this->auth->currentUser();

        return [
            'csrf_token'    => $this->auth->csrfToken(),
            'current_user'  => $user['username'] ?? '',
            'current_role'  => $user['role'] ?? '',
            'is_logged_in'  => $this->auth->isLoggedIn(),
            'php_version'   => PHP_VERSION,
            'timestamp'     => date('c'),
        ];
    }

    /**
     * テンプレート文字列にコンテキスト変数を展開する。
     *
     * `{{key}}` 形式のプレースホルダーをコンテキスト値で置換する。
     * 値は自動的にHTMLエスケープされる。
     *
     * @param string $template テンプレート文字列
     * @param array  $context  コンテキスト変数
     * @return string 展開済み文字列
     */
    private function renderTemplate(string $template, array $context): string
    {
        return preg_replace_callback(
            '/\{\{(\w+)\}\}/',
            function (array $matches) use ($context): string {
                $key = $matches[1];
                $value = $context[$key] ?? '';

                if (is_array($value)) {
                    return htmlspecialchars(
                        json_encode($value, JSON_UNESCAPED_UNICODE),
                        ENT_QUOTES,
                        'UTF-8'
                    );
                }

                if (is_bool($value)) {
                    return $value ? 'true' : 'false';
                }

                return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
            },
            $template
        ) ?? $template;
    }
}

// ============================================================================
// UserManager - ユーザー CRUD
// ============================================================================

/**
 * ユーザーの作成・削除・一覧・検索・パスワード管理を提供する。
 *
 * ユーザー情報は JSON ファイルに保存され、パスワードは bcrypt で
 * ハッシュ化される。ロールベースの権限管理に対応。
 */
class UserManager
{
    /** @var string データディレクトリのパス */
    private string $dataDir;

    /** @var string ユーザーデータファイル名 */
    private const USERS_FILE = 'users.json';

    /** @var string ユーザー名のバリデーションパターン */
    private const USERNAME_PATTERN = '/^[a-zA-Z0-9_\-]{3,30}$/';

    /** @var string[] 有効なロール一覧 */
    private const VALID_ROLES = ['admin', 'editor', 'viewer'];

    /**
     * コンストラクタ
     *
     * @param string $dataDir ユーザーデータを保存するディレクトリパス
     */
    public function __construct(string $dataDir)
    {
        $this->dataDir = rtrim($dataDir, '/');

        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0755, true);
        }
    }

    /**
     * 新しいユーザーを追加する。
     *
     * @param string $username ユーザー名（3〜30文字、英数字・ハイフン・アンダースコア）
     * @param string $password パスワード（平文）
     * @param string $role     ロール（admin, editor, viewer）
     * @return bool 追加成功時 true
     */
    public function addUser(string $username, string $password, string $role = 'editor'): bool
    {
        if (!preg_match(self::USERNAME_PATTERN, $username)) {
            return false;
        }

        if (!in_array($role, self::VALID_ROLES, true)) {
            return false;
        }

        if ($password === '') {
            return false;
        }

        $users = $this->loadUsers();

        if (isset($users[$username])) {
            return false;
        }

        $users[$username] = [
            'password_hash' => password_hash($password, PASSWORD_BCRYPT),
            'role'          => $role,
            'created_at'    => date('c'),
            'active'        => true,
        ];

        $this->saveUsers($users);

        return true;
    }

    /**
     * ユーザーを削除する。
     *
     * @param string $username 削除するユーザー名
     * @return bool 削除成功時 true
     */
    public function deleteUser(string $username): bool
    {
        $users = $this->loadUsers();

        if (!isset($users[$username])) {
            return false;
        }

        unset($users[$username]);
        $this->saveUsers($users);

        return true;
    }

    /**
     * 全ユーザーの一覧を取得する（パスワードハッシュは除外）。
     *
     * @return array<int, array{username: string, role: string, created_at: string, active: bool}>
     */
    public function listUsers(): array
    {
        $users = $this->loadUsers();
        $result = [];

        foreach ($users as $username => $user) {
            $result[] = [
                'username'   => $username,
                'role'       => $user['role'] ?? 'editor',
                'created_at' => $user['created_at'] ?? '',
                'active'     => $user['active'] ?? true,
            ];
        }

        return $result;
    }

    /**
     * ユーザー名でユーザーを検索する（パスワードハッシュは除外）。
     *
     * @param string $username 検索するユーザー名
     * @return array|null ユーザー情報または null
     */
    public function findUser(string $username): array|null
    {
        $users = $this->loadUsers();

        if (!isset($users[$username])) {
            return null;
        }

        $user = $users[$username];

        return [
            'username'   => $username,
            'role'       => $user['role'] ?? 'editor',
            'created_at' => $user['created_at'] ?? '',
            'active'     => $user['active'] ?? true,
        ];
    }

    /**
     * ユーザーのパスワードを更新する。
     *
     * @param string $username    ユーザー名
     * @param string $newPassword 新しいパスワード（平文）
     * @return bool 更新成功時 true
     */
    public function updatePassword(string $username, string $newPassword): bool
    {
        if ($newPassword === '') {
            return false;
        }

        $users = $this->loadUsers();

        if (!isset($users[$username])) {
            return false;
        }

        $users[$username]['password_hash'] = password_hash(
            $newPassword,
            PASSWORD_BCRYPT
        );

        $this->saveUsers($users);

        return true;
    }

    /**
     * ユーザーのパスワードを検証する。
     *
     * @param string $username ユーザー名
     * @param string $password パスワード（平文）
     * @return bool パスワードが正しい場合 true
     */
    public function verifyPassword(string $username, string $password): bool
    {
        $users = $this->loadUsers();

        if (!isset($users[$username])) {
            return false;
        }

        $user = $users[$username];

        /* 無効化されたユーザーは認証拒否 */
        if (!($user['active'] ?? true)) {
            return false;
        }

        return password_verify($password, $user['password_hash'] ?? '');
    }

    /**
     * ユーザーデータファイルを読み込む。
     *
     * @return array ユーザーデータの連想配列
     */
    private function loadUsers(): array
    {
        $path = $this->dataDir . '/' . self::USERS_FILE;

        if (!file_exists($path)) {
            return [];
        }

        $content = file_get_contents($path);
        $data = json_decode($content ?: '', true);

        return is_array($data) ? $data : [];
    }

    /**
     * ユーザーデータファイルを保存する。
     *
     * @param array $users ユーザーデータ
     */
    private function saveUsers(array $users): void
    {
        $path = $this->dataDir . '/' . self::USERS_FILE;
        file_put_contents(
            $path,
            json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }
}

// ============================================================================
// AuthManager - 認証・セッション管理
// ============================================================================

/**
 * セッションベースの認証、CSRF 保護、レート制限を提供する。
 *
 * ログイン・ログアウト、ロールベースアクセス制御、CSRF トークンの
 * 生成と検証、IP ベースのログイン試行レート制限を備える。
 */
class AuthManager
{
    /** @var string データディレクトリのパス */
    private string $dataDir;

    /** @var array 設定オプション */
    private readonly array $config;

    /** @var string レート制限データファイル名 */
    private const RATE_FILE = 'login_attempts.json';

    /** @var array<string, int> ロールの権限レベル */
    private const ROLE_LEVELS = [
        'viewer' => 1,
        'editor' => 2,
        'admin'  => 3,
    ];

    /**
     * コンストラクタ
     *
     * @param string $dataDir 認証データを保存するディレクトリパス
     * @param array  $config  設定オプション [max_attempts, lockout_seconds, session_key_prefix]
     */
    public function __construct(string $dataDir, array $config = [])
    {
        $this->dataDir = rtrim($dataDir, '/');

        $this->config = array_merge([
            'max_attempts'       => 5,
            'lockout_seconds'    => 900,
            'session_key_prefix' => 'ace_',
        ], $config);

        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0755, true);
        }

        /* セッション開始（まだ開始されていない場合） */
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * ログイン処理を実行する。
     *
     * UserManager と連携してパスワードを検証し、セッションにログイン状態を保存する。
     *
     * @param string $username ユーザー名
     * @param string $password パスワード（平文）
     * @return bool ログイン成功時 true
     */
    public function login(string $username, string $password): bool
    {
        $userManager = new UserManager($this->dataDir);

        if (!$userManager->verifyPassword($username, $password)) {
            return false;
        }

        $user = $userManager->findUser($username);
        if ($user === null) {
            return false;
        }

        /* セッション固定攻撃防止 */
        session_regenerate_id(true);

        $prefix = $this->config['session_key_prefix'];
        $_SESSION[$prefix . 'logged_in'] = true;
        $_SESSION[$prefix . 'username']  = $username;
        $_SESSION[$prefix . 'role']      = $user['role'];
        $_SESSION[$prefix . 'login_at']  = date('c');

        return true;
    }

    /**
     * ログアウト処理を実行する。
     *
     * セッションからログイン関連の情報をクリアする。
     */
    public function logout(): void
    {
        $prefix = $this->config['session_key_prefix'];

        unset(
            $_SESSION[$prefix . 'logged_in'],
            $_SESSION[$prefix . 'username'],
            $_SESSION[$prefix . 'role'],
            $_SESSION[$prefix . 'login_at']
        );
    }

    /**
     * 現在ログイン中かどうかを判定する。
     *
     * @return bool ログイン中の場合 true
     */
    public function isLoggedIn(): bool
    {
        $prefix = $this->config['session_key_prefix'];
        return isset($_SESSION[$prefix . 'logged_in'])
            && $_SESSION[$prefix . 'logged_in'] === true;
    }

    /**
     * 現在のログインユーザーの情報を取得する。
     *
     * @return array{username: string, role: string} ユーザー情報
     */
    public function currentUser(): array
    {
        $prefix = $this->config['session_key_prefix'];

        if (!$this->isLoggedIn()) {
            return ['username' => '', 'role' => ''];
        }

        return [
            'username' => $_SESSION[$prefix . 'username'] ?? '',
            'role'     => $_SESSION[$prefix . 'role'] ?? '',
        ];
    }

    /**
     * 指定ロール以上の権限を持っているかチェックする。
     *
     * ロール階層: viewer < editor < admin
     *
     * @param string $requiredRole 必要なロール
     * @return bool 権限がある場合 true
     */
    public function hasRole(string $requiredRole): bool
    {
        if (!$this->isLoggedIn()) {
            return false;
        }

        $user = $this->currentUser();
        $currentLevel = self::ROLE_LEVELS[$user['role']] ?? 0;
        $requiredLevel = self::ROLE_LEVELS[$requiredRole] ?? 0;

        return $currentLevel >= $requiredLevel;
    }

    /**
     * CSRF トークンを生成または取得する。
     *
     * セッションに保存されたトークンがあればそれを返し、
     * なければ新規に生成して保存する。
     *
     * @return string CSRF トークン文字列
     */
    public function csrfToken(): string
    {
        $prefix = $this->config['session_key_prefix'];
        $key = $prefix . 'csrf_token';

        if (empty($_SESSION[$key])) {
            $_SESSION[$key] = bin2hex(random_bytes(32));
        }

        return $_SESSION[$key];
    }

    /**
     * CSRF トークンを検証する。
     *
     * @param string $token 検証するトークン文字列
     * @return bool トークンが有効な場合 true
     */
    public function verifyCsrf(string $token): bool
    {
        $prefix = $this->config['session_key_prefix'];
        $key = $prefix . 'csrf_token';

        if (empty($_SESSION[$key]) || $token === '') {
            return false;
        }

        return hash_equals($_SESSION[$key], $token);
    }

    /**
     * IP アドレスに対するログイン試行レート制限をチェックする。
     *
     * 設定された最大試行回数を超えた場合、ロックアウト期間中は false を返す。
     *
     * @param string $ip チェック対象の IP アドレス
     * @return bool 試行可能な場合 true
     */
    public function checkRateLimit(string $ip): bool
    {
        $data = $this->loadRateData();
        $attempts = $data[$ip] ?? ['count' => 0, 'locked_until' => 0];

        /* ロックアウト中かチェック */
        if (time() < (int)$attempts['locked_until']) {
            return false;
        }

        return true;
    }

    /**
     * ログイン失敗を記録する。
     *
     * 最大試行回数に達した場合、ロックアウト期間を設定する。
     *
     * @param string $ip 失敗元の IP アドレス
     */
    public function recordFailure(string $ip): void
    {
        $data = $this->loadRateData();
        $attempts = $data[$ip] ?? ['count' => 0, 'locked_until' => 0];

        /* ロックアウト期間後はカウントリセット */
        if (time() >= (int)$attempts['locked_until']) {
            $attempts['count']++;
        }

        if ($attempts['count'] >= $this->config['max_attempts']) {
            $attempts['locked_until'] = time() + $this->config['lockout_seconds'];
            $attempts['count'] = 0;
        }

        $data[$ip] = $attempts;
        $this->saveRateData($data);
    }

    /**
     * ログイン成功時にレート制限をクリアする。
     *
     * @param string $ip クリア対象の IP アドレス
     */
    public function clearRateLimit(string $ip): void
    {
        $data = $this->loadRateData();
        unset($data[$ip]);
        $this->saveRateData($data);
    }

    /**
     * レート制限データを読み込む。
     *
     * @return array レート制限データ
     */
    private function loadRateData(): array
    {
        $path = $this->dataDir . '/' . self::RATE_FILE;

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
     * @param array $data レート制限データ
     */
    private function saveRateData(array $data): void
    {
        $path = $this->dataDir . '/' . self::RATE_FILE;
        file_put_contents(
            $path,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }
}

// ============================================================================
// ActivityLogger - アクティビティログ管理
// ============================================================================

/**
 * 管理操作のアクティビティログを記録・取得・クリアする。
 *
 * ログは JSON ファイルに新しいものから順に保存され、
 * 最大保持件数を超えた古いエントリは自動的に削除される。
 */
class ActivityLogger
{
    /** @var string ログファイルのパス */
    private string $logFile;

    /** @var int 最大保持件数 */
    private const MAX_ENTRIES = 100;

    /**
     * コンストラクタ
     *
     * @param string $logFile ログファイルのパス
     */
    public function __construct(string $logFile)
    {
        $this->logFile = $logFile;

        $dir = dirname($logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    /**
     * アクティビティログを記録する。
     *
     * @param string $message  ログメッセージ
     * @param string $username 操作ユーザー名（省略時は空文字列）
     */
    public function log(string $message, string $username = ''): void
    {
        $log = $this->loadLog();

        $entry = [
            'time'    => date('c'),
            'message' => $message,
        ];

        if ($username !== '') {
            $entry['user'] = $username;
        }

        /* 最新エントリを先頭に追加 */
        array_unshift($log, $entry);

        /* 最大件数を超えたエントリを削除 */
        $log = array_slice($log, 0, self::MAX_ENTRIES);

        $this->saveLog($log);
    }

    /**
     * 最新のアクティビティログを取得する。
     *
     * @param int $limit 取得件数（デフォルト: 20）
     * @return array ログエントリの配列
     */
    public function getRecent(int $limit = 20): array
    {
        $log = $this->loadLog();
        return array_slice($log, 0, max(1, $limit));
    }

    /**
     * アクティビティログを全件クリアする。
     */
    public function clear(): void
    {
        $this->saveLog([]);
    }

    /**
     * ログファイルを読み込む。
     *
     * @return array ログデータ
     */
    private function loadLog(): array
    {
        if (!file_exists($this->logFile)) {
            return [];
        }

        $content = file_get_contents($this->logFile);
        $data = json_decode($content ?: '', true);

        return is_array($data) ? $data : [];
    }

    /**
     * ログファイルを保存する。
     *
     * @param array $log ログデータ
     */
    private function saveLog(array $log): void
    {
        file_put_contents(
            $this->logFile,
            json_encode($log, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }
}
