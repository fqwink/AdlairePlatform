/// <reference lib="dom" />
/// <reference path="./browser-types.d.ts" />

'use strict';

/**
 * テキストエリア高さ自動調整
 * @param el - 対象のテキストエリア要素
 */
function apAutosize(el: HTMLTextAreaElement): void {
	el.style.boxSizing = 'border-box';
	el.style.overflowY = 'hidden';
	el.style.resize    = 'none';
	el.style.height    = 'auto';
	el.style.height    = el.scrollHeight + 'px';
	el.addEventListener('input', function (this: HTMLTextAreaElement): void {
		this.style.height = 'auto';
		this.style.height = this.scrollHeight + 'px';
	});
}

document.addEventListener('DOMContentLoaded', function (): void {
	document.querySelectorAll<HTMLTextAreaElement>('textarea[data-autosize]').forEach(apAutosize);
});
