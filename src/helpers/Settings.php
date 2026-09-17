<?php

namespace App\Library;

/**
 * The site-wide settings registry: one entry per setting, read by platform
 * code (Database, Cache, HttpClient, Email, ErrorHandler) and by the admin
 * screen (Cma\Services\SystemSettings validates and writes; the page in
 * cma/tools/tools_settings.php, the docs table and the browser injection are
 * all rendered from this array). A setting that exists here is the only
 * source for its value; the literal it replaced is gone from the code.
 *
 * Per entry:
 *   env         the .env variable
 *   type        bool   — written as true/false
 *               flag   — written as 1/0 (readers compare against the string '1')
 *               int    — bounded by min/max; 'optional' allows an empty value
 *               float  — idem, decimals allowed
 *               text   — free text, one line
 *               secret — like text, never shown again; an empty submission
 *                        keeps the stored value; never exposed to the browser
 *               email  — one or more addresses, comma-separated ('single' => true
 *                        allows one only)
 *               list   — comma-separated values; 'item' => 'int'|'text'
 *               select — one of 'options' (value => label)
 *   default     typed value when nothing is set (arrays for list)
 *   group       slug from GROUPS
 *   label, hint what the admin screen shows
 *   doc         "gelezen door & effect" for the documentation table
 *   app         Application key (or ordered list of keys) from app.php that
 *               stands in when the variable is unset — a site's older config
 *   app_invert  bool only: the app.php key means the opposite
 *   client      name under window.CMA.settings (camelCase); not for secrets
 *   requires    key that must be non-empty when this bool/flag is on
 *   confirm     question asked before saving a changed value
 *   placeholder literal placeholder; without it the app.php value shows
 *   hidden      readable and documented, not on the screen
 *
 * A consumer site adds its own entries through app.php:
 *   $GLOBALS['Application']['settings_groups_extra'] = ['shop' => ['caption' => 'Webshop', 'order' => 500]];
 *   $GLOBALS['Application']['settings_extra'] = ['shop_min_order' => [...same shape...]];
 * Bootstrap registers them right after app.php is loaded (registerExtra()).
 */
final class Settings
{
    private const GROUPS = [
        'notifications' => ['caption' => 'Meldingen per e-mail', 'order' => 100],
        'logging'       => ['caption' => 'Logging',              'order' => 200],
        'mail'          => ['caption' => 'Mailserver',           'order' => 300],
        'branding'      => ['caption' => 'Branding & taal',      'order' => 350],
        'lists'         => ['caption' => 'Lijsten',              'order' => 400],
        'cache'         => ['caption' => 'Cache',                'order' => 500],
        'cache_backend' => ['caption' => 'Cache-opslag',         'order' => 520],
        'timeouts'      => ['caption' => 'Time-outs',            'order' => 600],
        'debug'         => ['caption' => 'Foutweergave',         'order' => 700],
        'llm'           => ['caption' => 'LLM',                  'order' => 750],
        'deploy'        => ['caption' => 'Deploy',               'order' => 800],
        'integrations'  => ['caption' => 'Integraties',          'order' => 820],
        'site'          => ['caption' => 'Deze site',            'order' => 900],
    ];

