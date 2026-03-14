<?php
/**
 * UpdateEngine - アップデート・バックアップ・ロールバック
 *
 * 手続き型関数からstaticクラスに変換。
 * EngineTrait で認証・JSON レスポンスを統合。
 */
class UpdateEngine {
	use EngineTrait;

	public static function checkEnvironment(): array {
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

	private static function pruneOldBackups(): void {
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
				if ($f->isDir()) {
					if (!@rmdir($f->getRealPath()) && class_exists('DiagnosticEngine')) {
						DiagnosticEngine::log('engine', 'バックアップ削除失敗: rmdir', ['path' => DiagnosticEngine::isEnabled() ? basename($f->getRealPath()) : '']);
					}
				} else {
					if (!@unlink($f->getRealPath()) && class_exists('DiagnosticEngine')) {
						DiagnosticEngine::log('engine', 'バックアップ削除失敗: unlink', ['path' => DiagnosticEngine::isEnabled() ? basename($f->getRealPath()) : '']);
					}
				}
			}
			@rmdir($dir);
		}
	}

	private static function deleteBackup(string $name): void {
		$name = basename($name);
		if(!preg_match('/^[0-9_]+$/', $name)){
			self::jsonError('無効なバックアップ名です');
		}
		$dir = 'backup/'.$name;
		if(!is_dir($dir)){
			self::jsonError('バックアップが見つかりません: '.h($name), 404);
		}
		$iter = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		$deleteErrors = 0;
		foreach($iter as $f){
			if ($f->isDir()) {
				if (!@rmdir($f->getRealPath())) $deleteErrors++;
			} else {
				if (!@unlink($f->getRealPath())) $deleteErrors++;
			}
		}
		if (!@rmdir($dir)) $deleteErrors++;
		if ($deleteErrors > 0 && class_exists('DiagnosticEngine')) {
			DiagnosticEngine::log('engine', 'バックアップ削除で一部失敗', ['backup' => $name, 'errors' => $deleteErrors]);
		}
	}

	public static function handle(): void {
		$action = $_POST['ap_action'] ?? '';
		$valid_actions = ['check', 'check_env', 'apply', 'list_backups', 'rollback', 'delete_backup'];
		if(!in_array($action, $valid_actions, true)) return;

		self::requireLogin();

		switch($action){
			case 'check':
				echo json_encode(self::checkUpdate());
				break;
			case 'check_env':
				echo json_encode(self::checkEnvironment());
				break;
			case 'apply':
				/* C20 fix: URL をサーバー側で取得（ユーザー入力の URL を信頼しない） */
				$updateInfo = self::checkUpdate();
				if(empty($updateInfo['zip_url']) || empty($updateInfo['update_available'])){
					self::jsonError('アップデートが利用できません');
				}
				$zip_url = $updateInfo['zip_url'];
				if(!preg_match('#^https://(api\.github\.com|github\.com|codeload\.github\.com)/#', $zip_url)){
					self::jsonError('無効な URL です');
				}
				$version = $updateInfo['latest'] ?? '';
				self::applyUpdate($zip_url, $version);
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
							$decoded = FileSystem::readJson($meta_file);
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
					self::jsonError('無効なバックアップ名です');
				}
				self::rollbackToBackup($name);
				echo json_encode(['success' => true, 'message' => 'ロールバックが完了しました。ページを再読み込みします。']);
				break;
			case 'delete_backup':
				$name = $_POST['backup'] ?? '';
				if(!preg_match('/^[0-9_]+$/', $name)){
					self::jsonError('無効なバックアップ名です');
				}
				self::deleteBackup($name);
				echo json_encode(['success' => true, 'message' => 'バックアップを削除しました。']);
				break;
			default:
				self::jsonError('不明なアクションです');
		}
		exit;
	}

	public static function checkUpdate(): array {
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
		$_checkStart = hrtime(true);
		$res    = @file_get_contents(AP_UPDATE_URL, false, $ctx);
		$_checkElapsed = round((hrtime(true) - $_checkStart) / 1_000_000, 2);
		if ($res === false && class_exists('DiagnosticEngine')) {
			DiagnosticEngine::logIntegrationError('GitHub API', 0, 'アップデートチェック失敗: ' . AP_UPDATE_URL);
		}
		$status = 200;
		if(isset($http_response_header)){
			foreach($http_response_header as $h){
				if(preg_match('#^HTTP/\S+\s+(\d+)#', $h, $m)){
					$status = (int)$m[1];
				}
			}
		}
		if($status === 403 || $status === 429){
			if (class_exists('DiagnosticEngine')) DiagnosticEngine::logIntegrationError('GitHub API', $status, 'レート制限');
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
		if (class_exists('DiagnosticEngine')) DiagnosticEngine::log('performance', 'アップデートチェック完了', ['response_time_ms' => $_checkElapsed, 'current' => AP_VERSION, 'latest' => $latest, 'update_available' => $result['update_available']]);
		json_write('update_cache.json', ['result' => $result, 'expires_at' => time() + 3600], settings_dir());
		return $result;
	}

	private static function backupCurrent(): string {
		$name = date('Ymd_His');
		$dest = 'backup/'.$name;
		if(!is_dir('backup') && !@mkdir('backup', 0755, true)) {
			if (class_exists('DiagnosticEngine')) DiagnosticEngine::logEnvironmentIssue('バックアップディレクトリ作成失敗');
			return '';
		}
		if(!@mkdir($dest, 0755, true)) {
			if (class_exists('DiagnosticEngine')) DiagnosticEngine::logEnvironmentIssue('バックアップ先ディレクトリ作成失敗', ['dest' => $name]);
			return '';
		}
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
				} else {
					if (class_exists('DiagnosticEngine')) DiagnosticEngine::log('engine', 'バックアップ ファイルコピー失敗', ['file' => basename($path), 'error' => error_get_last()['message'] ?? '']);
				}
			}
		}
		FileSystem::writeJson($dest.'/meta.json', [
			'version_before' => AP_VERSION,
			'created_at'     => date('Y-m-d H:i:s'),
			'file_count'     => $file_count,
			'size_bytes'     => $size_bytes,
		]);
		return $name;
	}

	private static function applyUpdate(string $zip_url, string $new_version = ''): void {
		/* ディスク容量チェック（最低 50MB 必要） */
		$free = @disk_free_space('.');
		if($free !== false && $free < 50 * 1024 * 1024){
			http_response_code(507);
			echo json_encode(['error' => 'ディスク容量が不足しています（空き: '.round($free / 1024 / 1024, 1).'MB）。']);
			exit;
		}
		$backup = self::backupCurrent();
		if ($backup === '') {
			self::jsonError('バックアップの作成に失敗しました。', 500);
		}
		self::pruneOldBackups();
		/* M20 fix: 推測不可能なランダムファイル名を使用 */
		$tmp = sys_get_temp_dir().'/ap_update_'.bin2hex(random_bytes(16)).'.zip';
		$ctx = stream_context_create(['http' => [
			'method'           => 'GET',
			'header'           => "User-Agent: AdlairePlatform/".AP_VERSION."\r\n",
			'timeout'          => 60,
			'follow_location'  => 0,
			'max_redirects'    => 0,
		]]);
		/* SSRF 防止: リダイレクトを手動で処理し、許可ドメインのみ追跡する */
		$max_hops = 5;
		$current_url = $zip_url;
		$zip_data = false;
		for($hop = 0; $hop < $max_hops; $hop++){
			$zip_data = @file_get_contents($current_url, false, $ctx);
			$status = 200;
			$location = '';
			if(isset($http_response_header)){
				foreach($http_response_header as $hdr){
					if(preg_match('#^HTTP/\S+\s+(\d+)#', $hdr, $m)) $status = (int)$m[1];
					if(preg_match('#^Location:\s*(.+)$#i', $hdr, $m)) $location = trim($m[1]);
				}
			}
			if($status >= 300 && $status < 400 && $location !== ''){
				if(!preg_match('#^https://(api\.github\.com|github\.com|codeload\.github\.com|objects\.githubusercontent\.com)/#', $location)){
					error_log('apply_update: redirect to disallowed domain: '.$location);
					self::jsonError('リダイレクト先が許可ドメインではありません。');
				}
				$current_url = $location;
				$zip_data = false;
				continue;
			}
			break;
		}
		if($zip_data === false){
			error_log('apply_update: download failed: '.$zip_url);
			if (class_exists('DiagnosticEngine')) DiagnosticEngine::log('engine', 'アップデートダウンロード失敗', ['url' => $zip_url]);
			self::jsonError('ダウンロードに失敗しました。', 502);
		}
		/* Content-Type 検証 */
		$content_type = '';
		if(isset($http_response_header)){
			foreach($http_response_header as $hdr){
				if(preg_match('#^Content-Type:\s*(.+)$#i', $hdr, $m)){
					$content_type = strtolower(trim($m[1]));
				}
			}
		}
		if($content_type !== '' && !str_contains($content_type, 'zip') && !str_contains($content_type, 'octet-stream')){
			error_log('apply_update: unexpected content-type: '.$content_type);
			self::jsonError('ダウンロードしたファイルが ZIP ではありません。', 502);
		}
		if(strlen($zip_data) > 100 * 1024 * 1024){
			self::jsonError('ダウンロードサイズが上限（100MB）を超えています。', 413);
		}
		if (!FileSystem::write($tmp, $zip_data)) {
			self::jsonError('ZIP ファイルのディスク書き込みに失敗しました。', 500);
		}
		$zip = new ZipArchive();
		if($zip->open($tmp) !== true){
			unlink($tmp);
			self::jsonError('ZIP の展開に失敗しました。', 500);
		}
		$extract_dir = sys_get_temp_dir().'/ap_update_extract_'.bin2hex(random_bytes(16));
		$ok = $zip->extractTo($extract_dir);
		$zip->close();
		unlink($tmp);
		if(!$ok){
			if (class_exists('DiagnosticEngine')) DiagnosticEngine::log('engine', 'ZIP 展開失敗', ['url' => $zip_url]);
			self::jsonError('ZIP の展開に失敗しました。', 500);
		}
		$top = glob($extract_dir.'/*', GLOB_ONLYDIR);
		$src = (is_array($top) && count($top) === 1) ? $top[0] : $extract_dir;
		$real_src = realpath($src);
		if($real_src === false){
			self::jsonError('ZIP 展開先のパス解決に失敗しました。', 500);
		}
		$exclude = ['data', 'backup'];
		/* C19 fix: ZIP Slip（パストラバーサル）防止 */
		$app_root = realpath('.');
		$iter = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($real_src, RecursiveDirectoryIterator::SKIP_DOTS),
			RecursiveIteratorIterator::SELF_FIRST
		);
		foreach($iter as $item){
			$rel   = substr($item->getRealPath(), strlen($real_src) + 1);
			if(str_contains($rel, '..')) {
				if (class_exists('DiagnosticEngine')) DiagnosticEngine::log('security', 'ZIP Slip パストラバーサル検出', ['rel' => $rel]);
				continue;
			}
			$parts = explode(DIRECTORY_SEPARATOR, $rel);
			if(in_array($parts[0], $exclude, true)) continue;
			$dest = $app_root . DIRECTORY_SEPARATOR . $rel;
			/* 最終パスがアプリケーションルート内であることを確認 */
			if($item->isDir()){
				@mkdir($dest, 0755, true);
			} else {
				$destDir = dirname($dest);
				$realDestDir = realpath($destDir);
				if($realDestDir === false || !str_starts_with($realDestDir, $app_root)) continue;
				if (!@copy($item->getRealPath(), $dest) && class_exists('DiagnosticEngine')) {
					DiagnosticEngine::log('engine', 'アップデート ファイルコピー失敗', ['rel' => $rel, 'error' => error_get_last()['message'] ?? '']);
				}
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

	private static function rollbackToBackup(string $backup_name): void {
		$backup_name = basename($backup_name);
		if(!preg_match('/^[0-9_]+$/', $backup_name)){
			self::jsonError('無効なバックアップ名です');
		}
		$src = 'backup/'.$backup_name;
		if(!is_dir($src)){
			self::jsonError('バックアップが見つかりません: '.h($backup_name), 404);
		}
		$real_src = realpath($src);
		if($real_src === false){
			self::jsonError('バックアップパスの解決に失敗しました。', 500);
		}
		$exclude = ['data'];
		$iter = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS),
			RecursiveIteratorIterator::SELF_FIRST
		);
		/* R11 fix: apply_update() と同等のパストラバーサル防止をロールバックにも適用 */
		$app_root = realpath('.');
		foreach($iter as $item){
			$rel   = substr($item->getRealPath(), strlen($real_src) + 1);
			if($rel === false || $rel === '' || $rel === 'meta.json') continue;
			if(str_contains($rel, '..')) {
				if (class_exists('DiagnosticEngine')) DiagnosticEngine::log('security', 'ロールバック パストラバーサル検出', ['rel' => $rel]);
				continue;
			}
			$parts = explode(DIRECTORY_SEPARATOR, $rel);
			if(in_array($parts[0], $exclude, true)) continue;
			$dest = $app_root . DIRECTORY_SEPARATOR . $rel;
			if($item->isDir()){
				@mkdir($dest, 0755, true);
			} else {
				$destDir = dirname($dest);
				$realDestDir = realpath($destDir);
				if($realDestDir === false || !str_starts_with($realDestDir, $app_root)) continue;
				if (!@copy($item->getRealPath(), $dest) && class_exists('DiagnosticEngine')) {
					DiagnosticEngine::log('engine', 'ロールバック ファイルコピー失敗', ['rel' => $rel]);
				}
			}
		}
	}
}
