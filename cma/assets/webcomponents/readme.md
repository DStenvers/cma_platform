# Web Components - Shared Styles System

This document describes the shared stylesheet architecture for CMA web components.

## Overview

Web components use Shadow DOM for style encapsulation. Without optimization, each component instance duplicates its CSS in memory. The `lib-shared-styles.js` module solves this by using the `adoptedStyleSheets` API to share CSS across all component instances.

## Architecture

```
/library/webcomponents/
├── lib-shared-styles.js    # Shared stylesheet module
├── lib-dialog.js           # Uses shared styles
├── lib-datepicker.js       # Uses shared styles
├── lib-menu.js             # Uses shared styles
├── lib-toaster.js          # Uses shared styles
└── ...

/cma/webcomponents/
├── cma-combo.js            # Uses shared styles
├── cma-tabs.js             # Uses shared styles
├── cma-toolbar.js          # Uses shared styles
├── cma-tree.js             # Uses shared styles
└── ...
```

## Style Categories

The shared stylesheet provides these categories:

| Category | Description |
|----------|-------------|
| `base` | CSS custom properties, box-sizing reset, typography |
| `button` | Button styles (.btn, .btn-primary, .btn-success, .btn-danger) |
| `input` | Form input, textarea, select styles |
| `animation` | Keyframes: fadeIn, fadeOut, slideIn, slideOut, spin, pulse |
| `dropdown` | Dropdown/popup container and item styles |
| `badge` | Badge styles (.badge, .badge-success, etc.) |
| `scrollbar` | Custom scrollbar styling |

## CSS Custom Properties

The `base` category defines these CSS variables:

### Colors

```css
--color-primary: #204496;
--color-primary-dark: #1a3a7a;
--color-success: #28a745;
--color-warning: #f0ad4e;
--color-error: #dc3545;
--color-info: #077ab2;
```

### Backgrounds

```css
--bg-surface: #ffffff;
--bg-surface-alt: #f5f5f5;
--bg-hover: #d0e8f8;
--bg-disabled: #f9f9f9;
```

### Text Colors

```css
--text-primary: #333333;
--text-secondary: #666666;
--text-muted: #999999;
--text-inverse: #ffffff;
```

### Borders

```css
--border-color: #cccccc;
--border-light: #eeeeee;
--border-dark: #999999;
```

### Spacing & Radius

```css
--spacing-xs: 4px;
--spacing-sm: 8px;
--spacing-md: 12px;
--spacing-lg: 16px;
--spacing-xl: 24px;

--radius-sm: 3px;
--radius-md: 4px;
--radius-lg: 8px;
```

### Transitions

```css
--transition-fast: 0.1s ease;
--transition-base: 0.15s ease;
--transition-slow: 0.3s ease;
```

## Usage in Components

### Adopting Shared Styles

In your component's `connectedCallback()`:

```javascript
connectedCallback() {
    // Adopt shared styles if available
    if (typeof LibSharedStyles !== 'undefined' && LibSharedStyles.isSupported()) {
        LibSharedStyles.adopt(this.shadowRoot, 'base', 'button', 'input');
    }

    this.render();
}
```

### Using CSS Variables in Component CSS

Reference the CSS variables in your component-specific styles:

```javascript
render() {
    this.shadowRoot.innerHTML = `
        <style>
            .my-element {
                background: var(--bg-surface, #fff);
                border: 1px solid var(--border-color, #ccc);
                border-radius: var(--radius-md, 4px);
                padding: var(--spacing-sm, 8px);
                color: var(--text-primary, #333);
                transition: background var(--transition-base, 0.15s ease);
            }

            .my-element:hover {
                background: var(--bg-hover, #d0e8f8);
            }
        </style>
        <div class="my-element">Content</div>
    `;
}
```

### Fallback Values

Always include fallback values for browsers that don't support `adoptedStyleSheets`:

```css
/* Good - has fallback */
background: var(--bg-surface, #ffffff);

/* Bad - no fallback */
background: var(--bg-surface);
```

## API Reference

### LibSharedStyles.adopt(shadowRoot, ...categories)

Adopts shared stylesheets into a shadow root.

```javascript
LibSharedStyles.adopt(this.shadowRoot, 'base', 'button', 'dropdown');
```

**Parameters:**
- `shadowRoot` - The ShadowRoot to adopt styles into
- `...categories` - Style categories to include ('base' is always included automatically)

**Returns:** `boolean` - true if successful, false if not supported

### LibSharedStyles.isSupported()

Check if `adoptedStyleSheets` is supported.

```javascript
if (LibSharedStyles.isSupported()) {
    // Use adoptedStyleSheets
} else {
    // Use inline <style> fallback
}
```

### LibSharedStyles.getInlineCSS(...categories)

Get CSS as a string for inline use (fallback for unsupported browsers).

```javascript
const css = LibSharedStyles.getInlineCSS('base', 'button');
this.shadowRoot.innerHTML = `<style>${css}</style>...`;
```

### LibSharedStyles.categories

Array of available category names.

```javascript
console.log(LibSharedStyles.categories);
// ['base', 'button', 'input', 'animation', 'dropdown', 'badge', 'scrollbar']
```

## Component Reference

| Component | Location | Categories Used |
|-----------|----------|-----------------|
| `cma-combo` | /cma/webcomponents/ | base, input, dropdown, animation |
| `cma-tabs` | /cma/webcomponents/ | base, input, animation |
| `cma-toolbar` | /cma/webcomponents/ | base, button |
| `cma-tree` | /cma/webcomponents/ | base, scrollbar |
| `cma-sortlist` | /cma/webcomponents/ | base, button |
| `cma-checklist` | /cma/webcomponents/ | base, input |
| `cma-blockeditor` | /cma/webcomponents/ | base, button, input |
| `cma-fold` | /cma/webcomponents/ | base |
| `lib-dialog` | /library/webcomponents/ | base, button, input, animation |
| `lib-datepicker` | /library/webcomponents/ | base, input, button |
| `lib-menu` | /library/webcomponents/ | base, dropdown |
| `lib-toaster` | /library/webcomponents/ | base, animation |

## Performance Benefits

1. **Reduced Memory** - CSS is parsed once and shared across all instances
2. **Faster Rendering** - No duplicate CSS parsing per component
3. **Consistent Theming** - Single source of truth for design tokens
4. **Smaller Bundles** - Less CSS duplication in component code

## Browser Support

The `adoptedStyleSheets` API is supported in:
- Chrome 73+
- Edge 79+
- Firefox 101+
- Safari 16.4+

For older browsers, components fall back to inline `<style>` tags with the same CSS variables (using fallback values).

## Adding New Categories

To add a new style category:

1. Edit `/library/webcomponents/lib-shared-styles.js`
2. Add your CSS to a new constant:
   ```javascript
   const myNewCSS = `
       .my-class {
           /* styles */
       }
   `;
   ```
3. Add to `styleCategories` map:
   ```javascript
   const styleCategories = {
       // ...existing
       mynew: myNewCSS
   };
   ```
4. Components can now use: `LibSharedStyles.adopt(shadowRoot, 'mynew')`

## Theming

To customize the theme, override CSS variables at the document level:

```css
:root {
    --color-primary: #0066cc;
    --color-success: #00aa55;
    --bg-surface: #fafafa;
}
```

Or for dark mode:

```css
html.dark-theme {
    --bg-surface: #1a1a1a;
    --bg-surface-alt: #2a2a2a;
    --text-primary: #ffffff;
    --text-secondary: #aaaaaa;
    --border-color: #444444;
}
```
