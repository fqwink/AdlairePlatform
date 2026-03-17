/// <reference lib="dom" />

/**
 * AEB (Adlaire Editor & Blocks) - Core Module
 *
 * @file Framework/AEB/AEB.Core.ts
 * @version 1.0.0
 *
 * Core components: Editor, EventBus, BlockRegistry, StateManager, HistoryManager
 */

/**
 * EditorConfig - Configuration for the Editor
 */
export interface EditorConfig {
  holder: HTMLElement | null;
  autosave: boolean;
  autosaveInterval: number;
  historyLimit: number;
  placeholder: string;
  readOnly: boolean;
  minHeight: string;
  [key: string]: unknown;
}

/**
 * BlockData - Serialized block data for rendering
 */
export interface BlockData {
  type: string;
  data: Record<string, unknown>;
}

/**
 * BlockInstance - A rendered block tracked by the editor
 */
export interface BlockInstance {
  id: string;
  type: string;
  instance: { render(): HTMLElement; save?(): Record<string, unknown> };
  element: HTMLElement;
}

/**
 * HistoryInfo - Information about the history state
 */
export interface HistoryInfo {
  size: number;
  position: number;
  canUndo: boolean;
  canRedo: boolean;
  limit: number;
}

type EventCallback = (...args: unknown[]) => void;
type StateCallback = (newValue: unknown, oldValue: unknown) => void;

/**
 * EventBus - Central event management system
 */
export class EventBus {
  listeners: Map<string, Set<EventCallback>>;

  constructor() {
    this.listeners = new Map();
  }

  on(event: string, callback: EventCallback): () => void {
    if (!this.listeners.has(event)) {
      this.listeners.set(event, new Set());
    }
    this.listeners.get(event)!.add(callback);
    return () => this.off(event, callback);
  }

  once(event: string, callback: EventCallback): void {
    const wrapper: EventCallback = (...args: unknown[]) => {
      callback(...args);
      this.off(event, wrapper);
    };
    this.on(event, wrapper);
  }

  off(event: string, callback: EventCallback): void {
    if (!this.listeners.has(event)) return;
    this.listeners.get(event)!.delete(callback);
    if (this.listeners.get(event)!.size === 0) {
      this.listeners.delete(event);
    }
  }

  emit(event: string, data?: unknown): void {
    if (!this.listeners.has(event)) return;
    this.listeners.get(event)!.forEach(callback => {
      try {
        callback(data);
      } catch (error) {
        console.error(`[EventBus] Error in listener for "${event}":`, error);
      }
    });
  }

  clear(event?: string): void {
    if (event) {
      this.listeners.delete(event);
    } else {
      this.listeners.clear();
    }
  }

  listenerCount(event: string): number {
    return this.listeners.has(event) ? this.listeners.get(event)!.size : 0;
  }
}

/**
 * BlockRegistry - Manages block type registration and instantiation
 */
export class BlockRegistry {
  blocks: Map<string, new (...args: unknown[]) => unknown>;

  constructor() {
    this.blocks = new Map();
  }

  register(type: string, BlockClass: new (...args: unknown[]) => unknown): void {
    if (this.blocks.has(type)) {
      throw new Error(`[BlockRegistry] Block type "${type}" is already registered`);
    }
    if (typeof BlockClass !== 'function') {
      throw new Error(`[BlockRegistry] BlockClass for "${type}" must be a class/constructor`);
    }
    this.blocks.set(type, BlockClass);
  }

  unregister(type: string): void {
    this.blocks.delete(type);
  }

  has(type: string): boolean {
    return this.blocks.has(type);
  }

  get(type: string): (new (...args: unknown[]) => unknown) | undefined {
    return this.blocks.get(type);
  }

  create(type: string, data: Record<string, unknown> = {}, config: Record<string, unknown> = {}): unknown {
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

  getTypes(): string[] {
    return Array.from(this.blocks.keys());
  }

  getAll(): Array<{ type: string; BlockClass: new (...args: unknown[]) => unknown }> {
    return Array.from(this.blocks.entries()).map(([type, BlockClass]) => ({
      type,
      BlockClass
    }));
  }

  clear(): void {
    this.blocks.clear();
  }

  count(): number {
    return this.blocks.size;
  }
}

/**
 * StateManager - Manages editor state and reactive updates
 */
export class StateManager {
  state: Record<string, unknown>;
  subscribers: Map<string, Set<StateCallback>>;

  constructor(initialState: Record<string, unknown> = {}) {
    this.state = { ...initialState };
    this.subscribers = new Map();
  }

