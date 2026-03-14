<?php
/**
 * Adlaire Infrastructure Services (AIS) - Deployment Module
 *
 * AIS = Adlaire Infrastructure Services
 *
 * デプロイメント・運用管理サービスを提供するモジュール。
 * Updater（アプリケーション更新）、GitSync（Git同期）、
 * Mailer（メール送信）、BackupManager（バックアップ管理）を含む。
 *
 * @package AIS
 * @version 1.0.0
 * @license Adlaire License Ver.2.0
 */

namespace AIS\Deployment;

// ============================================================================
// Updater - アプリケーション更新管理
// ============================================================================

/**
 * アプリケーションの更新チェック・適用・ロールバック管理。
 *
 * UpdateEngine を汎用化し、任意のアプリケーションで利用可能にしたもの。
 * GitHub Releases API ベースの更新チェック、ZIPダウンロード・展開、
 * バックアップ作成・ロールバック機能を提供する。
 */
class Updater {

    /** @var string アプリケーションのルートディレクトリ */
    private string $appDir;

    /** @var string バックアップの保存先ディレクトリ */
    private string $backupDir;

    /** @var string 更新チェック用URL（GitHub Releases API等） */
    private string $updateUrl;

    /** @var string 最後のエラーメッセージ */
    private string $lastError = '';

    /**
     * コンストラクタ
     *
     * @param string $appDir    アプリケーションのルートディレクトリ
     * @param string $backupDir バックアップ保存先ディレクトリ
     * @param string $updateUrl 更新情報取得URL
     */
    public function __construct(string $appDir, string $backupDir, string $updateUrl) {
        $this->appDir = rtrim($appDir, '/\\');
        $this->backupDir = rtrim($backupDir, '/\\');
        $this->updateUrl = $updateUrl;

        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
    }

