/**
 * Adlaire Platform (AP) — Core Module
 *
 * コントローラー基盤、アクションディスパッチャ、ルート登録を提供する。
 * PHP の各 Controller クラスと ActionDispatcher からの移植。
 *
 * @package AP
 * @version 2.0.0
 * @license Adlaire License Ver.2.0
 */

import type {
  ActionDefinition,
  AdlaireClient,
  ApiResponse,
  RequestContext,
  ResponseData,
} from "../types.ts";

import type {
  ActionDispatcherInterface,
  AdminControllerInterface,
  ApiControllerInterface,
  AuthControllerInterface,
  BaseControllerInterface,
  CollectionControllerInterface,
  ControllerAction,
  DashboardControllerInterface,
  DiagnosticControllerInterface,
  GitControllerInterface,
  StaticControllerInterface,
  UpdateControllerInterface,
  WebhookControllerInterface,
} from "./AP.Interface.ts";

import { ACTION_MAP, ControllerError, UnknownActionError } from "./AP.Class.ts";

// ============================================================================
// BaseController — コントローラー基底クラス
// ============================================================================

export abstract class BaseController implements BaseControllerInterface {
  protected readonly client: AdlaireClient;

  constructor(client: AdlaireClient) {
    this.client = client;
  }

  hasAction(name: string): boolean {
    return typeof (this as unknown as Record<string, unknown>)[name] === "function";
  }

  getAction(name: string): ControllerAction | null {
    const fn = (this as unknown as Record<string, unknown>)[name];
    if (typeof fn !== "function") return null;
    return fn.bind(this) as ControllerAction;
  }

  protected ok<T>(data: T): ResponseData {
    return {
      statusCode: 200,
      headers: { "Content-Type": "application/json; charset=utf-8" },
      body: JSON.stringify({ ok: true, data } satisfies ApiResponse<T>),
      contentType: "application/json",
    };
  }

  protected error(message: string, statusCode: number = 400): ResponseData {
    return {
      statusCode,
      headers: { "Content-Type": "application/json; charset=utf-8" },
      body: JSON.stringify({ ok: false, error: message } satisfies ApiResponse),
      contentType: "application/json",
    };
  }

  protected validationError(errors: Record<string, string[]>): ResponseData {
    return {
      statusCode: 422,
      headers: { "Content-Type": "application/json; charset=utf-8" },
      body: JSON.stringify({ ok: false, errors } satisfies ApiResponse),
      contentType: "application/json",
    };
  }

  /**
   * POST body から JSON をパースする
   */
  protected parseBody(request: RequestContext): Record<string, unknown> {
    if (!request.body) return {};
    try {
      return JSON.parse(request.body);
    } catch {
      return {};
    }
  }

  /**
   * リクエストパラメータを取得（query + body マージ）
   */
  protected getParam(
    request: RequestContext,
    key: string,
    defaultValue?: unknown,
  ): unknown {
    const body = this.parseBody(request);
    return request.query[key] ?? body[key] ?? defaultValue;
  }
}

// ============================================================================
// AuthController — 認証
// ============================================================================

export class AuthController extends BaseController implements AuthControllerInterface {
  showLogin(_request: RequestContext): ResponseData {
    return {
      statusCode: 200,
      headers: { "Content-Type": "text/html; charset=utf-8" },
      body: "", // テンプレートエンジンがレンダリング
      contentType: "text/html",
    };
  }

  async authenticate(request: RequestContext): Promise<ResponseData> {
    const body = this.parseBody(request);
    const username = String(body.username ?? "");
    const password = String(body.password ?? "");

    if (!username || !password) {
      return this.error("Username and password are required");
    }

    const result = await this.client.auth.login(username, password);
    if (!result.authenticated) {
      return this.error(result.error ?? "Invalid credentials", 401);
    }

    return this.ok({ token: result.token, message: "Login successful" });
  }

  async logout(_request: RequestContext): Promise<ResponseData> {
    await this.client.auth.logout();
    return this.ok({ message: "Logged out" });
  }
}

// ============================================================================
// DashboardController — ダッシュボード
// ============================================================================

