<?php
/**
*
*@copyright Copyright (c) 2014 - 2026 Adlaire Group
*@license Adlaire License Ver.2.0
*
*/

if (PHP_VERSION_ID < 80300) {
	http_response_code(500);
	header('Content-Type: text/plain; charset=UTF-8');
	exit('AdlairePlatform requires PHP 8.3 or later. Current version: ' . PHP_VERSION);
}

define('AP_VERSION', '1.9.39');
define('AP_UPDATE_URL', 'https://api.github.com/repos/win-k/AdlairePlatform/releases/latest');
/* Ver.1.9: 設定値は Config クラスで管理。定数は後方互換のため残す */
define('AP_BACKUP_GENERATIONS', 5);
define('AP_REVISION_LIMIT', 30);

/* ── Ver.1.5: Framework オートローダー ── */
require __DIR__ . '/autoload.php';

/* ── Ver.1.8: グローバルユーティリティ関数（bootstrap.php より先に読み込む） ── */
require __DIR__ . '/Framework/AP/AP.Bridge.php';

/* ── Ver.1.5: ブートストラップ（DI コンテナ・イベント初期化） ── */
require __DIR__ . '/bootstrap.php';

/* ── Ver.1.7: ルート定義（Router にルートとミドルウェアを登録） ── */
require __DIR__ . '/routes.php';

/* Ver.1.9: セッション設定を Config クラスから取得（環境変数 AP_SESSION_* で上書き可能） */
$_ap_session_timeout = \APF\Utilities\Config::get('session.timeout', 1800);

ini_set('session.cookie_httponly', (int)\APF\Utilities\Config::get('session.cookie_httponly', true));
ini_set('session.cookie_samesite', \APF\Utilities\Config::get('session.cookie_samesite', 'Lax'));
ini_set('session.gc_maxlifetime', $_ap_session_timeout);
ini_set('session.cookie_lifetime', \APF\Utilities\Config::get('session.cookie_lifetime', 0));
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
	ini_set('session.cookie_secure', 1);
}
session_start();

