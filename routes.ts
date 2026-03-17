/**
 * AdlairePlatform — ルート定義
 *
 * Router にルートとミドルウェアを登録する。
 *
 * @since 2.0.0
 * @license Adlaire License Ver.2.0
 */

import type { AdlaireClient } from "./Framework/ACS/ACS.d.ts";
import type { ApplicationFacade } from "./bootstrap.ts";

import {
  AuthMiddleware,
  registerCollectionRoutes,
  registerGeneratorRoutes,
  registerInfraRoutes,
  registerPlatformRoutes,
  registerSystemRoutes,
  RequestLoggingMiddleware,
  Response,
  SecurityHeadersMiddleware,
} from "./Framework/mod.ts";

import {
  Builder,
  CollectionManager,
  ContentManager,
  FileSystem,
  MarkdownService,
  MetaManager,
  TemplateRenderer,
} from "./Framework/mod.ts";

import { BuildCache, Generator } from "./Framework/ASG/ASG.Core.ts";
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
  // クエリパラメータ → URI パスのマッピング（後方互換）
  //
  //   ?login    → /login
  //   ?admin    → /admin
  //   ?ap_api=X → /api/X
  //
  // POST ap_action dispatch は registerPlatformRoutes で処理
  // ══════════════════════════════════════════════════
  router.mapQuery("login", "/login");
  router.mapQuery("admin", "/admin");
  router.mapQuery("ap_api", "/api/{endpoint}", "endpoint");

  // ══════════════════════════════════════════════════
  // ヘルスチェック（認証不要）
  // ══════════════════════════════════════════════════
  router.get("/health", () =>
    Response.json({
      status: "ok",
      version: "Ver.2.3-44",
      runtime: `deno/${Deno.version.deno}`,
      time: new Date().toISOString(),
    }));

  // ══════════════════════════════════════════════════
  // システムエンドポイント (APF)
  // ══════════════════════════════════════════════════
  registerSystemRoutes(router);

  // ══════════════════════════════════════════════════
  // コレクション REST API (ACE)
  // FRAMEWORK_RULEBOOK §2.1 準拠: Response を DI で渡す
  // ══════════════════════════════════════════════════
  const metaManager = new MetaManager();
  const collectionManager = new CollectionManager(client);
  const contentManager = new ContentManager(client, metaManager);
  registerCollectionRoutes(router, collectionManager, contentManager, Response);

  // ══════════════════════════════════════════════════
  // インフラ API (AIS)
  // ══════════════════════════════════════════════════
  registerInfraRoutes(router, app.context, app.i18n, Response);

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
  registerGeneratorRoutes(router, generator, Response);

  // ══════════════════════════════════════════════════
  // 認証ミドルウェアの設定
  // ══════════════════════════════════════════════════
  const authMiddleware = new AuthMiddleware(async (token: string) => {
    const result = await client.auth.verify(token);
    return result.authenticated;
  }, Response);

  // ══════════════════════════════════════════════════
  // プラットフォーム管理ルート (AP)
  //   /login, /logout, /admin/*, /dispatch
  // ══════════════════════════════════════════════════
  registerPlatformRoutes(router, client, Response, authMiddleware);
}
