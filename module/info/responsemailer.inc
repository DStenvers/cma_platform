<?php
// This object serves 2 purposes :
// -  Store information posted in a form
// -  Retrieve the information and send it to a specific email addresss
// -----------------------------------------------------------------------------------------------------------------
// Sample implementation
// dim respMailer
// set respMailer = new ResponseMailer
// if Request.Form.Count > 0 then
// if respMailer.SaveToDatabase() then
// echo 'Thank you!'
// end if
// else
// respMailer.eMailTo = "doctorsfordoctors@altanapharma.nl"
// respMailer.SendMail()
// end if
// Requirements: lib_error should be included by the caller app
use App\Library\Application;
use App\Library\Arr;
use App\Library\Database;
use App\Library\Error;
use App\Library\Request;
use App\Library\SQL;

if (class_exists('ResponseMailer')) { return; }
class ResponseMailer
{
    private $strTable;
    private $mailTo;
    private $mailCC;
    private $mailBCC;
    private $conn_string;
    private $txtHaveData;
    private $txtHaveNoData;
    private $txtSubject;
    private $intFormat;
    private $txtConvertFieldsToUpper;
    private $txtConvertFieldsToFirstUpper;
    private $arrSkipFields;
    private $strSkipFields;
    private $blnCreateFields;
    /**
    * Settexts
    *
    */
    public function SetTexts($parData, $parNoData)
    {
        $this->txthavedata = $parData;
        $this->txthavenodata = $parNoData;
    }
    /**
    * Savetodatabase
    *
    */
    public function SaveToDatabase()
    {
        global $name, $strError;
        // Loosely based upon the code of formmail.php; skipping fields used for functionality
        // NOTE: Please note that the form fields are used to determine the database field names
        // If they dont match an error will occur!
        $strError = "";
        $blnRetval = null;
        $strVeld = "";
        $strInsertSQL = "";
        $strValues = "";
        $strFields = "";
        $name = null;
        // dim fieldOrder
        // Validatie van de vereiste gegevens
        $strError = lib_FormValRequired();
        // try and find an email address and validate this as well
        foreach (Request::postAll() as $name) {
            if ((is_null($name) ? "" : strtolower($name ?? '')) == 'email') {
                if (Request::post($name, '') != '') {
                    if (!lib_FormValidEmail(Request::post($name, ''))) {
                        if (Application::get('mod_language', '') == 'UK') {
                            $strError = $strError . "<li>'<b>" . $name . "</b>' contains an invalid e-mail address '" . Request::post($name, '') . "'</li>";
                        } else {
                            $strError = $strError . "<li>Het veld '<b>" . $name . "</b>' bevat een ongeldig e-mailadres '" . Request::post($name, '') . "'</li>";
                        }
                    }
                }
            }
        }
        if ($strError == '') {
            $crea_conn = Database::openRS($conn_string);
            if ($conn_string === null) {
                throw new \Exception('Database query failed: ' . Database::getLastError());
            }
            $conn_String_current_row = $conn_string->fetch(PDO::FETCH_ASSOC);
            $strInsertSQL = 'INSERT INTO ' . $strTable;
            foreach (Request::postAll() as $name) {
                $strVeld = $name;
                if (Arr::findTrimmed($arrSkipFields, $strVeld) == -1) {
                    if ((is_null(substr($strVeld, 0, 9)) ? "" : strtolower(substr($strVeld, 0, 9) ?? '')) == 'required-') {
                        $strVeld = substr($strVeld, max(0, 10 - 1));
                    }
                    if ((is_null(substr($strVeld, 0, 8)) ? "" : strtolower(substr($strVeld, 0, 8) ?? '')) == 'required') {
                        $strVeld = substr($strVeld, max(0, 9 - 1));
                    }
                    if ($blnCreateFields) {
                        if (!Database::fieldExists($crea_conn, $strTable, $strVeld)) {
                            Database::addField($conn_string, $strTable, $strVeld, ADVARWCHAR, 255);
                        }
                    }
                    $strValues = $strValues . SQL::postString(Request::post($name, '')) . ', ';
                    $strFields = $strFields . $strVeld . ', ';
                }
            }
            $crea_conn = null;
            $strInsertSQL = $strInsertSQL . '(' . substr($strFields, 0, max(0, min((is_null($strFields) ? 0 : strlen($strFields ?? '')) - 2, strlen($strFields ?? '')))) . ') VALUES (' . substr($strValues, 0, max(0, min((is_null($strValues) ? 0 : strlen($strValues ?? '')) - 2, strlen($strValues ?? '')))) . ')';
            return Database::execute($strInsertSQL, []);
        } else {
            Error::form($strError);
            return false;
        }
    }
    /**
    * Sendmail
    *
    */
    public function SendMail()
    {
        global $rs;
        $strFilename = "";
        $Excelobj = null;
        $blnHaveData = null;
        $rs = Database::openRS('SELECT * from ' . $strTable, $conn_string, adOpenForwardOnly, adLockReadOnly);
        $ExcelObj = new ExcelExport();
        $ExcelObj->SkipFields();
        strSkipFields();
        $ExcelObj->Format = $intFormat;
        // if specified use these fields otherwise use default values of the class
        if ($txtConvertFieldsToFirstUpper !== '') {
            $ExcelObj->ConvertFirstUpperFields($txtConvertFieldsToFirstUpper);
        }
        // if specified use these fields otherwise use default values of the class
        if ($txtConvertFieldsToUpper !== '') {
            $ExcelObj->ConvertUpperFields($txtConvertFieldsToUpper);
            require_once __DIR__ . '/../../library/library.inc';

        }
        $blnHaveData = $ExcelObj->DoExport($rs);
        $strFileName = $ExcelObj->Filename();
        $ExcelObj = null;
        $rs = null;
        if ($this->SendTheMail($blnHaveData, $strFilename)) {
            if (Application::get('local', '')) {
                echo '<HR>Records would be deleted, skipped for local testing purposes!';
            } else {
                Database::execute('DELETE FROM ' . $strTable, []);
            }
        } else {
            echo 'Kan de verzamel e-mail niet versturen?!';
        }
    }
    /**
    * Convertfieldstoupper
    *
    */
    public function ConvertFieldsToUpper($sFieldList)
    {
        $this->txtconvertfieldstoupper = $sFieldList;
    }
    /**
    * Convertfieldstofirstupper
    *
    */
    public function ConvertFieldsToFirstUpper($sFieldList)
    {
        $this->txtconvertfieldstofirstupper = $sFieldList;
    }
    /**
    * Skipfields
    *
    */
    public function SkipFields($sFields)
    {
        if ($sFields !== '') {
            $this->arrskipfields = Arr::splitAlways($sFields, ',');
            $this->strskipfields = $sFields;
        }
    }
    /**
    * Sendthemail
    *
    */
    public function SendTheMail($blnHaveData, $strFilename)
    {
        global $mailer;
        $mailer = null;
        $Mailer = new LibMailer();
        $mailer->AddRecipients($mailTo);
        if ($mailCC !== '') {
            $mailer->AddRecipientsCC($mailCC);
        }
        $mailer->AddRecipientsBCC($mailBCC);
        $mailer->Subject = $txtSubject;
        $mailer->UseTemplate = false;
        if ($blnHaveData) {
            $mailer->Body = $this->txthavedata . PHP_EOL;
            $mailer->addAttachment($strFilename);
        } else {
            $mailer->Body = $this->txthavenodata . PHP_EOL;
        }
        return $mailer->Send();
        $Mailer = null;
    }
    /**
    *   Construct
    */
    public function __construct()
    {
        // database data
        $this->strtable = 'tblInfoAanvragen';
        // mail settings
        $this->mailto = 'webmaster@toolsonline.nl';
        // to make sure it reaches a person if not specified
        $this->mailcc = '';
        $this->mailbcc = 'diederik@toolsonline.nl';
        // connection, geen separate conn_info; fall-back naar conn_data!
        $this->conn_string = Application::get('conn_info', '');
        if ($this->conn_string == "") {
            $this->conn_string = 'data';
        }
        // texts
        $this->txthavedata = 'Zie voor alle nieuwe aanvragen het bijgevoegde document. ';
        $this->txthavenodata = 'Er waren geen nieuwe aanvragen sinds de laatst verstuurde e-mail. ';
        $this->txtsubject = 'Aanvragen website ' . Application::get('company', '');
        // default format to HTML
        $this->intformat = EXCEL_FORMAT_HTML;
        // field conversion defaults
        $this->txtconvertfieldstoupper = '';
        $this->txtconvertfieldstofirstupper = '';
        $this->strskipfields = '';
        $this->blncreatefields = true;
        // ------------------------------------------------------------------------------------------------------------
        // Public Properties
        // ------------------------------------------------------------------------------------------------------------
        // Public functions
        // Free format database save
        // Generic function to send the content of a specified table to a mail recipient in Excel format
        // Allows a comma-separated list of fields that convert to uppercase
        // passed onto the Excel function and handled there
        // Allows a comma-separated list of fields that convert to First Capitalised
        // passed onto the Excel function and handled there
        // Provide a comma delimited list of fields to skip
        // ------------------------------------------------------------------------------------------------------------
        // Private Functions
        // ------------------------------------------------------------------------------------------------------------
        // Private Properties
        // ------------------------------------------------------------------------------------------------------------
        // Class functions
    }
    /**
    *   Destruct
    */
    public function __destruct()
    {
        // nothing to do here for now.
    }
}
?>
