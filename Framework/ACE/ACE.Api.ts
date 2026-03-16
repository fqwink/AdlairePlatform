/**
 * Adlaire Content Engine (ACE) — API Adapter Layer
 *
 * ACE のコレクション/コンテンツ操作を REST エンドポイントとして公開する。
 *
 * @package ACE
 * @version 2.0.0
 * @license Adlaire License Ver.2.0
 */

import type {
  RequestInterface,
  ResponseConstructor,
  RouterInterface,
} from "../types.ts";
import type { CollectionManagerInterface, ContentManagerInterface } from "./ACE.Interface.ts";

/**
 * ACE REST エンドポイントを Router に登録する
 *
 * FRAMEWORK_RULEBOOK §2.1「フレームワーク間依存ゼロ」準拠:
 * APF を直接 import せず、Response を DI で受け取る。
 */
export function registerCollectionRoutes(
  router: RouterInterface,
  collections: CollectionManagerInterface,
  content: ContentManagerInterface,
  Response: ResponseConstructor,
): void {
  // コレクション一覧
  router.get("/api/collections", async () => {
    const list = await collections.listCollections();
    return Response.json({ ok: true, data: list });
  });

  // コレクションスキーマ取得
  router.get("/api/collections/{name}", async (req: RequestInterface) => {
    const name = String(req.param("name"));
    const schema = await collections.getSchema(name);
    if (!schema) return Response.notFound(`Collection not found: ${name}`);
    return Response.json({ ok: true, data: schema });
  });

  // アイテム一覧
  router.get("/api/collections/{name}/items", async (req: RequestInterface) => {
    const name = String(req.param("name"));
    const items = await content.listItems(name, {
      sortBy: String(req.query("sortBy", "date")),
      sortOrder: String(req.query("sortOrder", "desc")) as "asc" | "desc",
      limit: Number(req.query("limit", 20)),
      offset: Number(req.query("offset", 0)),
    });
    return Response.json({ ok: true, data: items });
  });

  // アイテム取得
  router.get("/api/collections/{name}/items/{slug}", async (req: RequestInterface) => {
    const name = String(req.param("name"));
    const slug = String(req.param("slug"));
    const item = await content.getItem(name, slug);
    if (!item) return Response.notFound(`Item not found: ${slug}`);
    return Response.json({ ok: true, data: item });
  });

  // 検索
  router.get("/api/search", async (req: RequestInterface) => {
    const query = String(req.query("q", ""));
    if (!query) {
      return Response.json({ ok: false, error: "q parameter required" }, 400);
    }
    const results = await content.search(query);
    return Response.json({ ok: true, data: results });
  });
}
