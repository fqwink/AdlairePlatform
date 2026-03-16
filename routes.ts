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
  TemplateRenderer,
  MarkdownService,
  Builder,
  FileSystem,
} from "./Framework/mod.ts";

import { Generator, HybridResolver, BuildCache, SiteRouter } from "./Framework/ASG/ASG.Core.ts";
import type { StaticFileSystemInterface } from "./Framework/ASG/ASG.Interface.ts";

/**
 * APF FileSystem を ASG StaticFileSystemInterface に適合させるアダプター
 */
class StaticFileSystem extends FileSystem implements StaticFileSystemInterface {
  async listFiles(dir: string, ext?: string): Promise<string[]> {
    const files: string[] = [];
    try {
      for await (const entry of Deno.readDir(dir)) {
        if (!entry.isFile) continue;
        if (ext && !entry.name.endsWith(ext)) continue;
        files.push(`${dir}/${entry.name}`);
      }
    } catch {
      // directory doesn't exist
    }
    return files;
  }

  async getHash(path: string): Promise<string> {
    try {
      const content = await Deno.readFile(path);
      const hashBuffer = await crypto.subtle.digest("SHA-256", content);
      return Array.from(new Uint8Array(hashBuffer))
        .map((b) => b.toString(16).padStart(2, "0"))
        .join("");
    } catch {
      return "";
    }
  }
}

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
  const basePath = app.context.get<string>("basePath", Deno.cwd());
  const fs = new StaticFileSystem();
  const templateRenderer = new TemplateRenderer();
  const markdownService = new MarkdownService();
  const builder = new Builder(templateRenderer);
  const buildCache = new BuildCache(`${basePath}/data/cache`, fs);
  const generator = new Generator(
    {
      outputDir: `${basePath}/static`,
      contentDir: `${basePath}/data/content`,
      themeDir: `${basePath}/themes`,
      baseUrl: app.context.get<string>("url", ""),
      cleanUrls: app.context.get<boolean>("cleanUrls", true),
    },
    fs,
    buildCache,
    builder,
    templateRenderer,
    markdownService,
  );
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
