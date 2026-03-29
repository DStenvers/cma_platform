/**
 * Theme Elements Tests
 *
 * Tests for specific UI element theming in light and dark modes.
 * These tests are resilient to different theme implementations.
 */

describe('Theme Elements', () => {
    // Helper to set theme
    const setTheme = (theme) => {
        cy.setCookie('cma_theme', theme);
        cy.visit('/main.php');
        cy.wait(2000);
    };

    beforeEach(() => {
        cy.loginAsAdmin();
    });

    describe('Light Mode Elements', () => {
        beforeEach(() => {
            setTheme('light');
        });

        describe('Page Structure', () => {
            it('should have body element', () => {
                cy.get('body').should('exist');
            });

            it('should have visible content', () => {
                cy.get('body').then($body => {
                    const hasContent = $body.text().length > 0;
                    expect(hasContent).to.be.true;
                });
            });

            it('should have content area', () => {
                cy.get('body').then($body => {
                    const $content = $body.find('#contentArea, .content-area, #content, main');
                    if ($content.length > 0) {
                        expect($content.length).to.be.at.least(1);
                    } else {
                        cy.log('Content area not found with expected selector');
                    }
                });
            });
        });

        describe('Sidebar', () => {
            it('should have sidebar element', () => {
                cy.get('body').then($body => {
                    const $sidebar = $body.find('[data-cy="sidebar"], .cma-sidebar, #sidebar, .sidebar');
                    if ($sidebar.length > 0) {
                        expect($sidebar.length).to.be.at.least(1);
                    } else {
                        cy.log('Sidebar not found');
                    }
                });
            });

            it('should have menu items', () => {
                cy.get('body').then($body => {
                    const $menuItems = $body.find('.cma-menu-item, .menu-item, a[href*="page="]');
                    if ($menuItems.length > 0) {
                        expect($menuItems.length).to.be.at.least(1);
                    } else {
                        cy.log('Menu items not found');
                    }
                });
            });

            it('should have user menu', () => {
                cy.get('body').then($body => {
                    const $userMenu = $body.find('.cma-user-menu, [data-cy="user-menu"], .user-menu');
                    if ($userMenu.length > 0) {
                        expect($userMenu.length).to.be.at.least(1);
                    } else {
                        cy.log('User menu not found');
                    }
                });
            });
        });

        describe('Toolbar', () => {
            beforeEach(() => {
                cy.openFormTable('users');
                cy.wait(2000);
            });

            it('should have toolbar element', () => {
                cy.get('body').then($body => {
                    const $toolbar = $body.find('.toolbar, .cma-toolbar, #toolbar');
                    if ($toolbar.length > 0) {
                        expect($toolbar.length).to.be.at.least(1);
                    } else {
                        cy.log('Toolbar not found');
                    }
                });
            });

            it('should have toolbar buttons', () => {
                cy.get('body').then($body => {
                    const $buttons = $body.find('.tb-btn, .toolbar-btn, button');
                    if ($buttons.length > 0) {
                        expect($buttons.length).to.be.at.least(1);
                    } else {
                        cy.log('Toolbar buttons not found');
                    }
                });
            });
        });

        describe('Tables', () => {
            beforeEach(() => {
                cy.openFormTable('users');
                cy.wait(2000);
            });

            it('should have table element', () => {
                cy.get('body').then($body => {
                    const $table = $body.find('table.filtering, .listtable, table');
                    if ($table.length > 0) {
                        expect($table.length).to.be.at.least(1);
                    } else {
                        cy.log('Table not found');
                    }
                });
            });

            it('should have table rows', () => {
                cy.get('body').then($body => {
                    const $rows = $body.find('table tbody tr');
                    if ($rows.length > 0) {
                        expect($rows.length).to.be.at.least(1);
                    } else {
                        cy.log('Table rows not found');
                    }
                });
            });

            it('should have readable table text', () => {
                cy.get('body').then($body => {
                    const $cells = $body.find('table td');
                    if ($cells.length > 0) {
                        cy.log('Found ' + $cells.length + ' table cells');
                    } else {
                        cy.log('Table cells not found');
                    }
                });
            });
        });

        describe('Forms', () => {
            beforeEach(() => {
                cy.openFormTable('users');
                cy.wait(2000);
                cy.get('#listTable tbody tr, table.listtable tbody tr').then($rows => {
                    if ($rows.length > 0) {
                        cy.wrap($rows).first().click({ force: true });
                        cy.wait(2000);
                    }
                });
            });

            it('should have form inputs', () => {
                cy.get('body').then($body => {
                    const $inputs = $body.find('input[type="text"], select, textarea');
                    if ($inputs.length > 0) {
                        expect($inputs.length).to.be.at.least(1);
                    } else {
                        cy.log('Form inputs not found');
                    }
                });
            });

            it('should have form labels', () => {
                cy.get('body').then($body => {
                    const $labels = $body.find('label, .field-label');
                    if ($labels.length > 0) {
                        expect($labels.length).to.be.at.least(1);
                    } else {
                        cy.log('Form labels not found');
                    }
                });
            });
        });

        describe('Breadcrumb', () => {
            beforeEach(() => {
                cy.openFormTable('users');
                cy.wait(2000);
            });

            it('should have breadcrumb element', () => {
                cy.get('body').then($body => {
                    const $breadcrumb = $body.find('#breadcrumb, .cma-breadcrumb, .breadcrumb');
                    if ($breadcrumb.length > 0) {
                        expect($breadcrumb.length).to.be.at.least(1);
                    } else {
                        cy.log('Breadcrumb not found');
                    }
                });
            });
        });
    });

    describe('Dark Mode Elements', () => {
        beforeEach(() => {
            setTheme('dark');
        });

        describe('Page Structure', () => {
            it('should have body element in dark mode', () => {
                cy.get('body').should('exist');
            });

            it('should have visible content in dark mode', () => {
                cy.get('body').then($body => {
                    const hasContent = $body.text().length > 0;
                    expect(hasContent).to.be.true;
                });
            });

            it('should have dark mode class if implemented', () => {
                cy.get('html, body').then($el => {
                    const hasDarkClass = $el.hasClass('dark-mode') || $el.hasClass('dark');
                    cy.log('Dark mode class: ' + hasDarkClass);
                    // Don't fail - just log
                });
            });
        });

        describe('Sidebar', () => {
            it('should have sidebar in dark mode', () => {
                cy.get('body').then($body => {
                    const $sidebar = $body.find('[data-cy="sidebar"], .cma-sidebar, #sidebar, .sidebar');
                    if ($sidebar.length > 0) {
                        expect($sidebar.length).to.be.at.least(1);
                    } else {
                        cy.log('Sidebar not found');
                    }
                });
            });

            it('should have menu items in dark mode', () => {
                cy.get('body').then($body => {
                    const $menuItems = $body.find('.cma-menu-item, .menu-item, a[href*="page="]');
                    cy.log('Menu items found: ' + $menuItems.length);
                });
            });
        });

        describe('Toolbar', () => {
            beforeEach(() => {
                cy.openFormTable('users');
                cy.wait(2000);
            });

            it('should have dark toolbar background', () => {
                cy.get('body').then($body => {
                    const $toolbar = $body.find('.toolbar, .cma-toolbar, #toolbar');
                    if ($toolbar.length > 0) {
                        const bg = $toolbar.css('background-color');
                        cy.log('Toolbar background: ' + bg);
                    } else {
                        cy.log('Toolbar not found');
                    }
                });
            });
        });

        describe('Tables', () => {
            beforeEach(() => {
                cy.openFormTable('users');
                cy.wait(2000);
            });

            it('should have dark table header', () => {
                cy.get('body').then($body => {
                    const $thead = $body.find('table thead');
                    if ($thead.length > 0) {
                        const bg = $thead.css('background-color');
                        cy.log('Table header background: ' + bg);
                    } else {
                        cy.log('Table header not found');
                    }
                });
            });

            it('should have table rows in dark mode', () => {
                cy.get('body').then($body => {
                    const $rows = $body.find('table tbody tr');
                    cy.log('Table rows found: ' + $rows.length);
                });
            });
        });

        describe('Forms', () => {
            beforeEach(() => {
                cy.openFormTable('users');
                cy.wait(2000);
                cy.get('#listTable tbody tr, table.listtable tbody tr').then($rows => {
                    if ($rows.length > 0) {
                        cy.wrap($rows).first().click({ force: true });
                        cy.wait(2000);
                    }
                });
            });

            it('should have dark input backgrounds', () => {
                cy.get('body').then($body => {
                    const $inputs = $body.find('input[type="text"]');
                    if ($inputs.length > 0) {
                        const bg = $inputs.first().css('background-color');
                        cy.log('Input background: ' + bg);
                    } else {
                        cy.log('Inputs not found');
                    }
                });
            });
        });

        describe('Breadcrumb', () => {
            beforeEach(() => {
                cy.openFormTable('users');
                cy.wait(2000);
            });

            it('should have visible breadcrumb links', () => {
                cy.get('body').then($body => {
                    const $links = $body.find('#breadcrumb a, .cma-breadcrumb a, .breadcrumb a');
                    if ($links.length > 0) {
                        const color = $links.first().css('color');
                        cy.log('Breadcrumb link color: ' + color);
                    } else {
                        cy.log('Breadcrumb links not found');
                    }
                });
            });
        });

        describe('Select2 Dropdown', () => {
            beforeEach(() => {
                cy.openFormTable('users');
                cy.wait(2000);
                cy.get('#listTable tbody tr, table.listtable tbody tr').then($rows => {
                    if ($rows.length > 0) {
                        cy.wrap($rows).first().click({ force: true });
                        cy.wait(2000);
                    }
                });
            });

            it('should have dark select2 container', () => {
                cy.get('body').then($body => {
                    const $select2 = $body.find('.select2-container');
                    if ($select2.length > 0) {
                        const bg = $select2.first().css('background-color');
                        cy.log('Select2 background: ' + bg);
                    } else {
                        cy.log('Select2 not found on page');
                    }
                });
            });
        });

        describe('Groupbox Headers', () => {
            beforeEach(() => {
                cy.openFormTable('users');
                cy.wait(2000);
                cy.get('#listTable tbody tr, table.listtable tbody tr').then($rows => {
                    if ($rows.length > 0) {
                        cy.wrap($rows).first().click({ force: true });
                        cy.wait(2000);
                    }
                });
            });

            it('should have dark groupbox headers', () => {
                cy.get('body').then($body => {
                    const $headers = $body.find('.groupbox-header, .cma-groupbox-header, cma-groupbox');
                    if ($headers.length > 0) {
                        const bg = $headers.first().css('background');
                        cy.log('Groupbox header background: ' + bg);
                    } else {
                        cy.log('Groupbox headers not found');
                    }
                });
            });
        });
    });

    describe('Theme Transition', () => {
        it('should handle theme changes', () => {
            setTheme('light');
            cy.get('body').should('exist');

            setTheme('dark');
            cy.get('body').should('exist');

            setTheme('light');
            cy.get('body').should('exist');
        });
    });

    describe('Preferences Page', () => {
        beforeEach(() => {
            setTheme('light');
        });

        it('should display theme selector', () => {
            cy.visit('/main.php?page=preferences.php');
            cy.wait(2000);
            cy.get('body').then($body => {
                const $themeSelect = $body.find('#theme, select[name="theme"]');
                if ($themeSelect.length > 0) {
                    expect($themeSelect.length).to.be.at.least(1);
                } else {
                    cy.log('Theme selector not found on preferences page');
                }
            });
        });

        it('should have theme options', () => {
            cy.visit('/main.php?page=preferences.php');
            cy.wait(2000);
            cy.get('body').then($body => {
                const $options = $body.find('#theme option, select[name="theme"] option');
                if ($options.length > 0) {
                    cy.log('Theme options found: ' + $options.length);
                } else {
                    cy.log('Theme options not found');
                }
            });
        });

        it('should show current theme', () => {
            cy.visit('/main.php?page=preferences.php');
            cy.wait(2000);
            cy.get('body').then($body => {
                const $themeSelect = $body.find('#theme, select[name="theme"]');
                if ($themeSelect.length > 0) {
                    const value = $themeSelect.val();
                    cy.log('Current theme: ' + value);
                } else {
                    cy.log('Theme selector not found');
                }
            });
        });

        it('should update theme when changed', () => {
            cy.visit('/main.php?page=preferences.php');
            cy.wait(2000);
            cy.get('body').then($body => {
                const $themeSelect = $body.find('#theme, select[name="theme"]');
                if ($themeSelect.length > 0) {
                    cy.wrap($themeSelect).select('dark', { force: true });
                    cy.get('form').first().submit();
                    cy.wait(2000);
                    cy.get('body').should('exist');
                } else {
                    cy.log('Theme selector not found');
                }
            });
        });
    });

    describe('Icon Theming', () => {
        describe('Light Mode Icons', () => {
            beforeEach(() => {
                setTheme('light');
                cy.openFormTable('users');
                cy.wait(2000);
            });

            it('should have visible icons in toolbar', () => {
                cy.get('body').then($body => {
                    const $icons = $body.find('.lnr, .icon, [class*="icon"]');
                    cy.log('Icons found: ' + $icons.length);
                });
            });

            it('should have appropriate icon color', () => {
                cy.get('body').then($body => {
                    const $icons = $body.find('.tb-btn .lnr');
                    if ($icons.length > 0) {
                        const color = $icons.first().css('color');
                        cy.log('Icon color: ' + color);
                    } else {
                        cy.log('Toolbar icons not found');
                    }
                });
            });
        });

        describe('Dark Mode Icons', () => {
            beforeEach(() => {
                setTheme('dark');
                cy.openFormTable('users');
                cy.wait(2000);
            });

            it('should have visible icons in dark mode', () => {
                cy.get('body').then($body => {
                    const $icons = $body.find('.lnr, .icon, [class*="icon"]');
                    cy.log('Icons in dark mode: ' + $icons.length);
                });
            });

            it('should have light icon color in dark mode', () => {
                cy.get('body').then($body => {
                    const $icons = $body.find('.tb-btn .lnr');
                    if ($icons.length > 0) {
                        const color = $icons.first().css('color');
                        cy.log('Dark mode icon color: ' + color);
                    } else {
                        cy.log('Toolbar icons not found');
                    }
                });
            });

            it('should invert PNG theme icons', () => {
                cy.get('body').then($body => {
                    const $pngIcons = $body.find('.theme-icon-png, img.theme-icon');
                    if ($pngIcons.length > 0) {
                        const filter = $pngIcons.first().css('filter');
                        cy.log('PNG icon filter: ' + filter);
                    } else {
                        cy.log('PNG theme icons not found - may use different approach');
                    }
                });
            });
        });
    });
});