    private const PLATFORM = [
        // ---- Meldingen ----
        'error_mail_enabled' => ['env' => 'ERROR_MAIL_ENABLED', 'type' => 'bool', 'default' => false, 'group' => 'notifications',
            'label' => 'Fouten mailen', 'requires' => 'error_mail_to',
            'hint' => 'Elke niet-afgevangen fout (foutpagina 500) wordt gemaild. Dezelfde fout gaat hoogstens één keer per uur de deur uit.',
            'doc' => 'ErrorHandler mailt elke niet-afgevangen fout naar ERROR_MAIL_TO; dezelfde fout (klasse, melding, bestand, regel) hoogstens één keer per uur. Zie het logs-topic.'],
        'error_mail_to' => ['env' => 'ERROR_MAIL_TO', 'type' => 'email', 'default' => '', 'group' => 'notifications',
            'label' => 'Ontvanger foutmeldingen', 'hint' => 'Eén of meer adressen, gescheiden door komma\'s.',
            'doc' => 'Eén of meer adressen, kommagescheiden. Leeg = geen mail, ook als de schakelaar aan staat.'],
        'notfound_mail_enabled' => ['env' => 'NOTFOUND_MAIL_ENABLED', 'type' => 'bool', 'default' => false, 'group' => 'notifications',
            'label' => '404-overzicht mailen', 'requires' => 'notfound_mail_to',
            'hint' => 'Elke dag één mail met de niet-gevonden pagina\'s van gisteren. Zoekmachines en scanners worden apart geteld en niet uitgesplitst.',
            'doc' => 'NotFoundDigest mailt elke dag één overzicht van de 404\'s van gisteren naar NOTFOUND_MAIL_TO.'],
        'notfound_mail_to' => ['env' => 'NOTFOUND_MAIL_TO', 'type' => 'email', 'default' => '', 'group' => 'notifications',
            'label' => 'Ontvanger 404-overzicht', 'hint' => 'Eén of meer adressen, gescheiden door komma\'s.',
            'doc' => 'Ontvanger(s) van dat overzicht, kommagescheiden.'],
        'deploy_alert_email' => ['env' => 'DEPLOY_ALERT_EMAIL', 'type' => 'email', 'default' => '', 'group' => 'notifications',
            'label' => 'Ontvanger deploy-alarm', 'hint' => 'Krijgt een mail als een automatische deploy mislukt. Leeg = geen mail.',
            'doc' => '/deploy.php stuurt een best-effort mail() als een deploy mislukt. Zie het deployment-topic.'],
        'deploy_health_email' => ['env' => 'DEPLOY_HEALTH_EMAIL', 'type' => 'email', 'default' => '', 'group' => 'notifications',
            'label' => 'Ontvanger deploy-gezondheid', 'hint' => 'Krijgt bericht als een deploy een probleem achterlaat (onvolledige sync). Leeg = het beheerdersadres, anders het afzenderadres.',
            'doc' => 'DeployHealth: ontvanger van de melding dat een deploy een probleem achterliet; leeg = ADMIN_EMAIL, anders MAIL_FROM.'],

        // ---- Logging ----
        'perf_log_enabled' => ['env' => 'PERF_LOG_ENABLED', 'type' => 'bool', 'default' => true, 'group' => 'logging',
            'label' => 'Performance logging', 'hint' => 'Log API-aanroepen, queries en laadtijden.',
            'doc' => 'Services\\SystemSettings/PerformanceLogger — performance-logging naar .logs/perf.'],
        'cache_log_enabled' => ['env' => 'CACHE_LOG_ENABLED', 'type' => 'bool', 'default' => true, 'group' => 'logging',
            'label' => 'Cache logging', 'hint' => 'Log cache hits en misses.',
            'doc' => 'Idem, voor de cache-log.'],
        'debug_log_enabled' => ['env' => 'DEBUG_LOG_ENABLED', 'type' => 'bool', 'default' => true, 'group' => 'logging',
            'label' => 'Debug logging', 'hint' => 'Log debug-informatie vanuit de browser (libLog).',
            'doc' => 'Idem, voor de debug-log die api/log.php vanuit de browser ontvangt.'],
        'email_log_enabled' => ['env' => 'EMAIL_LOG_ENABLED', 'type' => 'bool', 'default' => true, 'group' => 'logging',
            'label' => 'E-mail log', 'hint' => 'Bewaar elke verzonden e-mail in de e-mail log (Site gezondheid → E-mail log).',
            'doc' => 'Bootstrap hangt de afterSend-logging aan Email, voor CMA- én front-end-mail.'],
        'sql_log_enabled' => ['env' => 'SQL_LOG_ENABLED', 'type' => 'bool', 'default' => false, 'group' => 'logging',
            'label' => 'SQL logging', 'hint' => 'Log elke query met duur. Niet standaard aan: het is één schrijfactie per query.',
            'doc' => 'Database logt elke query met duur naar SQL_LOG_FILE. Niet standaard aan: één schrijfactie per query.'],
        'error_log_retention_days' => ['env' => 'ERROR_LOG_RETENTION_DAYS', 'type' => 'int', 'default' => 7, 'min' => 1, 'max' => 365, 'group' => 'logging',
            'label' => 'Bewaartermijn foutlog', 'hint' => 'Dagen dat de dagelijkse PHP-foutlogs bewaard blijven (1 t/m 365).',
            'doc' => 'ErrorHandler — dagen dat de dagelijkse PHP-foutlogs bewaard blijven. De opruiming draait bij de eerste fout van een nieuwe dag.'],
        'sql_log_file' => ['env' => 'SQL_LOG_FILE', 'type' => 'text', 'default' => '', 'group' => 'logging',
            'label' => 'Pad SQL-log', 'hint' => 'Relatief pad hangt aan de siteroot. Leeg = sql_queries.log in de wortel.',
            'doc' => 'Database — doelbestand van de SQL-log. Relatief pad vanaf de siteroot; leeg = sql_queries.log in de siteroot.'],
        'profiler_enabled' => ['env' => 'PROFILER_ENABLED', 'type' => 'bool', 'default' => false, 'group' => 'logging',
            'label' => 'Profiler', 'hint' => 'Schrijft per request een regel met tijdmetingen. Niet gezet = automatisch aan in L/O/T, uit op productie.',
            'doc' => 'Profiler — request-profiling naar CSV. Niet gezet: automatisch aan in L/O/T en uit in P; gezet: deze waarde.'],
        'profiler_log_file' => ['env' => 'PROFILER_LOG_FILE', 'type' => 'text', 'default' => '', 'group' => 'logging',
            'label' => 'Pad profilerlog', 'hint' => 'Relatief pad hangt aan de siteroot. Leeg = profiler.csv in de wortel.',
            'doc' => 'Profiler — doelbestand. Relatief pad vanaf de siteroot; leeg = profiler.csv in de siteroot.'],
        'profiler_threshold_ms' => ['env' => 'PROFILER_THRESHOLD_MS', 'type' => 'int', 'default' => 0, 'min' => 0, 'max' => 60000, 'group' => 'logging',
            'label' => 'Profiler-drempel (ms)', 'hint' => 'Alleen requests trager dan dit worden gelogd. 0 = alles.',
            'doc' => 'Profiler — requests sneller dan deze grens worden niet gelogd.'],

        // ---- Mailserver ----
        'mail_host' => ['env' => 'MAIL_HOST', 'type' => 'text', 'default' => '', 'group' => 'mail', 'app' => 'mail_server',
            'label' => 'SMTP-server', 'hint' => 'Hostnaam van de mailserver. Leeg = de waarde uit app.php (mail_server), anders localhost.',
            'doc' => 'Email — SMTP-host. Leeg = mail_server uit app.php, anders localhost.'],
        'mail_port' => ['env' => 'MAIL_PORT', 'type' => 'int', 'default' => 0, 'min' => 1, 'max' => 65535, 'optional' => true, 'group' => 'mail', 'app' => 'mail_server_port',
            'label' => 'SMTP-poort', 'hint' => '25 zonder versleuteling, 587 voor TLS, 465 voor SSL. Leeg = de waarde uit app.php, anders 25.',
            'doc' => 'Email — SMTP-poort (25, 587 voor TLS, 465 voor SSL). Leeg = mail_server_port uit app.php, anders 25.'],
        'mail_username' => ['env' => 'MAIL_USERNAME', 'type' => 'text', 'default' => '', 'group' => 'mail', 'app' => 'mail_username',
            'label' => 'SMTP-gebruikersnaam', 'hint' => 'Leeg = geen authenticatie (of de waarde uit app.php).',
            'doc' => 'Email — SMTP-gebruikersnaam; leeg = geen authenticatie, tenzij mail_username in app.php staat.'],
        'mail_password' => ['env' => 'MAIL_PASSWORD', 'type' => 'secret', 'default' => '', 'group' => 'mail', 'app' => 'mail_password',
            'label' => 'SMTP-wachtwoord', 'hint' => 'Wordt niet getoond. Leeg laten houdt het huidige wachtwoord. Test de verbinding via Server informatie → Omgeving → Test-mail.',
            'doc' => 'Email — SMTP-wachtwoord. Het instellingenscherm toont dit nooit terug.'],

        'mail_from' => ['env' => 'MAIL_FROM', 'type' => 'email', 'single' => true, 'default' => '', 'group' => 'mail', 'app' => 'email_from',
            'label' => 'Afzenderadres', 'hint' => 'Van welk adres de site mailt. Leeg = email_from uit app.php; is ook dat leeg, dan het terugvaladres mét een melding in de mail.',
            'doc' => 'Email::resolveSender(): het From-adres van elke mail. Leeg = email_from uit app.php (of een adres in het oudere email_fromname); ontbreekt alles, dan MAIL_FALLBACK_ADDRESS met een melding bovenaan de mail.'],
        'mail_from_name' => ['env' => 'MAIL_FROM_NAME', 'type' => 'text', 'default' => '', 'group' => 'mail', 'app' => 'email_fromname',
            'label' => 'Afzendernaam', 'hint' => 'Naam die in de mailbox van de ontvanger verschijnt. Leeg = de organisatienaam.',
            'doc' => 'Email::resolveSender(): de From-naam. Leeg = email_fromname uit app.php, anders de organisatienaam (COMPANY), anders het adres.'],
        'mail_fallback_address' => ['env' => 'MAIL_FALLBACK_ADDRESS', 'type' => 'email', 'single' => true, 'default' => 'dstenvers@gmail.com', 'group' => 'mail',
            'label' => 'Terugvaladres', 'hint' => 'Alleen gebruikt als geen afzenderadres is ingesteld; de mail krijgt dan een melding bovenaan dat de afzender ingesteld moet worden.',
            'doc' => 'Email: afzender als er nergens een afzenderadres staat (geen MAIL_FROM, geen email_from). Elke zo verstuurde mail begint met een melding dat de afzender ingesteld moet worden.'],
        'admin_email' => ['env' => 'ADMIN_EMAIL', 'type' => 'email', 'default' => '', 'group' => 'mail', 'app' => 'app_beheerder_email',
            'label' => 'Beheerdersadres', 'hint' => 'Krijgt een blinde kopie van élke verzonden mail; buiten productie gaat alle mail hierheen in plaats van naar de echte ontvanger. Leeg = geen kopie.',
            'doc' => 'Email: BCC op elke uitgaande mail; buiten productie (test = true) de enige ontvanger, met de echte geadresseerden in een kop boven de mail. Leeg = geen kopie en buiten productie geen ontvanger.'],
        'mail_template' => ['env' => 'MAIL_TEMPLATE', 'type' => 'text', 'default' => '', 'group' => 'mail', 'app' => 'email_template',
            'label' => 'E-mailsjabloon', 'hint' => 'HTML waarin de body wordt verpakt. Leeg = geen sjabloon.',
            'doc' => 'Email: HTML-sjabloon om mails in te verpakken. Leeg = geen sjabloon.'],
        'mail_bcc_postfix' => ['env' => 'MAIL_BCC_POSTFIX', 'type' => 'text', 'default' => '', 'group' => 'mail', 'app' => 'emailpostfix',
            'label' => 'Domein voor webmaster-kopie', 'hint' => 'Achtervoegsel waarmee de legacy mailfuncties het kopie-adres uit de Windows-gebruikersnaam opbouwen. Leeg = geen kopie.',
            'doc' => 'library/lib_sendmail.inc en lib_getuser.inc: achtervoegsel achter de Windows-gebruikersnaam voor het BCC-adres van de legacy SendMail(). Leeg = geen kopie.'],

        // ---- Branding & taal ----
        'company' => ['env' => 'COMPANY', 'type' => 'text', 'default' => '', 'group' => 'branding', 'app' => 'company',
            'label' => 'Organisatienaam', 'hint' => 'Afzendernaam in e-mail en in meldingen als er geen aparte afzendernaam is.',
            'doc' => 'Email (afzendernaam), task.php (onderwerp) en de serverinformatie. Leeg = company uit app.php.'],

        // ---- Lijsten ----
        'list_page_size' => ['env' => 'LIST_PAGE_SIZE', 'type' => 'int', 'default' => 50, 'min' => 10, 'max' => 1000, 'group' => 'lists', 'client' => 'listPageSize',
            'label' => 'Rijen per pagina', 'hint' => 'Tabellen en bladerknoppen in CMA én front-end (lib-table, lib-pagination, de klassieke tabel) en de paginagrootte van lijstschermen.',
            'doc' => 'lib-table, lib-pagination, de klassieke class_table.inc en de paginagrootte van JsonFormService: rijen per pagina.'],
        'list_scroll_batch' => ['env' => 'LIST_SCROLL_BATCH', 'type' => 'int', 'default' => 500, 'min' => 50, 'max' => 5000, 'group' => 'lists', 'client' => 'listScrollBatch',
            'label' => 'Rijen per scroll-stap', 'hint' => 'Hoeveel rijen een lijst bij doorscrollen in één keer bijlaadt (server én client).',
            'doc' => 'JsonFormService (server) en de infinite scroll in table-preferences.js/form-controller.js (client): rijen per bijlaadstap.'],
        'list_limit' => ['env' => 'LIST_LIMIT', 'type' => 'int', 'default' => 800, 'min' => 50, 'max' => 100000, 'group' => 'lists',
            'label' => 'Zoekfilter verplicht vanaf', 'hint' => 'Boven dit aantal records toont een lijst eerst een zoekveld in plaats van alles te laden; een formulier kan een eigen limiet hebben.',
            'doc' => 'ListMode::listLimit() via ListService: boven dit aantal records eist een lijst eerst een zoekfilter. Een formulierdefinitie kan een eigen listLimit zetten.'],
        'combo_dynamic_items' => ['env' => 'COMBO_DYNAMIC_ITEMS', 'type' => 'int', 'default' => 50, 'min' => 10, 'max' => 1000, 'group' => 'lists',
            'label' => 'Keuzelijst dynamisch vanaf', 'hint' => 'Boven dit aantal opties wordt een keuzelijst dynamisch geladen (zoeken-terwijl-je-typt) in plaats van volledig.',
            'doc' => 'FormControlHelper::dynamicListItems(): boven dit aantal opties laadt een keuzelijst dynamisch.'],
        'report_preview_rows' => ['env' => 'REPORT_PREVIEW_ROWS', 'type' => 'int', 'default' => 100, 'min' => 10, 'max' => 1000, 'group' => 'lists',
            'label' => 'Rijen in rapportvoorbeeld', 'hint' => 'Standaard aantal rijen in het voorbeeld van de rapportontwerper (maximaal 1000).',
            'doc' => 'api/report-query.php: rijen in het rapportvoorbeeld als de client geen aantal meegeeft (maximaal 1000).'],
        'export_max_rows' => ['env' => 'EXPORT_MAX_ROWS', 'type' => 'int', 'default' => 15000, 'min' => 1000, 'max' => 1000000, 'group' => 'lists', 'client' => 'exportMaxRows',
            'label' => 'Volledige export tot', 'hint' => 'Boven dit aantal rijen biedt een rapport alleen CSV aan; Excel en PDF worden dan te zwaar.',
            'doc' => 'api/report-export.php, report-designer.php en form-controller.js: boven dit aantal alleen CSV-export.'],

        // ---- Cache ----
        'cache_default_ttl' => ['env' => 'CACHE_DEFAULT_TTL', 'type' => 'int', 'default' => 86400, 'min' => 60, 'max' => 2592000, 'group' => 'cache',
            'label' => 'Standaard cachetijd (s)', 'hint' => 'Levensduur van een cache-item zonder eigen tijd, en de browsercache van formulierdefinities.',
            'doc' => 'Cache::set() zonder eigen ttl, en de browsercache van formulierdefinities (form.php). Seconden.'],
        'list_cache_ttl' => ['env' => 'LIST_CACHE_TTL', 'type' => 'int', 'default' => 60, 'min' => 0, 'max' => 86400, 'group' => 'cache', 'app' => 'list_cache_ttl',
            'label' => 'Lijstcache (s)', 'hint' => 'Hoe lang een lijstresultaat hergebruikt wordt, op de server en in de browser. 0 = uit.',
            'doc' => 'ListService (servercache van lijstresultaten) en form_api.php (browsercache van ongefilterde lijsten). 0 = uit.'],
        'lookup_cache_ttl' => ['env' => 'LOOKUP_CACHE_TTL', 'type' => 'int', 'default' => 1800, 'min' => 0, 'max' => 86400, 'group' => 'cache',
            'label' => 'Keuzelijstcache (s)', 'hint' => 'Browsercache van keuzelijsten, checklists en kolomdefinities.',
            'doc' => 'form_api.php: browsercache van keuzelijsten, checklists en kolomdefinities.'],
        'api_cache_ttl' => ['env' => 'API_CACHE_TTL', 'type' => 'int', 'default' => 300, 'min' => 0, 'max' => 86400, 'group' => 'cache',
            'label' => 'Overige API-cache (s)', 'hint' => 'Browsercache voor de overige API-antwoorden van het CMA.',
            'doc' => 'form_api.php setCacheHeaders(): de overige API-antwoorden.'],
        'asset_cache_days' => ['env' => 'ASSET_CACHE_DAYS', 'type' => 'int', 'default' => 28, 'min' => 1, 'max' => 365, 'group' => 'cache',
            'label' => 'Bundels in browsercache (dagen)', 'hint' => 'Hoe lang JavaScript- en CSS-bundels in de browser blijven; een nieuwe versie krijgt een nieuwe URL, dus dit mag lang.',
            'doc' => 'minify.php: browsercache van de JS/CSS-bundels. Een nieuwe versie krijgt een nieuwe URL, dus dit mag lang.'],

        // ---- Cache-opslag (Cache falls back to the app.php keys of older sites) ----
        'cache_enabled' => ['env' => 'CACHE_ENABLED', 'type' => 'bool', 'default' => true, 'group' => 'cache_backend', 'app' => 'cma_caching',
            'label' => 'Cache aan', 'hint' => 'Uit alleen bij het opsporen van verouderde gegevens.',
            'doc' => 'Cache — master-schakelaar van de cachelaag.'],
        'cache_backend' => ['env' => 'CACHE_BACKEND', 'type' => 'select', 'default' => 'auto', 'group' => 'cache_backend', 'app' => 'cache_backend',
            'options' => ['auto' => 'Automatisch (Redis, anders APCu, anders bestanden)', 'redis' => 'Redis', 'apcu' => 'APCu', 'file' => 'Bestanden'],
            'label' => 'Cache-opslag', 'hint' => 'Een wijziging vraagt een recycle van de app-pool.',
            'doc' => 'Cache — welke backend; auto kiest Redis, anders APCu, anders bestanden.'],
        'cache_directory' => ['env' => 'CACHE_DIRECTORY', 'type' => 'text', 'default' => '', 'group' => 'cache_backend', 'app' => 'cache_directory',
            'label' => 'Cachemap', 'hint' => 'Map voor de bestandscache. Leeg = de tijdelijke map van de server.',
            'doc' => 'Cache (bestandsbackend) en de cache-leegmaker. Leeg = sys_get_temp_dir()/cma_cache.'],
        'redis_host' => ['env' => 'REDIS_HOST', 'type' => 'text', 'default' => '127.0.0.1', 'group' => 'cache_backend', 'app' => 'redis_host',
            'label' => 'Redis-server', 'hint' => 'Hostnaam of IP.', 'doc' => 'Cache (Redis-backend) en de cache-leegmaker.'],
        'redis_port' => ['env' => 'REDIS_PORT', 'type' => 'int', 'default' => 6379, 'min' => 1, 'max' => 65535, 'group' => 'cache_backend', 'app' => 'redis_port',
            'label' => 'Redis-poort', 'hint' => 'Standaard 6379.', 'doc' => 'Cache (Redis-backend) en de cache-leegmaker.'],
        'redis_timeout' => ['env' => 'REDIS_TIMEOUT', 'type' => 'float', 'default' => 2.5, 'min' => 0.1, 'max' => 30, 'group' => 'cache_backend', 'app' => 'redis_timeout',
            'label' => 'Redis-time-out (s)', 'hint' => 'Wachttijd op de verbinding.', 'doc' => 'Cache (Redis-backend): verbindingstime-out in seconden.'],
        'redis_password' => ['env' => 'REDIS_PASSWORD', 'type' => 'secret', 'default' => '', 'group' => 'cache_backend', 'app' => 'redis_password',
            'label' => 'Redis-wachtwoord', 'hint' => 'Wordt niet getoond. Leeg = geen authenticatie.', 'doc' => 'Cache (Redis-backend).'],
        'redis_database' => ['env' => 'REDIS_DATABASE', 'type' => 'int', 'default' => 0, 'min' => 0, 'max' => 15, 'group' => 'cache_backend', 'app' => 'redis_database',
            'label' => 'Redis-database', 'hint' => 'Nummer om meerdere sites op één server te scheiden.', 'doc' => 'Cache (Redis-backend).'],
        'redis_prefix' => ['env' => 'REDIS_PREFIX', 'type' => 'text', 'default' => 'cma_', 'group' => 'cache_backend', 'app' => 'redis_prefix',
            'label' => 'Redis-sleutelvoorvoegsel', 'hint' => 'Voorkomt botsingen tussen sites op dezelfde Redis.', 'doc' => 'Cache (Redis-backend).'],

        // ---- Time-outs ----
        'db_connect_timeout' => ['env' => 'DB_CONNECT_TIMEOUT', 'type' => 'int', 'default' => 10, 'min' => 1, 'max' => 300, 'group' => 'timeouts',
            'label' => 'Databaseverbinding (s)', 'hint' => 'Wachttijd op het openen van een databaseverbinding.',
            'doc' => 'Database: seconden wachten op een databaseverbinding (PDO::ATTR_TIMEOUT).'],
        'db_query_timeout' => ['env' => 'DB_QUERY_TIMEOUT', 'type' => 'int', 'default' => 1000, 'min' => 1, 'max' => 86400, 'group' => 'timeouts',
            'label' => 'Query (s)', 'hint' => 'Maximale looptijd van één query op MySQL en SQL Server.',
            'doc' => 'Database: maximale looptijd van een query op MySQL (max_execution_time) en SQL Server (SQLSRV_ATTR_QUERY_TIMEOUT).'],
        'http_timeout' => ['env' => 'HTTP_TIMEOUT', 'type' => 'int', 'default' => 30, 'min' => 1, 'max' => 600, 'group' => 'timeouts',
            'label' => 'HTTP-aanroepen (s)', 'hint' => 'Uitgaande HTTP-aanroepen van de site (koppelingen, feeds).',
            'doc' => 'HttpClient: uitgaande GET/POST zonder eigen time-out.'],
        'http_download_timeout' => ['env' => 'HTTP_DOWNLOAD_TIMEOUT', 'type' => 'int', 'default' => 60, 'min' => 1, 'max' => 3600, 'group' => 'timeouts',
            'label' => 'HTTP-downloads (s)', 'hint' => 'Uitgaande downloads van bestanden.',
            'doc' => 'HttpClient::downloadFile().'],
        'llm_timeout' => ['env' => 'LLM_TIMEOUT', 'type' => 'int', 'default' => 90, 'min' => 5, 'max' => 3600, 'group' => 'timeouts',
            'label' => 'LLM-aanroepen (s)', 'hint' => 'Tekstaanroepen naar het taalmodel; beeldanalyse krijgt het dubbele.',
            'doc' => 'Llm: tekstaanroepen; beeldanalyse krijgt het dubbele.'],
        'process_timeout' => ['env' => 'PROCESS_TIMEOUT', 'type' => 'int', 'default' => 300, 'min' => 10, 'max' => 86400, 'group' => 'timeouts',
            'label' => 'Achtergrondprocessen (s)', 'hint' => 'Maximale looptijd van een proces dat het CMA start (tests, conversies).',
            'doc' => 'Services\\ProcessRunner: achtergrondprocessen die het CMA start.'],

        // ---- Foutweergave ----
        'force_debug' => ['env' => 'FORCE_DEBUG', 'type' => 'flag', 'default' => false, 'group' => 'debug',
            'label' => 'Fouten tonen op productie', 'hint' => 'Toont foutdetails aan iedere bezoeker, ook op productie. Alleen tijdelijk aanzetten; beheerders zien de details altijd al.',
            'doc' => 'Bootstrap — 1 houdt verbose errors aan, ook op P.'],
        'cma_debug' => ['env' => 'CMA_DEBUG', 'type' => 'flag', 'default' => false, 'group' => 'debug',
            'label' => 'CMA-debugmodus voor iedereen', 'hint' => 'Zet de debugmodus van het CMA voor alle gebruikers aan (console-logging, debug-overlay). Alleen voor jezelf: groep Ontwikkelaar hieronder.',
            'doc' => 'cma/bootstrap.inc — 1 zet de CMA-debugmodus aan (constante CMA_DEBUG_MODE, gebruikt door de front-end-logging). Los van FORCE_DEBUG: dit gaat over de CMA-UI, niet over PHP-foutweergave.'],

        // ---- LLM ----
        'llm_provider' => ['env' => 'LLM_PROVIDER', 'type' => 'select', 'default' => '', 'optional' => true, 'group' => 'llm',
            'options' => ['ollama' => 'Ollama / lokaal', 'anthropic' => 'Anthropic', 'openai' => 'OpenAI'], 'placeholder' => 'Automatisch (uit adres of sleutel)',
            'label' => 'LLM-aanbieder', 'hint' => 'Leeg = automatisch afleiden uit het adres of de sleutel.',
            'doc' => 'Llm — welke aanbieder; leeg = afleiden uit LLM_URL/LLM_KEY. Zie het LLM-topic.'],
        'llm_url' => ['env' => 'LLM_URL', 'type' => 'text', 'default' => '', 'group' => 'llm',
            'label' => 'LLM-adres', 'hint' => 'Endpoint van een lokale Ollama of LM Studio, bijv. http://localhost:11434/api/generate.',
            'doc' => 'Llm — endpoint van een lokaal model; leeg = gehoste aanbieder.'],
        'llm_model' => ['env' => 'LLM_MODEL', 'type' => 'text', 'default' => '', 'group' => 'llm',
            'label' => 'Model', 'hint' => 'Modelnaam bij de gekozen aanbieder. Leeg = het standaardmodel van de aanbieder.',
            'doc' => 'Llm — modelnaam; leeg = standaardmodel per aanbieder.'],
        'llm_key' => ['env' => 'LLM_KEY', 'type' => 'secret', 'default' => '', 'group' => 'llm',
            'label' => 'API-sleutel LLM', 'hint' => 'Wordt niet getoond. Nodig bij Anthropic of OpenAI.',
            'doc' => 'Llm — API-sleutel van de gehoste aanbieder; valt terug op OCR_VISION_KEY bij dezelfde aanbieder.'],
        'llm_fallback_model' => ['env' => 'LLM_FALLBACK_MODEL', 'type' => 'text', 'default' => 'claude-haiku-4-5', 'group' => 'llm',
            'label' => 'Terugvalmodel', 'hint' => 'Gebruikt als het lokale model onbereikbaar is.',
            'doc' => 'Llm/tools_llm — model bij de terugval naar Anthropic als het lokale model niet antwoordt.'],
        'llm_vision_model' => ['env' => 'LLM_VISION_MODEL', 'type' => 'text', 'default' => '', 'group' => 'llm',
            'label' => 'Model voor afbeeldingen', 'hint' => 'Een tekstmodel leest geen plaatjes; hier een multimodaal model. Leeg = zelfde als Model.',
            'doc' => 'Llm — model voor beeldanalyse; leeg = LLM_MODEL.'],
        'llm_models_dir' => ['env' => 'LLM_MODELS_DIR', 'type' => 'text', 'default' => '', 'group' => 'llm',
            'label' => 'Map met lokale modellen', 'hint' => 'Waar de analysetool naar modelbestanden zoekt. Leeg = C:\\llama\\models of ~/llama-models.',
            'doc' => 'tools/llm_analyse.php — map met lokale modelbestanden.'],
        'ocr_vision_provider' => ['env' => 'OCR_VISION_PROVIDER', 'type' => 'select', 'default' => 'anthropic', 'group' => 'llm',
            'options' => ['anthropic' => 'Anthropic', 'openai' => 'OpenAI'],
            'label' => 'OCR-aanbieder', 'hint' => 'Bepaalt of de OCR-sleutel ook voor de LLM gebruikt mag worden.',
            'doc' => 'Llm — aanbieder van de OCR-sleutel; bij dezelfde aanbieder dient OCR_VISION_KEY als LLM-sleutel.'],
        'ocr_vision_key' => ['env' => 'OCR_VISION_KEY', 'type' => 'secret', 'default' => '', 'group' => 'llm',
            'label' => 'API-sleutel OCR', 'hint' => 'Wordt niet getoond.',
            'doc' => 'Llm — sleutel voor beeldanalyse/OCR; hergebruikt als LLM-sleutel bij dezelfde aanbieder.'],

        // ---- Deploy (read by /deploy.php with its own env reader; the screen writes the same file) ----
        'deploy_secret' => ['env' => 'DEPLOY_SECRET', 'type' => 'secret', 'default' => '', 'group' => 'deploy',
            'label' => 'Deploy-secret', 'hint' => 'Wordt niet getoond. Dezelfde waarde als in de GitHub-webhook.',
            'doc' => '/deploy.php — HMAC-secret van de webhook. Zie het deployment-topic.'],
        'deploy_branch' => ['env' => 'DEPLOY_BRANCH', 'type' => 'text', 'default' => 'main', 'group' => 'deploy',
            'label' => 'Branch', 'hint' => 'Welke branch deze site uitrolt.', 'doc' => '/deploy.php — alleen pushes naar deze branch worden uitgerold.'],
        'deploy_site_root' => ['env' => 'DEPLOY_SITE_ROOT', 'type' => 'text', 'default' => '', 'group' => 'deploy',
            'label' => 'Siteroot', 'hint' => 'Git-werkmap. Leeg = de map waar deploy.php staat.', 'doc' => '/deploy.php — git-werkmap; leeg = de map van deploy.php.'],
        'deploy_pipeline' => ['env' => 'DEPLOY_PIPELINE', 'type' => 'text', 'default' => '', 'group' => 'deploy',
            'label' => 'Eigen deploy-stappen', 'hint' => 'Puntkomma-gescheiden commando\'s; {branch} wordt vervangen. Leeg = git pull --ff-only origin {branch}. Zet hier geen wachtwoorden in.',
            'doc' => '/deploy.php — commando\'s in plaats van de standaard pull.'],
        'deploy_run_tests' => ['env' => 'DEPLOY_RUN_TESTS', 'type' => 'text', 'default' => '', 'group' => 'deploy',
            'label' => 'Testcommando', 'hint' => 'Draait vóór de recycle; een rode test blokkeert de deploy. Leeg = geen tests.',
            'doc' => '/deploy.php — commando na pull/composer, vóór de recycle.'],
        'deploy_migrate' => ['env' => 'DEPLOY_MIGRATE', 'type' => 'flag', 'default' => true, 'group' => 'deploy',
            'label' => 'Migraties automatisch draaien', 'hint' => 'Uit = zelf toepassen via Beheer → Migraties.',
            'doc' => '/deploy.php — draait cma/migrate.php na een geslaagde deploy.'],
        'deploy_migrate_backup' => ['env' => 'DEPLOY_MIGRATE_BACKUP', 'type' => 'flag', 'default' => true, 'group' => 'deploy',
            'label' => 'Back-up vóór migreren', 'hint' => 'Maakt een databasekopie voordat een migratie draait.',
            'doc' => '/deploy.php — back-up van de databases vóór de migraties.'],
        'deploy_composer_update' => ['env' => 'DEPLOY_COMPOSER_UPDATE', 'type' => 'list', 'default' => ['stenversonline/platform'], 'group' => 'deploy',
            'label' => 'Pakketten bijwerken', 'hint' => 'Komma-gescheiden Composer-pakketten die bij elke deploy worden geüpdatet; "install" = composer install volgens de lock.',
            'doc' => '/deploy.php — pakketten voor composer update; de waarde install kiest composer install.'],
        'deploy_composer_clear_cache' => ['env' => 'DEPLOY_COMPOSER_CLEAR_CACHE', 'type' => 'flag', 'default' => false, 'group' => 'deploy',
            'label' => 'Composer-cache legen', 'hint' => 'Alleen nodig als updates blijven hangen op oude versies.',
            'doc' => '/deploy.php — composer clear-cache vóór de update.'],
        'deploy_no_reset' => ['env' => 'DEPLOY_NO_RESET', 'type' => 'flag', 'default' => false, 'group' => 'deploy',
            'label' => 'Lokale wijzigingen behouden', 'hint' => 'Slaat de git checkout -- . vóór de pull over.',
            'doc' => '/deploy.php — geen reset van de werkmap vóór de pull.'],
        'deploy_no_pre_pull_touch' => ['env' => 'DEPLOY_NO_PRE_PULL_TOUCH', 'type' => 'flag', 'default' => false, 'group' => 'deploy',
            'label' => 'Geen recycle vóór de pull', 'hint' => 'Alleen op servers zonder bestandsvergrendeling.',
            'doc' => '/deploy.php — sla de app-pool-recycle vóór de pull over.'],
        'deploy_no_maintenance' => ['env' => 'DEPLOY_NO_MAINTENANCE', 'type' => 'flag', 'default' => false, 'group' => 'deploy',
            'label' => 'Geen onderhoudspagina tijdens deploy', 'hint' => 'Bezoekers zien dan half-bijgewerkte code.',
            'doc' => '/deploy.php — geen maintenance.flag tijdens de deploy.'],
        'deploy_recycle_touch' => ['env' => 'DEPLOY_RECYCLE_TOUCH', 'type' => 'text', 'default' => '', 'group' => 'deploy',
            'label' => 'Bestand voor app-pool recycle', 'hint' => 'Wordt na een geslaagde deploy aangeraakt. Leeg = web.config in de siteroot.',
            'doc' => '/deploy.php — bestand dat na succes wordt aangeraakt (IIS-recycle); leeg = web.config.'],
        'deploy_post_hook' => ['env' => 'DEPLOY_POST_HOOK', 'type' => 'text', 'default' => '', 'group' => 'deploy',
            'label' => 'Script na deploy', 'hint' => 'PHP-bestand dat na een geslaagde deploy wordt ingeladen.',
            'doc' => '/deploy.php — PHP-bestand na een geslaagde deploy.'],
        'deploy_log_file' => ['env' => 'DEPLOY_LOG_FILE', 'type' => 'text', 'default' => '', 'group' => 'deploy',
            'label' => 'Pad deploy-log', 'hint' => 'Leeg = .logs/deploy/deploy.log in de siteroot.',
            'doc' => '/deploy.php en DeployHealth — logbestand; leeg = .logs/deploy/deploy.log.'],

        // ---- Integraties ----
        'google_oauth_client_id' => ['env' => 'GOOGLE_OAUTH_CLIENT_ID', 'type' => 'text', 'default' => '', 'group' => 'integrations',
            'label' => 'Google client-ID', 'hint' => 'Voor inloggen met een Google-account. Leeg = Google-login uit.',
            'doc' => 'GoogleOAuth — zonder ID en secret is Google-login uit; de oudere GOOGLE_CLIENT_ID werkt als terugval.'],
        'google_oauth_client_secret' => ['env' => 'GOOGLE_OAUTH_CLIENT_SECRET', 'type' => 'secret', 'default' => '', 'group' => 'integrations',
            'label' => 'Google client-secret', 'hint' => 'Wordt niet getoond.', 'doc' => 'GoogleOAuth — de oudere GOOGLE_CLIENT_SECRET werkt als terugval.'],
        'google_api_key' => ['env' => 'GOOGLE_API_KEY', 'type' => 'secret', 'default' => '', 'group' => 'integrations', 'app' => 'Google_api_key',
            'label' => 'Google Maps-sleutel', 'hint' => 'Wordt niet getoond. Voor geocoderen en routeberekening.', 'doc' => 'library/lib_geocode.inc — geocoding en routes.'],
        'allowed_external_url' => ['env' => 'ALLOWED_EXTERNAL_URL', 'type' => 'text', 'default' => '', 'group' => 'integrations', 'app' => 'allowed_external_url',
            'label' => 'Toegestaan extern adres', 'hint' => 'Alleen URL\'s die dit bevatten mogen als externe module worden opgehaald. Leeg = geen beperking.',
            'doc' => 'library/lib_externhtml.inc — beperking op externe module-URL\'s.'],
        'nodejs_path' => ['env' => 'NODEJS_PATH', 'type' => 'text', 'default' => '', 'group' => 'integrations',
            'label' => 'Pad naar Node.js', 'hint' => 'Nodig op IIS, waar PATH niet is ingesteld; bijv. C:/Program Files/nodejs.',
            'doc' => 'tools_testrunner.php — zonder deze map kan de Cypress-runner op IIS niet starten.'],
        'db_type' => ['env' => 'DB_TYPE', 'type' => 'select', 'default' => 'mysql', 'group' => 'integrations',
            'options' => ['mysql' => 'MySQL / MariaDB', 'pgsql' => 'PostgreSQL', 'sqlserver' => 'SQL Server'],
            'label' => 'Back-up: databasetype', 'hint' => 'Voor mysqldump/pg_dump bij back-ups en herstel van een server-database.',
            'doc' => 'BackupService/tools_backup — welk dump-programma.'],
        'db_host' => ['env' => 'DB_HOST', 'type' => 'text', 'default' => 'localhost', 'group' => 'integrations',
            'label' => 'Back-up: databasehost', 'hint' => 'Server van de te dumpen database.', 'doc' => 'BackupService/tools_backup.'],
        'db_port' => ['env' => 'DB_PORT', 'type' => 'int', 'default' => 0, 'min' => 1, 'max' => 65535, 'optional' => true, 'group' => 'integrations',
            'label' => 'Back-up: poort', 'hint' => 'Leeg = 3306 (MySQL) of 5432 (PostgreSQL).', 'doc' => 'BackupService/tools_backup; leeg = de standaardpoort van het type.'],
        'db_name' => ['env' => 'DB_NAME', 'type' => 'text', 'default' => '', 'group' => 'integrations',
            'label' => 'Back-up: databasenaam', 'hint' => 'Leeg = de naam uit databases.json.', 'doc' => 'BackupService/tools_backup; leeg = de naam uit databases.json.'],
        'db_user' => ['env' => 'DB_USER', 'type' => 'text', 'default' => '', 'group' => 'integrations',
            'label' => 'Back-up: gebruiker', 'hint' => 'Databasegebruiker voor de dump.', 'doc' => 'BackupService/tools_backup.'],
        'db_password' => ['env' => 'DB_PASSWORD', 'type' => 'secret', 'default' => '', 'group' => 'integrations',
            'label' => 'Back-up: wachtwoord', 'hint' => 'Wordt niet getoond.', 'doc' => 'BackupService/tools_backup.'],
    ];

