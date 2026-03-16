/**
 * Adlaire Content Engine (ACE) — Interface Definitions
 *
 * コレクション管理、コンテンツ CRUD、メタデータ、バリデーション、
 * Webhook、API ルーティングのインターフェースを定義する。
 *
 * @package ACE
 * @version 2.0.0
 * @license Adlaire License Ver.2.0
 */

import type {
  ApiResponse,
  CollectionItem,
  CollectionSchema,
  CollectionSummary,
  FieldDef,
  FrontMatterResult,
  ItemMeta,
  PageData,
  RevisionEntry,
  SearchResult,
  ValidationErrors,
  WebhookConfig,
  WebhookEvent,
} from "../types.ts";

// ============================================================================
// Collection Management
// ============================================================================

export interface CollectionManagerInterface {
  create(name: string, label: string, fields?: Record<string, FieldDef>): Promise<boolean>;
  delete(name: string): Promise<boolean>;
  listCollections(): Promise<CollectionSummary[]>;
  getSchema(name: string): Promise<CollectionSchema | null>;
  isEnabled(): boolean;
}

// ============================================================================
// Content Management
// ============================================================================

export interface ContentManagerInterface {
  getItem(collection: string, slug: string): Promise<CollectionItem | null>;
  saveItem(collection: string, slug: string, data: ItemSaveData): Promise<boolean>;
  deleteItem(collection: string, slug: string): Promise<boolean>;
  listItems(collection: string, options?: ListItemsOptions): Promise<CollectionItem[]>;
  search(query: string, collections?: string[]): Promise<SearchResult[]>;
}

export interface ItemSaveData {
  readonly meta: ItemMeta;
  readonly body: string;
  readonly isNew?: boolean;
}

export interface ListItemsOptions {
  readonly sortBy?: string;
  readonly sortOrder?: "asc" | "desc";
  readonly limit?: number;
  readonly offset?: number;
  readonly draft?: boolean;
}

// ============================================================================
// Meta Management
// ============================================================================

export interface MetaManagerInterface {
  extractMeta(content: string): FrontMatterResult;
  buildMeta(meta: Record<string, unknown>): string;
  mergeMeta(
    base: Record<string, unknown>,
    override: Record<string, unknown>,
  ): Record<string, unknown>;
  validateMeta(meta: Record<string, unknown>, schema: Record<string, FieldDef>): ValidationErrors;
}

// ============================================================================
// Content Validation
// ============================================================================

export interface ContentValidatorInterface {
  validate(data: Record<string, unknown>, fields: Record<string, FieldDef>): ValidationErrors;
  validateSlug(slug: string): boolean;
  sanitizeSlug(input: string): string;
}

// ============================================================================
// Collection Service (Static Facade → Instance)
// ============================================================================

export interface CollectionServiceInterface {
  isEnabled(): boolean;
  loadSchema(): Promise<Record<string, CollectionSchema>>;
  saveSchema(schema: Record<string, CollectionSchema>): Promise<void>;
  listCollections(): Promise<CollectionSummary[]>;
  getCollectionDef(name: string): Promise<CollectionSchema | null>;
  createCollection(name: string, def: Omit<CollectionSchema, "name">): Promise<boolean>;
  deleteCollection(name: string): Promise<boolean>;
  getItems(collection: string): Promise<CollectionItem[]>;
  getAllItems(collection: string): Promise<CollectionItem[]>;
  getItem(collection: string, slug: string): Promise<CollectionItem | null>;
  saveItem(
    collection: string,
    slug: string,
    meta: ItemMeta,
    body: string,
    isNew?: boolean,
  ): Promise<boolean>;
  validateFields(collection: string, meta: Record<string, unknown>): Promise<ValidationErrors>;
  deleteItem(collection: string, slug: string): Promise<boolean>;
  loadAllAsPages(): Promise<Record<string, PageData>>;
  migrateFromPagesJson(): Promise<{ migrated: number; errors: string[] }>;
}

// ============================================================================
// API Router
// ============================================================================

export interface ApiRouterInterface {
  registerEndpoint(name: string, handler: ApiEndpointHandler, requiresAuth?: boolean): void;
  dispatch(
    endpoint: string,
    params: Record<string, unknown>,
    requestBody?: string,
  ): Promise<ApiResponse>;
  listEndpoints(): string[];
}

export type ApiEndpointHandler = (
  params: Record<string, unknown>,
  requestBody?: string,
) => Promise<ApiResponse>;

// ============================================================================
// Webhooks
// ============================================================================

export interface WebhookServiceInterface {
  listWebhooks(): Promise<WebhookConfig[]>;
  addWebhook(url: string, label: string, events: WebhookEvent[], secret?: string): Promise<boolean>;
  deleteWebhook(index: number): Promise<boolean>;
  toggleWebhook(index: number): Promise<boolean>;
  dispatch(event: WebhookEvent, data: Record<string, unknown>): Promise<void>;
}

// ============================================================================
// Revisions
// ============================================================================

export interface RevisionServiceInterface {
  list(slug: string): Promise<RevisionEntry[]>;
  get(slug: string, file: string): Promise<string | null>;
  restore(slug: string, file: string): Promise<boolean>;
  pin(slug: string, file: string): Promise<boolean>;
  search(slug: string, query: string): Promise<RevisionEntry[]>;
}
