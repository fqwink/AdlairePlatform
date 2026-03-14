'use strict';
/**
 * ap-utils.js — AdlairePlatform 共通ユーティリティ
 *
 * 全ダッシュボード JS モジュールが利用する共通関数を提供。
 * 他の JS ファイルより先に読み込むこと。
 *
 * @global {Object} AP
 */
var AP = (function () {

	/* ── CSRF トークン ── */

	function getCsrf() {
		var meta = document.querySelector('meta[name="csrf-token"]');
		return meta ? meta.getAttribute('content') : '';
	}

	/* ── HTML エスケープ ── */

	function escHtml(s) {
		var d = document.createElement('div');
		d.textContent = String(s == null ? '' : s);
		return d.innerHTML;
	}

	/* ── FormData POST（コールバック方式） ──
	   用途: collection_manager, git_manager, webhook_manager 等 */

	function post(action, params, callback) {
		var fd = new FormData();
		var c  = getCsrf();
		fd.append('ap_action', action);
		fd.append('csrf', c);
		if (params) {
			for (var k in params) {
				if (params.hasOwnProperty(k)) fd.append(k, params[k]);
			}
		}
		fetch('./', { method: 'POST', headers: { 'X-CSRF-TOKEN': c }, body: fd })
			.then(function (r) {
				if (!r.ok) throw new Error('HTTP ' + r.status);
				return r.json();
			})
			.then(callback)
			.catch(function (e) { callback({ ok: false, error: e.message }); });
	}

	/* ── URLSearchParams POST（Promise 方式） ──
	   用途: static_builder, updater, dashboard, diagnostics 等 */

	function postAction(action, extra) {
		var c    = getCsrf();
		var data = { ap_action: action, csrf: c };
		if (extra) {
			for (var k in extra) {
				if (extra.hasOwnProperty(k)) data[k] = extra[k];
			}
		}
		return fetch('./', {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': c },
			body: new URLSearchParams(data)
		}).then(function (r) {
			if (!r.ok) throw new Error('HTTP ' + r.status);
			return r.json();
		});
	}

	/* ── JSON API POST（コールバック方式） ──
	   用途: api_keys, webhook_manager (apiPost) 等 */

	function apiPost(endpoint, data, callback) {
		var c = getCsrf();
		if (data) data.csrf = c;
		fetch('./?ap_api=' + encodeURIComponent(endpoint), {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': c },
			body: JSON.stringify(data)
		})
		.then(function (r) {
			if (!r.ok) throw new Error('HTTP ' + r.status);
			return r.json();
		})
		.then(callback)
		.catch(function (e) { callback({ ok: false, error: e.message }); });
	}

	/* ── 公開 API ── */

	return {
		getCsrf:    getCsrf,
		escHtml:    escHtml,
		post:       post,
		postAction: postAction,
		apiPost:    apiPost
	};

})();
