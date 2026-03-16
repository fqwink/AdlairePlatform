/**
 * Adlaire Platform (AP) — Interface Definitions
 *
 * コントローラー群のインターフェースを定義する。
 * 各コントローラーは APF.Request を受け取り APF.Response を返す。
 *
 * @package AP
 * @version 2.0.0
 * @license Adlaire License Ver.2.0
 */

import type {
  RequestContext,
  ResponseData,
  ApiResponse,
  ActionDefinition,
} from "../types.ts";

// ============================================================================
// Base Controller
// ============================================================================

/**
 * コントローラーのハンドラ型
 *
 * APF の RequestInterface/ResponseInterface に依存しないよう、
 * types.ts のプリミティブ型で表現。
 * 実装時に APF.Interface の型でラップする。
 */
export type ControllerAction = (request: RequestContext) => Promise<ResponseData>;

export interface BaseControllerInterface {
  /** アクションメソッドの存在確認 */
  hasAction(name: string): boolean;
  /** アクション名からハンドラを取得 */
  getAction(name: string): ControllerAction | null;
}

// ============================================================================
// Auth Controller
// ============================================================================

export interface AuthControllerInterface extends BaseControllerInterface {
  showLogin(request: RequestContext): Promise<ResponseData>;
  authenticate(request: RequestContext): Promise<ResponseData>;
  logout(request: RequestContext): Promise<ResponseData>;
}

// ============================================================================
// Dashboard Controller
// ============================================================================

export interface DashboardControllerInterface extends BaseControllerInterface {
  index(request: RequestContext): Promise<ResponseData>;
}

// ============================================================================
// API Controller
// ============================================================================

export interface ApiControllerInterface extends BaseControllerInterface {
  dispatch(request: RequestContext): Promise<ResponseData>;
}

// ============================================================================
// Admin Controller
// ============================================================================

export interface AdminControllerInterface extends BaseControllerInterface {
  editField(request: RequestContext): Promise<ResponseData>;
  uploadImage(request: RequestContext): Promise<ResponseData>;
  deletePage(request: RequestContext): Promise<ResponseData>;
  listRevisions(request: RequestContext): Promise<ResponseData>;
  getRevision(request: RequestContext): Promise<ResponseData>;
  restoreRevision(request: RequestContext): Promise<ResponseData>;
  pinRevision(request: RequestContext): Promise<ResponseData>;
  searchRevisions(request: RequestContext): Promise<ResponseData>;
  userAdd(request: RequestContext): Promise<ResponseData>;
  userDelete(request: RequestContext): Promise<ResponseData>;
  redirectAdd(request: RequestContext): Promise<ResponseData>;
  redirectDelete(request: RequestContext): Promise<ResponseData>;
}

// ============================================================================
// Collection Controller
// ============================================================================

export interface CollectionControllerInterface extends BaseControllerInterface {
  create(request: RequestContext): Promise<ResponseData>;
  delete(request: RequestContext): Promise<ResponseData>;
  itemSave(request: RequestContext): Promise<ResponseData>;
  itemDelete(request: RequestContext): Promise<ResponseData>;
  migrate(request: RequestContext): Promise<ResponseData>;
}

// ============================================================================
// Git Controller
// ============================================================================

export interface GitControllerInterface extends BaseControllerInterface {
  configure(request: RequestContext): Promise<ResponseData>;
  test(request: RequestContext): Promise<ResponseData>;
  pull(request: RequestContext): Promise<ResponseData>;
  push(request: RequestContext): Promise<ResponseData>;
  log(request: RequestContext): Promise<ResponseData>;
  status(request: RequestContext): Promise<ResponseData>;
  previewBranch(request: RequestContext): Promise<ResponseData>;
}

// ============================================================================
// Webhook Controller
// ============================================================================

export interface WebhookControllerInterface extends BaseControllerInterface {
  add(request: RequestContext): Promise<ResponseData>;
  delete(request: RequestContext): Promise<ResponseData>;
  toggle(request: RequestContext): Promise<ResponseData>;
  test(request: RequestContext): Promise<ResponseData>;
}

// ============================================================================
// Static Controller
// ============================================================================

export interface StaticControllerInterface extends BaseControllerInterface {
  buildDiff(request: RequestContext): Promise<ResponseData>;
  buildAll(request: RequestContext): Promise<ResponseData>;
  clean(request: RequestContext): Promise<ResponseData>;
  buildZip(request: RequestContext): Promise<ResponseData>;
  status(request: RequestContext): Promise<ResponseData>;
  deployDiff(request: RequestContext): Promise<ResponseData>;
}

// ============================================================================
// Update Controller
// ============================================================================

export interface UpdateControllerInterface extends BaseControllerInterface {
  check(request: RequestContext): Promise<ResponseData>;
  checkEnv(request: RequestContext): Promise<ResponseData>;
  apply(request: RequestContext): Promise<ResponseData>;
  listBackups(request: RequestContext): Promise<ResponseData>;
  rollback(request: RequestContext): Promise<ResponseData>;
  deleteBackup(request: RequestContext): Promise<ResponseData>;
}

// ============================================================================
// Diagnostic Controller
// ============================================================================

export interface DiagnosticControllerInterface extends BaseControllerInterface {
  setEnabled(request: RequestContext): Promise<ResponseData>;
  setLevel(request: RequestContext): Promise<ResponseData>;
  preview(request: RequestContext): Promise<ResponseData>;
  sendNow(request: RequestContext): Promise<ResponseData>;
  clearLogs(request: RequestContext): Promise<ResponseData>;
  getLogs(request: RequestContext): Promise<ResponseData>;
  getSummary(request: RequestContext): Promise<ResponseData>;
  health(request: RequestContext): Promise<ResponseData>;
}

// ============================================================================
// Action Dispatcher
// ============================================================================

export interface ActionDispatcherInterface {
  handle(request: RequestContext): Promise<ResponseData>;
  registeredActions(): ActionDefinition[];
}
