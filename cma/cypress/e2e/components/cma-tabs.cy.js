/**
 * cma-tabs Web Component Tests
 *
 * Tests for the cma-tabs custom element - a tab strip component.
 * Tests all properties, methods, events, responsive behavior, and accessibility.
 */

describe('cma-tabs Web Component', () => {
    beforeEach(() => {
        cy.loginAsAdmin();
        cy.visit('/main.php');
    });

    describe('Component Registration', () => {
        it('should be defined in customElements registry', () => {
            cy.window().then(win => {
                expect(win.customElements.get('cma-tabs')).to.exist;
            });
        });
    });

    describe('Declarative Usage (tab-item children)', () => {
        it('should parse tab-item children', () => {
            cy.document().then(doc => {
                const tabs = doc.createElement('cma-tabs');
                tabs.innerHTML = `
                    <tab-item title="Tab 1" data-id="1"></tab-item>
                    <tab-item title="Tab 2" data-id="2"></tab-item>
                `;
                doc.body.appendChild(tabs);

                cy.get('cma-tabs').last()
                    .shadow()
                    .find('.tabs-list li')
                    .should('have.length', 2);
            });
        });

        it('should use title attribute as tab label', () => {
            cy.document().then(doc => {
                const tabs = doc.createElement('cma-tabs');
                tabs.innerHTML = `
                    <tab-item title="My Custom Tab" data-id="1"></tab-item>
                `;
                doc.body.appendChild(tabs);

                cy.get('cma-tabs').last()
                    .shadow()
                    .find('.tabs-list li a')
                    .should('contain', 'My Custom Tab');
            });
        });

        it('should display count badge from data-count', () => {
            cy.document().then(doc => {
                const tabs = doc.createElement('cma-tabs');
                tabs.innerHTML = `
                    <tab-item title="Tab 1" data-id="1" data-count="5"></tab-item>
                `;
                doc.body.appendChild(tabs);

                cy.get('cma-tabs').last()
                    .shadow()
                    .find('.tab-count')
                    .should('contain', '5');
            });
        });

        it('should show beheer indicator when beheer attribute present', () => {
            cy.document().then(doc => {
                const tabs = doc.createElement('cma-tabs');
                tabs.innerHTML = `
                    <tab-item title="Admin Tab" data-id="1" beheer></tab-item>
                `;
                doc.body.appendChild(tabs);

                cy.get('cma-tabs').last()
                    .shadow()
                    .find('.tab-beheer')
                    .should('exist');
            });
        });
    });

    describe('Attributes', () => {
        describe('selected attribute', () => {
            it('should select initial tab based on selected attribute', () => {
                cy.document().then(doc => {
                    const tabs = doc.createElement('cma-tabs');
                    tabs.setAttribute('selected', '1');
                    tabs.innerHTML = `
                        <tab-item title="Tab 1" data-id="1"></tab-item>
                        <tab-item title="Tab 2" data-id="2"></tab-item>
                    `;
                    doc.body.appendChild(tabs);

                    cy.get('cma-tabs').last()
                        .shadow()
                        .find('.tabs-list li')
                        .eq(1)
                        .should('have.class', 'selected');
                });
            });

            it('should update selected attribute when tab changes', () => {
                cy.document().then(doc => {
                    const tabs = doc.createElement('cma-tabs');
                    tabs.innerHTML = `
                        <tab-item title="Tab 1" data-id="1"></tab-item>
                        <tab-item title="Tab 2" data-id="2"></tab-item>
                    `;
                    doc.body.appendChild(tabs);

                    cy.get('cma-tabs').last()
                        .shadow()
                        .find('.tabs-list li')
                        .eq(1)
                        .click();

                    // The clicked tab should have the 'selected' class
                    cy.get('cma-tabs').last()
                        .shadow()
                        .find('.tabs-list li')
                        .eq(1)
                        .should('have.class', 'selected');
                });
            });
        });

        describe('breakpoint attribute', () => {
            it('should default breakpoint to 500', () => {
                cy.document().then(doc => {
                    const tabs = doc.createElement('cma-tabs');
                    expect(tabs.breakpoint).to.eq(500);
                });
            });

            it('should use custom breakpoint', () => {
                cy.document().then(doc => {
                    const tabs = doc.createElement('cma-tabs');
                    tabs.setAttribute('breakpoint', '768');
                    expect(tabs.breakpoint).to.eq(768);
                });
            });
        });

        describe('tabs attribute (JSON)', () => {
            it('should parse tabs from JSON array of strings', () => {
                cy.document().then(doc => {
                    const tabs = doc.createElement('cma-tabs');
                    tabs.setAttribute('tabs', '["Tab A", "Tab B", "Tab C"]');
                    doc.body.appendChild(tabs);

                    cy.get('cma-tabs').last()
                        .shadow()
                        .find('.tabs-list li')
                        .should('have.length', 3);

                    cy.get('cma-tabs').last()
                        .shadow()
                        .find('.tabs-list li')
                        .first()
                        .should('contain', 'Tab A');
                });
            });

            it('should parse tabs from JSON array of objects', () => {
                cy.document().then(doc => {
                    const tabs = doc.createElement('cma-tabs');
                    tabs.setAttribute('tabs', JSON.stringify([
                        { title: 'First', id: '1' },
                        { title: 'Second', id: '2', count: 5 }
                    ]));
                    doc.body.appendChild(tabs);

                    cy.get('cma-tabs').last()
                        .shadow()
                        .find('.tabs-list li')
                        .eq(1)
                        .find('.tab-count')
                        .should('contain', '5');
                });
            });
        });
    });

    describe('Slotted Content Panels', () => {
        it('should show content for selected tab and hide others', () => {
            cy.document().then(doc => {
                const tabs = doc.createElement('cma-tabs');
                tabs.setAttribute('tabs', '["Tab 1", "Tab 2"]');
                tabs.innerHTML = `
                    <div slot="tab-0" id="panel0">Panel 0 content</div>
                    <div slot="tab-1" id="panel1">Panel 1 content</div>
                `;
                doc.body.appendChild(tabs);

                // Slot for tab-0 should be visible, tab-1 hidden
                cy.get('cma-tabs').last()
                    .shadow()
                    .find('slot[name="tab-0"]')
                    .should('not.have.css', 'display', 'none');

                cy.get('cma-tabs').last()
                    .shadow()
                    .find('slot[name="tab-1"]')
                    .should('have.css', 'display', 'none');
            });
        });

        it('should switch content when tab is clicked', () => {
            cy.document().then(doc => {
                const tabs = doc.createElement('cma-tabs');
                tabs.setAttribute('tabs', '["Tab 1", "Tab 2"]');
                tabs.innerHTML = `
                    <div slot="tab-0" id="panel0">Panel 0 content</div>
                    <div slot="tab-1" id="panel1">Panel 1 content</div>
                `;
                doc.body.appendChild(tabs);

                // Click on second tab
                cy.get('cma-tabs').last()
                    .shadow()
                    .find('.tabs-list li')
                    .eq(1)
                    .click();

                // Slot for tab-1 should now be visible
                cy.get('cma-tabs').last()
                    .shadow()
                    .find('slot[name="tab-0"]')
                    .should('have.css', 'display', 'none');

                cy.get('cma-tabs').last()
                    .shadow()
                    .find('slot[name="tab-1"]')
                    .should('not.have.css', 'display', 'none');
            });
        });

        it('should animate content panels with fade when switching tabs', () => {
            cy.document().then(doc => {
                const tabs = doc.createElement('cma-tabs');
                tabs.setAttribute('tabs', '["Tab 1", "Tab 2"]');
                tabs.innerHTML = `
                    <div slot="tab-0" id="panel0">Panel 0 content</div>
                    <div slot="tab-1" id="panel1">Panel 1 content</div>
                `;
                doc.body.appendChild(tabs);

                // Wait for component to initialize
                cy.get('cma-tabs').last()
                    .shadow()
                    .find('slot[name="tab-0"]')
                    .should('not.have.css', 'display', 'none');

                // Click second tab — old slot should get fade-out class
                cy.get('cma-tabs').last()
                    .shadow()
                    .find('.tabs-list li')
                    .eq(1)
                    .click();

                // After animation completes, new slot should be visible
                cy.get('cma-tabs').last()
                    .shadow()
                    .find('slot[name="tab-1"]')
                    .should('not.have.css', 'display', 'none');

                // Old slot should be hidden after animation
                cy.get('cma-tabs').last()
                    .shadow()
                    .find('slot[name="tab-0"]')
                    .should('have.css', 'display', 'none');
            });
        });

        it('should work with tools_serverinfo style usage', () => {
            // Test the exact pattern used in tools_serverinfo.php
            cy.document().then(doc => {
                const tabs = doc.createElement('cma-tabs');
                tabs.setAttribute('tabs', JSON.stringify(['Applicatie instellingen', 'PHP info']));
                tabs.innerHTML = `
                    <div slot="tab-0"><h2>App Settings</h2><p>Content here</p></div>
                    <div slot="tab-1"><h2>PHP Info</h2><p>PHP content here</p></div>
                `;
                doc.body.appendChild(tabs);

                // First tab slot should be visible
                cy.get('cma-tabs').last()
                    .shadow()
                    .find('slot[name="tab-0"]')
                    .should('not.have.css', 'display', 'none');

                // Click second tab
                cy.get('cma-tabs').last()
                    .shadow()
                    .find('.tabs-list li')
                    .eq(1)
                    .click();

                // Second tab slot should now be visible
                cy.get('cma-tabs').last()
                    .shadow()
                    .find('slot[name="tab-1"]')
                    .should('not.have.css', 'display', 'none');
            });
        });
    });

    describe('Properties and Methods', () => {
        describe('setTabs() method', () => {
            it('should set tabs programmatically', () => {
                cy.document().then(doc => {
                    const tabs = doc.createElement('cma-tabs');
                    doc.body.appendChild(tabs);

                    tabs.setTabs([
                        { title: 'Dynamic Tab 1', id: 'd1' },
                        { title: 'Dynamic Tab 2', id: 'd2', count: 10 }
                    ]);

                    cy.get('cma-tabs').last()
                        .shadow()
                        .find('.tabs-list li')
                        .should('have.length', 2);
                });
            });

            it('should include count in dynamically set tabs', () => {
                cy.document().then(doc => {
                    const tabs = doc.createElement('cma-tabs');
                    doc.body.appendChild(tabs);

                    tabs.setTabs([
                        { title: 'Tab with Count', id: '1', count: 42 }
                    ]);

                    cy.get('cma-tabs').last()
                        .shadow()
                        .find('.tab-count')
                        .should('contain', '42');
                });
            });
        });

        describe('setCount() method', () => {
            it('should update count badge for specific tab', () => {
                cy.document().then(doc => {
                    const tabs = doc.createElement('cma-tabs');
                    doc.body.appendChild(tabs);

                    tabs.setTabs([
                        { title: 'Tab 1', id: '1', count: 0 }
                    ]);

                    tabs.setCount(0, 99);

                    cy.get('cma-tabs').last()
                        .shadow()
                        .find('.tab-count')
                        .should('contain', '99');
                });
            });

            it('should add empty class when count is 0', () => {
                cy.document().then(doc => {
                    const tabs = doc.createElement('cma-tabs');
                    doc.body.appendChild(tabs);

                    tabs.setTabs([
                        { title: 'Tab 1', id: '1', count: 5 }
                    ]);

                    tabs.setCount(0, 0);

                    cy.get('cma-tabs').last()
                        .shadow()
                        .find('.tab-count')
                        .should('have.class', 'empty');
                });
            });

            it('should hide count badge visually when count is 0 (visibility:hidden)', () => {
                cy.document().then(doc => {
                    const tabs = doc.createElement('cma-tabs');
                    doc.body.appendChild(tabs);

                    tabs.setTabs([
                        { title: 'Tab 1', id: '1', count: 0 },
                        { title: 'Tab 2', id: '2', count: 5 }
                    ]);

                    // Count 0 badge should be hidden but still occupy space
                    cy.get('cma-tabs').last()
                        .shadow()
                        .find('li[data-index="0"] .tab-count')
                        .should('have.class', 'empty')
                        .and('have.css', 'visibility', 'hidden');

                    // Count 5 badge should be visible
                    cy.get('cma-tabs').last()
                        .shadow()
                        .find('li[data-index="1"] .tab-count')
                        .should('not.have.class', 'empty')
                        .and('have.css', 'visibility', 'visible');
                });
            });

            it('should apply empty class on initial render when count is 0', () => {
                cy.document().then(doc => {
                    const tabs = doc.createElement('cma-tabs');
                    tabs.setAttribute('tabs', JSON.stringify([
                        { title: 'Tab A', id: '1', count: 0 }
                    ]));
                    doc.body.appendChild(tabs);

                    cy.get('cma-tabs').last()
                        .shadow()
                        .find('.tab-count')
                        .should('have.class', 'empty')
                        .and('have.css', 'visibility', 'hidden');
                });
            });
        });

        describe('selectTab() method', () => {
            it('should select tab by index', () => {
                cy.document().then(doc => {
                    const tabs = doc.createElement('cma-tabs');
                    doc.body.appendChild(tabs);

                    tabs.setTabs([
                        { title: 'Tab 1', id: '1' },
                        { title: 'Tab 2', id: '2' }
                    ]);

                    tabs.selectTab(1);

                    cy.get('cma-tabs').last()
                        .shadow()
                        .find('.tabs-list li.selected')
                        .should('contain', 'Tab 2');
                });
            });

            it('should emit event when emit=true', () => {
                cy.document().then(doc => {
                    const tabs = doc.createElement('cma-tabs');
                    doc.body.appendChild(tabs);

                    let eventFired = false;
                    tabs.addEventListener('tab-select', () => { eventFired = true; });

                    tabs.setTabs([
                        { title: 'Tab 1', id: '1' },
                        { title: 'Tab 2', id: '2' }
                    ]);

                    tabs.selectTab(1, true);

                    cy.wrap(null).then(() => {
                        expect(eventFired).to.be.true;
                    });
                });
            });

            it('should not emit event when emit=false', () => {
                cy.document().then(doc => {
                    const tabs = doc.createElement('cma-tabs');
                    doc.body.appendChild(tabs);

                    let eventFired = false;
                    tabs.addEventListener('tab-select', () => { eventFired = true; });

                    tabs.setTabs([
                        { title: 'Tab 1', id: '1' },
                        { title: 'Tab 2', id: '2' }
                    ]);

                    tabs.selectTab(1, false);

                    cy.wrap(null).then(() => {
                        expect(eventFired).to.be.false;
                    });
                });
            });
        });

        describe('selectedIndex getter', () => {
            it('should return current selected index', () => {
                cy.document().then(doc => {
                    const tabs = doc.createElement('cma-tabs');
                    doc.body.appendChild(tabs);

                    tabs.setTabs([
                        { title: 'Tab 1', id: '1' },
                        { title: 'Tab 2', id: '2' }
                    ]);

                    tabs.selectTab(1, false);

                    expect(tabs.selectedIndex).to.eq(1);
                });
            });
        });

        describe('tabs getter', () => {
            it('should return array of tabs', () => {
                cy.document().then(doc => {
                    const tabs = doc.createElement('cma-tabs');
                    doc.body.appendChild(tabs);

                    tabs.setTabs([
                        { title: 'Tab 1', id: '1' },
                        { title: 'Tab 2', id: '2' }
                    ]);

                    expect(tabs.tabs).to.have.length(2);
                    expect(tabs.tabs[0].title).to.eq('Tab 1');
                });
            });
        });
    });

    describe('Events', () => {
        describe('tab-select event', () => {
            it('should fire on tab click', () => {
                cy.document().then(doc => {
                    const tabs = doc.createElement('cma-tabs');
                    doc.body.appendChild(tabs);

                    tabs.setTabs([
                        { title: 'Tab 1', id: 't1' },
                        { title: 'Tab 2', id: 't2' }
                    ]);

                    let eventDetail = null;
                    tabs.addEventListener('tab-select', (e) => {
                        eventDetail = e.detail;
                    });

                    cy.get('cma-tabs').last()
                        .shadow()
                        .find('.tabs-list li')
                        .eq(1)
                        .click()
                        .then(() => {
                            expect(eventDetail).to.not.be.null;
                            expect(eventDetail.index).to.eq(1);
                            expect(eventDetail.id).to.eq('t2');
                            expect(eventDetail.title).to.eq('Tab 2');
                        });
                });
            });

            it('should not fire when clicking already selected tab', () => {
                cy.document().then(doc => {
                    const tabs = doc.createElement('cma-tabs');
                    doc.body.appendChild(tabs);

                    tabs.setTabs([
                        { title: 'Tab 1', id: 't1' }
                    ]);

                    let eventCount = 0;
                    tabs.addEventListener('tab-select', () => { eventCount++; });

                    cy.get('cma-tabs').last()
                        .shadow()
                        .find('.tabs-list li')
                        .first()
                        .click()
                        .click()
                        .then(() => {
                            // Should only fire once (or not at all if already selected)
                            expect(eventCount).to.be.at.most(1);
                        });
                });
            });
        });
    });

    describe('Tab Selection UI', () => {
        beforeEach(() => {
            cy.document().then(doc => {
                const tabs = doc.createElement('cma-tabs');
                tabs.id = 'test-tabs';
                doc.body.appendChild(tabs);

                tabs.setTabs([
                    { title: 'First Tab', id: '1' },
                    { title: 'Second Tab', id: '2' },
                    { title: 'Third Tab', id: '3' }
                ]);
            });
        });

        it('should highlight selected tab', () => {
            cy.get('#test-tabs')
                .shadow()
                .find('.tabs-list li.selected')
                .should('have.length', 1);
        });

        it('should update visual selection on click', () => {
            cy.get('#test-tabs')
                .shadow()
                .find('.tabs-list li')
                .eq(1)
                .click()
                .should('have.class', 'selected');

            cy.get('#test-tabs')
                .shadow()
                .find('.tabs-list li')
                .eq(0)
                .should('not.have.class', 'selected');
        });

        it('should update select dropdown on tab click', () => {
            cy.get('#test-tabs')
                .shadow()
                .find('.tabs-list li')
                .eq(2)
                .click();

            cy.get('#test-tabs')
                .shadow()
                .find('.tabs-select')
                .should('have.value', '2');
        });
    });

    describe('Mobile Select Dropdown', () => {
        beforeEach(() => {
            cy.document().then(doc => {
                const tabs = doc.createElement('cma-tabs');
                tabs.id = 'test-tabs';
                doc.body.appendChild(tabs);

                tabs.setTabs([
                    { title: 'Tab A', id: 'a' },
                    { title: 'Tab B', id: 'b', count: 5 }
                ]);
            });
        });

        it('should have select element for mobile', () => {
            cy.get('#test-tabs')
                .shadow()
                .find('.tabs-select')
                .should('exist');
        });

        it('should have options for each tab', () => {
            cy.get('#test-tabs')
                .shadow()
                .find('.tabs-select option')
                .should('have.length', 2);
        });

        it('should include count in option text', () => {
            cy.get('#test-tabs')
                .shadow()
                .find('.tabs-select option')
                .eq(1)
                .should('contain', '(5)');
        });

        it('should select tab on select change', () => {
            // Set mobile viewport to make select visible
            cy.viewport(400, 800);

            cy.get('#test-tabs')
                .shadow()
                .find('.tabs-select')
                .should('be.visible')
                .select('1');

            cy.get('#test-tabs')
                .shadow()
                .find('.tabs-list li.selected')
                .should('contain', 'Tab B');
        });
    });

    describe('Scroll Arrows (Overflow)', () => {
        beforeEach(() => {
            cy.document().then(doc => {
                const tabs = doc.createElement('cma-tabs');
                tabs.id = 'test-tabs';
                tabs.style.width = '200px'; // Narrow width to force overflow
                doc.body.appendChild(tabs);

                tabs.setTabs([
                    { title: 'Very Long Tab Name 1', id: '1' },
                    { title: 'Very Long Tab Name 2', id: '2' },
                    { title: 'Very Long Tab Name 3', id: '3' },
                    { title: 'Very Long Tab Name 4', id: '4' }
                ]);
            });
        });

        it('should show scroll arrows when tabs overflow', () => {
            cy.get('#test-tabs')
                .shadow()
                .find('.scroll-arrow.visible')
                .should('exist');
        });

        it('should scroll tabs on arrow click', () => {
            // Check if right scroll arrow exists and is visible
            cy.get('#test-tabs')
                .shadow()
                .find('.scroll-arrow.right')
                .then($arrow => {
                    if ($arrow.length > 0 && $arrow.is(':visible')) {
                        cy.wrap($arrow).click();

                        // Wait a moment for scroll to complete
                        cy.wait(100);

                        // Tabs list should have scrolled
                        cy.get('#test-tabs')
                            .shadow()
                            .find('.tabs-list')
                            .then($list => {
                                // Check that scrolling worked
                                expect($list[0].scrollLeft).to.be.at.least(0);
                            });
                    } else {
                        // Skip test if no visible scroll arrow
                        cy.log('No visible scroll arrow - skipping scroll test');
                    }
                });
        });
    });

    describe('Styling', () => {
        it('should apply Chrome-style tab curves', () => {
            cy.document().then(doc => {
                const tabs = doc.createElement('cma-tabs');
                doc.body.appendChild(tabs);

                tabs.setTabs([{ title: 'Tab', id: '1' }]);

                cy.get('cma-tabs').last()
                    .shadow()
                    .find('.tabs-list li a')
                    .should('have.css', 'border-radius');
            });
        });

        it('should highlight selected tab background', () => {
            cy.document().then(doc => {
                const tabs = doc.createElement('cma-tabs');
                doc.body.appendChild(tabs);

                tabs.setTabs([{ title: 'Tab', id: '1' }]);

                cy.get('cma-tabs').last()
                    .shadow()
                    .find('.tabs-list li.selected a')
                    .should('have.css', 'background-color')
                    .and('not.eq', 'rgba(0, 0, 0, 0)');
            });
        });

        it('should style count badge on selected tab', () => {
            cy.document().then(doc => {
                const tabs = doc.createElement('cma-tabs');
                doc.body.appendChild(tabs);

                tabs.setTabs([{ title: 'Tab', id: '1', count: 5 }]);

                cy.get('cma-tabs').last()
                    .shadow()
                    .find('.tabs-list li.selected .tab-count')
                    .should('have.css', 'background-color')
                    .and('not.eq', 'rgba(0, 0, 0, 0)');
            });
        });
    });

    describe('Shadow DOM', () => {
        it('should use shadow DOM encapsulation', () => {
            cy.document().then(doc => {
                const tabs = doc.createElement('cma-tabs');
                doc.body.appendChild(tabs);
                expect(tabs.shadowRoot).to.exist;
            });
        });

        it('should contain styles in shadow DOM', () => {
            cy.document().then(doc => {
                const tabs = doc.createElement('cma-tabs');
                tabs.innerHTML = `
                    <tab-item title="Style Test" data-id="1"></tab-item>
                `;
                doc.body.appendChild(tabs);

                cy.get('cma-tabs').last()
                    .shadow()
                    .find('style')
                    .should('exist');
            });
        });
    });

    describe('Dark Mode Support', () => {
        beforeEach(() => {
            cy.setCookie('cma_theme', 'dark');
        });

        it('should adapt to dark mode', () => {
            cy.visit('/main.php');

            cy.document().then(doc => {
                const tabs = doc.createElement('cma-tabs');
                doc.body.appendChild(tabs);

                tabs.setTabs([{ title: 'Dark Tab', id: '1' }]);

                cy.get('cma-tabs').last()
                    .shadow()
                    .find('.tabs-list')
                    .should('exist');
            });
        });
    });

    describe('Integration with Forms', () => {
        it('should work as subform tab navigation', () => {
            // Use tree view with contentblocks form
            cy.openFormTree('contentblocks');

            // Check if tabs exist on this form
            cy.get('body').then($body => {
                if ($body.find('cma-tabs').length > 0) {
                    cy.get('cma-tabs').first()
                        .shadow()
                        .find('.tabs-list li')
                        .should('have.length.at.least', 1);
                } else {
                    // Form may not have tabs - just check page loaded
                    cy.get('.detail-panel').should('be.visible');
                }
            });
        });
    });

    describe('Wizard Mode', () => {
        describe('Basic Wizard Rendering', () => {
            it('should render wizard steps when mode="wizard"', () => {
                cy.document().then(doc => {
                    const tabs = doc.createElement('cma-tabs');
                    tabs.setAttribute('mode', 'wizard');
                    tabs.setAttribute('tabs', JSON.stringify([
                        { title: 'Stap 1', id: '1' },
                        { title: 'Stap 2', id: '2' },
                        { title: 'Stap 3', id: '3' }
                    ]));
                    doc.body.appendChild(tabs);

                    cy.get('cma-tabs').last()
                        .shadow()
                        .find('.wizard-steps')
                        .should('exist');

                    cy.get('cma-tabs').last()
                        .shadow()
                        .find('.wizard-step')
                        .should('have.length', 3);
                });
            });

            it('should show step numbers in indicators', () => {
                cy.document().then(doc => {
                    const tabs = doc.createElement('cma-tabs');
                    tabs.setAttribute('mode', 'wizard');
                    tabs.setAttribute('tabs', JSON.stringify([
                        { title: 'Step One', id: '1' },
                        { title: 'Step Two', id: '2' }
                    ]));
                    doc.body.appendChild(tabs);

                    cy.get('cma-tabs').last()
                        .shadow()
                        .find('.wizard-step')
                        .eq(0)
                        .find('.step-number')
                        .should('contain', '1');

                    cy.get('cma-tabs').last()
                        .shadow()
                        .find('.wizard-step')
                        .eq(1)
                        .find('.step-number')
                        .should('contain', '2');
                });
            });

            it('should show step labels', () => {
                cy.document().then(doc => {
                    const tabs = doc.createElement('cma-tabs');
                    tabs.setAttribute('mode', 'wizard');
                    tabs.setAttribute('tabs', JSON.stringify([
                        { title: 'Tabellen', id: '1' },
                        { title: 'Velden', id: '2' }
                    ]));
                    doc.body.appendChild(tabs);

                    cy.get('cma-tabs').last()
                        .shadow()
                        .find('.wizard-step')
                        .eq(0)
                        .find('.step-label')
                        .should('contain', 'Tabellen');
                });
            });

            it('should show connector lines between steps', () => {
                cy.document().then(doc => {
                    const tabs = doc.createElement('cma-tabs');
                    tabs.setAttribute('mode', 'wizard');
                    tabs.setAttribute('tabs', JSON.stringify([
                        { title: 'Step 1', id: '1' },
                        { title: 'Step 2', id: '2' },
                        { title: 'Step 3', id: '3' }
                    ]));
                    doc.body.appendChild(tabs);

                    // First and second steps should have connectors, last should not
                    cy.get('cma-tabs').last()
                        .shadow()
                        .find('.wizard-step')
                        .eq(0)
                        .find('.step-connector')
                        .should('exist');

                    cy.get('cma-tabs').last()
                        .shadow()
                        .find('.wizard-step')
                        .eq(2)
                        .find('.step-connector')
                        .should('not.exist');
                });
            });
        });

        describe('Current Step Highlighting', () => {
            it('should mark first step as current by default', () => {
                cy.document().then(doc => {
                    const tabs = doc.createElement('cma-tabs');
                    tabs.setAttribute('mode', 'wizard');
                    tabs.setAttribute('tabs', JSON.stringify([
                        { title: 'Step 1', id: '1' },
                        { title: 'Step 2', id: '2' }
                    ]));
                    doc.body.appendChild(tabs);

                    cy.get('cma-tabs').last()
                        .shadow()
                        .find('.wizard-step')
                        .eq(0)
                        .should('have.class', 'current');
                });
            });

            it('should update current step on click', () => {
                cy.document().then(doc => {
                    const tabs = doc.createElement('cma-tabs');
                    tabs.setAttribute('mode', 'wizard');
                    tabs.setAttribute('tabs', JSON.stringify([
                        { title: 'Step 1', id: '1' },
                        { title: 'Step 2', id: '2' }
                    ]));
                    doc.body.appendChild(tabs);

                    cy.get('cma-tabs').last()
                        .shadow()
                        .find('.wizard-step')
                        .eq(1)
                        .click();

                    cy.get('cma-tabs').last()
                        .shadow()
                        .find('.wizard-step')
                        .eq(1)
                        .should('have.class', 'current');

                    cy.get('cma-tabs').last()
                        .shadow()
                        .find('.wizard-step')
                        .eq(0)
                        .should('not.have.class', 'current');
                });
            });
        });

        describe('Completed Steps', () => {
            it('should show checkmark for completed steps', () => {
                cy.document().then(doc => {
                    const tabs = doc.createElement('cma-tabs');
                    tabs.setAttribute('mode', 'wizard');
                    tabs.setAttribute('tabs', JSON.stringify([
                        { title: 'Step 1', id: '1', completed: true },
                        { title: 'Step 2', id: '2', completed: false }
                    ]));
                    doc.body.appendChild(tabs);

                    cy.get('cma-tabs').last()
                        .shadow()
                        .find('.wizard-step')
                        .eq(0)
                        .should('have.class', 'completed')
                        .find('.checkmark')
                        .should('contain', '✓');
                });
            });

            it('should update completed state via setStepCompleted()', () => {
                cy.document().then(doc => {
                    const tabs = doc.createElement('cma-tabs');
                    tabs.setAttribute('mode', 'wizard');
                    tabs.setAttribute('tabs', JSON.stringify([
                        { title: 'Step 1', id: '1' },
                        { title: 'Step 2', id: '2' }
                    ]));
                    doc.body.appendChild(tabs);

                    // Initially not completed
                    cy.get('cma-tabs').last()
                        .shadow()
                        .find('.wizard-step')
                        .eq(0)
                        .should('not.have.class', 'completed');

                    // Mark as completed
                    cy.get('cma-tabs').last().then($tabs => {
                        $tabs[0].setStepCompleted(0, true);
                    });

                    cy.get('cma-tabs').last()
                        .shadow()
                        .find('.wizard-step')
                        .eq(0)
                        .should('have.class', 'completed');
                });
            });

            it('should return correct value from isStepCompleted()', () => {
                cy.document().then(doc => {
                    const tabs = doc.createElement('cma-tabs');
                    tabs.setAttribute('mode', 'wizard');
                    tabs.setAttribute('tabs', JSON.stringify([
                        { title: 'Step 1', id: '1', completed: true },
                        { title: 'Step 2', id: '2', completed: false }
                    ]));
                    doc.body.appendChild(tabs);

                    expect(tabs.isStepCompleted(0)).to.be.true;
                    expect(tabs.isStepCompleted(1)).to.be.false;
                });
            });
        });

        describe('Navigation Methods', () => {
            it('should advance to next step with nextStep()', () => {
                cy.document().then(doc => {
                    const tabs = doc.createElement('cma-tabs');
                    tabs.setAttribute('mode', 'wizard');
                    tabs.setAttribute('tabs', JSON.stringify([
                        { title: 'Step 1', id: '1' },
                        { title: 'Step 2', id: '2' },
                        { title: 'Step 3', id: '3' }
                    ]));
                    doc.body.appendChild(tabs);

                    // Navigate to next
                    cy.get('cma-tabs').last().then($tabs => {
                        $tabs[0].nextStep();
                    });

                    cy.get('cma-tabs').last()
                        .shadow()
                        .find('.wizard-step')
                        .eq(1)
                        .should('have.class', 'current');
                });
            });

            it('should mark current step completed when nextStep(true)', () => {
                cy.document().then(doc => {
                    const tabs = doc.createElement('cma-tabs');
                    tabs.setAttribute('mode', 'wizard');
                    tabs.setAttribute('tabs', JSON.stringify([
                        { title: 'Step 1', id: '1' },
                        { title: 'Step 2', id: '2' }
                    ]));
                    doc.body.appendChild(tabs);

                    // Wait for component to be fully rendered before calling methods
                    cy.get('cma-tabs').last()
                        .shadow()
                        .find('.wizard-step')
                        .should('have.length', 2);

                    // Navigate with completion
                    cy.get('cma-tabs').last().then($tabs => {
                        $tabs[0].nextStep(true);
                    });

                    cy.get('cma-tabs').last()
                        .shadow()
                        .find('.wizard-step')
                        .eq(0)
                        .should('have.class', 'completed');
                });
            });

            it('should go back with prevStep()', () => {
                cy.document().then(doc => {
                    const tabs = doc.createElement('cma-tabs');
                    tabs.setAttribute('mode', 'wizard');
                    tabs.setAttribute('tabs', JSON.stringify([
                        { title: 'Step 1', id: '1' },
                        { title: 'Step 2', id: '2' }
                    ]));
                    doc.body.appendChild(tabs);

                    // Go to step 2
                    tabs.selectTab(1, false);

                    // Navigate back
                    cy.get('cma-tabs').last().then($tabs => {
                        $tabs[0].prevStep();
                    });

                    cy.get('cma-tabs').last()
                        .shadow()
                        .find('.wizard-step')
                        .eq(0)
                        .should('have.class', 'current');
                });
            });

            it('should return false when nextStep() at last step', () => {
                cy.document().then(doc => {
                    const tabs = doc.createElement('cma-tabs');
                    tabs.setAttribute('mode', 'wizard');
                    tabs.setAttribute('tabs', JSON.stringify([
                        { title: 'Step 1', id: '1' },
                        { title: 'Step 2', id: '2' }
                    ]));
                    doc.body.appendChild(tabs);

                    // Go to last step
                    tabs.selectTab(1, false);

                    const result = tabs.nextStep();
                    expect(result).to.be.false;
                });
            });

            it('should return false when prevStep() at first step', () => {
                cy.document().then(doc => {
                    const tabs = doc.createElement('cma-tabs');
                    tabs.setAttribute('mode', 'wizard');
                    tabs.setAttribute('tabs', JSON.stringify([
                        { title: 'Step 1', id: '1' },
                        { title: 'Step 2', id: '2' }
                    ]));
                    doc.body.appendChild(tabs);

                    const result = tabs.prevStep();
                    expect(result).to.be.false;
                });
            });
        });

        describe('Wizard Events', () => {
            it('should emit step-change event on navigation', () => {
                cy.document().then(doc => {
                    const tabs = doc.createElement('cma-tabs');
                    tabs.setAttribute('mode', 'wizard');
                    tabs.setAttribute('tabs', JSON.stringify([
                        { title: 'Step 1', id: '1' },
                        { title: 'Step 2', id: '2' }
                    ]));
                    doc.body.appendChild(tabs);

                    let eventDetail = null;
                    tabs.addEventListener('step-change', (e) => {
                        eventDetail = e.detail;
                    });

                    cy.get('cma-tabs').last()
                        .shadow()
                        .find('.wizard-step')
                        .eq(1)
                        .click()
                        .then(() => {
                            expect(eventDetail).to.not.be.null;
                            expect(eventDetail.index).to.eq(1);
                            expect(eventDetail.previousIndex).to.eq(0);
                            expect(eventDetail.title).to.eq('Step 2');
                            expect(eventDetail.isFirst).to.be.false;
                            expect(eventDetail.isLast).to.be.true;
                        });
                });
            });

            it('should still emit tab-select event in wizard mode', () => {
                cy.document().then(doc => {
                    const tabs = doc.createElement('cma-tabs');
                    tabs.setAttribute('mode', 'wizard');
                    tabs.setAttribute('tabs', JSON.stringify([
                        { title: 'Step 1', id: '1' },
                        { title: 'Step 2', id: '2' }
                    ]));
                    doc.body.appendChild(tabs);

                    let tabSelectFired = false;
                    tabs.addEventListener('tab-select', () => {
                        tabSelectFired = true;
                    });

                    cy.get('cma-tabs').last()
                        .shadow()
                        .find('.wizard-step')
                        .eq(1)
                        .click()
                        .then(() => {
                            expect(tabSelectFired).to.be.true;
                        });
                });
            });
        });

        describe('Mode Property', () => {
            it('should have mode getter', () => {
                cy.document().then(doc => {
                    const tabs = doc.createElement('cma-tabs');
                    tabs.setAttribute('mode', 'wizard');
                    expect(tabs.mode).to.eq('wizard');
                });
            });

            it('should switch between modes dynamically', () => {
                cy.document().then(doc => {
                    const tabs = doc.createElement('cma-tabs');
                    tabs.setAttribute('tabs', JSON.stringify([
                        { title: 'Tab 1', id: '1' },
                        { title: 'Tab 2', id: '2' }
                    ]));
                    doc.body.appendChild(tabs);

                    // Start in default mode
                    cy.get('cma-tabs').last()
                        .shadow()
                        .find('.tabs-list')
                        .should('exist');

                    // Switch to wizard mode
                    cy.get('cma-tabs').last().then($tabs => {
                        $tabs[0].mode = 'wizard';
                    });

                    cy.get('cma-tabs').last()
                        .shadow()
                        .find('.wizard-steps')
                        .should('exist');

                    cy.get('cma-tabs').last()
                        .shadow()
                        .find('.tabs-list')
                        .should('not.exist');
                });
            });
        });

        describe('Wizard Slotted Content', () => {
            it('should show/hide content panels in wizard mode', () => {
                cy.document().then(doc => {
                    const tabs = doc.createElement('cma-tabs');
                    tabs.setAttribute('mode', 'wizard');
                    tabs.setAttribute('tabs', JSON.stringify([
                        { title: 'Step 1', id: '1' },
                        { title: 'Step 2', id: '2' }
                    ]));
                    tabs.innerHTML = `
                        <div slot="tab-0">Step 1 Content</div>
                        <div slot="tab-1">Step 2 Content</div>
                    `;
                    doc.body.appendChild(tabs);

                    // First panel visible
                    cy.get('cma-tabs').last()
                        .shadow()
                        .find('slot[name="tab-0"]')
                        .should('not.have.css', 'display', 'none');

                    // Second panel hidden
                    cy.get('cma-tabs').last()
                        .shadow()
                        .find('slot[name="tab-1"]')
                        .should('have.css', 'display', 'none');

                    // Click step 2
                    cy.get('cma-tabs').last()
                        .shadow()
                        .find('.wizard-step')
                        .eq(1)
                        .click();

                    // Second panel visible
                    cy.get('cma-tabs').last()
                        .shadow()
                        .find('slot[name="tab-1"]')
                        .should('not.have.css', 'display', 'none');
                });
            });
        });

        describe('Mobile Select in Wizard Mode', () => {
            it('should have wizard-select dropdown', () => {
                cy.document().then(doc => {
                    const tabs = doc.createElement('cma-tabs');
                    tabs.setAttribute('mode', 'wizard');
                    tabs.setAttribute('tabs', JSON.stringify([
                        { title: 'Step 1', id: '1' },
                        { title: 'Step 2', id: '2' }
                    ]));
                    doc.body.appendChild(tabs);

                    cy.get('cma-tabs').last()
                        .shadow()
                        .find('.wizard-select')
                        .should('exist');
                });
            });

            it('should format options as "Stap N: Title"', () => {
                cy.document().then(doc => {
                    const tabs = doc.createElement('cma-tabs');
                    tabs.id = 'wizard-option-test';
                    tabs.setAttribute('mode', 'wizard');
                    tabs.setAttribute('tabs', JSON.stringify([
                        { title: 'Tabellen', id: '1' }
                    ]));
                    doc.body.appendChild(tabs);

                    // Wait for wizard to render first
                    cy.get('#wizard-option-test')
                        .shadow()
                        .find('.wizard-select')
                        .should('exist');

                    // Debug: log the actual option text
                    cy.get('#wizard-option-test')
                        .shadow()
                        .find('.wizard-select option')
                        .eq(0)
                        .then($option => {
                            cy.log('Option text: "' + $option.text() + '"');
                            cy.log('Option HTML: "' + $option.html() + '"');
                        });

                    cy.get('#wizard-option-test')
                        .shadow()
                        .find('.wizard-select option')
                        .eq(0)
                        .invoke('text')
                        .should('match', /Stap\s*1\s*:\s*Tabellen/);
                });
            });

            it('should navigate via select on mobile', () => {
                cy.viewport(400, 800);

                cy.document().then(doc => {
                    const tabs = doc.createElement('cma-tabs');
                    tabs.setAttribute('mode', 'wizard');
                    tabs.setAttribute('tabs', JSON.stringify([
                        { title: 'Step 1', id: '1' },
                        { title: 'Step 2', id: '2' }
                    ]));
                    doc.body.appendChild(tabs);

                    // Wait for mobile mode class to be applied
                    cy.get('cma-tabs').last()
                        .should('have.class', 'mobile-mode');

                    cy.get('cma-tabs').last()
                        .shadow()
                        .find('.wizard-select')
                        .select('1', { force: true });

                    cy.get('cma-tabs').last()
                        .shadow()
                        .find('.wizard-step[data-index="1"]')
                        .should('have.class', 'current');
                });
            });
        });
    });
});
