<?php
/**
 * Migration: Populate missing titleSingular fields in form definitions
 *
 * Scans all form definitions and adds titleSingular where missing.
 * Uses Dutch plural-to-singular conversion rules.
 *
 * Target directories:
 * - /site/assets/forms/ (site-specific forms)
 * - /site/cma/assets/forms/definitions/ (CMA core forms)
 */

$siteFormsDir = realpath(__DIR__ . '/../../assets/forms');
$cmaFormsDir = __DIR__ . '/../assets/forms/definitions';

$results = [
    'processed' => 0,
    'updated' => 0,
    'skipped' => 0,
    'errors' => [],
    'details' => [],
];

/**
 * Convert Dutch plural to singular
 * Rules:
 * - Words ending in 'en' -> remove 'en' (Opleidingen -> Opleiding)
 * - Words ending in 's' -> remove 's' (Gebruikers -> Gebruiker)
 * - Special cases handled manually
 */
function dutchPluralToSingular(string $plural): string
{
    // Special cases that don't follow standard rules
    $specialCases = [
        'Nieuws' => 'Nieuws', // Not a plural
        'Status' => 'Status', // Not a plural
        'RINO nieuws' => 'RINO nieuwsbericht', // Specific
        'Afspraken' => 'Afspraak', // en -> ak (irregular)
        'Taken' => 'Taak', // en -> ak (irregular)
        'Stukken' => 'Stuk', // ken -> k (irregular)
        'Rechten' => 'Recht', // ten -> t (irregular)
        'Kosten' => 'Kosten', // Keep as is (mass noun)
        'Gegevens' => 'Gegevens', // Keep as is (mass noun)
        'Forums' => 'Forum', // Latin plural
        'Agenda' => 'Agenda', // Keep as is
        'URLs' => 'URL',
        'Info' => 'Info',
        'Algemene info' => 'Algemene info',
        // Words with double consonant before -en
        'Blokken' => 'Blok',
        '(Ingeplande) tijdsblokken' => 'Tijdsblok',
        'Ingeplande tijdsblokken' => 'Ingepland tijdsblok',
        'Bijlagen' => 'Bijlage',
        'Bijlagen studiegids' => 'Bijlage studiegids',
        'Bijlagen beoordeling' => 'Bijlage beoordeling',
        'Beoordelingsbijlagen archief' => 'Beoordelingsbijlage archief',
        'Berichten' => 'Bericht',
        'Laatste 100 berichten' => 'Bericht',
        'Vragen' => 'Vraag',
        'Competentie template vragen' => 'Competentie template vraag',
        // Words ending in -onen -> -oon
        'Personen' => 'Persoon',
        'Contactpersonen' => 'Contactpersoon',
        'RINO contactpersonen' => 'RINO contactpersoon',
        // Words ending in -oren -> -or
        'Supervisoren' => 'Supervisor',
        // Aanwezigen special case
        'Aanwezigen' => 'Aanwezige',
        // Wijzigingen -> Wijziging
        'Draaiboek wijzigingen' => 'Draaiboek wijziging',
        // Verb forms - keep as is
        'Opleidingcode wijzigen' => 'Opleidingcode wijzigen',
        // CGO specific
        'CGO' => 'CGO',
        'CGO template' => 'CGO template',
        'CGO templates' => 'CGO template',
        'CGO vraag' => 'CGO vraag',
        // KBT specific
        'KBT' => 'KBT',
        'KBT beoordelingen' => 'KBT beoordeling',
        'KBT templates' => 'KBT template',
        // IOP
        'IOP' => 'IOP',
        // Antwoorden
        'Antwoorden' => 'Antwoord',
        'Competentie antwoorden' => 'Competentie antwoord',
        'Evaluatie verzoeken antwoorden' => 'Evaluatie verzoek antwoord',
        // Documenten
        'Documenten' => 'Document',
        'Aanmeldingsdocumenten' => 'Aanmeldingsdocument',
        'Startdocumenten per differentiatie' => 'Startdocument per differentiatie',
        // Verklaringen
        'Verklaringen' => 'Verklaring',
        // Verslagen
        'Verslagen' => 'Verslag',
        // Logins
        'Logins' => 'Login',
        // Locaties
        'Locaties' => 'Locatie',
        // Templates
        'Competentie templates' => 'Competentie template',
        'Evaluatie template' => 'Evaluatie template',
        // Subforms with 'per'
        'Uitnodiging per deelname' => 'Uitnodiging',
        'Competentie template vragen' => 'Competentie template vraag',
        'Evaluatie document' => 'Evaluatie document',
        'CGO document' => 'CGO document',
        // Opleiders
        'P-opleiders' => 'P-opleider',
        'Praktijkopleiders' => 'Praktijkopleider',
        'Praktijkopleiders bij deze P-opleider' => 'Praktijkopleider',
        // Deelnemers
        'Deelnemers' => 'Deelnemer',
        'Neemt deel aan' => 'Deelname',
        // Docenten
        'Docenten' => 'Docent',
        'Betrokken bij opleiding' => 'Betrokkenheid',
        'Hoofd/jaargroepopleider toegewezen aan' => 'Toewijzing',
        // Werkbegeleiders
        'Werkbegeleiders' => 'Werkbegeleider',
        // Vrijstellingen
        'Vrijstellingaanvragen' => 'Vrijstellingaanvraag',
        'Praktijkvrijstellingen' => 'Praktijkvrijstelling',
        'Vrijgesteld blok' => 'Vrijgesteld blok',
        // Voordrachten
        'Voordrachten' => 'Voordracht',
        'Voordrachtenpraktijkopleiders' => 'Voordracht praktijkopleider',
        // Praktijktoetsen
        'Praktijktoetsen' => 'Praktijktoets',
        // Praktijkopleidingsinstellingen
        'Praktijkopleidingsinstellingen' => 'Praktijkopleidingsinstelling',
        'Opleidingsplaatsen' => 'Opleidingsplaats',
        // Erkenningsaanvragen
        'Erkenningsaanvragen praktijkopleidingsinstelling' => 'Erkenningsaanvraag',
        // Aankondigingen
        'Aankondigingen' => 'Aankondiging',
        // Stichtingen
        'Stichtingen' => 'Stichting',
        // Rooster
        'Rooster' => 'Roosteritem',
        'Rooster downloads' => 'Download',
        'Rooster docenten' => 'Docent',
        'Rooster evaluatie verzoeken' => 'Evaluatie verzoek',
        // Dispensatie
        'Dispensatie' => 'Dispensatie',
        'Dispensatie deelnemers' => 'Dispensatie deelnemer',
        // Differentiatie
        'Differentiatie' => 'Differentiatie',
        // Gesprekstype
        'Gesprekstype' => 'Gesprekstype',
        'Bijlagen bij gesprekstype' => 'Bijlage bij gesprekstype',
        // Onderdelen
        'Onderdelen evaluatie' => 'Onderdeel evaluatie',
        // Inventarisatie
        'Inventarisatie' => 'Inventarisatie',
        'Inventarisatiegroepsomschrijving' => 'Inventarisatiegroepsomschrijving',
        'Contactpersonen inventarisatie' => 'Contactpersoon inventarisatie',
        // Toetsen
        'Toetsen' => 'Toets',
        'Toetsen archief' => 'Toets archief',
        'Toetsen per deelnemer' => 'Toets',
        // Toetsing
        'Toetsing' => 'Toetsing',
        'Toetsing bijlagen' => 'Toetsing bijlage',
        'Toetsing deelnemers' => 'Toetsing deelnemer',
        // Urentemplate
        'Urentemplate' => 'Urentemplate',
        // Snel naar
        'Snel naar' => 'Snelkoppeling',
        // Vrij blok
        'Vrij blok' => 'Vrij blok',
        // Aanwezigheid
        'Aanwezigheid' => 'Aanwezigheid',
        // Archief
        'Archief beoordelingbijlagen' => 'Archief beoordelingbijlage',
        'Archief bijlagen' => 'Archief bijlage',
        'Bijlagen archief' => 'Bijlage archief',
        // Competenties
        'Competenties' => 'Competentie',
        // Auditlog
        'Auditlog' => 'Auditlog entry',
        // Wijzigbare systeemteksten
        'Wijzigbare systeemteksten' => 'Wijzigbare systeemtekst',
        // Opleidingensoort
        'Opleidingensoort' => 'Opleidingssoort',
        // CMA specific
        'Gebruikers' => 'Gebruiker',
        'Groepen' => 'Groep',
        'Marketing URLs' => 'Marketing URL',
        'Contentblokken' => 'Contentblok',
        'CMA Monitoring' => 'Monitoring entry',
    ];

    if (isset($specialCases[$plural])) {
        return $specialCases[$plural];
    }

    // Words ending in 'ieen' -> 'ie' (e.g., "Categorieën" -> "Categorie")
    if (preg_match('/ieën$/u', $plural)) {
        return preg_replace('/ieën$/u', 'ie', $plural);
    }

    // Words ending in double consonant + 'en' -> single consonant
    // kken -> k (blokken -> blok)
    if (preg_match('/kken$/i', $plural)) {
        return preg_replace('/kken$/i', 'k', $plural);
    }
    // ssen -> s
    if (preg_match('/ssen$/i', $plural)) {
        return preg_replace('/ssen$/i', 's', $plural);
    }
    // tten -> t
    if (preg_match('/tten$/i', $plural)) {
        return preg_replace('/tten$/i', 't', $plural);
    }
    // nnen -> n
    if (preg_match('/nnen$/i', $plural)) {
        return preg_replace('/nnen$/i', 'n', $plural);
    }

    // Words ending in 'lagen' -> 'lage' (bijlagen -> bijlage)
    if (preg_match('/lagen$/i', $plural)) {
        return preg_replace('/lagen$/i', 'lage', $plural);
    }

    // Words ending in 'onen' -> 'oon' (personen -> persoon)
    if (preg_match('/onen$/i', $plural)) {
        return preg_replace('/onen$/i', 'oon', $plural);
    }

    // Words ending in 'oren' -> 'or' (supervisoren -> supervisor)
    if (preg_match('/oren$/i', $plural)) {
        return preg_replace('/oren$/i', 'or', $plural);
    }

    // Words ending in 'ingen' -> 'ing' (wijzigingen -> wijziging)
    if (preg_match('/ingen$/i', $plural)) {
        return preg_replace('/ingen$/i', 'ing', $plural);
    }

    // Words ending in 'igen' -> 'ige' (aanwezigen -> aanwezige)
    if (preg_match('/igen$/i', $plural)) {
        return preg_replace('/igen$/i', 'ige', $plural);
    }

    // Words ending in 'agen' -> 'aag' (vragen -> vraag)
    if (preg_match('/agen$/i', $plural)) {
        return preg_replace('/agen$/i', 'aag', $plural);
    }

    // Words ending in 'ten' -> 't' (berichten -> bericht)
    if (preg_match('/ten$/i', $plural)) {
        return preg_replace('/ten$/i', 't', $plural);
    }

    // Words ending in 'den' -> 'd' (honden -> hond)
    if (preg_match('/den$/i', $plural)) {
        return preg_replace('/den$/i', 'd', $plural);
    }

    // Words ending in 'en' (most common Dutch plural)
    if (preg_match('/en$/i', $plural)) {
        return preg_replace('/en$/i', '', $plural);
    }

    // Words ending in 's' (loan words, some Dutch words)
    if (preg_match('/[^s]s$/i', $plural)) {
        return preg_replace('/s$/i', '', $plural);
    }

    // No change if no pattern matches
    return $plural;
}

