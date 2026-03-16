/**
 * Adlaire Static Generator (ASG) — Core Module
 *
 * Static-First Hybrid 配信エンジン。
 *
 * 設計思想:
 *   1. ビルド時に全ページを静的 HTML として生成（Static-First）
 *   2. リクエスト時はまず静的ファイルを返す（高速）
 *   3. 静的ファイルが存在しない場合のみ動的レンダリング（Hybrid Fallback）
 *   4. 差分ビルドで変更ページのみ再生成（Incremental）
 *
 * PHP の StaticService を TypeScript に移植し、Hybrid 配信ロジックを新規追加。
 *
 * @package ASG
 * @version 2.0.0
 * @license Adlaire License Ver.2.0
 */

import type {
  BuildManifest,
  BuildResult,
  BuildState,
  BuildStats,
  PageData,
  RedirectRule,
  SitemapEntry,
  TemplateContext,
} from "../types.ts";

import type {
  BuildCacheInterface,
  BuilderInterface,
  DeployerInterface,
  GeneratorConfig,
  GeneratorInterface,
  GeneratorStatus,
  MarkdownServiceInterface,
  SiteRouterInterface,
  StaticFileSystemInterface,
  TemplateRendererInterface,
} from "./ASG.Interface.ts";

import { BuildError, BuildStatus, UrlStyle } from "./ASG.Class.ts";

// ============================================================================
// Generator — ビルドオーケストレーター
// ============================================================================

export class Generator implements GeneratorInterface {
  private status = BuildStatus.PENDING;
  private lastStats: BuildStats | null = null;
  private startTime = 0;

  constructor(
    private readonly config: GeneratorConfig,
    private readonly fs: StaticFileSystemInterface,
    private readonly cache: BuildCacheInterface,
    private readonly builder: BuilderInterface,
    private readonly renderer: TemplateRendererInterface,
    private readonly markdown: MarkdownServiceInterface,
  ) {}

  async buildAll(): Promise<BuildResult> {
    this.status = BuildStatus.BUILDING;
    this.startTime = performance.now();
    const warnings: string[] = [];
    const changedFiles: string[] = [];

    try {
      // 出力ディレクトリを初期化
      await this.fs.ensureDir(this.config.outputDir);

      // コンテンツを読み込み
      const pages = await this.loadPages();
      const hashes: Record<string, string> = {};

      let built = 0;
      let errors = 0;

      for (const [slug, page] of Object.entries(pages)) {
        try {
          const html = this.builder.buildPage(slug, page.content, this.buildContext(page, pages));
          const outPath = this.resolveOutputPath(slug);
          await this.fs.ensureDir(outPath.substring(0, outPath.lastIndexOf("/")));
          await this.fs.write(outPath, html);
          hashes[slug] = this.cache.getContentHash(page.content);
          changedFiles.push(outPath);
          built++;
        } catch (e: unknown) {
          errors++;
          warnings.push(`Build failed for ${slug}: ${e instanceof Error ? e.message : String(e)}`);
        }
      }

      // ビルド状態を保存
      const settingsHash = this.cache.getContentHash(JSON.stringify(this.config));
      await this.cache.commitManifest(hashes, settingsHash, "");

      this.status = BuildStatus.COMPLETE;
      const stats: BuildStats = {
        total: Object.keys(pages).length,
        built,
        skipped: 0,
        deleted: 0,
        errors,
        assets: 0,
      };
      this.lastStats = stats;

      return {
        status: "complete",
        stats,
        changedFiles,
        warnings,
        elapsed: performance.now() - this.startTime,
      };
    } catch (e: unknown) {
      this.status = BuildStatus.ERROR;
      throw new BuildError(
        `Full build failed: ${e instanceof Error ? e.message : String(e)}`,
        undefined,
        "template",
      );
    }
  }

