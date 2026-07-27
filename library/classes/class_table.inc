<?php
use App\Library\Application;
use App\Library\Arr;
use App\Library\Database;
use App\Library\Html;
use App\Library\Request;
use App\Library\Server;
use App\Library\Str;

$Table_CtrlID = 0;
// Data: you can either set the SQL and the Connection, of the Recordset
// Link: Set the LinkUrl or LinkTarget or set the OnClick ([ID] will be replaced by the actual value)
// 2do : use the more powerfull filtering option
if (class_exists('LibTable')) { return; }
#[\AllowDynamicProperties]
class LibTable
{
    // VBScript exposed public Property Let/Get/Set accessors (SQL, Recordset,
    // Connection, RowsPerPage, ShowIDField, ...) that stored into the private
    // Table* fields. The ASP→PHP conversion dropped those Property methods, so
    // callers doing `$oTable->SQL = $sql` / `$oTable->SQL()` / `$oTable->Recordset = $rs`
    // hit undefined-method / dynamic-property errors and the table never got its
    // SQL or recordset. These magic methods restore the accessors: public name
    // (case-insensitive) -> internal Table* field, preserving the ASP side effects.
    private const PROP_MAP = [
        'sql' => 'TableSQL', 'recordset' => 'TableRS', 'connection' => 'TableConnection',
        'rowsperpage' => 'TableRowsPerPage', 'activerowid' => 'TableActiveRowID',
        'fieldcaptions' => 'TableFieldCaptions', 'defaultsortcol' => 'TableDefaultSortCol',
        'idfield' => 'TableIDField', 'groupfield' => 'TableGroupField',
        'showcaptions' => 'TableShowCaptions', 'showidfield' => 'TableShowIDField',
        'attribtable' => 'TableAttrib', 'attribtd' => 'TableTDAttrib',
        'attribtr' => 'TableTRAttrib', 'fieldstyles' => 'TableStyles',
        'linkbaseurl' => 'TableLinkBaseURL', 'onclick' => 'TableOnClick',
        'filtered' => 'TableFiltered', 'name' => 'TableName',
        'emptymessage' => 'TableEmptyMessage',
        // RowClassField: veld waarvan de waarde de CSS-klasse van de RIJ wordt in plaats
        // van een eigen kolom. Rapporten die hun <tr> zelf schreven markeerden zo de
        // uitzonderingen ("kanniet", "voorlopig"); zonder dit konden ze niet naar deze
        // klasse over. FieldFilters: per kolom de waarde van data-filter op de <th>
        // ('N' = geen filterkeuzelijst, voor icoon-/actiekolommen).
        'rowclassfield' => 'TableRowClassField', 'fieldfilters' => 'TableFieldFilters',
    ];

    private function _setProp(string $name, $value): void
    {
        $key = strtolower($name);
        if (!isset(self::PROP_MAP[$key])) { $this->$name = $value; return; }
        $field = self::PROP_MAP[$key];
        if ($key === 'activerowid') { $value = $value . ''; }
        $this->$field = $value;
        if ($key === 'recordset') { $this->TableExternalRS = true; }
        if ($key === 'filtered' && $value) { $this->TableSortable = false; }
    }

    public function __set($name, $value) { $this->_setProp($name, $value); }

    public function __get($name)
    {
        $key = strtolower($name);
        return isset(self::PROP_MAP[$key]) ? ($this->{self::PROP_MAP[$key]} ?? null) : ($this->$name ?? null);
    }

    public function __call($name, $args)
    {
        // Property Get used as a method call, e.g. $oTable->SQL(), or a
        // Property Let used as a method call, e.g. $oTable->ShowIDField(false).
        $key = strtolower($name);
        if (isset(self::PROP_MAP[$key])) {
            if (count($args) === 0) { return $this->{self::PROP_MAP[$key]} ?? null; }
            $this->_setProp($name, $args[0]);
            return null;
        }
        throw new \BadMethodCallException('LibTable::' . $name . '() is not defined');
    }

