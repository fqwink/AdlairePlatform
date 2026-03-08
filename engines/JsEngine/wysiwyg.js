'use strict';

/* ─────────────────────────────────────────────
   AdlairePlatform WYSIWYG Editor  (Ph2-1)
   依存ライブラリなし / vanilla JS

   機能:
   - contenteditable + ツールバー (bold/italic/underline/h2/h3/p/ul/ol/link/img/removeFormat)
   - 画像挿入: ツールバーボタン / ドラッグ&ドロップ / クリップボード貼り付け
   - 定期自動保存 (30秒): 変更時のみ保存、ステータス表示
   - Ctrl+Enter: 手動保存 / Escape: キャンセル
   ───────────────────────────────────────────── */

(function () {

	/* ── スタイル注入 ── */
	var _css = [
		'.ap-wy-wrap{position:relative;min-height:2em;}',
		'.ap-wy-toolbar{display:flex;flex-wrap:wrap;align-items:center;gap:2px;padding:4px;',
		'  background:#333;border-radius:4px 4px 0 0;user-select:none;}',
		'.ap-wy-btn{padding:2px 7px;cursor:pointer;border:1px solid #555;',
		'  background:#444;color:#eee;border-radius:3px;font-size:13px;line-height:1.4;}',
		'.ap-wy-btn:hover{background:#666;}',
		'.ap-wy-sep{color:#666;padding:0 2px;}',
		'.ap-wy-status{margin-left:auto;font-size:11px;color:#aaa;padding:0 4px;white-space:nowrap;}',
		'.ap-wy-editor{min-height:3em;padding:6px;outline:2px solid #1ab;',
		'  border-radius:0 0 4px 4px;background:rgba(255,255,255,.05);',
		'  color:inherit;font-size:inherit;font-family:inherit;line-height:inherit;}',
		'.ap-wy-editor:focus{outline:2px solid #0df;}',
		'.ap-wy-editor.ap-wy-dragover{outline:2px dashed #0df;background:rgba(0,221,255,.08);}',
		'.ap-wy-editor img{max-width:100%;height:auto;display:block;margin:4px 0;}',
	].join('');
	var _styleEl = document.createElement('style');
	_styleEl.textContent = _css;
	document.head.appendChild(_styleEl);

	/* ── ツールバー定義 ── */
	var TOOLS = [
		{ cmd:'bold',                label:'<b>B</b>',  title:'太字 (Ctrl+B)' },
		{ cmd:'italic',              label:'<i>I</i>',  title:'斜体 (Ctrl+I)' },
		{ cmd:'underline',           label:'<u>U</u>',  title:'下線 (Ctrl+U)' },
		{ sep:true },
		{ cmd:'h2',                  label:'H2',        title:'見出し2' },
		{ cmd:'h3',                  label:'H3',        title:'見出し3' },
		{ cmd:'p',                   label:'¶',         title:'段落' },
		{ sep:true },
		{ cmd:'insertUnorderedList', label:'•≡',        title:'箇条書き' },
		{ cmd:'insertOrderedList',   label:'1≡',        title:'番号リスト' },
		{ sep:true },
		{ cmd:'link',                label:'🔗',        title:'リンク挿入' },
		{ cmd:'img',                 label:'🖼',        title:'画像挿入' },
		{ cmd:'removeFormat',        label:'✕',         title:'書式クリア' },
		{ sep:true },
		{ cmd:'save',   label:'✓ 保存', title:'保存 (Ctrl+Enter)' },
		{ cmd:'cancel', label:'✕ 取消', title:'キャンセル (Esc)'  },
	];

	/* ── 状態 ── */
	var _active       = false;
	var _autoTimer    = null;
	var _lastSaved    = '';
	var _statusEl     = null;
	var _currentSpan  = null;
	var _currentEditor = null;

	/* ── DOMContentLoaded ── */
	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('span.editRich').forEach(function (span) {
			span.addEventListener('click', function () { _activate(span); });
		});
	});

	/* ─────────────────── エディタ起動 ─────────────────── */
	function _activate(span) {
		if (_active) return;
		_active = true;
		_currentSpan = span;

		var originalHtml = span.innerHTML;

		/* ─ ツールバー構築 ─ */
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
			btn.className = 'ap-wy-btn';
			btn.innerHTML = t.label;
			btn.title = t.title || '';
			btn.dataset.cmd = t.cmd;
			toolbar.appendChild(btn);
		});

		/* ステータス表示 */
		_statusEl = document.createElement('span');
		_statusEl.className = 'ap-wy-status';
		toolbar.appendChild(_statusEl);

		/* ─ 編集エリア ─ */
		var editor = document.createElement('div');
		editor.contentEditable = 'true';
		editor.className = 'ap-wy-editor';
		editor.innerHTML = originalHtml;
		_currentEditor = editor;

		/* ─ ラッパー ─ */
		var wrap = document.createElement('div');
		wrap.className = 'ap-wy-wrap';
		wrap.appendChild(toolbar);
		wrap.appendChild(editor);

		span.innerHTML = '';
		span.appendChild(wrap);
		editor.focus();

		/* ─ 自動保存開始 ─ */
		_lastSaved = _cleanHtml(originalHtml);
		_startAutoSave(span.id);

		/* ─ ツールバー操作 ─ */
		toolbar.addEventListener('mousedown', function (e) {
			e.preventDefault();
			var btn = e.target.closest('[data-cmd]');
			if (!btn) return;
			_exec(btn.dataset.cmd, editor, span, originalHtml);
		});

		/* ─ キーボードショートカット ─ */
		editor.addEventListener('keydown', function (e) {
			var mod = e.ctrlKey || e.metaKey;
			if (mod && e.key === 'Enter') {
				e.preventDefault();
				_manualSave(editor, span);
			} else if (e.key === 'Escape') {
				e.preventDefault();
				_cancel(span, originalHtml);
			}
		});

		/* ─ 画像: ドラッグ&ドロップ ─ */
		editor.addEventListener('dragover', function (e) {
			if (e.dataTransfer.types.indexOf('Files') !== -1) {
				e.preventDefault();
				editor.classList.add('ap-wy-dragover');
			}
		});
		editor.addEventListener('dragleave', function () {
			editor.classList.remove('ap-wy-dragover');
		});
		editor.addEventListener('drop', function (e) {
			editor.classList.remove('ap-wy-dragover');
			var files = e.dataTransfer.files;
			if (files && files.length > 0) {
				e.preventDefault();
				_uploadAndInsert(files[0], editor);
			}
		});

		/* ─ 画像: クリップボード貼り付け ─ */
		editor.addEventListener('paste', function (e) {
			var items = e.clipboardData && e.clipboardData.items;
			if (!items) return;
			for (var i = 0; i < items.length; i++) {
				if (items[i].type.indexOf('image/') === 0) {
					e.preventDefault();
					_uploadAndInsert(items[i].getAsFile(), editor);
					return;
				}
			}
		});

		/* ─ エリア外クリックで保存 ─ */
		function _docHandler(e) {
			if (!wrap.contains(e.target)) {
				document.removeEventListener('mousedown', _docHandler);
				_manualSave(editor, span);
			}
		}
		document.addEventListener('mousedown', _docHandler);
	}

	/* ─────────────────── コマンド実行 ─────────────────── */
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
				if (url && url.trim()) document.execCommand('createLink', false, url.trim());
				editor.focus();
				break;
			case 'img':
				var input = document.createElement('input');
				input.type = 'file';
				input.accept = 'image/jpeg,image/png,image/gif,image/webp';
				input.onchange = function () {
					if (input.files && input.files[0]) {
						_uploadAndInsert(input.files[0], editor);
					}
				};
				input.click();
				break;
			case 'save':
				_manualSave(editor, span);
				break;
			case 'cancel':
				_cancel(span, originalHtml);
				break;
		}
	}

	/* ─────────────────── 保存 ─────────────────── */

	/* 手動保存（ボタン / Ctrl+Enter / blur） */
	function _manualSave(editor, span) {
		if (!_active) return;
		_stopAutoSave();
		_active = false;
		_currentSpan = null;
		_currentEditor = null;
		var html = _cleanHtml(editor.innerHTML);
		span.innerHTML = html || (span.getAttribute('title') || '');
		_apFieldSave(span.id, html);
	}

	/* キャンセル */
	function _cancel(span, originalHtml) {
		if (!_active) return;
		_stopAutoSave();
		_active = false;
		_currentSpan = null;
		_currentEditor = null;
		span.innerHTML = originalHtml;
	}

	/* ─────────────────── 自動保存 ─────────────────── */

	function _startAutoSave(fieldId) {
		_autoTimer = setInterval(function () {
			if (!_active || !_currentEditor) return;
			var html = _cleanHtml(_currentEditor.innerHTML);
			if (html === _lastSaved) return;
			_setStatus('保存中...');
			_fetchSave(fieldId, html, function (ok) {
				if (ok) {
					_lastSaved = html;
					_setStatus('✓ 自動保存済み');
					setTimeout(function () { _setStatus(''); }, 3000);
				} else {
					_setStatus('⚠ 自動保存失敗');
				}
			});
		}, 30000);
	}

	function _stopAutoSave() {
		if (_autoTimer) { clearInterval(_autoTimer); _autoTimer = null; }
		_statusEl = null;
	}

	function _setStatus(msg) {
		if (_statusEl) _statusEl.textContent = msg;
	}

	/* ─────────────────── 画像アップロード ─────────────────── */

	function _uploadAndInsert(file, editor) {
		if (!file || !file.type.match(/^image\//)) {
			alert('画像ファイルのみアップロードできます (JPEG/PNG/GIF/WebP)');
			return;
		}
		var csrfMeta = document.querySelector('meta[name="csrf-token"]');
		if (!csrfMeta) { console.error('[AP WYSIWYG] CSRF token not found'); return; }
		var csrf = csrfMeta.getAttribute('content');

		_setStatus('アップロード中...');

		var fd = new FormData();
		fd.append('ap_action', 'upload_image');
		fd.append('image', file);
		fd.append('csrf', csrf);

		fetch('index.php', {
			method: 'POST',
			headers: { 'X-CSRF-TOKEN': csrf },
			body: fd
		}).then(function (r) {
			if (!r.ok) throw new Error('HTTP ' + r.status);
			return r.json();
		}).then(function (data) {
			if (data.error) throw new Error(data.error);
			editor.focus();
			document.execCommand('insertImage', false, data.url);
			_setStatus('');
		}).catch(function (e) {
			alert('画像アップロード失敗: ' + e.message);
			_setStatus('');
		});
	}

	/* ─────────────────── Fetch ヘルパー ─────────────────── */

	/* コールバック付き保存（自動保存用） */
	function _fetchSave(key, val, callback) {
		var csrfMeta = document.querySelector('meta[name="csrf-token"]');
		if (!csrfMeta) { if (callback) callback(false); return; }
		var csrf = csrfMeta.getAttribute('content');
		fetch('index.php', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded',
				'X-CSRF-TOKEN': csrf
			},
			body: new URLSearchParams({ fieldname: key, content: val, csrf: csrf })
		}).then(function (r) {
			if (callback) callback(r.ok);
		}).catch(function () {
			if (callback) callback(false);
		});
	}

	/* ─────────────────── HTML サニタイズ ─────────────────── */
	/* 許可タグ: b i u strong em h2 h3 p br ul ol li a img        */
	/* 属性: a[href], img[src][alt] のみ                          */

	function _cleanHtml(html) {
		var tmp = document.createElement('div');
		tmp.innerHTML = html;
		_sanitizeNode(tmp);
		return tmp.innerHTML;
	}

	var _allowedTags = {
		B:1, I:1, U:1, STRONG:1, EM:1,
		H2:1, H3:1, P:1, BR:1,
		UL:1, OL:1, LI:1, A:1, IMG:1,
	};

	function _sanitizeNode(node) {
		var children = Array.from(node.childNodes);
		children.forEach(function (child) {
			if (child.nodeType !== 1) return; /* テキストノードはそのまま */
			if (!_allowedTags[child.tagName]) {
				var frag = document.createDocumentFragment();
				Array.from(child.childNodes).forEach(function (c) { frag.appendChild(c); });
				node.replaceChild(frag, child);
				_sanitizeNode(node);
				return;
			}
			/* 属性フィルタ */
			Array.from(child.attributes).forEach(function (attr) {
				var tag = child.tagName;
				var keep =
					(tag === 'A'   && attr.name === 'href') ||
					(tag === 'IMG' && (attr.name === 'src' || attr.name === 'alt'));
				if (!keep) child.removeAttribute(attr.name);
			});
			/* 危険スキーム除去 */
			if (child.tagName === 'A') {
				var href = child.getAttribute('href') || '';
				if (/^javascript:/i.test(href.trim())) child.removeAttribute('href');
			}
			if (child.tagName === 'IMG') {
				var src = child.getAttribute('src') || '';
				/* data: は image/* のみ許可、javascript: を拒否 */
				if (/^javascript:/i.test(src.trim()) ||
				    (/^data:/i.test(src.trim()) && !/^data:image\//i.test(src.trim()))) {
					child.removeAttribute('src');
				}
			}
			_sanitizeNode(child);
		});
	}

})();
