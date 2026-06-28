<?php

namespace Cma;

use App\Library\Request;

require_once __DIR__ . '/JsonFormLoader.php';

/**
 * FormRoute — the canonical inbound URL contract for the unified form page.
 *
 * This is the PHP mirror of the client-side url-manager.js. The IIS rewrite
 * rules in cma/web.config translate clean URLs into query parameters, e.g.
 *
 *     /cma/form/<form>/<recordId>            -> form.php?form=<form>&formID=<recordId>
 *     /cma/form/<form>/new                   -> form.php?form=<form>&formID=new
 *     /cma/form/<form>/<recordId>/<sub>/<id>  -> form.php?form=<form>&formID=<recordId>&popup=<sub>&popupID=<id>
 *
 * Historically form.php read those parameters with three different spellings
 * (formID vs id/ID vs popupID) and only understood `formID` as a *record id*
 * inside the popup branch — so a top-level deep link like
 * /cma/form/steensoorten_producten/4984 (which arrives as formID=4984)
 * rendered the list but never opened record 4984. The contract was effectively
 * defined in several disconnected places that drifted apart.
 *
 * FormRoute is the single place that normalises every spelling the rewrite
 * rules (and legacy links) can emit into one route state. form.php reads its
 * routing ONLY from here, which makes the server self-sufficient: a deep link
 * opens the record without depending on any client-side URL rewriting.
 *
 * Keep this in lock-step with cma/assets/js/url-manager.js (parseUrl /
 * toPageUrl) — they are the two halves of one contract.
 */
final class FormRoute
{
    /** Effective JSON form name to render (the subform itself in popup mode). */
    public string $form = '';

    /** Record id to load, or null for new-record / list mode. May be '0'. */
    public ?string $recordId = null;

    /** Explicit new-record request (New=Y, or a 'new' slug). */
    public bool $isNew = false;

    /** Copy mode: load $recordId but save as a new record (copy=Y). */
    public bool $isCopy = false;

    /** Parent record id for a subform context, or null. */
    public ?string $parentId = null;

    /** Parent foreign-key field for a subform context. */
    public string $parentField = '';

    /** Explicit view ('tree'|'table'); '' means honour the persisted mode. */
    public string $view = '';

    /**
     * Build the route from the current request ($_GET via Request helpers).
     */
    public static function fromRequest(): self
    {
        $route = new self();

        $form  = Request::query('form', '');
        $popup = Request::query('popup', '');

        if ($popup !== '') {
            // Subform context: the popup IS the form we render. The outer form's
            // record id (carried as formID) becomes the parent id, and popupID
            // is the subform record (or 'new').
            $route->form     = $popup;
            $route->parentId = self::nullIfEmpty(Request::query('formID', ''));

            $popupID = Request::query('popupID', '');
            if (strtolower($popupID) === 'new') {
                $route->isNew = true;
            } elseif ($popupID !== '') {
                $route->recordId = $popupID;
            }
        } else {
            $route->form = $form;

            // Legacy: a numeric FormID maps to a JSON form via sourceFormId.
            if ($route->form === '') {
                $legacyId = (int) Request::queryInt('FormID');
                if ($legacyId > 0) {
                    $route->form = JsonFormLoader::getFormNameBySourceId($legacyId) ?? '';
                }
            }

            // Record id, in priority order. `formID` is the clean-URL record
            // slug — this is the line that makes /cma/form/<form>/<id> work
            // server-side regardless of any client rewriting.
            $recordId = self::firstNonEmpty([
                Request::queryId('ID'),
                Request::queryId('id'),
                Request::queryId('formID'),
            ]);
            if (strtolower($recordId) === 'new') {
                $route->isNew = true;
            } elseif ($recordId !== '') {
                $route->recordId = $recordId;
            }
        }

        // Explicit new-record flag (independent of the slug spelling). Does not
        // clear an already-resolved record id: New=Y + an id keeps the id, as
        // the previous form.php logic did.
        if (Request::query('New', '') === 'Y') {
            $route->isNew = true;
        }

        $route->isCopy = Request::query('copy', '') === 'Y';
        $route->view   = Request::query('view', '');

        // Explicit parent params win over the popup-derived parent id.
        $explicitParent = Request::query('parentID', '');
        if ($explicitParent !== '') {
            $route->parentId = $explicitParent;
        }
        $route->parentField = Request::query('parentField', '');

        return $route;
    }

    /** Convenience accessor: '' instead of null, for legacy string callers. */
    public function parentIdString(): string
    {
        return $this->parentId ?? '';
    }

    private static function nullIfEmpty(string $value): ?string
    {
        return $value === '' ? null : $value;
    }

    /** First non-empty string in the list, or '' when all are empty. */
    private static function firstNonEmpty(array $values): string
    {
        foreach ($values as $value) {
            if ($value !== '' && $value !== null) {
                return (string) $value;
            }
        }
        return '';
    }
}
