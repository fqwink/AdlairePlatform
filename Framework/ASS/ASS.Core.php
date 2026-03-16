<?php

declare(strict_types=1);

/**
 * ASS.Core.php — Adlaire Server System / Engine 1: Core
 *
 * ビジネスロジック。認証・データストレージ・Git操作の具象実装。
 * 外部ライブラリ依存ゼロ。ACS インターフェース契約に準拠。
 */

namespace ASS\Core;

use ASS\Interface\AuthServiceInterface;
use ASS\Interface\StorageServiceInterface;
use ASS\Interface\FileServiceInterface;
use ASS\Interface\GitServiceInterface;
use ASS\Utilities\SessionManager;
use ASS\Utilities\CsrfManager;
use ASS\Utilities\Token;
use ASS\Utilities\PathSecurity;
use ASS\Utilities\MimeType;
use ASS\Utilities\GitCommand;
use ASS\Utilities\AdminTemplate;

// ─────────────────────────────────────────────
// 認証サービス
// ─────────────────────────────────────────────

final class AuthService implements AuthServiceInterface
{
    private string $dataDir;
    private SessionManager $sessions;
    private CsrfManager $csrf;

    public function __construct(string $dataDir, SessionManager $sessions, CsrfManager $csrf)
    {
        $this->dataDir = $dataDir;
        $this->sessions = $sessions;
        $this->csrf = $csrf;
    }

    public function authenticate(string $username, string $password): array
    {
        $users = $this->loadUsers();
        $passwordHash = Token::sha256($password);

        foreach ($users as $user) {
            if (strcasecmp($user['username'] ?? '', $username) === 0 && ($user['password'] ?? '') === $passwordHash) {
                $sessionId = $this->sessions->create($user['id'], $user['role'] ?? 'admin');

                return [
                    'authenticated' => true,
                    'user' => [
                        'id' => $user['id'],
                        'username' => $user['username'],
                        'role' => $user['role'] ?? 'admin',
                    ],
                    'token' => $sessionId,
                    'error' => null,
                ];
            }
        }

        return [
            'authenticated' => false,
            'user' => null,
            'token' => null,
            'error' => 'Invalid credentials',
        ];
    }

    public function logout(string $sessionId): void
    {
        $this->sessions->destroy($sessionId);
    }

    public function getSession(string $sessionId): ?array
    {
        $session = $this->sessions->get($sessionId);
        if ($session === null) {
            return null;
        }

        unset($session['expiresTimestamp']);
        return $session;
    }

    public function verifyToken(string $token): array
    {
        $session = $this->sessions->get($token);
        if ($session === null) {
            return ['authenticated' => false, 'user' => null];
        }

        $users = $this->loadUsers();
        foreach ($users as $user) {
            if ($user['id'] === $session['userId']) {
                return [
                    'authenticated' => true,
                    'user' => [
                        'id' => $user['id'],
                        'username' => $user['username'],
                        'role' => $user['role'] ?? 'admin',
                    ],
                ];
            }
        }

        return ['authenticated' => false, 'user' => null];
    }

    public function generateCsrfToken(): string
    {
        return $this->csrf->generate();
    }

    public function verifyCsrfToken(string $token): bool
    {
        return $this->csrf->verify($token);
    }

    /** @return array<array{id: string, username: string, password: string, role: string}> */
    private function loadUsers(): array
    {
        $path = $this->dataDir . '/settings/users.json';
        if (!file_exists($path)) {
            return [];
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            return [];
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return [];
        }

        return $data;
    }
}

// ─────────────────────────────────────────────
// データストレージサービス
// ─────────────────────────────────────────────

final class StorageService implements StorageServiceInterface
{
    private string $dataDir;

    public function __construct(string $dataDir)
    {
        $this->dataDir = $dataDir;
    }

    public function read(string $file, string $dir = 'settings'): mixed
    {
        $this->validateDir($dir);
        $path = $this->resolvePath($file, $dir);

        if (!file_exists($path)) {
            throw new \RuntimeException('File not found');
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new \RuntimeException('Failed to read file');
        }

        $ext = pathinfo($file, PATHINFO_EXTENSION);
        if ($ext === 'json') {
            $decoded = json_decode($raw, true);
            return $decoded;
        }

        return $raw;
    }