    public $TableTRColor1;
    public $TableTRColor2;
    public $TableTRActiveColor;
    public $TableTRHoverColor;
    public $TableRS;
    /** Veld dat de rij-klasse levert (leeg = uit). Wordt niet als kolom getoond. */
    public $TableRowClassField = '';
    /** data-filter per kolomindex, bv. [0 => 'N', 1 => 'N']. */
    public $TableFieldFilters = [];
    private $TableID;
    private $TableName = '';
    private $TableSQL;
    private $TableIDField;
    private $TableGroupField;
    private $TableActiveRowID;
    private $TableConnection;
    private $TableLinkBaseURL = '';
    private $TableLinkTarget = '';
    private $TableFieldCaptions = [];
    private $TableDefaultSortCol;
    private $TableRowsPerPage;
    private $TablePage;
    private $TableStyles;
    private $TableEmptyMessage;
    private $TableAttrib;
    private $TableTRClass;
    private $TableTHAttrib;
    private $TableTDAttrib;
    private $TableShowCaptions;
    private $TableShowIDField;
    private $TableFieldWidths = [];
    private $TableExternalRS;
    private $TableOnClick;
    private $TableDeleteField;
    private $TableDeleteAction;
    private $TableDeleteClassname;
    private $TableSortable;
    private $TableFiltered;
    /**
    * Render
    *
    */

    /**
     * data-filter voor de kop van kolom $kolom, of '' als er niets is opgegeven.
     * 'N' zet de filterkeuzelijst uit — op een icoon- of actiekolom valt niets te filteren.
     */
    private function _filterAttrib($kolom): string
    {
        if (!Arr::isArray($this->TableFieldFilters) || !isset($this->TableFieldFilters[$kolom])) {
            return '';
        }
        return ' data-filter="' . Server::htmlEncode((string)$this->TableFieldFilters[$kolom]) . '"';
    }

    /**
     * Extra CSS-klasse voor de huidige rij, uit RowClassField. Leeg als die eigenschap niet
     * gebruikt wordt. Alleen letters/cijfers/koppeltekens: een gegevenswaarde mag nooit een
     * attribuut de <TR> in schrijven.
     */
    private function _rijKlasse(): string
    {
        if ($this->TableRowClassField == '') {
            return '';
        }
        $waarde = trim((string)($this->TableRS->fields[$this->TableRowClassField] ?? ''));
        if ($waarde == '') {
            return '';
        }
        return ' ' . preg_replace('/[^A-Za-z0-9_\- ]/', '', $waarde);
    }

    /** Velden die gegevens dragen maar geen eigen kolom zijn (nu alleen RowClassField). */
    private function _isVerborgenVeld($naam): bool
    {
        return $this->TableRowClassField != ''
            && strtolower((string)$naam) === strtolower((string)$this->TableRowClassField);
    }

