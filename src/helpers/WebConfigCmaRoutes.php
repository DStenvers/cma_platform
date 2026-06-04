<?php

namespace App\Library;

/**
 * WebConfigCmaRoutes — shared, fail-safe injector for the CMA friendly-URL
 * rewrite rules into a site's *parent* web.config.
 *
 * The CMA rewrite rules (Dashboard, Preferences, Tools, Form) historically
 * lived in cma/web.config but don't apply reliably there because URL Rewrite
 * Module distributed-rule semantics + outbound-rule inheritance regularly
 * produce 500.50 / 404. They belong in the parent web.config, where IIS
 * always reaches them without inheritance complications.
 *
 * Two consumers share this logic:
 *   - migration 9.9.0_cma_routes_to_parent_webconfig.php (operator-run; ALSO
 *     runs an appcmd schema-check + a live HTTP smoke-test after the write —
 *     IIS-only steps that have no meaning at composer-CLI time)
 *   - Installer.php (composer install/update; file-level safeguards only)
 *
 * Safeguards applied here (the file-level ones — identical to the migration's
 * original STAP 0–6):
 *   0.  ext-simplexml present (else refuse — XML can't be validated)
 *   1.  current web.config is well-formed XML (else refuse — fix that first)
 *   1b. patched XML is well-formed (validated in memory, before any write)
 *   1c. no duplicate rule-names per collection (the IIS 500.50 trap)
 *   1d. our own CMA match-patterns compile as PCRE
 *   2.  backup before write
 *   3.  atomic temp-write + rename
 *   4.  read-back validation of the temp file
 *   5.  final read-back validation, rollback from backup on failure
 *
 * appcmd + the live HTTP smoke-test are intentionally NOT here: they need a
 * running IIS host and an HTTP request context, which the composer install
 * path does not have. The migration layers them on top, using the backup
 * path this class returns.
 *
 * No dependency on bootstrap, Application globals, or Cma\* classes, so the
 * Composer Installer (which runs before any bootstrap) can call it.
 */
class WebConfigCmaRoutes
{
    /** Idempotency marker written into the parent web.config. */
    public const MARKER = 'cma_platform: CMA rewrite rules applied (v1.20.12+)';

