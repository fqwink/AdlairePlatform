<?php
/**
 * FileSystemTest - FileSystem 抽象化レイヤーのテスト
 *
 * read/write/readJson/writeJson/ensureDir/delete の動作検証。
 */
class FileSystemTest extends TestCase {

	private string $testDir;

	protected function setUp(): void {
		$this->testDir = 'data/fs_test_' . mt_rand();
		@mkdir($this->testDir, 0755, true);
	}

	protected function tearDown(): void {
		/* テストディレクトリをクリーンアップ */
		if (is_dir($this->testDir)) {
			$files = glob($this->testDir . '/*');
			if ($files) {
				foreach ($files as $f) @unlink($f);
			}
			@rmdir($this->testDir);
		}
	}

	/* ═══ read / write ═══ */

	public function testWriteAndRead(): void {
		$path = $this->testDir . '/test.txt';
		$result = FileSystem::write($path, 'hello world');
		$this->assertTrue($result);
		$this->assertEquals('hello world', FileSystem::read($path));
	}

	public function testReadNonExistentFile(): void {
		$result = FileSystem::read($this->testDir . '/nonexistent.txt');
		$this->assertFalse($result);
	}

	public function testWriteCreatesParentDir(): void {
		$path = $this->testDir . '/sub/dir/file.txt';
		$result = FileSystem::write($path, 'nested');
		$this->assertTrue($result);
		$this->assertEquals('nested', FileSystem::read($path));
		/* クリーンアップ */
		@unlink($path);
		@rmdir($this->testDir . '/sub/dir');
		@rmdir($this->testDir . '/sub');
	}

	public function testWriteUnicode(): void {
		$path = $this->testDir . '/unicode.txt';
		$content = 'テスト日本語コンテンツ 🎉';
		FileSystem::write($path, $content);
		$this->assertEquals($content, FileSystem::read($path));
	}

	/* ═══ readJson / writeJson ═══ */

	public function testWriteJsonAndReadJson(): void {
		$path = $this->testDir . '/data.json';
		$data = ['name' => 'テスト', 'count' => 42, 'active' => true];
		$result = FileSystem::writeJson($path, $data);
		$this->assertTrue($result);
		$read = FileSystem::readJson($path);
		$this->assertNotNull($read);
		$this->assertEquals('テスト', $read['name']);
		$this->assertEquals(42, $read['count']);
		$this->assertTrue($read['active']);
	}

	public function testReadJsonNonExistentFile(): void {
		$result = FileSystem::readJson($this->testDir . '/missing.json');
		$this->assertNull($result);
	}

	public function testReadJsonInvalidContent(): void {
		$path = $this->testDir . '/invalid.json';
		FileSystem::write($path, 'not json content {{{');
		$result = FileSystem::readJson($path);
		$this->assertNull($result);
	}

	public function testWriteJsonPrettyPrinted(): void {
		$path = $this->testDir . '/pretty.json';
		FileSystem::writeJson($path, ['a' => 1]);
		$raw = FileSystem::read($path);
		/* JSON_PRETTY_PRINT は改行を含む */
		$this->assertContains("\n", $raw);
	}

	/* ═══ exists / delete ═══ */

	public function testExistsForExistingFile(): void {
		$path = $this->testDir . '/exists.txt';
		FileSystem::write($path, 'data');
		$this->assertTrue(FileSystem::exists($path));
	}

	public function testExistsForMissingFile(): void {
		$this->assertFalse(FileSystem::exists($this->testDir . '/missing.txt'));
	}

	public function testDeleteExistingFile(): void {
		$path = $this->testDir . '/to_delete.txt';
		FileSystem::write($path, 'delete me');
		$this->assertTrue(FileSystem::delete($path));
		$this->assertFalse(FileSystem::exists($path));
	}

	public function testDeleteNonExistentFile(): void {
		/* 存在しないファイルの削除は true を返す */
		$this->assertTrue(FileSystem::delete($this->testDir . '/not_here.txt'));
	}

	/* ═══ ensureDir ═══ */

	public function testEnsureDirCreatesDirectory(): void {
		$dir = $this->testDir . '/new_dir';
		$this->assertTrue(FileSystem::ensureDir($dir));
		$this->assertTrue(is_dir($dir));
		@rmdir($dir);
	}

	public function testEnsureDirExistingDirectory(): void {
		/* 既存ディレクトリでも true */
		$this->assertTrue(FileSystem::ensureDir($this->testDir));
	}

	public function testEnsureDirNestedCreation(): void {
		$dir = $this->testDir . '/a/b/c';
		$this->assertTrue(FileSystem::ensureDir($dir));
		$this->assertTrue(is_dir($dir));
		@rmdir($this->testDir . '/a/b/c');
		@rmdir($this->testDir . '/a/b');
		@rmdir($this->testDir . '/a');
	}
}
