/// <reference lib="dom" />
/// <reference path="../../ACS/ClientEngine/browser-types.d.ts" />
/**
 * dashboard.ts - ダッシュボード固有のインタラクション
 *
 * テーマ選択の保存は editInplace.ts で統一して処理。
 * ページ削除機能を担当。
 * 依存: ap-utils.js (AP.postAction)
 */
(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		initPageDelete();
	});

	/* ── ページ削除 ── */

	function initPageDelete(): void {
		document.addEventListener('click', function (e: MouseEvent) {
			const btn = (e.target as HTMLElement | null)?.closest('.ap-page-delete') as HTMLButtonElement | null;
			if (!btn) return;
			e.preventDefault();
			e.stopPropagation();
			const slug = btn.dataset.slug;
			if (!slug) return;
			if (!confirm('ページ「' + slug + '」を削除しますか？\n削除前にリビジョンとして保存されます。')) return;

			btn.disabled = true;
			btn.textContent = '削除中...';

			AP.postAction('delete_page', { slug: slug })
				.then(function (data: APResponse) {
					if (data.ok) {
						const item = btn.closest('.ap-dash-page-item');
						if (item) item.remove();
					} else {
						btn.textContent = '削除';
						btn.removeAttribute('disabled');
						alert(data.error || '削除に失敗しました');
					}
				}).catch(function (err: Error) {
					console.error('削除エラー:', err);
					btn.disabled = false;
					btn.textContent = '×';
				});
		});
	}
})();
