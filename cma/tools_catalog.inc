<?php
/**
 * Tools catalog — the single source of truth for the admin tools/forms menu.
 *
 * Side-effect-free: defines buildToolsTreeData() (and the cma_db_uses_sqlite()
 * helper it uses to gate SQLite-only tools). Consumed by
 * api/tools-catalog.php, which serves it as JSON to the launcher overlay
 * (cma-launcher) — the single tools menu. Keep it free of output/redirects so
 * it can be included anywhere. Uses fully-qualified class names (no use-imports).
 */

/**
 * True when any (non-deprecated) configured database connection uses SQLite.
 * Reads databases.json via ConfigLoader — no live DB connection, so it's cheap
 * and safe at menu-build time. Gates the SQLite-only repair tool out of the menu
 * on Access / SQL Server / MySQL sites (where it was pointless and always reported
 * "alles goed" on a database that isn't even SQLite). Fails closed: if the config
 * can't be read, the tool stays hidden.
 */
function cma_db_uses_sqlite(): bool
{
    try {
        foreach (\Cma\ConfigLoader::getDatabases() as $db) {
            if (!empty($db['deprecated'])) {
                continue;
            }
            $type = strtolower((string)($db['type'] ?? ''));
            $cs = strtolower((string)($db['connectionString'] ?? ''));
            if ($type === 'sqlite'
                || strncmp($cs, 'sqlite:', 7) === 0
                || preg_match('/\.(sqlite3?|db)\b/', $cs) === 1) {
                return true;
            }
        }
    } catch (\Throwable $e) {
        // fall through to false — hide the tool when config is unreadable
    }
    return false;
}