    public function write(string $file, mixed $data, string $dir = 'settings'): void
    {
        $this->validateDir($dir);
        $path = $this->resolvePath($file, $dir);

        $dirPath = dirname($path);
        if (!is_dir($dirPath)) {
            mkdir($dirPath, 0755, true);
        }

        $ext = pathinfo($file, PATHINFO_EXTENSION);
        if ($ext === 'json' || !is_string($data)) {
            $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            if ($encoded === false) {
                throw new \RuntimeException('Failed to encode data as JSON: ' . json_last_error_msg());
            }
            $result = file_put_contents($path, $encoded, LOCK_EX);
        } else {
            $result = file_put_contents($path, $data, LOCK_EX);
        }
        if ($result === false) {
            throw new \RuntimeException("Failed to write file: {$file}");
        }
    }

    public function delete(string $file, string $dir = 'settings'): void
    {
        $this->validateDir($dir);
        $path = $this->resolvePath($file, $dir);

        if (file_exists($path)) {
            unlink($path);
        }
    }

    public function exists(string $file, string $dir = 'settings'): bool
    {
        $this->validateDir($dir);
        $path = $this->resolvePath($file, $dir);
        return file_exists($path);
    }

    public function list(string $dir, ?string $ext = null): array
    {
        $this->validateDir($dir);
        $fullDir = $this->dataDir . '/' . $dir;

        if (!is_dir($fullDir)) {
            return [];
        }

        $pattern = $ext !== null
            ? $fullDir . '/*.' . preg_replace('/[^a-zA-Z0-9]/', '', $ext)
            : $fullDir . '/*';

        $files = glob($pattern);
        if ($files === false) {
            return [];
        }

        $result = [];
        foreach ($files as $file) {
            if (is_file($file)) {
                $result[] = basename($file);
            }
        }

        sort($result);
        return $result;
    }

    private function resolvePath(string $file, string $dir): string
    {
        $safe = PathSecurity::sanitize($file);
        return $this->dataDir . '/' . $dir . '/' . $safe;
    }

    private function validateDir(string $dir): void
    {
        if (!PathSecurity::isAllowedDir($dir)) {
            throw new \InvalidArgumentException("Invalid directory: {$dir}");
        }
    }
}

// ─────────────────────────────────────────────
// ファイルサービス
// ─────────────────────────────────────────────

final class FileService implements FileServiceInterface
{
    private string $dataDir;

    public function __construct(string $dataDir)
    {
        $this->dataDir = $dataDir;
    }

