/**
 * Adlaire Platform (AP) — Interface Definitions
 *
 * コントローラー群のインターフェースを定義する。
 * 各コントローラーは AFE.Request を受け取り AFE.Response を返す。
 *
 * @package AP
 * @version 2.0.0
 * @license Adlaire License Ver.2.0
 */

import type { RequestContext, ResponseData } from "../ACS/ACS.d.ts";

/**
 * アクション定義 — POST アクションディスパッチャー用
 */
export interface ActionDefinition {
  readonly name: string;
  readonly handler: string;
  readonly requiresAuth: boolean;
  readonly requiresCsrf: boolean;
}

/** コントローラーメソッドの戻り値型（同期/非同期どちらも可） */
type ControllerResult = ResponseData | Promise<ResponseData>;

// ============================================================================
// Base Controller
// ============================================================================

/**
 * コントローラーのハンドラ型
 */
export type ControllerAction = (request: RequestContext) => ControllerResult;

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
  showLogin(request: RequestContext): ControllerResult;
  authenticate(request: RequestContext): ControllerResult;
  logout(request: RequestContext): ControllerResult;
}

// ============================================================================
// Dashboard Controller
// ============================================================================

export interface DashboardControllerInterface extends BaseControllerInterface {
  index(request: RequestContext): ControllerResult;
}

// ============================================================================
// API Controller
// ============================================================================

export interface ApiControllerInterface extends BaseControllerInterface {
  dispatch(request: RequestContext): ControllerResult;
}

// ============================================================================
// Admin Controller
// ============================================================================

export interface AdminControllerInterface extends BaseControllerInterface {
  editField(request: RequestContext): ControllerResult;
  uploadImage(request: RequestContext): ControllerResult;
  deletePage(request: RequestContext): ControllerResult;
  listRevisions(request: RequestContext): ControllerResult;
  getRevision(request: RequestContext): ControllerResult;
  restoreRevision(request: RequestContext): ControllerResult;
  pinRevision(request: RequestContext): ControllerResult;
  searchRevisions(request: RequestContext): ControllerResult;
  userAdd(request: RequestContext): ControllerResult;
  userDelete(request: RequestContext): ControllerResult;
  redirectAdd(request: RequestContext): ControllerResult;
  redirectDelete(request: RequestContext): ControllerResult;
}

// ============================================================================
// Collection Controller
// ============================================================================

export interface CollectionControllerInterface extends BaseControllerInterface {
  create(request: RequestContext): ControllerResult;
  delete(request: RequestContext): ControllerResult;
  itemSave(request: RequestContext): ControllerResult;
  itemDelete(request: RequestContext): ControllerResult;
  migrate(request: RequestContext): ControllerResult;
}

// ============================================================================
// Git Controller
// ============================================================================

export interface GitControllerInterface extends BaseControllerInterface {
  configure(request: RequestContext): ControllerResult;
  test(request: RequestContext): ControllerResult;
  pull(request: RequestContext): ControllerResult;
  push(request: RequestContext): ControllerResult;
  log(request: RequestContext): ControllerResult;
  status(request: RequestContext): ControllerResult;
  previewBranch(request: RequestContext): ControllerResult;
}

// ============================================================================
// Webhook Controller
// ============================================================================

export interface WebhookControllerInterface extends BaseControllerInterface {
  add(request: RequestContext): ControllerResult;
  delete(request: RequestContext): ControllerResult;
  toggle(request: RequestContext): ControllerResult;
  test(request: RequestContext): ControllerResult;
}

// ============================================================================
// Static Controller
// ============================================================================

export interface StaticControllerInterface extends BaseControllerInterface {
  buildDiff(request: RequestContext): ControllerResult;
  buildAll(request: RequestContext): ControllerResult;
  clean(request: RequestContext): ControllerResult;
  buildZip(request: RequestContext): ControllerResult;
  status(request: RequestContext): ControllerResult;
  deployDiff(request: RequestContext): ControllerResult;
}

// ============================================================================
// Update Controller
// ============================================================================

export interface UpdateControllerInterface extends BaseControllerInterface {
  check(request: RequestContext): ControllerResult;
  checkEnv(request: RequestContext): ControllerResult;
  apply(request: RequestContext): ControllerResult;
  listBackups(request: RequestContext): ControllerResult;
  rollback(request: RequestContext): ControllerResult;
  deleteBackup(request: RequestContext): ControllerResult;
}

// ============================================================================
// Diagnostic Controller
// ============================================================================

export interface DiagnosticControllerInterface extends BaseControllerInterface {
  setEnabled(request: RequestContext): ControllerResult;
  setLevel(request: RequestContext): ControllerResult;
  preview(request: RequestContext): ControllerResult;
  sendNow(request: RequestContext): ControllerResult;
  clearLogs(request: RequestContext): ControllerResult;
  getLogs(request: RequestContext): ControllerResult;
  getSummary(request: RequestContext): ControllerResult;
  health(request: RequestContext): ControllerResult;
}

// ============================================================================
// Action Dispatcher
// ============================================================================

export interface ActionDispatcherInterface {
  handle(request: RequestContext): ControllerResult;
  registeredActions(): ActionDefinition[];
}
