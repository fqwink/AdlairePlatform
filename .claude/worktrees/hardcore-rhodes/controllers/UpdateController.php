<?php
/**
 * UpdateController - アップデート・バックアップ管理
 *
 * UpdateEngine の handle() private ハンドラを Controller メソッドとして提供。
 *
 * @since Ver.1.7-36
 */
namespace AP\Controllers;

use APF\Core\{Request, Response};

class UpdateController extends BaseController {

	/** アップデート確認 */
	public function check(Request $request): Response {
		$result = \UpdateEngine::checkUpdate();
		return Response::json(['ok' => true, 'data' => $result]);
	}

	/** 環境互換チェック */
	public function checkEnv(Request $request): Response {
		$result = \UpdateEngine::checkEnvironment();
		return Response::json(['ok' => true, 'data' => $result]);
	}

	/** アップデート適用 */
	public function apply(Request $request): Response {
		if ($err = $this->requireRole('admin')) return $err;

		$result = \UpdateEngine::checkUpdate();
		if (empty($result['available'])) {
			return $this->error('利用可能なアップデートがありません');
		}

		/* applyUpdate は UpdateEngine の handle() 内で呼ばれる private メソッド。
		   Stage 1 では既存エンジンに委譲する。 */
		\UpdateEngine::handle();
		/* handle() は exit() するため到達しない */
		return $this->error('Unexpected state', 500);
	}

	/** バックアップ一覧 */
	public function listBackups(Request $request): Response {
		$backupDir = defined('AP_BACKUP_DIR') ? AP_BACKUP_DIR : 'backup/';
		$backups = [];
		if (is_dir($backupDir)) {
			$dirs = glob($backupDir . '*/metadata.json');
			foreach ($dirs ?: [] as $metaFile) {
				$meta = \FileSystem::readJson($metaFile);
				if ($meta !== null) {
					$meta['dir'] = basename(dirname($metaFile));
					$backups[] = $meta;
				}
			}
			usort($backups, fn($a, $b) => ($b['created_at'] ?? '') <=> ($a['created_at'] ?? ''));
		}
		return Response::json(['ok' => true, 'data' => $backups]);
	}

	/** バックアップからロールバック */
	public function rollback(Request $request): Response {
		if ($err = $this->requireRole('admin')) return $err;

		/* rollbackToBackup は private メソッド — Stage 1 では既存エンジンに委譲 */
		\UpdateEngine::handle();
		return $this->error('Unexpected state', 500);
	}

	/** バックアップ削除 */
	public function deleteBackup(Request $request): Response {
		if ($err = $this->requireRole('admin')) return $err;

		/* deleteBackup は private メソッド — Stage 1 では既存エンジンに委譲 */
		\UpdateEngine::handle();
		return $this->error('Unexpected state', 500);
	}
}
