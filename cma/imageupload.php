<?php
use App\Library\Application;
use App\Library\Request;
use App\Library\Response;

require_once __DIR__ . '/bootstrap.inc';
require_once __DIR__ . '/../library/lib_general.inc';

/**
* Main
*/
function main()
{
    Response::noCache();
    define("TOPBAR_HEIGHT", 65);
    define("CONTROL_HEIGHT", '94%');
    define("BLNMODAL", false);
    $strTarget = Request::getOriginalScript();
    $strTarget = Request::server('SERVER_NAME', '') . substr($strTarget, 0, max(0, min((($pos = strrpos($strTarget, '/')) !== false ? $pos + 1 : 0), strlen($strTarget))));
    // Build extra head content for image upload specific assets
    // Note: library.js is already included via cma_html_header's bundle
    $extraHead = '<link rel="stylesheet" href="minify.php?f=assets/css/style.css">
<script src="slider/js/range.js"></script>
<script src="slider/js/timer.js"></script>
<script src="slider/js/slider.js"></script>
<link rel="stylesheet" href="slider/css/luna/luna.css">';
    cma_html_header('Beelden plaatsen', $extraHead, false);
    echo '<style>.kopje{FONT-WEIGHT:bold;FONT-SIZE:11pt;COLOR:#666699;FONT-FAMILY:Verdana,Tahoma}</style>';
    echo '<script>;
    var CROP_FIXED = 1;
    var CROP_MAX = 2;
    var s = 100;
    var current_page;
    if (window.dialogArguments) {
        var iCropStyle     = window.dialogArguments["mode"];
        var aspectWidth    = window.dialogArguments["width"];
        var aspectHeight   = window.dialogArguments["height"];
        var targetpath     = window.dialogArguments["path"];
        var randomfilename = window.dialogArguments["random"];
    }
    if (iCropStyle=""){
        //	alert( "Non modal");
        var iCropStyle     = \'' . Request::query('mode', '') . '\';
        var aspectWidth    = ' . (Request::query('width', '0') ?: '0') . ';
        var aspectHeight   = ' . (Request::query('height', '0') ?: '0') . ';
        var targetpath     = \'' . Request::query('path', '') . '\';
        var randomfilename = \'' . Request::query('random', '') . '\';
    }
    if (!aspectWidth)  {aspectWidth  = 800}
    if (!aspectHeight) {aspectHeight = 600}
    if (!iCropStyle)   {iCropStyle = CROP_MAX}
    if (!targetpath)   {targetpath = "/images/html/"}
    var oldLeft = 0;
    var oldTop = 0;
    var oldWidth = 0;
    var oldHeight = 0;
    /**
    * Setdescription
    */
    function SetDescription(intStep,sText)
    {
        /**
        * Page Init
        */
        function page_init()
        {
            var wiz;
            wiz = document.getElementById(\'ImageUploadWizard\');
            if (!wiz.ButtonNextCaption) {
                document.getElementById(\'wiz_error\').style.display=\'block\';
                document.getElementById(\'wiz_ok\').style.display=\'none\';
            }
            SetDescription( 1, "<font class=kopje>Selecteren afbeelding</font><BR>Op deze pagina kun je de afbeelding selecteren die je op de site wilt plaatsen." );
        }
        /**
        * Img Info
        */
        function img_info()
        {
            if (iCropStyle==CROP_FIXED) {
                strText = \'Vast formaat:\n\n\';
            } else {
                strText = \'Maximum formaat (het beeld moet binnen deze afmetingen vallen):\n\n\';
            }
            strText = strText + \'Hoogte : \' + aspectHeight.toString() + \'px\n\Breedte : \' + aspectWidth.toString() + \'px\n\';
            lib_alertbox(strText, "Beeldformaat", "info");
        }
        /**
        * Doresize
        */
        function doresize()
        {
            if (current_page==2) {
                var size_elt = document.getElementById(\'sizeinfo\');
                var wiz = document.getElementById(\'ImageUploadWizard\');
                //		var old_cursor = document.getElementById("sliderdiv").style.cursor;
                var intPerc = s.getValue();
                //       document.getElementById("sliderdiv").style.cursor = "wait";
                wiz.DisableUpdate();
                wiz.UndoAll();
                if (oldWidth>0 ) {
                    wiz.Crop(Math.max(oldLeft,0), Math.max(oldTop,0), Math.max(oldWidth,0) , Math.max(oldHeight,0) );
                }
                if (iCropStyle==CROP_FIXED) {
                    // Check whether we need to resize
                    wiz.Resize(aspectWidth, aspectHeight);
                } else {
                    // determine desired size
                    intReqWidth  = Math.min( aspectWidth, wiz.CurrentImageWidth );
                    intReqHeight = Math.min( aspectHeight,wiz.CurrentImageHeight);
                    // perform scaling
                    if (intPerc>0 && intPerc<=100) {
                        intReqWidth  = (intReqWidth*intPerc)/100;
                        intReqHeight = (intReqHeight*intPerc)/100;
                    }
                    intRatioWidth  = (wiz.CurrentImageWidth  / intReqWidth  );
                    intRatioHeight = (wiz.CurrentImageHeight / intReqHeight );
                    if (wiz.CurrentImageWidth > intReqWidth || wiz.CurrentImageHeight > intReqHeight) {
                        if (intReqHeight>=1 && intReqWidth>=1) {
                            if (intRatioWidth>intRatioHeight)
                            wiz.Resize(intReqWidth,0)
                            else
                            wiz.Resize(0,intReqHeight);
                        }
                        //	        } else {
                        //				modal_alert("Beeld is te klein voor het gewenste formaat\r\nintReqWidth: " + intReqWidth + "("+intRatioWidth.toString()+")\r\nintReqHeight: "  + intReqHeight + "("+intRatioHeight.toString()+")"  )
                    }
                }
                size_elt.innerHTML = (iCropStyle==CROP_FIXED?"<BR>":"")+"&nbsp;<b>" + wiz.CurrentImageWidth.toString() + "</b>b x <b>" + wiz.CurrentImageHeight.toString() + "</b>h";
                //        document.getElementById("sliderdiv").style.cursor = old_cursor;
                wiz.EnableUpdate();
            }
        }
        </script>';
        // Image Upload Wizard Event Handlers
        echo '<script lang=JScript for="ImageUploadWizard" event="OnPageChange(Index,IsNextButtonPressed)">
        var wiz
        var slid_step=2;
        current_page = Index;
        if (Index==0){
            page_init();
        } else {
            wiz = document.getElementById(\'ImageUploadWizard\');
            // alert( wiz.clientWidth.toString()+" van "+ wiz.parentElement.clientWidth.toString()+" van "+ wiz.parentElement.parentElement.clientWidth.toString());
        }
        if (iCropStyle!=CROP_FIXED) {
            slid = document.getElementById(\'sliderdiv\').style.display=(Index==slid_step?\'block\':\'none\');
            if (Index<=slid_step) s.recalculate();
        }
        if (wiz) {
            var size_elt = document.getElementById(\'sizeinfo\');
            size_elt.style.display = (Index==2)?\'block\':\'none\';
            switch(Index)
            {
            case 1:
                if (IsNextButtonPressed) {
                        if (iCropStyle==CROP_FIXED) {
                            if (wiz.CurrentImageWidth<aspectWidth || wiz.CurrentImageHeight<aspectHeight) {
                                wiz.Back();
                                modal_alert("Deze afbeelding is te klein voor de vereiste hoogte van " + aspectHeight.toString() + " en breedte van " + aspectWidth.toString());
                                return false;
                            break;								// NOTE!
                        }
                    }
                }
                SetDescription( 2, "<font class=kopje>Bijsnijden afbeelding</font><BR>Hier kun je de afbeelding bijsnijden indien nodig.");
                if (!IsNextButtonPressed) wiz.UndoAll();
                wiz.SelectionClear();
                if (iCropStyle==CROP_FIXED) {
                    wiz.SelectionModifyMode = 1;
                    wiz.SelectionSetAspect(aspectWidth, aspectHeight);
                } else {
                    wiz.SelectionLeft   = 0;
                    wiz.SelectionTop    = 0;
                    wiz.SelectionWidth  = wiz.CurrentImageWidth;
                    wiz.SelectionHeight = wiz.CurrentImageHeight;
                }
                if (IsNextButtonPressed) {
                    wiz.SelectionSetToCenter();
                } else {
                    wiz.SelectionLeft   = oldLeft;
                    wiz.SelectionTop    = oldTop;
                    wiz.SelectionWidth  = oldWidth;
                    wiz.SelectionHeight = oldHeight;
                    wiz.ClearUpload();
                }
            break;
        case 2:
            SetDescription( 3, "<font class=kopje>Plaatsen afbeelding</font><BR>Druk op Plaats afbeelding om het plaatsen af te ronden.");
                wiz.Action = "http://' . ($strTarget) . '' . 'imageupload_action.php?path="+targetpath+(randomfilename!=\'\' ? \'&random=Y\':\'\')
                //			wiz.Action = "http://imageupload.toolsonline.nl/imageupload_action.php?site=' . urlencode(Request::server('SERVER_NAME', '')) . '&path="+targetpath+(randomfilename!=\'\' ? \'&random=Y\':\'\')
                oldLeft = wiz.SelectionLeft;
                oldTop = wiz.SelectionTop;
                oldWidth = wiz.SelectionWidth;
                oldHeight = wiz.SelectionHeight;
                doresize()
            break;
        }
    }
    </script>';
    echo '<script lang=JScript for=ImageUploadWizard event="OnUploadStart()">
    var wiz=document.getElementById(\'ImageUploadWizard\');
    if (wiz) wiz.Add("blob");
    </script>';
    echo '<script lang=JScript for=ImageUploadWizard event="OnErrorOccured(ResponseCode, Headers, Page, ClientCode)">
    if (ClientCode!=3) {
        modal_alert("Er is een fout opgetreden - (code " + ClientCode.toString() + ")");
        modal_alert( "Foutbeschrijvingspagina:" + Page );
    }
    </script>';
    echo '<script lang=JScript for=ImageUploadWizard event="OnUploadComplete(ResponseCode, Headers, Page)">
    if (ResponseCode!=200) {
        modal_alert("Upload failed with response code " + ResponseCode.toString());
    } else {
        var wiz = document.getElementById(\'ImageUploadWizard\');
        ';
    if (BLNMODAL) {
        echo '
        var arrRes=new Array;
        arrRes["filename"] = Page;	// hmm, well it works!
        arrRes["height"]   = wiz.CurrentImageHeight;
        arrRes["width"]    = wiz.CurrentImageWidth;
        window.returnValue=arrRes;
        ';
    } else {
        echo '
        window.opener.fSetImage(\'' . Request::query('control', '') . '\', \'' . Request::query('path', '') . '\', Page, wiz.CurrentImageWidth, wiz.CurrentImageHeight);
        ';
    }
    echo '
        window.close();
    }
    </script>';
    echo '</head>';
    echo '<body onload=page_init() style=margin:0px;background-color:#ECE9D8>';
    echo '<table style=WIDTH:100%;HEIGHT:100%;background-color:#ECE9D8 cellpadding=0 cellspacing=0>';
    echo '<tr style=height:';
    echo TOPBAR_HEIGHT;
    echo 'px;PADDING-TOP:10px valign=top>  ';
    echo '	<td id=Descr style=height:';
    echo TOPBAR_HEIGHT;
    echo 'px;width:70%;padding-left:10px></TD>';
    echo '	<td style=width:25%;height:';
    echo TOPBAR_HEIGHT;
    echo 'px>';
    echo '		<div id=sliderdiv style=display:none;width:220px>';
    echo '		<table cellpadding=0 cellspacing=0>';
    echo '			<tr><td>&nbsp;Schaal</td><td align=right id=slider_perc></td></tr>';
    echo '			<tr><td colspan=2><div class=slider id="slider-1" tabIndex=1 style=WIDTH:100px><input class=slider-input id="slider-input-1" style=width:0px></div></td>';
    echo '			    <td><input type=button onclick="javascript:doresize()" style="margin-left:6px" value="Pas beeld aan" ></td></tr>';
    echo '		</table>';
    echo '		</div>';
    echo '		<div id=sizeinfo style=display:none;width:200px>';
    echo '		</div>';
    echo '	</td>';
    echo '	<td align=right style="padding-right:10px;padding-left:40px;width:5%">';
    echo '	 <img onclick=img_info() style="CURSOR:pointer" src=images/btn_info.gif width=24 height=24 border=0>';
    // <a href=imageupload_help.htm><img src=images/btn_help.gif width=24 height=24 border=0></a>
    echo '	</td>';
    echo '</tr>';
    echo '<tr style="height:';
    echo CONTROL_HEIGHT;
    echo '"><TD colSpan=99 ID=wiz_container style="width:100%;height:';
    echo CONTROL_HEIGHT;
    echo '">';
    echo '	<div id=wiz_ok style="height:';
    echo CONTROL_HEIGHT;
    echo ';width:100%">                               ';
    // oude CLS-ID = 96E4290E-04BA-4a7f-AE60-681147696DB4
    echo '		<OBJECT id="ImageUploadWizard" style="WIDTH:100%;HEIGHT:';
    echo CONTROL_HEIGHT;
    echo '" classid="CLSID:B5B743EA-3E3B-4102-8445-BC73D85FD15A">';
    echo '			<PARAM NAME=_cx VALUE=33099>';
    echo '			<PARAM NAME=_cy VALUE=20135>';
    echo '			<PARAM NAME=IsLzwEnabled VALUE=-1>';
    echo '			<PARAM NAME=MaxFileSize VALUE=0>';
    echo '			<PARAM NAME=AdditionalFormName VALUE="">';
    echo '			<PARAM NAME=LabelFoldersText VALUE="">';
    echo '			<PARAM NAME=ButtonBackCaption VALUE="Vorige">';
    echo '			<PARAM NAME=ButtonNextCaption VALUE="Volgende">';
    echo '			<PARAM NAME=ButtonUploadCaption VALUE="Plaats afbeelding!">';
    echo '			<PARAM NAME=ButtonAbortUploadCaption VALUE="Annuleer plaatsen">';
    echo '			<PARAM NAME=FolderViewMode VALUE=5>';
    echo '			<PARAM NAME=TransparentColor VALUE="#';
    echo Application::get('color_body', '');
    echo '">';
    echo '			<PARAM NAME=TimeOut VALUE=0>';
    echo '		</OBJECT>';
    echo '	</div>';
    echo '	<div id=wiz_error style=height:100%;width:100%;display:none>';
    echo '		<table height=100% width=100%><tr><td align=center style=width:50%>';
    echo '			<table cellspacing=0 cellpadding=0 bgcolor=#003366 style="width:335px;padding:10px;filter:progid:DXImageTransform.Microsoft.Shadow(color=\'#939393\',Direction=135,Strength=4);"><tr><td>';
    echo '			<tr><td><font color=#ffffff>Download Stenvers online Image Upload versie 1.1</font><br>&nbsp;</td></tr>';
    echo '			<tr><td><font color=#ffffff>';
    echo str_replace('dit scherm', 'de browser', $lang_download_imageupload);
    echo '</td></tr>';
    echo '			</table>';
    echo '		</td><td style=width:50%>';
    echo '			<table cellspacing=0 cellpadding=0 bgcolor=#003366 style="width:530px;padding:10px;filter:progid:DXImageTransform.Microsoft.Shadow(color=\'#939393\',Direction=135,Strength=4);"><tr><td>';
    echo '			<tr><td style=color:#ffffff>Heb je de wizard reeds ge&iuml;nstalleerd? Volg dan deze stappen:<br>';
    echo '			<ul style=color:#ffffff>';
    echo '			<li>Klik in het menu van Internet Explorer op Extra en ga naar Internet Opties.</li>';
    echo '			<li>Klik op de Beveiliging tab en kies Vertrouwde Websites.</li>';
    echo '			<li>Klik op de knop Websites.</li>';
    echo "			<li>Zorg dat het vinkje onderaan de lijst 'Serververificatie...' UIT staat.</li>";
    echo '			<li>Bovenin dit scherm moet de domeinnaam al vooringevuld zijn, klik daarnaast op Toevoegen</li>';
    echo '			<li>Klik op Sluiten en daarna op Ok.</li>';
    echo '			<li>U moet de browser nu opnieuw opstarten zodat deze instellingen actief worden.</li>';
    echo '			</ul>';
    echo '			</td></tr></table>';
    echo '		</td></tr></table>			';
    echo '	</div>		';
    echo '</td></tr>';
    echo '</table>';
    echo '<script type="">;
    s = new Slider(document.getElementById("slider-1"),document.getElementById("slider-input-1"));
    s.onchange = function () {
        if (s.value<1) s.setValue(1);
        var nPerc = s.getValue();
        slider_perc.innerHTML = nPerc.toString() + "%";
    };
    s.setValue(100);
    s.setValue(s.getValue());
    s.setUnitIncrement(10);
    s.setBlockIncrement(10);
    </SCRIPT>';
    echo '</body>';
    echo '</html>';
}
// Call main function
main();
?>
