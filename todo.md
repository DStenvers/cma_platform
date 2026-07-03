# TODO

## Wire `<cma-blockeditor>` in as a real replacement for the CKEditor-based block editor

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
