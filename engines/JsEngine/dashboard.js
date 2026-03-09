/**
 * dashboard.js - ダッシュボード固有のインタラクション
 *
 * テーマ選択の保存は editInplace.js で統一して処理。
 * ページ削除機能を担当。
 */
(function() {
	'use strict';

	document.addEventListener('DOMContentLoaded', function() {
		initPageDelete();
	});

	/* ── CSRF トークン取得 ── */

	function getCsrf() {
		var meta = document.querySelector('meta[name="csrf-token"]');
		if (!meta) {
			console.error('CSRF トークンが見つかりません');
			return null;
		}
		return meta.getAttribute('content');
	}

	/* ── ページ削除 ── */

	function initPageDelete() {
		document.addEventListener('click', function(e) {
			var btn = e.target.closest('.ap-page-delete');
			if (!btn) return;
			e.preventDefault();
			e.stopPropagation();
			var slug = btn.dataset.slug;
			if (!slug) return;
			if (!confirm('ページ「' + slug + '」を削除しますか？\n削除前にリビジョンとして保存されます。')) return;

			var csrf = getCsrf();
			if (!csrf) return;

			btn.disabled = true;
			btn.textContent = '削除中...';

			fetch('index.php', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
					'X-CSRF-TOKEN': csrf
				},
				body: new URLSearchParams({ ap_action: 'delete_page', slug: slug, csrf: csrf })
			}).then(function(res) {
				if (!res.ok) return res.json().then(function(d) { throw new Error(d.error || 'HTTP ' + res.status); });
				return res.json();
			}).then(function(data) {
				if (data.ok) {
					var item = btn.closest('.ap-dash-page-item');
					if (item) item.remove();
				}
			}).catch(function(err) {
				console.error('削除エラー:', err);
				btn.disabled = false;
				btn.textContent = '×';
			});
		});
	}
})();
