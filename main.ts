/**
 * AdlairePlatform — Deno HTTP Server Entry Point
 *
 * PHP index.php からの移植。Deno.serve() で HTTP サーバを起動し、
 * Router ディスパッチ → ページレンダリングのフローを実行する。
 *
 * 起動: deno task start
 * 開発: deno task dev
 *
 * @since 2.0.0
 * @license Adlaire License Ver.2.0
 */

import { bootstrap, ApplicationFacade } from "./bootstrap.ts";
import { registerRoutes } from "./routes.ts";
import {
  Application,
  Request,
  Response,
  MarkdownService,
  TemplateRenderer,
  ThemeManager,
  Config,
} from "./Framework/mod.ts";

// ============================================================================
// Constants
// ============================================================================

const AP_VERSION = "2.0.0";
const DEFAULT_PORT = 8080;

// ============================================================================
// Server Initialization
// ============================================================================

async function main(): Promise<void> {
  const basePath = Deno.cwd();
  const port = Number(Deno.env.get("AP_PORT") ?? Deno.env.get("PORT") ?? DEFAULT_PORT);

  // ── ブートストラップ ──
  const app = await bootstrap({
    basePath,
    baseUrl: Deno.env.get("AP_BASE_URL") ?? "",
    token: Deno.env.get("AP_TOKEN") ?? undefined,
    locale: Deno.env.get("AP_LOCALE") ?? undefined,
  });

  // ── ルート登録 ──
  registerRoutes(app);

  // ── テーマ/テンプレート初期化 ──
  const themeDir = `${basePath}/themes`;
  const themeName = app.context.get<string>("themeSelect", "AP-Default");
  const markdown = new MarkdownService();
  const renderer = new TemplateRenderer();

  // ── HTTP アプリケーション ──
  const httpApp = new Application();

  // Application の Router に全ルートをコピー（内部 Router と統合）
  const router = app.router;

  console.log(`Adlaire Platform v${AP_VERSION}`);
  console.log(`Runtime: Deno ${Deno.version.deno}`);
  console.log(`Listening on http://localhost:${port}`);

  // ── サーバ起動 ──
  Deno.serve({ port }, async (denoReq: globalThis.Request): Promise<globalThis.Response> => {
    const url = new URL(denoReq.url);
    const method = denoReq.method.toUpperCase();

    // クエリパラメータマッピング
    const query: Record<string, string> = {};
    url.searchParams.forEach((v, k) => {
      query[k] = v;
    });
    const mappedPath = router.resolveQueryMapping(query, method);
    const path = mappedPath ?? url.pathname;

    // ルート解決
    const resolved = router.resolve(method as Parameters<typeof router.resolve>[0], path);

    if (resolved) {
      // ── Router ディスパッチ ──
      try {
        const request = Request.fromDenoRequest(denoReq);
        request.setParams(resolved.params);

        const { MiddlewarePipeline } = await import("./Framework/APF/APF.Core.ts");
        const response = await MiddlewarePipeline.run(
          request,
          resolved.middleware,
          resolved.handler as (req: import("./Framework/APF/APF.Interface.ts").RequestInterface) => import("./Framework/APF/APF.Interface.ts").ResponseInterface | Promise<import("./Framework/APF/APF.Interface.ts").ResponseInterface>,
        );
        return (response as InstanceType<typeof Response>).toDenoResponse();
      } catch (error: unknown) {
        const message = error instanceof Error ? error.message : "Internal Server Error";
        return Response.error(message, 500).toDenoResponse();
      }
    }

    // ── 静的ファイル配信 ──
    if (path.match(/\.(css|js|png|jpg|jpeg|gif|svg|ico|woff2?|ttf|eot)$/)) {
      try {
        const filePath = `${basePath}/themes/${themeName}${path}`;
        const file = await Deno.readFile(filePath);
        const ext = path.split(".").pop() ?? "";
        const contentTypes: Record<string, string> = {
          css: "text/css",
          js: "application/javascript",
          png: "image/png",
          jpg: "image/jpeg",
          jpeg: "image/jpeg",
          gif: "image/gif",
          svg: "image/svg+xml",
          ico: "image/x-icon",
          woff: "font/woff",
          woff2: "font/woff2",
          ttf: "font/ttf",
          eot: "application/vnd.ms-fontobject",
        };
        return new globalThis.Response(file, {
          headers: {
            "Content-Type": contentTypes[ext] ?? "application/octet-stream",
            "Cache-Control": "public, max-age=86400",
          },
        });
      } catch {
        return new globalThis.Response("Not Found", { status: 404 });
      }
    }

    // ── ページレンダリング（Router 未処理 = 通常ページ表示） ──
    try {
      const slug = (path === "/" || path === "") ? "home" : path.replace(/^\/|\/$/g, "");

      // ページ JSON 読み込み
      let pagesData: Record<string, string> = {};
      try {
        const raw = await Deno.readTextFile(`${basePath}/data/content/pages.json`);
        pagesData = JSON.parse(raw);
      } catch {
        // pages.json がなければ空
      }

      // Markdown コレクションをマージ
      try {
        for await (const entry of Deno.readDir(`${basePath}/data/content/collections`)) {
          if (!entry.isDirectory) continue;
          for await (const file of Deno.readDir(`${basePath}/data/content/collections/${entry.name}`)) {
            if (!file.name.endsWith(".md")) continue;
            const mdContent = await Deno.readTextFile(
              `${basePath}/data/content/collections/${entry.name}/${file.name}`,
            );
            const parsed = markdown.parseFrontmatter(mdContent);
            const itemSlug = file.name.replace(/\.md$/, "");
            if (!(itemSlug in pagesData)) {
              pagesData[itemSlug] = markdown.toHtml(parsed.body);
            }
          }
        }
      } catch {
        // コレクションディレクトリがなければスキップ
      }

      const content = pagesData[slug];
      if (content === undefined) {
        return Response.notFound("Page not found").toDenoResponse();
      }

      // テンプレートレンダリング
      let templateHtml: string;
      try {
        templateHtml = await Deno.readTextFile(`${themeDir}/${themeName}/index.html`);
      } catch {
        templateHtml = "<!DOCTYPE html><html><head><title>{{title}}</title></head><body>{{{content}}}</body></html>";
      }

      const ctx: Record<string, unknown> = {
        ...app.context.all(),
        content,
        page: slug,
        version: AP_VERSION,
        lang: app.i18n.htmlLang(),
      };

      const html = renderer.render(templateHtml, ctx);
      return Response.html(html).toDenoResponse();
    } catch (error: unknown) {
      const message = error instanceof Error ? error.message : "Render error";
      return Response.error(message, 500).toDenoResponse();
    }
  });
}

// ── Entry point ──
main();
