<?php

namespace App\Library;

/**
 * Error Helper Class
 *
 * Provides user-friendly error handling, formatting, and display.
 * Integrates lib_error.inc functionality with modern ErrorHandler.
 *
 * Usage:
 *   Error::format($message) - Format technical database errors
 *   Error::page($title, $message, $showBack) - Display error page
 *   Error::show($message) - Display generic error with back button
 *   Error::report($message, $sql, $connection) - Report database error (with email)
 *   Error::form($message) - Display form validation error
 *   Error::ictAlert($title, $message) - Display ICT alert error
 */
class Error
{
    /**
     * Last error message displayed (prevents duplicates)
     * @var string
     */
    private static string $lastError = '';

    /**
     * Whether to send error emails
     * @var bool
     */
    private static bool $sendMail = true;

    /**
     * Whether verbose error reporting is enabled
     * @var bool
     */
    private static ?bool $verbose = null;

    /**
     * Initialize error settings
     */
    private static function init(): void
    {
        if (self::$verbose !== null) {
            return;
        }

        // Enable verbose mode in test/development environments
        $env = Application::get('omgeving', 'P');
        self::$verbose = in_array(strtoupper($env), ['L', 'O', 'T']);

        // Check if we're in a test environment path (for legacy compatibility)
        if (!self::$verbose) {
            $phpSelf = Request::server('PHP_SELF', '');
            self::$verbose = stripos($phpSelf, '/cma/') !== false;
        }

        // Email setting from config
        self::$sendMail = Application::get('error_send_mail', true);
    }

    /**
     * Format technical database error messages into user-friendly Dutch messages
     *
     * Maps VBScript lib_error_FormatMessage()
     *
     * @param string $message Raw error message
     * @return string Formatted user-friendly message
     */
    public static function format(string $message): string
    {
        $formatted = $message;

        // Remove verbose database engine names
        $formatted = str_ireplace('Microsoft JET ', '', $formatted);
        $formatted = str_ireplace('De Microsoft Jet-database-engine kan de invoertabel of -query', 'Tabel', $formatted);
        $formatted = str_ireplace(' niet vinden.', ' niet gevonden.', $formatted);
        $formatted = str_ireplace('(Microsoft JET Database Engine)', '', $formatted);
        $formatted = str_ireplace('The Microsoft Jet database engine ', 'The database ', $formatted);
        $formatted = str_ireplace('(Microsoft OLE DB Provider for SQL Server)', '', $formatted);
        $formatted = str_ireplace('(Microsoft OLE DB Driver for SQL Server)', '', $formatted);
        $formatted = str_ireplace('[Microsoft OLE DB Driver for SQL Server]', '', $formatted);
        $formatted = str_ireplace('[Microsoft][ODBC SQL Server Driver]', '', $formatted);
        $formatted = str_ireplace('You attempted to open a database that is already opened exclusively by', 'Database is in gebruik door', $formatted);

        // Handle SQL Server REFERENCE constraint errors
        // Pattern: The DELETE statement conflicted with the REFERENCE constraint "...". The conflict occurred in database "...", table "dbo.tblXXX", column '...'
        $pattern = '/The DELETE statement conflicted with the REFERENCE constraint[^.]*\\.\\s*The conflict occurred in database[^,]*, table\\s*"([^"]*)",/i';
        if (preg_match($pattern, $formatted, $matches)) {
            if (isset($matches[1])) {
                $tableName = $matches[1];
                $formatted = 'Het record kan niet worden verwijderd omdat er gerelateerde gegevens staan in de tabel' . str_replace(' tbl', ' ', str_replace('dbo.', '', ' ' . $tableName)) . '.';
            }
        }

        // Handle MS Access/Jet REFERENCE constraint errors
        // Pattern: The record cannot be deleted or changed because table 'tblXXX' includes related records
        $pattern = "/The record cannot be deleted or changed because table\\s*'([^']*)'\\s*includes related records/i";
        if (preg_match($pattern, $formatted, $matches)) {
            if (isset($matches[1])) {
                $tableName = $matches[1];
                $formatted = 'Het record kan niet worden verwijderd omdat er gerelateerde gegevens staan in de tabel' . str_replace(' tbl', ' ', ' ' . $tableName) . '.';
            }
        }

        // Handle SQL Server UNIQUE INDEX constraint errors
        // Pattern: Cannot insert duplicate key in object '...' with unique index 'tablename$indexname'. The duplicate key value is (value)
        $pattern = "/Cannot insert duplicate key.*with unique index\\s*'[^\$]*\\\$([^']*)'.*The duplicate key value is\\s*\\(([^\\)]*)\\)/i";
        if (preg_match($pattern, $formatted, $matches)) {
            if (isset($matches[1]) && isset($matches[2])) {
                $indexName = $matches[1];
                $keyValue = $matches[2];
                $formatted = 'Er bestaat al een record met de waarde <b>' . $keyValue . '</b> in <b>' . $indexName . '</b>.';
            }
        }

        // Handle MS Access/Jet UNIQUE INDEX constraint errors
        // Pattern: duplicate values in the index, primary key, or relationship
        if (stripos($formatted, 'duplicate values in the index') !== false ||
            stripos($formatted, 'duplicate key in index') !== false ||
            stripos($formatted, 'would create duplicate values') !== false) {
            $formatted = 'Er bestaat al een record met deze waarde. Duplicaten zijn niet toegestaan.';
        }

        // Handle other common patterns
        $formatted = str_ireplace('&lt;br&gt;', '<br>', $formatted);

        return $formatted;
    }