  async buildDiff(_previousState?: BuildState): Promise<BuildResult> {
    this.status = BuildStatus.BUILDING;
    this.startTime = performance.now();
    const warnings: string[] = [];
    const changedFiles: string[] = [];

    try {
      const pages = await this.loadPages();
      const currentHashes: Record<string, string> = {};

      for (const [slug, page] of Object.entries(pages)) {
        currentHashes[slug] = this.cache.getContentHash(page.content);
      }

      await this.cache.loadState();

      // 設定変更でフルリビルドが必要か確認
      const settingsHash = this.cache.getContentHash(JSON.stringify(this.config));
      if (this.cache.needsFullRebuild(settingsHash, "")) {
        return this.buildAll();
      }

      // マニフェストで差分を検出
      const manifest = this.cache.buildManifest(currentHashes);

      let built = 0;
      let errors = 0;

      // 変更 + 新規ページのみビルド
      const needsBuild = [...manifest.changed, ...manifest.added];
      for (const slug of needsBuild) {
        const page = pages[slug];
        if (!page) continue;

        try {
          const html = this.builder.buildPage(slug, page.content, this.buildContext(page, pages));
          const outPath = this.resolveOutputPath(slug);
          await this.fs.ensureDir(outPath.substring(0, outPath.lastIndexOf("/")));
          await this.fs.write(outPath, html);
          changedFiles.push(outPath);
          built++;
        } catch (e: unknown) {
          errors++;
          warnings.push(`Build failed for ${slug}: ${e instanceof Error ? e.message : String(e)}`);
        }
      }

      // 削除されたページのファイルを除去
      let deleted = 0;
      for (const slug of manifest.deleted) {
        const outPath = this.resolveOutputPath(slug);
        if (await this.fs.delete(outPath)) {
          deleted++;
        }
      }

      // 状態を保存
      await this.cache.commitManifest(currentHashes, settingsHash, "");

      this.status = BuildStatus.COMPLETE;
      const stats: BuildStats = {
        total: Object.keys(pages).length,
        built,
        skipped: manifest.unchanged.length,
        deleted,
        errors,
        assets: 0,
      };
      this.lastStats = stats;

      return {
        status: "complete",
        stats,
        changedFiles,
        warnings,
        elapsed: performance.now() - this.startTime,
      };
    } catch (e: unknown) {
      this.status = BuildStatus.ERROR;
      throw new BuildError(
        `Diff build failed: ${e instanceof Error ? e.message : String(e)}`,
        undefined,
        "template",
      );
    }
  }

  async clean(): Promise<void> {
    const files = await this.fs.listFiles(this.config.outputDir, ".html");
    for (const file of files) {
      await this.fs.delete(file);
    }
  }

  getStatus(): GeneratorStatus {
    return {
      status: this.status.value,
      config: this.config,
      lastBuild: this.lastStats
        ? {
          timestamp: new Date().toISOString(),
          stats: this.lastStats,
        }
        : undefined,
    };
  }

  // ── Helpers ──

  private resolveOutputPath(slug: string): string {
    const style = this.config.cleanUrls ? UrlStyle.CLEAN : UrlStyle.WITH_EXTENSION;
    return `${this.config.outputDir}/${style.resolveOutputPath(slug)}`;
  }

  private async loadPages(): Promise<Record<string, PageData>> {
    const mdFiles = await this.fs.listFiles(this.config.contentDir, ".md");
    const pages: Record<string, PageData> = {};

    for (const file of mdFiles) {
      const raw = await this.fs.read(file);
      if (raw === null) continue;

      const { meta, body } = this.markdown.parseFrontmatter(raw);
      const slug = file
        .replace(this.config.contentDir, "")
        .replace(/^\//, "")
        .replace(/\.md$/, "");

      pages[slug] = {
        slug,
        title: (meta.title as string) ?? slug,
        content: body,
        meta: meta as PageData["meta"],
      };
    }

    return pages;
  }

  private buildContext(
    page: PageData,
    allPages: Record<string, PageData>,
  ): TemplateContext {
    return {
      site: { title: "", description: "", url: this.config.baseUrl, language: "ja", theme: "" },
      page,
      pages: Object.values(allPages),
    };
  }
}

// ============================================================================
// HybridResolver — Static-First Hybrid 配信の解決ロジック
// ============================================================================

/**
 * Static-First Hybrid 配信リゾルバ
 *
 * リクエストパスに対して:
 *   1. 静的ファイルが存在するか確認 → あればそのパスを返す
 *   2. 存在しなければ動的レンダリング → レンダリング結果を返す
 *   3. レンダリング結果を静的ファイルとしてキャッシュ（次回から静的配信）
 *
 * これにより:
 *   - 初回アクセスでも動的に対応（Hybrid）
 *   - 2回目以降は静的配信（Static-First）
 *   - ビルド済みページは常に静的配信（最速）
 */
export class HybridResolver {
  constructor(
    private readonly outputDir: string,
    private readonly fs: StaticFileSystemInterface,
    private readonly builder: BuilderInterface,
    private readonly urlStyle: UrlStyle,
  ) {}

