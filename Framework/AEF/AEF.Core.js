/**
 * AEF (Adlaire Editor Framework) - Core Module
 * 
 * @file Framework/AEF/AEF.Core.js
 * @version 1.0.0
 * 
 * Core components: Editor, EventBus, BlockRegistry, StateManager, HistoryManager
 */

/**
 * EventBus - Central event management system
 */
export class EventBus {
  constructor() {
    this.listeners = new Map();
  }

  on(event, callback) {
    if (!this.listeners.has(event)) {
      this.listeners.set(event, new Set());
    }
    this.listeners.get(event).add(callback);
    return () => this.off(event, callback);
  }

  once(event, callback) {
    const wrapper = (...args) => {
      callback(...args);
      this.off(event, wrapper);
    };
    this.on(event, wrapper);
  }

  off(event, callback) {
    if (!this.listeners.has(event)) return;
    this.listeners.get(event).delete(callback);
    if (this.listeners.get(event).size === 0) {
      this.listeners.delete(event);
    }
  }

  emit(event, data) {
    if (!this.listeners.has(event)) return;
    this.listeners.get(event).forEach(callback => {
      try {
        callback(data);
      } catch (error) {
        console.error(`[EventBus] Error in listener for "${event}":`, error);
      }
    });
  }

  clear(event) {
    if (event) {
      this.listeners.delete(event);
    } else {
      this.listeners.clear();
    }
  }

  listenerCount(event) {
    return this.listeners.has(event) ? this.listeners.get(event).size : 0;
  }
}

/**
 * BlockRegistry - Manages block type registration and instantiation
 */
export class BlockRegistry {
  constructor() {
    this.blocks = new Map();
  }

  register(type, BlockClass) {
    if (this.blocks.has(type)) {
      throw new Error(`[BlockRegistry] Block type "${type}" is already registered`);
    }
    if (typeof BlockClass !== 'function') {
      throw new Error(`[BlockRegistry] BlockClass for "${type}" must be a class/constructor`);
    }
    this.blocks.set(type, BlockClass);
  }

  unregister(type) {
    this.blocks.delete(type);
  }

  has(type) {
    return this.blocks.has(type);
  }

  get(type) {
    return this.blocks.get(type);
  }

  create(type, data = {}, config = {}) {
    const BlockClass = this.blocks.get(type);
    if (!BlockClass) {
      throw new Error(`[BlockRegistry] Block type "${type}" is not registered`);
    }
    try {
      return new BlockClass(data, config);
    } catch (error) {
      console.error(`[BlockRegistry] Error creating block "${type}":`, error);
      throw error;
    }
  }

  getTypes() {
    return Array.from(this.blocks.keys());
  }

  getAll() {
    return Array.from(this.blocks.entries()).map(([type, BlockClass]) => ({
      type,
      BlockClass
    }));
  }

  clear() {
    this.blocks.clear();
  }

  count() {
    return this.blocks.size;
  }
}

/**
 * StateManager - Manages editor state and reactive updates
 */
export class StateManager {
  constructor(initialState = {}) {
    this.state = { ...initialState };
    this.subscribers = new Map();
  }

  get(key) {
    return this.state[key];
  }

  set(key, value) {
    const oldValue = this.state[key];
    if (oldValue === value) return;
    this.state[key] = value;
    this.notify(key, value, oldValue);
  }

  update(updates) {
    Object.entries(updates).forEach(([key, value]) => {
      this.set(key, value);
    });
  }

  subscribe(key, callback) {
    if (!this.subscribers.has(key)) {
      this.subscribers.set(key, new Set());
    }
    this.subscribers.get(key).add(callback);
    return () => this.unsubscribe(key, callback);
  }

  unsubscribe(key, callback) {
    if (!this.subscribers.has(key)) return;
    this.subscribers.get(key).delete(callback);
    if (this.subscribers.get(key).size === 0) {
      this.subscribers.delete(key);
    }
  }

