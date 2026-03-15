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

// ============================================================================
// RevisionManager - コンテンツリビジョン管理
// ============================================================================

/**
 * コンテンツのリビジョン（版管理）を保存・取得・削除する。
 *
 * フィールドごとにリビジョンファイルを保持し、ファイルロックによる
 * 競合状態の防止、世代数制限による自動クリーンアップに対応。
 *
 * @since Ver.1.8
 */
class RevisionManager {

    /** @var string リビジョン保存ベースディレクトリ */
    private string $baseDir;

    /** @var int 最大保持リビジョン数 */
    private int $maxRevisions;

    public function __construct(string $baseDir, int $maxRevisions = 20) {
        $this->baseDir = rtrim($baseDir, '/');
        $this->maxRevisions = $maxRevisions;
    }

    /**
     * リビジョンを保存する
     */
    public function save(string $fieldname, string $content, bool $restored = false): void {
        if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $fieldname)) return;
        $dir = $this->baseDir . '/' . $fieldname . '/';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);

        $lockFile = $dir . '.lock';
        $lf = fopen($lockFile, 'c');
        if ($lf === false) return;

        try {
            if (!flock($lf, LOCK_EX)) return;
            $ts = date('Ymd_His') . '_' . bin2hex(random_bytes(2));
            $rev = [
                'timestamp' => date('c'),
                'content'   => $content,
                'size'      => strlen($content),
                'user'      => $_SESSION['ap_username'] ?? '',
                'restored'  => $restored,
            ];
            \APF\Utilities\FileSystem::writeJson($dir . 'rev_' . $ts . '.json', $rev);
            $this->prune($dir);
        } finally {
            flock($lf, LOCK_UN);
            fclose($lf);
        }
    }

    /**
     * 古いリビジョンを削除（ピン留め以外）
     */
    private function prune(string $dir): void {
        $files = glob($dir . 'rev_*.json') ?: [];
        sort($files);
        $unpinned = [];
        foreach ($files as $f) {
            $data = \APF\Utilities\FileSystem::readJson($f);
            if ($data === null || !empty($data['pinned'])) continue;
            $unpinned[] = $f;
        }
        while (count($unpinned) > $this->maxRevisions) {
            unlink(array_shift($unpinned));
        }
    }
}

// ============================================================================
// AdminManager - 管理エンジン統合ファサード
// ============================================================================

/**
 * AdminEngine のロジックを Framework 化した静的ファサード。
 *
 * 認証・CSRF・ユーザー管理・アクティビティログ・リビジョン管理・
 * ダッシュボードコンテキスト構築を統合する。
 *
 * @since Ver.1.8
 */
class AdminManager {

    public const USERS_FILE = 'users.json';

    /* ── 認証 ── */

    public static function isLoggedIn(): bool {
        return isset($_SESSION['l']) && $_SESSION['l'] === true;
    }

    public static function currentRole(): string {
        return self::isLoggedIn() ? ($_SESSION['ap_role'] ?? 'admin') : '';
    }

    public static function currentUsername(): string {
        return self::isLoggedIn() ? ($_SESSION['ap_username'] ?? 'admin') : '';
    }

    public static function hasRole(string $requiredRole): bool {
        if (!self::isLoggedIn()) return false;
        $roleLevel = ['viewer' => 1, 'editor' => 2, 'admin' => 3];
        return ($roleLevel[self::currentRole()] ?? 0) >= ($roleLevel[$requiredRole] ?? 0);
    }

    /* ── マルチユーザー管理 ── */

    public static function listUsers(): array {
        $users = \APF\Utilities\JsonStorage::read(self::USERS_FILE, \AIS\Core\AppContext::settingsDir());
        if (empty($users)) return [];
        $result = [];
        foreach ($users as $username => $user) {
            $result[] = [
                'username' => $username, 'role' => $user['role'] ?? 'editor',
                'created_at' => $user['created_at'] ?? '', 'active' => $user['active'] ?? true,
            ];
        }
        return $result;
    }

