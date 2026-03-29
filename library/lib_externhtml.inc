<?php
// RetrieveExternalURLText
// lib_XML_displayRSS
// retrieves and processes (using XSLT) an external RSS feed
// In memory caching mechanism used, adjustable in minutes
/**
* Lib Xml Displayrss
*
*/
use App\Library\Application;
use App\Library\Database;
use App\Library\Debug;
use App\Library\Error;
use App\Library\HttpClient;
use App\Library\Request;
use App\Library\Server;

/**
 * Lib Xml Displayrss
 */
function lib_XML_displayRSS($strSource, $strXSL, $intMinutes)
{
    $strXSLFile = "";
    $sPage = null;
    $blnRefresh = true;
    $intMinutesAgo = 0;

    if (Application::get('cache_rss_' . $strSource, '') != '') {
        $intMinutesAgo = abs(dateDiff('n', date("Y-m-d H:i:s"), Application::get('cache_rss_' . $strSource . '_time', '')));
        if ($intMinutesAgo < $intMinutes) {
            $blnRefresh = false;
        }
    }

    // Fetch the RSS feed
    if ($blnRefresh) {
        // Use cURL-based HttpClient
        $http = HttpClient::get($strSource);
        $sPage = $http->getResponseText();
        $http = null;
    } else {
        $sPage = Application::get('cache_rss_' . $strSource, '');
    }

    // Load the feed into a DOM object
    $xmlRSSSource = new DOMDocument();
    $xmlRSSSource->loadXML($sPage);

    // Load the XSL style sheet
    $strXSLFile = Server::mapPath($strXSL);
    $xmlStyle = new DOMDocument();
    $xmlStyle->load($strXSLFile);

    // Render the feed using the style sheet
    $xslt = new XSLTProcessor();
    $xslt->importStyleSheet($xmlStyle);
    $sPage = $xslt->transformToXML($xmlRSSSource);

    if ($sPage === false) {
        Error::show("Error processing RSS XSLT file '" . $strXSLFile . "'.<BR>");
    }

    $sPage = str_replace('&lt;', '<', str_replace('&gt;', '>', $sPage));
    $sPage = str_replace('&amp;amp;', '&amp;', str_replace('&amp;', '&', $sPage));
    return $sPage;
}
// Basis function to retrieve the text of a given external page or site
/**
* Lib Httpget
*
*/
function lib_HttpGet($sUrl)
{
    $strRetval = '';

    // Use cURL-based HttpClient with timeout
    $http = new HttpClient();
    $http->setTimeouts(6000, 6000, 6000, 6000);
    $http->open('GET', $sUrl);
    $http->setRequestHeader('Cache-Control', 'no-cache');
    $http->setRequestHeader('Pragma', 'no-cache');
    $http->send();

    if ($http->getErrorCode() == 0 && $http->getStatus() == 200) {
        $strRetval = $http->getResponseText();
    }
    $http = null;

    return $strRetval;
}
/**
* Retrieveexternalurltext
*
*/
function RetrieveExternalURLText($txtModuleURL, $txtCurrentUrl)
{
    $reqVariable = null;
    $strDataToSend = "";
    $formCount = null;
    $sPath = null;
    $objXMLDoc = null;
    $txtModuleHTML = "";
    $bAllowed = true;
    $sw_reqMethod = Request::query('sw_reqMethod', '');
    $action = Request::query('strSWAction', '');
    $mode = Request::query('mode', '');
    if (Application::get('allowed_external_url', '') != '') {
        if ($txtModuleURL != '') {
            if (!(($pos = stripos($txtModuleURL, Application::get('allowed_external_url', ''))) !== false ? $pos + 1 : 0) > 1) {
                $bAllowed = false;
            }
        }
        if ($action != '') {
            if (!(($pos = stripos($action, Application::get('allowed_external_url', ''))) !== false ? $pos + 1 : 0) > 1) {
                $bAllowed = false;
            }
        }
    }
    // HACK: it's a hack!
    if (!$bAllowed) {
        echo 'Halting.';
        exit();
    }
    if ($bAllowed) {
        if ($txtModuleURL== '' && $sw_reqMethod== '' && $action== '') {
            Error::show('Fout, geen url opgegeven!!');
            exit();
        }
        if ($sw_reqMethod != '') {
            $formCount = 1;
            Request::postAll();
            foreach (Request::postAll() as $reqVariable) {
                $strDataToSend .= $reqVariable . '=' . Server::urlEncode(Request::post($reqVariable, ''));
                Request::postAll();
                if ($formCount != Request::postAll()) {
                    $strDataToSend .= '&';
                }
                $formCount = $formCount + 1;
            }

            if (strcasecmp($sw_reqMethod, 'get') == 0) {
                $action = Request::queryAll();
                $action = substr(Request::queryAll(), max(0, (($pos = stripos(Request::queryAll(), 'strSWAction=', max(0, 1 - 1))) !== false ? $pos + 1 : 0) + 12 - 1));
            }

            // Use cURL-based HttpClient
            $objXMLDoc = new HttpClient();
            $objXMLDoc->open(strtoupper($sw_reqMethod), $action);
            $objXMLDoc->setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            $objXMLDoc->setRequestHeader('Pragma', 'no-cache');
            $objXMLDoc->send($strDataToSend);
            $txtModuleHTML = $objXMLDoc->getResponseText();

            $response_contenttype = 'text/HTML';
            header("Content-Type: " . 'text/HTML');
            $objXMLDoc = null;
        } else {
            if ($txtModuleURL != '') {
                Debug::write("External content taken from '" . $txtModuleURL . "'");
                // ON ERROR RESUME NEXT - Errors handled by ErrorHandler.php
                $txtModuleHTML = lib_HttpGet($txtModuleURL);
                // ON ERROR GOTO 0 - Error handling via ErrorHandler.php
                if ($php_error_number != 0) {
                    $txtModuleHTML = 'Er is een fout opgetreden. Mogelijk zijn de door u opgegeven parameters onjuist. Controleer deze en probeer opnieuw...';
                }
            }
        }
        $txtModuleHTML = removeSpaces($txtModuleHTML);
        if ($action != '') {
            $txtModuleURL = $action;
        }
        $txtModuleHTML = transformPaths($txtModuleHTML, $txtModuleURL, 'background=', 1, '');
        $txtModuleHTML = transformPaths($txtModuleHTML, $txtModuleURL, 'src=', 1, '');
        $txtModuleHTML = transformPaths($txtModuleHTML, $txtModuleURL, '<a href=', 2, $txtCurrentUrl . '&sw_reqMethod=get&strSWAction=');
        $txtModuleHTML = transformPaths($txtModuleHTML, $txtModuleURL, 'href=', 1, '');
        $txtModuleHTML = transformAction($txtModuleHTML, $txtModuleURL, $txtCurrentUrl);
        return $txtModuleHTML;
    }
}
/**
* Streamtranslate
*
*/
function StreamTranslate($objXMLDoc)
{
    Error::Page('StreamTranslate is no longer supported');
   }
