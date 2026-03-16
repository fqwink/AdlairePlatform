/**
 * Adlaire Platform Foundation (APF) — Utilities Module
 *
 * 文字列操作、配列操作、設定管理、ファイルシステム、バリデーション、
 * キャッシュなどの汎用ユーティリティを提供する。
 * PHP APF.Utilities.php からの移植。
 *
 * @package APF
 * @version 2.0.0
 * @license Adlaire License Ver.2.0
 */

import type { ValidationErrors, ValidationRules } from "../types.ts";

import type {
  ConfigInterface,
  FileSystemInterface,
  JsonStorageInterface,
  ValidatorInterface,
} from "./APF.Interface.ts";

import { ValidationError } from "./APF.Class.ts";

// ============================================================================
// Str — 文字列ユーティリティ
// ============================================================================

export class Str {
  /**
   * 文字列をスラッグ化する
   *
   * PHP: `Str::slug()` 互換
   */
  static slug(input: string): string {
    return input
      .toLowerCase()
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .replace(/[^a-z0-9\s-]/g, "")
      .replace(/[\s_]+/g, "-")
      .replace(/-+/g, "-")
      .replace(/^-|-$/g, "");
  }

  /**
   * パスを安全に正規化する（ディレクトリトラバーサル防止）
   */
  static safePath(path: string): string {
    // Remove null bytes and backslashes
    let safe = path.replace(/\0/g, "").replace(/\\/g, "/");
    // Decode percent-encoded sequences to catch %2e%2e etc.
    try {
      safe = decodeURIComponent(safe);
    } catch {
      // Invalid encoding — use as-is
    }
    // Normalize slashes and split into segments
    const segments = safe.split("/").filter((s) => s !== "" && s !== ".");
    // Reject any segment that is ".."
    const result = segments.filter((s) => s !== "..");
    return result.join("/");
  }

  static startsWith(haystack: string, needle: string): boolean {
    return haystack.startsWith(needle);
  }

  static endsWith(haystack: string, needle: string): boolean {
    return haystack.endsWith(needle);
  }

  static contains(haystack: string, needle: string): boolean {
    return haystack.includes(needle);
  }

  /**
   * 暗号学的に安全なランダム文字列を生成する
   */
  static random(length: number = 32): string {
    const chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
    const array = new Uint8Array(length);
    crypto.getRandomValues(array);
    return Array.from(array, (b) => chars[b % chars.length]).join("");
  }

  /**
   * 文字列を指定文字数で切り詰める
   */
  static limit(input: string, max: number = 100, end: string = "..."): string {
    if (input.length <= max) return input;
    if (end.length >= max) return input.substring(0, max);
    return input.substring(0, max - end.length) + end;
  }

  /**
   * camelCase に変換
   */
  static camel(input: string): string {
    return input
      .replace(/[-_\s]+(.)?/g, (_, c: string | undefined) => c ? c.toUpperCase() : "")
      .replace(/^./, (c) => c.toLowerCase());
  }

  /**
   * snake_case に変換
   */
  static snake(input: string): string {
    return input
      .replace(/([A-Z])/g, "_$1")
      .replace(/[-\s]+/g, "_")
      .toLowerCase()
      .replace(/^_/, "");
  }

  /**
   * kebab-case に変換
   */
  static kebab(input: string): string {
    return input
      .replace(/([A-Z])/g, "-$1")
      .replace(/[_\s]+/g, "-")
      .toLowerCase()
      .replace(/^-/, "");
  }

  /**
   * Title Case に変換
   */
  static title(input: string): string {
    return input
      .replace(/[-_]+/g, " ")
      .replace(/\b\w/g, (c) => c.toUpperCase());
  }
}

// ============================================================================
// Arr — 配列ユーティリティ
// ============================================================================

export class Arr {
  /**
   * ドット記法でネストされた値を取得する
   *
   * PHP: `Arr::get($array, 'user.name')` 互換
   */
  static get<T = unknown>(
    obj: Record<string, unknown>,
    key: string,
    defaultValue?: T,
  ): T {
    const keys = key.split(".");
    let current: unknown = obj;

    for (const k of keys) {
      if (current === null || current === undefined || typeof current !== "object") {
        return (defaultValue ?? undefined) as T;
      }
      current = (current as Record<string, unknown>)[k];
    }

    return (current ?? defaultValue ?? undefined) as T;
  }

  /**
   * ドット記法でネストされた値をセットする
   */
  static set(obj: Record<string, unknown>, key: string, value: unknown): void {
    const keys = key.split(".");
    let current: Record<string, unknown> = obj;

    for (let i = 0; i < keys.length - 1; i++) {
      const k = keys[i];
      if (typeof current[k] !== "object" || current[k] === null) {
        current[k] = {};
      }
      current = current[k] as Record<string, unknown>;
    }

    current[keys[keys.length - 1]] = value;
  }

