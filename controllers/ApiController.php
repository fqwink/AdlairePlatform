<?php
/**
 * ApiController - REST API エンドポイント
 *
 * ApiEngine の REST API ルーティングを Controller 経由で提供。
 * Stage 1: エンジンの public メソッドに委譲。private ハンドラは Stage 2 で移行。
 *
 * @since Ver.1.7-36
 */
namespace AP\Controllers;

use APF\Core\{Request, Response};

class ApiController extends BaseController {

	/**
	 * API リクエストを ApiEngine に委譲
	 *
	 * ApiEngine::handle() は echo+exit パターンのため、
	 * Stage 1 では既存エンジンに直接委譲する。
	 * Stage 2 でハンドラを個別メソッドに移行予定。
	 */
	public function handle(Request $request): Response {
		$endpoint = $request->query('ap_api', '') !== ''
			? $request->query('ap_api', '')
			: ($request->param('endpoint') ?? '');

		if ($endpoint === '') {
			return $this->error('Missing API endpoint', 400);
		}

		/* ApiEngine::handle() は内部で echo+exit するため、
		   Stage 1 ではこのメソッドの戻り値は到達しない。
		   Router から呼ばれた場合も ApiEngine が直接レスポンスを出力する。 */
		\ApiEngine::handle();

		/* Stage 2 で到達可能になる（ApiEngine::handle() を非 exit 化後） */
		return Response::json(['error' => 'Unhandled API endpoint'], 404);
	}
}
