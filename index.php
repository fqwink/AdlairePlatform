<?php
/**
*
*@copyright Copyright (c) 2014 - 2015 IEAS Group
*@copyright Copyright (c) 2014 - 2015 AIZM
*@license　Adlaire License
*
*/

define('AP_VERSION', '1.0.0');
define('AP_UPDATE_URL', 'https://api.github.com/repos/win-k/AdlairePlatform/releases/latest');

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
$hook['admin-richText'] = "rte.php";

if(!file_exists('plugins')) mkdir('plugins', 0755, true);

$_settings = json_read('settings.json');
$_auth     = json_read('auth.json');
$_pages    = json_read('pages.json');

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
loadPlugins();

if(!preg_match('/^[a-zA-Z0-9_-]+$/', $c['themeSelect'])){
	$c['themeSelect'] = 'AP-Default';
}
require("themes/".$c['themeSelect']."/theme.php");

function loadPlugins(){
	global $hook, $c;
	$cwd = getcwd();
	if(chdir("./plugins/")){
		$dirs = glob('*', GLOB_ONLYDIR);
		if(is_array($dirs))
			foreach($dirs as $dir){
				require_once($cwd.'/plugins/'.$dir.'/index.php');
			}
	}
	chdir($cwd);
	$hook['admin-head'][] = "\n	<script type='text/javascript' src='./js/editInplace.php?hook=".h($hook['admin-richText'])."'></script>";
	$hook['admin-head'][] = "\n	<script type='text/javascript' src='./js/updater.js'></script>";
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

function content($id, $content){
	global $d;
	if(is_loggedin()){
		echo "<span title='".h($d['default']['content'])."' id='".h($id)."' class='editText richText'>".$content."</span>";
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
			$settings = json_read('settings.json');
			$settings[$fieldname] = $content;
			json_write('settings.json', $settings);
		} else {
			$pages = json_read('pages.json');
			$pages[$fieldname] = $content;
			json_write('pages.json', $pages);
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
			<li<?php if($c['page'] == $slug) echo ' id="active" '; ?>><a href='<?php echo h($slug); ?>'><?php echo h(strip_tags($cp)); ?></a></li>
	<?php } ?>
	</ul>
<?php
}

function login(){
	global $c, $msg;
	verify_csrf();
	if(!password_verify($_POST['password'] ?? '', $c['password'])){
		$msg = 'wrong password';
		return;
	}
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

function savePassword(string $p): string {
	$hash = password_hash($p, PASSWORD_BCRYPT);
	json_write('auth.json', ['password_hash' => $hash]);
	return $hash;
}

function data_dir(): string {
	$dir = 'data';
	if(!is_dir($dir)) mkdir($dir, 0755, true);
	return $dir;
}

function json_read(string $file): array {
	$path = data_dir().'/'.$file;
	if(!file_exists($path)) return [];
	$decoded = json_decode(file_get_contents($path), true);
	return is_array($decoded) ? $decoded : [];
}

function json_write(string $file, array $data): void {
	$result = file_put_contents(
		data_dir().'/'.$file,
		json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
	);
	if($result === false){
		error_log('json_write failed: '.$file);
		header('HTTP/1.1 500 Internal Server Error');
		exit;
	}
}

function migrate_from_files(){
	if(file_exists(data_dir().'/settings.json')) return;
	$settings_keys = ['title','description','keywords','copyright','themeSelect','menu','subside'];
	$settings = [];
	foreach($settings_keys as $key){
		$v = file_exists('files/'.$key) ? file_get_contents('files/'.$key) : false;
		if($v !== false) $settings[$key] = $v;
	}
	if($settings) json_write('settings.json', $settings);
	$pw = file_exists('files/password') ? file_get_contents('files/password') : false;
	if($pw) json_write('auth.json', ['password_hash' => trim($pw)]);
	$skip = array_merge($settings_keys, ['password','loggedin']);
	$pages = [];
	foreach(glob('files/*') ?: [] as $f){
		$slug = basename($f);
		if(!in_array($slug, $skip, true)){
			$v = file_get_contents($f);
			if($v !== false) $pages[$slug] = $v;
		}
	}
	if($pages) json_write('pages.json', $pages);
}

function handle_update_action(): void {
	if(!isset($_POST['ap_action'])) return;
	if(!isset($_SESSION['l']) || $_SESSION['l'] !== true){
		header('HTTP/1.1 401 Unauthorized');
		header('Content-Type: application/json');
		echo json_encode(['error' => '認証が必要です']);
		exit;
	}
	verify_csrf();
	header('Content-Type: application/json');
	switch($_POST['ap_action']){
		case 'check':
			echo json_encode(check_update());
			break;
		case 'apply':
			$zip_url = $_POST['zip_url'] ?? '';
			if(!preg_match('#^https://(api\.github\.com|github\.com|codeload\.github\.com)/#', $zip_url)){
				header('HTTP/1.1 400 Bad Request');
				echo json_encode(['error' => '無効な URL です']);
				exit;
			}
			$version = $_POST['version'] ?? '';
			apply_update($zip_url, $version);
			echo json_encode(['success' => true, 'message' => 'アップデートが完了しました。ページを再読み込みします。']);
			break;
		case 'list_backups':
			$backups = glob('backup/*', GLOB_ONLYDIR);
			$list = is_array($backups) ? array_map('basename', $backups) : [];
			rsort($list);
			echo json_encode(['backups' => $list]);
			break;
		case 'rollback':
			$name = $_POST['backup'] ?? '';
			if(!preg_match('/^[0-9_]+$/', $name)){
				header('HTTP/1.1 400 Bad Request');
				echo json_encode(['error' => '無効なバックアップ名です']);
				exit;
			}
			rollback_to_backup($name);
			echo json_encode(['success' => true, 'message' => 'ロールバックが完了しました。ページを再読み込みします。']);
			break;
		default:
			header('HTTP/1.1 400 Bad Request');
			echo json_encode(['error' => '不明なアクションです']);
	}
	exit;
}

function check_update(): array {
	$ctx = stream_context_create(['http' => [
		'method'  => 'GET',
		'header'  => "User-Agent: AdlairePlatform/".AP_VERSION."\r\n",
		'timeout' => 10,
	]]);
	$res = @file_get_contents(AP_UPDATE_URL, false, $ctx);
	if($res === false){
		return ['error' => 'アップデートサーバーに接続できませんでした。'];
	}
	$data = json_decode($res, true);
	if(!is_array($data) || !isset($data['tag_name'])){
		return ['error' => 'バージョン情報の取得に失敗しました。'];
	}
	$latest  = ltrim($data['tag_name'], 'v');
	$zip_url = $data['zipball_url'] ?? '';
	if(isset($data['assets']) && is_array($data['assets'])){
		foreach($data['assets'] as $asset){
			if(isset($asset['browser_download_url']) && str_ends_with($asset['browser_download_url'], '.zip')){
				$zip_url = $asset['browser_download_url'];
				break;
			}
		}
	}
	return [
		'current'          => AP_VERSION,
		'latest'           => $latest,
		'update_available' => version_compare($latest, AP_VERSION, '>'),
		'zip_url'          => $zip_url,
	];
}

function backup_current(): string {
	$name = date('Ymd_His');
	$dest = 'backup/'.$name;
	if(!is_dir('backup')) mkdir('backup', 0755, true);
	mkdir($dest, 0755, true);
	$exclude = ['data', 'backup', '.git'];
	$iter = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator('.', RecursiveDirectoryIterator::SKIP_DOTS),
		RecursiveIteratorIterator::SELF_FIRST
	);
	foreach($iter as $item){
		$path  = $iter->getSubPathname();
		$parts = explode(DIRECTORY_SEPARATOR, $path);
		if(in_array($parts[0], $exclude, true)) continue;
		if($item->isDir()){
			@mkdir($dest.'/'.$path, 0755, true);
		} else {
			@copy($item->getRealPath(), $dest.'/'.$path);
		}
	}
	return $name;
}

function apply_update(string $zip_url, string $new_version = ''): void {
	$backup = backup_current();
	$tmp = sys_get_temp_dir().'/ap_update_'.time().'.zip';
	$ctx = stream_context_create(['http' => [
		'method'  => 'GET',
		'header'  => "User-Agent: AdlairePlatform/".AP_VERSION."\r\n",
		'timeout' => 60,
	]]);
	$zip_data = @file_get_contents($zip_url, false, $ctx);
	if($zip_data === false){
		error_log('apply_update: download failed: '.$zip_url);
		header('HTTP/1.1 502 Bad Gateway');
		echo json_encode(['error' => 'ダウンロードに失敗しました。']);
		exit;
	}
	file_put_contents($tmp, $zip_data);
	$zip = new ZipArchive();
	if($zip->open($tmp) !== true){
		unlink($tmp);
		header('HTTP/1.1 500 Internal Server Error');
		echo json_encode(['error' => 'ZIP の展開に失敗しました。']);
		exit;
	}
	$extract_dir = sys_get_temp_dir().'/ap_update_extract_'.time();
	$ok = $zip->extractTo($extract_dir);
	$zip->close();
	unlink($tmp);
	if(!$ok){
		header('HTTP/1.1 500 Internal Server Error');
		echo json_encode(['error' => 'ZIP の展開に失敗しました。']);
		exit;
	}
	$top = glob($extract_dir.'/*', GLOB_ONLYDIR);
	$src = (is_array($top) && count($top) === 1) ? $top[0] : $extract_dir;
	$real_src = realpath($src);
	if($real_src === false){
		header('HTTP/1.1 500 Internal Server Error');
		echo json_encode(['error' => 'ZIP 展開先のパス解決に失敗しました。']);
		exit;
	}
	$exclude = ['data', 'backup'];
	$iter = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS),
		RecursiveIteratorIterator::SELF_FIRST
	);
	foreach($iter as $item){
		$rel   = substr($item->getRealPath(), strlen($real_src) + 1);
		$parts = explode(DIRECTORY_SEPARATOR, $rel);
		if(in_array($parts[0], $exclude, true)) continue;
		if($item->isDir()){
			@mkdir('./'.$rel, 0755, true);
		} else {
			@copy($item->getRealPath(), './'.$rel);
		}
	}
	$clean = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($extract_dir, RecursiveDirectoryIterator::SKIP_DOTS),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach($clean as $f){
		$f->isDir() ? @rmdir($f->getRealPath()) : @unlink($f->getRealPath());
	}
	@rmdir($extract_dir);
	$ver_data = json_read('version.json');
	if(empty($ver_data['history'])) $ver_data['history'] = [];
	$applied = $new_version ?: AP_VERSION;
	$ver_data['version']    = $applied;
	$ver_data['updated_at'] = date('Y-m-d');
	$ver_data['history'][]  = [
		'version'    => $applied,
		'applied_at' => date('Y-m-d H:i:s'),
		'backup'     => $backup,
	];
	json_write('version.json', $ver_data);
}