  /**
   * ドット記法でキーが存在するか確認する
   */
  static has(obj: Record<string, unknown>, key: string): boolean {
    const keys = key.split(".");
    let current: unknown = obj;

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

  /**
   * 指定キーのみ抽出する
   */
  static only<T extends Record<string, unknown>>(
    obj: T,
    keys: string[],
  ): Partial<T> {
    const result: Record<string, unknown> = {};
    for (const key of keys) {
      if (key in obj) {
        result[key] = obj[key];
      }
    }
    return result as Partial<T>;
  }

  /**
   * 指定キーを除外する
   */
  static except<T extends Record<string, unknown>>(
    obj: T,
    keys: string[],
  ): Partial<T> {
    const result = { ...obj } as Record<string, unknown>;
    for (const key of keys) {
      delete result[key];
    }
    return result as Partial<T>;
  }

  /**
   * ネストされた配列をフラットにする
   */
  static flatten(arr: unknown[]): unknown[] {
    return arr.flat(Infinity);
  }

  /**
   * 配列のオブジェクトから指定キーの値を抽出する
   */
  static pluck<T = unknown>(
    arr: Record<string, unknown>[],
    key: string,
  ): T[] {
    return arr.map((item) => Arr.get<T>(item, key));
  }

  /**
   * コールバックでフィルタリングする
   */
  static where<T>(arr: T[], callback: (item: T) => boolean): T[] {
    return arr.filter(callback);
  }
}

// ============================================================================
// Config — 設定管理
// ============================================================================

export class Config implements ConfigInterface {
  private data: Record<string, unknown> = {};
  private cache = new Map<string, unknown>();

  get<T = unknown>(key: string, defaultValue?: T): T {
    if (this.cache.has(key)) {
      return this.cache.get(key) as T;
    }
    const value = Arr.get<T>(this.data, key, defaultValue);
    this.cache.set(key, value);
    return value;
  }

  setDefaults(defaults: Record<string, unknown>): void {
    this.data = { ...defaults, ...this.data };
    this.clearCache();
  }

  clearCache(): void {
    this.cache.clear();
  }

  /** 設定値をセットする */
  set(key: string, value: unknown): void {
    Arr.set(this.data, key, value);
    this.cache.clear();
  }

  /** 全設定を返す */
  all(): Record<string, unknown> {
    return { ...this.data };
  }
}

// ============================================================================
// Validator — バリデーション
// ============================================================================

/**
 * バリデータ
 *
 * PHP: `Validator::make($data, $rules)` 互換
 *
 * ルール構文: `'required|string|max:255|min:1'`
 */
export class Validator implements ValidatorInterface {
  private readonly data: Record<string, unknown>;
  private readonly rules: ValidationRules;
  private readonly messages: Record<string, string>;
  private validationErrors: ValidationErrors = {};
  private validated = false;

  constructor(
    data: Record<string, unknown>,
    rules: ValidationRules,
    messages: Record<string, string> = {},
  ) {
    this.data = data;
    this.rules = rules;
    this.messages = messages;
  }

  static make(
    data: Record<string, unknown>,
    rules: ValidationRules,
    messages?: Record<string, string>,
  ): Validator {
    return new Validator(data, rules, messages);
  }

  /**
   * バリデーション対象のデータを検証して返す（失敗時は例外）
   */
  static validateOrThrow(
    data: Record<string, unknown>,
    rules: ValidationRules,
    messages?: Record<string, string>,
  ): Record<string, unknown> {
    const v = new Validator(data, rules, messages);
    if (!v.validate()) {
      throw new ValidationError("Validation failed", v.errors());
    }
    return data;
  }

  validate(): boolean {
    this.validationErrors = {};

    for (const [field, ruleStr] of Object.entries(this.rules)) {
      const ruleList = ruleStr.split("|");
      const value = this.data[field];

      for (const rule of ruleList) {
        const [ruleName, ruleParam] = rule.split(":");
        const error = this.checkRule(field, value, ruleName, ruleParam);
        if (error) {
          if (!this.validationErrors[field]) {
            this.validationErrors[field] = [];
          }
          this.validationErrors[field].push(error);
        }
      }
    }

    this.validated = true;
    return Object.keys(this.validationErrors).length === 0;
  }

  fails(): boolean {
    if (!this.validated) this.validate();
    return Object.keys(this.validationErrors).length > 0;
  }

  errors(): ValidationErrors {
    if (!this.validated) this.validate();
    return { ...this.validationErrors };
  }

  first(field: string): string | null {
    const errs = this.errors()[field];
    return errs && errs.length > 0 ? errs[0] : null;
  }

  hasError(field: string): boolean {
    return (this.errors()[field]?.length ?? 0) > 0;
  }