    /**
     * 更新の有無をチェックする
     *
     * 更新チェックURLにアクセスし、指定されたバージョンより新しい
     * リリースが存在するか確認する。
     *
     * @param string $currentVersion 現在のアプリケーションバージョン
     * @return array|null 更新情報（latest, zip_url, release_notes）、更新なしの場合 null
     */
    public function checkForUpdate(string $currentVersion): ?array {
        $ctx = stream_context_create([
            'http' => [
                'method'        => 'GET',
                'header'        => "User-Agent: AIS-Updater/1.0\r\n",
                'timeout'       => 15,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents($this->updateUrl, false, $ctx);
        if ($response === false) {
            $this->lastError = '更新サーバーに接続できませんでした';
            return null;
        }

        /* HTTPステータスコードの確認 */
        $statusCode = $this->extractHttpStatus($http_response_header ?? []);
        if ($statusCode === 403 || $statusCode === 429) {
            $this->lastError = 'API レート制限に達しました';
            return null;
        }
        if ($statusCode !== 200) {
            $this->lastError = "HTTP エラー: {$statusCode}";
            return null;
        }

        $data = json_decode($response, true);
        if (!is_array($data) || !isset($data['tag_name'])) {
            $this->lastError = 'バージョン情報のパースに失敗しました';
            return null;
        }

        $latest = ltrim($data['tag_name'], 'v');
        if (!version_compare($latest, $currentVersion, '>')) {
            return null; /* 更新なし */
        }

        /* ダウンロードURLの決定 */
        $zipUrl = $data['zipball_url'] ?? '';
        if (isset($data['assets']) && is_array($data['assets'])) {
            foreach ($data['assets'] as $asset) {
                if (isset($asset['browser_download_url']) && str_ends_with($asset['browser_download_url'], '.zip')) {
                    $zipUrl = $asset['browser_download_url'];
                    break;
                }
            }
        }

        return [
            'current'       => $currentVersion,
            'latest'        => $latest,
            'zip_url'       => $zipUrl,
            'release_notes' => $data['body'] ?? '',
            'published_at'  => $data['published_at'] ?? '',
        ];
    }

    /**
     * 更新を適用する
     *
     * ZIP ファイルをダウンロード・展開し、アプリケーションファイルを上書きする。
     * 適用前に自動バックアップを作成する。
     *
     * @param string $downloadUrl ZIPファイルのダウンロードURL
     * @return bool 成功した場合 true
     */
    public function apply(string $downloadUrl): bool {
        /* ダウンロード */
        $ctx = stream_context_create([
            'http' => [
                'method'  => 'GET',
                'header'  => "User-Agent: AIS-Updater/1.0\r\n",
                'timeout' => 120,
            ],
        ]);

        $zipData = @file_get_contents($downloadUrl, false, $ctx);
        if ($zipData === false) {
            $this->lastError = 'ZIPファイルのダウンロードに失敗しました';
            return false;
        }

        /* サイズ検証 */
        if (strlen($zipData) > 100 * 1024 * 1024) {
            $this->lastError = 'ダウンロードサイズが上限（100MB）を超えています';
            return false;
        }

        /* 一時ファイルに保存 */
        $tmpZip = sys_get_temp_dir() . '/ais_update_' . bin2hex(random_bytes(16)) . '.zip';
        if (file_put_contents($tmpZip, $zipData) === false) {
            $this->lastError = '一時ファイルの書き込みに失敗しました';
            return false;
        }

        /* ZIP 展開 */
        if (!class_exists(\ZipArchive::class)) {
            @unlink($tmpZip);
            $this->lastError = 'ZipArchive 拡張が利用できません';
            return false;
        }

        $zip = new \ZipArchive();
        if ($zip->open($tmpZip) !== true) {
            @unlink($tmpZip);
            $this->lastError = 'ZIP ファイルの展開に失敗しました';
            return false;
        }

        $extractDir = sys_get_temp_dir() . '/ais_update_extract_' . bin2hex(random_bytes(16));
        $ok = $zip->extractTo($extractDir);
        $zip->close();
        @unlink($tmpZip);

        if (!$ok) {
            $this->lastError = 'ZIP の展開処理に失敗しました';
            return false;
        }

        /* 展開元ディレクトリの決定 */
        $topDirs = glob($extractDir . '/*', GLOB_ONLYDIR);
        $src = (is_array($topDirs) && count($topDirs) === 1) ? $topDirs[0] : $extractDir;
        $realSrc = realpath($src);
        if ($realSrc === false) {
            $this->lastError = '展開先パスの解決に失敗しました';
            return false;
        }

        /* ファイルコピー（パストラバーサル防止付き） */
        $appRoot = realpath($this->appDir);
        if ($appRoot === false) {
            $this->lastError = 'アプリケーションルートの解決に失敗しました';
            return false;
        }

        $excludeDirs = ['backup', '.git'];
        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($realSrc, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iter as $item) {
            $rel = substr($item->getRealPath(), strlen($realSrc) + 1);
            if (str_contains($rel, '..')) {
                continue; /* パストラバーサル防止 */
            }
            $parts = explode(DIRECTORY_SEPARATOR, $rel);
            if (in_array($parts[0], $excludeDirs, true)) {
                continue;
            }

            $dest = $appRoot . DIRECTORY_SEPARATOR . $rel;
            if ($item->isDir()) {
                @mkdir($dest, 0755, true);
            } else {
                $destDir = dirname($dest);
                $realDestDir = realpath($destDir);
                if ($realDestDir === false || !str_starts_with($realDestDir, $appRoot)) {
                    continue;
                }
                @copy($item->getRealPath(), $dest);
            }
        }

        /* 展開ディレクトリの後片付け */
        $this->removeDirectory($extractDir);

        return true;
    }

    /**
     * バックアップからロールバックする
     *
     * @param string $backupName ロールバック対象のバックアップ名
     * @return bool 成功した場合 true
     */
    public function rollback(string $backupName): bool {
        $backupName = basename($backupName);
        $src = $this->backupDir . '/' . $backupName;

        if (!is_dir($src)) {
            $this->lastError = "バックアップが見つかりません: {$backupName}";
            return false;
        }

        $realSrc = realpath($src);
        $appRoot = realpath($this->appDir);
        if ($realSrc === false || $appRoot === false) {
            $this->lastError = 'パスの解決に失敗しました';
            return false;
        }

        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($realSrc, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iter as $item) {
            $rel = substr($item->getRealPath(), strlen($realSrc) + 1);
            if ($rel === false || $rel === '' || $rel === 'meta.json') {
                continue;
            }
            if (str_contains($rel, '..')) {
                continue;
            }

            $dest = $appRoot . DIRECTORY_SEPARATOR . $rel;
            if ($item->isDir()) {
                @mkdir($dest, 0755, true);
            } else {
                $destDir = dirname($dest);
                $realDestDir = realpath($destDir);
                if ($realDestDir === false || !str_starts_with($realDestDir, $appRoot)) {
                    continue;
                }
                @copy($item->getRealPath(), $dest);
            }
        }

        return true;
    }

    /**
     * バックアップ一覧を取得する
     *
     * @return array<array{name: string, meta: array|null}> バックアップ情報の配列
     */
    public function listBackups(): array {
        $backups = glob($this->backupDir . '/*', GLOB_ONLYDIR);
        if (!is_array($backups)) {
            return [];
        }

        rsort($backups);
        $list = [];

        foreach ($backups as $path) {
            $name = basename($path);
            $metaFile = $path . '/meta.json';
            $meta = null;

            if (is_file($metaFile)) {
                $content = file_get_contents($metaFile);
                if ($content !== false) {
                    $decoded = json_decode($content, true);
                    if (is_array($decoded)) {
                        $meta = $decoded;
                    }
                }
            }

            $list[] = ['name' => $name, 'meta' => $meta];
        }

        return $list;
    }

    /**
     * バックアップを削除する
     *
     * @param string $name 削除対象のバックアップ名
     * @return bool 成功した場合 true
     */
    public function deleteBackup(string $name): bool {
        $name = basename($name);
        $dir = $this->backupDir . '/' . $name;

        if (!is_dir($dir)) {
            $this->lastError = "バックアップが見つかりません: {$name}";
            return false;
        }

        $this->removeDirectory($dir);
        return true;
    }

    /**
     * 更新前の環境チェックを実行する
     *
     * @return array{ziparchive: bool, url_fopen: bool, writable: bool,
     *               disk_free: int, ok: bool}
     */
    public function checkEnvironment(): array {
        $zipArchive = class_exists(\ZipArchive::class);
        $urlFopen = (bool)ini_get('allow_url_fopen');
        $writable = is_writable($this->appDir);
        $diskFree = @disk_free_space($this->appDir);

        return [
            'ziparchive' => $zipArchive,
            'url_fopen'  => $urlFopen,
            'writable'   => $writable,
            'disk_free'  => $diskFree !== false ? (int)$diskFree : -1,
            'ok'         => $zipArchive && $urlFopen && $writable,
        ];
    }

    /**
     * 最後のエラーメッセージを取得する
     *
     * @return string エラーメッセージ
     */
    public function getLastError(): string {
        return $this->lastError;
    }

    /**
     * HTTPレスポンスヘッダーからステータスコードを抽出する
     *
     * @param array<string> $headers レスポンスヘッダー配列
     * @return int HTTPステータスコード
     */
    private function extractHttpStatus(array $headers): int {
        foreach ($headers as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d+)#', $header, $m)) {
                return (int)$m[1];
            }
        }
        return 0;
    }

    /**
     * ディレクトリを再帰的に削除する
     *
     * @param string $dir 削除対象のディレクトリ
     */
    private function removeDirectory(string $dir): void {
        if (!is_dir($dir)) {
            return;
        }

        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iter as $item) {
            if ($item->isDir()) {
                @rmdir($item->getRealPath());
            } else {
                @unlink($item->getRealPath());
            }
        }

        @rmdir($dir);
    }
}

