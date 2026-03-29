<?php

namespace App\Library;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Email Helper Class
 *
 * Modern PHP wrapper for libMailer functionality using PHPMailer
 * Maps VBScript libMailer class to PHP with improved error handling
 *
 * Usage:
 *   $email = new Email();
 *   $email->setSubject('Test Email');
 *   $email->setBody('Hello World');
 *   $email->addRecipient('user@example.com');
 *   $email->send();
 *
 * Or fluent interface:
 *   Email::create()
 *       ->setSubject('Test')
 *       ->setBody('Hello')
 *       ->addRecipient('user@example.com')
 *       ->send();
 */
class Email
{
    /**
     * @var PHPMailer The underlying PHPMailer instance
     */
    private $mailer;

    /**
     * @var string Email body content
     */
    private $body = '';

    /**
     * @var string Email subject
     */
    private $subject = '';

    /**
     * @var string Email header (used in templates)
     */
    private $header = '';

    /**
     * @var string From email address
     */
    private $fromEmail = '';

    /**
     * @var string From name
     */
    private $fromName = '';

    /**
     * @var string Reply-To email address
     */
    private $replyTo = '';

    /**
     * @var string Email template HTML
     */
    private $template = '';

    /**
     * @var bool Whether to use template
     */
    private $useTemplate = false;

    /**
     * @var bool Whether to use CMA template
     */
    private $cmaTemplate = false;

    /**
     * @var bool Whether email has been sent (prevent double send)
     */
    private $mailSent = false;

    /**
     * @var string Last error message
     */
    private $error = '';

    /**
     * @var array List of attachments
     */
    private $attachments = [];

    /**
     * @var array Track recipients to avoid duplicates
     */
    private $recipientTracker = [];

    /**
     * @var bool Whether to log email operations
     */
    private $logging = true;

    /**
     * @var bool Simulation mode (don't actually send)
     */
    private $simulation = false;

