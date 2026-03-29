/**
 * Native JS helpers for form.php
 * Replaces jQuery dependency for basic operations
 */

// Mini jQuery-like selector helper
const $ = (selector) => {
    if (typeof selector === 'string') {
        if (selector.charAt(0) === '#') {
            return document.getElementById(selector.substring(1));
        }
        return document.querySelectorAll(selector);
    }
    return selector;
};

// DOM ready helper
const ready = (fn) => {
    if (document.readyState !== 'loading') {
        fn();
    } else {
        document.addEventListener('DOMContentLoaded', fn);
    }
};

// Class helpers
const addClass = (el, className) => {
    if (!el) return;
    if (el instanceof NodeList || Array.isArray(el)) {
        el.forEach(e => e.classList && e.classList.add(className));
    } else if (el.classList) {
        el.classList.add(className);
    }
};

const removeClass = (el, className) => {
    if (!el) return;
    if (el instanceof NodeList || Array.isArray(el)) {
        el.forEach(e => e.classList && e.classList.remove(className));
    } else if (el.classList) {
        el.classList.remove(className);
    }
};

const toggleClass = (el, className, force) => {
    if (!el) return;
    if (el.classList) {
        if (force !== undefined) {
            el.classList.toggle(className, force);
        } else {
            el.classList.toggle(className);
        }
    }
};

const hasClass = (el, className) => {
    return el && el.classList && el.classList.contains(className);
};

// Visibility helpers
const show = (el) => {
    if (!el) return;
    if (el instanceof NodeList || Array.isArray(el)) {
        el.forEach(e => e.style && (e.style.display = ''));
    } else if (el.style) {
        el.style.display = '';
    }
};

const hide = (el) => {
    if (!el) return;
    if (el instanceof NodeList || Array.isArray(el)) {
        el.forEach(e => e.style && (e.style.display = 'none'));
    } else if (el.style) {
        el.style.display = 'none';
    }
};

const toggle = (el, show) => {
    if (!el) return;
    if (el instanceof NodeList || Array.isArray(el)) {
        el.forEach(e => {
            if (e.style) {
                e.style.display = show ? '' : 'none';
            }
        });
    } else if (el.style) {
        el.style.display = show ? '' : 'none';
    }
};

// Value helpers
const val = (el, newValue) => {
    if (!el) return '';
    if (newValue !== undefined) {
        el.value = newValue;
        return el;
    }
    return el.value || '';
};

// Attribute helpers
const attr = (el, name, value) => {
    if (!el) return '';
    if (value !== undefined) {
        el.setAttribute(name, value);
        return el;
    }
    return el.getAttribute(name) || '';
};

// Find within element
const find = (el, selector) => {
    if (!el) return [];
    return el.querySelectorAll(selector);
};

// CSS helpers
const css = (el, prop, value) => {
    if (!el || !el.style) return;
    if (typeof prop === 'object') {
        Object.assign(el.style, prop);
    } else if (value !== undefined) {
        el.style[prop] = value;
    } else {
        return getComputedStyle(el)[prop];
    }
};

// Position/dimension helpers
const height = (el) => {
    if (!el) return 0;
    return el.offsetHeight;
};

const width = (el) => {
    if (!el) return 0;
    return el.offsetWidth;
};

const position = (el) => {
    if (!el) return { top: 0, left: 0 };
    return { top: el.offsetTop, left: el.offsetLeft };
};

// Event helpers
const on = (el, event, handler) => {
    if (!el) return;
    if (el instanceof NodeList || Array.isArray(el)) {
        el.forEach(e => e.addEventListener(event, handler));
    } else {
        el.addEventListener(event, handler);
    }
};

const trigger = (el, eventName) => {
    if (!el) return;
    const event = new Event(eventName, { bubbles: true });
    el.dispatchEvent(event);
};

// Traversal
const closest = (el, selector) => {
    if (!el) return null;
    return el.closest(selector);
};

const next = (el) => {
    if (!el) return null;
    return el.nextElementSibling;
};

// Tree-specific helpers for form.php
const TreeHelper = {
    // Set active tree item
    setActive(itemId) {
        // Remove active from all tree links
        document.querySelectorAll('.complextree li a.active, #simpletree a.active').forEach(a => {
            a.classList.remove('active');
        });
        // Add active to specified item
        const item = document.getElementById(itemId);
        if (item) {
            item.classList.add('active');
        }
    },

    // Add icon class to tree items
    addIconClass(container, iconClass) {
        const links = container.querySelectorAll('li a[href]');
        links.forEach(link => {
            link.classList.add('icon');
            if (iconClass) {
                link.classList.add(iconClass);
            }
        });
    },

    // Search filter for tree
    filterTree(searchValue) {
        const normalized = searchValue.toLowerCase();
        const elements = document.querySelectorAll('.complextree a, #simpletree a');
        let lastVisible = null;

        elements.forEach(el => {
            const text = el.textContent.toLowerCase();
            const isMatch = text.indexOf(normalized) > -1;
            el.style.display = isMatch ? '' : 'none';
            if (isMatch && el.href) {
                lastVisible = el;
            }
        });

        // Auto-select if only one match
        const visibleCount = document.querySelectorAll('.complextree li.f_closed:not([style*="display: none"]), #simpletree a:not([style*="display: none"])').length;
        if (visibleCount === 1 && lastVisible) {
            lastVisible.click();
        }
    }
};

// Form helpers for form.php
const FormHelper = {
    // Mark form as dirty
    setDirty(isDirty) {
        const saveBtn = document.getElementById('toolbar_save');
        const saveCloseBtn = document.getElementById('toolbar_saveclose');

        if (isDirty) {
            if (saveBtn) saveBtn.classList.add('dirty');
            if (saveCloseBtn) saveCloseBtn.classList.add('dirty');
        } else {
            if (saveBtn) saveBtn.classList.remove('dirty');
            if (saveCloseBtn) saveCloseBtn.classList.remove('dirty');
        }
    },

    // Show save feedback
    showSaveProgress(closeAfter) {
        const btn = document.getElementById('toolbar_save' + (closeAfter ? 'close' : ''));
        if (btn) {
            const table = btn.closest('table');
            if (table) table.classList.add('tb_but_down');
        }
    },

    // Resize content area
    resizeContent(hasSubforms) {
        const toolbar = document.getElementById('toolbar');
        const content = document.getElementById('c');
        const subforms = document.getElementById('subform');

        if (!toolbar || !content) return;

        const toolbarBottom = toolbar.offsetTop + toolbar.offsetHeight;
        let remainHeight = window.innerHeight - toolbarBottom;

        if (hasSubforms && subforms) {
            const foldHeight = 17;
            remainHeight -= subforms.offsetHeight;
            subforms.style.position = 'fixed';
            subforms.style.top = (remainHeight + toolbarBottom + foldHeight) + 'px';
        }

        content.style.top = '0px';
        content.style.height = Math.max(remainHeight, 1) + 'px';
        content.style.width = '100%';
    }
};

// Expose to global scope for backwards compatibility with existing code
window.$ = window.$ || $;
window.ready = ready;
window.TreeHelper = TreeHelper;
window.FormHelper = FormHelper;
