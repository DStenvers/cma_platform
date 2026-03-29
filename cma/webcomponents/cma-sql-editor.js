/**
 * cma-sql-editor Web Component
 *
 * SQL editor component for the Report Designer SQL mode.
 * Provides a textarea-based SQL editor with syntax highlighting,
 * line numbers, and keyboard shortcuts.
 *
 * Usage:
 *   <cma-sql-editor id="sqlEditor"></cma-sql-editor>
 *
 * Attributes:
 *   - placeholder: Placeholder text
 *   - readonly: Make editor read-only
 *   - database-id: Database ID for schema hints
 *
 * Events:
 *   - change: Fired when SQL changes (debounced 300ms)
 *   - execute: Fired on Ctrl+Enter (request preview)
 *
 * Methods:
 *   - get sql(): Get current SQL
 *   - set sql(value): Set SQL content
 *   - setError(message): Show error message inline
 *   - clearError(): Clear error message
 *   - focus(): Focus the editor
 *   - format(): Format/indent the SQL
 */

// Guard against double registration
if (!customElements.get('cma-sql-editor')) {

class CmaSqlEditor extends HTMLElement {
    static get observedAttributes() {
        return ['placeholder', 'readonly', 'database-id'];
    }

    constructor() {
        super();
        this.attachShadow({ mode: 'open' });

        // State
        this._sql = '';
        this._error = null;
        this._changeTimeout = null;
    }

    connectedCallback() {
        this._render();
        this._setupEventListeners();
    }

    disconnectedCallback() {
        if (this._changeTimeout) {
            clearTimeout(this._changeTimeout);
        }
    }

    attributeChangedCallback(name, oldValue, newValue) {
        if (oldValue === newValue) return;

        switch (name) {
            case 'placeholder':
                this._updatePlaceholder();
                break;
            case 'readonly':
                this._updateReadOnly();
                break;
        }
    }

    // =========================================================================
    // Public API
    // =========================================================================

    /**
     * Get current SQL
     */
    get sql() {
        return this._sql;
    }

    /**
     * Set SQL content
     */
    set sql(value) {
        this._sql = value || '';
        const textarea = this.shadowRoot.getElementById('sqlTextarea');
        if (textarea && textarea.value !== this._sql) {
            textarea.value = this._sql;
            this._updateHighlight();
            this._updateLineNumbers();
        }
    }

    /**
     * Show error message inline
     */
    setError(message) {
        this._error = message;
        this._renderError();
    }

    /**
     * Clear error message
     */
    clearError() {
        this._error = null;
        this._renderError();
    }

    /**
     * Focus the editor
     */
    focus() {
        const textarea = this.shadowRoot.getElementById('sqlTextarea');
        if (textarea) {
            textarea.focus();
        }
    }

    /**
     * Format/indent the SQL
     */
    format() {
        this._sql = SqlUtils.formatSql(this._sql);
        const textarea = this.shadowRoot.getElementById('sqlTextarea');
        if (textarea) {
            textarea.value = this._sql;
            this._updateHighlight();
            this._updateLineNumbers();
        }
        this._dispatchChange();
    }

    // =========================================================================
    // Private Methods
    // =========================================================================

    _render() {
        const placeholder = this.getAttribute('placeholder') || 'SELECT * FROM ...';

        this.shadowRoot.innerHTML = `
            <style>
                :host {
                    display: block;
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    font-size: var(--font-size);
                    height: 100%;
                    min-height: 200px;
                }

                * {
                    box-sizing: border-box;
                }

                .editor-container {
                    display: flex;
                    flex-direction: column;
                    height: 100%;
                    border: 1px solid var(--border-color, #ccc);
                    border-radius: 4px;
                    overflow: hidden;
                    background: var(--bg-surface, #fff);
                }

                .editor-toolbar {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    padding: 8px 12px;
                    background: var(--bg-alternate, #f5f5f5);
                    border-bottom: 1px solid var(--border-color, #ccc);
                    flex-shrink: 0;
                }

                .toolbar-btn {
                    display: inline-flex;
                    align-items: center;
                    gap: 4px;
                    padding: 4px 10px;
                    border: 1px solid var(--border-color, #ccc);
                    border-radius: 4px;
                    background: var(--bg-surface, #fff);
                    color: var(--text-primary, #333);
                    font-size: var(--font-size-sm);
                    cursor: pointer;
                    transition: all 0.15s;
                }

                .toolbar-btn:hover {
                    background: var(--bg-hover, #e8e8e8);
                    border-color: var(--border-hover, #999);
                }

                .toolbar-btn:active {
                    background: var(--bg-active, #d0d0d0);
                }

                .toolbar-btn .lnr {
                    font-size: var(--font-size-md);
                }

                .toolbar-hint {
                    margin-left: auto;
                    font-size: var(--font-size-xs);
                    color: var(--text-muted, #888);
                }

                .editor-main {
                    flex: 1;
                    display: flex;
                    overflow: hidden;
                    position: relative;
                }

                .line-numbers {
                    width: 45px;
                    padding: 10px 5px;
                    background: var(--bg-alternate, #f5f5f5);
                    border-right: 1px solid var(--border-color, #ccc);
                    font-family: 'Menlo', 'Monaco', 'Courier New', monospace;
                    font-size: var(--font-size);
                    line-height: 1.5;
                    color: var(--text-muted, #888);
                    text-align: right;
                    overflow: hidden;
                    user-select: none;
                    flex-shrink: 0;
                }

                .line-number {
                    height: 19.5px;
                    line-height: 19.5px;
                }

                .editor-wrapper {
                    flex: 1;
                    position: relative;
                    overflow: hidden;
                }

                .sql-textarea {
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    padding: 10px;
                    border: none;
                    outline: none;
                    resize: none;
                    font-family: 'Menlo', 'Monaco', 'Courier New', monospace;
                    font-size: var(--font-size);
                    line-height: 1.5;
                    background: transparent;
                    color: transparent;
                    caret-color: var(--text-primary, #333);
                    z-index: 2;
                    white-space: pre;
                    overflow: auto;
                }

                .sql-textarea::placeholder {
                    color: var(--text-muted, #999);
                }

                .sql-highlight {
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    padding: 10px;
                    font-family: 'Menlo', 'Monaco', 'Courier New', monospace;
                    font-size: var(--font-size);
                    line-height: 1.5;
                    white-space: pre;
                    overflow: auto;
                    pointer-events: none;
                    z-index: 1;
                    color: var(--text-primary, #333);
                }

                ${typeof SqlUtils !== 'undefined' ? SqlUtils.highlightCss : ''}

                .error-container {
                    padding: 10px 12px;
                    background: #fef2f2;
                    border-top: 1px solid #fecaca;
                    color: #dc2626;
                    font-size: var(--font-size-sm);
                    display: none;
                }

                .error-container.visible {
                    display: flex;
                    align-items: flex-start;
                    gap: 8px;
                }

                .error-container .error-icon {
                    flex-shrink: 0;
                }

                .error-container .error-text {
                    flex: 1;
                    line-height: 1.4;
                }

                .error-container .error-dismiss {
                    flex-shrink: 0;
                    background: none;
                    border: none;
                    color: #dc2626;
                    cursor: pointer;
                    padding: 2px;
                    font-size: var(--font-size-md);
                }

                .error-container .error-dismiss:hover {
                    color: #b91c1c;
                }
            </style>
            <div class="editor-container">
                <div class="editor-toolbar">
                    <button type="button" class="toolbar-btn" id="btnFormat" data-tooltip="SQL formatteren">
                        <span class="lnr lnr-indent-increase"></span>
                        Formatteren
                    </button>
                    <button type="button" class="toolbar-btn" id="btnCopy" data-tooltip="SQL kopiëren">
                        <span class="lnr lnr-file-empty"></span>
                        Kopiëren
                    </button>
                    <span class="toolbar-hint">Ctrl+Enter om uit te voeren</span>
                </div>
                <div class="editor-main">
                    <div class="line-numbers" id="lineNumbers">
                        <div class="line-number">1</div>
                    </div>
                    <div class="editor-wrapper">
                        <div class="sql-highlight" id="sqlHighlight"></div>
                        <textarea
                            id="sqlTextarea"
                            class="sql-textarea"
                            placeholder="${SqlUtils.escapeHtml(placeholder)}"
                            spellcheck="false"
                            autocomplete="off"
                            autocorrect="off"
                            autocapitalize="off"
                        ></textarea>
                    </div>
                </div>
                <div class="error-container" id="errorContainer">
                    <span class="error-icon lnr lnr-warning"></span>
                    <span class="error-text" id="errorText"></span>
                    <button type="button" class="error-dismiss" id="errorDismiss" title="Sluiten">&times;</button>
                </div>
            </div>
        `;
    }

    _setupEventListeners() {
        const textarea = this.shadowRoot.getElementById('sqlTextarea');
        const highlight = this.shadowRoot.getElementById('sqlHighlight');
        const lineNumbers = this.shadowRoot.getElementById('lineNumbers');
        const btnFormat = this.shadowRoot.getElementById('btnFormat');
        const btnCopy = this.shadowRoot.getElementById('btnCopy');
        const errorDismiss = this.shadowRoot.getElementById('errorDismiss');

        // Input handling
        textarea.addEventListener('input', () => {
            this._sql = textarea.value;
            this._updateHighlight();
            this._updateLineNumbers();
            this._debouncedChange();
        });

        // Sync scroll between textarea and highlight
        textarea.addEventListener('scroll', () => {
            highlight.scrollTop = textarea.scrollTop;
            highlight.scrollLeft = textarea.scrollLeft;
            lineNumbers.scrollTop = textarea.scrollTop;
        });

        // Keyboard shortcuts
        textarea.addEventListener('keydown', (e) => {
            // Ctrl+Enter to execute
            if (e.ctrlKey && e.key === 'Enter') {
                e.preventDefault();
                this._execute();
                return;
            }

            // Tab handling - insert spaces instead of tab
            if (e.key === 'Tab') {
                e.preventDefault();
                const start = textarea.selectionStart;
                const end = textarea.selectionEnd;
                const spaces = '  ';
                textarea.value = textarea.value.substring(0, start) + spaces + textarea.value.substring(end);
                textarea.selectionStart = textarea.selectionEnd = start + spaces.length;
                this._sql = textarea.value;
                this._updateHighlight();
                this._updateLineNumbers();
                this._debouncedChange();
                return;
            }

            // Auto-indent on Enter
            if (e.key === 'Enter') {
                const start = textarea.selectionStart;
                const beforeCursor = textarea.value.substring(0, start);
                const currentLine = beforeCursor.split('\n').pop();
                const indent = currentLine.match(/^[\t ]*/)[0];

                if (indent) {
                    e.preventDefault();
                    const newText = '\n' + indent;
                    textarea.value = textarea.value.substring(0, start) + newText + textarea.value.substring(textarea.selectionEnd);
                    textarea.selectionStart = textarea.selectionEnd = start + newText.length;
                    this._sql = textarea.value;
                    this._updateHighlight();
                    this._updateLineNumbers();
                    this._debouncedChange();
                }
            }
        });

        // Toolbar buttons
        btnFormat.addEventListener('click', () => this.format());
        btnCopy.addEventListener('click', () => this._copyToClipboard());
        errorDismiss.addEventListener('click', () => this.clearError());
    }

    _updateHighlight() {
        const highlight = this.shadowRoot.getElementById('sqlHighlight');
        if (highlight) {
            highlight.innerHTML = SqlUtils.highlightSql(this._sql);
        }
    }

    _updateLineNumbers() {
        const lineNumbers = this.shadowRoot.getElementById('lineNumbers');
        if (!lineNumbers) return;

        const lines = (this._sql || '').split('\n');
        let html = '';
        for (let i = 1; i <= Math.max(lines.length, 1); i++) {
            html += `<div class="line-number">${i}</div>`;
        }
        lineNumbers.innerHTML = html;
    }

    _updatePlaceholder() {
        const textarea = this.shadowRoot.getElementById('sqlTextarea');
        if (textarea) {
            textarea.placeholder = this.getAttribute('placeholder') || 'SELECT * FROM ...';
        }
    }

    _updateReadOnly() {
        const textarea = this.shadowRoot.getElementById('sqlTextarea');
        if (textarea) {
            textarea.readOnly = this.hasAttribute('readonly');
        }
    }

    _renderError() {
        const container = this.shadowRoot.getElementById('errorContainer');
        const text = this.shadowRoot.getElementById('errorText');

        if (this._error) {
            container.classList.add('visible');
            text.textContent = this._error;
        } else {
            container.classList.remove('visible');
            text.textContent = '';
        }
    }

    _debouncedChange() {
        if (this._changeTimeout) {
            clearTimeout(this._changeTimeout);
        }
        this._changeTimeout = setTimeout(() => {
            this._dispatchChange();
        }, 300);
    }

    _dispatchChange() {
        this.dispatchEvent(new CustomEvent('change', {
            bubbles: true,
            detail: { sql: this._sql }
        }));
    }

    _execute() {
        this.dispatchEvent(new CustomEvent('execute', {
            bubbles: true,
            detail: { sql: this._sql }
        }));
    }

    async _copyToClipboard() {
        const btnCopy = this.shadowRoot.getElementById('btnCopy');
        if (!this._sql) return;

        try {
            await navigator.clipboard.writeText(this._sql);
            const originalHtml = btnCopy.innerHTML;
            btnCopy.innerHTML = '<span class="lnr lnr-checkmark-circle"></span> Gekopieerd';

            setTimeout(() => {
                btnCopy.innerHTML = originalHtml;
            }, 2000);
        } catch (err) {
            // Fallback
            const textarea = document.createElement('textarea');
            textarea.value = this._sql;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);

            const originalHtml = btnCopy.innerHTML;
            btnCopy.innerHTML = '<span class="lnr lnr-checkmark-circle"></span> Gekopieerd';

            setTimeout(() => {
                btnCopy.innerHTML = originalHtml;
            }, 2000);
        }
    }

}

// Register the component
customElements.define('cma-sql-editor', CmaSqlEditor);

} // End guard against double registration
