# エディタ & CSS フレームワーク化設計書

**Adlaire Editor Framework (AEF) & Adlaire CSS Framework (ACF)**

**日付**: 2026年3月14日  
**目的**: WYSIWYG エディタとCSSの専用フレームワーク化  
**ステータス**: 設計フェーズ

---

## 📋 目次

1. [エグゼクティブサマリー](#エグゼクティブサマリー)
2. [現状分析](#現状分析)
3. [Adlaire Editor Framework (AEF) 設計](#aef設計)
4. [Adlaire CSS Framework (ACF) 設計](#acf設計)
5. [実装計画](#実装計画)
6. [期待効果](#期待効果)

---

## エグゼクティブサマリー

### 背景

**現状の課題**:
- WYSIWYGエディタ: 2,889行の巨大なモノリシックファイル
- CSS: JS内にインライン注入（約200行）、保守性低下
- JavaScript: 13ファイル、約4,910行、統一されたアーキテクチャなし

**提案**:
1. **Adlaire Editor Framework (AEF)** - WYSIWYGエディタ専用フレームワーク
2. **Adlaire CSS Framework (ACF)** - 統一CSSフレームワーク

### 目標

| 指標 | 現状 | 目標 | 改善率 |
|-----|------|------|-------|
| エディタファイル数 | 1ファイル | 30+ファイル | - |
| 最大ファイルサイズ | 2,889行 | <300行 | **90%削減** |
| CSS管理 | JS内注入 | 独立CSSファイル | **100%分離** |
| 保守性 | 低 | 高 | **大幅改善** |
| テストカバレッジ | 0% | 80%+ | **∞** |
| 新機能追加時間 | 4-8h | 1-2h | **75%削減** |

---

## 現状分析

### JavaScript現状

**ファイル構成**:
```
engines/JsEngine/
├── wysiwyg.js (2,889行) ← 巨大モノリス
├── dashboard.js
├── editInplace.js
├── collection_manager.js
├── git_manager.js
├── webhook_manager.js
├── api_keys.js
├── diagnostics.js
├── updater.js
├── static_builder.js
├── ap-api-client.js
├── ap-search.js
└── autosize.js
```

**合計**: 13ファイル、約4,910行

### CSS現状

**ファイル構成**:
```
CSS散在状況:
├── engines/AdminEngine/dashboard.css (615行)
├── themes/AP-Adlaire/style.css (181行)
├── themes/AP-Default/style.css (181行)
└── wysiwyg.js内 (約200行) ← JS内に埋め込み
```

**合計**: 3+1ファイル、約977+200行

### 主な問題点

1. **モノリシック構造** - wysiwyg.js が2,889行
2. **CSS散在** - JS内注入、テーマファイル、エンジンファイルにバラバラ
3. **保守性低下** - コードの見通しが悪い
4. **再利用不可** - 他プロジェクトで使えない
5. **テスト困難** - 単体テストが書けない

---

## AEF設計

### Adlaire Editor Framework (AEF)

**コンセプト**: モダンでモジュラーなブロックベースWYSIWYGエディタフレームワーク

### アーキテクチャ

```
framework/editor/
├── index.js (エントリーポイント)
├── core/
│   ├── Editor.js (エディタコアクラス)
│   ├── BlockRegistry.js (ブロック登録システム)
│   ├── ToolRegistry.js (ツール登録システム)
│   ├── EventBus.js (イベントバス)
│   ├── StateManager.js (状態管理)
│   └── HistoryManager.js (Undo/Redo)
├── blocks/
│   ├── BaseBlock.js (基底ブロッククラス)
│   ├── ParagraphBlock.js
│   ├── HeadingBlock.js
│   ├── ListBlock.js
│   ├── CodeBlock.js
│   ├── QuoteBlock.js
│   ├── TableBlock.js
│   ├── ImageBlock.js
│   ├── ChecklistBlock.js
│   └── DelimiterBlock.js
├── tools/
│   ├── BaseTool.js (基底ツールクラス)
│   ├── InlineToolbar.js
│   ├── SlashCommands.js
│   ├── BlockHandle.js
│   └── BlockTunes.js
├── utils/
│   ├── sanitizer.js (HTMLサニタイザー)
│   ├── dom.js (DOM操作ユーティリティ)
│   ├── keyboard.js (キーボードハンドラ)
│   ├── selection.js (選択範囲管理)
│   └── clipboard.js (クリップボード)
├── plugins/
│   └── README.md (プラグインAPI)
└── types/
    └── index.d.ts (TypeScript型定義)
```

### コアAPI設計

#### Editor クラス

```javascript
// framework/editor/core/Editor.js
export class AEFEditor {
    constructor(container, options = {}) {
        this.container = container;
        this.options = this.mergeOptions(options);
        
        // コア機能
        this.blockRegistry = new BlockRegistry();
        this.toolRegistry = new ToolRegistry();
        this.eventBus = new EventBus();
        this.stateManager = new StateManager();
        this.historyManager = new HistoryManager();
        
        // 状態
        this.blocks = [];
        this.currentBlockId = null;
        this.isReady = false;
        
        this.init();
    }
    
    init() {
        this.registerDefaultBlocks();
        this.registerDefaultTools();
        this.render();
        this.attachEvents();
        
        this.isReady = true;
        this.eventBus.emit('ready');
    }
    
    // ブロック操作
    addBlock(type, data = {}, position = null) {
        const BlockClass = this.blockRegistry.get(type);
        if (!BlockClass) {
            throw new Error(`Block type "${type}" not registered`);
        }
        
        const block = new BlockClass(this, data);
        
        if (position === null) {
            this.blocks.push(block);
        } else {
            this.blocks.splice(position, 0, block);
        }
        
        this.renderBlock(block);
        this.historyManager.saveState();
        this.eventBus.emit('block:added', { block });
        
        return block;
    }
    
    removeBlock(blockId) {
        const index = this.blocks.findIndex(b => b.id === blockId);
        if (index === -1) return false;
        
        const block = this.blocks[index];
        this.blocks.splice(index, 1);
        
        block.destroy();
        this.historyManager.saveState();
        this.eventBus.emit('block:removed', { blockId });
        
        return true;
    }
    
    // データ操作
    getData() {
        return {
            time: Date.now(),
            version: '1.0.0',
            blocks: this.blocks.map(block => block.serialize())
        };
    }
    
    setData(data) {
        this.clear();
        
        if (data.blocks) {
            data.blocks.forEach(blockData => {
                this.addBlock(blockData.type, blockData.data);
            });
        }
    }
    
    clear() {
        this.blocks.forEach(block => block.destroy());
        this.blocks = [];
        this.render();
    }
    
    // 保存
    async save() {
        const data = this.getData();
        this.eventBus.emit('save:start', { data });
        
        try {
            const response = await this.options.onSave(data);
            this.eventBus.emit('save:success', { response });
            return response;
        } catch (error) {
            this.eventBus.emit('save:error', { error });
            throw error;
        }
    }
    
    // Undo/Redo
    undo() {
        const state = this.historyManager.undo();
        if (state) {
            this.setData(state);
            this.eventBus.emit('history:undo');
        }
    }
    
    redo() {
        const state = this.historyManager.redo();
        if (state) {
            this.setData(state);
            this.eventBus.emit('history:redo');
        }
    }
    
    // プラグインAPI
    registerBlock(name, BlockClass) {
        this.blockRegistry.register(name, BlockClass);
    }
    
    registerTool(name, ToolClass) {
        this.toolRegistry.register(name, ToolClass);
    }
    
    // イベントAPI
    on(event, callback) {
        return this.eventBus.on(event, callback);
    }
    
    off(eventId) {
        this.eventBus.off(eventId);
    }
    
    emit(event, data) {
        this.eventBus.emit(event, data);
    }
    
    // 破棄
    destroy() {
        this.blocks.forEach(block => block.destroy());
        this.eventBus.emit('destroy');
        this.container.innerHTML = '';
    }
}
```

#### BaseBlock クラス

```javascript
// framework/editor/blocks/BaseBlock.js
export class BaseBlock {
    constructor(editor, data = {}) {
        this.editor = editor;
        this.id = this.generateId();
        this.type = 'base';
        this.data = this.sanitizeData(data);
        this.element = null;
        this.contentElement = null;
    }
    
    // ID生成
    generateId() {
        return 'block-' + Date.now() + '-' + Math.random().toString(36).slice(2, 11);
    }
    
    // データサニタイズ（オーバーライド可能）
    sanitizeData(data) {
        return { ...data };
    }
    
    // レンダリング
    render() {
        this.element = document.createElement('div');
        this.element.className = 'aef-block';
        this.element.dataset.blockType = this.type;
        this.element.dataset.blockId = this.id;
        
        const wrapper = document.createElement('div');
        wrapper.className = 'aef-block-wrapper';
        
        const handle = this.createHandle();
        const content = this.createContent();
        const tunes = this.createTunes();
        
        wrapper.appendChild(handle);
        wrapper.appendChild(content);
        if (tunes) {
            wrapper.appendChild(tunes);
        }
        
        this.element.appendChild(wrapper);
        
        this.attachEvents();
        
        return this.element;
    }
    
    // ハンドル作成
    createHandle() {
        const handle = document.createElement('div');
        handle.className = 'aef-block-handle';
        handle.innerHTML = '⠿';
        handle.draggable = true;
        handle.setAttribute('aria-label', 'ドラッグしてブロックを移動');
        
        handle.addEventListener('click', (e) => {
            this.showTypeMenu(e);
        });
        
        return handle;
    }
    
    // コンテンツ作成（サブクラスで実装）
    createContent() {
        throw new Error('createContent() must be implemented by subclass');
    }
    
    // Block Tunes作成（オプション）
    createTunes() {
        const tunes = document.createElement('div');
        tunes.className = 'aef-block-tunes';
        
        // デフォルトTunes: テキスト配置
        const alignments = ['left', 'center', 'right'];
        alignments.forEach(align => {
            const btn = document.createElement('button');
            btn.className = 'aef-tune-btn';
            btn.dataset.tune = 'align';
            btn.dataset.value = align;
            btn.innerHTML = this.getAlignIcon(align);
            btn.title = `${align}揃え`;
            
            btn.addEventListener('click', () => {
                this.setAlignment(align);
            });
            
            tunes.appendChild(btn);
        });
        
        return tunes;
    }
    
    // イベントアタッチ
    attachEvents() {
        // サブクラスでオーバーライド
    }
    
    // シリアライズ
    serialize() {
        return {
            id: this.id,
            type: this.type,
            data: this.data
        };
    }
    
    // デシリアライズ（静的メソッド）
    static deserialize(editor, data) {
        return new this(editor, data.data);
    }
    
    // フォーカス
    focus() {
        if (this.contentElement) {
            this.contentElement.focus();
        }
    }
    
    // バリデーション
    validate() {
        return true; // サブクラスでオーバーライド
    }
    
    // 破棄
    destroy() {
        if (this.element) {
            this.element.remove();
        }
    }
    
    // ユーティリティ
    getAlignIcon(align) {
        const icons = {
            left: '⬅',
            center: '↔',
            right: '➡'
        };
        return icons[align] || '';
    }
    
    setAlignment(align) {
        this.data.align = align;
        this.element.dataset.align = align;
        this.editor.historyManager.saveState();
    }
    
    showTypeMenu(e) {
        // ブロックタイプ変換メニュー表示
        this.editor.emit('block:showTypeMenu', {
            block: this,
            position: { x: e.clientX, y: e.clientY }
        });
    }
}
```

#### ParagraphBlock 実装例

```javascript
// framework/editor/blocks/ParagraphBlock.js
import { BaseBlock } from './BaseBlock.js';
import { sanitizeHtml } from '../utils/sanitizer.js';

export class ParagraphBlock extends BaseBlock {
    constructor(editor, data = {}) {
        super(editor, data);
        this.type = 'paragraph';
    }
    
    sanitizeData(data) {
        return {
            text: sanitizeHtml(data.text || ''),
            align: data.align || 'left'
        };
    }
    
    createContent() {
        this.contentElement = document.createElement('div');
        this.contentElement.className = 'aef-block-content';
        this.contentElement.contentEditable = 'true';
        this.contentElement.innerHTML = this.data.text;
        this.contentElement.setAttribute('role', 'textbox');
        this.contentElement.setAttribute('aria-label', '段落');
        
        if (!this.data.text) {
            this.contentElement.dataset.placeholder = '/ を入力してコマンド...';
        }
        
        return this.contentElement;
    }
    
    attachEvents() {
        // 入力イベント
        this.contentElement.addEventListener('input', (e) => {
            this.data.text = e.target.innerHTML;
            
            // スラッシュコマンド検出
            if (e.target.textContent === '/') {
                this.showSlashMenu();
            }
            
            // 自動保存トリガー
            this.editor.emit('content:changed');
        });
        
        // キーボードイベント
        this.contentElement.addEventListener('keydown', (e) => {
            this.handleKeyDown(e);
        });
        
        // ペーストイベント
        this.contentElement.addEventListener('paste', (e) => {
            this.handlePaste(e);
        });
    }
    
    handleKeyDown(e) {
        // Enter: 新規段落作成
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            
            const selection = window.getSelection();
            const range = selection.getRangeAt(0);
            
            // カーソル位置で分割
            const beforeContent = this.getContentBefore(range);
            const afterContent = this.getContentAfter(range);
            
            this.data.text = beforeContent;
            this.contentElement.innerHTML = beforeContent;
            
            // 新しい段落を追加
            const index = this.editor.blocks.indexOf(this);
            const newBlock = this.editor.addBlock('paragraph', { text: afterContent }, index + 1);
            newBlock.focus();
        }
        
        // Backspace: 空なら削除
        if (e.key === 'Backspace' && this.isEmpty() && this.isAtStart()) {
            e.preventDefault();
            const index = this.editor.blocks.indexOf(this);
            if (index > 0) {
                this.editor.removeBlock(this.id);
                this.editor.blocks[index - 1].focus();
            }
        }
        
        // Tab: インデント（リストの場合）
        if (e.key === 'Tab') {
            e.preventDefault();
            // 実装
        }
    }
    
    handlePaste(e) {
        e.preventDefault();
        
        const text = e.clipboardData.getData('text/plain');
        const html = e.clipboardData.getData('text/html');
        
        if (html) {
            // HTMLペースト
            const sanitized = sanitizeHtml(html);
            document.execCommand('insertHTML', false, sanitized);
        } else {
            // プレーンテキストペースト
            document.execCommand('insertText', false, text);
        }
    }
    
    showSlashMenu() {
        this.editor.emit('slashMenu:show', {
            block: this,
            position: this.getCaretPosition()
        });
    }
    
    isEmpty() {
        return this.contentElement.textContent.trim() === '';
    }
    
    isAtStart() {
        const selection = window.getSelection();
        if (!selection.rangeCount) return false;
        const range = selection.getRangeAt(0);
        return range.startOffset === 0;
    }
    
    getContentBefore(range) {
        const beforeRange = range.cloneRange();
        beforeRange.selectNodeContents(this.contentElement);
        beforeRange.setEnd(range.startContainer, range.startOffset);
        return beforeRange.toString();
    }
    
    getContentAfter(range) {
        const afterRange = range.cloneRange();
        afterRange.selectNodeContents(this.contentElement);
        afterRange.setStart(range.endContainer, range.endOffset);
        return afterRange.toString();
    }
    
    getCaretPosition() {
        const selection = window.getSelection();
        if (!selection.rangeCount) return null;
        
        const range = selection.getRangeAt(0);
        const rect = range.getBoundingClientRect();
        
        return {
            x: rect.left,
            y: rect.bottom
        };
    }
    
    validate() {
        // 空の段落は警告
        if (this.isEmpty()) {
            return {
                valid: true,
                warning: '空の段落があります'
            };
        }
        return { valid: true };
    }
}
```

### プラグインシステム

```javascript
// framework/editor/plugins/README.md に記載するAPI例

/**
 * プラグインAPI
 */

// プラグインの基本構造
const myPlugin = {
    name: 'my-plugin',
    version: '1.0.0',
    
    // 初期化
    init(editor) {
        console.log('Plugin initialized');
        
        // カスタムブロックを登録
        editor.registerBlock('custom-block', CustomBlock);
        
        // カスタムツールを登録
        editor.registerTool('custom-tool', CustomTool);
        
        // イベントリスナー
        editor.on('block:added', ({ block }) => {
            console.log('Block added:', block);
        });
    },
    
    // 破棄
    destroy() {
        console.log('Plugin destroyed');
    }
};

// プラグイン使用例
const editor = new AEFEditor('#editor', {
    plugins: [myPlugin]
});
```

---

## ACF設計

### Adlaire CSS Framework (ACF)

**コンセプト**: モダンで軽量、カスタマイズ可能なCSSフレームワーク

### アーキテクチャ

```
framework/css/
├── index.css (メインエントリー)
├── base/
│   ├── reset.css (リセットCSS)
│   ├── variables.css (CSS変数)
│   ├── typography.css (タイポグラフィ)
│   └── utilities.css (ユーティリティクラス)
├── components/
│   ├── buttons.css
│   ├── forms.css
│   ├── cards.css
│   ├── modals.css
│   ├── tooltips.css
│   └── alerts.css
├── layout/
│   ├── grid.css (グリッドシステム)
│   ├── flexbox.css (Flexboxユーティリティ)
│   ├── container.css
│   └── spacing.css
├── editor/
│   ├── editor-base.css (エディタベーススタイル)
│   ├── blocks.css (ブロックスタイル)
│   ├── toolbar.css (ツールバースタイル)
│   └── themes/
│       ├── light.css
│       ├── dark.css (デフォルト)
│       └── custom.css
└── themes/
    ├── adlaire.css
    └── default.css
```

### CSS変数システム

```css
/* framework/css/base/variables.css */
:root {
    /* ── カラーシステム ── */
    --acf-primary: #0ad;
    --acf-secondary: #666;
    --acf-success: #4c4;
    --acf-danger: #c44;
    --acf-warning: #fc0;
    --acf-info: #6cf;
    
    /* ── グレースケール ── */
    --acf-gray-50: #fafafa;
    --acf-gray-100: #f5f5f5;
    --acf-gray-200: #e5e5e5;
    --acf-gray-300: #d4d4d4;
    --acf-gray-400: #a3a3a3;
    --acf-gray-500: #737373;
    --acf-gray-600: #525252;
    --acf-gray-700: #404040;
    --acf-gray-800: #262626;
    --acf-gray-900: #171717;
    
    /* ── 背景・テキスト ── */
    --acf-bg-primary: #1a1a1a;
    --acf-bg-secondary: #2a2a2a;
    --acf-bg-tertiary: #333;
    --acf-text-primary: #eee;
    --acf-text-secondary: #aaa;
    --acf-text-muted: #666;
    
    /* ── ボーダー ── */
    --acf-border: #555;
    --acf-border-light: #444;
    --acf-border-focus: var(--acf-primary);
    
    /* ── スペーシング ── */
    --acf-space-xs: 0.25rem;  /* 4px */
    --acf-space-sm: 0.5rem;   /* 8px */
    --acf-space-md: 1rem;     /* 16px */
    --acf-space-lg: 1.5rem;   /* 24px */
    --acf-space-xl: 2rem;     /* 32px */
    --acf-space-2xl: 3rem;    /* 48px */
    
    /* ── タイポグラフィ ── */
    --acf-font-sans: system-ui, -apple-system, sans-serif;
    --acf-font-mono: 'SF Mono', Monaco, 'Cascadia Code', monospace;
    --acf-font-size-xs: 0.75rem;   /* 12px */
    --acf-font-size-sm: 0.875rem;  /* 14px */
    --acf-font-size-base: 1rem;    /* 16px */
    --acf-font-size-lg: 1.125rem;  /* 18px */
    --acf-font-size-xl: 1.25rem;   /* 20px */
    --acf-font-size-2xl: 1.5rem;   /* 24px */
    --acf-font-size-3xl: 1.875rem; /* 30px */
    
    /* ── 角丸 ── */
    --acf-radius-sm: 3px;
    --acf-radius-md: 6px;
    --acf-radius-lg: 8px;
    --acf-radius-xl: 12px;
    --acf-radius-full: 9999px;
    
    /* ── シャドウ ── */
    --acf-shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.3);
    --acf-shadow-md: 0 4px 6px rgba(0, 0, 0, 0.3);
    --acf-shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.4);
    --acf-shadow-xl: 0 20px 25px rgba(0, 0, 0, 0.5);
    
    /* ── トランジション ── */
    --acf-transition-fast: 150ms ease;
    --acf-transition-base: 200ms ease;
    --acf-transition-slow: 300ms ease;
    
    /* ── Z-index ── */
    --acf-z-dropdown: 1000;
    --acf-z-modal: 2000;
    --acf-z-tooltip: 3000;
}

/* ライトテーマ */
[data-theme="light"] {
    --acf-bg-primary: #ffffff;
    --acf-bg-secondary: #f5f5f5;
    --acf-bg-tertiary: #e5e5e5;
    --acf-text-primary: #171717;
    --acf-text-secondary: #404040;
    --acf-text-muted: #737373;
    --acf-border: #d4d4d4;
    --acf-border-light: #e5e5e5;
}
```

### エディタ専用スタイル

```css
/* framework/css/editor/editor-base.css */

/* ── エディタラッパー ── */
.aef-editor {
    position: relative;
    min-height: 200px;
    font-family: var(--acf-font-sans);
    font-size: var(--acf-font-size-base);
    line-height: 1.6;
    color: var(--acf-text-primary);
    background: var(--acf-bg-primary);
}

/* ── ツールバー ── */
.aef-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: var(--acf-space-xs);
    padding: var(--acf-space-sm);
    background: var(--acf-bg-tertiary);
    border-radius: var(--acf-radius-md) var(--acf-radius-md) 0 0;
    user-select: none;
    border: 1px solid var(--acf-border);
    border-bottom: none;
}

.aef-toolbar-btn {
    padding: var(--acf-space-xs) var(--acf-space-sm);
    font-size: var(--acf-font-size-sm);
    color: var(--acf-text-primary);
    background: var(--acf-bg-secondary);
    border: 1px solid var(--acf-border);
    border-radius: var(--acf-radius-sm);
    cursor: pointer;
    transition: all var(--acf-transition-fast);
}

.aef-toolbar-btn:hover {
    background: var(--acf-bg-primary);
    border-color: var(--acf-border-focus);
}

.aef-toolbar-btn.active {
    background: var(--acf-primary);
    color: #000;
    border-color: var(--acf-primary);
}

.aef-toolbar-sep {
    width: 1px;
    height: 20px;
    background: var(--acf-border);
}

/* ── ブロックコンテナ ── */
.aef-blocks {
    min-height: 150px;
    padding: var(--acf-space-md) var(--acf-space-md) var(--acf-space-md) 40px;
    background: var(--acf-bg-secondary);
    border: 2px solid var(--acf-border);
    border-radius: 0 0 var(--acf-radius-md) var(--acf-radius-md);
}

.aef-blocks:focus-within {
    border-color: var(--acf-border-focus);
}

/* ── ブロック ── */
.aef-block {
    position: relative;
    margin: var(--acf-space-sm) 0;
    border-radius: var(--acf-radius-sm);
    transition: background var(--acf-transition-fast);
}

.aef-block:hover {
    background: rgba(255, 255, 255, 0.03);
}

.aef-block-wrapper {
    position: relative;
}

.aef-block-content {
    min-height: 1.5em;
    padding: var(--acf-space-xs);
    outline: none;
    word-break: break-word;
    overflow-wrap: break-word;
}

.aef-block-content:focus {
    background: rgba(0, 221, 255, 0.05);
    border-radius: var(--acf-radius-sm);
}

/* ── ブロックハンドル ── */
.aef-block-handle {
    position: absolute;
    left: -32px;
    top: 2px;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: var(--acf-font-size-sm);
    color: var(--acf-text-muted);
    cursor: grab;
    opacity: 0;
    transition: opacity var(--acf-transition-fast);
    border-radius: var(--acf-radius-sm);
}

.aef-block:hover .aef-block-handle,
.aef-block-handle:hover {
    opacity: 1;
}

.aef-block-handle:hover {
    background: rgba(255, 255, 255, 0.1);
    color: var(--acf-text-primary);
}

.aef-block-handle:active {
    cursor: grabbing;
}

/* ── プレースホルダー ── */
.aef-block-content[data-placeholder]:empty::before {
    content: attr(data-placeholder);
    color: var(--acf-text-muted);
    pointer-events: none;
    font-style: italic;
}

/* ── レスポンシブ ── */
@media (max-width: 768px) {
    .aef-toolbar {
        padding: var(--acf-space-xs);
    }
    
    .aef-toolbar-btn {
        min-width: 44px;
        min-height: 44px;
    }
    
    .aef-blocks {
        padding: var(--acf-space-sm);
    }
    
    .aef-block-handle {
        left: -28px;
        width: 20px;
        height: 20px;
    }
}
```

### ユーティリティクラス

```css
/* framework/css/base/utilities.css */

/* ── Spacing ── */
.acf-m-0 { margin: 0; }
.acf-m-1 { margin: var(--acf-space-xs); }
.acf-m-2 { margin: var(--acf-space-sm); }
.acf-m-3 { margin: var(--acf-space-md); }
.acf-m-4 { margin: var(--acf-space-lg); }

.acf-p-0 { padding: 0; }
.acf-p-1 { padding: var(--acf-space-xs); }
.acf-p-2 { padding: var(--acf-space-sm); }
.acf-p-3 { padding: var(--acf-space-md); }
.acf-p-4 { padding: var(--acf-space-lg); }

/* ── Display ── */
.acf-block { display: block; }
.acf-inline { display: inline; }
.acf-inline-block { display: inline-block; }
.acf-flex { display: flex; }
.acf-grid { display: grid; }
.acf-hidden { display: none; }

/* ── Flexbox ── */
.acf-flex-row { flex-direction: row; }
.acf-flex-col { flex-direction: column; }
.acf-items-center { align-items: center; }
.acf-justify-center { justify-content: center; }
.acf-justify-between { justify-content: space-between; }
.acf-gap-1 { gap: var(--acf-space-xs); }
.acf-gap-2 { gap: var(--acf-space-sm); }
.acf-gap-3 { gap: var(--acf-space-md); }

/* ── Text ── */
.acf-text-left { text-align: left; }
.acf-text-center { text-align: center; }
.acf-text-right { text-align: right; }
.acf-text-sm { font-size: var(--acf-font-size-sm); }
.acf-text-base { font-size: var(--acf-font-size-base); }
.acf-text-lg { font-size: var(--acf-font-size-lg); }

/* ── Colors ── */
.acf-text-primary { color: var(--acf-text-primary); }
.acf-text-secondary { color: var(--acf-text-secondary); }
.acf-text-muted { color: var(--acf-text-muted); }
.acf-bg-primary { background-color: var(--acf-bg-primary); }
.acf-bg-secondary { background-color: var(--acf-bg-secondary); }

/* ── Border ── */
.acf-border { border: 1px solid var(--acf-border); }
.acf-border-t { border-top: 1px solid var(--acf-border); }
.acf-border-r { border-right: 1px solid var(--acf-border); }
.acf-border-b { border-bottom: 1px solid var(--acf-border); }
.acf-border-l { border-left: 1px solid var(--acf-border); }

/* ── Rounded ── */
.acf-rounded-sm { border-radius: var(--acf-radius-sm); }
.acf-rounded { border-radius: var(--acf-radius-md); }
.acf-rounded-lg { border-radius: var(--acf-radius-lg); }
.acf-rounded-full { border-radius: var(--acf-radius-full); }

/* ── Shadow ── */
.acf-shadow-sm { box-shadow: var(--acf-shadow-sm); }
.acf-shadow { box-shadow: var(--acf-shadow-md); }
.acf-shadow-lg { box-shadow: var(--acf-shadow-lg); }
```

---

## 実装計画

### Phase 1: 基盤構築（4週間、48時間）

**Week 1-2: AEF コア実装**
- エディタコアクラス（Editor, BlockRegistry, EventBus）
- BaseBlock 実装
- 基本3ブロック（Paragraph, Heading, List）
- 工数: 24時間

**Week 3-4: ACF 基盤実装**
- CSS変数システム
- リセットCSS、ユーティリティクラス
- エディタベーススタイル
- 工数: 24時間

### Phase 2: 機能拡張（3週間、36時間）

**Week 5-6: AEF ブロック拡張**
- 残り6ブロック実装（Code, Quote, Table, Image, Checklist, Delimiter）
- InlineToolbar実装
- SlashCommands実装
- 工数: 24時間

**Week 7: ACF コンポーネント**
- ボタン、フォーム、カード、モーダル
- グリッドシステム
- 工数: 12時間

### Phase 3: 統合・テスト（2週間、24時間）

**Week 8: 統合**
- AEF + ACF 統合
- 既存wysiwyg.jsからの移行
- 工数: 12時間

**Week 9: テスト・ドキュメント**
- 単体テスト作成
- ドキュメント整備
- デモページ作成
- 工数: 12時間

### タイムライン

| Phase | 内容 | 工数 | 期間 | リリース予定 |
|-------|------|------|------|------------|
| Phase 1 | 基盤構築 | 48h | 4週間 | 2026-04-11 |
| Phase 2 | 機能拡張 | 36h | 3週間 | 2026-05-02 |
| Phase 3 | 統合・テスト | 24h | 2週間 | 2026-05-16 |
| **合計** | - | **108h** | **9週間** | **2026-05-16** |

---

## 期待効果

### コード品質

| 指標 | 現状 | 改善後 | 改善率 |
|-----|------|-------|-------|
| エディタファイル数 | 1 | 30+ | - |
| 最大ファイルサイズ | 2,889行 | <300行 | **90%削減** |
| CSS管理 | JS内注入 | 独立ファイル | **100%分離** |
| テストカバレッジ | 0% | 80%+ | **∞** |
| TypeScript対応 | ❌ | ✅ | **NEW** |

### 保守性

| 指標 | 現状 | 改善後 | 改善率 |
|-----|------|-------|-------|
| 新機能追加時間 | 4-8h | 1-2h | **75%削減** |
| バグ修正時間 | 2-4h | 0.5-1h | **75%削減** |
| コードレビュー時間 | 1h | 0.25h | **75%削減** |

### 再利用性

| 指標 | 現状 | 改善後 |
|-----|------|-------|
| 他プロジェクトでの使用 | 不可能 | 可能 |
| プラグインエコシステム | ❌ | ✅ |
| npm公開 | ❌ | ✅ (将来) |

### ROI

**投資**: 108時間、約¥540,000

**年間リターン**:
- 開発効率向上: ¥780,000
- バグ削減: ¥520,000
- 再利用による開発コスト削減: ¥300,000

**総リターン**: ¥1,600,000/年  
**ROI**: **196%**  
**投資回収期間**: **約4ヶ月**

---

## 次のステップ

1. **この設計書のレビュー**
2. **Phase 1 実装開始承認**
3. **開発環境セットアップ**
4. **AEF コア実装開始** (2026年3月中旬予定)

---

**作成日**: 2026年3月14日  
**作成者**: GenSpark AI Developer  
**ステータス**: 設計完了、実装待ち
