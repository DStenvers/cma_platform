// ============================================================================
// CMA JavaScript - Refactored with proper namespacing
// ============================================================================
//
// All functionality is contained within the CMA namespace.
// Legacy global functions are provided as backward-compat shims at the end.
//
(function() {
    'use strict';

    // Initialize CMA namespace
    window.CMA = window.CMA || {};

    // ========================================================================
    // jQuery Ready Queue - handles async jQuery loading
    // ========================================================================
    (function() {
        let jQueryReady = (typeof jQuery !== 'undefined');
        const jQueryQueue = [];

        CMA.ready = function(callback) {
            if (jQueryReady) {
                callback(jQuery);
            } else {
                jQueryQueue.push(callback);
            }
        };

        CMA.initjQuery = function() {
            if (typeof jQuery !== 'undefined') {
                jQueryReady = true;
                while (jQueryQueue.length > 0) {
                    const cb = jQueryQueue.shift();
                    try {
                        cb(jQuery);
                    } catch (e) {
                        cmaLog.error('CMA jQuery callback error:', e.message, { stack: e.stack });
                    }
                }
            }
        };

        if (!jQueryReady) {
            cmaLog.warn('CMA: jQuery not loaded yet. Some features will be delayed.');
            // Use 200ms interval instead of 50ms to reduce CPU overhead
            // Also track both interval and timeout for proper cleanup
            let checkCount = 0;
            const maxChecks = 50; // 10 seconds at 200ms intervals
            const checkInterval = setInterval(function() {
                checkCount++;
                if (typeof jQuery !== 'undefined') {
                    clearInterval(checkInterval);
                    CMA.initjQuery();
                } else if (checkCount >= maxChecks) {
                    clearInterval(checkInterval);
                    cmaLog.error('CMA: jQuery failed to load after 10 seconds');
                }
            }, 200);
        }
    })();

    // ========================================================================
    // Tree Module - Folder/Item tree navigation
    // @deprecated Use <cma-tree> web component instead. This module is kept
    // for backward compatibility with legacy forms that still generate inline
    // gFld()/F()/D()/I() scripts. Remove once all grouped trees use cma-tree.
    // ========================================================================
    CMA.tree = (function() {
        // Private state - NOT global
        let indexOfEntries = [];
        let nEntries = 0;
        let cookieID = '';
        let delaySave = false;
        let buffered = true;
        let buffer = '';

        // Folder constructor
        function Folder(folderDescription, hreference) {
            this.desc = folderDescription;
            this.hreference = hreference;
            this.id = -1;
            this.isLastNode = false;
            this.parent = null;
            this.mainObj = null;
            this.isOpen = false;
            this.iconClass = 'f_' + (this.isOpen ? 'open' : 'closed');
            this.children = [];
            this.nChildren = 0;
            this.isRendered = true;

            this.initialize = initializeFolder;
            this.setState = setStateFolder;
            this.addChild = addChild;
            this.createIndex = createEntryIndex;
            this.hide = hideFolder;
            this.display = displayFolder;
            this.renderOb = drawFolder;
        }

        // Item constructor
        function Item(itemDescription, itemLink, colorClass) {
            this.desc = itemDescription;
            this.link = itemLink;
            this.colorClass = colorClass || '';
            this.id = -1;
            this.parent = null;
            this.isRendered = false;

            this.initialize = initializeItem;
            this.createIndex = createEntryIndex;
            this.hide = hideItem;
            this.display = displayItem;
            this.renderOb = drawItem;
        }

        function bufferedWrite(stext) {
            if (buffered) {
                buffer += stext;
            } else {
                document.write(stext);
            }
        }

        function bufferedFlush() {
            if (buffered) {
                document.write(buffer);
                buffer = '';
            }
        }

        function setStateFolder(isOpen) {
            if (isOpen !== this.isOpen) {
                this.isOpen = isOpen;
                propagateChangesInState(this);
            }
        }

        function propagateChangesInState(folder) {
            const children = document.getElementById('_ni' + folder.id);
            const fldElt = document.getElementById('_f' + folder.id);

            folder.iconClass = folder.isOpen ? 'f_open' : 'f_closed';
            if (folder.isRendered && fldElt) {
                // Use classList to preserve other classes
                fldElt.classList.remove('f_open', 'f_closed');
                fldElt.classList.add(folder.iconClass);
            }

            if (folder.isOpen) {
                if (children) {
                    // Use classList to preserve the 't' class
                    children.classList.remove('f_closed');
                    children.classList.add('f_open');
                }
                if (folder.nChildren === 1 && folder.children[0].nChildren > 0) {
                    folder.children[0].setState(true);
                }
                // Scroll expanded folder into view
                if (fldElt) {
                    setTimeout(function() {
                        fldElt.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    }, 50);
                }
            } else {
                if (children) {
                    // Use classList to preserve the 't' class
                    children.classList.remove('f_open');
                    children.classList.add('f_closed');
                }
            }
        }

        function initializeFolder(level) {
            this.createIndex();
            this.renderOb('');

            const nc = this.nChildren;
            if (nc > 0) {
                if (level > 0) {
                    bufferedWrite('<ul id=_ni' + this.id + ' class="t ' + (this.isOpen ? 'f_open' : 'f_closed') + '">');
                }
                level++;
                for (let i = 0; i < nc; i++) {
                    this.children[i].initialize(level, i === nc - 1 ? 1 : 0);
                }
                if (level > 0) {
                    bufferedWrite('</ul>');
                }
            }
        }

        function drawFolder() {
            if (this.id === 0) {
                bufferedWrite('<div class=titel>' + this.desc + '</div>');
            } else {
                bufferedWrite('<li id=_f' + this.id + ' class=' + (this.isOpen ? 'f_open' : 'f_closed') + '>' +
                    '<a onclick="CMA.tree.clickOnFolder(' + this.id + ')">' + this.desc + '</a>');
            }

            if (!buffered) {
                this.mainObj = document.getElementById('_f' + this.id);
            }
        }

        function addChild(childNode) {
            this.children[this.nChildren] = childNode;
            childNode.parent = this;
            this.nChildren++;
            return childNode;
        }

        function initializeItem(level, lastNode) {
            this.createIndex();
            this.level = level;
            this.isLastNode = lastNode;
            this.renderOb();
        }

        function drawItem() {
            if (!this.isRendered) {
                const colorAttr = this.colorClass ? ' ' + this.colorClass : '';
                const sItem = '<li id=_i' + this.id + ' class="' + colorAttr.trim() + '">' +
                    '<a id=_h' + this.id + ' class=t href=' + this.link +
                    (this.desc.indexOf("'") < 0 ? " data-tooltip='" + this.desc + "'" : '') +
                    ' onclick="CMA.tree.hi(\'_h' + this.id + '\')">' + this.desc + '</a></li>';
                bufferedWrite(sItem);
                this.isRendered = true;
            }
        }

        function displayFolder() {
            if (!this.mainObj) {
                this.mainObj = document.getElementById('_f' + this.id);
            }
            if (this.mainObj) {
                this.mainObj.className = 'f_open';
            }
        }

        function hideFolder() {
            if (!this.mainObj) {
                this.mainObj = document.getElementById('_f' + this.id);
            }
            if (this.mainObj) {
                this.mainObj.className = 'f_closed';
            }
        }

        function displayItem() {
            if (this.isRendered) {
                const navObj = document.getElementById('_i' + this.id);
                if (navObj && navObj.style.display !== 'block') {
                    navObj.style.display = 'block';
                } else if (!navObj) {
                    this.renderOb(this.parent.mainObj);
                }
            }
        }

        function hideItem() {
            if (this.isRendered) {
                const navObj = document.getElementById('_i' + this.id);
                if (navObj && navObj.style.display !== 'none') {
                    navObj.style.display = 'none';
                }
            }
        }

        function createEntryIndex() {
            this.id = nEntries;
            indexOfEntries[nEntries] = this;
            nEntries++;
        }

        function hi(sID) {
            if (typeof jQuery === 'undefined') {
                const active = document.querySelectorAll('.complextree li a.active');
                for (let i = 0; i < active.length; i++) {
                    active[i].classList.remove('active');
                }
                const el = document.getElementById(sID);
                if (el) el.classList.add('active');
            } else {
                jQuery('.complextree li a.active').removeClass('active');
                jQuery('#' + sID).addClass('active');
            }
        }

        function clickOnFolder(folderId) {
            clickOnNode(folderId);
            saveTree();
        }

        function clickOnNode(folderId) {
            const clickedFolder = indexOfEntries[folderId];
            clickedFolder.setState(!clickedFolder.isOpen);
            if (folderId !== 0) saveTree();
        }

        function loadTreeState(sCookieID) {
            return lib_storage_get(sCookieID, 'tree') || '';
        }

        function saveTree() {
            if (!delaySave) {
                let s = '';
                for (let a = 1; a < nEntries; a++) {
                    const x = indexOfEntries[a];
                    if (x.isOpen) {
                        s += (x.parent ? x.parent.desc + '||' : '') + '|' + x.desc + ':';
                    }
                }
                lib_storage_set(cookieID, ':' + s, 'tree');
            }
        }

        function restoreTree(sOpenFolders) {
            if (sOpenFolders) {
                delaySave = true;
                for (let a = 1; a < nEntries; a++) {
                    const entry = indexOfEntries[a];
                    const sName = (entry.parent ? ':' + entry.parent.desc + '||' : '') + '|' + entry.desc + ':';
                    if (sOpenFolders.indexOf(sName) > -1) {
                        if (entry.nChildren > 0 && !entry.isOpen) {
                            entry.setState(true);
                            if (entry.parent && !entry.parent.isOpen) {
                                entry.parent.setState(true);
                            }
                        }
                    }
                }
                delaySave = false;
            }
        }

        function initializeDocument(sCookieID, sOpenFolders, sItemIconClass) {
            if (typeof window.T !== 'undefined') {
                document.write('<div class=complextree>');
                window.T.initialize(0, 1, '');
                bufferedFlush();
                document.write('</div>');

                if (sItemIconClass) {
                    if (typeof jQuery !== 'undefined') {
                        jQuery('.complextree li a[href]').addClass('icon').addClass(sItemIconClass);
                    } else {
                        const links = document.querySelectorAll('.complextree li a[href]');
                        for (let i = 0; i < links.length; i++) {
                            links[i].classList.add('icon');
                            links[i].classList.add(sItemIconClass);
                        }
                    }
                }

                cookieID = sCookieID;

                if (!sOpenFolders || sOpenFolders === '') {
                    sOpenFolders = loadTreeState(sCookieID);
                }

                clickOnNode(window.T.id);
                restoreTree(sOpenFolders);
            }
        }

        function initializeToElement(elementId, sCookieID, sOpenFolders, sItemIconClass, treeRoot) {
            const targetElement = document.getElementById(elementId);
            if (!targetElement) {
                cmaLog.error('[Tree] Target element not found:', elementId);
                return;
            }

            // Get tree root from parameter, element storage, or legacy window global
            const root = treeRoot || targetElement._treeRoot || window.T;
            if (!root) {
                cmaLog.error('[Tree] Root element not available - no treeRoot parameter, element._treeRoot, or window.T');
                return;
            }

            // Reset state for fresh tree
            indexOfEntries = [];
            nEntries = 0;
            buffer = '';
            buffered = true;

            bufferedWrite('<div class=complextree>');
            root.initialize(0, 1, '');
            bufferedWrite('</div>');

            targetElement.innerHTML = buffer;
            buffer = '';

            // Clear element storage after use
            if (targetElement._treeRoot) {
                targetElement._treeRoot = null;
            }

            if (sItemIconClass) {
                if (typeof jQuery !== 'undefined') {
                    jQuery(targetElement).find('li a[href]').addClass('icon').addClass(sItemIconClass);
                } else {
                    const links = targetElement.querySelectorAll('li a[href]');
                    for (let i = 0; i < links.length; i++) {
                        links[i].classList.add('icon');
                        links[i].classList.add(sItemIconClass);
                    }
                }
            }

            cookieID = sCookieID;

            if (!sOpenFolders || sOpenFolders === '') {
                sOpenFolders = loadTreeState(sCookieID);
            }

            clickOnNode(root.id);
            restoreTree(sOpenFolders);
        }

        function expandAll() {
            const oldcursor = document.body.style.cursor;
            document.body.style.cursor = 'wait';
            delaySave = true;
            for (let a = 1; a < nEntries; a++) {
                const x = indexOfEntries[a];
                if (x.children) x.setState(true);
            }
            delaySave = false;
            saveTree();
            document.body.style.cursor = oldcursor;
        }

        function collapseAll() {
            const oldcursor = document.body.style.cursor;
            document.body.style.cursor = 'wait';
            delaySave = true;
            for (let a = 1; a < nEntries; a++) {
                const x = indexOfEntries[a];
                if (x.children) x.setState(false);
            }
            delaySave = false;
            saveTree();
            document.body.style.cursor = oldcursor;
        }

        // Helper functions for creating tree nodes
        function gFld(desc) {
            return new Folder(desc, '');
        }

        function gLnk(target, description, linkData) {
            return new Item(description, "'" + linkData + "' target=" + (target === 1 ? '_blank' : 'R'));
        }

        function F(parentFolder, childFolder) {
            return parentFolder.addChild(childFolder);
        }

        function D(parentFolder, doc) {
            parentFolder.addChild(doc);
        }

        function I(target, description, linkData, colorClass) {
            return new Item(description, "'" + linkData + "' target=" + (target === 1 ? '_blank' : 'R'), colorClass);
        }

        // Public API
        return {
            Folder: Folder,
            Item: Item,
            hi: hi,
            clickOnFolder: clickOnFolder,
            clickOnNode: clickOnNode,
            initializeDocument: initializeDocument,
            initializeToElement: initializeToElement,
            expandAll: expandAll,
            collapseAll: collapseAll,
            saveTree: saveTree,
            restoreTree: restoreTree,
            gFld: gFld,
            gLnk: gLnk,
            F: F,
            D: D,
            I: I
        };
    })();

    // ========================================================================
    // Editor Module - CKEditor integration
    // ========================================================================
    CMA.editor = (function() {
        let htmlEditConfig = null;

        function setConfig(config) {
            // cmaLog.log('[CKEditor] setConfig:', config);
            htmlEditConfig = config;
        }

        function createSimple(fieldname, nSize, nHeight) {
            create(fieldname, nSize, false, nHeight, true, false);
        }

        function create(fieldname, nSize, bSpamJS, nHeight, bSimple, bNoToolbar) {
            nHeight = nHeight || 300;
            bSimple = bSimple || false;
            bNoToolbar = bNoToolbar || false;

            if (!fieldname) {
                cmaLog.error('[CreateFKEditor] Fieldname not specified');
                return;
            }

            const config = {};
            config.contentsCss = '/assets/css/cma.css';
            // Verify contentsCss is accessible (error only)
            fetch(config.contentsCss).then(r => {
                if (!r.ok) cmaLog.error('[CKEditor] contentsCss not found:', config.contentsCss, r.status);
            }).catch(() => {});
            config.language = 'nl';
            config.contentsLanguage = config.language;
            config.defaultLanguage = config.contentsLanguage;
            config.scayt_sLang = 'nl_NL';
            config.skin = 'office2013_modified';
            config.pasteFromWordPromptCleanup = true;
            if (nHeight) {
                config.height = nHeight.toString() + 'px';
            }
            config.scayt_autoStartup = false;
            config.allowedContent = true;
            config.extraAllowedContent = '*(*){*}[*]';

            if (bNoToolbar) {
                config.toolbar_Full = [{ name: 'basic', items: [] }];
            } else if (bSimple) {
                config.toolbar_Full = [
                    { name: 'basic', items: ['Cut', 'Copy', 'Paste', 'PasteText', '-', 'Find', 'Replace', '-', 'Undo', 'Redo', '-', 'Bold', 'Italic', '-', 'Styles', '-', 'BulletedList', 'NumberedList', '-', 'myRemoveFormat', '-', 'Image', '-', 'Link', 'Unlink', 'Source', 'myMaximize'] }
                ];
                config.extraPlugins = 'myMaximize,myRemoveFormat,stylescombo' + ((htmlEditConfig && htmlEditConfig.extraPlugins) ? htmlEditConfig.extraPlugins : '');
            } else {
                config.extraAllowedContent = 'script; area(*){*}[*]; table(*){*}[*]; h1; h2; h3; i; td(*){*}[*]; form(*){*}[*]; iframe(*){*}[*]; input(*){*}[*]; map(*){*}[*]; button(*){*}[*]; textarea(*){*}[*]; hr(*){*}[*]; tr(*){*}[*]; div(*){*}[*]; span(*){*}[*]; a(*){*}[*]; style(*){*}[*]; img(*){*}[*]; select(*){*}[*]; option(*){*}[*]; object(*){*}[*]; embed(*){*}[*]';
                config.extraPlugins = 'myMaximize,stylescombo,quicktable,imgtitle,videodetector,myRemoveFormat' + ((htmlEditConfig && htmlEditConfig.extraPlugins) ? htmlEditConfig.extraPlugins : '');
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
                config.toolbar_Basic = [
                    { name: 'basic', items: ['Cut', 'Copy', 'PasteText', '-', 'Bold', 'Italic', 'Underline', '-', 'Styles', '-'] },
                    { name: 'paragraph', items: ['-', 'NumberedList', 'BulletedList', '-', 'myRemoveFormat', '-', 'JustifyLeft', 'JustifyCenter', 'JustifyRight', 'JustifyBlock', '-'] },
                    { name: 'links', items: ['Link', 'Unlink'] },
                    { name: 'insert', items: ['-', 'Image', 'Table', 'SpecialChar'] },
                    { name: 'tools', items: ['-', 'SwitchBar', 'myMaximize'] }
                ];
                config.switchBarSimple = 'Basic';
                config.switchBarReach = 'Full';
                config.switchBarDefault = 'Full';
            }

            config.resize_enabled = false;
            config.entities = true;
            config.basicEntities = true;
            config.latinEntities = true;
            config.greekEntities = true;
            config.toolbar = 'Full';

            // Register custom styles only once (check if already registered)
            if (!CKEDITOR.stylesSet.registered || !CKEDITOR.stylesSet.registered['my_styles']) {
                try {
                    CKEDITOR.stylesSet.add('my_styles', [
                        { name: 'Titel', element: 'h3', attributes: { 'class': 'sectionTitle__title' } },
                        { name: 'SubTitel', element: 'h4', attributes: { 'class': 'sectionSubTitle__title' } }
                    ]);
                    // Mark as registered to prevent duplicate registration
                    CKEDITOR.stylesSet.registered = CKEDITOR.stylesSet.registered || {};
                    CKEDITOR.stylesSet.registered['my_styles'] = true;
                } catch (e) {
                    // Style set may already be registered, which is fine
                    cmaLog.warn('Could not register CKEditor styles:', e);
                }
            }
            config.stylesSet = 'my_styles';

            if (htmlEditConfig && htmlEditConfig.allowBR) {
                config.enterMode = CKEDITOR.ENTER_BR;
            }
            if (!bSimple) {
                config.qtBorder = '1';
                config.qtCellPadding = '4';
                config.qtCellSpacing = '0';
                config.qtStyle = { 'border-collapse': 'collapse', 'border': '1px solid #cccccc' };
                config.qtClass = 'cke_show_border';
                config.qtWidth = '100%';
            }

            // cmaLog.log('[CKEditor.create] Calling CKEDITOR.replace for:', fieldname, { height: config.height, contentsCss: config.contentsCss, extraPlugins: config.extraPlugins });
            try {
                CKEDITOR.replace(fieldname, config);
                // cmaLog.log('[CKEditor.create] CKEDITOR.replace called successfully for:', fieldname);
            } catch (e) {
                cmaLog.error('[CKEditor.create] CKEDITOR.replace FAILED for:', fieldname, e.message);
                // Restore textarea visibility on error
                var ta = document.getElementById(fieldname);
                if (ta && ta.style.visibility === 'hidden') {
                    ta.style.visibility = 'visible';
                }
                return;
            }

            if (CKEDITOR.instances[fieldname]) {
                // cmaLog.log('[CKEditor.create] Instance created, setting up events for:', fieldname);

                // Set dirty flag when CKEditor content changes
                CKEDITOR.instances[fieldname].on('change', function() {
                    CMA.form.setDirty();
                    // cmaLog.log('[CKEditor] Content changed, form marked dirty');

                    // Update required field indicator based on content
                    updateCKEditorRequiredState(this);
                });

                CKEDITOR.instances[fieldname].on('contentDom', function() {
                    CKEDITOR.instances[fieldname].document.on('keyup', function(event) {
                        if ((event.data.$.altKey) && (event.data.$.keyCode === 173 || event.data.$.keyCode === 219)) {
                            CKEDITOR.instances[fieldname].insertHtml('&shy;');
                        }
                    });
                });

                CKEDITOR.instances[fieldname].on('instanceReady', function(event) {
                    const editorName = event.sender.name;

                    // Override horizontal rule
                    const overridecmdHr = new CKEDITOR.command(CKEDITOR.instances[editorName], {
                        exec: function() {
                            CKEDITOR.instances[editorName].insertHtml('<hr noshade size=1>');
                        }
                    });
                    CKEDITOR.instances[editorName].commands.horizontalrule.exec = overridecmdHr.exec;

                    // Override image
                    const overridecmdImage = new CKEDITOR.command(CKEDITOR.instances[editorName], {
                        exec: function() {
                            if (isCursorInImage(CKEDITOR.instances[editorName])) {
                                imageProperties(CKEDITOR.instances[editorName]);
                            } else {
                                insertImage(CKEDITOR.instances[editorName]);
                            }
                        }
                    });
                    CKEDITOR.instances[editorName].commands.image.exec = overridecmdImage.exec;

                    // Override table
                    const overridecmdTable = new CKEDITOR.command(CKEDITOR.instances[editorName], {
                        exec: function() {
                            if (isCursorInTable(CKEDITOR.instances[editorName])) {
                                tableProperties(CKEDITOR.instances[editorName]);
                            } else {
                                insertTable(CKEDITOR.instances[editorName]);
                            }
                        }
                    });
                    CKEDITOR.instances[editorName].commands.table.exec = overridecmdTable.exec;
                    CKEDITOR.instances[editorName].commands.tableProperties.exec = overridecmdTable.exec;

                    // Override link
                    const overridecmdLink = new CKEDITOR.command(CKEDITOR.instances[editorName], {
                        exec: function() {
                            if (isCursorInAnchor(CKEDITOR.instances[editorName])) {
                                anchorProperties(CKEDITOR.instances[editorName]);
                            } else {
                                insertLink(CKEDITOR.instances[editorName]);
                            }
                        }
                    });
                    CKEDITOR.instances[editorName].commands.link.exec = overridecmdLink.exec;

                    // Check initial required field state
                    updateCKEditorRequiredState(CKEDITOR.instances[editorName]);
                });
            } else {
                cmaLog.warn('[CKEditor.create] Instance NOT created immediately for:', fieldname);
                // Restore textarea visibility
                var ta = document.getElementById(fieldname);
                if (ta && ta.style.visibility === 'hidden') {
                    // cmaLog.warn('[CKEditor.create] Restoring textarea visibility for:', fieldname);
                    ta.style.visibility = 'visible';
                }
            }
        }

        // Update required field indicator for CKEditor based on content
        function updateCKEditorRequiredState(editor) {
            if (!editor || !editor.name) return;

            const textarea = document.getElementById(editor.name);
            if (!textarea) return;

            // Only process if this is a required field
            if (textarea.getAttribute('data-required') !== 'true') return;

            // Get the CKEditor container
            const ckeContainer = document.getElementById('cke_' + editor.name);
            if (!ckeContainer) return;

            // Check if editor has content (strip HTML and check for text)
            const content = editor.getData();
            const textContent = content.replace(/<[^>]*>/g, '').replace(/&nbsp;/g, ' ').trim();
            const hasContent = textContent.length > 0;

            // Toggle has-content class on the CKEditor container
            if (hasContent) {
                ckeContainer.classList.add('has-content');
            } else {
                ckeContainer.classList.remove('has-content');
            }
        }

        // Editor helper functions
        let selectedTD = null;
        let selectedTR = null;
        let selectedTable = null;
        let selectedImage = null;
        let selectedAnchor = null;

        function getSelectedElement(editor) {
            try {
                return editor.getSelection().getStartElement();
            } catch (e) {
                return null;
            }
        }

        function isCursorInTable(editor) {
            const el = getSelectedElement(editor);
            if (!el) return false;
            const td = el.getAscendant('td', true);
            if (td) {
                selectedTD = td;
                selectedTR = td.getAscendant('tr', true);
                selectedTable = selectedTR ? selectedTR.getAscendant('table', true) : null;
                return true;
            }
            return false;
        }

        function isCursorInImage(editor) {
            const el = getSelectedElement(editor);
            if (!el) return false;
            if (el.$.tagName === 'IMG') {
                selectedImage = el;
                return true;
            }
            return false;
        }

        function isCursorInAnchor(editor) {
            const el = getSelectedElement(editor);
            if (!el) return false;
            const anchor = el.getAscendant('a', true);
            if (anchor) {
                selectedAnchor = anchor;
                return true;
            }
            return false;
        }

        function insertLink(editor) {
            const winWidth = 500;
            const winHeight = 350;
            top.activeEditor = editor;
            lib_OpenWindowCentered('html_edit_link.php?mode=insert', 'link', winWidth, winHeight, 'Link invoegen');
        }

        function insertImage(editor) {
            top.activeEditor = editor;
            _openImageDialog('imageupload_crop.php?path=/uploads/images/', 'Afbeelding invoegen');
        }

        function insertTable(editor) {
            const winWidth = 460;
            const winHeight = 380;
            top.activeEditor = editor;
            lib_OpenWindowCentered('html_edit_table.php?mode=insert', 'table', winWidth, winHeight, 'Tabel invoegen');
        }

        function tableProperties(editor) {
            const winWidth = 460;
            const winHeight = 380;
            top.activeEditor = editor;
            top.selectedTable = selectedTable;
            lib_OpenWindowCentered('html_edit_table.php?mode=edit', 'table', winWidth, winHeight, 'Tabeleigenschappen');
        }

        function imageProperties(editor) {
            const winWidth = 500;
            const winHeight = 400;
            top.activeEditor = editor;
            top.selectedImage = selectedImage;
            lib_OpenWindowCentered('html_edit_image.php', 'imageprops', winWidth, winHeight, 'Afbeeldingseigenschappen');
        }

        function anchorProperties(editor) {
            const winWidth = 500;
            const winHeight = 350;
            top.activeEditor = editor;
            top.selectedAnchor = selectedAnchor;
            lib_OpenWindowCentered('html_edit_link.php?mode=edit', 'link', winWidth, winHeight, 'Linkeigenschappen');
        }

        function showLiteratuurDialog(editor) {
            // Literatuur zoeken is only available for RINO Portal
            const appName = (CMA.formConfig && CMA.formConfig.appName) || '';
            if (appName.toLowerCase().indexOf('rino') === -1) {
                if (typeof libAlert === 'function') {
                    libAlert('Deze functie is niet beschikbaar in deze applicatie.', { type: 'info' });
                } else {
                    libAlert('Deze functie is niet beschikbaar in deze applicatie.');
                }
                return;
            }

            const winWidth = 700;
            const winHeight = 500;
            top.activeEditor = editor;
            lib_OpenWindowCentered('zoek_literatuur.php?popup=Y', 'literatuur', winWidth, winHeight, 'Zoek literatuur');
        }

        return {
            setConfig: setConfig,
            create: create,
            createSimple: createSimple,
            isCursorInTable: isCursorInTable,
            isCursorInImage: isCursorInImage,
            isCursorInAnchor: isCursorInAnchor,
            insertLink: insertLink,
            insertImage: insertImage,
            insertTable: insertTable,
            tableProperties: tableProperties,
            imageProperties: imageProperties,
            anchorProperties: anchorProperties,
            showLiteratuurDialog: showLiteratuurDialog
        };
    })();

    // ========================================================================
    // Form Module - Form utilities and validation
    // ========================================================================
    CMA.form = (function() {
        // Private state
        let changelogEl = null;
        let changelogFlds = null;
        let changelogType = null;
        let dirtySet = false;

        function specTrim(strValue) {
            while (strValue.charCodeAt(strValue.length - 1) === 32 ||
                   strValue.charCodeAt(strValue.length - 1) === 10 ||
                   strValue.charCodeAt(strValue.length - 1) === 13) {
                strValue = strValue.substr(0, strValue.length - 1);
            }
            while (strValue.charCodeAt(0) === 32 ||
                   strValue.charCodeAt(0) === 10 ||
                   strValue.charCodeAt(0) === 13) {
                strValue = strValue.substr(1, strValue.length - 1);
            }
            return strValue;
        }

        function specCharReplace(stext) {
            stext = stext.replace(/&#916;/g, String.fromCharCode(916));
            stext = stext.replace(/&#969;/g, String.fromCharCode(969));
            return stext;
        }

        function changeInit(form) {
            changelogEl = document.getElementById('_changelog');
            changelogFlds = document.getElementById('_changelog_flds');
            changelogType = document.getElementById('_changelog_type');
            return changelogEl;
        }

        function changeClear() {
            if (changelogEl) changelogEl.value = '';
        }

        function changeAdd(form, oFld, sOld, sNew) {
            if (!changelogEl) return;

            const actionEl = document.getElementById('actie');
            const blnChange = changelogType && changelogType.value === 'edit' && actionEl && actionEl.value !== 'delete';
            const thstyle = 'style=font-size:10pt;background-color:#002350;color:white;text-align:left';

            if (changelogEl.value === '') {
                let sHead = '<table cellspacing=0 cellpadding=3><tr><th ' + thstyle + '>Veld</th>';
                if (blnChange) {
                    sHead += '<th ' + thstyle + '>was</th><th ' + thstyle + '>gewijzigd in</th></tr>';
                } else {
                    sHead += '<th ' + thstyle + '>Inhoud</th></tr>';
                }
                changelogEl.value = sHead;
            }

            let sFld;
            if (form && form[oFld.name + '__label']) {
                sFld = form[oFld.name + '__label'].value;
            } else {
                sFld = oFld.name;
            }

            let sLine = '\r\n<TR id="' + sFld + '" vAlign=Top><TD style="border-bottom:1px solid #003366;border-left:1px solid #003366;font-size:10pt">' + sFld + '</TD>';
            if (blnChange) {
                sLine += '<TD style="border-bottom:1px solid #003366">' + sOld + '&nbsp;</TD>';
            }
            sLine += '<TD style="border-bottom:1px solid #003366;border-right:1px solid #003366;font-size:10pt">' + sNew + '&nbsp;</TD></TR>';
            changelogEl.value += sLine;
            changelogFlds.value = (changelogFlds.value !== '' ? changelogFlds.value + ',' : '') + oFld.name;
        }

        function changeClose() {
            if (changelogEl && changelogEl.value !== '') {
                changelogEl.value += '\r\n</table>';
            }
        }

        function isDirty(form, bQuick) {
            let blnRetval = false;

            try {
                // Sync all CKEditor instances to their textareas before checking
                if (typeof CKEDITOR !== 'undefined' && CKEDITOR.instances) {
                    for (const name in CKEDITOR.instances) {
                        if (CKEDITOR.instances.hasOwnProperty(name)) {
                            try {
                                CKEDITOR.instances[name].updateElement();
                            } catch (e) {
                                // Ignore errors from destroyed instances
                            }
                        }
                    }
                }

                changeInit(form);
                const bShowAll = changelogEl && changelogType && changelogType.value === 'add';

                changeClear();

                for (let tel = 0; tel < form.length; tel++) {
                    const objfield = form[tel];

                    if (!objfield.name) continue;

                    // Skip changelog and internal fields
                    if (objfield.name.substr(0, 10) === '_changelog' ||
                        objfield.name.substr(objfield.name.length - 7) === '__label' ||
                        objfield.name.substr(objfield.name.length - 6) === '_width' ||
                        objfield.name.substr(objfield.name.length - 7) === '_height' ||
                        objfield.name.substr(objfield.name.length - 5) === '_path' ||
                        objfield.name.substr(objfield.name.length - 11) === '_resizetype' ||
                        objfield.name.substr(objfield.name.length - 13) === '_resizeheight' ||
                        objfield.name.substr(objfield.name.length - 12) === '_resizewidth' ||
                        objfield.name.substr(0, 10) === 'blockedit_' ||
                        objfield.name.substr(0, 5) === '_old_') {
                        continue;
                    }

                    if (objfield.type === 'text' || objfield.type === 'file' ||
                        objfield.type === 'password' || objfield.type === 'textarea') {

                        if (objfield.value !== objfield.defaultValue || bShowAll) {
                            blnRetval = true;

                            let sOld, sNew;
                            if (objfield.className && objfield.className.search('select2') > -1) {
                                sOld = objfield.getAttribute('data-default_value') || objfield.defaultValue;
                                const data = jQuery('input[name="' + objfield.name + '"]').select2('data');
                                sNew = data ? data.text : '';
                            } else {
                                objfield.value = specTrim(objfield.value);
                                objfield.defaultValue = specTrim(objfield.defaultValue);
                                sOld = specCharReplace(objfield.defaultValue);
                                sNew = objfield.value;
                            }

                            if ((sNew !== sOld) || bShowAll) {
                                changeAdd(form, objfield, sOld, sNew);
                                blnRetval = true;
                                if (bQuick) return true;
                            }
                        }
                    } else if (objfield.type === 'select-one' || objfield.type === 'select' ||
                               objfield.type === 'select-multiple') {
                        let sNewSel = '';
                        let sOldSel = '';

                        if (objfield.name.substr(0, 6).toLowerCase() === 'srtlst') {
                            sOldSel = form[objfield.name + '_info_order'].value;
                            for (let opt = 0; opt < objfield.options.length; opt++) {
                                sNewSel = (sNewSel === '' ? '' : sNewSel + '<br>') + objfield.options[opt].text;
                            }
                        } else {
                            for (let opt = 0; opt < objfield.options.length; opt++) {
                                if (objfield.options[opt].defaultSelected) {
                                    sOldSel = (sOldSel === '' ? '' : sOldSel + ';') + objfield.options[opt].text;
                                }
                                if (objfield.options[opt].selected) {
                                    sNewSel = (sNewSel === '' ? '' : sNewSel + ';') + objfield.options[opt].text;
                                }
                            }
                        }

                        if ((sOldSel !== sNewSel) || bShowAll) {
                            changeAdd(form, objfield, sOldSel, sNewSel);
                            blnRetval = true;
                        }
                    } else if (objfield.type === 'checkbox' || objfield.type === 'radio') {
                        let bDefaultValue;
                        const sAttrValue = objfield.getAttribute('data-default');
                        if (sAttrValue !== '' && sAttrValue !== null) {
                            bDefaultValue = sAttrValue === 'checked';
                        } else {
                            bDefaultValue = objfield.defaultChecked;
                        }

                        if ((objfield.checked !== bDefaultValue) || bShowAll) {
                            changeAdd(form, objfield, bDefaultValue ? 'Aan' : 'Uit', objfield.checked ? 'Aan' : 'Uit');
                            blnRetval = true;
                        }
                    }
                }
                changeClose();
            } catch (e) {
                cmaLog.error('[Form] Error validating form:', e.message, { stack: e.stack });
            }

            return blnRetval;
        }

        function checkIfDirty(form) {
            if (!dirtySet) {
                if (isDirty(form, true)) {
                    dirtySet = true;
                    jQuery('#toolbar_save,#toolbar_saveclose').addClass('dirty');
                } else {
                    window.setTimeout(function() {
                        checkIfDirty(form);
                    }, 1000);
                }
            }
        }

        function changeLogDelete(form) {
            try {
                if (changeInit(form)) {
                    changeClear();
                    for (let tel = 0; tel < form.length; tel++) {
                        const objfield = form[tel];
                        if (objfield.name !== '_changelog') {
                            if (objfield.type === 'text' || objfield.type === 'file' ||
                                objfield.type === 'password' || objfield.type === 'textarea') {
                                changeAdd(form, objfield, objfield.defaultValue, objfield.defaultValue);
                            } else if (objfield.type === 'checkbox' || objfield.type === 'radio') {
                                if (objfield.defaultChecked) {
                                    changeAdd(form, objfield,
                                        objfield.defaultChecked ? 'Aan' : 'Uit',
                                        objfield.defaultChecked ? 'Aan' : 'Uit');
                                }
                            } else if (objfield.type === 'select-one') {
                                let sOldSel = '';
                                if (objfield.name.substr(0, 6).toLowerCase() === 'srtlst') {
                                    sOldSel = form[objfield.name + '_info_order'].value;
                                } else {
                                    for (let opt = 0; opt < objfield.options.length; opt++) {
                                        if (objfield.options[opt].defaultSelected) {
                                            sOldSel = (sOldSel === '' ? '' : sOldSel + ';') + objfield.options[opt].text;
                                        }
                                    }
                                }
                                changeAdd(form, objfield, sOldSel, sOldSel);
                            }
                        }
                    }
                    changeClose();
                }
            } catch (e) {
                cmaLog.warn('[ChangeLog] Error in changeLogDelete:', e.message);
            }
        }

        function setDirty() {
            if (!dirtySet) {
                dirtySet = true;
                jQuery('#toolbar_save,#toolbar_saveclose').addClass('dirty');
            }
        }

        return {
            isDirty: isDirty,
            setDirty: setDirty,
            checkIfDirty: checkIfDirty,
            changeLogDelete: changeLogDelete,
            changeInit: changeInit,
            changeClear: changeClear,
            changeAdd: changeAdd,
            changeClose: changeClose,
            specTrim: specTrim,
            specCharReplace: specCharReplace
        };
    })();

    // ========================================================================
    // Toolbar Module - Toolbar button handlers
    // ========================================================================
    CMA.toolbar = (function() {
        function highlight(item, blnOn) {
            item.style.borderColor = blnOn ? '#f78d1d' : 'transparent';
        }

        function askDelete(evt) {
            if (evt) evt.preventDefault();

            let actionElt = document.getElementById('actie');
            if (!actionElt) actionElt = lib_form_findfield('actie');
            const frm = document.forms.main;

            if (typeof libConfirm === 'function') {
                libConfirm('Weet je zeker dat je dit record wilt verwijderen?', {
                    title: 'Verwijderen',
                    confirmText: 'Ja, verwijderen',
                    cancelText: 'Nee, annuleren',
                    type: 'warning'
                }).then(function(confirmed) {
                    if (confirmed) {
                        if (actionElt) actionElt.value = 'delete';
                        CMA.form.changeLogDelete(frm);
                        frm.submit();
                    }
                });
            } else {
                libConfirm('Weet je zeker dat je dit record wilt verwijderen?').then(function(confirmed) {
                    if (confirmed) {
                        if (actionElt) actionElt.value = 'delete';
                        CMA.form.changeLogDelete(frm);
                        frm.submit();
                    }
                });
            }
        }

        function doSave(bClose) {
            const frm = document.forms.main;
            if (!frm) return;

            try {
                if (typeof blockedit_collect_htmls === 'function') {
                    blockedit_collect_htmls();
                }
            } catch (e) {
                cmaLog.error('[doSave] Error collecting blockedit HTML:', e.message);
            }

            try {
                for (const instance in CKEDITOR.instances) {
                    CKEDITOR.instances[instance].updateElement();
                }
            } catch (e) {
                cmaLog.error('[doSave] Error updating CKEditor instances:', e.message);
            }

            let actionElt = document.getElementById('actie');
            if (!actionElt) actionElt = lib_form_findfield('actie');

            if (actionElt) {
                actionElt.value = 'save';
                if (frm.elements['action_close']) {
                    frm.elements['action_close'].value = bClose === true ? 'Y' : '';
                }

                if (CMA.form.isDirty(frm)) {
                    if (typeof form_custom_validatie === 'function') {
                        if (!form_custom_validatie()) return;
                    }
                    if (form_valid(frm, 'forgotten', 'Bewaar')) {
                        jQuery('#toolbar_save' + (bClose ? 'close' : '')).closest('table').addClass('tb_but_down');
                        frm.submit();
                    }
                } else {
                    jQuery('#toolbar_save').removeClass('dirty');
                    if (bClose === true) {
                        if (top.lib_OpenWindowCenteredClose) {
                            top.lib_OpenWindowCenteredClose();
                        } else if (window.parent.lib_OpenWindowCenteredClose) {
                            window.parent.lib_OpenWindowCenteredClose();
                        }
                    }
                }
            }
        }

        function scroll() {
            const tb = document.getElementById('_tb');
            if (tb) tb.style.posTop = document.getElementsByTagName('body')[0].scrollTop;
        }

        return {
            highlight: highlight,
            askDelete: askDelete,
            doSave: doSave,
            scroll: scroll
        };
    })();

    // ========================================================================
    // Menu Module - Navigation menu handling
    // ========================================================================
    CMA.menu = (function() {
        // Menu arrays - populated by menurep.php
        let c = [];
        let l = typeof window.l !== 'undefined' ? window.l : [];
        let n = typeof window.n !== 'undefined' ? window.n : [];
        let f = typeof window.f !== 'undefined' ? window.f : [];
        let activeTab = -1;
        let oldSubmenuSel = null;

        function initContents() {
            for (let i = 0; i < l.length; i++) {
                c[i] = '';
                for (let j = 0; j < l[i].length; j++) {
                    if (l[i][j] != null && l[i][j].length > 0) {
                        c[i] += '<a href=' + l[i][j] + '><span onclick=CMA.menu.submenuSel(this)>' + n[i][j] + '</span></a>';
                    } else {
                        c[i] += n[i][j];
                    }
                }
            }
        }

        function setNavItem(id, value) {
            const sID = 't' + id.toString();
            const el = document.getElementById(sID);
            if (el) el.className = value;
        }

        function changeNavMenu(id) {
            if (activeTab !== -1) {
                setNavItem(activeTab, '');
            }
            activeTab = id;
            setNavItem(activeTab, 'selected');
            document.getElementById('submenu').innerHTML = c[id];
            submenuSel(document.getElementsByTagName('span')[0]);
            eval(f[id][0]);
        }

        function init() {
            initContents();
            setNavItem(0, 'selected');
            activeTab = 0;
            document.getElementById('submenu').innerHTML = c[0];
            submenuSel(document.getElementsByTagName('span')[0]);
        }

        function submenuSel(elt) {
            if (oldSubmenuSel) {
                oldSubmenuSel.className = '';
                oldSubmenuSel = null;
            }
            if (elt) {
                elt.className = 'selected';
                oldSubmenuSel = elt;
            }
        }

        function gotoForm(ID) {
            const sID = ID.toString();
            const frame = top.window.frames['C'];
            if (frame) {
                frame.location = 'form.php?FormID=' + sID;
            } else {
                document.location = 'form.php?FormID=' + sID;
            }
        }

        return {
            init: init,
            initContents: initContents,
            setNavItem: setNavItem,
            changeNavMenu: changeNavMenu,
            submenuSel: submenuSel,
            gotoForm: gotoForm,
            setArrays: function(lArr, nArr, fArr) {
                l = lArr;
                n = nArr;
                f = fArr;
            }
        };
    })();

    // ========================================================================
    // Search Module - Search as you type
    // ========================================================================
    CMA.search = (function() {
        let activated = false;
        let listLength = 0;

        function searchAsYouType() {
            if (typeof jQuery === 'undefined') {
                cmaLog.warn('searchasyoutype: jQuery not available');
                return;
            }

            const searchValue = jQuery('#searchfor').val().toLowerCase();
            const allElements = jQuery('.complextree a, #simpletree a');

            if (!listLength) {
                listLength = allElements.length;
            }

            const minLetters = listLength > 2000 ? 3 : (listLength > 1000 ? 2 : 1);

            if (searchValue.length > minLetters || activated) {
                activated = true;
                let nActiveItems = 0;
                let nLastItem = null;

                allElements.filter(function() {
                    const bActive = jQuery(this).text().toLowerCase().indexOf(searchValue) > -1;
                    jQuery(this).toggle(bActive);
                    if (bActive && nActiveItems < 2) {
                        if (jQuery(this)[0].href || jQuery(this).find('a')[0].href) {
                            nActiveItems++;
                            if (nActiveItems === 1) {
                                nLastItem = jQuery(this)[0].href ? jQuery(this)[0] : jQuery(this).find('a')[0];
                            }
                        }
                    }
                });

                if (jQuery('.complextree li.f_closed:visible, #simpletree a:visible').length === 1) {
                    jQuery('.complextree li:visible a, #simpletree a').click();
                }

                if (nActiveItems === 1) {
                    nLastItem.click();
                }
            }
        }

        return {
            searchAsYouType: searchAsYouType
        };
    })();

    // ========================================================================
    // Utility functions
    // ========================================================================
    CMA.util = (function() {
        function replaceString(strWhat, strWith, strOrig) {
            while (strOrig.search(strWhat) !== -1) {
                strOrig = strOrig.replace(strWhat, strWith);
            }
            return strOrig;
        }

        function getCookie(name) {
            const dc = document.cookie;
            const prefix = name + '=';
            let begin = dc.indexOf('; ' + prefix);
            if (begin === -1) {
                begin = dc.indexOf(prefix);
                if (begin !== 0) return null;
            } else {
                begin += 2;
            }
            let end = document.cookie.indexOf(';', begin);
            if (end === -1) end = dc.length;
            return unescape(dc.substring(begin + prefix.length, end));
        }

        function setCookie(name, value) {
            const expires = new Date();
            expires.setFullYear(expires.getFullYear() + 1);
            document.cookie = name + '=' + escape(value) + ';expires=' + expires.toGMTString() + ';path=/';
        }

        return {
            replaceString: replaceString,
            getCookie: getCookie,
            setCookie: setCookie
        };
    })();

    // ========================================================================
    // Group folding (form sections)
    // ========================================================================
    CMA.groups = (function() {
        function set(id, displayValue) {
            // Hide/show numbered rows (_g{id}_{index})
            let element = document.getElementById('_g' + id.toString() + '_1');
            let index = 1;
            while (element) {
                element.style.display = displayValue;
                index++;
                element = document.getElementById('_g' + id.toString() + '_' + index.toString());
            }
            // Also hide/show rows with data-group-row attribute (including groupbox-end)
            const groupRows = document.querySelectorAll('[data-group-row="' + id.toString() + '"]');
            groupRows.forEach(function(row) {
                row.style.display = displayValue;
            });
            const chevron = document.getElementById('_chv' + id.toString());
            if (chevron) {
                chevron.className = displayValue === 'none' ? 'lnr lnr-chevron-right' : 'lnr lnr-chevron-down';
            }
        }

        function flip(id, formId) {
            const cookieName = 'frm_' + formId.toString() + '_' + id.toString();
            const cookieVal = CMA.util.getCookie(cookieName) === 'none' ? '' : 'none';
            set(id, cookieVal);
            CMA.util.setCookie(cookieName, cookieVal);
        }

        function init(formId) {
            let id = 1;
            let elt = document.getElementById('_g' + id.toString() + '_1');
            while (elt) {
                const cookieName = 'frm_' + formId.toString() + '_' + id.toString();
                if (CMA.util.getCookie(cookieName) === 'none') {
                    set(id, 'none');
                }
                id++;
                elt = document.getElementById('_g' + id.toString() + '_1');
            }
        }

        return {
            set: set,
            flip: flip,
            init: init
        };
    })();

    // ========================================================================
    // Listbox sorting
    // ========================================================================
    CMA.listbox = (function() {
        function sortUp(listbox) {
            if (listbox.selectedIndex !== -1 && listbox.selectedIndex !== 0) {
                swapItem(listbox, listbox.selectedIndex, listbox.selectedIndex - 1);
                listbox.selectedIndex--;
            }
        }

        function sortDown(listbox) {
            if (listbox.selectedIndex !== -1 && listbox.selectedIndex !== listbox.options.length - 1) {
                swapItem(listbox, listbox.selectedIndex, listbox.selectedIndex + 1);
                listbox.selectedIndex++;
            }
        }

        function swapItem(listbox, item1, item2) {
            const txt = listbox.options[item2].text;
            const val = listbox.options[item2].value;
            listbox.options[item2].value = listbox.options[item1].value;
            listbox.options[item2].text = listbox.options[item1].text;
            listbox.options[item1].value = val;
            listbox.options[item1].text = txt;
            fillInfo(listbox);
        }

        function sort(listbox, isDesc) {
            const listlen = listbox.length;
            if (listlen < 1) return;

            for (let x = listlen - 1; x > 0; x--) {
                for (let y = 0; y < x; y++) {
                    try {
                        let swap = false;
                        if (isDesc) {
                            if (listbox.options[y].text < listbox.options[y + 1].text) swap = true;
                        } else {
                            if (listbox.options[y].text > listbox.options[y + 1].text) swap = true;
                        }
                        if (swap) {
                            swapItem(listbox, y, y + 1);
                        }
                    } catch (e) {
                        cmaLog.warn('[Sortlist] Error during sort at position', y, ':', e.message);
                    }
                }
            }
        }

        function keyHandler(evt) {
            if (!evt) evt = window.event;
            const ctrl = evt ? evt.ctrlKey : false;

            try {
                if (evt) {
                    const elt = evt.srcElement ? evt.srcElement : evt.currentTarget;
                    if (ctrl) {
                        if (evt.keyCode === 40) {
                            sortDown(elt);
                            return false;
                        }
                        if (evt.keyCode === 38) {
                            sortUp(elt);
                            return false;
                        }
                    }
                }
            } catch (e) {
                cmaLog.warn('[Sortlist] Error in keyHandler:', e.message);
            }
        }

        function fillInfo(listbox) {
            let info = '';
            for (let i = 0; i < listbox.options.length; i++) {
                info = info + (info === '' ? '' : ',') + listbox.options[i].value;
            }
            lib_form_findfield(listbox.name + '_info').value = info;
        }

        return {
            sortUp: sortUp,
            sortDown: sortDown,
            swapItem: swapItem,
            sort: sort,
            keyHandler: keyHandler,
            fillInfo: fillInfo
        };
    })();

    // ========================================================================
    // Image handling
    // ========================================================================
    CMA.image = (function() {
        function change(sControl, sPath) {
            const winWidth = 600;
            const winHeight = 380;
            const url = '/filebrowser/browser.asp?Type=images&Connector=connectors/asp/connector.asp&uploadpath=' + sPath + '&control=' + sControl;
            lib_OpenWindowCentered(url, 'filebrowser', winWidth, winHeight, 'Selecteer afbeelding');
        }

        function changeWizard(sControl, sPath, bActiveXControl) {
            const url = 'imageupload_crop.php?' + (bActiveXControl === true ? 'ActiveX=Y&' : '') + 'path=' + sPath + '&control=' + sControl;
            _openImageDialog(url, 'Upload afbeelding');
        }

        function clear(sControl, sSrc) {
            const elt = document.getElementById(sControl + '_display');
            if (elt) {
                elt.src = sSrc;
                elt.alt = '';
            }
            const fld = window.document.main[sControl];
            if (fld) fld.value = '';
            const fldW = window.document.main[sControl + '_width'];
            if (fldW) fldW.value = '';
            const fldH = window.document.main[sControl + '_height'];
            if (fldH) fldH.value = '';
        }

        function view(sControl, sSrc) {
            let imageURL = window.document.main[sControl].value;
            if (imageURL !== '') {
                imageURL = sSrc + imageURL;
                window.open(imageURL, 'view', 'toolbar=0,location=0,status=0,menubar=0,scrollbars=yes,resizable=yes,width=1000,height=700');
            }
        }

        function set(sControl, sPath, filename, width, height) {
            const elt = document.getElementById(sControl + '_display');
            if (elt) {
                // For WebP files, wrap in <picture> with JPG fallback
                if (filename.match(/\.webp$/i)) {
                    var jpgFallback = filename.replace(/\.webp$/i, '.jpg');
                    var picture = document.createElement('picture');
                    var source = document.createElement('source');
                    source.srcset = sPath + filename;
                    source.type = 'image/webp';
                    var img = document.createElement('img');
                    img.id = sControl + '_display';
                    img.src = sPath + jpgFallback;
                    img.alt = filename;
                    if (width) img.width = width;
                    if (height) img.height = height;
                    picture.appendChild(source);
                    picture.appendChild(img);
                    elt.parentNode.replaceChild(picture, elt);
                } else {
                    elt.src = sPath + filename;
                    elt.alt = filename;
                }
            }
            const fld = window.document.main[sControl];
            if (fld) fld.value = filename;
            const fldW = window.document.main[sControl + '_width'];
            if (fldW) fldW.value = width.toString();
            const fldH = window.document.main[sControl + '_height'];
            if (fldH) fldH.value = height.toString();
        }

        function preview(sControl, event) {
            const input = event.target;
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const elt = document.getElementById(sControl + '_display');
                    if (elt) {
                        elt.src = e.target.result;
                        elt.alt = input.files[0].name;
                    }
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        function changeFile(sControl, sPath) {
            const winWidth = 600;
            const winHeight = 380;
            const url = '/filebrowser/browser.asp?Type=images&Connector=connectors/asp/connector.asp&uploadpath=' + sPath + '&control=' + sControl + '&type=file';
            lib_OpenWindowCentered(url, 'filebrowser', winWidth, winHeight, 'Selecteer bestand');
        }

        function clearFile(sControl) {
            window.document.main[sControl].value = '';
        }

        // Open image upload/crop in a fullscreen lib-dialog with iframe
        function _openImageDialog(url, title) {
            var dialogId = 'image-crop-dialog';
            var dialog = document.getElementById(dialogId);

            if (!dialog) {
                dialog = document.createElement('lib-dialog');
                dialog.id = dialogId;
                dialog.setAttribute('heading', title);
                dialog.setAttribute('size', 'fullscreen');
                dialog.setAttribute('modal', '');
                var iframe = document.createElement('iframe');
                iframe.id = 'image-crop-iframe';
                iframe.style.cssText = 'width: 100%; height: 100%; border: none; display: block;';
                dialog.appendChild(iframe);
                document.body.appendChild(dialog);
            } else {
                dialog.setAttribute('heading', title);
            }

            var iframe = document.getElementById('image-crop-iframe');
            iframe.src = url + (url.indexOf('?') !== -1 ? '&' : '?') + '_=' + Date.now();

            // Listen for completion via postMessage from the iframe
            var messageHandler = function(e) {
                if (e.origin !== window.location.origin) return;
                if (!e.data || e.data.type !== 'image-crop-complete') return;
                window.removeEventListener('message', messageHandler);
                dialog.close();
                // Handle the result
                if (e.data.control && e.data.filename) {
                    set(e.data.control, e.data.path, e.data.filename, e.data.width, e.data.height);
                }
            };
            window.addEventListener('message', messageHandler);

            dialog.open();
        }

        return {
            change: change,
            changeWizard: changeWizard,
            clear: clear,
            view: view,
            set: set,
            preview: preview,
            changeFile: changeFile,
            clearFile: clearFile
        };
    })();

    // ========================================================================
    // Miscellaneous functions
    // ========================================================================
    CMA.misc = (function() {
        function showSite(fld) {
            let targetUrl = '';
            if (typeof fld === 'string') {
                targetUrl = fld;
            } else {
                targetUrl = fld.value;
            }
            if (targetUrl !== '') {
                if (targetUrl.indexOf('http://') !== 0 && targetUrl.indexOf('https://') !== 0) {
                    targetUrl = 'http://' + targetUrl;
                }
                window.open(targetUrl, '_blank');
            }
        }

        function showPwd(sIcon, sInput) {
            const inputElt = document.getElementById(sInput);
            const iconElt = document.getElementById(sIcon);
            if (inputElt) {
                if (inputElt.type === 'password') {
                    inputElt.type = 'text';
                    if (iconElt) iconElt.className = 'lnr lnr-eye';
                } else {
                    inputElt.type = 'password';
                    if (iconElt) iconElt.className = 'lnr lnr-eye-crossed';
                }
            }
        }

        function checkChanged() {
            const mainFrm = document.forms.main;
            const e = document.getElementById('action');
            if (mainFrm && e) {
                if (e.value !== 'save' && e.value !== 'delete') {
                    return CMA.form.isDirty(mainFrm);
                }
            }
        }

        function clearAction() {
            let e = document.getElementById('actie');
            if (!e) e = lib_form_findfield('actie');
            if (e) e.value = '';
        }

        function refreshParent(bClose, sUrlList, sUrldetails, sID) {
            // Implementation for refreshing parent window
        }

        function sizeDetailsC(bHaveSubForms) {
            // Implementation for sizing details container
        }

        function openSubform(sForm, subID, sParent, sParentID, sTitle, bFullWidth) {
            // Implementation for opening subform
        }

        return {
            showSite: showSite,
            showPwd: showPwd,
            checkChanged: checkChanged,
            clearAction: clearAction,
            refreshParent: refreshParent,
            sizeDetailsC: sizeDetailsC,
            openSubform: openSubform
        };
    })();

    // ========================================================================
    // Service Worker cache control
    // ========================================================================
    /**
     * Clear the Service Worker form template cache
     * @returns {Promise<boolean>} - Resolves to true if cache was cleared, false if SW unavailable
     */
    CMA.clearFormCache = function() {
        if ('serviceWorker' in navigator && navigator.serviceWorker.controller) {
            return new Promise(function(resolve) {
                var channel = new MessageChannel();
                channel.port1.onmessage = function(e) {
                    if (typeof cmaLog !== 'undefined') {
                        // cmaLog.log('[SW] Form cache cleared:', e.data);
                    }
                    resolve(e.data.cleared || false);
                };
                navigator.serviceWorker.controller.postMessage('clearFormCache', [channel.port2]);
            });
        }
        return Promise.resolve(false);
    };

    /**
     * Get Service Worker cache info (for debugging)
     * @returns {Promise<object|null>} - Cache info or null if SW unavailable
     */
    CMA.getFormCacheInfo = function() {
        if ('serviceWorker' in navigator && navigator.serviceWorker.controller) {
            return new Promise(function(resolve) {
                var channel = new MessageChannel();
                channel.port1.onmessage = function(e) {
                    resolve(e.data);
                };
                navigator.serviceWorker.controller.postMessage('getCacheInfo', [channel.port2]);
            });
        }
        return Promise.resolve(null);
    };

    // ========================================================================
    // HTML stripping utilities
    // ========================================================================
    CMA.html = (function() {
        function stripTags(content) {
            const sOriginal = content;

            content = content.replace(/mso-[^:]*:"[^"]*";/gi, '');
            content = content.replace(/mso-[^;'"]*;*(\n|\r)*/gi, '');
            content = content.replace(/BACKGROUND-COLOR: transparent/gi, '');
            content = content.replace(/ class=MsoTableGrid/gi, '');
            content = content.replace(/ class=MsoNormal/gi, '');
            content = content.replace(/ dir=ltr/gi, '');
            content = content.replace(/ style=['"]tab-interval:[^'"]*['"]/gi, '');
            content = content.replace(/<V:[^>]*>/gi, '');
            content = content.replace(/<\/V:[^>]*>/gi, '');
            content = content.replace(/<W:[^>]*>/gi, '');
            content = content.replace(/<\/W:[^>]*>/gi, '');
            content = content.replace(/<METRICCONVERTER[^>]*>/gi, '');
            content = content.replace(/<\/METRICCONVERTER[^>]*>/gi, '');
            content = content.replace(/<st1:[^>]*>/gi, '');
            content = content.replace(/<O:[^>]*>/gi, '');
            content = content.replace(/<pre>/gi, '');
            content = content.replace(/<\/pre>/gi, '');

            if (navigator.appVersion.search('MSIE 5') === -1) {
                content = content.replace(/<\/st1:[^>]*>/gi, '');
                content = content.replace(/<\?xml:.*?\/>/gi, '');
                content = content.replace(/<\/O:[^>]*>/gi, '');
            }

            while (content.search('<BR>\r\n') !== -1) {
                content = content.replace(/<BR>\r\n/gi, '<BR>');
            }

            while (content.search('<BR><BR><BR>') !== -1) {
                content = content.replace(/<BR><BR><BR>/gi, '<BR><BR>');
            }

            content = content.replace(/<TD><BR>/gi, '<TD>');
            content = content.replace(/<BR><\/TD>/gi, '</TD>');
            content = content.replace(/<TD><P>/gi, '<TD>');
            content = content.replace(/<\/SPAN[^>]*><\/TD[^>]*>/gi, '</TD>');
            content = content.replace(/<\/P[^>]*><\/TD[^>]*>/gi, '</TD>');
            content = content.replace(/<SPAN[^>]*>/gi, '');
            content = content.replace(/<\/SPAN[^>]*>/gi, '');
            content = content.replace(/<SPAN>/gi, '');
            content = content.replace(/<\/SPAN>/gi, '');
            content = content.replace(/<TBODY[^>]*>/gi, '');
            content = content.replace(/<\/TBODY[^>]*>/gi, '');
            content = content.replace(/<THEAD[^>]*>/gi, '');
            content = content.replace(/<\/THEAD[^>]*>/gi, '');

            for (let i = 1; i <= 6; i++) {
                content = content.replace(new RegExp('<H' + i + '[^>]*>', 'gi'), '');
                content = content.replace(new RegExp('<\\/H' + i + '[^>]*>', 'gi'), '');
            }

            content = content.replace(/<![if !supportEmptyParas]>&nbsp;<![endif]>/gi, ' ');
            content = content.replace(/<FONT[^>]*>&nbsp;<\/FONT>/gi, '');
            content = content.replace(/<FONT[^>]*><\/FONT>/gi, '');
            content = content.replace(/<P>&nbsp;<\/P>/gi, '<BR>');
            content = content.replace(/<P><\/P>/gi, '<BR>');
            content = content.replace(/<P> <\/P>/gi, '<BR>');
            content = content.replace(/<B><\/B>/gi, '');
            content = content.replace(/<I><\/I>/gi, '');
            content = content.replace(/style=""/gi, '');
            content = content.replace(/<P>/gi, '<BR>');
            content = content.replace(/<\/P>/gi, '');
            content = content.replace(/<FONT>/gi, '');

            if (content.toLowerCase().indexOf('<font') === -1) {
                content = content.replace(/<\/FONT>/gi, '');
            }

            content = content.replace(/<STRONG>/gi, '<B>');
            content = content.replace(/<\/STRONG>/gi, '</B>');
            content = content.replace(/<EM>/gi, '<I>');
            content = content.replace(/<\/EM>/gi, '</I>');
            content = content.replace(/<\/LI>/gi, '');
            content = content.replace(/<\/UL><BR><BR>/gi, '</UL>');
            content = content.replace(/<\/OL><BR><BR>/gi, '</OL>');
            content = content.replace(/é/gi, '&#233;');
            content = content.replace(/ë/gi, '&#235;');

            while ((content.substr(0, 4)).toLowerCase() === '<br>') {
                content = content.substr(4);
            }
            while (content.substr(content.length - 4, 4).toLowerCase() === '<br>') {
                content = content.substr(0, content.length - 4);
            }
            while (content.substr(0, 6).toLowerCase() === '&nbsp;') {
                content = content.substr(6);
            }
            while (content.substr(content.length - 6, 6).toLowerCase() === '&nbsp;') {
                content = content.substr(0, content.length - 6);
            }

            return sOriginal !== content ? stripTags(content) : content;
        }

        return {
            stripTags: stripTags
        };
    })();

    // ========================================================================
    // CMA.emailLog - Email log management actions
    // ========================================================================
    CMA.emailLog = {
        resend: function(id) {
            if (!confirm('Wil je deze e-mail opnieuw verzenden?')) return;
            jQuery.post('/cma/api/email-actions.php', { action: 'resend', id: id }, function(resp) {
                if (resp.success) {
                    libToast.success('E-mail opnieuw verzonden');
                } else {
                    libToast.error('Fout bij opnieuw verzenden: ' + (resp.error || 'Onbekende fout'));
                }
            }, 'json').fail(function() {
                libToast.error('Kan de e-mail actie niet uitvoeren');
            });
        }
    };

})();

// ============================================================================
// BACKWARD COMPATIBILITY SHIMS
// These exist ONLY for legacy code that uses global function calls.
// New code should use CMA.* namespace directly.
// ============================================================================

// @deprecated Tree functions - legacy shims for inline gFld()/F()/D()/I() scripts.
// New code should use <cma-tree> web component with JSON data.
var Folder = CMA.tree.Folder;
var Item = CMA.tree.Item;
function gFld(desc) { return CMA.tree.gFld(desc); }
function gLnk(target, description, linkData) { return CMA.tree.gLnk(target, description, linkData); }
function insFld(parentFolder, childFolder) { return parentFolder.addChild(childFolder); }
function insDoc(parentFolder, doc) { return parentFolder.addChild(doc); }
function F(parentFolder, childFolder) { return CMA.tree.F(parentFolder, childFolder); }
function D(parentFolder, doc) { CMA.tree.D(parentFolder, doc); }
function I(target, description, linkData) { return CMA.tree.I(target, description, linkData); }
function fExpandAll() {
    // Try cma-tree web component first, fall back to legacy CMA.tree
    var tree = document.querySelector('#listContent cma-tree');
    if (tree && tree.expandAll) { tree.expandAll(); } else { CMA.tree.expandAll(); }
}
function fCollapseAll() {
    var tree = document.querySelector('#listContent cma-tree');
    if (tree && tree.collapseAll) { tree.collapseAll(); } else { CMA.tree.collapseAll(); }
}
function fSaveTree() { CMA.tree.saveTree(); }
function fRestoreTree(s) { CMA.tree.restoreTree(s); }
function initializeDocument(a, b, c) { CMA.tree.initializeDocument(a, b, c); }
function initializeToElement(a, b, c, d) { CMA.tree.initializeToElement(a, b, c, d); }
function clickOnFolder(id) { CMA.tree.clickOnFolder(id); }
function clickOnNode(id) { CMA.tree.clickOnNode(id); }
function hi(id) { CMA.tree.hi(id); }

// Editor functions
function SetFKEditorConfig(config) { CMA.editor.setConfig(config); }
function CreateFKEditor(a, b, c, d, e, f) { CMA.editor.create(a, b, c, d, e, f); }
function CreateSimpleFKEditor(a, b, c) { CMA.editor.createSimple(a, b, c); }
function my_InsertLink(editor) { CMA.editor.insertLink(editor); }
function my_InsertImage(editor) { CMA.editor.insertImage(editor); }
function my_InsertTable(editor) { CMA.editor.insertTable(editor); }
function my_table_properties(editor) { CMA.editor.tableProperties(editor); }
function my_image_properties(editor) { CMA.editor.imageProperties(editor); }
function my_anchor_properties(editor) { CMA.editor.anchorProperties(editor); }
function ToonLiteratuur_dialoog(editor) { CMA.editor.showLiteratuurDialog(editor); }
function my_isCursorInTable(editor) { return CMA.editor.isCursorInTable(editor); }
function my_isCursorInImage(editor) { return CMA.editor.isCursorInImage(editor); }
function my_isCursorInAnchor(editor) { return CMA.editor.isCursorInAnchor(editor); }

// Form functions
function form_dirty(form, bQuick) { return CMA.form.isDirty(form, bQuick); }
function check_if_form_dirty(form) { CMA.form.checkIfDirty(form); }
function form_change_log_delete(form) { CMA.form.changeLogDelete(form); }
function form_change_init(form) { return CMA.form.changeInit(form); }
function form_change_clear(form) { CMA.form.changeClear(); }
function form_change_add(form, a, b, c) { CMA.form.changeAdd(form, a, b, c); }
function form_change_close(form) { CMA.form.changeClose(); }

// Toolbar functions
function tbHi(item, blnOn) { CMA.toolbar.highlight(item, blnOn); }
function tb_AskDelete(evt) { CMA.toolbar.askDelete(evt); }
function tb_DoSave(bClose) { CMA.toolbar.doSave(bClose); }
function ToolbarScroll() { CMA.toolbar.scroll(); }

// Menu functions
function form(ID) { CMA.menu.gotoForm(ID); }
function initContents() { CMA.menu.initContents(); }
function setnavitem(id, value) { CMA.menu.setNavItem(id, value); }
function changeNavMenu(id) { CMA.menu.changeNavMenu(id); }
function menu_init() { CMA.menu.init(); }
function submenu_sel(elt) { CMA.menu.submenuSel(elt); }

// Search
function searchasyoutype() { CMA.search.searchAsYouType(); }

// Utility functions
function replaceString(a, b, c) { return CMA.util.replaceString(a, b, c); }
function my_replacestring(a, b, c) { return CMA.util.replaceString(a, b, c); }
function Get_Cookie(name) { return CMA.util.getCookie(name); }
function Set_Cookie(name, value) { CMA.util.setCookie(name, value); }
function spec_trim(s) { return CMA.form.specTrim(s); }
function spec_char_replace(s) { return CMA.form.specCharReplace(s); }

// Group folding
function grp_set(id, display) { CMA.groups.set(id, display); }
function grp_flip(id, formId) { CMA.groups.flip(id, formId); }
function grp_init(formId) { CMA.groups.init(formId); }

// Listbox
function lb_sortup(listbox) { CMA.listbox.sortUp(listbox); }
function lb_sortdown(listbox) { CMA.listbox.sortDown(listbox); }
function lb_swapitem(listbox, a, b) { CMA.listbox.swapItem(listbox, a, b); }
function lb_sort(listbox, isDesc) { CMA.listbox.sort(listbox, isDesc); }
function lb_key(evt) { return CMA.listbox.keyHandler(evt); }
function lb_fill_info(listbox) { CMA.listbox.fillInfo(listbox); }

// Image handling
function fChangeImage(a, b) { CMA.image.change(a, b); }
function fChangeImageWizard(a, b, c) { CMA.image.changeWizard(a, b, c); }
function fClearImage(a, b) { CMA.image.clear(a, b); }
function fViewFile(a, b) { CMA.image.view(a, b); }
function fSetImage(a, b, c, d, e) { CMA.image.set(a, b, c, d, e); }
function fPreviewImage(a, b) { CMA.image.preview(a, b); }
function fChangeFile(a, b) { CMA.image.changeFile(a, b); }
function fClearFile(a) { CMA.image.clearFile(a); }

// Misc
function fShowSite(fld) { CMA.misc.showSite(fld); }
function ShowPwd(a, b) { CMA.misc.showPwd(a, b); }
function checkchanged() { return CMA.misc.checkChanged(); }
function clearaction() { CMA.misc.clearAction(); }
function form_refresh_parent(a, b, c, d) { CMA.misc.refreshParent(a, b, c, d); }
function size_details_c(a) { CMA.misc.sizeDetailsC(a); }
function w(a, b, c, d, e, f) { CMA.misc.openSubform(a, b, c, d, e, f); }

// HTML
function lib_strip_HTMLTags(content) { return CMA.html.stripTags(content); }

// Empty functions for compatibility
function list_init() {}
