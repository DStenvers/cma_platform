<?php
/**
* Lib Urldecode
*
*/

// Include-guard: dit bestand weigert zichzelf een tweede keer uit te voeren.
// require_once dekt het gewone geval al — die lost het pad eerst op — maar niet een
// kale require/include, en niet een pad dat via een junction anders uitkomt. De naam
// is uniek per bestand, zodat de guard niet van de aanroeper afhangt.
if (defined('INC_LIB_URL')) { return; }
define('INC_LIB_URL', 1);
use App\Library\Html;
use App\Library\Request;
use App\Library\Server;

/**
 * Lib Urldecode
 */
function lib_UrlDecode($str)
{
    $list = array();
    $strLen = "";
    $i = null;
    $val = null;
    $depth = null;
    $sT = null;
    $sR = null;
    $by = null;
    if ($str!= '') {
        $list = CreateObject('System.Collections.ArrayList');
        $strLen = (is_null($str) ? 0 : strlen($str));
        for ($i = 1; $i <= $strLen; $i++) {
            $sT = substr($str, max(0, $i - 1), 1);
            if ($sT == '%') {
                if ($i + 2 <= $strLen) {
                    $list->Add((function($value) {
                        $intVal = (int)round((float)$value);
                        if ($intVal < 0 || $intVal > 255) {
                            throw new Exception("Overflow");
                        }
                        return $intVal;
                    })('&H' . substr($str, max(0, $i + 1 - 1), 2)));
                    $i = $i + 2;
                }
            } else {
                $list->Add(ord($sT));
            }
        }
        $depth = 0;
        foreach ($list->ToArray() as $by) {
            if ($by && 128) {
                if ($by && 64 == 0) {
                    if ($depth == 0) {
                        throw new Exception("Error " . 5, 5);
                    }
                    $val = $val * $pow[2][6] + $by && 63;
                    $depth = $depth - 1;
                    if ($depth == 0) {
                        $sR .= $chrw[$val];
                        $val = 0;
                    }
                } elseif ($by && 32 == 0) {
                    if ($depth > 0) {
                        throw new Exception("Error " . 5, 5);
                    }
                    $val = $by && 31;
                    $depth = 1;
                } elseif ($by && 16 == 0) {
                    if ($depth > 0) {
                        throw new Exception("Error " . 5, 5);
                    }
                    $val = $by && 15;
                    $depth = 2;
                } else {
                    throw new Exception("Error " . 5, 5);
                }
            } else {
                if ($depth > 0) {
                    throw new Exception("Error " . 5, 5);
                }
                $sR .= $chrw[$by];
            }
        }
        if ($depth > 0) {
            throw new Exception("Error " . 5, 5);
        } else {
            $sR = '';
        }
    }
    return $sR;
}
// For Search friendly url conversion of titles (f.e. https://site.com/pagina/123/search_friendly_name.html )
// Based upon Google information changed the _ for -
/**
* Lib Friendlyurl
*
*/
function lib_friendlyURL($sUrl)
{
    // Zoekvriendelijk URL-deel (slug): kleine letters, cijfers en punten, verder alleen enkele
    // streepjes. Een witte lijst: alles wat geen a-z, 0-9 of punt is wordt een streepje, zodat
    // curly quotes, (R), zachte afbreekstreepjes en harde spaties niet in de URL belanden.
    // Zelfde regels als lib_friendlyURL() in de ASP-library, zodat beide dezelfde slug maken.
    $sTemp = Str::replaceDiacritics(HTMLToAscii(Html::fixUnicode($sUrl . '')));
    $sTemp = str_ireplace('Ã«', 'e', $sTemp);            // foutief gecodeerde e-trema
    $sTemp = mb_strtolower($sTemp, 'UTF-8');

    // tekens met een eigen vertaling
    $sTemp = str_replace(['&', '%'], ['-en-', '-procent-'], $sTemp);
    $sTemp = strtr($sTemp, [
        'ß' => 'ss', 'æ' => 'ae', 'œ' => 'oe', 'ø' => 'o', 'ã' => 'a', 'å' => 'a',
        'û' => 'u', 'ý' => 'y', 'ñ' => 'n', 'ç' => 'c',
    ]);

    // apostrofs, aanhalingstekens en zachte afbreekstreepjes weglaten zonder streepje
    $sTemp = preg_replace("/['\"`\x{00B4}\x{2018}\x{2019}\x{201A}\x{201C}\x{201D}\x{201E}\x{00AD}]/u", '', $sTemp);

    // al het andere buiten a-z, 0-9 en de punt wordt één streepje
    $sTemp = preg_replace('/[^a-z0-9.]+/', '-', $sTemp);
    $sTemp = preg_replace('/\.{2,}/', '.', $sTemp);
    $sTemp = preg_replace('/-\.+(?=-)/', '', $sTemp);      // losse punten tussen streepjes
    $sTemp = preg_replace('/-{2,}/', '-', $sTemp);
    $sTemp = preg_replace('/^[-.]+|[-.]+$/', '', $sTemp);
    return (string) $sTemp;
}
// Create tooltips automatically, careful not to create links inside links.
/**
* Lib Createtooltip
*
*/
function Lib_CreateToolTip($strText, $strWoord, $strTooltip)
{
    return Lib_CreateHyperlinks($strText, $strWoord, '<a href=# class=tt>' . Server::htmlEncode($strWoord) . '<span class=tooltip><span class=tt_top></span><span class=tt_middle>' . $strTooltip . '</span><span class=tt_bottom></span></span></a>');
}
// Create hyperlinks automatically, careful not to create links inside links.
/**
* Lib Createhyperlinks
*
*/
function Lib_CreateHyperlinks($strInput, $strText, $strReplacement)
{
    $iCurrentLocation = 1;
    $strOutput = '';
    $iLinkStart = null;
    $iLinkEnd = null;
    while ((($pos = stripos((is_null($strInput) ? "" : strtolower($strInput)), '<a', max(0, $iCurrentLocation - 1))) !== false ? $pos + 1 : 0) != 0) {
        $iLinkStart = (($pos = stripos((is_null($strInput) ? "" : strtolower($strInput)), '<a', max(0, $iCurrentLocation - 1))) !== false ? $pos + 1 : 0);
        $iLinkEnd = (($pos = stripos((is_null($strInput) ? "" : strtolower($strInput)), '</a>', max(0, $iCurrentLocation - 1))) !== false ? $pos + 1 : 0) + 4;
        if ($iLinkEnd > $iLinkStart) {
            $strOutput .= str_ireplace($strText, $strReplacement, substr($strInput, max(0, $iCurrentLocation - 1), $iLinkStart - $iCurrentLocation)) . substr($strInput, max(0, $iLinkStart - 1), $iLinkEnd - $iLinkStart);
            $iCurrentLocation = $iLinkEnd;
        } else {
            $strOutput .= str_ireplace($strText, $strReplacement, substr($strInput, max(0, $iCurrentLocation - 1)));
            $iCurrentLocation = (is_null($strInput) ? 0 : strlen($strInput));
        }
    }
    // add the rest to strOutput
    if ($iCurrentLocation < (is_null($strInput) ? 0 : strlen($strInput))) {
        $strOutput .= str_ireplace($strText, $strReplacement, substr($strInput, max(0, $iCurrentLocation - 1)));
    }
    return $strOutput;
}
// Wrapper
/**
* Lib Urlcreatelinks
*
*/
function Lib_UrlCreateLinks($strInput)
{
    return LinkURLs($strInput);
}
// Replaces website addresses by actual links (opening in a new window if domain is different from the current) ....
/**
* Linkurls
*
*/
function LinkURLs($strInput)
{
    $iCurrentLocation = null;
    $strOutput = "";
    $strLinkText = "";
    $iLinkStart = null;
    $iLinkEnd = null;
    $blnSearchOn = null;
    $strTarget = "";
    $strSite = "";
    $iCurrentLocation = 1;
    // make sure http is before any www reference... Not an award-winning one-liner, but works like a charm..
    $strInput = str_replace('https://https://', 'https://', str_replace('www.', 'https://www.', $strInput));
    while ((($pos = stripos((is_null($strInput) ? "" : strtolower($strInput)), 'https://', max(0, $iCurrentLocation - 1))) !== false ? $pos + 1 : 0) != 0) {
        $iLinkStart = (($pos = stripos((is_null($strInput) ? "" : strtolower($strInput)), 'https://', max(0, $iCurrentLocation - 1))) !== false ? $pos + 1 : 0);
        // iLinkEnd   = InStr(iLinkStart, strInput, " ", 1)
        $iLinkEnd = $iLinkStart + 1;
        $blnSearchOn = true;
        while ($blnSearchOn) {
            if ((substr($strInput, max(0, $iLinkEnd - 1), 1) == '<') || (substr($strInput, max(0, $iLinkEnd - 1), 1) == chr(10)) || (substr($strInput, max(0, $iLinkEnd - 1), 1) == chr(13)) || (substr($strInput, max(0, $iLinkEnd - 1), 1) == ' ') || (substr($strInput, max(0, $iLinkEnd - 1), 1) == '(') || (substr($strInput, max(0, $iLinkEnd - 1), 1) == ')')) {
                $blnSearchOn = false;
            } else {
                if ($iLinkEnd <= (is_null($strInput) ? 0 : strlen($strInput))) {
                    $iLinkEnd = $iLinkEnd + 1;
                } else {
                    $blnSearchOn = false;
                }
            }
        }
        // eventuele leestekens aan het einde skippen, deze mogen dus wel in een url/email adres opgenomen zijn
        if ((($pos = stripos(',:;.?! ', substr($strInput, max(0, $iLinkEnd - 1 - 1), 1), max(0, 1 - 1))) !== false ? $pos + 1 : 0) > 0) {
            $iLinkEnd = $iLinkEnd - 1;
        }
        $strOutput .= substr($strInput, max(0, $iCurrentLocation - 1), $iLinkStart - $iCurrentLocation);
        $strLinkText = str_replace('https://', '', substr($strInput, max(0, $iLinkStart - 1), $iLinkEnd - $iLinkStart));
        $strTarget = '_blank';
        // current site?
        $strSite = Request::server('SERVER_NAME', '');
        if (substr((is_null($strLinkText) ? "" : strtolower($strLinkText)), 0, max(0, min((is_null($strSite) ? 0 : strlen($strSite)), strlen((is_null($strLinkText) ? "" : strtolower($strLinkText)))))) == (is_null($strSite) ? "" : strtolower($strSite))) {
            $strTarget = '_self';
        }
        $strOutput .= '<a href="https://' . $strLinkText . '" target=' . $strTarget . '>' . $strLinkText . '</a>';
        $iCurrentLocation = $iLinkEnd;
    }
    $strOutput .= substr($strInput, max(0, $iCurrentLocation - 1));
    // E-mail addresses: every real address (filter_var) becomes an anti-spam
    // link; an @ in other text stays. Not one already inside a mailto: href.
    $strOutput = preg_replace_callback(
        '/(?<!mailto:)(?<![\\w.-])([A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\\.[A-Za-z]{2,})/',
        static fn(array $m): string => filter_var($m[1], FILTER_VALIDATE_EMAIL) !== false ? Lib_LinkEmailAntiSpam($m[1]) : $m[1],
        $strOutput
    ) ?? $strOutput;
    return $strOutput;
}
// Create an email link safe for email scrapers (requires library.js in the resulting HTML to be included!)
/**
* Lib Linkemailantispam
*
*/
function Lib_LinkEmailAntiSpam($email)
{
    $intSplit = 0;
    $strUser = "";
    $strDomain = "";
    if ($email!= '') {
        $intSplit = (($pos = stripos($email, '@', max(0, 1 - 1))) !== false ? $pos + 1 : 0);
        if ($intSplit > 0) {
            $strUser = substr($email, 0, max(0, min($intSplit - 1, strlen($email))));
            $strDomain = substr($email, max(0, $intSplit + 1 - 1));
            return "<script>lib_mail('" . $strUser . "','" . $strDomain . "','');</script>" . str_replace('@', '<nobr>@</nobr>', $email) . '</a>';
        } else {
            return $email;
        }
    } else {
        return $email;
    }
}
?>
