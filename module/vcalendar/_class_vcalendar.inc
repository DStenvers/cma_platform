<?php
use App\Library\Response;
use App\Library\Server;

require_once __DIR__ . '/../../library/library.inc';

// See http://www.devx.com/getHelpOn/10MinuteSolution/20508 for more information
if (class_exists('vCalendar')) { return; }
class vCalendar
{
    public $dtStart;
    public $dtEnd;
    public $strSubject;
    public $strLocation;
    public $strDesc;
    public $strCategorie;
    public $strUrl;
    /**
    * Csvfile
    *
    */
    public function CSVFile()
    {
        global $fso, $sContent;
        define("FORWRITING", 2);
        $fso = null;
        $f = null;
        $sFile = null;
        $sContent = null;
        $sFile = str_replace(':', '', str_replace(' ', '', CACHE_PATH . 'calender_' . str_replace('/', '_', $dtStart . '') . substr($strSubject, 0, 10) . str_replace('/', '_', date("Y-m-d H:i:s") . '') . '.ics'));
        $sContent = $this->BuildVCalendar();
        $fso = new FileSystemObject();
        $f = $fso->OpenTextFile(Server::mapPath($sFile), true);
        $f->Write($sContent);
        $f = null;
        $fso = null;
        if ($php_error_number == 0) {
            Response::redirect($sFile);
        }
    }
    /**
    * Buildvcalendar
    *
    */
    public function BuildVCalendar()
    {
        $sStart = null;
        $sStart = Date::toGMT($dtStart);
        $sEnd = null;
        $sEnd = Date::toGMT($dtEnd);
        $sDatestamp = null;
        $sDatestamp = Date::toGMT(date("Y-m-d H:i:s"));
        return 'BEGIN:VCALENDAR' . PHP_EOL . 'VERSION:1.0' . PHP_EOL . 'BEGIN: VEVENT' . PHP_EOL . 'DTStart:' . $sStart . PHP_EOL . 'DTEnd:' . $sEnd . PHP_EOL . 'DTSTAMP:' . $sDateStamp . PHP_EOL . 'CATEGORIES:' . $strCategorie . PHP_EOL . 'SUMMARY;ENCODING=QUOTED-PRINTABLE:' . $strSubject . PHP_EOL . 'DESCRIPTION;ENCODING=QUOTED-PRINTABLE:' . $strDesc . PHP_EOL . 'Location;ENCODING=QUOTED-PRINTABLE:' . $strLocation . PHP_EOL . 'UID:' . $sStart . $strSubject . PHP_EOL . 'URL:' . $strUrl . PHP_EOL . 'PRIORITY:0' . PHP_EOL . 'End:VEVENT' . PHP_EOL . 'End:VCALENDAR' . PHP_EOL;
    }
    /**
    *   Construct
    */
    public function __construct()
    {
        $this->dtstart = date("Y-m-d H:i:s");
        $this->dtend = date("Y-m-d H:i:s");
        $this->strsubject = '<Geen titel>';
        $this->strlocation = '<Geen locatie>';
        $this->strdesc = '';
        // Creates a HTML page with the content of the vCalendar item
    }
    /**
    *   Destruct
    */
    public function __destruct()
    {
    }
}
?>
