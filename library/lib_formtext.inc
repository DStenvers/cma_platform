<?php
// TODO: : Re-organize this stuff:
// create a generic processor for form generated data (array : name/value) -> lib_FormFieldList 2nd edition!
// Quick fix until reorganisation is complete!
use App\Library\Application;
use App\Library\Arr;
use App\Library\Html;
use App\Library\Request;
use App\Library\Server;

define("CONST_FIELDNAMEACHTERNAAM", 'achternaam');
define("LIB_FORM2XML_XMLOBJECT", 'MSXML2.FreeThreadedDOMDocument.4.0');
// deze splitst de lijst op zodat de gegevens opgesplitst worden in mee te sturen gegevens en een tabel die weg kan worden gehaald tbv privacy.
$lib_formtext_sensitive_fields = '';
$lib_formtext_ip_info = false;
$lib_formtext_exclude_fields = '';
$lib_formtext_skip_empty = true;
$lib_formtext_skip_fields = 'action,nextpage,unique_code,hp_value,wachtwoord,login,loginnaam';
// Build a html table containing the fields of a form (excluding internally used)
// (optionally with IP address, see function lib_FormText_IP_information below)
/**
* Lib Formtext
*
*/
function lib_FormText($strHeader, $strFooter)
{
    global $lib_formtext_exclude_fields, $lib_formtext_ip_info, $lib_formtext_sensitive_fields, $strUserAgent;
    $strHTMLBody = "";
    $arrExclude = array();
    $fieldorder = lib_FormFieldList();
    $arrExclude = Arr::splitAlways(($lib_formtext_exclude_fields != '' ? $lib_formtext_exclude_fields . ';' : '') . str_replace(',', ';', $lib_formtext_sensitive_fields) . '', ';');
    $strHTMLBody = internal_lib_FormText($fieldorder, $arrExclude, true);
    if ($lib_formtext_sensitive_fields != '') {
        $strHTMLBody .= '<br>' . internal_lib_FormText($fieldorder, $arrExclude, false);
    }
    return ($strHeader != '' ? $strHeader . '<BR><BR>' : '') . $strHTMLBody . ($strFooter != '' ? '<BR>' . $strFooter : '') . ($lib_formtext_ip_info ? '<BR><font size=-2>ip: ' . Request::server('REMOTE_ADDR', '') . ' | Browser: ' . $strUserAgent : '');
}
/**
* Lib Formhoneypot Write
*
*/
function lib_FormHoneyPot_Write()
{
    echo '<input type=email name=hp_value style=height:0px;width:0px;padding:0px;border:0px>';
}
/**
* Lib Formhoneypot Check
*
*/
function lib_FormHoneyPot_Check()
{
    if (Request::post('hp_value', '')!= '') {
        exit();
    }
}
/**
* Internal Lib Formtext
*
*/
function internal_lib_FormText($fieldorder, $arrExclude, $bFirstPart)
{
    global $lib_formtext_skip_empty, $lib_formtext_skip_fields;
    $fld = null;
    $strfieldname = "";
    $strVeld = "";
    $strValue = "";
    $name = null;
    $strHTMLBody = '<table cellpadding=0 cellspacing=0>';
    $bInclude = null;
    $arrSkip = Arr::splitAlways((is_null($lib_formtext_skip_fields) ? "" : strtolower($lib_formtext_skip_fields)), ',');
    foreach ($fieldorder as $name) {
        $strVeld = $name;
        $strValue = Request::post($name, '');
        if ((is_null(substr($strVeld, 0, max(0, min(9, strlen($strVeld))))) ? "" : strtolower(substr($strVeld, 0, max(0, min(9, strlen($strVeld)))))) == 'required-') {
            $strVeld = substr($strVeld, max(0, 10 - 1));
        }
        if ((is_null(substr($strVeld, 0, max(0, min(8, strlen($strVeld))))) ? "" : strtolower(substr($strVeld, 0, max(0, min(8, strlen($strVeld)))))) == 'required') {
            $strVeld = substr($strVeld, max(0, 9 - 1));
        }
        $bInclude = Arr::findTrimmed($arrSkip, $strVeld) == -1;
        if ($bInclude) {
            if ($bFirstPart && Arr::isArray($arrExclude)) {
                $bInclude = Arr::findTrimmed($arrExclude, $strVeld) == -1;
            }
            if (!$bFirstPart) {
                $bInclude = Arr::findTrimmed($arrExclude, $strVeld) > -1;
            }
        }
        if ($bInclude) {
            if (Request::post($strVeld . '__label', '') != '') {
                $strVeld = Request::post($strVeld . '__label', '');
            }
            if (Request::post($strVeld . '_' . Request::post($name, '') . '__label', '') != '') {
                $strValue = Html::fixUnicode(Request::post($strVeld . '_' . Request::post($name, '') . '__label', ''));
            }
            $strVeld = (is_null(substr($strVeld, 0, max(0, min(1, strlen($strVeld))))) ? "" : strtoupper(substr($strVeld, 0, max(0, min(1, strlen($strVeld)))))) . substr($strVeld, max(0, 2 - 1));
            $strVeld = str_replace('_', ' ', $strVeld);
            if ($lib_formtext_skip_empty && $strValue == '') {
            } else {
                $strHTMLBody .= '<tr valign=top><td style=padding-right:8px ' . ((is_null($strVeld) ? 0 : strlen($strVeld)) < 24 ? 'nowrap' : '') . '>' . $strVeld . '</td><td>' . (Application::get('infomailer_spaar_inkt', '') ? '' : '<b>') . HTMLCompile(str_ireplace(PHP_EOL, '<br>', Server::htmlEncode(HTMLDecompile(lib_HTML_CharacterCompile($strValue))))) . '</td></tr>';
            }
        }
    }
    return $strHTMLBody . '</table>';
}
// This function will create a text description of all fields posted from a form with IP address
/**
* Lib Formtext Ip Information
*
*/
function lib_FormText_IP_information($blnInclude)
{
    $lib_formtext_ip_info = $blnInclude;
}
// This function will create a list of fields to exclude
/**
* Lib Formtext Exclude
*
*/
function lib_FormText_Exclude($strExclude)
{
    $lib_formtext_exclude_fields = $strExclude;
}
// This function will create a list of fields to skip entirily
/**
* Lib Formtext Skip
*
*/
function lib_FormText_Skip($strSkip)
{
    $lib_formtext_skip_fields = $strSkip;
}
// This function will create a list of fields to exclude
/**
* Lib Formtext Sensitive
*
*/
function lib_FormText_Sensitive($strSensitive)
{
    $lib_formtext_sensitive_fields = $strSensitive;
}
/**
 * Converts form data to XML format
 *
 * @param string $cFormType The form type identifier
 * @param string $cFormVersion The form version
 * @return string The XML content
 */
