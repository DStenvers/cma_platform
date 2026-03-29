<?php
use App\Library\Application;
use App\Library\Arr;
use App\Library\Database;
use App\Library\Request;
use App\Library\SQL;
use App\Library\Server;
use App\Library\Str;


// - lib_FormValRequired( )
// - lib_FormValRequiredBinary( UploadRequest )
// - lib_FormValidEmail( myEmail )
define("WOORDENFILTER_REPLACE_TEXT", '***************************************************');
// ====================================================================================== Form validation routines
/**
* Lib Formcreateuniquecode
*
*/
function lib_FormCreateUniqueCode()
{
    $sUniekeCode = lib_CreateGUID();
    // check if not exists
    lib_FormCheckUniqueCodeTabel();
    if (Database::getFieldValue('data', 'SELECT count(*) as Aantal from ' . FORM_UNIQUE_CODE_TABLE . ' where ' . FORM_UNIQUE_CODE_FLDNAME . '=' . SQL::postString($sUniekeCode), 'aantal') > 0) {
        $sUniekeCode = lib_FormCreateUniqueCode();
    }
    return $sUniekeCode;
}
/**
* Lib Formreplaceuniquecode
*
*/
function lib_FormReplaceUniqueCode($sContent)
{
    return str_ireplace('{UNIQUE_CODE}', '<input type="hidden" name="' . FORM_UNIQUE_CODE_FLDNAME . '" class="lib_nosave" value="' . lib_FormCreateUniqueCode() . '" />' . PHP_EOL, $sContent);
}
/**
* Lib Formcheckuniquecode
*
*/
function lib_FormCheckUniqueCode()
{
    $sUniekeCode = substr(Request::post(FORM_UNIQUE_CODE_FLDNAME, ''), 0, max(0, min(254, strlen(Request::post(FORM_UNIQUE_CODE_FLDNAME, '')))));
    $bRetval = true;
    if ($sUniekeCode != '') {
        lib_FormCheckUniqueCodeTabel();
        if (Database::getFieldValue('data', 'SELECT count(*) as Aantal from ' . FORM_UNIQUE_CODE_TABLE . ' where ' . FORM_UNIQUE_CODE_FLDNAME . '=' . SQL::postString($sUniekeCode), 'aantal') > 0) {
            $bRetval = false;
        } else {
            if (!Database::execute('INSERT INTO ' . FORM_UNIQUE_CODE_TABLE . ' (' . FORM_UNIQUE_CODE_FLDNAME . ') VALUES (' . SQL::postString($sUniekeCode) . ')', [])) {
            }
        }
    }
    return $bRetval;
}
/**
* Lib Formcheckuniquecodetabel
*
*/
function lib_FormCheckUniqueCodeTabel()
{
    if (!Database::tableExists('data', FORM_UNIQUE_CODE_TABLE)) {
        if (!Database::execute('CREATE TABLE ' . FORM_UNIQUE_CODE_TABLE . ' (ID AUTOINCREMENT PRIMARY KEY, ' . FORM_UNIQUE_CODE_FLDNAME . ' varchar(254))', [])) {
        }
    }
}
// Test all required fields (who's name start with required- or were mentioned in the required field)
// returns formatted htmlcode with errors found
// Language = VBscript
/**
* Lib Formvalrequired
*
*/
function lib_FormValRequired()
{
    $strError = '';
    $flddescr = null;
    $fld = null;
    $strRequiredField = "";
    $blnRequired = null;
    $arrRequired = array();
    $intTeller = 0;
    $strRequiredField = Request::post('required', '') . '';
    if ($strRequiredField != '') {
        if ((($pos = stripos($strRequiredField, ';', max(0, 1 - 1))) !== false ? $pos + 1 : 0)) {
            $arrRequired = explode(';', $strRequiredField);
        } else {
            $arrRequired = explode(',', $strRequiredField);
        }
    }
    Request::postAll();
    foreach (Request::postAll() as $fld) {
        $blnRequired = (is_null(substr($fld, 0, max(0, min(9, strlen($fld))))) ? "" : strtolower(substr($fld, 0, max(0, min(9, strlen($fld)))))) == 'required-';
        if (!$blnRequired) {
            if (Arr::isArray($arrRequired)) {
                for ($intTeller = 0; $intTeller <= (count($arrRequired) - 1); $intTeller++) {
                    if ((is_null(arrRequired($intTeller)) ? "" : strtolower(arrRequired($intTeller))) == (is_null($fld) ? "" : strtolower($fld))) {
                        $blnRequired = true;
                    }
                }
            }
        }
        if ($blnRequired) {
            if (trim(Request::post($fld, '') . '') == '') {
                if (Request::post($fld . '__label', '') != '') {
                    $flddescr = Request::post($fld . '__label', '');
                } else {
                    if ((is_null(substr($fld, 0, max(0, min(9, strlen($fld))))) ? "" : strtolower(substr($fld, 0, max(0, min(9, strlen($fld)))))) == 'required-') {
                        $flddescr = substr($fld, max(0, 10 - 1));
                    } else {
                        $flddescr = $fld;
                    }
                    $flddescr = str_replace('_', ' ', $flddescr);
                }
                if (Application::get('mod_language', '') == 'UK') {
                    $strError .= "<li>'<b>" . $flddescr . "</b>' is required</li>";
                } else {
                    $strError .= "<li>Het veld '<b>" . $flddescr . "</b>' is niet ingevuld</li>";
                }
            }
        }
    }
    return $strError;
}
// compatibility
/**
* Fu Valrequired
*
*/
function fu_valrequired()
{
    return lib_FormValRequired();
}
// Same as above : For Binary forms
/**
* Lib Formvalrequiredbinary
*
*/
function lib_FormValRequiredBinary($UploadRequest)
{
    $strError = '';
    $objfld = null;
    foreach ($UploadRequest as $objfld) {
        if ((is_null(substr($objfld, 0, max(0, min(9, strlen($objfld))))) ? "" : strtolower(substr($objfld, 0, max(0, min(9, strlen($objfld)))))) == 'required-' && trim(GetItem($objfld) . '') == '') {
            if (Application::get('mod_language', '') == 'UK') {
                $strError .= "<li>'<b>" . substr($objfld, max(0, 10 - 1)) . "</b>' is required</li>";
            } else {
                $strError .= "<li>Het veld '<b>" . substr($objfld, max(0, 10 - 1)) . "</b>' is niet ingevuld</li>";
            }
        }
    }
    return $strError;
}
// This function will return a Boolean indicating whether the email address provided is valid
/**
* Lib Formvalidemail
*
*/
function lib_FormValidEmail($myEmail)
{
    $bRetval = true;
    $pattern = "/^[a-zA-Z0-9.!#$%&'*+\\/=?^_`{|}~-]+@[A-Za-z0-9]+([_\\.-]+[A-Za-z0-9]+)*\\.([A-Za-z]){2,13}$/";
    $arremails = Arr::splitAlways($myEmail, ';');
    for ($t = 0; $t <= (count($arremails) - 1); $t++) {
        $email = Str::trim($arremails[$t]);
        if ($email !== '') {
            $bRetval = $bRetval && preg_match($pattern, $email);
        }
    }
    return $bRetval;
}
// Compatibility
/**
* Fu Validemail
*
*/
function fu_ValidEmail($strEmail)
{
    return lib_FormValidEmail($strEmail);
}
// This function will return an error string whether a string is a valid date (hard-coded format dd/mm/jjjj)
/**
* Lib Formvaliddate
*
*/
function lib_FormValidDate($sTheDate)
{
    $sRetval = '';
    if (!is_null($sTheDate)) {
        if (!(strtotime($sTheDate) !== false) || (is_null($sTheDate) ? 0 : strlen($sTheDate)) != 10 || substr($sTheDate, max(0, 3 - 1), 1) != '/' || substr($sTheDate, max(0, 6 - 1), 1) != '/') {
            $sRetval = 'Ongeldige datum opgegeven: gebruik het formaat dd/mm/jjjj.';
        }
    }
    return $sRetval;
}
// Validates a Dutch BurgerServiceNummer
/**
* Lib Valid Bsn
*
*/
function lib_valid_BSN($sBNSNummer)
{
    $number = 0;
    // method: (9*A + 8*B + 7*C + 6*D + 5*E + 4*F + 3*G + 2*H + (-1*I)) % 11 == 0 (where A = first digit, B is second digit etc.)
    if ((is_null($sBNSNummer) ? 0 : strlen($sBNSNummer)) == 8) {
        return true;
    } else {
        if ((is_null($sBNSNummer) ? 0 : strlen($sBNSNummer)) == 9 && is_numeric($sBNSNummer) && $sBNSNummer != '000000000') {
            $number = 9 * intval(round(floatval(substr($sBNSNummer, max(0, 1 - 1), 1)))) + 8 * intval(round(floatval(substr($sBNSNummer, max(0, 2 - 1), 1)))) + 7 * intval(round(floatval(substr($sBNSNummer, max(0, 3 - 1), 1)))) + 6 * intval(round(floatval(substr($sBNSNummer, max(0, 4 - 1), 1)))) + 5 * intval(round(floatval(substr($sBNSNummer, max(0, 5 - 1), 1)))) + 4 * intval(round(floatval(substr($sBNSNummer, max(0, 6 - 1), 1)))) + 3 * intval(round(floatval(substr($sBNSNummer, max(0, 7 - 1), 1)))) + 2 * intval(round(floatval(substr($sBNSNummer, max(0, 8 - 1), 1)))) + -1 * intval(round(floatval(substr($sBNSNummer, max(0, 9 - 1), 1))));
            return $number % 11 == 0;
        } else {
            return false;
        }
    }
}
// Validates a single IP address
/**
* Lib Formvalidipaddress
*
*/
function lib_FormValidIPAddress($strIPAddress)
{
    $arrAddress = array();
    $n = null;
    $bRetval = true;
    $arrAddress = Arr::splitAlways(STRIPADDRESS, '.');
    if (Arr::isArray($arrAddress)) {
        for ($n = 0; $n <= (count($arrAddress) - 1); $n++) {
            if ($arrAddress[$n]!= '') {
                if (lib_FormValidNumber($arrAddress[$n])) {
                    if (intval(round(floatval($arrAddress[$n]))) < 0 || intval(round(floatval($arrAddress[$n]))) > 255) {
                        $bRetval = false;
                    }
                } else {
                    $bRetval = false;
                }
            } else {
                $bRetval = false;
            }
        }
        $bRetval = $bRetval && (count($arrAddress) - 1) == 3;
    }
    return $bRetval;
}
// Validates a single numerical value
/**
* Lib Formvalidnumber
*
*/
function lib_FormValidNumber($sNumber)
{
    return is_numeric($sNumber);
}
// Validates a postal code, both Belgian and Dutch
/**
* Lib Formvalidpostalcode
*
*/
function lib_FormValidPostalcode($cPostcode)
{
    $bRetval = false;
    $cCijfers = null;
    $cAlpha = null;
    if ((is_null(substr($cPostcode, 0, max(0, min(1, strlen($cPostcode))))) ? "" : strtoupper(substr($cPostcode, 0, max(0, min(1, strlen($cPostcode)))))) == 'B') {
        $bRetval = true;
    } else {
        $cPostcode = Str::trim(str_replace(' ', '', $cPostcode));
        if ((is_null($cPostcode) ? 0 : strlen($cPostcode)) == 6) {
            $cCijfers = substr($cPostcode, 0, max(0, min(4, strlen($cPostcode))));
            $cAlpha = substr($cPostcode, max(0, strlen($cPostcode) - 2));
            if (is_numeric($cCijfers) && !is_numeric($cAlpha)) {
                $bRetval = true;
            }
        }
    }
    return $bRetval;
}
// Validates a ;-delimted list of IP adresses
/**
* Lib Formvalidipaddresses
*
*/
function lib_FormValidIPAddresses($strIPAddresses)
{
    $arrAddresses = array();
    $n = null;
    $bRetval = true;
    $arrAddresses = Arr::splitAlways(STRIPADDRESSES, ';');
    if (Arr::isArray($arrAddresses)) {
        for ($n = 0; $n <= (count($arrAddresses) - 1); $n++) {
            $bRetval = $bRetval && lib_FormValidIPAddress($arrAddresses[$n]);
        }
    }
    return $bRetval;
}
// Validates a Dutch BurgerServiceNummer
/**
* Lib Formvalid Bsn
*
*/
function lib_FormValid_BSN($sBNSNummer)
{
    $number = 0;
    // method: (9*A + 8*B + 7*C + 6*D + 5*E + 4*F + 3*G + 2*H + (-1*I)) % 11 == 0 (where A = first digit, B is second digit etc.)
    if ((is_null($sBNSNummer) ? 0 : strlen($sBNSNummer)) == 9 && is_numeric($sBNSNummer) && $sBNSNummer != '000000000') {
        $number = 9 * intval(round(floatval(substr($sBNSNummer, max(0, 1 - 1), 1)))) + 8 * intval(round(floatval(substr($sBNSNummer, max(0, 2 - 1), 1)))) + 7 * intval(round(floatval(substr($sBNSNummer, max(0, 3 - 1), 1)))) + 6 * intval(round(floatval(substr($sBNSNummer, max(0, 4 - 1), 1)))) + 5 * intval(round(floatval(substr($sBNSNummer, max(0, 5 - 1), 1)))) + 4 * intval(round(floatval(substr($sBNSNummer, max(0, 6 - 1), 1)))) + 3 * intval(round(floatval(substr($sBNSNummer, max(0, 7 - 1), 1)))) + 2 * intval(round(floatval(substr($sBNSNummer, max(0, 8 - 1), 1)))) + -1 * intval(round(floatval(substr($sBNSNummer, max(0, 9 - 1), 1))));
        return $number % 11 == 0;
    } else {
        return false;
    }
}
// Validates a guid (both with { and without } )
/**
* Lib Formvalid Guid
*
*/
function lib_FormValid_GUID($sGUID)
{
    $pattern = '/^\\{?[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}\\}?$/i';
    return (bool) preg_match($pattern, $sGUID);
}
/**
* Function to filter words according to a specified text-file
*/
/**
* Lib Formwoordenfilter
*
*/
function lib_FormWoordenfilter($sFieldValue, $sFilenameToFilter)
{
    $sCacheName = 'woordenfilter_' . $sFilenameToFilter;
    $strWoorden = "";
    $arrWoorden = array();
    $arr_tel = array();
    $ScriptObject = null;
    $myfile = null;
    if (!Arr::isArray(Application::get($sCacheName, ''))) {
        $ScriptObject = new FileSystemObject();
        if (is_object($ScriptObject)) {
            if ($ScriptObject->FileExists(Server::mapPath(Application::get('base_path', '') . $sFilenameToFilter))) {
                $myfile = $ScriptObject->OpenTextFile(Server::mapPath(Application::get('base_path', '') . $sFilenameToFilter), 1, false);
                if (is_object($myfile)) {
                    $strWoorden = $myfile->ReadAll();
                    $myfile = null;
                }
            }
        }
        $ScriptObject = null;
        $arrWoorden = explode(PHP_EOL, $strWoorden);
    } else {
        $arrWoorden = Application::get($sCacheName, '');
    }
    for ($arr_tel = 0; $arr_tel <= (count($arrWoorden) - 1); $arr_tel++) {
        $sFieldValue = str_ireplace(trim($arrWoorden[$arr_tel]), substr(WOORDENFILTER_REPLACE_TEXT, 0, max(0, min((is_null(trim($arrWoorden[$arr_tel])) ? 0 : strlen(trim($arrWoorden[$arr_tel]))), strlen(WOORDENFILTER_REPLACE_TEXT)))), $sFieldValue);
    }
    return $sFieldValue;
}
// Passes on fields of a form post in hidden fields; skipping fields specified by a comma delimited list
/**
* Lib Formpasson
*
*/
function lib_FormPassOn($strSkipFlds)
{
    $fld = null;
    $arrSkip = array();
    $sVal = null;
    $arrSkip = Arr::splitAlways($strSkipFlds, ',');
    Request::postAll();
    foreach (Request::postAll() as $fld) {
        if (!Arr::findTrimmed($arrSkip, $fld) > -1) {
            $sVal = Str::trim(Request::post($fld, ''));
            if ($sVal!= '') {
                $sVal = str_ireplace("'", '&apos;', str_ireplace(chr(34), '&quot;', lib_HTML_CharacterCompile($sVal)));
                echo '<input type=hidden name="' . $fld . '" value="' . $sVal . '">';
            }
        }
    }
}
/**
* Lib Form Cleaninputname
*
*/
function lib_Form_CleanInputName($strInput)
{
    $iPos = 1;
    $valid_char = '_abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890/-?';
    $cur_chr = null;
    $strOutput = '';
    $strInput = str_replace('<BR />', '', $strInput);
    $strInput = str_replace(' ', '_', $strInput);
    $strInput = str_replace('�', 'e', $strInput);
    $strInput = str_replace('�', 'i', $strInput);
    $strInput = str_replace('�', 'o', $strInput);
    $strInput = str_replace('&euml;', 'e', $strInput);
    $strInput = HTMLDecompile($strInput);
    while ($iPos <= (is_null($strInput) ? 0 : strlen($strInput))) {
        $cur_chr = substr($strInput, max(0, $iPos - 1), 1);
        if ((($pos = stripos($valid_char, $cur_chr, max(0, 1 - 1))) !== false ? $pos + 1 : 0) > 0) {
            $strOutput .= $cur_chr;
        }
        $iPos = $iPos + 1;
    }
    $strOutput = str_ireplace('__uur', '', $strOutput);
    $strOutput = str_ireplace('__', '_', $strOutput);
    return $strOutput;
}
?>
