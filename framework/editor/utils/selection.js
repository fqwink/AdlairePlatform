/**
 * Selection - Selection and range manipulation utilities
 * 
 * Helper functions for working with text selections and ranges.
 * 
 * @example
 * const range = selection.getRange();
 * selection.save();
 * // ... do something ...
 * selection.restore();
 */

export const selection = {
  /**
   * Saved selection state
   * @private
   */
  _saved: null,

  /**
   * Get current selection
   * @returns {Selection|null}
   */
  get() {
    return window.getSelection();
  },

  /**
   * Get current range
   * @returns {Range|null}
   */
  getRange() {
    const sel = this.get();
    if (!sel || sel.rangeCount === 0) return null;
    return sel.getRangeAt(0);
  },

  /**
   * Save current selection
   * @returns {Object|null} Saved selection state
   */
  save() {
    const range = this.getRange();
    if (!range) {
      this._saved = null;
      return null;
    }

    this._saved = {
      startContainer: range.startContainer,
      startOffset: range.startOffset,
      endContainer: range.endContainer,
      endOffset: range.endOffset
    };

    return this._saved;
  },

  /**
   * Restore saved selection
   * @returns {boolean} True if restored successfully
   */
  restore() {
    if (!this._saved) return false;

    try {
      const range = document.createRange();
      range.setStart(this._saved.startContainer, this._saved.startOffset);
      range.setEnd(this._saved.endContainer, this._saved.endOffset);

      const sel = this.get();
      sel.removeAllRanges();
      sel.addRange(range);

      return true;
    } catch (error) {
      console.warn('[Selection] Failed to restore selection:', error);
      return false;
    }
  },

  /**
   * Clear selection
   */
  clear() {
    const sel = this.get();
    if (sel) {
      sel.removeAllRanges();
    }
  },

  /**
   * Select all content in element
   * @param {HTMLElement} element - Element to select
   */
  selectAll(element) {
    const range = document.createRange();
    range.selectNodeContents(element);

    const sel = this.get();
    sel.removeAllRanges();
    sel.addRange(range);
  },

  /**
   * Get selected text
   * @returns {string}
   */
  getText() {
    const sel = this.get();
    return sel ? sel.toString() : '';
  },

  /**
   * Check if selection is inside element
   * @param {HTMLElement} element - Element to check
   * @returns {boolean}
   */
  isInsideElement(element) {
    const sel = this.get();
    if (!sel || sel.rangeCount === 0) return false;

    const range = sel.getRangeAt(0);
    return element.contains(range.commonAncestorContainer);
  },

  /**
   * Get selected element (if selection is within a single element)
   * @returns {HTMLElement|null}
   */
  getSelectedElement() {
    const range = this.getRange();
    if (!range) return null;

    let container = range.commonAncestorContainer;
    if (container.nodeType === Node.TEXT_NODE) {
      container = container.parentElement;
    }

    return container;
  },

  /**
   * Check if there is a selection
   * @returns {boolean}
   */
  hasSelection() {
    const sel = this.get();
    return sel && !sel.isCollapsed && sel.toString().length > 0;
  },

  /**
   * Check if selection is collapsed (cursor)
   * @returns {boolean}
   */
  isCollapsed() {
    const sel = this.get();
    return sel ? sel.isCollapsed : true;
  },

  /**
   * Get selection bounding rect
   * @returns {DOMRect|null}
   */
  getRect() {
    const range = this.getRange();
    return range ? range.getBoundingClientRect() : null;
  },

  /**
   * Set selection to specific range
   * @param {Node} startNode - Start node
   * @param {number} startOffset - Start offset
   * @param {Node} endNode - End node
   * @param {number} endOffset - End offset
   */
  setRange(startNode, startOffset, endNode, endOffset) {
    const range = document.createRange();
    range.setStart(startNode, startOffset);
    range.setEnd(endNode, endOffset);

    const sel = this.get();
    sel.removeAllRanges();
    sel.addRange(range);
  },

  /**
   * Collapse selection to start or end
   * @param {boolean} toStart - If true, collapse to start; otherwise to end
   */
  collapse(toStart = false) {
    const sel = this.get();
    if (sel) {
      sel.collapseToEnd();
      if (toStart) {
        sel.collapseToStart();
      }
    }
  },

  /**
   * Wrap selection with element
   * @param {HTMLElement} element - Wrapper element
   * @returns {boolean} True if wrapped successfully
   */
  wrap(element) {
    const range = this.getRange();
    if (!range || range.collapsed) return false;

    try {
      range.surroundContents(element);
      return true;
    } catch (error) {
      console.warn('[Selection] Failed to wrap selection:', error);
      return false;
    }
  },

  /**
   * Insert node at cursor position
   * @param {Node} node - Node to insert
   */
  insertNode(node) {
    const range = this.getRange();
    if (!range) return;

    range.deleteContents();
    range.insertNode(node);
    
    // Move cursor after inserted node
    range.setStartAfter(node);
    range.setEndAfter(node);
    
    const sel = this.get();
    sel.removeAllRanges();
    sel.addRange(range);
  },

  /**
   * Insert HTML at cursor position
   * @param {string} html - HTML string to insert
   */
  insertHTML(html) {
    const range = this.getRange();
    if (!range) return;

    const fragment = range.createContextualFragment(html);
    range.deleteContents();
    range.insertNode(fragment);
  }
};