  notify(key, newValue, oldValue) {
    if (!this.subscribers.has(key)) return;
    this.subscribers.get(key).forEach(callback => {
      try {
        callback(newValue, oldValue);
      } catch (error) {
        console.error(`[StateManager] Error in subscriber for "${key}":`, error);
      }
    });
  }

  getAll() {
    return { ...this.state };
  }

  reset(initialState = {}) {
    const oldKeys = Object.keys(this.state);
    this.state = { ...initialState };
    oldKeys.forEach(key => {
      if (!(key in this.state)) {
        this.notify(key, undefined, undefined);
      }
    });
  }

  has(key) {
    return key in this.state;
  }

  delete(key) {
    if (!this.has(key)) return;
    const oldValue = this.state[key];
    delete this.state[key];
    this.notify(key, undefined, oldValue);
  }

  clearSubscribers(key) {
    if (key) {
      this.subscribers.delete(key);
    } else {
      this.subscribers.clear();
    }
  }
}

/**
 * HistoryManager - Undo/Redo functionality
 */
export class HistoryManager {
  constructor(limit = 50) {
    this.limit = limit;
    this.stack = [];
    this.position = -1;
  }

  push(state) {
    if (this.position < this.stack.length - 1) {
      this.stack = this.stack.slice(0, this.position + 1);
    }
    this.stack.push(this._clone(state));
    this.position++;
    if (this.stack.length > this.limit) {
      this.stack.shift();
      this.position--;
    }
  }

  undo() {
    if (!this.canUndo()) return null;
    this.position--;
    return this._clone(this.stack[this.position]);
  }

  redo() {
    if (!this.canRedo()) return null;
    this.position++;
    return this._clone(this.stack[this.position]);
  }

  canUndo() {
    return this.position > 0;
  }

  canRedo() {
    return this.position < this.stack.length - 1;
  }

  getCurrent() {
    if (this.position < 0) return null;
    return this._clone(this.stack[this.position]);
  }

  clear() {
    this.stack = [];
    this.position = -1;
  }

  getInfo() {
    return {
      size: this.stack.length,
      position: this.position,
      canUndo: this.canUndo(),
      canRedo: this.canRedo(),
      limit: this.limit
    };
  }

  setLimit(newLimit) {
    this.limit = newLimit;
    if (this.stack.length > newLimit) {
      const overflow = this.stack.length - newLimit;
      this.stack = this.stack.slice(overflow);
      this.position = Math.max(0, this.position - overflow);
    }
  }

  replaceCurrent(state) {
    if (this.position >= 0 && this.position < this.stack.length) {
      this.stack[this.position] = this._clone(state);
    }
  }

  getAt(pos) {
    if (pos < 0 || pos >= this.stack.length) return null;
    return this._clone(this.stack[pos]);
  }

  _clone(obj) {
    if (obj === null || typeof obj !== 'object') return obj;
    try {
      return JSON.parse(JSON.stringify(obj));
    } catch (error) {
      console.warn('[HistoryManager] Failed to clone state, returning reference');
      return obj;
    }
  }
}

/**
 * Editor - Main editor controller
 */
export class Editor {
  constructor(config = {}) {
    this.config = {
      holder: null,
      autosave: false,
      autosaveInterval: 30000,
      historyLimit: 50,
      placeholder: 'Start typing...',
      readOnly: false,
      minHeight: '300px',
      ...config
    };

    if (!this.config.holder) {
      throw new Error('[Editor] Configuration must include a "holder" element');
    }

    this.events = new EventBus();
    this.blocks = new BlockRegistry();
    this.state = new StateManager({
      blocks: [],
      currentBlockId: null,
      isReady: false,
      readOnly: this.config.readOnly
    });
    this.history = new HistoryManager(this.config.historyLimit);

    this.holder = this.config.holder;
    this.wrapper = null;
    this.isInitialized = false;
    this.autosaveTimer = null;

    this._handleKeyDown = this._handleKeyDown.bind(this);
    this._handleInput = this._handleInput.bind(this);

    this._init();
  }

