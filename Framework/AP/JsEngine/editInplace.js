/**
 * editInplace.js - インライン編集・テーマ選択・設定パネル
 *
 * Ver.1.6: ES6 モダン構文に移行（const/let, arrow, template literals）
 *
 * グローバル公開: window._apChanging, window._apFieldSave
 *   （wysiwyg.js から参照されるため）
 * 依存: ap-utils.js (AP.getCsrf)
 */
(() => {
	'use strict';

	let _apChanging = false;

	/* C13 fix: HTML サニタイズ — template 要素で解析（parse-time XSS 防止） */
	const sanitizeHtml = (html) => {
		const tpl = document.createElement('template');
		tpl.innerHTML = html;
		const tmp = tpl.content;
		/* script/style/iframe 等の危険要素を除去 */
		tmp.querySelectorAll('script,style,iframe,object,embed,form,input,textarea,select,button')
			.forEach(el => el.remove());
		/* on* イベント属性を除去 */
		tmp.querySelectorAll('*').forEach(el => {
			[...el.attributes].forEach(attr => {
				if (/^on/i.test(attr.name)) el.removeAttribute(attr.name);
			});
			/* R27 fix: javascript: スキームの href をブロック */
			if (el.tagName === 'A' && /^\s*javascript:/i.test(el.getAttribute('href') || '')) {
				el.removeAttribute('href');
			}
		});
		const div = document.createElement('div');
		div.appendChild(tmp.cloneNode(true));
		return div.innerHTML;
	};

	/* 保存フィードバックアニメーション */
	const flash = (el, success) => {
		if (!el) return;
		const cls = success ? 'ap-field-saved' : 'ap-field-error';
		el.classList.add(cls);
		setTimeout(() => el.classList.remove(cls), 1500);
	};

	/* 改行 → <br /> 変換 */
	const nl2br = (s) => `${s}`.replace(/\r\n|\r/g, '\n').replace(/([^>\n]?)(\n)/g, '$1<br />\n');

	/* BR を含む HTML フィールドかどうか判定 */
	const htmlFields = ['menu', 'subside', 'copyright'];

	/* フィールド保存（Fetch API） */
	const fieldSave = (key, val) => {
		const csrf = AP.getCsrf();
		if (!csrf) { console.error('[AdlairePlatform] CSRF token meta tag not found'); _apChanging = false; return; }
		/* M18 fix: タイムアウトでロック解除（15秒） */
		const lockTimeout = setTimeout(() => { _apChanging = false; }, 15000);
		fetch('index.php', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded',
				'X-CSRF-TOKEN': csrf
			},
			body: new URLSearchParams({ ap_action: 'edit_field', fieldname: key, content: val, csrf: csrf })
		}).then(r => {
			if (!r.ok) throw new Error(`HTTP ${r.status}`);
			return r.text();
		}).then(() => {
			if (key === 'themeSelect') {
				location.reload(true);
			} else {
				const el = document.getElementById(key);
				if (el) {
					if (val === '') {
						el.textContent = el.getAttribute('title') || '';
					} else if (htmlFields.includes(key)) {
						el.innerHTML = sanitizeHtml(val);
					} else {
						el.textContent = val;
					}
					flash(el, true);
				}
			}
			clearTimeout(lockTimeout);
			_apChanging = false;
		}).catch(e => {
			console.error('[AdlairePlatform] 保存エラー:', e.message);
			flash(document.getElementById(key), false);
			clearTimeout(lockTimeout);
			_apChanging = false;
		});
	};

	/* ── グローバル公開（wysiwyg.js 互換） ── */
	Object.defineProperty(window, '_apChanging', {
		get: () => _apChanging,
		set: (v) => { _apChanging = v; },
		configurable: true
	});
	window._apFieldSave = fieldSave;

	/* ── DOM Ready ── */
	document.addEventListener('DOMContentLoaded', () => {
		/* ── インライン編集 ── */
		document.querySelectorAll('span.editText').forEach(el => {
			el.addEventListener('click', function () {
				if (_apChanging) return;
				_apChanging = true;
				const a  = this;
				const ta = document.createElement('textarea');
				ta.name = 'textarea';
				ta.id   = `${a.id}_field`;
				if (a.title) ta.setAttribute('title', a.title);
				ta.value = a.innerHTML.replace(/<br\s*\/?>/gi, '\n');
				ta.addEventListener('blur', function handler() {
					ta.removeEventListener('blur', handler);
					fieldSave(ta.id.slice(0, -6), nl2br(ta.value));
				});
				a.innerHTML = '';
				a.appendChild(ta);
				ta.focus();
				if (typeof apAutosize === 'function') apAutosize(ta);
			});
		});

		/* ── メニューリフレッシュリンク ── */
		const refreshLink = document.getElementById('ap-refresh-link');
		if (refreshLink) {
			refreshLink.addEventListener('click', (e) => {
				e.preventDefault();
				location.reload(true);
			});
		}

		/* ── テーマ選択 ── */
		const themeSelect = document.getElementById('ap-theme-select');
		if (themeSelect) {
			themeSelect.addEventListener('change', function () {
				fieldSave('themeSelect', this.value);
			});
		}

		/* ── 設定パネル開閉 ── */
		document.querySelectorAll('.toggle').forEach(el => {
			el.addEventListener('click', function () {
				const panel = this.parentElement.querySelector('.hide');
				if (panel) {
					panel.style.display = (panel.style.display === 'block') ? 'none' : 'block';
				}
			});
		});
	});
})();
