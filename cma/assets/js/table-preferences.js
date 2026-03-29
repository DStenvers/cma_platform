/**
 * CMA Table Preferences Manager
 * Handles localStorage persistence for table settings:
 * - Column widths
 * - Column order
 * - Column visibility
 * - Selected columns in field chooser
 */
class CmaTablePreferences {
    constructor(formId) {
        this.formId = formId;
        this.storageKey = `cma_v2_table_prefs_${formId}`;
        this.preferences = this.load();
    }

    /**
     * Load preferences from localStorage
     * @returns {Object} Preferences object
     */
    load() {
        const defaults = {
            columnWidths: {},
            columnOrder: [],
            hiddenColumns: [],
            version: 1
        };
        try {
            const stored = localStorage.getItem(this.storageKey);
            if (stored) {
                const parsed = JSON.parse(stored);
                // Merge with defaults to ensure all properties exist
                return {
                    ...defaults,
                    ...parsed,
                    // Ensure nested objects exist
                    columnWidths: parsed.columnWidths || {},
                    columnOrder: parsed.columnOrder || [],
                    hiddenColumns: parsed.hiddenColumns || []
                };
            }
        } catch (e) {
            cmaLog.warn('[TablePreferences] Failed to load preferences:', e.message);
        }
        return defaults;
    }

    /**
     * Save preferences to localStorage
     */
    save() {
        try {
            localStorage.setItem(this.storageKey, JSON.stringify(this.preferences));
        } catch (e) {
            cmaLog.warn('[TablePreferences] Failed to save preferences:', e.message);
        }
    }

    /**
     * Get column width
     * @param {string} fieldName Column field name
     * @returns {number|null} Width in pixels or null if not set
     */
    getColumnWidth(fieldName) {
        return this.preferences.columnWidths[fieldName] || null;
    }

    /**
     * Set column width
     * @param {string} fieldName Column field name
     * @param {number} width Width in pixels
     */
    setColumnWidth(fieldName, width) {
        this.preferences.columnWidths[fieldName] = Math.round(width);
        this.save();
    }

    /**
     * Get column order
     * @returns {Array} Array of field names in order
     */
    getColumnOrder() {
        return this.preferences.columnOrder || [];
    }

    /**
     * Set column order
     * @param {Array} order Array of field names in order
     */
    setColumnOrder(order) {
        this.preferences.columnOrder = order;
        this.save();
    }

    /**
     * Get hidden columns
     * @returns {Array} Array of hidden field names
     */
    getHiddenColumns() {
        return this.preferences.hiddenColumns || [];
    }

    /**
     * Set hidden columns
     * @param {Array} hidden Array of hidden field names
     */
    setHiddenColumns(hidden) {
        this.preferences.hiddenColumns = hidden;
        this.save();
    }

    /**
     * Check if a column is hidden
     * @param {string} fieldName Column field name
     * @returns {boolean}
     */
    isColumnHidden(fieldName) {
        return (this.preferences.hiddenColumns || []).includes(fieldName);
    }

    /**
     * Toggle column visibility
     * @param {string} fieldName Column field name
     * @param {boolean} visible Whether column should be visible
     */
    setColumnVisible(fieldName, visible) {
        const hidden = this.preferences.hiddenColumns || [];
        const index = hidden.indexOf(fieldName);

        if (visible && index !== -1) {
            hidden.splice(index, 1);
        } else if (!visible && index === -1) {
            hidden.push(fieldName);
        }

        this.preferences.hiddenColumns = hidden;
        this.save();
    }

    /**
     * Apply hidden columns to a table
     * Column visibility is controlled server-side; this is a no-op.
     * @param {HTMLTableElement} table The table element
     */
    applyHiddenColumns(table) {
        // No-op: column visibility is managed server-side via column preferences
    }

    /**
     * Clear all preferences for this form
     */
    clear() {
        this.preferences = {
            columnWidths: {},
            columnOrder: [],
            hiddenColumns: [],
            version: 1
        };
        localStorage.removeItem(this.storageKey);
    }

    /**
     * Apply stored column widths to a table
     * @param {HTMLTableElement} table The table element
     */
    applyColumnWidths(table) {
        if (!table) return;

        const headers = table.querySelectorAll('thead th[data-field]');
        headers.forEach(th => {
            const fieldName = th.dataset.field;
            const width = this.getColumnWidth(fieldName);
            if (width) {
                th.style.width = width + 'px';
                th.style.minWidth = width + 'px';
            }
        });
    }

