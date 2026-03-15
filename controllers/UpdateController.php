<?php
/**
 * UpdateController - アップデート・バックアップ管理
 *
 * UpdateEngine の handle() private ハンドラを Controller メソッドとして提供。
 *
 * @since Ver.1.7-36
 * @since Ver.1.7-37 apply/rollback/deleteBackup 完全実装（Engine exit 委譲を除去）
 */
namespace AP\Controllers;

use APF\Core\{Request, Response};

class UpdateController extends BaseController {

	/** アップデート確認 */
	public function check(Request $request): Response {
		$result = \AIS\Deployment\UpdateService::checkUpdate();
		return Response::json(['ok' => true, 'data' => $result]);
	}

	/** 環境互換チェック */
	public function checkEnv(Request $request): Response {
		$result = \AIS\Deployment\UpdateService::checkEnvironment();
		return Response::json(['ok' => true, 'data' => $result]);
	}

	/** アップデート適用 */
	public function apply(Request $request): Response {
		if ($err = $this->requireRole('admin')) return $err;

		try {
			$result = \AIS\Deployment\UpdateService::executeApplyUpdate();
			\ACE\Admin\AdminManager::logActivity('アップデート適用');
			return Response::json($result);
		} catch (\RuntimeException $e) {
			return $this->error($e->getMessage(), 500);
		}
	}

	/** バックアップ一覧 */
	public function listBackups(Request $request): Response {
		$backups = glob('backup/*', GLOB_ONLYDIR);
		$list = [];
		if (is_array($backups)) {
			rsort($backups);
			foreach ($backups as $path) {
				$name = basename($path);
				$metaFile = $path . '/meta.json';
				$meta = null;
				if (file_exists($metaFile)) {
					$decoded = \APF\Utilities\FileSystem::readJson($metaFile);
					if (is_array($decoded)) $meta = $decoded;
				}
				$list[] = ['name' => $name, 'meta' => $meta];
			}
		}
		return Response::json(['ok' => true, 'data' => ['backups' => $list]]);
	}

	/** バックアップからロールバック */
	public function rollback(Request $request): Response {
		if ($err = $this->requireRole('admin')) return $err;

		$name = trim($request->post('backup', ''));
		try {
			$result = \AIS\Deployment\UpdateService::executeRollback($name);
			\ACE\Admin\AdminManager::logActivity('ロールバック実行: ' . basename($name));
			return Response::json($result);
		} catch (\RuntimeException $e) {
			return $this->error($e->getMessage());
		}
	}

	/** バックアップ削除 */
	public function deleteBackup(Request $request): Response {
		if ($err = $this->requireRole('admin')) return $err;

		$name = trim($request->post('backup', ''));
		try {
			$result = \AIS\Deployment\UpdateService::executeDeleteBackup($name);
			\ACE\Admin\AdminManager::logActivity('バックアップ削除: ' . basename($name));
			return Response::json($result);
		} catch (\RuntimeException $e) {
			return $this->error($e->getMessage());
		}
	}
}
