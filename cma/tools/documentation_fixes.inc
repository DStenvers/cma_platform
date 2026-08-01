<?php
/**
 * documentation_fixes.inc — the "Los op"-buttons behind the live checks in
 * cma/tools/documentation.php.
 *
 * Split out of documentation.php on purpose: this is the only doc-hub code
 * that WRITES to the site-root web.config, so it needs to be unit-testable
 * without pulling in the whole doc hub (which is admin-gated and renders a
 * page on include). See cma/tests/DocFixWebConfigTest.php.
 *
 * Every fixer takes a \DOMDocument and returns a short HTML summary of what it
 * changed, or null when the config already had it (idempotent by contract).
 */

// === AUTO-FIX =============================================================
// A handful of checks describe a MISSING piece of the site-root web.config.
// That file is PROJECT-owned — the Installer never writes it — so it drifts,
// and the operator had to hand-edit XML from a doc snippet. Each fixer below
// makes exactly the edit its check looks for, is idempotent (re-running is a
// no-op), backs the file up first, and rolls back if the result won't parse.
//
// Deliberately NOT fixable from here: the IIS URL Rewrite *module* (an MSI
// install), the CMA route rules and the child cma/web.config (both come from
// `composer update` / migratie 9.9.0 — writing them here would fight the
// Installer). Those keep their manual instructions.
//
// NB: writing web.config recycles the IIS app-pool. That's wanted — it is
// what makes the new rule take effect — but it does mean the very next
// request may be slow.
// =========================================================================

/** check-function name => ['label' => button text, 'apply' => fn(\DOMDocument): string|null] */
function cma_doc_fixers(): array {
    return [
        'cma_doc_check_parent_skip_cma' => [
            'label' => 'Regel verplaatsen',
            'apply' => 'cma_doc_fix_skip_cma',
        ],
        'cma_doc_check_parent_default_content_type' => [
            'label' => 'Outbound rule toevoegen',
            'apply' => 'cma_doc_fix_default_content_type',
        ],
        'cma_doc_check_parent_nosniff' => [
            'label' => 'Header toevoegen',
            'apply' => 'cma_doc_fix_nosniff',
        ],
        'cma_doc_check_parent_frame_options' => [
            'label' => 'Header toevoegen',
            'apply' => 'cma_doc_fix_frame_options',
        ],
        'cma_doc_check_parent_hidden_segments' => [
            'label' => 'Segmenten toevoegen',
            'apply' => 'cma_doc_fix_hidden_segments',
        ],
    ];
}

/**
 * Walk/create a nested element path under <configuration>, e.g.
 * 'system.webServer/rewrite/rules'. Returns the deepest element.
 */
function cma_doc_dom_path(\DOMDocument $doc, string $path): \DOMElement {
    $node = $doc->documentElement;
    foreach (explode('/', $path) as $name) {
        $child = null;
        foreach ($node->childNodes as $c) {
            if ($c instanceof \DOMElement && $c->nodeName === $name) { $child = $c; break; }
        }
        if ($child === null) {
            $child = $doc->createElement($name);
            $node->appendChild($child);
        }
        $node = $child;
    }
    return $node;
}

/** True when $parent already has a child <$name> carrying $attr="$value". */
function cma_doc_dom_has(\DOMElement $parent, string $name, string $attr, string $value): bool {
    foreach ($parent->childNodes as $c) {
        if ($c instanceof \DOMElement && $c->nodeName === $name && $c->getAttribute($attr) === $value) {
            return true;
        }
    }
    return false;
}

/**
 * Verplaats regels die de CMA-routes onbereikbaar maken naar ná het CMA-blok.
 *
 * Een regel die alles onder /cma met rust wil laten (action None,
 * stopProcessing) is nuttig — hij houdt de catch-all regels van de front-end
 * weg bij assets en *.php onder /cma. Maar hij stopt de héle rewrite-keten, ook
 * de rules in cma/web.config. Staat hij vóór het CMA-blok, dan bereikt geen
 * enkele schone CMA-URL zijn rewrite en zoekt IIS letterlijk naar een bestand
 * `cma/form/opleidingen/151`: 404 op elke lijst, elk record en elk tabblad.
 *
 * Welke regels blokkeren bepaalt WebConfigCmaRoutes — dezelfde bron als de
 * live-check en de installer-waarschuwing, zodat de drie niet uit elkaar lopen.
 */
