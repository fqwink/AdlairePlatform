/// <reference lib="dom" />

/**
 * AEB (Adlaire Editor & Blocks) - Blocks Module
 *
 * @file Framework/AEB/AEB.Blocks.ts
 * @version 1.0.0
 *
 * All block types: BaseBlock, ParagraphBlock, HeadingBlock, ListBlock, QuoteBlock,
 * CodeBlock, ImageBlock, TableBlock, ChecklistBlock, DelimiterBlock
 */

import type { EditorConfig } from './AEB.Core.ts';

/**
 * BlockConfig - Static configuration for a block type
 */
export interface BlockConfig {
  title: string;
  icon: string;
  supportsInlineTools: boolean;
}

/**
 * Data interfaces for each block type
 */
export interface ParagraphData {
  text: string;
  alignment: string;
  [key: string]: unknown;
}

export interface HeadingData {
  text: string;
  level: number;
  alignment: string;
  [key: string]: unknown;
}

export interface ListData {
  style: 'ordered' | 'unordered';
  items: string[];
  [key: string]: unknown;
}

export interface QuoteData {
  text: string;
  caption: string;
  [key: string]: unknown;
}

export interface CodeData {
  code: string;
  language: string;
  [key: string]: unknown;
}

export interface ImageData {
  url: string;
  caption: string;
  alt: string;
  stretched: boolean;
  [key: string]: unknown;
}

export interface TableData {
  content: string[][];
  withHeadings: boolean;
  [key: string]: unknown;
}

export interface ChecklistItem {
  text: string;
  checked: boolean;
}

export interface ChecklistData {
  items: ChecklistItem[];
  [key: string]: unknown;
}

export interface DelimiterData {
  [key: string]: never;
}

/**
 * BaseBlock - Abstract base class for all blocks
 */
export class BaseBlock {
  data: Record<string, unknown>;
  config: Partial<EditorConfig>;
  wrapper: HTMLElement | null;
  element!: HTMLElement;
  protected _boundListeners: Array<{ el: EventTarget; event: string; fn: EventListener }> = [];

  constructor(data: Record<string, unknown> = {}, config: Partial<EditorConfig> = {}) {
    this.data = data;
    this.config = config;
    this.wrapper = null;
  }

  protected _addListener(el: EventTarget, event: string, fn: EventListener): void {
    el.addEventListener(event, fn);
    this._boundListeners.push({ el, event, fn });
  }

  render(): HTMLElement {
    throw new Error('[BaseBlock] render() must be implemented by subclass');
  }

  save(): Record<string, unknown> {
    return { ...this.data };
  }

  validate(_data: Record<string, unknown>): boolean {
    return true;
  }

  onFocus(): void {}
  onBlur(): void {}
  onDestroy(): void {
    for (const { el, event, fn } of this._boundListeners) {
      el.removeEventListener(event, fn);
    }
    this._boundListeners = [];
  }

  static get config(): BlockConfig {
    return {
      title: 'Base Block',
      icon: '',
      supportsInlineTools: true
    };
  }

  static get type(): string {
    return 'base';
  }
}

/**
 * ParagraphBlock - Standard paragraph
 */
export class ParagraphBlock extends BaseBlock {
  declare data: ParagraphData;

  constructor(data: Partial<ParagraphData> = {}, config: Partial<EditorConfig> = {}) {
    super(data as Record<string, unknown>, config);
    this.data = {
      text: data.text || '',
      alignment: data.alignment || 'left'
    };
  }

  override render(): HTMLElement {
    this.wrapper = document.createElement('div');
    this.wrapper.className = 'aeb-block aeb-block-paragraph';
    this.wrapper.setAttribute('data-alignment', this.data.alignment);
    this.element = document.createElement('p');
    this.element.className = 'aeb-paragraph';
    this.element.contentEditable = String(!this.config.readOnly);
    this.element.innerHTML = this._sanitize(this.data.text);
    if (!this.data.text && !this.config.readOnly) {
      this.element.dataset.placeholder = this.config.placeholder || 'Type here...';
    }
    this.wrapper.appendChild(this.element);
    if (!this.config.readOnly) {
      this._addListener(this.element, 'input', () => {
        this.data.text = this.element.innerHTML;
      });
    }
    return this.wrapper;
  }