    public static function addUser(string $username, string $password, string $role = 'editor'): bool {
        if (!preg_match('/^[a-zA-Z0-9_\-]{3,30}$/', $username)) return false;
        if (!in_array($role, ['admin', 'editor', 'viewer'], true)) return false;
        $dir = \AIS\Core\AppContext::settingsDir();
        $users = \APF\Utilities\JsonStorage::read(self::USERS_FILE, $dir);
        if (isset($users[$username])) return false;
        $users[$username] = [
            'password_hash' => password_hash($password, self::preferredHashAlgo()),
            'role' => $role, 'created_at' => date('c'), 'active' => true,
        ];
        \APF\Utilities\JsonStorage::write(self::USERS_FILE, $users, $dir);
        return true;
    }

    public static function deleteUser(string $username): bool {
        $dir = \AIS\Core\AppContext::settingsDir();
        $users = \APF\Utilities\JsonStorage::read(self::USERS_FILE, $dir);
        if (!isset($users[$username])) return false;
        unset($users[$username]);
        \APF\Utilities\JsonStorage::write(self::USERS_FILE, $users, $dir);
        return true;
    }

    private static function tryMultiUserLogin(string $username, string $password): ?array {
        $dir = \AIS\Core\AppContext::settingsDir();
        $users = \APF\Utilities\JsonStorage::read(self::USERS_FILE, $dir);
        if (empty($users) || !isset($users[$username])) return null;
        $user = $users[$username];
        if (!($user['active'] ?? true)) return null;
        if (!password_verify($password, $user['password_hash'] ?? '')) return null;
        if (password_needs_rehash($user['password_hash'] ?? '', self::preferredHashAlgo())) {
            $users[$username]['password_hash'] = password_hash($password, self::preferredHashAlgo());
            \APF\Utilities\JsonStorage::write(self::USERS_FILE, $users, $dir);
        }
        return ['username' => $username, 'role' => $user['role'] ?? 'editor'];
    }

    /**
     * ログイン処理
     */
    public static function login(string $passwordHash): string {
        self::verifyCsrf();
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (!self::checkLoginRate($ip)) {
            $remaining = self::getLockoutRemaining($ip);
            return \AIS\Core\I18n::t('auth.too_many_attempts', ['remaining' => $remaining]);
        }

        $password = $_POST['password'] ?? '';
        $username = $_POST['username'] ?? '';

        /* マルチユーザーログイン試行 */
        if ($username !== '') {
            $multiUser = self::tryMultiUserLogin($username, $password);
            if ($multiUser !== null) {
                self::clearLoginRate($ip);
                session_regenerate_id(true);
                $_SESSION['l'] = true;
                $_SESSION['ap_username'] = $multiUser['username'];
                $_SESSION['ap_role'] = $multiUser['role'];
                self::logActivity('ログイン: ' . $multiUser['username'] . ' (' . $multiUser['role'] . ')');
                \AIS\System\DiagnosticsManager::log('security', 'ログイン成功', ['username' => $multiUser['username']]);
                header('Location: ./');
                exit;
            }
        }

        /* 従来の単一パスワード認証 */
        if (!password_verify($password, $passwordHash)) {
            self::recordLoginFailure($ip);
            \AIS\System\DiagnosticsManager::log('security', 'ログイン失敗（パスワード不一致）');
            $attemptsLeft = self::getRemainingAttempts($ip);
            if ($attemptsLeft > 0) {
                return \AIS\Core\I18n::t('auth.wrong_password', ['attempts' => $attemptsLeft]);
            }
            return \AIS\Core\I18n::t('auth.wrong_password_final');
        }

        self::clearLoginRate($ip);
        if (password_needs_rehash($passwordHash, self::preferredHashAlgo())) {
            self::savePassword($_POST['new'] ?? $password);
        }
        if (!empty($_POST['new'])) {
            self::savePassword($_POST['new']);
            return \AIS\Core\I18n::t('auth.password_changed');
        }
        session_regenerate_id(true);
        $_SESSION['l'] = true;
        $_SESSION['ap_username'] = 'admin';
        $_SESSION['ap_role'] = 'admin';
        \AIS\System\DiagnosticsManager::log('security', 'ログイン成功', ['username' => 'admin']);
        header('Location: ./');
        exit;
    }

    private static function preferredHashAlgo(): string|int {
        return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
    }

