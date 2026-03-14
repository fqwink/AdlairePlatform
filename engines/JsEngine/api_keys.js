/**
 * api_keys.js - API キー管理 UI
 *
 * ダッシュボードの API キー管理セクション用バニラ JS。
 * 依存: ap-utils.js (AP.getCsrf, AP.escHtml)
 */
(function() {
	'use strict';

	var escHtml = AP.escHtml;

	/* セッション認証ヘッダー付き API 呼び出し（api_keys 専用） */
	function apiCall(method, params, callback) {
		var url = './?ap_api=api_keys';
		var opts = { method: method, headers: {} };

		if (method === 'POST') {
			var csrf = AP.getCsrf();
			opts.headers['Content-Type'] = 'application/json';
			opts.headers['Authorization'] = 'session';
			opts.headers['X-CSRF-TOKEN'] = csrf;
			params.csrf = csrf;
			opts.body = JSON.stringify(params);
		}

		fetch(url, opts)
			.then(function(r) { return r.json(); })
			.then(callback)
			.catch(function(e) { callback({ ok: false, error: e.message }); });
	}

	function loadKeys() {
		var listEl = document.getElementById('ap-api-keys-list');
		if (!listEl) return;

		fetch('./?ap_api=api_keys')
			.then(function(r) { return r.json(); })
			.then(function(res) {
				if (!res.ok || !res.data || res.data.length === 0) {
					listEl.innerHTML = '<span style="color:#718096;">API キーがありません</span>';
					return;
				}
				var html = '';
				res.data.forEach(function(k) {
					var status = k.active ? '<span style="color:#38a169;">有効</span>' : '<span style="color:#e53e3e;">無効</span>';
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
				document.querySelectorAll('.ap-api-key-delete').forEach(function(btn) {
					btn.addEventListener('click', function() {
						var idx = parseInt(this.getAttribute('data-index'));
						if (!confirm('この API キーを削除しますか？')) return;
						apiCall('POST', { action: 'delete', index: idx }, function(res) {
							if (res.ok) { loadKeys(); }
							else { alert('エラー: ' + (res.error || '')); }
						});
					});
				});
			})
			.catch(function() {
				listEl.innerHTML = '<span style="color:#e53e3e;">読み込みエラー</span>';
			});
	}

	document.addEventListener('DOMContentLoaded', function() {
		loadKeys();

		var genBtn = document.getElementById('ap-api-key-generate');
		if (genBtn) {
			genBtn.addEventListener('click', function() {
				var label = (document.getElementById('ap-api-key-label') || {}).value || '';
				var resultEl = document.getElementById('ap-api-key-result');

				apiCall('POST', { action: 'generate', label: label }, function(res) {
					if (res.ok && res.data && res.data.key) {
						if (resultEl) {
							resultEl.style.color = '#38a169';
							resultEl.innerHTML = '<strong>生成されたキー（この画面を閉じると二度と表示されません）:</strong><br>'
								+ '<code style="background:#f7fafc;padding:4px 8px;border:1px solid #e2e8f0;border-radius:4px;user-select:all;font-size:14px;">'
								+ escHtml(res.data.key) + '</code>';
						}
						document.getElementById('ap-api-key-label').value = '';
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
