# prompts.md — verbatim user-prompt log

Append-only log of user prompts in chronological order. Newest at the
bottom. Each entry: a `## YYYY-MM-DD` date heading (group multiple same-
day prompts under one heading) and a `> ` blockquoted prompt verbatim.

Purpose: survives context compaction. When a session is summarised, the
exact wording of user directives is preserved verbatim here so future
sessions can recover original intent.

Do NOT edit, rephrase, or "tidy" prior prompts. Add only.

---

## 2026-05-28 — 2026-05-30 (lib-sheet / docs hub / deploy webhook / deploy_status)

> a new control has been made : lib_panel, inspect it and document in storybook. And see if all webcomponents now have an entry in storybook

> i thought we had a version number of the CMA in the profile menu

> the deploy log... do you want to download (when viewing the deploy log in the logreader)

> if an extra button uses the [slug] code

> let the new omgeving tool also show which env file is in use and push to git

> Wissel van omgeving -> Ik wil de setting kunnen switchen, niet de url. En niet onderaan maar naast de label van de huidige omgeving.

> yes please instruct the webhook to call composer update stenversonline/platform

> Nope, did nothing?!!

> i ran composer update a while ago

> is web.config in the /cma in the git at all?

> klei is at /mnt/c/repos/klei i believe

> create a documentation branch in the system menu and create a page documenting the deployment with a link to the deployment lig and the required settings... move the storybook to the documentation branch. then go into plan mode and think of other area's a developer or an administrator needs to know. Better too much than too little. also make sure claude.md knows how to maintain the documentation because stale documentation is the worst

> menu.json is application specific, we need to make sure it is in all sites, can we use the tools menu instead?

> always re-evaluate facts in the documents

> in the docs, are the locations of log files clearly marked?

> double check all facts in the docs

> the urls that bootstraps creates in the router and pits in the address bar are wrong, ehatever i do in the cma, refreshing leads to access denied errors

> it is mostly with tools

> dis you do the last change?

> yes push it

> i want to remeotely be able to see if a deploymen was succesfull guarded by a call you can store, document it so i can ask you to add it to claude.md on each site

> that site will not have that block, please give me the raw data

> the deploy secret is not on dev machines

> work with the repo name as a guard

> nee ik wil die secret niet in claude.md, verzin een eenvoudiger list: er komen geen geheimen naar boven als deploy_status.php de laatste status doorgeeft, je luisterd gewoon ook niet!!

> die url moet standalone kunnen fungeren, zo min mogelijk afhankelijkheden

## 2026-05-31 — copy-paste correctness + persistence rule

> give me a single command for claude not fragmentsni cannot copy

> plain text please

> damn, i want the complete verification process in plain text to hand over to other claude.md

> again you have vode in it, i cannot copy that! plain text please

> Jezus, again a code block?! why??

> thank you.. did you ipdate the documentation?

> doc klopt nu met de beschrijving die je me ju geeft?

> ja uiteraard

> can you add the prevvious conversatioon to prompts.md and save in claude.md never to compact that rule again?

## 2026-05-31 / 2026-06-01 — verification, error-handling sweep, self-checks, management story, IIS/routing root-cause hunt (backfill — was niet bijgehouden tijdens de sessie)

> okay now I want you to determine if all innprompts.md is implemented and no regression has taken place

> cma-blockeditor is not internal

> i am worried about error handling, could you do a thorough check?

> ja (Tier 0 + 1 fixes doorvoeren)

> ja (Database loud-log + form_api inner catches)

> make sure this is in the web.config template; <outboundRules>… kun je verder?

> kan de documentatie zelf checks uitvoeren met name de configuraties? bijvoorbeeld op web.config?

> alles graag!

> see if todo.md has any work left

> start with Server-side changelog voor Edit operations (line 62) — TODO sinds 2026-01-30. Add/Delete zijn al gefixed, Edit vereist "oude waarden ophalen vóór update". Test coverage 6% (line 140) — strategisch, niet één PR.

> sqlite not in use

> go!

> nee ga door met alles

> yes p[lease continue

> ja graag (DEPLOY_RUN_TESTS gate + DatabaseErrorPathTest)

> i have created a document for management (so low on details, user and business focussed) on the migration of classic asp to php and the implementation of cma_platform. This will in time be reviewed by a technical/architect. Can you create a text that 'sells' the migration and the new platform for MT and the tech person? We can split it into overview and technical details if that is more convenient for you or better for the targeted audience

> Kun je iets minder negatief over classic asp doen en de reden mag je weglaten, dat is bekend. Daarnaast wil ik een vergelijking tussen de oude cma en de nieuwe en wat deze allemaal toevoegd. Maak duidelijk dat web componenten component dependency hell voorkomen en dat composer is gekozen als update methode voor de paar componenten die we wel gebruiken, benoem deze componenten

> Toch nog teveel technische termen in het MT verhaal. Je mag wel meer technische termen plaatsen in het technische verhaal

> Toch nog teveel technische termen in het MT verhaal. Je mag wel meer technische termen plaatsen in het technische verhaal maar niet teveel interne implementatie details (zoals url's en bestandsnamen)

> Okay, ik mis nog een stuk in beide versies over onderhoudbaarheid en overdraagbaarheid.

> In deze scope betreft het maar 1 applicatie, het verhaal van multiple applicaties is dus alleen relevant om aan te tonen dat het proven technology is dat al werkt op andere sites.

> ik denk dat we het gebruik van AI bij het onderhoud ook kunnen duiden, een ervaren ontwikkelaar in combinatie met Claude code … [vul in] Bij beide varianten van de tekst

> ja graag (consolidated migration doc)

> a sample of a non-functional link: https://mijntoprecepten.nl/cma/tools/?tool=documentation while https://mijntoprecepten.nl/cma/tools.php?tool=documentation works, let's go with that, update links accordingly. Then : clickint an item in https://mijntoprecepten.nl/cma/tools/documentation.php?topic=deployment does noting. The Documentation tab has a deployment item, delete that please. The documentation has an unwanted padding in tools/documentation.php and the vertical fold is missing and links do not work from the tree

> the tree in documentation has a vissible &amp; remove that please

> https://mijntoprecepten.nl/cma/dashboard again does not work, tiresome..

> root cause -> grondoorzaak

> what is the latest version?

> ja check het ajb (rec/web.config inspectie)

> ja graag (appendQueryString fix)

> https://mijntoprecepten.nl/cma/dashboard.php doet het, https://mijntoprecepten.nl/cma/dashboard geeft een 404

> ja graag (URL Rewrite Module live-check + troubleshooting)

> https://mijntoprecepten.nl/cma/preferences doe het ook niet

> kan het zijn dat web.config in de .gitignore staat?

> this is the web.config on rec\cma : [volledige web.config inhoud geplakt] (yes the crlf are weird, can you fix that as well?)

> continue

> cma is geen application

> er staan regels in

> ja tuurlijk, staat toch in prompt.md als hard rule dat je die altijd bij moet werken> zo nee, wil je dat noteren?

## 2026-06-02

> n ik wil alledrie de extra validaties

> I want to have \n  The unrelated lib-arrowsteps.js/.min.js in the webcomponents

> the arrow-step had whitespece in between them, i think the gap is the cause of it..

> think mode: the cma-blockeditor needs to be tested for robustness. It now happens often that an edit field becomes totally empty and edits are lost. Take a really good look at the code, the underling ckeditor code and advice on how to anlyse and ultimately solve. First ultra-think

> Okay, some more information. If I move a block , the content is cleared. Also when i create a new block the ckeditor does not appear.

> fix the third symptom and the file may be called blockedit-test.html and moved to the file where ckeditor resides

> Note in todo.md that wire <cma-blockeditor> in as a real replacement to ckeditor should be performed, as well as modifying all callers

## 2026-06-04

> fixen van installatie zie ik niet terug, met name web.config bouw met zelfde safeguards als migratie 9.9.0

> migratie 9.9.0 loopt niet, de knop alle migraties uitvoer. leidt tot de vraag om migraties.php te downloaden, maak de url zo dat hij wel werkt

> back to the ck editor: if I press tha + button in an array item of htmleditors, the htmleditor does not appear, only after saving the record and retrieving it does it work

> so the function blockedit_array_add_array_element(this, template, 'Accordeon','Accordeon',null) has issues

> what files are you working in?

> please compare  cma/assets/js/blockedit.js to /mnt/c/repos/rino/portal1.0/cma/include/blockedit.js and see that needs to be changed in the latter

> webcomponent lib-radiogroup : remove .lib-radio-group__option--selected the box-shadow css

> in the documentation: the fold is not 100% heigh, look at tools.php for the correct implementation

> documentation reports: Actief .env-bestand	FOUT	.env ontbreekt op C:\wwwroot\karaat_php/.env. Kopieer uit .env.template of unset APP_ENVIRONMENT. , but .env.local is being used, reconsider that message

> Deprecated: Function curl_close() is deprecated since 8.5, as it has no effect since PHP 8.0 in C:\wwwroot\karaat_php\cma\tools\documentation.php on line 420

> cma/ is als IIS Application ingericht (niet als Virtual Directory) -> dat hoefde toch niet meer?

> IIS-configuratie is missing fix it options

> in the documentation, code block; can you create a generic function that places a copy button on the right upper corner and if clicked: copy entire block in plain text, make that generic, so each <code> block get's that option

> JSON-gedreven formulieren -> toon het schema en de opties

> karaat: er staat .env bestaat niet.

> .lib-radio-group__option--selected:first-child { border-top-right-radius: 0px;
    border-bottom-right-radius: 0px;} and the last-child the left-radius: 0px

> copy that to the mijn rino repo please

> de radio-group wijzigingen; op de iPhone zijn ze beide groen qua achtergrond, de rest van de layout lijkt niet goed te werken

> can you have a  look at https://github.com/chopratejas/headroom and see if that is useful for this project?

> do we still need /cma/tools/deploy_webhook_standalone.php , it was replaced by /deploy.php

> i have repointed karaat. mijn rino and mijntoprecepten

> make sure /deploy.php is the one and only and most extended version, harden it and make it scream loud if an error occured. then we can safely remove the older version

> are the cariables (HTTP_X_ORIGINAL_FILE, HTTP_X_TOOL_NAME still being used?

> yes please fi it

> please retire it

> /deploy_status.php - can we have it check the configuration and if needed ask for missing information and add it to the configuration file where it was missing from?

> https://staging-mijn.rino.nl/deploy_status.asp -> error 500??

> continue

> yes please

> the DEPLOY_ variables, are they in the templates of .env files now?

> no overwrite

> the deploy_status.php says: REJECT: DEPLOY_SECRET not configured , but i want to know which .env file is being used, can you include that?

> who creates the deploy.php and deploy_status.php? On a consumer site i also see deploy_post.php

> I want the cma_platform to manage these files. Now each site has their own and that is harder to maintain

> look into the version of the mijntoprecepten repo and use that as the latest version, be critical: review the files

> please analyse: [deploy_status.php JSON pasted] : it has an error but ok=true, that feels wrong

> migration 9.9.0 : [appcmd exit 5 "insufficient permissions" reading redirection.config → ROLLBACK, but runner reported "✓ succesvol"]

> the migration should be aware it has failed, and how do we solve this?

> cma/tools.php?tool=deploy_setup -> de documentatie over git webhooks iets fraaier vormgeven en met uitleg waar nodig

> if i delete a menu from a submenuitem the list is not refreshed

> after a login don't load /dashboard or /dashboard..php but main.php

> give the tools and report lists a search as you type searchfield in the toolbar

> the section Test e-mail versturen , can you design that as a new mail window in Outlook? And can you place the From in the dialog as well

> is the cariable cma_language used anywhere?

> check if any of these variables are still used: [Applicatie settings list: omgeving, base_path, path_images, pict_pixel, cma_htmledit_img_path, pdo_driver, email_from, email_fromname, appname, company, name, cma_language, mod_language, closed_site, migration_sources_extra, start, local, test, cma_htmledit_css]

> 2 consumer site reporting that the mail send from is wrong. But i see the correct email_from setting in the Applicatie instellingen.

> 2 consumer site reporting that the mail send from is wrong. ... So as you concluded: email_fromname is incorrectly used as the from email

> h2 span.lnr::before {
    color: var(--heading-color);
    font-weight: bold !important;
}

## 2026-06-06

> https://karaat.stenversonline.nl/cma/tools.php?tool=serverinfo : make a finer grained division of settings (mail/deployment/etc.)

> https://karaat.stenversonline.nl/cma/tools.php?tool=serverinfo : make a finer grained division of settings (mail/deployment/etc.) and find suitable icons for all

> we have all liniericons mentioned in storybook, take alook inthere

> cma-tabs, if the mobile displat is activated, the number of items is shown as (.) if there are no items, please remove the (.) entirily

> the layout of the email form is ugly, was that even comitted? And the titles in the serverinfo are chopped off vertically, use 16px top margin and line-height 1 rem

> how do i get a screendump to you
> Test e-mail versturen -> can you give it some box-shadow?
> From    Karaat Edelstenen (info@karaatedelstenen.nl)
> Subject    Test e-mail vanuit CMA
> Dit is een test-e-mail vanuit de CMA Omgeving-tab.
> -> this is a test email from serverinfo, but it lacks the addressees  (on screen simulation)
> the div for Test e-mail versturen -> can you give it some box-shadow?

> div.tools h2 {
    line-height: 1.5rem;
    margin-top: 19px;
}
> but make sure the first has NO margin-top
> karaat still says: CMA platform versie    vdev-main , is the app.php outdated?

## 2026-06-06 (form-editor tree: genest subform-niveau)

> The orderregels form cannot be edited because it is a subform of a subform, make sure you support that in the list of forms as well

> i don't understand, the cma_platform does not seem to render the form in the forms-list where I can choose it. It seems to only list 1st level forms, not 2nd level

> the cma-tree tree_formedit from tools_formedit.php

> commit all please

> i updated cma_platform with composer update

Form-editor-boom (`cma/tools/tools_formedit.php`, action `buildTree`) las de
subform-verwijzing als `$sub['form']`, maar de form-definities gebruiken
`formName` (alias `name`); `form` bestond niet → geen enkele subform-relatie
werd herkend, dus de boom toonde alleen topniveau-formulieren (geen genest
niveau, dus ook geen subform-van-een-subform zoals orderregels). Fix:
`$subName = $sub['formName'] ?? $sub['form'] ?? $sub['name'] ?? ''` op beide
buildTree-plekken (regels 113 & 139); de recursie nest nu tot elke diepte.
Belandde via een gelijktijdige release in commit `7fd4f02` (Release 1.23.12).
De bijbehorende JSON-formdefinitie (`klanten_orders_orderregels.json`) is
karaat-data en staat in de karaat-repo.

> the webp conversion and .json are not explained in the documentation. Please take a good, good look into all functionality of the cma_platform in total, including web components and determine of there are new area's to cover. While you are at it, re-evaluatie if the documtation does not have stale or outdated information after the last edits (like .env information)

> blocking issue: lib-radio-group "Soort korting" required validation keeps failing ("Vul alle verplichte velden in: Soort korting") even though an option (percentage) is selected. (full element markup provided)

> Undefined constant "CONST_STRSORTPARAM"

> the active flag on a menu-item is totally ignored by the menu. make it work please

> wtf does Geen toegang tot dit formulier mean? I am logged in as admin??

> .lib-radio-group__option { padding: 3px 8px;; border-radius:4px } within cma forms, overwrite the default value, don't change it
> i seem to be logged off?? Geen toegang / Je hebt geen toegang tot dit formulier. Debug: formName cmamonitoring, CMAU=1, isLoggedIn=false, isAdmin=false, getUserLevel=0, REQUEST_URI /cma/form.php?form=cmamonitoring
> WTF: Error in /cma/login.php — Exception: Database query failed: SQLSTATE[HY000]: General error: 1 no such table: tblUsers in C:\wwwroot\karaat_php\cma\login.php:74
> who decided to switch to sqlite??

> no something in your codebase has changed, databases,json is dated 22-05, and just this morning i had access. Take a really good look at your own code
> (karaat databases.json pasted: id5 name "CMAUsers" Access CMAUsers.mdb, etc.)
> i want a single point of truth and to me that should be databases.json. Perhaps we should rename cmausers to users in it, but i do NOT want any other places where databases are defined

> yes hard remove them, i really want only 1 source of truth
> webp_convert -> just show the gauge if percentage<100 and on hover the percentage, if percentage>100 show it. the size of the images needs a 3px smaller font, it is not that important

> Error in /cma/login.php — no such table: tblUsers (still, after v1.25.0) — so NO, cma does nothing

> 1.25.0 is installed
> (pasted data/databases.json: entries named rep/users/main, all Access .mdb)
> stop doing that, i said: database.json is the single point of truth, so base the globals upon that, not your own shitty code

> i updated manually, still the error occurs (login.php no such table tblUsers). can we add debug information to get this up and running?

> (diag output: version=dev-main, dbConfigSource=(no databases.json read), usersDsn=sqlite cmausers.sqlite, usersConnDriver=sqlite) really? Make access the default database format. And make a cleaner message than this. I really don't understand what the issue is.
> the lib_sheet still has no animation
> lib_sheet .close {margin-top: -56px, z-index: 99}
> panel.header -> make sure it is center aligned
> and the subform is still not in the form list

> the form is missing from the forms list tools_form_edit.php, that has a tree with forms and there it is missing from

> .recipe-action__icon { margin-top:0px }

> PDOException Database.php:481 (code 63) connection 'rep' failed ... Unable to open registry key Temporary (volatile) Ace DSN ... (mijntoprecepten)
> 1b , 2 : the row never disappears (stays after the delete)

> (rep PDOException again, mijntoprecepten) why is the rep database referenced? We have only created that for the migrations to json. So don't read from it here

> the  App\Library\Installer::postUpdate : can that also clear any cache?

> but resetting web.config does clear all caches right?

> it closes itself after deleting.

> add the guard anyway

> Deterministic enforcement — a UserPromptSubmit hook in .claude/settings.json (committed, so it's team-wide). It runs python3 to append every submitted prompt to prompts.md with a timestamp. Verified: Pipe-tested against sample input (quotes/newlines/HTML/ampersands) → exit 0, no stdout, correct output.

> i thought we dropped phpdotenv and createrd out own env.php?

## 2026-06-10

> we have been working on bliockeditor.js that works with ckeditor.js. The bug that is still there is that if we create a new block dynamically with a ckeditor inside, the editor does not appear, it is a blank div. 
>
> Investigate the possible causes. Consider at least the option that it is a timing thing, perhaps retrying to create the ckeditor after 200ms or even 500ms if it has failed is a solution? If not , take a deep dive into what can cause it.

> please implement and you mention too many timers, what are they?

> okay, I implemented this blockedit.js in an older version of the CMA and get the following errors : Failed to load resource: the server responded with a status of 500 (Server Error)
> blockedit.js?version=1.1:159 Uncaught ReferenceError: cmaLog is not defined
> assets/contentblocks/contentblocks.json?v=1781090270167:1  Failed to load resource: the server responded with a status of 500 (Server Error)
> blockedit.js?version=1.1:159 Uncaught ReferenceError: cmaLog is not defined
>     at Object.<anonymous> (blockedit.js?version=1.1:159:4)
>     at c (jquery-1.10.2.min.js:4:26036)
>     at Object.fireWith [as rejectWith] (jquery-1.10.2.min.js:4:26840)
>     at k (jquery-1.10.2.min.js:6:14283)
>     at XMLHttpRequest.r (jquery-1.10.2.min.js:6:18646) 
>
>
> 2 resuests: 
> - can we stub cmaLog if not available?

> 2 issues: 
>
> errors;
>
> Failed to load resource: the server responded with a status of 500 (Server Error)
> ckeditor/skins/moono/skin.js?t=G14E:1  Failed to load resource: the server responded with a status of 500 (Server Error)
> assets/contentblocks/contentblocks.json?v=1781090740715:1  Failed to load resource: the server responded with a status of 500 (Server Error)
> ckeditor/skins/moono/skin.js?t=G14E:1  Failed to load resource: the server responded with a status of 500 (Server Error)
> assets/contentblocks/contentblocks.json?v=1781090747045:1  Failed to load resource: the server responded with a status of 500 (Server Error)
> ckeditor/skins/moono/skin.js?t=G14E:1  Failed to load resource: the server responded with a status of 500 (Server Error)
>
> and the save does not seem to work, i always get the older version, might have to do with these errors, so let's deal with them first

> can you treat 500 errors as  400 errors and move on?

> installHook.js:1 [BlockEdit] Block definitions unusable from /cma/assets/contentblocks/contentblocks.json?v=1781091880958 (status 500 (error)), trying: /cma_contentblocks.json?v=1781091880958
> overrideMethod @ installHook.js:1
> warn @ blockedit.js?version=1.1:27
> blockedit_definitions_next @ blockedit.js?version=1.1:222
> (anonymous) @ blockedit.js?version=1.1:212
> c @ jquery-1.10.2.min.js:4
> fireWith @ jquery-1.10.2.min.js:4
> k @ jquery-1.10.2.min.js:6
> r @ jquery-1.10.2.min.js:6
> XMLHttpRequest.send
> send @ jquery-1.10.2.min.js:6
> ajax @ jquery-1.10.2.min.js:6
> blockedit_load_definitions @ blockedit.js?version=1.1:186
> blockedit_init @ blockedit.js?version=1.1:175
> (anonymous) @ details.asp?ID=195&FormID=132:57
> c @ jquery-1.10.2.min.js:4
> fireWith @ jquery-1.10.2.min.js:4
> ready @ jquery-1.10.2.min.js:4
> q @ jquery-1.10.2.min.js:4
> ckeditor.js:88 Allow attribute will take precedence over 'allowfullscreen'.
> CKEDITOR.tools.extend.setHtml @ ckeditor.js:88
> (anonymous) @ ckeditor.js:298
> n @ ckeditor.js:10
> (anonymous) @ ckeditor.js:12
> CKEDITOR.editor.CKEDITOR.editor.fire @ ckeditor.js:13
> toHtml @ ckeditor.js:301
> setData @ ckeditor.js:839
> (anonymous) @ ckeditor.js:352
> n @ ckeditor.js:10
> (anonymous) @ ckeditor.js:12
> CKEDITOR.editor.CKEDITOR.editor.fire @ ckeditor.js:13
> setData @ ckeditor.js:255
> b @ ckeditor.js:835
> (anonymous) @ ckeditor.js:837
> CKEDITOR.editor.setMode @ ckeditor.js:331
> (anonymous) @ ckeditor.js:326
> n @ ckeditor.js:10
> (anonymous) @ ckeditor.js:12
> CKEDITOR.editor.CKEDITOR.editor.fire @ ckeditor.js:13
> fireOnce @ ckeditor.js:12
> CKEDITOR.editor.CKEDITOR.editor.fireOnce @ ckeditor.js:13
> (anonymous) @ ckeditor.js:249
> f @ ckeditor.js:229
> load @ ckeditor.js:229
> (anonymous) @ ckeditor.js:248
> (anonymous) @ ckeditor.js:236
> (anonymous) @ ckeditor.js:234
> f @ ckeditor.js:229
> load @ ckeditor.js:229
> load @ ckeditor.js:234
> l @ ckeditor.js:235
> (anonymous) @ ckeditor.js:236
> x @ ckeditor.js:247
> (anonymous) @ ckeditor.js:246
> (anonymous) @ ckeditor.js:470
> (anonymous) @ ckeditor.js:234
> f @ ckeditor.js:229
> x @ ckeditor.js:229
> A @ ckeditor.js:229
> (anonymous) @ ckeditor.js:230
> setTimeout
> CKEDITOR.env.ie.CKEDITOR.env.version.g.$.onload @ ckeditor.js:230
> script
> CKEDITOR.dom.element @ ckeditor.js:79
> u @ ckeditor.js:230
> load @ ckeditor.js:230
> load @ ckeditor.js:234
> getStylesSet @ ckeditor.js:470
> f @ ckeditor.js:246
> (anonymous) @ ckeditor.js:246
> d @ ckeditor.js:228
> f @ ckeditor.js:229
> x @ ckeditor.js:229
> A @ ckeditor.js:229
> (anonymous) @ ckeditor.js:230
> setTimeout
> CKEDITOR.env.ie.CKEDITOR.env.version.g.$.onload @ ckeditor.js:230
> script
> CKEDITOR.dom.element @ ckeditor.js:79
> u @ ckeditor.js:230
> load @ ckeditor.js:230
> load @ ckeditor.js:228
> w @ ckeditor.js:245
> (anonymous) @ ckeditor.js:245
> b @ ckeditor.js:483
> loadPart @ ckeditor.js:485
> n @ ckeditor.js:245
> (anonymous) @ ckeditor.js:245
> n @ ckeditor.js:10
> (anonymous) @ ckeditor.js:12
> CKEDITOR.editor.CKEDITOR.editor.fire @ ckeditor.js:13
> fireOnce @ ckeditor.js:12
> CKEDITOR.editor.CKEDITOR.editor.fireOnce @ ckeditor.js:13
> l @ ckeditor.js:243
> k @ ckeditor.js:245
> (anonymous) @ ckeditor.js:241
> (anonymous) @ ckeditor.js:28
> setTimeout
> setTimeout @ ckeditor.js:28
> a @ ckeditor.js:241
> a @ ckeditor.js:325
> CKEDITOR.replace @ ckeditor.js:329
> blockedit_createCKEditor @ blockedit.js?version=1.1:1203
> blockedit_create_htmls_internal @ blockedit.js?version=1.1:1097
> blockedit_create_htmls @ blockedit.js?version=1.1:1090
> blockedit_add_new_element @ blockedit.js?version=1.1:784
> (anonymous) @ blockedit.js?version=1.1:298
> each @ jquery-1.10.2.min.js:4
> each @ jquery-1.10.2.min.js:4
> blockedit_init_elements @ blockedit.js?version=1.1:259
> (anonymous) @ blockedit.js?version=1.1:208
> c @ jquery-1.10.2.min.js:4
> fireWith @ jquery-1.10.2.min.js:4
> k @ jquery-1.10.2.min.js:6
> r @ jquery-1.10.2.min.js:6
> XMLHttpRequest.send
> send @ jquery-1.10.2.min.js:6
> ajax @ jquery-1.10.2.min.js:6
> blockedit_load_definitions @ blockedit.js?version=1.1:186
> blockedit_definitions_next @ blockedit.js?version=1.1:224
> (anonymous) @ blockedit.js?version=1.1:212
> c @ jquery-1.10.2.min.js:4
> fireWith @ jquery-1.10.2.min.js:4
> k @ jquery-1.10.2.min.js:6
> r @ jquery-1.10.2.min.js:6
> XMLHttpRequest.send
> send @ jquery-1.10.2.min.js:6
> ajax @ jquery-1.10.2.min.js:6
> blockedit_load_definitions @ blockedit.js?version=1.1:186
> blockedit_init @ blockedit.js?version=1.1:175
> (anonymous) @ details.asp?ID=195&FormID=132:57
> c @ jquery-1.10.2.min.js:4
> fireWith @ jquery-1.10.2.min.js:4
> ready @ jquery-1.10.2.min.js:4
> q @ jquery-1.10.2.min.js:4
> details.asp?ID=195&FormID=132:1 <meta name="apple-mobile-web-app-capable" content="yes"> is deprecated. Please include <meta name="mobile-web-app-capable" content="yes">

> Okay, save still does not work. Let's : add a console.log with the version number. And let's add logging for the save button.

> okay, no logging..

> [BlockEdit] blockedit.js v1.26.6 loaded
> details.asp?ID=195&FormID=132:28 Lib_Cache_retrieve_fromfile 'CMA_formdefinitie_132' from FILE - F:\wwwroot\test.rinoportal.nl\cache\CMA_formdefinitie_132
> details.asp?ID=195&FormID=132:30 Profiler 15ms -> Lib_Cache_retrieve_fromfile - Ophalen CMA_formdefinitie_132 -> total :15 ms : 
> details.asp?ID=195&FormID=132:32 Lib_Cache_retrieve_fromfile 'CMA_subform_detail_132' from FILE - F:\wwwroot\test.rinoportal.nl\cache\CMA_subform_detail_132
> details.asp?ID=195&FormID=132:34 Profiler 15ms -> Lib_Cache_retrieve_fromfile - Ophalen CMA_subform_detail_132 -> total :31 ms : 
> details.asp?ID=195&FormID=132:131 Lib_Cache_retrieve_fromfile 'CMA_access_notify_email_132' from FILE - F:\wwwroot\test.rinoportal.nl\cache\CMA_access_notify_email_132
> details.asp?ID=195&FormID=132:133 Profiler 187ms -> Lib_Cache_retrieve_fromfile - Ophalen CMA_access_notify_email_132 -> total :218 ms : 
> details.asp?ID=195&FormID=132:153 Profiler 31ms -> WriteRepCombo : Na ophalen data (naam: fkRinoNieuwsAfdeling, dynamisch: False, nRecords:3) -> total :250 ms : 
> details.asp?ID=195&FormID=132:162 Profiler 15ms -> WriteRepCombo : Na ophalen data (naam: fkOplSoort, dynamisch: False, nRecords:14) -> total :265 ms : 
> details.asp?ID=195&FormID=132:167 Profiler 15ms -> WriteRepCombo : Na ophalen data (naam: fkDifferentiatie, dynamisch: False, nRecords:7) -> total :281 ms : 
> details.asp?ID=195&FormID=132:172 Profiler 15ms -> WriteRepCombo : Na ophalen data (naam: fkBIGDifferentiatie, dynamisch: False, nRecords:7) -> total :296 ms : 
> subform.asp?ID=195&FormID=132:20 Lib_Cache_retrieve_fromfile 'CMA_subform_detail_132' from MEMORY 

> i only see± blockedit.js?version=1.1:1577 [BlockEdit] v1.26.7 collect_htmls() called, definitions loaded then nothing..

> remove the debug please

## 2026-06-14

> change repo to cma_platform

> okay, I get many errors like Lijst laden: HTTP 500 Internal Server Error. These vague errors are useless. If the current user is an admin or a supervisor, give all the details you have so someone can actually solve it.

## 2026-06-15

> review all items from prompts.md if they are handled  
>
> the lib_sheet still has no animation , i think a shadow dom issue..
>
> tools pagina op mobiel; placeholder en rechterkader niet 100% hoog, please check
>  Oeps, die kan ik niet vinden: tools/llm_models.php from the rec repo

> <task-notification>
> <task-id>bv3a73vfs</task-id>
> <tool-use-id>toolu_01PQj66kBynzKyVqxFYHJmhz</tool-use-id>
> <output-file>/tmp/claude-1000/-mnt-c-repos-cma-platform/387bc587-e360-4c3a-9559-1ccee2ca52c1/tasks/bv3a73vfs.output</output-file>
> <status>failed</status>
> <summary>Background command "Validate JSON with fallback check" failed with exit code 2</summary>
> </task-notification>

> <task-notification>
> <task-id>btgn1ega9</task-id>
> <tool-use-id>toolu_01TmDu7H4Kj66rPxDzrVGpGE</tool-use-id>
> <output-file>/tmp/claude-1000/-mnt-c-repos-cma-platform/387bc587-e360-4c3a-9559-1ccee2ca52c1/tasks/btgn1ega9.output</output-file>
> <status>completed</status>
> <summary>Background command "Validate JSON foreground" completed (exit code 0)</summary>
> </task-notification>

> <task-notification>
> <task-id>bww3ws31n</task-id>
> <tool-use-id>toolu_01Pv1XnEKZmwzjXzd9xwE6wx</tool-use-id>
> <output-file>/tmp/claude-1000/-mnt-c-repos-cma-platform/387bc587-e360-4c3a-9559-1ccee2ca52c1/tasks/bww3ws31n.output</output-file>
> <status>failed</status>
> <summary>Background command "Validate JSON and scan other repos" failed with exit code 1</summary>
> </task-notification>

> <task-notification>
> <task-id>bahg01j39</task-id>
> <tool-use-id>toolu_01WWoRw6ZsoHgzKHFRC2U6QA</tool-use-id>
> <output-file>/tmp/claude-1000/-mnt-c-repos-cma-platform/387bc587-e360-4c3a-9559-1ccee2ca52c1/tasks/bahg01j39.output</output-file>
> <status>completed</status>
> <summary>Background command "Validate JSON and scan other repos foreground" completed (exit code 0)</summary>
> </task-notification>

> lib_sheet : i see it on the rec consumer site, which is updated and all.

> switch to cma_platform repo on /mnt/c/repos/cma_platformn

> yes bump, commit and push

> the version of the cma_platform, I want that to be hardcoded as well, on sevaral sites it now reports a wrong version number, the current method is flawed. OR think of a better way to retrieve the version number

> on karaat - after update the version is still called vdev ??

> great, works!

> okay, now to the karaat cma: everywhere there is a 500 form load error, earlier we worked on a more descriptive errormessage, but i don't see it, when a developer/admin has an error in the cma we need a better description and more detailed errors

> forms are visible again! continious loading behavious strangely; it loads the first 100, then 100 at a time but stops prematurely ; records 1-1600 van 1759 (laden...)

> {
>     "success": true,
>     "html": "<tr class=\"listrow\" data-id=\"4806\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4806\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\"><\/td><td data-field=\"BerekendPrijs\" data-type=\"text\"><\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"34\">Opaal<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">05-04-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">140.0<\/td><td data-field=\"afm1\" data-type=\"text\">0.0<\/td><td data-field=\"afm2\" data-type=\"text\">0.0<\/td><td data-field=\"afm3\" data-type=\"text\">0.0<\/td><td data-field=\"inkoopNr\" data-type=\"text\">862139<\/td><td data-field=\"InkoopTitel\" data-type=\"text\">Bumblebee-Pattern-140Ct-Master-Piece-of-Designer-Cut-Welo-Opal-H<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\">20.0000<\/td><td data-field=\"inkoopUrl\" data-type=\"text\">https:\/\/www.gemrockauctions.com\/auctions\/ern-140ct-master-piece-of-designer-cut-welo-opal-h-862139<\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4807\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4807\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">9<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">91.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">91.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"3\">Partijen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"34\">Opaal<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">05-04-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">3.65<\/td><td data-field=\"afm1\" data-type=\"text\">0.0<\/td><td data-field=\"afm2\" data-type=\"text\">0.0<\/td><td data-field=\"afm3\" data-type=\"text\">0.0<\/td><td data-field=\"inkoopNr\" data-type=\"text\">860717<\/td><td data-field=\"InkoopTitel\" data-type=\"text\">365crt-BEAUTY-PARCEL-WELO-OPAL-CUSTOM-JEWELS<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\">15.0000<\/td><td data-field=\"inkoopUrl\" data-type=\"text\">https:\/\/www.gemrockauctions.com\/auctions\/365crt-beauty-parcel-welo-opal-custom-jewels-860717<\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4808\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4808\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">109.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">109.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"34\">Opaal<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">05-04-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bZeldzaam\" checked><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">4.35<\/td><td data-field=\"afm1\" data-type=\"text\">0.0<\/td><td data-field=\"afm2\" data-type=\"text\">0.0<\/td><td data-field=\"afm3\" data-type=\"text\">0.0<\/td><td data-field=\"inkoopNr\" data-type=\"text\">861811<\/td><td data-field=\"InkoopTitel\" data-type=\"text\">Rare-Black-Opal-435Ct-Natural-Untreated-Ethiopian-Black-Opal-H<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\">37.0000<\/td><td data-field=\"inkoopUrl\" data-type=\"text\">https:\/\/www.opalauctions.com\/auctions\/pal-435ct-natural-untreated-ethiopian-black-opal-h-861811<\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4809\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4809\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\"><\/td><td data-field=\"BerekendPrijs\" data-type=\"text\"><\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"34\">Opaal<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">05-04-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">170.0<\/td><td data-field=\"afm1\" data-type=\"text\">0.0<\/td><td data-field=\"afm2\" data-type=\"text\">0.0<\/td><td data-field=\"afm3\" data-type=\"text\">0.0<\/td><td data-field=\"inkoopNr\" data-type=\"text\">861589<\/td><td data-field=\"InkoopTitel\" data-type=\"text\">170-CRT-BEAUTY-CRYSTAL-CLEAR-GOLDEN-RIBBON-PIN-FIRE-WELO-OPAL<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\">16.0000<\/td><td data-field=\"inkoopUrl\" data-type=\"text\">https:\/\/www.gemrockauctions.com\/auctions\/uty-crystal-clear-golden-ribbon-pin-fire-welo-opal-861589<\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4810\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4810\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\"><\/td><td data-field=\"BerekendPrijs\" data-type=\"text\"><\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"34\">Opaal<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">05-04-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">418.0<\/td><td data-field=\"afm1\" data-type=\"text\">0.0<\/td><td data-field=\"afm2\" data-type=\"text\">0.0<\/td><td data-field=\"afm3\" data-type=\"text\">0.0<\/td><td data-field=\"inkoopNr\" data-type=\"text\">861507<\/td><td data-field=\"InkoopTitel\" data-type=\"text\">Micro-Honeycomb-418Ct-Master-Piece-Full-Bright-Honeycomb-Welo-Opal-H<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\">32.0000<\/td><td data-field=\"inkoopUrl\" data-type=\"text\">https:\/\/www.gemrockauctions.com\/auctions\/8ct-master-piece-full-bright-honeycomb-welo-opal-h-861507<\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4811\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4811\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\"><\/td><td data-field=\"BerekendPrijs\" data-type=\"text\"><\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"34\">Opaal<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">05-04-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">204.0<\/td><td data-field=\"afm1\" data-type=\"text\">0.0<\/td><td data-field=\"afm2\" data-type=\"text\">0.0<\/td><td data-field=\"afm3\" data-type=\"text\">0.0<\/td><td data-field=\"inkoopNr\" data-type=\"text\">861506<\/td><td data-field=\"InkoopTitel\" data-type=\"text\">Black-Crystal-204Ct-Master-Piece-of-Designer-Cut-Ridge-Crsytal-Opal-H<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\">47.0000<\/td><td data-field=\"inkoopUrl\" data-type=\"text\">https:\/\/www.gemrockauctions.com\/auctions\/-master-piece-of-designer-cut-ridge-crsytal-opal-h-861506<\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4812\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4812\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\"><\/td><td data-field=\"BerekendPrijs\" data-type=\"text\"><\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"34\">Opaal<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">05-04-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">160.0<\/td><td data-field=\"afm1\" data-type=\"text\">0.0<\/td><td data-field=\"afm2\" data-type=\"text\">0.0<\/td><td data-field=\"afm3\" data-type=\"text\">0.0<\/td><td data-field=\"inkoopNr\" data-type=\"text\">861505<\/td><td data-field=\"InkoopTitel\" data-type=\"text\">Bumblebee-Pattern-160Ct-Master-Piece-of-Designer-Cut-Welo-Opal-H<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\">24.0000<\/td><td data-field=\"inkoopUrl\" data-type=\"text\">https:\/\/www.gemrockauctions.com\/auctions\/ern-160ct-master-piece-of-designer-cut-welo-opal-h-861505<\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4813\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4813\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\"><\/td><td data-field=\"BerekendPrijs\" data-type=\"text\"><\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"34\">Opaal<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">05-04-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">261.0<\/td><td data-field=\"afm1\" data-type=\"text\">0.0<\/td><td data-field=\"afm2\" data-type=\"text\">0.0<\/td><td data-field=\"afm3\" data-type=\"text\">0.0<\/td><td data-field=\"inkoopNr\" data-type=\"text\">861209<\/td><td data-field=\"InkoopTitel\" data-type=\"text\">Black-Opal-261Ct-Master-Piece-of-Designer-Cut-Ridge-Black-Opal-H<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\">23.0000<\/td><td data-field=\"inkoopUrl\" data-type=\"text\">https:\/\/www.gemrockauctions.com\/auctions\/ct-master-piece-of-designer-cut-ridge-black-opal-h-861209<\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4814\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4814\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">42.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">42.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"34\">Opaal<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">05-04-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">1.69<\/td><td data-field=\"afm1\" data-type=\"text\">10.1<\/td><td data-field=\"afm2\" data-type=\"text\">7.4<\/td><td data-field=\"afm3\" data-type=\"text\">4.9<\/td><td data-field=\"inkoopNr\" data-type=\"text\">860930<\/td><td data-field=\"InkoopTitel\" data-type=\"text\">Honeycomb-169Ct-Ethiopian-Dark-Base-Welo-Opal-Honeycomb-Color-H<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\">21.0000<\/td><td data-field=\"inkoopUrl\" data-type=\"text\">https:\/\/www.opalauctions.com\/auctions\/honeycomb-169ct-ethiopian-dark-base-welo-opal-honeycomb-color-h97-860930<\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4815\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4815\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\"><\/td><td data-field=\"BerekendPrijs\" data-type=\"text\"><\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"34\">Opaal<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">05-04-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">150.0<\/td><td data-field=\"afm1\" data-type=\"text\">0.0<\/td><td data-field=\"afm2\" data-type=\"text\">0.0<\/td><td data-field=\"afm3\" data-type=\"text\">0.0<\/td><td data-field=\"inkoopNr\" data-type=\"text\">860927<\/td><td data-field=\"InkoopTitel\" data-type=\"text\">Aurora-Flash-150Ct-Master-Piece-of-Designer-Cut-Welo-Dark-Opal-H<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\">25.0000<\/td><td data-field=\"inkoopUrl\" data-type=\"text\">https:\/\/www.gemrockauctions.com\/auctions\/50ct-master-piece-of-designer-cut-welo-dark-opal-h-860927<\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4816\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4816\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\"><\/td><td data-field=\"BerekendPrijs\" data-type=\"text\"><\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"34\">Opaal<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">05-04-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bZeldzaam\" checked><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">246.0<\/td><td data-field=\"afm1\" data-type=\"text\">0.0<\/td><td data-field=\"afm2\" data-type=\"text\">0.0<\/td><td data-field=\"afm3\" data-type=\"text\">0.0<\/td><td data-field=\"inkoopNr\" data-type=\"text\">860288<\/td><td data-field=\"InkoopTitel\" data-type=\"text\">246-CT-Rare-Quality-Natural-Welo-Ethiopian-Opal-GC<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\">21.0000<\/td><td data-field=\"inkoopUrl\" data-type=\"text\">https:\/\/www.gemrockauctions.com\/auctions\/246-ct-rare-quality-natural-welo-ethiopian-opal-gc-860288<\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4817\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4817\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\"><\/td><td data-field=\"BerekendPrijs\" data-type=\"text\"><\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"34\">Opaal<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">05-04-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bZeldzaam\" checked><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">327.0<\/td><td data-field=\"afm1\" data-type=\"text\">0.0<\/td><td data-field=\"afm2\" data-type=\"text\">0.0<\/td><td data-field=\"afm3\" data-type=\"text\">0.0<\/td><td data-field=\"inkoopNr\" data-type=\"text\">860287<\/td><td data-field=\"InkoopTitel\" data-type=\"text\">327-CT-Rare-Quality-Natural-Welo-Ethiopian-Opal-GC<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\">48.0000<\/td><td data-field=\"inkoopUrl\" data-type=\"text\">https:\/\/www.gemrockauctions.com\/auctions\/327-ct-rare-quality-natural-welo-ethiopian-opal-gc-860287<\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4818\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4818\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">72.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">72.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"34\">Opaal<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">05-04-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bZeldzaam\" checked><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">2.87<\/td><td data-field=\"afm1\" data-type=\"text\">0.0<\/td><td data-field=\"afm2\" data-type=\"text\">0.0<\/td><td data-field=\"afm3\" data-type=\"text\">0.0<\/td><td data-field=\"inkoopNr\" data-type=\"text\">860285<\/td><td data-field=\"InkoopTitel\" data-type=\"text\">287-CT-15X9-MM-Rare-Quality-Natural-Welo-Ethiopian-Opal-GC<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\">26.0000<\/td><td data-field=\"inkoopUrl\" data-type=\"text\">https:\/\/www.opalauctions.com\/auctions\/5x9-mm-rare-quality-natural-welo-ethiopian-opal-gc-860285<\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4819\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4819\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">91.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">91.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"34\">Opaal<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">05-04-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">3.65<\/td><td data-field=\"afm1\" data-type=\"text\">0.0<\/td><td data-field=\"afm2\" data-type=\"text\">0.0<\/td><td data-field=\"afm3\" data-type=\"text\">0.0<\/td><td data-field=\"inkoopNr\" data-type=\"text\">860284<\/td><td data-field=\"InkoopTitel\" data-type=\"text\">365-CT-16X9-MM-Rare-Quality-Natural-Welo-Ethiopian-Opal-GC<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\">45.0000<\/td><td data-field=\"inkoopUrl\" data-type=\"text\">https:\/\/www.opalauctions.com\/auctions\/6x9-mm-rare-quality-natural-welo-ethiopian-opal-gc-860284<\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\">Ethiopie<\/td><\/tr><tr class=\"listrow\" data-id=\"4820\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4820\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">80.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">80.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"34\">Opaal<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">05-04-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bZeldzaam\" checked><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">3.2<\/td><td data-field=\"afm1\" data-type=\"text\">0.0<\/td><td data-field=\"afm2\" data-type=\"text\">0.0<\/td><td data-field=\"afm3\" data-type=\"text\">0.0<\/td><td data-field=\"inkoopNr\" data-type=\"text\">860281<\/td><td data-field=\"InkoopTitel\" data-type=\"text\">320-CT-Rare-Quality-Natural-Welo-Ethiopian-Opal-GC<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\">25.0000<\/td><td data-field=\"inkoopUrl\" data-type=\"text\">https:\/\/www.opalauctions.com\/auctions\/320-ct-rare-quality-natural-welo-ethiopian-opal-gc-860281<\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\">Ethiopie<\/td><\/tr><tr class=\"listrow\" data-id=\"4821\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4821\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\"><\/td><td data-field=\"BerekendPrijs\" data-type=\"text\"><\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"34\">Opaal<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">05-04-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bZeldzaam\" checked><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">398.0<\/td><td data-field=\"afm1\" data-type=\"text\">0.0<\/td><td data-field=\"afm2\" data-type=\"text\">0.0<\/td><td data-field=\"afm3\" data-type=\"text\">0.0<\/td><td data-field=\"inkoopNr\" data-type=\"text\">860282<\/td><td data-field=\"InkoopTitel\" data-type=\"text\">398-CT-Rare-Quality-Natural-Welo-Ethiopian-Opal-GC<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\">36.0000<\/td><td data-field=\"inkoopUrl\" data-type=\"text\">https:\/\/www.gemrockauctions.com\/auctions\/398-ct-rare-quality-natural-welo-ethiopian-opal-gc-860282<\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4822\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4822\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\"><\/td><td data-field=\"BerekendPrijs\" data-type=\"text\"><\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"34\">Opaal<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">05-04-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">161.0<\/td><td data-field=\"afm1\" data-type=\"text\">0.0<\/td><td data-field=\"afm2\" data-type=\"text\">0.0<\/td><td data-field=\"afm3\" data-type=\"text\">0.0<\/td><td data-field=\"inkoopNr\" data-type=\"text\">858930<\/td><td data-field=\"InkoopTitel\" data-type=\"text\">161cts-Natural-Ethiopian-Welo-Opal-BF<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\">14.0000<\/td><td data-field=\"inkoopUrl\" data-type=\"text\">https:\/\/www.gemrockauctions.com\/auctions\/161cts-natural-ethiopian-welo-opal-bf-858930<\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4823\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4823\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\"><\/td><td data-field=\"BerekendPrijs\" data-type=\"text\"><\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"34\">Opaal<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">05-04-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">203.0<\/td><td data-field=\"afm1\" data-type=\"text\">0.0<\/td><td data-field=\"afm2\" data-type=\"text\">0.0<\/td><td data-field=\"afm3\" data-type=\"text\">0.0<\/td><td data-field=\"inkoopNr\" data-type=\"text\">858929<\/td><td data-field=\"InkoopTitel\" data-type=\"text\">203cts-Natural-Ethiopian-Welo-Opal-BF<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\">21.0000<\/td><td data-field=\"inkoopUrl\" data-type=\"text\">https:\/\/www.gemrockauctions.com\/auctions\/203cts-natural-ethiopian-welo-opal-bf-858929<\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4824\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4824\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\"><\/td><td data-field=\"BerekendPrijs\" data-type=\"text\"><\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"34\">Opaal<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">05-04-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">237.0<\/td><td data-field=\"afm1\" data-type=\"text\">0.0<\/td><td data-field=\"afm2\" data-type=\"text\">0.0<\/td><td data-field=\"afm3\" data-type=\"text\">0.0<\/td><td data-field=\"inkoopNr\" data-type=\"text\">858925<\/td><td data-field=\"InkoopTitel\" data-type=\"text\">237cts-Natural-Ethiopian-Welo-Opal-BF<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\">22.0000<\/td><td data-field=\"inkoopUrl\" data-type=\"text\">https:\/\/www.gemrockauctions.com\/auctions\/237cts-natural-ethiopian-welo-opal-bf-858925<\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4825\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4825\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\"><\/td><td data-field=\"BerekendPrijs\" data-type=\"text\"><\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"34\">Opaal<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">05-04-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">171.0<\/td><td data-field=\"afm1\" data-type=\"text\">0.0<\/td><td data-field=\"afm2\" data-type=\"text\">0.0<\/td><td data-field=\"afm3\" data-type=\"text\">0.0<\/td><td data-field=\"inkoopNr\" data-type=\"text\">858922<\/td><td data-field=\"InkoopTitel\" data-type=\"text\">171cts-Natural-Ethiopian-Welo-Opal-BF<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\">15.0000<\/td><td data-field=\"inkoopUrl\" data-type=\"text\">https:\/\/www.gemrockauctions.com\/auctions\/171cts-natural-ethiopian-welo-opal-bf-858922<\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4826\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4826\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\"><\/td><td data-field=\"BerekendPrijs\" data-type=\"text\"><\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"34\">Opaal<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">05-04-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">146.0<\/td><td data-field=\"afm1\" data-type=\"text\">0.0<\/td><td data-field=\"afm2\" data-type=\"text\">0.0<\/td><td data-field=\"afm3\" data-type=\"text\">0.0<\/td><td data-field=\"inkoopNr\" data-type=\"text\">858921<\/td><td data-field=\"InkoopTitel\" data-type=\"text\">146cts-Natural-Ethiopian-Welo-Opal-BF<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\">14.0000<\/td><td data-field=\"inkoopUrl\" data-type=\"text\">https:\/\/www.gemrockauctions.com\/auctions\/146cts-natural-ethiopian-welo-opal-bf-858921<\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4827\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4827\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\"><\/td><td data-field=\"BerekendPrijs\" data-type=\"text\"><\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"34\">Opaal<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">05-04-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">130.0<\/td><td data-field=\"afm1\" data-type=\"text\">0.0<\/td><td data-field=\"afm2\" data-type=\"text\">0.0<\/td><td data-field=\"afm3\" data-type=\"text\">0.0<\/td><td data-field=\"inkoopNr\" data-type=\"text\">858920<\/td><td data-field=\"InkoopTitel\" data-type=\"text\">130cts-Natural-Ethiopian-Welo-Opal-BF<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\">11.0000<\/td><td data-field=\"inkoopUrl\" data-type=\"text\">https:\/\/www.gemrockauctions.com\/auctions\/130cts-natural-ethiopian-welo-opal-bf-858920<\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4828\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4828\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">31.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">31.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"34\">Opaal<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">05-04-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">1.24<\/td><td data-field=\"afm1\" data-type=\"text\">9.4<\/td><td data-field=\"afm2\" data-type=\"text\">6.0<\/td><td data-field=\"afm3\" data-type=\"text\">4.2<\/td><td data-field=\"inkoopNr\" data-type=\"text\">858919<\/td><td data-field=\"InkoopTitel\" data-type=\"text\">124cts-Natural-Ethiopian-Welo-Opal-BF<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\">11.0000<\/td><td data-field=\"inkoopUrl\" data-type=\"text\">https:\/\/www.opalauctions.com\/auctions\/124cts-natural-ethiopian-welo-opal-bf-858919<\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4829\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4829\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\"><\/td><td data-field=\"BerekendPrijs\" data-type=\"text\"><\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"34\">Opaal<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">05-04-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">276.0<\/td><td data-field=\"afm1\" data-type=\"text\">0.0<\/td><td data-field=\"afm2\" data-type=\"text\">0.0<\/td><td data-field=\"afm3\" data-type=\"text\">0.0<\/td><td data-field=\"inkoopNr\" data-type=\"text\">851871<\/td><td data-field=\"InkoopTitel\" data-type=\"text\">276-CT-SATURATED-PATTERN-Welo-Ethiopian-Opal-GB<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\">34.0000<\/td><td data-field=\"inkoopUrl\" data-type=\"text\">https:\/\/www.gemrockauctions.com\/auctions\/276-ct-saturated-pattern-welo-ethiopian-opal-gb-851871<\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4830\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4830\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\"><\/td><td data-field=\"BerekendPrijs\" data-type=\"text\"><\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"34\">Opaal<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">05-04-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">170.0<\/td><td data-field=\"afm1\" data-type=\"text\">0.0<\/td><td data-field=\"afm2\" data-type=\"text\">0.0<\/td><td data-field=\"afm3\" data-type=\"text\">0.0<\/td><td data-field=\"inkoopNr\" data-type=\"text\">858433<\/td><td data-field=\"InkoopTitel\" data-type=\"text\">170cts-Natural-Ethiopian-Welo-Opal-BF<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\">16.0000<\/td><td data-field=\"inkoopUrl\" data-type=\"text\">https:\/\/www.gemrockauctions.com\/auctions\/170cts-natural-ethiopian-welo-opal-bf-858433<\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4831\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4831\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">68.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">68.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"34\">Opaal<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">05-04-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">2.71<\/td><td data-field=\"afm1\" data-type=\"text\">0.0<\/td><td data-field=\"afm2\" data-type=\"text\">0.0<\/td><td data-field=\"afm3\" data-type=\"text\">0.0<\/td><td data-field=\"inkoopNr\" data-type=\"text\">858431<\/td><td data-field=\"InkoopTitel\" data-type=\"text\">271cts-Natural-Ethiopian-Welo-Opal-BF<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\">23.0000<\/td><td data-field=\"inkoopUrl\" data-type=\"text\">https:\/\/www.opalauctions.com\/auctions\/271cts-natural-ethiopian-welo-opal-bf-858431<\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4832\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4832\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">24.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">24.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"2\">Ruwe stenen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"34\">Opaal<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">05-04-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">37.0<\/td><td data-field=\"afm1\" data-type=\"text\">0.0<\/td><td data-field=\"afm2\" data-type=\"text\">0.0<\/td><td data-field=\"afm3\" data-type=\"text\">0.0<\/td><td data-field=\"inkoopNr\" data-type=\"text\">859444<\/td><td data-field=\"InkoopTitel\" data-type=\"text\">37cts-Ethiopian-Crystal-Rough-Specimen-Rough-CR<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\">12.0000<\/td><td data-field=\"inkoopUrl\" data-type=\"text\">https:\/\/www.gemrockauctions.com\/auctions\/37cts-ethiopian-crystal-rough-specimen-rough-cr-859444<\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\">Ethiopie<\/td><\/tr><tr class=\"listrow\" data-id=\"4833\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4833\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\"><\/td><td data-field=\"BerekendPrijs\" data-type=\"text\"><\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"34\">Opaal<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">05-04-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">147.0<\/td><td data-field=\"afm1\" data-type=\"text\">0.0<\/td><td data-field=\"afm2\" data-type=\"text\">0.0<\/td><td data-field=\"afm3\" data-type=\"text\">0.0<\/td><td data-field=\"inkoopNr\" data-type=\"text\">858417<\/td><td data-field=\"InkoopTitel\" data-type=\"text\">147cts-Natural-Ethiopian-Smoked-Faceted-Black-Opal-BF<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\">24.0000<\/td><td data-field=\"inkoopUrl\" data-type=\"text\">https:\/\/www.gemrockauctions.com\/auctions\/cts-natural-ethiopian-smoked-faceted-black-opal-bf-858417<\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4834\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4834\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">49.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">28.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"34\">Opaal<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">05-04-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">1.12<\/td><td data-field=\"afm1\" data-type=\"text\">8.8<\/td><td data-field=\"afm2\" data-type=\"text\">6.3<\/td><td data-field=\"afm3\" data-type=\"text\">4.4<\/td><td data-field=\"inkoopNr\" data-type=\"text\">858413<\/td><td data-field=\"InkoopTitel\" data-type=\"text\">112cts-Natural-Ethiopian-Smoked-Faceted-Black-Opal-BF<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\">24.0000<\/td><td data-field=\"inkoopUrl\" data-type=\"text\">https:\/\/www.opalauctions.com\/auctions\/112cts-natural-ethiopian-smoked-faceted-black-opal-bf914-858413<\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4835\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4835\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\"><\/td><td data-field=\"BerekendPrijs\" data-type=\"text\"><\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"34\">Opaal<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">05-04-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">118.0<\/td><td data-field=\"afm1\" data-type=\"text\">0.0<\/td><td data-field=\"afm2\" data-type=\"text\">0.0<\/td><td data-field=\"afm3\" data-type=\"text\">0.0<\/td><td data-field=\"inkoopNr\" data-type=\"text\">858411<\/td><td data-field=\"InkoopTitel\" data-type=\"text\">118cts-Natural-Ethiopian-Smoked-Faceted-Black-Opal-BF<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\">25.0000<\/td><td data-field=\"inkoopUrl\" data-type=\"text\">https:\/\/www.gemrockauctions.com\/auctions\/cts-natural-ethiopian-smoked-faceted-black-opal-bf-858411<\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4836\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4836\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">38.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">38.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"34\">Opaal<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">05-04-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">1.53<\/td><td data-field=\"afm1\" data-type=\"text\">10.6<\/td><td data-field=\"afm2\" data-type=\"text\">6.0<\/td><td data-field=\"afm3\" data-type=\"text\">4.8<\/td><td data-field=\"inkoopNr\" data-type=\"text\">858409<\/td><td data-field=\"InkoopTitel\" data-type=\"text\">153cts-Natural-Ethiopian-Welo-Opal-BF<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\">13.0000<\/td><td data-field=\"inkoopUrl\" data-type=\"text\">https:\/\/www.opalauctions.com\/auctions\/153cts-natural-ethiopian-welo-opal-bf-858409<\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4837\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4837\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">28.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">28.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"34\">Opaal<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">05-04-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">1.1<\/td><td data-field=\"afm1\" data-type=\"text\">8.7<\/td><td data-field=\"afm2\" data-type=\"text\">5.8<\/td><td data-field=\"afm3\" data-type=\"text\">4.1<\/td><td data-field=\"inkoopNr\" data-type=\"text\">858407<\/td><td data-field=\"InkoopTitel\" data-type=\"text\">110cts-Natural-Ethiopian-Welo-Opal-BF<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\">10.0000<\/td><td data-field=\"inkoopUrl\" data-type=\"text\">https:\/\/www.opalauctions.com\/auctions\/110cts-natural-ethiopian-welo-opal-bf-858407<\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\">Ethiopie<\/td><\/tr><tr class=\"listrow\" data-id=\"4838\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4838\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">57.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">57.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"34\">Opaal<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">05-04-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">2.3<\/td><td data-field=\"afm1\" data-type=\"text\">0.0<\/td><td data-field=\"afm2\" data-type=\"text\">0.0<\/td><td data-field=\"afm3\" data-type=\"text\">0.0<\/td><td data-field=\"inkoopNr\" data-type=\"text\">859280<\/td><td data-field=\"InkoopTitel\" data-type=\"text\">230Ct-Natural-Ethiopian-Welo-Opal-Lot-JA<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\">43.0000<\/td><td data-field=\"inkoopUrl\" data-type=\"text\">https:\/\/www.gemrockauctions.com\/auctions\/230ct-natural-ethiopian-welo-opal-lot-ja-859280<\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4839\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4839\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">28.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">28.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"34\">Opaal<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">05-04-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">1.13<\/td><td data-field=\"afm1\" data-type=\"text\">0.0<\/td><td data-field=\"afm2\" data-type=\"text\">0.0<\/td><td data-field=\"afm3\" data-type=\"text\">0.0<\/td><td data-field=\"inkoopNr\" data-type=\"text\">859279<\/td><td data-field=\"InkoopTitel\" data-type=\"text\">113Ct-Natural-Ethiopian-Welo-Opal-Lot-JA<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\">11.0000<\/td><td data-field=\"inkoopUrl\" data-type=\"text\">https:\/\/www.gemrockauctions.com\/auctions\/113ct-natural-ethiopian-welo-opal-lot-ja-859279<\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4840\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4840\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">49.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">43.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"34\">Opaal<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">05-04-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">1.73<\/td><td data-field=\"afm1\" data-type=\"text\">0.0<\/td><td data-field=\"afm2\" data-type=\"text\">0.0<\/td><td data-field=\"afm3\" data-type=\"text\">0.0<\/td><td data-field=\"inkoopNr\" data-type=\"text\">858395<\/td><td data-field=\"InkoopTitel\" data-type=\"text\">173cts-Natural-Ethiopian-Welo-Opal-BF<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\">31.0000<\/td><td data-field=\"inkoopUrl\" data-type=\"text\">https:\/\/www.gemrockauctions.com\/auctions\/173cts-natural-ethiopian-welo-opal-bf-858395<\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4841\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4841\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\"><\/td><td data-field=\"BerekendPrijs\" data-type=\"text\"><\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"34\">Opaal<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">05-04-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">129.0<\/td><td data-field=\"afm1\" data-type=\"text\">0.0<\/td><td data-field=\"afm2\" data-type=\"text\">0.0<\/td><td data-field=\"afm3\" data-type=\"text\">0.0<\/td><td data-field=\"inkoopNr\" data-type=\"text\">858390<\/td><td data-field=\"InkoopTitel\" data-type=\"text\">129cts-Natural-Ethiopian-Smoked-Faceted-Black-Opal-BF<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\">19.0000<\/td><td data-field=\"inkoopUrl\" data-type=\"text\">https:\/\/www.gemrockauctions.com\/auctions\/cts-natural-ethiopian-smoked-faceted-black-opal-bf-858390<\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4842\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4842\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">26.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">26.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"34\">Opaal<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">05-04-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">1.05<\/td><td data-field=\"afm1\" data-type=\"text\">8.8<\/td><td data-field=\"afm2\" data-type=\"text\">6.1<\/td><td data-field=\"afm3\" data-type=\"text\">4.3<\/td><td data-field=\"inkoopNr\" data-type=\"text\">858383<\/td><td data-field=\"InkoopTitel\" data-type=\"text\">105cts-Natural-Ethiopian-Smoked-Faceted-Black-Opal-BF<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\">18.0000<\/td><td data-field=\"inkoopUrl\" data-type=\"text\">https:\/\/www.opalauctions.com\/auctions\/cts-natural-ethiopian-smoked-faceted-black-opal-bf-858383<\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\">Ethiopie<\/td><\/tr><tr class=\"listrow\" data-id=\"4843\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4843\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">1875.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">1875.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"34\">Opaal<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">05-04-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">75.0<\/td><td data-field=\"afm1\" data-type=\"text\">0.0<\/td><td data-field=\"afm2\" data-type=\"text\">0.0<\/td><td data-field=\"afm3\" data-type=\"text\">0.0<\/td><td data-field=\"inkoopNr\" data-type=\"text\">858382<\/td><td data-field=\"InkoopTitel\" data-type=\"text\">075cts-Natural-Ethiopian-Smoked-Faceted-Black-Opal-BF<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\">21.0000<\/td><td data-field=\"inkoopUrl\" data-type=\"text\">https:\/\/www.gemrockauctions.com\/auctions\/cts-natural-ethiopian-smoked-faceted-black-opal-bf-858382<\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4844\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4844\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\"><\/td><td data-field=\"BerekendPrijs\" data-type=\"text\"><\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"34\">Opaal<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">05-04-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bZeldzaam\" checked><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">200.0<\/td><td data-field=\"afm1\" data-type=\"text\">0.0<\/td><td data-field=\"afm2\" data-type=\"text\">0.0<\/td><td data-field=\"afm3\" data-type=\"text\">0.0<\/td><td data-field=\"inkoopNr\" data-type=\"text\">859025<\/td><td data-field=\"InkoopTitel\" data-type=\"text\">200-CT-Rare-Quality-Natural-Welo-Ethiopian-Opal-GC<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\">17.0000<\/td><td data-field=\"inkoopUrl\" data-type=\"text\">https:\/\/www.opalauctions.com\/auctions\/200-ct-rare-quality-natural-welo-ethiopian-opal-gc-859025<\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4845\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4845\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\"><\/td><td data-field=\"BerekendPrijs\" data-type=\"text\"><\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"34\">Opaal<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">05-04-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bZeldzaam\" checked><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">200.0<\/td><td data-field=\"afm1\" data-type=\"text\">0.0<\/td><td data-field=\"afm2\" data-type=\"text\">0.0<\/td><td data-field=\"afm3\" data-type=\"text\">0.0<\/td><td data-field=\"inkoopNr\" data-type=\"text\">859018<\/td><td data-field=\"InkoopTitel\" data-type=\"text\">200-CT-Rare-Quality-Natural-Welo-Ethiopian-Opal-GC<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\">20.0000<\/td><td data-field=\"inkoopUrl\" data-type=\"text\">https:\/\/www.opalauctions.com\/auctions\/200-ct-rare-quality-natural-welo-ethiopian-opal-gc-859018<\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4846\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4846\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">50.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">50.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"18\">Kunziet<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">29-04-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">3.26<\/td><td data-field=\"afm1\" data-type=\"text\">11.5<\/td><td data-field=\"afm2\" data-type=\"text\">7.7<\/td><td data-field=\"afm3\" data-type=\"text\">5.9<\/td><td data-field=\"inkoopNr\" data-type=\"text\">coll?<\/td><td data-field=\"InkoopTitel\" data-type=\"text\"><\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4847\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4847\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">79.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">72.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"68\">Chroom diopsied<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">15-09-2019<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bZeldzaam\" checked><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">2.59<\/td><td data-field=\"afm1\" data-type=\"text\">10.04<\/td><td data-field=\"afm2\" data-type=\"text\">8.0<\/td><td data-field=\"afm3\" data-type=\"text\">4.03<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\">ebay<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\">Rusland<\/td><\/tr><tr class=\"listrow\" data-id=\"4848\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4848\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">86.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">86.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"16\">Aquamarijn<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">29-04-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">2.4<\/td><td data-field=\"afm1\" data-type=\"text\">9.84<\/td><td data-field=\"afm2\" data-type=\"text\">7.0<\/td><td data-field=\"afm3\" data-type=\"text\">6.87<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\">gift<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4849\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4849\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">69.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">54.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"68\">Chroom diopsied<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">29-04-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bZeldzaam\" checked><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">1.7<\/td><td data-field=\"afm1\" data-type=\"text\">9.05<\/td><td data-field=\"afm2\" data-type=\"text\">6.84<\/td><td data-field=\"afm3\" data-type=\"text\">3.57<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\">eb<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\">Rusland<\/td><\/tr><tr class=\"listrow\" data-id=\"4850\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4850\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">95.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">95.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"30\">Tripliet<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">29-04-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">1.3<\/td><td data-field=\"afm1\" data-type=\"text\">7.4<\/td><td data-field=\"afm2\" data-type=\"text\">7.0<\/td><td data-field=\"afm3\" data-type=\"text\">4.0<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\">whatsapp<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4851\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"0\"><span class=\"row-menu-trigger\" data-id=\"4851\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\"><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">49.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">39.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"10\">Afghaniet<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">29-04-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">0.27<\/td><td data-field=\"afm1\" data-type=\"text\">5.2<\/td><td data-field=\"afm2\" data-type=\"text\">4.2<\/td><td data-field=\"afm3\" data-type=\"text\">2.6<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\"><\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4852\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4852\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">18.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\"><\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"160\">Cassiteriet<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">29-04-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">13.35<\/td><td data-field=\"afm1\" data-type=\"text\">11.45<\/td><td data-field=\"afm2\" data-type=\"text\">9.41<\/td><td data-field=\"afm3\" data-type=\"text\">6.99<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\">whatsapp<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\">10.0000<\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4853\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4853\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">243.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">243.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"47\">Moldaviet<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">29-04-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bZeldzaam\" checked><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">3.04<\/td><td data-field=\"afm1\" data-type=\"text\">10.86<\/td><td data-field=\"afm2\" data-type=\"text\">8.81<\/td><td data-field=\"afm3\" data-type=\"text\">5.29<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\"><\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4854\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4854\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">190.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">190.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"30\">Tripliet<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">29-04-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">3.2<\/td><td data-field=\"afm1\" data-type=\"text\">8.5<\/td><td data-field=\"afm2\" data-type=\"text\">8.45<\/td><td data-field=\"afm3\" data-type=\"text\">5.71<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\"><\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4855\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4855\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">35.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">35.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"161\">Hiboniet<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">29-04-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bZeldzaam\" checked><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">2.47<\/td><td data-field=\"afm1\" data-type=\"text\">9.9<\/td><td data-field=\"afm2\" data-type=\"text\">7.0<\/td><td data-field=\"afm3\" data-type=\"text\">4.3<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\"><\/td><td data-field=\"inkoopPrijs\" data-type=\"text\">9.0000<\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4856\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4856\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">89.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">55.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"12\">Toermalijn<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">15-09-2019<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">1.16<\/td><td data-field=\"afm1\" data-type=\"text\">8.5<\/td><td data-field=\"afm2\" data-type=\"text\">6.0<\/td><td data-field=\"afm3\" data-type=\"text\">4.0<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\"><\/td><td data-field=\"inkoopPrijs\" data-type=\"text\">19.9000<\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4857\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4857\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">99.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">125.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"136\">Sphaleriet<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">29-04-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bZeldzaam\" checked><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">0.79<\/td><td data-field=\"afm1\" data-type=\"text\">5.2<\/td><td data-field=\"afm2\" data-type=\"text\">4.4<\/td><td data-field=\"afm3\" data-type=\"text\">3.3<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\"><\/td><td data-field=\"inkoopPrijs\" data-type=\"text\">9.0000<\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4858\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4858\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">49.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">33.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"90\">Indigoliet toermalijn<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">29-04-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">0.55<\/td><td data-field=\"afm1\" data-type=\"text\">4.4<\/td><td data-field=\"afm2\" data-type=\"text\">4.1<\/td><td data-field=\"afm3\" data-type=\"text\">3.9<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\"><\/td><td data-field=\"inkoopPrijs\" data-type=\"text\">11.0000<\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4859\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4859\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">54.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">54.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"12\">Toermalijn<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">29-04-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">1.15<\/td><td data-field=\"afm1\" data-type=\"text\">6.41<\/td><td data-field=\"afm2\" data-type=\"text\">5.83<\/td><td data-field=\"afm3\" data-type=\"text\">4.61<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\"><\/td><td data-field=\"inkoopPrijs\" data-type=\"text\">19.9000<\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4860\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4860\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">20.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\"><\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"160\">Cassiteriet<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">29-04-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">17.2<\/td><td data-field=\"afm1\" data-type=\"text\">13.17<\/td><td data-field=\"afm2\" data-type=\"text\">10.39<\/td><td data-field=\"afm3\" data-type=\"text\">7.84<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\">whatsapp<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\">10.0000<\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4861\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4861\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">76.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">76.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"14\">Tanzaniet<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">29-04-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">0.85<\/td><td data-field=\"afm1\" data-type=\"text\">7.0<\/td><td data-field=\"afm2\" data-type=\"text\">4.9<\/td><td data-field=\"afm3\" data-type=\"text\">3.3<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\">sloop<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\">40.0000<\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4862\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4862\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">20.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">20.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"22\">Kornerupine<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">29-04-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bZeldzaam\" checked><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">0.45<\/td><td data-field=\"afm1\" data-type=\"text\">5.9<\/td><td data-field=\"afm2\" data-type=\"text\">3.4<\/td><td data-field=\"afm3\" data-type=\"text\">2.4<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\"><\/td><td data-field=\"inkoopPrijs\" data-type=\"text\">2.5000<\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4863\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4863\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">18.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">18.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"22\">Kornerupine<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">29-04-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bZeldzaam\" checked><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">0.4<\/td><td data-field=\"afm1\" data-type=\"text\">5.2<\/td><td data-field=\"afm2\" data-type=\"text\">3.2<\/td><td data-field=\"afm3\" data-type=\"text\">2.4<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\"><\/td><td data-field=\"inkoopPrijs\" data-type=\"text\">2.5000<\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4864\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4864\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">2<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">119.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">98.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"7\">Edelstenen paren<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"56\">Tsavoriet<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">05-05-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">1.16<\/td><td data-field=\"afm1\" data-type=\"text\"><\/td><td data-field=\"afm2\" data-type=\"text\"><\/td><td data-field=\"afm3\" data-type=\"text\"><\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\"><\/td><td data-field=\"inkoopPrijs\" data-type=\"text\">66.0000<\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4865\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4865\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">2<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">26.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">23.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"7\">Edelstenen paren<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"1\">Topaas<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">05-05-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">1.01<\/td><td data-field=\"afm1\" data-type=\"text\">4.3<\/td><td data-field=\"afm2\" data-type=\"text\">4.3<\/td><td data-field=\"afm3\" data-type=\"text\">2.4<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\">coll<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4866\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4866\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">2<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">23.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">23.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"7\">Edelstenen paren<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"27\">Amethist<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">05-05-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">3.5<\/td><td data-field=\"afm1\" data-type=\"text\">9.0<\/td><td data-field=\"afm2\" data-type=\"text\">7.0<\/td><td data-field=\"afm3\" data-type=\"text\">4.65<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\">gesplitst<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\">3.0000<\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4867\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4867\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">2<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">22.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">22.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"7\">Edelstenen paren<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"27\">Amethist<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">05-05-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">3.3<\/td><td data-field=\"afm1\" data-type=\"text\">8.7<\/td><td data-field=\"afm2\" data-type=\"text\">6.9<\/td><td data-field=\"afm3\" data-type=\"text\">4.5<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\">gesplitst<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\">3.0000<\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4868\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4868\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">2<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">22.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">22.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"7\">Edelstenen paren<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"27\">Amethist<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">05-05-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">3.25<\/td><td data-field=\"afm1\" data-type=\"text\">9.0<\/td><td data-field=\"afm2\" data-type=\"text\">6.9<\/td><td data-field=\"afm3\" data-type=\"text\">4.5<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\">gesplitst<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\">3.0000<\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4869\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4869\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">78.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">78.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"16\">Aquamarijn<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">05-05-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">1.85<\/td><td data-field=\"afm1\" data-type=\"text\">7.6<\/td><td data-field=\"afm2\" data-type=\"text\">7.2<\/td><td data-field=\"afm3\" data-type=\"text\">4.9<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\">gesplitst<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\">3.5000<\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4870\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4870\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">80.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">80.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"16\">Aquamarijn<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">05-05-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">2.0<\/td><td data-field=\"afm1\" data-type=\"text\">8.6<\/td><td data-field=\"afm2\" data-type=\"text\">6.6<\/td><td data-field=\"afm3\" data-type=\"text\">4.0<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\">gesplitst<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\">3.5000<\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4871\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"0\"><span class=\"row-menu-trigger\" data-id=\"4871\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\"><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">19.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">19.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"57\">Prasioliet<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">15-09-2019<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">2.6<\/td><td data-field=\"afm1\" data-type=\"text\">9.88<\/td><td data-field=\"afm2\" data-type=\"text\">7.82<\/td><td data-field=\"afm3\" data-type=\"text\">4.7<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\">coll<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4872\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4872\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">62.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">62.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"27\">Amethist<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">05-05-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">13.2<\/td><td data-field=\"afm1\" data-type=\"text\">1605.0<\/td><td data-field=\"afm2\" data-type=\"text\">13.03<\/td><td data-field=\"afm3\" data-type=\"text\">9.64<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\"><\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4873\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4873\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">34.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">34.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"5\">Citrien<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">05-05-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">5.95<\/td><td data-field=\"afm1\" data-type=\"text\">13.96<\/td><td data-field=\"afm2\" data-type=\"text\">10.01<\/td><td data-field=\"afm3\" data-type=\"text\">7.36<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\">coll<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4874\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4874\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">60.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">60.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"36\">Labradoriet<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">05-05-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">14.95<\/td><td data-field=\"afm1\" data-type=\"text\">20.55<\/td><td data-field=\"afm2\" data-type=\"text\">16.34<\/td><td data-field=\"afm3\" data-type=\"text\">6.32<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\">coll<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4875\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4875\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">32.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">32.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"59\">Rook kwarts<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">05-05-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">4.35<\/td><td data-field=\"afm1\" data-type=\"text\">10.91<\/td><td data-field=\"afm2\" data-type=\"text\">9.06<\/td><td data-field=\"afm3\" data-type=\"text\">5.73<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\">coll<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4876\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4876\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">39.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">82.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"75\">Rutiel kwarts<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">05-05-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">7.2<\/td><td data-field=\"afm1\" data-type=\"text\">14.0<\/td><td data-field=\"afm2\" data-type=\"text\">14.0<\/td><td data-field=\"afm3\" data-type=\"text\">8.47<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\"><\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4877\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4877\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">275.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">275.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"1\">Topaas<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">04-01-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">17.65<\/td><td data-field=\"afm1\" data-type=\"text\">18.0<\/td><td data-field=\"afm2\" data-type=\"text\">13.0<\/td><td data-field=\"afm3\" data-type=\"text\"><\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\">??<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\">90.0000<\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4878\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4878\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">159.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">142.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"16\">Aquamarijn<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">05-05-2019<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">6.1<\/td><td data-field=\"afm1\" data-type=\"text\">16.7<\/td><td data-field=\"afm2\" data-type=\"text\">9.1<\/td><td data-field=\"afm3\" data-type=\"text\">5.6<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\">whatsapp<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4879\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4879\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">179.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">162.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"16\">Aquamarijn<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">05-05-2019<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">7.5<\/td><td data-field=\"afm1\" data-type=\"text\">16.8<\/td><td data-field=\"afm2\" data-type=\"text\">10.5<\/td><td data-field=\"afm3\" data-type=\"text\">5.9<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\">whatsapp<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4880\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"0\"><span class=\"row-menu-trigger\" data-id=\"4880\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\"><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">156.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">156.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"16\">Aquamarijn<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">05-05-2019<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">7.1<\/td><td data-field=\"afm1\" data-type=\"text\">15.6<\/td><td data-field=\"afm2\" data-type=\"text\">11.1<\/td><td data-field=\"afm3\" data-type=\"text\">6.2<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\">whatsapp<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4881\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4881\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">187.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">187.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"34\">Opaal<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">05-05-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">7.49<\/td><td data-field=\"afm1\" data-type=\"text\">15.41<\/td><td data-field=\"afm2\" data-type=\"text\">15.41<\/td><td data-field=\"afm3\" data-type=\"text\">6.06<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\">ebay<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4882\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4882\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">43.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">43.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"151\">Mystic kwarts<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">05-05-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">5.85<\/td><td data-field=\"afm1\" data-type=\"text\">14.36<\/td><td data-field=\"afm2\" data-type=\"text\">10.32<\/td><td data-field=\"afm3\" data-type=\"text\">6.92<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\">coll<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4883\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4883\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">153.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">153.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"34\">Opaal<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">05-05-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">6.13<\/td><td data-field=\"afm1\" data-type=\"text\">14.3<\/td><td data-field=\"afm2\" data-type=\"text\">12.77<\/td><td data-field=\"afm3\" data-type=\"text\">5.87<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\">ebay<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\">Mexico<\/td><\/tr><tr class=\"listrow\" data-id=\"4884\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4884\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">89.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">141.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"75\">Rutiel kwarts<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">05-05-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">13.1<\/td><td data-field=\"afm1\" data-type=\"text\">21.32<\/td><td data-field=\"afm2\" data-type=\"text\">11.6<\/td><td data-field=\"afm3\" data-type=\"text\">8.5<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\"><\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4885\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"0\"><span class=\"row-menu-trigger\" data-id=\"4885\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\"><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">12.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">156.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"26\">Andesine (Zonnesteen)<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">17-11-2019<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">12.19<\/td><td data-field=\"afm1\" data-type=\"text\">19.0<\/td><td data-field=\"afm2\" data-type=\"text\">15.0<\/td><td data-field=\"afm3\" data-type=\"text\">6.0<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\">collectie<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4886\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"0\"><span class=\"row-menu-trigger\" data-id=\"4886\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\"><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">35.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">35.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"9\">Spodumene<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">24-12-2019<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">7.0<\/td><td data-field=\"afm1\" data-type=\"text\">13.0<\/td><td data-field=\"afm2\" data-type=\"text\">10.0<\/td><td data-field=\"afm3\" data-type=\"text\">3.0<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\">??<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\">9.0000<\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4887\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4887\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">99.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">171.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"28\">Fluoriet<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">05-05-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">23.49<\/td><td data-field=\"afm1\" data-type=\"text\">19.6<\/td><td data-field=\"afm2\" data-type=\"text\">13.02<\/td><td data-field=\"afm3\" data-type=\"text\">9.57<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\">ebay<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4888\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4888\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">159.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">404.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"34\">Opaal<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">05-05-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">16.14<\/td><td data-field=\"afm1\" data-type=\"text\">19.04<\/td><td data-field=\"afm2\" data-type=\"text\">17.3<\/td><td data-field=\"afm3\" data-type=\"text\">8.95<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\">eb<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4889\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4889\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">19.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">9.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"60\">Bruciet<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">06-05-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">4.4<\/td><td data-field=\"afm1\" data-type=\"text\">10.8<\/td><td data-field=\"afm2\" data-type=\"text\">9.7<\/td><td data-field=\"afm3\" data-type=\"text\">5.9<\/td><td data-field=\"inkoopNr\" data-type=\"text\">2238439737<\/td><td data-field=\"InkoopTitel\" data-type=\"text\"><\/td><td data-field=\"inkoopPrijs\" data-type=\"text\">10.0000<\/td><td data-field=\"inkoopUrl\" data-type=\"text\">https:\/\/www.ebay.nl\/itm\/ESTATE-LOT-GEM-COLLECTION-SALE-RARE-VALUABLE-DIAMONDS-RUBIES-EMERALD-TREASURES-\/223843973742<\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\">PAKISTAN<\/td><\/tr><tr class=\"listrow\" data-id=\"4890\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4890\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">99.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">780.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"136\">Sphaleriet<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">29-04-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bZeldzaam\" checked><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">6.25<\/td><td data-field=\"afm1\" data-type=\"text\">11.5<\/td><td data-field=\"afm2\" data-type=\"text\">9.6<\/td><td data-field=\"afm3\" data-type=\"text\">6.8<\/td><td data-field=\"inkoopNr\" data-type=\"text\">2238397092<\/td><td data-field=\"InkoopTitel\" data-type=\"text\">ebay<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\">10.0000<\/td><td data-field=\"inkoopUrl\" data-type=\"text\">https:\/\/www.ebay.nl\/itm\/RARE-GEMS-STONE-COLLECTION-NATURAL-ORANGE-COLOR-SPAIN-SPHALERITE-6-25-CT-OVAL\/223839709283<\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4891\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4891\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">60.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">60.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"7\">Edelstenen paren<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"35\">Morganiet<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">07-05-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">1.5<\/td><td data-field=\"afm1\" data-type=\"text\">7.08<\/td><td data-field=\"afm2\" data-type=\"text\">5.12<\/td><td data-field=\"afm3\" data-type=\"text\">3.16<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\">ebay<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4892\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4892\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">9.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">9.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"7\">Edelstenen paren<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"36\">Labradoriet<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">07-05-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">0.95<\/td><td data-field=\"afm1\" data-type=\"text\">5.0<\/td><td data-field=\"afm2\" data-type=\"text\">5.0<\/td><td data-field=\"afm3\" data-type=\"text\">3.4<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\">coll<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4893\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"0\"><span class=\"row-menu-trigger\" data-id=\"4893\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\"><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bCheckedSecat\" checked><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">2<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">78.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">48.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"7\">Edelstenen paren<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"53\">Alexandriet<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">07-05-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bZeldzaam\" checked><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">0.24<\/td><td data-field=\"afm1\" data-type=\"text\">2.87<\/td><td data-field=\"afm2\" data-type=\"text\">2.87<\/td><td data-field=\"afm3\" data-type=\"text\">1.93<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\"><\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4894\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4894\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">136.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">136.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"68\">Chroom diopsied<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">03-06-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">5.81<\/td><td data-field=\"afm1\" data-type=\"text\">12.11<\/td><td data-field=\"afm2\" data-type=\"text\">10.1<\/td><td data-field=\"afm3\" data-type=\"text\">8.25<\/td><td data-field=\"inkoopNr\" data-type=\"text\">ebay<\/td><td data-field=\"InkoopTitel\" data-type=\"text\"><\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4896\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4896\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">303.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">303.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"84\">Kleurveranderende granaat<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">03-06-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">3.19<\/td><td data-field=\"afm1\" data-type=\"text\">9.8<\/td><td data-field=\"afm2\" data-type=\"text\">6.79<\/td><td data-field=\"afm3\" data-type=\"text\">5.0<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\">ebay<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4897\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4897\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">4<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">130.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">75.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"3\">Partijen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"8\">Spessartien granaat<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">08-09-2019<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">2.6<\/td><td data-field=\"afm1\" data-type=\"text\">7.0<\/td><td data-field=\"afm2\" data-type=\"text\">5.0<\/td><td data-field=\"afm3\" data-type=\"text\">3.0<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\">coll<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4898\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4898\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">2<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">30.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">30.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"33\">Peridoot<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">03-06-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">1.7<\/td><td data-field=\"afm1\" data-type=\"text\">6.14<\/td><td data-field=\"afm2\" data-type=\"text\">6.14<\/td><td data-field=\"afm3\" data-type=\"text\">3.86<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\">coll<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4899\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4899\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">54.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">54.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"3\">Spinel<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">03-06-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">1.18<\/td><td data-field=\"afm1\" data-type=\"text\">7.02<\/td><td data-field=\"afm2\" data-type=\"text\">5.08<\/td><td data-field=\"afm3\" data-type=\"text\">4.15<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\"><\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4900\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4900\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">49.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">49.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"3\">Spinel<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">03-06-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">0.96<\/td><td data-field=\"afm1\" data-type=\"text\">5.43<\/td><td data-field=\"afm2\" data-type=\"text\">5.25<\/td><td data-field=\"afm3\" data-type=\"text\">3.86<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\">ebay<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4901\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4901\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">131.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">131.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"16\">Aquamarijn<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">03-06-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">5.4<\/td><td data-field=\"afm1\" data-type=\"text\">15.0<\/td><td data-field=\"afm2\" data-type=\"text\">9.9<\/td><td data-field=\"afm3\" data-type=\"text\">5.79<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\">coll<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4902\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4902\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">46.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">46.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"27\">Amethist<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">03-06-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">9.36<\/td><td data-field=\"afm1\" data-type=\"text\">14.9<\/td><td data-field=\"afm2\" data-type=\"text\">13.1<\/td><td data-field=\"afm3\" data-type=\"text\">9.0<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\"><\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4903\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4903\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">29.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">22.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"27\">Amethist<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\"><\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">3.25<\/td><td data-field=\"afm1\" data-type=\"text\">10.08<\/td><td data-field=\"afm2\" data-type=\"text\">10.08<\/td><td data-field=\"afm3\" data-type=\"text\">6.2<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\">coll<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4904\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4904\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bGecontroleerd\"><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">21.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">21.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"27\">Amethist<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">15-09-2019<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">3.1<\/td><td data-field=\"afm1\" data-type=\"text\">9.98<\/td><td data-field=\"afm2\" data-type=\"text\">8.05<\/td><td data-field=\"afm3\" data-type=\"text\">5.32<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\">coll<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4905\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4905\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">233.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">233.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"34\">Opaal<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">04-06-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">9.3<\/td><td data-field=\"afm1\" data-type=\"text\">16.55<\/td><td data-field=\"afm2\" data-type=\"text\">11.05<\/td><td data-field=\"afm3\" data-type=\"text\">10.58<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\">eb<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4906\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4906\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">70.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">70.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"28\">Fluoriet<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">18-09-2019<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">8.99<\/td><td data-field=\"afm1\" data-type=\"text\">16.18<\/td><td data-field=\"afm2\" data-type=\"text\">8.39<\/td><td data-field=\"afm3\" data-type=\"text\">7.43<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\">ebay<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4907\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4907\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">21.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">21.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"57\">Prasioliet<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">04-06-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">3.75<\/td><td data-field=\"afm1\" data-type=\"text\">14.91<\/td><td data-field=\"afm2\" data-type=\"text\">9.6<\/td><td data-field=\"afm3\" data-type=\"text\">5.26<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\">coll<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4908\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4908\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">55.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">55.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"80\">Grossulair granaat<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">04-06-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">8.8<\/td><td data-field=\"afm1\" data-type=\"text\">15.28<\/td><td data-field=\"afm2\" data-type=\"text\">12.24<\/td><td data-field=\"afm3\" data-type=\"text\">5.76<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\"><\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4909\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4909\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">52.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">52.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"59\">Rook kwarts<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">04-06-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">8.3<\/td><td data-field=\"afm1\" data-type=\"text\">13.45<\/td><td data-field=\"afm2\" data-type=\"text\">9.95<\/td><td data-field=\"afm3\" data-type=\"text\">7.65<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\">coll<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4910\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"0\"><span class=\"row-menu-trigger\" data-id=\"4910\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\"><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">53.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">53.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"27\">Amethist<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">04-06-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">10.93<\/td><td data-field=\"afm1\" data-type=\"text\">16.19<\/td><td data-field=\"afm2\" data-type=\"text\">12.43<\/td><td data-field=\"afm3\" data-type=\"text\">8.74<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\">ebay<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\">6.5000<\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4911\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4911\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">285.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">1285.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"1\">Topaas<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">04-06-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">85.0<\/td><td data-field=\"afm1\" data-type=\"text\">25.0<\/td><td data-field=\"afm2\" data-type=\"text\">24.0<\/td><td data-field=\"afm3\" data-type=\"text\">17.0<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\">ebay<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\">97.0000<\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4912\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"0\"><span class=\"row-menu-trigger\" data-id=\"4912\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\"><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bGecontroleerd\"><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\"><\/td><td data-field=\"BerekendPrijs\" data-type=\"text\"><\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"12\">Toermalijn<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">18-06-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\"><\/td><td data-field=\"afm1\" data-type=\"text\">7.74<\/td><td data-field=\"afm2\" data-type=\"text\">4.51<\/td><td data-field=\"afm3\" data-type=\"text\">2.64<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\">whatsapp<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4913\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4913\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">2<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\"><\/td><td data-field=\"BerekendPrijs\" data-type=\"text\"><\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"7\">Edelstenen paren<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"151\">Mystic kwarts<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">18-06-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\"><\/td><td data-field=\"afm1\" data-type=\"text\">7.0<\/td><td data-field=\"afm2\" data-type=\"text\">5.0<\/td><td data-field=\"afm3\" data-type=\"text\">2.86<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\">coll<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4914\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"0\"><span class=\"row-menu-trigger\" data-id=\"4914\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\"><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">3<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">24.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">24.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"3\">Partijen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"138\">Almandine granaat<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">18-06-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">1.35<\/td><td data-field=\"afm1\" data-type=\"text\"><\/td><td data-field=\"afm2\" data-type=\"text\"><\/td><td data-field=\"afm3\" data-type=\"text\"><\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\">coll<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4915\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"0\"><span class=\"row-menu-trigger\" data-id=\"4915\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\"><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">2<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">34.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">34.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"7\">Edelstenen paren<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"114\">Vuuropaal<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">18-06-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">1.35<\/td><td data-field=\"afm1\" data-type=\"text\">6.37<\/td><td data-field=\"afm2\" data-type=\"text\">6.35<\/td><td data-field=\"afm3\" data-type=\"text\">4.35<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\">ebay<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4916\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4916\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">2<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">26.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">26.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"7\">Edelstenen paren<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"26\">Andesine (Zonnesteen)<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">18-06-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">1.37<\/td><td data-field=\"afm1\" data-type=\"text\">10.02<\/td><td data-field=\"afm2\" data-type=\"text\">8.62<\/td><td data-field=\"afm3\" data-type=\"text\">3.95<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\">ebay<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4917\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4917\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">3<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">86.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">86.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"3\">Partijen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"56\">Tsavoriet<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">18-06-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">0.92<\/td><td data-field=\"afm1\" data-type=\"text\">5.0<\/td><td data-field=\"afm2\" data-type=\"text\">3.8<\/td><td data-field=\"afm3\" data-type=\"text\">1.84<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\"><\/td><td data-field=\"inkoopPrijs\" data-type=\"text\">8.0000<\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4918\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4918\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">3<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">9.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">9.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"3\">Partijen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"34\">Opaal<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">18-06-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">0.3<\/td><td data-field=\"afm1\" data-type=\"text\">6.0<\/td><td data-field=\"afm2\" data-type=\"text\">2.0<\/td><td data-field=\"afm3\" data-type=\"text\">1.0<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\">coll<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4919\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4919\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">2<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">29.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">29.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"7\">Edelstenen paren<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"1\">Topaas<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">18-06-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">1.25<\/td><td data-field=\"afm1\" data-type=\"text\">5.17<\/td><td data-field=\"afm2\" data-type=\"text\">5.17<\/td><td data-field=\"afm3\" data-type=\"text\">3.44<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\">coll<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4920\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4920\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">2<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">15.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">15.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"7\">Edelstenen paren<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"33\">Peridoot<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">18-06-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">0.65<\/td><td data-field=\"afm1\" data-type=\"text\">4.17<\/td><td data-field=\"afm2\" data-type=\"text\">4.17<\/td><td data-field=\"afm3\" data-type=\"text\">2.65<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\">coll<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4921\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4921\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">2<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">28.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">28.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"7\">Edelstenen paren<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"138\">Almandine granaat<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">18-06-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">1.75<\/td><td data-field=\"afm1\" data-type=\"text\">6.0<\/td><td data-field=\"afm2\" data-type=\"text\">6.0<\/td><td data-field=\"afm3\" data-type=\"text\">2.92<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\"><\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4922\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4922\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">19.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">19.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"7\">Edelstenen paren<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"138\">Almandine granaat<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">18-06-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">0.7<\/td><td data-field=\"afm1\" data-type=\"text\">4.0<\/td><td data-field=\"afm2\" data-type=\"text\">4.0<\/td><td data-field=\"afm3\" data-type=\"text\">2.5<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\"><\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4923\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4923\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bCheckedSecat\" checked><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">282.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">282.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"32\">Robijn<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">22-06-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">1.5<\/td><td data-field=\"afm1\" data-type=\"text\">10.0<\/td><td data-field=\"afm2\" data-type=\"text\">4.7<\/td><td data-field=\"afm3\" data-type=\"text\">3.5<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\"><\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4924\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4924\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bCheckedSecat\" checked><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">282.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">282.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"32\">Robijn<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">22-06-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">1.5<\/td><td data-field=\"afm1\" data-type=\"text\">7.67<\/td><td data-field=\"afm2\" data-type=\"text\">5.81<\/td><td data-field=\"afm3\" data-type=\"text\">3.74<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\"><\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4925\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"0\"><span class=\"row-menu-trigger\" data-id=\"4925\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\"><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bCheckedSecat\" checked><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">195.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">195.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"32\">Robijn<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">22-06-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">1.0<\/td><td data-field=\"afm1\" data-type=\"text\">7.0<\/td><td data-field=\"afm2\" data-type=\"text\">5.1<\/td><td data-field=\"afm3\" data-type=\"text\">3.0<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\">coll<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4926\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4926\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bCheckedSecat\" checked><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">256.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">256.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"32\">Robijn<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">22-06-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">1.35<\/td><td data-field=\"afm1\" data-type=\"text\">7.1<\/td><td data-field=\"afm2\" data-type=\"text\">5.15<\/td><td data-field=\"afm3\" data-type=\"text\">3.55<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\">coll<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4927\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"0\"><span class=\"row-menu-trigger\" data-id=\"4927\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\"><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bCheckedSecat\" checked><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">114.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">114.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"32\">Robijn<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">22-06-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">0.54<\/td><td data-field=\"afm1\" data-type=\"text\">5.8<\/td><td data-field=\"afm2\" data-type=\"text\">3.95<\/td><td data-field=\"afm3\" data-type=\"text\">3.15<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\">coll<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4928\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4928\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bCheckedSecat\" checked><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">108.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">108.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"32\">Robijn<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">22-06-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">0.5<\/td><td data-field=\"afm1\" data-type=\"text\">6.0<\/td><td data-field=\"afm2\" data-type=\"text\">4.08<\/td><td data-field=\"afm3\" data-type=\"text\">2.05<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\">coll<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4929\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4929\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bGecontroleerd\"><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">37.5000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\"><\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"8\">Certificaten<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"162\">Certificaat<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">26-06-2020<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\"><\/td><td data-field=\"afm1\" data-type=\"text\"><\/td><td data-field=\"afm2\" data-type=\"text\"><\/td><td data-field=\"afm3\" data-type=\"text\"><\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\"><\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4930\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4930\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">24.5000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">46.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"163\">Lapis Lazuli<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">12-11-2023<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">15.2<\/td><td data-field=\"afm1\" data-type=\"text\">19.0<\/td><td data-field=\"afm2\" data-type=\"text\">14.6<\/td><td data-field=\"afm3\" data-type=\"text\">5.2<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\"><\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4931\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4931\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">18.7500<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">32.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"163\">Lapis Lazuli<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">12-11-2023<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">10.8<\/td><td data-field=\"afm1\" data-type=\"text\">15.2<\/td><td data-field=\"afm2\" data-type=\"text\">21.9<\/td><td data-field=\"afm3\" data-type=\"text\">3.8<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\"><\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4932\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4932\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">14.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">16.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"137\">Tijgeroog<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">12-11-2023<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">3.8<\/td><td data-field=\"afm1\" data-type=\"text\">9.7<\/td><td data-field=\"afm2\" data-type=\"text\">9.7<\/td><td data-field=\"afm3\" data-type=\"text\">4.9<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\"><\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4933\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4933\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">17.5000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">19.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"137\">Tijgeroog<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">12-11-2023<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">4.75<\/td><td data-field=\"afm1\" data-type=\"text\">10.2<\/td><td data-field=\"afm2\" data-type=\"text\">10.2<\/td><td data-field=\"afm3\" data-type=\"text\">5.4<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\"><\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4934\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4934\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">15.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">23.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"164\">Charoiet<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">12-11-2023<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">5.7<\/td><td data-field=\"afm1\" data-type=\"text\">14.3<\/td><td data-field=\"afm2\" data-type=\"text\">10.1<\/td><td data-field=\"afm3\" data-type=\"text\">5.7<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\"><\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4935\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4935\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">14.5000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">23.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"164\">Charoiet<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">12-11-2023<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">4.8<\/td><td data-field=\"afm1\" data-type=\"text\">14.3<\/td><td data-field=\"afm2\" data-type=\"text\">10.1<\/td><td data-field=\"afm3\" data-type=\"text\">4.7<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\"><\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4936\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4936\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">15.5000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">23.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"164\">Charoiet<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">12-11-2023<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">5.75<\/td><td data-field=\"afm1\" data-type=\"text\">14.2<\/td><td data-field=\"afm2\" data-type=\"text\">10.0<\/td><td data-field=\"afm3\" data-type=\"text\">5.3<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\"><\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4937\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4937\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">15.5000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">23.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"164\">Charoiet<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">12-11-2023<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">5.85<\/td><td data-field=\"afm1\" data-type=\"text\">14.3<\/td><td data-field=\"afm2\" data-type=\"text\">10.0<\/td><td data-field=\"afm3\" data-type=\"text\">5.3<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\"><\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4938\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"0\"><span class=\"row-menu-trigger\" data-id=\"4938\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\"><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\"><\/td><td data-field=\"BerekendPrijs\" data-type=\"text\"><\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"8\">Certificaten<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"162\">Certificaat<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">01-12-2023<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\"><\/td><td data-field=\"afm1\" data-type=\"text\">0.0<\/td><td data-field=\"afm2\" data-type=\"text\">0.0<\/td><td data-field=\"afm3\" data-type=\"text\">0.0<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\">Korting<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4942\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4942\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">256.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">256.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"16\">Aquamarijn<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">14-01-2024<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">13.7<\/td><td data-field=\"afm1\" data-type=\"text\">7.7<\/td><td data-field=\"afm2\" data-type=\"text\">7.0<\/td><td data-field=\"afm3\" data-type=\"text\">4.9<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\"><\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4943\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4943\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">269.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">269.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"16\">Aquamarijn<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">14-01-2024<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">14.6<\/td><td data-field=\"afm1\" data-type=\"text\">8.0<\/td><td data-field=\"afm2\" data-type=\"text\">6.2<\/td><td data-field=\"afm3\" data-type=\"text\">5.2<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\"><\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4944\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4944\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">270.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">270.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"16\">Aquamarijn<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">14-01-2024<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">14.65<\/td><td data-field=\"afm1\" data-type=\"text\">8.0<\/td><td data-field=\"afm2\" data-type=\"text\">6.2<\/td><td data-field=\"afm3\" data-type=\"text\">5.2<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\"><\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4945\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4945\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">264.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">264.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"16\">Aquamarijn<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">14-01-2024<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">14.3<\/td><td data-field=\"afm1\" data-type=\"text\">7.3<\/td><td data-field=\"afm2\" data-type=\"text\">6.6<\/td><td data-field=\"afm3\" data-type=\"text\">5.43<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\"><\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4946\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4946\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">318.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">318.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"16\">Aquamarijn<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">14-01-2024<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">17.9<\/td><td data-field=\"afm1\" data-type=\"text\">9.7<\/td><td data-field=\"afm2\" data-type=\"text\">7.6<\/td><td data-field=\"afm3\" data-type=\"text\">5.2<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\"><\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4947\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4947\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">9.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">9.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"48\">Turquoise<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">14-01-2024<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">6.15<\/td><td data-field=\"afm1\" data-type=\"text\">8.6<\/td><td data-field=\"afm2\" data-type=\"text\">5.2<\/td><td data-field=\"afm3\" data-type=\"text\">2.2<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\"><\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4948\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4948\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">9.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">9.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"48\">Turquoise<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">14-01-2024<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">3.35<\/td><td data-field=\"afm1\" data-type=\"text\">7.0<\/td><td data-field=\"afm2\" data-type=\"text\">5.1<\/td><td data-field=\"afm3\" data-type=\"text\">1.7<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\"><\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4949\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4949\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">9.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">9.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"48\">Turquoise<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">14-01-2024<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">5.2<\/td><td data-field=\"afm1\" data-type=\"text\">7.6<\/td><td data-field=\"afm2\" data-type=\"text\">5.7<\/td><td data-field=\"afm3\" data-type=\"text\">1.9<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\"><\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4950\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4950\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">9.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">9.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"48\">Turquoise<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">14-01-2024<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">6.0<\/td><td data-field=\"afm1\" data-type=\"text\">7.3<\/td><td data-field=\"afm2\" data-type=\"text\">5.6<\/td><td data-field=\"afm3\" data-type=\"text\">2.2<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\"><\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4951\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4951\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">86.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">86.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"19\">Saffier<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">14-01-2024<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">0.9<\/td><td data-field=\"afm1\" data-type=\"text\">3.22<\/td><td data-field=\"afm2\" data-type=\"text\">2.6<\/td><td data-field=\"afm3\" data-type=\"text\">1.61<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\"><\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4952\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4952\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">82.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">82.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"19\">Saffier<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">14-01-2024<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">0.8<\/td><td data-field=\"afm1\" data-type=\"text\">2.83<\/td><td data-field=\"afm2\" data-type=\"text\">2.4<\/td><td data-field=\"afm3\" data-type=\"text\">1.7<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\"><\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4953\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4953\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">2<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">40.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">40.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"7\">Edelstenen paren<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"1\">Topaas<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">14-01-2024<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">2.0<\/td><td data-field=\"afm1\" data-type=\"text\">3.47<\/td><td data-field=\"afm2\" data-type=\"text\">2.44<\/td><td data-field=\"afm3\" data-type=\"text\">1.74<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\">1<\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4954\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4954\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">125.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">145.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"12\">Toermalijn<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">14-01-2024<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">4.18<\/td><td data-field=\"afm1\" data-type=\"text\">12.4<\/td><td data-field=\"afm2\" data-type=\"text\">9.6<\/td><td data-field=\"afm3\" data-type=\"text\">5.5<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\"><\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4955\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"0\"><span class=\"row-menu-trigger\" data-id=\"4955\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\"><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">2<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">19.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">19.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"7\">Edelstenen paren<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"5\">Citrien<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">14-01-2024<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">2.1<\/td><td data-field=\"afm1\" data-type=\"text\">7.9<\/td><td data-field=\"afm2\" data-type=\"text\">6.0<\/td><td data-field=\"afm3\" data-type=\"text\">3.5<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\"><\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4956\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4956\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">20.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">20.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"61\">Carneool<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">14-01-2024<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">3.9<\/td><td data-field=\"afm1\" data-type=\"text\">14.1<\/td><td data-field=\"afm2\" data-type=\"text\">10.0<\/td><td data-field=\"afm3\" data-type=\"text\">4.5<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\"><\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4957\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4957\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">19.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">19.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"25\">Calcedoon<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">14-01-2024<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">5.7<\/td><td data-field=\"afm1\" data-type=\"text\">11.2<\/td><td data-field=\"afm2\" data-type=\"text\">11.2<\/td><td data-field=\"afm3\" data-type=\"text\">5.6<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\"><\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4958\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4958\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">21.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">23.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"25\">Calcedoon<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">14-01-2024<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">7.45<\/td><td data-field=\"afm1\" data-type=\"text\">11.3<\/td><td data-field=\"afm2\" data-type=\"text\">11.3<\/td><td data-field=\"afm3\" data-type=\"text\">6.6<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\"><\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4959\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4959\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">19.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">21.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"25\">Calcedoon<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">14-01-2024<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">6.3<\/td><td data-field=\"afm1\" data-type=\"text\">11.9<\/td><td data-field=\"afm2\" data-type=\"text\">11.9<\/td><td data-field=\"afm3\" data-type=\"text\">5.7<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\"><\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4960\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4960\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">26.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">26.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"61\">Carneool<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">14-01-2024<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">5.2<\/td><td data-field=\"afm1\" data-type=\"text\">10.0<\/td><td data-field=\"afm2\" data-type=\"text\">10.0<\/td><td data-field=\"afm3\" data-type=\"text\">6.5<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\"><\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4961\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"0\"><span class=\"row-menu-trigger\" data-id=\"4961\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\"><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">20.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">20.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"61\">Carneool<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">14-01-2024<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">4.1<\/td><td data-field=\"afm1\" data-type=\"text\">10.1<\/td><td data-field=\"afm2\" data-type=\"text\">10.1<\/td><td data-field=\"afm3\" data-type=\"text\">5.5<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\"><\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4962\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4962\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">19.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">19.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"20\">Prehniet<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">14-01-2024<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">2.75<\/td><td data-field=\"afm1\" data-type=\"text\">8.49<\/td><td data-field=\"afm2\" data-type=\"text\">7.49<\/td><td data-field=\"afm3\" data-type=\"text\">5.71<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\"><\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4963\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4963\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">21.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">21.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"20\">Prehniet<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">14-01-2024<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">3.05<\/td><td data-field=\"afm1\" data-type=\"text\">9.28<\/td><td data-field=\"afm2\" data-type=\"text\">8.45<\/td><td data-field=\"afm3\" data-type=\"text\">5.0<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\"><\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4964\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4964\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">84.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">84.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"19\">Saffier<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">14-01-2024<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">0.85<\/td><td data-field=\"afm1\" data-type=\"text\">3.26<\/td><td data-field=\"afm2\" data-type=\"text\">2.68<\/td><td data-field=\"afm3\" data-type=\"text\">1.41<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\"><\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4965\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4965\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">11.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">14.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"137\">Tijgeroog<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">12-11-2023<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">2.5<\/td><td data-field=\"afm1\" data-type=\"text\">9.6<\/td><td data-field=\"afm2\" data-type=\"text\">9.6<\/td><td data-field=\"afm3\" data-type=\"text\">3.8<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\"><\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4966\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4966\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">12.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\">19.0<\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"36\">Labradoriet<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">08-09-2019<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\">4.7<\/td><td data-field=\"afm1\" data-type=\"text\">10.9<\/td><td data-field=\"afm2\" data-type=\"text\">10.9<\/td><td data-field=\"afm3\" data-type=\"text\">5.6<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\"><\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4967\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4967\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">129.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\"><\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"53\">Alexandriet<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">14-01-2024<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\"><\/td><td data-field=\"afm1\" data-type=\"text\">0.0<\/td><td data-field=\"afm2\" data-type=\"text\">0.0<\/td><td data-field=\"afm3\" data-type=\"text\">0.0<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\"><\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr><tr class=\"listrow\" data-id=\"4968\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4968\">&#8942;<\/span><lib-switch data-field=\"bLeverbaar\" checked><\/lib-switch><\/td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked><\/lib-switch><\/td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"><\/lib-switch><\/td><td data-field=\"aantal\" data-type=\"text\">1<\/td><td data-field=\"voorraad\" data-type=\"text\">1<\/td><td data-field=\"Prijs\" data-type=\"text\">.0000<\/td><td data-field=\"BerekendPrijs\" data-type=\"text\"><\/td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"8\">Certificaten<\/td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"137\">Tijgeroog<\/td><td data-field=\"video\" data-type=\"text\"><\/td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"><\/lib-switch><\/td><td data-field=\"datestamp\" data-type=\"date\">26-03-2025<\/td><td data-field=\"fkCertificaat\" data-type=\"combobox\"><\/td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bZeldzaam\"><\/lib-switch><\/td><td data-field=\"Karaat\" data-type=\"text\"><\/td><td data-field=\"afm1\" data-type=\"text\">0.0<\/td><td data-field=\"afm2\" data-type=\"text\">0.0<\/td><td data-field=\"afm3\" data-type=\"text\">0.0<\/td><td data-field=\"inkoopNr\" data-type=\"text\"><\/td><td data-field=\"InkoopTitel\" data-type=\"text\"><\/td><td data-field=\"inkoopPrijs\" data-type=\"text\"><\/td><td data-field=\"inkoopUrl\" data-type=\"text\"><\/td><td data-field=\"inkoopVindplaats\" data-type=\"text\"><\/td><\/tr>",
>     "count": 159,
>     "displayMode": 2,
>     "hasGrouping": true,
>     "hasMore": false,
>     "lastId": "4968",
>     "pageSize": 200,
>     "totalCount": null
> }

> karaat composer install, but date is still not saved. We need to harden this. I values become empty that s a real concern

> i ran composer update stenversonline/platform

> date is now updated, only when i edit a form in popup mode, after closing the popup the value is not updated in the list mode of the kortingscodes

## 2026-06-19

> Versie 2.2.0: JavaScript error logging tabel aanmaken
>   1 wijziging(en) uit te voeren...
> ✗ Versie 2.2.0 MISLUKT: Script uitvoering mislukt (migrations/sql/2.2.0_javascript_errors.sql): SQLSTATE[42000]: Syntax error or access violation: -3551 [Microsoft][ODBC Microsoft Access Driver] Syntax error in CREATE TABLE statement. (SQLExecDirect[-3551] at ext\pdo_odbc\odbc_driver.c:246)
> ✗ Fout bij migratie versie 2.2.0: Script uitvoering mislukt (migrations/sql/2.2.0_javascript_errors.sql): SQLSTATE[42000]: Syntax error or access violation: -3551 [Microsoft][ODBC Microsoft Access Driver] Syntax error in CREATE TABLE statement. (SQLExecDirect[-3551] at ext\pdo_odbc\odbc_driver.c:246)

> yews please do

> yes please

> the image preview adds the domain name to it, but if an image already has http(s):// in it's name that should be skipped. Also the file selector should ignore the current filename if http(s):// is part of the name

> when using composer update i get another version number as mentioned in the profile-menu (v1.26.28), can we use the one in the profile-menu everywhere?

> okay, lat's leave it as it is

> push please

> yes push please

> please set BLOCKEDIT_TRACE = false

## 2026-06-20

> commit and push

## 2026-06-21

> if an image is uploaded in the file wizard, it is not automatically selected, this is crucial for UX

> in the search dialog of a form, comco boxes are not shown. Karaat: Soort steen is empty.

> scroll: lots of errors: installHook.js:1 [Infinite Scroll] DOM count mismatch! Tracked: 400, Actual DOM rows: 600. Difference: 200
>
> installHook.js:1 [Infinite Scroll] DOM count mismatch! Tracked: 600, Actual DOM rows: 800. Difference: 200
> installHook.js:1 [Infinite Scroll] DOM count mismatch! Tracked: 800, Actual DOM rows: 1000. Difference: 200
> installHook.js:1 [Infinite Scroll] DOM count mismatch! Tracked: 1000, Actual DOM rows: 1200. Difference: 200
> installHook.js:1 [Infinite Scroll] DOM count mismatch! Tracked: 1200, Actual DOM rows: 1400. Difference: 200
> installHook.js:1 [Infinite Scroll] DOM count mismatch! Tracked: 1400, Actual DOM rows: 1600. Difference: 200
> installHook.js:1 [Infinite Scroll] DOM count mismatch! Tracked: 1600, Actual DOM rows: 1800. Difference: 200
> installHook.js:1 [Infinite Scroll] DOM count mismatch! Tracked: 1800, Actual DOM rows: 2000. Difference: 200
> installHook.js:1 [Infinite Scroll] DOM count mismatch! Tracked: 2000, Actual DOM rows: 2200. Difference: 200
> installHook.js:1 [Infinite Scroll] DOM count mismatch! Tracked: 2200, Actual DOM rows: 2400. Difference: 200
> installHook.js:1 [Infinite Scroll] DOM count mismatch! Tracked: 2400, Actual DOM rows: 2600. Difference: 200
> installHook.js:1 [Infinite Scroll] DOM count mismatch! Tracked: 2600, Actual DOM rows: 2800. Difference: 200
> installHook.js:1 [Infinite Scroll] DOM count mismatch! Tracked: 2800, Actual DOM rows: 3000. Difference: 200
> installHook.js:1 [Infinite Scroll] DOM count mismatch! Tracked: 2961, Actual DOM rows: 3161. Difference: 200
> installHook.js:1 [Infinite Scroll] DOM count mismatch! Tracked: 3161, Actual DOM rows: 3322. Difference: 161
> ﻿

> Is the search for the tree the same as the search for the table view? In tree view I do get a filled combo box, in the table view i did not, make sure these are the same. 
>
> continious loading: the ID is unique, that is a good assumption.
>
> in the old CMA I could search for a numeric field and in the simple search field, now it only seems to search visible fields. Can you check?

> copy: yes use either
> that is the karaat form definition of a stone, the correct url is like : https://www.karaatedelstenen.nl/edelsteen/1957/preview.html 
> Combo: i need to see it, can you commit and push when ready?

> Vormen lijken niet meer te wijzigen, zorg dat dit bij de quick-edit aan de voorkant van Karaat te wijzigen is, maar voeg het veld ook toe aan de CMA definitie

> in the cma the beschrijving field of a stone hides when editing, is blnNewonly probably

> the btnViewList buttons on the file wizard should be right aligned in the path-bar

> first the Nieuw and theen the soort table search

> on a lib-combi the calculation of the position of combo-search is 2px too low, please re-evaluate that calculation

> {
>     "success": false,
>     "error": "controlId parameter is verplicht"
> }

> {
>     "success": false,
>     "error": "controlId parameter is verplicht"
> }

> C:\wwwroot\www.karaatedelstenen.nl>composer update
> Loading composer repositories with package information
> Updating dependencies
> Nothing to modify in lock file
> Installing dependencies from lock file (including require-dev)
> Nothing to install, update or remove
> Generating optimized autoload files
> 2 packages you are using are looking for funding.
> Use the `composer fund` command to find out more!
> > App\Library\Installer::postUpdate
> stenversonline/platform: syncing shared files...
>   - library/ synced
>   - cma/ synced
>   - synced deploy.php (root ops file)
>   - synced deploy_status.php (root ops file)
>   - web.config: Marker al aanwezig ÔÇö CMA-routes reeds toegepast.
>   - cleared cache/cma/minify (2 files)
>   - cleared cache/cma/forms (2 files)
>   - touched web.config (app-pool recycle ÔåÆ flushes OPcache/APCu)
> stenversonline/platform: sync complete

> the new caroussel shows only 2 stones, this is partly due to the missing default value of datum, and the link to the filter: it still shows the first sorting as default, not the correct one

> yes

> C:\wwwroot\www.karaatedelstenen.nl>  composer update stenversonline/platform --n
> o-cache -vvv
> Disabling cache usage
> Running 2.2.26 (2025-12-30 13:39:48) with PHP 8.5.6 on Windows NT / 6.3
> Reading ./composer.json (C:\wwwroot\www.karaatedelstenen.nl\composer.json)
> Loading config file ./composer.json (C:\wwwroot\www.karaatedelstenen.nl\composer
> .json)
> Checked CA file /etc/pki/ca-trust/extracted/pem/tls-ca-bundle.pem does not exist
>  or it is not a file.
> Checked directory /etc/pki/ca-trust/extracted/pem/tls-ca-bundle.pem does not exi
> st or it is not a directory.
> Checked CA file /etc/pki/tls/certs/ca-bundle.crt does not exist or it is not a f
> ile.
> Checked directory /etc/pki/tls/certs/ca-bundle.crt does not exist or it is not a
>  directory.
> Checked CA file /etc/ssl/certs/ca-certificates.crt does not exist or it is not a
>  file.
> Checked directory /etc/ssl/certs/ca-certificates.crt does not exist or it is not
>  a directory.
> Checked CA file /etc/ssl/ca-bundle.pem does not exist or it is not a file.
> Checked directory /etc/ssl/ca-bundle.pem does not exist or it is not a directory
> .
> Checked CA file /usr/local/share/certs/ca-root-nss.crt does not exist or it is n
> ot a file.
> Checked directory /usr/local/share/certs/ca-root-nss.crt does not exist or it is
>  not a directory.
> Checked CA file /usr/ssl/certs/ca-bundle.crt does not exist or it is not a file.
>
> Checked directory /usr/ssl/certs/ca-bundle.crt does not exist or it is not a dir
> ectory.
> Checked CA file /opt/local/share/curl/curl-ca-bundle.crt does not exist or it is
>  not a file.
> Checked directory /opt/local/share/curl/curl-ca-bundle.crt does not exist or it
> is not a directory.
> Checked CA file /usr/local/share/curl/curl-ca-bundle.crt does not exist or it is
>  not a file.
> Checked directory /usr/local/share/curl/curl-ca-bundle.crt does not exist or it
> is not a directory.
> Checked CA file /usr/share/ssl/certs/ca-bundle.crt does not exist or it is not a
>  file.
> Checked directory /usr/share/ssl/certs/ca-bundle.crt does not exist or it is not
>  a directory.
> Checked CA file /etc/ssl/cert.pem does not exist or it is not a file.
> Checked directory /etc/ssl/cert.pem does not exist or it is not a directory.
> Checked CA file /usr/local/etc/ssl/cert.pem does not exist or it is not a file.
> Checked directory /usr/local/etc/ssl/cert.pem does not exist or it is not a dire
> ctory.
> Checked CA file /usr/local/etc/openssl/cert.pem does not exist or it is not a fi
> le.
> Checked directory /usr/local/etc/openssl/cert.pem does not exist or it is not a
> directory.
> Checked CA file /usr/local/etc/openssl@1.1/cert.pem does not exist or it is not
> a file.
> Checked directory /usr/local/etc/openssl@1.1/cert.pem does not exist or it is no
> t a directory.
> Checked CA file /opt/homebrew/etc/openssl@3/cert.pem does not exist or it is not
>  a file.
> Checked directory /opt/homebrew/etc/openssl@3/cert.pem does not exist or it is n
> ot a directory.
> Checked CA file /opt/homebrew/etc/openssl@1.1/cert.pem does not exist or it is n
> ot a file.
> Checked directory /opt/homebrew/etc/openssl@1.1/cert.pem does not exist or it is
>  not a directory.
> Checked CA file /etc/pki/ca-trust/extracted/pem does not exist or it is not a fi
> le.
> Checked directory /etc/pki/ca-trust/extracted/pem does not exist or it is not a
> directory.
> Checked CA file /etc/pki/tls/certs does not exist or it is not a file.
> Checked directory /etc/pki/tls/certs does not exist or it is not a directory.
> Checked CA file /etc/ssl/certs does not exist or it is not a file.
> Checked directory /etc/ssl/certs does not exist or it is not a directory.
> Checked CA file /etc/ssl does not exist or it is not a file.
> Checked directory /etc/ssl does not exist or it is not a directory.
> Checked CA file /usr/local/share/certs does not exist or it is not a file.
> Checked directory /usr/local/share/certs does not exist or it is not a directory
> .
> Checked CA file /usr/ssl/certs does not exist or it is not a file.
> Checked directory /usr/ssl/certs does not exist or it is not a directory.
> Checked CA file /opt/local/share/curl does not exist or it is not a file.
> Checked directory /opt/local/share/curl does not exist or it is not a directory.
>
> Checked CA file /usr/local/share/curl does not exist or it is not a file.
> Checked directory /usr/local/share/curl does not exist or it is not a directory.
>
> Checked CA file /usr/share/ssl/certs does not exist or it is not a file.
> Checked directory /usr/share/ssl/certs does not exist or it is not a directory.
> Checked CA file /etc/ssl does not exist or it is not a file.
> Checked directory /etc/ssl does not exist or it is not a directory.
> Checked CA file /usr/local/etc/ssl does not exist or it is not a file.
> Checked directory /usr/local/etc/ssl does not exist or it is not a directory.
> Checked CA file /usr/local/etc/openssl does not exist or it is not a file.
> Checked directory /usr/local/etc/openssl does not exist or it is not a directory
> .
> Checked CA file /usr/local/etc/openssl@1.1 does not exist or it is not a file.
> Checked directory /usr/local/etc/openssl@1.1 does not exist or it is not a direc
> tory.
> Checked CA file /opt/homebrew/etc/openssl@3 does not exist or it is not a file.
> Checked directory /opt/homebrew/etc/openssl@3 does not exist or it is not a dire
> ctory.
> Checked CA file /opt/homebrew/etc/openssl@1.1 does not exist or it is not a file
> .
> Checked directory /opt/homebrew/etc/openssl@1.1 does not exist or it is not a di
> rectory.
> Checked CA file C:\Users\Administrator\AppData\Local\Temp\2\ope8BB4.tmp: valid
> Executing command (C:\wwwroot\www.karaatedelstenen.nl): git branch -a --no-color
>  --no-abbrev -v
> Failed to initialize global composer: Composer could not find the config file: C
> :\composer-home/composer.json
>
> Reading ./composer.lock (C:\wwwroot\www.karaatedelstenen.nl\composer.lock)
> Reading C:\wwwroot\www.karaatedelstenen.nl/vendor/composer/installed.json (C:\ww
> wroot\www.karaatedelstenen.nl\vendor\composer\installed.json)
> Loading composer repositories with package information
>
>
>   [RuntimeException]
>   GitDriver requires a usable cache directory, and it looks like you set it t
>   o be disabled
>
>
> Exception trace:
>  () at phar://C:/composer/composer.phar/src/Composer/Repository/Vcs/GitDriver.ph
> p:52
>  Composer\Repository\Vcs\GitDriver->initialize() at phar://C:/composer/composer.
> phar/src/Composer/Repository/Vcs/GitHubDriver.php:609
>  Composer\Repository\Vcs\GitHubDriver->setupGitDriver() at phar://C:/composer/co
> mposer.phar/src/Composer/Repository/Vcs/GitHubDriver.php:76
>  Composer\Repository\Vcs\GitHubDriver->initialize() at phar://C:/composer/compos
> er.phar/src/Composer/Repository/VcsRepository.php:149
>  Composer\Repository\VcsRepository->getDriver() at phar://C:/composer/composer.p
> har/src/Composer/Repository/VcsRepository.php:198
>  Composer\Repository\VcsRepository->initialize() at phar://C:/composer/composer.
> phar/src/Composer/Repository/ArrayRepository.php:311
>  Composer\Repository\ArrayRepository->getPackages() at phar://C:/composer/compos
> er.phar/src/Composer/Repository/ArrayRepository.php:62
>  Composer\Repository\ArrayRepository->loadPackages() at phar://C:/composer/compo
> ser.phar/src/Composer/DependencyResolver/PoolBuilder.php:379
>  Composer\DependencyResolver\PoolBuilder->loadPackagesMarkedForLoading() at phar
> ://C:/composer/composer.phar/src/Composer/DependencyResolver/PoolBuilder.php:234
>
>  Composer\DependencyResolver\PoolBuilder->buildPool() at phar://C:/composer/comp
> oser.phar/src/Composer/Repository/RepositorySet.php:261
>  Composer\Repository\RepositorySet->createPool() at phar://C:/composer/composer.
> phar/src/Composer/Installer.php:436
>  Composer\Installer->doUpdate() at phar://C:/composer/composer.phar/src/Composer
> /Installer.php:279
>  Composer\Installer->run() at phar://C:/composer/composer.phar/src/Composer/Comm
> and/UpdateCommand.php:248
>  Composer\Command\UpdateCommand->execute() at phar://C:/composer/composer.phar/v
> endor/symfony/console/Command/Command.php:245
>  Symfony\Component\Console\Command\Command->run() at phar://C:/composer/composer
> .phar/vendor/symfony/console/Application.php:835
>  Symfony\Component\Console\Application->doRunCommand() at phar://C:/composer/com
> poser.phar/vendor/symfony/console/Application.php:185
>  Symfony\Component\Console\Application->doRun() at phar://C:/composer/composer.p
> har/src/Composer/Console/Application.php:336
>  Composer\Console\Application->doRun() at phar://C:/composer/composer.phar/vendo
> r/symfony/console/Application.php:117
>  Symfony\Component\Console\Application->run() at phar://C:/composer/composer.pha
> r/src/Composer/Console/Application.php:131
>  Composer\Console\Application->run() at phar://C:/composer/composer.phar/bin/com
> poser:95
>  require() at C:\composer\composer.phar:29
>
> update [--with WITH] [--prefer-source] [--prefer-dist] [--prefer-install PREFER-
> INSTALL] [--dry-run] [--dev] [--no-dev] [--lock] [--no-install] [--no-autoloader
> ] [--no-suggest] [--no-progress] [-w|--with-dependencies] [-W|--with-all-depende
> ncies] [-v|vv|vvv|--verbose] [-o|--optimize-autoloader] [-a|--classmap-authorita
> tive] [--apcu-autoloader] [--apcu-autoloader-prefix APCU-AUTOLOADER-PREFIX] [--i
> gnore-platform-req IGNORE-PLATFORM-REQ] [--ignore-platform-reqs] [--prefer-stabl
> e] [--prefer-lowest] [-i|--interactive] [--root-reqs] [--] [<packages>]...

> i think the query editor also missed the databases.json migrtation, i now get the error : SQL foutCall to a member function setAttribute() on null on each call and the table list is empty

> that seems to have worked

> the table combo is still empty and therefor the fields

> and database structuur says: Database structuur | main
> Tabellen, kolommen en recordaantallen
> JSON
> XML
> TXT
> Fout: Database connection 'main' not configured in databases.json (data/databases.json or cma/config/databases.json — expected an entry named 'main').

> main should be data

> C:\wwwroot\www.karaatedelstenen.nl>git pull
> Already up to date.
>
> C:\wwwroot\www.karaatedelstenen.nl>git status
> On branch main
> Your branch is up to date with 'origin/main'.
>
> Changes not staged for commit:
>   (use "git add <file>..." to update what will be committed)
>   (use "git restore <file>..." to discard changes in working directory)
>         modified:   .platform-manifest.json
>         modified:   _bootstrap_constants.inc
>
> Untracked files:

> fixed, thanx!

> okay the corm and kleur now show, but are not saved correctly. The price has gone up from 9 to 90000 after a few saved, same with the sizes, they lost their delimiter. Vorm and kleur not saved at all.

> we HAVE to use a comma because saving does not work correctly so yes please

> and what abouw thee image editing? the old cma had a crude editing system, is that gone?

> the old cma had an image wizard next to the file wizard, we can integrate it but now it is gone. in the right pane of the file wizard place an edit icon , a crop icon and 2 rotate buttons and other easy editing buttons

> dashboard: Cache prestaties
> 0%
> Backend: apcu
> 0
> Hits
> 0
> Misses, skip that of hits if all is 0

> yes make the regular bittons as well

> any suggestions? the crop, does that respect ethe existing aspect ratio?

> fix the crop
> please

> no we will skip that one, any more idea’s , often used image transformations?

> autocrop sound great, can we have a setting for the remaining  percentage, bacause i do want some margin, default 10%

> and 5 sounds good but very subtle

> and the .responsive formats, are they regenerated after changes or uploads?

> yes do that too, if they are in use othereise archive them

## 2026-06-22

> is it pushed?

> .combo-display {    min-height: 32px;
>    remove the entire height property

> .combo-display { min-height32px; } .combo-tag { border-color: 1px solid var(--color-accent-hover) }

> in another discussion (about klei) you uncovered an issue we need to solve; file/directory case sensitivity. Do a thorough check on all file references whether they are in fact lower case or not

> no let's wait for a bit, this is just cosmetics and for future migrations

> lib_HTMLEditorInit : search for it again, if not found delete it and see if  lib_htmleditor.inc is needed. CMA/include/CKeditor.js : fix that too.

> the pctchecklist control is still not saving the selected values (seen in Karaat), this is a major issue, Look into this please

> path emptyness is an error, leave it as such. Push and commit everything if there is nothing more to do.

> see this checges: 
>
>
> ● Update(/mnt/c/repos/klei/app/library/Cache.php)
>   ⎿  Added 4 lines, removed 3 lines
>       845       * @param string $sql SQL query to execute if cache miss
>       846       * @return ColumnMajorArray|null Column-major array or null if no results
>       847       */
>       848 -    public static function retrieve(string $identifier, $connection, string $sql): ?ColumnMajorArray
>       848 +    public static function retrieve(string $identifier, $connection, string $sql): ColumnMajorArray
>       849      {
>       850          self::init();
>       851
>       852          // Check if caching is enabled
>       853          if (!self::$enabled) {
>       854              $results = self::executeQuery($connection, $sql);
>       855 -            return $results ? new ColumnMajorArray($results) : null;
>       855 +            return new ColumnMajorArray($results ?: []);
>       856          }
>       857
>       858          // Try to get from cache first
>      ...
>       873              return new ColumnMajorArray($results);
>       874          }
>       875
>       876 -        return null;
>       876 +        // No rows: empty countable ColumnMajorArray, never null (PHP8 count(null) fatal).
>       877 +        return new ColumnMajorArray([]);
>       878      }
>       879
>       880      /**
>
>
>
> pleas execute them in this codebase , bump the version and make sure it needs an update

> klei is updated, can you check?

> count(): Argument #1 ($value) must be of type Countable|array, null given
> in C:\wwwroot\klei.stenversonline.nl\views\homepage.inc on line 82

> can you change deploy.php so it runs composer clear-cache
> ! composer update stenversonline/platform using a special parameter? document that too

## 2026-06-23

> i don't want to have to edit server files, can we just - like i requested - add a parameter to the deploy.php , like ?Forcerefresh=Y

> i updated klei, so it should be there now

> i updated only the production server, i just updated /mnt/c/repos/klei as well , at least i tried and it threw this error: > App\Library\Installer::postUpdate
> stenversonline/platform: syncing shared files...
>   - removed (retired): cma/imageupload.php
>   - removed (retired): cma/imageupload_action.php
>   - removed (retired): cma/migrations/sql/2.2.0_javascript_errors.sql
>   - removed (retired): cma/migrations/migrations.json
>   - removed (retired): library/lib_htmleditor.inc
> Script App\Library\Installer::postUpdate handling the post-update-cmd event terminated with an exception
>
> In Installer.php line 454:
>
>   copy(C:\repos\klei/library/fonts\Linearicons\SVG\desktop.ini): Failed to open stream: Permission denied
>
>
> update [--with WITH] [--prefer-source] [--prefer-dist] [--prefer-install PREFER-INSTALL] [--dry-run] [--dev] [--no-dev] [--lock] [--no-install] [--no-audit] [--audit-format AUDIT-FORMAT] [--no-autoloader] [--no-suggest] [--no-progress] [-w|--with-dependencies] [-W|--with-all-dependencies] [-v|vv|vvv|--verbose] [-o|--optimize-autoloader] [-a|--classmap-authoritative] [--apcu-autoloader] [--apcu-autoloader-prefix APCU-AUTOLOADER-PREFIX] [--ignore-platform-req IGNORE-PLATFORM-REQ] [--ignore-platform-reqs] [--prefer-stable] [--prefer-lowest] [-m|--minimal-changes] [--patch-only] [-i|--interactive] [--root-reqs] [--bump-after-update [BUMP-AFTER-UPDATE]] [--] [<packages>...]

## 2026-06-24

> the html editor of the forms in cma had 2 custom elements, the insert Link and insert image, the insert link is changed into a non-styled popup that does noting and the button insert image is totally non-functional, I want the old code to be converted and made functional

## 2026-06-25

> can you push with a version bump?

> i updated Karaat , the cma behavious exactly the same?!

> version 1.27.6 was already deployed so i am afraiid you are wrong

> i now updated the local version

## 2026-06-26

> please go live, that is the real test, using forcerefresh

> try again please, i think i fixed the rights issue

> the DEPLOY_POST_HOOK i  weird, it does not show in the .env??

> yes

> pat rotated and path in karaat updated

> done

> karaat still has this error: ng(en) uit te voeren...
> ✗ Versie 9.5.0 MISLUKT: SQLSTATE[HYS11]: <<Unknown error>>: -1403 [Microsoft][ODBC Microsoft Access Driver] Table 'tblCMAMonitoring' already has an index named 'idx_CMAMonitoring_datestamp'. (SQLExecDirect[-1403] at ext\pdo_odbc\odbc_driver.c:246)
> ✗ Fout bij migratie versie 9.5.0: SQLSTATE[HYS11]: <<Unknown error>>: -1403 [Microsoft][ODBC Microsoft Access Driver] Table 'tblCMAMonitoring' already has an index named 'idx_CMAMonitoring_datestamp'. (SQLExecDirect[-1403] at ext\pdo_odbc\odbc_driver.c:246)

## 2026-06-27

> everything is up to date. A new request: the field chooser strips the ID field I suspect. Sometimes it is handy for seing that the last added record is. So can we add it to the field choose, default visible False?

> Please push
>
> is the tableservice still used anywhere?

> opening the image no not while typing, when saving

> yes please, but you are sure the numberic format is solved?

> no not solved, let's debug

> incoming:         "inkoopNr": "",
>         "Karaat": "2.0",
>         "afm1": "105.0",
>         "afm2": "7.0",
>         "afm3": "48.0", 
> https://www.karaatedelstenen.nl/cma/form_api.php?action=getRow&ID=4761&displayMode=1&jsonForm=steensoorten_producten shows : {
>     "success": true,
>     "html": "<tr class=\"listrow\" data-id=\"4761\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4761\">&#8942;</span><lib-switch data-field=\"bLeverbaar\" checked></lib-switch></td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked></lib-switch></td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"></lib-switch></td><td data-field=\"aantal\" data-type=\"text\">1</td><td data-field=\"voorraad\" data-type=\"text\">1</td><td data-field=\"Prijs\" data-type=\"text\">29.0000</td><td data-field=\"BerekendPrijs\" data-type=\"text\"></td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen</td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"156\">Pargasiet</td><td data-field=\"Beeld\" data-type=\"image\" class=\"cma-list-thumb-cell\"><img class=\"cma-list-thumb\" src=\"/images/producten/IMG_5757.JPG%3Fversie%3D1782036495\" data-full=\"/images/producten/IMG_5757.JPG%3Fversie%3D1782036495\" alt=\"\" loading=\"lazy\"></td><td data-field=\"video\" data-type=\"text\"></td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"></lib-switch></td><td data-field=\"datestamp\" data-type=\"date\">07-02-2020</td><td data-field=\"fkCertificaat\" data-type=\"combobox\" data-fk-value=\"\"></td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bZeldzaam\" checked></lib-switch></td><td data-field=\"Karaat\" data-type=\"text\">2.0</td><td data-field=\"afm1\" data-type=\"text\">105.0</td><td data-field=\"afm2\" data-type=\"text\">7.0</td><td data-field=\"afm3\" data-type=\"text\">48.0</td><td data-field=\"inkoopNr\" data-type=\"text\"></td><td data-field=\"InkoopTitel\" data-type=\"text\"></td><td data-field=\"inkoopPrijs\" data-type=\"text\">91000.0000</td><td data-field=\"inkoopUrl\" data-type=\"text\">https://www.ebay.nl/itm/2-CT-World-Rare-Unusual-Transparent-Green-Pargasite-Rare-Top-Cut-Gemstone-AFG/233428660860?ssPageName=STRK%3AMEBIDX%3AIT&amp;_trksid=p2057872.m2749.l2649</td><td data-field=\"inkoopVindplaats\" data-type=\"text\">Badakhshan</td></tr>",
>     "displayText": "2kt Pargasiet (Ovaal)",
>     "rowHtml": "<tr class=\"listrow\" data-id=\"4761\"><td data-field=\"bLeverbaar\" data-type=\"boolean\" data-value=\"1\"><span class=\"row-menu-trigger\" data-id=\"4761\">&#8942;</span><lib-switch data-field=\"bLeverbaar\" checked></lib-switch></td><td data-field=\"bGecontroleerd\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bGecontroleerd\" checked></lib-switch></td><td data-field=\"bCheckedSecat\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"bCheckedSecat\"></lib-switch></td><td data-field=\"aantal\" data-type=\"text\">1</td><td data-field=\"voorraad\" data-type=\"text\">1</td><td data-field=\"Prijs\" data-type=\"text\">29.0000</td><td data-field=\"BerekendPrijs\" data-type=\"text\"></td><td data-field=\"fkCategorie\" data-type=\"combobox\" data-fk-value=\"1\">Edelstenen geslepen</td><td data-field=\"fkSteensoort\" data-type=\"combobox\" data-fk-value=\"156\">Pargasiet</td><td data-field=\"Beeld\" data-type=\"image\" class=\"cma-list-thumb-cell\"><img class=\"cma-list-thumb\" src=\"/images/producten/IMG_5757.JPG%3Fversie%3D1782036495\" data-full=\"/images/producten/IMG_5757.JPG%3Fversie%3D1782036495\" alt=\"\" loading=\"lazy\"></td><td data-field=\"video\" data-type=\"text\"></td><td data-field=\"BlockVideo\" data-type=\"boolean\" data-value=\"0\"><lib-switch data-field=\"BlockVideo\"></lib-switch></td><td data-field=\"datestamp\" data-type=\"date\">07-02-2020</td><td data-field=\"fkCertificaat\" data-type=\"combobox\" data-fk-value=\"\"></td><td data-field=\"bZeldzaam\" data-type=\"boolean\" data-value=\"1\"><lib-switch data-field=\"bZeldzaam\" checked></lib-switch></td><td data-field=\"Karaat\" data-type=\"text\">2.0</td><td data-field=\"afm1\" data-type=\"text\">105.0</td><td data-field=\"afm2\" data-type=\"text\">7.0</td><td data-field=\"afm3\" data-type=\"text\">48.0</td><td data-field=\"inkoopNr\" data-type=\"text\"></td><td data-field=\"InkoopTitel\" data-type=\"text\"></td><td data-field=\"inkoopPrijs\" data-type=\"text\">91000.0000</td><td data-field=\"inkoopUrl\" data-type=\"text\">https://www.ebay.nl/itm/2-CT-World-Rare-Unusual-Transparent-Green-Pargasite-Rare-Top-Cut-Gemstone-AFG/233428660860?ssPageName=STRK%3AMEBIDX%3AIT&amp;_trksid=p2057872.m2749.l2649</td><td data-field=\"inkoopVindplaats\" data-type=\"text\">Badakhshan</td></tr>",
>     "_debug": {
>         "form": "steensoorten_producten",
>         "recordId": "4761",
>         "requestedColumns": [],
>         "matchedListColumns": 24,
>         "dbFieldNames": [
>             "Id",
>             "bLeverbaar",
>             "fkBTW",
>             "fkCategorie",
>             "fkSteensoort",
>             "fkSteenSubsoort",
>             "Naam",
>             "Beschrijving",
>             "BerekendPrijs",
>             "Prijs",
>             "Beeld",
>             "datestamp",
>             "inkoopPrijs",
>             "inkoopNr",
>             "Karaat",
>             "Afm1",
>             "Afm2",
>             "Afm3",
>             "bZeldzaam",
>             "inkoopBeeld",
>             "inkoopUrl",
>             "inkoopTitel",
>             "inkoopPagina",
>             "inkoopVindplaats",
>             "fkCertificaat",
>             "bGecontroleerd",
>             "video",
>             "videoStill",
>             "blockVideo",
>             "aantal",
>             "voorraad",
>             "bCheckedSecat"
>         ],
>         "totalTds": 24,
>         "emptyTds": 6
>     }
> }
>
> i don't actually see the post?!
> and for later: https://www.karaatedelstenen.nl/cma/form_api.php?action=checklist&form=steensoorten_producten&controlId=kleuren&id=4761 has a lot of debug info, can be optimised.

> not editing inline!
>
> <textarea id="Beschrijving" name="Beschrijving" data-field="Beschrijving" data-type="130" data-required="false" data-readonly="false" data-label="Beschrijving" data-allow-html="true" data-limited-html="false" data-max-chars="0" data-no-spam-js="false" data-use-blockedit="false" style="width: 100%; height: 90px; visibility: hidden;" rows="5" data-original-value=""></textarea> 
>
> that should become a htmledit field, but it does not?!

> continuea

> Posting : afm1
> 10,5
> afm1__label
> Afmetingen , can you include the actual sql in the console.log?

> can you add that screen to the menu.json , but it is site-related so it should reside in /tools/quick_add_stone.php

> did you push, i dont see the menu

> kleur en vorm kloppen nog niet, Type mag default Edelstenen geslepen zijn en als er 2 zijn Edelstenen paren, bij nog meer Edelstenen partijen. Te zetten na draaien AI. Voeg ook een omschrijving veld toe

> you switched upload methods? PHP Fatal error:  Uncaught Error: Class "App\Library\Request" not found in C:\wwwroot\www.karaatedelstenen.nl\cma\imageupload_crop_upload_handler.php:32
> Stack trace:
> #0 {main}
>   thrown in C:\wwwroot\www.karaatedelstenen.nl\cma\imageupload_crop_upload_handler.php on line 32

> Auto-detectie overgeslagen: Verbinding met Claude mislukt: SSL certificate OpenSSL verify result: unable to get local issuer certificate (20)

> for the quick stone add, fll the date with the current date, don't create a description, they are too generic. And can you create an edicated guess as to the stone-type? And default the stone is Active = True

> site is down: rror in //index.php
>
> TypeError: Unsupported operand types: string * int
>
> in C:\wwwroot\www.karaatedelstenen.nl\utils.inc:65
>
> #0 C:\wwwroot\www.karaatedelstenen.nl\views\homepage.inc(19): WriteCaroussel()
> #1 C:\wwwroot\www.karaatedelstenen.nl\index.php(36): require_once('...')
> #2 C:\wwwroot\www.karaatedelstenen.nl\_bootstrap_wrapper.php(61): include('...')
> #3 {main}

> so the webp images are not automatically made (the .responsive folder), please make sure that they are after saving in the newly created form AND in the steen form of the CMA

> site down again?? Error in /index.php
>
> Error: Class "App\Library\Application" not found
>
> in C:\wwwroot\www.karaatedelstenen.nl\filter.inc:14
>
> #0 C:\wwwroot\www.karaatedelstenen.nl\header.inc(14): require_once()
> #1 C:\wwwroot\www.karaatedelstenen.nl\index.php(11): require_once('...')
> #2 C:\wwwroot\www.karaatedelstenen.nl\_bootstrap_wrapper.php(61): include('...')
> #3 {main}

> @"/home/diede/.claude/uploads/5c0b7bef-8b7e-4e29-8860-f2af497303ac/7195a778-IMG_5827.png" the edit dialog onnthe site looks like this: please fix

> live dom on www.karaatedelstenen.nl please call the script to execute

> please show a clickable link for me
>
> the popip edit screen ; values please darker

> can you see why https://www.karaatedelstenen.nl/aanbod?sort=datum%20desc is missing an aquamarijn image?

> mow this one is missing: https://www.karaatedelstenen.nl/edelsteen/4967/alexandriet.html
> i am sure it was tehe before?!

> hompage laatste bekeken, the first 2 are okay, the 3rd an 4th are missing

## 2026-06-28

> edit dialog still not okay
>
> fields for carats, price and dimensions toonlrge, max 5 characters is fine
>
> checkbox s in a table format please 
>
> lazy loading the detaiol page leads to a content shift, please use a placeholder when loading
>
> verwijder het label ‘geen behandeling’
>
> from the front-end can we call the edit image from the cma_platform in a dialog?

> laatst bekeken still uses clustom code, delete that and use the standard web component

> deploy takes a long time

> yes good idea. can you do the extra parameter and include it in the documentation and all other repo’s and template?

> site is still down: Error in //index.php
>
> Error: Class "Database" not found
>
> in C:\wwwroot\www.karaatedelstenen.nl\utils.inc:40
>
> #0 C:\wwwroot\www.karaatedelstenen.nl\views\homepage.inc(19): WriteCaroussel()
> #1 C:\wwwroot\www.karaatedelstenen.nl\index.php(36): require_once('...')
> #2 C:\wwwroot\www.karaatedelstenen.nl\_bootstrap_wrapper.php(61): include('...')
> #3 {main}

> still broken inages in laatst bekeken?! cache?

> the caroussel haa smaller images noe , can we enlarge and STILL laatst bekeken has broken images but they are available

> @"/home/diede/.claude/uploads/5c0b7bef-8b7e-4e29-8860-f2af497303ac/e9b922bf-IMG_5832.png" the edit form is inconsistently designed:

> carroussel
> inages are now portrait? must be a max-width or something

> the edit dialog shoes beschikbaar but no contrl to change availability

> the aspect ratio is wrong on mobile, stones are cut through

> didnt you iinclude the cma imaggeeditor?

> create a button next to the edit button to change the image, remove it from the edit screen
>
> and the image editor url is wrong, this is an old uploader, perhaps the cma platgorm image editor is not ready?

> can we look at the url management? the url https://www.karaatedelstenen.nl/cma/form/steensoorten_producten/4984 for instance should open the list (the active view) and the stond with id 4984, but the detail form is never opnend. For many other links the same applies, also when opening or closing forms/screens the url is not maintained correctly.. Think of a solid system to fix this, not just some patchwork. Solve the root cause of the bug please.

> i will task another window to do just that

> on the file upload wizard in the right pane we have an effort to edit images. I want a stand-alone image editor with all the options that ar now on the right pane. Create a button on the right pane of the files wizard to open this. The stand-alone editor should be callable from fromt-end as well. with a cma login guard in place.

> the quick add form, does it scan for existing stones (using karaat and dimentions) before adding one?

> perhaps a tweak would be to indeed add tolerances. For instance 0.70 carat and 0.7 carat or dimentions that have been rounded like 4.84 rounded to 4.8.

> when saving the stone with 2 selected colors I get this error: <br/><b>Warning</b>:  Array to string conversion in <b>C:\wwwroot\www.karaatedelstenen.nl\cma\classes\FormDataProvider.php</b>on line<b>927</b><br /><br/><b>Warning</b>:  Array to string conversion in <b>C:\wwwroot\www.karaatedelstenen.nl\cma\classes\FormDataProvider.php</b>on line<b>927</b><br />{
>     "success": false,
>     "error": "Kan niet opslaan: veld(en) 'vormen', 'kleuren' bestaan niet in de database. Verwijder deze velden uit het formulier of voeg ze toe aan de tabel 'tblProducten'."
> }

> trigger deploy please

> it works. Please make a note of an issue in cma_platform: ..combo-display has the wrong min-height: it should be 26px, or can you fix that

> the new button should be annivon button as well

> laatst bekeken is still showing no images!

> the image editor to use is  cma/image-editor.php, can you implement that one?

> commit and push all

> after editing an image, can we save the original into an .originals folder?

> @"/home/diede/.claude/uploads/5c0b7bef-8b7e-4e29-8860-f2af497303ac/0192cb2c-IMG_5835.png" for the cma_platform, toolbar buttons on forma on mobile should be icon only

> remote-control

> yes please dive into that

> check for needed updates and petform them, you are alone now

> the detail screen shows;
>
> Home > Edelstenen geslepen
> Opaal (Ovaal) 4.65kt
> « Terug naar aanbod stenen – zoek je steen uit
>
> the last link should be the link for delstenen geslepen and be removex

> aanbod edelstenen should go to aanbodhref

> yes

> the stack seems required after closing a form? if not delete it

> the stone info now says 93 x 3 x 2 mm mm, the dirst mm may be removed (the black one)

> what are all routes in use? include tools and user/group management

> test all routes in the test frame and see if the desired layout was there, also test the close button to see if the url changes vorrectly, this is a recurring bug i want regressiontesting for

> the last bekeken section is missing the images: 1 use a placeholder, 2 fix the loading: most likely lazy loading is notvtriggered after the DOM changes

> on the filter screen : enlarge the size and dimensions information by 2px on mobile

> works! 
>
> bekijk al het nieuwe -> Alle nieuwe stenen

> @"/home/diede/.claude/uploads/1a002755-725b-42d5-ba0d-65e7201c641e/0e00b455-IMG_5836.png" on. mobile : content area moch too high and inconvtoolbar invisible , use available heiight - 150px and make dialog responsive

> force a deploy on karaat

> yes

> <task-notification>
> <task-id>biuh4kwfd</task-id>
> <tool-use-id>toolu_017CyEZ4pRo8VBpWgWjGKjDD</tool-use-id>
> <output-file>/tmp/claude-1000/-mnt-c-repos-cma-platform/16b74ece-f721-4e43-aebd-cf2be54b05c2/tasks/biuh4kwfd.output</output-file>
> <status>completed</status>
> <summary>Background command "cd /mnt/c/repos/cma_platform/cma; echo "=== new close-nav logic in min (loadPage list + history.back fallback) ==="; grep -o ".\{20\}loadPage(.\{0,25\}).\{0,40\}history.back().\{0,5\}" assets/js/form-controller.min.js | head -1 || echo "checking differently..."; grep -c "Could not navigate to list on close" assets/js/form-controller.min.js" completed (exit code 0)</summary>
> </task-notification>

## 2026-06-29

> what version are you on?

> yea please ship but first set version to 1.28.0

> the buttons in the detail screen: in winkelmand, edit, edit picture and save to wishlist have different sizes, make sure they all have the same size

> the image editor: that was a really bad edit. Not only did you AGAIN invent your own classes , it looks like crap. Icons are almost all missing, a strange grey background, buttons Annuleren en klaar don't align to the bottom, a rendomly placed size indicator and extra margins.. Use the available webcomponents as per claude.MD and make sure all icons are there. Then : most icons lead to the error Bewerking mislukt, so it does not even work also.

> one route: https://www.karaatedelstenen.nl/cma/tools.php?tool=webp_convert , this one is missing the menu on the left.

> no just the height

> okay, now if the screen is mobile, the edit overlaps the winkelmand, shy not just place them next to each other and only right align the save ?

> .dialog-maximize .lnr-frame-expand::before {
>     content: "\e952";
> }
>
> this one seems to be missing from linearicons

> the image editor allows for any format to be cropped, in the case of karaat: only allow for 16:9 aspect ratio, make sure it is parameterised by karaat.

> the quick_add_stone should use a lib_combo with multiple indication and be 100% wide

> yes i mean the main app sidebar, it should always be there, in all routes

> yes please require a manual crop

> Okay, starting the dialog I see everything and then a spinner appears... never to dissapear again?!

> the edit form should also use the same <lib-combo multiple to save space

> io don't want a stand alone renderer for the tools menu. so yes please remove it

> i had cropped an image, but the responsive formats did not regenerate, please check

> much better: great!

> i think that linearicons is not always available, on the product detail page it seems to be missing (i checked the network tab)

> it IS loaded through https://www.karaatedelstenen.nl/cma/minify.php?f=../library/css/lib-variables.css,assets/css/colors.css,../library/library.css,../library/css/lib-components.css,assets/css/style.css,../library/select2/select2.css,../library/classes/class_table.css,../library/webcomponents/lib-table.css,assets/css/form.css,assets/css/inline-edit.css,assets/css/main.css&v=20260629d but many icons are missing like minimize/maximize,

> the annuleer button does nothing?

> karaat site is down, please check

> 1 please

> if the login screen is shown, move focus to the login name field

> karaat detail page : Heel gaaf steentje, klein maar zeer mooi. Er zit een oneffenheid in de steen maar die zie je alleen onder extreme vergroting.<br /> Alexandriet is echt heel zeldzaam, zeldzamer dan diamand. Het is dan ook de enige steen die ik aan kan bieden.&nbsp;<br /> Alexandriet verandert van kleur onder verschillende typen licht, deze steen is groen en rood.&nbsp;
>
> if it contains html do not encode

> after an edit the page is updated, but that does not have this change, can we simply refresh the page?

> in the quick edit form, can you add Zeldzaam to the form, next to Leverbaar ? and change the captions -> Kleur(en) and Vorm(en)

> i now have a stone with way too much red, can we create a small popup where we can change the R G B hue values ? Preferably with a read-life update if possible?

> On the homepage, add 'Enkele edelstenen'and rename Alle steensoorten to Edelstenen van A-Z, over die pagina: alle stenen ontbreken, kun je die ook webp-aware maken?

> perfect, works like a charm!

> A generic white balance would be nice , can you include it in the current mini-dialog?

> .image-editor-info and .image-editor-footer: remove background and border-top 
>
> then the toolbar is not the same as a cma toolbar, the buttons need to be span with class'tb-btn responsive-btn'

> Kunnen we in de bevestigingsmail een link naar trustpilot uitnodiging plaatsen?
> vereist volgens mij api toegang

> the quick add form: remove the .qa-wrap {
>    max-width: 560px

> okay, now I want you to add a column to the right with simular stones already in the shop, select based upon steensoort and karats, pick max 6 so i can create a good pricing

> De feature 'Mogelijk bestaat deze steen al', deze toont geen plaatje (_resized issue) en ik wil eigenlijk de keuze: vul deze bestaande steen aan met deze gegevens, dus foto en prijs in ieder geval. En de annuleren knop is niet grijs

> well, the most important data; the price is invisible and make the max 10pieces , the network tab does have prices: 
>
> <karaat-stone>
>     <div class="card card--searchResult" data-afm1="6.92" data-afm2="5.1" data-afm3="3.65" data-soort="1" data-soortoms="Topaas" data-karaat="1.1" data-prijs="26.0000" data-datum="0" data-kleur_1 data-vorm_2>
>         <a href="/edelsteen/4560/topaas.html">
>             <div class=card_inner>
>                 <span class="card-imgwrap">
>                     <img width="600" height="400" data-img="/images/producten/.responsive/IMG_4444-400w.webp">
>                     <span class="card-wishlist" data-product="4560" role="button" tabindex="0" aria-label="Bewaar in wensenlijst" title="Bewaar in wensenlijst">
>                         <svg viewBox="0 0 24 24">
>                             <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
>                         </svg>
>                     </span>
>                 </span>
>                 <div class=details>
>                     <div class=prijs>
>                         &euro;&nbsp;<span>26,-</span>
>                     </div>
>                     <span class=s>Topaas</span>
>                     <span class=c>1.1</span>
>                     <span class=a>7 x 5 x 4</span>
>                 </div>
>             </div>
>         </a>
>     </div>
> </karaat-stone>
> <karaat-stone>
>     <div class="card card--searchResult" data-afm1="4.3" data-afm2="4.3" data-afm3="2.35" data-soort="1" data-soortoms="Topaas" data-karaat="1.0" data-prijs="25.0000" data-datum="0" data-kleur_1 data-kleur_10 data-vorm_1>
>         <a href="/edelsteen/4865/topaas.html">
>             <div class=card_inner>
>                 <span class="card-imgwrap">
>                     <img width="600" height="400" data-img="/images/producten/.responsive/IMG_4859-400w.webp">
>                     <span class="card-wishlist" data-product="4865" role="button" tabindex="0" aria-label="Bewaar in wensenlijst" title="Bewaar in wensenlijst">
>                         <svg viewBox="0 0 24 24">
>                             <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
>                         </svg>
>                     </span>
>                 </span>
>                 <div class=details>
>                     <div class=prijs>
>                         &euro;&nbsp;<span>25,-</span>
>                     </div>
>                     <span class=s>Topaas</span>
>                     <span class=c>1.0</span>
>                     <span class=a>4 x 4 x 2</span>
>                 </div>
>             </div>
>         </a>
>     </div>
> </karaat-stone>
> <karaat-stone>
>     <div class="card card--searchResult" data-afm1="7.0" data-afm2="4.25" data-afm3="3.45" data-soort="1" data-soortoms="Topaas" data-karaat="1.0" data-prijs="25.0000" data-datum="0" data-kleur_1 data-kleur_10 data-vorm_2>
>         <a href="/edelsteen/4552/topaas.html">
>             <div class=card_inner>
>                 <span class="card-imgwrap">
>                     <img width="600" height="400" data-img="/images/producten/.responsive/IMG_4432-400w.webp">
>                     <span class="card-wishlist" data-product="4552" role="button" tabindex="0" aria-label="Bewaar in wensenlijst" title="Bewaar in wensenlijst">
>                         <svg viewBox="0 0 24 24">
>                             <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
>                         </svg>
>                     </span>
>                 </span>
>                 <div class=details>
>                     <div class=prijs>
>                         &euro;&nbsp;<span>25,-</span>
>                     </div>
>                     <span class=s>Topaas</span>
>                     <span class=c>1.0</span>
>                     <span class=a>7 x 4 x 3</span>
>                 </div>
>             </div>
>         </a>
>     </div>
> </karaat-stone>
> <karaat-stone>
>     <div class="card card--searchResult" data-afm1="5.7" data-afm2="5.0" data-afm3="4.0" data-soort="1" data-soortoms="Topaas" data-karaat="0.94" data-prijs="24.0000" data-datum="0" data-kleur_12 data-vorm_2>
>         <a href="/edelsteen/2372/topaas.html">
>             <div class=card_inner>
>                 <span class="card-imgwrap">
>                     <img width="600" height="400" data-img="/images/producten/.responsive/IMG_4273-400w.webp">
>                     <span class="card-wishlist" data-product="2372" role="button" tabindex="0" aria-label="Bewaar in wensenlijst" title="Bewaar in wensenlijst">
>                         <svg viewBox="0 0 24 24">
>                             <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
>                         </svg>
>                     </span>
>                 </span>
>                 <div class=details>
>                     <div class=prijs>
>                         &euro;&nbsp;<span>24,-</span>
>                     </div>
>                     <span class=s>Topaas</span>
>                     <span class=c>0.94</span>
>                     <span class=a>6 x 5 x 4</span>
>                 </div>
>             </div>
>         </a>
>     </div>
> </karaat-stone>
> <karaat-stone>
>     <div class="card card--searchResult" data-afm1="5.85" data-afm2="5.85" data-afm3="3.77" data-soort="1" data-soortoms="Topaas" data-karaat="0.9" data-prijs="24.0000" data-datum="0" data-kleur_10 data-kleur_1 data-vorm_1>
>         <a href="/edelsteen/4531/topaas.html">
>             <div class=card_inner>
>                 <span class="card-imgwrap">
>                     <img width="600" height="400" data-img="/images/producten/.responsive/IMG_4372-400w.webp">
>                     <span class="card-wishlist" data-product="4531" role="button" tabindex="0" aria-label="Bewaar in wensenlijst" title="Bewaar in wensenlijst">
>                         <svg viewBox="0 0 24 24">
>                             <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
>                         </svg>
>                     </span>
>                 </span>
>                 <div class=details>
>                     <div class=prijs>
>                         &euro;&nbsp;<span>24,-</span>
>                     </div>
>                     <span class=s>Topaas</span>
>                     <span class=c>0.9</span>
>                     <span class=a>6 x 6 x 4</span>
>                 </div>
>             </div>
>         </a>
>     </div>
> </karaat-stone>

> the list of toegevoegde stenen does not show anything anymore, I see Steen toegevoegd (ID 4998)., but it is not below the title and does not contain a link

> in the form field selector the ID is still hidden, i really want to be able to sort on that

> the dialog for existing stones: still had no image, can you double check?

> the trustpilot address is www.karaatedelstenen.nl+3323486573@invite.trustpilot.com

> Nu een heel andere vraag; ik wil achterhalen welke steensoort het meest populair is, kun je op basis van tblorderregels een top 10 samenstellen?

> Kun je daar een rapport in de cma voor maken met de positie, de steensoort, het aantal verkocht en het aantal beschikbaar in de webshop.

> Syntax error or access violation: -3100 Syntax error in query expression '(SELECT Count(*) FROM tblOrderRegels WHERE InStr(tblOrderRegels.sProduct, tblSteensoorten.SoortNaamNL) > 0'. 
>
> tblOrderregels heeft een steensoort die verwijst naar de tabel steensoorten, nu gebruik je erg veen inner selects, dat moet sumpeler kunnen en wist je dat je order by per nummer kunt doen, dus order by 2 -> sorteer op het tweede uitvoerveld

## 2026-06-30

> De query klopt echt niet , er zitten 4 stenen in met 0 verkopen.

> the icons for lighter and darker: lighter use white color: darker use black, the contrast buttons also need to switch

> SELECT Count(*) FROM tblOrderRegels WHERE tblOrderRegels.sProduct LIKE '%' & tblSteens
> oorten.SoortNaamNL & '%' -> tblOrderregels heeft een fkProduct ID, die naar producten verwijst waar de soortnaam in staat

> ik haal nooit producten weg, kun je de query op de homepage ook aanpassen? En graag zie ik dat je dat soort - foute - keuzes voorlegd

> visualisatie 16:9 is aardig, maar kunnen we de 16 en 9 in het plaatje zetten?

> can we switch to the  mijnrino repo?

> the link https://www.karaatedelstenen.nl/cma/form/steensoorten/5 should open a detail form but it does not.

> the filtermenu is now desinged nicely, but the export menu (first menu in tables) not, can you copy the layour of filtermenu's to export menu's ?

> could you compile a list of tables in the SQL's of the export section, these are postgress SQL's ?

> Can you expand the list with an extra column that indicates if we have implemented a webhook for that table? 
>
>
> After that: we need to determine what to do if the enrichment does not work. Expecially 404's. My gut feeling says: ignore them. Or treat them as a Delete hook.

> okay, for later: make a 404 counter with a graph to catch systematic issues. Please note it in todo.md

> https://staging-webhook.rino.nl/admin/workload.php -> the graph shows the dates within the columns, moving them up, make sure the labels are outside the graph

> there is a mijnrino API, can we make a swagger documentation for it? create a batch file d.bat that updates the documentation based upon the settings and remember to update it after making changes to the API

> https://www.karaatedelstenen.nl/cma/form/steensoorten_producten/1958 -> still does not work. 
> ?
> Also the images are not always shown, the resize variants are now shown. Can we strip the resized part of the name ? So https://www.karaatedelstenen.nl/images/producten/IMG_4339_resized600x400.jpg should become https://www.karaatedelstenen.nl/images/producten/IMG_4339.jpg

> the export menu styling should be app specific, overriding the standard layout

> what is the url for the documentation?

> i want the lib-switch to filter on that ip address

> nee hij draait een oude

> je kunt toch een deploy forceren?

> yes please do

> ja wil je die 500 wegnemen?

> okay, what if i set the transport to live - change something real fast to test - and back to file, could that do any harm on the long run?

> the hook detail sidebar has a grafiek: show the numbers when i hover over a bar. The laatste 10 does not seem to work, it is empty, but the mislukt tab has many items so the last 10 should have content (possibly failed but nonetheless)

> verwerk wachtrij -> verwerken mislukt: AMQPSSLConnection is deprecated and will be removed in version 4 of php-amqplib

> PhpAmqpLib\Exception\AMQPIOException: stream_socket_client(): Unable to connect to tcp://rmqt.rino.nl:5671 (A connection attempt failed because the connected party did not properly respond after a period of time, or established connection failed because connected host has failed to respond) 
>
> if enrichment does not work/gets empty data/gets a timeout, fail and allow retry

> i get this error: tcp://rmqt.rino.nl:5671 verwerken mislukt: stream_socket_client(): Unable to connect to tcp://rmqt.rino.nl:5671 (A connection attempt failed because the connected party did not properly respond after a period of time, or established connection failed because connected host has failed to respond) , how do i check the firewall?

## 2026-07-02

> in karaat L if i remove all shapes / colors from a stone (multiple select), they are not saved. Like an empty value is ignored. can you check?

> https://www.karaatedelstenen.nl/edelsteen/4444/saffier.html toont 2 kleuren in de filterweergave, maar als ik de quickedit open staat er geen enkele kleur

> the email loggin into a table of the database, has that been implemented yet? The goal is to have an archive of all mail send with retry options etc.

> Is there an interface in the CMA to enable forwarding, re-sending etc? I could not find it

> Dus Libmailer is volledig separaat van app\library\email? Kunnen we libMailer terugschroeven naar alleen een wrapper voor app\libray\email ?

> Meteen de wrapper graag, 2 codepaden vind ik niet tof.

> kun je libMailer even tonen=

> Kun je een 1:1 vergelijking maken tussen de oude en de nieuwe class

> Bouw addReplyTo wel in als optie. en showpreview op het scherm moet nog wel in dezelfde opzet blijven.

> okay, push and commit please

> peError: LibLog.log is not a function
>     at Object.log (https://www.karaatedelstenen.nl/cma/minify.php?f=assets/js/cma-utils.js,assets/js/url-manager.js,../library/error-handler.js,assets/js/request-tracker.js,webcomponents/cma-base-component.js,../library/webcomponents/lib-shared-styles.js,../library/webcomponents/lib-loader.js,../library/webcomponents/lib-switch.js,../library/webcomponents/lib-radio-group.js,../library/webcomponents/lib-dialog.js,../library/webcomponents/lib-message.js,../library/webcomponents/lib-menu.js,../library/webcomponents/lib-toaster.js,../library/webcomponents/lib-search-input.js,../library/webcomponents/lib-datepicker.js,../library/webcomponents/lib-timepicker.js,../library/webcomponents/lib-histogram.js,../library/webcomponents/lib-gauge.js,../library/webcomponents/lib-combo.js,webcomponents/shared-icons.js,webcomponents/cma-blockeditor.js,webcomponents/cma-fold.js,webcomponents/cma-tree.js,webcomponents/cma-sortlist.js,webcomponents/cma-groupbox.js,webcomponents/cma-toolbar.js,webcomponents/cma-tabs.js,../library/library.js,../library/formval_nl.js,../library/datepicker.js,../library/select2/select2.js,../library/webcomponents/lib-table.js,assets/js/cma.js,assets/js/cma-users.js,webcomponents/cma-htmledit.js,assets/js/blockedit.js,assets/js/table-preferences.js,assets/js/inline-edit.js,assets/js/perf-logger.js,../library/webcomponents/lib-tip.js,assets/js/cma-tours.js,assets/js/cma-list-thumb.js,assets/js/main.js&v=20260702c:1:640)
>     at Object.log (https://www.karaatedelstenen.nl/cma/minify.php?f=assets/js/cma-utils.js,assets/js/url-manager.js,../library/error-handler.js,assets/js/request-tracker.js,webcomponents/cma-base-component.js,../library/webcomponents/lib-shared-styles.js,../library/webcomponents/lib-loader.js,../library/webcomponents/lib-switch.js,../library/webcomponents/lib-radio-group.js,../library/webcomponents/lib-dialog.js,../library/webcomponents/lib-message.js,../library/webcomponents/lib-menu.js,../library/webcomponents/lib-toaster.js,../library/webcomponents/lib-search-input.js,../library/webcomponents/lib-datepicker.js,../library/webcomponents/lib-timepicker.js,../library/webcomponents/lib-histogram.js,../library/webcomponents/lib-gauge.js,../library/webcomponents/lib-combo.js,webcomponents/shared-icons.js,webcomponents/cma-blockeditor.js,webcomponents/cma-fold.js,webcomponents/cma-tree.js,webcomponents/cma-sortlist.js,webcomponents/cma-groupbox.js,webcomponents/cma-toolbar.js,webcomponents/cma-tabs.js,../library/library.js,../library/formval_nl.js,../library/datepicker.js,../library/select2/select2.js,../library/webcomponents/lib-table.js,assets/js/cma.js,assets/js/cma-users.js,webcomponents/cma-htmledit.js,assets/js/blockedit.js,assets/js/table-preferences.js,assets/js/inline-edit.js,assets/js/perf-logger.js,../library/webcomponents/lib-tip.js,assets/js/cma-tours.js,assets/js/cma-list-thumb.js,assets/js/main.js&v=20260702c:1438:107)
>     at w (https://www.karaatedelstenen.nl/cma/minify.php?f=assets/js/cma-utils.js,assets/js/url-manager.js,../library/error-handler.js,assets/js/request-tracker.js,webcomponents/cma-base-component.js,../library/webcomponents/lib-shared-styles.js,../library/webcomponents/lib-loader.js,../library/webcomponents/lib-switch.js,../library/webcomponents/lib-radio-group.js,../library/webcomponents/lib-dialog.js,../library/webcomponents/lib-message.js,../library/webcomponents/lib-menu.js,../library/webcomponents/lib-toaster.js,../library/webcomponents/lib-search-input.js,../library/webcomponents/lib-datepicker.js,../library/webcomponents/lib-timepicker.js,../library/webcomponents/lib-histogram.js,../library/webcomponents/lib-gauge.js,../library/webcomponents/lib-combo.js,webcomponents/shared-icons.js,webcomponents/cma-blockeditor.js,webcomponents/cma-fold.js,webcomponents/cma-tree.js,webcomponents/cma-sortlist.js,webcomponents/cma-groupbox.js,webcomponents/cma-toolbar.js,webcomponents/cma-tabs.js,../library/library.js,../library/formval_nl.js,../l
> Browser    Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36

> Yes i want all values to go through the maskering.
>
> i see the email form now, but an UX concern : the forms all load in such a way that the tools menu is gone. Can we do it differently? Can we have a select a on top ot the screen in cma-header and load the form in #contentarea ? and then for all the tools the same approach

> css request : #contentArea { height: 100% }
>
> https://www.karaatedelstenen.nl/cma/form.php?form=marketingurl zegt dat tblCMAMarketingurl niet bestaat, de migratie zegt dat hij al wel bestaat?

## 2026-07-03

> ja is goed

> <task-notification>
> <task-id>aefa826ffbbac5b21</task-id>
> <tool-use-id>toolu_01GJyou47rhzz3uhdHs4YFa2</tool-use-id>
> <output-file>/tmp/claude-1000/-mnt-c-repos-cma-platform/16b74ece-f721-4e43-aebd-cf2be54b05c2/tasks/aefa826ffbbac5b21.output</output-file>
> <status>completed</status>
> <summary>Agent "Map CMA navigation architecture" finished</summary>
> <note>A task-notification fires each time this agent stops with no live background children of its own. The user can send it another message and resume it, so the same task-id may notify more than once.</note>
> <result>I have a complete picture of the navigation architecture. Here is the structured report.
>
> ---
>
> # CMA Admin Navigation Architecture — Current State
>
> ## Crux answer up front (content-loading mechanism)
>
> The shell loads content into `#contentArea` via **AJAX fetch**, NOT iframe (for internal pages) and NOT full navigation. `main.php` renders a persistent sidebar + header once; the content area is swapped by `window.loadPage()` which does `fetch('/cma/main.php?nomenu&amp;page=&lt;page&gt;')` and injects the returned HTML fragment into `#contentArea`. The header and sidebar are never reloaded on internal navigation. Iframes are used only for (a) external/absolute URLs and (b) the tools sub-navigation. This means a form CAN be loaded into `#contentArea` while keeping the menu — that is exactly what already happens today.
>
> ---
>
> ## 1. The main shell — `cma/main.php`
>
> - Layout is a flexbox app shell: `&lt;div class="cma-app"&gt;` containing a left `&lt;aside class="cma-sidebar" id="sidebar"&gt;` and a `&lt;main class="cma-main"&gt;` (main.php:463–578).
> - `#contentArea`: `cma/main.php:574` — `&lt;div class="cma-content" id="contentArea"&gt;&lt;div class="cma-content-loading"&gt;Laden...&lt;/div&gt;&lt;/div&gt;`. Initial spinner replaced once JS loads a page.
> - Content loading: bottom-of-page script (main.php:580–589) polls for `loadInitialPage` then calls it with `$contentPage`. All subsequent nav goes through `window.loadPage` (defined in `cma/assets/js/main.js:693`, minified in `main.min.js`).
> - Header: inline `&lt;header class="cma-header"&gt;` (main.php:534–572). **There is NO `cma-header` web component** — it is plain markup. Contains: a hamburger `#menuToggle` checkbox (534–538), a `#breadcrumb` div (539), an optional environment `lib-label` (540–542), and the user menu `.cma-user-menu` with dropdown (543–571: user level, version, Voorkeuren, wachtwoord, impersonation return, logout).
> - Left sidebar / main menu: always present across all internal routes because it lives in the shell and is never re-fetched. The sidebar has a header/logo (465–483) and `&lt;nav class="cma-sidebar-nav" id="sidebarNav"&gt;` (485–527).
> - `nomenu` mode: `main.php:56–138` — when `?nomenu&amp;page=X` is requested, main.php acts as a server-side proxy that `include`s the target PHP and returns only its output (defines `CMA_NOMENU_MODE`). This is the endpoint `loadPage` fetches.
>
> ## 2. Header / top bar
>
> - Inline in main.php (see §1), class `.cma-header`. Styling and mobile behavior live in `cma/assets/css/main.css` (only file defining `.cma-header`).
> - Contents: hamburger toggle, breadcrumb, env label, user dropdown menu. No logo (logo is in the sidebar header), no global search in the header (search exists per-page, e.g. tools).
> - Mobile: `@media (max-width: 768px)` in `main.css:977–1036` shows `#menuToggle` and `.cma-mobile-menu-btn`, turns `.cma-sidebar` into a fixed off-canvas drawer (`transform: translateX(-100%)`, `.open` → `translateX(0)`). Toggle logic in `main.js` `toggleSidebar()` adds/removes `.open` below 768px and `.collapsed` above.
>
> ## 3. Main menu — definition &amp; rendering
>
> - Data source: `cma/config/menu.json` (schema-referenced; structure is `menus[]` each with `id/name/order/items[]`, items carry `formId` + `form` or an href). Sample at menu.json:1–60.
> - Service: `cma/classes/Services/MenuService.php` (class at :21) loads menu config, preferring external `/site/data/menu.json` (CONFIG_PATH at :37), with access levels user/admin/developer. Consumed via `cma/menurep.inc` `loadMenuData()`, which main.php calls at `main.php:245`.
> - Rendering: PHP loop in `main.php:247–348` builds `$menuItems` grouped by menu name, applying access checks (`SecurityHelper::checkRights`, :256) and group icons (`$menuGroupIcons`, :182–228). Dashboard is force-injected first (:231–243); a Systeem→Tools group is force-injected for admins if absent (:324–347).
> - Item → page mapping (main.php:286–298): form-backed items get `dataPage = 'form.php?form=&lt;form&gt;'` (used for AJAX) and `href = '/cma/form/&lt;form&gt;'` (clean URL for bookmarks). Non-form items use their href (with `.asp`→`.php`).
> - Markup: `main.php:487–526`. Single-item groups render as a direct link; multi-item groups render an expandable header + `.cma-menu-group-items` + a collapsed-state `.cma-menu-popup`. Each link carries `data-page` (AJAX target) and `href` (clean URL). Click handling / active-state / breadcrumb sync is wired in `main.js` (function `q()` in the minified bundle attaches click → `loadPage(dataPage)`).
> - There is NO `cma-menu`/`lib-menu` component used for the primary nav — it is hand-rolled PHP + CSS. (`library/webcomponents/lib-menu.js` exists but is not the app's primary nav.)
>
> ## 4. Tools navigation — `cma/tools.php`
>
> - Access-guarded to admins (:30). Key routing guard at `tools.php:51–60`: if NOT in nomenu mode (i.e. a standalone/top-level hit), it **redirects to `/cma/main.php?page=tools.php`** so tools always render inside the shell (sidebar present). So tools does NOT open standalone — it is fetched into `#contentArea` like any other page.
> - Inside the content area, tools.php emits its own two-pane layout: `&lt;div class="tools-ajax-container"&gt;` with a left `#leftlist` containing a search input + `&lt;cma-tree id="tools-tree" ...&gt;` (tools.php:194–217), a `&lt;cma-fold&gt;` resizer (:220–226), and a right `&lt;iframe id="tools-content"&gt;` (:227). So the tools TREE lives inside `#contentArea`; individual tool pages load into a nested **iframe**.
> - Tree data built by `buildToolsTreeData()` (called :177), JSON-encoded into the `data` attribute.
> - Click behavior (tools.php:258–299): on the tree's `item-click`:
>   - If `href` starts with `form.php?form=` → it does NOT load into the tools iframe; instead it calls `window.top.loadPage(href)` (:265–274), promoting the form to the canonical `/cma/form/&lt;form&gt;` route in the shell content area (replacing the whole tools view).
>   - Otherwise (a real tool) → `iframe.src = href` (:277) and pushes `/cma/tools?tool=&lt;name&gt;` to history.
> - Form-backed aliases (users, groups, emaillog, …) are also collapsed to `/cma/form/&lt;form&gt;` server-side at tools.php:118–150 (`$formBackedTools` + a `location.replace` redirect script), preventing dual routes.
>
> ## 5. How forms load — `/cma/form/&lt;form&gt;`
>
> - Routing: `cma/web.config:105–139` rewrites `/cma/form/&lt;form&gt;[/id/...]` → `/cma/main.php?page=form.php%3Fform%3D&lt;form&gt;...`. So the entry point is always **`main.php` (the full shell)**, with `form.php` as the `page` parameter.
> - On a hard load/refresh of `/cma/form/klanten`: web.config → `main.php` renders the FULL shell (header + sidebar), then the bottom script calls `loadInitialPage`, which parses the URL and `loadPage('form.php?form=klanten')` → `fetch('/cma/main.php?nomenu&amp;page=form.php?form=klanten')`. In nomenu mode main.php includes `form.php`, and `form.php:294–330` strips everything to just the `&lt;body&gt;` inner content + the form's init scripts. That fragment is injected into `#contentArea`.
> - Result: the sidebar/menu and header ARE present on `/cma/form/&lt;form&gt;` routes. There is NO separate standalone form page in normal use — `form.php` only emits a full HTML document when NOT in nomenu mode (the `showFormError` path at :73–95, or a direct non-shell hit), but real navigation always routes through the shell.
> - SPA navigation (clicking a menu item or in-content link) never reloads the shell; only `#contentArea` changes, and history is updated via `history.pushState` to the clean `/cma/form/&lt;form&gt;` URL (logic inside `loadPage` in `main.js`). There is also a page cache (up to 5 form pages) keyed by page URL with suspend/resume of form controllers (top of `main.min.js`).
>
> ## 6. Reusable web components (for a treeview-select / mega/BIG menu)
>
> All in `cma/webcomponents/` unless noted:
> - `cma-tree.js` — tree view with folders/items, expand/collapse, state persistence (`storage-key`), `filter()`, `selectByHref()`, `expandAll/collapseAll`, emits `item-click`. Already used by tools. Strong candidate for a treeview-select.
> - `cma-fold.js` — draggable divider to resize adjacent panels (vertical/horizontal), state-persistent. Used for the tools two-pane split.
> - `cma-toolbar.js` — flexible toolbar with left/center/right sections (see also `ToolbarHelper` PHP used in tools.php:195–208).
> - `cma-tabs.js` — tab strip that auto-collapses to a `&lt;select&gt;` dropdown on mobile; also supports wizard/step mode. Useful for responsive nav.
> - `cma-base-component.js` — shared base class for the CMA components.
> - `shared-icons.js` — shared icon set for the components.
> - `library/webcomponents/lib-menu.js` — generic menu component (NOT currently used for primary nav).
> - `library/webcomponents/lib-combo.js` — combobox/select component.
> - `library/webcomponents/lib-table.js`, `lib-message.js`, `lib-search-input`, `lib-label`, `lib-dialog` — supporting UI primitives (search input used in tools.php:198; dialog used for password modal in main.js).
> - Note: there is NO `cma-header` component — the header is inline markup, so a nav rework can introduce one cleanly.
>
> ## 7. Mobile / responsive
>
> - Primary breakpoint: `@media (max-width: 768px)` in `cma/assets/css/main.css:977` (sidebar app-shell). Sidebar becomes a fixed off-canvas drawer (`transform: translateX(-100%)`; `.cma-sidebar.open` slides in). `#menuToggle` hamburger and `.cma-mobile-menu-btn` become visible; the desktop collapse button `.cma-toggle-btn` is hidden; `.cma-sidebar-backdrop` is force-hidden on mobile.
> - JS: `toggleSidebar()` (in `main.js`) — below 768px toggles `.open` on `#sidebar` and syncs `#menuToggleCheckbox`; above 768px toggles `.collapsed` (persisted to `localStorage` key `cma_v2_menu_collapsed`). Collapsed desktop sidebar shows hover popups (`.cma-menu-popup`, wired via function `x()` in the bundle).
> - Classic-mode responsive: `main.css:1283` (`max-width:1200px`) and `main.css:1289` (`max-width:768px`) restyle `html.classic-mode .cma-sidebar` into a horizontal top bar that wraps.
> - `cma/assets/css/style.css` breakpoints (form/content chrome, not the shell nav): `min-width:960px` (:1003), `max-width:1024px` (:1164), `max-width:960px` (:1212, :1324), `max-width:768px` (:1851).
> - `main.css` also has height-based tweaks: `@media (max-height:800px)` (:959) and `max-height:700px` (:966) for the sidebar nav.
>
> ---
>
> ### Key files
> - `/mnt/c/repos/cma_platform/cma/main.php` — shell, sidebar, header, menu rendering.
> - `/mnt/c/repos/cma_platform/cma/assets/js/main.js` (`window.loadPage` at :693, `loadInitialPage` at :1489) and shipped `main.min.js` — the AJAX content-swap engine + page cache.
> - `/mnt/c/repos/cma_platform/cma/tools.php` — tools tree + iframe two-pane, shell-only guard (:51), tree click handler (:258).
> - `/mnt/c/repos/cma_platform/cma/form.php` — form renderer; nomenu body-stripping at :294.
> - `/mnt/c/repos/cma_platform/cma/web.config` — clean-URL rewrites (tools :65, form :105–139) all pointing at `main.php`.
> - `/mnt/c/repos/cma_platform/cma/config/menu.json` + `/mnt/c/repos/cma_platform/cma/classes/Services/MenuService.php` + `/mnt/c/repos/cma_platform/cma/menurep.inc` — menu data.
> - `/mnt/c/repos/cma_platform/cma/bootstrap.inc:637` (`cma_css_bundle`) — CSS bundle incl. `main.css` (shell/nav) and `style.css`.
> - `/mnt/c/repos/cma_platform/cma/assets/css/main.css` — all `.cma-header` / `.cma-sidebar` styling and mobile breakpoints.</result>
> <usage><subagent_tokens>57739</subagent_tokens><tool_uses>19</tool_uses><duration_ms>136567</duration_ms></usage>
> </task-notification>

> Na de bouw van het BIG menu: records 1-1600 van 1796 (laden...) -> dit blijft maar staan, kun je kijken of het continious laden wel goed gaat? 
>
> The readonlyIndicator, can we move that to the lib_sidepanel_header, centered 
>
> routing issue: https://www.karaatedelstenen.nl/cma/form/steensoorten/6/steensoorten_producten/2039 should open 2 sidebars 1 with steensoort 6 and above that steensoorten_producten 2039, but it only opens https://www.karaatedelstenen.nl/cma/form/steensoorten/6

> the menu does not unfold AND the productie label is in the way, can we move that label to the top right corner?

> the documentation uses lnr-bug, but that definition is missing in css 
>
> .tool-docs .docs-content { remove max-width } 
> remove css rule : .listtable .lnr-database {
>     margin-right: 5px; 
> }

> you crashed, what is it you were working on?

>
> OPcache geleegd via app-pool recycle
> opcache_reset() faalde, dus web.config is aangeraakt. IIS herstart nu de applicatiepool — dat leegt OPcache én APCu volledig. De eerstvolgende paginalading kan iets trager zijn (koude start).
>
> in that case I would expect all caches to me empty? But is still see :
>
> OPcachei    APCui    App Cachei    File Cachei    Minifyi    Form HTMLi    Invalidationi    Realpathi    Groupsi    Sessionsi    Tempi    JS Minifyi
> ✓    ✓    ✓    ✓    –    –    –    ✓    ✓    –    –    –
> 141 items    9 items    9 items    6 items    0 bestanden    0 bestanden    Geen signalen    Intern (PHP)    2 groepen    0 bestanden    0 bestanden    0 bestanden

> no you were working on a big menu in the tools.php to skip the tree view currently in use (archive that by reming to _DEPRECATED, don't delete it)

> Not true becauase if i refresh the same numbers appear, it is geniounly not cleared

> yes commit and push please and can you bump karaat to force an update?

> once the tools menu has been installed it now stays visible , that is s state issue. I think it is better to move the tools menu to the #contentArea in a separate toolbar with just = menu in it. that prevents state management issues

> what is the cma_platform version i should see?

> i don't see the cma-toolbar, I do see the title that was removed and a real lib-search field

> can we make sure through a .user.ini that the opcache settings are correct instead of relying on global server settings?

> and you can catch a false return value on the reset as well. or is that the trigger to touch web.config?

> is there an iis setting that speficies monitoring files for changes? I say one in the fastcgi settings, but that was overall not site-epceific

> tools-empty lacks a space between the Menu word. And if that is shown, force opening the menu and hide it
>
> .cma-launcher__panel { position:absolute; width:100% ;     z-index: 999;} 
>
> .cma-launcher-btn is still also inside the cma-header, probably added through js

> Query uitvoering mislukt: Native ODBC error: [Microsoft][ODBC Microsoft Access Driver] Circular reference caused by alias 'Notificatie' in query definition's SELECT list.SELECT ID, datestamp, Username, Form, Formname, Actie, Left(Notificatie, 100) AS [Notificatie]FROM tblCMAMonitoringORDER BY ID DESC
>
> https://www.karaatedelstenen.nl/cma/main.php?page=tools.php%3Ftool%3Dformedit /: show the active tools page using { background-color: var(--tree-hover-bg, #e8f4fc);
>     border: 1px solid var(--color-info, #077ab2); }
>
> .tools-page .tools-toolbar .cma-launcher-btn {    padding-left: 0px !important; }

> can we not use left(notificatie,100) as Melding, if not add display-side truncation

> https://www.karaatedelstenen.nl/cma/main.php?page=tools.php%3Ftool%3Ddb_sync allows updatingform definitions, but after updating the forms they need to be downloaded to be implemented into github, can we make a download button for each form that was updated and a note where they should be placed (cma / site specific)

> #toolbar .select, #toolbar .select2 { min-width: 60px } and the endpoint tester has toolbar icons without tooltip. I want tooltips on all toolbar buttons to explain their functionality, please add them everywhere expecially within the tools menu.

> the endpoint tester has quite some errors: 
> Naam    URL    Status    Tijd    Details
> Blank page    /cma/blank.php    Fout    39 ms    HTTP 500 Internal Server Error
> Task page    /cma/task.php    Fout    27 ms    HTTP 500 Internal Server Error
> Naam    URL    Status    Tijd    Details
> Imageupload crop upload handler page    /cma/imageupload_crop_upload_handler.php    Fout    53 ms    Geen upload pad opgegeven
> Naam    URL    Status    Tijd    Details
> Email actions api    /cma/api/email-actions.php    Fout    29 ms    Ongeldig ID
> Form definition api    /cma/api/form_definition.php    Fout    28 ms    HTTP 400 Bad Request
> Form list api    /cma/api/form_list.php    Fout    36 ms    formId or formName is required
> Form record api    /cma/api/form_record.php    Fout    34 ms    formId or formName is required
> Form subform api    /cma/api/form_subform.php    Fout    25 ms    FormID or form name is required
> Report definition api    /cma/api/report-definition.php    Fout    47 ms    HTTP 400 Bad Request
> User actions api    /cma/api/user_actions.php    Fout    33 ms    HTTP 400 Bad Request
> User tips api    /cma/api/user_tips.php    Fout    45 ms    HTTP 400 Bad Request
> Naam    URL    Status    Tijd    Details
> Report export api    /cma/api/report-export.php    Fout    35 ms    HTTP 400 Bad Request
> Report query api    /cma/api/report-query.php    Fout    29 ms    HTTP 400 Bad Request
> Report schema api    /cma/api/report-schema.php    Fout    42 ms    HTTP 400 Bad Request
> Naam    URL    Status    Tijd    Details
> Record: users #1 api    /cma/api/form_record.php?formName=users&id=1    Fout    39 ms    Record met ID '1' niet gevonden
> Record: groups #10 api    /cma/api/form_record.php?formName=groups&id=10    Fout    32 ms    Record met ID '10' niet gevonden
> Naam    URL    Status    Tijd    Details
> Tools - Diag page    /cma/tools/diag.php    Fout    28 ms    HTTP 403 Forbidden
> Tools - Documentation page    /cma/tools/documentation.php    Fout    244 ms    Parse error\" \/ \"Unsupported declare 'strict_types'\"\/deploy.php vereist PHP
> Tools - Consistency picture delete page    /cma/tools/tools_consistency_picture_delete.php    Fout    177 ms    Fatal error
> Tools - Missing files page    /cma/tools/tools_missing_files.php    Fout    42 ms    Fatal error
> Tools - Picture analyse page    /cma/tools/tools_picture_analyse.php    Fout    30 ms    Fatal error
> Tools - Picture analyse delete page    /cma/tools/tools_picture_analyse_delete.php    Fout    44 ms    Fatal error
> Tools - Set picture sizes page    /cma/tools/tools_set_picture_sizes.php    Fout    53 ms    Fatal error
> Naam    URL    Status    Tijd    Details
> File frameset page    /cma/wizards/file_frameset.php    Fout    24 ms    HTTP 500 Internal Server Error
>
> determine if the files that are tested are still needed and actively used within the cma platform and if so, it the errors can be solved. I suspect this list of tested files is incomplete, can we analyse other files to test as well

> please review prompts.md from 2 weeks ago until 1 hour ago and determine if all items have been dealt with

> https://www.karaatedelstenen.nl/cma/tools/tools_dbsummary.php shows empty icons in the toolbar, can you fix that? 
>
> https://www.karaatedelstenen.nl/cma/form/steensoorten_producten/4563 still shows 2 forms, already reported earlier, did you work on that? 
>
>
> records 1-1500 van 1796 (laden...) -> also already reported, what is the status?

> 2: no the steensoorten_producten/4563 is shown twice, not the parent and the child. 
>
> 3: that sounds fixable right? Loop nutil hasMore is false? 
>
> If we need to refactor form-controller.js, please note it in todo.md, I agree that 5000 lines is too much for a single js file and is unmaintainable. 
>
> Start with 3 and then again look at 2 with the new knowlegde.

> #3 okay, that suprises me. because i always have a unique id, there are just 1700+ records. so the safeguard is actually wrong an unnecessary.  You can check out the sql in the json file and see what it does. 
>
> 2 no there are 2 sidepanels stacked upon each other with exactly the same content, not a detail and a sidepanel. Perhaps a simple safeguard could be implemented that a sidebar is not opened if the one on top has the same url? bit of a patch/workaround, but i think it will work

> i wanted to run the sql's but that leads to another bug find: whatever i do: i get no result : Resultaat:
>
> 0 records
>   , can you check?

> .toolbar-select .select, .toolbar-select .select2 { min-width:50px}

> content blocks form should contain a tips section (standard form element)  about how to define and use variables

> the groups/users/marketing url/email lig and other forms should be callable both ways

> also test forbtge general left  menu pane, because in the mentioned case that is gone too

> If ingive ypu a login for the cma, can you storenit and try for yourself?

> username claude - pwd claude!

## 2026-07-04

> i ran composer update on the server .56 now installed, can you re-test?

> cma: https://www.karaatedelstenen.nl/cma/main.php?page=tools.php%3Ftool%3Dformedit does not fill the entire screen

> ?

> automatische backup loopt niet: Versie 1.0.0: Initiële versie - versiebeheertabellen aanmaken
>   Automatisch backup staat aan.
>   ⚠ Database 'rep' niet gevonden in configuratie, backup overgeslagen
>   ⚠ Database 'users' niet gevonden in configuratie, backup overgeslagen
>   ⚠ Database 'data' niet gevonden in configuratie, backup overgeslagen
>
>   3 wijziging(en) uit te voeren...
>   [1/3] ✓ Versietabel aanmaken

> there is a version coonflict on site specific migrations, they are not executed because 1.0.0 has 2 occurences

> worked! bit i motices the tools big menu is not at https://www.karaatedelstenen.nl/cma/main.php?page=tools%2Ftools_migrations.php

> having to rerun the movie grations created a new menu.json, it a menu.json exists don’t overwrite it, i now lost the quick add a stone and have many dead links, it creates links to forms that don’t exist, highly unwanted

> can we introduce a maintenace screen, running composer update takes a while and a visitor is treated with many errors in between

> Yes please, keep it simple and say something like We zijn even bezig met de website, duurt niet lang.. Blijft u deze melding langer dan 10 minuten zien? App me even op 0654752275.

> whatsapp is prima. Moeten we dit niet ook voor andere consumer sites doen? Kunnen we een maintenance.php maken die dynamisch logo, emailadres enzo ophaalt?

> ja doe maar voor alle andere sites, en kunnen we de maintenance stand in het systeem menu opnemen? eventueel met een berichtop maat (post[= ander bericht)?

> de ander is /mnt/c/repos/mijnrino_php uit mijn hoofd

> Leeg laten? Dan gebruikt de pagina het standaardbericht uit data/maintenance.json / data/app.json.
>  
> wil je de echte tekst als default in het tekstvat zetten? leeg niet toeataand, hulptekst weg

> platform : content blocks, the html and omschrijving needs to be required, variables are optional

> karaat: the link to the cma stone page is incorrect: https://www.karaatedelstenen.nl/cma/main.php?page=form.php%3Fform%3Dsteensoorten_producten&ID=5008

> the new stones in karaat on the homepage, start with single stones and then the pairs,

> ik zie nu records 1-1800 van 1806 (laden...) (aantal stenen geplaatt)

> ik zie nu records 1-1800 van 1806 (laden...) (aantal stenen geplaatt)
>
> de maintenance stand werkt niet: ik krijg nu Error in //index.php
>
> Error: Class "App\Library\Application" not found
>
> in C:\wwwroot\www.karaatedelstenen.nl\filter.inc:14
>
> #0 C:\wwwroot\www.karaatedelstenen.nl\header.inc(14): require_once()
> #1 C:\wwwroot\www.karaatedelstenen.nl\index.php(11): require_once('...')
> #2 C:\wwwroot\www.karaatedelstenen.nl\_bootstrap_wrapper.php(61): include('...')
> #3 {main} tijdens een composer update

> ja graag

> nu 1-200 van 1806 ??

> kun jij sen deploy forceren? geen toegang tot de server nu

> je hebt de deploy secret in de locale .env staan

> atart maat met de webp en form height

> i have no access to server now, will have to wait

> can you bump rec? samee server as karaat and can fill the cache

## 2026-07-05

> leave it for now
>
> ecords 1-400 van 1806 , on mobile
>
> reports big menu does not close when i click on the trigger again
>
> the height of reports is not 100%, same as with forms
>
> https://www.karaatedelstenen.nl/cma/main.php?page=tools.php%3Ftool%3Dwebp_convert if i scroll the page is reloaded?? and it starts by scanning the default dir, let’s not do that, the page is too slow

> PLAN: Gemstone plausibility validation via specific gravity (SG)
>
> CONTEXT
> - Existing PHP project (karaatedelstenen). Use the existing codebase,
>   database access layer, and CSS styling. Do NOT use inline CSS.
> - Database contains: stone types (species) and individual stones with
>   dimensions (height, width, depth), cut style, and weight in carats.
> - Goal: for each stone, estimate SG from dimensions + weight, compare
>   with the reference SG of its stone type, and store/show a reliability
>   indicator on a 1-5 scale.
>
> STEP 0 - INSPECT, DO NOT ASSUME
> - Read the schema of the stone types table and the stones table.
>   Confirm: column names, units of dimensions (mm assumed - verify),
>   weight unit (carat assumed - verify), how cut style is stored
>   (free text, enum, foreign key?).
> - Check whether the stone types table already has a specific gravity
>   column. If not, this must be added (step 2).
> - List the distinct cut style values actually present in the data.
> - STOP and report findings before writing code if anything is
>   ambiguous (unknown units, missing columns, inconsistent cut values).
>
> STEP 1 - CALCULATION MODULE
> - Create one PHP class/function (following existing code conventions)
>   that computes:
>     grams = carats / 5
>     volume_cm3 = (L_mm * W_mm * D_mm * fill_factor) / 1000
>     sg_calculated = grams / volume_cm3
> - Fill factors per cut style (approximate):
>     round brilliant: 0.52
>     oval: 0.53
>     cushion: 0.57
>     emerald/octagon/baguette: 0.62
>     princess/square: 0.60
>     pear: 0.48
>     marquise: 0.45
>     trillion: 0.48
>     cabochon: 0.65
>     heart: 0.50
>     unknown/other: use 0.55 and flag lower confidence
> - Map the database's actual cut style values to these factors
>   (based on the distinct values found in step 0). Put the mapping in
>   a config array, not hardcoded in logic.
>
> STEP 2 - REFERENCE DATA
> - Ensure the stone types table has: sg_min and sg_max (a range, not a
>   single value, because natural variation exists).
> - If the columns are missing: add them via a migration/ALTER script
>   and populate for the types present in the database. Ask me to
>   review the populated values before applying.
>
> STEP 3 - RELIABILITY SCORE (1-5)
> - Compute deviation = how far sg_calculated falls outside [sg_min, sg_max],
>   expressed as a percentage relative to the range midpoint.
> - Scoring:
>     5 = inside the reference range
>     4 = outside range but within 5% of nearest bound
>     3 = 5-12% outside
>     2 = 12-25% outside
>     1 = more than 25% outside (likely wrong type or fake)
> - Confidence penalties (subtract 1, minimum 1):
>     - cut style unknown/unmapped
>     - any dimension missing or zero -> no score at all, mark as
>       "not assessable" instead of guessing
> - Store per stone: sg_calculated, score, and a short reason string
>   (e.g. "SG 5.7 vs expected 3.50-3.53").
>
> STEP 4 - EXECUTION
> - Build it as a batch script/command that processes all stones and
>   writes results to the stones table (add columns if needed, same
>   review rule as step 2).
> - Make it re-runnable (idempotent): recalculates and overwrites.
>
> STEP 5 - PRESENTATION
> - Add the score to the existing stone detail/list views using the
>   existing CSS classes and styling patterns. No inline CSS.
>   If a suitable visual pattern (badge/label) already exists in the
>   project, reuse it.
>
> STEP 6 - VERIFY
> - Show me: 10 sample results across different scores, plus any stones
>   marked "not assessable", before considering this done.
>
> GENERAL RULES
> - If units, schema, or cut values are unclear at any point: stop and
>   ask, do not guess.
> - Keep the fill factor table and SG reference data editable (config
>   or database), not buried in code.

> Make sure the 1-5 scale is not yet display to front-end, create a report with a link to the stone´s detail page of all stones and their sg-score

> Please commit and push.
>
> Desktop now shows: records 1-1800 van 1806 (laden...) and Mobile records 1-400 van 1806 (laden...)

> okay stop. I want you to look up the gravity from the gemdat link in each soorten record. Sillmanite for instane has Specific Gravity    3.20 to 3.26 .

> about that import, please also check if the refractive index and the hardness mohls scale are correct, in fact, just fill it with the value of gemdat

> @"/home/diede/.claude/uploads/fe158292-4673-4e2e-b4b7-5159aecd6533/f0a884ba-IMG_5855.png" i aill check, in the mean time the forma are si
> till not 100% heigh on mobile, please in estigate and aolve permanently, test yourself

> <task-notification>
> <task-id>b91xqwpo3</task-id>
> <tool-use-id>toolu_01Fvy1UK9uYNmnPhaJC8H5Jh</tool-use-id>
> <output-file>/tmp/claude-1000/-mnt-c-repos-cma-platform/fe158292-4673-4e2e-b4b7-5159aecd6533/tasks/b91xqwpo3.output</output-file>
> <status>completed</status>
> <summary>Background command "Run mobile height measurement against live" completed (exit code 0)</summary>
> </task-notification>

> did you include these tools in the site specifiic section of the tools menu?

> the numbers for the shapes, where do yhey come from? i find the cabouchon to be quite low on the values

> no i want a reliable source like gemdat.org

> do youbhave a link, incan retrieve them

> place the link so i can paste it, noq they all 404

> leave the cabouchons, and the 1 value, is thatvthe average of the 2 values we calculated?

> no the values ofbtge 3 spurces, they have 1 number, is that number close to our average?

> about the shapes: create a tool that determined the shape for stones that are not round and not oval , using the image and ai, make a 20 piece dry run and a full run, show the current shape and the proposed (new) shape. is we do this the shapes need to be correct

> are the tools now mentioned in the tools menu?

> <task-notification>
> <task-id>bptnpj6bc</task-id>
> <tool-use-id>toolu_017YRL22QLKw2ash7jk6aWHR</tool-use-id>
> <output-file>/tmp/claude-1000/-mnt-c-repos-cma-platform/fe158292-4673-4e2e-b4b7-5159aecd6533/tasks/bptnpj6bc.output</output-file>
> <status>completed</status>
> <summary>Background command "Wait for karaat to reach .85" completed (exit code 0)</summary>
> </task-notification>

> If a stone is outside the predicted range, can we compare the sg with glass ? If it falls within that range, note in the ag explaner: nieuwe rgel+‘Deze steen valt buiten de verwachte gewichtstange maar binnen die van glas. Mogelijk glas dus.’

> can we harden these tools to accept no parameters? if a parameter is missing, select the stone first?

## 2026-07-06

> loading external images leads to a lot of javascript errors: can we skip loading files from cloudfront.net?

> now it a report can open a form that only works when you click the icon in the first row, please suppoprt clicking on any row (the entire row)

> <task-notification>
> <task-id>bvs1hv1ky</task-id>
> <tool-use-id>toolu_017PUWWNYJLw1rmiVNdCwbV4</tool-use-id>
> <output-file>/tmp/claude-1000/-mnt-c-repos-cma-platform/fe158292-4673-4e2e-b4b7-5159aecd6533/tasks/bvs1hv1ky.output</output-file>
> <status>completed</status>
> <summary>Background command "Wait for karaat to reach .87" completed (exit code 0)</summary>
> </task-notification>

> <task-notification>
> <task-id>b70ahh8i9</task-id>
> <tool-use-id>toolu_01CAA6tpbsDvhtbEDCdXCyAG</tool-use-id>
> <output-file>/tmp/claude-1000/-mnt-c-repos-cma-platform/fe158292-4673-4e2e-b4b7-5159aecd6533/tasks/b70ahh8i9.output</output-file>
> <status>completed</status>
> <summary>Background command "Wait for karaat to reach .88" completed (exit code 0)</summary>
> </task-notification>

> The X of the menu now dissapears when scrolling and i still don´t see the image´s float:left, .88 is live

> One shape svg is not present: cabouchon, based on the existing svg's, can you create a new one for cabouchon? And add it to the filter page

> cma_platform: the error -> Verwijderen mislukt: SQLSTATE[HY000]: General error: -1612 [Microsoft][ODBC Microsoft Access Driver] The record cannot be deleted or changed because table 'tblProducten' includes related records. (SQLExecute[-1612] at ext\pdo_odbc\odbc_stmt.c:266) 
>
> i thought we had cleanued up arrors expecially these once to say something like  'Er zijn nog records in de tabel Producten' and skip all other numbers/drivernames/source-references

> chrome: false
> VM42495:2 val: 202 vis: hidden
> VM42495:3 CKEDITOR: object CreateFKEditor: function
> VM42495:5 instance: true status: unloaded
> VM42495:6 chrome: false
> VM42496:2 val: 202 vis: hidden
> VM42496:3 CKEDITOR: object CreateFKEditor: function
> VM42496:5 instance: true status: unloaded
> VM42496:6 chrome: false
> VM42498:2 val: 202 vis: hidden
> VM42498:3 CKEDITOR: object CreateFKEditor: function
> VM42498:5 instance: true status: unloaded
> VM42498:6 chrome: false
> VM42499:2 val: 202 vis: hidden
> VM42499:3 CKEDITOR: object CreateFKEditor: function
> VM42499:5 instance: true status: unloaded
> VM42499:6 chrome: false
> VM42500:2 val: 202 vis: hidden
> VM42500:3 CKEDITOR: object CreateFKEditor: function
> VM42500:5 instance: true status: unloaded
> VM42500:6 chrome: false
> VM42501:2 val: 202 vis: hidden
> VM42501:3 CKEDITOR: object CreateFKEditor: function
> VM42501:5 instance: true status: unloaded
> VM42501:6 chrome: false
> VM42503:2 val: 202 vis: hidden
> VM42503:3 CKEDITOR: object CreateFKEditor: function
> VM42503:5 instance: true status: unloaded
> VM42503:6 chrome: false
> VM42504:2 val: 202 vis: hidden
> VM42504:3 CKEDITOR: object CreateFKEditor: function
> VM42504:5 instance: true status: unloaded
> VM42504:6 chrome: false
> VM42505:2 val: 202 vis: hidden
> VM42505:3 CKEDITOR: object CreateFKEditor: function
> VM42505:5 instance: true status: unloaded
> VM42505:6 chrome: false
> VM42506:2 val: 202 vis: hidden
> VM42506:3 CKEDITOR: object CreateFKEditor: function
> VM42506:5 instance: true status: unloaded
> VM42506:6 chrome: false
> VM42508:2 val: 202 vis: hidden
> VM42508:3 CKEDITOR: object CreateFKEditor: function
> VM42508:5 instance: true status: unloaded
> VM42508:6 chrome: false
> VM42509:2 val: 202 vis: hidden
> VM42509:3 CKEDITOR: object CreateFKEditor: function
> VM42509:5 instance: true status: unloaded
> VM42509:6 chrome: false
> VM42510:2 val: 202 vis: hidden
> VM42510:3 CKEDITOR: object CreateFKEditor: function
> VM42510:5 instance: true status: unloaded
> VM42510:6 chrome: false
> VM42512:2 val: 202 vis: hidden
> VM42512:3 CKEDITOR: object CreateFKEditor: function
> VM42512:5 instance: true status: unloaded
> VM42512:6 chrome: false
> VM42513:2 val: 202 vis: hidden
> VM42513:3 CKEDITOR: object CreateFKEditor: function
> VM42513:5 instance: true status: unloaded
> VM42513:6 chrome: false
> VM42514:2 val: 202 vis: hidden
> VM42514:3 CKEDITOR: object CreateFKEditor: function
> VM42514:5 instance: true status: unloaded
> VM42514:6 chrome: false
> VM42515:2 val: 202 vis: hidden
> VM42515:3 CKEDITOR: object CreateFKEditor: function
> VM42515:5 instance: true status: unloaded
> VM42515:6 chrome: false

> i do like the fallback, after failed ckeditor-loading that sounds like a good idea?

> please remove all logging that contains [MenuService::getFormAccessLevel]

> after using the veldkiezer the display swaps to tree view, which is useless because the field chooser is specifically meant for table view.

> case-seneitivity: start there , 404 counter and graph please on dashboard of cma cma-blockeditor is now a fake one (in storybook), i wonder if we should just update storybook

> i don't want blockedit, because that is functionally a different control. I disagree with you assesment of ckeditor, in the blockeditor we regularly create and destroy them without issues. Upgrading is still an option

> please commit all, including other changes you did not make.

> Can you find other remnants of debugging sessions and report back to me? At this point I am not debugging anything apart from the continious loading issue that we cannot seem to solve, so all other debugging can be safely removed.

> upgrading mijn rino php version to latest platform lead to: ErrorException
> require_once(C:\lab\ai_conversion\site\library\lib_htmleditor.inc): Failed to open stream: No such file or directory

> yes please

> yes please commit

> darkmode: .subform-content is still white hardcoded, datepicker-calendar open is not darkmode aware , detail popup: cma-form body with classes 'mode-tree has-subforms-defined is-creating mode-detail popup' also not darkmode aware

> merge push and commit

> if the page switches through a click on the menu the reports menu is still visible, solve that in the component or perhaps make an overlay sonit cannot happen? and the toggle does not always work

> on cutstom buttons in forms: the lib_current_domain does not add the port number if it is different  than 80 or 443, please make that happen

> okay, i want detailed tests for all classes in the library inclusing edge cases and anything you can think of that might go wrong. Also test for error-handling

> also check the existing tests for their test-coverage

> <task-notification>
> <task-id>a4e64e6b7a5c3dc03</task-id>
> <tool-use-id>toolu_01NZtZ3M9ahEepiEwfBCbScH</tool-use-id>
> <output-file>/tmp/claude-1000/-mnt-c-repos-cma-platform/9acb05bb-e63e-4d18-894e-ce08c9e1a363/tasks/a4e64e6b7a5c3dc03.output</output-file>
> <status>completed</status>
> <summary>Agent "Test Cache file backend" finished</summary>
> <note>A task-notification fires each time this agent stops with no live background children of its own. The user can send it another message and resume it, so the same task-id may notify more than once.</note>
> <result>The `WebConfigCmaRoutesTest` failures are pre-existing and unrelated — they fail identically in isolation, before `CacheTest` existed. My new tests do not leak global state.
>
> ## Report
>
> **File created:** `/mnt/c/repos/cma_platform/cma/tests/CacheTest.php`
>
> **Result:** 40 tests, all passing (`php tests/TestRunner.php CacheTest`).
>
> **Backend / config used:** Forced the FILE backend by setting `$GLOBALS['Application']` config in `setUp`: `cma_caching=true`, `cache_backend='file'`, `cache_directory=&lt;throwaway temp dir&gt;`, `cache_log_enabled=false`. Because `Cache::init()` memoizes the chosen backend in a private static, each test resets all relevant statics (`backend`, `redis`, `cacheDir`, `enabled`, `hits`, `misses`, `logEnabled`, `invalidationFile`, `invalidationCache`, `lastInvalidationCheck`) via reflection in `setUp`/`tearDown`, and restores/removes the prior `$GLOBALS['Application']` and `rmrf`s the temp dir in `tearDown`. No Redis/APCu extension required or touched.
>
> **Coverage:** backend selection; set/get for string, empty string, int, zero, float, bool true/false, flat array, nested array, unicode; missing-key default (null and provided); overwrite; delete (present + missing); `has` (present/absent/after-delete); `clear`; TTL (long persists, null uses default, short TTL expires via `sleep(2)`); disabled-cache paths; hit/miss stats; corrupt-file → default + no fatal; unwritable path (dir planted at file location) → `set` returns false, no throw; raw file helpers `saveFile`/`loadFile` (round-trip, missing→null, BOM strip)/`clearFile`/`clearAllFiles`; and the file-based cross-instance invalidation (`setWithInvalidation`/`getWithInvalidation` round-trip, `invalidateGroup` expiring earlier entries, group isolation, signal-file write).
>
> **Uncovered methods (with reason):**
> - `retrieve`, `retrieveFromFile`, `executeQuery`, `retrieveSchema`, `fetchSchema` — require a live PDO/`Database` connection; out of scope for deterministic file-only tests.
> - Redis paths (`getRedis`/`setRedis`/`refreshInvalidationFromRedis`/`writeInvalidationSignal` Redis branch) and APCu paths (`getApcu`/`setApcu`) — excluded per instructions; would need the extensions.
> - `clearAll`, `getStatus` — thin wrappers over `opcache_reset`/`apcu_*`/`opcache_get_status`; environment-dependent, not deterministic. Left out.
> - `pruneInvalidationSignals`, `clearInvalidationSignals`, `getInvalidationStatus`, `isValid`, `resetStats` (partially exercised) — reachable but lower value; not exhaustively covered to keep the suite focused.
> - No `remember`/get-or-compute method exists on this class, so that requested scenario is N/A.
>
> **Suspected bug (pinned by `testStoringNullIsLossyReturnsDefault`):** Storing `null` is lossy. `getFile()` returns `$data['value'] ?? $default`, so a genuinely-stored `null` is indistinguishable from a miss — `get('k', 'DEFAULT')` returns `'DEFAULT'` even though `has('k')` returns `true`. The same `?? $default` reliance also drives `get()`'s hit/miss classification (`$result !== $default`), so a stored `null` is always counted as a miss. I did not modify production code; the test documents current behavior so a future change is noticed. (`false`, `0`, and `''` round-trip correctly — only `null` is affected.)</result>
> <usage><subagent_tokens>64027</subagent_tokens><tool_uses>9</tool_uses><duration_ms>179720</duration_ms></usage>
> </task-notification>

> <task-notification>
> <task-id>a0c7a59dbff8e44bb</task-id>
> <tool-use-id>toolu_01A9eUdjzjaSewQvHVaieFVi</tool-use-id>
> <output-file>/tmp/claude-1000/-mnt-c-repos-cma-platform/9acb05bb-e63e-4d18-894e-ce08c9e1a363/tasks/a0c7a59dbff8e44bb.output</output-file>
> <status>completed</status>
> <summary>Agent "Test Hilight, Table, Debug" finished</summary>
> <note>A task-notification fires each time this agent stops with no live background children of its own. The user can send it another message and resume it, so the same task-id may notify more than once.</note>
> <result>All 68 of my new tests pass. The 6 failures in the full run are in unrelated pre-existing suites (`ServerTest` path-traversal and `WebConfigCmaRoutesTest` web.config patching) — different domains, and my tests fully restore global state so they don't leak.
>
> ## Report
>
> Created three test files, all green:
>
> - `/mnt/c/repos/cma_platform/cma/tests/HilightTest.php` — 26 tests pass
> - `/mnt/c/repos/cma_platform/cma/tests/TableExtraTest.php` — 15 tests pass
> - `/mnt/c/repos/cma_platform/cma/tests/DebugTest.php` — 27 tests pass
>
> Verified individually and in the full suite (my suites contribute 0 failures to the 890/896 total).
>
> ### Coverage
> - **Hilight**: `text()` (basic highlight, default/custom class, case-insensitive with original-case preservation, multiple/adjacent non-overlapping matches, empty term/text, absent term, unicode, forward-slash-in-term), the full regex-metacharacter group (`.`, `.*`, `*`, `(`, `[` all treated literally), `multiple()`, `setDefaultClass()`/`getDefaultClass()` (global state saved/restored in setUp/tearDown).
> - **Table**: `fromArray()` — empty array, single/multiple rows, alternating odd/even classes, header/cell HTML-escaping, null→`&amp;nbsp;`, numeric/zero values, missing keys (later rows), extra keys ignored, all id/class/cellspacing/cellpadding/headerClass/oddClass/evenClass options, and the numeric-keyed-first-row limitation.
> - **Debug**: `getActive/setActive`, `write()` in all four paths (inactive=silent, console with valid-JSON payload, screen, file), `setJsonMode` (suppresses screen, writes file), `array/multiArray/collection` (active/inactive/escaping), `postContent/paramContent/cookiesContent` (escaping; superglobals saved/restored locally), `settingsWrite` (off/console/screen), `recordset`/`fullRecordset` via an anonymous `fetchAll` stub. Private static state (enabled/toFile/toScreen/jsonMode/fileName) snapshotted and restored via reflection; file output redirected to a temp file that's cleaned up.
>
> ### Uncovered methods (with reason)
> - `Debug::init()` — resets state from `Application::get('omgeving')` and hardcodes the real `debug.log` path; testing it would clobber global state and touch the repo-root log file. Its effects (enabled flag, fileName) are already exercised via `setActive`/reflection.
> - `Debug::cookies()` and `Debug::postContent`/`paramContent` full round-trips through `write()` — the underlying rendering + escaping is covered by the `*Content()` tests and `write()` tests; the thin wrappers add no logic.
> - `Debug::getCurrentUrl()`, `writeToFile()` — private; exercised indirectly through `write()`/toFile tests.
> - `Table::fromRecordset()` — already covered by the existing `TableFromRecordsetTest`, not duplicated per instructions.
>
> ### Suspected bugs (reported, not fixed)
> 1. **Regex metacharacters ARE handled correctly.** `Hilight::text()` uses `preg_quote($searchText, '/')`, so `.`, `*`, `(`, `[`, `.*`, and `/` are matched literally — not as regex. No bug here; locked in by `testDotIsLiteralNotWildcard`, `testDotStarDoesNotMatchEverything`, etc.
>
> 2. **Hilight does NOT HTML-escape text or the term.** `text()` wraps matches via `preg_replace_callback` and emits `$matches[0]` plus surrounding text verbatim — no `htmlspecialchars` anywhere. If either the haystack or the search term contains attacker-controlled HTML (e.g. `&lt;script&gt;` in a search query reflected into results), it passes through unescaped — a potential stored/reflected XSS depending on caller usage. Documented by `testSurroundingHtmlIsNotEscaped` and `testMatchedHtmlTermIsNotEscaped`.
>
> 3. **`Hilight::multiple()` re-scans its own injected markup.** It applies each term sequentially to the accumulated output, so a later term that matches characters inside the injected `&lt;span class="..."&gt;` markup corrupts earlier highlights (e.g. term `c` matches the `c` in `class`). Documented by `testMultipleRescansInjectedMarkup`. A safe implementation would tokenize/match against the original text or escape/skip already-wrapped regions.
>
> 4. **`Debug::recordset()` rejects plain arrays.** The guard `if (!$rs || !is_object($rs))` returns "Recordset is empty or invalid" for any array, making the later `is_array($rs) ? $rs : $rs-&gt;fetchAll()` array-branch unreachable for real arrays — only objects with `fetchAll()` reach the dump path. Same dead `is_array` branch exists in `fullRecordset()`. Minor, but the array support is effectively non-functional. Tests use an object stub to reach the working path.
>
> No production code was modified.</result>
> <usage><subagent_tokens>60988</subagent_tokens><tool_uses>18</tool_uses><duration_ms>313426</duration_ms></usage>
> </task-notification>

> <task-notification>
> <task-id>a3ec280f54d546f85</task-id>
> <tool-use-id>toolu_01W74VNmS7CeV8d4vnC4KVZs</tool-use-id>
> <output-file>/tmp/claude-1000/-mnt-c-repos-cma-platform/9acb05bb-e63e-4d18-894e-ce08c9e1a363/tasks/a3ec280f54d546f85.output</output-file>
> <status>completed</status>
> <summary>Agent "Test Profiler, Log, File" finished</summary>
> <note>A task-notification fires each time this agent stops with no live background children of its own. The user can send it another message and resume it, so the same task-id may notify more than once.</note>
> <result>All three suites pass cleanly, individually and in the full run.
>
> ## Summary
>
> Created three test files, all passing (67 tests total):
>
> - `/mnt/c/repos/cma_platform/cma/tests/ProfilerTest.php` — 23 tests
> - `/mnt/c/repos/cma_platform/cma/tests/LogTest.php` — 14 tests
> - `/mnt/c/repos/cma_platform/cma/tests/FileTest.php` — 30 tests
>
> Full suite: 896 tests, 891 passing. The only 5 failures are **pre-existing** `WebConfigCmaRoutesTest` failures present in the baseline before my work (confirmed by running the suite with my files removed) — unrelated to this task.
>
> ### Coverage per class
>
> - **Profiler** — `setEnabled`/`isEnabled`/`verbose`, `start`/`getElapsed` (non-negative, positive after `usleep`, restart resets clock, auto-init), `mark`/`markEnd` (non-negative, unknown-marker no-throw, mark consumption, multiple independent marks, threshold gating), `threshold` storage, `end` (no-op when disabled, yellow-box echo captured via `ob_*` when enabled in test env), `tussenstand` (no-op when disabled, no-throw when enabled), `log` (CSV write, append, newline-stripping, dir creation).
> - **Log** — `init`/`getFilename`/`write`/`writeLine`/`close`: create+content, append-across-calls, append preserves pre-existing content, unicode, re-init switches file, close resets filename and is idempotent, and the fail-safe path (parent is a file → `init` returns `false`, no exception).
> - **File** — all public methods: `createAsciiFile`/`readAsciiFile` round-trip (accounting for the appended `PHP_EOL`), overwrite, dir creation, empty content, `exists` (file-only, not dirs), `folderExists`, `createFolder` (nested + `$raw` literal-path mode), `copy` (dup, dest-dir creation, missing source, preserves original), `delete` (incl. graceful missing-file and unicode filename), full lifecycle.
>
> ### Uncovered methods (with reason)
>
> - **Profiler::`getCurrentUrl`** — `private`, exercised indirectly through `log()`.
> - **File::`makeDirStepwise`** — `private`, exercised through `createFolder` (nested + raw tests).
>
> ### Key implementation notes (not bugs, but caller-relevant)
>
> - `File` and `Log` resolve paths through `Server::mapPath()`, whose `documentRoot`/`allowedPaths` are cached statics. Tests point them at a per-run temp dir (`sys_get_temp_dir() . '/…-' . bin2hex(random_bytes(6))`) via reflection, and **reset them to null in tearDown**. This reset was necessary: without it, the polluted `Server` statics caused `ServerTest` and `HilightTest` to fail later in the run. `ProfilerTest` similarly resets Profiler's own statics in setUp for order-independence.
> - `Debug::write()` (called internally by Profiler's `end`/`tussenstand`/`markEnd`) is a no-op while `Debug` is disabled — which it is by default in the test process — so those paths are safe to exercise without side effects.
>
> ### No suspected bugs in production code
>
> One minor design observation (not a bug): `Log::init()` assigns `self::$logFilename` **before** the directory-creation check, so after a failed `init()` `getFilename()` returns the (unusable) mapped path rather than `null`. The file handle is correctly left `null`. I adjusted the fail-safe test to assert on the return value and that no file was created, rather than on `getFilename()`. No production code was modified.</result>
> <usage><subagent_tokens>76694</subagent_tokens><tool_uses>23</tool_uses><duration_ms>325102</duration_ms></usage>
> </task-notification>

> <task-notification>
> <task-id>a8897d1687606153a</task-id>
> <tool-use-id>toolu_015ERr84hmxDrzQkyNMXDsah</tool-use-id>
> <output-file>/tmp/claude-1000/-mnt-c-repos-cma-platform/9acb05bb-e63e-4d18-894e-ce08c9e1a363/tasks/a8897d1687606153a.output</output-file>
> <status>completed</status>
> <summary>Agent "Test Server helper" finished</summary>
> <note>A task-notification fires each time this agent stops with no live background children of its own. The user can send it another message and resume it, so the same task-id may notify more than once.</note>
> <result>All 6 pre-existing failures are WebConfigCmaRoutesTest/HilightTest — none are ServerTest. Done.
>
> ## Report
>
> **File created:** `/mnt/c/repos/cma_platform/cma/tests/ServerTest.php`
>
> **Result:** 69 tests, all passing — both in isolation (`php tests/TestRunner.php ServerTest`) and within the full suite (no ServerTest failures; the 6 remaining suite failures are pre-existing in `WebConfigCmaRoutesTest` and `HilightTest`, unrelated to this work — they fail/order-depend independently of my file).
>
> **Coverage** (methods enumerated from `src/helpers/Server.php`):
> - `htmlEncode` / `htmlDecode` — empty, null, `&lt;&gt;&amp;"'`, unicode/multibyte, plain, long string, and full round-trips. Confirmed `ENT_QUOTES|ENT_HTML5` encodes `'`→`&amp;apos;`, `"`→`&amp;quot;`.
> - `urlEncode` / `urlDecode` — space→`+`, reserved chars, `+` sign, empty, already-encoded (double-encode), unicode, long-string round-trip.
> - `mapPath` — absolute existing/non-existent-under-root, backslash normalization, leading/duplicate slashes, `.`/`..` resolving inside (allowed) vs outside (throws), relative-via-SCRIPT_FILENAME, empty string, forward-slash output.
> - `getDocumentRoot` — string, no trailing slash, forward slashes.
> - `getVar` / `getAllVars` — present, missing→default, custom/non-string default, explicit empty value, key presence.
> - `getServerName`, `getServerSoftware`, `getProtocol` (HTTPS on / port 443 / plain / off), `getServerUrl`.
> - `setScriptTimeout` (30 and 0), `getScriptTimeout` (int, non-negative).
> - `createObject` — always-throws stub.
> - `execute` — error-handling paths only (traversal→false, missing file→false).
> - `addAllowedPath` — idempotency, trailing-slash tolerance, no doc-root side effect.
>
> **Superglobals:** `$_SERVER` is snapshotted in `setUp` and restored in `tearDown`.
>
> **Methods intentionally not fully covered (with reason):**
> - `transfer()` — skipped: `require`s a file then calls `exit`, which would kill the test runner. No pure sub-logic to test beyond `mapPath` (already covered).
> - `execute()` success path — skipped: would `require` and run an arbitrary include. Only its error branches are exercised.
> - `init()` / `normalizePath()` — private; exercised indirectly through `mapPath`/`getDocumentRoot`.
> - `setScriptTimeout` — return value tested; the actual `set_time_limit` side effect isn't observable in CLI and isn't asserted.
>
> **Environment note (not a production bug, but a test-design hazard worth flagging):** `Server::init()` caches `documentRoot` in a process-wide static with no reset, and in CLI `$_SERVER['DOCUMENT_ROOT']` is an empty string. An empty document root makes `allowedPaths` contain `""`, and `strpos($path, "") === 0` is always true, so **every path — including traversal — would be treated as allowed** and `mapPath` would never throw. I did not modify production code; instead I force `documentRoot`/`allowedPaths` via reflection in `setUp` (the same convention already used by `FileTest`/`LogTest`) so the tests are deterministic and order-independent.
>
> No production code was changed.</result>
> <usage><subagent_tokens>67909</subagent_tokens><tool_uses>19</tool_uses><duration_ms>389462</duration_ms></usage>
> </task-notification>

> <task-notification>
> <task-id>a654a89fd03e2642f</task-id>
> <tool-use-id>toolu_01QkRgmnQWqBSTpAaQ3VmZeb</tool-use-id>
> <output-file>/tmp/claude-1000/-mnt-c-repos-cma-platform/9acb05bb-e63e-4d18-894e-ce08c9e1a363/tasks/a654a89fd03e2642f.output</output-file>
> <status>completed</status>
> <summary>Agent "Test FormControls" finished</summary>
> <note>A task-notification fires each time this agent stops with no live background children of its own. The user can send it another message and resume it, so the same task-id may notify more than once.</note>
> <result>The 5 failures are all in `WebConfigCmaRoutesTest` — a completely unrelated file (web.config route injection), pre-existing and not caused by my tests. My `FormControlsTest` contributes 70/70 passing.
>
> ## Report
>
> **File created:** `/mnt/c/repos/cma_platform/cma/tests/FormControlsTest.php`
>
> **Result:** 70 tests, all passing (`php tests/TestRunner.php FormControlsTest`). No production code was modified. The 5 failures in the full-suite run are all in the unrelated `WebConfigCmaRoutesTest` and are pre-existing.
>
> **Coverage** — every public method of `App\Library\FormControls` is exercised: `combo` (both the dynamic/AJAX branch and the standard `&lt;select&gt;` shell), `text`, `textarea`, `checkbox`, `hidden`, `date`, `time`, `textBox`, `switchBox`, `url`, `file`, `label`, `getOptions`, `checkList`, `sortList`, plus the echo-vs-return output paths. Assertions verify correct tag/type, `name`/`id`/`value`, checked/selected/required/readonly/disabled state, option/placeholder rendering, int-coercion of `maxlength`, and HTML-escaping of hostile values (`&lt;script&gt;`, `"`, `'`, `&amp;`).
>
> **Not fully covered (with reason):**
> - The **option-rendering loop** of `combo` (standard path), `checkList`, and `sortList` — these pull rows via `Database::openRS()`, and injecting a fake `RecordSet` isn't possible without either a configured DB or a static mock. Per the "no live DB" rule I tested only their no-DB behaviour: `combo` still emits a valid `&lt;select&gt;` shell + empty option; `getOptions` returns an empty array; `checkList`/`sortList` throw an `Exception`. Their per-option `Server::htmlEncode` escaping is therefore unverified by these tests (though the code does call it).
> - Note: `FormControls` uses the bare constant `adOpenForwardOnly`, normally defined by Bootstrap. `setUp()` defines it (value 0) so DB-backed methods don't fatal on an undefined constant.
>
> **Suspected bugs — output-escaping / XSS holes (reported, NOT fixed):**
>
> 1. **`textBox()` date branch — real stored-XSS.** `src/helpers/FormControls.php:520` emits `&lt;script&gt;DatePicker("$name","$value");&lt;/script&gt;` with **both `$name` and `$value` completely unescaped**. A value like `"};alert(1)//` produces `&lt;script&gt;DatePicker("fld",""};alert(1)//");&lt;/script&gt;` — arbitrary JS execution from a DB-sourced date value. This is the most serious finding.
>
> 2. **`date()` `useScript` branch — line 406.** `'&lt;script&gt;DatePicker("' . $name . '",...'` interpolates `$name` unescaped into a `&lt;script&gt;` context. A name containing `"` or `&lt;/script&gt;` breaks out.
>
> 3. **`date()` inline branch — line 424.** `onclick="show_calendar('$name')"` uses `$name` raw; the `id`/`name` attributes are escaped, but this JS-string-in-attribute is not, so a `"` in the name breaks the attribute (confirmed: output `onclick="show_calendar('n"m')"`).
>
> 4. **`textBox()` password toggle — line 557** and **`sortList` toolbar onclicks** interpolate `$name` / field names into `onclick` JS strings unescaped. `sortList`'s field name is `srtlst_&lt;int id&gt;` so it's safe in practice; the `textBox` password one takes an arbitrary `$name`.
>
> The common pattern: values placed into **JavaScript-string contexts** (inside `&lt;script&gt;` or `onclick`) are either unescaped or escaped with `Server::htmlEncode` (HTML-encoding), which is the wrong encoding for JS and doesn't neutralize a `&lt;script&gt;`-context breakout. All standard HTML-attribute and element-text outputs (`text`, `textarea`, `hidden`, `checkbox`, `url`, `file`, `label`, `switchBox`, and `textBox`'s non-date branches) are correctly escaped via `Server::htmlEncode` and verified by the tests.</result>
> <usage><subagent_tokens>82140</subagent_tokens><tool_uses>21</tool_uses><duration_ms>353153</duration_ms></usage>
> </task-notification>

> <task-notification>
> <task-id>a4d0e7204968c6e8d</task-id>
> <tool-use-id>toolu_01Tv1MX7CnuZ1MuTbRdNYdm4</tool-use-id>
> <output-file>/tmp/claude-1000/-mnt-c-repos-cma-platform/9acb05bb-e63e-4d18-894e-ce08c9e1a363/tasks/a4d0e7204968c6e8d.output</output-file>
> <status>completed</status>
> <summary>Agent "Test ErrorHandler helpers + Response" finished</summary>
> <note>A task-notification fires each time this agent stops with no live background children of its own. The user can send it another message and resume it, so the same task-id may notify more than once.</note>
> <result>The 6 failures are in pre-existing, unrelated suites (`ArrTest`, `WebConfigCmaRoutesTest`) — my two files add 49 tests, all passing.
>
> ## Report
>
> **Files created:**
> - `/mnt/c/repos/cma_platform/cma/tests/ErrorHandlerHelpersTest.php` — 35 tests, all passing
> - `/mnt/c/repos/cma_platform/cma/tests/ResponseTest.php` — 14 tests, all passing
>
> Both run clean via `php tests/TestRunner.php ErrorHandlerHelpersTest` / `ResponseTest`. No production code was modified. The 6 failures in the full-suite run are pre-existing and in unrelated files (`ArrTest::testFindStrict`, `WebConfigCmaRoutesTest`).
>
> ### ErrorHandler coverage
> - **Secret redaction (the priority)** — `getRedactedEnvContent` + `scrubEmbeddedSecrets`, invoked via reflection against a temp `.env` with all the specified keys. Verified: plain host/URL/`PLAIN_URL` stay visible; `SMTP_PASS`/`ANTHROPIC_API_KEY`/`DEPLOY_SECRET` values are masked to first-char+`*****`; the `DATABASE_URL` userinfo password becomes `user:*****@`; the `ODBC` `Pwd=hunter2` becomes `Pwd=*****` (cleartext secret never appears). Also unit-tested `scrubEmbeddedSecrets` directly for URL userinfo, ODBC Pwd, inline `password=`/`token=`/`api_key=`, and plain-value pass-through. **No redaction misses found — the redaction is sound**, including the tricky `SMTP_PASS` case (caught by `/pass/i`, not a literal "password").
> - `isLocalEnvironment` — localhost/127.0.0.1/::1/.test/.local → true; real hostname and absent vars → false.
> - `viewerIsElevated` — asserted bool + no-throw (fails closed to false since `Cma\SecurityHelper` isn't loaded).
> - Other deterministic helpers: `generateErrorComment` (incl. `--`/newline neutralisation), `formatExceptionForLog` (incl. previous-exception chaining), `getDatabaseDiagnostics` (null / connection-refused / access-denied / driver-not-found), `getFileLines`, `getTraceFrames`, `formatFileSize`, `renderPermissionStatus`, `getRecentLogEntries`.
>
> ### Response coverage
> Response is almost entirely HTTP header I/O. Fully exercised the body-emitting methods by capturing output: `write`/`writeEncoded` (incl. XSS escaping), `json` (payload + unescaped slashes/unicode + custom options), `xml`, `download`/`inline` (missing-file and happy-path streaming), `clearBuffers`, `headersSent`. Header setters (`setHeader`/`setContentType`/`setStatus`/`cacheControl`/`noCache`/`cacheExpires`/`enableCors`) are guarded by `if (!headers_sent())` so they never fatal — asserted their bool contract and no-throw.
>
> ### Uncovered methods / coverage gaps (with reason)
> - **`ErrorHandler::wantsJson()`** — short-circuits to `false` when `PHP_SAPI === 'cli'`. Since the test runner IS the CLI SAPI, the true-branches (`/api/`, `_api.php`, `X-Requested-With`, `Accept: application/json`, JSON content-type header) are **unreachable in unit tests**. The test proves the CLI guard holds even with all JSON signals set, but the positive detection logic can only be covered by an HTTP/integration test.
> - **ErrorHandler rendering paths** (`renderJsonError`, `renderProductionError`, `renderDetailedError`, `renderCliError`, `renderHtmlError`, `handleException`, `handleShutdown`, `handleError`) — emit full pages and/or call `exit()`; out of scope per the task.
> - **`ErrorHandler::testDatabaseConnection`** — opens a real PDO connection; env-dependent, not unit-testable.
> - **`Response::redirect`/`redirectPermanent`/`notFound`/`serverError`/`forbidden`/`unauthorized`** — all call `exit()`, which would terminate the whole runner; deliberately avoided.
>
> **No suspected bugs found.** The secret-redaction logic correctly masks every secret in the required test matrix.</result>
> <usage><subagent_tokens>87484</subagent_tokens><tool_uses>15</tool_uses><duration_ms>318178</duration_ms></usage>
> </task-notification>

> <task-notification>
> <task-id>af3e6280980f94e52</task-id>
> <tool-use-id>toolu_0122jKaG92XvoyBiCkaU4PEa</tool-use-id>
> <output-file>/tmp/claude-1000/-mnt-c-repos-cma-platform/9acb05bb-e63e-4d18-894e-ce08c9e1a363/tasks/af3e6280980f94e52.output</output-file>
> <status>completed</status>
> <summary>Agent "Test WebConfigCmaRoutes + ImageProfiles" finished</summary>
> <note>A task-notification fires each time this agent stops with no live background children of its own. The user can send it another message and resume it, so the same task-id may notify more than once.</note>
> <result>Both new test files pass. The 2 failures (`ArrTest::testFindStrict`, `SQLTest::testPostStringNeutralisesInjectionAttemptSqlServer`) are pre-existing and in files I did not touch.
>
> ## Report
>
> ### Files created
> - `/mnt/c/repos/cma_platform/cma/tests/WebConfigCmaRoutesTest.php` — 6 tests, all passing
> - `/mnt/c/repos/cma_platform/cma/tests/ImageProfilesTest.php` — 16 tests, all passing
>
> ### WebConfigCmaRoutesTest (6 passing)
> Covers `applyToParent`: fresh valid config gets routes + marker injected (result well-formed, backup created, pre-existing rules preserved); idempotency (2nd run → `noop`, no duplicate rule, file unchanged); malformed XML → `error` with file left byte-for-byte intact; missing `&lt;rules&gt;` node → `manual` (graceful, no error); non-existent path → `skipped` (no fatal, file not created); hand-added rules → only the marker is stamped, no duplicate block injected, then idempotent.
>
> **Important environment caveat:** this PHP CLI (8.3.6) is built **without ext-simplexml / ext-dom / ext-xml**, and I lack root to `apt-get install php8.3-xml`. `WebConfigCmaRoutes` deliberately refuses (returns status `skipped`) when `simplexml_load_string` is absent. So I made each simplexml-dependent test self-adapting via a `skipIfNoSimpleXml()` guard: where the extension is missing it asserts the documented graceful-skip contract (status `skipped`, no errors, no backup, file untouched); where simplexml **is** present (every consumer site — the class's stated precondition — and any standard PHP build, which bundles simplexml) the full patch/idempotency/error assertions run. **In this sandbox the tests exercised the skip path; the full patch-logic assertions were written against the code but could not be executed here for want of the extension.** They will run on any host with ext-simplexml.
>
> ### ImageProfilesTest (16 passing)
> Fully exercised (only needs ext-json, which is present): `load` (missing file → empty config; valid profiles + managed_paths load; malformed JSON degrades gracefully, no fatal; wrong-typed sections coerce to empty), `clearCache` (cache reused until cleared, then re-reads disk), `getProfile` (found / unknown→null), `isManaged` (managed dir + subdir + backslash normalisation true; unmanaged false; false when no managed_paths), `imgTag` (unknown profile → HTML comment; src+alt; fallback extension follows the variant's `format`; srcset+sizes+base_url composition; no srcset when profile lacks one; attribute escaping via htmlspecialchars ENT_QUOTES).
>
> Config path is redirected by setting `Bootstrap::$rootDir` (private static) via reflection to a temp dir in `setUp`, restored in `tearDown`; `resolveSiteRoot()` then reads `&lt;tmp&gt;/data/image-profiles.json`.
>
> ### Uncovered methods (with reason)
> - `ImageProfiles::generate`, and its private helpers `renderVariant` / `cropCenter` / `resolveSourceExt` — call the GD-backed `App\Library\Image` class against real image files (resize/crop/WebP transcode). Out of scope for a config/lookup unit test; would need real image fixtures + GD. Noted in the test file's header docblock.
>
> ### Suspected bugs
> None. Both classes behaved exactly as documented for every input tested. The graceful-degradation paths (missing simplexml, malformed XML, malformed JSON, unknown profile, missing file) are all correctly fail-safe. No production code was modified.</result>
> <usage><subagent_tokens>82118</subagent_tokens><tool_uses>27</tool_uses><duration_ms>474524</duration_ms></usage>
> </task-notification>

> <task-notification>
> <task-id>a6c71ff9d41a5f4a5</task-id>
> <tool-use-id>toolu_01XP8H2BdhS6foUB8harXn4o</tool-use-id>
> <output-file>/tmp/claude-1000/-mnt-c-repos-cma-platform/9acb05bb-e63e-4d18-894e-ce08c9e1a363/tasks/a6c71ff9d41a5f4a5.output</output-file>
> <status>completed</status>
> <summary>Agent "Audit SQL/DB test coverage" finished</summary>
> <note>A task-notification fires each time this agent stops with no live background children of its own. The user can send it another message and resume it, so the same task-id may notify more than once.</note>
> <result>All my new tests pass. The single failure in the full run (`ArrTest::testFindStrict`) is in a file outside my scope that I never touched — pre-existing and unrelated to SQL/Database.
>
> ## Coverage audit — SQL &amp; Database unit tests
>
> Added **48 passing tests** across 5 in-scope files. Per-class verdict:
>
> ### `App\Library\SQL` (`src/helpers/SQL.php`) — was thin, now solid
> **Before:** `SQLTest` (38) exercised `postNumber`, `normalizeDecimal`, `postGuid`, `postTimeStr`, `sqlDateToRealDate`, the `add*`/`complicatedWhere` clause helpers. The escaping/injection surface (`postString`), `postBoolean`, `postDate*`, `guidEquals` had **zero** coverage.
> **Filled (SQLTest 38 → 61):** `postString` quote-escaping for both Access (`' &amp; chr(39) &amp; '`) and SQL Server (`'+char(39)+'`) dialects, two injection payloads (`'; DROP TABLE …`, `' OR '1'='1`) proving quotes stay balanced, metacharacter/`;`/`--`/backtick preservation, unicode passthrough, empty/null→`null`; `postBoolean` both dialects; `postDate`/`postDateOnly`/`postDateStr` (valid, empty-component, invalid-date, SQL-Server CAST); `guidEquals` (Access `LIKE` vs SQL Server `=`); decimal-locale hardening (`10.5`→`10.5`, `1.234,56`→`1234.56`, negatives/boundaries).
> **Remaining untestable/untested:** `processSQL` (huge Access↔SQL-Server dialect rewriter) and `convertDoubleQuotesToSingle` are pure and testable but large — left as a follow-up, not a connection blocker.
>
> ### 🚩 SUSPECTED HAZARD — `SQL::postNumber` is not injection-safe by itself
> `postNumber` does **not** validate numericness: non-numeric input is returned **trimmed but unquoted**. `SQL::postNumber('1 OR 1=1')` emits `1 OR 1=1` raw into SQL. This is documented ("callers validate with `is_numeric()`") but is a foot-gun — any caller that trusts `postNumber` to sanitise a numeric field has an injection hole. I pinned the current behaviour in `testPostNumberDangerousPassthrough` (did **not** change src). Contrast: `QueryBuilder::quoteValue('number')` *does* validate and collapses the same payload to `0` — so the QueryBuilder path is safe, the bare `SQL::postNumber` path is not. Worth an audit of `postNumber` call sites.
>
> ### `App\Library\Database` (`src/helpers/Database.php`) — safe/pure helpers extended
> **Before:** safe-wrappers, insert/exists, error-path logging, DSN building, drop-index idempotency all covered via `StubConnection`.
> **Filled:**
> - `DatabaseErrorPathTest` (7 → 13): `cleanErrorMessage` — SQLSTATE + error-code stripping, `[Microsoft][ODBC …]` prefix, `PDO::` prefix, capitalisation, empty-string safety, mb-aware unicode.
> - `DatabasesJsonDsnTest` (6 → 11): `isSQLServer` (Initial Catalog / SQLOLEDB / SQLNCLI / MSOLEDBSQL true; Access/SQLite false) and `isODBC` (true **only** when string starts with `DSN=`).
> **Remaining untestable:** connection-bound helpers (`quoteIdentifier`, `escape`, `getDriverName`, the `*PDO` schema mutators beyond drop-index) need a live/SQLite PDO; `quoteIdentifier`/`escape` swallow the no-connection path into a fallback, so they're only partially exercisable without a real driver — left to integration coverage.
>
> ### `Cma\QueryBuilder` (`cma/classes/QueryBuilder.php`) — good, extended
> **Filled (QueryBuilderTest 27 → 38, run directly via `php tests/QueryBuilderTest.php`):** number-value injection neutralised to `0`, text-value quote-escaping via `postString`, `BETWEEN` bounds, `getOperatorsForType` per-category counts + unknown-category fallback-to-text.
>
> ### `Cma\SchemaHelper` (`cma/classes/SchemaHelper.php`) — already strong, topped up
> **Filled (14 → 17):** `getSqlServerTypeName` (ODBC codes, length suffix, no-length). Pinned the **value-6 collision** (`SQL_FLOAT` and `ADO_CURRENCY` both = 6 → always resolves `float`, `money` unreachable) — same benign integer-collision class as the already-tested type-11 date/boolean case. Not a bug, but now visible.
>
> ### `StubConnection` (`cma/tests/StubConnection.php`) — fully covered, untouched
> `StubConnectionTest` (12) already pins queue FIFO, call recording, exec/insertId/driver/reset. No gaps; left as-is.
>
> **Notes:** No `src/` production code was modified. `SqlParserTest`/`QueryBuilderTest` are procedural (excluded from the default glob) and were run directly per their headers. The full-suite `ArrTest::testFindStrict` failure is pre-existing and outside scope.
>
> Files changed (all absolute):
> - `/mnt/c/repos/cma_platform/cma/tests/SQLTest.php`
> - `/mnt/c/repos/cma_platform/cma/tests/DatabaseErrorPathTest.php`
> - `/mnt/c/repos/cma_platform/cma/tests/DatabasesJsonDsnTest.php`
> - `/mnt/c/repos/cma_platform/cma/tests/QueryBuilderTest.php`
> - `/mnt/c/repos/cma_platform/cma/tests/SchemaHelperTest.php`</result>
> <usage><subagent_tokens>123085</subagent_tokens><tool_uses>35</tool_uses><duration_ms>338597</duration_ms></usage>
> </task-notification>

> <task-notification>
> <task-id>a7881c257200e201a</task-id>
> <tool-use-id>toolu_01RyxoZ9Uw1QeBGut6YsMFHZ</tool-use-id>
> <output-file>/tmp/claude-1000/-mnt-c-repos-cma-platform/9acb05bb-e63e-4d18-894e-ce08c9e1a363/tasks/a7881c257200e201a.output</output-file>
> <status>completed</status>
> <summary>Agent "Audit pure-logic test coverage" finished</summary>
> <note>A task-notification fires each time this agent stops with no live background children of its own. The user can send it another message and resume it, so the same task-id may notify more than once.</note>
> <result>Full suite green (1181 passing). Here is the coverage audit.
>
> ## Coverage audit — 7 helper classes
>
> ### 1. `Arr` (App\Library\Arr)
> - **Before:** ~42/43 public methods covered; `shuffle()` entirely uncovered; no strict-comparison, array-needle, or invalid-size guard branches tested.
> - **Filled:** `shuffle` (elements preserved / null / empty); `contains` array-needle branch + `strict`; `get` dot-notation with scalar intermediate + isset/null-falls-to-default semantics; `findInstr` empty-element gotcha; `find` strict vs loose; `find2DByRow`/`join2DByRow` scalar-column guards + missing index; `removeItem` multi-occurrence + not-present; `merge` all-null/no-args; `flatten` deep nesting; `chunk` zero/negative size guard; `fill` negative count; `pluck` objects; `sortBy` objects; `field` ArrayAccess (RecordSet-style) case-insensitive; `unique` key-preservation.
> - **Result:** 95 → **119 passing**.
>
> ### 2. `Str`
> - **Before:** ~24/26 methods covered but almost all ASCII; `toUtf8` real conversion, unicode paths, and null-arg branches untested.
> - **Filled:** `toUtf8` real Latin-1→UTF-8 conversion + already-valid + null; `truncate` sub-suffix edge + unicode; `stripEnd`/`stripStart` comma-string form + unicode; `capitalize`/`firstUpper` empty+unicode; `contains`/`replace` null args; `padZero` null/string; `slug` custom-sep collapse + empty result; `upper`/`lower`/`length`/`between`/`startsWith` multibyte; endsWith empty-pattern asymmetry.
> - **Result:** 72 → **99 passing**.
>
> ### 3. `Html`
> - **Before:** 5/5 methods hit but few `fixUnicode` branches, no double-encode/self-closing/attribute cases.
> - **Filled:** more `fixUnicode` maps (en-dash, bullet, ó/ü, ç); `encode` double-encoding + empty; `decode` named entities; `stripTags` multi-allowed + self-closing; `containsHtml` attributes + entities-only.
> - **Result:** 17 → **28 passing**.
>
> ### 4. `Date`
> - **Before:** broad method coverage but no DateTime-object inputs, no hour/minute/second `diff`/`add`, DST/toGMT were timezone-nondeterministic (only asserted "is bool").
> - **Filled:** `age` from DateTime/same-day/exact-birthday/invalid-ref; `diff` same-date/seconds/minutes/cross-year-months/negative-hours; `add` hours/minutes/negative month+year/unknown-unit default; Sunday weekday boundary; `isValid` numeric-strings/zero-day/month-range; `fixValue` already-Dutch + midnight-drops-time; `isParseable` DateTime/int; **deterministic** DST and `toGMT` (pinned Europe/Amsterdam + UTC, restored in finally).
> - **Result:** 66 → **91 passing**.
>
> ### 5. `Encryption`
> - **Before:** 1/1 method, only ASCII vectors.
> - **Filled:** hex-format regex, unicode bytes, binary/NUL, 100k-char input, avalanche.
> - **Result:** 4 → **9 passing**.
>
> ### 6. `ColumnMajorArray`
> - **Before:** `count()` override (its whole reason to exist — returns row not column count) **untested**; ragged/scalar-row paths untested.
> - **Filled:** `count()` returns row count (multi/empty/single-row-3-col); numeric column second-row + out-of-range; `getRow` first/empty; columns-from-first-row-only with ragged data; scalar-first-row → empty; stable row order.
> - **Result:** 13 → **23 passing**.
>
> ### 7. `StringBuffer`
> - **Before:** `saveToFile()` **untested**; no byte-vs-char size, type-coercion, or clear-then-reuse cases.
> - **Filled:** `saveToFile` (contents + empty buffer, temp files cleaned in `tearDown`); `getSize` counts bytes (multibyte); `appendLine('')` still emits CRLF; clear-then-reuse; float/bool coercion; live `__toString`.
> - **Result:** 7 → **16 passing**.
>
> ## Suspected bugs found (no src/ changed — tests lock current behavior)
> 1. **`Str::truncate()` overshoots when `length &lt; strlen(suffix)`.** `truncate('hello world', 2)` returns `'hello worl...'` (13 chars — far longer than the requested 2). Cause: `mb_substr($text, 0, $length - mb_strlen($suffix))` gets a negative length, which trims from the *end* instead of clamping to empty. `truncate(..., 3)` (== suffix length) collapses to just `'...'`. Locked in `testTruncateLengthSmallerThanSuffix`.
> 2. **`Str::startsWith`/`endsWith` empty-pattern asymmetry.** `startsWith('hello','') === true` but `endsWith('hello','') === false` (`mb_substr($t, -0, null)` returns the whole string, never `''`). Locked in `testEndsWithEmptyPatternAsymmetry`.
> 3. **`Arr::findInstr` matches on empty-string array elements.** An element of `''` is "found" for any haystack because `stripos($haystack, '')` returns `0` in PHP 8, so a list containing `''` always yields index 0. Locked in `testFindInstrEmptyElementMatchesEverything`.
>
> Minor (not bugs, documented): `Html::encode()` double-encodes existing entities (default `htmlspecialchars`); `Arr::get()` uses `isset()` so an explicit `null` value returns the default.
>
> ## Remaining gaps not tested (with reason)
> - `Date::mediumDate/longDate/fullDate` with `useRelative` left at its `null` default — that path calls `Application::get(...)`, an external dependency the existing tests deliberately avoid; I kept passing `false` as they do. The relative-name and explicit-`false` branches are fully covered.
> - `Str::padRight/padLeft` STR_PAD_BOTH branch of the bundled `mb_str_pad` polyfill isn't reachable through `Str`'s public API (only LEFT/RIGHT are exposed), so I left it alone.
>
> Final passing counts: **ArrTest 119, StrTest 99, HtmlTest 28, DateTest 91, EncryptionTest 9, ColumnMajorArrayTest 23, StringBufferTest 16** — all green, and the full runner stays at 1181/1181.</result>
> <usage><subagent_tokens>113514</subagent_tokens><tool_uses>38</tool_uses><duration_ms>429759</duration_ms></usage>
> </task-notification>

> can you condiem that all classes functions are called and tested with null strings, 0 numbers and other strange parameters?

> <task-notification>
> <task-id>aa96315c55d1da9dc</task-id>
> <tool-use-id>toolu_01QSSmtYqnJtdqp1cG5c3d6M</tool-use-id>
> <output-file>/tmp/claude-1000/-mnt-c-repos-cma-platform/9acb05bb-e63e-4d18-894e-ce08c9e1a363/tasks/aa96315c55d1da9dc.output</output-file>
> <status>completed</status>
> <summary>Agent "Audit CMA/forms/migration test coverage" finished</summary>
> <note>A task-notification fires each time this agent stops with no live background children of its own. The user can send it another message and resume it, so the same task-id may notify more than once.</note>
> <result>All 1239 tests pass, no production code touched. Here is the coverage assessment.
>
> ## Coverage assessment (per target)
>
> **FormRoute** (`cma/classes/FormRoute.php`) — Well covered before; missed `New=Y`+id interaction, copy defaults, popup list mode, and the `parentIdString()` accessor. Added 9: New-flag-keeps-id, copy-without-id, copy-default-false, popup-list-mode (no popupID), popup-parent-null-when-formID-empty, `parentIdString()` both branches, case-insensitive `popupID=NEW`, view default, unknown legacy FormID → empty form. 13→22.
>
> **FormExpressionEvaluator** — Operators/errors covered; missed numeric loose-compare, `==` precedence vs `&amp;&amp;`, non-chainable comparison, nested parens, literal equality. Added 9. 28→37.
>
> **JsonFormLoader::validateDefinitionData** — Missed non-array fields, file-type fields, URL paths, legacy path aliases, `tip` presentational, multi-problem accumulation, unknown-type default. Added 9. 10→19.
>
> **EmailLogService** — The existing `EmailLogTest` is DB-integration and only declares when a parent bootstrap exists, so it ran **zero** tests in the bare repo. Added an unguarded `EmailLogServiceUnitTest` (same file): pure `formatAddresses`/`parseAddresses` (+ round-trip) via reflection, and `getById`/`delete`/`cleanup`/`resend` against an injected `StubConnection` incl. SQL-shape asserts and the not-found / DB-error paths. 0→13.
>
> **DeployHealth** — Missed partial-missing, path normalisation, log-dir auto-create, no-recipient alert no-op. Added 4. 3→7.
>
> **EnvFile** — Missed `load()` (missing + real file), duplicate-key-last-wins, no-`=` line, invalid-key skip, empty value, single-quote literalness, unbalanced quote, `loadInto` `$_SERVER`/`putenv` side effects. Added 9. 8→17.
>
> **Installer::cleanRemovedPaths** — Added nonexistent-root no-op, same-basename-elsewhere survives (path specificity), and a guard that every `REMOVED_PATHS` entry is relative (no `/` or drive prefix). 7→10.
>
> **Installer::syncDirectory** — Added nested-recursion + nested-junk skip, and junk-only source produces an empty dest with no errors. 3→5.
>
> **MigrationService::resolveScriptPath** — Added trailing-slash sourceDir and empty-string-sourceDir → cma fallback. 5→7.
>
> **MigrationService::getWarnings/detectVersionIssues** — Added platform-only-is-clean, high-site-version-without-collision (reserved-range only, not "meerdere bronnen"), and empty-version-string ignored. 3→6.
>
> **FormDataProvider::buildEditChangelog** — Added label-defaults-to-name, null-old → "(leeg)", equal-arrays → no row, exactly-500-chars not truncated (boundary). 17→21.
>
> **FormDataProvider::buildDeleteChangelog** — Added string boolean `'1'`/`'0'` → Ja/Nee, 500-char boundary, case-insensitive label lookup. 14→17.
>
> **FormDataProvider::saveJsonFormRecord** — Added two error-path tests: verification `COUNT=0` → surfaced failure ("verificatie mislukt"), and a thrown `PDOException` on read-back → structured failure, no fatal. 8→10.
>
> **Table::fromRecordset** — Added HTML escaping of cells, NULL → `&amp;nbsp;`, record-count footer pluralisation (1 record / N records), id/class option rendering. 3→7.
>
> ## Suspected bugs
> None. Every error/edge path behaved defensibly and I asserted the current behavior: save verification failures and DB exceptions surface as `success=false`, `delete` swallows driver errors into `false`, `resend` of a missing id fails cleanly without sending, and validation accumulates one problem per offending field.
>
> ## Remaining untestable gaps (by design)
> - `EmailLogTest` (the DB class): `testSendAndLog`, `testTableExists`, real `resend` send — need a live `tblEmailLog` + SMTP; left to the bootstrap-guarded integration class.
> - `DeployHealth::alert()` success email and `EmailLogService::resend()` success: require a working mailer/SMTP; only the no-recipient/not-found branches are deterministic.
> - `MigrationService` version read-back (`getCurrentVersions`/`getLatestVersion`): needs a real tracking table; already reachable only through a live connection.
>
> ## Final passing counts (per modified file)
> - FormRouteTest **22**, FormExpressionEvaluatorTest **37**, JsonFormValidationTest **19**, EmailLogTest **13**, DeployHealthTest **7**, EnvFileTest **17**, InstallerRemovedPathsTest **10**, InstallerSyncJunkTest **5**, MigrationScriptPathTest **7**, MigrationVersionWarningsTest **6**, FormDataProviderChangelogTest **21**, FormDataProviderDeleteChangelogTest **17**, SaveJsonFormRecordTest **10**, TableFromRecordsetTest **7**.
> - Full suite: **1239 passed, 0 failed**.</result>
> <usage><subagent_tokens>154242</subagent_tokens><tool_uses>62</tool_uses><duration_ms>651369</duration_ms></usage>
> </task-notification>

> nico to focus on security but the past 150 bugs have been about primary functions failing

> yes please build these tests

> <task-notification>
> <task-id>a777a00b2eb73fe36</task-id>
> <tool-use-id>toolu_016ZTxDMJsyFGN1yo7ynuFjM</tool-use-id>
> <output-file>/tmp/claude-1000/-mnt-c-repos-cma-platform/9acb05bb-e63e-4d18-894e-ce08c9e1a363/tasks/a777a00b2eb73fe36.output</output-file>
> <status>completed</status>
> <summary>Agent "Test save-path SQL correctness" finished</summary>
> <note>A task-notification fires each time this agent stops with no live background children of its own. The user can send it another message and resume it, so the same task-id may notify more than once.</note>
> <result>Both suites green.
>
> ## Summary
>
> Created `/mnt/c/repos/cma_platform/cma/tests/FormSavePipelineTest.php` — **10 tests, all passing**. It uses the existing `TestHarness` + `StubConnection` doubles to run the real `Cma\FormDataProvider::saveJsonFormRecord` without a DB and asserts on the exact SQL string and bound-params the code records. `TestHarness::reset()` + globals restore run in `tearDown`.
>
> ### What each failure-mode test pins about the built SQL
>
> 1. **Decimal / Dutch-locale (×100 corruption)** — three tests:
>    - `10.5` on an UPDATE emits `[gewicht] = 10.5` (bare, unquoted); asserts it is NOT `'10.5'`, NOT `1050`, NOT `10,5`, and that `params == []` (inlined, never bound).
>    - Assorted on an INSERT: `1234.56`, `-3.14`, and integer `42` (declared numeric) all inlined bare; asserts none are quoted or ×100-scaled and `params == []`.
>    - Comma form `2,41` normalises to bare `2.41` even without a numeric hint (the `strpos('.')` branch).
> 2. **NULL vs empty** — empty string and explicit `null` both produce `[col] = NULL` on the UPDATE; asserts NOT `= ''`, NOT `= '0'`, NOT `= 0`.
> 3. **Field filtering** — `naam__label`, `actie`, `required`, `user_groups[]` are absent from the INSERT columns while declared `[naam]`/`Alice` are present.
> 4. **Date field** — posted `15-06-2026` becomes `[DATESTAMP] = '2026-06-15'` (ISO, SQLite path); raw `15-06-2026` never appears.
> 5. **INSERT vs UPDATE** — no id → `INSERT INTO [tblTest]` with `isNew=true` and no UPDATE/pre-read; id `1` → `UPDATE [tblTest] SET … WHERE [ID] = 1` with `isNew=false`.
> 6. **Changelog pre-read** — extends the model suite: asserts the pre-update `SELECT *` scopes to `WHERE [ID] = 1` (same record) AND precedes the UPDATE.
> 7. **Error path** — new coverage: INSERT-path verification `COUNT = 0` surfaces `success=false` with the "verificatie" message (model suite already covered the UPDATE-path cnt=0 and read-back PDOException, which I did not duplicate).
>
> ### Primary-function bug findings
>
> **None** — the decimal path behaves correctly. Note: the production code carries a **stale comment** at `FormDataProvider.php:874-881` claiming numeric fields "must be BOUND, not inlined," but the actual code (lines 939-947 / 969-974) *inlines* them as bare `$norm` literals via `SQL::normalizeDecimal`, and `$params` is never populated. The inlining is the correct, locale-safe behaviour (matching the tests and the memory note on Access decimal writes); only the comment contradicts it. I did not modify production code per the rules — flagging the misleading comment for a possible one-line cleanup.
>
> No production code was changed. `SaveJsonFormRecordTest` still passes (10/10).</result>
> <usage><subagent_tokens>72517</subagent_tokens><tool_uses>22</tool_uses><duration_ms>260233</duration_ms></usage>
> </task-notification>

> <task-notification>
> <task-id>a6ac53aa48584113f</task-id>
> <tool-use-id>toolu_011pQGct7FTTNZFTaZcR2rbn</tool-use-id>
> <output-file>/tmp/claude-1000/-mnt-c-repos-cma-platform/9acb05bb-e63e-4d18-894e-ce08c9e1a363/tasks/a6ac53aa48584113f.output</output-file>
> <status>completed</status>
> <summary>Agent "Test form control rendering" finished</summary>
> <note>A task-notification fires each time this agent stops with no live background children of its own. The user can send it another message and resume it, so the same task-id may notify more than once.</note>
> <result>Done. All green — 76 new tests in the file, and the full suite (1325 tests) still passes.
>
> ## File
> `/mnt/c/repos/cma_platform/cma/tests/FormRendererControlsTest.php` — 76 tests, all passing. Loads `FormControlHelper.php` + `FormRenderer.php` via `require_once`; no harness/DB needed (the renderer is pure).
>
> ## Per-control-type coverage
> Every control type is driven both through its per-type entry point AND through the `renderControl($type, …)` dispatcher with the correct integer constant:
>
> - **textbox** (3): input emitted, name/data-field, maxlength on positive limit + omitted for 0, required/readonly (native + data-attr), defaultValue→data-default, long→textarea, number-validation width, name escaping, XSS escaping, empty config, isDate→datepicker
> - **password** (22): `type="password"`, autocomplete, opt-in visibility toggle
> - **date** (24): lib-datepicker, required, datetime-group (datepicker+timepicker), readonly datetime→plain input, name escaping
> - **time** (21): lib-timepicker
> - **combo** (2): lib-combo shell + id, empty option when optional / omitted when required, static options all appear (incl. `label` fallback), option-text escaping, empty options, dynamic ajax-url/min-search, name escaping
> - **checkbox** (5): lib-switch, Ja:Nee / Aan:Uit labels, readonly→disabled, defaultValue
> - **radiogroup** (100): options attr, defaultValue→value=+data-default, value-not-in-options graceful, readonly, pipe/colon percent-encoding
> - **memo** (6): textarea, allow-html, readonly-html→div, json class, defaultValue
> - **checklist** (8): multi lib-combo + hidden state fields, controlId→name fallback, readonly
> - **image** (9): value input + metadata, select button, clear only when optional
> - **file** (11): hidden value/path, select only when editable, clear only when optional+editable
>
> Edge cases: unknown type → safe disabled-input fallback (no fatal), HTMLSTRIP/THUMBNAIL → empty string, every mapped type survives empty config, explicit null value, caption→data-label.
>
> ## DB-backed option loops (not reachable, as expected)
> - **Static** combo/radio options come from `config['options']` and are fully covered.
> - **Checklist** options are client-loaded (renderer only emits the shell + `data-control-id`) — not reachable, covered structurally only.
> - **Dynamic combo** option loop is client-side AJAX; the renderer just emits `ajax-url`. The combo static path's "add related" button (`CmaRepository::getFormIdBySourceTable`) only fires when `sourceTable` is set — I deliberately omit `sourceTable` to keep the tests DB-free, so that DB branch is not exercised.
>
> ## Key finding (not a bug — a design contract worth flagging)
> Unlike the lower-level `App\Library\FormControls`, **`Cma\FormRenderer` never reads `config['value']`** — it renders value-free shells and the client populates current values via AJAX (per the class docblock). So the task's "current value populates (input value=, selected option, checked state)" does not apply server-side here. I tested the server-side analogs instead — `config['defaultValue']` (→ `data-default`, and the radio `value=` attribute) and static `config['options']` — and added `testValueKeyIsNotReflectedByDesign` to lock the contract (a `config['value']` never appears in output).
>
> No primary-function bugs found: every control emits the correct element for valid input, all `$name`/option/`defaultValue` output is escaped through `self::escape()` (including the date/datetime paths — the unescaped-`$name` issue flagged in the sibling `FormControls` effort does **not** reproduce in `FormRenderer`), and an unknown control type returns a safe disabled placeholder rather than fataling.</result>
> <usage><subagent_tokens>88413</subagent_tokens><tool_uses>12</tool_uses><duration_ms>230458</duration_ms></usage>
> </task-notification>

> <task-notification>
> <task-id>a319f87a3e906c182</task-id>
> <tool-use-id>toolu_01XPPNkJEqK3ow5Li2X9tgCv</tool-use-id>
> <output-file>/tmp/claude-1000/-mnt-c-repos-cma-platform/9acb05bb-e63e-4d18-894e-ce08c9e1a363/tasks/a319f87a3e906c182.output</output-file>
> <status>completed</status>
> <summary>Agent "Test list/table rendering" finished</summary>
> <note>A task-notification fires each time this agent stops with no live background children of its own. The user can send it another message and resume it, so the same task-id may notify more than once.</note>
> <result>Both suites are green. The stack-trace lines in the Save output are that suite's own expected-exception logging, not a failure (10/10 pass).
>
> ## Report
>
> **File:** `/mnt/c/repos/cma_platform/cma/tests/ListTableRenderTest.php` — 8 test methods, all passing. No production code was modified.
>
> **How the full path runs without a DB:** `TestHarness` logs in as admin, injects each form definition into `JsonFormLoader`'s cache, and injects a `StubConnection` as the `data` connection. `getTableHtml` runs its real code; `Database::openRS` does `prepare()+execute()`, each popping one result off the stub queue. On an initial (non-loadMore) load it runs the COUNT(*) query first (~line 372) then the data-rows query (~line 386), so fixtures are enqueued count-first.
>
> **What each failure-mode test proves:**
> 1. **Happy path** — N-column def + 3 rows renders `&lt;lib-table&gt;&lt;table class="listtable"&gt;`, one `&lt;th data-field&gt;` per visible column, one `&lt;tr class="listrow"&gt;` per record, `count == 3`.
> 2. **Empty result set** — a COUNT of 0 with zero data rows returns `success:true`, `count === 0`, a structurally valid empty table, and the "Geen gegevens gevonden" empty-state. No crash, no warning.
> 3. **Record-count correctness** — with COUNT(*)=100 but only 3 data rows, the grand total surfaces in `totalCount` (100) independent of rendered rows; `count` is the rendered-page count (3).
> 4. **Memo exclusion** — a `memo` field is dropped from the column list (no `data-field="notitie"`), while the sibling text column renders.
> 5. **Escaping + null/empty** — a value `a&lt;b&gt; &amp; "c"` is entity-encoded (`a&amp;lt;b&amp;gt;`, `&amp;amp;`) and the raw markup does not leak; NULL/'' render as empty `&lt;td&gt;`. The null/empty test runs under a strict error handler that promotes warnings to exceptions, so an "Undefined array key" warning would fail it — it doesn't.
> 6. **User-selected columns** — `options['columns'] = ['plaats','naam']` renders exactly those two, drops the unselected `land`, and preserves the requested order.
> 7. **Required-filter path** — a form with `filter.field` and no filter value short-circuits to `success:true, requiresFilter:true, filterFieldName, count:0`, emits the `filter-required-table` message, and issues zero DB calls.
>
> **Empty-set and count paths: both correct.** Empty result is handled gracefully (COUNT(*) legitimately returns one row holding 0). The count-query value is exposed correctly.
>
> **Primary-function finding (not a bug, but a contract sharp edge worth flagging):** the response exposes two distinct counts — `count` = rows rendered in *this* response/page, and `totalCount` = the COUNT(*) grand total. They coincide only when all rows fit one page. Any caller/pager that reads `count` as the record total will undercount whenever paging kicks in (the classic "records 1–N van M" undercount class). The grand total to trust is `totalCount`. The service computes both correctly; the risk lives entirely in which field a consumer reads. The tests pin this contract so a future refactor that conflates the two will fail.
>
> One incidental note (pre-existing, not touched): the NULL value in the first visible column can't be asserted "empty" because column 1 always carries the row-menu trigger span (`row-menu-trigger`) inside its `&lt;td&gt;`; the empty-cell assertion therefore targets a non-first column.</result>
> <usage><subagent_tokens>100463</subagent_tokens><tool_uses>34</tool_uses><duration_ms>386421</duration_ms></usage>
> </task-notification>

> <task-notification>
> <task-id>a01bce878dbd12845</task-id>
> <tool-use-id>toolu_018jfs4GiSF5asaMmgqQ7KUJ</tool-use-id>
> <output-file>/tmp/claude-1000/-mnt-c-repos-cma-platform/9acb05bb-e63e-4d18-894e-ce08c9e1a363/tasks/a01bce878dbd12845.output</output-file>
> <status>completed</status>
> <summary>Agent "Test tree + row rendering" finished</summary>
> <note>A task-notification fires each time this agent stops with no live background children of its own. The user can send it another message and resume it, so the same task-id may notify more than once.</note>
> <result>Full suite green: 1349 passed (my 16 new tests included), no regressions or state bleed.
>
> ## Report
>
> Two new test files, both passing:
>
> - `/mnt/c/repos/cma_platform/cma/tests/TreeRenderTest.php` — 8 tests for `Cma\Services\TreeService::getJsonFormTreeHtml`
> - `/mnt/c/repos/cma_platform/cma/tests/RowRenderTest.php` — 8 tests for `Cma\Services\JsonFormService::getRowHtml`
>
> Both follow the established `TestHarness` + `StubConnection` pattern (login short-circuit, injected form def, injected `data` connection, enqueued SELECT rows). No production code was modified.
>
> ### TreeRenderTest — what each failure-mode test proves
> - **Grouped happy path**: rows spanning 2 group values produce `treeData` with exactly `[Fruit, Groente]` folders (first-seen order), `count`=3, `hasGrouping`=true, and `html` emptied in favour of `treeData`.
> - **Empty group value (the historical crash class)**: rows with `categorie =&gt; ''` and `categorie =&gt; null` do **not** fatal — they degrade into a single `"[leeg]"` folder holding both leaves, `count`=2, `success`=true. This is the documented safe fallback in `buildTreeFromFlat` (the `$g1 === ''` branch), and it holds. Plus a mixed present+empty test confirming a real folder and a `[leeg]` folder coexist.
> - **No grouping**: flat def yields a `simpletree` html list, `hasGrouping`=false, no `treeData`.
> - **Empty result set**: zero rows → `success`=true, `count`=0, no `treeData` (grouped) / friendly "Geen gegevens gevonden" (flat).
> - **Multi-level (Group1+Group2)**: nests level-2 folders inside level-1, with the leaf item at the deepest level.
>
> ### RowRenderTest — what each test proves
> - **Population**: a record's values land in the `&lt;tr&gt;` (`Appel` in the `naam` cell; `1.50` number-formatted to `1.5`); `displayText` = first column value.
> - **NULL/empty safety**: `naam =&gt; null`, `prijs =&gt; ''` render without warning/fatal; cells still emit (table structure intact), `displayText` degrades to `''` not `"null"`.
> - **displayMode**: confirmed via code trace that `$displayMode` only branches for JSON-config forms; for table-backed forms modes 1 and 2 produce identical row HTML — asserted so a future coupling regression is caught.
> - **Type rendering**: boolean → checked `&lt;lib-switch&gt;` for admin; memo value populates its cell; HTML-special chars are escaped (no raw `&lt;b&gt;` survives into the DOM).
>
> ### Primary-function bugs found
> None. Both UI-producing functions behave correctly for every probed failure mode — most importantly, the empty/null grouping value degrades gracefully into a `[leeg]` folder rather than crashing, and rows populate (and null-render) safely.
>
> Note on the task brief: for these JSON-form entry points the grouping fields come from the def's `groupFields` array (not `Group1Field`/`Group2Field`/`Group3Field` — those are the FormDefinition getters used by the separate `getTreeHtml(int $formId, …)` path). Tests model the actual `groupFields` shape used by `getJsonFormTreeHtml`.</result>
> <usage><subagent_tokens>103647</subagent_tokens><tool_uses>29</tool_uses><duration_ms>375540</duration_ms></usage>
> </task-notification>

> the security fixes you deprioritised (FormControls date-path XSS, postNumber, mapPath fail-open), and the remaining unit-coverage gaps (Request especially). 
>
> all please

## 2026-07-07

> yes please, but all five

> dark mode lib-switch: background-color: #5a5959; border-color: #0f0f0f

> today i am working on the mijn rino, if you changed something to the platform can you run composer update in /mnt/c/lab/ai_conversion/site ?

> huh? so the mijn rino has changed platform files? Take a detailed look at the changes if they are usefull to implement into the base source

> yes please

> Ok, is cma platform up to date?

> the include issue is still there, should i do something?

> is cma-menurep.php still used?

>
> http://172.30.208.1:8090/cma/main.php?page=tools.php%3Ftool%3Dendpoint_tester
> Remove #gaugePending 
> Looking at index.php i still see a lot of globals 
> @media (max-width: 600px) {
>     remove width: 100% !important;
> .col-selector-list {     border: 0px; }
>
> form#login input:not([type="checkbox"]):not([type="radio"]):not([type="button"]):not([type="submit"]):not([type="hidden"]) {
> height: weghalen en    line-height: 26px !important;
> if cma-sidebar open then #menuToggle { top:10px and z-index: 9999} 

> yes remove menurep.php and do a re-evaluation of all files wether they are in use or not

> <task-notification>
> <task-id>ad1fab762e9231ff4</task-id>
> <tool-use-id>toolu_01WGAGeYn9uc3Bg6nN4zgukt</tool-use-id>
> <output-file>/tmp/claude-1000/-mnt-c-repos-cma-platform/9acb05bb-e63e-4d18-894e-ce08c9e1a363/tasks/ad1fab762e9231ff4.output</output-file>
> <status>completed</status>
> <summary>Agent "Audit cma webcomponents/classes/js" finished</summary>
> <note>A task-notification fires each time this agent stops with no live background children of its own. The user can send it another message and resume it, so the same task-id may notify more than once.</note>
> <result>I have enough to classify everything. Let me compile the final audit.
>
> ## Audit results
>
> ### Web components — `cma/webcomponents/*.js`
>
> | File | Verdict | Evidence |
> |---|---|---|
> | cma-base-component.js | USED | bundled in bootstrap.inc:680; base class extended by other cma-* components |
> | cma-blockeditor.js | USED | bundled bootstrap.inc:286,699 |
> | cma-fold.js | USED | bundled bootstrap.inc:290,700 |
> | cma-tree.js | USED | bundled bootstrap.inc:701 (also loaded per-page) |
> | cma-sortlist.js | USED | bundled bootstrap.inc:287,702 |
> | cma-groupbox.js | USED | bundled bootstrap.inc:288,703 |
> | cma-toolbar.js | USED | bundled bootstrap.inc:292,704; also image-editor.php:114 |
> | cma-tabs.js | USED | bundled bootstrap.inc:293,705 |
> | cma-launcher.js | USED | bundled bootstrap.inc:294,706 |
> | cma-htmledit.js | USED | bundled bootstrap.inc:301,715 |
> | shared-icons.js | USED | bundled bootstrap.inc:698 (provides icons for other components) |
> | cma-schema-canvas.js | USED | loaded via cma_script in report-designer.php:76 |
> | cma-field-config.js | USED | report-designer.php:77 |
> | cma-conditions-panel.js | USED | report-designer.php:78 |
> | cma-param-config.js | USED | report-designer.php:79 |
> | cma-sort-config.js | USED | report-designer.php:80 |
> | cma-group-config.js | USED | report-designer.php:81 |
> | cma-query-preview.js | USED | report-designer.php:83 |
> | cma-sql-editor.js | USED | report-designer.php:84 |
> | cma-combo.js | LIKELY DEAD | 11-line deprecated stub ("should not be loaded"); merged into lib-combo.js; loaded nowhere (bootstrap.inc:289 comment + storybook doc only) |
> | UNUSED_cma-checklist.js | LIKELY DEAD | filename + `&lt;cma-checklist&gt;` tag appear nowhere outside its own def; already flagged by `UNUSED_` prefix |
> | UNUSED_cma-rights-matrix.js | LIKELY DEAD | filename + `&lt;cma-rights-matrix&gt;` tag appear nowhere outside its own def; `UNUSED_` prefix |
>
> ### Classes — `cma/classes/**/*.php`
>
> All 46 are USED except two. Each USED class has ≥2 cross-file references (`require_once` + `::`/`new`). Notable low-but-live: HtmlHelper (FormTemplate.php:21,210,357), OptionsService(3), ProcessRunner(2), SubformService(3), DatabasesService(2), FormRoute(3), ReportExporter/ReportStorage, SqlHelper(3), form_constants(5). Suspects:
>
> | File | Verdict | Evidence |
> |---|---|---|
> | classes/FormColumn.php | LIKELY DEAD | `class FormColumn` referenced only inside its own docblock; the only repo hits for "FormColumn" are an unrelated `$hasFormColumn` var in migration 5.7.0. No `require_once`, `new`, or `FormColumn::` anywhere |
> | classes/Services/FormMetadataService.php | LIKELY DEAD | `require_once`'d in bootstrap.inc:156 but no `FormMetadataService::`, `new`, or `extends` anywhere; all public methods are static and none are called qualified. Loaded-but-never-invoked. (Note: removal must also drop the bootstrap.inc:156 require line) |
>
> ### assets/js — `cma/assets/js/*.js`
>
> | File | Verdict | Evidence |
> |---|---|---|
> | cma-utils.js | USED | bundled bootstrap.inc:270,673 |
> | url-manager.js | USED | bundled bootstrap.inc:675 |
> | request-tracker.js | USED | bundled bootstrap.inc:272,678 |
> | cma.js | USED | bundled bootstrap.inc:299,713 |
> | cma-users.js | USED | bundled bootstrap.inc:300,714 |
> | blockedit.js | USED | bundled bootstrap.inc:302,716 |
> | table-preferences.js | USED | bundled bootstrap.inc:303,718 |
> | inline-edit.js | USED | bundled bootstrap.inc:304,719 |
> | perf-logger.js | USED | bundled bootstrap.inc:305,720 |
> | cma-tours.js | USED | bundled bootstrap.inc:307,722 |
> | cma-list-thumb.js | USED | bundled bootstrap.inc:723 |
> | main.js | USED | bundled bootstrap.inc:308,724 |
> | form-controller.js | USED | injected into form JS bundle bootstrap.inc:737,739 |
> | image-editor.js | USED | loaded via minify_asset in image-editor.php:114 |
> | error-handler.js | LIKELY DEAD | stale duplicate — every load path points to `library/assets/js/error-handler.js` (bootstrap.inc:271,322,677; main.php:408; image-editor.php:114), which differs in content. The `cma/assets/js/` copy is loaded nowhere; only prose-referenced in documentation.php:2503 |
> | UNUSED_form-helpers.js | LIKELY DEAD | filename referenced nowhere; `UNUSED_` prefix |
> | UNUSED_image-wizard.js | LIKELY DEAD | filename referenced nowhere; `UNUSED_` prefix |
>
> ## Recommend removing (LIKELY DEAD)
>
> Web components:
> - `/mnt/c/repos/cma_platform/cma/webcomponents/cma-combo.js` (deprecated stub; also remove the storybook.php:2865 mention)
> - `/mnt/c/repos/cma_platform/cma/webcomponents/UNUSED_cma-checklist.js`
> - `/mnt/c/repos/cma_platform/cma/webcomponents/UNUSED_cma-rights-matrix.js`
>
> Classes:
> - `/mnt/c/repos/cma_platform/cma/classes/FormColumn.php`
> - `/mnt/c/repos/cma_platform/cma/classes/Services/FormMetadataService.php` (also drop `require_once` at bootstrap.inc:156)
>
> assets/js:
> - `/mnt/c/repos/cma_platform/cma/assets/js/error-handler.js` (stale duplicate of the live `library/assets/js/error-handler.js`; update documentation.php:2503 to point at the library path)
> - `/mnt/c/repos/cma_platform/cma/assets/js/UNUSED_form-helpers.js`
> - `/mnt/c/repos/cma_platform/cma/assets/js/UNUSED_image-wizard.js`
>
> Caveats: removals of any of these must also add the path to `Installer.php::REMOVED_PATHS` (per project rule) so consumer sites drop the dead files. FormMetadataService and the cma-copy error-handler are the only "surprising" ones — both are genuinely loaded (require/doc-referenced) but never actually invoked/served; I rated them LIKELY DEAD rather than UNCERTAIN because no qualified call site exists. Everything not listed here is USED. I found no truly UNCERTAIN files in this slice.</result>
> <usage><subagent_tokens>39796</subagent_tokens><tool_uses>13</tool_uses><duration_ms>607741</duration_ms></usage>
> </task-notification>

> <task-notification>
> <task-id>acee909e8992a8777</task-id>
> <tool-use-id>toolu_01X4cLBPKG9BbtfCnntv7rz4</tool-use-id>
> <output-file>/tmp/claude-1000/-mnt-c-repos-cma-platform/9acb05bb-e63e-4d18-894e-ce08c9e1a363/tasks/acee909e8992a8777.output</output-file>
> <status>completed</status>
> <summary>Agent "Audit cma root pages + includes" finished</summary>
> <note>A task-notification fires each time this agent stops with no live background children of its own. The user can send it another message and resume it, so the same task-id may notify more than once.</note>
> <result>Analysis complete. Here are my findings for all 41 top-level `cma/*.php` and `cma/*.inc` files.
>
> ## Dead-file audit — `cma/*.php` and `cma/*.inc`
>
> | File | Verdict | Evidence |
> |------|---------|----------|
> | `html_edit_cell.php` | LIKELY DEAD | Zero launchers; uses IE-only `window.dialogArguments` (`showModalDialog`) API. Siblings table/image/link are opened via `cma.js` `lib_OpenWindowCentered`, but cell/row have NO cma.js caller. Only "ref" is `tools_endpoint_tester.php` which auto-`glob()`s the dir (not real use). |
> | `html_edit_row.php` | LIKELY DEAD | Same as cell: `window.dialogArguments["table_row"]` IE modal API; no JS launcher anywhere. |
> | `wizard.php` | LIKELY DEAD | Legacy iframe wizard shell (`WizardInit`, `IMAGES/pixel.gif`, `blank.php` content frame). Current wizards open standalone via `wizards/file-browser.php?...` (form-controller.js). Zero launchers; only ref is a stale comment in `wizards/link-pages.php:149`. (Note: `wizards/wizard.js` it loads is still live via html_edit_* / template_edit — keep the JS.) |
> | `reports_DEPRECATED.php` | LIKELY DEAD | Self-documented archive of the old two-pane reports browser; superseded by `reports.php`; header says "reachable only by direct URL; nothing links to it. Remove after v1.29" (current v1.28.92). |
> | `tools_DEPRECATED.php` | LIKELY DEAD | Archived two-pane cma-tree tools view, superseded by `tools.php`; only mentioned as "archived" in `tools.php:9` + docs. Direct-URL-only intentional archive. |
> | `listTemplates.php` | UNCERTAIN | Routable full page (recently modernized to `cma-tree`) but NO menu.json / tools-catalog / tile / link points to it. Entry to the template-editing island. |
> | `template_edit.php` | UNCERTAIN | Reachable only from `listTemplates.php:158` (and `template_post.php` redirect) — part of the same island; no external entry. |
> | `template_post.php` | UNCERTAIN | POST handler for `template_edit.php`'s form (`action=template_post.php`); island member, no external entry. |
> | `template_fillrep.php` | UNCERTAIN | Linked only from `listTemplates.php:50`; redirects to `blank.php`. Island member. |
> | `subform.php` | UNCERTAIN | Full page "Modernized with lib-table" but zero launchers found; subforms are now served via `api/form_subform.php` (form-controller.js) — possibly superseded standalone page. |
> | `copyright.php` | UNCERTAIN | Full page emitting `versie &lt;STRCMAVERSION&gt;`; in bootstrap public-pages whitelist but nothing links it. Likely a frameset-era footer remnant; can't prove it's unreachable (may be loaded site-side). |
> | `404.php` | USED | web.config `httpErrors` → `path="/cma/404.php"`. |
> | `blank.php` | USED | Public-pages whitelist; iframe placeholder for `wizard.php` + `template_fillrep.php` redirect target. |
> | `bootstrap.inc` | USED | Required by virtually every root page (`require_once __DIR__.'/bootstrap.inc'`); builds bundles. |
> | `dashboard.php` | USED | web.config rewrite `main.php?page=dashboard.php`; requires `menurep.inc`. |
> | `default.php` | USED | IIS default document (web.config `&lt;add value="default.php"&gt;`); redirects logged-in users to `main.php`. |
> | `details_getdata.php` | USED | Called as dynamic AJAX URL by `classes/FormControlHelper.php` (3 sites). |
> | `form.php` | USED | Primary form page; many web.config rewrites `main.php?page=form.php`. |
> | `form_api.php` | USED | Central AJAX endpoint (325+ refs across JS). |
> | `html_edit_image.php` | USED | Opened via `cma.js:905` `lib_OpenWindowCentered('html_edit_image.php',...)`. |
> | `html_edit_link.php` | USED | Opened via `cma.js:801/913` (insert/edit modes). |
> | `html_edit_table.php` | USED | Opened via `cma.js:889/897` (insert/edit modes). |
> | `image-editor.php` | USED | Opened by `wizards/file-browser.php:2325` (`../image-editor.php?...`); documented. |
> | `imageupload_crop.php` | USED | Opened via `cma.js:808/1628` `CMA.image.openDialog(...)` and `wizards/file_upload.php`. |
> | `imageupload_crop_upload_handler.php` | USED | `endpoint` for `&lt;lib-fileuploader&gt;` in `imageupload_crop.php:225`. |
> | `index.php` | USED | IIS default doc; redirects to `default.php`. |
> | `login.php` | USED | Auth entry; public-pages whitelist; links `password.php`, `sso_login.php`. |
> | `logout.php` | USED | Auth flow; public-pages whitelist. |
> | `main.php` | USED | The sidebar shell; target of all web.config rewrites (`main.php?page=...`). |
> | `menurep.inc` | USED | Required by `main.php:160` and `dashboard.php:17` (`loadMenuData()`); Installer notes the dependency. |
> | `minify.php` | USED | Asset pipeline (`minify.php?f=...`) referenced by all pages; public-pages whitelist. |
> | `password.php` | USED | Linked from `login.php:358` (`&lt;a href="password.php"&gt;` change-password). |
> | `preferences.php` | USED | web.config rewrite `main.php?page=preferences.php`. |
> | `report-designer.php` | USED | `menu.json:484` `"href": "report-designer.php"`. |
> | `reportdetails.php` | USED | `reports-catalog.php:44` `'href' =&gt; 'reportdetails.php?RepID=...'`. |
> | `reports.php` | USED | `menu.json:475` `"href": "reports.php"`. |
> | `sso_callback.php` | USED | Public-pages whitelist; `SsoService.php:153` uses it as IDP callback path. |
> | `sso_login.php` | USED | Linked from `login.php:267`; public-pages whitelist. |
> | `task.php` | USED | Externally-triggered daily batch endpoint (`/cma/task.php`); head comment confirms it's the scheduled job (flagged for CLI-move but live). |
> | `tools.php` | USED | web.config rewrite `main.php?page=tools.php`; 182 refs. |
> | `tools_catalog.inc` | USED | Required by `api/tools-catalog.php:14` and `tools_DEPRECATED.php:28` (`buildToolsTreeData()`, shared launcher source). |
> | `wizard.php` | (see above) | — |
>
> ### Recommend removing (LIKELY DEAD)
> 1. `cma/html_edit_cell.php` — orphaned IE `showModalDialog` table-cell dialog, no launcher.
> 2. `cma/html_edit_row.php` — orphaned IE `showModalDialog` table-row dialog, no launcher.
> 3. `cma/wizard.php` — legacy iframe wizard shell; wizards now open standalone (keep `wizards/wizard.js`, still used).
> 4. `cma/reports_DEPRECATED.php` — intentional archive, superseded by `reports.php` ("Remove after v1.29").
> 5. `cma/tools_DEPRECATED.php` — intentional archive, superseded by `tools.php`.
>
> Notes for the caller:
> - Items 4–5 are deliberate transitional archives inside their stated keep-window (current release v1.28.92, slated for removal ~v1.29) — dead, but removal is a scheduled cleanup, not an accident.
> - Any removal must add the path to `src/Installer.php::REMOVED_PATHS` (per the repo's installer-propagation rule) or consumer sites keep the dead file forever. `reports_DEPRECATED.php`/`tools_DEPRECATED.php` also `require` `tools_catalog.inc` — that .inc is still shared with `api/tools-catalog.php`, so keep it.
> - The **template cluster** (`listTemplates` → `template_edit` → `template_post`, plus `template_fillrep`) is a self-contained island with no discoverable entry link, but was recently modernized to `cma-tree` and the pages are directly routable — worth confirming with the user whether "wijzigbare pagina's" is still a live feature before removing. Same caution for `subform.php` (superseded-looking but recently touched) and `copyright.php` (frameset-era footer remnant, still whitelisted).</result>
> <usage><subagent_tokens>52217</subagent_tokens><tool_uses>12</tool_uses><duration_ms>764665</duration_ms></usage>
> </task-notification>

> <task-notification>
> <task-id>a7fa63c43dadb45bd</task-id>
> <tool-use-id>toolu_01KE39rgfWMxja7qktxJzxtX</tool-use-id>
> <output-file>/tmp/claude-1000/-mnt-c-repos-cma-platform/9acb05bb-e63e-4d18-894e-ce08c9e1a363/tasks/a7fa63c43dadb45bd.output</output-file>
> <status>completed</status>
> <summary>Agent "Audit cma tools/api/wizards" finished</summary>
> <note>A task-notification fires each time this agent stops with no live background children of its own. The user can send it another message and resume it, so the same task-id may notify more than once.</note>
> <result>I have enough evidence. Key mechanism confirmed: tools are registered in `cma/tools_catalog.inc` (`buildToolsTreeData()`) + `cma/tools.php`; `cma/tools_DEPRECATED.php` is archived and never loaded (only mentioned in comments/docs), so references from it are NOT evidence of use. The modern `wizards/file-browser.php` (used by blockedit.js, form-controller.js, image-editor.js) self-serves its API via `fetch('?action=...')` and has superseded the entire legacy frameset file-picker family.
>
> ## Dead-file audit: `cma/tools`, `cma/api`, `cma/wizards`
>
> | File | Verdict | Evidence |
> |------|---------|----------|
> | `cma/tools/phpinfo_check.php` | LIKELY DEAD | 37-line debug dump ("Quick PHP configuration check for debugging"), no auth, zero references anywhere; not in catalog/tools.php/menu/docs. |
> | `cma/tools/reload_env.php` | LIKELY DEAD | Standalone .env-reload utility, zero references, not registered in catalog/tools.php, not documented. |
> | `cma/tools/temp_get_subform_order.php` | LIKELY DEAD | Throwaway debug script (`temp_` prefix), requires `_bootstrap.php` directly and reads removed `conn_data`/`conn_rep` globals (gone since DB-single-source v1.25.0); zero references. |
> | `cma/tools/tools_process_test.php` | LIKELY DEAD | Unregistered "Process Runner Test Page"; zero references; sole consumer of `ProcessRunner` service (nothing else uses it). |
> | `cma/tools/tools_test_config.php` | LIKELY DEAD | Unregistered developer "ConfigLoader Test Script"; zero references; not in any catalog. |
> | `cma/tools/tools_welcome.php` | LIKELY DEAD | 5-line empty-state page referenced only by the archived, never-loaded `tools_DEPRECATED.php`. |
> | `cma/api/contentblocks_api.php` | LIKELY DEAD | Standalone content-blocks CRUD API superseded by the `contentblocks` JSON form (handled by `ConfigFormService`/`form_api`); zero live consumers (only the endpoint-tester's dynamic `glob`). |
> | `cma/wizards/file-pages.php` | LIKELY DEAD | Legacy file-picker wizard superseded by `file-browser.php` (which labels it "old file-pages.php wizard"); referenced only in comments; no launcher. |
> | `cma/wizards/link-pages.php` | LIKELY DEAD | Legacy frameset picker; zero external references; only embeds the dead `file_frameset.php`. |
> | `cma/wizards/table-pages.php` | LIKELY DEAD | Legacy frameset picker; zero references in either direction. |
> | `cma/wizards/file_frameset.php` | LIKELY DEAD | Referenced only by dead `file-pages.php`/`link-pages.php`; classic frameset remnant. |
> | `cma/wizards/file_controls.php` | LIKELY DEAD | Referenced only within the dead frameset family (`file_frameset.php`, `file_controls_delete.php`). |
> | `cma/wizards/file_controls_delete.php` | LIKELY DEAD | Mutual reference with dead `file_controls.php` only. |
> | `cma/wizards/file_list.php` | LIKELY DEAD | Referenced only by dead-family members (`file_frameset`, `file_upload`, `file_list_ajaxdata`). |
> | `cma/wizards/file_list_ajaxdata.php` | LIKELY DEAD | Referenced only by dead `file_list.php`. |
> | `cma/wizards/file_outputfile.php` | LIKELY DEAD | Referenced by dead `file_upload.php`; the `LibUpload.php` hit is a comment, not a call. |
> | `cma/wizards/file_upload.php` | LIKELY DEAD | Referenced only within the dead frameset family (`file_frameset`, `file_outputfile`). |
> | `cma/wizards/file_browser_api.php` | LIKELY DEAD | Self-described "modern file browser API" but orphaned — `file-browser.php` self-serves via `fetch('?action=list/details')`; zero references anywhere. |
> | `cma/tools/set_migration_version.php` | UNCERTAIN | Dev-only utility (`isDeveloper` gate), documented as direct-URL in its own header; zero external references but a plausibly-intentional dev entry point. |
> | `cma/tools/tools_export_repository_cli.php` | UNCERTAIN | CLI companion of the registered `tools_export_repository.php`, meant to be run manually (`php …_cli.php`); no code refs expected, but paths look stale. |
> | `cma/tools/tools_validate_config.php` | UNCERTAIN | Unregistered config-validation maintenance script (browser/CLI, admin-gated); zero references, but plausibly run manually by URL. |
> | `cma/api/config_api.php` | UNCERTAIN | Config-read API (`?type=databases`); no live consumer found in JS/forms/menu/classes — only the endpoint-tester glob + a recorded test run; likely superseded by `ConfigFormService` but reachable by direct URL. |
> | `cma/api/config_post.php` | UNCERTAIN | Config-save POST handler; no form or JS posts to it (config-backed forms now go through `ConfigFormService`); referenced only in a comment + endpoint-tester list. |
> | `cma/api/file_edit.php` | UNCERTAIN | Local-env dev-only API ("open file in system editor"); zero consumers found, but a dev-mode link/fetch may exist outside this repo's grep surface. |
> | `cma/tools/db_health.php` | USED | Registered in `tools_catalog.inc` + `tools.php`. |
> | `cma/tools/diag.php` | USED | Documented direct-URL diagnostic endpoint (`?key=DEPLOY_SECRET`) in `documentation.php`; intentionally bootstrap-independent. |
> | `cma/tools/documentation.php` | USED | Registered tool; the docs hub. |
> | `cma/tools/llm_analyse.php` | USED | Linked from `cma/config/menu.json` (`href: tools/llm_analyse.php`). |
> | `cma/tools/logreader.php` | USED | Registered in catalog + tools.php; cypress specs. |
> | `cma/tools/opcache_reset.php` | USED | Linked as a live tool in `documentation.php` ("Tools → OPcache reset", `href="tools/opcache_reset.php"`). |
> | `cma/tools/storybook.php` | USED | Registered in catalog + tools.php; cypress specs. |
> | `cma/tools/tools_consistency_picture_delete.php` | USED | Invoked by `tools_db_consistency.php`. |
> | `cma/tools/tools_generate_forms.php` | USED | Registered in `tools.php`; invokes `tools_validate_parentfields.php`. |
> | `cma/tools/tools_validate_parentfields.php` | USED | Invoked by `tools_generate_forms.php`. |
> | `cma/tools/tools_backup / tools_clearcache / tools_create_indexes / tools_db_consistency / tools_db_sync / tools_dbcompact / tools_dbsummary / tools_deploy_setup / tools_dev_copymod / tools_endpoint_tester / tools_export_repository / tools_formedit / tools_formwiz / tools_llm / tools_maintenance / tools_migrate_prepare / tools_migrations / tools_phpunit / tools_query / tools_serverinfo / tools_sqlite_repair / tools_testrunner / tools_webp_convert` | USED | All registered in `tools_catalog.inc` + `tools.php`. |
> | `cma/api/change-password.php` | USED | Fetched by `main.js`; used by `password.php`; cypress. |
> | `cma/api/dashboard_stats.php` | USED | Used by `dashboard.php`; cypress. |
> | `cma/api/email-actions.php` | USED | Fetched by `cma.js`; tests + cypress. |
> | `cma/api/form_definition.php / form_list.php / form_record.php / form_subform.php` | USED | Fetched by `form-controller.js` / `subform.php`; endpoint-tester + cypress. |
> | `cma/api/forms_index.php` | USED | `ajaxUrl` in `assets/forms/definitions/_menu_items.json`. |
> | `cma/api/icon_add.php` | USED | Used by `storybook.php`; cypress. |
> | `cma/api/log.php` | USED | Fetched by `main.js`, `perf-logger.js`, `lib-log.js`. |
> | `cma/api/report-definition / report-export / report-query / report-save / report-schema.php` | USED | Used by `report-designer.php`/`reportdetails.php` and report web components; cypress. |
> | `cma/api/reports-catalog.php` | USED | Used by `reports.php`. |
> | `cma/api/tools-catalog.php` | USED | Fetched by `cma-launcher.js`. |
> | `cma/api/test_ip_match.php` | USED | Registered in `bootstrap.inc` allowlist (env-protected); cypress spec. |
> | `cma/api/user_actions.php` | USED | Fetched by `cma-users.js`; `main.php`. |
> | `cma/api/user_activity.php / user_forms.php` | USED | Used by `dashboard.php`; cypress. |
> | `cma/api/user_tips.php` | USED | Fetched by `lib-tip.js`; cypress. |
> | `cma/wizards/file-browser.php` | USED | Opened by `blockedit.js`, `form-controller.js`, `image-editor.js/php`; cypress. |
>
> ### Recommend removing (LIKELY DEAD)
> Tools:
> - `cma/tools/phpinfo_check.php`
> - `cma/tools/reload_env.php`
> - `cma/tools/temp_get_subform_order.php`
> - `cma/tools/tools_process_test.php`
> - `cma/tools/tools_test_config.php`
> - `cma/tools/tools_welcome.php`
>
> API:
> - `cma/api/contentblocks_api.php`
>
> Wizards (entire legacy frameset file-picker family, superseded by `file-browser.php`):
> - `cma/wizards/file-pages.php`
> - `cma/wizards/link-pages.php`
> - `cma/wizards/table-pages.php`
> - `cma/wizards/file_frameset.php`
> - `cma/wizards/file_controls.php`
> - `cma/wizards/file_controls_delete.php`
> - `cma/wizards/file_list.php`
> - `cma/wizards/file_list_ajaxdata.php`
> - `cma/wizards/file_outputfile.php`
> - `cma/wizards/file_upload.php`
> - `cma/wizards/file_browser_api.php`
>
> Note (per your Installer-propagation memory): any of these that gets `git rm`'d must also be added to `src/Installer.php` `REMOVED_PATHS`, or consumer sites keep the dead file forever. I verified none of the above are currently in `REMOVED_PATHS`. The 6 UNCERTAIN files (`set_migration_version.php`, `tools_export_repository_cli.php`, `tools_validate_config.php`, `config_api.php`, `config_post.php`, `file_edit.php`) each have zero discoverable live consumers but remain plausibly reachable as manual/direct-URL dev or maintenance endpoints — worth a human confirm before removal.</result>
> <usage><subagent_tokens>65119</subagent_tokens><tool_uses>17</tool_uses><duration_ms>908690</duration_ms></usage>
> </task-notification>

> okay remove all items where you are sure of that they are unused

>
> PHP Unit Tests : alles selecteren checkbox in eerste colom header met alleen vinkje, geen label 
>
> The cma-launcher should be inside tools-content , zelfde als bij rapportages
> http://172.30.208.1:8090/cma/reportdetails.php?RepID=83 -> oude weergave, geen mooie toolbar en achtergrond is wit bij dark mode 
> dark mode: . canvas-container bij cma-schema-canvas in dark mode: zwart als achtergrond
> :host-context(html.dark-mode) .tabs-list {
> 1.        Background-color: #474747
>
> Call to undefined method App\Library\SQL::normalizeDecimal() : http://172.30.208.1:8090/cma/form/opleidingen:0 
>
> Mijn recente activiteit uses a libTable and other old styling, use  a lib-table that knows dark mode as well , remove the old css 
>
> Turning 
> Performance logging        Log API-aanroepen, queries en laadtijden
> Cache logging        Log cache hits en misses
> Debug logging        
> Off does not work, they keep being ON 
> Bij de opmerkingen staat dat het in Deze instellingen gelden voor alle gebruikers en worden opgeslagen in het .env.local bestand. 
> Maar we zijn over op het enkelvoudige .env bestand. 

## 2026-07-08

> continue

> [08:41:49] JS: [iframe] Uncaught TypeError: Cannot read properties of undefined (reading '$')
>   at http://172.30.208.1:8090/cma/ckeditor/ckeditor.js:969

> can you load ckeditor with a version parameter?

> what is your status in this repo

> okay, STILL the annoying records 1-1800 van 1809 (laden...) on desktop, ultra think on how to solve that

> the webP conversie halts after a few images with a generic error 500. Pleas make sure the information i need is shown on the screen (non P)

>  commit/tag/push please

> just commit everything please

> commit and push ann changes

> there is a trick to remember filter criteria accross forms through a cookie in the old CMA, i remember you have added that to the new cma as well, but did you?

> 3: if you select a record in the tree or the table, that should also be stored in localstorage, can you make that happen?

> yes please do

> okay and the 1-1800 of 1806?? that issue is still there, why dont you just loop :
>
> while recordleft {
> await retrieverecords(offset)
> }

> still there, why dont you just loop :
> while recordleft {
> await retrieverecords(offset)
> }

## 2026-07-09

> can you check if blockedit.js has been worked on in this repo or in the one of mijnrino

> in /mnt/c/repos/mijnrino/cma/include blockedit.js has been battletested and approved, please check your version (in /assets/js) to see if changes need to be miograted to it

> i have a 404 on /cma/opcache_reset.php , is that called internally?

> yes please do

> in errorhandler.js the tabs are not correctly set during initialisation, perhaps a race condition, the tab Call Stack and Database tests should be selected, perhaps after 300ms check for being active?

> over de tabel filtering: is het een mogelijkheid dat het laatst toegepaste filter wordt opgeslagen in localstorage en dat tijdens initialisatie deze wordt uitgelezen en weer geactiveerd?

> nee goed, start maar op

> okay we willen dus opslaan welke gegevens je NIET wilt zien, want als je alleen kiest wat e wel wilt zien zal het vaak foutgaan .

> okay, andere vraag; bij een libConfirm of libAlert, zorgen deze schermen ervoor dat ze in de scrollview staan als ze op een lange pagina worden getoond? De vorige versie centreerde ze verticaal maar bij lange pagina's leidde dat ertoe dat ze niet altijd zichtbaar waren.

> ik dacht dat we de extra buttons een domeinnaam ook portnummers hebben gegeven, is dat 'kwijt'?

> nee we gaan nog even lekker door....
>
> major issue: alle comboboxen die veel data bevatten geven nu "Geen resultaten'. Ajax lijkt ook niet te werken . Dit geeft form_api.php terug: {success: true, combos: {,…}}
> combos
> : 
> {,…}
> fkAssistent
> : 
> {success: true,…}
> options
> : 
> [{id: "23", text: "__Renée de Haan"}, {id: "2", text: "Angelique van Wees"},…]
> success
> : 
> true
> fkDeelnemer
> : 
> {success: true, options: [], requires_search: true, min_search_length: 3, table_count: 1625}
> min_search_length
> : 
> 3
> options
> : 
> []
> requires_search
> : 
> true
> success
> : 
> true
> table_count
> : 
> 1625
> fkDocent
> : 
> {success: true,…}
> options
> : 
> [{id: "4814", text: "Joost van der Aa"}, {id: "4393", text: "Ineke Abdoelaziz-Hoogeveen"},…]
> success
> : 
> true
> fkKlantContactpersoon
> : 
> {success: true, options: []}
> fkP_PraktOpl
> : 
> {success: true, options: [{id: "240", text: "Karin Hattink"}, {id: "239", text: "Sanne Kriens"}]}
> fkPraktijkOpleider
> : 
> {success: true, options: [{id: "61", text: "Laurien Aben"}, {id: "74", text: "Rachel Adriaanse"},…]}
> fkSRHForumLid
> : 
> {success: true,…}
> fkSupervisor
> : 
> {success: true, options: []}
> fkWerkbegeleider
> : 
> {success: true, options: []}
> success
> : 
> true 
> medewerker kan ik dus kiezen maar deelnemers of docenten niet

> another issue: Error Handler Failed
> The error handler encountered a problem while processing an error.
>
> Error in Error Handler: htmlspecialchars(): Passing null to parameter #1 ($string) of type string is deprecated
> In file: C:\lab\ai_conversion\site\app\library\ErrorHandler.php
> On line: 1642
> Original Error:
> Error: Call to undefined function lib_AuditLog()
> In file: C:\lab\ai_conversion\site\index.php
> On line: 95
> Error Handler Stack Trace:
> #0 [internal function]: App\Library\ErrorHandler::handleError(8192, 'htmlspecialchar...', 'C:\\lab\\ai_conve...', 1642)
> #1 C:\lab\ai_conversion\site\app\library\ErrorHandler.php(1642): htmlspecialchars(NULL)
> #2 C:\lab\ai_conversion\site\app\library\ErrorHandler.php(798): App\Library\ErrorHandler::renderHtmlError(Object(Error), false)
> #3 C:\lab\ai_conversion\site\app\library\ErrorHandler.php(252): App\Library\ErrorHandler::renderDetailedError(Object(Error))
> #4 [internal function]: App\Library\ErrorHandler::handleException(Object(Error))
> #5 {main}
> Original Error Stack Trace:
> #0 C:\lab\ai_conversion\site\_bootstrap_wrapper.php(70): include()
> #1 {main}
> Time: 2026-07-09 15:17:59 | PHP Version: 8.4.5 | Server: cgi-fcgi 
>
> duid you change anything to the platform errorhandler that can cause this?

> ja die fout wordt al opgelost in een ander venster

## 2026-07-10

>
> ﻿
> (index):1 Refused to apply style from 'http://172.30.208.1:8090/library/css/linearicons.css?v=1' because its MIME type ('') is not a supported stylesheet MIME type, and strict MIME checking is enabled.

> Graag de linearicons.css in library/css plaatsen en alle overige locaties weghalen en de interne verwijzingen updaten

> maar die linearicons.css verwijst die naar de verkleinde versie van het font?

> woil je de subset opnieuw genereren want ik mis inderdaad vaak iconen (al eerder gerapporteerd)

> Maar die 634 zijn ook de storybook referenties toch? En dat is juist om de hele set te tonen, kun je die eraf halen?

> maak maar aliassen ajb

> laten we een release doen, commit en push alle wijzigingen, of ze uit deze thread komen of niet

> Undefined variable $CACHE_XSLTS
> in C:\lab\ai_conversion\site\library\lib_xmlsnippets.inc on line 182

> please commit and push to git

> ja patch m maar even idd

> Creation of dynamic property LibTable::$Recordset is deprecated
> in C:\lab\ai_conversion\site\rapportage_voordrachten_po.inc on line 89

> Undefined variable $php_error_number
> in C:\lab\ai_conversion\site\cma\details_getdata.php on line 68

> Call to a member function MoveNext() on string
> in C:\lab\ai_conversion\site\rapportage_presentie.inc on line 68

> http://172.30.208.1:8090/index.php?pageaction=rapportage_presentie 500 (Internal Server Error)
> index.php?pageaction=rapportage_presentie:1121  GET http://172.30.208.1:8090/uploads/profielfotos/100.jpg 404 (Not Found)
> index.php?pageaction=rapport_vrijstellingen:1  GET http://172.30.208.1:8090/index.php?pageaction=rapport_vrijstellingen 500 (Internal Server Error)
> index.php?pageaction=rapportage_presentie:1  POST http://172.30.208.1:8090/index.php?pageaction=rapportage_presentie 500 (Internal Server Error)
> index.php?pageaction=rapportage_presentie:4315  GET http://172.30.208.1:8090/uploads/profielfotos/100.jpg 404 (Not Found)
> index.php?pageaction=rapport_deelnemers_praktijkopleiders:1  GET http://172.30.208.1:8090/index.php?pageaction=rapport_deelnemers_praktijkopleiders 500 (Internal Server Error)
> index.php?pageaction=rapport_deelnemers_praktijkopleiders:1223  GET http://172.30.208.1:8090/uploads/profielfotos/100.jpg 404 (Not Found)
> index.php?pageaction=rapportage_presentie_ontbrekend:1  GET http://172.30.208.1:8090/index.php?pageaction=rapportage_presentie_ontbrekend 500 (Internal Server Error)
> index.php?pageaction=rapportage_presentie_ontbrekend:1019  GET http://172.30.208.1:8090/uploads/profielfotos/100.jpg 404 (Not Found)
> index.php?pageaction…ntie_ontbrekend:421 Uncaught TypeError: Cannot read properties of undefined (reading 'writeText')
>     at HTMLButtonElement.onclick (index.php?pageaction=rapportage_presentie_ontbrekend:421:663)
> onclick @ index.php?pageaction=rapportage_presentie_ontbrekend:421
> index.php?pageaction=rapport_vrijstellingen:1  Failed to load resource: the server responded with a status of 500 (Internal Server Error)
> index.php?pageaction=rapportage_presentie_ontbrekend:1  GET http://172.30.208.1:8090/index.php?pageaction=rapportage_presentie_ontbrekend 500 (Internal Server Error)
> index.php?pageaction=rapport_deelnemers_praktijkopleiders:1  GET http://172.30.208.1:8090/index.php?pageaction=rapport_deelnemers_praktijkopleiders 500 (Internal Server Error)
> index.php?pageaction=rapport_deelnemers_praktijkopleiders:2523  GET http://172.30.208.1:8090/uploads/profielfotos/100.jpg 404 (Not Found)
> agenda.php:216 Uncaught SyntaxError: await is only valid in async functions and the top level bodies of modules
> agenda.php:469  GET http://172.30.208.1:8090/uploads/profielfotos/100.jpg 404 (Not Found)
> agenda.php:216 Uncaught SyntaxError: await is only valid in async functions and the top level bodies of modules (at agenda.php:216:17)
> agenda.php:469  GET http://172.30.208.1:8090/uploads/profielfotos/100.jpg 404 (Not Found)

> Maak in de recordste een functie EOF() die de property eof retourneerd, backwars-compatibility

> pak alsjeblieft alles op wat je vaststeld

> wil je die oppakken inderdaad? Jij weet nu goed wat er aan de hand is.

> ja graag systemisch aanpakken dus de converter aanpassen

> zijn er openstaande zaken? kun je een full endpoints scan doen?

> ja graag

> ja graag, iisreset is gedraaid..

## 2026-07-11

> kun jij de refresh aanroepen?

> ja moet toch gebeuren namelijk

> ja graag

> typing in a select2 does not trigger a search (cma version 119)

> ja zet allrs maar om naar lib-combo voor consistentie

> trek dat onderzoek breder, kijk naar alle state management of die kunnen lekken

> <task-notification>
> <task-id>a5ad217c3a459f80c</task-id>
> <tool-use-id>toolu_01DYPtKnjmZNc1La9MtNb1AD</tool-use-id>
> <output-file>/tmp/claude-1000/-mnt-c-repos-cma-platform/4c10bf01-6ac5-42cd-b645-c012d5313179/tasks/a5ad217c3a459f80c.output</output-file>
> <status>completed</status>
> <summary>Agent "Map multi-form + combo architecture" finished</summary>
> <note>A task-notification fires each time this agent stops with no live background children of its own. The user can send it another message and resume it, so the same task-id may notify more than once.</note>
> <result>I have all the evidence needed. Here is my analysis.
>
> ---
>
> # CMA Platform — Combobox State-Isolation Analysis
>
> ## Summary of the failure mode
> The entire `CmaFormController` is written on the assumption that **exactly one form exists per JS document/realm**. Form identity and DOM roots are resolved through document-global singleton lookups — `document.getElementById('mainForm')` and `document.querySelector('.form-layout')` — never through an instance-owned root element. The combo cache compounds this: it is a module-level singleton backed by `sessionStorage` (shared across all same-origin iframes) keyed only by *form-name + recordId*, not by form instance. When more than one form is live in the same document context, the getters resolve to the *wrong* (first) form, so one form's combos get populated and the other's `lib-combo`s never receive options and stay empty.
>
> ---
>
> ## 1. FormController: per-form instance, but with global DOM lookups
>
> Per-form instance, **not** a `window.formController` singleton. Class is `CmaFormController` (exported as `CMA.FormController`), constructed once per rendered `form.php` page.
>
> - `cma/assets/js/form-controller.js:986` `class CmaFormController {`
> - `cma/assets/js/form-controller.js:991-1078` constructor; instance ref stored on the DOM element, not a global:
>   - `:1073` `formLayout._cmaController = this;`
>   - `:1377` (in `init()`) `formLayout._cmaFormController = this;`  ← stored under a *second* property name
> - Instantiation site is server-emitted: `cma/classes/FormTemplate.php` emits `new CMA.FormController(id, CMA.formConfig)` (referenced in the code comment at `form-controller.js:1170`).
>
> `this.mainForm` is **not a stored field** — it's a getter returning a hardcoded ID:
> ```js
> // form-controller.js:1298
> get mainForm() {
>     return document.getElementById('mainForm');   // singleton ID lookup
> }
> set mainForm(value) { /* no-op */ }               // :1306
> ```
> So `this.mainForm` always points at whatever element currently has `id="mainForm"` in the controller's document — never at an instance-scoped subtree.
>
> Note the property-name split: the constructor writes `_cmaController` (`:1073`) while `getController()` reads `_cmaFormController`:
> ```js
> // form-controller.js:1086
> static getController(element) {
>     if (element) {
>         const layout = element.closest('.form-layout');
>         if (layout &amp;&amp; layout._cmaFormController) return layout._cmaFormController;  // :1090
>     }
>     const layout = document.querySelector('.form-layout');   // :1095 fallback = FIRST form-layout
>     return layout?._cmaFormController || null;
> }
> ```
> `init()` (`:1377`) does set `_cmaFormController`, so both names end up populated, but `main.js` reads `_cmaController` (`main.js:83,121,146,161,875`) — two parallel names for the same reference, fragile. The fallback branch (`:1095`) always returns the **first** `.form-layout`'s controller regardless of which form is meant.
>
> ## 2. How "multiple forms open" — sidepanel **iframes** (plus SPA staging)
>
> - Main content forms are loaded into `#contentArea` in the top document via `main.js` `loadPage`. Each `form.php` renders exactly one `id="mainForm"`: `cma/classes/FormTemplate.php:1458`.
> - Sub/sub-sub forms open as stacked **side panels, each an `&lt;iframe src="form.php?..."&gt;`**:
>   - `library/library.js:2044` `function lib_OpenSidePanel(...)`
>   - `library/library.js:2152` `'&lt;iframe id="' + iframeId + '" class="lib_sidepanel_content" src="' + url + '" ...&gt;'`
>   - Auto-open of subform/subsubform panels: `main.js:1564 checkForPendingSidepanel` → `lib_OpenSidePanel(...)` at `:1585, :1598, :1631`.
> - The SPA layer also keeps a **cache of up to 5 detached form wrappers** in a Map and restores them (`main.js:82-161`), and stages incoming forms off-screen in `#cma-staging` (`main.js:900-911`).
>
> So: one top-document form + N sidepanel iframes can be live simultaneously. Each iframe is its own realm, so its `getElementById('mainForm')` normally resolves within that iframe — *unless* two form subtrees land in one document (staging overlap, a restored cached wrapper, or any inline detail/subform), which is exactly the fragile case the code guards against.
>
> The design's single-form assumption is explicitly acknowledged here:
> ```
> // main.js:900-903
> // CRITICAL: Clear old content BEFORE staging new content
> // This ensures document.querySelector('.form-layout') in CmaFormController
> // finds the NEW form-layout (in staging), not the OLD one (in contentArea)
> contentArea.innerHTML = '';
> ```
> i.e. they must manually delete one form so the global `querySelector('.form-layout')`/`getElementById('mainForm')` picks the right one — confirming there is no per-instance scoping.
>
> ## 3. Combo cache — module-level singleton in sessionStorage, keyed by form-name+field+recordId
>
> `cma/assets/js/modules/cma-combo-cache.js` is one IIFE singleton attached to `window.cmaComboCache` (`:262-264`), backed by `sessionStorage` (`:11-259`). `sessionStorage` is **shared across all same-origin iframes in the tab**, so every open form/panel shares this store.
>
> Key construction (`:74-80`):
> ```js
> buildKey(formId, field, recordId) {
>     let key = CACHE_PREFIX + formId + '_' + field;               // 'cma_combo_' + formId + '_' + field
>     if (recordId !== null &amp;&amp; recordId !== undefined &amp;&amp; recordId !== '')
>         key += '_' + recordId;
>     return key;
> }
> ```
> `formId` here is **not** a unique per-open-instance id — it's the form *type name* (see Q4). Two open forms of the same type with the same (or both empty/new) recordId collide on the same key. There is no notion of "which open instance" in the key, so the cache cannot isolate two simultaneously-open forms.
>
> Load path keys the cache with the **document-global** record id:
> ```js
> // form-controller.js:3141-3145
> const cacheFormId = this.getCacheFormId();
> const recordId = cmaGetRecordId();                               // no element arg → global lookup
> const cacheResult = cmaComboCache.getMultiple(cacheFormId, fieldNames, recordId);
> ```
> and `cmaGetRecordId()` with no element reads the **first** `.form-layout`:
> ```js
> // form-controller.js:928
> function cmaGetRecordId(element) {
>     const formLayout = element ? element.closest('.form-layout')
>                                : document.querySelector('.form-layout');   // FIRST form-layout
>     return formLayout?.dataset.recordId || null;
> }
> ```
> So if a second form-layout is present, the cache read/write can be keyed with the *other* form's recordId.
>
> ## 4. `getCacheFormId()` — returns the form type name (not instance-unique)
>
> ```js
> // form-controller.js:3044
> getCacheFormId() {
>     return this.jsonForm;   // e.g. "opleidingen" — the form definition name
> }
> ```
> `this.jsonForm` is the form-definition name captured at construction from `CMA.formConfig`/`data-json-form`. It is identical for every open instance of the same form type and is **not** unique per open panel. Combined with the global `cmaGetRecordId()` (Q3), the cache key can be identical or stale across two open forms.
>
> ## 5. Where `this.mainForm` resolves, and how a second form breaks it
>
> There is no per-instance `this.mainForm` assignment — it is the getter `document.getElementById('mainForm')` (`:1298`). All combo population targets it:
> ```js
> // initCombos — form-controller.js:2894
> const comboEls = this.mainForm?.querySelectorAll('lib-combo') || [];
> ...
> return this.loadAllComboOptions(combosToLoad, comboElements);   // :2928
> ```
> `loadAllComboOptions` → `applyComboOptions` (`:3138`, `:3237`) call `comboElement.setOptions(...)` on those gathered elements.
>
> Failure mechanism when &gt;1 form is live in the same document:
> - `getElementById('mainForm')` returns the **first** `#mainForm`. Both the first form's controller and a second form's controller therefore see the **same** DOM subtree.
> - Controller B's `initCombos()` collects `lib-combo`s from form A's subtree (or an empty set if B isn't first), fetches options, and applies them to form A's combos. Form B's own `lib-combo`s never get `setOptions` → they **stay empty**.
> - Identity helpers reinforce the wrong target: `getFormParam()` falls back to the first `.form-layout` (`:1324`), `cmaGetRecordId()` reads the first `.form-layout` (`:929`), and `getController()`'s fallback returns the first `.form-layout`'s controller (`:1095`). All of these silently bind to "form #1."
>
> There is even an "identity tripwire" (`verifyIdentity`, `:1133-…`) that re-syncs `this.jsonForm/formId` from `document.querySelector('.form-layout')` — again the first one — which will actively *rewrite* a second controller's identity to match the first form if both coexist.
>
> ---
>
> ## Shared/mutable state that breaks isolation (root causes)
> 1. `get mainForm()` = `document.getElementById('mainForm')` — a hardcoded singleton ID; no per-instance form root. (`form-controller.js:1298`)
> 2. Global identity resolvers assume one form per document: `cmaGetRecordId()` (`:928`), `getFormParam()` (`:1324`), `getController()` fallback (`:1095`), `verifyIdentity()` re-sync from first `.form-layout`.
> 3. Combo cache is a process-wide singleton over `sessionStorage` (shared across same-origin iframes), keyed only by `getCacheFormId()` = form *name* + field + recordId (`cma-combo-cache.js:74`, `form-controller.js:3044,3141-3145`) — no per-open-instance dimension, so two open forms collide or key off the wrong record id.
> 4. Controller reference stored under two different property names (`_cmaController` vs `_cmaFormController`) across constructor (`:1073`), `init()` (`:1377`), `getController()` (`:1090/1095`) and `main.js` (`:83` etc.) — fragile, and lookups still fall back to the first form.
>
> The fix direction (not applied): scope every DOM/identity lookup to an instance-owned root element (the controller's own `.form-layout`, e.g. via `closest()`/passing `element`) instead of `document.getElementById('mainForm')`/`document.querySelector('.form-layout')`, and add a per-open-instance dimension to the combo cache key.</result>
> <usage><subagent_tokens>51211</subagent_tokens><tool_uses>27</tool_uses><duration_ms>249360</duration_ms></usage>
> </task-notification>

> <task-notification>
> <task-id>a8487449b8d42cb56</task-id>
> <tool-use-id>toolu_016ueF4cbZuYMDdFWEfnReyT</tool-use-id>
> <output-file>/tmp/claude-1000/-mnt-c-repos-cma-platform/4c10bf01-6ac5-42cd-b645-c012d5313179/tasks/a8487449b8d42cb56.output</output-file>
> <status>completed</status>
> <summary>Agent "Web component instance-state leaks" finished</summary>
> <note>A task-notification fires each time this agent stops with no live background children of its own. The user can send it another message and resume it, so the same task-id may notify more than once.</note>
> <result>I have enough to report. All non-min source files in scope have been swept for the five patterns. Findings below.
>
> ## State-leak audit — `cma/webcomponents/*.js` + `library/webcomponents/*.js`
>
> ### Components WITH a genuine cross-instance leak / singleton assumption
>
> | Component | Leak? | Location + excerpt | Severity / effect |
> |---|---|---|---|
> | `cma/webcomponents/cma-groupbox.js` | **YES** (pattern 5 + 4) | `:190` `document.querySelector('cma-groupbox[group-id="' + groupId + '"]')` (also `:199`, `:208`); `:138-143` `document.getElementById('_g'+this._groupId+'_'+index)` | **Moderate.** `grp_flip`/`grp_set` find "the" groupbox by `group-id` and row visibility is toggled via global `document.getElementById` keyed only on `groupId`. Two open forms that reuse the same group-id collide — the wrong form's group toggles / rows hidden. Cross-form bleed, but affects group collapse, not combos. |
> | `cma/webcomponents/cma-htmledit.js` | **YES** (pattern 3 + 4) | `:363` `window.SetFKEditorConfig = function(config){ window.CMA?.editor?.setConfig(config); }`; `:367/378` `document.querySelector('cma-htmledit[name="${fieldname}"]')`; `:125` `CKEDITOR.instances[textarea.id]` | **Medium — most combo-relevant.** "FK" = foreign-key editor. `SetFKEditorConfig` pushes into a single global config; component/CKEDITOR lookups are keyed by field *name*/textarea id, not by the open form. Two forms with the same field name overwrite/collide. This is the closest in-scope match to "FK/combobox data not isolated per form." |
>
> ### Module-level / static mutable state that is shared-by-design (reviewed, not bugs)
>
> | Component | Item | Why it's safe |
> |---|---|---|
> | `library/webcomponents/lib-field.js` | `:48` `let _libFieldId = 0` → `:81` `` `lib-field-${++_libFieldId}` `` | Monotonic unique-id generator — the *correct* anti-collision pattern, never read as shared state. |
> | `cma/webcomponents/cma-schema-canvas.js` | `:62` `static _instanceCounter = 0` | Same: monotonic instance-id counter. All real state (`:70-93`) is per-instance `Map`/`Set`. |
> | `library/webcomponents/lib-shared-styles.js` | `:28` `const styleSheetCache = new Map()` keyed by category | Intended cache of immutable constructable stylesheets shared across instances. Safe. |
> | `library/webcomponents/lib-toaster.js` | `:400` `let _instance` / `let _config`; `:406` `document.querySelector('lib-toaster')` | Intentional app-wide singleton toaster. |
> | `library/webcomponents/lib-tip.js` | `:47` `let skipList = null` | App-global "tips to skip" cache; global by design, not per-form. |
> | `library/webcomponents/lib-message.js` | `:394` fixed id `'lib-top-notification'` singleton banner; `window.libMessage`/`Lib_ToonTopNotificatie` factories | Single global top-notification region; helpers are stateless factories. |
> | `library/webcomponents/lib-log.js` | module-level saved console methods + `window.LIBLOG_CONFIG` | Intentional global logging interceptor. |
> | `cma/webcomponents/shared-icons.js` `:334-341`; `cma-base-component.js:360`; `lib-dialog.js:1224`; `lib-menu.js:595`; `lib-fileuploader.js:653`; `lib-table.js` static `_ensureStyles`/`_ensureDependencies` | `window.*` assignments hold **class refs, constants (ICON_CODES), stateless factory helpers, or idempotent style/dep injectors** | No per-instance state stored; not overwritten by a 2nd instance. |
> | `cma-blockeditor.js:38` `static blockTypes`, `cma-field-config.js:62`/`cma-schema-canvas.js:43` `static _typeTranslations`, `lib-radio-group.js:132` `static parseOptions` | Constant lookup tables / pure helpers | Read-only; not mutated. |
>
> ### Components with NO shared-state issue (clean)
> `cma-base-component.js`, `cma-blockeditor.js`, `cma-combo.js` (deprecated no-op — real combo is in the excluded `lib-combo.js`), `cma-conditions-panel.js`, `cma-field-config.js`, `cma-fold.js`, `cma-group-config.js`, `cma-launcher.js`, `cma-param-config.js`, `cma-query-preview.js`, `cma-schema-canvas.js`, `cma-sort-config.js`, `cma-sortlist.js`, `cma-sql-editor.js`, `cma-tabs.js`, `cma-toolbar.js`, `cma-tree.js`, `shared-icons.js`, `UNUSED_cma-checklist.js`, `UNUSED_cma-rights-matrix.js`, `lib-arrowsteps.js`, `lib-datepicker.js`, `lib-dialog.js`, `lib-field.js`, `lib-fileuploader.js`, `lib-gauge.js`, `lib-histogram.js`, `lib-menu.js`, `lib-radio-group.js`, `lib-search-input.js`, `lib-sheet.js`, `lib-switch.js`, `lib-table.js`, `lib-timepicker.js`, `lib-toaster.js` (all shadow-DOM `getElementById`/`querySelector` calls are scoped to `this.shadowRoot`, i.e. per-instance).
>
> ### Note on the combobox bug
> `cma-combo.js` is a deprecated no-op; the actual combo registers from `lib-combo.js`, which you excluded as already-audited-clean for *module-level* state. Within the in-scope files the only leaks that touch field/FK data are **`cma-htmledit.js`'s global `SetFKEditorConfig` + name-keyed lookups** (FK = foreign key = the combo's data source) and, secondarily, groupbox's group-id collisions. If the empty-combo repro doesn't involve the FK editor path, the per-form leak is most likely inside `lib-combo.js` itself (e.g. option-loading that reads a global `window.CMA` config or a fixed DOM id rather than per-instance) — worth re-auditing lib-combo's option-population code specifically, even though its top-level `let`/`var` scope is clean.</result>
> <usage><subagent_tokens>29355</subagent_tokens><tool_uses>9</tool_uses><duration_ms>177358</duration_ms></usage>
> </task-notification>

> <task-notification>
> <task-id>a7fef20f6aa080152</task-id>
> <tool-use-id>toolu_016pV1GZSUAZjPeeZBsq1VSX</tool-use-id>
> <output-file>/tmp/claude-1000/-mnt-c-repos-cma-platform/4c10bf01-6ac5-42cd-b645-c012d5313179/tasks/a7fef20f6aa080152.output</output-file>
> <status>completed</status>
> <summary>Agent "Client-side global/persistent state leaks" finished</summary>
> <note>A task-notification fires each time this agent stops with no live background children of its own. The user can send it another message and resume it, so the same task-id may notify more than once.</note>
> <result>I have a comprehensive picture. Here is my report.
>
> ---
>
> # Client-side shared/persistent state audit — "multiple forms open → combos/fields empty"
>
> ## Critical architecture fact that reframes everything
> Second forms open as **sidepanel iframes**, not same-document nodes: `lib_OpenSidePanel` builds a `.lib_sidepanel_container` containing an `&lt;iframe&gt;` (`/mnt/c/repos/cma_platform/library/library.js:2044`, iframe/`contentDocument` at `:2189`, `:1034`; stack counters `lib_sidepanel_counter`/`lib_sidepanel_stack` at `:2008-2009`).
>
> Consequence: each open form has its **own `window`, its own `document`, and its own copy of every module**. So `window.*` globals and module-level `let/const/Map` state are **per-frame and cannot collide across forms**. The real cross-form collision surface is only:
> - **localStorage** (shared across all same-origin frames), and
> - **sessionStorage** (shared across same-origin frames in the same tab), and
> - code that explicitly writes **`window.top.*`**.
>
> This narrows the plausible culprits sharply. Ranked below.
>
> ---
>
> ## Ranked culprits
>
> ### 1. Shared sessionStorage combo cache keyed by form-name, not per open instance (TOP suspect)
> Keys are `cma_combo_&lt;formId&gt;_&lt;field&gt;[_&lt;recordId&gt;]`:
> - `/mnt/c/repos/cma_platform/cma/assets/js/modules/cma-combo-cache.js:74-78` `buildKey(formId, field, recordId)` — recordId is *optional* and omitted for record-independent combos.
> - `/mnt/c/repos/cma_platform/cma/assets/js/form-controller.js:560` `CACHE_PREFIX = 'cma_combo_'`; invalidation key `combo_ + getCacheFormId() + _ + fieldName` at `:2987`.
>
> Because sessionStorage is shared across the main frame and same-origin sidepanel iframes, **two open instances of the same form share these keys**. If one instance populates `cma_combo_&lt;form&gt;_&lt;field&gt;` via a record-*independent* path (no recordId) while another instance's combo is actually record-scoped — or an invalidation in one frame (`sessionStorage.removeItem`, form-controller `:2988`) races the other frame's read — the second form gets a stale/empty option set. Shared same-origin sessionStorage is the only cache that both frames touch, which matches the "combos empty" symptom. (These two files are owned by the other agents — flagged here purely as the cross-frame storage interaction.)
>
> ### 2. Shared localStorage keys namespaced by form-NAME, so a second instance of the same form clobbers the first
> - `cma_lastRecord_&lt;jsonForm&gt;` — `/mnt/c/repos/cma_platform/cma/assets/js/form-controller.js:2671,2685,2700` (set/get/remove). Two instances of the same form (main list + sidepanel edit, or two sidepanels) overwrite each other's "last selected record", so on refresh/reselect a form can target the wrong record → fields render empty.
> - `cma_filter_field_&lt;filterFieldName&gt;` — `:2064`, and read/write at `:6531,:6560,:6638,:6652`. Keyed only by filter-field name (not by form or instance). Any two open forms sharing that filter-field name clobber each other's persisted filter → list filtered to nothing / fields empty.
> - `cma_filter_field_&lt;filterIdName&gt;` — `:6638`.
>
> These are the clearest "shared global storage key, two forms clobber it" instances in the codebase.
>
> ### 3. `window.top.*` CKEditor dialog singletons (cross-frame, rich-text fields)
> `/mnt/c/repos/cma_platform/cma/assets/js/cma.js:789-882` writes single global slots on the top window:
> - `window.top.activeEditor` (`:798,:805`), `window.top.activeEditorBookmark` (`:789,:791,:838,:882`), `window.top.selectedAnchor` (`:799,:837`), `window.top.selectedImage` (`:806,:857`).
>
> Last editor to open a link/image dialog wins; the other form's rich-text operations then target the wrong editor. Genuine cross-form collision, but scoped to CKEditor link/image insertion (fields), not comboboxes.
>
> ### 4. `document.querySelector('.form-layout')` first-match as an implicit "current form" pointer
> `getController()` and sibling helpers fall back to the **first** `.form-layout` in the document when called without an element:
> `/mnt/c/repos/cma_platform/cma/assets/js/form-controller.js:1095` (getController), `:1105` (getRecordId), `:929,:952,:968,:978,:1016,:1134`.
>
> Isolated across iframes, but if two `.form-layout` nodes ever coexist in one document (SPA content swap in `main.js`, or an inline subform), all no-arg lookups resolve to the first form, so field/combo population/state reads point at the wrong DOM. (form-controller internals — other agent; flagged as the global-lookup interaction.)
>
> ### 5. `CMA.formConfig` shared singleton, re-emitted per page/form
> Emitted as a single global by the server: `/mnt/c/repos/cma_platform/cma/classes/FormTemplate.php:294` and `:440` (`CMA.formConfig = {…}`), and the controller is constructed from it at `:568`. The controller documents this exact hazard: a page-swap re-emit repoints the global while a stale controller holds the old object (`/mnt/c/repos/cma_platform/cma/assets/js/form-controller.js:1169-1184`), mitigated by `verifyIdentity` (`:1133`). Per-frame, so mainly a same-frame SPA/navigation hazard rather than a two-iframe one.
>
> ---
>
> ## Things that look scary but are NOT cross-form collisions (per-frame, or correctly namespaced)
> - **Module-level mutable state is per-frame**, so isolated across sidepanels: request-coalescer `inFlight = new Map()` (`/mnt/c/repos/cma_platform/cma/assets/js/modules/cma-request-coalescer.js:13`; note it parses the body once and shares the resulting `data` object — safe for reads, but a caller mutating the returned array would affect same-frame co-callers); cma-perf `timers/counters/gauges/measurements` (`/mnt/c/repos/cma_platform/cma/assets/js/modules/cma-perf.js:28-31`); in-memory `_pageCache` Map (`/mnt/c/repos/cma_platform/cma/assets/js/main.js:56`, keyed by full page URL incl. form+record).
> - **`window.cma*` singletons** (`cmaComboCache`, `cmaFormCache`, `cmaRecordCache`, `cmaRequestCoalescer`, `cmaNotification`, `cmaApiError`, `cmaPerf`, `cmaLog`, `cmaRequestTracker`) are all `window.X = X` service objects re-created per frame — not per-form state pointers. No `window.currentForm` / `window.activeController` / `window.currentRecord` singleton exists anywhere.
> - **table-preferences** is correctly per-form: `this.storageKey = cma_v2_table_prefs_${formId}` (`/mnt/c/repos/cma_platform/cma/assets/js/table-preferences.js:12`; also form-controller `:4809,:5162,:5226,:5229`).
> - **url-manager.js is stateless** — `parseURL()` returns a fresh object from `window.location` each call (`/mnt/c/repos/cma_platform/cma/assets/js/url-manager.js:25-82`); there is no stored "current form" global to overwrite.
> - **App-global-by-design localStorage keys** (not per-form, correctly so): `cma_v2_menu_state` / `cma_v2_menu_collapsed` (`main.js:460,509,516`), `cma_popup_style` (`library.js:2017,2029`), request-tracker/error-handler log stores.
> - **`window._cmaGlobalSwitchHandlerInitialized`** (`/mnt/c/repos/cma_platform/cma/assets/js/inline-edit.js:2666`) is a one-shot init guard, not per-form data. inline-edit's own "current row/cell" (`this.editingRow`, `this.editingRowId`, `this._contextMenuRow`, `this._pending`) is per-instance, though note its no-arg global lookups like `document.querySelector('.inline-edit-button-row')` at `:1387` share the first-match hazard of item 4 within a frame.
>
> ## Bottom line
> Given the iframe architecture, the highest-probability cause of "combos/fields empty with multiple forms open" is **shared same-origin storage keyed by form-name rather than by open-instance** — specifically the `cma_combo_*` sessionStorage cache (item 1) and the `cma_lastRecord_*` / `cma_filter_field_*` localStorage keys (item 2), where a second instance of the same form (or two forms sharing a filter-field name) clobbers the first. The `window.*`/module singletons and `CMA.formConfig` are per-frame and are not the cross-form vector here.</result>
> <usage><subagent_tokens>54215</subagent_tokens><tool_uses>24</tool_uses><duration_ms>272035</duration_ms></usage>
> </task-notification>

> <task-notification>
> <task-id>a1f09c9f392930220</task-id>
> <tool-use-id>toolu_01PD2ArgGEdSPSUp5rkftHRt</tool-use-id>
> <output-file>/tmp/claude-1000/-mnt-c-repos-cma-platform/4c10bf01-6ac5-42cd-b645-c012d5313179/tasks/a1f09c9f392930220.output</output-file>
> <status>completed</status>
> <summary>Agent "Server-side PHP state leaks" finished</summary>
> <note>A task-notification fires each time this agent stops with no live background children of its own. The user can send it another message and resume it, so the same task-id may notify more than once.</note>
> <result>I have enough evidence to report. Here are my findings, ranked by likelihood.
>
> ## Summary of the combo-serving architecture
>
> There are three distinct server paths, and they differ sharply in how much shared/cached state they carry:
>
> - **Live form-field combos (primary UI path):** `form_api.php` actions `combo`/`combos`/`init` → `FormDataProvider::getJsonFormComboOptions()` (`cma/classes/FormDataProvider.php:1264`). This path does **NOT** use the `Cache` layer at all and resolves the form purely from the per-request `jsonForm` request parameter (`cma/form_api.php:382`). It is essentially stateless.
> - **List/table-view FK combos:** `TableService` → `OptionsService::getComboOptionsForFields()` (`cma/classes/Services/OptionsService.php:584`, called at `TableService.php:353` and `:763`). This path uses **static in-memory caches and a persistent file/redis cache** and is where the real server-side collision risk lives.
> - **Legacy server-rendered combos:** `FormControlHelper::internalComboBox()` (`cma/classes/FormControlHelper.php:153`).
>
> Because the primary interactive combo endpoint is stateless and un-cached, if the empty combos are the normal select2 form fields, the server is an unlikely cause (that points to the client sending a wrong/empty `jsonForm` when two forms are open). The ranked culprits below all live in the `OptionsService`/legacy paths.
>
> ---
>
> ## Rank 1 (most plausible): `OptionsService::findFieldIndex` static cache keyed by first-3 field names, NOT by formId
>
> `cma/classes/Services/OptionsService.php:310` and `:319-345`:
>
> ```php
> protected static array $fieldIndexCache = [];
> ...
> // line 328:
> $cacheKey = is_object($arrRep) ? spl_object_id($arrRep)
>           : md5(serialize(array_slice((array)$fieldNames, 0, 3)));
> if (!isset(self::$fieldIndexCache[$cacheKey])) {
>     $lookup = [];
>     foreach ($fieldNames as $i =&gt; $name) { ... $lookup[$nameLower] = $i; }
>     self::$fieldIndexCache[$cacheKey] = $lookup;
> }
> return self::$fieldIndexCache[$cacheKey][$fieldNameLower] ?? -1;
> ```
>
> `getFormDef()` returns a plain legacy array (from `JsonFormLoader::convertToLegacyFormat`, `JsonFormLoader.php:1036`), so the **array branch** applies: the cache key is `md5(serialize(first 3 field names))`.
>
> Why this matches "second form's combos come back empty":
> - The static `$fieldIndexCache` persists across requests within a PHP-FPM/FastCGI worker (it is never reset).
> - Two different forms that share their first three field names (extremely common — e.g. `ID, Naam, Datum, …`) hash to the **same key**. Whichever form populates the entry first wins; the stored lookup map contains only *that* form's field→index mapping.
> - When the second form later asks for one of *its own* fields that the first form did not have, the lookup returns `-1` → `getComboOptions()` returns `'Veld niet gevonden'` / empty options (`OptionsService.php:83-86`), while fields shared in the first 3 positions still resolve correctly. That is precisely "form A works, form B's combos are empty."
> - The object branch (`spl_object_id`) is even less safe in principle: `spl_object_id` values are **recycled** once an object is freed, so a later request's `arrRep` can inherit a freed object's id and read a stale map — but the array branch is the one actually hit here.
>
> This is the strongest "static holds stale/wrong per-form state, not keyed by form" candidate. Reached via `getComboOptions` (`:83`) and `getComboOptionsForFields` (`:603`), i.e. the list/table-view combo path.
>
> Note: the live `getJsonFormComboOptions` does its own inline field loop (`FormDataProvider.php:1289`) and does NOT call `findFieldIndex`, so this bug only bites the OptionsService/TableService path.
>
> ---
>
> ## Rank 2: `OptionsService` combo-result cache keyed by SQL only (empty results cached; databaseId dropped from key)
>
> Two places build the same key:
> - `cma/classes/Services/OptionsService.php:174`: `$cacheKey = 'combo_' . md5($sql . ($databaseId !== '' ? '_db' . $databaseId : ''));`
> - `cma/classes/Services/OptionsService.php:639`: identical construction in the batch path.
>
> Backed by a static in-memory map `private static array $sqlResultCache = [];` (`:29`) plus the persistent `Cache` (`:184-207`, `:648-664`).
>
> Problems relevant to the failure mode:
> 1. **Empty results are cached.** `:205-207` and `:662-664` store `$options` unconditionally into both the static map and the persistent cache (TTL `COMBO_CACHE_TTL = 1800`, `:58`) even when the query returned zero rows. Once any request produces an empty result for a given SQL, every form/request hitting that SQL gets empty for 30 minutes (and for the life of the worker via the static map).
> 2. **databaseId is omitted from the key when empty** (`:174`, `:639`). Two forms whose combo fields generate an identical SQL string but resolve against different physical connections (both with `databaseId === ''`, differentiated only by the `global $conn` current connection) collide on one key → form B can serve form A's cached options, or a cached empty.
> 3. The key contains **no formId and no record context** in the single-field path (`getComboOptions` calls `buildComboSql` without `recordContext`, `:105`), so a record-dependent field falls back to the source-table query and shares one cache entry across contexts.
>
> ---
>
> ## Rank 3: `global $conn` used as a shared "current connection" pointer
>
> `cma/classes/Services/OptionsService.php:134-136` and `:598-599`:
>
> ```php
> CmaRepository::openConnectionById($formDef-&gt;getDatabaseId());
> global $conn;
> ```
>
> and `details_getdata.php` builds `$Myconn` similarly (`details_getdata.php:40-42`). `Database::getConnection()` pools connections statically (`Database.php:308-318`, `$namedConnections`/`$connData`/`$connRep`). Within a single request this is fine, but it is mutable shared state representing "the current form's connection." It is a lower-likelihood cross-form cause because concurrent forms are normally separate FPM processes; I flag it because combined with Rank 2's databaseId-less cache key it can produce wrong-connection results.
>
> ---
>
> ## Rank 4 (least likely of the real candidates): `FormControlHelper` legacy combo cache
>
> `cma/classes/FormControlHelper.php:178`:
> ```php
> $cacheId = 'CMA_combo_' . $formId . '_' . $name . '_' . $extraCacheId;
> ```
> and `:296` `Cache::set($cacheId, $completeCombo);` (note: **no TTL argument** → default 24h).
>
> This key is comparatively well-formed — it includes `formId`, field `name`, and `$extraCacheId`, which is set to the record id when the SQL has an `[ID]` placeholder (`:139`, `$hasIdPlaceholder ? $recordId : ''`). So cross-form collision is unlikely here. The residual risk is that an **empty combo is cached with no explicit TTL** (24h) for a record-dependent field, but only within the same form+record key.
>
> ---
>
> ## Things I checked that are NOT the culprit
>
> - `JsonFormLoader::load()` caches form definitions correctly keyed by normalized form name (`JsonFormLoader.php:83` `$cache` array, `:462-512`) — no single "current form" pointer.
> - `BaseFormService::$formDefCache` and the persistent `formdef_&lt;formId&gt;` cache are keyed by `formId` (`BaseFormService.php:61-86`) — correct.
> - `GetFormDef()` in `cma/bootstrap.inc:1754` resolves per formId → per form name; its `$_formDefCache` is an undeclared function-local (dead cache), harmless.
> - The `arrRep()` global helper (`bootstrap.inc:2093`, `global $arrRep`) is reassigned per request in `details_getdata.php:31`; consistent within a request.
> - The live `getJsonFormComboOptions` path performs **no caching** and resolves the form from the request param — so if the empty combos are the ordinary select2 form fields, the server is not holding stale state; investigate whether the client sends the correct `jsonForm`/`field` when a second form is open.
>
> ## Recommended focus for a fix (no changes made)
> 1. Key `$fieldIndexCache` by something form-unique (e.g. include `formId` or a full hash of all field names), or drop the static entirely — `OptionsService.php:328`.
> 2. Do not cache empty option results, and include `databaseId` unconditionally in the `combo_` key — `OptionsService.php:174/205-207` and `:639/662-664`.
> 3. Confirm whether the failing combos are list/table-view (OptionsService path → Rank 1/2 apply) or live form fields (JSON path → likely client-side `jsonForm` param).</result>
> <usage><subagent_tokens>65404</subagent_tokens><tool_uses>33</tool_uses><duration_ms>330978</duration_ms></usage>
> </task-notification>

> maar nogmaals, dit soort state management errors zijn architectureel, dat moet ook daar opgelost worden, niet met globals werken en alleen geparameteriseerd cachen. Zorg dat een lege cache onderscheiden kan worden van een cache die nooit gevuld is, dus nul is nooit gevuld en [] leeg.

> for an image field in the cma; create an icon to edit is, thw same way we have done for karaat at https://www.karaatedelstenen.nl/tools/quick_add_stone.php

> /login

> push and commit

> the file browser dialog is not browser aware,  perhaps we should show the files first and then the details?

> i installed the latest version, but the storybook does not work, i wonder if the full linearicons.css removal was so smart because it should show there all the icons

> 2e check: er zijn dus nog steeds 2 linearicons fonts, de totale en de gecomprimeerde met alleen de gebruikte icons?

> in welke versie is het tools menu gefixt?

> de db health check mag uit de tools, bestand mag weg

> database compacteee : of werkend maken (hij gaat naar het verkeerde pad), of weggooien, want dat kan volgend mij niet via odbc

## 2026-07-12

> .cma-launcher__group-title {
>     font-size: 18px;
>     remove text-transform: uppercase;

> <task-notification>
> <task-id>a0b0571fe0326eb50</task-id>
> <tool-use-id>toolu_01Y4ksATWWgCfDsCXcZrKGJv</tool-use-id>
> <output-file>/tmp/claude-1000/-mnt-c-repos-cma-platform/4c10bf01-6ac5-42cd-b645-c012d5313179/tasks/a0b0571fe0326eb50.output</output-file>
> <status>completed</status>
> <summary>Agent "Map karaat app for stone features" finished</summary>
> <note>A task-notification fires each time this agent stops with no live background children of its own. The user can send it another message and resume it, so the same task-id may notify more than once.</note>
> <result>I have a complete map. Here is the structured summary.
>
> # Karaat webshop — feature map
>
> ## Big picture / naming
> "Stenen" (stones for sale) and "producten" are the **same entity**: table **`tblProducten`**, exposed via the form **`steensoorten_producten`** (form title "Producten", `titleSingular` "Product"). There is **no separate `tblStenen` table** — a "steen" is a row in `tblProducten`. "Soorten" (stone types) = **`tblSteensoorten`** via form **`steensoorten`**. Backend is MS Access (`db/main.mdb`, connection name `main`/`conn_data`); databases defined in `/mnt/c/repos/karaat/data/databases.json`.
>
> ---
>
> ## 1. Database schema (MOST IMPORTANT)
>
> ### `tblSteensoorten` — stone types ("soorten")
> Form: `/mnt/c/repos/karaat/assets/forms/steensoorten.json` (`"table": "tblSteensoorten"`, `idField: id`, sourceFormId **18**).
> Key columns (field `name` = exact column):
> - `id` — PK
> - `Soortnaam` (UK/English name), **`SoortnaamNL`** (Dutch name, `detailField`) — steensoorten.json:31-45
> - `bVisible` (checkbox) — the **active/visible flag** for soorten — :24-29
> - **`SoortBeschrijvingNL`** / `SoortBeschrijvingUK` — description memos (HTML) — :66-72, :93-99
> - **`Beeld`** — image column (`type:image`, `path: images/producten/`, resize 600x400) — :74-84
> - `SoortKeywords`, `gemdaturl`, `bZeldzaam`, `bGroteVoorraad`
> - Pricing: **`PrijsStart`** (start price), **`PrijsPerKaraat`** (price per carat), `PrijsPerKleur` — :106-125 (used to compute product price in `tools/steen_price.php`)
> - Technical: `RI`, `Birefringence`, `Mohsscale`, **`sg_min`/`sg_max`** (specific gravity range) — :132-170
> - Subform: Producten via `fkSteensoort` — :172-180
>
> ### `tblProducten` — products / stones ("producten"/"stenen")
> Form: `/mnt/c/repos/karaat/assets/forms/steensoorten_producten.json` (`"table": "tblProducten"`, **`idField: Id`**, sourceFormId **6**).
> Key columns:
> - `Id` — PK
> - **`bLeverbaar`** (checkbox, caption "Actief") — **the active/online flag** — steensoorten_producten.json:34-40. (Confirmed in listQuery: `IIf(bLeverbaar=True,'Actief','Niet tonen')` :10, and used in every front-end query as `tblProducten.[bLeverbaar]=True`.)
> - `bGecontroleerd` (checkbox "Gecontroleerd" — second gate; front-end requires `bGecontroleerd=True`), `bCheckedSecat`
> - `aantal` (item count), `voorraad` (stock)
> - **`fkSteensoort`** — FK to `tblSteensoorten.id` (the soort foreign key), combobox — :100-109
> - `fkCategorie` — FK to `tblProductCategorien.Id` (1=losse stenen, 7=paren, 3=partijen) — :88-98
> - Pricing: **`Prijs`** (actual), **`BerekendPrijs`** (calculated) — :73-87
> - **`Beeld`** — image column (`type:image`, `path: images/producten/`, resize 600x400) — :142-153
> - `video`, `VideoStill` (path `images/video/`), `BlockVideo`
> - **`Beschrijving`** — description memo (HTML) — :181-188
> - **`Karaat`** — weight in carats (dataType 5 decimal) — :205-211
> - **`afm1`, `afm2`, `afm3`** — dimensions L×B×H in mm (decimal) — :212-234
> - `datestamp`, `bZeldzaam`, `fkCertificaat`
> - Inkoop (purchase/sourcing) group: `inkoopNr`, `InkoopTitel`, `inkoopBeeld` (path `images/inkoop/`), **`inkoopPrijs`** (USD cost), `inkoopUrl`, `inkoopVindplaats` — :240-281
> - SG-control (read-only, auto): `sg_score`, `sg_calculated`, `sg_reason` — :287-313
>
> ### Junction / lookup tables (many-to-many on products)
> From the checklist SQL in steensoorten_producten.json:111-136:
> - **`tblVormen`** (shapes; `ID`, `VormNL`) ↔ **`tblProductVormen`** (`fkProduct`, `fkVorm`)
> - **`tblKleuren`** (colors; `ID`, `kleurNL`) ↔ **`tblProductKleuren`** (`fkProduct`, `fkKleur`)
> - **`tblBehandelingen`** (treatments; `BehandelingNL`) ↔ **`tblProductBehandelingen`** (`fkProduct`, `fkBehandeling`)
> - Also `tblProductCategorien` (`Id`,`Naam`), `tblCertificaten` (`ID`,`NaamCertificaat`).
>
> ---
>
> ## 2. Image handling
> - Images live under **`/images/producten/`** (web) — see `IMG_PATH = '/images/producten/'` in `/mnt/c/repos/karaat/tools/quick_add_stone.php:230`. Both `tblProducten.Beeld` and `tblSteensoorten.Beeld` forms declare `"path": "images/producten/"`.
> - The `Beeld` column stores **just the filename** (random name; `"randomName": true`), not a path. The front-end/tool prefixes `IMG_PATH + filename`.
> - Upload flow (quick_add_stone.php): file POSTed to **`/cma/imageupload_crop_upload_handler.php?path=/images/producten/`**, which returns `{success, filename}`; that filename is written into the hidden `Beeld` input (`setBeeld()` :320-324) and saved with the record via `/cma/form_api.php` `action=save`.
> - Image editing (rotate/crop): **`/cma/image-editor.php?basepath=/images/&amp;path=producten/&amp;file=&lt;Beeld&gt;`** in a lib-dialog iframe; posts back `{type:'image-editor-complete', file}` (:362-389).
> - Responsive/WebP variants generated from the base name; the raw `_resized{W}x{H}` suffix is stripped when resolving (see `views/soorten.inc:39-47`, `App\Library\ResponsiveImage`). Cards use `data-img` (lazy-load) not `src` — `filter.inc:162`.
> - Linking product→image is simply the `Beeld` string column on the same `tblProducten` row (no separate image table). Updating an existing stone's photo: `/mnt/c/repos/karaat/ajax_product_update.php` (called from the dedupe "Vul aan met foto + prijs" path, quick_add_stone.php:694-726).
>
> ---
>
> ## 3. Front-end "Stenen" navigation &amp; list pages
> - **Front-end routing** is web.config rewrite → `_bootstrap_wrapper.php?pagina=X` → **`/mnt/c/repos/karaat/index.php`** `$viewMap` (index.php:15-33) → `views/X.inc`. E.g. `web.config:176-181` rewrites `^soorten` to `_bootstrap_wrapper.php?pagina=soorten`. To add a new front-end list page you add: (a) a rewrite rule in `web.config`, (b) a `$viewMap` entry in index.php, (c) a `views/&lt;name&gt;.inc` file.
> - The public "Stenen" experience is the **`/aanbod`** listing → **`/mnt/c/repos/karaat/views/aanbod.inc`** (filter UI + paginated card grid; soort/shape/color/treatment filters built via `FilterWriteSQLElement`), and the A–Z types index **`/soorten`** → **`/mnt/c/repos/karaat/views/soorten.inc`** (one card per soort, links to `/aanbod?soort=&lt;id&gt;` and `/soort/&lt;name&gt;`).
> - Categories map to routes: cat 1 → `/aanbod`, cat 7 → `/aanbod-paren`, cat 3 → `/aanbod-partijen` (soorten.inc:28).
> - The main top nav bar (`ul.pageMenu__list`, active-item logic in index.php:88-96, href pattern `/pagina/&lt;menuID&gt;/`) is rendered by the **platform**, not the site code — it's driven by CMA-managed pages (`tblPagina`-style), so a public menu entry is added through the CMA, whereas ad-hoc list pages are added via the viewMap/web.config path above. The header itself only emits the utility links (FAQ/Contact) at header.inc:447-454.
>
> Note: **`/mnt/c/repos/karaat/data/menu.json`** is the **CMA admin menu** (not the public site menu). Its "Stenen" group (menu.json:5-47) contains: Stenen → form `steensoorten_producten` (formId 6), Soorten → form `Steensoorten` (formId 18), Vormen, and "Snel toevoegen" → `/tools/quick_add_stone.php`.
>
> ---
>
> ## 4. Standard stone web component — `&lt;karaat-stone&gt;`
> - Source: **`/mnt/c/repos/karaat/assets/js/karaat.js:359-410`** (merged into `karaat.min.js`; also a minified copy in `karaat-home.min.js`). Original standalone lived at `assets/components/karaat-stone.js` (now inlined).
> - Tag: **`karaat-stone`**. Class `KaraatStone extends HTMLElement`. It renders in **light DOM** and has **no `observedAttributes`/`attributeChangedCallback`** — it does **not** build markup client-side anymore. The canonical HTML is produced **server-side** by PHP `karaat_render_stone_card($rs, $basePath, $bEditable, $maps)` in **`/mnt/c/repos/karaat/filter.inc:95`** (emits `&lt;karaat-stone&gt;&lt;div class="card card--searchResult"&gt;…&lt;/div&gt;&lt;/karaat-stone&gt;`, filter.inc:109/193). The component's only job (`connectedCallback` → `wireRemove`, karaat.js:399-406) is wiring the optional `.card__remove` (✕) button and dispatching a **`stone-remove`** CustomEvent `{detail:{id}}`.
> - Documented client-render attributes (legacy, karaat.js:376-380): `stone-id` (required), `soort`, `karaat`, `prijs`, `img`, `afm` ("lxbxd"), `url`, `certificaat`, `zeldzaam`, `video`, `removable`. Product id surfaces as `data-product` on an inner element (used by quick_add_stone.php:494-499 and wishlist JS).
> - **Edit mode**: handled by the PHP renderer, not the JS. `karaat_render_stone_card`'s `$bEditable` param (filter.inc:95, set from `Application::get('local')` at filter.inc:48) adds an **`&lt;a class=edit href=".../cma/form.php?FormID=6&amp;ID=&lt;id&gt;"&gt;edit&lt;/a&gt;`** link (filter.inc:165) and color-name labels (filter.inc:209-211 in `karaat_build_card_maps`). Cards are consumed via `/ajax_stones.php?ids=…` which returns this HTML.
>
> ---
>
> ## 5. Karaat tools pattern &amp; adding a soorten-iterating admin tool
> - Site-specific tools live in **`/mnt/c/repos/karaat/tools/`** (NOT in the platform `cma/` tree, so `composer update` never touches them). Existing examples: `quick_add_stone.php`, `sg_recalculate.php`, `gemdat_import.php`, `fill_missing_prices.php`, `find_duplicate_stone.php`, `similar_stones.php`, `steen_price.php`, `detect_stone.php`/`detect_shape.php`, `GemstoneSg.php`, `stone_picker.inc`.
> - **Tools launcher registry**: **`/mnt/c/repos/karaat/data/tools.json`** — the platform reads this via `tools_catalog.inc` and surfaces items in the CMA tools launcher (`tools.php`, linked from the CMA "Systeem → Alle beheerstools" menu). Each item: `{label, href:"/tools/xxx.php", icon, badge:"A"(admin)/"D"(dev)}`, grouped under "Karaat onderhoud".
> - **Tool bootstrap pattern** (from quick_add_stone.php:15-22): `require_once __DIR__ . '/../cma/bootstrap.inc';` then `use Cma\SecurityHelper;` and gate with `if (!SecurityHelper::isLoggedIn()) { header('Location: /cma/login.php?return=…'); exit; }`. Reuse platform endpoints: `/cma/form_api.php` (combo/checklist/save), `/cma/imageupload_crop_upload_handler.php`.
> - **DB access for a new tool** iterating soorten: use `App\Library\Database::openRS('SELECT id, SoortnaamNL, … FROM tblSteensoorten …', 'data', …)` (the `'data'` connection = `main.mdb`; see pattern in index.php:102 and `views/soorten.inc`). `App\Library\SQL` for escaping, `App\Library\Cache` for cached reads.
> - **To add a new admin tool that iterates `tblSteensoorten`**: (1) create `/tools/&lt;name&gt;.php` with the bootstrap+auth header above; (2) query `tblSteensoorten` via `Database::openRS(..., 'data')`; (3) register it in `data/tools.json` (add an item, badge `"A"`) so it appears in the CMA tools launcher; optionally also add a link under the "Stenen" group in `data/menu.json` like the existing "Snel toevoegen" → `/tools/quick_add_stone.php` entry (menu.json:37-45).
>
> ### Migrations (for schema changes)
> Site migrations are separate from the platform: **`/mnt/c/repos/karaat/migrations/site_migrations.json`**, registered in `app.php:179-186` as source `karaat` (tracking table `_cma_karaat_version`, db `data`). Add new columns to `tblProducten`/`tblSteensoorten` here (recent commits show `tblSteensoorten.Beeld` fixes, sg fields etc.).</result>
> <usage><subagent_tokens>77148</subagent_tokens><tool_uses>23</tool_uses><duration_ms>238974</duration_ms></usage>
> </task-notification>

> hoe zie ik de probleemstenen op front-end of in de cma?

> bump the version to 1.29.0 and push and commit

> https://www.karaatedelstenen.nl/cma/main.php?page=%2Fprobleemstenen is echt leeg, na herberekenen

> maak een migratie om video's te kunnen plaatsen. Zorg dat er een nieuw control type video is, vergelijkbaar met image, maar dan met de selectie *.mp4 , bij producten 1 veld maken voor een video die in /videos gebruik de bestaande file browser. In de lijstweergave een video symbool plaatsen en in de detailweergave de video tonen net als wanneer de video extern staat. Bij een interne video de <video> tag implementeren zonder geluid en met #t=0.001 achter de url om een safari bug te omzeilen.

> the openwindow centered sometimes opens the same url twice, can we put a guard against that in the function itself? It happens on multiple places to alow-level solution fixes that
>
> uploading a video that is too large leads to a cryptic error 'inexpected end of json input', please make the uploader check the size, i want a new maximum of 50mb to allow for video's

> records 1-1500 van 1827 (laden...) -> POk I want you to loop until this is finished, you claimed multilple times that this was the case, I want you to 1) acknowlegde it is not solved and prove it to moslty yourself. 2) make sure to iterate iuntil solved. Think race conditions. boundary checks etc.

> mouseover over een imgae and video placeholder en de layout verschiift, er is ook geen echte video placeholder, oogt leeg nu..

> throw a javascript error in these cases

> Failed to load img: https://www.karaatedelstenen.nl/IMG_4414.jpg in field "Beeld
>
> this should lead to a 404 not an error in the php log

> this is how a form looks on mobile, the search should be next to the buttons

> not treue:  scriptProcessor="C:\Program Files\PHP\v8.5.6\php-cgi.exe" is in the web.config in an add command

> look at what you changed and revert that, its not that difficult

> we have a live site down, don't go philisophiocal on me, fix it first

> ik kijk zo

> did you change anything to the bootstrapnor web.config handling recenyly?

> nee niet alleen /aanbod, de hele site is 404

> <?xml version="1.0" encoding="UTF-8"?>
> <configuration>
>
>     <system.web>
>         <httpRuntime maxRequestLength="1048576" executionTimeout="600" />
>         <sessionState mode="Off" />
>     </system.web>
>
>     <system.webServer>
>
>         <!-- MIME types -->
>         <staticContent>
>             <remove fileExtension=".webp" />
>             <mimeMap fileExtension=".webp" mimeType="image/webp" />
>             <remove fileExtension=".woff2" />
>             <mimeMap fileExtension=".woff2" mimeType="font/woff2" />
>             <remove fileExtension=".json" />
>             <mimeMap fileExtension=".json" mimeType="application/json" />
>             <remove fileExtension=".woff" />
>             <mimeMap fileExtension=".woff" mimeType="font/x-woff" />
>         </staticContent>
>
>         <!-- URL Rewrite rules -->
>         <rewrite>
>
>             <outboundRules rewriteBeforeCache="true">
>                 <rule name="Remove Server header">
>                     <match serverVariable="RESPONSE_Server" pattern=".+" />
>                     <action type="Rewrite" value="Who cares" />
>                 </rule>
>                 <rule name="Default Content-Type to text/html" preCondition="ContentTypeMissing">
>                     <match serverVariable="RESPONSE_Content-Type" pattern=".*" />
>                     <action type="Rewrite" value="text/html; charset=UTF-8" />
>                 </rule>
>                 <preConditions>
>                     <preCondition name="ContentTypeMissing">
>                         <add input="{RESPONSE_Content-Type}" pattern="^$" />
>                     </preCondition>
>                 </preConditions>
>             </outboundRules>
>
>             <rules>
>                 <!-- Factuur template wordt server-side via file_get_contents gelezen; directe HTTP-toegang blokkeren (404). -->
>                 <rule name="Block factuur template" stopProcessing="true">
>                     <match url="^factuur\.html$" ignoreCase="true" />
>                     <action type="CustomResponse" statusCode="404" statusReason="Not Found" statusDescription="Not Found" />
>                 </rule>
>                 <!-- Google OAuth webshop-callback -> google_callback.php via de bootstrap-wrapper. -->
>                 <rule name="Google OAuth webshop callback" stopProcessing="true">
>                     <match url="^auth/google/callback/?$" ignoreCase="true" />
>                     <serverVariables>
>                         <set name="HTTP_X_ORIGINAL_FILE" value="/google_callback.php" />
>                     </serverVariables>
>                     <action type="Rewrite" url="/_bootstrap_wrapper.php" appendQueryString="true" />
>                 </rule>
>                 <!-- CMA rewrite rules (cma_platform) -->
>                 <rule name="CMA Root" stopProcessing="true">
>                     <match url="^cma/?$" />
>                     <action type="Redirect" url="/cma/dashboard" redirectType="Found" />
>                 </rule>
>                 <!-- cma_platform: CMA rewrite rules applied (v1.20.12+) --><rule name="CMA Dashboard" stopProcessing="true">
>                     <match url="^cma/dashboard/?$" />
>                     <action type="Rewrite" url="/cma/main.php?page=dashboard.php" appendQueryString="true" />
>                 </rule>
>                 <rule name="CMA Preferences" stopProcessing="true">
>                     <match url="^cma/preferences/?$" />
>                     <action type="Rewrite" url="/cma/main.php?page=preferences.php" appendQueryString="true" />
>                 </rule>
>                 <rule name="CMA Tools" stopProcessing="true">
>                     <match url="^cma/tools/?$" />
>                     <action type="Rewrite" url="/cma/main.php?page=tools.php" appendQueryString="true" />
>                 </rule>
>                 <rule name="CMA Form list" stopProcessing="true">
>                     <match url="^cma/form/([^/]+)/?$" />
>                     <action type="Rewrite" url="/cma/main.php?page=form.php%3Fform%3D{R:1}" appendQueryString="true" />
>                 </rule>
>                 <rule name="CMA Form with record" stopProcessing="true">
>                     <match url="^cma/form/([^/]+)/([^/]+)/?$" />
>                     <action type="Rewrite" url="/cma/main.php?page=form.php%3Fform%3D{R:1}&amp;formID={R:2}" appendQueryString="true" />
>                 </rule>
>                 <rule name="CMA Form with subform list" stopProcessing="true">
>                     <match url="^cma/form/([^/]+)/([^/]+)/([^/]+)/?$" />
>                     <action type="Rewrite" url="/cma/main.php?page=form.php%3Fform%3D{R:1}&amp;formID={R:2}&amp;popup={R:3}" appendQueryString="true" />
>                 </rule>
>                 <rule name="CMA Form with subform record" stopProcessing="true">
>                     <match url="^cma/form/([^/]+)/([^/]+)/([^/]+)/([^/]+)/?$" />
>                     <action type="Rewrite" url="/cma/main.php?page=form.php%3Fform%3D{R:1}&amp;formID={R:2}&amp;popup={R:3}&amp;popupID={R:4}" appendQueryString="true" />
>                 </rule>
>                 <rule name="CMA Form with subsubform list" stopProcessing="true">
>                     <match url="^cma/form/([^/]+)/([^/]+)/([^/]+)/([^/]+)/([^/]+)/?$" />
>                     <action type="Rewrite" url="/cma/main.php?page=form.php%3Fform%3D{R:1}&amp;formID={R:2}&amp;popup={R:3}&amp;popupID={R:4}&amp;subpopup={R:5}" appendQueryString="true" />
>                 </rule>
>                 <rule name="CMA Form with subsubform record" stopProcessing="true">
>                     <match url="^cma/form/([^/]+)/([^/]+)/([^/]+)/([^/]+)/([^/]+)/([^/]+)/?$" />
>                     <action type="Rewrite" url="/cma/main.php?page=form.php%3Fform%3D{R:1}&amp;formID={R:2}&amp;popup={R:3}&amp;popupID={R:4}&amp;subpopup={R:5}&amp;subpopupID={R:6}" appendQueryString="true" />
>                 </rule>
>
>                 <!-- Domain and HTTPS redirects -->
>                 <rule name="Redirect to www subdomain">
>                     <match url=".*" />
>                     <conditions>
>                         <add input="{HTTP_HOST}" pattern="^karaatedelstenen\.nl$" />
>                     </conditions>
>                     <action type="Redirect" url="https://www.karaatedelstenen.nl/{R:0}" redirectType="Permanent" />
>                 </rule>
>
>                 <rule name="Redirect to https" stopProcessing="true">
>                     <match url="(.*)" />
>                     <conditions logicalGrouping="MatchAll">
>                         <add input="{HTTPS}" pattern="off" ignoreCase="true" />
>                         <add input="{HTTP_HOST}" pattern="^localhost" negate="true" />
>                         <add input="{HTTP_HOST}" pattern="^168\." negate="true" />
>                         <add input="{HTTP_HOST}" pattern="^172\." negate="true" />
>                     </conditions>
>                     <action type="Redirect" url="https://{HTTP_HOST}/{R:0}" redirectType="Permanent" />
>                 </rule>
>
>                 <!-- Hand /cma/* off to the child cma/web.config -->
>                 <rule name="Skip /cma to child config" stopProcessing="true">
>                     <match url="^cma($|/)" />
>                     <action type="None" />
>                 </rule>
>
>                 <!-- Friendly URL rewrites -->
>                 <rule name="edelsteen" enabled="true" stopProcessing="true">
>                     <match url="^edelsteen/([0-9]+)/(.*)" />
>                     <serverVariables>
>                         <set name="HTTP_X_ORIGINAL_FILE" value="/index.php" />
>                     </serverVariables>
>                     <action type="Rewrite" url="/_bootstrap_wrapper.php?id={R:1}" logRewrittenUrl="false" />
>                 </rule>
>
>                 <rule name="soortinfo_id" enabled="true" stopProcessing="true">
>                     <match url="^soort/([0-9]+)/bekijk" />
>                     <serverVariables>
>                         <set name="HTTP_X_ORIGINAL_FILE" value="/index.php" />
>                     </serverVariables>
>                     <action type="Rewrite" url="/_bootstrap_wrapper.php?pagina=soort&amp;soort={R:1}" logRewrittenUrl="false" />
>                 </rule>
>
>                 <rule name="soortinfo" enabled="true" stopProcessing="true">
>                     <match url="^soort/(.*)" />
>                     <serverVariables>
>                         <set name="HTTP_X_ORIGINAL_FILE" value="/index.php" />
>                     </serverVariables>
>                     <action type="Rewrite" url="/_bootstrap_wrapper.php?pagina=soort&amp;soortnaam={R:1}" logRewrittenUrl="false" />
>                 </rule>
>
>                 <rule name="diamanten" enabled="true" stopProcessing="true">
>                     <match url="^diamanten" />
>                     <serverVariables>
>                         <set name="HTTP_X_ORIGINAL_FILE" value="/index.php" />
>                     </serverVariables>
>                     <action type="Rewrite" url="/_bootstrap_wrapper.php?pagina=diamant" logRewrittenUrl="false" />
>                 </rule>
>
>                 <rule name="soortinfo_alles" enabled="true" stopProcessing="true">
>                     <match url="^soorten" />
>                     <serverVariables>
>                         <set name="HTTP_X_ORIGINAL_FILE" value="/index.php" />
>                     </serverVariables>
>                     <action type="Rewrite" url="/_bootstrap_wrapper.php?pagina=soorten" logRewrittenUrl="false" />
>                 </rule>
>
>                 <rule name="pagina" enabled="true" stopProcessing="true">
>                     <match url="^pagina/([0-9]+)/(.*)" />
>                     <serverVariables>
>                         <set name="HTTP_X_ORIGINAL_FILE" value="/index.php" />
>                     </serverVariables>
>                     <action type="Rewrite" url="/_bootstrap_wrapper.php?pageID={R:1}" logRewrittenUrl="false" />
>                 </rule>
>
>                 <rule name="aanbod-ruw" enabled="true" stopProcessing="true">
>                     <match url="^aanbod-ruw" />
>                     <serverVariables>
>                         <set name="HTTP_X_ORIGINAL_FILE" value="/index.php" />
>                     </serverVariables>
>                     <action type="Rewrite" url="/_bootstrap_wrapper.php?pagina=aanbod&amp;type=2" logRewrittenUrl="false" />
>                 </rule>
>
>                 <rule name="aanbod-ruw-en-geslepen" enabled="true" stopProcessing="true">
>                     <match url="^aanbod-ruw-en-geslepen" />
>                     <serverVariables>
>                         <set name="HTTP_X_ORIGINAL_FILE" value="/index.php" />
>                     </serverVariables>
>                     <action type="Rewrite" url="/_bootstrap_wrapper.php?pagina=aanbod&amp;type=6" logRewrittenUrl="false" />
>                 </rule>
>
>                 <rule name="aanbod-partijen" enabled="true" stopProcessing="true">
>                     <match url="^aanbod-partijen" />
>                     <serverVariables>
>                         <set name="HTTP_X_ORIGINAL_FILE" value="/index.php" />
>                     </serverVariables>
>                     <action type="Rewrite" url="/_bootstrap_wrapper.php?pagina=aanbod&amp;type=3" logRewrittenUrl="false" />
>                 </rule>
>
>                 <rule name="aanbod-paren" enabled="true" stopProcessing="true">
>                     <match url="^aanbod-paren" />
>                     <serverVariables>
>                         <set name="HTTP_X_ORIGINAL_FILE" value="/index.php" />
>                     </serverVariables>
>                     <action type="Rewrite" url="/_bootstrap_wrapper.php?pagina=aanbod&amp;type=7" logRewrittenUrl="false" />
>                 </rule>
>
>                 <rule name="aanbod" enabled="true" stopProcessing="true">
>                     <match url="^aanbod" />
>                     <serverVariables>
>                         <set name="HTTP_X_ORIGINAL_FILE" value="/index.php" />
>                     </serverVariables>
>                     <action type="Rewrite" url="/_bootstrap_wrapper.php?pagina=aanbod&amp;type=1" logRewrittenUrl="false" />
>                 </rule>
>
>                 <rule name="winkelwagen" enabled="true" stopProcessing="true">
>                     <match url="^winkelwagen" />
>                     <serverVariables>
>                         <set name="HTTP_X_ORIGINAL_FILE" value="/index.php" />
>                     </serverVariables>
>                     <action type="Rewrite" url="/_bootstrap_wrapper.php?pagina=winkelwagen" logRewrittenUrl="false" />
>                 </rule>
>
>                 <rule name="wensenlijst" enabled="true" stopProcessing="true">
>                     <match url="^wensenlijst" />
>                     <serverVariables>
>                         <set name="HTTP_X_ORIGINAL_FILE" value="/index.php" />
>                     </serverVariables>
>                     <action type="Rewrite" url="/_bootstrap_wrapper.php?pagina=wensenlijst" logRewrittenUrl="false" />
>                 </rule>
>
>                 <rule name="inloggen" enabled="true" stopProcessing="true">
>                     <match url="^inloggen" />
>                     <serverVariables>
>                         <set name="HTTP_X_ORIGINAL_FILE" value="/index.php" />
>                     </serverVariables>
>                     <action type="Rewrite" url="/_bootstrap_wrapper.php?pagina=inloggen" logRewrittenUrl="false" />
>                 </rule>
>
>                 <rule name="account" enabled="true" stopProcessing="true">
>                     <match url="^account" />
>                     <serverVariables>
>                         <set name="HTTP_X_ORIGINAL_FILE" value="/index.php" />
>                     </serverVariables>
>                     <action type="Rewrite" url="/_bootstrap_wrapper.php?pagina=account" logRewrittenUrl="false" />
>                 </rule>
>
>                 <rule name="eerlijk-over-edelstenen" enabled="true" stopProcessing="true">
>                     <match url="^eerlijk-over-edelstenen" />
>                     <serverVariables>
>                         <set name="HTTP_X_ORIGINAL_FILE" value="/index.php" />
>                     </serverVariables>
>                     <action type="Rewrite" url="/_bootstrap_wrapper.php?pagina=eerlijk-over-edelstenen" logRewrittenUrl="false" />
>                 </rule>
>
>                 <rule name="winkelmand" enabled="true" stopProcessing="true">
>                     <match url="^winkelmand" />
>                     <serverVariables>
>                         <set name="HTTP_X_ORIGINAL_FILE" value="/index.php" />
>                     </serverVariables>
>                     <action type="Rewrite" url="/_bootstrap_wrapper.php?pagina=winkelwagen" logRewrittenUrl="false" />
>                 </rule>
>
>                 <rule name="winkel" enabled="true" stopProcessing="true">
>                     <match url="^winkel" />
>                     <serverVariables>
>                         <set name="HTTP_X_ORIGINAL_FILE" value="/index.php" />
>                     </serverVariables>
>                     <action type="Rewrite" url="/_bootstrap_wrapper.php?pagina=aanbod" logRewrittenUrl="false" />
>                 </rule>
>
>                 <rule name="verzendkosten" enabled="true" stopProcessing="true">
>                     <match url="^verzendkosten" />
>                     <serverVariables>
>                         <set name="HTTP_X_ORIGINAL_FILE" value="/index.php" />
>                     </serverVariables>
>                     <action type="Rewrite" url="/_bootstrap_wrapper.php?pagina=verzendkosten" logRewrittenUrl="false" />
>                 </rule>
>
>                 <rule name="afrekenen" enabled="true" stopProcessing="true">
>                     <match url="^afrekenen" />
>                     <serverVariables>
>                         <set name="HTTP_X_ORIGINAL_FILE" value="/index.php" />
>                     </serverVariables>
>                     <action type="Rewrite" url="/_bootstrap_wrapper.php?pagina=afrekenen" logRewrittenUrl="false" />
>                 </rule>
>
>                 <rule name="betalen" enabled="true" stopProcessing="true">
>                     <match url="^betalen/(.*)" />
>                     <serverVariables>
>                         <set name="HTTP_X_ORIGINAL_FILE" value="/index.php" />
>                     </serverVariables>
>                     <action type="Rewrite" url="/_bootstrap_wrapper.php?pagina=betalen&amp;guid={R:1}" logRewrittenUrl="false" />
>                 </rule>
>
>                 <rule name="contact" enabled="true" stopProcessing="true">
>                     <match url="^contact" />
>                     <serverVariables>
>                         <set name="HTTP_X_ORIGINAL_FILE" value="/index.php" />
>                     </serverVariables>
>                     <action type="Rewrite" url="/_bootstrap_wrapper.php?pagina=contact" logRewrittenUrl="false" />
>                 </rule>
>
>                 <rule name="bestelling_bedankt" enabled="true" stopProcessing="true">
>                     <match url="^bestelling_bedankt" />
>                     <serverVariables>
>                         <set name="HTTP_X_ORIGINAL_FILE" value="/index.php" />
>                     </serverVariables>
>                     <action type="Rewrite" url="/_bootstrap_wrapper.php?pagina=bestelling_bedankt" logRewrittenUrl="false" />
>                 </rule>
>
>                 <rule name="zoeken" enabled="true" stopProcessing="true">
>                     <match url="^zoeken" />
>                     <serverVariables>
>                         <set name="HTTP_X_ORIGINAL_FILE" value="/index.php" />
>                     </serverVariables>
>                     <action type="Rewrite" url="/_bootstrap_wrapper.php?pagina=zoeken" logRewrittenUrl="false" />
>                 </rule>
>
>                 <rule name="voorwaarden" enabled="true" stopProcessing="true">
>                     <match url="^voorwaarden" />
>                     <action type="Rewrite" url="./algemenevoorwaarden.pdf" logRewrittenUrl="false" />
>                 </rule>
>
>                 <!-- Bootstrap PHP infrastructure -->
>                 <rule name="Bootstrap Directory Default" stopProcessing="true">
>                     <match url="^(.*?)/?$" ignoreCase="true" />
>                     <conditions>
>                         <add input="{REQUEST_FILENAME}" matchType="IsDirectory" />
>                         <add input="{REQUEST_FILENAME}/default.php" matchType="IsFile" />
>                     </conditions>
>                     <serverVariables>
>                         <set name="HTTP_X_ORIGINAL_FILE" value="/{R:1}/default.php" />
>                     </serverVariables>
>                     <action type="Rewrite" url="/_bootstrap_wrapper.php" appendQueryString="true" />
>                 </rule>
>
>                 <rule name="Bootstrap Directory Index" stopProcessing="true">
>                     <match url="^(.*?)/?$" ignoreCase="true" />
>                     <conditions>
>                         <add input="{REQUEST_FILENAME}" matchType="IsDirectory" />
>                         <add input="{REQUEST_FILENAME}/index.php" matchType="IsFile" />
>                     </conditions>
>                     <serverVariables>
>                         <set name="HTTP_X_ORIGINAL_FILE" value="/{R:1}/index.php" />
>                     </serverVariables>
>                     <action type="Rewrite" url="/_bootstrap_wrapper.php" appendQueryString="true" />
>                 </rule>
>
>                 <rule name="CMA Tools Friendly URL" stopProcessing="true">
>                     <match url="^cma/tools/([a-z0-9_-]+)$" ignoreCase="true" />
>                     <conditions>
>                         <add input="{REQUEST_FILENAME}" matchType="IsFile" negate="true" />
>                         <add input="{REQUEST_FILENAME}" matchType="IsDirectory" negate="true" />
>                     </conditions>
>                     <serverVariables>
>                         <set name="HTTP_X_ORIGINAL_FILE" value="/cma/tools.php" />
>                         <set name="HTTP_X_TOOL_NAME" value="{R:1}" />
>                     </serverVariables>
>                     <action type="Rewrite" url="/_bootstrap_wrapper.php" appendQueryString="true" />
>                 </rule>
>
>                 <rule name="Bootstrap PHP Files" stopProcessing="true">
>                     <match url="^(.*)\.php$" ignoreCase="true" />
>                     <conditions>
>                         <add input="{REQUEST_FILENAME}" matchType="IsFile" />
>                         <add input="{REQUEST_URI}" pattern="_bootstrap\.php$" negate="true" />
>                         <add input="{REQUEST_URI}" pattern="_bootstrap_wrapper\.php$" negate="true" />
>                     </conditions>
>                     <serverVariables>
>                         <set name="HTTP_X_ORIGINAL_FILE" value="{URL}" />
>                     </serverVariables>
>                     <action type="Rewrite" url="/_bootstrap_wrapper.php" appendQueryString="true" />
>                 </rule>
>
>                 <rule name="ASP redirect" stopProcessing="true">
>                     <match url="^(.*)\.asp$" ignoreCase="true" />
>                     <conditions>
>                         <add input="{REQUEST_FILENAME}" matchType="IsFile" negate="true" />
>                     </conditions>
>                     <serverVariables>
>                         <set name="HTTP_X_ORIGINAL_FILE" value="{R:1}.php" />
>                     </serverVariables>
>                     <action type="Rewrite" url="/_bootstrap_wrapper.php" appendQueryString="true" />
>                 </rule>
>
>             </rules>
>         </rewrite>
>
>         <!-- Default document settings -->
>         <defaultDocument>
>             <files>
>                 <clear />
>                 <add value="default.php" />
>                 <add value="index.php" />
>             </files>
>         </defaultDocument>
>
>         <!-- Security and custom headers -->
>         <httpProtocol>
>             <customHeaders>
>                 <clear />
>                 <remove name="Server" />
>                 <remove name="X-Powered-By" />
>                 <add name="Vary" value="Accept-Encoding" />
>                 <add name="Strict-Transport-Security" value="max-age=31536000" />
>                 <add name="X-Content-Type-Options" value="nosniff" />
>                 <add name="X-Frame-Options" value="SAMEORIGIN" />
>             </customHeaders>
>         </httpProtocol>
>
>         <!-- Hide sensitive files -->
>         <security>
>             <requestFiltering>
>                 <hiddenSegments>
>                     <add segment=".env" />
>                     <add segment=".app_started" />
>                     <add segment="composer.json" />
>                     <add segment="composer.lock" />
>                 </hiddenSegments>
>             </requestFiltering>
>         </security>
>
>         <!-- gzip compression -->
>         <httpCompression directory="%SystemDrive%\inetpub\temp\IIS Temporary Compressed Files">
>             <scheme name="gzip" dll="%Windir%\system32\inetsrv\gzip.dll" />
>             <dynamicTypes>
>                 <add mimeType="text/*" enabled="true" />
>                 <add mimeType="message/*" enabled="true" />
>                 <add mimeType="application/javascript" enabled="true" />
>                 <add mimeType="application/json" enabled="true" />
>                 <add mimeType="*/*" enabled="false" />
>             </dynamicTypes>
>             <staticTypes>
>                 <add mimeType="text/*" enabled="true" />
>                 <add mimeType="message/*" enabled="true" />
>                 <add mimeType="application/javascript" enabled="true" />
>                 <add mimeType="application/json" enabled="true" />
>                 <add mimeType="*/*" enabled="false" />
>             </staticTypes>
>         </httpCompression>
>
>         <!-- Let PHP error output pass through -->
>         <httpErrors existingResponse="PassThrough" />
>
>         <!-- PHP 8.5 FastCGI handler (overrides any inherited PHP_via_FastCGI mappings) -->
>         <handlers accessPolicy="Read, Execute, Script">
>             <remove name="PHP_via_FastCGI1" />
>             <remove name="PHP_via_FastCGI" />
>             <remove name="PHP_via_FastCGI3" />
>             <remove name="PHP_via_FastCGI2" />
>             <remove name="PHP53_via_FastCGI" />
>             <add name="php 8.5" path="*.php" verb="*" modules="FastCgiModule" scriptProcessor="C:\Program Files\PHP\v8.5.6\php-cgi.exe" resourceType="File" />
>         </handlers>
>
>     </system.webServer>
>
>     <!-- Static content caching -->
>
>     <location path="library">
>         <system.webServer>
>             <staticContent>
>                 <clientCache cacheControlMode="UseMaxAge" cacheControlMaxAge="365.00:00:00" />
>             </staticContent>
>         </system.webServer>
>     </location>
>
>     <location path="cma/images">
>         <system.webServer>
>             <staticContent>
>                 <clientCache cacheControlMode="UseMaxAge" cacheControlMaxAge="365.00:00:00" />
>             </staticContent>
>         </system.webServer>
>     </location>
>
>     <location path="cma/include">
>         <system.webServer>
>             <staticContent>
>                 <clientCache cacheControlMode="UseMaxAge" cacheControlMaxAge="28.00:00:00" />
>             </staticContent>
>         </system.webServer>
>     </location>
>
>     <location path="karaat.bundle.min.css">
>         <system.webServer>
>             <staticContent>
>                 <clientCache cacheControlMode="UseMaxAge" cacheControlMaxAge="365.00:00:00" />
>             </staticContent>
>         </system.webServer>
>     </location>
>
>     <location path="assets/js">
>         <system.webServer>
>             <staticContent>
>                 <clientCache cacheControlMode="UseMaxAge" cacheControlMaxAge="365.00:00:00" />
>             </staticContent>
>         </system.webServer>
>     </location>
>
>     <location path="assets/css">
>         <system.webServer>
>             <staticContent>
>                 <clientCache cacheControlMode="UseMaxAge" cacheControlMaxAge="365.00:00:00" />
>             </staticContent>
>         </system.webServer>
>     </location>
>
>     <location path="assets/logo">
>         <system.webServer>
>             <staticContent>
>                 <clientCache cacheControlMode="UseMaxAge" cacheControlMaxAge="365.00:00:00" />
>             </staticContent>
>         </system.webServer>
>     </location>
>
>     <location path="assets/components/magicscroll">
>         <system.webServer>
>             <staticContent>
>                 <clientCache cacheControlMode="UseMaxAge" cacheControlMaxAge="365.00:00:00" />
>             </staticContent>
>         </system.webServer>
>     </location>
>
>     <location path="assets/components/tooltipster">
>         <system.webServer>
>             <staticContent>
>                 <clientCache cacheControlMode="UseMaxAge" cacheControlMaxAge="365.00:00:00" />
>             </staticContent>
>         </system.webServer>
>     </location>
>
>     <location path="module/mmenu">
>         <system.webServer>
>             <staticContent>
>                 <clientCache cacheControlMode="UseMaxAge" cacheControlMaxAge="365.00:00:00" />
>             </staticContent>
>         </system.webServer>
>     </location>
>
>     <location path="images/producten">
>         <system.webServer>
>             <staticContent>
>                 <clientCache cacheControlMode="UseMaxAge" cacheControlMaxAge="365.00:00:00" />
>             </staticContent>
>         </system.webServer>
>     </location>
>
>     <location path="assets/images">
>         <system.webServer>
>             <staticContent>
>                 <clientCache cacheControlMode="UseMaxAge" cacheControlMaxAge="365.00:00:00" />
>             </staticContent>
>         </system.webServer>
>     </location>
>
>     <location path="assets/fonts">
>         <system.webServer>
>             <staticContent>
>                 <clientCache cacheControlMode="UseMaxAge" cacheControlMaxAge="365.00:00:00" />
>             </staticContent>
>             <httpProtocol>
>                 <customHeaders>
>                     <add name="Access-Control-Allow-Origin" value="*" />
>                 </customHeaders>
>             </httpProtocol>
>         </system.webServer>
>     </location>
>
>     <location path="favicon.ico">
>         <system.webServer>
>             <staticContent>
>                 <clientCache cacheControlMode="UseMaxAge" cacheControlMaxAge="365.00:00:00" />
>             </staticContent>
>         </system.webServer>
>     </location>
>
> </configuration>

> on hover the preview completely dissapears

> karaat's cma_platform version is at 1.29.2

> stop: ik heb composer update gedraaid en alles draait weer, nu op 1.29.7

> earlier i asked you to write code to fix soorten, the image and the description

> switching logfiles does not work, is that correctly implemented? And i see an amaerican date format if i select 404 errors, the combo shows Javascript errors and if i select another date it reverts back to javascript errors-date gone!

> site down again!!! did you do anything?

> waarom moet die probleemstenen een rewrite hebben? kan dat noet zonder?

> niets meer deployen of pushen tot ik het zeg

> sote seaait weer na een composer update dus die fixt het, niet de veroorzaker

> commit them

## 2026-07-13

> over de preview van een nbeeld/video: ik denk dat de tooltip het probleem is, die neemt de :after over en vervangt het beeld. Wil je daarnaar kijken? En als javascript een 404 tegenkomt moet er geen gewone preview komen maar eentje met een rood kruis er doorheen, de gebruiker moet wel weten dat er iets aan de hand is. Zet de tooltip dan op 'bestand [x] niet gevonden'

> .combo-clear::before { add     margin-top: 2px;
> }

> word form.min.css wel ergens aangeroepen? Anders mag hij weg. Het idee is dat altijd de minify.php word gebruikt.

> puysh and commit please, i will watch karaat

> karaat blijft draaien. Vreems is dat in de file browser de video de volgende fout geeft:  error loading '/videos/MVI_5975.MP4?versie=1783872290' , ten eerste: die melding moet in het nederlands en informatiever. Ten tweede het versienummer moet eraf, hetzelfde bij images en bestanden, het hele versienummer moet eraf. ten derde: het forms.php zelf kan 'm prima tonen, weliswaar in een lelijke nieuwe tab (graag hier een modal popup) , maar hij toont 'm wel.

> karaat is weer down??? Heb jij iets gereleased?

> karaat doet het na een composer update..

> http://www.karaatedelstenen.nl/index.php?pagina=probleemstenen zegt Deze pagina is alleen voor ingelogde beheerders. Inloggen. Maa ik open m vanuit de CMA? En ben dus al ingelogd, bij https://www.karaatedelstenen.nl/cma/main.php?page=%2Ftools%2Fquick_add_stone.php gaat dat wel goed, vergelijk die twee alsjeblieft.

> maak probleemstenen ook een back-end formulier inderdaad 
>
> .toolbar-left {margin-left: -6px;}

> .combo-tag { background-color: #ffffff;border:1px solid var(--border-color, #ccc); } .combo-tag:hover { background-color: var(--bg-hover, #d0e8f8); border:1px solid var(--color-accent-hover);}

> when cropping, the keyoard is not supported, handy for subtle movements, can you make that happen?

> the witruimte automatisch bijwerken does not work, probably because the threshold is not met. Can we iterate the thereshold until it is met? The background is sometimes quite lively.

> witruimte autocrop doet niets en meldt ook niets.

> Hij doet nog steeds nix en meldt ook niets, url van het beeld is https://www.karaatedelstenen.nl/images/producten/.responsive/IMG_4904.webp?v=1781682601

> versie die ik gebruik is de .19

> on front-end , the lnr lnr-frame-expand and lnr lnr-frame-contract are not visible, the ugly rectangle is shown to indicate an inknown character

> the quick edit form, show a gauge withe the weight confidence level, 1 and 2 red, 3 orange, 4 and 5 green. Make that a site specific web component

> continue

> in het formulier is er een ...lnr-cross-circle::before om een video of plaatje weg te halen, maak daar een prullenbakje van. 
>
> an: .image-404::before {
>     color: red;
> }

> Preview of a video is not inside a dialog, the image is. Clicking the preview image should close the window.

> if an image or video cannot be found, block the preview option

## 2026-07-14

> de video preview image was eigenlijk iets waar ik niet om heb gevraagd, kunnen we die automatisch laten vullen?

> okay, we proberen A, maar haal dan ook de still control uit tblforms (of de-activeer m voor nu)

> sorry, fout, het is een json, tblFOrms is oud.

> cma-groupbox -> expanding and collapsing is now instant, create a .3s animation through css alone (ease-in) to visualise the folding better, including rotating the icon.

> maak de sg-berekening leeg voor alle stenen anders dan enkele stenen, partijen en ruwe stenen vermelden de formaten per steen terwijl het er meerdere zijn; berekening onbetrouwbaar.

> de sg berekening zegt: 
> Simulant-detectie: UIT (tblSimulanten ontbreekt of is leeg — draai migratie 0.7.0)
> maar die migratie zie ik nie?!

> hij doet het. De svg is een dappere poging, maar onduidelijk. denk dat we geen tekst erin moeten zetten maar een tooltip met uitleg moeten doen. Het beeld moet alleen grafische elementen bevatten.

> graag een data-tooltip met html gebruiken en de kleuren ook echt uitleggen , de balk met de verwachte waardes graag grijs, daarin is een donkerblauw element opgenomen, wat is dat? Graag alles uitleggen

> the tooltip is blak°ck and has grey body text, unreadable and the svg now misses the labels, please show the start and end value of the diagram shown below it )and remove that from the tooltip' and place the measured value above the measured value.

> in formulier_dispensatie there are 2 volgende buttons, for now remove the volgende 2 button

> can you revert that change?

> The table view has coninious scrolling implemented, but it stops at records 1-1400 van 1423 (laden...) 
>
> the console has this error: minify.php:31650 Uncaught (in promise) Error: [Infinite Scroll] Pagination stopped at 200/1423 — 1223 record(s) not loaded (last id 403, form deelnemers).
>     at CmaInfiniteScroll.load (minify.php:31650:27)
>     at async prefetchBatch (minify.php:37811:17)
>
> ﻿
>  which is also incorrect

> The table view has coninious scrolling implemented, but it stops at records 1-1400 van 1423 (laden...) 
>
> the console has this error: minify.php:31650 Uncaught (in promise) Error: [Infinite Scroll] Pagination stopped at 200/1423 — 1223 record(s) not loaded (last id 403, form deelnemers).
>     at CmaInfiniteScroll.load (minify.php:31650:27)
>     at async prefetchBatch (minify.php:37811:17)
>
> ﻿
>  which is also incorrect
>
> /cma/form.php?form=deelnemers_laatste_100_berichten&ID=30697:1 Refused to apply style from 'https://test-mijn.rino.nl/assets/css/rinoportal.css' because its MIME type ('') is not a supported stylesheet MIME type, and strict MIME checking is enabled.

> The table view has coninious scrolling implemented, but it stops at records 1-1400 van 1423 (laden...) 
>
> the console has this error: minify.php:31650 Uncaught (in promise) Error: [Infinite Scroll] Pagination stopped at 200/1423 — 1223 record(s) not loaded (last id 403, form deelnemers).
>     at CmaInfiniteScroll.load (minify.php:31650:27)
>     at async prefetchBatch (minify.php:37811:17)
>
> ﻿
>  which is also incorrect
>
> /cma/form.php?form=deelnemers_laatste_100_berichten&ID=30697:1 Refused to apply style from 'https://test-mijn.rino.nl/assets/css/rinoportal.css' because its MIME type ('') is not a supported stylesheet MIME type, and strict MIME checking is enabled.
>
> Single sign on, can we stay in the login dialog and not create a new one? So empty the user name and password and place 
>
> Single Sign-On geactiveerd. Bezig met aanmelden.

> The table view has coninious scrolling implemented, but it stops at records 1-1400 van 1423 (laden...) 
>
> the console has this error: minify.php:31650 Uncaught (in promise) Error: [Infinite Scroll] Pagination stopped at 200/1423 — 1223 record(s) not loaded (last id 403, form deelnemers).
>     at CmaInfiniteScroll.load (minify.php:31650:27)
>     at async prefetchBatch (minify.php:37811:17)
>
> ﻿
>  which is also incorrect
>
> /cma/form.php?form=deelnemers_laatste_100_berichten&ID=30697:1 Refused to apply style from 'https://test-mijn.rino.nl/assets/css/rinoportal.css' because its MIME type ('') is not a supported stylesheet MIME type, and strict MIME checking is enabled.
>
> Single sign on, can we stay in the login dialog and not create a new one? So empty the user name and password and place 
>
> Single Sign-On geactiveerd. Bezig met aanmelden.

> the sso message should replace the username and password form

> opslaan record: 
> Call to undefined method App\Library\SQL::normalizeDecimal() bij opslaan record: versie v1.29.27

> https://test-mijn.rino.nl/cma/main.php?page=tools%3Ftool%3Ddocumentation if i select another document-item, it should only replace the content-area

## 2026-07-15

> continue

> als het de php versie betreft laat maar even zitten

> the detail form first shows 'Orders wijzigen', and then 'Order wijzigen', so the plural seems to be the default, for the detail screen singular is a better default

> .qa-layout and .qa-wrap { height:100% }

> Root cause: there are two places that emit the form config. /: Code smell! Centralise that

> i have 2 clients yhat are called rino, the rino portal was deliberate, please restore
>
> then take a good look at the whole codebase and try to find more duplicates or multiple paths, I suspect there are more of them

> try and dind more instaances of peomises that need await, we had that bug before, do a full scan of the cma platforms: plan mode

> <task-notification>
> <task-id>a687de6b9bb32efc8</task-id>
> <tool-use-id>toolu_012nZz8YPtEEedBG9ttosAKg</tool-use-id>
> <output-file>/tmp/claude-1000/-mnt-c-repos-cma-platform/4c10bf01-6ac5-42cd-b645-c012d5313179/tasks/a687de6b9bb32efc8.output</output-file>
> <status>completed</status>
> <summary>Agent "Scan utils/editor/tables JS for missing await" finished</summary>
> <note>A task-notification fires each time this agent stops with no live background children of its own. The user can send it another message and resume it, so the same task-id may notify more than once.</note>
> <result>Analysis complete. I read all seven files in full and grepped every async construct (`async`/`await`/`.then(`/`fetch(`/`Promise`/`sendBeacon`). Note: `request-tracker.js` and `url-manager.js` live in `cma/assets/js/`, not `library/assets/js/` (which contains only `error-handler.js`); I covered them anyway.
>
> ## Findings: no missing-await correctness bugs
>
> Every async/Promise site in scope is either correctly awaited/chained or a legitimate fire-and-forget with nothing depending on completion.
>
> **`/mnt/c/repos/cma_platform/cma/assets/js/table-preferences.js`** — None. The only `async` function is `CmaInfiniteScroll.load()` (line 666), which correctly `await`s `this.loadMore()` (line 692) and re-checks `this.destroyed`/`_retireIfStale()` after the await. Callers (`onScroll` → `this.load()` at line 658) are intentional fire-and-forget, guarded by `isLoading`/`hasMore`/`destroyed` flags — no race.
>
> **`/mnt/c/repos/cma_platform/cma/assets/js/cma-utils.js`** — None. `cmaCopyToClipboard` returns a Promise but is never consumed within this file (callers live elsewhere). No async value is misused as a resolved value.
>
> **`/mnt/c/repos/cma_platform/cma/assets/js/image-editor.js`** — None. All `fetch` chains in `editOp` (line 249) and `reload` (line 283) are fully `.then`-chained with `.catch`. The one place a result is depended upon — `finish()`'s "maximum" resize branch — correctly chains `this.editOp({...}).then(ok =&gt; { if (ok) self.postComplete(); })` (lines 578-579). The plain op methods (`rotate`/`flip`/`filter`/`autocrop`/`applyCrop`) fire `editOp` without awaiting, but each is a self-contained server round-trip that reloads the preview; no JS after the call reads its result, so this is legitimate fire-and-forget.
>
> **`/mnt/c/repos/cma_platform/cma/assets/js/error-handler.js`** — None. `sendError`'s `fetch` (line 426) has a `.catch`; `sendBeacon` is sync. `copy()`'s clipboard IIFE returns a Promise that is `.then`/`.catch`-handled (lines 636-643).
>
> **`/mnt/c/repos/cma_platform/library/assets/js/error-handler.js`** — None. Same structure as the CMA copy; `sendError` fetch `.catch`ed (line 450), `copy()` Promise handled with both `.then` and `.catch` (lines 696-712).
>
> **`/mnt/c/repos/cma_platform/cma/assets/js/request-tracker.js`** — None. Fully synchronous (localStorage + in-memory array); no Promises at all.
>
> **`/mnt/c/repos/cma_platform/cma/assets/js/url-manager.js`** — None. Fully synchronous URL parse/build/history manipulation; no Promises at all.</result>
> <usage><subagent_tokens>107946</subagent_tokens><tool_uses>11</tool_uses><duration_ms>84228</duration_ms></usage>
> </task-notification>

> <task-notification>
> <task-id>aa62efdf84e14925b</task-id>
> <tool-use-id>toolu_018oghXdqHhqSFAW4FXiN3om</tool-use-id>
> <output-file>/tmp/claude-1000/-mnt-c-repos-cma-platform/4c10bf01-6ac5-42cd-b645-c012d5313179/tasks/aa62efdf84e14925b.output</output-file>
> <status>completed</status>
> <summary>Agent "Scan library webcomponents for missing await" finished</summary>
> <note>A task-notification fires each time this agent stops with no live background children of its own. The user can send it another message and resume it, so the same task-id may notify more than once.</note>
> <result>I've completed a full scan of every non-min `.js` file in `/mnt/c/repos/cma_platform/library/webcomponents/`. The async surface is small and concentrated; I traced each async definition to its call sites.
>
> ## Findings: no missing-await correctness bugs
>
> Files with an actual async surface (all checked, all clean):
>
> - **lib-tip.js** — `_loadSkipList`, `isSkipped`, `show`, `tour`, `dismiss`, `reset` are all `async`. Every internal dependency is correctly awaited: `show`/`tour` do `if (await this.isSkipped(id))` (lines 711, 724) before showing; `isSkipped` awaits `_loadSkipList` (line 702). The only unawaited call is `LibTip.dismiss(this._tourId)` at line 629 (`_dismiss` handler) — legitimate fire-and-forget (server-side dismissal persist; nothing downstream reads the result, and it closes the tip synchronously right after). Public API returns Promises but callers use them fire-and-forget by design (boolean not needed).
>
> - **lib-table.js** — `async load()` (line 168). Call sites at 128, 325, 1118 (`this.load(...)`) are unawaited, but this is correct: `load()` guards re-entrancy with `if (this.#state.isLoading) return;` (line 169), manages its own `isLoading`/render/error state internally (`try/catch/finally`), and no caller reads its return value. Infinite-scroll append (1118) is protected by the same guard. No race.
>
> - **lib-dialog.js** — `alert`/`confirm`/`prompt` correctly `return dialog.open().then(...)` (lines 992, 1043, 1187), and the top-level `confirm/alert/prompt` wrappers `return LibDialog.*` (1237, 1247, 1266). Promise chains returned intact; nothing dropped.
>
> - **lib-combo.js** — `_searchAjax` fetch→`.then().then().catch()` chain (1079-1101) is self-contained: sets `_loading` and calls `_renderOptions()` in every branch. Search-as-you-type; correct fire-and-forget with error handling.
>
> - **lib-message.js** — `async _copyToClipboard()` (224) invoked from a click handler as `() =&gt; this._copyToClipboard()` (211); self-contained `await` + `try/catch`. Fine.
>
> - **lib-fileuploader.js** / **lib-log.js** — use callback-based `XMLHttpRequest` (`load`/`error`/`progress` listeners), no Promise/async misuse.
>
> - **responsive-tabs/index.js** — `Promise.all([loadCSS(), loadJS()]).then(...)` and `customElements.whenDefined(...).then(...)` returned/handled correctly.
>
> Files with **no async surface at all** (verified via grep for `async`/`await`/`.then`/`fetch`/`XMLHttpRequest`/`new Promise`): lib-datepicker.js, lib-sheet.js, lib-histogram.js, lib-gauge.js, lib-loader.js, lib-menu.js, lib-toaster.js, lib-field.js, lib-switch.js, lib-search-input.js, lib-radio-group.js, lib-timepicker.js, lib-arrowsteps.js, lib-shared-styles.js, responsive-tabs/responsive-tabs.js.
>
> **Conclusion:** No used-as-value, race/sequencing, promise-as-value, or dropped-result bugs found in any of the categories requested. Every async call either has its result properly awaited/returned, or is a deliberate fire-and-forget with self-contained state and error handling (and, where it matters, a re-entrancy guard). Nothing was edited — analysis only.</result>
> <usage><subagent_tokens>39084</subagent_tokens><tool_uses>15</tool_uses><duration_ms>106165</duration_ms></usage>
> </task-notification>

> <task-notification>
> <task-id>a8dcefba63bbd5f85</task-id>
> <tool-use-id>toolu_01HWk5VKwmBPRmaXjYv6D8pk</tool-use-id>
> <output-file>/tmp/claude-1000/-mnt-c-repos-cma-platform/4c10bf01-6ac5-42cd-b645-c012d5313179/tasks/a8dcefba63bbd5f85.output</output-file>
> <status>completed</status>
> <summary>Agent "Scan cma webcomponents for missing await" finished</summary>
> <note>A task-notification fires each time this agent stops with no live background children of its own. The user can send it another message and resume it, so the same task-id may notify more than once.</note>
> <result>Analysis complete. I audited every non-min, non-UNUSED `.js` file in `/mnt/c/repos/cma_platform/cma/webcomponents/`. Triage found 13 of the 20 files contain no async surface at all (no `async`/`await`/`.then`/`fetch`/`Promise`, and no calls to the async helpers `libConfirm`/`libPrompt`/`libAlert`/`cmaCopyToClipboard`): cma-combo.js, cma-field-config.js, cma-fold.js, cma-group-config.js, cma-groupbox.js, cma-htmledit.js, cma-param-config.js, cma-sort-config.js, cma-sortlist.js, cma-tabs.js, cma-toolbar.js, cma-tree.js, shared-icons.js. Nothing to report in those.
>
> For the 7 files with async surfaces, I traced every async target to its call sites.
>
> Findings: NO high-signal missing-await correctness bugs found.
>
> Every async call is either correctly awaited, correctly chained with `.then/.catch`, or legitimately fire-and-forget. Details of what I checked and cleared:
>
> - cma-launcher.js — `open()` (line 91, async) awaits `_loadCatalog()` at line 103 and even guards `if (!this._open) return` afterward against a concurrent close. `_loadCatalog()` (164) awaits `fetch` + `.json()` inside try/catch. `toggle()` (158) calls `this.open()` unawaited, but nothing follows it — correct fire-and-forget. Clean.
>
> - cma-schema-canvas.js — `loadSchema()` (374, async) called without `await` at lines 111 (connectedCallback) and 121 (attributeChangedCallback). Both calls are the last statement in their branch; no synchronous code after depends on the result, and completion is signalled via a `schema-loaded` CustomEvent (line 409). Re-entrancy is guarded by `this._loading` (line 375). Legitimate fire-and-forget. `dialog.open().then()` (2181) and the `async` delete handler awaiting `libConfirm` (2050-2051) are both correct. Clean.
>
> - cma-conditions-panel.js — `_fetchWherePreview` (979) runs a `fetch(...).then().then().catch()` chain inside a debounce `setTimeout`; the result only writes `previewElement.innerHTML` inside the `.then`, with a `.catch` fallback (985-1008). Self-contained. Clean.
>
> - cma-blockeditor.js — `libPrompt(...).then(url =&gt; ...)` (933) consumes the resolved value inside the callback with `index` captured by closure; the parallel wizard path uses an `onComplete` callback (924). Clean.
>
> - cma-query-preview.js — `_copyToClipboard()` (async, 1023, awaits `cmaCopyToClipboard`) invoked from a click handler at 690 unawaited; pure UI action, nothing depends on completion. Clean.
>
> - cma-sql-editor.js — same pattern: `_copyToClipboard()` (504) invoked from a click handler at 431 unawaited; fire-and-forget UI. Clean.
>
> - cma-base-component.js — exposes a `_readyPromise` via `whenReady()` (69); it's a ready-state primitive with no unawaited-consumer bug in this directory. Clean.
>
> No edits were made (read-only analysis, as requested).</result>
> <usage><subagent_tokens>35817</subagent_tokens><tool_uses>12</tool_uses><duration_ms>117560</duration_ms></usage>
> </task-notification>

> <task-notification>
> <task-id>a500dc23a1b0dcb30</task-id>
> <tool-use-id>toolu_01CVHAB7htgi4gXjPHEXnYcF</tool-use-id>
> <output-file>/tmp/claude-1000/-mnt-c-repos-cma-platform/4c10bf01-6ac5-42cd-b645-c012d5313179/tasks/a500dc23a1b0dcb30.output</output-file>
> <status>completed</status>
> <summary>Agent "Scan core CMA JS for missing await" finished</summary>
> <note>A task-notification fires each time this agent stops with no live background children of its own. The user can send it another message and resume it, so the same task-id may notify more than once.</note>
> <result>Analysis complete. I traced every async function in the five files to its call sites and checked each unawaited call against the four bug categories.
>
> ## Findings
>
> **No high-signal missing-await correctness bugs found.** Every dependent async call is either properly `await`ed or is legitimate fire-and-forget. Details per file:
>
> ### cma/assets/js/inline-edit.js — clean
> - `RequestBatcher.flush()` at line 170 (inside `scheduleFlush`'s `setTimeout`) is fire-and-forget by design; the flush has its own internal `try/await/catch` (line 187-205). No caller depends on it.
> - `saveInlineEdit` line 1406 `self.updateRowAfterSave(row, rowData)` — unawaited, but safe: `updateRowAfterSave` reads `this.editingRowId` synchronously (line 1434, before its first `await`) and owns its own cleanup via `finishInlineEditing()` (line 1550). Nothing after line 1406 in `saveInlineEdit` depends on it.
> - `deleteRow` (691), `startInlineEditing` (669), `saveInlineEdit` (854/872/890) call sites are UI event handlers; each async target self-contains its DOM update. No post-call code races.
> - `startInlineEditing` line 1056 `this.loadRecordForInlineEdit(...)` is the last statement — fire-and-forget is correct.
> - `fetchJson`/`fetchWithRetry` are always `await`ed at every call site.
>
> ### cma/assets/js/cma.js — clean
> - `resend` (1989) awaits `libConfirm`, then fires a jQuery.post callback — correct.
> - `fetch(config.contentsCss)` (516) is a deliberate diagnostic-only probe with `.then/.catch`; result intentionally unused.
>
> ### cma/assets/js/main.js — clean
> - `loadPage` (693) correctly `await`s `cmaCheckUnsavedChanges()` (699) and gates navigation on the result.
> - All `loadPage(...)` call sites (650, 807, 1392, 1400, 1501, 1552) are navigation triggers — no caller consumes the returned Promise; no post-call race.
> - `loadFormController().then(runScripts).catch(...)` (1021) is properly chained.
> - `fetch` chains in `loadPage`/`loadPagePost`/change-password all use `.then` correctly.
>
> ### cma/assets/js/blockedit.js — clean
> - No genuine async call-site issues. `string_JSON_fetch` (1965) is synchronous despite the name (the "fetch" comments at 1193/1203/1332/1615 describe CKEditor's internal async build, not unawaited JS calls here).
>
> ### cma/assets/js/cma-tours.js — clean
> - `showReportDesignerTips` (437) is invoked fire-and-forget from a `setTimeout` (433); it only branches to synchronous tour-render helpers — no awaited value is dropped.
> - `LibTip.reset(tourId).then(() =&gt; initTours())` (1345) is properly chained.
>
> ### One observation (not a live bug)
> `startEditing` (inline-edit.js:962) calls `this.loadRecordData(rowId, row)` at line 984 WITHOUT await, then immediately runs `positionEditControls(row)` (985) and shows controls (986). `loadRecordData` fetches and only then swaps the row into edit controls (`renderEditableRow`), so positioning would run against the pre-edit row height — a real sequencing defect in principle. However, `startEditing` has zero callers anywhere in the repo (confirmed by grep across cma/ and library/); it is dead code superseded by `startInlineEditing`. Confidence that it is a live bug: low (dead code). Worth deleting, but not a correctness bug in any executed path.</result>
> <usage><subagent_tokens>47355</subagent_tokens><tool_uses>16</tool_uses><duration_ms>144921</duration_ms></usage>
> </task-notification>

> <task-notification>
> <task-id>affdacb5a9901d918</task-id>
> <tool-use-id>toolu_01XV9Qkg9DHLuPJo47KRw9Yt</tool-use-id>
> <output-file>/tmp/claude-1000/-mnt-c-repos-cma-platform/11968c9b-70ed-460f-9055-e6fe9af77946/tasks/affdacb5a9901d918.output</output-file>
> <status>completed</status>
> <summary>Agent "Audit form-controller JS duplication" finished</summary>
> <note>A task-notification fires each time this agent stops with no live background children of its own. The user can send it another message and resume it, so the same task-id may notify more than once.</note>
> <result>I have thoroughly mapped the duplication. Here is my structured audit, ranked by drift risk (most severe first).
>
> ---
>
> ## Duplicated Logic &amp; Parallel Code Paths — form-controller.js + inline-edit.js
>
> ### FINDING 1 — [CRITICAL] Title/action-suffix building duplicated in 8 sites with 5 different fallback chains
> This is the exact bug class you just fixed, still present across the codebase.
>
> **Action-suffix computation (`toevoegen/wijzigen/bekijken/kopiëren`) — duplicated 3x, each with a DIFFERENT permission source:**
> - inline-edit.js:2177-2186 — uses `!this.options.canEdit`, and *has* a `copy → ' kopiëren'` branch
> - form-controller.js:4645-4653 (`openFormPopup`) — uses `this.config.accessLevel &lt; 2`, *no* copy branch
> - form-controller.js:11694 (`openSubformRecord`) — uses `this.config.accessLevel &gt;= 2`, only `wijzigen/bekijken`
>
> **Name-fallback chains — 5 inconsistent variants (this is where drift bites):**
> - form-controller.js:4642 → `formNameSingular || formName || 'Record'`
> - form-controller.js:9229 (`updateSidepanelTitle`) → `formNameSingular || formName || 'Record'`
> - inline-edit.js:2176 → `formNameSingular || formName || jsonFormToUse || 'Record'` (extra `jsonFormToUse` link)
> - form-controller.js:11479 (`addSubformRecord`) → `titleSingular || title || subformId`
> - form-controller.js:11691 (`openSubformRecord`) → `subformNameSingular || subformName || subformId`
> - inline-edit.js:439 &amp; 447 (context menu "Voeg X toe"/"Bekijk X") → `formNameSingular || 'record'` (lowercased, no `formName` fallback)
> - form-controller.js:11441 (subform add tooltip) → `titleSingular || title || 'record'`
>
> **Bare literal concatenations:**
> - form-controller.js:11480 → `formName + ' toevoegen'`
> - form-controller.js:2056-2058 → status text `'Wijzigen'/'Bekijken'` computed separately from the suffix, yet another parallel permission read.
>
> **Signs of drift already visible:** inline-edit is the only path that handles `copy → kopiëren`; `openSubformRecord` is the only edit path that can never say `toevoegen`; two paths gate on `canEdit`, two gate on `accessLevel`, and they can disagree for subforms. Fallback chains silently differ (`'Record'` vs `'record'` vs `subformId`).
> **Canonical home:** one `buildFormTitle({name, action, canEdit, isNew, isCopy})` helper (plus a single `resolveFormName(config)`) in cma-utils.js, called by every site.
>
> ---
>
> ### FINDING 2 — [CRITICAL] Two near-identical subform loaders (~85 lines each) with behavioral drift
> - form-controller.js:11148 `loadSubformDataAndCount(index, subformId, parentId)`
> - form-controller.js:11283 `loadSubformData(index, pane)`
>
> Both do the same fetch (`action=subform…&amp;SubformIndex`), same `checkRequireLogin`, same `renderSubformList` + `setSubformCount` + `renderSubformToolbar`, same requestTracker start/end, same try/catch. **Divergences that indicate accidental drift:**
> - Parent id source: `parentId` arg (11164) vs `cmaGetRecordId()` (11292)
> - Network-error UI: `&lt;lib-message type="error"&gt;` (11223) vs `&lt;div class="list-loading"&gt;Netwerkfout&lt;/div&gt;` (11345) — inconsistent styling
> - Count fallback: `data.count || data.total || (data.items?.length)` (11200) vs `data.count || data.total || 0` (11322)
> - `loadSubformData` has a `fixable/missingParentField` developer dialog branch (11333) the other lacks
> - `loadSubformDataAndCount` sets `pane.classList` loading/loaded + a `finally` and sets count `'!'` on error; `loadSubformData` does neither on catch.
>
> **Canonical home:** collapse into one `loadSubform(index, {parentId, updateCount})`; the count/pane-state is a boolean flag, not a second function.
>
> ---
>
> ### FINDING 3 — [HIGH] `#recordCount` written by two owners with duplicated, drifting format/visibility rules
> - form-controller.js:6358 `updateRecordCount(currentCount, totalCount)`
> - table-preferences.js:1116 `CmaInfiniteScroll.updateRecordCountDisplay()`
>
> Both write `#recordCount` with the identical template `records 1-${end} van ${total}`, identical "hide when `end &gt;= total`", identical scrollable-overflow gate, identical `${n} records` fallback. **Drift:**
> - table-preferences adds ` (laden...)` suffix when `hasMore`; form-controller never does.
> - form-controller adds two guards the scroller lacks: hide in non-table mode (6363) and hide when `searchFilters` active (6369).
> - Different "loaded" source: `currentCount` arg vs `this.currentCount` (with the long comment at 1136 explaining DOM-count must not be used — that reasoning is absent from the form-controller copy).
>
> Two independent writers of one shared DOM node is inherently race-prone (the table-preferences comment at 1117 already documents a stale-overwrite bug).
> **Canonical home:** a single `renderRecordCount(el, {loaded, total, hasMore, visibleContext})`; make form-controller delegate to the scroller's method rather than reimplement the string.
>
> ---
>
> ### FINDING 4 — [HIGH] `openFormPopup` implemented twice + popup-open + URL-update logic duplicated
> - inline-edit.js:2079 `openFormPopup` vs form-controller.js:4639 `openFormPopup` → 4686 `openPopup`
>
> The **popup-dispatch block** is copy-pasted:
> - inline-edit.js:2189-2203 (`prefAvailable`/`pref`/`useSidepanel` → `lib_OpenSidePanel` / `lib_OpenWindowCentered` / `window.open`)
> - form-controller.js:4751-4822 (same three-way branch; form-controller additionally wraps title in `toFirstCaps`, does dedupe (4700), onClose interval polling (4803), cascade offset).
>
> The **CMA.url.update block** is also duplicated:
> - inline-edit.js:2133-2171 (main/level-1/level-2/level-3 depth branches: `form/subform/subsubform`)
> - form-controller.js:4761-4792 and again 4824-4845 (main-vs-subform branches).
> Both recompute `isInSidepanel`/`currentDepth` and the `effectiveRecordId = (recordId != null &amp;&amp; != '')` guard independently. inline-edit supports 3 levels; openPopup only 2 → they already disagree on depth handling.
> Both recompute `width/height = innerWidth*0.85` independently (2173 vs 4740).
>
> **Canonical home:** inline-edit's `openFormPopup` should build the URL/title and delegate to a single shared `openPopup(options)` (already the "unified" function per its own docstring at 4672); the URL-sync should be one `syncPopupUrl(state)` helper.
>
> ---
>
> ### FINDING 5 — [MEDIUM] `lib_OpenWindowCentered` "extra action / post result" popups duplicated
> Repeated `if (typeof lib_OpenWindowCentered === 'function')` blank-window + post-result pattern:
> - inline-edit.js:2340, 2391-2392, 2452-2453, 2543-2544
> - form-controller.js:4073, 4122, 4424-4426, 10766-10767, 12334-12337
>
> The `about:blank` + form-post-into-popup pattern (inline-edit.js:2391 `afterpost_result` / 2543 `post_result` vs form-controller.js:12334 `post_result`) is essentially the same afterpost-result window logic in both files.
> **Canonical home:** a shared `openResultWindow(title, {width,height})` / `openExtraActionWindow(...)` helper.
>
> ---
>
> ### FINDING 6 — [MEDIUM] Row-column-derivation and "no data" rendering duplicated
> - form-controller.js:7848 (`renderList`) and 11530 (`renderSubformList`) both derive display columns via `Object.keys(item).filter(k =&gt; !k.startsWith('_') &amp;&amp; k.toLowerCase() !== 'id')`.
> - "Empty list" strings diverge: `renderList` → `'Geen records gevonden'` (7837); `renderSubformList` → `'Geen gegevens' + …'Toevoegen'…` (11534); subform loaders inject `'Netwerkfout…'` two different ways (Finding 2). No single empty/error-state renderer.
> **Canonical home:** `getDisplayColumns(item)` util + a shared `renderEmptyState(el, {canAdd})`.
>
> ---
>
> ### FINDING 7 — [LOW] Field/row "hidden" test computed in several places with different rules
> - form-controller.js:10406 `isFieldHidden` (validation): checks `display/visibility`, collapsed-groupbox exception, LIB-COMBO shadow-DOM, parent-row.
> - form-controller.js:13107 (subform-height calc): "hidden" = `style.display==='none' || .groupbox-hidden || .groupbox-end`.
> - form-controller.js:9125 (save/serialize) "skip invisible fields" and inline-edit.js:6218/7399 (`r.style.display !== 'none'` visible-row counts).
> These encode "is this row/field visible?" with independent, non-matching predicates (computed-style vs inline-style vs class list).
> **Canonical home:** `isRowVisible(el)` / `isFieldAccessible(field)` predicates shared by all counters/validators. Low drift risk today (different intents) but easy to desync.
>
> ---
>
> ### Not a concern
> - `deleteRow` (inline-edit.js:2209) and form-controller delete (10514) share the jsonForm-from-table-dataset guard pattern (also repeated in `openFormPopup` 2080-2094) — minor `jsonFormToUse` resolution duplication worth a `resolveJsonForm(el)` helper, but low risk.
>
> **Recommended fix order:** 1 (title builder) → 2 (subform loaders) → 3 (record count) → 4 (popup/url unify), as these carry live, already-diverged behavior.</result>
> <usage><subagent_tokens>57088</subagent_tokens><tool_uses>25</tool_uses><duration_ms>203392</duration_ms></usage>
> </task-notification>

> <task-notification>
> <task-id>a0fd60b70edc05665</task-id>
> <tool-use-id>toolu_01A2WqTSaDisyTTfDLeivaQr</tool-use-id>
> <output-file>/tmp/claude-1000/-mnt-c-repos-cma-platform/11968c9b-70ed-460f-9055-e6fe9af77946/tasks/a0fd60b70edc05665.output</output-file>
> <status>completed</status>
> <summary>Agent "Audit API endpoint duplication" finished</summary>
> <note>A task-notification fires each time this agent stops with no live background children of its own. The user can send it another message and resume it, so the same task-id may notify more than once.</note>
> <result>I have enough to produce the audit. Here are the findings, ranked by drift risk (most severe first).
>
> ---
>
> # Endpoint-layer duplication audit — `/mnt/c/repos/cma_platform/cma`
>
> ## 1. [CRITICAL] Auth/permission gate is implemented 4 different ways for the same form data — and is entirely ABSENT on the JSON API endpoints
> The same underlying record/list data is reachable through four entry points, each with a different (or missing) permission check:
>
> - `cma/form.php:108-131` — enforces login cookie + `MenuService::getFormAccessLevel($formName)`, returns 403 on `ACCESS_NONE`. This is the *only* path that actually gates form access.
> - `cma/form_api.php:439-1530` — **no top-level auth at all.** The switch starts straight after form-name validation (`:401-434`). Only `debug_access` checks `isDeveloper` (`:443`) and `logJsError` peeks at `isLoggedIn` (`:1279`). `save`/`delete`/`record`/`tableData`/`subform` run with zero permission check.
> - `cma/api/form_list.php`, `cma/api/form_record.php`, `cma/api/form_subform.php` — **no `SecurityHelper` call anywhere** (confirmed: the auth grep returns nothing for these three; only a `405`/JSON-encode line). Anyone who can reach the URL can GET/POST/DELETE records.
> - `cma/subform.php:36-49` — legacy path, uses `SecurityHelper::checkFormRights($userId, $formId)` with a *parent-inheritance* rule and a `SUBFORM_BEHEER`/`ACCESS_FULL_BEHEER` refinement that exists nowhere else.
>
> **Drift evidence:** `form.php` was hardened with menu-based access; the JSON endpoints it feeds were not. Contrast with the *other* files in `cma/api/` which do gate (`config_api.php:25`, `dashboard_stats.php:23`, `form_definition.php:20`, `forms_index.php:28`) — so the pattern exists and was simply never applied to the form-data endpoints.
> **Canonical home:** one `requireFormAccess($formName, $minLevel)` helper (wrapping `MenuService::getFormAccessLevel`) called at the top of `form_api.php` and each `cma/api/form_*.php`; delete the bespoke check in `subform.php`.
>
> ## 2. [HIGH] Record get / save / delete implemented twice with divergent business logic
> - `cma/form_api.php` — `record`/`get_form` (`:791-845`), `save` (`:847-1016`), `delete` (`:1018-1048`). Uses `FormDataProvider::getJsonFormRecordData / saveJsonFormRecord / deleteJsonFormRecord` (form-name based).
> - `cma/api/form_record.php` — `GET`/`POST`/`DELETE` (`:29-167`). Uses `FormDataProvider::getRecordData / saveRecord / deleteRecord` plus `JsonFormService` / `ConfigFormService` (formId based).
>
> **Drift evidence (already diverged):** the `form_api.php` `save` path does datetime date+time recombination (`:934-958`), `users`-form validation (`:973-984`), `ensureMinimumUserLevels()` (`:998-1000`), and WebP variant regeneration (`:1006-1012`). `form_record.php` POST (`:111-136`) does **none** of these — a record saved through `form_record.php` skips all of it. Parameter parsing also differs: `form_api` reads POST form fields + `ID`; `form_record.php` reads a JSON body with `formId`/`id`/`data`. ID handling differs (`Request::queryIntAndGuid` + raw `id` in `form_record.php:35-36` vs `id`-then-`ID` fallback in `form_api.php:796-797`).
> **Canonical home:** collapse onto one service method (`FormDataProvider::save/get/deleteJsonFormRecord`); make `form_record.php` a thin adapter or retire it.
>
> ## 3. [HIGH] Subform data has THREE parallel server paths returning two different shapes
> - `cma/form_api.php` `subform` (`:1050-1066`) and batch `subforms` (`:1068-1122`) → `ListService::getSubformTableHtml` (**HTML**).
> - `cma/api/form_subform.php:113` → `ListService::getSubformTableJson`, and for config forms `ConfigFormService::getSubformListData` (`:56`) (**JSON rows**).
> - `cma/api/form_list.php:58-71` subform branch → `ConfigFormService::getSubformListData` / `JsonFormService::getSubformListData` (**JSON rows**) — a *second* JSON subform entry point.
>
> Consumer `cma/subform.php:105` hard-codes `data-url="api/form_subform.php?..."`, while `form_api.php:823` piggybacks `getSubformTableHtml` onto the record response.
>
> **Drift evidence:** subform-index resolution is written three ways — `form_list.php:60-67` loops `$jsonData['subforms']` matching `name`/`formName`; `form_subform.php:32` just casts `subform` to int; `form_api.php:1054` reads `SubformIndex`. `ConfigFormService::getSubformListData` is now called from three unrelated call sites (`form_list.php:68`, `form_subform.php:56`, `SubformService.php:105,431`).
> **Canonical home:** `SubformService` should expose one `getSubform(formName, parentId, index, options)`; endpoints choose only HTML-vs-JSON rendering of its result. `form_list.php`'s subform branch should delegate to `form_subform.php`'s logic.
>
> ## 4. [HIGH] List/table data duplicated; `list` action is advertised but dead
> - `cma/form_api.php` `tableData` (`:709-755`) → `ListService::getJsonFormTableJson` (JSON rows).
> - `cma/api/form_list.php` (whole file) → `FormDataProvider::getListData` / `JsonFormService::getListData` / `ConfigFormService::getListData` (JSON rows).
>
> Same responsibility (paged/filtered JSON list), two independent implementations with independent option-building.
> **Drift evidence:** `list` is whitelisted (`form_api.php:161`) and given cache headers (`:195`) but has **no `case 'list'` in the main switch** (only the cache switch at `:195`; main switch default is `:1530`) — so `?action=list` falls through to "Ongeldige actie". The handler was moved out to `api/form_list.php` but the whitelist/cache stub was left behind. `form_list.php` also clamps `pageSize` to 10–100 (`:85`) while `form_api.php` `tableData` defaults `limit` to 50 with no clamp (`:716`).
> **Canonical home:** `api/form_list.php` is the JSON-list endpoint; drop `list` from `form_api.php`'s whitelist/cache switch, or make it an internal alias.
>
> ## 5. [MEDIUM] Duplicate request-parameter parsing blocks (filters / lastId / limit / search / sort)
> Near-identical blocks, copy-pasted, no shared helper:
> - `form_api.php` `init` (`:553-582`), `tree` (`:651-684`), `tableData` (`:713-745`) — each re-parses `filters` (json_decode + `Arr::isArray`), `lastId`, `limit`, `search`.
> - `api/form_subform.php:77-110` — same four params parsed again (plus `pageSize ?: limit`, `sortDirection ?: sortDir`).
> - `api/form_list.php:83-98` and `:128-143` — the `filter[...]` bracket-form parser is duplicated **twice within the same file** (JSON-form branch and legacy-ID branch, identical loop).
>
> **Drift evidence:** `limit` default is calculated differently per case — `init` uses 200 (`:579`), `tree` uses 500 (`:679`), `tableData` uses 50 (`:716`); `sortDir` default is `ASC` in most but `form_subform.php:94` layers `sortDirection`→`sortDir`→`ASC`. Two different filter grammars coexist (`filters={json}` vs `filter[name]=val`).
> **Canonical home:** a `ListOptions::fromRequest()` (or `ListQuery` value object) parsing filters/lastId/limit/search/sort once; every list/tree/table/subform case consumes it.
>
> ## 6. [MEDIUM] Combo-options loop copy-pasted, plus a separate combo endpoint
> - `form_api.php` builds the field→options map with an identical `foreach … getJsonFormComboOptions` loop in three places: `init` combos (`:606-611`), `init` searchCombos (`:623-628`), and `combos` batch (`:1177-1182`); single `combo` at `:1158`.
> - `cma/api/forms_index.php` is a *fourth* combo source (the `[{id,text}]` shape for the `form` field picker), independently checking auth and filtering system forms (`:40-53`).
>
> **Drift evidence:** the three in-file loops are byte-for-byte the same except the target array name — any change (e.g. adding filter context, which `combo` at `:1150-1157` already has but the batch loops do not) has to be made in three spots and already isn't consistent.
> **Canonical home:** `FormDataProvider::getComboMap(formName, array $fields, $filterContext)`; the four call sites reduce to one call.
>
> ## 7. [MEDIUM] Response-envelope shaping done three incompatible ways
> - `form_api.php:309` defines `outputJson()` — adds UTF-8 sanitisation, dev diagnostics, and `PerformanceLogger::logApi`.
> - 11 files under `cma/api/` use `Response::json(...)`.
> - 39 occurrences under `cma/api/` hand-roll `echo json_encode(['success' =&gt; false, 'error' =&gt; …])`. `form_list.php` inlines `json_encode($result, JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE)` four times (`:72,106,148` + error `:162`); `form_record.php` repeats the same flags at `:94` and bare `json_encode` elsewhere.
>
> **Drift evidence:** three success envelopes with different guarantees — `outputJson` sanitises UTF-8 and perf-logs; `Response::json` sets status codes; the raw `echo json_encode` in `form_record.php`/`form_list.php` does neither, and inconsistently applies `JSON_INVALID_UTF8_SUBSTITUTE` (present on list/record reads, absent on their own error branches → a corrupt-UTF8 error message can itself fail to encode and emit an empty body).
> **Canonical home:** everything routes through `Response::json()` (or `outputJson` folded into it); ban bare `echo json_encode` in endpoints.
>
> ## 8. [MEDIUM] Tool name→file routing exists in two independent maps
> - `cma/tools.php:60-116` `$toolNameMap` (friendly name → `tools/*.php`) and `:124-136` `$formBackedTools` (alias → JSON form) resolve `?tool=`.
> - `cma/api/tools-catalog.php:27` `buildToolsTreeData()` independently produces the launcher catalog that lists the same tools.
> - `cma/main.php:86` separately whitelists `^(tools|api)/[a-zA-Z0-9_-]+\.php$`, and `cma/web.config:65-68` has its own `^tools/?$` rewrite.
>
> **Drift evidence:** the code comments themselves document this drift class — `tools.php:106-115` explains keys had to be hand-added to `$toolNameMap` because a tool was "surfaced in the catalog but previously reachable only by URL," and warns the launcher's `resolveNav()` derives keys that must be matched "or [the tile] renders blank." Catalog and resolver are two sources of truth for one tool set.
> **Canonical home:** derive both the catalog and the `?tool=` resolver from a single tools registry (one array/JSON), so `tools-catalog.php` and `$toolNameMap` can't disagree.
>
> ## 9. [LOW-MEDIUM] Form-URL grammar parsed in three layers
> - `cma/web.config:105-139` — six `form/…` rewrite rules mapping path segments → `main.php?page=form.php?form=&amp;formID=&amp;popup=&amp;popupID=&amp;subpopup=…`.
> - `cma/main.php:61-119` — re-parses `page`, splits embedded query, re-injects params.
> - `cma/form.php:38-39` → `FormRoute::fromRequest()` normalises `formID`/`id`/`ID`/`popup`/`popupID`/`New`; the comment at `form.php:31-37` states it is "the server-side mirror of cma/assets/js/url-manager.js."
>
> **Drift evidence:** four spellings of the record id (`formID`, `id`, `ID`, `New`) are reconciled in `FormRoute`, but the web.config rules emit `formID`/`popupID` positionally and `main.php` forwards them blindly — a new segment added to the rewrite grammar must also be taught to `FormRoute` and to `url-manager.js`. Three parsers, one grammar.
> **Canonical home:** `FormRoute` is already the intended single contract; keep web.config purely positional and ensure `url-manager.js` and `FormRoute` share a documented segment spec (the only unavoidable duplication is JS-vs-PHP).
>
> ## 10. [LOW] config read/write split — clean but same pattern
> `cma/api/config_api.php` (GET: `list`/`get`/`schema`, `:41-51`) and `cma/api/config_post.php` (POST: `save`/`delete`, `:49-54`) duplicate the `isAdmin` gate (`:25` / `:27`) and the action-switch skeleton. This is a deliberate GET/POST split and currently consistent, so drift risk is low — noted only because it's the same "two files, one resource" shape as #2/#4 and is a candidate to merge behind one method-dispatching endpoint.
>
> ---
>
> ### Cross-cutting recommendation
> The recurring root cause is that `form_api.php` (HTML-returning, form-name based, migration-era) and `cma/api/form_*.php` (JSON-returning, formId based) were written as parallel stacks over the same `FormDataProvider`/`ListService`/`ConfigFormService`/`JsonFormService` services. The services are already shared; the duplication lives entirely in the **HTTP adapter layer** (param parsing, auth, envelope). Three small shared helpers would eliminate most of findings 1, 5, 6, 7: `requireFormAccess()`, `ListOptions::fromRequest()`, and routing all output through `Response::json()`.
>
> Not inspected but flagged as likely additional list-data paths worth the same audit: `cma/details_getdata.php` (7.8 KB, name suggests a legacy list/detail data endpoint) and `cma/wizards/file_list_ajaxdata.php`.</result>
> <usage><subagent_tokens>78404</subagent_tokens><tool_uses>20</tool_uses><duration_ms>225159</duration_ms></usage>
> </task-notification>

> <task-notification>
> <task-id>a5e9842e1525ebbd4</task-id>
> <tool-use-id>toolu_015V2PkkdWC2HXZirKX6QfG8</tool-use-id>
> <output-file>/tmp/claude-1000/-mnt-c-repos-cma-platform/4c10bf01-6ac5-42cd-b645-c012d5313179/tasks/a5e9842e1525ebbd4.output</output-file>
> <status>completed</status>
> <summary>Agent "Scan form-controller.js for missing await" finished</summary>
> <note>A task-notification fires each time this agent stops with no live background children of its own. The user can send it another message and resume it, so the same task-id may notify more than once.</note>
> <result>I have completed a thorough analysis of all ~45 async methods/functions in `form-controller.js` and their call sites.
>
> ## Findings
>
> The file is largely clean with respect to missing-await bugs. The overwhelming majority of non-awaited async calls are **deliberate fire-and-forget terminal actions** — click-handler navigation (`self.loadRecord(id)` on row/tree/list clicks), post-save/post-delete list refreshes, cross-window parent refreshes (`openerCmaForm.loadList()`), and top-level `formInit()` bootstrap in `init()`. None of those have dependent code after them, so they are correct. The core save/load/delete flow (`saveRecord` → `executeAfterPost`/`loadList`/`refreshRow`, `_doLoadRecord`, `Promise.allSettled` for checklists/subforms/renderers) is properly awaited.
>
> I found **one** plausible correctness bug plus one intentional-by-design non-await worth noting:
>
> ### 1. `setParentFieldValue()` not awaited before `captureOriginalValues()` — race — medium confidence
> `/mnt/c/repos/cma_platform/cma/assets/js/form-controller.js:9051`
> Call: `this.setParentFieldValue();` (async, line 2781) inside `newRecord()`, followed 10 lines later by `this.captureOriginalValues();` at **line 9061**.
>
> `setParentFieldValue()` is async and, when the parent field is a `lib-combo`, always takes the async branch: it `await fetch(...)`s the option label and only then sets `field.value = String(this.parentID)` (lines 2818-2834). Because the call is not awaited, `captureOriginalValues()` (line 9061) runs first and records the parent field's `data-original-value` as **empty**. `setParentFieldValue` then sets the field to `parentID` afterward.
>
> Concrete failure: user clicks "add subform record" for a subform whose parent FK is a `lib-combo`. The form opens; the parent combo gets its value set asynchronously *after* originals were captured. `hasUnsavedChanges()` (line 9681, compares current vs `dataset.originalValue`) now reports the fresh record as dirty even though the user typed nothing → cancelling or navigating away triggers a bogus "unsaved changes" confirmation, and `buildChangelog` (line 10036/10042) sees the parent field as a change from `''` to `parentID`. (Also affects the combos-not-yet-loaded path via the 5s poll at lines 2789-2793, for any parent field type.) The copy path at `loadRecordForCopy` line 8293 has the same non-await but is harmless there since it uses `setDirty(true)` and never captures originals.
>
> Fix would be `await this.setParentFieldValue();` in `newRecord()` (and making `newRecord`'s callers await it) — but that's an implementation decision, not requested here.
>
> ### 2. `applyRecordData(data.record)` not awaited in `_doFormInit()` — intentional, NOT a bug
> `/mnt/c/repos/cma_platform/cma/assets/js/form-controller.js:1962` — `this.applyRecordData(data.record);` (async, line 1996). The code comment at line 2016 explicitly documents this as intended ("formInit() calls applyRecordData() without await, then loadRecord()"), and `applyRecordData` internally awaits its own subform/checklist loads and calls `captureOriginalValues` at line 2042 after populating. Reporting for completeness; I do not consider it a defect.
>
> No category-1 (used-as-value in `if`/assignment), category-3 (promise assigned to DOM/innerHTML/JSON), or category-4 (dropped required return value) bugs were found — a grep for `if (this.&lt;async&gt;(` and `= this.&lt;async&gt;(` patterns across all listed async methods returned nothing, and `handleResponse` (the only async whose return value is consumed) is awaited at both call sites (lines 7036, 8183).</result>
> <usage><subagent_tokens>52289</subagent_tokens><tool_uses>18</tool_uses><duration_ms>230268</duration_ms></usage>
> </task-notification>

> <task-notification>
> <task-id>a4d07ed7b123931b2</task-id>
> <tool-use-id>toolu_017uc7ujioa9Toa9f8dUhhuQ</tool-use-id>
> <output-file>/tmp/claude-1000/-mnt-c-repos-cma-platform/11968c9b-70ed-460f-9055-e6fe9af77946/tasks/a4d07ed7b123931b2.output</output-file>
> <status>completed</status>
> <summary>Agent "Audit PHP services duplication" finished</summary>
> <note>A task-notification fires each time this agent stops with no live background children of its own. The user can send it another message and resume it, so the same task-id may notify more than once.</note>
> <result>I have gathered enough evidence across all five areas. Here are my findings, ranked by drift risk.
>
> ---
>
> # Duplicated Logic / Parallel Code Path Audit — `cma/classes`
>
> ## Architecture note
> The delegation chain is clean and should be *skipped*: `FormDataProvider::*` → `Services\ListService::*` → real service; `ListService`'s subform/table methods are all thin `@deprecated`-style pass-throughs (`ListService.php:308-327`, `266-301`). The duplication lives in the *real* implementations, which split legacy-DB vs JSON into fully parallel bodies.
>
> ---
>
> ## 1. HAS BITTEN — `getTableHtml` legacy vs JSON: twin query+render+pagination builders
> **`Services/TableService.php:33-485` (legacy, numeric formId) vs `Services/JsonFormService.php:45-485` (JSON, string formName).**
> `TableService::getTableHtml` line 36-38 hands string forms to `JsonFormService::getTableHtml`, so these two are the two halves of one feature, each ~450 lines, independently doing: column selection, SELECT build, search filter, field filters, keyset pagination, FK batch lookup, `&lt;lib-table&gt;` HTML render, empty-state message. This is the same twin-builder shape as the FormTemplate bug you already found. Concrete drift already present:
>
> - **`totalCount` exists on the JSON path only.** `JsonFormService.php:378-423` computes `totalCount` with elaborate DISTINCT-dedup logic and a comment block about a bug that "kept coming back." `TableService`'s result array (`TableService.php:448-467`) has **no `totalCount` key at all** — the legacy record counter can never have received that fix.
> - **Keyset pagination block is copy-paste and already forked.** `TableService.php:266-273` uses `"[$idField] $comparison $lastIdEscaped"`; `JsonFormService.php:368-376` is byte-for-byte the same except it uses `$qualifiedIdField` (a JOIN-ambiguity fix, `:349-359`) the legacy copy never got.
> - **Search filter forked.** `JsonFormService.php:279-337` grew numeric-search + search-by-ID fixes (with regression comments); `TableService.php:247-256` is still a bare `LIKE` over every column.
>
> Canonical home: extract a shared `ListQueryBuilder` (columns + WHERE + keyset + count) and a shared row/HTML renderer; the two entry points should differ only in how they load field metadata.
>
> ---
>
> ## 2. LIKELY TO BITE — column-type detection has two mappers that already disagree
> **`Services/JsonFormService.php:2140-2161` (`detectColumnType`) vs `Services/TableService.php:920-964` (`getColumnDataType`).**
> Both map a field to the same output vocabulary (`date`/`time`/`boolean`/`combobox`/`number`/`text`) for the `data-type` attribute, from the same ADO schema codes — independently, and they have drifted:
> - Numeric codes: legacy recognizes `[2,3,4,5,6,14,17,18,19,20,21,131]`; JSON recognizes only `['2','3','4','5','6','131']` — misses 14,17,18,19,20,21.
> - Time: legacy maps schema code `134` → `time`; JSON `detectColumnType` checks only the string `'time'` and **never code 134**, so a JSON-form time column carrying `dataType:"134"` renders as `text`.
>
> There is also a *third, cruder* copy of this same "detect type from value" logic inline in the subform methods (`RecordService.php:604-609`, `SubformService.php:220-231`, the latter additionally handling `DateTime`/`bool`). Canonical home: one `FieldType::detect()` used by every list/subform renderer.
>
> ---
>
> ## 3. LIKELY TO BITE — subform list data implemented four independent times
> Four methods produce a "subform rows for a parent record" response with overlapping-but-inconsistent shapes:
>
> | Method | file:line | Source | Pagination | Notable shape |
> |---|---|---|---|---|
> | `RecordService::getSubformData` | `RecordService.php:490-649` | legacy SQL, `TOP 500` | none | `rows/columns/totalCount/canAdd` |
> | `SubformService::getSubformTableJson` | `SubformService.php:48-364` | legacy SQL | keyset + `hasMore`/`lastId` | `rows/columns/totalCount/hasMore/lastId/canAdd` |
> | `ConfigFormService::getSubformListData` | `ConfigFormService.php:593-776` | JSON config file (nested) | none | `html/items/data/columns/total/count/canAdd/canEdit/canDelete` |
> | `JsonFormService::getSubformListData` | `JsonFormService.php:1571-1661` | JSON DB table | none | `data/total` only |
>
> `SubformService::getSubformData` (`:32-36`) even delegates *back* to `FormDataProvider::getSubformData` → `RecordService::getSubformData`, so the codebase maintains both the paginated (`getSubformTableJson`) and unpaginated (`getSubformData`) legacy readers side by side. Evidence of accidental drift:
> - Type detection differs (see finding 2): `RecordService.php:604-609` has no `DateTime`/`bool` branch that `SubformService.php:224-227` has.
> - The empty-`parentField` error path diverged: `SubformService.php:133-146` returns a rich `fixable`/`candidateFields` developer payload; `RecordService.php:539-546` returns a plain `self::error(...)` string.
> - `canAdd` computed differently: `RecordService.php:644` = `$rights &gt;= ACCESS_FULL`; `SubformService.php:359` = `$rights &gt;= ACCESS_FULL &amp;&amp; $hasValidJsonForm`.
>
> Canonical home: one `SubformService::getRows()` returning a single shape, with source (legacy SQL / JSON table / JSON config) as a strategy.
>
> ---
>
> ## 4. LIKELY TO BITE — permission booleans (canAdd/canEdit/canDelete/canCopy) computed in 7 places
> Each recomputes access from `formDef`/`jsonData` + level independently, and they don't agree on the formula:
> - `FormDataProvider.php:645-647` — `$accessLevel &gt;= ACCESS_FULL &amp;&amp; ($jsonData['allowEdit'] ?? true)` (no `canCopy`)
> - `Services/TableService.php:456-459` — `$canX &amp;&amp; $rights &gt;= ACCESS_FULL`, sourced from `formDef-&gt;hasMenuNew()/allowEdit()/hasMenuCopy()/hasMenuDelete()` (includes `canCopy`)
> - `FormTemplate.php:275-277`, `611-620`, `1228-1230` — `$this-&gt;formDef-&gt;allowAdd() &amp;&amp; $this-&gt;accessLevel &gt;= ACCESS_FULL` (three separate spots inside one file)
> - `Services/ConfigFormService.php:769-771` — `($subformJsonData['allowAdd'] ?? true) &amp;&amp; $hasFullAccess` where **`$hasFullAccess = SecurityHelper::isAdmin()`** (`:671`) — a *different gate* than `accessLevel &gt;= ACCESS_FULL` used everywhere else
> - `RecordService.php:644` and `SubformService.php:359` — subform `canAdd` only, two more formulas (see finding 3)
> - `LibTable.php:57-60` / `281-285` — object-property version
>
> Drift is definitional: three different truth sources (`jsonData['allowX']` vs `formDef-&gt;allowX()` vs `isAdmin()`), and `canCopy` present in some payloads and silently absent in others. Canonical home: `SecurityHelper::computePermissions(FormDefinition $def, int $level): array` returning all four keys.
>
> ---
>
> ## 5. CosmETIC→LIKELY — JSON combo/FK option loading duplicated (and legacy FK-label resolver parallels it)
> - **JSON combo options loaded two ways.** `FormDataProvider::getJsonFormComboOptions` (`FormDataProvider.php:1264-1400+`, single field, API) and the **inline block** in `JsonFormService::getTableHtml` (`JsonFormService.php:544-626`, batch, for the table) both independently branch on the same JSON field structure (`optionsSource.type==='jsonConfig'` → `ConfigFormService::getOptionsFromConfig`; else `sql`/`dataSource`/`sourceTable`+`idField`+`displayField`). They cache differently (JsonFormService uses APCu `:584-620`; FormDataProvider doesn't).
> - **FK-lookup map building triplicated:** `TableService.php:362-376`, `JsonFormService.php:628-640` — identical `$fkLookup[$field][$id] = $opt['text'] ?? $opt['label'] ?? $id` loops.
> - **Single-record FK label resolution is a legacy/JSON pair:** `RecordService::resolveFkLabels` (`RecordService.php:1028-1260`) vs `FormDataProvider::resolveJsonFormFkLabels` (`FormDataProvider.php:1975-2105`) — same job (resolve FK codes → display text for one record), two bodies.
>
> Canonical home: a single `OptionsService::getJsonComboOptions()` used by both the table renderer and the API, plus one FK-label resolver.
>
> ---
>
> ## 6. COSMETIC (but a live drift trap) — dead duplicate `buildComboSql`
> **`FormDataProvider.php:325-350` vs `Services/OptionsService.php:357`.**
> `FormDataProvider::buildComboSql` is `private` and has **no callers** (grep confirms only `OptionsService::buildComboSql` is invoked, at `OptionsService.php:105,615`). It is a stale copy that has already drifted — `OptionsService`'s version added a `$recordContext` parameter and context substitution the dead copy lacks. Suggestion: delete the `FormDataProvider` copy.
>
> ---
>
> ## 7. COSMETIC — `getTableJson` twins mirror the `getTableHtml` twins
> `TableService::getTableJson` (`:494`) and `JsonFormService::getTableJson` (`:1411`) repeat, in JSON-output form, the same column/SQL/search/keyset logic already duplicated in the two `getTableHtml` methods (finding 1) — so the query+pagination logic effectively exists **four** times. Folding finding 1 into a shared query builder collapses these too. (`ListService::getTableJson:274` and `getListData` at `ConfigFormService:61`/`JsonFormService:1425`/`ListService:38` are genuinely different data sources — a strategy split, not copy-paste — so lower priority.)
>
> ---
>
> ### Suggested priority order
> 1. Unify `getTableHtml` legacy/JSON (finding 1) — same failure mode that already bit you; the missing `totalCount` on the legacy path is a concrete latent bug.
> 2. Single `FieldType::detect()` (finding 2) — proven-divergent, easy to consolidate.
> 3. Single subform-rows service (finding 3) and single `computePermissions()` (finding 4).
> 4. Consolidate combo/FK loading (finding 5), delete dead `buildComboSql` (finding 6).</result>
> <usage><subagent_tokens>87818</subagent_tokens><tool_uses>35</tool_uses><duration_ms>286472</duration_ms></usage>
> </task-notification>

> <task-notification>
> <task-id>af148d298af9bc075</task-id>
> <tool-use-id>toolu_015G9GA93SSABsCJYeEAVhc6</tool-use-id>
> <output-file>/tmp/claude-1000/-mnt-c-repos-cma-platform/11968c9b-70ed-460f-9055-e6fe9af77946/tasks/af148d298af9bc075.output</output-file>
> <status>completed</status>
> <summary>Agent "Audit helpers/lib duplication" finished</summary>
> <note>A task-notification fires each time this agent stops with no live background children of its own. The user can send it another message and resume it, so the same task-id may notify more than once.</note>
> <result>I have enough to compile the audit. Here are the findings, ranked by drift risk.
>
> ---
>
> # Duplication Audit: src/helpers vs library/lib_*.inc vs cma/classes
>
> ## Summary of the landscape
> The migration is partly done well: many legacy procedural functions have already been reduced to **thin wrappers** that delegate to the class (`Lib_FixDateValue`→`Date::fixValue`, `gfxSpex`→`Image::getInfo`, `SendMail`/`LibMailer`→`Email`, `lib_Cache_FromFile`→`Cache::loadFile`, `SqlHelper::quoteValue`→`SQL::postString`). Those are healthy and need no action. The genuine drift risks are a smaller set where a **full second implementation** still lives, or where a **constant is defined with different values in different files**. Most duplicated procedural bodies are now *unreachable* (dead), which lowers their risk but leaves live landmines.
>
> ---
>
> ## SEVERITY 1 — Constant value drift, both copies reachable
>
> ### 1a. `CACHE_PATH` / `XSLT_PATH` — backslash vs forward-slash
> - `library/library.inc:30` → `define("CACHE_PATH", 'cache\\')` (Windows backslash) and `:29` → `XSLT_PATH = 'xslt\\'`
> - `library/config/constants.php:16-17` → `'xslt/'` / `'cache/'`
> - `templates/_bootstrap_constants.inc:16-17` → `'xslt/'` / `'cache/'`
>
> Duplicated: the cache/xslt directory-path constants. All three are `if(!defined)`-guarded, so the **value depends on which file loads first** — a classic ASP-ism (`cache\`) can win on one entry point and the POSIX form on another. On Linux (this deploy is WSL/Linux) the backslash form produces a literal `cache\` filename. Reachability: `library.inc` is the legacy aggregator; `constants.php` and `_bootstrap_constants.inc` are the modern bootstrap constants — different request entry points load different ones.
> Canonical home: keep the POSIX definition in `library/config/constants.php` only; delete the backslash defines in `library.inc`.
>
> ### 1b. jQuery asset version drift (`STRJQUERYVERSION` / `JQUERYVERSION`)
> - `cma/bootstrap.inc:246-247` → `STRJQUERYVERSION = '1.10.2'`, then `JQUERYVERSION = STRJQUERYVERSION`
> - `templates/_bootstrap_constants.inc:74,77` → `JQUERYVERSION = '1.11.3'`, `STRJQUERYVERSION = '1.11.3'`
>
> Duplicated: the asset-version constant used to build jQuery `&lt;script&gt;` URLs (point 3, asset-URL/version helper). Guarded, so whichever bootstrap loads first pins the version — the app can serve **jQuery 1.10.2 on one path and 1.11.3 on another**. Both files are live bootstraps.
> Canonical home: one constants file; the two disagree on the actual version number, so this needs a decision, not just dedup.
>
> ---
>
> ## SEVERITY 2 — Full re-implementation, reachable, output differs
>
> ### 2. Date formatting: `library/lib_general.inc` vs `App\Library\Date`
> Reachable caller of the legacy path: `library/classes/class_table.inc:219-227` (loaded via `cma/bootstrap.inc` / `library/library.inc`) calls `lib_time()`, `strMediumDate()`, `Lib_FixDateValue()`.
>
> - `strMediumDate()` `library/lib_general.inc:111` vs `Date::mediumDate()` `src/helpers/Date.php:276` — **real output drift**:
>   - legacy: `15-mrt-2024` (dash-separated, zero-padded day via `date('d')`, **never** relative)
>   - class: `15 mrt 2024` (space-separated, unpadded day via `date('j')`, and returns `Vandaag/Morgen/Gisteren` when `Application::get('library_relative_dates')` is on)
>   So the legacy list renderer and the new renderer format the same date differently.
> - `lib_date_sortable()` `:89` returns `Ymd` (`20240315`) vs `Date::sortable()` `:133` returns `Y-m-d` (`2024-03-15`) — different format.
> - `lib_datetime_sortable()` `:69` returns `Ymd H:i:s` vs `Date::sortableDateTime()` `:149` returns `Y-m-d_H_i` — different format.
> - `lib_date_ddmmmmmyyyy()` `:38` and `strWeekday()` `:142` duplicate `Date::longDate()`/`Date::shortWeekday()` (these two are currently *not* called elsewhere).
> - `lib_time()` `:26` == `Date::time()` `:168` — identical (safe).
> - `Lib_FixDateValue()` `:15` is already a clean wrapper to `Date::fixValue` — good.
>
> Canonical home: `App\Library\Date`. Convert the remaining `lib_general.inc` date functions to wrappers (as `Lib_FixDateValue` already is), or repoint `class_table.inc` at `Date::` directly. Note `strMediumDate` cannot become a pure wrapper without accepting the dash-vs-space/relative behavior change.
>
> ---
>
> ## SEVERITY 3 — Divergent duplicate logic, currently dead (latent landmines)
>
> ### 3. File-cache path scheme: `library/lib_cache.inc` vs `App\Library\Cache`
> `lib_cache.inc` self-describes as "NOT YET MIGRATED". Two incompatible path schemes exist, and the legacy file is even **internally inconsistent**:
> - `lib_Cache_GetFilename()` `library/lib_cache.inc:78` → `base_path + 'cache/' + Lib_FilenameSanitize(...)`
> - `lib_CacheFileClear()` `:25` → `base_path + CACHE_PATH + $identifier` (raw, **no sanitize, different constant**) — so Clear can't reliably delete what Save wrote.
> - `Cache::getCacheFilename()` `src/helpers/Cache.php:1052` → `Application::get('cache_directory', sys_get_temp_dir().'/cma_cache') + '/' + preg_replace('/[^a-zA-Z0-9_\-]/','_',$id) + '.cache'` — **different directory, different sanitizer, `.cache` suffix**.
>
> Reachability: `lib_CacheFileSave/Load/Clear` have **zero real callers** (the earlier "1 hit" was a doc-comment in `Cache.php:1025`). `lib_Cache_FromFile/ToFile` are already wrappers to `Cache::`. Risk is latent: if anyone revives the `lib_CacheFile*` trio it silently reads/writes a different location than `Cache`.
> Canonical home: `App\Library\Cache`; delete the `lib_CacheFile*` bodies. Also note the duplicated filename sanitizer — `Lib_FilenameSanitize()` (`library/lib_filename.inc`, only used by the dead `lib_cache.inc`) vs the inline `preg_replace` inside `Cache::getCacheFilename`.
>
> ### 4. HTML helpers: `library/lib_html.inc` vs `App\Library\Html` (+ private copy in Email)
> - `HTMLDecompile()` `library/lib_html.inc:40` is reachable via `cma/template_fillrep.php:138`. It is ~70 lines; `Html::decode()` `src/helpers/Html.php:105` is a one-line `html_entity_decode`. These are **not equivalent** despite the name overlap — the class version does not cover the legacy behavior, so you cannot blindly swap.
> - `lib_HTML_CharacterCompile()` `library/lib_html.inc:165` is duplicated as a **private method** `Email::htmlCharacterCompile()` `src/helpers/Email.php:840` (the code comment at `:842` even says "This would contain the lib_HTML_CharacterCompile logic"). `Html::fixUnicode()` `:23` corresponds to `lib_HTML_fixUnicode()` `library/lib_html.inc:124` (legacy dead).
> Canonical home: `App\Library\Html`; port the full `HTMLDecompile`/`CharacterCompile` bodies into `Html` and have both `template_fillrep.php` and `Email` call it (Email currently has its own private fork → drift-prone).
>
> ---
>
> ## SEVERITY 4 — Dead 1:1 duplicates (cleanup, low risk)
>
> ### 5. Filesystem helpers: `library/lib_dir.inc` vs `App\Library\File`
> Direct twins, all with **zero live callers** (legacy side dead):
> `lib_FileExists`↔`File::exists`, `lib_FileDelete`↔`File::delete`, `lib_FolderExists`↔`File::folderExists`, `lib_FileCopy`↔`File::copy`, `lib_CreateFolder`↔`File::createFolder`, `lib_ReadAsciiFile`↔`File::readAsciiFile`, `lib_CreateAsciiFile`↔`File::createAsciiFile` (`library/lib_dir.inc:13-207` vs `src/helpers/File.php:29-228`).
> Canonical home: `App\Library\File`; retire `lib_dir.inc`.
>
> ### 6. URL slug: `lib_friendlyURL()` vs `Str::slug()`
> `library/lib_url.inc:92` vs `src/helpers/Str.php:73`. **Different algorithms** — `lib_friendlyURL` has Dutch-specific rules (`&amp;`→`en`, `%`→`procent`, strips lidwoorden) that `Str::slug` does not. `lib_friendlyURL` has zero callers (dead). If URL slugging is ever needed again, these will produce different slugs.
> Canonical home: fold the Dutch rules into `Str::slug` (or a `Str::friendlyUrl`) before deleting the legacy one.
>
> ### 7. Query-string/int helpers overlap (3-way)
> `lib_general.inc` `Lib_QueryString_Int` / `_IntAndComma` / `_IntAndGuid` / `Lib_CleanUserInput` (`:290-316`) overlap both `Request::queryInt`/`queryIntAndComma`/`queryIntAndGuid` (`src/helpers/Request.php:220-279`) and `Str::numbersOnly`/`numbersOnlyAndComma` (`src/helpers/Str.php:181-195`). Legacy side dead.
> Canonical home: `Request::` for request parsing, `Str::` for pure string cleaning.
>
> ---
>
> ## SEVERITY 5 — Multiple logging/config layers (architectural fragmentation, not output drift)
>
> ### 8. Logging systems
> `App\Library\Log` (`src/helpers/Log.php`, simple single-file init/write/close) is used **only** by `src/helpers/Database.php`. `Cma\Services\Logger` (`cma/classes/Services/Logger.php`, leveled PSR-style) is used in ~28 files. Plus `Cma\Services\PerformanceLogger` and the very large `src/helpers/ErrorHandler.php` + `src/helpers/Error.php`. These overlap on "append a line to a log file" with different formats/locations.
> Canonical home: consolidate on `Cma\Services\Logger`; make `App\Library\Log` (or the `Database` call site) delegate to it.
>
> ### 9. Constants defined in 2 places with equal values (low risk, guarded)
> `ADBOOLEAN`/`ADINTEGER`/`ADDATE` — `cma/bootstrap.inc:988` vs `cma/classes/CmaRepository.php:20` (both `11`, guarded). `CONST_STRSORTPARAM='Sort'` in three files (`cma/bootstrap.inc:15`, `library/lib_table.inc:5`, `library/config/constants.php:70`). `VIEW_LIST`/`VIEW_THUMB`/`VIEW_COOKIE` duplicated across `cma/wizards/file_list.php:18` and `file_list_ajaxdata.php:14`. Values currently agree, so risk is only future drift.
> Canonical home: one constants file (`library/config/constants.php`); remove the inline re-defines.
>
> ---
>
> ## Confirmed NON-issues (already correct wrapper pattern — do not touch)
> - `Lib_FixDateValue()` → `Date::fixValue` (`library/lib_general.inc:15`)
> - `gfxSpex()` → `Image::getInfo`/`getType` (`library/lib_imgformat.inc:23`) — reachable from `cma/tools/tools_db_consistency.php:193`, `cma/wizards/file_outputfile.php:83`, but a clean delegate
> - `SendMail()`/`LibMailer` → `App\Library\Email` (documented thin wrapper since v1.28.30)
> - `Cma\SqlHelper::quoteValue` → composes `SQL::postString`/`postDateStr` (schema-aware layer, not a duplicate escaper)
> - `lib_Cache_FromFile`/`ToFile` → `Cache::loadFile`/`saveFile`
>
> ## Key file:line index
> - Date twins: `library/lib_general.inc:26,38,69,89,111,142` ↔ `src/helpers/Date.php:133,149,168,206,223,276`; live caller `library/classes/class_table.inc:219,221,227`
> - Cache twins: `library/lib_cache.inc:25,36,53,78` ↔ `src/helpers/Cache.php:981,1000,1031,1052`
> - File twins: `library/lib_dir.inc:13-207` ↔ `src/helpers/File.php:29-228`
> - HTML twins: `library/lib_html.inc:40,124,165` ↔ `src/helpers/Html.php:105,23` + `src/helpers/Email.php:840`
> - Constant drift: `library/library.inc:29-30`, `library/config/constants.php:16-17`, `templates/_bootstrap_constants.inc:16-17,74,77`, `cma/bootstrap.inc:246-247`</result>
> <usage><subagent_tokens>62681</subagent_tokens><tool_uses>17</tool_uses><duration_ms>488919</duration_ms></usage>
> </task-notification>

> <task-notification>
> <task-id>a719340cb332b417a</task-id>
> <tool-use-id>toolu_01YCEUhytuemF1QKd2JSHgXt</tool-use-id>
> <output-file>/tmp/claude-1000/-mnt-c-repos-cma-platform/11968c9b-70ed-460f-9055-e6fe9af77946/tasks/a719340cb332b417a.output</output-file>
> <status>completed</status>
> <summary>Agent "Audit webcomponents/CSS duplication" finished</summary>
> <note>A task-notification fires each time this agent stops with no live background children of its own. The user can send it another message and resume it, so the same task-id may notify more than once.</note>
> <result>I have gathered comprehensive evidence. Here is the structured audit, ranked most severe first.
>
> ---
>
> # Front-end Duplication / Parallel-Path Audit — cma_platform
>
> ## 1. `escapeHtml` — 11+ divergent copies, quote-escaping drifts (SECURITY-RELEVANT) — HIGHEST
> The canonical global escapes quotes; almost every private copy does not, so the same helper name is XSS-safe in attribute context in some call sites and unsafe in others.
>
> - Canonical: `cma/assets/js/cma-utils.js:271` `CMA.utils.escapeHtml` → `div.innerHTML` **plus** `.replace(/"/g,'&amp;quot;').replace(/'/g,'&amp;#039;')` (attribute-safe), exported as `window.escapeHtml`.
> - Drifted copies that do NOT escape quotes:
>   - `cma/assets/js/cma-utils.js:251` (inside `cmaErrorParser` object) — `div.textContent`, no quote escaping — **same file as the canonical one**.
>   - `cma/assets/js/cma.js:779` — regex `&amp; &lt; &gt;` only.
>   - `cma/assets/js/form-controller.js:5454` — `textContent`, no quotes.
>   - `cma/assets/js/table-preferences.js:1448` — `textContent`, no quotes, **no null guard** (throws on null).
>   - `cma/assets/js/inline-edit.js:2643` — correctly delegates to global (the one good citizen).
>   - Component-private copies: `library/webcomponents/lib-dialog.js:908` &amp; `:1202`, `library/webcomponents/lib-loader.js:274`, `library/webcomponents/lib-table.js:1126` (`#escapeHtml`), `cma/webcomponents/cma-groupbox.js:179`, `cma/webcomponents/cma-tabs.js:1386`.
> - Reachable: yes, all. Drift evidence: quote handling and null handling differ across copies.
> - Canonical home: delete local copies, everyone calls `CMA.utils.escapeHtml` / `window.escapeHtml` (component copies can keep a thin private alias but must match quote behavior).
>
> ## 2. Group folding — legacy `CMA.groups`/`grp_flip` + `.groupbox` markup vs `cma-groupbox` component — HIGH (this is the exact pattern the user flagged, still live)
> - Component path: `cma/classes/FormRenderer.php:1045` emits `&lt;cma-groupbox&gt;`; behavior in `cma/webcomponents/cma-groupbox.js`; CSS `cma/assets/css/style.css:473-633`.
> - Legacy path still reachable: `cma/classes/FormControlHelper.php:365` (`FormControlHelper::editTip()`) emits `&lt;div class="groupbox group_open" onclick="grp_flip(0,formId)"&gt;` + `.groupbox-title` / `.groupbox-chevron`, driven by `CMA.groups` at `cma/assets/js/cma.js:1476-1522` via globals `grp_set/grp_flip/grp_init` (`cma.js:2095-2097`). `grp_init` is also still called from `form-controller.js:1440`.
> - Drift evidence: `style.css:473` comment says "no `.groupbox` class needed" — the component was migrated off `.groupbox`, but `editTip()` still relies on `.groupbox group_open` + `grp_flip` and the surviving `.groupbox-chevron` rule. The two toggles compute chevron glyph independently (`cma.js:1493` sets `lnr-chevron-right/down`; the component sets its own).
> - Canonical home: convert `editTip()` to `&lt;cma-groupbox&gt;` and delete `CMA.groups` + `grp_*` globals.
>
> ## 3. CSS `style.css` vs `form.css` — same selectors maintained twice, values already drifted — HIGH
> Both files are loaded together on at least `cma/html_edit_link.php:15` and `cma/html_edit_image.php:12` (`minify.php?f=...style.css,form.css`), so the later file silently wins.
> - Duplicated selectors (intersection): `.tb-btn`, `.tb-btn:hover`, `.tb-btn a`, `.tb-btn .lnr-table`, `.tb-sep`, `.toolbar-left`, `.toolbar-right`, `.checklist-group` (+ `-header/-hint/-inline/-item`), `.select2-container .select2-choice`, `tr.groupbox-end &gt; td`, `#listContent a.active::before`.
> - Concrete drift — `.tb-btn`:
>   - `style.css:1115` → `padding:4px`; `.tb-btn a` uses `gap:4px`; hover on `.tb-btn a:hover`.
>   - `form.css:975` → `padding:0; padding-right:4px`; `.tb-btn a` uses `padding:3px`; hover on `.tb-btn:hover a`.
>   - Same class, different box metrics and different hover selector structure.
> - `tr.groupbox-end &gt; td`: `style.css:534` vs `form.css:169` (form.css adds a `border-top`).
> - Canonical home: one owner per selector. Toolbar/groupbox/checklist/select2 rules belong in `style.css` (global); remove the `form.css` copies or scope them (`div.toolbar .tb-btn` already exists at `form.css:798` as the intended scoped variant).
>
> ## 4. `form.php?form=...` URL building by hand vs `url-manager.js` — HIGH volume
> Canonical builder exists: `cma/assets/js/url-manager.js:179` `toPageUrl()` / `:127` `buildUrl()` (documented as the `Cma\FormRoute` contract at `url-manager.js:164-181`, i.e. `'form.php?form=' + encodeURIComponent(state.form)` + id/subform params). Hand-rolled concatenations bypass it:
> - `cma/assets/js/inline-edit.js:2096` and `:2438`
> - `cma/assets/js/main.js:1577`, `:1590`, `:1617`, `:1648`
> - `cma/assets/js/form-controller.js:4403`, `:4710`, `:12548`
> - `cma/assets/js/cma.js:1391` / `:1393` (`'form.php?FormID=' + sID` — note **different param name `FormID`** vs the canonical `form`/`id`, drift evidence).
> - Reachable: all. Worst offenders: `main.js` (4 sites building sub/subsub URLs by hand) and `form-controller.js` (3 sites), which re-derive param names the url-manager already owns.
> - Canonical home: route every internal navigation through `UrlManager.toPageUrl()` / `navigateToForm`.
>
> ## 5. Popup/window opening — sizing/naming duplicated across files &amp; two APIs — MEDIUM-HIGH
> Helpers defined once in `library/library.js`: `lib_OpenWindowCentered` (`:946`), `lib_OpenWindowCenteredClose` (`:1221`), `lib_OpenSidePanel` (`:2057`), plus `lib_OpenPanel`. But raw `window.open` with hardcoded sizes coexists, and the same logical popup is opened two different ways:
> - `'addRelated'` 800×600: `cma/assets/js/form-controller.js:4429` uses raw `window.open(popupUrl,'addRelated','width=800,height=600')` while `cma/assets/js/inline-edit.js:2451` uses `lib_OpenPanel(popupUrl,'addRelated',800,600)` — same window name/size, two mechanisms (drift risk if size changes).
> - Other raw `window.open` sizing scattered: `form-controller.js:4076` (fileSelect 1100×700), `:4125` (imageEdit 1200×820), `cma.js:1650` (view 1000×700), `inline-edit.js:2201/2455`, plus `library/library.js:936`, `library/classes/class_tablescroll.js:72`, `cma/wizards/wizard.js:29`.
> - The `useSidepanel ? lib_OpenSidePanel : lib_OpenWindowCentered` branch is itself re-implemented independently in `form-controller.js:4757/5102`, `inline-edit.js:2194`, and `main.js:1574/1629`.
> - Canonical home: a single `openFormPopup(url, {name,width,height,sidepanel})` wrapper in `cma-utils.js` that internally chooses `lib_OpenSidePanel`/`lib_OpenWindowCentered`.
>
> ## 6. Copy-to-clipboard — canonical helper exists, 4 reimplementations — MEDIUM
> Canonical: `cma/assets/js/cma-utils.js:114` `window.cmaCopyToClipboard` (Clipboard API + hidden-textarea/`execCommand` fallback, http-safe). Byte-for-byte re-implementations of the same fallback:
> - `cma/assets/js/error-handler.js:618-635`
> - `library/webcomponents/lib-message.js:37-49`
> - `cma/webcomponents/cma-query-preview.js:1023-1047`
> - `cma/webcomponents/cma-sql-editor.js:504-524`
> - Reachable: yes. Drift risk: only `cma-utils`/`lib-message` guard `navigator` existence for insecure origins; the two `cma-*` component copies call `document.execCommand('copy')` directly.
> - Canonical home: components call `window.cmaCopyToClipboard`.
>
> ## 7. Sortlist — legacy `CMA.listbox` (`lb_*`) vs `renderSortlist`/`cma-sortlist` — MEDIUM
> Two server renderers for `TYPE_SORTLIST = 13` produce incompatible markup + behavior:
> - Legacy live path: `src/helpers/FormControls.php:726-749` emits a `&lt;select name="srtlst_..."&gt;` with `onclick="lb_sortup/lb_sort(...)"` and `onkeydown="lb_key(event)"`, driven by `CMA.listbox` at `cma/assets/js/cma.js:1527-1606` (globals `lb_*` at `cma.js:2100-2105`).
> - Modern path: `cma/classes/FormRenderer.php:1005` `renderSortlist()` emits `&lt;div class="sortlist-container" data-sortlist&gt;` + hidden input (enhanced client-side, DnD).
> - Component `cma-sortlist` (`cma/webcomponents/cma-sortlist.js`) exists but is referenced **only** in `cma/tools/storybook.php` — an unadopted third implementation.
> - Drift evidence: two different DOM shapes and two different reorder mechanisms (Ctrl+arrow bubble-sort vs DnD) for the same control type; `RecordService.php:1425` still parses `srtlst_{id}_info`.
> - Canonical home: standardize on `renderSortlist` + `cma-sortlist`; retire `FormControls.php` sortlist + `CMA.listbox`.
>
> ## 8. Legacy client-side tree `CMA.tree` vs `cma-tree` component — MEDIUM (dormant but wired)
> - Component: `cma/webcomponents/cma-tree.js`, used across `cma/reports.php`, `tools.php`, `listTemplates.php`, `TreeService.php`, etc.
> - Legacy dTree builder: `cma/assets/js/cma.js:68-486` (`CMA.tree`, `Folder`/`Item`, `initializeToElement`, cookie state) with globals `gFld/insFld/F/D/I/initializeToElement` (`cma.js:2021-2043`). Still invoked defensively by `form-controller.js:2313` and `:7134` when a server response contains a `gFld` script.
> - Reachability: no PHP currently emits `gFld`/`insFld` output (grep found zero emitters), so the legacy path appears dormant, but the branch and ~420 lines remain and re-implement expand/collapse/cookie-persist that `cma-tree` owns.
> - Canonical home: `cma-tree`; delete `CMA.tree` and the `form-controller` fallback branches once confirmed no legacy tree emitters remain.
>
> ## 9. Tooltip fragmentation — 3 systems — MEDIUM/LOW
> - `cma/assets/js/cma-utils.js:409-575` — full JS tooltip engine (`createTooltip`/`showTooltip`/`hideTooltip`/`needsJsTooltip`, class `cma-tooltip`) for `data-tooltip` + CSS `::after` fallback.
> - `library/webcomponents/lib-tip.js` — `&lt;lib-tip&gt;` web component.
> - `library/tooltip.css` — a third styling source.
> - Overlap: `cma-utils` and `lib-tip` both render floating tooltips with arrow + positioning logic. Drift risk: positioning/arrow code maintained twice; which one applies depends on whether markup uses `data-tooltip` or `&lt;lib-tip&gt;`.
> - Canonical home: pick `lib-tip` as the component; keep `cma-utils` engine only as the documented "escapes overflow:hidden clipping" fallback, or fold that capability into `lib-tip`.
>
> ## 10. Cookie get/set — canonical exists, side copies — LOW-MEDIUM
> Canonical: `cma/assets/js/cma-utils.js:328` `CMA.utils.getCookie` / `:347` `setCookie` (cma.js:2089-2090 aliases `Get_Cookie`/`Set_Cookie` to these; comment at `cma.js:1468` confirms the migration).
> - Independent re-implementations: `library/webcomponents/lib-log.js:28` `getCookie` (own copy), and direct `document.cookie` regex reads in `form-controller.js:181` and `cma/assets/js/modules/cma-perf.js:69` (identical `cma_perf_logging` regex — duplicated in two files).
> - Canonical home: `CMA.utils.getCookie`; `lib-log` may keep a standalone copy only to stay dependency-free of `CMA`.
>
> ## 11. `debounce` — LOW
> Canonical: `cma/assets/js/cma-utils.js:368` `window.cmaDebounce` (used correctly by `form-controller.js:3776`). Private re-impl: `library/webcomponents/lib-table.js:845` `#debounce`. Only one component copy — low drift, note only.
>
> ## 12. Two `error-handler.js` files drifting — LOW (library copy is outside the core cma layer but worth flagging)
> `cma/assets/js/error-handler.js` (27,770 bytes, Jul 7) vs `library/assets/js/error-handler.js` (30,821 bytes, Jul 14) — `diff` reports DIFFERENT, and each carries its own clipboard fallback (see #6). Parallel maintenance of the same error-parser/notifier. Canonical home: one shared file (the library copy is newer — reconcile toward it).
>
> ---
>
> ### Suggested priority of remediation
> 1. Consolidate `escapeHtml` (security).
> 2. Kill legacy `CMA.groups`/`editTip` `.groupbox` path.
> 3. De-dupe `style.css`↔`form.css` selectors (start with `.tb-btn`).
> 4. Route hand-built `form.php?form=` URLs through `url-manager`.
> 5. Single popup wrapper over `lib_Open*`.
> 6. Point clipboard callers at `cmaCopyToClipboard`.
> 7. Pick one sortlist and one tree implementation; retire `CMA.listbox` / `CMA.tree`.
>
> Note: all `*.min.*` files were ignored per instructions; findings above are from source files only.</result>
> <usage><subagent_tokens>68880</subagent_tokens><tool_uses>28</tool_uses><duration_ms>493699</duration_ms></usage>
> </task-notification>

> ja fijn als je die toepast

> please solve all and double check assumptions

## 2026-07-16

> a restart forced a quit, do you know what you were working on?

> continue

> yes please do

> <task-notification>
> <task-id>a09a7ff6382a75bdc</task-id>
> <tool-use-id>toolu_01V2nXLB1iLJxvPgVnryRUae</tool-use-id>
> <output-file>/tmp/claude-1000/-mnt-c-repos-cma-platform/bdfd0d27-ce63-4936-89b3-95bc964247f8/tasks/a09a7ff6382a75bdc.output</output-file>
> <status>completed</status>
> <summary>Agent "Map tooltip system usage" finished</summary>
> <note>A task-notification fires each time this agent stops with no live background children of its own. The user can send it another message and resume it, so the same task-id may notify more than once.</note>
> <result>I have all the evidence needed. Here is the map.
>
> # Tooltip Systems Map — cma_platform
>
> ## System 1: `data-tooltip` CSS+JS engine (the primary, live system)
> Two cooperating layers that both key off the `data-tooltip` attribute:
> - CSS `::after` layer — `cma/assets/css/style.css:1159-1235` (base at 1165, position variants 1198-1220, responsive-only toolbar rules 1184-1195, JS-active suppression 1232-1234).
> - JS floating layer — `cma/assets/js/cma-utils.js:690-898` (IIFE): `createTooltip` (693), `showTooltip` (713), `hideTooltip` (820), `needsJsTooltip` (839), `init` delegation (869). Element class `cma-tooltip` styled at `style.css:1238-1258`.
>
> ## System 2: `&lt;lib-tip&gt;` / `LibTip` — tours and one-shot coach tips
> - `library/webcomponents/lib-tip.js`: custom element `lib-tip` defined at :641, `LibTip` API object at :644 (`show`/`tour`/`dismiss`/`close`/`reset`). Creates its element via `document.createElement('lib-tip')` (:653). Uses Shadow DOM (`.tip-title`, `.tip-content`, `.tip-nav-next` per the cypress specs).
>
> ## System 3: `library/tooltip.css` — legacy `a.tt` image-based tooltip
> - Styles only `a.tt` / `a.tt:hover span.tooltip` etc., using `images/tooltip.gif` background images (whole file, 56 lines).
>
> ---
>
> ## A. Who sets `data-tooltip`, and how many distinct places
> The single largest source is automatic: the global **title→data-tooltip converter** at `cma-utils.js:905-967` (sets at :918-919, :939, :953) — it strips every `title` attribute in the document (and via MutationObserver, dynamically) and re-emits it as `data-tooltip`. So effectively *every* titled element feeds System 1.
>
> Explicit emitters (~20 distinct sites):
>
> PHP:
> - `cma/classes/ToolbarHelper.php:207-211` — central `linearButton` helper (emits `data-tooltip` for many toolbars across the app)
> - `cma/classes/FormTemplate.php:622` — readonly indicator
> - `cma/bootstrap.inc:2023` — subform "add" toolbar button
> - `cma/dashboard.php:1068,1071,1088,1091,1108,1124,1173,1199`
> - `cma/image-editor.php:156-178` (16 buttons)
> - `cma/main.php:505` (menu header) and inline JS at `:609-612`
> - `cma/report-designer.php:97,257,337,347` + JS template strings at `:2532,3014-3019`
> - `cma/listTemplates.php:46,50`; `cma/template_edit.php:182`; `cma/reports_DEPRECATED.php:83`
>
> JS:
> - `cma/assets/js/cma.js:224`; `blockedit.js:806`; `inline-edit.js:494,497,594`; `form-controller.js:4279,4311-4313,4815`
>
> ## B. CSS `::after` fallback and when JS takes over
> - Fallback defined in `cma/assets/css/style.css` — base `[data-tooltip]:hover::after` at **:1165** (with `:not(input):not(select):not(textarea)` since pseudo-elements can't attach to form controls), position variants :1198-1220.
> - JS takeover logic: `needsJsTooltip(el)` at `cma-utils.js:839-863`. Documented reasons in the code comments:
>   1. `input`/`select`/`textarea` can't host `::after` (:842-843).
>   2. `.image-preview-btn` — its `::before` *is* the icon, so the `::after` tooltip gets clipped/fights the hover restyle (:845-851).
>   3. **Any ancestor with `overflow:hidden`/`overflowX/Y:hidden` clips the CSS tooltip** — walks up to `document.body` (:853-861). This is the primary "escape clipping" rationale: the JS tooltip is `position:fixed; z-index:100000` (`style.css:1238-1249`) so it escapes overflow clipping. When JS is active it adds `js-tooltip-active` (:813) which suppresses the CSS `::after`/`::before` via `style.css:1232-1234`.
>
> So the two layers of System 1 are mutually exclusive on any given element — CSS by default, JS only when `needsJsTooltip` returns true.
>
> ## C. Who uses `&lt;lib-tip&gt;`, and is it loaded everywhere
> - Consumers: `cma/assets/js/cma-tours.js` (guarded by `typeof LibTip` at :44, and 18 `LibTip.tour(...)`/`LibTip.show(...)` calls, e.g. :190,258,395,553,738,869,977,1262). Storybook demo/docs at `cma/tools/storybook.php:2234-2282, 5387-5441`. No PHP markup emits a literal `&lt;lib-tip&gt;` tag — it's always created programmatically by `LibTip` (`lib-tip.js:653`).
> - Loaded on all bundle pages: yes — `cma/bootstrap.inc:684` (`lib-tip.js`) and `:685` (`cma-tours.js`) are in `cma_js_bundle()`. Note `cma/dashboard.php:961-962` *also* loads both via `cma_script(...)` — a redundant double-load on the dashboard on top of the bundle.
>
> ## D. Who loads `library/tooltip.css` and does it overlap
> - **Nobody.** Zero references to `tooltip.css` anywhere in the repo (grep across php/inc/js/css/html/json returned nothing).
> - Its selectors (`a.tt`, `a.tt:hover span.tooltip/.tt_top/.tt_middle/.tt_bottom`) require `&lt;a class="tt"&gt;…&lt;span class="tooltip"&gt;` markup. No such `a.tt` markup exists (grep for `class="tt"` = none). The one `class="tooltip"` hit (`cma/tools/tools_clearcache.php:940`) is inside `&lt;span class="info-icon"&gt;`, not an `a.tt` ancestor, so tooltip.css cannot match it (that info-icon is a separate small system styled elsewhere).
> - No selector overlap with System 1's `.cma-tooltip` or `[data-tooltip]`, nor with lib-tip's shadow styles. It relies on `images/tooltip.gif` / `tooltip_filler.gif`.
> - Verdict: **dead** — not linked, no matching markup, image-based legacy.
>
> ## E. Do System 1 (engine) and System 2 (lib-tip) ever style the same element
> - **Disjoint trigger mechanisms.** System 1 fires on hover of `[data-tooltip]` elements (delegated `mouseenter`/`mouseleave`, `cma-utils.js:871-887`). System 2 is invoked imperatively via `LibTip.show/tour` and renders its own `&lt;lib-tip&gt;` shadow-DOM overlay pointing at a target selector; it never reads or writes `data-tooltip`. They never style the same element and never compete — one is hover microcopy, the other is click/programmatic coach-marks and multi-step tours.
>
> ---
>
> ## Verdict
>
> - **`library/tooltip.css` (System 3): dead.** Removable with zero behavior change — no loader, no `a.tt` markup, self-contained legacy image tooltip. Safe deletion candidate (also its `images/tooltip.gif`/`tooltip_filler.gif`).
> - **`&lt;lib-tip&gt;` (System 2): keep, not consolidatable.** It serves a genuinely different purpose (tours + dismissible coach tips with server-persisted skip state), disjoint from hover tooltips. No overlap to collapse.
> - **System 1 (CSS `::after` + `cma-tooltip` JS): keep, already a single coordinated system.** The CSS and JS layers are complementary (CSS default, JS only for the `needsJsTooltip` cases) rather than duplicative, and share visual tokens.
>
> Minimal consolidation with no behavior change:
> 1. Delete `library/tooltip.css` (and its two gif assets) — pure dead code.
> 2. Remove the redundant `cma/dashboard.php:961-962` `cma_script` loads of `lib-tip.js`/`cma-tours.js`, since `cma/bootstrap.inc:684-685` already ship them in the bundle (dashboard double-loads them).
>
> Leave Systems 1 and 2 intact; they are neither dead nor duplicative.</result>
> <usage><subagent_tokens>36745</subagent_tokens><tool_uses>10</tool_uses><duration_ms>142155</duration_ms></usage>
> </task-notification>

> <task-notification>
> <task-id>a235d3e15ffbdd561</task-id>
> <tool-use-id>toolu_01TfyTeBGwJNiXACjy5dwWYQ</tool-use-id>
> <output-file>/tmp/claude-1000/-mnt-c-repos-cma-platform/bdfd0d27-ce63-4936-89b3-95bc964247f8/tasks/a235d3e15ffbdd561.output</output-file>
> <status>completed</status>
> <summary>Agent "Verify legacy CMA.tree dormancy" finished</summary>
> <note>A task-notification fires each time this agent stops with no live background children of its own. The user can send it another message and resume it, so the same task-id may notify more than once.</note>
> <result>I have everything I need. Here is my thorough verdict.
>
> ## Summary: CMA.tree IS effectively dead — no runtime emitter exists anywhere. Safe to delete in one coordinated change (JS + tests). Details and exact line ranges below.
>
> ---
>
> ## A. Every hit for gFld / insFld / initializeToElement / aux0 / foldersTree / 'new Folder(' — classified
>
> **Definitions** (inside the `CMA.tree` IIFE, `cma/assets/js/cma.js`):
> - `cma.js:68` — `CMA.tree = (function() {` … `cma.js:486` `})();` — the whole legacy module
> - `cma.js:78` `function Folder(...)`, `cma.js:101` `function Item(...)` — constructors (`new Folder(`/`new Item(` used internally at `:448`, `:452`, `:464`)
> - `cma.js:366` `function initializeToElement(...)`
> - `cma.js:447` `gFld`, `:451` `gLnk`, `:455` `F`, `:459` `D`, `:463` `I`
> - `cma.js:468-485` public API export object
>
> **Global aliases** (`cma/assets/js/cma.js:2017-2043`):
> - `:2019` `Folder`, `:2020` `Item`, `:2021` `gFld`, `:2022` `gLnk`, `:2023` `insFld`, `:2024` `insDoc`, `:2025` `F`, `:2026` `D`, `:2027` `I`, `:2037` `fSaveTree`, `:2038` `fRestoreTree`, `:2039` `initializeDocument`, `:2040` `initializeToElement`, `:2041` `clickOnFolder`, `:2042` `clickOnNode`, `:2043` `hi`
> - `:2028-2036` `fExpandAll`/`fCollapseAll` — **hybrid**: try `#listContent cma-tree` first, fall back to `CMA.tree`. These are the only ones with live external callers (see D).
>
> **Defensive consumers** (`cma/assets/js/form-controller.js`):
> - `:2296-2320` — matches a `&lt;script&gt;` in `data.html` whose body `.includes('gFld')`, then `eval`s it and calls `initializeToElement`
> - `:6874-6926` — matches any `&lt;script&gt;` in `data.html`, checks `typeof gFld !== 'function'`, `eval`s it, calls `initializeToElement`
>
> **Actual emitters: NONE.** No PHP/inc/sql/json/template/XSLT file emits `gFld`, `insFld`, `F(`, `D(`, `I(`-script, `foldersTree`, or `aux0`.
> - `aux0` and `foldersTree` (the classic dTree/ftiens4 server-emitter variable names): **zero hits repo-wide.**
> - No `ftiens4.js` or `dtree*` file exists anywhere (the error strings in form-controller reference a file that is already gone).
>
> **Test references** (would break on deletion):
> - `cma/cypress/e2e/core/utility-functions.cy.js:677` asserts `win.CMA.tree` exists
> - `utility-functions.cy.js:842-844` (`gFld`), `:848-850` (`gLnk`), `:854-856` (`insFld`), `:860-862` (`insDoc`)
>
> **Build artifact:** `cma/assets/js/cma.min.js` contains the minified `CMA.tree` — excluded per your instruction and regenerated from `cma.js`, so not a manual deletion target.
>
> ---
>
> ## B. XSLT / legacy server-side tree builders — clean
> - **No XSLT files exist** in the repo at all.
> - `cma/classes/Services/TreeService.php` builds tree data via `buildTreeFromFlat()` (`:602`) returning a nested **array/JSON structure** consumed by the `cma-tree` web component. It emits no `gFld`/`F(`/`D(` script. (The `buildTree*` matches at `:270/436/447/524/576` are image-src closures and the modern JSON builder — unrelated.)
> - `cma/reports.php`, `cma/menurep.inc`, `cma/listTemplates.php`: no `gFld`/`insFld`. Their `&lt;script&gt;` blocks (`reports.php:78`, `listTemplates.php:20/56/104`) define `window.fExpandAll/fCollapseAll` that call the **cma-tree** component (`tree.expandAll()`), not `CMA.tree`.
> - `library/lib_*.inc`: no tree-script emitters.
> - Archived files `cma/reports_DEPRECATED.php` and `cma/tools_DEPRECATED.php` use the `cma-tree` web component too, and are referenced only in comments/docs — not routed or included.
>
> ## C. templates/ and library/ — clean
> `grep` for `gFld|insFld|initializeToElement|ftiens4|foldersTree` across `templates/`, `cma/templates/`, and `library/` returned **zero hits**.
>
> ## D. The two form-controller defensive branches
> Both live in the `else if (data.html …)` arm, reached only when the list endpoint returns `data.html` (not `data.tree`/a cma-tree). Triggers:
> - `form-controller.js:2298` — fires only if `data.html` contains a `&lt;script&gt;` whose body string-includes `gFld`.
> - `form-controller.js:6874` — fires if `data.html` contains any literal `&lt;script&gt;`, then `eval`s it (relies on global `gFld`/`initializeToElement`).
>
> Because **no server code emits such a script**, both branches are unreachable dead code. If they, `CMA.tree`, and the tree global aliases were all deleted, nothing breaks at runtime: the modern path is the `data.tree`→`cma-tree` branch, and the plain `else { this.listContent.innerHTML = data.html }` simple-HTML branch (`:2322-2324`, `:6927+`) remains. The only caller-visible globals that must be **preserved** are `fExpandAll`/`fCollapseAll` (emitted as `javascript:fExpandAll()`/`fCollapseAll()` buttons at `cma/classes/FormTemplate.php:605-606` and `cma/classes/ToolbarHelper.php:75-76`) — but their bodies already prefer the `cma-tree` component; only the `else { CMA.tree.expandAll() }` fallback needs to be dropped.
>
> ## E. Cookie / state persistence — no shared readers
> - `CMA.tree` persists open-folder state via `lib_storage_set(cookieID, ':'+s, 'tree')` / `lib_storage_get(sCookieID, 'tree')` where `cookieID = 'tree_' + formId` (`cma.js:301,313`). The **only reader** of this `'tree'`-typed store is `CMA.tree` itself (`cma.js:301`).
> - The modern `cma/webcomponents/cma-tree.js` uses a **separate, incompatible** store: raw `localStorage.getItem('tree_' + this._storageKey)` holding a JSON array (`cma-tree.js:291,303`). Different serialization, different access path — no overlap.
> - Deleting `CMA.tree` orphans only its own legacy entries; nothing else reads them.
>
> ---
>
> ## Verdict: safe to delete in one change
>
> Deletion list (do not edit — for your reference):
>
> 1. `cma/assets/js/cma.js:62-486` — the `CMA.tree` module (deprecation comment + IIFE).
> 2. `cma/assets/js/cma.js:2017-2027` and `2037-2043` — legacy tree global aliases (`Folder, Item, gFld, gLnk, insFld, insDoc, F, D, I, fSaveTree, fRestoreTree, initializeDocument, initializeToElement, clickOnFolder, clickOnNode, hi`).
> 3. `cma/assets/js/cma.js:2028-2036` — **keep** `fExpandAll`/`fCollapseAll` but remove their `else { CMA.tree.expandAll()/collapseAll() }` fallback (component-only).
> 4. `cma/assets/js/form-controller.js:2295-2321` — collapse the `gFld`-script sub-branch into the plain simple-HTML `else` (keep `:2322-2324`).
> 5. `cma/assets/js/form-controller.js:6874-6926` — collapse the `&lt;script&gt;`-eval branch into the plain simple-HTML `else` (keep `:6927+`).
> 6. Tests: `cma/cypress/e2e/core/utility-functions.cy.js:677` (drop `CMA.tree` assertion) and the `Tree Functions` describe block `:841-865` (`gFld`/`gLnk`/`insFld`/`insDoc`).
> 7. Regenerate `cma/assets/js/cma.min.js` from `cma.js` after the change (build artifact).
>
> Caveat/coordination note: this is safe **because** no PHP emits a `gFld`/`F(`/`D(` inline tree script today — the deletion relies on that invariant. Items 1-6 must land together so the removed `CMA.tree` symbol is not referenced by the surviving `fExpandAll`/`fCollapseAll` fallback or the tests. `fExpandAll`/`fCollapseAll` themselves must survive (live toolbar callers). No server-side, XSLT, template, or cookie dependency blocks the deletion.</result>
> <usage><subagent_tokens>33744</subagent_tokens><tool_uses>19</tool_uses><duration_ms>217333</duration_ms></usage>
> </task-notification>

> \1 : wire the cma-sortlist please, yes please gate it 3 ; where can i see that? 4: leave them, but possibly move them to a date specific file?

> maijn rino repo holds the original asp code, there the adding of attachments should be gated by an empty test, and make sure the new code has a simular gate; 
>
>
> ● Update(/mnt/c/dev/kl/library/classes/class_mailer.inc)
> Added 5 lines, removed 2 lines
>             '
>             if strAttachment<>"" then
>               arrAttachments = lib_array_split( replace(strAttachment, " ; ", ";"), ";")
>               for teller = 0 to ubound(arrAttachments)
>                 .addAttachment (arrAttachments(teller))
>               for teller = 0 to ubound(arrAttachments)
>                 ' skip missing files and directory paths: CDO raises 0x80070005 (access denied) on those
>                 if lib_FileExists( arrAttachments(teller) ) then
>                   .addAttachment (arrAttachments(teller))
>                 end if
>               next
>             end if

> ehhm, three mailers?  helpers/src/Email.php, converter/templates/library/Email.php, and site/app/library/Email.php ?? Can we not do that? Use 1 class and stub the others of needed, but i prefer to have them deleted

> a : yes but readonly is also allowed, not just FULL access

> continue

> look at the bootstrap/autoloader : are there more duplicated area's?

> continue

> please fix them by priority

> Note I left ConfigFormService::getSubformListData's stricter isAdmin() check is too strict, normal users should also have access.

> PHP Warning:  require(C:\lab\ai_conversion\site\vendor\composer/../symfony/polyfill-mbstring/bootstrap.php): Failed to open stream: No such file or directory in C:\lab\ai_conversion\site\vendor\composer\autoload_real.php on line 41

> I am genuily concerned about error handling, pages like opleidingen / agenda fail silently with no trace of errors, javascript or otherwise, we need a broader (role-based) test-script touching more area's of the system. An active deelnemer, a docent, a praktijkopleider and a contactpersoon inventarisatie, and a rino medewerker and just walk through all links in the menu and pages. Deep think in to how we can achieve a better test coverage with this in mind. You may access the database in search of other roles.

> <task-notification>
> <task-id>a75aa07292a300dc7</task-id>
> <tool-use-id>toolu_014h3NFQ8aXmGiWC8vZVnb4t</tool-use-id>
> <output-file>/tmp/claude-1000/-mnt-c-repos-cma-platform/bdfd0d27-ce63-4936-89b3-95bc964247f8/tasks/a75aa07292a300dc7.output</output-file>
> <status>completed</status>
> <summary>Agent "Map mijnRINO auth/roles/menu" finished</summary>
> <note>A task-notification fires each time this agent stops with no live background children of its own. The user can send it another message and resume it, so the same task-id may notify more than once.</note>
> <result>I now have a complete picture. Here is the structured report.
>
> ---
>
> # mijnRINO Front-End — Role-Based Login &amp; Menu Map
>
> Scope note: the login engine lives in `module/login/class_login.inc` (platform-adjacent, but `require_once`'d directly by `index.php`, `login/index.php`, and every entry page, so it's in-scope). The `Request`/`Cookie` helpers resolve to `vendor/stenversonline/platform/src/helpers/`.
>
> ## A. LOGIN MECHANICS
>
> **Password login is alive and is the default in this environment** (SSO is optional and currently OFF).
>
> - Entry point / form renderer: `Login::ShowForm()` — `module/login/class_login.inc:414-451`. Rendered by `index.php:180` when not logged in and `force_sso` is false.
> - Login gate decision: `index.php:174-181`
>   - `if (OPT_LIVE &amp;&amp; ... )` → redirect to `/`. **`OPT_LIVE` is hard-defined `false`** (`utils.inc:23,34,59`), so this branch never fires.
>   - `elseif (Application::get('force_sso'))` → SSO. **`force_sso = false`** (`app.php:121`).
>   - `else` → `Login::ShowForm()` (password form).
> - **POST target:** the form (`class_login.inc:417`) has `action=""` when there's no ReturnURL, i.e. it posts to the current URL — normally `/` which is served by `index.php`. Handler: `index.php:107-119` (`if (Request::post('formactie')=='login') Login::TryLogin('')`).
> - **POST fields** (form field names, `class_login.inc:417-431`):
>   - `formactie` = `login` (hidden, required trigger — `index.php:107`)
>   - `login` = e-mail address (text; DB match is `login` OR `email_primair`)
>   - `pwd` = password
>   - `ReturnURL` = optional post-login redirect (hidden)
>   - `SupportInfo` = decoy textarea (ignore)
>   - Note: `Request::post()` is **case-insensitive** (`vendor/stenversonline/platform/src/helpers/Request.php:70-85`), so the form's `login`/`pwd` correctly satisfy `TryLogin`'s reads of `Request::post('Login')` / `Request::post('Pwd')` at `class_login.inc:341-342`.
> - **Password verification** (`class_login.inc:343`):
>   ```
>   SELECT ... FROM tblLogins
>   WHERE (actief=true)
>     AND (login=&lt;user&gt; OR email_primair=&lt;user&gt;)
>     AND password_enc = SHA256(&lt;pwd&gt;)
>   ```
>   Hash = `Encryption::sha256($password)` compared to column `tblLogins.password_enc`. Account must be `actief=true`. (Fields: `strFieldLoginName='login'`, `strFieldPwdName='password_enc'`, `class_login.inc:157-158`.)
> - **What success sets (COOKIES, not PHP sessions)** — `TryLogin`, `class_login.inc:352-377`:
>   - `USID` = login ID (`COOKIE_ID`)
>   - `CHK` = guid (`COOKIE_GUID`) — the id/guid pair is the auth check (`isLoggedIn()`, `class_login.inc:531-561`)
>   - `UNAME` = display name, `UROEPNAAM`, `UCID` = PromptusID, `email`, optional `login_naam`
>   - Then `Login_GetRelated()` (the post-login hook, `strPostLogin`, `class_login.inc:111`) computes roles/opleidingen and writes the role cookies/app-vars (see section B).
> - **CSRF:** none. The login form carries no CSRF/nonce token (`class_login.inc:414-451`). No token is validated in `index.php`.
> - **Cookie mechanics:** `Login::isLoggedIn()` re-seeds `USID`/`CHK` from cookies and validates `SELECT guid FROM tblLogins WHERE id=USID` == `CHK`. To script a session you can either POST the form and keep cookies, or set `USID`+`CHK` cookies directly to a valid id/guid pair.
>
> **SSO** (`sso_tester.html`, `sso.php`, `sso_login.php`, `sso_callback.php`, `sso_shared.inc`):
> - `sso_tester.html` posts name/pwd to `sso.php`, but `sso.php` ignores them and just redirects to an external `sso_handshake.php` — it's a legacy/stub. Real SSO is OAuth: `sso_login.php` → IdP → `sso_callback.php`.
> - `sso_callback.php:22` → `Sso_callback-&gt;handleCallback()` → `sso_shared.inc:226` calls `$this-&gt;objUserLogin-&gt;LoginByEmailAddress($tokenUsername)` (`class_login.inc:203-225`): looks up `tblLogins WHERE actief=true AND (email_primair=... OR login=...)`, then `TryLogin($userID)`. So SSO logs in by e-mail with **no password**. If unknown → redirect to `profile_url`.
> - Password login remains fully possible because `force_sso=false`. For a test harness, **use the password form** — simplest and self-contained.
>
> **Impersonation ("login as" / switch user):** YES, two distinct mechanisms:
> 1. **Assume identity** (staff impersonation): `index.php:76` `$intLoginAs = Request::queryIntAndGuid('assumeidentity')` → `index.php:79-102` calls `Login::LoginAs($guid)` (`class_login.inc:169-198`). Trigger URL: `index.php?assumeidentity=&lt;tblLogins.guid&gt;`. Link is rendered for staff at `ajax_profiel_popup.inc:150` ("Login als.."). Original identity saved in cookie `original_login` (`COOKIE_ORIGINAL_LOGIN`); "Terug naar eigen rol" link at `header.inc:479`. `isLoggedInAs()` / `originalLogin()` at `class_login.inc:566-582`.
> 2. **Role switch** (same person, multiple roles): `index_wissel_rol.inc` + menu form `#menurol` posting `gewensterol` to `/?pageaction=wissel_rol` (`header.inc:439-476`). Sets cookie `GEWENSTE_ROL` (`COOKIE_ROL`) and re-runs `Login_GetRelated()`.
>
> ## B. ROLE MODEL
>
> **One person = one `tblLogins` row; roles are foreign-key links to per-role tables.** A single login can hold multiple roles simultaneously.
>
> Role detection — `Login_GetRelated()`, `class_login.inc:903-1085`. It reads `tblLogins.*` and counts/assigns roles from these FK columns (non-empty = has that role):
>
> | Role | tblLogins FK column | Linked table | USER_TYPE constant (value) | Runtime ID global |
> |---|---|---|---|---|
> | deelnemer (participant) | `fkDeelnemer` | `tblDeelnemers` (+ `tblDeelname` for active enrolments) | `USER_TYPE_DEELNEMER` = **1423** | `$deelnemerID` |
> | docent (teacher / hoofd-/jaargroepopleider) | `fkDocent` | `tblDocenten` (+ `tblOpleidingenPerDocent`, flags `blnHoofddocent/blnHoofdopleider`) | `USER_TYPE_DOCENT` = **2224** | `$docentID` |
> | praktijkopleider | `fkPraktijkopleider` | `tblPraktijkOpleiders` | `USER_TYPE_PRAKTIJKOPL` = **7873** | `$praktijkopleiderID` |
> | P-opleider | `fkP_Praktopl` | (P-opleider) | `USER_TYPE_P_PRAKTOPL` = **3455** | `$p_praktoplID` |
> | RINO medewerker / manager (staff/admin) | `fkAssistent` | `tblContactpersonen` (flags `bSuperUser,BIG,BNS,bPlanning,bServicebureau`) | `USER_TYPE_ASSISTENT` = **8312** | `$assistentID` |
> | contactpersoon inventarisatie | `fkSRHForumLid` | `tblSRHForumLeden` | `USER_TYPE_CONTACTP_INV` = **9989** | `$ContInvID` |
> | supervisor | `fkSupervisor` | `tblSupervisoren` | `USER_TYPE_SUPERVISOR` = **8300** | `$SupervisorID` |
> | werkbegeleider | `fkWerkbegeleider` | `tblWerkbegeleiders` | `USER_TYPE_WERKBEGELEIDER` = **8301** | `$WerkbegeleiderID` |
> | contactpersoon (klant) | `fkKlantContactPersoon` | `tblklantcontactpersonen` | `USER_TYPE_KLANTCP` = **9131** | `$klantContactpersoonID` |
>
> Constants defined `class_login.inc:89-107`. The reverse maps (type→FK column, type→table) are `User_GetPersoonsTypeField()` `utils.inc:1860-1888` and `User_GetPersoonsTableName()` `utils.inc:1894+`. Human label map: `Login_GetTypeString()` `utils.inc:566-598`.
>
> **Active/inactive:**
> - Login-level: `tblLogins.actief=true` is required by every login query (`class_login.inc:211,339,343`).
> - Deelnemer enrolment state: driven by `tblDeelname` (`toegelaten IN (1,2)` / `&lt;&gt;3`, `einddatum`, `certificaatdatum`, `bPauze`) — `class_login.inc:979-980`. A paused participant = `tblDeelname.bPauze` → `$bDeelnemerPauze` affects the menu.
>
> **Runtime role variable (the "current" role):**
> - `$LoginType` (global) = the active role's USER_TYPE. Persisted as app-var `login_setting_&lt;USID&gt;_TYPE` (`COOKIE_TYPE`) via `Login::StoreValue` (`class_login.inc:230-237`) and re-hydrated by `Login_InitVars()` (`class_login.inc:1147-1189`, sets `$LoginType` from `COOKIE_TYPE`, and all the per-role `*ID` globals from `login_setting_*` values).
> - Desired role when multi-role: cookie `GEWENSTE_ROL` (`COOKIE_ROL`). `Login_GetRelated` picks the active role as: the sole role if `intRollen==1`, else the one matching `GEWENSTE_ROL`, else defaults (assistent auto-defaults, `class_login.inc:1026-1031`; deelnemer is the fallback default at `index.php:131-132`).
> - Role count: `$UserAantalRollen` (app-var `AANTALROLLEN`). If `&gt;1`, user is sent to the "wissel rol" chooser after login (`index.php:98-99`, `127-128`).
> - Per-page enforcement: `Login::Check()` (`class_login.inc:499-526`, called e.g. `agenda.php:75`) verifies the active role's FK column is still present on the login row, else bounces to `/?ReturnURL=`.
>
> **Doelgroepen:** `Login_GetRelated` also builds a comma list `sDoelgroep` (stored as `COOKIE_DOELGROEPEN`) mixing role types with sub-flags `USER_TYPE_HOOFDOPL(7291)`, `USER_TYPE_JAARGROEPOPL(9912)`, `USER_TYPE_HOOFDDOC(5480)` derived from docent flags (`class_login.inc:1003-1011`).
>
> ## C. MENU (per-role, front-end)
>
> - Builder: `get_menu($bExternal)` in **`header.inc:249`** (emitted at `header.inc:231` inside `&lt;ul id="menu"&gt;`). Helpers `WriteInMenu()` (`header.inc:164`), `WriteSubItem()` (`header.inc:~195`), `AddRapportItem()`, `BuildRapportageItems()`. This is the front-end menu — unrelated to CMA's `menu.json`.
> - **Source: hardcoded per-role in PHP**, gated by `$LoginType == USER_TYPE_*` plus data probes (`check_toegang()`, `AgendaZichtbaar()`, `CheckToonDossioma()`, `Nieuws_GetAllowed()`, `AlgemeneInfo_GetAllowed()`, feature flags `feature_planning/feature_taken/feature_inventarisatie`, etc.). Result is cached per `menu_cache_id = 'opl_menu_..._&lt;USID&gt;_&lt;LoginType&gt;_&lt;date&gt;_...'` (`header.inc:265`).
> - Statically enumerable base items and their role gates:
>   - **Dashboard** — all logged-in (`header.inc:326`).
>   - **Agenda** — if `AgendaZichtbaar()` (`header.inc:327-328`).
>   - **Planning** (+ Status submenu) — `USER_TYPE_ASSISTENT` AND `$UserPlanning` AND `feature_planning` (`header.inc:331-332`).
>   - **Taken** — `feature_taken` &amp;&amp; `check_toegang('taken')` &amp;&amp; not deelnemer &amp;&amp; no active opleiding filter (`header.inc:334-335`).
>   - **opleiding(en)** — any role with `$UserOpleidingen` set, not a temp group (`header.inc:342`).
>   - **deelnemers** — everyone with opleidingen except plain deelnemers (`header.inc:343-344`).
>   - Sub-items (Toetsen, IOP, Competentieprofiel, Verslagen, Verklaringen, Dossioma('s), CGO portfolio) — gated by role + `CheckToon*()` (`header.inc:346-398`; note this whole block is wrapped in `if (false)` currently, i.e. disabled).
>   - **Inventarisatie** — `USER_TYPE_CONTACTP_INV` &amp;&amp; `feature_inventarisatie` (`header.inc:402-403`).
>   - **Nieuws** — if `Nieuws_GetAllowed()` (`header.inc:410-412`).
>   - **Algemene info** — DB-driven from `tblAlgemeneInfo` (`header.inc:414-432`).
>   - **Rapportages** submenu — assembled by `BuildRapportageItems()`; individual items gated (e.g. Rapportage dispensaties: assistent/docent/PO/P-opleider; Vrijstellingen &amp; Voordrachten PO: assistent / beoordelend hoofdopleider) — `header.inc:283-306,434-436`.
>   - **Profile block ("me")** — Mijn profiel / Registraties / Interesselijst / Facturen / Accreditaties / (Deelnemers delegeren if `check_toegang('delegeren')`) / Berichten (`header.inc:439-447`).
>   - **Rol switcher** — only if `$UserAantalRollen &gt; 1`; one `&lt;li&gt;` per held role, posting `gewensterol` to `/?pageaction=wissel_rol` (`header.inc:448-477`). Labels: Deelnemer, "Docent of hoofd-/jaargroepopleider", Praktijkopleider, P-opleider, Contactpersoon inventarisatie, Supervisor, Werkbegeleider, Contactpersoon, "Opleidingsmedewerker / Manager".
>   - **"Login als.." / "Terug naar eigen rol"** — impersonation links for staff (`header.inc:478-479`).
> - The "Algemene info-links" sidebar (`$sAlgInfoLinks`) adds role-specific quick links: PO/contactpersoon-inv get "Praktijkopleider voordragen", "Dispensatie aanvragen", "Inventarisatie en voordracht kandidaten" (`header.inc:286-289`); deelnemer gets "Vrijstelling aanvragen" (`header.inc:307-308`).
>
> ## D. TEST USERS / DEV BYPASS
>
> - **Environment is hard-pinned to dev:** `app.php:123` `omgeving='O'` → `local=true`, `test=true` (`app.php:125-126,138-139`), `force_sso=false` (`app.php:121`), `cma_sso_enabled='true'` but unused for gating. `global.asa.php:14` marks `development=true` for L/O/T. This is why the **password form is shown and `OPT_LIVE=false`**, so no live-redirect and no forced SSO.
> - **No dev auto-login / hardcoded credential bypass exists.** There is no "log in as X without password" shortcut other than the `assumeidentity` impersonation flow (which itself requires you to already be an authenticated staff/assistent user, since it saves `COOKIE_ORIGINAL_LOGIN` only when the prior role was assistent — `index.php:84-86`). Note `LoginAs()` itself does not re-check the caller's rights, but you reach it only via a logged-in `index.php` request.
> - **No fixtures / seed accounts** in the repo. The endpoint tester `tests/test_frontend_endpoints.php` does **not** authenticate — it GETs pages and ignores auth gates (`forcelogin=J`). `tests/test_frontend_functions.php` and `tests/test_all_endpoints.php` likewise don't log in.
> - **Anonymized DB pattern (useful for harness):** `db/anonimiseer.php:140` rewrites every `tblLogins` row to `login='login&lt;ID&gt;@testmail.nl'` and `email_primair='login&lt;ID&gt;@testmail.nl'`. So in an anonymized dev copy, the login e-mail is deterministically `login&lt;tblLogins.ID&gt;@testmail.nl`. **Passwords (`password_enc`) are NOT reset by the anonymizer**, so you still need a known password per account (or set `password_enc` = `SHA256(known)` directly in `db/pdodomain.mdb` → `tblLogins`). Env file: `.env.local` points `CONN_USERS_PATH=/db/CMAusers.mdb`; the primary domain DB is `db/pdodomain.mdb` (`global.asa.php:80`), which holds `tblLogins`.
> - Cypress is wired (`.env.local:89-92` `NODEJS_PATH`, `CYPRESS_CACHE_FOLDER`) but no spec directory was found under the site root.
>
> **Recommended harness login recipe (per role):**
> 1. Pick a `tblLogins` row that has the desired FK set (`fkDeelnemer`/`fkDocent`/`fkPraktijkopleider`/`fkP_Praktopl`/`fkAssistent`/`fkSRHForumLid`) and `actief=true`.
> 2. Ensure a known password: `UPDATE tblLogins SET password_enc = &lt;SHA256(pw)&gt; WHERE ID=...` (or read existing).
> 3. POST to `/` (index.php): `formactie=login`, `login=&lt;email_primair&gt;`, `pwd=&lt;pw&gt;`; keep cookies (`USID`, `CHK`, `UNAME`, `UCID`).
> 4. If the account holds &gt;1 role, the app redirects to `index.php?pageaction=wissel_rol`; select the role by POSTing `gewensterol=&lt;USER_TYPE_*&gt;` to `/?pageaction=wissel_rol` (sets `GEWENSTE_ROL` cookie), which fixes `$LoginType`.
> 5. Walk the menu from `&lt;ul id="menu"&gt;` rendered by `get_menu()`.
>
> ## E. PAGE INVENTORY — role-specific entry points
>
> Routing is mostly `index.php?pageaction=&lt;x&gt;` (dispatch table `index.php:184+`), plus standalone root scripts.
>
> - **index.php** — dashboard/homepage + login + role switch + `pageaction` router. Notable actions: `opleidingen`, `deelnemers`, `taken`, `berichten`, `eigen_gegevens`, `delegeren`, `wissel_rol`, `inventarisatie`, `nieuws`, `info`, `zoek`, `afspraak`, and the `rapport_*` / `rapportage_*` / `evaluatie_*` families (see grep list above). Rapport/afspraak actions are gated to `USER_TYPE_ASSISTENT` (staff), a few also allow docent-hoofdopleider (`index.php:186-199`).
> - **agenda.php** — all roles; content branches by `$LoginType` (deelnemer/docent/assistent) — role gate `Login::Check()` at `agenda.php:75`; assistent gets wider scope (`agenda.php:56,870`).
> - **opleiding.php** — opleiding detail; `pageaction=voortgang|toetsen|verslagen|verklaringen|dossioma|iop|competenties|cgoportfolio|vrijstellingen|delegeren`. Forces login via `Login::ForceLoginAndReturn()` (`opleiding.php:70`).
> - **inventarisatie/** (`inventarisatie/index.php`, `inventarisatie.inc`) — contactpersoon-inventarisatie (`USER_TYPE_CONTACTP_INV`) entry (`header.inc:402-403`).
> - **planningsoverzicht/**, `plannings_status` — assistent + planning feature.
> - **Praktijkopleider / voordracht flows:** `formulier_voordracht_praktijkopleider.php` (PO/contactpersoon-inv; calls its own `Login::TryLogin`), `formulier_dispensatie.php` (dispensatie aanvraag), `deelnemer_selecteer_compensatie.php`, `deelnemer_pauze.php`.
> - **Docent-oriented:** `presentielijst.php`, `dig_presentie.php`, `planning_docent_verstuur.php`, `opleiding_draaiboek_*`, `verslag.php`, `eval_resultaten.php`.
> - **Deelnemer-oriented:** `opleiding.php?pageaction=dossioma|iop|verslagen`, `sollicitatie.php`, `verklaring.php`, `ziekmelding.php`, `bericht.php`.
> - **Auth/support pages:** `login/index.php` (redirects to `/`), `login_email.php` (unconfirmed-email notice), `login_forgotten.php`/`Login::ForgotPassword`, `login_reset.php`/`bevestig_email.php` (password reset via `guid`), `login_change.php`, `logout.php` (`Login::Logout`, clears all cookies), `sso.php`/`sso_login.php`/`sso_callback.php`.
>
> Key files to cite for the harness: `module/login/class_login.inc` (engine, constants, role resolution), `index.php` (login POST handler + impersonation + router), `header.inc` (`get_menu`), `utils.inc` (`Login_GetTypeString`, `User_GetPersoonsTypeField/TableName`), `app.php` + `global.asa.php` (env pinned to `O`, `force_sso=false`), `vendor/stenversonline/platform/src/helpers/Request.php` (case-insensitive POST), `db/anonimiseer.php` (test-email pattern).</result>
> <usage><subagent_tokens>113788</subagent_tokens><tool_uses>31</tool_uses><duration_ms>277482</duration_ms></usage>
> </task-notification>

> Okay, this is a pivot moment. We are leaving the converter. We are moving directly into the mijn rino php version. So we can target bugs more directlty. Can you do that?

> the /module path is yours as well, you may alter that, it is site-specific

> site stopped

> i halted the toepassingsgroep as well, can you move it now?

> i copied/moved it to  /mnt/c/repos/adam/mijnrino_php, and changed iis settings shall we continue?

> i put you in a /loop didn’t I, and you stop?! Continue

## 2026-07-17

> continue

> the login page still has the text '
> Let op: log hier in als je deelneemt aan, of betrokken bent bij, één van de pilot-groepen: GZ2024-B, GZ2024-J, GZ2024-R, GZ2024-V, GZ2024-W.
> Zo nee, ga dan naar profiel.rino.nl om op de vertrouwde manier in te loggen.'
>
> Can you change that into: 
> Log hier in als je bij RINO Amsterdam een BIG opleiding volgt. 
> Zo nee, ga dan naar profiel.rino.nl om in te loggen.

> 1 please do the rewrite

> i am running version 1.29.44 and still i see records 1-2000 van 2174 (laden...)
> This is a re-occuring bug on desktop that you annot seem to solve. think hard to solve this

> first of all: harden the modules as well for double includes, did you harden the cma library files against it?

> remove the Verkort (KNP) checkbox in http://172.30.208.1:8090/index.php?pageaction=evaluatie_curcie and see why datepicker does not work. And i am missing the javascript error handling, the panel does not show.

> the planning status and planning_overzicht refer to localhost in the menu, let them just use /planning and /planningsoverzicht

> nice: [09:17:51] JS: Uncaught SyntaxError: Unexpected token ';'
>   at http://172.30.208.1:8090/agenda.php:366

> agenda.php delivers: <script>;
> ;
> var nCalendarOffset = 0 - (1000 * 7200);
> var currentEvent;
> var DATEFORMAT = "DD MM YYYY";

> yes please go after all echo '; instances, it might explain a lot

> okay, but how do we handle the sweep then? We need to fix it. Do you want me to halt all other agents?

> <task-notification>
> <task-id>a59723278bfd67dba</task-id>
> <tool-use-id>toolu_01Bmc6VSPtRgrA7oDpbbcvse</tool-use-id>
> <output-file>/tmp/claude-1000/-mnt-c-repos-cma-platform/bdfd0d27-ce63-4936-89b3-95bc964247f8/tasks/a59723278bfd67dba.output</output-file>
> <status>completed</status>
> <summary>Agent "Sweep site for leaked-semicolon echo bug" finished</summary>
> <note>A task-notification fires each time this agent stops with no live background children of its own. The user can send it another message and resume it, so the same task-id may notify more than once.</note>
> <result>All 78 edited files pass `php -l`, and the protected `cma/` directory is untouched.
>
> ## Summary
>
> Fixed the systematic converter bug (stray leading `;` leaking into emitted JS/HTML/CSS) across the mijnRINO PHP site at `/mnt/c/repos/adam/mijnrino_php`.
>
> **Total: 309 fixes across 78 files.** All changed files pass `php -l`.
>
> ### Fix categories
>
> | Category | Count | Pattern | Fix |
> |---|---|---|---|
> | A — leading `;` on a continuing echo | 178 | `echo ';` / `echo ";` (quote, `;`, EOL — string body continues on next lines) | drop the `;` → `echo '` |
> | B — bare `&lt;script&gt;;` | 58 | `echo '…&lt;script&gt;;` | `&lt;script&gt;;` → `&lt;script&gt;` |
> | Tag-adjacent (scope extension) | 73 | `&lt;style&gt;;`, `&lt;STYLE&gt;;`, `&lt;script type="text/javascript"&gt;;`, `&lt;style media=print&gt;;` | remove `;` right after the opening tag |
>
> ### Verification performed
> - **No dangerous incomplete-previous-expression cases.** I dumped prev/curr/next context for all 243 pattern-A/B hits and scanned for a preceding echo ending in `=`,`+`,`(`,`&amp;&amp;`, etc. The only flagged prev-lines were pattern-B `&lt;script&gt;;` cases whose previous line ends in a closing tag (`&lt;/script&gt;`, `&lt;/div&gt;`, `&lt;/title&gt;`) or a PHP assignment — all safe. No pattern-A hit had an incomplete preceding JS expression.
> - **No SQL false positives.** The three "SQL-looking" next-lines were jQuery `$('select')`, not SQL.
> - **No complete-string false positives.** The grep anchors on `;` at end-of-line after the opening quote, so legitimate `echo ';'` / `echo ";"` single-semicolon outputs never matched and were left alone. Mid-string JS terminators (`echo 'var x=1;`) were untouched.
> - Re-grepping after edits: zero remaining target hits outside protected dirs.
>
> ### Important scope note
> My initial exclusion filter (`grep -vE '/(vendor|cma|node_modules)/'`) failed to exclude the top-level `cma/` directory because grep emitted paths without a leading `./`. The first fix pass therefore edited 7 files under `cma/` (`html_edit_cell.php`, `html_edit_row.php`, `login.php`, `reportdetails.php`, `template_edit.php`, `wizard.php`, `wizards/file-pages.php`). I caught this immediately and reverted all 7 via `git checkout`; `git status` confirms **no `cma/` modifications remain**. Those 7 files still contain the same bug but were left untouched per the instruction to not edit `cma/`. All later passes correctly excluded `cma/`.
>
> ### Files touched (78)
> Notable high-count files: `upload.php` (47), `verslag.php` (14), `cgo_document.php` / `inventarisatie/excel.php` / `opleiding_voordracht.inc` (12 each), `opleiding_vrijstelling.inc` (11), `inventarisatie/rapport.php` (10), `opleiding_draaiboek_wijzigroosteritem.php` / `opleiding_kbts_poging.php` / `planning_wijzig.php` (9 each). Full per-file breakdown is available; the change logs are in the scratchpad (`changes.txt`, `changes2.txt`).
>
> The bug was indeed widespread — 309 leaked semicolons across 78 pages, most inside `&lt;script&gt;`/`&lt;style&gt;` blocks where a leading `;` between object-literal properties would throw `Uncaught SyntaxError` and silently kill that page's JavaScript.</result>
> <usage><subagent_tokens>59539</subagent_tokens><tool_uses>22</tool_uses><duration_ms>548549</duration_ms></usage>
> </task-notification>

> the  opleidingen.asp is still empty, since it the starting point of many complicated pages you have not even seen, let's focus on that first

> the wissel rol does nothing, the role is never changed. And rename the button Ga verder to Wissel rol

> http://172.30.208.1:8090/formulier_voordracht_praktijkopleider.php

>  http://172.30.208.1:8090/formulier_voordracht_praktijkopleider.php shows an enormeous pile of garbage \n\n and the layout is all gone. It also has these javascript error: [10:21:04] JS: Uncaught SyntaxError: Invalid or unexpected token at http://172.30.208.1:8090/formulier_voordracht_praktijkopleider.php:61
>  
> add a rule in web.config to map /berichten to '/index.php?pageaction=berichten and delete the directory berichten
> Simular with /taakoveraicht/but that also has parameters that need to be parsed: in php Response::redirect('/index.php?pageaction=taken&taak=' . Request::query('taak', '')); Then remove the taakoverzicht folder. 
>
>
> move the css files – including cma.css - in \css to assets\css and update all references to it , if they are platform related, create a migration to perform the action for other consumers to use it as well.. Make sure you only copy css files  that are actually used. Copy unused ones to /Deprecated_ Change paths inside the css files to reflect changed paths. 
>
> move the font files in \fonts to assets\ fonts and update all references to it , if they are platform related, create a migration to perform the action for other consumers to use it as well.. Make sure you only copy fonts  that are actually used. Copy unused ones to /Deprecated_
>
> move the image files in \images\interface to assets\ images and update all references to it , if they are platform related, create a migration to perform the action for other consumers to use it as well.. Make sure you only copy images that are actually used.
>
> The settings folder, can we move that to data? And can we move all databases to data too? And update databases.json to reflect this change. Preferably through a migration for other consumers to use it as well.
>
> The uploads folder needs to be empty in GitHub only the folders need to be created (with .gitkeep?)
>
> The root has  _probe*.php ,   audit_*,  \upload.php , upload_dummy.html , fix_* , .platform-manifest.json, are they still needed?
>
> Can we introduce a /src directory with the following structure: 
> -    Src\ajax  : alle ajax processing
>
> Please advice on how we can clean up the root folder and make sensible sections inside /src , like forms/menu/etc? 

> continue

> i moved draaiboek-timeline.css to assets/css

> draaiboek-timeline.css → assets/css/: index_opleidingen.inc:221 and opleiding_draaiboek_digitaal.inc:859 : is n't that a duplicate?

> the form title is Toetsen_per_deelnemer toevoegen but it is opened from the tab called Resultaten per deelnemer. Can we make sure those titles are synced? I would expect Resultaat per deelnemer in the detailform

> the rapport dispensaties, to whom is that shown?

> can you check these 404's? /cma/assets/icons/ico-moodle.svg
> 4
> /cma/opcache_reset.php
> 1
> /cma/convert.php , i think we should move custom icons to site specific /assets/icons, agreed?

> /images/interface/cma_logo.svg , can we move that to /assets/images

> okay, can we think of a structured way to prevent this, are there standard methods to fix this?

> Yes please let's start there, can i expand it to report groups? On the homepage there are groups of reports as well and let's guard the same type of items at once. Forms later, reports now.

> okay, let's retire  cma/tools/config/app.json

> the original asp had the general.js in header.inc, why is that lost?

>   GET http://172.30.208.1:8090/agenda.php?events=Y&start=2026-07-13T00%3A00%3A00&end=2026-07-20T00%3A00%3A00&_=1784296264000 500 (Internal Server Error) : does not appear on the error console?

> GET http://172.30.208.1:8090/ajax_profiel_popup.php?preview=J&id=100 500 (Internal Server Error)

>  
>  GET http://172.30.208.1:8090/ajax_profiel_popup.php?preview=J&id=100 500 (Internal Server Error) and  
>  GET http://172.30.208.1:8090/agenda.php?events=Y&start=2026-07-13T00%3A00%3A00&end=2026-07-20T00%3A00%3A00&_=1784296264000 500 (Internal Server Error)

> on mijn rino i still see 4 files in app/library

> [16:07:38] JS: Uncaught ReferenceError: lib_readCookie is not defined
>   at http://172.30.208.1:8090/general.js?v=1:544 
>
> that function resided in library.js

> Agenda.php, selecting a groups does not stick, it reverts to Mijn groepen

> those 4 , should they not be in the library ?

> the converter is in /mnt/c/lab/ai_converter

> you keep showing the list:  
>
>   4 tasks (3 done, 1 in progress, 0 open)
>   ✔ Fix Undefined constant XML_SNIPPET_SETTING_RESOLVE_FIELDS (opleidingen list)
>   ✔ Fix Arr::splitAlways null on formulier_voordracht_praktijkopleider.php
>   ✔ Fix vrijstellingen report SQL construction (+ vs &amp; concatenator)
>   ◼ Fix presentielijst.php + assistent report SQL syntax errors
>
> Thanks for the feedback!
> (Optional) Press [1] to tell us what went wrong · /feedback
> is that still uo-to-date? We still have open threads, like you pointed out i don't see

> about the errorhandler: make sure it breaks out of any div/table it might be shown in. So an absolute positioned div is key.

>
> Selecteer een record uit de linker lijst om te wijzigen, skip the icon and change the button from Toevoegen to Voeg [singular description] toe

> ck-editor still not functioning, see if all required resources are still in place after we moveed them eround, that is probably the cause

> ck-editor still not functioning, see if all required resources are still in place after we moveed them eround, that is probably the cause. I am on version .59, not 60, but composer install did not dive me a newer version?!

> /loop until no action points are there, STOP AT NOTHING! !

> /loop until no action points are there, STOP AT NOTHING! !

> /loop until no action points are there, STOP AT NOTHING! !

> /loop until no action points are there, STOP AT NOTHING! !

> /loop until no action points are there, STOP AT NOTHING! !

> /frontend-design

> /loop continue with a full test per role and log errors , after that fix the errors one by one

> fuck, just continue please

> do a full sweep in the codebase for select * and fix if possible

> lee an agent do a sweep in platform code
> have another agent. run tests
> and another to fix issues

> rename Deprecated_ to .deprecated
>
> are you still logging prompts to prompts.md ? if not you create a trigger to do so

> okay continue the loop

## 2026-07-18

> continue testing

> <task-notification>
> <task-id>bmwcgf06p</task-id>
> <tool-use-id>toolu_011duiWzuWMoUavhSgqM6CGU</tool-use-id>
> <output-file>/tmp/claude-1000/-mnt-c-repos-cma-platform/bdfd0d27-ce63-4936-89b3-95bc964247f8/tasks/bmwcgf06p.output</output-file>
> <status>completed</status>
> <summary>Background command "Commit + push site lib_html in background" completed (exit code 0)</summary>
> </task-notification>

> <task-notification>
> <task-id>boo7zeuhy</task-id>
> <tool-use-id>toolu_01PTGuMsfhkmCn4inbG7dXE1</tool-use-id>
> <output-file>/tmp/claude-1000/-mnt-c-repos-cma-platform/bdfd0d27-ce63-4936-89b3-95bc964247f8/tasks/boo7zeuhy.output</output-file>
> <status>completed</status>
> <summary>Background command "Commit + push inventarisatie cluster fix" completed (exit code 0)</summary>
> </task-notification>

> yes run that sweep  and note the learnings into the converter project

> can you continue?

> ※ recap: Fixing ASP-conversion bugs in mijnRINO. Just repaired the Carta/STBC REST subsystem and pushed it; a static sweep confirmed the loop and bareword bug classes are cleared. Next: pick up the remaining backlog (#39 REST follow-ups, or the #19/20/21 asset/data/refactor work).
>
>   30 tasks (24 done, 6 open)
>   ◻ Asset moves: fonts + images → assets/ (+ platform migration)
>   ◻ Data consolidation: settings + databases → data/ (+ migration)
>   ◻ /src refactor: ajax pilot
>   ◻ Dynamic-table SELECT * memo reorder (platform, deferred)
>   ◻ Draaiboek PDF-export (WriteTimeline) path still broken
>    … +1 pending, 24 completed
>  
>
> can you show the actual open points and explain why you are stoppen since I put you in a loop until 100% succes?

> i thought you worked on errorhandler to create a popup, but u still get the same error 'Error in in C:\repos\adam\mijnrino_php\class_calendars.inc on line 303
> Call to a member function RenderCalendar() in null' on the same way

> well get tonit then, 3 actionable items!

> <task-notification>
> <task-id>bsl5yc025</task-id>
> <tool-use-id>toolu_01XHTgNhhcBJLVHWBe7RkJcP</tool-use-id>
> <output-file>/tmp/claude-1000/-mnt-c-repos-cma-platform/bdfd0d27-ce63-4936-89b3-95bc964247f8/tasks/bsl5yc025.output</output-file>
> <status>completed</status>
> <summary>Background command "Commit + push the 3 fixes (background)" completed (exit code 0)</summary>
> </task-notification>

> continue with the open tasks and don't mention the others

> <task-notification>
> <task-id>bdjvccz1k</task-id>
> <tool-use-id>toolu_019Dp8eQbya8MDn1SefmvouA</tool-use-id>
> <output-file>/tmp/claude-1000/-mnt-c-repos-cma-platform/bdfd0d27-ce63-4936-89b3-95bc964247f8/tasks/bdjvccz1k.output</output-file>
> <status>completed</status>
> <summary>Background command "Commit + push src/ajax pilot (background)" completed (exit code 0)</summary>
> </task-notification>

> if the ini files are unused delete them and the ditectory

> <task-notification>
> <task-id>b0kty2n5k</task-id>
> <tool-use-id>toolu_0188wXz5sCjeo3QF7S9H4qRJ</tool-use-id>
> <output-file>/tmp/claude-1000/-mnt-c-repos-cma-platform/bdfd0d27-ce63-4936-89b3-95bc964247f8/tasks/b0kty2n5k.output</output-file>
> <status>completed</status>
> <summary>Background command "Delete unused ini files + dir" completed (exit code 0)</summary>
> </task-notification>

> lets continue the src directory and move as much from the root as possible

> okay, what is the status?

> /?pageAction=opleidingen&opleidingid=17&toets=23,17 ontdekt
> HTTP 500
> error page: syntax error, unexpected token &quot;catch&quot;
> PHP_ERROR marker: [PHP_ERROR] Type: ParseError | Message: syntax error, unexpected token "catch" | File: C:\repos\adam\mijnrino_php\src\opleiding\opleiding_voortgang.inc | Line: 265

> /?pageAction=deelnemers&deelnameID=2332 ontdekt
> HTTP 500
> error page: Database query failed: Native ODBC error: [Microsoft][ODBC Microsoft Access-stuurprogramma] Syntaxisfout (operator ontbreekt) in query-expre
> PHP_ERROR marker: [PHP_ERROR] Type: Exception | Message: Database query failed: Native ODBC error:

> continue

> <task-notification>
> <task-id>bkeqx2exk</task-id>
> <tool-use-id>toolu_017omtkXqozb14kfVmF7tYNx</tool-use-id>
> <output-file>/tmp/claude-1000/-mnt-c-repos-cma-platform/bdfd0d27-ce63-4936-89b3-95bc964247f8/tasks/bkeqx2exk.output</output-file>
> <status>completed</status>
> <summary>Background command "Commit site fixes" completed (exit code 0)</summary>
> </task-notification>

> continue please

> for the converter: <.script> is somehow generated, that should be converted into </script> note that as a but in the converter repo to be fixed

> the gate for max 5 parameters does not work: 
>
> wacht…    -    -        /?pageAction=opleidingen&opleidingid=37&toets=65,359,409,66 ontdekt
> bekijkopslaan
> wacht…    -    -        /?pageAction=opleidingen&opleidingid=37&toets=65,359,409,91 ontdekt
> bekijkopslaan
> wacht…    -    -        /?pageAction=opleidingen&opleidingid=37&toets=65,359,409,129 ontdekt
> bekijkopslaan
> wacht…    -    -        /?pageAction=opleidingen&opleidingid=37&toets=65,359,409,244 ontdekt
> bekijkopslaan
> wacht…    -    -        /?pageAction=opleidingen&opleidingid=37&toets=65,359,409,256 ontdekt
> bekijkopslaan
> wacht…    -    -        /?pageAction=opleidingen&opleidingid=37&toets=65,359,409,281 ontdekt
> bekijkopslaan
> wacht…    -    -        /?pageAction=opleidingen&opleidingid=37&toets=65,359,409,303 ontdekt
> bekijkopslaan
> wacht…    -    -        /?pageAction=opleidingen&opleidingid=37&toets=65,359,409,360 ontdekt
> bekijkopslaan
> wacht…    -    -        /?pageAction=opleidingen&opleidingid=37&toets=65,359,409,1084 ontdekt
> bekijkopslaan
> wacht…    -    -        /?pageAction=opleidingen&opleidingid=37&toets=359,1084 ontdekt
> bekijkopslaan
> wacht…    -    -        /?pageAction=opleidingen&opleidingid=37&toets=65,359,1084,64 ontdekt
> bekijkopslaan
> wacht…    -    -        /?pageAction=opleidingen&opleidingid=37&toets=65,359,1084,85 ontdekt
> bekijkopslaan
> wacht…    -    -        /?pageAction=opleidingen&opleidingid=37&toets=65,359,1084,68 ontdekt
> bekijkopslaan
> wacht…    -    -        /?pageAction=opleidingen&opleidingid=37&toets=65,359,1084,67 ontdekt
> bekijkopslaan
> wacht…    -    -        /?pageAction=opleidingen&opleidingid=37&toets=65,359,1084,66 ontdekt
> bekijkopslaan
> wacht…    -    -        /?pageAction=opleidingen&opleidingid=37&toets=65,359,1084,91 ontdekt
> bekijkopslaan
> wacht…    -    -        /?pageAction=opleidingen&opleidingid=37&toets=65,359,1084,129 ontdekt
> bekijkopslaan
> wacht…    -    -        /?pageAction=opleidingen&opleidingid=37&toets=65,359,1084,244 ontdekt
> bekijkopslaan
> wacht…    -    -        /?pageAction=opleidingen&opleidingid=37&toets=65,359,1084,256 ontdekt
> bekijkopslaan
> wacht…    -    -        /?pageAction=opleidingen&opleidingid=37&toets=65,359,1084,281 ontdekt
> bekijkopslaan
> wacht…    -    -        /?pageAction=opleidingen&opleidingid=37&toets=65,359,1084,303 ontdekt
> bekijkopslaan
> wacht…    -    -        /?pageAction=opleidingen&opleidingid=37&toets=65,359,1084,360 ontdekt
> bekijkopslaan
> wacht…    -    -        /?pageAction=opleidingen&opleidingid=37&toets=65,359,1084,409 ontdekt
> bekijkopslaan
> wacht…    -    -        /?pageAction=opleidingen&opleidingid=37&toets=409,64 ontdekt
> bekijkopslaan
> wacht…    -    -        /?pageAction=opleidingen&opleidingid=37&toets=65,409,64,85 ontdekt
> bekijkopslaan
> wacht…    -    -        /?pageAction=opleidingen&opleidingid=37&toets=65,409,64,68 ontdekt

> <task-notification>
> <task-id>b9911snz1</task-id>
> <tool-use-id>toolu_01MRn1mL1j3BeDGe7DgNTTXm</tool-use-id>
> <output-file>/tmp/claude-1000/-mnt-c-repos-cma-platform/bdfd0d27-ce63-4936-89b3-95bc964247f8/tasks/b9911snz1.output</output-file>
> <status>completed</status>
> <summary>Background command "Commit crawler gate fix" completed (exit code 0)</summary>
> </task-notification>

> the error dialog should have class 'container error' and .container.error { background-color: var(--color-error) } to make it stand out
>
> .exception-message,.exception-type { color:#ffffff} header.error h1 { color:#ffffff, font-size:18px } header.error h1::before { display:none}

> <task-notification>
> <task-id>b9pzyl3pa</task-id>
> <tool-use-id>toolu_01Wwpqz2RTDFT3KGCEAHuw6Y</tool-use-id>
> <output-file>/tmp/claude-1000/-mnt-c-repos-cma-platform/bdfd0d27-ce63-4936-89b3-95bc964247f8/tasks/b9pzyl3pa.output</output-file>
> <status>completed</status>
> <summary>Background command "Commit+tag+push 1.29.71" completed (exit code 0)</summary>
> </task-notification>

> Error in in C:\repos\adam\mijnrino_php\class_calendars.inc on line 303
> Call to undefined method Calendar::RenderCalendar()

> <task-notification>
> <task-id>bci7mybau</task-id>
> <tool-use-id>toolu_01NoGuLbMLhxe4k6P42YKiPY</tool-use-id>
> <output-file>/tmp/claude-1000/-mnt-c-repos-cma-platform/bdfd0d27-ce63-4936-89b3-95bc964247f8/tasks/bci7mybau.output</output-file>
> <status>completed</status>
> <summary>Background command "Commit calendar collision fix" completed (exit code 0)</summary>
> </task-notification>

> Error in in C:\repos\adam\mijnrino_php\module\calendar\class_calendar.inc on line 222
> Call to undefined method CalendarDay::TheDate()

> <task-notification>
> <task-id>bstdz0pn9</task-id>
> <tool-use-id>toolu_01WDyv1tSEqm6gykHSApK4vE</tool-use-id>
> <output-file>/tmp/claude-1000/-mnt-c-repos-cma-platform/bdfd0d27-ce63-4936-89b3-95bc964247f8/tasks/bstdz0pn9.output</output-file>
> <status>completed</status>
> <summary>Background command "Commit+tag+push 1.29.72" completed (exit code 0)</summary>
> </task-notification>

> ❯ The migration information is wrong? The errorhandler reports : $_SESSION['_migration_needed']    1
>   $_SESSION['_migration_current']    6.4.0
>   $_SESSION['_migration_target']    9.15.0
>
>   but the migration says [Pasted text #1 +3 lines]
>
>   so that would result in a false positive

> header.error h1 { padding-top:0px} and continue

> <task-notification>
> <task-id>bkrjy8m74</task-id>
> <tool-use-id>toolu_013dSirKTEbwn4uaHvWN4s3q</tool-use-id>
> <output-file>/tmp/claude-1000/-mnt-c-repos-cma-platform/bdfd0d27-ce63-4936-89b3-95bc964247f8/tasks/bkrjy8m74.output</output-file>
> <status>completed</status>
> <summary>Background command "Commit site _bootstrap.php fix" completed (exit code 0)</summary>
> </task-notification>

> do a full test run and judge the results

> <task-notification>
> <task-id>bwevk0g7n</task-id>
> <tool-use-id>toolu_01NJHE3ckH1HNbukz8j8UFVF</tool-use-id>
> <output-file>/tmp/claude-1000/-mnt-c-repos-cma-platform/bdfd0d27-ce63-4936-89b3-95bc964247f8/tasks/bwevk0g7n.output</output-file>
> <status>completed</status>
> <summary>Background command "Commit RecordSet.find fix" completed (exit code 0)</summary>
> </task-notification>

> the three open tasks: check if they are really open and if so, fix them all

> please continue

## 2026-07-19

> the carta api and webservice should not be used, analyse where they are used and together we will determine the path to go

> 1 lical db, 2 yes they are in use, 3. there is an stb api , stb replaces carta

> erkenningen is not in use, we assume its their responsibility

> <task-notification>
> <task-id>bme2a5txu</task-id>
> <tool-use-id>toolu_01AuarDjvowQ9q3DhQrQXnjc</tool-use-id>
> <output-file>/tmp/claude-1000/-mnt-c-repos-cma-platform/bdfd0d27-ce63-4936-89b3-95bc964247f8/tasks/bme2a5txu.output</output-file>
> <status>completed</status>
> <summary>Background command "Commit Carta→STB step 1" completed (exit code 0)</summary>
> </task-notification>

> 1 confirmed, 2 and 3 keep stubbed for now

> <task-notification>
> <task-id>b127p7nn2</task-id>
> <tool-use-id>toolu_01TFhhaGjk1H9jGq6h4EFiEg</tool-use-id>
> <output-file>/tmp/claude-1000/-mnt-c-repos-cma-platform/bdfd0d27-ce63-4936-89b3-95bc964247f8/tasks/b127p7nn2.output</output-file>
> <status>completed</status>
> <summary>Background command "Commit Carta→STB step 2" completed (exit code 0)</summary>
> </task-notification>

> continue with the last task and confirm no tasks left

> /formulier_dispensatie.php
> HTTP 500
> error page: Failed opening required &#039;C:\repos\adam\mijnrino_php/carta_Rest.inc&#039; (include_path=&#039;.;C:\php\pear&#039;)
> PHP_ERROR marker: [PHP_ERROR] Type: Error | Message: Failed opening required 'C:\repos\adam\mijnrino_php/carta_Rest.inc' (include_path='.;C:\php\pear') | File: C:\repos\adam\mijnrino_php\formulier_dispensatie.php | Line: 51

> /formulier_dispensatie.php
> HTTP 500
> error page: Failed opening required &#039;C:\repos\adam\mijnrino_php/carta_Rest.inc&#039; (include_path=&#039;.;C:\php\pear&#039;)
> PHP_ERROR marker: [PHP_ERROR] Type: Error | Message: Failed opening required 'C:\repos\adam\mijnrino_php/carta_Rest.inc' (include_path='.;C:\php\pear') | File: C:\repos\adam\mijnrino_php\formulier_dispensatie.php | Line: 51

> then i login as a docent i have all snel naar items double

> <task-notification>
> <task-id>bna9l1tq0</task-id>
> <tool-use-id>toolu_018zShboFpbqzRvkzYNzm1yK</tool-use-id>
> <output-file>/tmp/claude-1000/-mnt-c-repos-cma-platform/bdfd0d27-ce63-4936-89b3-95bc964247f8/tasks/bna9l1tq0.output</output-file>
> <status>completed</status>
> <summary>Background command "Bump + sync + commit+tag+push .req removal" completed (exit code 0)</summary>
> </task-notification>

> cma/template_edit.php, cma/tools_dev_copymod.php , yes use required instead

> <task-notification>
> <task-id>b255iksib</task-id>
> <tool-use-id>toolu_01FdnbdCdRN3aeWRgyAFjq5U</tool-use-id>
> <output-file>/tmp/claude-1000/-mnt-c-repos-cma-platform/bdfd0d27-ce63-4936-89b3-95bc964247f8/tasks/b255iksib.output</output-file>
> <status>completed</status>
> <summary>Background command "Bump + sync + commit+tag+push required repoint" completed (exit code 0)</summary>
> </task-notification>

## 2026-07-20

> set ==feature_page_rbac on, also when committing and pushing

> Delete the legacy safety copies data/app.json + data/reports.json if the new locations are used

> 2026-07-20 12:29:12 rol geweigerd ja formulier_voordracht_praktijkopleider.php 2248 8312 ... can you log the rol description instead of the code?

> Review before relying on fail-closed in production 4. Many registry entries carry review:true can you run a test-run and report which are set to true?

> can you create a isempotent migration to fo that?

> and then commit everything

> #this site is not yet in production, it is a migration still in proces

> are you still logging to prompts.md? I asked you to mention the logfile in logreader if there were no logs, but i don't see it yet.

## 2026-07-23

> this code is also used inside the cma_platform, can you check if the patch is needed there as well?

> 1 and re-wire the function into the cma and platform, the excel export from the .php file is probably less reliable

> can you do the same in the cma_platform repo?

> Selecteer een record uit de linker lijst om te wijzigen
>
>  : ik mis de of tussen de tekst en de knop, liefst in italic

> PDOException in in D:\wwwroot\test-mijn.rino.nl\vendor\stenversonline\platform\src\helpers\Database.php on line 542 (code: 63) Database connection 'data' failed.Driver: odbcDSN: odbc:Driver={Microsoft Access Driver (*.mdb, *.accdb)};Dbq=D:\wwwroot\test-mijn.rino.nl\db\pdodomain.mdb;Charset=UTF-8Error: SQLSTATE[HY000] SQLDriverConnect: 63 [Microsoft][ODBC Microsoft Access Driver]General error Unable to open registry key Temporary (volatile) Ace DSN for process 0x2ea0 Thread 0x1110 DBC 0xcd81b6d8 Jet'.

> on the test-site it says: [zelfde PDOException, met stacktrace via index.php:68 / _bootstrap_wrapper.php:90], but it seems database.json is not read?!

> the database tab in errorhandler, does that read databaess.json ? Diagnostics & Tests

> yes, release and push

> the errorhandler does not check the data directory, /logs should be .logs and cache should be .cache

> it now shows: [Database Tests tabel] data via conn_data (app.php) ... Failed ... rep via conn_rep (app.php) ... Connected ... users via conn_users (app.php) ... Connected , but i don's want app.php to be used, it is retired, databases.json should be used

> i have moved the databases from /db to /data

> $fsRoot . '/sessions' => 'Sessions Directory (legacy name)', remove it

> perhals .config should also be checked?

> i see [$_COOKIE lijst] cma_cols_Opleidingen and cms_cols_opleidingen (lowercase), shall we always use lowercase whe saving form columns?

> the databases are now in data/db

> 2. Strip the conn_data/conn_rep/conn_users assignments from app.php and global.asa.php. : can you do that?

> clear them too

> this is a new error : PDOException ... Database.php on line 338 Database connection 'data' not configured in databases.json (data/databases.json or cma/config/databases.json — expected an entry named 'data'). [+ inhoud van data/databases.json, die wel klopt]

> these are / in the path, should that not be \ ?

> i get an error on a missing xslt , we moved them to /assets/xslt but it is looking in /xslt

> can you also set the cache to .cache ?

> 1 yes, 2 identical rows  [beoordeling-conversie: ja; keuzemodules: identieke rijen]

> 3 let's fix that at the root please, so yes: go ahead  [RecordSet::GetRows]

## 2026-07-24

> lib_OpenWindowCenteredMax does not maximize the dialog, can you dedug and fix?

> in tools_query ignore lines stat start with --, those are comments

> i see the maximized class being added, but i don't see the css that goes with it

> the lib_window_max has an icon with a suqre and 2 arrows, if the dialog is shown normal, i want the left bottom arrow to be removed, if it is maximized I want hte upper right arrow to be removed, use inline svg if needed

> http://172.30.208.1:8090/?pageAction=deelnemers&deelnameID=205#Verslagen - verslagen are empty, but there are 3 so that cannot be

> SELECT tblOpleidingen.ID, tblOpleidingen.Titel, tblOpleidingen.fkoplsoort, tblOpleidingen.Code, (SELECT MIN(h.Code) FROM (tblDeelname AS kd INNER JOIN tblDeelname AS hd ON kd.fkHoofddeelname = hd.ID) INNER JOIN tblOpleidingen AS h ON h.ID = hd.fkOpleiding WHERE kd.fkOpleiding = tblOpleidingen.ID) AS HoofdCode, (SELECT Count(*) FROM tblDeelname WHERE tblDeelname.fkOpleiding = tblOpleidingen.ID AND (tblDeelname.certificaatdatum is null or tblDeelname.certificaatdatum > date()) and ((tblDeelname.bPauze = -1 and tblDeelname.datPauze <= date()) or tblDeelname.Einddatum is null or tblDeelname.Einddatum > date()) AND [DEELNAMEROLFILTER]) AS AantalActief FROM tblOpleidingen WHERE TBLOPLEIDINGEN.ID in (17,238) ORDER BY tblOpleidingen.CodeSELECT tblOpleidingen.ID, tblOpleidingen.Titel, tblOpleidingen.fkoplsoort, tblOpleidingen.Code, (SELECT MIN(h.Code) FROM (tblDeelname AS kd INNER JOIN tblDeelname AS hd ON kd.fkHoofddeelname = hd.ID) INNER JOIN tblOpleidingen AS h ON h.ID = hd.fkOpleiding WHERE kd.fkOpleiding = tblOpleidingen.ID) AS HoofdCode, (SELECT Count(*) FROM tblDeelname WHERE tblDeelname.fkOpleiding = tblOpleidingen.ID AND (tblDeelname.certificaatdatum is null or tblDeelname.certificaatdatum > date()) and ((tblDeelname.bPauze = -1 and tblDeelname.datPauze <= date()) or tblDeelname.Einddatum is null or tblDeelname.Einddatum > date()) AND [DEELNAMEROLFILTER]) AS AantalActief FROM tblOpleidingen WHERE TBLOPLEIDINGEN.ID in (17,238) ORDER BY tblOpleidingen.Code 
>
>
> Exception
> Database query failed: SQLSTATE[07002]: COUNT field incorrect: -3010 [Microsoft][ODBC Microsoft Access-stuurprogramma] Er zijn te weinig parameters. Het verwachte aantal is: 1. (SQLExecute[-3010] at ext\pdo_odbc\odbc_stmt.c:267)
> in C:\repos\adam\mijnrino_php\library\lib_xmlsnippets.inc on line 663
> CMA platform v1.29.113

> back to the cma_platform, can you bump the version and commit/push please?

> th lib_window_dialog is now set to 100% , but the container  __lib_win1 has fixed with and height to it falls within those. So we need to change those sizes (as well)

> de uitleg bij clearcache mag korter, eigenlijk wat ik heb aangeleverd.

> a subform recieves this information: {
>     "success": true,
>     "fields": {
>         "ID": "32",
>         "fkOrganisatie": null,
>         "titel": "Opleiding GZ-psycholoog - VO",
>         "Startdatum": "14-02-2025",
>         "contact1": "31",
>         "contact2": "2",
>         "Literatuurlijst": null,
>         "testlijst": null,
>         "RichtlijnenBehandelingcasus": null,
>         "RichtlijnenDiagnostiekcasus": null,
>         "Onderzoeksverslag": null,
>         "Routebeschrijving": null,
>         "TemplateWerkplan": "Individueel Opleidingsplan (IOP) - GZ-opleiding - aangepast door LvT(1).docx",
>         "TemplateJaarverslag": null,
>         "TemplateHalfJaarVerslag": "Voortgangsrapportage - GZ- v012025.docx",
>         "contact3": null,
>         "fotoboekPDF": null,
>         "Supervisieverklaring": null,
>         "BNSOpleiding": "0",
>         "Studiegids": "Studiegids 2024 (2024).pdf",
>         "PromptusID": "442",
>         "fkCompetentieTemplate": "1",
>         "DigitaalDossioma": "1",
>         "fkUrenTemplate": "1",
>         "ToetsenOverzicht": null,
>         "IliasCursusID": null,
>         "fkDifferentiatie": "1",
>         "CGO": "0",
>         "CGO_Documenten": null,
>         "CGOToetsformulier": "0",
>         "fkVATemplate": "3",
>         "code": "GZ2025-G",
>         "draaiboekDigitaal": "1",
>         "draaiboekIntro": null,
>         "datDocentenOnline": "16-12-2024",
>         "bHerregistratie": "0",
>         "fkEvaluatieTemplate": "2",
>         "bMoodleGekoppeld": "0",
>         "bFactuurGegevens": "0",
>         "bNieuweCGOIndeling": "1",
>         "fkOplSoort": "25",
>         "POMagWerkplanPlaatsen": "1",
>         "bTijdelijkeGroep": "0",
>         "bPuurOnline": "0",
>         "blnOvereenkomst": "0",
>         "blnWijzigbaarDraaiboek": "0",
>         "bBlended": "0",
>         "bPresentiePilot": "1",
>         "codeMoeder": "GZ2025-G",
>         "PlanningVerwerkt": "0",
>         "PlanningPrijsNNB": "0",
>         "bBIGVO": "1",
>         "bBIGKJ": "0",
>         "aantaldagenVoorstartZichtbaar": "14",
>         "StudiegidsURL": null,
>         "bReadonly": "0",
>         "bKeuzemodule": "0",
>         "fkPlanner": null,
>         "datBelangstellendenGeinformeerd": null,
>         "bVerborgen": "0",
>         "Beeld": null,
>         "AlgemeneInfo": null,
>         "datOnSite": "15-01-2025",
>         "Aanmeldingseisen": "Inhoud bericht voor &#39;aanmeldingseisen_GZ&#39; ontbreekt.",
>         "Opleidingsovereenkomst": "Inhoud bericht voor &#39;opleidingsovk_GZ&#39; ontbreekt.",
>         "Vrijstellingseisen": "[hier de tekst voor de Vrijstellingseisen GZ]",
>         "sluitdatum": null,
>         "bPilotOPT": "0",
>         "datMedewOnline": null,
>         "bBIGO": "0",
>         "VerstuurMails": "1",
>         "EindVerslagTemplate": null,
>         "VerslagBijlage1Oms": "Supervisieverklaring",
>         "VerslagBijlage2Oms": "Supervisieverklaring",
>         "VerslagBijlage3Oms": "Supervisieverklaring",
>         "VerslagBijlage1Template": "Supervisieverklaring v2025.docx",
>         "VerslagBijlage2Template": null,
>         "VerslagBijlage3Template": null,
>         "bVerbergPersoonlijsten": "0",
>         "VervangendeOpdrachttemplate": "Vervangende opdracht GZ vjuni25.docx",
>         "TemplateEindverslag": null,
>         "VerslagBijlage1Verplicht": "0",
>         "VerslagBijlage2Verplicht": "0",
>         "VerslagBijlage3Verplicht": "0",
>         "fkKBTTemplate": null,
>         "aantalPeriodes": "4",
>         "bCheckEindgesprek": "1",
>         "fkOplSoort__label": "BIG Opleiding                                     ",
>         "fkDifferentiatie__label": "GZ - GZ-Psycholoog",
>         "contact1__label": "Marline Dasbach",
>         "contact2__label": "Angelique van Wees",
>         "fkEvaluatieTemplate__label": "BIG- BIG template (standaard)",
>         "fkUrenTemplate__label": "GZ versie 2024 - Halfjaarverslagen",
>         "fkCompetentieTemplate__label": "Competentieprofiel GZ-psycholoog",
>         "fkVATemplate__label": "GZ-template"
>     },
>     "meta": {
>         "id": "32",
>         "accessLevel": 40,
>         "canEdit": true,
>         "canAdd": true,
>         "canDelete": false
>     },
>     "firstSubform": {
>         "success": true,
>         "html": "<lib-table><table class=\"listtable subform-table filtering sorttable\" id=\"subformTable_69\" data-subform-id=\"69\" data-json-form=\"opleidingen_deelnemers\" data-name=\"Deelnemers\" cellspacing=\"0\" cellpadding=\"0\"><thead><tr class=\"listheader\"><th data-field=\"Deelnemer\" data-type=\"text\">Deelnemer<\/th><th data-field=\"Praktijkopleider\" data-type=\"text\">Praktijkopleider<\/th><th data-field=\"Hoofd\/jaargroepopleider\" data-type=\"text\">Hoofd\/jaargroepopleider<\/th><th data-field=\"Status_toelating\" data-type=\"text\">Status_toelating<\/th><th data-field=\"Datum_Aangemeld\" data-type=\"text\">Datum_Aangemeld<\/th><th data-field=\"Toegang_tot\" data-type=\"text\">Toegang_tot<\/th><th data-field=\"CertificaatDatum\" data-type=\"date\">Datum certificaat<\/th><th data-field=\"Is_Inhaler\" data-type=\"text\">Is_Inhaler<\/th><\/tr><\/thead><tbody><tr class=\"listrow\" data-id=\"467\"><td data-field=\"Deelnemer\"><span class=\"row-menu-trigger\" data-id=\"467\">&#8942;<\/span>Thom van den Anker<\/td><td data-field=\"Praktijkopleider\">Ilanith Mattheus Yona<\/td><td data-field=\"Hoofd\/jaargroepopleider\">Ranne de Waart<\/td><td data-field=\"Status_toelating\">Toegelaten<\/td><td data-field=\"Datum_Aangemeld\"><\/td><td data-field=\"Toegang_tot\">01 jan 2030<\/td><td data-field=\"CertificaatDatum\"><\/td><td data-field=\"Is_Inhaler\">Nee<\/td><\/tr><tr class=\"listrow\" data-id=\"474\"><td data-field=\"Deelnemer\"><span class=\"row-menu-trigger\" data-id=\"474\">&#8942;<\/span>Rowan Bijl<\/td><td data-field=\"Praktijkopleider\">Sanne Beer<\/td><td data-field=\"Hoofd\/jaargroepopleider\">Ruthie Werner<\/td><td data-field=\"Status_toelating\">Toegelaten<\/td><td data-field=\"Datum_Aangemeld\"><\/td><td data-field=\"Toegang_tot\">01 jan 2030<\/td><td data-field=\"CertificaatDatum\"><\/td><td data-field=\"Is_Inhaler\">Nee<\/td><\/tr><tr class=\"listrow\" data-id=\"468\"><td data-field=\"Deelnemer\"><span class=\"row-menu-trigger\" data-id=\"468\">&#8942;<\/span>Kim Brinkman<\/td><td data-field=\"Praktijkopleider\">Marjolein Mazereeuw<\/td><td data-field=\"Hoofd\/jaargroepopleider\">Ranne de Waart<\/td><td data-field=\"Status_toelating\">Toegelaten<\/td><td data-field=\"Datum_Aangemeld\"><\/td><td data-field=\"Toegang_tot\"><\/td><td data-field=\"CertificaatDatum\"><\/td><td data-field=\"Is_Inhaler\">Nee<\/td><\/tr><tr class=\"listrow\" data-id=\"456\"><td data-field=\"Deelnemer\"><span class=\"row-menu-trigger\" data-id=\"456\">&#8942;<\/span><\/td><td data-field=\"Praktijkopleider\">Anouk Hage<\/td><td data-field=\"Hoofd\/jaargroepopleider\">Ranne de Waart<\/td><td data-field=\"Status_toelating\">Toegelaten<\/td><td data-field=\"Datum_Aangemeld\"><\/td><td data-field=\"Toegang_tot\">01 jan 2030<\/td><td data-field=\"CertificaatDatum\"><\/td><td data-field=\"Is_Inhaler\">Nee<\/td><\/tr><tr class=\"listrow\" data-id=\"464\"><td data-field=\"Deelnemer\"><span class=\"row-menu-trigger\" data-id=\"464\">&#8942;<\/span>Denise Dekker<\/td><td data-field=\"Praktijkopleider\">Eveline de Kam<\/td><td data-field=\"Hoofd\/jaargroepopleider\">Ruthie Werner<\/td><td data-field=\"Status_toelating\">Toegelaten<\/td><td data-field=\"Datum_Aangemeld\"><\/td><td data-field=\"Toegang_tot\">01 jan 2030<\/td><td data-field=\"CertificaatDatum\"><\/td><td data-field=\"Is_Inhaler\">Nee<\/td><\/tr><tr class=\"listrow\" data-id=\"457\"><td data-field=\"Deelnemer\"><span class=\"row-menu-trigger\" data-id=\"457\">&#8942;<\/span>Babette Edelman<\/td><td data-field=\"Praktijkopleider\">Saskia Lunenborg<\/td><td data-field=\"Hoofd\/jaargroepopleider\">Ruthie Werner<\/td><td data-field=\"Status_toelating\">Toegelaten<\/td><td data-field=\"Datum_Aangemeld\"><\/td><td data-field=\"Toegang_tot\"><\/td><td data-field=\"CertificaatDatum\"><\/td><td data-field=\"Is_Inhaler\">Nee<\/td><\/tr><tr class=\"listrow\" data-id=\"460\"><td data-field=\"Deelnemer\"><span class=\"row-menu-trigger\" data-id=\"460\">&#8942;<\/span>Manon Giesing<\/td><td data-field=\"Praktijkopleider\">Marieke van Spronsen<\/td><td data-field=\"Hoofd\/jaargroepopleider\">Ruthie Werner<\/td><td data-field=\"Status_toelating\">Toegelaten<\/td><td data-field=\"Datum_Aangemeld\"><\/td><td data-field=\"Toegang_tot\"><\/td><td data-field=\"CertificaatDatum\"><\/td><td data-field=\"Is_Inhaler\">Nee<\/td><\/tr><tr class=\"listrow\" data-id=\"475\"><td data-field=\"Deelnemer\"><span class=\"row-menu-trigger\" data-id=\"475\">&#8942;<\/span>Merel Hermans<\/td><td data-field=\"Praktijkopleider\">Anja Eijsackers<\/td><td data-field=\"Hoofd\/jaargroepopleider\">Ranne de Waart<\/td><td data-field=\"Status_toelating\">Toegelaten<\/td><td data-field=\"Datum_Aangemeld\"><\/td><td data-field=\"Toegang_tot\">01 jan 2030<\/td><td data-field=\"CertificaatDatum\"><\/td><td data-field=\"Is_Inhaler\">Nee<\/td><\/tr><tr class=\"listrow\" data-id=\"473\"><td data-field=\"Deelnemer\"><span class=\"row-menu-trigger\" data-id=\"473\">&#8942;<\/span>Esther Huiswoud<\/td><td data-field=\"Praktijkopleider\">Anouk Barteling<\/td><td data-field=\"Hoofd\/jaargroepopleider\">Ranne de Waart<\/td><td data-field=\"Status_toelating\">Toegelaten<\/td><td data-field=\"Datum_Aangemeld\"><\/td><td data-field=\"Toegang_tot\"><\/td><td data-field=\"CertificaatDatum\"><\/td><td data-field=\"Is_Inhaler\">Nee<\/td><\/tr><tr class=\"listrow\" data-id=\"470\"><td data-field=\"Deelnemer\"><span class=\"row-menu-trigger\" data-id=\"470\">&#8942;<\/span>Elvira de Jong<\/td><td data-field=\"Praktijkopleider\">Lucinda Meihuizen<\/td><td data-field=\"Hoofd\/jaargroepopleider\">Ranne de Waart<\/td><td data-field=\"Status_toelating\">Toegelaten<\/td><td data-field=\"Datum_Aangemeld\"><\/td><td data-field=\"Toegang_tot\"><\/td><td data-field=\"CertificaatDatum\"><\/td><td data-field=\"Is_Inhaler\">Nee<\/td><\/tr><tr class=\"listrow\" data-id=\"458\"><td data-field=\"Deelnemer\"><span class=\"row-menu-trigger\" data-id=\"458\">&#8942;<\/span>Ferdi de Jong<\/td><td data-field=\"Praktijkopleider\">Nanda Hollander<\/td><td data-field=\"Hoofd\/jaargroepopleider\">Ruthie Werner<\/td><td data-field=\"Status_toelating\">Toegelaten<\/td><td data-field=\"Datum_Aangemeld\"><\/td><td data-field=\"Toegang_tot\">01 jan 2030<\/td><td data-field=\"CertificaatDatum\"><\/td><td data-field=\"Is_Inhaler\">Nee<\/td><\/tr><tr class=\"listrow\" data-id=\"461\"><td data-field=\"Deelnemer\"><span class=\"row-menu-trigger\" data-id=\"461\">&#8942;<\/span>Floor Modderman<\/td><td data-field=\"Praktijkopleider\">Sigfried Schouws<\/td><td data-field=\"Hoofd\/jaargroepopleider\">Ruthie Werner<\/td><td data-field=\"Status_toelating\">Toegelaten<\/td><td data-field=\"Datum_Aangemeld\"><\/td><td data-field=\"Toegang_tot\">01 jan 2030<\/td><td data-field=\"CertificaatDatum\"><\/td><td data-field=\"Is_Inhaler\">Nee<\/td><\/tr><tr class=\"listrow\" data-id=\"472\"><td data-field=\"Deelnemer\"><span class=\"row-menu-trigger\" data-id=\"472\">&#8942;<\/span>Eveline Palandeng<\/td><td data-field=\"Praktijkopleider\">Anouk Barteling<\/td><td data-field=\"Hoofd\/jaargroepopleider\">Ranne de Waart<\/td><td data-field=\"Status_toelating\">Toegelaten<\/td><td data-field=\"Datum_Aangemeld\"><\/td><td data-field=\"Toegang_tot\"><\/td><td data-field=\"CertificaatDatum\"><\/td><td data-field=\"Is_Inhaler\">Nee<\/td><\/tr><tr class=\"listrow\" data-id=\"462\"><td data-field=\"Deelnemer\"><span class=\"row-menu-trigger\" data-id=\"462\">&#8942;<\/span>Leonie Sandman<\/td><td data-field=\"Praktijkopleider\"><\/td><td data-field=\"Hoofd\/jaargroepopleider\">Ruthie Werner<\/td><td data-field=\"Status_toelating\">Toegelaten<\/td><td data-field=\"Datum_Aangemeld\"><\/td><td data-field=\"Toegang_tot\"><\/td><td data-field=\"CertificaatDatum\"><\/td><td data-field=\"Is_Inhaler\">Nee<\/td><\/tr><tr class=\"listrow\" data-id=\"469\"><td data-field=\"Deelnemer\"><span class=\"row-menu-trigger\" data-id=\"469\">&#8942;<\/span>Lotte Schulze<\/td><td data-field=\"Praktijkopleider\">Lisette Durieux-van de Bilt<\/td><td data-field=\"Hoofd\/jaargroepopleider\">Ranne de Waart<\/td><td data-field=\"Status_toelating\">Toegelaten<\/td><td data-field=\"Datum_Aangemeld\"><\/td><td data-field=\"Toegang_tot\">01 jan 2030<\/td><td data-field=\"CertificaatDatum\"><\/td><td data-field=\"Is_Inhaler\">Nee<\/td><\/tr><tr class=\"listrow\" data-id=\"465\"><td data-field=\"Deelnemer\"><span class=\"row-menu-trigger\" data-id=\"465\">&#8942;<\/span>Angela Stigter<\/td><td data-field=\"Praktijkopleider\">Samui Winterwerp<\/td><td data-field=\"Hoofd\/jaargroepopleider\">Ruthie Werner<\/td><td data-field=\"Status_toelating\">Toegelaten<\/td><td data-field=\"Datum_Aangemeld\"><\/td><td data-field=\"Toegang_tot\">01 jan 2030<\/td><td data-field=\"CertificaatDatum\"><\/td><td data-field=\"Is_Inhaler\">Nee<\/td><\/tr><tr class=\"listrow\" data-id=\"463\"><td data-field=\"Deelnemer\"><span class=\"row-menu-trigger\" data-id=\"463\">&#8942;<\/span>David Tetteroo<\/td><td data-field=\"Praktijkopleider\">Kaisa Spierings<\/td><td data-field=\"Hoofd\/jaargroepopleider\">Ranne de Waart<\/td><td data-field=\"Status_toelating\">Toegelaten<\/td><td data-field=\"Datum_Aangemeld\"><\/td><td data-field=\"Toegang_tot\">01 jan 2030<\/td><td data-field=\"CertificaatDatum\"><\/td><td data-field=\"Is_Inhaler\">Nee<\/td><\/tr><tr class=\"listrow\" data-id=\"466\"><td data-field=\"Deelnemer\"><span class=\"row-menu-trigger\" data-id=\"466\">&#8942;<\/span>Rianne Visser<\/td><td data-field=\"Praktijkopleider\">Maxime Weijma<\/td><td data-field=\"Hoofd\/jaargroepopleider\">Ranne de Waart<\/td><td data-field=\"Status_toelating\">Toegelaten<\/td><td data-field=\"Datum_Aangemeld\"><\/td><td data-field=\"Toegang_tot\"><\/td><td data-field=\"CertificaatDatum\"><\/td><td data-field=\"Is_Inhaler\">Nee<\/td><\/tr><tr class=\"listrow\" data-id=\"471\"><td data-field=\"Deelnemer\"><span class=\"row-menu-trigger\" data-id=\"471\">&#8942;<\/span>Marit van der Waart<\/td><td data-field=\"Praktijkopleider\">Willemijn Scholten<\/td><td data-field=\"Hoofd\/jaargroepopleider\">Ruthie Werner<\/td><td data-field=\"Status_toelating\">Toegelaten<\/td><td data-field=\"Datum_Aangemeld\"><\/td><td data-field=\"Toegang_tot\">01 jan 2030<\/td><td data-field=\"CertificaatDatum\"><\/td><td data-field=\"Is_Inhaler\">Nee<\/td><\/tr><tr class=\"listrow\" data-id=\"459\"><td data-field=\"Deelnemer\"><span class=\"row-menu-trigger\" data-id=\"459\">&#8942;<\/span>Irma van de Wetering<\/td><td data-field=\"Praktijkopleider\">Astrid Blok<\/td><td data-field=\"Hoofd\/jaargroepopleider\">Ruthie Werner<\/td><td data-field=\"Status_toelating\">Toegelaten<\/td><td data-field=\"Datum_Aangemeld\"><\/td><td data-field=\"Toegang_tot\"><\/td><td data-field=\"CertificaatDatum\"><\/td><td data-field=\"Is_Inhaler\">Nee<\/td><\/tr><\/tbody><\/table><\/lib-table>",
>         "count": 20,
>         "subformId": "opleidingen_deelnemers",
>         "subformName": "Deelnemers",
>         "subformNameSingular": "Deelnemer",
>         "parentField": "fkOpleiding",
>         "fullWidth": false,
>         "canAdd": true,
>         "canEdit": true,
>         "canDelete": true,
>         "_debug": {
>             "sqlOriginal": "SELECT tblDeelname.ID, tblDeelnemers.VolledigeNaam AS Deelnemer, tblPraktijkOpleiders.VolledigeNaam AS Praktijkopleider, tblDocenten.VolledigeNaam AS [Hoofd\/jaargroepopleider], iif(toegelaten=1,\"Toegelaten\",iif(toegelaten=2,\"Tweede gesprek\",iif(toegelaten=3,\"Niet gestart\", \"Onbekend\"))) as Status_toelating, datAangemeld as Datum_Aangemeld, lcase(format([tblDeelname].[Einddatum],'dd MMM yyyy')) as Toegang_tot, lcase(format([tblDeelname].[Certificaatdatum],'dd MMM yyyy')) as CertificaatDatum, iif( tblDeelname.inhaler=true, \"Ja\", \"Nee\") as Is_Inhaler\nFROM tblPraktijkOpleiders\n  RIGHT JOIN (tblDocenten\n  RIGHT JOIN (tblDeelnemers\n  INNER JOIN tblDeelname\n    ON tblDeelnemers.ID = tblDeelname.fkDeelnemer)\n    ON tblDocenten.ID = tblDeelname.fkHoofdDocent)\n    ON tblPraktijkOpleiders.ID = tblDeelname.fkPraktijkOpleider\nORDER BY tblDeelnemers.Achternaam",
>             "sql": "SELECT TOP 500 tblDeelname.ID, tblDeelnemers.VolledigeNaam AS Deelnemer, tblPraktijkOpleiders.VolledigeNaam AS Praktijkopleider, tblDocenten.VolledigeNaam AS [Hoofd\/jaargroepopleider], iif(toegelaten=1,\"Toegelaten\",iif(toegelaten=2,\"Tweede gesprek\",iif(toegelaten=3,\"Niet gestart\", \"Onbekend\"))) as Status_toelating, datAangemeld as Datum_Aangemeld, lcase(format([tblDeelname].[Einddatum],'dd MMM yyyy')) as Toegang_tot, lcase(format([tblDeelname].[Certificaatdatum],'dd MMM yyyy')) as CertificaatDatum, iif( tblDeelname.inhaler=true, \"Ja\", \"Nee\") as Is_Inhaler\nFROM tblPraktijkOpleiders\n  RIGHT JOIN (tblDocenten\n  RIGHT JOIN (tblDeelnemers\n  INNER JOIN tblDeelname\n    ON tblDeelnemers.ID = tblDeelname.fkDeelnemer)\n    ON tblDocenten.ID = tblDeelname.fkHoofdDocent)\n    ON tblPraktijkOpleiders.ID = tblDeelname.fkPraktijkOpleider\n WHERE tblDeelname.fkOpleiding=32 ORDER BY tblDeelnemers.Achternaam",
>             "parentId": "32",
>             "parentField": "fkOpleiding",
>             "subformIndex": 0
>         }
>     }
> } the 4th deelnemer (Daniëlle van Dam) does not show because of special characters

> Startdatum2024-deelnemers_algemeen.inc: datum is still 2024-02-09 00:00:00

> can you bump the platform version, so i can check the special characters?

> what are you doing?

> shy are you witing if so much work is stil open, /loop until finished and keep me better informed of your progress

> <task-notification>
> <task-id>a5e1d2397d9035b33</task-id>
> <tool-use-id>toolu_01BRoTw8Ltrtws75GFe4rJm9</tool-use-id>
> <output-file>/tmp/claude-1000/-mnt-c-repos-cma-platform/da1cb063-f5e4-46ef-bf55-3dd561f1e3a3/tasks/a5e1d2397d9035b33.output</output-file>
> <status>completed</status>
> <summary>Agent "Audit memo-not-last in SQL" finished</summary>
> <note>A task-notification fires each time this agent stops with no live background children of its own. The user can send it another message and resume it, so the same task-id may notify more than once.</note>
> <result>I have enough to compile the report. All confirmed. Let me write up the findings.
>
> ---
>
> # MEMO-not-last Audit — mijnRINO (`/mnt/c/repos/adam/mijnrino_php`)
>
> Method: reconstructed the `SELECT … FROM` column list of every SQL string literal in all `.php`/`.inc` files (excluding `vendor/`), handling PHP concatenation, then flagged any where a MEMO column (per the supplied name list) is followed by ≥1 more column before `FROM`. All flagged queries run against the Access connection (`Database::openRS(…, 'data', …)`) unless noted. Two raw hits were confirmed false positives and excluded; see the end.
>
> **Total real findings: 34 query-sites across 30 files** (26 high, 4 medium, 4 low/deprecated).
>
> ---
>
> ## HIGH confidence
>
> These use exact known-memo column names, on the Access `'data'` connection, with the memo clearly not last.
>
> **Verslag display (matches the described symptom exactly — "Nog niet ingediend" / hours 0):**
>
> - `src/opleiding/opleiding_verslagen.inc:416` — memos `tblVerslagen.urenOpmerking` AND `tblVerslagen.verslag` sit at cols 2 and 8 of a **137-column** SELECT; ~130 columns (all the `*_UREN`, `*_Omschr`, `PR#_CLIENTEN`, `_Combi` fields) follow. This is the master verslag/uren read — everything after `verslag` reads NULL and rows with report text throw Jet 3197. Fix: move `urenOpmerking` and `verslag` to the very end of the list.
> - `verslag.php:326` (edit branch of `$SQLUrenTemplate`) — `tblVerslagen.verslag` is col 3 of an ~85-column SELECT; all the bijlage/uren columns follow. Fix: move `tblVerslagen.verslag` to the end. (Note: the sibling `add` branch at line 324 only uses `'' as Opmerking` literals — not affected.)
> - `cma_afterpost.php:492` — `tblVerslagen.Verslag` followed by `tblVerslagen.Volgnummer`. Fix: `…, tblVerslagen.ID, tblVerslagen.Volgnummer, tblVerslagen.Verslag`.
> - `src/opleiding/opleiding_dossioma.inc:1219` — `tblVerslagen.HoofdopleiderOpmerking` followed by `HoofdopleiderGoedgekeurd`, `tblOpleidingen.id as opleidingID`. Fix: move `HoofdopleiderOpmerking` last.
>
> **Afwezigheid / presentie (hours read as 0):**
>
> - `src/opleiding/opleiding_voortgang.inc:779` — the **Access (`else`) branch** of `$SQLAbsenties`: `tblAfwezigheid.Opmerking` is followed by `CompensatieAkkoord, fkVervangendeOpdracht, InhalenIngepland, iif(…) AS Uren`. The `Uren` computation (and compensation flags) read NULL. Note: the `isSQLServer()` branch at line 777 already puts `Opmerking` last correctly — only the Access path is broken. Fix: reorder the else-branch to end with `…AS Uren, tblAfwezigheid.Opmerking`.
> - `src/opleiding/opleiding_dossioma.inc:909` — `tblAfwezigheid.Opmerking` followed by `CompensatieAkkoord, InhalenIngepland, fkVervangendeOpdracht`. Fix: move `Opmerking` last.
> - `src/reports/rapportage_presentie.inc:69` — `tblAfwezigheid.Opmerking` followed by `inhaler, fkVervangendeOpdracht, InhalenIngepland`. Fix: move `Opmerking` last.
> - `dig_presentie.php:528`, `:530`, `:532` — three near-identical queries; `presentieOpmerking` followed by `docentAkkoord`. Fix: `…, docentAkkoord, presentieOpmerking`.
>
> **Beoordeling / IOP / toetsen / verklaringen:**
>
> - `opleiding_beoordeling.php:554` — `tblDeelname.HoofdopleiderOpmerking` followed by `tblPraktijkOpleiders.Email, .ID as praktijkopleiderID, .EmailNotificaties` (the email columns used to notify the PO read NULL). Fix: move `HoofdopleiderOpmerking` last.
> - `src/opleiding/opleiding_iop.inc:55` — **three** memos: `PraktijkopleiderOpmerking, HoofdopleiderOpmerking, HoofdopleiderOpmerking_Oud`, then `Hoofddocent, AssistentWerkplanCheck` follow. Multiple long-text columns are inherently unsafe on Access ODBC; at minimum the trailing two columns are lost. Fix: collapse the three memos to the end (and be aware only the last is reliably readable — consider a second query for the extra memos).
> - `src/opleiding/opleiding_toetsen.inc:242` — `tblToetsPerDeelnemer.Opmerking` at col 22 of a 34-col SELECT, then `Opmerking_Oud` + 11 more (`AssistentCheck`, elearning/scorm fields, `grademethod`). Fix: move both `Opmerking`/`Opmerking_Oud` to the end.
> - `src/opleiding/opleiding_verklaringen.inc:255` — `start_PraktijkopleiderOpmerking` and `einde_PraktijkopleiderOpmerking` each followed by document/date/goedgekeurd columns. Fix: move both opmerking columns last.
> - `opleiding_toets_eerderebeoordeling.php:36` and `utils.inc:3617` — `Opmerking` followed by `Opmerking_Oud` (two long-text columns adjacent at the tail). The earlier memo breaks reading of the later. Fix: only one memo can be truly last; split into two reads if both are needed.
>
> **Portfolio / startdocumenten / CGO:**
>
> - `src/opleiding/opleiding_cgo_portfolio.inc:469` — `tblDeelnameCGOPortfolio.OpmerkingHO` followed by `bestand AS Uitwerking, Naam AS Omschrijving, datestamp, CGOToetsformulier, HoofdOpleider`. Fix: move `OpmerkingHO` last.
> - `src/opleiding/opleiding_startdocumenten.inc:75` — `tbldeelname.AanvangsdocumentenOpmerking` followed by `AanvangsdocumentenCompleet, VolledigeNaam`. Fix: move memo last.
> - `upload_portfolio.php:176` — `tblStartdocumenten.Opmerking` followed by `Sortorder, template`. Fix: move memo last.
> - `upload_portfolio.php:215` — `tblStartdocumenten.Opmerking` followed by `bMeerdereDocs, template, verplicht, template`. Fix: move memo last.
>
> **Nieuws / gesprekken / voordracht / reports:**
>
> - `index.php:448` — `tblRinoNieuws.Inhoud` followed by `tblRinoNieuws.Beeld, DatOnline`. Fix: `…, Beeld, DatOnline, tblRinoNieuws.Inhoud`. (The scanner also emitted spurious `index.php:359/360` rows — those are the same query reached by a forward window, not separate bugs.)
> - `src/reports/rapportage_gesprekkenrapport.inc:141` — `tblUitnodigingGesprekken.sbOpmerking` at col 3 of a 28-col SELECT; date/time/location/email columns follow. Fix: move `sbOpmerking` last.
> - `src/reports/rapportage_gesprekkenrapport.inc:163` — `tblUitnodigingPerDeelname.sbopmerking` at col 2 of a 13-col SELECT; the rest follow. Fix: move `sbopmerking` last.
> - `src/opleiding/opleiding_voordracht.inc:158` — `Opmerking` at col 8, then ~29 persoonsgegevens columns follow. Fix: move `Opmerking` last.
>
> **Utility / migration (lower operational surface but same bug):**
>
> - `inventarisatie/excel.php:522` — `SBopmerking` at col 3, then ~15 columns (Access `iif(…)` syntax) follow. Fix: move `SBopmerking` last.
> - `data/db/migreer_systeemteksten.php:101` — three memos not last: `Beschrijving`, `Inhoud`, `businessreden`, each followed by more (`meldingtrigger, blokkade, naarwie` trail). One-off migration script, but reads Access. Fix: move the three long-text columns to the end.
>
> ---
>
> ## MEDIUM confidence
>
> Suffix-only memo match (`*beschrijving`) — likely long-text but not on the exact-name list.
>
> - `agenda_route.php:36` — `tblOpleidingen.Routebeschrijving` followed by `route_aanvullend, Locatie, LocatieAdres, LocatiePostcode, LocatiePlaats`.
> - `src/classes/calendar/class_calendar.inc:546` — `tblOpleidingen.Routebeschrijving` at col 2 of a 24-col agenda SELECT; the rest follow.
> - `src/opleiding/opleiding_draaiboek_digitaal.inc:1122` — `tblOpleidingen.Routebeschrijving` at col 15 of a 33-col SELECT; agenda/download columns follow.
> - `cma/task.php:39` — `tblUserDataNotifications.notBeschrijving` followed by `tblUsers.userFullName, tblUsers.userEMail` (CMA notifications; `notBeschrijving` may be short varchar rather than true MEMO — verify column type).
>
> Fix for all: move the `*beschrijving` column to the end of the SELECT.
>
> ---
>
> ## LOW / deprecated
>
> - `src/opleiding/opleiding_kba_oud.inc:139` — `select Beschrijving, Bestand, Beschrijving, datumGeplaatst, NaamSupervisor, datumBesproken` — `Beschrijving` listed twice (as-is in source), memo not last. File is the `_oud` (superseded) KBA variant; verify it is still reachable before fixing.
> - `.deprecated/inventarisatie/rapport_mailing.php:123` and `:124` — `tblPOInstellingen.SBOpmerking` not last. This is **un-ported Classic ASP** (uses `&amp;` concatenation, `_ //`) under `.deprecated/`; not live PHP.
> - `.deprecated/_moodle/moodle.inc:65` — `draaiboekIntro` followed by `sluitdatum`. Deprecated Moodle integration.
>
> ---
>
> ## Confirmed FALSE POSITIVES (excluded — no action needed)
>
> - `task_controles.php:910` — the "memo" is a string literal built by a PHP ternary: `'" . ($intMaanden == 6 ? 'half' : '') . "jaarverslag' as verslagtype`. Not a column.
> - `task_controles.php:710` — the flagged `akkoord_hoofdopleider_opmerking` etc. do have trailing columns, but this is a 117-column `tblControles`-style flat read; I list it here as a **watch item** rather than confirmed: several `*_opmerking`, `*_reden`, `situatie_toelichting`, `motivatie` memo-named columns appear mid-list with `opmerkingen, deelname_ids, situatie_*, UNIQUE_CODE, *_naam_*` after them. If those columns read empty in practice, this is a genuine high-impact instance — worth a targeted check, but I couldn't confirm the column types are MEMO vs. short text from the SQL alone.
> - `verslag.php:324` (add branch) — only `'' as Opmerking` / `'' as UrenOpmerking` literals; not real columns.
> - `index.php:359/360` — duplicate reconstructions of the real `index.php:448` query.
>
> ---
>
> The scanning script is at `/tmp/claude-1000/-mnt-c-repos-cma-platform/da1cb063-f5e4-46ef-bf55-3dd561f1e3a3/scratchpad/scan.py` if you want to re-run or tune the memo list. Note one methodological caveat surfaced during the audit: an early 3000-char window missed very long SELECTs (verslag.php:326, opleiding_verslagen.inc:416) — the final run uses a 20000-char window, which covered every query in this schema, but any future SELECT with a &gt;20k-char column list would need a larger window.</result>
> <usage><subagent_tokens>82150</subagent_tokens><tool_uses>15</tool_uses><duration_ms>386667</duration_ms></usage>
> </task-notification>

> <task-notification>
> <task-id>a5f47d9d75c75d66a</task-id>
> <tool-use-id>toolu_01CyJerHPEtYdHuKd8HgbE4X</tool-use-id>
> <output-file>/tmp/claude-1000/-mnt-c-repos-cma-platform/da1cb063-f5e4-46ef-bf55-3dd561f1e3a3/tasks/a5f47d9d75c75d66a.output</output-file>
> <status>completed</status>
> <summary>Agent "Apply memo-not-last SQL fixes" finished</summary>
> <note>A task-notification fires each time this agent stops with no live background children of its own. The user can send it another message and resume it, so the same task-id may notify more than once.</note>
> <result>All 21 edited files pass `php -l`. Here is the complete report.
>
> ## Applied fixes (memo column(s) moved to immediately before FROM)
>
> | File | Query | Moved to end |
> |---|---|---|
> | src/opleiding/opleiding_verslagen.inc:416 | urentemplate SELECT (~137 cols) | `urenOpmerking`, `verslag` |
> | verslag.php:326 | edit-branch SELECT | `tblVerslagen.verslag` (add branch left untouched) |
> | cma_afterpost.php:492 | verslag-mail SELECT | `tblVerslagen.Verslag` (now after `Volgnummer`) |
> | src/opleiding/opleiding_dossioma.inc:1219 | verslagen SELECT | `HoofdopleiderOpmerking` |
> | src/opleiding/opleiding_dossioma.inc:909 | absenties SELECT | `tblAfwezigheid.Opmerking` |
> | src/reports/rapportage_presentie.inc:69 | rooster SELECT | `tblAfwezigheid.Opmerking` |
> | dig_presentie.php:528, 530, 532 | 3 identical roosteritem SELECTs | `presentieOpmerking` (after `docentAkkoord`) |
> | opleiding_beoordeling.php:554 | PO-mail SELECT | `tblDeelname.HoofdopleiderOpmerking` |
> | src/opleiding/opleiding_iop.inc:55 | IOP SELECT | `PraktijkopleiderOpmerking`, `HoofdopleiderOpmerking`, `HoofdopleiderOpmerking_Oud` |
> | src/opleiding/opleiding_toetsen.inc:242 | toets/deelnemer SELECT | `Opmerking`, `Opmerking_Oud` |
> | src/opleiding/opleiding_verklaringen.inc:255 | verklaringen SELECT | `start_PraktijkopleiderOpmerking`, `einde_PraktijkopleiderOpmerking` |
> | src/opleiding/opleiding_cgo_portfolio.inc:469 | documenten SELECT | `OpmerkingHO` |
> | src/opleiding/opleiding_startdocumenten.inc:75 | basesql SELECT | `AanvangsdocumentenOpmerking` |
> | upload_portfolio.php:176 | bestanden SELECT | `tblStartdocumenten.Opmerking` |
> | upload_portfolio.php:215 | vereist SELECT | `tblStartdocumenten.Opmerking` |
> | index.php:448 | nieuws SELECT | `tblRinoNieuws.Inhoud` (after `Beeld, DatOnline`) |
> | src/reports/rapportage_gesprekkenrapport.inc:163 | niet-ingeplande SELECT | `sbopmerking` |
> | inventarisatie/excel.php:522 | voordrachten SELECT | `SBopmerking` |
> | data/db/migreer_systeemteksten.php:101 | systeemteksten SELECT | `Beschrijving`, `Inhoud`, `businessreden` |
> | agenda_route.php:36 | route SELECT | `Routebeschrijving` |
> | src/classes/calendar/class_calendar.inc:546 | calendar SELECT | `Routebeschrijving` (GROUP BY uses names, safe) |
> | src/opleiding/opleiding_draaiboek_digitaal.inc:1122 | rooster-details SELECT | `Routebeschrijving` |
> | cma/task.php:39 | notifications SELECT | `notBeschrijving` |
>
> ## Skipped
>
> - **src/reports/rapportage_gesprekkenrapport.inc:141** — skipped: positional `order by 1, ...`. Per the safety rule I did not reorder it (OplCode is aliased at position 1 and drives the sort).
> - **src/opleiding/opleiding_voordracht.inc:158** — skipped: the SQL is a commented-out `// VBS:` line (`$oTable-&gt;SQL` assignment is dead/un-ported), not a live query. Editing it would have no runtime effect.
>
> ## Already correct (no change needed)
>
> - **opleiding_toets_eerderebeoordeling.php:36** — `Opmerking, Opmerking_Oud` already sit immediately before `FROM`.
> - **utils.inc:3617** — `Opmerking, Opmerking_Oud` already sit immediately before `FROM`.
>
> ## Failed
>
> None. All 21 touched files pass `php -l`.
>
> Notes: A few queries contain additional plausibly-memo columns the audit did not flag (e.g. `tblVerslagen.Opmerking`/`Urenopmerking` in verslag.php:326, `Intro` in index.php:448, `route_aanvullend`, `draaiboekText`/`draaiboektext` in the draaiboek query). I moved only the columns named in the audit, as instructed, and left the rest in place.</result>
> <usage><subagent_tokens>94323</subagent_tokens><tool_uses>71</tool_uses><duration_ms>379694</duration_ms></usage>
> </task-notification>

> maximize a sidepanel: should stay below the header, so top:60px and i don't see the icon changes. it has a maximized class, but all is written css inline, can we move that to css?

> composer says: noting to update?

> push all changes to cma_platform to 117

> the mask-image has too thick lines, 1px please, for both lnr lnr-frame-expand and lnr lnr-frame-collapse

> yes push

> active subform tab: is flickers, can we set the active tab immediately?

> switch to mijn rino php converted repo when committed and pushed and then : 
>
> http://172.30.208.1:8090/agenda.php?events=Y&start=2024-08-26&end=2024-10-07&_=1784900917447 -> empty which is incorrect 
>
> then in agenda the right column the CalenderTable does not wrap every 7 days, it is now 1 long line with 28-31 days.

> continue

> verslagen_bekijk passes the deelnemerID not the deelnameID, please fix that

> The date IOP ingediend does not show, is that intended?

> http://172.30.208.1:8090/?pageaction=opleidingen&opleidingID=17#Toetsen -> names of the deelenmers and submitted toetsen not visible

> toetsuitwerkingen are not downoadable, they should be

> i asked you to improce the visualisation of http://172.30.208.1:8090/verslagen_bekijk.asp?ID=821 , make ut clearer that a verslag x is clickable. I see no changes

> 2 yes and after that commit and push

> just continue

> Plan mode: Investigate if miving display to Twig template is possible and potentially more stable, we have had a lot of issues with generating screens, are twif templates a possible solition? Investigate thoroughly

> and can we reuse the ajax calls already in use?

> First commit and push, start a new branch twig implementation and start working on teig templates, don’t do reports (i want another route for that), so the suggested xslt conversion is great and then try berichten/nieuws /rino info pages as simple screens

## 2026-07-25

> GET http://172.30.208.1:8090/?pageAction=deelnemers&deelnameID=216 500 (Internal Server Error)
>   GET http://172.30.208.1:8090/upload_cgoportfolio.asp?deelnameid=212&cgo_portfolioid= 403 (Forbidden)
> lib_OpenWindowCentered @ :8090/general.min.js?v=1784899882:1
> cgo_portfolio @ :8090/general.min.js?v=1784899882:1
> (anonymous) @ (unknown)

> the name twig_datastores is confusing bacause it should be twig_templates, twig is not data

> we need all xslts to be migrated, i dont see them all in the list?

> no deprecate all of them

> the determination of the match by sheer filesize is limited, can we read the content area and count the number of links and tr’s as a smoke test?

> can you create a full test for eva and her po and jgo an parallel run it?

> focus on the content area of the page

> yes please and for now skip the Draaiboeken tab if on the dashboard a button is shown (both sites please)

> skip the tab differences, focus on the draaiboeken tab first

## 2026-07-26

> [12:04:43] JS: [iframe] Uncaught TypeError: LibLog.log is not a function
>   at http://172.30.208.1:8090/cma/minify.php?f=assets/js/cma-utils.js,assets/js/url-manager.js,../library/assets/js/error-handler.js,assets/js/request-tracker.js,webcomponents/cma-base-component.js,../library/webcomponents/lib-shared-styles.js,../library/webcomponents/lib-loader.js,../library/webcomponents/lib-switch.js,../library/webcomponents/lib-radio-group.js,../library/webcomponents/lib-dialog.js,../library/webcomponents/lib-message.js,../library/webcomponents/lib-menu.js,../library/webcomponents/lib-toaster.js,../library/webcomponents/lib-search-input.js,../library/webcomponents/lib-datepicker.js,../library/webcomponents/lib-timepicker.js,../library/webcomponents/lib-histogram.js,../library/webcomponents/lib-gauge.js,../library/webcomponents/lib-combo.js,webcomponents/shared-icons.js,webcomponents/cma-blockeditor.js,webcomponents/cma-fold.js,webcomponents/cma-tree.js,webcomponents/cma-sortlist.js,webcomponents/cma-groupbox.js,webcomponents/cma-toolbar.js,webcomponents/cma-tabs.js,webcomponents/cma-launcher.js,../library/library.js,../library/formval_nl.js,../library/datepicker.js,../library/select2/select2.js,../library/webcomponents/lib-table.js,assets/js/cma.js,assets/js/cma-users.js,webcomponents/cma-htmledit.js,assets/js/blockedit.js,assets/js/table-preferences.js,assets/js/inline-edit.js,assets/js/perf-logger.js,../library/webcomponents/lib-tip.js,assets/js/cma-tours.js,assets/js/cma-list-thumb.js,assets/js/main.js&v=20260725a:70

> [12:32:03] JS: Uncaught TypeError: top.lib_OpenWindowCenteredClose is not a function
>   at http://172.30.208.1:8090/general.js?v=1785060004:1769 at login screen. the top. is not available perhaps? Why is this an issue all of a sudden?

> remove the internal info: URL-map oud↔nieuw: pad blijft gelijk, .php→.asp. Let op: bestanden onder /src/ajax/ stonden in de klassieke ASP-app in de root — die worden dus vergeleken met /<bestand>.asp. upload_handler.php wordt overgeslagen (geen ASP-tegenhanger).

> the bekijk screens are empty, should not the complete url be added?

> /upload.php bekijk
> netwerkfout
> undefined    –    0 / 0    ≠ oud[] nieuw[]    0 gedeeld    0 / -0 / +0    0 gedeeld / 0 oud / 0 nieuw
> bekijk
> Geen accounts voor rol: docent

> /export_pdf.php is die wel in gebruik? Zo nee: verplaats naar .deprecated en haal uit deze lijst

> can you open the comparison in a new page, i keep closing the main report page

> the body:has(#column_right) div#pagina {
>     width: calc(100% - 16px) !important;
> } seems to have dissapeared in the php version

> the rol_walkers ajax calls are fantastically slow, why? 
>
> http://172.30.208.1:8090/tests/roles_walk_report.php?ajax=inventory
> http://172.30.208.1:8090/tests/roles_walk_report.php?ajax=coverage
> http://172.30.208.1:8090/tests/roles_walk_report.php?ajax=diff&role=assistent
>
> still nothing after > 30 seconds

> shat is the status?

> yes please and the. the endpoints analyses

> yes!

> okay can you do a full run?

> <task-notification>
> <task-id>brsd66oo8</task-id>
> <summary>Monitor event: "role walk progress, one line per completed role"</summary>
> <event>deelnemer  http=200  9s</event>
> If this event is something the user would act on now, send a PushNotification. Routine or benign output doesn't need one.
> </task-notification>

> <task-notification>
> <task-id>brsd66oo8</task-id>
> <summary>Monitor event: "role walk progress, one line per completed role"</summary>
> <event>docent  http=500  75s</event>
> If this event is something the user would act on now, send a PushNotification. Routine or benign output doesn't need one.
> </task-notification>

> <task-notification>
> <task-id>brsd66oo8</task-id>
> <summary>Monitor event: "role walk progress, one line per completed role"</summary>
> <event>assistent  http=200  52s</event>
> If this event is something the user would act on now, send a PushNotification. Routine or benign output doesn't need one.
> </task-notification>

> <task-notification>
> <task-id>byehbpwhb</task-id>
> <tool-use-id>toolu_01Q7pXqf5hb32YfypvAisT5m</tool-use-id>
> <output-file>/tmp/claude-1000/-mnt-c-repos-cma-platform/ab294e91-daf4-4e43-b6d3-729aa854aeb9/tasks/byehbpwhb.output</output-file>
> <status>completed</status>
> <summary>Background command "Run the full role walk across all six roles" completed (exit code 0)</summary>
> </task-notification>

> <task-notification>
> <task-id>brsd66oo8</task-id>
> <summary>Monitor event: "role walk progress, one line per completed role"</summary>
> <event>contactp_inventarisatie  http=200  3s</event>
> If this event is something the user would act on now, send a PushNotification. Routine or benign output doesn't need one.
> </task-notification>

> <task-notification>
> <task-id>brsd66oo8</task-id>
> <summary>Monitor event: "role walk progress, one line per completed role"</summary>
> <event>[Monitor timed out — re-arm if needed.]</event>
> </task-notification>

> ues re run the entire test

> <task-notification>
> <task-id>bzcwoqvja</task-id>
> <summary>Monitor event: "role walk run 2 progress, one line per role"</summary>
> <event>deelnemer  http=200  20s</event>
> If this event is something the user would act on now, send a PushNotification. Routine or benign output doesn't need one.
> </task-notification>

> <task-notification>
> <task-id>bzcwoqvja</task-id>
> <summary>Monitor event: "role walk run 2 progress, one line per role"</summary>
> <event>docent  http=500  93s</event>
> If this event is something the user would act on now, send a PushNotification. Routine or benign output doesn't need one.
> </task-notification>

> skip the single_rile preference, who told you that?

> <task-notification>
> <task-id>bzcwoqvja</task-id>
> <summary>Monitor event: "role walk run 2 progress, one line per role"</summary>
> <event>praktijkopleider  http=200  45s</event>
> If this event is something the user would act on now, send a PushNotification. Routine or benign output doesn't need one.
> </task-notification>

> <task-notification>
> <task-id>bzcwoqvja</task-id>
> <summary>Monitor event: "role walk run 2 progress, one line per role"</summary>
> <event>p_praktopl  http=200  9s</event>
> If this event is something the user would act on now, send a PushNotification. Routine or benign output doesn't need one.
> </task-notification>

> <task-notification>
> <task-id>bzcwoqvja</task-id>
> <summary>Monitor event: "role walk run 2 progress, one line per role"</summary>
> <event>assistent  http=200  50s</event>
> If this event is something the user would act on now, send a PushNotification. Routine or benign output doesn't need one.
> </task-notification>

> <task-notification>
> <task-id>b7i4u3ecw</task-id>
> <tool-use-id>toolu_01SFcYwvifqkhGHtAtF9XMVn</tool-use-id>
> <output-file>/tmp/claude-1000/-mnt-c-repos-cma-platform/ab294e91-daf4-4e43-b6d3-729aa854aeb9/tasks/b7i4u3ecw.output</output-file>
> <status>completed</status>
> <summary>Background command "Run the full 7-role walk" completed (exit code 0)</summary>
> </task-notification>

> yes please do an retest both docenten roles

> <task-notification>
> <task-id>bzcwoqvja</task-id>
> <summary>Monitor event: "role walk run 2 progress, one line per role"</summary>
> <event>[Monitor timed out — re-arm if needed.]</event>
> </task-notification>

> verklaring probably wants Leertherapie or Supervisieverklaring as type parameter,
>
> now the 70s cap; that is a real issue we need to tackle, look at the medewerker role and analyse

> tell me what pages are exaclty so slow and can we investigate that because 70 seconds is aredicilous amount of time the .asp does not show that problem , does it? So  it must be something articectural and of the highest priority. Deep-think this through, you may add performance counters and log them if that helps. We will remove them later.

> òkay so we go after the deelnemers right?

> i don’t understand, how can you hit 120-340 page loads? i only asked a crawl so the endpoint tester tests all TYPES of pages, not every page! And what about throttling?

> continue please

## 2026-07-27

> okay now, the goal was to discover paths/urls that were not in the toles_walk, did that succees? Dit we raise the test-coverage?

> you say coverage is 79, so you DO know the other 21%, can you add those to the walker?  i think pageaction=404 should be redirected to the /src/pages/404.php and /planning_opleiding_verwerk_akkoord.php shoud be moved to .deprecated. Please list te unreached urls for me.

> okay, /mailtest.asp may be deprecated, it is in the cma now. pageaction=nieuws (all roles) and pageaction=sollicitaties (all roles) are testable. the /src/sync/index.php as well (assistent only). Pupops with verwijder in the name: skip, they delete records. Bewaar also. /src/ajax/delete_docentplanning_melding.php may be moved to .deprecated. Wat is grant_page? and voortgang_detail is a detail for  voortgang pages, should be testable (all roles, not comparable to an ASP page)

> form#login input:not([type="checkbox"]):not([type="radio"]):not([type="button"]):not([type="submit"]):not([type="hidden"]) {    width: 100% !important;     padding-left: 36px;} , http://172.30.208.1:8090/cma/tools/documentation.php?page=tools%3Ftool%3Ddocumentation -> remove the c.tools , add c.documentatie

> deprecate all above mentioned files

> ja graag die omzetting oppakken.

> implement either dynamic minification through minify or simply implementing general.min.js

> $MINIFY_ACTIVE = $isProduction -> i always want minification, so please skip that variable

> Arr::splitAlways($s, ',') 
>
> library/json/JSON.inc , wil je die in de cma_platform todo.md zetten dat die naar library/.deprecated verplaatst moet worden?

> Arr::splitAlways($s, ',') negeer maar even.

> can you give me a status overview of the twig migration?

> Error
> Call to undefined function cma_doc_fixers()
> in C:\repos\adam\mijnrino_php\cma\tools\documentation.php on line 1122
> CMA platform v1.29.126 ?

> The 6 entries in data-sources.json without a Twig template, make sure they are not used (double check and if not: remove them from the json)

> removeServerHeader="true" en outbound-rewrite van het Server-header — beperkt fingerprinting. -> zo staat het in de docu maar hadden we niet vastgesteld dat dat niet altijd werkt, op een oude server niet. Moet gedocumenteerd worden.

> vacatures, vacatures_detail and vacature_org can be removed

> ja haal die xslt references ook maar weg:
> en wil je dan de person twig template uitvoeren 
> layout dingetje: de annuleer knop heeft nu een chevron; dit moet een witte variant van url(/images/interface/arrow-purple.svg); worden.   
>
> wil je daarna in de ASP code kijken naar pdo_draaiboek? Want het is een raar ding, volgens mij werd alle draaiboek info dynamisch aangemaakt

>
> Server-header (je andere punt), in dezelfde commit: de docu claimde dat removeServerHeader="true" fingerprinting beperkt. Dat klopt alleen vanaf IIS 10.0. Op oudere IIS kent het configuratieschema het attribuut niet en weigert IIS de héle web.config met een 500.19 — de site is dan plat, niet alleen de header zichtbaar. Dat staat er nu bij, met de twee alternatieven: de outbound rewrite van RESPONSE_Server (met rewriteBeforeCache="true", anders mis je responses uit de kernel-cache — en http.sys-antwoorden blijven sowieso buiten bereik) en de DisableServerHeader-registrykey.
>
> ben je in staat te kijken op welke server de site draait? Zo ja dan kun je die tekst slim maken.

> expose_php = Off in php.ini gezet en herstart, kun je valideren?

> Al die documentatie / checks gaan er vanuit dat het op IIS draait, als we ooit naar Apache gaan of een andere Linux distro, leidt dat tot issues?

> /opleiding.php?pageaction=contactpersonen
> error page: Unexpected token &quot;name&quot; of value &quot;if&quot; (&quot;end of statement block&quot; expected) in &quot;_macros/person.twig&quot; a
> PHP_ERROR marker: [PHP_ERROR] Type: Twig\Error\SyntaxError | Message: Unexpected token "name" of value "if" ("end of statement block" expected) in "_macros/person.twig" at line 25 column 35. | File: C:\repos\adam\mijnrino_php\src kopieer

> ja graag een docu-hoofdstuk van maken

> caroline flipse: agenda.php weergave : Gesprekken inplannen wordt getoond mara dat hoeft niet. Daarnaast ontbreekt de optie Rapportage gesprekken en is er een script error.
