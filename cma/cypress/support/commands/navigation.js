/**
 * Navigation Commands
 *
 * Commands for sidebar, menu, and breadcrumb navigation.
 */

/**
 * Click a menu item by group and item name
 * @param {string} groupName - Menu group name
 * @param {string} itemName - Menu item name
 */
Cypress.Commands.add('clickMenuItem', (groupName, itemName) => {
    // First expand the menu group if collapsed
    cy.contains('[data-cy="menu-group-title"], .cma-menu-group-title', groupName)
        .parents('[data-cy="menu-group"], .cma-menu-group')
        .then($group => {
            if (!$group.hasClass('expanded')) {
                cy.wrap($group).find('[data-cy="menu-group-header"], .cma-menu-group-header').click();
            }
        });

    // Then click the item
    cy.contains('[data-cy="menu-item-text"], .cma-menu-item-text', itemName).click();
    cy.waitForContent();
});

/**
 * Click a single-item menu (direct navigation)
 * @param {string} menuName - Menu name
 */
Cypress.Commands.add('clickSingleMenu', (menuName) => {
    cy.contains('[data-cy="menu-group"].single-item, .cma-menu-group.single-item', menuName)
        .click();
    cy.waitForContent();
});

/**
 * Expand a menu group
 * @param {string} groupName - Menu group name
 */
Cypress.Commands.add('expandMenuGroup', (groupName) => {
    cy.contains('[data-cy="menu-group-title"], .cma-menu-group-title', groupName)
        .parents('[data-cy="menu-group"], .cma-menu-group')
        .as('menuGroup');

    cy.get('@menuGroup').then($group => {
        const hasExpandedClass = $group.hasClass('expanded');
        const hasVisibleItems = $group.find('.cma-menu-item:visible').length > 0;
        // Only click if not already expanded
        if (!hasExpandedClass && !hasVisibleItems) {
            cy.wrap($group).find('[data-cy="menu-group-header"], .cma-menu-group-header').click({ force: true });
            cy.wait(500);
        }
    });

    // Verify the group has visible items after expansion
    cy.get('@menuGroup').find('.cma-menu-item').should('be.visible');
});

/**
 * Collapse a menu group
 * @param {string} groupName - Menu group name
 */
Cypress.Commands.add('collapseMenuGroup', (groupName) => {
    cy.contains('[data-cy="menu-group-title"], .cma-menu-group-title', groupName)
        .parents('[data-cy="menu-group"], .cma-menu-group')
        .as('menuGroup');

    cy.get('@menuGroup').then($group => {
        if ($group.hasClass('expanded')) {
            cy.wrap($group).find('[data-cy="menu-group-header"], .cma-menu-group-header').click();
        }
    });

    cy.get('@menuGroup').should('not.have.class', 'expanded');
});

/**
 * Toggle sidebar collapsed state
 */
Cypress.Commands.add('toggleSidebar', () => {
    cy.get('[data-cy="sidebar-toggle"], .cma-toggle-btn, .cma-sidebar-toggle').click();
});

/**
 * Collapse the sidebar
 */
Cypress.Commands.add('collapseSidebar', () => {
    cy.get('[data-cy="sidebar"], .cma-sidebar').then($sidebar => {
        if (!$sidebar.hasClass('collapsed')) {
            cy.toggleSidebar();
        }
    });
    cy.get('[data-cy="sidebar"], .cma-sidebar').should('have.class', 'collapsed');
});

/**
 * Expand the sidebar
 */
Cypress.Commands.add('expandSidebar', () => {
    cy.get('[data-cy="sidebar"], .cma-sidebar').then($sidebar => {
        if ($sidebar.hasClass('collapsed')) {
            cy.toggleSidebar();
        }
    });
    cy.get('[data-cy="sidebar"], .cma-sidebar').should('not.have.class', 'collapsed');
});

/**
 * Hover over menu group to show popup (when collapsed)
 * @param {string} groupName - Menu group name
 */
Cypress.Commands.add('hoverMenuGroup', (groupName) => {
    cy.contains('[data-cy="menu-group"], .cma-menu-group', groupName)
        .trigger('mouseenter');
});

/**
 * Open user dropdown menu
 */
Cypress.Commands.add('openUserMenu', () => {
    cy.get('[data-cy="user-menu"], .cma-user-menu').trigger('mouseenter');
    cy.get('[data-cy="user-dropdown"], .cma-user-dropdown').should('be.visible');
});

/**
 * Click user menu item
 * @param {string} itemName - Menu item name
 */
Cypress.Commands.add('clickUserMenuItem', (itemName) => {
    cy.openUserMenu();
    cy.get('[data-cy="user-dropdown"], .cma-user-dropdown')
        .contains(itemName)
        .click();
});

/**
 * Check breadcrumb contains text
 * @param {string} text - Expected breadcrumb text
 */
Cypress.Commands.add('breadcrumbShouldContain', (text) => {
    cy.get('[data-cy="breadcrumb"], #breadcrumb, .cma-breadcrumb')
        .should('contain', text);
});

/**
 * Click breadcrumb item
 * @param {string} text - Breadcrumb item text
 */
Cypress.Commands.add('clickBreadcrumb', (text) => {
    cy.get('[data-cy="breadcrumb"], #breadcrumb, .cma-breadcrumb')
        .contains(text)
        .click();
    cy.waitForContent();
});

/**
 * Navigate to Dashboard
 */
Cypress.Commands.add('goToDashboard', () => {
    cy.clickSingleMenu('Dashboard');
});

/**
 * Navigate to Tools
 */
Cypress.Commands.add('goToTools', () => {
    cy.visit('/main.php?page=tools.php');
    cy.get('[data-cy="tools-tree"], #tools-tree, cma-tree').should('be.visible');
});

/**
 * Navigate to Preferences
 */
Cypress.Commands.add('goToPreferences', () => {
    cy.clickUserMenuItem('Voorkeuren');
    cy.waitForContent();
});