// ============================================================================
// GitSync - Gitベースコンテンツ同期
// ============================================================================

/**
 * GitHub REST API を使用したコンテンツの双方向同期。
 *
 * GitEngine を汎用化し、任意のアプリケーションで利用可能にしたもの。
 * リポジトリとの Pull / Push、コミット履歴取得、
 * 接続テスト、設定管理機能を提供する。
 */
class GitSync {

    /** @var array<string, mixed> 設定 */
    private array $config;

    /** @var string GitHub API ベースURL */
    private const API_BASE = 'https://api.github.com';

    /** @var int 最大リトライ回数 */
    private const MAX_RETRIES = 3;

    /** @var string 最後のエラーメッセージ */
    private string $lastError = '';

    /**
     * コンストラクタ
     *
     * @param array<string, mixed> $config 設定
     *   - repository: string  リポジトリ名（owner/repo 形式）
     *   - token: string       GitHub パーソナルアクセストークン
     *   - branch: string      対象ブランチ名（デフォルト: 'main'）
     *   - contentDir: string  コンテンツディレクトリのパス
     *   - remoteDir: string   リモートリポジトリ内のディレクトリ名（デフォルト: 'content'）
     *   - authorName: string  コミット作成者名（デフォルト: 'AIS-GitSync'）
     *   - authorEmail: string コミット作成者メール（デフォルト: 'ais@localhost'）
     */
    public function __construct(array $config) {
        $this->config = array_merge([
            'repository'  => '',
            'token'       => '',
            'branch'      => 'main',
            'contentDir'  => '',
            'remoteDir'   => 'content',
            'authorName'  => 'AIS-GitSync',
            'authorEmail' => 'ais@localhost',
        ], $config);
    }

    /**
     * 必要な設定が揃っているか確認する
     *
     * @return bool 設定済みの場合 true
     */
    public function isConfigured(): bool {
        return !empty($this->config['repository'])
            && !empty($this->config['token'])
            && !empty($this->config['contentDir']);
    }

