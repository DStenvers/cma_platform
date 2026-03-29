<?php
use App\Library\Application;
use App\Library\Arr;
use Cma\ToolbarHelper;
use Cma\SecurityHelper;
use App\Library\Request;
use App\Library\Response;

require_once __DIR__ . '/../bootstrap.inc';
/**
* Main
*/
function main()
{
    Response::noCache();
    cma_html_header('CMA - Server informatie');
    echo '<BODY class="contentbody tools tool-serverinfo" style="margin: 0;">';
    ToolbarHelper::report('Server informatie', false, false, false, false, 'Bekijk server en PHP configuratie');
    echo '<div id="c" class="tools">';

    // Build tabs array
    $tabs = ['Applicatie instellingen'];
    if (SecurityHelper::isDeveloper()) {
        $tabs[] = 'PHP info';
    }

    echo '<style>cma-tabs [slot^="tab-"] { padding: 24px; }</style>';
    echo '<cma-tabs tabs="' . htmlspecialchars(json_encode($tabs)) . '">';

    // Tab 1: Application settings
    echo '<div slot="tab-0">';
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

    // Tab 2: PHP Info - Developer only
    if (SecurityHelper::isDeveloper()) {
        echo '<div slot="tab-1">';

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
                echo '<strong>php.ini:</strong><br>';
                echo '<code style="font-size:var(--font-size-sm);">' . htmlspecialchars($phpIniPath) . '</code>';
                echo '</div>';
                echo '<a href="' . htmlspecialchars($vscodeUrl) . '" class="btn btn-primary" style="white-space:nowrap;text-decoration:none;">';
                echo '<span class="lnr lnr-pencil"></span> Open in VS Code</a>';
                echo '</div>';
            } else {
                echo '<div style="margin-bottom:10px;"><strong>php.ini:</strong> <em>Niet gevonden</em></div>';
            }

            // Additional scanned ini files
            if ($additionalInis) {
                $iniFiles = array_filter(array_map('trim', explode(',', $additionalInis)));
                echo '<div style="margin-bottom:10px;">';
                echo '<strong>Extra ini bestanden:</strong><br>';
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
                echo '<strong>User ini bestand:</strong> <code>' . htmlspecialchars($userIniFilename) . '</code>';
                echo ' <span style="color:var(--text-muted);font-size:var(--font-size-sm);">(per-directory override)</span>';
                echo '</div>';
            }

            echo '</div>';

            // APCu status warning
            if (!function_exists('apcu_enabled') || !apcu_enabled()) {
                echo '<lib-message type="warning" style="margin-bottom:15px;">';
                echo '<strong>APCu is niet actief!</strong> Voeg de volgende regels toe aan php.ini voor betere performance:<br>';
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
// Call main function
main();
?>
