/**
 * Adlaire Infrastructure Services (AIS) — Data Models & Enums
 *
 * AIS 固有の列挙型・データモデルを定義する。
 *
 * @package AIS
 * @version 2.0.0
 * @license Adlaire License Ver.2.0
 */

// ============================================================================
// Diagnostics Level
// ============================================================================

export class DiagnosticsLevel {
  static readonly BASIC = new DiagnosticsLevel("basic", 0);
  static readonly EXTENDED = new DiagnosticsLevel("extended", 1);
  static readonly DEBUG = new DiagnosticsLevel("debug", 2);

  private static readonly ALL = [
    DiagnosticsLevel.BASIC,
    DiagnosticsLevel.EXTENDED,
    DiagnosticsLevel.DEBUG,
  ];

  private constructor(
    public readonly value: "basic" | "extended" | "debug",
    public readonly verbosity: number,
  ) {}

  includes(other: DiagnosticsLevel): boolean {
    return this.verbosity >= other.verbosity;
  }

  static from(name: string): DiagnosticsLevel {
    const found = DiagnosticsLevel.ALL.find((l) => l.value === name.toLowerCase());
    if (!found) {
      throw new Error(`Unknown diagnostics level: ${name}`);
    }
    return found;
  }

  toString(): string {
    return this.value;
  }
}

// ============================================================================
// Health Status
// ============================================================================

export class HealthStatus {
  static readonly OK = new HealthStatus("ok");
  static readonly DEGRADED = new HealthStatus("degraded");
  static readonly ERROR = new HealthStatus("error");

  private constructor(public readonly value: "ok" | "degraded" | "error") {}

  isHealthy(): boolean {
    return this.value === "ok";
  }

  toString(): string {
    return this.value;
  }
}

// ============================================================================
// Errors
// ============================================================================

/**
 * インフラストラクチャエラー
 */
export class InfrastructureError extends Error {
  constructor(
    message: string,
    public readonly code: string = "INFRA_ERROR",
  ) {
    super(message);
    this.name = "InfrastructureError";
  }
}

/**
 * Git 操作エラー
 */
export class GitError extends InfrastructureError {
  constructor(
    message: string,
    public readonly command?: string,
    public readonly output?: string,
  ) {
    super(message, "GIT_ERROR");
    this.name = "GitError";
  }
}

/**
 * アップデートエラー
 */
export class UpdateError extends InfrastructureError {
  constructor(
    message: string,
    public readonly fromVersion?: string,
    public readonly toVersion?: string,
  ) {
    super(message, "UPDATE_ERROR");
    this.name = "UpdateError";
  }
}

/**
 * 設定検証エラー
 */
export class ConfigValidationError extends InfrastructureError {
  constructor(
    message: string,
    public readonly errors: string[],
  ) {
    super(message, "CONFIG_VALIDATION_ERROR");
    this.name = "ConfigValidationError";
  }
}