    /**
     * Apply stored column order to a table
     * @param {HTMLTableElement} table The table element
     * @returns {boolean} True if order was applied
     */
    applyColumnOrder(table) {
        const order = this.getColumnOrder();
        if (!order || order.length === 0) return false;

        const thead = table.querySelector('thead tr');
        const tbody = table.querySelector('tbody');
        if (!thead || !tbody) return false;

        // Get current headers and create a map
        const headers = Array.from(thead.querySelectorAll('th[data-field]'));
        const headerMap = {};
        headers.forEach(th => {
            headerMap[th.dataset.field] = th;
        });

        // Find first header without data-field (like export menu column)
        const firstTh = thead.querySelector('th:not([data-field])');

        // Reorder headers based on stored order
        const orderedHeaders = [];
        order.forEach(fieldName => {
            if (headerMap[fieldName]) {
                orderedHeaders.push(headerMap[fieldName]);
                delete headerMap[fieldName];
            }
        });

        // Add any remaining headers (new columns not in stored order)
        Object.values(headerMap).forEach(th => orderedHeaders.push(th));

        // Clear and rebuild header row
        while (thead.firstChild) {
            thead.removeChild(thead.firstChild);
        }
        if (firstTh) {
            thead.appendChild(firstTh);
        }
        orderedHeaders.forEach(th => thead.appendChild(th));

        // Reorder body cells to match
        const rows = tbody.querySelectorAll('tr');
        rows.forEach(row => {
            const cells = Array.from(row.querySelectorAll('td[data-field]'));
            const cellMap = {};
            cells.forEach(td => {
                cellMap[td.dataset.field] = td;
            });

            // Find first cell without data-field
            const firstTd = row.querySelector('td:not([data-field])');

            // Reorder cells
            const orderedCells = [];
            order.forEach(fieldName => {
                if (cellMap[fieldName]) {
                    orderedCells.push(cellMap[fieldName]);
                    delete cellMap[fieldName];
                }
            });
            Object.values(cellMap).forEach(td => orderedCells.push(td));

            // Clear and rebuild row
            while (row.firstChild) {
                row.removeChild(row.firstChild);
            }
            if (firstTd) {
                row.appendChild(firstTd);
            }
            orderedCells.forEach(td => row.appendChild(td));
        });

        return true;
    }
}

/**
 * CMA Table Column Manager
 * Handles column resizing, reordering, and field chooser
 */
class CmaTableColumnManager {
    constructor(table, formId, options = {}) {
        this.table = table;
        this.formId = formId;
        this.preferences = new CmaTablePreferences(formId);
        this.options = options;

        // State
        this.isResizing = false;
        this.isDragging = false;
        this.resizeColumn = null;
        this.dragColumn = null;
        this.startX = 0;
        this.startWidth = 0;

        // Bind methods
        this.onMouseMove = this.onMouseMove.bind(this);
        this.onMouseUp = this.onMouseUp.bind(this);
        this.onDragOver = this.onDragOver.bind(this);
        this.onDrop = this.onDrop.bind(this);

        this.init();
    }

    init() {
        if (!this.table) return;

        // Apply stored preferences
        this.preferences.applyColumnWidths(this.table);
        this.preferences.applyColumnOrder(this.table);
        this.preferences.applyHiddenColumns(this.table);

        // Initialize resize handles
        this.initResizeHandles();

        // Initialize drag-to-reorder
        this.initDragReorder();
    }

    /**
     * Initialize column resize handles
     */
    initResizeHandles() {
        const headers = this.table.querySelectorAll('thead th[data-field]');

        headers.forEach(th => {
            // Create resize handle
            const handle = document.createElement('div');
            handle.className = 'column-resize-handle';
            handle.addEventListener('mousedown', (e) => this.startResize(e, th));
            th.appendChild(handle);
            th.style.position = 'relative';
        });
    }

    /**
     * Start column resize
     */
    startResize(e, th) {
        e.preventDefault();
        e.stopPropagation();

        this.isResizing = true;
        this.resizeColumn = th;
        this.startX = e.pageX;
        this.startWidth = th.offsetWidth;

        // Add resize cursor to body
        document.body.style.cursor = 'col-resize';
        document.body.classList.add('resizing-column');

        // Add event listeners
        document.addEventListener('mousemove', this.onMouseMove);
        document.addEventListener('mouseup', this.onMouseUp);
    }

