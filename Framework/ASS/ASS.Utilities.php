<?php

declare(strict_types=1);

/**
 * ASS.Utilities.php — Adlaire Server System / Engine 3: Utils
 *
 * ユーティリティ・補助機能。外部ライブラリ依存ゼロ。
 */

namespace ASS\Utilities;

// ─────────────────────────────────────────────
// パス安全性ユーティリティ
// ─────────────────────────────────────────────

final class PathSecurity
{
    /** ディレクトリトラバーサルを防止する */
    public static function sanitize(string $path): string
    {
        $path = str_replace("\0", '', $path);
        // Decode URL-encoded sequences to prevent bypass via %2e%2e%2f
        $path = rawurldecode($path);
        $path = str_replace(['\\', '../', '..\\'], ['/', '', ''], $path);
        $path = preg_replace('#/+#', '/', $path) ?? $path;
        $path = ltrim($path, '/');

        $segments = explode('/', $path);
        $safe = [];
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                continue;
            }
            $safe[] = $segment;
        }

        return implode('/', $safe);
    }

    /** 許可ディレクトリ名を検証する */
    public static function isAllowedDir(string $dir): bool
    {
        $allowed = ['settings', 'content', 'collections', 'uploads', 'cache', 'backups', 'logs'];
        // Allow exact match
        if (in_array($dir, $allowed, true)) {
            return true;
        }
        // Allow subdirectories of allowed dirs (e.g., "revisions/page", "collections/blog")
        $topDir = explode('/', $dir)[0] ?? '';
        return in_array($topDir, $allowed, true) && self::sanitize($dir) === $dir;
    }
}

// ─────────────────────────────────────────────
// セッション管理ユーティリティ
// ─────────────────────────────────────────────

final class SessionManager
{
    private string $storagePath;
    private int $ttl;

    public function __construct(string $storagePath, int $ttl = 3600)
    {
        $this->storagePath = $storagePath;
        $this->ttl = $ttl;

        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0700, true);
        }
    }

    /** セッションを作成する */
    public function create(string $userId, string $role, array $data = []): string
    {
        $id = Token::generate(48);
        $now = time();
        $session = [
            'id' => $id,
            'userId' => $userId,
            'role' => $role,
            'createdAt' => gmdate('Y-m-d\TH:i:s\Z', $now),
            'expiresAt' => gmdate('Y-m-d\TH:i:s\Z', $now + $this->ttl),
            'expiresTimestamp' => $now + $this->ttl,
            'data' => $data,
        ];

        $path = $this->sessionPath($id);
        $tmpPath = $path . '.tmp';
        file_put_contents($tmpPath, json_encode($session, JSON_UNESCAPED_UNICODE), LOCK_EX);
        rename($tmpPath, $path);

        return $id;
    }

    /** セッションを取得する（有効期限チェック付き） */
    public function get(string $id): ?array
    {
        $path = $this->sessionPath($id);
        if (!file_exists($path)) {
            return null;
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            return null;
        }

        $session = json_decode($raw, true);
        if (!is_array($session)) {
            return null;
        }

        if (($session['expiresTimestamp'] ?? 0) < time()) {
            $this->destroy($id);
            return null;
        }

        return $session;
    }

    /** セッションを破棄する */
    public function destroy(string $id): void
    {
        $path = $this->sessionPath($id);
        if (file_exists($path)) {
            unlink($path);
        }
    }

    /** 期限切れセッションを一括削除する */
    public function purgeExpired(): int
    {
        $count = 0;
        $files = glob($this->storagePath . '/sess_*.json');
        if ($files === false) {
            return 0;
        }

        $now = time();
        foreach ($files as $file) {
            // Quick check: skip files modified recently (within TTL)
            $mtime = filemtime($file);
            if ($mtime !== false && ($now - $mtime) < $this->ttl) {
                continue;
            }
            $raw = file_get_contents($file);
            if ($raw === false) {
                continue;
            }
            $session = json_decode($raw, true);
            if (!is_array($session) || ($session['expiresTimestamp'] ?? 0) < $now) {
                unlink($file);
                $count++;
            }
        }

        return $count;
    }

    /** 全アクティブセッション一覧を取得する */
    public function listAll(): array
    {
        $files = glob($this->storagePath . '/sess_*.json');
        if ($files === false) {
            return [];
        }

        $sessions = [];
        $now = time();
        foreach ($files as $file) {
            $raw = file_get_contents($file);
            if ($raw === false) {
                continue;
            }
            $session = json_decode($raw, true);
            if (!is_array($session)) {
                continue;
            }
            if (($session['expiresTimestamp'] ?? 0) >= $now) {
                unset($session['expiresTimestamp']);
                $sessions[] = $session;
            }
        }

        return $sessions;
    }

    /** 全セッションを破棄する */
    public function destroyAll(): int
    {
        $files = glob($this->storagePath . '/sess_*.json');
        if ($files === false) {
            return 0;
        }

        $count = 0;
        foreach ($files as $file) {
            unlink($file);
            $count++;
        }

        return $count;
    }

    private function sessionPath(string $id): string
    {
        return $this->storagePath . '/sess_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $id) . '.json';
    }
}

