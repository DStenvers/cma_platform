<?php
// This function returns if HTML is included in this string
/**
* Htmlinstring
*
*/
use App\Library\Html;

/**
 * Htmlinstring
 */
function HTMLInString($strInput)
{
    $strTmp = HTMLStrip(HTMLToAscii($strInput));
    return $strInput != $strTmp;
}
// Issue with chr 240 or 0, simply  using replace or instr don't work!?
// Quite a performance penalty i'm afraid.
/**
* Lib Htmlstripchar240
*
*/
function lib_HTMLStripChar240($strContents)
{
    $t = null;
    $strTemp = '';
    for ($t = 1; $t <= (is_null($strContents) ? 0 : strlen($strContents)); $t++) {
        if (ord(substr($strContents, max(0, $t - 1), 1)) == 240 || ord(substr($strContents, max(0, $t - 1), 1)) == 0) {
        } else {
            $strTemp .= substr($strContents, max(0, $t - 1), 1);
        }
        // ON ERROR GOTO 0 - Error handling via ErrorHandler.php
    }
    return $strTemp;
}
/**
* Htmldecompile
*
*/
function HTMLDecompile($strInput)
{
    $sResult = $strInput . '';
    $i = null;
    if ($sResult != '') {
        $sResult = str_ireplace('&aacute;', 'á', $sResult);
        $sResult = str_ireplace('&agrave;', 'à', $sResult);
        $sResult = str_ireplace('&auml;', 'ä', $sResult);
        $sResult = str_ireplace('&ccedil;', 'ç', $sResult);
        $sResult = str_ireplace('&eacute;', 'é', $sResult);
        $sResult = str_ireplace('&egrave;', 'è', $sResult);
        $sResult = str_ireplace('&ecirc;', 'ê', $sResult);
        $sResult = str_ireplace('&euml;', 'ë', $sResult);
        $sResult = str_ireplace('&iuml;', 'ï', $sResult);
        $sResult = str_ireplace('&oacute;', 'ó', $sResult);
        $sResult = str_ireplace('&ograve;', 'ò', $sResult);
        $sResult = str_ireplace('&ocirc;', 'ô', $sResult);
        $sResult = str_ireplace('&ouml;', 'ö', $sResult);
        $sResult = str_ireplace('&ntilde;', 'ñ', $sResult);
        $sResult = str_ireplace('&uuml;', 'ü', $sResult);
        $sResult = str_ireplace('&ugrave;', 'ù', $sResult);
        $sResult = str_ireplace('&uacute;', 'ú', $sResult);
        $sResult = str_ireplace('&iacute;', 'í', $sResult);
        $sResult = str_ireplace('&icirc;', 'î', $sResult);
        $sResult = str_ireplace('&igrave;', 'ì', $sResult);
        $sResult = str_ireplace('&iuml;', 'ï', $sResult);
        $sResult = str_ireplace('&acute;', '´', $sResult);
        $sResult = str_ireplace('&lsquo;', '' . "\u{2018}" . '', $sResult);
        $sResult = str_ireplace('&rsquo;', '' . "\u{2019}" . '', $sResult);
        $sResult = str_ireplace('&ldquo;', '' . "\u{201C}" . '', $sResult);
        $sResult = str_ireplace('&rdquo;', '' . "\u{201D}" . '', $sResult);
        $sResult = str_ireplace('&euro;', '€', $sResult);
        $sResult = str_ireplace('&hellip;', '…', $sResult);
        $sResult = str_ireplace('&copy;', '©', $sResult);
        $sResult = str_ireplace('&reg;', '®', $sResult);
        $sResult = str_ireplace('&Aacute;', 'Á', $sResult);
        $sResult = str_ireplace('&Agrave;', 'À', $sResult);
        $sResult = str_ireplace('&scaron;', 'š', $sResult);
        $sResult = str_ireplace('&ldquo;', chr(34), $sResult);
        $sResult = str_ireplace('&rdquo;', chr(34), $sResult);
        $sResult = str_ireplace('&lsquo;', chr(34), $sResult);
        $sResult = str_ireplace('&rsquo;', chr(34), $sResult);
        $sResult = str_ireplace('&quot;', chr(34), $sResult);
        $sResult = str_ireplace('&qt;', '>', $sResult);
        $sResult = str_ireplace('&lt;', '<', $sResult);
        $sResult = str_ireplace('&nbsp;', ' ', $sResult);
        $sResult = str_ireplace('<BR>', PHP_EOL, $sResult);
        $sResult = str_ireplace('<BR/>', PHP_EOL, $sResult);
        $sResult = str_ireplace('<P>', PHP_EOL, $sResult);
        $sResult = str_ireplace('<DIV>', PHP_EOL, $sResult);
        $sResult = str_ireplace('&ndash;', '-', $sResult);
        $sResult = str_ireplace('&hellip;', '...', $sResult);
        $sResult = str_ireplace('&shy;', '', $sResult);
        $sResult = str_ireplace('&bullet;', '-', $sResult);
        $sResult = str_ireplace('&bull;', '-', $sResult);
        $sResult = str_ireplace('&scaron;', 'š', $sResult);
        $sResult = str_ireplace('&sup2;', '²', $sResult);
        $sResult = str_ireplace('&sup3;', '³', $sResult);
        $sResult = str_ireplace('&inodot;', 'ı', $sResult);
        $sResult = str_ireplace('&#350;', 'ı', $sResult);
        for ($i = 1; $i <= 255; $i++) {
            $sResult = str_replace('&#' . $i . ';', chr($i), $sResult);
        }
    }
    return $sResult;
}
/**
* Lib Html Cleancomments
*
*/
function lib_html_CleanComments($sHTML)
{
    $sRetval = $sHTML;
    $objRegExp = new Regexp();
    $objRegExp->IgnoreCase = true;
    $objRegExp->Global = true;
    $objRegExp->Pattern = '<!--(.|\\n)+?-->';
    $sRetval = $objRegExp->Replace($sRetval, '');
    return $sRetval;
}
/**
* Lib Html Fixunicode
*
*/
function lib_HTML_fixUnicode($strInput)
{
    $sRetval = $strInput . '';
    if ($sRetval != '') {
        $sRetval = str_ireplace('â€' . "\u{201C}" . '', '-', $sRetval);
        $sRetval = str_ireplace('â€‹', '', $sRetval);
        $sRetval = str_ireplace('Ã©', '&eacute;', $sRetval);
        $sRetval = str_ireplace('Ã«', '&euml;', $sRetval);
        $sRetval = str_ireplace('Ã‹', '&Euml;', $sRetval);
        $sRetval = str_ireplace('Ã¨', '&egrave;', $sRetval);
        $sRetval = str_ireplace('Ãª', '&ecirc;', $sRetval);
        $sRetval = str_ireplace('Ã‰', '&Egrave;', $sRetval);
        $sRetval = str_ireplace('ï¿½', '&euml;', $sRetval);
        $sRetval = str_ireplace('Ã³', '&oacute;', $sRetval);
        $sRetval = str_ireplace('Ã¶', '&ouml;', $sRetval);
        $sRetval = str_ireplace('Åˆ', '&ograve;', $sRetval);
        $sRetval = str_ireplace('Ã´', '&ocirc;', $sRetval);
        $sRetval = str_ireplace('Ã¡', '&aacute;', $sRetval);
        $sRetval = str_ireplace('Ã¤', '&auml;', $sRetval);
        $sRetval = str_ireplace('Ã¢', '&acirc;', $sRetval);
        $sRetval = str_ireplace('Ã¼', '&uuml;', $sRetval);
        $sRetval = str_ireplace('Ã¯', '&iuml;', $sRetval);
        $sRetval = str_ireplace('Ã®', '&icirc;', $sRetval);
        $sRetval = str_ireplace('â€¢', '•', $sRetval);
        $sRetval = str_ireplace('–', '-', $sRetval);
        $sRetval = str_ireplace('â‚¬', '&euro;', $sRetval);
        $sRetval = str_ireplace('â€™', "'", $sRetval);
        $sRetval = str_ireplace('Ã§', '&ccedil;', $sRetval);
        $sRetval = str_ireplace('â€', '&lsquo;', $sRetval);
        $sRetval = str_ireplace('â€œ', '&lsquo;', $sRetval);
        $sRetval = str_ireplace('â€', '&rsquo;', $sRetval);
        $sRetval = str_ireplace('â€˜', '&rsquo;', $sRetval);
        $sRetval = str_ireplace('Â½', '&frac12;', $sRetval);
        $sRetval = str_ireplace('ı', '&inodot;', $sRetval);
    }
    return $sRetval;
}
/**
* Lib Html Charactercompile
*
*/
function lib_HTML_CharacterCompile($strInput)
{
    $sRetval = $strInput;
    $sRetval = Html::fixUnicode($sRetval);
    if ($sRetval != '') {
        $sRetval = str_ireplace('á', '&aacute;', $sRetval);
        $sRetval = str_ireplace('à', '&agrave;', $sRetval);
        $sRetval = str_ireplace('ä', '&auml;', $sRetval);
        $sRetval = str_ireplace('â', '&acirc;', $sRetval);
        $sRetval = str_ireplace('ć', '&cacute;', $sRetval);
        $sRetval = str_ireplace('ç', '&ccedil;', $sRetval);
        $sRetval = str_ireplace('&#233;', '&eacute;', $sRetval);
        $sRetval = str_ireplace('&#252;', '&uuml;', $sRetval);
        $sRetval = str_ireplace('é', '&eacute;', $sRetval);
        $sRetval = str_ireplace('è', '&egrave;', $sRetval);
        $sRetval = str_ireplace('ê', '&ecirc;', $sRetval);
        $sRetval = str_ireplace('ë', '&euml;', $sRetval);
        $sRetval = str_ireplace('ï', '&iuml;', $sRetval);
        $sRetval = str_ireplace('ó', '&oacute;', $sRetval);
        $sRetval = str_ireplace('ò', '&ograve;', $sRetval);
        $sRetval = str_ireplace('ô', '&ocirc;', $sRetval);
        $sRetval = str_ireplace('ö', '&ouml;', $sRetval);
        $sRetval = str_ireplace('ø', '&oslash;', $sRetval);
        $sRetval = str_ireplace('ñ', '&ntilde;', $sRetval);
        $sRetval = str_ireplace('ü', '&uuml;', $sRetval);
        $sRetval = str_ireplace('ù', '&ugrave;', $sRetval);
        $sRetval = str_ireplace('ú', '&uacute;', $sRetval);
        $sRetval = str_ireplace('í', '&iacute;', $sRetval);
        $sRetval = str_ireplace('î', '&icirc;', $sRetval);
        $sRetval = str_ireplace('ì', '&igrave;', $sRetval);
        $sRetval = str_ireplace('ï', '&iuml;', $sRetval);
        $sRetval = str_ireplace('´', '&acute;', $sRetval);
        $sRetval = str_ireplace('' . "\u{2018}" . '', '&lsquo;', $sRetval);
        $sRetval = str_ireplace('' . "\u{2019}" . '', '&rsquo;', $sRetval);
        $sRetval = str_ireplace('' . "\u{201C}" . '', '&ldquo;', $sRetval);
        $sRetval = str_ireplace('' . "\u{201D}" . '', '&rdquo;', $sRetval);
        $sRetval = str_ireplace('€', '&euro;', $sRetval);
        $sRetval = str_ireplace('…', '&hellip;', $sRetval);
        $sRetval = str_ireplace('©', '&copy;', $sRetval);
        $sRetval = str_ireplace('®', '&reg;', $sRetval);
        $sRetval = str_ireplace('Á', '&Aacute;', $sRetval);
        $sRetval = str_ireplace('À', '&Agrave;', $sRetval);
        $sRetval = str_ireplace('š', '&scaron;', $sRetval);
        $sRetval = str_ireplace('²', '&sup2;', $sRetval);
        $sRetval = str_ireplace('³', '&sup3;', $sRetval);
        $sRetval = str_ireplace('ı', '&inodot;', $sRetval);
    }
    return $sRetval;
}
/**
* Lib Html Removescripts
*
*/
function lib_HTML_RemoveScripts($strInput)
{
    $strOutput = preg_replace('/<script(.|\\n)+?<\/script>/si', '', $strInput);
    return $strOutput;
}
// TODO: Test if vbCrlf can be replaced by <BR> : i dont think it would always work as desired!
/**
* Htmlcompile
*
*/
function HTMLCompile($strInput)
{
    $sResult = $strInput;
    $sResult = str_ireplace('&', '&amp;', $sResult);
    $sResult = str_ireplace(chr(34), '&quot;', $sResult);
    $sResult = str_ireplace('>', '&qt;', $sResult);
    $sResult = str_ireplace('<', '&lt;', $sResult);
    $sResult = str_ireplace(' ', '&nbsp;', $sResult);
    return $sResult;
}
/**
* Lib Html Clear
*
*/
function lib_HTML_clear($sInput)
{
    $sRetval = null;
    $bInHTML = false;
    $char_tel = null;
    $char = null;
    if ((($pos = stripos($sInput, '<')) !== false ? $pos + 1 : 0) > 0) {
        $sInput = lib_HTML_RemoveScripts(lib_html_CleanComments($sInput));
        $sRetval = '';
        for ($char_tel = 1; $char_tel <= (is_null($sInput) ? 0 : strlen($sInput)); $char_tel++) {
            $char = substr($sInput, max(0, $char_tel - 1), 1);
            if ($char == '<') {
                $bInHTML = true;
            } else {
                if ($char == '>') {
                    $bInHTML = false;
                } else {
                    if (!$bInHTML) {
                        $sRetval .= $char;
                    }
                }
            }
        }
    } else {
        $sRetval = $sInput;
    }
    return Html::fixUnicode($sRetval);
}
// Strips all HTML codes, completely removes all javascript and style references
/**
* Htmltoascii
*
*/
function HTMLToAscii($strHTML)
{
    $strOutput = STRHTML;
    $objRegExp = null;
    if ($strOutput!= '') {
        $strOutput = lib_HTML_clear($strOutput);
        $strOutput = HTMLDecompile($strOutput);
        $strOutput = str_ireplace(chr(9), '', $strOutput);
        $strOutput = str_ireplace(PHP_EOL . ' ' . PHP_EOL, PHP_EOL, $strOutput);
        while ((($pos = stripos($strOutput, '  ', max(0, 1 - 1))) !== false ? $pos + 1 : 0) > 0) {
            $strOutput = str_replace('  ', ' ', $strOutput);
        }
        while ((($pos = stripos($strOutput, PHP_EOL . PHP_EOL, max(0, 1 - 1))) !== false ? $pos + 1 : 0) > 0) {
            $strOutput = str_replace(PHP_EOL . PHP_EOL, PHP_EOL, $strOutput);
        }
    }
    return $strOutput;
}
// returns a cleaned version of an html text
/**
* Htmlstrip
*
*/
function HTMLstrip($strHTML)
{
    $strOutput = STRHTML . '';
    // initialise components!
    $objRegExp = new Regexp();
    $objRegExp->IgnoreCase = true;
    $objRegExp->Global = true;
    // beautify the resulting text a little
    $objRegExp->Pattern = '<ul[^>]*><li[^>]*>';
    $strOutput = $objRegExp->Replace($strOutput, PHP_EOL . '-');
    $strOutput = str_ireplace('</td>', ' ', $strOutput);
    $strOutput = str_ireplace('</tr>', PHP_EOL, $strOutput);
    $strOutput = str_ireplace('<ul>', PHP_EOL, $strOutput);
    $strOutput = str_ireplace('</ul>', PHP_EOL . PHP_EOL, $strOutput);
    $strOutput = str_ireplace('<li>', PHP_EOL . '-', $strOutput);
    $strOutput = str_ireplace(XMLSNIPPET_START, PHP_EOL, $strOutput);
    $strOutput = str_ireplace(XMLSNIPPET_END, PHP_EOL, $strOutput);
    $objRegExp->Pattern = '<a[^>]*>';
    $strOutput = $objRegExp->Replace($strOutput, PHP_EOL);
    $objRegExp->Pattern = '<div[^>]*>';
    $strOutput = $objRegExp->Replace($strOutput, PHP_EOL);
    $objRegExp->Pattern = '<span[^>]*>';
    $strOutput = $objRegExp->Replace($strOutput, PHP_EOL);
    $objRegExp->Pattern = '<hr[^>]*>';
    $strOutput = $objRegExp->Replace($strOutput, PHP_EOL . '------------------------------' . PHP_EOL);
    $strOutput = str_ireplace(PHP_EOL . PHP_EOL . PHP_EOL, PHP_EOL . PHP_EOL, $strOutput);
    $strOutput = HTMLToAscii($strOutput);
    // remove any datastores
    // 2do: does not work!
    // objRegExp.Pattern = replace( XMLSnippet_Start, "[", "\[") & "[^\]]*]]"
    // strOutput = objRegExp.Replace(strOutput, " ")
    $objRegExp = null;
    return str_ireplace('  ', ' ', $strOutput);
}
?>
