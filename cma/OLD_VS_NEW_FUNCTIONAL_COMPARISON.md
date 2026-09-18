# Old CMA (classic ASP) vs new CMA platform — functional comparison

Date: 2026-09-18. Scope: everything a CMA user touches — list views, record forms, navigation,
login, users/groups/rights, tools, reports, templates, wizards. The earlier
`FORM_COMPARISON_REPORT.md` covers the *definition* migration (repository.mdb → JSON) and is
not repeated here. Old code: `/mnt/c/repos/adam/mijnrino/CMA/`; new code: `cma/` in this repo.

Every claim below cites file:line in both codebases. The ten items marked ✓ in the priority
list were re-verified by hand on 2026-09-18; the rest come from the same code reading and
are cited so they can be checked in a minute each.

Legend: **present** = equivalent · **partial** = weaker/narrower · **missing** = no counterpart · **different** = deliberately other behaviour.

---

## 1. Priority findings

These are the items most likely to bite a user today. They are regressions against behaviour the old CMA had, not new features.

### P0 — data integrity, access, correctness

| # | Finding | Old | New | Why it matters |
|---|---|---|---|---|
| 1 ✓ | **Save and delete on JSON forms require admin level**, not the form's "Volledig" group right | `details.asp:272-283` (rights ≥ Full) | `classes/FormDataProvider.php:786, 1696` → `SecurityHelper::isAdmin()` (level ≥ ADMIN, `SecurityHelper.php:133-139`) | A non-admin user with Full rights on a form is refused on save. Group rights are effectively read-only. |
| 2 ✓ | **Group 0 "Iedereen" is unusable**: memberships dropped, rights not loaded or saved, no delete guard | `sec_user_maint_post.asp:85` (everyone member), `sec_group_maint.asp:180` | `RecordService.php:1573-1580` (`$v > 0`), `:1693`; `JsonFormRenderer.php:196`; `SecurityHelper.php:1040` | Baseline rights via "Iedereen" no longer apply. |
| 3 ✓ | **Group flag `isBeheer` gone**; FULL_BEHEER only for admins | `include/security.inc:115-141` | `SecurityHelper.php:481, 595, 700`; no field in `groups.json` | Non-admin "beheerders" lose beheer-only fields and subforms (`FormTemplate.php:1610-1618, 2083-2125` still hide them). |
| 4 | "Alleen eigen records" (level 20) cannot be assigned in the rights matrix, and record-level own-data filtering is not applied to JSON lists | `sec_group_maint.asp:303-305`; `list.asp:560-563, 1076-1080` | `JsonFormRenderer.php:184-189`; only legacy path `TreeService.php:66-68` | Level still honoured at runtime, but unreachable through the UI; lists show all rows. |
| 5 | **Expression defaults lost and DB defaults defeated**: empty fields are posted and stored as explicit NULL | `details.asp:569-606` (`Now()`, `dateadd`, `GenGUID()`) | `form-controller.js:9274-9328` static only; `FormDataProvider.php:917, 1915-1918`; `StartwaardeMigratie.php:1-30` drops them | `datestamp`/deadline columns end up NULL on new records. |
| 6 | **Readonly fields are written back** with their display formatting | `detailsRep_post.asp:165, 400` skipped them | `FormDataProvider.php:833-862, 906-959`; `FormRenderer.php:1232-1236` (`readonly`, not `disabled`); `form-controller.js:8640-8652` | e.g. currency shown as `5,5` saved back into a numeric column. |
| 7 | ~~Server-side validation removed~~ **Restored 2026-09-18** (`FormDataProvider::validateJsonFormData`, readonly fields no longer written) (required, numeric, date, e-mail list, time, URL, directory uniqueness) and `__fully_loaded` guard not checked | `detailsRep_post.asp:65-68, 415-446, 483, 502-522, 990-1020` | `FormDataProvider.php:783-1176` has none; only users form `form_api.php:975-986` | Client-only validation; any direct POST bypasses it. |
| 8 ✓ | **Daily data-notification task mails on every run** and the "days before" input is gone; the notification editor posts scalars where the saver expects arrays, so nothing persists | `task.asp:108-125`; `sec_user_maint.asp:330-375` | `task.php:44-68`; `JsonFormRenderer.php:130-153` vs `RecordService.php:1899-1912` | Spam or silence, never the intended warning. |
| 9 | **E-mail notifications on record changes are no longer sent**; only `tblCMAMonitoring` is written | `detailsRep_post.asp:709-762` | `FormDataProvider.php:2404-2529` | Users can still *subscribe* (`RecordService.php:1830-1879`) but never receive mail. |
| 10 | ~~Soft delete (`DeletedField`) replaced by hard DELETE~~ **Retired by decision (2026-09-18)**: no form ever used `DeletedField`; schema and docs now state deletes are permanent | `detailsRep_post.asp:112-122` | `FormDataProvider.php:1755` | — |
| 11 | ~~AfterPostUrl practically dead~~ **Reactivated 2026-09-18** (POST with all fields on save and delete, `FormTemplate::resolveAfterPostUrl`): only set in inline-table mode, executed as a bare GET with `[ID]`, never on delete | `detailsRep_post.asp:100-107, 865-893` (POST all fields + `_old_*` + changelog) | `form-controller.js:5861-5863, 12317-12340` | Site hooks that reacted to saves (mailings, sync) silently stop. |
| 12 ✓ | **JSON tree view has no row limit at all**; the 500-row "search first" gate is commented out | `list.asp:19, 528-543` | `TreeService.php:367-402` (no TOP, ignores `limit`); `form-controller.js:6867-6883` | A large form switched to tree view renders every row. Table view is safe (keyset batches). |
| 13 | Checkbox filter in the search panel emits `[col] = 1`; Access needs `-1`/`True` | `list.asp:458, 832` (`=True`) | `ListServiceHelper.php:253-256`; `SQL.php:617-620` rewrites only `= true` | "Ja" filter likely returns 0 rows on Access. Verify. |
| 14 | Table mode ignores the definition's own `listQuery` WHERE and ORDER BY (replaced by `ORDER BY [id]`) | `list.asp:315, 644` | `JsonFormService.php:286-294, 362-376, 443` | Groups sorted by ID instead of name; filtered lists show everything. |
| 15 | Image save no longer maintains `imgWidthField/imgHeightField`, `_tn` thumbnails or HTMLStrip plain-text copies | `detailsRep_post.asp:187-312, 524-542` | `FormDataProvider.php:783-1176`; `form_api.php:1004-1014` (WebP instead) | Front-end code that reads those columns gets stale data. |
| 16 | Directory field type (uppercase, illegal chars, uniqueness, `_old` rename) is a plain textbox | `details.asp:854-884`; `detailsRep_post.asp:411-413, 502-522` | `FormRenderer.php:95-97` | |
| 17 | App-wide IP allowlist (`cma_ip_protect`) has no equivalent | `default.asp:34-41` | — | Per-user/group IPs remain (and are better). |

### P1 — lost nuances users will notice

| # | Finding | Old | New |
|---|---|---|---|
| 18 ✓ | **Record status text invisible**: `updateStatus('Toevoegen' / 'Wijzigen' / 'Gekopieerde gegevens toevoegen' / '(beheer)')` writes to `#toolbar-status`, which the form template never renders | `details.asp:383-409` | `form-controller.js:11877-11881`; no such id in `FormTemplate.php` |
| 19 | **"Laatste gewijzigd" (who/when) never shown or stamped on JSON forms** although 11 definitions set `storeLastModified` | `detailsRep_post.asp:144-151`; `edit.inc:258-267` | `FormDataProvider.php:760-766, 783-1176`; block hidden `FormTemplate.php:1512-1518` |
| 20 ✓ | **Popup "Bewaar" always closes**; the old "save, stay open, subforms become available" flow for parent-then-children is gone | `toolbar.inc:12-28, 200-213` | `form-controller.js:4585` |
| 21 | Save is sent even when nothing changed (extra writes + a no-op audit row) | `all.js:692-712` | `form-controller.js:9411-9440` |
| 22 ✓ | **Numeric inputs capped at 8 characters** (old: precision-based) | `details.asp:553-558`; `edit.inc:324` | `FormRenderer.php:242-248` |
| 23 | Date/time shorthand (`01012026`, `9:3` → `9:30`) silently discarded, no "ongeldige dag/maand" feedback | `formval_nl.js:274-414` | `lib-timepicker.js:436-465`; `lib-datepicker.js:863-936` |
| 24 ✓ | **Group-box collapse state collides across forms**: renderer reads `formId`, template passes `sourceFormId` → `form-id="0"` everywhere | `details.asp:498-514` | `FormRenderer.php:1061`; `FormTemplate.php:1641`; `cma-groupbox.js:79-94` |
| 25 | Required marker `*` with tooltip and the per-field "actie" glyph (vervalt/beheer/readonly) are gone; only a red border on empty inputs | `details.asp:753-766`; `style.css:318-322, 2367-2398` | `FormRenderer.php:1101-1185`; `form.css:23-40` |
| 26 | Keep-with-next (two fields on one row) hard-disabled | `details.asp:700-702, 1174-1184` | `FormTemplate.php:1613-1615` |
| 27 | Quick search no longer looks in memo/long-text or subform fields; `" en "` AND-logic unused | `list.asp:337-343, 366-410`; `lib_sql.inc:487-497` | `JsonFormService.php:126-151, 167`; `SQL.php:1692` (ported, uncalled) |
| 28 | "Search in all records" bypassing the forced filter (`NoAutoFilter`) gone; tooltip still promises it | `list.asp:26, 585, 726` | `form-controller.js:6466-6471`; `JsonFormService.php:356-360`; `FormTemplate.php:642` |
| 29 | Tree open-state stored by node *index* instead of folder name → wrong folders re-open after any insert/delete | `ftiens4.js:301-330` | `cma-tree.js:290-321` |
| 30 | Simple-tree row refresh wipes the thumbnail and skips re-sort/re-group | `details.asp:144` → `list.asp:119-122` (full reload) | `form-controller.js:7302-7311`; `TreeService.php:531-534` |
| 31 | Recursive (self-referencing) tree unsupported | `list.asp:957-1005` | `FormDefinition.php:117, 566` (constant only) |
| 32 ✓ | Remembered login name deleted at logout | `logout.asp` (kept 365 d, `login.asp:94`) | `logout.php:22` |
| 33 | Menu group click no longer opens its first item; search criteria not carried across forms | `all.js:574-603, 623-627` | `main.js:390-420, 600-650` |
| 34 | Module-parameters editor (`tblModuleParameters`) removed | `mod_maint.asp:169-260` | — |
| 35 | Combo `Group\|Item` → OPTGROUP and `<br>` → ", " cleanup not reproduced; `=[ProdID]` → `is null` for new records gone | `edit.inc:206-226` | `FormDataProvider.php:1370-1380, 1533-1540` |
| 36 | Copy via URL loses checklist selections; `clearflds` gone | `details.asp:960-965` | `form-controller.js:8138-8235` |
| 37 | UserList field no longer admin-gated / defaulted to current user | `details.asp:525, 690-697` | `FormTemplate.php:1843` |
| 38 | `[GUID2]` reads `guid2` instead of `secret`; `[omgeving]` not substituted in extra buttons | `toolbar.inc:100-111` | `form-controller.js:2132-2133, 10570-10590` |
| 39 | Cached form template bakes request state (`body.popup`, `__ParentField/Value`) into a per-form cache | — | `FormTemplate.php:106-149, 502-533, 1539-1544` |
| 40 | Rights-matrix labels forced to `Ucfirst(lower)` ("CGO document" → "Cgo document"); users IP hint says comma, runtime splits on `;`; no IP validation | `sec_group_maint.asp:284-288`; `sec_user_maint_post.asp:31-35` | `JsonFormRenderer.php:451`; `users.json`; `login.php:127` |
| 41 | Notification checklist shows raw JSON form keys and no subforms | `sec_user_maint.asp:283-311` | `JsonFormRenderer.php:97-105` |
| 42 | Report toolbar lost Print button and the long Dutch date; report loader spinner gone; print-only edit URL gone | `toolbar.inc:250, 278-281`; `reportdetails.asp:87-88, 393` | `ToolbarHelper.php:378 (unused), 438-488`; `reportdetails.php:716` |
| 43 | Template indexer still scans `.asp/.htm/.html`, never `.php` | `template_fillrep.asp:410` | `template_fillrep.php:52` |
| 44 | Clear-cache no longer bumps the asset version; consistency tool lost the "HTML-stripped fields" resync | `tools_clearcache.asp:122-125`; `tools_db_consistency.asp:258-300` | `bootstrap.inc:788-790`; `tools/tools_db_consistency.php` |
| 45 | Link wizard lost mailto builder, upload-and-link, ImageZoom links; `wizards/link-pages.php` is dead code with stale logic | `wizards/link-pages.asp:484-573` | `html_edit_link.php`; `wizards/link-pages.php:100-114` |
| 46 | `onLoadJS` not run for new records; memo `maxChars` counter inert; CKEditor blur cleanup gone; editor height not sized to content | `details.asp:206, 912-934, 1245-1266` | `form-controller.js:2144, 8946`; `FormRenderer.php:634, 646-665` |
| 47 | Marketing URL screen lost "Bekijk" preview, host prefix hint, auto-date and update-on-duplicate | `url_maint.asp:146-180`; `url_maint_post.asp:244-276` | `marketingurl.json`; migration `9.14.0` unique index |

