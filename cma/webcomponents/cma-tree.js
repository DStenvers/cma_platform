/**
 * CMA Tree Web Component
 *
 * A reusable tree view component that matches the existing complextree styling.
 * Supports folders, items, expand/collapse, and state persistence.
 *
 * Usage:
 * <cma-tree
 *     data='[{"type":"folder","label":"Root","children":[...]}]'
 *     storage-key="my_tree"
 *     item-icon="person">
 * </cma-tree>
 */
// Guard against double registration
if (!customElements.get('cma-tree')) {

class CmaTree extends HTMLElement {
    constructor() {
        super();
        this.attachShadow({ mode: 'open' });
        this._data = [];
        this._storageKey = '';
        this._itemIcon = '';
        this._openFolders = new Set();
        this._nodeIndex = [];
        this._hasData = false;
        // Bind click handler for event delegation (prevents memory leaks)
        this._handleDelegatedClick = this._handleDelegatedClick.bind(this);
    }

    static get observedAttributes() {
        return ['data', 'storage-key', 'item-icon'];
    }

    connectedCallback() {
        // Adopt shared styles if available
        if (typeof LibSharedStyles !== 'undefined' && LibSharedStyles.isSupported()) {
            LibSharedStyles.adopt(this.shadowRoot, 'base', 'scrollbar');
        }

        this._storageKey = this.getAttribute('storage-key') || '';
        this._itemIcon = this.getAttribute('item-icon') || '';

        const dataAttr = this.getAttribute('data');
        if (dataAttr) {
            try {
                this._data = JSON.parse(dataAttr);
                this._hasData = true;
            } catch (e) {
                cmaLog.error('[cma-tree] Invalid JSON data:', e.message);
            }
        }

        this._loadState();
        this._render();

        // Single delegated click handler (prevents memory leaks from per-element listeners)
        this.shadowRoot.addEventListener('click', this._handleDelegatedClick);
    }

    disconnectedCallback() {
        // Clean up event listener to prevent memory leaks
        this.shadowRoot.removeEventListener('click', this._handleDelegatedClick);
    }

    /**
     * Delegated click handler - handles all folder/item clicks via event delegation
     * This avoids the memory leak of attaching listeners to each element on every render
     */
    _handleDelegatedClick(e) {
        // Check for folder click
        const folderEl = e.target.closest('[data-folder-id]');
        if (folderEl) {
            e.preventDefault();
            this._toggleFolder(parseInt(folderEl.dataset.folderId, 10));
            // If folder is also clickable (has an id), fire item-click
            if (folderEl.hasAttribute('data-clickable-folder')) {
                const folderId = parseInt(folderEl.dataset.folderId, 10);
                const node = this._nodeIndex[folderId];
                if (node) {
                    this._handleItemClick(node, folderEl);
                }
            }
            return;
        }

        // Check for item click
        const itemEl = e.target.closest('[data-item-id]');
        if (itemEl) {
            e.preventDefault();
            e.stopPropagation();
            const id = parseInt(itemEl.dataset.itemId, 10);
            const node = this._nodeIndex[id];
            if (node) {
                this._handleItemClick(node, itemEl);
            }
        }
    }

    attributeChangedCallback(name, oldValue, newValue) {
        if (oldValue === newValue) return;

        switch (name) {
            case 'data':
                try {
                    this._data = JSON.parse(newValue);
                    this._hasData = true;
                    this._render();
                } catch (e) {
                    cmaLog.error('[cma-tree] Invalid JSON data:', e.message);
                }
                break;
            case 'storage-key':
                this._storageKey = newValue;
                this._loadState();
                this._render();
                break;
            case 'item-icon':
                this._itemIcon = newValue;
                this._render();
                break;
        }
    }

    setData(data) {
        this._data = data;
        this._hasData = true;
        this._render();
    }

    expandAll() {
        this._nodeIndex.forEach((node, id) => {
            if (node.type === 'folder' && node.children && node.children.length > 0) {
                this._openFolders.add(id);
            }
        });
        this._saveState();
        this._render();
    }

    collapseAll() {
        this._openFolders.clear();
        if (this._nodeIndex.length > 0) {
            this._openFolders.add(0);
        }
        this._saveState();
        this._render();
    }

    /**
     * Filter tree items by search term. Shows matching items and their parent folders.
     * Pass empty string to clear the filter.
     * @param {string} term
     */
    filter(term) {
        const root = this.shadowRoot;
        if (!root) return;
        const needle = (term || '').toLowerCase().trim();

        // Clear filter
        if (!needle) {
            root.querySelectorAll('li[style*="display"]').forEach(li => li.style.display = '');
            root.querySelectorAll('ul.t').forEach(ul => {
                // Restore open/closed state from _openFolders
                const li = ul.parentElement;
                if (!li) return;
                const folderLink = li.querySelector(':scope > a[data-folder-id]');
                if (folderLink) {
                    const fid = parseInt(folderLink.dataset.folderId, 10);
                    const open = this._openFolders.has(fid);
                    ul.classList.toggle('f_open', open);
                    ul.classList.toggle('f_closed', !open);
                    li.classList.toggle('f_open', open);
                    li.classList.toggle('f_closed', !open);
                }
            });
            return;
        }

        // First hide all items
        root.querySelectorAll('li').forEach(li => li.style.display = 'none');

        // Find matching items and show them + their ancestors
        root.querySelectorAll('a[data-label]').forEach(a => {
            const label = (a.getAttribute('data-label') || '').toLowerCase();
            const nodeId = (a.getAttribute('data-node-id') || '').toLowerCase();
            if (label.indexOf(needle) === -1 && nodeId.indexOf(needle) === -1) return;

            // Show this item
            const li = a.closest('li');
            if (li) li.style.display = '';

            // Show all ancestor li and expand their ul
            let parent = li ? li.parentElement : null;
            while (parent) {
                if (parent.tagName === 'UL' && parent.classList.contains('t')) {
                    parent.classList.add('f_open');
                    parent.classList.remove('f_closed');
                }
                if (parent.tagName === 'LI') {
                    parent.style.display = '';
                    parent.classList.add('f_open');
                    parent.classList.remove('f_closed');
                }
                parent = parent.parentElement;
            }
        });
    }

    /**
     * Select a tree item by its href value
     * @param {string} href - The href to match
     * @returns {boolean} True if item was found and selected
     */
    selectByHref(href) {
        if (!href) return false;

        // Find the node with matching href
        const node = this._nodeIndex.find(n => n.href === href);
        if (!node) return false;

        // Expand parent folders to make item visible
        let parentId = node._parentId;
        while (parentId !== null) {
            this._openFolders.add(parentId);
            const parent = this._nodeIndex[parentId];
            parentId = parent ? parent._parentId : null;
        }

        // Re-render to show expanded folders
        this._saveState();
        this._render();

        // Find and click the item to select it
        const itemEl = this.shadowRoot.querySelector(`[data-item-id="${node._id}"]`);
        if (itemEl) {
            // Remove active from others and add to this one
            this.shadowRoot.querySelectorAll('a.active').forEach(el => el.classList.remove('active'));
            itemEl.classList.add('active');
            // Scroll into view
            itemEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            return true;
        }
        return false;
    }

    /**
     * Select a tree node by its id property (works for both items and clickable folders)
     * @param {string} nodeId - The node id to match
     * @returns {boolean} True if node was found and selected
     */
    selectById(nodeId) {
        if (!nodeId) return false;

        // Find the node with matching id
        const node = this._nodeIndex.find(n => String(n.id) === String(nodeId));
        if (!node) return false;

        // Expand parent folders to make item visible
        let parentId = node._parentId;
        while (parentId !== null) {
            this._openFolders.add(parentId);
            const parent = this._nodeIndex[parentId];
            parentId = parent ? parent._parentId : null;
        }

        // If this node is a folder, also expand it
        if (node.type === 'folder' && node.children && node.children.length > 0) {
            this._openFolders.add(node._id);
        }

        // Re-render to show expanded folders
        this._saveState();
        this._render();

        // Find and highlight the element
        const selector = `[data-node-id="${nodeId}"]`;
        const el = this.shadowRoot.querySelector(selector);
        if (el) {
            this.shadowRoot.querySelectorAll('a.active').forEach(a => a.classList.remove('active'));
            el.classList.add('active');
            el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            return true;
        }
        return false;
    }

    _loadState() {
        if (!this._storageKey) return;
        try {
            const saved = localStorage.getItem('tree_' + this._storageKey);
            if (saved) {
                this._openFolders = new Set(JSON.parse(saved));
            }
        } catch (e) {
            // localStorage may be disabled or quota exceeded - use defaults
        }
    }

    _saveState() {
        if (!this._storageKey) return;
        try {
            localStorage.setItem('tree_' + this._storageKey, JSON.stringify(Array.from(this._openFolders)));
        } catch (e) {
            // localStorage may be disabled or quota exceeded - state won't persist
        }
    }

    _buildIndex(nodes, parentId = null) {
        nodes.forEach(node => {
            const id = this._nodeIndex.length;
            node._id = id;
            node._parentId = parentId;
            this._nodeIndex.push(node);
            if (node.children && node.children.length > 0) {
                this._buildIndex(node.children, id);
            }
        });
    }

    _toggleFolder(id) {
        if (this._openFolders.has(id)) {
            this._openFolders.delete(id);
        } else {
            this._openFolders.add(id);
            // Auto-expand single child folders
            const node = this._nodeIndex[id];
            if (node && node.children && node.children.length === 1) {
                const child = node.children[0];
                if (child.type === 'folder' && child.children && child.children.length > 0) {
                    this._openFolders.add(child._id);
                }
            }
        }
        this._saveState();
        this._render();
    }

    _handleItemClick(node, element) {
        // Remove active class from all items
        this.shadowRoot.querySelectorAll('a.active').forEach(el => el.classList.remove('active'));

        // Add active class to clicked element
        if (element && element.classList) {
            element.classList.add('active');
        }

        this.dispatchEvent(new CustomEvent('item-click', {
            detail: { id: node.id, label: node.label, href: node.href, data: node.data },
            bubbles: true
        }));
    }

    _renderNode(node) {
        const isFolder = node.type === 'folder' && node.children && node.children.length > 0;
        const isOpen = this._openFolders.has(node._id);
        const badgeHtml = this._renderBadge(node.badge);

        if (isFolder) {
            // If folder has an id, make it clickable (fires item-click AND toggles)
            const folderAttrs = node.id
                ? `data-folder-id="${node._id}" data-clickable-folder="1" data-node-id="${this._escapeHtml(String(node.id))}" data-label="${this._escapeHtml(node.label).replace(/_/g, ' ')}"`
                : `data-folder-id="${node._id}" data-label="${this._escapeHtml(node.label).replace(/_/g, ' ')}"`;
            return `
                <li id="_f${node._id}" class="${isOpen ? 'f_open' : 'f_closed'}">
                    <a onclick="return false;" ${folderAttrs}>${this._escapeHtml(node.label)}${badgeHtml}</a>
                    <ul id="_ni${node._id}" class="t ${isOpen ? 'f_open' : 'f_closed'}">
                        ${node.children.map(child => this._renderNode(child)).join('')}
                    </ul>
                </li>`;
        } else {
            const iconClass = node.icon || this._itemIcon || '';
            const href = node.href || '#';
            const target = node.target || '';

            // Determine color class based on active/online_indic properties
            let colorClass = node.color || '';
            if (!colorClass) {
                // Check for active or online_indic - treat as boolean (1/true = green, 0/false = red)
                if (node.active !== undefined) {
                    colorClass = (node.active === 1 || node.active === '1' || node.active === true) ? 'green' : 'red';
                } else if (node.online_indic !== undefined) {
                    colorClass = (node.online_indic === 1 || node.online_indic === '1' || node.online_indic === true) ? 'green' : 'red';
                }
            }

            return `
                <li id="_i${node._id}" class="${colorClass}">
                    <a id="_h${node._id}" class="t icon ${iconClass}" href="${this._escapeHtml(href)}"
                       ${target ? `target="${target}"` : ''}
                       data-item-id="${node._id}"
                       data-node-id="${node.id || ''}"
                       data-label="${this._escapeHtml(node.label).replace(/_/g, ' ')}">${this._escapeHtml(node.label)}${badgeHtml}</a>
                </li>`;
        }
    }

    _renderBadge(badge) {
        if (!badge) return '';
        // badge can be 'A' (admin), 'D' (developer), or {type: 'admin|developer', label: 'X'}
        if (typeof badge === 'string') {
            const badgeClass = badge === 'D' ? 'developer' : 'admin';
            return `<span class="access-badge ${badgeClass}">${this._escapeHtml(badge)}</span>`;
        } else if (badge && badge.label) {
            const badgeClass = badge.type === 'developer' ? 'developer' : 'admin';
            return `<span class="access-badge ${badgeClass}">${this._escapeHtml(badge.label)}</span>`;
        }
        return '';
    }

    _escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    _render() {
        this._nodeIndex = [];
        this._buildIndex(this._data);

        // Open root folder by default
        if (this._openFolders.size === 0 && this._nodeIndex.length > 0) {
            this._openFolders.add(0);
        }

        // Use exact CSS from complextree
        const styles = `
            <style>
                :host {
                    display: block;
                    font-family: inherit;
                    font-size: inherit;
                }

                .complextree div.titel {
                    height: 23px;
                    line-height: 20px;
                    font-weight: bold;
                    width: 100%;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    padding-left: 4px;
                }

                .complextree ul.f_closed {
                    display: none;
                }

                .complextree li,
                .complextree ul {
                    width: 100%;
                    max-width: 100%;
                    overflow: hidden;
                    display: block;
                }

                .complextree ul {
                    margin: 0px;
                    padding-left: 16px;
                    list-style: none;
                    box-sizing: border-box;
                }

                .complextree > ul {
                    padding-left: 0;
                }

                .complextree li {
                    list-style: none;
                    margin: 0px;
                }

                .complextree li a:hover {
                    background-color: var(--tree-hover-bg, #e8f4fc);
                    color: var(--color-primary, #204496);
                    border: 1px solid var(--color-info, #077ab2);
                }

                .complextree li a {
                    height: 23px;
                    line-height: 22px;
                    display: block;
                    padding-left: 4px;
                    border-radius: 4px;
                    border: 1px solid transparent;
                    white-space: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    color: inherit;
                    text-decoration: none;
                    cursor: pointer;
                    font-family: var(--font-family);
                    font-size: var(--font-size);
                }

                .complextree li a.active,
                .complextree li a.active::before,
                .tools-sidebar .complextree li a.active,
                .tools-sidebar .complextree li a.active::before,
                .tools-sidebar #c a.active,
                .tools-sidebar #c a.active::before {
                    background-color: var(--tree-active-bg) !important;
                    color: var(--tree-active-text, #fff) !important;
                    border-color: var(--tree-active-bordercolor, transparent) !important;
                }

                /* Folder arrow - closed state */
                .complextree li.f_open > a::before,
                .complextree li.f_closed > a::before {
                    content: "";
                    display: inline-block;
                    border-bottom: 1px solid var(--color-primary, #204496);
                    margin-right: 8px;
                    border-left: 1px solid var(--color-primary, #204496);
                    width: 6px;
                    height: 6px;
                    transition: all 0.15s ease-in-out;
                    transform-origin: center center 1px;
                    transform: rotate(-135deg);
                }

                /* Folder arrow - open state */
                .complextree li.f_open > a::before {
                    transform: rotate(-45deg);
                    margin-left: 1px;
                    margin-right: 7px;
                    margin-bottom: 3px;
                }

                /* Item icons */
                a.icon::before {
                    float: left;
                    height: 22px;
                    width: 18px;
                    display: inline-block;
                    font-family: "Linearicons";
                    color: var(--color-primary, #204496);
                    content: "\\e6b3";
                }

                li.red a.icon::before { color: var(--color-error, #dc3545); }
                li.green a.icon::before { color: var(--color-success, #28a745); }

                /* Access level badges */
                .access-badge {
                    display: inline-flex;
                    width: 16px;
                    height: 16px;
                    border-radius: 50%;
                    font-size: var(--font-size-2xs);
                    font-weight: 700;
                    align-items: center;
                    justify-content: center;
                    flex-shrink: 0;
                    float: right;
                    margin-right: 4px;
                    margin-top: 3px;
                }
                .access-badge.developer {
                    background: var(--badge-developer-bg, #efefef);
                    color: var(--badge-developer-text, #666);
                }
                .access-badge.admin {
                    background: var(--badge-admin-bg, #d7e2f2);
                    color: var(--badge-admin-text, #333);
                }

                .complextree li a.active::before {
                    color: var(--tree-active-text, #fff) !important;
                }

                /* Icon classes - from shared-icons.js */
                ${CMA.getIconStylesFor([
                    'file-empty', 'users', 'user', 'database', 'cog', 'laptop',
                    'history', 'chart-bars', 'sync', 'list', 'folder', 'trash',
                    'warning', 'download', 'undo', 'arrow-right', 'code',
                    'magic-wand', 'text-format', 'question-circle', 'layers',
                    'menu', 'wrench', 'rocket', 'hourglass', 'upload',
                    'table', 'clock', 'bubble', 'palette', 'document', 'sun',
                    'tag', 'magnifier', 'pointer-up', 'picture'
                ], { prefix: 'a.icon' })}

                /* Form-specific icons */
                a.icon.person::before { content: "\\e71e"; }
                a.icon.group::before { content: "\\e722"; font-size: var(--font-size-lg); margin-right: 3px; }
                a.icon.report::before { content: "\\e6d8"; }
                a.icon.urls::before, a.icon.marketingurl::before { content: "\\e784"; }
                a.icon.logins::before { content: "\\e721"; }
                a.icon.srh::before, a.icon.deelnemers::before, a.icon.docenten::before,
                a.icon.praktijkopleiders::before, a.icon.werkbegeleiders::before,
                a.icon.supervisoren::before, a.icon.contactpersonen::before { content: "\\e71e"; }
                a.icon.rooster::before, a.icon.afspraak::before, a.icon.afspraken::before,
                a.icon.agenda::before, a.icon.kalender::before { content: "\\e788"; }
                a.icon.blokken::before { content: "\\e880"; }
                a.icon.big::before, a.icon.opleidingen::before { content: "\\e6da"; font-size: 17px; }
                a.icon.locaties::before { content: "\\e77a"; }
                a.icon.competenties::before { content: "\\e786"; }
                a.icon.toetsen::before { content: "\\e6db"; }
                a.icon.instellingen::before { content: "\\e6f2"; }
                a.icon.tools::before { content: "\\e676"; }
                a.icon.zoektermen::before { content: "\\e923"; }
                a.icon.cgo::before, a.icon.cgo_template::before { content: "\\e6b5"; }
                a.icon.literatuur::before { content: "\\e6d6"; }
                a.icon.autos::before { content: "\\e84a"; }

                /* Tooltip for truncated items - on li to avoid conflict with a::before icon */
                li.truncated {
                    position: relative;
                }

                li.truncated:hover::after {
                    content: attr(data-tooltip);
                    position: absolute;
                    left: 4px;
                    top: calc(100% + 2px);
                    background: var(--color-primary, #204496);
                    color: #fff;
                    padding: 6px 10px;
                    border-radius: 4px;
                    font-size: var(--font-size-sm);
                    white-space: normal;
                    max-width: min(300px, calc(100vw - 20px));
                    word-wrap: break-word;
                    z-index: 1000;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
                    pointer-events: none;
                }

                .loading {
                    text-align: center;
                    padding: 16px;
                    color: var(--text-secondary, #888);
                }
            </style>
        `;

        let html = styles + '<div class="complextree">';

        if (this._data.length === 0 && !this._hasData) {
            html += '<div class="loading"><span class="lnr lnr-sync lnr-spin"></span></div>';
        } else if (this._data.length === 0) {
            html += '<div class="titel">Geen items</div>';
        } else if (this._data.length === 1 && this._data[0].type === 'folder') {
            const root = this._data[0];
            html += `<div class="titel">${this._escapeHtml(root.label)}</div>`;
            if (root.children && root.children.length > 0) {
                html += `<ul class="t f_open">${root.children.map(child => this._renderNode(child)).join('')}</ul>`;
            }
        } else {
            html += `<ul class="t f_open">${this._data.map(node => this._renderNode(node)).join('')}</ul>`;
        }

        html += '</div>';
        this.shadowRoot.innerHTML = html;

        // Event listeners are handled via delegation in _handleDelegatedClick
        // (attached once in connectedCallback, not per-element per-render)

        // Show tooltip only when item text is actually truncated (checked on hover)
        // Use mouseover/mouseout which bubble (mouseenter/mouseleave do not bubble in Shadow DOM)
        const tree = this.shadowRoot.querySelector('.complextree');
        if (tree) {
            if (this._mouseoverHandler) {
                tree.removeEventListener('mouseover', this._mouseoverHandler);
                tree.removeEventListener('mouseout', this._mouseoutHandler);
            }
            this._mouseoverHandler = (e) => {
                const a = e.target.closest('a[data-label]');
                if (!a) return;
                const li = a.closest('li');
                if (!li || li.classList.contains('truncated')) return;
                // DEBUG: log to verify handler fires
                console.log('[cma-tree tooltip] hover on:', a.getAttribute('data-label'), 'scrollW:', a.scrollWidth, 'clientW:', a.clientWidth, 'offsetW:', a.offsetWidth);
                // Use Range to measure actual rendered text width
                var range = document.createRange();
                range.selectNodeContents(a);
                var textRect = range.getBoundingClientRect();
                var containerRect = a.getBoundingClientRect();
                console.log('[cma-tree tooltip] textW:', textRect.width, 'containerW:', containerRect.width);
                if (textRect.width > containerRect.width + 2) {
                    li.classList.add('truncated');
                    li.setAttribute('data-tooltip', a.getAttribute('data-label'));
                    console.log('[cma-tree tooltip] TRUNCATED - tooltip set');
                }
            };
            this._mouseoutHandler = (e) => {
                const a = e.target.closest('a[data-label]');
                if (!a) return;
                const li = a.closest('li');
                if (!li) return;
                li.classList.remove('truncated');
                li.removeAttribute('data-tooltip');
            };
            tree.addEventListener('mouseover', this._mouseoverHandler);
            tree.addEventListener('mouseout', this._mouseoutHandler);
        }
    }
}

customElements.define('cma-tree', CmaTree);

} // end guard
