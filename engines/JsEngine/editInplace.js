/**
 * editInplace.js - インライン編集・テーマ選択・設定パネル
 *
 * グローバル公開: window._apChanging, window._apFieldSave
 *   （wysiwyg.js から参照されるため）
 * 依存: ap-utils.js (AP.getCsrf)
 */
(function () {
	'use strict';

	var _apChanging = false;

	/* C13 fix: HTML サニタイズ — template 要素で解析（parse-time XSS 防止） */
	function sanitizeHtml(html) {
		var tpl = document.createElement('template');
		tpl.innerHTML = html;
		var tmp = tpl.content;
		/* script/style/iframe 等の危険要素を除去 */
		var dangerous = tmp.querySelectorAll('script,style,iframe,object,embed,form,input,textarea,select,button');
		for (var i = 0; i < dangerous.length; i++) dangerous[i].remove();
		/* on* イベント属性を除去 */
		var all = tmp.querySelectorAll('*');
		for (var j = 0; j < all.length; j++) {
			var attrs = Array.prototype.slice.call(all[j].attributes);
			for (var k = 0; k < attrs.length; k++) {
				if (/^on/i.test(attrs[k].name)) all[j].removeAttribute(attrs[k].name);
			}
			/* R27 fix: javascript: スキームの href をブロック */
			if (all[j].tagName === 'A' && /^\s*javascript:/i.test(all[j].getAttribute('href') || '')) {
				all[j].removeAttribute('href');
			}
		}
		var div = document.createElement('div');
		div.appendChild(tmp.cloneNode(true));
		return div.innerHTML;
	}

	/* 保存フィードバックアニメーション */
	function flash(el, success) {
		if (!el) return;
		var cls = success ? 'ap-field-saved' : 'ap-field-error';
		el.classList.add(cls);
		setTimeout(function () { el.classList.remove(cls); }, 1500);
	}

	/* 改行 → <br /> 変換（\r\n を \n に正規化してから変換） */
	function nl2br(s) {
		return (s + '').replace(/\r\n|\r/g, '\n').replace(/([^>\n]?)(\n)/g, '$1<br />\n');
	}

	/* BR を含む HTML フィールドかどうか判定 */
	var htmlFields = ['menu', 'subside', 'copyright'];

	/* フィールド保存（Fetch API） */
	function fieldSave(key, val) {
		var csrf = AP.getCsrf();
		if (!csrf) { console.error('[AdlairePlatform] CSRF token meta tag not found'); _apChanging = false; return; }
		/* M18 fix: タイムアウトでロック解除（15秒） */
		var lockTimeout = setTimeout(function () { _apChanging = false; }, 15000);
		fetch('index.php', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded',
				'X-CSRF-TOKEN': csrf
			},
			body: new URLSearchParams({ ap_action: 'edit_field', fieldname: key, content: val, csrf: csrf })
		}).then(function (r) {
			if (!r.ok) throw new Error('HTTP ' + r.status);
			return r.text();
		}).then(function () {
			if (key === 'themeSelect') {
				location.reload(true);
			} else {
				var el = document.getElementById(key);
				if (el) {
					if (val === '') {
						el.textContent = el.getAttribute('title') || '';
					} else if (htmlFields.indexOf(key) !== -1) {
						el.innerHTML = sanitizeHtml(val);
					} else {
						el.textContent = val;
					}
					flash(el, true);
				}
			}
			clearTimeout(lockTimeout);
			_apChanging = false;
		}).catch(function (e) {
			console.error('[AdlairePlatform] 保存エラー:', e.message);
			var el = document.getElementById(key);
			flash(el, false);
			clearTimeout(lockTimeout);
			_apChanging = false;
		});
	}

	/* ── グローバル公開（wysiwyg.js 互換） ── */
	Object.defineProperty(window, '_apChanging', {
		get: function () { return _apChanging; },
		set: function (v) { _apChanging = v; },
		configurable: true
	});
	window._apFieldSave = fieldSave;

	/* ── DOM Ready ── */
	document.addEventListener('DOMContentLoaded', function () {
		/* ── インライン編集 ── */
		document.querySelectorAll('span.editText').forEach(function (el) {
			el.addEventListener('click', function () {
				if (_apChanging) return;
				_apChanging = true;
				var a  = this;
				var ta = document.createElement('textarea');
				ta.name = 'textarea';
				ta.id   = a.id + '_field';
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
		var refreshLink = document.getElementById('ap-refresh-link');
		if (refreshLink) {
			refreshLink.addEventListener('click', function (e) {
				e.preventDefault();
				location.reload(true);
			});
		}

		/* ── テーマ選択 ── */
		var themeSelect = document.getElementById('ap-theme-select');
		if (themeSelect) {
			themeSelect.addEventListener('change', function () {
				fieldSave('themeSelect', this.value);
			});
		}

		/* ── 設定パネル開閉 ── */
		document.querySelectorAll('.toggle').forEach(function (el) {
			el.addEventListener('click', function () {
				var panel = this.parentElement.querySelector('.hide');
				if (panel) {
					panel.style.display = (panel.style.display === 'block') ? 'none' : 'block';
				}
			});
		});
	});
})();
