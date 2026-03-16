/**
 * Adlaire Client Services (ACS) — Data Models & Enums
 *
 * ACS 固有のデータモデル・列挙型を定義する。
 *
 * @package ACS
 * @version 2.0.0
 * @license Adlaire License Ver.2.0
 */

// ============================================================================
// Connection State
// ============================================================================

export class ConnectionState {
  static readonly DISCONNECTED = new ConnectionState("disconnected");
  static readonly CONNECTING = new ConnectionState("connecting");
  static readonly CONNECTED = new ConnectionState("connected");
  static readonly RECONNECTING = new ConnectionState("reconnecting");
  static readonly ERROR = new ConnectionState("error");

  private constructor(
    public readonly value: "disconnected" | "connecting" | "connected" | "reconnecting" | "error",
  ) {}

  isActive(): boolean {
    return this.value === "connected" || this.value === "reconnecting";
  }

  toString(): string {
    return this.value;
  }
}

// ============================================================================
// Storage Directory Constants
// ============================================================================

/**
 * サーバ側ストレージディレクトリ名
 *
 * ASS の PHP ファイルシステム上のディレクトリに対応。
 */
export const STORAGE_DIRS = {
  SETTINGS: "settings",
  CONTENT: "content",
  COLLECTIONS: "collections",
  UPLOADS: "uploads",
  CACHE: "cache",
  BACKUPS: "backups",
  LOGS: "logs",
} as const;

export type StorageDirectory = (typeof STORAGE_DIRS)[keyof typeof STORAGE_DIRS];

// ============================================================================
// API Endpoints
// ============================================================================

/**
 * ASS サーバのエンドポイントマッピング
 */
export const API_ENDPOINTS = {
  AUTH_LOGIN: "/login",
  AUTH_LOGOUT: "/logout",
  AUTH_SESSION: "/api/session",
  HEALTH: "/health",
  DISPATCH: "/dispatch",
  API: "/api",
} as const;

// ============================================================================
// Errors
// ============================================================================

/**
 * ネットワークエラー
 */
export class NetworkError extends Error {
  constructor(
    message: string,
    public readonly statusCode?: number,
    public readonly endpoint?: string,
  ) {
    super(message);
    this.name = "NetworkError";
  }
}

/**
 * タイムアウトエラー
 */
export class TimeoutError extends NetworkError {
  constructor(
    public readonly timeoutMs: number,
    endpoint?: string,
  ) {
    super(`Request timed out after ${timeoutMs}ms`, undefined, endpoint);
    this.name = "TimeoutError";
  }
}

/**
 * サーバエラー（5xx）
 */
export class ServerError extends NetworkError {
  constructor(
    message: string,
    statusCode: number,
    endpoint?: string,
    public readonly responseBody?: string,
  ) {
    super(message, statusCode, endpoint);
    this.name = "ServerError";
  }
}

/**
 * 認証エラー（401/403）
 */
export class AuthError extends NetworkError {
  constructor(
    message: string = "Authentication required",
    statusCode: number = 401,
    endpoint?: string,
  ) {
    super(message, statusCode, endpoint);
    this.name = "AuthError";
  }
}