    /**
     * @var callable|null Optional callback invoked after each send attempt.
     * Receives an array with keys: to, cc, bcc, from, fromName, subject, body,
     * attachments, success, error, simulation.
     */
    public static $afterSend = null;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->mailer = new PHPMailer(true);
        $this->initialize();
    }

    /**
     * Static factory method for fluent interface
     *
     * @return Email
     */
    public static function create(): Email
    {
        return new self();
    }

    /**
     * Initialize email settings from Application config
     */
    private function initialize(): void
    {
        // Get configuration from Application
        $mailServer = Application::get('mail_server', 'localhost');
        $mailPort = Application::get('mail_server_port', 25);
        $mailUsername = Application::get('mail_username', '');
        $mailPassword = Application::get('mail_password', '');

        // Configure SMTP
        $this->mailer->isSMTP();
        $this->mailer->Host = $mailServer;
        $this->mailer->Port = $mailPort;
        $this->mailer->CharSet = 'UTF-8';

        // Authentication if credentials provided
        if (!empty($mailUsername)) {
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = $mailUsername;
            $this->mailer->Password = $mailPassword;
        }

        // Default from address
        $defaultFrom = Application::get('email_fromname', 'webmaster@stenversonline.nl');
        $company = Application::get('company', 'RINO amsterdam');

        $this->fromEmail = $defaultFrom;
        $this->fromName = $company;

        // Template settings
        $this->template = Application::get('email_template', '');
        $this->useTemplate = !empty($this->template);

        // Check if in local/test environment
        $this->simulation = Application::get('local', false);

        // Default BCC to admin in all environments
        $adminEmail = Application::get('app_beheerder_email', '');
        if (!empty($adminEmail)) {
            $this->addRecipientBCC($adminEmail);
        }
    }

    /**
     * Set email body
     *
     * @param string $body HTML body content
     * @return Email
     */
    public function setBody(string $body): Email
    {
        if ($this->checkSend()) {
            // Replace line breaks with <BR>
            $body = str_replace("\r\n", '<BR>', $body);

            // Fix bullet character encoding (Amsterdam fix)
            $body = str_replace('â€¢', '•', $body);

            // Convert HTML entities
            $this->body = $this->htmlCharacterCompile($body);
        }

        return $this;
    }

    /**
     * Get email body
     *
     * @return string
     */
    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * Set email subject
     *
     * @param string $subject
     * @return Email
     */
    public function setSubject(string $subject): Email
    {
        if ($this->checkSend()) {
            // Capitalize first letter and trim
            $subject = $this->firstUpper(trim($subject));

            // Add environment prefix
            $env = Application::get('omgeving', 'O');
            if ($env === 'T') {
                $subject = 'TEST-OMGEVING: ' . $subject;
            } elseif ($env === 'A') {
                $subject = 'ACCEPTATIE-OMGEVING: ' . $subject;
            }

            // Fix bullet character
            $subject = str_replace('â€¢', '•', $subject);

            $this->subject = $subject;
        }

        return $this;
    }

    /**
     * Get email subject
     *
     * @return string
     */
    public function getSubject(): string
    {
        return $this->subject;
    }

    /**
     * Set email header (used in templates)
     *
     * @param string $header
     * @return Email
     */
    public function setHeader(string $header): Email
    {
        if ($this->checkSend()) {
            $this->header = $this->firstUpper(trim($header));
        }

        return $this;
    }

    /**
     * Set from email address
     *
     * @param string $email
     * @param string|null $name Optional name (if null, uses email)
     * @return Email
     */
    public function setFrom(string $email, ?string $name = null): Email
    {
        if ($this->checkSend()) {
            $this->fromEmail = $email;
            $this->fromName = $name ?? $email;
        }

        return $this;
    }

    /**
     * Set from name
     *
     * @param string $name
     * @return Email
     */
    public function setFromName(string $name): Email
    {
        if ($this->checkSend()) {
            $this->fromName = $name;
        }

        return $this;
    }

    /**
     * Set reply-to address
     *
     * @param string $email
     * @return Email
     */
    public function setReplyTo(string $email): Email
    {
        if ($this->checkSend()) {
            $this->replyTo = $email;
        }

        return $this;
    }

    /**
     * Set custom template
     *
     * @param string $template HTML template with [[body]] placeholder
     * @return Email
     */
    public function setTemplate(string $template): Email
    {
        if ($this->checkSend()) {
            $this->template = $template;
        }

        return $this;
    }

    /**
     * Set whether to use template
     *
     * @param bool $use
     * @return Email
     */
    public function setUseTemplate(bool $use): Email
    {
        if ($this->checkSend()) {
            $this->useTemplate = $use;
        }

        return $this;
    }

    /**
     * Set whether to use CMA template
     *
     * @param bool $use
     * @return Email
     */
    public function setCMATemplate(bool $use): Email
    {
        if ($this->checkSend()) {
            $this->cmaTemplate = $use;
        }

        return $this;
    }

    /**
     * Add attachment
     *
     * @param string $filepath Path to file
     * @return Email
     */
    public function addAttachment(string $filepath): Email
    {
        if (!empty($filepath) && $this->checkSend()) {
            $this->attachments[] = $filepath;
        }

        return $this;
    }

    /**
     * Add single recipient (TO)
     *
     * @param string $email
     * @param string $name Optional name
     * @return Email
     */
    public function addRecipient(string $email, string $name = ''): Email
    {
        $email = trim($email);

        if ($this->checkSend() && !empty($email)) {
            $key = '|' . $email . '|';
            if (!in_array($key, $this->recipientTracker)) {
                try {
                    $this->mailer->addAddress($email, $name ?: $email);
                    $this->recipientTracker[] = $key;
                } catch (PHPMailerException $e) {
                    $this->error = 'Invalid recipient: ' . $e->getMessage();
                }
            }
        }

        return $this;
    }

    /**
     * Add multiple recipients (TO) - semicolon delimited
     *
     * @param string $emails Semicolon-separated email addresses
     * @return Email
     */
    public function addRecipients(string $emails): Email
    {
        $recipients = explode(';', $emails);
        foreach ($recipients as $email) {
            $this->addRecipient(trim($email));
        }

        return $this;
    }

    /**
     * Add CC recipient
     *
     * @param string $email
     * @param string $name Optional name
     * @return Email
     */
    public function addRecipientCC(string $email, string $name = ''): Email
    {
        $email = trim($email);

        if ($this->checkSend() && !empty($email)) {
            $key = '|' . $email . '|';
            if (!in_array($key, $this->recipientTracker)) {
                try {
                    $this->mailer->addCC($email, $name ?: $email);
                    $this->recipientTracker[] = $key;
                } catch (PHPMailerException $e) {
                    $this->error = 'Invalid CC recipient: ' . $e->getMessage();
                }
            }
        }

        return $this;
    }

    /**
     * Add multiple CC recipients - semicolon delimited
     *
     * @param string $emails
     * @return Email
     */
    public function addRecipientsCC(string $emails): Email
    {
        $recipients = explode(';', $emails);
        foreach ($recipients as $email) {
            $this->addRecipientCC(trim($email));
        }

        return $this;
    }

    /**
     * Add BCC recipient
     *
     * @param string $email
     * @param string $name Optional name
     * @return Email
     */
    public function addRecipientBCC(string $email, string $name = ''): Email
    {
        $email = trim($email);

        if ($this->checkSend() && !empty($email)) {
            $key = '|' . $email . '|';
            if (!in_array($key, $this->recipientTracker)) {
                try {
                    $this->mailer->addBCC($email, $name ?: $email);
                    $this->recipientTracker[] = $key;
                } catch (PHPMailerException $e) {
                    $this->error = 'Invalid BCC recipient: ' . $e->getMessage();
                }
            }
        }

        return $this;
    }

    /**
     * Add multiple BCC recipients - semicolon delimited
     *
     * @param string $emails
     * @return Email
     */
    public function addRecipientsBCC(string $emails): Email
    {
        $recipients = explode(';', $emails);
        foreach ($recipients as $email) {
            $this->addRecipientBCC(trim($email));
        }

        return $this;
    }

    /**
     * Send the email
     *
     * @return bool True on success, false on failure
     */
    public function send(): bool
    {
        if (!$this->checkSend()) {
            return false;
        }

        // Check for empty body/subject
        $trimmedBody = trim(str_replace(['<br>', '<br/>'], '', $this->body));
        if (empty($trimmedBody) || empty($this->subject)) {
            return true; // Silently skip empty emails
        }

        // Apply template
        $finalBody = $this->applyTemplate();

        // Capture original recipients before test environment may clear them
        $originalTo = $this->mailer->getToAddresses();
        $originalCc = $this->mailer->getCcAddresses();
        $originalBcc = $this->mailer->getBccAddresses();

        // Handle test environment
        if (Application::get('test', false)) {
            $finalBody = $this->wrapTestEnvironmentWarning($finalBody);
        }

        // Set from address
        $this->ensureValidFromAddress();

        try {
            // Configure PHPMailer
            $this->mailer->Subject = $this->subject;
            $this->mailer->Body = $finalBody;
            $this->mailer->AltBody = strip_tags($finalBody);
            $this->mailer->isHTML(true);

            // Set from (sanitize name: strip HTML tags and decode entities like &bull; to •)
            $cleanFromName = html_entity_decode(strip_tags($this->fromName), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $cleanFromName = preg_replace('/\s+/', ' ', trim($cleanFromName));
            $this->mailer->setFrom($this->fromEmail, $cleanFromName);

            // Set reply-to if provided
            if (!empty($this->replyTo)) {
                $this->mailer->addReplyTo($this->replyTo);
            }

            // Add attachments
            foreach ($this->attachments as $filepath) {
                if (file_exists($filepath)) {
                    $this->mailer->addAttachment($filepath);
                }
            }

            // Send or simulate
            if ($this->simulation) {
                $this->showPreview();
                $this->mailSent = true;
                $result = true;
            } else {
                // Actually send
                $result = $this->mailer->send();
                $this->mailSent = $result;
            }

            // Invoke afterSend hook with original recipients (before test env cleared them)
            $this->invokeAfterSend($result, $cleanFromName, $originalTo, $originalCc, $originalBcc);

            return $result;
        } catch (PHPMailerException $e) {
            $this->error = $e->getMessage();

            // Log error if logging enabled
            if ($this->logging) {
                error_log('Email send failed: ' . $this->error);
            }

            // Invoke afterSend hook with error
            $this->invokeAfterSend(false, $cleanFromName ?? $this->fromName, $originalTo, $originalCc, $originalBcc);

            return false;
        }
    }

    /**
     * Invoke the afterSend callback with email data
     */
    private function invokeAfterSend(bool $success, string $cleanFromName, array $originalTo, array $originalCc, array $originalBcc): void
    {
        if (self::$afterSend === null) return;

        try {
            call_user_func(self::$afterSend, [
                'to' => $originalTo,
                'cc' => $originalCc,
                'bcc' => $originalBcc,
                'from' => $this->fromEmail,
                'fromName' => $cleanFromName,
                'subject' => $this->subject,
                'body' => $this->body,
                'attachments' => $this->attachments,
                'success' => $success,
                'error' => $this->error,
                'simulation' => $this->simulation,
            ]);
        } catch (\Exception $e) {
            error_log('Email afterSend hook error: ' . $e->getMessage());
        }
    }

    /**
     * Get last error message
     *
     * @return string
     */
    public function getError(): string
    {
        return $this->error;
    }

    /**
     * Apply email template
     *
     * @return string
     */
    private function applyTemplate(): string
    {
        $body = $this->body;

        if ($this->cmaTemplate) {
            $body = $this->applyCMATemplate($body);
        } elseif ($this->useTemplate && !empty($this->template)) {
            $body = str_replace('[[body]]', $body, $this->template);
        }

        // Replace header placeholder
        $headerValue = !empty($this->header) ? $this->header : $this->subject;
        $body = str_replace('<header>', $headerValue, $body);

        return $body;
    }

    /**
     * Apply CMA-specific email template
     *
     * @param string $body
     * @return string
     */
    private function applyCMATemplate(string $body): string
    {
        $basePath = Application::get('base_path', '/');
        $domain = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $imagePath = 'http://' . $domain . $basePath . 'cma/images/';

        $template = '<HTML><BODY style="margin:0px"><STYLE>P,TD,DIV,BODY{font-family:Arial;color:#333333;font-size:13px} A,A:visited,A:hover,A:link{font-family:Arial;font-size:13px;text-decoration:none}</STYLE>';
        $template .= '<table cellpadding="0" cellspacing="0" style="width:405px">';
        $template .= '<tr>';
        $template .= '<td><img src="' . $imagePath . 'corner_lt.gif" width="4" height="3"></td>';
        $template .= '<td><img src="' . $imagePath . 'shadow_top.gif" width="394" height="3"></td>';
        $template .= '<td><img src="' . $imagePath . 'corner_rt.gif" width="7" height="3"></td>';
        $template .= '</tr>';
        $template .= '<tr>';
        $template .= '<td rowspan="2" valign="top" background="' . $imagePath . 'shadow_left_fill.gif"><img src="' . $imagePath . 'shadow_left_start.gif" width="4" height="41"></td>';
        $template .= '<td><img src="' . $imagePath . 'log_groen.jpg" width="394" height="107"></td>';
        $template .= '<td rowspan="2" valign="top" background="' . $imagePath . 'shadow_right_fill.gif"><img src="' . $imagePath . 'shadow_right_start.gif" width="7" height="66"></td>';
        $template .= '</tr>';
        $template .= '<tr>';
        $template .= '<td style="padding-left:50px;padding-top:20px;padding-bottom:20px;padding-right:20px;">[[body]]</td>';
        $template .= '</tr>';
        $template .= '<tr>';
        $template .= '<td><img src="' . $imagePath . 'corner_lb.gif" width="4" height="7"></td>';
        $template .= '<td><img src="' . $imagePath . 'shadow_bot.gif" width="394" height="7"></td>';
        $template .= '<td><img src="' . $imagePath . 'corner_rb.gif" width="7" height="7"></td>';
        $template .= '</tr>';
        $template .= '</table>';
        $template .= '</BODY></HTML>';

        return str_replace('[[body]]', $body, $template);
    }

    /**
     * Wrap email body with test environment warning
     *
     * @param string $body
     * @return string
     */
    private function wrapTestEnvironmentWarning(string $body): string
    {
        $to = [];
        $cc = [];
        $bcc = [];

        // Extract recipients from PHPMailer
        foreach ($this->mailer->getToAddresses() as $addr) {
            $to[] = $addr[0];
        }
        foreach ($this->mailer->getCcAddresses() as $addr) {
            $cc[] = $addr[0];
        }
        foreach ($this->mailer->getBccAddresses() as $addr) {
            $bcc[] = $addr[0];
        }

        $warning = '<table style="background-color:#eeeeee">';
        $warning .= '<tr><td colspan="2">Onderstaande mail zou in productie verzonden worden naar:</td></tr>';
        $warning .= '<tr><td>To:</td><td>' . implode(', ', $to) . '</td></tr>';
        $warning .= '<tr><td>CC:</td><td>' . implode(', ', $cc) . '</td></tr>';
        $warning .= '<tr><td>BCC:</td><td>' . implode(', ', $bcc) . '</td></tr>';
        $warning .= '</table>';

        // Clear recipients and send to admin only
        $this->mailer->clearAddresses();
        $this->mailer->clearCCs();
        $this->mailer->clearBCCs();

        $adminEmail = Application::get('app_beheerder_email', '');
        if (!empty($adminEmail)) {
            $this->mailer->addBCC($adminEmail);
        }

        return $warning . $body;
    }

    /**
     * Ensure valid from address (RINO-specific logic)
     */
    private function ensureValidFromAddress(): void
    {
        $company = Application::get('company', '');

        // RINO-specific rules
        if (substr($company, 0, 4) === 'RINO' && substr($this->fromEmail, -12) !== 'rinogroep.nl') {
            $this->fromEmail = 'noreply@rino.nl';
            $this->fromName = $company;
        }

        // Fallback if no from address
        if (empty($this->fromEmail)) {
            $this->fromName = $company;
            if (substr($company, 0, 4) === 'RINO') {
                $this->fromEmail = 'noreply@rino.nl';
            } else {
                $this->fromEmail = 'diederik@stenversonline.nl';
            }
        }
    }

    /**
     * Show email preview (simulation mode)
     */
    private function showPreview(): void
    {
        echo '<table cellpadding="8" cellspacing="0" border="2" bordercolor="blue" style="margin:4px"><tr bgcolor="#CCCCCC"><td>';
        echo '<table cellpadding="4" cellspacing="0">';
        echo '<tr><td>From</td><td><b>' . htmlspecialchars($this->fromName . ' (' . $this->fromEmail . ')') . '</b></td></tr>';

        foreach ($this->mailer->getToAddresses() as $addr) {
            echo '<tr><td>To</td><td><b>' . htmlspecialchars($addr[0]) . '</b></td></tr>';
        }
        foreach ($this->mailer->getCcAddresses() as $addr) {
            echo '<tr><td>CC</td><td><b>' . htmlspecialchars($addr[0]) . '</b></td></tr>';
        }
        foreach ($this->mailer->getBccAddresses() as $addr) {
            echo '<tr><td>BCC</td><td><b>' . htmlspecialchars($addr[0]) . '</b></td></tr>';
        }

        echo '<tr><td>Subject</td><td><b>' . htmlspecialchars($this->subject) . '</b></td></tr>';

        if (!empty($this->attachments)) {
            echo '<tr><td>Attachment(s)</td><td><b>';
            foreach ($this->attachments as $file) {
                echo htmlspecialchars(basename($file)) . '<br>';
            }
            echo '</b></td></tr>';
        }

        echo '</table></td></tr>';
        echo '<tr><td colspan="2" bgcolor="white" style="padding:12px">' . $this->body . '</td></tr>';
        echo '</table>';
    }

    /**
     * Check if email can still be modified (hasn't been sent yet)
     *
     * @return bool
     */
    private function checkSend(): bool
    {
        if ($this->mailSent) {
            $this->error = "Mail with subject '" . $this->subject . "' has already been sent!";
            return false;
        }

        return true;
    }

    /**
     * Capitalize first character of string
     *
     * @param string $str
     * @return string
     */
    private function firstUpper(string $str): string
    {
        if (empty($str)) {
            return $str;
        }

        return mb_strtoupper(mb_substr($str, 0, 1)) . mb_substr($str, 1);
    }

    /**
     * Compile HTML character entities
     *
     * @param string $html
     * @return string
     */
    private function htmlCharacterCompile(string $html): string
    {
        // This would contain the lib_HTML_CharacterCompile logic
        // For now, return as-is with basic entity encoding
        return $html;
    }
}

