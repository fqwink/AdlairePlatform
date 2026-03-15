/**
 * dashboard.js - ダッシュボード固有のインタラクション
 *
 * テーマ選択の保存は editInplace.js で統一して処理。
 * ページ削除機能を担当。
 * 依存: ap-utils.js (AP.postAction)
 */
(function() {
	'use strict';

	document.addEventListener('DOMContentLoaded', function() {
		initPageDelete();
	});

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

			btn.disabled = true;
			btn.textContent = '削除中...';

			AP.postAction('delete_page', { slug: slug })
				.then(function(data) {
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
