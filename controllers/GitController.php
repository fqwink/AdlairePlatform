<?php
/**
 * GitController - Git/GitHub 連携
 *
 * GitEngine の handle() private ハンドラを Controller メソッドとして提供。
 *
 * @since Ver.1.7-36
 */
namespace AP\Controllers;

use APF\Core\{Request, Response};

class GitController extends BaseController {

	/** Git 設定保存 */
	public function configure(Request $request): Response {
		if ($err = $this->requireRole('admin')) return $err;

		$config = \AIS\Deployment\GitService::loadConfig();
		$fields = ['repository', 'token', 'branch', 'content_dir', 'webhook_secret'];
		foreach ($fields as $f) {
			$val = $request->post($f);
			if ($val !== null) $config[$f] = trim($val);
		}
		$config['enabled']        = !empty($request->post('enabled'));
		$config['issues_enabled'] = !empty($request->post('issues_enabled'));

		\AIS\Deployment\GitService::saveConfig($config);
		\ACE\Admin\AdminManager::logActivity('Git設定変更');
		return $this->ok();
	}

	/** 接続テスト */
	public function test(Request $request): Response {
		$result = \AIS\Deployment\GitService::testConnection();
		return Response::json(['ok' => ($result['status'] ?? '') === 'ok', 'data' => $result]);
	}

	/** GitHub → ローカル同期 */
	public function pull(Request $request): Response {
		$result = \AIS\Deployment\GitService::pull();
		if (($result['status'] ?? '') === 'ok') {
			\ACE\Admin\AdminManager::logActivity('Git pull 実行');
			\AIS\System\ApiCache::invalidateContent();
		}
		return Response::json(['ok' => ($result['status'] ?? '') === 'ok', 'data' => $result]);
	}

	/** ローカル → GitHub 同期 */
	public function push(Request $request): Response {
		$message = trim($request->post('message', 'Update content from AdlairePlatform'));
		$result = \AIS\Deployment\GitService::push($message);
		if (($result['status'] ?? '') === 'ok') {
			\ACE\Admin\AdminManager::logActivity('Git push 実行');
		}
		return Response::json(['ok' => ($result['status'] ?? '') === 'ok', 'data' => $result]);
	}

	/** コミット履歴取得 */
	public function log(Request $request): Response {
		$limit = min(100, max(1, (int)$request->post('limit', 20)));
		$result = \AIS\Deployment\GitService::log($limit);
		return Response::json(['ok' => true, 'data' => $result]);
	}

	/** Git ステータス取得 */
	public function status(Request $request): Response {
		$result = \AIS\Deployment\GitService::status();
		return Response::json(['ok' => true, 'data' => $result]);
	}

	/** プレビューブランチ作成 */
	public function previewBranch(Request $request): Response {
		$name = trim($request->post('name', 'draft'));
		$result = \AIS\Deployment\GitService::createPreviewBranch($name);
		return Response::json(['ok' => ($result['status'] ?? '') === 'ok', 'data' => $result]);
	}
}
