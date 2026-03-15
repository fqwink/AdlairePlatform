<?php
/**
 * Adlaire Static Generator (ASG) - Utilities Module
 *
 * ASG = Adlaire Static Generator
 *
 * ビルドキャッシュ、画像処理、差分ビルド、デプロイメントヘルパーを提供する。
 * Adlaire Platform の ImageOptimizer 等から抽出・汎用化した独立フレームワーク。
 *
 * @package ASG
 * @version 1.0.0
 * @license Adlaire License Ver.2.0
 */

namespace ASG\Utilities;

// ============================================================================
// BuildCache - ビルド状態キャッシュ
// ============================================================================

/**
 * ビルド状態の永続化とコンテンツ変更検出を管理する。
 *
 * 差分ビルドにおいて、前回のビルド状態（各ページのコンテンツハッシュ等）を
 * ファイルベースでキャッシュし、変更の有無を効率的に判定する。
 */
class BuildCache {
    /** @var string キャッシュディレクトリパス */
    private readonly string $cacheDir;

    /** @var string ビルド状態ファイルのパス */
    private readonly string $stateFile;

    /** @var array<string, mixed> メモリ上の状態キャッシュ */
    private array $stateCache = [];

    /** @var bool 状態がメモリに読み込み済みか */
    private bool $stateLoaded = false;

