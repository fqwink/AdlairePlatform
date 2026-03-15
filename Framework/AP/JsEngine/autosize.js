'use strict';

/**
 * テキストエリア高さ自動調整
 * @param {HTMLTextAreaElement} el
 */
function apAutosize(el) {
	el.style.boxSizing = 'border-box';
	el.style.overflowY = 'hidden';
	el.style.resize    = 'none';
	el.style.height    = 'auto';
	el.style.height    = el.scrollHeight + 'px';
	el.addEventListener('input', function () {
		this.style.height = 'auto';
		this.style.height = this.scrollHeight + 'px';
	});
}

document.addEventListener('DOMContentLoaded', function () {
	document.querySelectorAll('textarea[data-autosize]').forEach(apAutosize);
});