/**
 * Legacy wrapper functions for compatibility
 */

/**
 * Send email with template
 *
 * @param string $toEmail
 * @param string $toName
 * @param string $fromEmail
 * @param string $fromName
 * @param string $ccEmail
 * @param string $ccName
 * @param string $subject
 * @param string $body
 * @param string $attachmentLocation
 * @return string Error message (empty on success)
 */
function SendMail($toEmail, $toName, $fromEmail, $fromName, $ccEmail, $ccName, $subject, $body, $attachmentLocation = '')
{
    return internal_SendMail($toEmail, $toName, $fromEmail, $fromName, $ccEmail, $ccName, $subject, $body, $attachmentLocation, true);
}

/**
 * Send email without template
 *
 * @param string $toEmail
 * @param string $toName
 * @param string $fromEmail
 * @param string $fromName
 * @param string $ccEmail
 * @param string $ccName
 * @param string $subject
 * @param string $body
 * @param string $attachmentLocation
 * @return string Error message (empty on success)
 */
function SendMailNoTemplate($toEmail, $toName, $fromEmail, $fromName, $ccEmail, $ccName, $subject, $body, $attachmentLocation = '')
{
    return internal_SendMail($toEmail, $toName, $fromEmail, $fromName, $ccEmail, $ccName, $subject, $body, $attachmentLocation, false);
}

/**
 * Internal send mail implementation
 *
 * @param string $toEmail
 * @param string $toName
 * @param string $fromEmail
 * @param string $fromName
 * @param string $ccEmail
 * @param string $ccName
 * @param string $subject
 * @param string $body
 * @param string $attachmentLocation
 * @param bool $useTemplate
 * @return string Error message (empty on success)
 */
function internal_SendMail($toEmail, $toName, $fromEmail, $fromName, $ccEmail, $ccName, $subject, $body, $attachmentLocation, $useTemplate)
{
    try {
        $email = new Email();
        $email->setBody(str_replace("\r\n", '<BR>', $body))
              ->setSubject($subject)
              ->setFrom($fromEmail, $fromName)
              ->setUseTemplate($useTemplate)
              ->addRecipient($toEmail)
              ->addRecipientsCC($ccEmail);

        if (!empty($attachmentLocation)) {
            $email->addAttachment($attachmentLocation);
        }

        if (!$email->send()) {
            return $email->getError();
        }

        return '';
    } catch (\Exception $e) {
        return $e->getMessage();
    }
}
