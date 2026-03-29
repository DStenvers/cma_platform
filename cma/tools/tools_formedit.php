<?php
/**
 * CMA Form Definition Editor - Visual editor for existing JSON form definitions
 *
 * Allows editing all properties of form definitions based on the form-definition.schema.json.
 * New forms are created via the existing form wizard (tools_formwiz.php) in a dialog.
 */
use App\Library\Application;
use App\Library\Cache;
use App\Library\Database;
use App\Library\Error;
use App\Library\Request;
use App\Library\Response;
use Cma\CmaRepository;
use Cma\ConfigLoader;
use Cma\JsonFormLoader;
use Cma\SchemaHelper;
use Cma\SecurityHelper;
use Cma\ToolbarHelper;

require_once __DIR__ . '/../bootstrap.inc';

// Developer-only tool
if (!SecurityHelper::isDeveloper()) {
    Error::page('Toegang geweigerd', 'Deze functie is alleen beschikbaar voor developers.', false);
    exit;
}

Response::noCache();

// =========================================================================
// AJAX Endpoints
// =========================================================================

$action = Request::query('action', '');

// Clean output buffer for AJAX endpoints to prevent profiler/debug output from corrupting JSON
if ($action !== '') {
    while (ob_get_level() > 0) ob_end_clean();
}

// List all available forms (site-specific only, not CMA system forms)
if ($action === 'listForms') {
    header('Content-Type: application/json');
    $forms = [];

    // Only scan the site-specific forms directory (outside /cma)
    $siteFormsDir = __DIR__ . '/../../assets/forms';
    if (is_dir($siteFormsDir)) {
        $files = glob($siteFormsDir . '/*.json');
        $allNames = [];
        foreach ($files as $file) {
            $allNames[] = basename($file, '.json');
        }
        sort($allNames);

        foreach ($allNames as $name) {
            $raw = JsonFormLoader::loadRaw($name);
            $forms[] = [
                'name' => $name,
                'title' => $raw['title'] ?? $name,
                'table' => $raw['table'] ?? '',
            ];
        }
    }

    echo json_encode($forms);
    exit;
}

// Build recursive tree data for form hierarchy
if ($action === 'buildTree') {
    header('Content-Type: application/json');

    // Load all site-specific forms
    $siteFormsDir = __DIR__ . '/../../assets/forms';
    error_log("[FORMEDIT buildTree] siteFormsDir: $siteFormsDir, is_dir: " . (is_dir($siteFormsDir) ? 'YES' : 'NO'));
    $allForms = [];
    if (is_dir($siteFormsDir)) {
        $files = glob($siteFormsDir . '/*.json');
        error_log("[FORMEDIT buildTree] glob found " . count($files) . " JSON files");
        if (count($files) < 5) {
            error_log("[FORMEDIT buildTree] files: " . implode(', ', $files));
        }
        $loadedCount = 0;
        $nullCount = 0;
        foreach ($files as $file) {
            $name = basename($file, '.json');
            $raw = JsonFormLoader::loadRaw($name);
            if ($raw !== null) {
                $allForms[$name] = $raw;
                $loadedCount++;
            } else {
                $nullCount++;
                error_log("[FORMEDIT buildTree] loadRaw returned null for: $name");
            }
        }
        error_log("[FORMEDIT buildTree] loaded: $loadedCount, null: $nullCount, total forms: " . count($allForms));
    } else {
        error_log("[FORMEDIT buildTree] WARNING: siteFormsDir does not exist: $siteFormsDir");
    }

    // Build set of all forms that are subforms of another form
    $childForms = [];
    foreach ($allForms as $name => $def) {
        $subs = $def['subforms'] ?? [];
        foreach ($subs as $sub) {
            $subName = $sub['form'] ?? '';
            if ($subName !== '') {
                $childForms[$subName] = true;
            }
        }
    }

    // Recursive function to build tree nodes
    $buildNode = function ($name) use (&$allForms, &$buildNode) {
        $def = $allForms[$name] ?? null;
        $title = $def ? ($def['title'] ?? $name) : $name;
        $subs = $def ? ($def['subforms'] ?? []) : [];

        // Sort subforms by order
        usort($subs, function ($a, $b) {
            return ($a['order'] ?? 999) - ($b['order'] ?? 999);
        });

        // Build child nodes
        $children = [];
        foreach ($subs as $sub) {
            $subName = $sub['form'] ?? '';
            if ($subName !== '' && isset($allForms[$subName])) {
                $children[] = $buildNode($subName);
            }
        }

        if (count($children) > 0) {
            // Folder node (has children) - also clickable via id
            return [
                'type' => 'folder',
                'id' => $name,
                'label' => $title,
                'children' => $children,
            ];
        } else {
            // Leaf item
            return [
                'type' => 'item',
                'id' => $name,
                'label' => $title,
                'icon' => $name,
            ];
        }
    };

    // Root forms = forms that are NOT a subform of any other form
    $rootNames = [];
    foreach ($allForms as $name => $def) {
        if (!isset($childForms[$name])) {
            $rootNames[] = $name;
        }
    }
    sort($rootNames);

    // Build root-level tree wrapped in a single folder
    $treeChildren = [];
    foreach ($rootNames as $name) {
        $treeChildren[] = $buildNode($name);
    }

    $tree = $treeChildren;

    error_log("[FORMEDIT buildTree] rootNames: " . count($rootNames) . ", treeChildren: " . count($treeChildren));
    error_log("[FORMEDIT buildTree] JSON output length: " . strlen(json_encode($tree)));
    echo json_encode($tree);
    exit;
}

// Load a specific form definition
if ($action === 'loadForm') {
    header('Content-Type: application/json');
    $formName = Request::query('formName', '');
    if (empty($formName)) {
        echo json_encode(['error' => 'Geen formuliernaam opgegeven']);
        exit;
    }
    $raw = JsonFormLoader::loadRaw($formName);
    if ($raw === null) {
        echo json_encode(['error' => 'Formulier niet gevonden: ' . $formName]);
        exit;
    }
    echo json_encode($raw);
    exit;
}