    /**
     * ビルドキャッシュを初期化する
     *
     * @param string $cacheDir キャッシュディレクトリパス
     */
    public function __construct(string $cacheDir) {
        $this->cacheDir = rtrim($cacheDir, '/\\');
        $this->stateFile = $this->cacheDir . '/build_state.json';

        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    /**
     * キャッシュから値を取得する
     *
     * @param string $key キャッシュキー
     * @return mixed キャッシュされた値。存在しない場合は null
     */
    public function get(string $key): mixed {
        $file = $this->getCacheFilePath($key);

        if (!file_exists($file)) {
            return null;
        }

        $data = file_get_contents($file);
        if ($data === false) {
            return null;
        }

        $decoded = json_decode($data, true);
        if (!is_array($decoded)) {
            return null;
        }

        /* 有効期限チェック */
        if (isset($decoded['expires']) && $decoded['expires'] > 0 && $decoded['expires'] < time()) {
            $this->delete($key);
            return null;
        }

        return $decoded['value'] ?? null;
    }

    /**
     * キャッシュに値を保存する
     *
     * @param string $key   キャッシュキー
     * @param mixed  $value 保存する値（JSON シリアライズ可能）
     * @param int    $ttl   有効期限（秒）。0 は無期限
     */
    public function set(string $key, mixed $value, int $ttl = 0): void {
        $file = $this->getCacheFilePath($key);
        $data = [
            'value'   => $value,
            'expires' => $ttl > 0 ? time() + $ttl : 0,
            'created' => time(),
        ];

        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    /**
     * コンテンツのハッシュ値を計算する
     *
     * @param string $content ハッシュ対象のコンテンツ文字列
     * @return string SHA-256 ハッシュ値
     */
    public function getContentHash(string $content): string {
        return hash('sha256', $content);
    }

    /**
     * スラッグに対応するコンテンツが変更されたかを判定する
     *
     * 前回のビルド状態に保存されたハッシュと比較する。
     *
     * @param string $slug    ページスラッグ
     * @param string $newHash 現在のコンテンツハッシュ
     * @return bool 変更があった場合（または初回の場合）true
     */
    public function hasChanged(string $slug, string $newHash): bool {
        $state = $this->loadState();
        $previousHash = $state['hashes'][$slug] ?? '';

        return $previousHash !== $newHash;
    }

    /**
     * ビルド状態を保存する
     *
     * 全ページのハッシュマップとビルドメタデータを永続化する。
     *
     * @param array $state ビルド状態配列
     *   - hashes: array<string, string>  スラッグ => ハッシュのマップ
     *   - settings_hash: string          設定ファイルのハッシュ
     *   - theme_hash: string             テーマのハッシュ
     */
    public function saveState(array $state): void {
        $state['timestamp'] = date('Y-m-d H:i:s');
        $state['version'] = '1.0.0';

        $json = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json !== false) {
            file_put_contents($this->stateFile, $json, LOCK_EX);
        }

        $this->stateCache = $state;
        $this->stateLoaded = true;
    }

    /**
     * 保存されたビルド状態を読み込む
     *
     * @return array ビルド状態配列。未保存の場合は空の初期状態
     */
    public function loadState(): array {
        if ($this->stateLoaded) {
            return $this->stateCache;
        }

        if (!file_exists($this->stateFile)) {
            $this->stateCache = ['hashes' => [], 'timestamp' => '', 'version' => '1.0.0'];
            $this->stateLoaded = true;
            return $this->stateCache;
        }

        $data = file_get_contents($this->stateFile);
        if ($data === false) {
            $this->stateCache = ['hashes' => [], 'timestamp' => '', 'version' => '1.0.0'];
            $this->stateLoaded = true;
            return $this->stateCache;
        }

        $decoded = json_decode($data, true);
        $this->stateCache = is_array($decoded) ? $decoded : ['hashes' => [], 'timestamp' => '', 'version' => '1.0.0'];
        $this->stateLoaded = true;

        return $this->stateCache;
    }

    /**
     * ビルドマニフェストを生成する。
     *
     * 全ページのハッシュと前回の状態を比較し、変更・追加・削除されたページを分類する。
     * 差分ビルドの判断材料として使用する。
     *
     * @since Ver.1.9
     * @param array<string, string> $currentHashes スラッグ => コンテンツハッシュのマップ
     * @return array{changed: array, added: array, deleted: array, unchanged: array, stats: array} マニフェスト
     */
    public function buildManifest(array $currentHashes): array {
        $state = $this->loadState();
        $previousHashes = $state['hashes'] ?? [];

        $changed = [];
        $added = [];
        $deleted = [];
        $unchanged = [];

        foreach ($currentHashes as $slug => $hash) {
            if (!isset($previousHashes[$slug])) {
                $added[] = $slug;
            } elseif ($previousHashes[$slug] !== $hash) {
                $changed[] = $slug;
            } else {
                $unchanged[] = $slug;
            }
        }

        foreach ($previousHashes as $slug => $_) {
            if (!isset($currentHashes[$slug])) {
                $deleted[] = $slug;
            }
        }

        return [
            'changed'   => $changed,
            'added'     => $added,
            'deleted'   => $deleted,
            'unchanged' => $unchanged,
            'stats'     => [
                'total'     => count($currentHashes),
                'changed'   => count($changed),
                'added'     => count($added),
                'deleted'   => count($deleted),
                'unchanged' => count($unchanged),
                'needs_build' => count($changed) + count($added),
            ],
        ];
    }

    /**
     * 設定・テーマの変更を検知し、フルリビルドが必要か判定する。
     *
     * @since Ver.1.9
     * @param string $settingsHash 現在の設定ハッシュ
     * @param string $themeHash    現在のテーマハッシュ
     * @return bool フルリビルドが必要な場合 true
     */
    public function needsFullRebuild(string $settingsHash, string $themeHash): bool {
        $state = $this->loadState();
        $prevSettings = $state['settings_hash'] ?? '';
        $prevTheme = $state['theme_hash'] ?? '';

        return $prevSettings !== $settingsHash || $prevTheme !== $themeHash;
    }

    /**
     * マニフェストに基づいて状態を更新する。
     *
     * ビルド完了後に呼び出し、ハッシュマップを更新・永続化する。
     *
     * @since Ver.1.9
     * @param array<string, string> $hashes        スラッグ => ハッシュのマップ
     * @param string                $settingsHash  設定ファイルのハッシュ
     * @param string                $themeHash     テーマのハッシュ
     */
    public function commitManifest(array $hashes, string $settingsHash = '', string $themeHash = ''): void {
        $this->saveState([
            'hashes'        => $hashes,
            'settings_hash' => $settingsHash,
            'theme_hash'    => $themeHash,
        ]);
    }

    /**
     * キャッシュエントリを削除する
     *
     * @param string $key キャッシュキー
     */
    private function delete(string $key): void {
        $file = $this->getCacheFilePath($key);
        if (file_exists($file)) {
            @unlink($file);
        }
    }

    /**
     * キャッシュキーからファイルパスを生成する
     *
     * @param string $key キャッシュキー
     * @return string ファイルパス
     */
    private function getCacheFilePath(string $key): string {
        return $this->cacheDir . '/' . md5($key) . '.cache.json';
    }
}

// ============================================================================
// ImageProcessor - 画像最適化処理
// ============================================================================

/**
 * 画像のリサイズ、サムネイル生成、WebP 変換を提供する。
 *
 * GD ライブラリを使用。GD が利用できない環境ではグレースフルに
 * 失敗し、元の画像をそのまま使用できるようにする。
 *
 * Adlaire Platform の ImageOptimizer から抽出・汎用化。
 */
class ImageProcessor {
    /** @var int JPEG 保存品質（0-100） */
    private const JPEG_QUALITY = 85;

