/**
 * Keyboard - Keyboard event handling utilities
 * 
 * Helper functions for keyboard shortcuts and key detection.
 * 
 * @example
 * keyboard.on(element, 'mod+s', (e) => {
 *   e.preventDefault();
 *   save();
 * });
 */

export const keyboard = {
  /**
   * Key codes
   */
  keys: {
    BACKSPACE: 8,
    TAB: 9,
    ENTER: 13,
    SHIFT: 16,
    CTRL: 17,
    ALT: 18,
    ESC: 27,
    SPACE: 32,
    LEFT: 37,
    UP: 38,
    RIGHT: 39,
    DOWN: 40,
    DELETE: 46,
    CMD: 91
  },

  /**
   * Check if key is modifier (Ctrl, Shift, Alt, Cmd)
   * @param {KeyboardEvent} e - Keyboard event
   * @returns {boolean}
   */
  isModifier(e) {
    return e.ctrlKey || e.shiftKey || e.altKey || e.metaKey;
  },

  /**
   * Check if "mod" key is pressed (Ctrl on Windows/Linux, Cmd on Mac)
   * @param {KeyboardEvent} e - Keyboard event
   * @returns {boolean}
   */
  isMod(e) {
    return this.isMac() ? e.metaKey : e.ctrlKey;
  },

  /**
   * Check if running on Mac
   * @returns {boolean}
   */
  isMac() {
    return /Mac|iPhone|iPod|iPad/i.test(navigator.platform);
  },

  /**
   * Parse keyboard shortcut string (e.g., 'mod+s', 'ctrl+shift+enter')
   * @param {string} shortcut - Shortcut string
   * @returns {Object} Parsed shortcut
   * @private
   */
  _parseShortcut(shortcut) {
    const parts = shortcut.toLowerCase().split('+');
    const key = parts.pop();
    const modifiers = {
      ctrl: parts.includes('ctrl'),
      shift: parts.includes('shift'),
      alt: parts.includes('alt'),
      meta: parts.includes('meta') || parts.includes('cmd'),
      mod: parts.includes('mod')
    };

    return { key, modifiers };
  },

  /**
   * Check if event matches shortcut
   * @param {KeyboardEvent} e - Keyboard event
   * @param {string} shortcut - Shortcut string
   * @returns {boolean}
   */
  matches(e, shortcut) {
    const parsed = this._parseShortcut(shortcut);
    const eventKey = e.key.toLowerCase();

    // Check key
    if (eventKey !== parsed.key) return false;

    // Check modifiers
    if (parsed.modifiers.mod) {
      if (!this.isMod(e)) return false;
    } else {
      if (parsed.modifiers.ctrl && !e.ctrlKey) return false;
      if (parsed.modifiers.meta && !e.metaKey) return false;
    }

    if (parsed.modifiers.shift && !e.shiftKey) return false;
    if (parsed.modifiers.alt && !e.altKey) return false;

    return true;
  },

  /**
   * Bind keyboard shortcut to handler
   * @param {HTMLElement} element - Element to bind to
   * @param {string} shortcut - Shortcut string (e.g., 'mod+s')
   * @param {Function} handler - Handler function
   * @returns {Function} Cleanup function
   */
  on(element, shortcut, handler) {
    const listener = (e) => {
      if (this.matches(e, shortcut)) {
        handler(e);
      }
    };

    element.addEventListener('keydown', listener);
    return () => element.removeEventListener('keydown', listener);
  },

  /**
   * Check if cursor is at start of element
   * @param {HTMLElement} element - Element to check
   * @returns {boolean}
   */
  isCursorAtStart(element) {
    const sel = window.getSelection();
    if (!sel || sel.rangeCount === 0) return false;

    const range = sel.getRangeAt(0);
    if (range.startOffset !== 0) return false;

    let node = range.startContainer;
    if (node.nodeType === Node.TEXT_NODE) {
      node = node.parentElement;
    }

    // Check if we're at the first text node
    while (node && node !== element) {
      if (node.previousSibling) return false;
      node = node.parentElement;
    }

    return true;
  },

  /**
   * Check if cursor is at end of element
   * @param {HTMLElement} element - Element to check
   * @returns {boolean}
   */
  isCursorAtEnd(element) {
    const sel = window.getSelection();
    if (!sel || sel.rangeCount === 0) return false;

    const range = sel.getRangeAt(0);
    let node = range.endContainer;

    if (node.nodeType === Node.TEXT_NODE) {
      if (range.endOffset !== node.length) return false;
      node = node.parentElement;
    }

    // Check if we're at the last text node
    while (node && node !== element) {
      if (node.nextSibling) return false;
      node = node.parentElement;
    }

    return true;
  },

  /**
   * Get pressed keys as string (e.g., 'Ctrl+S')
   * @param {KeyboardEvent} e - Keyboard event
   * @returns {string}
   */
  getShortcutString(e) {
    const parts = [];

    if (e.ctrlKey) parts.push('Ctrl');
    if (e.shiftKey) parts.push('Shift');
    if (e.altKey) parts.push('Alt');
    if (e.metaKey) parts.push(this.isMac() ? 'Cmd' : 'Meta');

    if (e.key && e.key.length === 1) {
      parts.push(e.key.toUpperCase());
    } else if (e.key) {
      parts.push(e.key);
    }

    return parts.join('+');
  },

  /**
   * Prevent default for common editor shortcuts
   * @param {KeyboardEvent} e - Keyboard event
   */
  preventEditorDefaults(e) {
    const shortcuts = [
      'mod+b', 'mod+i', 'mod+u',  // Format
      'mod+s',                     // Save
      'mod+z', 'mod+shift+z',      // Undo/Redo
      'mod+a'                      // Select all (sometimes)
    ];

    for (const shortcut of shortcuts) {
      if (this.matches(e, shortcut)) {
        e.preventDefault();
        return true;
      }
    }

    return false;
  }
};