function cma_doc_fix_skip_cma(\DOMDocument $doc): ?string {
    $blockers = \App\Library\WebConfigCmaRoutes::precedingBlockers((string) $doc->saveXML());
    if (empty($blockers)) {
        return null;
    }
    $rules = cma_doc_dom_path($doc, 'system.webServer/rewrite/rules');
    $verplaatst = [];

    foreach ($blockers as $blocker) {
        // De lijst opnieuw opbouwen: een eerdere verplaatsing heeft de volgorde
        // veranderd.
        $lijst = [];
        foreach ($rules->childNodes as $c) {
            if ($c instanceof \DOMElement && $c->nodeName === 'rule') { $lijst[] = $c; }
        }
        $isCma = static fn(\DOMElement $r): bool => strncmp($r->getAttribute('name'), 'CMA ', 4) === 0;

        if ($blocker['stranded'] !== null) {
            // Eén losgeraakte CMA-route: die hoort terug in het blok, direct
            // achter de laatste CMA-regel die nog vóór de blokkade staat.
            $anker = null;
            $node  = null;
            foreach ($lijst as $r) {
                if ($r->getAttribute('name') === $blocker['name']) { break; }
                if ($isCma($r)) { $anker = $r; }
            }
            foreach ($lijst as $r) {
                if ($r->getAttribute('name') === $blocker['stranded']) { $node = $r; break; }
            }
            if ($node === null || $anker === null) {
                continue;
            }
            $te = $blocker['stranded'];
        } else {
            // De blokkade staat vóór het hele blok: hij hoort erachter.
            $anker = null;
            foreach ($lijst as $r) {
                if ($isCma($r)) { $anker = $r; }
            }
            $node = null;
            foreach ($lijst as $r) {
                if ($r->getAttribute('name') === $blocker['name']) { $node = $r; break; }
            }
            if ($node === null || $anker === null) {
                continue;
            }
            $te = $blocker['name'];
        }

        $node = $rules->removeChild($node);
        if ($anker->nextSibling === null) {
            $rules->appendChild($node);
        } else {
            $rules->insertBefore($node, $anker->nextSibling);
        }
        $verplaatst[$te] = true;
    }

    if (empty($verplaatst)) {
        return null;
    }
    return 'Regel(s) ' . htmlspecialchars(implode(', ', array_keys($verplaatst)), ENT_QUOTES, 'UTF-8')
        . ' verplaatst, zodat elke schone CMA-URL zijn eigen route weer als eerste tegenkomt.';
}

function cma_doc_fix_default_content_type(\DOMDocument $doc): ?string {
    $outbound = cma_doc_dom_path($doc, 'system.webServer/rewrite/outboundRules');
    if (cma_doc_dom_has($outbound, 'rule', 'name', 'Default Content-Type to text/html')) {
        return null;
    }
    $rule = $doc->createElement('rule');
    $rule->setAttribute('name', 'Default Content-Type to text/html');
    $rule->setAttribute('preCondition', 'ContentTypeMissing');
    $match = $doc->createElement('match');
    $match->setAttribute('serverVariable', 'RESPONSE_Content-Type');
    $match->setAttribute('pattern', '.*');
    $rule->appendChild($match);
    $action = $doc->createElement('action');
    $action->setAttribute('type', 'Rewrite');
    $action->setAttribute('value', 'text/html; charset=UTF-8');
    $rule->appendChild($action);
    $outbound->appendChild($rule);

    // The rule is inert without its preCondition.
    $preConditions = null;
    foreach ($outbound->childNodes as $c) {
        if ($c instanceof \DOMElement && $c->nodeName === 'preConditions') { $preConditions = $c; break; }
    }
    if ($preConditions === null) {
        $preConditions = $doc->createElement('preConditions');
        $outbound->appendChild($preConditions);
    }
    if (!cma_doc_dom_has($preConditions, 'preCondition', 'name', 'ContentTypeMissing')) {
        $pre = $doc->createElement('preCondition');
        $pre->setAttribute('name', 'ContentTypeMissing');
        $add = $doc->createElement('add');
        $add->setAttribute('input', '{RESPONSE_Content-Type}');
        $add->setAttribute('pattern', '^$');
        $pre->appendChild($add);
        $preConditions->appendChild($pre);
    }
    return 'Outbound rule + preCondition "ContentTypeMissing" toegevoegd.';
}