  _init() {
    this._createWrapper();
    this._setupEventListeners();
    if (this.config.autosave) {
      this._setupAutosave();
    }
    this.isInitialized = true;
    this.state.set('isReady', true);
    this.events.emit('ready', { editor: this });
  }

  _createWrapper() {
    this.wrapper = document.createElement('div');
    this.wrapper.className = 'aef-editor';
    this.wrapper.setAttribute('data-editor', 'true');
    if (this.config.minHeight) {
      this.wrapper.style.minHeight = this.config.minHeight;
    }
    this.blocksContainer = document.createElement('div');
    this.blocksContainer.className = 'aef-blocks';
    this.wrapper.appendChild(this.blocksContainer);
    this.holder.appendChild(this.wrapper);
  }

  _setupEventListeners() {
    this.wrapper.addEventListener('keydown', this._handleKeyDown);
    this.wrapper.addEventListener('input', this._handleInput);
    this.state.subscribe('blocks', (blocks) => {
      this.events.emit('blocks:changed', { blocks });
      this._saveToHistory();
    });
  }

  _handleKeyDown(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'z' && !e.shiftKey) {
      e.preventDefault();
      this.undo();
      return;
    }
    if ((e.ctrlKey || e.metaKey) && e.key === 'z' && e.shiftKey) {
      e.preventDefault();
      this.redo();
      return;
    }
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
      e.preventDefault();
      this.save();
      return;
    }
    this.events.emit('keydown', { event: e, editor: this });
  }

  _handleInput(e) {
    this.events.emit('input', { event: e, editor: this });
  }

  _setupAutosave() {
    this.autosaveTimer = setInterval(() => {
      this.save();
    }, this.config.autosaveInterval);
  }

  render(blocksData = []) {
    this.blocksContainer.innerHTML = '';
    const blockInstances = [];
    blocksData.forEach((blockData, index) => {
      try {
        const block = this.blocks.create(blockData.type, blockData.data, this.config);
        const blockElement = block.render();
        const blockId = `block-${Date.now()}-${index}`;
        blockElement.setAttribute('data-block-id', blockId);
        blockElement.setAttribute('data-block-type', blockData.type);
        this.blocksContainer.appendChild(blockElement);
        blockInstances.push({
          id: blockId,
          type: blockData.type,
          instance: block,
          element: blockElement
        });
      } catch (error) {
        console.error('[Editor] Error rendering block:', error);
      }
    });
    this.state.set('blocks', blockInstances);
    this.events.emit('rendered', { blocks: blockInstances });
  }

  save() {
    const blocks = this.state.get('blocks') || [];
    const serialized = blocks.map(blockInfo => ({
      type: blockInfo.type,
      data: blockInfo.instance.save ? blockInfo.instance.save() : {}
    }));
    this.events.emit('save', { blocks: serialized });
    return serialized;
  }

  _saveToHistory() {
    const currentState = this.save();
    this.history.push(currentState);
  }

  undo() {
    const previousState = this.history.undo();
    if (previousState) {
      this.render(previousState);
      this.events.emit('undo', { state: previousState });
    }
  }

  redo() {
    const nextState = this.history.redo();
    if (nextState) {
      this.render(nextState);
      this.events.emit('redo', { state: nextState });
    }
  }

  clear() {
    this.render([]);
    this.history.clear();
    this.events.emit('cleared');
  }

  destroy() {
    if (this.autosaveTimer) {
      clearInterval(this.autosaveTimer);
    }
    this.wrapper.removeEventListener('keydown', this._handleKeyDown);
    this.wrapper.removeEventListener('input', this._handleInput);
    this.events.clear();
    this.state.clearSubscribers();
    this.history.clear();
    if (this.wrapper && this.wrapper.parentNode) {
      this.wrapper.parentNode.removeChild(this.wrapper);
    }
    this.isInitialized = false;
    this.events.emit('destroyed');
  }

  getConfig() {
    return { ...this.config };
  }

  updateConfig(updates) {
    this.config = { ...this.config, ...updates };
    this.events.emit('config:updated', { config: this.config });
  }
}
