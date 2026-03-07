'use strict';

var _apChanging = false;

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
				_apFieldSave(ta.id.slice(0, -6), _apNl2br(ta.value));
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
			_apFieldSave('themeSelect', this.value);
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

/* 改行 → <br /> 変換（\r\n を \n に正規化してから変換） */
function _apNl2br(s) {
	return (s + '').replace(/\r\n|\r/g, '\n').replace(/([^>\n]?)(\n)/g, '$1<br />\n');
}

/* フィールド保存（Fetch API） */
function _apFieldSave(key, val) {
	var csrfMeta = document.querySelector('meta[name="csrf-token"]');
	if (!csrfMeta) { _apChanging = false; return; }
	var csrf = csrfMeta.getAttribute('content');
	fetch('index.php', {
		method: 'POST',
		headers: {
			'Content-Type': 'application/x-www-form-urlencoded',
			'X-CSRF-TOKEN': csrf
		},
		body: new URLSearchParams({ fieldname: key, content: val, csrf: csrf })
	}).then(function (r) {
		if (!r.ok) throw new Error('HTTP ' + r.status);
		return r.text();
	}).then(function (data) {
		if (key === 'themeSelect') {
			location.reload(true);
		} else {
			var el = document.getElementById(key);
			if (el) el.innerHTML = (val === '') ? (el.getAttribute('title') || '') : data;
		}
		_apChanging = false;
	}).catch(function () {
		alert('保存に失敗しました。再試行してください。');
		_apChanging = false;
	});
}
