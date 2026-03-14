<?php
/**
 * ImageOptimizer - 画像最適化エンジン
 *
 * アップロード画像の自動リサイズ・サムネイル生成・WebP 変換。
 * GD ライブラリを使用。GD 未対応環境ではスキップ。
 */
class ImageOptimizer {

	private const MAX_WIDTH     = 1920;
	private const MAX_HEIGHT    = 1920;
	private const THUMB_WIDTH   = 400;
	private const THUMB_HEIGHT  = 400;
	private const JPEG_QUALITY  = 85;
	private const WEBP_QUALITY  = 80;

	/**
	 * 画像を最適化（リサイズ + サムネイル生成）
	 */
	public static function optimize(string $path): bool {
		$_optimizeStart = hrtime(true);
		$_memBefore = memory_get_usage(true);
		if (!extension_loaded('gd')) {
			if (class_exists('DiagnosticEngine')) DiagnosticEngine::logEnvironmentIssue('GD ライブラリ未対応', ['path' => basename($path)]);
			return false;
		}
		if (!file_exists($path)) return false;

		/* C12 fix: ファイルサイズ上限チェック（50MB） */
		$fileSize = filesize($path);
		if ($fileSize === false || $fileSize > 50 * 1024 * 1024) return false;

		$info = @getimagesize($path);
		if ($info === false) {
			if (class_exists('DiagnosticEngine')) DiagnosticEngine::log('engine', 'ImageOptimizer 不正な画像ファイル', ['path' => basename($path)]);
			return false;
		}

		$mime = $info['mime'] ?? '';
		$origWidth  = $info[0];
		$origHeight = $info[1];

		/* C12 fix: メモリ使用量の事前チェック（ピクセル × 4バイト × 安全係数2） */
		$requiredMemory = $origWidth * $origHeight * 4 * 2;
		$memoryLimit = self::getMemoryLimitBytes();
		if ($memoryLimit > 0 && (memory_get_usage() + $requiredMemory) > $memoryLimit) {
			error_log("ImageOptimizer: メモリ不足のためスキップ: {$path} ({$origWidth}x{$origHeight})");
			if (class_exists('DiagnosticEngine')) DiagnosticEngine::log('engine', 'ImageOptimizer メモリ不足スキップ', ['dimensions' => "{$origWidth}x{$origHeight}", 'required_mb' => round($requiredMemory / 1048576, 1)]);
			return false;
		}

		try {
			/* メイン画像リサイズ（最大 1920px） */
			if ($origWidth > self::MAX_WIDTH || $origHeight > self::MAX_HEIGHT) {
				$src = self::loadImage($path, $mime);
				if ($src === null) return false;

				list($newW, $newH) = self::fitDimensions($origWidth, $origHeight, self::MAX_WIDTH, self::MAX_HEIGHT);
				$dst = imagecreatetruecolor($newW, $newH);
				if ($dst === false) {
					if (class_exists('DiagnosticEngine')) DiagnosticEngine::log('engine', '画像リサイズ失敗: imagecreatetruecolor', ['path' => basename($path), 'target' => "{$newW}x{$newH}", 'memory_usage_mb' => round(memory_get_usage(true) / 1048576, 1)]);
					imagedestroy($src); return false;
				}

				self::preserveTransparency($dst, $mime);
				imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $origWidth, $origHeight);
				self::saveImage($dst, $path, $mime);
				imagedestroy($src);
				imagedestroy($dst);
			}

			/* サムネイル生成 */
			self::generateThumbnail($path, $mime);

			/* WebP 変換（GD 対応時） */
			self::generateWebP($path, $mime);
		} catch (\Throwable $e) {
			Logger::error('ImageOptimizer 画像処理中にエラー', ['engine' => 'ImageOptimizer', 'path' => basename($path), 'error' => $e->getMessage()]);
			if (class_exists('DiagnosticEngine')) DiagnosticEngine::log('runtime', 'ImageOptimizer 例外', ['path' => basename($path), 'trace' => $e->getTraceAsString()]);
			return false;
		}

		$_optimizeElapsed = (hrtime(true) - $_optimizeStart) / 1_000_000;
		if ($_optimizeElapsed > 1000 && class_exists('DiagnosticEngine')) {
			DiagnosticEngine::logSlowExecution('ImageOptimizer::optimize(' . basename($path) . ')', $_optimizeElapsed, 1000);
		}
		$_memAfter = memory_get_usage(true);
		$_memDelta = $_memAfter - $_memBefore;
		if ($_memDelta > 10 * 1024 * 1024 && class_exists('DiagnosticEngine')) {
			DiagnosticEngine::log('memory', 'ImageOptimizer 高メモリ使用', ['file' => basename($path), 'delta_mb' => round($_memDelta / 1048576, 1)]);
		}

		return true;
	}

	/**
	 * サムネイルを生成して uploads/thumb/ に保存
	 */
	private static function generateThumbnail(string $path, string $mime): void {
		$thumbDir = dirname($path) . '/thumb';
		if (!FileSystem::ensureDir($thumbDir)) return;

		$thumbPath = $thumbDir . '/' . basename($path);
		$info = @getimagesize($path);
		if ($info === false) return;

		$origW = $info[0];
		$origH = $info[1];

		$src = self::loadImage($path, $mime);
		if ($src === null) return;

		list($newW, $newH) = self::fitDimensions($origW, $origH, self::THUMB_WIDTH, self::THUMB_HEIGHT);
		$dst = imagecreatetruecolor($newW, $newH);
		if ($dst === false) { imagedestroy($src); return; }

		self::preserveTransparency($dst, $mime);
		imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
		self::saveImage($dst, $thumbPath, $mime);
		imagedestroy($src);
		imagedestroy($dst);
	}

	/**
	 * WebP 変換版を生成（元画像と同じディレクトリに .webp として保存）
	 */
	private static function generateWebP(string $path, string $mime): void {
		if (!function_exists('imagewebp')) return;
		if ($mime === 'image/webp') return; /* 既に WebP */
		if ($mime === 'image/gif') return; /* GIF はスキップ */

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
		if ($result === null && class_exists('DiagnosticEngine')) {
			DiagnosticEngine::log('engine', '画像ロード失敗', ['path' => basename($path), 'mime' => $mime, 'error' => error_get_last()['message'] ?? '']);
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
		if ($limit === '-1' || $limit === false) return 0; /* 無制限 */
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
