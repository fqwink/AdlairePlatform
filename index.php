<?php
/**
*
*@copyright Copyright (c) 2014 - 2026 Adlaire Group
*@license Adlaire License Ver.2.0
*
*/

if (PHP_VERSION_ID < 80200) {
	http_response_code(500);
	header('Content-Type: text/plain; charset=UTF-8');
	exit('AdlairePlatform requires PHP 8.2 or later. Current version: ' . PHP_VERSION);
}

define('AP_VERSION', '1.6.0');
define('AP_UPDATE_URL', 'https://api.github.com/repos/win-k/AdlairePlatform/releases/latest');
define('AP_BACKUP_GENERATIONS', 5);
define('AP_REVISION_LIMIT', 30);

/* ── Ver.1.5: Framework オートローダー & ブートストラップ ── */
require __DIR__ . '/autoload.php';
require __DIR__ . '/bootstrap.php';

/* ── ユーティリティ関数（Bridge.php に集約） ── */
require __DIR__ . '/engines/Bridge.php';

/* ── エンジン読み込み ── */
require 'engines/EngineTrait.php';
require 'engines/FileSystem.php';
require 'engines/AppContext.php';
require 'engines/Logger.php';
require 'engines/I18n.php';
require 'engines/TemplateEngine.php';
require 'engines/ThemeEngine.php';
require 'engines/UpdateEngine.php';
require 'engines/AdminEngine.php';
require 'engines/StaticEngine.php';
require 'engines/ApiEngine.php';
require 'engines/MarkdownEngine.php';
require 'engines/CollectionEngine.php';
require 'engines/GitEngine.php';
require 'engines/WebhookEngine.php';
require 'engines/CacheEngine.php';
require 'engines/ImageOptimizer.php';
require 'engines/MailerEngine.php';
require 'engines/DiagnosticEngine.php';

ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');
/* Ver.1.6: セッションタイムアウト（30分） */
ini_set('session.gc_maxlifetime', 1800);
ini_set('session.cookie_lifetime', 0); /* ブラウザ閉じで消去 */
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
	ini_set('session.cookie_secure', 1);
}
session_start();

/* Ver.1.6: アイドルタイムアウト検証（30分非操作で自動ログアウト） */
if (isset($_SESSION['l']) && $_SESSION['l'] === true) {
	$_SESSION['ap_last_activity'] = $_SESSION['ap_last_activity'] ?? time();
	if (time() - $_SESSION['ap_last_activity'] > 1800) {
		$_SESSION = [];
		if (ini_get('session.use_cookies')) {
			$p = session_get_cookie_params();
			setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
		}
		session_destroy();
		session_start();
	} else {
		$_SESSION['ap_last_activity'] = time();
	}
}

/* i18n 初期化（セッション開始後） */
I18n::init();

/* B-6 fix: 集中ログ管理の初期化 */
Logger::init();
/* 診断エンジン: エラーハンドラ登録（セッション開始後） */
DiagnosticEngine::registerErrorHandler();
DiagnosticEngine::startTimer('request_total');

/* セキュリティヘッダー */
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
migrate_from_files();
host();
DiagnosticEngine::startTimer('AdminEngine');
AdminEngine::handle();       /* edit_field, upload_image, revision 等 */
DiagnosticEngine::stopTimer('AdminEngine');
DiagnosticEngine::startTimer('ApiEngine');
ApiEngine::handle();         /* ?ap_api= 公開REST API（認証不要） */
DiagnosticEngine::stopTimer('ApiEngine');
DiagnosticEngine::startTimer('CollectionEngine');
CollectionEngine::handle();  /* collection_create, collection_item_save 等 */
DiagnosticEngine::stopTimer('CollectionEngine');
DiagnosticEngine::startTimer('GitEngine');
GitEngine::handle();         /* git_configure, git_pull, git_push 等 */
DiagnosticEngine::stopTimer('GitEngine');
DiagnosticEngine::startTimer('WebhookEngine');
WebhookEngine::handle();     /* webhook_add, webhook_delete, webhook_toggle 等 */
DiagnosticEngine::stopTimer('WebhookEngine');
DiagnosticEngine::startTimer('StaticEngine');
StaticEngine::handle();      /* generate_static_*, clean_static, build_zip 等 */
DiagnosticEngine::stopTimer('StaticEngine');
DiagnosticEngine::startTimer('UpdateEngine');
UpdateEngine::handle();      /* update, backup, rollback 等 */
DiagnosticEngine::stopTimer('UpdateEngine');
DiagnosticEngine::handle();  /* diag_set_enabled, diag_preview, diag_send_now 等 */

$c['password'] = 'admin';
$c['loggedin'] = false;
$c['page'] = 'home';
$d['page']['home'] = "<h3>Your website is now powered by Adlaire Platform.</h3><br />\nLogin with the 'Login' link below. The password is admin.<br />\nChange the password as soon as possible.<br /><br />\n\nClick on the content to edit and click outside to save it.<br />";
$d['page']['example'] = "This is an example page.<br /><br />\n\nTo add a new one, click on the existing pages (in the admin panel) and enter a new one below the others.";
$d['new_page']['admin'] = "Page <b>".h($rp)."</b> created.<br /><br />\n\nClick here to start editing!";
$d['new_page']['visitor'] = "Sorry, but <b>".h($rp)."</b> doesn't exist. :(";
$d['default']['content'] = 'Click to edit!';
$c['themeSelect'] = 'AP-Default';
$c['menu'] = "Home<br />\nExample";
$c['title'] = 'Website title';
$c['subside'] = "<h3>ABOUT YOUR WEBSITE</h3><br />\n\n This content is static and is visible on all pages.";
$c['description'] = 'Your website description.';
$c['keywords'] = 'enter, your website, keywords';
$c['copyright'] = '&copy;'.date('Y').' Your website';
$apcredit = "Powered by <a href=''>Adlaire Platform</a>";