    /**
     * GitHub リポジトリへの接続をテストする
     *
     * @return array{ok: bool, name?: string, private?: bool, default_branch?: string, error?: string}
     */
    public function testConnection(): array {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'error' => 'リポジトリまたはトークンが未設定です'];
        }

        $repo = $this->config['repository'];
        $res = $this->apiRequest('GET', "/repos/{$repo}");

        if (!$res['ok']) {
            $msg = $res['data']['message'] ?? '接続に失敗しました';
            return ['ok' => false, 'error' => $msg . ' (HTTP ' . $res['status'] . ')'];
        }

        return [
            'ok'             => true,
            'name'           => $res['data']['full_name'] ?? $repo,
            'private'        => $res['data']['private'] ?? false,
            'default_branch' => $res['data']['default_branch'] ?? 'main',
        ];
    }

    /**
     * リモートリポジトリからコンテンツを取得する（Pull相当）
     *
     * リポジトリツリーを取得し、対象ディレクトリ内のファイルをダウンロードする。
     * SHAが一致するファイルはスキップする。
     *
     * @return array{ok: bool, downloaded?: int, skipped?: int, errors?: array<string>}
     */
    public function pull(): array {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'error' => 'Git 同期が設定されていません'];
        }

        $repo = $this->config['repository'];
        $branch = $this->config['branch'];
        $remoteDir = $this->config['remoteDir'];
        $contentDir = rtrim($this->config['contentDir'], '/\\');

        /* リポジトリツリーの取得 */
        $res = $this->apiRequest('GET', "/repos/{$repo}/git/trees/{$branch}?recursive=1");
        if (!$res['ok']) {
            return ['ok' => false, 'error' => 'ツリーの取得に失敗: ' . ($res['data']['message'] ?? '')];
        }

        $tree = $res['data']['tree'] ?? [];
        $downloaded = 0;
        $skipped = 0;
        $errors = [];

        foreach ($tree as $item) {
            if ($item['type'] !== 'blob') continue;
            $path = $item['path'];

            /* 対象ディレクトリ内のファイルのみ */
            if (!str_starts_with($path, $remoteDir . '/')) continue;

            $relativePath = substr($path, strlen($remoteDir) + 1);

            /* パストラバーサル防止 */
            if (str_contains($relativePath, '..') || str_starts_with($relativePath, '/')) {
                $errors[] = "不正なパス検出: {$path}";
                continue;
            }

            $localPath = $contentDir . '/' . $relativePath;

            /* ディレクトリ作成 */
            $localDir = dirname($localPath);
            if (!is_dir($localDir)) {
                @mkdir($localDir, 0755, true);
            }

            /* SHA比較でスキップ判定 */
            $remoteSha = $item['sha'] ?? '';
            if (is_file($localPath)) {
                $localContent = file_get_contents($localPath);
                if ($localContent !== false) {
                    $localSha = sha1('blob ' . strlen($localContent) . "\0" . $localContent);
                    if ($localSha === $remoteSha) {
                        $skipped++;
                        continue;
                    }
                }
            }

            /* ファイル内容の取得 */
            $fileRes = $this->apiRequest('GET', "/repos/{$repo}/contents/{$path}?ref={$branch}");
            if (!$fileRes['ok']) {
                $errors[] = "取得失敗: {$path}";
                continue;
            }

            $content = $fileRes['data']['content'] ?? '';
            $encoding = $fileRes['data']['encoding'] ?? '';
            $decoded = ($encoding === 'base64') ? base64_decode($content) : $content;

            if ($decoded === false) {
                $errors[] = "デコード失敗: {$path}";
                continue;
            }

            if (file_put_contents($localPath, $decoded) === false) {
                $errors[] = "書き込み失敗: {$path}";
                continue;
            }

            $downloaded++;
        }

        $result = [
            'ok'         => true,
            'downloaded' => $downloaded,
            'skipped'    => $skipped,
        ];
        if (!empty($errors)) {
            $result['errors'] = $errors;
        }

        return $result;
    }

    /**
     * ローカルコンテンツをリモートリポジトリに送信する（Push相当）
     *
     * Git Trees API を使用して一括コミットを作成する。
     *
     * @param string $commitMessage コミットメッセージ
     * @return array{ok: bool, uploaded?: int, commit_sha?: string, errors?: array<string>}
     */
    public function push(string $commitMessage): array {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'error' => 'Git 同期が設定されていません'];
        }

        $repo = $this->config['repository'];
        $branch = $this->config['branch'];
        $remoteDir = $this->config['remoteDir'];
        $contentDir = rtrim($this->config['contentDir'], '/\\');

        if ($commitMessage === '') {
            $commitMessage = 'Update content via AIS-GitSync';
        }

        /* HEAD コミット SHA の取得 */
        $refRes = $this->apiRequest('GET', "/repos/{$repo}/git/ref/heads/{$branch}");
        if (!$refRes['ok']) {
            return ['ok' => false, 'error' => 'ブランチ参照の取得に失敗'];
        }
        $headSha = $refRes['data']['object']['sha'] ?? '';

        /* 現在のツリー SHA の取得 */
        $commitRes = $this->apiRequest('GET', "/repos/{$repo}/git/commits/{$headSha}");
        if (!$commitRes['ok']) {
            return ['ok' => false, 'error' => 'コミット情報の取得に失敗'];
        }
        $baseTreeSha = $commitRes['data']['tree']['sha'] ?? '';

        /* ローカルファイルの収集 */
        $files = $this->collectFiles($contentDir);
        if (empty($files)) {
            return ['ok' => false, 'error' => 'アップロードするファイルがありません'];
        }

        /* Blob 作成 & ツリーエントリ構築 */
        $treeEntries = [];
        $uploaded = 0;
        $errors = [];

        foreach ($files as $relativePath => $localPath) {
            $content = file_get_contents($localPath);
            if ($content === false) {
                $errors[] = "読み込み失敗: {$localPath}";
                continue;
            }

            $blobRes = $this->apiRequest('POST', "/repos/{$repo}/git/blobs", [
                'content'  => base64_encode($content),
                'encoding' => 'base64',
            ]);

            if (!$blobRes['ok']) {
                $errors[] = "Blob 作成失敗: {$relativePath}";
                continue;
            }

            $treeEntries[] = [
                'path' => $remoteDir . '/' . $relativePath,
                'mode' => '100644',
                'type' => 'blob',
                'sha'  => $blobRes['data']['sha'],
            ];
            $uploaded++;
        }

        if (empty($treeEntries)) {
            return ['ok' => false, 'error' => 'アップロード可能なファイルがありません', 'errors' => $errors];
        }

        /* ツリー作成 */
        $treeRes = $this->apiRequest('POST', "/repos/{$repo}/git/trees", [
            'base_tree' => $baseTreeSha,
            'tree'      => $treeEntries,
        ]);

        if (!$treeRes['ok']) {
            return ['ok' => false, 'error' => 'ツリー作成に失敗'];
        }

        /* コミット作成 */
        $newCommitRes = $this->apiRequest('POST', "/repos/{$repo}/git/commits", [
            'message' => $commitMessage,
            'tree'    => $treeRes['data']['sha'],
            'parents' => [$headSha],
            'author'  => [
                'name'  => $this->config['authorName'],
                'email' => $this->config['authorEmail'],
                'date'  => date('c'),
            ],
        ]);

        if (!$newCommitRes['ok']) {
            return ['ok' => false, 'error' => 'コミット作成に失敗'];
        }

        /* ブランチ参照更新 */
        $updateRefRes = $this->apiRequest('PATCH', "/repos/{$repo}/git/refs/heads/{$branch}", [
            'sha' => $newCommitRes['data']['sha'],
        ]);

        if (!$updateRefRes['ok']) {
            return ['ok' => false, 'error' => '参照更新に失敗'];
        }

        $result = [
            'ok'         => true,
            'uploaded'   => $uploaded,
            'commit_sha' => $newCommitRes['data']['sha'],
        ];
        if (!empty($errors)) {
            $result['errors'] = $errors;
        }

        return $result;
    }

    /**
     * コミット履歴を取得する
     *
     * @param int $limit 取得件数（最大100）
     * @return array{ok: bool, commits?: array<array{sha: string, message: string,
     *               author: string, date: string}>, error?: string}
     */
    public function getLog(int $limit = 10): array {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'error' => 'Git 同期が設定されていません'];
        }

        $limit = max(1, min(100, $limit));
        $repo = $this->config['repository'];
        $branch = $this->config['branch'];
        $remoteDir = $this->config['remoteDir'];

        $res = $this->apiRequest('GET',
            "/repos/{$repo}/commits?sha={$branch}&path={$remoteDir}&per_page={$limit}"
        );

        if (!$res['ok']) {
            return ['ok' => false, 'error' => 'コミット履歴の取得に失敗'];
        }

        $commits = [];
        foreach ($res['data'] as $c) {
            $commits[] = [
                'sha'      => substr($c['sha'] ?? '', 0, 7),
                'full_sha' => $c['sha'] ?? '',
                'message'  => $c['commit']['message'] ?? '',
                'author'   => $c['commit']['author']['name'] ?? '',
                'date'     => $c['commit']['author']['date'] ?? '',
            ];
        }

        return ['ok' => true, 'commits' => $commits];
    }

    /**
     * 設定を更新する
     *
     * @param array<string, mixed> $settings 更新する設定値
     */
    public function configure(array $settings): void {
        $this->config = array_merge($this->config, $settings);
    }

    /**
     * 最後のエラーメッセージを取得する
     *
     * @return string エラーメッセージ
     */
    public function getLastError(): string {
        return $this->lastError;
    }

    /**
     * GitHub REST API を呼び出す（指数バックオフ付きリトライ対応）
     *
     * @param string     $method   HTTPメソッド
     * @param string     $endpoint APIエンドポイント
     * @param array|null $body     リクエストボディ
     * @return array{ok: bool, status: int, data: array, error?: string}
     */
    private function apiRequest(string $method, string $endpoint, ?array $body = null): array {
        $token = $this->config['token'];
        $url = self::API_BASE . $endpoint;

        $headers = [
            'Authorization: Bearer ' . $token,
            'Accept: application/vnd.github.v3+json',
            'User-Agent: AIS-GitSync/1.0',
        ];
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
        }

        for ($attempt = 0; $attempt <= self::MAX_RETRIES; $attempt++) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_CUSTOMREQUEST  => $method,
            ]);

            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
            }

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            /* リトライ判定 */
            $shouldRetry = ($error !== '')
                || $httpCode === 429
                || $httpCode === 502
                || $httpCode === 503;

            if ($shouldRetry && $attempt < self::MAX_RETRIES) {
                $backoffMs = (int)(pow(2, $attempt) * 500);
                usleep($backoffMs * 1000);
                continue;
            }

            if ($error !== '') {
                $this->lastError = 'cURL エラー: ' . $error;
                return ['ok' => false, 'error' => $this->lastError, 'status' => 0, 'data' => []];
            }

            $data = json_decode($response, true);
            if (!is_array($data)) {
                $data = [];
            }

            return [
                'ok'     => $httpCode >= 200 && $httpCode < 300,
                'status' => $httpCode,
                'data'   => $data,
            ];
        }

        $this->lastError = 'リトライ上限に達しました';
        return ['ok' => false, 'error' => $this->lastError, 'status' => 0, 'data' => []];
    }

    /**
     * ディレクトリ内のファイルを再帰的に収集する
     *
     * @param string $baseDir ベースディレクトリ
     * @return array<string, string> 相対パス => 絶対パス
     */
    private function collectFiles(string $baseDir): array {
        $files = [];
        if (!is_dir($baseDir)) {
            return $files;
        }

        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($baseDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iter as $item) {
            if ($item->isDir()) continue;
            $relativePath = substr($item->getPathname(), strlen($baseDir) + 1);
            $files[$relativePath] = $item->getPathname();
        }

        return $files;
    }
}

