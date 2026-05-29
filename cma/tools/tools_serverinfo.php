<?php
use App\Library\Application;
use App\Library\Arr;
use App\Library\Email;
use Cma\ToolbarHelper;
use Cma\SecurityHelper;
use App\Library\Request;
use App\Library\Response;

require_once __DIR__ . '/../bootstrap.inc';

// =========================================================================
// Test-email handler — POST-and-Redirect-to-Get (so a refresh doesn't
// resend) lives here so it runs before any HTML output.
// =========================================================================
$testMailResult = null;
$envSwitchResult = null;
$emailAvailable = class_exists(\App\Library\Email::class);

// =========================================================================
// Env-switch handler — writes APP_ENVIRONMENT to the site-root .env file
// and redirects so the next request reads the new value. Developers only.
// Runs before any output so the Location header can fire.
// =========================================================================
if (Request::post('action', '') === 'switch_env' && SecurityHelper::isDeveloper()) {
    $target = strtoupper(trim((string)Request::post('target', '')));
    if (!in_array($target, ['O', 'L', 'T', 'A', 'P'], true)) {
        $envSwitchResult = ['ok' => false, 'msg' => 'Ongeldige doel-omgeving.'];
    } else {
        $envFile = dirname(__DIR__, 2) . '/.env';
        if (!is_file($envFile)) {
            $envSwitchResult = ['ok' => false, 'msg' => '<code>.env</code> bestaat niet op site-root (' . htmlspecialchars($envFile) . ').'];
        } elseif (!is_writable($envFile)) {
            $envSwitchResult = ['ok' => false, 'msg' => '<code>.env</code> is niet schrijfbaar — controleer rechten op ' . htmlspecialchars($envFile) . '.'];
        } else {
            $contents = (string)file_get_contents($envFile);
            $newLine  = 'APP_ENVIRONMENT=' . $target;
            $newContents = preg_match('/^APP_ENVIRONMENT=.*$/m', $contents)
                ? preg_replace('/^APP_ENVIRONMENT=.*$/m', $newLine, $contents)
                : rtrim($contents) . "\n" . $newLine . "\n";
            if (file_put_contents($envFile, $newContents) === false) {
                $envSwitchResult = ['ok' => false, 'msg' => 'Schrijven naar <code>.env</code> mislukt.'];
            } else {
                header('Location: tools_serverinfo.php?env_switched=' . urlencode($target));
                exit;
            }
        }
    }
}

// Pick up the success flash from the POST-Redirect-GET round-trip
if ($envSwitchResult === null && Request::query('env_switched', '') !== '') {
    $newEnv = strtoupper(trim((string)Request::query('env_switched', '')));
    if (in_array($newEnv, ['O', 'L', 'T', 'A', 'P'], true)) {
        $envSwitchResult = ['ok' => true, 'msg' => 'Omgeving omgeschakeld — <code>APP_ENVIRONMENT=' . $newEnv . '</code> staat nu in <code>.env</code>.'];
    }
}
if (Request::post('action', '') === 'send_test_mail' && SecurityHelper::isDeveloper()) {
    if (!$emailAvailable) {
        $testMailResult = ['ok' => false, 'msg' => 'App\\Library\\Email is niet beschikbaar op deze site. Voer <code>composer update stenversonline/platform</code> uit.'];
    } else {
        $to      = trim(Request::post('to', ''));
        $subject = trim(Request::post('subject', '')) ?: 'Test e-mail vanuit CMA';
        $body    = trim(Request::post('body', '')) ?: 'Dit is een test-e-mail vanuit de CMA Omgeving-tab.';
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $testMailResult = ['ok' => false, 'msg' => 'Geen geldig e-mailadres opgegeven.'];
        } else {
            try {
                $email = Email::create()
                    ->setSubject($subject)
                    ->setBody(nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8')))
                    ->addRecipient($to);
                $sent = $email->send();
                if ($sent) {
                    $sim = Application::get('local', false);
                    $testMailResult = ['ok' => true, 'msg' => $sim
                        ? 'E-mail gesimuleerd (local-mode actief — er is niets echt verzonden).'
                        : 'E-mail succesvol verzonden naar ' . htmlspecialchars($to) . '.'];
                } else {
                    $err = method_exists($email, 'getError') ? $email->getError() : '';
                    $testMailResult = ['ok' => false, 'msg' => 'Verzending mislukt' . ($err ? ': ' . htmlspecialchars($err) : '.')];
                }
            } catch (\Throwable $e) {
                $testMailResult = ['ok' => false, 'msg' => 'Verzending mislukt: ' . htmlspecialchars($e->getMessage())];
            }
        }
    }
}

