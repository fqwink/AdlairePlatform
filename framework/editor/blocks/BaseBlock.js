/**
 * BaseBlock - Abstract base class for all editor blocks
 * 
 * All block types must extend this class and implement required methods.
 * 
 * @example
 * class ParagraphBlock extends BaseBlock {
 *   render() {
 *     const wrapper = document.createElement('div');
 *     wrapper.innerHTML = `<p contenteditable="true">${this.data.text || ''}</p>`;
 *     return wrapper;
 *   }
 *   
 *   save() {
 *     return {
 *       text: this.wrapper.querySelector('p').textContent
 *     };
 *   }
 * }
 */

export class BaseBlock {
  /**
   * @param {Object} data - Block data
   * @param {Object} config - Editor configuration
   */
  constructor(data = {}, config = {}) {
    this.data = data;
    this.config = config;
    this.wrapper = null;
  }

  /**
   * Render block DOM (must be implemented by subclass)
   * @returns {HTMLElement} Block wrapper element
   * @abstract
   */
  render() {
    throw new Error('[BaseBlock] render() must be implemented by subclass');
  }

  /**
   * Save block data (optional, can be overridden)
   * @returns {Object} Block data
   */
  save() {
    return { ...this.data };
  }

  /**
   * Validate block data (optional, can be overridden)
   * @param {Object} data - Data to validate
   * @returns {boolean} True if valid
   */
  validate(data) {
    return true;
  }

  /**
   * Called when block gains focus (optional)
   */
  onFocus() {
    // Override in subclass if needed
  }

  /**
   * Called when block loses focus (optional)
   */
  onBlur() {
    // Override in subclass if needed
  }

  /**
   * Called when block is destroyed (optional)
   */
  onDestroy() {
    // Override in subclass if needed
  }

  /**
   * Get block configuration (static, can be overridden)
   * @returns {Object} Block config
   * @static
   */
  static get config() {
    return {
      title: 'Base Block',
      icon: '',
      supportsInlineTools: true
    };
  }

  /**
   * Get block type name (static, should be overridden)
   * @returns {string}
   * @static
   */
  static get type() {
    return 'base';
  }
}
