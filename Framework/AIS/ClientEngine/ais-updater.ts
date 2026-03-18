/// <reference lib="dom" />
/// <reference path="../../ACS/ClientEngine/browser-types.d.ts" />
/**
 * updater.ts - アップデート管理 UI
 *
 * ダッシュボードのアップデートセクション用。
 * 依存: ap-utils.js (AP.getCsrf, AP.escHtml)
 */
(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		const csrf = AP.getCsrf();
		if (!csrf) return; /* CSRF メタタグが存在しない（非ログインページ等）場合は何もしない */

		const esc = AP.escHtml;

		/* ── DOM 要素キャッシュ ── */
		const statusEl = document.getElementById('ap-update-status');
		const resultEl = document.getElementById('ap-update-result');

		/* ── インライン通知ヘルパー ── */
		function notify(msg: string, type: 'error' | 'success' | 'info'): void {
			if (!resultEl) return;
			const cls = type === 'error' ? 'color:#c0392b;' : type === 'success' ? 'color:#27ae60;' : 'color:#2d3748;';
			const el = document.createElement('div');
			el.style.cssText = 'padding:8px 12px;margin:4px 0;border-radius:4px;font-size:13px;background:#f7fafc;border:1px solid #e2e8f0;' + cls;
			el.textContent = msg;
			resultEl.appendChild(el);
			setTimeout(function () { if (el.parentNode) el.style.opacity = '0.6'; }, 8000);
		}

		/* ── POST ヘルパー（固有のエラーパース処理あり） ── */
		function post(params: Record<string, string>): Promise<APResponse> {
			const c = AP.getCsrf();
			params.csrf = c;
			return fetch('/', {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': c },
				body: new URLSearchParams(params)
			}).then(function (r) {
				if (!r.ok) return r.text().then(function (t) {
					try { const d = JSON.parse(t); throw new Error(d.error || 'HTTP ' + r.status); }
					catch (e) { if (e instanceof Error && e.message && !e.message.startsWith('HTTP ')) throw e; throw new Error('HTTP ' + r.status); }
				});
				return r.json();
			});
		}

		function fmtDate(name: string): string {
			const m = name.match(/^(\d{4})(\d{2})(\d{2})_(\d{2})(\d{2})(\d{2})$/);
			if (!m) return name;
			return m[1] + '-' + m[2] + '-' + m[3] + ' ' + m[4] + ':' + m[5] + ':' + m[6];
		}

		function fmtSize(bytes: number): string {
			if (bytes < 1024)        return bytes + ' B';
			if (bytes < 1024 * 1024) return Math.round(bytes / 1024) + ' KB';
			return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
		}

		/* ── 更新確認ボタン ── */
		const checkBtn = document.getElementById('ap-check-update') as HTMLButtonElement | null;
		if (checkBtn) {
			checkBtn.addEventListener('click', function (this: HTMLButtonElement) {
				const btn = this;
				btn.disabled = true;
				if (statusEl) statusEl.textContent = '確認中...';
				if (resultEl) resultEl.innerHTML = '';

				post({ ap_action: 'check' })
					.then(function (data: APResponse) {
						if (statusEl) statusEl.textContent = '';
						if (data.error) {
							if (resultEl) resultEl.innerHTML =
								'<span style="color:#c0392b;">エラー: ' + esc(data.error) + '</span>';
						} else if (data.update_available) {
							if (resultEl) resultEl.innerHTML =
								'<b style="color:#27ae60;">バージョン ' + esc(data.latest) + ' が利用可能です</b>' +
								'（現在: ' + esc(data.current) + '）<br>' +
								'<button id="ap-apply-update"' +
								' data-zip="' + esc(data.zip_url) + '"' +
								' data-version="' + esc(data.latest) + '"' +
								' style="margin-top:8px;cursor:pointer;">今すぐ更新する</button>' +
								' <button id="ap-list-backups" style="margin-top:8px;cursor:pointer;">ロールバック</button>';
						} else {
							if (resultEl) resultEl.innerHTML =
								'<span style="color:#555;">最新バージョン ' + esc(data.current) + ' を使用中です。</span><br>' +
								'<button id="ap-list-backups" style="margin-top:8px;cursor:pointer;">ロールバック</button>';
						}
					})
					.catch(function () {
						if (statusEl) statusEl.textContent = '通信エラーが発生しました。';
					})
					.finally(function () { btn.disabled = false; });
			});
		}

		/* ── 動的生成ボタン用イベント委譲 ── */
		document.addEventListener('click', function (e: MouseEvent) {
			const t = e.target as HTMLElement | null;
			if (!t) return;

			/* 更新適用ボタン */
			if (t.id === 'ap-apply-update') {
				const btn     = t as HTMLButtonElement;
				const zip_url = btn.dataset.zip || '';
				const version = btn.dataset.version || '';
				btn.disabled = true;
				btn.textContent = '環境確認中...';

				post({ ap_action: 'check_env' })
					.then(function (env: APResponse) {
						if (!env.ok) {
							const issues: string[] = [];
							if (!env.ziparchive) issues.push('ZipArchive 拡張が無効です');
							if (!env.url_fopen)  issues.push('allow_url_fopen が無効です');
							if (!env.writable)   issues.push('ディレクトリへの書き込み権限がありません');
							notify('更新を実行できません: ' + issues.join(' / '), 'error');
							btn.disabled = false;
							btn.textContent = '今すぐ更新する';
							return;
						}
						const diskFree = env.disk_free as number;
						const diskMsg = diskFree >= 0
							? '（空き容量: ' + fmtSize(diskFree) + '）' : '';
						if (!confirm('アップデートを適用します。' + diskMsg + '\n事前にバックアップが自動作成されます。よろしいですか？')) {
							btn.disabled = false;
							btn.textContent = '今すぐ更新する';
							return;
						}
						btn.textContent = '更新中...';
						return post({ ap_action: 'apply', zip_url: zip_url, version: version })
							.then(function (data: APResponse) {
								if (data.error) {
									notify('更新エラー: ' + data.error, 'error');
									btn.disabled = false;
									btn.textContent = '今すぐ更新する';
								} else {
									notify((data.message as string) || 'アップデートが完了しました。ページをリロードします...', 'success');
									setTimeout(function () { location.reload(); }, 1500);
								}
							});
					})
					.catch(function (err: Error) {
						notify('エラー: ' + (err.message || '更新中にエラーが発生しました。'), 'error');
						btn.disabled = false;
						btn.textContent = '今すぐ更新する';
					});
			}

			/* バックアップ一覧ボタン */
			if (t.id === 'ap-list-backups') {
				const btn = t as HTMLButtonElement;
				btn.disabled = true;
				const ex = document.getElementById('ap-backup-list');
				if (ex) ex.remove();

				interface BackupMeta {
					created_at?: string;
					version_before?: string;
					size_bytes?: number;
				}
				interface BackupEntry {
					name: string;
					meta?: BackupMeta;
				}

				post({ ap_action: 'list_backups' })
					.then(function (data: APResponse) {
						let html = '';
						const backups = (data as APResponse & { backups?: BackupEntry[] }).backups;
						if (backups && backups.length > 0) {
							html = '<b>バックアップ一覧:</b>' +
								'<table style="margin-top:6px;border-collapse:collapse;font-size:0.9em;">' +
								'<tr style="background:#eee;">' +
								'<th style="padding:3px 8px;text-align:left;">作成日時</th>' +
								'<th style="padding:3px 8px;text-align:left;">更新前</th>' +
								'<th style="padding:3px 8px;text-align:right;">サイズ</th>' +
								'<th style="padding:3px 8px;"></th></tr>';
							backups.forEach(function (b) {
								const name = b.name;
								const meta = b.meta || {};
								const date = meta.created_at    ? esc(meta.created_at)           : esc(fmtDate(name));
								const ver  = meta.version_before ? esc(meta.version_before)      : '―';
								const size = meta.size_bytes != null && meta.size_bytes >= 0 ? fmtSize(meta.size_bytes) : '―';
								html += '<tr>' +
									'<td style="padding:3px 8px;">' + date + '</td>' +
									'<td style="padding:3px 8px;">' + ver  + '</td>' +
									'<td style="padding:3px 8px;text-align:right;">' + size + '</td>' +
									'<td style="padding:3px 8px;white-space:nowrap;">' +
									'<button class="ap-do-rollback" data-name="' + esc(name) + '" style="cursor:pointer;">復元</button> ' +
									'<button class="ap-delete-backup" data-name="' + esc(name) + '" style="cursor:pointer;color:#c0392b;">削除</button>' +
									'</td></tr>';
							});
							html += '</table>';
						} else {
							html = '<span style="color:#555;">バックアップはありません。</span>';
						}
						if (resultEl) resultEl.insertAdjacentHTML('beforeend', '<div id="ap-backup-list" style="margin-top:10px;">' + html + '</div>');
					})
					.catch(function () { notify('バックアップ一覧の取得に失敗しました。', 'error'); })
					.finally(function () { btn.disabled = false; });
			}

			/* ロールバック実行ボタン */
			if (t.classList.contains('ap-do-rollback')) {
				const btn  = t as HTMLButtonElement;
				const name = btn.dataset.name || '';
				if (!confirm('バックアップ "' + name + '" に復元します。\n現在のファイルは上書きされます。よろしいですか？')) return;
				btn.disabled = true;
				btn.textContent = '復元中...';
				post({ ap_action: 'rollback', backup: name })
					.then(function (data: APResponse) {
						if (data.error) {
							notify('復元エラー: ' + data.error, 'error');
							btn.disabled = false;
							btn.textContent = '復元';
						} else {
							notify((data.message as string) || 'ロールバックが完了しました。ページをリロードします...', 'success');
							setTimeout(function () { location.reload(); }, 1500);
						}
					})
					.catch(function (err: Error) {
						notify('復元エラー: ' + (err.message || '復元中にエラーが発生しました。'), 'error');
						btn.disabled = false;
						btn.textContent = '復元';
					});
			}

			/* バックアップ削除ボタン */
			if (t.classList.contains('ap-delete-backup')) {
				const btn  = t as HTMLButtonElement;
				const name = btn.dataset.name || '';
				if (!confirm('バックアップ "' + name + '" を削除します。この操作は取り消せません。よろしいですか？')) return;
				btn.disabled = true;
				btn.textContent = '削除中...';
				post({ ap_action: 'delete_backup', backup: name })
					.then(function (data: APResponse) {
						if (data.error) {
							notify('削除エラー: ' + data.error, 'error');
							btn.disabled = false;
							btn.textContent = '削除';
						} else {
							const row = btn.closest('tr');
							if (row) row.remove();
						}
					})
					.catch(function (err: Error) {
						notify('削除エラー: ' + (err.message || '削除中にエラーが発生しました。'), 'error');
						btn.disabled = false;
						btn.textContent = '削除';
					});
			}
		});
	});
})();
