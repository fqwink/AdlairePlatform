<?php

declare(strict_types=1);

/**
 * ASS.Api.php — Adlaire Server System / Engine 2: Api
 *
 * ACS（Client SDK）との接続を担うアダプター層。
 * 全エンドポイントを登録し、ACS からの HTTP リクエストをサービス層にディスパッチする。
 *
 * エントリポイント: ASS\Api\Application::boot() → run()
 */

namespace ASS\Api;

use ASS\Class\ApiResponse;
use ASS\Class\Request;
use ASS\Class\Response;
use ASS\Class\Router;
use ASS\Core\AuthService;
use ASS\Core\StorageService;
use ASS\Core\FileService;
use ASS\Core\GitService;
use ASS\Core\UserManager;
use ASS\Core\SessionAdmin;
use ASS\Core\ServerDiagnostics;
use ASS\Utilities\SessionManager;
use ASS\Utilities\CsrfManager;
use ASS\Utilities\MimeType;
use ASS\Utilities\AdminTemplate;

// ─────────────────────────────────────────────
// アプリケーション（エントリポイント）
// ─────────────────────────────────────────────

final class Application
{
    private string $dataDir;
    private string $repoDir;

    private AuthService $auth;
    private StorageService $storage;
    private FileService $files;
    private GitService $git;
    private UserManager $userManager;
    private SessionAdmin $sessionAdmin;
    private ServerDiagnostics $diagnostics;
    private SessionManager $sessions;
    private Router $router;

    private function __construct(string $dataDir, string $repoDir)
    {
        $this->dataDir = $dataDir;
        $this->repoDir = $repoDir;
    }

    /**
     * アプリケーションを初期化する。
     *
     * @param string $dataDir データディレクトリのルートパス
     * @param string $repoDir Gitリポジトリのルートパス
     */
    public static function boot(string $dataDir, string $repoDir): self
    {
        $app = new self($dataDir, $repoDir);

        // サービス初期化
        $sessionDir = $dataDir . '/cache/sessions';
        $csrfDir = $dataDir . '/cache/csrf';

        $sessions = new SessionManager($sessionDir);
        $csrf = new CsrfManager($csrfDir);

        $app->sessions = $sessions;
        $app->auth = new AuthService($dataDir, $sessions, $csrf);
        $app->storage = new StorageService($dataDir);
        $app->files = new FileService($dataDir);
        $app->git = new GitService($dataDir, $repoDir);
        $app->userManager = new UserManager($dataDir);
        $app->sessionAdmin = new SessionAdmin($sessions, $csrf);
        $app->diagnostics = new ServerDiagnostics($dataDir, $repoDir);

        // ルーター初期化
        $app->router = new Router();
        $app->registerRoutes();

        return $app;
    }

    /**
     * リクエストを処理する。
     */
    public function run(): void
    {
        $request = Request::fromGlobals();
        $response = new Response();

        // CORS ヘッダー — only reflect origin for same-site requests
        $origin = $request->getHeader('origin') ?? '';
        $allowedOrigin = ($origin !== '' && $origin !== '*') ? $origin : '';
        if ($allowedOrigin !== '') {
            $response->setHeader('Access-Control-Allow-Origin', $allowedOrigin);
        }
        $response->setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-CSRF-Token');
        $response->setHeader('Access-Control-Allow-Credentials', 'true');

        // セキュリティヘッダー
        $response->setHeader('X-Content-Type-Options', 'nosniff');
        $response->setHeader('X-Frame-Options', 'DENY');
        $response->setHeader('X-XSS-Protection', '1; mode=block');
        $response->setHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        $this->router->dispatch($request, $response);
    }

    // ─────────────────────────────────────────
    // ルート登録
    // ─────────────────────────────────────────

