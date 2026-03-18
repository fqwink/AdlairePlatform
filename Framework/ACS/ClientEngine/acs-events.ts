/// <reference lib="dom" />
/// <reference path="./browser-types.d.ts" />

"use strict";
/**
 * ap-events.ts — AdlairePlatform イベントバス
 *
 * Ver.1.6: ES6 モダン構文に移行
 *
 * AEB.Core.js の EventBus を AP エンジンパターンに適応。
 * モジュール間通信を統一する軽量イベントシステム。
 *
 * 使用例:
 *   AP.on('collection:saved', (data) => { ... });
 *   AP.emit('collection:saved', { slug: 'my-page' });
 *   const off = AP.on('cache:cleared', handler);
 *   off(); // リスナー解除
 *
 * ap-utils.js の後に読み込むこと。
 *
 * @requires AP (ap-utils.js)
 */
((): void => {
  type EventCallback = (data: unknown) => void;

  let listeners: Record<string, Array<EventCallback>> = {};

  /**
   * イベントリスナーを登録
   * @param event イベント名
   * @param callback コールバック
   * @returns リスナー解除関数
   */
  const on = (event: string, callback: EventCallback): () => void => {
    if (!listeners[event]) listeners[event] = [];
    listeners[event].push(callback);
    return () => off(event, callback);
  };

  /**
   * 一度だけ実行されるリスナーを登録
   */
  const once = (event: string, callback: EventCallback): void => {
    const wrapper: EventCallback = (data: unknown) => {
      callback(data);
      off(event, wrapper);
    };
    on(event, wrapper);
  };

  /**
   * イベントリスナーを解除
   */
  const off = (event: string, callback: EventCallback): void => {
    if (!listeners[event]) return;
    listeners[event] = listeners[event].filter((cb: EventCallback) => cb !== callback);
    if (listeners[event].length === 0) delete listeners[event];
  };

  /**
   * イベントを発火
   * @param event イベント名
   * @param data イベントデータ
   */
  const emit = (event: string, data?: unknown): void => {
    if (!listeners[event]) return;
    const cbs: Array<EventCallback> = [...listeners[event]]; /* コピーして安全にイテレート */
    for (const cb of cbs) {
      try {
        cb(data);
      } catch (e) {
        console.error(`[AP.events] Error in "${event}":`, e);
      }
    }
  };

  /**
   * 指定イベントまたは全リスナーをクリア
   */
  const clear = (event?: string): void => {
    if (event) {
      delete listeners[event];
    } else {
      listeners = {};
    }
  };

  /* AP オブジェクトに統合 */
  if (typeof AP !== "undefined") {
    AP.on = on;
    AP.once = once;
    AP.off = off;
    AP.emit = emit;
    AP.clearEvents = clear;
  }
})();
