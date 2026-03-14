<?php
/**
 * AuthController - 認証（ログイン/ログアウト）
 *
 * index.php のインラインログイン処理を Controller に移行。
 *
 * @since Ver.1.7-36
 */
namespace AP\Controllers;

use APF\Core\{Request, Response};

class AuthController extends BaseController {

	/** GET /login — ログインページ表示 */
	public function showLogin(Request $request): Response {
		if (\AdminEngine::isLoggedIn()) {
			return Response::redirect('./');
		}
		$html = \AdminEngine::renderLogin('');
		return Response::html($html);
	}

	/** POST /login — ログイン認証 */
	public function authenticate(Request $request): Response {
		if (\AdminEngine::isLoggedIn()) {
			return Response::redirect('./');
		}

		\AdminEngine::verifyCsrf();

		$password = $request->post('password', '');
		$username = $request->post('username', '');
		$ip = $request->server('REMOTE_ADDR', 'unknown');

		/* レート制限チェック */
		if (!$this->checkLoginRate($ip)) {
			$remaining = $this->getLockoutRemaining($ip);
			$msg = \I18n::t('auth.too_many_attempts', ['remaining' => $remaining]);
			return Response::html(\AdminEngine::renderLogin($msg));
		}

		/* マルチユーザーログイン試行 */
		if ($username !== '') {
			$users = json_read(\AdminEngine::USERS_FILE, settings_dir());
			if (!empty($users) && isset($users[$username])) {
				$user = $users[$username];
				if (($user['active'] ?? true) && password_verify($password, $user['password_hash'] ?? '')) {
					$this->clearLoginRate($ip);
					$this->rehashIfNeeded($users, $username, $password);
					session_regenerate_id(true);
					$_SESSION['l'] = true;
					$_SESSION['ap_username'] = $username;
					$_SESSION['ap_role'] = $user['role'] ?? 'editor';
					\AdminEngine::logActivity('ログイン: ' . $username . ' (' . ($user['role'] ?? 'editor') . ')');
					if (class_exists('DiagnosticEngine')) \DiagnosticEngine::log('security', 'ログイン成功', ['username' => $username]);
					return Response::redirect('./');
				}
			}
		}

		/* 単一パスワード認証（後方互換） */
		$_auth = json_read('auth.json', settings_dir());
		$passwordHash = $_auth['password_hash'] ?? '';
		if ($passwordHash === '' || !password_verify($password, $passwordHash)) {
			$this->recordLoginFailure($ip);
			if (class_exists('DiagnosticEngine')) \DiagnosticEngine::log('security', 'ログイン失敗');
			$attemptsLeft = $this->getRemainingAttempts($ip);
			$msg = $attemptsLeft > 0
				? \I18n::t('auth.wrong_password', ['attempts' => $attemptsLeft])
				: \I18n::t('auth.wrong_password_final');
			return Response::html(\AdminEngine::renderLogin($msg));
		}

		$this->clearLoginRate($ip);

		/* Argon2id リハッシュ */
		$algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
		if (password_needs_rehash($passwordHash, $algo)) {
			\AdminEngine::savePassword($password);
			\Logger::info('パスワードハッシュを Argon2id へアップグレードしました');
		}

		/* パスワード変更リクエスト */
		if (!empty($request->post('new'))) {
			\AdminEngine::savePassword($request->post('new'));
			return Response::html(\AdminEngine::renderLogin(\I18n::t('auth.password_changed')));
		}

		session_regenerate_id(true);
		$_SESSION['l'] = true;
		$_SESSION['ap_username'] = 'admin';
		$_SESSION['ap_role'] = 'admin';
		if (class_exists('DiagnosticEngine')) \DiagnosticEngine::log('security', 'ログイン成功', ['username' => 'admin']);
		return Response::redirect('./');
	}

	/** POST /logout — ログアウト */
	public function logout(Request $request): Response {
		\AdminEngine::verifyCsrf();
		$_SESSION = [];
		if (ini_get('session.use_cookies')) {
			$p = session_get_cookie_params();
			setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
		}
		session_destroy();
		session_start();
		return Response::redirect('./');
	}

	/* ── レート制限ヘルパー（AdminEngine の private メソッドを Controller に移行） ── */

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
			if (class_exists('DiagnosticEngine')) \DiagnosticEngine::log('security', 'ロックアウト発動');
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
			json_write(\AdminEngine::USERS_FILE, $users, settings_dir());
			\Logger::info("ユーザー \"{$username}\" のパスワードハッシュを Argon2id へアップグレードしました");
		}
	}
}
