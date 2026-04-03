<?php
/**
 * Report Designer
 *
 * Visual query builder and report generator with 6-step wizard:
 * 1. Tables - Select tables and view relationships
 * 2. Fields - Configure fields, aliases, and filters
 * 3. Parameters - Define runtime parameters (advanced mode)
 * 4. Sorting - Configure sorting and grouping
 * 5. Output - Select output format
 * 6. Save - Save the report with name and visibility settings
 *
 * URL Parameters:
 * - id: Report ID to load existing report
 * - mode: 'quick' or 'advanced' (default: show mode selection)
 */

use App\Library\Request;
use App\Library\Response;
use Cma\SecurityHelper;
use Cma\ToolbarHelper;
use Cma\CmaRepository;
use Cma\ReportStorage;
use Cma\QueryBuilder;

require_once __DIR__ . '/bootstrap.inc';

// Must be logged in
if (!SecurityHelper::isLoggedIn()) {
    header('Location: default.php');
    exit;
}

Response::noCache();

// Get parameters
$reportId = Request::queryId('id');
$mode = Request::query('mode', ''); // 'quick' or 'advanced'
$userId = SecurityHelper::getCurrentUserId();

// Load existing report if ID provided
$existingReport = null;
if (!empty($reportId)) {
    $existingReport = ReportStorage::load($reportId, $userId);
    if ($existingReport) {
        $mode = $existingReport['mode'] ?? 'advanced';
    }
}

// Get available databases
$databases = CmaRepository::getSelectableDatabases();
$defaultDatabaseId = CmaRepository::getDefaultDatabaseId();

// Prepare wizard tabs configuration (without step numbers - shown in step indicator)
$wizardTabs = [
    ['title' => 'Tabellen', 'completed' => false, 'tooltip' => 'Selecteer de databron en tabellen voor je rapport. Sleep tabellen naar het canvas en leg relaties tussen tabellen.'],
    ['title' => 'Parameters', 'completed' => false, 'tooltip' => 'Definieer parameters waarmee gebruikers het rapport kunnen filteren bij uitvoering.'],
    ['title' => 'Velden', 'completed' => false, 'tooltip' => 'Kies welke velden in het rapport worden getoond en pas kolomnamen en functies aan.'],
    ['title' => 'Sortering', 'completed' => false, 'tooltip' => 'Bepaal de sorteervolgorde en groepering van de rapportgegevens.'],
    ['title' => 'Uitvoer', 'completed' => false, 'tooltip' => 'Bekijk een voorbeeld van het rapport en exporteer naar Excel of andere formaten.'],
    ['title' => 'Bewaar', 'completed' => false, 'tooltip' => 'Sla het rapport op met een naam en omschrijving zodat je het later kunt uitvoeren of bewerken.']
];

// Quick mode skips step 2 (parameters)
$isQuickMode = ($mode === 'quick');

// Page header
cma_html_header('Rapport ontwerper', '<link rel="stylesheet" href="assets/css/report-designer.css">', false);

// Include ToolbarHelper for toolbar generation
ToolbarHelper::writeJS();
?>
<?php
cma_script('webcomponents/shared-icons.js');
cma_script('webcomponents/cma-schema-canvas.js');
cma_script('webcomponents/cma-field-config.js');
cma_script('webcomponents/cma-conditions-panel.js');
cma_script('webcomponents/cma-param-config.js');
cma_script('webcomponents/cma-sort-config.js');
cma_script('webcomponents/cma-group-config.js');
cma_script('../library/sql-utils.js');
cma_script('webcomponents/cma-query-preview.js');
cma_script('webcomponents/cma-sql-editor.js');
?>
</HEAD>
<BODY class="contentbody cma-form report-designer-page">

<?php
// Toolbar with Save and Run buttons
ToolbarHelper::start(true);
ToolbarHelper::title('Rapport ontwerper');
ToolbarHelper::separator();
ToolbarHelper::status('Bouw queries en genereer rapporten voor database:');
ToolbarHelper::startRight();
?>
<select id="databaseSelect" class="toolbar-database-select" data-tooltip="Selecteer database">
    <?php foreach ($databases as $db): ?>
    <option value="<?= $db['id'] ?>" <?= $db['id'] == $defaultDatabaseId ? 'selected' : '' ?>>
        <?= htmlspecialchars($db['title']) ?>
    </option>
    <?php endforeach; ?>
</select>
<?php
ToolbarHelper::button('javascript:void(0)', 'lnr-file-empty', true, 'Nieuw', 'Nieuw rapport', 'newReportBtn');
ToolbarHelper::button('javascript:void(0)', 'lnr-save', true, 'Opslaan', 'Rapport opslaan', 'saveReportBtn');
// Uitvoeren button commented out - functionality duplicates Results tab behavior
// ToolbarHelper::button('javascript:void(0)', 'lnr-play', true, 'Uitvoeren', 'Rapport uitvoeren', 'runReportBtn');
ToolbarHelper::separator();
?>
<span id="reportNameDisplay" style="font-weight: 600; margin-right: 15px; display: none;"></span>
<?php
ToolbarHelper::end(true);
?>

<!-- Message container for lib-message notifications -->
<div id="messageContainer" style="position: fixed; top: 60px; right: 20px; z-index: 1000; max-width: 400px;"></div>

<div class="report-designer">
    <!-- Mode Selection Dialog -->
    <lib-dialog id="modeDialog" heading="Kies rapport type" size="large">
        <div class="mode-selection mode-selection-4">
            <div class="mode-option" data-mode="load">
                <span class="mode-icon lnr lnr-folder"></span>
                <div class="mode-title">Rapport laden</div>
                <div class="mode-desc">Open een eerder opgeslagen rapport om te bewerken of uit te voeren</div>
            </div>
            <div class="mode-option" data-mode="quick">
                <span class="mode-icon lnr lnr-rocket"></span>
                <div class="mode-title">Snel</div>
                <div class="mode-desc">Snel een rapport maken met tabellen, velden en filters</div>
            </div>
            <div class="mode-option" data-mode="advanced">
                <span class="mode-icon lnr lnr-cog"></span>
                <div class="mode-title">Geavanceerd</div>
                <div class="mode-desc">Volledig rapport met parameters, groeperen en totalen</div>
            </div>
            <div class="mode-option" data-mode="sql">
                <span class="mode-icon lnr lnr-code"></span>
                <div class="mode-title">Bestaande SQL</div>
                <div class="mode-desc">Plak een bestaande SQL-query om te gebruiken als rapport</div>
            </div>
        </div>
    </lib-dialog>

    <!-- Paste SQL Dialog -->
    <lib-dialog id="pasteSqlDialog" heading="SQL plakken" size="medium">
        <div class="paste-sql-content">
            <p style="margin-bottom: 15px;">Plak hieronder een bestaande SELECT-query:</p>
            <textarea id="pasteSqlInput" class="form-control" rows="10" placeholder="SELECT * FROM tblUsers WHERE status = 1"
                      style="width: 100%; font-family: monospace; font-size: var(--font-size);"></textarea>
            <p style="margin-top: 10px; font-size: var(--font-size-sm); color: var(--text-muted);">
                Let op: alleen SELECT-queries zijn toegestaan. De query wordt geanalyseerd en omgezet naar een rapport.
            </p>
        </div>
        <div slot="footer">
            <button class="btn btn-cancel" onclick="document.getElementById('pasteSqlDialog').close()">Annuleren</button>
            <button class="btn btn-primary" id="pasteSqlConfirmBtn">
                <span class="lnr lnr-checkmark-circle"></span> Analyseren
            </button>
        </div>
    </lib-dialog>

    <!-- Load Report Dialog -->
    <lib-dialog id="loadReportDialog" heading="Rapport laden" size="medium">
        <div id="reportListContainer">
            <lib-loader id="reportListLoader" text="Rapporten laden..." active></lib-loader>
            <div id="reportList" style="display:none;"></div>
        </div>
        <div slot="footer">
            <button class="btn btn-cancel" onclick="document.getElementById('loadReportDialog').close()">Annuleren</button>
        </div>
    </lib-dialog>

    <!-- Save Report Dialog -->
    <lib-dialog id="saveReportDialog" heading="Rapport opslaan" size="small">
        <div class="save-dialog-content">
            <div class="form-group" style="margin-bottom: 15px;">
                <label for="saveDialogReportName" style="display: block; margin-bottom: 5px; font-weight: 500;">Rapportnaam</label>
                <input type="text" id="saveDialogReportName" class="form-control" placeholder="Mijn rapport" style="width: 100%; padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 4px;">
            </div>
            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                    <input type="checkbox" id="saveDialogIsGlobal">
                    <span>Zichtbaar voor iedereen</span>
                </label>
            </div>
        </div>
        <div slot="footer">
            <button class="btn btn-cancel" onclick="document.getElementById('saveReportDialog').close()">Annuleren</button>
            <button class="btn btn-primary" id="saveDialogConfirmBtn">
                <span class="lnr lnr-save"></span> Opslaan
            </button>
        </div>
    </lib-dialog>

    <!-- Unsaved Changes Confirmation Dialog -->
    <lib-dialog id="unsavedChangesDialog" heading="Niet-opgeslagen wijzigingen" size="small">
        <p>Er zijn niet-opgeslagen wijzigingen. Wil je deze eerst opslaan?</p>
        <div slot="footer">
            <button class="btn btn-cancel" id="unsavedDiscardBtn">Negeren</button>
            <button class="btn btn-primary" id="unsavedSaveBtn">
                <span class="lnr lnr-save"></span> Opslaan
            </button>
        </div>
    </lib-dialog>

    <!-- Parameter Input Dialog -->
    <lib-dialog id="paramInputDialog" heading="Parameterwaarden invoeren" size="medium">
        <div id="paramInputContent" class="param-input-form">
            <!-- Parameter inputs will be generated dynamically -->
        </div>
        <div slot="footer">
            <button class="btn btn-cancel" onclick="document.getElementById('paramInputDialog').close()">Annuleren</button>
            <button class="btn btn-primary" id="paramInputRunBtn">
                <span class="lnr lnr-play"></span> Uitvoeren
            </button>
        </div>
    </lib-dialog>

    <!-- Field Search Dialog -->
    <lib-dialog id="fieldSearchDialog" heading="Veld zoeken" size="medium">
        <div class="field-search-content">
            <div class="form-group" style="margin-bottom: 15px;">
                <label for="fieldSearchInput" style="display: block; margin-bottom: 5px;">Zoek op veldnaam, omschrijving of tabel:</label>
                <input type="text" id="fieldSearchInput" class="form-control" placeholder="Bijv. naam, datum, omschrijving..." style="width: 100%; padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 4px;">
            </div>
            <div id="fieldSearchResults" style="max-height: 350px; overflow-y: auto; border: 1px solid var(--border-color); border-radius: 4px;">
                <div class="empty-state" style="padding: 30px; text-align: center; color: var(--text-muted);">
                    <div style="margin-bottom: 10px;">Typ minimaal 2 tekens om te zoeken</div>
                    <div style="font-size: var(--font-size-sm);">Je kunt hier binnen alle teksten van de CMA zoeken naar een veldnaam of een formulier. Dus als een scherm een titel van een veld heeft, maar je weet de veldnaam niet, kun je hier daarop zoeken.</div>
                </div>
            </div>
        </div>
        <div slot="footer">
            <button class="btn btn-cancel" onclick="document.getElementById('fieldSearchDialog').close()">Sluiten</button>
        </div>
    </lib-dialog>

    <!-- Main Content -->
    <div class="report-designer-content">
        <!-- Main Tabs: Ontwerper / Resultaat -->
        <cma-tabs id="mainTabs" tabs='["Ontwerper", "Resultaat"]'>

        <!-- Ontwerper Tab Content -->
        <div slot="tab-0" class="main-tab-content" id="designerTabContent">
            <!-- Wizard Container -->
            <div class="report-designer-wizard step-0">
                <cma-tabs id="wizardTabs" mode="wizard" tabs='<?= htmlspecialchars(json_encode($wizardTabs)) ?>'>
                <!-- Step 1: Tables -->
                <div slot="tab-0" class="report-designer-step">
                    <div class="step-layout-split">
                        <!-- Table List -->
                        <div class="table-list-panel">
                            <cma-toolbar id="tableListToolbar" class="table-list-toolbar">
                                <left>
                                    <span class="tb-btn" id="fieldSearchBtn" data-tooltip="Veldzoeker - zoekt binnen alle velden van de CMA op naam en omschrijving" data-tooltip-pos="left">
                                        <a href="javascript:void(0)">
                                            <span class="lnr lnr-layers"></span>
                                        </a>
                                    </span>
                                    <lib-search-input id="tableSearch" placeholder="Zoek op tabelnaam"></lib-search-input>
                                </left>
                            </cma-toolbar>
                            <div class="table-list-items" id="tableListItems">
                                <lib-loader id="tableLoader" text="Tabellen laden..." active></lib-loader>
                            </div>
                        </div>

                        <!-- Vertical Splitter -->
                        <div class="vertical-splitter" id="verticalSplitter"></div>

                        <!-- Schema Canvas -->
                        <div class="schema-canvas-container">
                            <cma-schema-canvas id="schemaCanvas" show-columns="true" max-columns="8" selectable-fields="true"></cma-schema-canvas>
                            <!-- Inline next button for step 1 -->
                            <div class="inline-step-nav">
                                <button type="button" class="btn btn-nav btn-next" id="btnNextStepInline">
                                    Volgende <span class="lnr lnr-chevron-right"></span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Parameters (Advanced only) -->
                <div slot="tab-1" class="report-designer-step">
                    <div class="step-layout-single">
                        <p style="margin-bottom: 15px; margin-left: 15px;">Definieer parameters die de gebruiker kan invullen voor het tonen van het rapport, bij filtering kunnen deze worden gebruikt.</p>
                        <cma-param-config id="paramConfigComponent"></cma-param-config>
                    </div>
                </div>

                <!-- Step 3: Fields -->
                <div slot="tab-2" class="report-designer-step">
                    <div class="step-layout-fields" id="stepLayoutFields">
                        <div class="fields-main">
                            <cma-toolbar id="fieldListToolbar" class="field-list-toolbar">
                                <left>
                                    <lib-search-input id="fieldSearch" placeholder="Zoek velden..."></lib-search-input>
                                    <label id="selectedOnlyLabel" class="toolbar-checkbox">
                                        <input type="checkbox" id="selectedOnlyCheckbox"> Alleen geselecteerde velden
                                    </label>
                                </left>
                            </cma-toolbar>
                            <cma-field-config id="fieldConfigComponent"></cma-field-config>
                        </div>
                        <cma-fold
                            orientation="vertical"
                            target=".fields-conditions"
                            min-size="200"
                            max-size="600"
                            storage-key="report_conditions_fold"
                            reverse>
                        </cma-fold>
                        <div class="fields-conditions" id="fieldsConditions">
                            <cma-conditions-panel id="conditionsPanel"></cma-conditions-panel>
                        </div>
                    </div>
                </div>

                <!-- Step 4: Sorting & Grouping -->
                <div slot="tab-3" class="report-designer-step">
                    <div class="step-layout-single">
                        <div class="sort-group-layout">
                            <!-- Sorting -->
                            <div class="sort-section">
                                <div class="sort-section-header">Sortering</div>
                                <cma-sort-config id="sortConfigComponent"></cma-sort-config>
                            </div>

                            <!-- Grouping -->
                            <div class="group-section">
                                <div class="group-section-header">
                                    Groepering
                                    <span class="advanced-only" style="font-size: var(--font-size-sm); font-weight: normal; padding-left: 10px;">(geavanceerd)</span>
                                    <span class="help-icon" data-tooltip="Groepering groepeert rijen met dezelfde waarden samen. Velden die niet in de groepering staan worden geaggregeerd."><span class="lnr lnr-question-circle"></span></span>
                                </div>
                                <cma-group-config id="groupConfigComponent"></cma-group-config>
                            </div>
                        </div>

                        <!-- Query Options (advanced mode only) -->
                        <div class="query-options-section" id="queryOptionsSection">
                            <div class="query-options-header">
                                Query opties
                                <span class="help-icon" data-tooltip="Beperk of filter de query resultaten">
                                    <span class="lnr lnr-question-circle"></span>
                                </span>
                            </div>
                            <div class="query-options-content">
                                <label class="checkbox-label">
                                    <input type="checkbox" id="distinctCheckbox">
                                    <span>Alleen unieke rijen (DISTINCT)</span>
                                </label>
                                <div class="topn-row">
                                    <label for="topNInput">Maximum aantal rijen:</label>
                                    <input type="number" id="topNInput" min="1" max="100000" placeholder="Alle" style="width: 120px;">
                                    <span class="help-text">(leeg = geen limiet)</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 5: Output -->
                <div slot="tab-4" class="report-designer-step">
                    <div class="step-layout-single">
                        <div class="output-config" style="grid-template-columns: 1fr;">
                            <!-- Format Selection -->
                            <div class="output-section">
                                <h3>Uitvoerformaat</h3>
                                <div id="exportLimitNotice" class="export-limit-notice" style="display: none; padding: 8px 12px; background: #fff8e6; border: 1px solid #ffd666; border-radius: 4px; margin-bottom: 12px; font-size: var(--font-size-sm); color: #666;">
                                    <strong>Let op:</strong> Bij meer dan 15.000 records is alleen CSV export beschikbaar vanwege prestatiebeperkingen.
                                </div>
                                <div class="format-options">
                                    <label class="format-option selected">
                                        <input type="radio" name="outputFormat" value="table" checked>
                                        <div>
                                            <div class="format-label">Tabel</div>
                                            <div class="format-desc">Bekijk resultaten in een tabel op het scherm</div>
                                        </div>
                                    </label>
                                    <label class="format-option">
                                        <input type="radio" name="outputFormat" value="excel">
                                        <div>
                                            <div class="format-label">Excel</div>
                                            <div class="format-desc">Download als Excel bestand</div>
                                        </div>
                                    </label>
                                    <label class="format-option">
                                        <input type="radio" name="outputFormat" value="csv">
                                        <div>
                                            <div class="format-label">CSV</div>
                                            <div class="format-desc">Download als komma-gescheiden tekst</div>
                                        </div>
                                    </label>
                                    <label class="format-option">
                                        <input type="radio" name="outputFormat" value="json">
                                        <div>
                                            <div class="format-label">JSON</div>
                                            <div class="format-desc">Download als JSON bestand</div>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 6: Save -->
                <div slot="tab-5" class="report-designer-step">
                    <div class="step-layout-single">
                        <div class="save-step-content">
                            <p class="save-step-intro">Sla je rapport op zodat je het later kunt uitvoeren of bewerken.</p>
                            <div class="save-step-form">
                                <div class="form-group">
                                    <label for="saveStepReportName">Rapportnaam</label>
                                    <input type="text" id="saveStepReportName" class="form-control" placeholder="Mijn rapport">
                                </div>
                                <div class="form-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" id="saveStepIsGlobal">
                                        <span>Zichtbaar voor iedereen</span>
                                    </label>
                                </div>
                                <div class="form-actions">
                                    <button type="button" class="btn btn-primary" id="saveReportBtnStep">
                                        <span class="lnr lnr-save"></span> Rapport opslaan
                                    </button>
                                </div>
                                <div class="save-step-status" id="saveStepStatus" style="display: none;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </cma-tabs>

            <!-- Step Navigation -->
            <div class="step-navigation" id="stepNavigation">
                <button type="button" class="btn btn-nav btn-prev" id="btnPrevStep">
                    <span class="lnr lnr-chevron-left"></span> Vorige
                </button>
                <button type="button" class="btn btn-nav btn-next" id="btnNextStep">
                    Volgende <span class="lnr lnr-chevron-right"></span>
                </button>
            </div>
            </div>
        </div>

        <!-- Resultaat Tab Content -->
        <div slot="tab-1" class="main-tab-content" id="resultsTabContent">
            <div class="results-split-view">
                <!-- SQL Section with cma-query-preview for editing -->
                <cma-query-preview id="resultsQueryPreview" row-limit="1000" editable></cma-query-preview>
            </div>
        </div>
        </cma-tabs>
    </div>
