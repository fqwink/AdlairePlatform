<?php
/**
 * AdminEngineTest - 認証・CSRF・レート制限・ユーザー管理のテスト
 */
class AdminEngineTest extends TestCase {

	protected function setUp(): void {
		$this->resetSession();
		$this->resetPost();
		$this->clearJsonCache();
	}

	/* ═══ isLoggedIn ═══ */

	public function testIsLoggedInReturnsFalseByDefault(): void {
		$this->assertFalse(AdminEngine::isLoggedIn());
	}

	public function testIsLoggedInReturnsTrueWhenSessionSet(): void {
		$_SESSION['l'] = true;
		$this->assertTrue(AdminEngine::isLoggedIn());
	}

	public function testIsLoggedInReturnsFalseForNonTrue(): void {
		$_SESSION['l'] = 1;
		$this->assertFalse(AdminEngine::isLoggedIn());
		$_SESSION['l'] = 'true';
		$this->assertFalse(AdminEngine::isLoggedIn());
	}

	/* ═══ currentRole / currentUsername ═══ */

	public function testCurrentRoleDefaultsToAdmin(): void {
		$_SESSION['l'] = true;
		$this->assertEquals('admin', AdminEngine::currentRole());
	}

	public function testCurrentRoleReturnsSessionRole(): void {
		$_SESSION['l'] = true;
		$_SESSION['ap_role'] = 'editor';
		$this->assertEquals('editor', AdminEngine::currentRole());
	}

	public function testCurrentRoleEmptyWhenNotLoggedIn(): void {
		$this->assertEquals('', AdminEngine::currentRole());
	}

	public function testCurrentUsernameDefaultsToAdmin(): void {
		$_SESSION['l'] = true;
		$this->assertEquals('admin', AdminEngine::currentUsername());
	}

	public function testCurrentUsernameReturnsSessionValue(): void {
		$_SESSION['l'] = true;
		$_SESSION['ap_username'] = 'testuser';
		$this->assertEquals('testuser', AdminEngine::currentUsername());
	}

	public function testCurrentUsernameEmptyWhenNotLoggedIn(): void {
		$this->assertEquals('', AdminEngine::currentUsername());
	}

	/* ═══ hasRole ═══ */

	public function testHasRoleAdminCanAccessAll(): void {
		$_SESSION['l'] = true;
		$_SESSION['ap_role'] = 'admin';
		$this->assertTrue(AdminEngine::hasRole('admin'));
		$this->assertTrue(AdminEngine::hasRole('editor'));
		$this->assertTrue(AdminEngine::hasRole('viewer'));
	}

	public function testHasRoleEditorCannotAccessAdmin(): void {
		$_SESSION['l'] = true;
		$_SESSION['ap_role'] = 'editor';
		$this->assertFalse(AdminEngine::hasRole('admin'));
		$this->assertTrue(AdminEngine::hasRole('editor'));
		$this->assertTrue(AdminEngine::hasRole('viewer'));
	}

	public function testHasRoleViewerCanOnlyView(): void {
		$_SESSION['l'] = true;
		$_SESSION['ap_role'] = 'viewer';
		$this->assertFalse(AdminEngine::hasRole('admin'));
		$this->assertFalse(AdminEngine::hasRole('editor'));
		$this->assertTrue(AdminEngine::hasRole('viewer'));
	}

	public function testHasRoleReturnsFalseWhenNotLoggedIn(): void {
		$this->assertFalse(AdminEngine::hasRole('viewer'));
	}

	/* ═══ CSRF トークン ═══ */

	public function testCsrfTokenGeneratesToken(): void {
		$token = AdminEngine::csrfToken();
		$this->assertNotEmpty($token);
		$this->assertEquals(64, strlen($token), 'CSRF token should be 64 hex chars');
	}

	public function testCsrfTokenIsPersistentInSession(): void {
		$token1 = AdminEngine::csrfToken();
		$token2 = AdminEngine::csrfToken();
		$this->assertEquals($token1, $token2);
	}

	public function testCsrfTokenStoredInSession(): void {
		$token = AdminEngine::csrfToken();
		$this->assertEquals($token, $_SESSION['csrf']);
	}

	/* ═══ ユーザー管理 ═══ */

	public function testAddUserSuccess(): void {
		$result = AdminEngine::addUser('testuser', 'password123', 'editor');
		$this->assertTrue($result);
	}

	public function testAddUserDuplicate(): void {
		AdminEngine::addUser('dupuser', 'pass1', 'editor');
		$this->clearJsonCache();
		$result = AdminEngine::addUser('dupuser', 'pass2', 'editor');
		$this->assertFalse($result);
	}

	public function testAddUserInvalidUsername(): void {
		/* 短すぎる */
		$this->assertFalse(AdminEngine::addUser('ab', 'pass', 'editor'));
		/* 特殊文字 */
		$this->assertFalse(AdminEngine::addUser('user@name', 'pass', 'editor'));
		$this->assertFalse(AdminEngine::addUser('user name', 'pass', 'editor'));
	}

	public function testAddUserInvalidRole(): void {
		$this->assertFalse(AdminEngine::addUser('validuser', 'pass', 'superadmin'));
	}

	public function testAddUserValidRoles(): void {
		$this->assertTrue(AdminEngine::addUser('admin_user', 'pass', 'admin'));
		$this->clearJsonCache();
		$this->assertTrue(AdminEngine::addUser('editor_user', 'pass', 'editor'));
		$this->clearJsonCache();
		$this->assertTrue(AdminEngine::addUser('viewer_user', 'pass', 'viewer'));
	}

	public function testDeleteUser(): void {
		AdminEngine::addUser('delme', 'pass', 'editor');
		$this->clearJsonCache();
		$result = AdminEngine::deleteUser('delme');
		$this->assertTrue($result);
	}

	public function testDeleteNonExistentUser(): void {
		$result = AdminEngine::deleteUser('ghost_user_xyz');
		$this->assertFalse($result);
	}

	public function testListUsersExcludesPasswordHash(): void {
		AdminEngine::addUser('listtest', 'secret', 'editor');
		$this->clearJsonCache();
		$users = AdminEngine::listUsers();
		$this->assertNotEmpty($users);
		foreach ($users as $u) {
			$this->assertArrayHasKey('username', $u);
			$this->assertArrayHasKey('role', $u);
			/* パスワードハッシュが含まれていないこと */
			$this->assertFalse(isset($u['password_hash']), 'password_hash should not be exposed');
		}
	}

	/* ═══ パスワード保存 ═══ */

	public function testSavePasswordReturnsBcryptHash(): void {
		$hash = AdminEngine::savePassword('testpassword');
		$this->assertNotEmpty($hash);
		$this->assertTrue(password_verify('testpassword', $hash));
	}

	public function testSavePasswordDifferentHashEachTime(): void {
		$hash1 = AdminEngine::savePassword('same');
		$this->clearJsonCache();
		$hash2 = AdminEngine::savePassword('same');
		/* bcrypt は毎回異なるソルトを使用 */
		$this->assertNotEquals($hash1, $hash2);
	}
}
