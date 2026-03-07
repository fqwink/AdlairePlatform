<?php
/**
*
*@copyright Copyright (c) 2014 - 2015 IEAS Group
*@copyright Copyright (c) 2014 - 2015 AIZM
*@license　Adlaire License
*
*/

if (PHP_VERSION_ID < 80200) {
	http_response_code(500);
	header('Content-Type: text/plain; charset=UTF-8');
	exit('AdlairePlatform requires PHP 8.2 or later. Current version: ' . PHP_VERSION);
}

define('AP_VERSION', '1.2.16');
define('AP_UPDATE_URL', 'https://api.github.com/repos/win-k/AdlairePlatform/releases/latest');
define('AP_BACKUP_GENERATIONS', 5);

require 'engines/ThemeEngine.php';
require 'engines/UpdateEngine.php';

ob_start();
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');
session_start();
migrate_from_files();
host();
handle_update_action();
edit();

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

foreach($c as $key => $val){
	if($key == 'content') continue;
	$d['default'][$key] = $c[$key];
	if(isset($_settings[$key]))
		$c[$key] = $_settings[$key];
	switch($key){
		case 'password':
			if(empty($_auth['password_hash'])){
				$c[$key] = savePassword($val);
			} elseif(strlen($_auth['password_hash']) === 32 && ctype_xdigit($_auth['password_hash'])){
				$c[$key] = savePassword('admin');
				$c['migrate_warning'] = true;
			} else {
				$c[$key] = $_auth['password_hash'];
			}
			break;
		case 'loggedin':
			if(isset($_SESSION['l']) && $_SESSION['l'] === true)
				$c[$key] = true;
			if(isset($_POST['logout'])){
				verify_csrf();
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
					login();
				$c['content'] = "<form action='' method='POST'>
				<input type='hidden' name='csrf' value='".csrf_token()."'>
				<input type='password' name='password'>
				<input type='submit' name='login' value='Login'> $msg
				<p class='toggle'>Change password</p>
				<div class='hide'>Type your old password above and your new one below.<br />
				<input type='password' name='new'>
				<input type='submit' name='login' value='Change'>
				<input type='hidden' name='sub' value='sub'>
				</div>
				</form>";
			}
			$logout_form = "<form method='POST' style='display:inline'>"
				."<input type='hidden' name='csrf' value='".csrf_token()."'>"
				."<button type='submit' name='logout' value='1' style='background:none;border:none;cursor:pointer;padding:0;color:inherit;text-decoration:underline;font:inherit'>Logout</button>"
				."</form>";
			$lstatus = (is_loggedin()) ? $logout_form : "<a href='".h($host)."?login'>Login</a>";
			break;
		case 'page':
			if($rp)
				$c[$key] = $rp;
			$c[$key] = getSlug($c[$key]);
			if(isset($_GET['login'])) continue 2;
			$c['content'] = $_pages[$c[$key]] ?? null;
			if(!$c['content']){
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
registerCoreHooks();

ThemeEngine::load($c['themeSelect']);

function registerCoreHooks(): void {
	global $hook;
	$hook['admin-head'][] = "\n\t<script src='engines/JsEngine/autosize.js'></script>";
	$hook['admin-head'][] = "\n\t<script src='engines/JsEngine/editInplace.js'></script>";
	$hook['admin-head'][] = "\n\t<script src='engines/JsEngine/wysiwyg.js'></script>";
	$hook['admin-head'][] = "\n\t<script src='engines/JsEngine/updater.js'></script>";
}

function getSlug(string $p): string {
	return mb_convert_case(str_replace(' ', '-', $p), MB_CASE_LOWER, "UTF-8");
}

function is_loggedin(): bool {
	global $c;
	return $c['loggedin'];
}

function editTags(){
	global $hook;
	if(!is_loggedin() && !isset($_GET['login']))
		return;
	foreach($hook['admin-head'] as $o){
		echo "\t".$o."\n";
	}
}

function h(string $s): string {
	return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function content(string $id, $content = ''): void {
	global $d;
	$content = (string)($content ?? '');
	if(is_loggedin()){
		echo "<span title='".h($d['default']['content'])."' id='".h($id)."' class='editRich'>".$content."</span>";
	} else {
		echo $content;
	}
}

function edit(){
	if(isset($_POST['fieldname'], $_POST['content'])){
		$fieldname = $_POST['fieldname'];
		if(!preg_match('/^[a-zA-Z0-9_\-]+$/', $fieldname)){
			header('HTTP/1.1 400 Bad Request');
			exit;
		}
		$content = trim($_POST['content']);
		if(!isset($_SESSION['l'])){
			header('HTTP/1.1 401 Unauthorized');
			exit;
		}
		verify_csrf();
		$settings_keys = ['title','description','keywords','copyright','themeSelect','menu','subside'];
		if(in_array($fieldname, $settings_keys, true)){
			$settings = json_read('settings.json', settings_dir());
			$settings[$fieldname] = $content;
			json_write('settings.json', $settings, settings_dir());
		} else {
			$pages = json_read('pages.json', content_dir());
			$pages[$fieldname] = $content;
			json_write('pages.json', $pages, content_dir());
		}
		echo $content;
		exit;
	}
}

function menu(){
	global $c, $host;
	$mlist = explode("<br />\n", $c['menu']);
	?><ul>
	<?php
	foreach ($mlist as $cp){
		if(trim(strip_tags($cp)) === '') continue;
		$slug = getSlug(strip_tags($cp));
		?>
			<li<?php if($c['page'] == $slug) echo ' class="active"'; ?>><a href='<?php echo h($slug); ?>'><?php echo h(strip_tags($cp)); ?></a></li>
	<?php } ?>
	</ul>
<?php
}

function login(){
	global $c, $msg;
	verify_csrf();
	$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
	if(!check_login_rate($ip)){
		$msg = '試行回数が多すぎます。しばらくしてから再試行してください。';
		return;
	}
	if(!password_verify($_POST['password'] ?? '', $c['password'])){
		record_login_failure($ip);
		$msg = 'wrong password';
		return;
	}
	clear_login_rate($ip);
	if(!empty($_POST['new'])){
		savePassword($_POST['new']);
		$msg = 'password changed';
		return;
	}
	session_regenerate_id(true);
	$_SESSION['l'] = true;
	header('Location: ./');
	exit;
}

function check_login_rate(string $ip): bool {
	$data     = json_read('login_attempts.json', settings_dir());
	$attempts = $data[$ip] ?? ['count' => 0, 'locked_until' => 0];
	return time() >= (int)$attempts['locked_until'];
}

function record_login_failure(string $ip): void {
	$data     = json_read('login_attempts.json', settings_dir());
	$attempts = $data[$ip] ?? ['count' => 0, 'locked_until' => 0];
	if(time() >= (int)$attempts['locked_until']){
		$attempts['count']++;
	}
	if($attempts['count'] >= 5){
		$attempts['locked_until'] = time() + 900; /* 15分ロックアウト */
		$attempts['count']        = 0;
	}
	$data[$ip] = $attempts;
	json_write('login_attempts.json', $data, settings_dir());
}

function clear_login_rate(string $ip): void {
	$data = json_read('login_attempts.json', settings_dir());
	unset($data[$ip]);
	json_write('login_attempts.json', $data, settings_dir());
}

function savePassword(string $p): string {
	$hash = password_hash($p, PASSWORD_BCRYPT);
	json_write('auth.json', ['password_hash' => $hash], settings_dir());
	return $hash;
}

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

function csrf_token(): string {
	if(empty($_SESSION['csrf'])){
		$_SESSION['csrf'] = bin2hex(random_bytes(32));
	}
	return $_SESSION['csrf'];
}

function verify_csrf(): void {
	$token = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
	if(!hash_equals($_SESSION['csrf'] ?? '', $token)){
		header('HTTP/1.1 403 Forbidden');
		exit;
	}
}

function host(){
	global $host, $rp;
	$rp = preg_replace('#/+#', '/', (isset($_GET['page'])) ? urldecode($_GET['page']) : '');
	$host = $_SERVER['HTTP_HOST'];
	$uri = preg_replace('#/+#', '/', urldecode($_SERVER['REQUEST_URI']));
	$host = (strrpos($uri, $rp) !== false) ? $host.'/'.substr($uri, 0, strlen($uri) - strlen($rp)) : $host.'/'.$uri;
	$host = explode('?', $host);
	$host = '//'.str_replace('//', '/', $host[0]);
	$strip = array('index.php','?','"','\'','>','<','=','(',')','\\');
	$rp = strip_tags(str_replace($strip, '', $rp));
	$host = strip_tags(str_replace($strip, '', $host));
}

function settings(){
	global $c, $d;
	if(!empty($c['migrate_warning']))
		echo "<div style='background:#c0392b;color:#fff;padding:10px;margin:5px 0;font-weight:bold;'>警告: パスワードが MD5 から bcrypt に移行されました。パスワードが \"admin\" にリセットされています。すぐに変更してください。</div>";
	echo "<div class='settings'>
	<h3 class='toggle'>↕ Settings ↕</h3>
	<div class='hide'>
	<div class='change border'><b>Theme</b>&nbsp;<span id='themeSelect'><select name='themeSelect' id='ap-theme-select'>";
	foreach(ThemeEngine::listThemes() as $val){
		$select = ($val == $c['themeSelect']) ? ' selected' : '';
		echo '<option value="'.h($val).'"'.$select.'>'.h($val)."</option>\n";
	}
	echo "</select></span></div>
	<div class='change border'><b>Menu <small>(add a page below and <a href='./' id='ap-refresh-link'>refresh</a>)</small></b><span id='menu' title='Home' class='editText'>".$c['menu']."</span></div>";
	foreach(array('title','description','keywords','copyright') as $key){
		echo "<div class='change border'><span title='".h($d['default'][$key])."' id='".h($key)."' class='editText'>".$c[$key]."</span></div>";
	}
	echo "</div></div>";
	echo "<div class='settings'>
	<h3 class='toggle'>↕ アップデート ↕</h3>
	<div class='hide'>
	<div class='change border'>
		<b>現在のバージョン:</b> ".h(AP_VERSION)."
		<br><br>
		<button id='ap-check-update' style='cursor:pointer;'>更新を確認</button>
		<span id='ap-update-status' style='margin-left:10px;'></span>
		<div id='ap-update-result' style='margin-top:10px;'></div>
	</div>
	</div></div>";
}
ob_end_flush();
?>
