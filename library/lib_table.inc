<?php
use App\Library\Application;
use App\Library\Request;

if (!defined('CONST_STRSORTPARAM')) define("CONST_STRSORTPARAM", 'Sort');
// Displays a header with sort-option
/**
* Writesortheader
*
*/
function writeSortHeader($strCurrentSort, $strWidth, $strDisplay, $strField)
{
    $strPrevParam = "";
    $strParam = "";
    // Store all previous parameters except for the sorting parameter
    $strPrevParam = '';
    foreach ($_GET as $key => $strParam) {
        if ((is_null($strParam) ? "" : strtolower($strParam)) != (is_null(CONST_STRSORTPARAM) ? "" : strtolower(CONST_STRSORTPARAM))) {
            $strPrevParam .= $strParam . '=' . Request::query($strParam, '') . '&';
        }
    }
    if ($strWidth != '') {
        $strWidth = ' width=' + $strWidth;
    }
    echo '<TH' . $strWidth . ' nowrap><a href=' . Request::server('PHP_SELF', '') . '?' . $strPrevParam . CONST_STRSORTPARAM . '=';
    $strDisplay = '<SPAN class="sort_text">' . $strDisplay . '</SPAN>';
    if (substr($strCurrentSort, 0, max(0, min((is_null($strField) ? 0 : strlen($strField)), strlen($strCurrentSort)))) == $strField) {
        if (substr($strCurrentSort, max(0, strlen($strCurrentSort) - 5)) == ' DESC') {
            echo str_replace(' ', '%20', $strField) . '>' . $strDisplay . '</a><img class="sort_icon" src=' . Application::get('base_path', '') . 'library/images/sort_desc.gif';
        } else {
            echo str_replace(' ', '%20', $strField . '%20DESC') . '>' . $strDisplay . '</a><img class="sort_icon" src=' . Application::get('base_path', '') . 'library/images/sort_asc.gif';
        }
    } else {
        echo str_replace(' ', '%20', $strField) . '>' . $strDisplay . '</a><img class="sort_icon" src=' . Application::get('pict_pixel', '');
    }
    echo '></TH>';
}
?>
