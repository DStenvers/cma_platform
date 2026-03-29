<?php
/**
 * 500 Error Handler
 *
 * PHP replacement for ASP's Server.GetLastError() based 500.asp.
 * Uses PHP's error_get_last() to retrieve error information.
 * The ErrorHandler class (loaded via bootstrap) handles most errors;
 * this page is the last-resort fallback for fatal errors that bypass it.
 */
use App\Library\Application;
use App\Library\Request;
use App\Library\Response;
use App\Library\Email;

http_response_code(500);

function main()
{
    Response::noCache();

    $strMethod = Request::server("REQUEST_METHOD");
    $queryString = Request::server('QUERY_STRING');
    $isHttps = strtoupper(Request::server("HTTPS")) === "ON";
    $strPage = ($isHttps ? "https://" : "http://")
        . Request::server("SERVER_NAME")
        . Request::server("SCRIPT_NAME")
        . ($queryString ? "?" . $queryString : "");
    $strPage = urldecode($strPage);

    // Detect hacking attempts - redirect to home
    $hackPatterns = [' and user', 'char(', '/proc', '@@version', 'etc/', 'declare', 'select '];
    foreach ($hackPatterns as $pattern) {
        if (stripos($strPage, $pattern) !== false) {
            Response::redirect("/");
        }
    }

    // Fix &amp; in URLs
    if (strpos($strPage, '&amp;') !== false) {
        Response::redirect(str_replace("&amp;", "&", $strPage));
    }

    // Get PHP error info (replaces ASP's Server.GetLastError)
    $phpError = error_get_last();
    $errorTypes = [
        E_ERROR => 'Fatal Error', E_WARNING => 'Warning', E_PARSE => 'Parse Error',
        E_NOTICE => 'Notice', E_CORE_ERROR => 'Core Error', E_COMPILE_ERROR => 'Compile Error',
        E_RECOVERABLE_ERROR => 'Recoverable Error', E_DEPRECATED => 'Deprecated',
    ];

    $strUserAgent = Request::server("HTTP_USER_AGENT");

    // SQL injection denial
    $sPageQueryString = urldecode(Request::server('QUERY_STRING'));
    $isHackAttempt = stripos($sPageQueryString, "http") !== false
        || stripos($sPageQueryString, " and ") !== false
        || stripos($sPageQueryString, "INFORMATION_SCHEMA.TABLES") !== false
        || stripos($strUserAgent, "libwww-perl") !== false
        || stripos($strUserAgent, "python") !== false;

    if ($isHackAttempt) {
        exit;
    }

    // Build error detail string
    $strError = "\r\n" . '<table [style] cellpadding=3 cellspacing=5>';
    $strError .= '<tr valign=top><td width=1%>Pagina</td><td width=99%>';
    if ($strMethod !== "GET") {
        $strError .= htmlspecialchars($strMethod) . " ";
    }
    if ($strMethod === "POST") {
        $strError .= strlen(file_get_contents('php://input')) . " bytes naar ";
    }
    $strError .= htmlspecialchars($strPage);
    if ($strMethod !== "POST") {
        $strError .= ' (<a href="' . htmlspecialchars($strPage) . '">Bekijk pagina</a>)';
    }
    $strError .= '</td></tr>' . "\r\n";

    // Error details from PHP's error_get_last()
    if ($phpError) {
        $errorType = $errorTypes[$phpError['type']] ?? 'Error (' . $phpError['type'] . ')';
        $strError .= '<tr valign=top><td width=1% nowrap>Soort fout</td><td width=99%>' . "\r\n";
        $strError .= htmlspecialchars($errorType) . "<br>";
        $strError .= "<b>" . htmlspecialchars($phpError['message']) . "</b><br>";
        if (!empty($phpError['file'])) {
            $strError .= "<br>" . htmlspecialchars($phpError['file']);
            if (!empty($phpError['line'])) {
                $strError .= "<br>line <b>" . $phpError['line'] . "</b>";
            }
        }
        $strError .= '</td></tr>' . "\r\n";
    }

    $strError .= '<tr valign=top><td width=1%>Referrer</td><td width=99%>' . htmlspecialchars(Request::server("HTTP_REFERER")) . '</td></tr>' . "\r\n";
    $strError .= '<tr valign=top><td width=1%>IP&nbsp;address</td><td width=99%>' . htmlspecialchars(Request::server("REMOTE_ADDR")) . '</td></tr>' . "\r\n";
    $strError .= '<tr valign=top><td width=1%>Browser</td><td width=99%>' . htmlspecialchars($strUserAgent) . '</td></tr>' . "\r\n";
    $strError .= '</table>';

    // Render page
    echo '<!DOCTYPE html>';
    echo '<html>';
    echo '<head>';
    echo '<style>
    body, td, p, table { font-family: Tahoma, Verdana, sans-serif; font-size: 13px; }
    hr { height: 1px; }
    a:link { font: 8pt/11pt Verdana; color: #FF0000; }
    a:visited { font: 8pt/11pt Verdana; color: #4e4e4e; }
    </style>';
    echo '<meta name="robots" content="noindex">';
    echo '<title>De pagina kan niet worden weergegeven / 500</title>';
    echo '</head>';
    echo '<body style="background-color:#efefef">';
    echo '<div style="font-family:Tahoma;font-size:18px;font-weight:100;margin-left:8px;letter-spacing:.5px;color:#555">Er is een fout opgetreden</div>';

    // Show verbose errors for non-production environments
    $environment = Application::get('omgeving', 'P');
    if (in_array($environment, ['L', 'O', 'T'])) {
        echo str_replace(
            "[style]",
            'style="border:1px solid #ccc; background-color:#ffffff; color:#000000; margin:20px; padding:10px;" ',
            $strError
        );
    } else {
        echo '<div style="padding:30px;text-align:center"><a href="/" style="font-family:Verdana; font-size:11px">Ga naar de beginpagina</a></div>';
        echo '<!--' . $strError . '-->';
    }

    echo '<br><br><input type="button" value="Ga naar de vorige pagina" onclick="history.go(-1)">';

    // Send error email if not a search engine
    if (!empty($strUserAgent)) {
        try {
            $devEmail = Application::get("app_developer_email", "diederik@stenversonline.nl");
            if (class_exists('\App\Library\Email')) {
                Email::send([
                    'to' => $devEmail,
                    'from' => $devEmail,
                    'subject' => "Foutmelding website " . strtoupper(Request::server("SERVER_NAME")),
                    'body' => $strError,
                ]);
            }
        } catch (\Throwable $e) {
            // Silently fail - already in error handling
        }
    }

    echo '</body>';
    echo '</html>';
}

main();
?>