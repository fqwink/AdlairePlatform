/**
 * StateManager - Manages editor state and reactive updates
 * 
 * Centralized state management with reactive updates.
 * 
 * @example
 * const state = new StateManager();
 * state.subscribe('blocks', (blocks) => console.log('Blocks changed:', blocks));
 * state.set('blocks', [block1, block2]);
 */

export class StateManager {
  constructor(initialState = {}) {
    this.state = { ...initialState };
    this.subscribers = new Map();
  }

  /**
   * Get a state value
   * @param {string} key - State key
   * @returns {*} State value
   */
  get(key) {
    return this.state[key];
  }

  /**
   * Set a state value and notify subscribers
   * @param {string} key - State key
   * @param {*} value - New value
   */
  set(key, value) {
    const oldValue = this.state[key];
    
    // Check if value actually changed
    if (oldValue === value) return;

    this.state[key] = value;
    this.notify(key, value, oldValue);
  }

  /**
   * Update multiple state values at once
   * @param {Object} updates - Object with key-value pairs to update
   */
  update(updates) {
    Object.entries(updates).forEach(([key, value]) => {
      this.set(key, value);
    });
  }

  /**
   * Subscribe to state changes
   * @param {string} key - State key to watch
   * @param {Function} callback - Handler function (newValue, oldValue)
   * @returns {Function} Unsubscribe function
   */
  subscribe(key, callback) {
    if (!this.subscribers.has(key)) {
      this.subscribers.set(key, new Set());
    }
    this.subscribers.get(key).add(callback);

    // Return unsubscribe function
    return () => this.unsubscribe(key, callback);
  }

  /**
   * Unsubscribe from state changes
   * @param {string} key - State key
   * @param {Function} callback - Handler function to remove
   */
  unsubscribe(key, callback) {
    if (!this.subscribers.has(key)) return;
    this.subscribers.get(key).delete(callback);
    if (this.subscribers.get(key).size === 0) {
      this.subscribers.delete(key);
    }
  }

  /**
   * Notify subscribers of state change
   * @param {string} key - State key that changed
   * @param {*} newValue - New value
   * @param {*} oldValue - Old value
   * @private
   */
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

  /**
   * Get entire state object (shallow copy)
   * @returns {Object}
   */
  getAll() {
    return { ...this.state };
  }

  /**
   * Reset state to initial values
   * @param {Object} [initialState={}] - New initial state
   */
  reset(initialState = {}) {
    const oldKeys = Object.keys(this.state);
    this.state = { ...initialState };
    
    // Notify all subscribers of cleared keys
    oldKeys.forEach(key => {
      if (!(key in this.state)) {
        this.notify(key, undefined, undefined);
      }
    });
  }

  /**
   * Check if a key exists in state
   * @param {string} key - State key
   * @returns {boolean}
   */
  has(key) {
    return key in this.state;
  }

  /**
   * Delete a key from state
   * @param {string} key - State key
   */
  delete(key) {
    if (!this.has(key)) return;
    
    const oldValue = this.state[key];
    delete this.state[key];
    this.notify(key, undefined, oldValue);
  }

  /**
   * Clear all subscribers
   * @param {string} [key] - Optional key. If omitted, clears all subscribers.
   */
  clearSubscribers(key) {
    if (key) {
      this.subscribers.delete(key);
    } else {
      this.subscribers.clear();
    }
  }
}
