// browser-types.d.ts — Global type declarations for browser-side admin scripts

interface APResponse {
  ok: boolean;
  data?: unknown;
  error?: string;
  [key: string]: unknown;
}

interface APGlobal {
  // From ap-utils.js
  getCsrf(): string;
  escHtml(s: unknown): string;
  post(
    action: string,
    params: Record<string, string> | null,
    callback: (res: APResponse) => void,
  ): void;
  postAction(action: string, extra?: Record<string, string>): Promise<APResponse>;
  apiPost(
    endpoint: string,
    data: Record<string, unknown> | null,
    callback: (res: APResponse) => void,
  ): void;
  // From ap-events.js
  on(event: string, callback: (data: unknown) => void): () => void;
  once(event: string, callback: (data: unknown) => void): void;
  off(event: string, callback: (data: unknown) => void): void;
  emit(event: string, data?: unknown): void;
  clearEvents(event?: string): void;
  // From ap-i18n.js
  t(key: string, params?: Record<string, string | number>): string;
  locale(): string;
}

// deno-lint-ignore no-var
declare var AP: APGlobal;
declare function apAutosize(el: HTMLTextAreaElement): void;

// deno-lint-ignore no-var
declare var _apChanging: boolean;
// deno-lint-ignore no-var
declare var _apFieldSave: (key: string, val: string) => void;
// deno-lint-ignore no-var
declare var __AP_I18N__: Record<string, string>;
// deno-lint-ignore no-var
declare var __AP_LOCALE__: string;
// deno-lint-ignore no-var
declare var __AP_EventBus__: {
  on(event: string, callback: (...args: unknown[]) => void): void;
  off(event: string, callback: (...args: unknown[]) => void): void;
  emit(event: string, ...args: unknown[]): void;
};
// deno-lint-ignore no-var
declare var AEB: {
  Editor: new (config: Record<string, unknown>) => unknown;
  BlockRegistry: { register(name: string, cls: unknown): void };
  EventBus: new () => {
    on(event: string, callback: (...args: unknown[]) => void): void;
    off(event: string, callback: (...args: unknown[]) => void): void;
    emit(event: string, ...args: unknown[]): void;
  };
  StateManager: unknown;
  HistoryManager: unknown;
  Blocks: Record<string, unknown>;
  Utils: Record<string, unknown>;
};

interface Window {
  AP: APGlobal;
  AEB: typeof AEB;
  __AP_I18N__: Record<string, string>;
  __AP_LOCALE__: string;
  __AP_EventBus__: typeof __AP_EventBus__;
  _apFieldSave: (key: string, val: string) => void;
  _apChanging: boolean;
}