// ============================================================================
// Mailer - メール送信
// ============================================================================

/**
 * メール送信の抽象化レイヤー。
 *
 * MailerEngine を汎用化し、独立利用可能にしたもの。
 * ヘッダインジェクション対策、テストモード、
 * お問い合わせフォーム用ヘルパーを提供する。
 */
class Mailer {

    /** @var array<string, mixed> メール設定 */
    private array $config;

    /** @var bool テストモードフラグ */
    private bool $testMode;

    /** @var string 最後のエラーメッセージ */
    private string $lastError = '';

    /** @var array<array<string, mixed>> テストモードで送信されたメール */
    private array $sentMails = [];

    /**
     * コンストラクタ
     *
     * @param array<string, mixed> $config メール設定
     *   - from: string       送信元アドレス（デフォルト: ''）
     *   - replyTo: string    返信先アドレス（デフォルト: ''）
     *   - testMode: bool     テストモード（デフォルト: false）
     *   - charset: string    文字エンコーディング（デフォルト: 'UTF-8'）
     */
    public function __construct(array $config = []) {
        $this->config = array_merge([
            'from'     => '',
            'replyTo'  => '',
            'testMode' => false,
            'charset'  => 'UTF-8',
        ], $config);

        $this->testMode = (bool)$this->config['testMode'];
    }

