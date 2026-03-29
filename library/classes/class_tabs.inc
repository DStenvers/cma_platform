<?php
use App\Library\Request;
use App\Library\Str;

if (class_exists('LibTabs')) { return; }
class LibTabs
{
    private $TabCookieID;
    private $TabPlaceHolder;
    private $TabStripContent;
    public $cookieID;
    /**
    * Addtab
    *
    */
    public function AddTab($sID = '', $sCaption = '', $sAltText = '', $sCustomFunc = '')
    {
        $TabStripContent = $TabStripContent . '<li id=tab_item_' . $sID . '><a href=# class=tablink onclick="TabChangeNavMenu(' . $sID . ')';
        if ($sCustomFunc != '') {
            $TabStripContent = $TabStripContent . ';' . str_replace('[ID]', $sID, $sCustomFunc);
        }
        $TabStripContent = $TabStripContent . '" title="' . $sAltText . '">' . $sCaption . '</a></li>';
    }
    /**
    * Render
    *
    */
    public function Render()
    {
        // Use instance cookieID if set, otherwise use global $cookieID = $this->cookieID ?? $TabCookieID;
        echo '<script>';
        echo 'var activetab=-1;';
        echo 'function TabSet(id, value){';
        echo "sID='tab_item_'+id.toString();";
        echo 'chk=document.getElementById(sID);';
        echo 'if (chk) {';
        echo "chk.className=(value=='uit')?'':'selected';";
        echo "if (value=='uit') { $('#form'+activetab.toString()).hide() } else { $('#form'+activetab.toString()).show()}";
        echo '}';
        echo '}';
        echo 'function TabChangeNavMenu(id){';
        echo "if (activetab!=-1) TabSet(activetab,'uit');";
        echo 'activetab=id;';
        echo "TabSet(activetab,'aan');";
        echo "lib_createCookie('" . $cookieID . "',id.toString());";
        echo '}';
        // response.write "function tr_hi(elt,hilight){elt.style.backgroundColor=hilight?'#EBEAD8':'';}"
        echo 'document.getElementById(' . Str::JscriptSafe($TabPlaceHolder) . ').innerHTML=' . Str::JscriptSafe('<ul id="glow_tabs" onselectstart="return(false)">' . $TabStripContent . '</ul>') . ';';
        echo "var sCookie=lib_readCookie('" . $cookieID . "');";
        echo 'if (sCookie){TabChangeNavMenu(sCookie)} else {TabChangeNavMenu(0)}';
        echo '</script>';
    }
    /**
    *   Construct
    */
    public function __construct()
    {
        $TabPlaceHolder = 'LibTabPlaceHolder';
        // take the default filespecification exclusing parameters as the base ID for the cookie storing the active Tab
        $TabCookieID = (is_null('TABS_' . str_replace('/', '_', Request::server('PHP_SELF', ''))) ? "" : strtoupper('TABS_' . str_replace('/', '_', Request::server('PHP_SELF', ''))));
        $TabStripContent = '';
        // ------------------------------------------------------------------------------------------------------------
        // External requirements
        // - library.js should be included
        // ------------------------------------------------------------------------------------------------------------
        // Public Properties
        // ------------------------------------------------------------------------------------------------------------
        // Public Functions
        // ------------------------------------------------------------------------------------------------------------
        // Private Properties
        // ------------------------------------------------------------------------------------------------------------
        // Private Functions
        // ------------------------------------------------------------------------------------------------------------
        // Class functions
    }
    /**
    *   Destruct
    */
    public function __destruct()
    {
        // Cleanup (if any)
    }
}
?>
