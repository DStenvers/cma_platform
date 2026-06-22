/**
 * @license Copyright (c) 2003-2014, CKSource - Frederico Knabben. All rights reserved.
 * For licensing, see LICENSE.md or http://ckeditor.com/license
 */
 
CKEDITOR.plugins.add( 'literatuur', {
	icons: this.path + 'literatuur.png',
    init: function( editor ) {
        editor.addCommand( 'literatuur', {
            exec: function( editor ) {
                var now = new Date();
                editor.insertHtml( 'Hier komt dan het literatuuritem <em>' + now.toString() + '</em>' );
            }
        });
        editor.ui.addButton( 'literatuur', {
            label: 'Invoegen literatuur',
            command: 'literatuur',
            toolbar: 'insert'
        });
	
	}
});