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
            if (($user['username'] ?? '') === $username && ($user['password'] ?? '') === $passwordHash) {
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
            file_put_contents($path, $encoded, LOCK_EX);
        } else {
            file_put_contents($path, $data, LOCK_EX);
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

        $maxWidth = $options['maxWidth'] ?? 300;
        $maxHeight = $options['maxHeight'] ?? 300;
        $quality = $options['quality'] ?? 80;

        [$origW, $origH] = $info;
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