$_settings = json_read('settings.json', settings_dir());
$_auth     = json_read('auth.json', settings_dir());
$_pages    = json_read('pages.json', content_dir());

/* コレクションモード: Markdown → HTML 変換済みページをマージ */
if (class_exists('CollectionEngine') && CollectionEngine::isEnabled()) {
	$_collectionPages = CollectionEngine::loadAllAsPages();
	foreach ($_collectionPages as $_cpSlug => $_cpHtml) {
		if (!isset($_pages[$_cpSlug])) {
			$_pages[$_cpSlug] = $_cpHtml;
		}
	}
	unset($_collectionPages, $_cpSlug, $_cpHtml);
}

foreach($c as $key => $val){
	if($key == 'content') continue;
	$d['default'][$key] = $c[$key];
	if(isset($_settings[$key]))
		$c[$key] = $_settings[$key];
	switch($key){
		case 'password':
			if(empty($_auth['password_hash'])){
				$c[$key] = AdminEngine::savePassword($val);
			} elseif(strlen($_auth['password_hash']) === 32 && ctype_xdigit($_auth['password_hash'])){
				/* R2 fix: MD5ハッシュ検出 → デフォルトパスワード 'admin' で bcrypt 化（ログイン可能を維持） */
				$c[$key] = AdminEngine::savePassword('admin');
				$c['migrate_warning'] = true;
				Logger::warning('MD5パスワードを検出。デフォルト "admin" で bcrypt 化しました。直ちにパスワードを変更してください。');
				error_log('AdlairePlatform: MD5パスワードを検出。デフォルト "admin" で bcrypt 化しました。直ちにパスワードを変更してください。');
				DiagnosticEngine::log('security', 'MD5パスワード検出・bcrypt移行実行');
			} else {
				$c[$key] = $_auth['password_hash'];
			}
			/* デフォルトパスワード 'admin' が有効な場合の警告 */
			if (password_verify('admin', $c[$key])) {
				DiagnosticEngine::log('security', 'デフォルトパスワード使用中');
			}
			break;
		case 'loggedin':
			if(AdminEngine::isLoggedIn())
				$c[$key] = true;
			if(isset($_POST['logout'])){
				AdminEngine::verifyCsrf();
				$_SESSION = [];
				if(ini_get('session.use_cookies')){
					$p = session_get_cookie_params();
					setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
				}
				session_destroy();
				header('Location: ./');
				exit;
			}
			if(isset($_GET['login'])){
				if(AdminEngine::isLoggedIn()){
					header('Location: ./');
					exit;
				}
				$msg = '';
				if(isset($_POST['sub']))
					$msg = AdminEngine::login($c['password']);
				echo AdminEngine::renderLogin($msg);
				exit;
			}
			$logout_form = "<form method='POST' style='display:inline'>"
				."<input type='hidden' name='csrf' value='".AdminEngine::csrfToken()."'>"
				."<button type='submit' name='logout' value='1' style='background:none;border:none;cursor:pointer;padding:0;color:inherit;text-decoration:underline;font:inherit'>Logout</button>"
				."</form>";
			$admin_link = (AdminEngine::isLoggedIn()) ? " | <a href='?admin'>Dashboard</a>" : '';
			$lstatus = (AdminEngine::isLoggedIn()) ? $logout_form . $admin_link : "<a href='".h($host)."?login'>Login</a>";
			break;
		case 'page':
			if($rp)
				$c[$key] = $rp;
			$c[$key] = getSlug($c[$key]);
			if(isset($_GET['login'])) continue 2;
			$c['content'] = $_pages[$c[$key]] ?? null;
			if($c['content'] === null){
				if(!isset($d['page'][$c[$key]])){
					header('HTTP/1.1 404 Not Found');
					$c['content'] = (AdminEngine::isLoggedIn()) ? $d['new_page']['admin'] : $d['new_page']['visitor'];
				} else{
					$c['content'] = $d['page'][$c[$key]];
				}
			}
			break;
		default:
			break;
	}
}

/* B-3 fix: グローバル変数を AppContext に同期 */
AppContext::syncFromGlobals($c, $d, $host, $lstatus, $apcredit, $hook);

/* ダッシュボードルーティング: ?admin */
if (isset($_GET['admin'])) {
	if (!AdminEngine::isLoggedIn()) {
		header('Location: ./?login');
		exit;
	}
	echo AdminEngine::renderDashboard();
	exit;
}

AdminEngine::registerHooks();

/* 診断エンジン: リクエスト終了時にデータ収集・定期送信 */
DiagnosticEngine::stopTimer('request_total');
DiagnosticEngine::maybeSend();

ThemeEngine::load($c['themeSelect']);
?>
