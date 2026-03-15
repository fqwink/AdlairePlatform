<?php
/**
 * AP.Controllers - コントローラー統合モジュール
 *
 * 全 Controller クラスを単一ファイルに統合。
 * PHP 8.3+ 対応。
 *
 * @since Ver.1.8
 * @license Adlaire License Ver.2.0
 */
namespace AP\Controllers;

use APF\Core\{Request, Response};

/* ══════════════════════════════════════════════════
 * BaseController - Controller 基底クラス
 * ══════════════════════════════════════════════════ */

abstract class BaseController {

	/** JSON 成功レスポンス */
	protected function ok(mixed $data = null): Response {
		$body = $data !== null ? ['ok' => true, 'data' => $data] : ['ok' => true];
		return Response::json($body);
	}

	/** JSON エラーレスポンス */
	protected function error(string $message, int $status = 400): Response {
		return Response::json(['ok' => false, 'error' => $message], $status);
	}

	/** ロール検証 */
	protected function requireRole(string $role): ?Response {
		if (!\ACE\Admin\AdminManager::hasRole($role)) {
			return $this->error(\AIS\Core\I18n::t('auth.no_edit_permission'), 403);
		}
		return null;
	}

	/** POST パラメータバリデーション（正規表現） */
	protected function validateParam(Request $request, string $key, string $pattern, string $errorMsg = 'Invalid parameter'): ?Response {
		$value = $request->post($key, '');
		if (!preg_match($pattern, $value)) {
			return $this->error($errorMsg, 400);
		}
		return null;
	}

	/**
	 * Request バリデーション統合ヘルパー。
	 * 成功時はバリデーション済みデータを返し、失敗時は JSON エラーレスポンスを返す。
	 * @since Ver.1.9
	 * @return array|Response バリデーション済みデータ or エラーレスポンス
	 */
	protected function validate(Request $request, array $rules, array $messages = []): array|Response {
		try {
			return \APF\Utilities\Validator::request($request, $rules, $messages);
		} catch (\APF\Core\ValidationException $e) {
			return Response::json(['ok' => false, 'error' => 'Validation failed', 'errors' => $e->getErrors()], 422);
		}
	}
}

/* ══════════════════════════════════════════════════
 * AuthController - 認証（ログイン/ログアウト）
 * ══════════════════════════════════════════════════ */

class AuthController extends BaseController {

	/** GET /login — ログインページ表示 */
	public function showLogin(Request $request): Response {
		if (\ACE\Admin\AdminManager::isLoggedIn()) {
			return Response::redirect('./');
		}
		$html = \ACE\Admin\AdminManager::renderLogin('');
		return Response::html($html);
	}

	/** POST /login — ログイン認証 */
	public function authenticate(Request $request): Response {
		if (\ACE\Admin\AdminManager::isLoggedIn()) {
			return Response::redirect('./');
		}

		\ACE\Admin\AdminManager::verifyCsrf();

		$password = $request->post('password', '');
		$username = $request->post('username', '');
		$ip = $request->server('REMOTE_ADDR', 'unknown');

		/* レート制限チェック */
		if (!$this->checkLoginRate($ip)) {
			$remaining = $this->getLockoutRemaining($ip);
			$msg = \AIS\Core\I18n::t('auth.too_many_attempts', ['remaining' => $remaining]);
			return Response::html(\ACE\Admin\AdminManager::renderLogin($msg));
		}

		/* マルチユーザーログイン試行 */
		if ($username !== '') {
			$users = json_read(\ACE\Admin\AdminManager::USERS_FILE, settings_dir());
			if (!empty($users) && isset($users[$username])) {
				$user = $users[$username];
				if (($user['active'] ?? true) && password_verify($password, $user['password_hash'] ?? '')) {
					$this->clearLoginRate($ip);
					$this->rehashIfNeeded($users, $username, $password);
					session_regenerate_id(true);
					$_SESSION['l'] = true;
					$_SESSION['ap_username'] = $username;
					$_SESSION['ap_role'] = $user['role'] ?? 'editor';
					\ACE\Admin\AdminManager::logActivity('ログイン: ' . $username . ' (' . ($user['role'] ?? 'editor') . ')');
					\AIS\System\DiagnosticsManager::log('security', 'ログイン成功', ['username' => $username]);
					return Response::redirect('./');
				}
			}
		}

		/* 単一パスワード認証（後方互換） */
		$_auth = json_read('auth.json', settings_dir());
		$passwordHash = $_auth['password_hash'] ?? '';
		if ($passwordHash === '' || !password_verify($password, $passwordHash)) {
			$this->recordLoginFailure($ip);
			\AIS\System\DiagnosticsManager::log('security', 'ログイン失敗');
			$attemptsLeft = $this->getRemainingAttempts($ip);
			$msg = $attemptsLeft > 0
				? \AIS\Core\I18n::t('auth.wrong_password', ['attempts' => $attemptsLeft])
				: \AIS\Core\I18n::t('auth.wrong_password_final');
			return Response::html(\ACE\Admin\AdminManager::renderLogin($msg));
		}

		$this->clearLoginRate($ip);

		/* Argon2id リハッシュ */
		$algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
		if (password_needs_rehash($passwordHash, $algo)) {
			\ACE\Admin\AdminManager::savePassword($password);
			\APF\Utilities\Logger::info('パスワードハッシュを Argon2id へアップグレードしました');
		}

		/* パスワード変更リクエスト */
		if (!empty($request->post('new'))) {
			\ACE\Admin\AdminManager::savePassword($request->post('new'));
			return Response::html(\ACE\Admin\AdminManager::renderLogin(\AIS\Core\I18n::t('auth.password_changed')));
		}

		session_regenerate_id(true);
		$_SESSION['l'] = true;
		$_SESSION['ap_username'] = 'admin';
		$_SESSION['ap_role'] = 'admin';
		\AIS\System\DiagnosticsManager::log('security', 'ログイン成功', ['username' => 'admin']);
		return Response::redirect('./');
	}

