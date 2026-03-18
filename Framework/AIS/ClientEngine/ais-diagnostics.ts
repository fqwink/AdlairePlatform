/// <reference lib="dom" />
/// <reference path="../../ACS/ClientEngine/browser-types.d.ts" />
/**
 * diagnostics.ts - 診断データ管理 UI
 *
 * ダッシュボードの診断データセクションを制御。
 * 有効/無効切替、レベル変更、ログ表示、プレビュー、手動送信。
 * Ver.1.4 強化: パフォーマンスプロファイラ、セキュリティサマリー、ヘルスインジケーター。
 * 依存: ap-utils.js (AP.postAction, AP.escHtml)
 */
(function () {
	'use strict';

	const post = (action: string, params?: Record<string, string>): Promise<APResponse> => AP.postAction(action, params);
	const h = AP.escHtml;

	function formatTime(iso: string | undefined): string {
		if (!iso) return '-';
		const d = new Date(iso);
		return d.toLocaleString('ja-JP');
	}

	interface DiagSummaryData {
		enabled: boolean;
		level?: string;
		install_id?: string;
		last_sent?: string;
		error_count?: number | string;
		log_count?: number | string;
		realtime_send?: boolean;
		retention_days?: number;
		circuit_breaker?: boolean;
		error_types?: Record<string, number>;
		security?: SecurityData;
		timings?: TimingsData;
		trends?: TrendsData;
		recent_errors?: ErrorEntry[];
		recent_logs?: LogEntry[];
	}

	interface SecurityData {
		login_failure?: number;
		lockout?: number;
		rate_limit?: number;
		ssrf_blocked?: number;
	}

	interface TimingsData {
		timings_ms?: Record<string, number>;
		memory_peak_human?: string;
	}

	interface TrendsData {
		days?: TrendDay[];
		trend_direction?: string;
		spike_detected?: boolean;
	}

	interface TrendDay {
		date: string;
		total: number;
	}

	interface ErrorEntry {
		timestamp?: string;
		type?: string;
		message?: string;
		file?: string;
		line?: number;
	}

	interface LogEntry {
		timestamp?: string;
		category?: string;
		message?: string;
	}

	interface HealthData {
		status?: string;
		diagnostics?: {
			errors_24h?: number;
			disk_free_mb?: number | null;
			memory_peak_mb?: string | number;
		};
	}

	/* ── 初期化 ── */
	function init(): void {
		const section = document.getElementById('ap-diag-section');
		if (!section) return;

		loadSummary();
		bindEvents();
	}

	/* ── サマリー読み込み ── */
	function loadSummary(): void {
		post('diag_get_summary').then(res => {
			if (!res.ok) return;
			const d = res.data as DiagSummaryData;

			/* ステータス表示 */
			const statusEl = document.getElementById('ap-diag-status');
			if (statusEl) {
				statusEl.innerHTML = d.enabled
					? '<span style="color:#38a169;font-weight:bold;">有効</span>'
					: '<span style="color:#e53e3e;font-weight:bold;">無効</span>';
			}

			/* トグルボタン */
			const toggleBtn = document.getElementById('ap-diag-toggle') as HTMLButtonElement | null;
			if (toggleBtn) {
				toggleBtn.textContent = d.enabled ? '無効にする' : '有効にする';
				toggleBtn.dataset.enabled = d.enabled ? '1' : '0';
			}

			/* レベル選択 */
			const levelSelect = document.getElementById('ap-diag-level') as HTMLSelectElement | null;
			if (levelSelect) levelSelect.value = d.level || 'basic';

			/* インストールID */
			const idEl = document.getElementById('ap-diag-install-id');
			if (idEl) idEl.textContent = d.install_id || '-';

			/* 最終送信 */
			const lastEl = document.getElementById('ap-diag-last-sent');
			if (lastEl) lastEl.textContent = formatTime(d.last_sent);

			/* エラー件数 */
			const errEl = document.getElementById('ap-diag-error-count');
			if (errEl) errEl.textContent = String(d.error_count || '0');

			/* ログ件数 */
			const logEl = document.getElementById('ap-diag-log-count');
			if (logEl) logEl.textContent = String(d.log_count || '0');

			/* 保持ポリシー・送信モード */
			const retEl = document.getElementById('ap-diag-retention-info');
			if (retEl) {
				const mode = d.realtime_send ? 'リアルタイム' : '定期';
				retEl.textContent = mode + '送信 | ' + (d.retention_days || 14) + '日間保持';
			}

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

			/* エラートレンド */
			renderTrends(d.trends || {});

			/* 直近のエラー一覧 */
			renderRecentErrors(d.recent_errors || []);
			renderRecentLogs(d.recent_logs || []);

			/* ヘルスインジケーター */
			loadHealth();
		}).catch(() => {});
	}

	/* ── ヘルスチェック ── */
	function loadHealth(): void {
		post('diag_health').then(res => {
			if (!res.ok) return;
			const hd = res.data as HealthData;
			const el = document.getElementById('ap-diag-health-status');
			if (!el) return;

			const colors: Record<string, string> = { ok: '#38a169', warning: '#d69e2e', critical: '#e53e3e' };
			const labels: Record<string, string> = { ok: 'OK', warning: 'WARNING', critical: 'CRITICAL' };
			const status = hd.status || 'ok';
			el.innerHTML = '<span style="color:' + (colors[status] || '#718096')
				+ ';font-weight:bold;font-size:16px;">' + (labels[status] || status).toUpperCase() + '</span>';

			const diagEl = document.getElementById('ap-diag-health-details');
			if (diagEl && hd.diagnostics) {
				const dd = hd.diagnostics;
				diagEl.innerHTML =
					'<span>エラー(24h): <strong>' + (dd.errors_24h || 0) + '</strong></span>'
					+ '<span style="margin-left:16px;">ディスク空き: <strong>' + (dd.disk_free_mb != null ? dd.disk_free_mb + ' MB' : '-') + '</strong></span>'
					+ '<span style="margin-left:16px;">メモリピーク: <strong>' + (dd.memory_peak_mb || '-') + ' MB</strong></span>';
			}
		}).catch(() => {});
	}

	/* ── セキュリティサマリー ── */
	function renderSecuritySummary(sec: SecurityData): void {
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
	function renderTimings(timingsData: TimingsData): void {
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

	/* ── エラートレンド ── */
	function renderTrends(trends: TrendsData): void {
		const el = document.getElementById('ap-diag-trends');
		if (!el) return;
		const days = trends.days || [];
		if (days.length === 0) {
			el.innerHTML = '<span style="color:#718096;font-size:13px;">トレンドデータなし</span>';
			return;
		}

		const maxTotal = Math.max(...days.map(d => d.total), 1);
		let html = '<div class="ap-diag-trend-chart">';
		days.forEach(d => {
			const pct = Math.max((d.total / maxTotal) * 100, 2);
			/* 色: 平均の2倍超=赤, 1倍超=黄, それ以下=緑 */
			const avg = days.reduce((s, x) => s + x.total, 0) / days.length;
			let color = '#38a169';
			if (avg > 0 && d.total > avg * 2) color = '#e53e3e';
			else if (avg > 0 && d.total > avg) color = '#d69e2e';

			const dateLabel = d.date.slice(5); /* MM-DD */
			html += '<div class="ap-diag-trend-bar-wrap">'
				+ '<span class="ap-diag-trend-count">' + d.total + '</span>'
				+ '<div class="ap-diag-trend-bar" style="height:' + pct.toFixed(0) + '%;background:' + color + ';"></div>'
				+ '<span class="ap-diag-trend-date">' + h(dateLabel) + '</span>'
				+ '</div>';
		});
		html += '</div>';

		/* トレンド方向 */
		const dir = trends.trend_direction || 'stable';
		const dirLabels: Record<string, string> = { increasing: '増加傾向', stable: '安定', decreasing: '減少傾向' };
		const dirColors: Record<string, string> = { increasing: '#e53e3e', stable: '#38a169', decreasing: '#3182ce' };
		html += '<div class="ap-diag-trend-info">';
		html += '<span style="color:' + (dirColors[dir] || '#718096') + ';font-weight:600;">' + (dirLabels[dir] || dir) + '</span>';
		if (trends.spike_detected) {
			html += '<span style="color:#e53e3e;font-weight:600;">⚠ 急増検知</span>';
		}
		html += '</div>';

		el.innerHTML = html;
	}

	/* ── 直近エラー一覧 ── */
	function renderRecentErrors(errors: ErrorEntry[]): void {
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
	function renderRecentLogs(logs: LogEntry[]): void {
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
	function bindEvents(): void {
		/* 有効/無効トグル */
		(document.getElementById('ap-diag-toggle') as HTMLButtonElement | null)?.addEventListener('click', function (this: HTMLButtonElement) {
			const newEnabled = this.dataset.enabled === '0';
			post('diag_set_enabled', { enabled: newEnabled ? '1' : '0' }).then(res => {
				if (res.ok) loadSummary();
				showResult(res.ok ? (newEnabled ? '有効にしました' : '無効にしました') : (res.error || 'エラー'));
			});
		});

		/* レベル変更 */
		(document.getElementById('ap-diag-level') as HTMLSelectElement | null)?.addEventListener('change', function (this: HTMLSelectElement) {
			post('diag_set_level', { level: this.value }).then(res => {
				showResult(res.ok ? 'レベルを ' + this.value + ' に変更しました' : (res.error || 'エラー'));
			});
		});

		/* プレビュー */
		(document.getElementById('ap-diag-preview') as HTMLButtonElement | null)?.addEventListener('click', function () {
			post('diag_preview').then(res => {
				const el = document.getElementById('ap-diag-preview-data');
				if (el && res.ok) {
					el.style.display = 'block';
					const pre = el.querySelector('pre');
					if (pre) pre.textContent = JSON.stringify(res.data, null, 2);
				}
			});
		});

		/* 今すぐ送信 */
		(document.getElementById('ap-diag-send-now') as HTMLButtonElement | null)?.addEventListener('click', function (this: HTMLButtonElement) {
			if (!confirm('診断データを今すぐ開発元へ送信しますか？')) return;
			this.disabled = true;
			const self = this as HTMLButtonElement;
			post('diag_send_now').then(res => {
				self.disabled = false;
				showResult(res.ok ? '送信しました' : (res.error || '送信失敗'));
				if (res.ok) loadSummary();
			}).catch(() => { self.disabled = false; });
		});

		/* ログクリア */
		(document.getElementById('ap-diag-clear-logs') as HTMLButtonElement | null)?.addEventListener('click', function () {
			if (!confirm('全ての診断ログをクリアしますか？')) return;
			post('diag_clear_logs').then(res => {
				showResult(res.ok ? 'ログをクリアしました' : (res.error || 'エラー'));
				if (res.ok) loadSummary();
			});
		});

		/* 全ログ表示 */
		(document.getElementById('ap-diag-show-all-logs') as HTMLButtonElement | null)?.addEventListener('click', function () {
			post('diag_get_logs').then(res => {
				const el = document.getElementById('ap-diag-all-logs');
				if (el && res.ok) {
					el.style.display = 'block';
					const pre = el.querySelector('pre');
					if (pre) pre.textContent = JSON.stringify(res.data, null, 2);
				}
			});
		});

		/* 初回通知バナーを閉じる */
		(document.getElementById('ap-diag-notice-dismiss') as HTMLButtonElement | null)?.addEventListener('click', function () {
			const banner = document.getElementById('ap-diag-notice');
			if (banner) banner.style.display = 'none';
		});
	}

	function showResult(msg: string): void {
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
