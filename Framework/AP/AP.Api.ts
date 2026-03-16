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
import type { AdlaireClient, ResponseData, RequestContext } from "../types.ts";
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
 * ResponseData → ResponseInterface 変換ヘルパー
 */
function wrapController(
  fn: (ctx: RequestContext) => Promise<ResponseData>,
): (req: RequestInterface) => Promise<ResponseInterface> {
  return async (req: RequestInterface): Promise<ResponseInterface> => {
    const data = await fn(req.toContext());
    return new Response(data.body, data.statusCode, data.headers);
  };
}

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
  router.get("/login", wrapController((ctx) => auth.showLogin(ctx)));
  router.post("/login", wrapController((ctx) => auth.authenticate(ctx)));
  router.post("/logout", wrapController((ctx) => auth.logout(ctx)));

  // ── 認証必須エリア ──
  const mwOptions = authMiddleware ? { middleware: [authMiddleware] } : {};

  router.group({ prefix: "/admin", ...mwOptions }, (r) => {
    r.get("/", wrapController((ctx) => dashboard.index(ctx)));

    // 統合 POST アクションディスパッチャ
    r.post("/api", wrapController((ctx) => api.dispatch(ctx)));

    // Git
    r.get("/git/status", wrapController((ctx) => git.status(ctx)));
    r.get("/git/log", wrapController((ctx) => git.log(ctx)));

    // Static
    r.get("/static/status", wrapController((ctx) => staticCtrl.status(ctx)));

    // Update
    r.get("/update/check", wrapController((ctx) => update.check(ctx)));
    r.get("/update/env", wrapController((ctx) => update.checkEnv(ctx)));
    r.get("/update/backups", wrapController((ctx) => update.listBackups(ctx)));

    // Diagnostic
    r.get("/diagnostic/health", wrapController((ctx) => diagnostic.health(ctx)));
    r.get("/diagnostic/summary", wrapController((ctx) => diagnostic.getSummary(ctx)));
    r.get("/diagnostic/logs", wrapController((ctx) => diagnostic.getLogs(ctx)));
  });
}
