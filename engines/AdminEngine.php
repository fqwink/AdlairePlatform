<?php
/**
 * AdminEngine - 管理ツールエンジン
 *
 * 認証・CSRF・管理アクション（フィールド保存・画像アップロード・リビジョン管理）
 * およびダッシュボード（?admin）を提供する。
 */
class AdminEngine {

	/* ══════════════════════════════════════════════
	   POST アクションハンドラ
	   ══════════════════════════════════════════════ */

	/**
	 * ap_action に応じてディスパッチ → 処理 → exit
	 * 後方互換: ap_action なしで fieldname POST がある場合も edit_field として処理
	 */
	public static function handle(): void {
		$action = $_POST['ap_action'] ?? '';

		/* 後方互換: ap_action 未指定だが fieldname がある場合 */
		if ($action === '' && isset($_POST['fieldname'], $_POST['content'])) {
			$action = 'edit_field';
		}

		$valid = [
			'edit_field', 'upload_image', 'delete_page',
			'list_revisions', 'get_revision', 'restore_revision',
			'pin_revision', 'search_revisions',
			'user_add', 'user_delete',
			'redirect_add', 'redirect_delete',
		];
		if (!in_array($action, $valid, true)) return;

		/* 認証チェック（全アクション共通） */
		if (!isset($_SESSION['l']) || $_SESSION['l'] !== true) {
			http_response_code(401);
			header('Content-Type: application/json; charset=UTF-8');
			echo json_encode(['error' => '未ログイン']);
			exit;
		}
		self::verifyCsrf();

		match ($action) {
			'edit_field'        => self::handleEditField(),
			'upload_image'      => self::handleUploadImage(),
			'delete_page'       => self::handleDeletePage(),
			'user_add'          => self::handleUserAdd(),
			'user_delete'       => self::handleUserDelete(),
			'redirect_add'      => self::handleRedirectAdd(),
			'redirect_delete'   => self::handleRedirectDelete(),
			default             => self::handleRevisionAction($action),
		};
	}

	/* ══════════════════════════════════════════════
	   認証
	   ══════════════════════════════════════════════ */

	private const USERS_FILE = 'users.json';

	public static function isLoggedIn(): bool {
		return isset($_SESSION['l']) && $_SESSION['l'] === true;
	}

	/** 現在のユーザーのロールを取得 */
	public static function currentRole(): string {
		if (!self::isLoggedIn()) return '';
		return $_SESSION['ap_role'] ?? 'admin';
	}

	/** 現在のユーザー名を取得 */
	public static function currentUsername(): string {
		if (!self::isLoggedIn()) return '';
		return $_SESSION['ap_username'] ?? 'admin';
	}

	/** 指定ロール以上の権限があるかチェック */
	public static function hasRole(string $requiredRole): bool {
		if (!self::isLoggedIn()) return false;
		$roleLevel = ['viewer' => 1, 'editor' => 2, 'admin' => 3];
		$current = $roleLevel[self::currentRole()] ?? 0;
		$required = $roleLevel[$requiredRole] ?? 0;
		return $current >= $required;
	}

	/* ── マルチユーザー管理 ── */

	/** ユーザー一覧を取得（パスワードハッシュは除外） */
	public static function listUsers(): array {
		$users = json_read(self::USERS_FILE, settings_dir());
		if (empty($users)) return [];
		$result = [];
		foreach ($users as $username => $user) {
			$result[] = [
				'username'   => $username,
				'role'       => $user['role'] ?? 'editor',
				'created_at' => $user['created_at'] ?? '',
				'active'     => $user['active'] ?? true,
			];
		}
		return $result;
	}

	/** ユーザーを追加 */
	public static function addUser(string $username, string $password, string $role = 'editor'): bool {
		if (!preg_match('/^[a-zA-Z0-9_\-]{3,30}$/', $username)) return false;
		if (!in_array($role, ['admin', 'editor', 'viewer'], true)) return false;

		$users = json_read(self::USERS_FILE, settings_dir());
		if (isset($users[$username])) return false;

		$users[$username] = [
			'password_hash' => password_hash($password, PASSWORD_BCRYPT),
			'role'          => $role,
			'created_at'    => date('c'),
			'active'        => true,
		];
		json_write(self::USERS_FILE, $users, settings_dir());
		return true;
	}