  /**
   * リクエストパスを解決する
   *
   * @returns 解決結果。静的ファイルのパスまたはレンダリング済み HTML
   */
  async resolve(
    requestPath: string,
    dynamicRenderer?: (slug: string) => Promise<string | null>,
  ): Promise<HybridResult> {
    const slug = this.pathToSlug(requestPath);
    const staticPath = `${this.outputDir}/${this.urlStyle.resolveOutputPath(slug)}`;

    // Phase 1: 静的ファイルを確認
    const staticContent = await this.fs.read(staticPath);
    if (staticContent !== null) {
      return {
        mode: "static",
        slug,
        html: staticContent,
        path: staticPath,
        cached: true,
      };
    }

    // Phase 2: 動的レンダリングにフォールバック
    if (!dynamicRenderer) {
      return { mode: "not_found", slug, html: null, path: null, cached: false };
    }

    const html = await dynamicRenderer(slug);
    if (html === null) {
      return { mode: "not_found", slug, html: null, path: null, cached: false };
    }

    // Phase 3: 生成結果を静的キャッシュとして保存（Write-Through Cache）
    const dir = staticPath.substring(0, staticPath.lastIndexOf("/"));
    await this.fs.ensureDir(dir);
    await this.fs.write(staticPath, html);

    return {
      mode: "dynamic",
      slug,
      html,
      path: staticPath,
      cached: false,
    };
  }

  /**
   * 特定のスラッグの静的キャッシュを無効化する
   *
   * コンテンツ更新時に呼び出し、次回アクセスで再生成させる。
   */
  invalidate(slug: string): Promise<boolean> {
    const staticPath = `${this.outputDir}/${this.urlStyle.resolveOutputPath(slug)}`;
    return this.fs.delete(staticPath);
  }

  /**
   * 全静的キャッシュを無効化する
   */
  async invalidateAll(): Promise<number> {
    const files = await this.fs.listFiles(this.outputDir, ".html");
    let count = 0;
    for (const file of files) {
      if (await this.fs.delete(file)) count++;
    }
    return count;
  }

  /**
   * 静的ファイルの存在を確認する
   */
  async hasStatic(slug: string): Promise<boolean> {
    const staticPath = `${this.outputDir}/${this.urlStyle.resolveOutputPath(slug)}`;
    const content = await this.fs.read(staticPath);
    return content !== null;
  }

  private pathToSlug(path: string): string {
    let slug = path.replace(/^\//, "").replace(/\/$/, "");

    // /foo/bar/ → foo/bar
    // /foo/bar.html → foo/bar
    slug = slug.replace(/\/index\.html$/, "").replace(/\.html$/, "");

    return slug || "index";
  }
}

/**
 * Hybrid 解決結果
 */
export interface HybridResult {
  /** 配信モード */
  readonly mode: "static" | "dynamic" | "not_found";
  /** 解決されたスラッグ */
  readonly slug: string;
  /** HTML コンテンツ（not_found の場合は null） */
  readonly html: string | null;
  /** 静的ファイルのパス */
  readonly path: string | null;
  /** 既存の静的キャッシュから配信されたか */
  readonly cached: boolean;
}

// ============================================================================
// BuildCache — ビルド状態キャッシュ
// ============================================================================

export class BuildCache implements BuildCacheInterface {
  private stateCache: BuildState | null = null;

