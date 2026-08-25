<?php
/**
 * Composer Installer for stenversonline/platform
 *
 * Runs after `composer install` and `composer update` to copy
 * web-accessible files to the correct project directories.
 *
 * Usage in project composer.json:
 * {
 *     "scripts": {
 *         "post-install-cmd": "App\\Library\\Installer::postInstall",
 *         "post-update-cmd": "App\\Library\\Installer::postUpdate"
 *     }
 * }
 */

namespace App\Library;

use Composer\Script\Event;

class Installer
{
    /**
     * Files/directories that should NEVER be overwritten in the project.
     * These contain project-specific configuration.
     */
    private const PROTECTED_PATHS = [
        'data/cma_branding.json',
        'data/app.json',
        'data/databases.json',
        'data/cma_menu.json',
        'data/menu.json',
        'data/cma_reports.json',
        'data/reports.json',
        'data/cma_tools.json',
        'data/tools.json',
    ];

    /**
     * Paths (relative to project root) of files that were once shipped by
     * this package but have since been retired. syncDirectory() only copies
     * from source → dest; without an explicit list a consumer site keeps
     * the dead file forever after `composer update`. Add a row here when
     * you delete a file from the package and want it gone from existing
     * installs. Safe to leave entries here long-term — the cleanup just
     * no-ops once the file isn't there anymore.
     */
    private const REMOVED_PATHS = [
        // Retired: the classic-frameset menu-rendering page. It
        // emitted the l/n/f JS arrays into a `parent.window.frames['C']` frameset
        // (glow_tabs / menu_init()) — dead since the CMA moved to the sidebar
        // layout. Nothing loads it; the menu-building helpers it shared live on
        // in menurep.inc, which main.php/dashboard.php still require.
        'cma/menurep.php',
        'cma/tools/llm_models.php',
        // responsive-tabs: een derde tab-implementatie naast cma-tabs en de
        // pagetabs van de front-end, en de enige zonder consument — niets in cma/,
        // library/, module/, templates/ of op een site laadde hem. Drie manieren om
        // hetzelfde te doen maakt vooral onduidelijk welke je moet kiezen.
        'library/webcomponents/responsive-tabs/index.js',
        'library/webcomponents/responsive-tabs/responsive-tabs.css',
        'library/webcomponents/responsive-tabs/responsive-tabs.js',
        // LibTabs: de vierde tab-implementatie, en een mislukte conversie — Render()
        // las lokale variabelen die AddTab() nooit vulde, dus het strookje kwam er
        // nooit en de panelen bleven verborgen. De enige aanroeper (de
        // tabeleigenschappen-dialoog van de HTML-editor) gebruikt nu cma-tabs.
        'library/classes/class_tabs.inc',
        // De image-wizard is nooit in gebruik genomen: geen enkele pagina, tool of
        // bundel noemt hem, en de bestandsnamen zeiden dat zelf al. Ze werden wel
        // meegebouwd en meegeleverd, dus elke site droeg ze mee.
        'cma/assets/css/UNUSED_image-wizard.css',
        'cma/assets/css/UNUSED_image-wizard.min.css',
        'cma/assets/js/UNUSED_image-wizard.js',
        'cma/assets/js/UNUSED_image-wizard.min.js',
        // Twee componenten die nooit zijn aangesloten: hun tags (<cma-checklist>,
        // <cma-rights-matrix>) komen alleen voor in hun eigen bestand, geen bundel
        // laadt ze. De naam zei het al.
        'cma/webcomponents/UNUSED_cma-checklist.js',
        'cma/webcomponents/UNUSED_cma-checklist.min.js',
        'cma/webcomponents/UNUSED_cma-rights-matrix.js',
        'cma/webcomponents/UNUSED_cma-rights-matrix.min.js',
        // Lege plaatshouder. lib-combo.js registreert <cma-combo> zelf, dus dit
        // bestand deed niets — het stond er alleen om te vertellen dat het niets deed.
        'cma/webcomponents/cma-combo.js',
        'cma/webcomponents/cma-combo.min.js',
        // lib-menu had geen enkele consument: geen pagina, geen tool, geen site —
        // alleen een storybook-voorbeeld. Het zat wél in de CMA-bundel, dus elke
        // pagina haalde het op. Contextmenu's in de CMA zijn .cma-context-menu.
        'library/webcomponents/lib-menu.js',
        'library/webcomponents/lib-menu.min.js',
        // Tweede LibTable, in de Cma-namespace. Geen enkele require_once noemt hem en
        // de Cma-namespace wordt niet ge-autoload, dus deze class draaide nooit. De
        // tabelklasse die wél draait is library/classes/class_table.inc.
        'cma/classes/LibTable.php',
        // Replaced by cypress/e2e/auth/login-layout.cy.js. The old file only
        // measured the login box and wrote the numbers to a JSON file — it had
        // no assertions, so it could never fail. Two specs measuring the same
        // thing, one of which always passes, is worse than one.
        'cma/cypress/e2e/diag/login-width.cy.js',
        // Removed: the standalone "Database health check" tool
        // (tools/db_health.php) is dropped from the tools menu and repo. Clean
        // the synced copy off consumer sites so its URL stops resolving.
        'cma/tools/db_health.php',
        // Removed: the "Database compacteren" tool was a dead stub —
        // Access compaction needs JRO/DAO COM (unavailable in PHP) and can't be
        // done over ODBC at all, so it only ever printed a "do it in Access"
        // notice (with a stale path). Compact manually in Access instead.
        'cma/tools/tools_dbcompact.php',
        // Removed: two obsolete developer-only tools. The SQL Server
        // migration-prep helper and the repository exporter both targeted the
        // deprecated repository DB / one-off migration path and are no longer used.
        'cma/tools/tools_migrate_prepare.php',
        'cma/tools/tools_export_repository.php',
        'cma/tools/tools_export_repository_cli.php',
        // Renumbered: these two migrations were never registered in
        // config/migrations.json, so the high-water-mark runner never applied
        // them (leaving tblCMAMarketingUrl / api_call_log missing on sites past
        // 9.13.0). Re-issued as 9.14.0 / 9.15.0 (above target) so they become
        // pending everywhere. Drop the old-named orphan files from sites.
        'cma/migrations/9.10.0_create_tblcmamarketingurl.php',
        'cma/migrations/9.11.0_create_api_call_log.php',
        // Retired: the legacy IE/ActiveX image-upload wizard (an <OBJECT> COM
        // control + IE-only event scripting) and its POST target. Dead in any
        // modern browser and reached by nothing; superseded by the file-browser
        // wizard (cma/wizards/file-browser.php) and imageupload_crop.php.
        'cma/imageupload.php',
        'cma/imageupload_action.php',
        // Markdown docs retired (Phase 1 of the in-CMA
        // documentation hub at cma/tools/documentation.php). Their
        // content was inlined into the topics there. See CLAUDE.md
        // "No new .md documentation files" rule.
        'cma/docs/iis-setup.md',
        'cma/docs/logging.md',
        // Phase 2 retirements
        'cma/docs/architecture_review.md',
        'cma/docs/json.md',
        'cma/docs/forms.md',
        'cma/docs/api-reference.md',
        'cma/docs/menuicons.md',
        // Moved: the full Linearicons class reference now lives at
        // library/css/linearicons.css (the RINO front-end links it there, and the
        // storybook reads it from there). Remove the old cma/docs copy so sites
        // don't keep a stale duplicate.
        'cma/docs/linearicons.css',
        // Retired: BOTH the standalone single-file webhook and the
        // framework webhook (cma/tools/deploy_webhook.php → DeployWebhook) are
        // superseded by the one-and-only site-root recovery hatch /deploy.php
        // (ROOT_SYNCED_FILES), which folds in their full feature set. Re-point
        // any GitHub webhook still on these URLs to /deploy.php before this
        // lands, or auto-deploy 404s on that site. (The DeployWebhook class
        // under src/helpers/ lives in vendor/ — composer drops it, so it needs
        // no REMOVED_PATHS entry.)
        'cma/tools/deploy_webhook_standalone.php',
        // Renamed with the configs they validate: app.json -> cma_branding.json
        // and reports.json -> cma_reports.json. syncDirectory() only copies, so
        // the old schema files linger on every existing site and a stale
        // `$schema` reference keeps resolving to them — hiding the fact that it
        // points at the wrong file. Migration 9.21.0 re-points those references
        // at the current names; these rows remove what they pointed at before.
        'cma/config/schema/app.schema.json',
        'cma/config/schema/reports.schema.json',
        // Removed: a second, drifted copy of every schema. A migration once
        // moved config/schema/ to cma/schema/, but the canonical location is
        // cma/config/schema/ — so the copy stayed behind and fell out of date
        // (its form-definition.schema.json predates `hiddenWhen`). Worse, it
        // kept references that point at the wrong directory resolving, which is
        // exactly what hides them. Migration 9.21.0 re-points those references
        // at cma/config/schema/ before these rows take the old files away.
        'cma/schema/app.schema.json',
        'cma/schema/contentblocks.schema.json',
        'cma/schema/control-types.schema.json',
        'cma/schema/data-sources.schema.json',
        'cma/schema/databases.schema.json',
        'cma/schema/form-definition.schema.json',
        'cma/schema/menu.schema.json',
        'cma/schema/migrations.schema.json',
        'cma/schema/modules.schema.json',
        'cma/schema/reports.schema.json',
        // Hernummerd: deze drie versienummers zijn later opnieuw uitgegeven aan een
        // ANDERE migratie (9.16.0 aan de menu-hernoeming, 9.17.0 aan de tools-hernoeming;
        // 9.18.0 staat helemaal niet meer in config/migrations.json). De scripts zelf zijn
        // uit het pakket verdwenen, maar syncDirectory kopieert alleen vooruit — dus op
        // bestaande sites blijven ze staan als bestanden die de runner nooit aanroept.
        // Dat is niet alleen rommel: wie de map bekijkt ziet twee scripts op hetzelfde
        // nummer en kan niet zien welke telt.
        'cma/migrations/9.16.0_consolidate_cache_to_dot_cache.php',
        'cma/migrations/9.17.0_move_xslt_to_assets.php',
        'cma/migrations/9.18.0_fix_xslt_path_constant.php',
        'cma/tools/deploy_webhook.php',
        // Retired: the /cma/tools/ deploy-status endpoint sat under
        // the gitignored /cma/ tree — it 404s during exactly the botched
        // deploy you'd want to inspect. The git-tracked site-root twin
        // /deploy_status.php (ROOT_SYNCED_FILES) is the single status endpoint.
        'cma/tools/deploy_status.php',
        // Removed: migration 2.2.0 (JavaScript error logging table) targeted the
        // DEPRECATED_rep database, which is not part of the platform config. The
        // migration and its SQL script were dropped; this removes the synced copy.
        'cma/migrations/sql/2.2.0_javascript_errors.sql',
        // Removed: duplicate migrations.json. The single source of truth is
        // cma/config/migrations.json (read by MigrationService, Bootstrap and
        // ConfigLoader). 9.9.0 used to move it here, splitting it from the file
        // the runner reads; that move was dropped and this stale copy deleted.
        'cma/migrations/migrations.json',
        // Removed: lib_htmleditor.inc defined a single dead function
        // (lib_HTMLEditorInit) with no call site anywhere, plus a legacy
        // CreateFKEditor JS duplicate long superseded by cma/assets/js/cma.js
        // and cma/webcomponents/cma-htmledit.js. Its require in library.inc was
        // dropped; this removes the synced copy from consumer sites.
        'library/lib_htmleditor.inc',
        // Replaced by tools/build-minify.js. The shell script could not run on
        // the Windows/IIS boxes that need the build most — no bash — so a site
        // that followed the instruction got "command not found" and kept
        // serving every asset in full. Node runs everywhere the platform does.
        'cma/tools/build-minify.sh',
        // Windows junk file accidentally committed under the Linearicons font dir.
        // It was synced to consumers and its system/hidden/readonly attributes made
        // copy() fail ("Permission denied"), aborting the whole post-update sync.
        // Untracked now + skipped by syncDirectory; this cleans the synced copy.
        // (@unlink is best-effort, so a system-attribute file that won't delete is
        // a harmless no-op rather than a new failure.)
        'library/fonts/Linearicons/SVG/desktop.ini',
        // Retired: legacy "image/file maintenance" tool family that
        // walked the pre-JSON tblForms/tblControls schema plus on-disk image
        // folders. All superseded by tools_db_consistency.php (the only surfaced
        // one) + its POST target tools_consistency_picture_delete.php. These were
        // unsurfaced in the tools menu, self-declared deprecated, and reachable by
        // nothing. tools_contentblocks.php is superseded by form.php?form=contentblocks.
        'cma/tools/tools_missing_files.php',
        'cma/tools/tools_missing_pictures.php',
        'cma/tools/tools_show_pictures.php',
        'cma/tools/tools_set_picture_sizes.php',
        'cma/tools/tools_picture_analyse.php',
        'cma/tools/tools_picture_analyse_delete.php',
        'cma/tools/tools_contentblocks.php',
        // Relocated: the general error handler's assets moved under
        // library/assets/ (was library/error-handler.js[.min.js] and
        // library/css/errorhandler.css). New locations: library/assets/js/ and
        // library/assets/css/. Without these removals the old copies linger and,
        // for errorhandler.css, a stale file at the old path could still be
        // hit by any not-yet-updated reference.
        'library/error-handler.js',
        'library/error-handler.min.js',
        'library/css/errorhandler.css',
        // Removed: dead static CSS bundles. The CMA serves all CSS
        // through minify.php, which minifies the SOURCE files (cma_css_bundle())
        // on the fly and only ever prefers a pre-built .min.JS — never a .min.CSS.
        // build-minify.js doesn't generate these either (it only does
        // webcomponents/*.css). So these three were orphaned artifacts referenced
        // by nothing; drop the synced copies off consumer sites.
        'cma/assets/css/form.min.css',
        'cma/assets/css/style.min.css',
        'cma/assets/css/main.min.css',
        // Removed: the assets/js/modules/ ES-module set was an
        // extract-of-form-controller that was never wired up — nothing loads
        // it (the files use `export` syntax, so a plain <script> tag would
        // throw), no page uses type="module", and no consumer app references
        // it. The live implementations are the guarded inline copies in
        // form-controller.js. Deleted to remove the confusable parallel path.
        'cma/assets/js/modules/cma-api-error.js',
        'cma/assets/js/modules/cma-combo-cache.js',
        'cma/assets/js/modules/cma-form-cache.js',
        'cma/assets/js/modules/cma-notification.js',
        'cma/assets/js/modules/cma-perf.js',
        'cma/assets/js/modules/cma-record-cache.js',
        'cma/assets/js/modules/cma-request-coalescer.js',
        'cma/assets/js/modules/index.js',
        // Removed: the stale CMA-local error-handler copy. Every
        // loader (main.php standalone tag, cma_js_bundle, cma_form_js_bundle,
        // image-editor, file-browser, file_frameset) uses
        // library/assets/js/error-handler.js; this fork was frozen and stale
        // and silently missed the double-load guard, the resource-error
        // capture handler and later fixes.
        'cma/assets/js/error-handler.js',
        'cma/assets/js/error-handler.min.js',
        // Removed: legacy image-based a.tt tooltip styling. Nothing
        // links tooltip.css and no a.tt markup exists anywhere; the live systems
        // are the data-tooltip CSS/JS engine (cma-utils.js + style.css) and the
        // lib-tip component. The two gifs were only referenced by tooltip.css.
        'library/tooltip.css',
        'library/images/tooltip.gif',
        'library/images/tooltip_filler.gif',
        // Removed: cma/tools/config/ was a RINO-specific config
        // snapshot (app/menu/data-sources/databases/control-types/reports)
        // that leaked into the initial package import and shipped to every
        // consumer site since. Nothing reads it — ConfigLoader points at the
        // site's data/ dir and the bundled defaults live in cma/config/.
        'cma/tools/config/app.json',
        'cma/tools/config/control-types.json',
        'cma/tools/config/data-sources.json',
        'cma/tools/config/databases.json',
        'cma/tools/config/menu.json',
        'cma/tools/config/reports.json',
        // Removed 2026-07-20: the classic CMA 404 template. It only did
        // require header/footer + Response::redirect('?PageAction=404') — a dead
        // indirection (it would infinite-loop if it WERE the PageAction=404 handler,
        // so it never was), referenced by no code. Front-end 404s are handled by
        // the consumer's own handler now; drop the synced copy off consumer sites.
        'cma/templates/404.inc',
        // Renamed: the Vb helper became Cdate (Vb.php → src/library/Cdate.php;
        // VbTest → CdateTest). CdateTest.php now ships, but composer's additive sync leaves
        // the old VbTest.php twin behind on existing installs — where it still require's the
        // moved src/helpers/Vb.php, so it's a broken file until removed.
        'cma/tests/VbTest.php',
    ];

