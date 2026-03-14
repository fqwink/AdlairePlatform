# Adlaire Editor Framework (AEF) & CSS Framework (ACF)

**Version 1.0.0** - Modular WYSIWYG Editor Framework & CSS System

## 📖 Overview

This directory contains two tightly integrated frameworks:

1. **AEF (Adlaire Editor Framework)** - A modular, extensible block-based WYSIWYG editor
2. **ACF (Adlaire CSS Framework)** - A utility-first CSS framework with editor-specific styles

Both are designed to replace the monolithic `wysiwyg.js` (2,889 lines) with a clean, maintainable, and testable architecture.

---

## 🎯 Key Benefits

| Metric | Before | After | Improvement |
|--------|--------|-------|-------------|
| **Main file size** | 2,889 lines | ~300 lines | **~90% reduction** |
| **CSS organization** | 200 lines in JS + 977 scattered | Modular CSS files | **100% separation** |
| **Feature addition time** | 4-8 hours | 1-2 hours | **~75% reduction** |
| **Test coverage** | 0% | 80%+ target | **New capability** |
| **Reusability** | Low | High | **Multi-project** |

---

## 📁 Directory Structure

```
framework/
├── editor/                # AEF - JavaScript Editor Framework
│   ├── core/             # Core components
│   │   ├── Editor.js            # Main editor controller
│   │   ├── EventBus.js          # Event system (pub/sub)
│   │   ├── BlockRegistry.js     # Block type registry
│   │   ├── StateManager.js      # Reactive state management
│   │   ├── HistoryManager.js    # Undo/redo system
│   │   └── index.js             # Core exports
│   ├── blocks/           # Block implementations
│   │   ├── BaseBlock.js         # Abstract base class
│   │   ├── ParagraphBlock.js    # Paragraph block
│   │   ├── HeadingBlock.js      # Heading block (h2, h3)
│   │   └── index.js             # Block exports
│   ├── tools/            # Editor tools (TODO)
│   │   ├── InlineToolbar.js     # Floating inline toolbar
│   │   ├── SlashCommands.js     # Slash command menu
│   │   └── BlockHandle.js       # Drag handle & block menu
│   ├── utils/            # Utility functions
│   │   ├── sanitizer.js         # HTML sanitization
│   │   ├── dom.js               # DOM manipulation helpers
│   │   ├── selection.js         # Selection/range utilities
│   │   ├── keyboard.js          # Keyboard shortcuts
│   │   └── index.js             # Utils exports
│   └── index.js          # Main AEF entry point
├── css/                  # ACF - CSS Framework
│   ├── base/             # Base styles
│   │   ├── variables.css        # CSS custom properties
│   │   ├── reset.css            # Modern CSS reset
│   │   ├── typography.css       # Typography styles
│   │   └── utilities.css        # Utility classes
│   ├── components/       # Reusable components (TODO)
│   │   ├── buttons.css          # Button styles
│   │   └── forms.css            # Form styles
│   ├── editor/           # Editor-specific styles
│   │   ├── editor-base.css      # Editor wrapper & blocks
│   │   ├── blocks.css           # Individual block styles
│   │   └── toolbar.css          # Toolbar styles
│   ├── layout/           # Layout utilities (TODO)
│   │   ├── grid.css             # Grid system
│   │   └── flexbox.css          # Flexbox utilities
│   ├── themes/           # Theme variations (TODO)
│   │   ├── light.css            # Light theme
│   │   └── dark.css             # Dark theme
│   └── index.css         # Main ACF entry point
├── docs/                 # Documentation
│   ├── README.md                # This file
│   ├── EDITOR_CSS_FRAMEWORK_DESIGN.md
│   └── WYSIWYG_EDITOR_IMPROVEMENT_PROPOSAL.md
└── README.md             # Framework overview
```

---

## 🚀 Quick Start

### 1. Basic Editor Setup

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>AEF Demo</title>
  <!-- Import ACF styles -->
  <link rel="stylesheet" href="/framework/css/index.css">