	/** POST /logout — ログアウト */
	public function logout(Request $request): Response {
		\ACE\Admin\AdminManager::verifyCsrf();
		$_SESSION = [];
		if (ini_get('session.use_cookies')) {
			$p = session_get_cookie_params();
			setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
		}
		session_destroy();
		session_start();
		return Response::redirect('./');
	}

	/* ── レート制限ヘルパー ── */

	private function checkLoginRate(string $ip): bool {
		$data = json_read('login_attempts.json', settings_dir());
		$attempts = $data[$ip] ?? ['count' => 0, 'locked_until' => 0];
		return time() >= (int)$attempts['locked_until'];
	}

	private function recordLoginFailure(string $ip): void {
		$data = json_read('login_attempts.json', settings_dir());
		$attempts = $data[$ip] ?? ['count' => 0, 'locked_until' => 0];
		if (time() >= (int)$attempts['locked_until']) {
			$attempts['count']++;
		}
		if ($attempts['count'] >= 5) {
			$attempts['locked_until'] = time() + 900;
			$attempts['count'] = 0;
			\AIS\System\DiagnosticsManager::log('security', 'ロックアウト発動');
		}
		$data[$ip] = $attempts;
		json_write('login_attempts.json', $data, settings_dir());
	}

	private function clearLoginRate(string $ip): void {
		$data = json_read('login_attempts.json', settings_dir());
		unset($data[$ip]);
		json_write('login_attempts.json', $data, settings_dir());
	}

	private function getLockoutRemaining(string $ip): int {
		$data = json_read('login_attempts.json', settings_dir());
		$attempts = $data[$ip] ?? ['count' => 0, 'locked_until' => 0];
		return max(1, (int)ceil(((int)$attempts['locked_until'] - time()) / 60));
	}

	private function getRemainingAttempts(string $ip): int {
		$data = json_read('login_attempts.json', settings_dir());
		$attempts = $data[$ip] ?? ['count' => 0, 'locked_until' => 0];
		return max(0, 5 - (int)$attempts['count']);
	}

	private function rehashIfNeeded(array &$users, string $username, string $password): void {
		$algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
		if (password_needs_rehash($users[$username]['password_hash'] ?? '', $algo)) {
			$users[$username]['password_hash'] = password_hash($password, $algo);
			json_write(\ACE\Admin\AdminManager::USERS_FILE, $users, settings_dir());
			\APF\Utilities\Logger::info("ユーザー \"{$username}\" のパスワードハッシュを Argon2id へアップグレードしました");
		}
	}
}

/* ══════════════════════════════════════════════════
 * DashboardController - 管理ダッシュボード表示
 * ══════════════════════════════════════════════════ */

class DashboardController extends BaseController {

	/** GET /admin — ダッシュボード表示 */
	public function index(Request $request): Response {
		$html = \ACE\Admin\AdminManager::renderDashboard();
		return Response::html($html);
	}
}

/* ══════════════════════════════════════════════════
 * ApiController - REST API エンドポイント
 * ══════════════════════════════════════════════════ */

class ApiController extends BaseController {

	/**
	 * API リクエストを ApiEngine に委譲
	 */
	public function dispatch(Request $request): Response {
		$endpoint = $request->param('endpoint') ?? '';
		if ($endpoint !== '' && !isset($_GET['ap_api'])) {
			$_GET['ap_api'] = $endpoint;
		}

		\ApiEngine::handle();

		return $this->error('Unknown API endpoint', 404);
	}
}

/* ══════════════════════════════════════════════════
 * AdminController - 管理アクション
 * ══════════════════════════════════════════════════ */

class AdminController extends BaseController {

	/* ═══ フィールド保存 ═══ */

