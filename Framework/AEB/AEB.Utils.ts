/// <reference lib="dom" />

/**
 * AEB (Adlaire Editor & Blocks) - Utils Module
 *
 * @file Framework/AEB/AEB.Utils.ts
 * @version 1.0.0
 *
 * Utility functions: sanitizer, dom, selection, keyboard
 */

/**
 * SavedSelection - Represents a saved selection state
 */
export interface SavedSelection {
  startContainer: Node;
  startOffset: number;
  endContainer: Node;
  endOffset: number;
}

/**
 * sanitizer - HTML sanitization utility
 */
export const sanitizer = {
  allowedTags: [
    'p', 'br', 'span', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
    'strong', 'b', 'em', 'i', 'u', 's', 'mark', 'code',
    'a', 'ul', 'ol', 'li', 'blockquote', 'pre',
    'table', 'thead', 'tbody', 'tr', 'th', 'td', 'img'
  ] as string[],
  allowedAttributes: {
    'a': ['href', 'title', 'target', 'rel'],
    'img': ['src', 'alt', 'title', 'width', 'height'],
    'td': ['colspan', 'rowspan'],
    'th': ['colspan', 'rowspan'],
    '*': ['class', 'id', 'data-*']
  } as Record<string, string[]>,
  clean(html: string): string {
    if (!html || typeof html !== 'string') return '';
    const temp = document.createElement('div');
    temp.innerHTML = html;
    this._cleanNode(temp);
    return temp.innerHTML;
  },
  _cleanNode(node: Element): void {
    const nodesToRemove: ChildNode[] = [];
    Array.from(node.childNodes).forEach(child => {
      if (child.nodeType === Node.ELEMENT_NODE) {
        const el = child as Element;
        const tagName = el.tagName.toLowerCase();
        if (!this.allowedTags.includes(tagName)) {
          nodesToRemove.push(child);
          return;
        }
        this._cleanAttributes(el);
        this._cleanNode(el);
      } else if (child.nodeType !== Node.TEXT_NODE) {
        nodesToRemove.push(child);
      }
    });
    nodesToRemove.forEach(node => node.remove());
  },
  _cleanAttributes(element: Element): void {
    const tagName = element.tagName.toLowerCase();
    const allowedAttrs = this.allowedAttributes[tagName] || [];
    const globalAttrs = this.allowedAttributes['*'] || [];
    const allAllowed = [...allowedAttrs, ...globalAttrs];
    const attributesToRemove: string[] = [];
    Array.from(element.attributes).forEach(attr => {
      const attrName = attr.name.toLowerCase();
      let isAllowed = false;
      for (const allowed of allAllowed) {
        if (allowed.endsWith('*')) {
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
      if (!isAllowed || this._isDangerousAttributeValue(attrName, attr.value)) {
        attributesToRemove.push(attrName);
      }
    });
    attributesToRemove.forEach(name => element.removeAttribute(name));
  },
  _isDangerousAttributeValue(name: string, value: string): boolean {
    if (!value) return false;
    const lowerValue = value.toLowerCase().trim();
    if (name === 'href' || name === 'src') {
      if (lowerValue.startsWith('javascript:') ||
          lowerValue.startsWith('data:text/html') ||
          lowerValue.startsWith('vbscript:')) {
        return true;
      }
    }
    if (name.startsWith('on')) {
      return true;
    }
    return false;
  },
  stripTags(html: string): string {
    const temp = document.createElement('div');
    temp.innerHTML = html;
    return temp.textContent || temp.innerText || '';
  },
  escape(text: string): string {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  },
  unescape(html: string): string {
    const div = document.createElement('div');
    div.innerHTML = html;
    return div.textContent || div.innerText || '';
  }
};

/**
 * dom - DOM manipulation utilities
 */
export const dom = {
  create(tag: string, attrs: Record<string, unknown> = {}, content: string | Node | Node[] | null = null): HTMLElement {
    const el = document.createElement(tag);
    Object.entries(attrs).forEach(([key, value]) => {
      if (key === 'class') {
        el.className = value as string;
      } else if (key === 'style' && typeof value === 'object') {
        Object.assign(el.style, value);
      } else if (key.startsWith('data-')) {
        el.setAttribute(key, value as string);
      } else {
        (el as unknown as Record<string, unknown>)[key] = value;
      }
    });
    if (content !== null) {
      this.setContent(el, content);
    }
    return el;
  },
  setContent(el: HTMLElement, content: string | Node | Node[]): void {
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
  append(parent: HTMLElement, ...children: Node[]): void {
    children.forEach(child => {
      if (child instanceof Node) {
        parent.appendChild(child);
      }
    });
  },
  prepend(parent: HTMLElement, ...children: Node[]): void {
    children.reverse().forEach(child => {
      if (child instanceof Node) {
        parent.insertBefore(child, parent.firstChild);
      }
    });
  },
  remove(el: Node | null): void {
    if (el && el.parentNode) {
      el.parentNode.removeChild(el);
    }
  },
  closest(el: Element, selector: string): Element | null {
    return el.closest(selector);
  },
  findAll(selector: string, context: Document | Element = document): Element[] {
    return Array.from(context.querySelectorAll(selector));
  },
  find(selector: string, context: Document | Element = document): Element | null {
    return context.querySelector(selector);
  },
  matches(el: Element, selector: string): boolean {
    return el.matches(selector);
  },
  offset(el: HTMLElement): { top: number; left: number } {
    const rect = el.getBoundingClientRect();
    return {
      top: rect.top + window.pageYOffset,
      left: rect.left + window.pageXOffset
    };
  },
  position(el: HTMLElement): { top: number; left: number } {
    return {
      top: el.offsetTop,
      left: el.offsetLeft
    };
  },
  on(el: HTMLElement, event: string, selectorOrHandler: string | EventListener, handler?: EventListener): () => void {
    if (typeof selectorOrHandler === 'function') {
      el.addEventListener(event, selectorOrHandler);
      return () => el.removeEventListener(event, selectorOrHandler);
    } else {
      const delegatedHandler: EventListener = (e: Event) => {
        const target = (e.target as Element).closest(selectorOrHandler);
        if (target && handler) {
          handler.call(target, e);
        }
      };
      el.addEventListener(event, delegatedHandler);
      return () => el.removeEventListener(event, delegatedHandler);
    }
  },
  trigger(el: HTMLElement, event: string, detail: unknown = null): void {
    const evt = new CustomEvent(event, {
      detail,
      bubbles: true,
      cancelable: true
    });
    el.dispatchEvent(evt);
  },
  addClass(el: HTMLElement, ...classes: string[]): void {
    el.classList.add(...classes);
  },
  removeClass(el: HTMLElement, ...classes: string[]): void {
    el.classList.remove(...classes);
  },
  toggleClass(el: HTMLElement, className: string, force?: boolean): void {
    el.classList.toggle(className, force);
  },
  hasClass(el: HTMLElement, className: string): boolean {
    return el.classList.contains(className);
  }
};

/**
 * selection - Selection and range manipulation utilities
 */
export const selection = {
  _saved: null as SavedSelection | null,
  get(): Selection | null {
    return window.getSelection();
  },
  getRange(): Range | null {
    const sel = this.get();
    if (!sel || sel.rangeCount === 0) return null;
    return sel.getRangeAt(0);
  },
  save(): SavedSelection | null {
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
  restore(): boolean {
    if (!this._saved) return false;
    try {
      const range = document.createRange();
      range.setStart(this._saved.startContainer, this._saved.startOffset);
      range.setEnd(this._saved.endContainer, this._saved.endOffset);
      const sel = this.get();
      sel!.removeAllRanges();
      sel!.addRange(range);
      return true;
    } catch (error) {
      console.warn('[Selection] Failed to restore selection:', error);
      return false;
    }
  },
  clear(): void {
    const sel = this.get();
    if (sel) {
      sel.removeAllRanges();
    }
  },
  selectAll(element: Node): void {
    const range = document.createRange();
    range.selectNodeContents(element);
    const sel = this.get();
    sel!.removeAllRanges();
    sel!.addRange(range);
  },
  getText(): string {
    const sel = this.get();
    return sel ? sel.toString() : '';
  },
  isInsideElement(element: HTMLElement): boolean {
    const sel = this.get();
    if (!sel || sel.rangeCount === 0) return false;
    const range = sel.getRangeAt(0);
    return element.contains(range.commonAncestorContainer);
  },
  getSelectedElement(): Element | null {
    const range = this.getRange();
    if (!range) return null;
    let container: Node = range.commonAncestorContainer;
    if (container.nodeType === Node.TEXT_NODE) {
      container = container.parentElement!;
    }
    return container as Element;
  },
  hasSelection(): boolean {
    const sel = this.get();
    return sel !== null && !sel.isCollapsed && sel.toString().length > 0;
  },
  isCollapsed(): boolean {
    const sel = this.get();
    return sel ? sel.isCollapsed : true;
  },
  getRect(): DOMRect | null {
    const range = this.getRange();
    return range ? range.getBoundingClientRect() : null;
  },
  setRange(startNode: Node, startOffset: number, endNode: Node, endOffset: number): void {
    const range = document.createRange();
    range.setStart(startNode, startOffset);
    range.setEnd(endNode, endOffset);
    const sel = this.get();
    sel!.removeAllRanges();
    sel!.addRange(range);
  },
  collapse(toStart: boolean = false): void {
    const sel = this.get();
    if (sel) {
      sel.collapseToEnd();
      if (toStart) {
        sel.collapseToStart();
      }
    }
  },
  wrap(element: HTMLElement): boolean {
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
  insertNode(node: Node): void {
    const range = this.getRange();
    if (!range) return;
    range.deleteContents();
    range.insertNode(node);
    range.setStartAfter(node);
    range.setEndAfter(node);
    const sel = this.get();
    sel!.removeAllRanges();
    sel!.addRange(range);
  },
  insertHTML(html: string): void {
    const range = this.getRange();
    if (!range) return;
    const fragment = range.createContextualFragment(html);
    range.deleteContents();
    range.insertNode(fragment);
  }
};

/**
 * ParsedShortcut - Represents a parsed keyboard shortcut
 */
interface ParsedShortcut {
  key: string;
  modifiers: {
    ctrl: boolean;
    shift: boolean;
    alt: boolean;
    meta: boolean;
    mod: boolean;
  };
}

/**
 * keyboard - Keyboard event handling utilities
 */
export const keyboard = {
  keys: {
    BACKSPACE: 8, TAB: 9, ENTER: 13, SHIFT: 16, CTRL: 17, ALT: 18,
    ESC: 27, SPACE: 32, LEFT: 37, UP: 38, RIGHT: 39, DOWN: 40,
    DELETE: 46, CMD: 91
  } as Record<string, number>,
  isModifier(e: KeyboardEvent): boolean {
    return e.ctrlKey || e.shiftKey || e.altKey || e.metaKey;
  },
  isMod(e: KeyboardEvent): boolean {
    return this.isMac() ? e.metaKey : e.ctrlKey;
  },
  isMac(): boolean {
    return /Mac|iPhone|iPod|iPad/i.test(navigator.platform);
  },
  _parseShortcut(shortcut: string): ParsedShortcut {
    const parts = shortcut.toLowerCase().split('+');
    const key = parts.pop()!;
    const modifiers = {
      ctrl: parts.includes('ctrl'),
      shift: parts.includes('shift'),
      alt: parts.includes('alt'),
      meta: parts.includes('meta') || parts.includes('cmd'),
      mod: parts.includes('mod')
    };
    return { key, modifiers };
  },
  matches(e: KeyboardEvent, shortcut: string): boolean {
    const parsed = this._parseShortcut(shortcut);
    const eventKey = e.key.toLowerCase();
    if (eventKey !== parsed.key) return false;
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
  on(element: HTMLElement | Document, shortcut: string, handler: (e: KeyboardEvent) => void): () => void {
    const listener = (e: Event) => {
      if (this.matches(e as KeyboardEvent, shortcut)) {
        handler(e as KeyboardEvent);
      }
    };
    element.addEventListener('keydown', listener);
    return () => element.removeEventListener('keydown', listener);
  },
  isCursorAtStart(element: HTMLElement): boolean {
    const sel = window.getSelection();
    if (!sel || sel.rangeCount === 0) return false;
    const range = sel.getRangeAt(0);
    if (range.startOffset !== 0) return false;
    let node: Node | null = range.startContainer;
    if (node.nodeType === Node.TEXT_NODE) {
      node = node.parentElement;
    }
    while (node && node !== element) {
      if (node.previousSibling) return false;
      node = node.parentElement;
    }
    return true;
  },
  isCursorAtEnd(element: HTMLElement): boolean {
    const sel = window.getSelection();
    if (!sel || sel.rangeCount === 0) return false;
    const range = sel.getRangeAt(0);
    let node: Node | null = range.endContainer;
    if (node.nodeType === Node.TEXT_NODE) {
      if (range.endOffset !== (node as Text).length) return false;
      node = node.parentElement;
    }
    while (node && node !== element) {
      if (node.nextSibling) return false;
      node = node.parentElement;
    }
    return true;
  },
  getShortcutString(e: KeyboardEvent): string {
    const parts: string[] = [];
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
  preventEditorDefaults(e: KeyboardEvent): boolean {
    const shortcuts = [
      'mod+b', 'mod+i', 'mod+u',
      'mod+s',
      'mod+z', 'mod+shift+z',
      'mod+a'
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