### P2 — small display optimisations worth adopting

1. Placeholder with the form name: `Zoeken in 'Cursisten'...` (`list.asp:621`); new uses two different generic strings (`FormTemplate.php:691,701`, `form-controller.js:6149`).
2. `user-select: none` on the list panel and toolbar (`list.asp:635`, `toolbar.inc:39`): double-click now opens a popup and selects text.
3. Instant active highlight on click (`ftiens4.js:182-185`) instead of after the fetch (`form-controller.js:7530-7538`).
4. Search-as-you-type threshold scaled to list size: 3 chars above 2000 rows (`all.js:413`); new filters from the first character regardless of size.
5. Show the tree title with the form name (generated in `TreeService.php:462`, hidden by `style.css:754-755`).
6. Fold "expanded" width as a percentage (old 50 %, `style.css:231-235`) and per form (`list.asp:209-214`) instead of a global 600 px cap (`FormTemplate.php:573`).
7. Apply the remembered toolbar filter server-side on first paint (`list.asp:475-479`) to avoid the empty-combo flash (`form-controller.js:6547-6628`).
8. Month-name fix on tree items, not only folders (`list.asp:1161,1164` vs `TreeService.php:647`).
9. Clear error when `DetailField` is missing (`list.asp:1027-1040`) instead of a silent fallback (`TreeService.php:503-517`).
10. `<sort:value>` and `<html>` cell prefixes for custom sort keys and raw HTML (`class_table.inc:267-294`), unsupported by `JsonFormService.php:706-768`.
11. Required `*` beside the caption with "Verplichte invoer" tooltip; stays visible when filled or readonly.
12. "Laatste gewijzigd" tip card (markup exists, `form.css:751-780`) once #19 is fixed.
13. Post-caption hints `dd-mm-jjjj` / `uu:mm` at 11 px (`style.css:1247-1253`) instead of 9 px italic (`form.css:803-812`).
14. Image size hint next to the control ("Maximaal: 300px breed - 200px hoog", `details.asp:1150-1172`), not only inside the editor.
15. Sortlist A→Z / Z→A buttons and Ctrl+arrow hint (`details.asp:1369-1381`).
16. Auto-growing textareas and content-sized editor (`details.asp:912-917, 1244`).
17. Pressed-button feedback on Save/New/Copy (`style.css:43-49`, `all.js:700`); also listed in `todo.md`.
18. Collapsed groups rendered collapsed server-side to avoid the expand→collapse flash (`details.asp:508-515`).
19. Focus and tab-switch to the first invalid field after validation (`formval_nl.js:94-112`).
20. Parent record shown as a read-only label when adding from a subform (`details.asp:807-817`) instead of a hidden row.
21. Auto-open the first item when a menu group is expanded (`all.js:623-627`); focus the password field when the login name is pre-filled (`login.asp:291`).
22. Red menu bar on TEST (`style.css:276-278`) versus a small label; " (beheer)" suffix in the group list (`sec_list_groups.asp:210`); logo tooltip "Ga naar de site" (`menurep.asp:141`).
23. Report toolbar: Print button and `FormatDateTime(now(), vbLongDate)`; loader spinner only after 500 ms (`reportdetails.asp:87-88`).

### What the new CMA does better (do not regress)

Table view with sticky headers, column filters, chooser and drag order, inline editing, infinite scroll with keyset paging and record counters, multi-field search panel with ranges, search highlighting, grouped tree built by key, drag-and-drop file browser with crop/rotate/WebP, in-place AJAX save with row refresh and URL sync, cancel/leave confirmations with a change summary, server-side changelog and delete audit, passwords never sent to the client, dual-cookie session validation and CIDR IP rules, last-admin/self-delete guards, bulk rights radios, tools launcher (backup, migrations, logs, settings), SQL tool with schema pickers and UPDATE-without-WHERE guard, report designer and CSV export, dashboard, tours, preferences, themes. Details per area in the appendices.

---

## 2. Suggested order of work

1. P0 #1–#3 (rights): one change in `FormDataProvider` (use form rights, not admin level), restore group 0 and `isBeheer` in `SecurityHelper`/`RecordService`/`groups.json`.
2. P0 #5–#7 (save path): skip readonly fields, keep DB defaults by omitting empty columns, port the server-side validators from `detailsRep_post.asp`.
3. P0 #8–#11 (notifications, soft delete, AfterPostUrl): decide per feature whether to port or retire; if retired, remove the UI that still offers it.
4. P0 #12–#14 (lists): TOP/limit for the JSON tree, `= True` for boolean filters, honour `listQuery` WHERE/ORDER BY in table mode.
5. P1 #18–#24 (five one-line fixes): render `#toolbar-status`, stamp and show `LastModified*`, "Bewaar" vs "Bewaar en sluit" in popups, numeric maxlength from precision, `formId` key in the group-box config.
6. P2 items as small polish commits.

---

## Appendix A — List views (detail)


Scope note: in the old CMA the only list mode real users had was the **tree** (`strListMode = LIST_TREE` unless `application("cma_development")`, `/mnt/c/repos/adam/mijnrino/CMA/list.asp:285-298`). The old `LibTable` "table mode" was dev‑only. `include/list.js` and `include/dynamictable.js` are dead code (not referenced by any `.asp`/`.inc`; verified with grep), `include/rep_util.inc` is 0 bytes. "Toon alles" does not exist anywhere in the old list code.

---

### 1. Feature-by-feature table

