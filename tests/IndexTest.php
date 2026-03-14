<?php
/**
 * IndexTest - index.php ユーティリティ関数のテスト
 *
 * h() エスケープ、getSlug() パストラバーサル防止、
 * json_read/json_write の往復テスト。
 */
class IndexTest extends TestCase {

	protected function setUp(): void {
		$this->clearJsonCache();
	}

	/* ═══ h() HTMLエスケープ ═══ */

	public function testHEscapesHtmlSpecialChars(): void {
		$this->assertEquals('&lt;script&gt;', h('<script>'));
		$this->assertEquals('&amp;', h('&'));
		$this->assertEquals('&quot;', h('"'));
		$this->assertEquals('&#039;', h("'"));
	}

	public function testHHandlesEmptyString(): void {
		$this->assertEquals('', h(''));
	}

	public function testHHandlesUnicode(): void {
		$this->assertEquals('テスト', h('テスト'));
	}

	public function testHDoubleEscapePrevention(): void {
		$escaped = h('<b>');
		$doubleEscaped = h($escaped);
		$this->assertEquals('&amp;lt;b&amp;gt;', $doubleEscaped);
	}

	/* ═══ getSlug() パストラバーサル防止 ═══ */

	public function testGetSlugBasic(): void {
		$this->assertEquals('hello-world', getSlug('Hello World'));
	}

	public function testGetSlugLowerCase(): void {
		$this->assertEquals('about-us', getSlug('About Us'));
	}

	public function testGetSlugRemovesPathTraversal(): void {
		$this->assertEquals('', getSlug('../'));
		$this->assertEquals('etc/passwd', getSlug('../etc/passwd'));
		$this->assertEquals('', getSlug('..\\'));
	}

	public function testGetSlugRemovesRecursiveTraversal(): void {
		/* ....// → ../ を再帰除去 */
		$this->assertEquals('etc/passwd', getSlug('....//etc/passwd'));
		$this->assertEquals('secret', getSlug('....//..//secret'));
	}

	public function testGetSlugRemovesNullBytes(): void {
		$this->assertEquals('test', getSlug("test\0"));
		$this->assertEquals('file.txt', getSlug("file\0.txt"));
	}

	public function testGetSlugNormalizesSlashes(): void {
		$this->assertEquals('a/b/c', getSlug('a//b///c'));
	}

	public function testGetSlugTrimsLeadingSlash(): void {
		$this->assertEquals('page', getSlug('/page'));
	}

	public function testGetSlugUnicode(): void {
		$result = getSlug('テスト ページ');
		$this->assertEquals('テスト-ページ', $result);
	}

	/* ═══ json_read / json_write 往復テスト ═══ */

	public function testJsonWriteAndRead(): void {
		$data = ['key' => 'value', 'num' => 42];
		json_write('test_rw.json', $data);
		$this->clearJsonCache();
		$read = json_read('test_rw.json');
		$this->assertEquals('value', $read['key']);
		$this->assertEquals(42, $read['num']);
	}

	public function testJsonReadNonExistentFile(): void {
		$result = json_read('nonexistent_file_xyz.json');
		$this->assertEquals([], $result);
	}

	public function testJsonReadCaching(): void {
		$data = ['cached' => true];
		json_write('cache_test.json', $data);
		/* キャッシュヒットを確認（2回目は file_get_contents を呼ばない） */
		$first = json_read('cache_test.json');
		$second = json_read('cache_test.json');
		$this->assertEquals($first, $second);
		$this->assertTrue($first['cached']);
	}

	public function testJsonWriteUpdatesCache(): void {
		json_write('update_cache.json', ['v' => 1]);
		$this->assertEquals(1, json_read('update_cache.json')['v']);
		json_write('update_cache.json', ['v' => 2]);
		/* キャッシュクリアなしでも最新値が取れる */
		$this->assertEquals(2, json_read('update_cache.json')['v']);
	}

	public function testJsonReadWithCustomDir(): void {
		$dir = settings_dir();
		json_write('custom_dir.json', ['dir' => 'settings'], $dir);
		$this->clearJsonCache();
		$result = json_read('custom_dir.json', $dir);
		$this->assertEquals('settings', $result['dir']);
	}
}
