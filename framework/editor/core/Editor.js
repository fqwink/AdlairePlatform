/**
 * Editor - Main editor controller for Adlaire Editor Framework (AEF)
 * 
 * Coordinates all editor components and provides the main API.
 * 
 * @example
 * import { Editor } from './core/Editor.js';
 * import { ParagraphBlock } from './blocks/ParagraphBlock.js';
 * 
 * const editor = new Editor({
 *   holder: document.getElementById('editor'),
 *   autosave: true,
 *   autosaveInterval: 30000
 * });
 * 
 * editor.blocks.register('paragraph', ParagraphBlock);
 * editor.render([{ type: 'paragraph', data: { text: 'Hello' } }]);
 */

import { EventBus } from './EventBus.js';
import { BlockRegistry } from './BlockRegistry.js';
import { StateManager } from './StateManager.js';
import { HistoryManager } from './HistoryManager.js';

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

    // Validate holder
    if (!this.config.holder) {
      throw new Error('[Editor] Configuration must include a "holder" element');
    }

    // Core components
    this.events = new EventBus();
    this.blocks = new BlockRegistry();
    this.state = new StateManager({
      blocks: [],
      currentBlockId: null,
      isReady: false,
      readOnly: this.config.readOnly
    });
    this.history = new HistoryManager(this.config.historyLimit);

    // DOM references
    this.holder = this.config.holder;
    this.wrapper = null;

    // Internal state
    this.isInitialized = false;
    this.autosaveTimer = null;

    // Bind methods
    this._handleKeyDown = this._handleKeyDown.bind(this);
    this._handleInput = this._handleInput.bind(this);

    // Initialize
    this._init();
  }

  /**
   * Initialize editor
   * @private
   */
  _init() {
    // Create editor wrapper
    this._createWrapper();

    // Setup event listeners
    this._setupEventListeners();

    // Setup autosave if enabled
    if (this.config.autosave) {
      this._setupAutosave();
    }

    // Mark as initialized
    this.isInitialized = true;
    this.state.set('isReady', true);
    this.events.emit('ready', { editor: this });
  }

  /**
   * Create editor wrapper DOM
   * @private
   */
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

  /**
   * Setup event listeners
   * @private
   */
  _setupEventListeners() {
    // Keyboard events
    this.wrapper.addEventListener('keydown', this._handleKeyDown);
    this.wrapper.addEventListener('input', this._handleInput);

    // Block state changes
    this.state.subscribe('blocks', (blocks) => {
      this.events.emit('blocks:changed', { blocks });
      this._saveToHistory();
    });
  }

  /**
   * Handle keyboard shortcuts
   * @param {KeyboardEvent} e
   * @private
   */
  _handleKeyDown(e) {
    // Ctrl/Cmd + Z = Undo
    if ((e.ctrlKey || e.metaKey) && e.key === 'z' && !e.shiftKey) {
      e.preventDefault();
      this.undo();
      return;
    }

    // Ctrl/Cmd + Shift + Z = Redo
    if ((e.ctrlKey || e.metaKey) && e.key === 'z' && e.shiftKey) {
      e.preventDefault();
      this.redo();
      return;
    }

    // Ctrl/Cmd + S = Save
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
      e.preventDefault();
      this.save();
      return;
    }

    this.events.emit('keydown', { event: e, editor: this });
  }

  /**
   * Handle input events
   * @param {InputEvent} e
   * @private
   */
  _handleInput(e) {
    this.events.emit('input', { event: e, editor: this });
  }

  /**
   * Setup autosave
   * @private
   */
  _setupAutosave() {
    this.autosaveTimer = setInterval(() => {
      this.save();
    }, this.config.autosaveInterval);
  }

  /**
   * Render blocks into editor
   * @param {Array} blocksData - Array of block data objects
   */
  render(blocksData = []) {
    // Clear existing blocks
    this.blocksContainer.innerHTML = '';
    
    const blockInstances = [];

    blocksData.forEach((blockData, index) => {
      try {
        const block = this.blocks.create(blockData.type, blockData.data, this.config);
        const blockElement = block.render();
        
        // Add block ID
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

  /**
   * Save editor content
   * @returns {Array} Serialized blocks
   */
  save() {
    const blocks = this.state.get('blocks') || [];
    const serialized = blocks.map(blockInfo => ({
      type: blockInfo.type,
      data: blockInfo.instance.save ? blockInfo.instance.save() : {}
    }));

    this.events.emit('save', { blocks: serialized });
    return serialized;
  }

  /**
   * Save current state to history
   * @private
   */
  _saveToHistory() {
    const currentState = this.save();
    this.history.push(currentState);
  }

  /**
   * Undo last change
   */
  undo() {
    const previousState = this.history.undo();
    if (previousState) {
      this.render(previousState);
      this.events.emit('undo', { state: previousState });
    }
  }

  /**
   * Redo last undone change
   */
  redo() {
    const nextState = this.history.redo();
    if (nextState) {
      this.render(nextState);
      this.events.emit('redo', { state: nextState });
    }
  }

  /**
   * Clear editor content
   */
  clear() {
    this.render([]);
    this.history.clear();
    this.events.emit('cleared');
  }

  /**
   * Destroy editor instance
   */
  destroy() {
    // Clear autosave timer
    if (this.autosaveTimer) {
      clearInterval(this.autosaveTimer);
    }

    // Remove event listeners
    this.wrapper.removeEventListener('keydown', this._handleKeyDown);
    this.wrapper.removeEventListener('input', this._handleInput);

    // Clear components
    this.events.clear();
    this.state.clearSubscribers();
    this.history.clear();

    // Remove DOM
    if (this.wrapper && this.wrapper.parentNode) {
      this.wrapper.parentNode.removeChild(this.wrapper);
    }

    // Mark as destroyed
    this.isInitialized = false;
    this.events.emit('destroyed');
  }

  /**
   * Get editor configuration
   * @returns {Object}
   */
  getConfig() {
    return { ...this.config };
  }

  /**
   * Update editor configuration
   * @param {Object} updates - Config updates
   */
  updateConfig(updates) {
    this.config = { ...this.config, ...updates };
    this.events.emit('config:updated', { config: this.config });
  }
}
