/// <reference lib="dom" />
/// <reference path="./browser-types.d.ts" />

/**
 * AP.t() - クライアント側翻訳ヘルパー
 *
 * サーバーから window.__AP_I18N__ に翻訳データが注入される前提。
 * 使用例: AP.t('dash.nav.logout') → "ログアウト"
 *         AP.t('auth.attempts_left', {count: 3}) → "残り3回"
 */
(function (): void {
	'use strict';

	const AP: APGlobal = window.AP || ({} as APGlobal);
	window.AP = AP;

	/** 翻訳データ（サーバーから注入） */
	const _data: Record<string, string> = window.__AP_I18N__ || {};

	/**
	 * 翻訳キーから翻訳文字列を取得
	 * @param key - ドット記法キー
	 * @param params - プレースホルダ値
	 */
	AP.t = function (key: string, params?: Record<string, string | number>): string {
		let text: string = _data[key] || key;
		if (params) {
			Object.keys(params).forEach(function (k: string) {
				text = text.replace(new RegExp('\\{' + k + '\\}', 'g'), String(params[k]));
			});
		}
		return text;
	};

	/** 現在のロケールを返す */
	AP.locale = function (): string {
		return window.__AP_LOCALE__ || 'ja';
	};

})();