    /**
     * Handle mouse move during resize
     * PERFORMANCE FIX: Use CSS variable instead of updating every cell
     */
    onMouseMove(e) {
        if (this.isResizing && this.resizeColumn) {
            const diff = e.pageX - this.startX;
            const newWidth = Math.max(50, this.startWidth + diff); // Minimum 50px

            // PERFORMANCE FIX: Update only the header and use CSS colgroup for body cells
            // This avoids querying all rows on every mouse move event
            this.resizeColumn.style.width = newWidth + 'px';
            this.resizeColumn.style.minWidth = newWidth + 'px';

            // Use colgroup if available, otherwise update CSS variable
            const colIndex = Array.from(this.resizeColumn.parentNode.children).indexOf(this.resizeColumn);

            // Find or create colgroup for efficient column width management
            let colgroup = this.table.querySelector('colgroup');
            if (!colgroup) {
                colgroup = document.createElement('colgroup');
                const headerCount = this.table.querySelectorAll('thead th').length;
                for (let i = 0; i < headerCount; i++) {
                    colgroup.appendChild(document.createElement('col'));
                }
                this.table.insertBefore(colgroup, this.table.firstChild);
            }

            const col = colgroup.children[colIndex];
            if (col) {
                col.style.width = newWidth + 'px';
            }
        }
    }

    /**
     * Handle mouse up - end resize
     */
    onMouseUp(e) {
        if (this.isResizing && this.resizeColumn) {
            const fieldName = this.resizeColumn.dataset.field;
            const newWidth = this.resizeColumn.offsetWidth;

            // Save to preferences
            this.preferences.setColumnWidth(fieldName, newWidth);

            this.isResizing = false;
            this.resizeColumn = null;
        }

        // Reset cursor
        document.body.style.cursor = '';
        document.body.classList.remove('resizing-column');

        // Remove event listeners
        document.removeEventListener('mousemove', this.onMouseMove);
        document.removeEventListener('mouseup', this.onMouseUp);
    }

    /**
     * Initialize drag-to-reorder for column headers
     */
    initDragReorder() {
        const headers = this.table.querySelectorAll('thead th[data-field]');

        headers.forEach(th => {
            th.draggable = true;
            th.addEventListener('dragstart', (e) => this.onDragStart(e, th));
            th.addEventListener('dragend', (e) => this.onDragEnd(e, th));
            th.addEventListener('dragover', this.onDragOver);
            th.addEventListener('drop', this.onDrop);
            th.addEventListener('dragenter', (e) => this.onDragEnter(e, th));
            th.addEventListener('dragleave', (e) => this.onDragLeave(e, th));
        });
    }

    /**
     * Handle drag start
     */
    onDragStart(e, th) {
        // Don't start drag if resizing
        if (this.isResizing) {
            e.preventDefault();
            return;
        }

        this.isDragging = true;
        this.dragColumn = th;

        th.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', th.dataset.field);

        // Create drag image
        const dragImage = th.cloneNode(true);
        dragImage.style.opacity = '0.7';
        dragImage.style.position = 'absolute';
        dragImage.style.top = '-1000px';
        document.body.appendChild(dragImage);
        e.dataTransfer.setDragImage(dragImage, 20, 20);
        setTimeout(() => dragImage.remove(), 0);
    }

    /**
     * Handle drag end
     */
    onDragEnd(e, th) {
        this.isDragging = false;
        this.dragColumn = null;
        th.classList.remove('dragging');

        // Remove all drag-over classes
        this.table.querySelectorAll('.drag-over').forEach(el => {
            el.classList.remove('drag-over');
        });
    }

    /**
     * Handle drag over
     */
    onDragOver(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
    }

    /**
     * Handle drag enter
     */
    onDragEnter(e, th) {
        if (th !== this.dragColumn && th.dataset.field) {
            th.classList.add('drag-over');
        }
    }

    /**
     * Handle drag leave
     */
    onDragLeave(e, th) {
        th.classList.remove('drag-over');
    }

    /**
     * Handle drop - reorder columns
     */
    onDrop(e) {
        e.preventDefault();

        const targetTh = e.target.closest('th[data-field]');
        if (!targetTh || !this.dragColumn || targetTh === this.dragColumn) {
            return;
        }

        targetTh.classList.remove('drag-over');

        const thead = this.table.querySelector('thead tr');
        const tbody = this.table.querySelector('tbody');

        // Get positions
        const headers = Array.from(thead.querySelectorAll('th'));
        const dragIndex = headers.indexOf(this.dragColumn);
        const targetIndex = headers.indexOf(targetTh);

        if (dragIndex === -1 || targetIndex === -1) return;

        // Reorder header
        if (dragIndex < targetIndex) {
            thead.insertBefore(this.dragColumn, targetTh.nextSibling);
        } else {
            thead.insertBefore(this.dragColumn, targetTh);
        }

        // Reorder all body rows
        tbody.querySelectorAll('tr').forEach(row => {
            const cells = Array.from(row.children);
            const dragCell = cells[dragIndex];
            const targetCell = cells[targetIndex];

            if (dragCell && targetCell) {
                if (dragIndex < targetIndex) {
                    row.insertBefore(dragCell, targetCell.nextSibling);
                } else {
                    row.insertBefore(dragCell, targetCell);
                }
            }
        });

        // Save new order to preferences
        this.saveColumnOrder();
    }

