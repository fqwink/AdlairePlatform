/**
 * Adlaire Content Engine (ACE) — Data Models & Enums
 *
 * ACE 固有のデータモデル・列挙型を定義する。
 *
 * @package ACE
 * @version 2.0.0
 * @license Adlaire License Ver.2.0
 */

import type { FieldType } from "../types.ts";

// ============================================================================
// Content Format
// ============================================================================

/**
 * コンテンツフォーマット
 */
export class ContentFormat {
  static readonly MARKDOWN = new ContentFormat("markdown", ".md");
  static readonly HTML = new ContentFormat("html", ".html");
  static readonly JSON = new ContentFormat("json", ".json");

  private constructor(
    public readonly value: string,
    public readonly extension: string,
  ) {}

  toString(): string {
    return this.value;
  }

  static from(name: string): ContentFormat {
    switch (name.toLowerCase()) {
      case "markdown":
      case "md":
        return ContentFormat.MARKDOWN;
      case "html":
        return ContentFormat.HTML;
      case "json":
        return ContentFormat.JSON;
      default:
        throw new Error(`Unknown content format: ${name}`);
    }
  }
}

// ============================================================================
// Sort Order
// ============================================================================

export class SortOrder {
  static readonly ASC = new SortOrder("asc");
  static readonly DESC = new SortOrder("desc");

  private constructor(public readonly value: "asc" | "desc") {}

  static from(name: string): SortOrder {
    return name.toLowerCase() === "desc" ? SortOrder.DESC : SortOrder.ASC;
  }

  toString(): string {
    return this.value;
  }
}

// ============================================================================
// Errors
// ============================================================================

/**
 * コレクション操作エラー
 */
export class CollectionError extends Error {
  constructor(
    message: string,
    public readonly collection: string,
    public readonly code: string = "COLLECTION_ERROR",
  ) {
    super(message);
    this.name = "CollectionError";
  }
}

/**
 * コンテンツ不在エラー
 */
export class ContentNotFoundError extends Error {
  constructor(
    public readonly collection: string,
    public readonly slug: string,
  ) {
    super(`Content not found: ${collection}/${slug}`);
    this.name = "ContentNotFoundError";
  }
}

/**
 * スラッグ重複エラー
 */
export class DuplicateSlugError extends Error {
  constructor(
    public readonly collection: string,
    public readonly slug: string,
  ) {
    super(`Slug already exists: ${collection}/${slug}`);
    this.name = "DuplicateSlugError";
  }
}

// ============================================================================
// Field Type Helpers
// ============================================================================

/**
 * フィールドタイプのデフォルト値マッピング
 */
export const FIELD_TYPE_DEFAULTS: Record<FieldType, unknown> = {
  string: "",
  text: "",
  number: 0,
  boolean: false,
  date: "",
  datetime: "",
  array: [],
  image: "",
  select: "",
};

/**
 * スラッグとして許可される文字パターン
 */
export const SLUG_PATTERN = /^[a-z0-9]+(?:-[a-z0-9]+)*$/;
