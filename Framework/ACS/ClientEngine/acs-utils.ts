/// <reference lib="dom" />
/// <reference path="./browser-types.d.ts" />

'use strict';
/**
 * ap-utils.ts — AdlairePlatform 共通ユーティリティ
 *
 * Ver.1.6: ES6 モダン構文に移行
 *
 * 全ダッシュボード JS モジュールが利用する共通関数を提供。
 * 他の JS ファイルより先に読み込むこと。
 *
 * @global {Object} AP
 */
// deno-lint-ignore no-unused-vars
const AP: APGlobal = ((): APGlobal => {

	/* ── CSRF トークン ── */

	const getCsrf = (): string => {
		const meta: Element | null = document.querySelector('meta[name="csrf-token"]');
		return meta ? meta.getAttribute('content') ?? '' : '';
	};

	/* ── HTML エスケープ ── */

	const escHtml = (s: unknown): string => {
		const d: HTMLDivElement = document.createElement('div');
		d.textContent = String(s ?? '');
		return d.innerHTML;
	};

	/* ── FormData POST（コールバック方式） ──
	   用途: collection_manager, git_manager, webhook_manager 等 */

	const post = (action: string, params: Record<string, string> | null, callback: (res: APResponse) => void): void => {
		const fd: FormData = new FormData();
		const c: string = getCsrf();
		fd.append('ap_action', action);
		fd.append('csrf', c);
		if (params) {
			for (const [k, v] of Object.entries(params)) {
				fd.append(k, v);
			}
		}
		fetch('./', { method: 'POST', headers: { 'X-CSRF-TOKEN': c }, body: fd })
			.then((r: Response) => {
				if (!r.ok) throw new Error(`HTTP ${r.status}`);
				return r.json();
			})
			.then(callback)
			.catch((e: Error) => callback({ ok: false, error: e.message }));
	};

	/* ── URLSearchParams POST（Promise 方式） ──
	   用途: static_builder, updater, dashboard, diagnostics 等 */

	const postAction = (action: string, extra?: Record<string, string>): Promise<APResponse> => {
		const c: string = getCsrf();
		const data: Record<string, string> = { ap_action: action, csrf: c, ...extra };
		return fetch('./', {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': c },
			body: new URLSearchParams(data)
		}).then((r: Response) => {
			if (!r.ok) throw new Error(`HTTP ${r.status}`);
			return r.json();
		});
	};

	/* ── JSON API POST（コールバック方式） ──
	   用途: api_keys, webhook_manager (apiPost) 等 */

	const apiPost = (endpoint: string, data: Record<string, unknown> | null, callback: (res: APResponse) => void): void => {
		const c: string = getCsrf();
		if (data) (data as Record<string, unknown>).csrf = c;
		fetch(`./?ap_api=${encodeURIComponent(endpoint)}`, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': c },
			body: JSON.stringify(data)
		})
		.then((r: Response) => {
			if (!r.ok) throw new Error(`HTTP ${r.status}`);
			return r.json();
		})
		.then(callback)
		.catch((e: Error) => callback({ ok: false, error: e.message }));
	};

	/* ── 公開 API ── */

	return { getCsrf, escHtml, post, postAction, apiPost } as APGlobal;

})();
