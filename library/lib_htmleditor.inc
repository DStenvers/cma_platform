<?php
// aanzetten editors : geeft etxtarea's een class .html
// $("#" + sFormID + " textarea.html").each( function () {
    // CreateFKEditor($(this).attr("name"), 220);
    // var editor = CKEDITOR.instances[$(this).attr("name")];
    // editor.updateElement()
    // });
// bewaren:
// $("#" + sFormID + " textarea.html").each( function () {
    // var editor = CKEDITOR.instances[$(this).attr("name")];
    // editor.updateElement()
    // if (editor) { editor.destroy(true); }
    // });
use App\Library\Application;
use App\Library\Request;

$lib_htmlEditor_css = Application::get('cma_htmledit_css', '');
/**
* Lib Htmleditorinit
*
*/
function lib_HTMLEditorInit()
{
    global $lib_htmlEditor_css;
    echo '	<script src="' . (Application::get('base_path')) . '' . 'CMA/ckeditor/ckeditor.js"></script>';
    echo '	<script src="' . (Application::get('base_path')) . '' . 'CMA/include/CKeditor.js"></script> ';
    echo '	<script src="' . (Application::get('base_path')) . '' . 'CMA/wizards/wizard.js"></script>';
    echo '	<script>;
    var HTMLEdit = new Object();
    HTMLEdit.image_resize_type = 1; // maximum
    HTMLEdit.image_max_width   = ' . (Application::get('cma_htmledit_img_maxwidth')) . '' . ';
    HTMLEdit.image_max_height  = ' . (Application::get('cma_htmledit_img_maxheight')) . '' . ';
    HTMLEdit.image_path		   = "' . (Application::get('cma_htmledit_img_path')) . '' . '";
    HTMLEdit.domain			   = "' . (Request::currentDomain()) . '' . '";
    HTMLEdit.subpath		   = "' . (Application::get('base_path')) . '' . '";
    HTMLEdit.maximized		   = false;
    HTMLEdit.debug             = false;
    HTMLEdit.allowBR           = true;
    /**
    * Createfkeditor
    */
    function CreateFKEditor( fieldname, nHeight )
    {
        var config = {};
        config.contentsCss = \'' . ($lib_htmlEditor_css) . '' . '\';
        config.language = \'nl\';
        config.contentsLanguage = config.language;
        config.defaultLanguage = config.language;
        config.scayt_sLang = \'nl_NL\';
        config.skin = \'office2013_modified\';
        config.pasteFromWordPromptCleanup = true;
        config.height = nHeight.toString()+\'px\';
        config.extraAllowedContent=\'script;
        area(*){*}[*];
        table(*){*}[*];
        h1;
        h2;
        h3;
        td(*){*}[*];
        form(*){*}[*];
        iframe(*){*}[*];
        input(*){*}[*];
        map(*){*}[*];
        button(*){*}[*];
        textarea(*){*}[*];
        hr(*){*}[*];
        tr(*){*}[*];
        div(*){*}[*];
        span(*){*}[*];
        a(*){*}[*];
        style(*){*}[*];
        img(*){*}[*];
        select(*){*}[*];
        option(*){*}[*];
        object(*){*}[*];
        embed(*){*}[*]  \';
        config.scayt_autoStartup=false;
        config.disableObjectResizing = true;
        config.resize_enabled = false;
        config.toolbarCanCollapse = false;
        config.entities = true;
        config.basicEntities = true;
        config.toolbar = [;
        { name: \'basic\', items: [ \'Cut\', \'Copy\', \'Paste\', \'PasteText\', \'-\', \'Undo\', \'Redo\', \'-\', \'Bold\', \'-\', \'RemoveFormat\' ] },
        { name: \'paragraph\', items: [ \'-\', \'NumberedList\', \'BulletedList\'] },
        { name: \'links\', items: [ \'Link\', \'Unlink\' ] },
        ];
        config.startupFocus = true;
        if (HTMLEdit.allowBR) { config.enterMode = CKEDITOR.ENTER_BR; }
        CKEDITOR.replace( fieldname, config );
        CKEDITOR.instances[fieldname].on(\'contentDom\', function() {
            $(".cke_button__source_label").html("HTML");
            CKEDITOR.instances[fieldname].document.on(\'keyup\', function(event) {
                if ((event.data.$.altKey) && (event.data.$.keyCode==173)) {
                    CKEDITOR.instances[fieldname].insertHtml(\'&shy;
                    \');
                };
            });
        });
        CKEDITOR.instances[fieldname].on(\'instanceReady\', function ( event ) {
            var overridecmd_horizontalrule = new CKEDITOR.command(CKEDITOR.instances[ event.sender.name ], {
                exec: function(editor){
                    CKEDITOR.instances[ event.sender.name ].insertHtml(\'<hr noshade size=1>\');
                }
            });
            CKEDITOR.instances[ event.sender.name ].commands.horizontalrule.exec = overridecmd_horizontalrule.exec;
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
}
?>