// ─────────────────────────────────────────────
// トークン生成ユーティリティ
// ─────────────────────────────────────────────

final class Token
{
    /** 暗号論的に安全なランダムトークンを生成する */
    public static function generate(int $length = 32): string
    {
        return bin2hex(random_bytes((int) ceil($length / 2)));
    }

    /** SHA-256 ハッシュを生成する */
    public static function sha256(string $input): string
    {
        return hash('sha256', $input);
    }
}

// ─────────────────────────────────────────────
// CSRFトークン管理
// ─────────────────────────────────────────────

final class CsrfManager
{
    private string $storagePath;
    private int $ttl;

    public function __construct(string $storagePath, int $ttl = 3600)
    {
        $this->storagePath = $storagePath;
        $this->ttl = $ttl;

        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0700, true);
        }
    }

    /** CSRFトークンを生成する（使い捨て） */
    public function generate(): string
    {
        $token = Token::generate(48);
        $path = $this->tokenPath($token);
        $tmpPath = $path . '.tmp';
        file_put_contents($tmpPath, (string) (time() + $this->ttl), LOCK_EX);
        rename($tmpPath, $path);
        return $token;
    }

    /** CSRFトークンを検証する（検証後削除：使い捨て） */
    public function verify(string $token): bool
    {
        $path = $this->tokenPath($token);
        if (!file_exists($path)) {
            return false;
        }

        // Atomic: rename to prevent concurrent verification of same token
        $tmpPath = $path . '.verifying';
        if (!@rename($path, $tmpPath)) {
            // Another process already consumed this token
            return false;
        }

        $expiresAt = (int) file_get_contents($tmpPath);
        unlink($tmpPath);

        return $expiresAt > time();
    }

    /** 期限切れトークンを一括削除する */
    public function purgeExpired(): int
    {
        $count = 0;
        $files = glob($this->storagePath . '/csrf_*.dat');
        if ($files === false) {
            return 0;
        }

        $now = time();
        foreach ($files as $file) {
            $expiresAt = (int) file_get_contents($file);
            if ($expiresAt < $now) {
                unlink($file);
                $count++;
            }
        }

        return $count;
    }

    private function tokenPath(string $token): string
    {
        return $this->storagePath . '/csrf_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $token) . '.dat';
    }
}

// ─────────────────────────────────────────────
// MIME タイプ判定
// ─────────────────────────────────────────────

final class MimeType
{
    private const MAP = [
        'json' => 'application/json',
        'html' => 'text/html',
        'htm' => 'text/html',
        'css' => 'text/css',
        'js' => 'application/javascript',
        'txt' => 'text/plain',
        'md' => 'text/markdown',
        'xml' => 'application/xml',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'ico' => 'image/x-icon',
        'pdf' => 'application/pdf',
        'zip' => 'application/zip',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'otf' => 'font/otf',
    ];

    public static function fromExtension(string $ext): string
    {
        return self::MAP[strtolower($ext)] ?? 'application/octet-stream';
    }

    public static function fromPath(string $path): string
    {
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        return self::fromExtension($ext);
    }
}

// ─────────────────────────────────────────────
// Git コマンド実行ユーティリティ
// ─────────────────────────────────────────────

// ─────────────────────────────────────────────
// HTML テンプレートエンジン（管理画面用）
// ─────────────────────────────────────────────

