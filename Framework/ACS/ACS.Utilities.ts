/**
 * Adlaire Client Services (ACS) — Utilities
 *
 * URL 構築、ヘッダー操作、データ変換などの汎用ヘルパー。
 *
 * @package ACS
 * @version 2.0.0
 * @license Adlaire License Ver.2.0
 */

// ============================================================================
// URL Utilities
// ============================================================================

/**
 * ベース URL とパスを安全に結合する
 */
export function joinUrl(base: string, ...parts: string[]): string {
  let url = base.replace(/\/$/, "");
  for (const part of parts) {
    const cleaned = part.replace(/^\//, "").replace(/\/$/, "");
    if (cleaned) {
      url += "/" + cleaned;
    }
  }
  return url;
}

/**
 * クエリパラメータをオブジェクトから構築する
 */
export function buildQueryString(params: Record<string, string | number | boolean | undefined>): string {
  const pairs: string[] = [];
  for (const [key, value] of Object.entries(params)) {
    if (value !== undefined) {
      pairs.push(`${encodeURIComponent(key)}=${encodeURIComponent(String(value))}`);
    }
  }
  return pairs.length > 0 ? "?" + pairs.join("&") : "";
}

// ============================================================================
// Header Utilities
// ============================================================================

/**
 * Bearer トークンヘッダーを生成する
 */
export function bearerHeader(token: string): Record<string, string> {
  return { Authorization: `Bearer ${token}` };
}

/**
 * CSRF トークンヘッダーを生成する
 */
export function csrfHeader(token: string): Record<string, string> {
  return { "X-CSRF-Token": token };
}

/**
 * Content-Type を JSON に設定したヘッダーを返す
 */
export function jsonHeaders(extra?: Record<string, string>): Record<string, string> {
  return {
    "Content-Type": "application/json",
    Accept: "application/json",
    ...extra,
  };
}

// ============================================================================
// Data Utilities
// ============================================================================

/**
 * API レスポンスからデータを安全に抽出する
 */
export function extractData<T>(response: { ok: boolean; data?: T; error?: string }): T {
  if (!response.ok || response.data === undefined) {
    throw new Error(response.error ?? "No data in response");
  }
  return response.data;
}

/**
 * FormData にオブジェクトのフィールドを追加する
 */
export function objectToFormData(
  obj: Record<string, string | number | boolean | Blob | undefined>,
): FormData {
  const fd = new FormData();
  for (const [key, value] of Object.entries(obj)) {
    if (value === undefined) continue;
    if (value instanceof Blob) {
      fd.append(key, value);
    } else {
      fd.append(key, String(value));
    }
  }
  return fd;
}

// ============================================================================
// Exponential Backoff
// ============================================================================

/**
 * 指数バックオフの遅延時間を計算する（ジッター付き）
 */
export function calculateBackoff(
  attempt: number,
  baseDelay: number = 1000,
  maxDelay: number = 30000,
): number {
  const exponential = baseDelay * Math.pow(2, attempt);
  const jitter = Math.random() * baseDelay;
  return Math.min(exponential + jitter, maxDelay);
}
