<?php
use App\Library\Request;

$strUserAgent = Request::server('HTTP_USER_AGENT', '');
// Search engine detection
/**
* Lib Issearchengine
*
*/
function lib_isSearchEngine()
{
    global $strUserAgent;
    return (($pos = stripos((is_null($strUserAgent) ? "" : strtolower($strUserAgent)), 'bot', max(0, 1 - 1))) !== false ? $pos + 1 : 0) > 0 || (($pos = stripos((is_null($strUserAgent) ? "" : strtolower($strUserAgent)), 'seek', max(0, 1 - 1))) !== false ? $pos + 1 : 0) > 0 || (($pos = stripos((is_null($strUserAgent) ? "" : strtolower($strUserAgent)), 'find', max(0, 1 - 1))) !== false ? $pos + 1 : 0) > 0 || (($pos = stripos((is_null($strUserAgent) ? "" : strtolower($strUserAgent)), 'spider', max(0, 1 - 1))) !== false ? $pos + 1 : 0) > 0 || (($pos = stripos((is_null($strUserAgent) ? "" : strtolower($strUserAgent)), 'slurp', max(0, 1 - 1))) !== false ? $pos + 1 : 0) > 0 || (($pos = stripos((is_null($strUserAgent) ? "" : strtolower($strUserAgent)), 'crawl', max(0, 1 - 1))) !== false ? $pos + 1 : 0) > 0 || (($pos = stripos((is_null($strUserAgent) ? "" : strtolower($strUserAgent)), 'scooter', max(0, 1 - 1))) !== false ? $pos + 1 : 0) > 0 || (($pos = stripos((is_null($strUserAgent) ? "" : strtolower($strUserAgent)), 'ia_archiver', max(0, 1 - 1))) !== false ? $pos + 1 : 0) > 0 || (($pos = stripos((is_null($strUserAgent) ? "" : strtolower($strUserAgent)), 'voyager/', max(0, 1 - 1))) !== false ? $pos + 1 : 0) > 0 || (($pos = stripos((is_null($strUserAgent) ? "" : strtolower($strUserAgent)), 'indy library', max(0, 1 - 1))) !== false ? $pos + 1 : 0) > 0 || (($pos = stripos((is_null($strUserAgent) ? "" : strtolower($strUserAgent)), 'heritrix', max(0, 1 - 1))) !== false ? $pos + 1 : 0) > 0 || (($pos = stripos((is_null($strUserAgent) ? "" : strtolower($strUserAgent)), 'jandex', max(0, 1 - 1))) !== false ? $pos + 1 : 0) > 0 || (($pos = stripos((is_null($strUserAgent) ? "" : strtolower($strUserAgent)), 'java/', max(0, 1 - 1))) !== false ? $pos + 1 : 0) > 0 || (($pos = stripos((is_null($strUserAgent) ? "" : strtolower($strUserAgent)), 'ask jeeves/teoma', max(0, 1 - 1))) !== false ? $pos + 1 : 0) > 0 || (($pos = stripos((is_null($strUserAgent) ? "" : strtolower($strUserAgent)), 'vagabondo', max(0, 1 - 1))) !== false ? $pos + 1 : 0) > 0 || (($pos = stripos((is_null($strUserAgent) ? "" : strtolower($strUserAgent)), 'panscient.com', max(0, 1 - 1))) !== false ? $pos + 1 : 0) > 0 || (($pos = stripos((is_null($strUserAgent) ? "" : strtolower($strUserAgent)), 'cmsworldmap.com', max(0, 1 - 1))) !== false ? $pos + 1 : 0) > 0 || (($pos = stripos((is_null($strUserAgent) ? "" : strtolower($strUserAgent)), 'wise-guys', max(0, 1 - 1))) !== false ? $pos + 1 : 0) > 0 || (($pos = stripos((is_null($strUserAgent) ? "" : strtolower($strUserAgent)), 'Xenu Link Sleuth', max(0, 1 - 1))) !== false ? $pos + 1 : 0) > 0 || (($pos = stripos((is_null($strUserAgent) ? "" : strtolower($strUserAgent)), 'Google', max(0, 1 - 1))) !== false ? $pos + 1 : 0) > 0 || (($pos = stripos((is_null($strUserAgent) ? "" : strtolower($strUserAgent)), 'exaleadcloudview', max(0, 1 - 1))) !== false ? $pos + 1 : 0) > 0;
}
// This function returns the first accept-language
/**
* Lib Getbrowserlanguage
*
*/
function Lib_GetBrowserLanguage()
{
    $strHTTP = "";
    $strLANG = "";
    $strTEMP = "";
    $intTEMP = 0;
    $strHTTP = (is_null(Request::server('ALL_RAW', '')) ? "" : strtolower(Request::server('ALL_RAW', '')));
    if ((($pos = stripos($strHTTP, 'accept-language:')) !== false ? $pos + 1 : 0)) {
        $strHTTP = trim(substr($strHTTP, max(0, 16 + (($pos = stripos($strHTTP, 'accept-language:')) !== false ? $pos + 1 : 0) - 1)));
        for ($intTEMP = 1; $intTEMP <= (is_null($strHTTP) ? 0 : strlen($strHTTP)); $intTEMP++) {
            $strTEMP = substr($strHTTP, max(0, $intTEMP - 1), 1);
            if (Str::isAlpha($strTEMP) || $strTEMP == '-') {
                $strLANG .= $strTEMP;
            } else {
                return $strLANG;
            }
        }
    }
}
/**
* Lib Ismobile
*
*/
function lib_isMobile()
{
    global $strUserAgent;
    return (($pos = stripos((is_null($strUserAgent) ? "" : strtolower($strUserAgent)), 'iPhone', max(0, 1 - 1))) !== false ? $pos + 1 : 0) > 0 || (($pos = stripos((is_null($strUserAgent) ? "" : strtolower($strUserAgent)), 'Android', max(0, 1 - 1))) !== false ? $pos + 1 : 0) > 0 || (($pos = stripos((is_null($strUserAgent) ? "" : strtolower($strUserAgent)), 'Blackberry', max(0, 1 - 1))) !== false ? $pos + 1 : 0) > 0 || (($pos = stripos((is_null($strUserAgent) ? "" : strtolower($strUserAgent)), 'Googlebot-Mobile', max(0, 1 - 1))) !== false ? $pos + 1 : 0) > 0 || (($pos = stripos((is_null($strUserAgent) ? "" : strtolower($strUserAgent)), 'Windows Phone OS', max(0, 1 - 1))) !== false ? $pos + 1 : 0) > 0;
}
/**
* Lib Istablet
*
*/
function lib_isTablet()
{
    global $strUserAgent;
    return (($pos = stripos((is_null($strUserAgent) ? "" : strtolower($strUserAgent)), 'iPad', max(0, 1 - 1))) !== false ? $pos + 1 : 0) > 0;
}
/**
* Lib Isgoogle
*
*/
function lib_isGoogle()
{
    global $strUserAgent;
    return (($pos = stripos((is_null($strUserAgent) ? "" : strtolower($strUserAgent)), 'googlebot', max(0, 1 - 1))) !== false ? $pos + 1 : 0) > 0;
}
// Browser detection
/**
* Lib Ismozilla
*
*/
function lib_isMozilla()
{
    global $strUserAgent;
    return (($pos = stripos($strUserAgent, 'Mozilla/5.', max(0, 1 - 1))) !== false ? $pos + 1 : 0) > 0 && (($pos = stripos($strUserAgent, 'MSIE')) !== false ? $pos + 1 : 0) == 0;
}
/**
* Lib Isfirefox
*
*/
function lib_isFireFox()
{
    global $strUserAgent;
    return (($pos = stripos((is_null($strUserAgent) ? "" : strtolower($strUserAgent)), 'firefox', max(0, 1 - 1))) !== false ? $pos + 1 : 0) > 0;
}
/**
* Lib Isnetscape
*
*/
function lib_isNetscape()
{
    return lib_isNetscape3() || lib_isNetscape4();
}
/**
* Lib Isnetscape3
*
*/
function lib_isNetscape3()
{
    global $strUserAgent;
    return (($pos = stripos($strUserAgent, 'Mozilla/3.', max(0, 1 - 1))) !== false ? $pos + 1 : 0) > 0 && (($pos = stripos($strUserAgent, 'MSIE')) !== false ? $pos + 1 : 0) == 0;
}
/**
* Lib Ismozilla5
*
*/
function lib_isMozilla5()
{
    global $strUserAgent;
    return (($pos = stripos($strUserAgent, 'Mozilla/5.', max(0, 1 - 1))) !== false ? $pos + 1 : 0) > 0 && (($pos = stripos($strUserAgent, 'MSIE')) !== false ? $pos + 1 : 0) == 0;
}
/**
* Lib Isnetscape4
*
*/
function lib_isNetscape4()
{
    global $strUserAgent;
    return (($pos = stripos($strUserAgent, 'Mozilla/4.', max(0, 1 - 1))) !== false ? $pos + 1 : 0) > 0 && (($pos = stripos($strUserAgent, 'MSIE')) !== false ? $pos + 1 : 0) == 0;
}
/**
* Lib Isnetscape6
*
*/
function lib_isNetscape6()
{
    global $strUserAgent;
    return (($pos = stripos($strUserAgent, 'Mozilla/5.', max(0, 1 - 1))) !== false ? $pos + 1 : 0) > 0 && (($pos = stripos($strUserAgent, 'MSIE')) !== false ? $pos + 1 : 0) == 0;
}
/**
* Lib Isie
*
*/
function lib_isIE()
{
    global $strUserAgent;
    return (($pos = stripos($strUserAgent, 'MSIE')) !== false ? $pos + 1 : 0) > 0 || (($pos = stripos($strUserAgent, 'Trident')) !== false ? $pos + 1 : 0) > 0;
}
/**
* Lib Isopera
*
*/
function lib_isOpera()
{
    global $strUserAgent;
    return (($pos = stripos($strUserAgent, 'Opera', max(0, 1 - 1))) !== false ? $pos + 1 : 0) > 0;
}
/**
* Lib Isie4Orup
*
*/
function lib_isIE4orUp()
{
    global $strUserAgent;
    return (($pos = stripos($strUserAgent, 'MSIE 5')) !== false ? $pos + 1 : 0) > 0 || (($pos = stripos($strUserAgent, 'MSIE 6')) !== false ? $pos + 1 : 0) > 0 || (($pos = stripos($strUserAgent, 'MSIE 7')) !== false ? $pos + 1 : 0) > 0 || (($pos = stripos($strUserAgent, 'MSIE 4')) !== false ? $pos + 1 : 0) > 0;
}
/**
* Lib Isie5Orup
*
*/
function lib_isIE5orUp()
{
    global $strUserAgent;
    return (($pos = stripos($strUserAgent, 'MSIE 5')) !== false ? $pos + 1 : 0) > 0 || (($pos = stripos($strUserAgent, 'MSIE 6')) !== false ? $pos + 1 : 0) > 0 || (($pos = stripos($strUserAgent, 'MSIE 7')) !== false ? $pos + 1 : 0) > 0;
}
/**
* Lib Isie6
*
*/
function lib_isIE6()
{
    global $strUserAgent;
    return (($pos = stripos($strUserAgent, 'MSIE 6')) !== false ? $pos + 1 : 0) > 0;
}
/**
* Lib Isie7
*
*/
function lib_isIE7()
{
    global $strUserAgent;
    return (($pos = stripos($strUserAgent, 'MSIE 7')) !== false ? $pos + 1 : 0) > 0;
}
/**
* Lib Isie8
*
*/
function lib_isIE8()
{
    global $strUserAgent;
    return (($pos = stripos($strUserAgent, 'MSIE 8')) !== false ? $pos + 1 : 0) > 0;
}
/**
* Lib Isie9
*
*/
function lib_isIE9()
{
    global $strUserAgent;
    return (($pos = stripos($strUserAgent, 'MSIE 9')) !== false ? $pos + 1 : 0) > 0;
}
/**
* Lib Ismac
*
*/
function lib_isMac()
{
    global $strUserAgent;
    return (($pos = stripos($strUserAgent, 'Mac', max(0, 1 - 1))) !== false ? $pos + 1 : 0) > 0;
}
/**
* Lib Iswindows
*
*/
function lib_isWindows()
{
    global $strUserAgent;
    return (($pos = stripos($strUserAgent, 'Windows', max(0, 1 - 1))) !== false ? $pos + 1 : 0) > 0;
}
/**
* Lib Isvista
*
*/
function lib_isVista()
{
    global $strUserAgent;
    return (($pos = stripos($strUserAgent, 'Windows NT 6.0', max(0, 1 - 1))) !== false ? $pos + 1 : 0) > 0;
}
?>
