<?php
/**
 * AdminEngine - 管理ツールエンジン（後方互換シム）
 *
 * Ver.1.8: 全ロジックを ACE\Admin\AdminManager に移植。
 * このファイルは後方互換のためのシムとして残存。
 * Stage 8 で削除予定。
 *
 * @deprecated Ver.1.8 — ACE\Admin\AdminManager を使用してください
 */
class AdminEngine {

	/** @var \ACE\Admin\AuthManager|null Ver.1.5 Framework 認証マネージャ */
	private static ?\ACE\Admin\AuthManager $authManager = null;

	/** @since Ver.1.7-36 public 化（AuthController から参照） */
	public const USERS_FILE = 'users.json';

	/**
	 * Ver.1.5: Framework AuthManager インスタンスを取得する
	 */
	public static function getAuthManager(): \ACE\Admin\AuthManager {
		if (self::$authManager === null) {
			self::$authManager = new \ACE\Admin\AuthManager(settings_dir());
		}
		return self::$authManager;
	}

	/* ── 認証 ── */

	public static function isLoggedIn(): bool {
		return \ACE\Admin\AdminManager::isLoggedIn();
	}

	public static function currentRole(): string {
		return \ACE\Admin\AdminManager::currentRole();
	}

	public static function currentUsername(): string {
		return \ACE\Admin\AdminManager::currentUsername();
	}

	public static function hasRole(string $requiredRole): bool {
		return \ACE\Admin\AdminManager::hasRole($requiredRole);
	}

	/* ── マルチユーザー管理 ── */

	public static function listUsers(): array {
		return \ACE\Admin\AdminManager::listUsers();
	}

	public static function addUser(string $username, string $password, string $role = 'editor'): bool {
		return \ACE\Admin\AdminManager::addUser($username, $password, $role);
	}

	public static function deleteUser(string $username): bool {
		return \ACE\Admin\AdminManager::deleteUser($username);
	}

	public static function login(string $passwordHash): string {
		return \ACE\Admin\AdminManager::login($passwordHash);
	}

	public static function savePassword(string $p): string {
		return \ACE\Admin\AdminManager::savePassword($p);
	}

	/* ── CSRF ── */

	public static function csrfToken(): string {
		return \ACE\Admin\AdminManager::csrfToken();
	}

	public static function verifyCsrf(): void {
		\ACE\Admin\AdminManager::verifyCsrf();
	}

	/* ── アクティビティログ ── */

	public static function logActivity(string $message): void {
		\ACE\Admin\AdminManager::logActivity($message);
	}

	public static function getRecentActivity(int $limit = 20): array {
		return \ACE\Admin\AdminManager::getRecentActivity($limit);
	}

	/* ── リビジョン管理 ── */

	public static function saveRevision(string $fieldname, string $content, bool $restored = false): void {
		\ACE\Admin\AdminManager::saveRevision($fieldname, $content, $restored);
	}

	/* ── レンダリング ── */

	public static function renderLogin(string $message = ''): string {
		return \ACE\Admin\AdminManager::renderLogin($message);
	}

	public static function renderEditableContent(string $id, string $content, string $placeholder = 'Click to edit!'): string {
		return \ACE\Admin\AdminManager::renderEditableContent($id, $content, $placeholder);
	}

	/* ── フック管理 ── */

	public static function registerHooks(): void {
		\ACE\Admin\AdminManager::registerHooks();
	}

	public static function getAdminScripts(): string {
		return \ACE\Admin\AdminManager::getAdminScripts();
	}

	/* ── ダッシュボード ── */

	public static function renderDashboard(): string {
		return \ACE\Admin\AdminManager::renderDashboard();
	}

	public static function buildDashboardContext(): array {
		return \ACE\Admin\AdminManager::buildDashboardContext();
	}
}
