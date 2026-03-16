/**
 * Adlaire Content Engine (ACE) — Core Module
 *
 * コレクション管理、コンテンツ CRUD、メタデータ処理、バリデーションを提供する。
 * PHP ACE.Core.php からの移植。
 *
 * ACS (AdlaireClient) を通じてサーバ側ストレージと通信する。
 *
 * @package ACE
 * @version 2.0.0
 * @license Adlaire License Ver.2.0
 */

import type {
  AdlaireClient,
  CollectionItem,
  CollectionSchema,
  CollectionSummary,
  FieldDef,
  FrontMatterResult,
  ItemMeta,
  SearchResult,
  ValidationErrors,
} from "../types.ts";

import type {
  CollectionManagerInterface,
  ContentManagerInterface,
  ContentValidatorInterface,
  ItemSaveData,
  ListItemsOptions,
  MetaManagerInterface,
} from "./ACE.Interface.ts";

import { CollectionError, SLUG_PATTERN } from "./ACE.Class.ts";

// ============================================================================
// CollectionManager — コレクション管理
// ============================================================================

export class CollectionManager implements CollectionManagerInterface {
  constructor(private readonly client: AdlaireClient) {}

  async create(
    name: string,
    label: string,
    fields: Record<string, FieldDef> = {},
  ): Promise<boolean> {
    if (!SLUG_PATTERN.test(name)) {
      throw new CollectionError(`Invalid collection name: ${name}`, name);
    }

    const schema = await this.loadSchema();
    if (schema[name]) {
      throw new CollectionError(`Collection already exists: ${name}`, name);
    }

    schema[name] = {
      name,
      label,
      directory: name,
      format: "markdown",
      fields,
      createdAt: new Date().toISOString(),
    };

    await this.saveSchema(schema);
    return true;
  }

  async delete(name: string): Promise<boolean> {
    const schema = await this.loadSchema();
    if (!schema[name]) return false;

    delete schema[name];
    await this.saveSchema(schema);
    return true;
  }

  async listCollections(): Promise<CollectionSummary[]> {
    const schema = await this.loadSchema();
    const summaries: CollectionSummary[] = [];

    for (const [name, def] of Object.entries(schema)) {
      const items = await this.client.storage.list(`collections/${name}`, ".md");
      summaries.push({
        name,
        label: def.label,
        directory: def.directory,
        format: def.format,
        count: items.length,
      });
    }

    return summaries;
  }

  async getSchema(name: string): Promise<CollectionSchema | null> {
    const schema = await this.loadSchema();
    return schema[name] ?? null;
  }

  isEnabled(): boolean {
    return true;
  }

  private async loadSchema(): Promise<Record<string, CollectionSchema>> {
    return (await this.client.storage.read<Record<string, CollectionSchema>>(
      "collections.json",
      "settings",
    )) ?? {};
  }

  private async saveSchema(schema: Record<string, CollectionSchema>): Promise<void> {
    await this.client.storage.write("collections.json", schema, "settings");
  }
}

// ============================================================================
// ContentManager — コンテンツ CRUD
// ============================================================================

export class ContentManager implements ContentManagerInterface {
  constructor(
    private readonly client: AdlaireClient,
    private readonly meta: MetaManagerInterface,
  ) {}

  async getItem(collection: string, slug: string): Promise<CollectionItem | null> {
    const raw = await this.client.storage.read<string>(
      `${slug}.md`,
      `collections/${collection}`,
    );
    if (raw === null) return null;

    const { meta, body } = this.meta.extractMeta(
      typeof raw === "string" ? raw : JSON.stringify(raw),
    );

    return {
      slug,
      collection,
      meta: meta as ItemMeta,
      body,
    };
  }

  saveItem(collection: string, slug: string, data: ItemSaveData): Promise<boolean> {
    const frontMatter = this.meta.buildMeta(data.meta);
    const content = `---\n${frontMatter}---\n${data.body}`;

    return this.client.storage.write(
      `${slug}.md`,
      content,
      `collections/${collection}`,
    );
  }