  constructor(
    private readonly cacheDir: string,
    private readonly fs: StaticFileSystemInterface,
  ) {}

  async get<T = unknown>(key: string): Promise<T | null> {
    const path = `${this.cacheDir}/${this.hashKey(key)}.cache.json`;
    const content = await this.fs.read(path);
    if (content === null) return null;

    try {
      const data = JSON.parse(content);
      if (data.expires > 0 && data.expires < Date.now() / 1000) {
        await this.fs.delete(path);
        return null;
      }
      return data.value as T;
    } catch {
      return null;
    }
  }

  async set(key: string, value: unknown, ttl: number = 0): Promise<void> {
    const path = `${this.cacheDir}/${this.hashKey(key)}.cache.json`;
    await this.fs.ensureDir(this.cacheDir);
    const data = {
      value,
      expires: ttl > 0 ? Math.floor(Date.now() / 1000) + ttl : 0,
      created: Math.floor(Date.now() / 1000),
    };
    await this.fs.write(path, JSON.stringify(data, null, 2));
  }

  getContentHash(content: string): string {
    // SHA-256 の同期版（Deno / Web Crypto は async だが、ビルド用途ではシンプルなハッシュで十分）
    let hash = 0;
    for (let i = 0; i < content.length; i++) {
      const char = content.charCodeAt(i);
      hash = ((hash << 5) - hash + char) | 0;
    }
    // より長いハッシュにするため、複数パスを組み合わせる
    let hash2 = 0x811c9dc5;
    for (let i = 0; i < content.length; i++) {
      hash2 ^= content.charCodeAt(i);
      hash2 = (hash2 * 0x01000193) | 0;
    }
    return (hash >>> 0).toString(16).padStart(8, "0") + (hash2 >>> 0).toString(16).padStart(8, "0");
  }

  hasChanged(slug: string, newHash: string): boolean {
    if (!this.stateCache) return true;
    return (this.stateCache.hashes[slug] ?? "") !== newHash;
  }

  async saveState(state: BuildState): Promise<void> {
    await this.fs.ensureDir(this.cacheDir);
    await this.fs.write(
      `${this.cacheDir}/build_state.json`,
      JSON.stringify(state, null, 2),
    );
    this.stateCache = state;
  }

  async loadState(): Promise<BuildState> {
    if (this.stateCache) return this.stateCache;

    const content = await this.fs.read(`${this.cacheDir}/build_state.json`);
    if (content === null) {
      const empty: BuildState = {
        hashes: {},
        settings_hash: "",
        theme_hash: "",
        timestamp: "",
        version: "2.0.0",
      };
      this.stateCache = empty;
      return empty;
    }

    try {
      this.stateCache = JSON.parse(content) as BuildState;
      return this.stateCache;
    } catch {
      const empty: BuildState = {
        hashes: {},
        settings_hash: "",
        theme_hash: "",
        timestamp: "",
        version: "2.0.0",
      };
      this.stateCache = empty;
      return empty;
    }
  }

  buildManifest(currentHashes: Record<string, string>): BuildManifest {
    const previous = this.stateCache?.hashes ?? {};
    const changed: string[] = [];
    const added: string[] = [];
    const deleted: string[] = [];
    const unchanged: string[] = [];

    for (const [slug, hash] of Object.entries(currentHashes)) {
      if (!(slug in previous)) {
        added.push(slug);
      } else if (previous[slug] !== hash) {
        changed.push(slug);
      } else {
        unchanged.push(slug);
      }
    }

    for (const slug of Object.keys(previous)) {
      if (!(slug in currentHashes)) {
        deleted.push(slug);
      }
    }

    return {
      changed,
      added,
      deleted,
      unchanged,
      stats: {
        total: Object.keys(currentHashes).length,
        changed: changed.length,
        added: added.length,
        deleted: deleted.length,
        unchanged: unchanged.length,
        needs_build: changed.length + added.length,
      },
    };
  }

