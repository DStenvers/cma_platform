/**
 * cma-htmledit Web Component
 *
 * A wrapper for CKEditor that simplifies initialization.
 * Replaces CreateFKEditor, SetFKEditorConfig, CreateSimpleFKEditor functions.
 *
 * Usage:
 *   <cma-htmledit name="content" height="300">
 *       <textarea name="content">Initial HTML content</textarea>
 *   </cma-htmledit>
 *
 * Or with value attribute:
 *   <cma-htmledit name="content" height="300" value="<p>Hello</p>"></cma-htmledit>
 *
 * Attributes:
 *   - name: Field name (required)
 *   - height: Editor height in pixels (default: 300)
 *   - mode: 'full' (default), 'simple', or 'minimal'
 *   - custom-css: Path to custom CSS for editor content
 *   - allow-br: Use BR for enter instead of P
 *   - value: Initial HTML value (alternative to textarea child)
 *
 * Events:
 *   - editor-ready: Fired when CKEditor is initialized. Detail: { editor }
 *   - editor-change: Fired when content changes. Detail: { value }
 */
// Guard against double registration
if (!customElements.get('cma-htmledit')) {

class CmaHtmlEdit extends HTMLElement {
    static get observedAttributes() {
        return ['value', 'disabled'];
    }

    constructor() {
        super();
        this._editor = null;
        this._config = null;
        this._initialized = false;
    }

    connectedCallback() {
        if (this._initialized) return;

        const name = this.getAttribute('name') || '(unnamed)';

        // Wait for CKEditor to be available
        if (typeof CKEDITOR === 'undefined') {
            let attempts = 0;
            const checkCKEditor = setInterval(() => {
                attempts++;
                if (typeof CKEDITOR !== 'undefined') {
                    clearInterval(checkCKEditor);
                    this._initialize();
                }
            }, 100);
            // Timeout after 10 seconds
            setTimeout(() => {
                clearInterval(checkCKEditor);
                if (typeof CKEDITOR === 'undefined') {
                    cmaLog.error('[cma-htmledit] CKEDITOR not available after 10s for "' + name + '". Is ckeditor.js loaded?');
                }
            }, 10000);
        } else {
            this._initialize();
        }
    }

    disconnectedCallback() {
        this._destroy();
    }

    get editor() {
        return this._editor;
    }

    get value() {
        if (this._editor && typeof this._editor.getData === 'function') {
            return this._editor.getData();
        }
        const textarea = this.querySelector('textarea');
        return textarea ? textarea.value : (this.getAttribute('value') || '');
    }

    set value(html) {
        if (this._editor && typeof this._editor.setData === 'function') {
            this._editor.setData(html);
        } else {
            const textarea = this.querySelector('textarea');
            if (textarea) {
                textarea.value = html;
            }
        }
    }

    _initialize() {
        if (this._initialized) return;

        const name = this.getAttribute('name');
        if (!name) {
            cmaLog.error('[cma-htmledit] name attribute is required');
            return;
        }

        // Get or create textarea
        let textarea = this.querySelector('textarea');
        if (!textarea) {
            textarea = document.createElement('textarea');
            textarea.name = name;
            textarea.value = this.getAttribute('value') || '';
            this.appendChild(textarea);
        }

        // Ensure textarea has the right name
        textarea.name = name;
        textarea.id = textarea.id || name;

        // Verify the textarea is actually in the document DOM before CKEditor init
        if (!document.body.contains(textarea)) {
            requestAnimationFrame(() => this._initialize());
            return;
        }

        // Destroy any existing CKEditor instance on this element to avoid conflicts
        if (CKEDITOR.instances[textarea.id]) {
            try { CKEDITOR.instances[textarea.id].destroy(true); } catch (e) { /* safe to ignore */ }
        }

        // Parse attributes
        const height = parseInt(this.getAttribute('height') || '300', 10);
        const mode = this.getAttribute('mode') || 'full';
        const customCss = this.getAttribute('custom-css') || '';
        const allowBr = this.hasAttribute('allow-br');
        const isSimple = mode === 'simple';
        const isMinimal = mode === 'minimal';

        // Get global config if available
        const globalConfig = CMA.formConfig?.editorConfig || {};

        // Build CKEditor config
        const config = this._buildConfig({
            height,
            mode,
            allowBr: allowBr || globalConfig.allowBR || false,
            extraPlugins: globalConfig.extraPlugins || '',
            isSimple,
            isMinimal
        });

        try {
            // Pass the DOM element directly to avoid getElementById lookup failures
            this._editor = CKEDITOR.replace(textarea, config);
        } catch (e) {
            cmaLog.error('[cma-htmledit] CKEDITOR.replace failed for "' + name + '":', e.message);
            return;
        }

        if (this._editor) {
            this._setupEventHandlers();
            this._initialized = true;
        } else {
            cmaLog.error('[cma-htmledit] CKEDITOR.replace returned null for "' + name + '"');
        }
    }

    _buildConfig(options) {
        const config = {};

        // Base config
        config.language = 'nl';
        config.contentsLanguage = 'nl';
        config.defaultLanguage = 'nl';
        config.scayt_sLang = 'nl_NL';
        config.skin = 'office2013_modified';
        config.pasteFromWordPromptCleanup = true;
        config.height = options.height + 'px';
        config.scayt_autoStartup = false;
        config.allowedContent = true;
        config.extraAllowedContent = '*(*){*}[*]';
        config.resize_enabled = false;
        config.entities = true;
        config.basicEntities = true;
        config.latinEntities = true;
        config.greekEntities = true;

        config.contentsCss = '/assets/css/cma.css';

        // Toolbar based on mode
        if (options.isMinimal) {
            config.toolbar_Full = [{ name: 'basic', items: [] }];
            config.extraPlugins = 'myMaximize,myRemoveFormat';
        } else if (options.isSimple) {
            config.toolbar_Full = [
                { name: 'basic', items: ['Cut', 'Copy', 'Paste', 'PasteText', '-', 'Find', 'Replace', '-', 'Undo', 'Redo', '-', 'Bold', 'Italic', '-', 'Styles', '-', 'BulletedList', 'NumberedList', '-', 'myRemoveFormat', '-', 'Image', '-', 'Link', 'Unlink', 'Source', 'myMaximize'] }
            ];
            config.extraPlugins = 'myMaximize,myRemoveFormat,stylescombo' + options.extraPlugins;
        } else {
            // Full mode
            config.extraAllowedContent = 'script; area(*){*}[*]; table(*){*}[*]; h1; h2; h3; i; td(*){*}[*]; form(*){*}[*]; iframe(*){*}[*]; input(*){*}[*]; map(*){*}[*]; button(*){*}[*]; textarea(*){*}[*]; hr(*){*}[*]; tr(*){*}[*]; div(*){*}[*]; span(*){*}[*]; a(*){*}[*]; style(*){*}[*]; img(*){*}[*]; select(*){*}[*]; option(*){*}[*]; object(*){*}[*]; embed(*){*}[*]';
            config.extraPlugins = 'myMaximize,stylescombo,quicktable,imgtitle,videodetector,myRemoveFormat' + options.extraPlugins;
            config.startupOutlineShy = true;
            config.startupShowBorders = true;
            config.toolbarCanCollapse = false;

            config.toolbar_Full = [
                { name: 'basic', items: ['Cut', 'Copy', 'Paste', 'PasteText', '-', 'Find', 'Replace', '-', 'Undo', 'Redo', '-', 'Scayt', '-', 'Bold', 'Italic', 'Underline', '-', 'Styles'] },
                { name: 'paragraph', items: ['BulletedList', 'NumberedList', '-', 'myRemoveFormat', '-', 'Outdent', 'Indent', '-', 'JustifyLeft', 'JustifyCenter', 'JustifyRight', 'JustifyBlock', '-'] },
                { name: 'links', items: ['Link', 'Unlink'] },
                { name: 'insert', items: ['-', 'VideoDetector', 'Image', 'imgtitle', 'Table', 'SpecialChar', 'HorizontalRule'] },
                { name: 'tools', items: ['Source', 'myMaximize'] }
            ];

            // Quick table defaults
            config.qtBorder = '1';
            config.qtCellPadding = '4';
            config.qtCellSpacing = '0';
            config.qtStyle = { 'border-collapse': 'collapse', 'border': '1px solid #cccccc' };
            config.qtClass = 'cke_show_border';
            config.qtWidth = '100%';
        }

        config.toolbar = 'Full';

        // Custom styles
        try {
            CKEDITOR.stylesSet.add('my_styles', [
                { name: 'Titel', element: 'h3', attributes: { 'class': 'sectionTitle__title' } },
                { name: 'SubTitel', element: 'h4', attributes: { 'class': 'sectionSubTitle__title' } }
            ]);
        } catch (e) {
            // StylesSet may already exist - ignore duplicate registration
        }
        config.stylesSet = 'my_styles';

        // Enter mode
        if (options.allowBr) {
            config.enterMode = CKEDITOR.ENTER_BR;
        }

        return config;
    }

    _setupEventHandlers() {
        const editor = this._editor;
        const fieldname = this.getAttribute('name');

        // Soft hyphen shortcut (Alt + -)
        editor.on('contentDom', () => {
            editor.document.on('keyup', (event) => {
                if (event.data.$.altKey && (event.data.$.keyCode === 173 || event.data.$.keyCode === 219)) {
                    editor.insertHtml('&shy;');
                }
            });
        });

        // Instance ready - override commands
        editor.on('instanceReady', (event) => {

            // Override horizontal rule
            if (editor.commands.horizontalrule) {
                const hrCmd = new CKEDITOR.command(editor, {
                    exec: () => editor.insertHtml('<hr noshade size=1>')
                });
                editor.commands.horizontalrule.exec = hrCmd.exec;
            }

            // Override image command
            if (editor.commands.image) {
                const imgCmd = new CKEDITOR.command(editor, {
                    exec: () => {
                        if (CMA.editor.isCursorInImage(editor)) {
                            CMA.editor.imageProperties(editor);
                        } else {
                            CMA.editor.insertImage(editor);
                        }
                    }
                });
                editor.commands.image.exec = imgCmd.exec;
            } else {
                cmaLog.warn('[cma-htmledit] No "image" command available for "' + fieldname + '"');
            }

            // Override table command
            if (editor.commands.table) {
                const tableCmd = new CKEDITOR.command(editor, {
                    exec: () => {
                        if (CMA.editor.isCursorInTable(editor)) {
                            CMA.editor.tableProperties(editor);
                        } else {
                            CMA.editor.insertTable(editor);
                        }
                    }
                });
                editor.commands.table.exec = tableCmd.exec;
                if (editor.commands.tableProperties) {
                    editor.commands.tableProperties.exec = tableCmd.exec;
                }
            } else {
                cmaLog.warn('[cma-htmledit] No "table" command available for "' + fieldname + '"');
            }

            // Override link command
            if (editor.commands.link) {
                const linkCmd = new CKEDITOR.command(editor, {
                    exec: () => {
                        if (CMA.editor.isCursorInAnchor(editor)) {
                            CMA.editor.anchorProperties(editor);
                        } else {
                            CMA.editor.insertLink(editor);
                        }
                    }
                });
                editor.commands.link.exec = linkCmd.exec;
            } else {
                cmaLog.warn('[cma-htmledit] No "link" command available for "' + fieldname + '"');
            }

            // Dispatch ready event
            this.dispatchEvent(new CustomEvent('editor-ready', {
                bubbles: true,
                detail: { editor }
            }));
        });

        // Change event
        editor.on('change', () => {
            this.dispatchEvent(new CustomEvent('editor-change', {
                bubbles: true,
                detail: { value: editor.getData() }
            }));
        });
    }

    _destroy() {
        const name = this.getAttribute('name') || '(unnamed)';
        if (this._editor) {
            try {
                this._editor.destroy();
            } catch (e) { /* safe to ignore */ }
            this._editor = null;
        }
        this._initialized = false;
    }

    attributeChangedCallback(name, oldValue, newValue) {
        if (name === 'value' && this._editor && typeof this._editor.setData === 'function' && oldValue !== newValue) {
            this._editor.setData(newValue || '');
        }
        if (name === 'disabled') {
            if (this._editor && typeof this._editor.setReadOnly === 'function') {
                this._editor.setReadOnly(newValue !== null);
            }
        }
    }
}

// Register the component
customElements.define('cma-htmledit', CmaHtmlEdit);

} // end guard

// Backward compatibility shims
window.SetFKEditorConfig = function(config) {
    CMA.editor.setConfig(config);
};

window.CreateFKEditor = function(fieldname, nSize, bSpamJS, nHeight, bSimple, bNoToolbar) {
    // Check if there's a cma-htmledit component for this field
    const component = document.querySelector(`cma-htmledit[name="${fieldname}"]`);
    if (component) {
        // Component handles its own initialization
        return;
    }
    // Fall back to CMA.editor.create
    CMA.editor.create(fieldname, nSize, bSpamJS, nHeight, bSimple, bNoToolbar);
};

window.CreateSimpleFKEditor = function(fieldname, nSize, nHeight) {
    const component = document.querySelector(`cma-htmledit[name="${fieldname}"]`);
    if (component) {
        return;
    }
    CMA.editor.createSimple(fieldname, nSize, nHeight);
};
