<?php
use App\Library\Application;
use App\Library\Request;
use App\Library\Response;
use Cma\FormControlHelper;

require_once __DIR__ . '/../bootstrap.inc';

/**
* Main
*/
function main()
{
    Response::noCache();
    // TODO: Change!
    set_time_limit(600);
    $parFile = Request::query('file', '');
    echo '<HTML><HEAD>';
    cma_error_handler();
    echo '<TITLE>Wizard implementatie !</TITLE>';
    echo '<style> button { font-family:verdana; font-size : var(--font-size-xs) } </style>';
    echo '<script src="../minify.php?f=wizards/wizard.js,../library/colorpicker.js"></script>';
    $strAlignment = '';
    $strBorder = 0;
    $strBorderColor = '#000000';
    $strMargin = 10;
    $strAlternate = '';
    $cmaLanguage = Application::get('CMA_Language', 'NL');
    // Check if this is an image upload from query parameters (modern method)
    $isImage = Request::queryInt('image') === 1;

    echo '<script>
    var strType;
    var strPreType;
    var last_page;

    // Get dialog arguments from various sources (modern approach first)
    var wizardArgs = {};
    // Try query parameters first (modern approach via lib-dialog)
    var urlParams = new URLSearchParams(window.location.search);
    wizardArgs["is_image"] = urlParams.get("image") === "1" || ' . ($isImage ? 'true' : 'false') . ';
    wizardArgs["include_layout"] = urlParams.get("layout") !== "0";
    wizardArgs["resizetype"] = parseInt(urlParams.get("resizetype") || "0");
    wizardArgs["resizeheight"] = parseInt(urlParams.get("resizeheight") || "0");
    wizardArgs["resizewidth"] = parseInt(urlParams.get("resizewidth") || "0");
    wizardArgs["path"] = urlParams.get("basepath") || "";
    wizardArgs["fieldname"] = urlParams.get("fieldname") || "";
    // Try sessionStorage as fallback
    try {
        var storedArgs = sessionStorage.getItem("cma_file_wizard_args");
        if (storedArgs) {
            var parsed = JSON.parse(storedArgs);
            for (var key in parsed) {
                if (parsed.hasOwnProperty(key) && !wizardArgs[key]) {
                    wizardArgs[key] = parsed[key];
                }
            }
        }
    } catch (e) {}
    // Finally, try deprecated dialogArguments
    if (typeof top !== "undefined" && top.window && top.window.dialogArguments) {
        var dlgArgs = top.window.dialogArguments;
        for (var key in dlgArgs) {
            if (dlgArgs.hasOwnProperty(key) && !wizardArgs[key]) {
                wizardArgs[key] = dlgArgs[key];
            }
        }
    }
    // Make wizardArgs available globally for callback
    window.wizardArgs = wizardArgs;
    ';
    if ($cmaLanguage === 'UK') {
        echo '
    if (wizardArgs["is_image"]) {
        strType     = "image";
        strPreType  = "an";
    } else {
        strType	  = "file";
        strPreType  = "a";
    }
        ';
    } else {
        echo '
    if (wizardArgs["is_image"]) {
        strType     = "afbeelding";
    } else {
        strType	  = "bestand";
    }
    strPreType  = "het";
        ';
    }
    $basePath = Request::query('basepath', '');
    $uploadUrl = Request::addAllToURL("file_frameset.php?upload=J");
    $noUploadUrl = Request::addAllToURL("file_frameset.php");

    // Handle file path workaround
    $sFile = $parFile;
    $intPos = strrpos($sFile, "/");
    $filePathWorkaround = '';
    if ($intPos !== false && $intPos > 0) {
        $sFilePath = substr($sFile, 0, $intPos + 1);
        $filePathWorkaround = 'arrResult["filename"] = arrResult["filename"].replace("' . $sFilePath . $sFilePath . '", "' . $sFilePath . '");';
    }

    echo '
    // determine last page!
    if (!wizardArgs["include_layout"]) {
        last_page = 1;
    } else {
        last_page = 2;
    }
    /**
    * Checkfilename
    */
    function CheckFilename()
    {
        if (filename.value!="")  {
            return true;
        } else {
            modal_alert ("Selecteer " + strPreType + " " + strType + " voordat u verder gaat.");
            return false;
        }
    }
    /**
    * Call Wizardgetnextpage
    */
    function call_WizardGetNextPage( current_page )
    {
        switch ( current_page ) {
        case 0:
            var strNewSrc = unescape( UPLOAD[0].checked ? \'' . $uploadUrl . '\' : \'' . $noUploadUrl . '\');
                if (document.getElementById("fileselect").src!=strNewSrc) {
                    document.getElementById("fileselect").src=strNewSrc ;
                }
                return 1;
            case 1:
                return ( CheckFilename() ? last_page : current_page);
                default:
                    return ( (current_page < last_page) ? current_page + 1 : current_page );
                    }
                }
                /**
                * Call Wizardfinishpressed
                */
                function call_WizardFinishPressed()
                {
                    // return all attributes that are required
                    var arrResult  = new Array;
                    var strFullSpec = "";
                    var strStyle = "";
                    arrResult["filename"]  = document.getElementById("filename").value;
                    // workaround, sometimes the basepath is included
                    arrResult["filename"] = arrResult["filename"].replace("' . $basePath . '", "");
                    ' . $filePathWorkaround . '
                    if ( wizardArgs["is_image"] ) {
                        strFullSpec = \'<img src="\' + (wizardArgs["domain"] || "") + \'' . $basePath . '\' + document.getElementById("filename").value + \'"\';
                        if (document.getElementById("imgwidth").value!="0" && document.getElementById("imgheight").value!="0") {
                            strFullSpec = strFullSpec + \' width=\' + document.getElementById("imgwidth").value + \' height=\' + document.getElementById("imgheight").value;
                        }
                        if (document.getElementById("alternate").value!="") {
                            strFullSpec = strFullSpec + \' title="\' + document.getElementById("alternate").value + \'"\';
                        }
                        if (wizardArgs["include_layout"]) {
                            if (document.getElementById("imgAlignment").value!="") {
                                if (document.getElementById("imgAlignment").value==\'left\')
                                strStyle = \'margin-right:\' + document.getElementById("imgMargin").value + \'px;
                                margin-bottom:\' + document.getElementById("imgMargin").value + \'px;
                                \'
                                else if (document.getElementById("imgAlignment").value==\'right\')
                                strStyle = \'margin-left:\' + document.getElementById("imgMargin").value + \'px;
                                margin-bottom:\' + document.getElementById("imgMargin").value + \'px;
                                \'
                                else
                                strStyle = \'margin:\' + document.getElementById("imgMargin").value + \'px;
                                \';
                            }
                            strStyle = strStyle + \'border:\' + document.getElementById("imgBorder").value + \'px solid \' + document.getElementById("imgBorderColor").value + \';
                            \';
                            strFullSpec = strFullSpec + \' style="\'+strStyle+\'" align=\' + document.getElementById("imgAlignment").value ;
                        }
                        arrResult["full_spec"] = strFullSpec + \'>\';
                        arrResult["height"]    = document.getElementById("imgheight").value;
                        arrResult["width"]     = document.getElementById("imgwidth").value;
                        // check sizes according to setting!
                        if (wizardArgs["resizetype"]==' . FormControlHelper::IMG_MAXIMUM . '){
                            if ( parseInt(document.getElementById("imgheight").value) > parseInt(wizardArgs["resizeheight"])) {
                                modal_alert ("De afbeelding is te hoog, het maximum is " + wizardArgs["resizeheight"] + " pixels!");
                                return null;
                            }
                            if ( parseInt(document.getElementById("imgwidth").value) > parseInt(wizardArgs["resizewidth"])) {
                                modal_alert ("De afbeelding is te breed, het maximum is " + wizardArgs["resizewidth"] + " pixels!");
                                return null;
                            }
                        } else {
                            if (wizardArgs["resizetype"]==' . FormControlHelper::IMG_FIXED . ') {
                                if ( parseInt(document.getElementById("imgwidth").value) != parseInt(wizardArgs["resizewidth"]) ||
                                parseInt(document.getElementById("imgheight").value) != parseInt(wizardArgs["resizeheight"]) ) {
                                    modal_alert("De afbeelding heeft niet de correcte afmetingen, deze zou " + wizardArgs["resizewidth"] + "px breed en " + wizardArgs["resizeheight"] + "px hoog moeten zijn en de huidige breedte is " + document.getElementById("imgwidth").value + "px en de hoogte is " + document.getElementById("imgheight").value + "px.")
                                    return null;
                                }
                            }
                        }
                    }
                    if ( CheckFilename( ) ) {
                        // Use wizardArgs for callback info (modern approach)
                        // Try to find the callback target
                        var callbackTarget = null;
                        var callbackControl = wizardArgs["control"] || wizardArgs["fieldname"];
                        var callbackPath = wizardArgs["path"] || "";
                        var callbackWindow = null;

                        // Try parent windows for callback
                        if (window.opener && typeof window.opener.fSetImage === "function") {
                            callbackWindow = window.opener;
                        } else if (window.parent && typeof window.parent.fSetImage === "function") {
                            callbackWindow = window.parent;
                        } else if (top.window && typeof top.window.fSetImage === "function") {
                            callbackWindow = top.window;
                        }

                        // Also check for CKEditor in wizardArgs
                        var editor = wizardArgs["editor"];
                        if (editor && typeof editor.insertHtml === "function") {
                            editor.insertHtml( arrResult["full_spec"] );
                        }

                        // Call callback if found
                        if (callbackWindow && callbackControl) {
                            callbackWindow.fSetImage(callbackControl, callbackPath, arrResult["filename"], arrResult["width"], arrResult["height"]);
                        } else if (callbackControl && window.parent && window.parent.document) {
                            // Try to update hidden field directly
                            var targetField = window.parent.document.getElementById(callbackControl);
                            if (targetField) {
                                targetField.value = arrResult["filename"];
                                // Trigger change event
                                var evt = new Event("change", { bubbles: true });
                                targetField.dispatchEvent(evt);
                            }
                        }

                        // Close the popup/dialog
                        // Try lib_OpenWindowCentered first (most common for CMA wizards)
                        if (window.parent && typeof window.parent.lib_OpenWindowCenteredClose === "function") {
                            window.parent.lib_OpenWindowCenteredClose(true);
                        } else if (window.parent && window.parent.document) {
                            // Try lib-dialog in parent
                            var dialog = window.frameElement ? window.frameElement.closest("lib-dialog") : null;
                            if (dialog && typeof dialog.close === "function") {
                                dialog.close();
                            }
                        } else if (window.opener) {
                            // Real popup window
                            window.close();
                        }

                        return arrResult;
                    }
                }
                /**
                * Call Wizardgetshowfinish
                */
                function call_WizardGetShowFinish( current_page )
                {
                    return (current_page==last_page);
                }
                </script>';
                echo '</HEAD>';
                echo '<BODY style="margin:0 0 0 0;overflow:none" class="wizardcontent" onload="activateFirstPage()" onkeypress="if(window.parent && typeof window.parent.WizardButtonPressed===\'function\')window.parent.WizardButtonPressed(event.keyCode);return true;">';
                echo '<script>
                function activateFirstPage() {
                    // Try to use parent wizard frame first
                    if (window.parent && typeof window.parent.WizardActivatePage === "function") {
                        window.parent.WizardActivatePage(1);
                    } else {
                        // Standalone mode: activate page 1 directly
                        var page1 = document.getElementById("page1");
                        if (page1) {
                            page1.style.display = "block";
                        }
                    }
                }
                </script>';
                // ------------------------------------------------------------------------------------------------------------
                // fields to store information in ..
                echo '<input type=hidden name=filename id=filename value="">';
                echo '<input type=hidden name=imgwidth id=imgwidth value="">';
                echo '<input type=hidden name=imgheight id=imgheight value="">';
                echo '<input type=hidden name=imgAlignment id=imgAlignment value="';
                echo $strAlignment;
                echo '">';
                echo '<input type=hidden name=resizetype id=resizetype value="">';
                echo '<input type=hidden name=resizeheight id=resizeheight value="">';
                echo '<input type=hidden name=resizewidth id=resizewidth value="">';
                // ------------------------------------------------------------------------------------------------------------
                echo '<DIV ID="page0" style="display:none">';
                echo '<H3>Upload needed?</H3>';
                echo 'Is the <script type="text/javascript">document.write(strType);</script> already on the Internet server?<BR><BR>';
                echo '<INPUT TYPE=RADIO name=UPLOAD VALUE=N checked>&nbsp;&nbsp;No <BR><BR>  ';
                echo '<INPUT TYPE=RADIO name=UPLOAD VALUE=J>&nbsp;&nbsp;Yes <BR>';
                echo '</DIV>';
                // ------------------------------------------------------------------------------------------------------------
                echo '<!--<H3>Select a directory and upload the <script>document.write(strType);</script>:-->';
                echo '<DIV ID="page1" style=display:none>';
                echo '<iframe id="fileselect" scrolling="no" src="';
                echo Request::addAllToURL('file_frameset.php?upload=J');
                echo '" style="margin:0px;width:100%;height:100%" border="0" frameborder="0"></iframe> ';
                echo '</DIV>';
                // ------------------------------------------------------------------------------------------------------------
                echo '<DIV ID="page2" style="display:none">';
                echo '<H3>Layout opties';
                echo '<script type="text/javascript">;
                /**
                * Display
                */
                function display( strAlign )
                {
                    var elt = document.getElementById("imgAlignment");
                    if (elt) strAlign = elt.value.toLowerCase();
                    var sample = document.getElementById("sample");
                    var align_elt = document.all["img_Align"];
                    if (align_elt) {
                        for (x=0;x<align_elt.length;x++) {
                            if (align_elt[x].value.toLowerCase()==strAlign) {
                                document.getElementById("imgAlignment").value = strAlign;
                                align_elt[x].checked = true;
                                sample.align=strAlign;
                                var sMargin = document.getElementById("imgMargin").value!="" ? document.getElementById("imgMargin").value : "0";
                                switch (strAlign) {
                                case \'right\':
                                    sample.style.marginLeft = sMargin+\'px\';
                                        sample.style.marginBottom = sMargin+\'px\';
                                    case \'left\':
                                        sample.style.marginRight = sMargin+\'px\';
                                            sample.style.marginBottom = sMargin+\'px\';
                                        }
                                        if (imgBorder.value!="")
                                        sample.style.border = imgBorder.value + \'px solid \' + imgBorderColor.value;
                                        else
                                        sample.style.border = \'1px solid \' + imgBorderColor.value;
                                        sample.title = document.getElementById("alternate").value;
                                    }
                                }
                            }
                        }
                        /**
                        * Redisplay
                        */
                        function Redisplay()
                        {
                            display();
                            window.setTimeout ("Redisplay()", 500);
                        }
                        /**
                        * Setalign
                        */
                        function SetAlign( sValue )
                        {
                            document.getElementById("imgAlignment").value = sValue;
                            Redisplay();
                        }
                        </script>';
                        echo '<TABLE WIDTH=100% CELLSPACING=1 CELLPADDING=3>';
                        echo '   <TR valign=top>';
                        echo '   	<TD colspan=5 style="height:110px;border:1px solid black">';
                        echo '   	<H3>Voorbeeld:</H3>';
                        echo '   	<img id=sample src=images\\align_sample.jpg align=';
                        echo $strAlignment;
                        echo '>';
                        echo '   	Luctor et emergo. Luctor et emergo. Luctor et emergo. Luctor et emergo. Luctor et emergo. Luctor et emergo. Luctor et emergo. Luctor et emergo. Luctor et emergo.  </TD> ';
                        echo '   </TR>';
                        echo '   <TR align=center>';
                        echo '    <TD align=left>Uitlijning:</TD>';
                        echo "   	<TD><input type=radio id=1 name=img_Align value=left onclick=SetAlign('left')><label for=1>Links</label></TD>";
                        echo "   	<TD><input type=radio id=2 name=img_Align value=center onclick=SetAlign('center')><label for=2>Centreer</label></TD>";
                        echo '   	<TD><input type=radio id=3 name=img_Align value="" onclick=SetAlign(\'\')><label for=3>Geen</label></TD>';
                        echo "   	<TD><input type=radio id=4 name=img_Align value=right onclick=SetAlign('right')><label for=4>Rechts</label></TD>";
                        echo '   </TR>';
                        echo '</TABLE>';
                        echo '<BR>';
                        echo '<TABLE CELLSPACING=1 CELLPADDING=0>';
                        echo '<TR><TD nowrap>Rand dikte&nbsp;:&nbsp;</TD><TD nowrap><input type=text name=imgBorder id=imgBorder style=width:20px maxlength=2 value=';
                        echo $strBorder;
                        echo ' ONKEYPRESS="event.returnValue=IsDigit();"> Px</TD></TR>';
                        echo '<TR><TD nowrap>Rand kleur:</TD><TD colspan=4 align=left><script type="text/javascript">ColorPicker("imgBorderColor", "' . ($strBorderColor) . '' . '");</SCRIPT></TD></TR>';
                        echo '<TR><TD>Marge&nbsp;:&nbsp;</TD><TD nowrap><input type=text name=imgMargin id=imgMargin style=width:20px maxlength=2 value=';
                        echo $strMargin;
                        echo ' ONKEYPRESS="event.returnValue=IsDigit();"> Px</TD></tr>';
                        echo '<TR><TD>Omschrijving:</TD><TD width=85%><input name=alternate id=alternate type=text style="width:80%" maxlength=128 value="';
                        echo $strAlternate;
                        echo '"></TD></TR>';
                        echo '</TABLE>';
                        echo '</TD></TR>';
                        echo '</TABLE> ';
                        echo '<script>;
                        Redisplay();
                        document.getElementById("resizetype").value=wizardArgs["resizetype"];
                        document.getElementById("resizeheight").value=wizardArgs["resizeheight"];
                        document.getElementById("resizewidth").value=wizardArgs["resizewidth"];
                        </script> ';
                        echo '</DIV>';
                        echo '</BODY></HTML>';
                    }
                    // Call main function
                    main();
?>
