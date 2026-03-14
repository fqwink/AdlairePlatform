<?php
/**
 * LoggerTest - Logger エンジンのテスト
 *
 * ログレベルフィルタ、ファイル出力、ローテーション。
 */
class LoggerTest extends TestCase {

	protected function setUp(): void {
		/* テスト用にDEBUGレベルで再初期化 */
		Logger::init(Logger::DEBUG, 'data/logs');
	}

	/* ═══ ログレベルフィルタ ═══ */

	public function testLogLevelConstants(): void {
		$this->assertEquals('DEBUG', Logger::DEBUG);
		$this->assertEquals('INFO', Logger::INFO);
		$this->assertEquals('WARNING', Logger::WARNING);
		$this->assertEquals('ERROR', Logger::ERROR);
	}

	/* ═══ ファイル出力 ═══ */

	public function testInfoLogCreatesFile(): void {
		Logger::info('テストログ出力', ['test' => true]);
		$logFile = 'data/logs/ap-' . date('Y-m-d') . '.log';
		$this->assertFileExists($logFile);
	}

	public function testLogContainsMessage(): void {
		Logger::info('検索テスト文字列_xyz');
		$logFile = 'data/logs/ap-' . date('Y-m-d') . '.log';
		$content = file_get_contents($logFile);
		$this->assertContains('検索テスト文字列_xyz', $content);
	}

	public function testLogContainsLevel(): void {
		Logger::warning('警告テスト');
		$logFile = 'data/logs/ap-' . date('Y-m-d') . '.log';
		$content = file_get_contents($logFile);
		$this->assertContains('"WARNING"', $content);
	}

	public function testLogContainsContext(): void {
		Logger::error('エラーテスト', ['file' => 'test.json']);
		$logFile = 'data/logs/ap-' . date('Y-m-d') . '.log';
		$content = file_get_contents($logFile);
		$this->assertContains('test.json', $content);
	}

	public function testLogIsValidJson(): void {
		Logger::info('JSON検証テスト');
		$logFile = 'data/logs/ap-' . date('Y-m-d') . '.log';
		$lines = array_filter(explode("\n", file_get_contents($logFile)));
		$lastLine = end($lines);
		$decoded = json_decode($lastLine, true);
		$this->assertNotNull($decoded, 'ログ行が有効なJSONであること');
		$this->assertArrayHasKey('time', $decoded);
		$this->assertArrayHasKey('level', $decoded);
		$this->assertArrayHasKey('message', $decoded);
	}

	/* ═══ レベルフィルタリング ═══ */

	public function testDebugLogFilteredWhenMinLevelInfo(): void {
		Logger::init(Logger::INFO, 'data/logs');
		/* まずログファイルをクリア */
		$logFile = 'data/logs/ap-' . date('Y-m-d') . '.log';
		if (file_exists($logFile)) @unlink($logFile);

		Logger::debug('このメッセージは出力されないはず');

		if (file_exists($logFile)) {
			$content = file_get_contents($logFile);
			$this->assertNotContains('このメッセージは出力されないはず', $content);
		}
		/* ファイルが作られない場合もOK */
		$this->assertTrue(true);
	}

	/* ═══ クリーンアップ ═══ */

	public function testCleanupReturnsCount(): void {
		/* 古いログファイルをシミュレート */
		$oldFile = 'data/logs/ap-2020-01-01.log';
		file_put_contents($oldFile, 'old log');
		touch($oldFile, time() - (31 * 86400));

		$deleted = Logger::cleanup(30);
		$this->assertGreaterThan(0, $deleted);
		$this->assertFileNotExists($oldFile);
	}
}
