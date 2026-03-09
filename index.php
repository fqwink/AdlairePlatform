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

define('AP_VERSION', '1.4.0');
define('AP_UPDATE_URL', 'https://api.github.com/repos/win-k/AdlairePlatform/releases/latest');
define('AP_BACKUP_GENERATIONS', 5);
define('AP_REVISION_LIMIT', 30);

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
require 'engines/DiagnosticEngine.php';

ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
	ini_set('session.cookie_secure', 1);
}
session_start();

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
handle_update_action();      /* update, backup, rollback 等 */
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
				error_log('AdlairePlatform: MD5パスワードを検出。デフォルト "admin" で bcrypt 化しました。直ちにパスワードを変更してください。');
				DiagnosticEngine::log('security', 'MD5パスワード検出・bcrypt移行実行');
			} else {
				$c[$key] = $_auth['password_hash'];
			}
			break;
		case 'loggedin':
			if(AdminEngine::isLoggedIn())
				$c[$key] = true;
			if(isset($_POST['logout'])){
				AdminEngine::verifyCsrf();
				$_SESSION = [];
				session_destroy();
				header('Location: ./');
				exit;
			}
			if(isset($_GET['login'])){
				if(is_loggedin()){
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
			$admin_link = (is_loggedin()) ? " | <a href='?admin'>Dashboard</a>" : '';
			$lstatus = (is_loggedin()) ? $logout_form . $admin_link : "<a href='".h($host)."?login'>Login</a>";
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
					$c['content'] = (is_loggedin()) ? $d['new_page']['admin'] : $d['new_page']['visitor'];
				} else{
					$c['content'] = $d['page'][$c[$key]];
				}
			}
			break;
		default:
			break;
	}
}

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

/* ══════════════════════════════════════════════
   ラッパー関数（エンジン・ユーティリティ）
   ══════════════════════════════════════════════ */

/** @deprecated Ver.1.4 で削除予定。AdminEngine::isLoggedIn() を直接使用してください */
function is_loggedin(): bool {
	return AdminEngine::isLoggedIn();
}

/** @deprecated Ver.1.4 で削除予定。AdminEngine::csrfToken() を直接使用してください */
function csrf_token(): string {
	return AdminEngine::csrfToken();
}

/** @deprecated Ver.1.4 で削除予定。AdminEngine::verifyCsrf() を直接使用してください */
function verify_csrf(): void {
	AdminEngine::verifyCsrf();
}

function getSlug(string $p): string {
	$slug = mb_convert_case(str_replace(' ', '-', $p), MB_CASE_LOWER, "UTF-8");
	/* R26 fix: ループでパストラバーサルを再帰的に除去（ ....// → ../ 対策） */
	$slug = str_replace("\0", '', $slug);
	do {
		$prev = $slug;
		$slug = str_replace(['../', '..\\'], '', $slug);
	} while ($slug !== $prev);
	$slug = preg_replace('#/+#', '/', $slug);
	return ltrim($slug, '/');
}

function h(string $s): string {
	return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

/* ══════════════════════════════════════════════
   共有ユーティリティ（全エンジンで使用）
   ══════════════════════════════════════════════ */

function data_dir(): string {
	$dir = 'data';
	if(!is_dir($dir)) mkdir($dir, 0755, true);
	return $dir;
}

function settings_dir(): string {
	$dir = 'data/settings';
	if(!is_dir($dir)) mkdir($dir, 0755, true);
	return $dir;
}

function content_dir(): string {
	$dir = 'data/content';
	if(!is_dir($dir)) mkdir($dir, 0755, true);
	return $dir;
}

function json_read(string $file, string $dir = ''): array {
	$path = ($dir ?: data_dir()).'/'.$file;
	if(!file_exists($path)) return [];
	$decoded = json_decode(file_get_contents($path), true);
	return is_array($decoded) ? $decoded : [];
}

function json_write(string $file, array $data, string $dir = ''): void {
	$result = file_put_contents(
		($dir ?: data_dir()).'/'.$file,
		json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
		LOCK_EX
	);
	if($result === false){
		error_log('json_write failed: '.$file);
		header('HTTP/1.1 500 Internal Server Error');
		exit;
	}
}

function host(){
	global $host, $rp;
	$rp = preg_replace('#/+#', '/', (isset($_GET['page'])) ? urldecode($_GET['page']) : '');
	/* C8 fix: HTTP_HOST ヘッダーインジェクション防止 */
	$rawHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
	$rawHost = preg_replace('/[^a-zA-Z0-9.\-:\[\]]/', '', $rawHost);
	$host = $rawHost;
	$uri = preg_replace('#/+#', '/', urldecode($_SERVER['REQUEST_URI']));
	$host = (strrpos($uri, $rp) !== false) ? $host.'/'.substr($uri, 0, strlen($uri) - strlen($rp)) : $host.'/'.$uri;
	$host = explode('?', $host);
	$host = '//'.str_replace('//', '/', $host[0]);
	$strip = array('index.php','?','"','\'','>','<','=','(',')','\\');
	$rp = strip_tags(str_replace($strip, '', $rp));
	$host = strip_tags(str_replace($strip, '', $host));
}

function migrate_from_files(): void {
	/* Phase 1: files/ フラット構造 → data/ への旧来マイグレーション */
	if(!file_exists(data_dir().'/settings.json') && !file_exists(settings_dir().'/settings.json')){
		$settings_keys = ['title','description','keywords','copyright','themeSelect','menu','subside'];
		$settings = [];
		foreach($settings_keys as $key){
			$v = file_exists('files/'.$key) ? file_get_contents('files/'.$key) : false;
			if($v !== false) $settings[$key] = $v;
		}
		if($settings) json_write('settings.json', $settings, settings_dir());
		$pw = file_exists('files/password') ? file_get_contents('files/password') : false;
		if($pw) json_write('auth.json', ['password_hash' => trim($pw)], settings_dir());
		$skip = array_merge($settings_keys, ['password','loggedin']);
		$pages = [];
		foreach(glob('files/*') ?: [] as $f){
			$slug = basename($f);
			if(!in_array($slug, $skip, true)){
				$v = file_get_contents($f);
				if($v !== false) $pages[$slug] = $v;
			}
		}
		if($pages) json_write('pages.json', $pages, content_dir());
	}
	/* Phase 2: data/*.json → data/settings/ & data/content/ への移行 */
	$s_dir = settings_dir();
	$c_dir = content_dir();
	foreach(['settings.json','auth.json','update_cache.json','login_attempts.json','version.json'] as $f){
		$old = data_dir().'/'.$f;
		$new = $s_dir.'/'.$f;
		if(file_exists($old) && !file_exists($new)){
			rename($old, $new);
		}
	}
	$old_pages = data_dir().'/pages.json';
	$new_pages = $c_dir.'/pages.json';
	if(file_exists($old_pages) && !file_exists($new_pages)){
		rename($old_pages, $new_pages);
	}
}
?>
