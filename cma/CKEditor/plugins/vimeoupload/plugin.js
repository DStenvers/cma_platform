/*
	See more at: http://truelogic.org/wordpress/2011/11/28/undocumented-ckeditor-hack-to-do-your-own-image-uploads/#sthash.62Aat4mr.dpuf
	sample : https://gist.github.com/garryyao/1170303
*/
CKEDITOR.plugins.add('vimeoupload', { 
	icons: 'vimeoupload.svg',
	requires: [ 'iframedialog' ],
	init: function( editor )
	{
		var height = 230, width = 530;
		
		CKEDITOR.dialog.addIframe(
			   'vimeouploadDialog',
			   'Vimeo upload',
			   this.path + 'upload.asp?fromCMA=Y', width, height,
			   function()
			   {
				   // Iframe loaded callback.
			   },
			   {
					onOk : function()
					{
						var url = "https://player.vimeo.com/video/"+respuesta.id_video+"?portrait=0";
						
 						// Dialog onOk callback.
						var p = new CKEDITOR.dom.element( 'div' );
						p.setAttribute("class", "videodetector");

						var iframe = new CKEDITOR.dom.element( 'iframe' );
						iframe.setAttribute("src", url);
						iframe.setAttribute("frameborder", "0");
						p.append( iframe );
						editor.insertElement(p);
					}
			   }
			);

		editor.addCommand( 'vimeoupload', new CKEDITOR.dialogCommand( 'vimeouploadDialog' ) );

		editor.ui.addButton( 'vimeoupload',
		{
			label: 'Vimeo Upload',
			command: 'vimeoupload',
			icon: this.path + 'icons/vimeoupload.svg'
		} );
	}
} );

// called from the upload script directly
function insertVideo( sUrl ) {
		
	CKEDITOR.dialog.getCurrent().hide();
}

// var toolbar = CKEDITOR.config.toolbar_Full;
// toolbar[toolbar.length-1].items.push( 'Myiframedialog' );
/*
		icons: 'vimeoupload.svg',
	    requires: ['iframedialog'],
	    init:function(editor){
	        editor.addCommand('testimage', new CKEDITOR.dialogCommand('testimage' )); 
	        editor.ui.addButton('vimeoupload',
	        {
	             label:'Vimeo upload',
	             command : 'vimeoupload',
	             icon: this.path + 'vimeoupload.svg',
	                               
	         });
	         CKEDITOR.dialog.addIframe( 'vimeoupload', 'Vimeo upload', 'ckeditor/plugins/vimeoupload/test.html', 500, 400);
	   },

	   	
function closedialog(url) {
	 
	CKEDITOR.dialog.getCurrent().hide();
}

*/