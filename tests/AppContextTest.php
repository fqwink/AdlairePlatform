<?php
/**
 * AppContextTest - アプリケーション状態管理のテスト
 *
 * config/defaults アクセサ、フック管理、グローバル同期。
 */
class AppContextTest extends TestCase {

	/* ═══ config アクセサ ═══ */

	public function testConfigReturnsDefault(): void {
		$this->assertNull(AppContext::config('nonexistent_key_xyz'));
	}

	public function testConfigReturnsCustomDefault(): void {
		$this->assertEquals('fallback', AppContext::config('missing_key', 'fallback'));
	}

	/* ═══ defaults アクセサ ═══ */

	public function testDefaultsReturnsDefault(): void {
		$this->assertNull(AppContext::defaults('page', 'nonexistent'));
	}

	public function testDefaultsReturnsCustomDefault(): void {
		$this->assertEquals('fb', AppContext::defaults('page', 'missing', 'fb'));
	}

	/* ═══ host ═══ */

	public function testHostReturnsString(): void {
		$host = AppContext::host();
		$this->assertTrue(is_string($host));
	}

	/* ═══ フック管理 ═══ */

	public function testAddAndGetHook(): void {
		AppContext::addHook('test_hook', '<script>test.js</script>');
		$hooks = AppContext::getHooks('test_hook');
		$this->assertNotEmpty($hooks);
		$this->assertContains('<script>test.js</script>', $hooks);
	}

	public function testGetHooksEmptyForUnregistered(): void {
		$hooks = AppContext::getHooks('unregistered_hook_xyz');
		$this->assertEquals([], $hooks);
	}

	public function testMultipleHooksSameSlot(): void {
		AppContext::addHook('multi_hook', 'first');
		AppContext::addHook('multi_hook', 'second');
		$hooks = AppContext::getHooks('multi_hook');
		$this->assertCount(2, $hooks);
	}

	/* ═══ syncFromGlobals ═══ */

	public function testSyncFromGlobals(): void {
		$c = ['title' => 'テストサイト', 'password' => 'admin'];
		$d = ['page' => ['home' => 'ホーム']];
		$host = 'http://localhost';
		$lstatus = '<a>Login</a>';
		$apcredit = 'Powered by AP';
		$hook = ['head' => ['<meta>']];

		AppContext::syncFromGlobals($c, $d, $host, $lstatus, $apcredit, $hook);

		$this->assertEquals('テストサイト', AppContext::config('title'));
		$this->assertEquals('ホーム', AppContext::defaults('page', 'home'));
		$this->assertEquals('http://localhost', AppContext::host());
		$this->assertEquals('<a>Login</a>', AppContext::loginStatus());
		$this->assertEquals('Powered by AP', AppContext::credit());
		$this->assertEquals(['<meta>'], AppContext::getHooks('head'));
	}
}