</head>
<body>
  <div id="editor"></div>

  <!-- Import AEF as ES module -->
  <script type="module">
    import { createEditor } from '/framework/editor/index.js';

    const editor = createEditor({
      holder: document.getElementById('editor'),
      autosave: true,
      autosaveInterval: 30000,
      placeholder: 'Start typing...'
    });

    // Load initial content
    editor.render([
      { type: 'heading', data: { text: 'Welcome to AEF', level: 2 } },
      { type: 'paragraph', data: { text: 'Start writing amazing content!' } }
    ]);

    // Save handler
    editor.events.on('save', ({ blocks }) => {
      console.log('Saved:', blocks);
      // Send to server via fetch/axios
    });
  </script>
</body>
</html>
```

### 2. Advanced Usage with Custom Blocks

```javascript
import { Editor, BaseBlock } from '/framework/editor/index.js';

// Create custom block
class QuoteBlock extends BaseBlock {
  render() {
    this.wrapper = document.createElement('div');
    this.wrapper.className = 'aef-block aef-block-quote';
    
    this.element = document.createElement('blockquote');
    this.element.className = 'aef-quote';
    this.element.contentEditable = true;
    this.element.innerHTML = this.data.text || '';
    
    this.wrapper.appendChild(this.element);
    return this.wrapper;
  }
  
  save() {
    return { text: this.element.innerHTML };
  }
  
  static get config() {
    return {
      title: 'Quote',
      icon: '<svg>...</svg>',
      supportsInlineTools: true
    };
  }
  
  static get type() {
    return 'quote';
  }
}

// Setup editor
const editor = new Editor({
  holder: document.getElementById('editor')
});

// Register blocks
editor.blocks.register('paragraph', ParagraphBlock);
editor.blocks.register('heading', HeadingBlock);
editor.blocks.register('quote', QuoteBlock);  // Custom block

// Render
editor.render([...]);
```

---

## 🏗️ Architecture

### AEF Core Components

#### 1. **Editor** (`core/Editor.js`)
Main controller coordinating all components.

```javascript
const editor = new Editor({
  holder: element,
  autosave: true,
  autosaveInterval: 30000,
  historyLimit: 50,
  readOnly: false
});

editor.render(blocks);    // Render blocks
editor.save();            // Get serialized data
editor.undo();            // Undo last change
editor.redo();            // Redo
editor.clear();           // Clear all content
editor.destroy();         // Cleanup
```

#### 2. **EventBus** (`core/EventBus.js`)
Pub/sub event system for loose coupling.

```javascript
editor.events.on('block:added', (data) => {
  console.log('Block added:', data);
});

editor.events.emit('custom:event', { foo: 'bar' });
```

#### 3. **BlockRegistry** (`core/BlockRegistry.js`)
Manages block type registration.

```javascript
editor.blocks.register('paragraph', ParagraphBlock);
const block = editor.blocks.create('paragraph', { text: 'Hello' });
```

#### 4. **StateManager** (`core/StateManager.js`)
Reactive state management.

```javascript
editor.state.set('currentBlockId', 'block-123');
editor.state.subscribe('blocks', (blocks) => {
  console.log('Blocks changed:', blocks);
});
```

#### 5. **HistoryManager** (`core/HistoryManager.js`)
Undo/redo with configurable limits.

```javascript
editor.history.push(editorState);
const prev = editor.history.undo();
const next = editor.history.redo();
```

### Block System

All blocks extend `BaseBlock` and implement:

- `render()` - Returns DOM element
- `save()` - Returns serialized data
- `validate(data)` - Optional data validation
- `static get config()` - Block metadata
- `static get type()` - Block type name

### Utilities

- **sanitizer** - HTML sanitization
- **dom** - DOM manipulation helpers
- **selection** - Selection/range utilities
- **keyboard** - Keyboard shortcuts

---

## 🎨 ACF CSS Framework

### Design Tokens (CSS Variables)

```css
/* Colors */
--acf-primary: #2563eb;
--acf-text: #1e293b;
--acf-bg: #ffffff;

/* Spacing */
--acf-space-1: 0.25rem;  /* 4px */
--acf-space-4: 1rem;     /* 16px */

/* Typography */
--acf-font-size-base: 1rem;
--acf-font-weight-bold: 700;

