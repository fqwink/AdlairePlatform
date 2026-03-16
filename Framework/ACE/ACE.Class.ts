/**
 * Adlaire Content Engine (ACE) — Data Models & Enums
 *
 * ACE 固有のデータモデル・列挙型を定義する。
 *
 * @package ACE
 * @version 2.0.0
 * @license Adlaire License Ver.2.0
 */

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
// Helpers
// ============================================================================

/**
 * スラッグとして許可される文字パターン
 */
export const SLUG_PATTERN = /^[a-z0-9]+(?:-[a-z0-9]+)*$/;
