/**
 * Adlaire Static Generator (ASG) — API Adapter Layer
 *
 * 静的サイト生成のビルド/デプロイ操作を REST として公開する。
 *
 * @package ASG
 * @version 2.0.0
 * @license Adlaire License Ver.2.0
 */

import type {
  ResponseConstructor,
  RouterInterface,
} from "../ACS/ACS.d.ts";
import type { GeneratorInterface } from "./ASG.Interface.ts";

/**
 * ASG REST エンドポイントを Router に登録する
 *
 * FRAMEWORK_RULEBOOK §2.1「フレームワーク間依存ゼロ」準拠:
 * APF を直接 import せず、Response を DI で受け取る。
 */
export function registerGeneratorRoutes(
  router: RouterInterface,
  generator: GeneratorInterface,
  Response: ResponseConstructor,
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

  // クリーン
  router.post("/api/build/clean", async () => {
    await generator.clean();
    return Response.json({ ok: true, data: { cleaned: true } });
  });
}
