<?php
use App\Library\Error;
use App\Library\Request;
use App\Library\Response;
use App\Library\Server;

require_once __DIR__ . '/bootstrap.inc';

/**
* Main
*/
function main()
{
    Response::noCache();
    $strError = "";
    $strDate = "";
    $strName = "";
    $strFilename = "";
    $strFieldContent = "";
    $strType = "";
    $strTag = "";
    $strArea = "";
    $strStart = "";
    $strEnd = "";
    $strFileContent = "";
    $strContent = "";
    $strPre = "";
    $strPost = "";
    $strFormat = "";
    $blnHTML = null;
    $intPosStart = 0;
    $intPosEnd = 0;
    $strTagValue = "";
    $strTagName = "";
    $arrTagElements = array();

    // TODO: Update the Title field of the tblSiteFiles
    $strError = lib_FormValRequired();
    if ($strError == '') {
        $strFileContent = '';
        $strFilename = Request::post(CONSTFILENAMEFLD, '');
        if ($strFilename != '') {
            if (file_exists($strFilename)) {
                // Check if file is writable
                if (!is_writable($strFilename)) {
                    $strError = 'File is readonly!';
                } else {
                    $strContent = file_get_contents($strFilename);
                    if ($strContent !== false && $strContent !== '') {
                        $intPosStart = (($pos = stripos($strContent, CONSTEDITSTART)) !== false ? $pos + 1 : 0);
                        if ($intPosStart > 0) {
                            $strFileContent = substr($strContent, 0, max(0, min($intPosStart - 1, strlen($strContent))));
                            while ($intPosStart > 0) {
                                $intPosEnd = (($pos = stripos($strContent, CONSTEDITEND, max(0, $intPosStart - 1))) !== false ? $pos + 1 : 0);
                                $strArea = substr($strContent, max(0, $intPosStart - 1), $intPosEnd - $intPosStart);
                                $strTag = substr($strArea, max(0, (is_null(CONSTEDITSTART) ? 0 : strlen(CONSTEDITSTART)) + 1 - 1), (($pos = stripos($strArea, '-->')) !== false ? $pos + 1 : 0) - 2 - (is_null(CONSTEDITSTART) ? 0 : strlen(CONSTEDITSTART)));
                                $strArea = substr($strArea, max(0, (($pos = stripos($strArea, '-->')) !== false ? $pos + 1 : 0) + 3 - 1));
                                $strType = 'text';
                                $strName = '';
                                $strPre = '';
                                $strPost = '';
                                $strFormat = 'dd/mm/yyyy';
                                $arrTagElements = explode(',', (is_null($strTag) ? "" : strtolower($strTag)));
                                foreach ($arrTagElements as $tagelt) {
                                    $strTagValue = trim(substr($tagelt, max(0, (($pos = stripos($tagelt, '=')) !== false ? $pos + 1 : 0) + 1 - 1)));
                                    $strTagName = trim(substr($tagelt, 0, max(0, min((($pos = stripos($tagelt, '=')) !== false ? $pos + 1 : 0) - 1, strlen($tagelt)))));
                                    switch ($strTagName) {
                                    case 'type':
                                        $strType = $strTagValue;
                                        break;
                                    case 'name':
                                        $strName = $strTagValue;
                                        break;
                                    case 'pre':
                                        $strPre = $strTagValue;
                                        break;
                                    case 'post':
                                        $strPost = $strTagValue;
                                        break;
                                    case 'html':
                                        $blnHTML = $strTagValue == 'yes';
                                        break;
                                    case 'required':
                                        if ($strTagValue == 'yes') {
                                                $strName = 'required-' . $strName;
                                            }
                                        break;
                                    case 'format':
                                        // make sure the year element is in English
                                            $strFormat = str_replace('J', 'Y', $strTagValue);
                                        break;
                                    }
                                }
                                $strName = str_replace(' ', '_', $strName);
                                $strFileContent .= CONSTEDITSTART . $strTag . ' -->' . $strPre;
                                if ($strType == 'datestamp') {
                                    $strDate = $strFormat;
                                    $strDate = str_replace('dd', date("j", strtotime(date("Y-m-d H:i:s"))), $strDate);
                                    $strDate = str_replace('mm', date("n", strtotime(date("Y-m-d H:i:s"))), $strDate);
                                    $strDate = str_replace('yyyy', date("Y", strtotime(date("Y-m-d H:i:s"))), $strDate);
                                    $strDate = str_replace('yy', substr(strval(date("Y", strtotime(date("Y-m-d H:i:s")))), max(0, strlen(strval(date("Y", strtotime(date("Y-m-d H:i:s"))))) - 2)), $strDate);
                                    $strFileContent .= $strDate;
                                } else {
                                    switch ($strType) {
                                    case 'keyword':
                                        $strFileContent .= '<meta name="KEYWORD" content="';
                                        break;
                                    case 'description':
                                        $strFileContent .= '<meta name="DESCRIPTION" content="';
                                        break;
                                    }
                                    $strFieldContent = Request::post($strName, '');
                                    if ($blnHTML) {
                                        $strFileContent .= $strFieldContent;
                                    } else {
                                        if ($strFieldContent != '') {
                                            if ($strType == 'text' || $strType == 'textarea') {
                                                $strFileContent .= Server::htmlEncode($strFieldContent);
                                            } else {
                                                $strFileContent .= $strFieldContent;
                                            }
                                        }
                                    }
                                    if ($strType == 'keyword' || $strType == 'description') {
                                        $strFileContent .= '">';
                                    }
                                }
                                $strFileContent .= $strPost . CONSTEDITEND;
                                $intPosStart = (($pos = stripos($strContent, CONSTEDITSTART, max(0, $intPosEnd + (is_null(CONSTEDITEND) ? 0 : strlen(CONSTEDITEND)) - 1))) !== false ? $pos + 1 : 0);
                                if ($intPosStart > 0) {
                                    $strStart = substr($strContent, max(0, $intPosEnd + (is_null(CONSTEDITEND) ? 0 : strlen(CONSTEDITEND)) - 1), $intPosStart - $intPosEnd + (is_null(CONSTEDITEND) ? 0 : strlen(CONSTEDITEND)));
                                    $strFileContent = $strFileContent + $strStart;
                                }
                            }
                            $strEnd = substr($strContent, max(0, $intPosEnd + (is_null(CONSTEDITEND) ? 0 : strlen(CONSTEDITEND)) - 1));
                            $strFileContent = $strFileContent + $strEnd;
                        }
                    } else {
                        $strError = 'File ' . $strFilename . ' is empty!';
                    }
                }
            } else {
                $strError = 'File ' . $strFilename . ' not found!';
            }
        }
    }

    // Write the file if no errors
    if ($strError == '') {
        if ($strFilename != '') {
            $result = file_put_contents($strFilename, $strFileContent);
            if ($result === false) {
                $strError = 'Could not write to file ' . $strFilename;
            } else {
                Response::redirect('template_edit.php?ID=' . Request::post(CONSTIDFLD, ''));
            }
        }
    }

    if ($strError != '') {
        Error::page('Saving was not completed succesfully', $strError, true);
    }
}
// Call main function
main();
?>