  needsFullRebuild(settingsHash: string, themeHash: string): boolean {
    if (!this.stateCache) return true;
    return (
      (this.stateCache.settings_hash !== "" && this.stateCache.settings_hash !== settingsHash) ||
      (this.stateCache.theme_hash !== "" && this.stateCache.theme_hash !== themeHash)
    );
  }

  async commitManifest(
    hashes: Record<string, string>,
    settingsHash: string = "",
    themeHash: string = "",
  ): Promise<void> {
    await this.saveState({
      hashes,
      settings_hash: settingsHash,
      theme_hash: themeHash,
      timestamp: new Date().toISOString(),
      version: "2.0.0",
    });
  }

  private hashKey(key: string): string {
    // 簡易 MD5 ライクなハッシュ
    let hash = 0;
    for (let i = 0; i < key.length; i++) {
      hash = ((hash << 5) - hash + key.charCodeAt(i)) | 0;
    }
    return (hash >>> 0).toString(16).padStart(8, "0");
  }
}

// ============================================================================
// SiteRouter — URL 解決とサイトマップ生成
// ============================================================================

export class SiteRouter implements SiteRouterInterface {
  constructor(
    private readonly baseUrl: string,
    private readonly urlStyle: UrlStyle,
  ) {}

  resolveSlug(url: string): string {
    let path = url;

    // ベース URL を除去
    if (path.startsWith(this.baseUrl)) {
      path = path.substring(this.baseUrl.length);
    }

    path = path.replace(/^\//, "").replace(/\/$/, "");
    path = path.replace(/\/index\.html$/, "").replace(/\.html$/, "");

    return path || "index";
  }

  buildUrl(slug: string): string {
    return this.urlStyle.buildUrl(slug, this.baseUrl);
  }

  generateSitemap(pages: SitemapEntry[], _baseUrl: string): string {
    const lines: string[] = [
      '<?xml version="1.0" encoding="UTF-8"?>',
      '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
    ];

    for (const page of pages) {
      lines.push("  <url>");
      lines.push(`    <loc>${this.escapeXml(page.url)}</loc>`);
      if (page.lastmod) {
        lines.push(`    <lastmod>${page.lastmod}</lastmod>`);
      }
      if (page.changefreq) {
        lines.push(`    <changefreq>${page.changefreq}</changefreq>`);
      }
      if (page.priority !== undefined) {
        lines.push(`    <priority>${page.priority}</priority>`);
      }
      lines.push("  </url>");
    }

    lines.push("</urlset>");
    return lines.join("\n");
  }

  private escapeXml(str: string): string {
    return str
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&apos;");
  }
}

// ============================================================================
// Deployer — デプロイメントヘルパー
// ============================================================================

export class Deployer implements DeployerInterface {
  constructor(private readonly fs: StaticFileSystemInterface) {}

  createZip(_sourceDir: string, _outputPath: string): Promise<boolean> {
    // Deno では外部ライブラリ or コマンドライン zip を使用
    // 実装は環境依存のため、インターフェースのみ定義
    throw new Error("ZIP creation requires platform-specific implementation");
  }

  createDiffZip(
    _sourceDir: string,
    _changedFiles: string[],
    _outputPath: string,
  ): Promise<boolean> {
    throw new Error("ZIP creation requires platform-specific implementation");
  }

