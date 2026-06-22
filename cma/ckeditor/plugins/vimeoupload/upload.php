<?php
use App\Library\Application;
use App\Library\Request;
use App\Library\Response;

require_once __DIR__ . '/../../../bootstrap.inc';

Response::noCache();

$parPath = 'vimeo/';
$strPrePath = '';
$blnFromCMA = Request::query('fromCMA', '') != '';

$basePath = Application::get('base_path', '');
$isLocal = Application::get('local', false);
$jqueryVersion = defined('STRJQUERYVERSION') ? STRJQUERYVERSION : '3.6.0';
?>
<!DOCTYPE html>
<HTML><head>
<?php cma_error_handler(); ?>
<link rel="stylesheet" href="<?php echo $basePath; ?>library/fineuploader/fineuploader.css" type="text/css">
<link rel="stylesheet" href="<?php echo $basePath; ?>library/css/lib-variables.css" type="text/css">
<link rel="stylesheet" href="<?php echo $basePath; ?>library/library.min.css" type="text/css">
<link rel="stylesheet" href="<?php echo $basePath; ?>rinoportal.css" type="text/css">
<link rel="stylesheet" href="<?php echo $basePath; ?>adam.css" type="text/css">
<script type="text/javascript" src="//ajax.googleapis.com/ajax/libs/jquery/<?php echo $jqueryVersion; ?>/jquery.<?php echo $isLocal ? '' : 'min.'; ?>js"></script>
<script type="text/javascript" src="<?php echo $basePath; ?>general.js"></script>
<script type="text/javascript" src="<?php echo $basePath; ?>library/library.min.js"></script>
<script type="text/javascript" src="<?php echo $basePath; ?>library/formval_nl.js"></script>
<script type="text/javascript" src="<?php echo $basePath; ?>library/fineuploader/fineuploader-jquery-plugin.js"></script>
<script type="text/javascript" src="<?php echo $basePath; ?>library/fineuploader/fineuploader.min.js"></script>
<script type="text/javascript">
jQuery(document).ready( function() {
    var my_errorHandler = function(event, id, fileName, reason, xhr) {
        modal_alert( fileName + ' kon niet worden geplaatst/aangemaakt.\r\n' + reason.replace( /<br>/gi, "\r\n") );
    };

    jQuery('.fine-uploader_video').fineUploader({
        request: { endpoint: '<?php echo $basePath; ?>upload_handler.php?path=<?php echo $basePath . $parPath; ?>&path_extra=<?php echo $strPrePath; ?>' },
        multiple: false,
        uploaderType: 'advanced',
        validation: { allowedExtensions: ['mp4', 'avi', 'mpg', 'mpeg', 'mov', 'wmv', 'vob', 'asf'], sizeLimit: 1000485760},
        messages: {typeError: "Alleen video's met extentie 'asf', 'avi', 'mov', 'mp4', 'asf', 'wmv', 'vob', 'mpg' of 'mpeg' zijn toegestaan."},
        debug: <?php echo $isLocal ? 'true' : 'false'; ?>
    })
    .on('error', my_errorHandler)
    .on('complete', function(event, id, fileName, responseJSON) {
        if (responseJSON.success) {
            if (responseJSON.filename) {fileName=responseJSON.filename}
            jQuery(this).addClass("fine-uploader_succes");
            var elt_id = jQuery(this).attr("data-field");
            document.getElementById( elt_id ).value = '<?php echo $strPrePath; ?>' + fileName;
            var file_elt = jQuery(this).find('span.qq-upload-file');
            file_elt.html('<a href="<?php echo $basePath . $parPath . $strPrePath; ?>' + fileName + '" target=_blank>Bekijk bestand</a>');
        } else {
            modal_alert(responseJSON.error);
        }
    });

    // zet de teksten op maat op basis van de data-buttontext property
    uploader_buttonsinit();

    jQuery('.fine-uploader .qq-uploader .qq-upload-button input').each( function() {
        jQuery(this).css("background-color", "transparent" );
        elt = jQuery(this).get(0);
        if (elt) {
            if (elt.style.filter) {
                elt.style.filter = 'alpha(Opacity=0)'; elt.parentElement.style.filter = 'alpha(Opacity=100)'
            }
        };
    });
});
</script>
<style>
label {width:90px !important; display:inline-block; color:#666666}
label.req::after{ color:red; content:' *'}
.fine-uploader ul.qq-upload-list span.qq-upload-file {display:inline !important}
</style>
</head>
<BODY class="popup">
<FORM id="main" action="/vimeo/upload.php<?php echo $blnFromCMA ? '?fromCMA=Y' : ''; ?>" style="margin:0px" data-show-tooltip="N" method="post" onsubmit="return form_valid(this)">
<?php
echo '<input type="hidden" id="video_id" name="video"/>' . PHP_EOL;
echo '<div><label class=req>Videobestand</label><div class="fine-uploader fine-uploader_video" style=display:inline-block data-field="video_id" data-buttontext="Selecteer video"></div></div>' . PHP_EOL;
echo '<div><label class=req>Titel</label><input type="text" data-required=Y value="" id="titel" name="titel" maxlength=56 style="width:375px;height:24px;margin-top:3px"></div>' . PHP_EOL;
echo '<div><label class=req>Beschrijving</label><input type="text" data-required=Y value="" id="beschrijving" name="beschrijving" maxlength=56 style="width:375px;height:24px;margin-top:3px"></div>' . PHP_EOL;
echo '<DIV ID=button_div><div><span class=GenButton><a href="javascript:if (form_valid(document.forms.main)) document.forms.main.submit()">Plaats op Vimeo</a></span></div>' . PHP_EOL;
?>
</FORM>
</BODY></HTML>