// Get tables for a database
if ($action === 'getTables') {
    header('Content-Type: application/json');
    $databaseId = Request::query('database', '');
    if (empty($databaseId)) {
        echo json_encode([]);
        exit;
    }
    try {
        $pdo = Database::getConnection($databaseId);
        $dbType = Database::getDatabaseType($pdo);
        $tables = [];

        if ($dbType === 'access') {
            // MS Access via native ODBC odbc_tables()
            $tablesFound = false;
            if (function_exists('odbc_tables')) {
                $configKey = 'conn_' . $databaseId;
                $dsn = \App\Library\Application::get($configKey, '') ?: \App\Library\Application::get('conn_data', '');
                if (!empty($dsn)) {
                    $nativeDsn = preg_replace('/^odbc:/i', '', $dsn);
                    $odbc = @odbc_connect($nativeDsn, '', '', SQL_CUR_USE_ODBC);
                    if ($odbc) {
                        $result = @odbc_tables($odbc, null, null, null, "'TABLE'");
                        if ($result) {
                            while ($row = odbc_fetch_array($result)) {
                                $name = $row['TABLE_NAME'] ?? '';
                                if (empty($name) || substr($name, 0, 4) === 'MSys' || substr($name, 0, 4) === '~TMP' || substr($name, 0, 1) === '_') continue;
                                $tables[] = $name;
                            }
                            @odbc_free_result($result);
                            $tablesFound = true;
                        }
                        @odbc_close($odbc);
                    }
                }
            }
            if (!$tablesFound) {
                // Fallback: scan form definitions
                $formsDir = __DIR__ . '/../../assets/forms';
                if (is_dir($formsDir)) {
                    foreach (glob($formsDir . '/*.json') as $file) {
                        $def = @json_decode(file_get_contents($file), true);
                        if (!empty($def['table']) && !in_array($def['table'], $tables)) {
                            $tables[] = $def['table'];
                        }
                    }
                }
            }
            sort($tables);
        } elseif ($dbType === 'sqlserver') {
            $stmt = $pdo->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME");
            if ($stmt) {
                while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $tables[] = $row['TABLE_NAME'];
                }
            }
        } elseif ($dbType === 'sqlite') {
            $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
            if ($stmt) {
                while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $tables[] = $row['name'];
                }
            }
        }

        echo json_encode($tables);
    } catch (\Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// Get columns for a table
if ($action === 'getColumns') {
    header('Content-Type: application/json');
    $databaseId = Request::query('database', '');
    $tableName = Request::query('table', '');
    if (empty($databaseId) || empty($tableName)) {
        echo json_encode([]);
        exit;
    }
    try {
        $pdo = Database::getConnection($databaseId);
        $dbType = Database::getDatabaseType($pdo);
        $columns = [];

        if ($dbType === 'access') {
            // For Access, use a dummy query to get column metadata
            $stmt = $pdo->prepare("SELECT TOP 1 * FROM [$tableName]");
            $stmt->execute();
            $colCount = $stmt->columnCount();
            for ($i = 0; $i < $colCount; $i++) {
                $meta = $stmt->getColumnMeta($i);
                $columns[] = [
                    'name' => $meta['name'] ?? '',
                    'dataType' => $meta['native_type'] ?? '',
                    'length' => $meta['len'] ?? null,
                ];
            }
        } elseif ($dbType === 'sqlserver') {
            $stmt = $pdo->prepare("SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ? ORDER BY ORDINAL_POSITION");
            $stmt->execute([$tableName]);
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $columns[] = [
                    'name' => $row['COLUMN_NAME'],
                    'dataType' => $row['DATA_TYPE'],
                    'length' => $row['CHARACTER_MAXIMUM_LENGTH'],
                ];
            }
        } elseif ($dbType === 'sqlite') {
            $stmt = $pdo->query("PRAGMA table_info([$tableName])");
            if ($stmt) {
                while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $columns[] = [
                        'name' => $row['name'],
                        'dataType' => $row['type'],
                        'length' => null,
                    ];
                }
            }
        }

        echo json_encode($columns);
    } catch (\Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// Save form definition
if (Request::isPost()) {
    $contentType = Request::server('CONTENT_TYPE');
    if (strpos($contentType, 'application/json') !== false) {
        header('Content-Type: application/json');
        $jsonInput = json_decode(file_get_contents('php://input'), true);
        if (!$jsonInput) {
            echo json_encode(['error' => 'Ongeldige JSON invoer']);
            exit;
        }

        $formAction = $jsonInput['action'] ?? '';

        if ($formAction === 'save') {
            $formName = $jsonInput['formName'] ?? '';
            $definition = $jsonInput['definition'] ?? null;

            if (empty($formName) || $definition === null) {
                echo json_encode(['error' => 'Formuliernaam en definitie zijn verplicht']);
                exit;
            }

            // Save using JsonFormLoader
            $success = JsonFormLoader::save($formName, $definition);
            if ($success) {
                // Clear caches
                JsonFormLoader::clearCache($formName);
                Cache::clear();
                echo json_encode(['success' => true, 'message' => 'Formulier opgeslagen']);
            } else {
                echo json_encode(['error' => 'Opslaan mislukt']);
            }
            exit;
        }
    }
}

// =========================================================================
// HTML Output
// =========================================================================

$defaultDbId = CmaRepository::getDefaultDatabaseId() ?? 6;

$initialFormName = Request::query('formName', '');

$extraScript = '
' . cma_script('/library/sql-utils.js', true)
  . cma_script('/cma/assets/js/main.js', true)
  . cma_script('/cma/webcomponents/cma-tree.js', true)
  . cma_script('/cma/webcomponents/cma-fold.js', true) . '
<script>
(function() {
    "use strict";

    var FIELD_TYPES = [
        "textbox", "memo", "checkbox", "combobox", "date", "time", "datetime",
        "dropdown", "email", "password", "file", "image", "url", "checklist",
        "checklisttree", "checklistinline", "sortlist", "label", "groupseparator",
        "readonly", "radiogroup", "custom", "userlist", "xmlstore", "directory"
    ];

    var SOURCE_TYPES = ["combobox", "checklist", "userlist", "checklisttree", "checklistinline", "dropdown"];

    var FormEditor = {
        currentForm: null,
        definition: null,
        isDirty: false,
        editingFieldIndex: null,
        editingSubformIndex: null,
        _formList: null,

        init: function() {
            this.bindEvents();
            this.updateDirtyIndicator();
            this.loadTree(this._initialFormName || null);
        },

        bindEvents: function() {
            var self = this;

            // Tree item click
            var formTree = document.getElementById("formTree");
            if (formTree) {
                formTree.addEventListener("item-click", function(e) {
                    var formName = e.detail.id;
                    if (formName) {
                        self.confirmUnsaved().then(function(proceed) {
                            if (proceed) {
                                self.loadForm(formName);
                                // Update URL without reload
                                var newUrl = window.location.pathname + "?formName=" + encodeURIComponent(formName);
                                history.pushState({ formName: formName }, "", newUrl);
                            }
                        });
                    }
                });
            }

            // Toolbar buttons (tb-btn spans from ToolbarHelper)
            var saveBtn = document.getElementById("saveBtn");
            if (saveBtn) {
                saveBtn.addEventListener("click", function() {
                    if (saveBtn.classList.contains("disabled")) return;
                    self.saveForm();
                });
            }

            var jsonBtn = document.getElementById("jsonBtn");
            if (jsonBtn) {
                jsonBtn.addEventListener("click", function() {
                    if (jsonBtn.classList.contains("disabled")) return;
                    self.showJsonPreview();
                });
            }

            var newBtn = document.getElementById("newBtn");
            if (newBtn) {
                newBtn.addEventListener("click", function() { self.openFormWizard(); });
            }

            // Initialize database combo options
            var dbCombo = document.getElementById("gs-database");
            if (dbCombo && typeof dbCombo.setOptions === "function") {
                dbCombo.setOptions([
                    { value: "data", label: "data" },
                    { value: "rep", label: "rep" },
                    { value: "users", label: "users" },
                    { value: "json", label: "json" }
                ]);
                dbCombo.value = "data";
            }

            // Reload tables when database changes
            document.getElementById("gs-database").addEventListener("change", function() {
                var val = this.value || (this.detail && this.detail.value) || "";
                self.loadTables(val, "");
            });

            // Track changes on inputs inside form sections
            document.getElementById("editorArea").addEventListener("input", function() {
                self.markDirty();
            });
            document.getElementById("editorArea").addEventListener("change", function() {
                self.markDirty();
            });

            // Add field button
            var addFieldBtn = document.getElementById("addFieldBtn");
            if (addFieldBtn) {
                addFieldBtn.addEventListener("click", function() { self.openFieldEditor(null); });
            }

            // Add subform button
            var addSubformBtn = document.getElementById("addSubformBtn");
            if (addSubformBtn) {
                addSubformBtn.addEventListener("click", function() { self.openSubformEditor(null); });
            }

            // Add list column button
            var addColBtn = document.getElementById("addListColBtn");
            if (addColBtn) {
                addColBtn.addEventListener("click", function() { self.addListColumn(); });
            }

            // Field editor save
            var saveFieldBtn = document.getElementById("saveFieldBtn");
            if (saveFieldBtn) {
                saveFieldBtn.addEventListener("click", function() { self.saveFieldFromEditor(); });
            }

            // Subform editor save
            var saveSubformBtn = document.getElementById("saveSubformBtn");
            if (saveSubformBtn) {
                saveSubformBtn.addEventListener("click", function() { self.saveSubformFromEditor(); });
            }

            // Subform form selector auto-fill
            var sfFormCombo = document.getElementById("sf-form");
            if (sfFormCombo) {
                sfFormCombo.addEventListener("change", function(e) {
                    var formName = e.detail ? e.detail.value : sfFormCombo.value;
                    if (!formName || !self._formList) return;
                    var match = self._formList.find(function(f) { return f.name === formName; });
                    if (match) {
                        var titleEl = document.getElementById("sf-title");
                        var titleEnEl = document.getElementById("sf-titleEn");
                        // Only auto-fill if title is still empty
                        if (titleEl && !titleEl.value.trim()) {
                            titleEl.value = match.title || formName;
                        }
                    }
                });
            }

            // Field type change in editor - show/hide source tab
            var fieldTypeSelect = document.getElementById("fe-type");
            if (fieldTypeSelect) {
                fieldTypeSelect.addEventListener("change", function() {
                    self.updateFieldEditorTabs();
                });
            }

            // Add extra button
            var addExtraBtnBtn = document.getElementById("addExtraBtn");
            if (addExtraBtnBtn) {
                addExtraBtnBtn.addEventListener("click", function() { self.addExtraButton(); });
            }

            // Add tip button
            var addTipBtn = document.getElementById("addTipBtn");
            if (addTipBtn) {
                addTipBtn.addEventListener("click", function() { self.addTip(); });
            }

            // Browser back/forward
            window.addEventListener("popstate", function(e) {
                if (e.state && e.state.formName) {
                    self.loadForm(e.state.formName);
                    var tree = document.getElementById("formTree");
                    if (tree && typeof tree.selectById === "function") {
                        tree.selectById(e.state.formName);
                    }
                }
            });

            // Global expand/collapse for toolbar buttons
            window.fExpandAll = function() {
                var tree = document.getElementById("formTree");
                if (tree) tree.expandAll();
            };
            window.fCollapseAll = function() {
                var tree = document.getElementById("formTree");
                if (tree) tree.collapseAll();
            };

            // View toggle (tree/table)
            window.feSetView = function(mode) {
                self.setViewMode(mode);
            };

            // Table row clicks
            document.getElementById("formTableBody").addEventListener("click", function(e) {
                var row = e.target.closest("tr");
                if (!row || !row.dataset.formName) return;
                var formName = row.dataset.formName;
                self.confirmUnsaved().then(function(proceed) {
                    if (proceed) {
                        self.loadForm(formName);
                        // Highlight selected row
                        var rows = document.querySelectorAll("#formTableBody tr");
                        rows.forEach(function(r) { r.classList.remove("selected"); });
                        row.classList.add("selected");
                        // Also select in tree for consistency
                        var tree = document.getElementById("formTree");
                        if (tree && typeof tree.selectById === "function") {
                            tree.selectById(formName);
                        }
                        var newUrl = window.location.pathname + "?formName=" + encodeURIComponent(formName);
                        history.pushState({ formName: formName }, "", newUrl);
                    }
                });
            });

            // Restore saved view mode
            var savedMode = localStorage.getItem("fe_view_mode") || "tree";
            this.setViewMode(savedMode);

            // Search-as-you-type for tree and table
            var searchInput = document.getElementById("feTreeSearch");
            if (searchInput) {
                var searchTimer = null;
                searchInput.addEventListener("input", function(e) {
                    clearTimeout(searchTimer);
                    var val = e.detail ? e.detail.value : searchInput.value;
                    searchTimer = setTimeout(function() {
                        self.filterList(val);
                    }, 150);
                });
                searchInput.addEventListener("clear", function() {
                    self.filterList("");
                });
            }
        },

        filterList: function(term) {
            // Filter tree
            var tree = document.getElementById("formTree");
            if (tree && typeof tree.filter === "function") {
                tree.filter(term);
            }
            // Filter table
            var rows = document.querySelectorAll("#formTableBody tr");
            var needle = (term || "").toLowerCase().trim();
            rows.forEach(function(row) {
                if (!needle) {
                    row.style.display = "";
                    return;
                }
                var text = row.textContent.toLowerCase();
                row.style.display = text.indexOf(needle) !== -1 ? "" : "none";
            });
        },

        // =====================================================================
        // Form List (for subform editor combos)
        // =====================================================================

        loadFormList: function() {
            var self = this;
            if (this._formList) return Promise.resolve(this._formList);
            return fetch("tools_formedit.php?action=listForms")
                .then(function(r) { return r.json(); })
                .then(function(list) {
                    self._formList = list;
                    return list;
                })
                .catch(function() {
                    return [];
                });
        },

        // =====================================================================
        // Tree Loading
        // =====================================================================

        loadTree: function(selectAndLoad) {
            var self = this;
            var tree = document.getElementById("formTree");
            fetch("tools_formedit.php?action=buildTree")
                .then(function(r) {
                    return r.text();
                })
                .then(function(text) {
                    var data;
                    try {
                        data = JSON.parse(text);
                    } catch (e) {
                        return;
                    }
                    self._treeData = data;
                    if (tree && typeof tree.setData === "function") {
                        tree.setData(data);
                        tree.expandAll();
                    }
                    // Populate table view from same data
                    self.populateTableView(data);
                    // Re-select current form in tree (after save/refresh)
                    var formToSelect = selectAndLoad || self.currentForm || self._initialFormName;
                    if (formToSelect && tree && typeof tree.selectById === "function") {
                        setTimeout(function() {
                            tree.selectById(formToSelect);
                            // Only load form if not already loaded (initial load or explicit request)
                            if (selectAndLoad || (!self.currentForm && self._initialFormName)) {
                                self.loadForm(formToSelect);
                            }
                        }, 100);
                    }
                })
                .catch(function() {});
        },

        // =====================================================================
        // View Toggle (Tree / Table)
        // =====================================================================

        _treeData: null,

        setViewMode: function(mode) {
            var treeArea = document.querySelector("#leftlist .tree-area");
            var tableArea = document.querySelector("#leftlist .table-area");
            var btnTree = document.getElementById("btn_viewTree");
            var btnTable = document.getElementById("btn_viewTable");

            if (mode === "table") {
                if (treeArea) treeArea.style.display = "none";
                if (tableArea) tableArea.style.display = "";
                document.body.classList.add("fe-view-table");
            } else {
                mode = "tree";
                if (treeArea) treeArea.style.display = "";
                if (tableArea) tableArea.style.display = "none";
                document.body.classList.remove("fe-view-table");
            }
            if (btnTree) btnTree.classList.toggle("fe-view-active", mode === "tree");
            if (btnTable) btnTable.classList.toggle("fe-view-active", mode === "table");
            localStorage.setItem("fe_view_mode", mode);

            // Highlight current form in table if switching to table view
            if (mode === "table" && this.currentForm) {
                this.selectTableRow(this.currentForm);
            }
        },

        populateTableView: function(treeData) {
            var tbody = document.getElementById("formTableBody");
            if (!tbody) return;

            // Flatten tree data into rows
            var rows = [];
            var flatten = function(nodes, depth) {
                nodes.forEach(function(node) {
                    var subCount = (node.children && node.children.length) || 0;
                    rows.push({
                        name: node.id,
                        label: node.label || node.id,
                        subCount: subCount,
                        depth: depth
                    });
                    if (node.children) {
                        flatten(node.children, depth + 1);
                    }
                });
            };
            flatten(treeData, 0);

            // Also fetch the listForms data for table column
            var self = this;
            this.loadFormList().then(function(formList) {
                var tableMap = {};
                formList.forEach(function(f) { tableMap[f.name] = f.table || ""; });

                var html = "";
                rows.forEach(function(r) {
                    var indent = r.depth > 0 ? "padding-left:" + (r.depth * 16) + "px" : "";
                    html += "<tr data-form-name=\"" + self.escapeHtml(r.name) + "\">";
                    html += "<td style=\"" + indent + "\">" + self.escapeHtml(r.name) + "</td>";
                    html += "<td>" + self.escapeHtml(r.label) + "</td>";
                    html += "<td>" + self.escapeHtml(tableMap[r.name] || "") + "</td>";
                    html += "<td style=\"text-align:center\">" + (r.subCount > 0 ? r.subCount : "") + "</td>";
                    html += "</tr>";
                });
                tbody.innerHTML = html;

                // If a form is already loaded, highlight it
                if (self.currentForm) {
                    self.selectTableRow(self.currentForm);
                }
            });
        },

        selectTableRow: function(formName) {
            var rows = document.querySelectorAll("#formTableBody tr");
            rows.forEach(function(r) {
                r.classList.toggle("selected", r.dataset.formName === formName);
            });
        },

        _initialFormName: ' . json_encode($initialFormName) . ',

        loadForm: function(name) {
            var self = this;
            var spinner = document.getElementById("formLoadSpinner");
            if (spinner) spinner.style.display = "";

            fetch("tools_formedit.php?action=loadForm&formName=" + encodeURIComponent(name))
                .then(function(r) { return r.json(); })
                .then(function(def) {
                    if (spinner) spinner.style.display = "none";
                    if (def.error) {
                        libToast.error(def.error);
                        return;
                    }
                    self.currentForm = name;
                    self.definition = def;
                    self.isDirty = false;
                    self.updateDirtyIndicator();
                    // Sync table view selection
                    self.selectTableRow(name);
                    var jBtn = document.getElementById("jsonBtn");
                    if (jBtn) jBtn.classList.remove("disabled");
                    self.populateUI(def);
                })
                .catch(function(e) {
                    if (spinner) spinner.style.display = "none";
                    cmaLog.error("loadForm:", e);
                });
        },

        // =====================================================================
        // Populate UI from definition
        // =====================================================================

        populateUI: function(def) {
            // Hide welcome message and show editor area
            var welcomeMsg = document.getElementById("welcomeMsg");
            if (welcomeMsg) welcomeMsg.style.display = "none";
            document.getElementById("editorArea").style.display = "";

            // General settings
            document.getElementById("gs-name").value = def.name || "";
            document.getElementById("gs-title").value = def.title || "";
            document.getElementById("gs-titleSingular").value = def.titleSingular || "";

            var dbCombo = document.getElementById("gs-database");
            if (dbCombo) dbCombo.value = def.database || "data";

            // Load full table list for the database, then set current value
            this.loadTables(def.database || "data", def.table || "");
            document.getElementById("gs-idField").value = def.idField || "ID";

            // Parent form combo - populate with form names
            var parentFormCombo = document.getElementById("gs-parentForm");
            if (parentFormCombo && typeof parentFormCombo.setOptions === "function") {
                this.loadFormList().then(function(formList) {
                    var options = [{ value: "", label: "(geen - hoofdformulier)" }];
                    formList.forEach(function(f) {
                        options.push({ value: f.name, label: (f.title || f.name) + " (" + f.name + ")" });
                    });
                    parentFormCombo.setOptions(options);
                    parentFormCombo.value = def.parentForm || "";
                });
            }

            // New form-level properties
            document.getElementById("gs-quickSearchFields").value = (def.quickSearchFields || []).join(", ");
            document.getElementById("gs-activeField").value = def.activeField || "";
            this.setSwitch("gs-securityByUser", def.securityByUser === true);

            // Switches
            this.setSwitch("gs-allowAdd", def.allowAdd !== false);
            this.setSwitch("gs-allowDelete", def.allowDelete !== false);
            this.setSwitch("gs-allowCopy", def.allowCopy === true);
            this.setSwitch("gs-storeLastModified", def.storeLastModified === true);

            // List settings
            var rawQuery = def.listQuery || "";
            document.getElementById("ls-listQuery").value = rawQuery ? SqlUtils.formatSql(rawQuery) : "";
            document.getElementById("ls-detailField").value = def.detailField || "";

            var gf = def.groupFields || [];
            document.getElementById("ls-groupField1").value = gf[0] || "";
            document.getElementById("ls-groupField2").value = gf[1] || "";
            document.getElementById("ls-groupField3").value = gf[2] || "";

            // List columns
            this.renderListColumns(def.listColumns || []);

            // Fields
            this.renderFieldsList(def.fields || []);

            // Subforms
            this.renderSubformsList(def.subforms || []);

            // Advanced
            var filter = def.filter || {};
            document.getElementById("adv-filterField").value = filter.field || def.filterIdName || "";
            document.getElementById("adv-filterDescr").value = filter.description || "";
            document.getElementById("adv-filterSql").value = filter.sql || "";
            document.getElementById("adv-previewUrl").value = def.previewUrl || "";
            document.getElementById("adv-afterPostUrl").value = def.afterPostUrl || "";
            document.getElementById("adv-onLoadJs").value = def.onLoadJs || "";

            // Extra buttons
            this.renderExtraButtons(def.extraButtons || []);

            // Tips
            this.renderTips(def.tips || []);
        },

        // =====================================================================
        // Collect definition from UI
        // =====================================================================

        collectDefinition: function() {
            var def = JSON.parse(JSON.stringify(this.definition || {}));

            // General
            def.name = document.getElementById("gs-name").value;
            def.title = document.getElementById("gs-title").value;
            var titleSingular = document.getElementById("gs-titleSingular").value;
            if (titleSingular) def.titleSingular = titleSingular; else delete def.titleSingular;

            var tableCombo = document.getElementById("gs-table");
            def.table = (tableCombo && tableCombo.value) || "";
            var dbCombo = document.getElementById("gs-database");
            def.database = (dbCombo && dbCombo.value) || "data";
            def.idField = document.getElementById("gs-idField").value || "ID";
            var parentFormCombo = document.getElementById("gs-parentForm");
            var parentForm = (parentFormCombo && parentFormCombo.value) || "";
            if (parentForm) def.parentForm = parentForm; else delete def.parentForm;


            def.securityByUser = this.getSwitch("gs-securityByUser");
            if (!def.securityByUser) delete def.securityByUser;

            var qsf = document.getElementById("gs-quickSearchFields").value.trim();
            if (qsf) {
                def.quickSearchFields = qsf.split(/[,;]\s*/).filter(function(s) { return s; });
            } else {
                delete def.quickSearchFields;
            }

            var activeField = document.getElementById("gs-activeField").value.trim();
            if (activeField) def.activeField = activeField; else delete def.activeField;

            def.allowAdd = this.getSwitch("gs-allowAdd");
            def.allowDelete = this.getSwitch("gs-allowDelete");
            def.allowCopy = this.getSwitch("gs-allowCopy");
            def.storeLastModified = this.getSwitch("gs-storeLastModified");

            // List settings
            def.listQuery = document.getElementById("ls-listQuery").value;
            def.detailField = document.getElementById("ls-detailField").value;

            var g1 = document.getElementById("ls-groupField1").value;
            var g2 = document.getElementById("ls-groupField2").value;
            var g3 = document.getElementById("ls-groupField3").value;
            var gf = [];
            if (g1) gf.push(g1);
            if (g2) gf.push(g2);
            if (g3) gf.push(g3);
            if (gf.length > 0) def.groupFields = gf; else delete def.groupFields;

            // List columns
            def.listColumns = this.collectListColumns();
            if (def.listColumns.length === 0) delete def.listColumns;

            // Fields (already maintained in this.definition.fields via field editor)
            // def.fields is kept in sync by saveFieldFromEditor/deleteField/moveField

            // Subforms - auto-assign order from array position
            if (def.subforms && def.subforms.length > 0) {
                def.subforms.forEach(function(sf, i) {
                    sf.order = i + 1;
                });
            }

            // Advanced
            var filterField = document.getElementById("adv-filterField").value;
            var filterDescr = document.getElementById("adv-filterDescr").value;
            var filterSql = document.getElementById("adv-filterSql").value;
            if (filterField) {
                def.filter = { field: filterField };
                if (filterDescr) def.filter.description = filterDescr;
                if (filterSql) def.filter.sql = filterSql;
                delete def.filterIdName;
            } else {
                delete def.filter;
                delete def.filterIdName;
            }

            def.previewUrl = document.getElementById("adv-previewUrl").value;
            def.afterPostUrl = document.getElementById("adv-afterPostUrl").value;
            def.onLoadJs = document.getElementById("adv-onLoadJs").value;

            // Extra buttons
            def.extraButtons = this.collectExtraButtons();
            if (def.extraButtons.length === 0) delete def.extraButtons;

            // Tips
            def.tips = this.collectTips();
            if (!def.tips || def.tips.length === 0) delete def.tips;

            // Clean up empty strings at top level
            var cleanKeys = ["previewUrl", "afterPostUrl", "onLoadJs", "detailField"];
            cleanKeys.forEach(function(k) {
                if (def[k] === "") delete def[k];
            });

            return def;
        },

        // =====================================================================
        // Save
        // =====================================================================

        saveForm: function() {
            var self = this;
            if (!this.currentForm) return;

            var def = this.collectDefinition();

            // Validate required fields
            var errors = [];
            if (!def.table) errors.push("Tabel is verplicht");
            if (!def.title) errors.push("Titel is verplicht");
            if (!def.idField) errors.push("ID-veld is verplicht");
            if (errors.length > 0) {
                libToast.error(errors.join(", "));
                return;
            }

            fetch("tools_formedit.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ action: "save", formName: this.currentForm, definition: def })
            })
            .then(function(r) { return r.json(); })
            .then(function(result) {
                if (result.error) {
                    libToast.error(result.error);
                } else {
                    libToast.success("Formulier opgeslagen");
                    self.isDirty = false;
                    self.definition = def;
                    self.updateDirtyIndicator();
                    // Show dev notice with actual filename
                    var notice = document.getElementById("fe-save-notice");
                    if (notice) {
                        notice.show("Opgeslagen: /assets/forms/" + self.currentForm + ".json. Update locale kopie\u00ebn zodat deze meegaat in updates in het versiebeheer-systeem.", "info");
                    }
                    // Reload tree in case subforms changed
                    self.loadTree();
                }
            })
            .catch(function(e) {
                libToast.error("Fout bij opslaan: " + e.message);
            });
        },

        // =====================================================================
        // Fields Management
        // =====================================================================

        renderFieldsList: function(fields) {
            var self = this;
            var container = document.getElementById("fieldsListBody");
            if (!container) return;
            container.innerHTML = "";


            fields.forEach(function(field, idx) {
                var tr = document.createElement("tr");
                tr.innerHTML =
                    \'<td>\' + self.escapeHtml(field.name) + \'</td>\' +
                    \'<td><lib-label type="information" size="small">\' + self.escapeHtml(field.type || "textbox") + \'</lib-label></td>\' +
                    \'<td>\' + self.escapeHtml(field.caption || "") + \'</td>\' +
                    \'<td class="actions-cell">\' +
                        \'<button class="btn btn-small" data-action="edit" data-idx="\' + idx + \'" title="Bewerk"><span class="lnr lnr-pencil"></span></button>\' +
                        \'<button class="btn btn-small" data-action="up" data-idx="\' + idx + \'" title="Omhoog"\' + (idx === 0 ? " disabled" : "") + \'><span class="lnr lnr-chevron-up"></span></button>\' +
                        \'<button class="btn btn-small" data-action="down" data-idx="\' + idx + \'" title="Omlaag"\' + (idx === fields.length - 1 ? " disabled" : "") + \'><span class="lnr lnr-chevron-down"></span></button>\' +
                        \'<button class="btn btn-small btn-danger" data-action="delete" data-idx="\' + idx + \'" title="Verwijder"><span class="lnr lnr-trash"></span></button>\' +
                    \'</td>\';
                tr.addEventListener("click", function(e) {
                    var btn = e.target.closest("button[data-action]");
                    if (!btn) return;
                    var action = btn.dataset.action;
                    var i = parseInt(btn.dataset.idx);
                    if (action === "edit") self.openFieldEditor(i);
                    else if (action === "up") self.moveField(i, -1);
                    else if (action === "down") self.moveField(i, 1);
                    else if (action === "delete") self.deleteField(i);
                });
                container.appendChild(tr);
            });
            // Refresh lib-table filters for dynamically populated content
            var libTable = container.closest(\'lib-table\');
            if (libTable && libTable.refresh) libTable.refresh();
        },

        openFieldEditor: function(index) {
            this.editingFieldIndex = index;
            var field = index !== null ? (this.definition.fields[index] || {}) : {};
            var isNew = index === null;

            // Populate dialog fields
            document.getElementById("fe-name").value = field.name || "";
            document.getElementById("fe-type").value = field.type || "textbox";
            document.getElementById("fe-caption").value = field.caption || "";
            document.getElementById("fe-captionEn").value = field.captionEnglish || "";
            document.getElementById("fe-hint").value = field.hint || "";
            document.getElementById("fe-hintEn").value = field.hintEnglish || "";
            this.setSwitch("fe-required", field.required === true);
            this.setSwitch("fe-readonly", field.readonly === true);
            this.setSwitch("fe-beheer", field.beheer === true || field.adminOnly === true);

            // Data tab
            document.getElementById("fe-dataType").value = field.dataType || "";
            document.getElementById("fe-maxLength").value = field.maxLength || "";
            document.getElementById("fe-height").value = field.height || "";
            document.getElementById("fe-format").value = field.format || "";
            document.getElementById("fe-defaultValue").value = field.default || field.defaultValue || "";

            // Source tab
            var src = field.source || {};
            document.getElementById("fe-sourceTable").value = src.table || field.sourceTable || "";
            document.getElementById("fe-sourceIdField").value = src.valueField || field.idField || "";
            document.getElementById("fe-sourceDisplayField").value = src.displayField || field.displayField || "";
            document.getElementById("fe-sourceFilter").value = src.filter || "";
            document.getElementById("fe-filterByField").value = field.filterByField || "";
            document.getElementById("fe-sql").value = field.sql || "";
            document.getElementById("fe-sqlDatabase").value = field.database || "";

            // Static options for radiogroup
            this.renderStaticOptions(field.options || []);

            // Advanced tab
            document.getElementById("fe-renderer").value = field.renderer || "";
            document.getElementById("fe-css").value = field.css || "";
            document.getElementById("fe-class").value = field.class || "";
            document.getElementById("fe-optionsJson").value = field.options && typeof field.options === "object" && !Array.isArray(field.options) ? JSON.stringify(field.options, null, 2) : "";

            // Dependencies
            this.renderDependencies(field.dependencies || []);

            // Validation
            this.renderValidation(field.validation || []);

            this.updateFieldEditorTabs();

            // Open dialog
            var dlg = document.getElementById("fieldEditorDialog");
            dlg.setAttribute("heading", isNew ? "Nieuw veld" : "Veld bewerken: " + (field.name || ""));
            dlg.open();
        },

        updateFieldEditorTabs: function() {
            var type = document.getElementById("fe-type").value;
            var srcTab = document.querySelector("#fieldEditorTabs [data-tab-id=source]");
            if (srcTab) {
                srcTab.style.display = SOURCE_TYPES.indexOf(type) >= 0 ? "" : "none";
            }
            // Show height field only for memo
            var heightRow = document.getElementById("fe-height-row");
            if (heightRow) {
                heightRow.style.display = type === "memo" ? "" : "none";
            }
        },

        saveFieldFromEditor: function() {
            var field = {};
            field.name = document.getElementById("fe-name").value.trim();
            if (!field.name) {
                libToast.error("Veldnaam is verplicht");
                return;
            }
            field.type = document.getElementById("fe-type").value;
            field.caption = document.getElementById("fe-caption").value;
            var captionEn = document.getElementById("fe-captionEn").value;
            if (captionEn) field.captionEnglish = captionEn;
            var hint = document.getElementById("fe-hint").value;
            if (hint) field.hint = hint;
            var hintEn = document.getElementById("fe-hintEn").value;
            if (hintEn) field.hintEnglish = hintEn;
            if (this.getSwitch("fe-required")) field.required = true;
            if (this.getSwitch("fe-readonly")) field.readonly = true;
            if (this.getSwitch("fe-beheer")) field.beheer = true;

            // Data
            var dataType = document.getElementById("fe-dataType").value;
            if (dataType) field.dataType = dataType;
            var maxLen = document.getElementById("fe-maxLength").value;
            if (maxLen) field.maxLength = parseInt(maxLen);
            var height = document.getElementById("fe-height").value;
            if (height && field.type === "memo") field.height = parseInt(height);
            var format = document.getElementById("fe-format").value;
            if (format) field.format = format;
            var defVal = document.getElementById("fe-defaultValue").value;
            if (defVal) field.default = defVal;

            // Source (only for source types)
            if (SOURCE_TYPES.indexOf(field.type) >= 0) {
                var srcTable = document.getElementById("fe-sourceTable").value;
                var srcId = document.getElementById("fe-sourceIdField").value;
                var srcDisplay = document.getElementById("fe-sourceDisplayField").value;
                var srcFilter = document.getElementById("fe-sourceFilter").value;

                if (srcTable) {
                    field.source = { table: srcTable };
                    if (srcId) field.source.valueField = srcId;
                    if (srcDisplay) field.source.displayField = srcDisplay;
                    if (srcFilter) field.source.filter = srcFilter;
                }

                var filterByField = document.getElementById("fe-filterByField").value;
                if (filterByField) field.filterByField = filterByField;

                var sql = document.getElementById("fe-sql").value;
                if (sql) field.sql = sql;
                var sqlDb = document.getElementById("fe-sqlDatabase").value;
                if (sqlDb) field.database = sqlDb;
            }

            // Static options for radiogroup
            if (field.type === "radiogroup" || field.type === "dropdown") {
                var opts = this.collectStaticOptions();
                if (opts.length > 0) field.options = opts;
            } else {
                // Options JSON for other types
                var optJson = document.getElementById("fe-optionsJson").value.trim();
                if (optJson) {
                    try { field.options = JSON.parse(optJson); } catch(e) { /* ignore */ }
                }
            }

            // Advanced
            var renderer = document.getElementById("fe-renderer").value;
            if (renderer) field.renderer = renderer;
            var css = document.getElementById("fe-css").value;
            if (css) field.css = css;
            var cls = document.getElementById("fe-class").value;
            if (cls) field.class = cls;

            // Dependencies
            var deps = this.collectDependencies();
            if (deps.length > 0) field.dependencies = deps;

            // Validation
            var vals = this.collectValidation();
            if (vals.length > 0) field.validation = vals;

            // Preserve properties not shown in editor
            if (this.editingFieldIndex !== null) {
                var existing = this.definition.fields[this.editingFieldIndex];
                var preserveKeys = ["sourceFormId", "newOnly", "editableOnNewOnly", "combineWithNext",
                    "allowHtml", "html", "limitedHtml", "maxChars", "passToPost", "action",
                    "noSpamJs", "path", "randomName", "resizeType", "resizeWidth", "resizeHeight",
                    "widthField", "heightField", "xmlSnippet", "dirFileName", "dirTemplate", "width",
                    "image", "file", "useContentBlocks"];
                preserveKeys.forEach(function(k) {
                    if (existing[k] !== undefined && field[k] === undefined) {
                        field[k] = existing[k];
                    }
                });
            }

            // Update definition
            if (this.editingFieldIndex !== null) {
                this.definition.fields[this.editingFieldIndex] = field;
            } else {
                if (!this.definition.fields) this.definition.fields = [];
                this.definition.fields.push(field);
            }

            this.renderFieldsList(this.definition.fields);
            this.markDirty();

            document.getElementById("fieldEditorDialog").close();
        },

        deleteField: async function(index) {
            if (!await libConfirm("Weet je zeker dat je dit veld wilt verwijderen?")) return;
            this.definition.fields.splice(index, 1);
            this.renderFieldsList(this.definition.fields);
            this.markDirty();
        },

        moveField: function(index, direction) {
            var fields = this.definition.fields;
            var newIdx = index + direction;
            if (newIdx < 0 || newIdx >= fields.length) return;
            var temp = fields[index];
            fields[index] = fields[newIdx];
            fields[newIdx] = temp;
            this.renderFieldsList(fields);
            this.markDirty();
        },

        // =====================================================================
        // List Columns
        // =====================================================================

        renderListColumns: function(columns) {
            var container = document.getElementById("listColumnsBody");
            if (!container) return;
            container.innerHTML = "";

            var self = this;
            columns.forEach(function(col, idx) {
                var tr = document.createElement("tr");
                tr.innerHTML =
                    \'<td><input type="text" class="lc-field" value="\' + self.escapeHtml(col.field || "") + \'" style="width:120px"></td>\' +
                    \'<td><input type="text" class="lc-title" value="\' + self.escapeHtml(col.title || "") + \'" style="width:120px"></td>\' +
                    \'<td><input type="text" class="lc-width" value="\' + self.escapeHtml(col.width || "") + \'" style="width:80px"></td>\' +
                    \'<td><select class="lc-type" style="width:90px"><option value="text">text</option><option value="boolean">boolean</option><option value="date">date</option><option value="datetime">datetime</option><option value="number">number</option></select></td>\' +
                    \'<td><select class="lc-align" style="width:80px"><option value="left">left</option><option value="center">center</option><option value="right">right</option></select></td>\' +
                    \'<td><button class="btn btn-small btn-danger" data-action="deleteCol" data-idx="\' + idx + \'" title="Verwijder"><span class="lnr lnr-trash"></span></button></td>\';

                // Set select values
                var typeSelect = tr.querySelector(".lc-type");
                if (typeSelect) typeSelect.value = col.type || "text";
                var alignSelect = tr.querySelector(".lc-align");
                if (alignSelect) alignSelect.value = col.align || "left";

                tr.querySelector("[data-action=deleteCol]").addEventListener("click", function() {
                    self.deleteListColumn(parseInt(this.dataset.idx));
                });

                container.appendChild(tr);
            });
            var libTable = container.closest(\'lib-table\');
            if (libTable && libTable.refresh) libTable.refresh();
        },

        collectListColumns: function() {
            var rows = document.querySelectorAll("#listColumnsBody tr");
            var cols = [];
            rows.forEach(function(tr) {
                var field = tr.querySelector(".lc-field").value.trim();
                if (!field) return;
                var col = { field: field };
                var title = tr.querySelector(".lc-title").value.trim();
                if (title) col.title = title;
                var width = tr.querySelector(".lc-width").value.trim();
                if (width) col.width = width;
                var type = tr.querySelector(".lc-type").value;
                if (type && type !== "text") col.type = type;
                var align = tr.querySelector(".lc-align").value;
                if (align && align !== "left") col.align = align;
                cols.push(col);
            });
            return cols;
        },

        addListColumn: function() {
            var cols = this.collectListColumns();
            cols.push({ field: "", title: "", width: "", type: "text", align: "left" });
            this.renderListColumns(cols);
            this.markDirty();
        },

        deleteListColumn: function(index) {
            var cols = this.collectListColumns();
            cols.splice(index, 1);
            this.renderListColumns(cols);
            this.markDirty();
        },

        // =====================================================================
        // Subforms
        // =====================================================================

        renderSubformsList: function(subforms) {
            var self = this;
            var container = document.getElementById("subformsListBody");
            if (!container) return;
            container.innerHTML = "";


            subforms.forEach(function(sub, idx) {
                var tr = document.createElement("tr");
                tr.innerHTML =
                    \'<td>\' + self.escapeHtml(sub.title || "") + \'</td>\' +
                    \'<td>\' + self.escapeHtml(sub.form || "") + \'</td>\' +
                    \'<td>\' + self.escapeHtml(sub.parentField || "") + \'</td>\' +
                    \'<td class="actions-cell">\' +
                        \'<button class="btn btn-small" data-action="edit" data-idx="\' + idx + \'" title="Bewerk"><span class="lnr lnr-pencil"></span></button>\' +
                        \'<button class="btn btn-small" data-action="up" data-idx="\' + idx + \'" title="Omhoog"\' + (idx === 0 ? " disabled" : "") + \'><span class="lnr lnr-chevron-up"></span></button>\' +
                        \'<button class="btn btn-small" data-action="down" data-idx="\' + idx + \'" title="Omlaag"\' + (idx === subforms.length - 1 ? " disabled" : "") + \'><span class="lnr lnr-chevron-down"></span></button>\' +
                        \'<button class="btn btn-small btn-danger" data-action="delete" data-idx="\' + idx + \'" title="Verwijder"><span class="lnr lnr-trash"></span></button>\' +
                    \'</td>\';
                tr.addEventListener("click", function(e) {
                    var btn = e.target.closest("button[data-action]");
                    if (!btn) return;
                    var action = btn.dataset.action;
                    var i = parseInt(btn.dataset.idx);
                    if (action === "edit") self.openSubformEditor(i);
                    else if (action === "up") self.moveSubform(i, -1);
                    else if (action === "down") self.moveSubform(i, 1);
                    else if (action === "delete") self.deleteSubform(i);
                });
                container.appendChild(tr);
            });
            // Refresh lib-table filters for dynamically populated content
            var libTable = container.closest(\'lib-table\');
            if (libTable && libTable.refresh) libTable.refresh();
        },

        openSubformEditor: function(index) {
            var self = this;
            this.editingSubformIndex = index;
            var sub = index !== null ? (this.definition.subforms[index] || {}) : {};
            var isNew = index === null;

            document.getElementById("sf-title").value = sub.title || "";
            document.getElementById("sf-parentField").value = sub.parentField || sub.linkField || "";

            var dlg = document.getElementById("subformEditorDialog");
            dlg.setAttribute("heading", isNew ? "Nieuw subformulier" : "Subformulier bewerken");

            // Load form list and populate combos
            this.loadFormList().then(function(list) {
                var opts = list.map(function(f) { return { value: f.name, label: f.title + " (" + f.name + ")" }; });

                var formCombo = document.getElementById("sf-form");
                if (formCombo && typeof formCombo.setOptions === "function") {
                    formCombo.setOptions(opts);
                    formCombo.value = sub.form || "";
                }

                dlg.open();
            });
        },

        moveSubform: function(index, direction) {
            var subforms = this.definition.subforms;
            var newIdx = index + direction;
            if (newIdx < 0 || newIdx >= subforms.length) return;
            var temp = subforms[index];
            subforms[index] = subforms[newIdx];
            subforms[newIdx] = temp;
            this.renderSubformsList(subforms);
            this.markDirty();
        },

        saveSubformFromEditor: function() {
            var sub = {};
            sub.title = document.getElementById("sf-title").value.trim();
            if (!sub.title) {
                libToast.error("Titel is verplicht");
                return;
            }
            var formCombo = document.getElementById("sf-form");
            sub.form = (formCombo && formCombo.value) || "";
            var pf = document.getElementById("sf-parentField").value.trim();
            if (pf) sub.parentField = pf;

            if (this.editingSubformIndex !== null) {
                this.definition.subforms[this.editingSubformIndex] = sub;
            } else {
                if (!this.definition.subforms) this.definition.subforms = [];
                this.definition.subforms.push(sub);
            }

            this.renderSubformsList(this.definition.subforms);
            this.markDirty();

            document.getElementById("subformEditorDialog").close();
        },

        deleteSubform: async function(index) {
            if (!await libConfirm("Weet je zeker dat je dit subformulier wilt verwijderen?")) return;
            this.definition.subforms.splice(index, 1);
            this.renderSubformsList(this.definition.subforms);
            this.markDirty();
        },

        // =====================================================================
        // Extra Buttons
        // =====================================================================

        renderExtraButtons: function(buttons) {
            var container = document.getElementById("extraButtonsBody");
            if (!container) return;
            container.innerHTML = "";

            var self = this;
            buttons.forEach(function(btn, idx) {
                var tr = document.createElement("tr");
                tr.innerHTML =
                    \'<td><input type="text" class="eb-icon" value="\' + self.escapeHtml(btn.icon || "") + \'" style="width:150px"></td>\' +
                    \'<td><input type="text" class="eb-title" value="\' + self.escapeHtml(btn.title || "") + \'" style="width:120px"></td>\' +
                    \'<td><input type="text" class="eb-url" value="\' + self.escapeHtml(btn.url || "") + \'" style="width:200px"></td>\' +
                    \'<td><select class="eb-target" style="width:80px"><option value="_self">_self</option><option value="_blank">_blank</option><option value="_parent">_parent</option><option value="_top">_top</option></select></td>\' +
                    \'<td style="text-align:center"><input type="checkbox" class="eb-openInNewWindow"\' + (btn.openInNewWindow ? \' checked\' : \'\') + \'></td>\' +
                    \'<td><button class="btn btn-small btn-danger" data-action="deleteEB" data-idx="\' + idx + \'"><span class="lnr lnr-trash"></span></button></td>\';

                var targetSelect = tr.querySelector(".eb-target");
                if (targetSelect) targetSelect.value = btn.target || "_self";

                tr.querySelector("[data-action=deleteEB]").addEventListener("click", function() {
                    self.deleteExtraButton(parseInt(this.dataset.idx));
                });

                container.appendChild(tr);
            });
            var libTable = container.closest(\'lib-table\');
            if (libTable && libTable.refresh) libTable.refresh();
        },

        collectExtraButtons: function() {
            var rows = document.querySelectorAll("#extraButtonsBody tr");
            var btns = [];
            rows.forEach(function(tr) {
                var url = tr.querySelector(".eb-url").value.trim();
                var title = tr.querySelector(".eb-title").value.trim();
                if (!url && !title) return;
                var btn = {};
                var icon = tr.querySelector(".eb-icon").value.trim();
                if (icon) btn.icon = icon;
                if (title) btn.title = title;
                if (url) btn.url = url;
                var target = tr.querySelector(".eb-target").value;
                if (target && target !== "_self") btn.target = target;
                var openInNewWindow = tr.querySelector(".eb-openInNewWindow");
                if (openInNewWindow && openInNewWindow.checked) btn.openInNewWindow = true;
                btns.push(btn);
            });
            return btns;
        },

        addExtraButton: function() {
            var btns = this.collectExtraButtons();
            if (btns.length >= 5) {
                libToast.warning("Maximaal 5 extra knoppen");
                return;
            }
            btns.push({ icon: "", title: "", url: "", target: "_self" });
            this.renderExtraButtons(btns);
            this.markDirty();
        },

        deleteExtraButton: function(index) {
            var btns = this.collectExtraButtons();
            btns.splice(index, 1);
            this.renderExtraButtons(btns);
            this.markDirty();
        },

        // =====================================================================
        // Tips
        // =====================================================================

        renderTips: function(tips) {
            var container = document.getElementById("tipsBody");
            if (!container) return;
            container.innerHTML = "";

            var self = this;
            (tips || []).forEach(function(tip, idx) {
                var tr = document.createElement("tr");
                tr.innerHTML =
                    \'<td><input type="text" class="tip-id" value="\' + self.escapeHtml(tip.id || "") + \'" style="width:120px"></td>\' +
                    \'<td><input type="text" class="tip-content" value="\' + self.escapeHtml(tip.content || "") + \'" style="width:300px"></td>\' +
                    \'<td><button class="btn btn-small btn-danger" data-action="deleteTip" data-idx="\' + idx + \'"><span class="lnr lnr-trash"></span></button></td>\';

                tr.querySelector("[data-action=deleteTip]").addEventListener("click", function() {
                    self.deleteTip(parseInt(this.dataset.idx));
                });

                container.appendChild(tr);
            });
            var libTable = container.closest(\'lib-table\');
            if (libTable && libTable.refresh) libTable.refresh();
        },

        collectTips: function() {
            var rows = document.querySelectorAll("#tipsBody tr");
            var tips = [];
            rows.forEach(function(tr) {
                var id = tr.querySelector(".tip-id").value.trim();
                var content = tr.querySelector(".tip-content").value.trim();
                if (!id && !content) return;
                var tip = {};
                if (id) tip.id = id;
                if (content) tip.content = content;
                tips.push(tip);
            });
            return tips;
        },

        addTip: function() {
            var tips = this.collectTips();
            tips.push({ id: "", content: "" });
            this.renderTips(tips);
            this.markDirty();
        },

        deleteTip: function(index) {
            var tips = this.collectTips();
            tips.splice(index, 1);
            this.renderTips(tips);
            this.markDirty();
        },

        // =====================================================================
        // Static Options (radiogroup/dropdown)
        // =====================================================================

        renderStaticOptions: function(options) {
            var container = document.getElementById("staticOptionsBody");
            if (!container) return;
            container.innerHTML = "";

            var self = this;
            if (!Array.isArray(options)) return;

            options.forEach(function(opt, idx) {
                var tr = document.createElement("tr");
                tr.innerHTML =
                    \'<td><input type="text" class="so-value" value="\' + self.escapeHtml(opt.value !== undefined ? String(opt.value) : "") + \'" style="width:100px"></td>\' +
                    \'<td><input type="text" class="so-text" value="\' + self.escapeHtml(opt.text || opt.label || "") + \'" style="width:200px"></td>\' +
                    \'<td><button class="btn btn-small btn-danger" data-action="deleteSO"><span class="lnr lnr-trash"></span></button></td>\';

                tr.querySelector("[data-action=deleteSO]").addEventListener("click", function() {
                    tr.remove();
                });

                container.appendChild(tr);
            });
        },

        collectStaticOptions: function() {
            var rows = document.querySelectorAll("#staticOptionsBody tr");
            var opts = [];
            rows.forEach(function(tr) {
                var value = tr.querySelector(".so-value").value.trim();
                var text = tr.querySelector(".so-text").value.trim();
                if (value || text) {
                    opts.push({ value: value, text: text });
                }
            });
            return opts;
        },

        // =====================================================================
        // Dependencies
        // =====================================================================

        renderDependencies: function(deps) {
            var container = document.getElementById("depsBody");
            if (!container) return;
            container.innerHTML = "";

            var self = this;
            deps.forEach(function(dep, idx) {
                var tr = document.createElement("tr");
                tr.innerHTML =
                    \'<td><input type="text" class="dep-field" value="\' + self.escapeHtml(dep.field || "") + \'" style="width:100px"></td>\' +
                    \'<td><select class="dep-condition" style="width:80px"><option value="=">=</option><option value="!=">!=</option><option value=">">></option><option value="<"><</option><option value="contains">contains</option><option value="empty">empty</option><option value="notEmpty">notEmpty</option></select></td>\' +
                    \'<td><input type="text" class="dep-value" value="\' + self.escapeHtml(dep.value !== undefined ? String(dep.value) : "") + \'" style="width:100px"></td>\' +
                    \'<td><button class="btn btn-small btn-danger" data-action="deleteDep"><span class="lnr lnr-trash"></span></button></td>\';

                var condSelect = tr.querySelector(".dep-condition");
                if (condSelect) condSelect.value = dep.condition || "=";

                tr.querySelector("[data-action=deleteDep]").addEventListener("click", function() {
                    tr.remove();
                });

                container.appendChild(tr);
            });
        },

        collectDependencies: function() {
            var rows = document.querySelectorAll("#depsBody tr");
            var deps = [];
            rows.forEach(function(tr) {
                var field = tr.querySelector(".dep-field").value.trim();
                if (!field) return;
                deps.push({
                    field: field,
                    condition: tr.querySelector(".dep-condition").value,
                    value: tr.querySelector(".dep-value").value
                });
            });
            return deps;
        },

        // =====================================================================
        // Validation
        // =====================================================================

        renderValidation: function(vals) {
            var container = document.getElementById("validationBody");
            if (!container) return;
            container.innerHTML = "";

            var self = this;
            vals.forEach(function(v) {
                var val = typeof v === "string" ? v : (v.rule || "");
                var tr = document.createElement("tr");
                tr.innerHTML =
                    \'<td><input type="text" class="val-rule" value="\' + self.escapeHtml(val) + \'" style="width:200px"></td>\' +
                    \'<td><button class="btn btn-small btn-danger" data-action="deleteVal"><span class="lnr lnr-trash"></span></button></td>\';
                tr.querySelector("[data-action=deleteVal]").addEventListener("click", function() {
                    tr.remove();
                });
                container.appendChild(tr);
            });
        },

        collectValidation: function() {
            var rows = document.querySelectorAll("#validationBody tr");
            var vals = [];
            rows.forEach(function(tr) {
                var rule = tr.querySelector(".val-rule").value.trim();
                if (rule) vals.push(rule);
            });
            return vals;
        },

        // =====================================================================
        // Load tables for database
        // =====================================================================

        loadTables: function(database, selectedTable) {
            var tableCombo = document.getElementById("gs-table");
            if (!tableCombo || typeof tableCombo.setOptions !== "function") return;

            // Clear any previous value before replacing options to avoid "not found" error
            tableCombo.value = "";
            // Set a temporary option while loading
            if (selectedTable) {
                tableCombo.setOptions([{ value: selectedTable, label: selectedTable }]);
                tableCombo.value = selectedTable;
            }

            if (!database || database === "json") return;

            fetch("tools_formedit.php?action=getTables&database=" + encodeURIComponent(database))
                .then(function(r) { return r.json(); })
                .then(function(tables) {
                    if (tables.error || !Array.isArray(tables)) return;
                    var options = [{ value: "", label: "(geen)" }];
                    tables.forEach(function(t) {
                        options.push({ value: t, label: t });
                    });
                    // Clear value before replacing options to avoid spurious "not found" error
                    tableCombo.value = "";
                    tableCombo.setOptions(options);
                    // Case-insensitive match: ODBC may return different casing than JSON definition
                    if (selectedTable) {
                        var lc = selectedTable.toLowerCase();
                        var match = tables.find(function(t) { return t.toLowerCase() === lc; });
                        tableCombo.value = match || selectedTable;
                    }
                })
                .catch(function(e) { cmaLog.error("loadTables:", e); });
        },

        // =====================================================================
        // JSON Preview
        // =====================================================================

        showJsonPreview: function() {
            var def = this.collectDefinition();
            var json = JSON.stringify(def, null, 2);

            var dlg = document.getElementById("jsonPreviewDialog");
            dlg.setAttribute("heading", "JSON definitie " + (this.currentForm || ""));
            document.getElementById("jsonPreviewArea").value = json;
            dlg.open();
        },

        // =====================================================================
        // Form Wizard (new form)
        // =====================================================================

        openFormWizard: function() {
            var self = this;
            var dlg = document.getElementById("formwizDialog");
            var iframe = document.getElementById("formwizIframe");
            iframe.src = "tools_formwiz.php";
            dlg.open();

            // Listen for message from formwiz iframe when a form is saved
            var messageHandler = function(e) {
                if (e.data && e.data.type === "formwiz-saved" && e.data.formName) {
                    var newFormName = e.data.formName;
                    window.removeEventListener("message", messageHandler);
                    dlg.close();
                    // Reload tree and then select + load the newly created form
                    self._initialFormName = newFormName;
                    self.loadTree();
                    setTimeout(function() {
                        self.loadForm(newFormName);
                        var tree = document.getElementById("formTree");
                        if (tree && typeof tree.selectById === "function") {
                            tree.selectById(newFormName);
                        }
                    }, 500);
                }
            };
            window.addEventListener("message", messageHandler);

            // Clean up listener if dialog is closed manually
            var closeHandler = function() {
                window.removeEventListener("message", messageHandler);
                self.loadTree();
                dlg.removeEventListener("close", closeHandler);
            };
            dlg.addEventListener("close", closeHandler);
        },

        // =====================================================================
        // Utilities
        // =====================================================================

        markDirty: function() {
            this.isDirty = true;
            this.updateDirtyIndicator();
        },

        updateDirtyIndicator: function() {
            var saveBtn = document.getElementById("saveBtn");
            if (saveBtn) {
                var link = saveBtn.querySelector("a");
                if (this.isDirty) {
                    saveBtn.classList.remove("disabled");
                    if (link) link.style.color = "var(--color-danger, #e74c3c)";
                } else {
                    saveBtn.classList.add("disabled");
                    if (link) link.style.color = "";
                }
            }
        },

        confirmUnsaved: function() {
            if (!this.isDirty) return Promise.resolve(true);
            return libConfirm("Er zijn niet-opgeslagen wijzigingen. Wil je doorgaan?");
        },

        setSwitch: function(id, checked) {
            var sw = document.getElementById(id);
            if (sw) sw.checked = checked;
        },

        getSwitch: function(id) {
            var sw = document.getElementById(id);
            return sw ? sw.checked : false;
        },

        escapeHtml: function(text) {
            var div = document.createElement("div");
            div.textContent = text;
            return div.innerHTML;
        }
    };

    document.addEventListener("DOMContentLoaded", function() { FormEditor.init(); });
})();
</script>

<style>
/* Split layout */
body.fe-layout {
    display: flex;
    flex-direction: row;
    height: 100vh;
    overflow: hidden;
    margin: 0;
    padding: 0;
}
body.fe-layout #leftlist {
    flex: 0 0 280px;
    min-width: 150px;
    max-width: 500px;
    height: 100%;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    background: var(--bg-surface);
    border-right: 1px solid var(--border-color);
}
body.fe-layout #leftlist .toolbar-left {
    flex: 1;
}
body.fe-layout #leftlist .fe-toolbar-search {
    flex: 1;
    min-width: 0;
    margin: 0 4px;
}
body.fe-layout #leftlist .tree-area {
    flex: 1;
    overflow: auto;
    padding: 8px;
}
body.fe-layout #leftlist .table-area {
    flex: 1;
    overflow: auto;
    padding: 4px;
}
body.fe-layout #leftlist .table-area table {
    font-size: var(--font-size-sm);
    width: 100%;
}
body.fe-layout #leftlist .table-area tbody tr {
    cursor: pointer;
}
body.fe-layout #leftlist .table-area tbody tr:hover td {
    background: var(--bg-hover);
}
body.fe-layout #leftlist .table-area tbody tr.selected td {
    background: var(--selected-bg, #e3f2fd);
}
/* Active view toggle button */
#btn_viewTree.fe-view-active a,
#btn_viewTable.fe-view-active a {
    color: var(--accent-color, #077ab2) !important;
}
/* Hide expand/collapse in table mode */
body.fe-view-table #btn_expand,
body.fe-view-table #btn_collapse {
    display: none;
}
body.fe-layout cma-fold {
    flex: 0 0 8px;
    height: 100%;
}
body.fe-layout #rightPanel {
    flex: 1;
    height: 100%;
    overflow: auto;
    display: flex;
    flex-direction: column;
}
body.fe-layout #rightPanel #editorScroll {
    flex: 1;
    overflow: auto;
}

