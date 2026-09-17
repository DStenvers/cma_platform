<?php

namespace App\Library;

/**
 * The site-wide settings registry: which .env variable, which type, which
 * default. One place, read by platform code (Database, Cache, HttpClient,
 * Email, ErrorHandler) and by the admin screen (Cma\Services\SystemSettings,
 * which validates and writes). A setting that exists here is the only
 * source for its value; the literal it replaced is gone from the code.
 *
 *   type    bool   — written as true/false
 *           flag   — written as 1/0 (readers compare against the string '1')
 *           email  — one or more addresses, comma-separated
 *           int    — bounded by min/max; 'optional' allows an empty value
 *           text   — free text, one line
 *           secret — like text, but never shown again; an empty submission
 *                    keeps the stored value
 *   default the value when the variable is absent
 */
final class Settings
{
    public const DEFINITIONS = [
        // Meldingen
        'error_mail_enabled'       => ['env' => 'ERROR_MAIL_ENABLED',       'type' => 'bool',  'default' => false],
        'error_mail_to'            => ['env' => 'ERROR_MAIL_TO',            'type' => 'email', 'default' => ''],
        'notfound_mail_enabled'    => ['env' => 'NOTFOUND_MAIL_ENABLED',    'type' => 'bool',  'default' => false],
        'notfound_mail_to'         => ['env' => 'NOTFOUND_MAIL_TO',         'type' => 'email', 'default' => ''],
        'deploy_alert_email'       => ['env' => 'DEPLOY_ALERT_EMAIL',       'type' => 'email', 'default' => ''],
        // Logging
        'perf_log_enabled'         => ['env' => 'PERF_LOG_ENABLED',         'type' => 'bool',  'default' => true],
        'cache_log_enabled'        => ['env' => 'CACHE_LOG_ENABLED',        'type' => 'bool',  'default' => true],
        'debug_log_enabled'        => ['env' => 'DEBUG_LOG_ENABLED',        'type' => 'bool',  'default' => true],
        'email_log_enabled'        => ['env' => 'EMAIL_LOG_ENABLED',        'type' => 'bool',  'default' => true],
        'sql_log_enabled'          => ['env' => 'SQL_LOG_ENABLED',          'type' => 'bool',  'default' => false],
        'error_log_retention_days' => ['env' => 'ERROR_LOG_RETENTION_DAYS', 'type' => 'int',   'default' => 7, 'min' => 1, 'max' => 365],
        // Mailserver (Email falls back to the mail_* Application keys in app.php when these are empty)
        'mail_host'                => ['env' => 'MAIL_HOST',                'type' => 'text',   'default' => ''],
        'mail_port'                => ['env' => 'MAIL_PORT',                'type' => 'int',    'default' => 0, 'min' => 1, 'max' => 65535, 'optional' => true],
        'mail_username'            => ['env' => 'MAIL_USERNAME',            'type' => 'text',   'default' => ''],
        'mail_password'            => ['env' => 'MAIL_PASSWORD',            'type' => 'secret', 'default' => ''],
        // Lijsten
        'list_page_size'           => ['env' => 'LIST_PAGE_SIZE',           'type' => 'int', 'default' => 50,    'min' => 10, 'max' => 1000],
        'list_scroll_batch'        => ['env' => 'LIST_SCROLL_BATCH',        'type' => 'int', 'default' => 500,   'min' => 50, 'max' => 5000],
        'list_limit'               => ['env' => 'LIST_LIMIT',               'type' => 'int', 'default' => 800,   'min' => 50, 'max' => 100000],
        'combo_dynamic_items'      => ['env' => 'COMBO_DYNAMIC_ITEMS',      'type' => 'int', 'default' => 50,    'min' => 10, 'max' => 1000],
        'report_preview_rows'      => ['env' => 'REPORT_PREVIEW_ROWS',      'type' => 'int', 'default' => 100,   'min' => 10, 'max' => 1000],
        'export_max_rows'          => ['env' => 'EXPORT_MAX_ROWS',          'type' => 'int', 'default' => 15000, 'min' => 1000, 'max' => 1000000],
        // Cache (seconds, except asset_cache_days)
        'cache_default_ttl'        => ['env' => 'CACHE_DEFAULT_TTL',        'type' => 'int', 'default' => 86400, 'min' => 60, 'max' => 2592000],
        'list_cache_ttl'           => ['env' => 'LIST_CACHE_TTL',           'type' => 'int', 'default' => 60,    'min' => 0, 'max' => 86400],
        'lookup_cache_ttl'         => ['env' => 'LOOKUP_CACHE_TTL',         'type' => 'int', 'default' => 1800,  'min' => 0, 'max' => 86400],
        'api_cache_ttl'            => ['env' => 'API_CACHE_TTL',            'type' => 'int', 'default' => 300,   'min' => 0, 'max' => 86400],
        'asset_cache_days'         => ['env' => 'ASSET_CACHE_DAYS',         'type' => 'int', 'default' => 28,    'min' => 1, 'max' => 365],
        // Time-outs (seconds)
        'db_connect_timeout'       => ['env' => 'DB_CONNECT_TIMEOUT',       'type' => 'int', 'default' => 10,    'min' => 1, 'max' => 300],
        'db_query_timeout'         => ['env' => 'DB_QUERY_TIMEOUT',         'type' => 'int', 'default' => 1000,  'min' => 1, 'max' => 86400],
        'http_timeout'             => ['env' => 'HTTP_TIMEOUT',             'type' => 'int', 'default' => 30,    'min' => 1, 'max' => 600],
        'http_download_timeout'    => ['env' => 'HTTP_DOWNLOAD_TIMEOUT',    'type' => 'int', 'default' => 60,    'min' => 1, 'max' => 3600],
        'llm_timeout'              => ['env' => 'LLM_TIMEOUT',              'type' => 'int', 'default' => 90,    'min' => 5, 'max' => 3600],
        'process_timeout'          => ['env' => 'PROCESS_TIMEOUT',          'type' => 'int', 'default' => 300,   'min' => 10, 'max' => 86400],
        // Foutweergave
        'force_debug'              => ['env' => 'FORCE_DEBUG',              'type' => 'flag',  'default' => false],
        'cma_debug'                => ['env' => 'CMA_DEBUG',                'type' => 'flag',  'default' => false],
    ];

    /**
     * Current value of one setting, typed per its definition: the .env
     * variable when set, else $fallback when given (a site's older
     * Application key, for instance), else the registry default.
     *
     * @return bool|int|string
     */
    public static function get(string $key, $fallback = null)
    {
        $def = self::DEFINITIONS[$key] ?? null;
        if ($def === null) {
            throw new \InvalidArgumentException("Unknown system setting: $key");
        }
        $raw = EnvFile::value($def['env']);
        if ($raw === null || trim($raw) === '') {
            return $fallback ?? $def['default'];
        }
        switch ($def['type']) {
            case 'bool':
                return filter_var($raw, FILTER_VALIDATE_BOOLEAN);
            case 'flag':
                return trim($raw) === '1';
            case 'int':
                return max($def['min'], min($def['max'], (int) $raw));
            default:
                return trim($raw);
        }
    }

    /**
     * The settings the browser side needs, in the shape cma_html_header()
     * injects as window.CMA.settings.
     *
     * @return array<string,int>
     */
    public static function forClient(): array
    {
        return [
            'listPageSize'    => (int) self::get('list_page_size'),
            'listScrollBatch' => (int) self::get('list_scroll_batch'),
            'exportMaxRows'   => (int) self::get('export_max_rows'),
        ];
    }
}
