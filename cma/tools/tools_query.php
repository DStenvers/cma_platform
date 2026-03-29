<?php
use App\Library\Arr;
use Cma\ToolbarHelper;
use Cma\ConfigLoader;
use Cma\SchemaHelper;
use App\Library\Database;
use App\Library\Error;
use App\Library\Profiler;
use App\Library\Request;
use App\Library\Response;
use App\Library\SQL;
use App\Library\Str;
use App\Library\Table;
use Cma\CmaRepository;
use Cma\SecurityHelper;

require_once __DIR__ . '/../bootstrap.inc';

// Admin and developer tool
if (!SecurityHelper::isAdmin()) {
    Error::page('Toegang geweigerd', 'Deze functie is alleen beschikbaar voor administrators.', false);
    exit;
}

Response::noCache();

Profiler::start();

// Support both GET (from dashboard links) and POST (from form submission)
$CustomSQL = Request::post('query', '') ?: Request::query('sql', '');
$iDatabase = Request::post('database', '') ?: Request::query('database', '');

// Auto-select first database if none specified
$databases = CmaRepository::getSelectableDatabases();
if ($iDatabase === '' && count($databases) >= 1) {
    $iDatabase = (string)$databases[0]['id'];
}
$strHistory = "";
$libSQL_KeepOffQuotes = true;
$libSQL_KeepOffAmp = true;
ErrorHandler::setVerbose(true);
Error::setSendMail(false); // Don't email admin for ad-hoc query errors

// Build database type map for JS (id -> type) and determine current type
$dbTypeMap = [];
foreach ($databases as $db) {
    $cfg = ConfigLoader::getDatabase((int)$db['id']);
    $dbTypeMap[$db['id']] = $cfg['type'] ?? 'access';
}
$dbType = $dbTypeMap[$iDatabase] ?? 'access';

// Get table names for the selected database using centralized SchemaHelper
$tableNames = [];
if ($iDatabase !== '') {
    try {
        $dbConfig = ConfigLoader::getDatabase((int)$iDatabase);
        $connName = $dbConfig['name'] ?? null;
        $schemaConn = $connName ?: CmaRepository::getResolvedConnectionString((int)$iDatabase);
        $tables = SchemaHelper::getTables($schemaConn);
        $tableNames = array_column($tables, 'name');
    } catch (\Exception $e) {
        $tableLoadError = $e->getMessage();
    }
}

// Build toolbar extras (database selector)
$extras = '';
if (count($databases) > 1) {
    $extras = '<SELECT style="width:150px" id="toolbarDatabase" onchange="syncDatabaseAndSubmit(this)">';
    $dataDbId = CmaRepository::getDefaultDatabaseId();
    $effectiveDefault = $iDatabase !== '' ? $iDatabase : ($dataDbId ?? '');
    foreach ($databases as $opt) {
        $selected = (strval($effectiveDefault) === strval($opt['id'])) ? ' selected' : '';
        $extras .= '<OPTION VALUE="' . $opt['id'] . '"' . $selected . '>' . htmlspecialchars($opt['title'] ?? $opt['name']) . '</OPTION>';
    }
    $extras .= '</SELECT>';
}

// Pre-compute JS values for injection into script
$jsDbType = htmlspecialchars($dbType);
$jsDbTypeMap = json_encode($dbTypeMap);

// Build extra head content for custom styles and scripts
$extraHead = <<<'HEADCONTENT'
<style>
/* Query tool specific padding */
body.query #c.tools {
    padding: 0;
}
body.query .toolbar,
body.query #toolbar {
    border-bottom: 0;
}
/* Query tool tab layout */
.query-area,
.history-area {
    padding-left: 8px;
    padding-right: 8px;
    padding-bottom: 8px;
}
textarea#query {
    width: 100%;
    height: 200px;
    font-size: var(--font-size);
    padding: 8px;
    font-family: "Consolas", "Monaco", monospace;
    resize: vertical;
    background: var(--bg-primary);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
}
select[name="history"] {
    width: 100%;
    height: 300px;
    font-family: "Consolas", "Monaco", monospace;
    font-size: var(--font-size-xs);
    background: var(--bg-primary);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
}
/* Results area */
.query-results-area {
    padding: 10px;
}
.query-results-area table#resultaat {
    margin-left: -10px;
    width: calc(100% + 20px);
}
.query-results-area:empty {
    display: none;
}
.query-results-area h3 { margin-top: 0; }
.query-results-area .table-footer {
    margin-top: 8px;
    font-size: var(--font-size-sm);
    color: var(--text-muted);
}
/* Insert button next to dropdowns */
#toolbar .btn-icon {
    background: none;
    border: 1px solid transparent;
    box-shadow: none;
    padding: 4px 6px;
    border-radius: 3px;
    cursor: pointer;
    color: var(--text-secondary);
    vertical-align: middle;
    margin-left: 2px;
    transform: rotate(-90deg);
}
#toolbar .btn-icon:hover:not(:disabled) {
    border-color: var(--border-hover);
    background-color: var(--color-active);
}
#toolbar .btn-icon:disabled {
    opacity: 0.3;
    cursor: default;
}
</style>
<script>
// Track selected table and field for template replacements
var _selectedTable = '';
var _selectedField = '';
var _dbType = '__DB_TYPE__';
var _dbTypeMap = __DB_TYPE_MAP__;

