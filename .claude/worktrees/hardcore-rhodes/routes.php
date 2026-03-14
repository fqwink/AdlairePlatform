<?php
/**
 * AdlairePlatform - ルート定義
 *
 * APF Router にルートとミドルウェアを登録する。
 * index.php から bootstrap.php の後に読み込む。
 *
 * 既存の ?login / ?admin / ap_action= クエリパラメータ方式を
 * URI パスにマッピングし、Controller にルーティングする。
 *
 * Stage 1: API ルート（?ap_api=）はレガシーエンジンチェーンが処理。
 *          Stage 2 で ApiController に完全移行予定。
 *
 * @since Ver.1.7-36
 * @license Adlaire License Ver.2.0
 */

use APF\Core\Router;
use APF\Middleware\{AuthMiddleware, CsrfMiddleware, SecurityHeadersMiddleware};
use AP\Controllers\{AuthController, DashboardController, ActionDispatcher};

/**
 * @var Router $router  bootstrap.php で DI コンテナに登録済み
 */
$router = Application::make(Router::class);

/* ══════════════════════════════════════════════════
 * グローバルミドルウェア（全ルートに適用）
 * ══════════════════════════════════════════════════ */
$router->middleware(new SecurityHeadersMiddleware());

/* ══════════════════════════════════════════════════
 * クエリ/POST パラメータ → URI パスのマッピング
 *
 * 既存 URL 互換:
 *   ?login         → /login
 *   ?admin         → /admin
 *   POST ap_action → /dispatch
 *
 * Note: ?ap_api= は Stage 1 ではレガシーチェーンが処理するため
 *       マッピングしない。Stage 2 で追加予定。
 * ══════════════════════════════════════════════════ */
$router->mapQuery('login', '/login');
$router->mapQuery('admin', '/admin');
$router->mapPost('ap_action', '/dispatch');

/* ══════════════════════════════════════════════════
 * 認証ルート（ミドルウェアなし — 未認証でアクセス）
 * ══════════════════════════════════════════════════ */
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'authenticate']);

/* ══════════════════════════════════════════════════
 * 認証済みルート
 * ══════════════════════════════════════════════════ */
$router->group(['middleware' => [new AuthMiddleware()]], function (Router $router) {

	/* ── ダッシュボード ── */
	$router->get('/admin', [DashboardController::class, 'index']);

	/* ── ログアウト（CSRF 検証は Controller 内で実施） ── */
	$router->post('/logout', [AuthController::class, 'logout']);

	/* ── POST アクションディスパッチ ── */
	$router->group(['middleware' => [new CsrfMiddleware()]], function (Router $router) {
		$router->post('/dispatch', [ActionDispatcher::class, 'handle']);
	});
});
