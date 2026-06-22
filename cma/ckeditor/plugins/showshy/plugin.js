/**
 * @license Copyright (c) 2003-2014, CKSource - Frederico Knabben. All rights reserved.
 * For licensing, see LICENSE.md or http://ckeditor.com/license
 */

/**
 * @fileOverview The "shy" plugin. Enable it will make all soft hyphens visible 
 * 
 */

( function() {
	'use strict';
	var showShyClassName = "ckeditor_showshy";
	var showButton = false;
	
	var commandDefinition = {
		readOnly: 1,
		preserveState: true,
		editorFocus: false,

		exec: function( editor ) {
			this.toggleState();
			this.refresh( editor );
		},

		refresh: function( editor ) {
			if ( editor.document ) {
				// Show shy turns inactive after editor loses focus when in inline.
				var showShy = true; // (this.state == CKEDITOR.TRISTATE_ON) && ( editor.elementMode != CKEDITOR.ELEMENT_MODE_INLINE ||  editor.focusManager.hasFocus );

				var funcName = showShy ? 'attachClass' : 'removeClass';
				editor.editable()[ funcName ]( showShyClassName );
			}
		}
	};
	
	CKEDITOR.plugins.add( 'showshy', {
		lang: 'en,nl',  
		icons: 'showshy,showshy-rtl', 
		hidpi: true,  
		onLoad: function() {	
			var cssShy = '.' + showShyClassName + '{width:10px;background-color:#efefef;display:inline-block;text-align:center;color:#666666;height:inherit}'+
						 '.' + showShyClassName + '::after{display:inline-block;content:"-";color:red}';
			CKEDITOR.addCss( cssShy );
		},
		init: function( editor ) {

			var command = editor.addCommand( 'showshy', commandDefinition );
			command.canUndo = false;
			
			if (!showButton) { 
				command.setState( CKEDITOR.TRISTATE_ON );
			} else { 
				if ( editor.config.startupOutlineShy ) { command.setState( CKEDITOR.TRISTATE_ON ); }

				editor.ui.addButton && editor.ui.addButton( 'ShowShy', {
					label: editor.lang.showblocks.toolbar,
					command: 'showshy',
					toolbar: 'tools,20'
				} );
				
				// Refresh the command on setData.
				editor.on( 'mode', function() {
					if ( command.state != CKEDITOR.TRISTATE_DISABLED ) command.refresh( editor );
				} );

				// Refresh the command on focus/blur in inline.
				if ( editor.elementMode == CKEDITOR.ELEMENT_MODE_INLINE ) {
					editor.on( 'focus', onFocusBlur );
					editor.on( 'blur', onFocusBlur );
				}

				// Refresh the command on setData.
				editor.on( 'contentDom', function() {
					if ( command.state != CKEDITOR.TRISTATE_DISABLED ) command.refresh( editor );
				} );
			}
			
			editor.on( 'removeFormatCleanup', function( evt ) {
				var element = evt.data;
				if ( editor.getCommand( 'showshy' ).state == CKEDITOR.TRISTATE_ON ) {
					if (element.contains( '&shy;' )) {
						element.innerHtml = element.innerHtml.replace( "&shy;", "<span class=\"" + showShyClassName + "\">&shy;</span>");
					}
				}
			} );
			
			function onFocusBlur() {
				command.refresh( editor );
			}
		},
		afterInit: function( editor ) {
			var dataProcessor = editor.dataProcessor;

			dataProcessor.toDataFormat = function ( html, fixForBody ) {
				html = replaceAll( html, "<span class=\"" + showShyClassName + "\"></span>", "&shy;");	
				return html;
			};

			dataProcessor.toHtml = function ( data, fixForBody ) {
				data = replaceAll( data, "&shy;", "<span class=\"" + showShyClassName + "\"></span>");	
				return data;
			};

if (false) {			
			var dataFilter = dataProcessor && dataProcessor.dataFilter;
			if ( dataFilter ) {
				dataFilter.addRules( {
					html: function( value ) {
						return value.replace("&shy;", "<span class=\"" + showShyClassName + "\">&shy;</span>");
					},
					text: function( value ) {
						return value.replace("&shy;", "<span class=\"" + showShyClassName + "\">&shy;</span>");
					},
					elements: {
						'': function( element ) {
							var attributes = element.attributes;
							var cssClass = attributes[ 'class' ];

							if ( ( !cssClass || cssClass.indexOf( showShyClassName ) == -1 ) )
								attributes[ 'class' ] = ( cssClass || '' ) + ' ' + showShyClassName;
						}
					}
				} );
			}

			var htmlFilter = dataProcessor && dataProcessor.htmlFilter;
			if ( htmlFilter ) {

				htmlFilter.addRules( {
					html: function( value ) {
						return value.replace("<span class=\"" + showShyClassName + "\">&shy;</span>","&shy;" );
					},
					text: function( value ) {
						return value.replace("<span class=\"" + showShyClassName + "\">&shy;</span>", "&shy;");
					},
					elements: {
						'': function( table ) {
							var attributes = table.attributes;
							var cssClass = attributes[ 'class' ];
							cssClass && ( attributes[ 'class' ] = cssClass.replace( showShyClassName, '' ).replace( /\s{2}/, ' ' ).replace( /^\s+|\s+$/, '' ) );
						}
					}
				} );				
			}
}
		}
	} );
	function replaceAll(str, find, replace) {
		return str.replace(new RegExp(find, 'g'), replace);
	}
} )();

/**
 * Whether to automaticaly enable the show block" command when the editor loads.
 *
 *		config.startupOutlineShy = true;
 *
 * @cfg {Boolean} [startupOutlineShy=true]
 * @member CKEDITOR.config
 */
