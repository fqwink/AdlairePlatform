<?php
/**
 * ApiController - REST API エンドポイント
 *
 * ApiEngine の REST API ルーティングを Controller 経由で提供。
 * Router 経由で到達し、ApiEngine::handle() の echo+exit パターンで応答。
 *
 * Note: ApiEngine の全 22+ ハンドラの非 exit 化は Ver.1.8 以降で実施予定。
 * Stage 2 では Router 統合のみ実施し、エンジンの出力方式は維持する。
 *
 * @since Ver.1.7-36
 * @since Ver.1.7-37 Router 経由での API ルーティング統合
 */
namespace AP\Controllers;

use APF\Core\{Request, Response};

class ApiController extends BaseController {

	/**
	 * API リクエストを ApiEngine に委譲
	 *
	 * ApiEngine::handle() は内部で echo+exit するため、
	 * エンドポイント一致時はこのメソッドの戻り値に到達しない。
	 * 一致しない場合（handle() が return する場合）のみ 404 を返す。
	 */
	public function dispatch(Request $request): Response {
		/* ApiEngine::handle() は $_GET['ap_api'] を参照する。
		   Router の mapQuery により URI パスに変換されているため、
		   元のクエリパラメータを復元する。 */
		$endpoint = $request->param('endpoint') ?? '';
		if ($endpoint !== '' && !isset($_GET['ap_api'])) {
			$_GET['ap_api'] = $endpoint;
		}

		\ApiEngine::handle();

		/* ApiEngine::handle() がマッチしなかった場合のみここに到達 */
		return $this->error('Unknown API endpoint', 404);
	}
}
