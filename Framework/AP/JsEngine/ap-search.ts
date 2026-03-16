/// <reference lib="dom" />
/// <reference path="./browser-types.d.ts" />

/**
 * ap-search.ts - 静的サイト用クライアントサイド検索
 *
 * search-index.json を読み込み、簡易トークンマッチングで検索。
 * 使い方: <input id="ap-search-input"> <div id="ap-search-results"></div>
 */
(function (): void {
	'use strict';

	interface SearchIndexItem {
		title?: string;
		body?: string;
		tags?: string[];
		url?: string;
		slug?: string;
		date?: string;
	}

	interface ScoredItem {
		item: SearchIndexItem;
		score: number;
	}

	let index: SearchIndexItem[] | null = null;

	function init(): void {
		const input: HTMLInputElement | null = document.getElementById('ap-search-input') as HTMLInputElement | null;
		const results: HTMLElement | null = document.getElementById('ap-search-results');
		if (!input || !results) return;

		function loadIndex(callback: (data: SearchIndexItem[]) => void): void {
			if (index !== null) { callback(index); return; }
			fetch('/search-index.json')
				.then(function (r: Response): Promise<SearchIndexItem[]> {
					if (!r.ok) throw new Error('HTTP ' + r.status);
					return r.json();
				})
				.then(function (data: SearchIndexItem[]): void { index = data; callback(data); })
				.catch(function (): void { results!.innerHTML = '<p>検索インデックスの読み込みに失敗しました。</p>'; });
		}

		function search(query: string, data: SearchIndexItem[]): void {
			if (!query.trim()) { results!.innerHTML = ''; return; }
			const tokens: string[] = query.toLowerCase().split(/\s+/).filter(function (t: string): boolean { return t.length > 0; });
			let scored: ScoredItem[] = [];

			for (let i: number = 0; i < data.length; i++) {
				const item: SearchIndexItem = data[i];
				const title: string = (item.title || '').toLowerCase();
				const body: string = (item.body || '').toLowerCase();
				const tags: string = (item.tags || []).join(' ').toLowerCase();
				let score: number = 0;

				for (let j: number = 0; j < tokens.length; j++) {
					const t: string = tokens[j];
					/* M9 fix: 排他的スコアリング（二重加算防止） */
					if (title.indexOf(t) !== -1) {
						score += 10;
					} else if (tags.indexOf(t) !== -1) {
						score += 5;
					} else if (body.indexOf(t) !== -1) {
						score += 1;
					}
				}
				if (score > 0) scored.push({ item: item, score: score });
			}

			scored.sort(function (a: ScoredItem, b: ScoredItem): number { return b.score - a.score; });
			scored = scored.slice(0, 20);

			if (scored.length === 0) {
				results!.innerHTML = '<p class="ap-search-empty">' + escHtml('「' + query + '」に一致する結果はありません。') + '</p>';
				return;
			}

			let html: string = '<div class="ap-search-results">';
			for (let k: number = 0; k < scored.length; k++) {
				const s: SearchIndexItem = scored[k].item;
				const preview: string = (s.body || '').substring(0, 150);
				html += '<article class="ap-search-result">';
				html += '<h3><a href="' + escHtml(s.url || '/' + s.slug + '/') + '">' + escHtml(s.title || s.slug || '') + '</a></h3>';
				if (s.date) html += '<time>' + escHtml(s.date) + '</time>';
				html += '<p>' + escHtml(preview) + '</p>';
				html += '</article>';
			}
			html += '</div>';
			results!.innerHTML = html;
		}

		let debounce: ReturnType<typeof setTimeout> | null = null;
		input.addEventListener('input', function (): void {
			if (debounce !== null) clearTimeout(debounce);
			debounce = setTimeout(function (): void {
				loadIndex(function (data: SearchIndexItem[]): void { search((input as HTMLInputElement).value, data); });
			}, 300);
		});
	}

	function escHtml(s: string): string {
		const d: HTMLDivElement = document.createElement('div');
		d.appendChild(document.createTextNode(s));
		return d.innerHTML;
	}

	/* M10 fix: DOMContentLoaded で初期化 */
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
