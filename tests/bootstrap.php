<?php
/**
 * テスト環境ブートストラップ
 *
 * テスト実行前に呼び出され、エンジン群を読み込み、
 * グローバル状態をテスト用に初期化する。
 *
 * 使用: require __DIR__ . '/bootstrap.php';
 */

define('AP_TESTING', true);
define('AP_VERSION', '1.4.0-test');
define('AP_UPDATE_URL', 'https://api.github.com/repos/test/test/releases/latest');
define('AP_BACKUP_GENERATIONS', 5);
define('AP_REVISION_LIMIT', 30);

/* ── テスト用一時ディレクトリ ── */
$_AP_TEST_DIR = sys_get_temp_dir() . '/ap_test_' . getmypid() . '_' . mt_rand();
mkdir($_AP_TEST_DIR, 0755, true);
mkdir($_AP_TEST_DIR . '/data', 0755, true);
mkdir($_AP_TEST_DIR . '/data/settings', 0755, true);
mkdir($_AP_TEST_DIR . '/data/content', 0755, true);
mkdir($_AP_TEST_DIR . '/data/logs', 0755, true);

/* テスト終了時にクリーンアップ */
register_shutdown_function(function () use ($_AP_TEST_DIR) {
	if (is_dir($_AP_TEST_DIR)) {
		$it = new RecursiveDirectoryIterator($_AP_TEST_DIR, RecursiveDirectoryIterator::SKIP_DOTS);
		$files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
		foreach ($files as $file) {
			$file->isDir() ? @rmdir($file->getRealPath()) : @unlink($file->getRealPath());
		}
		@rmdir($_AP_TEST_DIR);
	}
});

/* ── グローバル状態の初期化 ── */
$_SESSION = [];
$_POST    = [];
$_GET     = [];
$_SERVER['REMOTE_ADDR']  = '127.0.0.1';
$_SERVER['REQUEST_URI']  = '/';
$_SERVER['HTTP_HOST']    = 'localhost';
$_SERVER['SERVER_NAME']  = 'localhost';

/* テスト用作業ディレクトリ（data_dir 等がテンポラリを参照するように） */
chdir($_AP_TEST_DIR);

/* ── index.php から必要なユーティリティ関数を抽出定義 ── */

/** JSON キャッシュ（index.php の JsonCache クラス相当） */
class JsonCache {
	private static array $store = [];
	public static function get(string $path): ?array { return self::$store[$path] ?? null; }
	public static function set(string $path, array $data): void { self::$store[$path] = $data; }
	public static function clear(): void { self::$store = []; }
}

function h(string $s): string {
	return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function getSlug(string $p): string {
	$slug = mb_convert_case(str_replace(' ', '-', $p), MB_CASE_LOWER, 'UTF-8');
	$slug = str_replace("\0", '', $slug);
	do {
		$prev = $slug;
		$slug = str_replace(['../', '..\\'], '', $slug);
	} while ($slug !== $prev);
	$slug = preg_replace('#/+#', '/', $slug);
	return ltrim($slug, '/');
}

function data_dir(): string {
	$dir = 'data';
	if (!is_dir($dir)) @mkdir($dir, 0755, true);
	return $dir;
}

function settings_dir(): string {
	$dir = 'data/settings';
	if (!is_dir($dir)) @mkdir($dir, 0755, true);
	return $dir;
}

function content_dir(): string {
	$dir = 'data/content';
	if (!is_dir($dir)) @mkdir($dir, 0755, true);
	return $dir;
}

function json_read(string $file, string $dir = ''): array {
	$path = ($dir ?: data_dir()) . '/' . $file;
	$cached = JsonCache::get($path);
	if ($cached !== null) return $cached;
	if (!file_exists($path)) return [];
	$raw = file_get_contents($path);
	if ($raw === false) return [];
	$decoded = json_decode($raw, true);
	$result = is_array($decoded) ? $decoded : [];
	JsonCache::set($path, $result);
	return $result;
}

function json_write(string $file, array $data, string $dir = ''): void {
	$path = ($dir ?: data_dir()) . '/' . $file;
	$result = file_put_contents(
		$path,
		json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
		LOCK_EX
	);
	if ($result === false) {
		throw new RuntimeException("json_write failed: {$file}");
	}
	JsonCache::set($path, $data);
}

/* ── エンジン読み込み ── */
$engineDir = dirname(__DIR__) . '/engines';

require $engineDir . '/EngineTrait.php';
require $engineDir . '/FileSystem.php';
require $engineDir . '/AppContext.php';
require $engineDir . '/Logger.php';
require $engineDir . '/AdminEngine.php';
require $engineDir . '/CacheEngine.php';
require $engineDir . '/MarkdownEngine.php';
require $engineDir . '/CollectionEngine.php';
require $engineDir . '/DiagnosticEngine.php';
require $engineDir . '/WebhookEngine.php';
require $engineDir . '/TemplateEngine.php';
require $engineDir . '/Validator.php';

/* Logger をテスト用ディレクトリで初期化 */
Logger::init(Logger::DEBUG, 'data/logs');

/* TestCase クラス読み込み */
require __DIR__ . '/TestCase.php';
