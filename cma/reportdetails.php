<?php
use App\Library\Arr;
use App\Library\Application;
use App\Library\Database;
use App\Library\Html;
use App\Library\Profiler;
use App\Library\Request;
use App\Library\Response;
use App\Library\SQL;
use App\Library\Server;
use App\Library\StringBuffer;
use Cma\CmaRepository;
use Cma\ReportExporter;
use Cma\SecurityHelper;
use Cma\Services\Logger;
use Cma\Services\ReportsService;
use Cma\ToolbarHelper;

require_once __DIR__ . '/bootstrap.inc';

Response::noCache();
// use SELECT and add edit button to it for enabling editing
// use field object caching for speed (like in list.php)
$nStarted = microtime(true);
echo '<HTML><HEAD>';
echo '<meta name="robots" content="noindex,nofollow,noarchive">';
echo '<TITLE>Content Management Application - Report details</TITLE>';
// PERFORMANCE FIX: Removed unused table_functions.js (488KB) - Excel export is done server-side via PHP
// Backup saved as table_functions.js.backup
cma_css();
cma_js();
echo '<script>;
var bDocReady = false;
document.addEventListener("DOMContentLoaded", function() {
    bDocReady = true;
});
/**
* Export To Excel
*/
function export_to_excel()
{
    if (bDocReady) {
        var exportBtn = document.querySelector("#resultaat a.exportXLS");
        if (exportBtn) {
            exportBtn.click();
        } else {
            // oude methode
            var sUrl = window.location + "&export=excel";
            var x = window.open(sUrl, "export", "");
            if (x.focus) x.focus();
        }
    } else {
        libToast.info("Even geduld aub, het rapport is nog aan het laden...");
    }
}
/**
* Export To Csv
*/
function export_to_CSV()
{
    if (bDocReady) {
        var exportBtn = document.querySelector("#resultaat a.exportCSV");
        if (exportBtn) {
            exportBtn.click();
        } else {
            // oude methode
            var sUrl = window.location + "&export=excel&type=CSV";
            var x = window.open(sUrl, "export", "");
            if (x.focus) x.focus();
        }
    } else {
        libToast.info("Even geduld aub, het rapport is nog aan het laden...");
    }
}
/**
* Export To Word
*/
function export_to_word()
{
    if (bDocReady) {
        var exportBtn = document.querySelector("#resultaat a.exportDOC");
        if (exportBtn) {
            exportBtn.click();
        } else {
            // oude methode
            var sUrl = window.location + "&export=word";
            var x = window.open (sUrl, "export", "")
            if (x.focus) x.focus();
        }
    } else {
        libToast.info("Even geduld aub, het rapport is nog bezig met laden...");
    }
}
/**
* Flip Group
*/
function flip_group( id )
{
    var sID = id.toString();
    var base_elt = document.getElementById("grp_" + sID + "_1");
    var strNewStyle = (base_elt.style.display==\'none\')?\'block\':\'none\';
    var row = 1;
    var elt = document.getElementById ("grp_" + sID + \'_\' + row.toString() );
    while (elt) {
        elt.style.display = strNewStyle;
        row++;
        elt = document.getElementById ("grp_" + sID + \'_\' + row.toString() );
    }
    document.getElementById("grp_img_"+sID).src = (strNewStyle==\'none\'?\'images/report_expand.gif\':\'images/report_collapse.gif\');
}
</script>';
echo '</HEAD>';
echo '<BODY style="margin:0px" class="contentbody reportdetails">';
define("FSO_FORREADING", 1);
define("FSO_FORWRITING", 2);
$sRepID = null;
$sRecID = null;
$strOrderBy = "";
$rsSubs = null;
$rsSubData = null;
$connSub = null;
$rsRep = null;

/**
 * Format a field value nicely for display in reports
 *
 * @param mixed $value The field value
 * @param string $width Optional width for TD
 * @param bool $vertical Vertical layout mode
 * @return string HTML TD element with formatted value
 */
function Lib_dbNiceFieldValue($value, string $width = '', bool $vertical = false): string
{
    // Handle null values
    if ($value === null) {
        return '<TD' . ($width ? ' width="' . $width . '"' : '') . '>&nbsp;</TD>';
    }

    // Extract value from ADO field object if needed
    if (is_object($value) && method_exists($value, 'value')) {
        $value = $value->value();
    }

    // Handle boolean values
    if (is_bool($value)) {
        $display = $value ? 'Ja' : 'Nee';
        return '<TD' . ($width ? ' width="' . $width . '"' : '') . '>' . $display . '</TD>';
    }

    // Handle DateTime objects
    if ($value instanceof \DateTime) {
        $display = $value->format('d-m-Y H:i');
        return '<TD' . ($width ? ' width="' . $width . '"' : '') . ' nowrap>' . $display . '</TD>';
    }

    // Handle date strings (check for common date patterns)
    if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
        $ts = strtotime($value);
        if ($ts !== false) {
            // Check if it's a date-only or datetime
            if (strpos($value, ' ') !== false || strpos($value, 'T') !== false) {
                $display = date('d-m-Y H:i', $ts);
            } else {
                $display = date('d-m-Y', $ts);
            }
            return '<TD' . ($width ? ' width="' . $width . '"' : '') . ' nowrap>' . $display . '</TD>';
        }
    }

    // Handle numeric values
    if (is_numeric($value) && !is_string($value)) {
        // Check if it's a float with decimals
        if (is_float($value) || (is_string($value) && strpos($value, '.') !== false)) {
            $display = number_format((float)$value, 2, ',', '.');
        } else {
            $display = (string)$value;
        }
        return '<TD' . ($width ? ' width="' . $width . '"' : '') . ' align="right">' . $display . '</TD>';
    }

    // Handle strings - escape HTML
    $display = htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');

    // Convert newlines to <br> for display
    $display = nl2br($display);

    return '<TD' . ($width ? ' width="' . $width . '"' : '') . '>' . $display . '</TD>';
}

/**
* Main
*
*/
function main()
{
    global $conn, $connrep;
    $sql = null;
    $sqlSub = null;
    $intOrderPos = 0;
    $strGroupField1 = "";
    $strGroupField2 = "";
    $strGroupField3 = "";
    $sOldFilterItem = null;
    $strTitle = "";
    $sFilterID = Request::query('filterID', '');
    $blnExcel = (($pos = stripos(Request::query('export', ''), 'excel', max(0, 1 - 1))) !== false ? $pos + 1 : 0) > 0;
    $blnWord = (($pos = stripos(Request::query('export', ''), 'word', max(0, 1 - 1))) !== false ? $pos + 1 : 0) > 0;
    $blnCSV = (($pos = stripos(Request::query('type', ''), 'csv', max(0, 1 - 1))) !== false ? $pos + 1 : 0) > 0;
    // give it some time!
    set_time_limit(900000);
    $sRepID = Request::queryInt('RepID');
    $srecID = Request::query('ID', '');

    // Validate required RepID parameter
    if (empty($sRepID)) {
        echo '<lib-message type="error">Report ID (RepID) parameter is required.</lib-message></body></html>';
        return;
    }

    // Laad rapport uit JSON config (vervangt tblModules/tblReports JOIN)
    $reportData = ReportsService::getById($sRepID);
    if ($reportData === null) {
        throw new \Exception('Rapport niet gevonden (ID: ' . $sRepID . ')');
    }

    // Maak een fields-compatibel object van JSON data
    // Koppelt JSON keys aan oude database kolomnamen
    $rsRep = new \stdClass();
    $rsRep->fields = [
        'Query' => $reportData['query'] ?? '',
        'IDField' => $reportData['idField'] ?? 'ID',
        'Title' => $reportData['title'] ?? '',
        'EditURL' => $reportData['editUrl'] ?? null,
        'EditForm' => $reportData['editForm'] ?? null,
        'GroupField1' => $reportData['groupField1'] ?? '',
        'GroupField2' => $reportData['groupField2'] ?? '',
        'GroupField3' => $reportData['groupField3'] ?? '',
        'FilterIDField' => $reportData['filterIdField'] ?? '',
        'FilterDisplayField' => $reportData['filterDisplayField'] ?? '',
        'filterCaption' => $reportData['filterCaption'] ?? '',
        'blnWordTextOnly' => $reportData['wordTextOnly'] ?? false,
        'blnWordSkipEmpty' => $reportData['wordSkipEmpty'] ?? false,
    ];

    // Haal database connection string op via DatabasesService
    $databaseId = $reportData['databaseId'] ?? 6;
    $connectionString = CmaRepository::getResolvedConnectionString($databaseId);
    $rsRep->fields['ConnectionString'] = $connectionString;

    // Controleer Query veld
    $sql = $rsRep->fields['Query'] ?? '';
    if (empty($sql)) {
        // Debug: show available fields
        $fieldNames = Arr::isArray($rsRep->fields) ? implode(', ', array_keys($rsRep->fields)) : 'fields not available';
        throw new \Exception('Report has no query defined (ID: ' . $sRepID . '). Available fields: ' . $fieldNames);
    }
    if ($srecID != '') {
        $sql = SQL::addWhere($sql, '[' . $rsRep->fields['IDField'] . ']=' . $srecID);
    }
    if ($sFilterID != '') {
        $sql = SQL::addWhere($sql, $rsRep->fields['FilterIDField'] . '=' . ((is_null($rsRep->fields['FilterIDField'] . '') ? "" : strtoupper($rsRep->fields['FilterIDField'] . '')) == 'ID' ? $sFilterID : "'" . $sFilterID . "'"));
    }
    // stuff new sort order
    // assumes: the ORDER BY is always the last clause in an SQL
    $strOrderBy = Request::query(CONST_STRSORTPARAM, '');
    if ($strOrderBy != '') {
        $intOrderPos = (($pos = stripos((is_null($sql) ? "" : strtoupper($sql)), 'ORDER BY')) !== false ? $pos + 1 : 0);
        if ($intOrderPos > 0) {
            $sql = substr($sql, 0, max(0, min($intOrderPos - 1, strlen($sql))));
        }
        $sql .= ' ORDER BY ' . $strOrderBy;
    } else {
        $strGroupField1 = $rsRep->fields['GroupField1'];
        $strGroupField2 = $rsRep->fields['GroupField2'];
        $strGroupField3 = $rsRep->fields['GroupField3'];
    }
    // Open database connection using the resolved connection string
    $conn = Database::getConnection($connectionString);
    $rs = Database::openRS($sql, $conn, adOpenForwardOnly);
    if ($rs === null) {
        throw new \Exception(Database::getLastError());
    }
    // Note: RecordSet constructor already loads first row, no MoveNext needed here
    // Get initial row data for filter display
    $rs_current_row = $rs->fields;
    // Laad sub-rapporten uit JSON config (vervangt tblModules/tblReports JOIN)
    $subReportsData = ReportsService::getSubReports($sRepID);

    // Maak een mock recordset-achtig object voor sub-rapporten
    $rsSubs = new class($subReportsData) {
        private array $data = [];
        private int $position = 0;
        public array $fields = [];
        public bool $EOF = true;

        public function __construct(array $subReports) {
            // Converteer JSON data naar recordset-compatibel formaat
            foreach ($subReports as $sub) {
                $dbId = $sub['databaseId'] ?? 6;
                $this->data[] = [
                    'Query' => $sub['query'] ?? '',
                    'parentField' => $sub['parentField'] ?? '',
                    'IDField' => $sub['idField'] ?? 'ID',
                    'Title' => $sub['title'] ?? '',
                    'EditURL' => $sub['editUrl'] ?? null,
                    'EditForm' => $sub['editForm'] ?? null,
                    'GroupField1' => $sub['groupField1'] ?? '',
                    'GroupField2' => $sub['groupField2'] ?? '',
                    'GroupField3' => $sub['groupField3'] ?? '',
                    'FilterIDField' => $sub['filterIdField'] ?? '',
                    'FilterDisplayField' => $sub['filterDisplayField'] ?? '',
                    'filterCaption' => $sub['filterCaption'] ?? '',
                    'ConnectionString' => \Cma\CmaRepository::getResolvedConnectionString($dbId),
                ];
            }
            $this->updateState();
        }

        private function updateState(): void {
            $this->EOF = $this->position >= count($this->data);
            if (!$this->EOF) {
                $this->fields = $this->data[$this->position];
            }
        }

        public function MoveNext(): void {
            $this->position++;
            $this->updateState();
        }

        public function MoveFirst(): void {
            $this->position = 0;
            $this->updateState();
        }
    };
    $strTitle = Server::htmlEncode($rsRep->fields['Title'] . '');
    if ($sFilterID != '') {
        $strTitle .= ' | ' . Arr::field($rs_current_row, $rsRep->fields['filterDisplayField']);
    }
    // TODO: : block Excel when not rsSubs.eof?
    ToolbarHelper::report($strTitle, $rsRep->fields['GroupField1']!= '', $strOrderBy == '', !$rsRep->fields['FilterIDField']!= '' && $sFilterID == '');
    flush();
    ob_flush();
    if ($rs->EOF) {
        echo (Application::get('CMA_LANGUAGE', '') == 'UK' ? 'No records to display...' : '<div class="no-data">Geen gegevens om weer te geven...</div>');
    } else {
        if ($rsRep->fields['FilterIDField']!= '' && Request::query('action', '')== '') {
            echo '<br><br><br><form id=main name=main method=get><input type=hidden name=action value=start><input type=hidden name=RepID value=' . $sRepID . '>';
            echo '<table><tr><td><b>' . Server::htmlEncode($rsRep->fields['filterCaption'] . '') . '</td><td>';
            echo '<select name=filterID><OPTION>';
            $sOldFilterItem = '';
            while (!$rs->EOF) {
                $rs_current_row = $rs->fields;
                if (Arr::field($rs_current_row, $rsRep->fields['FilterIDField']) != $sOldFilterItem) {
                    $sOldFilterItem = Arr::field($rs_current_row, $rsRep->fields['filterIDField']);
                    echo '<OPTION VALUE="' . Arr::field($rs_current_row, $rsRep->fields['filterIDField']) . '">';
                    echo Arr::field($rs_current_row, $rsRep->fields['filterDisplayField']);
                }
                $rs->MoveNext();
            }
            echo '</select></td></tr>';
            echo '<tr valign=top><td style=padding-top:6px><b>Aktie</td><td>';
            echo '<input type=radio checked name=export value="">Toon op het scherm<br><input type=radio name=export value="word">Exporteer naar Microsoft Word<br><input type=radio name=export value="excel">Exporteer naar Microsoft Excel</td></tr>';
            echo '<tr><td><button class="btn btn-primary" onclick="document.forms.main.submit()" id="go">Ok</button></td></tr>';
            echo '</table>';
        } else {
            if ($blnExcel) {
                ExcelExportRS($rs, $rsSubs, $blnCSV);
            } else {
                if ($blnWord) {
                    WordExportRS($rs, $rsSubs, $strTitle);
                } else {
                    DisplayRS($rsRep->fields['Title'], $rs, $rsSubs, $rsRep->fields['EditForm'], $rsRep->fields['EditURL'], $rsRep->fields['IDField'], $strGroupField1, $strGroupField2, $strGroupField3, $rsRep->fields['FilterIDField'], $rsRep->fields['FilterDisplayField']);
                }
            }
        }
    }
    $rsSubs = null;
    $connrep = null;
    echo '</body></html>';
    if (Application::get('performance_log', '')) {
        Profiler::log('CMA', (string)floor(Profiler::getElapsed()), 'Tonen rapport ' . $strTitle, '', '');
    }
    Profiler::end();
}
// This function will open a specific subreport (prepared in rsSubData)
// TODO: Todo: support grouping, editing etc
/**
* Opensubreport
*
*/
function OpenSubReport($rs, $rsRepDef)
{
    global $rsSubData;
    // base query
    $sql = $rsRepDef->fields['Query'];
    // parent key matching
    $sql = SQL::addWhere($sql, '[' . $rsRepDef->fields['parentField'] . ']=' . $rs->fields[$rsRepDef->fields['IDField']]);
    $rsSubData = Database::openRS($sql, $rs->activeconnection, adOpenForwardOnly);
    if ($rsSubData === null) {
        throw new \Exception(Database::getLastError());
    }
    // Note: RecordSet constructor already loads first row, no MoveNext needed here
}
// Closes the subreport data connection
/**
* Closesubreport
*
*/
function CloseSubReport()
{
    // connSub.Close
}

/**
 * PERFORMANCE FIX: Pre-fetch all sub-report data for all parent IDs in batch
 * Instead of N queries (one per parent record), execute M queries (one per sub-report definition)
 *
 * @param array $parentIds Array of parent record IDs
 * @param \ADODB\Recordset $rsSubs Recordset of sub-report definitions
 * @param \ADODB\Connection $conn Database connection
 * @param string $idField Name of the ID field in parent records
 * @return array Associative array: [subReportIndex][parentId] => [rows]
 */
function prefetchSubReportData(array $parentIds, $rsSubs, $conn, string $idField): array
{
    $subReportData = [];

    if (empty($parentIds) || $rsSubs->EOF) {
        return $subReportData;
    }

    // Convert parent IDs to comma-separated list for IN clause
    $sanitizedIds = array_map(function($id) {
        return is_numeric($id) ? intval($id) : "'" . addslashes($id) . "'";
    }, $parentIds);
    $inClause = implode(',', $sanitizedIds);

    // Store sub-report definitions in an array first (for forward-only cursors)
    $subReportDefs = [];
    while (!$rsSubs->EOF) {
        $subReportDefs[] = [
            'Query' => $rsSubs->fields['Query'],
            'parentField' => $rsSubs->fields['parentField'],
            'IDField' => $rsSubs->fields['IDField'] ?? $idField,
            'Title' => $rsSubs->fields['Title'] ?? ''
        ];
        $rsSubs->MoveNext();
    }

    // For each sub-report definition, batch fetch all related data
    foreach ($subReportDefs as $index => $subDef) {
        $subReportData[$index] = [];

        $baseQuery = $subDef['Query'];
        $parentField = $subDef['parentField'];

        // Modify query to fetch all parent records at once using IN clause
        $batchSql = SQL::addWhere($baseQuery, '[' . $parentField . '] IN (' . $inClause . ')');

        try {
            $rsSubBatch = Database::openRS($batchSql, $conn, adOpenForwardOnly);
            if ($rsSubBatch !== null) {
                while (!$rsSubBatch->EOF) {
                    $parentId = $rsSubBatch->fields[$parentField] ?? null;
                    if ($parentId !== null) {
                        if (!isset($subReportData[$index][$parentId])) {
                            $subReportData[$index][$parentId] = [];
                        }
                        // Store the row data
                        $rowData = [];
                        if (Arr::isArray($rsSubBatch->fields)) {
                            $i = 0;
                            foreach ($rsSubBatch->fields as $fname => $fvalue) {
                                $rowData[$i] = $fvalue;
                                $rowData[$fname] = $fvalue;
                                $i++;
                            }
                        } else {
                            for ($i = 0; $i < $rsSubBatch->fields->count; $i++) {
                                $rowData[$i] = $rsSubBatch->fields[$i];
                            }
                        }
                        $subReportData[$index][$parentId][] = $rowData;
                    }
                    $rsSubBatch->MoveNext();
                }
            }
        } catch (\Exception $e) {
            // Log error but continue with other sub-reports
            Logger::error('SubReport batch fetch failed', ['error' => $e->getMessage()]);
        }
    }

    // Store sub-report definitions globally for use in DisplayRS
    $cachedSubReportDefs = $subReportDefs;

    return $subReportData;
}

// Display function on screen
/**
* Displayrs
*
*/
function DisplayRS($sTitel, $rs, $rsSubs, $intEditFormID, $strEditURL, $strIDField, $strGroupField1, $strGroupField2, $strGroupField3, $strFilterIDField, $strFilterDisplayField)
{
    global $lang_tb_edit, $nStarted;
    $a = null;
    $i = null;
    $strCurrentColor = "";
    $strGroupValue1 = "";
    $strGroupValue2 = "";
    $strGroupValue3 = "";
    $intColumns = 0;
    $sValue = null;
    $group_count = null;
    $group_id = null;
    $intPerc = 0;
    $oGroep1 = null;
    $oGroep2 = null;
    $oGroep3 = null;
    $fld = null;
    $fld_name = null;
    $sSingleRecord = null;
    $sFullEditUrl = null;

    // PERFORMANCE FIX: Pre-fetch all data first to avoid N+1 queries
    // Step 1: Collect all rows from main recordset into array
    $allRows = [];
    $parentIds = [];
    $fieldNames = []; // Store column names at numeric indices
    $idFieldName = $strIDField ?: 'ID';
    $idFieldNameLower = strtolower($idFieldName);
    $firstRow = true;
    while (!$rs->EOF) {
        $rowData = [];
        // Get current row as array
        $currentRow = $rs->fetchAssoc();
        if (Arr::isArray($currentRow)) {
            // PHP array - iterate by key
            $col = 0;
            foreach ($currentRow as $fieldName => $value) {
                // Skip numeric keys (ADO duplicates)
                if (is_int($fieldName)) continue;
                $rowData[$col] = $value;
                $rowData[$fieldName] = $value;
                // Also store with lowercase key for case-insensitive lookup
                $rowData[strtolower($fieldName)] = $value;
                // Capture field names on first row
                if ($firstRow) {
                    $fieldNames[$col] = $fieldName;
                }
                $col++;
            }
        } else {
            // ADO-style field collection
            for ($col = 0; $col < $rs->fields->count; $col++) {
                $fldObj = $rs->fields[$col];
                $value = is_object($fldObj) && method_exists($fldObj, 'value') ? $fldObj->value() : $fldObj;
                $rowData[$col] = $value;
                if ($fldObj && method_exists($fldObj, 'name')) {
                    $fieldName = $fldObj->name();
                    if ($fieldName) {
                        $rowData[$fieldName] = $value;
                        // Also store with lowercase key for case-insensitive lookup
                        $rowData[strtolower($fieldName)] = $value;
                        // Capture field names on first row
                        if ($firstRow) {
                            $fieldNames[$col] = $fieldName;
                        }
                    }
                }
            }
        }
        $firstRow = false;
        // Collect parent ID for sub-report prefetch (case-insensitive lookup)
        $idValue = null;
        if (isset($rowData[$idFieldName])) {
            $idValue = $rowData[$idFieldName];
        } elseif (isset($rowData[$idFieldNameLower])) {
            $idValue = $rowData[$idFieldNameLower];
        }
        if ($idValue !== null) {
            $idValue = is_object($idValue) && method_exists($idValue, 'value')
                ? $idValue->value()
                : $idValue;
            if ($idValue !== null && $idValue !== '') {
                $parentIds[] = $idValue;
            }
        }
        $allRows[] = $rowData;
        $rs->MoveNext();
    }

    // Step 2: Pre-fetch all sub-report data in batch (M queries instead of N*M)
    $subReportData = [];
    $subReportDefs = [];
    if (!$rsSubs->EOF && !empty($parentIds)) {
        $subReportData = prefetchSubReportData($parentIds, $rsSubs, $rs->activeconnection, $idFieldName);
        $subReportDefs = $cachedSubReportDefs ?? [];
    }
    // Wrap table with lib-table component for filtering when no grouping is used
$useLibTable = ($strGroupField1 == "");
$tableDataName = substr(str_replace('?', '', str_replace('/', '', $sTitel)), 0, max(0, min(30, strlen(str_replace('?', '', str_replace('/', '', $sTitel))))));
if ($useLibTable) {
    echo '<div id="c"><lib-table><TABLE class="listtable filtering" cellspacing="0" cellpadding="0" WIDTH=100% data-name="' . $tableDataName . '" id=resultaat><thead><TR class="listheader">';
} else {
    echo '<div id="c"><TABLE class="listtable filtering" cellspacing="0" cellpadding="0" WIDTH=100% data-name="' . $tableDataName . '" id=resultaat><thead><TR class="listheader">';
}
    $intColumns = 0;
    // provide an edit URL if a form is specified (editForm is now a form name string)
    if (!empty($intEditFormID)) {
        $strEditURL = 'form.php?ID=[ID]&form=' . urlencode($intEditFormID);
    }
    // provide spacer header column if first grouping is specified
    if (!$strGroupField1 == "") {
        echo '<TH>&nbsp;</TH>';
        $intColumns = $intColumns + 1;
    }
    // provide spacer header column if an edit URL is not specified
    if (!$strEditURL == "") {
        echo "<TH data-filter='N'>&nbsp;</TH>";
        $intColumns = $intColumns + 1;
    }
    // PERFORMANCE FIX: Use fieldNames array captured during pre-fetch
    $headerFieldCount = count($fieldNames);
    for ($a = 0; $a < $headerFieldCount; $a++) {
        if (!isset($fieldNames[$a])) continue;
        $fld_name = strtolower($fieldNames[$a]);
        // display header if it's not a grouping or ID field
        if ($fld_name != strtolower($strGroupField1 ?? '') && $fld_name != strtolower($strGroupField2 ?? '') && $fld_name != strtolower($strGroupField3 ?? '') && $fld_name != $idFieldNameLower && $fld_name != 'guid' && $fld_name != strtolower($strFilterIDField ?? '') && $fld_name != strtolower($strFilterDisplayField ?? '')) {
            $headerText = $fieldNames[$a];
            echo '<TH align=middle valign=top>' . str_replace('_', ' ', $headerText) . '</TH>';
            $intColumns = $intColumns + 1;
        }
    }
    echo '</tr></thead><tbody>';
    $i = 0;
    $strCurrentColor = 'col1';
    $strGroupValue1 = '';
    $strGroupValue2 = '';
    $strGroupValue3 = '';
    $group_id = 0;
    $group_count = 0;

    // Get field count from fieldNames array
    $fieldCount = count($fieldNames);

    // PERFORMANCE FIX: Iterate over pre-fetched array instead of recordset
    foreach ($allRows as $rowIndex => $currentRow) {
        $i = $i + 1;
        if (microtime(true) - $nStarted > 1.5) {
            ob_flush();
            flush();
            $nStarted = microtime(true);
        }

        // Get group field values from current row
        $oGroep1 = $strGroupField1 != '' && isset($currentRow[$strGroupField1]) ? $currentRow[$strGroupField1] : null;
        $oGroep2 = $strGroupField2 != '' && isset($currentRow[$strGroupField2]) ? $currentRow[$strGroupField2] : null;
        $oGroep3 = $strGroupField3 != '' && isset($currentRow[$strGroupField3]) ? $currentRow[$strGroupField3] : null;

        // Extract value from field object if needed
        $oGroep1Val = is_object($oGroep1) && method_exists($oGroep1, 'value') ? $oGroep1->value() : $oGroep1;
        $oGroep2Val = is_object($oGroep2) && method_exists($oGroep2, 'value') ? $oGroep2->value() : $oGroep2;
        $oGroep3Val = is_object($oGroep3) && method_exists($oGroep3, 'value') ? $oGroep3->value() : $oGroep3;

        if ($strGroupField1 != '') {
            if ($strGroupValue1 != $oGroep1Val) {
                $group_id = $group_id + 1;
                $group_count = 0;
                echo '<TR class=noexport><td align=left id=grp_' . $group_id . '_0 colspan=' . $intColumns . '><a href=javascript:flip_group(' . $group_id . ')><img border=0 id=grp_img_' . $group_id . ' src=images/report_collapse.gif width=9 height=9 style=margin-left:4px;margin-right:4px;></a>' . Server::htmlEncode(Html::fixUnicode($oGroep1Val)) . '</td></TR>';
                $strGroupValue1 = $oGroep1Val;
            }
        }
        if ($strGroupField2 != '') {
            if ($strGroupValue2 != $oGroep2Val) {
                $group_count = $group_count + 1;
                echo '<TR class=noexport ><td align=left colspan=' . $intColumns . ' id=grp_' . $group_id . '_' . $group_count . '><img src=' . Application::get('pict_filler', '') . ' width=9 height=9 style=margin-left:14px;margin-right:4px;>' . Server::htmlEncode(Html::fixUnicode($oGroep2Val)) . '</td></TR>';
                $strGroupValue2 = $oGroep2Val;
            }
        }
        if ($strGroupField3 != '') {
            if ($strGroupValue3 != $oGroep3Val) {
                $group_count = $group_count + 1;
                echo '<TR class=noexport ><td align=left colspan=' . $intColumns . ' id=grp_' . $group_id . '_' . $group_count . '><img src=' . Application::get('pict_filler', '') . ' width=9 height=9 style=margin-left:24px;margin-right:4px;>' . Server::htmlEncode(Html::fixUnicode($oGroep3Val)) . '</td></TR>';
                $strGroupValue3 = $oGroep3Val;
            }
        }
        $group_count = $group_count + 1;
        echo '<TR id=grp_' . $group_id . '_' . $group_count . ' class=' . $strCurrentColor . ' valign=top>';
        if (!$strGroupField1 == "") {
            echo '<td><span class=noexport style=font-weight:normal;font-size:var(--font-size-2xs);color:#666666>' . $i . '</span></td>';
        }

        // Get current record ID for edit URL and sub-reports (case-insensitive lookup)
        $currentId = null;
        if (isset($currentRow[$idFieldName])) {
            $currentId = $currentRow[$idFieldName];
        } elseif (isset($currentRow[$idFieldNameLower])) {
            $currentId = $currentRow[$idFieldNameLower];
        }
        $currentIdVal = is_object($currentId) && method_exists($currentId, 'value') ? $currentId->value() : $currentId;

        if ($strEditURL != '') {
            $sFullEditUrl = str_replace('[ID]', $currentIdVal . '', $strEditURL);
            echo '<TD><A class="icon editicon" href="' . Server::htmlEncode($sFullEditUrl) . '" title="' . Server::htmlEncode($lang_tb_edit) . '"></A></TD>';
        }
        $sSingleRecord = '';
        for ($a = 0; $a < $fieldCount; $a++) {
            if (!isset($currentRow[$a])) continue;
            $fld = $currentRow[$a];
            // Use fieldNames array to get field name
            $fld_name = isset($fieldNames[$a]) ? strtolower($fieldNames[$a]) : '';
            if ($fld_name != strtolower($strGroupField1 ?? '') && $fld_name != strtolower($strGroupField2 ?? '') && $fld_name != strtolower($strGroupField3 ?? '') && $fld_name != $idFieldNameLower && $fld_name != 'guid' && $fld_name != strtolower($strFilterIDField ?? '') && $fld_name != strtolower($strFilterDisplayField ?? '')) {
                $sSingleRecord .= Lib_dbNiceFieldValue($fld, '', false);
            }
        }
        echo $sSingleRecord . '</TR>';

        // PERFORMANCE FIX: Use pre-fetched sub-report data instead of N queries
        if (!empty($subReportData) && $currentIdVal !== null) {
            foreach ($subReportDefs as $subIndex => $subDef) {
                $parentField = $subDef['parentField'];
                // Check if we have pre-fetched data for this parent ID
                if (isset($subReportData[$subIndex][$currentIdVal]) && !empty($subReportData[$subIndex][$currentIdVal])) {
                    $subRows = $subReportData[$subIndex][$currentIdVal];
                    echo '<TR><TD colspan=' . $intColumns . '><TABLE cellpadding=1 cellspacing=1 style="width:100%;background-color:#dddddd">';
                    foreach ($subRows as $subRow) {
                        echo '<TR valign=top><TD width=1% >&nbsp;</TD>';
                        $subFieldCount = count($subRow);
                        for ($a = 0; $a < $subFieldCount; $a++) {
                            $subFld = $subRow[$a];
                            $subFldName = is_object($subFld) && method_exists($subFld, 'name') ? strtolower($subFld->name() ?? '') : '';
                            // Skip parent field column
                            if ($subFldName != strtolower($parentField)) {
                                if ($subFieldCount == 1) {
                                    $intPerc = 100;
                                } else {
                                    $intPerc = floor(99 / ($subFieldCount - 1));
                                }
                                echo Html::fixUnicode(Lib_dbNiceFieldValue($subFld, $intPerc . '%', false));
                            }
                        }
                        echo '</TR>';
                    }
                    echo '</TABLE></TD></TR>';
                }
            }
        }
    }
    $oGroep1 = null;
    $oGroep2 = null;
    $oGroep3 = null;
    // Close lib-table wrapper if used
    if ($useLibTable) {
        echo '</tbody></TABLE></lib-table></div>';
    } else {
        echo '</tbody></TABLE></div>';
    }
}
// Export tot Word function (actually uses HTML format)
// Idea : Steal the xls and rename it to .doc?
/**
* Wordexportrs
*
*/
function WordExportRS($rs, $rsSubs, $strTitle)
{
    $strFileName = "";
    $oContent = null;
    $fld = null;
    $a = null;
    $intColumns = 0;
    $blnVertical = $rs->Fields->count > 6;
    // more than 12 fields will give problems for MS Word, vertical orientation is better
    $fso = null;
    $f = null;
    $blnUseTable = null;
    $blnSkipEmpty = null;
    $intPerc = 0;
    $strGroupField1 = "";
    $strGroupField2 = "";
    $strGroupField3 = "";
    $strGroupValue1 = '';
    $strGroupValue2 = '';
    $strGroupValue3 = '';
    // create a text file in the /cache directory
    $strFileName = Application::get('base_path', '') . 'cache/WORDreport_' . datePart('yyyy', date("Y-m-d H:i:s")) . '_' . datePart('m', date("Y-m-d H:i:s")) . '_' . datePart('d', date("Y-m-d H:i:s")) . '-' . datePart('h', date("Y-m-d H:i:s")) . '_' . datePart('n', date("Y-m-d H:i:s")) . '_' . datePart('s', date("Y-m-d H:i:s")) . '.doc';
    $blnVertical = true;
    $blnUseTable = false;
    $blnSkipEmpty = $rsRep->fields['blnWordSkipEmpty'] == true;
    // TODO: Title
    // create the titles for the file
    $intColumns = 0;
    $oContent = new StringBuffer();
    $oContent->AppendLine('<html><head><style>body{font-family:verdana;font-size:var(--font-size-2xs)}H1{font-size:var(--font-size-lg)};div,span{display:inline !important}</style></head><h1>' . $strTitle . '</H1>');
    if ($blnVertical) {
        $intColumns = 2;
    } else {
        if (!$blnUseTable) {
            $oContent->AppendLine('<table><tr valign=top>');
            foreach ($rs->fields() as $fld) {
                if ((is_null($fld->name()) ? "" : strtolower($fld->name())) != (is_null($rsRep->fields['IDField']) ? "" : strtolower($rsRep->fields['IDField']))) {
                    $oContent->AppendLine('<TH>' . $fld->name . '</TH>');
                    $intColumns = $intColumns + 1;
                }
            }
            $oContent->AppendLine('</tr>' . PHP_EOL);
        }
    }
    $strGroupField1 = $rsRep->fields['GroupField1'] . '';
    $strGroupField2 = $rsRep->fields['GroupField2'] . '';
    $strGroupField3 = $rsRep->fields['GroupField3'] . '';
    // create the contents of the file
    while (!$rs->EOF) {
        $rs_current_row = $rs->fields;
        if (!$blnVertical) {
            if ($strGroupField1 != '') {
                if ($strGroupValue1 != Arr::field($rs_current_row, $strGroupField1)) {
                    $oContent->AppendLine('<TR><TH align=left><br/>' . Server::htmlEncode(Html::fixUnicode(Arr::field($rs_current_row, $strGroupField1))) . '</TH></TR>' . PHP_EOL);
                    $strGroupValue1 = Arr::field($rs_current_row, $strGroupField1);
                }
            }
            if ($strGroupField2 != '') {
                if ($strGroupValue2 != Arr::field($rs_current_row, $strGroupField2)) {
                    $oContent->AppendLine('<TR><TH align=left>' . Server::htmlEncode(Html::fixUnicode(Arr::field($rs_current_row, $strGroupField2))) . '</TH></TR>' . PHP_EOL);
                    $strGroupValue2 = Arr::field($rs_current_row, $strGroupField2);
                }
            }
            if ($strGroupField3 != '') {
                if ($strGroupValue3 != Arr::field($rs_current_row, $strGroupField3)) {
                    $oContent->AppendLine('<TR><TH align=left>' . Server::htmlEncode(Html::fixUnicode(Arr::field($rs_current_row, $strGroupField2))) . '</TH></TR>' . PHP_EOL);
                    $strGroupValue3 = Arr::field($rs_current_row, $strGroupField3);
                }
            }
            if ($blnUseTable) {
                $oContent->AppendLine('<TABLE>');
            }
            foreach ($rs->fields() as $fld) {
                if ((is_null($fld->name()) ? "" : strtolower($fld->name())) != (is_null($rsRep->fields['IDField']) ? "" : strtolower($rsRep->fields['IDField'])) && (is_null($fld->name()) ? "" : strtolower($fld->name())) != (is_null($strGroupField1) ? "" : strtolower($strGroupField1)) && (is_null($fld->name()) ? "" : strtolower($fld->name())) != (is_null($strGroupField2) ? "" : strtolower($strGroupField2)) && (is_null($fld->name()) ? "" : strtolower($fld->name())) != (is_null($strGroupField3) ? "" : strtolower($strGroupField3))) {
                    if (!$blnSkipEmpty && $fld == '' || is_null($fld)) {
                        if ($blnUseTable) {
                            $oContent->AppendLine('<TR valign=top><TD>');
                        }
                        $oContent->AppendLine('<B>' . $fld->Name . '</B>');
                        if ($blnUseTable) {
                            $oContent->AppendLine('</TD><TD>');
                        } else {
                            $oContent->AppendLine('<BR>');
                        }
                        $oContent->AppendLine(Html::fixUnicode(Lib_dbNiceFieldValue($fld, '', $blnVertical)));
                        if ($blnUseTable) {
                            $oContent->AppendLine('</TD></TR>');
                        } else {
                            $oContent->AppendLine('<BR><BR>');
                        }
                    }
                }
            }
        } else {
            if ($blnUseTable) {
                $oContent->AppendLine('<tr valign=top><td>');
            }
            foreach ($rs->fields() as $fld) {
                if (!$blnSkipEmpty && $fld == '' || is_null($fld)) {
                    if ((is_null($fld->name()) ? "" : strtolower($fld->name())) != (is_null($rsRep->fields['IDField']) ? "" : strtolower($rsRep->fields['IDField'])) && (is_null($fld->name()) ? "" : strtolower($fld->name())) != (is_null($strGroupField1) ? "" : strtolower($strGroupField1)) && (is_null($fld->name()) ? "" : strtolower($fld->name())) != (is_null($strGroupField2) ? "" : strtolower($strGroupField2)) && (is_null($fld->name()) ? "" : strtolower($fld->name())) != (is_null($strGroupField3) ? "" : strtolower($strGroupField3))) {
                        $oContent->AppendLine('</div></span></div><b>' . $fld->Name . '</b><br>' . Html::fixUnicode(str_ireplace('<TD nowrap>', '', str_ireplace('</TD>', '', str_ireplace('<TD>', '', Lib_dbNiceFieldValue($fld, '', $blnVertical))))) . '<br>');
                    }
                }
            }
            if ($blnUseTable) {
                $oContent->AppendLine('</td></tr>' . PHP_EOL);
            }
        }
        if (!$rsSubs->EOF) {
            while (!$rsSubs->EOF) {
                OpenSubReport($rs, $rsSubs);
                if (!$rsSubData->EOF) {
                    if ($blnUseTable) {
                        $oContent->AppendLine('<TR valign=top>');
                        if ($blnVertical) {
                            $oContent->AppendLine('<td><b>' . $rsSubs->fields['title'] . '</b></td>');
                        }
                        $oContent->AppendLine('<TD colspan=' . $intColumns . '><TABLE width=100% cellpadding=1 cellspacing=1>');
                    } else {
                        $oContent->AppendLine('<b>' . $rsSubs->fields['title'] . '</b><br>');
                    }
                    while (!$rsSubData->EOF) {
                        if ($blnUseTable) {
                            $oContent->AppendLine('<TR valign=top>');
                            if (!$blnVertical) {
                                $oContent->AppendLine('<TD width=1% >&nbsp;</TD>');
                            }
                        }
                        // Handle both associative arrays and ADO-style collections
                        $subFields = Arr::isArray($rsSubData->fields) ? $rsSubData->fields : [];
                        if (!Arr::isArray($rsSubData->fields)) {
                            for ($a = 0; $a < $rsSubData->fields->count; $a++) {
                                $subFields[$a] = $rsSubData->fields[$a];
                            }
                        }
                        $subDataFieldCount = count($subFields);
                        $parentFieldName = $rsSubs->fields['parentField'] ?? '';
                        foreach ($subFields as $fieldKey => $fieldValue) {
                            // skip Parent Field
                            $fieldNameLower = is_string($fieldKey) ? strtolower($fieldKey) : '';
                            if ($fieldNameLower !== strtolower($parentFieldName)) {
                                if ($subDataFieldCount == 1) {
                                    $intPerc = 100;
                                } else {
                                    $intPerc = floor(99 / $subDataFieldCount - 1);
                                }
                                $oContent->AppendLine(Lib_dbNiceFieldValue($fieldValue, $intPerc . '%', false));
                            }
                        }
                        if ($blnUseTable) {
                            $oContent->AppendLine('</TR>');
                        } else {
                            $oContent->AppendLine('<BR/>');
                        }
                    }
                    if ($blnUseTable) {
                        $oContent->AppendLine('</TABLE></TD></TR>' . PHP_EOL);
                    }
                }
                CloseSubReport();
            }
        }
        if ($blnUseTable) {
            echo '</table>';
        }
        if ($blnVertical) {
            $oContent->AppendLine("<br clear=all style='page-break-before:always'>");
        }
        $rs->MoveNext();
    }
    $oContent->AppendLine('</html>');
    $oContent->SaveToFile(Server::mapPath($strFileName));
    echo '<script>window.location=\'' . $strFileName . '\';</script>';
}
// Export tot Excel function (actually uses CSV format)
/**
* Excelexportrs
*
*/
function ExcelExportRS($rs, $rsSubs, $bCSV)
{
    global $rsRep;

    try {
        // Determine which fields to skip
        $skipFields = array_filter(array_map('trim', explode(',', ($rsRep->fields['IDField'] ?? '') . ',' . ($rsRep->fields['FilterIDField'] ?? '') . ',' . ($rsRep->fields['FilterDisplayField'] ?? ''))));

        // Convert recordset to array data
        $data = [];
        $headers = [];
        $headersSet = false;

        while (!$rs->EOF) {
            $row = [];
            foreach ($rs->fields as $key => $value) {
                if (is_numeric($key)) continue; // Skip numeric indices
                if (in_array($key, $skipFields)) continue;
                if (!$headersSet) {
                    $headers[] = $key;
                }
                $row[$key] = $value;
            }
            $headersSet = true;
            $data[] = $row;
            $rs->MoveNext();
        }

        if (empty($data)) {
            echo 'Geen gegevens gevonden om te exporteren.';
            return;
        }

        // Use ReportExporter for download
        if ($bCSV) {
            ReportExporter::downloadCSV($data, $headers, 'rapport');
        } else {
            ReportExporter::downloadExcel($data, $headers, 'rapport');
        }
    } catch (\Throwable $e) {
        error_log('[reportdetails.php] ExcelExportRS failed: ' . $e->getMessage());
        echo '<lib-message type="error">Fout bij exporteren: ' . htmlspecialchars($e->getMessage()) . '</lib-message>';
    }
}
// Schrijf naar een bestand
// TODO: : Library function for this (HIG replace e.d)
/**
* Writetofile
*
*/
function WriteToFile($filename, $strContents)
{
    $result = @file_put_contents($filename, $strContents);
    return ($result !== false) ? 1 : 0;
}
try {
    main();
} catch (\Throwable $e) {
    $isDeveloper = SecurityHelper::isDeveloper();
    $cleanMessage = Database::cleanErrorMessage($e->getMessage());
    $repId = Request::queryInt('RepID');

    if ($isDeveloper && $repId) {
        // Developer error view with edit options
        $reportData = ReportsService::getById($repId);
        $reportJson = $reportData ? json_encode($reportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '{}';
        $reportSql = htmlspecialchars($reportData['query'] ?? '', ENT_QUOTES, 'UTF-8');

        echo '<div class="report-error-dev" style="padding:16px;max-width:1200px">';
        echo '<lib-message type="error">' . htmlspecialchars($cleanMessage, ENT_QUOTES, 'UTF-8') . '</lib-message>';

        echo '<div style="margin-top:16px">';
        echo '<h3 style="margin:0 0 8px">Rapport #' . $repId . ' — Developer tools</h3>';

        // Tab buttons
        echo '<div style="margin-bottom:8px">';
        echo '<button class="btn btn-secondary" id="btnTabSql" onclick="showTab(\'sql\')" style="margin-right:4px">SQL bewerken</button>';
        echo '<button class="btn btn-secondary" id="btnTabJson" onclick="showTab(\'json\')">JSON bewerken</button>';
        echo '</div>';

        // SQL tab
        echo '<div id="tabSql">';
        echo '<label style="font-weight:bold;display:block;margin-bottom:4px">SQL Query</label>';
        echo '<textarea id="reportSql" style="width:100%;height:200px;font-family:monospace;font-size:13px;padding:8px;border:1px solid #ccc;border-radius:4px;resize:vertical">' . $reportSql . '</textarea>';
        echo '<div style="margin-top:8px">';
        echo '<button class="btn btn-primary" onclick="saveSql()">SQL opslaan</button>';
        echo '</div>';
        echo '</div>';

        // JSON tab
        echo '<div id="tabJson" style="display:none">';
        echo '<label style="font-weight:bold;display:block;margin-bottom:4px">Rapport definitie (JSON)</label>';
        echo '<textarea id="reportJson" style="width:100%;height:350px;font-family:monospace;font-size:13px;padding:8px;border:1px solid #ccc;border-radius:4px;resize:vertical">' . htmlspecialchars($reportJson, ENT_QUOTES, 'UTF-8') . '</textarea>';
        echo '<div style="margin-top:8px">';
        echo '<button class="btn btn-primary" onclick="saveJson()">JSON opslaan</button>';
        echo '</div>';
        echo '</div>';

        // Download and status
        echo '<div style="margin-top:16px;padding-top:12px;border-top:1px solid #ddd">';
        echo '<button class="btn btn-secondary" onclick="downloadReports()"><span class="lnr lnr-download2"></span> Download reports.json</button>';
        echo '<span id="saveStatus" style="margin-left:12px;display:none"></span>';
        echo '</div>';
        echo '</div>';
        echo '</div>';

        // JavaScript for tabs, save, download
        echo '<script>
(function() {
    "use strict";
    var reportId = ' . $repId . ';

    window.showTab = function(tab) {
        document.getElementById("tabSql").style.display = tab === "sql" ? "" : "none";
        document.getElementById("tabJson").style.display = tab === "json" ? "" : "none";
        document.getElementById("btnTabSql").className = "btn " + (tab === "sql" ? "btn-primary" : "btn-secondary");
        document.getElementById("btnTabJson").className = "btn " + (tab === "json" ? "btn-primary" : "btn-secondary");
    };

    function showStatus(msg, isError) {
        var el = document.getElementById("saveStatus");
        el.style.display = "inline";
        if (isError) {
            el.textContent = msg;
            el.style.color = "#c00";
        } else {
            el.innerHTML = msg + " <a href=\"reportdetails.php?RepID=" + reportId + "\" style=\"color:#080;font-weight:bold\">Rapport herladen &rarr;</a>";
            el.style.color = "#080";
        }
    }

    window.saveSql = function() {
        var sql = document.getElementById("reportSql").value.trim();
        if (!sql) { showStatus("SQL mag niet leeg zijn", true); return; }
        fetch("api/report-definition.php?action=update", {
            method: "POST",
            headers: {"Content-Type": "application/json"},
            body: JSON.stringify({action: "update", id: reportId, query: sql})
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                showStatus("SQL opgeslagen.", false);
            } else {
                showStatus("Fout: " + (data.error || "Onbekende fout"), true);
            }
        })
        .catch(function(err) { showStatus("Fout: " + err.message, true); });
    };

    window.saveJson = function() {
        var jsonStr = document.getElementById("reportJson").value.trim();
        var def;
        try {
            def = JSON.parse(jsonStr);
        } catch(e) {
            showStatus("Ongeldige JSON: " + e.message, true);
            return;
        }
        fetch("api/report-definition.php?action=update", {
            method: "POST",
            headers: {"Content-Type": "application/json"},
            body: JSON.stringify({action: "update", id: reportId, definition: def})
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                showStatus("JSON opgeslagen.", false);
            } else {
                showStatus("Fout: " + (data.error || "Onbekende fout"), true);
            }
        })
        .catch(function(err) { showStatus("Fout: " + err.message, true); });
    };

    window.downloadReports = function() {
        window.open("api/report-definition.php?action=download", "_blank");
    };

    // Default to SQL tab active
    showTab("sql");
})();
</script>';
    } else {
        // Non-developer: show clean error message
        echo '<div style="padding:16px">';
        echo '<lib-message type="error">' . htmlspecialchars($cleanMessage, ENT_QUOTES, 'UTF-8') . '</lib-message>';
        echo '</div>';
    }
    echo '</body></html>';
}
?>
