/**
 * CMA Tours and Tips
 *
 * Comprehensive tip and tour system for the CMA application.
 * Tours are organized by page/feature and user level.
 *
 * User Levels:
 * - All users: Basic navigation and form usage
 * - Admin (A): User management, groups, tools
 * - Developer (D): Database tools, migrations, form wizard
 */

(function() {
    'use strict';

    // Detect user level from body class or data attribute
    function getUserLevel() {
        const body = document.body;
        if (body.classList.contains('user-level-D') || body.dataset.userLevel === 'D') {
            return 'developer';
        }
        if (body.classList.contains('user-level-A') || body.dataset.userLevel === 'A') {
            return 'admin';
        }
        return 'user';
    }

    // Check if user is at least admin level
    function isAdmin() {
        const level = getUserLevel();
        return level === 'admin' || level === 'developer';
    }

    // Check if user is developer level
    function isDeveloper() {
        return getUserLevel() === 'developer';
    }

    // Wait for LibTip to be available
    function initTours() {
        // DISABLED: Tours temporarily skipped
        return;

        if (typeof LibTip === 'undefined') {
            setTimeout(initTours, 100);
            return;
        }

        // Detect current page
        const path = window.location.pathname;
        const currentPage = path.split('/').pop() || 'dashboard.php';

        // Get URL parameters
        const urlParams = new URLSearchParams(window.location.search);
        const formName = urlParams.get('form') || urlParams.get('jsonForm');
        const toolName = urlParams.get('tool');

        // Initialize tours based on current page
        switch (currentPage) {
            case 'dashboard.php':
                initDashboardTour();
                break;
            case 'main.php':
                // Check what's loaded in main.php
                const page = urlParams.get('page');
                if (page) {
                    handleMainPageContent(page, formName);
                } else {
                    initMainNavigationTour();
                }
                break;
            case 'form.php':
                initFormTour(formName);
                break;
            case 'report-designer.php':
                initReportDesignerTips();
                break;
            case 'reports.php':
                initReportsListTour();
                break;
            case 'tools.php':
                initToolsPageTour(toolName);
                break;
            case 'preferences.php':
                initPreferencesTour();
                break;
            case 'imageupload.php':
                initImageUploadTour();
                break;
        }

        // Also check for tool pages loaded directly
        if (currentPage.startsWith('tools_')) {
            initSpecificToolTour(currentPage);
        }
    }

    // Handle content loaded within main.php
    function handleMainPageContent(page, formName) {
        if (page === 'form.php') {
            initFormTour(formName);
        } else if (page === 'tools.php') {
            initToolsPageTour();
        } else if (page === 'reports.php') {
            initReportsListTour();
        } else if (page === 'dashboard.php') {
            initDashboardTour();
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // 1. DASHBOARD TOUR
    // ═══════════════════════════════════════════════════════════════════════════

    function initDashboardTour() {
        const steps = [];

        // Welcome step - always shown
        if (document.querySelector('.dashboard-container, .dashboard-grid, #contentArea')) {
            steps.push({
                target: '.dashboard-container, .dashboard-grid, #contentArea',
                title: 'Welkom bij CMA',
                content: `
                    <p>Dit is het dashboard van het Content Management Systeem.</p>
                    <p>Vanaf hier heb je toegang tot alle onderdelen van de applicatie.</p>
                `,
                position: 'bottom'
            });
        }

        // Menu cards for regular users
        if (document.querySelector('.menu-grid, .menu-cards')) {
            steps.push({
                target: '.menu-grid, .menu-cards',
                title: 'Menukaarten',
                content: `
                    <p>Klik op een kaart om direct naar dat onderdeel te gaan.</p>
                    <p>De kaarten tonen je beschikbare formulieren en rapporten.</p>
                `,
                position: 'top'
            });
        }

        // Quick access for admins
        if (isAdmin() && document.querySelector('.quick-access-grid, .quick-access')) {
            steps.push({
                target: '.quick-access-grid, .quick-access',
                title: 'Snelle toegang',
                content: `
                    <p>Als beheerder heb je hier snelle toegang tot:</p>
                    <ul style="margin: 8px 0; padding-left: 20px;">
                        <li><strong>Gebruikers</strong> - Beheer accounts</li>
                        <li><strong>Groepen</strong> - Beheer toegangsrechten</li>
                        <li><strong>Tools</strong> - Systeemhulpmiddelen</li>
                        <li><strong>Cache</strong> - Wis de cache</li>
                    </ul>
                `,
                position: 'bottom'
            });
        }

        // Stats for admins/developers
        if (isAdmin() && document.querySelector('.stats-grid, .stats-cards')) {
            steps.push({
                target: '.stats-grid, .stats-cards',
                title: 'Systeemstatistieken',
                content: `
                    <p>Overzicht van de systeemgezondheid:</p>
                    <ul style="margin: 8px 0; padding-left: 20px;">
                        <li><strong>Errors</strong> - Recente foutmeldingen</li>
                        <li><strong>Cache</strong> - Cache hit ratio</li>
                        <li><strong>Performance</strong> - Trage API calls</li>
                    </ul>
                `,
                position: 'left'
            });
        }

        // AI Question section
        if (document.querySelector('#aiQuestionInput, .ai-assistant')) {
            steps.push({
                target: '#aiQuestionInput, .ai-assistant',
                title: 'AI Assistent',
                content: `
                    <p>Stel hier vragen over het systeem of je gegevens.</p>
                    <p>De AI helpt je met uitleg, tips en antwoorden.</p>
                `,
                position: 'top'
            });
        }

        if (steps.length > 1) {
            LibTip.tour('dashboard', steps);
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // 2. MAIN NAVIGATION TOUR
    // ═══════════════════════════════════════════════════════════════════════════

    function initMainNavigationTour() {
        const steps = [];

        // Sidebar navigation
        if (document.querySelector('.nav-sidebar, #sidebar, .sidebar')) {
            steps.push({
                target: '.nav-sidebar, #sidebar, .sidebar',
                title: 'Zijbalk navigatie',
                content: `
                    <p>Dit is je hoofdmenu met alle beschikbare onderdelen.</p>
                    <p>Klik op een item om het te openen.</p>
                `,
                position: 'right'
            });
        }

        // Sidebar toggle
        if (document.querySelector('.sidebar-toggle, #sidebarToggle, .menu-toggle')) {
            steps.push({
                target: '.sidebar-toggle, #sidebarToggle, .menu-toggle',
                title: 'Menu in-/uitklappen',
                content: `
                    <p>Klik hier om de zijbalk te verbergen of te tonen.</p>
                    <p>Handig voor meer werkruimte op kleinere schermen.</p>
                `,
                position: 'right'
            });
        }

        // User menu
        if (document.querySelector('#user-menu, .user-menu, .user-dropdown')) {
            steps.push({
                target: '#user-menu, .user-menu, .user-dropdown',
                title: 'Gebruikersmenu',
                content: `
                    <p>Hier vind je:</p>
                    <ul style="margin: 8px 0; padding-left: 20px;">
                        <li>Je profiel en voorkeuren</li>
                        <li>Thema wisselen (licht/donker)</li>
                        <li>Uitloggen</li>
                    </ul>
                `,
                position: 'bottom'
            });
        }

        // Breadcrumb
        if (document.querySelector('.breadcrumb, #breadcrumb')) {
            steps.push({
                target: '.breadcrumb, #breadcrumb',
                title: 'Kruimelpad',
                content: `
                    <p>Toont waar je bent in de applicatie.</p>
                    <p>Klik op een niveau om terug te navigeren.</p>
                `,
                position: 'bottom'
            });
        }

        if (steps.length > 1) {
            LibTip.tour('main-navigation', steps);
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // 3. FORM PAGE TOUR
    // ═══════════════════════════════════════════════════════════════════════════

    function initFormTour(formName) {
        // Check for specific form tours first, then fall through to generic
        if (formName === 'users' && isAdmin()) {
            initUsersFormTour();
            return;
        }
        if (formName === 'groups' && isAdmin()) {
            initGroupsFormTour();
            return;
        }

        // Generic form tour - dynamically detects available elements
        const steps = [];

        // Get form title from toolbar or breadcrumb
        const titleEl = document.querySelector('#toolbar-status, .breadcrumb .active, .toolbar .title');
        const formTitle = titleEl ? titleEl.textContent.trim() : 'Dit formulier';

        // 1. Toolbar overview
        if (document.querySelector('#listToolbar')) {
            steps.push({
                target: '#listToolbar',
                title: 'Werkbalk',
                content: '<p>De werkbalk bevat alle acties voor <strong>' + formTitle + '</strong>.</p>',
                position: 'bottom'
            });
        }

        // 2. View toggle (tree/table) - only if both buttons exist
        const treeBtn = document.querySelector('#btn_treeview');
        const tableBtn = document.querySelector('#btn_tableview');
        if (treeBtn && tableBtn) {
            steps.push({
                target: '#btn_treeview',
                title: 'Weergave wisselen',
                content: '<p>Schakel tussen <strong>boomweergave</strong> (gegroepeerd) en <strong>tabelweergave</strong> (platte lijst).</p>',
                position: 'bottom'
            });
        }

        // 3. Search button
        if (document.querySelector('#btn_search')) {
            steps.push({
                target: '#btn_search',
                title: 'Zoeken',
                content: '<p>Open het uitgebreide zoekpaneel om te filteren op specifieke velden.</p>',
                position: 'bottom'
            });
        }

        // 4. Column selector
        if (document.querySelector('#btn_columns')) {
            steps.push({
                target: '#btn_columns',
                title: 'Kolommen kiezen',
                content: '<p>Selecteer welke kolommen zichtbaar zijn en pas de volgorde aan.</p>',
                position: 'bottom'
            });
        }

        // 5. Add button
        if (document.querySelector('#btn_add')) {
            steps.push({
                target: '#btn_add',
                title: 'Nieuw record',
                content: '<p>Voeg een nieuw record toe.</p><p><strong>Sneltoets:</strong> <kbd>Ctrl+N</kbd></p>',
                position: 'bottom'
            });
        }

        // 6. Toolbar filter (if form has a filter field)
        if (document.querySelector('#toolbarFilter')) {
            steps.push({
                target: '#toolbarFilter',
                title: 'Filter',
                content: '<p>Filter de lijst op een specifieke waarde. Handig om snel een subset te bekijken.</p>',
                position: 'bottom'
            });
        }

        // 7. Record list
        if (document.querySelector('#listContent')) {
            steps.push({
                target: '#listContent',
                title: 'Recordlijst',
                content: `
                    <p><strong>Klik</strong> op een record om het te selecteren.</p>
                    <p><strong>Rechtermuisklik</strong> om te bewerken in een zijpaneel.</p>
                    <p>Gebruik <kbd>&#8593;</kbd><kbd>&#8595;</kbd> pijltjestoetsen om te navigeren.</p>
                `,
                position: 'right'
            });
        }

        // 8. Detail toolbar (save/cancel/delete) - only if visible
        if (document.querySelector('#toolbar_save')) {
            steps.push({
                target: '#toolbar_save',
                title: 'Opslaan',
                content: '<p>Sla je wijzigingen op.</p><p><strong>Sneltoets:</strong> <kbd>Ctrl+S</kbd></p>',
                position: 'bottom'
            });
        }

        // 9. Export button
        if (document.querySelector('#btn_export')) {
            steps.push({
                target: '#btn_export',
                title: 'Exporteren',
                content: '<p>Exporteer de huidige lijst naar Excel of CSV.</p>',
                position: 'bottom'
            });
        }

        // 10. Subforms (if present after record load)
        if (document.querySelector('.subform-container')) {
            steps.push({
                target: '.subform-container',
                title: 'Subformulieren',
                content: `
                    <p>Gerelateerde records bij het huidige record.</p>
                    <p><strong>+</strong> = Toevoegen</p>
                    <p><strong>Rechtermuisklik</strong> = Bewerken</p>
                `,
                position: 'top'
            });
        }

        if (steps.length > 0) {
            LibTip.tour('form-' + (formName || 'generic'), steps);
        }

        // Also show inline edit tip after a delay
        setTimeout(function() {
            showInlineEditTip();
        }, 2000);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // 4. INLINE EDITING TIP
    // ═══════════════════════════════════════════════════════════════════════════

    function showInlineEditTip() {
        const tableBody = document.querySelector('#listTable tbody, .record-table tbody');
        if (tableBody && tableBody.children.length > 0) {
            LibTip.show({
                id: 'inline-edit',
                target: '#listTable tbody tr:first-child, .record-table tbody tr:first-child',
                title: 'Inline bewerken',
                content: `
                    <p><strong>Rechtermuisklik</strong> op een cel om direct in de tabel te bewerken.</p>
                    <p><kbd>Enter</kbd> = Opslaan</p>
                    <p><kbd>Escape</kbd> = Annuleren</p>
                    <p><kbd>Tab</kbd> = Volgende cel</p>
                `,
                position: 'bottom'
            });
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // 5. REPORT DESIGNER TOUR
    // ═══════════════════════════════════════════════════════════════════════════

    function initReportDesignerTips() {
        // Wait for UI to settle
        setTimeout(() => {
            showReportDesignerTips();
        }, 500);
    }

    async function showReportDesignerTips() {
        const wizardContainer = document.querySelector('.report-designer-wizard');
        if (!wizardContainer) return;

        // Detect if a report was loaded
        const reportNameDisplay = document.querySelector('#reportNameDisplay');
        const hasLoadedReport = reportNameDisplay &&
                               reportNameDisplay.style.display !== 'none' &&
                               reportNameDisplay.textContent.trim();

        if (hasLoadedReport) {
            // Loaded report tour
            showLoadedReportTour();
        } else {
            // New report tour
            showNewReportTour();
        }
    }

    function showNewReportTour() {
        const steps = [];

        // Mode dialog (if visible)
        if (document.querySelector('#modeDialog[open]')) {
            steps.push({
                target: '#modeDialog',
                title: 'Kies een modus',
                content: `
                    <p><strong>Snel</strong> - Maak eenvoudige rapporten zonder parameters.</p>
                    <p><strong>Geavanceerd</strong> - Volledige controle met parameters en filters.</p>
                    <p><strong>Laden</strong> - Open een bestaand rapport.</p>
                `,
                position: 'bottom'
            });
        }

        // Wizard tabs
        if (document.querySelector('#wizardTabs')) {
            steps.push({
                target: '#wizardTabs',
                title: 'Wizard stappen',
                content: `
                    <p>De wizard leidt je in 6 stappen:</p>
                    <ol style="margin: 8px 0; padding-left: 20px;">
                        <li>Tabellen selecteren</li>
                        <li>Parameters definiëren</li>
                        <li>Velden configureren</li>
                        <li>Sortering instellen</li>
                        <li>Uitvoer bekijken</li>
                        <li>Rapport opslaan</li>
                    </ol>
                `,
                position: 'bottom'
            });
        }

        // Table list
        if (document.querySelector('.table-list-panel')) {
            steps.push({
                target: '.table-list-panel',
                title: 'Tabellen selecteren',
                content: `
                    <p>Klik op tabellen om ze toe te voegen aan je rapport.</p>
                    <p>Geselecteerde tabellen verschijnen op het canvas rechts.</p>
                `,
                position: 'right'
            });
        }

        // Schema canvas
        if (document.querySelector('cma-schema-canvas')) {
            steps.push({
                target: 'cma-schema-canvas',
                title: 'Schema canvas',
                content: `
                    <p>Dit toont je geselecteerde tabellen en hun relaties.</p>
                    <p><strong>Sleep</strong> tabellen om ze te ordenen.</p>
                    <p>Lijnen tonen de koppelingen tussen tabellen.</p>
                `,
                position: 'left'
            });
        }

        // Field search button
        if (document.querySelector('#fieldSearchBtn')) {
            steps.push({
                target: '#fieldSearchBtn',
                title: 'Veldzoeker',
                content: `
                    <p>Krachtige zoekfunctie voor velden.</p>
                    <p>Zoek op:</p>
                    <ul style="margin: 8px 0; padding-left: 20px;">
                        <li>Veldnaam (technisch)</li>
                        <li>Omschrijving/label</li>
                        <li>Tabelnaam</li>
                    </ul>
                    <p>Ideaal als je de exacte naam niet weet!</p>
                `,
                position: 'bottom'
            });
        }

        // Navigation buttons
        if (document.querySelector('#btnNextStep')) {
            steps.push({
                target: '#btnNextStep',
                title: 'Navigatie',
                content: `
                    <p>Gebruik <strong>Volgende/Vorige</strong> om door de stappen te gaan.</p>
                    <p>Je kunt ook direct op een stap klikken in de tabs.</p>
                `,
                position: 'top'
            });
        }

        if (steps.length > 0) {
            LibTip.tour('report-designer', steps);
        }
    }

    function showLoadedReportTour() {
        const steps = [];

        // Report name
        if (document.querySelector('#reportNameDisplay')) {
            steps.push({
                target: '#reportNameDisplay',
                title: 'Geladen rapport',
                content: `
                    <p>Je bewerkt nu een bestaand rapport.</p>
                    <p>Wijzigingen worden pas opgeslagen als je op <strong>Opslaan</strong> klikt.</p>
                `,
                position: 'bottom'
            });
        }

        // Schema canvas
        if (document.querySelector('cma-schema-canvas')) {
            steps.push({
                target: 'cma-schema-canvas',
                title: 'Tabeloverzicht',
                content: `
                    <p>Dit zijn de tabellen in je rapport.</p>
                    <p>Je kunt tabellen toevoegen of verwijderen via de lijst links.</p>
                `,
                position: 'left'
            });
        }

        // Main tabs
        if (document.querySelector('#mainTabs')) {
            steps.push({
                target: '#mainTabs',
                title: 'Ontwerper / Resultaten',
                content: `
                    <p>Schakel tussen:</p>
                    <ul style="margin: 8px 0; padding-left: 20px;">
                        <li><strong>Ontwerper</strong> - Pas het rapport aan</li>
                        <li><strong>Resultaten</strong> - Test en bekijk output</li>
                    </ul>
                `,
                position: 'bottom'
            });
        }

        // Field search
        if (document.querySelector('#fieldSearchBtn')) {
            steps.push({
                target: '#fieldSearchBtn',
                title: 'Veldzoeker',
                content: `
                    <p>Zoek velden op naam of omschrijving.</p>
                    <p>Handig om snel het juiste veld te vinden!</p>
                `,
                position: 'bottom'
            });
        }

        if (steps.length > 0) {
            LibTip.tour('report-designer-loaded', steps);
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // 6. REPORTS LIST TOUR
    // ═══════════════════════════════════════════════════════════════════════════

    function initReportsListTour() {
        const steps = [];

        // Report tree
        if (document.querySelector('#reportTree, .report-tree, cma-tree')) {
            steps.push({
                target: '#reportTree, .report-tree, cma-tree',
                title: 'Rapportenlijst',
                content: `
                    <p>Alle beschikbare rapporten, georganiseerd in mappen.</p>
                    <p>Klik op een map om deze te openen.</p>
                    <p>Klik op een rapport om het uit te voeren.</p>
                `,
                position: 'right'
            });
        }

        // Search
        if (document.querySelector('#reportSearch, .report-search, lib-search-input')) {
            steps.push({
                target: '#reportSearch, .report-search, lib-search-input',
                title: 'Zoeken',
                content: `
                    <p>Typ om snel een rapport te vinden op naam.</p>
                `,
                position: 'bottom'
            });
        }

        // New report button
        if (document.querySelector('[data-action="newReport"], .btn-new-report')) {
            steps.push({
                target: '[data-action="newReport"], .btn-new-report',
                title: 'Nieuw rapport',
                content: `
                    <p>Start de rapport-ontwerper om een nieuw rapport te maken.</p>
                `,
                position: 'bottom'
            });
        }

        if (steps.length > 0) {
            LibTip.tour('reports.php', steps);
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // 7. USERS FORM TOUR (Admin Only)
    // ═══════════════════════════════════════════════════════════════════════════

    function initUsersFormTour() {
        if (!isAdmin()) return;

        const steps = [];

        // User level
        if (document.querySelector('[name="usrLevel"], #usrLevel')) {
            steps.push({
                target: '[name="usrLevel"], #usrLevel',
                title: 'Gebruikersniveau',
                content: `
                    <p>Bepaalt de rechten van de gebruiker:</p>
                    <ul style="margin: 8px 0; padding-left: 20px;">
                        <li><strong>U</strong> = Gebruiker (basis)</li>
                        <li><strong>A</strong> = Administrator (beheer)</li>
                        <li><strong>D</strong> = Developer (volledig)</li>
                    </ul>
                `,
                position: 'right'
            });
        }

        // Groups
        if (document.querySelector('[name="usrGroups"], .user-groups, .subform-groups')) {
            steps.push({
                target: '[name="usrGroups"], .user-groups, .subform-groups',
                title: 'Groepen',
                content: `
                    <p>Wijs gebruikers toe aan groepen.</p>
                    <p>Groepen bepalen welke menu-items en rapporten zichtbaar zijn.</p>
                `,
                position: 'right'
            });
        }

        // Login As button
        if (document.querySelector('[data-action="loginAs"], .btn-login-as')) {
            steps.push({
                target: '[data-action="loginAs"], .btn-login-as',
                title: 'Inloggen als',
                content: `
                    <p>Log in als deze gebruiker om te testen wat ze zien.</p>
                    <p><strong>Alleen voor administrators!</strong></p>
                    <p>Klik op je eigen naam in de header om terug te keren.</p>
                `,
                position: 'bottom'
            });
        }

        // IP restriction
        if (document.querySelector('[name="usrIPFrom"], #usrIPFrom')) {
            steps.push({
                target: '[name="usrIPFrom"], #usrIPFrom',
                title: 'IP-beperking',
                content: `
                    <p>Beperk inloggen tot specifieke IP-adressen.</p>
                    <p>Laat leeg voor geen beperking.</p>
                    <p>Gebruik een bereik: <code>192.168.1.1</code> - <code>192.168.1.255</code></p>
                `,
                position: 'right'
            });
        }

        if (steps.length > 0) {
            LibTip.tour('form-users', steps);
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // 8. GROUPS FORM TOUR (Admin Only)
    // ═══════════════════════════════════════════════════════════════════════════

    function initGroupsFormTour() {
        if (!isAdmin()) return;

        const steps = [];

        // Group name
        if (document.querySelector('[name="grpName"], #grpName')) {
            steps.push({
                target: '[name="grpName"], #grpName',
                title: 'Groepsnaam',
                content: `
                    <p>Geef de groep een duidelijke naam die de functie beschrijft.</p>
                    <p>Bijvoorbeeld: "Redacteurs", "Managers", "Alleen-lezen".</p>
                `,
                position: 'right'
            });
        }

        // Members subform
        if (document.querySelector('.subform-members, [data-subform="members"], #subform_grpMembers')) {
            steps.push({
                target: '.subform-members, [data-subform="members"], #subform_grpMembers',
                title: 'Leden',
                content: `
                    <p>Voeg gebruikers toe aan deze groep.</p>
                    <p>Leden krijgen automatisch de rechten van de groep.</p>
                `,
                position: 'top'
            });
        }

        // Menu rights
        if (document.querySelector('.subform-rights, [data-subform="rights"], #subform_grpRights')) {
            steps.push({
                target: '.subform-rights, [data-subform="rights"], #subform_grpRights',
                title: 'Menu rechten',
                content: `
                    <p>Bepaal welke menu-items zichtbaar zijn voor deze groep.</p>
                    <p>Vink aan om toegang te geven.</p>
                `,
                position: 'top'
            });
        }

        // Report rights
        if (document.querySelector('.subform-reports, [data-subform="reports"], #subform_grpReports')) {
            steps.push({
                target: '.subform-reports, [data-subform="reports"], #subform_grpReports',
                title: 'Rapportrechten',
                content: `
                    <p>Bepaal welke rapporten deze groep mag uitvoeren.</p>
                `,
                position: 'top'
            });
        }

        if (steps.length > 0) {
            LibTip.tour('form-groups', steps);
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // 9. TOOLS PAGE TOUR (Admin Only)
    // ═══════════════════════════════════════════════════════════════════════════

    function initToolsPageTour(toolName) {
        if (!isAdmin()) return;

        // If a specific tool is loaded, show tool-specific tour
        if (toolName) {
            initSpecificToolTour(toolName);
            return;
        }

        const steps = [];

        // Tools tree
        if (document.querySelector('#tools-tree, #leftlist, cma-tree')) {
            steps.push({
                target: '#tools-tree, #leftlist, cma-tree',
                title: 'Tools menu',
                content: `
                    <p>Alle systeemtools, georganiseerd per categorie:</p>
                    <ul style="margin: 8px 0; padding-left: 20px;">
                        <li>Database tools</li>
                        <li>Cache beheer</li>
                        <li>Import/Export</li>
                        <li>Ontwikkeltools</li>
                    </ul>
                `,
                position: 'right'
            });
        }

        // Badge explanation
        steps.push({
            target: '#tools-tree, #leftlist',
            title: 'Toegangsniveaus',
            content: `
                <p>Tools zijn gemarkeerd met badges:</p>
                <ul style="margin: 8px 0; padding-left: 20px;">
                    <li><strong>A</strong> = Alleen voor Administrators</li>
                    <li><strong>D</strong> = Alleen voor Developers</li>
                </ul>
                <p>Je ziet alleen tools waartoe je toegang hebt.</p>
            `,
            position: 'right'
        });

        // Tool iframe
        if (document.querySelector('#details_iframe, #tools-content, iframe')) {
            steps.push({
                target: '#details_iframe, #tools-content, iframe',
                title: 'Tool weergave',
                content: `
                    <p>De geselecteerde tool opent hier.</p>
                    <p>Elke tool heeft eigen opties en functies.</p>
                `,
                position: 'left'
            });
        }

        if (steps.length > 0) {
            LibTip.tour('tools.php', steps);
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // 10. SPECIFIC TOOL TOURS
    // ═══════════════════════════════════════════════════════════════════════════

    function initSpecificToolTour(toolName) {
        // Normalize tool name
        const tool = toolName.replace('tools_', '').replace('.php', '');

        switch (tool) {
            case 'query':
                initQueryToolTour();
                break;
            case 'clearcache':
                initClearCacheTip();
                break;
            case 'dbsummary':
                initDbSummaryTour();
                break;
            case 'migrations':
                initMigrationsTour();
                break;
            case 'formwiz':
                initFormWizardTour();
                break;
            case 'storybook':
                initStorybookTour();
                break;
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // 11. QUERY TOOL TOUR (Admin/Developer)
    // ═══════════════════════════════════════════════════════════════════════════

    function initQueryToolTour() {
        if (!isAdmin()) return;

        const steps = [];

        // Database select
        if (document.querySelector('#databaseSelect, [name="database"], select')) {
            steps.push({
                target: '#databaseSelect, [name="database"], select',
                title: 'Database kiezen',
                content: `
                    <p>Selecteer de database waarop je queries wilt uitvoeren.</p>
                `,
                position: 'bottom'
            });
        }

        // Query textarea
        if (document.querySelector('#query, textarea, .query-editor')) {
            steps.push({
                target: '#query, textarea, .query-editor',
                title: 'SQL invoer',
                content: `
                    <p>Schrijf hier je SQL query.</p>
                    <p>Ondersteunt: SELECT, INSERT, UPDATE, DELETE</p>
                    <p><strong>Tip:</strong> Wees voorzichtig met UPDATE en DELETE!</p>
                `,
                position: 'bottom'
            });
        }

        // Execute button
        if (document.querySelector('#go, [type="submit"], .btn-execute')) {
            steps.push({
                target: '#go, [type="submit"], .btn-execute',
                title: 'Uitvoeren',
                content: `
                    <p>Voer de query uit.</p>
                    <p><strong>Sneltoets:</strong> <kbd>Ctrl+Enter</kbd></p>
                `,
                position: 'bottom'
            });
        }

        // Standard queries
        if (document.querySelector('#stdQueries, [name="stdQueries"]')) {
            steps.push({
                target: '#stdQueries, [name="stdQueries"]',
                title: 'Standaard queries',
                content: `
                    <p>Kies uit voorgedefinieerde queries voor veelvoorkomende taken.</p>
                `,
                position: 'bottom'
            });
        }

        // History
        if (document.querySelector('#history, [name="history"], .query-history')) {
            steps.push({
                target: '#history, [name="history"], .query-history',
                title: 'Geschiedenis',
                content: `
                    <p>Je recente queries worden bewaard.</p>
                    <p>Selecteer een query om deze opnieuw te gebruiken.</p>
                `,
                position: 'bottom'
            });
        }

        if (steps.length > 0) {
            LibTip.tour('tools-query', steps);
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // 12. CLEAR CACHE TIP (Admin)
    // ═══════════════════════════════════════════════════════════════════════════

    function initClearCacheTip() {
        if (!isAdmin()) return;

        const target = document.querySelector('.cache-section, .cache-info, form');
        if (target) {
            LibTip.show({
                id: 'tools-clearcache',
                target: '.cache-section, .cache-info, form',
                title: 'Cache beheer',
                content: `
                    <p>Wis de cache na wijzigingen aan:</p>
                    <ul style="margin: 8px 0; padding-left: 20px;">
                        <li>Formulierdefinities</li>
                        <li>Menu configuratie</li>
                        <li>Systeeminstellingen</li>
                    </ul>
                    <p>De browser-cache wordt automatisch geleegd.</p>
                `,
                position: 'bottom'
            });
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // 13. DATABASE SUMMARY TOUR (Developer)
    // ═══════════════════════════════════════════════════════════════════════════

    function initDbSummaryTour() {
        if (!isDeveloper()) return;

        const steps = [];

        // Database select
        if (document.querySelector('#databaseSelect, [name="database"], select')) {
            steps.push({
                target: '#databaseSelect, [name="database"], select',
                title: 'Database',
                content: `
                    <p>Kies de database om te analyseren.</p>
                `,
                position: 'bottom'
            });
        }

        // Table stats
        if (document.querySelector('.table-stats, .table-list, table')) {
            steps.push({
                target: '.table-stats, .table-list, table',
                title: 'Tabeloverzicht',
                content: `
                    <p>Toont alle tabellen met:</p>
                    <ul style="margin: 8px 0; padding-left: 20px;">
                        <li>Aantal records</li>
                        <li>Aantal kolommen</li>
                        <li>Geschatte grootte</li>
                    </ul>
                    <p>Klik op een tabel voor details.</p>
                `,
                position: 'right'
            });
        }

        if (steps.length > 0) {
            LibTip.tour('tools-dbsummary', steps);
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // 14. MIGRATIONS TOUR (Developer)
    // ═══════════════════════════════════════════════════════════════════════════

    function initMigrationsTour() {
        if (!isDeveloper()) return;

        const steps = [];

        // Migration list
        if (document.querySelector('.migration-list, .migrations, table')) {
            steps.push({
                target: '.migration-list, .migrations, table',
                title: 'Migraties',
                content: `
                    <p>Lijst van alle database-migraties.</p>
                    <p>Status indicatoren:</p>
                    <ul style="margin: 8px 0; padding-left: 20px;">
                        <li><span style="color: green;">●</span> Uitgevoerd</li>
                        <li><span style="color: orange;">●</span> Pending</li>
                        <li><span style="color: red;">●</span> Gefaald</li>
                    </ul>
                `,
                position: 'right'
            });
        }

        // Run migration button
        if (document.querySelector('[data-action="runMigration"], .btn-run, button')) {
            steps.push({
                target: '[data-action="runMigration"], .btn-run, button',
                title: 'Uitvoeren',
                content: `
                    <p>Voer pending migraties uit om de database bij te werken.</p>
                    <p><strong>Let op:</strong> Maak eerst een backup!</p>
                `,
                position: 'bottom'
            });
        }

        if (steps.length > 0) {
            LibTip.tour('tools-migrations', steps);
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // 15. FORM WIZARD TOUR (Developer)
    // ═══════════════════════════════════════════════════════════════════════════

    function initFormWizardTour() {
        if (!isDeveloper()) return;

        const steps = [];

        // Table select
        if (document.querySelector('#tableSelect, [name="table"], select')) {
            steps.push({
                target: '#tableSelect, [name="table"], select',
                title: 'Tabel kiezen',
                content: `
                    <p>Selecteer de databasetabel waarvoor je een formulier wilt genereren.</p>
                `,
                position: 'bottom'
            });
        }

        // Field config
        if (document.querySelector('#fieldConfig, .field-config, .field-list')) {
            steps.push({
                target: '#fieldConfig, .field-config, .field-list',
                title: 'Velden configureren',
                content: `
                    <p>Bepaal voor elk veld:</p>
                    <ul style="margin: 8px 0; padding-left: 20px;">
                        <li>Of het in het formulier komt</li>
                        <li>Het label/caption</li>
                        <li>Het invoertype</li>
                        <li>Validatieregels</li>
                    </ul>
                `,
                position: 'right'
            });
        }

        // Generate button
        if (document.querySelector('#generateBtn, .btn-generate, [type="submit"]')) {
            steps.push({
                target: '#generateBtn, .btn-generate, [type="submit"]',
                title: 'Genereren',
                content: `
                    <p>Maak de JSON-formulierdefinitie aan.</p>
                    <p>Je kunt de gegenereerde JSON daarna handmatig aanpassen.</p>
                `,
                position: 'bottom'
            });
        }

        if (steps.length > 0) {
            LibTip.tour('tools-formwiz', steps);
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // 16. STORYBOOK TOUR (Developer)
    // ═══════════════════════════════════════════════════════════════════════════

    function initStorybookTour() {
        if (!isDeveloper()) return;

        const steps = [];

        // Component navigation
        if (document.querySelector('.nav-sidebar, .component-nav, nav')) {
            steps.push({
                target: '.nav-sidebar, .component-nav, nav',
                title: 'Component navigatie',
                content: `
                    <p>Alle UI-componenten, gegroepeerd per type:</p>
                    <ul style="margin: 8px 0; padding-left: 20px;">
                        <li>Library componenten (lib-*)</li>
                        <li>CMA componenten (cma-*)</li>
                        <li>Ontwerpsysteem (kleuren, typografie)</li>
                    </ul>
                `,
                position: 'right'
            });
        }

        // Playground
        if (document.querySelector('.playground, .component-demo, .demo-area')) {
            steps.push({
                target: '.playground, .component-demo, .demo-area',
                title: 'Playground',
                content: `
                    <p>Test componenten interactief.</p>
                    <p>Pas eigenschappen aan en zie direct het resultaat.</p>
                `,
                position: 'left'
            });
        }

        // Code preview
        if (document.querySelector('.code-preview, pre, code')) {
            steps.push({
                target: '.code-preview, pre, code',
                title: 'Code voorbeeld',
                content: `
                    <p>Kopieer de code om het component te gebruiken.</p>
                    <p>Inclusief alle benodigde attributen en events.</p>
                `,
                position: 'top'
            });
        }

        if (steps.length > 0) {
            LibTip.tour('tools-storybook', steps);
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // 17. IMAGE UPLOAD TOUR
    // ═══════════════════════════════════════════════════════════════════════════

    function initImageUploadTour() {
        const steps = [];

        // Dropzone
        if (document.querySelector('.dropzone, .upload-area, #dropzone')) {
            steps.push({
                target: '.dropzone, .upload-area, #dropzone',
                title: 'Afbeelding uploaden',
                content: `
                    <p><strong>Sleep</strong> een afbeelding hierheen, of <strong>klik</strong> om te bladeren.</p>
                `,
                position: 'bottom'
            });
        }

        // File info
        steps.push({
            target: '.dropzone, .upload-area, #dropzone',
            title: 'Ondersteunde formaten',
            content: `
                <p>Toegestane bestandstypen:</p>
                <ul style="margin: 8px 0; padding-left: 20px;">
                    <li>JPG / JPEG</li>
                    <li>PNG</li>
                    <li>GIF</li>
                </ul>
                <p>Maximale grootte: 5MB</p>
            `,
            position: 'bottom'
        });

        // Crop tool (if visible)
        if (document.querySelector('.crop-tool, #cropArea, .jcrop-holder')) {
            steps.push({
                target: '.crop-tool, #cropArea, .jcrop-holder',
                title: 'Bijsnijden',
                content: `
                    <p>Sleep de hoeken om het bijsnijgebied aan te passen.</p>
                    <p>De afbeelding wordt geschaald naar het vereiste formaat.</p>
                `,
                position: 'right'
            });
        }

        if (steps.length > 0) {
            LibTip.tour('imageupload', steps);
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // 18. PREFERENCES TOUR
    // ═══════════════════════════════════════════════════════════════════════════

    function initPreferencesTour() {
        const steps = [];

        // Theme select
        if (document.querySelector('#themeSelect, [name="theme"], .theme-selector')) {
            steps.push({
                target: '#themeSelect, [name="theme"], .theme-selector',
                title: 'Thema',
                content: `
                    <p>Kies je voorkeursthema:</p>
                    <ul style="margin: 8px 0; padding-left: 20px;">
                        <li><strong>Licht</strong> - Klassiek licht thema</li>
                        <li><strong>Donker</strong> - Makkelijker voor de ogen</li>
                        <li><strong>Systeem</strong> - Volgt je systeeminstelling</li>
                    </ul>
                `,
                position: 'right'
            });
        }

        // Popup style
        if (document.querySelector('#popupStyle, [name="popupStyle"], .popup-style')) {
            steps.push({
                target: '#popupStyle, [name="popupStyle"], .popup-style',
                title: 'Popup stijl',
                content: `
                    <p>Kies hoe records openen:</p>
                    <ul style="margin: 8px 0; padding-left: 20px;">
                        <li><strong>Zijpaneel</strong> - Schuift in vanaf rechts</li>
                        <li><strong>Popup</strong> - Opent in een venster</li>
                    </ul>
                `,
                position: 'right'
            });
        }

        if (steps.length > 0) {
            LibTip.tour('preferences', steps);
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // 19. KEYBOARD SHORTCUTS TIP
    // ═══════════════════════════════════════════════════════════════════════════

    function showKeyboardShortcutsTip() {
        LibTip.show({
            id: 'keyboard-shortcuts',
            target: 'body',
            title: 'Sneltoetsen',
            content: `
                <p>Handige sneltoetsen:</p>
                <table style="margin: 8px 0; width: 100%;">
                    <tr><td><kbd>Ctrl+S</kbd></td><td>Opslaan</td></tr>
                    <tr><td><kbd>Ctrl+N</kbd></td><td>Nieuw record</td></tr>
                    <tr><td><kbd>Escape</kbd></td><td>Annuleren</td></tr>
                    <tr><td><kbd>F2</kbd></td><td>Bewerken</td></tr>
                    <tr><td><kbd>Delete</kbd></td><td>Verwijderen</td></tr>
                    <tr><td><kbd>Ctrl+F</kbd></td><td>Zoeken</td></tr>
                </table>
            `,
            position: 'bottom'
        });
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // 20. SUBFORM TIP
    // ═══════════════════════════════════════════════════════════════════════════

    function showSubformTip() {
        const subform = document.querySelector('.subform-container, .subform, [data-subform]');
        if (subform) {
            LibTip.show({
                id: 'subform-usage',
                target: '.subform-container, .subform, [data-subform]',
                title: 'Subformulieren',
                content: `
                    <p>Dit zijn gerelateerde records.</p>
                    <p><strong>+</strong> = Nieuw record toevoegen</p>
                    <p><strong>Dubbelklik</strong> = Record bewerken</p>
                    <p><strong>Delete</strong> = Record verwijderen</p>
                `,
                position: 'top'
            });
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // INITIALIZATION
    // ═══════════════════════════════════════════════════════════════════════════

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTours);
    } else {
        initTours();
    }

    // Export for manual triggering
    window.CMATours = {
        // Page tours
        dashboard: initDashboardTour,
        mainNavigation: initMainNavigationTour,
        form: initFormTour,
        reportDesigner: initReportDesignerTips,
        reportsList: initReportsListTour,
        tools: initToolsPageTour,
        preferences: initPreferencesTour,
        imageUpload: initImageUploadTour,

        // Form-specific tours
        usersForm: initUsersFormTour,
        groupsForm: initGroupsFormTour,

        // Tool tours
        queryTool: initQueryToolTour,
        clearCache: initClearCacheTip,
        dbSummary: initDbSummaryTour,
        migrations: initMigrationsTour,
        formWizard: initFormWizardTour,
        storybook: initStorybookTour,

        // Tips
        inlineEdit: showInlineEditTip,
        keyboardShortcuts: showKeyboardShortcutsTip,
        subform: showSubformTip,

        // Utility
        restart: function(tourId) {
            if (typeof LibTip !== 'undefined') {
                LibTip.reset(tourId).then(() => {
                    initTours();
                });
            }
        },

        // Re-run all tours for current page
        reinit: initTours
    };

})();
