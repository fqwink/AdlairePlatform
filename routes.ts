/**
 * AdlairePlatform — ルート定義
 *
 * Router にルートとミドルウェアを登録する。
 * PHP routes.php からの移植。
 *
 * @since 2.0.0
 * @license Adlaire License Ver.2.0
 */

import type { AdlaireClient } from "./Framework/types.ts";
import type { ApplicationFacade } from "./bootstrap.ts";

import {
  Response,
  SecurityHeadersMiddleware,
  RequestLoggingMiddleware,
  AuthMiddleware,
  CsrfMiddleware,
  registerSystemRoutes,
  registerCollectionRoutes,
  registerInfraRoutes,
  registerGeneratorRoutes,
  registerPlatformRoutes,
} from "./Framework/mod.ts";

import {
  CollectionManager,
  ContentManager,
  MetaManager,
  Generator,
  HybridResolver,
  BuildCache,
} from "./Framework/mod.ts";

/**
 * 全ルートを登録する
 */
export function registerRoutes(app: ApplicationFacade): void {
  const router = app.router;
  const client = app.container.make<AdlaireClient>("client");

  // ══════════════════════════════════════════════════
  // グローバルミドルウェア
  // ══════════════════════════════════════════════════
  router.middleware(new SecurityHeadersMiddleware());
  router.middleware(new RequestLoggingMiddleware());

  // ══════════════════════════════════════════════════
  // クエリ/POST パラメータ → URI パスのマッピング（後方互換）
  //
  //   ?login         → /login
  //   ?admin         → /admin
  //   POST ap_action → /dispatch
  //   ?ap_api=X      → /api/X
  // ══════════════════════════════════════════════════
  router.mapQuery("login", "/login");
  router.mapQuery("admin", "/admin");
  router.mapQuery("ap_api", "/api/{endpoint}", "endpoint");
  router.mapPost("ap_action", "/dispatch");

  // ══════════════════════════════════════════════════
  // ヘルスチェック（認証不要）
  // ══════════════════════════════════════════════════
  router.get("/health", () =>
    Response.json({
      status: "ok",
      version: "2.0.0",
      runtime: `deno/${Deno.version.deno}`,
      time: new Date().toISOString(),
    }),
  );

  // ══════════════════════════════════════════════════
  // システムエンドポイント (APF)
  // ══════════════════════════════════════════════════
  registerSystemRoutes(router);

  // ══════════════════════════════════════════════════
  // コレクション REST API (ACE)
  // ══════════════════════════════════════════════════
  const metaManager = new MetaManager();
  const collectionManager = new CollectionManager(client);
  const contentManager = new ContentManager(client, metaManager);
  registerCollectionRoutes(router, collectionManager, contentManager);

  // ══════════════════════════════════════════════════
  // インフラ API (AIS)
  // ══════════════════════════════════════════════════
  registerInfraRoutes(router, app.context, app.i18n);

  // ══════════════════════════════════════════════════
  // 静的サイトビルド API (ASG)
  // ══════════════════════════════════════════════════
  const resolver = new HybridResolver(client);
  const buildCache = new BuildCache(client);
  const generator = new Generator(resolver, buildCache, client);
  registerGeneratorRoutes(router, generator);

  // ══════════════════════════════════════════════════
  // 認証ミドルウェアの設定
  // ══════════════════════════════════════════════════
  const authMiddleware = new AuthMiddleware(async (token: string) => {
    const result = await client.auth.verify(token);
    return result.authenticated;
  });

  // ══════════════════════════════════════════════════
  // プラットフォーム管理ルート (AP)
  //   /login, /logout, /admin/*, /dispatch
  // ══════════════════════════════════════════════════
  registerPlatformRoutes(router, client, authMiddleware);
}
