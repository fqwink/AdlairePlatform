/**
 * HistoryManager - Undo/Redo functionality for editor
 * 
 * Manages editor history with configurable limits.
 * 
 * @example
 * const history = new HistoryManager(50);
 * history.push(editorState);
 * history.undo(); // Returns previous state
 * history.redo(); // Returns next state
 */

export class HistoryManager {
  constructor(limit = 50) {
    this.limit = limit;
    this.stack = [];
    this.position = -1;
  }

  /**
   * Push a new state to history
   * @param {*} state - State to save (usually serialized blocks)
   */
  push(state) {
    // Remove any states after current position (when new action after undo)
    if (this.position < this.stack.length - 1) {
      this.stack = this.stack.slice(0, this.position + 1);
    }

    // Add new state
    this.stack.push(this._clone(state));
    this.position++;

    // Enforce limit
    if (this.stack.length > this.limit) {
      this.stack.shift();
      this.position--;
    }
  }

  /**
   * Undo - get previous state
   * @returns {*|null} Previous state, or null if at start
   */
  undo() {
    if (!this.canUndo()) return null;
    
    this.position--;
    return this._clone(this.stack[this.position]);
  }

  /**
   * Redo - get next state
   * @returns {*|null} Next state, or null if at end
   */
  redo() {
    if (!this.canRedo()) return null;
    
    this.position++;
    return this._clone(this.stack[this.position]);
  }

  /**
   * Check if undo is possible
   * @returns {boolean}
   */
  canUndo() {
    return this.position > 0;
  }

  /**
   * Check if redo is possible
   * @returns {boolean}
   */
  canRedo() {
    return this.position < this.stack.length - 1;
  }

  /**
   * Get current state without modifying position
   * @returns {*|null}
   */
  getCurrent() {
    if (this.position < 0) return null;
    return this._clone(this.stack[this.position]);
  }

  /**
   * Clear all history
   */
  clear() {
    this.stack = [];
    this.position = -1;
  }

  /**
   * Get history info
   * @returns {Object} { size, position, canUndo, canRedo, limit }
   */
  getInfo() {
    return {
      size: this.stack.length,
      position: this.position,
      canUndo: this.canUndo(),
      canRedo: this.canRedo(),
      limit: this.limit
    };
  }

  /**
   * Set history limit
   * @param {number} newLimit - New limit
   */
  setLimit(newLimit) {
    this.limit = newLimit;
    
    // Trim stack if needed
    if (this.stack.length > newLimit) {
      const overflow = this.stack.length - newLimit;
      this.stack = this.stack.slice(overflow);
      this.position = Math.max(0, this.position - overflow);
    }
  }

  /**
   * Deep clone helper
   * @param {*} obj - Object to clone
   * @returns {*} Cloned object
   * @private
   */
  _clone(obj) {
    if (obj === null || typeof obj !== 'object') return obj;
    
    try {
      // Use JSON for simple deep clone
      return JSON.parse(JSON.stringify(obj));
    } catch (error) {
      console.warn('[HistoryManager] Failed to clone state, returning reference');
      return obj;
    }
  }

  /**
   * Replace current state (without adding new history entry)
   * Useful for minor updates that shouldn't create undo point
   * @param {*} state - State to replace current with
   */
  replaceCurrent(state) {
    if (this.position >= 0 && this.position < this.stack.length) {
      this.stack[this.position] = this._clone(state);
    }
  }

  /**
   * Get state at specific position
   * @param {number} pos - Position in stack
   * @returns {*|null}
   */
  getAt(pos) {
    if (pos < 0 || pos >= this.stack.length) return null;
    return this._clone(this.stack[pos]);
  }
}
