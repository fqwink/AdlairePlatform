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
        return in_array($dir, [
            'settings',
            'content',
            'collections',
            'uploads',
            'cache',
            'backups',
            'logs',
        ], true);
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
        file_put_contents($path, json_encode($session, JSON_UNESCAPED_UNICODE), LOCK_EX);

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

        foreach ($files as $file) {
            $raw = file_get_contents($file);
            if ($raw === false) {
                continue;
            }
            $session = json_decode($raw, true);
            if (!is_array($session) || ($session['expiresTimestamp'] ?? 0) < time()) {
                unlink($file);
                $count++;
            }
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
        file_put_contents($path, (string) (time() + $this->ttl), LOCK_EX);
        return $token;
    }

    /** CSRFトークンを検証する（検証後削除：使い捨て） */
    public function verify(string $token): bool
    {
        $path = $this->tokenPath($token);
        if (!file_exists($path)) {
            return false;
        }

        $expiresAt = (int) file_get_contents($path);
        unlink($path);

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

final class GitCommand
{
    private string $workDir;

    public function __construct(string $workDir)
    {
        $this->workDir = $workDir;
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

        $stdout = stream_get_contents($pipes[1]) ?: '';
        $stderr = stream_get_contents($pipes[2]) ?: '';
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
