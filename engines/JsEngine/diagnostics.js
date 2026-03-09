/**
 * diagnostics.js - 診断データ管理 UI
 *
 * ダッシュボードの診断データセクションを制御。
 * 有効/無効切替、レベル変更、ログ表示、プレビュー、手動送信。
 */
(function () {
	'use strict';

	const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

	function post(action, params = {}) {
		const body = new URLSearchParams({ ap_action: action, csrf: csrf(), ...params });
		return fetch('./', { method: 'POST', body, headers: { 'X-CSRF-Token': csrf() } })
			.then(r => r.json());
	}

	function h(s) {
		const d = document.createElement('div');
		d.textContent = s;
		return d.innerHTML;
	}

	function formatTime(iso) {
		if (!iso) return '-';
		const d = new Date(iso);
		return d.toLocaleString('ja-JP');
	}

	/* ── 初期化 ── */
	function init() {
		const section = document.getElementById('ap-diag-section');
		if (!section) return;

		loadSummary();
		bindEvents();
	}

	/* ── サマリー読み込み ── */
	function loadSummary() {
		post('diag_get_summary').then(res => {
			if (!res.ok) return;
			const d = res.data;

			/* ステータス表示 */
			const statusEl = document.getElementById('ap-diag-status');
			if (statusEl) {
				statusEl.innerHTML = d.enabled
					? '<span style="color:#38a169;font-weight:bold;">有効</span>'
					: '<span style="color:#e53e3e;font-weight:bold;">無効</span>';
			}

			/* トグルボタン */
			const toggleBtn = document.getElementById('ap-diag-toggle');
			if (toggleBtn) {
				toggleBtn.textContent = d.enabled ? '無効にする' : '有効にする';
				toggleBtn.dataset.enabled = d.enabled ? '1' : '0';
			}

			/* レベル選択 */
			const levelSelect = document.getElementById('ap-diag-level');
			if (levelSelect) levelSelect.value = d.level || 'basic';

			/* インストールID */
			const idEl = document.getElementById('ap-diag-install-id');
			if (idEl) idEl.textContent = d.install_id || '-';

			/* 最終送信 */
			const lastEl = document.getElementById('ap-diag-last-sent');
			if (lastEl) lastEl.textContent = formatTime(d.last_sent);

			/* エラー件数 */
			const errEl = document.getElementById('ap-diag-error-count');
			if (errEl) errEl.textContent = d.error_count || '0';

			/* ログ件数 */
			const logEl = document.getElementById('ap-diag-log-count');
			if (logEl) logEl.textContent = d.log_count || '0';

			/* エラー種別サマリー */
			const typesEl = document.getElementById('ap-diag-error-types');
			if (typesEl && d.error_types) {
				const entries = Object.entries(d.error_types);
				if (entries.length === 0) {
					typesEl.innerHTML = '<span style="color:#718096;">エラーなし</span>';
				} else {
					typesEl.innerHTML = entries.map(([t, c]) =>
						'<span class="ap-diag-badge">' + h(t) + ': ' + c + '</span>'
					).join(' ');
				}
			}

			/* 直近のエラー一覧 */
			renderRecentErrors(d.recent_errors || []);
			renderRecentLogs(d.recent_logs || []);
		}).catch(() => {});
	}

	/* ── 直近エラー一覧 ── */
	function renderRecentErrors(errors) {
		const el = document.getElementById('ap-diag-recent-errors');
		if (!el) return;
		if (errors.length === 0) {
			el.innerHTML = '<p style="color:#718096;font-size:13px;">直近のエラーはありません。</p>';
			return;
		}
		let html = '<table class="ap-diag-table"><thead><tr><th>日時</th><th>種別</th><th>メッセージ</th><th>ファイル</th></tr></thead><tbody>';
		errors.reverse().forEach(e => {
			html += '<tr>'
				+ '<td>' + h(formatTime(e.timestamp)) + '</td>'
				+ '<td><code>' + h(e.type || '') + '</code></td>'
				+ '<td>' + h(e.message || '') + '</td>'
				+ '<td>' + h((e.file || '') + (e.line ? ':' + e.line : '')) + '</td>'
				+ '</tr>';
		});
		html += '</tbody></table>';
		el.innerHTML = html;
	}

	/* ── 直近カスタムログ一覧 ── */
	function renderRecentLogs(logs) {
		const el = document.getElementById('ap-diag-recent-logs');
		if (!el) return;
		if (logs.length === 0) {
			el.innerHTML = '<p style="color:#718096;font-size:13px;">直近のログはありません。</p>';
			return;
		}
		let html = '<table class="ap-diag-table"><thead><tr><th>日時</th><th>カテゴリ</th><th>メッセージ</th></tr></thead><tbody>';
		logs.reverse().forEach(l => {
			html += '<tr>'
				+ '<td>' + h(formatTime(l.timestamp)) + '</td>'
				+ '<td><code>' + h(l.category || '') + '</code></td>'
				+ '<td>' + h(l.message || '') + '</td>'
				+ '</tr>';
		});
		html += '</tbody></table>';
		el.innerHTML = html;
	}

	/* ── イベントバインド ── */
	function bindEvents() {
		/* 有効/無効トグル */
		document.getElementById('ap-diag-toggle')?.addEventListener('click', function () {
			const newEnabled = this.dataset.enabled === '0';
			post('diag_set_enabled', { enabled: newEnabled ? '1' : '0' }).then(res => {
				if (res.ok) loadSummary();
				showResult(res.ok ? (newEnabled ? '有効にしました' : '無効にしました') : (res.error || 'エラー'));
			});
		});

		/* レベル変更 */
		document.getElementById('ap-diag-level')?.addEventListener('change', function () {
			post('diag_set_level', { level: this.value }).then(res => {
				showResult(res.ok ? 'レベルを ' + this.value + ' に変更しました' : (res.error || 'エラー'));
			});
		});

		/* プレビュー */
		document.getElementById('ap-diag-preview')?.addEventListener('click', function () {
			post('diag_preview').then(res => {
				const el = document.getElementById('ap-diag-preview-data');
				if (el && res.ok) {
					el.style.display = 'block';
					el.querySelector('pre').textContent = JSON.stringify(res.data, null, 2);
				}
			});
		});

		/* 今すぐ送信 */
		document.getElementById('ap-diag-send-now')?.addEventListener('click', function () {
			if (!confirm('診断データを今すぐ開発元へ送信しますか？')) return;
			this.disabled = true;
			post('diag_send_now').then(res => {
				this.disabled = false;
				showResult(res.ok ? '送信しました' : (res.error || '送信失敗'));
				if (res.ok) loadSummary();
			}).catch(() => { this.disabled = false; });
		});

		/* ログクリア */
		document.getElementById('ap-diag-clear-logs')?.addEventListener('click', function () {
			if (!confirm('全ての診断ログをクリアしますか？')) return;
			post('diag_clear_logs').then(res => {
				showResult(res.ok ? 'ログをクリアしました' : (res.error || 'エラー'));
				if (res.ok) loadSummary();
			});
		});

		/* 全ログ表示 */
		document.getElementById('ap-diag-show-all-logs')?.addEventListener('click', function () {
			post('diag_get_logs').then(res => {
				const el = document.getElementById('ap-diag-all-logs');
				if (el && res.ok) {
					el.style.display = 'block';
					el.querySelector('pre').textContent = JSON.stringify(res.data, null, 2);
				}
			});
		});

		/* 初回通知バナーを閉じる */
		document.getElementById('ap-diag-notice-dismiss')?.addEventListener('click', function () {
			const banner = document.getElementById('ap-diag-notice');
			if (banner) banner.style.display = 'none';
		});
	}

	function showResult(msg) {
		const el = document.getElementById('ap-diag-result');
		if (el) {
			el.textContent = msg;
			setTimeout(() => { el.textContent = ''; }, 5000);
		}
	}

	/* DOM Ready */
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
