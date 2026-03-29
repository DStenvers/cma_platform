/**
 * cma-groupbox Web Component Tests
 *
 * Tests for the cma-groupbox custom element - a collapsible group header for form sections.
 * Tests all attributes, methods, state persistence, and backward compatibility.
 */

describe('cma-groupbox Web Component', () => {
    beforeEach(() => {
        cy.loginAsAdmin();
        cy.visit('/main.php');
    });

    describe('Component Registration', () => {
        it('should be defined in customElements registry', () => {
            cy.window().then(win => {
                expect(win.customElements.get('cma-groupbox')).to.exist;
            });
        });

        it('should render as a custom element', () => {
            cy.document().then(doc => {
                const groupbox = doc.createElement('cma-groupbox');
                groupbox.setAttribute('group-id', '99');
                groupbox.setAttribute('caption', 'Test Group');
                doc.body.appendChild(groupbox);

                cy.get('cma-groupbox[group-id="99"]')
                    .should('exist');
            });
        });
    });

    describe('Attributes', () => {
        // Ensure component is defined before running attribute tests
        beforeEach(() => {
            cy.visit('/main.php');
            cy.window().then(win => {
                expect(win.customElements.get('cma-groupbox')).to.exist;
            });
        });

        describe('group-id attribute', () => {
            it('should accept group-id attribute', () => {
                cy.document().then(doc => {
                    const groupbox = doc.createElement('cma-groupbox');
                    groupbox.setAttribute('group-id', '5');
                    doc.body.appendChild(groupbox);

                    expect(groupbox.getAttribute('group-id')).to.eq('5');
                });
            });

            it('should default group-id to 0 if not set', () => {
                cy.document().then(doc => {
                    const groupbox = doc.createElement('cma-groupbox');
                    doc.body.appendChild(groupbox);

                    expect(groupbox._groupId).to.eq(0);
                });
            });
        });

        describe('form-id attribute', () => {
            it('should accept form-id attribute', () => {
                cy.document().then(doc => {
                    const groupbox = doc.createElement('cma-groupbox');
                    groupbox.setAttribute('form-id', '123');
                    doc.body.appendChild(groupbox);

                    expect(groupbox._formId).to.eq(123);
                });
            });

            it('should default form-id to 0 if not set', () => {
                cy.document().then(doc => {
                    const groupbox = doc.createElement('cma-groupbox');
                    doc.body.appendChild(groupbox);

                    expect(groupbox._formId).to.eq(0);
                });
            });
        });

        describe('caption attribute', () => {
            it('should render caption text', () => {
                cy.document().then(doc => {
                    const groupbox = doc.createElement('cma-groupbox');
                    groupbox.setAttribute('caption', 'Personal Details');
                    doc.body.appendChild(groupbox);

                    cy.get('cma-groupbox').last()
                        .find('.groupbox-title')
                        .should('contain', 'Personal Details');
                });
            });

            it('should escape HTML in caption', () => {
                cy.document().then(doc => {
                    const groupbox = doc.createElement('cma-groupbox');
                    groupbox.setAttribute('caption', '<script>alert("xss")</script>');
                    doc.body.appendChild(groupbox);

                    cy.get('cma-groupbox').last()
                        .find('.groupbox-title')
                        .should('not.contain.html', '<script>');
                });
            });

            it('should default to empty string if not set', () => {
                cy.document().then(doc => {
                    const groupbox = doc.createElement('cma-groupbox');
                    doc.body.appendChild(groupbox);

                    expect(groupbox._caption).to.eq('');
                });
            });
        });

        describe('collapsed attribute', () => {
            it('should be open by default (no collapsed attribute)', () => {
                cy.document().then(doc => {
                    const groupbox = doc.createElement('cma-groupbox');
                    groupbox.setAttribute('group-id', '100');
                    groupbox.setAttribute('form-id', '999');
                    doc.body.appendChild(groupbox);

                    expect(groupbox.isOpen).to.be.true;
                    cy.get('cma-groupbox[group-id="100"]')
                        .should('have.class', 'group_open');
                });
            });

            it('should start collapsed when attribute is present', () => {
                cy.window().then(win => {
                    // Clear any stored state
                    win.localStorage.removeItem('cma_grp_998_101');
                });

                cy.document().then(doc => {
                    const groupbox = doc.createElement('cma-groupbox');
                    groupbox.setAttribute('group-id', '101');
                    groupbox.setAttribute('form-id', '998');
                    groupbox.setAttribute('collapsed', '');
                    doc.body.appendChild(groupbox);

                    expect(groupbox.isOpen).to.be.false;
                    cy.get('cma-groupbox[group-id="101"]')
                        .should('have.class', 'group_closed');
                });
            });

            it('should respond to collapsed attribute changes', () => {
                cy.document().then(doc => {
                    const groupbox = doc.createElement('cma-groupbox');
                    groupbox.setAttribute('group-id', '102');
                    doc.body.appendChild(groupbox);

                    // Initially open
                    expect(groupbox.isOpen).to.be.true;

                    // Add collapsed attribute
                    groupbox.setAttribute('collapsed', '');
                    expect(groupbox._isOpen).to.be.false;

                    // Remove collapsed attribute
                    groupbox.removeAttribute('collapsed');
                    expect(groupbox._isOpen).to.be.true;
                });
            });
        });
    });

    describe('Properties and Methods', () => {
        // Ensure component is defined before running tests
        beforeEach(() => {
            cy.visit('/main.php');
            cy.window().then(win => {
                expect(win.customElements.get('cma-groupbox')).to.exist;
            });
        });

        describe('isOpen property', () => {
            it('should return current open state', () => {
                cy.document().then(doc => {
                    const groupbox = doc.createElement('cma-groupbox');
                    doc.body.appendChild(groupbox);

                    expect(groupbox.isOpen).to.be.true;
                });
            });

            it('should update state when set', () => {
                cy.document().then(doc => {
                    const groupbox = doc.createElement('cma-groupbox');
                    groupbox.setAttribute('group-id', '103');
                    doc.body.appendChild(groupbox);

                    groupbox.isOpen = false;
                    expect(groupbox.isOpen).to.be.false;

                    cy.get('cma-groupbox[group-id="103"]')
                        .should('have.class', 'group_closed');
                });
            });
        });

        describe('toggle() method', () => {
            it('should toggle open to closed', () => {
                cy.document().then(doc => {
                    const groupbox = doc.createElement('cma-groupbox');
                    groupbox.setAttribute('group-id', '104');
                    doc.body.appendChild(groupbox);

                    expect(groupbox.isOpen).to.be.true;
                    groupbox.toggle();
                    expect(groupbox.isOpen).to.be.false;
                });
            });

            it('should toggle closed to open', () => {
                cy.window().then(win => {
                    win.localStorage.removeItem('cma_grp_0_105');
                });

                cy.document().then(doc => {
                    const groupbox = doc.createElement('cma-groupbox');
                    groupbox.setAttribute('group-id', '105');
                    groupbox.setAttribute('collapsed', '');
                    doc.body.appendChild(groupbox);

                    expect(groupbox.isOpen).to.be.false;
                    groupbox.toggle();
                    expect(groupbox.isOpen).to.be.true;
                });
            });
        });

        describe('open() method', () => {
            it('should open a closed groupbox', () => {
                cy.window().then(win => {
                    win.localStorage.removeItem('cma_grp_0_106');
                });

                cy.document().then(doc => {
                    const groupbox = doc.createElement('cma-groupbox');
                    groupbox.setAttribute('group-id', '106');
                    groupbox.setAttribute('collapsed', '');
                    doc.body.appendChild(groupbox);

                    expect(groupbox.isOpen).to.be.false;
                    groupbox.open();
                    expect(groupbox.isOpen).to.be.true;
                });
            });

            it('should keep open groupbox open', () => {
                cy.document().then(doc => {
                    const groupbox = doc.createElement('cma-groupbox');
                    doc.body.appendChild(groupbox);

                    expect(groupbox.isOpen).to.be.true;
                    groupbox.open();
                    expect(groupbox.isOpen).to.be.true;
                });
            });
        });

        describe('close() method', () => {
            it('should close an open groupbox', () => {
                cy.document().then(doc => {
                    const groupbox = doc.createElement('cma-groupbox');
                    groupbox.setAttribute('group-id', '107');
                    doc.body.appendChild(groupbox);

                    expect(groupbox.isOpen).to.be.true;
                    groupbox.close();
                    expect(groupbox.isOpen).to.be.false;
                });
            });

            it('should keep closed groupbox closed', () => {
                cy.window().then(win => {
                    win.localStorage.removeItem('cma_grp_0_108');
                });

                cy.document().then(doc => {
                    const groupbox = doc.createElement('cma-groupbox');
                    groupbox.setAttribute('group-id', '108');
                    groupbox.setAttribute('collapsed', '');
                    doc.body.appendChild(groupbox);

                    expect(groupbox.isOpen).to.be.false;
                    groupbox.close();
                    expect(groupbox.isOpen).to.be.false;
                });
            });
        });
    });

    describe('Click Behavior', () => {
        beforeEach(() => {
            cy.visit('/main.php');
            cy.window().then(win => {
                expect(win.customElements.get('cma-groupbox')).to.exist;
            });
        });

        it('should toggle on click', () => {
            cy.document().then(doc => {
                const groupbox = doc.createElement('cma-groupbox');
                groupbox.setAttribute('group-id', '109');
                groupbox.setAttribute('caption', 'Click Test');
                doc.body.appendChild(groupbox);

                cy.get('cma-groupbox[group-id="109"]')
                    .should('have.class', 'group_open')
                    .click()
                    .should('have.class', 'group_closed')
                    .click()
                    .should('have.class', 'group_open');
            });
        });
    });

    describe('Row Visibility', () => {
        beforeEach(() => {
            cy.visit('/main.php');
            cy.window().then(win => {
                expect(win.customElements.get('cma-groupbox')).to.exist;
            });
        });

        it('should hide rows with matching data-group on collapse', () => {
            cy.document().then(doc => {
                // Create groupbox
                const groupbox = doc.createElement('cma-groupbox');
                groupbox.setAttribute('group-id', '110');
                groupbox.setAttribute('caption', 'Row Test');
                doc.body.appendChild(groupbox);

                // Create rows that belong to this group
                for (let i = 1; i <= 3; i++) {
                    const row = doc.createElement('div');
                    row.id = `_g110_${i}`;
                    row.textContent = `Row ${i}`;
                    doc.body.appendChild(row);
                }

                // Rows should be visible when open
                cy.get('#_g110_1').should('be.visible');
                cy.get('#_g110_2').should('be.visible');
                cy.get('#_g110_3').should('be.visible');

                // Close groupbox
                cy.get('cma-groupbox[group-id="110"]').click();

                // Rows should be hidden
                cy.get('#_g110_1').should('not.be.visible');
                cy.get('#_g110_2').should('not.be.visible');
                cy.get('#_g110_3').should('not.be.visible');
            });
        });

        it('should show rows on expand', () => {
            cy.window().then(win => {
                win.localStorage.removeItem('cma_grp_0_111');
            });

            cy.document().then(doc => {
                // Create rows FIRST (before groupbox, so they exist when connectedCallback runs)
                for (let i = 1; i <= 2; i++) {
                    const row = doc.createElement('div');
                    row.id = `_g111_${i}`;
                    row.textContent = `Row ${i}`;
                    doc.body.appendChild(row);
                }

                // Create groupbox (collapsed) - now rows already exist
                const groupbox = doc.createElement('cma-groupbox');
                groupbox.setAttribute('group-id', '111');
                groupbox.setAttribute('collapsed', '');
                doc.body.appendChild(groupbox);

                // Initially hidden (collapsed)
                cy.get('#_g111_1').should('not.be.visible');

                // Open groupbox
                cy.get('cma-groupbox[group-id="111"]').click();

                // Rows should now be visible
                cy.get('#_g111_1').should('be.visible');
                cy.get('#_g111_2').should('be.visible');
            });
        });

        it('should hide rows created AFTER groupbox (deferred initialization)', () => {
            // This tests the fix for timing issue where rows are parsed after the groupbox
            // The component defers _applyRowVisibility() to allow DOM to be fully parsed
            cy.window().then(win => {
                win.localStorage.removeItem('cma_grp_0_121');
            });

            cy.document().then(doc => {
                // Create collapsed groupbox FIRST
                const groupbox = doc.createElement('cma-groupbox');
                groupbox.setAttribute('group-id', '121');
                groupbox.setAttribute('collapsed', '');
                doc.body.appendChild(groupbox);

                // Create rows AFTER groupbox (simulating HTML parsing order)
                for (let i = 1; i <= 2; i++) {
                    const row = doc.createElement('div');
                    row.id = `_g121_${i}`;
                    row.textContent = `Row ${i}`;
                    doc.body.appendChild(row);
                }

                // Groupbox should have closed class immediately
                cy.get('cma-groupbox[group-id="121"]')
                    .should('have.class', 'group_closed');

                // Rows should be hidden after deferred initialization (requestAnimationFrame)
                cy.get('#_g121_1').should('not.be.visible');
                cy.get('#_g121_2').should('not.be.visible');
            });
        });
    });

    describe('State Persistence', () => {
        beforeEach(() => {
            cy.visit('/main.php');
            cy.window().then(win => {
                expect(win.customElements.get('cma-groupbox')).to.exist;
            });
        });

        it('should save state to localStorage', () => {
            cy.document().then(doc => {
                const groupbox = doc.createElement('cma-groupbox');
                groupbox.setAttribute('group-id', '112');
                groupbox.setAttribute('form-id', '500');
                doc.body.appendChild(groupbox);

                // Toggle to save state
                groupbox.toggle();

                cy.window().then(win => {
                    const stored = win.localStorage.getItem('cma_grp_500_112');
                    expect(stored).to.eq('closed');
                });
            });
        });

        it('should restore state from localStorage', () => {
            cy.window().then(win => {
                // Pre-set state
                win.localStorage.setItem('cma_grp_501_113', 'closed');
            });

            cy.document().then(doc => {
                const groupbox = doc.createElement('cma-groupbox');
                groupbox.setAttribute('group-id', '113');
                groupbox.setAttribute('form-id', '501');
                doc.body.appendChild(groupbox);

                // Should be closed based on stored state
                expect(groupbox.isOpen).to.be.false;
            });
        });

        it('should prefer localStorage over collapsed attribute', () => {
            cy.window().then(win => {
                // Pre-set state as open
                win.localStorage.setItem('cma_grp_502_114', 'open');
            });

            cy.document().then(doc => {
                const groupbox = doc.createElement('cma-groupbox');
                groupbox.setAttribute('group-id', '114');
                groupbox.setAttribute('form-id', '502');
                groupbox.setAttribute('collapsed', ''); // Attribute says closed
                doc.body.appendChild(groupbox);

                // Should be open based on stored state (takes precedence)
                expect(groupbox.isOpen).to.be.true;
            });
        });

        it('should use collapsed attribute if no stored state', () => {
            cy.window().then(win => {
                // Clear any stored state
                win.localStorage.removeItem('cma_grp_503_115');
            });

            cy.document().then(doc => {
                const groupbox = doc.createElement('cma-groupbox');
                groupbox.setAttribute('group-id', '115');
                groupbox.setAttribute('form-id', '503');
                groupbox.setAttribute('collapsed', '');
                doc.body.appendChild(groupbox);

                // Should use attribute (collapsed)
                expect(groupbox.isOpen).to.be.false;
            });
        });
    });

    describe('Styling', () => {
        beforeEach(() => {
            cy.visit('/main.php');
            cy.window().then(win => {
                expect(win.customElements.get('cma-groupbox')).to.exist;
            });
        });

        it('should have groupbox-title element', () => {
            cy.document().then(doc => {
                const groupbox = doc.createElement('cma-groupbox');
                groupbox.setAttribute('caption', 'Styled Group');
                doc.body.appendChild(groupbox);

                cy.get('cma-groupbox').last()
                    .find('.groupbox-title')
                    .should('exist');
            });
        });

        it('should have groupbox-chevron element', () => {
            cy.document().then(doc => {
                const groupbox = doc.createElement('cma-groupbox');
                doc.body.appendChild(groupbox);

                cy.get('cma-groupbox').last()
                    .find('.groupbox-chevron')
                    .should('exist');
            });
        });

        it('should have group_open class when open', () => {
            cy.document().then(doc => {
                const groupbox = doc.createElement('cma-groupbox');
                groupbox.setAttribute('group-id', '116');
                doc.body.appendChild(groupbox);

                cy.get('cma-groupbox[group-id="116"]')
                    .should('have.class', 'group_open')
                    .and('not.have.class', 'group_closed');
            });
        });

        it('should have group_closed class when closed', () => {
            cy.window().then(win => {
                win.localStorage.removeItem('cma_grp_0_117');
            });

            cy.document().then(doc => {
                const groupbox = doc.createElement('cma-groupbox');
                groupbox.setAttribute('group-id', '117');
                groupbox.setAttribute('collapsed', '');
                doc.body.appendChild(groupbox);

                cy.get('cma-groupbox[group-id="117"]')
                    .should('have.class', 'group_closed')
                    .and('not.have.class', 'group_open');
            });
        });
    });

    describe('Backward Compatibility', () => {
        beforeEach(() => {
            cy.visit('/main.php');
            cy.window().then(win => {
                expect(win.customElements.get('cma-groupbox')).to.exist;
            });
        });

        describe('grp_flip global function', () => {
            it('should toggle groupbox via grp_flip', () => {
                cy.document().then(doc => {
                    const groupbox = doc.createElement('cma-groupbox');
                    groupbox.setAttribute('group-id', '118');
                    doc.body.appendChild(groupbox);

                    cy.window().then(win => {
                        expect(groupbox.isOpen).to.be.true;
                        win.grp_flip(118);
                        expect(groupbox.isOpen).to.be.false;
                    });
                });
            });
        });

        describe('grp_set global function', () => {
            it('should set groupbox state via grp_set', () => {
                cy.document().then(doc => {
                    const groupbox = doc.createElement('cma-groupbox');
                    groupbox.setAttribute('group-id', '119');
                    doc.body.appendChild(groupbox);

                    cy.window().then(win => {
                        win.grp_set(119, 'none');
                        expect(groupbox.isOpen).to.be.false;

                        win.grp_set(119, '');
                        expect(groupbox.isOpen).to.be.true;
                    });
                });
            });
        });

        describe('grp_init global function', () => {
            it('should exist as no-op for web components', () => {
                cy.window().then(win => {
                    expect(win.grp_init).to.be.a('function');
                });
            });
        });

        describe('groupbox-end rows', () => {
            it('should hide groupbox-end rows when group is collapsed', () => {
                cy.document().then(doc => {
                    // Create a mock form table structure
                    const table = doc.createElement('table');
                    table.innerHTML = `
                        <tr id="_g120_1"><td>Row 1</td></tr>
                        <tr id="_g120_2"><td>Row 2</td></tr>
                        <tr class="groupbox-end" data-group-row="120"><td></td></tr>
                    `;
                    doc.body.appendChild(table);

                    const groupbox = doc.createElement('cma-groupbox');
                    groupbox.setAttribute('group-id', '120');
                    doc.body.appendChild(groupbox);

                    // Initially open - all rows visible
                    expect(groupbox.isOpen).to.be.true;
                    const endRow = doc.querySelector('[data-group-row="120"]');
                    expect(endRow.style.display).to.not.equal('none');

                    // Collapse - rows should be hidden
                    groupbox.close();
                    expect(doc.getElementById('_g120_1').style.display).to.equal('none');
                    expect(doc.getElementById('_g120_2').style.display).to.equal('none');
                    expect(endRow.style.display).to.equal('none');

                    // Expand - rows should be visible
                    groupbox.open();
                    expect(doc.getElementById('_g120_1').style.display).to.equal('');
                    expect(doc.getElementById('_g120_2').style.display).to.equal('');
                    expect(endRow.style.display).to.equal('');
                });
            });
        });
    });

    describe('Integration', () => {
        it('should work in actual forms', () => {
            // Open users form in tree mode (loads detail inline)
            cy.openFormTree('users');

            // Click first record to load detail
            cy.get('#listContent a[target="R"]', { timeout: 15000 }).first().click({ force: true });
            cy.get('.detail-content, .form-layout', { timeout: 10000 }).should('be.visible');

            // Check if any groupbox components exist in the form
            cy.get('body').then($body => {
                if ($body.find('cma-groupbox').length > 0) {
                    cy.get('cma-groupbox').first()
                        .should('be.visible')
                        .and('have.class', 'group_open');
                } else {
                    // No groupbox in users form - verify the component is registered
                    cy.window().then(win => {
                        expect(win.customElements.get('cma-groupbox')).to.exist;
                    });
                }
            });
        });
    });

    describe('No Shadow DOM', () => {
        beforeEach(() => {
            cy.visit('/main.php');
            cy.window().then(win => {
                expect(win.customElements.get('cma-groupbox')).to.exist;
            });
        });

        it('should NOT use shadow DOM (light DOM for styling)', () => {
            cy.document().then(doc => {
                const groupbox = doc.createElement('cma-groupbox');
                doc.body.appendChild(groupbox);

                // cma-groupbox uses light DOM, not shadow DOM
                expect(groupbox.shadowRoot).to.be.null;
            });
        });

        it('should render children in light DOM', () => {
            cy.document().then(doc => {
                const groupbox = doc.createElement('cma-groupbox');
                groupbox.setAttribute('caption', 'Light DOM Test');
                doc.body.appendChild(groupbox);

                // Children should be directly accessible
                cy.get('cma-groupbox').last()
                    .find('.groupbox-title')
                    .should('exist');
            });
        });
    });

    describe('Empty Groupbox Hiding', () => {
        beforeEach(() => {
            cy.visit('/main.php');
            cy.window().then(win => {
                expect(win.customElements.get('cma-groupbox')).to.exist;
            });
        });

        it('should hide empty groupbox when immediately followed by groupbox-end', () => {
            cy.document().then(doc => {
                // Create a table with empty groupbox structure
                const table = doc.createElement('table');
                table.className = 'form-table';
                table.innerHTML = `
                    <tr class="groupbox-row"><td colspan="99"><cma-groupbox group-id="300" caption="Empty Group"></cma-groupbox></td></tr>
                    <tr class="groupbox-end" data-group-row="300"><td colspan="99"></td></tr>
                `;
                doc.body.appendChild(table);

                // Empty groupbox row should be hidden via CSS :has() selector
                cy.get('tr.groupbox-row').first()
                    .should('not.be.visible');
            });
        });

        it('should hide groupbox-end for empty groupbox', () => {
            cy.document().then(doc => {
                const table = doc.createElement('table');
                table.className = 'form-table';
                table.innerHTML = `
                    <tr class="groupbox-row"><td colspan="99"><cma-groupbox group-id="301" caption="Empty Group"></cma-groupbox></td></tr>
                    <tr class="groupbox-end" data-group-row="301"><td colspan="99"></td></tr>
                `;
                doc.body.appendChild(table);

                // Empty groupbox-end row should also be hidden
                cy.get('tr.groupbox-end[data-group-row="301"]')
                    .should('not.be.visible');
            });
        });

        it('should show groupbox with content rows', () => {
            cy.document().then(doc => {
                const table = doc.createElement('table');
                table.className = 'form-table';
                table.innerHTML = `
                    <tr class="groupbox-row"><td colspan="99"><cma-groupbox group-id="302" caption="Has Content"></cma-groupbox></td></tr>
                    <tr id="_g302_1" data-group-row="302"><td>Content Row 1</td></tr>
                    <tr id="_g302_2" data-group-row="302"><td>Content Row 2</td></tr>
                    <tr class="groupbox-end" data-group-row="302"><td colspan="99"></td></tr>
                `;
                doc.body.appendChild(table);

                // Groupbox with content should be visible
                cy.get('tr.groupbox-row').last()
                    .should('be.visible');
            });
        });

        it('should correctly detect empty vs non-empty groups in mixed table', () => {
            cy.document().then(doc => {
                const table = doc.createElement('table');
                table.className = 'form-table';
                table.innerHTML = `
                    <tr class="groupbox-row" id="grp-empty"><td colspan="99"><cma-groupbox group-id="310" caption="Empty Group"></cma-groupbox></td></tr>
                    <tr class="groupbox-end" data-group-row="310"><td colspan="99"></td></tr>
                    <tr class="groupbox-row" id="grp-content"><td colspan="99"><cma-groupbox group-id="311" caption="Has Content"></cma-groupbox></td></tr>
                    <tr id="_g311_1" data-group-row="311"><td>Some Content</td></tr>
                    <tr class="groupbox-end" data-group-row="311"><td colspan="99"></td></tr>
                `;
                doc.body.appendChild(table);

                // Empty group should be hidden
                cy.get('#grp-empty').should('not.be.visible');

                // Group with content should be visible
                cy.get('#grp-content').should('be.visible');
            });
        });
    });

    describe('DOM Move Handling', () => {
        // Tests for fix: when groupbox is moved between containers,
        // connectedCallback fires twice which would add duplicate click handlers
        beforeEach(() => {
            cy.visit('/main.php');
            cy.window().then(win => {
                expect(win.customElements.get('cma-groupbox')).to.exist;
            });
        });

        it('should only toggle once when clicked after being moved in DOM', () => {
            cy.document().then(doc => {
                // Create a staging container (like main.js does)
                const staging = doc.createElement('div');
                staging.id = 'test-staging';
                doc.body.appendChild(staging);

                // Create groupbox in staging
                const groupbox = doc.createElement('cma-groupbox');
                groupbox.setAttribute('group-id', '200');
                groupbox.setAttribute('caption', 'DOM Move Test');
                staging.appendChild(groupbox);

                // connectedCallback fires here (first time)
                expect(groupbox.isOpen).to.be.true;
                expect(groupbox.classList.contains('group_open')).to.be.true;

                // Create target container
                const target = doc.createElement('div');
                target.id = 'test-target';
                doc.body.appendChild(target);

                // Move groupbox to target (simulates main.js staging -> contentArea move)
                // connectedCallback fires again (second time)
                target.appendChild(groupbox);

                // Click should toggle ONLY ONCE (not twice due to duplicate handlers)
                cy.get('cma-groupbox[group-id="200"]').click();
                cy.get('cma-groupbox[group-id="200"]')
                    .should('have.class', 'group_closed')
                    .and('not.have.class', 'group_open');

                // Click again should toggle back
                cy.get('cma-groupbox[group-id="200"]').click();
                cy.get('cma-groupbox[group-id="200"]')
                    .should('have.class', 'group_open')
                    .and('not.have.class', 'group_closed');
            });
        });

        it('should work correctly after multiple DOM moves', () => {
            cy.document().then(doc => {
                // Create multiple containers
                const container1 = doc.createElement('div');
                container1.id = 'container1';
                doc.body.appendChild(container1);

                const container2 = doc.createElement('div');
                container2.id = 'container2';
                doc.body.appendChild(container2);

                const container3 = doc.createElement('div');
                container3.id = 'container3';
                doc.body.appendChild(container3);

                // Create groupbox
                const groupbox = doc.createElement('cma-groupbox');
                groupbox.setAttribute('group-id', '201');
                groupbox.setAttribute('caption', 'Multi-Move Test');

                // Move through multiple containers (each triggers connectedCallback)
                container1.appendChild(groupbox);
                container2.appendChild(groupbox);
                container3.appendChild(groupbox);

                // Should still only have one click handler
                cy.get('cma-groupbox[group-id="201"]').click();
                cy.get('cma-groupbox[group-id="201"]')
                    .should('have.class', 'group_closed');
            });
        });
    });
});