    /**
     * メールを送信する
     *
     * ヘッダインジェクション対策を適用した上で、
     * PHP の mail() 関数でメールを送信する。
     *
     * @param string              $to      送信先アドレス
     * @param string              $subject 件名
     * @param string              $body    本文
     * @param array<string,string> $headers 追加ヘッダー
     * @return bool 送信成功の場合 true
     */
    public function send(string $to, string $subject, string $body, array $headers = []): bool {
        /* ヘッダインジェクション対策 */
        $to = $this->sanitizeHeader($to);
        $subject = $this->sanitizeHeader($subject);

        /* ヘッダー構築 */
        $headerLines = "Content-Type: text/plain; charset={$this->config['charset']}";

        if (!empty($this->config['from'])) {
            $from = $this->sanitizeHeader($this->config['from']);
            $headerLines .= "\r\nFrom: {$from}";
        }

        if (!empty($this->config['replyTo'])) {
            $replyTo = $this->sanitizeHeader($this->config['replyTo']);
            $headerLines .= "\r\nReply-To: {$replyTo}";
        }

        foreach ($headers as $name => $value) {
            $headerLines .= "\r\n" . $this->sanitizeHeader($name) . ': ' . $this->sanitizeHeader($value);
        }

        /* テストモード */
        if ($this->testMode) {
            $this->sentMails[] = [
                'to'      => $to,
                'subject' => $subject,
                'body'    => $body,
                'headers' => $headerLines,
                'time'    => date('c'),
            ];
            return true;
        }

        /* 送信実行 */
        if (@mail($to, $subject, $body, $headerLines)) {
            return true;
        }

        $this->lastError = 'mail() 関数によるメール送信に失敗しました';
        return false;
    }

