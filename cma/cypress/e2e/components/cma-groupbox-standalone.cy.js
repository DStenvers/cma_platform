/**
 * Cypress tests for cma-groupbox component using standalone test HTML
 *
 * Tests the groupbox component in isolation without requiring the full CMA application
 */
describe('cma-groupbox Component (Standalone)', () => {
    beforeEach(() => {
        // Clear localStorage to ensure clean state
        cy.clearLocalStorage();

        // Visit the standalone test HTML file
        cy.visit('/cypress/fixtures/components/cma-groupbox-test.html');

        // Wait for component to be registered
        cy.window().then(win => {
            expect(win.customElements.get('cma-groupbox')).to.exist;
        });

        // Wait for test page JavaScript to initialize (sets up button handlers)
        cy.get('#test-log').should('contain', 'All tests initialized');
    });

    describe('Component Registration', () => {
        it('should register the cma-groupbox custom element', () => {
            cy.window().then(win => {
                const CmaGroupbox = win.customElements.get('cma-groupbox');
                expect(CmaGroupbox).to.exist;
            });
        });

        it('should render all test groupbox elements', () => {
            cy.get('cma-groupbox').should('have.length.at.least', 7);
        });
    });

    describe('Basic Toggle', () => {
        it('should start in open state by default', () => {
            cy.get('#groupbox-1').should('have.class', 'group_open');
            cy.get('#_g1_1').should('be.visible');
        });

        it('should toggle to closed when clicked', () => {
            cy.get('#groupbox-1').click();
            cy.get('#groupbox-1').should('have.class', 'group_closed');
            cy.get('#_g1_1').should('not.be.visible');
        });

        it('should toggle back to open on second click', () => {
            cy.get('#groupbox-1').click(); // close
            cy.get('#groupbox-1').click(); // open
            cy.get('#groupbox-1').should('have.class', 'group_open');
            cy.get('#_g1_1').should('be.visible');
        });

        it('should update state display', () => {
            cy.get('#state-1').should('contain', 'open');
            cy.get('#groupbox-1').click();
            cy.get('#state-1').should('contain', 'collapsed');
        });
    });

    describe('Initially Collapsed', () => {
        it('should start collapsed when collapsed attribute is present', () => {
            cy.get('#groupbox-2').should('have.class', 'group_closed');
        });

        it('should hide group rows when collapsed', () => {
            cy.get('#_g2_1').should('not.be.visible');
            cy.get('#_g2_2').should('not.be.visible');
            cy.get('#_g2_3').should('not.be.visible');
        });

        it('should expand when clicked', () => {
            cy.get('#groupbox-2').click();
            cy.get('#groupbox-2').should('have.class', 'group_open');
            cy.get('#_g2_1').should('be.visible');
        });
    });

    describe('Multiple Groups', () => {
        it('should have independent group states', () => {
            // Close group A
            cy.get('#groupbox-3a').click();
            cy.get('#groupbox-3a').should('have.class', 'group_closed');

            // Group B should still be open
            cy.get('#groupbox-3b').should('have.class', 'group_open');

            // Group C should still be open
            cy.get('#groupbox-3c').should('have.class', 'group_open');
        });

        it('should only toggle own group rows', () => {
            cy.get('#groupbox-3a').click(); // Close group A

            cy.get('#_g3_1').should('not.be.visible'); // Group A row hidden
            cy.get('#_g4_1').should('be.visible'); // Group B row visible
            cy.get('#_g5_1').should('be.visible'); // Group C row visible
        });
    });

    describe('API Methods', () => {
        it('should expose isOpen property', () => {
            cy.get('#groupbox-api').then($el => {
                expect($el[0].isOpen).to.be.true;
            });
        });

        it('should close when close() is called', () => {
            cy.get('#btn-close').click();
            cy.get('#groupbox-api').should('have.class', 'group_closed');
            cy.get('#_g6_1').should('not.be.visible');
        });

        it('should open when open() is called', () => {
            cy.get('#btn-close').click(); // First close
            cy.get('#btn-open').click(); // Then open
            cy.get('#groupbox-api').should('have.class', 'group_open');
            cy.get('#_g6_1').should('be.visible');
        });

        it('should toggle when toggle() is called', () => {
            cy.get('#groupbox-api').should('have.class', 'group_open');
            cy.get('#btn-toggle').click();
            cy.get('#groupbox-api').should('have.class', 'group_closed');
            cy.get('#btn-toggle').click();
            cy.get('#groupbox-api').should('have.class', 'group_open');
        });

        it('should update API result display', () => {
            cy.get('#btn-check').click();
            cy.get('#api-result').should('contain', 'isOpen property: true');

            cy.get('#btn-close').click();
            cy.get('#api-result').should('contain', 'isOpen: false');
        });
    });

    describe('localStorage Persistence', () => {
        it('should save state to localStorage', () => {
            cy.get('#groupbox-storage').click(); // Toggle

            cy.window().then(win => {
                const stored = win.localStorage.getItem('cma_grp_200_7');
                expect(stored).to.eq('closed');
            });
        });

        it('should clear localStorage when button clicked', () => {
            cy.get('#groupbox-storage').click(); // Toggle to save state
            cy.get('#btn-clear-storage').click();

            cy.window().then(win => {
                const stored = win.localStorage.getItem('cma_grp_200_7');
                expect(stored).to.be.null;
            });
        });
    });

    describe('data-group-row Attribute', () => {
        it('should toggle rows with data-group-row attribute', () => {
            cy.get('[data-group-row="8"]').should('have.length', 2);
            cy.get('[data-group-row="8"]').first().should('be.visible');

            cy.get('#groupbox-data').click();

            cy.get('[data-group-row="8"]').first().should('not.be.visible');
        });
    });

    describe('Backward Compatibility', () => {
        it('should expose grp_flip function globally', () => {
            cy.window().then(win => {
                expect(win.grp_flip).to.be.a('function');
            });
        });

        it('should toggle via grp_flip function', () => {
            cy.get('#groupbox-compat').should('have.class', 'group_open');

            cy.get('#btn-grp-flip').click();

            cy.get('#groupbox-compat').should('have.class', 'group_closed');
        });
    });

    describe('Visual Elements', () => {
        it('should display caption text', () => {
            cy.get('#groupbox-1 .groupbox-title').should('contain', 'Persoonlijke gegevens');
        });

        it('should have chevron indicator', () => {
            cy.get('#groupbox-1 .groupbox-chevron').should('exist');
        });

        it('should rotate chevron when collapsed', () => {
            cy.get('#groupbox-1').click();
            // The CSS transform should change when collapsed
            cy.get('#groupbox-1').should('have.class', 'group_closed');
        });
    });

    describe('Properties', () => {
        it('should expose groupId property', () => {
            cy.get('#groupbox-1').then($el => {
                expect($el[0].groupId).to.eq(1);
            });
        });

        it('should expose formId property', () => {
            cy.get('#groupbox-1').then($el => {
                expect($el[0].formId).to.eq(100);
            });
        });

        it('should expose caption property', () => {
            cy.get('#groupbox-1').then($el => {
                expect($el[0].caption).to.eq('Persoonlijke gegevens');
            });
        });
    });

    describe('Test Log', () => {
        it('should log successful registration', () => {
            cy.get('#test-log')
                .should('contain', 'cma-groupbox component is registered');
        });

        it('should log test results', () => {
            cy.get('#test-log')
                .should('contain', 'All tests initialized');
        });
    });
});
