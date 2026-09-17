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
        'lists'         => ['caption' => 'Lijsten',              'order' => 400],
        'cache'         => ['caption' => 'Cache',                'order' => 500],
        'timeouts'      => ['caption' => 'Time-outs',            'order' => 600],
        'debug'         => ['caption' => 'Foutweergave',         'order' => 700],
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
            if ($v === null || $v === '' || $v === false) {
                continue;
            }
            if (is_bool($v)) {
                return $v ? '1' : '0';
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
