<?php
/**
 * CacheEngine - ファイルベース API レスポンスキャッシュ
 *
 * API レスポンスをファイルキャッシュし、高負荷時のパフォーマンスを改善。
 * コンテンツ変更時にキャッシュを自動無効化。
 * ETag / Last-Modified ヘッダー対応。
 */
class CacheEngine {

	private const CACHE_DIR = 'data/cache/api';

	/* デフォルト TTL（秒） */
	private const TTL = [
		'pages'       => 300,
		'page'        => 300,
		'settings'    => 3600,
		'collections' => 60,
		'collection'  => 60,
		'item'        => 60,
		'search'      => 120,
	];

	/**
	 * キャッシュからレスポンスを返す。ヒットすれば true、ミスなら false。
	 * ETag / Last-Modified ヘッダーも処理。
	 */
	public static function serve(string $endpoint, array $params = []): bool {
		$key = self::cacheKey($endpoint, $params);
		$path = self::cachePath($key);
		if (!file_exists($path)) return false;

		$ttl = self::TTL[$endpoint] ?? 60;
		$mtime = filemtime($path);
		if ($mtime === false || (time() - $mtime) > $ttl) {
			@unlink($path);
			return false;
		}

		$etag = '"' . md5($key . $mtime) . '"';
		$lastModified = gmdate('D, d M Y H:i:s', $mtime) . ' GMT';

		/* 304 Not Modified チェック */
		$ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
		$ifModifiedSince = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '';

		if ($ifNoneMatch !== '' && $ifNoneMatch === $etag) {
			http_response_code(304);
			exit;
		}
		if ($ifModifiedSince !== '' && strtotime($ifModifiedSince) >= $mtime) {
			http_response_code(304);
			exit;
		}

		$content = file_get_contents($path);
		if ($content === false) return false;

		header('Content-Type: application/json; charset=UTF-8');
		header('ETag: ' . $etag);
		header('Last-Modified: ' . $lastModified);
		header('X-Cache: HIT');
		echo $content;
		exit;
	}

	/**
	 * レスポンスをキャッシュに保存
	 */
	public static function store(string $endpoint, array $params, string $content): void {
		$key = self::cacheKey($endpoint, $params);
		$path = self::cachePath($key);
		$dir = dirname($path);
		if (!is_dir($dir)) mkdir($dir, 0755, true);
		file_put_contents($path, $content, LOCK_EX);
	}

	/**
	 * 指定エンドポイントのキャッシュを無効化
	 */
	public static function invalidate(string $endpoint = ''): void {
		$dir = self::CACHE_DIR;
		if (!is_dir($dir)) return;

		if ($endpoint === '') {
			/* 全キャッシュクリア */
			self::clearDir($dir);
			return;
		}

		/* 特定エンドポイントのキャッシュを削除 */
		$files = glob($dir . '/' . $endpoint . '_*.json') ?: [];
		foreach ($files as $f) {
			@unlink($f);
		}
	}

	/**
	 * コンテンツ変更時に関連キャッシュを無効化
	 */
	public static function invalidateContent(): void {
		self::invalidate('pages');
		self::invalidate('page');
		self::invalidate('collections');
		self::invalidate('collection');
		self::invalidate('item');
		self::invalidate('search');
	}

	/**
	 * キャッシュ統計情報を取得
	 */
	public static function getStats(): array {
		$dir = self::CACHE_DIR;
		if (!is_dir($dir)) return ['files' => 0, 'size' => 0];

		$files = glob($dir . '/*.json') ?: [];
		$size = 0;
		foreach ($files as $f) {
			$size += filesize($f) ?: 0;
		}
		return [
			'files' => count($files),
			'size'  => $size,
			'size_human' => self::humanSize($size),
		];
	}

	/** 全キャッシュクリア */
	public static function clear(): void {
		self::invalidate('');
	}

	private static function cacheKey(string $endpoint, array $params): string {
		ksort($params);
		return $endpoint . '_' . md5(json_encode($params));
	}

	private static function cachePath(string $key): string {
		return self::CACHE_DIR . '/' . $key . '.json';
	}

	private static function clearDir(string $dir): void {
		$files = glob($dir . '/*.json') ?: [];
		foreach ($files as $f) {
			@unlink($f);
		}
	}

	private static function humanSize(int $bytes): string {
		if ($bytes < 1024) return $bytes . ' B';
		if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
		return round($bytes / 1048576, 1) . ' MB';
	}
}