    /**
     * Save current column order to preferences
     */
    saveColumnOrder() {
        const headers = this.table.querySelectorAll('thead th[data-field]');
        const order = Array.from(headers).map(th => th.dataset.field);
        this.preferences.setColumnOrder(order);
    }

    /**
     * Get current column order
     * @returns {Array} Array of field names in current order
     */
    getCurrentOrder() {
        const headers = this.table.querySelectorAll('thead th[data-field]');
        return Array.from(headers).map(th => th.dataset.field);
    }

    /**
     * Reset all preferences to default
     */
    resetPreferences() {
        this.preferences.clear();
        // Trigger table reload to apply defaults
        if (this.options.onReset) {
            this.options.onReset();
        }
    }

    /**
     * Destroy the column manager
     */
    destroy() {
        document.removeEventListener('mousemove', this.onMouseMove);
        document.removeEventListener('mouseup', this.onMouseUp);

        // Remove resize handles
        this.table.querySelectorAll('.column-resize-handle').forEach(h => h.remove());
    }
}

/**
 * CMA Infinite Scroll Manager
 * Handles lazy loading of table rows as user scrolls
 */
class CmaInfiniteScroll {
    constructor(options) {
        this.container = options.container;
        this.table = options.table;
        this.loadMore = options.loadMore; // Callback function to load more data
        this.threshold = options.threshold || 200; // Pixels from bottom to trigger load
        this.formId = options.formId;

        // State
        this.isLoading = false;
        this.hasMore = true;
        this.lastId = null;
        this.pageSize = options.pageSize || 500;
        this.scrollDebounceTimer = null; // Debounce timer for scroll events
        this.pendingLastId = null; // Track which lastId is being loaded to prevent duplicates
        this.destroyed = false; // Flag to prevent stale async loads from completing

        // Record count tracking
        this.currentCount = 0; // Current number of loaded records
        this.totalCount = null; // Total records in dataset (from initial load)

        // PERFORMANCE FIX: DOM pruning to prevent unbounded growth
        this.maxRowsInDom = options.maxRowsInDom || 500; // Keep max 500 rows in DOM
        this.pruneThreshold = options.pruneThreshold || 100; // Prune when 100 rows above viewport
        this.rowHeight = options.rowHeight || 35; // Estimated row height for placeholder
        this.prunedTopCount = 0; // Track how many rows have been pruned from top
        this.topPlaceholder = null; // Placeholder element for pruned rows

        // Bind methods
        this.onScroll = this.onScroll.bind(this);
        this.pruneExcessRows = this.pruneExcessRows.bind(this);

        this.init();
    }

    init() {
        if (!this.container) return;

        // Listen to scroll on the container
        this.container.addEventListener('scroll', this.onScroll);

        // Create loading indicator using lib-loader component
        this.loadingIndicator = document.createElement('lib-loader');
        this.loadingIndicator.setAttribute('delay', '0');
        this.loadingIndicator.setAttribute('size', 'small');
        this.loadingIndicator.setAttribute('text', 'Meer laden...');
        this.loadingIndicator.className = 'infinite-scroll-loading';
    }

    /**
     * Handle scroll event with debouncing to prevent duplicate requests
     */
    onScroll() {
        // Quick exit if destroyed, already loading, paused (auto-prefetch active), or no more data
        if (this.destroyed || this.isLoading || this.paused || !this.hasMore) return;

        // Debounce scroll events - wait 50ms for scroll to settle
        if (this.scrollDebounceTimer) {
            clearTimeout(this.scrollDebounceTimer);
        }

        this.scrollDebounceTimer = setTimeout(() => {
            // Re-check guards after debounce (including destroyed and paused flags)
            if (this.destroyed || this.isLoading || this.paused || !this.hasMore) return;

            const scrollTop = this.container.scrollTop;
            const scrollHeight = this.container.scrollHeight;
            const clientHeight = this.container.clientHeight;

            // Check if we're near the bottom
            if (scrollTop + clientHeight >= scrollHeight - this.threshold) {
                this.load();
            }
        }, 50);
    }

