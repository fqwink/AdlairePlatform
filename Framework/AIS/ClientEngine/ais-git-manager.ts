/// <reference lib="dom" />
/// <reference path="../../ACS/ClientEngine/browser-types.d.ts" />
/**
 * git_manager.ts - Git 連携管理 UI
 *
 * ダッシュボードの Git 連携セクション用バニラ JS。
 * 依存: ap-utils.js (AP.post, AP.escHtml)
 */
(function () {
	'use strict';

	const post = AP.post;

	function showResult(elId: string, res: APResponse, successMsg?: string): void {
		const el = document.getElementById(elId);
		if (!el) return;
		if (res.ok) {
			el.style.color = '#38a169';
			el.textContent = successMsg || '成功';
		} else {
			el.style.color = '#e53e3e';
			el.textContent = 'エラー: ' + (res.error || '不明なエラー');
		}
	}

	document.addEventListener('DOMContentLoaded', function () {

		/* ── 設定保存 ── */
		const saveBtn = document.getElementById('ap-git-save') as HTMLButtonElement | null;
		if (saveBtn) {
			saveBtn.addEventListener('click', function () {
				const token = (document.getElementById('ap-git-token') as HTMLInputElement | null)?.value ?? '';
				post('git_configure', {
					repository:     (document.getElementById('ap-git-repository') as HTMLInputElement | null)?.value ?? '',
					token:          token,
					branch:         (document.getElementById('ap-git-branch') as HTMLInputElement | null)?.value ?? 'main',
					content_dir:    (document.getElementById('ap-git-content-dir') as HTMLInputElement | null)?.value ?? 'content',
					enabled:        '1',
					issues_enabled: (document.getElementById('ap-git-issues') as HTMLInputElement | null)?.checked ? '1' : '',
					webhook_secret: (document.getElementById('ap-git-webhook-secret') as HTMLInputElement | null)?.value ?? '',
				}, function (res: APResponse) {
					showResult('ap-git-config-result', res, (res.message as string) || '保存しました');
					if (res.ok) setTimeout(function () { location.reload(); }, 1200);
				});
			});
		}

		/* ── 接続テスト ── */
		const testBtn = document.getElementById('ap-git-test') as HTMLButtonElement | null;
		if (testBtn) {
			testBtn.addEventListener('click', function () {
				const resultEl = document.getElementById('ap-git-config-result');
				if (resultEl) { resultEl.style.color = '#718096'; resultEl.textContent = 'テスト中...'; }
				post('git_test', {}, function (res: APResponse) {
					if (res.ok) {
						showResult('ap-git-config-result', res,
							'✓ 接続成功: ' + ((res.name as string) || '') + ' (' + ((res.default_branch as string) || 'main') + ')');
					} else {
						showResult('ap-git-config-result', res);
					}
				});
			});
		}

		/* ── Pull ── */
		const pullBtn = document.getElementById('ap-git-pull') as HTMLButtonElement | null;
		if (pullBtn) {
			pullBtn.addEventListener('click', function () {
				const resultEl = document.getElementById('ap-git-sync-result');
				if (resultEl) { resultEl.style.color = '#718096'; resultEl.textContent = 'Pull 中...'; }
				pullBtn.disabled = true;
				post('git_pull', {}, function (res: APResponse) {
					pullBtn.disabled = false;
					if (res.ok) {
						const msg = 'Pull 完了: ' + ((res.downloaded as number) || 0) + '件ダウンロード, '
							+ ((res.skipped as number) || 0) + '件スキップ';
						showResult('ap-git-sync-result', res, msg);
					} else {
						showResult('ap-git-sync-result', res);
					}
				});
			});
		}

		/* ── Push ── */
		const pushBtn = document.getElementById('ap-git-push') as HTMLButtonElement | null;
		if (pushBtn) {
			pushBtn.addEventListener('click', function () {
				const msg = (document.getElementById('ap-git-commit-msg') as HTMLInputElement | null)?.value ?? '';
				if (!confirm('ローカルのコンテンツを GitHub にプッシュしますか？')) return;
				const resultEl = document.getElementById('ap-git-sync-result');
				if (resultEl) { resultEl.style.color = '#718096'; resultEl.textContent = 'Push 中...'; }
				pushBtn.disabled = true;
				post('git_push', { message: msg }, function (res: APResponse) {
					pushBtn.disabled = false;
					if (res.ok) {
						const text = 'Push 完了: ' + ((res.uploaded as number) || 0) + '件アップロード'
							+ ((res.commit_sha as string) ? ' (コミット: ' + (res.commit_sha as string).substring(0, 7) + ')' : '');
						showResult('ap-git-sync-result', res, text);
					} else {
						showResult('ap-git-sync-result', res);
					}
				});
			});
		}

		/* ── コミット履歴 ── */
		const logBtn = document.getElementById('ap-git-load-log') as HTMLButtonElement | null;
		if (logBtn) {
			logBtn.addEventListener('click', function () {
				const logEl = document.getElementById('ap-git-log');
				if (!logEl) return;
				logEl.innerHTML = '<span style="color:#718096;">読み込み中...</span>';
				post('git_log', { limit: '15' }, function (res: APResponse) {
					const commits = (res as APResponse & { commits?: Array<{ sha: string; message: string; author: string; date?: string }> }).commits;
					if (!res.ok || !commits) {
						logEl.innerHTML = '<span style="color:#e53e3e;">履歴の取得に失敗しました</span>';
						return;
					}
					if (commits.length === 0) {
						logEl.innerHTML = '<span style="color:#718096;">コミットがありません</span>';
						return;
					}
					let html = '';
					commits.forEach(function (c) {
						const date = c.date ? new Date(c.date).toLocaleString('ja-JP') : '';
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

		const escHtml = AP.escHtml;
	});
})();