	public function editField(Request $request): Response {
		if ($err = $this->requireRole('editor')) return $err;

		$fieldname = $request->post('fieldname', '');
		$content   = trim($request->post('content', ''));
		if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $fieldname)) {
			return $this->error(\AIS\Core\I18n::t('auth.invalid_field_name'));
		}

		$settingsKeys = ['title', 'description', 'keywords', 'copyright', 'themeSelect', 'menu', 'subside', 'contact_email'];
		if (in_array($fieldname, $settingsKeys, true)) {
			$settings = json_read('settings.json', settings_dir());
			$settings[$fieldname] = $content;
			json_write('settings.json', $settings, settings_dir());
			\ACE\Admin\AdminManager::logActivity('設定変更: ' . $fieldname);
		} else {
			\ACE\Admin\AdminManager::saveRevision($fieldname, $content);
			$pages = json_read('pages.json', content_dir());
			$pages[$fieldname] = $content;
			json_write('pages.json', $pages, content_dir());
			\ACE\Admin\AdminManager::logActivity('ページ編集: ' . $fieldname);
			\ACE\Api\WebhookService::dispatch('page.updated', ['slug' => $fieldname]);
		}
		\AIS\System\ApiCache::invalidateContent();

		return Response::json(
			['ok' => true, 'content' => $content],
			200,
			['Content-Type' => 'application/json; charset=UTF-8']
		);
	}

	/* ═══ 画像アップロード ═══ */

	public function uploadImage(Request $request): Response {
		if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
			return $this->error('ファイルエラー');
		}
		$file = $_FILES['image'];
		if ($file['size'] > 2 * 1024 * 1024) {
			return $this->error('ファイルサイズが上限（2MB）を超えています');
		}
		$finfo = new \finfo(FILEINFO_MIME_TYPE);
		$mime = $finfo->file($file['tmp_name']);
		$extMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
		if (!isset($extMap[$mime])) {
			return $this->error('許可されていないファイル形式です');
		}
		$dir = 'uploads/';
		if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
			return $this->error('アップロードディレクトリを作成できません', 500);
		}
		$filename = bin2hex(random_bytes(12)) . '.' . $extMap[$mime];
		if (!move_uploaded_file($file['tmp_name'], $dir . $filename)) {
			return $this->error('ファイル保存に失敗しました', 500);
		}
		\ASG\Utilities\ImageService::optimize($dir . $filename);
		\ACE\Admin\AdminManager::logActivity('画像アップロード: ' . $filename);

		return Response::json(['url' => $dir . $filename]);
	}

	/* ═══ ページ削除 ═══ */

	public function deletePage(Request $request): Response {
		if ($err = $this->requireRole('admin')) return $err;

		$slug = $request->post('slug', '');
		if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $slug)) {
			return $this->error('不正なページ名');
		}
		$pages = json_read('pages.json', content_dir());
		if (!isset($pages[$slug])) {
			return $this->error('ページが見つかりません', 404);
		}
		\ACE\Admin\AdminManager::saveRevision($slug, $pages[$slug]);
		unset($pages[$slug]);
		json_write('pages.json', $pages, content_dir());
		\ACE\Admin\AdminManager::logActivity('ページ削除: ' . $slug);

		return $this->ok();
	}

	/* ═══ リビジョン管理 ═══ */

	public function listRevisions(Request $request): Response {
		$fieldname = $request->post('fieldname', '');
		if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $fieldname)) {
			return $this->error('Invalid fieldname');
		}
		$offset = max(0, (int)$request->post('offset', 0));
		$limit  = min(50, max(1, (int)$request->post('limit', 20)));

		$dir = content_dir() . '/revisions/' . $fieldname . '/';
		$files = glob($dir . 'rev_*.json') ?: [];
		rsort($files);
		$total = count($files);
		$files = array_slice($files, $offset, $limit);

		$revisions = [];
		foreach ($files as $f) {
			$data = \APF\Utilities\FileSystem::readJson($f);
			if ($data === null) continue;
			$revisions[] = [
				'file'      => basename($f, '.json'),
				'timestamp' => $data['timestamp'] ?? '',
				'size'      => $data['size'] ?? 0,
				'user'      => $data['user'] ?? '',
				'restored'  => !empty($data['restored']),
				'pinned'    => !empty($data['pinned']),
			];
		}
		return Response::json(['revisions' => $revisions, 'total' => $total, 'offset' => $offset, 'limit' => $limit]);
	}

	public function getRevision(Request $request): Response {
		$fieldname = $request->post('fieldname', '');
		$revFile   = $request->post('revision', '');
		if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $fieldname) ||
		    !preg_match('/^rev_[0-9a-f_]+$/', $revFile)) {
			return $this->error('Invalid parameters');
		}
		$path = content_dir() . '/revisions/' . $fieldname . '/' . $revFile . '.json';
		if (!file_exists($path)) return $this->error('Revision not found', 404);
		$rev = \APF\Utilities\FileSystem::readJson($path);
		if ($rev === null || !isset($rev['content'])) return $this->error('Invalid revision data', 500);

		return Response::json(['ok' => true, 'content' => $rev['content']]);
	}

	public function restoreRevision(Request $request): Response {
		$fieldname = $request->post('fieldname', '');
		$revFile   = $request->post('revision', '');
		if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $fieldname) ||
		    !preg_match('/^rev_[0-9a-f_]+$/', $revFile)) {
			return $this->error('Invalid parameters');
		}
		$path = content_dir() . '/revisions/' . $fieldname . '/' . $revFile . '.json';
		if (!file_exists($path)) return $this->error('Revision not found', 404);
		$rev = \APF\Utilities\FileSystem::readJson($path);
		if ($rev === null || !isset($rev['content'])) return $this->error('Invalid revision data', 500);

		$content = $rev['content'];
		\ACE\Admin\AdminManager::saveRevision($fieldname, $content, true);
		$pages = json_read('pages.json', content_dir());
		$pages[$fieldname] = $content;
		json_write('pages.json', $pages, content_dir());
		\ACE\Admin\AdminManager::logActivity('リビジョン復元: ' . $fieldname);

		return Response::json(['ok' => true, 'content' => $content]);
	}

	public function pinRevision(Request $request): Response {
		$fieldname = $request->post('fieldname', '');
		$revFile   = $request->post('revision', '');
		if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $fieldname) ||
		    !preg_match('/^rev_[0-9a-f_]+$/', $revFile)) {
			return $this->error('Invalid parameters');
		}
		$path = content_dir() . '/revisions/' . $fieldname . '/' . $revFile . '.json';
		if (!file_exists($path)) return $this->error('Revision not found', 404);
		$rev = \APF\Utilities\FileSystem::readJson($path);
		if ($rev === null) return $this->error('Invalid revision data', 500);

		$rev['pinned'] = empty($rev['pinned']);
		\APF\Utilities\FileSystem::writeJson($path, $rev);

		return Response::json(['ok' => true, 'pinned' => $rev['pinned']]);
	}

	public function searchRevisions(Request $request): Response {
		$fieldname = $request->post('fieldname', '');
		$query     = $request->post('query', '');
		if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $fieldname)) {
			return $this->error('Invalid fieldname');
		}
		$dir = content_dir() . '/revisions/' . $fieldname . '/';
		$files = glob($dir . 'rev_*.json') ?: [];
		rsort($files);
		$results = [];
		foreach ($files as $f) {
			$data = \APF\Utilities\FileSystem::readJson($f);
			if ($data === null) continue;
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
		return Response::json(['revisions' => $results]);
	}

	/* ═══ ユーザー管理 ═══ */

	public function userAdd(Request $request): Response {
		if ($err = $this->requireRole('admin')) return $err;
		$username = trim($request->post('username', ''));
		$password = $request->post('password', '');
		$role = trim($request->post('role', 'editor'));
		if ($username === '' || $password === '') {
			return $this->error('ユーザー名とパスワードは必須です');
		}
		if (!\ACE\Admin\AdminManager::addUser($username, $password, $role)) {
			return $this->error('ユーザーの追加に失敗しました');
		}
		\ACE\Admin\AdminManager::logActivity("ユーザー追加: {$username} ({$role})");
		return Response::json(['ok' => true, 'data' => ['username' => $username, 'role' => $role]]);
	}

	public function userDelete(Request $request): Response {
		if ($err = $this->requireRole('admin')) return $err;
		$username = trim($request->post('username', ''));
		if ($username === \ACE\Admin\AdminManager::currentUsername()) {
			return $this->error('自分自身は削除できません');
		}
		if (!\ACE\Admin\AdminManager::deleteUser($username)) {
			return $this->error('ユーザーの削除に失敗しました');
		}
		\ACE\Admin\AdminManager::logActivity("ユーザー削除: {$username}");
		return Response::json(['ok' => true, 'data' => ['deleted' => $username]]);
	}

	/* ═══ リダイレクト管理 ═══ */

	public function redirectAdd(Request $request): Response {
		if ($err = $this->requireRole('admin')) return $err;
		$from = trim($request->post('from', ''));
		$to   = trim($request->post('to', ''));
		$code = (int)$request->post('code', 301);
		if ($from === '' || $to === '') {
			return $this->error('旧URLと新URLは必須です');
		}
		$from = preg_replace('/[\r\n\x00-\x1f]/', '', $from);
		$to   = preg_replace('/[\r\n\x00-\x1f]/', '', $to);
		if (!str_starts_with($from, '/')) {
			return $this->error('旧URLは / で始まる必要があります');
		}
		if (!str_starts_with($to, '/') && !preg_match('#^https?://#i', $to)) {
			return $this->error('リダイレクト先は / で始まるパスまたは https:// URL を指定してください');
		}
		if (!in_array($code, [301, 302], true)) $code = 301;
		$redirects = json_read('redirects.json', settings_dir());
		foreach ($redirects as $r) {
			if (($r['from'] ?? '') === $from) {
				return $this->error('この旧URLのリダイレクトは既に存在します');
			}
		}
		$redirects[] = ['from' => $from, 'to' => $to, 'code' => $code];
		json_write('redirects.json', $redirects, settings_dir());
		\ACE\Admin\AdminManager::logActivity("リダイレクト追加: {$from} → {$to}");
		return $this->ok();
	}

	public function redirectDelete(Request $request): Response {
		if ($err = $this->requireRole('admin')) return $err;
		$index = (int)$request->post('index', -1);
		$redirects = json_read('redirects.json', settings_dir());
		if ($index < 0 || $index >= count($redirects)) {
			return $this->error('無効なインデックス');
		}
		array_splice($redirects, $index, 1);
		json_write('redirects.json', $redirects, settings_dir());
		\ACE\Admin\AdminManager::logActivity("リダイレクト削除: #{$index}");
		return $this->ok();
	}
}