    /**
     * Load more data
     */
    async load() {
        // Guard against duplicate calls - set isLoading FIRST before any async work
        if (this.destroyed || this.isLoading || !this.hasMore) {
            return;
        }

        // Additional guard: prevent loading the same lastId twice
        if (this.pendingLastId === this.lastId && this.lastId !== null) {
            return;
        }

        // Mark as loading IMMEDIATELY to prevent race conditions
        this.isLoading = true;
        this.pendingLastId = this.lastId;

        this.showLoading();

        try {
            const result = await this.loadMore(this.lastId, this.pageSize);

            // Check if destroyed while loading - prevents stale loads from appending to new table
            if (this.destroyed) {
                return;
            }

            if (result.success) {
                this.lastId = result.lastId;
                // Use truthy check - PHP may return 1/0 or true/false
                this.hasMore = !!result.hasMore;

                // Append new rows to table
                if (result.html && this.table) {
                    // CRITICAL: Check if table is still in the DOM (may be stale after list reload)
                    if (!document.body.contains(this.table)) {
                        // Table was replaced - try to find the current one
                        const currentTable = document.getElementById('listTable');
                        if (currentTable) {
                            this.table = currentTable;
                        } else {
                            cmaLog.warn('Infinite scroll: table is stale and no replacement found');
                            this.hasMore = false;
                            return;
                        }
                    }

                    const tbody = this.table.querySelector('tbody');
                    if (tbody) {
                        // Parse HTML using a temporary table structure
                        // TR elements are only valid inside TBODY, so we need a proper container
                        const tempTable = document.createElement('table');
                        const tempTbody = document.createElement('tbody');
                        tempTable.appendChild(tempTbody);
                        tempTbody.innerHTML = result.html;

                        // Convert NodeList to array to prevent issues during iteration
                        const newRows = Array.from(tempTbody.querySelectorAll('tr'));

                        // Check if rows are being appended
                        const beforeCount = tbody.querySelectorAll('tr').length;
                        newRows.forEach(row => tbody.appendChild(row));
                        const afterCount = tbody.querySelectorAll('tr').length;
                        const actualRowsAdded = afterCount - beforeCount;

                        // VALIDATION: Check if all rows were actually added
                        if (actualRowsAdded !== newRows.length) {
                            cmaLog.error('[Infinite Scroll] Row append mismatch!', {
                                expected: newRows.length,
                                actual: actualRowsAdded,
                                difference: newRows.length - actualRowsAdded,
                                beforeCount: beforeCount,
                                afterCount: afterCount
                            });
                        }

                        // Update the current count and display
                        this.currentCount += actualRowsAdded; // Use actual rows added, not expected
                        this.updateRecordCountDisplay();

                        // VALIDATION: Verify tracked count matches actual DOM rows
                        this.verifyDomRowCount();

                        // Refresh table filtering to include new rows
                        if (typeof jQuery !== 'undefined' && typeof jQuery.fn.excelTableFilterRefresh === 'function') {
                            jQuery(this.table).excelTableFilterRefresh();
                        }

                        // NOTE: DOM pruning disabled - was causing issues with placeholder growth
                        // this.pruneExcessRows();
                    }
                } else {
                    // No HTML means no more data
                    this.hasMore = false;
                }
            } else {
                cmaLog.warn('Infinite scroll: result.success is false');
                this.hasMore = false;
            }
        } catch (error) {
            cmaLog.error('Infinite scroll load error:', error);
            this.hasMore = false;
        } finally {
            // Skip cleanup if destroyed (already cleaned up)
            if (!this.destroyed) {
                this.isLoading = false;
                // Don't clear pendingLastId here - it's cleared when lastId changes
                // This prevents duplicate requests for the same lastId while allowing retries after errors
                this.hideLoading();
            }
        }
    }

    /**
     * Show loading indicator (lib-loader component)
     */
    showLoading() {
        if (this.table && this.table.parentNode) {
            this.table.parentNode.appendChild(this.loadingIndicator);
            if (this.loadingIndicator.showImmediately) {
                this.loadingIndicator.showImmediately();
            }
        }
    }

    /**
     * Hide loading indicator (lib-loader component)
     */
    hideLoading() {
        if (this.loadingIndicator) {
            if (this.loadingIndicator.hide) {
                this.loadingIndicator.hide();
            }
            // Also remove from DOM when hiding
            if (this.loadingIndicator.parentNode) {
                this.loadingIndicator.remove();
            }
        }
    }

