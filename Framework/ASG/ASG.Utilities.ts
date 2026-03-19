/**
 * Adlaire Static Generator (ASG) — Utilities Module
 *
 * テンプレートレンダリング（Handlebars ライク）、Markdown パース、
 * テーマ管理を提供する。
 * PHP ASG.Template.php からの移植。
 *
 * @package ASG
 * @version 2.0.0
 * @license Adlaire License Ver.2.0
 */

import type {
  BuilderInterface,
  FrontMatterResult,
  MarkdownServiceInterface,
  StaticFileSystemInterface,
  TemplateHelper,
  TemplateRendererInterface,
  ThemeConfig,
  ThemeManagerInterface,
} from "./ASG.Interface.ts";

import { PARTIAL_MAX_DEPTH } from "./ASG.Class.ts";

// ============================================================================
// TemplateRenderer — Handlebars ライクテンプレートエンジン
// ============================================================================

/**
 * Handlebars ライク構文:
 *   {{variable}}              → エスケープ出力
 *   {{{variable}}}            → 生 HTML
 *   {{user.name}}             → ドット記法
 *   {{var|filter}}            → フィルター
 *   {{#if var}}...{{else}}...{{/if}}  → 条件分岐
 *   {{#each items}}...{{/each}}       → ループ (@index, @first, @last)
 *   {{> partial}}             → パーシャル展開
 */
export class TemplateRenderer implements TemplateRendererInterface {
  private helpers = new Map<string, TemplateHelper>();
  private partials = new Map<string, string>();
  private partialDepth = 0;

  render(template: string, context: Record<string, unknown>): string {
    this.partialDepth = 0;
    let html = this.processPartials(template, context);
    html = this.processEach(html, context);
    html = this.processIf(html, context);
    html = this.processRawVars(html, context);
    html = this.processVars(html, context);
    return html;
  }

  registerHelper(name: string, fn: TemplateHelper): void {
    this.helpers.set(name, fn);
  }

  registerPartial(name: string, template: string): void {
    this.partials.set(name, template);
  }

  // ── Partials ──

  private processPartials(tpl: string, ctx: Record<string, unknown>): string {
    return tpl.replace(/\{\{>\s*(\w+)\s*\}\}/g, (_match, name: string) => {
      if (this.partialDepth >= PARTIAL_MAX_DEPTH) return "";
      const partial = this.partials.get(name);
      if (!partial) return "";
      this.partialDepth++;
      const result = this.processPartials(partial, ctx);
      this.partialDepth--;
      return result;
    });
  }

  // ── {{#each}} ──