    /** @var int ファイルサイズ上限（50MB） */
    private const MAX_FILE_SIZE = 52_428_800;

    /**
     * 画像を指定の最大寸法にリサイズする
     *
     * アスペクト比を維持したまま、指定の最大幅・最大高さに収まるようリサイズする。
     * 既に指定サイズ以下の場合は何もしない。
     *
     * @param string $path      画像ファイルパス
     * @param int    $maxWidth  最大幅（ピクセル）
     * @param int    $maxHeight 最大高さ（ピクセル）
     * @return bool リサイズが実行された場合 true
     */
    public function resize(string $path, int $maxWidth, int $maxHeight): bool {
        if (!$this->validateImage($path)) {
            return false;
        }

        $info = @getimagesize($path);
        if ($info === false) {
            return false;
        }

        $origWidth  = $info[0];
        $origHeight = $info[1];
        $mime       = $info['mime'] ?? '';

        /* リサイズ不要の場合はスキップ */
        if ($origWidth <= $maxWidth && $origHeight <= $maxHeight) {
            return true;
        }

        /* メモリ使用量の事前チェック */
        if (!$this->checkMemory($origWidth, $origHeight)) {
            return false;
        }

        $src = $this->loadImage($path, $mime);
        if ($src === null) {
            return false;
        }

        [$newW, $newH] = $this->fitDimensions($origWidth, $origHeight, $maxWidth, $maxHeight);
        $dst = @imagecreatetruecolor($newW, $newH);
        if ($dst === false) {
            imagedestroy($src);
            return false;
        }

        $this->preserveTransparency($dst, $mime);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $origWidth, $origHeight);
        $this->saveImage($dst, $path, $mime);

        imagedestroy($src);
        imagedestroy($dst);

        return true;
    }

    /**
     * サムネイルを生成する
     *
     * 指定サイズに収まるサムネイルを生成し、ファイルパスを返す。
     * サムネイルは元画像と同じディレクトリの thumb/ サブディレクトリに保存される。
     *
     * @param string $path   元画像のファイルパス
     * @param int    $width  サムネイルの最大幅
     * @param int    $height サムネイルの最大高さ
     * @return string サムネイルのファイルパス。失敗時は空文字
     */
    public function thumbnail(string $path, int $width, int $height): string {
        if (!$this->validateImage($path)) {
            return '';
        }

        $info = @getimagesize($path);
        if ($info === false) {
            return '';
        }

        $origW = $info[0];
        $origH = $info[1];
        $mime  = $info['mime'] ?? '';

        /* サムネイル出力先 */
        $thumbDir = dirname($path) . '/thumb';
        if (!is_dir($thumbDir)) {
            mkdir($thumbDir, 0755, true);
        }
        $thumbPath = $thumbDir . '/' . basename($path);

        if (!$this->checkMemory($origW, $origH)) {
            return '';
        }

        $src = $this->loadImage($path, $mime);
        if ($src === null) {
            return '';
        }

        [$newW, $newH] = $this->fitDimensions($origW, $origH, $width, $height);
        $dst = @imagecreatetruecolor($newW, $newH);
        if ($dst === false) {
            imagedestroy($src);
            return '';
        }

        $this->preserveTransparency($dst, $mime);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
        $this->saveImage($dst, $thumbPath, $mime);

        imagedestroy($src);
        imagedestroy($dst);

        return $thumbPath;
    }

    /**
     * 画像を WebP 形式に変換する
     *
     * 元画像と同じディレクトリに .webp 拡張子のファイルとして保存する。
     * GIF 画像と既に WebP の画像はスキップする。
     *
     * @param string $path    元画像のファイルパス
     * @param int    $quality WebP 品質（0-100、デフォルト: 80）
     * @return string|false WebP ファイルパス。失敗時は false
     */
    public function toWebP(string $path, int $quality = 80): string|false {
        if (!function_exists('imagewebp')) {
            return false;
        }

        if (!$this->validateImage($path)) {
            return false;
        }

        $info = @getimagesize($path);
        if ($info === false) {
            return false;
        }

        $mime = $info['mime'] ?? '';

        /* WebP / GIF はスキップ */
        if ($mime === 'image/webp' || $mime === 'image/gif') {
            return false;
        }

        $src = $this->loadImage($path, $mime);
        if ($src === null) {
            return false;
        }

        $webpPath = preg_replace('/\.\w+$/', '.webp', $path);
        if ($webpPath === null || $webpPath === $path) {
            imagedestroy($src);
            return false;
        }

        $result = @imagewebp($src, $webpPath, $quality);
        imagedestroy($src);

        return $result ? $webpPath : false;
    }