    /**
     * Platform-owned files copied to the project root and ALWAYS overwritten
     * (unlike TEMPLATE_FILES, which are copy-if-absent). Use this for shared
     * ops/tooling that must stay in lock-step with the platform AND must live
     * at the site root rather than inside the gitignored /cma/ tree.
     *
     * deploy.php + deploy_status.php are the deploy recovery hatch + its
     * status endpoint: git-tracked root files that survive a broken /cma/
     * sync (the failure that historically stranded deploys, because the old
     * webhook lived under /cma/tools/). Consumers commit them; this keeps
     * them current on every `composer update`. See DeployHealth + the
     * templates/deploy*.php.template headers.
     *
     * cma/package.json is the manifest for the asset build. It cannot be the
     * one in the platform checkout: that one carries Cypress and jsdom, which
     * .gitattributes keeps off a consumer's production server on purpose. This
     * template is the build half of it — terser and lightningcss, nothing else
     * — so `cd cma && npm install && npm run build:minify` works on a site
     * without dragging a test runner onto it. Always overwritten, like the
     * deploy hatch: a site that wants its own scripts has its own package.json
     * at the root, and this file is the platform's to keep current.
     *
     * Map of: <template under templates/> => <target relative to site root>.
     */
    private const ROOT_SYNCED_FILES = [
        'deploy.php.template'        => 'deploy.php',
        'deploy_status.php.template' => 'deploy_status.php',
        'cma-package.json.template'  => 'cma/package.json',
    ];

