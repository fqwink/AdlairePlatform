/**
 * Adlaire Static Generator (ASG) — Data Models & Enums
 *
 * ASG 固有の列挙型・データモデルを定義する。
 *
 * @package ASG
 * @version 2.0.0
 * @license Adlaire License Ver.2.0
 */

import type { BuildStatusValue } from "../types.ts";

// ============================================================================
// Build Status Enum
// ============================================================================

/**
 * ビルドステータス列挙クラス
 *
 * PHP: `enum BuildStatus: string`
 */
export class BuildStatus {
  static readonly PENDING = new BuildStatus("pending");
  static readonly BUILDING = new BuildStatus("building");
  static readonly COMPLETE = new BuildStatus("complete");
  static readonly ERROR = new BuildStatus("error");

  private static readonly ALL = [
    BuildStatus.PENDING,
    BuildStatus.BUILDING,
    BuildStatus.COMPLETE,
    BuildStatus.ERROR,
  ];

  private constructor(public readonly value: BuildStatusValue) {}

  isFinished(): boolean {
    return this.value === "complete" || this.value === "error";
  }

  isSuccess(): boolean {
    return this.value === "complete";
  }

  static from(name: string): BuildStatus {
    const found = BuildStatus.ALL.find((s) => s.value === name.toLowerCase());
    if (!found) {
      throw new Error(`Unknown build status: ${name}`);
    }
    return found;
  }

  toString(): string {
    return this.value;
  }
}

// ============================================================================
// Build Mode
// ============================================================================

/**
 * ビルドモード — フル or 差分
 */
export class BuildMode {
  static readonly FULL = new BuildMode("full");
  static readonly DIFF = new BuildMode("diff");

  private constructor(public readonly value: "full" | "diff") {}

  toString(): string {
    return this.value;
  }
}

// ============================================================================
// URL Style
// ============================================================================

/**
 * URL スタイル — クリーン URL or 拡張子付き
 */
export class UrlStyle {
  static readonly CLEAN = new UrlStyle(true);
  static readonly WITH_EXTENSION = new UrlStyle(false);

  private constructor(public readonly cleanUrls: boolean) {}

  resolveOutputPath(slug: string): string {
    if (slug === "index") {
      return "index.html";
    }
    return this.cleanUrls ? `${slug}/index.html` : `${slug}.html`;
  }

  buildUrl(slug: string, baseUrl: string): string {
    const base = baseUrl.replace(/\/$/, "");
    if (slug === "index") {
      return `${base}/`;
    }
    return this.cleanUrls ? `${base}/${slug}/` : `${base}/${slug}.html`;
  }
}

// ============================================================================
// Errors
// ============================================================================

/**
 * ビルドエラー
 */
export class BuildError extends Error {
  constructor(
    message: string,
    public readonly slug?: string,
    public readonly phase?: "template" | "markdown" | "asset" | "deploy",
  ) {
    super(message);
    this.name = "BuildError";
  }
}

/**
 * テンプレートエラー
 */
export class TemplateError extends Error {
  constructor(
    message: string,
    public readonly templateName?: string,
    public readonly line?: number,
  ) {
    super(message);
    this.name = "TemplateError";
  }
}

/**
 * テーマエラー
 */
export class ThemeError extends Error {
  constructor(
    message: string,
    public readonly themeName: string,
  ) {
    super(message);
    this.name = "ThemeError";
  }
}

// ============================================================================
// Constants
// ============================================================================

/** デフォルトの画像最大寸法 */
export const IMAGE_DEFAULTS = {
  MAX_WIDTH: 1920,
  MAX_HEIGHT: 1920,
  THUMB_WIDTH: 400,
  THUMB_HEIGHT: 400,
  JPEG_QUALITY: 85,
  WEBP_QUALITY: 80,
  MAX_FILE_SIZE: 52_428_800, // 50MB
} as const;

/** パーシャル展開の最大ネスト深度 */
export const PARTIAL_MAX_DEPTH = 10;
