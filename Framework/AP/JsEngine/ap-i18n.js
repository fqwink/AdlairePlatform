/**
 * AP.t() - クライアント側翻訳ヘルパー
 *
 * サーバーから window.__AP_I18N__ に翻訳データが注入される前提。
 * 使用例: AP.t('dash.nav.logout') → "ログアウト"
 *         AP.t('auth.attempts_left', {count: 3}) → "残り3回"
 */
(function() {
	'use strict';

	var AP = window.AP || {};
	window.AP = AP;

	/** 翻訳データ（サーバーから注入） */
	var _data = window.__AP_I18N__ || {};

	/**
	 * 翻訳キーから翻訳文字列を取得
	 * @param {string} key - ドット記法キー
	 * @param {Object} [params] - プレースホルダ値
	 * @returns {string}
	 */
	AP.t = function(key, params) {
		var text = _data[key] || key;
		if (params) {
			Object.keys(params).forEach(function(k) {
				text = text.replace(new RegExp('\\{' + k + '\\}', 'g'), String(params[k]));
			});
		}
		return text;
	};

	/** 現在のロケールを返す */
	AP.locale = function() {
		return window.__AP_LOCALE__ || 'ja';
	};

})();