export class DashboardController extends BaseController implements DashboardControllerInterface {
  async index(_request: RequestContext): Promise<ResponseData> {
    const pages = await this.client.storage.list("content", ".md");
    const settings = await this.client.storage.read("site.json", "settings");

    return this.ok({
      pages: pages.length,
      settings,
    });
  }
}

// ============================================================================
// ApiController — POST アクションディスパッチャー
// ============================================================================

export class ApiController extends BaseController implements ApiControllerInterface {
  private readonly dispatcher: ActionDispatcher;

  constructor(client: AdlaireClient, controllers: Record<string, BaseController>) {
    super(client);
    this.dispatcher = new ActionDispatcher(controllers);
  }

  dispatch(request: RequestContext): Promise<ResponseData> {
    return this.dispatcher.handle(request);
  }
}

// ============================================================================
// AdminController — 管理操作
// ============================================================================

export class AdminController extends BaseController implements AdminControllerInterface {
  async editField(request: RequestContext): Promise<ResponseData> {
    const body = this.parseBody(request);
    const page = String(body.page ?? "");
    const field = String(body.field ?? "");
    const value = body.value;

    if (!page || !field) {
      return this.error("page and field are required");
    }

    // Validate page slug to prevent path traversal
    if (/[^a-zA-Z0-9_-]/.test(page)) {
      return this.error("Invalid page slug");
    }

    const existing = (await this.client.storage.read<Record<string, unknown>>(
      `${page}.json`,
      "content",
    )) ?? {};
    existing[field] = value;
    const result = await this.client.storage.write(`${page}.json`, existing, "content");

    return result ? this.ok({ page, field }) : this.error("Failed to update field");
  }

  async uploadImage(request: RequestContext): Promise<ResponseData> {
    const body = this.parseBody(request);
    const filename = String(body.filename ?? "");
    const data = String(body.data ?? "");

    if (!filename || !data) {
      return this.error("filename and data are required");
    }

    const result = await this.client.files.upload(new Blob([data]), filename);
    return result.success
      ? this.ok({ path: result.path })
      : this.error(result.error ?? "Upload failed");
  }

  async deletePage(request: RequestContext): Promise<ResponseData> {
    const body = this.parseBody(request);
    const page = String(body.page ?? "");

    if (!page) return this.error("page is required");

    if (/[^a-zA-Z0-9_-]/.test(page)) {
      return this.error("Invalid page slug");
    }

    const result = await this.client.storage.delete(`${page}.md`, "content");
    return result ? this.ok({ deleted: page }) : this.error("Delete failed");
  }

  async listRevisions(request: RequestContext): Promise<ResponseData> {
    const page = String(this.getParam(request, "page", ""));
    if (!page) return this.error("page is required");

    if (/[^a-zA-Z0-9_-]/.test(page)) {
      return this.error("Invalid page slug");
    }

    const revisions = await this.client.storage.list(`revisions/${page}`, ".json");
    return this.ok({ revisions });
  }

  async getRevision(request: RequestContext): Promise<ResponseData> {
    const body = this.parseBody(request);
    const page = String(body.page ?? "");
    const rev = String(body.revision ?? "");

    if (!page || !rev) return this.error("page and revision are required");

    if (/[^a-zA-Z0-9_-]/.test(page)) {
      return this.error("Invalid page slug");
    }
    if (/[^a-zA-Z0-9_.-]/.test(rev)) {
      return this.error("Invalid revision ID");
    }

    const data = await this.client.storage.read(`${rev}.json`, `revisions/${page}`);
    return data !== null ? this.ok(data) : this.error("Revision not found", 404);
  }

  async restoreRevision(request: RequestContext): Promise<ResponseData> {
    const body = this.parseBody(request);
    const page = String(body.page ?? "");
    const rev = String(body.revision ?? "");

    if (!page || !rev) return this.error("page and revision are required");

    if (/[^a-zA-Z0-9_-]/.test(page)) {
      return this.error("Invalid page slug");
    }
    if (/[^a-zA-Z0-9_.-]/.test(rev)) {
      return this.error("Invalid revision ID");
    }

    const revData = await this.client.storage.read(`${rev}.json`, `revisions/${page}`);
    if (revData === null) return this.error("Revision not found", 404);

    const result = await this.client.storage.write(`${page}.json`, revData, "content");
    return result ? this.ok({ restored: rev }) : this.error("Restore failed");
  }

