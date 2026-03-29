<?php
// TODO: make optional
use App\Library\Application;
use App\Library\Cache;
use App\Library\Cookie;
use App\Library\Database;
use App\Library\Debug;
use App\Library\Request;
use App\Library\SQL;
use App\Library\Server;
use App\Library\Str;
use App\Library\Arr;

$calendar_blnShowEventsInCalendar = null;
// Requires lib_datum.inc
// TODO: : allow change of CSS classes used
// For Clickthrough, implement the onclick and use the DATE parameter to retrieve the selected data
$TypeWeergave = Cookie::get(COOKIE_WEERGAVETYPE, '');
define("TYPE_ALLES", '0');
define("TYPE_ROOSTER", '1');
define("TYPE_GESPREKKEN", '2');
$TOON_EVALUATIES_DEELNEMER = $blnTestSite;
$TOON_EVALUATIES_ASSISTENT = true;
$TOON_EVALUATIES_DOCENT = $blnTestSite;
if (class_exists('Calendar')) { return; }
class Calendar
{
    public $Width;
    public $Height;
    public $OnDayClick;
    public $OnNextMonthClick;
    public $OnPrevMonthClick;
    public $ShowDateSelect;
    public $ShowPrevNextMonth;
    public $strDays;
    public $mcolDays;
    private $mdDate;
    private $msToday;
    private $mnDay;
    private $mnMonth;
    private $mnYear;
    private $mnDayMonthStarts;
    private $mnDaysInMonth;
    private $mbDaysInitialized;
    /**
    * Setdate
    *
    */
    public function SetDate($dDate)
    {
        $this->mddate = (function($value) {
            if (is_null($value) || $value === "") throw new Exception("Type mismatch");
            $timestamp = strtotime($value);
            if ($timestamp === false) throw new Exception("Type mismatch");
            return date("Y-m-d H:i:s", $timestamp);
        })($dDate);
        $this->mnday = date("j", strtotime($dDate));
        $this->mnmonth = date("n", strtotime($dDate));
        $this->mnyear = date("Y", strtotime($dDate));
        /* ERROR: Could not convert statement */
        /* ERROR: Could not convert statement */
        /* ERROR: Could not convert statement */
        /* ERROR: Could not convert statement */
        /* ERROR: Could not convert statement */
        /* ERROR: Could not convert statement */
        /* ERROR: Could not convert statement */
        /* ERROR: Could not convert statement */
        $this->mndaysinmonth = date("j", strtotime(dateAdd('d', -1, date("Y-m-d H:i:s", mktime(0, 0, 0, null, 1, $this->mnyear)))));
        $this->mndaymonthstarts = (date("w") + 1);
    }
    /**
    * Rendercalendar
    */
    public function RenderCalendar()
    {
        $nDayCount = null;
        $nCellWidth = null;
        $objDay = null;
        $arrDaysdescr = array();
        $nTDWidth = null;
        $t = null;
        $strDay = "";
        if (!$this->mbDaysInitialized) {
            $this->InitDays();
        }
        $this->Send('<TABLE class=CalenderTable data-date=' . $this->mddate . ' cellspacing=0 cellpadding=3>');
        $this->Send('<TR><TD class=CalenderTDTitle colspan=7>');
        if ($this->ShowPrevNextMonth) {
            $this->Send('<A id=prev_month href="' . $this->OnPrevMonthClick . '"><span class="calendar_back"></span></a>');
        }
        // iif(mnYear=year(date()), ""," " &)
        $this->Send('<span>' . (is_null(Date::longMonth($this->mnmonth)) ? "" : strtolower((Date::longMonth($this->mnmonth)) ?? '')) . ' ' . $this->mnyear . '</span>');
        if ($this->ShowPrevNextMonth) {
            $this->Send('<A id=next_month href="' . $this->OnNextMonthClick . '"><span class="calendar_fwd"></span></a>');
        }
        $this->Send('</TD></TR><TR>');
        $arrDaysdescr = explode(',', $this->strDays);
        for ($t = 0; $t <= 6; $t++) {
            $this->Send('<TD class=CalenderTDDays width=' . (($this->Width / 7) >= 0 ? floor($this->Width / 7) : ceil($this->Width / 7)) . '>' . $arrDaysdescr[$t] . '</TD>');
        }
        $this->Send('</TR><TR align=center>');
        for ($nDayCount = 0; $nDayCount <= $this->mndaymonthstarts - 2; $nDayCount++) {
            $this->Send('<TD class=CalenderTDUnused>&nbsp;</TD>');
        }
        foreach ($this->mcolDays->Items() as $objDay) {
            if ($nDayCount == 7) {
                $this->Send('</TR><TR align=center>');
                $nDayCount = 0;
            }
            $this->Send('<TD');
            if ($objDay->DateString == $this->msToday) {
                $this->Send(' class=CalenderTDToday');
            } else {
                if ($objDay->HaveActivities()) {
                    $this->Send(' class=CalenderTDApp');
                } else {
                    $this->Send(' class=CalenderTD');
                }
                if ($objDay->TheDate < date("Y-m-d")) {
                    $this->Send('Past');
                }
            }
            $this->Send('>' . $objDay->GiveLink());
            $objDay->Draw();
            $this->Send('</A></TD>');
            /* ERROR: Could not convert statement */
            $nDayCount = null;
        }
        if ($nDayCount < 7) {
            for ($nDayCount = $nDayCount; $nDayCount <= 6; $nDayCount++) {
                $this->Send('<TD class=CalenderTDUnused>&nbsp;</TD>');
            }
        }
        $this->Send('</TR>');
        if ($this->ShowDateSelect) {
            $this->Send('<TR><TD colspan=7 align=center>');
            $this->DrawDateSelect();
            $this->Send('</TD></TR>');
        }
        $this->Send('</TABLE>');
    }
    /**
    * Drawdateselect
    */
    public function DrawDateSelect()
    {
        $nIndex = null;
        $this->Send('	<form id=frmGO name=frmGO><table><tr><td><select name=month>');
        for ($nIndex = 1; $nIndex <= 12; $nIndex++) {
            echo '<option value="' . $nIndex . '"' . ($nIndex == date("n", strtotime($this->mddate)) ? ' selected' : '') . '>' . date((true ? "M" : "F"), mktime(0, 0, 0, $nIndex, 1)) . '</option>';
        }
        $this->Send('	</select></td>');
        $this->Send('	<td><select name=year>');
        /* ERROR: Could not convert statement */
        for ($nIndex = date("Y", strtotime(date("Y-m-d H:i:s"))) - 4; $nIndex <= null; $nIndex++) {
            echo '<option value="' . $nIndex . '"' . ($nIndex == date("Y", strtotime($this->mddate)) ? ' selected' : '') . '>' . strval($nIndex) . '</option>';
        }
        $this->Send('	</select></td>');
        $this->Send('	<td><input type=Button Value=Go onclick="document.location=\'' . Request::getOriginalScript() . "?date='+this.form.month.options[this.form.month.selectedIndex]+'/1/'+this.form.year.options[this.form.year.selectedIndex];\"></td>");
        $this->Send('	</form></tr></table>');
    }
    /**
    * Send
    *
    */
    public function Send($sHTML)
    {
        if (Application::get('local', '')) {
            echo $sHTML . PHP_EOL;
        } else {
            echo $sHTML;
        }
    }
    /**
    * Initdays
    */
    public function InitDays()
    {
        $nDayIndex = null;
        $objNewDay = null;
        if ($this->mcolDays->Count > 0) {
            $this->mcolDays->RemoveAll();
        }
        for ($nDayIndex = 1; $nDayIndex <= $this->mndaysinmonth; $nDayIndex++) {
            $objNewDay = new CalendarDay();
            $objNewDay->TheDate = date("Y-m-d H:i:s", mktime(0, 0, 0, $this->mnmonth, $nDayIndex, $this->mnyear));
            $objNewDay->DateString = date("Y-m-d H:i:s", strtotime($objNewDay->TheDate()));
            $objNewDay->OnClick = $this->OnDayClick;
            $this->mcolDays->Add($nDayIndex, $objNewDay);
        }
        $this->mbdaysinitialized = true;
    }
    /**
    *   Construct
    */
    public function __construct()
    {
        $sURL = null;
        $this->width = 160;
        $this->height = 115;
        $this->showdateselect = false;
        $this->mstoday = date("Y-m-d H:i:s", strtotime(date("Y-m-d H:i:s", mktime(0, 0, 0, date("n", strtotime(date("Y-m-d H:i:s"))), date("j", strtotime(date("Y-m-d H:i:s"))), date("Y", strtotime(date("Y-m-d H:i:s")))))));
        $this->showprevnextmonth = true;
        if (Application::get('Mod_language', '') == 'UK') {
            $this->strdays = 'Mo,Tu,We,Th,Fr,Sa,Su';
        } else {
            $this->strdays = 'Ma,Di,Wo,Do,Vr,Za,Zo';
        }
        $calendar_blnShowEventsInCalendar = false;
        $this->mcoldays = new ArrayObject();
        if (Request::query('date', '') != '') {
            $nUnderScorePos = null;
            $strDatum = "";
            $strDatum = Request::query('date', '');
            $nUnderScorePos = (($_pos = stripos($strDatum ?? '', '_', max(0, 1 - 1))) !== false ? $_pos + 1 : 0);
            if ($nUnderScorePos > 0) {
                $strDatum = substr($strDatum, 0, max(0, min($nUnderScorePos - 1, strlen($strDatum ?? ''))));
            }
            $this->SetDate($strDatum);
        } else {
            $this->SetDate(date("Y-m-d H:i:s"));
        }
        $sURL = 'agenda.php';
        // Request.ServerVariables("SCRIPT_NAME")
        $this->ondayclick = $sURL;
        /* ERROR: Could not convert statement */
        /* ERROR: Could not convert statement */
        $this->onnextmonthclick = $sURL . '?date=' . date("Y-m-d H:i:s", mktime(0, 0, 0, null, 1, $this->mnyear));
        $this->onprevmonthclick = $sURL . '?date=' . date("Y-m-d H:i:s", mktime(0, 0, 0, $this->mnmonth - 1, 1, $this->mnyear));
        $this->mbdaysinitialized = false;
        // comma separated list of days descriptions
    }
    /**
    *   Destruct
    */
    public function __destruct()
    {
        if (is_object($this->mcoldays)) {
            $this->mcoldays = null;
        }
    }
}
if (class_exists('CalendarActivity')) { return; }
class CalendarActivity
{
    public $Text;
    public $Time;
    public $TimeTot;
    public $Locatie;
    public $LocatieOnline;
    public $LocatieOnlineMeetingID;
    public $LocatieOnlineMeetingPassword;
    public $Docenten;
    public $Praktijkopleider;
    public $Downloads;
    public $Draaiboek;
    public $DraaiboekPDF;
    public $OplID;
    public $Route;
    public $DocentTxt;
    public $isJaargroepopleider;
    public $isDocent;
    public $isHoofdDocent;
    public $ExportType;
    public $ID;
    public $showDownloads;
    public $GesprekID;
    public $TypeGesprekID;
    public $AfspraakId;
    public $UploadAllowed;
    public $UploadDescr;
    public $ConceptDocument;
    public $ConceptGeplaatst;
    public $EvaluatieTonen;
    public $EvaluatieUrl;
    public $isReservering;
    public $Opmerking;
    public $Printed;
    /**
    *   Construct
    */
    public function __construct()
    {
        $this->docenttxt = 'Docent(en)';
        $this->afspraakid = '';
        $this->gesprekid = '';
        $this->isreservering = false;
        // voor gesprekken
        // ID van draaiboek (voor compatibility purposes)
        // used for vCalendar Links
        // voor gesprekken
        // voor het wijzigen van een gesprek
        // voor het bepalen van rechten van wijzigen
        // zodat het vervolgschermen weten dat het een afspraak betreft
        // voor plaatsen concept werkplan
    }
}
if (class_exists('CalendarDay')) { return; }
class CalendarDay
{
    public $DateString;
    public $OnClick;
    public $TheDate;
    public $mcolActivities;
    private $mbActivitiesInit;
    /**
    * Addactivity
    *
    */
    public function AddActivity($sActivity, $dTime, $dTimeTot, $sLocatie, $sLocatieOnline, $sLocatieOnlineMeetingId, $sLocatieOnlineMeetingPassword, $sDocenten, $sDownloads, $sRoute)
    {
        $objActivity = null;
        $nNewPos = null;
        $nPosition = null;
        $actObj = null;
        if (!$this->mbActivitiesInit) {
            $this->InitActivities();
        }
        $objActivity = new CalendarActivity();
        $objActivity->Text = $sActivity;
        $objActivity->Time = $dTime;
        $objActivity->TimeTot = $dTimeTot;
        $objActivity->Locatie = $sLocatie;
        $objActivity->LocatieOnline = $sLocatieOnline;
        $objActivity->LocatieOnlineMeetingID = $sLocatieOnlineMeetingId;
        $objActivity->LocatieOnlineMeetingPassword = $sLocatieOnlineMeetingPassword;
        $objActivity->Docenten = $sDocenten;
        $objActivity->Downloads = $sDownloads;
        $objActivity->Route = $sRoute;
        $objActivity->Printed = false;
        $objActivity->OplID = 0;
        // nNewPos = mcolActivities.Count + 1
        // nPosition = 0
        // for each ActObj in mcolActivities.items
        // if ActObj.Time>dTime then
        // nNewPos = min( nNewPos, nPosition )
        // end if
        // nPosition = nPosition + 1
        // next
        /* ERROR: Could not convert statement */
        $this->mcolActivities->Add(null, $objActivity);
        // mcolActivities.Add nNewPos + 1, objActivity
        $objActivity = null;
        // echo 'Activity added at position '  & nNewPos + 1
        $AddActivity = $this->mcolActivities->Item($this->mcolActivities->Count());
        // Set AddActivity = mcolActivities.Item(nNewPos+1)
    }
    /**
    * Givelink
    *
    */
    public function GiveLink()
    {
        $sTitle = null;
        $objActivity = null;
        $sLink = null;
        if ($this->mbActivitiesInit) {
            $sTitle = '<table>';
            foreach ($this->mcolActivities->Items() as $objActivity) {
                $sTitle = ($sTitle == '' ? '' : $sTitle . PHP_EOL) . '<tr valign=top><td>' . $objActivity->Text . '</td><td>' . Date::time($objActivity->Time()) . '&nbsp;-&nbsp;' . Date::time($objActivity->TimeTot()) . '</td><td>' . str_replace('RINO amsterdam', 'RINO&nbsp;amsterdam', $objActivity->Locatie . '') . '</td></tr>';
            }
            if (substr($sTitle, 0, 4) == '<BR>') {
                $sTitle = substr($sTitle, max(0, 5 - 1));
            }
            $sTitle = str_replace('(-)', '', $sTitle);
            $sTitle = $sTitle . '</table>';
            $sLink = $sLink . '<a class=agenda_link title="' . str_replace(chr(34), '', $sTitle) . chr(34) . ' href="';
            if ((($_pos = stripos($this->OnClick ?? '', 'javascript', max(0, 1 - 1))) !== false ? $_pos + 1 : 0)) {
                $sLink = $sLink . str_replace('DATE', $this->DateString, $this->OnClick);
            } else {
                $sLink = $sLink . str_replace('DATE', $this->DateString, $this->OnClick);
            }
            return $sLink . chr(34) . '>';
        } else {
            return '';
        }
    }
    /**
    * Draw
    */
    public function Draw()
    {
        $objActivity = null;
        $blnHaveLink = null;
        if ($calendar_blnShowEventsInCalendar) {
            $this->Send('<TABLE width=100% cellspacing=2 cellpadding=1><TR><TD align=center valign=top>');
        }
        $this->Send(date("j", strtotime($this->DateString)));
        if ($calendar_blnShowEventsInCalendar) {
            $this->Send('</TD></TR>');
        }
        if ($this->mbActivitiesInit) {
            if ($calendar_blnShowEventsInCalendar) {
                foreach ($this->mcolActivities->Items() as $objActivity) {
                    $this->Send('<TR><TD>' . $objActivity->Text . '</TD></TR>');
                }
            }
        }
        if ($calendar_blnShowEventsInCalendar) {
            $this->Send('</TABLE>');
        }
    }
    /**
    * Haveactivities
    */
    public function HaveActivities()
    {
        return $this->mbActivitiesInit && $this->mcolActivities->Count() > 0;
    }
    /**
    * Initactivities
    */
    public function InitActivities()
    {
        $this->mcolactivities = new ArrayObject();
        $this->mbactivitiesinit = true;
    }
    /**
    * Send
    *
    */
    public function Send($sHTML)
    {
        if (Application::get('local', '')) {
            echo $sHTML . PHP_EOL;
        } else {
            echo $sHTML;
        }
    }
    /**
    *   Construct
    */
    public function __construct()
    {
        $this->mbactivitiesinit = false;
    }
    /**
    *   Destruct
    */
    public function __destruct()
    {
        if (is_object($this->mcolactivities)) {
            $this->mcolactivities->RemoveAll();
            $this->mcolactivities = null;
        }
    }
}
// Opbouwen en vullen van de calender class
/**
* Create Calendar
*
*/
function Create_Calendar($datum, $blnNavigation, $blnAlles)
{
    $LoginType = $LoginType ?? '';
    global $AfspraakRS, $AfspraakSQL, $DocentID, $LoginType, $TypeWeergave, $UserAlleOpleidingen, $UserOpleidingen, $arrAfspraakDownloads, $deelnemerID, $downloadsSQL, $tel, $userPraktijkopleiders, $CalendarRS, $CalendarSQL, $CalendarSQL_current_row, $DocentenRS, $DocentenSQL, $DownloadRS, $DownloadSQL, $MyCalendar, $actObj, $blnActivityIsJaargroepopleider, $blnActivityIsVanDocent, $gesprekRS, $gesprekRS_current_row, $gesprekSQL, $intInhalerBy, $objFSO, $sDocenten, $sDownloads, $sLocatie, $sTitel;
    $CalendarRS = null;
    $DocentenRS = null;
    $DownloadRS = null;
    $sDocenten = null;
    $sDownloads = null;
    $blnActivityIsVanDocent = null;
    $blnShow = null;
    $gesprekRS = null;
    $objFSO = null /* FileSystemObject - Use native PHP: file_exists(), is_dir(), fopen(), etc. */;
    // Create the calendar
    $MyCalendar = new Calendar();
    $MyCalendar->SetDate($datum);
    $MyCalendar->ShowPrevNextMonth = $blnNavigation;
    $MyCalendar->OnDayClick = "javascript:setCalendarDay('DATE')";
    if ($UserOpleidingen === '') {
        $UserOpleidingen = $UserAlleOpleidingen;
    }
    $toonOpleidingen = $UserOpleidingen;
    if ($toonOpleidingen == $UserAlleOpleidingen) {
        if ($LoginType == USER_TYPE_ASSISTENT) {
            $toonOpleidingen = GetAssistentOpleidingIDs();
        }
    }
    if ($toonOpleidingen !== '' && $TypeWeergave != TYPE_GESPREKKEN) {
        if ($LoginType == USER_TYPE_DEELNEMER) {
            $intInhalerBy = AgendaGetRoosterInhalerIds($deelnemerID);
        }
        $CalendarRS = null;
        $CalendarSQL = ' SELECT distinct tblDraaiboek.Download AS DraaiboekPDF, tblOpleidingen.Routebeschrijving, tblAgenda.ID AS AgendaID, tblAgenda.Omschrijving, tblAgenda.Datum, tblAgenda.Tijd, tblAgenda.EindTijd, tblAgenda.guid, tblAgenda.locatieadres, tblAgenda.ZoomMeetingID, tblAgenda.Zoompassword, tblAgenda.rinozaal, tblAgenda.locatieplaats,  tblopleidingen.fkEvaluatieTemplate,tblAgenda.Locatie, Count(tblAgendaDownloads.ID) AS Downloads, Count(tblDocentenPerAgendapunt.Id) AS Docenten, tblAgenda.fkOpleiding, tblAgenda.fkDraaiboek, tblAgenda.DraaiboekPDF as RoosterDraaiboekPDF, tblAgenda.bEvaluatieDeelnemer, tblAgenda.fkEvaluatieDocumentIncidenteel, tblAgenda.fkEindEvaluatie, tblAgenda.bBlokkeerEindevaluatie  FROM tblDraaiboek RIGHT JOIN (tblOpleidingen INNER JOIN ((tblAgenda LEFT JOIN tblAgendaDownloads ON tblAgenda.ID = tblAgendaDownloads.fkAgenda) LEFT JOIN tblDocentenPerAgendapunt ON tblAgenda.ID = tblDocentenPerAgendapunt.fkAgenda) ON tblOpleidingen.ID = tblAgenda.fkOpleiding) ON tblDraaiboek.ID = tblAgenda.fkDraaiboek  GROUP BY tblAgenda.bActief, tblDraaiboek.Download, tblOpleidingen.Routebeschrijving, tblAgenda.ID, tblAgenda.Omschrijving, tblAgenda.Datum, tblAgenda.Tijd, tblAgenda.EindTijd, tblAgenda.guid, tblAgenda.locatieadres, tblAgenda.ZoomMeetingID, tblAgenda.Zoompassword, tblAgenda.rinozaal, tblAgenda.locatieplaats, tblopleidingen.fkEvaluatieTemplate, tblAgenda.Locatie, tblAgenda.fkOpleiding, tblAgenda.fkDraaiboek, tblAgenda.DraaiboekPDF, tblAgenda.bEvaluatieDeelnemer, tblAgenda.fkEvaluatieDocumentIncidenteel, tblAgenda.fkEindEvaluatie, tblAgenda.bBlokkeerEindevaluatie,tblDocentenPerAgendapunt.fkDocent  HAVING ' . ($toonOpleidingen == $UserAlleOpleidingen && $LoginType == USER_TYPE_DOCENT ? 'tblDocentenPerAgendapunt.fkDocent = ' . SQL::postNumber($DocentID) . ' and ' : '') . ' tblAgenda.bActief=true and (' . ($intInhalerBy !== '' ? 'tblagenda.id in (' . $intInhalerBy . ') or ' : '') . ((($_pos = stripos($toonOpleidingen ?? '', ',', max(0, 1 - 1))) !== false ? $_pos + 1 : 0) > 0 ? 'tblAgenda.fkOpleiding in (' . $toonOpleidingen . ')' : 'tblAgenda.fkOpleiding=' . $toonOpleidingen) . ') AND month(Datum)=' . date("n", strtotime($datum)) . ' AND year(Datum)=' . date("Y", strtotime($datum)) . '  ORDER BY tblAgenda.Datum, tblAgenda.Tijd';
        Database::openRS($CalendarRS, $CalendarSQL, 'data', adOpenForwardOnly);
        if ($CalendarRS === null) {
            throw new \Exception('Database query failed: ' . Database::getLastError());
        }
        $CalendarRS->MoveNext();
        $DocentenRS = null;
        while (!$CalendarRS->EOF()) {
            $sDocenten = '';
            $blnActivityIsVanDocent = false;
            $blnActivityIsHoofdDocent = Login_IsHoofdDocent(Arr::field($CalendarRS_current_row, 'fkOpleiding'));
            $blnActivityIsJaargroepopleider = Login_IsJaargroepopleider(Arr::field($CalendarRS_current_row, 'fkOpleiding'));
            if ($CalendarRS['Docenten'] > 0) {
                $DocentenSQL = ' SELECT tblDocentenPerAgendapunt.fkAgenda, tblDocenten.VolledigeNaam, tblDocenten.ID as DocentID  FROM tblDocenten INNER JOIN tblDocentenPerAgendapunt ON tblDocenten.ID = tblDocentenPerAgendapunt.fkDocent  WHERE tblDocentenPerAgendapunt.fkAgenda=' . $CalendarRS['AgendaID'] . (GetOplisBIG(Arr::field($CalendarRS_current_row, 'fkOpleiding') . '') ? ' and bVoorlopig=False ' : '') . ' ORDER BY tblDocenten.Achternaam ';
                Database::openRS($DocentenRS, $DocentenSQL, 'data', adOpenForwardOnly);
                if ($DocentenRS === null) {
                    throw new \Exception('Database query failed: ' . Database::getLastError());
                }
                $DocentenRS->MoveNext();
                while (!$DocentenRS->EOF()) {
                    $sDocenten .= Arr::field($DocentenRS_current_row, 'VolledigeNaam');
                    if (strval(Arr::field($DocentenRS_current_row, 'DocentID')) == strval($DocentID) && $LoginType == USER_TYPE_DOCENT) {
                        $blnActivityIsVanDocent = true;
                    }
                    if (!$DocentenRS->EOF) {
                        $sDocenten .= ', ';
                    }
                }
            }
            if ($CalendarRS['Downloads'] > 0) {
                $DownloadSQL = 'SELECT tblAgendaDownloads.ID, tblAgendaDownloads.fkAgenda, tblAgendaDownloads.Bestand, tblAgendaDownloads.Titel, tblAgendaDownloads.CommentaarHoofdOpleider FROM tblAgenda INNER JOIN tblAgendaDownloads ON tblAgenda.ID = tblAgendaDownloads.fkAgenda WHERE (tblAgenda.ID=' . $CalendarRS['AgendaID'] . ') ORDER BY tblAgendaDownloads.sortorder, tblAgendaDownloads.Titel ';
                $DownloadRS = Database::openRS($DownloadSQL, 'data', ADOPENREADONLY);
                if ($DownloadRS === null) {
                    throw new \Exception('Database query failed: ' . Database::getLastError());
                }
                $DownloadSQL->MoveNext();
                $sDownloads = '<table cellpadding=0 cellspacing=4 style="width:100%;margin-left:-4px">';
                while (!$DownloadRS->EOF()) {
                    $sDownloads .= '<tr><td style=width:95%;padding-right:12px><A href="downloads/rooster/' . $DownloadRS['Bestand'] . '" target=_blank>' . (Arr::field($DownloadRS_current_row, 'Titel') == '-' || Arr::field($DownloadRS_current_row, 'Titel') == '' ? FileNameDescription($objFSO, $DownloadRS['Bestand']) : Arr::field($DownloadRS_current_row, 'Titel')) . '</a></td>';
                    if ($blnActivityIsVanDocent || $blnActivityIsJaargroepopleider) {
                        $sDownloads .= '<td class=dl_knop>' . ButtonBar_ButtonText('Verwijder', '<a href="javascript:agenda_dl_verw(' . Arr::field($DownloadRS_current_row, 'fkAgenda') . ',' . Arr::field($DownloadRS_current_row, 'ID') . ')">') . '</td><td class=dl_knop>' . (Arr::field($DownloadRS_current_row, 'CommentaarHoofdOpleider') != '' ? ButtonBar_ButtonText('Toon opmerking', '<a href=# title="Toon opmerking van de hoofd-/jaargroepopleider" onclick="javascript:opmerking(' . $DownloadRS['ID'] . ',false)">') : '') . '</td>';
                    }
                    if ($blnActivityIsJaargroepopleider) {
                        $sDownloads .= '<td class=dl_knop>' . ButtonBar_ButtonText('Plaats opmerking', '<a title="Plaats opmerking voor docent" href="javascript:opmerking(' . $DownloadRS['ID'] . ',true)">') . '</td>';
                    }
                    $sDownloads .= '</tr>';
                }
                $sDownloads .= '</table>';
            }
            $strOms = Str::trim($CalendarRS['Omschrijving'] ?? '') . '&nbsp;';
            if ((($_pos = stripos($toonOpleidingen ?? '', ',', max(0, 1 - 1))) !== false ? $_pos + 1 : 0) > 0) {
                $strOms = GetOplCode($CalendarRS['fkOpleiding']) . ' ' . $strOms;
            }
            if ($LoginType == USER_TYPE_DOCENT) {
                $blnShow = $blnShow && $blnActivityIsVanDocent || $blnActivityIsHoofdDocent || $blnActivityIsJaargroepopleider;
            }
            if ($blnShow) {
                $actObj = $MyCalendar->Days.AddActivity($strOms, $CalendarRS['Tijd'], $CalendarRS['EindTijd'], $CalendarRS['Locatie'] . ', ' . $CalendarRS['locatieadres'] . '  ' . $CalendarRS['locatieplaats'] . ($CalendarRS['Rinozaal']!= '' ? ' (' . $CalendarRS['Rinozaal'] . ')' : ''), ' ', $CalendarRS['ZoomMeetingID'], $CalendarRS['Zoompassword'], $sDocenten, $sDownloads, $CalendarRS['Routebeschrijving'] . '');
                $actObj->isDocent = $blnActivityIsVanDocent;
                $actObj->isHoofddocent = $blnActivityIsHoofdDocent;
                $actObj->isJaargroepopleider = $blnActivityIsJaargroepopleider;
                $actObj->ExportType = 1;
                $actObj->ID = $CalendarRS['AgendaID'];
                $actObj->Draaiboek = $CalendarRS['fkDraaiboek'];
                $actObj->DraaiboekPDF = (Arr::field($CalendarRS_current_row, 'RoosterDraaiboekPDF') == "" ? $CalendarRS['DraaiboekPDF'] : Arr::field($CalendarRS_current_row, 'RoosterDraaiboekPDF'));
                $actObj->oplID = $CalendarRS['fkOpleiding'];
                $actObj->EvaluatieTonen = $CalendarRS['fkEvaluatieTemplate'] != '' && $TOON_EVALUATIES_DOCENT && $LoginType == USER_TYPE_DOCENT || $TOON_EVALUATIES_DEELNEMER && $LoginType == USER_TYPE_DEELNEMER || $TOON_EVALUATIES_ASSISTENT && $LoginType == USER_TYPE_ASSISTENT;
                $actObj->EvaluatieTonen = $actObj->EvaluatieTonen && $CalendarRS['datum'] <= date("Y-m-d") && $CalendarRS['bEvaluatieDeelnemer'] || $CalendarRS['fkEvaluatieDocumentIncidenteel'] || $CalendarRS['fkEindEvaluatie'] && !$CalendarRS['bBlokkeerEindevaluatie'];
                if ($actObj->EvaluatieTonen) {
                    if ($LoginType == USER_TYPE_ASSISTENT) {
                        $actObj->EvaluatieUrl = Application::get('base_path', '') . 'eval_resultaten.php?popup=Y&guid=' . $CalendarRS['guid'];
                    } else {
                        if ($LoginType == USER_TYPE_DEELNEMER) {
                            $sEvalGuid = Database::getFieldValue('data', 'select guid from tblEvalDeelname where fkDeelnemer=' . $deelnemerID . ' AND fkRooster=' . $CalendarRS['AgendaID'], 'guid');
                            if ($sEvalGuid !== '') {
                                $actObj->EvaluatieUrl = '/evaluatie/' . $sEvalGuid;
                            } else {
                                $actObj->EvaluatieTonen = false;
                            }
                        } else {
                            $sEvalGuid = Database::getFieldValue('data', 'select guid from tblEvalDeelname where fkDocent=' . $DocentID . ' AND fkrooster=' . $CalendarRS['AgendaID'], 'guid');
                            if ($sEvalGuid !== '') {
                                $actObj->EvaluatieUrl = '/evaluatie/' . $sEvalGuid;
                            } else {
                                $actObj->EvaluatieTonen = false;
                            }
                        }
                    }
                }
                $actObj->showDownloads = true;
            } else {
                // Empty else block
            }
        }
        $DocentenRS = null;
        $DownloadRS = null;
        $CalendarRS = null;
    }
    if (false) {
        if ($LoginType == USER_TYPE_DEELNEMER) {
            $CalendarSQL = ' SELECT tblDeelnameVrijBlok.ID, tblDeelnameVrijBlok.OMSCHRIJVING, tblDeelnameVrijBlok.Datum, tblDeelnameVrijBlok.AantalUur, tblDeelnameVrijBlok.AKKOORD, tblDeelname.fkDeelnemer, tblDeelname.fkOpleiding 	FROM tblDeelname INNER JOIN tblDeelnameVrijBlok ON tblDeelname.ID = tblDeelnameVrijBlok.fkDeelname  WHERE tblDeelname.fkOpleiding in (' . $UserOpleidingen . ') AND month(Datum)=' . date("n", strtotime($datum)) . ' AND year(Datum)=' . date("Y", strtotime($datum)) . ' and tblDeelname.fkDeelnemer = ' . $deelnemerID . ' ORDER BY tblDeelnameVrijBlok.Datum';
            $CalendarRS = Database::openRS($CalendarSQL, 'data', ADOPENREADONLY);
            if ($CalendarRS === null) {
                throw new \Exception('Database query failed: ' . Database::getLastError());
            }
            $CalendarSQL->MoveNext();
            while (!$CalendarRS->EOF()) {
                $actObj = $MyCalendar->Days.AddActivity('Vrij blok:&nbsp;' . $CalendarRS['OMSCHRIJVING'] . ' ' . ($CalendarRS['AKKOORD'] ? '(akkoord)' : '(nog niet akkoord)'), '', '', '', '', '', '', '', '', '');
                $actObj->isDocent = false;
                $actObj->isJaargroepopleider = false;
                $actObj->isHoofddocent = false;
                $actObj->Draaiboek = '';
                $actObj->DraaiboekPDF = '';
                $actObj->showDownloads = false;
            }
            $CalendarRS = null;
        }
        if (false) {
            if ($LoginType == USER_TYPE_DEELNEMER) {
                $deadlineSQL = 'SELECT tblUitnodigingGesprekken.ConceptControle, tblUitnodigingGesprekken.ConceptDocument, tblOpleidingen.Titel AS Opleiding, tblOpleidingen.Code, tblUitnodigingGesprekken.datum, tblDeelnemers.VolledigeNaam as Deelnemer FROM tblOpleidingen INNER JOIN (tblDeelnemers INNER JOIN (tblDeelname INNER JOIN tblUitnodigingGesprekken ON tblDeelname.ID = tblUitnodigingGesprekken.fkDeelname) ON tblDeelnemers.ID = tblDeelname.fkDeelnemer) ON tblOpleidingen.ID = tblDeelname.fkOpleiding WHERE tblUitnodigingGesprekken.ConceptDocument Is Null AND tblUitnodigingGesprekken.fkGespreksType=1 and tblDeelname.fkDeelnemer=' . $deelnemerID;
                Debug::write($deadlineSQL);
                Create_Controle($MyCalendar, $datum, 'Uiterste deadline plaatsing concept werkplan bij jouw IOP-gesprek op [datum] ' . (Login_MeerdereOpleidingen() ? '([code] [opleiding])' : ''), 'datum', Application::get('termijn_concept_werkplan', ''));
            }
        }
    }
    if ($TypeWeergave != TYPE_ROOSTER) {
        if ($LoginType != USER_TYPE_CONTACTP_INV && $LoginType != USER_TYPE_KLANTCP && !$LoginType == USER_TYPE_ASSISTENT && !GetUserBIG()) {
            $gesprekSQL = 'SELECT tblOpleidingen.ID as OplID, tblOpleidingen.code, tblUitnodigingGesprekken.ID, tblUitnodiginggesprekken.rinozaal, tblUitnodigingGesprekken.datum, tblUitnodigingGesprekken.van, tblUitnodigingGesprekken.tot, tblUitnodigingGesprekken.ConceptDocument, tblUitnodigingGesprekken.ConceptGeplaatst, tblUitnodigingGesprekken.locatie, tblUitnodigingGesprekken.locatieOnline, tblUitnodigingGesprekken.locatieOnlineMeetingID, tblUitnodigingGesprekken.locatieOnlineMeetingPassword,  tblPraktijkOpleiders.VolledigeNaam AS Praktijkopleider, tblDocenten.VolledigeNaam AS Hoofddocent, tblDeelnemers.VolledigeNaam AS Deelnemer, tblGespreksType.Naam AS TypeGesprek, tblUitnodigingGesprekken.fkUitnodiging, tblUitnodigingGesprekken.fkGespreksType as TypeGesprekID ';
            if ($LoginType == USER_TYPE_DOCENT || $LoginType == USER_TYPE_ASSISTENT || $LoginType == USER_TYPE_DEELNEMER) {
                $gesprekSQL .= 'FROM tblOpleidingen RIGHT JOIN (tblPraktijkOpleiders RIGHT JOIN (tblGespreksType RIGHT JOIN (tblDocenten INNER JOIN (tblDeelnemers RIGHT JOIN (tblDeelname RIGHT JOIN tblUitnodigingGesprekken ON tblDeelname.ID = tblUitnodigingGesprekken.fkDeelname) ON tblDeelnemers.ID = tblDeelname.fkDeelnemer) ON tblDocenten.ID = tblUitnodigingGesprekken.fkHoofddocent) ON tblGespreksType.ID = tblUitnodigingGesprekken.fkGespreksType) ON tblPraktijkOpleiders.ID = tblDeelname.fkPraktijkOpleider) ON tblOpleidingen.ID = tblDeelname.fkOpleiding ';
                if ($LoginType == USER_TYPE_DEELNEMER) {
                    $gesprekSQL = SQL::addWhere($gesprekSQL, 'tblDeelname.fkDeelnemer=' . $deelnemerID);
                } elseif ($LoginType == USER_TYPE_PRAKTIJKOPL) {
                    $gesprekSQL = SQL::addWhere($gesprekSQL, 'tblPraktijkOpleiders.id=' . $praktijkopleiderID);
                }
            } else {
                $gesprekSQL .= 'FROM tblOpleidingen INNER JOIN (tblPraktijkOpleiders INNER JOIN (tblGespreksType INNER JOIN (tblDocenten INNER JOIN (tblDeelnemers INNER JOIN (tblDeelname INNER JOIN tblUitnodigingGesprekken ON tblDeelname.ID = tblUitnodigingGesprekken.fkDeelname) ON tblDeelnemers.ID = tblDeelname.fkDeelnemer) ON tblDocenten.ID = tblUitnodigingGesprekken.fkHoofddocent) ON tblGespreksType.ID = tblUitnodigingGesprekken.fkGespreksType) ON tblPraktijkOpleiders.ID = tblDeelname.fkPraktijkOpleider) ON tblOpleidingen.ID = tblDeelname.fkOpleiding ';
            }
            $gesprekSQL .= ' ORDER BY Datum, Van ';
            $gesprekSQL = SQL::addWhere($gesprekSQL, 'bVoorlopig=False');
            $gesprekSQL = SQL::addWhere($gesprekSQL, 'month(Datum)=' . date("n", strtotime($datum)) . ' AND year(Datum)=' . date("Y", strtotime($datum)));
            if ($LoginType == USER_TYPE_ASSISTENT) {
                if ($LoginType == USER_TYPE_ASSISTENT) {
                    $gesprekSQL = SQL::addInClause($gesprekSQL, 'tblDeelname.fkOpleiding', GetAssistentOpleidingIDs());
                }
            } elseif (USER_BSERVICEBUREAU) {
            } else {
                if ($LoginType == USER_TYPE_P_PRAKTOPL) {
                    $gesprekSQL = SQL::addWhere($gesprekSQL, ($userPraktijkopleiders === "" ? 'false' : 'fkPraktijkopleider in (' . $userPraktijkopleiders . ')'));
                } elseif ($LoginType == USER_TYPE_DOCENT) {
                    $gesprekSQL = SQL::addWhere($gesprekSQL, User_GetWhereClauseHoofdDocent('tblUitnodigingGesprekken') . ' or tblUitnodigingGesprekken.fkHoofddocent2=' . $DocentID . ' or tblUitnodigingGesprekken.fkHoofdopleider=' . $DocentID);
                } else {
                    $gesprekSQL = SQL::addWhere($gesprekSQL, User_GetWhereClauseHoofdDocent('tblUitnodigingGesprekken'));
                }
            }
            if ($LoginType == USER_TYPE_PRAKTIJKOPL) {
                $gesprekSQL = SQL::addWhere($gesprekSQL, 'tblGespreksType.ID<>' . GESPREKSTYPE_SELECTIEGESPREK);
            }
            $gesprekRS = null;
            Database::openRS($gesprekRS, $gesprekSQL, 'data', adOpenForwardOnly);
            if ($gesprekRS === null) {
                throw new \Exception('Database query failed: ' . Database::getLastError());
            }
            $gesprekRS->MoveNext();
            while (!$gesprekRS->eof()) {
                $sTitel = ($gesprekRS['TypeGesprek'] == "" ? 'Gereserveerd voor een gesprek' : $gesprekRS['TypeGesprek'] . ($LoginType == USER_TYPE_DEELNEMER ? '' : ' ' . $gesprekRS['deelnemer'] . ' - ' . $gesprekRS['code'])) . ($LoginType != USER_TYPE_DEELNEMER && !is_null($gesprekRS['ConceptGeplaatst']) ? ' - concept geplaatst' : '');
                $sLocatie = $gesprekRS['Locatie'];
                if ($gesprekRS['RinoZaal']!= '') {
                    $sLocatie .= ' - ' . Str::trim($gesprekRS['RinoZaal'] ?? '');
                }
                $actObj = $MyCalendar->Days.AddActivity($sTitel, $gesprekRS['Van'], $gesprekRS['Tot'], $sLocatie, $gesprekRS['LocatieOnline'], $gesprekRS['LocatieOnlineMeetingID'], $gesprekRS['LocatieOnlineMeetingPassword'], ($LoginType != USER_TYPE_DOCENT ? $gesprekRS['HoofdDocent'] : ''), '', '');
                $actObj->isDocent = false;
                $actObj->isHoofddocent = false;
                $actObj->isJaargroepopleider = false;
                $actObj->ExportType = 2;
                $actObj->ID = $gesprekRS['ID'];
                $actObj->UploadAllowed = $LoginType == USER_TYPE_PRAKTIJKOPL && $gesprekRS['TypeGesprekID'] == GESPREKSTYPE_WERKPLAN || $gesprekRS['TypeGesprekID'] == GESPREKSTYPE_WERKPLAN_SELECT || $gesprekRS['TypeGesprekID'] == GESPREKSTYPE_WERKPLAN_POP;
                $actObj->UploadDescr = 'Concept IOP/werkplan';
                $actObj->ConceptDocument = ($gesprekRS['ConceptDocument']!= '' ? 'uploads/gesprekken/' . $gesprekRS['ConceptDocument'] : '');
                $actObj->ConceptGeplaatst = $gesprekRS['ConceptGeplaatst'];
                $actObj->showDownloads = false;
                $actObj->Praktijkopleider = $gesprekRS['Praktijkopleider'];
                $actObj->GesprekID = ($gesprekRS['fkUitnodiging'] == "" ? '' : $gesprekRS['ID']);
                $actObj->TypeGesprekID = $gesprekRS['TypeGesprekID'];
                $actObj->DocentTxt = 'Hoofd-/jaargroepopleider';
            }
            if ($LoginType == USER_TYPE_ASSISTENT || $LoginType == USER_TYPE_DEELNEMER || $LoginType == USER_TYPE_DOCENT || $LoginType == USER_TYPE_PRAKTIJKOPL) {
                $AfspraakRS = null;
                $tel = null;
                $AfspraakSQL = 'SELECT tblAfspraak.ID, tblAfspraak.guid, tblAfspraak.datum, tblAfspraak.tijd, tblAfspraak.tijdTot, tblAfspraak.Onderwerp, tblAfspraak.Locatie, tblAfspraak.Opmerkingen_Ingevuld FROM tblAfspraak INNER JOIN tblAfspraakAanwezigen ON tblAfspraak.ID = tblAfspraakAanwezigen.fkAfspraak ORDER BY Datum, Tijd ';
                $AfspraakSQL = SQL::addWhere($AfspraakSQL, 'month(Datum)=' . date("n", strtotime($datum)) . ' AND year(Datum)=' . date("Y", strtotime($datum)));
                $AfspraakSQL = SQL::addWhere($AfspraakSQL, User_GetWhereClause());
                $AfspraakRS = null;
                Database::openRS($AfspraakRS, $AfspraakSQL, 'data', adOpenForwardOnly);
                if ($AfspraakRS === null) {
                    throw new \Exception('Database query failed: ' . Database::getLastError());
                }
                $AfspraakRS->MoveNext();
                while (!$AfspraakRS->eof()) {
                    $sDownloads = '';
                    $downloadsSQL = 'select download,omschrijving from tblAfspraakBijlagen where fkAfspraak=' . $AfspraakRS['ID'] . ' order by sortorder';
                    $arrAfspraakDownloads = Cache::retrieve('afspraak_bijlagen_' . $AfspraakRS['ID'] . '_' . date("G", strtotime(date("H:i:s"))), 'data', $downloadsSQL);
                    if (Arr::isArray($arrAfspraakDownloads)) {
                        for ($tel = 0; $tel <= (count($arrAfspraakDownloads) - 1); $tel++) {
                            $sTitel = ($arrAfspraakDownloads[1][$tel] == '-' ? FileNameDescription($objFSO, $arrAfspraakDownloads[0][$tel]) : $arrAfspraakDownloads[1][$tel]);
                            $sDownloads .= '<a href="/uploads/afspraken/' . $arrAfspraakDownloads[0][$tel] . '" target=_blank>' . $sTitel . '</a><br>';
                        }
                    }
                    $actObj = $MyCalendar->Days.AddActivity(Server::htmlEncode($AfspraakRS['onderwerp'] . ''), $AfspraakRS['Tijd'], $AfspraakRS['TijdTot'], $AfspraakRS['Locatie'], '', '', '', '', '', '');
                    $actObj->isDocent = false;
                    $actObj->isHoofddocent = false;
                    $actObj->isJaargroepopleider = false;
                    $actObj->ID = $AfspraakRS['ID'];
                    $actObj->AfspraakID = $actObj->ID();
                    $actObj->UploadAllowed = false;
                    $actObj->UploadDescr = '';
                    $actObj->ConceptDocument = '';
                    $actObj->ConceptGeplaatst = false;
                    $actObj->Praktijkopleider = '';
                    $actObj->GesprekID = '';
                    $actObj->TypeGesprekID = '';
                    $actObj->showDownloads = $sDownloads !== '';
                    $actObj->Downloads = $sDownloads;
                    $actObj->DocentTxt = '';
                }
            }
            if ($TypeWeergave != TYPE_ROOSTER) {
                if ($LoginType == USER_TYPE_ASSISTENT || $LoginType == USER_TYPE_DOCENT) {
                    $reserveringSQL = 'SELECT tblAgendaReserveringen.*, tblDocenten.VolledigeNaam, tblGespreksType.Naam AS Gesprekstype FROM tblGespreksType RIGHT JOIN (tblDocenten INNER JOIN tblAgendaReserveringen ON tblDocenten.ID = tblAgendaReserveringen.fkDocent) ON tblGespreksType.ID = tblAgendaReserveringen.fkTypeGesprek';
                    if ($LoginType == USER_TYPE_DOCENT) {
                        $reserveringSQL = SQL::addWhere($reserveringSQL, 'fkDocent=' . $DocentID);
                    }
                    $reserveringSQL = SQL::addWhere($reserveringSQL, 'month(Datum)=' . date("n", strtotime($datum)) . ' AND year(Datum)=' . date("Y", strtotime($datum)));
                    $reserveringRS = Database::openRS($reserveringSQL, 'data', ADOPENREADONLY);
                    if ($reserveringRS === null) {
                        throw new \Exception('Database query failed: ' . Database::getLastError());
                    }
                    $reserveringSQL->MoveNext();
                    while (!$reserveringRS->eof()) {
                        $actObj = $MyCalendar->Days.AddActivity('Beschikbaar voor ' . ($reserveringRS['opleidingscode'] != '' ? $reserveringRS['opleidingscode'] . ' ' : '') . ($reserveringRS['Gesprekstype'] != '' ? str_replace('gesprek', 'gesprekken', $reserveringRS['Gesprekstype'] . '') : 'gesprekken') . ' ' . ($LoginType == USER_TYPE_DOCENT ? '' : ' (' . $reserveringRS['volledigenaam'] . ')') . ($reserveringRS['Opmerking']!= '' ? ': ' . str_replace(PHP_EOL, ' ', $reserveringRS['Opmerking'] . '') : ''), $reserveringRS['TijdVan'], $reserveringRS['TijdTot'], '', '', '', '', '', '', '');
                        $actObj->ID = $reserveringRS['ID'];
                        $actObj->isReservering = true;
                        $actObj->Opmerking = str_replace(PHP_EOL, '<br>', $reserveringRS['Opmerking'] . '');
                        $actObj = null;
                    }
                    $reserveringRS = null;
                }
            }
        }
    }
    $objFSO = null;
    $Create_Calendar = $MyCalendar;
}
?>
