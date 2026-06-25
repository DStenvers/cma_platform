<?php
require_once __DIR__ . '/bootstrap.inc';

/**
* Main
*/
function main()
{
    echo '<HTML><HEAD> ';

    cma_error_handler();
    echo '<link rel="stylesheet" href="minify.php?f=assets/css/style.css,assets/css/form.css">';
    echo '<script src="minify.php?f=wizards/wizard.js,../library/library.js,../library/colorpicker.js,../library/layoutpicker.js"></script>';

    echo '<script>
    // The editor and selected image live on the top window (set by CMA.editor before
    // this dialog opened). Same-origin iframe, so read them directly.
    var EDITWIN = window.top;
    var selectedImage = EDITWIN.selectedImage ? (EDITWIN.selectedImage.$ || EDITWIN.selectedImage) : null;

    function init()
    {
        if (selectedImage) {
            document.getElementById("title_id").value = selectedImage.getAttribute("title") || "";
            document.getElementById("border_id").value = (selectedImage.style.borderWidth || "").replace("px","") || "0";
            document.getElementById("image_margin_t").value = (selectedImage.style.marginTop    || "").replace("px","");
            document.getElementById("image_margin_l").value = (selectedImage.style.marginLeft   || "").replace("px","");
            document.getElementById("image_margin_b").value = (selectedImage.style.marginBottom || "").replace("px","");
            document.getElementById("image_margin_r").value = (selectedImage.style.marginRight  || "").replace("px","");

            var align = selectedImage.getAttribute("align") || "";
            if (align != "") {
                layout_switch( "image_align", align);
            }
            if (selectedImage.style.borderColor != "") {
                setColor( selectedImage.style.borderColor );
            }
        } else if (typeof libAlert === "function") {
            libAlert("Kan gegevens plaatje niet uitlezen!");
        }
    }

    function closeDialog()
    {
        if (window.parent && typeof window.parent.lib_OpenWindowCenteredClose === "function") {
            window.parent.lib_OpenWindowCenteredClose(true);
        }
    }

    function save()
    {
        if (selectedImage && EDITWIN.CMA && EDITWIN.CMA.editor) {
            EDITWIN.CMA.editor.applyImageProps({
                title:        document.getElementById("title_id").value,
                border:       document.getElementById("border_id").value,
                borderColor:  document.getElementById("borderColor").value,
                align:        document.getElementById("image_align").value,
                marginTop:    document.getElementById("image_margin_t").value,
                marginLeft:   document.getElementById("image_margin_l").value,
                marginBottom: document.getElementById("image_margin_b").value,
                marginRight:  document.getElementById("image_margin_r").value
            });
        }
        closeDialog();
    }
    </script>';

    echo '</head>';

    echo '<BODY style="margin:8px" onload="init()" onkeydown="if(event.keyCode===13){event.preventDefault();save();}">';

    echo '<DIV ID=page1>';

    echo '<form name=imageForm>';

    echo '<table cellspacing=0 cellpadding=0>';

    echo '<tr height=35><td>Omschrijving:&nbsp;</td><td colspan=2><input id=title_id name=title size=50 maxlength=128></td></tr>';

    echo '<tr height=35><td>Rand dikte:&nbsp;</td><td><input type="text" name="border" id=border_id ONKEYPRESS="event.returnValue=IsDigit()" size="2" value="1" maxlength="2"></td><td>Beeldpunten</td></td></tr>';

    echo '<tr height=35><td>Rand kleur:&nbsp;</td><td><script>ColorPicker("borderColor", "");</script></td></tr>';

    echo '<tr height=35><td>Horizontale positie:&nbsp;</td><td><script>LayoutPicker( "image_align", "", "left", "center", "right");</script></td></tr>';

    echo '<tr height=35><td>Marges:&nbsp;</td><td><script>PickMargins( "image_margin", "","","","");</script></td><td>Beeldpunten</td></tr>';

    echo '</table>';

    echo '<div style="margin-top:16px;text-align:right">';
    echo '<button type="button" class="button" onclick="closeDialog()">Annuleren</button> ';
    echo '<button type="button" class="button" onclick="save()">Opslaan</button>';
    echo '</div>';

    echo '</form>';

    echo '</div>';

    echo '</body>';

    echo '</html>';

}

// Call main function
main();
?>
