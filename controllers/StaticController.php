<?php
/**
 * StaticController - 静的サイト生成
 *
 * StaticEngine の handle() private ハンドラを Controller メソッドとして提供。
 *
 * @since Ver.1.7-36
 * @since Ver.1.7-37 buildZip/deployDiff 完全実装（Engine exit 委譲を除去）
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

	/** ZIP ダウンロード */
	public function buildZip(Request $request): Response {
		try {
			$engine = new \StaticEngine();
			$engine->init();
			$path = $engine->buildZipFile();
			return Response::file($path, 'static-' . date('Ymd') . '.zip', 'application/zip');
		} catch (\RuntimeException $e) {
			return $this->error($e->getMessage());
		}
	}

	/** ビルドステータス取得 */
	public function status(Request $request): Response {
		$engine = new \StaticEngine();
		$engine->init();
		$result = $engine->getStatus();
		return Response::json(['ok' => true, 'data' => $result]);
	}

	/** デプロイ差分ZIP */
	public function deployDiff(Request $request): Response {
		try {
			$engine = new \StaticEngine();
			$engine->init();
			$path = $engine->buildDiffZipFile();
			return Response::file($path, 'deploy-diff-' . date('Ymd') . '.zip', 'application/zip');
		} catch (\RuntimeException $e) {
			return $this->error($e->getMessage());
		}
	}
}
