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
     * @param string $details Technische toelichting (databasemelding, SQL) — staat achter
     *                        "Toon details" en gaat mee met de kopieerknop
     * @return void
     */
    public static function show(string $message, string $details = ''): void
    {
        self::renderDialog('Er is een fout opgetreden', $message, true, true, $details);
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
     * @param string $details Technische toelichting achter "Toon details" (zie show())
     * @return void
     */
    public static function form(string $message, string $details = ''): void
    {
        $language = Application::get('mod_language', '');

        if ($language === 'UK') {
            $title = 'The form has not been completed:';
        } else {
            $title = 'Het formulier is niet volledig of niet correct ingevuld:';
        }

        self::renderDialog($title, $message, true, true, $details);
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
    /**
     * Copy-to-clipboard control, ported from the ASP original
     * (library/lib_error.inc :: lib_error_CopyButton).
     *
     * It walks the rendered card and copies what is actually ON it — including
     * comment nodes, which is where the extra diagnostics live — instead of a
     * string baked at render time. That way the operator always copies exactly
     * what they are looking at.
     *
     * Het loopgebied is .lib-error-copy (de cel met de melding), niet de hele
     * kaart. Op de hele kaart lopen leverde "Er is een fout opgetreden" en
     * "Terug" mee in het plakresultaat — de kop en de knop zijn scherm, geen
     * foutmelding. Ontbreekt die haak (een oudere kaart in de pagina), dan valt
     * hij terug op de kaart zelf, zodat er altijd íets gekopieerd wordt.
     *
     * Self-contained by necessity: this card shows up on pages whose stylesheet
     * and scripts may never have loaded. Hence the inline handler, inline SVG,
     * and a textarea+execCommand fallback for non-secure contexts (plain http on
     * a dev host) where navigator.clipboard is undefined.
     */
    private static function copyButtonHtml(): string
    {
        $html = '<button type="button" title="Kopieer foutmelding naar klembord" onclick="libErrorCopy(this);return false;"'
            . ' style="position:absolute;top:6px;right:8px;background:rgba(255,255,255,0.2);border:1px solid rgba(255,255,255,0.55);border-radius:4px;color:#ffffff;width:22px;height:22px;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;padding:0;line-height:0">'
            . '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            . '<rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>'
            . '<path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>'
            . '</svg></button>';

        $html .= '<script>window.libErrorCopy=window.libErrorCopy||function(b){'
            . "var c=b.closest?b.closest('.lib-error-card'):null;"
            . "if(!c){var p=b.parentNode;while(p){if(p.className&&(' '+p.className+' ').indexOf(' lib-error-card ')>-1){c=p;break;}p=p.parentNode;}}"
            . 'if(!c)return;'
            . "var s=c.querySelector?c.querySelector('.lib-error-copy'):null;if(s){c=s;}"
            . "var skip=function(n){var p=n.parentNode;while(p&&p!==c){var nm=p.nodeName;if(nm==='SCRIPT'||nm==='STYLE'||nm==='NOSCRIPT')return 2;p=p.parentNode;}return 1;};"
            . "var t='',w=document.createTreeWalker(c,132,skip,false),n;"
            . "while(n=w.nextNode()){if(n.nodeType===8)t+='\\n'+n.nodeValue+'\\n';else t+=n.nodeValue;}"
            . "t=t.replace(/\\u00a0/g,' ').replace(/[ \\t]+\\n/g,'\\n').replace(/\\n{3,}/g,'\\n\\n').trim();"
            . "function ok(){var o=b.innerHTML;b.innerHTML='&#10003;';b.title='Gekopieerd';setTimeout(function(){b.innerHTML=o;b.title='Kopieer foutmelding naar klembord';},1200);}"
            . "function fb(){var a=document.createElement('textarea');a.value=t;a.style.position='fixed';a.style.left='-9999px';document.body.appendChild(a);a.select();try{document.execCommand('copy');ok();}catch(e){}document.body.removeChild(a);}"
            . 'if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(t).then(ok,fb);}else{fb();}'
            . '};</script>';

        return $html;
    }

    private static function renderDialog(string $title, string $message, bool $showBackButton, bool $makeSureVisible, string $details = ''): void
    {
        // Prevent duplicate errors
        if (self::$lastError === $message) {
            return;
        }
        self::$lastError = $message;

        Response::write(PHP_EOL . PHP_EOL . '<!-- An error occured -->' . PHP_EOL . PHP_EOL);

        // Format message
        $message = self::format($message);

        // Markup mirrors the ASP original (library/lib_error.inc ::
        // internal_errordialog): .lib-error-card wrapper, red h3 header holding
        // the title plus the copy button, 24px body. Everything is inline-styled
        // for the same reason it was there — this card has to render on a page
        // whose stylesheet never loaded, which is exactly when errors appear.
        //
        // One deliberate deviation: position:fixed + transform-centring instead
        // of the original's position:relative/top:35%/margin:auto. The original
        // got trapped inside any positioned or overflow:hidden parent it
        // happened to be echoed into, and then nobody saw the error at all.
        $position = $makeSureVisible
            ? 'position:fixed;left:50%;top:50%;transform:translate(-50%,-50%);z-index:2147483647;'
            : 'position:relative;margin:auto;';

        // Het lettertype hoort op de KAART, niet alleen op de kop. Error::show()
        // schrijft geen <head> en geen stylesheet — de kaart landt vaak op een
        // pagina die nog niets had geladen — dus alles zonder eigen font-family
        // viel terug op de schreefletter van de browser: een Verdana-kop met een
        // Times-melding en een Times-knop eronder. Erven vanaf de kaart geeft
        // alle drie dezelfde letter, ook als er wél een stylesheet staat.
        $font = 'font-family:Verdana,Geneva,Arial,sans-serif;font-size:13px;color:#333333;';
        Response::write('</script><div class="lib-error-card" style="display:block;visibility:visible !important;border-radius:8px;max-width:800px;width:calc(100% - 32px);' . $position . $font . 'box-shadow:0 6px 28px rgba(0,0,0,0.35);background-color:#ffffff;border:1px solid #dddddd">');
        Response::write('<h3 style="position:relative;margin:0;font-family:inherit;font-size:18px;line-height:1.2;text-transform:initial;border-top-left-radius:8px;border-top-right-radius:8px;background-color:#E01F3D;color:#ffffff;display:block;padding:8px 48px 8px 24px">' . $title . self::copyButtonHtml() . '</h3>');
        Response::write('<div style="padding:24px">');
        // class="lib-error-copy": dit is het enige dat de kopieerknop meeneemt.
        // De technische toelichting (databasemelding, SQL) staat IN de kopieercel, maar
        // dichtgeklapt: de gebruiker ziet alleen de melding, en wie hem doorgeeft aan de
        // beheerder heeft met de kopieerknop de details toch mee — de wandeling van die
        // knop kijkt niet naar display:none. De schakelaar zelf staat buiten de cel, anders
        // kwam "Toon details" op het klembord. Net als de rest van de kaart zonder
        // stylesheet of jQuery: een inline handler.
        $details = trim($details);
        $detailsHtml = '';
        if ($details !== '') {
            $detailsHtml = '<div class="lib-error-details" style="display:none;margin-top:12px;padding:10px 12px;border:1px solid #dddddd;border-radius:6px;background-color:#f7f7f7;font-family:Consolas,Menlo,monospace;font-size:11px;line-height:16px;white-space:pre-wrap;word-break:break-word;color:#555555">'
                . htmlspecialchars($details, ENT_QUOTES, 'UTF-8') . '</div>';
        }
        Response::write('<table cellpadding=0 cellspacing=0 style="border-collapse:collapse;width:100%"><tr><td class="lib-error-copy" style="line-height:22px">' . $message . $detailsHtml . '</td></tr>');
        if ($details !== '') {
            Response::write('<tr><td style="padding-top:8px"><a href="#" class="lib-error-details-toggle" style="font-size:12px;color:#555555" '
                . "onclick=\"var d=this.parentNode.parentNode.parentNode.querySelector('.lib-error-details');var open=d.style.display==='none';d.style.display=open?'block':'none';this.textContent=open?'Verberg details':'Toon details';return false;\">Toon details</a></td></tr>");
        }

        // Back button
        if ($showBackButton) {
            Response::write('<tr><td colspan=9 align=center style="padding-top:16px"><a class="button GenButton FormBack" href="javascript:history.go(-1)">Terug</a><br>&nbsp;</td></tr>');
        }

        Response::write('</table></div></div>');
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