    /** @deprecated Use definitions(); this constant holds the platform entries only. */
    public const DEFINITIONS = self::PLATFORM;

    private const TYPES = ['bool', 'flag', 'int', 'float', 'text', 'secret', 'email', 'list', 'select'];

    /** @var array<string,array>|null merged registry, memoized once extras are known */
    private static ?array $merged = null;

    /** @var array<string,array> */
    private static array $extra = [];

    /** @var array<string,array{caption:string,order:int}> */
    private static array $extraGroups = [];

    /** @var array<string,string> rejected site entries: key => reason */
    private static array $rejected = [];

    private static bool $extrasRegistered = false;

    /**
     * Every setting, platform first, then the site's own. Before the site's
     * extras are registered (early bootstrap), the platform entries.
     *
     * @return array<string,array>
     */
    public static function definitions(): array
    {
        if (!self::$extrasRegistered) {
            return self::PLATFORM;
        }
        if (self::$merged === null) {
            self::$merged = self::PLATFORM + self::$extra;
        }
        return self::$merged;
    }

    /**
     * Groups in display order: slug => ['caption', 'order'].
     *
     * @return array<string,array{caption:string,order:int}>
     */
    public static function groups(): array
    {
        $groups = self::GROUPS + self::$extraGroups;
        uasort($groups, static fn ($a, $b) => $a['order'] <=> $b['order']);
        return $groups;
    }