    /**
     * Template files that are copied to the project root only if they don't exist.
     * These are one-time setup files.
     */
    /**
     * De verouderde onderhoudspoort in een bestaand _bootstrap.php vervangen —
     * uitsluitend wanneer daar het ongewijzigde platformblok van een vorige
     * generatie staat.
     *
     * _bootstrap.php is met opzet copy-if-absent: het is site-eigen terrein.
     * Maar dit ene blok is platformlogica die daar toevallig woont, en de oude
     * generatie liet /cma/ ALTIJD door — ook achter een deploy-vlag, terwijl
     * cma/ dan zelf half verwisseld is. Geen enkele site kreeg de reparatie
     * ooit binnen, want niets overschrijft het bestand. Vandaar deze middenweg:
     * staat het oude blok er byte-voor-byte in (dus onaangepast), dan mag het
     * mee naar de huidige vorm; is er ook maar iets aan veranderd, dan blijven
     * we eraf en wijst de live check in de documentatie de beheerder de weg.
     *
     * PUUR: broncode erin, nieuwe broncode eruit — of null wanneer er niets
     * (veilig) te vervangen valt.
     */
    public static function upgradeMaintenanceGate(string $source): ?string
    {
        $oud = "    \$uri = (string) (\$_SERVER['REQUEST_URI'] ?? '');\n"
             . "    if (strpos(\$uri, '/cma/') === 0\n"
             . "        || preg_match('#(^|/)(deploy|deploy_status)\\\\.php(\\\\?|\$)#', \$uri)) {\n"
             . "        return;\n"
             . "    }\n";
        if (strpos($source, $oud) === false) {
            return null;
        }
        $nieuw = "    \$uri = (string) (\$_SERVER['REQUEST_URI'] ?? '');\n"
              . "    if (preg_match('#(^|/)(deploy|deploy_status)\\\\.php(\\\\?|\$)#', \$uri)) {\n"
              . "        return;\n"
              . "    }\n"
              . "    // /cma/ alleen bij een HANDMATIGE vlag: daar staat de uitknop, dus daar\n"
              . "    // mag je jezelf niet kunnen buitensluiten. Een deploy-vlag betekent dat\n"
              . "    // cma/ zelf half verwisseld is — dan toont ook de beheerkant de 503 in\n"
              . "    // plaats van halve code met fatale fouten. De deploy heeft hier niets\n"
              . "    // nodig: de migratieloper is CLI en de deploy-endpoints staan hierboven.\n"
              . "    if (\$manual && strpos(\$uri, '/cma/') === 0) {\n"
              . "        return;\n"
              . "    }\n";
        return str_replace($oud, $nieuw, $source);
    }

