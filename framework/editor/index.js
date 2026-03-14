/**
 * AEF (Adlaire Editor Framework) - Main Entry Point
 * 
 * Modular, extensible block-based editor framework.
 * 
 * @version 1.0.0
 * @example
 * import { Editor, ParagraphBlock, HeadingBlock } from './framework/editor/index.js';
 * 
 * const editor = new Editor({
 *   holder: document.getElementById('editor'),
 *   autosave: true
 * });
 * 
 * editor.blocks.register('paragraph', ParagraphBlock);
 * editor.blocks.register('heading', HeadingBlock);
 * 
 * editor.render([
 *   { type: 'heading', data: { text: 'Welcome', level: 2 } },
 *   { type: 'paragraph', data: { text: 'Start writing...' } }
 * ]);
 */

// Core
export { Editor } from './core/Editor.js';
export { EventBus } from './core/EventBus.js';
export { BlockRegistry } from './core/BlockRegistry.js';
export { StateManager } from './core/StateManager.js';
export { HistoryManager } from './core/HistoryManager.js';

// Blocks
export { BaseBlock } from './blocks/BaseBlock.js';
export { ParagraphBlock } from './blocks/ParagraphBlock.js';
export { HeadingBlock } from './blocks/HeadingBlock.js';

// Utils
export { sanitizer } from './utils/sanitizer.js';
export { dom } from './utils/dom.js';
export { selection } from './utils/selection.js';
export { keyboard } from './utils/keyboard.js';

/**
 * Quick start helper function
 * @param {Object} config - Editor configuration
 * @returns {Editor} Editor instance with default blocks registered
 */
export function createEditor(config) {
  const editor = new Editor(config);
  
  // Register default blocks
  editor.blocks.register('paragraph', ParagraphBlock);
  editor.blocks.register('heading', HeadingBlock);
  
  return editor;
}
