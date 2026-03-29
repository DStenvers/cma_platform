<?php
/**
 * Migration: Add titleSingular field to form definitions
 *
 * Converts Dutch plural titles to singular:
 * - "Opleidingen" -> "Opleiding" (remove 'en')
 * - "Gebruikers" -> "Gebruiker" (remove 's')
 * - "Groepen" -> "Groep" (remove 'en')
 *
 * The titleSingular is used for action labels like "Opleiding toevoegen" or "Opleiding details"
 */

$definitionsDir = __DIR__ . '/../assets/forms/definitions';

$results = [
    'processed' => 0,
    'updated' => 0,
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
        'RINO nieuws' => 'RINO nieuws', // Keep as is
        'Afspraken' => 'Afspraak', // en -> ak (irregular)
        'Taken' => 'Taak', // en -> ak (irregular)
        'Stukken' => 'Stuk', // ken -> k (irregular)
        'Rechten' => 'Recht', // ten -> t (irregular)
        'Kosten' => 'Kosten', // Keep as is (mass noun)
        'Gegevens' => 'Gegevens', // Keep as is (mass noun)
        'Forums' => 'Forum', // Latin plural
        'Agenda' => 'Agenda', // Keep as is
        'URLs' => 'URL',
        // Words with double consonant before -en
        'Blokken' => 'Blok',
        '(Ingeplande) tijdsblokken' => '(Ingeplande) tijdsblok',
        'Bijlagen' => 'Bijlage',
        'Bijlagen studiegids' => 'Bijlage studiegids',
        'Berichten' => 'Bericht',
        'Laatste 100 berichten' => 'Bericht',
        'Vragen' => 'Vraag',
        'Competentie template vragen' => 'Competentie template vraag',
        // Words ending in -onen -> -oon
        'Personen' => 'Persoon',
        'Contactpersonen' => 'Contactpersoon',
        'RINO contactpersonen' => 'RINO contactpersoon',
        // Words ending in -oren -> -or (supervisor already handled by -s rule)
        'Supervisoren' => 'Supervisor',
        // Aanwezigen special case
        'Aanwezigen' => 'Aanwezige',
        // Wijzigingen -> Wijziging
        'Draaiboek wijzigingen' => 'Draaiboek wijziging',
        // Verb forms - keep as is
        'Opleidingcode wijzigen' => 'Opleidingcode wijzigen',
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

// Get all JSON files
$files = glob($definitionsDir . '/*.json');

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
        continue;
    }

    $definition = json_decode($content, true);
    if ($definition === null) {
        $results['errors'][] = "Invalid JSON in: $filename";
        continue;
    }

    // Skip if already has titleSingular
    if (isset($definition['titleSingular'])) {
        continue;
    }

    // Skip if no title
    if (!isset($definition['title']) || empty($definition['title'])) {
        continue;
    }

    $title = $definition['title'];
    $singular = dutchPluralToSingular($title);

    // Only add if different from title
    if ($singular !== $title) {
        // Insert titleSingular right after title
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

// Output results
echo "=== Add titleSingular Migration Results ===\n\n";
echo "Files processed: {$results['processed']}\n";
echo "Files updated: {$results['updated']}\n";

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
