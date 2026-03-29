bDevelopment = (window.location.href.indexOf('http://localhost/') > -1);
rootUrl = (bDevelopment ? '/rp/' : '/');

function ShowWizard(args) {
	var intHeight = 560;
	var intWidth = 530;
	
	// new: save the caller window 
	args["window"]=window;
	
	if ( args["scalable"] ) {
		if (screen.width>800)  { intWidth  = screen.width  - 120 }
		if (screen.height>800) { intHeight = screen.height - 240 }
	}
	var sWizard = sWizard = rootUrl + "cma/wizard.asp";
	
	if (window.showModalDialog) {
		return window.showModalDialog( sWizard, args, "dialogWidth:"+intWidth.toString()+"px;dialogHeight:"+intHeight.toString()+"px;edge:Raised;center:Yes;status:no;resize:no;help:0;scroll:0"); 
	} else {
		if (screen) {
			var left = (screen.width/2 )-(intWidth/2 );
			var top  = (screen.height/2)-(intHeight/2);
		} else {
			// no screen object, assume 1024x800.
			var left = (1024/2)-(intWidth/2);
			var top  = (800/2)-(intHeight/2);	
		}
		try { 
			var modal = window.open( sWizard, "Wizard", "width="+intWidth.toString()+",height="+intHeight.toString()+",top="+ top.toString()+",left="+left.toString()+",center=yes,modal=yes,status=no,menubar=no,resizable=no,alwaysRaised=yes");
			modal.dialogArguments = args; 		
		} 
		catch ( err ) {
			modal_alert("Helaas, het venster kon niet worden geopend");
			if (typeof cmaLog !== 'undefined') cmaLog.error('[Wizard] Failed to open modal:', err.message);
		}
	} 
}

function IsDigit(){
	if (document.all) {
		parent.WizardButtonPressed( event.keyCode );
		// allow only 0-9 and bs and delete and %
		return ((event.keyCode >= 48 && event.keyCode <= 57) || (event.keyCode >= 96 && event.keyCode <= 105) || event.keyCode==9 || event.keyCode==8 || event.keyCode==46 || event.keyCode==37 ) 
	} else {
		// coward: quick fix..
		return true;
	}
}