<?php
// TODO: make optional
use App\Library\Application;
use App\Library\Request;
use App\Library\Server;

$blnSmartLinks = null;
// only links to days that have activities!
$blnShowEventsInCalendar = null;
$CalendarFont = null;
// Requires lib_datum.inc
// TODO: : allow change of CSS classes used
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
    private $mdDate;
    private $msToday;
    private $mnDay;
    private $mnMonth;
    private $mnYear;
    private $mnDayMonthStarts;
    private $mnDaysInMonth;
    private $mcolDays;
    private $mbDaysInitialized;
    /**
    *   Construct
    */
    public function __construct()
    {
        $this->Width = 175;
        $this->Height = 150;
        $this->ShowDateSelect = false;
        $this->msToday = date("Y-m-d H:i:s", strtotime(date("Y-m-d H:i:s", mktime(0, 0, 0, date("n", strtotime(date("Y-m-d H:i:s"))), date("j", strtotime(date("Y-m-d H:i:s"))), date("Y", strtotime(date("Y-m-d H:i:s")))))));
        $this->ShowPrevNextMonth = true;
        if (Application::get('Mod_language', '') == 'UK') {
            $this->strDays = 'Su,Mo,Tu,We,Th,Fr,Sa';
        } else {
            $this->strDays = 'Zo,Ma,Di,Wo,Do,Vr,Za';
        }
        $blnSmartLinks = true;
        $blnShowEventsInCalendar = false;
        $this->mcolDays = new ArrayObject();
        if (Request::query('date', '') != '') {
            $this->SetDate(Request::query('date', ''));
        } else {
            $this->SetDate(date("Y-m-d H:i:s"));
        }
        $this->OnDayClick = Request::server('PHP_SELF', '');
        $this->OnNextMonthClick = Request::server('PHP_SELF', '') . '?date=' . Server::urlEncode(date("Y-m-d H:i:s", mktime(0, 0, 0, $this->mnMonth + 1, $this->mnDay, $this->mnYear)));
        $this->OnPrevMonthClick = Request::server('PHP_SELF', '') . '?date=' . Server::urlEncode(date("Y-m-d H:i:s", mktime(0, 0, 0, $this->mnMonth - 1, $this->mnDay, $this->mnYear)));
        $this->mbDaysInitialized = false;
        // comma separated list of days
    }
    /**
    *   Destruct
    */
    public function __destruct()
    {
        if (is_object($this->mcolDays)) {
            $this->mcolDays = null;
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
        for ($nDayIndex = 1; $nDayIndex <= $this->mnDaysInMonth; $nDayIndex++) {
            $objNewDay = new CalendarDay();
            $objNewDay->DateString = date("Y-m-d H:i:s", strtotime(date("Y-m-d H:i:s", mktime(0, 0, 0, $this->mnMonth, $nDayIndex, $this->mnYear))));
            $objNewDay->OnClick = $this->OnDayClick;
            $this->mcolDays->Add($nDayIndex, $objNewDay);
        }
        $this->mbDaysInitialized = true;
    }
    /**
    * Setdate
    *
    */
    public function SetDate($dDate)
    {
        $this->mdDate = (function($value) {
            if (is_null($value) || $value === "") throw new Exception("Type mismatch");
            $timestamp = strtotime($value);
            if ($timestamp === false) throw new Exception("Type mismatch");
            return date("Y-m-d H:i:s", $timestamp);
        })($dDate);
        $this->mnDay = date("j", strtotime($dDate));
        $this->mnMonth = date("n", strtotime($dDate));
        $this->mnYear = date("Y", strtotime($dDate));
        $this->mnDaysInMonth = date("j", strtotime(dateAdd('d', -1, date("Y-m-d H:i:s", mktime(0, 0, 0, $this->mnMonth + 1, 1, $this->mnYear)))));
        $this->mnDayMonthStarts = (date("w", strtotime(dateAdd('d', -date("j", strtotime((function($value) {
            if (is_null($value) || $value === "") throw new Exception("Type mismatch");
            $timestamp = strtotime($value);
            if ($timestamp === false) throw new Exception("Type mismatch");
            return date("Y-m-d H:i:s", $timestamp);
        })($dDate))) - 1, (function($value) {
            if (is_null($value) || $value === "") throw new Exception("Type mismatch");
            $timestamp = strtotime($value);
            if ($timestamp === false) throw new Exception("Type mismatch");
            return date("Y-m-d H:i:s", $timestamp);
        })($dDate)))) + 1);
    }
    /**
    * Render
    */
    public function Render()
    {
        $nDayCount = null;
        $nCellWidth = null;
        $nFontSizeRatio = null;
        $objDay = null;
        $arrDaysdescr = array();
        $nTDWidth = null;
        $t = null;
        if (!$this->mbDaysInitialized) {
            $this->InitDays();
        }
        $nFontSizeRatio = (($this->Width / 200) >= 0 ? floor($this->Width / 200) : ceil($this->Width / 200));
        $this->Send('<div id=calendar style="width:' . $this->Width . 'px;height:' . strval($this->Height) . 'px">');
        // bordercolor=darkblue
        $this->Send('<table class=CalenderTable border=1 width=100% height=100% cellspacing=0 cellpadding=3>');
        $this->Send('<tr><td class=CalenderTDTitle colspan=7>');
        if ($this->ShowPrevNextMonth) {
            $this->Send('	<table width=100% height=100% cellspacing=0>');
            $this->Send('	<tr>');
            $this->Send('	<td class=CalenderTDTitle align=left><a style=text-decoration:none href="' . str_replace('$date', date("Y-m-d H:i:s", mktime(0, 0, 0, $this->mnMonth - 1, $this->mnDay, $this->mnYear)), $this->OnPrevMonthClick) . '">&nbsp;&lt;&lt;</a></thd>');
            $this->Send('	<td class=CalenderTDTitle>');
        }
        $this->Send(ToLongMonth($this->mnMonth) . ' ' . $this->mnYear);
        if ($this->ShowPrevNextMonth) {
            $this->Send('</th>');
            $this->Send('	<td class=CalenderTDTitle align=right><a style=text-decoration:none href="' . str_replace('$date', date("Y-m-d H:i:s", mktime(0, 0, 0, $this->mnMonth + 1, $this->mnDay, $this->mnYear)), $this->OnNextMonthClick) . '">&gt;&gt;&nbsp;</a></th>');
            $this->Send('	</tr>');
            $this->Send('	</table>');
        }
        $this->Send('</td></tr>');
        $this->Send('<tr>');
        $arrDaysdescr = explode(',', $this->strDays);
        for ($t = 0; $t <= 6; $t++) {
            $this->Send('<td class=CalenderTDDays width=' . (($this->Width / 7) >= 0 ? floor($this->Width / 7) : ceil($this->Width / 7)) . '>' . $arrDaysdescr[$t] . '</td>');
        }
        $this->Send('</tr>');
        $this->Send('<tr align=center>');
        for ($nDayCount = 1; $nDayCount <= $this->mnDayMonthStarts - 1; $nDayCount++) {
            $this->Send('<td class=CalenderTDUnused>&nbsp;</td>');
        }
        $nDayCount = $nDayCount - 1;
        foreach ($this->mcolDays->Items() as $objDay) {
            if ($nDayCount == 7) {
                $this->Send('</tr><tr align=center>');
                $nDayCount = 0;
            }
            $this->Send('<td');
            if ($objDay->DateString == $this->msToday) {
                $this->Send(' class=CalenderTDToday>');
            } else {
                $this->Send(' class=CalenderTD>');
            }
            $objDay->Draw();
            $this->Send('</td>');
            $nDayCount = $nDayCount + 1;
        }
        if ($nDayCount < 7) {
            for ($nDayCount = $nDayCount; $nDayCount <= 6; $nDayCount++) {
                $this->Send('<td class=CalenderTDUnused>&nbsp;</td>');
            }
        }
        $this->Send('</tr>');
        if ($this->ShowDateSelect) {
            $this->Send('<tr><td colspan=7 align=center>');
            $this->DrawDateSelect();
            $this->Send('</td></tr>');
        }
        $this->Send('</table>');
        $this->Send('</div>');
    }
    /**
    * Drawdateselect
    */
    public function DrawDateSelect()
    {
        $nIndex = null;
        $this->Send('	<form id=frmGO name=frmGO>');
        $this->Send('	<table>');
        $this->Send('	<tr>');
        $this->Send('	<td><select name="month">');
        for ($nIndex = 1; $nIndex <= 12; $nIndex++) {
            echo '<option value="' . $nIndex . '"';
            if ($nIndex == date("n", strtotime($this->mdDate))) {
                echo ' selected';
            }
            $this->Send('>' . date((true ? "M" : "F"), mktime(0, 0, 0, $nIndex, 1)) . '</option>');
        }
        $this->Send('	</select></td>');
        $this->Send('	<td><select name="year">');
        for ($nIndex = date("Y", strtotime(date("Y-m-d H:i:s"))) - 4; $nIndex <= date("Y", strtotime(date("Y-m-d H:i:s"))) + 6; $nIndex++) {
            echo '<option value="' . $nIndex . '"';
            if ($nIndex == date("Y", strtotime($this->mdDate))) {
                echo ' selected';
            }
            $this->Send('>' . strval($nIndex) . '</option>');
        }
        $this->Send('	</select></td>');
        $this->Send('	<td><input type="button" Value="Go" onclick="document.location=\'' . Request::server('PHP_SELF', '') . "?date='+this.form.month.options[this.form.month.selectedIndex]+'/1/'+this.form.year.options[this.form.year.selectedIndex];\" id=1 name=1></td>");
        $this->Send('	</form>');
        $this->Send('	</tr></table>');
    }
    /**
    * Send
    *
    */
    public function Send($sHTML)
    {
        echo $sHTML . PHP_EOL;
        // Response.Write (sHTML)
    }
}
if (class_exists('CalendarActivity')) { return; }
class CalendarActivity
{
    public $Text;
    public $Color;
    /**
    *   Construct
    */
    public function __construct()
    {
    }
    /**
    *   Destruct
    */
    public function __destruct()
    {
    }
}
if (class_exists('CalendarDay')) { return; }
class CalendarDay
{
    public $DateString;
    public $OnClick;
    private $mcolActivities;
    private $mbActivitiesInit;
    /**
    * Addactivity
    *
    */
    public function AddActivity($sActivity, $sColor)
    {
        $objActivity = null;
        if (!$this->mbActivitiesInit) {
            $this->InitActivities();
        }
        $objActivity = new CalendarActivity();
        $objActivity->Text = $sActivity;
        $objActivity->Color = $sColor;
        $this->mcolActivities->Add($this->mcolActivities->Count + 1, $objActivity);
        $objActivity = null;
    }
    /**
    * Draw
    */
    public function Draw()
    {
        $objActivity = null;
        $blnHaveLink = null;
        if ($blnShowEventsInCalendar) {
            $this->Send('<table width=100% cellspacing=2 cellpadding=1>');
            $this->Send('<tr><td align=center valign=top>');
        }
        $blnHaveLink = true;
        if ($blnSmartLinks) {
            if (!$this->mbActivitiesInit) {
                $blnHaveLink = false;
            }
        }
        if ($blnHaveLink) {
            if ($blnSmartLinks) {
                $this->Send('<B>');
            }
            $this->Send('<a style="text-decoration:none;' . $CalendarFont . ';" href="' . str_replace('$date', $this->DateString, $this->OnClick) . '">');
        }
        echo date("j", strtotime($this->DateString)) . '</a>';
        if ($blnShowEventsInCalendar) {
            $this->Send('</td></tr>');
        }
        if ($this->mbActivitiesInit) {
            if ($blnShowEventsInCalendar) {
                foreach ($this->mcolActivities->Items() as $objActivity) {
                    $this->Send('<tr><td>' . $objActivity->Text . '</td></tr>');
                }
            }
        }
        if ($blnShowEventsInCalendar) {
            $this->Send('</table>');
        }
    }
    /**
    * Initactivities
    */
    public function InitActivities()
    {
        $this->mcolActivities = new ArrayObject();
        $this->mbActivitiesInit = true;
    }
    /**
    * Send
    *
    */
    public function Send($sHTML)
    {
        echo $sHTML . PHP_EOL;
        // Response.Write sHTML
    }
    /**
    *   Construct
    */
    public function __construct()
    {
        $this->mbActivitiesInit = false;
    }
    /**
    *   Destruct
    */
    public function __destruct()
    {
        if (is_object($this->mcolActivities)) {
            $this->mcolActivities->RemoveAll();
            $this->mcolActivities = null;
        }
    }
}
?>