/* ══════════════════════════════════════════════════
 * CollectionController - コレクション CRUD
 * ══════════════════════════════════════════════════ */

class CollectionController extends BaseController {

	/** コレクション作成 */
	public function create(Request $request): Response {
		$name  = trim($request->post('name', ''));
		$label = trim($request->post('label', ''));
		if ($name === '') {
			return $this->error('コレクション名は必須です');
		}
		if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $name)) {
			return $this->error('コレクション名に使用できない文字が含まれています');
		}
		if (\ACE\Core\CollectionService::getCollectionDef($name) !== null) {
			return $this->error('同名のコレクションが既に存在します');
		}
		$def = ['label' => $label ?: $name, 'fields' => []];
		if (!\ACE\Core\CollectionService::createCollection($name, $def)) {
			return $this->error('コレクション作成に失敗しました', 500);
		}
		\ACE\Admin\AdminManager::logActivity('コレクション作成: ' . $name);
		\AIS\System\ApiCache::invalidateContent();
		return $this->ok(['name' => $name]);
	}

	/** コレクション削除 */
	public function delete(Request $request): Response {
		if ($err = $this->requireRole('admin')) return $err;

		$name = trim($request->post('name', ''));
		if ($name === '' || \ACE\Core\CollectionService::getCollectionDef($name) === null) {
			return $this->error('コレクションが見つかりません', 404);
		}
		if (!\ACE\Core\CollectionService::deleteCollection($name)) {
			return $this->error('コレクション削除に失敗しました', 500);
		}
		\ACE\Admin\AdminManager::logActivity('コレクション削除: ' . $name);
		\AIS\System\ApiCache::invalidateContent();
		return $this->ok();
	}

	/** コレクションアイテム保存 */
	public function itemSave(Request $request): Response {
		$collection = trim($request->post('collection', ''));
		$slug       = trim($request->post('slug', ''));
		$title      = trim($request->post('title', ''));
		$body       = $request->post('body', '');
		$isNew      = !empty($request->post('is_new'));
		$metaRaw    = $request->post('meta', '{}');

		if ($collection === '' || $slug === '') {
			return $this->error('コレクション名とスラッグは必須です');
		}
		if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $collection) || !preg_match('/^[a-zA-Z0-9_\-]+$/', $slug)) {
			return $this->error('無効なコレクション名またはスラッグ');
		}

		$meta = json_decode($metaRaw, true) ?: [];
		$itemData = array_merge($meta, ['title' => $title, 'body' => $body]);

		/* バリデーション */
		$errors = \ACE\Core\CollectionService::validateFields($collection, $itemData);
		if (!empty($errors)) {
			return Response::json(['ok' => false, 'errors' => $errors], 422);
		}

		if (!\ACE\Core\CollectionService::saveItem($collection, $slug, $itemData, $title, $isNew)) {
			return $this->error('アイテム保存に失敗しました', 500);
		}

		$event = $isNew ? 'item.created' : 'item.updated';
		\ACE\Admin\AdminManager::logActivity(($isNew ? 'アイテム作成' : 'アイテム更新') . ": {$collection}/{$slug}");
		\ACE\Api\WebhookService::dispatch($event, ['collection' => $collection, 'slug' => $slug]);
		\AIS\System\ApiCache::invalidateContent();

		return $this->ok(['collection' => $collection, 'slug' => $slug]);
	}

	/** コレクションアイテム削除 */
	public function itemDelete(Request $request): Response {
		$collection = trim($request->post('collection', ''));
		$slug       = trim($request->post('slug', ''));

		if ($collection === '' || $slug === '') {
			return $this->error('コレクション名とスラッグは必須です');
		}
		if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $collection) || !preg_match('/^[a-zA-Z0-9_\-]+$/', $slug)) {
			return $this->error('無効なパラメータ');
		}

		if (!\ACE\Core\CollectionService::deleteItem($collection, $slug)) {
			return $this->error('アイテム削除に失敗しました', 500);
		}

		\ACE\Admin\AdminManager::logActivity("アイテム削除: {$collection}/{$slug}");
		\ACE\Api\WebhookService::dispatch('item.deleted', ['collection' => $collection, 'slug' => $slug]);
		\AIS\System\ApiCache::invalidateContent();

		return $this->ok();
	}

	/** pages.json → コレクションへのマイグレーション */
	public function migrate(Request $request): Response {
		if ($err = $this->requireRole('admin')) return $err;

		$result = \ACE\Core\CollectionService::migrateFromPagesJson();
		\ACE\Admin\AdminManager::logActivity('コレクションマイグレーション実行');
		return Response::json(['ok' => true, 'data' => $result]);
	}
}