function lib_FormtoXML($cFormType, $cFormVersion)
{
    // Create a new DOM document
    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->preserveWhiteSpace = true;
    $dom->formatOutput = true;

    // Create root element and append to document
    $root = $dom->createElement('form');
    $dom->appendChild($root);

    // Append type element
    $typeEl = $dom->createElement('type', htmlspecialchars($cFormType));
    $root->appendChild($typeEl);

    // Append version element
    $versionEl = $dom->createElement('version', htmlspecialchars($cFormVersion));
    $root->appendChild($versionEl);

    // Get all POST data once
    $formdata = Request::postAll();

    // Variables for name splitting
    $strTussenvoegsel = '';
    $strAchternaam = '';

    // Iterate through form fields
    foreach ($formdata as $fieldName => $fieldValue) {
        // Skip button fields (containing 'btn')
        if (stripos($fieldName, 'btn') !== false) {
            continue;
        }

        // Process field name - remove 'required' prefix
        $strfieldname = $fieldName;
        if (strtolower(substr($strfieldname, 0, 8)) === 'required') {
            $strfieldname = substr($strfieldname, 8);
            if (substr($strfieldname, 0, 1) === '-') {
                $strfieldname = substr($strfieldname, 1);
            }
        }

        // Create field element with name attribute
        $field = $dom->createElement('field');
        $field->setAttribute('name', strtolower($strfieldname));

        // Handle array values (e.g., checkboxes with same name)
        if (Arr::isArray($fieldValue)) {
            $fieldValue = implode(', ', $fieldValue);
        }

        // Clean the value
        $cleanValue = HTMLDecompile(lib_HTML_CharacterCompile($fieldValue));

        // Special handling for 'achternaam' field - split into surname and prefix
        if (strtolower($strfieldname) === strtolower(CONST_FIELDNAMEACHTERNAAM)) {
            lib_FormSplitsAchternaam($cleanValue, $strTussenvoegsel, $strAchternaam);

            // Set the surname value
            $field->appendChild($dom->createTextNode($strAchternaam));
            $root->appendChild($field);

            // Create additional field for 'tussenvoegsel' (prefix)
            $tussenvoegselField = $dom->createElement('field');
            $tussenvoegselField->setAttribute('name', 'tussenvoegsel');
            $tussenvoegselField->appendChild($dom->createTextNode($strTussenvoegsel));
            $root->appendChild($tussenvoegselField);
        } else {
            $field->appendChild($dom->createTextNode($cleanValue));
            $root->appendChild($field);
        }
    }

    // Return the XML string
    return $dom->saveXML();
}
// Build an array of form field names ordered as they were received
/**
* Lib Formfieldlist
*
*/
function lib_FormFieldList()
{
    $skipFields = ['to','subject','body','cc','check','css','from','font','file','nextpage','fieldorder','submit','verstuur','danktekst','bewaar','logging','required','to_name','from_name'];
    $result = [];
    foreach (Request::postAll() as $name => $value) {
        $lower = strtolower($name);
        if (substr($lower, 0, 1) === '_') continue;
        if (in_array($lower, $skipFields)) continue;
        if (str_ends_with($lower, '_check') || str_ends_with($lower, '__label') || str_ends_with($lower, '.y') || str_ends_with($lower, '.x')) continue;
        $result[] = $name;
    }
    return $result;
}
// Haalt vooraf gedefinieerde tussenvoegsel uit een achternaamveld (wanneer deze voor EN na de naam staan!)
/**
* Lib Formsplitsachternaam
*
*/
function lib_FormSplitsAchternaam($strCompletenaam, $strTussenvoegsel, $strAchternaam)
{
    $voorvoeg_teller = null;
    $arrTussenvoegsels = explode(',', "aan de,aan den,bij de,de,de la,de van de,den,du,el,het,in 't,in het,la,le,op 't,op de,op den,op het,'t,te,ten,ter,v.d,van den,van der,van het,van 't,van,vd,von,von der,der,de,di,al,ek");
    $blnLeft = false;
    $blnRight = false;
    $strNaam = trim(str_replace(',', ' ', $strCompletenaam));
    $strTussenvoegsel = '';
    $strAchternaam = $strNaam;
    for ($voorvoeg_teller = 0; $voorvoeg_teller <= (count($arrTussenvoegsels) - 1); $voorvoeg_teller++) {
        // achtervoegsel staat voor de achternaam ingevuld
        if ((is_null(substr($strNaam, 0, max(0, min((is_null($arrTussenvoegsels[$voorvoeg_teller]) ? 0 : strlen($arrTussenvoegsels[$voorvoeg_teller])) + 1, strlen($strNaam))))) ? "" : strtolower(substr($strNaam, 0, max(0, min((is_null($arrTussenvoegsels[$voorvoeg_teller]) ? 0 : strlen($arrTussenvoegsels[$voorvoeg_teller])) + 1, strlen($strNaam)))))) == (is_null($arrTussenvoegsels[$voorvoeg_teller]) ? "" : strtolower($arrTussenvoegsels[$voorvoeg_teller])) . ' ') {
            if ((is_null($arrTussenvoegsels[$voorvoeg_teller]) ? 0 : strlen($arrTussenvoegsels[$voorvoeg_teller])) > (is_null($strTussenvoegsel) ? 0 : strlen($strTussenvoegsel))) {
                $strTussenvoegsel = $arrTussenvoegsels[$voorvoeg_teller];
                $blnLeft = true;
            }
        }
        // achtervoegsel staat achter de naam ingevuld
        if ((is_null(substr($strNaam, max(0, strlen($strNaam) - (is_null($arrTussenvoegsels[$voorvoeg_teller]) ? 0 : strlen($arrTussenvoegsels[$voorvoeg_teller])) + 1))) ? "" : strtolower(substr($strNaam, max(0, strlen($strNaam) - (is_null($arrTussenvoegsels[$voorvoeg_teller]) ? 0 : strlen($arrTussenvoegsels[$voorvoeg_teller])) + 1)))) == (is_null(' ' . $arrTussenvoegsels[$voorvoeg_teller]) ? "" : strtolower(' ' . $arrTussenvoegsels[$voorvoeg_teller]))) {
            if ((is_null($arrTussenvoegsels[$voorvoeg_teller]) ? 0 : strlen($arrTussenvoegsels[$voorvoeg_teller])) > (is_null($strTussenvoegsel) ? 0 : strlen($strTussenvoegsel))) {
                $strTussenvoegsel = $arrTussenvoegsels[$voorvoeg_teller];
                $blnRight = true;
            }
        }
    }
    if ($strTussenvoegsel != '') {
        if ($blnLeft) {
            $strAchternaam = trim(substr($strNaam, max(0, (is_null($strTussenvoegsel) ? 0 : strlen($strTussenvoegsel)) + 1 - 1)));
        } else {
            $strAchternaam = trim(substr($strNaam, 0, max(0, min((is_null($strNaam) ? 0 : strlen($strNaam)) - (is_null($strTussenvoegsel) ? 0 : strlen($strTussenvoegsel)), strlen($strNaam)))));
        }
    }
    // TODO: Call libstringfirstupper
    $strAchternaam = (is_null(substr($strAchternaam, 0, max(0, min(1, strlen($strAchternaam))))) ? "" : strtoupper(substr($strAchternaam, 0, max(0, min(1, strlen($strAchternaam)))))) . substr($strAchternaam, max(0, 2 - 1));
}
?>
