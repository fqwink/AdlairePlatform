<?php
/**
 * DashboardController - 管理ダッシュボード表示
 *
 * @since Ver.1.7-36
 */
namespace AP\Controllers;

use APF\Core\{Request, Response};

class DashboardController extends BaseController {

	/** GET /admin — ダッシュボード表示 */
	public function index(Request $request): Response {
		$html = \AdminEngine::renderDashboard();
		return Response::html($html);
	}
}
