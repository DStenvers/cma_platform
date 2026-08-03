# TODO

## Dubbele componenten samenvoegen

Inventarisatie 2026-08-03: per UI-taak is geteld hoeveel losse implementaties er
in `library/` + `cma/` naast elkaar staan. Volgorde hieronder = volgorde van
aanpakken. Elke stap is los af te ronden (component + call-sites + minify +
documentatie).

### 1. Melding — vier lagen boven één component

| Laag | Waar | Rol |
|---|---|---|
| `lib-toaster` → `window.libToast` | `library/webcomponents/lib-toaster.js:418` | zwevende toast, verdwijnt vanzelf |
| `lib-message` → `window.libMessage` | `library/webcomponents/lib-message.js:313` | blok in de pagina, blijft staan |
| `lib-dialog` → `libAlert/libConfirm/libPrompt` | `library/webcomponents/lib-dialog.js:1294` | modaal, vraagt om een klik |
| `cmaNotification` | `cma/assets/js/form-controller.js:889` | dunne omhulling om `libToast` |
| `CmaFormController.showNotification` | `cma/assets/js/form-controller.js:5215` | roept `cmaNotification` aan |
| `CmaInlineEdit.showNotification` | `cma/assets/js/inline-edit.js:2498` | eigen kopie van dezelfde omhulling |
| `cmaApiError.showError` | `cma/assets/js/form-controller.js:103` | keten `cmaNotification` → `libMessage` → `libAlert` |
| `imgEditor.toast` | `cma/assets/js/image-editor.js:631` | kale `alert()` |

De drie componenten zijn géén duplicaten van elkaar — toast, blok en modaal zijn
drie verschillende gesprekken met de gebruiker. Het dubbele zit in de vier
omhullingen: ze doen alle vier `libToast[type] || libToast.info` met een
`typeof`-wacht eromheen. Geen enkele consumer-site gebruikt `cmaNotification`
zelf (nagekeken in karaat, rec, klei, mijnRINO), dus die naam mag weg.

- [ ] `libToast.show(message, type, duration)` toevoegen — het enige punt dat een
      dynamisch type aanneemt; dat is precies wat elke omhulling zelf nabouwde.
- [ ] `cmaNotification` verwijderen; de twee aanroepen in `form-controller.js`
      naar `libToast` laten wijzen.
- [ ] `CmaInlineEdit.showNotification` laten delegeren (`showSuccess`/`showError`
      blijven — die voegen echt iets toe: `showError` meldt óók aan
      `CmaErrorHandler`).
- [ ] `cmaApiError.showError`: de driedubbele terugvalketen wordt één
      `libToast.error`.
- [ ] `imgEditor.toast`: de kale `alert()` eruit.
- [ ] Storybook + documentatie: één tabel "welke melding kies je wanneer"
      (toast / blok / modaal), zodat de volgende omhulling niet ontstaat.

### 2. Venster — vijf manieren om iets te openen

| Manier | Waar | Aantal |
|---|---|---|
| `lib_OpenWindowCentered` | `library/library.js:1127` | 108 aanroepen |
| `lib_OpenSidePanel` | `library/library.js` | zijpaneel-variant van hetzelfde |
| `lib-dialog` | `library/webcomponents/lib-dialog.js` | 27 in de opmaak, 30 via `LibDialog.*` |
| `lib-sheet` | `library/webcomponents/lib-sheet.js` | 66 verwijzingen |
| kale `window.open` | 13 bestanden | 28 aanroepen |

`lib_OpenWindowCentered` is geen echt browservenster maar een nagebouwd venster
in de DOM (`__lib_win<n>`, eigen z-index-beheer, eigen maximaliseren). Het staat
volledig los van `lib-dialog`, dat hetzelfde probleem met shadow DOM oplost.
`CMA.utils.openFormPopup` (`cma/assets/js/cma-utils.js:472`) is de enige plek die
al kiest tussen drie van deze vijf op basis van een gebruikersvoorkeur.

- [ ] Bepaal de bestemming: `lib-dialog` als enig venster, met `lib-sheet` als
      smalle-schermvariant. `lib_OpenWindowCentered` levert vandaag iets dat
      `lib-dialog` niet heeft — een iframe met een hele pagina erin — dus dat
      moet er eerst in.
