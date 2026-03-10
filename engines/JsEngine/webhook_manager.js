/**
 * webhook_manager.js - Outgoing Webhook / キャッシュ / ユーザー管理 UI
 */
(function() {
	'use strict';

	var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

	/* A-1 fix: X-CSRF-TOKEN ヘッダーを追加（apiPost() と統一） */
	function post(action, params, callback) {
		var fd = new FormData();
		fd.append('ap_action', action);
		fd.append('csrf', csrf);
		for (var k in params) {
			if (params.hasOwnProperty(k)) fd.append(k, params[k]);
		}
		fetch('./', { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf }, body: fd })
			.then(function(r) {
				if (!r.ok) throw new Error('HTTP ' + r.status);
				return r.json();
			})
			.then(callback)
			.catch(function(e) { callback({ ok: false, error: e.message }); });
	}

	/* R1 fix: CSRF トークンを X-CSRF-TOKEN ヘッダーで送信 */
	function apiPost(endpoint, data, callback) {
		fetch('./?ap_api=' + encodeURIComponent(endpoint), {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
			body: JSON.stringify(data)
		})
		.then(function(r) {
			if (!r.ok) throw new Error('HTTP ' + r.status);
			return r.json();
		})
		.then(callback)
		.catch(function(e) { callback({ ok: false, error: e.message }); });
	}

	document.addEventListener('DOMContentLoaded', function() {

		/* ── Webhook 追加 ── */
		var addBtn = document.getElementById('ap-webhook-add');
		if (addBtn) {
			addBtn.addEventListener('click', function() {
				var url = (document.getElementById('ap-webhook-url') || {}).value || '';
				var label = (document.getElementById('ap-webhook-label') || {}).value || '';
				var secret = (document.getElementById('ap-webhook-secret') || {}).value || '';
				if (!url.trim()) { alert('URL を入力してください'); return; }
				post('webhook_add', { url: url.trim(), label: label.trim(), secret: secret.trim() }, function(res) {
					var r = document.getElementById('ap-webhook-result');
					if (res.ok) {
						if (r) r.textContent = '追加しました';
						setTimeout(function() { location.reload(); }, 800);
					} else {
						if (r) { r.textContent = 'エラー: ' + (res.error || ''); r.style.color = '#e53e3e'; }
					}
				});
			});
		}

		/* ── Webhook 削除 ── */
		document.querySelectorAll('.ap-webhook-delete').forEach(function(btn) {
			btn.addEventListener('click', function() {
				var index = this.getAttribute('data-index');
				if (!confirm('この Webhook を削除しますか？')) return;
				post('webhook_delete', { index: index }, function(res) {
					if (res.ok) location.reload();
					else alert('エラー: ' + (res.error || ''));
				});
			});
		});

		/* ── Webhook 切替 ── */
		document.querySelectorAll('.ap-webhook-toggle').forEach(function(btn) {
			btn.addEventListener('click', function() {
				var index = this.getAttribute('data-index');
				post('webhook_toggle', { index: index }, function(res) {
					if (res.ok) location.reload();
					else alert('エラー: ' + (res.error || ''));
				});
			});
		});

		/* ── Webhook テスト ── */
		document.querySelectorAll('.ap-webhook-test').forEach(function(btn) {
			btn.addEventListener('click', function() {
				var index = this.getAttribute('data-index');
				post('webhook_test', { index: index }, function(res) {
					var r = document.getElementById('ap-webhook-result');
					if (r) {
						r.textContent = res.ok ? 'テスト送信しました' : 'エラー: ' + (res.error || '');
						r.style.color = res.ok ? '#38a169' : '#e53e3e';
					}
				});
			});
		});

		/* ── キャッシュクリア ── */
		var cacheClearBtn = document.getElementById('ap-cache-clear');
		if (cacheClearBtn) {
			cacheClearBtn.addEventListener('click', function() {
				apiPost('cache_clear', {}, function(res) {
					var r = document.getElementById('ap-cache-result');
					if (r) {
						r.textContent = res.ok ? 'キャッシュをクリアしました' : 'エラー';
						r.style.color = res.ok ? '#38a169' : '#e53e3e';
					}
				});
			});
		}

		/* ── ユーザー追加 ── */
		var userAddBtn = document.getElementById('ap-user-add');
		if (userAddBtn) {
			userAddBtn.addEventListener('click', function() {
				var username = (document.getElementById('ap-user-username') || {}).value || '';
				var password = (document.getElementById('ap-user-password') || {}).value || '';
				var role = (document.getElementById('ap-user-role') || {}).value || 'editor';
				if (!username.trim() || !password) { alert('ユーザー名とパスワードを入力してください'); return; }
				post('user_add', { username: username.trim(), password: password, role: role }, function(res) {
					var r = document.getElementById('ap-user-result');
					if (res.ok) {
						if (r) { r.textContent = 'ユーザーを追加しました'; r.style.color = '#38a169'; }
						setTimeout(function() { location.reload(); }, 800);
					} else {
						if (r) { r.textContent = 'エラー: ' + (res.error || ''); r.style.color = '#e53e3e'; }
					}
				});
			});
		}

		/* ── ユーザー削除 ── */
		document.querySelectorAll('.ap-user-delete').forEach(function(btn) {
			btn.addEventListener('click', function() {
				var username = this.getAttribute('data-username');
				if (!confirm('ユーザー「' + username + '」を削除しますか？')) return;
				post('user_delete', { username: username }, function(res) {
					if (res.ok) location.reload();
					else alert('エラー: ' + (res.error || ''));
				});
			});
		});

		/* ── リダイレクト追加 ── */
		var redirectAddBtn = document.getElementById('ap-redirect-add');
		if (redirectAddBtn) {
			redirectAddBtn.addEventListener('click', function() {
				var from = (document.getElementById('ap-redirect-from') || {}).value || '';
				var to = (document.getElementById('ap-redirect-to') || {}).value || '';
				var code = (document.getElementById('ap-redirect-code') || {}).value || '301';
				if (!from.trim() || !to.trim()) { alert('旧URLと新URLを入力してください'); return; }
				post('redirect_add', { from: from.trim(), to: to.trim(), code: code }, function(res) {
					var r = document.getElementById('ap-redirect-result');
					if (res.ok) {
						if (r) { r.textContent = '追加しました'; r.style.color = '#38a169'; }
						setTimeout(function() { location.reload(); }, 800);
					} else {
						if (r) { r.textContent = 'エラー: ' + (res.error || ''); r.style.color = '#e53e3e'; }
					}
				});
			});
		}

		/* ── リダイレクト削除 ── */
		document.querySelectorAll('.ap-redirect-delete').forEach(function(btn) {
			btn.addEventListener('click', function() {
				var index = this.getAttribute('data-index');
				if (!confirm('このリダイレクトを削除しますか？')) return;
				post('redirect_delete', { index: index }, function(res) {
					if (res.ok) location.reload();
					else alert('エラー: ' + (res.error || ''));
				});
			});
		});

		/* ── 差分デプロイ ZIP ── */
		var deployDiffBtn = document.getElementById('ap-static-deploy-diff');
		if (deployDiffBtn) {
			deployDiffBtn.addEventListener('click', function() {
				var fd = new FormData();
				fd.append('ap_action', 'deploy_diff');
				fd.append('csrf', csrf);
				fetch('./', { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf }, body: fd })
					.then(function(r) {
						if (r.headers.get('content-type') && r.headers.get('content-type').indexOf('application/zip') !== -1) {
							return r.blob().then(function(blob) {
								var a = document.createElement('a');
								var url = URL.createObjectURL(blob);
								a.href = url;
								a.download = 'deploy-diff.zip';
								a.click();
								setTimeout(function() { URL.revokeObjectURL(url); }, 1000);
							});
						} else {
							return r.json().then(function(data) {
								alert(data.error || 'エラーが発生しました');
							});
						}
					})
					.catch(function(e) { alert('エラー: ' + e.message); });
			});
		}
	});
})();