/* Ver.1.6: アイドルタイムアウト検証（Config で設定可能） */
if (isset($_SESSION['l']) && $_SESSION['l'] === true) {
	$_SESSION['ap_last_activity'] = (int)($_SESSION['ap_last_activity'] ?? time());
	if (time() - $_SESSION['ap_last_activity'] > $_ap_session_timeout) {
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
unset($_ap_session_timeout);

/* i18n 初期化（セッション開始後） */
\AIS\Core\I18n::init();

/* B-6 fix: 集中ログ管理の初期化 */
\APF\Utilities\Logger::init();
/* Ver.1.9: グローバルエラーハンドラ登録（未キャッチ例外の統一処理） */
\APF\Core\ErrorBoundary::registerGlobal();
/* 診断: エラーハンドラ登録（セッション開始後） */
\AIS\System\DiagnosticsManager::registerErrorHandler();
\AIS\System\DiagnosticsManager::startTimer('request_total');

/* セキュリティヘッダー（Ver.1.9 強化） */
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
	header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}
host();

/* ══════════════════════════════════════════════════
 * Ver.1.7: Router ディスパッチ
 *
 * 全ルート対象: ?login, ?admin, POST ap_action=*, ?ap_api=*
 * 404（ルート未登録）の場合はページレンダリングにフォールスルー。
 * ══════════════════════════════════════════════════ */
$_ap_request  = Application::make(\APF\Core\Request::class);
$_ap_router   = Application::make(\APF\Core\Router::class);
$_ap_response = $_ap_router->dispatch($_ap_request);

if ($_ap_response->getStatusCode() !== 404) {
	\AIS\System\DiagnosticsManager::stopTimer('request_total');
	\AIS\System\DiagnosticsManager::maybeSend();
	$_ap_response->send();
	exit;
}
unset($_ap_request, $_ap_router, $_ap_response);

/* ══════════════════════════════════════════════════
 * ページレンダリング（Router 未処理 = 通常ページ表示）
 * ══════════════════════════════════════════════════ */

/* Ver.1.9: デフォルトパスワードを外部化（環境変数 AP_APP_DEFAULT_PASSWORD で上書き可能） */
$c['password'] = \APF\Utilities\Config::get('app.default_password', 'admin');
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
if (\ACE\Core\CollectionService::isEnabled()) {
	$_collectionPages = \ACE\Core\CollectionService::loadAllAsPages();
	foreach ($_collectionPages as $_cpSlug => $_cpHtml) {
		if (!isset($_pages[$_cpSlug])) {
			$_pages[$_cpSlug] = $_cpHtml;
		}
	}
	unset($_collectionPages, $_cpSlug, $_cpHtml);
}

foreach($c as $key => $val){
	if($key === 'content') continue;
	$d['default'][$key] = $c[$key];
	if(isset($_settings[$key]))
		$c[$key] = $_settings[$key];
	switch($key){
		case 'password':
			if(empty($_auth['password_hash'])){
				$c[$key] = \ACE\Admin\AdminManager::savePassword($val);
			} elseif(strlen($_auth['password_hash']) === 32 && ctype_xdigit($_auth['password_hash'])){
				/* R2 fix: MD5ハッシュ検出 → デフォルトパスワード 'admin' で bcrypt 化（ログイン可能を維持） */
				$c[$key] = \ACE\Admin\AdminManager::savePassword('admin');
				$c['migrate_warning'] = true;
				\APF\Utilities\Logger::warning('MD5パスワードを検出。デフォルト "admin" で bcrypt 化しました。直ちにパスワードを変更してください。');
				\AIS\System\DiagnosticsManager::log('security', 'MD5パスワード検出・bcrypt移行実行');
			} else {
				$c[$key] = $_auth['password_hash'];
			}
			/* デフォルトパスワード 'admin' が有効な場合の警告 */
			if (password_verify('admin', $c[$key])) {
				\AIS\System\DiagnosticsManager::log('security', 'デフォルトパスワード使用中');
			}
			break;
		case 'loggedin':
			/* Ver.1.7-37: ログイン/ログアウト/ダッシュボードは Router が処理。
			   ここではテンプレート変数 $lstatus の組み立てのみ実施。 */
			if(\ACE\Admin\AdminManager::isLoggedIn())
				$c[$key] = true;
			$logout_form = "<form method='POST' style='display:inline'>"
				."<input type='hidden' name='csrf' value='".\ACE\Admin\AdminManager::csrfToken()."'>"
				."<button type='submit' name='logout' value='1' style='background:none;border:none;cursor:pointer;padding:0;color:inherit;text-decoration:underline;font:inherit'>Logout</button>"
				."</form>";
			$admin_link = (\ACE\Admin\AdminManager::isLoggedIn()) ? " | <a href='?admin'>Dashboard</a>" : '';
			$lstatus = (\ACE\Admin\AdminManager::isLoggedIn()) ? $logout_form . $admin_link : "<a href='".h($host)."?login'>Login</a>";
			break;
		case 'page':
			if($rp)
				$c[$key] = $rp;
			$c[$key] = getSlug($c[$key]);
			$c['content'] = $_pages[$c[$key]] ?? null;
			if($c['content'] === null){
				if(!isset($d['page'][$c[$key]])){
					header('HTTP/1.1 404 Not Found');
					$c['content'] = (\ACE\Admin\AdminManager::isLoggedIn()) ? $d['new_page']['admin'] : $d['new_page']['visitor'];
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
\AIS\Core\AppContext::syncFromGlobals($c, $d, $host, $lstatus, $apcredit, $hook);

\ACE\Admin\AdminManager::registerHooks();

/* 診断: リクエスト終了時にデータ収集・定期送信 */
\AIS\System\DiagnosticsManager::stopTimer('request_total');
\AIS\System\DiagnosticsManager::maybeSend();

\ASG\Template\ThemeService::load($c['themeSelect']);
?>