function cma_doc_fix_custom_header(\DOMDocument $doc, string $name, string $value): ?string {
    $headers = cma_doc_dom_path($doc, 'system.webServer/httpProtocol/customHeaders');
    if (cma_doc_dom_has($headers, 'add', 'name', $name)) {
        return null;
    }
    $add = $doc->createElement('add');
    $add->setAttribute('name', $name);
    $add->setAttribute('value', $value);
    $headers->appendChild($add);
    return 'Header <code>' . htmlspecialchars($name) . ': ' . htmlspecialchars($value) . '</code> toegevoegd.';
}

function cma_doc_fix_nosniff(\DOMDocument $doc): ?string {
    return cma_doc_fix_custom_header($doc, 'X-Content-Type-Options', 'nosniff');
}

function cma_doc_fix_frame_options(\DOMDocument $doc): ?string {
    return cma_doc_fix_custom_header($doc, 'X-Frame-Options', 'SAMEORIGIN');
}

function cma_doc_fix_hidden_segments(\DOMDocument $doc): ?string {
    $segments = cma_doc_dom_path($doc, 'system.webServer/security/requestFiltering/hiddenSegments');
    $added = [];
    foreach (['.env', 'composer.json', 'composer.lock', '.sessions'] as $segment) {
        if (cma_doc_dom_has($segments, 'add', 'segment', $segment)) { continue; }
        $add = $doc->createElement('add');
        $add->setAttribute('segment', $segment);
        $segments->appendChild($add);
        $added[] = $segment;
    }
    if ($added === []) { return null; }
    return 'Verborgen: <code>' . htmlspecialchars(implode('</code>, <code>', $added)) . '</code>.';
}

/**
 * Apply one fixer to the site-root web.config.
 * Returns ['ok' => bool, 'message' => string] — message is HTML-safe.
 */
function cma_doc_apply_fix(string $check, ?string $path = null): array {
    $fixers = cma_doc_fixers();
    if (!isset($fixers[$check])) {
        return ['ok' => false, 'message' => 'Onbekende fix.'];
    }
    // $path is injectable so the test can drive a throwaway web.config;
    // production callers pass nothing and get the site-root one.
    $path = $path ?? (cma_doc_site_root() . '/web.config');
    if (!is_file($path)) {
        return ['ok' => false, 'message' => 'Geen <code>web.config</code> in de site-root — deze site draait niet onder IIS.'];
    }
    if (!is_writable($path)) {
        return ['ok' => false, 'message' => 'Geen schrijfrechten op <code>web.config</code>. Geef de IIS app-pool-user Modify-rechten, of pas het bestand handmatig aan.'];
    }

    $original = file_get_contents($path);
    if ($original === false) {
        return ['ok' => false, 'message' => 'Kan <code>web.config</code> niet lezen.'];
    }

    $doc = new \DOMDocument();
    $doc->preserveWhiteSpace = false;
    $doc->formatOutput = true;
    if (!@$doc->loadXML($original)) {
        return ['ok' => false, 'message' => 'De huidige <code>web.config</code> is geen geldige XML — repareer die eerst handmatig.'];
    }

    try {
        $summary = ($fixers[$check]['apply'])($doc);
    } catch (\Throwable $e) {
        return ['ok' => false, 'message' => 'Fix mislukte: ' . htmlspecialchars($e->getMessage())];
    }
    if ($summary === null) {
        return ['ok' => true, 'message' => 'Stond er al in — niets gewijzigd.'];
    }

    // Backup BEFORE the write, so a bad result is always recoverable by hand.
    $backup = $path . '.bak-' . date('Ymd-His');
    if (@copy($path, $backup) === false) {
        return ['ok' => false, 'message' => 'Kan geen backup maken naast <code>web.config</code> — niets gewijzigd.'];
    }

    $xml = $doc->saveXML();
    if ($xml === false || @file_put_contents($path, $xml) === false) {
        @copy($backup, $path);
        return ['ok' => false, 'message' => 'Schrijven mislukte — backup teruggezet.'];
    }

    // Re-parse what we actually wrote; a web.config IIS can't read takes the
    // whole site down with a 500, so roll back rather than leave it.
    if (@simplexml_load_file($path) === false) {
        @copy($backup, $path);
        return ['ok' => false, 'message' => 'Resultaat was geen geldige XML — backup teruggezet, niets gewijzigd.'];
    }

    return ['ok' => true, 'message' => $summary . ' Backup: <code>' . htmlspecialchars(basename($backup)) . '</code>. De app-pool recyclet door deze wijziging.'];
}