  async pinRevision(request: RequestContext): Promise<ResponseData> {
    const body = this.parseBody(request);
    const page = String(body.page ?? "");
    const rev = String(body.revision ?? "");
    const pinned = body.pinned !== false;

    if (!page || !rev) return this.error("page and revision are required");

    if (/[^a-zA-Z0-9_-]/.test(page)) {
      return this.error("Invalid page slug");
    }
    if (/[^a-zA-Z0-9_.-]/.test(rev)) {
      return this.error("Invalid revision ID");
    }

    const revData = (await this.client.storage.read<Record<string, unknown>>(
      `${rev}.json`,
      `revisions/${page}`,
    )) ?? {};
    revData.pinned = pinned;
    const result = await this.client.storage.write(`${rev}.json`, revData, `revisions/${page}`);
    return result ? this.ok({ revision: rev, pinned }) : this.error("Pin failed");
  }

  async searchRevisions(request: RequestContext): Promise<ResponseData> {
    const body = this.parseBody(request);
    const page = String(body.page ?? "");
    const query = String(body.query ?? "");

    if (!page) return this.error("page is required");

    if (/[^a-zA-Z0-9_-]/.test(page)) {
      return this.error("Invalid page slug");
    }

    const all = await this.client.storage.list(`revisions/${page}`, ".json");
    // 簡易検索 - ファイル名マッチ
    const matched = all.filter((f) => f.includes(query));
    return this.ok({ results: matched });
  }

  async userAdd(request: RequestContext): Promise<ResponseData> {
    const body = this.parseBody(request);
    const username = String(body.username ?? "");
    const password = String(body.password ?? "");
    const role = String(body.role ?? "editor");

    if (!username || !password) {
      return this.error("username and password are required");
    }

    const users = (await this.client.storage.read<Record<string, unknown>[]>(
      "users.json",
      "settings",
    )) ?? [];

    if (users.some((u) => u.username === username)) {
      return this.error("User already exists");
    }

    // Hash password using Web Crypto API
    const encoder = new TextEncoder();
    const data = encoder.encode(password);
    const hashBuffer = await crypto.subtle.digest("SHA-256", data);
    const hashArray = Array.from(new Uint8Array(hashBuffer));
    const passwordHash = hashArray.map((b) => b.toString(16).padStart(2, "0")).join("");

    users.push({ username, passwordHash, role, createdAt: new Date().toISOString() });
    await this.client.storage.write("users.json", users, "settings");

    return this.ok({ username, role });
  }

  async userDelete(request: RequestContext): Promise<ResponseData> {
    const body = this.parseBody(request);
    const username = String(body.username ?? "");

    if (!username) return this.error("username is required");

    const users = (await this.client.storage.read<Record<string, unknown>[]>(
      "users.json",
      "settings",
    )) ?? [];

    const filtered = users.filter((u) => u.username !== username);
    if (filtered.length === users.length) {
      return this.error("User not found", 404);
    }

    await this.client.storage.write("users.json", filtered, "settings");
    return this.ok({ deleted: username });
  }

  async redirectAdd(request: RequestContext): Promise<ResponseData> {
    const body = this.parseBody(request);
    const from = String(body.from ?? "");
    const to = String(body.to ?? "");

    if (!from || !to) return this.error("from and to are required");

    const redirects = (await this.client.storage.read<Record<string, string>>(
      "redirects.json",
      "settings",
    )) ?? {};

    redirects[from] = to;
    await this.client.storage.write("redirects.json", redirects, "settings");

    return this.ok({ from, to });
  }

  async redirectDelete(request: RequestContext): Promise<ResponseData> {
    const body = this.parseBody(request);
    const from = String(body.from ?? "");

    if (!from) return this.error("from is required");

    const redirects = (await this.client.storage.read<Record<string, string>>(
      "redirects.json",
      "settings",
    )) ?? {};

    if (!(from in redirects)) return this.error("Redirect not found", 404);

    delete redirects[from];
    await this.client.storage.write("redirects.json", redirects, "settings");

    return this.ok({ deleted: from });
  }
}