    private function registerRoutes(): void
    {
        $r = $this->router;

        // ── 認証 ──
        $r->post('/login', $this->handleLogin(...));
        $r->post('/logout', $this->handleLogout(...));
        $r->get('/api/session', $this->handleGetSession(...));
        $r->post('/api/session/verify', $this->handleVerifyToken(...));
        $r->get('/api/csrf', $this->handleGetCsrf(...));
        $r->post('/api/csrf/verify', $this->handleVerifyCsrf(...));

        // ── ストレージ ──
        $r->get('/api/storage/read', $this->handleStorageRead(...));
        $r->post('/api/storage/write', $this->handleStorageWrite(...));
        $r->post('/api/storage/delete', $this->handleStorageDelete(...));
        $r->get('/api/storage/exists', $this->handleStorageExists(...));
        $r->get('/api/storage/list', $this->handleStorageList(...));

        // ── ファイル ──
        $r->post('/api/files/upload', $this->handleFileUpload(...));
        $r->post('/api/files/upload-image', $this->handleImageUpload(...));
        $r->get('/api/files/download', $this->handleFileDownload(...));
        $r->post('/api/files/delete', $this->handleFileDelete(...));
        $r->get('/api/files/exists', $this->handleFileExists(...));
        $r->get('/api/files/info', $this->handleFileInfo(...));

        // ── Git ──
        $r->post('/api/git/configure', $this->handleGitConfigure(...));
        $r->get('/api/git/config', $this->handleGitGetConfig(...));
        $r->get('/api/git/test', $this->handleGitTest(...));
        $r->post('/api/git/pull', $this->handleGitPull(...));
        $r->post('/api/git/push', $this->handleGitPush(...));
        $r->get('/api/git/log', $this->handleGitLog(...));
        $r->get('/api/git/status', $this->handleGitStatus(...));

        // ── ヘルスチェック ──
        $r->get('/health', $this->handleHealth(...));

        // ── 管理画面 ──
        $r->get('/ass-admin/', $this->adminDashboard(...));
        $r->get('/ass-admin/login', $this->adminLoginPage(...));
        $r->post('/ass-admin/login', $this->adminLoginAction(...));
        $r->get('/ass-admin/logout', $this->adminLogout(...));
        $r->get('/ass-admin/users', $this->adminUsers(...));
        $r->post('/ass-admin/users/add', $this->adminUserAdd(...));
        $r->post('/ass-admin/users/delete', $this->adminUserDelete(...));
        $r->post('/ass-admin/users/password', $this->adminUserPassword(...));
        $r->post('/ass-admin/users/role', $this->adminUserRole(...));
        $r->get('/ass-admin/sessions', $this->adminSessions(...));
        $r->post('/ass-admin/sessions/destroy', $this->adminSessionDestroy(...));
        $r->post('/ass-admin/sessions/destroy-all', $this->adminSessionDestroyAll(...));
        $r->post('/ass-admin/sessions/purge', $this->adminSessionPurge(...));
        $r->get('/ass-admin/server', $this->adminServer(...));
        $r->get('/ass-admin/git', $this->adminGit(...));
        $r->get('/ass-admin/storage', $this->adminStorage(...));
    }

    // ─────────────────────────────────────────
    // 認証ミドルウェアヘルパー
    // ─────────────────────────────────────────

    /**
     * Bearer トークンからセッションを検証する。
     * 失敗時は null を返す。
     */
    private function requireAuth(Request $request, Response $response): ?array
    {
        $header = $request->getHeader('authorization') ?? '';
        $token = '';

        if (str_starts_with($header, 'Bearer ')) {
            $token = substr($header, 7);
        }

        // Cookie フォールバック
        if ($token === '') {
            $token = $request->getCookie('ass_session') ?? '';
        }

        if ($token === '') {
            $response->json(ApiResponse::error('Authentication required'), 401);
            return null;
        }

        $session = $this->auth->getSession($token);
        if ($session === null) {
            $response->json(ApiResponse::error('Invalid or expired session'), 401);
            return null;
        }

        return $session;
    }

    // ─────────────────────────────────────────
    // 認証ハンドラー
    // ─────────────────────────────────────────

    private function handleLogin(Request $request, Response $response): void
    {
        $body = $request->getBody();
        $username = $body['username'] ?? '';
        $password = $body['password'] ?? '';

        if ($username === '' || $password === '') {
            $response->json(ApiResponse::error('Username and password required'), 400);
            return;
        }

        $result = $this->auth->authenticate($username, $password);

        if (!$result['authenticated']) {
            $response->json(ApiResponse::ok($result), 401);
            return;
        }

        $response
            ->setCookie('ass_session', $result['token'], [
                'expires' => time() + 3600,
                'httponly' => true,
                'samesite' => 'Strict',
            ])
            ->json(ApiResponse::ok($result));
    }

    private function handleLogout(Request $request, Response $response): void
    {
        $header = $request->getHeader('authorization') ?? '';
        $token = str_starts_with($header, 'Bearer ') ? substr($header, 7) : '';

        if ($token === '') {
            $token = $request->getCookie('ass_session') ?? '';
        }

        if ($token !== '') {
            $this->auth->logout($token);
        }

        $response
            ->clearCookie('ass_session')
            ->json(ApiResponse::ok());
    }

    private function handleGetSession(Request $request, Response $response): void
    {
        $session = $this->requireAuth($request, $response);
        if ($session === null) {
            return;
        }

        $response->json(ApiResponse::ok($session));
    }

    private function handleVerifyToken(Request $request, Response $response): void
    {
        $body = $request->getBody();
        $token = $body['token'] ?? '';

        if ($token === '') {
            $response->json(ApiResponse::error('Token required'), 400);
            return;
        }

        $result = $this->auth->verifyToken($token);
        $response->json(ApiResponse::ok($result));
    }

    private function handleGetCsrf(Request $request, Response $response): void
    {
        $token = $this->auth->generateCsrfToken();
        $response->json(ApiResponse::ok(['token' => $token]));
    }

