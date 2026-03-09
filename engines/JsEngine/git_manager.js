/**
 * git_manager.js - Git 連携管理 UI
 *
 * ダッシュボードの Git 連携セクション用バニラ JS。
 * 依存: なし（ES5 互換）
 */
(function() {
	'use strict';

	var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

	function post(action, params, callback) {
		var fd = new FormData();
		fd.append('ap_action', action);
		fd.append('csrf', csrf);
		for (var k in params) {
			if (params.hasOwnProperty(k)) fd.append(k, params[k]);
		}
		fetch('./', { method: 'POST', body: fd })
			.then(function(r) { return r.json(); })
			.then(callback)
			.catch(function(e) { callback({ ok: false, error: e.message }); });
	}

	function showResult(elId, res, successMsg) {
		var el = document.getElementById(elId);
		if (!el) return;
		if (res.ok) {
			el.style.color = '#38a169';
			el.textContent = successMsg || '成功';
		} else {
			el.style.color = '#e53e3e';
			el.textContent = 'エラー: ' + (res.error || '不明なエラー');
		}
	}

	document.addEventListener('DOMContentLoaded', function() {

		/* ── 設定保存 ── */
		var saveBtn = document.getElementById('ap-git-save');
		if (saveBtn) {
			saveBtn.addEventListener('click', function() {
				var token = (document.getElementById('ap-git-token') || {}).value || '';
				post('git_configure', {
					repository:     (document.getElementById('ap-git-repository') || {}).value || '',
					token:          token,
					branch:         (document.getElementById('ap-git-branch') || {}).value || 'main',
					content_dir:    (document.getElementById('ap-git-content-dir') || {}).value || 'content',
					enabled:        '1',
					issues_enabled: (document.getElementById('ap-git-issues') || {}).checked ? '1' : '',
					webhook_secret: (document.getElementById('ap-git-webhook-secret') || {}).value || '',
				}, function(res) {
					showResult('ap-git-config-result', res, res.message || '保存しました');
					if (res.ok) setTimeout(function() { location.reload(); }, 1200);
				});
			});
		}

		/* ── 接続テスト ── */
		var testBtn = document.getElementById('ap-git-test');
		if (testBtn) {
			testBtn.addEventListener('click', function() {
				var resultEl = document.getElementById('ap-git-config-result');
				if (resultEl) { resultEl.style.color = '#718096'; resultEl.textContent = 'テスト中...'; }
				post('git_test', {}, function(res) {
					if (res.ok) {
						showResult('ap-git-config-result', res,
							'✓ 接続成功: ' + (res.name || '') + ' (' + (res.default_branch || 'main') + ')');
					} else {
						showResult('ap-git-config-result', res);
					}
				});
			});
		}

		/* ── Pull ── */
		var pullBtn = document.getElementById('ap-git-pull');
		if (pullBtn) {
			pullBtn.addEventListener('click', function() {
				var resultEl = document.getElementById('ap-git-sync-result');
				if (resultEl) { resultEl.style.color = '#718096'; resultEl.textContent = 'Pull 中...'; }
				pullBtn.disabled = true;
				post('git_pull', {}, function(res) {
					pullBtn.disabled = false;
					if (res.ok) {
						var msg = 'Pull 完了: ' + (res.downloaded || 0) + '件ダウンロード, '
							+ (res.skipped || 0) + '件スキップ';
						showResult('ap-git-sync-result', res, msg);
					} else {
						showResult('ap-git-sync-result', res);
					}
				});
			});
		}

		/* ── Push ── */
		var pushBtn = document.getElementById('ap-git-push');
		if (pushBtn) {
			pushBtn.addEventListener('click', function() {
				var msg = (document.getElementById('ap-git-commit-msg') || {}).value || '';
				if (!confirm('ローカルのコンテンツを GitHub にプッシュしますか？')) return;
				var resultEl = document.getElementById('ap-git-sync-result');
				if (resultEl) { resultEl.style.color = '#718096'; resultEl.textContent = 'Push 中...'; }
				pushBtn.disabled = true;
				post('git_push', { message: msg }, function(res) {
					pushBtn.disabled = false;
					if (res.ok) {
						var text = 'Push 完了: ' + (res.uploaded || 0) + '件アップロード'
							+ (res.commit_sha ? ' (コミット: ' + res.commit_sha.substring(0, 7) + ')' : '');
						showResult('ap-git-sync-result', res, text);
					} else {
						showResult('ap-git-sync-result', res);
					}
				});
			});
		}

		/* ── コミット履歴 ── */
		var logBtn = document.getElementById('ap-git-load-log');
		if (logBtn) {
			logBtn.addEventListener('click', function() {
				var logEl = document.getElementById('ap-git-log');
				if (!logEl) return;
				logEl.innerHTML = '<span style="color:#718096;">読み込み中...</span>';
				post('git_log', { limit: '15' }, function(res) {
					if (!res.ok || !res.commits) {
						logEl.innerHTML = '<span style="color:#e53e3e;">履歴の取得に失敗しました</span>';
						return;
					}
					if (res.commits.length === 0) {
						logEl.innerHTML = '<span style="color:#718096;">コミットがありません</span>';
						return;
					}
					var html = '';
					res.commits.forEach(function(c) {
						var date = c.date ? new Date(c.date).toLocaleString('ja-JP') : '';
						html += '<div style="padding:6px 0;border-bottom:1px solid #f0f2f5;font-size:13px;">'
							+ '<code style="color:#3182ce;margin-right:8px;">' + escHtml(c.sha) + '</code>'
							+ '<span>' + escHtml(c.message.split('\n')[0]) + '</span>'
							+ '<br><span style="color:#718096;font-size:11px;">'
							+ escHtml(c.author) + ' — ' + escHtml(date) + '</span>'
							+ '</div>';
					});
					logEl.innerHTML = html;
				});
			});
		}

		function escHtml(s) {
			var el = document.createElement('span');
			el.textContent = s || '';
			return el.innerHTML;
		}
	});
})();
