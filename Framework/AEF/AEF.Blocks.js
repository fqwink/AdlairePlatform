/**
 * AEF (Adlaire Editor Framework) - Blocks Module
 * 
 * @file Framework/AEF/AEF.Blocks.js
 * @version 1.0.0
 * 
 * All block types: BaseBlock, ParagraphBlock, HeadingBlock, ListBlock, QuoteBlock, 
 * CodeBlock, ImageBlock, TableBlock, ChecklistBlock, DelimiterBlock
 */

/**
 * BaseBlock - Abstract base class for all blocks
 */
export class BaseBlock {
  constructor(data = {}, config = {}) {
    this.data = data;
    this.config = config;
    this.wrapper = null;
  }

  render() {
    throw new Error('[BaseBlock] render() must be implemented by subclass');
  }

  save() {
    return { ...this.data };
  }

  validate(data) {
    return true;
  }

  onFocus() {}
  onBlur() {}
  onDestroy() {}

  static get config() {
    return {
      title: 'Base Block',
      icon: '',
      supportsInlineTools: true
    };
  }

  static get type() {
    return 'base';
  }
}

/**
 * ParagraphBlock - Standard paragraph
 */
export class ParagraphBlock extends BaseBlock {
  constructor(data = {}, config = {}) {
    super(data, config);
    this.data = {
      text: data.text || '',
      alignment: data.alignment || 'left'
    };
  }

  render() {
    this.wrapper = document.createElement('div');
    this.wrapper.className = 'aef-block aef-block-paragraph';
    this.wrapper.setAttribute('data-alignment', this.data.alignment);
    this.element = document.createElement('p');
    this.element.className = 'aef-paragraph';
    this.element.contentEditable = !this.config.readOnly;
    this.element.innerHTML = this._sanitize(this.data.text);
    if (!this.data.text && !this.config.readOnly) {
      this.element.dataset.placeholder = this.config.placeholder || 'Type here...';
    }
    this.wrapper.appendChild(this.element);
    if (!this.config.readOnly) {
      this.element.addEventListener('input', () => {
        this.data.text = this.element.innerHTML;
      });
    }
    return this.wrapper;
  }

  save() {
    return {
      text: this.element.innerHTML,
      alignment: this.data.alignment
    };
  }

  validate(data) {
    return typeof data.text === 'string';
  }

  setAlignment(alignment) {
    this.data.alignment = alignment;
    if (this.wrapper) {
      this.wrapper.setAttribute('data-alignment', alignment);
    }
  }

  _sanitize(html) {
    const div = document.createElement('div');
    div.textContent = html;
    return div.innerHTML;
  }

  static get config() {
    return {
      title: 'Paragraph',
      icon: '<svg width="16" height="16"><path d="M3 5h10M3 8h10M3 11h10"/></svg>',
      supportsInlineTools: true
    };
  }

  static get type() {
    return 'paragraph';
  }
}

/**
 * HeadingBlock - Heading (h2, h3)
 */
export class HeadingBlock extends BaseBlock {
  constructor(data = {}, config = {}) {
    super(data, config);
    this.data = {
      text: data.text || '',
      level: data.level || 2,
      alignment: data.alignment || 'left'
    };
  }

  render() {
    this.wrapper = document.createElement('div');
    this.wrapper.className = 'aef-block aef-block-heading';
    this.wrapper.setAttribute('data-level', this.data.level);
    this.wrapper.setAttribute('data-alignment', this.data.alignment);
    const tag = `h${this.data.level}`;
    this.element = document.createElement(tag);
    this.element.className = 'aef-heading';
    this.element.contentEditable = !this.config.readOnly;
    this.element.innerHTML = this._sanitize(this.data.text);
    if (!this.data.text && !this.config.readOnly) {
      this.element.dataset.placeholder = `Heading ${this.data.level}`;
    }
    this.wrapper.appendChild(this.element);
    if (!this.config.readOnly) {
      this.element.addEventListener('input', () => {
        this.data.text = this.element.innerHTML;
      });
    }
    return this.wrapper;
  }