    /**
     * Display error page with HTML wrapper
     *
     * Maps VBScript lib_ErrorPage()
     *
     * @param string $title Error title/header
     * @param string $message Error message
     * @param bool $showBackButton Whether to show back button
     * @return void
     */
    public static function page(string $title, string $message, bool $showBackButton = true): void
    {
        Response::write('<html><head><title>Er is een fout opgetreden</title>');
        Response::write('<style>td,body,h3,ul{font-family:Verdana;font-size:13px}</style>');
        Response::write('<meta http-equiv=Content-Type content=text/html;charset=iso-8859-1>');
        Response::write('</head><body bgcolor=#ffffff style=margin:0px marginwidth=0 marginheight=0><br><br>');

        self::renderDialog($title, $message, $showBackButton, true);

        Response::write('</html></body>');
    }

    /**
     * Display generic error with back button
     *
     * Maps VBScript lib_Error()
     *
     * @param string $message Error message
     * @return void
     */
    public static function show(string $message): void
    {
        self::renderDialog('Er is een fout opgetreden', $message, true, true);
    }

    /**
     * Display error for ICT alerts
     *
     * Maps VBScript lib_ErrorPage_ictAlert()
     *
     * @param string $title Error title
     * @param string $message Error message
     * @param bool $showBackButton Whether to show back button
     * @return void
     */
    public static function ictAlert(string $title, string $message, bool $showBackButton = true): void
    {
        self::renderDialog($title, $message, $showBackButton, true);
    }

    /**
     * Report database error with email notification
     *
     * Maps VBScript lib_ReportError()
     *
     * @param string $message Error message
     * @param string $sql SQL query that caused error (optional)
     * @param mixed $connection Database connection (optional)
     * @return void
     */
    public static function report(string $message, string $sql = '', $connection = null): void
    {
        self::init();

        $errorText = '';

        // Get PHP error information if available
        $lastError = error_get_last();
        if ($lastError && $lastError['type'] !== E_NOTICE) {
            $errorText .= 'Er is een fout opgetreden, voor details zie hieronder:<P>';
            $errorText .= 'Error Number=' . $lastError['type'] . '<P>';
            $errorText .= 'Error Descr.=' . $lastError['message'] . '<P>';
            $errorText .= 'Source=' . $lastError['file'] . '<P>';
        }

        // Get connection errors if connection provided
        if (is_object($connection) && method_exists($connection, 'errorInfo')) {
            $errorInfo = $connection->errorInfo();
            if ($errorInfo && $errorInfo[0] !== '00000') {
                $errorText .= 'Er is een probleem met een SQL:<BR><br>';
                $errorText .= '<span style="font-weight:600">' . ($errorInfo[2] ?? 'Unknown error') . '</span><BR>';
            }
        }

        // Add SQL if provided
        if ($sql !== '') {
            $errorText .= htmlspecialchars($sql, ENT_QUOTES, 'UTF-8');
        }

        // Format messages
        $errorText = self::format($errorText);
        $message = self::format($message);

        // Display error on screen (with limited info in production)
        $displaySql = self::$verbose && stripos($errorText, $sql) === false ? '<br>' . $sql : '';
        self::page('Oeps', $errorText . $displaySql, true);

        // Write error to console in verbose mode
        if (self::$verbose) {
            $jsError = json_encode($errorText);
            Response::write('<script>console.error(' . $jsError . ')</script>');
        }

        // Send error email
        if (self::$sendMail && $errorText !== '') {
            self::sendErrorEmail($errorText);
        }
    }