  override save(): ParagraphData {
    return {
      text: this.element.innerHTML,
      alignment: this.data.alignment
    };
  }

  override validate(data: Partial<ParagraphData>): boolean {
    return typeof data.text === 'string';
  }

  setAlignment(alignment: string): void {
    this.data.alignment = alignment;
    if (this.wrapper) {
      this.wrapper.setAttribute('data-alignment', alignment);
    }
  }

  private _sanitize(html: string): string {
    const div = document.createElement('div');
    div.textContent = html;
    return div.innerHTML;
  }

  static override get config(): BlockConfig {
    return {
      title: 'Paragraph',
      icon: '<svg width="16" height="16"><path d="M3 5h10M3 8h10M3 11h10"/></svg>',
      supportsInlineTools: true
    };
  }

  static override get type(): string {
    return 'paragraph';
  }
}

/**
 * HeadingBlock - Heading (h2, h3)
 */
export class HeadingBlock extends BaseBlock {
  declare data: HeadingData;

  constructor(data: Partial<HeadingData> = {}, config: Partial<EditorConfig> = {}) {
    super(data as Record<string, unknown>, config);
    const level = [2, 3].includes(data.level ?? 0) ? data.level! : 2;
    this.data = {
      text: data.text || '',
      level,
      alignment: data.alignment || 'left'
    };
  }

  override render(): HTMLElement {
    const level = [2, 3].includes(this.data.level) ? this.data.level : 2;
    this.wrapper = document.createElement('div');
    this.wrapper.className = 'aeb-block aeb-block-heading';
    this.wrapper.setAttribute('data-level', String(level));
    this.wrapper.setAttribute('data-alignment', this.data.alignment);
    const tag = `h${level}`;
    this.element = document.createElement(tag);
    this.element.className = 'aeb-heading';
    this.element.contentEditable = String(!this.config.readOnly);
    this.element.innerHTML = this._sanitize(this.data.text);
    if (!this.data.text && !this.config.readOnly) {
      this.element.dataset.placeholder = `Heading ${this.data.level}`;
    }
    this.wrapper.appendChild(this.element);
    if (!this.config.readOnly) {
      this._addListener(this.element, 'input', () => {
        this.data.text = this.element.innerHTML;
      });
    }
    return this.wrapper;
  }

  override save(): HeadingData {
    return {
      text: this.element.innerHTML,
      level: this.data.level,
      alignment: this.data.alignment
    };
  }

  override validate(data: Partial<HeadingData>): boolean {
    return typeof data.text === 'string' && [2, 3].includes(data.level!);
  }

  setLevel(level: number): void {
    if (![2, 3].includes(level)) {
      throw new Error('[HeadingBlock] Level must be 2 or 3');
    }
    this.data.level = level;
    if (this.wrapper && this.element) {
      const newElement = document.createElement(`h${level}`);
      newElement.className = this.element.className;
      newElement.contentEditable = this.element.contentEditable;
      newElement.innerHTML = this.element.innerHTML;
      this.wrapper.replaceChild(newElement, this.element);
      this.element = newElement;
      this.wrapper.setAttribute('data-level', String(level));
    }
  }

  setAlignment(alignment: string): void {
    this.data.alignment = alignment;
    if (this.wrapper) {
      this.wrapper.setAttribute('data-alignment', alignment);
    }
  }

  private _sanitize(html: string): string {
    const div = document.createElement('div');
    div.textContent = html;
    return div.innerHTML;
  }

  static override get config(): BlockConfig {
    return {
      title: 'Heading',
      icon: '<svg width="16" height="16"><text x="0" y="14" font-size="14" font-weight="bold">H</text></svg>',
      supportsInlineTools: true
    };
  }

  static override get type(): string {
    return 'heading';
  }
}

/**
 * ListBlock - Ordered/Unordered list
 */
export class ListBlock extends BaseBlock {
  declare data: ListData;

  constructor(data: Partial<ListData> = {}, config: Partial<EditorConfig> = {}) {
    super(data as Record<string, unknown>, config);
    this.data = {
      style: data.style || 'unordered',
      items: data.items || ['']
    };
  }

