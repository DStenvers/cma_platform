# TODO

## Wire `<cma-blockeditor>` in as a real replacement for the CKEditor-based block editor

> **Status (2026-07-06): PARKED — someday/maybe, not planned.** Decision: keep the
> working `blockedit.js` (CKEditor-per-block) editor; the full swap is high-risk
> (stored-content migration + every HTML memo field) for little user-visible gain.
> `cma-blockeditor` is now clearly marked **experimental / not in production** in
> the storybook so nobody mistakes it for the real editor. Revisit only if we
> deliberately choose to modernise the block editor. The plan below stays as the
> reference for what that work would entail.

**Goal:** make the `cma/webcomponents/cma-blockeditor.js` web component the actual
content editor used by forms, replacing the legacy CKEditor-backed content-block
editor (`cma/assets/js/blockedit.js`) and/or the single-field CKEditor wrapper
(`cma/webcomponents/cma-htmledit.js`), and modify all callers accordingly.

**Context (why this exists):** `<cma-blockeditor>` looks like the obvious block
editor from its name, but today it is dormant — only loaded as a script in
`cma/bootstrap.inc` and shown in the storybook/docs; it is never emitted into a
form. The editor actually used in production is `blockedit.js` (a CKEditor per
block, attached to `data-allow-html` memo fields wrapped in `<div class="blockedit">`
by `FormRenderer::renderMemo()`).

### Work items
- [ ] Fix the component's blocking gaps first (it cannot work in a form as-is):
  - [ ] Its hidden `<input>` lives in shadow DOM, so `new FormData(form)` does NOT
        submit it. Make it form-associated (`static formAssociated = true` +
        `ElementInternals.setFormValue()`), or have `form-controller` read its
        `.value` getter explicitly in `collectFormData()`.
  - [ ] Remove `'value'` from `observedAttributes` (or guard it): an attribute
        re-set currently clobbers in-progress edits via `_blocks = JSON.parse(...)`.
  - [ ] The `input` handler stores `target.textContent` only → pasted/typed
        formatting is dropped on the next `_renderBlocks()`. Preserve rich content.
- [ ] Decide the storage format and migrate existing data:
  - [ ] `blockedit.js` stores `<!--BLOCK{json}-->html` payloads; `<cma-blockeditor>`
        stores a JSON array of blocks. Pick the on-disk format and write a converter
        (and a `cma/migrations/` script if stored content must be rewritten).
- [ ] Emit the component from the renderers:
  - [ ] `cma/classes/FormRenderer.php::renderMemo()` — emit `<cma-blockeditor>`
        instead of the `.blockedit` textarea wrapper (and/or replace `cma-htmledit`).
  - [ ] `cma/classes/FormTemplate.php` — stop injecting `blockedit.js` where no
        longer needed.
- [ ] Modify all callers / integration points:
  - [ ] `cma/assets/js/form-controller.js` — populate (`populateForm`), collect
        (`collectFormData`), clear (`clearForm`), and dirty-tracking for the new
        component; remove the `blockedit_init`/`blockedit_clear`/`blockedit_collect_htmls`
        wiring once migrated.
  - [ ] Any consumer-app `assets/forms/*.json` field configs relying on
        `allowHtml`/content-blocks behaviour.
- [ ] Retirement of the old path (per Installer/propagation + docs rules):
  - [ ] Remove `cma/assets/js/blockedit.js` (+ `.min.js`) and add to
        `Installer.php` `REMOVED_PATHS` so consumer sites drop the dead file.
        Same for `cma/CKEditor/blockedit-test.html` and the storybook blockedit demo
        if obsolete.
  - [ ] Update `cma/tools/documentation.php` (the "Content blocks (blockedit)"
        troubleshooting section + web-components topic) to describe the new component.
- [ ] Tests: add Cypress coverage for `<cma-blockeditor>` (create/edit/move/save +
      record-switch survival), replacing `cma/cypress/e2e/forms/blockedit-content-loss.cy.js`.

