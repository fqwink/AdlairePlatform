/**
 * ParagraphBlock - Standard paragraph block
 * 
 * Basic text block with contenteditable paragraph.
 * 
 * @example
 * const block = new ParagraphBlock({ text: 'Hello world' });
 * document.body.appendChild(block.render());
 */

import { BaseBlock } from './BaseBlock.js';

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
    
    // Event listeners
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

  /**
   * Set text alignment
   * @param {string} alignment - 'left', 'center', 'right'
   */
  setAlignment(alignment) {
    this.data.alignment = alignment;
    if (this.wrapper) {
      this.wrapper.setAttribute('data-alignment', alignment);
    }
  }

  /**
   * Basic HTML sanitization
   * @param {string} html
   * @returns {string}
   * @private
   */
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
