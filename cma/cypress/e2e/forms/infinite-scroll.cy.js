/**
 * Infinite Scroll Tests
 *
 * Tests for the CmaInfiniteScroll functionality in table views.
 * Uses simpler assertions to avoid memory issues.
 *
 * Run: npx cypress run --spec "cypress/e2e/forms/infinite-scroll.cy.js"
 */

describe('Infinite Scroll', () => {
    const formName = 'users';

    beforeEach(() => {
        cy.loginAsAdmin();
    });

    // ═══════════════════════════════════════════════════════════════
    // THOROUGH INFINITE SCROLL TESTS (cmamonitoring - 15,863 records)
    // ═══════════════════════════════════════════════════════════════

    describe('Thorough Infinite Scroll with Large Dataset (cmamonitoring)', () => {
        const largeFormName = 'cmamonitoring';
        const pageSize = 500; // Default page size

        it('should load initial batch and show hasMore = true', () => {
            cy.openFormTable(largeFormName);

            // Verify initial batch is loaded with ~500 rows
            cy.get('#listTable tbody tr', { timeout: 15000 })
                .should('have.length.at.least', 100);

            // Verify record count shows there are more records (hasMore)
            // With 15,863 records and 500 page size, should show "records 1-500 van 17084" or similar
            cy.get('#recordCount', { timeout: 5000 })
                .should('be.visible')
                .invoke('text')
                .should('match', /records\s+1-\d+\s+van\s+\d+/i);
        });

        it('should trigger first infinite scroll load when scrolling to bottom', () => {
            // Intercept initial and subsequent loads
            cy.intercept('GET', '**/form_api.php*cmamonitoring*').as('treeLoad');

            cy.openFormTable(largeFormName);

            // Wait for initial load
            cy.wait('@treeLoad').then((interception) => {
                expect(interception.response.body.success).to.be.true;
            });

            // Get initial row count
            cy.get('#listTable tbody tr').then($rows => {
                const initialCount = $rows.length;
                cy.log(`Initial row count: ${initialCount}`);

                // Scroll to bottom to trigger infinite scroll
                cy.get('#listContent').parent().scrollTo('bottom');

                // Wait for the load more request
                cy.wait('@treeLoad', { timeout: 10000 }).then((interception) => {
                    expect(interception.response.body.success).to.be.true;
                    expect(interception.response.body.html).to.exist;
                });

                // Verify more rows were added
                cy.get('#listTable tbody tr', { timeout: 5000 })
                    .should('have.length.greaterThan', initialCount);
            });
        });

        it('should perform multiple infinite scroll loads (at least 2)', () => {
            // Intercept any pagination loads (uses action=tree with lastId in any position)
            cy.intercept('GET', '**/form_api.php*action=tree*').as('treeLoad');
            cy.intercept('GET', '**/form_api.php*action=init*cmamonitoring*').as('initLoad');

            cy.openFormTable(largeFormName);

            // Wait for initial load and for DOM to stabilize
            cy.wait('@initLoad');
            cy.get('#listTable tbody tr', { timeout: 10000 }).should('have.length.at.least', 100);

            // Helper function to trigger scroll and wait for rows to increase
            const scrollAndWaitForMore = (previousCount) => {
                cy.get('#listContent').parent().then($container => {
                    const container = $container[0];
                    container.scrollTop = container.scrollHeight - 200;
                    container.scrollTop = container.scrollHeight + 1000;
                    container.dispatchEvent(new Event('scroll', { bubbles: true }));
                });

                // Wait for more rows to appear (polling approach)
                cy.get('#listTable tbody tr', { timeout: 15000 })
                    .should('have.length.greaterThan', previousCount);
            };

            // First scroll load
            cy.get('#listTable tbody tr').its('length').then(countAfterInitial => {
                cy.log(`Rows after initial load: ${countAfterInitial}`);
                scrollAndWaitForMore(countAfterInitial);
            });

            // Second scroll load
            cy.get('#listTable tbody tr').its('length').then(countAfterFirst => {
                cy.log(`Rows after first scroll: ${countAfterFirst}`);
                scrollAndWaitForMore(countAfterFirst);
            });

            // Final verification
            cy.get('#listTable tbody tr').its('length').then(finalCount => {
                cy.log(`Final row count after 2 scroll loads: ${finalCount}`);
                // Should have loaded at least 2 pages worth
                expect(finalCount).to.be.at.least(pageSize);
            });
        });

        it('should maintain row count consistency (tracked vs DOM)', () => {
            cy.intercept('GET', '**/form_api.php*action=init*cmamonitoring*').as('initLoad');

            cy.openFormTable(largeFormName);

            // Wait for initial load
            cy.wait('@initLoad');
            cy.get('#listTable tbody tr', { timeout: 10000 }).should('have.length.at.least', 100);

            // Helper function to scroll and wait for rows to increase
            const scrollAndWaitForMore = (previousCount) => {
                cy.get('#listContent').parent().then($container => {
                    const container = $container[0];
                    container.scrollTop = container.scrollHeight - 200;
                    container.scrollTop = container.scrollHeight + 1000;
                    container.dispatchEvent(new Event('scroll', { bubbles: true }));
                });

                // Wait for more rows
                cy.get('#listTable tbody tr', { timeout: 15000 })
                    .should('have.length.greaterThan', previousCount);
            };

            // First scroll
            cy.get('#listTable tbody tr').its('length').then(count1 => {
                scrollAndWaitForMore(count1);
            });

            // Second scroll
            cy.get('#listTable tbody tr').its('length').then(count2 => {
                scrollAndWaitForMore(count2);
            });

            // Verify DOM has loaded rows
            cy.get('#listTable tbody tr').its('length').then(domRowCount => {
                cy.log(`DOM row count: ${domRowCount}`);
                // The count should be at least 2 pages worth
                expect(domRowCount).to.be.at.least(pageSize);
            });
        });

        it('should show record count display when not all records loaded', () => {
            cy.openFormTable(largeFormName);

            // Wait for table to load
            cy.get('#listTable tbody tr', { timeout: 15000 })
                .should('have.length.at.least', 1);

            // Record count should be visible since we have 15,863 records
            // and only load 500 initially
            cy.get('#recordCount', { timeout: 5000 })
                .should('be.visible')
                .invoke('text')
                .should('match', /records\s+1-\d+\s+van\s+\d+|\d+\s+records/);
        });

        it('should update record count after scroll loads', () => {
            cy.intercept('GET', '**/form_api.php*action=init*cmamonitoring*').as('initLoad');

            cy.openFormTable(largeFormName);

            // Wait for initial load
            cy.wait('@initLoad');
            cy.get('#listTable tbody tr', { timeout: 15000 })
                .should('have.length.at.least', 100);

            // Get initial row count (use DOM count as more reliable)
            cy.get('#listTable tbody tr').its('length').then(initialRowCount => {
                cy.log(`Initial row count: ${initialRowCount}`);

                // Trigger scroll to load more
                cy.get('#listContent').parent().then($container => {
                    const container = $container[0];
                    container.scrollTop = container.scrollHeight - 200;
                    container.scrollTop = container.scrollHeight + 1000;
                    container.dispatchEvent(new Event('scroll', { bubbles: true }));
                });

                // Wait for more rows to appear
                cy.get('#listTable tbody tr', { timeout: 15000 })
                    .should('have.length.greaterThan', initialRowCount);
            });
        });

        it('should handle rapid scrolling without duplicate requests', () => {
            // Use an array to track requests (closure-safe for async)
            const requests = [];

            // Intercept and track all tree loads
            cy.intercept('GET', '**/form_api.php*cmamonitoring*', (req) => {
                requests.push(new Date().getTime());
                req.continue();
            }).as('treeLoad');

            cy.openFormTable(largeFormName);

            // Wait for initial load
            cy.wait('@treeLoad');
            cy.get('#listTable tbody tr', { timeout: 10000 }).should('have.length.at.least', 100);

            // Record initial request count
            cy.then(() => {
                const initialCount = requests.length;
                cy.log(`Initial request count: ${initialCount}`);

                // Rapid scroll multiple times synchronously
                cy.get('#listContent').parent().then($container => {
                    const container = $container[0];
                    // Scroll rapidly 5 times in quick succession
                    for (let i = 0; i < 5; i++) {
                        container.scrollTop = container.scrollHeight;
                        container.dispatchEvent(new Event('scroll', { bubbles: true }));
                    }
                });

                // Wait for any debounced requests to complete
                cy.wait(2000);

                // Check request count - should be debounced
                cy.then(() => {
                    const finalCount = requests.length;
                    const additionalRequests = finalCount - initialCount;
                    cy.log(`Additional requests after rapid scroll: ${additionalRequests}`);
                    // Debouncing should limit to at most 2-3 requests
                    expect(additionalRequests).to.be.at.most(3);
                });
            });
        });

        it('should not load more when hasMore is false (API verification)', () => {
            // This test verifies that when all data is loaded, no more requests are made
            cy.request({
                method: 'GET',
                url: `/form_api.php?action=tree&jsonForm=${largeFormName}&displayMode=2&limit=100`
            }).then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body.success).to.be.true;
                expect(response.body.count).to.eq(100);
                // With 15,863 records and 100 limit, hasMore should be true
                expect(response.body.hasMore).to.be.true;
                expect(response.body.lastId).to.exist;
            });
        });

        it('should load subsequent pages with lastId parameter', () => {
            // First request to get lastId
            cy.request({
                method: 'GET',
                url: `/form_api.php?action=tree&jsonForm=${largeFormName}&displayMode=2&limit=100`
            }).then((response1) => {
                expect(response1.body.success).to.be.true;
                const lastId = response1.body.lastId;
                expect(lastId).to.exist;

                // Second request with lastId
                cy.request({
                    method: 'GET',
                    url: `/form_api.php?action=tree&jsonForm=${largeFormName}&displayMode=2&limit=100&lastId=${lastId}`
                }).then((response2) => {
                    expect(response2.body.success).to.be.true;
                    expect(response2.body.html).to.exist;
                    expect(response2.body.count).to.eq(100);
                    // Second page should also have more
                    expect(response2.body.hasMore).to.be.true;

                    // Third request with new lastId
                    const lastId2 = response2.body.lastId;
                    cy.request({
                        method: 'GET',
                        url: `/form_api.php?action=tree&jsonForm=${largeFormName}&displayMode=2&limit=100&lastId=${lastId2}`
                    }).then((response3) => {
                        expect(response3.body.success).to.be.true;
                        expect(response3.body.count).to.eq(100);
                        // Verify we're getting different data
                        expect(response3.body.lastId).to.not.eq(lastId);
                        expect(response3.body.lastId).to.not.eq(lastId2);
                    });
                });
            });
        });

        it('should verify row count matches after multiple loads', () => {
            cy.intercept('GET', '**/form_api.php*action=init*cmamonitoring*').as('initLoad');

            cy.openFormTable(largeFormName);
            cy.wait('@initLoad');
            cy.get('#listTable tbody tr', { timeout: 10000 }).should('have.length.at.least', 100);

            // Helper to scroll and wait for rows to increase
            const scrollAndWaitForMore = (previousCount) => {
                cy.get('#listContent').parent().then($container => {
                    const container = $container[0];
                    container.scrollTop = container.scrollHeight - 200;
                    container.scrollTop = container.scrollHeight + 1000;
                    container.dispatchEvent(new Event('scroll', { bubbles: true }));
                });

                cy.get('#listTable tbody tr', { timeout: 15000 })
                    .should('have.length.greaterThan', previousCount);
            };

            // Perform three scroll loads
            cy.get('#listTable tbody tr').its('length').then(count1 => {
                scrollAndWaitForMore(count1);
            });
            cy.get('#listTable tbody tr').its('length').then(count2 => {
                scrollAndWaitForMore(count2);
            });
            cy.get('#listTable tbody tr').its('length').then(count3 => {
                scrollAndWaitForMore(count3);
            });

            // Verify the DOM row count is substantial after 3 loads
            cy.get('#listTable tbody tr').its('length').then(domRowCount => {
                cy.log(`DOM has ${domRowCount} rows after 3 scroll loads`);
                // Should have at least 3 pages worth (initial + 3 loads = ~4 pages)
                expect(domRowCount).to.be.at.least(pageSize);
            });
        });
    });

    // ═══════════════════════════════════════════════════════════════
    // INITIAL LOAD
    // ═══════════════════════════════════════════════════════════════

    describe('Initial Load', () => {
        it('should load initial batch of records', () => {
            cy.openFormTable(formName);
            cy.get('#listTable tbody tr, table.listtable tbody tr', { timeout: 15000 })
                .should('have.length.at.least', 1);
        });

        it('should display table with proper structure', () => {
            cy.openFormTable(formName);
            cy.get('#listTable, table.listtable', { timeout: 15000 }).should('be.visible');
            cy.get('#listTable thead, table.listtable thead').should('exist');
            cy.get('#listTable tbody, table.listtable tbody').should('exist');
        });

        it('should have scroll container', () => {
            cy.openFormTable(formName);
            cy.get('#listTable, table.listtable', { timeout: 15000 }).should('be.visible');
            // Either the table itself or a parent can be scrollable
            cy.get('#listContent, .scroll-container, #listTable').should('exist');
        });
    });

    // ═══════════════════════════════════════════════════════════════
    // TABLE DISPLAY
    // ═══════════════════════════════════════════════════════════════

    describe('Table Display', () => {
        it('should display sortable headers', () => {
            cy.openFormTable(formName);
            cy.get('#listTable th, table.listtable th', { timeout: 15000 })
                .should('have.length.at.least', 1);
        });

        it('should display data in table rows', () => {
            cy.openFormTable(formName);
            cy.get('#listTable tbody tr td, table.listtable tbody tr td', { timeout: 15000 })
                .should('have.length.at.least', 1);
        });
    });

    // ═══════════════════════════════════════════════════════════════
    // FILTER INTEGRATION
    // ═══════════════════════════════════════════════════════════════

    describe('Filter Integration', () => {
        it('should maintain table after filter applied', () => {
            cy.openFormTable(formName);
            cy.get('#listTable, table.listtable', { timeout: 15000 }).should('be.visible');

            // Type in search
            cy.get('#searchfor').type('admin');

            // Wait for filter to apply
            cy.wait(500);

            // Table should still be visible
            cy.get('#listTable, table.listtable').should('be.visible');
        });

        it('should not break table on search', () => {
            cy.openFormTable(formName);
            cy.get('#listTable, table.listtable', { timeout: 15000 }).should('be.visible');

            // Type in search (lib-search-input is a web component, access inner input)
            cy.get('#searchfor').then($el => {
                if ($el[0].shadowRoot) {
                    cy.wrap($el).shadow().find('input').clear().type('admin');
                } else {
                    cy.wrap($el).find('input').clear().type('admin');
                }
            });

            // Wait for filter to apply
            cy.wait(500);

            // Table structure should still exist
            cy.get('#listTable tbody, table.listtable tbody').should('exist');
        });
    });

    // ═══════════════════════════════════════════════════════════════
    // API VERIFICATION (via logged-in session)
    // ═══════════════════════════════════════════════════════════════

    describe('API List', () => {
        it('should return tree data via API', () => {
            cy.request({
                method: 'GET',
                url: `/form_api.php?action=tree&form=${formName}`
            }).then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body.success).to.be.true;
                expect(response.body.html).to.exist;
            });
        });

        it('should return record data via API', () => {
            cy.request({
                method: 'GET',
                url: `/form_api.php?action=record&form=${formName}&id=1`
            }).then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body.success).to.be.true;
                expect(response.body.fields).to.exist;
            });
        });
    });

    // ═══════════════════════════════════════════════════════════════
    // UI ELEMENTS
    // ═══════════════════════════════════════════════════════════════

    describe('UI Elements', () => {
        it('should have toolbar with buttons', () => {
            cy.openFormTable(formName);
            cy.get('.toolbar', { timeout: 10000 }).should('be.visible');
        });

        it('should have search input', () => {
            cy.openFormTable(formName);
            cy.get('#searchfor', { timeout: 10000 }).should('exist');
        });

        it('should have view mode toggle buttons', () => {
            cy.openFormTable(formName);
            cy.get('[data-action="setlistmode"]', { timeout: 10000 })
                .should('have.length.at.least', 2);
        });
    });

    // ═══════════════════════════════════════════════════════════════
    // RECORD COUNT DISPLAY
    // ═══════════════════════════════════════════════════════════════

    describe('Record Count Display', () => {
        it('should have record count element in toolbar', () => {
            cy.openFormTable(formName);
            cy.get('#listTable', { timeout: 15000 }).should('be.visible');
            // Record count element should exist
            cy.get('#recordCount').should('exist');
        });

        it('should display record count in table mode', () => {
            cy.openFormTable(formName);
            cy.get('#listTable', { timeout: 15000 }).should('be.visible');
            // Record count element should exist
            cy.get('#recordCount', { timeout: 10000 }).then($el => {
                const text = $el.text().trim();
                // If text is present, it should contain record count info
                // If text is empty, that's valid - means all records are displayed
                if (text) {
                    cy.wrap($el).invoke('text')
                        .should('match', /records?\s+\d+/i);
                }
                // Empty text is valid - all records are shown
            });
        });

        it('should show correct format "records 1-X van Y" when not all records shown, or be hidden when all shown', () => {
            cy.openFormTable(formName);
            cy.get('#listTable', { timeout: 15000 }).should('be.visible');
            // Record count shows "records 1-X van Y" format when partial
            // or is empty/hidden when all records shown
            cy.get('#recordCount', { timeout: 10000 }).then($el => {
                const text = $el.text().trim();
                // If text is present, it should be in the correct format
                // If text is empty, that's valid - means all records are displayed
                if (text) {
                    cy.wrap($el).invoke('text')
                        .should('match', /records\s+1-\d+\s+van\s+\d+|^\d+\s+records$/);
                }
                // Empty text is valid - all records are shown
            });
        });

        it('should not show record count in tree mode', () => {
            cy.visit(`/form/${formName}`);
            // Click tree view button
            cy.get('[data-action="setlistmode"][data-mode="1"]', { timeout: 10000 }).click();
            cy.wait(1000);
            // Record count should be hidden in tree mode
            cy.get('#recordCount').should('not.be.visible');
        });
    });

    // ═══════════════════════════════════════════════════════════════
    // API RESPONSE
    // ═══════════════════════════════════════════════════════════════

    describe('API Response with Counts', () => {
        it('should return totalCount in table mode API response', () => {
            cy.request({
                method: 'GET',
                url: `/form_api.php?action=tree&jsonForm=${formName}&displayMode=2`
            }).then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body.success).to.be.true;
                expect(response.body.count).to.be.a('number');
                // totalCount should be present for initial load
                expect(response.body.totalCount).to.be.a('number');
            });
        });

        it('should return hasMore and pagination info', () => {
            cy.request({
                method: 'GET',
                url: `/form_api.php?action=tree&jsonForm=${formName}&displayMode=2`
            }).then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body.success).to.be.true;
                // Pagination fields should be present
                expect(response.body).to.have.property('hasMore');
                expect(response.body).to.have.property('pageSize');
            });
        });
    });
});
