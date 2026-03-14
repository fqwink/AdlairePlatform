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
define('AP_VERSION', '1.5.0-test');
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

/* ── Ver.1.5: Framework オートローダー ── */
require dirname(__DIR__) . '/autoload.php';

/* ── Bridge.php からユーティリティ関数を読み込み ── */
require dirname(__DIR__) . '/engines/Bridge.php';

/* ── エンジン読み込み ── */
$engineDir = dirname(__DIR__) . '/engines';

require $engineDir . '/EngineTrait.php';
require $engineDir . '/FileSystem.php';
require $engineDir . '/I18n.php';
require $engineDir . '/AppContext.php';
require $engineDir . '/Logger.php';
require $engineDir . '/AdminEngine.php';
require $engineDir . '/CacheEngine.php';
require $engineDir . '/MarkdownEngine.php';
require $engineDir . '/CollectionEngine.php';
require $engineDir . '/DiagnosticEngine.php';
require $engineDir . '/WebhookEngine.php';
require $engineDir . '/TemplateEngine.php';
require $engineDir . '/ThemeEngine.php';
require $engineDir . '/ApiEngine.php';
require $engineDir . '/StaticEngine.php';
require $engineDir . '/GitEngine.php';
require $engineDir . '/UpdateEngine.php';
require $engineDir . '/ImageOptimizer.php';
require $engineDir . '/MailerEngine.php';
require $engineDir . '/Validator.php';

/* Logger をテスト用ディレクトリで初期化 */
Logger::init(Logger::DEBUG, 'data/logs');

/* TestCase クラス読み込み */
require __DIR__ . '/TestCase.php';
