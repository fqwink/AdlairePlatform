<?php
/**
 * CacheEngine - ファイルベース API レスポンスキャッシュ（後方互換シム）
 *
 * Ver.1.8: 全ロジックを AIS\System\ApiCache に移植。
 * このファイルは後方互換のためのシムとして残存。
 * Stage 8 で削除予定。
 *
 * @deprecated Ver.1.8 — AIS\System\ApiCache を使用してください
 */
class CacheEngine {

	/** @var \AIS\System\CacheStore|null Ver.1.5 Framework キャッシュストア */
	private static ?\AIS\System\CacheStore $store = null;

	/**
	 * キャッシュからレスポンスを返す。ヒットすれば true（echo+exit）、ミスなら false。
	 */
	public static function serve(string $endpoint, array $params = []): bool {
		$response = \AIS\System\ApiCache::serve($endpoint, $params);
		if ($response === null) return false;
		$response->send();
		exit;
	}

	public static function store(string $endpoint, array $params, string $content): void {
		\AIS\System\ApiCache::store($endpoint, $params, $content);
	}

	public static function invalidate(string $endpoint = ''): void {
		\AIS\System\ApiCache::invalidate($endpoint);
	}

	public static function invalidateContent(): void {
		\AIS\System\ApiCache::invalidateContent();
	}

	public static function getStats(): array {
		return \AIS\System\ApiCache::getStats();
	}

	public static function clear(): void {
		\AIS\System\ApiCache::clear();
	}

	public static function remember(string $key, int $ttl, callable $callback): mixed {
		return \AIS\System\ApiCache::remember($key, $ttl, $callback);
	}

	/**
	 * Ver.1.5: Framework CacheStore インスタンスを取得する
	 */
	public static function getStore(): \AIS\System\CacheStore {
		if (self::$store === null) {
			self::$store = new \AIS\System\CacheStore('data/cache/api');
		}
		return self::$store;
	}
}
