/**
 * Form Commands
 *
 * Commands for form navigation, field interaction, and CRUD operations.
 */

/**
 * Clear all CMA caches via API
 * Call this before opening forms to ensure no stale cached data
 */
Cypress.Commands.add('clearCache', () => {
    cy.request({
        url: '/cma/tools/tools_clearcache.php?api=1',
        failOnStatusCode: false
    }).then((response) => {
        if (response.status === 200 && response.body.success) {
            cy.log(`Cache cleared: ${response.body.cleared} files`);
        }
    });
});

/**
 * Open a form by name
 * @param {string} formName - JSON form name (e.g., 'opleidingen')
 */
Cypress.Commands.add('openForm', (formName) => {
    // Clear cache first to ensure we're not testing stale cached forms
    cy.clearCache();
    // Use query string format: /form.php?form=formname
    cy.visit(`/form.php?form=${formName}`);
    // Wait for form content to load - check for actual content elements instead of absence of loading text
    cy.get('#listTable, table.listtable, .form-layout, .detail-content, .error-message', { timeout: 30000 }).should('exist');
    // Dismiss any tip overlays that may block interactions
    cy.dismissTips();
});

/**
 * Open a form and wait for table view
 * @param {string} formName - JSON form name
 */
Cypress.Commands.add('openFormTable', (formName) => {
    cy.openForm(formName);
    // Main table has id="listTable" and class="listtable filtering sorttable"
    cy.get('#listTable, table.listtable', { timeout: 15000 }).should('be.visible');
});

/**
 * Open a form in tree mode with detail view visible
 * Use this mode for CRUD operations via detail form
 * @param {string} formName - JSON form name
 */
Cypress.Commands.add('openFormTree', (formName) => {
    // Clear cache first to ensure we're not testing stale cached forms
    cy.clearCache();
    // Visit form.php directly with view=tree parameter
    cy.visit(`/form.php?form=${formName}&view=tree&nocache=1`);
    // Wait for page to load and body to have mode-tree class
    cy.get('body.mode-tree', { timeout: 15000 }).should('exist');
    // Wait for list content and detail panel to be available
    cy.get('#listContent', { timeout: 15000 }).should('exist');
    cy.get('.detail-panel', { timeout: 15000 }).should('be.visible');
});

/**
 * Open a specific record in a form
 * @param {string} formName - JSON form name
 * @param {string|number} recordId - Record ID
 */
Cypress.Commands.add('openRecord', (formName, recordId) => {
    // Clear cache first to ensure we're not testing stale cached forms
    cy.clearCache();
    // Use query string format with formID parameter
    cy.visit(`/form.php?form=${formName}&formID=${recordId}`);
    cy.waitForContent();
    // Wait for sidepanel to open with detail content
    cy.get('.lib_sidepanel_container, [data-cy="detail-view"], .detail-content, .form-layout', { timeout: 10000 }).should('be.visible');
});

/**
 * Wait for AJAX content to load
 */
Cypress.Commands.add('waitForContent', () => {
    // Wait for actual content elements to appear instead of checking for absence of loading text
    cy.get('#listTable, table.listtable, .form-layout, .detail-content, .error-message, .toolbar', { timeout: 30000 }).should('exist');
});

/**
 * Get a form field by name
 * @param {string} fieldName - Field name attribute
 */
Cypress.Commands.add('getField', (fieldName) => {
    return cy.get(`[name="${fieldName}"], [data-field="${fieldName}"]`);
});

/**
 * Fill a form field
 * @param {string} fieldName - Field name attribute
 * @param {string} value - Value to enter
 */
Cypress.Commands.add('fillField', (fieldName, value) => {
    cy.getField(fieldName).then($el => {
        if ($el.is('select')) {
            cy.wrap($el).select(value);
        } else if ($el.is('textarea')) {
            cy.wrap($el).clear().type(value);
        } else if ($el.attr('type') === 'checkbox') {
            if (value) {
                cy.wrap($el).check();
            } else {
                cy.wrap($el).uncheck();
            }
        } else {
            cy.wrap($el).clear().type(value);
        }
    });
});

/**
 * Clear a form field
 * @param {string} fieldName - Field name attribute
 */
Cypress.Commands.add('clearField', (fieldName) => {
    cy.getField(fieldName).clear();
});