    private const TEMPLATE_FILES = [
        '_bootstrap.php.template'         => '_bootstrap.php',
        '_bootstrap_wrapper.php.template' => '_bootstrap_wrapper.php',
        'src/pages/maintenance.php.template' => 'src/pages/maintenance.php',
        '404.php.template'                => '404.php',
        'web.config.template'             => 'web.config',
        'app.php.template'                => 'app.php',
        'global.asa.php.template'         => 'global.asa.php',
        '.env.example'                    => '.env.example',
        'cma.css.template'                => 'assets/css/cma.css',
    ];

    /**
     * The per-site JSON configs under data/, and what an empty one looks like.
     *
     * A fresh install (casa is the bare case) has no data/ at all, so every
     * screen that edits one of these had to cope with a file that isn't there
     * — and the CMA's own config editors have nothing to open. Creating them
     * empty at install time removes that state: the operator gets a file with
     * its `$schema` already pointing at the right place, and every reader sees
     * exactly what it saw before, because for each of these an empty document
     * is what the reader falls back to when the file is missing.
     *
     * `legacy` is the pre-rename file name. It is the reason this can't be a
     * plain "create if absent": MenuService, ReportsService, the tools catalog
     * and cma_get_app_logo() all prefer the cma_-prefixed name and only fall
     * back to the legacy one. Writing an empty data/cma_menu.json onto a site
     * that still has data/menu.json would not add a default — it would shadow
     * the site's real menu with an empty one. So a config is only created when
     * NEITHER name is present.
     *
     * cma_branding.json is the one that is not written empty: cma_get_app_logo()
     * takes the first file that exists, so an empty one would shadow the bundled
     * cma/config/cma_branding.json and leave the CMA header without a logo. It is
     * seeded with those bundled defaults instead, which renders identically and
     * is then the operator's to change.
     *
     * @return array<string, array{legacy: ?string, content: array}>
     */
    private static function siteConfigDefaults(string $platformDir): array
    {
        $schema = static fn(string $name): string => '../cma/config/schema/' . $name . '.schema.json';

        $branding = ['$schema' => $schema('cma_branding'), 'company' => []];
        $bundled = @file_get_contents($platformDir . '/cma/config/cma_branding.json');
        $decoded = $bundled === false ? null : json_decode($bundled, true);
        if (is_array($decoded) && isset($decoded['company'])) {
            $branding = ['$schema' => $schema('cma_branding'), 'company' => $decoded['company']];
        }

        return [
            'cma_menu.json' => [
                'legacy'  => 'menu.json',
                'content' => ['$schema' => $schema('menu'), 'version' => '1.0.0', 'menus' => []],
            ],
            'cma_reports.json' => [
                'legacy'  => 'reports.json',
                'content' => ['$schema' => $schema('cma_reports'), 'version' => '1.0.0', 'reports' => []],
            ],
            'cma_tools.json' => [
                'legacy'  => 'tools.json',
                'content' => ['$schema' => $schema('cma_tools'), 'groups' => []],
            ],
            'cma_branding.json' => [
                'legacy'  => 'app.json',
                'content' => $branding,
            ],
            'databases.json' => [
                'legacy'  => null,
                'content' => ['$schema' => $schema('databases'), 'version' => '2.0.0', 'databases' => []],
            ],
            'image-profiles.json' => [
                'legacy'  => null,
                'content' => ['profiles' => (object) [], 'managed_paths' => []],
            ],
        ];
    }