/* Shadows */
--acf-shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1);
```

### Utility Classes

```html
<!-- Spacing -->
<div class="acf-m-4 acf-p-2">...</div>

<!-- Flexbox -->
<div class="acf-flex acf-items-center acf-gap-2">...</div>

<!-- Text -->
<p class="acf-text-lg acf-font-bold acf-text-primary">...</p>

<!-- Border & Shadow -->
<div class="acf-border acf-rounded-md acf-shadow-lg">...</div>
```

### Editor-Specific Classes

```css
.aef-editor              /* Editor wrapper */
.aef-blocks              /* Blocks container */
.aef-block               /* Individual block wrapper */
.aef-block-paragraph     /* Paragraph block */
.aef-block-heading       /* Heading block */
.aef-toolbar             /* Toolbar container */
.aef-toolbar-btn         /* Toolbar button */
```

---

## 🧪 Testing (TODO)

```javascript
// Example test structure
import { Editor, ParagraphBlock } from './index.js';

describe('Editor', () => {
  test('renders blocks correctly', () => {
    const editor = new Editor({ holder: div });
    editor.blocks.register('paragraph', ParagraphBlock);
    
    editor.render([
      { type: 'paragraph', data: { text: 'Test' } }
    ]);
    
    expect(div.querySelector('.aef-paragraph').textContent).toBe('Test');
  });
});
```

---

## 📋 Roadmap

### Phase 1 - Core Infrastructure ✅ **COMPLETE**
- [x] Core components (Editor, EventBus, BlockRegistry, StateManager, HistoryManager)
- [x] BaseBlock & basic blocks (Paragraph, Heading)
- [x] Utilities (sanitizer, dom, selection, keyboard)
- [x] ACF base styles (variables, reset, typography, utilities)
- [x] Editor-specific styles (editor-base, blocks, toolbar)

### Phase 2 - Advanced Blocks & Tools (TODO)
- [ ] List block (ordered, unordered)
- [ ] Quote block
- [ ] Code block with syntax highlighting
- [ ] Image block with upload
- [ ] Table block with editing
- [ ] Checklist block
- [ ] Delimiter block
- [ ] InlineToolbar (bold, italic, link, etc.)
- [ ] SlashCommands menu
- [ ] BlockHandle with drag & drop

### Phase 3 - Polish & Testing (TODO)
- [ ] Unit tests (80%+ coverage)
- [ ] Integration tests
- [ ] Performance optimization
- [ ] Accessibility (ARIA, keyboard navigation)
- [ ] Documentation site
- [ ] Migration guide from wysiwyg.js

---

## 🔧 Development

### File Naming Conventions
- **PascalCase** for classes: `Editor.js`, `ParagraphBlock.js`
- **camelCase** for utilities: `sanitizer.js`, `dom.js`
- **kebab-case** for CSS: `editor-base.css`, `variables.css`

### Code Style
- ES6+ modules (import/export)
- JSDoc comments for public APIs
- Consistent indentation (2 spaces)
- Descriptive variable names

### Adding a New Block

1. Create block file: `framework/editor/blocks/MyBlock.js`
2. Extend `BaseBlock` and implement required methods
3. Add CSS: `framework/css/editor/blocks.css`
4. Export in `framework/editor/blocks/index.js`
5. Register in editor: `editor.blocks.register('myblock', MyBlock)`

---

## 📚 Related Documentation

- [EDITOR_CSS_FRAMEWORK_DESIGN.md](./EDITOR_CSS_FRAMEWORK_DESIGN.md) - Detailed design document
- [WYSIWYG_EDITOR_IMPROVEMENT_PROPOSAL.md](./WYSIWYG_EDITOR_IMPROVEMENT_PROPOSAL.md) - Original proposal
- [AFE_IMPROVEMENT_ROADMAP_V2.md](./AFE_IMPROVEMENT_ROADMAP_V2.md) - Framework roadmap

---

## 📄 License

Part of the Adlaire Platform project.

---

## 🤝 Contributing

1. Follow existing code style
2. Add JSDoc comments for public APIs
3. Write tests for new features
4. Update documentation

---

**Last Updated:** 2026-03-14  
**Version:** 1.0.0  
**Status:** Core Infrastructure Complete ✅