function filterSampleQueries(dbType) {
    var select = document.querySelector('select[name="stdQueries"]');
    if (!select) return;
    select.querySelectorAll('option[data-db-type]').forEach(function(opt) {
        var type = opt.dataset.dbType;
        opt.hidden = (type !== 'all' && type !== dbType);
    });
    select.querySelectorAll('optgroup').forEach(function(group) {
        var visible = group.querySelectorAll('option:not([hidden])');
        group.hidden = (visible.length === 0);
    });
}

// Sync database selection from toolbar to hidden form field and submit
function syncDatabaseAndSubmit(selectEl) {
    var hiddenDb = document.getElementById('hiddenDatabase');
    if (hiddenDb) {
        hiddenDb.value = selectEl.value;
    }

    // Update sample queries filter for new database type
    _dbType = _dbTypeMap[selectEl.value] || 'access';
    filterSampleQueries(_dbType);

    // Don't set _execute flag - we just want to reload with new database
    document.forms.main._execute.value = '0';
    document.forms.main.submit();
}

function setQuery(strText) {
    // Replace placeholders with selected table/field
    var text = strText;
    if (_selectedTable) {
        text = text.replace(/\[tabel\]/gi, '[' + _selectedTable + ']');
    }
    if (_selectedField) {
        text = text.replace(/\[veld\]/gi, '[' + _selectedField + ']');
        text = text.replace(/\[veldnaam\]/gi, _selectedField);
    }
    document.forms.main.query.value = text;
}
function insertAtCursor(text) {
    var textarea = document.getElementById('query');
    if (!textarea) return;

    var start = textarea.selectionStart;
    var end = textarea.selectionEnd;
    var value = textarea.value;

    // Insert text at cursor position, replacing selection if any
    textarea.value = value.substring(0, start) + text + value.substring(end);

    // Move cursor to end of inserted text
    var newPos = start + text.length;
    textarea.setSelectionRange(newPos, newPos);
    textarea.focus();
}
function insertSelectedTable() {
    if (!_selectedTable) return;
    var textarea = document.getElementById('query');
    var text = textarea.value;
    var name = _selectedTable;
    // Only wrap in brackets if name contains a space
    var insertValue = name.indexOf(' ') >= 0 ? '[' + name + ']' : name;
    // Check for numbered placeholders: [tabel], [tabel2], [tabel3], etc.
    var placeholders = ['\\[tabel\\]', '\\[tabel2\\]', '\\[tabel3\\]', '\\[tabel4\\]', '\\[tabel5\\]'];
    var replaced = false;
    for (var i = 0; i < placeholders.length; i++) {
        var regex = new RegExp(placeholders[i], 'i');
        if (text.match(regex)) {
            textarea.value = text.replace(regex, insertValue);
            replaced = true;
            break;
        }
    }
    if (!replaced) {
        insertAtCursor(insertValue);
    }
}
function selectTable(selectEl) {
    // Called when table is selected (without inserting)
    var insertBtn = document.getElementById('insertTableBtn');
    if (selectEl.selectedIndex > 0) {
        _selectedTable = selectEl.value;
        loadFieldNames(selectEl.value);
        if (insertBtn) insertBtn.disabled = false;
    } else {
        _selectedTable = '';
        clearFieldNames();
        if (insertBtn) insertBtn.disabled = true;
    }
}
function loadFieldNames(tableName) {
    // Get database ID from toolbar select or hidden field
    var toolbarDbSelect = document.getElementById('toolbarDatabase');
    var hiddenDb = document.getElementById('hiddenDatabase');
    var dbId = toolbarDbSelect ? toolbarDbSelect.value : (hiddenDb ? hiddenDb.value : '');
    var fieldSelect = document.querySelector('select[name="fieldNames"]');
    if (!fieldSelect) return;

    // Show loading
    fieldSelect.innerHTML = '<option value="">[Laden...]</option>';
    fieldSelect.disabled = true;

    // Use centralized report-schema API for getting columns
    fetch('../api/report-schema.php?action=getColumns&database=' + encodeURIComponent(dbId) + '&table=' + encodeURIComponent(tableName))
        .then(function(response) { return response.json(); })
        .then(function(data) {
            fieldSelect.innerHTML = '<option value="">[Veld]</option>';
            if (data.success && data.columns && data.columns.length > 0) {
                data.columns.forEach(function(col) {
                    var opt = document.createElement('option');
                    opt.value = col.name;
                    opt.textContent = col.name + (col.typeName ? ' (' + col.typeName + ')' : '');
                    fieldSelect.appendChild(opt);
                });
            } else if (data.error) {
                fieldSelect.innerHTML = '<option value="">[' + data.error + ']</option>';
            }
            fieldSelect.disabled = false;
        })
        .catch(function(err) {
            console.error('[loadFieldNames]', err);
            fieldSelect.innerHTML = '<option value="">[Fout bij laden]</option>';
            fieldSelect.disabled = false;
        });
}
function clearFieldNames() {
    var fieldSelect = document.querySelector('select[name="fieldNames"]');
    if (fieldSelect) {
        fieldSelect.innerHTML = '<option value="">[Veld]</option>';
    }
    _selectedField = '';
    var insertBtn = document.getElementById('insertFieldBtn');
    if (insertBtn) insertBtn.disabled = true;
}
function insertSelectedField() {
    if (!_selectedField) return;
    var textarea = document.getElementById('query');
    var text = textarea.value;
    var name = _selectedField;
    // Only wrap in brackets if name contains a space
    var insertValue = name.indexOf(' ') >= 0 ? '[' + name + ']' : name;
    // Check for numbered placeholders: [veld], [veld2], [veld3], etc.
    var placeholders = ['\\[veld\\]', '\\[veld2\\]', '\\[veld3\\]', '\\[veld4\\]', '\\[veld5\\]'];
    var replaced = false;
    for (var i = 0; i < placeholders.length; i++) {
        var regex = new RegExp(placeholders[i], 'i');
        if (text.match(regex)) {
            textarea.value = text.replace(regex, insertValue);
            replaced = true;
            break;
        }
    }
    if (!replaced) {
        insertAtCursor(insertValue);
    }
}
function selectField(selectEl) {
    var insertBtn = document.getElementById('insertFieldBtn');
    if (selectEl.selectedIndex > 0) {
        _selectedField = selectEl.value;
        if (insertBtn) insertBtn.disabled = false;
    } else {
        _selectedField = '';
        if (insertBtn) insertBtn.disabled = true;
    }
}
function clear_history() {
    document.forms.main.query.value = " ";
    sessionStorage.removeItem("CMA_CustomSQL_History");
    document.forms.main._save_history.value = "";
    document.forms.main.submit();
}
function saveToHistory(query) {
    if (query && query.trim() !== "") {
        var history = sessionStorage.getItem("CMA_CustomSQL_History") || "";
        // Remove existing duplicate, then prepend (move to top)
        var items = history.split("|").filter(function(item) {
            return item.trim() !== "" && item.trim() !== query.trim();
        });
        items.unshift(query);
        var newHistory = items.join("|");
        sessionStorage.setItem("CMA_CustomSQL_History", newHistory);
        document.forms.main._save_history.value = newHistory;
    }
}
function loadFromHistory() {
    var history = sessionStorage.getItem("CMA_CustomSQL_History") || "";
    document.forms.main._save_history.value = history;
    return history;
}
function updateDeleteButtonState() {
    // Find the delete button in the history area by its onclick attribute
    var deleteBtn = document.querySelector('.history-area .tb-btn[onclick*="clear_history"]');
    if (!deleteBtn) return;

    var historySelect = document.forms.main.history;
    var hasItems = historySelect && historySelect.options.length > 0;

    if (hasItems) {
        deleteBtn.classList.remove('tb-btn-disabled');
        deleteBtn.removeAttribute('disabled');
    } else {
        deleteBtn.classList.add('tb-btn-disabled');
        deleteBtn.setAttribute('disabled', 'disabled');
    }
}
window.onload = function() {
    var history = loadFromHistory();
    var historySelect = document.forms.main.history;
    if (historySelect) {
        historySelect.innerHTML = "";
        if (history) {
            var items = history.split("|");
            for (var i = 0; i < items.length; i++) {
                if (items[i].trim() !== "") {
                    var option = document.createElement("option");
                    option.value = items[i];
                    option.textContent = items[i];
                    historySelect.appendChild(option);
                }
            }
        }
    }
    // Update delete button state based on history items
    updateDeleteButtonState();
    // Filter sample queries for current database type
    filterSampleQueries(_dbType);
};