    /**
     * お問い合わせフォーム用のメール送信ヘルパー
     *
     * 名前・メールアドレス・メッセージからお問い合わせメールを構築して送信する。
     *
     * @param string $to      送信先アドレス
     * @param string $name    送信者名
     * @param string $email   送信者メールアドレス
     * @param string $message メッセージ本文
     * @return bool 送信成功の場合 true
     */
    public function sendContact(string $to, string $name, string $email, string $message): bool {
        $safeName = $this->sanitizeHeader($name);
        $safeEmail = $this->sanitizeHeader($email);

        $subject = "お問い合わせ: {$safeName}";
        $body = "名前: {$safeName}\nメール: {$safeEmail}\n\n{$message}";

        return $this->send($to, $subject, $body, ['Reply-To' => $safeEmail]);
    }

    /**
     * テストモードかどうかを返す
     *
     * @return bool テストモードの場合 true
     */
    public function isTestMode(): bool {
        return $this->testMode;
    }

    /**
     * 最後のエラーメッセージを取得する
     *
     * @return string エラーメッセージ
     */
    public function getLastError(): string {
        return $this->lastError;
    }

    /**
     * テストモードで送信されたメール一覧を取得する
     *
     * @return array<array<string, mixed>> 送信メール情報の配列
     */
    public function getSentMails(): array {
        return $this->sentMails;
    }

    /**
     * ヘッダインジェクション対策: CR/LF/NULL を除去する
     *
     * @param string $value サニタイズ対象の文字列
     * @return string サニタイズ済みの文字列
     */
    private function sanitizeHeader(string $value): string {
        return str_replace(["\r", "\n", "\0"], '', $value);
    }
}

// ============================================================================
// BackupManager - バックアップ作成・管理
// ============================================================================

/**
 * アプリケーションのバックアップ作成・復元・世代管理。
 *
 * UpdateEngine のバックアップ機能を独立したクラスとして抽出。
 * メタデータ付きバックアップ作成、復元、世代数による自動削除に対応。
 */
class BackupManager {

    /** @var string アプリケーションのルートディレクトリ */
    private string $appDir;

    /** @var string バックアップ保存先ディレクトリ */
    private string $backupDir;

    /** @var array<string> バックアップ対象から除外するディレクトリ名 */
    private array $excludeDirs = ['backup', '.git', 'node_modules', 'vendor'];

    /**
     * コンストラクタ
     *
     * @param string $appDir    アプリケーションのルートディレクトリ
     * @param string $backupDir バックアップ保存先ディレクトリ
     */
    public function __construct(string $appDir, string $backupDir) {
        $this->appDir = rtrim($appDir, '/\\');
        $this->backupDir = rtrim($backupDir, '/\\');

        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
    }

    /**
     * バックアップを作成する
     *
     * アプリケーションディレクトリの内容を日時ベースのディレクトリにコピーし、
     * メタデータファイル（meta.json）を付与する。
     *
     * @param string $label バックアップラベル（メタデータに記録）
     * @return string バックアップ名（ディレクトリ名）
     * @throws \RuntimeException バックアップ作成に失敗した場合
     */
    public function create(string $label = ''): string {
        $name = date('Ymd_His');
        $dest = $this->backupDir . '/' . $name;

        if (!@mkdir($dest, 0755, true)) {
            throw new \RuntimeException("バックアップディレクトリの作成に失敗しました: {$name}");
        }

        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->appDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        $fileCount = 0;
        $sizeBytes = 0;

        foreach ($iter as $item) {
            $subPath = $iter->getSubPathname();
            $parts = explode(DIRECTORY_SEPARATOR, $subPath);

            /* 除外ディレクトリのスキップ */
            if (in_array($parts[0], $this->excludeDirs, true)) {
                continue;
            }

            $destPath = $dest . '/' . $subPath;

            if ($item->isDir()) {
                @mkdir($destPath, 0755, true);
            } else {
                $destDir = dirname($destPath);
                if (!is_dir($destDir)) {
                    @mkdir($destDir, 0755, true);
                }
                if (@copy($item->getRealPath(), $destPath)) {
                    $fileCount++;
                    $sizeBytes += $item->getSize();
                }
            }
        }

        /* メタデータの保存 */
        $meta = [
            'label'      => $label,
            'created_at' => date('Y-m-d H:i:s'),
            'file_count' => $fileCount,
            'size_bytes' => $sizeBytes,
        ];
        file_put_contents(
            $dest . '/meta.json',
            json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );

        return $name;
    }