    public static function savePassword(string $p): string {
        $hash = password_hash($p, self::preferredHashAlgo());
        \APF\Utilities\JsonStorage::write('auth.json', ['password_hash' => $hash], \AIS\Core\AppContext::settingsDir());
        return $hash;
    }

    /* ── レート制限 ── */

    private static function checkLoginRate(string $ip): bool {
        $data = \APF\Utilities\JsonStorage::read('login_attempts.json', \AIS\Core\AppContext::settingsDir());
        $attempts = $data[$ip] ?? ['count' => 0, 'locked_until' => 0];
        return time() >= (int)$attempts['locked_until'];
    }

    private static function recordLoginFailure(string $ip): void {
        $dir = \AIS\Core\AppContext::settingsDir();
        $data = \APF\Utilities\JsonStorage::read('login_attempts.json', $dir);
        $attempts = $data[$ip] ?? ['count' => 0, 'locked_until' => 0];
        if (time() >= (int)$attempts['locked_until']) $attempts['count']++;
        if ($attempts['count'] >= 5) {
            $attempts['locked_until'] = time() + 900;
            $attempts['count'] = 0;
            \AIS\System\DiagnosticsManager::log('security', 'ロックアウト発動');
        }
        $data[$ip] = $attempts;
        \APF\Utilities\JsonStorage::write('login_attempts.json', $data, $dir);
    }

    private static function clearLoginRate(string $ip): void {
        $dir = \AIS\Core\AppContext::settingsDir();
        $data = \APF\Utilities\JsonStorage::read('login_attempts.json', $dir);
        unset($data[$ip]);
        \APF\Utilities\JsonStorage::write('login_attempts.json', $data, $dir);
    }

    private static function getLockoutRemaining(string $ip): int {
        $data = \APF\Utilities\JsonStorage::read('login_attempts.json', \AIS\Core\AppContext::settingsDir());
        $attempts = $data[$ip] ?? ['count' => 0, 'locked_until' => 0];
        return max(1, (int)ceil(((int)$attempts['locked_until'] - time()) / 60));
    }

    private static function getRemainingAttempts(string $ip): int {
        $data = \APF\Utilities\JsonStorage::read('login_attempts.json', \AIS\Core\AppContext::settingsDir());
        $attempts = $data[$ip] ?? ['count' => 0, 'locked_until' => 0];
        return max(0, 5 - (int)$attempts['count']);
    }

    /* ── CSRF ── */

    public static function csrfToken(): string {
        if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf'];
    }

