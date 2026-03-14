<?php
/**
 * CacheEngineTest - キャッシュ管理のテスト
 *
 * store/invalidate/clear/getStats。
 */
class CacheEngineTest extends TestCase {

	protected function setUp(): void {
		CacheEngine::clear();
	}

	/* ═══ store / getStats ═══ */

	public function testStoreCreatesCache(): void {
		CacheEngine::store('testendpoint', ['id' => '1'], '{"data": "hello"}');
		$stats = CacheEngine::getStats();
		$this->assertGreaterThan(0, $stats['files'] ?? 0);
	}

	public function testGetStatsStructure(): void {
		$stats = CacheEngine::getStats();
		$this->assertArrayHasKey('files', $stats);
		$this->assertArrayHasKey('size', $stats);
	}

	/* ═══ clear ═══ */

	public function testClearRemovesAllCache(): void {
		CacheEngine::store('cleartest', [], '{"test": true}');
		CacheEngine::clear();
		$stats = CacheEngine::getStats();
		$this->assertEquals(0, $stats['files']);
	}

	/* ═══ invalidate ═══ */

	public function testInvalidateSpecificEndpoint(): void {
		CacheEngine::store('endpointa', [], '{"a": 1}');
		CacheEngine::store('endpointb', [], '{"b": 2}');
		CacheEngine::invalidate('endpointa');
		/* endpointb はまだ残っているはず */
		$stats = CacheEngine::getStats();
		$this->assertGreaterThan(0, $stats['files'] ?? 0);
	}

	public function testInvalidateAllEndpoints(): void {
		CacheEngine::store('all1', [], '{"a": 1}');
		CacheEngine::store('all2', [], '{"b": 2}');
		CacheEngine::invalidate();
		$stats = CacheEngine::getStats();
		$this->assertEquals(0, $stats['files']);
	}
}