    /**
     * Register a site's own settings (app.php: settings_extra / settings_groups_extra).
     * Entries that do not fit are skipped and listed in rejectedExtra(), so the
     * documentation topic can show them. Platform keys and env names win.
     *
     * @param  array<string,mixed> $settings
     * @param  array<string,mixed> $groups
     * @return array<string,string> the rejected entries with the reason
     */
    public static function registerExtra(array $settings, array $groups = []): array
    {
        foreach ($groups as $slug => $g) {
            if (is_string($slug) && preg_match('/^[a-z][a-z0-9_]*$/', $slug) && !isset(self::GROUPS[$slug]) && is_array($g) && isset($g['caption'])) {
                self::$extraGroups[$slug] = ['caption' => (string) $g['caption'], 'order' => (int) ($g['order'] ?? 800)];
            }
        }
        $usedEnv = array_map(static fn ($d) => $d['env'], self::PLATFORM + self::$extra);
        foreach ($settings as $key => $def) {
            $reason = self::validateExtra($key, $def, $usedEnv);
            if ($reason !== null) {
                self::$rejected[(string) $key] = $reason;
                continue;
            }
            if (!isset(self::GROUPS[$def['group']]) && !isset(self::$extraGroups[$def['group']])) {
                $def['group'] = 'site';
            }
            $def['hint'] = (string) ($def['hint'] ?? '');
            $def['doc']  = (string) ($def['doc'] ?? $def['hint']);
            self::$extra[$key] = $def;
            $usedEnv[$key] = $def['env'];
        }
        self::$extrasRegistered = true;
        self::$merged = null;
        return self::$rejected;
    }