    /**
     * Report error for ICT alerts with email
     *
     * Maps VBScript lib_ReportError_ictAlert()
     *
     * @param string $message Error message
     * @return void
     */
    public static function reportIctAlert(string $message): void
    {
        self::init();

        $showDetails = Application::get('test', false);
        self::ictAlert('Foutmelding', '<pre>' . $message . '</pre>', true);

        // Write error to console in verbose mode
        if (self::$verbose) {
            $jsError = json_encode($message);
            Response::write('<script>console.error(' . $jsError . ')</script>');
        }

        // Always send email for ICT alerts
        if ($message !== '') {
            self::sendErrorEmail($message, 'app_beheerder_email');
        }
    }

    /**
     * Display form validation error
     *
     * Maps VBScript lib_FormError()
     *
     * @param string $message Validation error message
     * @return void
     */
    public static function form(string $message): void
    {
        $language = Application::get('mod_language', '');

        if ($language === 'UK') {
            $title = 'The form has not been completed:';
        } else {
            $title = 'Het formulier is niet volledig of niet correct ingevuld:';
        }

        self::renderDialog($title, $message, true, true);
    }

    /**
     * Render error dialog HTML
     *
     * Maps VBScript internal_errordialog()
     *
     * @param string $title Dialog title
     * @param string $message Error message
     * @param bool $showBackButton Whether to show back button
     * @param bool $makeSureVisible Whether to force visibility with positioning
     * @return void
     */
    /**
     * Copy-to-clipboard control for the error card.
     *
     * Self-contained by necessity: the card is echoed into a page that may be
     * half-rendered, so there is no stylesheet, no jQuery and possibly no
     * <head> to hook into. Hence an inline handler, an inline SVG icon, and a
     * textarea+execCommand fallback for the non-secure contexts (plain http on
     * a dev host) where navigator.clipboard is undefined.
     */
    private static function copyButtonHtml(string $title, string $message): string
    {
        // The clipboard gets the plain text, not the HTML: strip tags, decode
        // entities, and collapse the whitespace that the markup introduced.
        $plain = html_entity_decode(strip_tags(str_replace(['<br>', '<BR>', '<P>', '<p>'], "\n", $title . "\n\n" . $message)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $plain = trim(preg_replace("/[ \t]+/", ' ', $plain));
        // Escaped once, when the whole handler goes into the attribute below.
        $js = json_encode($plain, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $onclick = "var t=" . $js . ";var b=this;"
            . "var done=function(){b.title='Gekopieerd';b.style.opacity='0.55';setTimeout(function(){b.title='Kopieer deze foutmelding';b.style.opacity='1'},1500)};"
            . "if(navigator.clipboard&&window.isSecureContext){navigator.clipboard.writeText(t).then(done)}"
            . "else{var a=document.createElement('textarea');a.value=t;a.style.position='fixed';a.style.opacity='0';document.body.appendChild(a);a.select();try{document.execCommand('copy');done()}catch(e){}document.body.removeChild(a)}";

        return '<a href="javascript:void(0)" title="Kopieer deze foutmelding" style="display:inline-block;width:22px;height:22px;line-height:0;cursor:pointer;text-decoration:none" onclick="' . htmlspecialchars($onclick, ENT_QUOTES, 'UTF-8') . '">'
            . '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            . '<rect x="9" y="9" width="13" height="13" rx="2"></rect>'
            . '<path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>'
            . '</svg></a>';
    }

    private static function renderDialog(string $title, string $message, bool $showBackButton, bool $makeSureVisible): void
    {
        // Prevent duplicate errors
        if (self::$lastError === $message) {
            return;
        }
        self::$lastError = $message;

        Response::write(PHP_EOL . PHP_EOL . '<!-- An error occured -->' . PHP_EOL . PHP_EOL);

        // Format message
        $message = self::format($message);

        // Make dialog visible with absolute positioning
        if ($makeSureVisible) {
            // position:fixed (not absolute) so the dialog escapes any positioned/overflow
            // parent table or div it happens to be echoed inside; transform-centering keeps
            // it centred regardless of its own size, and the max z-index puts it above all.
            Response::write('</script><div style="display:block; visibility:visible !important; border-radius:8px; position:fixed;left:50%;top:50%;transform:translate(-50%,-50%);z-index:2147483647;box-shadow:0 6px 28px rgba(0,0,0,0.35)">');
        }

        // Error dialog HTML. Everything is inline-styled on purpose: this card
        // has to render on a page whose stylesheet may never have loaded — that
        // is precisely when errors show up.
        Response::write('<table cellspacing=0 border=0 cellpadding=1 style="width:380px;background-color:#ffffff;border:1px solid #dddddd;box-shadow:8px 8px 8px rgba(128,128,128,.3); border-radius:8px"><tr>');
        Response::write('<td style="background-color:#E01F3D;min-height:24px;border-top-left-radius:8px;border-top-right-radius:8px"><table width=100% cellspacing=2 cellpadding=0><tr><td align=left><font style="font-family:Trebuchet MS;font-size:15px;line-height:24px;font-weight:100;color:#dddddd">&nbsp;');
        Response::write(PHP_EOL . $title . '&nbsp;&nbsp;</font></td>');
        Response::write('<td align=right style="width:34px">' . self::copyButtonHtml($title, $message) . '</td></tr></table></td></tr>');
        Response::write('<tr><td style="padding:10px;line-height:22px" data-error-body>' . $message . '</td></tr>');

        // Back button
        if ($showBackButton) {
            $backFunction = 'javascript:history.go(-1)';
            Response::write('<tr><td colspan=9 align=center><a class="button GenButton FormBack" href="' . $backFunction . '">Terug</a><br>&nbsp;</td></tr>');
        }

        Response::write('</table>');

        if ($makeSureVisible) {
            Response::write('</div>');
        }
    }

    /**
     * Send error email to developer/administrator
     *
     * @param string $errorMessage Error message
     * @param string $emailConfigKey Config key for recipient email (default: app_developer_email)
     * @return void
     */
    private static function sendErrorEmail(string $errorMessage, string $emailConfigKey = 'app_developer_email'): void
    {
        try {
            $to = Application::get($emailConfigKey, '');
            if ($to === '') {
                $to = 'diederik@stenversonline.nl';
            }

            $mail = new Email();
            $mail->setFrom($to, 'Website ' . Request::server('SERVER_NAME', ''));
            $mail->addRecipient($to);
            $mail->setSubject('Foutmelding website ' . Request::server('SERVER_NAME', ''));

            $body = $errorMessage . '<br>';
            $body .= 'Referer: ' . Request::server('HTTP_REFERER', '') . '<br><br>';
            $body .= 'Request URI: ' . Request::server('REQUEST_URI', '') . '<br>';
            $body .= 'User Agent: ' . Request::server('HTTP_USER_AGENT', '') . '<br>';
            $body .= 'Remote Address: ' . Request::server('REMOTE_ADDR', '') . '<br>';

            $mail->setBody($body);
            $mail->send();
        } catch (\Exception $e) {
            // Silently fail - don't let email errors break error reporting
            error_log('Failed to send error email: ' . $e->getMessage());
        }
    }

    /**
     * Clear the last error (useful for testing)
     *
     * @return void
     */
    public static function clearLastError(): void
    {
        self::$lastError = '';
    }

    /**
     * Set whether to send error emails
     *
     * @param bool $enabled
     * @return void
     */
    public static function setSendMail(bool $enabled): void
    {
        self::$sendMail = $enabled;
    }

    /**
     * Set verbose mode
     *
     * @param bool $enabled
     * @return void
     */
    public static function setVerbose(bool $enabled): void
    {
        self::$verbose = $enabled;
    }
}

