<?php
use App\Library\Application;
use App\Library\Arr;
use App\Library\Database;
use App\Library\Html;
use App\Library\Request;
use App\Library\Server;

$Table_CtrlID = 0;
// Data: you can either set the SQL and the Connection, of the Recordset
// Link: Set the LinkUrl or LinkTarget or set the OnClick ([ID] will be replaced by the actual value)
// 2do : use the more powerfull filtering option
if (class_exists('LibTable')) { return; }
class LibTable
{
    public $TableTRColor1;
    public $TableTRColor2;
    public $TableTRActiveColor;
    public $TableTRHoverColor;
    public $TableRS;
    private $TableID;
    private $TableName;
    private $TableSQL;
    private $TableIDField;
    private $TableGroupField;
    private $TableActiveRowID;
    private $TableConnection;
    private $TableLinkBaseURL;
    private $TableLinkTarget;
    private $TableFieldCaptions;
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
    private $TableFieldWidths;
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
    public function Render()
    {
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
            $this->TableRS = null;
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
                    echo'<TABLE data-name="' . substr(str_replace('?','', str_replace('/','', $this->TableName)), 0, max(0, min(30, strlen(str_replace('?','', str_replace('/','', $this->TableName)))))) . chr(34) . ($this->TableFiltered ?' class=' :'') . $this->TableAttrib . ($this->TableID !='' ?' id="' . $this->TableID . chr(34) : ($this->TableSortable ?' id=sorttable' . $Table_CtrlID :'')) .' onselectstart="return(false)"><THEAD><TR>';
                    if ($this->TableShowCaptions) {
                        if ($this->TableDeleteField != '') {
                            echo '<TH ' . $this->TableTHAttrib . '></TH>';
                        }
                        if (Arr::isArray($this->TableFieldCaptions)) {
                            for ($x = 0; $x <= (count($this->TableFieldCaptions) - 1); $x++) {
                                echo '<TH nowrap ' . $this->TableTHAttrib . '>' . Server::htmlEncode($this->TableFieldCaptions[$x]) . '</TH>';
                            }
                        } else {
                            foreach ($this->TableRS->Fields() as $fld) {
                                if (!!$this->TableShowIDField && (is_null($fld->name()) ? "" : strtolower($fld->name())) == (is_null($this->TableIDField) ? "" : strtolower($this->TableIDField)) && (is_null($fld->name()) ? "" : strtolower($fld->name())) != (is_null($this->TableGroupField) ? "" : strtolower($this->TableGroupField)) && (is_null($fld->name()) ? "" : strtolower($fld->name())) != (is_null($this->TableDeleteField) ? "" : strtolower($this->TableDeleteField))) {
                                    $sNiceField = Str::firstUpper(str_replace('_', ' ', $fld->name()));
                                    echo '<TH nowrap ' . $this->TableTHAttrib . '>' . Server::htmlEncode($sNiceField) . '</TH>';
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
                            echo '<TR ID="lt_row_' . $curID . '" class="' . $this->TableTRClass . '" onmouseOver="row_hover' . $Table_CtrlID . '(' . $curID . ',true)" onmouseOut="row_hover' . $Table_CtrlID . '(' . $curID . ',false)" onclick="row_act' . $Table_CtrlID . '(' . $curID . ');' . str_replace('[ID]', $curID, $this->TableOnClick) . '">';
                            $fldTel = 0;
                            if ($this->TableDeleteField != '') {
                                if ($this->TableRS->fields[$this->TableDeleteField]!= '0') {
                                    $curAttrib = $this->TableTDAttrib . '_deleted"';
                                }
                                echo '<td ' . $curAttrib . ' ><a href="' . $this->TableDeleteAction . $curID . '"><img src="library/images/formbtn_del.gif"></a></td>';
                            }
                            foreach ($this->TableRS->Fields() as $fld) {
                                if (!!$this->TableShowIDField && (is_null($fld->name()) ? "" : strtolower($fld->name())) == (is_null($this->TableIDField) ? "" : strtolower($this->TableIDField)) && (is_null($fld->name()) ? "" : strtolower($fld->name())) != (is_null($this->TableGroupField) ? "" : strtolower($this->TableGroupField)) && (is_null($fld->name()) ? "" : strtolower($fld->name())) != (is_null($this->TableDeleteField) ? "" : strtolower($this->TableDeleteField))) {
                                    echo '<TD ' . $curAttrib;
                                    if (Arr::isArray($this->TableFieldWidths)) {
                                        echo ' width=' . $this->TableFieldWidths[$fldTel];
                                    }
                                    if (Arr::isArray($this->TableStyles)) {
                                        echo ' style="' . $this->TableStyles[$fldTel] . chr(34);
                                    }
                                    echo '>';
                                    if ($fld == "") {
                                        echo '&nbsp;';
                                    } else {
                                        $value = $fld;
                                        if ($fld->type == 11) {
                                            $value = ($fld ? 'Ja' : 'Nee');
                                        }
                                        $value = Html::fixUnicode($value);
                                        if ($fld->type == ADDATE) {
                                            if (date("Y", strtotime($value)) <= 1900) {
                                                echo lib_time($value);
                                            } else {
                                                echo (is_null(strMediumDate($value)) ? "" : strtolower(strMediumDate($value)));
                                            }
                                        } else {
                                            if (substr($value, 0, max(0, min(6, strlen($value)))) == '<html>') {
                                                echo substr($value, max(0, 7 - 1));
                                            } else {
                                                echo Server::htmlEncode(Lib_FixDateValue($value . ''));
                                            }
                                        }
                                    }
                                    echo '</A></TD>';
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
