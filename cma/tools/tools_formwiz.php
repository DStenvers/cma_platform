<?php
/**
 * CMA Form Wizard - Generate JSON form definitions from database tables
 *
 * This tool introspects database tables and generates JSON form definitions
 * that can be used by the CMA form system.
 */
use App\Library\Application;
use App\Library\Cache;
use App\Library\Database;
use App\Library\Error;
use App\Library\Request;
use App\Library\Response;
use App\Library\SQL;
use Cma\CmaRepository;
use Cma\ConfigLoader;
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

// Handle AJAX requests
if (Request::query('action') === 'getColumns') {
    header('Content-Type: application/json');
    echo json_encode(getTableColumns(Request::query('database', ''), Request::query('table', '')));
    exit;
}

if (Request::query('action') === 'getTables') {
    header('Content-Type: application/json');
    echo json_encode(getTableList(Request::query('database', '')));
    exit;
}

// Handle form generation (JSON body)
if (Request::isPost()) {
    $contentType = Request::server('CONTENT_TYPE');
    if (strpos($contentType, 'application/json') !== false) {
        $jsonInput = json_decode(file_get_contents('php://input'), true);

        // Generate form definition
        if ($jsonInput && ($jsonInput['action'] ?? '') === 'generate') {
            header('Content-Type: application/json');
            $result = generateFormDefinition($jsonInput);
            echo json_encode($result);
            exit;
        }

        // Save edited definition directly
        if ($jsonInput && ($jsonInput['action'] ?? '') === 'save') {
            header('Content-Type: application/json');
            $result = saveFormDefinition($jsonInput);
            echo json_encode($result);
            exit;
        }
    }
}

$extraScript = '
' . cma_script('/cma/assets/js/main.js', true) . '
<script>
// Store available tables for combobox configuration
let availableTables = [];

// Enable/disable HTML switch based on type selection (global for inline onchange)
function updateFieldTypeUI(selectEl, idx) {
    const htmlSwitch = document.querySelector(`lib-switch[name="col_html[]"][data-idx="${idx}"]`);
    const comboRow = document.getElementById(`combo-config-${idx}`);

    // Handle HTML switch for memo fields
    if (htmlSwitch) {
        if (selectEl.value === "memo") {
            htmlSwitch.removeAttribute("disabled");
        } else {
            htmlSwitch.setAttribute("disabled", "");
            htmlSwitch.checked = false;
        }
    }

    // Handle combobox configuration row
    if (comboRow) {
        comboRow.style.display = selectEl.value === "combobox" ? "table-row" : "none";
    }
}

// Alias for backward compat
function updateHtmlSwitch(selectEl, idx) {
    updateFieldTypeUI(selectEl, idx);
}

// Load columns for a table (for combobox configuration)
async function loadComboColumns(idx, table) {
    const idCombo = document.getElementById("combo-id-" + idx);
    const displayCombo = document.getElementById("combo-display-" + idx);

    if (!table) {
        idCombo.clearOptions();
        displayCombo.clearOptions();
        return;
    }

    try {
        const dbSelect = document.getElementById("database");
        const response = await fetch("tools_formwiz.php?action=getColumns&database=" + encodeURIComponent(dbSelect.value) + "&table=" + encodeURIComponent(table));
        const columns = await response.json();

        if (columns.error) {
            top.console.error("Column load error:", columns.error);
            return;
        }

        // Build options arrays
        const idOptions = columns.map(col => ({
            value: col.COLUMN_NAME,
            label: col.COLUMN_NAME
        }));

        const displayOptions = columns.map(col => ({
            value: col.COLUMN_NAME,
            label: col.COLUMN_NAME
        }));

        // Set options on lib-combos
        idCombo.setOptions(idOptions);
        displayCombo.setOptions(displayOptions);

        // Auto-select ID field
        const idField = columns.find(c => c.COLUMN_NAME.toUpperCase() === "ID");
        if (idField) {
            idCombo.value = idField.COLUMN_NAME;
        }

        // Auto-select display field
        const displayCandidates = columns.filter(c => /name|descr|titel|title|omschr/i.test(c.COLUMN_NAME));
        if (displayCandidates.length > 0) {
            displayCombo.value = displayCandidates[0].COLUMN_NAME;
        } else if (columns.length > 1) {
            displayCombo.value = columns[1].COLUMN_NAME;
        }

    } catch (e) {
        top.console.error(e);
    }
}