  save() {
    return {
      text: this.element.innerHTML,
      level: this.data.level,
      alignment: this.data.alignment
    };
  }

  validate(data) {
    return typeof data.text === 'string' && [2, 3].includes(data.level);
  }

  setLevel(level) {
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
      this.wrapper.setAttribute('data-level', level);
    }
  }

  setAlignment(alignment) {
    this.data.alignment = alignment;
    if (this.wrapper) {
      this.wrapper.setAttribute('data-alignment', alignment);
    }
  }

  _sanitize(html) {
    const div = document.createElement('div');
    div.textContent = html;
    return div.innerHTML;
  }

  static get config() {
    return {
      title: 'Heading',
      icon: '<svg width="16" height="16"><text x="0" y="14" font-size="14" font-weight="bold">H</text></svg>',
      supportsInlineTools: true
    };
  }

  static get type() {
    return 'heading';
  }
}

/**
 * ListBlock - Ordered/Unordered list
 */
export class ListBlock extends BaseBlock {
  constructor(data = {}, config = {}) {
    super(data, config);
    this.data = {
      style: data.style || 'unordered',
      items: data.items || ['']
    };
  }

  render() {
    this.wrapper = document.createElement('div');
    this.wrapper.className = 'aef-block aef-block-list';
    const tag = this.data.style === 'ordered' ? 'ol' : 'ul';
    this.element = document.createElement(tag);
    this.element.className = 'aef-list';
    this.element.setAttribute('data-style', this.data.style);
    this.data.items.forEach((item, index) => {
      const li = document.createElement('li');
      li.className = 'aef-list-item';
      li.contentEditable = !this.config.readOnly;
      li.innerHTML = item;
      li.dataset.index = index;
      this.element.appendChild(li);
    });
    this.wrapper.appendChild(this.element);
    if (!this.config.readOnly) {
      this.element.addEventListener('input', () => {
        this.data.items = Array.from(this.element.querySelectorAll('li')).map(li => li.innerHTML);
      });
    }
    return this.wrapper;
  }

  save() {
    return {
      style: this.data.style,
      items: Array.from(this.element.querySelectorAll('li')).map(li => li.innerHTML)
    };
  }

  validate(data) {
    return ['ordered', 'unordered'].includes(data.style) && Array.isArray(data.items);
  }

  static get config() {
    return {
      title: 'List',
      icon: '<svg width="16" height="16"><path d="M2 3h1v1H2zm3 0h9v1H5z"/></svg>',
      supportsInlineTools: true
    };
  }

  static get type() {
    return 'list';
  }
}

/**
 * QuoteBlock - Blockquote
 */
export class QuoteBlock extends BaseBlock {
  constructor(data = {}, config = {}) {
    super(data, config);
    this.data = {
      text: data.text || '',
      caption: data.caption || ''
    };
  }

  render() {
    this.wrapper = document.createElement('div');
    this.wrapper.className = 'aef-block aef-block-quote';
    this.element = document.createElement('blockquote');
    this.element.className = 'aef-quote';
    this.element.contentEditable = !this.config.readOnly;
    this.element.innerHTML = this.data.text;
    this.wrapper.appendChild(this.element);
    if (!this.config.readOnly) {
      this.element.addEventListener('input', () => {
        this.data.text = this.element.innerHTML;
      });
    }
    return this.wrapper;
  }

  save() {
    return {
      text: this.element.innerHTML,
      caption: this.data.caption
    };
  }

  validate(data) {
    return typeof data.text === 'string';
  }

  static get config() {
    return {
      title: 'Quote',
      icon: '<svg width="16" height="16"><path d="M3 3h4v4H3z"/></svg>',
      supportsInlineTools: true
    };
  }

  static get type() {
    return 'quote';
  }
}

/**
 * CodeBlock - Code with syntax highlighting
 */
