/**
 * dashboard.js - ダッシュボード固有のインタラクション
 *
 * テーマ選択の変更保存を担当。
 * editInplace.js（editText フィールド保存）と updater.js（アップデート管理）は
 * dashboard.html で別途読み込み済み。
 */
(function() {
	'use strict';

	document.addEventListener('DOMContentLoaded', function() {
		initThemeSelect();
	});

	/* ── テーマ選択 ── */

	function initThemeSelect() {
		var select = document.getElementById('ap-theme-select');
		if (!select) return;

		select.addEventListener('change', function() {
			var csrf = getCsrf();
			if (!csrf) return;

			var body = new FormData();
			body.append('ap_action', 'edit_field');
			body.append('fieldname', 'themeSelect');
			body.append('content', select.value);
			body.append('csrf', csrf);

			fetch('./', { method: 'POST', body: body })
				.then(function(res) {
					if (!res.ok) throw new Error('HTTP ' + res.status);
					/* テーマ変更後はリロードして反映 */
					window.location.reload();
				})
				.catch(function(err) {
					console.error('テーマ保存エラー:', err);
				});
		});
	}

	/* ── CSRF トークン取得 ── */

	function getCsrf() {
		var meta = document.querySelector('meta[name="csrf-token"]');
		if (!meta) {
			console.error('CSRF トークンが見つかりません');
			return null;
		}
		return meta.getAttribute('content');
	}
})();
