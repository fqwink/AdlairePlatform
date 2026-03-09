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
		if (!extension_loaded('gd')) return false;
		if (!file_exists($path)) return false;

		$info = @getimagesize($path);
		if ($info === false) return false;

		$mime = $info['mime'] ?? '';
		$origWidth  = $info[0];
		$origHeight = $info[1];

		/* メイン画像リサイズ（最大 1920px） */
		if ($origWidth > self::MAX_WIDTH || $origHeight > self::MAX_HEIGHT) {
			$src = self::loadImage($path, $mime);
			if ($src === null) return false;

			list($newW, $newH) = self::fitDimensions($origWidth, $origHeight, self::MAX_WIDTH, self::MAX_HEIGHT);
			$dst = imagecreatetruecolor($newW, $newH);
			if ($dst === false) { imagedestroy($src); return false; }

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

		return true;
	}

	/**
	 * サムネイルを生成して uploads/thumb/ に保存
	 */
	private static function generateThumbnail(string $path, string $mime): void {
		$thumbDir = dirname($path) . '/thumb';
		if (!is_dir($thumbDir)) mkdir($thumbDir, 0755, true);

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

	/**
	 * 既存の uploads/ 画像をバッチ最適化
	 */
	public static function batchOptimize(): array {
		if (!extension_loaded('gd')) return ['error' => 'GD ライブラリが利用できません'];

		$dir = 'uploads/';
		if (!is_dir($dir)) return ['optimized' => 0, 'total' => 0];

		$files = glob($dir . '*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE) ?: [];
		$optimized = 0;

		foreach ($files as $f) {
			if (self::optimize($f)) $optimized++;
		}

		return ['optimized' => $optimized, 'total' => count($files)];
	}

	/* ── 内部ヘルパー ── */

	private static function loadImage(string $path, string $mime): ?\GdImage {
		return match ($mime) {
			'image/jpeg' => @imagecreatefromjpeg($path) ?: null,
			'image/png'  => @imagecreatefrompng($path) ?: null,
			'image/gif'  => @imagecreatefromgif($path) ?: null,
			'image/webp' => @imagecreatefromwebp($path) ?: null,
			default      => null,
		};
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
