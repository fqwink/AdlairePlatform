<?php
/**
 * ControllerTest - Controller 完全実装テスト
 *
 * Ver.1.7-37 で完全実装された Controller メソッドのテスト。
 * - Response::file() ファクトリ
 * - EngineTrait $throwOnError フラグ
 * - DiagnosticController / UpdateController / StaticController
 *
 * @since Ver.1.7-37
 */
class ControllerTest extends TestCase {

	protected function setUp(): void {
		$this->resetSession();
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI'] = '/dispatch';
		$_GET = [];
		$_POST = [];
	}

	/* ── Response::file() ── */

	public function testResponseFileFactory(): void {
		/* テスト用一時ファイルを作成 */
		$tmp = sys_get_temp_dir() . '/ap_test_' . mt_rand() . '.txt';
		file_put_contents($tmp, 'hello');

		$response = \APF\Core\Response::file($tmp, 'test.txt', 'text/plain');

		$this->assertEquals(200, $response->getStatusCode());

		/* クリーンアップ */
		@unlink($tmp);
	}

	/* ── EngineTrait $throwOnError ── */

	public function testEngineTraitThrowOnErrorFlag(): void {
		/* UpdateEngine の executeDeleteBackup は不正な名前で RuntimeException を投げる */
		$threw = false;
		try {
			\AIS\Deployment\UpdateService::executeDeleteBackup('');
		} catch (\RuntimeException $e) {
			$threw = true;
			$this->assertNotEmpty($e->getMessage());
		}
		$this->assertTrue($threw, 'executeDeleteBackup should throw on empty name');
	}

	public function testEngineTraitThrowOnErrorRollback(): void {
		$threw = false;
		try {
			\AIS\Deployment\UpdateService::executeRollback('');
		} catch (\RuntimeException $e) {
			$threw = true;
		}
		$this->assertTrue($threw, 'executeRollback should throw on empty name');
	}

	/* ── DiagnosticController ── */

	public function testDiagnosticControllerPreview(): void {
		$controller = new \AP\Controllers\DiagnosticController();
		$request = $this->makeRequest('POST', ['ap_action' => 'diag_preview']);

		$response = $controller->preview($request);

		$this->assertEquals(200, $response->getStatusCode());
		$this->assertContains('"ok":true', $response->getContent());
	}

	public function testDiagnosticControllerGetLogs(): void {
		$controller = new \AP\Controllers\DiagnosticController();
		$request = $this->makeRequest('POST', ['ap_action' => 'diag_get_logs']);

		$response = $controller->getLogs($request);

		$this->assertEquals(200, $response->getStatusCode());
		$this->assertContains('"ok":true', $response->getContent());
	}

	public function testDiagnosticControllerGetSummary(): void {
		$controller = new \AP\Controllers\DiagnosticController();
		$request = $this->makeRequest('POST', ['ap_action' => 'diag_get_summary']);

		$response = $controller->getSummary($request);

		$this->assertEquals(200, $response->getStatusCode());
		$this->assertContains('"ok":true', $response->getContent());
	}

	public function testDiagnosticControllerHealth(): void {
		$controller = new \AP\Controllers\DiagnosticController();
		$request = $this->makeRequest('POST', ['ap_action' => 'diag_health']);

		$response = $controller->health($request);

		$this->assertEquals(200, $response->getStatusCode());
		$this->assertContains('"ok":true', $response->getContent());
	}

	public function testDiagnosticControllerSetEnabledRequiresAuth(): void {
		/* 未認証 */
		$controller = new \AP\Controllers\DiagnosticController();
		$request = $this->makeRequest('POST', ['ap_action' => 'diag_set_enabled', 'enabled' => '1']);

		$response = $controller->setEnabled($request);

		$this->assertEquals(403, $response->getStatusCode());
	}