/* ══════════════════════════════════════════════════
 * GitController - Git/GitHub 連携
 * ══════════════════════════════════════════════════ */

class GitController extends BaseController {

	/** Git 設定保存 */
	public function configure(Request $request): Response {
		if ($err = $this->requireRole('admin')) return $err;

		$config = \AIS\Deployment\GitService::loadConfig();
		$fields = ['repository', 'token', 'branch', 'content_dir', 'webhook_secret'];
		foreach ($fields as $f) {
			$val = $request->post($f);
			if ($val !== null) $config[$f] = trim($val);
		}
		$config['enabled']        = !empty($request->post('enabled'));
		$config['issues_enabled'] = !empty($request->post('issues_enabled'));

		\AIS\Deployment\GitService::saveConfig($config);
		\ACE\Admin\AdminManager::logActivity('Git設定変更');
		return $this->ok();
	}

	/** 接続テスト */
	public function test(Request $request): Response {
		$result = \AIS\Deployment\GitService::testConnection();
		return Response::json(['ok' => ($result['status'] ?? '') === 'ok', 'data' => $result]);
	}

	/** GitHub → ローカル同期 */
	public function pull(Request $request): Response {
		$result = \AIS\Deployment\GitService::pull();
		if (($result['status'] ?? '') === 'ok') {
			\ACE\Admin\AdminManager::logActivity('Git pull 実行');
			\AIS\System\ApiCache::invalidateContent();
		}
		return Response::json(['ok' => ($result['status'] ?? '') === 'ok', 'data' => $result]);
	}

