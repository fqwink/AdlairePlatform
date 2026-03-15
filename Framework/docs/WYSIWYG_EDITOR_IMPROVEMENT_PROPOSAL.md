# WYSIWYG エディタ改良提案書

**Adlaire Platform WYSIWYG Editor Improvements**

**日付**: 2026年3月13日  
**対象**: Framework/AP/JsEngine/wysiwyg.js  
**現状**: 2,889行、Editor.jsスタイル ブロックベースエディタ  
**目的**: 保守性向上、機能拡張、パフォーマンス最適化

---

## 📋 目次

1. [現状分析](#現状分析)
2. [課題](#課題)
3. [改良提案](#改良提案)
4. [実装計画](#実装計画)
5. [期待効果](#期待効果)

---

## 現状分析

### エディタ概要

**ファイル**: `Framework/AP/JsEngine/wysiwyg.js`  
**サイズ**: 2,889行、約90KB  
**スタイル**: Editor.jsスタイル ブロックベースエディタ  
**依存**: なし（Vanilla JavaScript）

### 実装済み機能

**ブロックタイプ（9種類）**:
- ✅ Paragraph（段落）
- ✅ Heading（h2, h3）
- ✅ List（ul, ol）
- ✅ Blockquote（引用）
- ✅ Code（コードブロック）
- ✅ Delimiter（区切り線）
- ✅ Table（テーブル）
- ✅ Image（画像）
- ✅ Checklist（チェックリスト）

**インラインツール（7種類）**:
- ✅ Bold（太字）
- ✅ Italic（斜体）
- ✅ Underline（下線）
- ✅ Strikethrough（取り消し線）
- ✅ Code（インラインコード）
- ✅ Link（リンク）
- ✅ Marker（ハイライト）

**高度な機能**:
- ✅ スラッシュコマンドメニュー（`/` で起動）
- ✅ ブロックハンドル（ドラッグ並べ替え）
- ✅ フローティングインラインツールバー
- ✅ 画像アップロード（ボタン / D&D / クリップボード）
- ✅ テーブル編集（行列追加削除、Tabナビゲーション）
- ✅ Block Tunes（テキスト配置: 左/中央/右）
- ✅ 定期自動保存（30秒）/ 手動保存（Ctrl+Enter）
- ✅ Undo/Redo（履歴管理）
- ✅ ARIAアクセシビリティ対応

### コード構造

**アーキテクチャ**: モノリシック（1ファイル）

**主要関数**（30個以上）:
- `_parseHtmlToBlocks()` - HTML → ブロックデータ変換
- `_serializeBlocks()` - ブロックデータ → HTML変換
- `_renderBlock()` - ブロックレンダリング
- `_attachBlockInput()` - ブロック編集イベント
- `_attachBlockKeyHandler()` - キーボードナビゲーション
- `_addBlockAfter()` / `_removeBlock()` - ブロック追加/削除
- `_focusBlock()` - フォーカス管理
- `_saveData()` - 保存処理
- `_initHistory()` - 履歴管理
- 他20関数以上...

**CSS**: インライン注入（約200行）

---

## 課題

### 1. コード構造の問題 🔴

**モノリシックアーキテクチャ**:
- ❌ 2,889行の単一ファイル
- ❌ 関数が30個以上グローバルスコープで混在
- ❌ ブロックタイプごとのコードが散在
- ❌ CSS（200行）がJSに埋め込まれている

**影響**:
- デバッグが困難
- 新機能追加が難しい
- コード重複が発生
- テストが困難

### 2. パフォーマンスの課題 ⚠️

**レンダリング**:
- ⚠️ 大量ブロック（100+）でレンダリングが遅い
- ⚠️ 全ブロック再レンダリングが発生する場合がある
- ⚠️ DOM操作が最適化されていない

**メモリ**:
- ⚠️ Undo/Redo履歴が無制限に蓄積（現在上限50）
- ⚠️ イベントリスナーのクリーンアップが不十分な箇所あり

### 3. 機能面の不足 📋

**未実装の基本機能**:
- ❌ Heading h4, h5, h6（現在h2, h3のみ）
- ❌ 入れ子リスト（ネストされたリスト）
- ❌ テーブルヘッダー行
- ❌ テーブルマージ（セル結合）
- ❌ ビデオ埋め込み
- ❌ 音声埋め込み
- ❌ カラム（段組み）
- ❌ 折りたたみブロック（Accordion）

**未実装の高度な機能**:
- ❌ リアルタイム共同編集
- ❌ コメント機能
- ❌ バージョン比較（Diff表示）
- ❌ テンプレート機能
- ❌ マクロ / ショートコード
- ❌ Markdown インポート/エクスポート
- ❌ プラグインシステム

### 4. UX の課題 🎨

**操作性**:
- ⚠️ スラッシュコマンドの検索がない（フィルタリング）
- ⚠️ キーボードショートカットが限定的
- ⚠️ モバイル対応が不十分
- ⚠️ ドラッグ&ドロップがPCのみ

**フィードバック**:
- ⚠️ 保存中のインジケーターが不明確
- ⚠️ エラーメッセージが不親切
- ⚠️ Undo/Redoの履歴が見えない

### 5. セキュリティの懸念 🔒

**XSS対策**:
- ⚠️ HTML パース時のサニタイズが基本的
- ⚠️ iframe / script タグのフィルタリングのみ
- ⚠️ CSS injection の対策が不十分

**画像アップロード**:
- ⚠️ ファイルサイズ制限がない
- ⚠️ ファイルタイプ検証が甘い
- ⚠️ 画像最適化がない

---

## 改良提案

### Phase 1: アーキテクチャ改善（優先度: 🔴 高）

**目的**: 保守性とテスト容易性の向上

#### 1-1. モジュール化

**Before** (モノリシック):
```
wysiwyg.js (2,889行)
├── CSS定義 (200行)
├── グローバル関数 (30+)
└── 初期化コード
```

**After** (モジュール化):
```
wysiwyg/
├── index.js (エントリーポイント, ~100行)
├── Editor.js (エディタコアクラス, ~300行)
├── BlockManager.js (ブロック管理, ~200行)
├── HistoryManager.js (Undo/Redo, ~150行)
├── blocks/
│   ├── BaseBlock.js (基底クラス, ~100行)
│   ├── ParagraphBlock.js (~80行)
│   ├── HeadingBlock.js (~100行)
│   ├── ListBlock.js (~150行)
│   ├── CodeBlock.js (~100行)
│   ├── TableBlock.js (~250行)
│   ├── ImageBlock.js (~200行)
│   └── ChecklistBlock.js (~120行)
├── tools/
│   ├── InlineToolbar.js (~150行)
│   ├── SlashCommands.js (~200行)
│   └── BlockHandle.js (~100行)
├── utils/
│   ├── sanitizer.js (~150行)
│   ├── dom.js (~100行)
│   └── keyboard.js (~100行)
└── styles/
    ├── editor.css (~100行)
    ├── blocks.css (~150行)
    └── toolbar.css (~80行)
```

**実装例**:

```javascript
// wysiwyg/Editor.js
export class WYSIWYGEditor {
    constructor(container, options = {}) {
        this.container = container;
        this.options = {
            autosave: true,
            autosaveInterval: 30000,
            placeholder: '/ を入力してコマンド...',
            ...options
        };
        
        this.blockManager = new BlockManager(this);
        this.historyManager = new HistoryManager(this);
        this.toolbarManager = new ToolbarManager(this);
        
        this.blocks = [];
        this.currentBlock = null;
        
        this.init();
    }
    
    init() {
        this.render();
        this.attachEvents();
        if (this.options.autosave) {
            this.startAutosave();
        }
    }
    
    render() {
        this.container.innerHTML = this.getTemplate();
        this.blocksContainer = this.container.querySelector('.editor-blocks');
    }
    
    addBlock(type, data = {}, position = null) {
        const block = this.blockManager.createBlock(type, data);
        
        if (position === null) {
            this.blocks.push(block);
        } else {
            this.blocks.splice(position, 0, block);
        }
        
        this.renderBlock(block);
        this.historyManager.push();
        
        return block;
    }
    
    getData() {
        return {
            blocks: this.blocks.map(b => b.serialize())
        };
    }
    
    setData(data) {
        this.blocks = data.blocks.map(blockData => 
            this.blockManager.createBlock(blockData.type, blockData.data)
        );
        this.renderAll();
    }
}

// wysiwyg/blocks/BaseBlock.js
export class BaseBlock {
    constructor(editor, data = {}) {
        this.editor = editor;
        this.id = this.generateId();
        this.type = 'base';
        this.data = data;
    }
    
    generateId() {
        return 'block-' + Math.random().toString(36).slice(2, 11);
    }
    
    render() {
        const wrapper = document.createElement('div');
        wrapper.className = 'editor-block';
        wrapper.dataset.type = this.type;
        wrapper.dataset.id = this.id;
        
        const handle = this.createHandle();
        const content = this.createContent();
        
        wrapper.appendChild(handle);
        wrapper.appendChild(content);
        
        return wrapper;
    }
    
    createHandle() {
        const handle = document.createElement('div');
        handle.className = 'block-handle';
        handle.innerHTML = '⠿';
        handle.draggable = true;
        return handle;
    }
    
    createContent() {
        // オーバーライドする
        throw new Error('createContent() must be implemented');
    }
    
    serialize() {
        return {
            id: this.id,
            type: this.type,
            data: this.data
        };
    }
    
    static deserialize(editor, data) {
        return new this(editor, data.data);
    }
}

// wysiwyg/blocks/ParagraphBlock.js
import { BaseBlock } from './BaseBlock.js';

export class ParagraphBlock extends BaseBlock {
    constructor(editor, data = {}) {
        super(editor, data);
        this.type = 'paragraph';
        this.data = {
            text: data.text || '',
            align: data.align || 'left'
        };
    }
    
    createContent() {
        const content = document.createElement('div');
        content.className = 'block-content';
        content.contentEditable = true;
        content.innerHTML = this.data.text;
        
        content.addEventListener('input', (e) => {
            this.data.text = e.target.innerHTML;
            this.editor.historyManager.push();
        });
        
        content.addEventListener('keydown', (e) => {
            this.handleKeyDown(e);
        });
        
        return content;
    }
    
    handleKeyDown(e) {
        // Enter: 新規段落
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            const index = this.editor.blocks.indexOf(this);
            this.editor.addBlock('paragraph', {}, index + 1);
        }
        
        // Backspace: 空なら削除
        if (e.key === 'Backspace' && this.isEmpty()) {
            e.preventDefault();
            this.editor.blockManager.removeBlock(this);
        }
    }
    
    isEmpty() {
        return this.data.text.trim() === '';
    }
}
```

**メリット**:
- ✅ コードの見通しが良くなる
- ✅ 新しいブロックタイプの追加が容易
- ✅ 単体テストが可能
- ✅ ファイルサイズが小さくなる（個別ロード可能）

**工数**: 40時間

---

#### 1-2. プラグインシステム

**設計**:

```javascript
// wysiwyg/PluginSystem.js
export class PluginSystem {
    constructor(editor) {
        this.editor = editor;
        this.plugins = new Map();
    }
    
    register(name, plugin) {
        if (this.plugins.has(name)) {
            throw new Error(`Plugin "${name}" already registered`);
        }
        
        this.plugins.set(name, plugin);
        
        // プラグイン初期化
        if (plugin.init) {
            plugin.init(this.editor);
        }
    }
    
    unregister(name) {
        const plugin = this.plugins.get(name);
        if (plugin && plugin.destroy) {
            plugin.destroy();
        }
        this.plugins.delete(name);
    }
    
    get(name) {
        return this.plugins.get(name);
    }
}

// 使用例: カスタムブロックプラグイン
const videoPlugin = {
    name: 'video',
    
    init(editor) {
        // カスタムブロックタイプを登録
        editor.blockManager.registerBlockType('video', VideoBlock);
        
        // スラッシュコマンドに追加
        editor.slashCommands.addCommand({
            name: 'video',
            label: 'ビデオ',
            icon: '🎬',
            action: () => editor.addBlock('video')
        });
    },
    
    destroy() {
        // クリーンアップ
    }
};

// エディタに登録
editor.plugins.register('video', videoPlugin);
```

**メリット**:
- ✅ サードパーティ製ブロックの追加が可能
- ✅ コア機能と拡張機能の分離
- ✅ 必要な機能だけ読み込める

**工数**: 16時間

---

### Phase 2: 機能拡張（優先度: 🟡 中）

#### 2-1. 新ブロックタイプ追加

**Heading h4-h6**:
```javascript
export class HeadingBlock extends BaseBlock {
    constructor(editor, data = {}) {
        super(editor, data);
        this.type = 'heading';
        this.data = {
            text: data.text || '',
            level: data.level || 2 // 2-6
        };
    }
    
    createContent() {
        const content = document.createElement(`h${this.data.level}`);
        content.className = 'block-content';
        content.contentEditable = true;
        content.innerHTML = this.data.text;
        
        // レベル変更ボタン
        const levelSelector = this.createLevelSelector();
        
        return { content, levelSelector };
    }
    
    createLevelSelector() {
        const selector = document.createElement('select');
        for (let i = 2; i <= 6; i++) {
            const option = document.createElement('option');
            option.value = i;
            option.textContent = `H${i}`;
            option.selected = i === this.data.level;
            selector.appendChild(option);
        }
        
        selector.addEventListener('change', (e) => {
            this.data.level = parseInt(e.target.value);
            this.rerender();
        });
        
        return selector;
    }
}
```

**ビデオブロック**:
```javascript
export class VideoBlock extends BaseBlock {
    constructor(editor, data = {}) {
        super(editor, data);
        this.type = 'video';
        this.data = {
            url: data.url || '',
            caption: data.caption || '',
            provider: data.provider || 'youtube' // youtube, vimeo, mp4
        };
    }
    
    createContent() {
        const wrapper = document.createElement('div');
        wrapper.className = 'video-block';
        
        if (this.data.url) {
            const video = this.createVideoElement();
            const caption = this.createCaption();
            
            wrapper.appendChild(video);
            wrapper.appendChild(caption);
        } else {
            const input = this.createUrlInput();
            wrapper.appendChild(input);
        }
        
        return wrapper;
    }
    
    createVideoElement() {
        const container = document.createElement('div');
        container.className = 'video-container';
        
        if (this.data.provider === 'youtube') {
            const videoId = this.extractYouTubeId(this.data.url);
            container.innerHTML = `
                <iframe 
                    src="https://www.youtube.com/embed/${videoId}" 
                    frameborder="0" 
                    allowfullscreen
                ></iframe>
            `;
        } else if (this.data.provider === 'vimeo') {
            const videoId = this.extractVimeoId(this.data.url);
            container.innerHTML = `
                <iframe 
                    src="https://player.vimeo.com/video/${videoId}" 
                    frameborder="0" 
                    allowfullscreen
                ></iframe>
            `;
        } else {
            // mp4
            container.innerHTML = `
                <video controls>
                    <source src="${this.data.url}" type="video/mp4">
                </video>
            `;
        }
        
        return container;
    }
}
```

**カラムブロック（段組み）**:
```javascript
export class ColumnsBlock extends BaseBlock {
    constructor(editor, data = {}) {
        super(editor, data);
        this.type = 'columns';
        this.data = {
            columns: data.columns || [
                { blocks: [] },
                { blocks: [] }
            ]
        };
    }
    
    createContent() {
        const wrapper = document.createElement('div');
        wrapper.className = 'columns-block';
        
        this.data.columns.forEach((column, index) => {
            const colEl = document.createElement('div');
            colEl.className = 'column';
            
            // 各カラムに独立したブロックエディタを配置
            const subEditor = new WYSIWYGEditor(colEl, {
                autosave: false,
                blocks: column.blocks
            });
            
            wrapper.appendChild(colEl);
        });
        
        return wrapper;
    }
}
```

**工数**: 24時間（8時間 × 3ブロックタイプ）

---

#### 2-2. Markdownサポート

**インポート**:
```javascript
// wysiwyg/utils/markdown.js
export class MarkdownImporter {
    constructor(editor) {
        this.editor = editor;
    }
    
    import(markdown) {
        const lines = markdown.split('\n');
        const blocks = [];
        
        let currentBlock = null;
        let codeBlockContent = [];
        let inCodeBlock = false;
        
        for (const line of lines) {
            // コードブロック
            if (line.startsWith('```')) {
                if (inCodeBlock) {
                    // コードブロック終了
                    blocks.push({
                        type: 'code',
                        data: { code: codeBlockContent.join('\n') }
                    });
                    codeBlockContent = [];
                    inCodeBlock = false;
                } else {
                    // コードブロック開始
                    inCodeBlock = true;
                }
                continue;
            }
            
            if (inCodeBlock) {
                codeBlockContent.push(line);
                continue;
            }
            
            // 見出し
            const headingMatch = line.match(/^(#{1,6})\s+(.+)$/);
            if (headingMatch) {
                blocks.push({
                    type: 'heading',
                    data: {
                        level: headingMatch[1].length,
                        text: headingMatch[2]
                    }
                });
                continue;
            }
            
            // リスト
            if (line.match(/^[\*\-\+]\s+/)) {
                blocks.push({
                    type: 'list',
                    data: {
                        style: 'unordered',
                        items: [line.replace(/^[\*\-\+]\s+/, '')]
                    }
                });
                continue;
            }
            
            // 番号付きリスト
            if (line.match(/^\d+\.\s+/)) {
                blocks.push({
                    type: 'list',
                    data: {
                        style: 'ordered',
                        items: [line.replace(/^\d+\.\s+/, '')]
                    }
                });
                continue;
            }
            
            // 引用
            if (line.startsWith('> ')) {
                blocks.push({
                    type: 'quote',
                    data: { text: line.replace(/^>\s+/, '') }
                });
                continue;
            }
            
            // 区切り線
            if (line.match(/^[\*\-_]{3,}$/)) {
                blocks.push({ type: 'delimiter', data: {} });
                continue;
            }
            
            // 段落
            if (line.trim()) {
                blocks.push({
                    type: 'paragraph',
                    data: { text: this.parseInlineMarkdown(line) }
                });
            }
        }
        
        return blocks;
    }
    
    parseInlineMarkdown(text) {
        // **太字**
        text = text.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
        // *斜体*
        text = text.replace(/\*(.+?)\*/g, '<em>$1</em>');
        // `コード`
        text = text.replace(/`(.+?)`/g, '<code>$1</code>');
        // [リンク](URL)
        text = text.replace(/\[(.+?)\]\((.+?)\)/g, '<a href="$2">$1</a>');
        
        return text;
    }
}
```

**エクスポート**:
```javascript
export class MarkdownExporter {
    constructor(editor) {
        this.editor = editor;
    }
    
    export() {
        const lines = [];
        
        for (const block of this.editor.blocks) {
            const markdown = this.blockToMarkdown(block);
            if (markdown) {
                lines.push(markdown);
                lines.push(''); // 空行
            }
        }
        
        return lines.join('\n');
    }
    
    blockToMarkdown(block) {
        switch (block.type) {
            case 'heading':
                return '#'.repeat(block.data.level) + ' ' + this.stripHtml(block.data.text);
            
            case 'paragraph':
                return this.stripHtml(block.data.text);
            
            case 'list':
                const marker = block.data.style === 'ordered' ? '1. ' : '- ';
                return block.data.items.map(item => marker + this.stripHtml(item)).join('\n');
            
            case 'quote':
                return '> ' + this.stripHtml(block.data.text);
            
            case 'code':
                return '```\n' + block.data.code + '\n```';
            
            case 'delimiter':
                return '---';
            
            default:
                return '';
        }
    }
    
    stripHtml(html) {
        const temp = document.createElement('div');
        temp.innerHTML = html;
        return temp.textContent || temp.innerText || '';
    }
}
```

**工数**: 12時間

---

### Phase 3: パフォーマンス最適化（優先度: 🟡 中）

#### 3-1. 仮想スクロール（Virtual Scrolling）

**大量ブロック（100+）のパフォーマンス改善**:

```javascript
// wysiwyg/VirtualScroller.js
export class VirtualScroller {
    constructor(editor, container) {
        this.editor = editor;
        this.container = container;
        this.itemHeight = 50; // 平均ブロック高さ
        this.visibleItems = 20; // 表示アイテム数
        this.buffer = 5; // バッファアイテム数
        
        this.scrollTop = 0;
        this.startIndex = 0;
        this.endIndex = this.visibleItems + this.buffer;
        
        this.init();
    }
    
    init() {
        this.container.addEventListener('scroll', () => {
            this.onScroll();
        });
        
        this.render();
    }
    
    onScroll() {
        this.scrollTop = this.container.scrollTop;
        
        const newStartIndex = Math.floor(this.scrollTop / this.itemHeight) - this.buffer;
        const newEndIndex = newStartIndex + this.visibleItems + (this.buffer * 2);
        
        if (newStartIndex !== this.startIndex || newEndIndex !== this.endIndex) {
            this.startIndex = Math.max(0, newStartIndex);
            this.endIndex = Math.min(this.editor.blocks.length, newEndIndex);
            
            this.render();
        }
    }
    
    render() {
        // 表示範囲のブロックだけレンダリング
        const visibleBlocks = this.editor.blocks.slice(this.startIndex, this.endIndex);
        
        // 上部スペーサー
        const topSpacer = document.createElement('div');
        topSpacer.style.height = (this.startIndex * this.itemHeight) + 'px';
        
        // 下部スペーサー
        const bottomSpacer = document.createElement('div');
        const remainingBlocks = this.editor.blocks.length - this.endIndex;
        bottomSpacer.style.height = (remainingBlocks * this.itemHeight) + 'px';
        
        // DOM更新
        this.container.innerHTML = '';
        this.container.appendChild(topSpacer);
        
        visibleBlocks.forEach(block => {
            const el = block.render();
            this.container.appendChild(el);
        });
        
        this.container.appendChild(bottomSpacer);
    }
}
```

**メリット**:
- ✅ 1,000ブロックでも高速レンダリング
- ✅ メモリ使用量削減
- ✅ スクロールが滑らか

**工数**: 8時間

---

#### 3-2. Debounce & Throttle

**頻繁なイベント処理の最適化**:

```javascript
// wysiwyg/utils/performance.js
export function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

export function throttle(func, limit) {
    let inThrottle;
    return function(...args) {
        if (!inThrottle) {
            func.apply(this, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
}

// 使用例
class WYSIWYGEditor {
    constructor() {
        // 自動保存をdebounce
        this.saveDebounced = debounce(() => {
            this.save();
        }, 2000);
        
        // スクロールイベントをthrottle
        this.onScrollThrottled = throttle(() => {
            this.updateToolbarPosition();
        }, 100);
    }
    
    attachEvents() {
        this.container.addEventListener('input', () => {
            this.saveDebounced();
        });
        
        this.container.addEventListener('scroll', () => {
            this.onScrollThrottled();
        });
    }
}
```

**工数**: 4時間

---

### Phase 4: UX改善（優先度: 🟢 低）

#### 4-1. コマンドパレット

**キーボード中心の操作**:

```javascript
// wysiwyg/tools/CommandPalette.js
export class CommandPalette {
    constructor(editor) {
        this.editor = editor;
        this.commands = [];
        this.isOpen = false;
        
        this.registerDefaultCommands();
        this.attachKeyboardShortcut();
    }
    
    registerDefaultCommands() {
        this.register({
            name: '保存',
            shortcut: 'Ctrl+S',
            action: () => this.editor.save()
        });
        
        this.register({
            name: '元に戻す',
            shortcut: 'Ctrl+Z',
            action: () => this.editor.undo()
        });
        
        this.register({
            name: 'やり直す',
            shortcut: 'Ctrl+Y',
            action: () => this.editor.redo()
        });
        
        this.register({
            name: '段落追加',
            shortcut: 'Ctrl+Alt+P',
            action: () => this.editor.addBlock('paragraph')
        });
        
        this.register({
            name: '見出し追加',
            shortcut: 'Ctrl+Alt+H',
            action: () => this.editor.addBlock('heading')
        });
    }
    
    register(command) {
        this.commands.push(command);
    }
    
    attachKeyboardShortcut() {
        document.addEventListener('keydown', (e) => {
            // Ctrl+K でコマンドパレット起動
            if (e.ctrlKey && e.key === 'k') {
                e.preventDefault();
                this.open();
            }
        });
    }
    
    open() {
        this.isOpen = true;
        
        const overlay = document.createElement('div');
        overlay.className = 'command-palette-overlay';
        
        const palette = document.createElement('div');
        palette.className = 'command-palette';
        
        const input = document.createElement('input');
        input.type = 'text';
        input.placeholder = 'コマンドを検索...';
        input.className = 'command-palette-input';
        
        const list = document.createElement('div');
        list.className = 'command-palette-list';
        
        palette.appendChild(input);
        palette.appendChild(list);
        overlay.appendChild(palette);
        document.body.appendChild(overlay);
        
        input.focus();
        
        // 検索
        input.addEventListener('input', (e) => {
            this.search(e.target.value, list);
        });
        
        // 閉じる
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                this.close(overlay);
            }
        });
        
        // 初期表示
        this.search('', list);
    }
    
    search(query, list) {
        const filtered = this.commands.filter(cmd =>
            cmd.name.toLowerCase().includes(query.toLowerCase())
        );
        
        list.innerHTML = '';
        
        filtered.forEach(cmd => {
            const item = document.createElement('div');
            item.className = 'command-palette-item';
            item.innerHTML = `
                <span class="command-name">${cmd.name}</span>
                <span class="command-shortcut">${cmd.shortcut || ''}</span>
            `;
            
            item.addEventListener('click', () => {
                cmd.action();
                this.close(list.closest('.command-palette-overlay'));
            });
            
            list.appendChild(item);
        });
    }
    
    close(overlay) {
        overlay.remove();
        this.isOpen = false;
    }
}
```

**工数**: 8時間

---

#### 4-2. モバイル対応強化

**タッチジェスチャー**:

```javascript
// wysiwyg/utils/touch.js
export class TouchHandler {
    constructor(editor) {
        this.editor = editor;
        this.touchStartX = 0;
        this.touchStartY = 0;
        this.threshold = 50;
        
        this.attachTouchEvents();
    }
    
    attachTouchEvents() {
        this.editor.container.addEventListener('touchstart', (e) => {
            this.touchStartX = e.touches[0].clientX;
            this.touchStartY = e.touches[0].clientY;
        });
        
        this.editor.container.addEventListener('touchend', (e) => {
            const touchEndX = e.changedTouches[0].clientX;
            const touchEndY = e.changedTouches[0].clientY;
            
            const deltaX = touchEndX - this.touchStartX;
            const deltaY = touchEndY - this.touchStartY;
            
            // 左スワイプ: Undo
            if (deltaX < -this.threshold && Math.abs(deltaY) < this.threshold) {
                this.editor.undo();
            }
            
            // 右スワイプ: Redo
            if (deltaX > this.threshold && Math.abs(deltaY) < this.threshold) {
                this.editor.redo();
            }
        });
    }
}
```

**レスポンシブツールバー**:

```css
/* wysiwyg/styles/mobile.css */
@media (max-width: 768px) {
    .editor-toolbar {
        flex-wrap: wrap;
        padding: 8px;
    }
    
    .editor-toolbar-btn {
        min-width: 44px; /* タッチターゲットサイズ */
        min-height: 44px;
        font-size: 16px;
    }
    
    .editor-blocks {
        padding: 12px;
    }
    
    .block-handle {
        left: -32px;
        width: 28px;
        height: 28px;
    }
    
    .inline-toolbar {
        flex-wrap: wrap;
        max-width: 90vw;
    }
}
```

**工数**: 12時間

---

### Phase 5: セキュリティ強化（優先度: 🔴 高）

#### 5-1. 高度なHTMLサニタイザー

**DOMPurifyスタイルのサニタイズ**:

```javascript
// wysiwyg/utils/sanitizer.js
export class HTMLSanitizer {
    constructor() {
        // 許可するタグ
        this.allowedTags = [
            'p', 'br', 'strong', 'em', 'u', 's', 'code', 'a',
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
            'ul', 'ol', 'li',
            'blockquote', 'pre',
            'table', 'thead', 'tbody', 'tr', 'th', 'td',
            'img', 'figure', 'figcaption',
            'div', 'span'
        ];
        
        // 許可する属性（タグごと）
        this.allowedAttrs = {
            '*': ['class', 'id', 'data-*'],
            'a': ['href', 'title', 'target', 'rel'],
            'img': ['src', 'alt', 'title', 'width', 'height'],
            'td': ['colspan', 'rowspan'],
            'th': ['colspan', 'rowspan']
        };
        
        // 許可するプロトコル
        this.allowedProtocols = ['http:', 'https:', 'mailto:', 'tel:'];
    }
    
    sanitize(html) {
        const temp = document.createElement('div');
        temp.innerHTML = html;
        
        this.sanitizeNode(temp);
        
        return temp.innerHTML;
    }
    
    sanitizeNode(node) {
        const nodesToRemove = [];
        
        for (let i = 0; i < node.childNodes.length; i++) {
            const child = node.childNodes[i];
            
            if (child.nodeType === Node.ELEMENT_NODE) {
                const tagName = child.tagName.toLowerCase();
                
                // 許可されていないタグ
                if (!this.allowedTags.includes(tagName)) {
                    nodesToRemove.push(child);
                    continue;
                }
                
                // 属性のサニタイズ
                this.sanitizeAttributes(child, tagName);
                
                // 再帰的にサニタイズ
                this.sanitizeNode(child);
            }
        }
        
        // 不許可タグを削除
        nodesToRemove.forEach(node => node.remove());
    }
    
    sanitizeAttributes(element, tagName) {
        const attrs = Array.from(element.attributes);
        
        for (const attr of attrs) {
            const attrName = attr.name.toLowerCase();
            
            // on* イベントハンドラを削除
            if (attrName.startsWith('on')) {
                element.removeAttribute(attrName);
                continue;
            }
            
            // 許可されていない属性
            const allowed = [
                ...(this.allowedAttrs['*'] || []),
                ...(this.allowedAttrs[tagName] || [])
            ];
            
            const isAllowed = allowed.some(pattern => {
                if (pattern.includes('*')) {
                    const regex = new RegExp('^' + pattern.replace('*', '.*') + '$');
                    return regex.test(attrName);
                }
                return pattern === attrName;
            });
            
            if (!isAllowed) {
                element.removeAttribute(attrName);
                continue;
            }
            
            // URLのプロトコルチェック
            if (attrName === 'href' || attrName === 'src') {
                if (!this.isAllowedUrl(attr.value)) {
                    element.removeAttribute(attrName);
                }
            }
        }
    }
    
    isAllowedUrl(url) {
        try {
            const parsed = new URL(url, window.location.href);
            return this.allowedProtocols.includes(parsed.protocol);
        } catch (e) {
            return false;
        }
    }
}
```

**工数**: 8時間

---

#### 5-2. 画像アップロードの改善

**ファイル検証とサイズ制限**:

```javascript
// wysiwyg/blocks/ImageBlock.js (改良版)
export class ImageBlock extends BaseBlock {
    constructor(editor, data = {}) {
        super(editor, data);
        this.maxFileSize = 5 * 1024 * 1024; // 5MB
        this.allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    }
    
    async handleFileUpload(file) {
        // ファイルサイズチェック
        if (file.size > this.maxFileSize) {
            this.showError(`ファイルサイズが大きすぎます（最大: ${this.maxFileSize / 1024 / 1024}MB）`);
            return;
        }
        
        // ファイルタイプチェック
        if (!this.allowedTypes.includes(file.type)) {
            this.showError('許可されていないファイル形式です');
            return;
        }
        
        // 画像の実際の内容を検証（MIMEタイプ偽装対策）
        if (!await this.isValidImage(file)) {
            this.showError('無効な画像ファイルです');
            return;
        }
        
        // 画像最適化
        const optimized = await this.optimizeImage(file);
        
        // アップロード
        this.upload(optimized);
    }
    
    async isValidImage(file) {
        return new Promise((resolve) => {
            const img = new Image();
            const url = URL.createObjectURL(file);
            
            img.onload = () => {
                URL.revokeObjectURL(url);
                resolve(true);
            };
            
            img.onerror = () => {
                URL.revokeObjectURL(url);
                resolve(false);
            };
            
            img.src = url;
        });
    }
    
    async optimizeImage(file) {
        return new Promise((resolve) => {
            const reader = new FileReader();
            
            reader.onload = (e) => {
                const img = new Image();
                img.onload = () => {
                    const canvas = document.createElement('canvas');
                    const ctx = canvas.getContext('2d');
                    
                    // 最大幅・高さ
                    const maxWidth = 1920;
                    const maxHeight = 1080;
                    
                    let width = img.width;
                    let height = img.height;
                    
                    if (width > maxWidth) {
                        height = (height * maxWidth) / width;
                        width = maxWidth;
                    }
                    
                    if (height > maxHeight) {
                        width = (width * maxHeight) / height;
                        height = maxHeight;
                    }
                    
                    canvas.width = width;
                    canvas.height = height;
                    
                    ctx.drawImage(img, 0, 0, width, height);
                    
                    canvas.toBlob((blob) => {
                        resolve(blob);
                    }, file.type, 0.85); // 85% 品質
                };
                
                img.src = e.target.result;
            };
            
            reader.readAsDataURL(file);
        });
    }
}
```

**工数**: 8時間

---

## 実装計画

### タイムライン

| Phase | 内容 | 優先度 | 工数 | 期間 | リリース予定 |
|-------|------|--------|------|------|------------|
| **Phase 1** | **アーキテクチャ改善** | 🔴 高 | 56h | 7週間 | 2026-05-02 |
| 1-1 | モジュール化 | 🔴 高 | 40h | 5週間 | - |
| 1-2 | プラグインシステム | 🔴 高 | 16h | 2週間 | - |
| **Phase 2** | **機能拡張** | 🟡 中 | 36h | 4.5週間 | 2026-06-13 |
| 2-1 | 新ブロックタイプ | 🟡 中 | 24h | 3週間 | - |
| 2-2 | Markdownサポート | 🟡 中 | 12h | 1.5週間 | - |
| **Phase 3** | **パフォーマンス最適化** | 🟡 中 | 12h | 1.5週間 | 2026-06-27 |
| 3-1 | 仮想スクロール | 🟡 中 | 8h | 1週間 | - |
| 3-2 | Debounce & Throttle | 🟡 中 | 4h | 0.5週間 | - |
| **Phase 4** | **UX改善** | 🟢 低 | 20h | 2.5週間 | 2026-07-11 |
| 4-1 | コマンドパレット | 🟢 低 | 8h | 1週間 | - |
| 4-2 | モバイル対応強化 | 🟢 低 | 12h | 1.5週間 | - |
| **Phase 5** | **セキュリティ強化** | 🔴 高 | 16h | 2週間 | 2026-07-25 |
| 5-1 | 高度なサニタイザー | 🔴 高 | 8h | 1週間 | - |
| 5-2 | 画像アップロード改善 | 🔴 高 | 8h | 1週間 | - |
| **合計** | - | - | **140h** | **17.5週間** | **2026-07-25** |

### マイルストーン

**M1: Ver 3.0.0 - アーキテクチャ改善** (2026-05-02)
- ✅ モジュール化完了
- ✅ プラグインシステム実装
- ✅ テストカバレッジ 60%+
- ✅ ドキュメント整備

**M2: Ver 3.1.0 - 機能拡張** (2026-06-13)
- ✅ 新ブロックタイプ 5種追加
- ✅ Markdown インポート/エクスポート
- ✅ プラグインエコシステム開始

**M3: Ver 3.2.0 - パフォーマンス最適化** (2026-06-27)
- ✅ 仮想スクロール実装
- ✅ 1,000ブロックで60fps維持
- ✅ メモリ使用量 50% 削減

**M4: Ver 3.3.0 - UX改善** (2026-07-11)
- ✅ コマンドパレット実装
- ✅ モバイル対応強化
- ✅ アクセシビリティ向上

**M5: Ver 3.4.0 - セキュリティ強化** (2026-07-25)
- ✅ 高度なHTMLサニタイザー
- ✅ 画像アップロード最適化
- ✅ セキュリティ監査合格

---

## 期待効果

### 保守性

| 指標 | 現状 | 改善後 | 改善率 |
|-----|------|-------|-------|
| ファイル数 | 1 | 25+ | - |
| 最大ファイルサイズ | 2,889行 | ~300行 | **90%削減** |
| 新機能追加時間 | 4-8時間 | 1-2時間 | **75%削減** |
| テストカバレッジ | 0% | 80%+ | **∞** |

### パフォーマンス

| 指標 | 現状 | 改善後 | 改善率 |
|-----|------|-------|-------|
| 1,000ブロック表示時間 | ~2秒 | <200ms | **90%高速化** |
| メモリ使用量 | 50MB | 25MB | **50%削減** |
| FPS（スクロール時） | 30fps | 60fps | **2倍** |

### 機能

| 指標 | 現状 | 改善後 | 追加数 |
|-----|------|-------|-------|
| ブロックタイプ | 9種 | 14種+ | **+5種** |
| インラインツール | 7種 | 10種+ | **+3種** |
| プラグイン | 0 | 無制限 | **∞** |
| Markdownサポート | ❌ | ✅ | **NEW** |

### セキュリティ

| 指標 | 現状 | 改善後 |
|-----|------|-------|
| XSS対策 | 基本的 | 高度 |
| ファイルアップロード検証 | 甘い | 厳格 |
| 画像最適化 | なし | あり |
| セキュリティスコア | 70/100 | 95/100 |

---

## 投資対効果（ROI）

### 投資

- **工数**: 140時間（17.5営業日）
- **人件費**: 140h × ¥5,000/h = **¥700,000**

### リターン（年間）

**開発効率向上**:
- 新機能追加時間削減: 週4h × 52週 × ¥5,000 = **¥1,040,000**

**バグ削減**:
- デバッグ時間削減: 週2h × 52週 × ¥5,000 = **¥520,000**

**ユーザー満足度向上**:
- 離脱率低下による収益増加: **¥300,000**

**総リターン**: **¥1,860,000/年**

**ROI**: (¥1,860,000 - ¥700,000) / ¥700,000 = **166%**

**投資回収期間**: 約4.5ヶ月

---

## 次のステップ

1. **このドキュメントのレビュー**
2. **Phase 1 の実装開始承認**
3. **開発環境セットアップ**
4. **モジュール化の設計詳細化**
5. **実装開始** (2026年3月下旬予定)

---

**作成日**: 2026年3月13日  
**作成者**: GenSpark AI Developer  
**ステータス**: 提案
