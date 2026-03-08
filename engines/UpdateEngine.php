<?php
/**
 * UpdateEngine - アップデート・バックアップ・ロールバック
 */

function check_environment(): array {
	$ziparchive = class_exists('ZipArchive');
	$url_fopen  = (bool)ini_get('allow_url_fopen');
	$writable   = is_writable('.');
	$disk_free  = @disk_free_space('.');
	$ok = $ziparchive && $url_fopen && $writable;
	return [
		'ziparchive' => $ziparchive,
		'url_fopen'  => $url_fopen,
		'writable'   => $writable,
		'disk_free'  => $disk_free !== false ? (int)$disk_free : -1,
		'ok'         => $ok,
	];
}

function prune_old_backups(): void {
	$backups = glob('backup/*', GLOB_ONLYDIR);
	if(!is_array($backups) || count($backups) <= AP_BACKUP_GENERATIONS) return;
	sort($backups);
	$excess = count($backups) - AP_BACKUP_GENERATIONS;
	for($i = 0; $i < $excess; $i++){
		$dir  = $backups[$i];
		$iter = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach($iter as $f){
			$f->isDir() ? @rmdir($f->getRealPath()) : @unlink($f->getRealPath());
		}
		@rmdir($dir);
	}
}

function delete_backup(string $name): void {
	$name = basename($name);
	if(!preg_match('/^[0-9_]+$/', $name)){
		header('HTTP/1.1 400 Bad Request');
		echo json_encode(['error' => '無効なバックアップ名です']);
		exit;
	}
	$dir = 'backup/'.$name;
	if(!is_dir($dir)){
		header('HTTP/1.1 404 Not Found');
		echo json_encode(['error' => 'バックアップが見つかりません: '.h($name)]);
		exit;
	}
	$iter = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach($iter as $f){
		$f->isDir() ? @rmdir($f->getRealPath()) : @unlink($f->getRealPath());
	}
	@rmdir($dir);
}

function handle_update_action(): void {
	$action = $_POST['ap_action'] ?? '';
	$valid_actions = ['check', 'check_env', 'apply', 'list_backups', 'rollback', 'delete_backup'];
	if(!in_array($action, $valid_actions, true)) return;
	if(!isset($_SESSION['l']) || $_SESSION['l'] !== true){
		header('HTTP/1.1 401 Unauthorized');
		header('Content-Type: application/json');
		echo json_encode(['error' => '認証が必要です']);
		exit;
	}
	AdminEngine::verifyCsrf();
	header('Content-Type: application/json');
	switch($_POST['ap_action']){
		case 'check':
			echo json_encode(check_update());
			break;
		case 'check_env':
			echo json_encode(check_environment());
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
			$list = [];
			if(is_array($backups)){
				rsort($backups);
				foreach($backups as $path){
					$name      = basename($path);
					$meta_file = $path.'/meta.json';
					$meta      = null;
					if(file_exists($meta_file)){
						$decoded = json_decode(file_get_contents($meta_file), true);
						if(is_array($decoded)) $meta = $decoded;
					}
					$list[] = ['name' => $name, 'meta' => $meta];
				}
			}
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
		case 'delete_backup':
			$name = $_POST['backup'] ?? '';
			if(!preg_match('/^[0-9_]+$/', $name)){
				header('HTTP/1.1 400 Bad Request');
				echo json_encode(['error' => '無効なバックアップ名です']);
				exit;
			}
			delete_backup($name);
			echo json_encode(['success' => true, 'message' => 'バックアップを削除しました。']);
			break;
		default:
			header('HTTP/1.1 400 Bad Request');
			echo json_encode(['error' => '不明なアクションです']);
	}
	exit;
}