    private function handleVerifyCsrf(Request $request, Response $response): void
    {
        $body = $request->getBody();
        $token = $body['token'] ?? '';

        if ($token === '') {
            $response->json(ApiResponse::error('Token required'), 400);
            return;
        }

        $valid = $this->auth->verifyCsrfToken($token);
        $response->json(ApiResponse::ok(['valid' => $valid]));
    }

    // ─────────────────────────────────────────
    // ストレージハンドラー
    // ─────────────────────────────────────────

    private function handleStorageRead(Request $request, Response $response): void
    {
        if ($this->requireAuth($request, $response) === null) {
            return;
        }

        $file = $request->getQuery('file', '');
        $dir = $request->getQuery('dir', 'settings');

        if ($file === '') {
            $response->json(ApiResponse::error('File parameter required'), 400);
            return;
        }

        try {
            $data = $this->storage->read($file, $dir);
            $response->json(ApiResponse::ok($data));
        } catch (\RuntimeException $e) {
            $response->json(ApiResponse::error($e->getMessage()), 404);
        } catch (\InvalidArgumentException $e) {
            $response->json(ApiResponse::error($e->getMessage()), 400);
        }
    }

    private function handleStorageWrite(Request $request, Response $response): void
    {
        if ($this->requireAuth($request, $response) === null) {
            return;
        }

        $body = $request->getBody();
        $file = $body['file'] ?? '';
        $data = $body['data'] ?? null;
        $dir = $body['dir'] ?? 'settings';

        if ($file === '') {
            $response->json(ApiResponse::error('File parameter required'), 400);
            return;
        }

        try {
            $this->storage->write($file, $data, $dir);
            $response->json(ApiResponse::ok());
        } catch (\InvalidArgumentException $e) {
            $response->json(ApiResponse::error($e->getMessage()), 400);
        }
    }

    private function handleStorageDelete(Request $request, Response $response): void
    {
        if ($this->requireAuth($request, $response) === null) {
            return;
        }

        $body = $request->getBody();
        $file = $body['file'] ?? '';
        $dir = $body['dir'] ?? 'settings';

        if ($file === '') {
            $response->json(ApiResponse::error('File parameter required'), 400);
            return;
        }

        try {
            $this->storage->delete($file, $dir);
            $response->json(ApiResponse::ok());
        } catch (\InvalidArgumentException $e) {
            $response->json(ApiResponse::error($e->getMessage()), 400);
        }
    }

    private function handleStorageExists(Request $request, Response $response): void
    {
        if ($this->requireAuth($request, $response) === null) {
            return;
        }

        $file = $request->getQuery('file', '');
        $dir = $request->getQuery('dir', 'settings');

        if ($file === '') {
            $response->json(ApiResponse::error('File parameter required'), 400);
            return;
        }

        try {
            $exists = $this->storage->exists($file, $dir);
            $response->json(ApiResponse::ok(['exists' => $exists]));
        } catch (\InvalidArgumentException $e) {
            $response->json(ApiResponse::error($e->getMessage()), 400);
        }
    }

    private function handleStorageList(Request $request, Response $response): void
    {
        if ($this->requireAuth($request, $response) === null) {
            return;
        }

        $dir = $request->getQuery('dir', '');
        $ext = $request->getQuery('ext');

        if ($dir === '') {
            $response->json(ApiResponse::error('Dir parameter required'), 400);
            return;
        }

        try {
            $files = $this->storage->list($dir, $ext);
            $response->json(ApiResponse::ok($files));
        } catch (\InvalidArgumentException $e) {
            $response->json(ApiResponse::error($e->getMessage()), 400);
        }
    }

    // ─────────────────────────────────────────
    // ファイルハンドラー
    // ─────────────────────────────────────────

    private function handleFileUpload(Request $request, Response $response): void
    {
        if ($this->requireAuth($request, $response) === null) {
            return;
        }

        $file = $request->getFile('file');
        $body = $request->getBody();
        $path = $body['path'] ?? ($_POST['path'] ?? '');

        if ($file === null || $path === '') {
            $response->json(ApiResponse::error('File and path required'), 400);
            return;
        }

        $result = $this->files->upload($file, $path);
        $status = $result['success'] ? 200 : 400;
        $response->json(ApiResponse::ok($result), $status);
    }

    private function handleImageUpload(Request $request, Response $response): void
    {
        if ($this->requireAuth($request, $response) === null) {
            return;
        }

        $file = $request->getFile('file');
        $body = $request->getBody();
        $path = $body['path'] ?? ($_POST['path'] ?? '');
        $optionsRaw = $body['options'] ?? ($_POST['options'] ?? '{}');

        if ($file === null || $path === '') {
            $response->json(ApiResponse::error('File and path required'), 400);
            return;
        }

        $options = is_string($optionsRaw) ? (json_decode($optionsRaw, true) ?? []) : $optionsRaw;
        $result = $this->files->uploadImage($file, $path, $options);
        $status = $result['success'] ? 200 : 400;
        $response->json(ApiResponse::ok($result), $status);
    }