| # | Old feature | Old location | Status in new | New location | Note |
|---|---|---|---|---|---|
| 1 | Simple tree (flat `<a>` list under bold form title) | `list.asp:1051-1054, 1167-1169` | present | `classes/Services/TreeService.php:461-462, 528-534` | New hides the title: `#simpletree div.titel{display:none}` `cma/assets/css/style.css:754-755` (beats `form.css:1576`). |
| 2 | Grouped tree (3 group levels, folders) | `list.asp:1012-1194` (GetTree), `include/ftiens4.js` | present, different impl | `TreeService.php:575-586, 603-680`, `cma/webcomponents/cma-tree.js` | New groups by key (old relied on ORDER BY; unsorted data produced duplicate folders). |
| 3 | Recursive tree (`recurseField`, self-referencing parent) | `list.asp:549, 663-666, 957-1005` | **missing** | only a constant: `classes/FormDefinition.php:117,566`; no use in `TreeService`/`JsonFormService` | |
| 4 | Empty-group folder `[Info ontbreekt]` | `list.asp:1068-1071` | present, different label `[leeg]` | `TreeService.php:633-637` | New is actually correct for every empty group; old only for the first. |
| 5 | Tree open/closed state persisted (cookie `tree<FormID>`, keyed by **folder label path**) | `ftiens4.js:301-330`, `list.asp:852-856` | present, weaker | `cma-tree.js:290-321` (localStorage, keyed by **sequential node index**) | See nuance 2.1. |
| 6 | Root folder auto-unfolded on load | `ftiens4.js:251-252` | present | `cma-tree.js:437-442` | |
| 7 | Expand all / collapse all toolbar buttons | `include/toolbar.inc:27-31`, `list.asp:593-595` | present | `classes/FormTemplate.php:653-656`, `cma-tree.js:133-155`, `form-controller.js:5248-5281` | |
| 8 | Active item highlight from URL `ID`, orange (#ff6400) | `list.asp:186-188`, `style.css:1402-1408` | present (primary colour) | `TreeService.php:529`, `form.css:1618-1622`, `form-controller.js:7738-7807` | New also scrolls into view + expands parent folders (`7801-7805`). |
| 9 | Active set immediately on click (`act(this)` / `hi()`) | `list.asp:661, 1168-1169`, `ftiens4.js:182-185` | different | `form-controller.js:7530-7538` | New sets `active` only after `loadRecord` resolves. |
| 10 | Real `href=details.asp?ID=…` links, `target=R` | `list.asp:1156, 1168`, `ftiens4.js:187-195, 277` | **different** | `TreeService.php:534` (`href="javascript:void(0)"`), `cma-tree.js:615` | Ctrl/middle-click "open in new tab" lost. |
| 11 | Search-as-you-type (client side) | `include/all.js:407-447` | present | `form-controller.js:5963-6055` | Old: min letters 1/2/3 scaled to list size (413-417); new: from 1st char, 100 ms debounce. |
| 12 | SAYT: single remaining result auto-opened | `all.js:439-441` | present for simple tree/table, **missing for grouped tree** | `form-controller.js:5981-5986` returns before `_autoSelectSingleVisibleItem` (6052-6054) when `cma-tree` is used | |
| 13 | SAYT: single remaining folder auto-expanded | `all.js:435-437` | present | `cma-tree.js:187-212` expands ancestors of matches | |
| 14 | Quick search (Enter) server side | `list.asp:322-346, 356-364` | present, narrower | `JsonFormService.php:296-354` (table), `TreeService.php:376-391` (tree) | Old: `quickFilterFields` or **all** Memo/Textbox/Directory/EMail/Label/Image/File/Url/Htmlstrip fields. New: `quickSearchFields` or visible listColumns (memo excluded at `JsonFormService.php:126-151,167`). |
| 15 | Quick search splits on ` en `/` and ` → AND of terms | `library/lib_sql.inc:460-500` (`lib_SQL_ComplicatedWhere`) | **missing** | `src/helpers/SQL.php:1692-1750` port exists but is not called from any list service (grep) | |
| 16 | Quick search also searches subform text fields (≤3 subforms, `ID IN (SELECT …)`) | `list.asp:366-410` | **missing** | – | |
| 17 | Search "in all records" bypassing the forced filter (`NoAutoFilter=Y&Search=Y`) | `list.asp:26, 585, 726` | **missing** | `form-controller.js:6466-6471` always re-adds the toolbar filter; `JsonFormService.php:250` only skips the *requirement* when quick search is used but still applies `filters` (356-360) | Tooltip "Uitgebreid zoeken binnen alle gegevens" (`FormTemplate.php:642`) overstates. |
| 18 | Advanced search: pick one field (select incl. **ID**), then type / combo / Ja-Nee radio | `list.asp:723-843` (ID option 742-747; combo 787-823; radio 829-834) | different (multi-field panel) | `FormTemplate.php:941-988, 1001-1114, 1198-1276` | New: AND over up to N fields, date/number ranges. ID field not in panel (only numeric quick search, `JsonFormService.php:340-349`). |
| 19 | Advanced search on combos uses the field's own list SQL, select2 auto-opened | `list.asp:802-821` | partial | `FormTemplate.php:1057-1069` skips record-dependent combos w/o `sourceTable`; `form-controller.js:6361-6421` lazy loads | |
| 20 | Unsupported field types shown as "Helaas… niet ondersteund" (dates/numbers) | `list.asp:836-837` | better | `FormTemplate.php:1215-1259` (date/number/time ranges) | |
| 21 | Forced filter form (`FilterFieldName`): select2 in toolbar, "Selecteer de … hierboven" instruction, combo auto-open, italic grey placeholder | `list.asp:600-631, 698-704, 894-950` | present | `FormTemplate.php:678-748`, `JsonFormService.php:250-272`, `TreeService.php:344-358`, `form-controller.js:6625-6628` | |
| 22 | Filter value remembered (cookie `CMAfilter<field>`, 365 d, shared across forms with same field) | `list.asp:90-96, 551-557` | present (localStorage `cma_filter_field_<field>`) | `form-controller.js:6529-6573` | Old cookie readable server-side ⇒ first paint already filtered (`list.asp:475-479`). |
| 23 | Opening a parent record sets the child filter (`filterIdName`, `s(nID)`) | `list.asp:216-222, 1169` | present | `form-controller.js:6636-6644, 8100` | |
| 24 | URL filter value ≠ cookie ⇒ redirect to cookie value | `list.asp:440-451` | different (record wins over filter) | `form-controller.js:7657-7675` | |
| 25 | Filter written only on explicit pick (`filterpick=1`) | `list.asp:551-557, 932` | present | `form-controller.js:6493-6523` (on `change`) | |
| 26 | Unknown `SearchField` carried from previous form is discarded | `list.asp:422-435`; `all.js:574-603` (`form()` carries Search*/ID params across forms) | n/a | – | Old quirk; new URL manager does not carry search to other forms. |
| 27 | `LIST_LIMIT` (500): table with > limit rows forces the search screen first | `list.asp:19, 528-543`, `lib_db.inc:599-607` | **missing** (deliberately disabled) | `form-controller.js:6867-6883` (commented out); `list_limit`=800 only in legacy int path `ListService.php:159` / `TreeService.php:127-129` | JSON **tree** loads all rows, no TOP/limit at all: `TreeService.php:368-402` ignores `$options['limit']`. Table: infinite scroll batches of 500 (`Settings.php:238`, `JsonFormService.php:281`). |
| 28 | Quick-search value stays in the box after searching (text types only) | `list.asp:602-620` | present | `form-controller.js:6144-6157` | |
| 29 | Search input autofocus, `autocomplete=off`, placeholder "Zoeken in '<Formnaam>'..." | `list.asp:602, 621`; `list.asp:193, 857-859` | partial | `FormTemplate.php:691,701` ("Zoeken...") then JS overwrites with "Zoek..." (`form-controller.js:6149`) / "Geen gegevens" | Form name in placeholder lost; two different placeholder strings. |
| 30 | Search icon in box submits (`zoekicoon`) | `list.asp:622` | present | `library/webcomponents/lib-search-input.js:185-213` | |
| 31 | Deep link `?SearchFor=x` searches on first load | `list.asp:23, 322` | partial | `form-controller.js:4542-4548` fills the box; init request omits `search` (`_doFormInit` 1914-1947) | Box filled, list not filtered until Enter. |
| 32 | Record-level security (`blnSecurityByUser` → only rows with `userid` = current user) | `list.asp:560-563, 648-655, 1076-1080, 1137-1141` | **missing** in JSON paths | only legacy int path `TreeService.php:66-68,190-191`; nothing in `JsonFormService::getTableHtml` / `TreeService::getJsonFormTreeHtml` | |
| 33 | Missing DetailField ⇒ explicit error dialog listing available columns | `list.asp:1027-1040` | partial | `TreeService.php:503-517` silently falls back to first non-ID column | |
| 34 | No DetailField ⇒ label = all columns joined with "," | `list.asp:1157-1161` | different | `TreeService.php:510-517` (first column only) | |
| 35 | Label clean-up: `chr(13)`→space, `Lib_FixDateValue` (oct→okt, may→mei) on items and folders | `list.asp:1087, 1103, 1126, 1161, 1164` | partial | `TreeService.php:647,660,673` (folders only, `Date::fixValue`); items raw (`534`) | |
| 36 | "Geen gegevens om weer te geven" under the title | `list.asp:1051`, `style.css:299-303` | present | `TreeService.php:553`, `JsonFormService.php:777-780` (adds ", gezocht naar: x") | |
| 37 | List reload after every save (`event_received_invalidate`) keeps sort/group correct | `list.asp:119-122`, `details.asp:144` | different | `form-controller.js:9555-9562` (new ⇒ `loadList`, existing ⇒ `refreshRow` 7247-7325) | See nuance 2.4. |
| 38 | After delete ⇒ list reload without ID | `detailsRepNew.asp:53` | present (targeted) | `form-controller.js:7332-7400` | |
| 39 | Fold handle: 3 states (20% / expanded 50% / folded 0), 2 round arrow buttons, hover cursors, 0.3 s transition, per-form cookie (dev only) | `list.asp:209-214, 875-877`, `style.css:159-252, 1011-1080` | present, richer | `FormTemplate.php:573`, `cma/webcomponents/cma-fold.js` (drag, dblclick, arrows, `min-size=150 max-size=600`, global key `form_fold`) | 50 % "expanded" > 600 px on wide screens; state not per form. |
| 40 | Text selection disabled in toolbar/list (`onselectstart=return(false)`, `.blockselect`) | `list.asp:635`, `toolbar.inc:39` | **missing** | no `user-select:none` on `#listContent`/`#leftlist` (`form.css:261-283, 1687-1690`) | Matters now that dblclick opens a popup (`form-controller.js:7548-7578`). |
| 41 | Toolbar select2 width scaled to panel (`scale_windows`) | `list.asp:224-244` | present via CSS flex | `form.css:1414-1447` | |
| 42 | Per-form item icons (`a.icon.<form>`), red/green li colouring hooks | `style.css:1437-1520` (red/green JS disabled at `list.asp:199-206`) | present | `cma-tree.js:547-559, 618-639, 389-397`; `style.css:777-785` | New colouring needs `node.active`/`online_indic`, which `TreeService.php:537-544` never sets ⇒ dormant in both. |
| 43 | Tooltip = full label on grouped-tree items (`title=`) | `ftiens4.js:189-191` | present (measured, only when truncated) | `cma-tree.js:640-731` | |
| 44 | Table mode (dev only, `LibTable`): alt row colours, hover/active row JS, sortable headers, `<sort:…>`/`<html>` cell prefixes, Ja/Nee booleans, date/time formatting, caption "_"→" " + first-upper | `list.asp:672-689`, `library/classes/class_table.inc:149-374` | present, different (lib-table) | `JsonFormService.php:455-781`, `library/webcomponents/lib-table.js`, `lib-table.css:101-201` | `<sort:>`/`<html>` prefix conventions not ported (`JsonFormService.php:706-768` escapes everything). |
| 45 | Extra icon buttons ([ID]/[GUID] substitution) – detail toolbar, not list | `toolbar.inc:231-283` | present | `FormTemplate.php:1352+`, `form-controller.js:10557-10727` | Out of list scope; hidden in table mode via `.requires-record` (`form.css:290`). |
| 46 | Performance log per list render | `list.asp:1201-1207` | present | `TableService.php:42, 427-432` (`PerformanceLogger`), `form_api.php:635-636` | |

---

### 2. Nuances likely lost (with evidence)

1. **Tree open-state drifts after data changes.** Old saved open folders by `parent.desc || desc` (`ftiens4.js:303-310`) and restored by name (`312-330`). New `cma-tree` saves `Set` of `_id` = position in a depth-first index that counts folders *and items* (`cma-tree.js:302-321`). Adding/removing one record shifts every later id, so restored "open" folders are the wrong ones.

2. **Forced search for big tables is gone; JSON tree has no limit at all.** Old: `lib_TableRecordCount_Cached > 500` ⇒ search form first (`list.asp:528-543`). New: check commented out (`form-controller.js:6867-6883`); `TreeService::getJsonFormTreeHtml` builds `SELECT … FROM [table]` with no TOP and ignores `$options['limit']` (`TreeService.php:367-402`), so switching a 50k-row form to tree view renders every row. Table mode is safe (keyset batches of 500, `JsonFormService.php:281, 385-446`).

3. **Quick search no longer looks in memo/long-text or subform fields, and no ` en ` AND-logic.** Old field set at `list.asp:337-343` (incl. `constFldType_Memo`, `Htmlstrip`), subforms `366-410`, AND-splitting `lib_sql.inc:487-497`. New table search is limited to `listColumns` (memo removed at `JsonFormService.php:126-151,167`), tree search to `listColumns` (`TreeService.php:376-391`); `SQL::complicatedWhere` (`src/helpers/SQL.php:1692`) is unused.

4. **Row refresh after editing an existing record in a simple tree drops the thumbnail and ignores re-sorting/re-grouping.** `refreshRow` sets `treeNode.textContent = data.displayText` (`form-controller.js:7302-7311`); the `<a>` contains the `<img class="cma-list-thumb">` (`TreeService.php:531-534`), which is wiped. Old always reloaded the list (`details.asp:144` → `list.asp:119-122`), so label, order and group were always right. (For `cma-tree` the selector misses shadow DOM and falls back to `loadList`, so grouped trees are fine.)

5. **"Search all records regardless of the required filter" is gone.** Old `Search=Y&NoAutoFilter=Y` (`list.asp:26, 585, 726`) let a user find e.g. a toets in any opleiding. New `applySearchFilters` re-injects the toolbar filter (`form-controller.js:6466-6471`) and the API always applies `filters` (`JsonFormService.php:356-360`).

6. **Record-level "own data" filtering is not applied to JSON lists.** Old `blnCheckUser` (`list.asp:560-563, 1076-1080`). New only in the legacy int-form path (`TreeService.php:66-68, 190-191`); `ACCESS_CHANGE_OWN_DATA` is otherwise unused in list services.

7. **Recursive (self-referencing) tree is unsupported** (`list.asp:957-1005` vs no consumer of `recurseField`).

8. **Checkbox filter in the search panel emits `[col] = 1`** (`ListServiceHelper.php:253-256`). On Access (primary driver per `CLAUDE.md:38`) Yes/No `True` is `-1`; `processSQL` only rewrites `= true` → `= -1` (`SQL.php:617-620`), not `= 1`. Old emitted `=True` (`list.asp:458, 832`). Likely returns 0 rows for "Ja" on Access – verify.

9. **Table mode ignores the definition's own query and ordering.** Old tree always used `NameQuery` incl. its WHERE/ORDER BY (`list.asp:315, 644`). New table: `table` wins over `listQuery` (`JsonFormService.php:286-294`), ORDER BY is stripped and replaced with `ORDER BY [id]` (`362-376, 443`). E.g. `tblGroups … ORDER BY grpName` (`assets/forms/definitions/*.json`) shows by ID in table view; any WHERE in `listQuery` is dropped in table view.

10. **Tree search does `LIKE` on every list column regardless of type** (`TreeService.php:378-383`), while the table path skips FK/bool and matches numerics exactly (`JsonFormService.php:317-337`). On SQL Server `intcol LIKE '%x%'` raises a conversion error; on Access it works.

11. **Deep link with a search term doesn't search.** `?search=` fills the box (`form-controller.js:4542-4548`) but `_doFormInit` sends no `search` (`1914-1947`). Old `SearchFor` was applied server-side on the first load (`list.asp:23, 322`).

12. **Single-match auto-open is skipped in grouped trees** (`form-controller.js:5981-5986` returns before `6052-6054`), whereas old clicked the last remaining item for both tree kinds (`all.js:439-441`).

13. **Advanced search of the ID field** (old always first option, `list.asp:742-747`) exists only implicitly via numeric quick search (`JsonFormService.php:340-349`), not in the panel.

---

### 3. Small display optimisations the old had that the new lacks / could adopt

1. **Placeholder with form name**: `Zoeken in 'Cursisten'...` (`list.asp:621`). New uses "Zoeken..." (`FormTemplate.php:691,701`) and then "Zoek..." (`form-controller.js:6149`) – pick one and add the form title.
2. **No text selection on double-click** (`list.asp:635`, `toolbar.inc:39`). Add `user-select:none` to `#listContent`/`#listToolbar` since dblclick now opens a popup (`form-controller.js:7548-7578`).
3. **Instant active feedback on click** (`act(this)`, `list.asp:661/1168`; `hi()` `ftiens4.js:182-185`). New waits for the fetch (`form-controller.js:7530-7538`); add a "pending/active" class immediately.
4. **Search-as-you-type threshold scaled with list size** (`all.js:413`: >2000 items ⇒ 3 chars, >1000 ⇒ 2). New filters on the first character for any size (`form-controller.js:5976-6030`), which on a 5k-row tree causes a full pass per keystroke.
5. **Tree title showing the form name** (`list.asp:1051,1054`, `style.css:1354-1362`). New generates it (`TreeService.php:462`) but hides it (`style.css:754-755`).
6. **Fold "expanded" = 50 % of window** (`style.css:231-235`). New caps the list panel at 600 px (`FormTemplate.php:573`) – consider `max-size` in % or larger.
7. **Fold state per form** (`list.asp:209-214`, cookie `list_state_<FormID>`) vs global `form_fold` (`FormTemplate.php:573`, `cma-fold.js:548-597`).
8. **Filter already applied on first paint** (server read the cookie, `list.asp:475-479`) – new reads localStorage in JS and then fetches (`form-controller.js:6547-6573`), so the combo may show a stale/empty value for up to ~1 s (`_applyToolbarFilterDisplay` retries, `6595-6628`).
9. **Month-name fix on item labels** (`Lib_FixDateValue`, `list.asp:1161,1164`) – new only on folders (`TreeService.php:647`).
10. **Clear error when DetailField is missing** (`list.asp:1027-1040`) instead of silent fallback (`TreeService.php:503-517`).
11. **`<sort:value>` / `<html>` cell prefixes** for custom sort keys and raw HTML in list cells (`class_table.inc:267-294`) – not honoured by `JsonFormService.php:706-768`.

---

### 4. Things the new does better (do not regress)

- Table view with sticky header, column filters/sort, column chooser + drag order, widths persisted, export, inline editing, row context menu, boolean `lib-switch` toggles, image thumbnails with hover preview (`FormTemplate.php:645-649`, `lib-table.css:101-108`, `form-controller.js:4717-5230, 5306-5360`, `inline-edit.js`, `table-preferences.js`, `cma-list-thumb.js`, `JsonFormService.php:716-729`).
- Infinite scroll with keyset pagination, distinct-safe total count, background prefetch and "records 1-X van Y" counter (`JsonFormService.php:385-446`, `form-controller.js:5701-5808, 5361-5410, 6173-6197`).
- Multi-field search panel with date/number/time ranges, boolean select, lazy combos, Enter to apply, "Wissen" (`FormTemplate.php:941-1276`, `form-controller.js:6435-6487`).
- Grouped tree built by key (no duplicate folders on unsorted data), `[leeg]` for every empty group, auto-expand of single child folders, measured truncation tooltips (`TreeService.php:603-680`, `cma-tree.js:323-339, 640-731`).
- Search highlighting (`form-controller.js:6223-6296`), auto-select of a single result, remember last record per form, scroll active into view and expand its parents (`6984-7045, 7738-7833`).
- Filter/record consistency: stale filter value cleared, deep-linked record moves the filter to its parent (`6605-6624, 7657-7675`).
- Hover prefetch of records, AbortController on list loads, unified `init` request (`2421-2491, 6779-6785`, `form_api.php:539-643`).
- Escaping of user input in the search box (old wrote `parSearchFor` raw into the HTML at `list.asp:619`), GUID/alphanumeric IDs (`form_api.php:551`, old `Lib_QueryString_Int("ID")` at `list.asp:28`).
- Draggable, double-click-collapsible fold bar with min/max and persisted size (`cma-fold.js`), tree/table mode remembered per form and globally, honoured server-side to avoid flicker (`form.php:214-224`, `FormTemplate.php:547-560`).
- Keyboard: Ctrl+S save, Delete removes record (`form-controller.js:3889-3908`); dblclick opens record in popup (`7548-7578`).

---

## Appendix B — Record forms (detail)

### Comparison report: CMA record edit form — classic ASP (`/mnt/c/repos/adam/mijnrino/CMA`) vs PHP/JS (`/mnt/c/repos/cma_platform/cma`)

Legend for "status in new": **present** = equivalent, **partial** = exists but weaker/narrower, **missing** = no counterpart, **different** = deliberately other behaviour.

#### 1. Feature-by-feature table

| # | Old feature | Where in old | Status in new | Where in new | Note |
|---|---|---|---|---|---|
| 1 | Toolbar status text "Toevoegen" / "Wijzigen" / "Gekopieerde gegevens toevoegen" + " (beheer)" | `details.asp:383-409`; `style.css:576-582` | **missing** | `form-controller.js:11877-11881` writes to `#toolbar-status`, but no such element exists in `FormTemplate.php` (only `id="recordCount" class="toolbar-status"` at `:695,705`) | `updateStatus('Toevoegen')` (`:9006`), `'Wijzigen'/'Bekijken'` (`:2086`), `'Gekopieerde gegevens toevoegen'` (`:10518`) are no-ops. Only sidepanel title suffix (`:9139-9167`) and `* ` in `document.title` (`:11846-11848`) remain. "(beheer)" marker gone. |
| 2 | Separate "Bewaar" and "Bewaar en sluit" buttons in popups | `toolbar.inc:12-28, 200-213`; `details.asp:340` (SaveClose only when `sParentID<>""`) | **different** | `FormTemplate.php:1303-1305` (one Save); `form-controller.js:4582-4590` `closeAfterSave = this.isInPopup()` | In a popup, Save *always* closes. Old "Bewaar" kept the popup open and reloaded the record (so subforms became available right after adding). |
| 3 | Save skipped when nothing changed (`form_dirty` false → no submit; just close if "sluit") | `all.js:671-715` (esp. `:692-712`) | **missing** | `form-controller.js:9411-9440` always validates + POSTs | Extra writes; with monitoring on, an "edit" audit row is logged for a no-op save (`FormDataProvider.php:1159`). |
| 4 | Ctrl-S saves | `details.asp:43-46` | present | `form-controller.js:3889-3895` | New also adds `Delete` key = delete record (`:3897-3906`). |
| 5 | `onbeforeunload` "Je laatste wijzigingen zijn nog niet opgeslagen" | `details.asp:245`; `all.js:737-746` | present | `FormTemplate.php:538`; `form-controller.js:3910-3916` | |
| 6 | Dirty indicator: save icon pulses once form really differs from defaults (polled 500 ms/1 s) | `all.js:160-172`, `:789-909`; `style.css:56-70` | present (different trigger) | `form-controller.js:3796-3805, 11823-11849`; `style.css:202-206`; `form.css:1557-1561` | New sets dirty on any `input`/`change` (even if value reverted); `hasUnsavedChanges()` (`:11858`) does the real diff only for confirms. Cancel button muted when clean (new). |
| 7 | Delete confirm + soft delete via `DeletedField` | `all.js:655-669`; `detailsRep_post.asp:112-122` | **partial** | `form-controller.js:10429-10497`; `FormDataProvider.php:1693-1788` (hard `DELETE` at `:1755`) | No `deletedField` support anywhere in JSON loader/provider (grep empty). FK-violation message is formatted (`:1764,1786`) — parity with `detailsRep_post.asp:619-631`. |
| 8 | Delete passes `blnPassOnToPostUrl` fields + changelog to AfterPostUrl | `detailsRep_post.asp:126-134, 865-893` | **missing** | `form-controller.js:10429-10497` never calls `executeAfterPost` | |
| 9 | AfterPostUrl: server POSTs all fields, `_old_*`, `_changelog*`, `recid`, `recdescr`, `table`, `copy_id`, `nextpage` to the URL, then redirects | `detailsRep_post.asp:100-107, 865-893` | **partial / effectively missing** | `form-controller.js:12317-12340` does a GET with only `[ID]`; `this.afterPostUrl` is only assigned in `initInlineEdit` (`:5861-5863`, table mode); not in `buildFormConfig` (`FormTemplate.php:262-305`) | In normal tree/detail mode `afterPostUrl` is undefined → not executed. `_old_` fields exist (`FormTemplate.php:1694-1698`) but go nowhere. |
| 10 | E-mail notifications to subscribed users (`CheckNotifyForForm`, `_changelog_email`, `LibMailer`) | `details.asp:436-450`; `detailsRep_post.asp:709-762` | **missing** | `FormTemplate.php:1531` renders empty `_changelog_email`; `FormDataProvider.php:2404-2529` only writes `tblCMAMonitoring` | Users form still lets you *configure* notifications (`RecordService.php:1830-1879`) but nothing sends mail. |
| 11 | CMA monitoring log | `detailsRep_post.asp:766-777` | present (better) | `FormDataProvider.php:2404-2529`, server-side changelog fallback `:1142-1154`, delete changelog `:1717-1729` | |
| 12 | "Laatste gewijzigd" tip (user + date) top-right; `LastModifiedUser/Date` written on save | `details.asp:310-313, 460-462, 1309-1311`; `edit.inc:258-267`; `detailsRep_post.asp:144-151`; `style.css:1083-1113` | **missing for JSON forms** | Markup exists `FormTemplate.php:1512-1518`, `updateMeta` `form-controller.js:8930-8941` needs `meta.lastModifiedUser`; JSON record endpoint `FormDataProvider.php:594-772` returns meta `:760-766` without it; `saveJsonFormRecord` `:783-1176` never writes `LastModified*` (only legacy `RecordService.php:342-350`) | 11 definitions set `storeLastModified` but the block never shows and nothing is stamped. |
| 13 | Required marker: orange `*` in its own column with tooltip "Verplichte invoer" | `details.asp:753-766`; `style.css:318-322, 2400-2407` | **different** | `FormRenderer.php:1101-1185` reads `$required` (`:1107`) but emits nothing; `form.css:23-40` red left border on empty required inputs | No caption-side marker; readonly/filled required fields show nothing. |
| 14 | "Actie" icon per field/subform (vervalt / beheer / readonly) with tooltip | `details.asp:760-765`; `subform.inc:123-126`; `style.css:2367-2398` | **missing** | `FormRenderer.php:1108-1109` reads `$beheer`,`$actie` and drops them | Subform tab gets a `beheer` attr (`FormTemplate.php:2125-2127`) but field rows have no marker. |
| 15 | Keep-with-next (two fields on one row) | `details.asp:700-702, 1174-1184`; `style.css:2344-2365` | **missing** | `FormTemplate.php:1613-1615` hard-coded `false` ("too many edge cases") | CSS `.next_col` still exists (`form.css:847-855`). |
| 16 | Post-caption placement (inline after short fields/checkbox, under caption otherwise); default hints "dd-mm-jjjj"/"uu:mm" | `details.asp:737-751, 793-801, 865-877`; `style.css:1247-1253` (11px, #666699) | partial | `FormRenderer.php:1117-1152`; `form.css:803-830` (9px italic) | New strips `hh:mm` and surrounding parentheses; datepicker has `dd-mm-yyyy` placeholder (`lib-datepicker.js:169`), timepicker `uu:mm` (`lib-timepicker.js:394`). |
| 17 | Group separators: collapse state per form+group stored server-side in cookie → rendered collapsed on first paint | `details.asp:498-514, 713-715, 1204-1207`; `all.js:1579-1617` | **partial (bug)** | `FormRenderer.php:1056-1074` reads `$config['formId']` but template passes `'sourceFormId'` (`FormTemplate.php:1637-1642`) → `form-id="0"`; `cma-groupbox.js:79-94` key `cma_grp_0_<n>` | Collapse state is shared between *all* forms (group 1 of form A = group 1 of form B). New auto-expands groups with empty required fields (`form-controller.js:10242-10270`). |
| 18 | Defaults on add: `datestamp*` → Now(); schema defaults incl. `CURRENT_DATE_STAMP`, `dateadd(day,14)`, `dateadd(month,2)`, `eval()` of others; checkbox `((1))` → True | `details.asp:569-606` | **partial** | `applyDefaultValues` `form-controller.js:9274-9328` only static `data-default`; `StartwaardeMigratie.php:1-30` explicitly drops `Now()`, `Date()+60`, `GenGUID()` | Empty fields are sent and stored as explicit `NULL` (`FormDataProvider.php:917, 1915-1918`), so DB defaults don't apply either → datestamp/deadline fields become NULL. |
| 19 | fk* fields on add pre-filled from any form's filter cookie (`CMAfilter<field>`); opening a record stores its `filterIDName` cookie | `details.asp:226-235, 615-620` | partial | `form-controller.js:2090-2099` (localStorage `cma_filter_field_`), `setFilterFieldValue` `:2850-2897` only own toolbar filter/URL filters | Cross-form "last used opleiding" defaulting for arbitrary fk fields is gone. |
| 20 | Parent field on add-from-subform rendered as read-only label with parent display value | `details.asp:807-817` | different | `hideParentField` `form-controller.js:2742-2776` hides row unless required; `setParentFieldValue` `:2783-2844` sets combo | If parent field is required it stays an editable combo. |
| 21 | `newChangableOnly` → readonly after insert, value passed via hidden | `details.asp:770-781`; `detailsRep_post.asp:161-163` | present | `form-controller.js:9335-9405`; `FormRenderer.php:1211-1214` | |
| 22 | Readonly / Label fields excluded from UPDATE | `detailsRep_post.asp:165, 400` | **missing** | `FormDataProvider.php:833-862, 906-959` (no readonly check; label only) | Readonly inputs are `readonly` not `disabled` (`FormRenderer.php:1232-1236`) so their (display-formatted) value is posted and written back. |
| 23 | Server-side validation: required, numeric, date, e-mail (multiple `;`-separated), time format, URL https prefix, directory uniqueness | `detailsRep_post.asp:415-446, 483, 502-522, 990-1020` | **missing** | `FormDataProvider.php:783-1176` has none (only users-form special case `form_api.php:975-986`) | Client-only validation (`form-controller.js:10149-10232`). |
| 24 | Client validation: alert box listing every problem, focus first invalid field, switch to its tab, blur re-validation clears error, valid/invalid classes | `formval_nl.js:33-142, 147-149, 569-578, 732-746` | partial | `form-controller.js:10149-10232`, `showError` `:12201-12270` | No focus on first invalid, `invalid` class stays until next save/populate; message is one joined sentence. |
| 25 | Validation type by *control type* (EMail/Time) plus name-based (postcode/telefoon/adres) | `details.asp:544-567, 862, 869` | **partial** | `FormTemplate.php:1785-1827` all textbox-like types go through name-based `getValidationType` (`:1937-1965`) | An EMAIL-typed field not named *email* gets no e-mail validation. |
| 26 | Date entry shorthand: `01012026`, `0101`, `1`, `01-01`, 2-digit year; invalid day/month/year messages; `date-minimum/maximum` | `formval_nl.js:326-414` | **partial** | `lib-datepicker.js:863-936` handles `d-m` and `d-m-yy`; digit-only input (no separator) is silently ignored; invalid dates overflow silently (`new Date(...)`) | Old `formval_nl.js` was ported (`library/formval_nl.js:470+`) but the datepicker input lives in shadow DOM so `[data-validation-type]` scan (`form-controller.js:10203`) never sees it. |
| 27 | Time shorthand `9`→`9:00`, `9:`→`9:00`, `9:1`→`9:15`, `9:3`→`9:30`, `9 30`; hour/minute range errors | `formval_nl.js:274-324` | **partial** | `lib-timepicker.js:123-142, 436-465` reverts anything not `h:mm`; controller blur shortcuts `form-controller.js:3838-3885` read the host value *after* the revert | Typed shorthand is discarded without message. |
| 28 | Number fields: maxlength = numeric precision, size = maxlength, digits-only keydown | `details.asp:553-558`; `edit.inc:320-324`; `formval_nl.js:639-646` | **partial (regression)** | `FormRenderer.php:242-248` forces `maxlength="8"`, `size=8`, `width:80px` | Any numeric value longer than 8 chars (e.g. `123456.78`, `-1234567`, bigints) cannot be typed. |
| 29 | Currency: shown with 2 decimals (`formatnumber`), maxlength 10 | `details.asp:637-646` | partial | `form-controller.js:8640-8652` (`.`→`,`, strips `.00`) | `5.5` shows `5,5` not `5,50`. |
| 30 | Textbox > 128 chars becomes textarea | `edit.inc:311-318` | present | `FormRenderer.php:223-235` | |
| 31 | Password: admin-only "Toon wachtwoord" eye; value present in input | `edit.inc:325-327`; `all.js:1342-1351` | different | `FormRenderer.php:292-298` opt-in `showPasswordToggle`, 3 s auto-hide `form-controller.js:4013`; value never sent (`FormDataProvider.php:746-749`) | Better security; toggle is off by default. |
| 32 | Memo: CKEditor toolbars Full/Basic + SwitchBar, custom styles, quicktable, soft-hyphen Alt+-, `customCSS` from config | `all.js:9-158`; `details.asp:177-193` | present | `cma.js:65-260` | `contentsCss` hard-coded `/assets/css/cma.css` (`cma.js:88`) instead of `editor_css` setting (setting is passed in `FormTemplate.php:297` but unused). |
| 33 | Memo: editor height estimated from content length; plain textareas `autoGrow` | `details.asp:912-919, 1244` | **missing** | `FormRenderer.php:646-665` fixed `rows*18` (min 5 rows) | |
| 34 | Memo `maxChars`: live counter + `maxlength` | `details.asp:931-934`; `all.js:1244-1280` | **missing** | `FormRenderer.php:634` emits `data-max-chars`; no JS/CSS reads it (grep empty) | |
| 35 | CKEditor blur: strip DOM/attributes, encode entities, `<br>`-only → empty | `details.asp:1245-1266` | **missing** | no counterpart in `cma.js`/`form-controller.js` | Server-side scayt cleanup `detailsRep_post.asp:449-464` also gone. |
| 36 | Blockedit content blocks (incl. `scorm` block type) | `include/blockedit.js:670-675` | partial | `assets/js/blockedit.js:708` ("Onbekend type veld") — no `scorm` case | RINO-specific block type missing. |
| 37 | Combo: static select2 (`minimumResultsForSearch 20`, `allowClear` unless required) / AJAX when >50 rows (`minimumInputLength 2`); `Group|Item` → OPTGROUP; `<br>`→", " | `edit.inc:10-45, 95-253` | partial | `FormRenderer.php:375-483`; `FormDataProvider.php:1257-1550` (large table → min 3 chars `:1449-1457`); `lib-combo.js:614,643` supports groups only via `<optgroup>` markup | No `\|` grouping, no `<br>` replacement; `sqlList` with `[ID]` returns `requires_context` (`:1370-1380`) and old `=[ProdID]`→`is null` for new records is gone. |
| 38 | Combo "add related record" (tb_new) via `updatevalues`, rights + MenuNew checked | `details.asp:830-847`; `detailsRep_post.asp:795-840` | present (better) | `FormRenderer.php:412-427, 463-479`; `form-controller.js:4403-4520` | New auto-selects the new record. Rights check is at template-generation time and template is cached per access level (`FormTemplate.php:106-149`), `currentFormId` never passed (`:1842-1873`). |
| 39 | UserList hidden for non-admins, defaults to current user | `details.asp:525, 690-697` | **missing** | `FormTemplate.php:1843` treats as normal combo | |
| 40 | Checklist: SQL sanity checks (`[ProdID]`, `DisplayName`, `Selected`), width setting | `details.asp:937-971`; `edit.inc:272-301` | partial | `FormRenderer.php:688-720` (`data-width` written `:704`, never read); `form.php:138-151` validates definition | |
| 41 | Sortlist: toolbar (up/down, sort A→Z / Z→A), Ctrl+arrow keys, hint text | `details.asp:1340-1410`; `all.js:1619-1715` | partial | `cma-sortlist.js:322-325` (drag + per-item up/down only) | No alphabetical sort, no keyboard shortcuts. |
| 42 | Image: crop/upload wizard with fixed/max size, info icon "Maximaal: 300px breed - 200px hoog", 40 px ratio-correct preview, zoom view | `details.asp:973-1048, 1150-1172`; `all.js:1407-1548` | different | `FormRenderer.php:725-812` (select / edit / clear; `image-editor.js:609-621` shows size hint inside editor); `form.css:3115-3118` preview; 404 handling `form-controller.js:4317` | No inline info icon on the form. |
| 43 | Image width/height saved to `imgWidthField/imgHeightField`; thumbnail (`_tn`) generation; HTMLStrip plain-text copy | `detailsRep_post.asp:187-205, 207-312, 524-542` | **missing** | `FormDataProvider.php:783-1176` (no `_width/_height`, thumbnail, htmlstrip handling); `form_api.php:1004-1014` generates WebP variants instead | |
| 44 | File field: view / select / clear | `details.asp:1063-1088` | present | `FormRenderer.php:886-953` | |
| 45 | URL: `fShowSite` adds `https://` for `www…`; server prefixes https, trims | `all.js:1552-1561`; `detailsRep_post.asp:440-446` | partial | `FormRenderer.php:958-993`, `form-controller.js:3815-3826` | No auto-prefix. |
| 46 | Directory field: one per form, uppercase + illegal-char strip, uniqueness check, `_old` for rename | `details.asp:854-859, 880-884`; `detailsRep_post.asp:411-413, 502-522` | **missing** | `FormRenderer.php:95-97` plain textbox | |
| 47 | Label field: boolean → disabled switch; datetime → "dd-mm-yyyy om h:mm" | `details.asp:1090-1118, 656-660` | different | `FormRenderer.php:998-1017`; `form-controller.js:8609-8637` (Ja/Nee text) | |
| 48 | `http://` → `https://` rewrite and unicode fix on save | `detailsRep_post.asp:405-406, 669` | **missing** | — | |
| 49 | Large-field (>1024) handling + Access transaction (begin/commit/rollback) | `detailsRep_post.asp:487-491, 595-597, 662-675, 691-707` | **missing** | `FormDataProvider.php:983, 1091-1112` (checklist failure only logged after main row is written) | |
| 50 | `__fully_loaded` post guard | `detailsRep_post.asp:65-68` | **missing** | sent by `form-controller.js:9748` but never checked (grep empty in `form_api.php`/provider) | |
| 51 | Preview ("Bekijk") URL with `[ID]`, `[code]`, `[guid]` | `details.asp:342-360` | present (generic) | `form-controller.js:10527-10550` | |
| 52 | Extra buttons: `[ID]`, `[GUID]`, `[GUID2]`=`secret` column, `[domein]`, `[omgeving]`; disabled while adding/copying | `toolbar.inc:98-119, 264-347`; `details.asp:362-381` | partial | `FormTemplate.php:1352-1426`; `form-controller.js:2126-2134` (`guid2` column, not `secret`), `:10557-10616, 10645-10715` (no `[omgeving]`; `ToolbarHelper.php:161-170` has it but is not used here) | |
| 53 | Cancel = reload page / close popup | `toolbar.inc:172-176` | present (better) | `form-controller.js:9617-9657` (confirm with change summary) | |
| 54 | Copy: `Copy=Y` + `clearflds=a,b` to blank chosen fields; changelog copy id | `details.asp:13, 85-86, 677-686, 440-443` | partial | `form-controller.js:8138-8235, 10502-10522` | No `clearflds`; URL-copy path never calls `loadChecklists` → checklist selections not copied (old kept them: `details.asp:960-965`). |
| 55 | Print mode (`?Print=Y`) | `details.asp:12, 240-241, 898-906` | **missing** | grep empty | |
| 56 | `onloadJS` run on every page load (add + edit) | `details.asp:206` | partial | `form-controller.js:2144, 2152-2166` only after loading an existing record; `newRecord` (`:8946`) does not call it | |
| 57 | Subform tabs: record-count badge, "Voeg toe" per tab, popup sized by nesting depth, `bFullWidth`, group field | `subform.inc:122-143, 166`; `all.js:1389-1405`; `style.css:323-330` | present / partial | `FormTemplate.php:2073-2138`; `form-controller.js:10760, 11402-11481`; `cma-utils.js:584-591` | `fullWidth` collected (`FormTemplate.php:2061`) but not used for popup size. |
| 58 | Subform iframe fold (larger/smaller) | `details.asp:1215-1218`; `style.css:1011-1078` | present | `FormTemplate.php:1569` (`cma-fold`) | |
| 59 | Beheer-only fields hidden for non-beheer | `details.asp:488` | present | `FormTemplate.php:1617-1620` | |
| 60 | Save/delete rights = form rights ≥ Full | `details.asp:272-283`; `detailsRep_post.asp` (implicit) | **different** | `FormDataProvider.php:786, 1696` require `SecurityHelper::isAdmin()` (`SecurityHelper.php:133-139`, level ≥ `LEVEL_ADMIN`) | Non-admin users with group "Full" rights get "Geen toegang" on save/delete (legacy `RecordService::canWrite` is not used on the JSON path). |
| 61 | Combo `Q_HEIGHT` → `size` (listbox) | `edit.inc:187` | missing | `FormRenderer.php:380` unused | Minor. |
| 62 | Form-level tips / readonly indicator / focus first field / expand required groups | — | new | `FormTemplate.php:1492-1509`; `form-controller.js:9095-9137, 9038-9069, 10242-10270` | |

#### 2. Nuances likely lost (concrete)

1. **Status of the record is invisible.** `updateStatus()` targets `#toolbar-status` which the form template never renders (`form-controller.js:11877-11881`, `FormTemplate.php` has no such id). Users no longer see "Toevoegen / Wijzigen / Gekopieerde gegevens toevoegen / (beheer)" (`details.asp:383-409`) except as a sidepanel title.
2. **Audit "who/when" is gone for JSON forms.** `saveJsonFormRecord` never sets `LastModifiedUser/Date` and `getJsonFormRecordData` never returns them (`FormDataProvider.php:783-1176`, `:760-766`); the `#lastModified` block (`FormTemplate.php:1512-1518`) stays `display:none`. Old: `detailsRep_post.asp:144-151`, `details.asp:460-462`.
3. **Notification e-mails no longer sent** (`detailsRep_post.asp:709-762`) — only `tblCMAMonitoring` (`FormDataProvider.php:2404-2529`).
4. **AfterPostUrl practically dead**: only set in table/inline mode (`form-controller.js:5861-5863`), executed as a bare GET (`:12317-12340`), never on delete; old posted the whole form incl. `_old_` values (`detailsRep_post.asp:865-893`).
5. **Soft delete (`DeletedField`) replaced by hard DELETE** (`FormDataProvider.php:1755` vs `detailsRep_post.asp:117-122`).
6. **Expression defaults lost and DB defaults defeated**: `Now()`/`dateadd` defaults were pre-filled (`details.asp:571-604`); new sends `''` → explicit `NULL` (`FormDataProvider.php:917, 1915-1918`), so neither the form nor the column default fills e.g. `datestamp`.
7. **Group collapse state collides across forms**: `form-id="0"` for every form because the template passes `sourceFormId` while the renderer reads `formId` (`FormTemplate.php:1637-1642`, `FormRenderer.php:1061,1067`, `cma-groupbox.js:83`).
8. **Numeric input capped at 8 characters** (`FormRenderer.php:242-248`) vs precision-based maxlength (`details.asp:553-558`, `edit.inc:324`).
9. **Time/date shorthand entry** (`formval_nl.js:274-414`) silently discarded by `lib-timepicker.js:436-465` / not handled by `lib-datepicker.js:863-936`; no "ongeldige dag/maand/uur" feedback.
10. **Readonly fields are written back** (no `blnReadOnly` skip as in `detailsRep_post.asp:165,400`); combined with display formatting (`form-controller.js:8640-8652`) this can alter stored values.
11. **Required rich-text memo likely fails validation on new records**: `validateForm()` (`form-controller.js:9435`) reads `textarea.value` before `CKEDITOR…updateElement()` in `collectFormData` (`:9684`); `updateCKEditorRequiredState` (`cma.js:272-296`) only toggles a CSS class.
12. **Save/delete require admin level** on JSON forms (`FormDataProvider.php:786,1696`) instead of per-form Full rights (`details.asp:272-283`).
13. **Keep-with-next layouts flattened** (`FormTemplate.php:1613-1615`) — forms designed with paired fields (e.g. date + time, number + unit) now take one row each.
14. **Popup Save closes immediately** (`form-controller.js:4585`) — the old "Bewaar" (keep open, reload with subforms) workflow for adding parent-then-children is gone.
15. **`[GUID2]` now reads `guid2` instead of `secret`** and `[omgeving]` is not substituted (`form-controller.js:2132-2133, 10570-10590`; old `details.asp:368-373`, `toolbar.inc:100-111`).
16. **Cached template bakes request state**: `body.popup` class and `__ParentField/__ParentValue` hidden inputs are generated from `Request::query` inside a template cached per form+access level (`FormTemplate.php:106-149, 502-533, 1539-1544`); the first request's parent id lives on in every later render (harmless for saving because `_`-prefixed fields are skipped at `form_api.php:901`, but `body.popup` styling can be wrong).
17. **Combo grouping via `Group|Item` → OPTGROUP** and `<br>`→", " cleanup (`edit.inc:206-226`) not reproduced (`FormDataProvider.php:1533-1540`).
18. **Copy via URL loses checklist selections** (`form-controller.js:8138-8235` never calls `loadChecklists`; old `details.asp:960-965` kept them) and `clearflds` is gone.
19. **UserList no longer admin-gated/defaulted to current user** (`details.asp:525, 690-697`).
20. **Server-side data checks gone** (required/numeric/date/email-list/time/URL/directory — `detailsRep_post.asp:415-446, 483, 502-522, 990-1020`); the `__fully_loaded` guard (`:65-68`) and Access transaction (`:595-597, 691-707`) as well.
21. **onLoadJS not run for new records** (`form-controller.js:2144` only; old `details.asp:206` always).
22. **Thumbnail / HTMLStrip / image width-height columns not maintained** on save (`detailsRep_post.asp:187-312, 524-542`).
23. **Memo `maxChars` counter + limit** (`details.asp:931-934`, `all.js:1244-1280`) inert (`FormRenderer.php:634`, no consumer).

#### 3. Small display optimisations the old had that the new lacks (or could adopt)

1. Required `*` next to the caption with tooltip "Verplichte invoer" (`details.asp:756-758`, `style.css:318-322`) — new only colours the input border (`form.css:23-40`), invisible once filled/readonly.
2. Field "actie" glyph (vervalt/beheer/readonly Linearicons with hover title) in the marker column (`details.asp:760-765`, `style.css:2367-2398`).
3. Toolbar status text incl. "(beheer)" (`details.asp:383-409`, `style.css:576-582`).
4. "Laatste gewijzigd" tip card top-right (`edit.inc:258-267`, `style.css:1083-1113`) — new markup exists (`form.css:751-780`) but is never filled.
5. Post-caption hints `dd-mm-jjjj` / `uu:mm` when a date/time field has no caption (`details.asp:793-801`) and 11 px `#666699` styling (`style.css:1247-1253`) vs 9 px italic (`form.css:803-812`).
6. Image resize spec info icon ("Maximaal: 300px breed - 200px hoog") next to the image control (`details.asp:1150-1172`); new only inside the editor (`image-editor.js:609-621`).
7. Sortlist toolbar with A→Z / Z→A and "Ctrl+Pijltjes verplaatst" hint (`details.asp:1369-1381`).
8. Editor height sized to content (`details.asp:912-917`) and auto-growing textareas (`details.asp:1244`).
9. Side-by-side compact fields (`combining`/`next_col`, `style.css:2344-2365`).
10. Live character counter on limited memos (`all.js:1274`).
11. Pressed-button feedback (`tb_but_down`, `style.css:43-49`, `all.js:700`) on Save/New/Copy — new only shows a spinner (`form-controller.js:11786`).
12. Collapsed groups rendered collapsed server-side (no expand→collapse flash) (`details.asp:508-515`).
13. Password eye for admins by default (`edit.inc:325-327`).
14. Focus + tab switch to the first invalid field after validation (`formval_nl.js:94-112`).
15. Parent record shown as read-only label when adding from a subform (`details.asp:807-817`) instead of a hidden row.

#### 4. Things the new does better (brief)

- In-place AJAX save without page reload, list row refresh, URL sync (`form-controller.js:9504-9576`).
- "Add related" auto-selects the new record and works for AJAX combos (`:4446-4520`).
- Cancel/New/leave confirmations with a change summary (`:8948-8960, 9621-9633, 12047`).
- Server-side changelog fallback and full delete audit (`FormDataProvider.php:1142-1154, 1717-1729`), monitoring log levels.
- Passwords never sent to the client (`:746-749`); form-definition validation before render (`form.php:138-151`); missing-column diagnostics on save (`FormDataProvider.php:985-1037`).
- Datetime split into date + time controls (`FormRenderer.php:151-181`, `form_api.php:923-960`), image 404 handling, WebP variants, in-place image editor, video field.
- Focus first editable field, auto-expand groups with empty required fields, readonly indicator, sidepanel/popup preference, filter context passed to popups, prefetching, Delete-key shortcut.

---

## Appendix C — Navigation, security, tools, reports (detail)


Scope: navigation/menu, login/logout, security (users/groups/rights), tools, reports, templates, wizards/image tools, URL/module maintenance, small display details. Old paths are under `/mnt/c/repos/adam/mijnrino/CMA/`, new under `/mnt/c/repos/cma_platform/cma/`.

---

### 1. Feature-by-feature table

| # | Old feature | Where in old | Status in new | Where in new | Note |
|---|---|---|---|---|---|
| **Navigation / shell** |||||
| 1 | 3-row frameset (menu U / content C / copyright B), height derived from logo height (`max(logo+2, 62)`) | `default.asp:129-135, 415-419` | different | `main.php:446-561`, `--header-height` from logo: `main.php:402-408` | Sidebar shell replaces frames; logo height still drives header height. |
| 2 | Horizontal tabs (`glow_tabs`) + submenu row; clicking a tab auto-clicks its first sub-item | `menurep.asp:147`, `include/all.js:615-627` (`changeNavMenu` → `firstItem.click()`) | different | `assets/js/main.js:390-420` (`toggleMenuGroup` only expands) | New group header only expands/collapses; no auto-navigation to first item. |
| 3 | "Start" tab: Startpagina / Gebruikers / Groepen / Module instellingen / Afmelden (admin-gated) | `menurep.asp:69-83` | different | Dashboard `main.php:211-223`; users/groups in tools launcher `tools_catalog.inc:60-70`; logout in user dropdown `main.php:549-551` | Users/groups moved 2 clicks deeper (Tools → launcher). |
| 4 | Menu grouped by `tblMenu`, item name falls back to form name, rights via `CheckRights(constSecType_Menu)` | `menurep.asp:90-130` | present | `main.php:225-300`, `menurep.inc:91-139` | Menu now from JSON (`MenuService`). Whole group hidden when no accessible items – same. |
| 5 | Backwards-compat `listreports/listtools/listtemplates.asp` → contentframe wrapper | `menurep.asp:117-119` | partial | `main.php:275` (`.asp`→`.php` only) | `listTemplates.php` not in tools catalog nor `config/menu.json`; reachable only if a site menu links it. |
| 6 | `form(ID)` menu click carries current list's `SearchField/SearchFor/SearchType/NoAutoFilter/ID` into the target form | `include/all.js:574-603` | missing | `main.js:600-650` (`loadPage(page)` with plain `data-page`) | Cross-form search carry-over lost. |
| 7 | Tab overflow: scroll arrows + keyboard ←/→ | `include/all.js:447-569` | n/a | sidebar scrolls | Not needed with sidebar. |
| 8 | Test environment: entire menu bar red (`body.m_body.test`) + title prefix `TEST:`/`ACC:` | `menurep.asp:138`, `style.css:276-278`, `default.asp:30` | present (different) | `main.php:192-202, 356, 523-525` (`lib-label` "Test"/"Acceptatie") | Less loud than a red bar; also `L`/`O` map to "Test". |
| 9 | Logo right-aligned, `title="Ga naar de site"`, opens site in new tab, vertical centering math | `menurep.asp:139-145` | partial | `main.php:449-462` | Logo in sidebar header; no `title` tooltip; `onerror` fallback added (better). |
| 10 | Bottom frame "versie 5.11" | `copyright.asp:147`, `include/header.inc:2` | present | `main.php:533` (`v<CMA_APP_VERSION>` in user dropdown) | |
| 11 | Deep link `contentframe.asp?FormID=&ID=` / `default.asp?FormID=` | `contentframe.asp:28-35`, `login.asp:127-130` | present | `default.php:39-45` | |
| 12 | Startpagina = menu grid (4 per row, `kader startpage`, per-menu icon `kader_icon <menuname>`, hover border orange) + welcome box + inline password change | `login.asp:154-222, 299-344`, `style.css:591-668` | present (different) | `dashboard.php:88-140, 1394-1404` (`menu-card`), `menurep.inc:147-198` (`menuGroupIcon`) | Menu grid now at bottom below stats; icons via lnr map (old used image per menu name). Password change now a modal `main.js:322`. |
| **Login / logout** |||||
| 13 | Login form, remembered last login name, focus goes to **password** when name is remembered | `login.asp:271, 291` | different | `login.php:366, 388-391` (always focus + select login field) | Deliberate change, but one extra keystroke for returning users. |
| 14 | `COOKIE_LAST_LOGIN` kept 365 days, survives logout | `login.asp:94`, `logout.asp:8-10` (does not clear it) | missing | `logout.php:22` deletes `COOKIE_LAST_LOGIN`; `login.php:184` sets it with `auth_cookie_lifetime` | "Remember my login name" is lost on every logout. |
| 15 | IP protection: user IPs (`;`), group IPs, `admin` bypass, local bypass | `login.asp:40-82` | present (better) | `login.php:117-154`, `SecurityHelper::ipMatchesAnyPattern` (CIDR/wildcards) | |
| 16 | App-level IP allowlist `cma_ip_protect + cma_ip_adresses` → 401 before frameset | `default.asp:34-41` | missing | no reference (`grep cma_ip_adresses` → none) | Only per-user/group IPs remain. |
| 17 | Forgotten login → mails login+password in plain text | `login.asp:237-267` | present (same weakness) | `login.php:329-356` | Both mail plaintext passwords. |
| 18 | Auto-create Admin user / group "Iedereen" (ID 0) on login | `login.asp:106-115` | present | `login.php:208-222` | |
| 19 | `chk_login.inc`: redirect to `default.asp?forcelogin=J&<qs>` and resume after login | `include/chk_login.inc:372-377`, `login.asp:127` | present | `bootstrap.inc:1600-1674`, `login.php:231-234` | |
| 20 | Password change: old pwd required, case-insensitive compare, messages incl. "Sessie verlopen" | `login.asp:301-323` | present | `api/change-password.php:40`, `password.php`, modal | |
| **Security: users** |||||
| 21 | User fields: login, full name, password (plain text box), email (required), IP list (`;`), Administrator checkbox with note "er moet altijd één administrator zijn" | `sec_user_maint.asp:199-234` | present (different) | `assets/forms/definitions/users.json` | Password is `type: password` (better); email not required; `userLevel` radiogroup (User/Admin/Developer). |
| 22 | IP hint "(;-gescheiden)" + server validation `lib_FormValidIPAddresses` | `sec_user_maint.asp:219`, `sec_user_maint_post.asp:31-35` | different / weaker | `users.json` hint "Komma-gescheiden (geen beperking)"; login splits on `;` `login.php:127` | Hint contradicts runtime separator; no server validation. |
| 23 | Groups list hidden for administrators ("Administrator heeft alle rechten") | `sec_user_maint.asp:185-196, 258-267` | missing | `users.json` `user_groups` always shown | |
| 24 | Every user implicitly member of group 0 (Iedereen) on save | `sec_user_maint_post.asp:85` | missing | `RecordService.php:1573-1576` filters `> 0` | See §2. |
| 25 | Notifications checklist grouped per menu, label = menu item name or form name, **subforms listed indented** | `sec_user_maint.asp:283-311` | partial | `JsonFormRenderer.php:52-116` | Label uses JSON form key (`$item['form']`, e.g. "rooster"), subforms not listed. |
| 26 | "Geen notificatie voor eigen wijzigingen" + "opnieuw inloggen noodzakelijk" note when editing self | `sec_user_maint.asp:318-329` | present (note not needed) | `users.json` `userSkipNotifyOwnRecords` | New reads from DB, no cookie → note obsolete. |
| 27 | Data meldingen: per XMLStore with `date()` in query, "N dagen van tevoren" input | `sec_user_maint.asp:330-375`, `sec_user_maint_post.asp:120-137` | different / likely broken | render: `JsonFormRenderer.php:121-170` (reads `tblDataNotificationSources`/`...Subscriptions`), save: `RecordService.php:1879-1921` (expects `storeId/days/...` arrays, writes `tblUserDataNotifications`) | Renderer posts scalar IDs; saver expects arrays → nothing persisted; days input gone. |
| 28 | Tip box explaining group inheritance ("hoogste rechten") | `sec_user_maint.asp:180` | different | tours `cma-tours.js:674` | |
| 29 | Delete user: any | `sec_user_maint_post.asp:21-25` | better | `form_api.php:1685-1735` (last admin/dev, self-delete guards) | |
| 30 | Cache `CMA_access` cleared on user/group save | `sec_user_maint_post.asp:46-47`, `sec_group_maint_post.asp:41-42` | present | `RecordService.php:387-390` (group invalidation) | Assumed via `Cache::invalidateGroup`. |
| **Security: groups** |||||
| 31 | Group "Beheerders?" (`isBeheer`) checkbox → members get `ACCESS_FULL_BEHEER` on forms when they have FULL | `sec_group_maint.asp:226-238`, `include/security.inc:115-141` | missing | `groups.json` (no field); `SecurityHelper.php:481` (query has no `isBeheer`), FULL_BEHEER only for admins `:595,700` | See §2. |
| 32 | Group list shows " (beheer)" suffix | `sec_list_groups.asp:210` | missing | `groups.json` listQuery | |
| 33 | Rights matrix columns Geen / Alleen lezen / Volledig / **Alleen eigen records** (when form `blnSecurityByUser`) | `sec_group_maint.asp:266-305` | partial | `JsonFormRenderer.php:184-189` default columns 0/10/30 only | Level 20 still honoured at runtime (`TreeService.php:68`, `FormTemplate.php:386`) but cannot be assigned in UI. |
| 34 | Extra-button checkboxes per form (5) labelled with `extraIconTitle` | `sec_group_maint.asp:306-310` | present (better) | `JsonFormRenderer.php:293-318, 496-524` (hides unused columns) | |
| 35 | Subform rows indented, disabled when parent = Geen (`disable_subs`) | `sec_group_maint.asp:20-42, 297-302` | present (better) | `form-controller.js:8743-8924` (data-parent + bulk header radios) | |
| 36 | Group "Iedereen" (ID 0) cannot be deleted but its rights are editable | `sec_group_maint.asp:180` | different | no delete guard; rights not loaded/saved for id 0 (`JsonFormRenderer.php:196`, `RecordService.php:1693`, `SecurityHelper.php:1040`) | See §2. |
| 37 | Report rights checklist | `sec_group_maint.asp:326-362` | present | `JsonFormRenderer.php:567-610` | |
| 38 | Group IP list with client `data-validation-type="ip-address"` + server validation | `sec_group_maint.asp:241`, `sec_group_maint_post.asp:32-36` | weaker | `groups.json` plain textbox | No validation. |
| 39 | Row labels keep original casing | `sec_group_maint.asp:284-288` | different | `JsonFormRenderer.php:451` `ucfirst(strtolower($label))` | "CGO document" → "Cgo document". |
| **Module settings** |||||
| 40 | Module parameters editor (`tblModuleParameters`: HTML via CKEditor, number, url, date, text; PostCaption; required) gated by `CMA_show_module_settings` | `mod_list.asp`, `mod_maint.asp:169-260`, `mod_maint_post.asp` | missing | only `$lang_module_settings` string `bootstrap.inc:1523` | No counterpart at all. |
| **Marketing URLs** |||||
| 41 | Tree list, search, auto-click single result | `url_list.asp:8-16` | present | `marketingurl.json` (`displayMode 2`, `quickSearchFields`) | |
| 42 | "Bekijk" preview button (`../<dir>`), print button, hint `https://<host>/` under field, tip text | `url_maint.asp:146-151, 170-174, 180` | missing | `marketingurl.json` `previewUrl: ""` | |
| 43 | Save: if `dir` exists → update instead of insert; DateStamp auto `Date()`; clears dir-index cache | `url_maint_post.asp:244-262, 276` | different | `migrations/9.14.0…:56` unique index; `DATESTAMP` editable date field | Duplicate dir now errors; no auto date; table renamed `tblCMAMarketingUrl`. |
| **Tools** |||||
| 44 | Tools tree (Standaard / Database onderhoud / Beheer hulpmiddelen) | `listTools.asp:39-61` | present (better) | `tools_catalog.inc`, `tools.php` launcher | Many new tools (backup, migrations, logs, settings, …). |
| 45 | "Veld toevoegen" (`tools_fieldadd.asp`), "Database voorbereiden op migratie" | `listTools.asp:52-53` | missing | `grep fieldadd` → none | Arguably obsolete. |
| 46 | SQL tool: DB select incl. "CMA definitie" (999), templates dropdown, history (sessionStorage), `;;` split, timing | `tools_query.asp:79-135, 162-246` | present (better) | `tools/tools_query.php:34-82, 340-400, 519-616` | Repository option "deprecated, routes to data" (`:540`); adds table/field pickers, UPDATE-without-WHERE guard. |
| 47 | Clear cache: also bumps `Application("asset_version")` to bust browser caches | `tools_clearcache.asp:122-125` | partial | `tools/tools_clearcache.php`; `bootstrap.inc:788-790` hard-coded `cma_asset_version()` | No asset-version bump on clear. |
| 48 | Server info: Application contents + server variables | `tools_serverinfo.asp:150-175` | present (better) | `tools/tools_serverinfo.php:196-301` (tabs, secret masking) | |
| 49 | DB consistency: images sizes / unused images with delete / external files / **HTML-stripped fields refresh** / XMLStores / users | `tools_db_consistency.asp:61-410, 258-300` | partial | `tools/tools_db_consistency.php:148-400` | "HTML stripped fields" section absent (`grep -i strip` → none). |
| 50 | Report toolbar: **Print** button, date/time (long Dutch date) at right | `include/toolbar.inc:244-284` | missing | `ToolbarHelper.php:438-488` (`printButton` defined `:378` but never called; `showTimestamp=false`, format `Y-m-d H:i:s`) | |
| **Reports** |||||
| 51 | Report tree per module, search, rights `CheckRights(Report)` | `listReports.asp:61-101` | present | `reports.php`, `api/reports-catalog.php:40` | Launcher/tiles instead of tree. |
| 52 | Report detail: grouping, flip groups, filter page, Excel/Word, subreports, edit link | `reportdetails.asp` | present (+CSV) | `reportdetails.php` | Faithful port; CSV added. |
| 53 | Loader spinner shown after 500 ms while report renders | `reportdetails.asp:87-88` | missing | `grep -i loading reportdetails.php` → none | |
| 54 | Edit link contains `<span class=print>` with full URL for printouts | `reportdetails.asp:393` | missing | `reportdetails.php:716` | |
| 55 | No rights check when opening `reportdetails` directly | `reportdetails.asp` (none) | same | `reportdetails.php` (none) | Same weakness both sides. |
| **Templates (wijzigbare pagina's)** |||||
| 56 | Tree, refresh (`template_fillrep`), edit tags, preview button | `listTemplates.asp`, `template_edit.asp`, `template_post.asp` | present | `listTemplates.php`, `template_edit.php:182`, `template_post.php` | |
| 57 | Fillrep scans `.asp/.htm/.html` | `template_fillrep.asp:410` | same (now wrong) | `template_fillrep.php:52` | On a PHP site the converted `.php` pages are never indexed. |
| **Wizards / image tools** |||||
| 58 | `wizard.asp` shell + `file-pages/link-pages/table-pages` (modal dialog, Enter/Esc, history) | `wizard.asp`, `wizards/*.asp`, `wizards/wizard.js` | dead code | `wizard.php`, `wizards/link-pages.php` etc. exist but unreferenced (`grep link-pages` only in wizards/) | Replaced by `html_edit_link.php`, `imageupload_crop.php`, `wizards/file-browser.php`. |
| 59 | Link wizard: mailto+subject, auto `target=_top` for own site, upload-and-link file, upload-and-link image → `lib_window_ImageZoom`, auto-zoom for `.jpg`, default link text from filename, bookmark-restored cursor | `wizards/link-pages.asp:484-573` | partial | `html_edit_link.php` (URL/title/target only); `cma.js:369-412` (bookmark restore present) | No mailto builder, no upload-from-link-dialog, no ImageZoom link. New `link-pages.php` still has stale logic (`http://`, `./` instead of `/`, no query-safe ext) `wizards/link-pages.php:100-114`. |
| 60 | File list: list/thumbnail toggle (cookie `CMA_Listview`), size in Kb/Mb, "too large" placeholder >250 Kb, created/modified dates, dimensions, "schaalbaar" for SVG, delete, create folder, replace-selected-file checkbox | `wizards/file_list_ajaxdata.asp`, `file_controls.asp:317-350`, `file_upload.asp:106-166` | present (better) | `wizards/file-browser.php:327-337, 2041-2082, 1818, 372-437` | Adds crop/rotate/autocrop/webp. |
| 61 | Image layout page (align/border/margin/alt, live sample) | `wizards/file-pages.asp:228-305` | present | `cma.js:414-430` `applyImageProps` | |
| **Misc** |||||
| 62 | Daily task: mail only when store has 0 records at day N and >0 at day N-1; BCC to Stenvers | `task.asp:108-125` | different (regression) | `task.php:33-68` | Condition removed → mail sent every run for every subscription; store name replaced by `notBeschrijving`. |
| 63 | UK/NL labels for users/groups/URL screens | `include/language.inc`, `sec_user_maint.asp:161-175` | weaker | `bootstrap.inc:1440-1530` (strings exist) but `users.json`/`groups.json`/`main.php` hard-code Dutch | |
| 64 | Tree open-state persisted via cookie (`cookie.asp`) | `include/ftiens4.js:301-330` | present | `webcomponents/cma-tree.js:293-307` (localStorage) | |
| 65 | `showimage.asp` popup | `showimage.asp` | present | `library/library.js:1584` `lib_window_ImageZoom` | |

---

### 2. Nuances likely lost (concrete)

1. **Group flag `isBeheer` and `ACCESS_FULL_BEHEER` for non-admins.** Old: `include/security.inc:115-141` selects `tblGroups.isBeheer`; a non-admin with FULL on a form via a "beheer" group gets `constSecAccess_Full_Beheer` (unlocks `isBeheer` fields/subforms). New `SecurityHelper::checkRightsForUser` (`classes/SecurityHelper.php:481`) does not read `isBeheer`; `ACCESS_FULL_BEHEER` is only granted when `isAdmin()` (`:595`, `:700`). `groups.json` has no `isBeheer` field. `FormTemplate.php:1610-1618, 2083-2125` still hide beheer fields/subforms below FULL_BEHEER, so non-admin "beheerders" lose those controls.

2. **"Alleen eigen records" (level 20) cannot be assigned.** Old shows a 4th radio when `blnSecurityByUser` (`sec_group_maint.asp:303-305`). New matrix default columns are 0/10/30 (`JsonFormRenderer.php:184-189`); the `conditional` mechanism (`:480`) is never configured in `groups.json`. Runtime still distinguishes level 20 (`TreeService.php:68`).

3. **Group 0 "Iedereen" is unusable.** Old: rights editable for ID 0 and every user automatically a member (`sec_user_maint_post.asp:85`). New: `saveUserGroups` drops IDs `<= 0` (`RecordService.php:1573-1576`), `saveGroupRights` returns for falsy ID (`:1693`), renderer skips loading rights for `$recordId == 0` (`JsonFormRenderer.php:196`), `checkGroupRights` returns NONE for `<= 0` (`SecurityHelper.php:1040`). Also no "cannot delete group 0" guard (old `sec_group_maint.asp:180`).

4. **Data-notification editing is broken/mismatched.** Renderer posts `data_notifications[]=<sourceId>` from tables `tblDataNotificationSources/Subscriptions` (`JsonFormRenderer.php:130-153`); saver expects `$notif['storeId']` arrays and writes `tblUserDataNotifications` (`RecordService.php:1899-1912`) → `empty()` on a string offset silently skips everything. The old "N dagen van tevoren" number is gone.

5. **Daily task sends unconditionally.** Old `task.asp:108-109` only mailed when the store was about to become empty; `task.php:44-68` mails every subscription on every run (and drops the BCC).

6. **Remembered login name lost at logout** (`logout.php:22` deletes `COOKIE_LAST_LOGIN`; old `logout.asp` kept it with 365-day expiry `login.asp:94`).

7. **Menu group click no longer opens the first item** (`main.js:390-420` vs `all.js:623-627`); and **search criteria are no longer carried across forms via the menu** (`all.js:574-603`).

8. **Module settings editor removed** (`mod_maint.asp`, incl. CKEditor for HTML params) — no PHP counterpart.

9. **Report toolbar lost Print button and the localized date/time stamp** (`toolbar.inc:250, 278-281` vs `ToolbarHelper.php:438-488`; `printButton` unused).

10. **Consistency tool lost the "HTML stripped fields" resync section** (`tools_db_consistency.asp:258-300`).

11. **Link wizard capabilities**: mailto+subject builder, "upload & link a PDF", "upload & link an image (ImageZoom)", auto `target=_top` for own-site links, auto-zoom for `.jpg` (`link-pages.asp:491-543`) → new dialog only URL/title/target (`html_edit_link.php`). The recent old fixes (https, `/`-absolute links, query-safe extension check, bookmark range insert) are **not** in `wizards/link-pages.php:100-114` (which is dead anyway).

12. **Users IP hint contradicts runtime**: `users.json` says "Komma-gescheiden", `login.php:127` splits on `;`. Old validated IPs server-side (`lib_FormValidIPAddresses`); new does not.

13. **Notifications list labels**: old showed menu-item/form title and included subforms (`sec_user_maint.asp:283-311`); new shows the raw JSON form key (`JsonFormRenderer.php:97-105`) and no subforms.

14. **App-wide IP allowlist** (`default.asp:34-41`) has no equivalent.

15. **Template indexing** ignores `.php` (`template_fillrep.php:52`), so on a converted site the tool finds nothing new.

16. **Clear cache no longer bumps the asset version** (`tools_clearcache.asp:122-125` vs constant `bootstrap.inc:788-790`).

---

### 3. Small display optimisations the old had (new lacks / could adopt)

1. **Auto-open first sub-item when switching menu group** (`all.js:623-627`) — saves a click; new could load the first item on group expand.
2. **Focus password field when the login name is pre-filled** (`login.asp:291`).
3. **Print button + human date/time in report toolbar** (`toolbar.inc:250, 281`: `FormatDateTime(now(), vbLongDate)`), useful for printed reports; new `date("Y-m-d H:i:s")` and off by default.
4. **Report loader shown only after 500 ms** to avoid flicker (`reportdetails.asp:87-88`).
5. **Print-only full edit URL** in report rows (`reportdetails.asp:393` `<span class=print>`), so a printed report shows where to edit.
6. **Red menu bar on TEST** (`style.css:276-278`) — unmistakable; new uses a small label.
7. **Marketing URL screen**: "Bekijk" preview button, the `https://<host>/` prefix under the Dir field, explanatory tip (`url_maint.asp:150-151, 173, 180`).
8. **Group list shows " (beheer)"** (`sec_list_groups.asp:210`) — quick visual cue of privileged groups.
9. **Administrator edit hides irrelevant group section** with explanation (`sec_user_maint.asp:258-267`), and shows "er moet altijd één administrator zijn" inline (`:234`).
10. **Rights matrix keeps original label casing** (new `ucfirst(strtolower())`, `JsonFormRenderer.php:451`, mangles acronyms).
11. **Logo `title="Ga naar de site"`** tooltip (`menurep.asp:141`).
12. **Toolbar "pressed" feedback** (`tb_but_down`) on New/Save/Copy (`toolbar.inc:186, 213`, `all.js:686`) — verify the new `.tb-btn` has an equivalent active state; the dirty save-pulse is retained (`assets/css/style.css:204`).
13. **Single-result auto-click in list panes** (`sec_list_users.asp:9-15`) — kept for templates (`listTemplates.php:27-29`); confirm form tree mode does the same for users/groups.

---

### 4. Things the new does better (brief)

- Sidebar shell with collapsed popups, breadcrumb, history/pushState, theme (light/dark/system), truncation tooltips (`main.php`, `main.js`).
- Page-level access enforcement derived from the tools catalog badges (`page_levels.inc`), user levels User/Admin/Developer, impersonation ("Inloggen als" `users.json` extraIcon, return-to-self `main.php:422-443`), SSO (`sso_*.php`), dual cookie (ID+GUID) validation (`SecurityHelper.php:68-110`), CIDR IP patterns.
- Last-admin / last-developer / self-delete guards (`form_api.php:1685-1735`); password stored as `type: password` field.
- Rights matrix: bulk header radios, parent/child disabling, hides unused button columns (`JsonFormRenderer.php:293-330`, `form-controller.js:8743-8924`).
- Tools: launcher with search, many new tools (backup/restore, migrations, log reader, settings, maintenance page, LLM, WebP, tests), SQL tool with schema pickers/dangerous-query guard, secret masking in server info.
- Reports: CSV export, report designer, batched sub-report fetch (`reportdetails.php:405-476`).
- File browser: drag-and-drop upload, crop/rotate/autocrop/restore, thumbnails in both views (`wizards/file-browser.php`).
- Dashboard: stats, recent activity, migration/PHP warnings, guided tours (`cma-tours.js`), preferences page.
- Cache clearing covers form/APCu/OPcache/Twig/minify with API mode (`tools/tools_clearcache.php:44-108`).