    /**
     * PERFORMANCE FIX: Prune excess rows from DOM to prevent memory bloat
     * Removes rows that are far above the viewport and replaces with a placeholder
     */
    pruneExcessRows() {
        if (!this.table) return;

        const tbody = this.table.querySelector('tbody');
        if (!tbody) return;

        const rows = tbody.querySelectorAll('tr:not(.infinite-scroll-placeholder)');
        const totalRows = rows.length;

        // Only prune if we exceed max rows
        if (totalRows <= this.maxRowsInDom) return;

        // Calculate how many rows to prune
        const rowsToPrune = totalRows - this.maxRowsInDom + this.pruneThreshold;

        // Find rows that are above the viewport
        const containerRect = this.container.getBoundingClientRect();
        let pruneCount = 0;

        for (let i = 0; i < rows.length && pruneCount < rowsToPrune; i++) {
            const row = rows[i];
            const rowRect = row.getBoundingClientRect();

            // Only prune rows that are above the viewport with some buffer
            if (rowRect.bottom < containerRect.top - 100) {
                // Get actual row height before removing
                if (!this.rowHeight || this.rowHeight === 35) {
                    this.rowHeight = row.offsetHeight || 35;
                }
                row.remove();
                pruneCount++;
            } else {
                // Stop when we reach visible rows
                break;
            }
        }

        if (pruneCount > 0) {
            this.prunedTopCount += pruneCount;
            this.updateTopPlaceholder(tbody);
        }
    }

    /**
     * Update or create the top placeholder that maintains scroll position
     */
    updateTopPlaceholder(tbody) {
        const placeholderHeight = this.prunedTopCount * this.rowHeight;

        if (!this.topPlaceholder) {
            // Create placeholder row
            this.topPlaceholder = document.createElement('tr');
            this.topPlaceholder.className = 'infinite-scroll-placeholder';

            // Get column count from table header
            const headerCells = this.table.querySelectorAll('thead th');
            const colCount = headerCells.length || 1;

            const td = document.createElement('td');
            td.colSpan = colCount;
            td.style.height = placeholderHeight + 'px';
            td.style.padding = '0';
            td.style.border = 'none';
            this.topPlaceholder.appendChild(td);

            // Insert at the beginning of tbody
            tbody.insertBefore(this.topPlaceholder, tbody.firstChild);
        } else {
            // Update existing placeholder height
            const td = this.topPlaceholder.querySelector('td');
            if (td) {
                td.style.height = placeholderHeight + 'px';
            }
        }
    }

    /**
     * Reset scroll state (for new searches/filters)
     */
    reset() {
        this.lastId = null;
        this.hasMore = true;
        this.isLoading = false;
        this.pendingLastId = null;

        // Reset count tracking
        this.currentCount = 0;
        this.totalCount = null;

        // Clear any pending scroll debounce
        if (this.scrollDebounceTimer) {
            clearTimeout(this.scrollDebounceTimer);
            this.scrollDebounceTimer = null;
        }

        // Reset pruning state
        this.prunedTopCount = 0;
        if (this.topPlaceholder) {
            this.topPlaceholder.remove();
            this.topPlaceholder = null;
        }
    }

    /**
     * Update state from server response
     * @param {Object} data Response data with hasMore, lastId, count, totalCount
     */
    updateFromResponse(data) {
        // Use truthy check - PHP may return 1/0 or true/false
        this.hasMore = !!data.hasMore;
        this.lastId = data.lastId || null;
        this.pageSize = data.pageSize || this.pageSize;

        // Track record counts
        if (data.count !== undefined) {
            this.currentCount = data.count;
        }
        if (data.totalCount !== undefined && data.totalCount !== null) {
            this.totalCount = data.totalCount;
        }

        // Verify tracked count matches actual DOM rows
        this.verifyDomRowCount();

        // Verify count when all records have been loaded
        this.verifyTotalCount();

        // Update the record count display
        this.updateRecordCountDisplay();
    }

    /**
     * Verify that the totalCount matches the actual loaded count when loading is complete
     * Throws a console error if there's a mismatch
     */
    verifyTotalCount() {
        // Only verify when all data is loaded (hasMore = false)
        if (this.hasMore) return;

        // Need both counts to verify
        if (this.totalCount === null || this.currentCount === 0) return;

        // Check if counts match
        if (this.currentCount !== this.totalCount) {
            const msg = `[Infinite Scroll] Count mismatch! Displayed: ${this.currentCount}, Reported total: ${this.totalCount}. Difference: ${Math.abs(this.currentCount - this.totalCount)}`;
            cmaLog.error(msg);
        }
    }

    /**
     * Verify that the tracked currentCount matches the actual rows in the DOM
     * This catches cases where rows fail to append or are removed unexpectedly
     */
    verifyDomRowCount() {
        if (!this.table) return;

        const tbody = this.table.querySelector('tbody');
        if (!tbody) return;

        // Count actual rows in DOM (excluding placeholder rows)
        const actualRows = tbody.querySelectorAll('tr:not(.infinite-scroll-placeholder)').length;

        // Check if tracked count matches DOM
        if (this.currentCount !== actualRows) {
            const msg = `[Infinite Scroll] DOM count mismatch! Tracked: ${this.currentCount}, Actual DOM rows: ${actualRows}. Difference: ${Math.abs(this.currentCount - actualRows)}`;
            cmaLog.error(msg);

            // Correct the tracked count to match reality
            cmaLog.warn('[Infinite Scroll] Correcting currentCount from', this.currentCount, 'to', actualRows);
            this.currentCount = actualRows;
            this.updateRecordCountDisplay();
        }
    }