  deleteItem(collection: string, slug: string): Promise<boolean> {
    return this.client.storage.delete(
      `${slug}.md`,
      `collections/${collection}`,
    );
  }

  async listItems(collection: string, options?: ListItemsOptions): Promise<CollectionItem[]> {
    const files = await this.client.storage.list(`collections/${collection}`, ".md");
    const items: CollectionItem[] = [];

    // Batch read to avoid N+1 HTTP requests
    const readRequests = files.map((file) => ({
      file: `${file.replace(/\.md$/, "")}.md`,
      directory: `collections/${collection}`,
    }));
    const rawResults = await this.client.storage.readMany<string>(readRequests);

    for (let i = 0; i < files.length; i++) {
      const raw = rawResults[i];
      if (raw === null) continue;

      const slug = files[i].replace(/\.md$/, "");
      const { meta, body } = this.meta.extractMeta(
        typeof raw === "string" ? raw : JSON.stringify(raw),
      );
      const item: CollectionItem = {
        slug,
        collection,
        meta: meta as ItemMeta,
        body,
      };

      // ドラフトフィルタ
      if (options?.draft === false && item.meta.draft) continue;

      items.push(item);
    }

    // ソート
    const sortBy = options?.sortBy ?? "date";
    const sortOrder = options?.sortOrder ?? "desc";
    items.sort((a, b) => {
      const va = String((a.meta as Record<string, unknown>)[sortBy] ?? "");
      const vb = String((b.meta as Record<string, unknown>)[sortBy] ?? "");
      return sortOrder === "desc" ? vb.localeCompare(va) : va.localeCompare(vb);
    });

    // ページネーション
    const offset = options?.offset ?? 0;
    const limit = options?.limit ?? items.length;
    return items.slice(offset, offset + limit);
  }

  async search(query: string, collections?: string[]): Promise<SearchResult[]> {
    const results: SearchResult[] = [];
    const lower = query.toLowerCase();

    const targetCollections = collections ?? await this.getCollectionNames();

    for (const col of targetCollections) {
      const items = await this.listItems(col);
      for (const item of items) {
        const title = item.meta.title ?? "";
        const bodyPreview = item.body.substring(0, 200);

        if (
          title.toLowerCase().includes(lower) ||
          item.body.toLowerCase().includes(lower)
        ) {
          results.push({
            collection: col,
            slug: item.slug,
            title,
            preview: bodyPreview,
          });
        }
      }
    }

    return results;
  }

  private async getCollectionNames(): Promise<string[]> {
    const schema = await this.client.storage.read<Record<string, CollectionSchema>>(
      "collections.json",
      "settings",
    );
    return schema ? Object.keys(schema) : [];
  }
}

// ============================================================================
// MetaManager — Front Matter 処理
// ============================================================================

export class MetaManager implements MetaManagerInterface {
  extractMeta(content: string): FrontMatterResult {
    const fmRegex = /^---\r?\n([\s\S]*?)\r?\n---\r?\n([\s\S]*)$/;
    const match = fmRegex.exec(content);

    if (!match) {
      return { meta: {}, body: content };
    }

    const meta = this.parseYaml(match[1]);
    return { meta, body: match[2] };
  }

