<?php
/**
 * StaticController - 静的サイト生成
 *
 * StaticEngine の handle() private ハンドラを Controller メソッドとして提供。
 * ファイルダウンロード系 (build_zip, deploy_diff) は Stage 2 で完全移行予定。
 *
 * @since Ver.1.7-36
 */
namespace AP\Controllers;

use APF\Core\{Request, Response};

class StaticController extends BaseController {

	/** 差分ビルド */
	public function buildDiff(Request $request): Response {
		$engine = new \StaticEngine();
		$engine->init();
		$result = $engine->buildDiff();
		\AdminEngine::logActivity('静的サイト差分ビルド');
		if (class_exists('WebhookEngine')) {
			\WebhookEngine::dispatch('build.completed', $result);
		}
		return Response::json(['ok' => true, 'data' => $result]);
	}

	/** フルビルド */
	public function buildAll(Request $request): Response {
		$engine = new \StaticEngine();
		$engine->init();
		$result = $engine->buildAll();
		\AdminEngine::logActivity('静的サイトフルビルド');
		if (class_exists('WebhookEngine')) {
			\WebhookEngine::dispatch('build.completed', $result);
		}
		return Response::json(['ok' => true, 'data' => $result]);
	}

	/** 静的ファイルクリーン */
	public function clean(Request $request): Response {
		$engine = new \StaticEngine();
		$engine->init();
		$engine->clean();
		\AdminEngine::logActivity('静的サイトクリーン');
		return $this->ok();
	}

	/** ZIP ダウンロード（Stage 2 で完全移行） */
	public function buildZip(Request $request): Response {
		/* StaticEngine::serveZip() は直接ファイルを出力するため、
		   Stage 1 では既存エンジンに委譲 */
		$engine = new \StaticEngine();
		$engine->init();
		$engine->serveZip();
		/* serveZip() は exit() するため到達しない */
		return $this->error('Unexpected state', 500);
	}

	/** ビルドステータス取得 */
	public function status(Request $request): Response {
		$engine = new \StaticEngine();
		$engine->init();
		$result = $engine->getStatus();
		return Response::json(['ok' => true, 'data' => $result]);
	}

	/** デプロイ差分ZIP（Stage 2 で完全移行） */
	public function deployDiff(Request $request): Response {
		$engine = new \StaticEngine();
		$engine->init();
		$engine->serveDiffZip();
		/* serveDiffZip() は exit() するため到達しない */
		return $this->error('Unexpected state', 500);
	}
}
