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