  generateHtaccess(redirects: RedirectRule[]): string {
    const lines: string[] = [
      "# Generated by ASG (Adlaire Static Generator) v2.0",
      `# ${new Date().toISOString()}`,
      "",
      "RewriteEngine On",
      "",
      "ErrorDocument 404 /404.html",
      "",
      "AddDefaultCharset UTF-8",
      "",
      "<IfModule mod_mime.c>",
      "    AddType text/html .html",
      "    AddType text/css .css",
      "    AddType application/javascript .js",
      "    AddType image/webp .webp",
      "    AddType image/svg+xml .svg",
      "</IfModule>",
      "",
      "<IfModule mod_deflate.c>",
      "    AddOutputFilterByType DEFLATE text/html text/css application/javascript",
      "    AddOutputFilterByType DEFLATE application/json application/xml",
      "    AddOutputFilterByType DEFLATE image/svg+xml",
      "</IfModule>",
      "",
      "<IfModule mod_expires.c>",
      "    ExpiresActive On",
      '    ExpiresByType text/html "access plus 1 hour"',
      '    ExpiresByType text/css "access plus 1 month"',
      '    ExpiresByType application/javascript "access plus 1 month"',
      '    ExpiresByType image/jpeg "access plus 1 year"',
      '    ExpiresByType image/png "access plus 1 year"',
      '    ExpiresByType image/webp "access plus 1 year"',
      "</IfModule>",
      "",
    ];

    if (redirects.length > 0) {
      lines.push("# Redirects");
      for (const r of redirects) {
        const flag = r.status === 302 ? "[R=302,L]" : "[R=301,L]";
        const escaped = r.from.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
        lines.push(`RewriteRule ^${escaped}$ ${r.to} ${flag}`);
      }
      lines.push("");
    }

    return lines.join("\n") + "\n";
  }

  generateRedirectsFile(redirects: RedirectRule[]): string {
    const lines: string[] = [
      "# Generated by ASG (Adlaire Static Generator) v2.0",
      `# ${new Date().toISOString()}`,
      "",
    ];

    for (const r of redirects) {
      lines.push(`${r.from}    ${r.to}    ${r.status}`);
    }

    lines.push("");
    lines.push("# Custom 404");
    lines.push("/*    /404.html    404");

    return lines.join("\n") + "\n";
  }
}

// ============================================================================
// HtmlMinifier — HTML 最適化
// ============================================================================

export class HtmlMinifier {
  /**
   * HTML を最小化する
   *
   * - コメント除去（条件付きコメントは維持）
   * - 連続空白の圧縮
   * - pre/code/script/style 内は保持
   */
  static minify(html: string): string {
    // pre/code/script/style ブロックを保護
    const preserved: string[] = [];
    let result = html.replace(
      /(<(?:pre|code|script|style|textarea)[^>]*>)([\s\S]*?)(<\/(?:pre|code|script|style|textarea)>)/gi,
      (_match, open: string, content: string, close: string) => {
        const idx = preserved.length;
        preserved.push(open + content + close);
        return `\x00PRESERVE_${idx}\x00`;
      },
    );

    // HTML コメント除去（条件付きコメントは保持）
    result = result.replace(/<!--(?!\[if)[\s\S]*?-->/g, "");

    // 連続空白を圧縮
    result = result.replace(/\s{2,}/g, " ");

    // タグ間の空白を除去
    result = result.replace(/>\s+</g, "><");

    // 保護ブロックを復元
    // deno-lint-ignore no-control-regex
    result = result.replace(/\x00PRESERVE_(\d+)\x00/g, (_match, idx: string) => {
      return preserved[Number(idx)];
    });

    return result.trim();
  }
}

// ============================================================================
// CssMinifier — CSS 最適化
// ============================================================================

export class CssMinifier {
  /**
   * CSS を最小化する（calc() 式を保持）
   */
  static minify(css: string): string {
    // calc() 内の空白を保護
    const calcExpressions: string[] = [];
    let result = css.replace(/calc\([^)]+\)/g, (match) => {
      const idx = calcExpressions.length;
      calcExpressions.push(match);
      return `\x00CALC_${idx}\x00`;
    });

    // コメント除去
    result = result.replace(/\/\*[\s\S]*?\*\//g, "");

    // 空白圧縮
    result = result.replace(/\s+/g, " ");
    result = result.replace(/\s*([{}:;,>~+])\s*/g, "$1");

    // 末尾セミコロン除去
    result = result.replace(/;}/g, "}");

    // calc() 復元
    // deno-lint-ignore no-control-regex
    result = result.replace(/\x00CALC_(\d+)\x00/g, (_match, idx: string) => {
      return calcExpressions[Number(idx)];
    });

    return result.trim();
  }
}
