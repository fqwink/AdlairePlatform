/**
 * ap-search.js - 静的サイト用クライアントサイド検索
 *
 * search-index.json を読み込み、簡易トークンマッチングで検索。
 * 使い方: <input id="ap-search-input"> <div id="ap-search-results"></div>
 */
(function() {
	'use strict';

	var index = null;
	var input = document.getElementById('ap-search-input');
	var results = document.getElementById('ap-search-results');
	if (!input || !results) return;

	function loadIndex(callback) {
		if (index !== null) { callback(index); return; }
		fetch('/search-index.json')
			.then(function(r) { return r.json(); })
			.then(function(data) { index = data; callback(data); })
			.catch(function() { results.innerHTML = '<p>検索インデックスの読み込みに失敗しました。</p>'; });
	}

	function search(query, data) {
		if (!query.trim()) { results.innerHTML = ''; return; }
		var tokens = query.toLowerCase().split(/\s+/).filter(function(t) { return t.length > 0; });
		var scored = [];

		for (var i = 0; i < data.length; i++) {
			var item = data[i];
			var text = ((item.title || '') + ' ' + (item.body || '')).toLowerCase();
			var tags = (item.tags || []).join(' ').toLowerCase();
			var score = 0;

			for (var j = 0; j < tokens.length; j++) {
				var t = tokens[j];
				if ((item.title || '').toLowerCase().indexOf(t) !== -1) score += 10;
				if (tags.indexOf(t) !== -1) score += 5;
				if (text.indexOf(t) !== -1) score += 1;
			}
			if (score > 0) scored.push({ item: item, score: score });
		}

		scored.sort(function(a, b) { return b.score - a.score; });
		scored = scored.slice(0, 20);

		if (scored.length === 0) {
			results.innerHTML = '<p class="ap-search-empty">「' + escHtml(query) + '」に一致する結果はありません。</p>';
			return;
		}

		var html = '<div class="ap-search-results">';
		for (var k = 0; k < scored.length; k++) {
			var s = scored[k].item;
			var preview = (s.body || '').substring(0, 150);
			html += '<article class="ap-search-result">';
			html += '<h3><a href="' + escHtml(s.url || '/' + s.slug + '/') + '">' + escHtml(s.title || s.slug) + '</a></h3>';
			if (s.date) html += '<time>' + escHtml(s.date) + '</time>';
			html += '<p>' + escHtml(preview) + '</p>';
			html += '</article>';
		}
		html += '</div>';
		results.innerHTML = html;
	}

	function escHtml(s) {
		var d = document.createElement('div');
		d.appendChild(document.createTextNode(s));
		return d.innerHTML;
	}

	var debounce = null;
	input.addEventListener('input', function() {
		clearTimeout(debounce);
		debounce = setTimeout(function() {
			loadIndex(function(data) { search(input.value, data); });
		}, 300);
	});
})();
