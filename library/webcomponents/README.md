# Web Components Directory

This directory contains reusable web components for the application.

## Components

### Active Web Components (JavaScript-based)

- **lib-loader** - Loading indicator with configurable delay
- **lib-switch** - Toggle switch component
- **lib-dialog** - Modal dialog component
- **lib-message** - Message/notification component
- **lib-menu** - Menu component
- **lib-toaster** - Toast notification component
- **lib-datepicker** - Date picker component
- **lib-table** - Table component

### CSS-only Components

- **lib-label** - Status label component (pure CSS, no JavaScript)

## lib-label: Why CSS-only Instead of Web Component?

### The Problem We Encountered

Initially, `lib-label` was implemented as a JavaScript web component that:
1. Wrapped content in a styled `<span>` element
2. Applied CSS classes based on `type` and `size` attributes
3. Used `customElements.define()` to register the component

**However, we encountered a critical timing issue:**

When the browser parses HTML like `<lib-label type="information">Test</lib-label>`, it processes it in this order:

1. Parse opening tag → Creates the element
2. **Custom element upgrade** → Calls constructor, attributeChangedCallback, and connectedCallback
3. **Parse text content** → Adds the "Test" text node as a child ⚠️
4. Parse closing tag

The problem: **Step 2 happens BEFORE step 3**. When `connectedCallback` fired, `this.innerHTML` was empty because the browser hadn't added the text node yet.

### Failed Solutions

1. ❌ **Using `this.innerHTML`** - Empty during connectedCallback
2. ❌ **Using `queueMicrotask()`** - Still ran before content was parsed
3. ✅ **Using `setTimeout(0)`** - Worked, but added unnecessary complexity and delay

### The Solution: Pure CSS

We converted `lib-label` to a **pure CSS component** because:

✅ **No JavaScript needed** - Component works with CSS only
✅ **No timing issues** - No parsing/upgrade race conditions
✅ **Better performance** - No JS execution, instant rendering
✅ **Simpler code** - Just HTML classes and CSS
✅ **Smaller bundle** - One less JavaScript file
✅ **More maintainable** - Easier to understand and modify

### Usage

```html
<!-- Still uses the same clean syntax, but now with pure CSS! -->
<lib-label type="information" size="large">Test</lib-label>

<!-- Also supports class-based syntax for flexibility -->
<lib-label class="information large">Test</lib-label>
```

Available types: `information`, `warning`, `error`, `success`
Available sizes: `small`, `normal`, `large`

The CSS uses attribute selectors like `lib-label[type="information"]` and `lib-label[size="large"]`, so the original attribute-based syntax works perfectly without any JavaScript.

### When to Use Web Components vs CSS

**Use Web Components when:**
- Complex interactive behavior needed
- State management required
- Dynamic updates based on attribute changes
- Encapsulation of logic and behavior
- Shadow DOM benefits are needed

**Use CSS-only when:**
- Simple styling/presentation
- Static content (no dynamic updates)
- No JavaScript logic needed
- Performance is critical
- Simplicity is preferred

### Lessons Learned

1. **Custom element upgrade timing is tricky** - Child nodes may not exist during connectedCallback when parsing HTML
2. **setTimeout(0) is a workaround, not a solution** - It adds complexity and delay
3. **Question the need for JavaScript** - Not every custom element needs to be a web component
4. **Pure CSS is often sufficient** - Modern CSS can handle many use cases without JavaScript
5. **KISS principle applies** - The simplest solution is often the best

## References

- [Custom Elements Lifecycle](https://developer.mozilla.org/en-US/docs/Web/API/Web_components/Using_custom_elements)
- [HTML Parser Timing](https://html.spec.whatwg.org/multipage/parsing.html)
