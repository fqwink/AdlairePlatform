/**
 * Sanitizer - HTML sanitization utility
 * 
 * Removes dangerous tags, attributes, and scripts from HTML.
 * 
 * @example
 * const clean = sanitizer.clean('<script>alert("xss")</script><p>Hello</p>');
 * // Returns: '<p>Hello</p>'
 */

export const sanitizer = {
  /**
   * Allowed HTML tags
   */
  allowedTags: [
    'p', 'br', 'span', 'div',
    'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
    'strong', 'b', 'em', 'i', 'u', 's', 'mark', 'code',
    'a', 'ul', 'ol', 'li',
    'blockquote', 'pre',
    'table', 'thead', 'tbody', 'tr', 'th', 'td',
    'img'
  ],

  /**
   * Allowed attributes per tag
   */
  allowedAttributes: {
    'a': ['href', 'title', 'target', 'rel'],
    'img': ['src', 'alt', 'title', 'width', 'height'],
    'td': ['colspan', 'rowspan'],
    'th': ['colspan', 'rowspan'],
    '*': ['class', 'id', 'data-*']
  },

  /**
   * Clean HTML string
   * @param {string} html - HTML to sanitize
   * @returns {string} Cleaned HTML
   */
  clean(html) {
    if (!html || typeof html !== 'string') return '';

    const temp = document.createElement('div');
    temp.innerHTML = html;

    this._cleanNode(temp);

    return temp.innerHTML;
  },

  /**
   * Recursively clean a DOM node
   * @param {Node} node - DOM node to clean
   * @private
   */
  _cleanNode(node) {
    const nodesToRemove = [];

    // Process child nodes
    Array.from(node.childNodes).forEach(child => {
      if (child.nodeType === Node.ELEMENT_NODE) {
        const tagName = child.tagName.toLowerCase();

        // Check if tag is allowed
        if (!this.allowedTags.includes(tagName)) {
          nodesToRemove.push(child);
          return;
        }

        // Clean attributes
        this._cleanAttributes(child);

        // Recursively clean children
        this._cleanNode(child);
      } else if (child.nodeType === Node.TEXT_NODE) {
        // Text nodes are safe
      } else {
        // Remove comments and other node types
        nodesToRemove.push(child);
      }
    });

    // Remove flagged nodes
    nodesToRemove.forEach(node => node.remove());
  },

  /**
   * Clean element attributes
   * @param {Element} element - DOM element
   * @private
   */
  _cleanAttributes(element) {
    const tagName = element.tagName.toLowerCase();
    const allowedAttrs = this.allowedAttributes[tagName] || [];
    const globalAttrs = this.allowedAttributes['*'] || [];
    const allAllowed = [...allowedAttrs, ...globalAttrs];

    // Get all attributes
    const attributesToRemove = [];
    Array.from(element.attributes).forEach(attr => {
      const attrName = attr.name.toLowerCase();

      // Check if attribute is allowed
      let isAllowed = false;

      for (const allowed of allAllowed) {
        if (allowed.endsWith('*')) {
          // Wildcard match (e.g., 'data-*')
          const prefix = allowed.slice(0, -1);
          if (attrName.startsWith(prefix)) {
            isAllowed = true;
            break;
          }
        } else if (attrName === allowed) {
          isAllowed = true;
          break;
        }
      }

      if (!isAllowed) {
        attributesToRemove.push(attrName);
      } else {
        // Check for dangerous values
        if (this._isDangerousAttributeValue(attrName, attr.value)) {
          attributesToRemove.push(attrName);
        }
      }
    });

    // Remove flagged attributes
    attributesToRemove.forEach(name => element.removeAttribute(name));
  },

  /**
   * Check if attribute value is dangerous
   * @param {string} name - Attribute name
   * @param {string} value - Attribute value
   * @returns {boolean}
   * @private
   */
  _isDangerousAttributeValue(name, value) {
    if (!value) return false;

    const lowerValue = value.toLowerCase().trim();

    // Check for javascript: protocol
    if (name === 'href' || name === 'src') {
      if (lowerValue.startsWith('javascript:') ||
          lowerValue.startsWith('data:text/html') ||
          lowerValue.startsWith('vbscript:')) {
        return true;
      }
    }

    // Check for event handlers
    if (name.startsWith('on')) {
      return true;
    }

    return false;
  },

  /**
   * Strip all HTML tags
   * @param {string} html - HTML string
   * @returns {string} Plain text
   */
  stripTags(html) {
    const temp = document.createElement('div');
    temp.innerHTML = html;
    return temp.textContent || temp.innerText || '';
  },

  /**
   * Escape HTML special characters
   * @param {string} text - Text to escape
   * @returns {string} Escaped text
   */
  escape(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  },

  /**
   * Unescape HTML entities
   * @param {string} html - HTML with entities
   * @returns {string} Unescaped text
   */
  unescape(html) {
    const div = document.createElement('div');
    div.innerHTML = html;
    return div.textContent || div.innerText || '';
  }
};
