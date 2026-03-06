<?php
/**
*
*@copyright Copyright (c) 2014 - 2015 IEAS Group
*@copyright Copyright (c) 2014 - 2015 AIZM
*@license　Adlaire License
*
*/

ob_start();
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');
session_start();
migrate_from_files();
host();
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
					$c['content'] = (is_loggedin()) ? $d['new_page']['admin'] : $c['content'] = $d['new_page']['visitor'];
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
	$hook['admin-head'][] = "\n	<script type='text/javascript' src='./js/editInplace.php?hook=".$hook['admin-richText']."'></script>";
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
		$v = @file_get_contents('files/'.$key);
		if($v !== false) $settings[$key] = $v;
	}
	if($settings) json_write('settings.json', $settings);
	$pw = @file_get_contents('files/password');
	if($pw) json_write('auth.json', ['password_hash' => trim($pw)]);
	$skip = array_merge($settings_keys, ['password','loggedin']);
	$pages = [];
	foreach(glob('files/*') ?: [] as $f){
		$slug = basename($f);
		if(!in_array($slug, $skip, true)) $pages[$slug] = file_get_contents($f);
	}
	if($pages) json_write('pages.json', $pages);
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
	if(chdir("./themes/")){
		$dirs = glob('*', GLOB_ONLYDIR);
		foreach($dirs as $val){
			$select = ($val == $c['themeSelect']) ? ' selected' : '';
			echo '<option value="'.h($val).'"'.$select.'>'.h($val)."</option>\n";
		}
	}
	echo "</select></span></div>
	<div class='change border'><b>Menu <small>(add a page below and <a href='javascript:location.reload(true);'>refresh</a>)</small></b><span id='menu' title='Home' class='editText'>".$c['menu']."</span></div>";
	foreach(array('title','description','keywords','copyright') as $key){
		echo "<div class='change border'><span title='".h($d['default'][$key])."' id='".h($key)."' class='editText'>".$c[$key]."</span></div>";
	}
	echo "</div></div>";
}
ob_end_flush();
?>