  private processEach(tpl: string, ctx: Record<string, unknown>): string {
    let offset = 0;

    while (true) {
      const openMatch = tpl.substring(offset).match(/\{\{#each\s+([\w.]+)\}\}/);
      if (!openMatch || openMatch.index === undefined) break;

      const tagStart = offset + openMatch.index;
      const tagEnd = tagStart + openMatch[0].length;
      const key = openMatch[1];

      const closeEnd = this.findClosingTag(tpl, tagEnd, "each");
      if (closeEnd === null) {
        offset = tagEnd;
        continue;
      }

      const closeTagLen = "{{/each}}".length;
      const body = tpl.substring(tagEnd, closeEnd - closeTagLen);
      const items = this.resolveValue(key, ctx);

      let replacement = "";
      if (Array.isArray(items)) {
        const count = items.length;
        for (let i = 0; i < count; i++) {
          const item = items[i];
          const loopCtx: Record<string, unknown> = {
            ...ctx,
            "@index": i,
            "@first": i === 0,
            "@last": i === count - 1,
          };

          if (typeof item === "object" && item !== null) {
            Object.assign(loopCtx, item);
          } else {
            loopCtx["this"] = item;
          }

          let rendered = this.processEach(body, loopCtx);
          rendered = this.processIf(rendered, loopCtx);
          rendered = this.processRawVars(rendered, loopCtx);
          rendered = this.processVars(rendered, loopCtx);
          replacement += rendered;
        }
      }

      tpl = tpl.substring(0, tagStart) + replacement + tpl.substring(closeEnd);
      offset = tagStart + replacement.length;
    }

    return tpl;
  }

  // ── {{#if}} ──

  private processIf(tpl: string, ctx: Record<string, unknown>): string {
    let offset = 0;

    while (true) {
      const openMatch = tpl.substring(offset).match(/\{\{#if\s+(!?)(\w[\w.]*)\}\}/);
      if (!openMatch || openMatch.index === undefined) break;

      const tagStart = offset + openMatch.index;
      const tagEnd = tagStart + openMatch[0].length;
      const negate = openMatch[1] === "!";
      const varName = openMatch[2];

      const closeEnd = this.findClosingTag(tpl, tagEnd, "if");
      if (closeEnd === null) {
        offset = tagEnd;
        continue;
      }

      const closeTagLen = "{{/if}}".length;
      const innerContent = tpl.substring(tagEnd, closeEnd - closeTagLen);

      // {{else}} 分割
      const elseParts = this.splitElse(innerContent);
      const truthyBlock = elseParts[0];
      const falsyBlock = elseParts[1] ?? "";

      const value = this.resolveValue(varName, ctx);
      let isTruthy = Boolean(value);
      if (negate) isTruthy = !isTruthy;

      // 配列の場合は length > 0 で判定
      if (Array.isArray(value)) {
        isTruthy = negate ? value.length === 0 : value.length > 0;
      }

      const chosen = isTruthy ? truthyBlock : falsyBlock;
      tpl = tpl.substring(0, tagStart) + chosen + tpl.substring(closeEnd);
      offset = tagStart + chosen.length;
    }

    return tpl;
  }

  // ── {{{raw}}} ──

  private processRawVars(tpl: string, ctx: Record<string, unknown>): string {
    return tpl.replace(/\{\{\{([\w.]+)\}\}\}/g, (_match, key: string) => {
      const value = this.resolveValue(key, ctx);
      return value !== null && value !== undefined ? String(value) : "";
    });
  }

  // ── {{escaped}} ──

  private processVars(tpl: string, ctx: Record<string, unknown>): string {
    return tpl.replace(/\{\{([\w.]+(?:\|[\w:]+)?)\}\}/g, (_match, expr: string) => {
      const [key, filter] = expr.split("|");
      const value = this.resolveValue(key, ctx);

      if (value === null || value === undefined) return "";

      let str = String(value);

      // フィルター適用
      if (filter) {
        const [filterName, filterArg] = filter.split(":");
        const helper = this.helpers.get(filterName);
        if (helper) {
          str = helper(str, filterArg);
        }
      }

      return this.escapeHtml(str);
    });
  }

  // ── Helpers ──

  private resolveValue(key: string, ctx: Record<string, unknown>): unknown {
    const keys = key.split(".");
    let current: unknown = ctx;

    for (const k of keys) {
      if (current === null || current === undefined || typeof current !== "object") {
        return undefined;
      }
      current = (current as Record<string, unknown>)[k];
    }

    return current;
  }

  private escapeHtml(str: string): string {
    return str
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  private findClosingTag(tpl: string, start: number, tagName: string): number | null {
    let depth = 1;
    let pos = start;
    const closeStr = `{{/${tagName}}}`;
    const openPattern = new RegExp(`\\{\\{#${tagName}(\\s|\\}\\})`);

    while (pos < tpl.length && depth > 0) {
      const remaining = tpl.substring(pos);
      const openMatch = openPattern.exec(remaining);
      const nextOpen = openMatch ? pos + openMatch.index : -1;
      const nextClose = tpl.indexOf(closeStr, pos);

      if (nextClose === -1) return null;

      if (nextOpen !== -1 && nextOpen < nextClose) {
        depth++;
        pos = nextOpen + openMatch![0].length;
      } else {
        depth--;
        pos = nextClose + closeStr.length;
      }
    }

    return depth === 0 ? pos : null;
  }

  private splitElse(content: string): [string, string | undefined] {
    // ネストされた if 内の else を無視するため、深度を追跡
    let depth = 0;
    let i = 0;

    while (i < content.length) {
      if (content.substring(i).startsWith("{{#if")) {
        depth++;
        i += 5;
      } else if (content.substring(i).startsWith("{{/if}}")) {
        depth--;
        i += 7;
      } else if (depth === 0 && content.substring(i).startsWith("{{else}}")) {
        return [
          content.substring(0, i),
          content.substring(i + "{{else}}".length),
        ];
      } else {
        i++;
      }
    }

    return [content, undefined];
  }
}

// ============================================================================
// MarkdownService — Markdown パース + Front Matter 抽出
// ============================================================================

export class MarkdownService implements MarkdownServiceInterface {
  /**
   * YAML Front Matter を抽出する
   *
   * ```
   * ---
   * title: Hello
   * date: 2024-01-01
   * tags: [a, b]
   * ---
   * Content here
   * ```
   */
  parseFrontmatter(content: string): FrontMatterResult {
    const fmRegex = /^---\r?\n([\s\S]*?)\r?\n---(?:\r?\n([\s\S]*))?$/;
    const match = fmRegex.exec(content);

    if (!match) {
      return { meta: {}, body: content };
    }

    const yamlStr = match[1];
    const body = match[2] ?? "";
    const meta = this.parseSimpleYaml(yamlStr);

    return { meta, body };
  }

  /**
   * Markdown を HTML に変換する
   *
   * 軽量パーサー。見出し、段落、リスト、コードブロック、インライン書式に対応。
   */
  toHtml(markdown: string): string {
    let html = markdown;

    // コードブロック（fenced）
    html = html.replace(
      /```(\w*)\r?\n([\s\S]*?)```/g,
      (_match, lang: string, code: string) => {
        const escaped = this.escapeHtml(code.trimEnd());
        const cls = lang ? ` class="language-${lang}"` : "";
        return `<pre><code${cls}>${escaped}</code></pre>`;
      },
    );

    // インラインコード
    html = html.replace(/`([^`]+)`/g, (_match, code: string) => {
      return `<code>${this.escapeHtml(code)}</code>`;
    });

    // 見出し
    html = html.replace(/^(#{1,6})\s+(.+)$/gm, (_match, hashes: string, text: string) => {
      const level = hashes.length;
      return `<h${level}>${text.trim()}</h${level}>`;
    });

    // 水平線
    html = html.replace(/^(?:---|\*\*\*|___)\s*$/gm, "<hr>");

    // 画像
    html = html.replace(
      /!\[([^\]]*)\]\(([^)]+)\)/g,
      (_match, alt: string, src: string) => {
        const safeSrc = this.sanitizeUrl(src);
        const safeAlt = this.escapeHtml(alt);
        return `<img src="${safeSrc}" alt="${safeAlt}">`;
      },
    );

    // リンク
    html = html.replace(
      /\[([^\]]+)\]\(([^)]+)\)/g,
      (_match, text: string, href: string) => {
        const safeHref = this.sanitizeUrl(href);
        return `<a href="${safeHref}">${text}</a>`;
      },
    );

    // 太字・斜体
    html = html.replace(/\*\*\*(.+?)\*\*\*/g, "<strong><em>$1</em></strong>");
    html = html.replace(/\*\*(.+?)\*\*/g, "<strong>$1</strong>");
    html = html.replace(/\*(.+?)\*/g, "<em>$1</em>");

    // 打ち消し線
    html = html.replace(/~~(.+?)~~/g, "<del>$1</del>");

    // 順序なしリスト — consecutive lines starting with -, *, +
    html = html.replace(
      /(^(?:[-*+])\s+.+$\n?)+/gm,
      (block) => {
        const items = block.trim().split("\n").map((line) =>
          `<li>${line.replace(/^[-*+]\s+/, "")}</li>`
        ).join("\n");
        return `<ul>\n${items}\n</ul>`;
      },
    );

    // 順序付きリスト — consecutive lines starting with number.
    html = html.replace(
      /(^\d+\.\s+.+$\n?)+/gm,
      (block) => {
        const items = block.trim().split("\n").map((line) =>
          `<li>${line.replace(/^\d+\.\s+/, "")}</li>`
        ).join("\n");
        return `<ol>\n${items}\n</ol>`;
      },
    );

    // 引用
    html = html.replace(/^>\s+(.+)$/gm, "<blockquote><p>$1</p></blockquote>");
    // 連続する blockquote をマージ
    html = html.replace(/<\/blockquote>\s*<blockquote><p>/g, "\n<p>");

    // 段落（空行で区切られたテキスト）
    html = html.replace(
      /^(?!<[a-z]|$)(.+)$/gm,
      (match) => {
        if (match.startsWith("<")) return match;
        return `<p>${match}</p>`;
      },
    );

    // 連続する空行を除去
    html = html.replace(/\n{3,}/g, "\n\n");

    return html.trim();
  }

  async loadDirectory(dir: string): Promise<Record<string, FrontMatterResult>> {
    const results: Record<string, FrontMatterResult> = {};

    // Deno でディレクトリを走査
    for await (const entry of Deno.readDir(dir)) {
      if (!entry.isFile || !entry.name.endsWith(".md")) continue;

      const content = await Deno.readTextFile(`${dir}/${entry.name}`);
      const slug = entry.name.replace(/\.md$/, "");
      results[slug] = this.parseFrontmatter(content);
    }

    return results;
  }

  // ── Helpers ──

  /**
   * 簡易 YAML パーサー（Front Matter 用）
   *
   * 対応: key: value, key: [a, b, c], boolean, number
   */
  private parseSimpleYaml(yaml: string): Record<string, unknown> {
    const result: Record<string, unknown> = {};
    const lines = yaml.split("\n");

    for (const line of lines) {
      const trimmed = line.trim();
      if (!trimmed || trimmed.startsWith("#")) continue;

      const colonIdx = trimmed.indexOf(":");
      if (colonIdx === -1) continue;

      const key = trimmed.substring(0, colonIdx).trim();
      const value: string = trimmed.substring(colonIdx + 1).trim();

      // 引用符除去
      if (
        (value.startsWith('"') && value.endsWith('"')) ||
        (value.startsWith("'") && value.endsWith("'"))
      ) {
        result[key] = value.slice(1, -1);
        continue;
      }

      // 配列 [a, b, c]
      if (value.startsWith("[") && value.endsWith("]")) {
        const inner = value.slice(1, -1);
        result[key] = inner.split(",").map((s) => this.parseYamlValue(s.trim()));
        continue;
      }

      // boolean / number / string
      result[key] = this.parseYamlValue(value);
    }

    return result;
  }

  private parseYamlValue(value: string): unknown {
    if (value === "true") return true;
    if (value === "false") return false;
    if (value === "null" || value === "") return null;
    if (/^-?\d+$/.test(value)) return parseInt(value, 10);
    if (/^-?\d+\.\d+$/.test(value)) return parseFloat(value);
    // 引用符除去
    if (
      (value.startsWith('"') && value.endsWith('"')) ||
      (value.startsWith("'") && value.endsWith("'"))
    ) {
      return value.slice(1, -1);
    }
    return value;
  }

  private escapeHtml(str: string): string {
    return str
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;");
  }

  private sanitizeUrl(url: string): string {
    const trimmed = url.trim();
    // Only allow http, https, mailto, and relative URLs
    if (/^(https?:|mailto:|\/|#|\.)/i.test(trimmed)) {
      return this.escapeHtmlAttr(trimmed);
    }
    // Block javascript:, data:, vbscript:, etc.
    if (/^[a-zA-Z][a-zA-Z0-9+.-]*:/.test(trimmed)) {
      return "#";
    }
    return this.escapeHtmlAttr(trimmed);
  }

  private escapeHtmlAttr(str: string): string {
    return str
      .replace(/&/g, "&amp;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;");
  }
}

// ============================================================================
// ThemeManager — テーマ管理
// ============================================================================

export class ThemeManager implements ThemeManagerInterface {
  private currentTheme: ThemeConfig | null = null;
  private templates = new Map<string, string>();
  private themePartials = new Map<string, string>();

  constructor(
    private readonly themesDir: string,
    private readonly fs: StaticFileSystemInterface,
  ) {}

  async loadTheme(name: string): Promise<ThemeConfig> {
    const themeDir = `${this.themesDir}/${name}`;

    // テーマ設定ファイルの読み込み
    const configContent = await this.fs.read(`${themeDir}/theme.json`);
    const config: ThemeConfig = configContent
      ? JSON.parse(configContent)
      : { name, directory: themeDir, templates: {}, assets: [] };

    // テンプレートファイルの読み込み
    this.templates.clear();
    const templateFiles = await this.fs.listFiles(themeDir, ".html");
    for (const file of templateFiles) {
      const content = await this.fs.read(file);
      if (content !== null) {
        const templateName = file
          .replace(themeDir + "/", "")
          .replace(/\.html$/, "");
        this.templates.set(templateName, content);
      }
    }

    // パーシャルの読み込み
    this.themePartials.clear();
    const partialDir = `${themeDir}/partials`;
    const partialFiles = await this.fs.listFiles(partialDir, ".html");
    for (const file of partialFiles) {
      const content = await this.fs.read(file);
      if (content !== null) {
        const partialName = file
          .replace(partialDir + "/", "")
          .replace(/\.html$/, "");
        this.themePartials.set(partialName, content);
      }
    }

    this.currentTheme = {
      ...config,
      name,
      directory: themeDir,
    };

    return this.currentTheme;
  }

  getTemplate(name: string): string | null {
    return this.templates.get(name) ?? null;
  }

  getPartials(): Record<string, string> {
    const result: Record<string, string> = {};
    for (const [name, content] of this.themePartials) {
      result[name] = content;
    }
    return result;
  }

  getAssets(): string[] {
    return this.currentTheme?.assets ?? [];
  }
}

// ============================================================================
// Builder — ページ HTML 構築
// ============================================================================

export class Builder implements BuilderInterface {
  constructor(private readonly renderer: TemplateRendererInterface) {}

  buildPage(
    slug: string,
    content: string,
    context: Record<string, unknown>,
  ): string {
    const ctx = { ...context, slug, content };
    return this.renderer.render(
      (context as { _template?: string })._template ?? "{{{content}}}",
      ctx,
    );
  }

  buildIndex(
    pages: Array<{ slug: string; title: string; content: string; meta: Record<string, unknown> }>,
    context: Record<string, unknown>,
  ): string {
    const sorted = [...pages].sort((a, b) => {
      const dateA = String(a.meta?.date ?? "");
      const dateB = String(b.meta?.date ?? "");
      return dateB.localeCompare(dateA);
    });

    return this.renderer.render(
      (context as { _template?: string })._template ?? "",
      { ...context, pages: sorted },
    );
  }

  buildPermalink(slug: string, context: Record<string, unknown>): string {
    const baseUrl = String((context as { baseUrl?: string }).baseUrl ?? "").replace(/\/$/, "");
    if (slug === "index") return `${baseUrl}/`;
    return `${baseUrl}/${slug}/`;
  }

  buildMetaTags(context: Record<string, unknown>): string {
    const page = context.page as Record<string, unknown> | undefined;
    const site = context.site as Record<string, unknown> | undefined;
    const meta = (page?.meta ?? {}) as Record<string, unknown>;

    const tags: string[] = [];
    const desc = String(meta.description ?? site?.description ?? "");
    const title = String(meta.title ?? page?.title ?? site?.title ?? "");

    if (desc) {
      tags.push(`<meta name="description" content="${this.escapeAttr(desc)}">`);
    }
    if (meta.keywords) {
      tags.push(`<meta name="keywords" content="${this.escapeAttr(String(meta.keywords))}">`);
    }

    // OGP
    tags.push(`<meta property="og:title" content="${this.escapeAttr(title)}">`);
    if (desc) {
      tags.push(`<meta property="og:description" content="${this.escapeAttr(desc)}">`);
    }
    if (meta.image) {
      tags.push(`<meta property="og:image" content="${this.escapeAttr(String(meta.image))}">`);
    }

    return tags.join("\n");
  }

  private escapeAttr(str: string): string {
    return str
      .replace(/&/g, "&amp;")
      .replace(/"/g, "&quot;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;");
  }
}
