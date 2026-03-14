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
		CacheEngine::store('test/endpoint', ['id' => '1'], '{"data": "hello"}');
		$stats = CacheEngine::getStats();
		$this->assertGreaterThan(0, $stats['file_count'] ?? 0);
	}

	public function testGetStatsStructure(): void {
		$stats = CacheEngine::getStats();
		$this->assertArrayHasKey('file_count', $stats);
		$this->assertArrayHasKey('total_size', $stats);
	}

	/* ═══ clear ═══ */

	public function testClearRemovesAllCache(): void {
		CacheEngine::store('clear/test', [], '{"test": true}');
		CacheEngine::clear();
		$stats = CacheEngine::getStats();
		$this->assertEquals(0, $stats['file_count']);
	}

	/* ═══ invalidate ═══ */

	public function testInvalidateSpecificEndpoint(): void {
		CacheEngine::store('endpoint/a', [], '{"a": 1}');
		CacheEngine::store('endpoint/b', [], '{"b": 2}');
		CacheEngine::invalidate('endpoint/a');
		/* endpoint/b はまだ残っているはず */
		$stats = CacheEngine::getStats();
		$this->assertGreaterThan(0, $stats['file_count'] ?? 0);
	}

	public function testInvalidateAllEndpoints(): void {
		CacheEngine::store('all/1', [], '{"a": 1}');
		CacheEngine::store('all/2', [], '{"b": 2}');
		CacheEngine::invalidate();
		$stats = CacheEngine::getStats();
		$this->assertEquals(0, $stats['file_count']);
	}
}