    /** @return array<string,string> */
    public static function rejectedExtra(): array
    {
        return self::$rejected;
    }

    /** Forget the site's extras (tests). */
    public static function reset(): void
    {
        self::$merged = null;
        self::$extra = [];
        self::$extraGroups = [];
        self::$rejected = [];
        self::$extrasRegistered = false;
    }

    /**
     * Current value of one setting, typed per its definition: the .env
     * variable when set, else $fallback when given, else the app.php key(s)
     * the entry names, else the registry default.
     *
     * @return bool|int|float|string|array
     */
    public static function get(string $key, $fallback = null)
    {
        $def = self::definition($key);
        $raw = EnvFile::value($def['env']);
        if ($raw !== null && trim($raw) !== '') {
            return self::cast($def, $raw);
        }
        if ($fallback !== null) {
            return $fallback;
        }
        $app = self::appValue($def);
        if ($app !== null) {
            return self::cast($def, $app, true);
        }
        return $def['default'];
    }

    /** Where the current value comes from: 'env', 'app' or 'default'. */
    public static function source(string $key): string
    {
        $def = self::definition($key);
        $raw = EnvFile::value($def['env']);
        if ($raw !== null && trim($raw) !== '') {
            return 'env';
        }
        return self::appValue($def) !== null ? 'app' : 'default';
    }

