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

import type {
  SiteSettings,
  LocaleId,
  TranslationDict,
} from "../types.ts";

import type {
  AppContextInterface,
  AppContextPaths,
  HostInfo,
  I18nInterface,
  ServiceProviderInterface,
  ServiceContainerInterface,
} from "./AIS.Interface.ts";

import { ConfigValidationError } from "./AIS.Class.ts";

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

  async loadFromFile(path: string): Promise<void> {
    const content = await Deno.readTextFile(path);
    const parsed = JSON.parse(content);
    this.data = { ...this.data, ...parsed };
  }

  async saveToFile(path: string): Promise<void> {
    await Deno.writeTextFile(path, JSON.stringify(this.data, null, 2));
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

  async init(basePath?: string): Promise<void> {
    const dir = basePath ?? "./locales";
    const file = `${dir}/${this.locale}.json`;

    try {
      const content = await Deno.readTextFile(file);
      this.nestedTranslations = JSON.parse(content);
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
        text = text.replace(new RegExp(`\\{${k}\\}`, "g"), v);
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

// ============================================================================
// ServiceContainer — サービスコンテナ
// ============================================================================

export class ServiceContainer implements ServiceContainerInterface {
  private factories = new Map<string, (...args: unknown[]) => unknown>();
  private singletons = new Map<string, unknown>();
  private isSingleton = new Set<string>();
  private providers: ServiceProviderInterface[] = [];

  bind(name: string, factory: (...args: unknown[]) => unknown): void {
    this.factories.set(name, factory);
    this.isSingleton.delete(name);
    this.singletons.delete(name);
  }

  singleton(name: string, factory: (...args: unknown[]) => unknown): void {
    this.factories.set(name, factory);
    this.isSingleton.add(name);
  }

  make<T = unknown>(name: string): T {
    if (this.singletons.has(name)) {
      return this.singletons.get(name) as T;
    }

    const factory = this.factories.get(name);
    if (!factory) {
      throw new Error(`No binding for: ${name}`);
    }

    const instance = factory();

    if (this.isSingleton.has(name)) {
      this.singletons.set(name, instance);
    }

    return instance as T;
  }

  has(name: string): boolean {
    return this.factories.has(name);
  }

  register(provider: ServiceProviderInterface): void {
    this.providers.push(provider);
    provider.register();
  }

  boot(): void {
    for (const provider of this.providers) {
      provider.boot();
    }
  }
}

// ============================================================================
// EventDispatcher — イベントディスパッチャ
// ============================================================================

export class EventDispatcher {
  private listeners = new Map<string, Array<{
    fn: (data: Record<string, unknown>) => unknown;
    priority: number;
  }>>();

  listen(
    event: string,
    listener: (data: Record<string, unknown>) => unknown,
    priority: number = 0,
  ): void {
    if (!this.listeners.has(event)) {
      this.listeners.set(event, []);
    }
    const list = this.listeners.get(event)!;
    list.push({ fn: listener, priority });
    list.sort((a, b) => b.priority - a.priority);
  }

  dispatch(event: string, data?: Record<string, unknown>): unknown[] {
    const list = this.listeners.get(event);
    if (!list) return [];
    return list.map(({ fn }) => fn(data ?? {}));
  }

  hasListeners(event: string): boolean {
    return (this.listeners.get(event)?.length ?? 0) > 0;
  }

  removeListener(
    event: string,
    listener: (data: Record<string, unknown>) => unknown,
  ): void {
    const list = this.listeners.get(event);
    if (!list) return;
    const idx = list.findIndex((entry) => entry.fn === listener);
    if (idx !== -1) list.splice(idx, 1);
  }
}
