'use strict';

/* ─────────────────────────────────────────────
   AdlairePlatform WYSIWYG Editor  (Ph3)
   依存ライブラリなし / vanilla JS (ES2020+)

   Editor.js スタイル ブロックベースエディタ

   機能:
   - ブロックベース contenteditable（各ブロック独立）
   - ブロックタイプ: paragraph / heading(h2,h3) / list(ul,ol) /
     blockquote / code / delimiter(hr) / table / image / checklist
   - インラインツール: B / I / U / S / Code / Link / Marker
   - フローティングインラインツールバー（テキスト選択時）
   - "/" スラッシュコマンドメニュー（空行で / 入力）
   - ブロックハンドル ⠿（ホバー時表示、タイプ変換ポップアップ）
   - ドラッグ並べ替え（⠿ ハンドルでブロック順序変更）
   - 画像: ボタン / D&D / クリップボード / キャプション / Alt
   - テーブル: セル編集 / 行列追加削除 / Tab移動
   - Block Tunes（テキスト配置: 左/中央/右）
   - 定期自動保存 (30秒) / 手動保存 (Ctrl+Enter) / Escape キャンセル
   - ARIA アクセシビリティ対応
   ───────────────────────────────────────────── */

(function () {

/* ══════════════════════════════════════════════
   CSS 注入
   ══════════════════════════════════════════════ */
const _css = `
/* ── 共通 ── */
.ap-wy-wrap{position:relative;min-height:2em;}
.ap-wy-toolbar{display:flex;flex-wrap:wrap;align-items:center;gap:2px;padding:4px;
  background:#333;border-radius:4px 4px 0 0;user-select:none;}
.ap-wy-btn{padding:2px 7px;cursor:pointer;border:1px solid #555;
  background:#444;color:#eee;border-radius:3px;font-size:13px;line-height:1.4;}
.ap-wy-btn:hover{background:#666;}
.ap-wy-btn.ap-wy-active{background:#0ad;color:#000;border-color:#0ad;}
.ap-wy-sep{color:#666;padding:0 2px;}
.ap-wy-status{margin-left:auto;font-size:11px;color:#aaa;padding:0 4px;white-space:nowrap;}

/* ── ブロックコンテナ ── */
.ap-wy-blocks{min-height:3em;padding:6px 6px 6px 30px;outline:2px solid #1ab;
  border-radius:0 0 4px 4px;background:rgba(255,255,255,.05);
  color:inherit;font-size:inherit;font-family:inherit;line-height:inherit;}
.ap-wy-blocks:focus-within{outline:2px solid #0df;}

/* ── ブロック ── */
.ap-wy-block{position:relative;margin:2px 0;border-radius:3px;transition:background .15s;}
.ap-wy-block:hover{background:rgba(255,255,255,.03);}
.ap-wy-block-content{min-height:1.4em;padding:2px 4px;outline:none;
  word-break:break-word;overflow-wrap:break-word;}
.ap-wy-block-content:focus{background:rgba(0,221,255,.04);border-radius:2px;}

/* ── ブロックハンドル ── */
.ap-wy-block-handle{position:absolute;left:-26px;top:2px;width:20px;height:20px;
  cursor:grab;opacity:0;transition:opacity .15s;display:flex;align-items:center;
  justify-content:center;font-size:12px;color:#888;user-select:none;border-radius:3px;}
.ap-wy-block:hover .ap-wy-block-handle,.ap-wy-block-handle:hover{opacity:1;}
.ap-wy-block-handle:hover{background:rgba(255,255,255,.1);color:#ccc;}
.ap-wy-block-handle:active{cursor:grabbing;}

/* ── ブロックタイプ別スタイル ── */
.ap-wy-block[data-type="heading"] .ap-wy-block-content{font-weight:bold;}
.ap-wy-block[data-level="2"] .ap-wy-block-content{font-size:1.5em;margin:8px 0 4px;}
.ap-wy-block[data-level="3"] .ap-wy-block-content{font-size:1.2em;margin:6px 0 3px;}
.ap-wy-block[data-type="quote"] .ap-wy-block-content{border-left:3px solid #0ad;
  padding-left:12px;font-style:italic;color:#ccc;}
.ap-wy-block[data-type="code"] .ap-wy-block-content{font-family:monospace;font-size:13px;
  background:rgba(0,0,0,.3);padding:8px;border-radius:4px;white-space:pre-wrap;}
.ap-wy-block[data-type="delimiter"]{text-align:center;padding:8px 0;pointer-events:auto;}
.ap-wy-block[data-type="delimiter"] hr{border:none;border-top:2px solid #555;margin:0;}
.ap-wy-block[data-type="image"] img{max-width:100%;height:auto;display:block;margin:4px 0;border-radius:4px;}
.ap-wy-block[data-type="image"] .ap-wy-figcaption{font-size:0.85em;color:#999;
  text-align:center;padding:4px;outline:none;min-height:1em;}
.ap-wy-block[data-type="image"] .ap-wy-figcaption:empty::before{content:'キャプションを入力...';color:#666;}
.ap-wy-block[data-type="image"] .ap-wy-img-actions{display:flex;gap:4px;padding:4px;flex-wrap:wrap;}
.ap-wy-block[data-type="image"] .ap-wy-img-size-btn{padding:2px 6px;font-size:11px;
  background:#444;color:#ddd;border:1px solid #555;border-radius:3px;cursor:pointer;}
.ap-wy-block[data-type="image"] .ap-wy-img-size-btn:hover{background:#666;}
.ap-wy-block[data-type="image"] .ap-wy-img-size-btn.active{background:#0ad;color:#000;}

/* ── テーブル ── */
.ap-wy-block[data-type="table"] .ap-wy-table{width:100%;border-collapse:collapse;margin:4px 0;}
.ap-wy-block[data-type="table"] .ap-wy-table td{border:1px solid #555;padding:4px 6px;
  min-width:40px;outline:none;vertical-align:top;}
.ap-wy-block[data-type="table"] .ap-wy-table td:focus{background:rgba(0,221,255,.08);}
.ap-wy-block[data-type="table"] .ap-wy-tbl-actions{display:flex;gap:4px;padding:4px 0;}
.ap-wy-block[data-type="table"] .ap-wy-tbl-btn{padding:2px 8px;font-size:11px;
  background:#444;color:#ddd;border:1px solid #555;border-radius:3px;cursor:pointer;}
.ap-wy-block[data-type="table"] .ap-wy-tbl-btn:hover{background:#666;}

/* ── チェックリスト ── */
.ap-wy-block[data-type="checklist"] .ap-wy-check-item{display:flex;align-items:flex-start;gap:6px;padding:2px 0;}
.ap-wy-block[data-type="checklist"] .ap-wy-check-box{width:16px;height:16px;margin-top:3px;
  cursor:pointer;accent-color:#0ad;flex-shrink:0;}
.ap-wy-block[data-type="checklist"] .ap-wy-check-text{flex:1;outline:none;min-height:1.4em;}
.ap-wy-block[data-type="checklist"] .ap-wy-check-item.checked .ap-wy-check-text{
  text-decoration:line-through;opacity:.6;}

/* ── スラッシュコマンドメニュー ── */
.ap-wy-slash-menu{position:absolute;z-index:1000;background:#2a2a2a;border:1px solid #555;
  border-radius:6px;padding:4px;min-width:180px;max-height:260px;overflow-y:auto;
  box-shadow:0 4px 12px rgba(0,0,0,.4);}
.ap-wy-slash-item{padding:6px 10px;cursor:pointer;border-radius:4px;display:flex;
  align-items:center;gap:8px;font-size:13px;color:#ddd;}
.ap-wy-slash-item:hover,.ap-wy-slash-item.active{background:#0ad;color:#000;}
.ap-wy-slash-item-icon{width:24px;text-align:center;font-size:15px;}
.ap-wy-slash-item-label{flex:1;}

/* ── タイプ変換ポップアップ ── */
.ap-wy-type-popup{position:absolute;z-index:1000;background:#2a2a2a;border:1px solid #555;
  border-radius:6px;padding:4px;min-width:160px;box-shadow:0 4px 12px rgba(0,0,0,.4);}
.ap-wy-type-popup-item{padding:5px 10px;cursor:pointer;border-radius:4px;font-size:13px;color:#ddd;}
.ap-wy-type-popup-item:hover{background:#0ad;color:#000;}
.ap-wy-type-popup-sep{border-top:1px solid #444;margin:3px 0;}
.ap-wy-type-popup-item.danger{color:#f66;}
.ap-wy-type-popup-item.danger:hover{background:#f44;color:#fff;}

/* ── インラインツールバー ── */
.ap-wy-inline-tb{position:absolute;z-index:1001;display:flex;gap:1px;padding:3px;
  background:#222;border:1px solid #555;border-radius:5px;box-shadow:0 2px 8px rgba(0,0,0,.5);
  user-select:none;transform:translateX(-50%);}
.ap-wy-inline-tb .ap-wy-btn{font-size:12px;padding:2px 6px;}
.ap-wy-inline-tb .ap-wy-link-input{width:160px;padding:2px 4px;font-size:12px;
  background:#333;color:#eee;border:1px solid #555;border-radius:3px;outline:none;}

/* ── ドラッグ ── */
.ap-wy-block.dragging{opacity:.4;}
.ap-wy-drop-line{position:absolute;left:0;right:0;height:3px;background:#0df;
  border-radius:2px;pointer-events:none;z-index:999;}

/* ── 画像D&D ── */
.ap-wy-blocks.ap-wy-dragover{outline:2px dashed #0df;background:rgba(0,221,255,.08);}

/* ── Block Tunes ── */
.ap-wy-block[data-align="center"] .ap-wy-block-content{text-align:center;}
.ap-wy-block[data-align="right"] .ap-wy-block-content{text-align:right;}

/* ── 空ブロックプレースホルダ ── */
.ap-wy-block[data-type="paragraph"] .ap-wy-block-content:empty::before{
  content:'/ を入力してコマンド...';color:#666;pointer-events:none;font-style:italic;}
`;
const _styleEl = document.createElement('style');
_styleEl.textContent = _css;
document.head.appendChild(_styleEl);

/* ══════════════════════════════════════════════
   定義
   ══════════════════════════════════════════════ */

/* ── ツールバー定義（上部固定） ── */
const TOOLS = [
	{ cmd:'bold',      label:'<b>B</b>',  title:'太字 (Ctrl+B)',       aria:'太字' },
	{ cmd:'italic',    label:'<i>I</i>',  title:'斜体 (Ctrl+I)',       aria:'斜体' },
	{ cmd:'underline', label:'<u>U</u>',  title:'下線 (Ctrl+U)',       aria:'下線' },
	{ cmd:'strike',    label:'<s>S</s>',  title:'取消線 (Ctrl+Shift+S)', aria:'取消線' },
	{ cmd:'inlineCode',label:'<code>&lt;&gt;</code>', title:'インラインコード (Ctrl+E)', aria:'コード' },
	{ cmd:'marker',    label:'<mark>M</mark>', title:'マーカー (Ctrl+Shift+M)', aria:'マーカー' },
	{ sep:true },
	{ cmd:'h2',    label:'H2',  title:'見出し2',   aria:'見出し2' },
	{ cmd:'h3',    label:'H3',  title:'見出し3',   aria:'見出し3' },
	{ cmd:'p',     label:'¶',   title:'段落',      aria:'段落' },
	{ cmd:'quote', label:'❝',   title:'引用',      aria:'引用' },
	{ cmd:'codeBlock', label:'{}',  title:'コードブロック', aria:'コードブロック' },
	{ cmd:'delimiter', label:'—',   title:'区切り線',      aria:'区切り線' },
	{ sep:true },
	{ cmd:'insertUnorderedList', label:'•≡',  title:'箇条書き',   aria:'箇条書き' },
	{ cmd:'insertOrderedList',   label:'1≡',  title:'番号リスト', aria:'番号リスト' },
	{ sep:true },
	{ cmd:'link',  label:'🔗', title:'リンク (Ctrl+K)',  aria:'リンク' },
	{ cmd:'img',   label:'🖼', title:'画像挿入',          aria:'画像' },
	{ cmd:'table', label:'📊', title:'テーブル挿入',      aria:'テーブル' },
	{ cmd:'removeFormat', label:'✕', title:'書式クリア', aria:'書式クリア' },
	{ sep:true },
	{ cmd:'save',   label:'✓ 保存', title:'保存 (Ctrl+Enter)', aria:'保存' },
	{ cmd:'cancel', label:'✕ 取消', title:'キャンセル (Esc)',   aria:'取消' },
];

/* ── スラッシュコマンド定義 ── */
const SLASH_COMMANDS = [
	{ type:'paragraph',  icon:'¶',   label:'段落',           keywords:'paragraph p' },
	{ type:'h2',         icon:'H2',  label:'見出し2',        keywords:'heading h2' },
	{ type:'h3',         icon:'H3',  label:'見出し3',        keywords:'heading h3' },
	{ type:'ul',         icon:'•≡',  label:'箇条書きリスト', keywords:'list ul bullet' },
	{ type:'ol',         icon:'1≡',  label:'番号リスト',     keywords:'list ol number' },
	{ type:'quote',      icon:'❝',   label:'引用',           keywords:'quote blockquote' },
	{ type:'code',       icon:'{}',  label:'コードブロック', keywords:'code pre' },
	{ type:'delimiter',  icon:'—',   label:'区切り線',       keywords:'delimiter hr line' },
	{ type:'table',      icon:'📊',  label:'テーブル',       keywords:'table grid' },
	{ type:'image',      icon:'🖼',  label:'画像',           keywords:'image img photo' },
	{ type:'checklist',  icon:'☑',   label:'チェックリスト', keywords:'checklist todo check' },
];

/* ── 許可タグ（サニタイザー） ── */
const _allowedTags = {
	B:1, I:1, U:1, S:1, STRONG:1, EM:1, MARK:1, CODE:1,
	H2:1, H3:1, P:1, BR:1, BLOCKQUOTE:1, PRE:1, HR:1,
	UL:1, OL:1, LI:1, A:1, IMG:1,
	TABLE:1, THEAD:1, TBODY:1, TR:1, TH:1, TD:1,
	FIGURE:1, FIGCAPTION:1,
};

/* ── 状態 ── */
let _active       = false;
let _autoTimer    = null;
let _lastSaved    = '';
let _statusEl     = null;
let _currentSpan  = null;
let _blocksEl     = null;
let _blocks       = [];   // 内部ブロックモデル
let _slashMenu    = null;
let _slashFilter  = '';
let _slashBlock   = null;
let _slashIdx     = 0;
let _typePopup    = null;
let _inlineToolbar = null;
let _dragBlock    = null;
let _dragStarted  = false;
let _dropLine     = null;
let _idCounter    = 0;
let _undoStack    = [];
let _redoStack    = [];
const _UNDO_LIMIT = 50;

/* ── ユーティリティ ── */
function _uid() { return 'b' + (++_idCounter) + '-' + Math.random().toString(36).slice(2, 7); }

function _isSafeUrl(url) {
	const trimmed = (url || '').trim();
	if (/^(https?:|mailto:|\/)/i.test(trimmed)) return true;
	if (/^[a-z0-9#]/i.test(trimmed) && !trimmed.includes(':')) return true; /* 相対パス */
	return false;
}

function _getCsrf() {
	const meta = document.querySelector('meta[name="csrf-token"]');
	return meta ? meta.getAttribute('content') : null;
}

/* ══════════════════════════════════════════════
   初期化 & エディタ起動
   ══════════════════════════════════════════════ */

document.addEventListener('DOMContentLoaded', () => {
	document.querySelectorAll('span.editRich').forEach(span => {
		span.addEventListener('click', () => { _activate(span); });
	});
});

function _activate(span) {
	if (_active) return;
	_active = true;
	_currentSpan = span;
	if (typeof _apChanging !== 'undefined') _apChanging = true;

	const originalHtml = span.innerHTML;

	/* Chromium ネイティブリサイズハンドルを無効化 (Ph2-2) */
	try {
		document.execCommand('enableObjectResizing', false, false);
		document.execCommand('enableInlineTableEditing', false, false);
	} catch (e) { console.warn('[AP WYSIWYG] execCommand not supported:', e.message); }

	/* ─ ツールバー構築 ─ */
	const toolbar = document.createElement('div');
	toolbar.className = 'ap-wy-toolbar';
	toolbar.setAttribute('role', 'toolbar');
	toolbar.setAttribute('aria-label', 'エディタツールバー');

	TOOLS.forEach(t => {
		if (t.sep) {
			const sep = document.createElement('span');
			sep.className = 'ap-wy-sep';
			sep.textContent = '|';
			sep.setAttribute('aria-hidden', 'true');
			toolbar.appendChild(sep);
			return;
		}
		const btn = document.createElement('button');
		btn.type = 'button';
		btn.className = 'ap-wy-btn';
		btn.innerHTML = t.label;
		btn.title = t.title || '';
		btn.dataset.cmd = t.cmd;
		btn.setAttribute('aria-label', t.aria || t.title);
		toolbar.appendChild(btn);
	});

	_statusEl = document.createElement('span');
	_statusEl.className = 'ap-wy-status';
	_statusEl.setAttribute('aria-live', 'polite');
	toolbar.appendChild(_statusEl);

	/* ─ ブロックコンテナ構築 ─ */
	_blocksEl = document.createElement('div');
	_blocksEl.className = 'ap-wy-blocks';
	_blocksEl.setAttribute('role', 'document');
	_blocksEl.setAttribute('aria-label', 'エディタ本文');

	/* 既存 HTML をブロックにパース */
	_blocks = _parseHtmlToBlocks(originalHtml);
	if (_blocks.length === 0) _blocks.push({ id: _uid(), type: 'paragraph', data: { text: '' } });
	_blocks.forEach(b => _blocksEl.appendChild(_renderBlock(b)));

	/* ─ ラッパー ─ */
	const wrap = document.createElement('div');
	wrap.className = 'ap-wy-wrap';
	wrap.appendChild(toolbar);
	wrap.appendChild(_blocksEl);

	span.innerHTML = '';
	span.appendChild(wrap);

	/* 最初のブロックにフォーカス */
	const firstContent = _blocksEl.querySelector('.ap-wy-block-content[contenteditable="true"]');
	if (firstContent) { firstContent.focus(); _setCursorToEnd(firstContent); }

	/* ─ 自動保存開始 ─ */
	_lastSaved = _cleanHtml(originalHtml);
	_undoStack = [];
	_redoStack = [];
	_saveSnapshot();
	_startAutoSave(span.id);

	/* ─ ツールバークリック ─ */
	toolbar.addEventListener('mousedown', e => {
		e.preventDefault();
		const btn = e.target.closest('[data-cmd]');
		if (!btn) return;
		_exec(btn.dataset.cmd, span, originalHtml);
	});

	/* ─ グローバルキーボードショートカット ─ */
	_blocksEl.addEventListener('keydown', e => {
		const mod = e.ctrlKey || e.metaKey;
		if (mod && e.key === 'Enter') { e.preventDefault(); _manualSave(span); }
		else if (e.key === 'Escape') { e.preventDefault(); _hideSlashMenu(); _hideTypePopup(); _hideInlineToolbar(); _cancel(span, originalHtml); }
		else if (mod && e.shiftKey && e.key.toLowerCase() === 's') { e.preventDefault(); _toggleInline('strikeThrough'); }
		else if (mod && e.shiftKey && e.key.toLowerCase() === 'm') { e.preventDefault(); _toggleMarker(); }
		else if (mod && e.key.toLowerCase() === 'e') { e.preventDefault(); _toggleInlineCode(); }
		else if (mod && e.key.toLowerCase() === 'k') { e.preventDefault(); _showLinkInput(); }
		else if (mod && e.shiftKey && e.key.toLowerCase() === 'z') { e.preventDefault(); _redo(); }
		else if (mod && e.key.toLowerCase() === 'z') { e.preventDefault(); _undo(); }
		else if (mod && e.key.toLowerCase() === 'y') { e.preventDefault(); _redo(); }
	});

	/* ─ テキスト選択時にインラインツールバー表示 ─ */
	document.addEventListener('selectionchange', _onSelectionChange);

	/* ─ 画像 D&D（ブロックコンテナ全体） ─ */
	_blocksEl.addEventListener('dragover', e => {
		if (e.dataTransfer.types.indexOf('Files') !== -1) {
			e.preventDefault();
			_blocksEl.classList.add('ap-wy-dragover');
		}
	});
	_blocksEl.addEventListener('dragleave', () => { _blocksEl.classList.remove('ap-wy-dragover'); });
	_blocksEl.addEventListener('drop', e => {
		_blocksEl.classList.remove('ap-wy-dragover');
		const files = e.dataTransfer.files;
		if (files && files.length > 0 && files[0].type.match(/^image\//)) {
			e.preventDefault();
			_insertImageBlock(files[0]);
		}
	});

	/* ─ クリップボード（画像 & リッチテキスト） ─ */
	_blocksEl.addEventListener('paste', e => {
		const cd = e.clipboardData;
		if (!cd) return;

		/* 画像ペースト */
		const items = cd.items;
		if (items) {
			for (let i = 0; i < items.length; i++) {
				if (items[i].type.indexOf('image/') === 0) {
					e.preventDefault();
					_insertImageBlock(items[i].getAsFile());
					return;
				}
			}
		}

		/* コードブロック内では plaintext のみ（既存の per-block handler で処理済み） */
		const focused = _getFocusedBlock();
		if (focused && focused.type === 'code') return;

		/* リッチテキストペースト: HTML をサニタイズして挿入 */
		const html = cd.getData('text/html');
		if (html) {
			e.preventDefault();
			const clean = _cleanHtml(html);
			document.execCommand('insertHTML', false, clean);
		}
	});

	/* ─ エリア外クリックで保存 ─ */
	function _docHandler(e) {
		if (!wrap.contains(e.target)) {
			document.removeEventListener('mousedown', _docHandler);
			document.removeEventListener('selectionchange', _onSelectionChange);
			_manualSave(span);
		}
	}
	document.addEventListener('mousedown', _docHandler);
}

/* ══════════════════════════════════════════════
   HTML ↔ ブロック変換
   ══════════════════════════════════════════════ */

/* HTML文字列 → ブロック配列 */
function _parseHtmlToBlocks(html) {
	const tmp = document.createElement('div');
	tmp.innerHTML = html;
	const blocks = [];

	const _pushText = (type, node, extra) => {
		const b = { id: _uid(), type, data: { text: node.innerHTML.trim(), ...extra } };
		blocks.push(b);
	};

	Array.from(tmp.childNodes).forEach(node => {
		if (node.nodeType === 3) {
			const t = node.textContent.trim();
			if (t) blocks.push({ id: _uid(), type: 'paragraph', data: { text: t } });
			return;
		}
		if (node.nodeType !== 1) return;
		const tag = node.tagName;

		if (tag === 'H2') { _pushText('heading', node, { level: 2 }); }
		else if (tag === 'H3') { _pushText('heading', node, { level: 3 }); }
		else if (tag === 'BLOCKQUOTE') { _pushText('quote', node); }
		else if (tag === 'PRE') {
			const code = node.querySelector('code');
			blocks.push({ id: _uid(), type: 'code', data: { text: (code || node).textContent } });
		}
		else if (tag === 'HR') { blocks.push({ id: _uid(), type: 'delimiter', data: {} }); }
		else if (tag === 'UL' && node.classList.contains('ap-checklist')) {
			const items = Array.from(node.querySelectorAll('li')).map(li => ({
				text: li.innerHTML.trim(),
				checked: li.classList.contains('ap-checked'),
			}));
			blocks.push({ id: _uid(), type: 'checklist', data: { items } });
		}
		else if (tag === 'UL' || tag === 'OL') {
			const items = Array.from(node.querySelectorAll('li')).map(li => li.innerHTML.trim());
			blocks.push({ id: _uid(), type: 'list', data: { style: tag === 'OL' ? 'ordered' : 'unordered', items } });
		}
		else if (tag === 'TABLE') {
			const rows = Array.from(node.querySelectorAll('tr')).map(tr =>
				Array.from(tr.querySelectorAll('td,th')).map(cell => cell.innerHTML.trim())
			);
			blocks.push({ id: _uid(), type: 'table', data: { rows } });
		}
		else if (tag === 'FIGURE') {
			const img = node.querySelector('img');
			const cap = node.querySelector('figcaption');
			if (img) {
				blocks.push({ id: _uid(), type: 'image', data: {
					src: img.getAttribute('src') || '',
					alt: img.getAttribute('alt') || '',
					caption: cap ? cap.innerHTML.trim() : '',
					width: img.style.width || '100%',
				}});
			}
		}
		else if (tag === 'IMG') {
			blocks.push({ id: _uid(), type: 'image', data: {
				src: node.getAttribute('src') || '',
				alt: node.getAttribute('alt') || '',
				caption: '', width: '100%',
			}});
		}
		else { /* P, DIV, その他 → paragraph */
			_pushText('paragraph', node);
		}
	});
	return blocks;
}

/* ブロック配列 → HTML文字列 */
function _serializeBlocks() {
	return _blocks.map(b => {
		const d = b.data;
		const alignStyle = b.data.align && b.data.align !== 'left' ? ` style="text-align:${b.data.align}"` : '';

		switch (b.type) {
			case 'paragraph':    return `<p${alignStyle}>${d.text || '<br>'}</p>`;
			case 'heading':      return `<h${d.level || 2}${alignStyle}>${d.text || ''}</h${d.level || 2}>`;
			case 'quote':        return `<blockquote${alignStyle}>${d.text || ''}</blockquote>`;
			case 'code':         return `<pre><code>${_escHtml(d.text || '')}</code></pre>`;
			case 'delimiter':    return '<hr>';
			case 'list': {
				const tag = d.style === 'ordered' ? 'ol' : 'ul';
				const lis = (d.items || []).map(i => `<li>${i}</li>`).join('');
				return `<${tag}>${lis}</${tag}>`;
			}
			case 'table': {
				const trs = (d.rows || []).map(row =>
					'<tr>' + row.map(cell => `<td>${cell}</td>`).join('') + '</tr>'
				).join('');
				return `<table>${trs}</table>`;
			}
			case 'image': {
				const w = d.width && d.width !== '100%' ? ` style="width:${d.width}"` : '';
				const alt = d.alt ? ` alt="${_escAttr(d.alt)}"` : '';
				const cap = d.caption ? `<figcaption>${d.caption}</figcaption>` : '';
				return `<figure><img src="${_escAttr(d.src)}"${alt}${w}>${cap}</figure>`;
			}
			case 'checklist': {
				const lis = (d.items || []).map(i =>
					`<li class="${i.checked ? 'ap-checked' : ''}">${i.text}</li>`
				).join('');
				return `<ul class="ap-checklist">${lis}</ul>`;
			}
			default: return `<p>${d.text || ''}</p>`;
		}
	}).join('\n');
}

function _escHtml(s) { return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function _escAttr(s) { return s.replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

/* ══════════════════════════════════════════════
   ブロック描画
   ══════════════════════════════════════════════ */

function _renderBlock(block) {
	const el = document.createElement('div');
	el.className = 'ap-wy-block';
	el.dataset.type = block.type;
	el.dataset.id = block.id;
	if (block.data.align) el.dataset.align = block.data.align;

	/* ── ハンドル ── */
	const handle = document.createElement('div');
	handle.className = 'ap-wy-block-handle';
	handle.textContent = '⠿';
	handle.title = 'ドラッグで移動 / クリックでタイプ変換';
	handle.setAttribute('aria-label', 'ブロック操作');
	handle.addEventListener('mousedown', e => {
		e.preventDefault();
		_startDrag(block, el, e);
	});
	handle.addEventListener('touchstart', e => {
		if (e.touches.length !== 1) return;
		e.preventDefault();
		_startDrag(block, el, e.touches[0], true);
	}, { passive: false });
	handle.addEventListener('click', e => {
		if (_dragStarted) return;
		e.stopPropagation();
		_showTypePopup(block, el);
	});
	el.appendChild(handle);

	/* ── コンテンツ ── */
	switch (block.type) {
		case 'paragraph':
		case 'heading':
		case 'quote':
			_renderTextBlock(el, block);
			break;
		case 'code':
			_renderCodeBlock(el, block);
			break;
		case 'delimiter':
			_renderDelimiter(el, block);
			break;
		case 'list':
			_renderListBlock(el, block);
			break;
		case 'table':
			_renderTableBlock(el, block);
			break;
		case 'image':
			_renderImageBlock(el, block);
			break;
		case 'checklist':
			_renderChecklistBlock(el, block);
			break;
	}

	if (block.type === 'heading') el.dataset.level = block.data.level || 2;

	return el;
}

/* ── テキストブロック（paragraph / heading / quote） ── */
function _renderTextBlock(el, block) {
	const content = document.createElement('div');
	content.className = 'ap-wy-block-content';
	content.contentEditable = 'true';
	content.setAttribute('role', 'textbox');
	content.setAttribute('aria-label', `${block.type} ブロック`);
	content.innerHTML = block.data.text || '<br>';
	_attachBlockKeyHandler(content, block);
	_attachBlockInput(content, block);
	el.appendChild(content);
}

/* ── コードブロック ── */
function _renderCodeBlock(el, block) {
	const content = document.createElement('div');
	content.className = 'ap-wy-block-content';
	content.contentEditable = 'true';
	content.setAttribute('role', 'textbox');
	content.setAttribute('aria-label', 'コードブロック');
	content.textContent = block.data.text || '';
	/* コードブロック内ではインライン書式無効化 */
	content.addEventListener('keydown', e => {
		const mod = e.ctrlKey || e.metaKey;
		if (mod && ['b','i','u'].includes(e.key.toLowerCase())) e.preventDefault();
	});
	content.addEventListener('paste', e => {
		e.preventDefault();
		const text = e.clipboardData.getData('text/plain');
		document.execCommand('insertText', false, text);
	});
	_attachBlockKeyHandler(content, block);
	content.addEventListener('input', () => { block.data.text = content.textContent; });
	el.appendChild(content);
}

/* ── 区切り線 ── */
function _renderDelimiter(el, block) {
	const content = document.createElement('div');
	content.className = 'ap-wy-block-content';
	content.innerHTML = '<hr>';
	content.style.cursor = 'pointer';
	content.addEventListener('click', () => {
		/* 次のブロックにフォーカス、なければ新規段落作成 */
		const idx = _blocks.indexOf(block);
		if (idx < _blocks.length - 1) {
			_focusBlock(_blocks[idx + 1]);
		} else {
			_addBlockAfter(block, 'paragraph', { text: '' });
		}
	});
	el.appendChild(content);
}

/* ── リストブロック ── */
function _renderListBlock(el, block) {
	const content = document.createElement('div');
	content.className = 'ap-wy-block-content';

	const listTag = block.data.style === 'ordered' ? 'ol' : 'ul';
	const list = document.createElement(listTag);
	list.contentEditable = 'true';
	list.setAttribute('role', 'textbox');
	list.setAttribute('aria-label', 'リストブロック');

	(block.data.items || ['']).forEach(item => {
		const li = document.createElement('li');
		li.innerHTML = item || '<br>';
		list.appendChild(li);
	});

	/* リスト特殊キー処理 */
	list.addEventListener('keydown', e => {
		if (e.key === 'Enter') {
			const sel = window.getSelection();
			const li = sel.anchorNode?.closest?.('li') || sel.anchorNode?.parentElement?.closest?.('li');
			if (li && li.textContent.trim() === '') {
				/* 空LIでEnter → リスト脱出、新段落作成 */
				e.preventDefault();
				li.remove();
				_syncListData(list, block);
				_addBlockAfter(block, 'paragraph', { text: '' });
				return;
			}
		}
		if (e.key === 'Backspace') {
			if (list.querySelectorAll('li').length <= 1 && list.textContent.trim() === '') {
				e.preventDefault();
				_changeBlockType(block, 'paragraph');
				return;
			}
		}
	});

	list.addEventListener('input', () => { _syncListData(list, block); });

	content.appendChild(list);
	_attachBlockKeyHandler(content, block, list);
	el.appendChild(content);
}

function _syncListData(list, block) {
	block.data.items = Array.from(list.querySelectorAll('li')).map(li => li.innerHTML.trim());
}

/* ── テーブルブロック ── */
function _renderTableBlock(el, block) {
	const content = document.createElement('div');
	content.className = 'ap-wy-block-content ap-wy-table-wrap';

	const table = document.createElement('table');
	table.className = 'ap-wy-table';

	const rows = block.data.rows || [['','',''],['','',''],['','','']];
	block.data.rows = rows;

	const _buildTable = () => {
		table.innerHTML = '';
		rows.forEach((row, ri) => {
			const tr = document.createElement('tr');
			row.forEach((cell, ci) => {
				const td = document.createElement('td');
				td.contentEditable = 'true';
				td.innerHTML = cell || '<br>';
				td.addEventListener('input', () => { rows[ri][ci] = td.innerHTML.trim(); });
				td.addEventListener('keydown', e => {
					if (e.key === 'Tab') {
						e.preventDefault();
						const tds = Array.from(table.querySelectorAll('td'));
						const idx = tds.indexOf(td);
						if (e.shiftKey) {
							if (idx > 0) { tds[idx - 1].focus(); _setCursorToEnd(tds[idx - 1]); }
							else {
								/* 最初のセルで Shift+Tab → 前のブロックへ */
								const bIdx = _blocks.indexOf(block);
								if (bIdx > 0) _focusBlock(_blocks[bIdx - 1], 'end');
							}
						} else {
							if (idx < tds.length - 1) { tds[idx + 1].focus(); _setCursorToEnd(tds[idx + 1]); }
							else {
								/* 最後のセルで Tab → 新しい行を追加 */
								rows.push(new Array(rows[0]?.length || 3).fill(''));
								_buildTable();
								const newTds = Array.from(table.querySelectorAll('td'));
								const first = newTds[newTds.length - (rows[0]?.length || 3)];
								if (first) { first.focus(); _setCursorToEnd(first); }
							}
						}
					}
					e.stopPropagation(); /* ブロック間キー処理を防止 */
				});
				tr.appendChild(td);
			});
			table.appendChild(tr);
		});
	};
	_buildTable();

	/* テーブル操作ボタン */
	const actions = document.createElement('div');
	actions.className = 'ap-wy-tbl-actions';
	const _tbtn = (label, fn) => {
		const b = document.createElement('button');
		b.type = 'button'; b.className = 'ap-wy-tbl-btn'; b.textContent = label;
		b.addEventListener('click', e => { e.preventDefault(); fn(); });
		actions.appendChild(b);
	};
	_tbtn('+ 行', () => { rows.push(new Array(rows[0]?.length || 3).fill('')); _buildTable(); });
	_tbtn('+ 列', () => { rows.forEach(r => r.push('')); _buildTable(); });
	_tbtn('- 行', () => { if (rows.length > 1) { rows.pop(); _buildTable(); } });
	_tbtn('- 列', () => { if ((rows[0]?.length || 0) > 1) { rows.forEach(r => r.pop()); _buildTable(); } });

	content.appendChild(table);
	content.appendChild(actions);
	el.appendChild(content);
}

/* ── 画像ブロック ── */
function _renderImageBlock(el, block) {
	const content = document.createElement('div');
	content.className = 'ap-wy-block-content';

	if (block.data.src) {
		const img = document.createElement('img');
		img.src = block.data.src;
		img.alt = block.data.alt || '';
		if (block.data.width && block.data.width !== '100%') img.style.width = block.data.width;
		content.appendChild(img);

		/* サイズプリセット */
		const actions = document.createElement('div');
		actions.className = 'ap-wy-img-actions';
		['25%','50%','75%','100%'].forEach(w => {
			const btn = document.createElement('button');
			btn.type = 'button';
			btn.className = 'ap-wy-img-size-btn' + (block.data.width === w ? ' active' : '');
			btn.textContent = w;
			btn.addEventListener('click', e => {
				e.preventDefault();
				block.data.width = w;
				img.style.width = w === '100%' ? '' : w;
				actions.querySelectorAll('.ap-wy-img-size-btn').forEach(b => b.classList.remove('active'));
				btn.classList.add('active');
			});
			actions.appendChild(btn);
		});
		content.appendChild(actions);

		/* Alt テキスト入力 */
		const altLabel = document.createElement('div');
		altLabel.style.cssText = 'font-size:11px;color:#888;padding:2px 0 0;';
		altLabel.textContent = 'Alt: ';
		const altInput = document.createElement('input');
		altInput.type = 'text';
		altInput.value = block.data.alt || '';
		altInput.placeholder = '代替テキスト';
		altInput.style.cssText = 'width:200px;padding:1px 4px;font-size:11px;background:#333;color:#eee;border:1px solid #555;border-radius:3px;';
		altInput.addEventListener('input', () => { block.data.alt = altInput.value; img.alt = altInput.value; });
		altInput.addEventListener('keydown', e => { e.stopPropagation(); }); /* Ph2-2 */
		altLabel.appendChild(altInput);
		content.appendChild(altLabel);

		/* キャプション */
		const caption = document.createElement('div');
		caption.className = 'ap-wy-figcaption';
		caption.contentEditable = 'true';
		caption.setAttribute('role', 'textbox');
		caption.setAttribute('aria-label', 'キャプション');
		caption.innerHTML = block.data.caption || '';
		caption.addEventListener('input', () => { block.data.caption = caption.innerHTML.trim(); });
		caption.addEventListener('keydown', e => {
			if (e.key === 'Enter') { e.preventDefault(); }
			e.stopPropagation();
		});
		content.appendChild(caption);
	} else {
		content.textContent = '画像を読み込み中...';
	}

	el.appendChild(content);
}

/* ── チェックリストブロック ── */
function _renderChecklistBlock(el, block) {
	const content = document.createElement('div');
	content.className = 'ap-wy-block-content';

	const items = block.data.items || [{ text: '', checked: false }];
	block.data.items = items;

	const _buildChecklist = () => {
		content.innerHTML = '';
		items.forEach((item, idx) => {
			const row = document.createElement('div');
			row.className = 'ap-wy-check-item' + (item.checked ? ' checked' : '');

			const cb = document.createElement('input');
			cb.type = 'checkbox';
			cb.className = 'ap-wy-check-box';
			cb.checked = item.checked;
			cb.addEventListener('change', () => {
				item.checked = cb.checked;
				row.classList.toggle('checked', cb.checked);
			});

			const text = document.createElement('div');
			text.className = 'ap-wy-check-text';
			text.contentEditable = 'true';
			text.innerHTML = item.text || '<br>';
			text.addEventListener('input', () => { item.text = text.innerHTML.trim(); });
			text.addEventListener('keydown', e => {
				if (e.key === 'Enter') {
					e.preventDefault();
					items.splice(idx + 1, 0, { text: '', checked: false });
					_buildChecklist();
					/* 新しい項目にフォーカス */
					const newText = content.querySelectorAll('.ap-wy-check-text')[idx + 1];
					if (newText) newText.focus();
				}
				if (e.key === 'Backspace' && text.textContent.trim() === '') {
					e.preventDefault();
					if (idx === 0 && items.length === 1) {
						/* 唯一の空項目 → 段落に変換 */
						_changeBlockType(block, 'paragraph');
					} else if (idx === 0) {
						/* 最初の項目を削除、次の項目にフォーカス */
						items.splice(0, 1);
						_buildChecklist();
						const first = content.querySelectorAll('.ap-wy-check-text')[0];
						if (first) { first.focus(); _setCursorToStart(first); }
					} else {
						items.splice(idx, 1);
						_buildChecklist();
						const prev = content.querySelectorAll('.ap-wy-check-text')[idx - 1];
						if (prev) { prev.focus(); _setCursorToEnd(prev); }
					}
				}
				e.stopPropagation();
			});

			row.appendChild(cb);
			row.appendChild(text);
			content.appendChild(row);
		});
	};
	_buildChecklist();
	el.appendChild(content);
}

/* ══════════════════════════════════════════════
   ブロック操作 & キーハンドラ
   ══════════════════════════════════════════════ */

function _attachBlockInput(content, block) {
	content.addEventListener('input', () => {
		block.data.text = content.innerHTML.trim();
		if (block.data.text === '<br>') block.data.text = '';
	});
}

function _attachBlockKeyHandler(contentWrap, block, editableEl) {
	const target = editableEl || contentWrap;
	target.addEventListener('keydown', e => {
		/* スラッシュコマンド */
		if (_slashMenu) {
			if (e.key === 'ArrowDown') { e.preventDefault(); _slashNav(1); return; }
			if (e.key === 'ArrowUp')   { e.preventDefault(); _slashNav(-1); return; }
			if (e.key === 'Enter')     { e.preventDefault(); _applySlashCmd(); return; }
			if (e.key === 'Escape')    { e.preventDefault(); _hideSlashMenu(); return; }
		}

		/* Enter: ブロック分割（リストは除外） */
		if (e.key === 'Enter' && !e.shiftKey && block.type !== 'list' && block.type !== 'code'
			&& block.type !== 'table' && block.type !== 'checklist') {
			e.preventDefault();
			/* カーソル以降のコンテンツを抽出 */
			const sel = window.getSelection();
			if (!sel.rangeCount) return;
			const range = sel.getRangeAt(0);
			range.deleteContents();

			const afterRange = document.createRange();
			afterRange.setStartBefore(range.startContainer.nodeType === 3 ? range.startContainer : range.startContainer.childNodes[range.startOffset] || range.startContainer);
			afterRange.setStart(range.startContainer, range.startOffset);
			afterRange.setEndAfter(target.lastChild || target);
			const frag = afterRange.extractContents();
			const tmp = document.createElement('div');
			tmp.appendChild(frag);
			const afterHtml = tmp.innerHTML.trim();

			/* 現在のブロックデータ更新 */
			block.data.text = target.innerHTML.trim();
			if (block.data.text === '<br>') block.data.text = '';

			/* 新ブロック作成 */
			_addBlockAfter(block, 'paragraph', { text: afterHtml || '' });
			return;
		}

		/* Shift+Enter: <br>挿入（code以外） */
		if (e.key === 'Enter' && e.shiftKey && block.type === 'code') {
			/* codeブロックでは通常Enter = 改行 */
		}
		if (e.key === 'Enter' && !e.shiftKey && block.type === 'code') {
			/* codeブロック内ではEnter = 改行（分割しない） */
			e.preventDefault();
			document.execCommand('insertText', false, '\n');
			block.data.text = target.textContent;
			return;
		}

		/* Backspace: ブロック結合 */
		if (e.key === 'Backspace' && _isCursorAtStart(target)) {
			const idx = _blocks.indexOf(block);
			if (idx > 0) {
				e.preventDefault();
				const prev = _blocks[idx - 1];
				if (['paragraph','heading','quote'].includes(prev.type) && ['paragraph','heading','quote'].includes(block.type)) {
					/* テキスト系ブロック同士を結合 */
					const prevEl = _getBlockEl(prev);
					const prevContent = prevEl?.querySelector('.ap-wy-block-content');
					if (prevContent) {
						const prevLen = (prev.data.text || '').length;
						prevContent.innerHTML = (prev.data.text || '') + (block.data.text || '');
						prev.data.text = prevContent.innerHTML;

						/* ブロック削除 */
						_removeBlock(block);

						/* カーソルを結合点に配置 */
						prevContent.focus();
						_setCursorAtOffset(prevContent, prevLen);
					}
				} else {
					/* 前のブロックにフォーカス移動のみ */
					_focusBlock(prev, 'end');
				}
			}
			return;
		}

		/* Delete: 次のブロックと結合 */
		if (e.key === 'Delete' && _isCursorAtEnd(target)) {
			const idx = _blocks.indexOf(block);
			if (idx < _blocks.length - 1) {
				e.preventDefault();
				const next = _blocks[idx + 1];
				if (['paragraph','heading','quote'].includes(block.type) && ['paragraph','heading','quote'].includes(next.type)) {
					block.data.text = (block.data.text || '') + (next.data.text || '');
					target.innerHTML = block.data.text || '<br>';
					_removeBlock(next);
				}
			}
			return;
		}

		/* ArrowUp: 前のブロックへ移動 */
		if (e.key === 'ArrowUp' && _isCursorAtStart(target)) {
			const idx = _blocks.indexOf(block);
			if (idx > 0) { e.preventDefault(); _focusBlock(_blocks[idx - 1], 'end'); }
			return;
		}

		/* ArrowDown: 次のブロックへ移動 */
		if (e.key === 'ArrowDown' && _isCursorAtEnd(target)) {
			const idx = _blocks.indexOf(block);
			if (idx < _blocks.length - 1) { e.preventDefault(); _focusBlock(_blocks[idx + 1], 'start'); }
			return;
		}

		/* "/" スラッシュコマンド検出 */
		if (e.key === '/' && block.type !== 'code') {
			const text = target.textContent.trim();
			if (text === '' || text === '/') {
				/* _showSlashMenu は input イベントで処理 */
			}
		}
	});

	/* input で "/" 検出 */
	if (block.type !== 'code' && block.type !== 'table') {
		target.addEventListener('input', () => {
			const text = target.textContent;
			if (text.startsWith('/')) {
				_slashFilter = text.slice(1).toLowerCase();
				_slashBlock = block;
				_showSlashMenu(target);
			} else if (_slashMenu) {
				_hideSlashMenu();
			}
		});
	}
}

/* ── ブロック追加・削除・フォーカス ── */

function _addBlockAfter(afterBlock, type, data) {
	_saveSnapshot();
	const idx = _blocks.indexOf(afterBlock);
	const newBlock = { id: _uid(), type, data };
	_blocks.splice(idx + 1, 0, newBlock);
	const newEl = _renderBlock(newBlock);
	const afterEl = _getBlockEl(afterBlock);
	if (afterEl && afterEl.nextSibling) {
		_blocksEl.insertBefore(newEl, afterEl.nextSibling);
	} else {
		_blocksEl.appendChild(newEl);
	}
	_focusBlock(newBlock, 'start');
	return newBlock;
}

function _removeBlock(block) {
	_saveSnapshot();
	const idx = _blocks.indexOf(block);
	if (idx === -1) return;
	_blocks.splice(idx, 1);
	const el = _getBlockEl(block);
	if (el) el.remove();
	/* 全ブロック削除されたら空段落を追加 */
	if (_blocks.length === 0) {
		const empty = { id: _uid(), type: 'paragraph', data: { text: '' } };
		_blocks.push(empty);
		_blocksEl.appendChild(_renderBlock(empty));
		_focusBlock(empty, 'start');
	}
}

function _getBlockEl(block) {
	return _blocksEl?.querySelector(`[data-id="${block.id}"]`);
}

function _focusBlock(block, pos) {
	const el = _getBlockEl(block);
	if (!el) return;
	const content = el.querySelector('.ap-wy-block-content[contenteditable="true"]')
		|| el.querySelector('[contenteditable="true"]');
	if (content) {
		content.focus();
		if (pos === 'start') _setCursorToStart(content);
		else if (pos === 'end') _setCursorToEnd(content);
	}
}

/* ── カーソルユーティリティ ── */

function _isCursorAtStart(el) {
	const sel = window.getSelection();
	if (!sel.rangeCount) return false;
	const range = sel.getRangeAt(0);
	if (!range.collapsed) return false;
	const pre = document.createRange();
	pre.selectNodeContents(el);
	pre.setEnd(range.startContainer, range.startOffset);
	return pre.toString().length === 0;
}

function _isCursorAtEnd(el) {
	const sel = window.getSelection();
	if (!sel.rangeCount) return false;
	const range = sel.getRangeAt(0);
	if (!range.collapsed) return false;
	const post = document.createRange();
	post.selectNodeContents(el);
	post.setStart(range.endContainer, range.endOffset);
	return post.toString().length === 0;
}

function _setCursorToStart(el) {
	const range = document.createRange();
	const sel = window.getSelection();
	range.selectNodeContents(el);
	range.collapse(true);
	sel.removeAllRanges();
	sel.addRange(range);
}

function _setCursorToEnd(el) {
	const range = document.createRange();
	const sel = window.getSelection();
	range.selectNodeContents(el);
	range.collapse(false);
	sel.removeAllRanges();
	sel.addRange(range);
}

function _setCursorAtOffset(el, htmlOffset) {
	/* HTML文字列のオフセット位置にカーソルを配置するため、
	   一時的にマーカーを挿入して位置を特定する */
	const html = el.innerHTML;
	const marker = '\u200B\u200B\u200B';
	el.innerHTML = html.slice(0, htmlOffset) + marker + html.slice(htmlOffset);
	const walker = document.createTreeWalker(el, NodeFilter.SHOW_TEXT);
	while (walker.nextNode()) {
		const idx = walker.currentNode.textContent.indexOf(marker);
		if (idx !== -1) {
			walker.currentNode.textContent = walker.currentNode.textContent.replace(marker, '');
			const range = document.createRange();
			range.setStart(walker.currentNode, idx);
			range.collapse(true);
			const sel = window.getSelection();
			sel.removeAllRanges();
			sel.addRange(range);
			return;
		}
	}
	_setCursorToEnd(el);
}

/* ── Undo / Redo ── */

function _saveSnapshot() {
	_syncAllBlocks();
	const snap = JSON.stringify(_blocks);
	if (_undoStack.length > 0 && _undoStack[_undoStack.length - 1] === snap) return;
	_undoStack.push(snap);
	if (_undoStack.length > _UNDO_LIMIT) _undoStack.shift();
	_redoStack.length = 0;
}

function _undo() {
	if (_undoStack.length === 0) return;
	_syncAllBlocks();
	_redoStack.push(JSON.stringify(_blocks));
	const snap = _undoStack.pop();
	_restoreSnapshot(snap);
	_setStatus('↩ 元に戻しました');
	setTimeout(() => _setStatus(''), 2000);
}

function _redo() {
	if (_redoStack.length === 0) return;
	_syncAllBlocks();
	_undoStack.push(JSON.stringify(_blocks));
	const snap = _redoStack.pop();
	_restoreSnapshot(snap);
	_setStatus('↪ やり直しました');
	setTimeout(() => _setStatus(''), 2000);
}

function _restoreSnapshot(snap) {
	_blocks = JSON.parse(snap);
	_blocksEl.innerHTML = '';
	_blocks.forEach(b => _blocksEl.appendChild(_renderBlock(b)));
	if (_blocks.length > 0) _focusBlock(_blocks[0], 'start');
}

/* ══════════════════════════════════════════════
   ツールバーコマンド実行
   ══════════════════════════════════════════════ */

function _exec(cmd, span, originalHtml) {
	switch (cmd) {
		case 'bold':
		case 'italic':
		case 'underline':
		case 'removeFormat':
			document.execCommand(cmd, false, null);
			break;
		case 'strike':
			_toggleInline('strikeThrough');
			break;
		case 'inlineCode':
			_toggleInlineCode();
			break;
		case 'marker':
			_toggleMarker();
			break;
		case 'insertUnorderedList':
		case 'insertOrderedList': {
			const focused = _getFocusedBlock();
			if (focused && ['paragraph','heading','quote'].includes(focused.type)) {
				const style = cmd === 'insertOrderedList' ? 'ordered' : 'unordered';
				const text = focused.data.text || '';
				focused.type = 'list';
				focused.data = { style, items: [text] };
				_rerenderBlock(focused);
			}
			break;
		}
		case 'h2':
		case 'h3':
		case 'p':
		case 'quote':
		case 'codeBlock':
		case 'delimiter': {
			const focused = _getFocusedBlock();
			if (focused) {
				const typeMap = { h2:'heading', h3:'heading', p:'paragraph', quote:'quote', codeBlock:'code', delimiter:'delimiter' };
				const newType = typeMap[cmd] || 'paragraph';
				const extra = {};
				if (cmd === 'h2') extra.level = 2;
				if (cmd === 'h3') extra.level = 3;
				_changeBlockType(focused, newType, extra);
			}
			break;
		}
		case 'link':
			_showLinkInput();
			break;
		case 'img': {
			const input = document.createElement('input');
			input.type = 'file';
			input.accept = 'image/jpeg,image/png,image/gif,image/webp';
			input.onchange = () => { if (input.files?.[0]) _insertImageBlock(input.files[0]); };
			input.click();
			break;
		}
		case 'table': {
			const focused = _getFocusedBlock();
			if (focused) {
				_addBlockAfter(focused, 'table', { rows: [['','',''],['','',''],['','','']] });
			}
			break;
		}
		case 'save':
			_manualSave(span);
			break;
		case 'cancel':
			_cancel(span, originalHtml);
			break;
	}
}

function _getFocusedBlock() {
	const active = document.activeElement;
	if (!active || !_blocksEl?.contains(active)) return _blocks[_blocks.length - 1] || null;
	const blockEl = active.closest('.ap-wy-block');
	if (!blockEl) return null;
	return _blocks.find(b => b.id === blockEl.dataset.id) || null;
}

function _changeBlockType(block, newType, extra = {}) {
	_saveSnapshot();
	const oldEl = _getBlockEl(block);
	if (!oldEl) return;

	/* テキスト内容を保持 */
	let text = block.data.text || '';
	if (block.type === 'list') text = (block.data.items || []).join('<br>');
	if (block.type === 'code') text = _escHtml(block.data.text || '');

	if (newType === 'delimiter') {
		block.type = 'delimiter';
		block.data = {};
	} else if (newType === 'list') {
		block.type = 'list';
		const rawText = newType === 'list' ? text.replace(/<br\s*\/?>/gi, '\n') : text;
		const items = rawText.split('\n').filter(s => s.trim());
		block.data = { style: extra.style || 'unordered', items: items.length ? items : [''] };
	} else if (newType === 'code') {
		const tmp = document.createElement('div');
		tmp.innerHTML = text;
		block.type = 'code';
		block.data = { text: tmp.textContent };
	} else {
		block.type = newType;
		block.data = { text, ...extra };
	}

	_rerenderBlock(block);
}

function _rerenderBlock(block) {
	const oldEl = _getBlockEl(block);
	if (!oldEl) return;
	const newEl = _renderBlock(block);
	oldEl.replaceWith(newEl);
	_focusBlock(block, 'end');
}

/* ══════════════════════════════════════════════
   インラインツール（S / Code / Marker / Link）
   ══════════════════════════════════════════════ */

function _toggleInline(cmd) {
	document.execCommand(cmd, false, null);
}

function _toggleInlineCode() {
	const sel = window.getSelection();
	if (!sel.rangeCount || sel.isCollapsed) return;
	const range = sel.getRangeAt(0);

	/* 既に<code>内にいるかチェック */
	let codeParent = range.commonAncestorContainer;
	while (codeParent && codeParent !== _blocksEl) {
		if (codeParent.nodeType === 1 && codeParent.tagName === 'CODE') {
			/* 解除: <code>の中身をテキストノードに置換 */
			const text = document.createTextNode(codeParent.textContent);
			codeParent.parentNode.replaceChild(text, codeParent);
			return;
		}
		codeParent = codeParent.parentNode;
	}

	/* 新規適用 */
	const code = document.createElement('code');
	try {
		code.appendChild(range.extractContents());
		range.insertNode(code);
		sel.removeAllRanges();
		const newRange = document.createRange();
		newRange.selectNodeContents(code);
		sel.addRange(newRange);
	} catch (e) { console.warn('[AP WYSIWYG] inlineCode:', e.message); }
}

function _toggleMarker() {
	const sel = window.getSelection();
	if (!sel.rangeCount || sel.isCollapsed) return;
	const range = sel.getRangeAt(0);

	let markParent = range.commonAncestorContainer;
	while (markParent && markParent !== _blocksEl) {
		if (markParent.nodeType === 1 && markParent.tagName === 'MARK') {
			const text = document.createTextNode(markParent.textContent);
			markParent.parentNode.replaceChild(text, markParent);
			return;
		}
		markParent = markParent.parentNode;
	}

	const mark = document.createElement('mark');
	try {
		mark.appendChild(range.extractContents());
		range.insertNode(mark);
		sel.removeAllRanges();
		const newRange = document.createRange();
		newRange.selectNodeContents(mark);
		sel.addRange(newRange);
	} catch (e) { console.warn('[AP WYSIWYG] marker:', e.message); }
}

function _showLinkInput() {
	const sel = window.getSelection();
	if (!sel.rangeCount || sel.isCollapsed) {
		const url = prompt('URL を入力してください:');
		if (url?.trim()) {
			if (!_isSafeUrl(url)) { _setStatus('⚠ 安全でないURLです'); return; }
			document.execCommand('createLink', false, url.trim());
		}
		return;
	}

	/* 既存のリンクをチェック */
	let anchor = sel.anchorNode;
	while (anchor && anchor.tagName !== 'A' && anchor !== _blocksEl) anchor = anchor.parentNode;

	if (anchor?.tagName === 'A') {
		/* リンク解除 */
		document.execCommand('unlink', false, null);
	} else {
		const url = prompt('URL を入力してください:');
		if (url?.trim()) {
			if (!_isSafeUrl(url)) { _setStatus('⚠ 安全でないURLです'); return; }
			document.execCommand('createLink', false, url.trim());
		}
	}
}

/* ══════════════════════════════════════════════
   フローティングインラインツールバー
   ══════════════════════════════════════════════ */

const INLINE_TB_TOOLS = [
	{ cmd:'bold',          label:'<b>B</b>',  aria:'太字' },
	{ cmd:'italic',        label:'<i>I</i>',  aria:'斜体' },
	{ cmd:'underline',     label:'<u>U</u>',  aria:'下線' },
	{ cmd:'strikeThrough', label:'<s>S</s>',  aria:'取消線' },
	{ cmd:'inlineCode',    label:'&lt;&gt;',   aria:'コード' },
	{ cmd:'marker',        label:'<mark>M</mark>', aria:'マーカー' },
	{ cmd:'link',          label:'🔗',         aria:'リンク' },
];

function _onSelectionChange() {
	if (!_active || !_blocksEl) return;
	const sel = window.getSelection();
	if (!sel.rangeCount || sel.isCollapsed) { _hideInlineToolbar(); return; }

	/* 選択がブロックコンテナ内かチェック */
	const range = sel.getRangeAt(0);
	if (!_blocksEl.contains(range.commonAncestorContainer)) { _hideInlineToolbar(); return; }

	/* コードブロック内では表示しない */
	const blockEl = range.commonAncestorContainer.nodeType === 1
		? range.commonAncestorContainer.closest('.ap-wy-block')
		: range.commonAncestorContainer.parentElement?.closest('.ap-wy-block');
	if (blockEl?.dataset.type === 'code') { _hideInlineToolbar(); return; }

	_showInlineToolbar(range);
}

function _showInlineToolbar(range) {
	if (!_inlineToolbar) {
		_inlineToolbar = document.createElement('div');
		_inlineToolbar.className = 'ap-wy-inline-tb';
		INLINE_TB_TOOLS.forEach(t => {
			const btn = document.createElement('button');
			btn.type = 'button';
			btn.className = 'ap-wy-btn';
			btn.dataset.cmd = t.cmd;
			btn.innerHTML = t.label;
			btn.setAttribute('aria-label', t.aria);
			btn.addEventListener('mousedown', e => {
				e.preventDefault();
				if (t.cmd === 'inlineCode') _toggleInlineCode();
				else if (t.cmd === 'marker') _toggleMarker();
				else if (t.cmd === 'link') _showLinkInput();
				else _toggleInline(t.cmd);
			});
			_inlineToolbar.appendChild(btn);
		});
		document.body.appendChild(_inlineToolbar);
	}

	/* アクティブ状態を更新 */
	_inlineToolbar.querySelectorAll('.ap-wy-btn').forEach(btn => {
		const cmd = btn.dataset.cmd;
		let active = false;
		try {
			if (cmd === 'bold') active = document.queryCommandState('bold');
			else if (cmd === 'italic') active = document.queryCommandState('italic');
			else if (cmd === 'underline') active = document.queryCommandState('underline');
			else if (cmd === 'strikeThrough') active = document.queryCommandState('strikeThrough');
		} catch (_e) { /* ignore */ }
		btn.classList.toggle('ap-wy-active', active);
	});

	const rect = range.getBoundingClientRect();
	_inlineToolbar.style.display = 'flex';
	let top = window.scrollY + rect.top - 40;
	let left = window.scrollX + rect.left + rect.width / 2;
	_inlineToolbar.style.top = top + 'px';
	_inlineToolbar.style.left = left + 'px';
	/* ビューポートクランプ */
	requestAnimationFrame(() => {
		if (!_inlineToolbar) return;
		const tbRect = _inlineToolbar.getBoundingClientRect();
		if (tbRect.top < 0) _inlineToolbar.style.top = (window.scrollY + rect.bottom + 4) + 'px';
		if (tbRect.right > window.innerWidth) _inlineToolbar.style.left = (window.innerWidth - tbRect.width - 8) + 'px';
		if (tbRect.left < 0) _inlineToolbar.style.left = '4px';
	});
}

function _hideInlineToolbar() {
	if (_inlineToolbar) _inlineToolbar.style.display = 'none';
}

/* ══════════════════════════════════════════════
   "/" スラッシュコマンドメニュー
   ══════════════════════════════════════════════ */

function _showSlashMenu(target) {
	const filtered = SLASH_COMMANDS.filter(c =>
		_slashFilter === '' ||
		c.label.toLowerCase().includes(_slashFilter) ||
		c.keywords.includes(_slashFilter) ||
		c.type.includes(_slashFilter)
	);
	if (filtered.length === 0) { _hideSlashMenu(); return; }

	if (!_slashMenu) {
		_slashMenu = document.createElement('div');
		_slashMenu.className = 'ap-wy-slash-menu';
		_slashMenu.setAttribute('role', 'listbox');
		_slashMenu.setAttribute('aria-label', 'ブロックタイプ選択');
		document.body.appendChild(_slashMenu);
	}

	_slashIdx = 0;
	_slashMenu.innerHTML = '';
	filtered.forEach((c, i) => {
		const item = document.createElement('div');
		item.className = 'ap-wy-slash-item' + (i === 0 ? ' active' : '');
		item.setAttribute('role', 'option');
		item.innerHTML = `<span class="ap-wy-slash-item-icon">${c.icon}</span><span class="ap-wy-slash-item-label">${c.label}</span>`;
		item.addEventListener('mousedown', e => {
			e.preventDefault();
			_slashIdx = i;
			_applySlashCmd();
		});
		_slashMenu.appendChild(item);
	});

	/* 位置計算 */
	const sel = window.getSelection();
	if (sel.rangeCount) {
		const rect = sel.getRangeAt(0).getBoundingClientRect();
		_slashMenu.style.display = 'block';
		let top = window.scrollY + rect.bottom + 4;
		let left = window.scrollX + rect.left;

		/* DOM追加後に実寸でビューポートクランプ (Ph3-F) */
		_slashMenu.style.top = top + 'px';
		_slashMenu.style.left = Math.max(4, left) + 'px';
		requestAnimationFrame(() => {
			if (!_slashMenu) return;
			const menuRect = _slashMenu.getBoundingClientRect();
			if (menuRect.bottom > window.innerHeight) top = window.scrollY + rect.top - menuRect.height - 4;
			if (menuRect.right > window.innerWidth) left = window.innerWidth - menuRect.width - 8;
			_slashMenu.style.top = top + 'px';
			_slashMenu.style.left = Math.max(4, left) + 'px';
		});
	}
}

function _hideSlashMenu() {
	if (_slashMenu) { _slashMenu.style.display = 'none'; }
	_slashFilter = '';
	_slashBlock = null;
}

function _slashNav(dir) {
	if (!_slashMenu) return;
	const items = _slashMenu.querySelectorAll('.ap-wy-slash-item');
	if (items.length === 0) return;
	items[_slashIdx]?.classList.remove('active');
	_slashIdx = (_slashIdx + dir + items.length) % items.length;
	items[_slashIdx]?.classList.add('active');
	items[_slashIdx]?.scrollIntoView({ block: 'nearest' });
}

function _applySlashCmd() {
	if (!_slashMenu || !_slashBlock) return;
	const items = _slashMenu.querySelectorAll('.ap-wy-slash-item');
	const filtered = SLASH_COMMANDS.filter(c =>
		_slashFilter === '' ||
		c.label.toLowerCase().includes(_slashFilter) ||
		c.keywords.includes(_slashFilter) ||
		c.type.includes(_slashFilter)
	);
	if (_slashIdx >= filtered.length) _slashIdx = Math.max(0, filtered.length - 1);
	const selected = filtered[_slashIdx];
	if (!selected) { _hideSlashMenu(); return; }

	const block = _slashBlock;
	_hideSlashMenu();

	/* 現在のブロックの "/" テキストをクリア */
	const el = _getBlockEl(block);
	const content = el?.querySelector('.ap-wy-block-content[contenteditable="true"]');
	if (content) content.innerHTML = '';
	block.data.text = '';

	switch (selected.type) {
		case 'paragraph':
			_changeBlockType(block, 'paragraph');
			break;
		case 'h2':
			_changeBlockType(block, 'heading', { level: 2 });
			break;
		case 'h3':
			_changeBlockType(block, 'heading', { level: 3 });
			break;
		case 'ul':
			block.type = 'list'; block.data = { style: 'unordered', items: [''] };
			_rerenderBlock(block);
			break;
		case 'ol':
			block.type = 'list'; block.data = { style: 'ordered', items: [''] };
			_rerenderBlock(block);
			break;
		case 'quote':
			_changeBlockType(block, 'quote');
			break;
		case 'code':
			_changeBlockType(block, 'code');
			break;
		case 'delimiter':
			_changeBlockType(block, 'delimiter');
			/* 区切り線の後に新段落を追加 (Ph3-F) */
			_addBlockAfter(block, 'paragraph', { text: '' });
			break;
		case 'table':
			block.type = 'table'; block.data = { rows: [['','',''],['','',''],['','','']] };
			_rerenderBlock(block);
			break;
		case 'image': {
			const input = document.createElement('input');
			input.type = 'file';
			input.accept = 'image/jpeg,image/png,image/gif,image/webp';
			input.onchange = () => {
				if (input.files?.[0]) {
					_removeBlock(block);
					_insertImageBlock(input.files[0]);
				}
			};
			input.click();
			break;
		}
		case 'checklist':
			block.type = 'checklist'; block.data = { items: [{ text: '', checked: false }] };
			_rerenderBlock(block);
			break;
	}
}

/* ══════════════════════════════════════════════
   ブロックハンドル・タイプ変換ポップアップ
   ══════════════════════════════════════════════ */

function _showTypePopup(block, blockEl) {
	_hideTypePopup();

	_typePopup = document.createElement('div');
	_typePopup.className = 'ap-wy-type-popup';

	const options = [
		{ label: '¶ 段落',         type: 'paragraph' },
		{ label: 'H2 見出し2',     type: 'heading', extra: { level: 2 } },
		{ label: 'H3 見出し3',     type: 'heading', extra: { level: 3 } },
		{ label: '❝ 引用',         type: 'quote' },
		{ label: '{} コード',       type: 'code' },
		{ label: '— 区切り線',     type: 'delimiter' },
		{ label: '•≡ 箇条書き',    type: 'list', extra: { style: 'unordered' } },
		{ label: '1≡ 番号リスト',  type: 'list', extra: { style: 'ordered' } },
	];

	/* Block Tunes: テキスト配置 */
	const alignOptions = [
		{ label: '← 左揃え',   align: 'left' },
		{ label: '↔ 中央揃え', align: 'center' },
		{ label: '→ 右揃え',   align: 'right' },
	];

	options.forEach(opt => {
		const item = document.createElement('div');
		item.className = 'ap-wy-type-popup-item';
		item.textContent = opt.label;
		if (block.type === opt.type && (!opt.extra?.level || block.data.level === opt.extra.level)
			&& (!opt.extra?.style || block.data.style === opt.extra.style)) {
			item.style.fontWeight = 'bold';
		}
		item.addEventListener('mousedown', e => {
			e.preventDefault();
			_hideTypePopup();
			if (opt.type === 'list') {
				const text = block.data.text || '';
				block.type = 'list';
				block.data = { style: opt.extra.style, items: text ? [text] : [''] };
				_rerenderBlock(block);
			} else {
				_changeBlockType(block, opt.type, opt.extra || {});
			}
		});
		_typePopup.appendChild(item);
	});

	/* 配置セパレータ */
	if (['paragraph','heading','quote'].includes(block.type)) {
		const sep = document.createElement('div');
		sep.className = 'ap-wy-type-popup-sep';
		_typePopup.appendChild(sep);

		alignOptions.forEach(opt => {
			const item = document.createElement('div');
			item.className = 'ap-wy-type-popup-item';
			item.textContent = opt.label;
			if ((block.data.align || 'left') === opt.align) item.style.fontWeight = 'bold';
			item.addEventListener('mousedown', e => {
				e.preventDefault();
				_hideTypePopup();
				block.data.align = opt.align;
				blockEl.dataset.align = opt.align;
			});
			_typePopup.appendChild(item);
		});
	}

	/* 削除ボタン */
	const sep2 = document.createElement('div');
	sep2.className = 'ap-wy-type-popup-sep';
	_typePopup.appendChild(sep2);
	const delItem = document.createElement('div');
	delItem.className = 'ap-wy-type-popup-item danger';
	delItem.textContent = '🗑 ブロック削除';
	delItem.addEventListener('mousedown', e => {
		e.preventDefault();
		_hideTypePopup();
		_removeBlock(block);
	});
	_typePopup.appendChild(delItem);

	document.body.appendChild(_typePopup);

	/* 位置計算 */
	const handleRect = blockEl.querySelector('.ap-wy-block-handle').getBoundingClientRect();
	let top = window.scrollY + handleRect.bottom + 2;
	let left = window.scrollX + handleRect.left;

	/* ビューポートクランプ (Ph3-G) */
	_typePopup.style.top = top + 'px';
	_typePopup.style.left = Math.max(4, left) + 'px';
	requestAnimationFrame(() => {
		if (!_typePopup) return;
		const popRect = _typePopup.getBoundingClientRect();
		if (popRect.bottom > window.innerHeight) top = window.scrollY + handleRect.top - popRect.height - 2;
		if (popRect.right > window.innerWidth) left = window.innerWidth - popRect.width - 8;
		_typePopup.style.top = top + 'px';
		_typePopup.style.left = Math.max(4, left) + 'px';
	});

	/* キーボード操作 */
	let _popIdx = -1;
	const allItems = _typePopup.querySelectorAll('.ap-wy-type-popup-item');
	const _keyHandler = e => {
		if (e.key === 'ArrowDown') {
			e.preventDefault();
			if (_popIdx >= 0) allItems[_popIdx].style.background = '';
			_popIdx = (_popIdx + 1) % allItems.length;
			allItems[_popIdx].style.background = '#0ad';
			allItems[_popIdx].style.color = '#000';
			allItems[_popIdx].scrollIntoView({ block: 'nearest' });
		} else if (e.key === 'ArrowUp') {
			e.preventDefault();
			if (_popIdx >= 0) { allItems[_popIdx].style.background = ''; allItems[_popIdx].style.color = ''; }
			_popIdx = (_popIdx - 1 + allItems.length) % allItems.length;
			allItems[_popIdx].style.background = '#0ad';
			allItems[_popIdx].style.color = '#000';
			allItems[_popIdx].scrollIntoView({ block: 'nearest' });
		} else if (e.key === 'Enter' && _popIdx >= 0) {
			e.preventDefault();
			document.removeEventListener('keydown', _keyHandler);
			allItems[_popIdx].dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
		} else if (e.key === 'Escape') {
			e.preventDefault();
			document.removeEventListener('keydown', _keyHandler);
			_hideTypePopup();
		}
	};
	setTimeout(() => document.addEventListener('keydown', _keyHandler), 0);

	/* 外部クリックで閉じる */
	const _closeHandler = e => {
		if (!_typePopup?.contains(e.target)) {
			_hideTypePopup();
			document.removeEventListener('mousedown', _closeHandler);
			document.removeEventListener('keydown', _keyHandler);
		}
	};
	setTimeout(() => document.addEventListener('mousedown', _closeHandler), 0);
}

function _hideTypePopup() {
	if (_typePopup) { _typePopup.remove(); _typePopup = null; }
}

/* ══════════════════════════════════════════════
   ドラッグ並べ替え
   ══════════════════════════════════════════════ */

function _startDrag(block, blockEl, startEvent, isTouch) {
	_dragStarted = false;
	_dragBlock = block;

	const startY = startEvent.clientY;
	let moved = false;

	if (!_dropLine) {
		_dropLine = document.createElement('div');
		_dropLine.className = 'ap-wy-drop-line';
		_dropLine.style.display = 'none';
	}

	const _getY = e => isTouch ? e.touches[0].clientY : e.clientY;

	const _onMove = e => {
		const y = isTouch ? (e.touches?.[0]?.clientY ?? startY) : e.clientY;
		if (!moved && Math.abs(y - startY) < 5) return;

		if (!moved) {
			moved = true;
			_dragStarted = true;
			blockEl.classList.add('dragging');
			_blocksEl.appendChild(_dropLine);
		}

		/* ドロップ位置計算 */
		const blockEls = Array.from(_blocksEl.querySelectorAll('.ap-wy-block'));
		let insertBefore = null;
		let lineTop = 0;

		for (const el of blockEls) {
			if (el === blockEl) continue;
			const rect = el.getBoundingClientRect();
			const mid = rect.top + rect.height / 2;
			if (y < mid) {
				insertBefore = el;
				lineTop = rect.top - _blocksEl.getBoundingClientRect().top - 2;
				break;
			}
			lineTop = rect.bottom - _blocksEl.getBoundingClientRect().top;
		}

		_dropLine.style.display = 'block';
		_dropLine.style.top = lineTop + 'px';
		_dropLine.dataset.beforeId = insertBefore?.dataset.id || '';
	};

	const moveEvt = isTouch ? 'touchmove' : 'mousemove';
	const upEvt = isTouch ? 'touchend' : 'mouseup';

	const _onUp = () => {
		document.removeEventListener(moveEvt, _onMove);
		document.removeEventListener(upEvt, _onUp);

		if (moved) {
			_saveSnapshot();
			blockEl.classList.remove('dragging');
			_dropLine.style.display = 'none';

			const beforeId = _dropLine.dataset.beforeId;
			const fromIdx = _blocks.indexOf(block);
			_blocks.splice(fromIdx, 1);

			let toIdx = _blocks.length;
			if (beforeId) {
				toIdx = _blocks.findIndex(b => b.id === beforeId);
				if (toIdx === -1) toIdx = _blocks.length;
			}
			_blocks.splice(toIdx, 0, block);

			/* DOM 移動 */
			if (beforeId) {
				const beforeEl = _blocksEl.querySelector(`[data-id="${beforeId}"]`);
				if (beforeEl) _blocksEl.insertBefore(blockEl, beforeEl);
			} else {
				_blocksEl.appendChild(blockEl);
			}
		}

		/* ドラッグフラグリセット（clickイベント防止のため遅延） */
		setTimeout(() => { _dragStarted = false; _dragBlock = null; }, 50);
	};

	document.addEventListener(moveEvt, _onMove, isTouch ? { passive: false } : undefined);
	document.addEventListener(upEvt, _onUp);
}

/* ══════════════════════════════════════════════
   画像アップロード & 画像ブロック挿入
   ══════════════════════════════════════════════ */

function _insertImageBlock(file) {
	if (!file || !file.type.match(/^image\//)) {
		_setStatus('⚠ 画像ファイルのみ対応 (JPEG/PNG/GIF/WebP)');
		setTimeout(() => _setStatus(''), 5000);
		return;
	}
	const csrf = _getCsrf();
	if (!csrf) { console.error('[AP WYSIWYG] CSRF token not found'); return; }

	_setStatus('アップロード中...');

	const fd = new FormData();
	fd.append('ap_action', 'upload_image');
	fd.append('image', file);
	fd.append('csrf', csrf);

	fetch('index.php', {
		method: 'POST',
		headers: { 'X-CSRF-TOKEN': csrf },
		body: fd,
	}).then(r => {
		if (!r.ok) throw new Error('HTTP ' + r.status);
		return r.json();
	}).then(data => {
		if (data.error) throw new Error(data.error);
		_setStatus('');
		const focused = _getFocusedBlock() || _blocks[_blocks.length - 1];
		if (focused) {
			_addBlockAfter(focused, 'image', { src: data.url, alt: '', caption: '', width: '100%' });
		} else {
			const b = { id: _uid(), type: 'image', data: { src: data.url, alt: '', caption: '', width: '100%' } };
			_blocks.push(b);
			_blocksEl.appendChild(_renderBlock(b));
		}
	}).catch(e => {
		_setStatus('⚠ アップロード失敗: ' + e.message);
		setTimeout(() => _setStatus(''), 5000);
	});
}

/* ══════════════════════════════════════════════
   保存 / 自動保存 / キャンセル
   ══════════════════════════════════════════════ */

function _manualSave(span) {
	if (!_active) return;
	_stopAutoSave();
	_active = false;
	_hideSlashMenu();
	_hideTypePopup();
	_hideInlineToolbar();
	document.removeEventListener('selectionchange', _onSelectionChange);
	if (_inlineToolbar) { _inlineToolbar.remove(); _inlineToolbar = null; }
	if (typeof _apChanging !== 'undefined') _apChanging = false;

	/* ブロックからHTML生成 */
	_syncAllBlocks();
	const html = _cleanHtml(_serializeBlocks());

	_currentSpan = null;
	_blocksEl = null;
	_blocks = [];
	span.innerHTML = html || (span.getAttribute('title') || '');
	_apFieldSave(span.id, html);
}

function _cancel(span, originalHtml) {
	if (!_active) return;
	_stopAutoSave();
	_active = false;
	_hideSlashMenu();
	_hideTypePopup();
	_hideInlineToolbar();
	document.removeEventListener('selectionchange', _onSelectionChange);
	if (_inlineToolbar) { _inlineToolbar.remove(); _inlineToolbar = null; }
	if (typeof _apChanging !== 'undefined') _apChanging = false;

	_currentSpan = null;
	_blocksEl = null;
	_blocks = [];
	span.innerHTML = originalHtml;
}

function _startAutoSave(fieldId) {
	_autoTimer = setInterval(() => {
		if (!_active || !_blocksEl) return;
		_syncAllBlocks();
		const html = _cleanHtml(_serializeBlocks());
		if (html === _lastSaved) return;
		_setStatus('保存中...');
		_fetchSave(fieldId, html, ok => {
			if (ok) {
				_lastSaved = html;
				_setStatus('✓ 自動保存済み');
				setTimeout(() => _setStatus(''), 3000);
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

/* 全ブロックの内部データを DOM から同期 */
function _syncAllBlocks() {
	_blocks.forEach(block => {
		const el = _getBlockEl(block);
		if (!el) return;

		if (['paragraph','heading','quote'].includes(block.type)) {
			const c = el.querySelector('.ap-wy-block-content');
			if (c) { block.data.text = c.innerHTML.trim(); if (block.data.text === '<br>') block.data.text = ''; }
		} else if (block.type === 'code') {
			const c = el.querySelector('.ap-wy-block-content');
			if (c) block.data.text = c.textContent;
		} else if (block.type === 'list') {
			const list = el.querySelector('ul,ol');
			if (list) _syncListData(list, block);
		}
		/* table, image, checklist はリアルタイムでdata更新済み */
	});
}

/* ── Fetch ヘルパー ── */
function _fetchSave(key, val, callback) {
	const csrf = _getCsrf();
	if (!csrf) { if (callback) callback(false); return; }
	fetch('index.php', {
		method: 'POST',
		headers: {
			'Content-Type': 'application/x-www-form-urlencoded',
			'X-CSRF-TOKEN': csrf,
		},
		body: new URLSearchParams({ fieldname: key, content: val, csrf }),
	}).then(r => { if (callback) callback(r.ok); })
	  .catch(() => { if (callback) callback(false); });
}

/* ══════════════════════════════════════════════
   HTML サニタイズ
   ══════════════════════════════════════════════ */

function _cleanHtml(html) {
	const tmp = document.createElement('div');
	tmp.innerHTML = html;
	_sanitizeNode(tmp);
	return tmp.innerHTML;
}

function _sanitizeNode(node) {
	const children = Array.from(node.childNodes);
	children.forEach(child => {
		if (child.nodeType !== 1) return; /* テキストノードはそのまま */
		if (!_allowedTags[child.tagName]) {
			const frag = document.createDocumentFragment();
			Array.from(child.childNodes).forEach(c => frag.appendChild(c));
			node.replaceChild(frag, child);
			_sanitizeNode(node);
			return;
		}
		/* 属性フィルタ */
		Array.from(child.attributes).forEach(attr => {
			const tag = child.tagName;
			const keep =
				(tag === 'A' && attr.name === 'href') ||
				(tag === 'IMG' && (attr.name === 'src' || attr.name === 'alt')) ||
				(tag === 'IMG' && attr.name === 'style') ||
				(tag === 'UL' && attr.name === 'class') ||
				(tag === 'LI' && attr.name === 'class') ||
				(tag === 'FIGURE' && attr.name === 'style') ||
				(['P','H2','H3','BLOCKQUOTE','DIV'].includes(tag) && attr.name === 'style');
			if (!keep) child.removeAttribute(attr.name);
		});
		/* style属性: text-align のみ許可 */
		if (child.hasAttribute('style')) {
			const style = child.getAttribute('style');
			const match = style.match(/text-align\s*:\s*(left|center|right)/);
			const widthMatch = style.match(/width\s*:\s*(\d+%)/);
			const allowed = [];
			if (match) allowed.push(`text-align:${match[1]}`);
			if (widthMatch) allowed.push(`width:${widthMatch[1]}`);
			if (allowed.length) child.setAttribute('style', allowed.join(';'));
			else child.removeAttribute('style');
		}
		/* 危険スキーム除去 */
		if (child.tagName === 'A') {
			const href = child.getAttribute('href') || '';
			if (!_isSafeUrl(href)) child.removeAttribute('href');
		}
		if (child.tagName === 'IMG') {
			const src = child.getAttribute('src') || '';
			if (/^javascript:/i.test(src.trim()) ||
				(/^data:/i.test(src.trim()) && !/^data:image\/(png|jpeg|gif|webp)/i.test(src.trim()))) {
				child.removeAttribute('src');
			}
		}
		_sanitizeNode(child);
	});
}

})();