  get(key: string): unknown {
    return this.state[key];
  }

  set(key: string, value: unknown): void {
    const oldValue = this.state[key];
    if (oldValue === value) return;
    this.state[key] = value;
    this.notify(key, value, oldValue);
  }

  update(updates: Record<string, unknown>): void {
    Object.entries(updates).forEach(([key, value]) => {
      this.set(key, value);
    });
  }

  subscribe(key: string, callback: StateCallback): () => void {
    if (!this.subscribers.has(key)) {
      this.subscribers.set(key, new Set());
    }
    this.subscribers.get(key)!.add(callback);
    return () => this.unsubscribe(key, callback);
  }

  unsubscribe(key: string, callback: StateCallback): void {
    if (!this.subscribers.has(key)) return;
    this.subscribers.get(key)!.delete(callback);
    if (this.subscribers.get(key)!.size === 0) {
      this.subscribers.delete(key);
    }
  }

  notify(key: string, newValue: unknown, oldValue: unknown): void {
    if (!this.subscribers.has(key)) return;
    this.subscribers.get(key)!.forEach(callback => {
      try {
        callback(newValue, oldValue);
      } catch (error) {
        console.error(`[StateManager] Error in subscriber for "${key}":`, error);
      }
    });
  }

  getAll(): Record<string, unknown> {
    return { ...this.state };
  }

  reset(initialState: Record<string, unknown> = {}): void {
    const oldKeys = Object.keys(this.state);
    this.state = { ...initialState };
    oldKeys.forEach(key => {
      if (!(key in this.state)) {
        this.notify(key, undefined, undefined);
      }
    });
  }

  has(key: string): boolean {
    return key in this.state;
  }

  delete(key: string): void {
    if (!this.has(key)) return;
    const oldValue = this.state[key];
    delete this.state[key];
    this.notify(key, undefined, oldValue);
  }

