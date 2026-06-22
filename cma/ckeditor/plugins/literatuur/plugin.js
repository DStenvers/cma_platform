CKEDITOR.plugins.add( 'literatuur', {
    icons: 'literatuur',
	init: function( editor ) {
			
        editor.addCommand( 'cmdLiteratuur', {
            exec: function( editor ) {
                ToonLiteratuur_dialoog( editor );
            }
        });
		
        editor.ui.addButton( 'literatuur', {
            label: 'Literatuur',
            command: 'cmdLiteratuur',
            toolbar: 'tools'
        });
		
		// http://docs.ckeditor.com/#!/guide/plugin_sdk_sample_2  
		if ( editor.contextMenu ) {
			editor.addMenuGroup( 'literatuurgroep' );
			
			editor.addMenuItem( 'literatuuritem', {
				label: 'Invoegen literatuur',
				icon: this.path + 'icons/literatuur.png',
				command: 'cmdLiteratuur',
				group: 'literatuurgroep'
			});		
			
			editor.contextMenu.addListener( function( element ) {
				return { literatuuritem: CKEDITOR.TRISTATE_OFF };
			});
		}
		
		/* Ctrl-L : literatuur */
		editor.setKeystroke( [
			[ CKEDITOR.CTRL + 76 , 'cmdLiteratuur' ]
		] );

	},
	onLoad: function() {
		CKEDITOR.addCss(
			'.cke_button__literatuur_label .cke_button_label {display:inline !important}'
		);
	}
 });