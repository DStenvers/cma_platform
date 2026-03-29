<?php
use App\Library\Application;
use App\Library\Arr;
use App\Library\Cache;
use App\Library\Database;
use App\Library\Debug;
use App\Library\Error;
use App\Library\RecordSet;
use App\Library\Request;
use App\Library\SQL;
use App\Library\Server;
use App\Library\Str;

define("XMLSNIPPET_START", '[[Gegevensbron:');
define("XMLSNIPPET_END", ']]');
define("LIBXMLSNIPPETS_CONSTFIELDSTART", '[FIELD_REPEAT_START]');
define("LIBXMLSNIPPETS_CONSTFIELDEND", '[FIELD_REPEAT_END]');
define("CONST_XMLPOSTFIX", '_XMLobj');
define("PARAM_AUTO_RETRIEVE", 'AUTO');

$XML_SNIPPET_SETTING_RESOLVE_FIELDS = true;
$CACHE_XSLTS = Application::get('cma_XML_caching', '');

$libObjXMLDOM = null;
$libArrXMLStore = null;
$strStoreCacheID = "";
$strXMLSQL = "";
$dictParams = null;
$intSnippetLevel = 0;
// Constants for the store data array
define("STORE_DATABASE", 0);
define("STORE_QUERY", 1);
define("STORE_CUSTOMASP", 2);
define("STORE_ROOTTABLE", 3);
define("STORE_PARAM", 4);
define("STORE_MODULE", 5);
define("STORE_MODULE_NAME", 6);
define("STORE_CACHE_PREFIX", 7);
// Constants for XSLT template generation
define("XSLT_TEMPLATE_LIST", 'lijst');
$sActiveRepository = 'rep';
// ============================================================================
// XML UTILITY FUNCTIONS (moved from lib_xml.inc for consolidation)
// ============================================================================
// Coderen van een waarde naar een XML save variant
// 2do: check of dit hier beneden ook moet, of dat dit automatisch gedaan word
/**
* Lib Xmlencode
*
*/
function lib_XMLEncode($sValue)
{
    $sRetval = Str::trim($sValue . '');
    if ($sRetval != '') {
        $sRetval = str_ireplace('<', '&lt;', $sRetval);
        $sRetval = str_ireplace('>', '&gt;', $sRetval);
        $sRetval = str_ireplace(chr(34), '&quot;', $sRetval);
        $sRetval = str_ireplace("'", '&#39;', $sRetval);
        $sRetval = lib_HTML_CharacterCompile($sRetval);
        $sRetval = str_ireplace('', '&nbsp;', $sRetval);
        $sRetval = str_ireplace('&', '&amp;', $sRetval);
        $sRetval = str_ireplace('&amp;&amp;', '&amp;', $sRetval);
    }
    return $sRetval;
}
// Add's a root element to a XML DOM object in the following format: <element>value</element>
/**
* Lib Xmladdrootelement
*
*/
function lib_XMLAddRootElement($objXML, $Element, $Value)
{
    if (!($objXML instanceof DOMDocument)) {
        return;
    }
    try {
        $elementName = Str::trim($Element);
        if ($elementName == '') {
            return;
        }
        $objXMLRootField = $objXML->createElement($elementName);
        $objXMLRootField->nodeValue = lib_XMLEncode($Value);
        if ($objXML->documentElement) {
            $objXML->documentElement->appendChild($objXMLRootField);
        }
    } catch (Exception $e) {
        // Silently ignore errors like in original ASP
    }
}
// ============================================================================
// END OF MOVED FUNCTIONS
// ============================================================================
// Handle a single SML snippet
/**
* Lib Xmlsnippet
*
*/
function lib_XMLSnippet($sStore)
{
    return HandleXMLSnippets(XMLSNIPPET_START . $sStore . ((($pos = stripos($sStore, '}', max(0, 1 - 1))) !== false ? $pos + 1 : 0) > 0 ? '' : '{}') . XMLSNIPPET_END);
}
/**
* Lib Xmlsnippets Setrepository
*
*/
function lib_XMLSnippets_SetRepository($sNew)
{
    $sActiveRepository = $sNew;
}
// Preferred call
/**
* Lib Handlexmlsnippets
*
*/
function lib_HandleXMLSnippets($sHTML)
{
    return HandleXMLSnippets($sHTML);
}
// This function takes a string (typically a HTML Edit field) and replaces all references to an XML store with the actual content
/**
* Handlexmlsnippets
*
*/
function HandleXMLSnippets($sHTML)
{
    $cFullStoreSpec = null;
    $intPos = 0;
    $intEndPos = 0;
    Debug::write('HandleXMLSnippets: ' . $sHTML);
    $intPos = (($pos = stripos($sHTML, XMLSNIPPET_START, max(0, 1 - 1))) !== false ? $pos + 1 : 0);
    while ($intPos > 0) {
        $intEndPos = (($pos = stripos(substr($sHTML, max(0, $intPos + (is_null(XMLSNIPPET_START) ? 0 : strlen(XMLSNIPPET_START)) - 1)), XMLSNIPPET_END)) !== false ? $pos + 1 : 0);
        if ($intEndPos > 0) {
            $cFullStoreSpec = substr($sHTML, max(0, $intPos + (is_null(XMLSNIPPET_START) ? 0 : strlen(XMLSNIPPET_START)) - 1), $intEndPos - 1);
            $sHTML = str_ireplace(XMLSNIPPET_START . $cFullStoreSpec . XMLSNIPPET_END, GetSnippetContent((is_null($cFullStoreSpec) ? "" : strtoupper($cFullStoreSpec))) ?? '', $sHTML);
        } else {
            $sHTML = str_ireplace(XMLSNIPPET_START, '', $sHTML);
        }
        // look for the next snippet command
        $intPos = (($pos = stripos($sHTML, XMLSNIPPET_START, max(0, 1 - 1))) !== false ? $pos + 1 : 0);
    }
    // eliminate the remnants of the XSLT processor
return str_ireplace('<?xml version="1.0"?>', '', $sHTML . '');
    }
