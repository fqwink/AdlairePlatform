'use strict';
/**
 * ap-utils.js — AdlairePlatform 共通ユーティリティ
 *
 * Ver.1.6: ES6 モダン構文に移行
 *
 * 全ダッシュボード JS モジュールが利用する共通関数を提供。
 * 他の JS ファイルより先に読み込むこと。
 *
 * @global {Object} AP
 */
const AP = (() => {

	/* ── CSRF トークン ── */

	const getCsrf = () => {
		const meta = document.querySelector('meta[name="csrf-token"]');
		return meta ? meta.getAttribute('content') : '';
	};

	/* ── HTML エスケープ ── */

	const escHtml = (s) => {
		const d = document.createElement('div');
		d.textContent = String(s ?? '');
		return d.innerHTML;
	};

	/* ── FormData POST（コールバック方式） ──
	   用途: collection_manager, git_manager, webhook_manager 等 */

	const post = (action, params, callback) => {
		const fd = new FormData();
		const c  = getCsrf();
		fd.append('ap_action', action);
		fd.append('csrf', c);
		if (params) {
			for (const [k, v] of Object.entries(params)) {
				fd.append(k, v);
			}
		}
		fetch('./', { method: 'POST', headers: { 'X-CSRF-TOKEN': c }, body: fd })
			.then(r => {
				if (!r.ok) throw new Error(`HTTP ${r.status}`);
				return r.json();
			})
			.then(callback)
			.catch(e => callback({ ok: false, error: e.message }));
	};

	/* ── URLSearchParams POST（Promise 方式） ──
	   用途: static_builder, updater, dashboard, diagnostics 等 */

	const postAction = (action, extra) => {
		const c    = getCsrf();
		const data = { ap_action: action, csrf: c, ...extra };
		return fetch('./', {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': c },
			body: new URLSearchParams(data)
		}).then(r => {
			if (!r.ok) throw new Error(`HTTP ${r.status}`);
			return r.json();
		});
	};

	/* ── JSON API POST（コールバック方式） ──
	   用途: api_keys, webhook_manager (apiPost) 等 */

	const apiPost = (endpoint, data, callback) => {
		const c = getCsrf();
		if (data) data.csrf = c;
		fetch(`./?ap_api=${encodeURIComponent(endpoint)}`, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': c },
			body: JSON.stringify(data)
		})
		.then(r => {
			if (!r.ok) throw new Error(`HTTP ${r.status}`);
			return r.json();
		})
		.then(callback)
		.catch(e => callback({ ok: false, error: e.message }));
	};

	/* ── 公開 API ── */

	return { getCsrf, escHtml, post, postAction, apiPost };

})();
