<?php
// For compatibility reasons (older sites do not use the class so i created rewrote this function as a wrapper function of the class)
/**
* Sendmail
*
*/
use App\Library\Application;
use App\Library\Arr;
use App\Library\Request;
use App\Library\Server;
use App\Library\Str;

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

/**
 * LibMailer — thin wrapper around App\Library\Email.
 *
 * Historically this was a stand-alone PHPMailer sender converted from VBScript,
 * riddled with $GLOBALS state. Most of its "smart" logic (template wrapping,
 * fonts, reply-to, custom SMTP port) was dead: those variables were declared in
 * the constructor but never imported into Send(), so it effectively just posted
 * the raw body over SMTP. That also meant it bypassed the App\Library\Email
 * afterSend hook, so none of these mails were archived in tblEmailLog.
 *
 * This is now a faithful wrapper: it keeps the exact same public API (the
 * property setters via magic __set/__get and the recipient/attachment/Send
 * methods) but on Send() it builds an App\Library\Email and delegates. That
 * unifies the mail pipeline (one code path), and every mail now flows through
 * the afterSend hook -> EmailLogService, so it's logged and re-sendable.
 *
 * Behaviour kept identical on purpose: the body is sent WITHOUT template
 * wrapping (setUseTemplate(false)), matching what the old class actually did.
 * Test-env redirect is App\Library\Email's canonical implementation. On local
 * this class renders its own classic on-screen preview (ShowPreview) rather than
 * delegating, so the familiar layout is preserved. An optional Reply-To address
 * ($mailer->replyTo) is passed through to Email::setReplyTo() when set.
 */
class LibMailer
{
    // Public properties — preserved for API/backward compatibility. Callers set
    // these directly (e.g. $m->Body = ..., case-insensitively via __set below).
    public $strBaseStyle;
    public $strError = '';
    public $blnLogging = true;
    public $subject;
    public $body;
    public $useTemplate;
    public $from;
    public $fromName;
    public $replyTo;   // optional Reply-To address (empty = none)
    public $header;

    // Instance state (replaces the old cross-request $GLOBALS soup).
    private array $_to = [];
    private array $_cc = [];
    private array $_bcc = [];
    private array $_attachments = [];
    private array $_seen = [];      // lower-cased addresses already added (dedupe across to/cc/bcc)
    private bool $_sent = false;