    /**
     * 画像の情報を取得する
     *
     * @param string $path 画像ファイルパス
     * @return array 画像情報
     *   - width: int      幅（ピクセル）
     *   - height: int     高さ（ピクセル）
     *   - mime: string    MIME タイプ
     *   - size: int       ファイルサイズ（バイト）
     *   - aspect: float   アスペクト比（幅/高さ）
     */
    public function getInfo(string $path): array {
        $default = [
            'width'  => 0,
            'height' => 0,
            'mime'   => '',
            'size'   => 0,
            'aspect' => 0.0,
        ];

        if (!file_exists($path)) {
            return $default;
        }

        $size = filesize($path);
        $info = @getimagesize($path);

        if ($info === false) {
            return array_merge($default, ['size' => $size ?: 0]);
        }

        $width  = $info[0];
        $height = $info[1];

        return [
            'width'  => $width,
            'height' => $height,
            'mime'   => $info['mime'] ?? '',
            'size'   => $size ?: 0,
            'aspect' => $height > 0 ? round($width / $height, 4) : 0.0,
        ];
    }

    // ========================================================================
    // 内部ヘルパー
    // ========================================================================

    /**
     * 画像ファイルの基本的なバリデーションを行う
     *
     * @param string $path ファイルパス
     * @return bool 有効な場合 true
     */
    private function validateImage(string $path): bool {
        if (!extension_loaded('gd')) {
            return false;
        }
        if (!file_exists($path)) {
            return false;
        }

        $fileSize = filesize($path);
        return $fileSize !== false && $fileSize <= self::MAX_FILE_SIZE;
    }

    /**
     * 画像処理に必要なメモリが確保可能か事前チェックする
     *
     * @param int $width  画像の幅
     * @param int $height 画像の高さ
     * @return bool メモリが足りる場合 true
     */
    private function checkMemory(int $width, int $height): bool {
        $requiredMemory = $width * $height * 4 * 2;
        $memoryLimit = $this->getMemoryLimitBytes();

        if ($memoryLimit > 0 && (memory_get_usage() + $requiredMemory) > $memoryLimit) {
            return false;
        }

        return true;
    }

    /**
     * MIME タイプに応じて画像を読み込む
     *
     * @param string $path ファイルパス
     * @param string $mime MIME タイプ
     * @return \GdImage|null GD 画像リソース
     */
    private function loadImage(string $path, string $mime): ?\GdImage {
        return match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path) ?: null,
            'image/png'  => @imagecreatefrompng($path) ?: null,
            'image/gif'  => @imagecreatefromgif($path) ?: null,
            'image/webp' => @imagecreatefromwebp($path) ?: null,
            default      => null,
        };
    }

    /**
     * MIME タイプに応じて画像を保存する
     *
     * @param \GdImage $img  GD 画像リソース
     * @param string   $path 保存先パス
     * @param string   $mime MIME タイプ
     */
    private function saveImage(\GdImage $img, string $path, string $mime): void {
        match ($mime) {
            'image/jpeg' => imagejpeg($img, $path, self::JPEG_QUALITY),
            'image/png'  => imagepng($img, $path, 6),
            'image/gif'  => imagegif($img, $path),
            'image/webp' => imagewebp($img, $path, 80),
            default      => null,
        };
    }

    /**
     * アスペクト比を維持したまま指定サイズに収まる寸法を計算する
     *
     * @param int $w    元の幅
     * @param int $h    元の高さ
     * @param int $maxW 最大幅
     * @param int $maxH 最大高さ
     * @return array [幅, 高さ]
     */
    private function fitDimensions(int $w, int $h, int $maxW, int $maxH): array {
        $ratio = min($maxW / $w, $maxH / $h);
        if ($ratio >= 1) {
            return [$w, $h];
        }
        return [(int)round($w * $ratio), (int)round($h * $ratio)];
    }

    /**
     * PHP のメモリ制限をバイト単位で取得する
     *
     * @return int メモリ制限（バイト）。無制限の場合は 0
     */
    private function getMemoryLimitBytes(): int {
        $limit = ini_get('memory_limit');
        if ($limit === '-1' || $limit === false) {
            return 0;
        }
        $limit = trim($limit);
        $unit = strtolower(substr($limit, -1));
        $val = (int)$limit;
        return match ($unit) {
            'g' => $val * 1024 * 1024 * 1024,
            'm' => $val * 1024 * 1024,
            'k' => $val * 1024,
            default => $val,
        };
    }

    /**
     * PNG / GIF / WebP の透過を保持する設定を適用する
     *
     * @param \GdImage $img  GD 画像リソース
     * @param string   $mime MIME タイプ
     */
    private function preserveTransparency(\GdImage $img, string $mime): void {
        if ($mime === 'image/png' || $mime === 'image/gif' || $mime === 'image/webp') {
            imagealphablending($img, false);
            imagesavealpha($img, true);
            $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
            if ($transparent !== false) {
                imagefilledrectangle($img, 0, 0, imagesx($img), imagesy($img), $transparent);
            }
        }
    }
}

