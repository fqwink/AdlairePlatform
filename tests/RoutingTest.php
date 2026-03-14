<?php
/**
 * RoutingTest - APF Router + Controller + ActionDispatcher テスト
 *
 * @since Ver.1.7-36
 */
class RoutingTest extends TestCase {

	private \APF\Core\Router $router;
	private \APF\Core\Container $container;

	protected function setUp(): void {
		$this->container = new \APF\Core\Container();
		$this->router = new \APF\Core\Router($this->container);
	}

	/* ── Router 基本ルーティング ── */

	public function testGetRouteMatches(): void {
		$this->router->get('/test', function (\APF\Core\Request $r) {
			return \APF\Core\Response::json(['ok' => true]);
		});

		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['REQUEST_URI'] = '/test';
		$_GET = [];
		$_POST = [];

		$request = new \APF\Core\Request();
		$response = $this->router->dispatch($request);

		$this->assertEquals(200, $response->getStatusCode());
		$this->assertContains('"ok":true', $response->getContent());
	}

	public function testPostRouteMatches(): void {
		$this->router->post('/submit', function (\APF\Core\Request $r) {
			return \APF\Core\Response::json(['submitted' => true]);
		});

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI'] = '/submit';
		$_GET = [];
		$_POST = [];

		$request = new \APF\Core\Request();
		$response = $this->router->dispatch($request);

		$this->assertEquals(200, $response->getStatusCode());
	}

	public function testUnmatchedRouteReturns404(): void {
		$this->router->get('/exists', function (\APF\Core\Request $r) {
			return new \APF\Core\Response('ok');
		});

		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['REQUEST_URI'] = '/not-exists';
		$_GET = [];
		$_POST = [];

		$request = new \APF\Core\Request();
		$response = $this->router->dispatch($request);

		$this->assertEquals(404, $response->getStatusCode());
	}

	/* ── パラメータ付きルート ── */

	public function testRouteWithParams(): void {
		$captured = null;
		$this->router->get('/page/{slug}', function (\APF\Core\Request $r) use (&$captured) {
			$captured = $r->param('slug');
			return new \APF\Core\Response('ok');
		});

		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['REQUEST_URI'] = '/page/hello-world';
		$_GET = [];
		$_POST = [];

		$request = new \APF\Core\Request();
		$this->router->dispatch($request);

		$this->assertEquals('hello-world', $captured);
	}

	/* ── クエリマッピング ── */

	public function testMapQueryMapsKeyToPath(): void {
		$this->router->mapQuery('login', '/login');
		$this->router->get('/login', function (\APF\Core\Request $r) {
			return \APF\Core\Response::json(['route' => 'login']);
		});

		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['REQUEST_URI'] = '/';
		$_GET = ['login' => ''];
		$_POST = [];

		$request = new \APF\Core\Request();
		$response = $this->router->dispatch($request);

		$this->assertEquals(200, $response->getStatusCode());
		$this->assertContains('login', $response->getContent());
	}

	public function testMapQueryWithPathParam(): void {
		$captured = null;
		$this->router->mapQuery('ap_api', '/api/{endpoint}', 'endpoint');
		$this->router->get('/api/{endpoint}', function (\APF\Core\Request $r) use (&$captured) {
			$captured = $r->param('endpoint');
			return new \APF\Core\Response('ok');
		});

		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['REQUEST_URI'] = '/';
		$_GET = ['ap_api' => 'pages'];
		$_POST = [];

		$request = new \APF\Core\Request();
		$this->router->dispatch($request);

		$this->assertEquals('pages', $captured);
	}

	/* ── POST マッピング ── */

	public function testMapPostMapsKeyToPath(): void {
		$this->router->mapPost('ap_action', '/dispatch');
		$this->router->post('/dispatch', function (\APF\Core\Request $r) {
			return \APF\Core\Response::json(['dispatched' => true]);
		});

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI'] = '/';
		$_GET = [];
		$_POST = ['ap_action' => 'edit_field'];

		$request = new \APF\Core\Request();
		$response = $this->router->dispatch($request);

		$this->assertEquals(200, $response->getStatusCode());
	}

	/* ── ルートグループ ── */

	public function testGroupAppliesPrefix(): void {
		$this->router->group(['prefix' => '/admin'], function (\APF\Core\Router $r) {
			$r->get('/settings', function (\APF\Core\Request $req) {
				return new \APF\Core\Response('settings');
			});
		});

		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['REQUEST_URI'] = '/admin/settings';
		$_GET = [];
		$_POST = [];

		$request = new \APF\Core\Request();
		$response = $this->router->dispatch($request);

		$this->assertEquals(200, $response->getStatusCode());
		$this->assertEquals('settings', $response->getContent());
	}

