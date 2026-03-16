/**
 * Adlaire Platform (AP) — API Adapter Layer
 *
 * AP コントローラー群のルートを Router に一括登録する。
 * PHP の route 定義ファイルに相当。
 *
 * @package AP
 * @version 2.0.0
 * @license Adlaire License Ver.2.0
 */

import type { RouterInterface, MiddlewareInterface, RequestInterface, ResponseInterface } from "../APF/APF.Interface.ts";
import type { AdlaireClient } from "../types.ts";
import { Response } from "../APF/APF.Core.ts";
import {
  AuthController,
  DashboardController,
  ApiController,
  AdminController,
  CollectionController,
  GitController,
  WebhookController,
  StaticController,
  UpdateController,
  DiagnosticController,
  BaseController,
} from "./AP.Core.ts";

/**
 * 全コントローラーのルートを登録する
 */
export function registerPlatformRoutes(
  router: RouterInterface,
  client: AdlaireClient,
  authMiddleware?: MiddlewareInterface,
): void {
  const auth = new AuthController(client);
  const dashboard = new DashboardController(client);
  const admin = new AdminController(client);
  const collection = new CollectionController(client);
  const git = new GitController(client);
  const webhook = new WebhookController(client);
  const staticCtrl = new StaticController(client);
  const update = new UpdateController(client);
  const diagnostic = new DiagnosticController(client);

  const controllers: Record<string, BaseController> = {
    AdminController: admin,
    CollectionController: collection,
    GitController: git,
    WebhookController: webhook,
    StaticController: staticCtrl,
    UpdateController: update,
    DiagnosticController: diagnostic,
  };

  const api = new ApiController(client, controllers);

  // ── 認証（ミドルウェア不要） ──
  router.get("/login", (req) => auth.showLogin(req.toContext()));
  router.post("/login", (req) => auth.authenticate(req.toContext()));
  router.post("/logout", (req) => auth.logout(req.toContext()));

  // ── 認証必須エリア ──
  const mwOptions = authMiddleware ? { middleware: [authMiddleware] } : {};

  router.group({ prefix: "/admin", ...mwOptions }, (r) => {
    r.get("/", (req) => dashboard.index(req.toContext()));

    // 統合 POST アクションディスパッチャ
    r.post("/api", (req) => api.dispatch(req.toContext()));

    // Git
    r.get("/git/status", (req) => git.status(req.toContext()));
    r.get("/git/log", (req) => git.log(req.toContext()));

    // Static
    r.get("/static/status", (req) => staticCtrl.status(req.toContext()));

    // Update
    r.get("/update/check", (req) => update.check(req.toContext()));
    r.get("/update/env", (req) => update.checkEnv(req.toContext()));
    r.get("/update/backups", (req) => update.listBackups(req.toContext()));

    // Diagnostic
    r.get("/diagnostic/health", (req) => diagnostic.health(req.toContext()));
    r.get("/diagnostic/summary", (req) => diagnostic.getSummary(req.toContext()));
    r.get("/diagnostic/logs", (req) => diagnostic.getLogs(req.toContext()));
  });
}