	/** ユーザーを削除 */
	public static function deleteUser(string $username): bool {
		$users = json_read(self::USERS_FILE, settings_dir());
		if (!isset($users[$username])) return false;
		unset($users[$username]);
		json_write(self::USERS_FILE, $users, settings_dir());
		return true;
	}

	/** ユーザーのロールを変更 */
	public static function updateUserRole(string $username, string $role): bool {
		if (!in_array($role, ['admin', 'editor', 'viewer'], true)) return false;
		$users = json_read(self::USERS_FILE, settings_dir());
		if (!isset($users[$username])) return false;
		$users[$username]['role'] = $role;
		json_write(self::USERS_FILE, $users, settings_dir());
		return true;
	}

	/**
	 * マルチユーザーログイン試行。
	 * users.json にユーザーが定義されていればそちらで認証。
	 */
	private static function tryMultiUserLogin(string $username, string $password): ?array {
		$users = json_read(self::USERS_FILE, settings_dir());
		if (empty($users)) return null; /* マルチユーザー未設定 */
		if (!isset($users[$username])) return null;
		$user = $users[$username];
		if (!($user['active'] ?? true)) return null;
		if (!password_verify($password, $user['password_hash'] ?? '')) return null;
		return [
			'username' => $username,
			'role'     => $user['role'] ?? 'editor',
		];
	}

	/**
	 * ログイン処理（パスワード検証・パスワード変更）
	 */
	public static function login(string $passwordHash): string {
		self::verifyCsrf();
		$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
		if (!self::checkLoginRate($ip)) {
			$remaining = self::getLockoutRemaining($ip);
			return '試行回数が多すぎます。' . $remaining . '分後に再試行してください。';
		}

		$password = $_POST['password'] ?? '';
		$username = $_POST['username'] ?? '';

		/* マルチユーザーログイン試行（提案10） */
		if ($username !== '') {
			$multiUser = self::tryMultiUserLogin($username, $password);
			if ($multiUser !== null) {
				self::clearLoginRate($ip);
				session_regenerate_id(true);
				$_SESSION['l'] = true;
				$_SESSION['ap_username'] = $multiUser['username'];
				$_SESSION['ap_role'] = $multiUser['role'];
				self::logActivity('ログイン: ' . $multiUser['username'] . ' (' . $multiUser['role'] . ')');
				header('Location: ./');
				exit;
			}
		}

		/* 従来の単一パスワード認証（後方互換） */
		if (!password_verify($password, $passwordHash)) {
			self::recordLoginFailure($ip);
			$attemptsLeft = self::getRemainingAttempts($ip);
			if ($attemptsLeft > 0) {
				return 'パスワードが違います（残り' . $attemptsLeft . '回）';
			}
			return 'wrong password';
		}
		self::clearLoginRate($ip);
		if (!empty($_POST['new'])) {
			self::savePassword($_POST['new']);
			return 'password changed';
		}
		session_regenerate_id(true);
		$_SESSION['l'] = true;
		$_SESSION['ap_username'] = 'admin';
		$_SESSION['ap_role'] = 'admin';
		header('Location: ./');
		exit;
	}

	public static function savePassword(string $p): string {
		$hash = password_hash($p, PASSWORD_BCRYPT);
		json_write('auth.json', ['password_hash' => $hash], settings_dir());
		return $hash;
	}

	/* ── レート制限 ── */

	private static function checkLoginRate(string $ip): bool {
		$data     = json_read('login_attempts.json', settings_dir());
		$attempts = $data[$ip] ?? ['count' => 0, 'locked_until' => 0];
		return time() >= (int)$attempts['locked_until'];
	}

	private static function recordLoginFailure(string $ip): void {
		$data     = json_read('login_attempts.json', settings_dir());
		$attempts = $data[$ip] ?? ['count' => 0, 'locked_until' => 0];
		if (time() >= (int)$attempts['locked_until']) {
			$attempts['count']++;
		}
		if ($attempts['count'] >= 5) {
			$attempts['locked_until'] = time() + 900; /* 15分ロックアウト */
			$attempts['count']        = 0;
		}
		$data[$ip] = $attempts;
		json_write('login_attempts.json', $data, settings_dir());
	}

	private static function clearLoginRate(string $ip): void {
		$data = json_read('login_attempts.json', settings_dir());
		unset($data[$ip]);
		json_write('login_attempts.json', $data, settings_dir());
	}