    /** True when the .env variable itself is set (not the app.php fallback). */
    public static function isSet(string $key): bool
    {
        return self::source($key) === 'env';
    }

    /**
     * The settings the browser side needs, as window.CMA.settings: every entry
     * with a 'client' name, cast per type. Secrets are never included.
     *
     * @return array<string,mixed>
     */
    public static function forClient(): array
    {
        $out = [];
        foreach (self::definitions() as $key => $def) {
            if (empty($def['client']) || $def['type'] === 'secret') {
                continue;
            }
            $out[$def['client']] = self::get($key);
        }
        return $out;
    }

    /** The script tag that injects forClient() into a page. */
    public static function clientScript(): string
    {
        return '<script>window.CMA=window.CMA||{};window.CMA.settings='
            . json_encode(self::forClient(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
            . ';</script>';
    }

    // ---- internals -------------------------------------------------------------

    /** @return array<string,mixed> */
    private static function definition(string $key): array
    {
        $def = self::definitions()[$key] ?? null;
        if ($def === null) {
            throw new \InvalidArgumentException("Unknown system setting: $key");
        }
        return $def;
    }

    /** The first non-empty app.php value the entry names, or null. */
    private static function appValue(array $def): ?string
    {
        if (empty($def['app']) || !isset($GLOBALS['Application'])) {
            return null;
        }
        foreach ((array) $def['app'] as $appKey) {
            $v = Application::get($appKey, null);
            if ($v === null || $v === '') {
                continue;
            }
            if (is_bool($v)) {
                return $v ? '1' : '0'; // an explicit false in app.php is a value, not "unset"
            }
            if (is_array($v)) {
                return implode(',', $v);
            }
            return (string) $v;
        }
        return null;
    }

    /**
     * @param  array<string,mixed> $def
     * @return bool|int|float|string|array
     */
    private static function cast(array $def, string $raw, bool $fromApp = false)
    {
        switch ($def['type']) {
            case 'bool':
                $b = filter_var($raw, FILTER_VALIDATE_BOOLEAN);
                return ($fromApp && !empty($def['app_invert'])) ? !$b : $b;
            case 'flag':
                return trim($raw) === '1';
            case 'int':
                return max($def['min'], min($def['max'], (int) $raw));
            case 'float':
                return max((float) $def['min'], min((float) $def['max'], (float) $raw));
            case 'list':
                $items = array_values(array_filter(array_map('trim', explode(',', $raw)), 'strlen'));
                return ($def['item'] ?? 'text') === 'int' ? array_map('intval', $items) : $items;
            case 'select':
                $v = trim($raw);
                return array_key_exists($v, $def['options'] ?? []) ? $v : $def['default'];
            default:
                return trim($raw);
        }
    }

    /**
     * @param mixed $key
     * @param mixed $def
     * @param array<string,string> $usedEnv
     */
    private static function validateExtra($key, $def, array $usedEnv): ?string
    {
        if (!is_string($key) || !preg_match('/^[a-z][a-z0-9_]*$/', $key)) {
            return 'sleutel moet kleine letters, cijfers en _ zijn';
        }
        if (isset(self::PLATFORM[$key])) {
            return 'sleutel bestaat al in het platform';
        }
        if (!is_array($def)) {
            return 'definitie is geen array';
        }
        foreach (['env', 'type', 'default', 'label', 'group'] as $required) {
            if (!array_key_exists($required, $def)) {
                return "'$required' ontbreekt";
            }
        }
        if (!is_string($def['env']) || !preg_match('/^[A-Z][A-Z0-9_]*$/', $def['env'])) {
            return 'env moet HOOFDLETTERS_MET_UNDERSCORES zijn';
        }
        if (in_array($def['env'], $usedEnv, true)) {
            return 'env ' . $def['env'] . ' is al in gebruik';
        }
        if (!in_array($def['type'], self::TYPES, true)) {
            return 'onbekend type ' . (is_scalar($def['type']) ? $def['type'] : '?');
        }
        if (in_array($def['type'], ['int', 'float'], true) && (!isset($def['min']) || !isset($def['max']))) {
            return 'min en max ontbreken';
        }
        if ($def['type'] === 'select' && (empty($def['options']) || !is_array($def['options']))) {
            return 'options ontbreken';
        }
        if ($def['type'] === 'secret' && !empty($def['client'])) {
            return 'een secret hoort niet in de browser';
        }
        return null;
    }
}