// ============================================================================
// ImageService - 画像最適化スタティックファサード
// ============================================================================

/**
 * ImageOptimizer の全ロジックを Framework 名前空間に移植したスタティックファサード。
 *
 * GD ライブラリを使用した画像リサイズ・サムネイル生成・WebP 変換を提供する。
 * GD 未対応環境ではグレースフルにスキップする。
 *
 * @since Ver.1.8
 */
class ImageService {

    private const MAX_WIDTH     = 1920;
    private const MAX_HEIGHT    = 1920;
    private const THUMB_WIDTH   = 400;
    private const THUMB_HEIGHT  = 400;
    private const JPEG_QUALITY  = 85;
    private const WEBP_QUALITY  = 80;

    /**
     * 画像を最適化（リサイズ + サムネイル生成 + WebP 変換）
     */
    public static function optimize(string $path): bool {
        $_optimizeStart = hrtime(true);
        $_memBefore     = memory_get_usage(true);

        if (!extension_loaded('gd')) {
            \AIS\System\DiagnosticsManager::logEnvironmentIssue(
                'GD ライブラリ未対応', ['path' => basename($path)]
            );
            return false;
        }
        if (!file_exists($path)) return false;

        $fileSize = filesize($path);
        if ($fileSize === false || $fileSize > 50 * 1024 * 1024) return false;

        $info = @getimagesize($path);
        if ($info === false) {
            \AIS\System\DiagnosticsManager::log('engine', 'ImageService 不正な画像ファイル', [
                'path' => basename($path),
            ]);
            return false;
        }

        $mime       = $info['mime'] ?? '';
        $origWidth  = $info[0];
        $origHeight = $info[1];

        /* メモリ使用量の事前チェック */
        $requiredMemory = $origWidth * $origHeight * 4 * 2;
        $memoryLimit    = self::getMemoryLimitBytes();
        if ($memoryLimit > 0 && (memory_get_usage() + $requiredMemory) > $memoryLimit) {
            \APF\Utilities\Logger::warning('ImageService: メモリ不足のためスキップ', ['path' => $path, 'dimensions' => "{$origWidth}x{$origHeight}"]);
            \AIS\System\DiagnosticsManager::log('engine', 'ImageService メモリ不足スキップ', [
                'dimensions' => "{$origWidth}x{$origHeight}",
                'required_mb' => round($requiredMemory / 1048576, 1),
            ]);
            return false;
        }

        try {
            /* メイン画像リサイズ（最大 1920px） */
            if ($origWidth > self::MAX_WIDTH || $origHeight > self::MAX_HEIGHT) {
                $src = self::loadImage($path, $mime);
                if ($src === null) return false;

                [$newW, $newH] = self::fitDimensions(
                    $origWidth, $origHeight, self::MAX_WIDTH, self::MAX_HEIGHT
                );
                $dst = imagecreatetruecolor($newW, $newH);
                if ($dst === false) {
                    \AIS\System\DiagnosticsManager::log('engine', '画像リサイズ失敗: imagecreatetruecolor', [
                        'path' => basename($path), 'target' => "{$newW}x{$newH}",
                        'memory_usage_mb' => round(memory_get_usage(true) / 1048576, 1),
                    ]);
                    imagedestroy($src);
                    return false;
                }

                self::preserveTransparency($dst, $mime);
                imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $origWidth, $origHeight);
                self::saveImage($dst, $path, $mime);
                imagedestroy($src);
                imagedestroy($dst);
            }

            /* サムネイル生成 */
            self::generateThumbnail($path, $mime);

            /* WebP 変換 */
            self::generateWebP($path, $mime);
        } catch (\Throwable $e) {
            \APF\Utilities\Logger::error('ImageService 画像処理中にエラー', [
                'path' => basename($path), 'error' => $e->getMessage(),
            ]);
            \AIS\System\DiagnosticsManager::log('runtime', 'ImageService 例外', [
                'path' => basename($path), 'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }

        $_optimizeElapsed = (hrtime(true) - $_optimizeStart) / 1_000_000;
        if ($_optimizeElapsed > 1000) {
            \AIS\System\DiagnosticsManager::logSlowExecution(
                'ImageService::optimize(' . basename($path) . ')', $_optimizeElapsed, 1000
            );
        }
        $_memAfter = memory_get_usage(true);
        $_memDelta = $_memAfter - $_memBefore;
        if ($_memDelta > 10 * 1024 * 1024) {
            \AIS\System\DiagnosticsManager::log('memory', 'ImageService 高メモリ使用', [
                'file' => basename($path), 'delta_mb' => round($_memDelta / 1048576, 1),
            ]);
        }

        return true;
    }

    /**
     * サムネイルを生成して uploads/thumb/ に保存
     */
    private static function generateThumbnail(string $path, string $mime): void {
        $thumbDir = dirname($path) . '/thumb';
        if (!is_dir($thumbDir) && !@mkdir($thumbDir, 0755, true)) return;

        $thumbPath = $thumbDir . '/' . basename($path);
        $info = @getimagesize($path);
        if ($info === false) return;

        $origW = $info[0];
        $origH = $info[1];

        $src = self::loadImage($path, $mime);
        if ($src === null) return;

        [$newW, $newH] = self::fitDimensions($origW, $origH, self::THUMB_WIDTH, self::THUMB_HEIGHT);
        $dst = imagecreatetruecolor($newW, $newH);
        if ($dst === false) { imagedestroy($src); return; }

        self::preserveTransparency($dst, $mime);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
        self::saveImage($dst, $thumbPath, $mime);
        imagedestroy($src);
        imagedestroy($dst);
    }

    /**
     * WebP 変換版を生成
     */
    private static function generateWebP(string $path, string $mime): void {
        if (!function_exists('imagewebp')) return;
        if ($mime === 'image/webp') return;
        if ($mime === 'image/gif') return;

        $src = self::loadImage($path, $mime);
        if ($src === null) return;

        $webpPath = preg_replace('/\.\w+$/', '.webp', $path);
        if ($webpPath === null || $webpPath === $path) return;

        imagewebp($src, $webpPath, self::WEBP_QUALITY);
        imagedestroy($src);
    }

    /* ── 内部ヘルパー ── */

    private static function loadImage(string $path, string $mime): ?\GdImage {
        $result = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path) ?: null,
            'image/png'  => @imagecreatefrompng($path) ?: null,
            'image/gif'  => @imagecreatefromgif($path) ?: null,
            'image/webp' => @imagecreatefromwebp($path) ?: null,
            default      => null,
        };
        if ($result === null) {
            \AIS\System\DiagnosticsManager::log('engine', '画像ロード失敗', [
                'path' => basename($path), 'mime' => $mime,
                'error' => error_get_last()['message'] ?? '',
            ]);
        }
        return $result;
    }

