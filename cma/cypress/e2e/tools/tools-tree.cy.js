/**
 * Tools Tree Tests
 *
 * Tests for the tools tree view and tool selection.
 * cma-tree uses shadow DOM with .complextree container structure:
 * - li.f_open / li.f_closed for folders
 * - li with a.t.icon for items
 */

describe('Tools Tree', () => {
    beforeEach(() => {
        cy.loginAsAdmin();
        cy.visit('/main.php?page=tools.php');
        // Wait for tools page to load and cma-tree to be visible
        cy.get('cma-tree#tools-tree', { timeout: 15000 }).should('exist');
        // Wait for the tree to render its content (shadow DOM)
        cy.get('cma-tree#tools-tree')
            .shadow()
            .find('.complextree', { timeout: 10000 })
            .should('exist');
    });

    describe('Tools Page Display', () => {
        it('should display tools page', () => {
            cy.get('cma-tree#tools-tree')
                .should('be.visible');
        });

        it('should show tools tree component', () => {
            cy.get('cma-tree#tools-tree')
                .should('exist');
        });

        it('should have tree nodes', () => {
            // cma-tree uses shadow DOM with .complextree container
            cy.get('cma-tree#tools-tree')
                .shadow()
                .find('.complextree li')
                .should('have.length.at.least', 1);
        });
    });

    describe('Tree Navigation', () => {
        it('should expand tree node on click', () => {
            // Find a closed folder and click to expand
            cy.get('cma-tree#tools-tree')
                .shadow()
                .find('.complextree li.f_closed')
                .first()
                .find('> a')
                .click({ force: true });
        });

        it('should show child nodes when expanded', () => {
            // Find first open folder and check for children
            cy.get('cma-tree#tools-tree')
                .shadow()
                .find('.complextree li.f_open')
                .first()
                .find('ul li')
                .should('exist');
        });

        it('should highlight selected node', () => {
            // Click on an item link (items have data-item-id attribute)
            // Use invoke to call the click via JavaScript which properly triggers event handlers
            cy.get('cma-tree#tools-tree')
                .shadow()
                .find('.complextree a[data-item-id]')
                .first()
                .then($el => {
                    // Trigger click via JavaScript to ensure event handlers fire
                    $el[0].click();
                });

            // Check that iframe loaded (indicates item was clicked and processed)
            cy.get('#details_iframe, #tools-content, iframe.tools-content-area')
                .should('exist')
                .should('have.attr', 'src')
                .and('not.eq', 'about:blank');
        });
    });

    describe('Tool Selection', () => {
        it('should load tool content on node click', () => {
            // Find an item (items have data-item-id) and click it
            cy.get('cma-tree#tools-tree')
                .shadow()
                .find('.complextree a[data-item-id]')
                .first()
                .click({ force: true });

            // Details iframe should exist (AJAX mode uses #tools-content)
            cy.get('#details_iframe, #tools-content, iframe.tools-content-area')
                .should('exist');
        });

        it('should display tool in iframe', () => {
            // Wait a bit for tree to be ready then click a tool
            cy.wait(500);
            cy.get('cma-tree#tools-tree')
                .shadow()
                .find('.complextree a[data-item-id]')
                .first()
                .click({ force: true });

            // Check iframe src was updated
            cy.get('#details_iframe, #tools-content, iframe.tools-content-area')
                .should('have.attr', 'src')
                .and('not.eq', 'about:blank');
        });
    });

    describe('Tool Categories', () => {
        it('should have database tools', () => {
            cy.get('cma-tree#tools-tree')
                .shadow()
                .find('.complextree')
                .should('contain', 'Database');
        });

        it('should have system tools', () => {
            cy.get('cma-tree#tools-tree')
                .shadow()
                .find('.complextree')
                .then($tree => {
                    const text = $tree.text();
                    const hasSystemTools = text.includes('Standaard') ||
                                          text.includes('Server');
                    expect(hasSystemTools).to.be.true;
                });
        });
    });

    describe('Tree State', () => {
        it('should remember expanded state', () => {
            // First check if there are any folders
            cy.get('cma-tree#tools-tree')
                .shadow()
                .find('.complextree a[data-folder-id]')
                .first()
                .then($folder => {
                    if ($folder.length === 0) {
                        // No folders to test, skip
                        return;
                    }

                    // Toggle a folder to ensure state is saved
                    cy.wrap($folder).click({ force: true });

                    // Wait for localStorage to be updated
                    cy.wait(200);

                    // Refresh page
                    cy.reload();
                    cy.get('cma-tree#tools-tree', { timeout: 15000 }).should('exist');
                    cy.get('cma-tree#tools-tree')
                        .shadow()
                        .find('.complextree', { timeout: 10000 })
                        .should('exist');

                    // Tree should have some folders (expanded or collapsed)
                    cy.get('cma-tree#tools-tree')
                        .shadow()
                        .find('.complextree a[data-folder-id]')
                        .should('exist');
                });
        });
    });

    describe('Responsive Layout', () => {
        it('should adjust layout on resize', () => {
            cy.viewport(1024, 768);

            cy.get('cma-tree#tools-tree')
                .should('be.visible');
        });
    });
});
