/**
 * Adlaire Platform (AP) — Data Models & Enums
 *
 * AP 固有のデータモデル・列挙型を定義する。
 *
 * @package AP
 * @version 2.0.0
 * @license Adlaire License Ver.2.0
 */

// ============================================================================
// Action Registry
// ============================================================================

/**
 * アクション名 → コントローラーマッピング
 *
 * PHP の ActionDispatcher::$actions に相当。
 */
export const ACTION_MAP: Record<string, { controller: string; method: string }> = {
  edit_field: { controller: "AdminController", method: "editField" },
  upload_image: { controller: "AdminController", method: "uploadImage" },
  delete_page: { controller: "AdminController", method: "deletePage" },
  list_revisions: { controller: "AdminController", method: "listRevisions" },
  get_revision: { controller: "AdminController", method: "getRevision" },
  restore_revision: { controller: "AdminController", method: "restoreRevision" },
  pin_revision: { controller: "AdminController", method: "pinRevision" },
  search_revisions: { controller: "AdminController", method: "searchRevisions" },
  user_add: { controller: "AdminController", method: "userAdd" },
  user_delete: { controller: "AdminController", method: "userDelete" },
  redirect_add: { controller: "AdminController", method: "redirectAdd" },
  redirect_delete: { controller: "AdminController", method: "redirectDelete" },
  collection_create: { controller: "CollectionController", method: "create" },
  collection_delete: { controller: "CollectionController", method: "delete" },
  collection_item_save: { controller: "CollectionController", method: "itemSave" },
  collection_item_delete: { controller: "CollectionController", method: "itemDelete" },
  collection_migrate: { controller: "CollectionController", method: "migrate" },
  git_configure: { controller: "GitController", method: "configure" },
  git_test: { controller: "GitController", method: "test" },
  git_pull: { controller: "GitController", method: "pull" },
  git_push: { controller: "GitController", method: "push" },
  git_preview_branch: { controller: "GitController", method: "previewBranch" },
  webhook_add: { controller: "WebhookController", method: "add" },
  webhook_delete: { controller: "WebhookController", method: "delete" },
  webhook_toggle: { controller: "WebhookController", method: "toggle" },
  webhook_test: { controller: "WebhookController", method: "test" },
  static_build_diff: { controller: "StaticController", method: "buildDiff" },
  static_build_all: { controller: "StaticController", method: "buildAll" },
  static_clean: { controller: "StaticController", method: "clean" },
  static_build_zip: { controller: "StaticController", method: "buildZip" },
  static_deploy_diff: { controller: "StaticController", method: "deployDiff" },
  update_check: { controller: "UpdateController", method: "check" },
  update_check_env: { controller: "UpdateController", method: "checkEnv" },
  update_apply: { controller: "UpdateController", method: "apply" },
  update_rollback: { controller: "UpdateController", method: "rollback" },
  update_delete_backup: { controller: "UpdateController", method: "deleteBackup" },
  diag_set_enabled: { controller: "DiagnosticController", method: "setEnabled" },
  diag_set_level: { controller: "DiagnosticController", method: "setLevel" },
  diag_preview: { controller: "DiagnosticController", method: "preview" },
  diag_send_now: { controller: "DiagnosticController", method: "sendNow" },
  diag_clear_logs: { controller: "DiagnosticController", method: "clearLogs" },
  diag_get_logs: { controller: "DiagnosticController", method: "getLogs" },
  diag_get_summary: { controller: "DiagnosticController", method: "getSummary" },
} as const;

// ============================================================================
// Errors
// ============================================================================

/**
 * コントローラーエラー
 */
export class ControllerError extends Error {
  constructor(
    message: string,
    public readonly statusCode: number = 500,
    public readonly errorCode: string = "CONTROLLER_ERROR",
  ) {
    super(message);
    this.name = "ControllerError";
  }
}

/**
 * アクション未登録エラー
 */
export class UnknownActionError extends ControllerError {
  constructor(public readonly actionName: string) {
    super(`Unknown action: ${actionName}`, 400, "UNKNOWN_ACTION");
    this.name = "UnknownActionError";
  }
}

/**
 * 権限不足エラー
 */
export class ForbiddenError extends ControllerError {
  constructor(message: string = "Forbidden") {
    super(message, 403, "FORBIDDEN");
    this.name = "ForbiddenError";
  }
}
