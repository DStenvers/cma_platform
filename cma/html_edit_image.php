<?php
require_once __DIR__ . '/bootstrap.inc';

/**
* Main
*/
function main()
{
    echo '<HTML><HEAD> ';

    echo '<script src="minify.php?f=wizards/wizard.js,../library/library.js,../library/colorpicker.js,../library/layoutpicker.js"></script>';

    echo '<script >;
    var selectedImage;

    /**
    * Init
    */
    function init()
    {
        selectedImage = window.parent.window.dialogArguments["image"];
        if (selectedImage) {
            document.getElementById("title_id").value= selectedImage.title ? selectedImage.title : "";
            document.getElementById("border_id").value=selectedImage.style.borderWidth.replace("px","");
            if(document.getElementById("border_id").value=="") {
                document.getElementById("border_id").value="0";
            }
            document.getElementById("image_margin_t").value = selectedImage.style.marginTop.replace( "px", "")
            document.getElementById("image_margin_l").value = selectedImage.style.marginLeft.replace( "px", "")
            document.getElementById("image_margin_b").value = selectedImage.style.marginBottom.replace( "px", "")
            document.getElementById("image_margin_r").value = selectedImage.style.marginRight.replace( "px", "")

            if (selectedImage.align!="") {
                layout_switch( "image_align", selectedImage.align);
            }
            if (selectedImage.style.borderColor!="") {
                setColor( selectedImage.style.borderColor );
            }
        } else {
            libAlert("Kan gegevens plaatje niet uitlezen!");
        }
    }

    /**
    * Call Wizardgetnextpage
    */
    function call_WizardGetNextPage( current_page )
    {
        return 1;
    }

    /**
    * Call Wizardfinishpressed
    */
    function call_WizardFinishPressed()
    {
        var arr = new Array();

        if (selectedImage) {
            selectedImage.title = document.getElementById("title_id").value;
            selectedImage.style.borderWidth = (document.getElementById("border_id").value!="" ? document.getElementById("border_id").value + "px" : "");
            selectedImage.style.borderStyle = (document.getElementById("border_id").value!="" ? "solid" : "");
            selectedImage.style.borderColor = document.getElementById("borderColor").value;

            if (document.getElementById("image_align").value != "") {
                selectedImage.align = document.getElementById("image_align").value;
            } else {
                selectedImage.removeAttribute(\'align\',0)
            }
            selectedImage.style.marginTop    = (document.getElementById("image_margin_t").value!="") ? document.getElementById("image_margin_t").value +"px":"";
            selectedImage.style.marginLeft   = (document.getElementById("image_margin_l").value!="") ? document.getElementById("image_margin_l").value +"px":"";
            selectedImage.style.marginBottom = (document.getElementById("image_margin_b").value!="") ? document.getElementById("image_margin_b").value +"px":"";
            selectedImage.style.marginRight  = (document.getElementById("image_margin_r").value!="") ? document.getElementById("image_margin_r").value +"px":"";
        }
        return arr;
    }

    /**
    * Call Wizardgetshowfinish
    */
    function call_WizardGetShowFinish( current_page )
    {
        return true;
    }

    /**
    * Printalign
    */
    function printAlign()
    {
        if ((cellAlign != undefined) && (cellAlign != "")) {
            document.write(\'<option selected>\' + cellAlign)
            document.write(\'<option>' . ($lang_geen) . '' . '\')
        } else {
            document.write(\'<option selected>' . ($lang_geen) . '' . '\')
        }
    }

    /**
    * Printvalign
    */
    function printvAlign()
    {
        if ((cellvAlign != undefined) && (cellvAlign != "")) {
            document.write(\'<option selected>\' + cellvAlign)
            document.write(\'<option>' . ($lang_geen) . '' . '\')
        } else {
            document.write(\'<option selected>' . ($lang_geen) . '' . '\')
        }
    }
    </script>';

    echo '</head>';

    echo '<BODY class=wizardcontent style=margin:0px onload="init();if(window.parent && typeof window.parent.WizardActivatePage===\'function\')window.parent.WizardActivatePage(1)" onkeypress="if(window.parent && typeof window.parent.WizardButtonPressed===\'function\')window.parent.WizardButtonPressed(event.keyCode);return true;">';

    echo '<DIV ID=page1>';

    echo '<form name=imageForm>';

    echo '<table cellspacing=0 cellpadding=0>';

    echo '<tr height=35><td>Omschrijving:&nbsp;</td><td colspan=2><input id=title_id name=title size=50 maxlength=128></td></tr>';

    echo '<tr height=35><td>Rand dikte:&nbsp;</td><td><input type="text" name="border" id=border_id ONKEYPRESS="event.returnValue=IsDigit()" size="2" value="1" maxlength="2"></td><td>Beeldpunten</td></td></tr>';

    echo '<tr height=35><td>Rand kleur:&nbsp;</td><td><script>ColorPicker("borderColor", "");</script></td></tr>';

    echo '<tr height=35><td>Horizontale positie:&nbsp;</td><td><script>LayoutPicker( "image_align", "", "left", "center", "right");</script></td></tr>';

    echo '<tr height=35><td>Marges:&nbsp;</td><td><script>PickMargins( "image_margin", "","","","");</script></td><td>Beeldpunten</td></tr>';

    echo '</table>';

    echo '</form>';

    echo '</div>';

    echo '</body>';

    echo '</html>';

}

// Call main function
main();
?>
