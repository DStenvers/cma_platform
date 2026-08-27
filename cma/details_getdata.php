<?php
// UTF-8 support by adding a codepage tag above
use App\Library\Database;
use App\Library\Debug;
use App\Library\Request;
use App\Library\Response;
use App\Library\SQL;
use Cma\CmaRepository;

require_once __DIR__ . '/bootstrap.inc';

Response::noCache();
// select2 documentation: http://ivaynberg.github.io/select2/
$parFormID = Request::queryInt('FormID');
$parControl = Request::query('ControlName', '');
$parQuery = Request::query('q', '');
$bControlFound = false;
$parID = Request::queryInt('ID');
$arrRep = array();
$intRec = 0;
$comboSQL = null;
$sFieldSpec = null;
$arrFlds = array();
$testSQL = null;
$testRS = null;
$Myconn = null;
Debug::setActive(false);
// otherwise it will disturb the layout
echo '{"data":[' . PHP_EOL;
if ($parFormID != '') {
    $arrRep = GetFormDef($parFormID);
    if (!Arr::isArray($arrRep)) {
        GetData_VisibleError('Form (' . $parFormID . ') does not contain any enabled controls!');
        exit();
    } else {
        for ($intRec = 0; $intRec <= (count($arrRep) - 1); $intRec++) {
            if ((is_null($arrRep[Q_FIELDNAME][$intRec]) ? "" : strtolower($arrRep[Q_FIELDNAME][$intRec])) == (is_null($parControl) ? "" : strtolower($parControl))) {
                $bControlFound = true;
                if ($arrRep[Q_DATABASEID][$intRec]) {
                    $Myconn = CmaRepository::getResolvedConnectionString((int)$arrRep[Q_DATABASEID][$intRec]);
                } else {
                    $Myconn = CmaRepository::getResolvedConnectionString((int)$arrRep[Q_FKDATABASE][$intRec]);
                }
                if (is_null($arrRep[Q_SQLLIST][$intRec])) {
                    $comboSQL = 'select ' . $arrRep[Q_FOREIGNIDFIELD][$intRec] . ',' . $arrRep[Q_CTRLIDFIELD][$intRec] . ' from ' . $arrRep[Q_SOURCETABLE][$intRec] . ' order by ' . $arrRep[Q_FOREIGNIDFIELD][$intRec];
                } else {
                    $comboSQL = str_replace('[ID]', '[ProdID]', $arrRep[Q_SQLLIST][$intRec]);
                    $comboSQL = str_replace('[ProdID]', $parID, $comboSQL);
                }
                if ($parQuery != '') {
                    // What does the user search in? The column they SEE - the label column
                    // from the SqlList. That was only half-implemented: the label was only
                    // extracted when the SqlList aliased it as "as <fieldname>". Without an
                    // alias the code fell back on the control's own FIELD NAME, which is a
                    // column of the PARENT table, not of the source table. Access answers
                    // that with "No value given for one or more required parameters", so the
                    // user got an error card and an alert mail instead of a filtered list.
                    // Seen on form 245, control fkVADocument: its SqlList reads
                    // "SELECT ID, Naam FROM tblVADocument ..." - no alias - so typing "der"
                    // produced "fkVADocument LIKE '%der%'".
                    //
                    // The label column now comes out of the SELECT list either way: up to
                    // " as <fieldname>" when that alias is there, up to FROM otherwise. The
                    // ID column is stripped off; what is left is what the user searches in.
                    $sFieldSpec = '';
                    if (!$arrRep[Q_DATABASEID][$intRec]
                        || stripos($comboSQL, ' as ' . $arrRep[Q_FOREIGNIDFIELD][$intRec]) === false) {
                        $sFieldSpec = SQL::labelColumn(
                            $comboSQL,
                            $arrRep[Q_CTRLIDFIELD][$intRec],
                            $arrRep[Q_SOURCETABLE][$intRec],
                            $arrRep[Q_FOREIGNIDFIELD][$intRec]
                        );
                    }
                    if ($sFieldSpec !== '') {
                        $testSQL = SQL::addWhere($comboSQL, $sFieldSpec . " LIKE '%" . $parQuery . "%'");
                        $testSQL = SQL::processSQL($Myconn, $testSQL);
                        // The trial run is the safety net, and it is the reason this does not
                        // rethrow: if the guess does not run, the filter simply stays out and
                        // the list shows everything. That beats an error card plus an alert
                        // mail for what is only a type-ahead.
                        try {
                            $testRS = Database::openRS($testSQL, $Myconn, adOpenForwardOnly);
                            if ($testRS !== null) {
                                $comboSQL = $testSQL;
                            }
                            $testRS = null;
                        } catch (\Throwable $e) {
                            // Guess did not hold - carry on unfiltered.
                        }
                    }
                }
                GetData_WriteRecords($Myconn, $comboSQL, $parQuery, $arrRep[Q_FOREIGNIDFIELD][$intRec], $arrRep[Q_CTRLIDFIELD][$intRec], $arrRep[Q_ISREQUIRED][$intRec]);
            }
        }
    }
    if (!$bControlFound) {
        GetData_VisibleError('Control (' . $parControl . ')  not found in form ' . $parFormID . '..');
    }
} else {
    GetData_VisibleError('Missing Form parameter');
}
echo PHP_EOL . ']}';
// Inserts the error like it was a record, so it is at least visible
/**
* Getdata Visibleerror
*
*/
function GetData_VisibleError($strError)
{
    // { "id" : 13, "text" : "Idaho" }
    echo ' { "id" : 0, "text" : ' . chr(34) . $strError . chr(34) . '}';
}
// TODO: : Check user
/**
* Getdata Writerecords
*
*/
function GetData_WriteRecords($oConn, $sSql, $parQuery, $sDisplayField, $sIDField, $bRequired)
{
    $bFirst = true;
    $strPreviousGroup = '';
    $sGroup = null;
    $strGroupName = "";
    $strDetailName = "";
    $rsCombo = Database::openRS($sSql, $oConn, adOpenForwardOnly);
    if ($rsCombo === null) {
        throw new \Exception('Database query failed: ' . Database::getLastError());
    }
    $rsCombo->MoveNext();
    // Als er een query is moet getoond worden dat het resultaat leeg is.
    if (!$bRequired && !($rsCombo_current_row === false) && $parQuery != '') {
        echo ' { "id" : 0, "text" : "" }' . PHP_EOL;
        if (!$rsCombo->EOF) {
            echo ',';
        }
    }
    if (!$rsCombo->EOF) {
        $strPreviousGroup = '';
        while (!$rsCombo->EOF) {
            $sGroup = '';
            if (false && (($pos = stripos($rsCombo->Fields[$sDisplayField] . '', '|', max(0, 1 - 1))) !== false ? $pos + 1 : 0) > 0) {
                $strGroupName = trim(substr($rsCombo->Fields[$sDisplayField] . '', 0, max(0, min((($pos = stripos($rsCombo->Fields[$sDisplayField] . '', '|', max(0, 1 - 1))) !== false ? $pos + 1 : 0) - 1, strlen($rsCombo->Fields[$sDisplayField] . '')))));
                $strDetailName = substr($rsCombo->Fields[$sDisplayField] . '', max(0, (($pos = stripos($rsCombo->Fields[$sDisplayField] . '', '|', max(0, 1 - 1))) !== false ? $pos + 1 : 0) + 1 - 1));
                if ($strPreviousGroup != $strGroupName) {
                    $bFirst = true;
                    if ($strPreviousGroup != '') {
                        $sGroup .= ']},';
                    }
                    $sGroup .= '{ "text": "' . $strGroupName . '", "children": [';
                    $strPreviousGroup = $strGroupName;
                }
            } else {
                $strGroupName = '';
                $strDetailName = $rsCombo->Fields[$sDisplayField] . '';
                if ($strPreviousGroup != '') {
                    $sGroup .= ']}';
                    $strPreviousGroup = '';
                }
            }
            echo $sGroup;
            $strDetailName = str_ireplace(chr(9), ' ', $strDetailName);
            if ($parQuery == '' || (($pos = stripos($strDetailName, $parQuery, max(0, 1 - 1))) !== false ? $pos + 1 : 0)) {
                if ($bFirst) {
                    $bFirst = false;
                } else {
                    echo ',' . PHP_EOL;
                }
                echo '{ "id" : ' . $rsCombo->Fields[$sIDField] . ', "text" : ' . chr(34) . $strDetailName . chr(34) . ' }';
            }
            $rsCombo->MoveNext();
        }
        if ($strPreviousGroup != '') {
            echo ']}';
        }
    }
}
?>
