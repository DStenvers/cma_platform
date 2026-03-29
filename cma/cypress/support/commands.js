/**
 * CMA Custom Cypress Commands
 *
 * Reusable commands for common CMA operations.
 */

/**
 * Login to CMA
 * Uses element IDs: #txtLogin, #txtPW, #btnLogin, #sidebar
 * @param {string} username - Login username
 * @param {string} password - Login password
 */
Cypress.Commands.add('login', (username, password) => {
  cy.session([username, password], () => {
    cy.visit('/login.php');
    cy.get('#txtLogin').clear().type(username);
    cy.get('#txtPW').clear().type(password);
    cy.get('#btnLogin').click();
    cy.url().should('include', 'main.php');
    cy.get('#sidebar').should('be.visible');
  });
});

/**
 * Login as admin user
 */
Cypress.Commands.add('loginAsAdmin', () => {
  cy.login(Cypress.env('adminUser'), Cypress.env('adminPass'));
});

/**
 * Login as regular test user
 */
Cypress.Commands.add('loginAsUser', () => {
  cy.login(Cypress.env('testUser'), Cypress.env('testPass'));
});

/**
 * Open a form by name
 * Uses element IDs: #contentArea
 * @param {string} formName - JSON form name (e.g., 'opleidingen')
 */
Cypress.Commands.add('openForm', (formName) => {
  // Use query string format: /form.php?form=formname
  cy.visit(`/form.php?form=${formName}`);
  // Wait for form content to load - check for actual content elements (table or form layout)
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
 * Open a specific record in a form
 * @param {string} formName - JSON form name
 * @param {string|number} recordId - Record ID
 */
Cypress.Commands.add('openRecord', (formName, recordId) => {
  // Use query string format with formID parameter
  cy.visit(`/form.php?form=${formName}&formID=${recordId}`);
  // Wait for sidepanel to open with detail content
  cy.get('.lib_sidepanel_container, .detail-content', { timeout: 10000 }).should('be.visible');
});

/**
 * Click a menu item by name
 * @param {string} menuName - Menu group name
 * @param {string} itemName - Menu item name
 */
Cypress.Commands.add('clickMenuItem', (menuName, itemName) => {
  // First expand the menu group if collapsed
  cy.contains('.cma-menu-group-title', menuName)
    .parents('.cma-menu-group')
    .then($group => {
      if (!$group.hasClass('expanded')) {
        cy.wrap($group).find('.cma-menu-group-header').click();
      }
    });

  // Then click the item
  cy.contains('.cma-menu-item-text', itemName).click();
});

/**
 * Wait for AJAX content to load
 * Uses element IDs: #contentArea
 */
Cypress.Commands.add('waitForContent', () => {
  // Wait for actual content elements to appear instead of checking for absence of loading text
  cy.get('#listTable, table.listtable, .form-layout, .detail-content, .error-message, .toolbar', { timeout: 30000 }).should('exist');
});

/**
 * Check if a field has a specific data type
 * @param {string} fieldName - Field name attribute
 * @param {string} expectedType - Expected data-type value
 */
Cypress.Commands.add('fieldShouldHaveType', (fieldName, expectedType) => {
  cy.get(`[name="${fieldName}"]`)
    .should('have.attr', 'data-type', expectedType);
});

/**
 * Check if a date field has a datepicker
 * @param {string} fieldName - Field name attribute
 */
Cypress.Commands.add('fieldShouldHaveDatepicker', (fieldName) => {
  cy.get(`[name="${fieldName}"]`)
    .parent()
    .find('.calendar-trigger, .lnr-calendar-full')
    .should('exist');
});

/**
 * Fill a form field
 * @param {string} fieldName - Field name attribute
 * @param {string} value - Value to enter
 */
Cypress.Commands.add('fillField', (fieldName, value) => {
  cy.get(`[name="${fieldName}"]`).clear().type(value);
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

// Note: clickToolbarButton is defined in commands/forms.js with proper data-action selectors

/**
 * Assert a notification message appears
 * @param {string} type - Notification type (success, error, warning, info)
 * @param {string} message - Expected message text (partial match)
 */
Cypress.Commands.add('shouldShowNotification', (type, message) => {
  cy.get(`lib-message[type="${type}"], .alert-${type}`)
    .should('be.visible')
    .and('contain', message);
});

/**
 * Get table row by content
 * @param {string} content - Text content to find in row
 */
Cypress.Commands.add('getTableRow', (content) => {
  return cy.get('#listTable tbody tr, table.listtable tbody tr')
    .contains('tr', content);
});

/**
 * Click inline edit on a table row
 * @param {string} rowContent - Text content to identify the row
 */
Cypress.Commands.add('inlineEditRow', (rowContent) => {
  cy.getTableRow(rowContent).find('.three-dot-menu, .row-actions').click();
  cy.get('.context-menu, .dropdown-menu').contains('Wijzigen').click();
});

/**
 * Open tools page
 * Uses element IDs: #tools-tree
 */
Cypress.Commands.add('openTools', () => {
  cy.visit('/main.php?page=tools.php');
  cy.get('#tools-tree').should('be.visible');
});

/**
 * Click a tool in the tools tree
 * Uses element IDs: #tools-tree, #details_iframe
 * @param {string} toolName - Name of the tool to click
 */
Cypress.Commands.add('clickTool', (toolName) => {
  cy.get('#tools-tree').contains(toolName).click();
  cy.get('#details_iframe').should('not.have.attr', 'src', 'about:blank');
});

/**
 * Click a table row by index
 * @param {number} index - Zero-based row index
 */
Cypress.Commands.add('clickTableRow', (index) => {
  cy.get('#listTable tbody tr, table.listtable tbody tr')
    .eq(index)
    .click();
});

/**
 * Expand sidebar if collapsed
 */
Cypress.Commands.add('expandSidebar', () => {
  cy.get('body').then($body => {
    // Check if sidebar is collapsed
    if ($body.hasClass('sidebar-collapsed') || $body.find('#sidebar.collapsed').length > 0) {
      cy.get('#sidebarToggle, .sidebar-toggle, #sidebar-toggle').click();
    }
  });
});

/**
 * Expand a menu group by name
 * @param {string} groupName - Name of the menu group to expand
 */
Cypress.Commands.add('expandMenuGroup', (groupName) => {
  cy.contains('.cma-menu-group-title', groupName)
    .parents('.cma-menu-group')
    .then($group => {
      if (!$group.hasClass('expanded')) {
        cy.wrap($group).find('.cma-menu-group-header').click();
      }
    });
});

// Note: Toast notification commands (shouldShowToast, shouldShowSuccess, shouldShowError)
// are defined in commands/forms.js

/**
 * Dismiss any visible tips or overlays
 * Useful when tips are blocking test interactions
 */
Cypress.Commands.add('dismissTips', () => {
    // Remove all lib-tip elements from the DOM to prevent overlay blocking
    // The tip-overlay is inside lib-tip's Shadow DOM and blocks all clicks at z-index 10000
    cy.window().then(win => {
        // Close via API if available
        if (win.LibTip && typeof win.LibTip.close === 'function') {
            win.LibTip.close();
        }
        // Remove all lib-tip elements from DOM entirely
        win.document.querySelectorAll('lib-tip').forEach(el => el.remove());
    });
});

/**
 * Wait for tips to be dismissed and element to be interactable
 * @param {string} selector - Element selector to wait for
 */
Cypress.Commands.add('waitForInteractable', (selector) => {
    // First dismiss any tips
    cy.dismissTips();
    // Then wait for the element
    cy.get(selector).should('be.visible').and('not.be.disabled');
});

/**
 * Wait for table list items to be available, skip test if none found
 * Used in report designer tests when database might not have tables configured
 * @param {number} timeout - Timeout in ms to wait for tables
 * @returns {Cypress.Chainable} - Chainable with table items or skips
 */
Cypress.Commands.add('waitForTablesOrSkip', { prevSubject: false }, function(timeout = 10000) {
    return cy.get('.table-list-items', { timeout }).then($container => {
        const $items = $container.find('.table-list-item');
        if ($items.length === 0) {
            // Store flag for the test to check
            cy.wrap(true).as('noTablesAvailable');
            return cy.wrap([]);
        }
        cy.wrap(false).as('noTablesAvailable');
        return cy.wrap($items);
    });
});

/**
 * Skip current test if no tables are available
 * Call this at the start of tests that require database tables
 */
Cypress.Commands.add('skipIfNoTables', { prevSubject: false }, function() {
    cy.get('@noTablesAvailable', { log: false }).then(function(noTables) {
        if (noTables === true) {
            this.skip();
        }
    });
});