/**
 * Check if query is dangerous and needs confirmation
 * Returns: { dangerous: boolean, message: string, tableName: string }
 */
function checkDangerousQuery(query) {
    var sql = query.trim().toLowerCase();
    // Extract table name from query
    var tableMatch;
    var tableName = "onbekend";

    // Check DELETE without WHERE
    if (sql.match(/^delete\s+from\s+/i)) {
        tableMatch = sql.match(/^delete\s+from\s+[\[\`]?(\w+)[\]\`]?/i);
        if (tableMatch) tableName = tableMatch[1];
        if (!sql.includes(" where ")) {
            return {
                dangerous: true,
                message: "De query maakt de hele tabel [" + tableName + "] leeg. Weet je het zeker?",
                tableName: tableName
            };
        }
    }

    // Check UPDATE without WHERE
    if (sql.match(/^update\s+/i)) {
        tableMatch = sql.match(/^update\s+[\[\`]?(\w+)[\]\`]?/i);
        if (tableMatch) tableName = tableMatch[1];
        if (!sql.includes(" where ")) {
            return {
                dangerous: true,
                message: "Deze query werkt alle records in tabel [" + tableName + "] bij. Weet je het zeker?",
                tableName: tableName
            };
        }
    }

    // Check DROP TABLE
    if (sql.match(/^drop\s+table/i)) {
        tableMatch = sql.match(/^drop\s+table\s+[\[\`]?(\w+)[\]\`]?/i);
        if (tableMatch) tableName = tableMatch[1];
        return {
            dangerous: true,
            message: "Deze query verwijdert de tabel [" + tableName + "] permanent. Weet je het zeker?",
            tableName: tableName
        };
    }

    // Check TRUNCATE TABLE
    if (sql.match(/^truncate\s+table/i)) {
        tableMatch = sql.match(/^truncate\s+table\s+[\[\`]?(\w+)[\]\`]?/i);
        if (tableMatch) tableName = tableMatch[1];
        return {
            dangerous: true,
            message: "Deze query leegt de tabel [" + tableName + "] volledig. Weet je het zeker?",
            tableName: tableName
        };
    }

    return { dangerous: false, message: "", tableName: "" };
}

function executeQuery() {
    var query = document.forms.main.query.value;
    var check = checkDangerousQuery(query);

    if (check.dangerous) {
        // Use libConfirm if available, otherwise native confirm
        if (typeof libConfirm === "function") {
            libConfirm(check.message, {
                title: "Gevaarlijke query",
                confirmText: "Ja, uitvoeren",
                cancelText: "Annuleren",
                onConfirm: function() {
                    doSubmitQuery();
                }
            });
        } else {
            libConfirm(check.message).then(function(ok) {
                if (ok) doSubmitQuery();
            });
        }
    } else {
        doSubmitQuery();
    }
}

function doSubmitQuery() {
    saveToHistory(document.forms.main.query.value);
    document.forms.main.go.disabled = true;
    document.forms.main._execute.value = '1';
    document.forms.main.submit();
}

</script>
HEADCONTENT;

// Inject PHP values into JS
$extraHead = str_replace('__DB_TYPE__', $jsDbType, $extraHead);
$extraHead = str_replace('__DB_TYPE_MAP__', $jsDbTypeMap, $extraHead);

// Initialize table filtering after content loads (filtering_init is in library.js)
$extraHead .= '<script>
document.addEventListener("DOMContentLoaded", function() {
    var table = document.getElementById("resultaat");
    if (table && typeof filtering_init === "function") {
        filtering_init(jQuery(table));
    }
});
</script>';

cma_html_header('CMA - SQL Query Tool', $extraHead);
echo '<BODY class="contentbody tools tool-query query">';
echo '<lib-loader id="queryLoader" overlay text="Query uitvoeren..."></lib-loader>';

ToolbarHelper::report('SQL commando', false, false, false, false, 'Voer SQL queries uit op de database', $extras);
echo '<div id="c" class="tools">';
if ($CustomSQL != '') {
    $strHistory = $CustomSQL . '|' . Request::post('_save_history', '');
} else {
    $strHistory = Request::post('_save_history', '');
}
DisplayForm();
echo '<div id="queryResultsArea" class="query-results-area">';
// Only execute query if _execute flag is set (not on database change)
if (Str::trim($CustomSQL) != '' && Request::post('_execute', '') === '1') {
    // Use custom error handling to prevent the full ErrorHandler from taking over
    $prevExHandler = set_exception_handler(null);
    restore_exception_handler();
    set_exception_handler(function(\Throwable $e) {
        echo '<lib-message type="error"><strong>Fout</strong><br>' . htmlspecialchars(Database::cleanErrorMessage($e->getMessage())) . '</lib-message>';
    });

    try {
        global $conn;
        $nTussenStand = microtime(true);
        if ($iDatabase != 999) {
            CmaRepository::openConnectionById($iDatabase);
        } else {
            $conn = $connrep;
        }
        $arrQueries = Arr::splitAlways($CustomSQL, ';');
        for ($tel = 0; $tel <= (count($arrQueries) - 1); $tel++) {
            $tempSQL = Str::trim($arrQueries[$tel]);
            if ($tempSQL === '') continue;
            echo '<h3>Resultaat:</h3>';
            try {
                $sqlStart = strtolower(substr($tempSQL, 0, 6));
                switch ($sqlStart) {
                    case 'select':
                        // Force PDO to throw on errors
                        $conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                        $stmt = $conn->prepare($tempSQL);
                        $stmt->execute();
                        $rs = new \App\Library\RecordSet($stmt, false);
                        echo Table::fromRecordset($rs, [
                            'id' => 'resultaat',
                            'class' => 'listtable filtering sorttable'
                        ]);
                        break;
                    default:
                        $conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                        $stmt = $conn->prepare($tempSQL);
                        $stmt->execute();
                        $iAffected = $stmt->rowCount();
                        echo $iAffected . ' record' . ($iAffected != 1 ? 's' : '') . ' aangepast<br><br>';
                        break;
                }
            } catch (\Throwable $e) {
                echo '<lib-message type="error"><strong>SQL fout</strong><br>' . htmlspecialchars(Database::cleanErrorMessage($e->getMessage())) . '</lib-message>';
            }
        }
        $nQueryMs = floor((microtime(true) - $nTussenStand) * 1000);
        $nTotalMs = floor(Profiler::getElapsed());
        echo '<br><i>Query : ' . $nQueryMs . 'ms' . ($nTotalMs > $nQueryMs ? ', totaal ' . $nTotalMs . 'ms' : '') . '</i>';
    } catch (\Throwable $e) {
        echo '<lib-message type="error"><strong>Verbindingsfout</strong><br>' . htmlspecialchars(Database::cleanErrorMessage($e->getMessage())) . '</lib-message>';
    }

    // Restore original exception handler
    set_exception_handler($prevExHandler);
}
echo '</div>'; // query-results-area
echo '</div>'; // #c.tools
echo '</body></html>';

/**
 * DisplayForm - Displays the SQL query form
 */
function DisplayForm()
{
    global $CustomSQL, $iDatabase, $strHistory, $lang_tb_delete, $tableNames;
    ?>
    <FORM action="tools_query.php" method="POST" id="main" name="main">
    <input type="hidden" name="_execute" value="0">
    <input type="hidden" name="database" id="hiddenDatabase" value="<?php echo htmlspecialchars($iDatabase); ?>">

    <cma-tabs id="queryTabs" tabs='["SQL", "Geschiedenis"]'>
        <div slot="tab-0">
            <div class="query-area">
<?php
    ToolbarHelper::start(false);
    ToolbarHelper::imageButton("javascript:setQuery('SELECT [veld], [veld2] FROM [tabel] WHERE [veld3]=value ORDER BY [veld4]')",
                       "/cma/assets/icons/0094-database.svg",
                       true, "Select", "Select commando");
    ToolbarHelper::imageButton("javascript:setQuery('INSERT INTO [tabel] ([veld], [veld2]) VALUES (value1, value2)')",
                       "/cma/assets/icons/0095-database-add.svg",
                       true, "Insert", "Insert commando");
    ToolbarHelper::imageButton("javascript:setQuery('UPDATE [tabel] SET [veld] = value WHERE [veld2]=value')",
                       "/cma/assets/icons/0098-database-refresh.svg",
                       true, "Update", "Update commando");
    ToolbarHelper::imageButton("javascript:setQuery('DELETE FROM [tabel] WHERE [veld]=value')",
                       "/cma/assets/icons/0096-database-remove.svg",
                       true, "Delete", "Delete commando");
?>
            <select name="stdQueries" onchange="javascript:setQuery(this.options[this.selectedIndex].value);this.selectedIndex=0">
                <option>[Selecteer een voorbeeld-query]</option>
                <optgroup Label="Velden">
                    <option data-db-type="access" value="ALTER TABLE [tabel] ADD COLUMN [veld] VARCHAR(30) WITH COMPRESSION DEFAULT defaultwaarde">Toevoegen</option>
                    <option data-db-type="sqlserver" value="ALTER TABLE [tabel] ADD [veld] NVARCHAR(30) DEFAULT defaultwaarde">Toevoegen</option>
                    <option data-db-type="all" value="ALTER TABLE [tabel] DROP COLUMN [veld]">Verwijderen</option>
                    <option data-db-type="access" value="ALTER TABLE [tabel] ALTER COLUMN [veld] VARCHAR(30) WITH COMPRESSION DEFAULT defaultwaarde">Wijzigen (tekst)</option>
                    <option data-db-type="sqlserver" value="ALTER TABLE [tabel] ALTER COLUMN [veld] NVARCHAR(30)">Wijzigen (tekst)</option>
                    <option data-db-type="access" value="ALTER TABLE [tabel] ALTER COLUMN [veld] YESNO DEFAULT FALSE">Wijzigen (boolean)</option>
                    <option data-db-type="sqlserver" value="ALTER TABLE [tabel] ALTER COLUMN [veld] BIT">Wijzigen (boolean)</option>
                </optgroup>
                <optgroup Label="Tabellen">
                    <option data-db-type="access" value="CREATE TABLE [tabel] (ID AUTOINCREMENT PRIMARY KEY, [veld] INTEGER, [veld2] YESNO DEFAULT False, [veld3] VARCHAR(50), Sortorder INTEGER)">Aanmaken</option>
                    <option data-db-type="sqlserver" value="CREATE TABLE [tabel] (ID INT IDENTITY(1,1) PRIMARY KEY, [veld] INT, [veld2] BIT DEFAULT 0, [veld3] NVARCHAR(50), Sortorder INT)">Aanmaken</option>
                    <option data-db-type="all" value="DROP TABLE [tabel]">Verwijderen</option>
                    <option data-db-type="all" value="ALTER TABLE [tabel] ADD CONSTRAINT naam_relatie FOREIGN KEY ([veld]) REFERENCES [tabel2](ID) ON DELETE CASCADE">Relatie aanmaken</option>
                </optgroup>
                <optgroup Label="Indexen">
                    <option data-db-type="all" value="CREATE UNIQUE INDEX indexnaam ON [tabel]([veld])">Aanmaken</option>
                    <option data-db-type="all" value="DROP INDEX indexnaam ON [tabel]">Verwijderen</option>
                </optgroup>
                <optgroup Label="Relaties">
                    <option data-db-type="all" value="ALTER TABLE [tabel] ADD CONSTRAINT naam FOREIGN KEY ([veld]) REFERENCES [tabel2](ID) ON DELETE CASCADE">Aanmaken</option>
                    <option data-db-type="all" value="ALTER TABLE [tabel] DROP CONSTRAINT [veld]">Verwijderen</option>
                </optgroup>
                <optgroup Label="Constraints">
                    <option data-db-type="all" value="ALTER TABLE [tabel] DROP CONSTRAINT [veld]">Verwijder constraint</option>
                    <option data-db-type="sqlserver" value="SELECT Name, definition FROM SYS.DEFAULT_CONSTRAINTS WHERE PARENT_OBJECT_ID = OBJECT_ID('[tabel]')">Defaults per tabel</option>
                    <option data-db-type="sqlserver" value="DECLARE @default_name varchar(256);
                        SELECT @default_name = [name] FROM sys.default_constraints WHERE parent_object_id=OBJECT_ID('[tabel]') AND COL_NAME(parent_object_id, parent_column_id)='[veld]';
                        EXEC('ALTER TABLE [tabel] DROP CONSTRAINT ' + @default_name);
                        ALTER TABLE [tabel] ADD DEFAULT([veld2]) FOR [veld];"> Wijzig defaults voor 1 veld</option>
                </optgroup>
                <optgroup Label="Views">
                    <option data-db-type="sqlserver" value="SELECT o.name, definition FROM sys.objects o JOIN sys.sql_modules m ON m.object_id = o.object_id WHERE o.type='V'">Alle views tonen</option>
                    <option data-db-type="sqlserver" value="SELECT definition FROM sys.objects o JOIN sys.sql_modules m ON m.object_id = o.object_id WHERE o.object_id = object_id('dbo.[veld]') AND o.type='V'">View tonen</option>
                    <option data-db-type="sqlserver" value="CREATE VIEW [veld] AS SELECT * FROM [tabel]">View aanmaken</option>
                    <option data-db-type="sqlserver" value="DROP VIEW [veld]">View verwijderen</option>
                </optgroup>
            </select>
            <span class="tb-sep tb-wrap-point"></span>
            <span class="tb-group">
                <select name="tableNames" onchange="selectTable(this)">
                    <option value="">[Tabel]</option>
<?php
    if (!empty($tableNames)) {
        foreach ($tableNames as $tbl) {
            echo '<option value="' . htmlspecialchars($tbl) . '">' . htmlspecialchars($tbl) . '</option>';
        }
    }
?>
                </select>
                <button type="button" class="btn-icon" id="insertTableBtn" onclick="insertSelectedTable()" title="Tabel invoegen" disabled><span class="lnr lnr-arrow-left"></span></button>
                <select name="fieldNames" onchange="selectField(this)">
                    <option value="">[Veld]</option>
                </select>
                <button type="button" class="btn-icon" id="insertFieldBtn" onclick="insertSelectedField()" title="Veld invoegen" disabled><span class="lnr lnr-arrow-left"></span></button>
            </span>
<?php
    ToolbarHelper::end(false);
    if (!empty($tableLoadError)) {
        echo '<lib-message type="error" style="margin-bottom:8px;">Fout bij laden tabellen: ' . htmlspecialchars(Database::cleanErrorMessage($tableLoadError)) . '</lib-message>';
    }
?>
            <TEXTAREA id="query" name="query"><?php echo $CustomSQL != '' ? htmlspecialchars($CustomSQL) : 'SELECT [veld], [veld2] FROM [tabel] WHERE [veld3]=value'; ?></TEXTAREA>
            <button class="btn btn-primary" onclick="executeQuery(); return false;" id="go">Query uitvoeren</button>
            </div>
        </div>

        <div slot="tab-1">
            <div class="history-area">
<?php
    ToolbarHelper::start(false);
    // Button state is managed by JavaScript updateDeleteButtonState()
    ToolbarHelper::imageButton("javascript:clear_history()",
                         "/cma/assets/icons/0130-trash2.svg",
                       true, "Verwijder", $lang_tb_delete ?? 'Verwijderen');
    ToolbarHelper::end(false);
    echo '<select name="history" size="15" onchange="javascript:setQuery(this.options[this.selectedIndex].value);document.getElementById(\'queryTabs\').setAttribute(\'selected\', \'0\');">';
    $arrItems = Arr::splitAlways($strHistory, '|');
    for ($y = 0; $y <= (count($arrItems) - 1); $y++) {
        if ($arrItems[$y] != '') {
            echo '<option value="' . htmlspecialchars($arrItems[$y]) . '">' . htmlspecialchars($arrItems[$y]) . '</option>';
        }
    }
    echo '</select>';
?>
            </div>
        </div>
    </cma-tabs>
    <textarea style="display:none" name="_save_history"><?php echo htmlspecialchars($strHistory); ?></textarea>
    </FORM>
<?php
}
