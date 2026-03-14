/**
 * DOM - DOM manipulation utilities
 * 
 * Helper functions for common DOM operations.
 * 
 * @example
 * const el = dom.create('div', { class: 'my-class' }, 'Hello');
 * dom.append(parent, el);
 */

export const dom = {
  /**
   * Create an element with attributes and content
   * @param {string} tag - Tag name
   * @param {Object} attrs - Attributes object
   * @param {string|Node|Node[]} content - Content (text, node, or array of nodes)
   * @returns {HTMLElement}
   */
  create(tag, attrs = {}, content = null) {
    const el = document.createElement(tag);

    // Set attributes
    Object.entries(attrs).forEach(([key, value]) => {
      if (key === 'class') {
        el.className = value;
      } else if (key === 'style' && typeof value === 'object') {
        Object.assign(el.style, value);
      } else if (key.startsWith('data-')) {
        el.setAttribute(key, value);
      } else {
        el[key] = value;
      }
    });

    // Set content
    if (content !== null) {
      this.setContent(el, content);
    }

    return el;
  },

  /**
   * Set element content
   * @param {HTMLElement} el - Element
   * @param {string|Node|Node[]} content - Content
   */
  setContent(el, content) {
    if (typeof content === 'string') {
      el.textContent = content;
    } else if (content instanceof Node) {
      el.appendChild(content);
    } else if (Array.isArray(content)) {
      content.forEach(child => {
        if (child instanceof Node) {
          el.appendChild(child);
        }
      });
    }
  },

  /**
   * Append children to parent
   * @param {HTMLElement} parent - Parent element
   * @param {...Node} children - Child nodes
   */
  append(parent, ...children) {
    children.forEach(child => {
      if (child instanceof Node) {
        parent.appendChild(child);
      }
    });
  },

  /**
   * Prepend children to parent
   * @param {HTMLElement} parent - Parent element
   * @param {...Node} children - Child nodes
   */
  prepend(parent, ...children) {
    children.reverse().forEach(child => {
      if (child instanceof Node) {
        parent.insertBefore(child, parent.firstChild);
      }
    });
  },

  /**
   * Remove element from DOM
   * @param {HTMLElement} el - Element to remove
   */
  remove(el) {
    if (el && el.parentNode) {
      el.parentNode.removeChild(el);
    }
  },

  /**
   * Find closest ancestor matching selector
   * @param {HTMLElement} el - Starting element
   * @param {string} selector - CSS selector
   * @returns {HTMLElement|null}
   */
  closest(el, selector) {
    return el.closest(selector);
  },

  /**
   * Find all elements matching selector
   * @param {string} selector - CSS selector
   * @param {HTMLElement} context - Search context (default: document)
   * @returns {HTMLElement[]}
   */
  findAll(selector, context = document) {
    return Array.from(context.querySelectorAll(selector));
  },

  /**
   * Find first element matching selector
   * @param {string} selector - CSS selector
   * @param {HTMLElement} context - Search context (default: document)
   * @returns {HTMLElement|null}
   */
  find(selector, context = document) {
    return context.querySelector(selector);
  },

  /**
   * Check if element matches selector
   * @param {HTMLElement} el - Element
   * @param {string} selector - CSS selector
   * @returns {boolean}
   */
  matches(el, selector) {
    return el.matches(selector);
  },

  /**
   * Get element offset relative to document
   * @param {HTMLElement} el - Element
   * @returns {{top: number, left: number}}
   */
  offset(el) {
    const rect = el.getBoundingClientRect();
    return {
      top: rect.top + window.pageYOffset,
      left: rect.left + window.pageXOffset
    };
  },

  /**
   * Get element position relative to parent
   * @param {HTMLElement} el - Element
   * @returns {{top: number, left: number}}
   */
  position(el) {
    return {
      top: el.offsetTop,
      left: el.offsetLeft
    };
  },

  /**
   * Add event listener with optional delegation
   * @param {HTMLElement} el - Element
   * @param {string} event - Event name
   * @param {string|Function} selectorOrHandler - Selector for delegation or handler function
   * @param {Function} handler - Handler function (if delegation)
   * @returns {Function} Cleanup function
   */
  on(el, event, selectorOrHandler, handler) {
    if (typeof selectorOrHandler === 'function') {
      // Direct binding
      el.addEventListener(event, selectorOrHandler);
      return () => el.removeEventListener(event, selectorOrHandler);
    } else {
      // Delegated binding
      const delegatedHandler = (e) => {
        const target = e.target.closest(selectorOrHandler);
        if (target) {
          handler.call(target, e);
        }
      };
      el.addEventListener(event, delegatedHandler);
      return () => el.removeEventListener(event, delegatedHandler);
    }
  },

  /**
   * Trigger custom event
   * @param {HTMLElement} el - Element
   * @param {string} event - Event name
   * @param {*} detail - Event detail data
   */
  trigger(el, event, detail = null) {
    const evt = new CustomEvent(event, {
      detail,
      bubbles: true,
      cancelable: true
    });
    el.dispatchEvent(evt);
  },

  /**
   * Add class(es) to element
   * @param {HTMLElement} el - Element
   * @param {...string} classes - Class names
   */
  addClass(el, ...classes) {
    el.classList.add(...classes);
  },

  /**
   * Remove class(es) from element
   * @param {HTMLElement} el - Element
   * @param {...string} classes - Class names
   */
  removeClass(el, ...classes) {
    el.classList.remove(...classes);
  },

  /**
   * Toggle class on element
   * @param {HTMLElement} el - Element
   * @param {string} className - Class name
   * @param {boolean} force - Force add/remove
   */
  toggleClass(el, className, force) {
    el.classList.toggle(className, force);
  },

  /**
   * Check if element has class
   * @param {HTMLElement} el - Element
   * @param {string} className - Class name
   * @returns {boolean}
   */
  hasClass(el, className) {
    return el.classList.contains(className);
  }
};
