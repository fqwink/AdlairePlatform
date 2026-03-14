/**
 * HeadingBlock - Heading block (h2, h3)
 * 
 * Heading block with configurable level.
 * 
 * @example
 * const block = new HeadingBlock({ text: 'Chapter 1', level: 2 });
 * document.body.appendChild(block.render());
 */

import { BaseBlock } from './BaseBlock.js';

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
      level: this.data.level,
      alignment: this.data.alignment
    };
  }

  validate(data) {
    return (
      typeof data.text === 'string' &&
      [2, 3].includes(data.level)
    );
  }

  /**
   * Set heading level
   * @param {number} level - 2 or 3
   */
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
      title: 'Heading',
      icon: '<svg width="16" height="16"><text x="0" y="14" font-size="14" font-weight="bold">H</text></svg>',
      supportsInlineTools: true
    };
  }

  static get type() {
    return 'heading';
  }
}