    public function Render()
    {
        global $Table_CtrlID;
        $fld = null;
        $curID = null;
        $x = null;
        $fldTel = null;
        $blnShow = null;
        $intRow = 0;
        $blnPrevious = null;
        $blnNext = null;
        $blnAbort = null;
        $strColor = "";
        $sNiceField = null;
        $value = null;
        $curAttrib = null;
        if (is_object($this->TableRS)) {
            $this->TableExternalRS = true;
        }
        if (!$this->TableExternalRS) {
            $this->TableRS = Database::openRS($this->TableSQL, $this->TableConnection, adOpenForwardOnly);
            if ($this->TableRS === null) {
                throw new \Exception('Database query failed: ' . Database::getLastError());
            }
            $this->TableRS->MoveNext();
        }
        if ($this->TableRS->eof && $this->TableEmptyMessage != '') {
            echo $this->TableEmptyMessage;
        } else {
            if ($Table_CtrlID == 1 && $this->TableSortable) {
                if ($this->TableShowCaptions) {
                    echo '<script src="' . Application::get('base_path', '') . 'library/classes/class_tablesort.js"></script>';
                }
                echo '<script src="' . Application::get('base_path', '') . 'library/classes/class_tablescroll.js"></script>';
            }
            echo '<script>var intActiveRowID=' . ($this->TableActiveRowID != '' ? $this->TableActiveRowID : '-1') . ';' . PHP_EOL . 'function row_act' . $Table_CtrlID . '(id){' . PHP_EOL . 'if (intActiveRowID!=id){' . PHP_EOL . '
            if (intActiveRowID!=-1){' . PHP_EOL . "    var row=document.getElementById('lt_row_' + intActiveRowID.toString());" . PHP_EOL . "
                if (row) {row.className='" . $this->TableTRClass . "'}" . PHP_EOL . '  }' . PHP_EOL . '}' . PHP_EOL . "row=document.getElementById('lt_row_' + id.toString() );
                " . PHP_EOL . 'if (row) {' . PHP_EOL . "  row.className='" . $this->TableTRClass . "_active';
                " . PHP_EOL . '  intActiveRowID=id;' . PHP_EOL . '}' . PHP_EOL . '}' . PHP_EOL;
                echo 'function row_hover' . $Table_CtrlID . '(id, bOn){' . PHP_EOL . '
                if (id!=intActiveRowID) {' . PHP_EOL . "    var x=document.getElementById('lt_row_'+id.toString());" . PHP_EOL . "
                    if(x){x.className=(bOn?'" . $this->TableTRClass . "_hover':'libTableTR')}" . PHP_EOL . '  }' . PHP_EOL . '}</script>' . PHP_EOL;
                    echo '<lib-table resizable>';
                    echo'<TABLE data-name="' . substr(str_replace('?','', str_replace('/','', $this->TableName)), 0, max(0, min(30, strlen(str_replace('?','', str_replace('/','', $this->TableName)))))) . chr(34) . ' ' . $this->TableAttrib .($this->TableID !='' ?' id="' . $this->TableID . chr(34) : ($this->TableSortable ?' id=sorttable' . $Table_CtrlID :'')) .' onselectstart="return(false)"><THEAD><TR>';
                    if ($this->TableShowCaptions) {
                        if ($this->TableDeleteField != '') {
                            echo '<TH ' . $this->TableTHAttrib . '></TH>';
                        }
                        if (Arr::isArray($this->TableFieldCaptions) && count($this->TableFieldCaptions) > 0) {
                            for ($x = 0; $x <= (count($this->TableFieldCaptions) - 1); $x++) {
                                echo '<TH nowrap ' . $this->TableTHAttrib . $this->_filterAttrib($x) . '>' . Server::htmlEncode($this->TableFieldCaptions[$x]) . '</TH>';
                            }
                        } else {
                            $kopTel = 0;
                            // No explicit captions -> auto-generate headers from the
                            // field names (TableFieldCaptions defaults to [], which is
                            // Arr::isArray()==true but empty, so this fallback was skipped).
                            foreach ($this->TableRS->Fields() as $fld) {
                                if ($this->_isVerborgenVeld($fld->name())) { continue; }
                                if ((!!$this->TableShowIDField || (is_null($fld->name()) ? "" : strtolower($fld->name())) != (is_null($this->TableIDField) ? "" : strtolower($this->TableIDField))) && (is_null($fld->name()) ? "" : strtolower($fld->name())) != (is_null($this->TableGroupField) ? "" : strtolower($this->TableGroupField)) && (is_null($fld->name()) ? "" : strtolower($fld->name())) != (is_null($this->TableDeleteField) ? "" : strtolower($this->TableDeleteField))) {
                                    $sNiceField = Str::firstUpper(str_replace('_', ' ', $fld->name()));
                                    echo '<TH nowrap ' . $this->TableTHAttrib . $this->_filterAttrib($kopTel) . '>' . Server::htmlEncode($sNiceField) . '</TH>';
                                    $kopTel++;
                                }
                            }
                        }
                    }
                    echo '</TR></THEAD><TBODY>';
                    $intRow = 0;
                    $blnPrevious = false;
                    $blnNext = false;
                    $blnAbort = false;
                    $curAttrib = $this->TableTDAttrib . '1';
                    while (!$this->TableRS->EOF && !$blnAbort) {
                        $intRow = $intRow + 1;
                        $blnShow = true;
                        if ($this->TableRowsPerPage != -1) {
                            if ($intRow < $this->TablePage - 1 * $this->TableRowsPerPage + 1) {
                                $blnPrevious = true;
                                $blnShow = false;
                            }
                            if ($intRow > $this->TablePage * $this->TableRowsPerPage) {
                                $blnNext = true;
                                $blnShow = false;
                                $blnAbort = true;
                            }
                        }
                        if ($blnShow) {
                            $curID = $this->TableRS->Fields[$this->TableIDField];
                            echo '<TR ID="lt_row_' . $curID . '" class="' . $this->TableTRClass . $this->_rijKlasse() . '" onmouseOver="row_hover' . $Table_CtrlID . '(' . $curID . ',true)" onmouseOut="row_hover' . $Table_CtrlID . '(' . $curID . ',false)" onclick="row_act' . $Table_CtrlID . '(' . $curID . ');' . str_replace('[ID]', $curID, $this->TableOnClick) . '">';
                            $fldTel = 0;
                            if ($this->TableDeleteField != '') {
                                if ($this->TableRS->fields[$this->TableDeleteField]!= '0') {
                                    $curAttrib = $this->TableTDAttrib . '_deleted"';
                                }
                                echo '<td ' . $curAttrib . ' ><a href="' . $this->TableDeleteAction . $curID . '"><img src="library/images/formbtn_del.gif"></a></td>';
                            }
                            foreach ($this->TableRS->Fields() as $fld) {
                                if ($this->_isVerborgenVeld($fld->name())) { continue; }
                                if ((!!$this->TableShowIDField || (is_null($fld->name()) ? "" : strtolower($fld->name())) != (is_null($this->TableIDField) ? "" : strtolower($this->TableIDField))) && (is_null($fld->name()) ? "" : strtolower($fld->name())) != (is_null($this->TableGroupField) ? "" : strtolower($this->TableGroupField)) && (is_null($fld->name()) ? "" : strtolower($fld->name())) != (is_null($this->TableDeleteField) ? "" : strtolower($this->TableDeleteField))) {
                                    $raw = (string)$fld;
                                    // <sort:KEY> marker: sets this cell's sort key
                                    // (honoured by <lib-table> via the data-sort
                                    // attribute) and is itself NOT displayed.
                                    $sortKey = null;
                                    if (preg_match('/<sort:([^>]*)>/i', $raw, $mSort)) {
                                        $sortKey = $mSort[1];
                                        $raw = str_replace($mSort[0], '', $raw);
                                    }
                                    echo '<TD ' . $curAttrib;
                                    if (Arr::isArray($this->TableFieldWidths) && isset($this->TableFieldWidths[$fldTel])) {
                                        echo ' width=' . $this->TableFieldWidths[$fldTel];
                                    }
                                    if (Arr::isArray($this->TableStyles) && isset($this->TableStyles[$fldTel])) {
                                        echo ' style="' . $this->TableStyles[$fldTel] . chr(34);
                                    }
                                    if ($sortKey !== null) {
                                        echo ' data-sort="' . Server::htmlEncode($sortKey) . '"';
                                    }
                                    echo '>';
                                    if ($raw === '') {
                                        echo '&nbsp;';
                                    } elseif (substr($raw, 0, 6) === '<html>') {
                                        // Raw-HTML cell: strip the <html> marker and emit the
                                        // inner content unencoded. Checked on the RAW value so
                                        // fixUnicode can't mangle the marker.
                                        echo substr($raw, 6);
                                    } else {
                                        echo Server::htmlEncode(Lib_FixDateValue(Html::fixUnicode($raw)));
                                    }
                                    echo '</TD>';
                                }
                                $fldTel = $fldTel + 1;
                            }
                            echo '</TR>' . PHP_EOL;
                            if ($curAttrib == $this->TableTDAttrib . '1') {
                                $curAttrib = $this->TableTDAttrib . '2';
                            } else {
                                $curAttrib = $this->TableTDAttrib . '1';
                            }
                        }
                        // Advance the cursor each iteration (the ASP->PHP conversion
                        // dropped the RS.MoveNext at the end of the Do-While body, so
                        // the loop re-rendered the first row until it hit RowsPerPage).
                        $this->TableRS->MoveNext();
                    }
                    echo '</TBODY>';
                    if ($blnPrevious || $blnNext) {
                        echo '<TFOOT><TR class=sortbottom><TH nowrap ' . $this->TableTHAttrib . ' colspan=99>';
                        $this->WriteNavButton('nav_rwnd.gif', $this->TablePage - 1, $blnPrevious);
                        $this->WriteNavButton('nav_fwd.gif', $this->TablePage + 1, $blnNext);
                        echo '</TH></TR></TFOOT>';
                    }
                    echo '</TABLE>';
                    echo '</lib-table>';
                    if ($this->TableDefaultSortCol != -1 && $this->TableSortable) {
                        echo '<script>sortables_initial(' . $this->TableDefaultSortCol . ');</script>' . PHP_EOL;
                    }
                }
                if (!$this->TableExternalRS) {
                    $this->TableRS = null;
                }
            }
            /**
            * Deletesettings
            *
            */
            public function DeleteSettings($sField, $sAction, $sClass)
            {
                $this->TableDeleteField = $sField;
                $this->TableDeleteAction = $sAction;
                $this->TableDeleteClassname = $sClass;
            }
            /**
            * Writenavbutton
            *
            */
            public function WriteNavButton($cImg, $ToPage, $blnActive)
            {
                if ($blnActive) {
                    echo ' <A href="' . Request::addToURL('', 'TablePage', $ToPage) . '">';
                }
                echo '<img src=' . Application::get('base_path', '') . 'library/images/' . $cImg . ' width=17 height=16 hspace=3 ';
                if ($blnActive) {
                    echo 'border=0></a>';
                } else {
                    echo 'style=filter:alpha(opacity=50)>';
                }
            }
            /**
            *   Construct
            */
            public function __construct()
            {
                global $Table_CtrlID;
                $this->TableID = '';
                // generated
                $this->TableSQL = '';
                $this->TableIDField = 'ID';
                $this->TableGroupField = '';
                $this->TableActiveRowID = '';
                $this->TableConnection = 'data';
                $this->TableDefaultSortCol = -1;
                $this->TableRowsPerPage = 50;
                if (Request::query('TablePage', '') != '') {
                    $this->TablePage = intval(round(floatval(substr(Request::queryInt('TablePage'), 0, max(0, min(4, strlen(Request::queryInt('TablePage'))))))));
                } else {
                    $this->TablePage = 1;
                }
                $this->TableDeleteField = '';
                $this->TableDeleteAction = '';
                $this->TableDeleteClassname = '';
                // layout propertiues
                $this->TableTRColor1 = '#FFFFEE';
                $this->TableTRColor2 = '#FFFFFF';
                $this->TableAttrib = 'cellpadding="4" cellspacing="0" width="100%" class="libTable blockselect"';
                $this->TableTRClass = 'libTableTR';
                $this->TableTDAttrib = 'class=libTableTD';
                $this->TableTHAttrib = 'class=libTableTH';
                $this->TableShowCaptions = true;
                $this->TableShowIDField = true;
                $this->TableStyles = '';
                $this->TableExternalRS = false;
                $this->TableOnClick = '';
                $this->TableSortable = true;
                $this->TableFiltered = false;
                $this->TableEmptyMessage = 'Geen gegevens om weer te geven';
                $Table_CtrlID = $Table_CtrlID + 1;
            }
            /**
            *   Destruct
            */
            public function __destruct()
            {
                // Cleanup (if any)
            }
        }
?>