// Get suggested table name from FK field name
function suggestTableFromField(fieldName) {
    // fkDeelnemer -> tblDeelnemers, fkOpleiding -> tblOpleidingen
    let match = fieldName.match(/^fk(.+)$/i);
    if (!match) return "";

    let baseName = match[1];
    // Common plural patterns in Dutch
    let suggestions = [
        "tbl" + baseName + "s",      // tblDeelnemers
        "tbl" + baseName + "en",     // tblOpleidingen
        "tbl" + baseName,            // tblDeelnemer (singular)
        baseName + "s",
        baseName + "en",
        baseName
    ];

    // Find matching table from available tables
    for (let suggestion of suggestions) {
        let found = availableTables.find(t => t.toLowerCase() === suggestion.toLowerCase());
        if (found) return found;
    }

    // Return first suggestion as fallback (user can change it)
    return suggestions[0];
}

document.addEventListener("DOMContentLoaded", function() {
    const databaseSelect = document.getElementById("database");
    const tableSelect = document.getElementById("table");
    const columnsContainer = document.getElementById("columns-container");
    const previewContainer = document.getElementById("preview-container");
    const generateBtn = document.getElementById("generate-btn");
    const formNameInput = document.getElementById("form-name");
    const formTitleInput = document.getElementById("form-title");

    // Database change - load tables
    databaseSelect.addEventListener("change", async function() {
        tableSelect.innerHTML = "<option value=\"\">Laden...</option>";
        tableSelect.disabled = true;
        columnsContainer.innerHTML = "";
        previewContainer.innerHTML = "";

        if (!this.value) {
            tableSelect.innerHTML = "<option value=\"\">-- Selecteer eerst een database --</option>";
            return;
        }

        try {
            const response = await fetch(`tools_formwiz.php?action=getTables&database=${encodeURIComponent(this.value)}`);
            const tables = await response.json();

            // Check for error response
            if (tables.error) {
                tableSelect.innerHTML = `<option value="">Fout: ${tables.error}</option>`;
                top.console.error("Table load error:", tables.error);
                return;
            }

            tableSelect.innerHTML = "<option value=\"\">-- Selecteer een tabel --</option>";
            if (Array.isArray(tables)) {
                // Store for combobox configuration
                availableTables = tables;
                tables.forEach(table => {
                    tableSelect.innerHTML += `<option value="${table}">${table}</option>`;
                });
            }
            tableSelect.disabled = false;
        } catch (e) {
            tableSelect.innerHTML = "<option value=\"\">Fout bij laden tabellen</option>";
            top.console.error(e);
        }
    });

    // Table change - load columns
    tableSelect.addEventListener("change", async function() {
        columnsContainer.innerHTML = "<div class=\"loading\">Kolommen laden...</div>";
        previewContainer.innerHTML = "";

        if (!this.value) {
            columnsContainer.innerHTML = "";
            return;
        }

        // Auto-fill form name from table name
        let tableName = this.value;
        let formName = tableName.replace(/^tbl/i, "").replace(/^vw/i, "");
        formName = formName.charAt(0).toLowerCase() + formName.slice(1);
        formName = formName.replace(/([A-Z])/g, "_$1").toLowerCase().replace(/^_/, "");
        formNameInput.value = formName;

        // Auto-fill title (capitalize words)
        let title = formName.replace(/_/g, " ");
        title = title.charAt(0).toUpperCase() + title.slice(1);
        formTitleInput.value = title;

        try {
            const response = await fetch(`tools_formwiz.php?action=getColumns&database=${encodeURIComponent(databaseSelect.value)}&table=${encodeURIComponent(this.value)}`);
            const columns = await response.json();

            if (columns.error) {
                columnsContainer.innerHTML = `<lib-message type="error">${columns.error}</lib-message>`;
                return;
            }

            renderColumnEditor(columns);
            generateBtn.disabled = false;

            // Initialize lib-combo elements for table selection
            customElements.whenDefined("lib-combo").then(() => {
                // Set up table combos with available tables
                const tableOptions = availableTables.map(t => ({ value: t, label: t }));

                document.querySelectorAll(\'lib-combo[id^="combo-table-"]\').forEach(tableCombo => {
                    const idx = tableCombo.dataset.idx;
                    const suggestedTable = tableCombo.getAttribute("value");

                    // Set options
                    tableCombo.setOptions(tableOptions);

                    // Restore suggested value after setOptions
                    if (suggestedTable) {
                        tableCombo.value = suggestedTable;
                        // Load columns for pre-selected table
                        loadComboColumns(idx, suggestedTable);
                    }

                    // Listen for changes
                    tableCombo.addEventListener("change", (e) => {
                        loadComboColumns(idx, e.detail.value);
                    });
                });
            });
        } catch (e) {
            columnsContainer.innerHTML = "<lib-message type=\"error\">Fout bij laden kolommen</lib-message>";
            top.console.error(e);
        }
    });

    function renderColumnEditor(columns) {
        let html = `
            <lib-table export="n">
            <table class="filtering">
                <thead>
                    <tr>
                        <th style="width:30px"><input type="checkbox" id="select-all" checked></th>
                        <th>Kolom</th>
                        <th>DB type</th>
                        <th>Control type</th>
                        <th>HTML</th>
                        <th>Label</th>
                        <th>Verplicht</th>
                        <th title="ID veld voor dit formulier">ID</th>
                        <th>Lijst</th>
                    </tr>
                </thead>
                <tbody>
        `;

        columns.forEach((col, idx) => {
            const fieldType = suggestFieldType(col);
            const caption = generateCaption(col.COLUMN_NAME);
            const isId = col.COLUMN_NAME.toUpperCase() === "ID" || col.IS_PRIMARY === "YES";
            const isRequired = col.IS_NULLABLE === "NO" && !isId;
            const isMemo = fieldType === "memo";
            const isCombobox = fieldType === "combobox";
            const suggestedTable = isCombobox ? suggestTableFromField(col.COLUMN_NAME) : "";

            // Build type options
            const types = ["textbox", "memo", "checkbox", "date", "datetime", "time", "combobox", "email", "url", "password", "readonly", "file", "image"];
            const typeLabels = {"textbox":"Textbox", "memo":"Memo", "checkbox":"Checkbox", "date":"Datum", "datetime":"Datum/tijd", "time":"Tijd", "combobox":"Combobox", "email":"E-mail", "url":"URL", "password":"Wachtwoord", "readonly":"Alleen lezen", "file":"Bestand", "image":"Afbeelding"};
            let typeOptions = "";
            types.forEach(t => {
                typeOptions += "<option value=\\"" + t + "\\"" + (fieldType === t ? " selected" : "") + ">" + typeLabels[t] + "</option>";
            });

            // Build table options for combobox
            let tableOptions = "<option value=\\"\\">-- Selecteer tabel --</option>";
            availableTables.forEach(t => {
                tableOptions += "<option value=\\"" + t + "\\"" + (t === suggestedTable ? " selected" : "") + ">" + t + "</option>";
            });

            html += "<tr>" +
                "<td><input type=\\"checkbox\\" name=\\"col_include[]\\" value=\\"" + col.COLUMN_NAME + "\\"" + (isId ? "" : " checked") + " data-idx=\\"" + idx + "\\"></td>" +
                "<td><code>" + col.COLUMN_NAME + "</code></td>" +
                "<td><small>" + col.DATA_TYPE + (col.MAX_LENGTH ? "(" + col.MAX_LENGTH + ")" : "") + "</small></td>" +
                "<td><select name=\\"col_type[]\\" data-idx=\\"" + idx + "\\" style=\\"width:120px\\" onchange=\\"updateFieldTypeUI(this, " + idx + ")\\">" + typeOptions + "</select></td>" +
                "<td style=\\"text-align:center\\"><lib-switch name=\\"col_html[]\\" data-idx=\\"" + idx + "\\" size=\\"small\\"" + (isMemo ? "" : " disabled") + "></lib-switch></td>" +
                "<td><input type=\\"text\\" name=\\"col_caption[]\\" value=\\"" + caption + "\\" data-idx=\\"" + idx + "\\" style=\\"width:150px\\"></td>" +
                "<td style=\\"text-align:center\\"><lib-switch name=\\"col_required\\" data-idx=\\"" + idx + "\\" size=\\"small\\"" + (isRequired ? " checked" : "") + "></lib-switch></td>" +
                "<td style=\\"text-align:center\\"><input type=\\"radio\\" name=\\"col_primary\\" value=\\"" + col.COLUMN_NAME + "\\"" + (isId ? " checked" : "") + "></td>" +
                "<td style=\\"text-align:center\\"><lib-switch name=\\"col_list\\" data-idx=\\"" + idx + "\\" size=\\"small\\"" + (idx < 3 ? " checked" : "") + "></lib-switch></td>" +
                "</tr>" +
                "<tr id=\\"combo-config-" + idx + "\\" class=\\"combo-config-row\\"" + (isCombobox ? "" : " style=\\"display:none\\"") + ">" +
                "<td></td>" +
                "<td colspan=\\"8\\">" +
                "<div class=\\"combo-config-fields\\">" +
                "<label class=\\"combo-config-label\\"><span>Brontabel:</span>" +
                "<lib-combo id=\\"combo-table-" + idx + "\\" data-idx=\\"" + idx + "\\" class=\\"combo-table-select\\" placeholder=\\"-- Selecteer tabel --\\"" + (suggestedTable ? " value=\\"" + suggestedTable + "\\"" : "") + "></lib-combo></label>" +
                "<label class=\\"combo-config-label\\"><span>ID veld:</span>" +
                "<lib-combo id=\\"combo-id-" + idx + "\\" data-idx=\\"" + idx + "\\" class=\\"combo-id-select\\" placeholder=\\"-- Selecteer eerst tabel --\\"></lib-combo></label>" +
                "<label class=\\"combo-config-label\\"><span>Weergave veld:</span>" +
                "<lib-combo id=\\"combo-display-" + idx + "\\" data-idx=\\"" + idx + "\\" class=\\"combo-display-select\\" placeholder=\\"-- Selecteer eerst tabel --\\"></lib-combo></label>" +
                "</div>" +
                "</td>" +
                "</tr>";
        });

        html += "</tbody></table></lib-table>";
        html += "<input type=\\"hidden\\" id=\\"columns-data\\" value=\\"" + JSON.stringify(columns).replace(/"/g, "&quot;") + "\\">";

        columnsContainer.innerHTML = html;

        // Select all toggle
        document.getElementById("select-all").addEventListener("change", function() {
            document.querySelectorAll("input[name=\\"col_include[]\\"]").forEach(cb => {
                cb.checked = this.checked;
            });
        });
    }

    function suggestFieldType(col) {
        const name = col.COLUMN_NAME.toLowerCase();
        const type = (col.DATA_TYPE || "").toUpperCase();
        const maxLen = parseInt(col.MAX_LENGTH) || 0;

        // FK fields -> combobox (check first, before other name-based)
        if (name.startsWith("fk")) return "combobox";

        // Name-based detection
        if (name.includes("email") || name.includes("mail")) return "email";
        if (name.includes("password") || name.includes("wachtwoord")) return "password";
        if (name.includes("url") || name.includes("website") || name.includes("link")) return "url";
        if (name.includes("image") || name.includes("foto") || name.includes("afbeelding") || name.includes("photo")) return "image";
        if (name.includes("file") || name.includes("bestand") || name.includes("document") || name.includes("attachment")) return "file";

        // Type-based detection
        if (type.includes("BIT") || type.includes("BOOL") || type.includes("YESNO")) return "checkbox";
        if (type.includes("DATE") && type.includes("TIME")) return "datetime";
        if (type.includes("DATE")) return "date";
        if (type.includes("TIME")) return "time";
        if (type.includes("TEXT") || type.includes("MEMO") || type.includes("LONGCHAR") || maxLen > 255) return "memo";
        if (type.includes("BINARY") || type.includes("BLOB")) return "file";

        return "textbox";
    }

    function generateCaption(columnName) {
        // Remove common prefixes
        let caption = columnName.replace(/^(fk|pk|tbl|vw|int|str|bln|dtm|txt)/i, "");
        // Convert camelCase to spaces
        caption = caption.replace(/([a-z])([A-Z])/g, "$1 $2");
        // Convert underscores to spaces
        caption = caption.replace(/_/g, " ");
        // Capitalize first letter
        caption = caption.charAt(0).toUpperCase() + caption.slice(1).toLowerCase();
        return caption.trim() || columnName;
    }

    // Generate button click
    generateBtn.addEventListener("click", async function() {
        const formData = collectFormData();

        previewContainer.innerHTML = "<div class=\"loading\">Formulier genereren...</div>";

        try {
            const response = await fetch("tools_formwiz.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ action: "generate", ...formData })
            });
            const result = await response.json();

            if (result.error) {
                previewContainer.innerHTML = `<lib-message type="error">${result.error}</lib-message>`;
                return;
            }

            // Show editable preview
            let html = `
                <lib-message type="success">Formulier definitie gegenereerd! Je kunt de JSON hieronder aanpassen voor opslaan.</lib-message>
                <h3>Preview: ${result.filename}</h3>
                <textarea id="json-editor" style="width:100%;height:400px;font-family:Consolas,Monaco,monospace;font-size:var(--font-size-sm);padding:10px;border:1px solid #ddd;background:var(--bg-primary);color:var(--text-primary);">${escapeHtml(JSON.stringify(result.definition, null, 2))}</textarea>
                <div style="margin-top:10px">
                    <button class="btn btn-primary" id="save-btn">Opslaan naar bestand</button>
                    <button class="btn" id="copy-btn">Kopieer naar klembord</button>
                </div>
            `;
            previewContainer.innerHTML = html;

            document.getElementById("save-btn").addEventListener("click", async function() {
                // Get edited JSON from textarea
                let editedJson;
                try {
                    editedJson = JSON.parse(document.getElementById("json-editor").value);
                } catch (parseError) {
                    previewContainer.insertAdjacentHTML("afterbegin", `<lib-message type="error">Ongeldige JSON: ${parseError.message}</lib-message>`);
                    return;
                }

                const saveResponse = await fetch("tools_formwiz.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({ action: "save", definition: editedJson, formName: formData.formName })
                });
                const saveResult = await saveResponse.json();

                if (saveResult.saved) {
                    previewContainer.innerHTML = `
                        <lib-message type="success">
                            <strong>Opgeslagen!</strong><br>
                            Bestand: <code>${saveResult.filename}</code><br>
                            Pad: <code>${saveResult.path}</code>
                        </lib-message>
                        <p>Je kunt het formulier nu openen via: <a href="/cma/form.php?form=${formData.formName}" target="_blank">/cma/form.php?form=${formData.formName}</a></p>
                    `;
                    // Notify parent window (form editor) that a form was created
                    if (window.parent && window.parent !== window) {
                        window.parent.postMessage({ type: "formwiz-saved", formName: formData.formName }, "*");
                    }
                } else {
                    previewContainer.insertAdjacentHTML("afterbegin", `<lib-message type="error">${saveResult.error || "Opslaan mislukt"}</lib-message>`);
                }
            });

            document.getElementById("copy-btn").addEventListener("click", function() {
                const jsonText = document.getElementById("json-editor").value;
                navigator.clipboard.writeText(jsonText);
                this.textContent = "Gekopieerd!";
                setTimeout(() => this.textContent = "Kopieer naar klembord", 2000);
            });

        } catch (e) {
            previewContainer.innerHTML = `<lib-message type="error">Fout: ${e.message}</lib-message>`;
            top.console.error(e);
        }
    });

    function collectFormData() {
        const columns = JSON.parse(document.getElementById("columns-data").value);
        const fields = [];
        const listColumns = [];
        let idField = "ID";

        document.querySelectorAll("input[name=\\"col_include[]\\"]:checked").forEach(cb => {
            const idx = parseInt(cb.dataset.idx);
            const col = columns[idx];
            const typeSelect = document.querySelector("select[name=\\"col_type[]\\"][data-idx=\\"" + idx + "\\"]");
            const captionInput = document.querySelector("input[name=\\"col_caption[]\\"][data-idx=\\"" + idx + "\\"]");
            const requiredSwitch = document.querySelector("lib-switch[name=\\"col_required\\"][data-idx=\\"" + idx + "\\"]");
            const listSwitch = document.querySelector("lib-switch[name=\\"col_list\\"][data-idx=\\"" + idx + "\\"]");
            const htmlSwitch = document.querySelector("lib-switch[name=\\"col_html[]\\"][data-idx=\\"" + idx + "\\"]");

            const field = {
                name: col.COLUMN_NAME,
                type: typeSelect.value,
                caption: captionInput.value
            };

            if (requiredSwitch && requiredSwitch.checked) field.required = true;
            if (col.MAX_LENGTH && col.MAX_LENGTH < 1000) field.maxLength = parseInt(col.MAX_LENGTH);
            if (typeSelect.value === "memo") {
                field.height = 5;
                if (htmlSwitch && htmlSwitch.checked) field.html = true;
            }

            // Combobox source configuration
            if (typeSelect.value === "combobox") {
                const comboTable = document.getElementById("combo-table-" + idx);
                const comboId = document.getElementById("combo-id-" + idx);
                const comboDisplay = document.getElementById("combo-display-" + idx);

                if (comboTable && comboTable.value) {
                    field.source = {
                        table: comboTable.value,
                        valueField: comboId?.value || "ID",
                        displayField: comboDisplay?.value || "Descr"
                    };
                }
            }

            fields.push(field);

            if (listSwitch && listSwitch.checked) {
                listColumns.push(col.COLUMN_NAME);
            }
        });

        // Get primary key
        const primaryRadio = document.querySelector("input[name=\\"col_primary\\"]:checked");
        if (primaryRadio) idField = primaryRadio.value;

        return {
            database: databaseSelect.value,
            table: tableSelect.value,
            formName: formNameInput.value,
            formTitle: formTitleInput.value,
            idField: idField,
            fields: fields,
            listColumns: listColumns,
            allowAdd: document.getElementById("opt-allowAdd").checked,
            allowDelete: document.getElementById("opt-allowDelete").checked,
            allowCopy: document.getElementById("opt-allowCopy").checked,
            storeLastModified: document.getElementById("opt-storeLastModified").checked
        };
    }

    // escapeHtml() provided by cma-utils.js

    // Load tables for the pre-selected database
    if (databaseSelect.value) {
        // Trigger the change handler to load tables
        tableSelect.innerHTML = "<option value=\"\">Laden...</option>";
        databaseSelect.dispatchEvent(new Event("change", { bubbles: true }));
    }
});
</script>
<style>
.loading { padding: 20px; text-align: center; color: #666; }
#columns-container { margin: 20px 0; }
#preview-container { margin: 20px 0; }
.form-row { display: flex; gap: 20px; margin-bottom: 15px; }
.form-row label { display: block; margin-bottom: 5px; font-weight: bold; }
.form-row input[type="text"] { width: 250px; padding: 6px 10px; }
.combo-config-row { background-color: var(--bg-input); }
.combo-config-row td { border-top: none !important; padding: 8px 12px; }
.combo-config-fields { display: flex; gap: 15px; align-items: center; flex-wrap: wrap; }
.combo-config-label { display: flex; align-items: center; gap: 6px; font-weight: normal; }
.combo-config-label span { color: var(--text-secondary); }
.combo-table-select { width: 180px; }
.combo-id-select { width: 120px; }
.combo-display-select { width: 150px; }
</style>';

cma_html_header('CMA - Form Wizard', $extraScript);
echo '<body class="contentbody tools">';

ToolbarHelper::report('Formulieren wizard', false, false, false, false, 'Genereer automatisch een JSON formulierdefinitie vanuit een database tabel');
echo '<div id="c" class="tools">';

// Database selection - default to 'data' database
$defaultDbId = CmaRepository::getDefaultDatabaseId() ?? 6;
echo '<div class="form-row">';
echo '<div>';
echo '<label for="database">Database</label>';
echo ToolsDatabaseSelect($defaultDbId, true, false, false);
echo '</div>';
echo '<div>';
echo '<label for="table">Tabel</label>';
echo '<select id="table" name="table" style="width:250px" disabled>';
echo '<option value="">-- Selecteer eerst een database --</option>';
echo '</select>';
echo '</div>';
echo '</div>';

// Form settings
echo '<div class="form-row">';
echo '<div>';
echo '<label for="form-name">Naam formulier</label>';
echo '<input type="text" id="form-name" name="form_name">';
echo '</div>';
echo '<div>';
echo '<label for="form-title">Titel</label>';
echo '<input type="text" id="form-title" name="form_title">';
echo '</div>';
echo '</div>';

// Form options
echo '<div class="form-row" style="flex-wrap:wrap;gap:10px 30px;">';
echo '<label style="display:inline-flex;align-items:center;gap:6px;font-weight:normal;"><input type="checkbox" id="opt-allowAdd" checked> Toevoegen toegestaan</label>';
echo '<label style="display:inline-flex;align-items:center;gap:6px;font-weight:normal;"><input type="checkbox" id="opt-allowDelete" checked> Verwijderen toegestaan</label>';
echo '<label style="display:inline-flex;align-items:center;gap:6px;font-weight:normal;"><input type="checkbox" id="opt-allowCopy"> Kopiëren toegestaan</label>';
echo '<label style="display:inline-flex;align-items:center;gap:6px;font-weight:normal;"><input type="checkbox" id="opt-storeLastModified"> Laatste wijziging opslaan</label>';
echo '</div>';

// Columns editor
echo '<div id="columns-container"></div>';

// Generate button
echo '<div style="margin: 20px 0;">';
echo '<button id="generate-btn" class="btn btn-primary" disabled>Genereer formulier</button>';
echo '</div>';

// Preview
echo '<div id="preview-container"></div>';

echo '</div></body></html>';

/**
 * Get list of tables from a database using centralized SchemaHelper
 */
function getTableList(string $databaseId): array {
    if (empty($databaseId)) return [];

    try {
        // Get connection name from config
        $dbConfig = ConfigLoader::getDatabase((int)$databaseId);
        $connName = $dbConfig['name'] ?? null;

        // Use connection name if available, otherwise resolved connection string
        $schemaConn = $connName ?: CmaRepository::getResolvedConnectionString((int)$databaseId);

        // Get tables via centralized SchemaHelper (handles all filtering)
        $tables = SchemaHelper::getTables($schemaConn);
        return array_column($tables, 'name');
    } catch (\Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

/**
 * Get columns from a table using centralized SchemaHelper
 */
function getTableColumns(string $databaseId, string $tableName): array {
    if (empty($databaseId) || empty($tableName)) return [];

    try {
        // Get connection name from config
        $dbConfig = ConfigLoader::getDatabase((int)$databaseId);
        $connName = $dbConfig['name'] ?? null;

        // Use connection name if available, otherwise resolved connection string
        $schemaConn = $connName ?: CmaRepository::getResolvedConnectionString((int)$databaseId);

        // Get columns via centralized SchemaHelper (handles filtering of hidden columns)
        $schemaColumns = SchemaHelper::getColumns($schemaConn, $tableName);

        // Map to expected format for JavaScript compatibility
        $columns = [];
        foreach ($schemaColumns as $col) {
            $columns[] = [
                'COLUMN_NAME' => $col['name'],
                'DATA_TYPE' => $col['dataTypeName'],
                'MAX_LENGTH' => $col['length'],
                'IS_NULLABLE' => $col['nullable'] ? 'YES' : 'NO',
                'IS_PRIMARY' => strtoupper($col['name']) === 'ID' ? 'YES' : 'NO',
                'ORDINAL' => $col['ordinal']
            ];
        }

        return $columns;
    } catch (\Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

/**
 * Generate form definition from input data
 */
function generateFormDefinition(array $input): array {
    $formName = preg_replace('/[^a-z0-9_]/', '', strtolower($input['formName'] ?? ''));
    $formTitle = $input['formTitle'] ?? ucfirst($formName);
    $table = $input['table'] ?? '';
    $database = $input['database'] ?? 'data';
    $idField = $input['idField'] ?? 'ID';
    $fields = $input['fields'] ?? [];
    $listColumns = $input['listColumns'] ?? [];

    // Settings with defaults
    $allowAdd = $input['allowAdd'] ?? true;
    $allowDelete = $input['allowDelete'] ?? true;
    $allowCopy = $input['allowCopy'] ?? false;
    $storeLastModified = $input['storeLastModified'] ?? false;

    if (empty($formName) || empty($table)) {
        return ['error' => 'Formuliernaam en tabel zijn verplicht'];
    }

    // Build listQuery
    $listSelectCols = [$idField];
    if (!empty($listColumns)) {
        foreach ($listColumns as $col) {
            if ($col !== $idField) {
                $listSelectCols[] = $col;
            }
        }
    }
    // Add a Descr alias for the first non-ID column
    $listQuery = "SELECT " . implode(', ', $listSelectCols);
    if (count($listSelectCols) > 1) {
        $listQuery = "SELECT {$idField}, {$listSelectCols[1]} as Descr";
        if (count($listSelectCols) > 2) {
            $listQuery .= ", " . implode(', ', array_slice($listSelectCols, 2));
        }
    }
    $listQuery .= " FROM {$table} ORDER BY " . ($listSelectCols[1] ?? $idField);

    // Map database ID to database name from config
    $dbName = 'data';
    $dbConfig = ConfigLoader::getDatabase((int)$database);
    if ($dbConfig && !empty($dbConfig['name'])) {
        $dbName = $dbConfig['name'];
    }

    // Build definition
    $definition = [
        '$schema' => '../schema/form-definition.schema.json',
        'version' => '1.0.0',
        'name' => $formName,
        'title' => $formTitle,
        'titleSingular' => $formTitle,
        'table' => $table,
        'database' => $dbName,
        'idField' => $idField,
        'listQuery' => $listQuery,
        'allowAdd' => $allowAdd,
        'allowDelete' => $allowDelete,
        'allowCopy' => $allowCopy,
        'securityByUser' => false,
        'storeLastModified' => $storeLastModified,
        'previewUrl' => '',
        'afterPostUrl' => '',
        'onLoadJs' => '',
        'filterIdName' => '',
        'quickSearchFields' => '',
        'detailField' => 'Descr',
        'fields' => $fields,
        'subforms' => []
    ];

    // Generate quickSearchFields from text fields
    $searchFields = [];
    foreach ($fields as $field) {
        if (in_array($field['type'], ['textbox', 'email', 'url']) && count($searchFields) < 5) {
            $searchFields[] = $field['name'];
        }
    }
    $definition['quickSearchFields'] = implode(',', $searchFields);

    $filename = "{$formName}.json";
    $path = __DIR__ . "/../assets/forms/definitions/{$filename}";

    // Save if requested
    if (!empty($input['save'])) {
        if (file_exists($path)) {
            return ['error' => "Bestand bestaat al: {$filename}. Verwijder eerst het bestaande bestand."];
        }

        $json = json_encode($definition, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (file_put_contents($path, $json) !== false) {
            return ['saved' => true, 'filename' => $filename, 'path' => $path];
        } else {
            return ['error' => 'Kan bestand niet opslaan'];
        }
    }

    return ['definition' => $definition, 'filename' => $filename];
}

/**
 * Save edited form definition directly
 */
function saveFormDefinition(array $input): array {
    $definition = $input['definition'] ?? null;
    $formName = preg_replace('/[^a-z0-9_]/', '', strtolower($input['formName'] ?? ''));

    if (!$definition || empty($formName)) {
        return ['error' => 'Ongeldige invoer'];
    }

    $filename = "{$formName}.json";
    $path = __DIR__ . "/../assets/forms/definitions/{$filename}";

    if (file_exists($path)) {
        return ['error' => "Bestand bestaat al: {$filename}. Verwijder eerst het bestaande bestand."];
    }

    $json = json_encode($definition, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (file_put_contents($path, $json) !== false) {
        return ['saved' => true, 'filename' => $filename, 'path' => $path];
    } else {
        return ['error' => 'Kan bestand niet opslaan'];
    }
}