    /**
     * Update the record count display in the toolbar
     * Only shows when the container is scrollable (has overflow)
     */
    updateRecordCountDisplay() {
        const countEl = document.getElementById('recordCount');
        if (!countEl) return;

        // Check if container is scrollable - only show count if content overflows
        if (this.container) {
            const isScrollable = this.container.scrollHeight > this.container.clientHeight;
            if (!isScrollable) {
                countEl.style.display = 'none';
                return;
            }
        }

        if (this.totalCount !== null && this.totalCount > 0) {
            // Show "records 1-X van Y" format, but hide if showing all records
            const endRecord = Math.min(this.currentCount, this.totalCount);
            if (endRecord >= this.totalCount) {
                // All records shown - hide the count
                countEl.style.display = 'none';
            } else {
                const loadingText = this.hasMore ? ' (laden...)' : '';
                countEl.textContent = `records 1-${endRecord} van ${this.totalCount}${loadingText}`;
                countEl.style.display = '';
            }
        } else if (this.currentCount > 0) {
            // No totalCount available, just show current count
            countEl.textContent = `${this.currentCount} records`;
            countEl.style.display = '';
        } else {
            countEl.style.display = 'none';
        }
    }

    /**
     * Destroy the infinite scroll handler
     */
    destroy() {
        // Mark as destroyed to stop any pending async loads
        this.destroyed = true;

        // Clear any pending scroll debounce
        if (this.scrollDebounceTimer) {
            clearTimeout(this.scrollDebounceTimer);
            this.scrollDebounceTimer = null;
        }

        if (this.container) {
            this.container.removeEventListener('scroll', this.onScroll);
        }
        if (this.loadingIndicator && this.loadingIndicator.parentNode) {
            this.loadingIndicator.remove();
        }
        // Clean up placeholder
        if (this.topPlaceholder) {
            this.topPlaceholder.remove();
            this.topPlaceholder = null;
        }
    }
}

/**
 * CMA Field Chooser
 * Modal dialog for selecting and reordering visible columns
 */
class CmaFieldChooser {
    // Static flag to prevent multiple instances
    static _isOpen = false;

    constructor(options) {
        this.formId = options.formId;
        this.allFields = options.allFields || []; // Array of {name, caption, visible}
        this.preferences = new CmaTablePreferences(options.formId);
        this.onSave = options.onSave; // Callback when columns change
        this.onClose = options.onClose; // Callback when modal closes (cancel or backdrop click)

        this.modal = null;
        this.dragItem = null;
    }

    /**
     * Show the field chooser modal
     */
    show() {
        // Guard against multiple rapid clicks
        if (CmaFieldChooser._isOpen) {
            return;
        }

        // Check if a modal already exists in the DOM
        const existing = document.querySelector('.field-chooser-modal');
        if (existing) {
            return;
        }

        CmaFieldChooser._isOpen = true;
        this.createModal();
        document.body.appendChild(this.modal);

        // Focus first checkbox
        const firstCheckbox = this.modal.querySelector('input[type="checkbox"]');
        if (firstCheckbox) {
            firstCheckbox.focus();
        }
    }

    /**
     * Create the modal element
     */
    createModal() {
        // Get current order from preferences
        const storedOrder = this.preferences.getColumnOrder();
        const hiddenColumns = this.preferences.getHiddenColumns();

        // Sort fields by stored order, then add any new fields at the end
        let orderedFields = [];
        if (storedOrder.length > 0) {
            storedOrder.forEach(fieldName => {
                const field = this.allFields.find(f => f.name === fieldName);
                if (field) {
                    orderedFields.push({
                        ...field,
                        visible: !hiddenColumns.includes(field.name)
                    });
                }
            });
            // Add fields not in stored order
            this.allFields.forEach(field => {
                if (!storedOrder.includes(field.name)) {
                    orderedFields.push({
                        ...field,
                        visible: !hiddenColumns.includes(field.name)
                    });
                }
            });
        } else {
            orderedFields = this.allFields.map(f => ({
                ...f,
                visible: !hiddenColumns.includes(f.name)
            }));
        }

        // Create modal structure
        this.modal = document.createElement('div');
        this.modal.className = 'field-chooser-modal';
        this.modal.innerHTML = `
            <div class="field-chooser-backdrop"></div>
            <div class="field-chooser-dialog">
                <div class="field-chooser-header">
                    <h3>Kolommen kiezen</h3>
                    <button type="button" class="field-chooser-close">&times;</button>
                </div>
                <div class="field-chooser-body">
                    <p class="field-chooser-hint">Sleep om te herschikken, vink aan/uit om te tonen/verbergen</p>
                    <ul class="field-chooser-list">
                        ${orderedFields.map((field, index) => `
                            <li class="field-chooser-item" data-field="${field.name}" draggable="true">
                                <span class="field-chooser-drag-handle">☰</span>
                                <label>
                                    <input type="checkbox" ${field.visible ? 'checked' : ''} value="${field.name}">
                                    ${this.escapeHtml(field.caption || field.name)}
                                </label>
                            </li>
                        `).join('')}
                    </ul>
                </div>
                <div class="field-chooser-footer">
                    <button type="button" class="btn btn-secondary field-chooser-reset">Standaard</button>
                    <button type="button" class="btn btn-secondary field-chooser-cancel">Annuleren</button>
                    <button type="button" class="btn btn-primary field-chooser-save">Opslaan</button>
                </div>
            </div>
        `;

        // Bind events
        this.bindEvents();
    }