**Risk:** this touches stored content and every form using a rich/HTML memo field —
plan a data migration and a staged rollout, not a drop-in swap.

## Refactor `cma/assets/js/form-controller.js` — it is unmaintainable

**Problem:** `form-controller.js` is ~12,900 lines in a single file (the whole form
runtime: record load/save, list + infinite scroll, subforms, checklists, combos,
custom renderers, popups, column selector, dirty tracking, block-editor wiring, …).
One file this size is hard to navigate, review, and test, and it is the recurring
source of hard-to-localise bugs (e.g. the duplicate-form render and the premature
"records X van Y (laden…)" stall both live here).

**Goal:** split it into cohesive modules with clear responsibilities, e.g.
- `form-record.js` — load/populate/collect/save a single record
- `form-list.js` — list rendering + infinite-scroll pager (`hasMore`/`loadMore`)
- `form-subforms.js` — subform panels + stacking/layout
- `form-fields/` — checklists, combos, custom renderers, block-editor wiring
- `form-popups.js` — popup/cascade window management
- a thin `form-controller.js` orchestrator that wires them together

**Constraints:** it is bundled + minified (`.min.js`) and served via `minify.php`;
keep the public entry points (`window.loadRecord`, `formInit`, `collectFormData`,
etc.) stable, migrate incrementally behind those, and bump the asset version. Add
unit/Cypress coverage per module as it is extracted, since there is almost none today.

## Test-plan: dekking uitbreiden voor features sinds de laatste test-update

**Context:** de PHP-suite dekt de helpers goed af, maar een aantal
platform-features is met weinig of geen tests geland. Doel: de belangrijkste
logica afdekken, unit-first (deterministisch), dan Cypress. Draai
`php cma/tests/TestRunner.php` voor de actuele stand.

### A. Unit-tests (pure logica — snel, hoge waarde)
- [ ] `JsonFormService::renderImageCell` — absolute/CDN-URL wordt as-is gebruikt
      (geen `/https%3A%2F%2F…`-mangling); relatieve bestandsnaam krijgt pad +
      rawurlencode. Regressie van de CloudFront-bug.
- [ ] `JsonFormService::formatNumber` — trailing-zero decimalen strippen
      (12.5000→12.5, 12.0000→12, 0.0000→0); niet-numeriek/komma/leeg ongemoeid.
- [ ] MigratieService: `backupAffectedDatabases`-diagnostiek (welke databases.json,
      welke logische namen) + `runPhp`/`runSqlScript`-padresolutie voor extra sources.
      (Aanvulling op MigrationScriptPathTest / DropIndexIdempotentTest / MigrationVersionWarningsTest.)
- [ ] Maintenance: extraheer de gate-beslissing (vlag aanwezig / >20min stale /
      `"manual"` / `/cma`-exempt / deploy-scripts) en `maintenance.php` branding-pick
      (`data/maintenance.json` → app.json `maintenance` → `company` → default) naar
      testbare functies en dek ze af.

### B. Cypress-specs (UI/integratie)
- [ ] Grote lijst / pager: teller eindigt op het totaal, géén duplicaten, géén
      eeuwig "(laden…)". Maak `cypress/e2e/diag/pager-stall.cy.js` productie-assertie
      (nu diagnostisch). Test óók een mobiele viewport (was de "1-200"-bug).
- [ ] Onderhoudsscherm-tool (Alle beheertools → Onderhoudsscherm): aan/uit +
      bericht-op-maat (verplicht, niet leeg), `/cma` blijft bereikbaar.
- [ ] Contentblocks-form: html + omschrijving verplicht, variabelen optioneel.
- [ ] Tools-routing/launcher: `tools?tool=<alias>` en `tools?form=<form>` tonen het
      Menu (launcher) — uitbreiding van `routing-variants.cy.js`.
- [ ] Login-scherm responsive: geen horizontale overflow op mobiele viewport
      (box ≤ viewport). Zie `cypress/e2e/diag/login-width.cy.js` (diagnostisch).
