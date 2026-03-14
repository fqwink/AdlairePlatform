<?php
/**
 * TestCase - 軽量テスト基底クラス
 *
 * 外部依存ゼロのテストフレームワーク。
 * テストメソッドは test で始まる public メソッドとして定義する。
 *
 * 使用例:
 *   class MyTest extends TestCase {
 *       public function testSomething(): void {
 *           $this->assertEquals(1, 1);
 *       }
 *   }
 */
abstract class TestCase {

	/* ── 結果カウンタ ── */
	private int $passed = 0;
	private int $failed = 0;
	private int $errors = 0;
	/** @var array{test: string, message: string, type: string}[] */
	private array $failures = [];

	/* ── セットアップ / ティアダウン ── */

	/** 各テストメソッドの前に呼ばれる */
	protected function setUp(): void {}

	/** 各テストメソッドの後に呼ばれる */
	protected function tearDown(): void {}

	/** テストクラス全体の前に1回呼ばれる */
	protected static function setUpClass(): void {}

	/** テストクラス全体の後に1回呼ばれる */
	protected static function tearDownClass(): void {}

	/* ── テスト実行 ── */

	/**
	 * このクラスのすべてのテストメソッドを実行。
	 * @return array{passed: int, failed: int, errors: int, failures: array}
	 */
	public function runAll(): array {
		$this->passed = 0;
		$this->failed = 0;
		$this->errors = 0;
		$this->failures = [];

		$class = new ReflectionClass($this);
		$methods = $class->getMethods(ReflectionMethod::IS_PUBLIC);

		/* test* メソッドのみ抽出 */
		$tests = [];
		foreach ($methods as $m) {
			if (str_starts_with($m->getName(), 'test') && $m->getDeclaringClass()->getName() === static::class) {
				$tests[] = $m->getName();
			}
		}

		if (empty($tests)) {
			return ['passed' => 0, 'failed' => 0, 'errors' => 0, 'failures' => []];
		}

		static::setUpClass();

		foreach ($tests as $test) {
			$this->runSingleTest($test);
		}

		static::tearDownClass();

		return [
			'passed'   => $this->passed,
			'failed'   => $this->failed,
			'errors'   => $this->errors,
			'failures' => $this->failures,
		];
	}

	private function runSingleTest(string $method): void {
		try {
			$this->setUp();
			$this->$method();
			$this->passed++;
			echo '.';
		} catch (AssertionFailure $e) {
			$this->failed++;
			$this->failures[] = ['test' => $method, 'message' => $e->getMessage(), 'type' => 'FAIL'];
			echo 'F';
		} catch (\Throwable $e) {
			$this->errors++;
			$this->failures[] = [
				'test'    => $method,
				'message' => get_class($e) . ': ' . $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine(),
				'type'    => 'ERROR',
			];
			echo 'E';
		} finally {
			try {
				$this->tearDown();
			} catch (\Throwable $e) {
				/* tearDown のエラーは無視（テスト結果を汚さない） */
			}
		}
	}

	/* ── アサーションメソッド ── */

	protected function assertEquals(mixed $expected, mixed $actual, string $msg = ''): void {
		if ($expected !== $actual) {
			$msg = $msg ?: sprintf(
				"Expected %s, got %s",
				$this->export($expected),
				$this->export($actual)
			);
			throw new AssertionFailure($msg);
		}
	}

	protected function assertNotEquals(mixed $expected, mixed $actual, string $msg = ''): void {
		if ($expected === $actual) {
			$msg = $msg ?: sprintf("Expected value to differ from %s", $this->export($expected));
			throw new AssertionFailure($msg);
		}
	}

	protected function assertTrue(mixed $value, string $msg = ''): void {
		if ($value !== true) {
			throw new AssertionFailure($msg ?: sprintf("Expected true, got %s", $this->export($value)));
		}
	}

	protected function assertFalse(mixed $value, string $msg = ''): void {
		if ($value !== false) {
			throw new AssertionFailure($msg ?: sprintf("Expected false, got %s", $this->export($value)));
		}
	}

	protected function assertNull(mixed $value, string $msg = ''): void {
		if ($value !== null) {
			throw new AssertionFailure($msg ?: sprintf("Expected null, got %s", $this->export($value)));
		}
	}

	protected function assertNotNull(mixed $value, string $msg = ''): void {
		if ($value === null) {
			throw new AssertionFailure($msg ?: 'Expected non-null value');
		}
	}

	protected function assertEmpty(mixed $value, string $msg = ''): void {
		if (!empty($value)) {
			throw new AssertionFailure($msg ?: sprintf("Expected empty, got %s", $this->export($value)));
		}
	}