/**
 * Process forms in a directory
 */
function processDirectory(string $dir, array &$results): void
{
    if (!is_dir($dir)) {
        $results['errors'][] = "Directory not found: $dir";
        return;
    }

    $files = glob($dir . '/*.json');

    foreach ($files as $file) {
        $filename = basename($file);
        $results['processed']++;

        $content = file_get_contents($file);
        if ($content === false) {
            $results['errors'][] = "Failed to read: $filename";
            continue;
        }

        // Skip empty files
        if (trim($content) === '') {
            $results['skipped']++;
            continue;
        }

        $definition = json_decode($content, true);
        if ($definition === null) {
            $results['errors'][] = "Invalid JSON in: $filename";
            continue;
        }

        // Skip if already has titleSingular with a value
        if (!empty($definition['titleSingular'])) {
            $results['skipped']++;
            continue;
        }

        // Skip if no title
        if (!isset($definition['title']) || empty($definition['title'])) {
            $results['skipped']++;
            continue;
        }

        $title = $definition['title'];
        $singular = dutchPluralToSingular($title);

        // Add titleSingular
        // Insert right after title for consistency
        $newDefinition = [];
        foreach ($definition as $key => $value) {
            $newDefinition[$key] = $value;
            if ($key === 'title') {
                $newDefinition['titleSingular'] = $singular;
            }
        }

        // Write back with pretty printing
        $newContent = json_encode($newDefinition, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($newContent === false) {
            $results['errors'][] = "Failed to encode: $filename";
            continue;
        }

        if (file_put_contents($file, $newContent . "\n") === false) {
            $results['errors'][] = "Failed to write: $filename";
            continue;
        }

        $results['updated']++;
        $results['details'][$filename] = "$title -> $singular";
    }
}

// Process both directories
echo "Processing site forms: $siteFormsDir\n";
processDirectory($siteFormsDir, $results);

echo "Processing CMA forms: $cmaFormsDir\n";
processDirectory($cmaFormsDir, $results);

// Output results
echo "\n=== Populate titleSingular Migration Results ===\n\n";
echo "Files processed: {$results['processed']}\n";
echo "Files updated: {$results['updated']}\n";
echo "Files skipped (already have titleSingular): {$results['skipped']}\n";

if (!empty($results['errors'])) {
    echo "\nErrors:\n";
    foreach ($results['errors'] as $error) {
        echo "  - $error\n";
    }
}

if (!empty($results['details'])) {
    echo "\nConversions:\n";
    foreach ($results['details'] as $file => $conversion) {
        echo "  $file: $conversion\n";
    }
}

echo "\nMigration complete.\n";
