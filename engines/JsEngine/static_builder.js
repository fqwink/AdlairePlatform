'use strict';

/**
 * static_builder.js — ダッシュボード上の静的書き出し管理 UI
 *
 * ボタン操作:
 *   [差分ビルド]  → ap_action=generate_static_diff
 *   [フルビルド]  → ap_action=generate_static_full
 *   [クリーン]    → ap_action=clean_static
 *   [ZIP DL]      → ap_action=build_zip
 *   [ステータス]  → ap_action=static_status
 */
document.addEventListener('DOMContentLoaded', function () {
	var statusEl  = document.getElementById('ap-static-status');
	var resultEl  = document.getElementById('ap-static-result');
	var pagesEl   = document.getElementById('ap-static-pages');
	var csrfMeta  = document.querySelector('meta[name="csrf-token"]');

	if (!statusEl || !resultEl || !csrfMeta) return;
	var csrf = csrfMeta.getAttribute('content');

	/* ── ヘルパー ── */
	function esc(s) {
		var d = document.createElement('div');
		d.textContent = s;
		return d.innerHTML;
	}

	function showStatus(msg) { statusEl.textContent = msg; }
	function showResult(html) { resultEl.innerHTML = html; }

	function post(action) {
		return fetch('index.php', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded',
				'X-CSRF-TOKEN': csrf
			},
			body: new URLSearchParams({ ap_action: action, csrf: csrf })
		}).then(function (r) {
			if (!r.ok) throw new Error('HTTP ' + r.status);
			return r.json();
		});
	}

	function renderPages(pages) {
		if (!pagesEl || !pages || !pages.length) return;
		var html = '';
		pages.forEach(function (p) {
			var icon = p.state === 'current' ? '✅' : (p.state === 'outdated' ? '⚠️' : '❌');
			var label = p.state === 'current' ? '最新' : (p.state === 'outdated' ? '更新必要' : '未生成');
			html += '<span class="ap-static-page-badge" title="' + esc(label) + '">'
				+ icon + ' ' + esc(p.slug)
				+ '</span> ';
		});
		pagesEl.innerHTML = html;
	}

	/* ── ステータス取得 ── */
	function refreshStatus() {
		showStatus('状態を取得中...');
		post('static_status').then(function (data) {
			if (!data.ok) { showStatus('エラー: ' + (data.error || '不明')); return; }
			var info = '';
			if (data.last_full_build) info += 'フルビルド: ' + esc(data.last_full_build) + ' ';
			if (data.last_diff_build) info += '差分ビルド: ' + esc(data.last_diff_build);
			if (!info) info = 'まだビルドされていません';
			showStatus(info);
			renderPages(data.pages);
		}).catch(function (e) {
			showStatus('取得失敗: ' + e.message);
		});
	}

	/* ── 差分ビルド ── */
	var diffBtn = document.getElementById('ap-static-diff');
	if (diffBtn) {
		diffBtn.addEventListener('click', function () {
			showStatus('差分ビルド中...');
			showResult('');
			post('generate_static_diff').then(function (data) {
				if (!data.ok) { showResult('エラー: ' + esc(data.error || '不明')); return; }
				showResult('ビルド: ' + data.built + ' / スキップ: ' + data.skipped
					+ ' / 削除: ' + data.deleted + ' (' + data.elapsed_ms + 'ms)');
				refreshStatus();
			}).catch(function (e) {
				showResult('失敗: ' + esc(e.message));
			});
		});
	}

	/* ── フルビルド ── */
	var fullBtn = document.getElementById('ap-static-full');
	if (fullBtn) {
		fullBtn.addEventListener('click', function () {
			showStatus('フルビルド中...');
			showResult('');
			post('generate_static_full').then(function (data) {
				if (!data.ok) { showResult('エラー: ' + esc(data.error || '不明')); return; }
				showResult('ビルド: ' + data.built + ' / 削除: ' + data.deleted + ' (' + data.elapsed_ms + 'ms)');
				refreshStatus();
			}).catch(function (e) {
				showResult('失敗: ' + esc(e.message));
			});
		});
	}

	/* ── クリーン ── */
	var cleanBtn = document.getElementById('ap-static-clean');
	if (cleanBtn) {
		cleanBtn.addEventListener('click', function () {
			if (!confirm('静的ファイルをすべて削除しますか？')) return;
			showStatus('クリーン中...');
			showResult('');
			post('clean_static').then(function (data) {
				showResult(data.ok ? '削除完了' : 'エラー: ' + esc(data.error || '不明'));
				refreshStatus();
			}).catch(function (e) {
				showResult('失敗: ' + esc(e.message));
			});
		});
	}

	/* ── ZIP ダウンロード ── */
	var zipBtn = document.getElementById('ap-static-zip');
	if (zipBtn) {
		zipBtn.addEventListener('click', function () {
			showStatus('ZIP 生成中...');
			fetch('index.php', {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': csrf },
				body: new URLSearchParams({ ap_action: 'build_zip', csrf: csrf })
			}).then(function (r) {
				if (!r.ok) {
					return r.json().then(function (d) { throw new Error(d.error || 'HTTP ' + r.status); });
				}
				return r.blob();
			}).then(function (blob) {
				var a = document.createElement('a');
				a.href = URL.createObjectURL(blob);
				a.download = 'static-' + new Date().toISOString().slice(0, 10) + '.zip';
				document.body.appendChild(a);
				a.click();
				a.remove();
				URL.revokeObjectURL(a.href);
				showStatus('ZIP ダウンロード完了');
			}).catch(function (e) {
				showStatus('ZIP 失敗: ' + e.message);
			});
		});
	}

	/* ── 初期ステータス取得 ── */
	refreshStatus();
});