// Handle a single XML Store
/**
* Getsnippetcontent
*
*/
function GetSnippetContent($cXMLStore)
{
    global $intSnippetLevel, $libObjXMLDOM;
    $cParam = '';
    $cache_id = '';
    $total_cacheid = $cXMLStore;
    $sRetval = null;
    $nParamPos = null;
    $sPage = null;
    $objXSL = null;
    $cXSLTFile = null;
    $fso = null;
    $param = null;
    // get store name and parameters
    $nParamPos = (($pos = stripos($cXMLStore, '{', max(0, 1 - 1))) !== false ? $pos + 1 : 0);
    if ($nParamPos > 0) {
        $cParam = substr($cXMLStore, max(0, $nParamPos + 1 - 1), (is_null($cXMLStore) ? 0 : strlen($cXMLStore)) - $nParamPos - 1);
        $cXMLStore = substr($cXMLStore, 0, max(0, min($nParamPos - 1, strlen($cXMLStore))));
        $total_cacheid = $cXMLStore . '_' . $cParam;
        } else {
        $cParam = ($intSnippetLevel == 1 ? PARAM_AUTO_RETRIEVE : '');
        foreach ($_GET as $key => $param) {
            $total_cacheid .= '_param=' . Request::query($param, '');
            }
        }
    // Debug::write ("GetSnippetContent " & total_cacheid  )
    $sRetval = Cache::get($total_cacheid);
    // sRetval  = Cache::loadFile ( total_cacheid )
    if ($sRetval != '') {
        } else {
        $intSnippetLevel = $intSnippetLevel + 1;
        XMLStore_XMLRetrieve($cXMLStore, $cParam, date("Y-m-d"));
        if ($CACHE_XSLTS) {
            $cache_id = 'cache_xslobject_' . $cXMLStore;
            if (is_object(Application::get($cache_id, ''))) {
                $objXSL = Application::get($cache_id, '');
                }
            }
        $cXSLTFile = Application::get('base_path', '') . XSLT_PATH . $cXMLStore . '.xslt';
        $xsltFilePath = Server::mapPath($cXSLTFile);

        // Load XSLT using PHP DOMDocument
        if (!($objXSL instanceof DOMDocument)) {
            $objXSL = new DOMDocument();
            $objXSL->preserveWhiteSpace = false;

            // Check if file exists
            if (file_exists($xsltFilePath)) {
                $loadResult = @$objXSL->load($xsltFilePath);
                if (!$loadResult) {
                    $error = libxml_get_last_error();
                    Error::show("Error processing XSLT file '" . $cXSLTFile . "'.<BR>" .
                        'Description: ' . ($error ? $error->message : 'Unknown error') . '.<BR>');
                }
            } else {
                XMLStore_GenerateXSLT($cXMLStore, XSLT_TEMPLATE_LIST);
                Error::show($xsltFilePath . ' was not found.<BR><BR>Note: A default version was generated, press Ctrl-F5 to refresh and view the result.');
            }
        }

        // Perform XSLT transformation using PHP XSLTProcessor
        if ($objXSL instanceof DOMDocument && $libObjXMLDOM instanceof DOMDocument) {
            try {
                $xslt = new XSLTProcessor();
                $xslt->importStyleSheet($objXSL);
                $sPage = $xslt->transformToXML($libObjXMLDOM);

                if ($sPage !== false) {
                    $sRetval = str_ireplace('&amp;amp;', '&amp;', str_ireplace('&amp;', '&', str_ireplace('&lt;', '<', str_ireplace('&gt;', '>', $sPage))));
                } else {
                    $error = libxml_get_last_error();
                    Error::show("Error parsing XSLT file '" . $cXMLStore . ".xslt'.<BR>" .
                        'Description: ' . ($error ? $error->message : 'Unknown error') . '.<BR>');
                }
            } catch (Exception $e) {
                Error::show("Error parsing XSLT file '" . $cXMLStore . ".xslt'.<BR>" .
                    'Description: ' . $e->getMessage() . '.<BR>');
            }
        }
        $libObjXMLDOM = null;
        $objXSL = null;
        Cache::set($total_cacheid, $sRetval);
        Debug::write('GetSnippetContent DISK ' . $total_cacheid);
        }
    return $sRetval;
}
 
/**
* Function to generate an XSLT file bases upon a specific store
*/
// Based upon templates found in library\templates, for now only one is supported (lijst)
/**
* Xmlstore Generatexslt
*
*/
function XMLStore_GenerateXSLT($XMLStore, $XMLTTemplateType)
{
    $cTemplate = null;
    $cFieldRepeat = null;
    $cFieldContent = null;
    $cContent = null;
    $intPosStart = 0;
    $intPosEnd = 0;
    $libArrXMLStoreData = null;
    $field = null;

    $libArrXMLStoreData = XMLStore_GetStoreData($XMLStore);

    // Use native PHP file functions instead of FileSystemObject
    $cTemplate = Server::mapPath(Application::get('base_path', '') . 'library/templates/' . $XMLTTemplateType . '.xslt');

    if (file_exists($cTemplate)) {
        $cContent = file_get_contents($cTemplate);

        if ($cContent !== false) {
            // Get field names from database query
            $rs = Database::openRS($libArrXMLStoreData[STORE_QUERY], $libArrXMLStoreData[STORE_DATABASE]);
            if ($rs !== null) {
                $moduleName = isset($libArrXMLStoreData[STORE_MODULE_NAME]) ? strtolower($libArrXMLStoreData[STORE_MODULE_NAME]) : '';
                $cContent = str_ireplace('[XMLSTORE]', $moduleName . '/' . strtoupper($XMLStore), $cContent);

                if ($cContent != '') {
                    $intPosStart = stripos($cContent, LIBXMLSNIPPETS_CONSTFIELDSTART);
                    while ($intPosStart !== false) {
                        $searchStart = $intPosStart + strlen(LIBXMLSNIPPETS_CONSTFIELDSTART);
                        $intPosEnd = stripos($cContent, LIBXMLSNIPPETS_CONSTFIELDEND, $searchStart);

                        if ($intPosEnd !== false) {
                            $cFieldRepeat = substr($cContent, $searchStart, $intPosEnd - $searchStart);
                            $cFieldContent = '';

                            // Get field metadata from PDO statement
                            $columnCount = $rs->columnCount();
                            for ($i = 0; $i < $columnCount; $i++) {
                                $meta = $rs->getColumnMeta($i);
                                if ($meta) {
                                    $fieldName = strtoupper($meta['name']);
                                    $cFieldContent .= str_replace('[FIELD_NAME]', $fieldName, $cFieldRepeat);
                                }
                            }

                            $cContent = substr($cContent, 0, $intPosStart) . $cFieldContent .
                                substr($cContent, $intPosEnd + strlen(LIBXMLSNIPPETS_CONSTFIELDEND));
                        }

                        $intPosStart = stripos($cContent, LIBXMLSNIPPETS_CONSTFIELDSTART);
                    }
                }
            }
        }

        // Write output file
        $outPath = Server::mapPath(Application::get('base_path', '') . XSLT_PATH . strtolower($XMLStore) . '.xslt');
        $outDir = dirname($outPath);
        if (!is_dir($outDir)) {
            mkdir($outDir, 0777, true);
        }
        file_put_contents($outPath, $cContent);
    }
}
/**
* Function called from outside to enable skipping menu-items if data is not available
*/
/**
* Xmlstore Hasdata
*
*/
function XMLStore_HasData($strParamStoreName)
{
    return XMLStore_RecordCount($strParamStoreName, '', 0) > 0;
    }
// Telt het aantal records in een store op een bepaalde datum vanaf vandaag
/**
* Xmlstore Recordcount
*
*/
function XMLStore_RecordCount($sStore, $sParam, $dagenOffset)
{
    $retval = 0;
    $item = null;
    $subitem = null;
    $sStore = (is_null($sStore) ? "" : strtoupper($sStore));
    // fills libObjXMLDOM with the XML structure and libArrXMLStore with the store parameters!
    XMLStore_XMLRetrieve($sStore, $sParam, dateAdd('d', $dagenOffset, date("Y-m-d")));
    // seek the childnode for records
    foreach ($libObjXMLDOM->childNodes() as $item) {
        foreach ($item->childNodes() as $subitem) {
            if ((is_null($subitem->nodeName()) ? "" : strtoupper($subitem->nodeName())) == $sStore) {
                $retval = $item->childNodes->length;
                }
            }
        }
    $libObjXMLDOM = null;
    return $retval;
    }
// Ophalen naam van een store
/**
* Xmlstore Getname
*
*/
function XMLStore_GetName($intStore)
{
    $dataSources = XMLStore_LoadDataSources();
    foreach ($dataSources as $ds) {
        if (($ds['id'] ?? 0) == $intStore) {
            return $ds['name'] ?? '';
        }
    }
    return '';
}

/**
* Load data sources from JSON config (cached in static variable)
*/
function XMLStore_LoadDataSources()
{
    static $dataSources = null;
    if ($dataSources === null) {
        $configFile = __DIR__ . '/../assets/datastores/data-sources.json';
        if (!file_exists($configFile)) {
            Error::show("Data sources configuratie niet gevonden: " . $configFile);
            return [];
        }
        $data = json_decode(file_get_contents($configFile), true);
        $dataSources = $data['dataSources'] ?? [];
    }
    return $dataSources;
}