final class AdminTemplate
{
    /**
     * 管理画面の HTML を生成する。
     *
     * @param string $title ページタイトル
     * @param string $content メインコンテンツ HTML
     * @param string $activePage アクティブなナビゲーション項目
     */
    public static function render(string $title, string $content, string $activePage = ''): string
    {
        $nav = self::buildNav($activePage);
        $css = self::getStyles();

        return <<<HTML
        <!DOCTYPE html>
        <html lang="ja">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>{$title} - ASS Admin</title>
            <style>{$css}</style>
        </head>
        <body>
            <div class="layout">
                <nav class="sidebar">{$nav}</nav>
                <main class="main">
                    <header class="header"><h1>{$title}</h1></header>
                    <div class="content">{$content}</div>
                </main>
            </div>
            <script>
            function assApi(method, url, body) {
                const opts = { method, headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin' };
                if (body) opts.body = JSON.stringify(body);
                return fetch(url, opts).then(r => r.json());
            }
            function confirmAction(msg, fn) { if (confirm(msg)) fn(); }
            </script>
        </body>
        </html>
        HTML;
    }

    /** ログインページを生成する */
    public static function renderLogin(string $error = ''): string
    {
        $css = self::getStyles();
        $errorHtml = $error !== '' ? '<p class="alert alert-error">' . self::esc($error) . '</p>' : '';

        return <<<HTML
        <!DOCTYPE html>
        <html lang="ja">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Login - ASS Admin</title>
            <style>{$css}</style>
        </head>
        <body>
            <div class="login-wrap">
                <div class="login-box">
                    <h1>ASS Admin</h1>
                    <p class="subtitle">Adlaire Server System</p>
                    {$errorHtml}
                    <form method="POST" action="/ass-admin/login">
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" name="username" required autofocus>
                        </div>
                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password" required>
                        </div>
                        <button type="submit" class="btn btn-primary btn-full">Login</button>
                    </form>
                </div>
            </div>
        </body>
        </html>
        HTML;
    }

    /** HTML エスケープ */
    public static function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private static function buildNav(string $active): string
    {
        $items = [
            ['url' => '/ass-admin/', 'label' => 'Dashboard', 'id' => 'dashboard'],
            ['url' => '/ass-admin/users', 'label' => 'Users', 'id' => 'users'],
            ['url' => '/ass-admin/sessions', 'label' => 'Sessions', 'id' => 'sessions'],
            ['url' => '/ass-admin/server', 'label' => 'Server', 'id' => 'server'],
            ['url' => '/ass-admin/git', 'label' => 'Git', 'id' => 'git'],
            ['url' => '/ass-admin/storage', 'label' => 'Storage', 'id' => 'storage'],
        ];

        $html = '<div class="sidebar-brand">ASS Admin</div><ul class="nav-list">';
        foreach ($items as $item) {
            $cls = $item['id'] === $active ? ' class="active"' : '';
            $html .= "<li{$cls}><a href=\"{$item['url']}\">{$item['label']}</a></li>";
        }
        $html .= '</ul><div class="nav-footer"><a href="/ass-admin/logout">Logout</a></div>';

        return $html;
    }

    private static function getStyles(): string
    {
        return <<<'CSS'
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f5f5f5; color: #333; font-size: 14px; }
        .layout { display: flex; min-height: 100vh; }
        .sidebar { width: 220px; background: #1a1a2e; color: #e0e0e0; padding: 0; flex-shrink: 0; display: flex; flex-direction: column; }
        .sidebar-brand { padding: 20px 16px; font-size: 18px; font-weight: 700; color: #fff; border-bottom: 1px solid #2a2a4a; }
        .nav-list { list-style: none; padding: 8px 0; flex: 1; }
        .nav-list li a { display: block; padding: 10px 16px; color: #b0b0c0; text-decoration: none; transition: background .15s; }
        .nav-list li a:hover { background: #2a2a4a; color: #fff; }
        .nav-list li.active a { background: #16213e; color: #fff; border-left: 3px solid #4a9eff; }
        .nav-footer { padding: 16px; border-top: 1px solid #2a2a4a; }
        .nav-footer a { color: #b0b0c0; text-decoration: none; }
        .main { flex: 1; display: flex; flex-direction: column; }
        .header { padding: 20px 24px; background: #fff; border-bottom: 1px solid #e0e0e0; }
        .header h1 { font-size: 20px; font-weight: 600; }
        .content { padding: 24px; flex: 1; }
        .card { background: #fff; border-radius: 8px; padding: 20px; margin-bottom: 16px; border: 1px solid #e0e0e0; }
        .card h2 { font-size: 16px; margin-bottom: 12px; color: #555; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .stat-card { background: #fff; border-radius: 8px; padding: 16px; border: 1px solid #e0e0e0; }
        .stat-card .label { font-size: 12px; color: #888; text-transform: uppercase; }
        .stat-card .value { font-size: 24px; font-weight: 700; margin-top: 4px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { font-weight: 600; color: #555; font-size: 12px; text-transform: uppercase; background: #fafafa; }
        .btn { display: inline-block; padding: 8px 16px; border-radius: 6px; border: none; cursor: pointer; font-size: 13px; text-decoration: none; transition: background .15s; }
        .btn-primary { background: #4a9eff; color: #fff; }
        .btn-primary:hover { background: #3a8eef; }
        .btn-danger { background: #ef4444; color: #fff; }
        .btn-danger:hover { background: #dc2626; }
        .btn-sm { padding: 4px 10px; font-size: 12px; }
        .btn-full { width: 100%; }
        .form-group { margin-bottom: 14px; }
        .form-group label { display: block; font-size: 13px; font-weight: 500; margin-bottom: 4px; color: #555; }
        .form-group input, .form-group select { width: 100%; padding: 8px 12px; border: 1px solid #d0d0d0; border-radius: 6px; font-size: 14px; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: #4a9eff; box-shadow: 0 0 0 2px rgba(74,158,255,.2); }
        .form-inline { display: flex; gap: 8px; align-items: end; }
        .form-inline .form-group { margin-bottom: 0; }
        .alert { padding: 10px 14px; border-radius: 6px; margin-bottom: 16px; font-size: 13px; }
        .alert-success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .alert-info { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; }
        .badge-ok { background: #d1fae5; color: #065f46; }
        .badge-warn { background: #fef3c7; color: #92400e; }
        .badge-error { background: #fee2e2; color: #991b1b; }
        .badge-admin { background: #dbeafe; color: #1e40af; }
        .badge-editor { background: #e0e7ff; color: #3730a3; }
        .badge-viewer { background: #f3f4f6; color: #374151; }
        .mono { font-family: "SF Mono", "Fira Code", monospace; font-size: 12px; }
        .login-wrap { display: flex; align-items: center; justify-content: center; min-height: 100vh; background: #1a1a2e; }
        .login-box { background: #fff; padding: 32px; border-radius: 12px; width: 360px; }
        .login-box h1 { font-size: 22px; text-align: center; margin-bottom: 4px; }
        .login-box .subtitle { text-align: center; color: #888; margin-bottom: 24px; font-size: 13px; }
        .pre-wrap { white-space: pre-wrap; word-break: break-all; background: #f9fafb; padding: 12px; border-radius: 6px; font-family: monospace; font-size: 12px; max-height: 300px; overflow: auto; }
        .mt-8 { margin-top: 8px; } .mt-16 { margin-top: 16px; } .mb-16 { margin-bottom: 16px; }
        .flex { display: flex; } .gap-8 { gap: 8px; } .items-center { align-items: center; } .justify-between { display: flex; justify-content: space-between; align-items: center; }
        CSS;
    }
}

// ─────────────────────────────────────────────
// Git コマンド実行ユーティリティ
// ─────────────────────────────────────────────

final class GitCommand
{
    private string $workDir;
    private int $timeout;

    public function __construct(string $workDir, int $timeout = 30)
    {
        $this->workDir = $workDir;
        $this->timeout = $timeout;
    }

    /**
     * Git コマンドを実行する。
     *
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    public function execute(string $command, array $args = []): array
    {
        $escaped = array_map('escapeshellarg', $args);
        $full = 'git -C ' . escapeshellarg($this->workDir) . ' ' . $command . ' ' . implode(' ', $escaped);

        $descriptors = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($full, $descriptors, $pipes);
        if (!is_resource($process)) {
            return ['exitCode' => -1, 'stdout' => '', 'stderr' => 'Failed to execute git'];
        }

        // Set non-blocking and enforce timeout
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $stderr = '';
        $startTime = time();

        while (true) {
            $status = proc_get_status($process);
            if (!$status['running']) {
                break;
            }
            if ((time() - $startTime) >= $this->timeout) {
                proc_terminate($process, 9);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);
                return ['exitCode' => -1, 'stdout' => '', 'stderr' => "Git command timed out after {$this->timeout}s"];
            }
            $stdout .= stream_get_contents($pipes[1]) ?: '';
            $stderr .= stream_get_contents($pipes[2]) ?: '';
            usleep(50000); // 50ms
        }

        // Read remaining output
        $stdout .= stream_get_contents($pipes[1]) ?: '';
        $stderr .= stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        return [
            'exitCode' => $exitCode,
            'stdout' => trim($stdout),
            'stderr' => trim($stderr),
        ];
    }
}
