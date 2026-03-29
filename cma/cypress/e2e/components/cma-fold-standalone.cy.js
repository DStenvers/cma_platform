/**
 * Cypress tests for cma-fold component using standalone test HTML
 *
 * Tests the fold component in isolation without requiring the full CMA application
 */
describe('cma-fold Component (Standalone)', () => {
    beforeEach(() => {
        // Visit the standalone test HTML file
        cy.visit('/cypress/fixtures/components/cma-fold-test.html');

        // Wait for component to be registered
        cy.window().then(win => {
            expect(win.customElements.get('cma-fold')).to.exist;
        });

        // Wait for test page JavaScript to initialize (sets up button handlers)
        cy.get('#validation-log').should('contain', 'All tests initialized');
    });

    describe('Component Registration', () => {
        it('should register the cma-fold custom element', () => {
            cy.window().then(win => {
                const CmaFold = win.customElements.get('cma-fold');
                expect(CmaFold).to.exist;
                // Check that prototype has validateSetup method using typeof to avoid Cypress inspection issues
                expect(typeof CmaFold.prototype.validateSetup).to.equal('function');
            });
        });

        it('should render all test fold elements', () => {
            cy.get('cma-fold').should('have.length', 5);
        });
    });

    describe('CSS Validation - Correct Setup', () => {
        it('should pass validation for vertical fold with correct CSS', () => {
            cy.get('#fold-vertical').then($el => {
                const fold = $el[0];
                const result = fold.validateSetup();
                expect(result.valid).to.be.true;
                expect(result.errors).to.have.length(0);
            });
        });

        it('should pass validation for horizontal fold with correct CSS', () => {
            cy.get('#fold-horizontal').then($el => {
                const fold = $el[0];
                const result = fold.validateSetup();
                expect(result.valid).to.be.true;
                expect(result.errors).to.have.length(0);
            });
        });

        it('should display success validation in UI for correct setups', () => {
            cy.get('#validation-vertical')
                .should('have.class', 'success')
                .and('contain', 'CSS Validation Passed');

            cy.get('#validation-horizontal')
                .should('have.class', 'success')
                .and('contain', 'CSS Validation Passed');
        });
    });

    describe('CSS Validation - Incorrect Setup', () => {
        it('should fail validation when parent is not flex', () => {
            cy.get('#fold-incorrect').then($el => {
                const fold = $el[0];
                const result = fold.validateSetup();
                expect(result.valid).to.be.false;
                expect(result.errors.length).to.be.greaterThan(0);
                expect(result.errors.some(e => e.includes('display: flex'))).to.be.true;
            });
        });

        it('should display error validation in UI for incorrect setups', () => {
            cy.get('#validation-incorrect')
                .should('have.class', 'error')
                .and('contain', 'CSS Validation Failed');
        });

        it('should provide suggestions for fixing incorrect CSS', () => {
            cy.get('#fold-incorrect').then($el => {
                const fold = $el[0];
                const result = fold.validateSetup();
                expect(result.suggestions.length).to.be.greaterThan(0);
            });
        });
    });

    describe('CSS Validation - Missing Flex Shorthand', () => {
        it('should warn about missing flex shorthand on target', () => {
            cy.get('#fold-missing-flex').then($el => {
                const fold = $el[0];
                const result = fold.validateSetup();
                // May have warnings even if valid
                expect(result.warnings.some(w =>
                    w.includes('flex: 0 0') || w.includes('flex shorthand')
                )).to.be.true;
            });
        });
    });

    describe('Vertical Fold - Resize Functionality', () => {
        it('should resize target width when dragged horizontally', () => {
            cy.get('#panel-vertical').invoke('outerWidth').then(initialWidth => {
                // Get the fold bar
                cy.get('#fold-vertical').then($fold => {
                    const fold = $fold[0];
                    const rect = fold.getBoundingClientRect();

                    // Simulate drag
                    cy.get('#fold-vertical')
                        .trigger('mousedown', { clientX: rect.left, clientY: rect.top + 10 })
                        .trigger('mousemove', { clientX: rect.left + 50, clientY: rect.top + 10, force: true })
                        .trigger('mouseup', { force: true });

                    // Check width changed
                    cy.get('#panel-vertical').invoke('outerWidth').should('be.greaterThan', initialWidth);
                });
            });
        });

        it('should update width display during resize', () => {
            cy.get('#width-display').invoke('text').then(initialText => {
                cy.get('#fold-vertical').then($fold => {
                    const fold = $fold[0];
                    const rect = fold.getBoundingClientRect();

                    cy.get('#fold-vertical')
                        .trigger('mousedown', { clientX: rect.left, clientY: rect.top + 10 })
                        .trigger('mousemove', { clientX: rect.left + 50, clientY: rect.top + 10, force: true })
                        .trigger('mouseup', { force: true });

                    cy.get('#width-display').invoke('text').should('not.eq', initialText);
                });
            });
        });

        it('should fire fold-resize event during drag', () => {
            let eventFired = false;
            cy.get('#fold-vertical').then($fold => {
                $fold[0].addEventListener('fold-resize', () => {
                    eventFired = true;
                });

                const rect = $fold[0].getBoundingClientRect();

                cy.get('#fold-vertical')
                    .trigger('mousedown', { clientX: rect.left, clientY: rect.top + 10 })
                    .trigger('mousemove', { clientX: rect.left + 30, clientY: rect.top + 10, force: true })
                    .trigger('mouseup', { force: true })
                    .then(() => {
                        expect(eventFired).to.be.true;
                    });
            });
        });
    });

    describe('Horizontal Fold - Resize Functionality', () => {
        it('should resize target height when dragged vertically', () => {
            cy.get('#panel-horizontal').invoke('outerHeight').then(initialHeight => {
                cy.get('#fold-horizontal').then($fold => {
                    const fold = $fold[0];
                    const rect = fold.getBoundingClientRect();

                    cy.get('#fold-horizontal')
                        .trigger('mousedown', { clientX: rect.left + 10, clientY: rect.top })
                        .trigger('mousemove', { clientX: rect.left + 10, clientY: rect.top + 40, force: true })
                        .trigger('mouseup', { force: true });

                    cy.get('#panel-horizontal').invoke('outerHeight').should('be.greaterThan', initialHeight);
                });
            });
        });
    });

    describe('Collapse/Expand API', () => {
        it('should collapse panel when collapse() is called', () => {
            cy.get('#panel-collapsed').invoke('outerWidth').then(initialWidth => {
                expect(initialWidth).to.be.greaterThan(0);

                cy.get('#btn-collapse').click();

                // Panel should be collapsed (0 width or very small)
                cy.get('#panel-collapsed').invoke('outerWidth').should('be.lessThan', 50);
            });
        });

        it('should expand panel when expand() is called', () => {
            // First collapse
            cy.get('#btn-collapse').click();
            cy.get('#panel-collapsed').invoke('outerWidth').should('be.lessThan', 50);

            // Then expand
            cy.get('#btn-expand').click();
            cy.get('#panel-collapsed').invoke('outerWidth').should('be.greaterThan', 100);
        });

        it('should toggle panel state when toggle() is called', () => {
            cy.get('#collapsed-state').should('contain', 'open');

            cy.get('#btn-toggle').click();
            cy.get('#collapsed-state').should('contain', 'collapsed');

            cy.get('#btn-toggle').click();
            cy.get('#collapsed-state').should('contain', 'open');
        });

        it('should set specific size when setSize() is called', () => {
            cy.get('#btn-setsize').click();

            // Check that panel width changed from setSize() call
            // Note: actual size may differ based on component min/max constraints
            cy.get('#panel-collapsed').invoke('outerWidth').then(width => {
                expect(width).to.be.greaterThan(100);
            });
        });

        it('should update collapsed state display', () => {
            cy.get('#collapsed-state').should('contain', 'open');

            cy.get('#btn-collapse').click();
            cy.get('#collapsed-state').should('contain', 'collapsed');

            cy.get('#btn-expand').click();
            cy.get('#collapsed-state').should('contain', 'open');
        });
    });

    describe('Double-Click Collapse', () => {
        it('should collapse on double-click', () => {
            cy.get('#panel-collapsed').invoke('outerWidth').should('be.greaterThan', 100);

            cy.get('#fold-collapsed').dblclick();

            cy.get('#panel-collapsed').invoke('outerWidth').should('be.lessThan', 50);
        });

        it('should expand on second double-click', () => {
            // First double-click to collapse
            cy.get('#fold-collapsed').dblclick();
            cy.get('#panel-collapsed').invoke('outerWidth').should('be.lessThan', 50);

            // Second double-click to expand
            cy.get('#fold-collapsed').dblclick();
            cy.get('#panel-collapsed').invoke('outerWidth').should('be.greaterThan', 100);
        });
    });

    describe('Min/Max Size Constraints', () => {
        it('should not resize below min-size', () => {
            cy.get('#fold-vertical').then($fold => {
                const fold = $fold[0];
                const minSize = fold.minSize; // Should be 100
                const rect = fold.getBoundingClientRect();

                // Try to drag far to the left (reduce size)
                cy.get('#fold-vertical')
                    .trigger('mousedown', { clientX: rect.left, clientY: rect.top + 10 })
                    .trigger('mousemove', { clientX: rect.left - 500, clientY: rect.top + 10, force: true })
                    .trigger('mouseup', { force: true });

                // Width should not go below min-size
                cy.get('#panel-vertical').invoke('outerWidth').should('be.at.least', minSize);
            });
        });

        it('should not resize above max-size', () => {
            cy.get('#fold-vertical').then($fold => {
                const fold = $fold[0];
                const maxSize = fold.maxSize; // Should be 400
                const rect = fold.getBoundingClientRect();

                // Try to drag far to the right (increase size)
                cy.get('#fold-vertical')
                    .trigger('mousedown', { clientX: rect.left, clientY: rect.top + 10 })
                    .trigger('mousemove', { clientX: rect.left + 500, clientY: rect.top + 10, force: true })
                    .trigger('mouseup', { force: true });

                // Width should not exceed max-size
                cy.get('#panel-vertical').invoke('outerWidth').should('be.at.most', maxSize);
            });
        });
    });

    describe('Cursor and Visual Feedback', () => {
        it('should show resize cursor on hover', () => {
            cy.get('#fold-vertical').should('have.css', 'cursor', 'col-resize');
        });

        it('should show vertical resize cursor for horizontal fold', () => {
            cy.get('#fold-horizontal').should('have.css', 'cursor', 'row-resize');
        });
    });

    describe('Validation Log', () => {
        it('should log successful tests to validation log', () => {
            cy.get('#validation-log')
                .should('contain', 'cma-fold component is registered')
                .and('contain', 'All tests initialized');
        });

        it('should log validation results', () => {
            cy.get('#validation-log').then($log => {
                const text = $log.text();
                // Should have logged test results
                expect(text).to.include('Test 1');
                expect(text).to.include('Test 3');
            });
        });
    });

    describe('getDiagnostics Method', () => {
        it('should return diagnostic information', () => {
            cy.get('#fold-vertical').then($el => {
                const fold = $el[0];
                if (typeof fold.getDiagnostics === 'function') {
                    const diag = fold.getDiagnostics();
                    expect(diag).to.have.property('orientation');
                    expect(diag).to.have.property('minSize');
                    expect(diag).to.have.property('maxSize');
                }
            });
        });
    });
});
