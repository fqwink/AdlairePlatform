/**
 * Adlaire Client Services (ACS) — API Adapter Layer
 *
 * ACS クライアントの生成・設定を統合する初期化モジュール。
 *
 * @package ACS
 * @version 2.0.0
 * @license Adlaire License Ver.2.0
 */

import type { AdlaireClient } from "./ACS.d.ts";
import type { ClientConfig } from "./ACS.Interface.ts";
import { ClientFactory } from "./ACS.Core.ts";

/**
 * 環境設定からクライアントを初期化する
 */
export function createClient(
  config?: Partial<ClientConfig> & { token?: string | null },
): AdlaireClient {
  const factory = new ClientFactory();
  const fullConfig: ClientConfig = {
    baseUrl: config?.baseUrl ?? "",
    timeout: config?.timeout ?? 30000,
    headers: config?.token
      ? { ...config?.headers, Authorization: `Bearer ${config.token}` }
      : config?.headers,
  };
  return factory.create(fullConfig);
}

/**
 * テスト用のモッククライアントを生成する
 */
export function createMockClient(overrides?: Partial<AdlaireClient>): AdlaireClient {
  const noop = () => Promise.resolve(null);
  const noopBool = () => Promise.resolve(false);
  const noopArr = () => Promise.resolve([]);

  return {
    auth: {
      login: () => Promise.resolve({ authenticated: false, user: null }),
      logout: () => Promise.resolve(),
      session: noop,
      verify: () => Promise.resolve({ authenticated: false, user: null }),
    },
    storage: {
      read: noop,
      readMany: noopArr,
      write: noopBool,
      delete: noopBool,
      exists: noopBool,
      list: noopArr,
    },
    files: {
      upload: () => Promise.resolve({ success: false, path: "" }),
      download: () => Promise.resolve(new Blob()),
      delete: noopBool,
      exists: noopBool,
      info: noop,
    },
    http: {
      get: () => Promise.resolve({ ok: false }),
      post: () => Promise.resolve({ ok: false }),
      put: () => Promise.resolve({ ok: false }),
      delete: () => Promise.resolve({ ok: false }),
    },
    ...overrides,
  };
}