/**
* Get XMLstore data from JSON config, returns array with STORE_* indexes
*/
function XMLStore_GetStoreData($strParamStoreName)
{
    if ($strParamStoreName == "") {
        Error::show("Vereiste parameter '<b>Store</b>' ontbreekt!");
        exit();
    }
    // Find data source by name (case-insensitive)
    $dataSources = XMLStore_LoadDataSources();
    $found = null;
    $default = null;
    foreach ($dataSources as $ds) {
        if (strcasecmp($ds['name'] ?? '', $strParamStoreName) === 0) {
            $found = $ds;
            break;
        }
        if (strcasecmp($ds['name'] ?? '', 'DEFAULT') === 0) {
            $default = $ds;
        }
    }
    if ($found === null) {
        $found = $default;
    }
    if ($found === null) {
        Error::show("Store '<b>" . $strParamStoreName . "</b>' niet gevonden in data-sources.json en er is geen store met de naam DEFAULT gevonden.<BR>NB: Deze parameter is niet hoofdletter-gevoelig!");
        return null;
    }
    // Map to STORE_* indexed array (matches old tblXMLStore query column order)
    $dbName = $found['database'] ?? 'data';
    $connectionString = '';
    if ($dbName !== 'data') {
        // For non-data databases, resolve the connection string from databases.json
        $dbConfigFile = __DIR__ . '/../cma/config/databases.json';
        if (file_exists($dbConfigFile)) {
            $dbData = json_decode(file_get_contents($dbConfigFile), true);
            foreach ($dbData['databases'] ?? [] as $db) {
                if (($db['name'] ?? '') === $dbName) {
                    $connectionString = $db['connectionString'] ?? '';
                    break;
                }
            }
        }
    }
    return [
        STORE_DATABASE     => $connectionString,
        STORE_QUERY        => $found['query'] ?? '',
        STORE_CUSTOMASP    => '',
        STORE_ROOTTABLE    => '',
        STORE_PARAM        => false,
        STORE_MODULE       => $found['moduleId'] ?? 0,
        STORE_MODULE_NAME  => $found['module'] ?? '',
        STORE_CACHE_PREFIX => $found['cachePrefix'] ?? '',
    ];
}
// Internal function to handle parameters centralised
// blnSaveOnly, no impact on the query, just save in the parameter collection
/**
* Handleparam
*
*/
function HandleParam($param, $expression, $Value, $blnSaveOnly)
{
    global $dictParams, $strStoreCacheID, $strXMLSQL;
    $objXMLRootField = null;
    Debug::write('HandleParam : ' . $param . ' ' . $expression . ' ' . $Value);
    // X en Y are for image submits (method=Get) , gclid lead to problems with RINO  with an external link from a Google AdWords; a simular problem occured with _utm parameters; also from Google
    // fb_source, fwcc, fwcl are from facebook.
    // goback is from Linkedin
    $blnSaveOnly = $blnSaveOnly || (is_null($param) ? "" : strtolower($param)) == 'ref' || substr((is_null($param) ? "" : strtolower($param)), 0, max(0, min(4, strlen((is_null($param) ? "" : strtolower($param)))))) == 'utm_' || substr((is_null($param) ? "" : strtolower($param)), 0, max(0, min(6, strlen((is_null($param) ? "" : strtolower($param)))))) == 'goback' || substr((is_null($param) ? "" : strtolower($param)), 0, max(0, min(4, strlen((is_null($param) ? "" : strtolower($param)))))) == 'code' || substr((is_null($param) ? "" : strtolower($param)), 0, max(0, min(5, strlen((is_null($param) ? "" : strtolower($param)))))) == 'gclid' || substr((is_null($param) ? "" : strtolower($param)), 0, max(0, min(4, strlen((is_null($param) ? "" : strtolower($param)))))) == 'fwcc' || substr((is_null($param) ? "" : strtolower($param)), 0, max(0, min(4, strlen((is_null($param) ? "" : strtolower($param)))))) == 'fwcl' || substr((is_null($param) ? "" : strtolower($param)), 0, max(0, min(3, strlen((is_null($param) ? "" : strtolower($param)))))) == 'fb_' || (is_null($param) ? "" : strtolower($param)) == 'color' || (is_null($param) ? "" : strtolower($param)) == 'x' || (is_null($param) ? "" : strtolower($param)) == 'y' || (is_null($param) ? "" : strtolower($param)) == 'store' || (is_null($param) ? "" : strtolower($param)) == 'search_for' || (is_null($param) ? "" : strtolower($param)) == 'search_in';
    if (!$blnSaveOnly) {
        $Value = str_ireplace('%2C', ',', $Value);
        if ((($pos = stripos($param, ' and ', max(0, 1 - 1))) !== false ? $pos + 1 : 0) > 0 || (($pos = stripos($param, ' or ', max(0, 1 - 1))) !== false ? $pos + 1 : 0) > 0) {
            $strXMLSQL = SQL::addWhere($strXMLSQL, $param);
            $strStoreCacheID = $strStoreCacheID . $param;
            } else {
            if ($Value != '') {
                $strStoreCacheID .= $param . '_' . $expression . '_' . $Value;
                Debug::write('strStoreCacheID=' . $strStoreCacheID);
                if (substr((is_null($param) ? "" : strtolower($param)), 0, max(0, min(4, strlen((is_null($param) ? "" : strtolower($param)))))) != 'page') {
                    if ((($pos = stripos($Value, ';')) !== false ? $pos + 1 : 0) > 0 || (($pos = stripos($Value, ',')) !== false ? $pos + 1 : 0) > 0) {
                        $strXMLSQL = SQL::addWhere($strXMLSQL, $param . ' in (' . str_ireplace(';', ',', $Value) . ')');
                        } else {
                        if ((is_null($param) ? "" : strtolower($param)) == 'id' || (is_null(substr($param, 0, max(0, min(2, strlen($param))))) ? "" : strtolower(substr($param, 0, max(0, min(2, strlen($param)))))) == 'fk' || (is_null(substr($param, max(0, strlen($param) - 3))) ? "" : strtolower(substr($param, max(0, strlen($param) - 3)))) == '.id') {
                            $Value = Str::numbersOnly($Value);
                            }
                        if ((($pos = strpos((is_null($strXMLSQL) ? "" : strtolower($strXMLSQL)), '[' . (is_null($param) ? "" : strtolower($param)) . ']', max(0, 1 - 1))) !== false ? $pos + 1 : 0) > 0) {
                            while ((($pos = strpos((is_null($strXMLSQL) ? "" : strtolower($strXMLSQL)), '[' . (is_null($param) ? "" : strtolower($param)) . ']', max(0, 1 - 1))) !== false ? $pos + 1 : 0) > 0) {
                                $strXMLSQL = str_ireplace('[' . $param . ']', $Value, $strXMLSQL);
                                }
                            } else {
                            $strXMLSQL = SQL::addWhere($strXMLSQL, $param . $expression . $Value);
                            }
                        }
                    }
                }
            }
        } else {
        $strStoreCacheID .= $param . '_' . $expression . '_' . $Value;
        }
    // Debug::write ("HandleParam resulted in SQL : " &strXMLSQL & " with cache id " & strStoreCacheID)
    // store values in a dictionary to enable adding parameters later on
    if (!is_object($dictParams)) {
        $dictParams = new ArrayObject();
        }
    // sometimes a key is already in the dictParams collection, skip it
    if (!$dictParams->offsetExists($param)) {
        $dictParams[$param] = $Value;
    }
    }