- [ ] `lib_OpenWindowCentered`/`lib_OpenSidePanel` achter `openFormPopup` houden
      en de 108 directe aanroepen daarheen verleggen.
- [ ] De 28 kale `window.open`-aanroepen langslopen: welke moeten écht een
      browservenster zijn (afdrukken, export) en welke horen in een dialoog.

### 3. Tooltip — component naast een eigen singleton

`lib-tip` (`library/webcomponents/lib-tip.js`) naast `cmaTooltip`
(`cma/assets/js/cma-utils.js:683`, één `div.cma-tooltip` met `data-tooltip`).
Twee positioneringsalgoritmes, twee stylings, dezelfde taak.

- [ ] Kies `lib-tip`, laat `data-tooltip` erop uitkomen, verwijder de singleton.

### 4. Tabel — drie renderers, waarvan één dood

| Implementatie | Waar | Stand |
|---|---|---|
| `lib-table` (web component) | `library/webcomponents/lib-table.js` (2920 regels) | in gebruik |
| `LibTable` (PHP, globaal) | `library/classes/class_table.inc` (47 KB) | in gebruik |
| `Cma\LibTable` (PHP) | `cma/classes/LibTable.php` (12 KB) | **nergens ge-`require`d** |

`cma/classes/LibTable.php` staat in geen enkele `require_once` in
`cma/bootstrap.inc` en de `Cma\`-namespace wordt niet ge-autoload — het bestand
draait dus nooit.

- [ ] `cma/classes/LibTable.php` verwijderen + rij in `Installer.php`
      `REMOVED_PATHS` (anders houden bestaande sites het bestand).
- [ ] Daarna: overlap tussen `LibTable` (PHP) en `lib-table` (JS) in kaart —
      sorteren, filteren en exporteren zitten in allebei.

### 5. Menu — component naast met de hand gebouwde menu's

`lib-menu`/`lib-menu-item` (3 aanroepen) tegenover `.cma-context-menu`, dat als
losse HTML wordt opgebouwd in `lib-table.js:2544`, `inline-edit.js` en
`form-controller.js`, met styling in vier stylesheets (`lib-table.css`,
`inline-edit.css`, `form.css`, `style.css`).

- [ ] Het exportmenu en het rijmenu op `lib-menu` zetten, of `lib-menu`
      verwijderen als `.cma-context-menu` de bedoelde vorm is. Nu betaalt de
      pagina voor allebei.

### 6. Laadindicator

`lib-loader` (20 aanroepen, ook netjes gebruikt in `form-controller.js:11656`)
naast een eigen ring in `cma-launcher.js:346`
(`.cma-launcher__spinner-ring` + `__spinner-text`).

- [ ] `cma-launcher` op `lib-loader` zetten.

### 7. Dode componentbestanden opruimen

Geen enkele `<script>`-verwijzing, geen aanroepen:

- [ ] `cma/webcomponents/UNUSED_cma-checklist.js` (670 regels)
- [ ] `cma/webcomponents/UNUSED_cma-rights-matrix.js` (674 regels)
- [ ] `cma/webcomponents/cma-combo.js` — lege plaatshouder; `lib-combo.js`
      registreert zelf al `cma-combo`
- [ ] `cma/assets/js/UNUSED_form-helpers.js` (275 regels)

Alle vier ook in `Installer.php` `REMOVED_PATHS`, plus de `.min.js`-tweelingen.

### Al samengevoegd (niet opnieuw oppakken)

- Tabs: `pagetabs` en `LibTabs` weg, `responsive-tabs` weg, `cma-tabs` is de
  enige. Rest van de front-end-migratie staat in het geheugen, niet hier.
- Combo: `lib-combo.js` registreert zowel `lib-combo` als `cma-combo`.
- Datumkiezer: `library/datepicker.js` is nog maar een `document.write`-omhulling
  om `lib-datepicker` — één implementatie, twee ingangen.
- Editor: `cma-htmledit` / `blockedit.js` / `cma-blockeditor` staan hieronder als
  eigen (geparkeerd) onderwerp.

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
- [x] **Gedaan:** `cypress/e2e/auth/login-layout.cy.js` — 8 asserties over desktop-
      en telefoonbreedte, icoon-vrije padding en het wachtwoord-vergeten-veld. De
      diagnostische `diag/login-width.cy.js` (die niets assertte) is vervallen.
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