function buildToolsTreeData(bool $isDeveloper): array
{
    $folders = [];

    // === STANDAARD TOOLS ===
    $standardFolder = [
        'type' => 'folder',
        'label' => 'Standaard',
        'icon' => 'lnr-layers',
        'children' => [
            ['type' => 'item', 'label' => 'Server informatie', 'href' => 'tools/tools_serverinfo.php', 'target' => 'R', 'icon' => 'lnr-laptop'],
            ['type' => 'item', 'label' => 'Cache leegmaken', 'badge' => 'A', 'href' => 'tools/tools_clearcache.php', 'target' => 'R', 'icon' => 'lnr-trash'],
            ['type' => 'item', 'label' => 'Onderhoudsscherm', 'badge' => 'A', 'href' => 'tools/tools_maintenance.php', 'target' => 'R', 'icon' => 'lnr-warning'],
        ]
    ];
    $folders[] = $standardFolder;

    // === GEBRUIKERS EN GROEPEN ===
    $usersFolder = [
        'type' => 'folder',
        'label' => 'Gebruikers en groepen',
        'badge' => 'A',
        'icon' => 'lnr-users',
        'children' => [
            ['type' => 'item', 'label' => 'Gebruikers', 'badge' => 'A', 'href' => 'form.php?form=users', 'target' => 'R', 'icon' => 'lnr-user'],
            ['type' => 'item', 'label' => 'Groepen', 'badge' => 'A', 'href' => 'form.php?form=groups', 'target' => 'R', 'icon' => 'lnr-users'],
        ]
    ];
    $folders[] = $usersFolder;

    // === SITE GEZONDHEID ===
    $healthFolder = [
        'type' => 'folder',
        'label' => 'Site gezondheid',
        'icon' => 'lnr-heart-pulse',
        'children' => [
            ['type' => 'item', 'label' => 'Logbestanden lezen', 'href' => 'tools/logreader.php', 'target' => 'R', 'icon' => 'lnr-list'],
            ['type' => 'item', 'label' => 'E-mail log', 'badge' => 'A', 'href' => 'form.php?form=emaillog', 'target' => 'R', 'icon' => 'lnr-envelope'],
            ['type' => 'item', 'label' => 'Controleer bestanden', 'badge' => 'A', 'href' => 'tools/tools_db_consistency.php', 'target' => 'R', 'icon' => 'lnr-sync'],
            ['type' => 'item', 'label' => 'LLM management', 'badge' => 'A', 'href' => 'tools/tools_llm.php', 'target' => 'R', 'icon' => 'lnr-brain'],
        ]
    ];
    $folders[] = $healthFolder;

    // === DATABASE ===
    $dbFolder = [
        'type' => 'folder',
        'label' => 'Database',
        'icon' => 'lnr-database',
        'children' => [
            ['type' => 'item', 'label' => 'Database structuur', 'badge' => 'A', 'href' => 'tools/tools_dbsummary.php', 'target' => 'R', 'icon' => 'lnr-list'],
            ['type' => 'item', 'label' => 'Database backup', 'badge' => 'A', 'href' => 'tools/tools_backup.php', 'target' => 'R', 'icon' => 'lnr-download'],
            ['type' => 'item', 'label' => 'Backups beheren', 'badge' => 'A', 'href' => 'tools/tools_backup.php?tab=manage', 'target' => 'R', 'icon' => 'lnr-undo'],
            ['type' => 'item', 'label' => 'Platform updates', 'badge' => 'A', 'href' => 'tools/tools_migrations.php', 'target' => 'R', 'icon' => 'lnr-arrow-right'],
            ['type' => 'item', 'label' => 'SQL uitvoeren', 'badge' => 'D', 'href' => 'tools/tools_query.php', 'target' => 'R', 'icon' => 'lnr-code'],
            ['type' => 'item', 'label' => 'Indexen aanmaken', 'badge' => 'D', 'href' => 'tools/tools_create_indexes.php', 'target' => 'R', 'icon' => 'lnr-list'],
        ]
    ];

    // SQLite-reparatie alleen tonen als er echt een SQLite-connectie is. Op
    // Access/SQL Server/MySQL-sites is de tool zinloos — hij meldde daar altijd
    // "alles goed" op een database die niet eens SQLite is.
    if (cma_db_uses_sqlite()) {
        $dbFolder['children'][] = ['type' => 'item', 'label' => 'SQLite reparatie', 'badge' => 'A', 'href' => 'tools/tools_sqlite_repair.php', 'target' => 'R', 'icon' => 'lnr-sync'];
    }

    $folders[] = $dbFolder;

    // === RAPPORTAGES ===
    $reportsFolder = [
        'type' => 'folder',
        'label' => 'Rapportages',
        'icon' => 'lnr-chart-bars',
        'children' => [
            ['type' => 'item', 'label' => 'CMA Monitoring', 'badge' => 'A', 'href' => 'form.php?form=cmamonitoring', 'target' => 'R', 'icon' => 'lnr-chart-bars'],
        ]
    ];
    $folders[] = $reportsFolder;

    // === FRONT-END ===
    $frontendFolder = [
        'type' => 'folder',
        'label' => 'Front-end',
        'icon' => 'lnr-screen',
        'children' => [
            ['type' => 'item', 'label' => 'Content blocks', 'href' => 'form.php?form=contentblocks', 'target' => 'R', 'icon' => 'lnr-text-format'],
            ['type' => 'item', 'label' => 'Marketing URLs/Redirects', 'href' => 'form.php?form=marketingurl', 'target' => 'R', 'icon' => 'lnr-link'],
            ['type' => 'item', 'label' => 'WebP beeld-conversie', 'href' => 'tools/tools_webp_convert.php', 'target' => 'R', 'icon' => 'lnr-picture'],
        ]
    ];
    $folders[] = $frontendFolder;

    // === DOCUMENTATIE ===
    // Centrale plek voor onderwerpen die een beheerder of ontwikkelaar
    // moet kennen. Storybook hoort hier ook bij omdat het levende
    // component-documentatie is, geen tool die data manipuleert.
    $docsFolder = [
        'type' => 'folder',
        'label' => 'Documentatie',
        'icon' => 'lnr-book',
        'children' => [
            ['type' => 'item', 'label' => 'Overzicht',          'href' => 'tools/documentation.php', 'target' => 'R', 'icon' => 'lnr-book'],
            ['type' => 'item', 'label' => 'Component Storybook','badge' => 'D', 'href' => 'tools/storybook.php', 'target' => 'R', 'icon' => 'lnr-bubble'],
        ]
    ];
    $folders[] = $docsFolder;

    // === DEVELOPER HULPMIDDELEN (Developer only) ===
    if ($isDeveloper) {
        $devFolder = [
            'type' => 'folder',
            'label' => 'Developer',
            'badge' => 'D',
            'icon' => 'lnr-code',
            'children' => [
                ['type' => 'item', 'label' => 'CMA menu', 'href' => 'form.php?form=_menus', 'target' => 'R', 'icon' => 'lnr-menu'],
                ['type' => 'item', 'label' => 'Formulierdefinities', 'href' => 'tools/tools_formedit.php', 'target' => 'R', 'icon' => 'lnr-pencil'],
                ['type' => 'item', 'label' => 'CMA ↔ DB synchronisatie', 'href' => 'tools/tools_db_sync.php', 'target' => 'R', 'icon' => 'lnr-sync'],
                ['type' => 'item', 'label' => 'Deploy setup', 'href' => 'tools/tools_deploy_setup.php', 'target' => 'R', 'icon' => 'lnr-rocket'],
                ['type' => 'item', 'label' => 'Formulier wizard', 'href' => 'tools/tools_formwiz.php', 'target' => 'R', 'icon' => 'lnr-magic-wand'],
                ['type' => 'folder', 'label' => 'Testen', 'icon' => 'lnr-checkmark-circle', 'children' => [
                    ['type' => 'item', 'label' => 'Cypress browsertests', 'href' => 'tools/tools_testrunner.php', 'target' => 'R', 'icon' => 'lnr-rocket'],
                    ['type' => 'item', 'label' => 'PHP unit tests', 'href' => 'tools/tools_phpunit.php', 'target' => 'R', 'icon' => 'lnr-checkmark-circle'],
                    ['type' => 'item', 'label' => 'Endpoint interactief testen', 'href' => 'tools/tools_endpoint_tester.php', 'target' => 'R', 'icon' => 'lnr-pulse'],
                ]],
            ]
        ];

        if (\App\Library\Application::get('local', '')) {
            if (file_exists(\App\Library\Server::mapPath('tools/tools_dev_copymod.php'))) {
                $devFolder['children'][] = ['type' => 'item', 'label' => 'Module kopiëren', 'href' => 'tools/tools_dev_copymod.php', 'target' => 'R', 'icon' => 'lnr-layers'];
            }
        }

        $folders[] = $devFolder;
    }

    // Site-specific tools: a consumer site surfaces its own root-level tools/*.php
    // scripts in the launcher by dropping a data/tools.json. Kept generic — the
    // platform never hardcodes a site's filenames. Format:
    //   { "groups": [ { "label": "...", "icon": "lnr-...", "items": [
    //       { "label": "...", "href": "/tools/x.php", "icon": "lnr-...", "badge": "A|D" } ] } ] }
    // Items with an absolute href (/tools/…, http…) open in a new tab (they are
    // plain scripts at the site root, not shell pages). A "D" badge is dev-only.
    // Site root is one level above cma/ (this file is cma/tools_catalog.inc);
    // the per-site data/ lives there (same as ReportsService's config path).
    // Prefer data/cma_tools.json (cma_-prefixed, consistent with cma_menu.json);
    // fall back to the legacy data/tools.json until migration 9.17.0 renames it.
    $siteToolsFile = dirname(__DIR__) . '/data/cma_tools.json';
    if (!is_file($siteToolsFile)) {
        $legacyToolsFile = dirname(__DIR__) . '/data/tools.json';
        if (is_file($legacyToolsFile)) {
            $siteToolsFile = $legacyToolsFile;
        }
    }
    if (is_file($siteToolsFile)) {
        $siteCfg = json_decode((string) @file_get_contents($siteToolsFile), true);
        foreach (($siteCfg['groups'] ?? []) as $g) {
            $children = [];
            foreach (($g['items'] ?? []) as $it) {
                if (empty($it['label']) || empty($it['href'])) {
                    continue;
                }
                if (($it['badge'] ?? '') === 'D' && !$isDeveloper) {
                    continue;
                }
                $child = [
                    'type'  => 'item',
                    'label' => (string) $it['label'],
                    'href'  => (string) $it['href'],
                    'icon'  => (string) ($it['icon'] ?? 'lnr-cog'),
                ];
                if (!empty($it['badge'])) {
                    $child['badge'] = (string) $it['badge'];
                }
                $children[] = $child;
            }
            if ($children) {
                $folders[] = [
                    'type'     => 'folder',
                    'label'    => (string) ($g['label'] ?? 'Site'),
                    'icon'     => (string) ($g['icon'] ?? 'lnr-layers'),
                    'children' => $children,
                ];
            }
        }
    }

    return $folders;
}
