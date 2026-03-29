<?php
use App\Library\Application;
use App\Library\Database;
use App\Library\Error;
use App\Library\Request;
use App\Library\Response;
use App\Library\Server;
use App\Library\Str;
use Cma\FormControlHelper;
use Cma\ToolbarHelper;

require_once __DIR__ . '/bootstrap.inc';

Response::noCache();
echo '<!DOCTYPE HTML>';
echo '<HTML>';
echo '<HEAD>';
cma_error_handler();
echo '<meta http-equiv="X-UA-Compatible" content="IE=edge" /> ';
echo '<script src=../library/formval_' . (Application::get('cma_language')) . '' . '.js></script> ';
echo '<script src="wizards/wizard.js"></script>';
echo '<script src="ckeditor/ckeditor.js" defer></script> ';
echo '<script src="include/all' . ((!Application::get('test')) ? ".min" : "") . '' . '.js"></script>';
echo '<script>;
var HTMLEdit = new Object();
HTMLEdit.image_resize_type = ' . FormControlHelper::IMG_MAXIMUM . ';
HTMLEdit.image_max_width   = ' . (Application::get('cma_htmledit_img_maxwidth')) . '' . ';
HTMLEdit.image_max_height  = ' . (Application::get('cma_htmledit_img_maxheight')) . '' . ';
HTMLEdit.image_path		   = "' . (Application::get('cma_htmledit_img_path')) . '' . '";
HTMLEdit.domain			   = "http://' . Server::getServerName() . '";
HTMLEdit.subpath		   = "' . (Application::get('base_path')) . '' . '";
HTMLEdit.maximized		   = false;
HTMLEdit.debug             = ' . ((Application::get('development')) ? "true" : "false") . '' . ';
HTMLEdit.allowBR           = ' . ((Application::get('cma_htmledit_allowBR')) ? "false" : "true") . '' . ';
/**
* Template Createfkeditor
*/
function template_CreateFKEditor( fieldname, bSpamJS, nHeight )
{
    var config = {};
    config.contentsCss = \'' . (Application::get('cma_htmledit_css')) . '' . '\';
    config.language = \'nl\';
    config.contentsLanguage = config.language;
    config.defaultLanguage = config.contentsLanguage;
    config.scayt_sLang = \'nl_NL\';
    config.skin = \'office2013_modified\';
    config.pasteFromWordPromptCleanup = true;
    config.height = nHeight.toString()+\'px\';
    config.extraAllowedContent=\'script;
    iframe(*){*}[*];
    table(*){*}[*];
    h1;
    h2;
    h3;
    td(*){*}[*];
    form(*){*}[*];
    input(*){*}[*];
    textarea(*){*}[*];
    hr(*){*}[*];
    tr(*){*}[*];
    div(*){*}[*];
    span(*){*}[*];
    img(*){*}[*];
    object(*){*}[*];
    embed(*){*}[*]  \';
    config.scayt_autoStartup=true;
    if (HTMLEdit.bSpamJS) {config.emailProtection = \'encode\';}
    config.disableObjectResizing = true;
    config.resize_enabled = false;
    <% if false then %>
    // TODO : config.disableNativeSpellChecker = false;??
    //		  filebrowserBrowseUrl
    //		  config.readOnly = true
    //		key handler: my_keyhandler
    //? \'PasteText\', \'PasteFromWord\',, \'Blockquote\', \'CreateDiv\'
    <% end if %>
    config.toolbar = [;
    { name: \'basic\', items: [ \'Cut\', \'Copy\', \'Paste\', \'PasteText\', \'-\', \'Undo\', \'Redo\', \'-\', \'Find\', \'Scayt\', \'-\', \'Bold\', \'Italic\', \'Underline\', \'-\', \'RemoveFormat\' ] },
    { name: \'paragraph\', items: [ \'-\', \'NumberedList\', \'BulletedList\', \'-\', \'Outdent\', \'Indent\', \'-\', \'JustifyLeft\', \'JustifyCenter\', \'JustifyRight\', \'JustifyBlock\'] },
    { name: \'links\', items: [ \'Link\', \'Unlink\', \'Anchor\' ] },
    { name: \'insert\', items: [ \'Image\', \'Table\', \'HorizontalRule\', \'SpecialChar\' ] },
    { name: \'tools\', items: [ \'Maximize\', \'ShowBlocks\', \'Source\' ] }
    ];
    if (HTMLEdit.allowBR) { config.enterMode = CKEDITOR.ENTER_BR; }
    CKEDITOR.replace( fieldname, config );
    CKEDITOR.instances[fieldname].on(\'instanceReady\', function ( event ) {
        var overridecmd_image = new CKEDITOR.command(CKEDITOR.instances[ event.sender.name ], {
            exec: function(editor){
                if (my_isCursorInImage( CKEDITOR.instances[ event.sender.name ] )){
                    my_image_properties( CKEDITOR.instances[ event.sender.name ] );
                } else {
                    my_InsertImage( CKEDITOR.instances[ event.sender.name ] );
                }
            }
        });
        CKEDITOR.instances[ event.sender.name ].commands.image.exec = overridecmd_image.exec;
        var overridecmd_table = new CKEDITOR.command( CKEDITOR.instances[ event.sender.name ], {
            exec: function(editor){
                if ( my_isCursorInTable( CKEDITOR.instances[ event.sender.name ] ) ) {
                    my_table_properties( CKEDITOR.instances[ event.sender.name ] )
                } else {
                    my_InsertTable( CKEDITOR.instances[ event.sender.name ] );
                }
            }
        });
        CKEDITOR.instances[ event.sender.name ].commands.table.exec = overridecmd_table.exec;
        var overridecmd_table_prop = new CKEDITOR.command( CKEDITOR.instances[ event.sender.name ], {
            exec: function(editor){
                if ( my_isCursorInTable( CKEDITOR.instances[ event.sender.name ] ) ) {
                    my_table_properties( CKEDITOR.instances[ event.sender.name ] )
                } else {
                    my_InsertTable( CKEDITOR.instances[ event.sender.name ] );
                }
            }
        });
        CKEDITOR.instances[ event.sender.name ].commands.tableProperties.exec = overridecmd_table_prop.exec;
        var overridecmd_link = new CKEDITOR.command( CKEDITOR.instances[ event.sender.name ], {
            exec: function(editor){
                if ( my_isCursorInAnchor ( CKEDITOR.instances[ event.sender.name ] ) ) {
                    my_anchor_properties( CKEDITOR.instances[ event.sender.name ] )
                } else {
                    my_InsertLink( CKEDITOR.instances[ event.sender.name ] );
                }
            }
        });
        CKEDITOR.instances[ event.sender.name ].commands.link.exec = overridecmd_link.exec;
    });
}
</script>';
// For Documentation, see Help_Template.html
/**
* Main
*
*/
function main()
{
    $ScriptObject = null;
    $MyFile = null;
    $strID = Request::queryInt('ID');

    // Validate required ID parameter
    if (empty($strID)) {
        echo '</head><body class="contentbody"><lib-message type="error">Template ID parameter is required.</lib-message></body></html>';
        return;
    }

    $sql = null;
    $strURL = "";
    $strFilename = "";
    $strContent = "";
    $intPosStart = 0;
    $intPosEnd = 0;
    $strArea = "";
    $strError = "";
    $strTag = "";
    $arrTagElements = array();
    $strLen = "";
    $intFieldLen = 0;
    $strType = "";
    $strName = "";
    $strPre = "";
    $strPost = "";
    $tagElt = null;
    $strTagName = "";
    $strTagValue = "";
    $strPath = "";
    $strHeight = "";
    $blnHTML = null;
    $blnRequired = null;
    $intHTMLEdits = 0;
    $sql = ' SELECT * from tblSiteFiles WHERE ID=' . $strID;
    $rs = Database::openRS($sql, 'data', adOpenForwardOnly, adLockReadOnly);
    if ($rs === null) {
        throw new \Exception('Database query failed: ' . Database::getLastError());
    }
    if (!$rs->EOF) {
        $strURL = Application::get('base_path', '') . Str::trim($rs->fields['FilePath']) . Str::trim($rs->fields['FileName']);
        $strPath = Request::server('SERVER_NAME', '') . Application::get('base_path', '') . Str::trim($rs->fields['FilePath']);
        $strFilename = (is_null(Server::mapPath(Application::get('base_path', '') . Str::trim($rs->fields['FilePath']) . Str::trim($rs->fields['FileName']))) ? "" : strtolower(Server::mapPath(Application::get('base_path', '') . Str::trim($rs->fields['FilePath']) . Str::trim($rs->fields['FileName']))));
    }
    // Toolbar for editing purposes
    ToolbarHelper::writeJS();
    echo '</head><BODY style=margin:0px marginwidth=0 marginheight=0 class=contentbody onload=javascript:clearaction() onunload=javascript:checkchanged()>';
    echo '<form name=main action=template_post.php method=post onsubmit="return form_valid(this)">';
    ToolbarStart(true);
    ToolbarHelper::recordButtons(0, false, true, false, false, false, false);
    ToolbarHelper::separator();
    ToolbarHelper::linearButton('<a target=_blank href="' . $strURL . '">', '<span class="lnr lnr-preview" data-tooltip=\'' . $lang_tb_preview . "'></span>", true, 'Bekijk');
    ToolbarHelper::end(true);
    // TODO: make save work
    echo '<input type=hidden name=' . CONSTIDFLD . ' value=' . $strID . '>';
    if (!file_exists($strFilename)) {
        echo "'" . $strFilename . "' niet gevonden";
        exit();
    }
    echo '<input type=hidden name=' . CONSTFILENAMEFLD . ' value="' . Server::htmlEncode($strFilename) . '">';
    echo '<input type=hidden id=actie name=Actie value="">';
    $strContent = file_get_contents($strFilename);
    if ($strContent !== false) {
        if (!$strContent== '') {
                $intPosStart = (($pos = stripos($strContent, CONSTEDITSTART)) !== false ? $pos + 1 : 0);
                echo '<table width=100% CELLSPACING=0 CELLPADDING=0>';
                while ($intPosStart > 0) {
                    $intPosEnd = (($pos = stripos($strContent, CONSTEDITEND, max(0, $intPosStart - 1))) !== false ? $pos + 1 : 0);
                    $strArea = substr($strContent, max(0, $intPosStart - 1), $intPosEnd - $intPosStart);
                    $strTag = substr($strArea, max(0, (is_null(CONSTEDITSTART) ? 0 : strlen(CONSTEDITSTART)) + 1 - 1), (($pos = stripos($strArea, '-->')) !== false ? $pos + 1 : 0) - 2 - (is_null(CONSTEDITSTART) ? 0 : strlen(CONSTEDITSTART)));
                    $strArea = substr($strArea, max(0, (($pos = stripos($strArea, '-->')) !== false ? $pos + 1 : 0) + 3 - 1));
                    $strType = 'text';
                    $strLen = '80';
                    $strName = 'field';
                    $strPre = '';
                    $strPost = '';
                    $strHeight = '6';
                    $blnHTML = false;
                    $blnRequired = false;
                    $arrTagElements = explode(',', (is_null($strTag) ? "" : strtolower($strTag)));
                    foreach ($arrTagElements as $tagElt) {
                        $strTagValue = trim(substr($tagElt, max(0, (($pos = stripos($tagElt, '=')) !== false ? $pos + 1 : 0) + 1 - 1)));
                        $strTagName = trim(substr($tagElt, 0, max(0, min((($pos = stripos($tagElt, '=')) !== false ? $pos + 1 : 0) - 1, strlen($tagElt)))));
                        switch ($strTagName) {
                        case 'type':
                            $strType = $strTagValue;
                            break;
                        case 'size':
                            $strLen = $strTagValue;
                            break;
                        case 'name':
                            $strName = $strTagValue;
                            break;
                        case 'html':
                            $blnHTML = $strTagValue == 'yes';
                            break;
                        case 'required':
                            $blnRequired = $strTagValue == 'yes';
                            break;
                        case 'pre':
                            $strPre = $strTagValue;
                            break;
                        case 'post':
                            $strPost = $strTagValue;
                            break;
                        case 'height':
                            $strHeight = $strTagValue;
                            break;
                        case 'format':
                            // not used yet
                            break;
                        }
                    }
                    if ($strType != 'datestamp') {
                        echo '<tr><td class=c1>';
                        if ($strPre != '') {
                            $strArea = trim(substr($strArea, max(0, (($pos = stripos((is_null($strArea) ? "" : strtolower($strArea)), $strPre)) !== false ? $pos + 1 : 0) + (is_null($strPre) ? 0 : strlen($strPre)) - 1)));
                        }
                        if ($strPost != '') {
                            $strArea = trim(substr($strArea, 0, max(0, min((($pos = stripos((is_null($strArea) ? "" : strtolower($strArea)), $strPost)) !== false ? $pos + 1 : 0) - 1, strlen($strArea)))));
                        }
                        if ($strType == 'keyword' || $strType == 'description') {
                            $strArea = RetrieveMetaContent($strArea);
                        }
                        echo (is_null(substr($strName, 0, max(0, min(1, strlen($strName))))) ? "" : strtoupper(substr($strName, 0, max(0, min(1, strlen($strName)))))) . substr($strName, max(0, 2 - 1)) . '</TD><TD class=c2 width=1% >';
                        if ($blnRequired) {
                            echo '<span class="req" title="' . $lang_required_entry . '">*</span>';
                        }
                        echo '</TD>';
                        echo '<TD class=c3>';
                        $strName = str_replace(' ', '_', $strName . '');
                        if ($blnRequired) {
                            $strName = 'required-' . $strName;
                        }
                        switch ($strType) {
                        case 'text':
                        case 'title':
                            $intFieldLen = intval(round(floatval($strLen)));
                                if ($intFieldLen > 70) {
                                    $intFieldLen = 70;
                                }
                                echo '<input type=text size="' . $intFieldLen . '" maxsize="' . $strLen . '" name="' . $strName . '" value="' . $strArea . '">';
                            break;
                        case 'textarea':
                        case 'keyword':
                        case 'description':
                            if ($blnHTML) {
                                    $intHTMLEdits = $intHTMLEdits + 1;
                                    echo '<textarea style=display:none rows=12 cols=78 name="' . $strName . '">' . Server::htmlEncode($strArea . '') . '</textarea>';
                                    echo "<script>template_CreateFKEditor( '" . $strName . "', false, " . intval(round(floatval($strHeight))) * 40 . ');</script>';
                                } else {
                                    echo '<textarea name="' . $strName . '" style=width:100% rows=' . $strHeight . '>' . $strArea . '</textarea>';
                                }
                            break;
                        }
                        echo '</td></tr>';
                    }
                    $intPosStart = (($pos = stripos($strContent, CONSTEDITSTART, max(0, $intPosEnd + (is_null(CONSTEDITEND) ? 0 : strlen(CONSTEDITEND)) - 1))) !== false ? $pos + 1 : 0);
                }
                echo '</table>';
        } else {
            $strError = 'File ' . $strFilename . ' is empty!';
        }
    }
    if ($strError != '') {
        Error::show($strError);
    }
    echo '</form></body>';
    echo '<script>';
    echo 'var sp=(document.body.offsetHeight-document.body.scrollHeight)/';
    echo $intHTMLEdits;
    echo ';';
    echo 'if (sp>0) for(var f=0;f<window.frames.length;f++) window.frames[f].frameElement.style.posHeight=window.frames[f].frameElement.style.posHeight+sp;';
    echo '</script>';
    echo '</html>';
}
// Retrieves the meta content of a tag
/**
* Retrievemetacontent
*
*/
function RetrieveMetaContent($strTag)
{
    $strRetval = "";
    $intPos = 0;
    $strStartTag = 'content="';
    $intPos = (($pos = stripos($strTag, $strStartTag)) !== false ? $pos + 1 : 0);
    if ($intPos > 0) {
        $strRetval = substr($strTag, max(0, $intPos + (is_null($strStartTag) ? 0 : strlen($strStartTag)) - 1));
        $strRetval = substr($strRetval, 0, max(0, min((($pos = stripos($strRetval, chr(34))) !== false ? $pos + 1 : 0) - 1, strlen($strRetval))));
    }
    return $strRetval;
}
main();
?>