/**
* Main
*/
function main(): void
{
    global $testMailResult;

    Response::noCache();
    cma_html_header('CMA - Server informatie');
    echo '<BODY class="contentbody tools tool-serverinfo" style="margin: 0;">';
    ToolbarHelper::report('Server informatie', false, false, false, false, 'Bekijk server en PHP configuratie');
    echo '<div id="c" class="tools">';

    // Build tabs array — Omgeving lands first so the most actionable
    // info (env summary, test-mail, env switcher) is the default view.
    $tabs = ['Omgeving', 'Applicatie instellingen'];
    if (SecurityHelper::isDeveloper()) {
        $tabs[] = 'PHP info';
    }

    echo '<style>cma-tabs [slot^="tab-"] { padding: 24px; }</style>';
    echo '<cma-tabs tabs="' . htmlspecialchars(json_encode($tabs)) . '">';

    // =====================================================================
    // Tab 0: Omgeving — env summary + test-mail + test/prod indicators
    //                  + environment switcher
    // =====================================================================
    echo '<div slot="tab-0">';
    renderEnvironmentTab($testMailResult, $envSwitchResult);
    echo '</div>';

    // Tab 1: Application settings (was tab-0)
    echo '<div slot="tab-1">';
    $AppVal = null;
    $strKey = "";
    echo '<table>';
    echo '<TR><TD colspan=2><h2><i>Applicatie settings</TD></TR>';
    foreach (Application::getAll() as $AppVal => $__value) {
        if (substr($AppVal, 0, max(0, min(5, strlen($AppVal)))) != 'conn_') {
            echo '<TR><TD>' . $AppVal . '</TD><TD><B>';
            if (Arr::isArray(Application::get($AppVal, ''))) {
                echo '{ Array }';
            } else {
                if (is_object(Application::get($AppVal, ''))) {
                    echo '{ Object }';
                } else {
                    echo Application::get($AppVal, '');
                }
            }
            echo '</TD></TR>';
        }
    }
    echo '<TR><TD colspan=2>&nbsp;</TD></TR>';
    echo '<TR><TD colspan=2><h2><i>Server instellingen</TD></TR>';
    // Intentionally iterates $_SERVER to display all server variables for diagnostics
    foreach ($_SERVER as $strKey => $value) {
        echo '<TR><TD>' . htmlspecialchars($strKey) . '</TD><TD><B>' . htmlspecialchars($value) . '</B></TD></TR>';
    }
    echo '</table>';
    echo '</div>';

    // Tab 2: PHP Info - Developer only (was tab-1)
    if (SecurityHelper::isDeveloper()) {
        echo '<div slot="tab-2">';

        // Show php.ini edit link in development environments (L=Local, O=Ontwikkeling, T=Test)
        $env = Application::get('omgeving', '');
        $isDev = in_array($env, ['L', 'O', 'T']);
        $phpIniPath = php_ini_loaded_file();
        $additionalInis = php_ini_scanned_files();
        $userIniFilename = ini_get('user_ini.filename');

        if ($isDev) {
            echo '<div style="margin-bottom:15px;padding:15px;background:var(--bg-surface-alt);border-radius:6px;">';

            // Main php.ini
            if ($phpIniPath) {
                $vscodeUrl = 'vscode://file/' . str_replace('\\', '/', $phpIniPath);
                echo '<div style="display:flex;align-items:center;gap:15px;margin-bottom:10px;">';
                echo '<div style="flex:1;">';
                echo '<span class="cma-tool__strong">php.ini:</span><br>';
                echo '<code style="font-size:var(--font-size-sm);">' . htmlspecialchars($phpIniPath) . '</code>';
                echo '</div>';
                echo '<a href="' . htmlspecialchars($vscodeUrl) . '" class="btn btn-primary" style="white-space:nowrap;text-decoration:none;">';
                echo '<span class="lnr lnr-pencil"></span> Open in VS Code</a>';
                echo '</div>';
            } else {
                echo '<div style="margin-bottom:10px;"><span class="cma-tool__strong">php.ini:</span> <span class="cma-tool__em">Niet gevonden</span></div>';
            }

            // Additional scanned ini files
            if ($additionalInis) {
                $iniFiles = array_filter(array_map('trim', explode(',', $additionalInis)));
                echo '<div style="margin-bottom:10px;">';
                echo '<span class="cma-tool__strong">Extra ini bestanden:</span><br>';
                foreach ($iniFiles as $iniFile) {
                    $vscodeUrl = 'vscode://file/' . str_replace('\\', '/', $iniFile);
                    echo '<a href="' . htmlspecialchars($vscodeUrl) . '" style="font-size:var(--font-size-xs);color:var(--text-link);display:block;margin:2px 0;">';
                    echo htmlspecialchars(basename($iniFile)) . '</a>';
                }
                echo '</div>';
            }

            // User ini filename (.user.ini)
            if ($userIniFilename) {
                echo '<div>';
                echo '<span class="cma-tool__strong">User ini bestand:</span> <code>' . htmlspecialchars($userIniFilename) . '</code>';
                echo ' <span style="color:var(--text-muted);font-size:var(--font-size-sm);">(per-directory override)</span>';
                echo '</div>';
            }

            echo '</div>';

            // APCu status warning
            if (!function_exists('apcu_enabled') || !apcu_enabled()) {
                echo '<lib-message type="warning" style="margin-bottom:15px;">';
                echo '<span class="cma-tool__strong">APCu is niet actief!</span> Voeg de volgende regels toe aan php.ini voor betere performance:<br>';
                echo '<code style="display:block;margin-top:10px;padding:10px;background:var(--bg-surface);border-radius:4px;">';
                echo 'extension=apcu<br>apc.enabled=1<br>apc.shm_size=128M';
                echo '</code>';
                echo '</lib-message>';
            }
        }

        echo '<div class="phpinfo-container">';

        // Capture phpinfo output and embed it
        ob_start();
        phpinfo();
        $phpinfo = ob_get_clean();

        // Extract just the body content from phpinfo and clean up styles
        if (preg_match('/<body[^>]*>(.*)<\/body>/is', $phpinfo, $matches)) {
            // Remove inline styles that conflict with our layout
            $content = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $matches[1]);
            echo $content;
        } else {
            echo $phpinfo;
        }

        echo '</div>';
        echo '</div>';
    }

    echo '</cma-tabs>';

    echo '</div></BODY></HTML>';
}