    public function upload(array $file, string $path): array
    {
        $safePath = PathSecurity::sanitize($path);
        $dest = $this->dataDir . '/uploads/' . $safePath;

        $destDir = dirname($dest);
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }

        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['success' => false, 'path' => $safePath, 'size' => 0, 'error' => 'Invalid upload'];
        }

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            return ['success' => false, 'path' => $safePath, 'size' => 0, 'error' => 'Upload failed'];
        }

        return [
            'success' => true,
            'path' => $safePath,
            'size' => filesize($dest) ?: 0,
        ];
    }

    public function uploadImage(array $file, string $path, array $options = []): array
    {
        // Validate MIME type before upload
        if (isset($file['tmp_name']) && is_uploaded_file($file['tmp_name'])) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);
            $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($mime, $allowed, true)) {
                return ['success' => false, 'path' => $path, 'size' => 0, 'error' => 'Invalid image MIME type: ' . $mime];
            }
        }

        $result = $this->upload($file, $path);
        if (!$result['success']) {
            return $result;
        }

        $dest = $this->dataDir . '/uploads/' . $result['path'];

        // サムネイル生成
        if ($options['generateThumbnail'] ?? false) {
            $this->generateThumbnail($dest, $options);
        }

        return $result;
    }

    public function resolve(string $path): ?string
    {
        $safePath = PathSecurity::sanitize($path);
        $full = $this->dataDir . '/uploads/' . $safePath;

        if (!file_exists($full) || !is_file($full)) {
            return null;
        }

        // uploads ディレクトリ外へのアクセスを防止
        $realBase = realpath($this->dataDir . '/uploads');
        $realFull = realpath($full);
        if ($realBase === false || $realFull === false || !str_starts_with($realFull, $realBase)) {
            return null;
        }

        return $realFull;
    }

    public function deleteFile(string $path): void
    {
        $resolved = $this->resolve($path);
        if ($resolved !== null) {
            unlink($resolved);
        }
    }

    public function fileExists(string $path): bool
    {
        return $this->resolve($path) !== null;
    }

    public function getImageInfo(string $path): ?array
    {
        $resolved = $this->resolve($path);
        if ($resolved === null) {
            return null;
        }

        $info = @getimagesize($resolved);
        if ($info === false) {
            return null;
        }

        $size = filesize($resolved) ?: 0;

        return [
            'width' => $info[0],
            'height' => $info[1],
            'mime' => $info['mime'],
            'size' => $size,
            'aspect' => $info[1] > 0 ? round($info[0] / $info[1], 3) : 0.0,
        ];
    }

    private function generateThumbnail(string $sourcePath, array $options): void
    {
        $info = @getimagesize($sourcePath);
        if ($info === false) {
            return;
        }

        $maxWidth = max(1, (int) ($options['maxWidth'] ?? 300));
        $maxHeight = max(1, (int) ($options['maxHeight'] ?? 300));
        $quality = max(1, min(100, (int) ($options['quality'] ?? 80)));

        [$origW, $origH] = $info;
        if ($origW <= 0 || $origH <= 0) {
            return;
        }
        $ratio = min($maxWidth / $origW, $maxHeight / $origH, 1.0);
        $newW = (int) round($origW * $ratio);
        $newH = (int) round($origH * $ratio);

        $source = match ($info['mime']) {
            'image/jpeg' => @imagecreatefromjpeg($sourcePath),
            'image/png' => @imagecreatefrompng($sourcePath),
            'image/gif' => @imagecreatefromgif($sourcePath),
            'image/webp' => @imagecreatefromwebp($sourcePath),
            default => false,
        };

        if ($source === false) {
            return;
        }

        $thumb = imagecreatetruecolor($newW, $newH);
        if ($thumb === false) {
            imagedestroy($source);
            return;
        }

        // PNG/WebP の透明度を保持
        if (in_array($info['mime'], ['image/png', 'image/webp'], true)) {
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
        }

        imagecopyresampled($thumb, $source, 0, 0, 0, 0, $newW, $newH, $origW, $origH);

        $dir = dirname($sourcePath);
        $base = basename($sourcePath);
        $thumbDir = $dir . '/thumb';
        if (!is_dir($thumbDir)) {
            mkdir($thumbDir, 0755, true);
        }

        $thumbPath = $thumbDir . '/' . $base;

        match ($info['mime']) {
            'image/jpeg' => imagejpeg($thumb, $thumbPath, $quality),
            'image/png' => imagepng($thumb, $thumbPath, (int) round(9 * (1 - $quality / 100))),
            'image/gif' => imagegif($thumb, $thumbPath),
            'image/webp' => imagewebp($thumb, $thumbPath, $quality),
            default => null,
        };

        imagedestroy($source);
        imagedestroy($thumb);
    }
}

// ─────────────────────────────────────────────
// Git サービス
// ─────────────────────────────────────────────

final class GitService implements GitServiceInterface
{
    private string $dataDir;
    private string $repoDir;
    private GitCommand $git;

    public function __construct(string $dataDir, string $repoDir)
    {
        $this->dataDir = $dataDir;
        $this->repoDir = $repoDir;
        $this->git = new GitCommand($repoDir);
    }