	/** ローカル → GitHub 同期 */
	public function push(Request $request): Response {
		$message = trim($request->post('message', 'Update content from AdlairePlatform'));
		$result = \AIS\Deployment\GitService::push($message);
		if (($result['status'] ?? '') === 'ok') {
			\ACE\Admin\AdminManager::logActivity('Git push 実行');
		}
		return Response::json(['ok' => ($result['status'] ?? '') === 'ok', 'data' => $result]);
	}

	/** コミット履歴取得 */
	public function log(Request $request): Response {
		$limit = min(100, max(1, (int)$request->post('limit', 20)));
		$result = \AIS\Deployment\GitService::log($limit);
		return Response::json(['ok' => true, 'data' => $result]);
	}

	/** Git ステータス取得 */
	public function status(Request $request): Response {
		$result = \AIS\Deployment\GitService::status();
		return Response::json(['ok' => true, 'data' => $result]);
	}

	/** プレビューブランチ作成 */
	public function previewBranch(Request $request): Response {
		$name = trim($request->post('name', 'draft'));
		$result = \AIS\Deployment\GitService::createPreviewBranch($name);
		return Response::json(['ok' => ($result['status'] ?? '') === 'ok', 'data' => $result]);
	}
}

/* ══════════════════════════════════════════════════
 * WebhookController - Outgoing Webhook 管理
 * ══════════════════════════════════════════════════ */

class WebhookController extends BaseController {

	/** Webhook 追加 */
	public function add(Request $request): Response {
		if ($err = $this->requireRole('admin')) return $err;

		$url    = trim($request->post('url', ''));
		$label  = trim($request->post('label', ''));
		$events = json_decode($request->post('events', '[]'), true) ?: [];
		$secret = trim($request->post('secret', ''));

		if ($url === '') {
			return $this->error('URLは必須です');
		}
		if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $url)) {
			return $this->error('有効な HTTP(S) URL を指定してください');
		}

		if (!\ACE\Api\WebhookService::addWebhook($url, $label, $events, $secret)) {
			return $this->error('Webhook追加に失敗しました', 500);
		}
		\ACE\Admin\AdminManager::logActivity('Webhook追加: ' . $label);
		return $this->ok();
	}

	/** Webhook 削除 */
	public function delete(Request $request): Response {
		if ($err = $this->requireRole('admin')) return $err;

		$index = (int)$request->post('index', -1);
		if (!\ACE\Api\WebhookService::deleteWebhook($index)) {
			return $this->error('Webhook削除に失敗しました');
		}
		\ACE\Admin\AdminManager::logActivity("Webhook削除: #{$index}");
		return $this->ok();
	}

	/** Webhook 有効/無効 切替 */
	public function toggle(Request $request): Response {
		if ($err = $this->requireRole('admin')) return $err;

		$index = (int)$request->post('index', -1);
		if (!\ACE\Api\WebhookService::toggleWebhook($index)) {
			return $this->error('Webhook切替に失敗しました');
		}
		return $this->ok();
	}

	/** テスト送信 */
	public function test(Request $request): Response {
		if ($err = $this->requireRole('admin')) return $err;

		$index = (int)$request->post('index', -1);
		$webhooks = \ACE\Api\WebhookService::listWebhooks();
		if ($index < 0 || $index >= count($webhooks)) {
			return $this->error('無効なインデックス');
		}
		\ACE\Api\WebhookService::dispatch('webhook.test', ['test' => true, 'timestamp' => date('c')]);
		return $this->ok();
	}
}

/* ══════════════════════════════════════════════════
 * StaticController - 静的サイト生成
 * ══════════════════════════════════════════════════ */

class StaticController extends BaseController {

	/** 差分ビルド */
	public function buildDiff(Request $request): Response {
		\ASG\Core\StaticService::init();
		$result = \ASG\Core\StaticService::buildDiff();
		\ACE\Admin\AdminManager::logActivity('静的サイト差分ビルド');
		return Response::json(['ok' => true, 'data' => $result]);
	}

	/** フルビルド */
	public function buildAll(Request $request): Response {
		\ASG\Core\StaticService::init();
		$result = \ASG\Core\StaticService::buildAll();
		\ACE\Admin\AdminManager::logActivity('静的サイトフルビルド');
		return Response::json(['ok' => true, 'data' => $result]);
	}

	/** 静的ファイルクリーン */
	public function clean(Request $request): Response {
		\ASG\Core\StaticService::init();
		\ASG\Core\StaticService::clean();
		\ACE\Admin\AdminManager::logActivity('静的サイトクリーン');
		return $this->ok();
	}

	/** ZIP ダウンロード */
	public function buildZip(Request $request): Response {
		try {
			\ASG\Core\StaticService::init();
			$path = \ASG\Core\StaticService::buildZipFile();
			return Response::file($path, 'static-' . date('Ymd') . '.zip', 'application/zip');
		} catch (\RuntimeException $e) {
			return $this->error($e->getMessage());
		}
	}

	/** ビルドステータス取得 */
	public function status(Request $request): Response {
		\ASG\Core\StaticService::init();
		$result = \ASG\Core\StaticService::getStatus();
		return Response::json(['ok' => true, 'data' => $result]);
	}

	/** デプロイ差分ZIP */
	public function deployDiff(Request $request): Response {
		try {
			\ASG\Core\StaticService::init();
			$path = \ASG\Core\StaticService::buildDiffZipFile();
			return Response::file($path, 'deploy-diff-' . date('Ymd') . '.zip', 'application/zip');
		} catch (\RuntimeException $e) {
			return $this->error($e->getMessage());
		}
	}
}