/* Section content (used in both main panel and dialogs) */
.fe-section,
.dlg-form {
    padding: 10px;
}
.fe-section {
    border-left: 1px solid var(--border-color);
    border-right: 1px solid var(--border-color);
}
.fe-section .form-row,
.dlg-form .form-row {
    display: flex;
    gap: 16px;
    margin-bottom: 10px;
    flex-wrap: wrap;
}
.fe-section .form-row > div,
.dlg-form .form-row > div {
    display: flex;
    flex-direction: column;
    gap: 3px;
}
.fe-section label,
.dlg-form label {
    font-size: var(--font-size-sm);
    padding-left: 9px;
    color: #aaa;
    font-weight: 100;
}
.fe-section input[type="text"],
.fe-section select,
.fe-section textarea,
.dlg-form input[type="text"],
.dlg-form select,
.dlg-form textarea {
    padding: 5px 8px;
    border-radius: 3px;
    font-size: var(--font-size);
}
.fe-section textarea,
.dlg-form textarea {
    font-family: "Consolas", "Monaco", monospace;
    font-size: var(--font-size-sm);
    resize: vertical;
}

/* Switch row */
.switch-row {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
    margin-bottom: 10px;
}
.switch-item {
    display: flex;
    align-items: center;
    gap: 6px;
}
.switch-item label {
    font-weight: normal;
}

