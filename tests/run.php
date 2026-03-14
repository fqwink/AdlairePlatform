<?php
/**
 * CLIテストランナー
 *
 * 使用: php tests/run.php [フィルタ]
 *
 * 例:
 *   php tests/run.php              # 全テスト実行
 *   php tests/run.php Logger       # LoggerTest のみ
 *   php tests/run.php Index,Logger # 複数指定
 */

/* ── ブートストラップ ── */
require __DIR__ . '/bootstrap.php';

/* ── テストファイル自動検出 ── */
$filter = $argv[1] ?? '';
$filters = $filter !== '' ? array_map('trim', explode(',', $filter)) : [];

$testFiles = glob(__DIR__ . '/*Test.php');
if (empty($testFiles)) {
	echo "\033[33mNo test files found in " . __DIR__ . "\033[0m\n";
	exit(0);
}

/* フィルタ適用 */
if (!empty($filters)) {
	$testFiles = array_filter($testFiles, function ($file) use ($filters) {
		$basename = basename($file, '.php');
		foreach ($filters as $f) {
			if (stripos($basename, $f) !== false) return true;
		}
		return false;
	});
}

/* ── テスト実行 ── */
$totalPassed  = 0;
$totalFailed  = 0;
$totalErrors  = 0;
$allFailures  = [];
$startTime    = microtime(true);

echo "\n\033[1mAdlairePlatform Test Runner\033[0m\n";
echo str_repeat('=', 50) . "\n\n";

foreach ($testFiles as $file) {
	require_once $file;

	/* ファイル内のテストクラスを特定 */
	$className = basename($file, '.php');
	if (!class_exists($className)) {
		echo "\033[33mSkip: {$className} (class not found)\033[0m\n";
		continue;
	}

	$ref = new ReflectionClass($className);
	if ($ref->isAbstract() || !$ref->isSubclassOf(TestCase::class)) {
		continue;
	}

	echo "\033[1m{$className}\033[0m ";

	/** @var TestCase $instance */
	$instance = new $className();
	$result = $instance->runAll();

	$totalPassed += $result['passed'];
	$totalFailed += $result['failed'];
	$totalErrors += $result['errors'];

	if (!empty($result['failures'])) {
		foreach ($result['failures'] as $f) {
			$allFailures[] = ['class' => $className] + $f;
		}
	}

	$count = $result['passed'] + $result['failed'] + $result['errors'];
	echo " ({$count} tests)\n";
}

$elapsed = round((microtime(true) - $startTime) * 1000, 1);

/* ── 結果レポート ── */
echo "\n" . str_repeat('=', 50) . "\n";

if (!empty($allFailures)) {
	echo "\n\033[31mFailures / Errors:\033[0m\n\n";
	foreach ($allFailures as $i => $f) {
		$n = $i + 1;
		$icon = $f['type'] === 'ERROR' ? 'E' : 'F';
		echo "  \033[31m{$n}) [{$icon}] {$f['class']}::{$f['test']}\033[0m\n";
		echo "     {$f['message']}\n\n";
	}
}

$total = $totalPassed + $totalFailed + $totalErrors;
$color = ($totalFailed + $totalErrors) > 0 ? "\033[31m" : "\033[32m";

echo "\n{$color}Tests: {$total}, Passed: {$totalPassed}, Failed: {$totalFailed}, Errors: {$totalErrors}\033[0m";
echo " ({$elapsed}ms)\n\n";

exit(($totalFailed + $totalErrors) > 0 ? 1 : 0);