/* ══════════════════════════════════════════════════
 * UpdateController - アップデート・バックアップ管理
 * ══════════════════════════════════════════════════ */

class UpdateController extends BaseController {

	/** アップデート確認 */
	public function check(Request $request): Response {
		$result = \AIS\Deployment\UpdateService::checkUpdate();
		return Response::json(['ok' => true, 'data' => $result]);
	}

	/** 環境互換チェック */
	public function checkEnv(Request $request): Response {
		$result = \AIS\Deployment\UpdateService::checkEnvironment();
		return Response::json(['ok' => true, 'data' => $result]);
	}

	/** アップデート適用 */
	public function apply(Request $request): Response {
		if ($err = $this->requireRole('admin')) return $err;

		try {
			$result = \AIS\Deployment\UpdateService::executeApplyUpdate();
			\ACE\Admin\AdminManager::logActivity('アップデート適用');
			return Response::json($result);
		} catch (\RuntimeException $e) {
			return $this->error($e->getMessage(), 500);
		}
	}

	/** バックアップ一覧 */
	public function listBackups(Request $request): Response {
		$backups = glob('backup/*', GLOB_ONLYDIR);
		$list = [];
		if (is_array($backups)) {
			rsort($backups);
			foreach ($backups as $path) {
				$name = basename($path);
				$metaFile = $path . '/meta.json';
				$meta = null;
				if (file_exists($metaFile)) {
					$decoded = \APF\Utilities\FileSystem::readJson($metaFile);
					if (is_array($decoded)) $meta = $decoded;
				}
				$list[] = ['name' => $name, 'meta' => $meta];
			}
		}
		return Response::json(['ok' => true, 'data' => ['backups' => $list]]);
	}

	/** バックアップからロールバック */
	public function rollback(Request $request): Response {
		if ($err = $this->requireRole('admin')) return $err;

		$name = trim($request->post('backup', ''));
		try {
			$result = \AIS\Deployment\UpdateService::executeRollback($name);
			\ACE\Admin\AdminManager::logActivity('ロールバック実行: ' . basename($name));
			return Response::json($result);
		} catch (\RuntimeException $e) {
			return $this->error($e->getMessage());
		}
	}

	/** バックアップ削除 */
	public function deleteBackup(Request $request): Response {
		if ($err = $this->requireRole('admin')) return $err;

		$name = trim($request->post('backup', ''));
		try {
			$result = \AIS\Deployment\UpdateService::executeDeleteBackup($name);
			\ACE\Admin\AdminManager::logActivity('バックアップ削除: ' . basename($name));
			return Response::json($result);
		} catch (\RuntimeException $e) {
			return $this->error($e->getMessage());
		}
	}
}

/* ══════════════════════════════════════════════════
 * DiagnosticController - 診断・テレメトリ設定
 * ══════════════════════════════════════════════════ */

class DiagnosticController extends BaseController {

	/** 診断収集の有効/無効切替 */
	public function setEnabled(Request $request): Response {
		if ($err = $this->requireRole('admin')) return $err;

		$enabled = !empty($request->post('enabled'));
		\AIS\System\DiagnosticsManager::setEnabled($enabled);
		\ACE\Admin\AdminManager::logActivity('診断収集: ' . ($enabled ? '有効' : '無効'));
		return $this->ok(['enabled' => $enabled]);
	}

	/** 収集レベル変更 */
	public function setLevel(Request $request): Response {
		if ($err = $this->requireRole('admin')) return $err;

		$level = trim($request->post('level', 'basic'));
		if (!in_array($level, ['basic', 'extended', 'debug'], true)) {
			return $this->error('無効なレベル: basic, extended, debug のいずれかを指定してください');
		}
		\AIS\System\DiagnosticsManager::setLevel($level);
		\ACE\Admin\AdminManager::logActivity('診断レベル変更: ' . $level);
		return $this->ok(['level' => $level]);
	}

	/** 現在のバッファプレビュー */
	public function preview(Request $request): Response {
		$data = \AIS\System\DiagnosticsManager::healthCheck(true);
		return Response::json(['ok' => true, 'data' => $data]);
	}

	/** 即時送信 */
	public function sendNow(Request $request): Response {
		if ($err = $this->requireRole('admin')) return $err;

		$config = \AIS\System\DiagnosticsManager::loadConfig();
		$data = \AIS\System\DiagnosticsManager::collectWithUnsent($config['last_sent'] ?? '');
		$success = \AIS\System\DiagnosticsManager::send($data);

		if ($success) {
			$config = \AIS\System\DiagnosticsManager::loadConfig();
			$config['last_sent'] = date('c');
			$config['consecutive_failures'] = 0;
			$config['circuit_breaker_until'] = 0;
			\AIS\System\DiagnosticsManager::saveConfig($config);
			\AIS\System\DiagnosticsManager::purgeExpiredLogs();
			\ACE\Admin\AdminManager::logActivity('診断データを手動送信');
			return $this->ok(['message' => '送信しました（ログは14日間保持）', 'sent_at' => date('c')]);
		}

		return $this->error('送信に失敗しました（エンドポイントに到達できない可能性があります）');
	}

	/** ログクリア */
	public function clearLogs(Request $request): Response {
		if ($err = $this->requireRole('admin')) return $err;

		\AIS\System\DiagnosticsManager::clearLogs();
		\ACE\Admin\AdminManager::logActivity('診断ログをクリア');
		return $this->ok(['message' => 'ログをクリアしました']);
	}