  override render(): HTMLElement {
    this.wrapper = document.createElement('div');
    this.wrapper.className = 'aeb-block aeb-block-list';
    const tag = this.data.style === 'ordered' ? 'ol' : 'ul';
    this.element = document.createElement(tag);
    this.element.className = 'aeb-list';
    this.element.setAttribute('data-style', this.data.style);
    this.data.items.forEach((item, index) => {
      const li = document.createElement('li');
      li.className = 'aeb-list-item';
      li.contentEditable = String(!this.config.readOnly);
      li.innerHTML = this._sanitize(item);
      li.dataset.index = String(index);
      this.element.appendChild(li);
    });
    this.wrapper.appendChild(this.element);
    if (!this.config.readOnly) {
      this._addListener(this.element, 'input', () => {
        this.data.items = Array.from(this.element.querySelectorAll('li')).map(li => li.innerHTML);
      });
    }
    return this.wrapper;
  }

  override save(): ListData {
    return {
      style: this.data.style,
      items: Array.from(this.element.querySelectorAll('li')).map(li => li.innerHTML)
    };
  }

  private _sanitize(html: string): string {
    const div = document.createElement('div');
    div.textContent = html;
    return div.innerHTML;
  }

  override validate(data: Partial<ListData>): boolean {
    return ['ordered', 'unordered'].includes(data.style!) && Array.isArray(data.items);
  }

  static override get config(): BlockConfig {
    return {
      title: 'List',
      icon: '<svg width="16" height="16"><path d="M2 3h1v1H2zm3 0h9v1H5z"/></svg>',
      supportsInlineTools: true
    };
  }

  static override get type(): string {
    return 'list';
  }
}

/**
 * QuoteBlock - Blockquote
 */
export class QuoteBlock extends BaseBlock {
  declare data: QuoteData;

  constructor(data: Partial<QuoteData> = {}, config: Partial<EditorConfig> = {}) {
    super(data as Record<string, unknown>, config);
    this.data = {
      text: data.text || '',
      caption: data.caption || ''
    };
  }

  override render(): HTMLElement {
    this.wrapper = document.createElement('div');
    this.wrapper.className = 'aeb-block aeb-block-quote';
    this.element = document.createElement('blockquote');
    this.element.className = 'aeb-quote';
    this.element.contentEditable = String(!this.config.readOnly);
    this.element.innerHTML = this._sanitize(this.data.text);
    this.wrapper.appendChild(this.element);
    if (!this.config.readOnly) {
      this._addListener(this.element, 'input', () => {
        this.data.text = this.element.innerHTML;
      });
    }
    return this.wrapper;
  }

  override save(): QuoteData {
    return {
      text: this.element.innerHTML,
      caption: this.data.caption
    };
  }

  private _sanitize(html: string): string {
    const div = document.createElement('div');
    div.textContent = html;
    return div.innerHTML;
  }

  override validate(data: Partial<QuoteData>): boolean {
    return typeof data.text === 'string';
  }

  static override get config(): BlockConfig {
    return {
      title: 'Quote',
      icon: '<svg width="16" height="16"><path d="M3 3h4v4H3z"/></svg>',
      supportsInlineTools: true
    };
  }

  static override get type(): string {
    return 'quote';
  }
}

/**
 * CodeBlock - Code with syntax highlighting
 */
export class CodeBlock extends BaseBlock {
  declare data: CodeData;

  constructor(data: Partial<CodeData> = {}, config: Partial<EditorConfig> = {}) {
    super(data as Record<string, unknown>, config);
    this.data = {
      code: data.code || '',
      language: data.language || 'plaintext'
    };
  }

  override render(): HTMLElement {
    this.wrapper = document.createElement('div');
    this.wrapper.className = 'aeb-block aeb-block-code';
    this.element = document.createElement('pre');
    const code = document.createElement('code');
    code.className = 'aeb-code';
    code.contentEditable = String(!this.config.readOnly);
    code.textContent = this.data.code;
    this.element.appendChild(code);
    this.wrapper.appendChild(this.element);
    if (!this.config.readOnly) {
      this._addListener(code, 'input', () => {
        this.data.code = code.textContent || '';
      });
    }
    return this.wrapper;
  }