  clearSubscribers(key?: string): void {
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
export class HistoryManager<T = unknown> {
  limit: number;
  stack: T[];
  position: number;

  constructor(limit: number = 50) {
    this.limit = limit;
    this.stack = [];
    this.position = -1;
  }

  push(state: T): void {
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

  undo(): T | null {
    if (!this.canUndo()) return null;
    this.position--;
    return this._clone(this.stack[this.position]);
  }

  redo(): T | null {
    if (!this.canRedo()) return null;
    this.position++;
    return this._clone(this.stack[this.position]);
  }

  canUndo(): boolean {
    return this.position > 0;
  }

  canRedo(): boolean {
    return this.position < this.stack.length - 1;
  }

  getCurrent(): T | null {
    if (this.position < 0) return null;
    return this._clone(this.stack[this.position]);
  }

  clear(): void {
    this.stack = [];
    this.position = -1;
  }

  getInfo(): HistoryInfo {
    return {
      size: this.stack.length,
      position: this.position,
      canUndo: this.canUndo(),
      canRedo: this.canRedo(),
      limit: this.limit
    };
  }

  setLimit(newLimit: number): void {
    this.limit = newLimit;
    if (this.stack.length > newLimit) {
      const overflow = this.stack.length - newLimit;
      this.stack = this.stack.slice(overflow);
      this.position = Math.max(0, this.position - overflow);
    }
  }

  replaceCurrent(state: T): void {
    if (this.position >= 0 && this.position < this.stack.length) {
      this.stack[this.position] = this._clone(state);
    }
  }

  getAt(pos: number): T | null {
    if (pos < 0 || pos >= this.stack.length) return null;
    return this._clone(this.stack[pos]);
  }

  _clone(obj: T): T {
    if (obj === null || typeof obj !== 'object') return obj;
    try {
      if (typeof structuredClone === 'function') {
        return structuredClone(obj);
      }
      return JSON.parse(JSON.stringify(obj));
    } catch {
      // Deep copy fallback for non-serializable objects
      try {
        return JSON.parse(JSON.stringify(obj));
      } catch {
        console.warn('[HistoryManager] Failed to clone state, creating shallow copy');
        if (Array.isArray(obj)) {
          return [...obj] as unknown as T;
        }
        return { ...obj };
      }
    }
  }
}

/**
 * Editor - Main editor controller
 */
export class Editor {
  config: EditorConfig;
  events: EventBus;
  blocks: BlockRegistry;
  state: StateManager;
  history: HistoryManager<BlockData[]>;
  holder: HTMLElement;
  wrapper: HTMLDivElement | null;
  blocksContainer!: HTMLDivElement;
  isInitialized: boolean;
  autosaveTimer: ReturnType<typeof setInterval> | null;

  private _handleKeyDown: (e: KeyboardEvent) => void;
  private _handleInput: (e: Event) => void;
  private _restoringHistory = false;

  constructor(config: Partial<EditorConfig> = {}) {
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
    this.history = new HistoryManager<BlockData[]>(this.config.historyLimit);

    this.holder = this.config.holder;
    this.wrapper = null;
    this.isInitialized = false;
    this.autosaveTimer = null;

    this._handleKeyDown = this._onKeyDown.bind(this);
    this._handleInput = this._onInput.bind(this);

    this._init();
  }

  private _init(): void {
    this._createWrapper();
    this._setupEventListeners();
    if (this.config.autosave) {
      this._setupAutosave();
    }
    this.isInitialized = true;
    this.state.set('isReady', true);
    this.events.emit('ready', { editor: this });
  }

  private _createWrapper(): void {
    this.wrapper = document.createElement('div') as HTMLDivElement;
    this.wrapper.className = 'aeb-editor';
    this.wrapper.setAttribute('data-editor', 'true');
    if (this.config.minHeight) {
      this.wrapper.style.minHeight = this.config.minHeight;
    }
    this.blocksContainer = document.createElement('div') as HTMLDivElement;
    this.blocksContainer.className = 'aeb-blocks';
    this.wrapper.appendChild(this.blocksContainer);
    this.holder.appendChild(this.wrapper);
  }

  private _setupEventListeners(): void {
    this.wrapper!.addEventListener('keydown', this._handleKeyDown);
    this.wrapper!.addEventListener('input', this._handleInput);
    this.state.subscribe('blocks', (blocks: unknown) => {
      this.events.emit('blocks:changed', { blocks });
      if (this._restoringHistory) return;
      this._saveToHistory();
    });
  }

  private _onKeyDown(e: KeyboardEvent): void {
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

  private _onInput(e: Event): void {
    this.events.emit('input', { event: e, editor: this });
  }

  private _setupAutosave(): void {
    this.autosaveTimer = setInterval(() => {
      this.save();
    }, this.config.autosaveInterval);
  }

  render(blocksData: BlockData[] = []): void {
    this.blocksContainer.innerHTML = '';
    const blockInstances: BlockInstance[] = [];
    blocksData.forEach((blockData, index) => {
      try {
        const block = this.blocks.create(blockData.type, blockData.data, this.config as unknown as Record<string, unknown>) as BlockInstance['instance'];
        const blockElement = block.render();
        // Use stable block ID from data if available, otherwise generate
        const blockId = (blockData.data?._blockId as string) || `block-${index}-${blockData.type}`;
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

  save(): BlockData[] {
    const blocks = (this.state.get('blocks') as BlockInstance[]) || [];
    const serialized: BlockData[] = blocks.map(blockInfo => ({
      type: blockInfo.type,
      data: blockInfo.instance.save ? blockInfo.instance.save() : {}
    }));
    this.events.emit('save', { blocks: serialized });
    return serialized;
  }

  private _saveToHistory(): void {
    const currentState = this.save();
    this.history.push(currentState);
  }

  undo(): void {
    const previousState = this.history.undo();
    if (previousState) {
      this._restoringHistory = true;
      this.render(previousState);
      this._restoringHistory = false;
      this.events.emit('undo', { state: previousState });
    }
  }

  redo(): void {
    const nextState = this.history.redo();
    if (nextState) {
      this._restoringHistory = true;
      this.render(nextState);
      this._restoringHistory = false;
      this.events.emit('redo', { state: nextState });
    }
  }

  clear(): void {
    this.render([]);
    this.history.clear();
    this.events.emit('cleared');
  }

  destroy(): void {
    if (this.autosaveTimer) {
      clearInterval(this.autosaveTimer);
    }
    this.wrapper!.removeEventListener('keydown', this._handleKeyDown);
    this.wrapper!.removeEventListener('input', this._handleInput);
    this.events.emit('destroyed');
    this.events.clear();
    this.state.clearSubscribers();
    this.history.clear();
    if (this.wrapper && this.wrapper.parentNode) {
      this.wrapper.parentNode.removeChild(this.wrapper);
    }
    this.isInitialized = false;
  }

  getConfig(): EditorConfig {
    return { ...this.config };
  }

  updateConfig(updates: Partial<EditorConfig>): void {
    this.config = { ...this.config, ...updates };
    this.events.emit('config:updated', { config: this.config });
  }
}