/**
 * Check if a field has a specific value
 * @param {string} fieldName - Field name attribute
 * @param {string} expectedValue - Expected value
 */
Cypress.Commands.add('fieldShouldHaveValue', (fieldName, expectedValue) => {
    cy.getField(fieldName).should('have.value', expectedValue);
});

/**
 * Check if a field has a specific data type
 * @param {string} fieldName - Field name attribute
 * @param {string} expectedType - Expected data-type value
 */
Cypress.Commands.add('fieldShouldHaveType', (fieldName, expectedType) => {
    cy.getField(fieldName).should('have.attr', 'data-type', expectedType);
});

/**
 * Check if a date field has a datepicker
 * @param {string} fieldName - Field name attribute
 */
Cypress.Commands.add('fieldShouldHaveDatepicker', (fieldName) => {
    cy.getField(fieldName)
        .parent()
        .find('[data-cy="calendar-trigger"], .calendar-trigger, .cal_arrow')
        .should('exist');
});

/**
 * Select a combobox option (lib-combo web component)
 * @param {string} fieldName - Field name attribute
 * @param {string} optionText - Text of option to select
 */
Cypress.Commands.add('selectOption', (fieldName, optionText) => {
    cy.get(`lib-combo[name="${fieldName}"]`).click();
    cy.get(`lib-combo[name="${fieldName}"]`).shadow().find('.option').contains(optionText).click();
});

/**
 * Get table row by content
 * @param {string} content - Text content to find in row
 */
Cypress.Commands.add('getTableRow', (content) => {
    return cy.get('[data-cy="form-table"] tbody tr, table.filtering tbody tr')
        .contains('tr', content);
});

/**
 * Get table row by index
 * @param {number} index - Zero-based row index
 */
Cypress.Commands.add('getTableRowByIndex', (index) => {
    return cy.get('[data-cy="form-table"] tbody tr, table.filtering tbody tr')
        .eq(index);
});

/**
 * Click on a table row to open detail view
 * @param {number} index - Zero-based row index
 */
Cypress.Commands.add('clickTableRow', (index = 0) => {
    cy.getTableRowByIndex(index).click();
    cy.get('[data-cy="detail-view"], .detail-content', { timeout: 15000 }).should('be.visible');
});

/**
 * Click inline edit on a table row
 * Uses right-click which directly starts inline editing when canEdit is true.
 * @param {string} rowContent - Text content to identify the row
 */
Cypress.Commands.add('inlineEditRow', (rowContent) => {
    cy.getTableRow(rowContent).rightclick();
    // Right-click shows context menu - click "Direct wijzigen" to enter edit mode
    cy.get('.cma-context-menu.row-menu', { timeout: 5000 })
        .should('be.visible')
        .contains('Direct wijzigen')
        .click();
    // Wait for the row to enter edit mode (editing class added by CmaInlineEdit)
    cy.get('tr.editing', { timeout: 10000 }).should('exist');
    // Wait for inline edit controls to load (API call to get record data)
    cy.get('.inline-edit-button-row', { timeout: 10000 }).should('exist');
});

/**
 * Click inline edit on a table row by index
 * Right-click shows context menu, then clicks "Direct wijzigen" to start editing.
 * @param {number} index - Zero-based row index
 */
Cypress.Commands.add('inlineEditRowByIndex', (index = 0) => {
    cy.getTableRowByIndex(index).rightclick();
    // Right-click shows context menu - click "Direct wijzigen" to enter edit mode
    cy.get('.cma-context-menu.row-menu', { timeout: 5000 })
        .should('be.visible')
        .contains('Direct wijzigen')
        .click();
    // Wait for the row to enter edit mode
    cy.get('tr.editing', { timeout: 10000 }).should('exist');
    // Wait for inline edit controls to load
    cy.get('.inline-edit-button-row', { timeout: 10000 }).should('exist');
});

/**
 * Save inline edit
 * The save button is in the .inline-edit-button-row overlay positioned below the editing row.
 */
Cypress.Commands.add('saveInlineEdit', () => {
    cy.get('.inline-edit-button-row .btn-save-inline, [data-cy="inline-save"]')
        .click();
});

/**
 * Cancel inline edit
 * The cancel button is in the .inline-edit-button-row overlay positioned below the editing row.
 */