  buildMeta(meta: Record<string, unknown>): string {
    const lines: string[] = [];
    for (const [key, value] of Object.entries(meta)) {
      if (value === undefined || value === null) continue;

      if (Array.isArray(value)) {
        lines.push(`${key}: [${value.map((v) => String(v)).join(", ")}]`);
      } else if (typeof value === "string" && /[:#{}[\]|>!&*?,'"]/.test(value)) {
        lines.push(`${key}: "${value.replace(/\\/g, "\\\\").replace(/"/g, '\\"')}"`);
      } else {
        lines.push(`${key}: ${value}`);
      }
    }
    return lines.join("\n") + "\n";
  }

  mergeMeta(
    base: Record<string, unknown>,
    override: Record<string, unknown>,
  ): Record<string, unknown> {
    return { ...base, ...override };
  }

  validateMeta(
    meta: Record<string, unknown>,
    schema: Record<string, FieldDef>,
  ): ValidationErrors {
    const errors: ValidationErrors = {};

    for (const [field, def] of Object.entries(schema)) {
      const value = meta[field];

      if (def.required && (value === undefined || value === null || value === "")) {
        errors[field] = [`${field} is required`];
        continue;
      }

      if (value === undefined || value === null) continue;

      switch (def.type) {
        case "string":
        case "text":
          if (typeof value !== "string") {
            errors[field] = [`${field} must be a string`];
          }
          break;
        case "number":
          if (typeof value !== "number") {
            errors[field] = [`${field} must be a number`];
          }
          break;
        case "boolean":
          if (typeof value !== "boolean") {
            errors[field] = [`${field} must be a boolean`];
          }
          break;
        case "array":
          if (!Array.isArray(value)) {
            errors[field] = [`${field} must be an array`];
          }
          break;
      }
    }

    return errors;
  }

  private parseYaml(yaml: string): Record<string, unknown> {
    const result: Record<string, unknown> = {};
    for (const line of yaml.split("\n")) {
      const trimmed = line.trim();
      if (!trimmed || trimmed.startsWith("#")) continue;

      const idx = trimmed.indexOf(":");
      if (idx === -1) continue;

      const key = trimmed.substring(0, idx).trim();
      const raw = trimmed.substring(idx + 1).trim();

      if (raw.startsWith("[") && raw.endsWith("]")) {
        result[key] = raw.slice(1, -1).split(",").map((s) => this.coerce(s.trim()));
      } else {
        result[key] = this.coerce(raw);
      }
    }
    return result;
  }

  private coerce(value: string): unknown {
    if (
      (value.startsWith('"') && value.endsWith('"')) ||
      (value.startsWith("'") && value.endsWith("'"))
    ) {
      return value.slice(1, -1);
    }
    if (value === "true") return true;
    if (value === "false") return false;
    if (value === "null" || value === "") return null;
    if (/^-?\d+$/.test(value)) return parseInt(value, 10);
    if (/^-?\d+\.\d+$/.test(value)) return parseFloat(value);
    return value;
  }
}

// ============================================================================
// ContentValidator — バリデーション
// ============================================================================

export class ContentValidator implements ContentValidatorInterface {
  validate(
    data: Record<string, unknown>,
    fields: Record<string, FieldDef>,
  ): ValidationErrors {
    const errors: ValidationErrors = {};

    for (const [field, def] of Object.entries(fields)) {
      const value = data[field];

      if (def.required && (value === undefined || value === null || value === "")) {
        errors[field] = [`${field} is required`];
        continue;
      }

      if (value === undefined || value === null) continue;

      // 型チェック
      const typeError = this.checkType(field, value, def.type);
      if (typeError) {
        errors[field] = [typeError];
        continue;
      }

      // min/max チェック
      if (def.min !== undefined && typeof value === "string" && value.length < Number(def.min)) {
        errors[field] = [`${field} must be at least ${def.min} characters`];
      }
      if (def.max !== undefined && typeof value === "string" && value.length > Number(def.max)) {
        errors[field] = [`${field} must not exceed ${def.max} characters`];
      }
    }

    return errors;
  }

  validateSlug(slug: string): boolean {
    return SLUG_PATTERN.test(slug);
  }

  sanitizeSlug(input: string): string {
    return input
      .toLowerCase()
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .replace(/[^a-z0-9\s-]/g, "")
      .replace(/[\s_]+/g, "-")
      .replace(/-+/g, "-")
      .replace(/^-|-$/g, "");
  }

  private checkType(field: string, value: unknown, type: string): string | null {
    switch (type) {
      case "string":
      case "text":
      case "image":
        return typeof value === "string" ? null : `${field} must be a string`;
      case "number":
        return typeof value === "number" ? null : `${field} must be a number`;
      case "boolean":
        return typeof value === "boolean" ? null : `${field} must be a boolean`;
      case "array":
        return Array.isArray(value) ? null : `${field} must be an array`;
      case "date":
      case "datetime":
        return typeof value === "string" ? null : `${field} must be a date string`;
      default:
        return null;
    }
  }
}
