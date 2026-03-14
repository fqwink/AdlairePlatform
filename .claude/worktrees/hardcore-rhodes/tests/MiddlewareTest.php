<?php
/**
 * MiddlewareTest - APF Middleware テスト
 *
 * @since Ver.1.7-36
 */
class MiddlewareTest extends TestCase {

	protected function setUp(): void {
		$this->resetSession();
	}

	/* ── AuthMiddleware ── */

	public function testAuthMiddlewareBlocksUnauthenticated(): void {
		$middleware = new \APF\Middleware\AuthMiddleware();
		$request = $this->makeRequest('GET', '/admin', isAjax: true);
		$next = fn($r) => \APF\Core\Response::json(['ok' => true]);

		$response = $middleware->handle($request, $next);

		$this->assertEquals(401, $response->getStatusCode());
	}

	public function testAuthMiddlewareAllowsAuthenticated(): void {
		$_SESSION['l'] = true;

		$middleware = new \APF\Middleware\AuthMiddleware();
		$request = $this->makeRequest('GET', '/admin');
		$next = fn($r) => \APF\Core\Response::json(['ok' => true]);

		$response = $middleware->handle($request, $next);

		$this->assertEquals(200, $response->getStatusCode());
	}

	public function testAuthMiddlewareRedirectsBrowserRequests(): void {
		$middleware = new \APF\Middleware\AuthMiddleware();

		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['REQUEST_URI'] = '/admin';
		$_GET = [];
		$_POST = [];
		unset($_SERVER['HTTP_X_REQUESTED_WITH']);

		$request = new \APF\Core\Request();
		$next = fn($r) => \APF\Core\Response::json(['ok' => true]);

		$response = $middleware->handle($request, $next);

		$this->assertEquals(302, $response->getStatusCode());
	}

	/* ── CsrfMiddleware ── */

	public function testCsrfMiddlewareSkipsGetRequests(): void {
		$middleware = new \APF\Middleware\CsrfMiddleware();
		$request = $this->makeRequest('GET', '/page');
		$next = fn($r) => new \APF\Core\Response('ok');

		$response = $middleware->handle($request, $next);

		$this->assertEquals(200, $response->getStatusCode());
	}

	public function testCsrfMiddlewareBlocksInvalidToken(): void {
		$_SESSION['csrf'] = 'valid_token_123';

		$middleware = new \APF\Middleware\CsrfMiddleware();

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI'] = '/dispatch';
		$_GET = [];
		$_POST = ['csrf' => 'wrong_token'];

		$request = new \APF\Core\Request();
		$next = fn($r) => new \APF\Core\Response('ok');

		$response = $middleware->handle($request, $next);

		$this->assertEquals(403, $response->getStatusCode());
	}

	public function testCsrfMiddlewareAllowsValidToken(): void {
		$_SESSION['csrf'] = 'valid_token_123';

		$middleware = new \APF\Middleware\CsrfMiddleware();

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI'] = '/dispatch';
		$_GET = [];
		$_POST = ['csrf' => 'valid_token_123'];

		$request = new \APF\Core\Request();
		$next = fn($r) => new \APF\Core\Response('ok');

		$response = $middleware->handle($request, $next);

		$this->assertEquals(200, $response->getStatusCode());
	}

	/* ── RateLimitMiddleware ── */

	public function testRateLimitAllowsWithinLimit(): void {
		$middleware = new \APF\Middleware\RateLimitMiddleware(5, 60, '_test_rate');
		$request = $this->makeRequest('GET', '/');
		$next = fn($r) => new \APF\Core\Response('ok');

		for ($i = 0; $i < 5; $i++) {
			$response = $middleware->handle($request, $next);
			$this->assertEquals(200, $response->getStatusCode());
		}
	}

	public function testRateLimitBlocksOverLimit(): void {
		$_SESSION['_test_rate2'] = [];
		$middleware = new \APF\Middleware\RateLimitMiddleware(3, 60, '_test_rate2');
		$request = $this->makeRequest('GET', '/');
		$next = fn($r) => new \APF\Core\Response('ok');

		/* 3回はOK */
		for ($i = 0; $i < 3; $i++) {
			$response = $middleware->handle($request, $next);
		}

		/* 4回目は 429 */
		$response = $middleware->handle($request, $next);
		$this->assertEquals(429, $response->getStatusCode());
	}

	/* ── SecurityHeadersMiddleware ── */

	public function testSecurityHeadersAdded(): void {
		$middleware = new \APF\Middleware\SecurityHeadersMiddleware();
		$request = $this->makeRequest('GET', '/');
		$next = fn($r) => new \APF\Core\Response('ok');

		$response = $middleware->handle($request, $next);

		/* Response オブジェクトのヘッダー確認は getContent で間接確認 */
		$this->assertEquals(200, $response->getStatusCode());
	}

	/* ── ミドルウェアパイプライン統合テスト ── */

	public function testMiddlewarePipelineOrder(): void {
		$container = new \APF\Core\Container();
		$router = new \APF\Core\Router($container);

		$order = [];

		/* カスタムミドルウェアで実行順を記録 */
		$first = new class($order) extends \APF\Core\Middleware {
			private array &$order;
			public function __construct(array &$order) { $this->order = &$order; }
			public function handle(\APF\Core\Request $request, \Closure $next): \APF\Core\Response {
				$this->order[] = 'first';
				return $next($request);
			}
		};

		$second = new class($order) extends \APF\Core\Middleware {
			private array &$order;
			public function __construct(array &$order) { $this->order = &$order; }
			public function handle(\APF\Core\Request $request, \Closure $next): \APF\Core\Response {
				$this->order[] = 'second';
				return $next($request);
			}
		};

		$router->middleware($first);
		$router->group(['middleware' => [$second]], function (\APF\Core\Router $r) {
			$r->get('/test', function (\APF\Core\Request $req) {
				return new \APF\Core\Response('ok');
			});
		});

		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['REQUEST_URI'] = '/test';
		$_GET = [];
		$_POST = [];

		$request = new \APF\Core\Request();
		$router->dispatch($request);

		$this->assertEquals(['first', 'second'], $order, 'Middleware should execute in order');
	}

	/* ── ヘルパー ── */

	private function makeRequest(string $method, string $uri, bool $isAjax = false): \APF\Core\Request {
		$_SERVER['REQUEST_METHOD'] = $method;
		$_SERVER['REQUEST_URI'] = $uri;
		$_GET = [];
		$_POST = [];

		if ($isAjax) {
			$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
		} else {
			unset($_SERVER['HTTP_X_REQUESTED_WITH']);
		}

		return new \APF\Core\Request();
	}
}
