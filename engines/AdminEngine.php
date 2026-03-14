<?php
/**
 * AdminEngine - 管理ツールエンジン
 *
 * 認証・CSRF・管理アクション（フィールド保存・画像アップロード・リビジョン管理）
 * およびダッシュボード（?admin）を提供する。
 *
 * Ver.1.5: ACE\Admin\AuthManager に内部委譲。既存 static API は完全維持。
 */
class AdminEngine {

	/** @var \ACE\Admin\AuthManager|null Ver.1.5 Framework 認証マネージャ */
	private static ?\ACE\Admin\AuthManager $authManager = null;

	/**
	 * Ver.1.5: Framework AuthManager インスタンスを取得する
	 */
	public static function getAuthManager(): \ACE\Admin\AuthManager {
		if (self::$authManager === null) {
			self::$authManager = new \ACE\Admin\AuthManager(settings_dir());
		}
		return self::$authManager;
	}

	/* ══════════════════════════════════════════════
	   認証
	   ══════════════════════════════════════════════ */

	/** @since Ver.1.7-36 public 化（AuthController から参照） */
	public const USERS_FILE = 'users.json';

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
			'password_hash' => password_hash($password, self::preferredHashAlgo()),
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
		/* Ver.1.6: マルチユーザーもリハッシュ対応 */
		if (password_needs_rehash($user['password_hash'] ?? '', self::preferredHashAlgo())) {
			$users[$username]['password_hash'] = password_hash($password, self::preferredHashAlgo());
			json_write(self::USERS_FILE, $users, settings_dir());
			Logger::info('ユーザー "' . $username . '" のパスワードハッシュを Argon2id へアップグレードしました');
		}
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
			return I18n::t('auth.too_many_attempts', ['remaining' => $remaining]);
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
				if (class_exists('DiagnosticEngine')) DiagnosticEngine::log('security', 'ログイン成功', ['username' => $multiUser['username'], 'role' => $multiUser['role']]);
				header('Location: ./');
				exit;
			}
		}

		/* 従来の単一パスワード認証（後方互換） */
		if (!password_verify($password, $passwordHash)) {
			self::recordLoginFailure($ip);
			if (class_exists('DiagnosticEngine')) DiagnosticEngine::log('security', 'ログイン失敗（パスワード不一致）');
			$attemptsLeft = self::getRemainingAttempts($ip);
			if ($attemptsLeft > 0) {
				return I18n::t('auth.wrong_password', ['attempts' => $attemptsLeft]);
			}
			return I18n::t('auth.wrong_password_final');
		}
		self::clearLoginRate($ip);
		/* Ver.1.6: ログイン成功時に旧ハッシュを Argon2id へ自動リハッシュ */
		if (password_needs_rehash($passwordHash, self::preferredHashAlgo())) {
			self::savePassword($password);
			Logger::info('パスワードハッシュを Argon2id へアップグレードしました');
		}
		if (!empty($_POST['new'])) {
			self::savePassword($_POST['new']);
			return I18n::t('auth.password_changed');
		}
		session_regenerate_id(true);
		$_SESSION['l'] = true;
		$_SESSION['ap_username'] = 'admin';
		$_SESSION['ap_role'] = 'admin';
		if (class_exists('DiagnosticEngine')) DiagnosticEngine::log('security', 'ログイン成功', ['username' => 'admin', 'role' => 'admin']);
		header('Location: ./');
		exit;
	}

	/**
	 * Ver.1.6: Argon2id 優先、非対応環境では bcrypt にフォールバック
	 */
	private static function preferredHashAlgo(): string|int {
		return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
	}

	public static function savePassword(string $p): string {
		$hash = password_hash($p, self::preferredHashAlgo());
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
			if (class_exists('DiagnosticEngine')) DiagnosticEngine::log('security', 'ロックアウト発動（5回連続ログイン失敗）');
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
			$reason = empty($_SESSION['csrf']) ? 'session_empty' : (empty($token) ? 'token_missing' : 'token_mismatch');
			if (class_exists('DiagnosticEngine')) DiagnosticEngine::log('security', 'CSRF 検証失敗', ['reason' => $reason]);
			header('HTTP/1.1 403 Forbidden');
			exit;
		}
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
		if (!is_dir($dir)) {
			if (!@mkdir($dir, 0755, true) && class_exists('DiagnosticEngine')) {
				DiagnosticEngine::logEnvironmentIssue('リビジョンディレクトリ作成失敗', ['dir' => $fieldname, 'error' => error_get_last()['message'] ?? '']);
			}
		}

		$lockFile = $dir . '.lock';
		$lf = fopen($lockFile, 'c');
		if ($lf === false) {
			if (class_exists('DiagnosticEngine')) DiagnosticEngine::logRaceCondition('revisions/' . $fieldname, 'ロックファイルオープン失敗');
			return;
		}
		try {
			if (!flock($lf, LOCK_EX)) {
				if (class_exists('DiagnosticEngine')) DiagnosticEngine::logRaceCondition('revisions/' . $fieldname, 'ファイルロック取得失敗');
				return;
			}

			/* R29 fix: 同一秒のリビジョン衝突を防止（ランダムサフィックス追加） */
			$ts = date('Ymd_His') . '_' . bin2hex(random_bytes(2));
			$rev = [
				'timestamp' => date('c'),
				'content'   => $content,
				'size'      => strlen($content),
				'user'      => $_SESSION['ap_username'] ?? '',
				'restored'  => $restored,
			];
			FileSystem::writeJson($dir . 'rev_' . $ts . '.json', $rev);
			self::pruneRevisions($dir);
		} catch (\Throwable $e) {
			Logger::error('リビジョン保存中にエラー', ['engine' => 'AdminEngine', 'field' => $fieldname, 'error' => $e->getMessage()]);
			if (class_exists('DiagnosticEngine')) DiagnosticEngine::log('runtime', 'リビジョン保存例外', ['field' => $fieldname, 'trace' => $e->getTraceAsString()]);
		} finally {
			flock($lf, LOCK_UN);
			fclose($lf);
		}
	}

	private static function pruneRevisions(string $dir): void {
		$files = glob($dir . 'rev_*.json') ?: [];
		sort($files);
		$unpinned = [];
		foreach ($files as $f) {
			$data = FileSystem::readJson($f);
			if ($data === null) continue;
			if (!empty($data['pinned'])) continue;
			$unpinned[] = $f;
		}
		$pruned = 0;
		while (count($unpinned) > AP_REVISION_LIMIT) {
			unlink(array_shift($unpinned));
			$pruned++;
		}
		if ($pruned > 0 && class_exists('DiagnosticEngine')) {
			DiagnosticEngine::log('debug', 'リビジョンクリーンアップ', ['pruned' => $pruned, 'remaining' => count($files) - $pruned]);
		}
	}

	/* ══════════════════════════════════════════════
	   ログインページ
	   ══════════════════════════════════════════════ */

	public static function renderLogin(string $message = ''): string {
		$tplPath = __DIR__ . '/AdminEngine/login.html';
		if (!file_exists($tplPath)) {
			return '<h1>Login template not found</h1>';
		}
		$tpl = FileSystem::read($tplPath);
		if ($tpl === false) {
			return '<h1>Login template read error</h1>';
		}
		/* B-3 fix: AppContext 経由でアクセス */
		$ctx = [
			'title'         => AppContext::config('title', 'Login'),
			'csrf_token'    => self::csrfToken(),
			'login_message' => $message,
			'html_lang'     => I18n::htmlLang(),
			'i18n'          => I18n::allNested(),
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
	/* B-3 fix: AppContext 経由でフック管理 */
	public static function registerHooks(): void {
		/* Ver.1.5: ADS CSS + AEB adapter を追加 */
		AppContext::addHook('admin-head', "\n\t<link rel='stylesheet' href='Framework/ADS/ADS.Base.css'>");
		AppContext::addHook('admin-head', "\n\t<link rel='stylesheet' href='Framework/ADS/ADS.Components.css'>");
		AppContext::addHook('admin-head', "\n\t<link rel='stylesheet' href='Framework/ADS/ADS.Editor.css'>");
		AppContext::addHook('admin-head', "\n\t<script src='engines/JsEngine/aeb-adapter.js' type='module'></script>");
		AppContext::addHook('admin-head', "\n\t<script src='engines/JsEngine/ap-utils.js'></script>");
		AppContext::addHook('admin-head', "\n\t<script src='engines/JsEngine/ap-events.js'></script>");
		AppContext::addHook('admin-head', "\n\t<script src='engines/JsEngine/autosize.js'></script>");
		AppContext::addHook('admin-head', "\n\t<script src='engines/JsEngine/editInplace.js'></script>");
		AppContext::addHook('admin-head', "\n\t<script src='engines/JsEngine/wysiwyg.js'></script>");
		AppContext::addHook('admin-head', "\n\t<script src='engines/JsEngine/updater.js'></script>");
	}

	/**
	 * admin-head フック内容を文字列として返却
	 */
	public static function getAdminScripts(): string {
		$scripts = '';
		foreach (AppContext::getHooks('admin-head') as $o) {
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
		$tpl = FileSystem::read($tplPath);
		if ($tpl === false) {
			return '<h1>Dashboard template read error</h1>';
		}
		$ctx = self::buildDashboardContext();
		/* 診断データ初回通知バナーを表示済みとしてマーク */
		if (class_exists('DiagnosticEngine') && DiagnosticEngine::shouldShowNotice()) {
			DiagnosticEngine::markNoticeShown();
		}
		return TemplateEngine::render($tpl, $ctx, __DIR__ . '/AdminEngine');
	}

	/**
	 * ダッシュボード用コンテキスト変数を構築
	 */
	public static function buildDashboardContext(): array {
		/* B-3 fix: AppContext 経由でアクセス（global 変数への直接依存を解消） */

		/* テーマ選択 HTML */
		$selectHtml = "<select name='themeSelect' id='ap-theme-select'>";
		foreach (ThemeEngine::listThemes() as $val) {
			$selected = ($val == AppContext::config('themeSelect', '')) ? ' selected' : '';
			$selectHtml .= '<option value="' . h($val) . '"' . $selected . '>' . h($val) . "</option>\n";
		}
		$selectHtml .= '</select>';

		/* 設定フィールド */
		$fields = [];
		foreach (['title', 'description', 'keywords', 'copyright'] as $key) {
			$fields[] = [
				'key'           => $key,
				'default_value' => AppContext::defaults('default', $key, ''),
				'value'         => AppContext::config($key, ''),
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
		if ($diskFree === false && class_exists('DiagnosticEngine')) {
			DiagnosticEngine::logEnvironmentIssue('disk_free_space() 取得失敗');
		}
		$diskFreeStr = ($diskFree !== false)
			? number_format($diskFree / 1024 / 1024, 0) . ' MB'
			: I18n::t('disk.unavailable');

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
			'title'                => AppContext::config('title', ''),
			'host'                 => AppContext::host(),
			'ap_version'           => AP_VERSION,
			'csrf_token'           => self::csrfToken(),
			'theme_select_html'    => $selectHtml,
			/* M25 fix: メニュー表示時に XSS 防止（br タグのみ許可） */
			/* BUG#5 fix: strip_tags は属性を除去しないため、on* 属性を手動で除去 */
			'menu_raw'             => preg_replace('/\s+on\w+\s*=\s*["\'][^"\']*["\']/i', '', strip_tags(AppContext::config('menu', ''), '<br>')),
			'settings_fields'      => $fields,
			'pages'                => $pageList,
			'has_pages'            => !empty($pageList),
			'php_version'          => PHP_VERSION,
			'disk_free'            => $diskFreeStr,
			'migrate_warning'      => (bool) AppContext::config('migrate_warning', false),
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
			/* 診断データ（Ver.1.4） */
			'diag_show_notice'     => class_exists('DiagnosticEngine') && DiagnosticEngine::shouldShowNotice(),
			/* i18n */
			'html_lang'            => I18n::htmlLang(),
			'ap_locale'            => I18n::getLocale(),
			'i18n'                 => I18n::allNested(),
			'i18n_json'            => json_encode(I18n::all(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG),
		];
	}
}