/**
* Removespaces
*
*/
function removeSpaces($str)
{
    $strTemp = $str;
    $strTemp = str_replace(' = ', '=', $strTemp);
    $strTemp = str_replace('= ', '=', $strTemp);
    $strTemp = str_replace(' =', '=', $strTemp);
    return $strTemp;
}
/**
* Transformpaths
*
*/
function transformPaths($strInput, $strURL, $strPattern, $intSpaces, $txtCurrentUrl)
{
    $strTemp = "";
    $intStart = 0;
    $intEnd = 0;
    $strSub = "";
    $strRip = "";
    $strBegin = "";
    $strTail = "";
    $patternLenght = null;
    $root = null;
    $newURL = null;
    $i = null;
    $iSpace = false;
    $nextChar = null;
    $base = null;
    $strTemp = $strInput;
    $intStart = 1;
    $intEnd = 1;
    $patternLenght = (is_null($strPattern) ? 0 : strlen($strPattern));
    $root = getRootFromURL($strURL);
    $base = getBaseFromURL($strURL);
    while ($intStart != 0) {
        $intStart = (($pos = stripos((is_null($strTemp) ? "" : strtolower($strTemp)), (is_null($strPattern) ? "" : strtolower($strPattern)), max(0, $intStart - 1))) !== false ? $pos + 1 : 0);
        $iSpace = 0;
        if ($intStart > 0) {
            for ($i = $intStart; $i <= (is_null($strTemp) ? 0 : strlen($strTemp)); $i++) {
                $nextChar = substr($strTemp, max(0, $i - 1), 1);
                switch ($nextChar) {
                case ' ':
                    $iSpace = $iSpace + 1;
                        if ($iSpace == $intSpaces) {
                            $intEnd = $i;
                        break; // EXIT FOR
                    }
                break;
            case '>':
                $intEnd = $i;
                break; // EXIT FOR
            break;
        }
    }
    if ($intEnd > 1) {
        $strSub = substr($strTemp, max(0, $intStart + $patternLenght - 1), $intEnd - $intStart - $patternLenght);
    }
    $strRip = str_replace('"', '', $strSub);
    $strRip = str_replace("'", '', $strRip);
    if ((($pos = stripos((is_null($strSub) ? "" : strtolower($strSub)), 'javascript:')) !== false ? $pos + 1 : 0) == 0) {
        if ((($pos = stripos($strSub, '://')) !== false ? $pos + 1 : 0) == 0) {
            if (substr($strRip, 0, max(0, min(1, strlen($strRip)))) == '/') {
                $newURL = $txtCurrentUrl . $root . $strRip;
            } elseif (substr($strRip, 0, max(0, min(1, strlen($strRip)))) == '#') {
                $newURL = $strRip;
            } else {
                $newURL = $txtCurrentUrl . $base . $strRip;
            }
        } else {
            $newURL = $txtCurrentUrl . $strRip;
        }
        $strBegin = substr($strTemp, max(0, 1 - 1), $intStart - 1);
        $strTail = lib_vbscript_replace($strTemp, $strPattern . $strSub, $strPattern . $newURL, $intStart, 1, 1);
        $strTemp = $strBegin . $strTail;
    }
    $intStart = $intEnd;
}
}
return $strTemp;
}
/**
* Transformaction
*
*/
function transformAction($strInput, $strURL, $newAction)
{
    $strTemp = "";
    $intStart = 0;
    $intEnd = 0;
    $strSub = "";
    $strRip = "";
    $strBegin = "";
    $strTail = "";
    $patternLenght = null;
    $root = null;
    $originalAction = null;
    $i = null;
    $nextChar = null;
    $strPattern = "";
    $base = null;
    $intFormStart = 0;
    $intMethodStart = 0;
    $reqMethod = null;
    $iMethod = null;
    $intMethodEnd = 0;
    $strSubMethod = "";
    $intFormStart = 1;
    $strPattern = ' action';
    $strTemp = $strInput;
    $intStart = 1;
    $intEnd = 1;
    $intMethodEnd = 1;
    $patternLenght = (is_null($strPattern) ? 0 : strlen($strPattern)) + 1;
    $root = getRootFromURL($strURL);
    $base = getBaseFromURL($strURL);
    // Get method if any
    $intFormStart = (($pos = stripos($strTemp, '<form', max(0, $intFormStart - 1))) !== false ? $pos + 1 : 0);
    if ($intFormStart > 0) {
        $intMethodStart = (($pos = stripos($strTemp, ' method=', max(0, $intFormStart - 1))) !== false ? $pos + 1 : 0);
        if ($intMethodStart > 0) {
            for ($iMethod = $intMethodStart + 1; $iMethod <= (is_null($strTemp) ? 0 : strlen($strTemp)); $iMethod++) {
                $nextChar = substr($strTemp, max(0, $iMethod - 1), 1);
                switch ($nextChar) {
                case ' ':
                    $intMethodEnd = $iMethod;
                    break; // EXIT FOR
                break;
            case '>':
                $intMethodEnd = $iMethod;
                break; // EXIT FOR
            break;
        }
    }
    $strSubMethod = substr($strTemp, max(0, $intMethodStart - 1), $intMethodEnd - $intMethodStart);
    $reqMethod = substr($strSubMethod, max(0, 9 - 1), $intMethodEnd - $intMethodStart - 8);
    $reqMethod = str_replace('"', '', $reqMethod);
    $strBegin = substr($strTemp, max(0, 1 - 1), $intMethodStart);
    $strTail = lib_vbscript_replace($strTemp, $strSubMethod, 'method="POST"', $intMethodStart, 1, 1);
    $strTemp = $strBegin . $strTail;
} else {
    for ($iMethod = $intFormStart; $iMethod <= (is_null($strTemp) ? 0 : strlen($strTemp)); $iMethod++) {
        $nextChar = substr($strTemp, max(0, $iMethod - 1), 1);
        switch ($nextChar) {
        case ' ':
            $intMethodStart = $iMethod;
            break; // EXIT FOR
        break;
    case '>':
        $intMethodStart = $iMethod;
        break; // EXIT FOR
    break;
}
}
$strBegin = substr($strTemp, max(0, 1 - 1), $intMethodStart - 1);
$strTail = lib_vbscript_replace($strTemp, $nextChar, ' method="POST"' . $nextChar, $intMethodStart, 1, 1);
$strTemp = $strBegin . $strTail;
$reqMethod = 'GET';
}
$newAction .= '&sw_reqMethod=' . $reqMethod;
$intEnd = 1;
while ($intStart != 0) {
    $intStart = (($pos = stripos($strTemp, $strPattern, max(0, $intStart - 1))) !== false ? $pos + 1 : 0);
    if ($intStart > 0) {
        for ($i = $intStart + 1; $i <= (is_null($strTemp) ? 0 : strlen($strTemp)); $i++) {
            $nextChar = substr($strTemp, max(0, $i - 1), 1);
            switch ($nextChar) {
            case ' ':
                $intEnd = $i;
                break; // EXIT FOR
            break;
        case '>':
            $intEnd = $i;
            break; // EXIT FOR
        break;
    }
}
if ($intEnd > 1) {
    $strSub = substr($strTemp, max(0, $intStart + $patternLenght - 1), $intEnd - $intStart - $patternLenght);
}
$strRip = str_replace('"', '', $strSub);
if ((($pos = stripos($strSub, '://')) !== false ? $pos + 1 : 0) == 0) {
    if (substr($strRip, max(0, 1 - 1), 1) == '/') {
        $originalAction = $root . $strRip;
    } else {
        $originalAction = $base . $strRip;
    }
} else {
    $originalAction = $strRip;
}
$newAction .= '&strSWAction=' . Server::urlEncode($originalAction);
$strBegin = substr($strTemp, max(0, 1 - 1), $intStart);
$strTail = lib_vbscript_replace($strTemp, $strPattern . '=' . $strSub, $strPattern . '="' . $newAction . '"', $intStart, 1, 1);
$strTemp = $strBegin . $strTail;
$intStart = $intEnd;
}
}
} else {
$reqMethod = 'none';
}
return $strTemp;
}
/**
* Getbasefromurl
*
*/
function getBaseFromURL($strURL)
{
    $thisChar = null;
    $i = null;
    $count = 0;
    $lastSlashPos = null;
    $strTemp = $strURL;
    $count = 0;
    for ($i = 1; $i <= (is_null($strTemp) ? 0 : strlen($strTemp)); $i++) {
        $thisChar = substr($strTemp, max(0, $i - 1), 1);
        switch ($thisChar) {
        case '/':
            $count = $count + 1;
                if ($count == 3) {
                break; // EXIT FOR
            }
        break;
    }
}
if ($count == 3) {
    if ((($pos = stripos($strTemp, '.', max(0, $i - 1))) !== false ? $pos + 1 : 0) > 0) {
        $lastSlashPos = (($pos = strrpos($strTemp, '/')) !== false ? $pos + 1 : 0);
        $strTemp = substr($strTemp, max(0, 1 - 1), $lastSlashPos);
    }
}
if (substr($strTemp, max(0, (is_null($strTemp) ? 0 : strlen($strTemp)) - 1), 1) != '/') {
    $strTemp .= '/';
}
return $strTemp;
}
/**
* Getrootfromurl
*
*/
function getRootFromURL($strURL)
{
    $thisChar = null;
    $i = null;
    $count = 0;
    for ($i = 1; $i <= (is_null($strURL) ? 0 : strlen($strURL)); $i++) {
        $thisChar = substr($strURL, max(0, $i - 1), 1);
        switch ($thisChar) {
        case '/':
            $count = $count + 1;
                if ($count == 3) {
                    $i = $i - 1;
                break; // EXIT FOR
            }
        break;
    }
}
if ($count == 2) {
    $i = (is_null($strURL) ? 0 : strlen($strURL));
}
return substr($strURL, max(0, 1 - 1), $i);
}
?>