// Alternative for simple XML generation :
// adPersistXML = 1
// SET stmXML = Server::createObject("ADODB.Stream")
// objRs.Save stmXML, adPersistXML ' ado 2.5 required!
// result = stmXML.ReadText
// dCurrentDate implemented for task.php
/**
* Xmlstore Xmlretrieve
*
*/
/**
    * Retrieves XML data from a store, applying parameters and caching
    *
    * @param string $strParamStoreName Name of the XML store
    * @param string $cParam Parameters (comma-separated or 'AUTO' for query string)
    * @param string $dCurrentDate Current date for date() replacements in SQL
    */
function XMLStore_XMLRetrieve($strParamStoreName, $cParam, $dCurrentDate)
{
    global $cache_pre_identifier, $cache_xmlstore_ident, $dictParams, $libArrXMLStore, $libObjXMLDOM, $sActiveRepository, $sRootFieldName, $strStoreCacheID, $strmenuid, $strmoduleid, $strpageid, $strXMLSQL;

    $blnSearch = false;
    $strSearchIn = "";
    $strSearchFor = "";
    $arrImagePaths = [];
    $arrTxtConvert = [];
    $arrEMailConvert = [];
    $arrDateFields = [];
    $blnPageIDParam = false;

    $libArrXMLStore = XMLStore_GetStoreData($strParamStoreName);

    // Create the XML (in memory)
    if (!Arr::isArray($libArrXMLStore)) {
        return;
    }

    $strXMLSQL = isset($libArrXMLStore[STORE_QUERY]) ? $libArrXMLStore[STORE_QUERY] : '';
    $strStoreCacheID = '';
    $dictParams = new ArrayObject();
    if ($strXMLSQL!= '') {
            if ($cParam == PARAM_AUTO_RETRIEVE) {
                if (Request::queryAll()) {
                    foreach ($_GET as $key => $param) {
                        if ((is_null($param) ? "" : strtolower($param)) == 'search_for' || (is_null($param) ? "" : strtolower($param)) == 'search_in') {
                            $blnSearch = true;
                            }
                        HandleParam($param, '=', Request::query($param, ''), false);
                        // blnPageIDParam = blnPageIDParam or ( lcase(param)="pageID" )
                        }
                    }
                } else {
                if ($cParam != '') {
                    if ((($pos = stripos($cParam, ' and ', max(0, 1 - 1))) !== false ? $pos + 1 : 0) > 0 || (($pos = stripos($cParam, ' or ', max(0, 1 - 1))) !== false ? $pos + 1 : 0) > 0) {
                        HandleParam($cParam, '', '', false);
                        } else {
                        $arrParams = Arr::splitAlways($cParam, ',');
                        if (Arr::isArray($arrParams)) {
                            for ($t = 0; $t <= (count($arrParams) - 1); $t++) {
                                // response.write "<script>alert(""" & arrParams(t) & """);</script>"
                                $arrParams[$t] = str_replace('&GT;', '>', str_replace('&LT;', '<', $arrParams[$t]));
                                if ((($pos = strpos($arrParams[$t], '<>', max(0, 1 - 1))) !== false ? $pos + 1 : 0) > 0) {
                                    $arrThisPar = Arr::splitAlways(str_replace('<>', '|', $arrParams[$t]), '|');
                                    if (Arr::isArray($arrThisPar)) {
                                        HandleParam($arrThisPar["0"], '<>', $arrThisPar["1"], false);
                                        }
                                    } elseif ((($pos = strpos($arrParams[$t], '>=', max(0, 1 - 1))) !== false ? $pos + 1 : 0) > 0) {
                                    $arrThisPar = Arr::splitAlways(str_replace('>=', '|', $arrParams[$t]), '|');
                                    if (Arr::isArray($arrThisPar)) {
                                        HandleParam($arrThisPar["0"], '>=', $arrThisPar["1"], false);
                                        }
                                    } elseif ((($pos = strpos($arrParams[$t], '<=', max(0, 1 - 1))) !== false ? $pos + 1 : 0) > 0) {
                                    $arrThisPar = Arr::splitAlways(str_replace('<=', '|', $arrParams[$t]), '|');
                                    if (Arr::isArray($arrThisPar)) {
                                        HandleParam($arrThisPar["0"], '<=', $arrThisPar["1"], false);
                                        }
                                    } elseif ((($pos = strpos($arrParams[$t], '<', max(0, 1 - 1))) !== false ? $pos + 1 : 0) > 0) {
                                    $arrThisPar = Arr::splitAlways($arrParams[$t], '<');
                                    if (Arr::isArray($arrThisPar)) {
                                        HandleParam($arrThisPar["0"], '<', $arrThisPar["1"], false);
                                        }
                                    } elseif ((($pos = strpos($arrParams[$t], '>', max(0, 1 - 1))) !== false ? $pos + 1 : 0) > 0) {
                                    $arrThisPar = Arr::splitAlways($arrParams[$t], '>');
                                    if (Arr::isArray($arrThisPar)) {
                                        HandleParam($arrThisPar["0"], '>', $arrThisPar["1"], false);
                                        }
                                    } elseif ((($pos = strpos($arrParams[$t], '=', max(0, 1 - 1))) !== false ? $pos + 1 : 0) > 0) {
                                    $arrThisPar = Arr::splitAlways($arrParams[$t], '=');
                                    if (Arr::isArray($arrThisPar)) {
                                        HandleParam($arrThisPar["0"], '=', $arrThisPar["1"], false);
                                        }
                                    } else {
                                    if ($arrParams[$t] != '') {
                                        Error::show('Unhandled parameter : ' . $arrParams[$t]);
                                        }
                                    }
                                }
                            }
                        }
                    }
                if (Request::queryAll()) {
                    foreach ($_GET as $key => $param) {
                        // wijziging 12-6-2019: alleen als de parameter met Page begint (RINO actualiteiten)
                        if (substr((is_null($param) ? "" : strtolower($param)), 0, max(0, min(4, strlen((is_null($param) ? "" : strtolower($param)))))) == 'page') {
                            HandleParam($param, '=', Request::query($param, ''), true);
                            }
                        }
                    }
                }
        if ($blnSearch) {
            $strSearchIn = Request::query('search_in', '');
            if ($strSearchIn == '') {
                Error::show("Vereiste parameter 'search_in' ontbreekt!");
                exit();
                }
            $strSearchFor = lib_HTML_RemoveScripts(Request::query('search_for', ''));
            if ($strSearchFor == '') {
                Error::form("Vereiste parameter 'search_for' ontbreekt");
                exit();
                }
            $strStoreCacheID = str_replace(',', '-', $strSearchIn) . '__' . $strSearchFor;
            $strXMLSQL = SQL::complicatedWhere($strXMLSQL, $strSearchFor, $strSearchIn);
            }
        if ($dCurrentDate != date("Y-m-d")) {
            $strXMLSQL = str_ireplace('date()', SQL::postDateTime($dCurrentDate), $strXMLSQL);
            $strXMLSQL = str_ireplace('date ()', SQL::postDateTime($dCurrentDate), $strXMLSQL);
            }
        $strStoreCacheID = $cache_pre_identifier . (isset($libArrXMLStore[STORE_CACHE_PREFIX]) ? $libArrXMLStore[STORE_CACHE_PREFIX] : '') . '_' . $strParamStoreName . '_' . $strStoreCacheID . '_XML';
        $strStoreCacheID = str_ireplace('<', '_', $strStoreCacheID);
        $strStoreCacheID = str_ireplace('>', '_', $strStoreCacheID);
        $strStoreCacheID = str_ireplace('&', '_', $strStoreCacheID);
        $strStoreCacheID = str_ireplace('?', '_', $strStoreCacheID);
        $strStoreCacheID = str_ireplace('\\', '_', $strStoreCacheID);
        $strStoreCacheID = str_ireplace('{', '_', $strStoreCacheID);
        $strStoreCacheID = str_ireplace('}', '_', $strStoreCacheID);
        $strStoreCacheID = str_ireplace(':', '_', $strStoreCacheID);
        $strStoreCacheID = str_ireplace(';', '_', $strStoreCacheID);
        $strStoreCacheID = str_ireplace(',', '_', $strStoreCacheID);
        if (!empty(Application::get($strStoreCacheID . CONST_XMLPOSTFIX, ''))) {
            $libObjXMLDOM = Application::get($strStoreCacheID . CONST_XMLPOSTFIX, '');
        } else {
            if (Cache::isEnabledFor($strStoreCacheID) && Application::get($strStoreCacheID, '') != '') {
                Debug::write('XMLSnippets => DISK: Loading existing XML ' . $strStoreCacheID);
                // Use PHP DOMDocument instead of MSXML COM object
                $libObjXMLDOM = new DOMDocument();
                $libObjXMLDOM->preserveWhiteSpace = true;
                $libObjXMLDOM->load(Server::mapPath(Application::get($strStoreCacheID, '')));
                Cache::set($strStoreCacheID . CONST_XMLPOSTFIX, $libObjXMLDOM);
            } else {
                Debug::write('XMLSnippets => First call to XML Store ' . $strStoreCacheID);
                if ($strXMLSQL!= '') {
                    $rs = new RecordSet();
                    $rs = Database::openRS($strXMLSQL, CMA_connect_fulldbString($libArrXMLStore[STORE_DATABASE] ?? ''), adOpenForwardOnly);
                    if ($rs === null) {
                        throw new \Exception('Database query failed: ' . Database::getLastError());
                    }
                    $rs_current_row = $rs->fetch(PDO::FETCH_ASSOC);
                    if ($XML_SNIPPET_SETTING_RESOLVE_FIELDS) {
                        $sImageSQL = 'SELECT tblControls.ImgPath, tblControls.FieldName, tblSqlStatements.Table FROM tblSqlStatements INNER JOIN (tblForms INNER JOIN tblControls ON tblForms.ID = tblControls.FormID) ON tblSqlStatements.ID = tblForms.SqlID WHERE (tblForms.fkModule=' . ($libArrXMLStore[STORE_MODULE] ?? 0) . ' AND (tblControls.ControlTypeID=' . CONSTFLDTYPE_FILE . ' or tblControls.ControlTypeID=' . CONSTFLDTYPE_IMAGE . ' or tblControls.ControlTypeID=' . CONSTFLDTYPE_THUMBNAIL . '))';
                        $arrImgFields = Cache::retrieve($cache_xmlstore_ident . '_imgfields_' . ($libArrXMLStore[STORE_MODULE] ?? 0), $sActiveRepository, $sImageSQL);
                        // REDIM arrImagePaths($rs->fields->count) - Create new array
                        $new_size = ($rs->fields->count) + 1; // VBScript arrays are 0-based
                        $arrImagePaths = array_fill(0, $new_size, null);
                        if (Arr::isArray($arrImgFields)) {
                            for ($t = 0; $t <= $rs->fields->count - 1; $t++) {
                                $cur_fld = $rs->Fields[$t];
                                $strTableName = (is_null($cur_fld->properties['BASETABLENAME']) ? "" : strtolower($cur_fld->properties['BASETABLENAME']));
                                $strFieldName = (is_null($cur_fld->properties[0]) ? "" : strtolower($cur_fld->properties[0]));
                                for ($x = 0; $x <= (count($arrImgFields) - 1); $x++) {
                                    // response.write "  -> checking " & arrImgFields( 2, x) & " - " & arrImgFields( 1, x) & " against " & strFieldname
                                    if ((is_null($arrImgFields[2][$x]) ? "" : strtolower($arrImgFields[2][$x])) == $strTableName && (is_null($arrImgFields[1][$x]) ? "" : strtolower($arrImgFields[1][$x])) == $strFieldName) {
                                        if (Application::get('base_resource_path', '') != '') {
                                            $arrImagePaths[$t] = Application::get('base_resource_path', '');
                                            } else {
                                            $arrImagePaths[$t] = Application::get('base_path', '');
                                            }
                                        $arrImagePaths[$t] = $arrImagePaths[$t] . $arrImgFields[0][$x];
                                        }
                                    }
                                }
                            }
                        // REDIM arrDateFields($rs->fields->count) - Create new array
                        $new_size = ($rs->fields->count) + 1; // VBScript arrays are 0-based
                        $arrDateFields = array_fill(0, $new_size, null);
                        if (Arr::isArray($arrDateFields)) {
                            for ($t = 0; $t <= $rs->fields->count - 1; $t++) {
                                $cur_fld = $rs->Fields[$t];
                                $arrDateFields[$t] = ($cur_fld->type == ADDATE ? $cur_fld->name() : '');
                                }
                            }
                        $sTextSQL = 'SELECT tblControls.FieldName, tblSqlStatements.Table FROM tblSqlStatements INNER JOIN (tblForms INNER JOIN tblControls ON tblForms.ID = tblControls.FormID) ON tblSqlStatements.ID = tblForms.SqlID WHERE (tblForms.fkModule=' . ($libArrXMLStore[STORE_MODULE] ?? 0) . ' AND (tblControls.ControlTypeID=' . CONSTFLDTYPE_MEMO . ' and tblControls.TagsAllowed=False))';
                        $arrTxtFields = Cache::retrieve($cache_xmlstore_ident . '_textfields_' . ($libArrXMLStore[STORE_MODULE] ?? 0), $sActiveRepository, $sTextSQL);
                        // REDIM arrTxtConvert($rs->fields->count) - Create new array
                        $new_size = ($rs->fields->count) + 1; // VBScript arrays are 0-based
                        $arrTxtConvert = array_fill(0, $new_size, null);
                        if (Arr::isArray($arrTxtFields)) {
                            for ($t = 0; $t <= $rs->fields->count - 1; $t++) {
                                $cur_fld = $rs->Fields[$t];
                                $strTableName = (is_null($cur_fld->properties['BASETABLENAME']) ? "" : strtolower($cur_fld->properties['BASETABLENAME']));
                                $strFieldName = (is_null($cur_fld->properties[0]) ? "" : strtolower($cur_fld->properties[0]));
                                for ($x = 0; $x <= (count($arrTxtFields) - 1); $x++) {
                                    if ((is_null($arrTxtFields[1][$x]) ? "" : strtolower($arrTxtFields[1][$x])) == $strTableName && (is_null($arrTxtFields[0][$x]) ? "" : strtolower($arrTxtFields[0][$x])) == $strFieldName) {
                                        $arrTxtConvert[$t] = true;
                                        
                                    }
                                }
                            }
                        }
                        if (false) {
                            $sEMailSQL = 'SELECT tblControls.ImgPath, tblControls.FieldName, tblSqlStatements.Table FROM tblSqlStatements INNER JOIN (tblForms INNER JOIN tblControls ON tblForms.ID = tblControls.FormID) ON tblSqlStatements.ID = tblForms.SqlID WHERE (tblForms.fkModule=' . ($libArrXMLStore[STORE_MODULE] ?? 0) . ' AND tblControls.ControlTypeID=' . CONSTFLDTYPE_EMAIL . ')';
                            $arrEMailFields = Cache::retrieve($cache_xmlstore_ident . '_emailfields_' . ($libArrXMLStore[STORE_MODULE] ?? 0), $sActiveRepository, $sEMailSQL);
                            // REDIM arrEMailConvert($rs->fields->count) - Create new array
                            $new_size = ($rs->fields->count) + 1; // VBScript arrays are 0-based
                            $arrEMailConvert = array_fill(0, $new_size, null);
                            for ($t = 0; $t <= $rs->fields->count - 1; $t++) {
                                $cur_fld = $rs->Fields[$t];
                                $strTableName = (is_null($cur_fld->Properties['BaseTableName']) ? "" : strtolower($cur_fld->Properties['BaseTableName']));
                                $strFieldName = (is_null($cur_fld->Properties['BaseColumnName']) ? "" : strtolower($cur_fld->Properties['BaseColumnName']));
                                if (Arr::isArray($arrEMailFields)) {
                                    for ($x = 0; $x <= (count($arrEMailFields) - 1); $x++) {
                                        if ((is_null($arrEMailFields[2][$x]) ? "" : strtolower($arrEMailFields[2][$x])) == $strTableName && (is_null($arrEMailFields[1][$x]) ? "" : strtolower($arrEMailFields[1][$x])) == $strFieldName) {
                                            $arrEMailConvert[$t] = $cur_fld->name();
                                        }
                                    }
                                }
                            }
                        }
                        $libObjXMLDOM = lib_XMLfromRs($rs, $arrDateFields, $arrImagePaths, $arrTxtConvert, $arrEMailConvert, $libArrXMLStore[STORE_ROOTTABLE] ?? '', strtolower($libArrXMLStore[STORE_MODULE_NAME] ?? ''), $strParamStoreName);
                    }
                if (Arr::isArray($libArrXMLStore)) {
                    if (($libArrXMLStore[STORE_PARAM] ?? false) == true && Application::get('cma_show_module_settings', '')) {
                        $sqlParams = 'SELECT Name, Waarde FROM tblModuleParameters WHERE ModuleNaam=' . SQL::postString($libArrXMLStore[STORE_MODULE_NAME] ?? '');
                        $libArrXMLStoreParams = Cache::retrieve($cache_xmlstore_ident . '_params_' . ($libArrXMLStore[STORE_MODULE] ?? 0), 'data', $sqlParams);
                        if (Arr::isArray($libArrXMLStoreParams)) {
                            for ($t = 0; $t <= (count($libArrXMLStoreParams) - 1); $t++) {
                                $elementName = strtoupper(str_replace(' ', '', $libArrXMLStoreParams[0][$t] ?? ''));
                                if ($elementName != '') {
                                    $objXMLRootField = $libObjXMLDOM->createElement($elementName);
                                    $objXMLRootField->nodeValue = $libArrXMLStoreParams[1][$t] ?? '';
                                    if ($libObjXMLDOM->documentElement) {
                                        $libObjXMLDOM->documentElement->appendChild($objXMLRootField);
                                    }
                                }
                            }
                        }
                        }
                    }
                if ($blnSearch) {
                    lib_SearchSave($strSearchFor);
                    }
                if (is_object($dictParams)) {
                    foreach ($dictParams as $param) {
                        // support for calculations in parameters, results in crippled parameters, MSXSLT does not accept these characters
                        $sRootFieldName = (is_null($param) ? "" : strtoupper($param));
                        // skip that bugger 404 param: it is useless anyway
                        if (substr($param, 0, max(0, min(4, strlen($param)))) != '404;') {
                            $sRootFieldName = str_replace('(', '_', $sRootFieldName);
                            $sRootFieldName = str_replace(')', '_', $sRootFieldName);
                            $sRootFieldName = str_replace('/', '_', $sRootFieldName);
                            $sRootFieldName = str_replace('\\', '_', $sRootFieldName);
                            $sRootFieldName = str_replace('[', '_', $sRootFieldName);
                            $sRootFieldName = str_replace(']', '_', $sRootFieldName);
                            $sRootFieldName = str_replace('+', '_', $sRootFieldName);
                            $sRootFieldName = str_replace('<', '_', $sRootFieldName);
                            $sRootFieldName = str_replace('>', '_', $sRootFieldName);
                            $sRootFieldName = str_replace(';', '_', $sRootFieldName);
                            $sRootFieldName = str_replace(':', '_', $sRootFieldName);
                            $sRootFieldName = str_replace("'", '_', $sRootFieldName);
                            $sRootFieldName = str_replace(' ', '_', $sRootFieldName);
                            $sRootFieldName = str_replace('&', '_', $sRootFieldName);
                            $sRootFieldName = str_replace('?', '_', $sRootFieldName);
                            $sRootFieldName = str_replace('�', '_', $sRootFieldName);
                            if (is_numeric(substr($sRootFieldName, 0, max(0, min(1, strlen($sRootFieldName)))))) {
                                $sRootFieldName = '_' . $sRootFieldName;
                                }
                            lib_XMLAddRootElement($libObjXMLDOM, $sRootFieldName, $dictParams[$param]);
                            }
                        }
                    }
                lib_XMLAddRootElement($libObjXMLDOM, 'base_path', Application::get('base_path', ''));
                if (false) {
                    if (!$blnPageIDParam) {
                        // ON ERROR RESUME NEXT - Errors handled by ErrorHandler.php
                        lib_XMLAddRootElement($libObjXMLDOM, 'PAGEID', $strmoduleid);
                        lib_XMLAddRootElement($libObjXMLDOM, 'PAGEID', $strpageid);
                        lib_XMLAddRootElement($libObjXMLDOM, 'PAGEID', $strmenuid);
                        // ON ERROR GOTO 0 - Error handling via ErrorHandler.php
                        }
                    }
                if (!Application::get('cma_caching', '') == false) {
                    Cache::set($strStoreCacheID . CONST_XMLPOSTFIX, $libObjXMLDOM);
                    }
                }
            }
        }
    }

/**
    * Creates XML from a PDO recordset with support for:
    * - Date field formatting (multiple formats: shortdate, mediumdate, largedate, day, month_short, month_long, year)
    * - Image path prefixing
    * - Text field conversion (newlines to <BR>, bullet lists)
    * - Root table fields (fields from a specific table go to root level)
    *
    * @param PDOStatement|array|null $objRS PDO statement with results or array of rows
    * @param array $arrDateFields Array mapping field index to date field name (empty string if not a date)
    * @param array $arrPaths Array mapping field index to image path prefix
    * @param array $arrTxtConvert Array mapping field index to boolean (true if text should be converted)
    * @param array $arrEMailFields Array mapping field index to email field name (unused)
    * @param string $strRootElementsTable Table name whose fields go to root level (unused in simplified version)
    * @param string $strTopLevelNodeName Root element name
    * @param string $strRowNodeName Row element name
    * @return DOMDocument
    */
function lib_XMLfromRS($objRS, $arrDateFields, $arrPaths, $arrTxtConvert, $arrEMailFields, $strRootElementsTable, $strTopLevelNodeName, $strRowNodeName)
{
    // Create PHP DOMDocument
    $libObjXMLDOM = new DOMDocument('1.0', 'ISO-8859-1');
    $libObjXMLDOM->preserveWhiteSpace = true;
    $libObjXMLDOM->formatOutput = true;

    // Create the root element and append it to the XML document
    $objXMLRoot = $libObjXMLDOM->createElement($strTopLevelNodeName);
    $libObjXMLDOM->appendChild($objXMLRoot);

    // Support for empty RS (retrieval of module parameters only)
    if ($objRS === null) {
        return $libObjXMLDOM;
    }

    // Handle PDOStatement or pre-fetched array
    if ($objRS instanceof PDOStatement) {
        $allRows = $objRS->fetchAll(PDO::FETCH_ASSOC);
    } elseif (Arr::isArray($objRS)) {
        $allRows = $objRS;
    } else {
        return $libObjXMLDOM;
    }

    if (empty($allRows)) {
        return $libObjXMLDOM;
    }

    // Ensure arrays are properly initialized
    if (!Arr::isArray($arrDateFields)) $arrDateFields = [];
    if (!Arr::isArray($arrPaths)) $arrPaths = [];
    if (!Arr::isArray($arrTxtConvert)) $arrTxtConvert = [];

    // Get field names from first row
    $fieldNames = array_keys($allRows[0]);
    $intNumFields = count($fieldNames);

    // Convert rows to column-indexed array for compatibility with original code
    $aryRows = [];
    foreach ($allRows as $rowIdx => $row) {
        $fieldIdx = 0;
        foreach ($row as $Value) {
            $aryRows[$fieldIdx][$rowIdx] = $Value;
            $fieldIdx++;
        }
    }
    $intNumRows = count($allRows) - 1;
    $intNumFields = count($aryRows);
    $intNumRows = (count($aryRows) - 1);

    // Create the row template element
    $objXMLRow = $libObjXMLDOM->createElement(strtoupper($strRowNodeName));

    // Create field elements in the row template
    for ($i = 0; $i < count($fieldNames); $i++) {
        $objXMLField = $libObjXMLDOM->createElement(strtoupper($fieldNames[$i]));
        $objXMLRow->appendChild($objXMLField);
    }

    if (Arr::isArray($arrDateFields)) {
        for ($intCurFieldIdx = 0; $intCurFieldIdx <= $intNumFields; $intCurFieldIdx++) {
            // if $arrEMailFields[$intCurFieldIdx]&""<>"" then
            // Set objXMLField = libObjXMLDOM.createElement( ucase( replace( ucase( $arrEMailFields[$intCurFieldIdx]& "_antispam")," ","_")))
            // objXMLRow.appendChild objXMLField
            // ' Copy the field to give it a new memory address (copy all its children too)
            // Set objXMLField = objXMLField.cloneNode(True)
            // end if
            // if XML_SNIPPET_SETTING_RESOLVE_FIELDS then
            $sFldName = $arrDateFields[$intCurFieldIdx] . '';
            if ($sFldName != '') {
                $objXMLField = $libObjXMLDOM->createElement((is_null(str_replace(' ', '_', (is_null($sFldName . '_shortdate') ? "" : strtoupper($sFldName . '_shortdate')))) ? "" : strtoupper(str_replace(' ', '_', (is_null($sFldName . '_shortdate') ? "" : strtoupper($sFldName . '_shortdate'))))));
                $objXMLRow->appendChild($objXMLField);
                $objXMLField = $objXMLField->cloneNode(true);
                $objXMLField = $libObjXMLDOM->createElement((is_null(str_replace(' ', '_', (is_null($sFldName . '_mediumdate') ? "" : strtoupper($sFldName . '_mediumdate')))) ? "" : strtoupper(str_replace(' ', '_', (is_null($sFldName . '_mediumdate') ? "" : strtoupper($sFldName . '_mediumdate'))))));
                $objXMLRow->appendChild($objXMLField);
                $objXMLField = $objXMLField->cloneNode(true);
                $objXMLField = $libObjXMLDOM->createElement((is_null(str_replace(' ', '_', (is_null($sFldName . '_largedate') ? "" : strtoupper($sFldName . '_largedate')))) ? "" : strtoupper(str_replace(' ', '_', (is_null($sFldName . '_largedate') ? "" : strtoupper($sFldName . '_largedate'))))));
                $objXMLRow->appendChild($objXMLField);
                $objXMLField = $objXMLField->cloneNode(true);
                $objXMLField = $libObjXMLDOM->createElement((is_null(str_replace(' ', '_', (is_null($sFldName . '_day') ? "" : strtoupper($sFldName . '_day')))) ? "" : strtoupper(str_replace(' ', '_', (is_null($sFldName . '_day') ? "" : strtoupper($sFldName . '_day'))))));
                $objXMLRow->appendChild($objXMLField);
                $objXMLField = $objXMLField->cloneNode(true);
                $objXMLField = $libObjXMLDOM->createElement((is_null(str_replace(' ', '_', (is_null($sFldName . '_month_short') ? "" : strtoupper($sFldName . '_month_short')))) ? "" : strtoupper(str_replace(' ', '_', (is_null($sFldName . '_month_short') ? "" : strtoupper($sFldName . '_month_short'))))));
                $objXMLRow->appendChild($objXMLField);
                $objXMLField = $objXMLField->cloneNode(true);
                $objXMLField = $libObjXMLDOM->createElement((is_null(str_replace(' ', '_', (is_null($sFldName . '_month_long') ? "" : strtoupper($sFldName . '_month_long')))) ? "" : strtoupper(str_replace(' ', '_', (is_null($sFldName . '_month_long') ? "" : strtoupper($sFldName . '_month_long'))))));
                $objXMLRow->appendChild($objXMLField);
                $objXMLField = $objXMLField->cloneNode(true);
                $objXMLField = $libObjXMLDOM->createElement((is_null(str_replace(' ', '_', (is_null($sFldName . '_year') ? "" : strtoupper($sFldName . '_year')))) ? "" : strtoupper(str_replace(' ', '_', (is_null($sFldName . '_year') ? "" : strtoupper($sFldName . '_year'))))));
                $objXMLRow->appendChild($objXMLField);
                $objXMLField = $objXMLField->cloneNode(true);
                if (false) {
                    $objXMLField = $libObjXMLDOM->createElement((is_null(str_replace(' ', '_', (is_null($sFldName . '_rssdate') ? "" : strtoupper($sFldName . '_rssdate')))) ? "" : strtoupper(str_replace(' ', '_', (is_null($sFldName . '_rssdate') ? "" : strtoupper($sFldName . '_rssdate'))))));
                    $objXMLRow->appendChild($objXMLField);
                    $objXMLField = $objXMLField->cloneNode(true);
                    }
                }
            }
        }
        for ($intCurRowIdx = 0; $intCurRowIdx <= $intNumRows; $intCurRowIdx++) {
            // index for email fields
            $arrIndex = $intNumFields + 1;
            // Retrieve all the Field nodes within the Row
            $objXMLNodeList = $objXMLRow->childNodes;
            // Add the data for the fields
            // We know there's only one child for each Field (the FieldValue node) so the FirstChild property will work fine
            for ($intCurFieldIdx = 0; $intCurFieldIdx <= $intNumFields; $intCurFieldIdx++) {
                $objXMLField = $objXMLNodeList[$intCurFieldIdx];

                $sCurValue = '';
                try {
                    $sCurValue = $aryRows[$intCurFieldIdx][$intCurRowIdx] . '';
                }
                catch (Exception $e) { // ignore 
                } 
                // NL IIS probleem.
                if (true== 'Waar') {
                    if ($sCurValue == 'Waar') {
                        $sCurValue = 'True';
                        } elseif ($sCurValue == 'Onwaar') {
                        $sCurValue = 'False';
                        }
                    }
                if (Arr::isArray($arrTxtConvert)) {
                    if ($sCurValue == '') {
                        $objXMLField->nodeValue = '';
                        } else {
                        if ((count($arrTxtConvert) - 1) >= $intCurFieldIdx) {
                            if (arrTxtConvert($intCurFieldIdx) == true) {
                                if ((($pos = stripos($sCurValue, '�', max(0, 1 - 1))) !== false ? $pos + 1 : 0) > 0) {
                                    $sCurValue = '<UL>' . str_replace('�', '<LI>', $sCurValue);
                                    $intLIpos = (($pos = strrpos(substr($sCurValue, 0, -1), '<LI>')) !== false ? $pos + 1 : 0);
                                    $intvbCrlfPos = (($pos = stripos(substr($sCurValue, max(0, $intLIpos - 1)), PHP_EOL, max(0, 1 - 1))) !== false ? $pos + 1 : 0);
                                    if ($intvbCrlfPos > 0) {
                                        $sCurValue = substr($sCurValue, 0, max(0, min($intLIpos + $intvbCrlfPos, strlen($sCurValue)))) . '</UL>' . substr($sCurValue, max(0, $intLIpos + $intvbCrlfPos - 1));
                                        } else {
                                        $sCurValue .= '</UL>';
                                        }
                                    $sCurValue = str_replace(PHP_EOL + PHP_EOL, '</UL><UL>', $sCurValue);
                                    }
                                $sCurValue = str_ireplace(PHP_EOL, '<BR />', $sCurValue);
                                $sCurValue = LinkURLs($sCurValue);
                                } else {
                                $sCurValue = arrPaths($intCurFieldIdx) . $sCurValue;
                                }
                            }
                        }
                    }
                // email anti-spam Creates extra protected fields!
                // TODO: : leads to problems for ALIASED (fld as xxx) email fields!
                // if $arrEMailFields[$intCurFieldIdx]&""<>"" then
                // set objXMLEmailField = objXMLNodeList.item($arrIndex)
                // objXMLEmailField.text = Lib_LinkEmailAntiSpam( sCurValue )
                // ' Copy the field to give it a new memory address (copy all its children too)
                // Set objXMLEmailField = objXMLEmailField.cloneNode(True)
                // arrIndex = arrIndex + 1
                // end if
                if (Arr::isArray($arrDateFields)) {
                    if ($arrDateFields[$intCurFieldIdx]!= '') {
                        $objXMLDateField = $objXMLNodeList[$arrIndex];
                        if (is_object($objXMLDateField)) {
                            if ($aryRows[$intCurFieldIdx][$intCurRowIdx]!= '') {
                                $objXMLDateField->nodeValue = (is_null(strMediumDate($aryRows[$intCurFieldIdx][$intCurRowIdx])) ? "" : strtolower(strMediumDate($aryRows[$intCurFieldIdx][$intCurRowIdx])));
                                }
                            $objXMLDateField = $objXMLDateField->cloneNode(true);
                            $arrIndex = $arrIndex + 1;
                            }
                        $objXMLDateField = $objXMLNodeList[$arrIndex];
                        if (is_object($objXMLDateField)) {
                            if ($aryRows[$intCurFieldIdx][$intCurRowIdx]!= '') {
                                $objXMLDateField->nodeValue = (is_null(lib_date_ddmmmmmyyyy($aryRows[$intCurFieldIdx][$intCurRowIdx])) ? "" : strtolower(lib_date_ddmmmmmyyyy($aryRows[$intCurFieldIdx][$intCurRowIdx])));
                                }
                            $objXMLDateField = $objXMLDateField->cloneNode(true);
                            $arrIndex = $arrIndex + 1;
                            }
                        $objXMLDateField = $objXMLNodeList[$arrIndex];
                        if (is_object($objXMLDateField)) {
                            if ($aryRows[$intCurFieldIdx][$intCurRowIdx]!= '') {
                                $objXMLDateField->nodeValue = (is_null(date("F j, Y", strtotime($aryRows[$intCurFieldIdx][$intCurRowIdx]))) ? "" : strtolower(date("F j, Y", strtotime($aryRows[$intCurFieldIdx][$intCurRowIdx]))));
                                }
                            $objXMLDateField = $objXMLDateField->cloneNode(true);
                            $arrIndex = $arrIndex + 1;
                            }
                        $objXMLDateField = $objXMLNodeList[$arrIndex];
                        if (is_object($objXMLDateField)) {
                            if ($aryRows[$intCurFieldIdx][$intCurRowIdx]!= '') {
                                $objXMLDateField->nodeValue = date("j", strtotime($aryRows[$intCurFieldIdx][$intCurRowIdx]));
                                }
                            $objXMLDateField = $objXMLDateField->cloneNode(true);
                            $arrIndex = $arrIndex + 1;
                            }
                        $objXMLDateField = $objXMLNodeList[$arrIndex];
                        if (is_object($objXMLDateField)) {
                            if ($aryRows[$intCurFieldIdx][$intCurRowIdx]!= '') {
                                $objXMLDateField->nodeValue = (is_null(ToShortMonth(date("n", strtotime($aryRows[$intCurFieldIdx][$intCurRowIdx])))) ? "" : strtolower(ToShortMonth(date("n", strtotime($aryRows[$intCurFieldIdx][$intCurRowIdx])))));
                                }
                            $objXMLDateField = $objXMLDateField->cloneNode(true);
                            $arrIndex = $arrIndex + 1;
                            }
                        $objXMLDateField = $objXMLNodeList[$arrIndex];
                        if (is_object($objXMLDateField)) {
                            if ($aryRows[$intCurFieldIdx][$intCurRowIdx]!= '') {
                                $objXMLDateField->nodeValue = (is_null(ToLongMonth(date("n", strtotime($aryRows[$intCurFieldIdx][$intCurRowIdx])))) ? "" : strtolower(ToLongMonth(date("n", strtotime($aryRows[$intCurFieldIdx][$intCurRowIdx])))));
                                }
                            $objXMLDateField = $objXMLDateField->cloneNode(true);
                            $arrIndex = $arrIndex + 1;
                            }
                        $objXMLDateField = $objXMLNodeList[$arrIndex];
                        if (is_object($objXMLDateField)) {
                            if ($aryRows[$intCurFieldIdx][$intCurRowIdx]!= '') {
                                $objXMLDateField->nodeValue = date("Y", strtotime($aryRows[$intCurFieldIdx][$intCurRowIdx]));
                                }
                            $objXMLDateField = $objXMLDateField->cloneNode(true);
                            $arrIndex = $arrIndex + 1;
                            }
                        if (false) {
                            $rssdate = $aryRows[$intCurFieldIdx][$intCurRowIdx];
                            $objXMLDateField = $objXMLNodeList[$arrIndex];
                            if ($rssdate!= '') {
                                $objXMLDateField->nodeValue = date((true ? "D" : "l"), strtotime("Sunday +" . ($weekday[$rssdate] - 1) . " days")) . ', ' . Str::padZero(date("j", strtotime($rssdate)), 2) . date((true ? "M" : "F"), mktime(0, 0, 0, date("n", strtotime($rssdate)), 1)) . ' ' . date("Y", strtotime($rssdate)) . ' ' . Str::padZero(date("G", strtotime($rssdate)), 2) . ':' . Str::padZero(intval(date("i", strtotime($rssdate))), 2) . ':00 GMT';
                                }
                            $objXMLDateField = $objXMLDateField->cloneNode(true);
                            $arrIndex = $arrIndex + 1;
                            }
                        }
                    }

                // prevents data error of XML processor - was in use in RINO Support
                $sCurValue = str_ireplace('', '&nbsp;', $sCurValue);
                // this is the preferred way, but display is crippeled
                // objXMLField.text = lib_XMLEncode( sCurValue )
                $objXMLField->nodeValue = $sCurValue;
                // if err and application("local") then
                // response.write "<BR>Data error |<B>" & Server::htmlEncode(sCurValue) & "</B>|<BR>"
                // end if
                // on error goto 0
            }
            // Build the populated Row
            $objXMLRoot->appendChild($objXMLRow);
            // Copy the current row to give it a new memory address (copy children too)
            $objXMLRow = $objXMLRow->cloneNode(true);
            }
        }
        // The XML declaration is already set in DOMDocument constructor
        // Return the DOMDocument for XSLT transformation
        return $libObjXMLDOM;
}
?>