	/* ── メソッド不一致 ── */

	public function testWrongMethodReturns404(): void {
		$this->router->get('/only-get', function (\APF\Core\Request $r) {
			return new \APF\Core\Response('ok');
		});

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI'] = '/only-get';
		$_GET = [];
		$_POST = [];

		$request = new \APF\Core\Request();
		$response = $this->router->dispatch($request);

		$this->assertEquals(404, $response->getStatusCode());
	}

	/* ── any() ルート ── */

	public function testAnyRouteMatchesMultipleMethods(): void {
		$this->router->any('/flex', function (\APF\Core\Request $r) {
			return new \APF\Core\Response('flex');
		});

		foreach (['GET', 'POST', 'PUT', 'DELETE'] as $method) {
			$_SERVER['REQUEST_METHOD'] = $method;
			$_SERVER['REQUEST_URI'] = '/flex';
			$_GET = [];
			$_POST = [];

			$request = new \APF\Core\Request();
			$response = $this->router->dispatch($request);

			$this->assertEquals(200, $response->getStatusCode(), "any() should match {$method}");
		}
	}

	/* ── API ルート（Ver.1.7-37） ── */

	public function testApiRouteQueryMapping(): void {
		$captured = null;
		$this->router->mapQuery('ap_api', '/api/{endpoint}', 'endpoint');
		$this->router->any('/api/{endpoint}', function (\APF\Core\Request $r) use (&$captured) {
			$captured = $r->param('endpoint');
			return new \APF\Core\Response('api-ok');
		});

		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['REQUEST_URI'] = '/';
		$_GET = ['ap_api' => 'collections'];
		$_POST = [];

		$request = new \APF\Core\Request();
		$response = $this->router->dispatch($request);

		$this->assertEquals(200, $response->getStatusCode());
		$this->assertEquals('collections', $captured);
	}

	public function testApiRoutePostMethod(): void {
		$this->router->mapQuery('ap_api', '/api/{endpoint}', 'endpoint');
		$this->router->any('/api/{endpoint}', function (\APF\Core\Request $r) {
			return \APF\Core\Response::json(['endpoint' => $r->param('endpoint')]);
		});

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI'] = '/';
		$_GET = ['ap_api' => 'contact'];
		$_POST = ['name' => 'Test'];

		$request = new \APF\Core\Request();
		$response = $this->router->dispatch($request);

		$this->assertEquals(200, $response->getStatusCode());
		$this->assertContains('contact', $response->getContent());
	}

	/* ── ActionDispatcher ── */

	public function testActionDispatcherRegisteredActions(): void {
		$actions = \AP\Controllers\ActionDispatcher::registeredActions();

		$this->assertContains('edit_field', $actions);
		$this->assertContains('collection_create', $actions);
		$this->assertContains('git_pull', $actions);
		$this->assertContains('webhook_add', $actions);
		$this->assertContains('generate_static_diff', $actions);
		$this->assertContains('check', $actions);
		$this->assertContains('diag_health', $actions);
	}

	public function testActionDispatcherRejectsUnknownAction(): void {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI'] = '/dispatch';
		$_GET = [];
		$_POST = ['ap_action' => 'nonexistent_action_xyz'];

		$request = new \APF\Core\Request();
		$dispatcher = new \AP\Controllers\ActionDispatcher();
		$response = $dispatcher->handle($request);

		$this->assertEquals(404, $response->getStatusCode());
	}

	public function testActionDispatcherRejectsMissingAction(): void {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI'] = '/dispatch';
		$_GET = [];
		$_POST = [];

		$request = new \APF\Core\Request();
		$dispatcher = new \AP\Controllers\ActionDispatcher();
		$response = $dispatcher->handle($request);

		$this->assertEquals(400, $response->getStatusCode());
	}

	/* ── Controller/Array アクション ── */

	public function testControllerActionViaRouter(): void {
		$this->router->get('/ctrl', [\AP\Controllers\DashboardController::class, 'index']);

		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['REQUEST_URI'] = '/ctrl';
		$_GET = [];
		$_POST = [];
		$_SESSION['l'] = true;
		$_SESSION['ap_role'] = 'admin';

		$request = new \APF\Core\Request();
		$response = $this->router->dispatch($request);

		/* DashboardController は AdminEngine::renderDashboard() を呼ぶため HTML が返る */
		$this->assertEquals(200, $response->getStatusCode());
	}
}