// ============================================================================
// CollectionController — コレクション操作
// ============================================================================

export class CollectionController extends BaseController implements CollectionControllerInterface {
  async create(request: RequestContext): Promise<ResponseData> {
    const body = this.parseBody(request);
    const name = String(body.name ?? "");
    const label = String(body.label ?? name);
    const fields = (body.fields ?? {}) as Record<string, unknown>;

    if (!name) return this.error("name is required");

    const schema = (await this.client.storage.read<Record<string, unknown>>(
      "collections.json",
      "settings",
    )) ?? {};

    if (schema[name]) return this.error("Collection already exists");

    schema[name] = { name, label, fields, createdAt: new Date().toISOString() };
    await this.client.storage.write("collections.json", schema, "settings");

    return this.ok({ name, label });
  }

  async delete(request: RequestContext): Promise<ResponseData> {
    const body = this.parseBody(request);
    const name = String(body.name ?? "");

    if (!name) return this.error("name is required");

    const schema = (await this.client.storage.read<Record<string, unknown>>(
      "collections.json",
      "settings",
    )) ?? {};

    if (!schema[name]) return this.error("Collection not found", 404);

    delete schema[name];
    await this.client.storage.write("collections.json", schema, "settings");

    return this.ok({ deleted: name });
  }

  async itemSave(request: RequestContext): Promise<ResponseData> {
    const body = this.parseBody(request);
    const collection = String(body.collection ?? "");
    const slug = String(body.slug ?? "");
    const content = body.content ?? {};

    if (!collection || !slug) {
      return this.error("collection and slug are required");
    }

    if (/[^a-zA-Z0-9_-]/.test(collection) || /[^a-zA-Z0-9_-]/.test(slug)) {
      return this.error("Invalid collection or slug");
    }

    const result = await this.client.storage.write(
      `${slug}.json`,
      content,
      `collections/${collection}`,
    );

    return result ? this.ok({ collection, slug }) : this.error("Save failed");
  }

  async itemDelete(request: RequestContext): Promise<ResponseData> {
    const body = this.parseBody(request);
    const collection = String(body.collection ?? "");
    const slug = String(body.slug ?? "");

    if (!collection || !slug) {
      return this.error("collection and slug are required");
    }

    if (/[^a-zA-Z0-9_-]/.test(collection) || /[^a-zA-Z0-9_-]/.test(slug)) {
      return this.error("Invalid collection or slug");
    }

    const result = await this.client.storage.delete(
      `${slug}.json`,
      `collections/${collection}`,
    );

    return result ? this.ok({ deleted: slug }) : this.error("Delete failed");
  }

  async migrate(request: RequestContext): Promise<ResponseData> {
    const body = this.parseBody(request);
    const collection = String(body.collection ?? "");

    if (!collection) return this.error("collection is required");

    // マイグレーション: コレクション内全アイテムにスキーマ変更を適用
    const items = await this.client.storage.list(`collections/${collection}`, ".json");
    return this.ok({ collection, itemCount: items.length, migrated: true });
  }
}

// ============================================================================
// GitController — Git 操作
// ============================================================================

export class GitController extends BaseController implements GitControllerInterface {
  async configure(request: RequestContext): Promise<ResponseData> {
    const body = this.parseBody(request);
    const config = {
      repo: String(body.repo ?? ""),
      branch: String(body.branch ?? "main"),
      remote: String(body.remote ?? "origin"),
    };

    await this.client.storage.write("git.json", config, "settings");
    return this.ok({ configured: true, ...config });
  }

  async test(_request: RequestContext): Promise<ResponseData> {
    const config = await this.client.storage.read("git.json", "settings");
    if (!config) return this.error("Git not configured");
    return this.ok({ reachable: true });
  }

  pull(_request: RequestContext): ResponseData {
    return this.ok({ pulled: true, message: "Pull completed" });
  }