    /**
     * Create the per-site configs under data/ that this site does not have yet.
     *
     * Never touches an existing file — not the new name, not the legacy one.
     * Public for testability; run() is composer-bound, this isn't.
     *
     * @return string[] Log lines, one per file created (empty when nothing was missing).
     */
    public static function ensureSiteConfigs(string $projectRoot, string $platformDir): array
    {
        $dir = $projectRoot . '/data';
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            return ['<warning>kon data/ niet aanmaken — per-site configs overgeslagen</warning>'];
        }

        $created = [];
        foreach (self::siteConfigDefaults($platformDir) as $name => $spec) {
            if (is_file($dir . '/' . $name)) {
                continue;
            }
            if ($spec['legacy'] !== null && is_file($dir . '/' . $spec['legacy'])) {
                continue;
            }
            $json = json_encode($spec['content'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (@file_put_contents($dir . '/' . $name, $json . "\n") !== false) {
                $created[] = 'created data/' . $name;
            }
        }
        return $created;
    }

    /**
     * Run BEFORE composer install/update: put the site in maintenance.
     *
     * Tijdens een update wordt vendor/ verwisseld en draait de site op half
     * bijgewerkte code. deploy.php hijst daarvoor zelf een vlag, maar een
     * `composer update` die iemand in een shell start deed dat niet — dan kregen
     * bezoekers in dat venster stapels fouten. Deze hook dekt beide gevallen af.
     *
     * De vlag is bewust LEEG (geen "manual"-markering): _bootstrap.php laat een
     * niet-handmatige vlag na 20 minuten vanzelf vallen, zodat een afgebroken of
     * gekilde composer-run de site niet permanent dichtzet.
     *
     * Ook /cma/ zit achter deze vlag: tijdens de update wordt de cma-tree zelf
     * verwisseld, dus de beheerkant is precies zo stuk als de voorkant. Alleen
     * een hándmatige vlag laat /cma/ door — daar staat de uitknop.
     */
    public static function preOperation(Event $event): void
    {
        $root = self::projectRoot($event);
        $flag = $root . '/maintenance.flag';
        // Een handmatig ingeschakelde onderhoudsstand (vanuit de CMA) blijft staan
        // en wordt straks ook niet door ons opgeheven.
        if (is_file($flag) && strpos((string) @file_get_contents($flag), '"manual"') !== false) {
            $event->getIO()->write('<info>Onderhoudsstand stond al handmatig aan — ongemoeid gelaten.</info>');
            return;
        }
        if (@file_put_contents($flag, '') !== false) {
            $event->getIO()->write('<info>Onderhoudspagina AAN tijdens composer (site én /cma).</info>');
        }
    }

    /**
     * Run after composer install.
     */
    public static function postInstall(Event $event): void
    {
        self::run($event);
        self::liftMaintenance($event);
    }

    /**
     * Run after composer update.
     */
    public static function postUpdate(Event $event): void
    {
        self::run($event);
        self::liftMaintenance($event);
    }

    /** Onderhoudsstand weer uit — maar nooit een handmatige vlag opheffen. */
    private static function liftMaintenance(Event $event): void
    {
        $flag = self::projectRoot($event) . '/maintenance.flag';
        if (!is_file($flag)) {
            return;
        }
        if (strpos((string) @file_get_contents($flag), '"manual"') !== false) {
            $event->getIO()->write('<info>Onderhoudsstand blijft aan (handmatig ingeschakeld).</info>');
            return;
        }
        if (@unlink($flag)) {
            $event->getIO()->write('<info>Onderhoudspagina UIT.</info>');
        }
    }

    /** De projectmap: vendor/ ligt altijd één niveau onder de siteroot. */
    private static function projectRoot(Event $event): string
    {
        return dirname((string) $event->getComposer()->getConfig()->get('vendor-dir'));
    }

    /**
     * Main installation logic.
     */
    private static function run(Event $event): void
    {
        $io = $event->getIO();
        $vendorDir = $event->getComposer()->getConfig()->get('vendor-dir');
        $projectRoot = dirname($vendorDir); // vendor is always one level deep
        $platformDir = $vendorDir . '/stenversonline/platform';

        if (!is_dir($platformDir)) {
            $io->write('<warning>stenversonline/platform not found in vendor — skipping install</warning>');
            return;
        }

        $io->write('<info>stenversonline/platform: syncing shared files...</info>');

        // 0. Clean up files that have been retired from the package. Without
        //    this step, composer update copies the new files but leaves the
        //    old ones lingering on the consumer site. Runs before sync so
        //    a re-added file with the same path isn't accidentally wiped.
        foreach (self::cleanRemovedPaths($projectRoot) as $relPath) {
            $io->write('  - removed (retired): ' . $relPath);
        }

        // Per-file copy failures are collected across all three directory syncs
        // and reported together at the very end (step 9). A single unwritable
        // file no longer aborts the sync, so critical top-level files like
        // library/library.inc always get overwritten even if some junk/locked
        // file elsewhere in the tree fails.
        $syncErrors = [];

        // 1. Sync library → /library/
        $librarySrc = $platformDir . '/library';
        $libraryDest = $projectRoot . '/library';
        if (is_dir($librarySrc)) {
            $syncErrors = array_merge($syncErrors, self::syncDirectory($librarySrc, $libraryDest, [], $io));
            $io->write('  - library/ synced');
        }

        // 2. Sync CMA → /cma/ (with protected configs)
        $cmaSrc = $platformDir . '/cma';
        $cmaDest = $projectRoot . '/cma';
        if (is_dir($cmaSrc)) {
            $syncErrors = array_merge($syncErrors, self::syncDirectory($cmaSrc, $cmaDest, self::PROTECTED_PATHS, $io));
            $io->write('  - cma/ synced');
        }

        // 3. Sync module → /module/
        $moduleSrc = $platformDir . '/module';
        $moduleDest = $projectRoot . '/module';
        if (is_dir($moduleSrc)) {
            $syncErrors = array_merge($syncErrors, self::syncDirectory($moduleSrc, $moduleDest, [], $io));
            $io->write('  - module/ synced');
        }

        // 5. Copy template files (only if they don't exist in project)
        $templatesDir = $platformDir . '/templates';
        if (is_dir($templatesDir)) {
            foreach (self::TEMPLATE_FILES as $template => $target) {
                $src = $templatesDir . '/' . $template;
                $dest = $projectRoot . '/' . $target;
                if (file_exists($src) && !file_exists($dest)) {
                    self::copyFile($src, $dest);
                    $io->write("  - created $target (from template)");
                }
            }

            // Bestond _bootstrap.php al, kijk dan of de verouderde
            // onderhoudspoort erin staat — ongewijzigd — en werk die bij.
            $bootstrapPad = $projectRoot . '/_bootstrap.php';
            if (is_file($bootstrapPad)) {
                $huidig = (string) @file_get_contents($bootstrapPad);
                $bijgewerkt = self::upgradeMaintenanceGate($huidig);
                if ($bijgewerkt !== null && @file_put_contents($bootstrapPad, $bijgewerkt) !== false) {
                    $io->write('  - _bootstrap.php: onderhoudspoort bijgewerkt (deploy-vlag sluit nu ook /cma/ af)');
                }
            }

            // 5a. Always-synced files — overwritten on every update so platform
            //     fixes to the deploy recovery hatch and to the asset-build
            //     manifest reach every consumer.
            foreach (self::ROOT_SYNCED_FILES as $template => $target) {
                $src  = $templatesDir . '/' . $template;
                $dest = $projectRoot . '/' . $target;
                if (file_exists($src)) {
                    self::copyFile($src, $dest);
                    $io->write("  - synced $target");
                }
            }
        }

        // 5aa. Create the per-site configs under data/ that this site is missing.
        //      Copy-if-absent like the templates above, but data/ is never synced
        //      so it needs its own step. See siteConfigDefaults() for why an empty
        //      file is safe here and why the legacy names are checked too.
        foreach (self::ensureSiteConfigs($projectRoot, $platformDir) as $line) {
            $io->write('  - ' . $line);
        }

        // 5b. Ensure the parent web.config carries the CMA friendly-URL
        //     rewrite rules. Uses the same fail-safe patch/validate logic as
        //     migration 9.9.0 (shared helper): simplexml-check, XML
        //     well-formedness, duplicate-name + PCRE-regex validation, backup,
        //     atomic temp-write + rename, read-back, rollback. Idempotent via
        //     marker, so it's safe to run on every update. The appcmd +
        //     live-HTTP smoke-test stay migration-only (no IIS/HTTP context at
        //     composer time). Never throws — a routing-rule failure must not
        //     break the composer run.
        $parentWebConfig = $projectRoot . '/web.config';
        if (is_file($parentWebConfig)) {
            // Defensive load in case the package PSR-4 autoload isn't warm yet.
            if (!class_exists(WebConfigCmaRoutes::class)) {
                require __DIR__ . '/helpers/WebConfigCmaRoutes.php';
            }
            $res = WebConfigCmaRoutes::applyToParent($parentWebConfig);
            foreach ($res['messages'] as $line) {
                $io->write('  - web.config: ' . $line);
            }
            foreach ($res['errors'] as $line) {
                $io->write('  - <warning>web.config: ' . $line . '</warning>');
            }
        }

        // 6. Copy _bootstrap_constants.inc (from platform)
        $constantsSrc = $platformDir . '/templates/_bootstrap_constants.inc';
        $constantsDest = $projectRoot . '/_bootstrap_constants.inc';
        if (file_exists($constantsSrc)) {
            self::copyFile($constantsSrc, $constantsDest);
        }

        // 7. Ensure writable directories exist
        foreach (['sessions', '.cache', '.logs'] as $dir) {
            $path = $projectRoot . '/' . $dir;
            if (!is_dir($path)) {
                @mkdir($path, 0755, true);
                $io->write("  - created $dir/");
            }
        }

        // 7a. Touch the root web.config FIRST to trigger the IIS application-pool
        //     recycle. IIS watches web.config; bumping its mtime restarts the
        //     worker (and its PHP FastCGI children) — the only way to flush the
        //     in-process OPcache/APCu that a file sync alone can't reach. This
        //     makes `composer update` self-applying, so a manual iisreset is no
        //     longer required for changed PHP to take effect. Guarded on
        //     existence so we never create an (empty, site-breaking) web.config.
        $rootWebConfig = $projectRoot . '/web.config';
        if (is_file($rootWebConfig) && @touch($rootWebConfig)) {
            $io->write('  - touched web.config (app-pool recycle → flushes OPcache/APCu)');
        }

        // 7b. THEN clear the regenerable disk caches (minified JS/CSS, cached form
        //     definitions) — AFTER the recycle, never before. Clearing before the
        //     recycle left a race that took the front-end down on deploy: an
        //     in-flight request still running on the OLD OPcache bytecode would
        //     regenerate the just-emptied .cache/cma/{forms,minify} with STALE
        //     output, which the freshly-recycled worker then served — the classic
        //     "first composer update breaks the site, a second one fixes it".
        //     Clearing last means the new worker repopulates the caches from the
        //     new code. (These files are shared with the web process, so clearing
        //     them from this composer CLI run IS effective.)
        foreach (self::clearRegenerableCaches($projectRoot) as $line) {
            $io->write('  - ' . $line);
        }

        // 8. Write manifest for tracking
        self::writeManifest($projectRoot, $platformDir);

        // 8b. Name the assets that will go out unminified. The serving layer falls
        //     back to the source whenever the .min is missing or older, which is the
        //     safe choice but a silent one: nothing anywhere says that a site is
        //     shipping a 137 KB stylesheet where 118 KB would do. This is the moment
        //     the operator is looking at the deploy, so this is where it belongs.
        foreach (self::reportUnminifiedAssets($projectRoot) as $line) {
            $io->write('  - ' . $line);
        }

        // 9. If any file could not be copied, the sync ran to completion (so
        //    the site got as much of the new version as possible) but the
        //    result is a mixed-version state. Fail loudly — an unmissable
        //    non-zero composer exit — so the operator fixes the underlying
        //    permission/disk/lock problem and re-runs, rather than discovering
        //    a half-updated site at runtime.
        if (!empty($syncErrors)) {
            $io->write('  - <warning>' . count($syncErrors) . ' file(s) could NOT be synced:</warning>');
            foreach ($syncErrors as $err) {
                $io->write('    <warning>' . $err . '</warning>');
            }
            throw new \RuntimeException(
                'stenversonline/platform: sync completed with ' . count($syncErrors)
                . ' file error(s) — the site is on MIXED versions. Fix the file '
                . 'permission/disk/lock problem listed above and re-run "composer '
                . 'update stenversonline/platform".'
            );
        }

        $io->write('<info>stenversonline/platform: sync complete</info>');
    }

    /**
     * List the CSS/JS that will be served in full because their .min is missing or older.
     *
     * Deliberately a report and not a build. Minifying needs terser and lightningcss
     * from cma/node_modules, and only `npm install` puts them there — which composer
     * cannot do for the operator. A step that silently did nothing would be worse than
     * none at all: it would read as "minification is handled".
     *
     * So the platform builds its own .min files before it is released (a test guards
     * that), and this step names what is left — mostly a site's own assets/, which the
     * platform never builds. The site can build those itself: it gets cma/package.json
     * (ROOT_SYNCED_FILES) and tools/build-minify.js, so the command this prints works
     * on a consumer site, Windows/IIS included.
     *
     * @return string[] Lines for the composer output; empty when everything is current.
     */
    private static function reportUnminifiedAssets(string $projectRoot): array
    {
        // The directories the build covers, plus a site's own assets. Vendor trees
        // (select2, jcrop, fineuploader) are absent on purpose: their .min files are
        // shipped as-is by the vendor and are not ours to rebuild.
        $dirs = [
            'library', 'library/css', 'library/webcomponents',
            'cma/assets/css', 'cma/assets/js',
            'assets/css', 'assets/js',
        ];

        $stale = [];
        foreach ($dirs as $dir) {
            $path = $projectRoot . '/' . $dir;
            if (!is_dir($path)) {
                continue;
            }
            foreach ((array) glob($path . '/*.{css,js}', GLOB_BRACE) as $src) {
                if (preg_match('/\.min\.(css|js)$/i', $src)) {
                    continue;
                }
                $min = preg_replace('/\.(css|js)$/i', '.min.$1', $src);
                if (is_file($min) && filemtime($min) >= filemtime($src)) {
                    continue;
                }
                $stale[$dir . '/' . basename($src)] = (int) filesize($src);
            }
        }

        if ($stale === []) {
            return [];
        }

        arsort($stale);
        $totaal = array_sum($stale);
        $lines = [sprintf(
            '<warning>%d asset(s) worden onverkleind geserveerd (%d KB):</warning>',
            count($stale),
            (int) round($totaal / 1024)
        )];
        foreach (array_slice($stale, 0, 8, true) as $rel => $bytes) {
            $lines[] = sprintf('    %6d KB  %s', (int) round($bytes / 1024), $rel);
        }
        if (count($stale) > 8) {
            $lines[] = sprintf('    ... en nog %d', count($stale) - 8);
        }
        $lines[] = '    Bouwen: cd cma && npm install && npm run build:minify';

        return $lines;
    }

    /**
     * Clear the platform's regenerable disk caches (minified JS/CSS in
     * .cache/cma/minify, cached form definitions in .cache/cma/forms). They are
     * file-based and shared with the web process, so clearing them from the
     * composer CLI is effective — the web process simply regenerates them.
     * Does NOT (cannot) clear the FastCGI worker's OPcache/APCu.
     *
     * @return string[] Log lines describing what was cleared.
     */
    private static function clearRegenerableCaches(string $projectRoot): array
    {
        $log = [];
        foreach (['.cache/cma/minify', '.cache/cma/forms'] as $rel) {
            $dir = $projectRoot . '/' . $rel;
            if (!is_dir($dir)) {
                continue;
            }
            $count = self::deleteDirContents($dir);
            if ($count > 0) {
                $log[] = "cleared $rel ($count files)";
            }
        }
        return $log;
    }

    /**
     * Recursively delete everything inside $dir, keeping $dir itself (so its
     * permissions are preserved). Returns the number of files removed.
     */
    private static function deleteDirContents(string $dir): int
    {
        $removed = 0;
        $items = @scandir($dir);
        if ($items === false) {
            return 0;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $removed += self::deleteDirContents($path);
                @rmdir($path);
            } elseif (@unlink($path)) {
                $removed++;
            }
        }
        return $removed;
    }

