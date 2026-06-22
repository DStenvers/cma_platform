
CKEDITOR.plugins.add( 'imgtitle', {
    icons: 'imgtitle',
    init: function( editor ) {

        editor.addCommand( 'imgtitle', new CKEDITOR.dialogCommand( 'imgtitle' ) );
        editor.ui.addButton( 'imgtitle', {
            label: 'Invoegen beeld met titel',
            command: 'imgtitle',
            icon: CKEDITOR.plugins.getPath('imgtitle') + '/icons/imgtitle.png'
        });

        CKEDITOR.dialog.add( 'imgtitle', this.path + 'dialogs/imgtitle.js' );
    }
});