	/** ログ取得 */
	public function getLogs(Request $request): Response {
		$timings = \AIS\System\DiagnosticsManager::getTimings();
		$engineTimings = \AIS\System\DiagnosticsManager::getEngineTimings();
		return Response::json(['ok' => true, 'data' => [
			'timings' => $timings,
			'engine_timings' => $engineTimings,
		]]);
	}

	/** サマリー取得 */
	public function getSummary(Request $request): Response {
		$data = \AIS\System\DiagnosticsManager::healthCheck(false);
		return Response::json(['ok' => true, 'data' => $data]);
	}

	/** ヘルスチェック */
	public function health(Request $request): Response {
		$detailed = !empty($request->post('detailed'));
		$data = \AIS\System\DiagnosticsManager::healthCheck($detailed);
		return Response::json(['ok' => true, 'data' => $data]);
	}
}

/* ══════════════════════════════════════════════════
 * ActionDispatcher - POST ap_action ディスパッチャ
 * ══════════════════════════════════════════════════ */

class ActionDispatcher extends BaseController {

	/**
	 * ap_action → [ControllerClass, method] マッピング
	 */
	private const ACTION_MAP = [
		/* AdminController */
		'edit_field'        => [AdminController::class, 'editField'],
		'upload_image'      => [AdminController::class, 'uploadImage'],
		'delete_page'       => [AdminController::class, 'deletePage'],
		'list_revisions'    => [AdminController::class, 'listRevisions'],
		'get_revision'      => [AdminController::class, 'getRevision'],
		'restore_revision'  => [AdminController::class, 'restoreRevision'],
		'pin_revision'      => [AdminController::class, 'pinRevision'],
		'search_revisions'  => [AdminController::class, 'searchRevisions'],
		'user_add'          => [AdminController::class, 'userAdd'],
		'user_delete'       => [AdminController::class, 'userDelete'],
		'redirect_add'      => [AdminController::class, 'redirectAdd'],
		'redirect_delete'   => [AdminController::class, 'redirectDelete'],

		/* CollectionController */
		'collection_create'      => [CollectionController::class, 'create'],
		'collection_delete'      => [CollectionController::class, 'delete'],
		'collection_item_save'   => [CollectionController::class, 'itemSave'],
		'collection_item_delete' => [CollectionController::class, 'itemDelete'],
		'collection_migrate'     => [CollectionController::class, 'migrate'],

		/* GitController */
		'git_configure'      => [GitController::class, 'configure'],
		'git_test'           => [GitController::class, 'test'],
		'git_pull'           => [GitController::class, 'pull'],
		'git_push'           => [GitController::class, 'push'],
		'git_log'            => [GitController::class, 'log'],
		'git_status'         => [GitController::class, 'status'],
		'git_preview_branch' => [GitController::class, 'previewBranch'],

		/* WebhookController */
		'webhook_add'    => [WebhookController::class, 'add'],
		'webhook_delete' => [WebhookController::class, 'delete'],
		'webhook_toggle' => [WebhookController::class, 'toggle'],
		'webhook_test'   => [WebhookController::class, 'test'],

		/* StaticController */
		'generate_static_diff' => [StaticController::class, 'buildDiff'],
		'generate_static_full' => [StaticController::class, 'buildAll'],
		'clean_static'         => [StaticController::class, 'clean'],
		'build_zip'            => [StaticController::class, 'buildZip'],
		'static_status'        => [StaticController::class, 'status'],
		'deploy_diff'          => [StaticController::class, 'deployDiff'],

		/* UpdateController */
		'check'          => [UpdateController::class, 'check'],
		'check_env'      => [UpdateController::class, 'checkEnv'],
		'apply'          => [UpdateController::class, 'apply'],
		'list_backups'   => [UpdateController::class, 'listBackups'],
		'rollback'       => [UpdateController::class, 'rollback'],
		'delete_backup'  => [UpdateController::class, 'deleteBackup'],

		/* DiagnosticController */
		'diag_set_enabled' => [DiagnosticController::class, 'setEnabled'],
		'diag_set_level'   => [DiagnosticController::class, 'setLevel'],
		'diag_preview'     => [DiagnosticController::class, 'preview'],
		'diag_send_now'    => [DiagnosticController::class, 'sendNow'],
		'diag_clear_logs'  => [DiagnosticController::class, 'clearLogs'],
		'diag_get_logs'    => [DiagnosticController::class, 'getLogs'],
		'diag_get_summary' => [DiagnosticController::class, 'getSummary'],
		'diag_health'      => [DiagnosticController::class, 'health'],
	];

	/** POST /dispatch — ap_action に基づいてコントローラにルーティング */
	public function handle(Request $request): Response {
		$action = $request->post('ap_action', '');
		if ($action === '') {
			return $this->error('Missing ap_action parameter');
		}

		if (!isset(self::ACTION_MAP[$action])) {
			return $this->error("Unknown action: {$action}", 404);
		}

		[$controllerClass, $method] = self::ACTION_MAP[$action];
		$controller = new $controllerClass();
		return $controller->$method($request);
	}

	/** 登録済みアクション一覧を返す（デバッグ用） */
	public static function registeredActions(): array {
		return array_keys(self::ACTION_MAP);
	}
}