    private function handleFileDownload(Request $request, Response $response): void
    {
        if ($this->requireAuth($request, $response) === null) {
            return;
        }

        $path = $request->getQuery('path', '');
        if ($path === '') {
            $response->json(ApiResponse::error('Path parameter required'), 400);
            return;
        }

        $resolved = $this->files->resolve($path);
        if ($resolved === null) {
            $response->json(ApiResponse::error('File not found'), 404);
            return;
        }

        $mime = MimeType::fromPath($resolved);
        $response->file($resolved, $mime);
    }

    private function handleFileDelete(Request $request, Response $response): void
    {
        if ($this->requireAuth($request, $response) === null) {
            return;
        }

        $body = $request->getBody();
        $path = $body['path'] ?? '';

        if ($path === '') {
            $response->json(ApiResponse::error('Path required'), 400);
            return;
        }

        $this->files->deleteFile($path);
        $response->json(ApiResponse::ok());
    }

    private function handleFileExists(Request $request, Response $response): void
    {
        if ($this->requireAuth($request, $response) === null) {
            return;
        }

        $path = $request->getQuery('path', '');
        if ($path === '') {
            $response->json(ApiResponse::error('Path parameter required'), 400);
            return;
        }

        $exists = $this->files->fileExists($path);
        $response->json(ApiResponse::ok(['exists' => $exists]));
    }

    private function handleFileInfo(Request $request, Response $response): void
    {
        if ($this->requireAuth($request, $response) === null) {
            return;
        }

        $path = $request->getQuery('path', '');
        if ($path === '') {
            $response->json(ApiResponse::error('Path parameter required'), 400);
            return;
        }

        $info = $this->files->getImageInfo($path);
        if ($info === null) {
            $response->json(ApiResponse::error('File not found or not an image'), 404);
            return;
        }

        $response->json(ApiResponse::ok($info));
    }

    // ─────────────────────────────────────────
    // Git ハンドラー
    // ─────────────────────────────────────────

    private function handleGitConfigure(Request $request, Response $response): void
    {
        if ($this->requireAuth($request, $response) === null) {
            return;
        }

        $body = $request->getBody();
        $this->git->configure($body);
        $response->json(ApiResponse::ok());
    }

    private function handleGitGetConfig(Request $request, Response $response): void
    {
        if ($this->requireAuth($request, $response) === null) {
            return;
        }

        $config = $this->git->getConfig();
        $response->json(ApiResponse::ok($config));
    }

    private function handleGitTest(Request $request, Response $response): void
    {
        if ($this->requireAuth($request, $response) === null) {
            return;
        }

        $result = $this->git->testConnection();
        $response->json(ApiResponse::ok($result));
    }

    private function handleGitPull(Request $request, Response $response): void
    {
        if ($this->requireAuth($request, $response) === null) {
            return;
        }

        $result = $this->git->pull();
        $response->json(ApiResponse::ok($result));
    }

    private function handleGitPush(Request $request, Response $response): void
    {
        if ($this->requireAuth($request, $response) === null) {
            return;
        }

        $result = $this->git->push();
        $response->json(ApiResponse::ok($result));
    }

    private function handleGitLog(Request $request, Response $response): void
    {
        if ($this->requireAuth($request, $response) === null) {
            return;
        }

        $limit = max(1, min(100, (int) ($request->getQuery('limit', '20'))));
        $result = $this->git->log($limit);
        $response->json(ApiResponse::ok($result));
    }

    private function handleGitStatus(Request $request, Response $response): void
    {
        if ($this->requireAuth($request, $response) === null) {
            return;
        }

        $result = $this->git->status();
        $response->json(ApiResponse::ok($result));
    }

    // ─────────────────────────────────────────
    // ヘルスチェック
    // ─────────────────────────────────────────

    private function handleHealth(Request $request, Response $response): void
    {
        $checks = [];

        // ファイルシステムチェック
        $fsWritable = is_writable($this->dataDir);
        $checks['filesystem'] = [
            'status' => $fsWritable ? 'ok' : 'error',
            'message' => $fsWritable ? 'Data directory writable' : 'Data directory not writable',
        ];

        // Git チェック
        $gitResult = (new \ASS\Utilities\GitCommand($this->repoDir))->execute('--version', []);
        $checks['git'] = [
            'status' => $gitResult['exitCode'] === 0 ? 'ok' : 'warning',
            'message' => $gitResult['exitCode'] === 0 ? $gitResult['stdout'] : 'Git not available',
        ];

        $allOk = true;
        foreach ($checks as $check) {
            if ($check['status'] === 'error') {
                $allOk = false;
                break;
            }
        }

        $response->json(ApiResponse::ok([
            'status' => $allOk ? 'ok' : 'degraded',
            'version' => 'Ver.2.2-43',
            'runtime' => 'PHP ' . PHP_VERSION,
            'time' => gmdate('Y-m-d\TH:i:s\Z'),
            'checks' => $checks,
        ]));
    }

    // ─────────────────────────────────────────
    // 管理画面: 認証ヘルパー
    // ─────────────────────────────────────────

