'use strict';

/* ─────────────────────────────────────────────
   AdlairePlatform WYSIWYG Editor  (Ph2-1)
   依存ライブラリなし / vanilla JS
   ─────────────────────────────────────────────
   - span.editRich をクリックで起動
   - contenteditable div + ツールバー
   - Ctrl+Enter: 保存 / Escape: キャンセル
   - 保存は共通 _apFieldSave() を使用
   ───────────────────────────────────────────── */

(function () {

	/* ── スタイル注入（管理者モード限定） ── */
	var _css = [
		'.ap-wy-wrap{position:relative;min-height:2em;}',
		'.ap-wy-toolbar{display:flex;flex-wrap:wrap;gap:2px;padding:4px;',
		'  background:#333;border-radius:4px 4px 0 0;user-select:none;}',
		'.ap-wy-btn{padding:2px 7px;cursor:pointer;border:1px solid #555;',
		'  background:#444;color:#eee;border-radius:3px;font-size:13px;line-height:1.4;}',
		'.ap-wy-btn:hover{background:#666;}',
		'.ap-wy-sep{color:#666;padding:0 2px;align-self:center;}',
		'.ap-wy-editor{min-height:3em;padding:6px;outline:2px solid #1ab;',
		'  border-radius:0 0 4px 4px;background:rgba(255,255,255,.05);',
		'  color:inherit;font-size:inherit;font-family:inherit;line-height:inherit;}',
		'.ap-wy-editor:focus{outline:2px solid #0df;}',
	].join('');
	var _style = document.createElement('style');
	_style.textContent = _css;
	document.head.appendChild(_style);

	/* ── ツールバー定義 ── */
	var TOOLS = [
		{ cmd:'bold',                label:'<b>B</b>',  title:'太字 (Ctrl+B)' },
		{ cmd:'italic',              label:'<i>I</i>',  title:'斜体 (Ctrl+I)' },
		{ cmd:'underline',           label:'<u>U</u>',  title:'下線 (Ctrl+U)' },
		{ sep: true },
		{ cmd:'h2',                  label:'H2',        title:'見出し2' },
		{ cmd:'h3',                  label:'H3',        title:'見出し3' },
		{ cmd:'p',                   label:'¶',         title:'段落' },
		{ sep: true },
		{ cmd:'insertUnorderedList', label:'•≡',        title:'箇条書き' },
		{ cmd:'insertOrderedList',   label:'1≡',        title:'番号リスト' },
		{ sep: true },
		{ cmd:'link',                label:'🔗',        title:'リンク挿入' },
		{ cmd:'removeFormat',        label:'✕',         title:'書式クリア' },
		{ sep: true },
		{ cmd:'save',   label:'✓ 保存', title:'保存 (Ctrl+Enter)', cls:'ap-wy-save' },
		{ cmd:'cancel', label:'✕ 取消', title:'キャンセル (Esc)',   cls:'ap-wy-cancel' },
	];

	var _active = false;

	/* ── DOMContentLoaded ── */
	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('span.editRich').forEach(function (span) {
			span.addEventListener('click', function () {
				_activate(span);
			});
		});
	});

	/* ── エディタ起動 ── */
	function _activate(span) {
		if (_active) return;
		_active = true;

		var originalHtml = span.innerHTML;

		/* ツールバー */
		var toolbar = document.createElement('div');
		toolbar.className = 'ap-wy-toolbar';
		TOOLS.forEach(function (t) {
			if (t.sep) {
				var sep = document.createElement('span');
				sep.className = 'ap-wy-sep';
				sep.textContent = '|';
				toolbar.appendChild(sep);
				return;
			}
			var btn = document.createElement('button');
			btn.type = 'button';
			btn.className = 'ap-wy-btn' + (t.cls ? ' ' + t.cls : '');
			btn.innerHTML = t.label;
			btn.title = t.title || '';
			btn.dataset.cmd = t.cmd;
			toolbar.appendChild(btn);
		});

		/* 編集エリア */
		var editor = document.createElement('div');
		editor.contentEditable = 'true';
		editor.className = 'ap-wy-editor';
		editor.innerHTML = originalHtml;

		/* ラッパー */
		var wrap = document.createElement('div');
		wrap.className = 'ap-wy-wrap';
		wrap.appendChild(toolbar);
		wrap.appendChild(editor);

		span.innerHTML = '';
		span.appendChild(wrap);
		editor.focus();

		/* ── ツールバー操作 ── */
		toolbar.addEventListener('mousedown', function (e) {
			e.preventDefault(); /* blur を防ぐ */
			var btn = e.target.closest('[data-cmd]');
			if (!btn) return;
			_exec(btn.dataset.cmd, editor, span, originalHtml);
		});

		/* ── キーボードショートカット ── */
		editor.addEventListener('keydown', function (e) {
			var mod = e.ctrlKey || e.metaKey;
			if (mod && e.key === 'Enter') {
				e.preventDefault();
				_save(editor.innerHTML, span);
			} else if (e.key === 'Escape') {
				e.preventDefault();
				_cancel(span, originalHtml);
			}
		});

		/* ── エリア外クリックで保存 ── */
		function _docHandler(e) {
			if (!wrap.contains(e.target)) {
				document.removeEventListener('mousedown', _docHandler);
				_save(editor.innerHTML, span);
			}
		}
		document.addEventListener('mousedown', _docHandler);
	}

	/* ── コマンド実行 ── */
	function _exec(cmd, editor, span, originalHtml) {
		switch (cmd) {
			case 'bold':
			case 'italic':
			case 'underline':
			case 'insertUnorderedList':
			case 'insertOrderedList':
			case 'removeFormat':
				document.execCommand(cmd, false, null);
				editor.focus();
				break;
			case 'h2':
			case 'h3':
			case 'p':
				document.execCommand('formatBlock', false, cmd);
				editor.focus();
				break;
			case 'link':
				var url = prompt('URL を入力してください:');
				if (url && url.trim()) {
					document.execCommand('createLink', false, url.trim());
				}
				editor.focus();
				break;
			case 'save':
				_save(editor.innerHTML, span);
				break;
			case 'cancel':
				_cancel(span, originalHtml);
				break;
		}
	}

	/* ── 保存 ── */
	function _save(html, span) {
		if (!_active) return;
		_active = false;
		/* innerHTML を正規化して保存 */
		var clean = _cleanHtml(html);
		span.innerHTML = clean || (span.getAttribute('title') || '');
		_apFieldSave(span.id, clean);
	}

	/* ── キャンセル ── */
	function _cancel(span, originalHtml) {
		if (!_active) return;
		_active = false;
		span.innerHTML = originalHtml;
	}

	/* ── HTML 軽量サニタイズ ──
	   許可タグ: b i u strong em h2 h3 p br ul ol li a
	   属性: a[href] のみ許可                              */
	function _cleanHtml(html) {
		var tmp = document.createElement('div');
		tmp.innerHTML = html;
		_sanitizeNode(tmp);
		return tmp.innerHTML;
	}

	var _allowedTags = {
		B:1, I:1, U:1, STRONG:1, EM:1,
		H2:1, H3:1, P:1, BR:1,
		UL:1, OL:1, LI:1, A:1,
	};

	function _sanitizeNode(node) {
		var children = Array.from(node.childNodes);
		children.forEach(function (child) {
			if (child.nodeType === 1) { /* ELEMENT_NODE */
				if (!_allowedTags[child.tagName]) {
					/* 許可外タグ → 内容のみ展開 */
					var frag = document.createDocumentFragment();
					Array.from(child.childNodes).forEach(function (c) { frag.appendChild(c); });
					node.replaceChild(frag, child);
					_sanitizeNode(node);
					return;
				}
				/* 属性フィルタ */
				Array.from(child.attributes).forEach(function (attr) {
					if (child.tagName === 'A' && attr.name === 'href') return;
					child.removeAttribute(attr.name);
				});
				/* a[href] の危険スキームを除去 */
				if (child.tagName === 'A') {
					var href = child.getAttribute('href') || '';
					if (/^javascript:/i.test(href.trim())) {
						child.removeAttribute('href');
					}
				}
				_sanitizeNode(child);
			}
		});
	}

})();
