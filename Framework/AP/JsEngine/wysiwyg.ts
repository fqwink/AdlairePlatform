/// <reference lib="dom" />
/// <reference path="./browser-types.d.ts" />

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

(function (): void {

/* ── Interfaces ── */

interface WysiwygBlock {
	id: string;
	type: string;
	data: Record<string, unknown>;
}

interface Tool {
	cmd?: string;
	label?: string;
	title?: string;
	aria?: string;
	sep?: boolean;
}

interface SlashCommand {
	type: string;
	icon: string;
	label: string;
	keywords: string;
}

interface InlineTool {
	cmd: string;
	label: string;
	aria: string;
}

interface TypePopupOption {
	label: string;
	type: string;
	extra?: Record<string, unknown>;
}

interface AlignOption {
	label: string;
	align: string;
}

interface UndoEntry {
	snap: string;
	time: number;
	blockCount: number;
}

interface DiffLine {
	type: 'equal' | 'add' | 'remove';
	text: string;
}

interface DiffResult extends Array<DiffLine> {
	_truncated?: boolean;
}

interface Revision {
	file: string;
	timestamp?: string;
	size?: number;
	restored?: boolean;
	pinned?: boolean;
	user?: string;
}

interface HistoryPanelState {
	panel: HTMLElement;
	overlay: HTMLElement;
	body: HTMLElement;
	span: HTMLElement;
}

/* ── AEB EventBus 連携 ── */
function _emitAEB(event: string, detail: Record<string, unknown>): void {
	if (window.__AP_EventBus__ && typeof window.__AP_EventBus__.emit === 'function') {
		try { window.__AP_EventBus__.emit(event, detail); } catch (_) { /* ignore */ }
	}
}

/* ══════════════════════════════════════════════
   CSS 注入
   ══════════════════════════════════════════════ */
const _css: string = `
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

/* ── 履歴パネル ── */
.ap-wy-history-panel{position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);
  z-index:2000;background:#1e1e1e;border:1px solid #555;border-radius:8px;
  width:640px;max-width:90vw;min-height:300px;max-height:80vh;display:flex;flex-direction:column;
  box-shadow:0 8px 32px rgba(0,0,0,.6);}
@media(max-width:480px){.ap-wy-history-panel{width:98vw;max-height:90vh;border-radius:4px;}}
.ap-wy-history-header{display:flex;align-items:center;padding:10px 14px;
  border-bottom:1px solid #444;gap:8px;}
.ap-wy-history-header span{margin:0;font-size:14px;color:#eee;flex:1;}
.ap-wy-history-close{cursor:pointer;color:#aaa;font-size:18px;background:none;
  border:none;padding:4px 8px;}
.ap-wy-history-close:hover{color:#fff;}
.ap-wy-history-tabs{display:flex;border-bottom:1px solid #444;}
.ap-wy-history-tab{padding:8px 16px;cursor:pointer;font-size:13px;color:#aaa;
  background:none;border:none;border-bottom:2px solid transparent;flex-direction:column;}
.ap-wy-history-tab.active{color:#0ad;border-bottom-color:#0ad;}
.ap-wy-history-tab:hover{color:#ddd;}
.ap-wy-history-tab:focus{outline:2px solid #0ad;outline-offset:-2px;}
.ap-wy-history-tab small{display:block;font-size:10px;color:#777;margin-top:2px;}
.ap-wy-history-body{flex:1;overflow-y:auto;padding:8px;min-height:0;}
.ap-wy-history-item{display:block;padding:8px 10px;border-radius:4px;font-size:13px;
  color:#ccc;border:1px solid transparent;margin-bottom:2px;}
.ap-wy-history-item:hover{background:rgba(255,255,255,.06);}
.ap-wy-history-item.active,.ap-wy-history-item:focus{outline:2px solid #0ad;background:rgba(0,170,221,.1);}
.ap-wy-history-item-time{font-size:11px;color:#888;}
.ap-wy-history-item-info{flex:1;}
.ap-wy-history-item-badge{display:inline-block;font-size:10px;padding:1px 6px;border-radius:3px;margin-left:6px;}
.ap-wy-history-item-badge.restored{background:#553;color:#fc0;}
.ap-wy-history-item-badge.pinned{background:#335;color:#6cf;}
.ap-wy-history-btn{padding:3px 8px;font-size:11px;background:#444;color:#ddd;
  border:1px solid #555;border-radius:3px;cursor:pointer;}
.ap-wy-history-btn:hover{background:#666;}
.ap-wy-history-btn:focus{outline:2px solid #0ad;}
.ap-wy-history-btn.primary{background:#0ad;color:#000;border-color:#0ad;}
.ap-wy-history-overlay{position:fixed;top:0;left:0;right:0;bottom:0;
  background:rgba(0,0,0,.5);z-index:1999;}
.ap-wy-history-search{display:flex;gap:6px;padding:8px 10px;border-bottom:1px solid #444;}
.ap-wy-history-search input{flex:1;padding:4px 8px;font-size:12px;border:1px solid #555;
  border-radius:3px;background:#2a2a2a;color:#ddd;}
.ap-wy-history-search input:focus{border-color:#0ad;outline:none;}
.ap-wy-history-search button{padding:4px 10px;font-size:12px;}
.ap-wy-diff-view{padding:8px;font-family:monospace;font-size:12px;
  white-space:pre-wrap;line-height:1.6;max-height:40vh;overflow-y:auto;
  background:#111;border-radius:4px;margin:8px;}
.ap-wy-diff-add{background:rgba(0,180,0,.15);color:#8f8;border-left:3px solid #4c4;}
.ap-wy-diff-del{background:rgba(255,0,0,.15);color:#f88;text-decoration:line-through;border-left:3px solid #c44;}
.ap-wy-diff-eq{color:#888;}
.ap-wy-diff-fold{color:#0ad;cursor:pointer;padding:2px 0;font-style:italic;font-size:11px;}
.ap-wy-diff-fold:hover{text-decoration:underline;}
`;
const _styleEl: HTMLStyleElement = document.createElement('style');
_styleEl.textContent = _css;
document.head.appendChild(_styleEl);

/* ══════════════════════════════════════════════
   定義
   ══════════════════════════════════════════════ */

/* ── ツールバー定義（上部固定） ── */
const TOOLS: Tool[] = [
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
	{ cmd:'history', label:'📋', title:'編集履歴', aria:'編集履歴' },
	{ sep:true },
	{ cmd:'save',   label:'✓ 保存', title:'保存 (Ctrl+Enter)', aria:'保存' },
	{ cmd:'cancel', label:'✕ 取消', title:'キャンセル (Esc)',   aria:'取消' },
];

/* ── スラッシュコマンド定義 ── */
const SLASH_COMMANDS: SlashCommand[] = [
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
const _allowedTags: Record<string, number> = {
	B:1, I:1, U:1, S:1, STRONG:1, EM:1, MARK:1, CODE:1,
	H2:1, H3:1, P:1, BR:1, BLOCKQUOTE:1, PRE:1, HR:1,
	UL:1, OL:1, LI:1, A:1, IMG:1,
	TABLE:1, THEAD:1, TBODY:1, TR:1, TH:1, TD:1,
	FIGURE:1, FIGCAPTION:1,
};

/* ── 状態 ── */
let _active: boolean       = false;
let _autoTimer: ReturnType<typeof setInterval> | null    = null;
let _lastSaved: string     = '';
let _statusEl: HTMLElement | null     = null;
let _currentSpan: HTMLElement | null  = null;
let _blocksEl: HTMLElement | null     = null;
let _blocks: WysiwygBlock[]       = [];   // 内部ブロックモデル
let _slashMenu: HTMLElement | null    = null;
let _slashFilter: string  = '';
let _slashBlock: WysiwygBlock | null   = null;
let _slashIdx: number     = 0;
let _typePopup: HTMLElement | null    = null;
let _inlineToolbar: HTMLElement | null = null;
let _dragBlock: WysiwygBlock | null    = null;
let _dragStarted: boolean  = false;
let _dropLine: HTMLElement | null     = null;
let _idCounter: number    = 0;
let _undoStack: UndoEntry[]    = [];
let _redoStack: UndoEntry[]    = [];
const _UNDO_LIMIT: number = 50;
let _historyPanel: HistoryPanelState | null = null;
let _docHandler: ((e: MouseEvent) => void) | null   = null;
let _typePopupKeyHandler: ((e: KeyboardEvent) => void) | null   = null;
let _typePopupCloseHandler: ((e: MouseEvent) => void) | null = null;

/* ── ユーティリティ ── */
function _uid(): string { return 'b' + (++_idCounter) + '-' + Math.random().toString(36).slice(2, 7); }

function _isSafeUrl(url: string): boolean {
	const trimmed = (url || '').trim();
	if (/^(https?:|mailto:|\/)/i.test(trimmed)) return true;
	if (/^[a-z0-9#]/i.test(trimmed) && !trimmed.includes(':')) return true; /* 相対パス */
	return false;
}

function _getCsrf(): string | null {
	const meta = document.querySelector('meta[name="csrf-token"]');
	return meta ? meta.getAttribute('content') : null;
}

/* ══════════════════════════════════════════════
   初期化 & エディタ起動
   ══════════════════════════════════════════════ */

document.addEventListener('DOMContentLoaded', (): void => {
	document.querySelectorAll('span.editRich').forEach((span: Element): void => {
		span.addEventListener('click', (): void => { _activate(span as HTMLElement); });
	});
});

function _activate(span: HTMLElement): void {
	if (_active) return;
	_active = true;
	_currentSpan = span;
	if (typeof window._apChanging !== 'undefined') window._apChanging = true;

	const originalHtml: string = span.innerHTML;

	/* Chromium ネイティブリサイズハンドルを無効化 (Ph2-2) */
	try {
		document.execCommand('enableObjectResizing', false, 'false');
		document.execCommand('enableInlineTableEditing', false, 'false');
	} catch (e: unknown) { console.warn('[AP WYSIWYG] execCommand not supported:', (e as Error).message); }

	/* ─ ツールバー構築 ─ */
	const toolbar: HTMLDivElement = document.createElement('div');
	toolbar.className = 'ap-wy-toolbar';
	toolbar.setAttribute('role', 'toolbar');
	toolbar.setAttribute('aria-label', 'エディタツールバー');

	TOOLS.forEach((t: Tool): void => {
		if (t.sep) {
			const sep: HTMLSpanElement = document.createElement('span');
			sep.className = 'ap-wy-sep';
			sep.textContent = '|';
			sep.setAttribute('aria-hidden', 'true');
			toolbar.appendChild(sep);
			return;
		}
		const btn: HTMLButtonElement = document.createElement('button');
		btn.type = 'button';
		btn.className = 'ap-wy-btn';
		btn.innerHTML = t.label || '';
		btn.title = t.title || '';
		btn.dataset.cmd = t.cmd || '';
		btn.setAttribute('aria-label', t.aria || t.title || '');
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
	_blocks.forEach((b: WysiwygBlock): void => { _blocksEl!.appendChild(_renderBlock(b)); });

	/* ─ ラッパー ─ */
	const wrap: HTMLDivElement = document.createElement('div');
	wrap.className = 'ap-wy-wrap';
	wrap.appendChild(toolbar);
	wrap.appendChild(_blocksEl);

	span.innerHTML = '';
	span.appendChild(wrap);

	/* 最初のブロックにフォーカス */
	const firstContent = _blocksEl.querySelector('.ap-wy-block-content[contenteditable="true"]') as HTMLElement | null;
	if (firstContent) { firstContent.focus(); _setCursorToEnd(firstContent); }

	/* ─ 自動保存開始 ─ */
	_lastSaved = _cleanHtml(originalHtml);
	_undoStack = [];
	_redoStack = [];
	_saveSnapshot();
	_startAutoSave(span.id);

	/* ─ ツールバークリック ─ */
	toolbar.addEventListener('mousedown', (e: MouseEvent): void => {
		e.preventDefault();
		const btn = (e.target as HTMLElement).closest('[data-cmd]') as HTMLElement | null;
		if (!btn) return;
		_exec(btn.dataset.cmd || '', span, originalHtml);
	});

	/* ─ グローバルキーボードショートカット ─ */
	_blocksEl.addEventListener('keydown', (e: KeyboardEvent): void => {
		const mod: boolean = e.ctrlKey || e.metaKey;
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
	_blocksEl.addEventListener('dragover', (e: DragEvent): void => {
		if (e.dataTransfer && e.dataTransfer.types.indexOf('Files') !== -1) {
			e.preventDefault();
			_blocksEl!.classList.add('ap-wy-dragover');
		}
	});
	_blocksEl.addEventListener('dragleave', (): void => { _blocksEl!.classList.remove('ap-wy-dragover'); });
	_blocksEl.addEventListener('drop', (e: DragEvent): void => {
		_blocksEl!.classList.remove('ap-wy-dragover');
		const files = e.dataTransfer?.files;
		if (files && files.length > 0 && files[0].type.match(/^image\//)) {
			e.preventDefault();
			_insertImageBlock(files[0]);
		}
	});

	/* ─ クリップボード（画像 & リッチテキスト） ─ */
	_blocksEl.addEventListener('paste', (e: ClipboardEvent): void => {
		const cd = e.clipboardData;
		if (!cd) return;

		/* 画像ペースト */
		const items = cd.items;
		if (items) {
			for (let i = 0; i < items.length; i++) {
				if (items[i].type.indexOf('image/') === 0) {
					e.preventDefault();
					_insertImageBlock(items[i].getAsFile()!);
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
	_docHandler = function (e: MouseEvent): void {
		if (!wrap.contains(e.target as Node)) {
			document.removeEventListener('mousedown', _docHandler!);
			document.removeEventListener('selectionchange', _onSelectionChange);
			_manualSave(span);
		}
	};
	document.addEventListener('mousedown', _docHandler);
}

/* ══════════════════════════════════════════════
   HTML ↔ ブロック変換
   ══════════════════════════════════════════════ */

/* C15 fix: HTML文字列 → ブロック配列（template 要素で安全に解析） */
function _parseHtmlToBlocks(html: string): WysiwygBlock[] {
	const tpl = document.createElement('template');
	tpl.innerHTML = html;
	const tmp = document.createElement('div');
	tmp.appendChild(tpl.content.cloneNode(true));
	const blocks: WysiwygBlock[] = [];

	const _pushText = (type: string, node: Element, extra?: Record<string, unknown>): void => {
		const b: WysiwygBlock = { id: _uid(), type, data: { text: node.innerHTML.trim(), ...extra } };
		blocks.push(b);
	};

	Array.from(tmp.childNodes).forEach((node: ChildNode): void => {
		if (node.nodeType === 3) {
			const t = (node.textContent || '').trim();
			if (t) blocks.push({ id: _uid(), type: 'paragraph', data: { text: t } });
			return;
		}
		if (node.nodeType !== 1) return;
		const el = node as Element;
		const tag = el.tagName;

		if (tag === 'H2') { _pushText('heading', el, { level: 2 }); }
		else if (tag === 'H3') { _pushText('heading', el, { level: 3 }); }
		else if (tag === 'BLOCKQUOTE') { _pushText('quote', el); }
		else if (tag === 'PRE') {
			const code = el.querySelector('code');
			blocks.push({ id: _uid(), type: 'code', data: { text: (code || el).textContent || '' } });
		}
		else if (tag === 'HR') { blocks.push({ id: _uid(), type: 'delimiter', data: {} }); }
		else if (tag === 'UL' && el.classList.contains('ap-checklist')) {
			const items = Array.from(el.querySelectorAll('li')).map((li: Element) => ({
				text: li.innerHTML.trim(),
				checked: li.classList.contains('ap-checked'),
			}));
			blocks.push({ id: _uid(), type: 'checklist', data: { items } });
		}
		else if (tag === 'UL' || tag === 'OL') {
			const items = Array.from(el.querySelectorAll('li')).map((li: Element) => li.innerHTML.trim());
			blocks.push({ id: _uid(), type: 'list', data: { style: tag === 'OL' ? 'ordered' : 'unordered', items } });
		}
		else if (tag === 'TABLE') {
			const rows = Array.from(el.querySelectorAll('tr')).map((tr: Element) =>
				Array.from(tr.querySelectorAll('td,th')).map((cell: Element) => cell.innerHTML.trim())
			);
			blocks.push({ id: _uid(), type: 'table', data: { rows } });
		}
		else if (tag === 'FIGURE') {
			const img = el.querySelector('img');
			const cap = el.querySelector('figcaption');
			if (img) {
				blocks.push({ id: _uid(), type: 'image', data: {
					src: img.getAttribute('src') || '',
					alt: img.getAttribute('alt') || '',
					caption: cap ? cap.innerHTML.trim() : '',
					width: (img as HTMLImageElement).style.width || '100%',
				}});
			}
		}
		else if (tag === 'IMG') {
			blocks.push({ id: _uid(), type: 'image', data: {
				src: el.getAttribute('src') || '',
				alt: el.getAttribute('alt') || '',
				caption: '', width: '100%',
			}});
		}
		else { /* P, DIV, その他 → paragraph */
			_pushText('paragraph', el);
		}
	});
	return blocks;
}

/* ブロック配列 → HTML文字列 */
function _serializeBlocks(): string {
	return _blocks.map((b: WysiwygBlock): string => {
		const d = b.data;
		const alignStyle: string = d.align && d.align !== 'left' ? ` style="text-align:${d.align}"` : '';

		switch (b.type) {
			case 'paragraph':    return `<p${alignStyle}>${d.text || '<br>'}</p>`;
			case 'heading':      return `<h${d.level || 2}${alignStyle}>${d.text || ''}</h${d.level || 2}>`;
			case 'quote':        return `<blockquote${alignStyle}>${d.text || ''}</blockquote>`;
			case 'code':         return `<pre><code>${_escHtml(String(d.text || ''))}</code></pre>`;
			case 'delimiter':    return '<hr>';
			case 'list': {
				const tag = d.style === 'ordered' ? 'ol' : 'ul';
				const lis = ((d.items || []) as string[]).map((i: string) => `<li>${i}</li>`).join('');
				return `<${tag}>${lis}</${tag}>`;
			}
			case 'table': {
				const trs = ((d.rows || []) as string[][]).map((row: string[]) =>
					'<tr>' + row.map((cell: string) => `<td>${cell}</td>`).join('') + '</tr>'
				).join('');
				return `<table>${trs}</table>`;
			}
			case 'image': {
				const w: string = d.width && d.width !== '100%' ? ` style="width:${d.width}"` : '';
				const alt: string = d.alt ? ` alt="${_escAttr(String(d.alt))}"` : '';
				const cap: string = d.caption ? `<figcaption>${d.caption}</figcaption>` : '';
				return `<figure><img src="${_escAttr(String(d.src))}"${alt}${w}>${cap}</figure>`;
			}
			case 'checklist': {
				const lis = ((d.items || []) as Array<{checked: boolean; text: string}>).map((i) =>
					`<li class="${i.checked ? 'ap-checked' : ''}">${i.text}</li>`
				).join('');
				return `<ul class="ap-checklist">${lis}</ul>`;
			}
			default: return `<p>${d.text || ''}</p>`;
		}
	}).join('\n');
}

function _escHtml(s: string): string { return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function _escAttr(s: string): string { return s.replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

/* ══════════════════════════════════════════════
   ブロック描画
   ══════════════════════════════════════════════ */

function _renderBlock(block: WysiwygBlock): HTMLDivElement {
	const el: HTMLDivElement = document.createElement('div');
	el.className = 'ap-wy-block';
	el.dataset.type = block.type;
	el.dataset.id = block.id;
	if (block.data.align) el.dataset.align = String(block.data.align);

	/* ── ハンドル ── */
	const handle: HTMLDivElement = document.createElement('div');
	handle.className = 'ap-wy-block-handle';
	handle.textContent = '⠿';
	handle.title = 'ドラッグで移動 / クリックでタイプ変換';
	handle.setAttribute('aria-label', 'ブロック操作');
	handle.addEventListener('mousedown', (e: MouseEvent): void => {
		e.preventDefault();
		_startDrag(block, el, e, false);
	});
	handle.addEventListener('touchstart', (e: TouchEvent): void => {
		if (e.touches.length !== 1) return;
		e.preventDefault();
		_startDrag(block, el, e.touches[0], true);
	}, { passive: false });
	handle.addEventListener('click', (e: MouseEvent): void => {
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

	if (block.type === 'heading') el.dataset.level = String(block.data.level || 2);

	return el;
}

/* ── テキストブロック（paragraph / heading / quote） ── */
function _renderTextBlock(el: HTMLElement, block: WysiwygBlock): void {
	const content: HTMLDivElement = document.createElement('div');
	content.className = 'ap-wy-block-content';
	content.contentEditable = String(true);
	content.setAttribute('role', 'textbox');
	content.setAttribute('aria-label', `${block.type} ブロック`);
	content.innerHTML = String(block.data.text || '') || '<br>';
	_attachBlockKeyHandler(content, block);
	_attachBlockInput(content, block);
	el.appendChild(content);
}

/* ── コードブロック ── */
function _renderCodeBlock(el: HTMLElement, block: WysiwygBlock): void {
	const content: HTMLDivElement = document.createElement('div');
	content.className = 'ap-wy-block-content';
	content.contentEditable = String(true);
	content.setAttribute('role', 'textbox');
	content.setAttribute('aria-label', 'コードブロック');
	content.textContent = String(block.data.text || '');
	/* コードブロック内ではインライン書式無効化 */
	content.addEventListener('keydown', (e: KeyboardEvent): void => {
		const mod: boolean = e.ctrlKey || e.metaKey;
		if (mod && ['b','i','u'].includes(e.key.toLowerCase())) e.preventDefault();
	});
	content.addEventListener('paste', (e: ClipboardEvent): void => {
		e.preventDefault();
		const text = e.clipboardData?.getData('text/plain') || '';
		document.execCommand('insertText', false, text);
	});
	_attachBlockKeyHandler(content, block);
	content.addEventListener('input', (): void => { block.data.text = content.textContent || ''; });
	el.appendChild(content);
}

/* ── 区切り線 ── */
function _renderDelimiter(el: HTMLElement, block: WysiwygBlock): void {
	const content: HTMLDivElement = document.createElement('div');
	content.className = 'ap-wy-block-content';
	content.innerHTML = '<hr>';
	content.style.cursor = 'pointer';
	content.addEventListener('click', (): void => {
		/* 次のブロックにフォーカス、なければ新規段落作成 */
		const idx: number = _blocks.indexOf(block);
		if (idx < _blocks.length - 1) {
			_focusBlock(_blocks[idx + 1]);
		} else {
			_addBlockAfter(block, 'paragraph', { text: '' });
		}
	});
	el.appendChild(content);
}

/* ── リストブロック ── */
function _renderListBlock(el: HTMLElement, block: WysiwygBlock): void {
	const content: HTMLDivElement = document.createElement('div');
	content.className = 'ap-wy-block-content';

	const listTag: string = block.data.style === 'ordered' ? 'ol' : 'ul';
	const list: HTMLElement = document.createElement(listTag);
	list.contentEditable = String(true);
	list.setAttribute('role', 'textbox');
	list.setAttribute('aria-label', 'リストブロック');

	((block.data.items || ['']) as string[]).forEach((item: string): void => {
		const li: HTMLLIElement = document.createElement('li');
		li.innerHTML = item || '<br>';
		list.appendChild(li);
	});

	/* リスト特殊キー処理 */
	list.addEventListener('keydown', (e: KeyboardEvent): void => {
		if (e.key === 'Enter') {
			const sel = window.getSelection();
			const anchorNode = sel?.anchorNode as Node | null;
			const li = (anchorNode as HTMLElement)?.closest?.('li') || (anchorNode?.parentElement as HTMLElement)?.closest?.('li');
			if (li && li.textContent?.trim() === '') {
				/* 空LIでEnter → リスト脱出、新段落作成 */
				e.preventDefault();
				li.remove();
				_syncListData(list, block);
				_addBlockAfter(block, 'paragraph', { text: '' });
				return;
			}
		}
		if (e.key === 'Backspace') {
			if (list.querySelectorAll('li').length <= 1 && (list.textContent || '').trim() === '') {
				e.preventDefault();
				_changeBlockType(block, 'paragraph');
				return;
			}
		}
	});

	list.addEventListener('input', (): void => { _syncListData(list, block); });

	content.appendChild(list);
	_attachBlockKeyHandler(content, block, list);
	el.appendChild(content);
}

function _syncListData(list: HTMLElement, block: WysiwygBlock): void {
	block.data.items = Array.from(list.querySelectorAll('li')).map((li: Element) => li.innerHTML.trim());
}

/* ── テーブルブロック ── */
function _renderTableBlock(el: HTMLElement, block: WysiwygBlock): void {
	const content: HTMLDivElement = document.createElement('div');
	content.className = 'ap-wy-block-content ap-wy-table-wrap';

	const table: HTMLTableElement = document.createElement('table');
	table.className = 'ap-wy-table';

	const rows = (block.data.rows || [['','',''],['','',''],['','','']]) as string[][];
	block.data.rows = rows;

	const _buildTable = (): void => {
		table.innerHTML = '';
		rows.forEach((row: string[], ri: number): void => {
			const tr: HTMLTableRowElement = document.createElement('tr');
			row.forEach((cell: string, ci: number): void => {
				const td: HTMLTableCellElement = document.createElement('td');
				td.contentEditable = String(true);
				td.innerHTML = cell || '<br>';
				td.addEventListener('input', (): void => { rows[ri][ci] = td.innerHTML.trim(); });
				td.addEventListener('keydown', (e: KeyboardEvent): void => {
					if (e.key === 'Tab') {
						e.preventDefault();
						const tds = Array.from(table.querySelectorAll('td')) as HTMLTableCellElement[];
						const idx: number = tds.indexOf(td);
						if (e.shiftKey) {
							if (idx > 0) { tds[idx - 1].focus(); _setCursorToEnd(tds[idx - 1]); }
							else {
								/* 最初のセルで Shift+Tab → 前のブロックへ */
								const bIdx: number = _blocks.indexOf(block);
								if (bIdx > 0) _focusBlock(_blocks[bIdx - 1], 'end');
							}
						} else {
							if (idx < tds.length - 1) { tds[idx + 1].focus(); _setCursorToEnd(tds[idx + 1]); }
							else {
								/* 最後のセルで Tab → 新しい行を追加 */
								rows.push(new Array(rows[0]?.length || 3).fill(''));
								_buildTable();
								const newTds = Array.from(table.querySelectorAll('td')) as HTMLTableCellElement[];
								const first = newTds[newTds.length - (rows[0]?.length || 3)];
								if (first) { first.focus(); _setCursorToEnd(first); }
							}
						}
					}
					if (e.key === 'Tab') e.stopPropagation(); /* Tab のみブロック間キー処理を防止 */
				});
				tr.appendChild(td);
			});
			table.appendChild(tr);
		});
	};
	_buildTable();

	/* テーブル操作ボタン */
	const actions: HTMLDivElement = document.createElement('div');
	actions.className = 'ap-wy-tbl-actions';
	const _tbtn = (label: string, fn: () => void): void => {
		const b: HTMLButtonElement = document.createElement('button');
		b.type = 'button'; b.className = 'ap-wy-tbl-btn'; b.textContent = label;
		b.addEventListener('click', (e: MouseEvent): void => { e.preventDefault(); fn(); });
		actions.appendChild(b);
	};
	_tbtn('+ 行', (): void => { rows.push(new Array(rows[0]?.length || 3).fill('')); _buildTable(); });
	_tbtn('+ 列', (): void => { rows.forEach((r: string[]) => r.push('')); _buildTable(); });
	_tbtn('- 行', (): void => { if (rows.length > 1) { rows.pop(); _buildTable(); } });
	_tbtn('- 列', (): void => { if ((rows[0]?.length || 0) > 1) { rows.forEach((r: string[]) => r.pop()); _buildTable(); } });

	content.appendChild(table);
	content.appendChild(actions);
	el.appendChild(content);
}

/* ── 画像ブロック ── */
function _renderImageBlock(el: HTMLElement, block: WysiwygBlock): void {
	const content: HTMLDivElement = document.createElement('div');
	content.className = 'ap-wy-block-content';

	if (block.data.src && _isSafeUrl(String(block.data.src))) {
		const img: HTMLImageElement = document.createElement('img');
		img.src = String(block.data.src);
		img.alt = String(block.data.alt || '');
		if (block.data.width && block.data.width !== '100%') img.style.width = String(block.data.width);
		content.appendChild(img);

		/* サイズプリセット */
		const actions: HTMLDivElement = document.createElement('div');
		actions.className = 'ap-wy-img-actions';
		['25%','50%','75%','100%'].forEach((w: string): void => {
			const btn: HTMLButtonElement = document.createElement('button');
			btn.type = 'button';
			btn.className = 'ap-wy-img-size-btn' + (block.data.width === w ? ' active' : '');
			btn.textContent = w;
			btn.addEventListener('click', (e: MouseEvent): void => {
				e.preventDefault();
				block.data.width = w;
				img.style.width = w === '100%' ? '' : w;
				actions.querySelectorAll('.ap-wy-img-size-btn').forEach((b: Element) => b.classList.remove('active'));
				btn.classList.add('active');
			});
			actions.appendChild(btn);
		});
		content.appendChild(actions);

		/* Alt テキスト入力 */
		const altLabel: HTMLDivElement = document.createElement('div');
		altLabel.style.cssText = 'font-size:11px;color:#888;padding:2px 0 0;';
		altLabel.textContent = 'Alt: ';
		const altInput: HTMLInputElement = document.createElement('input');
		altInput.type = 'text';
		altInput.value = String(block.data.alt || '');
		altInput.placeholder = '代替テキスト';
		altInput.style.cssText = 'width:200px;padding:1px 4px;font-size:11px;background:#333;color:#eee;border:1px solid #555;border-radius:3px;';
		altInput.addEventListener('input', (): void => { block.data.alt = altInput.value; img.alt = altInput.value; });
		altInput.addEventListener('keydown', (e: KeyboardEvent): void => {
		const mod: boolean = e.ctrlKey || e.metaKey;
		if ((mod && e.key === 'Enter') || e.key === 'Escape') return; /* save/cancel をバブルアップ */
		e.stopPropagation();
	}); /* Ph2-2 */
		altLabel.appendChild(altInput);
		content.appendChild(altLabel);

		/* キャプション */
		const caption: HTMLDivElement = document.createElement('div');
		caption.className = 'ap-wy-figcaption';
		caption.contentEditable = String(true);
		caption.setAttribute('role', 'textbox');
		caption.setAttribute('aria-label', 'キャプション');
		caption.innerHTML = String(block.data.caption || '');
		caption.addEventListener('input', (): void => { block.data.caption = caption.innerHTML.trim(); });
		caption.addEventListener('keydown', (e: KeyboardEvent): void => {
			if (e.key === 'Enter') { e.preventDefault(); }
			const mod: boolean = e.ctrlKey || e.metaKey;
			if ((mod && e.key === 'Enter') || e.key === 'Escape') return; /* save/cancel をバブルアップ */
			e.stopPropagation();
		});
		content.appendChild(caption);
	} else {
		content.textContent = '画像を読み込み中...';
	}

	el.appendChild(content);
}

/* ── チェックリストブロック ── */
function _renderChecklistBlock(el: HTMLElement, block: WysiwygBlock): void {
	const content: HTMLDivElement = document.createElement('div');
	content.className = 'ap-wy-block-content';

	const items = (block.data.items || [{ text: '', checked: false }]) as Array<{text: string; checked: boolean}>;
	block.data.items = items;

	const _buildChecklist = (): void => {
		content.innerHTML = '';
		items.forEach((item: {text: string; checked: boolean}, idx: number): void => {
			const row: HTMLDivElement = document.createElement('div');
			row.className = 'ap-wy-check-item' + (item.checked ? ' checked' : '');

			const cb: HTMLInputElement = document.createElement('input');
			cb.type = 'checkbox';
			cb.className = 'ap-wy-check-box';
			cb.checked = item.checked;
			cb.addEventListener('change', (): void => {
				item.checked = cb.checked;
				row.classList.toggle('checked', cb.checked);
			});

			const text: HTMLDivElement = document.createElement('div');
			text.className = 'ap-wy-check-text';
			text.contentEditable = String(true);
			text.innerHTML = item.text || '<br>';
			text.addEventListener('input', (): void => { item.text = text.innerHTML.trim(); });
			text.addEventListener('keydown', (e: KeyboardEvent): void => {
				if (e.key === 'Enter') {
					e.preventDefault();
					items.splice(idx + 1, 0, { text: '', checked: false });
					_buildChecklist();
					/* 新しい項目にフォーカス */
					const newText = content.querySelectorAll('.ap-wy-check-text')[idx + 1] as HTMLElement | undefined;
					if (newText) newText.focus();
				}
				if (e.key === 'Backspace' && (text.textContent || '').trim() === '') {
					e.preventDefault();
					if (idx === 0 && items.length === 1) {
						/* 唯一の空項目 → 段落に変換 */
						_changeBlockType(block, 'paragraph');
					} else if (idx === 0) {
						/* 最初の項目を削除、次の項目にフォーカス */
						items.splice(0, 1);
						_buildChecklist();
						const first = content.querySelectorAll('.ap-wy-check-text')[0] as HTMLElement | undefined;
						if (first) { first.focus(); _setCursorToStart(first); }
					} else {
						items.splice(idx, 1);
						_buildChecklist();
						const prev = content.querySelectorAll('.ap-wy-check-text')[idx - 1] as HTMLElement | undefined;
						if (prev) { prev.focus(); _setCursorToEnd(prev); }
					}
				}
				const mod: boolean = e.ctrlKey || e.metaKey;
				if ((mod && e.key === 'Enter') || e.key === 'Escape') return; /* save/cancel をバブルアップ */
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