    private function requireAdminSession(Request $request, Response $response): ?array
    {
        $token = $request->getCookie('ass_session') ?? '';
        if ($token === '') {
            $this->adminRedirect($response, '/ass-admin/login');
            return null;
        }

        $session = $this->auth->getSession($token);
        if ($session === null) {
            $response->clearCookie('ass_session');
            $this->adminRedirect($response, '/ass-admin/login');
            return null;
        }

        return $session;
    }

    private function adminRedirect(Response $response, string $url): void
    {
        $response->setHeader('Location', $url);
        $response->json([], 302);
    }

    /**
     * HTML レスポンスを送信する。
     * Response クラスの json() は JSON 専用のため、直接出力する。
     */
    private function sendHtml(Response $response, string $html, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        echo $html;
    }

    // ─────────────────────────────────────────
    // 管理画面: ログイン/ログアウト
    // ─────────────────────────────────────────

    private function adminLoginPage(Request $request, Response $response): void
    {
        $token = $request->getCookie('ass_session') ?? '';
        if ($token !== '' && $this->auth->getSession($token) !== null) {
            $this->adminRedirect($response, '/ass-admin/');
            return;
        }

        $this->sendHtml($response, AdminTemplate::renderLogin());
    }

    private function adminLoginAction(Request $request, Response $response): void
    {
        $body = $request->getBody();
        $username = $body['username'] ?? ($_POST['username'] ?? '');
        $password = $body['password'] ?? ($_POST['password'] ?? '');

        $result = $this->auth->authenticate($username, $password);

        if (!$result['authenticated']) {
            $this->sendHtml($response, AdminTemplate::renderLogin('Invalid credentials'), 401);
            return;
        }

        $response->setCookie('ass_session', $result['token'], [
            'expires' => time() + 3600,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        $this->adminRedirect($response, '/ass-admin/');
    }

    private function adminLogout(Request $request, Response $response): void
    {
        $token = $request->getCookie('ass_session') ?? '';
        if ($token !== '') {
            $this->auth->logout($token);
        }
        $response->clearCookie('ass_session');
        $this->adminRedirect($response, '/ass-admin/login');
    }

    // ─────────────────────────────────────────
    // 管理画面: ダッシュボード
    // ─────────────────────────────────────────

    private function adminDashboard(Request $request, Response $response): void
    {
        if ($this->requireAdminSession($request, $response) === null) {
            return;
        }

        $e = fn(string $s) => AdminTemplate::esc($s);
        $users = $this->userManager->listUsers();
        $sessions = $this->sessionAdmin->listSessions();
        $storageStats = $this->diagnostics->getStorageStats();
        $gitStatus = $this->git->status();
        $serverInfo = $this->diagnostics->getServerInfo();

        $totalFiles = 0;
        $totalSize = 0;
        foreach ($storageStats as $k => $v) {
            if ($k !== '_disk' && isset($v['files'])) {
                $totalFiles += $v['files'];
                $totalSize += $v['size'];
            }
        }

        $gitBadge = $gitStatus['clean']
            ? '<span class="badge badge-ok">Clean</span>'
            : '<span class="badge badge-warn">' . count($gitStatus['changes']) . ' changes</span>';

        $content = <<<HTML
        <div class="stats">
            <div class="stat-card"><div class="label">Users</div><div class="value">{$e((string)count($users))}</div></div>
            <div class="stat-card"><div class="label">Active Sessions</div><div class="value">{$e((string)count($sessions))}</div></div>
            <div class="stat-card"><div class="label">Files</div><div class="value">{$e((string)$totalFiles)}</div></div>
            <div class="stat-card"><div class="label">Storage</div><div class="value">{$e(ServerDiagnostics::humanSize($totalSize))}</div></div>
        </div>
        <div class="card">
            <h2>Server</h2>
            <table>
                <tr><td>PHP</td><td>{$e($serverInfo['php_version'])} ({$e($serverInfo['php_sapi'])})</td></tr>
                <tr><td>OS</td><td>{$e($serverInfo['os'])}</td></tr>
                <tr><td>Disk</td><td>{$e($storageStats['_disk']['free'] ?? 'unknown')} free / {$e($storageStats['_disk']['total'] ?? 'unknown')} total</td></tr>
                <tr><td>Git</td><td>{$gitBadge}</td></tr>
            </table>
        </div>
        HTML;

        $this->sendHtml($response, AdminTemplate::render('Dashboard', $content, 'dashboard'));
    }

    // ─────────────────────────────────────────
    // 管理画面: ユーザー管理
    // ─────────────────────────────────────────

    private function adminUsers(Request $request, Response $response): void
    {
        if ($this->requireAdminSession($request, $response) === null) {
            return;
        }

        $e = fn(string $s) => AdminTemplate::esc($s);
        $msg = $request->getQuery('msg', '');
        $err = $request->getQuery('err', '');
        $users = $this->userManager->listUsers();

        $alert = '';
        if ($msg !== '') {
            $alert = '<div class="alert alert-success">' . $e($msg) . '</div>';
        }
        if ($err !== '') {
            $alert = '<div class="alert alert-error">' . $e($err) . '</div>';
        }

        $rows = '';
        foreach ($users as $u) {
            $badge = '<span class="badge badge-' . $e($u['role']) . '">' . $e($u['role']) . '</span>';
            $rows .= <<<HTML
            <tr>
                <td class="mono">{$e($u['id'])}</td>
                <td>{$e($u['username'])}</td>
                <td>{$badge}</td>
                <td>
                    <form method="POST" action="/ass-admin/users/delete" style="display:inline">
                        <input type="hidden" name="id" value="{$e($u['id'])}">
                        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Delete user {$e($u['username'])}?')">Delete</button>
                    </form>
                </td>
            </tr>
            HTML;
        }

        $content = <<<HTML
        {$alert}
        <div class="card">
            <h2>Add User</h2>
            <form method="POST" action="/ass-admin/users/add">
                <div class="form-inline">
                    <div class="form-group"><label>Username</label><input type="text" name="username" required></div>
                    <div class="form-group"><label>Password</label><input type="password" name="password" required></div>
                    <div class="form-group">
                        <label>Role</label>
                        <select name="role"><option value="admin">admin</option><option value="editor">editor</option><option value="viewer">viewer</option></select>
                    </div>
                    <button type="submit" class="btn btn-primary">Add</button>
                </div>
            </form>
        </div>
        <div class="card">
            <h2>Users ({$e((string)count($users))})</h2>
            <table>
                <thead><tr><th>ID</th><th>Username</th><th>Role</th><th>Actions</th></tr></thead>
                <tbody>{$rows}</tbody>
            </table>
        </div>
        <div class="card">
            <h2>Change Password</h2>
            <form method="POST" action="/ass-admin/users/password">
                <div class="form-inline">
                    <div class="form-group">
                        <label>User</label>
                        <select name="id">
        HTML;

        foreach ($users as $u) {
            $content .= '<option value="' . $e($u['id']) . '">' . $e($u['username']) . '</option>';
        }

        $content .= <<<HTML
                        </select>
                    </div>
                    <div class="form-group"><label>New Password</label><input type="password" name="password" required></div>
                    <button type="submit" class="btn btn-primary">Change</button>
                </div>
            </form>
        </div>
        HTML;

        $this->sendHtml($response, AdminTemplate::render('Users', $content, 'users'));
    }

    private function adminUserAdd(Request $request, Response $response): void
    {
        if ($this->requireAdminSession($request, $response) === null) {
            return;
        }

        $body = $request->getBody();
        $username = $body['username'] ?? ($_POST['username'] ?? '');
        $password = $body['password'] ?? ($_POST['password'] ?? '');
        $role = $body['role'] ?? ($_POST['role'] ?? 'admin');

        $result = $this->userManager->addUser($username, $password, $role);

        if ($result['success']) {
            $this->adminRedirect($response, '/ass-admin/users?msg=User+added');
        } else {
            $this->adminRedirect($response, '/ass-admin/users?err=' . urlencode($result['error'] ?? 'Failed'));
        }
    }

    private function adminUserDelete(Request $request, Response $response): void
    {
        if ($this->requireAdminSession($request, $response) === null) {
            return;
        }

        $body = $request->getBody();
        $id = $body['id'] ?? ($_POST['id'] ?? '');
        $result = $this->userManager->deleteUser($id);

        if ($result['success']) {
            $this->adminRedirect($response, '/ass-admin/users?msg=User+deleted');
        } else {
            $this->adminRedirect($response, '/ass-admin/users?err=' . urlencode($result['error'] ?? 'Failed'));
        }
    }

    private function adminUserPassword(Request $request, Response $response): void
    {
        if ($this->requireAdminSession($request, $response) === null) {
            return;
        }

        $body = $request->getBody();
        $id = $body['id'] ?? ($_POST['id'] ?? '');
        $password = $body['password'] ?? ($_POST['password'] ?? '');
        $result = $this->userManager->changePassword($id, $password);

        if ($result['success']) {
            $this->adminRedirect($response, '/ass-admin/users?msg=Password+changed');
        } else {
            $this->adminRedirect($response, '/ass-admin/users?err=' . urlencode($result['error'] ?? 'Failed'));
        }
    }

    private function adminUserRole(Request $request, Response $response): void
    {
        if ($this->requireAdminSession($request, $response) === null) {
            return;
        }

        $body = $request->getBody();
        $id = $body['id'] ?? ($_POST['id'] ?? '');
        $role = $body['role'] ?? ($_POST['role'] ?? '');
        $result = $this->userManager->changeRole($id, $role);

        if ($result['success']) {
            $this->adminRedirect($response, '/ass-admin/users?msg=Role+changed');
        } else {
            $this->adminRedirect($response, '/ass-admin/users?err=' . urlencode($result['error'] ?? 'Failed'));
        }
    }

    // ─────────────────────────────────────────
    // 管理画面: セッション管理
    // ─────────────────────────────────────────

    private function adminSessions(Request $request, Response $response): void
    {
        if ($this->requireAdminSession($request, $response) === null) {
            return;
        }

        $e = fn(string $s) => AdminTemplate::esc($s);
        $msg = $request->getQuery('msg', '');
        $sessions = $this->sessionAdmin->listSessions();

        $alert = $msg !== '' ? '<div class="alert alert-success">' . $e($msg) . '</div>' : '';

        $rows = '';
        foreach ($sessions as $s) {
            $rows .= <<<HTML
            <tr>
                <td class="mono">{$e(substr($s['id'] ?? '', 0, 16))}...</td>
                <td>{$e($s['userId'] ?? '')}</td>
                <td><span class="badge badge-{$e($s['role'] ?? 'viewer')}">{$e($s['role'] ?? '')}</span></td>
                <td>{$e($s['createdAt'] ?? '')}</td>
                <td>{$e($s['expiresAt'] ?? '')}</td>
                <td>
                    <form method="POST" action="/ass-admin/sessions/destroy" style="display:inline">
                        <input type="hidden" name="id" value="{$e($s['id'] ?? '')}">
                        <button type="submit" class="btn btn-danger btn-sm">Force Logout</button>
                    </form>
                </td>
            </tr>
            HTML;
        }

        $content = <<<HTML
        {$alert}
        <div class="justify-between mb-16">
            <span></span>
            <div class="flex gap-8">
                <form method="POST" action="/ass-admin/sessions/purge" style="display:inline">
                    <button type="submit" class="btn btn-primary">Purge Expired</button>
                </form>
                <form method="POST" action="/ass-admin/sessions/destroy-all" style="display:inline">
                    <button type="submit" class="btn btn-danger" onclick="return confirm('Force logout ALL sessions?')">Destroy All</button>
                </form>
            </div>
        </div>
        <div class="card">
            <h2>Active Sessions ({$e((string)count($sessions))})</h2>
            <table>
                <thead><tr><th>Session ID</th><th>User ID</th><th>Role</th><th>Created</th><th>Expires</th><th>Actions</th></tr></thead>
                <tbody>{$rows}</tbody>
            </table>
        </div>
        HTML;

        $this->sendHtml($response, AdminTemplate::render('Sessions', $content, 'sessions'));
    }

    private function adminSessionDestroy(Request $request, Response $response): void
    {
        if ($this->requireAdminSession($request, $response) === null) {
            return;
        }

        $body = $request->getBody();
        $id = $body['id'] ?? ($_POST['id'] ?? '');
        $this->sessionAdmin->forceLogout($id);
        $this->adminRedirect($response, '/ass-admin/sessions?msg=Session+destroyed');
    }

    private function adminSessionDestroyAll(Request $request, Response $response): void
    {
        if ($this->requireAdminSession($request, $response) === null) {
            return;
        }

        $count = $this->sessionAdmin->forceLogoutAll();
        $this->adminRedirect($response, '/ass-admin/sessions?msg=' . $count . '+sessions+destroyed');
    }

    private function adminSessionPurge(Request $request, Response $response): void
    {
        if ($this->requireAdminSession($request, $response) === null) {
            return;
        }

        $expired = $this->sessionAdmin->purgeExpiredSessions();
        $csrf = $this->sessionAdmin->purgeExpiredCsrf();
        $this->adminRedirect($response, '/ass-admin/sessions?msg=Purged+' . $expired . '+sessions+and+' . $csrf . '+CSRF+tokens');
    }

    // ─────────────────────────────────────────
    // 管理画面: サーバ情報
    // ─────────────────────────────────────────

    private function adminServer(Request $request, Response $response): void
    {
        if ($this->requireAdminSession($request, $response) === null) {
            return;
        }

        $e = fn(string $s) => AdminTemplate::esc($s);
        $info = $this->diagnostics->getServerInfo();
        $reqs = $this->diagnostics->checkRequirements();

        $infoRows = '';
        $fields = [
            'PHP Version' => $info['php_version'],
            'SAPI' => $info['php_sapi'],
            'OS' => $info['os'],
            'Hostname' => $info['hostname'],
            'Server Time' => $info['server_time'],
            'Timezone' => $info['timezone'],
            'Memory Limit' => $info['memory_limit'],
            'Max Upload' => $info['max_upload'],
            'Post Max Size' => $info['post_max_size'],
            'Max Execution Time' => $info['max_execution_time'] . 's',
        ];
        foreach ($fields as $label => $value) {
            $infoRows .= "<tr><td>{$e($label)}</td><td class=\"mono\">{$e($value)}</td></tr>";
        }

        $reqRows = '';
        foreach ($reqs as $ext => $check) {
            $status = $check['loaded']
                ? '<span class="badge badge-ok">Loaded</span>'
                : ($check['required'] ? '<span class="badge badge-error">Missing</span>' : '<span class="badge badge-warn">Not loaded</span>');
            $reqLabel = $check['required'] ? 'Required' : 'Optional';
            $reqRows .= "<tr><td class=\"mono\">{$e($ext)}</td><td>{$e($reqLabel)}</td><td>{$status}</td></tr>";
        }

        $extList = $e(implode(', ', $info['extensions']));

        $content = <<<HTML
        <div class="card">
            <h2>Server Information</h2>
            <table><tbody>{$infoRows}</tbody></table>
        </div>
        <div class="card">
            <h2>PHP Extensions</h2>
            <table>
                <thead><tr><th>Extension</th><th>Type</th><th>Status</th></tr></thead>
                <tbody>{$reqRows}</tbody>
            </table>
        </div>
        <div class="card">
            <h2>All Loaded Extensions</h2>
            <div class="pre-wrap">{$extList}</div>
        </div>
        HTML;

        $this->sendHtml($response, AdminTemplate::render('Server', $content, 'server'));
    }

    // ─────────────────────────────────────────
    // 管理画面: Git
    // ─────────────────────────────────────────

    private function adminGit(Request $request, Response $response): void
    {
        if ($this->requireAdminSession($request, $response) === null) {
            return;
        }

        $e = fn(string $s) => AdminTemplate::esc($s);
        $config = $this->git->getConfig();
        $status = $this->git->status();
        $logData = $this->git->log(10);

        $statusBadge = $status['clean']
            ? '<span class="badge badge-ok">Clean</span>'
            : '<span class="badge badge-warn">' . count($status['changes']) . ' uncommitted changes</span>';

        $changesRows = '';
        foreach ($status['changes'] as $change) {
            $changesRows .= "<tr><td class=\"mono\">{$e($change['status'])}</td><td>{$e($change['file'])}</td></tr>";
        }

        $commitRows = '';
        foreach ($logData['commits'] as $commit) {
            $shortHash = substr($commit['hash'], 0, 8);
            $commitRows .= <<<HTML
            <tr>
                <td class="mono">{$e($shortHash)}</td>
                <td>{$e($commit['message'])}</td>
                <td>{$e($commit['author'])}</td>
                <td>{$e($commit['date'])}</td>
            </tr>
            HTML;
        }

        $content = <<<HTML
        <div class="card">
            <h2>Configuration</h2>
            <table>
                <tr><td>Remote URL</td><td class="mono">{$e($config['remoteUrl'] ?: '(not set)')}</td></tr>
                <tr><td>Branch</td><td class="mono">{$e($config['branch'])}</td></tr>
                <tr><td>User Name</td><td>{$e($config['userName'] ?: '(not set)')}</td></tr>
                <tr><td>User Email</td><td>{$e($config['userEmail'] ?: '(not set)')}</td></tr>
            </table>
        </div>
        <div class="card">
            <h2>Status {$statusBadge}</h2>
        HTML;

        if (!$status['clean']) {
            $content .= <<<HTML
            <table>
                <thead><tr><th>Status</th><th>File</th></tr></thead>
                <tbody>{$changesRows}</tbody>
            </table>
            HTML;
        }

        $content .= <<<HTML
        </div>
        <div class="card">
            <h2>Recent Commits</h2>
            <table>
                <thead><tr><th>Hash</th><th>Message</th><th>Author</th><th>Date</th></tr></thead>
                <tbody>{$commitRows}</tbody>
            </table>
        </div>
        HTML;

        $this->sendHtml($response, AdminTemplate::render('Git', $content, 'git'));
    }

    // ─────────────────────────────────────────
    // 管理画面: ストレージ
    // ─────────────────────────────────────────

    private function adminStorage(Request $request, Response $response): void
    {
        if ($this->requireAdminSession($request, $response) === null) {
            return;
        }

        $e = fn(string $s) => AdminTemplate::esc($s);
        $stats = $this->diagnostics->getStorageStats();

        $rows = '';
        foreach ($stats as $dir => $info) {
            if ($dir === '_disk') {
                continue;
            }
            $rows .= <<<HTML
            <tr>
                <td class="mono">{$e($dir)}/</td>
                <td>{$e((string)$info['files'])}</td>
                <td>{$e($info['sizeHuman'])}</td>
            </tr>
            HTML;
        }

        $disk = $stats['_disk'] ?? [];

        $content = <<<HTML
        <div class="stats">
            <div class="stat-card"><div class="label">Disk Free</div><div class="value">{$e($disk['free'] ?? 'unknown')}</div></div>
            <div class="stat-card"><div class="label">Disk Total</div><div class="value">{$e($disk['total'] ?? 'unknown')}</div></div>
        </div>
        <div class="card">
            <h2>Directory Usage</h2>
            <table>
                <thead><tr><th>Directory</th><th>Files</th><th>Size</th></tr></thead>
                <tbody>{$rows}</tbody>
            </table>
        </div>
        HTML;

        $this->sendHtml($response, AdminTemplate::render('Storage', $content, 'storage'));
    }
}