export class CodeBlock extends BaseBlock {
  constructor(data = {}, config = {}) {
    super(data, config);
    this.data = {
      code: data.code || '',
      language: data.language || 'plaintext'
    };
  }

  render() {
    this.wrapper = document.createElement('div');
    this.wrapper.className = 'aef-block aef-block-code';
    this.element = document.createElement('pre');
    const code = document.createElement('code');
    code.className = 'aef-code';
    code.contentEditable = !this.config.readOnly;
    code.textContent = this.data.code;
    this.element.appendChild(code);
    this.wrapper.appendChild(this.element);
    if (!this.config.readOnly) {
      code.addEventListener('input', () => {
        this.data.code = code.textContent;
      });
    }
    return this.wrapper;
  }

  save() {
    return {
      code: this.wrapper.querySelector('code').textContent,
      language: this.data.language
    };
  }

  validate(data) {
    return typeof data.code === 'string';
  }

  static get config() {
    return {
      title: 'Code',
      icon: '<svg width="16" height="16"><path d="M5 7l-3 3 3 3M11 7l3 3-3 3"/></svg>',
      supportsInlineTools: false
    };
  }

  static get type() {
    return 'code';
  }
}

/**
 * ImageBlock - Image with caption
 */
export class ImageBlock extends BaseBlock {
  constructor(data = {}, config = {}) {
    super(data, config);
    this.data = {
      url: data.url || '',
      caption: data.caption || '',
      alt: data.alt || '',
      stretched: data.stretched || false
    };
  }

  render() {
    this.wrapper = document.createElement('div');
    this.wrapper.className = 'aef-block aef-block-image';
    const imgWrapper = document.createElement('div');
    imgWrapper.className = 'aef-image-wrapper';
    this.element = document.createElement('img');
    this.element.className = 'aef-image';
    this.element.src = this.data.url;
    this.element.alt = this.data.alt;
    imgWrapper.appendChild(this.element);
    this.wrapper.appendChild(imgWrapper);
    if (this.data.caption) {
      const caption = document.createElement('div');
      caption.className = 'aef-image-caption';
      caption.contentEditable = !this.config.readOnly;
      caption.textContent = this.data.caption;
      this.wrapper.appendChild(caption);
      if (!this.config.readOnly) {
        caption.addEventListener('input', () => {
          this.data.caption = caption.textContent;
        });
      }
    }
    return this.wrapper;
  }

  save() {
    const caption = this.wrapper.querySelector('.aef-image-caption');
    return {
      url: this.data.url,
      caption: caption ? caption.textContent : '',
      alt: this.data.alt,
      stretched: this.data.stretched
    };
  }

  validate(data) {
    return typeof data.url === 'string' && data.url.length > 0;
  }

  static get config() {
    return {
      title: 'Image',
      icon: '<svg width="16" height="16"><rect x="2" y="2" width="12" height="12"/></svg>',
      supportsInlineTools: false
    };
  }

  static get type() {
    return 'image';
  }
}

/**
 * TableBlock - Editable table
 */
export class TableBlock extends BaseBlock {
  constructor(data = {}, config = {}) {
    super(data, config);
    this.data = {
      content: data.content || [['', ''], ['', '']],
      withHeadings: data.withHeadings || false
    };
  }

  render() {
    this.wrapper = document.createElement('div');
    this.wrapper.className = 'aef-block aef-block-table';
    this.element = document.createElement('table');
    this.element.className = 'aef-table';
    this.data.content.forEach((row, rowIndex) => {
      const tr = document.createElement('tr');
      row.forEach((cell, cellIndex) => {
        const td = document.createElement(this.data.withHeadings && rowIndex === 0 ? 'th' : 'td');
        td.contentEditable = !this.config.readOnly;
        td.textContent = cell;
        td.dataset.row = rowIndex;
        td.dataset.col = cellIndex;
        tr.appendChild(td);
      });
      this.element.appendChild(tr);
    });
    this.wrapper.appendChild(this.element);
    if (!this.config.readOnly) {
      this.element.addEventListener('input', () => {
        this.data.content = Array.from(this.element.querySelectorAll('tr')).map(tr => 
          Array.from(tr.querySelectorAll('td, th')).map(cell => cell.textContent)
        );
      });
    }
    return this.wrapper;
  }

