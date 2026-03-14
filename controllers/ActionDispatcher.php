<?php
/**
 * ActionDispatcher - POST ap_action ディスパッチャ
 *
 * 全 ap_action 値を対応する Controller メソッドにルーティングする。
 * 旧 Engine::handle() チェーンの Controller 版。
 *
 * @since Ver.1.7-36
 */
namespace AP\Controllers;

use APF\Core\{Request, Response};

class ActionDispatcher extends BaseController {

	/**
	 * ap_action → [ControllerClass, method] マッピング
	 *
	 * 各エンジンの handle() が処理していた ap_action 値を
	 * 対応する Controller メソッドに明示的にマッピング。
	 */
	private const ACTION_MAP = [
		/* AdminController */
		'edit_field'        => [AdminController::class, 'editField'],
		'upload_image'      => [AdminController::class, 'uploadImage'],
		'delete_page'       => [AdminController::class, 'deletePage'],
		'list_revisions'    => [AdminController::class, 'listRevisions'],
		'get_revision'      => [AdminController::class, 'getRevision'],
		'restore_revision'  => [AdminController::class, 'restoreRevision'],
		'pin_revision'      => [AdminController::class, 'pinRevision'],
		'search_revisions'  => [AdminController::class, 'searchRevisions'],
		'user_add'          => [AdminController::class, 'userAdd'],
		'user_delete'       => [AdminController::class, 'userDelete'],
		'redirect_add'      => [AdminController::class, 'redirectAdd'],
		'redirect_delete'   => [AdminController::class, 'redirectDelete'],

		/* CollectionController */
		'collection_create'      => [CollectionController::class, 'create'],
		'collection_delete'      => [CollectionController::class, 'delete'],
		'collection_item_save'   => [CollectionController::class, 'itemSave'],
		'collection_item_delete' => [CollectionController::class, 'itemDelete'],
		'collection_migrate'     => [CollectionController::class, 'migrate'],

		/* GitController */
		'git_configure'      => [GitController::class, 'configure'],
		'git_test'           => [GitController::class, 'test'],
		'git_pull'           => [GitController::class, 'pull'],
		'git_push'           => [GitController::class, 'push'],
		'git_log'            => [GitController::class, 'log'],
		'git_status'         => [GitController::class, 'status'],
		'git_preview_branch' => [GitController::class, 'previewBranch'],

		/* WebhookController */
		'webhook_add'    => [WebhookController::class, 'add'],
		'webhook_delete' => [WebhookController::class, 'delete'],
		'webhook_toggle' => [WebhookController::class, 'toggle'],
		'webhook_test'   => [WebhookController::class, 'test'],

		/* StaticController */
		'generate_static_diff' => [StaticController::class, 'buildDiff'],
		'generate_static_full' => [StaticController::class, 'buildAll'],
		'clean_static'         => [StaticController::class, 'clean'],
		'build_zip'            => [StaticController::class, 'buildZip'],
		'static_status'        => [StaticController::class, 'status'],
		'deploy_diff'          => [StaticController::class, 'deployDiff'],

		/* UpdateController */
		'check'          => [UpdateController::class, 'check'],
		'check_env'      => [UpdateController::class, 'checkEnv'],
		'apply'          => [UpdateController::class, 'apply'],
		'list_backups'   => [UpdateController::class, 'listBackups'],
		'rollback'       => [UpdateController::class, 'rollback'],
		'delete_backup'  => [UpdateController::class, 'deleteBackup'],

		/* DiagnosticController */
		'diag_set_enabled' => [DiagnosticController::class, 'setEnabled'],
		'diag_set_level'   => [DiagnosticController::class, 'setLevel'],
		'diag_preview'     => [DiagnosticController::class, 'preview'],
		'diag_send_now'    => [DiagnosticController::class, 'sendNow'],
		'diag_clear_logs'  => [DiagnosticController::class, 'clearLogs'],
		'diag_get_logs'    => [DiagnosticController::class, 'getLogs'],
		'diag_get_summary' => [DiagnosticController::class, 'getSummary'],
		'diag_health'      => [DiagnosticController::class, 'health'],
	];

	/** POST /dispatch — ap_action に基づいてコントローラにルーティング */
	public function handle(Request $request): Response {
		$action = $request->post('ap_action', '');
		if ($action === '') {
			return $this->error('Missing ap_action parameter');
		}

		if (!isset(self::ACTION_MAP[$action])) {
			return $this->error("Unknown action: {$action}", 404);
		}

		[$controllerClass, $method] = self::ACTION_MAP[$action];
		$controller = new $controllerClass();
		return $controller->$method($request);
	}

	/** 登録済みアクション一覧を返す（デバッグ用） */
	public static function registeredActions(): array {
		return array_keys(self::ACTION_MAP);
	}
}
