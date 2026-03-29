<?php
use App\Library\Application;
use App\Library\Response;
use App\Library\Server;
use App\Library\File;

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
        $sFile = null;
        $sFile = str_replace(':', '_', str_replace(' ', '_', str_replace('\\', '/', Application::get('base_path', '') . CACHE_PATH . 'VCAL_' . date("Y-m-d H:i:s") . '.ics')));
        $sContent = null;
        $sContent = $this->BuildVCalendar();
        if (File::createAsciiFile(Server::mapPath($sFile), $sContent)) {
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
        $sStart = Date::toGMT($this->dtStart);
        $sEnd = null;
        $sEnd = Date::toGMT($this->dtEnd);
        $sDatestamp = null;
        $sDatestamp = Date::toGMT(date("Y-m-d H:i:s"));
        return 'BEGIN:VCALENDAR' . PHP_EOL . 'VERSION:1.0' . PHP_EOL . 'BEGIN: VEVENT' . PHP_EOL . 'DTStart:' . $sStart . PHP_EOL . 'DTEnd:' . $sEnd . PHP_EOL . 'DTSTAMP:' . $sDatestamp . PHP_EOL . 'CATEGORIES:' . $this->strCategorie . PHP_EOL . 'SUMMARY;ENCODING=QUOTED-PRINTABLE:' . $this->strSubject . PHP_EOL . 'DESCRIPTION;ENCODING=QUOTED-PRINTABLE:' . $this->strDesc . PHP_EOL . 'Location;ENCODING=QUOTED-PRINTABLE:' . $this->strLocation . PHP_EOL . 'UID:' . $sStart . $this->strSubject . PHP_EOL . 'URL:' . $this->strUrl . PHP_EOL . 'PRIORITY:0' . PHP_EOL . 'End:VEVENT' . PHP_EOL . 'End:VCALENDAR' . PHP_EOL;
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
