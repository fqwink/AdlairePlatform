'use strict';

/* ─────────────────────────────────────────────────────────────────────
   AdlairePlatform WYSIWYG Editor  (Ph3)
   依存ライブラリなし / vanilla JS

   機能:
   A. Undo/Redo ボタン（↩/↪）
   B. フローティングツールバー（テキスト選択時に浮上）
   C. 画像リサイズ（ドラッグハンドル）& alt 属性編集
   D. テーブルサポート（グリッドピッカー / 行列追加削除）
   E. 新ブロックタイプ: 引用（blockquote）・コード（pre）・区切り線（hr）
   F. "/" スラッシュコマンドメニュー（空行で "/" 入力）
   G. ブロックハンドル（ホバーで ⠿ → タイプ変換ポップアップ）
   H. ドラッグ並べ替え（ハンドルをドラッグ → ブロック順序変更）
   + 画像挿入: ツールバー / ドラッグ&ドロップ / クリップボード貼り付け
   + 定期自動保存（30秒）/ Ctrl+Enter 手動保存 / Esc キャンセル
   ───────────────────────────────────────────────────────────────────── */

(function () {

	/* ══════════════════════════ CSS 注入 ══════════════════════════ */
	var _css = [
		/* ── 固定ツールバー ── */
		'.ap-wy-wrap{position:relative;min-height:2em;}',
		'.ap-wy-toolbar{display:flex;flex-wrap:wrap;align-items:center;gap:2px;padding:4px;',
		'  background:#333;border-radius:4px 4px 0 0;user-select:none;}',
		'.ap-wy-btn{padding:2px 7px;cursor:pointer;border:1px solid #555;',
		'  background:#444;color:#eee;border-radius:3px;font-size:13px;line-height:1.4;}',
		'.ap-wy-btn:hover{background:#666;}',
		'.ap-wy-sep{color:#666;padding:0 2px;}',
		'.ap-wy-status{margin-left:auto;font-size:11px;color:#aaa;padding:0 4px;white-space:nowrap;}',
		/* ── 編集エリア ── */
		'.ap-wy-editor{min-height:3em;padding:6px;outline:2px solid #1ab;',
		'  border-radius:0 0 4px 4px;background:rgba(255,255,255,.05);',
		'  color:inherit;font-size:inherit;font-family:inherit;line-height:inherit;}',
		'.ap-wy-editor:focus{outline:2px solid #0df;}',
		'.ap-wy-editor.ap-wy-dragover{outline:2px dashed #0df;background:rgba(0,221,255,.08);}',
		'.ap-wy-editor img{max-width:100%;height:auto;display:block;margin:4px 0;}',
		/* ── テーブルスタイル（エディタ内） ── */
		'.ap-wy-editor table{border-collapse:collapse;width:100%;margin:4px 0;}',
		'.ap-wy-editor td,.ap-wy-editor th{border:1px solid #888;padding:4px 6px;min-width:30px;vertical-align:top;}',
		/* ── B: フローティングツールバー ── */
		'.ap-wy-float-bar{position:fixed;display:none;z-index:9999;',
		'  background:#222;border:1px solid #555;border-radius:4px;padding:2px;gap:1px;',
		'  box-shadow:0 2px 8px rgba(0,0,0,.5);}',
		'.ap-wy-float-bar .ap-wy-btn{font-size:12px;padding:1px 6px;}',
		/* ── C: 画像リサイズ ── */
		'.ap-wy-img-overlay{position:fixed;pointer-events:none;',
		'  border:2px solid #0df;box-sizing:border-box;z-index:1000;}',
		'.ap-wy-handle{position:absolute;width:10px;height:10px;',
		'  background:#0df;border-radius:2px;pointer-events:auto;z-index:1001;}',
		'.ap-wy-img-panel{position:fixed;background:#2a2a2a;border:1px solid #555;',
		'  padding:4px 8px;border-radius:3px;font-size:12px;z-index:1002;',
		'  display:flex;align-items:center;gap:6px;}',
		'.ap-wy-alt-input{background:#111;color:#eee;border:1px solid #555;',
		'  padding:2px 4px;border-radius:2px;font-size:11px;width:150px;}',
		/* ── D: テーブルグリッドピッカー ── */
		'.ap-wy-tbl-picker{position:fixed;background:#2a2a2a;border:1px solid #555;',
		'  padding:6px;border-radius:4px;z-index:2000;',
		'  display:grid;grid-template-columns:repeat(8,18px);gap:2px;}',
		'.ap-wy-tbl-cell{width:16px;height:16px;border:1px solid #555;',
		'  border-radius:1px;cursor:pointer;box-sizing:border-box;}',
		'.ap-wy-tbl-cell.hi{background:#0df;border-color:#0df;}',
		'.ap-wy-tbl-label{grid-column:1/9;text-align:center;color:#aaa;font-size:11px;',
		'  padding-bottom:3px;font-family:monospace;}',
		/* ── D: テーブル操作バー ── */
		'.ap-wy-tbl-bar{position:fixed;display:none;z-index:9998;',
		'  background:#2a2a2a;border:1px solid #555;border-radius:3px;',
		'  padding:2px;gap:2px;box-shadow:0 2px 6px rgba(0,0,0,.4);}',
		'.ap-wy-tbl-bar .ap-wy-btn{font-size:11px;padding:1px 5px;}',
		/* ── E: 新ブロックタイプ ── */
		'.ap-wy-editor blockquote{border-left:3px solid #1ab;margin:4px 0;',
		'  padding:4px 12px;color:#aaa;font-style:italic;}',
		'.ap-wy-editor pre{background:#1a1a1a;border:1px solid #444;',
		'  border-radius:4px;padding:8px 12px;font-family:monospace;',
		'  font-size:13px;white-space:pre-wrap;overflow-x:auto;margin:4px 0;}',
		'.ap-wy-editor pre code{background:none;padding:0;border:none;}',
		'.ap-wy-editor hr{border:none;border-top:2px solid #555;margin:12px 0;}',
		/* ── F: スラッシュメニュー ── */
		'.ap-wy-slash-menu{position:fixed;z-index:9995;background:#222;',
		'  border:1px solid #555;border-radius:4px;padding:4px;',
		'  min-width:180px;max-height:240px;overflow-y:auto;',
		'  box-shadow:0 2px 8px rgba(0,0,0,.5);}',
		'.ap-wy-slash-item{display:flex;align-items:center;gap:8px;',
		'  padding:5px 10px;border-radius:3px;cursor:pointer;color:#eee;font-size:13px;}',
		'.ap-wy-slash-item:hover,.ap-wy-slash-item.sel{background:#1ab;color:#fff;}',
		'.ap-wy-slash-icon{width:24px;height:24px;display:flex;align-items:center;',
		'  justify-content:center;background:#333;border-radius:3px;',
		'  font-size:11px;font-weight:bold;flex-shrink:0;}',
		/* ── G: ブロックハンドル ── */
		'.ap-wy-block-handle{position:fixed;z-index:9990;width:20px;',
		'  display:none;flex-direction:column;align-items:center;',
		'  padding:2px 0;cursor:pointer;user-select:none;}',
		'.ap-wy-block-handle.vis{display:flex;}',
		'.ap-wy-block-hdot{width:18px;height:22px;display:flex;align-items:center;',
		'  justify-content:center;color:#666;font-size:15px;',
		'  border-radius:3px;line-height:1;}',
		'.ap-wy-block-hdot:hover{background:#444;color:#bbb;}',
		'.ap-wy-type-popup{position:fixed;z-index:9995;background:#222;',
		'  border:1px solid #555;border-radius:4px;padding:4px;',
		'  min-width:150px;box-shadow:0 2px 8px rgba(0,0,0,.5);}',
		'.ap-wy-type-item{display:flex;align-items:center;gap:8px;',
		'  padding:4px 8px;border-radius:3px;cursor:pointer;color:#eee;font-size:13px;}',
		'.ap-wy-type-item:hover{background:#1ab;color:#fff;}',
		'.ap-wy-type-icon{width:22px;text-align:center;font-weight:bold;',
		'  font-size:12px;color:#aaa;flex-shrink:0;}',
		/* ── H: ドロップライン ── */
		'.ap-wy-drop-line{position:fixed;z-index:9990;height:2px;',
		'  background:#0df;pointer-events:none;',
		'  box-shadow:0 0 6px rgba(0,221,255,.6);}',
	].join('');
	var _styleEl = document.createElement('style');
	_styleEl.textContent = _css;
	document.head.appendChild(_styleEl);

	/* ══════════════════════════ ツールバー定義 ══════════════════════════ */
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
		{ cmd:'table',               label:'表',        title:'表を挿入' },
		{ cmd:'blockquote',          label:'❝',         title:'引用ブロック' },
		{ cmd:'pre',                 label:'{}',        title:'コードブロック' },
		{ cmd:'hr',                  label:'—',         title:'区切り線' },
		{ cmd:'removeFormat',        label:'✕',         title:'書式クリア' },
		{ sep:true },
		{ cmd:'undo',   label:'↩',      title:'元に戻す (Ctrl+Z)' },
		{ cmd:'redo',   label:'↪',      title:'やり直す (Ctrl+Y)' },
		{ sep:true },
		{ cmd:'save',   label:'✓ 保存', title:'保存 (Ctrl+Enter)' },
		{ cmd:'cancel', label:'✕ 取消', title:'キャンセル (Esc)'  },
	];

	/* ══════════════════════════ モジュールレベル状態 ══════════════════════════ */
	var _active        = false;
	var _autoTimer     = null;
	var _lastSaved     = '';
	var _statusEl      = null;
	var _currentSpan   = null;
	var _currentEditor = null;

	/* B: フローティングツールバー */
	var _floatBar      = null;
	var _selHandler    = null;   /* selectionchange ハンドラ参照 */

	/* C: 画像リサイズ */
	var _imgOverlay    = null;
	var _imgPanel      = null;
	var _selectedImg   = null;

	/* D: テーブル */
	var _tblPicker     = null;
	var _tblPickerClickHandler = null;
	var _tblBar        = null;
	var _currentTd     = null;
	var _currentTable  = null;

	/* Ph3: Editor.js スタイル */
	var _blockHandle      = null;   /* G: ブロックハンドル div */
	var _handleTarget     = null;   /* G: 現在指しているブロック要素 */
	var _hideHandleTimer  = null;   /* G: ハンドル非表示タイマー */
	var _blockHandleMoveFn = null;  /* G: mousemove ハンドラ参照（cleanup用） */
	var _editorLeaveFn    = null;   /* G: mouseleave ハンドラ参照（cleanup用） */
	var _typePopup        = null;   /* G: ブロックタイプ変換ポップアップ */
	var _typePopupOutFn   = null;   /* G: ポップアップ外クリックハンドラ参照 */
	var _slashMenu        = null;   /* F: "/" スラッシュメニュー */
	var _slashBlock       = null;   /* F: "/" を入力したブロック要素 */
	var _slashFilter      = '';     /* F: "/" 以降のフィルタ文字列 */
	var _slashIndex       = 0;      /* F: 現在選択中のメニュー項目 */
	var _slashOutFn       = null;   /* F: メニュー外クリックハンドラ参照 */
	var _dragBlock        = null;   /* H: ドラッグ中のブロック */
	var _dropLine         = null;   /* H: 挿入位置インジケータ */

	/* Ph3: ブロックタイプ定義（スラッシュメニュー・ハンドルポップアップ共用） */
	var BLOCK_CMDS = [
		{ cmd:'p',          icon:'¶',  name:'段落'          },
		{ cmd:'h2',         icon:'H2', name:'見出し2'        },
		{ cmd:'h3',         icon:'H3', name:'見出し3'        },
		{ cmd:'blockquote', icon:'❝',  name:'引用ブロック'   },
		{ cmd:'pre',        icon:'{}', name:'コードブロック' },
		{ cmd:'ul',         icon:'•',  name:'箇条書き'       },
		{ cmd:'ol',         icon:'1.', name:'番号リスト'     },
		{ cmd:'hr',         icon:'—',  name:'区切り線', isVoid:true },
		{ cmd:'table',      icon:'⊞',  name:'テーブル'       },
		{ cmd:'img',        icon:'🖼', name:'画像挿入'       },
	];

	/* ══════════════════════════ DOMContentLoaded ══════════════════════════ */
	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('span.editRich').forEach(function (span) {
			span.addEventListener('click', function () { _activate(span); });
		});
	});

	/* ══════════════════════════ エディタ起動 ══════════════════════════ */
	function _activate(span) {
		if (_active) return;
		_active = true;
		_currentSpan = span;

		var originalHtml = span.innerHTML;

		/* ── ツールバー構築 ── */
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

		_statusEl = document.createElement('span');
		_statusEl.className = 'ap-wy-status';
		toolbar.appendChild(_statusEl);

		/* ── 編集エリア ── */
		var editor = document.createElement('div');
		editor.contentEditable = 'true';
		editor.className = 'ap-wy-editor';
		editor.innerHTML = originalHtml;
		_currentEditor = editor;

		/* ── ラッパー ── */
		var wrap = document.createElement('div');
		wrap.className = 'ap-wy-wrap';
		wrap.appendChild(toolbar);
		wrap.appendChild(editor);

		span.innerHTML = '';
		span.appendChild(wrap);
		editor.focus();

		/* ── ブラウザ標準の画像リサイズハンドルを無効化（カスタムハンドルと競合防止） ── */
		try { document.execCommand('enableObjectResizing', false, false); } catch (e) { /* 非対応ブラウザを無視 */ }

		/* ── 自動保存 ── */
		_lastSaved = _cleanHtml(originalHtml);
		_startAutoSave(span.id);

		/* ── B: フローティングツールバー初期化 ── */
		_initFloatBar(editor);

		/* ── D: テーブル操作バー初期化 ── */
		_initTableBar(editor);

		/* ── ツールバー操作 ── */
		toolbar.addEventListener('mousedown', function (e) {
			e.preventDefault();
			var btn = e.target.closest('[data-cmd]');
			if (!btn) return;
			_exec(btn.dataset.cmd, editor, span, originalHtml, btn);
		});

		/* ── キーボードショートカット ── */
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

		/* ── C: 画像クリック検出 ── */
		editor.addEventListener('click', function (e) {
			if (e.target.tagName === 'IMG') {
				_showImageControls(e.target);
			} else {
				_hideImageControls();
			}
		});

		/* ── 画像: ドラッグ&ドロップ ── */
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

		/* ── 画像: クリップボード貼り付け ── */
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

		/* ── エリア外クリックで保存 ── */
		function _docHandler(e) {
			/* フローティングバー・オーバーレイ・ピッカー はエリア外判定から除外 */
			if (_floatBar && _floatBar.contains(e.target)) return;
			if (_imgPanel && _imgPanel.contains(e.target)) return;
			if (_tblBar && _tblBar.contains(e.target)) return;
			if (_tblPicker && _tblPicker.contains(e.target)) return;
			if (!wrap.contains(e.target)) {
				document.removeEventListener('mousedown', _docHandler);
				_manualSave(editor, span);
			}
		}
		document.addEventListener('mousedown', _docHandler);
	}

	/* ══════════════════════════ コマンド実行 ══════════════════════════ */
	function _exec(cmd, editor, span, originalHtml, triggerEl) {
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
			/* A: Undo/Redo */
			case 'undo':
				document.execCommand('undo', false, null);
				editor.focus();
				break;
			case 'redo':
				document.execCommand('redo', false, null);
				editor.focus();
				break;
			/* D: テーブル */
			case 'table':
				_showTablePicker(editor, triggerEl);
				break;
			/* E: 新ブロックタイプ */
			case 'blockquote':
				document.execCommand('formatBlock', false, 'blockquote');
				editor.focus();
				break;
			case 'pre':
				document.execCommand('formatBlock', false, 'pre');
				editor.focus();
				break;
			case 'hr':
				document.execCommand('insertHTML', false, '<hr><p><br></p>');
				editor.focus();
				break;
			case 'save':
				_manualSave(editor, span);
				break;
			case 'cancel':
				_cancel(span, originalHtml);
				break;
		}
	}

	/* ══════════════════════════ 保存・キャンセル ══════════════════════════ */

	function _manualSave(editor, span) {
		if (!_active) return;
		var html = _cleanHtml(editor.innerHTML);
		span.innerHTML = html || (span.getAttribute('title') || '');
		_apFieldSave(span.id, html);
		_cleanup();
	}

	function _cancel(span, originalHtml) {
		if (!_active) return;
		span.innerHTML = originalHtml;
		_cleanup();
	}

	function _cleanup() {
		_stopAutoSave();
		/* B: フローティングツールバー */
		if (_floatBar && _floatBar.parentNode) _floatBar.parentNode.removeChild(_floatBar);
		/* D: テーブル操作バー */
		if (_tblBar && _tblBar.parentNode) _tblBar.parentNode.removeChild(_tblBar);
		/* C: 画像コントロール */
		_hideImageControls();
		/* D: グリッドピッカー */
		_hideTablePicker();
		/* selectionchange 解除 */
		if (_selHandler) {
			document.removeEventListener('selectionchange', _selHandler);
			_selHandler = null;
		}
		/* 状態リセット */
		_floatBar = _tblBar = null;
		_selectedImg = _currentTd = _currentTable = null;
		_statusEl = null;
		_active = false;
		_currentSpan = _currentEditor = null;
	}

	/* ══════════════════════════ A: Undo/Redo（execCommand 利用） ══════════════════════════ */
	/* _exec() 内の case 'undo' / 'redo' で処理済み */

	/* ══════════════════════════ B: フローティングツールバー ══════════════════════════ */

	var FLOAT_TOOLS = [
		{ cmd:'bold',         label:'<b>B</b>' },
		{ cmd:'italic',       label:'<i>I</i>' },
		{ cmd:'underline',    label:'<u>U</u>' },
		{ cmd:'link',         label:'🔗' },
		{ cmd:'removeFormat', label:'✕' },
	];

	function _initFloatBar(editor) {
		var bar = document.createElement('div');
		bar.className = 'ap-wy-float-bar';
		bar.style.display = 'none';

		FLOAT_TOOLS.forEach(function (t) {
			var btn = document.createElement('button');
			btn.type = 'button';
			btn.className = 'ap-wy-btn';
			btn.innerHTML = t.label;
			btn.dataset.cmd = t.cmd;
			btn.addEventListener('mousedown', function (e) {
				e.preventDefault(); /* 選択を失わないよう防止 */
				if (t.cmd === 'link') {
					var url = prompt('URL を入力してください:');
					if (url && url.trim()) document.execCommand('createLink', false, url.trim());
				} else {
					document.execCommand(t.cmd, false, null);
				}
				editor.focus();
			});
			bar.appendChild(btn);
		});

		document.body.appendChild(bar);
		_floatBar = bar;

		/* selectionchange で位置更新（テーブルバーと共用） */
		_selHandler = function () {
			_updateFloatBar(editor);
			_updateTableBar(editor);
		};
		document.addEventListener('selectionchange', _selHandler);
	}

	function _updateFloatBar(editor) {
		var sel = window.getSelection();
		if (!sel || sel.isCollapsed || !sel.rangeCount) {
			if (_floatBar) _floatBar.style.display = 'none';
			return;
		}
		var range = sel.getRangeAt(0);
		if (!editor.contains(range.commonAncestorContainer)) {
			if (_floatBar) _floatBar.style.display = 'none';
			return;
		}
		var rect = range.getBoundingClientRect();
		if (!rect.width && !rect.height) {
			if (_floatBar) _floatBar.style.display = 'none';
			return;
		}
		_floatBar.style.display = 'flex';
		/* 選択範囲の上（36px）に配置、ビューポートからはみ出さない */
		var top = rect.top - 36;
		var left = rect.left;
		if (top < 4) top = rect.bottom + 4;
		_floatBar.style.left = Math.max(4, left) + 'px';
		_floatBar.style.top  = Math.max(4, top) + 'px';
	}

	/* ══════════════════════════ C: 画像リサイズ & alt編集 ══════════════════════════ */

	function _showImageControls(img) {
		_hideImageControls();
		_selectedImg = img;

		var rect = img.getBoundingClientRect();

		/* オーバーレイ枠（青い選択枠） */
		var overlay = document.createElement('div');
		overlay.className = 'ap-wy-img-overlay';
		_positionFixed(overlay, rect);
		overlay.style.pointerEvents = 'none';

		/* 4 コーナーハンドル */
		var handles = [
			{ pos:'nw', style:'top:-5px;left:-5px;cursor:nw-resize' },
			{ pos:'ne', style:'top:-5px;right:-5px;cursor:ne-resize' },
			{ pos:'sw', style:'bottom:-5px;left:-5px;cursor:sw-resize' },
			{ pos:'se', style:'bottom:-5px;right:-5px;cursor:se-resize' },
		];
		handles.forEach(function (h) {
			var handle = document.createElement('div');
			handle.className = 'ap-wy-handle';
			handle.style.cssText = h.style;
			handle.addEventListener('mousedown', function (e) {
				e.preventDefault();
				_startResize(e, img, h.pos, overlay);
			});
			overlay.appendChild(handle);
		});

		/* alt テキストパネル */
		var panel = document.createElement('div');
		panel.className = 'ap-wy-img-panel';
		panel.style.left = rect.left + 'px';
		panel.style.top  = (rect.bottom + 4) + 'px';

		var altLabel = document.createElement('span');
		altLabel.textContent = 'alt:';
		altLabel.style.cssText = 'color:#aaa;font-size:11px;';

		var altInput = document.createElement('input');
		altInput.type = 'text';
		altInput.className = 'ap-wy-alt-input';
		altInput.placeholder = 'alt テキスト';
		altInput.value = img.alt || '';
		altInput.addEventListener('input', function () { img.alt = altInput.value; });
		/* クリックが editor の blur 保存をトリガーしないよう止める */
		altInput.addEventListener('mousedown', function (e) { e.stopPropagation(); });
		/* Escape/Enter がエディタのキーハンドラに届かないよう止める */
		altInput.addEventListener('keydown', function (e) { e.stopPropagation(); });

		panel.appendChild(altLabel);
		panel.appendChild(altInput);

		document.body.appendChild(overlay);
		document.body.appendChild(panel);

		_imgOverlay = overlay;
		_imgPanel   = panel;
	}

	function _hideImageControls() {
		if (_imgOverlay && _imgOverlay.parentNode) _imgOverlay.parentNode.removeChild(_imgOverlay);
		if (_imgPanel   && _imgPanel.parentNode)   _imgPanel.parentNode.removeChild(_imgPanel);
		_imgOverlay = _imgPanel = _selectedImg = null;
	}

	function _startResize(e, img, pos, overlay) {
		var startX  = e.clientX;
		var startW  = img.offsetWidth  || img.naturalWidth  || 100;
		var startH  = img.offsetHeight || img.naturalHeight || 100;
		var aspect  = startH > 0 ? startW / startH : 1;

		function onMove(e) {
			var dx   = e.clientX - startX;
			var newW = (pos === 'nw' || pos === 'sw')
				? Math.max(20, startW - dx)
				: Math.max(20, startW + dx);
			var newH = Math.round(newW / aspect);
			newW = Math.round(newW);
			img.style.width  = newW + 'px';
			img.style.height = newH + 'px';
			/* オーバーレイ枠を追従 */
			var r = img.getBoundingClientRect();
			_positionFixed(overlay, r);
			/* alt パネルも追従 */
			if (_imgPanel) {
				_imgPanel.style.left = r.left + 'px';
				_imgPanel.style.top  = (r.bottom + 4) + 'px';
			}
		}

		function onUp() {
			document.removeEventListener('mousemove', onMove);
			document.removeEventListener('mouseup',   onUp);
		}

		document.addEventListener('mousemove', onMove);
		document.addEventListener('mouseup',   onUp);
	}

	function _positionFixed(el, rect) {
		el.style.left   = rect.left   + 'px';
		el.style.top    = rect.top    + 'px';
		el.style.width  = rect.width  + 'px';
		el.style.height = rect.height + 'px';
	}

	/* ══════════════════════════ D: テーブルサポート ══════════════════════════ */

	/* ── テーブル操作バー ── */
	var TBL_OPS = [
		{ op:'rowAdd', label:'行+', title:'行を下に追加' },
		{ op:'rowDel', label:'行−', title:'行を削除' },
		{ op:'colAdd', label:'列+', title:'列を右に追加' },
		{ op:'colDel', label:'列−', title:'列を削除' },
	];

	function _initTableBar(editor) {
		var bar = document.createElement('div');
		bar.className = 'ap-wy-tbl-bar';
		bar.style.display = 'none';

		TBL_OPS.forEach(function (t) {
			var btn = document.createElement('button');
			btn.type = 'button';
			btn.className = 'ap-wy-btn';
			btn.textContent = t.label;
			btn.title = t.title;
			btn.addEventListener('mousedown', function (e) {
				e.preventDefault();
				_tableOp(t.op, editor);
			});
			bar.appendChild(btn);
		});

		document.body.appendChild(bar);
		_tblBar = bar;
		/* _selHandler は _initFloatBar で登録済みなので追加不要 */
	}

	function _updateTableBar(editor) {
		if (!_tblBar) return;
		var sel = window.getSelection();
		if (!sel || !sel.rangeCount) { _tblBar.style.display = 'none'; return; }
		var node = sel.getRangeAt(0).startContainer;
		var td   = _findAncestor(node, 'TD') || _findAncestor(node, 'TH');
		var tbl  = td ? _findAncestor(td, 'TABLE') : null;
		if (!tbl || !editor.contains(tbl)) { _tblBar.style.display = 'none'; return; }
		_currentTd    = td;
		_currentTable = tbl;
		var rect = tbl.getBoundingClientRect();
		_tblBar.style.display = 'flex';
		_tblBar.style.left    = rect.left + 'px';
		_tblBar.style.top     = Math.max(4, rect.top - 30) + 'px';
	}

	function _tableOp(op, editor) {
		if (!_currentTd || !_currentTable) return;
		var td  = _currentTd;
		var tbl = _currentTable;

		switch (op) {
			case 'rowAdd': {
				var tr   = _findAncestor(td, 'TR');
				var newTr = document.createElement('TR');
				var cols  = tr.children.length;
				for (var i = 0; i < cols; i++) {
					var newTd = document.createElement(td.tagName);
					newTd.innerHTML = '&nbsp;';
					newTr.appendChild(newTd);
				}
				tr.parentNode.insertBefore(newTr, tr.nextSibling);
				break;
			}
			case 'rowDel': {
				var tr = _findAncestor(td, 'TR');
				if (tbl.rows.length <= 1) return; /* 最終行削除禁止 */
				tr.parentNode.removeChild(tr);
				_currentTd    = null;
				_currentTable = null;
				_tblBar.style.display = 'none';
				break;
			}
			case 'colAdd': {
				var tr     = _findAncestor(td, 'TR');
				var colIdx = Array.from(tr.children).indexOf(td);
				Array.from(tbl.rows).forEach(function (row) {
					var newCell = document.createElement(row.cells[colIdx] ? row.cells[colIdx].tagName : 'TD');
					newCell.innerHTML = '&nbsp;';
					var after = row.cells[colIdx + 1] || null;
					row.insertBefore(newCell, after);
				});
				break;
			}
			case 'colDel': {
				var tr     = _findAncestor(td, 'TR');
				var colIdx = Array.from(tr.children).indexOf(td);
				if (tbl.rows[0] && tbl.rows[0].cells.length <= 1) return; /* 最終列削除禁止 */
				Array.from(tbl.rows).forEach(function (row) {
					if (row.cells[colIdx]) row.removeChild(row.cells[colIdx]);
				});
				break;
			}
		}
		editor.focus();
		/* テーブルバー位置を更新 */
		var rect = tbl.getBoundingClientRect();
		_tblBar.style.left = rect.left + 'px';
		_tblBar.style.top  = Math.max(4, rect.top - 30) + 'px';
	}

	/* ── テーブルグリッドピッカー ── */

	function _showTablePicker(editor, btn) {
		_hideTablePicker();

		var btnRect = btn ? btn.getBoundingClientRect() : { left:100, bottom:100 };

		var picker = document.createElement('div');
		picker.className = 'ap-wy-tbl-picker';
		picker.style.left = btnRect.left + 'px';
		picker.style.top  = (btnRect.bottom + 4) + 'px';

		var label = document.createElement('div');
		label.className = 'ap-wy-tbl-label';
		label.textContent = '表のサイズ';
		picker.appendChild(label);

		var cells = [];
		for (var r = 1; r <= 8; r++) {
			for (var c = 1; c <= 8; c++) {
				(function (row, col) {
					var cell = document.createElement('div');
					cell.className = 'ap-wy-tbl-cell';
					cell.addEventListener('mouseenter', function () {
						cells.forEach(function (ci) {
							var cr = +ci.dataset.r, cc = +ci.dataset.c;
							ci.classList.toggle('hi', cr <= row && cc <= col);
						});
						label.textContent = row + ' × ' + col;
					});
					cell.addEventListener('click', function (e) {
						e.stopPropagation();
						_hideTablePicker();
						_insertTable(row, col, editor);
					});
					cell.dataset.r = r;
					cell.dataset.c = c;
					cells.push(cell);
					picker.appendChild(cell);
				})(r, c);
			}
		}

		document.body.appendChild(picker);
		_tblPicker = picker;

		/* エリア外クリックで閉じる */
		_tblPickerClickHandler = function (e) {
			if (!picker.contains(e.target)) _hideTablePicker();
		};
		setTimeout(function () {
			document.addEventListener('click', _tblPickerClickHandler);
		}, 0);
	}

	function _hideTablePicker() {
		if (_tblPicker && _tblPicker.parentNode) _tblPicker.parentNode.removeChild(_tblPicker);
		_tblPicker = null;
		if (_tblPickerClickHandler) {
			document.removeEventListener('click', _tblPickerClickHandler);
			_tblPickerClickHandler = null;
		}
	}

	function _insertTable(rows, cols, editor) {
		var html = '<table><tbody>';
		for (var r = 0; r < rows; r++) {
			html += '<tr>';
			for (var c = 0; c < cols; c++) {
				html += '<td>&nbsp;</td>';
			}
			html += '</tr>';
		}
		/* 末尾の <p> でテーブル外へカーソルを移動できるようにする */
		html += '</tbody></table><p><br></p>';
		editor.focus();
		document.execCommand('insertHTML', false, html);
	}

	function _findAncestor(node, tag) {
		var n = node;
		while (n && n.nodeType !== 9) {
			if (n.nodeType === 1 && n.tagName === tag) return n;
			n = n.parentNode;
		}
		return null;
	}

	/* ══════════════════════════ 自動保存 ══════════════════════════ */

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
	}

	function _setStatus(msg) {
		if (_statusEl) _statusEl.textContent = msg;
	}

	/* ══════════════════════════ 画像アップロード ══════════════════════════ */

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

	/* ══════════════════════════ Fetch ヘルパー ══════════════════════════ */

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

	/* ══════════════════════════ HTML サニタイズ ══════════════════════════ */
	/*
	 * 許可タグ: B I U STRONG EM H2 H3 P BR UL OL LI A IMG
	 *           TABLE TBODY THEAD TFOOT TR TD TH
	 * 属性:     A[href]  IMG[src][alt]  TD/TH[colspan][rowspan]
	 */

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
		TABLE:1, TBODY:1, THEAD:1, TFOOT:1, TR:1, TD:1, TH:1,
		/* Ph3 E: 新ブロックタイプ */
		BLOCKQUOTE:1, PRE:1, CODE:1, HR:1,
	};

	function _sanitizeNode(node) {
		Array.from(node.childNodes).forEach(function (child) {
			if (child.nodeType !== 1) return; /* テキストノード等はスキップ */
			if (!_allowedTags[child.tagName]) {
				/* 許可外タグは中身を保持してタグだけ除去 */
				var frag = document.createDocumentFragment();
				Array.from(child.childNodes).forEach(function (c) { frag.appendChild(c); });
				node.replaceChild(frag, child);
				_sanitizeNode(node);
				return;
			}
			/* 属性フィルタ */
			Array.from(child.attributes).forEach(function (attr) {
				var keep = false;
				var tag  = child.tagName;
				if (tag === 'A'   && attr.name === 'href')    keep = true;
				if (tag === 'IMG' && (attr.name === 'src' || attr.name === 'alt' || attr.name === 'style'))  keep = true;
				if ((tag === 'TD' || tag === 'TH') && (attr.name === 'colspan' || attr.name === 'rowspan')) keep = true;
				if (!keep) child.removeAttribute(attr.name);
			});
			/* 危険スキーム除去 */
			if (child.tagName === 'A') {
				var href = child.getAttribute('href') || '';
				if (/^javascript:/i.test(href.trim())) child.removeAttribute('href');
			}
			if (child.tagName === 'IMG') {
				var src = child.getAttribute('src') || '';
				if (/^javascript:/i.test(src.trim()) ||
				    (/^data:/i.test(src.trim()) && !/^data:image\//i.test(src.trim()))) {
					child.removeAttribute('src');
				}
			}
			_sanitizeNode(child);
		});
	}

})();