    private static function saveImage(\GdImage $img, string $path, string $mime): void {
        match ($mime) {
            'image/jpeg' => imagejpeg($img, $path, self::JPEG_QUALITY),
            'image/png'  => imagepng($img, $path, 6),
            'image/gif'  => imagegif($img, $path),
            'image/webp' => imagewebp($img, $path, self::WEBP_QUALITY),
            default      => null,
        };
    }

    private static function fitDimensions(int $w, int $h, int $maxW, int $maxH): array {
        $ratio = min($maxW / $w, $maxH / $h);
        if ($ratio >= 1) return [$w, $h];
        return [(int)round($w * $ratio), (int)round($h * $ratio)];
    }

    private static function getMemoryLimitBytes(): int {
        $limit = ini_get('memory_limit');
        if ($limit === '-1' || $limit === false) return 0;
        $limit = trim($limit);
        $unit  = strtolower(substr($limit, -1));
        $val   = (int)$limit;
        return match ($unit) {
            'g' => $val * 1024 * 1024 * 1024,
            'm' => $val * 1024 * 1024,
            'k' => $val * 1024,
            default => $val,
        };
    }

    private static function preserveTransparency(\GdImage $img, string $mime): void {
        if ($mime === 'image/png' || $mime === 'image/gif' || $mime === 'image/webp') {
            imagealphablending($img, false);
            imagesavealpha($img, true);
            $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
            if ($transparent !== false) {
                imagefilledrectangle($img, 0, 0, imagesx($img), imagesy($img), $transparent);
            }
        }
    }
}

// ============================================================================
// DiffBuilder - 差分ビルドエンジン
// ============================================================================

