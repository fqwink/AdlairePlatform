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

import type {
  MiddlewareInterface,
  RequestInterface,
  ResponseInterface,
  RouterInterface,
} from "../APF/APF.Interface.ts";
import type { AdlaireClient, RequestContext, ResponseData } from "../types.ts";
import { Response } from "../APF/APF.Core.ts";
import {
  AdminController,
  ApiController,
  AuthController,
  BaseController,
  CollectionController,
  DashboardController,
  DiagnosticController,
  GitController,
  StaticController,
  UpdateController,
  WebhookController,
} from "./AP.Core.ts";

/**
 * ResponseData → ResponseInterface 変換ヘルパー
 */
function wrapController(
  fn: (ctx: RequestContext) => ResponseData | Promise<ResponseData>,
): (req: RequestInterface) => Promise<ResponseInterface> {
  return async (req: RequestInterface): Promise<ResponseInterface> => {
    const result = fn(req.toContext());
    const data = result instanceof Promise ? await result : result;
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

  // ── POST アクションディスパッチ ──
  // Browser scripts POST to '/' or './' (relative to /admin/) with ap_action in body.
  const dispatchHandler = wrapController((ctx) => api.dispatch(ctx));

  // ── 認証必須エリア ──
  const mwOptions = authMiddleware ? { middleware: [authMiddleware] } : {};

  // POST to root '/' for scripts using absolute path (wysiwyg, editInplace, updater)
  const rootPost = router.post("/", dispatchHandler);
  if (authMiddleware) rootPost.middleware(authMiddleware);

  router.group({ prefix: "/admin", ...mwOptions }, (r) => {
    r.get("/", wrapController((ctx) => dashboard.index(ctx)));

    // POST to '/admin/' for scripts using './' relative path (ap-utils post/postAction)
    r.post("/", dispatchHandler);

    // 統合 POST アクションディスパッチャ (explicit API path)
    r.post("/api", dispatchHandler);

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