    // Case-insensitive property access (VBScript compatibility).
    private $_propMap = null;
    private function _getPropMap() {
        if ($this->_propMap === null) {
            $this->_propMap = [];
            foreach (['strBaseStyle','strError','blnLogging','subject','body','useTemplate','from','fromName','replyTo','header'] as $prop) {
                $this->_propMap[strtolower($prop)] = $prop;
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

    public function __construct() {}
    public function __destruct() {}

    /** Kept for API compatibility — the direct-queue mechanism was already disabled. */
    public function UseDirectQueue() {}

    public function AddAttachment($sFilename)
    {
        $sFilename = trim((string)$sFilename);
        if ($sFilename !== '' && $this->CheckSend()) {
            $this->_attachments[] = $sFilename;
        }
    }

    /** Add a recipient, de-duplicated across To/CC/BCC (matching the old behaviour). */
    private function _add(array &$list, $strToEMail): void
    {
        $strToEMail = Str::trim((string)$strToEMail);
        if ($strToEMail === '' || !$this->CheckSend()) return;
        $key = strtolower($strToEMail);
        if (isset($this->_seen[$key])) return;
        $this->_seen[$key] = true;
        $list[] = $strToEMail;
    }

    public function AddRecipient($strToEMail)    { $this->_add($this->_to, $strToEMail); }
    public function AddRecipientCC($strToEMail)  { $this->_add($this->_cc, $strToEMail); }
    public function AddRecipientBCC($strToEMail) { $this->_add($this->_bcc, $strToEMail); }

    public function AddRecipients($strToEMail)    { foreach (Arr::splitAlways($strToEMail, ';') as $e) $this->AddRecipient($e); }
    public function AddRecipientsCC($strToEMail)  { foreach (Arr::splitAlways($strToEMail, ';') as $e) $this->AddRecipientCC($e); }
    public function AddRecipientsBCC($strToEMail) { foreach (Arr::splitAlways($strToEMail, ';') as $e) $this->AddRecipientBCC($e); }

    /**
     * Build an App\Library\Email and send it. Returns true on success.
     */
    public function Send()
    {
        if (!$this->CheckSend()) return false;

        $subject = (string)($this->subject ?? '');
        $body    = (string)($this->body ?? '');

        // Legacy "<header>" placeholder: replaced by the header (if set) or the
        // subject — same as the old class did on its (only) reachable path.
        $headerVal = ($this->header !== null && $this->header !== '') ? (string)$this->header : $subject;
        $body = str_ireplace('<header>', $headerVal, $body);

        [$from, $fromName] = $this->_resolveFrom();

        // Local/simulation mode: render the classic on-screen preview (same layout
        // as the original LibMailer) and stop — no SMTP, no logging, exactly as it
        // behaved on local before. We do this here rather than delegating to
        // App\Library\Email::showPreview() so the familiar preview is preserved.
        if (Application::get('local', false)) {
            $this->ShowPreview($subject, $body, $from, $fromName);
            $this->_sent = true;
            return true;
        }

        if (!class_exists(\App\Library\Email::class)) {
            $this->strError = 'App\\Library\\Email not available';
            return false;
        }

        try {
            $email = new \App\Library\Email();
            // Preserve the old effective behaviour: no template wrapping. (The old
            // class never actually applied a template.) Enabling it is a separate,
            // deliberate change.
            $email->setUseTemplate(false);
            $email->setSubject($subject);
            $email->setBody($body);
            $email->setFrom($from, $fromName);

            $replyTo = trim((string)($this->replyTo ?? ''));
            if ($replyTo !== '') { $email->setReplyTo($replyTo); }

            foreach ($this->_to as $e)  { $email->addRecipient($e); }
            foreach ($this->_cc as $e)  { $email->addRecipientCC($e); }
            foreach ($this->_bcc as $e) { $email->addRecipientBCC($e); }
            foreach ($this->_attachments as $f) {
                if (is_file($f)) { $email->addAttachment($f); }
            }

            // App\Library\Email handles env (simulate on local / test-env redirect)
            // and invokes the afterSend hook -> EmailLogService (archive + resend).
            $ok = $email->send();
            if (!$ok) {
                $this->strError = $email->getError();
            }
            $this->_sent = $ok;
            return $ok;
        } catch (\Throwable $e) {
            $this->strError = $e->getMessage();
            return false;
        }
    }

    /** Resolve the effective From address/name: instance value, else config, else a safe default. */
    private function _resolveFrom(): array
    {
        $from = trim((string)($this->from ?? ''));
        if ($from === '') {
            $from = trim((string)Application::get('email_from', '')) ?: 'webmaster@stenversonline.nl';
        }
        $fromName = trim((string)($this->fromName ?? ''));
        if ($fromName === '') {
            $fromName = trim((string)Application::get('company', '')) ?: $from;
        }
        return [$from, $fromName];
    }

    /**
     * Render the mail as an on-screen preview table (the classic LibMailer layout,
     * used in local/simulation mode). Args default to the current instance state so
     * direct callers can still invoke $mailer->ShowPreview() with no arguments.
     */
    public function ShowPreview($subject = null, $body = null, $from = null, $fromName = null)
    {
        if ($subject === null) $subject = (string)($this->subject ?? '');
        if ($body === null)    $body    = (string)($this->body ?? '');
        if ($from === null || $fromName === null) {
            [$rf, $rfn] = $this->_resolveFrom();
            if ($from === null)     $from = $rf;
            if ($fromName === null) $fromName = $rfn;
        }
        $to  = implode(' ', $this->_to);
        $cc  = implode(' ', $this->_cc);
        $bcc = implode(' ', $this->_bcc);

        echo '<table cellpadding=8 cellspacing=0 border=2 bordercolor=blue style=margin:4px><tr bgcolor=#CCCCCC><td>';
        echo '<table cellpadding=4 cellspacing=0>';
        echo '<tr><td>From</td><td><span style="font-weight:600;">' . $fromName . ' (' . $from . ')</span></td></tr>';
        if ($to !== '')  { echo '<tr><td>To</td><td><span style="font-weight:600;">' . $to . '</span></td></tr>'; }
        if ($cc !== '')  { echo '<tr><td>CC</td><td><span style="font-weight:600;">' . $cc . '</span></td></tr>'; }
        if ($bcc !== '') { echo '<tr><td>BCC</td><td><span style="font-weight:600;">' . $bcc . '</span></td></tr>'; }
        if (($rt = trim((string)($this->replyTo ?? ''))) !== '') {
            echo '<tr><td>Reply-To</td><td><span style="font-weight:600;">' . $rt . '</span></td></tr>';
        }
        echo '<tr><td>Subject</td><td><span style="font-weight:600;">' . $subject . '</span></td></tr>';
        if (!empty($this->_attachments)) {
            echo '<tr><td>Attachment(s)</td><td><span style="font-weight:600;">';
            foreach ($this->_attachments as $f) {
                $url = Request::currentDomain() . str_ireplace(Server::mapPath('/'), '', $f);
                echo '<a target=_blank href=' . $url . '>' . htmlspecialchars($f) . '</a> ';
            }
            echo '</span></td></tr>';
        }
        echo '</table></td></tr>';
        echo '<tr><td colspan=2 bgcolor=white style="padding:12px">' . $body . '</td></tr>';
        echo '</table>';
    }

    /** True while nothing has been sent yet (guards double-send like the original). */
    public function CheckSend()
    {
        return !$this->_sent;
    }
}