/* Toolbar title within form editor sections */
.fe-section .toolbar-title {
    font-size: var(--font-size);
    font-weight: 200;
    padding-left: 4px;
    color: var(--color-header, #003366);
}

/* Actions cell */
.actions-cell {
    white-space: nowrap;
}
.actions-cell .btn-small {
    padding: 2px 6px;
    min-width: auto;
    font-size: var(--font-size-xs);
}

/* Groupbox end separator for div-based sections */
div.groupbox-end {
    height: 8px;
    border-top: 1px solid var(--border-color);
    background: transparent;
}

/* Field editor dialog body */
#fieldEditorDialog::part(body) {
    padding-top: 0;
    padding-left: 0;
    padding-right: 0;
    min-height: 300px;
}
#fieldEditorDialog .dlg-form {
    padding: 20px;
}

/* Form wizard dialog body */
#formwizDialog::part(body) {
    padding: 0;
}

/* JSON preview */
#jsonPreviewArea {
    width: 100%;
    height: 400px;
    font-family: "Consolas", "Monaco", monospace;
    font-size: var(--font-size-sm);
    padding: 10px;
}

/* Btn danger */
.btn-danger {
    color: var(--color-error, #e74c3c);
}
.btn-danger:hover {
    background: var(--color-error, #e74c3c);
    color: #fff;
}
</style>';

cma_html_header('CMA - Formulierdefinities', $extraScript);
echo '<body class="fe-layout">';

// ========== Left panel: Tree ==========
echo '<div id="leftlist">';
ToolbarHelper::start(false);
ToolbarHelper::treeButtons();
ToolbarHelper::separator();
// View toggle buttons: tree (default) and table
ToolbarHelper::button('javascript:feSetView("tree")', 'lnr-grouped', true, '', 'Boomweergave', 'btn_viewTree');
ToolbarHelper::button('javascript:feSetView("table")', 'lnr-table', true, '', 'Tabelweergave', 'btn_viewTable');
echo '<lib-search-input id="feTreeSearch" placeholder="Zoeken..." class="fe-toolbar-search"></lib-search-input>';
ToolbarHelper::end(false);
echo '<div class="tree-area">';
echo '<cma-tree id="formTree" storage-key="tree_formedit"></cma-tree>';
echo '</div>';
echo '<div class="table-area" style="display:none">';
echo '<lib-table id="formTableView">';
echo '<table>';
echo '<thead><tr><th>Naam</th><th>Titel</th><th>Tabel</th><th>Sub</th></tr></thead>';
echo '<tbody id="formTableBody"></tbody>';
echo '</table>';
echo '</lib-table>';
echo '</div>';
echo '</div>';

// ========== Fold divider ==========
echo '<cma-fold orientation="vertical" target="#leftlist" min-size="150" max-size="500" storage-key="formedit_fold"></cma-fold>';

// ========== Right panel: Editor ==========
echo '<div id="rightPanel">';

// Toolbar (standard CMA toolbar like forms.php)
ToolbarHelper::start(false);
ToolbarHelper::button('javascript:void(0)', 'lnr-file-add', true, 'Toevoegen', 'Maak een nieuw formulier via de wizard', 'newBtn');
ToolbarHelper::button('javascript:void(0)', 'lnr-save', true, 'Opslaan', 'Formulier opslaan', 'saveBtn');
ToolbarHelper::separator();
ToolbarHelper::button('javascript:void(0)', 'lnr-code', false, 'JSON', 'JSON bekijken', 'jsonBtn');
ToolbarHelper::startRight();
echo '<span id="formLoadSpinner" style="display:none; color: var(--border-hover, #077ab2);"><span class="lnr lnr-sync spin-animation" data-tooltip="Laden..."></span></span>';
ToolbarHelper::end(false);

echo '<lib-message id="fe-save-notice" type="info" hidden></lib-message>';

echo '<div id="editorScroll">';

// Welcome message (shown when no form selected)
echo '<div id="welcomeMsg" style="padding: 40px 20px; text-align: center; color: var(--text-muted)">';
echo '<p>Selecteer een formulier in de boomstructuur om te bewerken.</p>';
echo '</div>';

// Editor area (hidden until form loaded)
echo '<div id="editorArea" style="display:none; padding: 8px">';

// ========== Section: General Settings ==========
echo '<cma-groupbox group-id="1" form-id="0" data-section="general" caption="Algemene instellingen"></cma-groupbox>';
echo '<div data-group-row="1" class="fe-section">';
echo '<div class="form-row">';
echo '<div><label>Naam (niet wijzigbaar)</label><input type="text" id="gs-name" readonly required style="width:200px; background:var(--bg-surface)"></div>';
echo '<div><label>Omschrijving (meervoud)</label><input type="text" id="gs-title" style="width:200px"></div>';
echo '<div><label>Omschrijving (enkelvoud)</label><input type="text" id="gs-titleSingular" style="width:200px"></div>';
echo '</div>';
echo '<div class="form-row">';
echo '<div><label>Database</label><lib-combo id="gs-database" name="gs-database" style="width:150px"></lib-combo></div>';
echo '<div style="flex:1"><label>Tabel</label><lib-combo id="gs-table" name="gs-table" style="width:100%" required searchable></lib-combo></div>';
echo '</div>';
echo '<div class="form-row">';
echo '<div style="flex:1"><label>Parent formulier</label><lib-combo id="gs-parentForm" name="gs-parentForm" style="width:100%" searchable placeholder="(geen - hoofdformulier)"></lib-combo></div>';
echo '</div>';
echo '</div>';
echo '<div class="groupbox-end" data-group-row="1"></div>';

// ========== Section: Knoppen ==========
echo '<cma-groupbox group-id="6" form-id="0" caption="Knoppen"></cma-groupbox>';
echo '<div data-group-row="6" class="fe-section">';
echo '<div class="switch-row">';
echo '<div class="switch-item"><lib-switch id="gs-allowAdd" size="small"></lib-switch><label>Toevoegen</label></div>';
echo '<div class="switch-item"><lib-switch id="gs-allowDelete" size="small"></lib-switch><label>Verwijderen</label></div>';
echo '<div class="switch-item"><lib-switch id="gs-allowCopy" size="small"></lib-switch><label>Kopiëren</label></div>';
echo '</div>';
echo '<div class="form-row">';
echo '<div><label>Preview URL <span style="color:#888;font-weight:normal">(altijd in nieuw venster)</span></label><input type="text" id="adv-previewUrl" style="width:400px"></div>';
echo '</div>';

// Extra buttons sub-toolbar
echo '<cma-toolbar variant="subform">';
echo '<left><span class="toolbar-title">Extra knoppen (max 5)</span></left>';
echo '<right><span class="tb-btn" id="addExtraBtn"><a href="javascript:void(0)" title="Toevoegen"><span class="lnr lnr-file-add"></span><span class="tb-btn-text">Toevoegen</span></a></span></right>';
echo '</cma-toolbar>';
echo '<lib-table export="n"><table class="filtering">';
echo '<thead><tr><th>Icoon</th><th>Titel</th><th>URL</th><th>Target</th><th>Nieuw venster</th><th data-no-sort data-no-filter>Acties</th></tr></thead>';
echo '<tbody id="extraButtonsBody"></tbody>';
echo '</table></lib-table>';
echo '</div>';
echo '<div class="groupbox-end" data-group-row="6"></div>';

// ========== Section: List Settings ==========
echo '<cma-groupbox group-id="2" form-id="0" data-section="list" caption="Lijst instellingen"></cma-groupbox>';
echo '<div data-group-row="2" class="fe-section">';
echo '<div class="form-row">';
echo '<div style="flex:1"><label>Lijst query</label><textarea id="ls-listQuery" rows="6" style="width:100%"></textarea></div>';
echo '</div>';
echo '<div class="form-row">';
echo '<div><label>ID veld</label><input type="text" id="gs-idField" style="width:120px"></div>';
echo '<div><label>Detail veld</label><input type="text" id="ls-detailField" style="width:150px"></div>';
echo '<div><label>Groep veld 1</label><input type="text" id="ls-groupField1" style="width:150px"></div>';
echo '<div><label>Groep veld 2</label><input type="text" id="ls-groupField2" style="width:150px"></div>';
echo '<div><label>Groep veld 3</label><input type="text" id="ls-groupField3" style="width:150px"></div>';
echo '</div>';

// List columns sub-toolbar
echo '<cma-toolbar variant="subform">';
echo '<left><span class="toolbar-title">Lijst kolommen</span></left>';
echo '<right><span class="tb-btn" id="addListColBtn"><a href="javascript:void(0)" title="Toevoegen"><span class="lnr lnr-file-add"></span><span class="tb-btn-text">Toevoegen</span></a></span></right>';
echo '</cma-toolbar>';
echo '<lib-table export="n"><table class="filtering">';
echo '<thead><tr><th>Veld</th><th>Titel</th><th>Breedte</th><th>Type</th><th>Uitlijning</th><th data-no-sort data-no-filter>Acties</th></tr></thead>';
echo '<tbody id="listColumnsBody"></tbody>';
echo '</table></lib-table>';
echo '</div>';
echo '<div class="groupbox-end" data-group-row="2"></div>';

// ========== Section: Fields ==========
echo '<cma-groupbox group-id="3" form-id="0" data-section="fields" caption="Velden"></cma-groupbox>';
echo '<div data-group-row="3" class="fe-section">';
echo '<cma-toolbar variant="subform">';
echo '<left><span class="tb-btn" id="addFieldBtn"><a href="javascript:void(0)" title="Veld toevoegen"><span class="lnr lnr-file-add"></span><span class="tb-btn-text">Toevoegen</span></a></span></left>';
echo '</cma-toolbar>';
echo '<lib-table export="n"><table class="filtering">';
echo '<thead><tr><th>Naam</th><th>Type</th><th>Label</th><th style="width:160px" data-no-sort data-no-filter>Acties</th></tr></thead>';
echo '<tbody id="fieldsListBody"></tbody>';
echo '</table></lib-table>';
echo '</div>';
echo '<div class="groupbox-end" data-group-row="3"></div>';

// ========== Section: Subforms ==========
echo '<cma-groupbox group-id="4" form-id="0" data-section="subforms" caption="Subformulieren"></cma-groupbox>';
echo '<div data-group-row="4" class="fe-section">';
echo '<cma-toolbar variant="subform">';
echo '<left><span class="tb-btn" id="addSubformBtn"><a href="javascript:void(0)" title="Subformulier toevoegen"><span class="lnr lnr-file-add"></span><span class="tb-btn-text">Toevoegen</span></a></span></left>';
echo '</cma-toolbar>';
echo '<lib-table export="n"><table class="filtering">';
echo '<thead><tr><th>Titel</th><th>Formulier</th><th>Parent veld</th><th style="width:160px" data-no-sort data-no-filter>Acties</th></tr></thead>';
echo '<tbody id="subformsListBody"></tbody>';
echo '</table></lib-table>';
echo '</div>';
echo '<div class="groupbox-end" data-group-row="4"></div>';

// ========== Section: Advanced ==========
echo '<cma-groupbox group-id="5" form-id="0" data-section="advanced" caption="Geavanceerd" collapsed></cma-groupbox>';
echo '<div id="section-advanced" data-group-row="5" class="fe-section">';
echo '<div class="switch-row">';
echo '<div class="switch-item"><lib-switch id="gs-securityByUser" size="small"></lib-switch><label>Beveiliging per gebruiker</label></div>';
echo '<div class="switch-item"><lib-switch id="gs-storeLastModified" size="small"></lib-switch><label>Laatste wijziging opslaan</label></div>';
echo '</div>';
echo '<div class="form-row">';
echo '<div><label>Snelzoek velden</label><input type="text" id="gs-quickSearchFields" style="width:300px" placeholder="veld1,veld2,veld3"></div>';
echo '<div><label>Actief veld</label><input type="text" id="gs-activeField" style="width:150px" placeholder="bijv. bActief"></div>';
echo '</div>';
echo '<div class="form-row">';
echo '<div><label>After post URL</label><input type="text" id="adv-afterPostUrl" style="width:400px"></div>';
echo '</div>';
echo '<div class="form-row">';
echo '<div><label>Afdwingen filtering op veld</label><input type="text" id="adv-filterField" style="width:200px"></div>';
echo '<div><label>Filter omschrijving</label><input type="text" id="adv-filterDescr" style="width:200px"></div>';
echo '</div>';
echo '<div class="form-row">';
echo '<div style="flex:1"><label>Filter SQL</label><textarea id="adv-filterSql" rows="3" style="width:100%"></textarea></div>';
echo '</div>';
echo '<div class="form-row">';
echo '<div style="flex:1"><label>onLoad JavaScript</label><textarea id="adv-onLoadJs" rows="3" style="width:100%"></textarea></div>';
echo '</div>';

// Tips sub-toolbar
echo '<cma-toolbar variant="subform">';
echo '<left><span class="toolbar-title">Tips</span></left>';
echo '<right><span class="tb-btn" id="addTipBtn"><a href="javascript:void(0)" title="Toevoegen"><span class="lnr lnr-file-add"></span><span class="tb-btn-text">Toevoegen</span></a></span></right>';
echo '</cma-toolbar>';
echo '<lib-table export="n"><table class="filtering">';
echo '<thead><tr><th>ID</th><th>Inhoud</th><th data-no-sort data-no-filter>Acties</th></tr></thead>';
echo '<tbody id="tipsBody"></tbody>';
echo '</table></lib-table>';

echo '</div>'; // section-advanced
echo '<div class="groupbox-end" data-group-row="5"></div>';

echo '</div>'; // editorArea

// ========== Field Editor Dialog ==========
echo '<lib-dialog id="fieldEditorDialog" heading="Veld bewerken" size="large" maximizable>';
echo '<cma-tabs id="fieldEditorTabs" tabs=\'["Basis","Data","Bron","Geavanceerd"]\'>';

// Tab: Basis
echo '<div slot="tab-0" data-tab-id="basis">';
echo '<div class="dlg-form">';
echo '<div class="form-row">';
echo '<div><label>Naam</label><input type="text" id="fe-name" required style="width:200px"></div>';
echo '<div><label>Type</label><select id="fe-type" required style="width:150px">';
foreach (['textbox','memo','checkbox','combobox','date','time','datetime','dropdown','email','password','file','image','url','checklist','checklisttree','checklistinline','sortlist','label','groupseparator','readonly','radiogroup','custom','userlist','xmlstore','directory'] as $ft) {
    echo '<option value="' . $ft . '">' . $ft . '</option>';
}
echo '</select></div>';
echo '</div>';
echo '<div class="form-row">';
echo '<div><label>Label</label><input type="text" id="fe-caption" style="width:250px"></div>';
echo '<div><label>Label (Engels)</label><input type="text" id="fe-captionEn" style="width:250px"></div>';
echo '</div>';
echo '<div class="form-row">';
echo '<div><label>Hint</label><input type="text" id="fe-hint" style="width:250px"></div>';
echo '<div><label>Hint (Engels)</label><input type="text" id="fe-hintEn" style="width:250px"></div>';
echo '</div>';
echo '<div class="switch-row">';
echo '<div class="switch-item"><lib-switch id="fe-required" size="small"></lib-switch><label>Verplicht</label></div>';
echo '<div class="switch-item"><lib-switch id="fe-readonly" size="small"></lib-switch><label>Alleen lezen</label></div>';
echo '<div class="switch-item"><lib-switch id="fe-beheer" size="small"></lib-switch><label>Beheer</label></div>';
echo '</div>';
echo '</div>';
echo '</div>';

// Tab: Data
echo '<div slot="tab-1" data-tab-id="data">';
echo '<div class="dlg-form">';
echo '<div class="form-row">';
echo '<div><label>Data type</label><input type="text" id="fe-dataType" style="width:150px" placeholder="text, boolean, number, ..."></div>';
echo '<div><label>Max lengte</label><input type="text" id="fe-maxLength" style="width:100px"></div>';
echo '</div>';
echo '<div class="form-row" id="fe-height-row">';
echo '<div><label>Hoogte (regels)</label><input type="text" id="fe-height" style="width:100px"></div>';
echo '</div>';
echo '<div class="form-row">';
echo '<div><label>Formaat</label><input type="text" id="fe-format" style="width:150px" placeholder="date, datetime, currency, ..."></div>';
echo '<div><label>Standaardwaarde</label><input type="text" id="fe-defaultValue" style="width:200px"></div>';
echo '</div>';
echo '</div>';
echo '</div>';

// Tab: Bron (source)
echo '<div slot="tab-2" data-tab-id="source">';
echo '<div class="dlg-form">';
echo '<h4 style="margin:0 0 8px">Tabel bron</h4>';
echo '<div class="form-row">';
echo '<div><label>Brontabel</label><input type="text" id="fe-sourceTable" style="width:200px"></div>';
echo '<div><label>ID veld</label><input type="text" id="fe-sourceIdField" style="width:150px"></div>';
echo '<div><label>Weergave veld</label><input type="text" id="fe-sourceDisplayField" style="width:150px"></div>';
echo '</div>';
echo '<div class="form-row">';
echo '<div><label>Filter</label><input type="text" id="fe-sourceFilter" style="width:250px"></div>';
echo '<div><label>Filter op veld</label><input type="text" id="fe-filterByField" style="width:150px" placeholder="bijv. fkOpleiding" title="Filtert combo-opties op de waarde van een ander veld op hetzelfde formulier. De brontabel moet een kolom hebben met deze naam."></div>';
echo '</div>';
echo '<h4 style="margin:12px 0 8px">OF: SQL query</h4>';
echo '<div class="form-row">';
echo '<div style="flex:1"><label>SQL</label><textarea id="fe-sql" rows="3" style="width:100%"></textarea></div>';
echo '</div>';
echo '<div class="form-row">';
echo '<div><label>Database</label><input type="text" id="fe-sqlDatabase" style="width:150px"></div>';
echo '</div>';
echo '<h4 style="margin:12px 0 8px">Statische opties (radiogroup/dropdown)</h4>';
echo '<lib-table export="n"><table class="filtering"><thead><tr><th>Waarde</th><th>Tekst</th><th></th></tr></thead><tbody id="staticOptionsBody"></tbody></table></lib-table>';
echo '<button class="btn btn-small" style="margin-top:5px" onclick="var b=document.getElementById(\'staticOptionsBody\');var r=document.createElement(\'tr\');r.innerHTML=\'<td><input type=text class=so-value style=width:100px></td><td><input type=text class=so-text style=width:200px></td><td><button class=\\\'btn btn-small btn-danger\\\' onclick=\\\'this.closest(\\\\\\\'tr\\\\\\\').remove()\\\'><span class=\\\'lnr lnr-trash\\\'></span></button></td>\';b.appendChild(r)"><span class="lnr lnr-file-add"></span> Optie toevoegen</button>';
echo '</div>';
echo '</div>';

// Tab: Geavanceerd
echo '<div slot="tab-3" data-tab-id="adv">';
echo '<div class="dlg-form">';
echo '<div class="form-row">';
echo '<div><label>Renderer</label><input type="text" id="fe-renderer" style="width:200px"></div>';
echo '<div><label>CSS</label><input type="text" id="fe-css" style="width:200px"></div>';
echo '<div><label>Class</label><input type="text" id="fe-class" style="width:200px"></div>';
echo '</div>';
echo '<div class="form-row">';
echo '<div style="flex:1"><label>Options JSON (object)</label><textarea id="fe-optionsJson" rows="3" style="width:100%"></textarea></div>';
echo '</div>';

// Dependencies
echo '<h4 style="margin:12px 0 8px">Afhankelijkheden</h4>';
echo '<lib-table export="n"><table class="filtering"><thead><tr><th>Veld</th><th>Conditie</th><th>Waarde</th><th></th></tr></thead><tbody id="depsBody"></tbody></table></lib-table>';
echo '<button class="btn btn-small" style="margin-top:5px" onclick="var b=document.getElementById(\'depsBody\');var r=document.createElement(\'tr\');r.innerHTML=\'<td><input type=text class=dep-field style=width:100px></td><td><select class=dep-condition style=width:80px><option value==>=</option><option value=!=">!=</option><option value=>>>&gt;</option><option value=<>&lt;</option><option value=contains>contains</option><option value=empty>empty</option><option value=notEmpty>notEmpty</option></select></td><td><input type=text class=dep-value style=width:100px></td><td><button class=\\\'btn btn-small btn-danger\\\' onclick=\\\'this.closest(\\\\\\\'tr\\\\\\\').remove()\\\'><span class=\\\'lnr lnr-trash\\\'></span></button></td>\';b.appendChild(r)"><span class="lnr lnr-file-add"></span> Afhankelijkheid toevoegen</button>';

// Validation
echo '<h4 style="margin:12px 0 8px">Validatie</h4>';
echo '<lib-table export="n"><table class="filtering"><thead><tr><th>Regel</th><th></th></tr></thead><tbody id="validationBody"></tbody></table></lib-table>';
echo '<button class="btn btn-small" style="margin-top:5px" onclick="var b=document.getElementById(\'validationBody\');var r=document.createElement(\'tr\');r.innerHTML=\'<td><input type=text class=val-rule style=width:200px></td><td><button class=\\\'btn btn-small btn-danger\\\' onclick=\\\'this.closest(\\\\\\\'tr\\\\\\\').remove()\\\'><span class=\\\'lnr lnr-trash\\\'></span></button></td>\';b.appendChild(r)"><span class="lnr lnr-file-add"></span> Regel toevoegen</button>';

echo '</div>';
echo '</div>';

echo '</cma-tabs>';
echo '<div slot="footer">';
echo '<button id="saveFieldBtn" class="btn btn-primary">Opslaan</button>';
echo '<button class="btn btn-cancel" onclick="document.getElementById(\'fieldEditorDialog\').close()">Annuleren</button>';
echo '</div>';
echo '</lib-dialog>';

// ========== Subform Editor Dialog ==========
echo '<lib-dialog id="subformEditorDialog" heading="Subformulier bewerken" size="medium">';
echo '<div class="dlg-form">';
echo '<div class="form-row">';
echo '<div><label>Formulier</label><lib-combo id="sf-form" placeholder="Selecteer formulier..." style="width:350px"></lib-combo></div>';
echo '</div>';
echo '<div class="form-row">';
echo '<div><label>Titel</label><input type="text" id="sf-title" required style="width:350px"></div>';
echo '</div>';
echo '<div class="form-row">';
echo '<div><label>Parent veld</label><input type="text" id="sf-parentField" style="width:350px"></div>';
echo '</div>';
echo '</div>';
echo '<div slot="footer">';
echo '<button id="saveSubformBtn" class="btn btn-primary">Opslaan</button>';
echo '<button class="btn btn-cancel" onclick="document.getElementById(\'subformEditorDialog\').close()">Annuleren</button>';
echo '</div>';
echo '</lib-dialog>';

// ========== JSON Preview Dialog ==========
echo '<lib-dialog id="jsonPreviewDialog" heading="JSON preview" size="large" maximizable>';
echo '<textarea id="jsonPreviewArea" readonly></textarea>';
echo '<div slot="footer">';
echo '<button class="btn" id="jsonCopyBtn" onclick="navigator.clipboard.writeText(document.getElementById(\'jsonPreviewArea\').value);this.textContent=\'Gekopieerd!\';setTimeout(function(){document.getElementById(\'jsonCopyBtn\').textContent=\'Kopieer naar klembord\'},2000)">Kopieer naar klembord</button>';
echo '<button class="btn btn-primary" id="jsonDownloadBtn" onclick="(function(){var json=document.getElementById(\'jsonPreviewArea\').value;var name=(FormEditor.currentForm||\'form\')+\'.json\';var blob=new Blob([json],{type:\'application/json\'});var a=document.createElement(\'a\');a.href=URL.createObjectURL(blob);a.download=name;a.click();URL.revokeObjectURL(a.href)})()"><span class="lnr lnr-download"></span> Downloaden</button>';
echo '</div>';
echo '</lib-dialog>';

// ========== Form Wizard Dialog ==========
echo '<lib-dialog id="formwizDialog" heading="Nieuw formulier" size="large" maximizable>';
echo '<iframe id="formwizIframe" src="about:blank" style="width:100%;height:500px;border:none"></iframe>';
echo '</lib-dialog>';

echo '</div>'; // editorScroll
echo '</div>'; // rightPanel
echo '</body></html>';