    public function configure(array $config): void
    {
        $current = $this->getConfig();
        $merged = array_merge($current, $config);

        $path = $this->dataDir . '/settings/git.json';
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($path, json_encode($merged, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);

        // Git ユーザー設定を適用
        if (isset($config['userName'])) {
            $this->git->execute('config', ['user.name', $config['userName']]);
        }
        if (isset($config['userEmail'])) {
            $this->git->execute('config', ['user.email', $config['userEmail']]);
        }
    }

    public function getConfig(): array
    {
        $path = $this->dataDir . '/settings/git.json';
        $default = [
            'remoteUrl' => '',
            'branch' => 'main',
            'userName' => '',
            'userEmail' => '',
        ];

        if (!file_exists($path)) {
            return $default;
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            return $default;
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return $default;
        }

        return array_merge($default, $data);
    }

    public function testConnection(): array
    {
        $config = $this->getConfig();
        if (empty($config['remoteUrl'])) {
            return ['reachable' => false, 'error' => 'No remote URL configured'];
        }

        $result = $this->git->execute('ls-remote', ['--exit-code', $config['remoteUrl']]);
        if ($result['exitCode'] !== 0) {
            return ['reachable' => false, 'error' => $result['stderr'] ?: 'Connection failed'];
        }

        return ['reachable' => true];
    }

    public function pull(): array
    {
        $config = $this->getConfig();
        $branch = $config['branch'] ?: 'main';

        $result = $this->git->execute('pull', ['origin', $branch]);
        if ($result['exitCode'] !== 0) {
            return ['success' => false, 'message' => $result['stderr'] ?: 'Pull failed'];
        }

        return [
            'success' => true,
            'message' => $result['stdout'] ?: 'Already up to date.',
        ];
    }

    public function push(): array
    {
        $config = $this->getConfig();
        $branch = $config['branch'] ?: 'main';

        $result = $this->git->execute('push', ['origin', $branch]);
        if ($result['exitCode'] !== 0) {
            return ['success' => false, 'message' => $result['stderr'] ?: 'Push failed'];
        }

        return [
            'success' => true,
            'message' => $result['stdout'] ?: 'Push completed.',
        ];
    }

    public function log(int $limit = 20): array
    {
        $format = '--format=%H|%s|%an|%aI';
        $result = $this->git->execute('log', [$format, '-n', (string) $limit]);

        if ($result['exitCode'] !== 0) {
            return ['commits' => []];
        }

        $commits = [];
        $lines = array_filter(explode("\n", $result['stdout']));
        foreach ($lines as $line) {
            $parts = explode('|', $line, 4);
            if (count($parts) === 4) {
                $commits[] = [
                    'hash' => $parts[0],
                    'message' => $parts[1],
                    'author' => $parts[2],
                    'date' => $parts[3],
                ];
            }
        }

        return ['commits' => $commits];
    }

    public function status(): array
    {
        $result = $this->git->execute('status', ['--porcelain']);

        if ($result['exitCode'] !== 0) {
            return ['clean' => true, 'changes' => []];
        }

        $changes = [];
        $lines = array_filter(explode("\n", $result['stdout']));
        foreach ($lines as $line) {
            $status = trim(substr($line, 0, 2));
            $file = trim(substr($line, 3));
            $changes[] = ['file' => $file, 'status' => $status];
        }

        return [
            'clean' => empty($changes),
            'changes' => $changes,
        ];
    }
}

// ─────────────────────────────────────────────
// ユーザー管理サービス（管理画面用）
// ─────────────────────────────────────────────

final class UserManager
{
    private string $dataDir;

    public function __construct(string $dataDir)
    {
        $this->dataDir = $dataDir;
    }

    /** 全ユーザーを取得する（パスワードハッシュ除外） */
    public function listUsers(): array
    {
        $users = $this->loadUsers();
        return array_map(fn(array $u) => [
            'id' => $u['id'],
            'username' => $u['username'],
            'role' => $u['role'] ?? 'admin',
        ], $users);
    }

    /** ユーザーを追加する */
    public function addUser(string $username, string $password, string $role = 'admin'): array
    {
        if ($username === '' || $password === '') {
            return ['success' => false, 'error' => 'Username and password required'];
        }

        $users = $this->loadUsers();

        foreach ($users as $user) {
            if ($user['username'] === $username) {
                return ['success' => false, 'error' => 'Username already exists'];
            }
        }

        $users[] = [
            'id' => Token::generate(16),
            'username' => $username,
            'password' => Token::sha256($password),
            'role' => in_array($role, ['admin', 'editor', 'viewer'], true) ? $role : 'admin',
        ];

        $this->saveUsers($users);
        return ['success' => true];
    }

    /** ユーザーを削除する */
    public function deleteUser(string $id): array
    {
        $users = $this->loadUsers();
        $filtered = array_values(array_filter($users, fn(array $u) => $u['id'] !== $id));

        if (count($filtered) === count($users)) {
            return ['success' => false, 'error' => 'User not found'];
        }

        if (count($filtered) === 0) {
            return ['success' => false, 'error' => 'Cannot delete the last user'];
        }

        $this->saveUsers($filtered);
        return ['success' => true];
    }

    /** パスワードを変更する */
    public function changePassword(string $id, string $newPassword): array
    {
        if ($newPassword === '') {
            return ['success' => false, 'error' => 'Password required'];
        }

        $users = $this->loadUsers();
        $found = false;

        foreach ($users as &$user) {
            if ($user['id'] === $id) {
                $user['password'] = Token::sha256($newPassword);
                $found = true;
                break;
            }
        }
        unset($user);

        if (!$found) {
            return ['success' => false, 'error' => 'User not found'];
        }

        $this->saveUsers($users);
        return ['success' => true];
    }

    /** ロールを変更する */
    public function changeRole(string $id, string $role): array
    {
        if (!in_array($role, ['admin', 'editor', 'viewer'], true)) {
            return ['success' => false, 'error' => 'Invalid role'];
        }

        $users = $this->loadUsers();
        $found = false;

        foreach ($users as &$user) {
            if ($user['id'] === $id) {
                $user['role'] = $role;
                $found = true;
                break;
            }
        }
        unset($user);

        if (!$found) {
            return ['success' => false, 'error' => 'User not found'];
        }

        $this->saveUsers($users);
        return ['success' => true];
    }

