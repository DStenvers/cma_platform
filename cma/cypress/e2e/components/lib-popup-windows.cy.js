/**
 * Popup Window Tests
 *
 * Tests for the lib_OpenWindowCentered popup system.
 * Each window container now has its own backdrop, eliminating z-index sync issues.
 */

describe('Popup Window System', () => {
    beforeEach(() => {
        cy.loginAsAdmin();
        // Visit form.php to have library.js loaded (lib_OpenWindowCentered is in library.js)
        cy.visit('/form.php?form=users');
        // Wait for form to load
        cy.get('#listTable, .form-container', { timeout: 10000 }).should('exist');
    });

    describe('Single Window', () => {
        it('should open a popup window', () => {
            // First verify the function exists
            cy.window().then(win => {
                expect(win.lib_OpenWindowCentered).to.be.a('function');
            });

            // Open window with embedded content for more reliable test
            cy.window().then(win => {
                win.lib_OpenWindowCentered('about:blank', '', 800, 600, 'Test Window', '<p>Test content</p>');
            });

            // Window container should exist and be fullscreen
            cy.get('.lib_window_container', { timeout: 5000 }).should('exist')
                .and('have.css', 'position', 'fixed');

            // Dialog should be centered (flexbox)
            cy.get('.lib_window_dialog').should('exist')
                .and('be.visible');

            // Caption should show title (firstUppercase only changes first char)
            cy.get('.lib_window_caption_title').should('contain', 'Test Window');
        });

        it('should close popup when clicking X button', () => {
            cy.window().then(win => {
                win.lib_OpenWindowCentered('about:blank', '', 800, 600, 'Test Window', '<p>Test content</p>');
            });

            cy.get('.lib_window_container', { timeout: 5000 }).should('exist');
            cy.get('.lib_window_close').click();
            cy.get('.lib_window_container').should('not.exist');
        });

        it('should close popup when clicking backdrop', () => {
            cy.window().then(win => {
                win.lib_OpenWindowCentered('about:blank', '', 800, 600, 'Test Window', '<p>Test content</p>');
            });

            cy.get('.lib_window_container', { timeout: 5000 }).should('exist');
            // Click on the container (backdrop), not the dialog
            cy.get('.lib_window_container').click('topLeft');
            cy.get('.lib_window_container').should('not.exist');
        });

        it('should have draggable caption bar', () => {
            // Note: Cypress synthetic mouse events don't properly trigger drag handlers.
            // This test verifies the popup window structure is correct.
            cy.window().then(win => {
                win.lib_OpenWindowCentered('about:blank', '', 400, 300, 'Draggable Window', '<p>Test content</p>');
            });

            cy.get('.lib_window_dialog', { timeout: 5000 }).should('exist');

            // Verify caption bar exists (draggable target)
            cy.get('.lib_window_caption').should('exist');

            // Verify caption has the title
            cy.get('.lib_window_caption_title').should('contain', 'Draggable Window');
        });
    });

    describe('Stacked Windows', () => {
        it('should stack multiple windows correctly', () => {
            cy.window().then(win => {
                // Open first window
                win.lib_OpenWindowCentered('about:blank', '', 400, 300, 'Window 1', '<p>First window</p>');
            });

            cy.get('.lib_window_container', { timeout: 5000 }).should('have.length', 1);

            cy.window().then(win => {
                // Open second window
                win.lib_OpenWindowCentered('about:blank', '', 400, 300, 'Window 2', '<p>Second window</p>');
            });

            cy.get('.lib_window_container', { timeout: 5000 }).should('have.length', 2);

            // Both windows should have their own backdrop
            cy.get('.lib_window_container').each($container => {
                cy.wrap($container).should('have.css', 'background-color')
                    .and('not.equal', 'rgba(0, 0, 0, 0)'); // Has backdrop color
            });

            // Second window should have higher z-index
            cy.get('.lib_window_container').first().then($first => {
                cy.get('.lib_window_container').last().then($last => {
                    const firstZ = parseInt($first.css('z-index'));
                    const lastZ = parseInt($last.css('z-index'));
                    expect(lastZ).to.be.greaterThan(firstZ);
                });
            });
        });

        it('should keep first window usable after closing second window', () => {
            cy.window().then(win => {
                // Open first window
                win.lib_OpenWindowCentered('about:blank', '', 400, 300, 'Window 1', '<p>First window</p>');
            });

            cy.get('.lib_window_container', { timeout: 5000 }).should('have.length', 1);

            cy.window().then(win => {
                // Open second window
                win.lib_OpenWindowCentered('about:blank', '', 400, 300, 'Window 2', '<p>Second window</p>');
            });

            cy.get('.lib_window_container', { timeout: 5000 }).should('have.length', 2);

            // Close second window
            cy.get('.lib_window_container').last().find('.lib_window_close').click();

            // Only first window should remain
            cy.get('.lib_window_container').should('have.length', 1);

            // First window's dialog should be visible and clickable (not covered by fader)
            cy.get('.lib_window_dialog').should('be.visible');
            cy.get('.lib_window_caption_title').should('contain', 'Window 1');

            // Dialog should be interactive (not covered by any overlay)
            cy.get('.lib_window_dialog').click();
            cy.get('.lib_window_dialog').should('be.visible');
        });

        it('should close topmost window when pressing Escape', () => {
            cy.window().then(win => {
                win.lib_OpenWindowCentered('about:blank', '', 400, 300, 'Window 1', '<p>First window</p>');
                win.lib_OpenWindowCentered('about:blank', '', 400, 300, 'Window 2', '<p>Second window</p>');
            });

            cy.get('.lib_window_container', { timeout: 5000 }).should('have.length', 2);

            // Press Escape
            cy.get('body').type('{esc}');

            // Only first window should remain
            cy.get('.lib_window_container').should('have.length', 1);
            cy.get('.lib_window_caption_title').should('contain', 'Window 1');
        });
    });

    describe('Maximize/Restore', () => {
        it('should maximize and restore window', () => {
            cy.window().then(win => {
                win.lib_OpenWindowCentered('about:blank', '', 400, 300, 'Maximize Test', '<p>Content</p>');
            });

            cy.get('.lib_window_dialog', { timeout: 5000 }).then($dialog => {
                const initialWidth = $dialog.width();

                // Click maximize button
                cy.get('.lib_window_max').click();

                // Container should have maximized class
                cy.get('.lib_window_container').should('have.class', 'maximized');

                // Dialog should be larger
                cy.get('.lib_window_dialog').invoke('width').should('be.greaterThan', initialWidth);

                // Click maximize again to restore
                cy.get('.lib_window_max').click();

                // Should no longer be maximized
                cy.get('.lib_window_container').should('not.have.class', 'maximized');
            });
        });
    });

    describe('No Legacy Fader', () => {
        it('should not create a separate fader element', () => {
            cy.window().then(win => {
                win.lib_OpenWindowCentered('about:blank', '', 400, 300, 'Test', '<p>Content</p>');
            });

            // Wait for window to appear
            cy.get('.lib_window_container', { timeout: 5000 }).should('exist');

            // The legacy fader should not exist
            cy.get('#__lib_fader').should('not.exist');

            // Backdrop is now part of the container itself
            cy.get('.lib_window_container').should('have.css', 'background-color')
                .and('not.equal', 'rgba(0, 0, 0, 0)');
        });
    });
});
