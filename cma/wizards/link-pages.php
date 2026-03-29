<?php
use App\Library\Application;
use App\Library\Request;
use App\Library\Response;

require_once __DIR__ . '/../bootstrap.inc';

/**
* Main
*/
function main()
{
    Response::noCache();
    define("STRDEFAULTEXTERNAL", 'https://');

    $serverName = Request::server('SERVER_NAME', 'localhost');
    $basePath = Application::get('base_path', '');
    $imgPath = Application::get('cma_htmledit_img_path', '');

    echo '<HTML><HEAD>';
    cma_error_handler();
    echo '<TITLE>Wizard implementatie !</TITLE>';
    echo '<script>
    var last_page = 6;
    function CheckLink()
    {
        if (document.getElementById("url_link").value!=""){return true;
        } else {modal_alert ("Geef een internet-adres voordat u verder gaat");
            return false;
        }
    }
    function CheckEmail()
    {
        if (document.getElementById("email").value!=""){return true;
        } else {modal_alert ("Voer het email adres in voordat u verder gaat");
            return false;
        }
    }
    function CheckFilename()
    {
        if (document.getElementById("filename").value!=""){return true;
        } else {modal_alert ("Selecteer een bestand voordat u verder gaat");
            return false;
        }
    }
    function call_WizardGetNextPage( current_page )
    {
        var iPage;
        switch ( current_page ) {
        case 1:
            if (document.getElementById("linktype0").checked) {
                return 3;
            }
            if (document.getElementById("linktype1").checked) {
                return 2;
            }
            if (document.getElementById("linktype2").checked) {
                return 4;
            }
            if (document.getElementById("linktype3").checked) {
                PlaceImage();
                return 5;
            }
            break;
        case 2:
            return ( CheckLink()  ? 6 : current_page);
        case 3:
            return ( CheckEmail() ? 6 : current_page);
        case 4:
            return ( CheckFilename() ? 6 : current_page);
        case 5:
            return ( CheckFilename() ? 6 : current_page);
        default:
            return ( (current_page < last_page) ? current_page + 1 : current_page );
        }
    }
    function call_WizardFinishPressed()
    {
        var arrResult  = new Array;
        var strFullSpec = "";
        var strTarget = "";
        var sPath = "http://' . $serverName . $basePath . '";
        if (document.getElementById("linktype0").checked) {
            arrResult["link"] = "mailto:" + email.value;
            if (subject.value!="") {
                arrResult["link"] = arrResult["link"] + "?subject=" + subject.value
            }
        }
        if (document.getElementById("linktype1").checked) {
            if (document.getElementById("targettype0").checked) {
                strTarget=" target=_top"
            } else {
                strTarget=" target=_blank"
            }
            if (document.getElementById("url_link").value.indexOf(sPath) != -1) {
                strTarget=" target=_top"
            }
            arrResult["link"] = url_link.value;
        }
        if (document.getElementById("linktype2").checked) {
            arrResult["link"] = "http://' . $serverName . $imgPath . '" + filename.value;
            strTarget=" target=_blank"
        }
        if (document.getElementById("linktype3").checked) {
            arrResult["link"] = "http://' . $serverName . $imgPath . '" + filename.value;
        }
        if (arrResult["link"].indexOf("www.") == -1) {
            arrResult["link"] = arrResult["link"].replace( sPath, "./")
        }
        if (document.getElementById("linktype3").checked || arrResult["link"].substring(arrResult["link"].lastIndexOf(".") + 1, arrResult["link"].length).toLowerCase()=="jpg") {
            arrResult["link"] = "javascript:lib_window_ImageZoom(\'" + arrResult["link"] + "\'";
            arrResult["link"] = arrResult["link"] + ",\'" + escape(document.getElementById("title_id").value) + "\')";
            strTarget="" ;
        }
        strFullSpec = "<a href=\"" + arrResult["link"] + "\"" + strTarget;
        if (document.getElementById("title_id").value!="") {
            strFullSpec = strFullSpec + " title=\"" + document.getElementById("title_id").value + "\"";
        }
        strFullSpec = strFullSpec + ">";
        var editor = window.parent.window.dialogArguments["editor"];
        var innerText = window.parent.window.dialogArguments["innertext"];
        if (innerText=="" || !innerText || typeof innerText === "object") {
            var cTitle = document.getElementById("title_id").value;
            innerText = cTitle ? cTitle : filename.value.replace(/_/g," ");
        }
        editor.insertHtml( strFullSpec + innerText + "</a>" );
        return arrResult;
    }
    function call_WizardGetShowFinish( current_page )
    {
        if (current_page==2) {
            document.getElementById("url_link").focus();
        }
        if (current_page==3) {
            document.getElementById("email").focus();
        }
        if (current_page==6) {
            document.getElementById("title_id").focus();
        }
        return (current_page==last_page);
    }
    function selectfile( strPath, strFile )
    {
        link.value = strPath + strFile;
        parent.WizardGetNextPage();
    }
    </script>';
    echo '</HEAD>';
    echo '<!-- the actual margins are defined in wizard.php -->';
    echo '<BODY style="margin:10 0 0 0" class=wizardcontent onload="if(window.parent && typeof window.parent.WizardActivatePage===\'function\')window.parent.WizardActivatePage(1)" onkeypress="if(window.parent && typeof window.parent.WizardButtonPressed===\'function\')window.parent.WizardButtonPressed(event.keyCode);return true;">';

    // fields to store information in
    echo '<input type=hidden id=link name=link value="">';
    echo '<input type=hidden id=filename name=filename value="">';
    echo '<input type=hidden id=imgwidth name=imgwidth value="">';
    echo '<input type=hidden id=imgheight name=imgheight value="">';

    // Page 1 - Link type selection
    echo '<DIV ID=page1 style=display:none>';
    echo '<H3>Type link</H3>';
    echo '<font class=wiz_caption>Geef het type link dat je wilt aanmaken:</font><BR><BR>';
    echo '<B>Eenvoudig: zonder plaatsen</B><BR>';
    echo '<INPUT TYPE=RADIO id=linktype0 name=linktype VALUE=MAILTO checked>&nbsp;&nbsp;<a href="javascript:document.getElementById(\'linktype0\').checked=true;parent.WizardGetNextPage()">email adres</a><BR>';
    echo '<INPUT TYPE=RADIO id=linktype1 name=linktype VALUE=INTERNAL>&nbsp;&nbsp;<a href="javascript:document.getElementById(\'linktype1\').checked=true;parent.WizardGetNextPage()">Link naar bestand of internet-pagina</a><BR><BR>';
    echo '<B>Geavanceerd: inclusief plaatsen</B><BR>';
    echo '<INPUT TYPE=RADIO id=linktype2 name=linktype VALUE=FILE>&nbsp;&nbsp;<a href="javascript:document.getElementById(\'linktype2\').checked=true;parent.WizardGetNextPage()">Bestand (inclusief plaatsen) zoals PDF of Word</a><BR>';
    echo '<INPUT TYPE=RADIO id=linktype3 name=linktype VALUE=IMAGE>&nbsp;&nbsp;<a href="javascript:document.getElementById(\'linktype3\').checked=true;parent.WizardGetNextPage()">Plaatje (max. 800x600)</a><BR><BR>';
    echo '</DIV>';

    // Page 2 - External link
    echo '<DIV ID=page2 style=display:none>';
    echo '<H3>Bestand of internet-pagina</H3>';
    echo '<font class=wiz_caption>Geef het internetadres</font><BR>';
    echo '<input type=text id=url_link name=url_link value="' . STRDEFAULTEXTERNAL . '" style="width:100%;max-width:800px" ><BR>';
    echo '<font class=wiz_tip>Tip:&nbsp;Ga met de browser naar de pagina en copieer het adres uit de adres-balk via Ctrl-C en plak deze hier met Ctrl-V.</font><br>';
    echo '<br><BR>';
    echo '<H3>Venster</h3>';
    echo '<INPUT TYPE=RADIO id=targettype0 name=targettype VALUE=_TOP>&nbsp;&nbsp;<a href=# onclick=\'javascript:document.getElementById("targettype0").checked=true\'>Huidige venster (link naar eigen site)</a><BR>';
    echo '<INPUT TYPE=RADIO id=targettype1 name=targettype VALUE=_BLANK checked>&nbsp;&nbsp;<a href=# onclick=\'javascript:document.getElementById("targettype1").checked=true\'>Nieuw venster (externe links of bestanden)</a><BR>';
    echo '<font class=wiz_tip>Tip:&nbsp;Gebruik huidig venster alleen als de link verwijst naar een pagina op de eigen site.</font><br>';
    echo '</DIV>';

    // Page 3 - Email link
    echo '<DIV ID=page3 style=display:none>';
    echo '<H3>E-mail link</H3>';
    echo '<font class=wiz_caption>Geef het email adres:</font><BR>';
    echo '<input type=text name=email id=email value="" style="width:230px" ><br><br>';
    echo '<font class=wiz_caption>Geef het onderwerp:<BR>';
    echo '<input type=text name=subject id=subject value="" style="width:230px" > (optioneel) ';
    echo '</DIV>';

    // Page 4 - File upload
    echo '<DIV ID=page4 style=display:none>';
    echo '<H3>Plaats het bestand</H3>';
    echo '<iframe id=fileselect height=660 src="file_frameset.php?basepath=' . $imgPath . '&dir=N&upload=J" frameborder=0 border=0 width=100%></iframe>';
    echo '</DIV>';

    // Page 5 - Image upload
    echo '<DIV ID=page5 style=display:none>';
    echo '<script>function PlaceImage(){ }</script>';
    echo '<iframe id=fileselect height=660 src="file_frameset.php?basepath=' . $imgPath . '&dir=N&image=Y&upload=J" frameborder=0 border=0 width=100%></iframe>';
    echo '<br><br>';
    echo '<INPUT type=button value="Afbeelding plaatsen" style=width:140px onclick=javascript:PlaceImage()>';
    echo '</DIV>';

    // Page 6 - Description
    echo '<DIV ID=page6 style=display:none>';
    echo '<H3>Beschrijving</H3>';
    echo '</B>';
    echo '<font class=wiz_caption>Geef een omschrijving voor deze link:<BR>';
    echo '<input type=text name=title id=title_id value="" style="width:230px" > (optioneel) ';
    echo '</DIV>';

    echo '</BODY>';
    echo '</HTML>';
}

// Call main function
main();
?>
