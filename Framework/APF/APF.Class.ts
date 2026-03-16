/**
 * Adlaire Platform Foundation (APF) — Data Models & Enums
 *
 * APF 固有の列挙型・データモデルクラスを定義する。
 * FRAMEWORK_RULEBOOK: PHP enum → static readonly クラスパターン
 *
 * @package APF
 * @version 2.0.0
 * @license Adlaire License Ver.2.0
 */

import type { HttpMethodValue, LogLevelValue } from "../types.ts";

// ============================================================================
// HttpMethod Enum
// ============================================================================

/**
 * HTTP メソッド列挙クラス
 *
 * PHP: `enum HttpMethod: string`
 * TS:  `class HttpMethod { static readonly GET = ... }`
 */
export class HttpMethod {
  static readonly GET = new HttpMethod("GET");
  static readonly POST = new HttpMethod("POST");
  static readonly PUT = new HttpMethod("PUT");
  static readonly PATCH = new HttpMethod("PATCH");
  static readonly DELETE = new HttpMethod("DELETE");
  static readonly HEAD = new HttpMethod("HEAD");
  static readonly OPTIONS = new HttpMethod("OPTIONS");

  private static readonly ALL = [
    HttpMethod.GET,
    HttpMethod.POST,
    HttpMethod.PUT,
    HttpMethod.PATCH,
    HttpMethod.DELETE,
    HttpMethod.HEAD,
    HttpMethod.OPTIONS,
  ];

  private constructor(public readonly value: HttpMethodValue) {}

  isSafe(): boolean {
    return this.value === "GET" || this.value === "HEAD" || this.value === "OPTIONS";
  }

  isIdempotent(): boolean {
    return this.isSafe() || this.value === "PUT" || this.value === "DELETE";
  }

  static from(name: string): HttpMethod {
    const upper = name.toUpperCase();
    const found = HttpMethod.ALL.find((m) => m.value === upper);
    if (!found) {
      throw new Error(`Unknown HTTP method: ${name}`);
    }
    return found;
  }

  toString(): string {
    return this.value;
  }
}

// ============================================================================
// LogLevel Enum
// ============================================================================

/**
 * ログレベル列挙クラス
 *
 * PHP: `enum LogLevel: int`
 */
export class LogLevel {
  static readonly DEBUG = new LogLevel("debug", 0);
  static readonly INFO = new LogLevel("info", 1);
  static readonly WARNING = new LogLevel("warning", 2);
  static readonly ERROR = new LogLevel("error", 3);
  static readonly CRITICAL = new LogLevel("critical", 4);

  private static readonly ALL = [
    LogLevel.DEBUG,
    LogLevel.INFO,
    LogLevel.WARNING,
    LogLevel.ERROR,
    LogLevel.CRITICAL,
  ];

  private constructor(
    public readonly value: LogLevelValue,
    public readonly severity: number,
  ) {}

  isAtLeast(other: LogLevel): boolean {
    return this.severity >= other.severity;
  }

  label(): string {
    return this.value.toUpperCase();
  }

  static fromName(name: string): LogLevel {
    const lower = name.toLowerCase();
    const found = LogLevel.ALL.find((l) => l.value === lower);
    if (!found) {
      throw new Error(`Unknown log level: ${name}`);
    }
    return found;
  }

  toString(): string {
    return this.value;
  }
}

// ============================================================================
// Errors
// ============================================================================

/**
 * フレームワーク基底エラー
 */
export class FrameworkError extends Error {
  constructor(
    message: string,
    public readonly code: string = "FRAMEWORK_ERROR",
  ) {
    super(message);
    this.name = "FrameworkError";
  }
}

/**
 * バリデーションエラー
 */
export class ValidationError extends FrameworkError {
  constructor(
    message: string,
    public readonly errors: Record<string, string[]>,
  ) {
    super(message, "VALIDATION_ERROR");
    this.name = "ValidationError";
  }
}

/**
 * Not Found エラー
 */
export class NotFoundError extends FrameworkError {
  constructor(message: string = "Not Found") {
    super(message, "NOT_FOUND");
    this.name = "NotFoundError";
  }
}
