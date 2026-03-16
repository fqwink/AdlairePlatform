/**
 * Adlaire Static Generator (ASG) — Interface Definitions
 *
 * 静的サイト生成、テンプレートレンダリング、Markdown パース、
 * テーマ管理、ビルドキャッシュ、デプロイメントのインターフェースを定義する。
 *
 * @package ASG
 * @version 2.0.0
 * @license Adlaire License Ver.2.0
 */

import type {
  BuildResult,
  BuildStats,
  BuildManifest,
  BuildState,
  BuildStatusValue,
  ThemeConfig,
  TemplateContext,
  PageData,
  RedirectRule,
  ImageInfo,
  SitemapEntry,
  FrontMatterResult,
  BuildHook,
} from "../types.ts";

// ============================================================================
// Generator
// ============================================================================

export interface GeneratorInterface {
  buildAll(): Promise<BuildResult>;
  buildDiff(previousState?: BuildState): Promise<BuildResult>;
  clean(): Promise<void>;
  getStatus(): GeneratorStatus;
}

export interface GeneratorStatus {
  readonly status: BuildStatusValue;
  readonly config: GeneratorConfig;
  readonly lastBuild?: {
    readonly timestamp: string;
    readonly stats: BuildStats;
  };
}

export interface GeneratorConfig {
  readonly outputDir: string;
  readonly contentDir: string;
  readonly themeDir: string;
  readonly baseUrl: string;
  readonly cleanUrls: boolean;
}

// ============================================================================
// Builder
// ============================================================================

export interface BuilderInterface {
  buildPage(slug: string, content: string, context: TemplateContext): string;
  buildIndex(pages: PageData[], context: TemplateContext): string;
  buildPermalink(slug: string, context: TemplateContext): string;
  buildMetaTags(context: TemplateContext): string;
}

// ============================================================================
// Template Renderer
// ============================================================================

export interface TemplateRendererInterface {
  render(template: string, context: Record<string, unknown>): string;
  registerHelper(name: string, fn: TemplateHelper): void;
  registerPartial(name: string, template: string): void;
}

export type TemplateHelper = (value: string, arg?: string) => string;

// ============================================================================
// Markdown Service
// ============================================================================

export interface MarkdownServiceInterface {
  parseFrontmatter(content: string): FrontMatterResult;
  toHtml(markdown: string): string;
  loadDirectory(dir: string): Promise<Record<string, FrontMatterResult>>;
}

// ============================================================================
// Static Service (High-level Facade)
// ============================================================================

export interface StaticServiceInterface {
  init(): Promise<void>;
  buildAll(): Promise<BuildResult>;
  buildDiff(): Promise<BuildResult>;
  buildZipFile(): Promise<string>;
  buildDiffZipFile(): Promise<string>;
  clean(): Promise<void>;
  getStatus(): Promise<StaticServiceStatus>;

  /** SEO & メタファイル生成 */
  generateSitemap(): Promise<void>;
  generateRobotsTxt(): Promise<void>;
  generate404Page(): Promise<void>;
  generateSearchIndex(): Promise<void>;
  generateRedirects(): Promise<void>;

  /** アセット管理 */
  copyAssets(): Promise<void>;
  syncUploads(): Promise<void>;

  /** HTML/CSS 最適化 */
  minifyHtml(html: string): string;
  minifyCss(css: string): string;

  /** ビルドフック */
  runHook(hookName: string, context: Record<string, unknown>): Promise<void>;
}

export interface StaticServiceStatus {
  readonly pages: Array<{
    readonly slug: string;
    readonly status: "built" | "skipped" | "error";
    readonly hash?: string;
  }>;
  readonly lastBuild?: string;
  readonly totalPages: number;
}

// ============================================================================
// Build Cache
// ============================================================================

export interface BuildCacheInterface {
  get<T = unknown>(key: string): Promise<T | null>;
  set(key: string, value: unknown, ttl?: number): Promise<void>;
  getContentHash(content: string): string;
  hasChanged(slug: string, newHash: string): boolean;
  saveState(state: BuildState): Promise<void>;
  loadState(): Promise<BuildState>;
  buildManifest(currentHashes: Record<string, string>): BuildManifest;
  needsFullRebuild(settingsHash: string, themeHash: string): boolean;
  commitManifest(hashes: Record<string, string>, settingsHash?: string, themeHash?: string): Promise<void>;
}

// ============================================================================
// Diff Builder
// ============================================================================

export interface DiffBuilderInterface {
  detectChanges(
    currentPages: Record<string, { content: string } | string>,
    currentSettings: Record<string, unknown>,
  ): string[];
  markBuilt(slug: string, hash: string): Promise<void>;
}

// ============================================================================
// Site Router (URL Resolution)
// ============================================================================

export interface SiteRouterInterface {
  resolveSlug(url: string): string;
  buildUrl(slug: string): string;
  generateSitemap(pages: SitemapEntry[], baseUrl: string): string;
}

// ============================================================================
// Static File System
// ============================================================================

export interface StaticFileSystemInterface {
  write(path: string, content: string): Promise<boolean>;
  read(path: string): Promise<string | null>;
  delete(path: string): Promise<boolean>;
  ensureDir(dir: string): Promise<boolean>;
  listFiles(dir: string, ext?: string): Promise<string[]>;
  getHash(path: string): Promise<string>;
}

// ============================================================================
// Deployer
// ============================================================================

export interface DeployerInterface {
  createZip(sourceDir: string, outputPath: string): Promise<boolean>;
  createDiffZip(sourceDir: string, changedFiles: string[], outputPath: string): Promise<boolean>;
  generateHtaccess(redirects: RedirectRule[]): string;
  generateRedirectsFile(redirects: RedirectRule[]): string;
}

// ============================================================================
// Image Processor
// ============================================================================

export interface ImageProcessorInterface {
  resize(path: string, maxWidth: number, maxHeight: number): Promise<boolean>;
  thumbnail(path: string, width: number, height: number): Promise<string>;
  toWebP(path: string, quality?: number): Promise<string | false>;
  getInfo(path: string): Promise<ImageInfo>;
}

// ============================================================================
// Theme Manager
// ============================================================================

export interface ThemeManagerInterface {
  loadTheme(name: string): Promise<ThemeConfig>;
  getTemplate(name: string): string | null;
  getPartials(): Record<string, string>;
  getAssets(): string[];
}