Cypress.Commands.add('cancelInlineEdit', () => {
    cy.get('.inline-edit-button-row .btn-cancel-inline, [data-cy="inline-cancel"]')
        .click();
});

/**
 * Click toolbar button
 * @param {string} action - Button action (save, delete, add, copy, etc.)
 */
Cypress.Commands.add('clickToolbarButton', (action) => {
    const buttonMap = {
        'save': '[data-action="save"]',
        'delete': '[data-action="delete"]',
        // addInline is used in tree mode (detail toolbar), add is used in table mode (list toolbar)
        'add': '[data-action="addInline"], [data-action="add"]',
        'copy': '[data-action="copy"]',
        'refresh': '[data-action="refresh"]',
        'cancel': '[data-action="cancel"]'
    };

    const selector = buttonMap[action] || `[data-action="${action}"]`;
    // Filter to only visible elements and click the first one
    cy.get(selector).filter(':visible').first().click();
});

/**
 * Assert table has rows
 * @param {number} minCount - Minimum expected row count
 */
Cypress.Commands.add('tableShouldHaveRows', (minCount = 1) => {
    cy.get('[data-cy="form-table"] tbody tr, table.filtering tbody tr')
        .should('have.length.at.least', minCount);
});

/**
 * Assert table is empty
 */
Cypress.Commands.add('tableShouldBeEmpty', () => {
    cy.get('body').should('contain', 'Geen gegevens');
});

/**
 * Open subform tab
 * @param {string} tabName - Subform tab name
 */
Cypress.Commands.add('openSubformTab', (tabName) => {
    cy.get('[data-cy="subform-tabs"], .cma-tabs, .subform-tabs')
        .contains(tabName)
        .click();
    cy.waitForContent();
});

/**
 * Get subform table
 */
Cypress.Commands.add('getSubformTable', () => {
    return cy.get('[data-cy="subform-table"], .subform-content table');
});

/**
 * Check for a toast notification (lib-toaster)
 * Toast notifications appear in lib-toaster's Shadow DOM
 * @param {string} type - Toast type: 'success', 'error', 'warning', 'info'
 * @param {object} options - Optional: { timeout: number }
 */
Cypress.Commands.add('shouldShowToast', (type = 'success', options = {}) => {
    const timeout = options.timeout || 10000;

    // lib-toaster creates toasts inside shadow DOM with class 'toast {type}'
    cy.get('lib-toaster', { timeout })
        .should('exist')
        .shadow()
        .find(`.toast.${type}`, { timeout })
        .should('exist');
});

/**
 * Wait for success notification (either lib-message or lib-toaster)
 * This handles both notification systems for backwards compatibility
 */
Cypress.Commands.add('shouldShowSuccess', (options = {}) => {
    const timeout = options.timeout || 10000;

    // First wait for either notification system to appear
    cy.get('lib-toaster, lib-message[type="success"]', { timeout }).should('exist');

    // Then check for success toast or message
    cy.document().then((doc) => {
        const toaster = doc.querySelector('lib-toaster');
        if (toaster && toaster.shadowRoot) {
            const successToast = toaster.shadowRoot.querySelector('.toast.success');
            if (successToast) {
                cy.log('Found success toast in lib-toaster');
                return;
            }
        }
        const libMessage = doc.querySelector('lib-message[type="success"]');
        if (libMessage) {
            cy.get('lib-message[type="success"]').should('be.visible');
            return;
        }
        // If neither found, wait for toaster's shadow DOM content
        cy.get('lib-toaster').shadow().find('.toast.success', { timeout }).should('exist');
    });
});

/**
 * Wait for error notification
 */
Cypress.Commands.add('shouldShowError', (options = {}) => {
    const timeout = options.timeout || 10000;

    cy.get('lib-toaster, lib-message[type="error"]', { timeout }).should('exist');

    cy.document().then((doc) => {
        const toaster = doc.querySelector('lib-toaster');
        if (toaster && toaster.shadowRoot) {
            const errorToast = toaster.shadowRoot.querySelector('.toast.error');
            if (errorToast) {
                cy.log('Found error toast in lib-toaster');
                return;
            }
        }
        const libMessage = doc.querySelector('lib-message[type="error"]');
        if (libMessage) {
            cy.get('lib-message[type="error"]').should('be.visible');
        }
    });
});