    /**
     * The CMA rewrite-rules block, inserted at the top of <rules>.
     * nowdoc ('EOT') so $-signs stay literal — they are regex anchors and
     * IIS back-reference tokens, not PHP variables.
     */
    private const RULES = <<<'EOT'
                <!-- ============================================================
                     CMA rewrite rules — applied by cma_platform
                     Marker: cma_platform: CMA rewrite rules applied (v1.20.12+)
                     ============================================================
                     Deze rules leefden historisch in cma/web.config maar werken
                     daar niet betrouwbaar wegens URL Rewrite Module distributed-
                     rule semantics. Hier in de parent worden ze direct geapplied
                     zonder inheritance-issues. Plaats vóór elke catch-all rule
                     die anders /cma/* zou opslokken. -->
                <rule name="CMA Root" stopProcessing="true">
                    <match url="^cma/?$" />
                    <action type="Redirect" url="/cma/dashboard" redirectType="Found" />
                </rule>
                <rule name="CMA Dashboard" stopProcessing="true">
                    <match url="^cma/dashboard/?$" />
                    <action type="Rewrite" url="/cma/main.php?page=dashboard.php" appendQueryString="true" />
                </rule>
                <rule name="CMA Preferences" stopProcessing="true">
                    <match url="^cma/preferences/?$" />
                    <action type="Rewrite" url="/cma/main.php?page=preferences.php" appendQueryString="true" />
                </rule>
                <rule name="CMA Tools" stopProcessing="true">
                    <match url="^cma/tools/?$" />
                    <action type="Rewrite" url="/cma/main.php?page=tools.php" appendQueryString="true" />
                </rule>
                <rule name="CMA Form list" stopProcessing="true">
                    <match url="^cma/form/([^/]+)/?$" />
                    <action type="Rewrite" url="/cma/main.php?page=form.php%3Fform%3D{R:1}" appendQueryString="true" />
                </rule>
                <rule name="CMA Form with record" stopProcessing="true">
                    <match url="^cma/form/([^/]+)/([^/]+)/?$" />
                    <action type="Rewrite" url="/cma/main.php?page=form.php%3Fform%3D{R:1}&amp;formID={R:2}" appendQueryString="true" />
                </rule>
                <rule name="CMA Form with subform list" stopProcessing="true">
                    <match url="^cma/form/([^/]+)/([^/]+)/([^/]+)/?$" />
                    <action type="Rewrite" url="/cma/main.php?page=form.php%3Fform%3D{R:1}&amp;formID={R:2}&amp;popup={R:3}" appendQueryString="true" />
                </rule>
                <rule name="CMA Form with subform record" stopProcessing="true">
                    <match url="^cma/form/([^/]+)/([^/]+)/([^/]+)/([^/]+)/?$" />
                    <action type="Rewrite" url="/cma/main.php?page=form.php%3Fform%3D{R:1}&amp;formID={R:2}&amp;popup={R:3}&amp;popupID={R:4}" appendQueryString="true" />
                </rule>
                <rule name="CMA Form with subsubform list" stopProcessing="true">
                    <match url="^cma/form/([^/]+)/([^/]+)/([^/]+)/([^/]+)/([^/]+)/?$" />
                    <action type="Rewrite" url="/cma/main.php?page=form.php%3Fform%3D{R:1}&amp;formID={R:2}&amp;popup={R:3}&amp;popupID={R:4}&amp;subpopup={R:5}" appendQueryString="true" />
                </rule>
                <rule name="CMA Form with subsubform record" stopProcessing="true">
                    <match url="^cma/form/([^/]+)/([^/]+)/([^/]+)/([^/]+)/([^/]+)/([^/]+)/?$" />
                    <action type="Rewrite" url="/cma/main.php?page=form.php%3Fform%3D{R:1}&amp;formID={R:2}&amp;popup={R:3}&amp;popupID={R:4}&amp;subpopup={R:5}&amp;subpopupID={R:6}" appendQueryString="true" />
                </rule>

EOT;

    /**
     * Apply the CMA routes to $webConfig, idempotently and fail-safe.
     *
     * Never throws and never leaves a half-written file: on any validation
     * or write failure the original is left untouched, or rolled back from
     * the backup.
     *
     * @param string $webConfig Absolute path to the parent web.config.
     * @return array{status:string,messages:string[],backup:?string,errors:string[]}
     *   status:
     *     'patched' — routes (or idempotency marker) written; 'backup' set
     *     'noop'    — marker already present; nothing to do
     *     'skipped' — precondition not met (no simplexml / file missing); not an error
     *     'manual'  — could not locate <rules>; needs a hand-patch
     *     'error'   — validation/write failed; see 'errors'
     */
    public static function applyToParent(string $webConfig): array
    {
        $messages = [];
        $errors   = [];
        $msg = static function (string $m) use (&$messages): void { $messages[] = $m; };

        if (!function_exists('simplexml_load_string')) {
            $msg('ext-simplexml niet geladen — CMA-routes overgeslagen (kan XML niet valideren).');
            return self::result('skipped', $messages, null, $errors);
        }
        if (!is_file($webConfig)) {
            $msg('parent web.config niet gevonden (' . $webConfig . ') — overgeslagen.');
            return self::result('skipped', $messages, null, $errors);
        }

        $content = @file_get_contents($webConfig);
        if ($content === false) {
            $errors[] = 'kan parent web.config niet lezen: ' . $webConfig;
            return self::result('error', $messages, null, $errors);
        }

        // Already applied — marker present.
        if (strpos($content, self::MARKER) !== false) {
            $msg('Marker al aanwezig — CMA-routes reeds toegepast.');
            return self::result('noop', $messages, null, $errors);
        }

        // Rules added by hand earlier (no marker): only stamp the marker so
        // future runs detect idempotency. No rule injection.
        if (strpos($content, 'name="CMA Dashboard"') !== false) {
            $patched = str_replace(
                '<rule name="CMA Dashboard"',
                '<!-- ' . self::MARKER . ' --><rule name="CMA Dashboard"',
                $content
            );
            $backup = self::safeWrite($webConfig, $patched, $errors);
            if ($backup === false) {
                return self::result('error', $messages, null, $errors);
            }
            $msg("Rule 'CMA Dashboard' al aanwezig (handmatige patch) — idempotency-marker toegevoegd. Backup: " . $backup);
            return self::result('patched', $messages, $backup, $errors);
        }

        // The current config must itself be valid XML before we touch it —
        // writing into an already-broken file would only compound the damage.
        if (@simplexml_load_string($content) === false) {
            $errors[] = 'parent web.config is GEEN valid XML — fix dat eerst handmatig. CMA-routes niet toegepast.';
            return self::result('error', $messages, null, $errors);
        }

        // Locate the <rules> opening tag (flexible for whitespace variants).
        if (!preg_match('|<rules>\s*\R|', $content, $m, PREG_OFFSET_CAPTURE)) {
            $msg('Kan <rules> opening-tag niet vinden — handmatige patch nodig (zie documentation.php?topic=iis_config).');
            return self::result('manual', $messages, null, $errors);
        }
        $insertPos = $m[0][1] + strlen($m[0][0]);
        $patched   = substr_replace($content, self::RULES, $insertPos, 0);

        // Validate the patched content (well-formed + duplicate-name + regex)
        // entirely in memory — never write a file we have not validated.
        $validationErrors = self::validatePatched($patched);
        if (!empty($validationErrors)) {
            foreach ($validationErrors as $e) {
                $errors[] = $e;
            }
            return self::result('error', $messages, null, $errors);
        }

        $backup = self::safeWrite($webConfig, $patched, $errors);
        if ($backup === false) {
            return self::result('error', $messages, null, $errors);
        }
        $msg('Parent web.config gepatched met CMA-routes (XML + duplicate-name + regex gevalideerd, atomic write). Backup: ' . $backup);
        return self::result('patched', $messages, $backup, $errors);
    }

