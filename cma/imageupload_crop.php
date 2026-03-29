<?php
use App\Library\Application;
use App\Library\Image;
use App\Library\Request;
use App\Library\Response;
use App\Library\ResponsiveImage;
use App\Library\Server;
use App\Library\Str;

require_once __DIR__ . '/bootstrap.inc';

/**
* Main
*/
function main()
{
    Response::noCache();
    // Uses jQuery jCrop
    // http://www.mikesdotnetting.com/Article/95/Upload-and-Crop-Images-with-jQuery-JCrop-and-ASP.NET
    $FORUM_CONNECTION = 'data';
    $parPath = Request::query('path', '');
    $intStep = Request::post('step', '');
    if ($intStep == '') {
        $intStep = '1';
    }
    $intermediatesize = Request::post('intermediatesize', '');
    $THUMB_HEIGHT = Request::query('height', '');
    if ($THUMB_HEIGHT == '') {
        $THUMB_HEIGHT = 600;
    }
    $THUMB_WIDTH = Request::query('width', '');
    if ($THUMB_WIDTH== '' || $THUMB_WIDTH == '0') {
        $THUMB_WIDTH = 800;
    }
    $parCropStyle = Request::query('mode', '');
    echo '<!DOCTYPE html><head>';
    cma_error_handler();
    echo '    <TITLE>Beeld plaatsen</TITLE>';
    echo '	<link rel="shortcut icon" href="/favicon.ico">';
    if ($intStep == '1') {
    }
    echo '	<link rel="stylesheet" href="minify.php?f=../library/css/lib-variables.css,assets/css/colors.css,../library/library.css,assets/css/style.css" type="text/css">';
    echo '	<script src="//ajax.googleapis.com/ajax/libs/jquery/' . STRJQUERYVERSION . '/jquery.' . ((Application::get('local')) ? "" : "min.") . 'js"></script>';
    echo '	<script src="../library/formval_nl.js"></script>';
    if ($intStep == '1') {
        cma_script('../library/webcomponents/lib-shared-styles.js');
        cma_script('../library/webcomponents/lib-fileuploader.js');
    } elseif ($intStep == '2') {
        echo '	<script src="../library/jcrop/jquery.Jcrop.min.js"></script>';
        echo '	<link rel="stylesheet" href="../library/jcrop/jquery.Jcrop.min.css" type="text/css">';
        echo '    <script src="assets/js/jquery-slider/js/simple-slider.js"></script>';
        echo '    <link href="assets/js/jquery-slider/css/simple-slider.css" rel="stylesheet" type="text/css" />';
        echo '    <link href="assets/js/jquery-slider/css/simple-slider-volume.css" rel="stylesheet" type="text/css" />  ';
    }
    echo '	<style>
    body {
        -moz-user-select: none;
        -khtml-user-select: none;
        -webkit-user-select: none;
        -o-user-select: none;
        overflow: hidden;
        margin: 0;
        padding: 0;
        height: 100vh;
    }
    body div#content {
        height: calc(100vh - 20px);
        overflow: auto;
        width: 100%;
    }
    body div#buttons {}
    body h2 { font-size:var(--font-size-lg);
        border-bottom:1px solid #dedede;
        padding:12px }
    body div.explain{border-bottom:1px solid #dedede;
        background-color:#fcfcfc;
        padding:6px 4px 4px;
        text-align:center}
    lib-fileuploader {
        max-width: 400px;
        display: block;
        margin: 0 auto;
    }
    span.bullet {background-color:#ffffff;
        color:#E84F18;
        border-color:#E84F18}
    span.dimmed {background-color:#E84F18 !important;
        color:#ffffff !important;
        opacity:.4 !important}
    span.active {background-color:#E84F18 !important;
        color:#ffffff  !important;
        opacity:1 !important}
    span.active, span.bullet, span.dimmed {border-radius:50%;
        border:1px solid #E84F18;
        margin-right:12px;
        line-height: 28px;
        display:inline-block;
        text-align: center;
        width: 28px;
        height: 28px;
        font-size:var(--font-size-md);
        font-weight: bold;
    }
    .jcrop-holder .jcrop-handle { height:10px;
        width:10px;
        border: 1px solid #E84F18;
        background-color:#E84F18}
    .explain {min-height:25px}
    .toolbar {display:flex; align-items:center; gap:4px; justify-content:center}
    .toolbar .spacer {width:16px}
    #zoom-slider {display:inline-block}
    </style>';
    echo '	<script>
    var CROP_FIXED 	= 1;
    var CROP_MAX 	= 2;
    var s 			= 100;
    var current_page;
    if (iCropStyle=""){
        var iCropStyle     = ' . $parCropStyle . ';
        var aspectWidth    = ' . $THUMB_WIDTH . ';
        var aspectHeight   = ' . $THUMB_HEIGHT . ';
        var targetpath     = \'' . $parPath . '\';
        //			var randomfilename = \'\';
    }
    if (!aspectWidth)  {aspectWidth  = 800}
    if (!aspectHeight) {aspectHeight = 600}
    if (!iCropStyle)   {iCropStyle = CROP_MAX}
    if (!targetpath)   {targetpath = "/images/html/"}
    var oldLeft = 0;
    var oldTop = 0;
    var oldWidth = 0;
    var oldHeight = 0;
    ';
    if ($intStep == '1') {
        echo '
    document.addEventListener("DOMContentLoaded", function() {
        var uploader = document.querySelector("lib-fileuploader");
        if (uploader) {
            uploader.addEventListener("upload-complete", function(e) {
                var filename = e.detail.filename.replace(".png", ".jpg");
                document.getElementById("bestandsnaam_id").value = filename;
                jQuery("#uploader").hide();
                jQuery("#processing").show();
                document.getElementById("formulier").submit();
            });
        }
    });
    ';
    } // end if step 1
    echo '
    /**
    * Doclose
    */
    function DoClose( sFilename )
    {
        var sPath = \'' . Request::query('path', '') . '\';
        var sControl = \'' . Request::query('control', '') . '\';

        if (window.parent && window.parent !== window) {
            // Inside iframe (lib-dialog): use postMessage
            var editor = window.parent.top ? window.parent.top.activeEditor : null;
            if (!sControl && editor) {
                // CKEditor context: insert <picture> with WebP + JPG fallback
                var html;
                if (sFilename.match(/\\.webp$/i)) {
                    var jpgFallback = sFilename.replace(/\\.webp$/i, ".jpg");
                    html = "<picture><source srcset=\\"" + sPath + sFilename + "\\" type=\\"image/webp\\"><img src=\\"" + sPath + jpgFallback + "\\" alt=\\"\\" width=\\"' . $THUMB_WIDTH . '\\" height=\\"' . $THUMB_HEIGHT . '\\"></picture>";
                } else {
                    html = "<img src=\\"" + sPath + sFilename + "\\" alt=\\"\\" width=\\"' . $THUMB_WIDTH . '\\" height=\\"' . $THUMB_HEIGHT . '\\">";
                }
                editor.insertHtml(html);
            }
            window.parent.postMessage({
                type: "image-crop-complete",
                control: sControl,
                path: sPath,
                filename: sFilename,
                width: ' . $THUMB_WIDTH . ',
                height: ' . $THUMB_HEIGHT . '
            }, window.location.origin);
        } else if (window.opener) {
            // Popup window fallback
            window.opener.fSetImage(sControl, sPath, sFilename, ' . $THUMB_WIDTH . ', ' . $THUMB_HEIGHT . ');
            lib_OpenWindowCenteredClose();
            window.close();
        }
    }
    /**
    * Img Info
    */
    function img_info()
    {
        var strText = "' . ($parCropStyle == "1" ? "Vast formaat:" : "Maximum formaat (het beeld moet binnen deze afmetingen vallen):") . '";
        strText = strText + \'hoogte :  ' . $THUMB_HEIGHT . 'px   -   breedte :  ' . $THUMB_WIDTH . 'px\n\';
        lib_alertbox(strText, "Beeldformaat", "info");
    }
    </script>';
    echo '</HEAD>';
    echo '<BODY class="popup">';
    echo '	<form id="formulier" method="post" data-show-tooltip="N" >';
    if ($intStep == '1') {
        echo '			<input type=hidden id="intermediatesize_id" name="intermediatesize" value="1600" />';
        echo '			<script>
        jQuery(document).ready( function(){
            var h = jQuery(document).height() - jQuery("body h2").height() - jQuery(".explain").height();
            $(\'#intermediatesize_id\').val( Math.round( h - 80, 0)  );
        } );
        /**
        * Next
        */
        function next()
        {
            if (jQuery("#bestandsnaam_id").val()!="") {
                document.forms[0].submit();
            } else {
                modal_alert("Kies eerst een bestand")
            }
        }
        </script>			';
        echo '			<div id="content">';
        echo '				<h2>';
        echo '					<a href=';
        echo Request::url();
        echo '><span class="bullet active">1</span></a>';
        echo '					<span class="bullet dimmed">2</span><span class="bullet dimmed">3</span>Beeld selecteren';
        echo '					<span class="GenButton" style="float:right">';
        echo '						<a class="button"   href="javascript:next()">Volgende stap</a>';
        echo '					</span>';
        echo '				</h2>';
        echo '				<div class="explain">';
        echo '					<input type="hidden" name="step" value="2" />';
        echo '				</div>';
        echo '				<div id="uploader" style="margin-top:100px">';
        echo '					<input type="hidden" id="bestandsnaam_id" name="bestandsnaam" />';
        echo '					<lib-fileuploader field="bestandsnaam_id" endpoint="./imageupload_crop_upload_handler.php" path="' . htmlspecialchars($parPath) . '" extensions="jpg,png,jpeg" max-size="10240000" button-text="Kies het beeld" type-error="Alleen PNG en JPG beelden zijn ondersteund" show-link="false"></lib-fileuploader>';
        echo '				</div>';
        echo '				<div id="processing" style="margin-top:100px;text-align:center;display:none">';
        echo '					<span style="text-align:center; width:auto; display:inline-block"><img src="../library/images/AJAX_indicator.gif" style="float:left; margin-top:-2px; margin-right:8px; font-size:var(--font-size-lg)">Even geduld, upload en verwerking loopt.. </span>';
        echo '				</div>';
        echo '			</div>';
    } elseif ($intStep == '2') {
        $sThumbBestand = str_ireplace('.jpg', '', Request::post('bestandsnaam', '')) . '_resized.jpg';
        $sOriginalResized = $parPath . $sThumbBestand;
        $sOriginal = $parPath . Request::post('bestandsnaam', '');
        $nOriginalHeight = 0;
        $nOriginalWidth = 0;
        $intAttempts = 0;
        if (Request::post('rebuild', '') == '') {
            $sResizeUrl = Request::currentDomain() . Application::get('base_path', '') . 'library/lib_ImageCropper.php?image=' . str_replace(Application::get('base_path', ''), '/', $sOriginal) . '&thumb=' . str_replace(Application::get('base_path', ''), '/', $sOriginalResized) . '&compression=97';
            if ($nOriginalHeight > $nOriginalWidth) {
                $sResizeUrl .= '&thumb_h=' . $intermediatesize;
            } else {
                $sResizeUrl .= '&thumb_w=' . $intermediatesize;
            }
            lib_HttpGet($sResizeUrl);
            Image::getSize(Server::mapPath($sOriginalResized), $nOriginalWidth, $nOriginalHeight);
        } else {
            Image::getSize(Server::mapPath($sOriginalResized), $nOriginalWidth, $nOriginalHeight);
        }
        $nOriginalWidth = round($nOriginalWidth, 0);
        $nOriginalHeight = round($nOriginalHeight, 0);
        echo '			<div id="content" style="overflow:auto">			';
        echo '				<h2>';
        echo '					<a href=';
        echo Request::url();
        echo '><span class="bullet">1</span></a><span class="bullet active">2</span><span class="bullet dimmed">3</span>';
        echo '					Beeld bewerken';
        echo '					<span class="GenButton" style="float:right">';
        echo '						<a class="button"    href="javascript:document.forms[0].submit()">Volgende stap</a>';
        echo '					</span>';
        echo '				</h2>';
        echo '				<div class="explain">';
        echo '					<div class="toolbar">';
        echo '						<span class="tb-btn" title="Beeldformaat info" onclick="img_info()"><a><span class="lnr lnr-question-circle"></span></a></span>';
        echo '						<span class=spacer></span>';
        echo '						<span class="tb-btn" title="Linksom draaien" onclick="rotate(90)"><a><span class="lnr lnr-undo"></span></a></span>';
        echo '						<span class="tb-btn" title="Rechtsom draaien" onclick="rotate(-90)"><a><span class="lnr lnr-redo"></span></a></span>';
        echo '						<span class="tb-btn" title="180 graden draaien" onclick="rotate(180)"><a><span class="lnr lnr-sync"></span></a></span>';
        echo '						<span class=spacer></span>';
        echo '						<span class="tb-btn" title="Donkerder maken" onclick="brightness(\'-\')"><a><span class="lnr lnr-circle-minus"></span></a></span>';
        echo '						<span class="tb-btn" title="Lichter maken" onclick="brightness(\'+\')"><a><span class="lnr lnr-sun"></span></a></span>';
        echo '						<span class=spacer></span>';
        echo '						<span class="tb-btn" title="Scherper maken" onclick="sharpen()"><a><span class="lnr lnr-magic-wand"></span></a></span>';
        echo '					</div>';
        if ($parCropStyle == '2') {
            echo '						<span style="color: #666666;display: block;margin-top: 10px;">';
            echo '							  Zoom : <input type="text" name="zoom" id=zoom value="';
            echo (Request::post('zoom', '') != '' ? Request::post('zoom', '') : 100);
            echo '" data-slider="true" data-slider-range="1,100" data-slider-step="1">';
            echo '							  <span id=dimensions style="color:#666666"></span>';
            echo '						</span>';
        }
        echo '				</div>';
        echo '				<input type="hidden" name="path" 			 						    value="';
        echo $parPath;
        echo '" 													  />';
        echo '				<input type="hidden" name="original" 		 						    value="';
        echo $sOriginal;
        echo '" 													  />';
        echo '				<input type="hidden" name="thumb" 			 						    value="';
        echo $sOriginalResized;
        echo '" 											  />';
        echo '				<input type="hidden" name="bestand" 		 						    value="';
        echo $sThumbBestand;
        echo '" 												  />';
        echo '				<input type="hidden" name="bestandsnaam" 	 						    value="';
        echo Request::post('bestandsnaam', '');
        echo '" 								  />';
        echo '				<input type="hidden" name="step" 			 id="step_id"			    value="3"																  />';
        echo '				<input type="hidden" name="rebuild" 	                  			    value="n"																  />';
        echo '				<input type="hidden" name="x" 				 id="x" 					value="';
        echo round((Request::post('x', '') != '' ? Request::post('x', '') : 0), 0);
        echo '"   			  />';
        echo '				<input type="hidden" name="y" 				 id="y" 					value="';
        echo round((Request::post('y', '') != '' ? Request::post('y', '') : 0), 0);
        echo '"   			  />';
        echo '				<input type="hidden" name="w" 				 id="w" 					value="';
        echo round((Request::post('w', '') != '' ? Request::post('w', '') : $nOriginalWidth), 0);
        echo '"  />';
        echo '				<input type="hidden" name="h" 				 id="h" 					value="';
        echo round((Request::post('h', '') != '' ? Request::post('h', '') : $nOriginalHeight), 0);
        echo '" />';
        echo '				<input type="hidden" name="intermediatesize" id="intermediatesize_id"   value="';
        echo $intermediatesize;
        echo '" 											  />';
        echo '				<div style="width:100%; text-align:center">';
        echo '					<div id="crop-container" style="width:';
        echo $nOriginalWidth;
        echo 'px;position:relative;top:0px;left:50%;margin-left:-';
        echo round($nOriginalWidth / 2, 0);
        echo 'px">';
        echo '						<img id="jcrop_target" src="';
        echo $sOriginalResized;
        echo '?forcereload=';
        echo time();
        echo '" style="max-width:';
        echo $nOriginalWidth;
        echo 'px">';
        echo '					</div>';
        echo '				</div>';
        $formX = Request::post('x', '');
        $formY = Request::post('y', '');
        $formW = Request::post('w', '');
        $formH = Request::post('h', '');
        $setSelectX1 = ($formX != '') ? (int)$formX : 0;
        $setSelectY1 = ($formY != '') ? (int)$formY : 0;
        $setSelectX2 = ($formW != '') ? ((int)$formX + (int)$formW) : $nOriginalWidth;
        $setSelectY2 = ($formH != '') ? ((int)$formY + (int)$formH) : $nOriginalHeight;
        $aspectRatio = ($parCropStyle == "1") ? str_replace(",", ".", (string)($THUMB_WIDTH / $THUMB_HEIGHT)) : "0";
        echo '				<script>
        jQuery(document).ready( function(){
            $(\'#jcrop_target\').Jcrop({
                onChange: crop_changed,
                onSelect: crop_changed,
                setSelect: [ ' . $setSelectX1 . ', ' . $setSelectY1 . ', ' . $setSelectX2 . ', ' . $setSelectY2 . ' ],
                aspectRatio: ( ' . $aspectRatio . ')
            });
        } );
        /**
        * Crop Changed
        */
        function crop_changed( coords )
        {
            $(\'#x\').val( Math.round(coords.x, 0) );
            $(\'#y\').val( Math.round(coords.y, 0) );
            $(\'#w\').val( Math.round(coords.w, 0) );
            $(\'#h\').val( Math.round(coords.h, 0) );
            if (document.getElementById("zoom")) {
                recalculate_result( document.getElementById("zoom").value );
            }
        }
        jQuery("#zoom").bind("slider:ready slider:changed", function (event, data) {
            recalculate_result( data.value );
        });
        /**
        * Recalculate Result
        */
        function recalculate_result(  zoomfactor )
        {
            jQuery("#zoom-slider .dragger").attr("title", zoomfactor.toString() + "% - " );
            result_w = document.getElementById("w").value;
            result_h = document.getElementById("h").value;
            // determine desired size
            intReqWidth  = Math.min( ' . $THUMB_WIDTH . '  , result_w);
            intReqHeight = Math.min( ' . $THUMB_HEIGHT . ' , result_h);
            // perform scaling
            intPerc		 = zoomfactor;
            if (intPerc>0 && intPerc<=100) {
                intReqWidth  = (intReqWidth*intPerc)/100.0;
                intReqHeight = (intReqHeight*intPerc)/100.0;
            }
            intRatioWidth  = ( result_w / intReqWidth  )
            intRatioHeight = ( result_h / intReqHeight )
            intAspectRatio = ( result_w / result_h 	   )
            if ((document.getElementById("w").value) > intReqWidth || (document.getElementById("h").value) > intReqHeight) {
                if (intRatioWidth>intRatioHeight ) {
                    result_h = ( intReqWidth * (1 / intAspectRatio) );
                    result_w = intReqWidth;
                } else {
                    result_w = ( intReqHeight * intAspectRatio);
                    result_h = intReqHeight;
                }
            }
            result_w = Math.round(result_w,0);
            result_h = Math.round(result_h,0);
            var sDim = result_w.toString() + "b x " + result_h.toString() + "h";
            jQuery("#dimensions").html( sDim );
        }
        /**
        * Rotate
        */
        function rotate( deg )
        {
            var sUrl = \'' . Request::currentDomain() . Application::get('base_path', '') . 'library/lib_ImageRotate.php?image=\' + ' . Str::JscriptSafe(Server::mapPath($sOriginalResized)) . ' + \'&rotate=\' + deg.toString() + \'&compression=100\';
            $.ajax( { url : sUrl, cache : false } ).done(function() {
                jQuery("#step_id").val("2");
                document.getElementById("formulier").submit();
            })
            .fail(function() {
                modal_alert( "Error: " + sUrl );
            });
        }
        /**
        * Brightness
        */
        function brightness( arg )
        {
            var sUrl = \'' . Request::currentDomain() . Application::get('base_path', '') . 'library/lib_ImageFilter.php?image=\' + ' . Str::JscriptSafe(Server::mapPath($sOriginalResized)) . ' + \'&filter=brightness&arg=\' + arg + \'&compression=100\';
            $.ajax( { url : sUrl, cache : false } ).done(function() {
                jQuery("#step_id").val("2");
                document.getElementById("formulier").submit();
            })
            .fail(function() {
                modal_alert( "Error: " + sUrl );
            });
        }
        /**
        * Sharpen
        */
        function sharpen( )
        {
            var sUrl = \'' . Request::currentDomain() . Application::get('base_path', '') . 'library/lib_ImageFilter.php?image=\' + ' . Str::JscriptSafe(Server::mapPath($sOriginalResized)) . ' + \'&filter=sharpen&compression=100\';
            $.ajax( { url : sUrl, cache : false } ).done(function() {
                jQuery("#step_id").val("2");
                document.getElementById("formulier").submit();
            })
            .fail(function() {
                modal_alert( "Error: " + sUrl );
            });
        }
        </script>';
        echo '			</div>';
    } elseif ($intStep == '3') {
        echo '			<div id="content">';
        $sThumb = Request::post('thumb', '');
        $sBestandsnaam = str_ireplace('.jpg', '', Request::post('bestand', '')) . $THUMB_WIDTH . 'x' . $THUMB_HEIGHT . '.jpg';
        $img_h = (float)(Request::post('h', '') ?: 600);
        $img_w = (float)(Request::post('w', '') ?: 800);
        $result_h = null;
        $result_w = null;
        if ($parCropStyle== '1') {
            $result_h = $THUMB_HEIGHT;
            $result_w = $THUMB_WIDTH;
        } else {
            $intPerc = (float)(Request::post('zoom', '') ?: 100);
            $result_h = $img_h;
            $result_w = $img_w;
            if ($result_h <= $THUMB_HEIGHT && $result_w <= $THUMB_WIDTH && $intPerc == 100) {
            } else {
                $intRatioWidth = 0;
                $intRatioHeight = 0;
                $intReqWidth = 0;
                $intReqHeight = 0;
                $intAspectRatio = 1.0;
                $intReqWidth = min($THUMB_WIDTH, $result_w);
                $intReqHeight = min($THUMB_HEIGHT, $result_h);
                if ($intPerc > 0 && $intPerc < 100) {
                    $intReqWidth = $intReqWidth * $intPerc / 100.0;
                    $intReqHeight = $intReqHeight * $intPerc / 100.0;
                }
                $intRatioWidth = $result_w / $intReqWidth;
                $intRatioHeight = $result_h / $intReqHeight;
                $intAspectRatio = $result_w / $result_h;
                if ($intReqHeight >= 1.0 && $intReqWidth >= 1.0) {
                    if ($intRatioWidth > $intRatioHeight) {
                        $result_h = $intReqWidth * 1 / $intAspectRatio;
                        $result_w = $intReqWidth;
                    } else {
                        $result_w = $intReqHeight * $intAspectRatio;
                        $result_h = $intReqHeight;
                    }
                }
                $result_w = round($result_w, 0);
                $result_h = round($result_h, 0);
            }
        }
        $sCropUrl = Request::currentDomain() . Application::get('base_path', '') . 'library/lib_ImageCropper.php?image=' . str_replace(Application::get('base_path', ''), '/', $sThumb) . '&thumb=' . str_replace(Application::get('base_path', ''), '/', $parPath . $sBestandsnaam) . '&x=' . Request::post('x', '') . '&y=' . Request::post('y', '') . '&w=' . $img_w . '&h=' . $img_h . '&thumb_h=' . $result_h . '&thumb_w=' . $result_w . '&compression=97';
        $targetFile = Server::mapPath($parPath . $sBestandsnaam);
        if (file_exists($targetFile)) {
            unlink($targetFile);
        }
        lib_HttpGet($sCropUrl);
        // Generate WebP + responsive variants from the cropped JPG
        $displayFilename = $sBestandsnaam;
        $croppedPath = Server::mapPath($parPath . $sBestandsnaam);
        if (Image::isWebPSupported() && file_exists($croppedPath)) {
            ResponsiveImage::generate($croppedPath, ResponsiveImage::DEFAULT_QUALITY);
            $webpBestandsnaam = preg_replace('/\.jpg$/i', '.webp', $sBestandsnaam);
            $webpPath = Server::mapPath($parPath . $webpBestandsnaam);
            if (Image::convertToWebP($croppedPath, $webpPath, ResponsiveImage::DEFAULT_QUALITY)) {
                $displayFilename = $webpBestandsnaam;
            }
        }
        echo '					<script>
        $( document ).ready( function(){
            jQuery(".explain").html( "Formaat: " + jQuery("#result_img").width().toString() + "px breed x " + jQuery("#result_img").height().toString() + "px hoog");
        });
        </script>';
        echo '					<h2>';
        echo '						<a href="';
        echo Request::url();
        echo '"><span class="bullet">1</span></a>';
        echo '						<a href="javascript:history.go(-1)"><span class="bullet">2</span></a>';
        echo '						<span class="bullet active">3</span>';
        echo '						Beeld plaatsen';
        echo '						<span class="GenButton" style="float:right">';
        echo '							<a class="button" href="javascript:DoClose(\'';
        echo $displayFilename;
        echo '\')">Plaats beeld</a>';
        echo '						</span>';
        echo '					</h2>';
        echo '					<div class="explain">';
        echo '					</div>';
        echo '						<div style="margin-top:40px;text-align:center">';
        $cacheBust = str_replace(':', '_', date("H:i:s"));
        if (preg_match('/\.webp$/i', $displayFilename)) {
            $jpgFallback = preg_replace('/\.webp$/i', '.jpg', $displayFilename);
            echo '							<picture>';
            echo '								<source srcset="' . $parPath . $displayFilename . '?dummy=' . $cacheBust . '" type="image/webp">';
            echo '								<img id=result_img src="' . $parPath . $jpgFallback . '?dummy=' . $cacheBust . '" alt="' . htmlspecialchars($displayFilename) . '">';
            echo '							</picture>';
        } else {
            echo '							<img id=result_img src="' . $parPath . $displayFilename . '?dummy=' . $cacheBust . '">';
        }
        echo '						</div>';
        echo '				</div>';
    }
    echo '	</form>';
    echo '</BODY>';
    echo '</HTML>';
}
// Call main function
main();
?>
