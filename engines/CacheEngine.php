<?php
/**
 * CacheEngine - ファイルベース API レスポンスキャッシュ
 *
 * API レスポンスをファイルキャッシュし、高負荷時のパフォーマンスを改善。
 * コンテンツ変更時にキャッシュを自動無効化。
 * ETag / Last-Modified ヘッダー対応。
 *
 * Ver.1.5: AIS\System\CacheStore に内部委譲。既存 static API は完全維持。
 */
class CacheEngine {

	private const CACHE_DIR = 'data/cache/api';

	/** @var \AIS\System\CacheStore|null Ver.1.5 Framework キャッシュストア */
	private static ?\AIS\System\CacheStore $store = null;

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

		/* M14 fix: TOCTOU 回避 — FileSystem::read で一括読込 */
		$content = FileSystem::read($path);
		if ($content === false) return false;

		$ttl = self::TTL[$endpoint] ?? 60;
		$mtime = filemtime($path);
		if ($mtime === false || (time() - $mtime) > $ttl) {
			if (!@unlink($path) && class_exists('DiagnosticEngine')) {
				DiagnosticEngine::log('engine', 'キャッシュ期限切れファイル削除失敗', ['endpoint' => $endpoint, 'path' => basename($path)]);
			}
			return false;
		}

		/* M15 fix: コンテンツベースの ETag（mtime ではなくハッシュ） */
		$etag = '"' . hash('xxh128', $content) . '"';
		$lastModified = gmdate('D, d M Y H:i:s', $mtime) . ' GMT';

		/* 304 Not Modified チェック */
		$ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
		$ifModifiedSince = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '';

		if ($ifNoneMatch !== '' && $ifNoneMatch === $etag) {
			/* M16 fix: 304 レスポンスにも ETag / Cache-Control ヘッダーを含める */
			http_response_code(304);
			header('ETag: ' . $etag);
			header('Cache-Control: max-age=' . $ttl . ', must-revalidate');
			exit;
		}
		if ($ifModifiedSince !== '' && strtotime($ifModifiedSince) >= $mtime) {
			http_response_code(304);
			header('ETag: ' . $etag);
			header('Last-Modified: ' . $lastModified);
			header('Cache-Control: max-age=' . $ttl . ', must-revalidate');
			exit;
		}

		header('Content-Type: application/json; charset=UTF-8');
		header('ETag: ' . $etag);
		header('Last-Modified: ' . $lastModified);
		header('Cache-Control: max-age=' . $ttl . ', must-revalidate');
		header('X-Cache: HIT');
		if (class_exists('DiagnosticEngine')) DiagnosticEngine::log('performance', 'キャッシュヒット', ['endpoint' => $endpoint, 'age_sec' => time() - $mtime]);
		echo $content;
		exit;
	}

	/**
	 * レスポンスをキャッシュに保存
	 */
	public static function store(string $endpoint, array $params, string $content): void {
		$key = self::cacheKey($endpoint, $params);
		$path = self::cachePath($key);
		if (!FileSystem::write($path, $content)) {
			if (class_exists('DiagnosticEngine')) {
				DiagnosticEngine::log('engine', 'CacheEngine 書き込み失敗', ['endpoint' => $endpoint]);
			}
		}
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

	/**
	 * Ver.1.5: Framework CacheStore インスタンスを取得する
	 *
	 * API キャッシュ以外の汎用キャッシュに使用可能。
	 */
	public static function getStore(): \AIS\System\CacheStore {
		if (self::$store === null) {
			self::$store = new \AIS\System\CacheStore(self::CACHE_DIR);
		}
		return self::$store;
	}

	private static function cacheKey(string $endpoint, array $params): string {
		/* R18 fix: エンドポイント名をサニタイズ（パストラバーサル防止） */
		$sanitized = preg_replace('/[^a-zA-Z0-9_\-]/', '', $endpoint);
		if ($sanitized !== $endpoint && class_exists('DiagnosticEngine')) {
			DiagnosticEngine::log('security', 'CacheEngine パストラバーサル試行検出', ['endpoint' => $sanitized]);
		}
		$endpoint = $sanitized;
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