	private static function getLockoutRemaining(string $ip): int {
		$data     = json_read('login_attempts.json', settings_dir());
		$attempts = $data[$ip] ?? ['count' => 0, 'locked_until' => 0];
		$remaining = (int)$attempts['locked_until'] - time();
		return max(1, (int)ceil($remaining / 60));
	}

	private static function getRemainingAttempts(string $ip): int {
		$data     = json_read('login_attempts.json', settings_dir());
		$attempts = $data[$ip] ?? ['count' => 0, 'locked_until' => 0];
		return max(0, 5 - (int)$attempts['count']);
	}

	/* ══════════════════════════════════════════════
	   CSRF
	   ══════════════════════════════════════════════ */

	public static function csrfToken(): string {
		if (empty($_SESSION['csrf'])) {
			$_SESSION['csrf'] = bin2hex(random_bytes(32));
		}
		return $_SESSION['csrf'];
	}

	public static function verifyCsrf(): void {
		$token = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
		if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $token)) {
			header('HTTP/1.1 403 Forbidden');
			exit;
		}
	}

	/* ══════════════════════════════════════════════
	   フィールド保存
	   ══════════════════════════════════════════════ */

	private static function handleEditField(): void {
		$fieldname = $_POST['fieldname'] ?? '';
		$content   = trim($_POST['content'] ?? '');
		if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $fieldname)) {
			http_response_code(400);
			header('Content-Type: application/json; charset=UTF-8');
			echo json_encode(['error' => '不正なフィールド名']);
			exit;
		}
		$settings_keys = ['title', 'description', 'keywords', 'copyright', 'themeSelect', 'menu', 'subside', 'contact_email'];
		if (in_array($fieldname, $settings_keys, true)) {
			$settings = json_read('settings.json', settings_dir());
			$settings[$fieldname] = $content;
			json_write('settings.json', $settings, settings_dir());
			self::logActivity('設定変更: ' . $fieldname);
		} else {
			self::saveRevision($fieldname, $content);
			$pages = json_read('pages.json', content_dir());
			$pages[$fieldname] = $content;
			json_write('pages.json', $pages, content_dir());
			self::logActivity('ページ編集: ' . $fieldname);
			if (class_exists('WebhookEngine')) {
				WebhookEngine::dispatch('page.updated', ['slug' => $fieldname]);
			}
		}
		if (class_exists('CacheEngine')) CacheEngine::invalidateContent();
		/* M19 fix: レスポンスの Content-Type を明示（XSS 防止） */
		header('Content-Type: text/plain; charset=UTF-8');
		echo $content;
		exit;
	}

	/* ══════════════════════════════════════════════
	   画像アップロード
	   ══════════════════════════════════════════════ */

	private static function handleUploadImage(): void {
		header('Content-Type: application/json; charset=UTF-8');
		if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
			http_response_code(400);
			echo json_encode(['error' => 'ファイルエラー: ' . ($_FILES['image']['error'] ?? 'なし')]);
			exit;
		}
		$file = $_FILES['image'];
		if ($file['size'] > 2 * 1024 * 1024) {
			http_response_code(400);
			echo json_encode(['error' => 'ファイルサイズが上限（2MB）を超えています']);
			exit;
		}
		$finfo = new finfo(FILEINFO_MIME_TYPE);
		$mime  = $finfo->file($file['tmp_name']);
		$ext_map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
		if (!isset($ext_map[$mime])) {
			http_response_code(400);
			echo json_encode(['error' => '許可されていないファイル形式です（JPEG/PNG/GIF/WebP のみ）']);
			exit;
		}
		$dir = 'uploads/';
		if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
			http_response_code(500);
			echo json_encode(['error' => 'アップロードディレクトリを作成できません']);
			exit;
		}
		$filename = bin2hex(random_bytes(12)) . '.' . $ext_map[$mime];
		if (!move_uploaded_file($file['tmp_name'], $dir . $filename)) {
			http_response_code(500);
			echo json_encode(['error' => 'ファイル保存に失敗しました']);
			exit;
		}
		/* 画像最適化（提案8: リサイズ + サムネイル + WebP） */
		if (class_exists('ImageOptimizer')) {
			ImageOptimizer::optimize($dir . $filename);
		}
		self::logActivity('画像アップロード: ' . $filename);
		echo json_encode(['url' => $dir . $filename]);
		exit;
	}

	/* ══════════════════════════════════════════════
	   ページ削除
	   ══════════════════════════════════════════════ */

	private static function handleDeletePage(): void {
		header('Content-Type: application/json; charset=UTF-8');
		$slug = $_POST['slug'] ?? '';
		if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $slug)) {
			http_response_code(400);
			echo json_encode(['error' => '不正なページ名']);
			exit;
		}
		$pages = json_read('pages.json', content_dir());
		if (!isset($pages[$slug])) {
			http_response_code(404);
			echo json_encode(['error' => 'ページが見つかりません']);
			exit;
		}
		/* 削除前にリビジョンとして保存 */
		self::saveRevision($slug, $pages[$slug]);
		unset($pages[$slug]);
		json_write('pages.json', $pages, content_dir());
		self::logActivity('ページ削除: ' . $slug);
		echo json_encode(['ok' => true]);
		exit;
	}

	/* ══════════════════════════════════════════════
	   アクティビティログ
	   ══════════════════════════════════════════════ */

	public static function logActivity(string $message): void {
		$log = json_read('activity.json', settings_dir());
		$entry = [
			'time'    => date('c'),
			'message' => $message,
		];
		/* ユーザー名を記録（マルチユーザー対応） */
		$username = $_SESSION['ap_username'] ?? '';
		if ($username !== '') {
			$entry['user'] = $username;
		}
		array_unshift($log, $entry);
		/* 最新100件のみ保持 */
		$log = array_slice($log, 0, 100);
		json_write('activity.json', $log, settings_dir());
	}

	public static function getRecentActivity(int $limit = 20): array {
		$log = json_read('activity.json', settings_dir());
		return array_slice($log, 0, $limit);
	}

	/* ══════════════════════════════════════════════
	   リビジョン管理
	   ══════════════════════════════════════════════ */

	public static function saveRevision(string $fieldname, string $content, bool $restored = false): void {
		if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $fieldname)) return;
		$dir = content_dir() . '/revisions/' . $fieldname . '/';
		if (!is_dir($dir)) mkdir($dir, 0755, true);

		$lockFile = $dir . '.lock';
		$lf = fopen($lockFile, 'c');
		if ($lf === false) return;
		if (!flock($lf, LOCK_EX)) { fclose($lf); return; }

		$ts = date('Ymd_His');
		$rev = [
			'timestamp' => date('c'),
			'content'   => $content,
			'size'      => strlen($content),
			'user'      => $_SESSION['ap_username'] ?? '',
			'restored'  => $restored,
		];
		file_put_contents(
			$dir . 'rev_' . $ts . '.json',
			json_encode($rev, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
			LOCK_EX
		);
		self::pruneRevisions($dir);

		flock($lf, LOCK_UN);
		fclose($lf);
	}

	private static function pruneRevisions(string $dir): void {
		$files = glob($dir . 'rev_*.json') ?: [];
		sort($files);
		$unpinned = [];
		foreach ($files as $f) {
			$data = json_decode(file_get_contents($f), true);
			if (is_array($data) && !empty($data['pinned'])) continue;
			$unpinned[] = $f;
		}
		while (count($unpinned) > AP_REVISION_LIMIT) {
			unlink(array_shift($unpinned));
		}
	}

	private static function handleRevisionAction(string $action): void {
		header('Content-Type: application/json; charset=UTF-8');

		/* レート制限（セッション単位、60秒あたり30リクエスト） */
		$now = time();
		$_SESSION['_rev_requests'] = $_SESSION['_rev_requests'] ?? [];
		$_SESSION['_rev_requests'] = array_filter($_SESSION['_rev_requests'], fn($t) => $t > $now - 60);
		if (count($_SESSION['_rev_requests']) >= 30) {
			http_response_code(429);
			echo json_encode(['error' => 'Too many requests']);
			exit;
		}
		$_SESSION['_rev_requests'][] = $now;

		match ($action) {
			'list_revisions'   => self::listRevisions(),
			'get_revision'     => self::getRevision(),
			'restore_revision' => self::restoreRevision(),
			'pin_revision'     => self::pinRevision(),
			'search_revisions' => self::searchRevisions(),
			default            => (function() { http_response_code(400); echo json_encode(['error' => 'Unknown action']); exit; })(),
		};
	}

	private static function listRevisions(): void {
		$fieldname = $_POST['fieldname'] ?? '';
		if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $fieldname)) {
			http_response_code(400);
			echo json_encode(['error' => 'Invalid fieldname']);
			exit;
		}
		$offset = max(0, (int)($_POST['offset'] ?? 0));
		$limit  = min(50, max(1, (int)($_POST['limit'] ?? 20)));

		$dir = content_dir() . '/revisions/' . $fieldname . '/';
		$files = glob($dir . 'rev_*.json') ?: [];
		rsort($files);
		$total = count($files);
		$files = array_slice($files, $offset, $limit);
		$revisions = [];
		foreach ($files as $f) {
			$data = json_decode(file_get_contents($f), true);
			if (!is_array($data)) continue;
			$revisions[] = [
				'file'      => basename($f, '.json'),
				'timestamp' => $data['timestamp'] ?? '',
				'size'      => $data['size'] ?? 0,
				'user'      => $data['user'] ?? '',
				'restored'  => !empty($data['restored']),
				'pinned'    => !empty($data['pinned']),
			];
		}
		echo json_encode(['revisions' => $revisions, 'total' => $total, 'offset' => $offset, 'limit' => $limit]);
		exit;
	}

	private static function getRevision(): void {
		$fieldname = $_POST['fieldname'] ?? '';
		$revFile   = $_POST['revision'] ?? '';
		if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $fieldname) ||
		    !preg_match('/^rev_[0-9_]+$/', $revFile)) {
			http_response_code(400);
			echo json_encode(['error' => 'Invalid parameters']);
			exit;
		}
		$path = content_dir() . '/revisions/' . $fieldname . '/' . $revFile . '.json';
		if (!file_exists($path)) {
			http_response_code(404);
			echo json_encode(['error' => 'Revision not found']);
			exit;
		}
		$rev = json_decode(file_get_contents($path), true);
		if (!is_array($rev) || !isset($rev['content'])) {
			http_response_code(500);
			echo json_encode(['error' => 'Invalid revision data']);
			exit;
		}
		echo json_encode(['ok' => true, 'content' => $rev['content']]);
		exit;
	}

	private static function restoreRevision(): void {
		$fieldname = $_POST['fieldname'] ?? '';
		$revFile   = $_POST['revision'] ?? '';
		if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $fieldname) ||
		    !preg_match('/^rev_[0-9_]+$/', $revFile)) {
			http_response_code(400);
			echo json_encode(['error' => 'Invalid parameters']);
			exit;
		}
		$path = content_dir() . '/revisions/' . $fieldname . '/' . $revFile . '.json';
		if (!file_exists($path)) {
			http_response_code(404);
			echo json_encode(['error' => 'Revision not found']);
			exit;
		}
		$rev = json_decode(file_get_contents($path), true);
		if (!is_array($rev) || !isset($rev['content'])) {
			http_response_code(500);
			echo json_encode(['error' => 'Invalid revision data']);
			exit;
		}
		$content = $rev['content'];
		self::saveRevision($fieldname, $content, true);
		$pages = json_read('pages.json', content_dir());
		$pages[$fieldname] = $content;
		json_write('pages.json', $pages, content_dir());
		self::logActivity('リビジョン復元: ' . $fieldname);
		echo json_encode(['ok' => true, 'content' => $content]);
		exit;
	}

	private static function pinRevision(): void {
		$fieldname = $_POST['fieldname'] ?? '';
		$revFile   = $_POST['revision'] ?? '';
		if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $fieldname) ||
		    !preg_match('/^rev_[0-9_]+$/', $revFile)) {
			http_response_code(400);
			echo json_encode(['error' => 'Invalid parameters']);
			exit;
		}
		$path = content_dir() . '/revisions/' . $fieldname . '/' . $revFile . '.json';
		if (!file_exists($path)) {
			http_response_code(404);
			echo json_encode(['error' => 'Revision not found']);
			exit;
		}
		$rev = json_decode(file_get_contents($path), true);
		if (!is_array($rev)) {
			http_response_code(500);
			echo json_encode(['error' => 'Invalid revision data']);
			exit;
		}
		$rev['pinned'] = empty($rev['pinned']);
		file_put_contents($path,
			json_encode($rev, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
			LOCK_EX);
		echo json_encode(['ok' => true, 'pinned' => $rev['pinned']]);
		exit;
	}

	private static function searchRevisions(): void {
		$fieldname = $_POST['fieldname'] ?? '';
		$query     = $_POST['query'] ?? '';
		if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $fieldname)) {
			http_response_code(400);
			echo json_encode(['error' => 'Invalid fieldname']);
			exit;
		}
		$dir = content_dir() . '/revisions/' . $fieldname . '/';
		$files = glob($dir . 'rev_*.json') ?: [];
		rsort($files);
		$results = [];
		foreach ($files as $f) {
			$data = json_decode(file_get_contents($f), true);
			if (!is_array($data)) continue;
			if ($query !== '' && stripos($data['content'] ?? '', $query) === false) continue;
			$results[] = [
				'file'      => basename($f, '.json'),
				'timestamp' => $data['timestamp'] ?? '',
				'size'      => $data['size'] ?? 0,
				'user'      => $data['user'] ?? '',
				'restored'  => !empty($data['restored']),
				'pinned'    => !empty($data['pinned']),
			];
		}
		echo json_encode(['revisions' => $results]);
		exit;
	}

	/* ══════════════════════════════════════════════
	   ユーザー管理ハンドラ（提案10）
	   ══════════════════════════════════════════════ */

	private static function handleUserAdd(): void {
		header('Content-Type: application/json; charset=UTF-8');
		if (!self::hasRole('admin')) {
			http_response_code(403);
			echo json_encode(['ok' => false, 'error' => '管理者権限が必要です']);
			exit;
		}
		$username = trim($_POST['username'] ?? '');
		$password = $_POST['password'] ?? '';
		$role = trim($_POST['role'] ?? 'editor');
		if ($username === '' || $password === '') {
			http_response_code(400);
			echo json_encode(['ok' => false, 'error' => 'ユーザー名とパスワードは必須です']);
			exit;
		}
		if (!self::addUser($username, $password, $role)) {
			http_response_code(400);
			echo json_encode(['ok' => false, 'error' => 'ユーザーの追加に失敗しました（既に存在するか、不正な名前です）']);
			exit;
		}
		self::logActivity("ユーザー追加: {$username} ({$role})");
		echo json_encode(['ok' => true, 'data' => ['username' => $username, 'role' => $role]]);
		exit;
	}

	private static function handleUserDelete(): void {
		header('Content-Type: application/json; charset=UTF-8');
		if (!self::hasRole('admin')) {
			http_response_code(403);
			echo json_encode(['ok' => false, 'error' => '管理者権限が必要です']);
			exit;
		}
		$username = trim($_POST['username'] ?? '');
		if ($username === self::currentUsername()) {
			http_response_code(400);
			echo json_encode(['ok' => false, 'error' => '自分自身は削除できません']);
			exit;
		}
		if (!self::deleteUser($username)) {
			http_response_code(400);
			echo json_encode(['ok' => false, 'error' => 'ユーザーの削除に失敗しました']);
			exit;
		}
		self::logActivity("ユーザー削除: {$username}");
		echo json_encode(['ok' => true, 'data' => ['deleted' => $username]]);
		exit;
	}

	/* ══════════════════════════════════════════════
	   リダイレクト管理
	   ══════════════════════════════════════════════ */

	private static function handleRedirectAdd(): void {
		header('Content-Type: application/json; charset=UTF-8');
		/* M5 fix: 管理者権限チェック */
		if (!self::hasRole('admin')) {
			http_response_code(403);
			echo json_encode(['ok' => false, 'error' => '管理者権限が必要です']);
			exit;
		}
		$from = trim($_POST['from'] ?? '');
		$to   = trim($_POST['to'] ?? '');
		$code = (int)($_POST['code'] ?? 301);
		if ($from === '' || $to === '') {
			http_response_code(400);
			echo json_encode(['ok' => false, 'error' => '旧URLと新URLは必須です']);
			exit;
		}
		/* C7 fix: URL バリデーション — from はパス、制御文字除去 */
		$from = preg_replace('/[\r\n\x00-\x1f]/', '', $from);
		$to   = preg_replace('/[\r\n\x00-\x1f]/', '', $to);
		if (!str_starts_with($from, '/')) {
			http_response_code(400);
			echo json_encode(['ok' => false, 'error' => '旧URLは / で始まる必要があります']);
			exit;
		}
		if (!in_array($code, [301, 302], true)) $code = 301;
		$redirects = json_read('redirects.json', settings_dir());
		/* M6 fix: 重複チェック */
		foreach ($redirects as $r) {
			if (($r['from'] ?? '') === $from) {
				http_response_code(400);
				echo json_encode(['ok' => false, 'error' => 'この旧URLのリダイレクトは既に存在します']);
				exit;
			}
		}
		$redirects[] = ['from' => $from, 'to' => $to, 'code' => $code];
		json_write('redirects.json', $redirects, settings_dir());
		self::logActivity("リダイレクト追加: {$from} → {$to}");
		echo json_encode(['ok' => true]);
		exit;
	}

	private static function handleRedirectDelete(): void {
		header('Content-Type: application/json; charset=UTF-8');
		/* M5 fix: 管理者権限チェック */
		if (!self::hasRole('admin')) {
			http_response_code(403);
			echo json_encode(['ok' => false, 'error' => '管理者権限が必要です']);
			exit;
		}
		$index = (int)($_POST['index'] ?? -1);
		$redirects = json_read('redirects.json', settings_dir());
		if ($index < 0 || $index >= count($redirects)) {
			http_response_code(400);
			echo json_encode(['ok' => false, 'error' => '無効なインデックス']);
			exit;
		}
		array_splice($redirects, $index, 1);
		json_write('redirects.json', $redirects, settings_dir());
		self::logActivity("リダイレクト削除: #{$index}");
		echo json_encode(['ok' => true]);
		exit;
	}

	/* ══════════════════════════════════════════════
	   ログインページ
	   ══════════════════════════════════════════════ */

	public static function renderLogin(string $message = ''): string {
		global $c;
		$tplPath = __DIR__ . '/AdminEngine/login.html';
		if (!file_exists($tplPath)) {
			return '<h1>Login template not found</h1>';
		}
		$tpl = file_get_contents($tplPath);
		if ($tpl === false) {
			return '<h1>Login template read error</h1>';
		}
		$ctx = [
			'title'         => $c['title'] ?? 'Login',
			'csrf_token'    => self::csrfToken(),
			'login_message' => $message,
		];
		return TemplateEngine::render($tpl, $ctx, __DIR__ . '/AdminEngine');
	}

	/* ══════════════════════════════════════════════
	   コンテンツレンダリング補助
	   ══════════════════════════════════════════════ */

	/**
	 * ログイン時は editRich span を付与、非ログイン時は生コンテンツ
	 */
	public static function renderEditableContent(string $id, string $content, string $placeholder = 'Click to edit!'): string {
		if (self::isLoggedIn()) {
			return "<span title='" . h($placeholder)
				. "' id='" . h($id) . "' class='editRich'>" . $content . "</span>";
		}
		return $content;
	}

	/* ══════════════════════════════════════════════
	   フック管理
	   ══════════════════════════════════════════════ */

	/**
	 * admin-head フックに JsEngine スクリプトを登録
	 */
	public static function registerHooks(): void {
		global $hook;
		$hook['admin-head'][] = "\n\t<script src='engines/JsEngine/autosize.js'></script>";
		$hook['admin-head'][] = "\n\t<script src='engines/JsEngine/editInplace.js'></script>";
		$hook['admin-head'][] = "\n\t<script src='engines/JsEngine/wysiwyg.js'></script>";
		$hook['admin-head'][] = "\n\t<script src='engines/JsEngine/updater.js'></script>";
	}

	/**
	 * admin-head フック内容を文字列として返却
	 */
	public static function getAdminScripts(): string {
		global $hook;
		$scripts = '';
		foreach (($hook['admin-head'] ?? []) as $o) {
			$scripts .= "\t" . $o . "\n";
		}
		return $scripts;
	}

	/* ══════════════════════════════════════════════
	   ダッシュボード
	   ══════════════════════════════════════════════ */

	/**
	 * ダッシュボードをレンダリングして返却
	 */
	public static function renderDashboard(): string {
		$tplPath = __DIR__ . '/AdminEngine/dashboard.html';
		if (!file_exists($tplPath)) {
			return '<h1>Dashboard template not found</h1>';
		}
		$tpl = file_get_contents($tplPath);
		if ($tpl === false) {
			return '<h1>Dashboard template read error</h1>';
		}
		$ctx = self::buildDashboardContext();
		return TemplateEngine::render($tpl, $ctx, __DIR__ . '/AdminEngine');
	}

	/**
	 * ダッシュボード用コンテキスト変数を構築
	 */
	public static function buildDashboardContext(): array {
		global $c, $d, $host;

		/* テーマ選択 HTML */
		$selectHtml = "<select name='themeSelect' id='ap-theme-select'>";
		foreach (ThemeEngine::listThemes() as $val) {
			$selected = ($val == ($c['themeSelect'] ?? '')) ? ' selected' : '';
			$selectHtml .= '<option value="' . h($val) . '"' . $selected . '>' . h($val) . "</option>\n";
		}
		$selectHtml .= '</select>';

		/* 設定フィールド */
		$fields = [];
		foreach (['title', 'description', 'keywords', 'copyright'] as $key) {
			$fields[] = [
				'key'           => $key,
				'default_value' => $d['default'][$key] ?? '',
				'value'         => $c[$key] ?? '',
			];
		}

		/* contact_email（settings.json から直接読み込み） */
		$_s = json_read('settings.json', settings_dir());
		$contactEmail = $_s['contact_email'] ?? '';

		/* ページ一覧 */
		$pages = json_read('pages.json', content_dir());
		$pageList = [];
		foreach ($pages as $slug => $content) {
			$preview = mb_substr(strip_tags((string)$content), 0, 80, 'UTF-8');
			$pageList[] = [
				'slug'    => $slug,
				'preview' => $preview,
			];
		}

		/* ディスク空き容量 */
		$diskFree = @disk_free_space('.');
		$diskFreeStr = ($diskFree !== false)
			? number_format($diskFree / 1024 / 1024, 0) . ' MB'
			: '取得不可';

		/* コレクション情報 */
		$collectionsEnabled = class_exists('CollectionEngine') && CollectionEngine::isEnabled();
		$collectionList = $collectionsEnabled ? CollectionEngine::listCollections() : [];

		/* Git 連携情報 */
		$gitEnabled = class_exists('GitEngine') && GitEngine::isEnabled();
		$gitConfig = class_exists('GitEngine') ? GitEngine::loadConfig() : [];

		/* マルチユーザー情報（提案10） */
		$users = self::listUsers();
		$hasUsers = !empty($users);

		/* Webhook 情報（提案4） */
		$webhooks = class_exists('WebhookEngine') ? WebhookEngine::listWebhooks() : [];

		/* キャッシュ情報（提案9） */
		$cacheStats = class_exists('CacheEngine') ? CacheEngine::getStats() : ['files' => 0, 'size_human' => '0 B'];

		/* リダイレクト情報 */
		$redirects = json_read('redirects.json', settings_dir());
		$redirectList = [];
		foreach ($redirects as $i => $r) {
			$redirectList[] = ['from' => $r['from'] ?? '', 'to' => $r['to'] ?? '', 'code' => $r['code'] ?? 301, 'index' => $i];
		}

		return [
			'title'                => $c['title'] ?? '',
			'host'                 => $host ?? '',
			'ap_version'           => AP_VERSION,
			'csrf_token'           => self::csrfToken(),
			'theme_select_html'    => $selectHtml,
			/* M25 fix: メニュー表示時に XSS 防止（br タグのみ許可） */
			'menu_raw'             => strip_tags($c['menu'] ?? '', '<br>'),
			'settings_fields'      => $fields,
			'pages'                => $pageList,
			'has_pages'            => !empty($pageList),
			'php_version'          => PHP_VERSION,
			'disk_free'            => $diskFreeStr,
			'migrate_warning'      => !empty($c['migrate_warning']),
			'contact_email'        => $contactEmail,
			'activity_log'         => ($activityLog = self::getRecentActivity(20)),
			'has_activity'         => !empty($activityLog),
			'collections_enabled'  => $collectionsEnabled,
			'collections'          => $collectionList,
			'has_collections'      => !empty($collectionList),
			'git_enabled'          => $gitEnabled,
			'git_repository'       => $gitConfig['repository'] ?? '',
			'git_branch'           => $gitConfig['branch'] ?? 'main',
			'git_content_dir'      => $gitConfig['content_dir'] ?? 'content',
			'git_last_sync'        => $gitConfig['last_sync'] ?? '',
			'git_issues_enabled'   => !empty($gitConfig['issues_enabled']),
			'git_webhook_secret'   => $gitConfig['webhook_secret'] ?? '',
			/* マルチユーザー（提案10） */
			'current_user'         => self::currentUsername(),
			'current_role'         => self::currentRole(),
			'users'                => $users,
			'has_users'            => $hasUsers,
			/* Webhook（提案4） */
			'webhooks'             => $webhooks,
			'has_webhooks'         => !empty($webhooks),
			/* キャッシュ（提案9） */
			'cache_files'          => $cacheStats['files'] ?? 0,
			'cache_size'           => $cacheStats['size_human'] ?? '0 B',
			/* リダイレクト */
			'redirects'            => $redirectList,
			'has_redirects'        => !empty($redirectList),
		];
	}
}