  override save(): CodeData {
    const codeEl = this.wrapper?.querySelector('code');
    return {
      code: codeEl?.textContent || this.data.code,
      language: this.data.language
    };
  }

  override validate(data: Partial<CodeData>): boolean {
    return typeof data.code === 'string';
  }

  static override get config(): BlockConfig {
    return {
      title: 'Code',
      icon: '<svg width="16" height="16"><path d="M5 7l-3 3 3 3M11 7l3 3-3 3"/></svg>',
      supportsInlineTools: false
    };
  }

  static override get type(): string {
    return 'code';
  }
}

/**
 * ImageBlock - Image with caption
 */
export class ImageBlock extends BaseBlock {
  declare data: ImageData;

  constructor(data: Partial<ImageData> = {}, config: Partial<EditorConfig> = {}) {
    super(data as Record<string, unknown>, config);
    this.data = {
      url: data.url || '',
      caption: data.caption || '',
      alt: data.alt || '',
      stretched: data.stretched || false
    };
  }

  override render(): HTMLElement {
    this.wrapper = document.createElement('div');
    this.wrapper.className = 'aeb-block aeb-block-image';
    const imgWrapper = document.createElement('div');
    imgWrapper.className = 'aeb-image-wrapper';
    this.element = document.createElement('img');
    this.element.className = 'aeb-image';
    (this.element as HTMLImageElement).src = this.data.url;
    (this.element as HTMLImageElement).alt = this.data.alt;
    imgWrapper.appendChild(this.element);
    this.wrapper.appendChild(imgWrapper);
    if (this.data.caption) {
      const caption = document.createElement('div');
      caption.className = 'aeb-image-caption';
      caption.contentEditable = String(!this.config.readOnly);
      caption.textContent = this.data.caption;
      this.wrapper.appendChild(caption);
      if (!this.config.readOnly) {
        this._addListener(caption, 'input', () => {
          this.data.caption = caption.textContent || '';
        });
      }
    }
    return this.wrapper;
  }

  override save(): ImageData {
    const caption = this.wrapper?.querySelector('.aeb-image-caption');
    return {
      url: this.data.url,
      caption: caption ? caption.textContent || '' : this.data.caption,
      alt: this.data.alt,
      stretched: this.data.stretched
    };
  }

  override validate(data: Partial<ImageData>): boolean {
    return typeof data.url === 'string' && data.url.length > 0;
  }

  static override get config(): BlockConfig {
    return {
      title: 'Image',
      icon: '<svg width="16" height="16"><rect x="2" y="2" width="12" height="12"/></svg>',
      supportsInlineTools: false
    };
  }

  static override get type(): string {
    return 'image';
  }
}

/**
 * TableBlock - Editable table
 */
export class TableBlock extends BaseBlock {
  declare data: TableData;

  constructor(data: Partial<TableData> = {}, config: Partial<EditorConfig> = {}) {
    super(data as Record<string, unknown>, config);
    this.data = {
      content: data.content || [['', ''], ['', '']],
      withHeadings: data.withHeadings || false
    };
  }

  override render(): HTMLElement {
    this.wrapper = document.createElement('div');
    this.wrapper.className = 'aeb-block aeb-block-table';
    this.element = document.createElement('table');
    this.element.className = 'aeb-table';
    this.data.content.forEach((row, rowIndex) => {
      const tr = document.createElement('tr');
      row.forEach((cell, cellIndex) => {
        const td = document.createElement(this.data.withHeadings && rowIndex === 0 ? 'th' : 'td');
        td.contentEditable = String(!this.config.readOnly);
        td.textContent = cell;
        td.dataset.row = String(rowIndex);
        td.dataset.col = String(cellIndex);
        tr.appendChild(td);
      });
      this.element.appendChild(tr);
    });
    this.wrapper.appendChild(this.element);
    if (!this.config.readOnly) {
      this._addListener(this.element, 'input', () => {
        this.data.content = Array.from(this.element.querySelectorAll('tr')).map(tr =>
          Array.from(tr.querySelectorAll('td, th')).map(cell => cell.textContent || '')
        );
      });
    }
    return this.wrapper;
  }

