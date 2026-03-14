'use strict';
/**
 * ap-events.js — AdlairePlatform イベントバス
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
(() => {

	let listeners = {};

	/**
	 * イベントリスナーを登録
	 * @param {string} event イベント名
	 * @param {Function} callback コールバック
	 * @returns {Function} リスナー解除関数
	 */
	const on = (event, callback) => {
		if (!listeners[event]) listeners[event] = [];
		listeners[event].push(callback);
		return () => off(event, callback);
	};

	/**
	 * 一度だけ実行されるリスナーを登録
	 */
	const once = (event, callback) => {
		const wrapper = (data) => {
			callback(data);
			off(event, wrapper);
		};
		on(event, wrapper);
	};

	/**
	 * イベントリスナーを解除
	 */
	const off = (event, callback) => {
		if (!listeners[event]) return;
		listeners[event] = listeners[event].filter(cb => cb !== callback);
		if (listeners[event].length === 0) delete listeners[event];
	};

	/**
	 * イベントを発火
	 * @param {string} event イベント名
	 * @param {*} data イベントデータ
	 */
	const emit = (event, data) => {
		if (!listeners[event]) return;
		const cbs = [...listeners[event]]; /* コピーして安全にイテレート */
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
	const clear = (event) => {
		if (event) {
			delete listeners[event];
		} else {
			listeners = {};
		}
	};

	/* AP オブジェクトに統合 */
	if (typeof AP !== 'undefined') {
		AP.on    = on;
		AP.once  = once;
		AP.off   = off;
		AP.emit  = emit;
		AP.clearEvents = clear;
	}

})();
