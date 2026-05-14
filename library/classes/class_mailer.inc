<?php
// For compatibility reasons (older sites do not use the class so i created rewrote this function as a wrapper function of the class)
/**
* Sendmail
*
*/
use App\Library\Application;
use App\Library\Arr;
use App\Library\Error;
use App\Library\Request;
use App\Library\Server;
use App\Library\Str;
use App\Library\File;

/**
 * Sendmail
 */
function SendMail($strToEMail, $strToName, $strFromEMail, $strCCEMail, $strCCName, $strSubject, $strBody, $strAttachmentLocation)
{
    return internal_SendMail($strToEMail, $strToName, $strFromEMail, $strCCEMail, $strCCName, $strSubject, $strBody, $strAttachmentLocation, true);
}
// For compatibility reasons (older sites do not use the class so i created rewrote this function as a wrapper function of the class)
/**
* Sendmailnotemplate
*
*/
function SendMailNoTemplate($strToEMail, $strToName, $strFromEMail, $strCCEMail, $strCCName, $strSubject, $strBody, $strAttachmentLocation)
{
    return internal_SendMail($strToEMail, $strToName, $strFromEMail, $strCCEMail, $strCCName, $strSubject, $strBody, $strAttachmentLocation, false);
}
/**
* Internal Sendmail
*
*/
function internal_SendMail($strToEMail, $strToName, $strFromEMail, $strCCEMail, $strCCName, $strSubject, $strBody, $strAttachmentLocation, $blnTemplate)
{
    $sendmail_objMail = null;
    $strRetval = "";
    $sendmail_objMail = new LibMailer();
    $sendmail_objMail->body = str_replace(PHP_EOL, '<BR>', $strBody);
    $sendmail_objMail->subject = $strSubject;
    $sendmail_objMail->from = $strFromEMail;
    $sendmail_objMail->fromName = Application::get('company', '') ? Application::get('company', '') : $strFromEMail;
    $sendmail_objMail->useTemplate = $blnTemplate;
    $sendmail_objMail->AddRecipient($strToEMail);
    $sendmail_objMail->AddRecipientsCC($strCCEMail);
    $sendmail_objMail->AddAttachment($strAttachmentLocation);
    if (!$sendmail_objMail->Send()) {
        $strRetval = $sendmail_objMail->strError;
    }
    $sendmail_objMail = null;
    return $strRetval;
}
if (class_exists('LibMailer')) { return; }
class LibMailer
{
    public $strBaseStyle;
    public $strError;
    public $blnLogging;
    public $subject;
    public $body;
    public $useTemplate;
    public $from;
    public $fromName;
    public $header;
    // Case-insensitive property access (VBScript compatibility)
    private $_propMap;
    private function _getPropMap() {
        if ($this->_propMap === null) {
            $this->_propMap = [];
            foreach (array_keys(get_object_vars($this)) as $prop) {
                if ($prop !== '_propMap') $this->_propMap[strtolower($prop)] = $prop;
            }
        }
        return $this->_propMap;
    }
    public function __set($name, $value) {
        $map = $this->_getPropMap();
        $key = strtolower($name);
        if (isset($map[$key])) { $prop = $map[$key]; $this->$prop = $value; return; }
        $this->$name = $value;
    }
    public function __get($name) {
        $map = $this->_getPropMap();
        $key = strtolower($name);
        if (isset($map[$key])) { $prop = $map[$key]; return $this->$prop; }
        return null;
    }
    /**
    * Usedirectqueue
    *
    */
    public function UseDirectQueue()
    {
        // disabled!
    }
    /**
    * Addattachment
    *
    */
    public function AddAttachment($sFilename)
    {
        global $strAttachment;
        if ($sFilename != '') {
            if ($this->CheckSend()) {
                if ($strAttachment != '') {
                    $strAttachment = $strAttachment . ' ; ';
                }
                $strAttachment = $strAttachment . $sFilename;
            }
        }
    }
    /**
    * Addrecipient
    *
    */
    public function AddRecipient($strToEMail)
    {
        global $strRecipients, $strSaveTo;
        $strRecipients = $strRecipients ?? '';
        $strSaveTo = $strSaveTo ?? '';
        $strToEMail = Str::trim($strToEMail);
        if ($this->CheckSend()) {
            if ($strToEMail == '' || (($pos = stripos($strRecipients, '|' . $strToEMail . '|')) !== false)) {
            } else {
                $strSaveTo = ($strSaveTo == '' ? '' : $strSaveTo . ' ') . $strToEMail;
                $strRecipients = $strRecipients . '|' . $strToEMail . '|';
            }
        }
    }
    /**
    * Addrecipientcc
    *
    */
    public function AddRecipientCC($strToEMail)
    {
        global $strRecipients, $strSaveCC;
        $strRecipients = $strRecipients ?? '';
        $strSaveCC = $strSaveCC ?? '';
        $strToEMail = Str::trim($strToEMail);
        if ($this->CheckSend()) {
            if ($strToEMail == '' || (($pos = stripos($strRecipients, '|' . $strToEMail . '|')) !== false)) {
            } else {
                $strSaveCC = ($strSaveCC == '' ? '' : $strSaveCC . ' ') . $strToEMail;
                $strRecipients = $strRecipients . '|' . $strToEMail . '|';
            }
        }
    }
    /**
    * Addrecipientbcc
    *
    */
    public function AddRecipientBCC($strToEMail)
    {
        global $strRecipients, $strSaveBCC;
        $strRecipients = $strRecipients ?? '';
        $strSaveBCC = $strSaveBCC ?? '';
        $strToEMail = Str::trim($strToEMail);
        if ($this->CheckSend()) {
            if ($strToEMail == '' || (($pos = stripos($strRecipients, '|' . $strToEMail . '|')) !== false)) {
            } else {
                $strSaveBCC = ($strSaveBCC == '' ? '' : $strSaveBCC . ' ') . $strToEMail;
                $strRecipients = $strRecipients . '|' . $strToEMail . '|';
            }
        }
    }
    /**
    * Addrecipientsbcc
    *
    */
    public function AddRecipientsBCC($strToEMail)
    {
        $arrRecipients = Arr::splitAlways($strToEMail, ';');
        for ($t = 0; $t <= (count($arrRecipients) - 1); $t++) {
            $this->AddRecipientBCC($arrRecipients[$t]);
        }
    }
    /**
    * Addrecipientscc
    *
    */
    public function AddRecipientsCC($strToEMail)
    {
        $arrRecipients = Arr::splitAlways($strToEMail, ';');
        for ($t = 0; $t <= (count($arrRecipients) - 1); $t++) {
            $this->AddRecipientCC($arrRecipients[$t]);
        }
    }
    /**
    * Addrecipients
    *
    */
    public function AddRecipients($strToEMail)
    {
        $arrRecipients = Arr::splitAlways($strToEMail, ';');
        for ($t = 0; $t <= (count($arrRecipients) - 1); $t++) {
            $this->AddRecipient($arrRecipients[$t]);
        }
    }
    /**
    * Send
    *
    */
    public function Send()
    {
        // VBScript class-level variables stored in $GLOBALS by the converter
        global $strBody, $strSubject, $strSaveTo, $strSaveCC, $strSaveBCC,
               $strRecipients, $strAttachment, $blnMailisSend;
        // Sync instance properties to globals (VBScript Property Let mapped to public properties)
        if ($this->subject !== null) $strSubject = $this->subject;
        if ($this->body !== null) $strBody = $this->body;
        if ($this->from !== null) $strFromeMail = $this->from;
        if ($this->fromName !== null) $strFromName = $this->fromName;
        if ($this->useTemplate !== null) $blnUseTemplate = $this->useTemplate;
        if ($this->header !== null) $strHeader = $this->header;
        $strFramework = "";
        $sBasePath = null;
        $sBasePath = Request::currentDomain() . Application::get('base_path', '') . 'cma/images/';
        $strObject = "";
        $arrAttachments = array();
        $arrRec = array();
        $teller = null;
        $bretried = null;
        $bretried = false;
        if (!Application::get('local', '')) {
            $strFramework = '<HTML><BODY><STYLE>P,TD,DIV,BODY{font-family:' . $strFont . ';color:' . $strFontColor . ';font-size:' . $strfontSize . '} FIELDSET {PADDING:4px} LEGEND{margin:8px;margin-left:-8px;color:#444444} A,A:visited,a:hover,A:link{font-family:' . $strFont . ';font-size:' . $strfontSize . ';text-decoration:none}</STYLE>[[body]]</BODY></HTML>';
            if ($strBody == str_ireplace('[[body]]', '', $strFrameWork) || str_ireplace('<br/>', '', str_ireplace('<br>', '', Str::trim($strBody . ''))) == '' || str_ireplace('ACCEPTATIEOMGEVING:', '', $strSubject . '') == '' || str_ireplace('ACCEPTATIE-OMGEVING:', '', $strSubject . '') == '') {
            } else {
                if ($this->CheckSend()) {
                    if ($strRecipients == '' && $strSaveCC == '' && $strSaveBCC == '') {
                        Error::show('No recipients specified!');
                    }
                    $strFramework = '';
                    if ($blnUseTemplate) {
                        $strFrameWork = $strTemplate;
                    } elseif ($blnCMATemplate) {
                        $strFramework = '<HTML><BODY style=margin:0px><STYLE>P,TD,DIV,BODY{font-family:Arial;color:#333333;font-size:13px} A,A:visited,A:hover,A:link{font-family:Arial;font-size:13px;text-decoration:none}</STYLE><table cellpadding=0 cellspacing=0 style=width:405px><tr><td><img src=' . $sBasePath . 'corner_lt.gif width=4 height=3></td><td><img src=' . $sBasePath . 'shadow_top.gif width=394 height=3></td><td><img src=' . $sBasePath . 'corner_rt.gif width=7 height=3></td></tr><tr><td rowspan=2 valign=top background=images/shadow_left_fill.gif><img src=' . $sBasePath . 'shadow_left_start.gif width=4 height=41></td><td><img src=' . $sBasePath . 'log_groen.jpg width=394 height=107></td><td rowspan=2 valign=top background=' . $sBasePath . 'shadow_right_fill.gif><img src=' . $sBasePath . 'shadow_right_start.gif width=7 height=66></td></tr><tr><td style=padding-left:50px;padding-top:20px;padding-bottom:20px;padding-right:20px;>[[body]]</td></tr><tr><td><img src=' . $sBasePath . 'corner_lb.gif width=4 height=7></td><td><img src=' . $sBasePath . 'shadow_bot.gif width=394 height=7></td><td><img src=' . $sBasePath . 'corner_rb.gif width=7 height=7></td></tr></table></BODY></HTML>';
                    }
                    if ($strFrameWork == '') {
                        $strFramework = '[[body]]';
                    }
                    if (substr(Application::get('company', ''), 0, max(0, min(4, strlen(Application::get('company', ''))))) == 'RINO' && substr($strFromEMail, max(0, strlen($strFromEMail) - 12)) != 'rinogroep.nl') {
                        $strFromeMail = 'noreply@rino.nl';
                        $strFromName = Application::get('company', '');
                    }
                    if ($strFromeMail == "") {
                        $strFromName = Application::get('company', '');
                        if (substr(Application::get('company', ''), 0, max(0, min(4, strlen(Application::get('company', ''))))) == 'RINO') {
                            $strFromeMail = 'noreply@rino.nl';
                        } else {
                            $strFromeMail = 'diederik@stenversonline.nl';
                        }
                    }
                    if (trim($strBody . '') != '' && trim($strBody) != '<table cellpadding=0 cellspacing=0></table>') {
                        $strBody = str_replace('[[body]]', str_replace('<BR><BR><BR>', '<BR><BR>', $strBody), $strFrameWork);
                    } else {
                        $strBody = '';
                    }
                    if ($strHeader!= '') {
                        $strBody = str_ireplace('<header>', $strBody);
                    } else {
                        $strBody = str_ireplace('<header>', $strSubject, $strBody);
                    }
                    if (Application::get('test', '')) {
                        $strBody = '<table style="background-color:#eeeeee"><tr><td colspan=2>Onderstaande mail zou in productie verzonden worden naar: </td></tr><tr><td>To:</td><td>' . $strSaveTo . '</td></tr><tr><td>CC:</td><td>' . $strSaveCC . '</td></tr><tr><td>BCC:</td><td>' . $strSaveBCC . '</td></tr></table>' . $strBody;
                        $strSaveTO = '';
                        $strSaveCC = '';
                        $strSaveBCC = Application::get('app_beheerder_email', '');
                    }
                    $blnMailisSend = true;
                    if ($blnSimulation) {
                        $this->ShowPreview();
                    } else {
                        // Use PHPMailer instead of CDO/JMail COM objects
                        $objMail = new \PHPMailer\PHPMailer\PHPMailer(true);
                        try {
                            $objMail->CharSet = 'UTF-8';
                            $objMail->isHTML(true);

                            // SMTP configuration
                            $smtpServer = $strMailServer ?: Application::get('mail_server', '');
                            if ($smtpServer !== '' && $smtpServer !== 'localhost') {
                                $objMail->isSMTP();
                                $objMail->Host = $smtpServer;
                                $objMail->Port = $strMailServerPort ?: 25;
                                if (Application::get('mail_username', '') !== '') {
                                    $objMail->SMTPAuth = true;
                                    $objMail->Username = Application::get('mail_username', '');
                                    $objMail->Password = Application::get('mail_password', '');
                                }
                            }

                            // From
                            $objMail->setFrom($strFromeMail, $strFromName ?: '');
                            if ($strReplyTo != '') {
                                $objMail->addReplyTo($strReplyTo);
                            }

                            // Recipients
                            if ($strSaveTo != '') {
                                foreach (explode(' ', $strSaveTo) as $to) {
                                    if (trim($to) !== '') $objMail->addAddress(trim($to));
                                }
                            }
                            if ($strSaveCC != '') {
                                foreach (explode(' ', $strSaveCC) as $cc) {
                                    if (trim($cc) !== '') $objMail->addCC(trim($cc));
                                }
                            }
                            if ($strSaveBCC != '') {
                                foreach (explode(' ', $strSaveBCC) as $bcc) {
                                    if (trim($bcc) !== '') $objMail->addBCC(trim($bcc));
                                }
                            }

                            // Subject and body
                            $objMail->Subject = $strSubject;
                            $objMail->Body = $strBody;
                            $objMail->AltBody = strip_tags(str_ireplace(['<br>', '<br/>'], "\n", $strBody));

                            // Attachments
                            if ($strAttachment != '') {
                                $arrAttachments = Arr::splitAlways(str_replace(' ; ', ';', $strAttachment), ';');
                                for ($teller = 0; $teller <= (count($arrAttachments) - 1); $teller++) {
                                    if (trim($arrAttachments[$teller]) !== '' && file_exists($arrAttachments[$teller])) {
                                        $objMail->addAttachment($arrAttachments[$teller]);
                                    }
                                }
                            }

                            if (trim($strBody) !== '') {
                                $objMail->send();
                            }
                        } catch (\Exception $e) {
                            $blnMailisSend = false;
                            $this->strError = $objMail->ErrorInfo;
                            if (Application::get('test', '')) {
                                echo 'Mail error: ' . $objMail->ErrorInfo;
                            }
                        }
                    }
                }
                return $blnMailisSend;
            }
        }
    }
    /**
    * Showpreview
    *
    */
    public function ShowPreview()
    {
        global $strSubject, $strBody;
        echo '<table cellpadding=8 cellspacing=0 border=2 bordercolor=blue style=margin:4px><tr bgcolor=#CCCCCC><td>';
        echo '<table cellpadding=4 cellspacing=0>';
        echo '<tr><td>From</td><td><b>' . $strFromName . '(' . $strFromeMail . ')</td></tr>';
        if ($strSaveTo != '') {
            echo '<tr><td>To</td><td><b>' . $strSaveTo . '</td></tr>';
        }
        if ($strSaveCC != '') {
            echo '<tr><td>CC</td><td><b>' . $strSaveCC . '</td></tr>';
        }
        if ($strSaveBCC != '') {
            echo '<tr><td>BCC</td><td><b>' . $strSaveBCC . '</td></tr>';
        }
        echo '<tr><td>Subject</td><td><b>' . $strSubject . '</td></tr>';
        if ($strAttachment != '') {
            echo '<tr><td>Attachment(s)</td><td><b>';
            echo '<a target=_blank href=' . Request::currentDomain() . str_ireplace(Server::mapPath('/'), '', $strAttachment) . '>' . $strAttachment . '</a>';
            echo '</td></tr>';
        }
        echo '</table></td></tr>';
        echo '<tr><td colspan=2 bgcolor=white style="padding:12 12 12 12px">' . $strBody . '</td></tr>';
        echo '</table>';
    }
    /**
    * Checksend
    *
    */
    public function CheckSend()
    {
        global $strSubject, $blnMailisSend;
        if ($blnMailisSend) {
            Error::show("Mail with subject '" . $strSubject . "' has already been send!");
            return false;
        } else {
            return true;
        }
    }
    /**
    *   Construct
    */
    public function __construct()
    {
        $strBody = '';
        $strSubject = '';
        $strHeader = '';
        $strAttachment = '';
        // make sure there is a valid from address
        $strMailServer = '';
        if (Application::get('mail_server', '') != '') {
            $strMailServer = Application::get('mail_server', '');
        }
        $strMailServerPort = 25;
        if (Application::get('mail_server_port', '') != '') {
            $strMailServerPort = Application::get('mail_server_port', '');
        }
        $strFromeMail = 'webmaster@stenversonline.nl';
        // Application setting overrides default values
        if (Application::get('email_fromname', '') != '') {
            $strFromeMail = Application::get('email_fromname', '');
        }
        $strFromName = $strFromeMail;
        // there is a mail template but it has not been read into Application
        // normally you would do that at initialisation in global.asa, but at that point the server objects are not available
        if (Application::get('email_template_file', '') != '' && Application::get('email_template', '') == '') {
            if (File::exists(Application::get('email_template_file', ''))) {
            } else {
                echo 'kan m echt niet vinden..';
            }
        }
        $strTemplate = Application::get('email_template', '');
        $blnUseTemplate = $strTemplate != '';
        $blnCMATemplate = false;
        // Font specifications below are only used when no template is used
        $strFont = (Application::get('email_font', '') == '' ? 'Verdana' : Application::get('email_font', ''));
        $strFontColor = (Application::get('email_fontcolor', '') == '' ? '#000000' : Application::get('email_fontcolor', ''));
        $strFontSize = (Application::get('email_fontsize', '') == '' ? '11px' : Application::get('email_fontsize', ''));
        $strSaveBCC = Application::get('app_beheerder_email', '');
        $strRecipients = $strSaveBCC;
        $strSaveTo = '';
        $strSaveCC = '';
        $strToEmail = '';
        $strReplyTo = '';
        $blnLogging = true;
        $blnSimulation = Application::get('local', '');
        $blnMailisSend = false;
        // ------------------------------------------------------------------------------------------------------------
        // External requirements
        // - Lib_Error included beforehand
        // - JMail object installed on web server (can be found on www.dimac.net)
        // - SMTP server running in the server specified by the property MailServer, default set to the SMTP server on TIM
        // - Application("email_fromname")           - the sender name (optional)
        // - Application("email_MSPickupDirectory")  - NO LONGER USED
        // - Application("email_template")           - default template (include [[body]] where the body should reside)
        // - Application("email_font")               - font family
        // - Application("email_fontcolor")          - font color
        // - Application("email_fontsize")           - font size
        // Remark: if Local, simulation mode is used automatically, perhaps we should make a property of that
        // TODO: : Use HTML mail per default, convert into plain text as a service, if so: what to do about links?
        // Changelog
        // 14 april 2005   : Check double recipients and if mail has been send
        // 16 juni  2005   : Added attachment option
        // 15 augustus 2005: Added font and color option
        // 2 dec 2007      : Added CMA templates
        // ------------------------------------------------------------------------------------------------------------
        // Public Properties
        // ------------------------------------------------------------------------------------------------------------
        // Public Functions
        // used for bulk emailings!
        // Adds multiple BCC recipients ;-delimited
        // Adds multiple CC recipients ;-delimited
        // Adds multiple recipients ;-delimited
        // ------------------------------------------------------------------------------------------------------------
        // Reset global state for each new LibMailer instance
        global $objMail, $strBody, $strSubject, $strFromName, $strFromEMail,
               $strMailServer, $strMailServerPort, $strAttachment, $strFont,
               $strFontColor, $strfontSize, $strTemplate, $blnUseTemplate,
               $blnCMATemplate, $blnSimulation, $strSaveTo, $strSaveCC,
               $strSaveBCC, $strRecipients, $strReplyTo, $strHeader, $blnMailisSend;
        $objMail = null;
        $strBody = "";
        $strSubject = "";
        $strFromName = "";
        $strFromEMail = "";
        $strMailServer = "";
        $strMailServerPort = "";
        $strAttachment = "";
        $strFont = "";
        $strFontColor = "";
        $strfontSize = "";
        $strTemplate = "";
        $blnUseTemplate = null;
        $blnCMATemplate = null;
        $blnSimulation = null;
        $strSaveTo = "";
        $strSaveCC = "";
        $strSaveBCC = "";
        $strRecipients = "";
        $strReplyTo = "";
        $strHeader = "";
        $blnMailisSend = false;
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
    }
}
?>
