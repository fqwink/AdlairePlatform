/**
 * Adlaire Infrastructure Services (AIS) — Core Module
 *
 * アプリケーションコンテキスト、i18n、サービスコンテナ、
 * イベントディスパッチャを提供する。
 * PHP AIS.Core.php からの移植。
 *
 * @package AIS
 * @version 2.0.0
 * @license Adlaire License Ver.2.0
 */

import type { AdlaireClient, LocaleId, TranslationDict } from "../types.ts";

import type {
  AppContextInterface,
  AppContextPaths,
  HostInfo,
  I18nInterface,
} from "./AIS.Interface.ts";

// ============================================================================
// AppContext — アプリケーション設定コンテキスト
// ============================================================================

export class AppContext implements AppContextInterface, AppContextPaths {
  private data: Record<string, unknown> = {};
  private readonly basePath: string;

  constructor(basePath: string, config?: Record<string, unknown>) {
    this.basePath = basePath.replace(/\/$/, "");
    if (config) {
      this.data = { ...config };
    }
  }

  get<T = unknown>(key: string, defaultValue?: T): T {
    const keys = key.split(".");
    let current: unknown = this.data;

    for (const k of keys) {
      if (current === null || current === undefined || typeof current !== "object") {
        return (defaultValue ?? undefined) as T;
      }
      current = (current as Record<string, unknown>)[k];
    }

    return (current ?? defaultValue ?? undefined) as T;
  }

  set(key: string, value: unknown): void {
    const keys = key.split(".");
    let current: Record<string, unknown> = this.data;

    for (let i = 0; i < keys.length - 1; i++) {
      const k = keys[i];
      if (typeof current[k] !== "object" || current[k] === null) {
        current[k] = {};
      }
      current = current[k] as Record<string, unknown>;
    }

    current[keys[keys.length - 1]] = value;
  }

  has(key: string): boolean {
    const keys = key.split(".");
    let current: unknown = this.data;

    for (const k of keys) {
      if (current === null || current === undefined || typeof current !== "object") {
        return false;
      }
      if (!(k in (current as Record<string, unknown>))) {
        return false;
      }
      current = (current as Record<string, unknown>)[k];
    }
    return true;
  }

  all(): Record<string, unknown> {
    return { ...this.data };
  }

  /**
   * ACS 経由でファイルを読み込む。ASS PHP サーバに委譲。
   * path は "dir/filename" 形式（例: "settings/site.json"）
   */
  async loadFromFile(path: string, client?: AdlaireClient): Promise<void> {
    if (client) {
      const parts = path.replace(/^\.\/data\//, "").split("/");
      const file = parts.pop() ?? path;
      const dir = parts.join("/") || "settings";
      const data = await client.storage.read<Record<string, unknown>>(file, dir);
      if (data) {
        this.data = { ...this.data, ...data };
      }
    } else {
      // フォールバック: Deno 直接アクセス（開発時のみ）
      const content = await Deno.readTextFile(path);
      const parsed = JSON.parse(content);
      this.data = { ...this.data, ...parsed };
    }
  }

  /**
   * ACS 経由でファイルを書き込む。ASS PHP サーバに委譲。
   */
  async saveToFile(path: string, client?: AdlaireClient): Promise<void> {
    if (client) {
      const parts = path.replace(/^\.\/data\//, "").split("/");
      const file = parts.pop() ?? path;
      const dir = parts.join("/") || "settings";
      await client.storage.write(file, this.data, dir);
    } else {
      // フォールバック: Deno 直接アクセス（開発時のみ）
      await Deno.writeTextFile(path, JSON.stringify(this.data, null, 2));
    }
  }

  validate(schema: Record<string, string>): string[] {
    const errors: string[] = [];

    for (const [key, rule] of Object.entries(schema)) {
      const value = this.get(key);
      const rules = rule.split("|");

      for (const r of rules) {
        if (r === "required" && (value === undefined || value === null || value === "")) {
          errors.push(`${key} is required`);
        }
        if (r === "string" && value !== undefined && typeof value !== "string") {
          errors.push(`${key} must be a string`);
        }
        if (r === "number" && value !== undefined && typeof value !== "number") {
          errors.push(`${key} must be a number`);
        }
        if (r === "boolean" && value !== undefined && typeof value !== "boolean") {
          errors.push(`${key} must be a boolean`);
        }
      }
    }

    return errors;
  }

  dataDir(): string {
    return `${this.basePath}/data`;
  }

  settingsDir(): string {
    return `${this.basePath}/data/settings`;
  }

  contentDir(): string {
    return `${this.basePath}/data/content`;
  }

  /**
   * ホスト情報を解決する
   */
  static resolveHost(headers?: Record<string, string>): HostInfo {
    const host = headers?.["host"] ?? headers?.["x-forwarded-host"] ?? "localhost";
    const rp = headers?.["x-forwarded-prefix"] ?? "";
    return { host, rp };
  }
}

// ============================================================================
// I18n — 国際化
// ============================================================================

export class I18n implements I18nInterface {
  private locale: LocaleId = "ja";
  private translations: Record<string, string> = {};
  private nestedTranslations: TranslationDict = {};

  /**
   * ACS 経由で翻訳ファイルを読み込む。ASS PHP サーバに委譲。
   */
  async init(basePath?: string, client?: AdlaireClient): Promise<void> {
    const file = `${this.locale}.json`;

    try {
      if (client) {
        const data = await client.storage.read<TranslationDict>(file, "settings");
        this.nestedTranslations = data ?? {};
      } else {
        // フォールバック: Deno 直接アクセス（開発時のみ）
        const dir = basePath ?? "./locales";
        const content = await Deno.readTextFile(`${dir}/${file}`);
        this.nestedTranslations = JSON.parse(content);
      }
      this.translations = this.flattenDict(this.nestedTranslations);
    } catch {
      this.translations = {};
      this.nestedTranslations = {};
    }
  }

  getLocale(): LocaleId {
    return this.locale;
  }

  setLocale(locale: LocaleId): void {
    this.locale = locale;
  }

  t(key: string, params?: Record<string, string>): string {
    let text = this.translations[key] ?? key;

    if (params) {
      for (const [k, v] of Object.entries(params)) {
        const escaped = k.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
        text = text.replace(new RegExp(`\\{${escaped}\\}`, "g"), String(v));
      }
    }

    return text;
  }

  all(): Record<string, string> {
    return { ...this.translations };
  }

  allNested(): TranslationDict {
    return { ...this.nestedTranslations };
  }

  htmlLang(): string {
    return this.locale;
  }

  private flattenDict(
    dict: TranslationDict,
    prefix: string = "",
  ): Record<string, string> {
    const result: Record<string, string> = {};

    for (const [key, value] of Object.entries(dict)) {
      const fullKey = prefix ? `${prefix}.${key}` : key;
      if (typeof value === "string") {
        result[fullKey] = value;
      } else {
        Object.assign(result, this.flattenDict(value, fullKey));
      }
    }

    return result;
  }
}

