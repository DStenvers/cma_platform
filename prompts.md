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
