/// <reference lib="dom" />
/// <reference path="../../ACS/ClientEngine/browser-types.d.ts" />

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
		const validAligns = ["left", "center", "right"];
		const align = typeof d.align === 'string' ? d.align : '';
		const alignStyle: string = align && align !== 'left' && validAligns.includes(align) ? ` style="text-align:${align}"` : '';

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

/* ══════════════════════════════════════════════
   ブロック操作 & キーハンドラ
   ══════════════════════════════════════════════ */

function _attachBlockInput(content: HTMLElement, block: WysiwygBlock): void {
	content.addEventListener('input', (): void => {
		block.data.text = content.innerHTML.trim();
		if (block.data.text === '<br>') block.data.text = '';
	});
}

function _attachBlockKeyHandler(contentWrap: HTMLElement, block: WysiwygBlock, editableEl?: HTMLElement): void {
	const target: HTMLElement = editableEl || contentWrap;
	target.addEventListener('keydown', (e: KeyboardEvent): void => {
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
			if (!sel || !sel.rangeCount) return;
			const range = sel.getRangeAt(0);
			range.deleteContents();

			const afterRange = document.createRange();
			afterRange.setStartBefore(range.startContainer.nodeType === 3 ? range.startContainer : (range.startContainer.childNodes[range.startOffset] || range.startContainer));
			afterRange.setStart(range.startContainer, range.startOffset);
			afterRange.setEndAfter(target.lastChild || target);
			const frag = afterRange.extractContents();
			const tmp = document.createElement('div');
			tmp.appendChild(frag);
			const afterHtml: string = tmp.innerHTML.trim();

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
			block.data.text = target.textContent || '';
			return;
		}

		/* Backspace: ブロック結合 */
		if (e.key === 'Backspace' && _isCursorAtStart(target)) {
			const idx: number = _blocks.indexOf(block);
			if (idx > 0) {
				e.preventDefault();
				const prev: WysiwygBlock = _blocks[idx - 1];
				if (['paragraph','heading','quote'].includes(prev.type) && ['paragraph','heading','quote'].includes(block.type)) {
					/* テキスト系ブロック同士を結合 */
					const prevEl = _getBlockEl(prev);
					const prevContent = prevEl?.querySelector('.ap-wy-block-content') as HTMLElement | null;
					if (prevContent) {
						const prevLen: number = (String(prev.data.text || '')).length;
						prevContent.innerHTML = String(prev.data.text || '') + String(block.data.text || '');
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
			const idx: number = _blocks.indexOf(block);
			if (idx < _blocks.length - 1) {
				e.preventDefault();
				const next: WysiwygBlock = _blocks[idx + 1];
				if (['paragraph','heading','quote'].includes(block.type) && ['paragraph','heading','quote'].includes(next.type)) {
					block.data.text = String(block.data.text || '') + String(next.data.text || '');
					target.innerHTML = String(block.data.text) || '<br>';
					_removeBlock(next);
				}
			}
			return;
		}

		/* ArrowUp: 前のブロックへ移動 */
		if (e.key === 'ArrowUp' && _isCursorAtStart(target)) {
			const idx: number = _blocks.indexOf(block);
			if (idx > 0) { e.preventDefault(); _focusBlock(_blocks[idx - 1], 'end'); }
			return;
		}

		/* ArrowDown: 次のブロックへ移動 */
		if (e.key === 'ArrowDown' && _isCursorAtEnd(target)) {
			const idx: number = _blocks.indexOf(block);
			if (idx < _blocks.length - 1) { e.preventDefault(); _focusBlock(_blocks[idx + 1], 'start'); }
			return;
		}

		/* "/" スラッシュコマンド検出 */
		if (e.key === '/' && block.type !== 'code') {
			const text: string = (target.textContent || '').trim();
			if (text === '' || text === '/') {
				/* _showSlashMenu は input イベントで処理 */
			}
		}
	});

	/* input で "/" 検出 */
	if (block.type !== 'code' && block.type !== 'table') {
		target.addEventListener('input', (): void => {
			const text: string = target.textContent || '';
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

function _addBlockAfter(afterBlock: WysiwygBlock, type: string, data: Record<string, unknown>): WysiwygBlock {
	_saveSnapshot();
	const idx: number = _blocks.indexOf(afterBlock);
	const newBlock: WysiwygBlock = { id: _uid(), type, data };
	_blocks.splice(idx + 1, 0, newBlock);
	const newEl = _renderBlock(newBlock);
	const afterEl = _getBlockEl(afterBlock);
	if (afterEl && afterEl.nextSibling) {
		_blocksEl!.insertBefore(newEl, afterEl.nextSibling);
	} else {
		_blocksEl!.appendChild(newEl);
	}
	_focusBlock(newBlock, 'start');
	return newBlock;
}

function _removeBlock(block: WysiwygBlock): void {
	_saveSnapshot();
	const idx: number = _blocks.indexOf(block);
	if (idx === -1) return;
	_blocks.splice(idx, 1);
	const el = _getBlockEl(block);
	if (el) el.remove();
	/* 全ブロック削除されたら空段落を追加 */
	if (_blocks.length === 0) {
		const empty: WysiwygBlock = { id: _uid(), type: 'paragraph', data: { text: '' } };
		_blocks.push(empty);
		_blocksEl!.appendChild(_renderBlock(empty));
		_focusBlock(empty, 'start');
	}
}

function _getBlockEl(block: WysiwygBlock): HTMLElement | null {
	return _blocksEl?.querySelector(`[data-id="${block.id}"]`) as HTMLElement | null;
}

function _focusBlock(block: WysiwygBlock, pos?: string): void {
	const el = _getBlockEl(block);
	if (!el) return;
	const content = (el.querySelector('.ap-wy-block-content[contenteditable="true"]')
		|| el.querySelector('[contenteditable="true"]')) as HTMLElement | null;
	if (content) {
		content.focus();
		if (pos === 'start') _setCursorToStart(content);
		else if (pos === 'end') _setCursorToEnd(content);
	}
}

/* ── カーソルユーティリティ ── */

function _isCursorAtStart(el: HTMLElement): boolean {
	const sel = window.getSelection();
	if (!sel || !sel.rangeCount) return false;
	const range = sel.getRangeAt(0);
	if (!range.collapsed) return false;
	const pre = document.createRange();
	pre.selectNodeContents(el);
	pre.setEnd(range.startContainer, range.startOffset);
	return pre.toString().length === 0;
}

function _isCursorAtEnd(el: HTMLElement): boolean {
	const sel = window.getSelection();
	if (!sel || !sel.rangeCount) return false;
	const range = sel.getRangeAt(0);
	if (!range.collapsed) return false;
	const post = document.createRange();
	post.selectNodeContents(el);
	post.setStart(range.endContainer, range.endOffset);
	return post.toString().length === 0;
}

function _setCursorToStart(el: HTMLElement): void {
	const range = document.createRange();
	const sel = window.getSelection();
	if (!sel) return;
	range.selectNodeContents(el);
	range.collapse(true);
	sel.removeAllRanges();
	sel.addRange(range);
}

function _setCursorToEnd(el: HTMLElement): void {
	const range = document.createRange();
	const sel = window.getSelection();
	if (!sel) return;
	range.selectNodeContents(el);
	range.collapse(false);
	sel.removeAllRanges();
	sel.addRange(range);
}

function _setCursorAtOffset(el: HTMLElement, htmlOffset: number): void {
	/* HTML文字列のオフセット位置にカーソルを配置するため、
	   一時的にマーカーを挿入して位置を特定する */
	const html: string = el.innerHTML;
	const marker = '\u200B\u200B\u200B';
	el.innerHTML = html.slice(0, htmlOffset) + marker + html.slice(htmlOffset);
	const walker = document.createTreeWalker(el, NodeFilter.SHOW_TEXT);
	while (walker.nextNode()) {
		const idx = (walker.currentNode.textContent || '').indexOf(marker);
		if (idx !== -1) {
			walker.currentNode.textContent = (walker.currentNode.textContent || '').replace(marker, '');
			const range = document.createRange();
			range.setStart(walker.currentNode, idx);
			range.collapse(true);
			const sel = window.getSelection();
			if (sel) {
				sel.removeAllRanges();
				sel.addRange(range);
			}
			return;
		}
	}
	_setCursorToEnd(el);
}

/* ── Undo / Redo ── */

function _saveSnapshot(): void {
	_syncAllBlocks();
	const snap: string = JSON.stringify(_blocks);
	const last = _undoStack[_undoStack.length - 1];
	if (last && last.snap === snap) return;
	_undoStack.push({ snap, time: Date.now(), blockCount: _blocks.length });
	if (_undoStack.length > _UNDO_LIMIT) _undoStack.shift();
	_redoStack.length = 0;
}

function _undo(): void {
	if (_undoStack.length === 0) return;
	_syncAllBlocks();
	_redoStack.push({ snap: JSON.stringify(_blocks), time: Date.now(), blockCount: _blocks.length });
	const entry = _undoStack.pop()!;
	_restoreSnapshot(entry.snap);
	_setStatus('↩ 元に戻しました');
	setTimeout((): void => { _setStatus(''); }, 2000);
}

function _redo(): void {
	if (_redoStack.length === 0) return;
	_syncAllBlocks();
	_undoStack.push({ snap: JSON.stringify(_blocks), time: Date.now(), blockCount: _blocks.length });
	const entry = _redoStack.pop()!;
	_restoreSnapshot(entry.snap);
	_setStatus('↪ やり直しました');
	setTimeout((): void => { _setStatus(''); }, 2000);
}

function _restoreSnapshot(snap: string): void {
	_blocks = JSON.parse(snap) as WysiwygBlock[];
	_blocksEl!.innerHTML = '';
	_blocks.forEach((b: WysiwygBlock): void => { _blocksEl!.appendChild(_renderBlock(b)); });
	if (_blocks.length > 0) _focusBlock(_blocks[0], 'start');
}

/* ══════════════════════════════════════════════
   ツールバーコマンド実行
   ══════════════════════════════════════════════ */

function _exec(cmd: string, span: HTMLElement, originalHtml: string): void {
	switch (cmd) {
		case 'bold':
		case 'italic':
		case 'underline':
		case 'removeFormat':
			document.execCommand(cmd, false, undefined);
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
				const style: string = cmd === 'insertOrderedList' ? 'ordered' : 'unordered';
				const text: string = String(focused.data.text || '');
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
				const typeMap: Record<string, string> = { h2:'heading', h3:'heading', p:'paragraph', quote:'quote', codeBlock:'code', delimiter:'delimiter' };
				const newType: string = typeMap[cmd] || 'paragraph';
				const extra: Record<string, unknown> = {};
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
			const input: HTMLInputElement = document.createElement('input');
			input.type = 'file';
			input.accept = 'image/jpeg,image/png,image/gif,image/webp';
			input.onchange = (): void => { if (input.files?.[0]) _insertImageBlock(input.files[0]); };
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
		case 'history':
			_showHistoryPanel(span);
			break;
		case 'save':
			_manualSave(span);
			break;
		case 'cancel':
			_cancel(span, originalHtml);
			break;
	}
}

function _getFocusedBlock(): WysiwygBlock | null {
	const active = document.activeElement;
	if (!active || !_blocksEl?.contains(active)) return _blocks[_blocks.length - 1] || null;
	const blockEl = (active as HTMLElement).closest('.ap-wy-block') as HTMLElement | null;
	if (!blockEl) return null;
	return _blocks.find((b: WysiwygBlock) => b.id === blockEl.dataset.id) || null;
}

function _changeBlockType(block: WysiwygBlock, newType: string, extra: Record<string, unknown> = {}): void {
	_saveSnapshot();
	const oldEl = _getBlockEl(block);
	if (!oldEl) return;

	/* テキスト内容を保持 */
	let text: string = String(block.data.text || '');
	if (block.type === 'list') text = ((block.data.items || []) as string[]).join('<br>');
	if (block.type === 'code') text = _escHtml(String(block.data.text || ''));

	if (newType === 'delimiter') {
		block.type = 'delimiter';
		block.data = {};
	} else if (newType === 'list') {
		block.type = 'list';
		const rawText: string = newType === 'list' ? text.replace(/<br\s*\/?>/gi, '\n') : text;
		const items: string[] = rawText.split('\n').filter((s: string) => s.trim());
		block.data = { style: extra.style || 'unordered', items: items.length ? items : [''] };
	} else if (newType === 'code') {
		const tmp = document.createElement('div');
		tmp.innerHTML = text;
		block.type = 'code';
		block.data = { text: tmp.textContent || '' };
	} else {
		block.type = newType;
		block.data = { text, ...extra };
	}

	_rerenderBlock(block);
}

function _rerenderBlock(block: WysiwygBlock): void {
	const oldEl = _getBlockEl(block);
	if (!oldEl) return;
	const newEl = _renderBlock(block);
	oldEl.replaceWith(newEl);
	_focusBlock(block, 'end');
}

/* ══════════════════════════════════════════════
   インラインツール（S / Code / Marker / Link）
   ══════════════════════════════════════════════ */

function _toggleInline(cmd: string): void {
	document.execCommand(cmd, false, undefined);
}

function _toggleInlineCode(): void {
	const sel = window.getSelection();
	if (!sel || !sel.rangeCount || sel.isCollapsed) return;
	const range = sel.getRangeAt(0);

	/* 既に<code>内にいるかチェック */
	let codeParent: Node | null = range.commonAncestorContainer;
	while (codeParent && codeParent !== _blocksEl) {
		if (codeParent.nodeType === 1 && (codeParent as Element).tagName === 'CODE') {
			/* 解除: <code>の中身をテキストノードに置換 */
			const text = document.createTextNode(codeParent.textContent || '');
			codeParent.parentNode!.replaceChild(text, codeParent);
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
	} catch (e: unknown) { console.warn('[AP WYSIWYG] inlineCode:', (e as Error).message); }
}

function _toggleMarker(): void {
	const sel = window.getSelection();
	if (!sel || !sel.rangeCount || sel.isCollapsed) return;
	const range = sel.getRangeAt(0);

	let markParent: Node | null = range.commonAncestorContainer;
	while (markParent && markParent !== _blocksEl) {
		if (markParent.nodeType === 1 && (markParent as Element).tagName === 'MARK') {
			const text = document.createTextNode(markParent.textContent || '');
			markParent.parentNode!.replaceChild(text, markParent);
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
	} catch (e: unknown) { console.warn('[AP WYSIWYG] marker:', (e as Error).message); }
}

function _showLinkInput(): void {
	const sel = window.getSelection();
	if (!sel || !sel.rangeCount || sel.isCollapsed) {
		const url = prompt('URL を入力してください:');
		if (url?.trim()) {
			if (!_isSafeUrl(url)) { _setStatus('⚠ 安全でないURLです'); return; }
			document.execCommand('createLink', false, url.trim());
		}
		return;
	}

	/* 既存のリンクをチェック */
	let anchor: Node | null = sel.anchorNode;
	while (anchor && (anchor as Element).tagName !== 'A' && anchor !== _blocksEl) anchor = anchor.parentNode;

	if (anchor && (anchor as Element).tagName === 'A') {
		/* リンク解除 */
		document.execCommand('unlink', false, undefined);
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

const INLINE_TB_TOOLS: InlineTool[] = [
	{ cmd:'bold',          label:'<b>B</b>',  aria:'太字' },
	{ cmd:'italic',        label:'<i>I</i>',  aria:'斜体' },
	{ cmd:'underline',     label:'<u>U</u>',  aria:'下線' },
	{ cmd:'strikeThrough', label:'<s>S</s>',  aria:'取消線' },
	{ cmd:'inlineCode',    label:'&lt;&gt;',   aria:'コード' },
	{ cmd:'marker',        label:'<mark>M</mark>', aria:'マーカー' },
	{ cmd:'link',          label:'🔗',         aria:'リンク' },
];

function _onSelectionChange(): void {
	if (!_active || !_blocksEl) return;
	const sel = window.getSelection();
	if (!sel || !sel.rangeCount || sel.isCollapsed) { _hideInlineToolbar(); return; }

	/* 選択がブロックコンテナ内かチェック */
	const range = sel.getRangeAt(0);
	if (!_blocksEl.contains(range.commonAncestorContainer)) { _hideInlineToolbar(); return; }

	/* コードブロック内では表示しない */
	const blockEl = range.commonAncestorContainer.nodeType === 1
		? (range.commonAncestorContainer as Element).closest('.ap-wy-block')
		: range.commonAncestorContainer.parentElement?.closest('.ap-wy-block');
	if ((blockEl as HTMLElement)?.dataset.type === 'code') { _hideInlineToolbar(); return; }

	_showInlineToolbar(range);
}

function _showInlineToolbar(range: Range): void {
	if (!_inlineToolbar) {
		_inlineToolbar = document.createElement('div');
		_inlineToolbar.className = 'ap-wy-inline-tb';
		INLINE_TB_TOOLS.forEach((t: InlineTool): void => {
			const btn: HTMLButtonElement = document.createElement('button');
			btn.type = 'button';
			btn.className = 'ap-wy-btn';
			btn.dataset.cmd = t.cmd;
			btn.innerHTML = t.label;
			btn.setAttribute('aria-label', t.aria);
			btn.addEventListener('mousedown', (e: MouseEvent): void => {
				e.preventDefault();
				if (t.cmd === 'inlineCode') _toggleInlineCode();
				else if (t.cmd === 'marker') _toggleMarker();
				else if (t.cmd === 'link') _showLinkInput();
				else _toggleInline(t.cmd);
			});
			_inlineToolbar!.appendChild(btn);
		});
		document.body.appendChild(_inlineToolbar);
	}

	/* アクティブ状態を更新 */
	_inlineToolbar.querySelectorAll('.ap-wy-btn').forEach((btn: Element): void => {
		const cmd = (btn as HTMLElement).dataset.cmd;
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
	let top: number = window.scrollY + rect.top - 40;
	let left: number = window.scrollX + rect.left + rect.width / 2;
	_inlineToolbar.style.top = top + 'px';
	_inlineToolbar.style.left = left + 'px';
	/* ビューポートクランプ */
	requestAnimationFrame((): void => {
		if (!_inlineToolbar) return;
		const tbRect = _inlineToolbar.getBoundingClientRect();
		if (tbRect.top < 0) _inlineToolbar!.style.top = (window.scrollY + rect.bottom + 4) + 'px';
		if (tbRect.right > window.innerWidth) _inlineToolbar!.style.left = (window.innerWidth - tbRect.width - 8) + 'px';
		if (tbRect.left < 0) _inlineToolbar!.style.left = '4px';
	});
}

function _hideInlineToolbar(): void {
	if (_inlineToolbar) _inlineToolbar.style.display = 'none';
}

/* ══════════════════════════════════════════════
   "/" スラッシュコマンドメニュー
   ══════════════════════════════════════════════ */

function _showSlashMenu(target: HTMLElement): void {
	const filtered = SLASH_COMMANDS.filter((c: SlashCommand): boolean =>
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
	filtered.forEach((c: SlashCommand, i: number): void => {
		const item: HTMLDivElement = document.createElement('div');
		item.className = 'ap-wy-slash-item' + (i === 0 ? ' active' : '');
		item.setAttribute('role', 'option');
		item.innerHTML = `<span class="ap-wy-slash-item-icon">${c.icon}</span><span class="ap-wy-slash-item-label">${c.label}</span>`;
		item.addEventListener('mousedown', (e: MouseEvent): void => {
			e.preventDefault();
			_slashIdx = i;
			_applySlashCmd();
		});
		_slashMenu!.appendChild(item);
	});

	/* 位置計算 */
	const sel = window.getSelection();
	if (sel && sel.rangeCount) {
		const rect = sel.getRangeAt(0).getBoundingClientRect();
		_slashMenu.style.display = 'block';
		let top: number = window.scrollY + rect.bottom + 4;
		let left: number = window.scrollX + rect.left;

		/* DOM追加後に実寸でビューポートクランプ (Ph3-F) */
		_slashMenu.style.top = top + 'px';
		_slashMenu.style.left = Math.max(4, left) + 'px';
		requestAnimationFrame((): void => {
			if (!_slashMenu) return;
			const menuRect = _slashMenu.getBoundingClientRect();
			if (menuRect.bottom > window.innerHeight) top = window.scrollY + rect.top - menuRect.height - 4;
			if (menuRect.right > window.innerWidth) left = window.innerWidth - menuRect.width - 8;
			_slashMenu!.style.top = top + 'px';
			_slashMenu!.style.left = Math.max(4, left) + 'px';
		});
	}
}

function _hideSlashMenu(): void {
	if (_slashMenu) { _slashMenu.remove(); _slashMenu = null; }
	_slashFilter = '';
	_slashBlock = null;
}

function _slashNav(dir: number): void {
	if (!_slashMenu) return;
	const items = _slashMenu.querySelectorAll('.ap-wy-slash-item');
	if (items.length === 0) return;
	items[_slashIdx]?.classList.remove('active');
	_slashIdx = (_slashIdx + dir + items.length) % items.length;
	items[_slashIdx]?.classList.add('active');
	items[_slashIdx]?.scrollIntoView({ block: 'nearest' });
}

function _applySlashCmd(): void {
	if (!_slashMenu || !_slashBlock) return;
	const filtered = SLASH_COMMANDS.filter((c: SlashCommand): boolean =>
		_slashFilter === '' ||
		c.label.toLowerCase().includes(_slashFilter) ||
		c.keywords.includes(_slashFilter) ||
		c.type.includes(_slashFilter)
	);
	if (_slashIdx >= filtered.length) _slashIdx = Math.max(0, filtered.length - 1);
	const selected = filtered[_slashIdx];
	if (!selected) { _hideSlashMenu(); return; }

	const block: WysiwygBlock = _slashBlock;
	_hideSlashMenu();

	/* 現在のブロックの "/" テキストをクリア */
	const el = _getBlockEl(block);
	const content = el?.querySelector('.ap-wy-block-content[contenteditable="true"]') as HTMLElement | null;
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
			const input: HTMLInputElement = document.createElement('input');
			input.type = 'file';
			input.accept = 'image/jpeg,image/png,image/gif,image/webp';
			input.onchange = (): void => {
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

function _showTypePopup(block: WysiwygBlock, blockEl: HTMLElement): void {
	_hideTypePopup();

	_typePopup = document.createElement('div');
	_typePopup.className = 'ap-wy-type-popup';

	const options: TypePopupOption[] = [
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
	const alignOptions: AlignOption[] = [
		{ label: '← 左揃え',   align: 'left' },
		{ label: '↔ 中央揃え', align: 'center' },
		{ label: '→ 右揃え',   align: 'right' },
	];

	options.forEach((opt: TypePopupOption): void => {
		const item: HTMLDivElement = document.createElement('div');
		item.className = 'ap-wy-type-popup-item';
		item.textContent = opt.label;
		if (block.type === opt.type && (!opt.extra?.level || block.data.level === opt.extra.level)
			&& (!opt.extra?.style || block.data.style === opt.extra.style)) {
			item.style.fontWeight = 'bold';
		}
		item.addEventListener('mousedown', (e: MouseEvent): void => {
			e.preventDefault();
			_hideTypePopup();
			if (opt.type === 'list') {
				const text: string = String(block.data.text || '');
				block.type = 'list';
				block.data = { style: opt.extra!.style, items: text ? [text] : [''] };
				_rerenderBlock(block);
			} else {
				_changeBlockType(block, opt.type, opt.extra || {});
			}
		});
		_typePopup!.appendChild(item);
	});

	/* 配置セパレータ */
	if (['paragraph','heading','quote'].includes(block.type)) {
		const sep: HTMLDivElement = document.createElement('div');
		sep.className = 'ap-wy-type-popup-sep';
		_typePopup.appendChild(sep);

		alignOptions.forEach((opt: AlignOption): void => {
			const item: HTMLDivElement = document.createElement('div');
			item.className = 'ap-wy-type-popup-item';
			item.textContent = opt.label;
			if ((block.data.align || 'left') === opt.align) item.style.fontWeight = 'bold';
			item.addEventListener('mousedown', (e: MouseEvent): void => {
				e.preventDefault();
				_hideTypePopup();
				block.data.align = opt.align;
				blockEl.dataset.align = opt.align;
			});
			_typePopup!.appendChild(item);
		});
	}

	/* 削除ボタン */
	const sep2: HTMLDivElement = document.createElement('div');
	sep2.className = 'ap-wy-type-popup-sep';
	_typePopup.appendChild(sep2);
	const delItem: HTMLDivElement = document.createElement('div');
	delItem.className = 'ap-wy-type-popup-item danger';
	delItem.textContent = '🗑 ブロック削除';
	delItem.addEventListener('mousedown', (e: MouseEvent): void => {
		e.preventDefault();
		_hideTypePopup();
		_removeBlock(block);
	});
	_typePopup.appendChild(delItem);

	document.body.appendChild(_typePopup);

	/* 位置計算 */
	const handleEl = blockEl.querySelector('.ap-wy-block-handle') as HTMLElement | null;
	if (!handleEl) return;
	const handleRect = handleEl.getBoundingClientRect();
	let top: number = window.scrollY + handleRect.bottom + 2;
	let left: number = window.scrollX + handleRect.left;

	/* ビューポートクランプ (Ph3-G) */
	_typePopup.style.top = top + 'px';
	_typePopup.style.left = Math.max(4, left) + 'px';
	requestAnimationFrame((): void => {
		if (!_typePopup) return;
		const popRect = _typePopup.getBoundingClientRect();
		if (popRect.bottom > window.innerHeight) top = window.scrollY + handleRect.top - popRect.height - 2;
		if (popRect.right > window.innerWidth) left = window.innerWidth - popRect.width - 8;
		_typePopup!.style.top = top + 'px';
		_typePopup!.style.left = Math.max(4, left) + 'px';
	});

	/* キーボード操作 */
	let _popIdx = -1;
	const allItems = _typePopup.querySelectorAll('.ap-wy-type-popup-item');
	_typePopupKeyHandler = (e: KeyboardEvent): void => {
		if (e.key === 'ArrowDown') {
			e.preventDefault();
			if (_popIdx >= 0) (allItems[_popIdx] as HTMLElement).style.background = '';
			_popIdx = (_popIdx + 1) % allItems.length;
			(allItems[_popIdx] as HTMLElement).style.background = '#0ad';
			(allItems[_popIdx] as HTMLElement).style.color = '#000';
			allItems[_popIdx]?.scrollIntoView({ block: 'nearest' });
		} else if (e.key === 'ArrowUp') {
			e.preventDefault();
			if (_popIdx >= 0) { (allItems[_popIdx] as HTMLElement).style.background = ''; (allItems[_popIdx] as HTMLElement).style.color = ''; }
			_popIdx = (_popIdx - 1 + allItems.length) % allItems.length;
			(allItems[_popIdx] as HTMLElement).style.background = '#0ad';
			(allItems[_popIdx] as HTMLElement).style.color = '#000';
			allItems[_popIdx]?.scrollIntoView({ block: 'nearest' });
		} else if (e.key === 'Enter' && _popIdx >= 0) {
			e.preventDefault();
			allItems[_popIdx].dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
		} else if (e.key === 'Escape') {
			e.preventDefault();
			_hideTypePopup();
		}
	};
	setTimeout((): void => { document.addEventListener('keydown', _typePopupKeyHandler!); }, 0);

	/* 外部クリックで閉じる */
	_typePopupCloseHandler = (e: MouseEvent): void => {
		if (!_typePopup?.contains(e.target as Node)) {
			_hideTypePopup();
		}
	};
	setTimeout((): void => { document.addEventListener('mousedown', _typePopupCloseHandler!); }, 0);
}

function _hideTypePopup(): void {
	if (_typePopupKeyHandler) { document.removeEventListener('keydown', _typePopupKeyHandler); _typePopupKeyHandler = null; }
	if (_typePopupCloseHandler) { document.removeEventListener('mousedown', _typePopupCloseHandler); _typePopupCloseHandler = null; }
	if (_typePopup) { _typePopup.remove(); _typePopup = null; }
}

/* ══════════════════════════════════════════════
   ドラッグ並べ替え
   ══════════════════════════════════════════════ */

function _startDrag(block: WysiwygBlock, blockEl: HTMLElement, startEvent: MouseEvent | Touch, isTouch: boolean): void {
	_dragStarted = false;
	_dragBlock = block;

	const startY: number = startEvent.clientY;
	let moved = false;

	if (!_dropLine) {
		_dropLine = document.createElement('div');
		_dropLine.className = 'ap-wy-drop-line';
		_dropLine.style.display = 'none';
	}

	const _onMove = (e: MouseEvent | TouchEvent): void => {
		const y: number = isTouch ? ((e as TouchEvent).touches?.[0]?.clientY ?? startY) : (e as MouseEvent).clientY;
		if (!moved && Math.abs(y - startY) < 5) return;

		if (!moved) {
			moved = true;
			_dragStarted = true;
			blockEl.classList.add('dragging');
			_blocksEl!.appendChild(_dropLine!);
		}

		/* ドロップ位置計算 */
		const blockEls = Array.from(_blocksEl!.querySelectorAll('.ap-wy-block')) as HTMLElement[];
		let insertBefore: HTMLElement | null = null;
		let lineTop = 0;

		for (const el of blockEls) {
			if (el === blockEl) continue;
			const rect = el.getBoundingClientRect();
			const mid: number = rect.top + rect.height / 2;
			if (y < mid) {
				insertBefore = el;
				lineTop = rect.top - _blocksEl!.getBoundingClientRect().top - 2;
				break;
			}
			lineTop = rect.bottom - _blocksEl!.getBoundingClientRect().top;
		}

		_dropLine!.style.display = 'block';
		_dropLine!.style.top = lineTop + 'px';
		_dropLine!.dataset.beforeId = insertBefore?.dataset.id || '';
	};

	const moveEvt: string = isTouch ? 'touchmove' : 'mousemove';
	const upEvt: string = isTouch ? 'touchend' : 'mouseup';

	const _onUp = (): void => {
		document.removeEventListener(moveEvt, _onMove as EventListener);
		document.removeEventListener(upEvt, _onUp);

		if (moved) {
			_saveSnapshot();
			blockEl.classList.remove('dragging');
			_dropLine!.style.display = 'none';

			const beforeId: string = _dropLine!.dataset.beforeId || '';
			const fromIdx: number = _blocks.indexOf(block);
			_blocks.splice(fromIdx, 1);

			let toIdx: number = _blocks.length;
			if (beforeId) {
				toIdx = _blocks.findIndex((b: WysiwygBlock) => b.id === beforeId);
				if (toIdx === -1) toIdx = _blocks.length;
			}
			_blocks.splice(toIdx, 0, block);

			/* DOM 移動 */
			if (beforeId) {
				const beforeEl = _blocksEl!.querySelector(`[data-id="${beforeId}"]`);
				if (beforeEl) _blocksEl!.insertBefore(blockEl, beforeEl);
			} else {
				_blocksEl!.appendChild(blockEl);
			}
		}

		/* ドラッグフラグリセット（clickイベント防止のため遅延） */
		setTimeout((): void => { _dragStarted = false; _dragBlock = null; }, 50);
	};

	document.addEventListener(moveEvt, _onMove as EventListener, isTouch ? { passive: false } : undefined);
	document.addEventListener(upEvt, _onUp);
}

/* ══════════════════════════════════════════════
   画像アップロード & 画像ブロック挿入
   ══════════════════════════════════════════════ */

function _insertImageBlock(file: File): void {
	if (!file || !file.type.match(/^image\//)) {
		_setStatus('⚠ 画像ファイルのみ対応 (JPEG/PNG/GIF/WebP)');
		setTimeout((): void => { _setStatus(''); }, 5000);
		return;
	}
	const csrf = _getCsrf();
	if (!csrf) { console.error('[AP WYSIWYG] CSRF token not found'); return; }

	_setStatus('アップロード中...');

	const fd = new FormData();
	fd.append('ap_action', 'upload_image');
	fd.append('image', file);
	fd.append('csrf', csrf);

	fetch('/', {
		method: 'POST',
		headers: { 'X-CSRF-TOKEN': csrf },
		body: fd,
	}).then((r: Response): Promise<Record<string, unknown>> => {
		if (!r.ok) throw new Error('HTTP ' + r.status);
		return r.json() as Promise<Record<string, unknown>>;
	}).then((data: Record<string, unknown>): void => {
		if (data.error) throw new Error(String(data.error));
		_setStatus('');
		const focused = _getFocusedBlock() || _blocks[_blocks.length - 1];
		if (focused) {
			_addBlockAfter(focused, 'image', { src: data.url, alt: '', caption: '', width: '100%' });
		} else {
			const b: WysiwygBlock = { id: _uid(), type: 'image', data: { src: data.url, alt: '', caption: '', width: '100%' } };
			_blocks.push(b);
			_blocksEl!.appendChild(_renderBlock(b));
		}
	}).catch((e: Error): void => {
		_setStatus('⚠ アップロード失敗: ' + e.message);
		setTimeout((): void => { _setStatus(''); }, 5000);
	});
}

/* ══════════════════════════════════════════════
   編集履歴パネル
   ══════════════════════════════════════════════ */

/* D1: リビジョン間比較用の選択状態 */
let _diffCompareA: Revision | null = null;

function _showHistoryPanel(span: HTMLElement): void {
	if (_historyPanel) { _closeHistoryPanel(); return; }
	_diffCompareA = null;

	const overlay: HTMLDivElement = document.createElement('div');
	overlay.className = 'ap-wy-history-overlay';
	overlay.addEventListener('click', _closeHistoryPanel);

	const panel: HTMLDivElement = document.createElement('div');
	panel.className = 'ap-wy-history-panel';
	panel.setAttribute('role', 'dialog');
	panel.setAttribute('aria-label', '編集履歴');

	/* ヘッダー */
	const header: HTMLDivElement = document.createElement('div');
	header.className = 'ap-wy-history-header';
	const title: HTMLSpanElement = document.createElement('span');
	title.textContent = '編集履歴';
	header.appendChild(title);
	const closeBtn: HTMLButtonElement = document.createElement('button');
	closeBtn.className = 'ap-wy-history-close';
	closeBtn.textContent = '✕';
	closeBtn.setAttribute('aria-label', '閉じる');
	closeBtn.addEventListener('click', _closeHistoryPanel);
	header.appendChild(closeBtn);

	/* C5: タブ（説明テキスト付き） */
	const tabs: HTMLDivElement = document.createElement('div');
	tabs.className = 'ap-wy-history-tabs';
	tabs.setAttribute('role', 'tablist');
	const tabSession: HTMLButtonElement = document.createElement('button');
	tabSession.className = 'ap-wy-history-tab active';
	tabSession.setAttribute('role', 'tab');
	tabSession.setAttribute('aria-selected', 'true');
	tabSession.innerHTML = 'セッション<small>ブラウザ内の操作履歴</small>';
	tabSession.dataset.tab = 'session';
	const tabRevision: HTMLButtonElement = document.createElement('button');
	tabRevision.className = 'ap-wy-history-tab';
	tabRevision.setAttribute('role', 'tab');
	tabRevision.setAttribute('aria-selected', 'false');
	tabRevision.innerHTML = 'リビジョン<small>サーバー保存の版管理</small>';
	tabRevision.dataset.tab = 'revision';
	tabs.appendChild(tabSession);
	tabs.appendChild(tabRevision);

	/* ボディ */
	const body: HTMLDivElement = document.createElement('div');
	body.className = 'ap-wy-history-body';
	body.setAttribute('role', 'tabpanel');

	panel.appendChild(header);
	panel.appendChild(tabs);
	panel.appendChild(body);

	document.body.appendChild(overlay);
	document.body.appendChild(panel);

	_historyPanel = { panel, overlay, body, span };

	/* タブ切り替え */
	tabSession.addEventListener('click', (): void => {
		tabSession.classList.add('active');
		tabSession.setAttribute('aria-selected', 'true');
		tabRevision.classList.remove('active');
		tabRevision.setAttribute('aria-selected', 'false');
		_diffCompareA = null;
		_renderSessionHistory(body);
	});
	tabRevision.addEventListener('click', (): void => {
		tabRevision.classList.add('active');
		tabRevision.setAttribute('aria-selected', 'true');
		tabSession.classList.remove('active');
		tabSession.setAttribute('aria-selected', 'false');
		_diffCompareA = null;
		_renderRevisionTab(body, span);
	});

	/* C1: キーボード操作 */
	panel.addEventListener('keydown', (e: KeyboardEvent): void => {
		if (e.key === 'Escape') { _closeHistoryPanel(); e.preventDefault(); return; }
		if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
			const active = panel.querySelector('.ap-wy-history-tab.active') as HTMLButtonElement;
			const other: HTMLButtonElement = active === tabSession ? tabRevision : tabSession;
			other.click();
			other.focus();
			e.preventDefault();
			return;
		}
		/* リスト内の上下移動 */
		if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
			const items = Array.from(body.querySelectorAll('.ap-wy-history-item[tabindex]')) as HTMLElement[];
			if (items.length === 0) return;
			const cur = document.activeElement as HTMLElement;
			let idx: number = items.indexOf(cur);
			if (e.key === 'ArrowDown') idx = Math.min(idx + 1, items.length - 1);
			else idx = Math.max(idx - 1, 0);
			items[idx].focus();
			e.preventDefault();
		}
	});

	/* フォーカストラップ */
	closeBtn.focus();

	/* 初期表示: セッションタブ */
	_renderSessionHistory(body);
}

function _closeHistoryPanel(): void {
	if (!_historyPanel) return;
	_historyPanel.panel.remove();
	_historyPanel.overlay.remove();
	_historyPanel = null;
	_diffCompareA = null;
}

/* ── セッション履歴タブ ── */

function _renderSessionHistory(container: HTMLElement): void {
	container.innerHTML = '';
	const total: number = _undoStack.length;
	if (total === 0) {
		container.innerHTML = '<div style="padding:16px;color:#999;">操作履歴がありません</div>';
		return;
	}

	/* 現在の状態 */
	const currentItem: HTMLDivElement = document.createElement('div');
	currentItem.className = 'ap-wy-history-item';
	currentItem.style.borderLeft = '3px solid #0ad';
	const curInfo: HTMLDivElement = document.createElement('div');
	curInfo.innerHTML = '<strong>\u25B6 現在の状態</strong>';
	const curDetail: HTMLElement = document.createElement('small');
	curDetail.className = 'ap-wy-history-item-time';
	curDetail.textContent = 'ブロック数: ' + _blocks.length;
	currentItem.appendChild(curInfo);
	currentItem.appendChild(curDetail);
	container.appendChild(currentItem);

	/* undoStack を新しい順に */
	for (let i = total - 1; i >= 0; i--) {
		const entry: UndoEntry = _undoStack[i];
		const item: HTMLDivElement = document.createElement('div');
		item.className = 'ap-wy-history-item';
		item.setAttribute('tabindex', '0');
		item.setAttribute('role', 'button');
		item.setAttribute('aria-label', '操作 #' + (i + 1) + ' に戻す');
		const time: string = entry.time ? new Date(entry.time).toLocaleTimeString('ja-JP') : '';
		const bc: number | string = entry.blockCount || '?';
		const info: HTMLDivElement = document.createElement('div');
		info.className = 'ap-wy-history-item-info';
		info.textContent = '操作 #' + (i + 1);
		const detail: HTMLElement = document.createElement('small');
		detail.className = 'ap-wy-history-item-time';
		detail.textContent = time + '  ブロック: ' + bc;
		item.appendChild(info);
		item.appendChild(detail);
		item.style.cursor = 'pointer';
		const handler = ((idx: number) => (e: Event): void => {
			if (e.type === 'keydown' && (e as KeyboardEvent).key !== 'Enter') return;
			_jumpToSnapshot(idx);
			_closeHistoryPanel();
		})(i);
		item.addEventListener('click', handler);
		item.addEventListener('keydown', handler);
		container.appendChild(item);
	}
}

function _jumpToSnapshot(targetIdx: number): void {
	_syncAllBlocks();
	const currentSnap: string = JSON.stringify(_blocks);
	const moveCount: number = _undoStack.length - targetIdx - 1;
	_redoStack.push({ snap: currentSnap, time: Date.now(), blockCount: _blocks.length });
	for (let i = 0; i < moveCount; i++) {
		_redoStack.push(_undoStack.pop()!);
	}
	const entry = _undoStack.pop()!;
	_restoreSnapshot(entry.snap);
	_setStatus('\u21A9 スナップショット #' + (targetIdx + 1) + ' に戻しました');
	setTimeout((): void => { _setStatus(''); }, 3000);
}

/* ── リビジョンタブ ── */

function _renderRevisionTab(container: HTMLElement, span: HTMLElement): void {
	container.innerHTML = '<div style="padding:16px;color:#999;">読み込み中...</div>';
	const fieldname: string = span ? span.id : '';
	if (!fieldname) {
		container.innerHTML = '<div style="padding:16px;color:#999;">フィールドが不明です</div>';
		return;
	}

	_fetchRevisions(fieldname, (revisions: Revision[]): void => {
		container.innerHTML = '';

		/* D3: 検索バー */
		const searchBar: HTMLDivElement = document.createElement('div');
		searchBar.className = 'ap-wy-history-search';
		const searchInput: HTMLInputElement = document.createElement('input');
		searchInput.type = 'text';
		searchInput.placeholder = 'キーワードで検索...';
		searchInput.setAttribute('aria-label', 'リビジョン検索');
		const searchBtn: HTMLButtonElement = document.createElement('button');
		searchBtn.className = 'ap-wy-history-btn';
		searchBtn.textContent = '検索';
		searchBtn.addEventListener('click', (): void => {
			const q: string = searchInput.value.trim();
			_searchRevisions(fieldname, q, container, span);
		});
		searchInput.addEventListener('keydown', (e: KeyboardEvent): void => {
			if (e.key === 'Enter') searchBtn.click();
		});
		searchBar.appendChild(searchInput);
		searchBar.appendChild(searchBtn);
		container.appendChild(searchBar);

		/* ツールバー */
		const toolbar: HTMLDivElement = document.createElement('div');
		toolbar.style.cssText = 'padding:6px 10px;border-bottom:1px solid #444;display:flex;gap:8px;flex-wrap:wrap;';

		/* 前回保存時との比較 */
		const diffBtn: HTMLButtonElement = document.createElement('button');
		diffBtn.className = 'ap-wy-history-btn';
		diffBtn.textContent = '前回保存時と比較';
		diffBtn.setAttribute('aria-label', '前回保存時と現在の内容を比較');
		diffBtn.addEventListener('click', (): void => {
			_syncAllBlocks();
			const currentHtml: string = _serializeBlocks();
			const oldHtml: string = _lastSaved || '';
			const diff: DiffResult = _computeDiff(_stripTags(oldHtml), _stripTags(currentHtml));
			_renderDiffView(container, diff, null);
		});
		toolbar.appendChild(diffBtn);

		/* D1: 2つのリビジョンを比較ボタン */
		const cmpBtn: HTMLButtonElement = document.createElement('button');
		cmpBtn.className = 'ap-wy-history-btn';
		cmpBtn.textContent = _diffCompareA ? '比較対象A: ' + _diffCompareA.file.replace('rev_','') : '2つを比較';
		cmpBtn.setAttribute('aria-label', '2つのリビジョンを比較');
		if (_diffCompareA) cmpBtn.style.background = '#335';
		toolbar.appendChild(cmpBtn);

		container.appendChild(toolbar);

		if (revisions.length === 0) {
			const msg: HTMLDivElement = document.createElement('div');
			msg.style.cssText = 'padding:16px;color:#999;';
			msg.textContent = '保存されたリビジョンがありません';
			container.appendChild(msg);
			return;
		}

		_renderRevisionList(container, revisions, fieldname, span, cmpBtn);
	});
}

function _renderRevisionList(container: HTMLElement, revisions: Revision[], fieldname: string, span: HTMLElement, cmpBtn: HTMLButtonElement): void {
	revisions.forEach((rev: Revision, idx: number): void => {
		const item: HTMLDivElement = document.createElement('div');
		item.className = 'ap-wy-history-item';
		item.setAttribute('tabindex', '0');
		const d = rev.timestamp ? new Date(rev.timestamp) : null;
		const ts: string = d && !isNaN(d.getTime()) ? d.toLocaleString('ja-JP') : rev.file;
		const kb: string = rev.size ? (rev.size / 1024).toFixed(1) + ' KB' : '';

		const info: HTMLDivElement = document.createElement('div');
		info.style.cssText = 'flex:1;';
		const tsSpan: HTMLSpanElement = document.createElement('span');
		tsSpan.textContent = ts;
		info.appendChild(tsSpan);

		/* C4: 復元マーキング */
		if (rev.restored) {
			const badge: HTMLSpanElement = document.createElement('span');
			badge.className = 'ap-wy-history-item-badge restored';
			badge.textContent = '復元';
			info.appendChild(badge);
		}
		/* D4: ピン留めバッジ */
		if (rev.pinned) {
			const badge: HTMLSpanElement = document.createElement('span');
			badge.className = 'ap-wy-history-item-badge pinned';
			badge.textContent = '\u2605 固定';
			info.appendChild(badge);
		}

		/* D2: ユーザー帰属 */
		const meta: HTMLElement = document.createElement('small');
		meta.className = 'ap-wy-history-item-time';
		let metaText: string = kb;
		if (rev.user) metaText += '  by ' + rev.user;
		meta.textContent = metaText;

		item.appendChild(info);
		item.appendChild(meta);

		/* ボタン群 */
		const btnWrap: HTMLDivElement = document.createElement('div');
		btnWrap.style.cssText = 'margin-top:6px;display:flex;gap:6px;flex-wrap:wrap;';

		const restoreBtn: HTMLButtonElement = document.createElement('button');
		restoreBtn.className = 'ap-wy-history-btn primary';
		restoreBtn.textContent = '復元';
		restoreBtn.setAttribute('aria-label', ts + ' のリビジョンを復元');
		restoreBtn.addEventListener('click', (e: MouseEvent): void => {
			e.stopPropagation();
			/* B3: 未保存変更警告 */
			_syncAllBlocks();
			const currentHtml: string = _serializeBlocks();
			const hasUnsaved: boolean = _lastSaved !== undefined && currentHtml !== _lastSaved;
			const msg: string = hasUnsaved
				? 'このリビジョンを復元しますか？\n\n⚠ 未保存の変更があります。復元すると現在の編集内容は失われます。'
				: 'このリビジョンを復元しますか？現在の内容は上書きされます。';
			if (!confirm(msg)) return;
			_restoreRevision(fieldname, rev.file, span);
		});

		const diffRevBtn: HTMLButtonElement = document.createElement('button');
		diffRevBtn.className = 'ap-wy-history-btn';
		diffRevBtn.textContent = '現在と比較';
		diffRevBtn.setAttribute('aria-label', ts + ' と現在の内容を比較');
		diffRevBtn.addEventListener('click', (e: MouseEvent): void => {
			e.stopPropagation();
			_fetchRevisionContent(fieldname, rev.file, (content: string): void => {
				_syncAllBlocks();
				const currentHtml: string = _serializeBlocks();
				const diff: DiffResult = _computeDiff(_stripTags(content), _stripTags(currentHtml));
				_renderDiffView(container, diff, null);
			});
		});

		/* D4: ピン留めボタン */
		const pinBtn: HTMLButtonElement = document.createElement('button');
		pinBtn.className = 'ap-wy-history-btn';
		pinBtn.textContent = rev.pinned ? '\u2605 固定解除' : '\u2606 固定';
		pinBtn.setAttribute('aria-label', rev.pinned ? 'ピン留め解除' : 'ピン留め');
		pinBtn.addEventListener('click', (e: MouseEvent): void => {
			e.stopPropagation();
			_pinRevision(fieldname, rev.file, (): void => {
				_renderRevisionTab(container, span);
			});
		});

		/* D1: 比較対象選択ボタン */
		const selectBtn: HTMLButtonElement = document.createElement('button');
		selectBtn.className = 'ap-wy-history-btn';
		if (_diffCompareA && _diffCompareA.file === rev.file) {
			selectBtn.textContent = '選択中 (A)';
			selectBtn.style.background = '#335';
		} else if (_diffCompareA) {
			selectBtn.textContent = 'Bとして比較';
		} else {
			selectBtn.textContent = 'Aとして選択';
		}
		selectBtn.addEventListener('click', (e: MouseEvent): void => {
			e.stopPropagation();
			if (!_diffCompareA) {
				_diffCompareA = rev;
				_renderRevisionTab(container, span);
			} else if (_diffCompareA.file === rev.file) {
				_diffCompareA = null;
				_renderRevisionTab(container, span);
			} else {
				/* D1: 2つのリビジョンを比較実行 */
				const revA: Revision = _diffCompareA;
				_fetchRevisionContent(fieldname, revA.file, (contentA: string): void => {
					_fetchRevisionContent(fieldname, rev.file, (contentB: string): void => {
						const diff: DiffResult = _computeDiff(_stripTags(contentA), _stripTags(contentB));
						_diffCompareA = null;
						_renderDiffView(container, diff, null);
					});
				});
			}
		});

		btnWrap.appendChild(restoreBtn);
		btnWrap.appendChild(diffRevBtn);
		btnWrap.appendChild(pinBtn);
		btnWrap.appendChild(selectBtn);
		item.appendChild(btnWrap);
		container.appendChild(item);
	});
}

/* D3: リビジョン検索 */
function _searchRevisions(fieldname: string, query: string, container: HTMLElement, span: HTMLElement): void {
	const csrf = _getCsrf();
	fetch('/', {
		method: 'POST',
		headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': csrf || '' },
		body: new URLSearchParams({ ap_action: 'search_revisions', fieldname, query, csrf: csrf || '' }),
	}).then((r: Response) => r.json() as Promise<Record<string, unknown>>)
	  .then((data: Record<string, unknown>): void => {
		/* 検索バーは維持、リスト部分のみ再描画 */
		const items = container.querySelectorAll('.ap-wy-history-item');
		items.forEach((el: Element) => el.remove());
		const msg = container.querySelector('.ap-wy-history-nomatch');
		if (msg) msg.remove();
		const revisions = (data.revisions || []) as Revision[];
		if (revisions.length === 0) {
			const noMatch: HTMLDivElement = document.createElement('div');
			noMatch.className = 'ap-wy-history-nomatch';
			noMatch.style.cssText = 'padding:16px;color:#999;';
			noMatch.textContent = query ? '「' + query + '」に一致するリビジョンはありません' : 'リビジョンがありません';
			container.appendChild(noMatch);
			return;
		}
		const cmpBtn = container.querySelector('.ap-wy-history-btn[aria-label="2つのリビジョンを比較"]') as HTMLButtonElement;
		_renderRevisionList(container, revisions, fieldname, span, cmpBtn);
	  })
	  .catch((): void => { console.warn('revision search failed'); });
}

/* A1: 全 API を POST に統一（CSRF をヘッダーで送信） */
function _fetchRevisions(fieldname: string, callback: (revisions: Revision[]) => void): void {
	const csrf = _getCsrf();
	fetch('/', {
		method: 'POST',
		headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': csrf || '' },
		body: new URLSearchParams({ ap_action: 'list_revisions', fieldname, csrf: csrf || '' }),
	}).then((r: Response): Promise<Record<string, unknown>> => {
		if (!r.ok) throw new Error('HTTP ' + r.status);
		return r.json() as Promise<Record<string, unknown>>;
	}).then((data: Record<string, unknown>): void => { callback((data.revisions || []) as Revision[]); })
	  .catch((e: Error): void => { console.warn('fetchRevisions:', e.message); callback([]); });
}

/* D5: リビジョンコンテンツ取得専用 API */
function _fetchRevisionContent(fieldname: string, revFile: string, callback: (content: string) => void): void {
	const csrf = _getCsrf();
	fetch('/', {
		method: 'POST',
		headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': csrf || '' },
		body: new URLSearchParams({ ap_action: 'get_revision', fieldname, revision: revFile, csrf: csrf || '' }),
	}).then((r: Response): Promise<Record<string, unknown>> => {
		if (!r.ok) throw new Error('HTTP ' + r.status);
		return r.json() as Promise<Record<string, unknown>>;
	}).then((data: Record<string, unknown>): void => { callback(String(data.content || '')); })
	  .catch((e: Error): void => { console.warn('fetchRevisionContent:', e.message); callback(''); });
}

/* D4: ピン留め切り替え */
function _pinRevision(fieldname: string, revFile: string, callback?: () => void): void {
	const csrf = _getCsrf();
	fetch('/', {
		method: 'POST',
		headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': csrf || '' },
		body: new URLSearchParams({ ap_action: 'pin_revision', fieldname, revision: revFile, csrf: csrf || '' }),
	}).then((r: Response) => r.json() as Promise<Record<string, unknown>>)
	  .then((data: Record<string, unknown>): void => {
		if (data.ok) {
			_setStatus(data.pinned ? '\u2605 固定しました' : '\u2606 固定解除しました');
			setTimeout((): void => { _setStatus(''); }, 2000);
		}
		if (callback) callback();
	  })
	  .catch((e: Error): void => { console.warn('pinRevision:', e.message); if (callback) callback(); });
}

function _restoreRevision(fieldname: string, revFile: string, span: HTMLElement): void {
	const csrf = _getCsrf();
	_setStatus('復元中...');
	fetch('/', {
		method: 'POST',
		headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': csrf || '' },
		body: new URLSearchParams({ ap_action: 'restore_revision', fieldname, revision: revFile, csrf: csrf || '' }),
	}).then((r: Response): Promise<Record<string, unknown>> => {
		if (!r.ok) throw new Error('HTTP ' + r.status);
		return r.json() as Promise<Record<string, unknown>>;
	}).then((data: Record<string, unknown>): void => {
		if (data.ok && data.content != null) {
			/* B2: コンテンツ検証 */
			let blocks: WysiwygBlock[];
			try {
				blocks = _parseHtmlToBlocks(String(data.content));
				if (!Array.isArray(blocks)) throw new Error('invalid blocks');
			} catch (err) {
				_setStatus('\u26A0 復元失敗: コンテンツの解析エラー');
				setTimeout((): void => { _setStatus(''); }, 5000);
				return;
			}
			_saveSnapshot();
			_blocks = blocks;
			_blocksEl!.innerHTML = '';
			_blocks.forEach((b: WysiwygBlock): void => { _blocksEl!.appendChild(_renderBlock(b)); });
			if (_blocks.length > 0) _focusBlock(_blocks[0], 'start');
			_lastSaved = String(data.content);
			_setStatus('\u2713 リビジョンを復元しました');
			_closeHistoryPanel();
			setTimeout((): void => { _setStatus(''); }, 3000);
		} else {
			_setStatus('\u26A0 復元失敗: ' + (data.error || '不明なエラー'));
			setTimeout((): void => { _setStatus(''); }, 5000);
		}
	}).catch((e: Error): void => {
		_setStatus('\u26A0 復元失敗: ' + e.message);
		setTimeout((): void => { _setStatus(''); }, 5000);
	});
}

/* ── 簡易 diff（LCS ベース） ── */

const _DIFF_LINE_LIMIT: number = 2000; /* B4: 大容量コンテンツガード */

function _computeDiff(oldText: string, newText: string): DiffResult {
	const oldLines: string[] = oldText.split('\n');
	const newLines: string[] = newText.split('\n');
	const m: number = oldLines.length;
	const n: number = newLines.length;

	/* B4: 行数が多すぎる場合は簡易比較にフォールバック（通知付き） */
	if (m * n > _DIFF_LINE_LIMIT * _DIFF_LINE_LIMIT) {
		const result: DiffResult = _computeSimpleDiff(oldLines, newLines);
		result._truncated = true;
		return result;
	}

	/* LCS テーブル構築 */
	const dp: number[][] = [];
	for (let i = 0; i <= m; i++) {
		dp[i] = new Array(n + 1).fill(0);
	}
	for (let i = 1; i <= m; i++) {
		for (let j = 1; j <= n; j++) {
			if (oldLines[i - 1] === newLines[j - 1]) {
				dp[i][j] = dp[i - 1][j - 1] + 1;
			} else {
				dp[i][j] = Math.max(dp[i - 1][j], dp[i][j - 1]);
			}
		}
	}

	/* バックトレースで diff 生成 */
	const result: DiffLine[] = [];
	let i: number = m, j: number = n;
	while (i > 0 || j > 0) {
		if (i > 0 && j > 0 && oldLines[i - 1] === newLines[j - 1]) {
			result.unshift({ type: 'equal', text: oldLines[i - 1] });
			i--; j--;
		} else if (j > 0 && (i === 0 || dp[i][j - 1] >= dp[i - 1][j])) {
			result.unshift({ type: 'add', text: newLines[j - 1] });
			j--;
		} else {
			result.unshift({ type: 'remove', text: oldLines[i - 1] });
			i--;
		}
	}
	return result as DiffResult;
}

/* B4: 大容量フォールバック — 行単位の直接比較 */
function _computeSimpleDiff(oldLines: string[], newLines: string[]): DiffResult {
	const result: DiffLine[] = [];
	const max: number = Math.max(oldLines.length, newLines.length);
	for (let i = 0; i < max; i++) {
		const o: string | undefined = i < oldLines.length ? oldLines[i] : undefined;
		const n: string | undefined = i < newLines.length ? newLines[i] : undefined;
		if (o === n) {
			result.push({ type: 'equal', text: o! });
		} else {
			if (o !== undefined) result.push({ type: 'remove', text: o });
			if (n !== undefined) result.push({ type: 'add', text: n });
		}
	}
	return result as DiffResult;
}

function _stripTags(html: string): string {
	/* DOMParser を使用して外部リソース読み込みを防止（blind SSRF 対策） */
	try {
		const doc = new DOMParser().parseFromString(html || '', 'text/html');
		return (doc.body.textContent || '').trim();
	} catch (e) {
		return (html || '').replace(/<[^>]*>/g, '').trim();
	}
}

/* C2: 等行折りたたみ付き diff 表示 */
function _renderDiffView(container: HTMLElement, diff: DiffResult, keepEl: HTMLElement | null): void {
	container.innerHTML = '';
	if (keepEl) container.appendChild(keepEl);

	const backBtn: HTMLButtonElement = document.createElement('button');
	backBtn.className = 'ap-wy-history-btn';
	backBtn.textContent = '\u2190 一覧に戻る';
	backBtn.style.margin = '8px 10px';
	backBtn.setAttribute('aria-label', '一覧に戻る');
	backBtn.addEventListener('click', (): void => {
		if (_historyPanel) {
			const tab = _historyPanel.panel.querySelector('.ap-wy-history-tab.active') as HTMLElement | null;
			if (tab && tab.dataset.tab === 'revision') {
				_renderRevisionTab(container, _historyPanel.span);
			} else {
				_renderSessionHistory(container);
			}
		}
	});
	container.appendChild(backBtn);

	const view: HTMLDivElement = document.createElement('div');
	view.className = 'ap-wy-diff-view';
	view.setAttribute('role', 'log');
	view.setAttribute('aria-label', '変更差分');

	let hasChanges = false;
	const FOLD_THRESHOLD = 4;

	/* C2: 等行を折りたたみ */
	let i = 0;
	while (i < diff.length) {
		const d: DiffLine = diff[i];
		if (d.type !== 'equal') {
			const line: HTMLDivElement = document.createElement('div');
			/* C3: 色覚多様性対応 — プレフィックス記号で区別 */
			if (d.type === 'add') {
				line.className = 'ap-wy-diff-add';
				line.textContent = '+ ' + d.text;
			} else {
				line.className = 'ap-wy-diff-del';
				line.textContent = '- ' + d.text;
			}
			view.appendChild(line);
			hasChanges = true;
			i++;
		} else {
			/* 連続する equal 行をカウント */
			let eqStart: number = i;
			while (i < diff.length && diff[i].type === 'equal') i++;
			const eqCount: number = i - eqStart;
			if (eqCount <= FOLD_THRESHOLD) {
				for (let k = eqStart; k < i; k++) {
					const line: HTMLDivElement = document.createElement('div');
					line.className = 'ap-wy-diff-eq';
					line.textContent = '  ' + diff[k].text;
					view.appendChild(line);
				}
			} else {
				/* 前後1行は表示、中間は折りたたみ */
				const firstLine: HTMLDivElement = document.createElement('div');
				firstLine.className = 'ap-wy-diff-eq';
				firstLine.textContent = '  ' + diff[eqStart].text;
				view.appendChild(firstLine);

				const foldCount: number = eqCount - 2;
				const fold: HTMLDivElement = document.createElement('div');
				fold.className = 'ap-wy-diff-fold';
				fold.textContent = '\u2026 ' + foldCount + ' 行省略（クリックで展開）';
				fold.setAttribute('role', 'button');
				fold.setAttribute('tabindex', '0');
				const foldLines: string[] = [];
				for (let k = eqStart + 1; k < i - 1; k++) {
					foldLines.push(diff[k].text);
				}
				fold.addEventListener('click', (): void => {
					const expanded = document.createDocumentFragment();
					foldLines.forEach((t: string): void => {
						const line: HTMLDivElement = document.createElement('div');
						line.className = 'ap-wy-diff-eq';
						line.textContent = '  ' + t;
						expanded.appendChild(line);
					});
					fold.replaceWith(expanded);
				});
				fold.addEventListener('keydown', (e: KeyboardEvent): void => { if (e.key === 'Enter') fold.click(); });
				view.appendChild(fold);

				const lastLine: HTMLDivElement = document.createElement('div');
				lastLine.className = 'ap-wy-diff-eq';
				lastLine.textContent = '  ' + diff[i - 1].text;
				view.appendChild(lastLine);
			}
		}
	}

	if (!hasChanges) {
		view.innerHTML = '<div style="padding:16px;color:#999;">変更はありません</div>';
	}
	if (diff._truncated) {
		const notice: HTMLDivElement = document.createElement('div');
		notice.style.cssText = 'padding:8px 10px;font-size:12px;color:#e2a308;background:rgba(226,163,8,.1);border-radius:4px;margin-top:4px;';
		notice.textContent = '⚠ 大容量コンテンツのため簡易比較で表示しています（行単位の直接比較）';
		view.appendChild(notice);
	}

	container.appendChild(view);
}

/* ══════════════════════════════════════════════
   保存 / 自動保存 / キャンセル
   ══════════════════════════════════════════════ */

function _manualSave(span: HTMLElement): void {
	if (!_active) return;
	_stopAutoSave();
	_active = false;
	_hideSlashMenu();
	_hideTypePopup();
	_hideInlineToolbar();
	document.removeEventListener('selectionchange', _onSelectionChange);
	if (_docHandler) { document.removeEventListener('mousedown', _docHandler); _docHandler = null; }
	if (_inlineToolbar) { _inlineToolbar.remove(); _inlineToolbar = null; }
	if (typeof window._apChanging !== 'undefined') window._apChanging = false;

	/* ブロックからHTML生成 */
	_syncAllBlocks();
	const html: string = _cleanHtml(_serializeBlocks());

	_currentSpan = null;
	_blocksEl = null;
	_dropLine = null;
	_blocks = [];
	span.innerHTML = html || (span.getAttribute('title') || '');
	if (typeof window._apFieldSave === 'function') window._apFieldSave(span.id, html);
	else console.error('[AP WYSIWYG] _apFieldSave not available');
	_emitAEB('editor:save', { fieldId: span.id, html: html });
}

function _cancel(span: HTMLElement, originalHtml: string): void {
	if (!_active) return;
	_stopAutoSave();
	_active = false;
	_hideSlashMenu();
	_hideTypePopup();
	_hideInlineToolbar();
	document.removeEventListener('selectionchange', _onSelectionChange);
	if (_docHandler) { document.removeEventListener('mousedown', _docHandler); _docHandler = null; }
	if (_inlineToolbar) { _inlineToolbar.remove(); _inlineToolbar = null; }
	if (typeof window._apChanging !== 'undefined') window._apChanging = false;

	_currentSpan = null;
	_blocksEl = null;
	_dropLine = null;
	_blocks = [];
	span.innerHTML = originalHtml;
}

let _autoSaving: boolean = false;

function _startAutoSave(fieldId: string): void {
	_autoTimer = setInterval((): void => {
		if (!_active || !_blocksEl || _autoSaving) return;
		_syncAllBlocks();
		const html: string = _cleanHtml(_serializeBlocks());
		if (html === _lastSaved) return;
		_autoSaving = true;
		_setStatus('保存中...');
		_fetchSave(fieldId, html, (ok: boolean): void => {
			_autoSaving = false;
			if (ok) {
				_lastSaved = html;
				_setStatus('✓ 自動保存済み');
				setTimeout((): void => { _setStatus(''); }, 3000);
				_emitAEB('editor:autosave', { fieldId: fieldId, html: html });
			} else {
				_setStatus('⚠ 自動保存失敗');
				_emitAEB('editor:autosave:error', { fieldId: fieldId });
			}
		});
	}, 30000);
}

function _stopAutoSave(): void {
	if (_autoTimer) { clearInterval(_autoTimer); _autoTimer = null; }
	_statusEl = null;
}

function _setStatus(msg: string): void {
	if (_statusEl) _statusEl.textContent = msg;
}

/* 全ブロックの内部データを DOM から同期 */
function _syncAllBlocks(): void {
	_blocks.forEach((block: WysiwygBlock): void => {
		const el = _getBlockEl(block);
		if (!el) return;

		if (['paragraph','heading','quote'].includes(block.type)) {
			const c = el.querySelector('.ap-wy-block-content') as HTMLElement | null;
			if (c) { block.data.text = c.innerHTML.trim(); if (block.data.text === '<br>') block.data.text = ''; }
		} else if (block.type === 'code') {
			const c = el.querySelector('.ap-wy-block-content') as HTMLElement | null;
			if (c) block.data.text = c.textContent || '';
		} else if (block.type === 'list') {
			const list = el.querySelector('ul,ol') as HTMLElement | null;
			if (list) _syncListData(list, block);
		}
		/* table, image, checklist はリアルタイムでdata更新済み */
	});
}

/* ── Fetch ヘルパー ── */
function _fetchSave(key: string, val: string, callback?: (ok: boolean) => void): void {
	const csrf = _getCsrf();
	if (!csrf) { if (callback) callback(false); return; }
	fetch('/', {
		method: 'POST',
		headers: {
			'Content-Type': 'application/x-www-form-urlencoded',
			'X-CSRF-TOKEN': csrf,
		},
		body: new URLSearchParams({ ap_action: 'edit_field', fieldname: key, content: val, csrf }),
	}).then((r: Response): void => { if (callback) callback(r.ok); })
	  .catch((): void => { if (callback) callback(false); });
}

/* ══════════════════════════════════════════════
   HTML サニタイズ
   ══════════════════════════════════════════════ */

/* C14 fix: template 要素で解析（parse-time XSS 防止） */
function _cleanHtml(html: string): string {
	const tpl = document.createElement('template');
	tpl.innerHTML = html;
	const tmp = document.createElement('div');
	tmp.appendChild(tpl.content.cloneNode(true));
	_sanitizeNode(tmp);
	return tmp.innerHTML;
}

function _sanitizeNode(node: Node): void {
	/* 不許可タグをフラット化（O(n)化: 再帰リスタート回避） */
	let changed = true;
	while (changed) {
		changed = false;
		const children = Array.from(node.childNodes);
		for (const child of children) {
			if (child.nodeType !== 1) continue;
			if (!_allowedTags[(child as Element).tagName]) {
				const frag = document.createDocumentFragment();
				Array.from(child.childNodes).forEach((c: ChildNode) => frag.appendChild(c));
				node.replaceChild(frag, child);
				changed = true;
				break;
			}
		}
	}
	Array.from(node.childNodes).forEach((child: ChildNode): void => {
		if (child.nodeType !== 1) return;
		const el = child as Element;
		/* 属性フィルタ */
		Array.from(el.attributes).forEach((attr: Attr): void => {
			const tag: string = el.tagName;
			const keep: boolean =
				(tag === 'A' && attr.name === 'href') ||
				(tag === 'IMG' && (attr.name === 'src' || attr.name === 'alt')) ||
				(tag === 'IMG' && attr.name === 'style') ||
				(tag === 'UL' && attr.name === 'class') ||
				(tag === 'LI' && attr.name === 'class') ||
				(tag === 'FIGURE' && attr.name === 'style') ||
				(['P','H2','H3','BLOCKQUOTE','DIV'].includes(tag) && attr.name === 'style');
			if (!keep) el.removeAttribute(attr.name);
		});
		/* style属性: text-align のみ許可 */
		if (el.hasAttribute('style')) {
			const style: string = el.getAttribute('style') || '';
			const match = style.match(/text-align\s*:\s*(left|center|right)/);
			const widthMatch = style.match(/width\s*:\s*(\d+%)/);
			const allowed: string[] = [];
			if (match) allowed.push(`text-align:${match[1]}`);
			if (widthMatch) allowed.push(`width:${widthMatch[1]}`);
			if (allowed.length) el.setAttribute('style', allowed.join(';'));
			else el.removeAttribute('style');
		}
		/* 危険スキーム除去 */
		if (el.tagName === 'A') {
			const href: string = el.getAttribute('href') || '';
			if (!_isSafeUrl(href)) el.removeAttribute('href');
		}
		if (el.tagName === 'IMG') {
			const src: string = (el.getAttribute('src') || '').trim().toLowerCase();
			/* C16 fix: javascript/vbscript スキーム + data:image/svg+xml もブロック */
			if (/^(javascript|vbscript):/.test(src) ||
				(/^data:/.test(src) && !/^data:image\/(png|jpeg|gif|webp)(;|,)/.test(src))) {
				el.removeAttribute('src');
			}
		}
		_sanitizeNode(child);
	});
}

})();
