/**
 * BlockRegistry - Manages block type registration and instantiation
 * 
 * Central registry for all block types in the editor.
 * 
 * @example
 * const registry = new BlockRegistry();
 * registry.register('paragraph', ParagraphBlock);
 * const block = registry.create('paragraph', { text: 'Hello' });
 */

export class BlockRegistry {
  constructor() {
    this.blocks = new Map();
  }

  /**
   * Register a block type
   * @param {string} type - Block type name (e.g., 'paragraph', 'heading')
   * @param {class} BlockClass - Block class (must extend BaseBlock)
   * @throws {Error} If type is already registered or BlockClass is invalid
   */
  register(type, BlockClass) {
    if (this.blocks.has(type)) {
      throw new Error(`[BlockRegistry] Block type "${type}" is already registered`);
    }
    
    // Validate BlockClass has required methods
    if (typeof BlockClass !== 'function') {
      throw new Error(`[BlockRegistry] BlockClass for "${type}" must be a class/constructor`);
    }

    this.blocks.set(type, BlockClass);
  }

  /**
   * Unregister a block type
   * @param {string} type - Block type name
   */
  unregister(type) {
    this.blocks.delete(type);
  }

  /**
   * Check if a block type is registered
   * @param {string} type - Block type name
   * @returns {boolean}
   */
  has(type) {
    return this.blocks.has(type);
  }

  /**
   * Get a block class by type
   * @param {string} type - Block type name
   * @returns {class|undefined}
   */
  get(type) {
    return this.blocks.get(type);
  }

  /**
   * Create a block instance
   * @param {string} type - Block type name
   * @param {Object} data - Block data
   * @param {Object} config - Editor configuration
   * @returns {Object} Block instance
   * @throws {Error} If block type is not registered
   */
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

  /**
   * Get all registered block types
   * @returns {string[]} Array of block type names
   */
  getTypes() {
    return Array.from(this.blocks.keys());
  }

  /**
   * Get all registered block classes
   * @returns {Array<{type: string, BlockClass: class}>}
   */
  getAll() {
    return Array.from(this.blocks.entries()).map(([type, BlockClass]) => ({
      type,
      BlockClass
    }));
  }

  /**
   * Clear all registered blocks
   */
  clear() {
    this.blocks.clear();
  }

  /**
   * Get count of registered blocks
   * @returns {number}
   */
  count() {
    return this.blocks.size;
  }
}
