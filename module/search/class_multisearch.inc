<?php
use App\Library\Application;
use App\Library\Database;
use App\Library\Request;
use App\Library\SQL;
use App\Library\Server;
use App\Library\Arr;

// Multisearch enables searching through more than 1 table and presenting it in a uniform style to the user where
// an item has been found, styling of the results is done in the .CSS of the project
// Per default a form field called search_for is picked up
// dim SearchIt
// set SearchIt = new MultiSearch
// SearchIt.AddSearchItem "Pagina", "SELECT ID, PaginaTitel from tblModules where blnActive=True", "paginaTitel,paginaHTML","ID", "paginaTitel", "./?pageID="
// SearchIt.AddSearchItem "Nieuws", "SELECT ID, nwsTitle from tblNews ORDER BY nwsDateStamp", "nwsIntro,nwsTitle,nwsContent", "ID", "nwsTitle", "./?store=nieuws_detail&pageID=147&id="
// SearchIt.Go()
// Addition in june of 2007: if a group results in 1 result: show otherwise not
if (class_exists('MultiSearch')) { return; }
class MultiSearch
{
    public $mColSearches;
    private $parSearchFor;
    /**
    * Addsearchitem
    *
    */
    public function AddSearchItem($parTitle, $parQuery, $parConn, $parFields, $parIDFieldName, $parTitleFieldname, $parBaseURL)
    {
        $MySearch = null;
        $MySearch = new SearchItem();
        $MySearch->Title = $parTitle;
        $MySearch->Query = $parQuery;
        $MySearch->Conn = $parConn;
        $MySearch->Fields = $parFields;
        $MySearch->IDField = $parIDFieldName;
        $MySearch->TitleField = $parTitleFieldname;
        $MySearch->BaseURL = $parBaseURL;
        // addition june 2007
        $MySearch->intMaxtoShow = 9999;
        // Add Search criteria to base query
        $MySearch->QueryComplete = SQL::complicatedWhere($MySearch->Query, $this->parSearchFor, $MySearch->Fields, CONST_LIB_SQL_SEARCH_TEXT);
        $this->mColSearches->Add($this->mColSearches->count, $MySearch);
        $MySearch = null;
    }
    /**
    * Showresults
    */
    public function ShowResults()
    {
        // Store search in database for later searching!
        lib_SearchSave($this->parSearchFor);
    }
    /**
    * Go
    *
    */
    public function Go($strSearchForField, $strNotFound)
    {
        $Search = null;
        $blnResults = null;
        if ($this->parSearchFor != '') {
            echo '<TABLE class=searchtable cellspacing=0>';
            foreach ($this->mColSearches->Items() as $Search) {
                $blnResults = $blnResults || $Search->Go();
            }
            if (!$blnResults) {
                echo '<tr><td>' . $strNotFound . ' </td></tr>';
            }
            echo '</TABLE>';
        } else {
            echo '<form method=get style=margin:0px action="' . Application::get('base_path', '') . 'index.php">';
            echo '<input type=hidden name=pageAction value=zoek>';
            echo '<table class=searchtable cellpadding=0 cellspacing=0>';
            echo '<tr><td>' . $strSearchForField . '&nbsp;</td><td><input type=text name=pagesearchfor maxlength=128></td></tr>';
            echo '<tr><td colspan=2><input class=button type=Submit value="  Ok  "></td></tr>';
            echo '</table></form>';
        }
    }
    /**
    *   Construct
    */
    public function __construct()
    {
        $this->mColSearches = new ArrayObject();
        $this->parSearchFor = Request::query('search_for', '');
        if ($this->parSearchFor == '') {
            $this->parSearchFor = Request::query('pageSearchFor', '');
        }
        // XSS prevention
        $this->parSearchFor = lib_HTML_RemoveScripts($this->parSearchFor);
    }
    /**
    *   Destruct
    */
    public function __destruct()
    {
        if (is_object($this->mColSearches)) {
            $this->mColSearches = null;
        }
    }
}
if (class_exists('SearchItem')) { return; }
class SearchItem
{
    public $Conn;
    public $Query;
    public $Fields;
    public $Title;
    public $IDField;
    public $TitleField;
    public $BaseURL;
    public $QueryComplete;
    public $intMaxtoShow;
    /**
    *   Construct
    */
    public function __construct() {
        // base query containing all data that can be potentially found
        // comma delimited list of fields to search in
        // Returns if any results were found
    }
    /**
    * Go
    *
    */
    public function Go()
    {
        $bRetval = null;
        $bRetval = false;
        // echo '<hr>' . $QueryComplete . ' <HR>'
        $rs = null;
        Database::openRS($rs, $this->QueryComplete, $this->Conn, adOpenKeyset);
        if ($rs === null) {
            throw new \Exception('Database query failed: ' . Database::getLastError());
        }
        $rs->MoveNext();
        if (!$rs->EOF) {
            if (!$rs->recordCount > $this->intMaxtoShow) {
                $bRetval = true;
                echo '<tr><td colspan=5 class=searchtitle>' . Server::htmlEncode($this->Title) . '</td></tr>';
            }
        }
        if ($bRetval) {
            while (!$rs->EOF) {
                echo '<tr valign=top><td>-</td><td><A class=searchlink href=' . $this->BaseURL . $rs_current_row[$this->IDField] . '>' . Server::htmlEncode(Arr::field($rs_current_row, $this->TitleField) . '') . '</A></td></tr>';
                $rs->MoveNext();
            }
        }
        $rs = null;
        return $bRetval;
    }
}
?>