    private function loadUsers(): array
    {
        $path = $this->usersPath();
        if (!file_exists($path)) {
            return [];
        }
        $data = json_decode(file_get_contents($path) ?: '[]', true);
        return is_array($data) ? $data : [];
    }

    private function saveUsers(array $users): void
    {
        $dir = dirname($this->usersPath());
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents(
            $this->usersPath(),
            json_encode($users, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }

    private function usersPath(): string
    {
        return $this->dataDir . '/settings/users.json';
    }
}

// ─────────────────────────────────────────────
// セッション管理サービス（管理画面用）
// ─────────────────────────────────────────────

final class SessionAdmin
{
    private SessionManager $sessions;
    private CsrfManager $csrf;

    public function __construct(SessionManager $sessions, CsrfManager $csrf)
    {
        $this->sessions = $sessions;
        $this->csrf = $csrf;
    }

    /** アクティブセッション一覧 */
    public function listSessions(): array
    {
        return $this->sessions->listAll();
    }

    /** 特定セッションを強制ログアウト */
    public function forceLogout(string $sessionId): void
    {
        $this->sessions->destroy($sessionId);
    }

    /** 全セッションを強制ログアウト */
    public function forceLogoutAll(): int
    {
        return $this->sessions->destroyAll();
    }

    /** 期限切れセッションを一括削除 */
    public function purgeExpiredSessions(): int
    {
        return $this->sessions->purgeExpired();
    }

    /** 期限切れCSRFトークンを一括削除 */
    public function purgeExpiredCsrf(): int
    {
        return $this->csrf->purgeExpired();
    }
}

// ─────────────────────────────────────────────
// サーバ診断サービス（管理画面用）
// ─────────────────────────────────────────────

final class ServerDiagnostics
{
    private string $dataDir;
    private string $repoDir;

    public function __construct(string $dataDir, string $repoDir)
    {
        $this->dataDir = $dataDir;
        $this->repoDir = $repoDir;
    }

    /** サーバ情報を取得する */
    public function getServerInfo(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'php_sapi' => PHP_SAPI,
            'os' => PHP_OS_FAMILY . ' ' . php_uname('r'),
            'hostname' => gethostname() ?: 'unknown',
            'server_time' => gmdate('Y-m-d\TH:i:s\Z'),
            'timezone' => date_default_timezone_get(),
            'memory_limit' => ini_get('memory_limit') ?: 'unknown',
            'max_upload' => ini_get('upload_max_filesize') ?: 'unknown',
            'post_max_size' => ini_get('post_max_size') ?: 'unknown',
            'max_execution_time' => ini_get('max_execution_time') ?: 'unknown',
            'extensions' => get_loaded_extensions(),
        ];
    }

    /** ストレージ使用量を取得する */
    public function getStorageStats(): array
    {
        $dirs = ['settings', 'content', 'collections', 'uploads', 'cache', 'backups', 'logs'];
        $stats = [];

        foreach ($dirs as $dir) {
            $path = $this->dataDir . '/' . $dir;
            if (!is_dir($path)) {
                $stats[$dir] = ['files' => 0, 'size' => 0, 'sizeHuman' => '0 B'];
                continue;
            }

            $files = 0;
            $size = 0;
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $files++;
                    $size += $file->getSize();
                }
            }

            $stats[$dir] = [
                'files' => $files,
                'size' => $size,
                'sizeHuman' => self::humanSize($size),
            ];
        }

        // ディスク空き容量
        $free = disk_free_space($this->dataDir);
        $total = disk_total_space($this->dataDir);
        $stats['_disk'] = [
            'free' => $free !== false ? self::humanSize((int) $free) : 'unknown',
            'total' => $total !== false ? self::humanSize((int) $total) : 'unknown',
        ];

        return $stats;
    }

    /** PHP拡張機能の状態を確認する */
    public function checkRequirements(): array
    {
        $checks = [];

        $required = ['json', 'mbstring', 'gd'];
        foreach ($required as $ext) {
            $checks[$ext] = [
                'required' => true,
                'loaded' => extension_loaded($ext),
            ];
        }

        $optional = ['openssl', 'curl', 'zip', 'intl'];
        foreach ($optional as $ext) {
            $checks[$ext] = [
                'required' => false,
                'loaded' => extension_loaded($ext),
            ];
        }

        return $checks;
    }

    public static function humanSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $size = (float) $bytes;
        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }
        return round($size, 1) . ' ' . $units[$i];
    }
}
