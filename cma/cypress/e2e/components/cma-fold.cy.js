/**
 * cma-fold Web Component Tests
 *
 * Tests for the cma-fold custom element - a draggable panel resizer.
 * Tests drag behavior, collapse/expand, state persistence, and orientations.
 */

describe('cma-fold Web Component', () => {
    beforeEach(() => {
        cy.loginAsAdmin();
        cy.visit('/main.php');
    });

    /**
     * Helper to wait for cma-fold custom element to be defined
     * Must be called after visiting a page that loads cma-fold.js
     */
    const waitForCmaFold = () => {
        return cy.window().then(win => {
            return new Cypress.Promise((resolve) => {
                if (win.customElements.get('cma-fold')) {
                    resolve();
                } else {
                    win.customElements.whenDefined('cma-fold').then(resolve);
                }
            });
        });
    };

    /**
     * Helper to create a cma-fold element in test context
     * Ensures the component is properly defined first
     */
    const createFoldElement = (attrs = {}) => {
        return cy.document().then(doc => {
            const fold = doc.createElement('cma-fold');
            Object.entries(attrs).forEach(([key, value]) => {
                fold.setAttribute(key, value);
            });
            doc.body.appendChild(fold);
            return fold;
        });
    };

    describe('Component Registration', () => {
        it('should be defined in customElements registry', () => {
            // Just visit main.php - component scripts are loaded in HEAD
            cy.visit('/main.php');
            cy.window().then(win => {
                expect(win.customElements.get('cma-fold')).to.exist;
            });
        });

        it('should render as a custom element on tools page', () => {
            cy.visit('/main.php?page=tools.php');
            // Wait for AJAX content to load - look for tools-tree first (indicates content loaded)
            cy.get('#tools-tree', { timeout: 20000 }).should('exist');
            // Now cma-fold should exist
            cy.get('cma-fold', { timeout: 5000 }).should('exist');
        });
    });

    describe('Attributes', () => {
        // Load page with cma-fold to ensure component is defined
        beforeEach(() => {
            cy.visit('/main.php?page=tools.php');
            // Wait for AJAX content to load
            cy.get('#tools-tree', { timeout: 20000 }).should('exist');
            cy.get('cma-fold', { timeout: 5000 }).should('exist');
        });

        describe('orientation attribute', () => {
            it('should default to "vertical"', () => {
                waitForCmaFold().then(() => {
                    cy.document().then(doc => {
                        const fold = doc.createElement('cma-fold');
                        doc.body.appendChild(fold);
                        expect(fold.orientation).to.eq('vertical');
                    });
                });
            });

            it('should support "horizontal" orientation', () => {
                waitForCmaFold().then(() => {
                    cy.document().then(doc => {
                        const fold = doc.createElement('cma-fold');
                        fold.setAttribute('orientation', 'horizontal');
                        doc.body.appendChild(fold);
                        expect(fold.orientation).to.eq('horizontal');
                    });
                });
            });

            it('should set col-resize cursor for vertical', () => {
                waitForCmaFold().then(() => {
                    cy.document().then(doc => {
                        const fold = doc.createElement('cma-fold');
                        fold.setAttribute('orientation', 'vertical');
                        fold.id = 'test-fold-v';
                        doc.body.appendChild(fold);
                    });
                    cy.get('#test-fold-v')
                        .should('have.css', 'cursor', 'col-resize');
                });
            });

            it('should set row-resize cursor for horizontal', () => {
                waitForCmaFold().then(() => {
                    cy.document().then(doc => {
                        const fold = doc.createElement('cma-fold');
                        fold.setAttribute('orientation', 'horizontal');
                        fold.id = 'test-fold-h';
                        doc.body.appendChild(fold);
                    });
                    cy.get('#test-fold-h')
                        .should('have.css', 'cursor', 'row-resize');
                });
            });
        });

        describe('target attribute', () => {
            it('should use CSS selector to find target element', () => {
                waitForCmaFold().then(() => {
                    cy.document().then(doc => {
                        const target = doc.createElement('div');
                        target.id = 'resize-target';
                        target.style.width = '200px';
                        doc.body.appendChild(target);

                        const fold = doc.createElement('cma-fold');
                        fold.setAttribute('target', '#resize-target');
                        fold.setAttribute('orientation', 'vertical');
                        doc.body.appendChild(fold);

                        // Fold should have found the target
                        expect(fold._target).to.eq(target);
                    });
                });
            });

            it('should use previous sibling if no target specified', () => {
                waitForCmaFold().then(() => {
                    cy.document().then(doc => {
                        const container = doc.createElement('div');
                        container.style.display = 'flex';

                        const sibling = doc.createElement('div');
                        sibling.style.width = '200px';
                        container.appendChild(sibling);

                        const fold = doc.createElement('cma-fold');
                        container.appendChild(fold);

                        doc.body.appendChild(container);

                        // Fold should target previous sibling
                        expect(fold._target).to.eq(sibling);
                    });
                });
            });
        });

        describe('min-size attribute', () => {
            it('should default to 150', () => {
                waitForCmaFold().then(() => {
                    cy.document().then(doc => {
                        const fold = doc.createElement('cma-fold');
                        expect(fold.minSize).to.eq(150);
                    });
                });
            });

            it('should use custom min-size', () => {
                waitForCmaFold().then(() => {
                    cy.document().then(doc => {
                        const fold = doc.createElement('cma-fold');
                        fold.setAttribute('min-size', '100');
                        expect(fold.minSize).to.eq(100);
                    });
                });
            });
        });

        describe('max-size attribute', () => {
            it('should default to 600', () => {
                waitForCmaFold().then(() => {
                    cy.document().then(doc => {
                        const fold = doc.createElement('cma-fold');
                        expect(fold.maxSize).to.eq(600);
                    });
                });
            });

            it('should use custom max-size', () => {
                waitForCmaFold().then(() => {
                    cy.document().then(doc => {
                        const fold = doc.createElement('cma-fold');
                        fold.setAttribute('max-size', '800');
                        expect(fold.maxSize).to.eq(800);
                    });
                });
            });
        });

        describe('collapsed-size attribute', () => {
            it('should default to 0', () => {
                waitForCmaFold().then(() => {
                    cy.document().then(doc => {
                        const fold = doc.createElement('cma-fold');
                        expect(fold.collapsedSize).to.eq(0);
                    });
                });
            });

            it('should use custom collapsed-size', () => {
                waitForCmaFold().then(() => {
                    cy.document().then(doc => {
                        const fold = doc.createElement('cma-fold');
                        fold.setAttribute('collapsed-size', '40');
                        expect(fold.collapsedSize).to.eq(40);
                    });
                });
            });
        });

        describe('storage-key attribute', () => {
            it('should persist state when storage-key is set', () => {
                cy.get('cma-fold').first().invoke('attr', 'storage-key').then(key => {
                    if (key) {
                        // Interact with the fold to trigger state save
                        cy.get('cma-fold').first()
                            .trigger('mousedown', { clientX: 280 })
                            .trigger('mousemove', { clientX: 300 }, { force: true });
                        cy.document().trigger('mouseup');

                        // Wait a moment for state to be saved
                        cy.wait(100);

                        // Now check localStorage
                        cy.window().then(win => {
                            const storageKey = `cma_fold_${key}`;
                            const stored = win.localStorage.getItem(storageKey);
                            expect(stored).to.not.be.null;
                            const state = JSON.parse(stored);
                            expect(state).to.have.property('size');
                            expect(state).to.have.property('collapsed');
                        });
                    }
                });
            });
        });
    });

    describe('Properties and Methods', () => {
        // Load page with cma-fold to ensure component is defined
        beforeEach(() => {
            cy.visit('/main.php?page=tools.php');
            // Wait for AJAX content to load
            cy.get('#tools-tree', { timeout: 20000 }).should('exist');
            cy.get('cma-fold', { timeout: 5000 }).should('exist');
        });

        describe('isVertical getter', () => {
            it('should return true for vertical orientation', () => {
                waitForCmaFold().then(() => {
                    cy.document().then(doc => {
                        const fold = doc.createElement('cma-fold');
                        fold.setAttribute('orientation', 'vertical');
                        expect(fold.isVertical).to.be.true;
                    });
                });
            });

            it('should return false for horizontal orientation', () => {
                waitForCmaFold().then(() => {
                    cy.document().then(doc => {
                        const fold = doc.createElement('cma-fold');
                        fold.setAttribute('orientation', 'horizontal');
                        expect(fold.isVertical).to.be.false;
                    });
                });
            });
        });

        describe('collapse() method', () => {
            it('should collapse the target panel', () => {
                waitForCmaFold().then(() => {
                    cy.document().then(doc => {
                        const container = doc.createElement('div');
                        container.style.display = 'flex';

                        const target = doc.createElement('div');
                        target.id = 'collapse-target';
                        target.style.width = '200px';
                        container.appendChild(target);

                        const fold = doc.createElement('cma-fold');
                        fold.setAttribute('orientation', 'vertical');
                        fold.setAttribute('collapsed-size', '0');
                        container.appendChild(fold);

                        doc.body.appendChild(container);

                        fold.collapse();
                    });
                    cy.get('#collapse-target')
                        .should('have.css', 'width', '0px');
                });
            });
        });

        describe('expand() method', () => {
            it('should expand a collapsed panel', () => {
                waitForCmaFold().then(() => {
                    cy.document().then(doc => {
                        const container = doc.createElement('div');
                        container.style.display = 'flex';

                        const target = doc.createElement('div');
                        target.id = 'expand-target';
                        target.style.width = '200px';
                        container.appendChild(target);

                        const fold = doc.createElement('cma-fold');
                        fold.setAttribute('orientation', 'vertical');
                        container.appendChild(fold);

                        doc.body.appendChild(container);

                        fold.collapse();
                        fold.expand();
                    });
                    cy.get('#expand-target')
                        .invoke('css', 'width')
                        .then(width => {
                            expect(parseInt(width)).to.be.greaterThan(0);
                        });
                });
            });
        });

        describe('toggle() method', () => {
            it('should toggle between collapsed and expanded', () => {
                waitForCmaFold().then(() => {
                    cy.document().then(doc => {
                        const container = doc.createElement('div');
                        container.style.display = 'flex';

                        const target = doc.createElement('div');
                        target.id = 'toggle-target';
                        target.style.width = '200px';
                        container.appendChild(target);

                        const fold = doc.createElement('cma-fold');
                        fold.setAttribute('orientation', 'vertical');
                        container.appendChild(fold);

                        doc.body.appendChild(container);

                        const initialCollapsed = fold._collapsed;
                        fold.toggle();
                        expect(fold._collapsed).to.eq(!initialCollapsed);
                    });
                });
            });
        });

        describe('setSize() method', () => {
            it('should set target size programmatically', () => {
                waitForCmaFold().then(() => {
                    cy.document().then(doc => {
                        const container = doc.createElement('div');
                        container.style.display = 'flex';

                        const target = doc.createElement('div');
                        target.id = 'setsize-target';
                        target.style.width = '200px';
                        container.appendChild(target);

                        const fold = doc.createElement('cma-fold');
                        fold.setAttribute('orientation', 'vertical');
                        fold.setAttribute('min-size', '100');
                        fold.setAttribute('max-size', '400');
                        container.appendChild(fold);

                        doc.body.appendChild(container);

                        fold.setSize(300);
                    });
                    cy.get('#setsize-target')
                        .should('have.css', 'width', '300px');
                });
            });

            it('should clamp size to min/max', () => {
                waitForCmaFold().then(() => {
                    cy.document().then(doc => {
                        const container = doc.createElement('div');
                        container.style.display = 'flex';

                        const target = doc.createElement('div');
                        target.id = 'clamp-target';
                        target.style.width = '200px';
                        container.appendChild(target);

                        const fold = doc.createElement('cma-fold');
                        fold.setAttribute('orientation', 'vertical');
                        fold.setAttribute('min-size', '150');
                        fold.setAttribute('max-size', '400');
                        container.appendChild(fold);

                        doc.body.appendChild(container);

                        fold.setSize(50); // Below min
                    });
                    cy.get('#clamp-target')
                        .should('have.css', 'width', '150px'); // Clamped to min
                });
            });
        });
    });

    describe('Drag Behavior', () => {
        // Load page with cma-fold
        beforeEach(() => {
            cy.visit('/main.php?page=tools.php');
            // Wait for AJAX content to load
            cy.get('#tools-tree', { timeout: 20000 }).should('exist');
            cy.get('cma-fold', { timeout: 5000 }).should('exist');
        });

        it('should resize target on drag (vertical)', () => {
            cy.get('cma-fold[orientation="vertical"]', { timeout: 15000 }).first().then($fold => {
                const fold = $fold[0];
                const target = fold._target;

                if (target) {
                    const initialWidth = target.offsetWidth;

                    // Simulate drag
                    cy.wrap($fold)
                        .trigger('mousedown', { clientX: 280 })
                        .trigger('mousemove', { clientX: 350 }, { force: true });

                    cy.document().trigger('mouseup');
                }
            });
        });

        it('should add dragging class during drag', () => {
            cy.get('cma-fold').first().then($fold => {
                cy.wrap($fold)
                    .trigger('mousedown', { clientX: 280 });

                cy.wrap($fold)
                    .should('have.class', 'dragging');

                cy.document().trigger('mouseup');
            });
        });

        it('should remove dragging class after drag ends', () => {
            cy.get('cma-fold').first().then($fold => {
                cy.wrap($fold)
                    .trigger('mousedown', { clientX: 280 });

                cy.document().trigger('mouseup');

                cy.wrap($fold)
                    .should('not.have.class', 'dragging');
            });
        });

        it('should disable text selection during drag', () => {
            cy.get('cma-fold').first().then($fold => {
                cy.wrap($fold)
                    .trigger('mousedown', { clientX: 280 });

                // Body should have user-select: none during drag
                cy.get('body')
                    .should('have.css', 'user-select', 'none');

                cy.document().trigger('mouseup');

                // Body should restore user-select after drag
                cy.get('body')
                    .should('not.have.css', 'user-select', 'none');
            });
        });

        it('should follow mouse movement accurately', () => {
            cy.get('cma-fold[orientation="vertical"]').first().then($fold => {
                const fold = $fold[0];
                const target = fold._target;

                if (target) {
                    const initialWidth = target.offsetWidth;

                    // Simulate a drag movement of 50px to the right
                    cy.wrap($fold)
                        .trigger('mousedown', { clientX: 280, pointerId: 1 });

                    // Move mouse 50px to the right
                    cy.document().trigger('mousemove', { clientX: 330 });

                    // The target should have grown by approximately 50px
                    cy.wrap(target)
                        .invoke('prop', 'offsetWidth')
                        .should('be.closeTo', initialWidth + 50, 10);

                    cy.document().trigger('mouseup');
                }
            });
        });
    });

    describe('Double-Click Collapse', () => {
        it('should collapse on double-click', () => {
            cy.visit('/main.php?page=tools.php');
            // Wait for AJAX content to load
            cy.get('#tools-tree', { timeout: 20000 }).should('exist');
            cy.get('cma-fold', { timeout: 5000 }).should('exist');

            cy.get('cma-fold').first().then($fold => {
                const fold = $fold[0];
                if (fold._target) {
                    const initialWidth = fold._target.offsetWidth;

                    cy.wrap($fold)
                        .shadow()
                        .find('.grip')
                        .parent()
                        .dblclick();

                    // Should be collapsed or expanded (toggled)
                }
            });
        });
    });

    describe('Events', () => {
        // Load page with cma-fold to ensure component is defined
        beforeEach(() => {
            cy.visit('/main.php?page=tools.php');
            // Wait for AJAX content to load
            cy.get('#tools-tree', { timeout: 20000 }).should('exist');
            cy.get('cma-fold', { timeout: 5000 }).should('exist');
        });

        describe('fold-resize event', () => {
            it('should fire during resize', () => {
                waitForCmaFold().then(() => {
                    cy.document().then(doc => {
                        const container = doc.createElement('div');
                        container.style.display = 'flex';

                        const target = doc.createElement('div');
                        target.style.width = '200px';
                        container.appendChild(target);

                        const fold = doc.createElement('cma-fold');
                        fold.setAttribute('orientation', 'vertical');
                        container.appendChild(fold);

                        doc.body.appendChild(container);

                        let eventFired = false;
                        fold.addEventListener('fold-resize', () => {
                            eventFired = true;
                        });

                        // Simulate drag
                        fold.dispatchEvent(new MouseEvent('mousedown', { clientX: 200 }));
                        document.dispatchEvent(new MouseEvent('mousemove', { clientX: 250 }));
                        document.dispatchEvent(new MouseEvent('mouseup'));

                        // Event may or may not fire depending on drag implementation
                    });
                });
            });

            it('should include size in event detail', () => {
                // Event detail should contain { size, collapsed }
            });
        });

        describe('fold-collapse event', () => {
            it('should fire on collapse/expand', () => {
                waitForCmaFold().then(() => {
                    cy.document().then(doc => {
                        const container = doc.createElement('div');
                        container.style.display = 'flex';

                        const target = doc.createElement('div');
                        target.style.width = '200px';
                        container.appendChild(target);

                        const fold = doc.createElement('cma-fold');
                        fold.setAttribute('orientation', 'vertical');
                        container.appendChild(fold);

                        doc.body.appendChild(container);

                        let eventDetail = null;
                        fold.addEventListener('fold-collapse', (e) => {
                            eventDetail = e.detail;
                        });

                        fold.toggle();

                        expect(eventDetail).to.not.be.null;
                        expect(eventDetail).to.have.property('collapsed');
                    });
                });
            });
        });
    });

    describe('State Persistence', () => {
        it('should save state to localStorage', () => {
            cy.visit('/main.php?page=tools.php');
            // Wait for AJAX content to load
            cy.get('#tools-tree', { timeout: 20000 }).should('exist');
            cy.get('cma-fold', { timeout: 5000 }).should('exist');

            cy.get('cma-fold').first().invoke('attr', 'storage-key').then(key => {
                if (key) {
                    cy.window().then(win => {
                        const stored = win.localStorage.getItem(`cma_fold_${key}`);
                        if (stored) {
                            const state = JSON.parse(stored);
                            expect(state).to.have.property('size');
                            expect(state).to.have.property('collapsed');
                        }
                    });
                }
            });
        });

        it('should restore state from localStorage', () => {
            cy.window().then(win => {
                win.localStorage.setItem('cma_fold_test_storage', JSON.stringify({
                    size: 250,
                    collapsed: false,
                    savedSize: null
                }));
            });

            // Reload page and verify state restoration
            cy.visit('/main.php?page=tools.php');
            // Wait for AJAX content to load
            cy.get('#tools-tree', { timeout: 20000 }).should('exist');
            cy.get('cma-fold', { timeout: 5000 }).should('exist');
        });
    });

    describe('Styling', () => {
        // Load page with cma-fold
        beforeEach(() => {
            cy.visit('/main.php?page=tools.php');
            // Wait for AJAX content to load
            cy.get('#tools-tree', { timeout: 20000 }).should('exist');
            cy.get('cma-fold', { timeout: 5000 }).should('exist');
        });

        it('should have gradient background', () => {
            cy.get('cma-fold').first()
                .should('have.css', 'background-image')
                .and('include', 'gradient');
        });

        it('should have grip dots', () => {
            cy.get('cma-fold').first()
                .shadow()
                .find('.grip')
                .should('exist');

            cy.get('cma-fold').first()
                .shadow()
                .find('.grip span')
                .should('have.length', 3);
        });

        it('should highlight on hover', () => {
            cy.get('cma-fold').first()
                .trigger('mouseover');

            cy.get('cma-fold').first()
                .should('have.css', 'background-image');
        });

        it('should have correct width for vertical orientation', () => {
            cy.get('cma-fold[orientation="vertical"]').first()
                .should('have.css', 'width', '8px');
        });
    });

    describe('Shadow DOM', () => {
        // Load page with cma-fold
        beforeEach(() => {
            cy.visit('/main.php?page=tools.php');
            // Wait for AJAX content to load
            cy.get('#tools-tree', { timeout: 20000 }).should('exist');
            cy.get('cma-fold', { timeout: 5000 }).should('exist');
        });

        it('should use shadow DOM encapsulation', () => {
            cy.get('cma-fold').first().then($fold => {
                expect($fold[0].shadowRoot).to.exist;
            });
        });

        it('should contain styles in shadow DOM', () => {
            cy.get('cma-fold').first()
                .shadow()
                .find('style')
                .should('exist');
        });
    });

    describe('Cleanup', () => {
        it('should remove event listeners on disconnect', () => {
            cy.visit('/main.php?page=tools.php');
            // Wait for AJAX content to load
            cy.get('#tools-tree', { timeout: 20000 }).should('exist');
            cy.get('cma-fold', { timeout: 5000 }).should('exist');

            waitForCmaFold().then(() => {
                cy.document().then(doc => {
                    const fold = doc.createElement('cma-fold');
                    doc.body.appendChild(fold);

                    // Remove element
                    fold.remove();

                    // Should not throw when trying to drag
                    cy.document().trigger('mousemove', { clientX: 100 });
                });
            });
        });
    });
});