- [ ] Externe-afbeelding-thumbnails in de lijst (na de webp-feature).

### Aanpak
Eerst A (in de custom runner `cma/tests/TestRunner.php`), dan B gefaseerd.

## Site-specifieke tools generiek in tools.php wiren

- [x] **Gedaan:** `tools_catalog.inc` leest de site-`data/tools.json`
      en voegt die groepen toe aan de launcher; items met een absolute href
      (`/tools/…`) openen in een nieuw tabblad (`data-external` in
      `cma-launcher.js`). karaat levert nu `data/tools.json` (groep "Karaat
      onderhoud"). Data-gedreven — het platform kent geen site-bestandsnamen.
- [ ] Ontdek alle site-specifieke tools (bv. `karaat/tools/*.php` zoals
      `sg_recalculate.php`, `fill_missing_prices.php`, `detect_stone.php`,
      `find_duplicate_stone.php`, `similar_stones.php`, `quick_add_stone.php`)
      en surface ze **generiek** in de CMA `tools.php` / launcher, zonder per
      site handmatig aliassen in `$toolNameMap` te hoeven zetten.
- Idee: een conventie/manifest per site (bv. `data/tools.json` of een
      `tools/*.php` docblock-tag) die het platform inleest en als launcher-tegels
      toont — analoog aan hoe `menu.json`/`reports` per site werken. Platform
      blijft de generieke lader; de site levert alleen de lijst + metadata
      (label, icoon, groep, rechten).
- Let op: `tools.php` staat in het platform; de site-tools staan in de
      consumer-repo. De koppeling moet dus data-gedreven zijn (geen platform-code
      die karaat-bestandsnamen kent).

## `library/json/JSON.inc` naar `library/.deprecated/` verplaatsen

**Context (2026-07-27):** `JSON.inc` is de PHP-wrapper voor de legacy `aspJSON`-class
uit de ASP-conversie. De class doet zelf niets meer dan `json_decode`/`json_encode`
aanroepen, dus er is geen reden meer om hem te leveren. In het platform is hij al
nergens in gebruik (nul verwijzingen buiten het bestand zelf); karaat gebruikt hem
ook niet. mijnRINO was de enige consumer: ~30 `require_once` + 38 call-sites in 15
bestanden, en die zijn op 2026-07-27 omgezet naar `json_decode($x, true, 512,
JSON_THROW_ON_ERROR)` (branch `twig-implementation`, nog niet gecommit/uitgerold).

**Waarom dit nog niet gedaan is:** de *gedeployde* mijnRINO (main) requiret het
bestand nog. Zodra het pad in `Installer.php::REMOVED_PATHS` staat, wist een
`composer update` het van de site — dat is een gegarandeerde fatal op elke pagina
die het nog inlaadt. Dus: pas verplaatsen nadat de mijnRINO-conversie gecommit én
uitgerold is.

### Work items
- [ ] Verifieer dat de mijnRINO-conversie live staat: `grep -rn "AspJSON\|JSON.inc"`
      op de site levert niets meer op (behalve het bestand zelf).
- [ ] `git mv library/json/JSON.inc library/.deprecated/JSON.inc` (de map bestaat nog
      niet; mijnRINO gebruikt dezelfde `.deprecated/`-conventie, het platform tot nu
      toe `*_DEPRECATED.php` — hiermee wordt `.deprecated/` de conventie voor hele
      mappen).
- [ ] Voeg `'library/json/JSON.inc'` toe aan `Installer.php::REMOVED_PATHS` met een
      regel uitleg, anders houden consumer-sites het dode bestand voor altijd.
- [ ] Let op: de Installer synct heel `library/`, dus na de verplaatsing wordt
      `library/.deprecated/JSON.inc` alsnog naar sites gekopieerd. Wil je dat niet,
      sluit `.deprecated/` dan uit in de sync (en documenteer die uitzondering), of
      verwijder het bestand meteen helemaal in plaats van te verplaatsen.
- [ ] Bump de versie + tag, en meld in de commit dat de aspJSON-compat weg is.
