/**
 * ap-api-client.js — AdlairePlatform 公開 API クライアント
 *
 * 静的サイト（static/）から Fetch API で公開エンドポイントを呼び出す。
 * 依存ライブラリなし / ES5 互換 / バニラ JS
 *
 * API:
 *   window.AP.api(action, params) → Promise<Object>
 *   window.AP.origin              → API オリジン（自動検出）
 *
 * フォーム自動バインド:
 *   <form class="ap-contact"> を検出し、submit を Fetch に変換。
 *   送信完了時に CustomEvent 'ap:done' を dispatch。
 */
(function () {
	'use strict';

	var script = document.currentScript;
	var origin = '';
	if (script && script.src) {
		origin = script.src.replace(/\/engines\/.*$|\/static\/.*$|\/assets\/.*$/, '');
	}

	/**
	 * 公開 API 呼び出し
	 * @param {string} action - ap_api パラメータ値（pages, page, settings, search, contact）
	 * @param {Object} [params] - 追加パラメータ
	 * @returns {Promise<Object>} JSON レスポンス
	 */
	function api(action, params) {
		var url = origin + '/?ap_api=' + encodeURIComponent(action);
		var isPost = (action === 'contact');
		var opts = { method: isPost ? 'POST' : 'GET' };

		if (isPost) {
			opts.headers = { 'Content-Type': 'application/x-www-form-urlencoded' };
			opts.body = _toUrlParams(params || {});
		} else if (params) {
			var keys = Object.keys(params);
			for (var i = 0; i < keys.length; i++) {
				url += '&' + encodeURIComponent(keys[i]) + '=' + encodeURIComponent(params[keys[i]]);
			}
		}

		return fetch(url, opts).then(function (r) { return r.json(); });
	}

	/* ── ユーティリティ ── */

	function _toUrlParams(obj) {
		var parts = [];
		var keys = Object.keys(obj);
		for (var i = 0; i < keys.length; i++) {
			parts.push(encodeURIComponent(keys[i]) + '=' + encodeURIComponent(obj[keys[i]]));
		}
		return parts.join('&');
	}

	/* ── フォーム自動バインド ── */

	document.addEventListener('DOMContentLoaded', function () {
		var forms = document.querySelectorAll('form.ap-contact');
		for (var i = 0; i < forms.length; i++) {
			(function (form) {
				form.addEventListener('submit', function (e) {
					e.preventDefault();
					var btn = form.querySelector('[type="submit"]');
					if (btn) { btn.disabled = true; btn.dataset.origText = btn.textContent; btn.textContent = '送信中...'; }

					var fd = new FormData(form);
					var params = {};
					fd.forEach(function (v, k) { params[k] = v; });

					api('contact', params).then(function (res) {
						form.dispatchEvent(new CustomEvent('ap:done', { detail: res }));
						if (btn) { btn.disabled = false; btn.textContent = btn.dataset.origText; }
					}).catch(function () {
						form.dispatchEvent(new CustomEvent('ap:done', {
							detail: { ok: false, error: '通信エラーが発生しました' }
						}));
						if (btn) { btn.disabled = false; btn.textContent = btn.dataset.origText; }
					});
				});
			})(forms[i]);
		}
	});

	/* ── グローバル公開 ── */

	window.AP = {
		origin: origin,
		api: api
	};
})();