    /**
     * Validate patched content: well-formed XML, no duplicate rule-names per
     * collection, valid PCRE on our own CMA match-patterns.
     *
     * @return string[] Error messages (empty = valid).
     */
    private static function validatePatched(string $patched): array
    {
        $errors = [];

        libxml_use_internal_errors(true);
        libxml_clear_errors();
        $post = simplexml_load_string($patched);
        if ($post === false) {
            foreach (libxml_get_errors() as $err) {
                $errors[] = 'libxml: ' . trim($err->message) . ' (line ' . $err->line . ')';
            }
            libxml_clear_errors();
            $errors[] = 'gepatchte XML is niet valid — original NIET aangeraakt (platform-bug).';
            return $errors;
        }

        // Duplicate rule-names: IIS marks <rule name=""> as uniqueId per
        // collection — duplicates give 500.50 "cannot add duplicate
        // collection entry".
        $collect = static function (array $nodes, string $scope) use (&$errors): void {
            $seen = [];
            foreach ($nodes as $node) {
                $name = (string)$node['name'];
                if ($name === '') {
                    continue;
                }
                if (isset($seen[$name])) {
                    $errors[] = "duplicate $scope rule name: '" . $name . "'";
                }
                $seen[$name] = true;
            }
        };
        $collect($post->xpath('//rewrite/rules/rule'), 'inbound');
        $collect($post->xpath('//rewrite/outboundRules/rule'), 'outbound');
        $collect($post->xpath('//rewrite/outboundRules/preConditions/preCondition'), 'preCondition');

        // PCRE check on ONLY our own patterns ("CMA " prefix). User rules may
        // be .NET-regex that isn't PCRE-compatible, so we skip those to avoid
        // false positives. A failure on our own pattern is a platform bug.
        foreach ($post->xpath('//rewrite/rules/rule[starts-with(@name, "CMA ")]/match') as $match) {
            $url = (string)$match['url'];
            if ($url === '') {
                continue;
            }
            if (@preg_match('~' . $url . '~', '') === false) {
                $err = error_get_last();
                $errors[] = "invalid regex in CMA rule match url '" . $url . "': " . ($err['message'] ?? 'unknown');
            }
        }

        return $errors;
    }

    /**
     * Backup → atomic temp-write → read-back → rename → final read-back,
     * rolling back from the backup on a final-validation failure.
     *
     * @param string   $webConfig Path to overwrite.
     * @param string   $patched   New content (already validated by caller).
     * @param string[] $errors    Collected by reference on failure.
     * @return string|false Backup path on success, false on failure.
     */
    private static function safeWrite(string $webConfig, string $patched, array &$errors)
    {
        $stamp  = date('YmdHis');
        $backup = $webConfig . '.cma-routes-backup-' . $stamp;
        if (!@copy($webConfig, $backup)) {
            $errors[] = 'kan geen backup maken naar ' . $backup;
            return false;
        }

        $tmp = $webConfig . '.cma-routes-tmp-' . $stamp;
        if (@file_put_contents($tmp, $patched) === false) {
            @unlink($tmp);
            $errors[] = 'kan tmp-bestand niet schrijven: ' . $tmp;
            return false;
        }

        $readBack = @file_get_contents($tmp);
        if ($readBack === false || @simplexml_load_string($readBack) === false) {
            @unlink($tmp);
            $errors[] = 'tmp-bestand passeert validatie niet na read-back. Original NIET vervangen.';
            return false;
        }

        if (!@rename($tmp, $webConfig)) {
            @unlink($tmp);
            $errors[] = 'rename ' . $tmp . ' → ' . $webConfig . ' faalde.';
            return false;
        }

        $final = @file_get_contents($webConfig);
        if ($final === false || @simplexml_load_string($final) === false) {
            if (@copy($backup, $webConfig)) {
                $errors[] = 'finale validatie faalde — ROLLBACK uit backup voltooid.';
            } else {
                $errors[] = 'finale validatie faalde EN rollback faalde. HANDMATIG HERSTEL: kopieer '
                          . $backup . ' → ' . $webConfig;
            }
            return false;
        }

        return $backup;
    }

    /**
     * @param string[] $messages
     * @param string|null $backup
     * @param string[] $errors
     * @return array{status:string,messages:string[],backup:?string,errors:string[]}
     */
    private static function result(string $status, array $messages, $backup, array $errors): array
    {
        return [
            'status'   => $status,
            'messages' => $messages,
            'backup'   => $backup !== false ? $backup : null,
            'errors'   => $errors,
        ];
    }
}