	public function testDiagnosticControllerClearLogsRequiresAuth(): void {
		$controller = new \AP\Controllers\DiagnosticController();
		$request = $this->makeRequest('POST', ['ap_action' => 'diag_clear_logs']);

		$response = $controller->clearLogs($request);

		$this->assertEquals(403, $response->getStatusCode());
	}

	public function testDiagnosticControllerSendNowRequiresAuth(): void {
		$controller = new \AP\Controllers\DiagnosticController();
		$request = $this->makeRequest('POST', ['ap_action' => 'diag_send_now']);

		$response = $controller->sendNow($request);

		$this->assertEquals(403, $response->getStatusCode());
	}

	/* ── UpdateController ── */

	public function testUpdateControllerApplyRequiresAuth(): void {
		$controller = new \AP\Controllers\UpdateController();
		$request = $this->makeRequest('POST', ['ap_action' => 'apply']);

		$response = $controller->apply($request);

		$this->assertEquals(403, $response->getStatusCode());
	}

	public function testUpdateControllerRollbackRequiresAuth(): void {
		$controller = new \AP\Controllers\UpdateController();
		$request = $this->makeRequest('POST', ['ap_action' => 'rollback', 'backup' => 'test']);

		$response = $controller->rollback($request);

		$this->assertEquals(403, $response->getStatusCode());
	}

	public function testUpdateControllerDeleteBackupRequiresAuth(): void {
		$controller = new \AP\Controllers\UpdateController();
		$request = $this->makeRequest('POST', ['ap_action' => 'delete_backup', 'backup' => 'test']);

		$response = $controller->deleteBackup($request);

		$this->assertEquals(403, $response->getStatusCode());
	}

	public function testUpdateControllerListBackups(): void {
		$controller = new \AP\Controllers\UpdateController();
		$request = $this->makeRequest('POST', ['ap_action' => 'list_backups']);

		$response = $controller->listBackups($request);

		$this->assertEquals(200, $response->getStatusCode());
		$this->assertContains('"ok":true', $response->getContent());
	}

	/* ── StaticController ── */

	public function testStaticControllerBuildZipNoStaticFiles(): void {
		/* 静的ファイル未生成状態でエラー返却を検証 */
		$_SESSION['l'] = true;
		$_SESSION['ap_role'] = 'admin';

		$controller = new \AP\Controllers\StaticController();
		$request = $this->makeRequest('POST', ['ap_action' => 'build_zip']);

		$response = $controller->buildZip($request);

		/* 静的ファイルがないのでエラー */
		$this->assertContains('"ok":false', $response->getContent());
	}

	public function testStaticControllerDeployDiffNoStaticFiles(): void {
		$_SESSION['l'] = true;
		$_SESSION['ap_role'] = 'admin';

		$controller = new \AP\Controllers\StaticController();
		$request = $this->makeRequest('POST', ['ap_action' => 'deploy_diff']);

		$response = $controller->deployDiff($request);

		$this->assertContains('"ok":false', $response->getContent());
	}

	/* ── ActionDispatcher Stage 2 アクション登録確認 ── */

	public function testActionDispatcherHasStage2Actions(): void {
		$actions = \AP\Controllers\ActionDispatcher::registeredActions();

		/* Stage 2 で完全実装された Controller アクション */
		$this->assertContains('build_zip', $actions);
		$this->assertContains('deploy_diff', $actions);
		$this->assertContains('apply', $actions);
		$this->assertContains('rollback', $actions);
		$this->assertContains('delete_backup', $actions);
		$this->assertContains('diag_send_now', $actions);
		$this->assertContains('diag_clear_logs', $actions);
		$this->assertContains('diag_health', $actions);
	}

	/* ── ヘルパー ── */

	private function makeRequest(string $method, array $post = []): \APF\Core\Request {
		$_SERVER['REQUEST_METHOD'] = $method;
		$_SERVER['REQUEST_URI'] = '/dispatch';
		$_GET = [];
		$_POST = $post;
		unset($_SERVER['HTTP_X_REQUESTED_WITH']);

		return new \APF\Core\Request();
	}
}