    /**
     * バックアップからアプリケーションを復元する
     *
     * @param string $name 復元対象のバックアップ名
     * @return bool 成功した場合 true
     * @throws \RuntimeException バックアップが見つからない場合
     */
    public function restore(string $name): bool {
        $name = basename($name);
        $src = $this->backupDir . '/' . $name;

        if (!is_dir($src)) {
            throw new \RuntimeException("バックアップが見つかりません: {$name}");
        }

        $realSrc = realpath($src);
        $appRoot = realpath($this->appDir);

        if ($realSrc === false || $appRoot === false) {
            throw new \RuntimeException("パスの解決に失敗しました");
        }

        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($realSrc, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iter as $item) {
            $rel = substr($item->getRealPath(), strlen($realSrc) + 1);
            if ($rel === false || $rel === '' || $rel === 'meta.json') {
                continue;
            }
            if (str_contains($rel, '..')) {
                continue; /* パストラバーサル防止 */
            }

            $dest = $appRoot . DIRECTORY_SEPARATOR . $rel;

            if ($item->isDir()) {
                @mkdir($dest, 0755, true);
            } else {
                $destDir = dirname($dest);
                $realDestDir = realpath($destDir);
                if ($realDestDir === false || !str_starts_with($realDestDir, $appRoot)) {
                    continue;
                }
                @copy($item->getRealPath(), $dest);
            }
        }

        return true;
    }

    /**
     * バックアップを削除する
     *
     * @param string $name 削除対象のバックアップ名
     * @return bool 成功した場合 true
     */
    public function delete(string $name): bool {
        $name = basename($name);
        $dir = $this->backupDir . '/' . $name;

        if (!is_dir($dir)) {
            return false;
        }

        $this->removeDirectory($dir);
        return true;
    }

    /**
     * バックアップ一覧を取得する
     *
     * @return array<array{name: string, meta: array|null}> バックアップ情報の配列（新しい順）
     */
    public function list(): array {
        $backups = glob($this->backupDir . '/*', GLOB_ONLYDIR);
        if (!is_array($backups)) {
            return [];
        }

        rsort($backups);
        $result = [];

        foreach ($backups as $path) {
            $name = basename($path);
            $meta = null;
            $metaFile = $path . '/meta.json';

            if (is_file($metaFile)) {
                $content = file_get_contents($metaFile);
                if ($content !== false) {
                    $decoded = json_decode($content, true);
                    if (is_array($decoded)) {
                        $meta = $decoded;
                    }
                }
            }

            $result[] = ['name' => $name, 'meta' => $meta];
        }

        return $result;
    }

    /**
     * 古いバックアップを自動削除する（世代管理）
     *
     * 最大世代数を超えるバックアップを古い順に削除する。
     *
     * @param int $maxGenerations 保持する最大世代数
     * @return int 削除されたバックアップ数
     */
    public function prune(int $maxGenerations = 5): int {
        $backups = glob($this->backupDir . '/*', GLOB_ONLYDIR);
        if (!is_array($backups) || count($backups) <= $maxGenerations) {
            return 0;
        }

        sort($backups); /* 古い順にソート */
        $excess = count($backups) - $maxGenerations;
        $deleted = 0;

        for ($i = 0; $i < $excess; $i++) {
            $this->removeDirectory($backups[$i]);
            $deleted++;
        }

        return $deleted;
    }

    /**
     * ディレクトリを再帰的に削除する
     *
     * @param string $dir 削除対象のディレクトリ
     */
    private function removeDirectory(string $dir): void {
        if (!is_dir($dir)) {
            return;
        }

        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iter as $item) {
            if ($item->isDir()) {
                @rmdir($item->getRealPath());
            } else {
                @unlink($item->getRealPath());
            }
        }

        @rmdir($dir);
    }
}