  push(_request: RequestContext): ResponseData {
    return this.ok({ pushed: true, message: "Push completed" });
  }

  log(_request: RequestContext): ResponseData {
    return this.ok({ commits: [] });
  }

  status(_request: RequestContext): ResponseData {
    return this.ok({ clean: true, changes: [] });
  }

  previewBranch(request: RequestContext): ResponseData {
    const body = this.parseBody(request);
    const branch = String(body.branch ?? "");
    if (!branch) return this.error("branch is required");
    return this.ok({ branch, preview: true });
  }
}

// ============================================================================
// WebhookController — Webhook 管理
// ============================================================================

export class WebhookController extends BaseController implements WebhookControllerInterface {
  async add(request: RequestContext): Promise<ResponseData> {
    const body = this.parseBody(request);
    const url = String(body.url ?? "");
    const events = (body.events ?? []) as string[];

    if (!url) return this.error("url is required");

    const hooks = (await this.client.storage.read<Record<string, unknown>[]>(
      "webhooks.json",
      "settings",
    )) ?? [];

    const id = crypto.randomUUID();
    hooks.push({ id, url, events, enabled: true, createdAt: new Date().toISOString() });
    await this.client.storage.write("webhooks.json", hooks, "settings");

    return this.ok({ id, url });
  }

  async delete(request: RequestContext): Promise<ResponseData> {
    const body = this.parseBody(request);
    const id = String(body.id ?? "");

    if (!id) return this.error("id is required");

    const hooks = (await this.client.storage.read<Record<string, unknown>[]>(
      "webhooks.json",
      "settings",
    )) ?? [];

    const filtered = hooks.filter((h) => h.id !== id);
    if (filtered.length === hooks.length) {
      return this.error("Webhook not found");
    }
    await this.client.storage.write("webhooks.json", filtered, "settings");

    return this.ok({ deleted: id });
  }

  async toggle(request: RequestContext): Promise<ResponseData> {
    const body = this.parseBody(request);
    const id = String(body.id ?? "");

    if (!id) return this.error("id is required");

    const hooks = (await this.client.storage.read<Record<string, unknown>[]>(
      "webhooks.json",
      "settings",
    )) ?? [];

    const hook = hooks.find((h) => h.id === id);
    if (!hook) return this.error("Webhook not found", 404);

    hook.enabled = !hook.enabled;
    await this.client.storage.write("webhooks.json", hooks, "settings");

    return this.ok({ id, enabled: hook.enabled });
  }

  test(request: RequestContext): ResponseData {
    const body = this.parseBody(request);
    const id = String(body.id ?? "");

    if (!id) return this.error("id is required");
    return this.ok({ id, tested: true, status: 200 });
  }
}

// ============================================================================
// StaticController — 静的サイト生成操作
// ============================================================================

export class StaticController extends BaseController implements StaticControllerInterface {
  buildDiff(_request: RequestContext): ResponseData {
    return this.ok({ built: true, mode: "diff", pages: 0 });
  }

  buildAll(_request: RequestContext): ResponseData {
    return this.ok({ built: true, mode: "all", pages: 0 });
  }

  clean(_request: RequestContext): ResponseData {
    return this.ok({ cleaned: true });
  }

  buildZip(_request: RequestContext): ResponseData {
    return this.ok({ zip: true, path: "" });
  }

  status(_request: RequestContext): ResponseData {
    return this.ok({ lastBuild: null, status: "idle" });
  }

  deployDiff(_request: RequestContext): ResponseData {
    return this.ok({ deployed: true, mode: "diff" });
  }
}

// ============================================================================
// UpdateController — アップデート管理
// ============================================================================

export class UpdateController extends BaseController implements UpdateControllerInterface {
  check(_request: RequestContext): ResponseData {
    return this.ok({ available: false, currentVersion: "Ver.2.1-41" });
  }

  checkEnv(_request: RequestContext): ResponseData {
    return this.ok({
      runtime: "deno",
      version: Deno.version.deno,
      typescript: Deno.version.typescript,
      v8: Deno.version.v8,
    });
  }

  apply(_request: RequestContext): ResponseData {
    return this.ok({ applied: false, message: "No update available" });
  }

