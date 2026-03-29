/**
 * lib-loader Web Component Tests
 *
 * Tests for the loading indicator component with delayed display.
 * Uses a real CMA page to ensure the custom element is already registered.
 */

describe('lib-loader Component', () => {
    beforeEach(() => {
        cy.loginAsAdmin();
        cy.visit('/main.php');
        // Ensure lib-loader is registered
        cy.window().then(win => {
            expect(win.customElements.get('lib-loader')).to.exist;
        });
    });

    afterEach(() => {
        // Clean up any test loaders we added
        cy.document().then(doc => {
            doc.querySelectorAll('lib-loader[data-test]').forEach(el => el.remove());
        });
    });

    /**
     * Helper: create a lib-loader element on the page with given attributes.
     * Returns a Cypress chainable for the created element.
     */
    function createLoader(attrs = {}) {
        cy.document().then(doc => {
            const loader = doc.createElement('lib-loader');
            loader.setAttribute('data-test', 'true');
            Object.entries(attrs).forEach(([key, value]) => {
                loader.setAttribute(key, value);
            });
            doc.body.appendChild(loader);
        });
        return cy.get('lib-loader[data-test]').last();
    }

    describe('Initialization', () => {
        it('should render as invisible by default', () => {
            createLoader();
            cy.get('lib-loader[data-test]').last()
                .should('not.have.class', 'visible')
                .and('have.css', 'display', 'none');
        });

        it('should show immediately when active attribute is set with delay=0', () => {
            createLoader({ delay: '0', active: '' });
            cy.get('lib-loader[data-test]').last()
                .should('have.class', 'visible')
                .and('have.css', 'display', 'flex');
        });

        it('should show after delay when active attribute is set', () => {
            createLoader({ delay: '200' });

            // Should not be visible initially
            cy.get('lib-loader[data-test]').last()
                .should('not.have.class', 'visible');

            // Set active attribute
            cy.get('lib-loader[data-test]').last().then($el => {
                $el[0].setAttribute('active', '');
            });

            // Should become visible after the delay
            cy.get('lib-loader[data-test]').last()
                .should('have.class', 'visible', { timeout: 5000 });
        });
    });

    describe('Size Attribute', () => {
        it('should render small spinner', () => {
            createLoader({ size: 'small', delay: '0', active: '' });
            cy.get('lib-loader[data-test]').last()
                .should('have.class', 'visible')
                .shadow().find('.spinner')
                .should('have.css', 'width', '16px');
        });

        it('should render medium spinner by default', () => {
            createLoader({ delay: '0', active: '' });
            cy.get('lib-loader[data-test]').last()
                .should('have.class', 'visible')
                .shadow().find('.spinner')
                .should('have.css', 'width', '32px');
        });

        it('should render large spinner', () => {
            createLoader({ size: 'large', delay: '0', active: '' });
            cy.get('lib-loader[data-test]').last()
                .should('have.class', 'visible')
                .shadow().find('.spinner')
                .should('have.css', 'width', '48px');
        });
    });

    describe('Text Attribute', () => {
        it('should display text when provided', () => {
            createLoader({ text: 'Laden...', delay: '0', active: '' });
            cy.get('lib-loader[data-test]').last()
                .should('have.class', 'visible')
                .shadow().find('.text')
                .should('exist')
                .and('contain.text', 'Laden...');
        });

        it('should not display text element when not provided', () => {
            createLoader({ delay: '0', active: '' });
            cy.get('lib-loader[data-test]').last()
                .should('have.class', 'visible')
                .shadow().find('.text')
                .should('not.exist');
        });
    });

    describe('Methods', () => {
        it('should show immediately with showImmediately()', () => {
            createLoader({ delay: '5000' });

            // Call showImmediately on the element
            cy.get('lib-loader[data-test]').last().then($el => {
                $el[0].showImmediately();
            });

            cy.get('lib-loader[data-test]').last()
                .should('have.class', 'visible');
        });

        it('should hide with hide()', () => {
            createLoader({ delay: '0', active: '' });

            // Wait for it to show
            cy.get('lib-loader[data-test]').last()
                .should('have.class', 'visible');

            // Hide it
            cy.get('lib-loader[data-test]').last().then($el => {
                $el[0].hide();
            });

            cy.get('lib-loader[data-test]').last()
                .should('not.have.class', 'visible');
        });

        it('should cancel pending delay when hide() is called', () => {
            createLoader({ delay: '200' });

            // Start showing then immediately hide
            cy.get('lib-loader[data-test]').last().then($el => {
                $el[0].show();
                $el[0].hide();
            });

            // Wait past the delay
            cy.wait(400);

            // Should still be hidden
            cy.get('lib-loader[data-test]').last()
                .should('not.have.class', 'visible');
        });
    });

    describe('Events', () => {
        it('should dispatch show event when becoming visible', () => {
            createLoader({ delay: '0' });

            cy.get('lib-loader[data-test]').last().then($el => {
                const loader = $el[0];
                let showFired = false;
                loader.addEventListener('show', () => { showFired = true; });
                loader.show();

                cy.wrap(null).should(() => {
                    expect(showFired).to.be.true;
                });
            });
        });

        it('should dispatch hide event when hiding', () => {
            createLoader({ delay: '0', active: '' });

            // Wait for visible state first
            cy.get('lib-loader[data-test]').last()
                .should('have.class', 'visible')
                .then($el => {
                    const loader = $el[0];
                    let hideFired = false;
                    loader.addEventListener('hide', () => { hideFired = true; });
                    loader.hide();

                    cy.wrap(null).should(() => {
                        expect(hideFired).to.be.true;
                    });
                });
        });
    });

    describe('Overlay Mode', () => {
        it('should have overlay styling when overlay attribute is set', () => {
            createLoader({ overlay: '', delay: '0', active: '' });

            cy.get('lib-loader[data-test]').last()
                .should('have.class', 'visible')
                .and('have.css', 'position', 'absolute');
        });
    });
});