    /**
     * Recursively sync a source directory to a destination.
     * Delete files listed in REMOVED_PATHS from the consumer site root.
     * Public for testability — `run()` is composer-bound, this isn't.
     *
     * Each REMOVED_PATHS entry is a relative path under $projectRoot;
     * existing files are unlinked, missing ones are no-ops (idempotent).
     * Returns the list of paths that were actually removed so the caller
     * can log them. Symlinks are left alone — only regular files match
     * is_file().
     *
     * @param string $projectRoot Absolute path to the consumer site root.
     * @return string[] Relative paths that were actually removed.
     */
    public static function cleanRemovedPaths(string $projectRoot): array
    {
        $removed = [];
        foreach (self::REMOVED_PATHS as $relPath) {
            $abs = $projectRoot . '/' . $relPath;
            if (is_file($abs) && @unlink($abs)) {
                $removed[] = $relPath;
                // Sweep the parent directory when the removal emptied it, so a
                // fully-retired directory (e.g. cma/tools/config/) doesn't
                // linger as an empty husk. rmdir refuses non-empty dirs, so
                // this is a safe no-op whenever anything else still lives there.
                @rmdir(dirname($abs));
            }
        }
        return $removed;
    }

    /**
     * Skips protected paths. Overwrites everything else.
     *
     * Resilient by design: a single file that can't be copied (locked, bad
     * permissions, disk full, an OS-junk file with system attributes) is
     * recorded and skipped, NOT allowed to abort the whole sync. Aborting on
     * the first bad file used to strand critical top-level files — e.g. a
     * `desktop.ini` under library/fonts/ failing left the freshly-retired
     * `lib_htmleditor.inc` deleted but the top-level `library.inc` (which
     * required it) never overwritten, so every request fatal-errored. Best-
     * effort completeness means library.inc always gets copied; the caller
     * still fails loudly at the end if any file was skipped.
     *
     * @param string $src Source directory
     * @param string $dest Destination directory
     * @param string[] $protectedPaths Relative paths to skip
     * @param mixed $io Composer IO interface (optional)
     * @return string[] One message per file that could not be synced (empty on full success).
     */
    private static function syncDirectory(string $src, string $dest, array $protectedPaths = [], $io = null): array
    {
        $errors = [];
        if (!is_dir($dest) && !mkdir($dest, 0755, true) && !is_dir($dest)) {
            // Failing silently here meant `composer update` could land
            // half a sync — destination dir missing, no files copied,
            // no exception, exit 0 — and the consumer site appeared
            // to update successfully. Fail loud so the operator sees
            // the permissions/disk problem in the composer output.
            throw new \RuntimeException("Installer::syncDirectory could not create $dest");
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($src, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            // Calculate relative path from source root
            $relativePath = substr($item->getPathname(), strlen($src) + 1);
            $destPath = $dest . '/' . $relativePath;

            // Normalize path separators for comparison
            $normalizedRelative = str_replace('\\', '/', $relativePath);

            // Check if this path is protected
            $isProtected = false;
            foreach ($protectedPaths as $protected) {
                // Protected paths are relative to project root, but we're syncing
                // a subdirectory. Extract the relevant part.
                $protectedParts = explode('/', $protected);
                $destBasename = basename($dest);

                // If the protected path starts with our destination folder name,
                // compare the remainder
                if ($protectedParts[0] === $destBasename) {
                    $protectedRelative = implode('/', array_slice($protectedParts, 1));
                    if ($normalizedRelative === $protectedRelative) {
                        $isProtected = true;
                        break;
                    }
                }
            }

            if ($item->isDir()) {
                if (!is_dir($destPath) && !mkdir($destPath, 0755, true) && !is_dir($destPath)) {
                    // Non-fatal: record and continue. copyFile() recreates the
                    // parent dir per file anyway, so a failed dir here doesn't
                    // strand siblings elsewhere in the tree.
                    $errors[] = "could not create directory: $normalizedRelative";
                }
            } else {
                // Skip protected files that already exist
                if ($isProtected && file_exists($destPath)) {
                    if ($io) {
                        $io->write("  - SKIPPED (protected): $normalizedRelative");
                    }
                    continue;
                }

                // Skip .template files (they are handled separately)
                if (substr($item->getFilename(), -9) === '.template') {
                    continue;
                }

                // Skip OS-generated junk (Windows desktop.ini / Thumbs.db, macOS
                // .DS_Store). These are never platform content, and the Windows
                // ones carry system/hidden/readonly attributes, so copy() over an
                // existing target fails with "Permission denied" — which, because
                // copyFile() throws, aborted the ENTIRE post-update sync mid-way.
                if (preg_match('/^(desktop\.ini|Thumbs\.db|\.DS_Store)$/i', $item->getFilename())) {
                    continue;
                }

                // Skip node_modules, .git, vendor directories
                if (strpos($normalizedRelative, 'node_modules/') !== false ||
                    strpos($normalizedRelative, '.git/') !== false ||
                    strpos($normalizedRelative, 'vendor/') !== false) {
                    continue;
                }

                // Best-effort: one unwritable file must not abort the sync and
                // leave the site on mixed versions. Record and keep going; the
                // caller reports every failure and fails the run loudly at the end.
                try {
                    self::copyFile($item->getPathname(), $destPath);
                } catch (\Throwable $e) {
                    $errors[] = $normalizedRelative . ': ' . $e->getMessage();
                    if ($io) {
                        $io->write("  - <warning>skipped (copy failed): $normalizedRelative</warning>");
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Copy a single file, creating parent directories as needed.
     */
    private static function copyFile(string $src, string $dest): void
    {
        $dir = dirname($dest);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException("Installer::copyFile could not create parent dir $dir");
        }
        if (!copy($src, $dest)) {
            // Disk full / permission denied / source vanished. Silent
            // copy failure mid-sync left consumer sites with mixed
            // platform versions — surface the error so the operator
            // sees it in the composer-update output.
            throw new \RuntimeException("Installer::copyFile failed: $src -> $dest");
        }
    }

    /**
     * Write a manifest file to track what was installed.
     * This helps identify which files came from the platform vs. project-specific.
     */
    private static function writeManifest(string $projectRoot, string $platformDir): void
    {
        $version = 'unknown';

        // Try to get version from Composer
        if (class_exists('Composer\InstalledVersions')) {
            try {
                $version = \Composer\InstalledVersions::getPrettyVersion('stenversonline/platform') ?? 'unknown';
            } catch (\Exception $e) {
                // Ignore
            }
        }

        $manifest = [
            'package' => 'stenversonline/platform',
            'version' => $version,
            'synced_at' => date('Y-m-d H:i:s'),
            'directories' => ['library/', 'cma/', 'module/'],
            'protected_configs' => self::PROTECTED_PATHS,
        ];

        $dest = $projectRoot . '/.platform-manifest.json';
        file_put_contents($dest, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    }
}