function check_update(): array {
	$cache = json_read('update_cache.json', settings_dir());
	if(!empty($cache['result']) && !empty($cache['expires_at']) && time() < (int)$cache['expires_at']){
		return $cache['result'];
	}
	$ctx = stream_context_create(['http' => [
		'method'        => 'GET',
		'header'        => "User-Agent: AdlairePlatform/".AP_VERSION."\r\n",
		'timeout'       => 10,
		'ignore_errors' => true,
	]]);
	$res    = @file_get_contents(AP_UPDATE_URL, false, $ctx);
	$status = 200;
	if(isset($http_response_header)){
		foreach($http_response_header as $h){
			if(preg_match('#^HTTP/\S+\s+(\d+)#', $h, $m)){
				$status = (int)$m[1];
			}
		}
	}
	if($status === 403 || $status === 429){
		return ['error' => 'GitHub API のレート制限に達しました。しばらく待ってから再試行してください。'];
	}
	if($res === false || $status !== 200){
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
	$result = [
		'current'          => AP_VERSION,
		'latest'           => $latest,
		'update_available' => version_compare($latest, AP_VERSION, '>'),
		'zip_url'          => $zip_url,
	];
	json_write('update_cache.json', ['result' => $result, 'expires_at' => time() + 3600], settings_dir());
	return $result;
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
	$file_count = 0;
	$size_bytes = 0;
	foreach($iter as $item){
		$path  = $iter->getSubPathname();
		$parts = explode(DIRECTORY_SEPARATOR, $path);
		if(in_array($parts[0], $exclude, true)) continue;
		if($item->isDir()){
			@mkdir($dest.'/'.$path, 0755, true);
		} else {
			if(@copy($item->getRealPath(), $dest.'/'.$path)){
				$file_count++;
				$size_bytes += $item->getSize();
			}
		}
	}
	@file_put_contents($dest.'/meta.json', json_encode([
		'version_before' => AP_VERSION,
		'created_at'     => date('Y-m-d H:i:s'),
		'file_count'     => $file_count,
		'size_bytes'     => $size_bytes,
	], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
	return $name;
}

function apply_update(string $zip_url, string $new_version = ''): void {
	$backup = backup_current();
	prune_old_backups();
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
		new RecursiveDirectoryIterator($real_src, RecursiveDirectoryIterator::SKIP_DOTS),
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
	$ver_data = json_read('version.json', settings_dir());
	if(empty($ver_data['history'])) $ver_data['history'] = [];
	$applied = $new_version ?: AP_VERSION;
	$ver_data['version']    = $applied;
	$ver_data['updated_at'] = date('Y-m-d');
	$ver_data['history'][]  = [
		'version'    => $applied,
		'applied_at' => date('Y-m-d H:i:s'),
		'backup'     => $backup,
	];
	json_write('version.json', $ver_data, settings_dir());
}

function rollback_to_backup(string $backup_name): void {
	$backup_name = basename($backup_name);
	if(!preg_match('/^[0-9_]+$/', $backup_name)){
		header('HTTP/1.1 400 Bad Request');
		echo json_encode(['error' => '無効なバックアップ名です']);
		exit;
	}
	$src = 'backup/'.$backup_name;
	if(!is_dir($src)){
		header('HTTP/1.1 404 Not Found');
		echo json_encode(['error' => 'バックアップが見つかりません: '.h($backup_name)]);
		exit;
	}
	$real_src = realpath($src);
	if($real_src === false){
		header('HTTP/1.1 500 Internal Server Error');
		echo json_encode(['error' => 'バックアップパスの解決に失敗しました。']);
		exit;
	}
	$exclude = ['data'];
	$iter = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS),
		RecursiveIteratorIterator::SELF_FIRST
	);
	foreach($iter as $item){
		$rel   = substr($item->getRealPath(), strlen($real_src) + 1);
		if($rel === 'meta.json') continue;
		$parts = explode(DIRECTORY_SEPARATOR, $rel);
		if(in_array($parts[0], $exclude, true)) continue;
		if($item->isDir()){
			@mkdir('./'.$rel, 0755, true);
		} else {
			@copy($item->getRealPath(), './'.$rel);
		}
	}
}
