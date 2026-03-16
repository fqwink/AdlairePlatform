<?php

declare(strict_types=1);

/**
 * ASS.Interface.php — Adlaire Server System / Engine 4: Interface
 *
 * ACS（クライアントSDK）との契約に基づくインターフェース定義。
 * ASS は認証・データストレージ・Git操作のみを担当する。
 */

namespace ASS\Interface;

// ─────────────────────────────────────────────
// 認証インターフェース
// ─────────────────────────────────────────────

interface AuthServiceInterface
{
    /**
     * ユーザー認証を実行する。
     *
     * @param string $username
     * @param string $password
     * @return array{authenticated: bool, user: ?array{id: string, username: string, role: string}, token: ?string, error: ?string}
     */
    public function authenticate(string $username, string $password): array;

    /**
     * セッションを破棄してログアウトする。
     */
    public function logout(string $sessionId): void;

    /**
     * セッション情報を取得する。
     *
     * @return array{id: string, userId: string, role: string, createdAt: string, expiresAt: string, data: array}|null
     */
    public function getSession(string $sessionId): ?array;

    /**
     * トークンを検証する。
     *
     * @return array{authenticated: bool, user: ?array{id: string, username: string, role: string}}
     */
    public function verifyToken(string $token): array;

    /**
     * CSRFトークンを生成する。
     */
    public function generateCsrfToken(): string;

    /**
     * CSRFトークンを検証する。
     */
    public function verifyCsrfToken(string $token): bool;
}

// ─────────────────────────────────────────────
// データストレージインターフェース
// ─────────────────────────────────────────────

interface StorageServiceInterface
{
    /**
     * JSONファイルを読み込む。
     *
     * @param string $file ファイル名
     * @param string $dir ディレクトリ（settings|content|collections|uploads|cache|backups|logs）
     * @return mixed デコード済みJSONデータ
     * @throws \RuntimeException ファイルが存在しない場合
     */
    public function read(string $file, string $dir = 'settings'): mixed;

    /**
     * JSONファイルに書き込む。
     *
     * @param string $file ファイル名
     * @param mixed $data JSONシリアライズ可能なデータ
     * @param string $dir ディレクトリ
     */
    public function write(string $file, mixed $data, string $dir = 'settings'): void;

    /**
     * ファイルを削除する。
     *
     * @param string $file ファイル名
     * @param string $dir ディレクトリ
     */
    public function delete(string $file, string $dir = 'settings'): void;

    /**
     * ファイルの存在を確認する。
     */
    public function exists(string $file, string $dir = 'settings'): bool;

    /**
     * ディレクトリ内のファイル一覧を取得する。
     *
     * @param string $dir ディレクトリ
     * @param string|null $ext 拡張子フィルタ（例: "json", "md"）
     * @return string[] ファイル名の配列
     */
    public function list(string $dir, ?string $ext = null): array;
}

// ─────────────────────────────────────────────
// ファイルサービスインターフェース
// ─────────────────────────────────────────────

interface FileServiceInterface
{
    /**
     * バイナリファイルをアップロードする。
     *
     * @param array $file $_FILES エントリ
     * @param string $path 保存先パス
     * @return array{success: bool, path: string, size: int, error?: string}
     */
    public function upload(array $file, string $path): array;

    /**
     * 画像をアップロード・最適化する。
     *
     * @param array $file $_FILES エントリ
     * @param string $path 保存先パス
     * @param array{maxWidth?: int, maxHeight?: int, quality?: int, generateThumbnail?: bool, generateWebP?: bool} $options
     * @return array{success: bool, path: string, size: int, error?: string}
     */
    public function uploadImage(array $file, string $path, array $options = []): array;

    /**
     * ファイルのパスを解決して返す（ダウンロード用）。
     *
     * @return string|null 実ファイルパス。存在しない場合null
     */
    public function resolve(string $path): ?string;

    /**
     * ファイルを削除する。
     */
    public function deleteFile(string $path): void;

    /**
     * ファイルの存在を確認する。
     */
    public function fileExists(string $path): bool;

    /**
     * 画像のメタデータを取得する。
     *
     * @return array{width: int, height: int, mime: string, size: int, aspect: float}|null
     */
    public function getImageInfo(string $path): ?array;
}

// ─────────────────────────────────────────────
// Git操作インターフェース
// ─────────────────────────────────────────────

interface GitServiceInterface
{
    /**
     * Git設定を保存する。
     *
     * @param array{remoteUrl?: string, branch?: string, userName?: string, userEmail?: string} $config
     */
    public function configure(array $config): void;

    /**
     * Git設定を取得する。
     *
     * @return array{remoteUrl: string, branch: string, userName: string, userEmail: string}
     */
    public function getConfig(): array;

    /**
     * リモートへの接続をテストする。
     *
     * @return array{reachable: bool, error?: string}
     */
    public function testConnection(): array;

    /**
     * リモートからプルする。
     *
     * @return array{success: bool, message: string, changes?: int}
     */
    public function pull(): array;

    /**
     * リモートへプッシュする。
     *
     * @return array{success: bool, message: string}
     */
    public function push(): array;

    /**
     * コミットログを取得する。
     *
     * @param int $limit 取得件数
     * @return array{commits: array<array{hash: string, message: string, author: string, date: string}>}
     */
    public function log(int $limit = 20): array;

    /**
     * ワーキングツリーの状態を取得する。
     *
     * @return array{clean: bool, changes: array<array{file: string, status: string}>}
     */
    public function status(): array;
}

// ─────────────────────────────────────────────
// リクエスト/レスポンスインターフェース
// ─────────────────────────────────────────────

interface RequestInterface
{
    public function getMethod(): string;

    public function getPath(): string;

    public function getQuery(string $key, mixed $default = null): mixed;

    public function getBody(): array;

    public function getHeader(string $name): ?string;

    public function getCookie(string $name): ?string;

    public function getFile(string $name): ?array;
}

interface ResponseInterface
{
    /**
     * @param array{ok: bool, data?: mixed, error?: string, errors?: array<string, string[]>} $body
     */
    public function json(array $body, int $status = 200): void;

    public function file(string $path, string $contentType): void;

    public function setCookie(string $name, string $value, array $options = []): static;

    public function clearCookie(string $name): static;

    public function setHeader(string $name, string $value): static;
}

// ─────────────────────────────────────────────
// ルーターインターフェース
// ─────────────────────────────────────────────

interface RouterInterface
{
    public function get(string $path, callable $handler): static;

    public function post(string $path, callable $handler): static;

    public function dispatch(RequestInterface $request, ResponseInterface $response): void;
}
