/**
 * Adlaire Static Generator (ASG) — API Adapter Layer
 *
 * 静的サイト生成のビルド/デプロイ操作を REST として公開する。
 *
 * @package ASG
 * @version 2.0.0
 * @license Adlaire License Ver.2.0
 */

import type { RouterInterface, RequestInterface } from "../APF/APF.Interface.ts";
import type { GeneratorInterface } from "./ASG.Interface.ts";
import { Response } from "../APF/APF.Core.ts";

/**
 * ASG REST エンドポイントを Router に登録する
 */
export function registerGeneratorRoutes(
  router: RouterInterface,
  generator: GeneratorInterface,
): void {
  // ビルドステータス
  router.get("/api/build/status", () => {
    return Response.json({
      ok: true,
      data: { status: "idle" },
    });
  });

  // 差分ビルド
  router.post("/api/build/diff", async () => {
    const result = await generator.buildDiff();
    return Response.json({ ok: true, data: result });
  });

  // フルビルド
  router.post("/api/build/full", async () => {
    const result = await generator.buildAll();
    return Response.json({ ok: true, data: result });
  });

  // 単一ページビルド
  router.post("/api/build/page/{slug}", async (req: RequestInterface) => {
    const slug = String(req.param("slug"));
    const result = await generator.buildSingle(slug);
    return Response.json({ ok: true, data: result });
  });

  // クリーン
  router.post("/api/build/clean", async () => {
    await generator.clean();
    return Response.json({ ok: true, data: { cleaned: true } });
  });
}