    /**
     * Bind modal events
     */
    bindEvents() {
        // Close button
        this.modal.querySelector('.field-chooser-close').addEventListener('click', () => this.close());
        this.modal.querySelector('.field-chooser-cancel').addEventListener('click', () => this.close());
        this.modal.querySelector('.field-chooser-backdrop').addEventListener('click', () => this.close());

        // Save button
        this.modal.querySelector('.field-chooser-save').addEventListener('click', () => this.save());

        // Reset button
        this.modal.querySelector('.field-chooser-reset').addEventListener('click', () => this.reset());

        // Escape key
        this.modal.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') this.close();
        });

        // Drag and drop for list items
        this.initDragDrop();
    }

    /**
     * Initialize drag and drop for field list
     */
    initDragDrop() {
        const list = this.modal.querySelector('.field-chooser-list');
        const items = list.querySelectorAll('.field-chooser-item');

        items.forEach(item => {
            item.addEventListener('dragstart', (e) => {
                this.dragItem = item;
                item.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
            });

            item.addEventListener('dragend', () => {
                this.dragItem = null;
                item.classList.remove('dragging');
                list.querySelectorAll('.drag-over').forEach(el => el.classList.remove('drag-over'));
            });

            item.addEventListener('dragover', (e) => {
                e.preventDefault();
                if (this.dragItem && this.dragItem !== item) {
                    item.classList.add('drag-over');
                }
            });

            item.addEventListener('dragleave', () => {
                item.classList.remove('drag-over');
            });

            item.addEventListener('drop', (e) => {
                e.preventDefault();
                item.classList.remove('drag-over');

                if (this.dragItem && this.dragItem !== item) {
                    const allItems = Array.from(list.children);
                    const dragIndex = allItems.indexOf(this.dragItem);
                    const dropIndex = allItems.indexOf(item);

                    if (dragIndex < dropIndex) {
                        list.insertBefore(this.dragItem, item.nextSibling);
                    } else {
                        list.insertBefore(this.dragItem, item);
                    }
                }
            });
        });
    }

    /**
     * Save current selection and order
     */
    save() {
        const items = this.modal.querySelectorAll('.field-chooser-item');
        const order = [];
        const hidden = [];

        items.forEach(item => {
            const fieldName = item.dataset.field;
            const checkbox = item.querySelector('input[type="checkbox"]');

            order.push(fieldName);
            if (!checkbox.checked) {
                hidden.push(fieldName);
            }
        });

        this.preferences.setColumnOrder(order);
        this.preferences.setHiddenColumns(hidden);

        if (this.onSave) {
            this.onSave({ order, hidden });
        }

        this.close();
    }

    /**
     * Reset to defaults
     */
    reset() {
        this.preferences.clear();

        if (this.onSave) {
            this.onSave({ order: [], hidden: [], reset: true });
        }

        this.close();
    }

    /**
     * Close the modal
     */
    close() {
        if (this.modal && this.modal.parentNode) {
            this.modal.remove();
        }
        this.modal = null;

        // Reset static flag to allow reopening
        CmaFieldChooser._isOpen = false;

        // Call onClose callback if provided (for cancel/backdrop click)
        if (this.onClose) {
            this.onClose();
        }
    }

    /**
     * Escape HTML
     */
    escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
}

// Export for use
window.CmaTablePreferences = CmaTablePreferences;
window.CmaTableColumnManager = CmaTableColumnManager;
window.CmaInfiniteScroll = CmaInfiniteScroll;
window.CmaFieldChooser = CmaFieldChooser;