function rollback_to_backup(string $backup_name): void {
	$src = 'backup/'.$backup_name;
	if(!is_dir($src)){
		header('HTTP/1.1 404 Not Found');
		echo json_encode(['error' => 'バックアップが見つかりません: '.h($backup_name)]);
		exit;
	}
	$exclude = ['data'];
	$real_src = realpath($src);
	$iter = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS),
		RecursiveIteratorIterator::SELF_FIRST
	);
	foreach($iter as $item){
		$rel   = substr($item->getRealPath(), strlen($real_src) + 1);
		$parts = explode(DIRECTORY_SEPARATOR, $rel);
		if(in_array($parts[0], $exclude, true)) continue;
		if($item->isDir()){
			@mkdir('./'.$rel, 0755, true);
		} else {
			@copy($item->getRealPath(), './'.$rel);
		}
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
	<div class='change border'><b>Theme</b>&nbsp;<span id='themeSelect'><select name='themeSelect' onchange='fieldSave(\"themeSelect\",this.value);'>";
	$cwd = getcwd();
	if(chdir("./themes/")){
		$dirs = glob('*', GLOB_ONLYDIR);
		if(is_array($dirs))
			foreach($dirs as $val){
				$select = ($val == $c['themeSelect']) ? ' selected' : '';
				echo '<option value="'.h($val).'"'.$select.'>'.h($val)."</option>\n";
			}
	}
	chdir($cwd);
	echo "</select></span></div>
	<div class='change border'><b>Menu <small>(add a page below and <a href='javascript:location.reload(true);'>refresh</a>)</small></b><span id='menu' title='Home' class='editText'>".$c['menu']."</span></div>";
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
