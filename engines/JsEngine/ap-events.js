'use strict';
/**
 * ap-events.js — AdlairePlatform イベントバス
 *
 * AEF.Core.js の EventBus を AP エンジンパターンに適応。
 * モジュール間通信を統一する軽量イベントシステム。
 *
 * 使用例:
 *   AP.on('collection:saved', function(data) { ... });
 *   AP.emit('collection:saved', { slug: 'my-page' });
 *   var off = AP.on('cache:cleared', handler);
 *   off(); // リスナー解除
 *
 * ap-utils.js の後に読み込むこと。
 *
 * @requires AP (ap-utils.js)
 */
(function () {

	var listeners = {};

	/**
	 * イベントリスナーを登録
	 * @param {string} event イベント名
	 * @param {Function} callback コールバック
	 * @returns {Function} リスナー解除関数
	 */
	function on(event, callback) {
		if (!listeners[event]) {
			listeners[event] = [];
		}
		listeners[event].push(callback);
		return function () { off(event, callback); };
	}

	/**
	 * 一度だけ実行されるリスナーを登録
	 */
	function once(event, callback) {
		function wrapper(data) {
			callback(data);
			off(event, wrapper);
		}
		on(event, wrapper);
	}

	/**
	 * イベントリスナーを解除
	 */
	function off(event, callback) {
		if (!listeners[event]) return;
		listeners[event] = listeners[event].filter(function (cb) {
			return cb !== callback;
		});
		if (listeners[event].length === 0) {
			delete listeners[event];
		}
	}

	/**
	 * イベントを発火
	 * @param {string} event イベント名
	 * @param {*} data イベントデータ
	 */
	function emit(event, data) {
		if (!listeners[event]) return;
		var cbs = listeners[event].slice(); /* コピーして安全にイテレート */
		for (var i = 0; i < cbs.length; i++) {
			try {
				cbs[i](data);
			} catch (e) {
				console.error('[AP.events] Error in "' + event + '":', e);
			}
		}
	}

	/**
	 * 指定イベントまたは全リスナーをクリア
	 */
	function clear(event) {
		if (event) {
			delete listeners[event];
		} else {
			listeners = {};
		}
	}

	/* AP オブジェクトに統合 */
	if (typeof AP !== 'undefined') {
		AP.on    = on;
		AP.once  = once;
		AP.off   = off;
		AP.emit  = emit;
		AP.clearEvents = clear;
	}

})();
