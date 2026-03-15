<?php
/**
 * StaticController - 静的サイト生成
 *
 * ASG\Core\StaticService の機能を Controller メソッドとして提供。
 *
 * @since Ver.1.7-36
 * @since Ver.1.7-37 buildZip/deployDiff 完全実装（Engine exit 委譲を除去）
 * @since Ver.1.8   StaticEngine → StaticService 移行
 */
namespace AP\Controllers;

use APF\Core\{Request, Response};

class StaticController extends BaseController {

	/** 差分ビルド */
	public function buildDiff(Request $request): Response {
		\ASG\Core\StaticService::init();
		$result = \ASG\Core\StaticService::buildDiff();
		\ACE\Admin\AdminManager::logActivity('静的サイト差分ビルド');
		return Response::json(['ok' => true, 'data' => $result]);
	}

	/** フルビルド */
	public function buildAll(Request $request): Response {
		\ASG\Core\StaticService::init();
		$result = \ASG\Core\StaticService::buildAll();
		\ACE\Admin\AdminManager::logActivity('静的サイトフルビルド');
		return Response::json(['ok' => true, 'data' => $result]);
	}

	/** 静的ファイルクリーン */
	public function clean(Request $request): Response {
		\ASG\Core\StaticService::init();
		\ASG\Core\StaticService::clean();
		\ACE\Admin\AdminManager::logActivity('静的サイトクリーン');
		return $this->ok();
	}

	/** ZIP ダウンロード */
	public function buildZip(Request $request): Response {
		try {
			\ASG\Core\StaticService::init();
			$path = \ASG\Core\StaticService::buildZipFile();
			return Response::file($path, 'static-' . date('Ymd') . '.zip', 'application/zip');
		} catch (\RuntimeException $e) {
			return $this->error($e->getMessage());
		}
	}

	/** ビルドステータス取得 */
	public function status(Request $request): Response {
		\ASG\Core\StaticService::init();
		$result = \ASG\Core\StaticService::getStatus();
		return Response::json(['ok' => true, 'data' => $result]);
	}

	/** デプロイ差分ZIP */
	public function deployDiff(Request $request): Response {
		try {
			\ASG\Core\StaticService::init();
			$path = \ASG\Core\StaticService::buildDiffZipFile();
			return Response::file($path, 'deploy-diff-' . date('Ymd') . '.zip', 'application/zip');
		} catch (\RuntimeException $e) {
			return $this->error($e->getMessage());
		}
	}
}