</div>

<script>
(function() {
    'use strict';

    // Report state
    const state = {
        mode: <?= json_encode($mode ?: null) ?>,
        reportId: <?= json_encode($reportId ?: null) ?>,
        existingReport: <?= json_encode($existingReport) ?>,
        databaseId: <?= json_encode($defaultDatabaseId) ?>,
        tables: [],
        selectedTables: [],
        fields: [],
        conditions: [],  // Separate conditions array for WHERE clause (allows multiple per field, reordering)
        parameters: [],
        sorting: [],
        grouping: [],
        outputFormat: 'table',
        reportName: '',
        isGlobal: false,
        isDirty: false,  // Track unsaved changes
        lastSavedState: null,  // Snapshot of last saved state
        rawSql: '',  // SQL mode: raw SQL query
        parsedJoins: null,  // Parsed joins from SQL (used instead of computing from relationships)
        tablePositions: {},  // Canvas table positions {tableName: {x, y}}
        tableAliases: {},  // Table aliases {tableName: alias}
        tableSizes: {},  // Table sizes in canvas {tableName: {width, height}}
        distinct: false,  // DISTINCT checkbox
        topN: null,  // TOP N input (null = no limit)
        activeMainTab: 'designer',  // 'designer' or 'results'
        panelPosition: null,  // Relationships panel position { left, top }
        currentRowCount: 0  // Track row count for export format limitations
    };

    // Constants
    const MAX_ROWS_FOR_FULL_EXPORT = 15000;

    // Sync debounce timer for SQL parsing
    let sqlSyncDebounceTimer = null;
    const SQL_SYNC_DEBOUNCE_MS = 500;

    /**
     * Event-based communication system
     * Components dispatch events on document, all interested parties listen.
     * Each area subscribes to the events it cares about in setupReactiveListeners().
     *
     * State change events: TABLES_, FIELDS_, CONDITIONS_, PARAMETERS_,
     *   SORTING_, GROUPING_, OPTIONS_, RELATIONSHIPS_, ALIASES_CHANGED
     * Sync events: SQL_CHANGED, SYNC_COMPLETE
     */
    const CmaEvents = {
        // State change events (dispatched when state mutates)
        TABLES_CHANGED: 'cma:tables-changed',
        FIELDS_CHANGED: 'cma:fields-changed',
        CONDITIONS_CHANGED: 'cma:conditions-changed',
        PARAMETERS_CHANGED: 'cma:parameters-changed',
        SORTING_CHANGED: 'cma:sorting-changed',
        GROUPING_CHANGED: 'cma:grouping-changed',
        OPTIONS_CHANGED: 'cma:options-changed',
        RELATIONSHIPS_CHANGED: 'cma:relationships-changed',
        ALIASES_CHANGED: 'cma:aliases-changed',

        // Derived/sync events
        SQL_CHANGED: 'cma:sql-changed',
        SYNC_COMPLETE: 'cma:sync-complete'
    };

    // --- Event batching for bulk operations (load report, SQL sync) ---
    let _batchingEvents = false;
    let _pendingEvents = new Map(); // eventType -> last detail

    function beginBatch() {
        _batchingEvents = true;
        _pendingEvents.clear();
    }

    function endBatch() {
        _batchingEvents = false;
        const pending = new Map(_pendingEvents);
        _pendingEvents.clear();
        pending.forEach((detail, eventType) => {
            dispatchCmaEvent(eventType, { ...detail, source: 'batch' });
        });
    }

    /**
     * Dispatch a CMA event on document (queued during batches)
     * @param {string} eventType - One of CmaEvents constants
     * @param {Object} detail - Event detail data
     */
    function dispatchCmaEvent(eventType, detail = {}) {
        if (_batchingEvents) {
            _pendingEvents.set(eventType, detail);
            return;
        }
        cmaLog.log('CMA Event:', eventType, detail);
        document.dispatchEvent(new CustomEvent(eventType, {
            bubbles: true,
            detail: { ...detail, timestamp: Date.now() }
        }));
    }

    /**
     * Subscribe to a CMA event
     * @param {string} eventType - One of CmaEvents constants
     * @param {Function} handler - Receives detail object
     */
    function onCmaEvent(eventType, handler) {
        document.addEventListener(eventType, (e) => handler(e.detail || {}));
    }

    // DOM elements
    const elements = {
        wizardTabs: document.getElementById('wizardTabs'),
        databaseSelect: document.getElementById('databaseSelect'),
        tableSearch: document.getElementById('tableSearch'),
        fieldSearch: document.getElementById('fieldSearch'),
        tableListItems: document.getElementById('tableListItems'),
        tableLoader: document.getElementById('tableLoader'),
        schemaCanvas: document.getElementById('schemaCanvas'), // cma-schema-canvas component
        fieldConfigComponent: document.getElementById('fieldConfigComponent'),
        conditionsPanel: document.getElementById('conditionsPanel'),
        paramConfigComponent: document.getElementById('paramConfigComponent'),
        sortConfigComponent: document.getElementById('sortConfigComponent'),
        groupConfigComponent: document.getElementById('groupConfigComponent'),
        newReportBtn: document.getElementById('newReportBtn'),
        saveReportBtn: document.getElementById('saveReportBtn'),
        saveReportBtnStep: document.getElementById('saveReportBtnStep'),
        runReportBtn: document.getElementById('runReportBtn'),
        modeDialog: document.getElementById('modeDialog'),
        loadReportDialog: document.getElementById('loadReportDialog'),
        reportListContainer: document.getElementById('reportListContainer'),
        reportListLoader: document.getElementById('reportListLoader'),
        reportList: document.getElementById('reportList'),
        reportNameDisplay: document.getElementById('reportNameDisplay'),
        // Save dialog elements (for toolbar save button)
        saveReportDialog: document.getElementById('saveReportDialog'),
        saveDialogReportName: document.getElementById('saveDialogReportName'),
        saveDialogIsGlobal: document.getElementById('saveDialogIsGlobal'),
        saveDialogConfirmBtn: document.getElementById('saveDialogConfirmBtn'),
        // Save step elements (inline form in wizard)
        saveStepReportName: document.getElementById('saveStepReportName'),
        saveStepIsGlobal: document.getElementById('saveStepIsGlobal'),
        saveStepStatus: document.getElementById('saveStepStatus'),
        // Parameter input dialog elements
        paramInputDialog: document.getElementById('paramInputDialog'),
        paramInputContent: document.getElementById('paramInputContent'),
        paramInputRunBtn: document.getElementById('paramInputRunBtn'),
        // Unsaved changes dialog elements
        unsavedChangesDialog: document.getElementById('unsavedChangesDialog'),
        unsavedDiscardBtn: document.getElementById('unsavedDiscardBtn'),
        unsavedSaveBtn: document.getElementById('unsavedSaveBtn'),
        // Field search dialog elements
        fieldSearchBtn: document.getElementById('fieldSearchBtn'),
        fieldSearchDialog: document.getElementById('fieldSearchDialog'),
        fieldSearchInput: document.getElementById('fieldSearchInput'),
        fieldSearchResults: document.getElementById('fieldSearchResults'),
        // Step navigation
        btnPrevStep: document.getElementById('btnPrevStep'),
        btnNextStep: document.getElementById('btnNextStep'),
        btnNextStepInline: document.getElementById('btnNextStepInline'),
        wizardContainer: document.querySelector('.report-designer-wizard'),
        // Main tabs
        mainTabs: document.getElementById('mainTabs'),
        designerTabContent: document.getElementById('designerTabContent'),
        resultsTabContent: document.getElementById('resultsTabContent'),
        resultsQueryPreview: document.getElementById('resultsQueryPreview')
    };

    // Initialize
    function init() {
        setupEventListeners();
        setupReactiveListeners();
        setupVerticalSplitter();

        // Show mode selection if needed
        if (!state.mode && !state.existingReport && elements.modeDialog) {
            elements.modeDialog.open();
        } else {
            if (state.existingReport) {
                // loadExistingReport handles loadTables internally
                loadExistingReport();
            } else {
                loadTables();
            }
        }
    }

    /**
     * Reactive listener registry
     * Each area subscribes to the events it cares about.
     * This replaces manual update chains scattered across event handlers.
     */
    function setupReactiveListeners() {
        // --- Field Config reacts to table changes ---
        onCmaEvent(CmaEvents.TABLES_CHANGED, (detail) => {
            if (!detail.skipFieldUpdate) {
                updateFieldConfig();
            }
        });

        // --- Schema Canvas reacts to field changes ---
        onCmaEvent(CmaEvents.FIELDS_CHANGED, () => {
            syncFieldSelectionToCanvas();
        });

        // --- Sort/Group Config reacts to field changes ---
        onCmaEvent(CmaEvents.FIELDS_CHANGED, () => {
            updateSortGroupAvailableFields();
        });

        // --- Conditions Panel reacts to field changes ---
        onCmaEvent(CmaEvents.FIELDS_CHANGED, () => {
            updateConditionsPanel();
        });

        // --- Field Config reacts to sorting changes (indicators) ---
        onCmaEvent(CmaEvents.SORTING_CHANGED, () => {
            if (elements.fieldConfigComponent) {
                elements.fieldConfigComponent.setSorting(state.sorting);
            }
        });

        // --- Field Config reacts to grouping changes (indicators) ---
        onCmaEvent(CmaEvents.GROUPING_CHANGED, () => {
            if (elements.fieldConfigComponent) {
                elements.fieldConfigComponent.setGrouping(state.grouping);
            }
        });

        // --- Field Config reacts to parameter changes ---
        onCmaEvent(CmaEvents.PARAMETERS_CHANGED, () => {
            if (elements.fieldConfigComponent) {
                elements.fieldConfigComponent.setParameters(state.parameters);
            }
        });

        // --- SQL Preview reacts to ANY definition change ---
        // Coalesce rapid changes with a short debounce
        const PREVIEW_EVENTS = [
            CmaEvents.TABLES_CHANGED, CmaEvents.FIELDS_CHANGED,
            CmaEvents.CONDITIONS_CHANGED, CmaEvents.PARAMETERS_CHANGED,
            CmaEvents.SORTING_CHANGED, CmaEvents.GROUPING_CHANGED,
            CmaEvents.OPTIONS_CHANGED, CmaEvents.RELATIONSHIPS_CHANGED,
            CmaEvents.ALIASES_CHANGED
        ];
        let previewTimer = null;
        PREVIEW_EVENTS.forEach(eventType => {
            onCmaEvent(eventType, () => {
                clearTimeout(previewTimer);
                previewTimer = setTimeout(() => {
                    syncSqlToResultsEditor();
                }, 50);
            });
        });
    }

    // Vertical splitter drag handling
    function setupVerticalSplitter() {
        const splitter = document.getElementById('verticalSplitter');
        const container = document.querySelector('.step-layout-split');
        if (!splitter || !container) return;

        let isDragging = false;
        let startX = 0;
        let startWidth = 0;

        splitter.addEventListener('mousedown', (e) => {
            isDragging = true;
            startX = e.clientX;
            startWidth = document.querySelector('.table-list-panel').offsetWidth;
            splitter.classList.add('dragging');
            document.body.style.cursor = 'ew-resize';
            document.body.style.userSelect = 'none';
            e.preventDefault();
        });

        document.addEventListener('mousemove', (e) => {
            if (!isDragging) return;

            const delta = e.clientX - startX;
            const newWidth = Math.max(150, Math.min(500, startWidth + delta));
            container.style.gridTemplateColumns = `${newWidth}px 6px 1fr`;
        });

        document.addEventListener('mouseup', () => {
            if (isDragging) {
                isDragging = false;
                splitter.classList.remove('dragging');
                document.body.style.cursor = '';
                document.body.style.userSelect = '';
            }
        });
    }

    function setupEventListeners() {
        // Main tab switching (using cma-tabs component)
        if (elements.mainTabs) {
            elements.mainTabs.addEventListener('tab-select', (e) => {
                const tabName = e.detail.index === 0 ? 'designer' : 'results';
                switchMainTab(tabName, false);  // false = don't update cma-tabs (already done)
            });
        }

        // Results tab: cma-query-preview events
        if (elements.resultsQueryPreview) {
            elements.resultsQueryPreview.addEventListener('refresh', () => {
                runResultsQuery();
            });

            // SQL changed in editor - debounce sync to visual editor
            elements.resultsQueryPreview.addEventListener('sql-change', (e) => {
                const sql = e.detail.sql;
                state.rawSql = sql;
                state.isDirty = true;

                // Dispatch event for any listeners
                dispatchCmaEvent(CmaEvents.SQL_CHANGED, { sql, source: 'editor' });

                // Debounced sync to visual editor
                if (e.detail.requestSync) {
                    clearTimeout(sqlSyncDebounceTimer);
                    sqlSyncDebounceTimer = setTimeout(() => {
                        syncSqlToVisualEditor(sql);
                    }, SQL_SYNC_DEBOUNCE_MS);
                }
            });

            // Explicit sync request (e.g., from sync button)
            elements.resultsQueryPreview.addEventListener('sync-request', (e) => {
                const sql = e.detail.sql || state.rawSql;
                if (sql) {
                    syncSqlToVisualEditor(sql);
                }
            });
        }

        // Database change - check for unsaved changes first
        if (elements.databaseSelect) {
            elements.databaseSelect.addEventListener('change', async (e) => {
                const newDatabaseId = parseInt(elements.databaseSelect.value);

                if (state.isDirty) {
                    // Store the previous value to restore if user cancels
                    const previousValue = state.databaseId;

                    if (!await libConfirm('Er zijn niet-opgeslagen wijzigingen. Weet je zeker dat je van database wilt wisselen? Alle wijzigingen gaan verloren.', {
                        title: 'Niet-opgeslagen wijzigingen',
                        confirmText: 'Wisselen',
                        cancelText: 'Annuleren',
                        type: 'warning'
                    })) {
                        // User cancelled - restore previous selection
                        elements.databaseSelect.value = previousValue;
                        return;
                    }
                }

                state.databaseId = newDatabaseId;
                loadTables();
                clearSelection();
            });
        }

        // Table search
        if (elements.tableSearch) {
            elements.tableSearch.addEventListener('input', filterTables);
        }

        // Field search with debounce
        let fieldSearchTimeout = null;
        if (elements.fieldSearch) {
            elements.fieldSearch.addEventListener('input', (e) => {
                clearTimeout(fieldSearchTimeout);
                fieldSearchTimeout = setTimeout(() => {
                    if (elements.fieldConfigComponent) {
                        elements.fieldConfigComponent.setSearchFilter(e.target.value);
                    }
                }, 200); // 0.2 second delay
            });
        }

        // Selected-only filter checkbox
        const selectedOnlyCheckbox = document.getElementById('selectedOnlyCheckbox');
        if (selectedOnlyCheckbox) {
            selectedOnlyCheckbox.addEventListener('change', (e) => {
                if (elements.fieldConfigComponent) {
                    elements.fieldConfigComponent.setShowSelectedOnly(e.target.checked);
                }
            });
        }

        // Wizard tab navigation validation
        if (elements.wizardTabs) {
            elements.wizardTabs.addEventListener('beforechange', (e) => {
                // Block navigation away from step 0 if no table selected
                if (e.detail.fromIndex === 0 && state.selectedTables.length === 0) {
                    e.preventDefault();
                    showWarning('Selecteer eerst een tabel');
                }
            });

            // Populate save form when entering save step (index 5)
            elements.wizardTabs.addEventListener('tab-select', (e) => {
                if (e.detail.index === 5) {
                    populateSaveForm();
                }
            });
        }

        // Schema canvas events
        if (elements.schemaCanvas) {
            elements.schemaCanvas.addEventListener('table-remove', (e) => {
                const tableName = e.detail.tableName;
                state.selectedTables = state.selectedTables.filter(t => t.name !== tableName);
                state.isDirty = true;
                renderTableList();
                dispatchCmaEvent(CmaEvents.TABLES_CHANGED, { action: 'removed', tableName });
            });

            // Handle manually added/edited relationships
            elements.schemaCanvas.addEventListener('relationship-add', (e) => {
                const { from, to, innerJoin } = e.detail;
                // Add the relationship to the appropriate table in state
                const table = state.selectedTables.find(t => t.name === from.table);
                if (table) {
                    if (!table.relationships) {
                        table.relationships = [];
                    }
                    // Check if this relationship already exists (editing case)
                    const existingIdx = table.relationships.findIndex(r =>
                        r.fkTable === from.table && r.fkColumn === from.column &&
                        r.pkTable === to.table && r.pkColumn === to.column
                    );
                    const relData = {
                        fkTable: from.table,
                        fkColumn: from.column,
                        pkTable: to.table,
                        pkColumn: to.column,
                        innerJoin: innerJoin !== false, // Preserve join type (default to INNER)
                        manual: true
                    };
                    if (existingIdx !== -1) {
                        // Update existing relationship
                        table.relationships[existingIdx] = relData;
                    } else {
                        // Add new relationship
                        table.relationships.push(relData);
                    }
                }
                state.isDirty = true;
                dispatchCmaEvent(CmaEvents.RELATIONSHIPS_CHANGED, { from, to, innerJoin });
            });

            // Handle table position changes (drag)
            elements.schemaCanvas.addEventListener('positions-change', (e) => {
                state.tablePositions = e.detail.positions;
                state.isDirty = true;
            });

            // Handle field selection changes from canvas
            elements.schemaCanvas.addEventListener('field-selection-change', (e) => {
                const { tableName, selectedFields } = e.detail;
                // Update field visibility in state.fields based on canvas selection
                state.fields.forEach(field => {
                    if (field.table === tableName) {
                        field.visible = selectedFields.includes(field.field);
                    }
                });
                // Update field config component
                if (elements.fieldConfigComponent) {
                    elements.fieldConfigComponent.setFields(state.fields);
                }
                state.isDirty = true;
                dispatchCmaEvent(CmaEvents.FIELDS_CHANGED, { fields: state.fields, source: 'canvas' });
            });

            // Handle table alias changes
            elements.schemaCanvas.addEventListener('alias-change', (e) => {
                if (!state.tableAliases) {
                    state.tableAliases = {};
                }
                state.tableAliases = e.detail.allAliases;
                state.isDirty = true;
                dispatchCmaEvent(CmaEvents.ALIASES_CHANGED, { aliases: state.tableAliases });
            });

            // Handle table size changes
            elements.schemaCanvas.addEventListener('table-size-change', (e) => {
                if (!state.tableSizes) {
                    state.tableSizes = {};
                }
                state.tableSizes = e.detail.sizes;
                state.isDirty = true;
            });

            // Handle relationships panel position changes
            elements.schemaCanvas.addEventListener('panel-position-change', (e) => {
                state.panelPosition = e.detail;
                state.isDirty = true;
            });
        }

        // Field config component events
        if (elements.fieldConfigComponent) {
            elements.fieldConfigComponent.addEventListener('change', (e) => {
                // Ignore native input change events that bubble up without detail
                if (!e.detail || !e.detail.fields) return;

                state.fields = e.detail.fields;
                state.isDirty = true;
                dispatchCmaEvent(CmaEvents.FIELDS_CHANGED, { fields: state.fields, source: 'fieldConfig' });
            });

            // Handle add-condition event from field config "+" button
            elements.fieldConfigComponent.addEventListener('add-condition', (e) => {
                const newCondition = {
                    id: 'cond_' + Date.now() + '_' + Math.random().toString(36).substr(2, 5),
                    table: e.detail.table,
                    field: e.detail.field,
                    alias: e.detail.alias || e.detail.field,
                    dataType: e.detail.dataType,
                    typeCategory: e.detail.typeCategory || 'text',
                    operator: '',
                    value: '',
                    logic: state.conditions.length > 0 ? 'AND' : '',
                    prefix: '',
                    suffix: ''
                };
                state.conditions.push(newCondition);
                if (elements.conditionsPanel) {
                    elements.conditionsPanel.setConditions(state.conditions);
                }
                state.isDirty = true;
            });
        }

        // Conditions panel events
        if (elements.conditionsPanel) {
            elements.conditionsPanel.addEventListener('conditions-change', (e) => {
                state.conditions = e.detail.conditions;
                state.isDirty = true;
                dispatchCmaEvent(CmaEvents.CONDITIONS_CHANGED, { conditions: state.conditions });
            });
        }

        // Parameter config component events
        if (elements.paramConfigComponent) {
            elements.paramConfigComponent.addEventListener('change', (e) => {
                state.parameters = e.detail.parameters;
                state.isDirty = true;
                dispatchCmaEvent(CmaEvents.PARAMETERS_CHANGED, { parameters: state.parameters });
            });
        }

        // Sort config component events
        if (elements.sortConfigComponent) {
            elements.sortConfigComponent.addEventListener('change', (e) => {
                state.sorting = e.detail.sorting;
                state.isDirty = true;
                dispatchCmaEvent(CmaEvents.SORTING_CHANGED, { sorting: state.sorting });
            });
        }

        // Group config component events
        if (elements.groupConfigComponent) {
            elements.groupConfigComponent.addEventListener('change', (e) => {
                state.grouping = e.detail.grouping;
                state.isDirty = true;
                dispatchCmaEvent(CmaEvents.GROUPING_CHANGED, { grouping: state.grouping });
            });
        }

        // Initialize Select2 on sort and group config dropdowns
        if (elements.sortConfigComponent && typeof elements.sortConfigComponent.initSelect2 === 'function') {
            elements.sortConfigComponent.initSelect2();
        }
        if (elements.groupConfigComponent && typeof elements.groupConfigComponent.initSelect2 === 'function') {
            elements.groupConfigComponent.initSelect2();
        }

        // Query options: DISTINCT checkbox
        const distinctCheckbox = document.getElementById('distinctCheckbox');
        if (distinctCheckbox) {
            distinctCheckbox.addEventListener('change', (e) => {
                state.distinct = e.target.checked;
                state.isDirty = true;
                dispatchCmaEvent(CmaEvents.OPTIONS_CHANGED, { distinct: state.distinct, topN: state.topN });
            });
        }

        // Query options: TOP N input
        const topNInput = document.getElementById('topNInput');
        if (topNInput) {
            topNInput.addEventListener('change', (e) => {
                const val = parseInt(e.target.value, 10);
                state.topN = (val > 0) ? val : null;
                state.isDirty = true;
                dispatchCmaEvent(CmaEvents.OPTIONS_CHANGED, { distinct: state.distinct, topN: state.topN });
            });
        }

        // Toolbar buttons
        if (elements.newReportBtn) {
            elements.newReportBtn.addEventListener('click', newReport);
        }
        if (elements.saveReportBtn) {
            elements.saveReportBtn.addEventListener('click', openSaveDialog);
        }
        if (elements.saveReportBtnStep) {
            // Step save button uses inline form directly
            elements.saveReportBtnStep.addEventListener('click', saveFromInlineForm);
        }
        if (elements.runReportBtn) {
            elements.runReportBtn.addEventListener('click', runReport);
        }

        // Save dialog confirm button
        if (elements.saveDialogConfirmBtn) {
            elements.saveDialogConfirmBtn.addEventListener('click', confirmSaveReport);
        }

        // Parameter input dialog run button
        if (elements.paramInputRunBtn) {
            elements.paramInputRunBtn.addEventListener('click', () => {
                const paramValues = getParameterValues();
                elements.paramInputDialog.close();
                executeReport(paramValues);
            });
        }

        // Unsaved changes dialog buttons
        if (elements.unsavedDiscardBtn) {
            elements.unsavedDiscardBtn.addEventListener('click', () => {
                elements.unsavedChangesDialog.close();
                resetAndShowModeDialog();
            });
        }
        if (elements.unsavedSaveBtn) {
            elements.unsavedSaveBtn.addEventListener('click', () => {
                elements.unsavedChangesDialog.close();
                // Open save dialog, then reset after save
                state._pendingNewAfterSave = true;
                openSaveDialog();
            });
        }

        // Format options
        document.querySelectorAll('input[name="outputFormat"]').forEach(radio => {
            radio.addEventListener('change', (e) => {
                state.outputFormat = e.target.value;
                document.querySelectorAll('.format-option').forEach(opt => opt.classList.remove('selected'));
                e.target.closest('.format-option').classList.add('selected');
            });
        });

        /**
         * Update export format options based on row count
         * When row count exceeds MAX_ROWS_FOR_FULL_EXPORT, only CSV is available
         */
        function updateExportFormatOptions(rowCount) {
            state.currentRowCount = rowCount;
            const notice = document.getElementById('exportLimitNotice');
            const formatOptions = document.querySelectorAll('.format-option');
            const csvOnly = rowCount > MAX_ROWS_FOR_FULL_EXPORT;

            if (notice) {
                notice.style.display = csvOnly ? 'block' : 'none';
            }

            formatOptions.forEach(option => {
                const radio = option.querySelector('input[type="radio"]');
                if (!radio) return;

                const value = radio.value;
                // CSV and table are always available
                if (value === 'csv' || value === 'table') {
                    option.classList.remove('disabled');
                    radio.disabled = false;
                } else {
                    // Excel, JSON, etc are disabled for large datasets
                    if (csvOnly) {
                        option.classList.add('disabled');
                        radio.disabled = true;
                        // If this option was selected, switch to CSV
                        if (radio.checked) {
                            const csvRadio = document.querySelector('input[name="outputFormat"][value="csv"]');
                            if (csvRadio) {
                                csvRadio.checked = true;
                                csvRadio.dispatchEvent(new Event('change'));
                            }
                        }
                    } else {
                        option.classList.remove('disabled');
                        radio.disabled = false;
                    }
                }
            });
        }
        // Make it accessible for preview data updates
        window.updateExportFormatOptions = updateExportFormatOptions;

        // Mode selection
        document.querySelectorAll('.mode-option').forEach(opt => {
            opt.addEventListener('click', () => {
                const mode = opt.dataset.mode;
                if (mode === 'load') {
                    elements.modeDialog.close();
                    showLoadDialog();
                } else if (mode === 'sql') {
                    elements.modeDialog.close();
                    // Go directly to Resultaat tab with SQL editor
                    enterSqlMode();
                    loadTables(); // Load tables in background for schema info
                } else {
                    state.mode = mode;
                    elements.modeDialog.close();

                    // Clear schema canvas when starting fresh
                    if (elements.schemaCanvas) {
                        elements.schemaCanvas.setSelectedTables([]);
                    }

                    loadTables();
                    updateWizardForMode();
                }
            });
        });

        // Closing the mode dialog without selecting defaults to advanced mode
        if (elements.modeDialog) {
            elements.modeDialog.addEventListener('close', () => {
                if (!state.mode && !state.existingReport) {
                    state.mode = 'advanced';
                    if (elements.schemaCanvas) {
                        elements.schemaCanvas.setSelectedTables([]);
                    }
                    loadTables();
                    updateWizardForMode();
                }
            });
        }

        // Paste SQL dialog confirm
        const pasteSqlConfirmBtn = document.getElementById('pasteSqlConfirmBtn');
        if (pasteSqlConfirmBtn) {
            pasteSqlConfirmBtn.addEventListener('click', () => {
                const sql = document.getElementById('pasteSqlInput').value.trim();
                if (!sql) {
                    showError('Voer een SQL-query in');
                    return;
                }
                processPastedSql(sql);
            });
        }

        // Field search
        if (elements.fieldSearchBtn) {
            elements.fieldSearchBtn.addEventListener('click', openFieldSearchDialog);
        }
        if (elements.fieldSearchInput) {
            let searchTimeout;
            elements.fieldSearchInput.addEventListener('input', () => {
                clearTimeout(searchTimeout);
                // Longer debounce to reduce API calls while typing
                searchTimeout = setTimeout(performFieldSearch, 600);
            });
            // Allow immediate search on Enter key
            elements.fieldSearchInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    clearTimeout(searchTimeout);
                    performFieldSearch();
                }
            });
        }


        // Step navigation buttons
        if (elements.btnPrevStep) {
            elements.btnPrevStep.addEventListener('click', () => {
                if (elements.wizardTabs) {
                    elements.wizardTabs.prevStep();
                }
            });
        }
        if (elements.btnNextStep) {
            elements.btnNextStep.addEventListener('click', () => {
                if (elements.wizardTabs) {
                    elements.wizardTabs.nextStep(false);
                }
            });
        }

        // Inline next button for step 1 (same behavior as btnNextStep)
        if (elements.btnNextStepInline) {
            elements.btnNextStepInline.addEventListener('click', () => {
                if (elements.wizardTabs) {
                    elements.wizardTabs.nextStep(false);
                }
            });
        }

        // Update navigation visibility when tab changes
        if (elements.wizardTabs) {
            elements.wizardTabs.addEventListener('tab-select', (e) => {
                updateStepNavigation(e.detail.index);
            });
            // Initial update
            updateStepNavigation(0);
        }
    }

    /**
     * Update step navigation button visibility based on current step
     */
    function updateStepNavigation(currentIndex) {
        const maxIndex = 5; // 6 tabs (0-5)

        // Default to 0 if undefined
        if (currentIndex === undefined || currentIndex === null) {
            currentIndex = 0;
        }

        // Update step class on wizard container for CSS targeting
        const wizardContainer = elements.wizardContainer;
        if (wizardContainer) {
            // Remove all step-X classes (including step-undefined just in case)
            wizardContainer.classList.remove('step-undefined');
            for (let i = 0; i <= maxIndex; i++) {
                wizardContainer.classList.remove(`step-${i}`);
            }
            // Add current step class
            wizardContainer.classList.add(`step-${currentIndex}`);
        }

        if (elements.btnPrevStep) {
            if (currentIndex === 0) {
                elements.btnPrevStep.classList.add('hidden');
            } else {
                elements.btnPrevStep.classList.remove('hidden');
            }
        }

        if (elements.btnNextStep) {
            if (currentIndex >= maxIndex) {
                elements.btnNextStep.classList.add('hidden');
            } else {
                elements.btnNextStep.classList.remove('hidden');
            }
        }
    }

    // Load tables from API
    // skipBackgroundTasks: skip cache building when loading a report (defer until after load)
    async function loadTables(skipBackgroundTasks = false) {
        elements.tableLoader.setAttribute('active', '');
        elements.tableListItems.innerHTML = '';
        elements.tableListItems.appendChild(elements.tableLoader);

        try {
            const response = await fetch(`api/report-schema.php?action=getTables&database=${state.databaseId}`);
            const data = await response.json();

            if (data.success) {
                state.tables = data.tables;
                renderTableList();

                // Background: build field description cache for field finder
                // Skip during report loading to avoid competing for bandwidth
                if (!skipBackgroundTasks) {
                    buildFieldCacheInBackground();
                }
            } else {
                showError('Fout bij laden tabellen: ' + (data.error || 'Onbekende fout'));
            }
        } catch (err) {
            showError('Fout bij laden tabellen: ' + err.message);
        }
    }

    // Build field description cache in background (non-blocking)
    function buildFieldCacheInBackground() {
        fetch(`api/report-schema.php?action=buildFieldCache&database=${state.databaseId}`)
            .then(r => r.json())
            .then(data => {
                if (data.success && data.cached) {
                    cmaLog.log('[report-designer] Field cache built:', data.fieldCount, 'fields');
                    state.fieldCacheReady = true;
                }
            })
            .catch(err => {
                cmaLog.warn('[report-designer] Failed to build field cache:', err);
            });
    }

    function renderTableList() {
        elements.tableListItems.innerHTML = '';

        const filtered = filterTableList(state.tables, elements.tableSearch.value);

        if (filtered.length === 0) {
            elements.tableListItems.innerHTML = '<div class="empty-state"><div class="empty-text">Geen tabellen gevonden</div></div>';
            return;
        }

        filtered.forEach(table => {
            const item = document.createElement('div');
            item.className = 'table-list-item';
            if (state.selectedTables.some(t => t.name === table.name)) {
                item.classList.add('selected');
            }
            item.innerHTML = `<span class="table-name">${escapeHtml(CMA.displayTableName(table.name))}</span>`;
            item.addEventListener('click', () => toggleTable(table));
            elements.tableListItems.appendChild(item);
        });
    }

    function filterTableList(tables, search) {
        // Always hide system tables like _cma_version
        let filtered = tables.filter(t => !t.name.startsWith('_cma_'));

        if (!search) return filtered;
        const lower = search.toLowerCase();
        return filtered.filter(t => t.name.toLowerCase().includes(lower));
    }

    function filterTables() {
        renderTableList();
    }

    async function toggleTable(table, skipUpdates = false) {
        const index = state.selectedTables.findIndex(t => t.name === table.name);

        // Clear parsed joins when user manually modifies tables (not via sync)
        // This ensures joins are recomputed from relationships
        if (!skipUpdates) {
            state.parsedJoins = null;
        }

        if (index >= 0) {
            // Remove table
            state.selectedTables.splice(index, 1);
            // Also remove from canvas
            if (elements.schemaCanvas) {
                elements.schemaCanvas.removeTable(table.name);
            }
        } else {
            // Add table placeholder immediately for instant feedback
            if (elements.schemaCanvas) {
                elements.schemaCanvas.addTable(table.name); // No schema = shows loading state
            }

            // Add placeholder to state
            const tableData = {
                name: table.name,
                alias: table.name.substring(0, 1).toLowerCase(),
                columns: [],
                primaryKeys: [],
                relationships: [],
                joins: [],
                isLoading: true
            };
            state.selectedTables.push(tableData);
            state.isDirty = true;

            // Fetch schema in background
            try {
                const response = await fetch(`api/report-schema.php?action=getTableSchema&database=${state.databaseId}&table=${encodeURIComponent(table.name)}`);

                // Check if response is OK
                if (!response.ok) {
                    const errorText = await response.text();
                    throw new Error(`HTTP ${response.status}: ${errorText || 'Geen respons'}`);
                }

                // Get response text first for better error handling
                const responseText = await response.text();
                if (!responseText) {
                    throw new Error('Lege respons van server');
                }

                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (parseErr) {
                    throw new Error('Ongeldige JSON: ' + responseText.substring(0, 200));
                }

                if (data.success) {
                    // Update state with actual schema
                    const stateTable = state.selectedTables.find(t => t.name === table.name);
                    if (stateTable) {
                        stateTable.columns = data.schema.columns;
                        stateTable.primaryKeys = data.schema.primaryKeys;
                        stateTable.relationships = data.schema.relationships || [];
                        stateTable.isLoading = false;
                    }

                    // Update canvas with actual schema
                    if (elements.schemaCanvas) {
                        elements.schemaCanvas.updateTableSchema(table.name, data.schema);
                    }
                }
            } catch (err) {
                // Remove placeholder on error
                const errIndex = state.selectedTables.findIndex(t => t.name === table.name);
                if (errIndex >= 0) {
                    state.selectedTables.splice(errIndex, 1);
                }
                if (elements.schemaCanvas) {
                    elements.schemaCanvas.removeTable(table.name);
                }
                showError('Fout bij laden kolommen: ' + err.message);
                return;
            }
        }

        if (!skipUpdates) {
            renderTableList();
            dispatchCmaEvent(CmaEvents.TABLES_CHANGED, {
                tables: state.selectedTables.map(t => t.name),
                action: index >= 0 ? 'removed' : 'added',
                tableName: table.name
            });
        }
    }

    function renderSchemaCanvas() {
        // Sync state with the canvas component
        const canvas = elements.schemaCanvas;
        if (!canvas) return;

        // Build table data with schemas for the component
        const tablesWithSchema = state.selectedTables.map(t => ({
            name: t.name,
            columns: t.columns,
            primaryKeys: t.primaryKeys,
            relationships: t.relationships || []
        }));

        canvas.setSelectedTables(tablesWithSchema);
    }

    function updateFieldConfig() {
        if (!elements.fieldConfigComponent) return;

        if (state.selectedTables.length === 0) {
            state.fields = [];
            elements.fieldConfigComponent.setFields([]);
            dispatchCmaEvent(CmaEvents.FIELDS_CHANGED, { fields: state.fields, source: 'tableChange' });
            return;
        }

        // Build field list from all selected tables
        const newFields = [];
        state.selectedTables.forEach(table => {
            // Check if this table has important fields from form definition
            const hasImportantFields = table.columns.some(col => col.isImportant);

            table.columns.forEach(col => {
                // Check if field already exists in state (preserve user changes)
                const existing = state.fields.find(f => f.table === table.name && f.field === col.name);
                if (existing) {
                    newFields.push(existing);
                } else {
                    let shouldShow;

                    if (hasImportantFields) {
                        // If table has form metadata with important fields, only show those
                        shouldShow = col.isImportant === true;
                    } else {
                        // Fallback: hide ID and foreign key fields
                        const fieldLower = col.name.toLowerCase();
                        const isIdField = fieldLower === 'id';
                        const isFkField = fieldLower.startsWith('fk');
                        shouldShow = !isIdField && !isFkField;
                    }

                    newFields.push({
                        table: table.name,
                        field: col.name,
                        alias: col.name,
                        dataType: col.dataType,
                        typeCategory: col.typeCategory,
                        visible: shouldShow,
                        isImportant: col.isImportant || false,
                        filter: null
                    });
                }
            });
        });

        state.fields = newFields;
        elements.fieldConfigComponent.setFields(state.fields);

        // Dispatch FIELDS_CHANGED — canvas sync, sort/group, conditions, and SQL preview
        // all react to this event via setupReactiveListeners()
        dispatchCmaEvent(CmaEvents.FIELDS_CHANGED, { fields: state.fields, source: 'tableChange' });
    }

    /**
     * Sync field selection from state.fields to the schema canvas
     */
    function syncFieldSelectionToCanvas() {
        if (!elements.schemaCanvas) return;

        const selectedFields = {};
        state.fields.forEach(f => {
            if (f.visible) {
                if (!selectedFields[f.table]) {
                    selectedFields[f.table] = [];
                }
                selectedFields[f.table].push(f.field);
            }
        });
        elements.schemaCanvas.setSelectedFields(selectedFields);
    }

    function updateSortGroupAvailableFields() {
        // Build list of all fields for sorting/grouping (not just visible ones)
        const availableFields = state.fields.map(f => ({
            table: f.table,
            field: f.field,
            alias: f.alias
        }));

        if (elements.sortConfigComponent) {
            elements.sortConfigComponent.setAvailableFields(availableFields);
        }

        if (elements.groupConfigComponent) {
            elements.groupConfigComponent.setAvailableFields(availableFields);
        }
    }

    /**
     * Update conditions panel with current conditions from state
     */
    function updateConditionsPanel() {
        if (!elements.conditionsPanel) return;

        // Add operator labels for display
        const conditionsWithLabels = state.conditions.map(c => ({
            ...c,
            operatorLabel: c.operator ? getOperatorLabel(c.operator, c.typeCategory) : ''
        }));

        elements.conditionsPanel.setConditions(conditionsWithLabels);

        // Also pass available fields for adding new conditions
        const availableFields = state.fields.map(f => ({
            table: f.table,
            field: f.field,
            alias: f.alias || f.field,
            dataType: f.dataType,
            typeCategory: f.typeCategory
        }));
        elements.conditionsPanel.setAvailableFields(availableFields);
    }

    /**
     * Get human-readable operator label
     */
    function getOperatorLabel(operator, typeCategory) {
        const labels = {
            '=': 'is gelijk aan',
            '<>': 'is niet gelijk aan',
            '<': 'kleiner dan',
            '>': 'groter dan',
            '<=': 'kleiner of gelijk',
            '>=': 'groter of gelijk',
            'contains': 'bevat',
            'starts': 'begint met',
            'ends': 'eindigt met',
            'empty': 'is leeg',
            'notempty': 'is niet leeg',
            'between': 'tussen',
            'before': 'voor',
            'after': 'na',
            'today': 'vandaag',
            'thisweek': 'deze week',
            'thismonth': 'deze maand',
            'yes': 'ja',
            'no': 'nee'
        };
        return labels[operator] || operator;
    }

    // Field config rendering is now handled by cma-field-config component
    // Parameter config rendering is now handled by cma-param-config component
    // Sort/Group config rendering is now handled by cma-sort-config and cma-group-config components

    function clearSelection() {
        state.selectedTables = [];
        state.fields = [];

        // Clear canvas component
        if (elements.schemaCanvas) {
            elements.schemaCanvas.setSelectedTables([]);
        }

        renderTableList();
        updateFieldConfig();
    }

    function newReport() {
        // Check for unsaved changes
        if (state.isDirty && state.selectedTables.length > 0) {
            elements.unsavedChangesDialog.open();
            return;
        }
        resetAndShowModeDialog();
    }

    function resetAndShowModeDialog() {
        // Reset all state
        state.mode = null;
        state.reportId = null;
        state.existingReport = null;
        state.selectedTables = [];
        state.fields = [];
        state.conditions = [];
        state.parameters = [];
        state.sorting = [];
        state.grouping = [];
        state.outputFormat = 'table';
        state.reportName = '';
        state.isGlobal = false;
        state.isDirty = false;

        // Clear UI components
        if (elements.schemaCanvas) {
            elements.schemaCanvas.setSelectedTables([]);
        }
        if (elements.fieldConfigComponent) {
            elements.fieldConfigComponent.setFields([]);
        }
        if (elements.paramConfigComponent) {
            elements.paramConfigComponent.setParameters([]);
        }
        if (elements.sortConfigComponent) {
            elements.sortConfigComponent.setSorting([]);
        }
        if (elements.groupConfigComponent) {
            elements.groupConfigComponent.setGrouping([]);
        }
        if (elements.conditionsPanel) {
            elements.conditionsPanel.setConditions([]);
        }
        if (elements.resultsQueryPreview) {
            elements.resultsQueryPreview.setSql('');
            elements.resultsQueryPreview.setData([], [], 0);
        }

        // Hide report name
        elements.reportNameDisplay.style.display = 'none';
        elements.reportNameDisplay.textContent = '';

        // Reset wizard to first tab
        if (elements.wizardTabs) {
            elements.wizardTabs.selectTab(0);
        }

        // Show mode dialog
        if (elements.modeDialog) {
            elements.modeDialog.open();
        }
    }

    function updateWizardForMode() {
        if (elements.wizardContainer) elements.wizardContainer.style.display = 'flex';

        // Hide parameter step (index 1) in quick mode - use setStepHidden for proper step numbering
        if (elements.wizardTabs && typeof elements.wizardTabs.setStepHidden === 'function') {
            elements.wizardTabs.setStepHidden(1, state.mode === 'quick');
        }

        // Set advanced-mode attribute on schema canvas for alias editing
        if (elements.schemaCanvas) {
            if (state.mode === 'advanced') {
                elements.schemaCanvas.setAttribute('advanced-mode', 'true');
            } else {
                elements.schemaCanvas.removeAttribute('advanced-mode');
            }
        }

        // Show query options only in advanced mode
        const queryOptionsSection = document.getElementById('queryOptionsSection');
        if (queryOptionsSection) {
            queryOptionsSection.style.display = state.mode === 'advanced' ? '' : 'none';
        }
    }

    /**
     * Compute JOINs from relationships between selected tables
     * Returns an array of tables with joins computed from their relationships
     */
    function computeJoinsFromRelationships() {
        if (state.selectedTables.length <= 1) {
            // No joins needed for single table
            return state.selectedTables.map(t => ({
                name: t.name,
                alias: t.alias,
                joins: []
            }));
        }

        // If we have parsed joins from SQL, use those instead of computing from relationships
        if (state.parsedJoins && state.parsedJoins.length > 0) {
            cmaLog.log('[report-designer] Using parsed joins from SQL:', state.parsedJoins);

            // Build tables structure with parsed joins
            // First table gets all joins attached (MS Access nested syntax)
            const result = state.selectedTables.map((t, i) => ({
                name: t.name,
                alias: t.alias,
                joins: i === 0 ? state.parsedJoins.map(j => ({
                    table: j.table,
                    alias: '',
                    type: j.type || 'INNER',
                    on: j.on
                })) : []
            }));

            cmaLog.log('[report-designer] Tables with parsed joins:', result);
            return result;
        }

        // Collect all relationships from all selected tables
        const allRelationships = [];
        const selectedTableNames = state.selectedTables.map(t => t.name.toLowerCase());

        for (const table of state.selectedTables) {
            if (table.relationships) {
                for (const rel of table.relationships) {
                    // Only include relationships where BOTH tables are selected
                    const fkLower = (rel.fkTable || '').toLowerCase();
                    const pkLower = (rel.pkTable || '').toLowerCase();
                    const fkInSelection = selectedTableNames.includes(fkLower);
                    const pkInSelection = selectedTableNames.includes(pkLower);

                    if (!fkInSelection || !pkInSelection) {
                        cmaLog.log('[report-designer] Skipping relationship (table not selected):', {
                            rel,
                            fkInSelection,
                            pkInSelection,
                            selectedTableNames
                        });
                        continue;
                    }

                    // Avoid duplicates
                    const key = `${rel.fkTable}.${rel.fkColumn}->${rel.pkTable}.${rel.pkColumn}`;
                    if (!allRelationships.some(r => `${r.fkTable}.${r.fkColumn}->${r.pkTable}.${r.pkColumn}` === key)) {
                        allRelationships.push(rel);
                    }
                }
            }
        }

        cmaLog.log('[report-designer] Computing joins from relationships:', {
            selectedTables: selectedTableNames,
            allRelationships: allRelationships
        });

        // Build tables with joins
        // First table has no join, subsequent tables get joins based on relationships
        const result = [];
        const joinedTables = new Set();

        for (let i = 0; i < state.selectedTables.length; i++) {
            const table = state.selectedTables[i];
            const tableEntry = {
                name: table.name,
                alias: table.alias,
                joins: []
            };

            if (i === 0) {
                // First table - no join needed
                joinedTables.add(table.name.toLowerCase());
                result.push(tableEntry);
                continue;
            }

            // Find a relationship that connects this table to any already-joined table
            const tableLower = table.name.toLowerCase();
            let foundJoin = false;

            for (const rel of allRelationships) {
                const fkLower = rel.fkTable.toLowerCase();
                const pkLower = rel.pkTable.toLowerCase();
                // Determine join type: INNER if "Moet bestaan" is checked, LEFT OUTER if not
                const joinType = rel.innerJoin !== false ? 'INNER' : 'LEFT OUTER';

                // Safety check: both tables in the relationship must be selected
                if (!selectedTableNames.includes(fkLower) || !selectedTableNames.includes(pkLower)) {
                    cmaLog.warn('[report-designer] Skipping relationship with non-selected table:', rel);
                    continue;
                }

                // Check if this relationship connects current table to an already-joined table
                if (fkLower === tableLower && joinedTables.has(pkLower)) {
                    // Current table has FK pointing to an already-joined table
                    // Add join to the FIRST table's joins array
                    result[0].joins.push({
                        table: table.name,
                        alias: '',
                        type: joinType,
                        on: `[${rel.fkTable}].[${rel.fkColumn}] = [${rel.pkTable}].[${rel.pkColumn}]`
                    });
                    foundJoin = true;
                    break;
                } else if (pkLower === tableLower && joinedTables.has(fkLower)) {
                    // Current table is referenced by an already-joined table
                    result[0].joins.push({
                        table: table.name,
                        alias: '',
                        type: joinType,
                        on: `[${rel.fkTable}].[${rel.fkColumn}] = [${rel.pkTable}].[${rel.pkColumn}]`
                    });
                    foundJoin = true;
                    break;
                }
            }

            joinedTables.add(tableLower);
            result.push(tableEntry);

            // If no relationship found, add table without join (will result in cross join)
            // This is intentional - user may want to add custom join later
        }

        cmaLog.log('[report-designer] Computed tables with joins:', result);
        return result;
    }

    function buildReportDefinition() {
        // For SQL mode, return a simplified definition
        if (state.mode === 'sql') {
            return {
                id: state.reportId || null,
                name: state.reportName || 'Nieuw rapport',
                mode: 'sql',
                database: state.databaseId,
                isGlobal: state.isGlobal,
                rawSql: state.rawSql,
                tables: [],
                fields: [],
                parameters: [],
                sorting: [],
                grouping: [],
                totals: { showGrandTotal: false, fields: [] },
                output: { format: state.outputFormat }
            };
        }

        // Compute joins from relationships
        const tablesWithJoins = computeJoinsFromRelationships();

        // Include relationships in saved tables (for restoring manually added joins)
        const tablesWithRelationships = tablesWithJoins.map(t => {
            const stateTable = state.selectedTables.find(st => st.name === t.name);
            return {
                ...t,
                relationships: stateTable?.relationships || []
            };
        });

        return {
            id: state.reportId || null,
            name: state.reportName || 'Nieuw rapport',
            mode: state.mode || 'advanced',
            database: state.databaseId,
            isGlobal: state.isGlobal,
            tables: tablesWithRelationships,
            // Fields now only contain SELECT info, not filters (filters are in conditions)
            fields: state.fields.map(f => {
                const fieldDef = {
                    table: f.table,
                    field: f.field,
                    dataType: f.dataType,
                    visible: f.visible,
                    showTotal: f.showTotal || false
                };
                // Only include alias if different from field name (save bandwidth)
                if (f.alias && f.alias !== f.field) {
                    fieldDef.alias = f.alias;
                }
                return fieldDef;
            }),
            // Conditions array - separate from fields, supports multiple per field, reordering
            conditions: state.conditions.map(c => ({
                id: c.id,
                table: c.table,
                field: c.field,
                alias: c.alias,
                dataType: c.dataType,
                typeCategory: c.typeCategory,
                operator: c.operator,
                value: c.value || '',
                value2: c.value2 || '',
                logic: c.logic || 'AND',
                prefix: c.prefix || '',
                suffix: c.suffix || ''
            })),
            parameters: state.parameters,
            sorting: state.sorting,
            grouping: state.grouping,
            distinct: state.distinct,
            topN: state.topN,
            // Derive totals from fields with showTotal enabled
            totals: {
                showGrandTotal: state.fields.some(f => f.showTotal),
                fields: state.fields.filter(f => f.showTotal).map(f => {
                    const totalDef = { table: f.table, field: f.field };
                    if (f.alias && f.alias !== f.field) {
                        totalDef.alias = f.alias;
                    }
                    return totalDef;
                })
            },
            output: {
                format: state.outputFormat
            },
            layout: {
                tablePositions: state.tablePositions,
                tableAliases: state.tableAliases,
                tableSizes: state.tableSizes,
                panelPosition: state.panelPosition
            }
        };
    }

    /**
     * Try to sync SQL back to the visual editor
     * Parses the SQL and updates tables, fields, sorting if possible
     * Falls back to SQL-only mode if parsing fails
     */
    async function syncSqlToVisualEditor(sql) {
        const preview = elements.resultsQueryPreview;

        try {
            const response = await fetch('api/report-query.php?action=parseSql', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ sql, database: state.databaseId })
            });

            const result = await response.json();

            if (result.success && result.parsed) {
                // Batch events during SQL→visual sync
                beginBatch();

                // Successfully parsed - update visual editor
                const parsed = result.parsed;

                // Store parsed joins from SQL - these override computed joins from relationships
                if (parsed.joins && parsed.joins.length > 0) {
                    state.parsedJoins = parsed.joins;
                    cmaLog.log('[report-designer] Stored parsed joins from SQL:', state.parsedJoins);
                } else {
                    state.parsedJoins = null;
                }

                // Update tables
                if (parsed.tables && parsed.tables.length > 0) {
                    // Clear current selection
                    state.selectedTables = [];
                    if (elements.schemaCanvas) {
                        elements.schemaCanvas.setSelectedTables([]);
                    }

                    // Add parsed tables - skip intermediate updates
                    for (const tableName of parsed.tables) {
                        const tableObj = state.tables.find(t => t.name.toLowerCase() === tableName.toLowerCase());
                        if (tableObj) {
                            await toggleTable(tableObj, true); // skipUpdates = true
                        }
                    }
                    // Render table list but DON'T call updateFieldConfig yet
                    // We'll handle fields manually based on parsed SQL
                    renderTableList();

                    // Update schema canvas relationships from parsed joins
                    if (elements.schemaCanvas && state.parsedJoins) {
                        elements.schemaCanvas.setRelationshipsFromJoins(state.parsedJoins);
                    }
                }

                // Update fields - merge parsed fields with available columns
                if (parsed.fields && parsed.fields.length > 0 && elements.fieldConfigComponent) {
                    // Build full field list from all table columns
                    const allFields = [];
                    state.selectedTables.forEach(table => {
                        table.columns.forEach(col => {
                            // Check if this field is in the parsed SELECT
                            const parsedField = parsed.fields.find(f =>
                                f.table.toLowerCase() === table.name.toLowerCase() &&
                                f.field.toLowerCase() === col.name.toLowerCase()
                            );

                            if (parsedField) {
                                // Use the parsed field info (preserves alias, etc.)
                                allFields.push({
                                    ...parsedField,
                                    dataType: col.dataType,
                                    typeCategory: col.typeCategory,
                                    visible: true
                                });
                            } else {
                                // Field not in SELECT - add as hidden
                                allFields.push({
                                    table: table.name,
                                    field: col.name,
                                    alias: col.name,
                                    dataType: col.dataType,
                                    typeCategory: col.typeCategory,
                                    visible: false,
                                    isImportant: col.isImportant || false,
                                    filter: null
                                });
                            }
                        });
                    });

                    state.fields = allFields;
                    elements.fieldConfigComponent.setFields(state.fields);

                    // Sync field selection to canvas
                    syncFieldSelectionToCanvas();

                    // Update sort/group config with available fields
                    updateSortGroupAvailableFields();
                } else if (parsed.tables && parsed.tables.length > 0) {
                    // No parsed fields but we have tables - use default field config
                    updateFieldConfig();
                }

                // Update sorting
                if (parsed.sorting && parsed.sorting.length > 0 && elements.sortConfigComponent) {
                    state.sorting = parsed.sorting;
                    elements.sortConfigComponent.setSorting(state.sorting);
                }

                // Update grouping
                if (parsed.grouping && parsed.grouping.length > 0 && elements.groupConfigComponent) {
                    state.grouping = parsed.grouping;
                    elements.groupConfigComponent.setGrouping(state.grouping);
                }

                // Update DISTINCT
                if (parsed.distinct !== undefined) {
                    state.distinct = parsed.distinct;
                    const distinctCheckbox = document.getElementById('distinctCheckbox');
                    if (distinctCheckbox) {
                        distinctCheckbox.checked = state.distinct;
                    }
                }

                // Update TOP N
                if (parsed.topN !== undefined) {
                    state.topN = parsed.topN;
                    const topNInput = document.getElementById('topNInput');
                    if (topNInput) {
                        topNInput.value = state.topN || '';
                    }
                }

                // Clear SQL-only mode
                state.rawSql = '';
                if (preview) {
                    preview.setSqlOnlyMode(false);
                }

                // End batch — fires all queued events once
                endBatch();

                // Dispatch sync complete event
                dispatchCmaEvent(CmaEvents.SYNC_COMPLETE, {
                    success: true,
                    tables: parsed.tables,
                    fields: parsed.fields,
                    sorting: parsed.sorting,
                    grouping: parsed.grouping,
                    distinct: parsed.distinct,
                    topN: parsed.topN
                });

            } else {
                // Failed to parse - enable SQL-only mode
                state.rawSql = sql;
                state.mode = 'sql';
                if (preview) {
                    preview.setSql(sql);  // Preserve the raw SQL in the preview
                    preview.setSqlOnlyMode(true, result.error || 'De SQL-query kan niet automatisch worden vertaald naar de visuele editor.');
                }

                // Dispatch sync complete event with failure
                dispatchCmaEvent(CmaEvents.SYNC_COMPLETE, {
                    success: false,
                    error: result.error || 'Parsing failed',
                    sqlOnlyMode: true
                });
            }
        } catch (err) {
            cmaLog.error('Error parsing SQL:', err);
            // Enable SQL-only mode on error
            state.rawSql = sql;
            state.mode = 'sql';
            if (preview) {
                preview.setSql(sql);  // Preserve the raw SQL in the preview
                preview.setSqlOnlyMode(true, 'Fout bij het parsen van de SQL: ' + err.message);
            }

            // Dispatch sync complete event with error
            dispatchCmaEvent(CmaEvents.SYNC_COMPLETE, {
                success: false,
                error: err.message,
                sqlOnlyMode: true
            });
        }
    }

    /**
     * @deprecated SQL preview now updates reactively via setupReactiveListeners().
     * Kept as a noop for any remaining call sites during transition.
     */
    function updatePreview() {
        // Noop — SQL preview sync is now handled reactively by setupReactiveListeners().
        // Each state change dispatches a typed event, and the preview listener coalesces
        // them with a 50ms debounce before calling syncSqlToResultsEditor().
    }

    function updatePreviewData(parameterValues = {}) {
        const preview = elements.resultsQueryPreview;
        if (!preview) return;

        const definition = buildReportDefinition();

        preview.setLoading(true);

        fetch('api/report-query.php?action=preview', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ definition, parameterValues })
        })
        .then(async r => {
            const responseText = await r.text();
            if (!r.ok) {
                throw new Error(`HTTP ${r.status}: ${responseText || 'Geen respons'}`);
            }
            if (!responseText) {
                throw new Error('Lege respons van server');
            }
            try {
                return JSON.parse(responseText);
            } catch (parseErr) {
                throw new Error('Ongeldige JSON: ' + responseText.substring(0, 200));
            }
        })
        .then(data => {
            preview.setLoading(false);

            if (data.success) {
                preview.setData(data.columns, data.data, data.rowCount);
                // Update export format options based on row count
                if (typeof window.updateExportFormatOptions === 'function') {
                    window.updateExportFormatOptions(data.rowCount || 0);
                }
            } else {
                preview.setError(data.error || 'Onbekende fout');
            }
        })
        .catch(err => {
            preview.setLoading(false);
            preview.setError(err.message);
        });
    }

    /**
     * Populate save forms (both inline and dialog) with current state values
     */
    function populateSaveForm() {
        const reportName = state.reportName || '';
        const isGlobal = state.isGlobal || false;

        // Populate dialog form
        if (elements.saveDialogReportName) {
            elements.saveDialogReportName.value = reportName;
        }
        if (elements.saveDialogIsGlobal) {
            elements.saveDialogIsGlobal.checked = isGlobal;
        }

        // Populate inline form (step 6)
        if (elements.saveStepReportName) {
            elements.saveStepReportName.value = reportName;
        }
        if (elements.saveStepIsGlobal) {
            elements.saveStepIsGlobal.checked = isGlobal;
        }

        // Clear any previous status message
        showSaveStepStatus(null);
    }

    function openSaveDialog() {
        // Pre-fill forms with current values
        populateSaveForm();

        // Open dialog
        if (elements.saveReportDialog) {
            elements.saveReportDialog.open();
            // Focus on name input
            setTimeout(() => {
                if (elements.saveDialogReportName) {
                    elements.saveDialogReportName.focus();
                }
            }, 100);
        }
    }

    async function confirmSaveReport() {
        // Get values from dialog
        const reportName = elements.saveDialogReportName?.value?.trim();
        const isGlobal = elements.saveDialogIsGlobal?.checked || false;

        if (!reportName) {
            showWarning('Vul een rapportnaam in');
            elements.saveDialogReportName?.focus();
            return;
        }

        // Update state
        state.reportName = reportName;
        state.isGlobal = isGlobal;

        const definition = buildReportDefinition();

        try {
            const response = await fetch('api/report-save.php?action=save', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(definition)
            });
            const data = await response.json();

            if (data.success) {
                state.reportId = data.id;
                state.isDirty = false;
                elements.saveReportDialog?.close();
                showSuccess('Rapport opgeslagen');
                elements.reportNameDisplay.textContent = state.reportName;
                elements.reportNameDisplay.style.display = 'inline';

                // If pending new after save, reset and show mode dialog
                if (state._pendingNewAfterSave) {
                    state._pendingNewAfterSave = false;
                    resetAndShowModeDialog();
                }
            } else {
                showError('Fout bij opslaan: ' + (data.error || 'Onbekende fout'));
            }
        } catch (err) {
            showError('Fout bij opslaan: ' + err.message);
        }
    }

    /**
     * Save report from inline form (step 6)
     * Similar to confirmSaveReport but uses inline form elements and status display
     */
    async function saveFromInlineForm() {
        // Get values from inline form
        const reportName = elements.saveStepReportName?.value?.trim();
        const isGlobal = elements.saveStepIsGlobal?.checked || false;

        // Hide any previous status message
        showSaveStepStatus(null);

        if (!reportName) {
            showSaveStepStatus('Vul een rapportnaam in', 'error');
            elements.saveStepReportName?.focus();
            return;
        }

        // Update state
        state.reportName = reportName;
        state.isGlobal = isGlobal;

        // Sync to dialog form as well (for consistency)
        if (elements.saveDialogReportName) {
            elements.saveDialogReportName.value = reportName;
        }
        if (elements.saveDialogIsGlobal) {
            elements.saveDialogIsGlobal.checked = isGlobal;
        }

        const definition = buildReportDefinition();

        // Disable button during save
        if (elements.saveReportBtnStep) {
            elements.saveReportBtnStep.disabled = true;
            elements.saveReportBtnStep.innerHTML = '<span class="lnr lnr-sync lnr-spin"></span> Opslaan...';
        }

        try {
            const response = await fetch('api/report-save.php?action=save', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(definition)
            });
            const data = await response.json();

            if (data.success) {
                state.reportId = data.id;
                state.isDirty = false;
                showSaveStepStatus('Rapport succesvol opgeslagen!', 'success');
                elements.reportNameDisplay.textContent = state.reportName;
                elements.reportNameDisplay.style.display = 'inline';

                // If pending new after save, reset and show mode dialog
                if (state._pendingNewAfterSave) {
                    state._pendingNewAfterSave = false;
                    resetAndShowModeDialog();
                }
            } else {
                showSaveStepStatus('Fout bij opslaan: ' + (data.error || 'Onbekende fout'), 'error');
            }
        } catch (err) {
            showSaveStepStatus('Fout bij opslaan: ' + err.message, 'error');
        } finally {
            // Re-enable button
            if (elements.saveReportBtnStep) {
                elements.saveReportBtnStep.disabled = false;
                elements.saveReportBtnStep.innerHTML = '<span class="lnr lnr-save"></span> Rapport opslaan';
            }
        }
    }

    /**
     * Show status message in save step
     * @param {string|null} message - Message to show, or null to hide
     * @param {string} type - 'success' or 'error'
     */
    function showSaveStepStatus(message, type = 'success') {
        const status = elements.saveStepStatus;
        if (!status) return;

        if (!message) {
            status.style.display = 'none';
            return;
        }

        status.textContent = message;
        status.className = 'save-step-status ' + type;
        status.style.display = 'block';
    }

    function runReport() {
        // Check if there are parameters that need values
        if (state.parameters && state.parameters.length > 0) {
            showParameterInputDialog();
            return;
        }

        executeReport({});
    }

    function showParameterInputDialog() {
        let html = '';
        state.parameters.forEach((param, index) => {
            const inputId = `param_input_${index}`;
            const typeAttr = param.type === 'number' ? 'number' : param.type === 'date' ? 'date' : 'text';
            const required = param.required ? 'required' : '';

            html += `
                <div class="form-group" style="margin-bottom: 15px;">
                    <label for="${inputId}" style="display: block; margin-bottom: 5px; font-weight: 500;">
                        ${escapeHtml(param.label || param.name)}
                        ${param.required ? '<span style="color: var(--danger-color);">*</span>' : ''}
                    </label>
                    <input type="${typeAttr}" id="${inputId}" class="formbox"
                           data-param-name="${escapeHtml(param.name)}"
                           value="${escapeHtml(param.default || '')}"
                           placeholder="${escapeHtml(param.label || param.name)}"
                           ${required}
                           style="width: 100%; padding: 8px; border: 1px solid var(--border-color); border-radius: 4px;">
                </div>
            `;
        });

        elements.paramInputContent.innerHTML = html;
        elements.paramInputDialog.open();
    }

    function getParameterValues() {
        const values = {};
        const inputs = elements.paramInputContent.querySelectorAll('input[data-param-name]');
        inputs.forEach(input => {
            const paramName = input.dataset.paramName;
            // Remove @ prefix for value key
            const key = paramName.startsWith('@') ? paramName.substring(1) : paramName;
            values[key] = input.value;
        });
        return values;
    }

    function executeReport(parameterValues) {
        const format = state.outputFormat;
        const definition = buildReportDefinition();
        definition.parameterValues = parameterValues;

        if (format === 'table') {
            // Switch to Results tab and show data
            switchMainTab('results');
            const preview = elements.resultsQueryPreview;
            if (preview) {
                preview.setAttribute('active-tab', 'data');
            }
            updatePreviewData(parameterValues);
        } else {
            // Export via API
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = `api/report-export.php?action=${format}`;
            form.target = '_blank';

            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'definition';
            input.value = JSON.stringify(definition);
            form.appendChild(input);

            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }
    }

    function showLoadDialog() {
        elements.loadReportDialog.open();

        // Load report list
        fetch('api/report-save.php?action=list')
            .then(r => r.json())
            .then(data => {
                elements.reportListLoader.removeAttribute('active');

                if (data.success && data.reports.length > 0) {
                    let html = '<div class="report-list-items">';
                    data.reports.forEach(report => {
                        html += `
                            <div class="table-list-item" data-id="${escapeHtml(report.id)}" style="cursor: pointer;">
                                <span class="table-icon lnr ${report.isGlobal ? 'lnr-earth' : 'lnr-user'}" data-tooltip="${report.isGlobal ? 'Globaal rapport (gedeeld)' : 'Persoonlijk rapport'}"></span>
                                <span class="table-name">${escapeHtml(report.name)}</span>
                                <span style="font-size: var(--font-size-sm); color: var(--text-muted);">${escapeHtml(report.updatedAt || '')}</span>
                            </div>
                        `;
                    });
                    html += '</div>';
                    elements.reportList.innerHTML = html;
                    elements.reportList.style.display = 'block';

                    // Click handlers
                    elements.reportList.querySelectorAll('.table-list-item').forEach(item => {
                        item.addEventListener('click', () => {
                            const reportId = item.dataset.id;
                            loadReportById(reportId);
                            elements.loadReportDialog.close();
                        });
                    });
                } else {
                    elements.reportList.innerHTML = '<div class="empty-state"><div class="empty-text">Geen opgeslagen rapporten</div></div>';
                    elements.reportList.style.display = 'block';
                }
            })
            .catch(err => {
                elements.reportListLoader.removeAttribute('active');
                elements.reportList.innerHTML = `<div style="color: var(--danger-color);">Fout: ${escapeHtml(err.message)}</div>`;
                elements.reportList.style.display = 'block';
            });
    }

    async function loadReportById(reportId) {
        try {
            const response = await fetch(`api/report-save.php?action=load&id=${encodeURIComponent(reportId)}`);
            const data = await response.json();

            if (data.success) {
                state.existingReport = data.report;
                await loadExistingReport();
            } else {
                showError('Fout bij laden rapport: ' + (data.error || 'Onbekende fout'));
            }
        } catch (err) {
            showError('Fout bij laden rapport: ' + err.message);
        }
    }

    /**
     * Show the Paste SQL dialog
     */
    function showPasteSqlDialog() {
        const dialog = document.getElementById('pasteSqlDialog');
        const input = document.getElementById('pasteSqlInput');
        if (dialog) {
            input.value = '';
            dialog.open();
            // Focus the textarea after a short delay for the dialog to open
            setTimeout(() => input.focus(), 100);
        }
    }

    /**
     * Process pasted SQL and convert to visual report
     */
    async function processPastedSql(sql) {
        const dialog = document.getElementById('pasteSqlDialog');

        // Validate it's a SELECT query
        const trimmedSql = sql.trim().toUpperCase();
        if (!trimmedSql.startsWith('SELECT')) {
            showError('Alleen SELECT-queries zijn toegestaan');
            return;
        }

        // Block dangerous keywords
        const blocked = ['INSERT', 'UPDATE', 'DELETE', 'DROP', 'TRUNCATE', 'ALTER', 'CREATE', 'EXEC', 'EXECUTE'];
        for (const keyword of blocked) {
            const regex = new RegExp('\\b' + keyword + '\\b', 'i');
            if (regex.test(sql)) {
                showError(`Query bevat niet-toegestaan keyword: ${keyword}`);
                return;
            }
        }

        try {
            // Send SQL to API for parsing
            const response = await fetch('api/report-query.php?action=parseSQL', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    sql: sql,
                    databaseId: state.databaseId
                })
            });

            const data = await response.json();

            if (data.success) {
                dialog.close();

                // Set mode to advanced
                state.mode = 'advanced';
                state.rawSql = sql;

                // Load parsed structure
                if (data.tables && data.tables.length > 0) {
                    // First load tables to get schema
                    await loadTables();

                    // Select the parsed tables
                    for (const tableName of data.tables) {
                        const table = state.tables.find(t =>
                            t.name.toLowerCase() === tableName.toLowerCase()
                        );
                        if (table) {
                            toggleTableSelection(table);
                        }
                    }

                    // Set parsed fields
                    if (data.fields && data.fields.length > 0) {
                        state.fields = data.fields;
                        if (elements.fieldConfigComponent) {
                            elements.fieldConfigComponent.setFields(state.fields);
                        }
                    }

                    // Set parsed conditions
                    if (data.conditions && data.conditions.length > 0 && elements.conditionsPanel) {
                        elements.conditionsPanel.setConditions(data.conditions);
                    }

                    updateWizardForMode();
                    dispatchCmaEvent(CmaEvents.FIELDS_CHANGED, { fields: state.fields, source: 'pastedSql' });
                    showSuccess('SQL succesvol geanalyseerd');
                } else {
                    showWarning('Geen tabellen herkend in de query');
                }
            } else {
                showError('Fout bij analyseren SQL: ' + (data.error || 'Onbekende fout'));
            }
        } catch (err) {
            showError('Fout bij analyseren SQL: ' + err.message);
        }
    }

    async function loadExistingReport() {
        const report = state.existingReport;
        if (!report) return;

        // Batch all events during loading — fire once at the end
        beginBatch();

        state.reportId = report.id;
        state.reportName = report.name || '';
        // Always use advanced mode when loading a report to show all options including parameters
        state.mode = 'advanced';
        // Set advanced mode attribute BEFORE loading tables so alias editing is enabled
        updateWizardForMode();
        state.databaseId = report.database || state.databaseId;
        state.isGlobal = report.isGlobal || false;
        state.parameters = report.parameters || [];
        state.sorting = report.sorting || [];
        state.grouping = report.grouping || [];
        state.outputFormat = report.output?.format || 'table';
        state.rawSql = report.rawSql || '';
        state.distinct = report.distinct || false;
        state.topN = report.topN || null;

        // Update UI - database select
        elements.databaseSelect.value = state.databaseId;
        if (state.reportName) {
            elements.reportNameDisplay.textContent = state.reportName;
            elements.reportNameDisplay.style.display = 'inline';
        }

        // Load tables first, then restore selection
        // Skip background tasks during loading to avoid competing for bandwidth
        await loadTables(true);

        // Restore selected tables - load in parallel for faster loading
        // Skip intermediate updates to avoid duplicate API calls
        if (report.tables && report.tables.length > 0) {
            // Preload positions BEFORE adding tables to avoid visual flickering
            if (report.layout?.tablePositions && elements.schemaCanvas) {
                state.tablePositions = report.layout.tablePositions;
                elements.schemaCanvas.preloadPositions(state.tablePositions);
            }

            // Restore table aliases
            if (report.layout?.tableAliases && elements.schemaCanvas) {
                state.tableAliases = report.layout.tableAliases;
                elements.schemaCanvas.setTableAliases(state.tableAliases);
            }

            // Restore table sizes
            if (report.layout?.tableSizes && elements.schemaCanvas) {
                state.tableSizes = report.layout.tableSizes;
                elements.schemaCanvas.setTableSizes(state.tableSizes);
            }

            // Restore relationships panel position
            if (report.layout?.panelPosition && elements.schemaCanvas) {
                state.panelPosition = report.layout.panelPosition;
                elements.schemaCanvas.setPanelPosition(state.panelPosition);
            }

            const tablePromises = report.tables.map(table => {
                const tableObj = state.tables.find(t => t.name === table.name);
                if (tableObj) {
                    return toggleTable(tableObj, true); // skipUpdates = true
                }
                return Promise.resolve();
            });
            await Promise.all(tablePromises);

            // Restore saved relationships (including manually added ones)
            report.tables.forEach(savedTable => {
                if (savedTable.relationships && savedTable.relationships.length > 0) {
                    const stateTable = state.selectedTables.find(t => t.name === savedTable.name);
                    if (stateTable) {
                        // Merge saved relationships with schema-provided ones
                        const existingKeys = new Set((stateTable.relationships || []).map(r =>
                            `${r.fkTable}.${r.fkColumn}->${r.pkTable}.${r.pkColumn}`
                        ));
                        savedTable.relationships.forEach(rel => {
                            const key = `${rel.fkTable}.${rel.fkColumn}->${rel.pkTable}.${rel.pkColumn}`;
                            if (!existingKeys.has(key)) {
                                if (!stateTable.relationships) {
                                    stateTable.relationships = [];
                                }
                                stateTable.relationships.push(rel);
                                existingKeys.add(key);
                            }
                        });
                    }
                }
            });

            // Update schema canvas with restored relationships
            renderSchemaCanvas();

            // Clear preloaded positions after tables are added
            if (elements.schemaCanvas) {
                elements.schemaCanvas.clearPreloadedPositions();
            }

            // Do a single update after all tables are loaded
            renderTableList();
            updateFieldConfig();
        }

        // Restore fields config - merge saved settings with all available fields
        if (report.fields && elements.fieldConfigComponent) {
            // state.fields now contains ALL available fields from updateFieldConfig()
            // Merge saved field settings with available fields
            state.fields = state.fields.map(availableField => {
                const savedField = report.fields.find(f => f.table === availableField.table && f.field === availableField.field);
                if (savedField) {
                    // Apply saved settings to this available field (filter is no longer stored on fields)
                    return {
                        ...availableField,
                        alias: savedField.alias || availableField.alias,
                        visible: savedField.visible !== false,
                        showTotal: savedField.showTotal || false
                    };
                }
                // Field was not in saved report - mark as not visible
                return {
                    ...availableField,
                    visible: false
                };
            });
            elements.fieldConfigComponent.setFields(state.fields);

            // Sync field selection to canvas
            syncFieldSelectionToCanvas();
        }

        // Restore conditions - new format or migrate from old field.filter format
        if (report.conditions && report.conditions.length > 0) {
            // New format: conditions array
            state.conditions = report.conditions.map(c => ({
                id: c.id || 'cond_' + Date.now() + '_' + Math.random().toString(36).substr(2, 5),
                table: c.table,
                field: c.field,
                alias: c.alias || c.field,
                dataType: c.dataType,
                typeCategory: c.typeCategory || 'text',
                operator: c.operator || '',
                value: c.value || '',
                value2: c.value2 || '',
                logic: c.logic || 'AND',
                prefix: c.prefix || '',
                suffix: c.suffix || ''
            }));
        } else {
            // Backward compatibility: migrate old field.filter format to conditions
            state.conditions = [];
            (report.fields || []).forEach(f => {
                if (f.filter && f.filter.operator) {
                    // Get field metadata from state.fields
                    const stateField = state.fields.find(sf => sf.table === f.table && sf.field === f.field);
                    state.conditions.push({
                        id: 'cond_' + Date.now() + '_' + Math.random().toString(36).substr(2, 5),
                        table: f.table,
                        field: f.field,
                        alias: f.alias || f.field,
                        dataType: stateField?.dataType || f.dataType,
                        typeCategory: stateField?.typeCategory || 'text',
                        operator: f.filter.operator,
                        value: f.filter.value || '',
                        value2: f.filter.value2 || '',
                        logic: f.filter.logic || 'AND',
                        prefix: f.filter.prefix || '',
                        suffix: f.filter.suffix || ''
                    });
                }
            });
        }

        // Restore parameters
        if (elements.paramConfigComponent) {
            elements.paramConfigComponent.setParameters(state.parameters);
        }

        // Update conditions panel with restored conditions
        updateConditionsPanel();

        // Restore sorting and grouping
        updateSortGroupAvailableFields();
        if (elements.sortConfigComponent) {
            elements.sortConfigComponent.setSorting(state.sorting);
        }
        if (elements.groupConfigComponent) {
            elements.groupConfigComponent.setGrouping(state.grouping);
        }
        // Update field config to show sort/group indicators and parameters
        if (elements.fieldConfigComponent) {
            elements.fieldConfigComponent.setSorting(state.sorting);
            elements.fieldConfigComponent.setGrouping(state.grouping);
            elements.fieldConfigComponent.setParameters(state.parameters);
        }

        // Restore query options (DISTINCT and TOP N)
        const distinctCheckbox = document.getElementById('distinctCheckbox');
        if (distinctCheckbox) {
            distinctCheckbox.checked = state.distinct;
        }
        const topNInput = document.getElementById('topNInput');
        if (topNInput) {
            topNInput.value = state.topN || '';
        }

        // End batch — fires all queued events once (TABLES_CHANGED, FIELDS_CHANGED, etc.)
        // The reactive listeners will handle SQL sync, sort/group updates, etc.
        endBatch();

        // Ensure step navigation shows correctly for step 0
        updateStepNavigation(0);

        // Now that report is fully loaded, trigger background tasks
        // Use setTimeout to ensure UI updates first
        setTimeout(() => {
            buildFieldCacheInBackground();
        }, 100);

        // Trigger tips for report designer after report is loaded
        // Delay to let UI settle and ensure tips appear after loading overlay is gone
        setTimeout(() => {
            if (typeof CMATours !== 'undefined' && CMATours.reportDesigner) {
                CMATours.reportDesigner();
            }
        }, 1500);
    }

    function openFieldSearchDialog() {
        if (elements.fieldSearchDialog) {
            elements.fieldSearchDialog.open();
            // Clear previous search
            if (elements.fieldSearchInput) {
                elements.fieldSearchInput.value = '';
            }
            if (elements.fieldSearchResults) {
                elements.fieldSearchResults.innerHTML = `
                    <div class="empty-state" style="padding: 30px; text-align: center; color: var(--text-muted);">
                        <div style="margin-bottom: 10px;">Typ minimaal 2 tekens om te zoeken</div>
                        <div style="font-size: var(--font-size-sm);">Je kunt hier binnen alle teksten van de CMA zoeken naar een veldnaam of een formulier. Dus als een scherm een titel van een veld heeft, maar je weet de veldnaam niet, kun je hier daarop zoeken.</div>
                    </div>
                `;
            }
            // Focus on input
            setTimeout(() => {
                if (elements.fieldSearchInput) {
                    elements.fieldSearchInput.focus();
                }
            }, 100);
        }
    }

    async function performFieldSearch() {
        const query = elements.fieldSearchInput?.value?.trim() || '';
        const resultsContainer = elements.fieldSearchResults;

        if (!resultsContainer) return;

        if (query.length < 2) {
            resultsContainer.innerHTML = `
                <div class="empty-state" style="padding: 30px; text-align: center; color: var(--text-muted);">
                    <div style="margin-bottom: 10px;">Typ minimaal 2 tekens om te zoeken</div>
                    <div style="font-size: var(--font-size-sm);">Je kunt hier binnen alle teksten van de CMA zoeken naar een veldnaam of een formulier. Dus als een scherm een titel van een veld heeft, maar je weet de veldnaam niet, kun je hier daarop zoeken.</div>
                </div>
            `;
            return;
        }

        resultsContainer.innerHTML = `
            <div style="padding: 20px; text-align: center;">
                <lib-loader text="Zoeken..." active></lib-loader>
            </div>
        `;

        try {
            const response = await fetch(`api/report-schema.php?action=searchFields&database=${state.databaseId}&q=${encodeURIComponent(query)}`);
            const data = await response.json();

            if (data.success) {
                if (data.results.length === 0) {
                    resultsContainer.innerHTML = `
                        <div class="empty-state" style="padding: 30px; text-align: center; color: var(--text-muted);">
                            Geen velden gevonden voor "${escapeHtml(query)}"
                        </div>
                    `;
                    return;
                }

                let html = '<div class="field-search-list">';
                data.results.forEach(result => {
                    const caption = result.caption ? ` (${escapeHtml(result.caption)})` : '';
                    const typeIcon = getTypeIcon(result.typeCategory);
                    html += `
                        <div class="field-search-item" data-table="${escapeHtml(result.table)}" data-field="${escapeHtml(result.field)}"
                             style="display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-bottom: 1px solid var(--border-color); cursor: pointer;">
                            <span class="type-icon" style="color: var(--text-muted); width: 20px; text-align: center;">${typeIcon}</span>
                            <div style="flex: 1; min-width: 0;">
                                <div style="font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    ${escapeHtml(result.field)}${caption}
                                </div>
                                <div style="font-size: var(--font-size-sm); color: var(--text-muted);">
                                    Tabel: <strong style="color: #000;">${escapeHtml(CMA.displayTableName(result.table))}</strong>
                                </div>
                            </div>
                        </div>
                    `;
                });
                html += '</div>';
                resultsContainer.innerHTML = html;

                // Add click handlers
                resultsContainer.querySelectorAll('.field-search-item').forEach(item => {
                    item.addEventListener('click', () => {
                        selectTableFromSearch(item.dataset.table, item.dataset.field);
                    });
                });
            } else {
                resultsContainer.innerHTML = `
                    <div style="padding: 20px; text-align: center; color: var(--danger-color);">
                        ${escapeHtml(data.error || 'Onbekende fout')}
                    </div>
                `;
            }
        } catch (err) {
            resultsContainer.innerHTML = `
                <div style="padding: 20px; text-align: center; color: var(--danger-color);">
                    Fout bij zoeken: ${escapeHtml(err.message)}
                </div>
            `;
        }
    }

    function getTypeIcon(typeCategory) {
        // Categories from SchemaHelper::categorizeType: text, number, date, boolean, binary
        switch (typeCategory) {
            case 'text': return '<span class="lnr lnr-text-format" data-tooltip="Tekst"></span>';
            case 'number': return '<span class="lnr lnr-calculator" data-tooltip="Getal"></span>';
            case 'date': return '<span class="lnr lnr-calendar-full" data-tooltip="Datum/tijd"></span>';
            case 'boolean': return '<span class="lnr lnr-toggle-on" data-tooltip="Ja/Nee"></span>';
            case 'binary': return '<span class="lnr lnr-file-empty" data-tooltip="Binair/Bijlage"></span>';
            default: return '<span class="lnr lnr-database" data-tooltip="Onbekend type"></span>';
        }
    }

    async function selectTableFromSearch(tableName, fieldName) {
        // Close dialog
        if (elements.fieldSearchDialog) {
            elements.fieldSearchDialog.close();
        }

        // Check if table is already selected
        const isSelected = state.selectedTables.some(t => t.name === tableName);

        if (!isSelected) {
            // Find the table in the list and select it
            const tableObj = state.tables.find(t => t.name === tableName);
            if (tableObj) {
                await toggleTable(tableObj);
            } else {
                showWarning(`Tabel "${tableName}" niet gevonden in de lijst`);
                return;
            }
        }

        // Make the specific field visible in the field config
        if (fieldName) {
            const fieldIndex = state.fields.findIndex(f => f.table === tableName && f.field === fieldName);
            if (fieldIndex >= 0) {
                const wasHidden = !state.fields[fieldIndex].visible;
                state.fields[fieldIndex].visible = true;
                state.isDirty = true;

                // Update the field config component
                if (elements.fieldConfigComponent) {
                    elements.fieldConfigComponent.setFields(state.fields);
                }

                // Dispatch fields changed — sort/group, conditions, canvas, SQL preview all react
                dispatchCmaEvent(CmaEvents.FIELDS_CHANGED, { fields: state.fields, source: 'fieldSearch' });

                if (wasHidden) {
                    showSuccess(`Veld "${fieldName}" toegevoegd aan selectie`);
                } else {
                    showSuccess(`Veld "${fieldName}" is al geselecteerd`);
                }
            }
        }

        // Highlight the table in the table list
        renderTableList();

        // Scroll the table into view in the table list
        const tableItem = elements.tableListItems.querySelector(`.table-list-item.selected`);
        if (tableItem) {
            tableItem.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        // Flash the table on the canvas
        if (elements.schemaCanvas && typeof elements.schemaCanvas.highlightTable === 'function') {
            elements.schemaCanvas.highlightTable(tableName);
        }
    }

    // =========================================================================
    // Main Tabs: Ontwerper / Resultaat
    // =========================================================================

    /**
     * Switch between main tabs (Ontwerper/Resultaat)
     */
    function switchMainTab(tabName, updateTabs = true) {
        state.activeMainTab = tabName;

        // Update cma-tabs component if needed (e.g., when called programmatically)
        if (updateTabs && elements.mainTabs) {
            const tabIndex = tabName === 'designer' ? 0 : 1;
            elements.mainTabs.selectTab(tabIndex, false);  // false = don't emit event
        }

        // When switching to results, sync SQL
        if (tabName === 'results' && elements.resultsQueryPreview) {
            syncSqlToResultsEditor();
        }
    }

    /**
     * Sync SQL to the results preview by generating it from the report definition
     */
    async function syncSqlToResultsEditor() {
        if (!elements.resultsQueryPreview) return;

        // If in SQL-only mode, use the raw SQL
        if (state.rawSql) {
            elements.resultsQueryPreview.setSql(state.rawSql);
            return;
        }

        // Generate SQL from the report definition
        const definition = buildReportDefinition();
        if (!definition.tables || definition.tables.length === 0) {
            elements.resultsQueryPreview.setSql('');
            return;
        }

        try {
            const response = await fetch('api/report-query.php?action=getSql', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ definition })
            });

            const data = await response.json();
            if (data.success) {
                elements.resultsQueryPreview.setSql(data.sql || '');
            } else {
                elements.resultsQueryPreview.setError(data.error || 'Onbekende fout');
            }
        } catch (err) {
            elements.resultsQueryPreview.setError(err.message);
        }
    }

    /**
     * Run query from the results tab
     */
    async function runResultsQuery() {
        const preview = elements.resultsQueryPreview;
        const sql = preview?.sql?.trim();
        if (!sql) {
            showWarning('Voer eerst een SQL query in');
            return;
        }

        // Show loading state
        if (preview) {
            preview.setLoading(true);
        }

        try {
            const response = await fetch('api/report-query.php?action=executeRawSql', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    database: state.databaseId,
                    sql: sql,
                    limit: 1000
                })
            });

            const result = await response.json();

            if (!result.success) {
                throw new Error(result.error || 'Query uitvoeren mislukt');
            }

            // Display results using cma-query-preview
            if (preview) {
                preview.setLoading(false);
                preview.setData(result.columns || [], result.data || [], result.totalRows || 0);
                // Update export format options based on row count
                if (typeof window.updateExportFormatOptions === 'function') {
                    window.updateExportFormatOptions(result.totalRows || 0);
                }
            }

        } catch (error) {
            showError(error.message);
            if (preview) {
                preview.setLoading(false);
                preview.setError(error.message);
            }
        }
    }

    /**
     * Switch directly to SQL mode in results tab
     */
    function enterSqlMode() {
        state.mode = 'sql';
        switchMainTab('results');

        // Enter edit mode and focus the SQL editor
        setTimeout(() => {
            if (elements.resultsQueryPreview) {
                elements.resultsQueryPreview.enterEditMode();
            }
        }, 100);
    }

    function showError(message) {
        cmaLog.error(message);
        libMessage.error(message, {
            container: document.getElementById('messageContainer'),
            closable: true,
            autoDismiss: 8000
        });
    }

    function showSuccess(message) {
        libMessage.success(message, {
            container: document.getElementById('messageContainer'),
            closable: true,
            autoDismiss: 3000
        });
    }

    function showWarning(message) {
        libMessage.warning(message, {
            container: document.getElementById('messageContainer'),
            closable: true,
            autoDismiss: 5000
        });
    }

    // escapeHtml() provided by cma-utils.js

    // Start
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>

</BODY>
</HTML>
