/**
 * Adlaire Infrastructure Services (AIS) — API Adapter Layer
 *
 * サイト設定、i18n、診断エンドポイントを REST として公開する。
 *
 * @package AIS
 * @version 2.0.0
 * @license Adlaire License Ver.2.0
 */

import type {
  RequestInterface,
  ResponseConstructor,
  RouterInterface,
} from "../types.ts";
import type { AppContextInterface, I18nInterface } from "./AIS.Interface.ts";

/**
 * AIS REST エンドポイントを Router に登録する
 *
 * FRAMEWORK_RULEBOOK §2.1「フレームワーク間依存ゼロ」準拠:
 * APF を直接 import せず、Response を DI で受け取る。
 */
export function registerInfraRoutes(
  router: RouterInterface,
  context: AppContextInterface,
  i18n: I18nInterface,
  Response: ResponseConstructor,
): void {
  // サイト設定取得
  router.get("/api/settings", () => {
    return Response.json({ ok: true, data: context.all() });
  });

  // 設定値取得
  router.get("/api/settings/{key}", (req: RequestInterface) => {
    const key = String(req.param("key"));
    if (!context.has(key)) {
      return Response.notFound(`Setting not found: ${key}`);
    }
    return Response.json({ ok: true, data: context.get(key) });
  });

  // 翻訳取得
  router.get("/api/i18n", () => {
    return Response.json({
      ok: true,
      data: {
        locale: i18n.getLocale(),
        translations: i18n.all(),
      },
    });
  });

  // 翻訳キー取得
  router.get("/api/i18n/{key}", (req: RequestInterface) => {
    const key = String(req.param("key"));
    return Response.json({ ok: true, data: { key, value: i18n.t(key) } });
  });
}