/**
 * 差分ビルドの変更検出ロジックを担当する。
 *
 * コンテンツのハッシュ値を比較し、前回のビルドから変更された
 * ページのみを特定する。設定やテーマの変更による全ページ再ビルドにも対応。
 */
class DiffBuilder {
    /** @var BuildCache ビルドキャッシュインスタンス */
    private readonly BuildCache $cache;

    /**
     * 差分ビルダーを初期化する
     *
     * @param BuildCache $cache ビルドキャッシュ
     */
    public function __construct(BuildCache $cache) {
        $this->cache = $cache;
    }

    /**
     * 変更されたページを検出する
     *
     * 現在のページ群と前回のビルド状態を比較し、
     * 再ビルドが必要なスラッグの一覧を返す。
     *
     * 設定やテーマが変更された場合は全ページを変更ありとして返す。
     *
     * @param array $currentPages    現在のページ情報
     *   キー: slug, 値: ['content' => string, ...] または content 文字列
     * @param array $currentSettings 現在の設定配列（ハッシュ比較用）
     * @return array 変更されたスラッグの配列
     */
    public function detectChanges(array $currentPages, array $currentSettings): array {
        $state = $this->cache->loadState();
        $previousHashes = $state['hashes'] ?? [];
        $previousSettingsHash = $state['settings_hash'] ?? '';

        /* 設定変更チェック — 変更があれば全ページを再ビルド */
        $currentSettingsHash = $this->cache->getContentHash(json_encode($currentSettings));
        if ($previousSettingsHash !== '' && $previousSettingsHash !== $currentSettingsHash) {
            return array_keys($currentPages);
        }

        $changedSlugs = [];

        foreach ($currentPages as $slug => $pageData) {
            $content = is_array($pageData) ? ($pageData['content'] ?? '') : (string)$pageData;
            $currentHash = $this->cache->getContentHash($content);

            if ($this->cache->hasChanged($slug, $currentHash)) {
                $changedSlugs[] = $slug;
            }
        }

        /* 削除されたページも検出 */
        $deletedSlugs = array_diff(array_keys($previousHashes), array_keys($currentPages));
        foreach ($deletedSlugs as $slug) {
            $changedSlugs[] = $slug;
        }

        return array_unique($changedSlugs);
    }

    /**
     * ページのビルド完了を記録する
     *
     * 指定スラッグのハッシュを現在の状態に書き込む。
     * saveState() を呼ぶまでメモリ上にのみ保持される。
     *
     * @param string $slug ページスラッグ
     * @param string $hash コンテンツハッシュ
     */
    public function markBuilt(string $slug, string $hash): void {
        $state = $this->cache->loadState();
        $state['hashes'][$slug] = $hash;
        $this->cache->saveState($state);
    }
}

// ============================================================================
// Deployer - 静的サイトデプロイメントヘルパー
// ============================================================================

/**
 * 静的サイトのデプロイに必要なファイル生成を提供する。
 *
 * ZIP アーカイブ作成、.htaccess 生成、Netlify _redirects 生成など、
 * 各種デプロイ先に対応した出力をサポートする。
 */