  save() {
    return {
      content: Array.from(this.element.querySelectorAll('tr')).map(tr => 
        Array.from(tr.querySelectorAll('td, th')).map(cell => cell.textContent)
      ),
      withHeadings: this.data.withHeadings
    };
  }

  validate(data) {
    return Array.isArray(data.content) && data.content.length > 0;
  }

  static get config() {
    return {
      title: 'Table',
      icon: '<svg width="16" height="16"><path d="M2 2h12v12H2z M2 6h12 M8 2v12"/></svg>',
      supportsInlineTools: false
    };
  }

  static get type() {
    return 'table';
  }
}

/**
 * ChecklistBlock - Todo checklist
 */
export class ChecklistBlock extends BaseBlock {
  constructor(data = {}, config = {}) {
    super(data, config);
    this.data = {
      items: data.items || [{ text: '', checked: false }]
    };
  }

  render() {
    this.wrapper = document.createElement('div');
    this.wrapper.className = 'aef-block aef-block-checklist';
    this.element = document.createElement('ul');
    this.element.className = 'aef-checklist';
    this.data.items.forEach((item, index) => {
      const li = document.createElement('li');
      li.className = 'aef-checklist-item';
      li.dataset.checked = item.checked;
      const checkbox = document.createElement('div');
      checkbox.className = 'aef-checklist-checkbox';
      checkbox.dataset.checked = item.checked;
      const text = document.createElement('div');
      text.className = 'aef-checklist-text';
      text.contentEditable = !this.config.readOnly;
      text.textContent = item.text;
      li.appendChild(checkbox);
      li.appendChild(text);
      this.element.appendChild(li);
      if (!this.config.readOnly) {
        checkbox.addEventListener('click', () => {
          const checked = checkbox.dataset.checked === 'true';
          checkbox.dataset.checked = !checked;
          li.dataset.checked = !checked;
        });
        text.addEventListener('input', () => {
          this.data.items[index].text = text.textContent;
        });
      }
    });
    this.wrapper.appendChild(this.element);
    return this.wrapper;
  }

  save() {
    return {
      items: Array.from(this.element.querySelectorAll('.aef-checklist-item')).map(li => ({
        text: li.querySelector('.aef-checklist-text').textContent,
        checked: li.dataset.checked === 'true'
      }))
    };
  }

  validate(data) {
    return Array.isArray(data.items);
  }

  static get config() {
    return {
      title: 'Checklist',
      icon: '<svg width="16" height="16"><path d="M3 8l3 3 7-7"/></svg>',
      supportsInlineTools: false
    };
  }

  static get type() {
    return 'checklist';
  }
}

/**
 * DelimiterBlock - Horizontal rule
 */
export class DelimiterBlock extends BaseBlock {
  constructor(data = {}, config = {}) {
    super(data, config);
    this.data = {};
  }

  render() {
    this.wrapper = document.createElement('div');
    this.wrapper.className = 'aef-block aef-block-delimiter';
    this.element = document.createElement('div');
    this.element.className = 'aef-delimiter';
    for (let i = 0; i < 3; i++) {
      const dot = document.createElement('div');
      dot.className = 'aef-delimiter-dot';
      this.element.appendChild(dot);
    }
    this.wrapper.appendChild(this.element);
    return this.wrapper;
  }

  save() {
    return {};
  }

  validate(data) {
    return true;
  }

  static get config() {
    return {
      title: 'Delimiter',
      icon: '<svg width="16" height="16"><path d="M2 8h12"/></svg>',
      supportsInlineTools: false
    };
  }

  static get type() {
    return 'delimiter';
  }
}