    public static function verifyCsrf(): void {
        $token = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $token)) {
            \AIS\System\DiagnosticsManager::log('security', 'CSRF 検証失敗');
            header('HTTP/1.1 403 Forbidden');
            exit;
        }
    }

    /* ── アクティビティログ ── */

    public static function logActivity(string $message): void {
        $dir = \AIS\Core\AppContext::settingsDir();
        $log = \APF\Utilities\JsonStorage::read('activity.json', $dir);
        $entry = ['time' => date('c'), 'message' => $message];
        $username = $_SESSION['ap_username'] ?? '';
        if ($username !== '') $entry['user'] = $username;
        array_unshift($log, $entry);
        $log = array_slice($log, 0, 100);
        \APF\Utilities\JsonStorage::write('activity.json', $log, $dir);
    }

    public static function getRecentActivity(int $limit = 20): array {
        return array_slice(
            \APF\Utilities\JsonStorage::read('activity.json', \AIS\Core\AppContext::settingsDir()),
            0, $limit
        );
    }

    /* ── リビジョン管理 ── */

    public static function saveRevision(string $fieldname, string $content, bool $restored = false): void {
        $revLimit = defined('AP_REVISION_LIMIT') ? AP_REVISION_LIMIT : 20;
        $mgr = new RevisionManager(\AIS\Core\AppContext::contentDir() . '/revisions', $revLimit);
        $mgr->save($fieldname, $content, $restored);
    }

    /* ── レンダリング ── */

    public static function renderLogin(string $message = ''): string {
        $tplDir = dirname(__DIR__, 2) . '/engines/AdminEngine';
        $tplPath = $tplDir . '/login.html';
        if (!file_exists($tplPath)) return '<h1>Login template not found</h1>';
        $tpl = \APF\Utilities\FileSystem::read($tplPath);
        if ($tpl === false) return '<h1>Login template read error</h1>';
        $ctx = [
            'title' => \AIS\Core\AppContext::config('title', 'Login'),
            'csrf_token' => self::csrfToken(),
            'login_message' => $message,
            'html_lang' => \AIS\Core\I18n::htmlLang(),
            'i18n' => \AIS\Core\I18n::allNested(),
        ];
        return \ASG\Template\TemplateService::render($tpl, $ctx, $tplDir);
    }

    public static function renderDashboard(): string {
        $tplDir = dirname(__DIR__, 2) . '/engines/AdminEngine';
        $tplPath = $tplDir . '/dashboard.html';
        if (!file_exists($tplPath)) return '<h1>Dashboard template not found</h1>';
        $tpl = \APF\Utilities\FileSystem::read($tplPath);
        if ($tpl === false) return '<h1>Dashboard template read error</h1>';
        $ctx = self::buildDashboardContext();
        if (\AIS\System\DiagnosticsManager::shouldShowNotice()) {
            \AIS\System\DiagnosticsManager::markNoticeShown();
        }
        return \ASG\Template\TemplateService::render($tpl, $ctx, $tplDir);
    }

    public static function renderEditableContent(string $id, string $content, string $placeholder = 'Click to edit!'): string {
        if (self::isLoggedIn()) {
            return "<span title='" . \APF\Utilities\Security::escape($placeholder)
                . "' id='" . \APF\Utilities\Security::escape($id) . "' class='editRich'>" . $content . "</span>";
        }
        return $content;
    }

    /* ── フック管理 ── */

    public static function registerHooks(): void {
        \AIS\Core\AppContext::addHook('admin-head', "\n\t<link rel='stylesheet' href='Framework/ADS/ADS.Base.css'>");
        \AIS\Core\AppContext::addHook('admin-head', "\n\t<link rel='stylesheet' href='Framework/ADS/ADS.Components.css'>");
        \AIS\Core\AppContext::addHook('admin-head', "\n\t<link rel='stylesheet' href='Framework/ADS/ADS.Editor.css'>");
        \AIS\Core\AppContext::addHook('admin-head', "\n\t<script src='engines/JsEngine/aeb-adapter.js' type='module'></script>");
        \AIS\Core\AppContext::addHook('admin-head', "\n\t<script src='engines/JsEngine/ap-utils.js'></script>");
        \AIS\Core\AppContext::addHook('admin-head', "\n\t<script src='engines/JsEngine/ap-events.js'></script>");
        \AIS\Core\AppContext::addHook('admin-head', "\n\t<script src='engines/JsEngine/autosize.js'></script>");
        \AIS\Core\AppContext::addHook('admin-head', "\n\t<script src='engines/JsEngine/editInplace.js'></script>");
        \AIS\Core\AppContext::addHook('admin-head', "\n\t<script src='engines/JsEngine/wysiwyg.js'></script>");
        \AIS\Core\AppContext::addHook('admin-head', "\n\t<script src='engines/JsEngine/updater.js'></script>");
    }

    public static function getAdminScripts(): string {
        $scripts = '';
        foreach (\AIS\Core\AppContext::getHooks('admin-head') as $o) {
            $scripts .= "\t" . $o . "\n";
        }
        return $scripts;
    }

    /* ── ダッシュボードコンテキスト ── */

    public static function buildDashboardContext(): array {
        $selectHtml = "<select name='themeSelect' id='ap-theme-select'>";
        foreach (\ASG\Template\ThemeService::listThemes() as $val) {
            $selected = ($val == \AIS\Core\AppContext::config('themeSelect', '')) ? ' selected' : '';
            $selectHtml .= '<option value="' . \APF\Utilities\Security::escape($val) . '"' . $selected . '>' . \APF\Utilities\Security::escape($val) . "</option>\n";
        }
        $selectHtml .= '</select>';

        $fields = [];
        foreach (['title', 'description', 'keywords', 'copyright'] as $key) {
            $fields[] = [
                'key' => $key,
                'default_value' => \AIS\Core\AppContext::defaults('default', $key, ''),
                'value' => \AIS\Core\AppContext::config($key, ''),
            ];
        }

        $_s = \APF\Utilities\JsonStorage::read('settings.json', \AIS\Core\AppContext::settingsDir());
        $pages = \APF\Utilities\JsonStorage::read('pages.json', \AIS\Core\AppContext::contentDir());
        $pageList = [];
        foreach ($pages as $slug => $content) {
            $pageList[] = [
                'slug' => $slug,
                'preview' => mb_substr(strip_tags((string)$content), 0, 80, 'UTF-8'),
            ];
        }

        $diskFree = @disk_free_space('.');
        $diskFreeStr = ($diskFree !== false)
            ? number_format($diskFree / 1024 / 1024, 0) . ' MB'
            : \AIS\Core\I18n::t('disk.unavailable');

        $collectionsEnabled = \ACE\Core\CollectionService::isEnabled();
        $collectionList = $collectionsEnabled ? \ACE\Core\CollectionService::listCollections() : [];
        $gitEnabled = class_exists('GitEngine') && \GitEngine::isEnabled();
        $gitConfig = class_exists('GitEngine') ? \GitEngine::loadConfig() : [];
        $users = self::listUsers();
        $webhooks = \ACE\Api\WebhookService::listWebhooks();
        $cacheStats = \AIS\System\ApiCache::getStats();
        $redirects = \APF\Utilities\JsonStorage::read('redirects.json', \AIS\Core\AppContext::settingsDir());
        $redirectList = [];
        foreach ($redirects as $i => $r) {
            $redirectList[] = ['from' => $r['from'] ?? '', 'to' => $r['to'] ?? '', 'code' => $r['code'] ?? 301, 'index' => $i];
        }

        return [
            'title' => \AIS\Core\AppContext::config('title', ''),
            'host' => \AIS\Core\AppContext::host(),
            'ap_version' => AP_VERSION,
            'csrf_token' => self::csrfToken(),
            'theme_select_html' => $selectHtml,
            'menu_raw' => preg_replace('/\s+on\w+\s*=\s*["\'][^"\']*["\']/i', '', strip_tags(\AIS\Core\AppContext::config('menu', ''), '<br>')),
            'settings_fields' => $fields, 'pages' => $pageList,
            'has_pages' => !empty($pageList),
            'php_version' => PHP_VERSION, 'disk_free' => $diskFreeStr,
            'migrate_warning' => (bool)\AIS\Core\AppContext::config('migrate_warning', false),
            'contact_email' => $_s['contact_email'] ?? '',
            'activity_log' => ($activityLog = self::getRecentActivity(20)),
            'has_activity' => !empty($activityLog),
            'collections_enabled' => $collectionsEnabled,
            'collections' => $collectionList, 'has_collections' => !empty($collectionList),
            'git_enabled' => $gitEnabled,
            'git_repository' => $gitConfig['repository'] ?? '',
            'git_branch' => $gitConfig['branch'] ?? 'main',
            'git_content_dir' => $gitConfig['content_dir'] ?? 'content',
            'git_last_sync' => $gitConfig['last_sync'] ?? '',
            'git_issues_enabled' => !empty($gitConfig['issues_enabled']),
            'git_webhook_secret' => $gitConfig['webhook_secret'] ?? '',
            'current_user' => self::currentUsername(), 'current_role' => self::currentRole(),
            'users' => $users, 'has_users' => !empty($users),
            'webhooks' => $webhooks, 'has_webhooks' => !empty($webhooks),
            'cache_files' => $cacheStats['files'] ?? 0, 'cache_size' => $cacheStats['size_human'] ?? '0 B',
            'redirects' => $redirectList, 'has_redirects' => !empty($redirectList),
            'diag_show_notice' => \AIS\System\DiagnosticsManager::shouldShowNotice(),
            'html_lang' => \AIS\Core\I18n::htmlLang(),
            'ap_locale' => \AIS\Core\I18n::getLocale(),
            'i18n' => \AIS\Core\I18n::allNested(),
            'i18n_json' => json_encode(\AIS\Core\I18n::all(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG),
        ];
    }
}