class Deployer {
    /**
     * ディレクトリ全体を ZIP アーカイブにする
     *
     * @param string $sourceDir  アーカイブ対象ディレクトリ
     * @param string $outputPath 出力 ZIP ファイルパス
     * @return bool 成功した場合 true
     */
    public function createZip(string $sourceDir, string $outputPath): bool {
        if (!class_exists(\ZipArchive::class)) {
            return false;
        }

        if (!is_dir($sourceDir)) {
            return false;
        }

        $zip = new \ZipArchive();
        if ($zip->open($outputPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return false;
        }

        $sourceDir = realpath($sourceDir);
        if ($sourceDir === false) {
            return false;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $filePath = $file->getPathname();
            $relativePath = substr($filePath, strlen($sourceDir) + 1);
            /* パス区切りを統一 */
            $relativePath = str_replace('\\', '/', $relativePath);

            $zip->addFile($filePath, $relativePath);
        }

        return $zip->close();
    }

    /**
     * 変更されたファイルのみを含む差分 ZIP を作成する
     *
     * @param string $sourceDir    ソースディレクトリ
     * @param array  $changedFiles 変更されたファイルの相対パス配列
     * @param string $outputPath   出力 ZIP ファイルパス
     * @return bool 成功した場合 true
     */
    public function createDiffZip(string $sourceDir, array $changedFiles, string $outputPath): bool {
        if (!class_exists(\ZipArchive::class)) {
            return false;
        }

        if (!is_dir($sourceDir)) {
            return false;
        }

        $zip = new \ZipArchive();
        if ($zip->open($outputPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return false;
        }

        $sourceDir = realpath($sourceDir);
        if ($sourceDir === false) {
            $zip->close();
            return false;
        }

        foreach ($changedFiles as $relativePath) {
            $relativePath = str_replace('\\', '/', $relativePath);
            $fullPath = $sourceDir . '/' . $relativePath;

            if (file_exists($fullPath) && is_file($fullPath)) {
                $zip->addFile($fullPath, $relativePath);
            }
        }

        return $zip->close();
    }

    /**
     * Apache .htaccess のリダイレクトルールを生成する
     *
     * @param array $redirects リダイレクト定義の配列
     *   各要素: ['from' => string, 'to' => string, 'status' => int]
     *   status: 301（恒久的）または 302（一時的）
     * @return string .htaccess ファイル内容
     */
    public function generateHtaccess(array $redirects): string {
        $lines = [];
        $lines[] = '# Generated by ASG (Adlaire Static Generator)';
        $lines[] = '# ' . date('Y-m-d H:i:s');
        $lines[] = '';
        $lines[] = 'RewriteEngine On';
        $lines[] = '';

        /* エラードキュメント */
        $lines[] = 'ErrorDocument 404 /404.html';
        $lines[] = '';

        /* UTF-8 文字エンコーディング */
        $lines[] = 'AddDefaultCharset UTF-8';
        $lines[] = '';

        /* MIME タイプ */
        $lines[] = '<IfModule mod_mime.c>';
        $lines[] = '    AddType text/html .html';
        $lines[] = '    AddType text/css .css';
        $lines[] = '    AddType application/javascript .js';
        $lines[] = '    AddType image/webp .webp';
        $lines[] = '    AddType image/svg+xml .svg';
        $lines[] = '</IfModule>';
        $lines[] = '';

        /* GZIP 圧縮 */
        $lines[] = '<IfModule mod_deflate.c>';
        $lines[] = '    AddOutputFilterByType DEFLATE text/html text/css application/javascript';
        $lines[] = '    AddOutputFilterByType DEFLATE application/json application/xml';
        $lines[] = '    AddOutputFilterByType DEFLATE image/svg+xml';
        $lines[] = '</IfModule>';
        $lines[] = '';

        /* キャッシュ制御 */
        $lines[] = '<IfModule mod_expires.c>';
        $lines[] = '    ExpiresActive On';
        $lines[] = '    ExpiresByType text/html "access plus 1 hour"';
        $lines[] = '    ExpiresByType text/css "access plus 1 month"';
        $lines[] = '    ExpiresByType application/javascript "access plus 1 month"';
        $lines[] = '    ExpiresByType image/jpeg "access plus 1 year"';
        $lines[] = '    ExpiresByType image/png "access plus 1 year"';
        $lines[] = '    ExpiresByType image/webp "access plus 1 year"';
        $lines[] = '</IfModule>';
        $lines[] = '';

        /* リダイレクトルール */
        if (!empty($redirects)) {
            $lines[] = '# Redirects';
            foreach ($redirects as $redirect) {
                $from   = $redirect['from'] ?? '';
                $to     = $redirect['to'] ?? '';
                $status = $redirect['status'] ?? 301;

                if ($from === '' || $to === '') {
                    continue;
                }

                $flag = $status === 302 ? '[R=302,L]' : '[R=301,L]';
                $escapedFrom = preg_quote($from, '/');
                $lines[] = "RewriteRule ^{$escapedFrom}$ {$to} {$flag}";
            }
            $lines[] = '';
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * Netlify _redirects ファイルを生成する
     *
     * @param array $redirects リダイレクト定義の配列
     *   各要素: ['from' => string, 'to' => string, 'status' => int]
     * @return string _redirects ファイル内容
     */
    public function generateRedirectsFile(array $redirects): string {
        $lines = [];
        $lines[] = '# Generated by ASG (Adlaire Static Generator)';
        $lines[] = '# ' . date('Y-m-d H:i:s');
        $lines[] = '';

        foreach ($redirects as $redirect) {
            $from   = $redirect['from'] ?? '';
            $to     = $redirect['to'] ?? '';
            $status = $redirect['status'] ?? 301;

            if ($from === '' || $to === '') {
                continue;
            }

            /* Netlify の _redirects 形式: /from /to status */
            $lines[] = "{$from}    {$to}    {$status}";
        }

        /* 404 フォールバック */
        $lines[] = '';
        $lines[] = '# Custom 404';
        $lines[] = '/*    /404.html    404';

        return implode("\n", $lines) . "\n";
    }
}