/**
 * Render the Omgeving tab — environment summary, test-email form, the
 * differences-between-test-and-prod table, and a env-switch shortcut.
 */
function renderEnvironmentTab(?array $testMailResult, ?array $envSwitchResult): void
{
    // ---- Environment summary ----
    $envCode  = strtoupper((string)Application::get('omgeving', '?'));
    $envNames = [
        'O' => 'Ontwikkeling', 'L' => 'Lokaal', 'T' => 'Test',
        'A' => 'Acceptatie', 'P' => 'Productie'
    ];
    $envName = $envNames[$envCode] ?? 'Onbekend';
    $envBadgeType = match ($envCode) {
        'P'      => 'error',       // red — pay attention, this is live
        'A'      => 'warning',
        'T', 'O', 'L' => 'information',
        default  => 'information',
    };

    $cmaVersion = defined('CMA_APP_VERSION') ? CMA_APP_VERSION : 'onbekend';

    // Switch target — flip between T and P. From any other env (O/L/A)
    // the default target is P. Developers only.
    $envSwitchCell = '';
    if (SecurityHelper::isDeveloper()) {
        $target = $envCode === 'P' ? 'T' : 'P';
        $targetName = $envNames[$target] ?? $target;
        $confirmMsg = htmlspecialchars(
            'Schakel APP_ENVIRONMENT om naar ' . $target . ' (' . $targetName . ')? '
            . 'Deze wijziging wordt in .env weggeschreven en geldt voor alle gebruikers van deze site.',
            ENT_QUOTES, 'UTF-8'
        );
        $envSwitchCell  = '<form method="post" action="" style="display:inline-block;margin-left:12px;">';
        $envSwitchCell .= '<input type="hidden" name="action" value="switch_env">';
        $envSwitchCell .= '<input type="hidden" name="target" value="' . $target . '">';
        $envSwitchCell .= '<button type="submit" class="btn btn-secondary btn-sm" '
                       .  'onclick="return confirm(\'' . $confirmMsg . '\')" '
                       .  'title="Schakel APP_ENVIRONMENT om naar ' . htmlspecialchars($targetName) . '">';
        $envSwitchCell .= '<span class="lnr lnr-sync"></span> Wissel naar ' . htmlspecialchars($target);
        $envSwitchCell .= '</button>';
        $envSwitchCell .= '</form>';
    }

    if ($envSwitchResult !== null) {
        $type = $envSwitchResult['ok'] ? 'success' : 'error';
        echo '<lib-message type="' . $type . '" closable style="margin-bottom:15px;">' . $envSwitchResult['msg'] . '</lib-message>';
    }

    $summaryRows = [
        ['Omgeving',           '<lib-label type="' . $envBadgeType . '">' . htmlspecialchars($envCode . ' — ' . $envName) . '</lib-label>' . $envSwitchCell],
        ['CMA platform versie', '<code>v' . htmlspecialchars($cmaVersion) . '</code>'],
        ['PHP versie',         '<code>' . htmlspecialchars(PHP_VERSION) . ' (' . PHP_SAPI . ')</code>'],
        ['Host',               '<code>' . htmlspecialchars($_SERVER['HTTP_HOST'] ?? '?') . '</code>'],
        ['HTTPS',              !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'Ja' : '<span class="cma-tool__em">Nee</span>'],
        ['Server software',    htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? '?')],
        ['Document root',      '<code>' . htmlspecialchars($_SERVER['DOCUMENT_ROOT'] ?? '?') . '</code>'],
        ['Applicatie naam',    htmlspecialchars((string)Application::get('app_name', '?'))],
        ['Debug mode',         Application::get('debug', false) ? 'Aan' : 'Uit'],
        ['Display errors',     ini_get('display_errors') ? 'Aan' : 'Uit'],
        ['Memory limit',       htmlspecialchars((string)ini_get('memory_limit'))],
        ['Upload max',         htmlspecialchars((string)ini_get('upload_max_filesize'))],
        ['Post max',           htmlspecialchars((string)ini_get('post_max_size'))],
        ['Max execution',      htmlspecialchars((string)ini_get('max_execution_time')) . ' s'],
    ];

    echo '<h2><span class="lnr lnr-laptop"></span> Omgeving</h2>';
    echo '<table class="listtable">';
    echo '<thead><tr class="listheader"><th style="width:220px">Instelling</th><th>Waarde</th></tr></thead><tbody>';
    foreach ($summaryRows as [$label, $value]) {
        echo '<tr><td>' . htmlspecialchars($label) . '</td><td>' . $value . '</td></tr>';
    }
    echo '</tbody></table>';

    // ---- Test/Prod differences ----
    $simulation = (bool)Application::get('local', false);
    $testWrap   = (bool)Application::get('test', false);
    $mailServer = (string)Application::get('mail_server', 'localhost');
    $adminMail  = (string)Application::get('app_beheerder_email', '');
    $llmUrl     = trim((string)(getenv('LLM_URL') ?: ($_ENV['LLM_URL'] ?? '')));
    $deployKey  = trim((string)(getenv('DEPLOY_SECRET') ?: ($_ENV['DEPLOY_SECRET'] ?? '')));

    $boolLabel = static fn(bool $b, string $onLabel = 'Ja', string $offLabel = 'Nee'): string =>
        '<lib-label type="' . ($b ? 'warning' : 'success') . '">' . ($b ? $onLabel : $offLabel) . '</lib-label>';

    $diffRows = [
        ['Mail-simulatie (geen echte verzending)',     $boolLabel($simulation, 'Aan (local)', 'Uit')],
        ['Test-wrap op uitgaande mail',                $boolLabel($testWrap, 'Aan', 'Uit')],
        ['SMTP-server',                                '<code>' . htmlspecialchars($mailServer) . '</code>'],
        ['Beheerder e-mail (BCC op alle mail)',        $adminMail !== '' ? '<code>' . htmlspecialchars($adminMail) . '</code>' : '<span class="cma-tool__em">niet ingesteld</span>'],
        ['LLM_URL',                                    $llmUrl !== '' ? '<code>' . htmlspecialchars($llmUrl) . '</code>' : '<span class="cma-tool__em">niet ingesteld</span>'],
        ['DEPLOY_SECRET aanwezig',                     $boolLabel($deployKey !== '', 'Ja', 'Nee')],
        ['Cookie secure flag (HTTPS-only)',            $boolLabel((bool)ini_get('session.cookie_secure'), 'Aan', 'Uit')],
        ['Error log',                                  '<code>' . htmlspecialchars((string)(ini_get('error_log') ?: 'php default')) . '</code>'],
    ];

    echo '<h2 style="margin-top:30px;"><span class="lnr lnr-warning"></span> Test-versus-productie instellingen</h2>';
    echo '<p class="cma-tool__em" style="color:var(--text-muted);">';
    echo 'Verschillen die typisch tussen test en productie variëren. Controleer of de waarden passen bij de omgeving waarin je werkt.';
    echo '</p>';
    echo '<table class="listtable">';
    echo '<thead><tr class="listheader"><th style="width:320px">Instelling</th><th>Waarde</th></tr></thead><tbody>';
    foreach ($diffRows as [$label, $value]) {
        echo '<tr><td>' . htmlspecialchars($label) . '</td><td>' . $value . '</td></tr>';
    }
    echo '</tbody></table>';

    // ---- Test e-mail form ----
    if (SecurityHelper::isDeveloper()) {
        echo '<h2 style="margin-top:30px;"><span class="lnr lnr-envelope"></span> Test e-mail versturen</h2>';
        if ($testMailResult !== null) {
            $type = $testMailResult['ok'] ? 'success' : 'error';
            echo '<lib-message type="' . $type . '" closable style="margin-bottom:15px;">' . $testMailResult['msg'] . '</lib-message>';
        }
        if (!class_exists(\App\Library\Email::class)) {
            echo '<lib-message type="warning">';
            echo 'De <code>App\\Library\\Email</code> klasse is op deze site niet beschikbaar. ';
            echo 'Voer <code>composer update stenversonline/platform</code> uit zodat <code>vendor/</code> wordt bijgewerkt.';
            echo '</lib-message>';
        } else {
            $prefillTo = htmlspecialchars($adminMail);
            echo '<form method="post" action="" style="max-width:600px;display:grid;gap:12px;">';
            echo '<input type="hidden" name="action" value="send_test_mail">';
            echo '<label style="display:grid;gap:4px;"><span>Naar</span>';
            echo '<input type="email" name="to" required value="' . $prefillTo . '" placeholder="ontvanger@voorbeeld.nl"></label>';
            echo '<label style="display:grid;gap:4px;"><span>Onderwerp</span>';
            echo '<input type="text" name="subject" value="Test e-mail vanuit CMA"></label>';
            echo '<label style="display:grid;gap:4px;"><span>Bericht</span>';
            echo '<textarea name="body" rows="4">Dit is een test-e-mail vanuit de CMA Omgeving-tab.</textarea></label>';
            echo '<div><button type="submit" class="btn btn-primary"><span class="lnr lnr-paper-plane"></span> Verstuur test</button></div>';
            echo '</form>';
            if ($simulation) {
                echo '<lib-message type="info" compact style="margin-top:10px;">';
                echo 'Local-mode is actief — de verzending wordt gesimuleerd en de body wordt in een preview-venster getoond i.p.v. via SMTP geleverd.';
                echo '</lib-message>';
            }
        }
    }

}

// Call main function
main();
?>