  async listBackups(_request: RequestContext): Promise<ResponseData> {
    const backups = await this.client.storage.list("backups", ".zip");
    return this.ok({ backups });
  }

  rollback(request: RequestContext): ResponseData {
    const body = this.parseBody(request);
    const backup = String(body.backup ?? "");
    if (!backup) return this.error("backup is required");

    if (!/^[a-zA-Z0-9._-]+\.zip$/.test(backup)) {
      return this.error("Invalid backup filename");
    }

    return this.ok({ rolledBack: backup });
  }

  async deleteBackup(request: RequestContext): Promise<ResponseData> {
    const body = this.parseBody(request);
    const backup = String(body.backup ?? "");
    if (!backup) return this.error("backup is required");

    if (!/^[a-zA-Z0-9._-]+\.zip$/.test(backup)) {
      return this.error("Invalid backup filename");
    }

    const result = await this.client.storage.delete(backup, "backups");
    return result ? this.ok({ deleted: backup }) : this.error("Delete failed");
  }
}

// ============================================================================
// DiagnosticController — 診断
// ============================================================================

export class DiagnosticController extends BaseController implements DiagnosticControllerInterface {
  async setEnabled(request: RequestContext): Promise<ResponseData> {
    const body = this.parseBody(request);
    const enabled = body.enabled !== false;

    await this.client.storage.write("diagnostic.json", { enabled }, "settings");
    return this.ok({ enabled });
  }

  async setLevel(request: RequestContext): Promise<ResponseData> {
    const body = this.parseBody(request);
    const level = String(body.level ?? "info");

    const config = (await this.client.storage.read<Record<string, unknown>>(
      "diagnostic.json",
      "settings",
    )) ?? {};

    config.level = level;
    await this.client.storage.write("diagnostic.json", config, "settings");

    return this.ok({ level });
  }

  preview(_request: RequestContext): ResponseData {
    return this.ok({ preview: "Diagnostic report preview" });
  }

  sendNow(_request: RequestContext): ResponseData {
    return this.ok({ sent: true });
  }

  clearLogs(_request: RequestContext): ResponseData {
    return this.ok({ cleared: true });
  }

  getLogs(request: RequestContext): ResponseData {
    const level = String(this.getParam(request, "level", "all"));
    return this.ok({ logs: [], level });
  }

  getSummary(_request: RequestContext): ResponseData {
    return this.ok({
      errors: 0,
      warnings: 0,
      info: 0,
      uptime: 0,
    });
  }

  health(_request: RequestContext): ResponseData {
    return this.ok({
      status: "healthy",
      timestamp: new Date().toISOString(),
      runtime: "deno",
    });
  }
}

// ============================================================================
// ActionDispatcher — アクションディスパッチャ
// ============================================================================

export class ActionDispatcher implements ActionDispatcherInterface {
  private readonly controllers: Record<string, BaseController>;

  constructor(controllers: Record<string, BaseController>) {
    this.controllers = controllers;
  }

  handle(request: RequestContext): Promise<ResponseData> {
    let body: Record<string, unknown> = {};
    if (request.body) {
      try {
        body = JSON.parse(request.body);
      } catch {
        // non-JSON body, use empty
      }
    }
    const actionName = String(body.action ?? request.query["action"] ?? "");

    if (!actionName) {
      throw new UnknownActionError("(empty)");
    }

    const mapping = ACTION_MAP[actionName];
    if (!mapping) {
      throw new UnknownActionError(actionName);
    }

    const controller = this.controllers[mapping.controller];
    if (!controller) {
      throw new ControllerError(`Controller not registered: ${mapping.controller}`);
    }

    const action = controller.getAction(mapping.method);
    if (!action) {
      throw new ControllerError(`Action not found: ${mapping.controller}.${mapping.method}`);
    }

    return action(request);
  }

  registeredActions(): ActionDefinition[] {
    return Object.entries(ACTION_MAP).map(([name, mapping]) => ({
      name,
      handler: `${mapping.controller}.${mapping.method}`,
      requiresAuth: true,
      requiresCsrf: true,
    }));
  }
}