  private checkRule(
    field: string,
    value: unknown,
    rule: string,
    param?: string,
  ): string | null {
    const customKey = `${field}.${rule}`;
    const getMessage = (defaultMsg: string): string => this.messages[customKey] ?? defaultMsg;

    switch (rule) {
      case "required":
        if (value === undefined || value === null || value === "") {
          return getMessage(`${field} is required`);
        }
        break;
      case "string":
        if (value !== undefined && value !== null && typeof value !== "string") {
          return getMessage(`${field} must be a string`);
        }
        break;
      case "number":
      case "numeric":
        if (
          value !== undefined && value !== null && typeof value !== "number" && isNaN(Number(value))
        ) {
          return getMessage(`${field} must be a number`);
        }
        break;
      case "boolean":
        if (value !== undefined && value !== null && typeof value !== "boolean") {
          return getMessage(`${field} must be a boolean`);
        }
        break;
      case "email":
        if (typeof value === "string" && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
          return getMessage(`${field} must be a valid email`);
        }
        break;
      case "min":
        if (param && typeof value === "string" && value.length < Number(param)) {
          return getMessage(`${field} must be at least ${param} characters`);
        }
        if (param && typeof value === "number" && value < Number(param)) {
          return getMessage(`${field} must be at least ${param}`);
        }
        break;
      case "max":
        if (param && typeof value === "string" && value.length > Number(param)) {
          return getMessage(`${field} must not exceed ${param} characters`);
        }
        if (param && typeof value === "number" && value > Number(param)) {
          return getMessage(`${field} must not exceed ${param}`);
        }
        break;
      case "in":
        if (param && value !== undefined) {
          const allowed = param.split(",");
          if (!allowed.includes(String(value))) {
            return getMessage(`${field} must be one of: ${param}`);
          }
        }
        break;
      case "url":
        if (typeof value === "string") {
          try {
            new URL(value);
          } catch {
            return getMessage(`${field} must be a valid URL`);
          }
        }
        break;
      case "slug":
        if (typeof value === "string" && !/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(value)) {
          return getMessage(`${field} must be a valid slug`);
        }
        break;
    }

    return null;
  }
}

// ============================================================================
// Security — セキュリティヘルパー
// ============================================================================

export class Security {
  /**
   * HTML 特殊文字をエスケープする
   */
  static escape(value: string): string {
    return value
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  /**
   * HTML タグを除去する
   */
  static sanitize(value: string): string {
    return value.replace(/<[^>]*>/g, "");
  }

  /**
   * 暗号学的に安全なランダム文字列を生成する
   */
  static randomString(length: number = 32): string {
    return Str.random(length);
  }

  /**
   * CSRF トークンを生成する
   */
  static generateCsrfToken(): string {
    return Str.random(64);
  }

  /**
   * SHA-256 ハッシュを計算する
   */
  static async sha256(input: string): Promise<string> {
    const data = new TextEncoder().encode(input);
    const hashBuffer = await crypto.subtle.digest("SHA-256", data);
    const hashArray = Array.from(new Uint8Array(hashBuffer));
    return hashArray.map((b) => b.toString(16).padStart(2, "0")).join("");
  }
}

// ============================================================================
// FileSystem — ファイル操作（Deno 環境用）
// ============================================================================

export class FileSystem implements FileSystemInterface {
  async read(path: string): Promise<string | null> {
    try {
      return await Deno.readTextFile(path);
    } catch {
      return null;
    }
  }

  async write(path: string, content: string): Promise<boolean> {
    try {
      await Deno.writeTextFile(path, content);
      return true;
    } catch {
      return false;
    }
  }

  async readJson<T = unknown>(path: string): Promise<T | null> {
    const text = await this.read(path);
    if (text === null) return null;
    try {
      return JSON.parse(text) as T;
    } catch {
      return null;
    }
  }

  writeJson(path: string, data: unknown): Promise<boolean> {
    const json = JSON.stringify(data, null, 2);
    return this.write(path, json);
  }

  async exists(path: string): Promise<boolean> {
    try {
      await Deno.stat(path);
      return true;
    } catch {
      return false;
    }
  }

  async delete(path: string): Promise<boolean> {
    try {
      await Deno.remove(path);
      return true;
    } catch {
      return false;
    }
  }

  async ensureDir(dir: string): Promise<boolean> {
    try {
      await Deno.mkdir(dir, { recursive: true });
      return true;
    } catch {
      return false;
    }
  }
}

// ============================================================================
// JsonStorage — JSON ファイルストレージ（Deno 環境用）
// ============================================================================

export class JsonStorage implements JsonStorageInterface {
  private cache = new Map<string, unknown>();
  private readonly baseDir: string;

  constructor(baseDir: string) {
    this.baseDir = baseDir.replace(/\/$/, "");
  }

  async read<T = unknown>(file: string, dir?: string): Promise<T> {
    const path = this.resolvePath(file, dir);

    const cached = this.cache.get(path);
    if (cached !== undefined) return JSON.parse(JSON.stringify(cached)) as T;

    try {
      const text = await Deno.readTextFile(path);
      const data = JSON.parse(text) as T;
      this.cache.set(path, data);
      return data;
    } catch {
      const empty = {} as T;
      return empty;
    }
  }

  async write(file: string, data: unknown, dir?: string): Promise<void> {
    const path = this.resolvePath(file, dir);
    const json = JSON.stringify(data, null, 2);
    await Deno.writeTextFile(path, json);
    this.cache.set(path, JSON.parse(JSON.stringify(data)));
  }

  clearCache(): void {
    this.cache.clear();
  }

  private resolvePath(file: string, dir?: string): string {
    if (dir) {
      return `${this.baseDir}/${dir}/${file}`;
    }
    return `${this.baseDir}/${file}`;
  }
}
