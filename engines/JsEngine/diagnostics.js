/**
 * diagnostics.js - 診断データ管理 UI
 *
 * ダッシュボードの診断データセクションを制御。
 * 有効/無効切替、レベル変更、ログ表示、プレビュー、手動送信。
 * Ver.1.4 強化: パフォーマンスプロファイラ、セキュリティサマリー、ヘルスインジケーター。
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

			/* サーキットブレーカー */
			const cbEl = document.getElementById('ap-diag-circuit-breaker');
			if (cbEl) {
				cbEl.style.display = d.circuit_breaker ? 'block' : 'none';
			}

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

			/* セキュリティサマリー */
			renderSecuritySummary(d.security || {});

			/* パフォーマンスプロファイラ */
			renderTimings(d.timings || {});

			/* 直近のエラー一覧 */
			renderRecentErrors(d.recent_errors || []);
			renderRecentLogs(d.recent_logs || []);

			/* ヘルスインジケーター */
			loadHealth();
		}).catch(() => {});
	}

	/* ── ヘルスチェック ── */
	function loadHealth() {
		post('diag_health').then(res => {
			if (!res.ok) return;
			const h = res.data;
			const el = document.getElementById('ap-diag-health-status');
			if (!el) return;

			const colors = { ok: '#38a169', warning: '#d69e2e', critical: '#e53e3e' };
			const labels = { ok: 'OK', warning: 'WARNING', critical: 'CRITICAL' };
			const status = h.status || 'ok';
			el.innerHTML = '<span style="color:' + (colors[status] || '#718096')
				+ ';font-weight:bold;font-size:16px;">' + (labels[status] || status).toUpperCase() + '</span>';

			const diagEl = document.getElementById('ap-diag-health-details');
			if (diagEl && h.diagnostics) {
				const d = h.diagnostics;
				diagEl.innerHTML =
					'<span>エラー(24h): <strong>' + (d.errors_24h || 0) + '</strong></span>'
					+ '<span style="margin-left:16px;">ディスク空き: <strong>' + (d.disk_free_mb != null ? d.disk_free_mb + ' MB' : '-') + '</strong></span>'
					+ '<span style="margin-left:16px;">メモリピーク: <strong>' + (d.memory_peak_mb || '-') + ' MB</strong></span>';
			}
		}).catch(() => {});
	}

	/* ── セキュリティサマリー ── */
	function renderSecuritySummary(sec) {
		const el = document.getElementById('ap-diag-security');
		if (!el) return;
		const items = [
			{ label: 'ログイン失敗', count: sec.login_failure || 0, color: '#e53e3e' },
			{ label: 'ロックアウト', count: sec.lockout || 0, color: '#c53030' },
			{ label: 'レート制限', count: sec.rate_limit || 0, color: '#d69e2e' },
			{ label: 'SSRF ブロック', count: sec.ssrf_blocked || 0, color: '#e53e3e' },
		];
		const total = items.reduce((s, i) => s + i.count, 0);
		if (total === 0) {
			el.innerHTML = '<span style="color:#38a169;font-size:13px;">セキュリティイベントなし</span>';
			return;
		}
		el.innerHTML = items
			.filter(i => i.count > 0)
			.map(i => '<span class="ap-diag-badge" style="background:' + i.color + '20;color:' + i.color + ';">' + h(i.label) + ': ' + i.count + '</span>')
			.join(' ');
	}

	/* ── パフォーマンスプロファイラ ── */
	function renderTimings(timingsData) {
		const el = document.getElementById('ap-diag-timings');
		if (!el) return;
		const timings = timingsData.timings_ms || {};
		const entries = Object.entries(timings).filter(([k]) => k !== 'request_total');
		const requestTotal = timings.request_total;

		if (entries.length === 0 && !requestTotal) {
			el.innerHTML = '<span style="color:#718096;font-size:13px;">計測データなし（次回リクエストで記録されます）</span>';
			return;
		}

		const maxMs = Math.max(...entries.map(([, v]) => v), 1);
		let html = '';

		if (requestTotal != null) {
			html += '<div style="font-size:13px;margin-bottom:8px;">リクエスト合計: <strong>' + requestTotal.toFixed(1) + ' ms</strong>';
			if (timingsData.memory_peak_human) {
				html += ' | メモリピーク: <strong>' + h(timingsData.memory_peak_human) + '</strong>';
			}
			html += '</div>';
		}

		entries.forEach(([label, ms]) => {
			const pct = Math.max((ms / maxMs) * 100, 2);
			html += '<div class="ap-diag-timing-row">'
				+ '<span class="ap-diag-timing-label">' + h(label) + '</span>'
				+ '<div class="ap-diag-timing-bar-bg"><div class="ap-diag-timing-bar" style="width:' + pct.toFixed(0) + '%;"></div></div>'
				+ '<span class="ap-diag-timing-value">' + ms.toFixed(1) + ' ms</span>'
				+ '</div>';
		});

		el.innerHTML = html;
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