	protected function assertNotEmpty(mixed $value, string $msg = ''): void {
		if (empty($value)) {
			throw new AssertionFailure($msg ?: 'Expected non-empty value');
		}
	}

	protected function assertContains(mixed $needle, array|string $haystack, string $msg = ''): void {
		if (is_string($haystack)) {
			if (!str_contains($haystack, (string)$needle)) {
				throw new AssertionFailure($msg ?: sprintf("String does not contain %s", $this->export($needle)));
			}
		} else {
			if (!in_array($needle, $haystack, true)) {
				throw new AssertionFailure($msg ?: sprintf("Array does not contain %s", $this->export($needle)));
			}
		}
	}

	protected function assertNotContains(mixed $needle, array|string $haystack, string $msg = ''): void {
		if (is_string($haystack)) {
			if (str_contains($haystack, (string)$needle)) {
				throw new AssertionFailure($msg ?: sprintf("String unexpectedly contains %s", $this->export($needle)));
			}
		} else {
			if (in_array($needle, $haystack, true)) {
				throw new AssertionFailure($msg ?: sprintf("Array unexpectedly contains %s", $this->export($needle)));
			}
		}
	}

	protected function assertCount(int $expected, array|Countable $value, string $msg = ''): void {
		$actual = count($value);
		if ($expected !== $actual) {
			throw new AssertionFailure($msg ?: sprintf("Expected count %d, got %d", $expected, $actual));
		}
	}

	protected function assertInstanceOf(string $class, mixed $value, string $msg = ''): void {
		if (!($value instanceof $class)) {
			$actual = is_object($value) ? get_class($value) : gettype($value);
			throw new AssertionFailure($msg ?: sprintf("Expected instance of %s, got %s", $class, $actual));
		}
	}

	protected function assertMatchesRegex(string $pattern, string $value, string $msg = ''): void {
		if (!preg_match($pattern, $value)) {
			throw new AssertionFailure($msg ?: sprintf("Value does not match pattern %s", $pattern));
		}
	}

	protected function assertArrayHasKey(string|int $key, array $arr, string $msg = ''): void {
		if (!array_key_exists($key, $arr)) {
			throw new AssertionFailure($msg ?: sprintf("Array does not have key %s", $this->export($key)));
		}
	}

	protected function assertGreaterThan(int|float $expected, int|float $actual, string $msg = ''): void {
		if ($actual <= $expected) {
			throw new AssertionFailure($msg ?: sprintf("Expected value > %s, got %s", $expected, $actual));
		}
	}

	protected function assertFileExists(string $path, string $msg = ''): void {
		if (!file_exists($path)) {
			throw new AssertionFailure($msg ?: sprintf("File does not exist: %s", $path));
		}
	}

	protected function assertFileNotExists(string $path, string $msg = ''): void {
		if (file_exists($path)) {
			throw new AssertionFailure($msg ?: sprintf("File unexpectedly exists: %s", $path));
		}
	}

	/**
	 * テストが例外を投げることを検証。
	 */
	protected function assertThrows(string $exceptionClass, callable $fn, string $msg = ''): void {
		try {
			$fn();
			throw new AssertionFailure($msg ?: sprintf("Expected %s to be thrown", $exceptionClass));
		} catch (\Throwable $e) {
			if (!($e instanceof $exceptionClass) && !($e instanceof AssertionFailure)) {
				/* 異なる例外なら OK（期待通り例外が投げられた） */
				return;
			}
			if ($e instanceof AssertionFailure) {
				throw $e;
			}
		}
	}

	/* ── ヘルパー ── */

	private function export(mixed $v): string {
		if (is_null($v)) return 'null';
		if (is_bool($v)) return $v ? 'true' : 'false';
		if (is_string($v)) return '"' . substr($v, 0, 100) . '"';
		if (is_array($v)) return 'array(' . count($v) . ')';
		return (string)$v;
	}

	/**
	 * テスト用一時ファイルを作成。
	 */
	protected function createTempFile(string $filename, string $content, string $subdir = ''): string {
		$dir = $subdir ? ($subdir . '/' . dirname($filename)) : dirname($filename);
		if ($dir !== '.' && !is_dir($dir)) {
			@mkdir($dir, 0755, true);
		}
		$path = ($subdir ? $subdir . '/' : '') . $filename;
		file_put_contents($path, $content);
		return $path;
	}

	/**
	 * $_SESSION をリセット。
	 */
	protected function resetSession(): void {
		$_SESSION = [];
	}

	/**
	 * $_POST をリセット。
	 */
	protected function resetPost(): void {
		$_POST = [];
	}

	/**
	 * JsonCache をクリア。
	 */
	protected function clearJsonCache(): void {
		JsonCache::clear();
	}
}

/**
 * アサーション失敗例外
 */
class AssertionFailure extends \RuntimeException {}