  override save(): TableData {
    return {
      content: Array.from(this.element.querySelectorAll('tr')).map(tr =>
        Array.from(tr.querySelectorAll('td, th')).map(cell => cell.textContent || '')
      ),
      withHeadings: this.data.withHeadings
    };
  }

  override validate(data: Partial<TableData>): boolean {
    return Array.isArray(data.content) && data.content.length > 0;
  }

  static override get config(): BlockConfig {
    return {
      title: 'Table',
      icon: '<svg width="16" height="16"><path d="M2 2h12v12H2z M2 6h12 M8 2v12"/></svg>',
      supportsInlineTools: false
    };
  }

  static override get type(): string {
    return 'table';
  }
}

/**
 * ChecklistBlock - Todo checklist
 */
export class ChecklistBlock extends BaseBlock {
  declare data: ChecklistData;

  constructor(data: Partial<ChecklistData> = {}, config: Partial<EditorConfig> = {}) {
    super(data as Record<string, unknown>, config);
    this.data = {
      items: data.items || [{ text: '', checked: false }]
    };
  }

  override render(): HTMLElement {
    this.wrapper = document.createElement('div');
    this.wrapper.className = 'aeb-block aeb-block-checklist';
    this.element = document.createElement('ul');
    this.element.className = 'aeb-checklist';
    this.data.items.forEach((item, index) => {
      const li = document.createElement('li');
      li.className = 'aeb-checklist-item';
      li.dataset.checked = String(item.checked);
      const checkbox = document.createElement('div');
      checkbox.className = 'aeb-checklist-checkbox';
      checkbox.dataset.checked = String(item.checked);
      const text = document.createElement('div');
      text.className = 'aeb-checklist-text';
      text.contentEditable = String(!this.config.readOnly);
      text.textContent = item.text;
      li.appendChild(checkbox);
      li.appendChild(text);
      this.element.appendChild(li);
      if (!this.config.readOnly) {
        this._addListener(checkbox, 'click', () => {
          const checked = checkbox.dataset.checked === 'true';
          checkbox.dataset.checked = String(!checked);
          li.dataset.checked = String(!checked);
        });
        this._addListener(text, 'input', () => {
          this.data.items[index].text = text.textContent || '';
        });
      }
    });
    this.wrapper.appendChild(this.element);
    return this.wrapper;
  }

  override save(): ChecklistData {
    return {
      items: Array.from(this.element.querySelectorAll('.aeb-checklist-item')).map(li => ({
        text: (li.querySelector('.aeb-checklist-text') as HTMLElement).textContent || '',
        checked: (li as HTMLElement).dataset.checked === 'true'
      }))
    };
  }

  override validate(data: Partial<ChecklistData>): boolean {
    return Array.isArray(data.items);
  }

  static override get config(): BlockConfig {
    return {
      title: 'Checklist',
      icon: '<svg width="16" height="16"><path d="M3 8l3 3 7-7"/></svg>',
      supportsInlineTools: false
    };
  }

  static override get type(): string {
    return 'checklist';
  }
}

/**
 * DelimiterBlock - Horizontal rule
 */
export class DelimiterBlock extends BaseBlock {
  constructor(data: Record<string, unknown> = {}, config: Partial<EditorConfig> = {}) {
    super(data, config);
    this.data = {};
  }

  override render(): HTMLElement {
    this.wrapper = document.createElement('div');
    this.wrapper.className = 'aeb-block aeb-block-delimiter';
    this.element = document.createElement('div');
    this.element.className = 'aeb-delimiter';
    for (let i = 0; i < 3; i++) {
      const dot = document.createElement('div');
      dot.className = 'aeb-delimiter-dot';
      this.element.appendChild(dot);
    }
    this.wrapper.appendChild(this.element);
    return this.wrapper;
  }

  override save(): Record<string, never> {
    return {};
  }

  override validate(_data: Record<string, unknown>): boolean {
    return true;
  }

  static override get config(): BlockConfig {
    return {
      title: 'Delimiter',
      icon: '<svg width="16" height="16"><path d="M2 8h12"/></svg>',
      supportsInlineTools: false
    };
  }

  static override get type(): string {
    return 'delimiter';
  }
}
