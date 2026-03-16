/// <reference lib="dom" />
/// <reference path="./browser-types.d.ts" />
/**
 * api_keys.ts - API キー管理 UI
 *
 * ダッシュボードの API キー管理セクション用バニラ JS。
 * 依存: ap-utils.js (AP.getCsrf, AP.escHtml)
 */
(function () {
	'use strict';

	const escHtml = AP.escHtml;

	interface ApiKeyEntry {
		key_prefix: string;
		label: string;
		active: boolean;
		index: number;
	}

	/* セッション認証ヘッダー付き API 呼び出し（api_keys 専用） */
	function apiCall(method: string, params: Record<string, unknown>, callback: (res: APResponse) => void): void {
		const url = './?ap_api=api_keys';
		const opts: RequestInit = { method: method, headers: {} as Record<string, string> };

		if (method === 'POST') {
			const csrf = AP.getCsrf();
			(opts.headers as Record<string, string>)['Content-Type'] = 'application/json';
			(opts.headers as Record<string, string>)['Authorization'] = 'session';
			(opts.headers as Record<string, string>)['X-CSRF-TOKEN'] = csrf;
			(params as Record<string, unknown>).csrf = csrf;
			opts.body = JSON.stringify(params);
		}

		fetch(url, opts)
			.then(function (r) { return r.json(); })
			.then(callback)
			.catch(function (e: Error) { callback({ ok: false, error: e.message }); });
	}

	function loadKeys(): void {
		const listEl = document.getElementById('ap-api-keys-list');
		if (!listEl) return;

		fetch('./?ap_api=api_keys')
			.then(function (r) { return r.json(); })
			.then(function (res: APResponse) {
				const data = res.data as ApiKeyEntry[] | undefined;
				if (!res.ok || !data || data.length === 0) {
					listEl.innerHTML = '<span style="color:#718096;">API キーがありません</span>';
					return;
				}
				let html = '';
				data.forEach(function (k) {
					const status = k.active ? '<span style="color:#38a169;">有効</span>' : '<span style="color:#e53e3e;">無効</span>';
					html += '<div style="display:flex;align-items:center;gap:8px;padding:6px 0;border-bottom:1px solid #f0f2f5;">'
						+ '<code>' + escHtml(k.key_prefix) + '</code>'
						+ '<span>' + escHtml(k.label) + '</span>'
						+ '<span style="margin-left:auto;">' + status + '</span>'
						+ '<button class="ap-api-key-delete" data-index="' + k.index + '" '
						+ 'style="font-size:12px;padding:2px 8px;background:#e53e3e;">削除</button>'
						+ '</div>';
				});
				listEl.innerHTML = html;

				/* 削除ボタンバインド */
				document.querySelectorAll<HTMLButtonElement>('.ap-api-key-delete').forEach(function (btn) {
					btn.addEventListener('click', function (this: HTMLButtonElement) {
						const idx = parseInt(this.getAttribute('data-index') || '0', 10);
						if (!confirm('この API キーを削除しますか？')) return;
						apiCall('POST', { action: 'delete', index: idx }, function (res: APResponse) {
							if (res.ok) { loadKeys(); }
							else { alert('エラー: ' + (res.error || '')); }
						});
					});
				});
			})
			.catch(function () {
				listEl.innerHTML = '<span style="color:#e53e3e;">読み込みエラー</span>';
			});
	}

	document.addEventListener('DOMContentLoaded', function () {
		loadKeys();

		const genBtn = document.getElementById('ap-api-key-generate') as HTMLButtonElement | null;
		if (genBtn) {
			genBtn.addEventListener('click', function () {
				const label = (document.getElementById('ap-api-key-label') as HTMLInputElement | null)?.value ?? '';
				const resultEl = document.getElementById('ap-api-key-result');

				apiCall('POST', { action: 'generate', label: label }, function (res: APResponse) {
					const data = res.data as { key?: string } | undefined;
					if (res.ok && data && data.key) {
						if (resultEl) {
							resultEl.style.color = '#38a169';
							resultEl.innerHTML = '<strong>生成されたキー（この画面を閉じると二度と表示されません）:</strong><br>'
								+ '<code style="background:#f7fafc;padding:4px 8px;border:1px solid #e2e8f0;border-radius:4px;user-select:all;font-size:14px;">'
								+ escHtml(data.key) + '</code>';
						}
						const labelEl = document.getElementById('ap-api-key-label') as HTMLInputElement | null;
						if (labelEl) labelEl.value = '';
						loadKeys();
					} else {
						if (resultEl) {
							resultEl.style.color = '#e53e3e';
							resultEl.textContent = 'エラー: ' + (res.error || '不明なエラー');
						}
					}
				});
			});
		}
	});
})();
