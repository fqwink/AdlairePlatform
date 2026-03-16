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
use ASS\Utilities\SessionManager;
use ASS\Utilities\CsrfManager;
use ASS\Utilities\MimeType;

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

        $app->auth = new AuthService($dataDir, $sessions, $csrf);
        $app->storage = new StorageService($dataDir);
        $app->files = new FileService($dataDir);
        $app->git = new GitService($dataDir, $repoDir);

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

        // CORS ヘッダー
        $response->setHeader('Access-Control-Allow-Origin', $request->getHeader('origin') ?? '*');
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

        $limit = (int) ($request->getQuery('limit', '20'));
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
            'version' => '2.1',
            'runtime' => 'PHP ' . PHP_VERSION,
            'time' => gmdate('Y-m-d\TH:i:s\Z'),
            'checks' => $checks,
        ]));
    }
}
