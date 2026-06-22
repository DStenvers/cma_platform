CKEDITOR.dialog.add( 'imgtitle', function( editor ) {
    return {
        title: 'Geef de beschrijving van de foto',
        minWidth: 400,
        minHeight: 100,
        contents: [
            {
                id: 'tab-basic',
                label: 'Basis instellingen',
                elements: [
                    {
                        type: 'text',
                        id: 'beschrijving',
                        label: 'Beschrijving'
                    }
                ]
            }
        ],
        onOk: function() {
            var dialog 		= this;
			var getDialog   = document.getElementsByClassName('cke_dialog_contents').item(0);
			var caption     = getDialog.getElementsByTagName('input').item(0).value;
			// <img src=\"" + editor.plugins["imgtitle"].path + "img/image-container.jpg\" style=\"width:150px\">
			editor.insertHtml( "<figure><br><br>Selecteer deze tekst <br>en vervang deze door het <br>gewenste plaatje<br><br>&nbsp;<figcaption>" + (caption ? caption : "&nbsp;") + "</figcaption></figure>")
        }
    };
});