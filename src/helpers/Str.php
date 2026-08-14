<?php

namespace App\Library;

/**
 * String Helper Class
 *
 * Provides string manipulation utilities using native PHP and mbstring.
 * Maps VBScript lib_string_* functions to modern PHP equivalents.
 *
 * Key Features:
 * - Unicode-safe string operations (UTF-8)
 * - Padding, trimming, truncation
 * - Diacritic removal for URL slugs
 * - String sanitization
 *
 * Usage:
 *   $slug = Str::slug('Héllo Wörld!'); // hello-world
 *   $clean = Str::sanitize($input, 'a-zA-Z0-9');
 *   $short = Str::truncate($text, 100, '...');
 */
class Str
{
    /**
     * Remove diacritics (accents) from string
     *
     * Converts é → e, ñ → n, ü → u, etc.
     * Useful for creating URL-safe slugs
     *
     * @param string $text Text with diacritics
     * @return string Text without diacritics
     */
    /** @see removeDiacritics() */
    public static function replaceDiacritics(?string $text): string
    {
        return self::removeDiacritics($text);
    }

    public static function removeDiacritics(?string $text): string
    {
        if ($text === null) return '';
        // Transliteration map for common diacritics
        $map = [
            'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A', 'Æ' => 'AE',
            'Ç' => 'C', 'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ð' => 'D', 'Ñ' => 'N',
            'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ø' => 'O',
            'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'Ý' => 'Y', 'Þ' => 'TH', 'ß' => 'ss',
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'æ' => 'ae',
            'ç' => 'c', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ð' => 'd', 'ñ' => 'n',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ý' => 'y', 'þ' => 'th', 'ÿ' => 'y',
        ];

        return strtr($text, $map);
    }

    /**
     * Create URL-friendly slug from text
     *
     * Converts "Hello World!" → "hello-world"
     * Removes diacritics, special characters
     *
     * @param string $text Text to slugify
     * @param string $separator Separator (default: -)
     * @return string URL-safe slug
     */
    public static function slug(string $text, string $separator = '-'): string
    {
        // Remove diacritics
        $text = self::removeDiacritics($text);

        // Convert to lowercase
        $text = mb_strtolower($text, 'UTF-8');

        // Replace non-alphanumeric with separator
        $text = preg_replace('/[^a-z0-9]+/', $separator, $text);

        // Remove leading/trailing separators
        $text = trim($text, $separator);

        // Remove duplicate separators
        $text = preg_replace('/' . preg_quote($separator, '/') . '+/', $separator, $text);

        return $text;
    }

    /**
     * Pad string on the right to specified length
     *
     * @param string $text Text to pad
     * @param int $length Target length
     * @param string $pad Padding character (default: space)
     * @return string Padded string
     */
    public static function padRight(string $text, int $length, string $pad = ' '): string
    {
        return mb_str_pad($text, $length, $pad, STR_PAD_RIGHT);
    }

    /**
     * Pad string on the left to specified length
     *
     * @param string $text Text to pad
     * @param int $length Target length
     * @param string $pad Padding character (default: space)
     * @return string Padded string
     */
    public static function padLeft(string $text, int $length, string $pad = ' '): string
    {
        return mb_str_pad($text, $length, $pad, STR_PAD_LEFT);
    }

    /**
     * Truncate string to maximum length
     *
     * @param string $text Text to truncate
     * @param int $length Maximum length
     * @param string $suffix Suffix to append if truncated (default: ...)
     * @return string Truncated string
     */
    public static function truncate(string $text, int $length, string $suffix = '...'): string
    {
        if (mb_strlen($text, 'UTF-8') <= $length) {
            return $text;
        }

        $truncated = mb_substr($text, 0, $length - mb_strlen($suffix, 'UTF-8'), 'UTF-8');
        return $truncated . $suffix;
    }

    /**
     * Extract substring between two markers
     *
     * @param string $text Text to search
     * @param string $start Start marker
     * @param string $end End marker
     * @return string Text between markers, or empty string
     */
    public static function between(string $text, string $start, string $end): string
    {
        $startPos = mb_strpos($text, $start, 0, 'UTF-8');
        if ($startPos === false) {
            return '';
        }

        $startPos += mb_strlen($start, 'UTF-8');

        $endPos = mb_strpos($text, $end, $startPos, 'UTF-8');
        if ($endPos === false) {
            return '';
        }

        return mb_substr($text, $startPos, $endPos - $startPos, 'UTF-8');
    }

    /**
     * Sanitize string to only allow specific characters
     *
     * @param string $text Text to sanitize
     * @param string $allowedChars Allowed characters (regex pattern)
     * @return string Sanitized string
     */
    public static function sanitize(?string $text, string $allowedChars = 'a-zA-Z0-9'): string
    {
        if ($text === null) return '';
        return preg_replace('/[^' . $allowedChars . ']/', '', $text);
    }

    /**
     * Extract only numbers from string
     *
     * @param string $text Text containing numbers
     * @return string Only numeric characters
     */
    public static function numbersOnly(?string $text): string
    {
        if ($text === null) return '';
        return preg_replace('/[^0-9]/', '', $text);
    }

    /**
     * Extract only numbers and commas from string
     *
     * Useful for comma-separated ID lists like "1,2,3,4"
     *
     * @param string $text Text containing numbers and commas
     * @return string Only numeric characters and commas
     */
    public static function numbersOnlyAndComma(?string $text): string
    {
        if ($text === null) return '';
        return preg_replace('/[^0-9,]/', '', $text);
    }

    /**
     * Remove spaces from string
     *
     * @param string $text Text with spaces
     * @return string Text without spaces
     */
    public static function removeSpaces(?string $text): string
    {
        if ($text === null) return '';
        return str_replace(' ', '', $text);
    }

    /**
     * Custom trim function (trims whitespace and specific characters)
     *
     * @param string $text Text to trim
     * @param string $characters Characters to trim (default: whitespace)
     * @return string Trimmed string
     */
    public static function trim(?string $text, string $characters = " \t\n\r\0\x0B"): string
    {
        if ($text === null) {
            return '';
        }
        return trim($text, $characters);
    }

    /**
     * Strip patterns from end of string
     *
     * @param string $text Text to process
     * @param array $patterns Patterns to strip from end
     * @return string Text with patterns removed
     */
    public static function stripEnd(string $text, array|string $patterns): string
    {
        if (is_string($patterns)) {
            $patterns = explode(',', $patterns);
        }
        foreach ($patterns as $pattern) {
            if (mb_substr($text, -mb_strlen($pattern, 'UTF-8'), null, 'UTF-8') === $pattern) {
                $text = mb_substr($text, 0, -mb_strlen($pattern, 'UTF-8'), 'UTF-8');
            }
        }

        return $text;
    }

    /**
     * Strip patterns from start of string
     *
     * @param string $text Text to process
     * @param array $patterns Patterns to strip from start
     * @return string Text with patterns removed
     */
    public static function stripStart(string $text, array|string $patterns): string
    {
        if (is_string($patterns)) {
            $patterns = explode(',', $patterns);
        }
        foreach ($patterns as $pattern) {
            if (mb_substr($text, 0, mb_strlen($pattern, 'UTF-8'), 'UTF-8') === $pattern) {
                $text = mb_substr($text, mb_strlen($pattern, 'UTF-8'), null, 'UTF-8');
            }
        }

        return $text;
    }

    /**
     * Check if string starts with pattern
     *
     * @param string $text Text to check
     * @param string $pattern Pattern to match
     * @return bool
     */
    public static function startsWith(string $text, string $pattern): bool
    {
        return mb_substr($text, 0, mb_strlen($pattern, 'UTF-8'), 'UTF-8') === $pattern;
    }

    /**
     * Check if string ends with pattern
     *
     * @param string $text Text to check
     * @param string $pattern Pattern to match
     * @return bool
     */
    public static function endsWith(string $text, string $pattern): bool
    {
        return mb_substr($text, -mb_strlen($pattern, 'UTF-8'), null, 'UTF-8') === $pattern;
    }

    /**
     * Check if string contains pattern
     *
     * @param string $text Text to search
     * @param string $pattern Pattern to find
     * @param bool $caseSensitive Case sensitive search (default: false)
     * @return bool
     */
    public static function contains(?string $text, ?string $pattern, bool $caseSensitive = false): bool
    {
        if ($text === null || $pattern === null) return false;
        if ($caseSensitive) {
            return mb_strpos($text, $pattern, 0, 'UTF-8') !== false;
        }

        return mb_stripos($text, $pattern, 0, 'UTF-8') !== false;
    }

    /**
     * Replace all occurrences (case-insensitive option)
     *
     * @param string $text Text to search
     * @param string $search Search pattern
     * @param string $replace Replacement text
     * @param bool $caseSensitive Case sensitive (default: false)
     * @return string Modified text
     */
    public static function replace(?string $text, ?string $search, ?string $replace, bool $caseSensitive = false): string
    {
        if ($text === null) return '';
        if ($search === null || $replace === null) return $text;
        if ($caseSensitive) {
            return str_replace($search, $replace, $text);
        }

        return str_ireplace($search, $replace, $text);
    }

    /**
     * Get string length (UTF-8 safe)
     *
     * @param string $text Text to measure
     * @return int Length in characters (not bytes)
     */
    public static function length(?string $text): int
    {
        if ($text === null) return 0;
        return mb_strlen($text, 'UTF-8');
    }

    /**
     * Convert string to uppercase (UTF-8 safe)
     *
     * @param string $text Text to convert
     * @return string Uppercase text
     */
    public static function upper(?string $text): string
    {
        if ($text === null) return '';
        return mb_strtoupper($text, 'UTF-8');
    }

    /**
     * Convert string to lowercase (UTF-8 safe)
     *
     * @param string $text Text to convert
     * @return string Lowercase text
     */
    public static function lower(?string $text): string
    {
        if ($text === null) return '';
        return mb_strtolower($text, 'UTF-8');
    }

    /**
     * Capitalize first letter (UTF-8 safe)
     *
     * @param string $text Text to capitalize
     * @return string Capitalized text
     */
    public static function capitalize(string $text): string
    {
        return mb_strtoupper(mb_substr($text, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($text, 1, null, 'UTF-8');
    }

    /**
     * Make string safe for JavaScript string parameters
     *
     * Escapes special characters for use in JavaScript strings.
     * Returns the string wrapped in single quotes, ready for JS output.
     *
     * Equivalent to VBScript JScriptStrParam() function.
     *
     * @param string $text Text to escape
     * @return string JavaScript-safe string with surrounding quotes
     */
    public static function JscriptSafe(?string $text): string
    {
        // Handle null or empty values
        if ($text === null || $text === '') {
            return "''";
        }

        // Escape backslashes first
        $result = str_replace('\\', '\\\\', $text);

        // Escape double quotes
        $result = str_replace('"', '\\"', $result);

        // Escape single quotes
        $result = str_replace("'", "\\'", $result);

        // Escape square brackets using String.fromCharCode for JS compatibility
        $result = str_replace('[', "'+String.fromCharCode(91)+'", $result);
        $result = str_replace(']', "'+String.fromCharCode(93)+'", $result);

        // Escape newlines
        $result = str_replace("\r\n", "\\r\\n", $result);
        $result = str_replace("\r", "\\r\\n", $result);
        $result = str_replace("\n", "\\r\\n", $result);

        return "'" . $result . "'";
    }

    /**
     * Capitalize the first character of a string
     *
     * Converts "hello world" → "Hello world"
     * Unicode-safe version of ucfirst()
     *
     * @param string|null $text Text to capitalize
     * @return string Text with first character capitalized
     */
    public static function firstUpper(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        $firstChar = mb_substr($text, 0, 1, 'UTF-8');
        $rest = mb_substr($text, 1, null, 'UTF-8');

        return mb_strtoupper($firstChar, 'UTF-8') . $rest;
    }

    /**
     * Pad a number or string with leading zeros
     *
     * Converts 5, 2 → "05"
     * Converts 123, 5 → "00123"
     *
     * @param int|string|null $value Value to pad
     * @param int $length Target length (default: 2)
     * @return string Zero-padded string
     */
    public static function padZero($value, int $length = 2): string
    {
        if ($value === null) {
            return str_repeat('0', $length);
        }

        return str_pad((string)$value, $length, '0', STR_PAD_LEFT);
    }

    /**
     * Convert string to valid UTF-8 encoding
     *
     * Handles mixed encodings, removes invalid UTF-8 sequences,
     * and ensures the output is valid UTF-8.
     *
     * Accepts both strings and arrays. For arrays, recursively converts each string value.
     *
     * @param string|array|null $text Text or array to convert
     * @return string|array Valid UTF-8 string or array with converted values
     */
    public static function toUtf8(string|array|null $text): string|array
    {
        // Handle arrays recursively
        if (is_array($text)) {
            $result = [];
            foreach ($text as $key => $value) {
                if (is_int($key)) {
                    // Recurse into nested arrays (e.g. combo options: [0 => ['id'=>..., 'text'=>...]])
                    // but skip numeric-keyed strings (ADO duplicate fields: [0 => 'value'])
                    $result[$key] = is_array($value) ? self::toUtf8($value) : $value;
                } else {
                    $result[$key] = is_string($value) ? self::toUtf8($value) : (is_array($value) ? self::toUtf8($value) : $value);
                }
            }
            return $result;
        }

        if ($text === null || $text === '') {
            return '';
        }

        // If already valid UTF-8, return as-is
        if (mb_check_encoding($text, 'UTF-8')) {
            return $text;
        }

        // Try to detect encoding and convert
        $detected = mb_detect_encoding($text, ['UTF-8', 'Windows-1252', 'ISO-8859-1', 'ASCII'], true);

        if ($detected !== false && $detected !== 'UTF-8') {
            $converted = mb_convert_encoding($text, 'UTF-8', $detected);
            if ($converted !== false) {
                return $converted;
            }
        }

        // Fallback: remove invalid UTF-8 sequences
        return mb_convert_encoding($text, 'UTF-8', 'UTF-8');
    }

    /**
     * Het enkelvoud van een Nederlands meervoud, voor formuliertitels.
     *
     * "Deelnemers" -> "Deelnemer", zodat een detailscherm "Deelnemer bewerken"
     * kan heten waar de lijst "Deelnemers" heet. Nederlandse meervouden zijn te
     * onregelmatig voor een enkele regel, dus dit is een tabel met uitzonderingen
     * plus een reeks patronen (-en, -eren, -oren, -s, verdubbelde medeklinker).
     *
     * Woorden die geen meervoud zijn ("Nieuws", "Status") staan als uitzondering
     * genoteerd en komen ongewijzigd terug; dat geldt ook voor alles waar geen
     * patroon op past. Een titel die niet te vertalen is hoort onveranderd te
     * blijven, niet half afgekapt.
     *
     * Woont hier omdat twee migraties hem delen. Als kopie in beide bestanden
     * waren ze uit elkaar gegroeid: dezelfde titel leverde een ander enkelvoud
     * op, en welke won hing af van welke migratie als eerste geladen werd.
     */
    public static function dutchSingular(string $plural): string
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
}

/**
 * Multi-byte string padding function (PHP < 8.3 compatibility)
 */
if (!function_exists('mb_str_pad')) {
    function mb_str_pad(string $string, int $length, string $pad_string = ' ', int $pad_type = STR_PAD_RIGHT, ?string $encoding = null): string
    {
        $encoding = $encoding ?? mb_internal_encoding();
        $string_length = mb_strlen($string, $encoding);

        if ($length <= $string_length) {
            return $string;
        }

        $pad_string_length = mb_strlen($pad_string, $encoding);
        if ($pad_string_length === 0) {
            return $string;
        }

        $diff = $length - $string_length;

        if ($pad_type === STR_PAD_RIGHT) {
            $pad_repeat = (int)ceil($diff / $pad_string_length);
            return $string . mb_substr(str_repeat($pad_string, $pad_repeat), 0, $diff, $encoding);
        }

        if ($pad_type === STR_PAD_LEFT) {
            $pad_repeat = (int)ceil($diff / $pad_string_length);
            return mb_substr(str_repeat($pad_string, $pad_repeat), 0, $diff, $encoding) . $string;
        }

        // STR_PAD_BOTH
        $pad_left = (int)floor($diff / 2);
        $pad_right = $diff - $pad_left;

        $pad_left_repeat = (int)ceil($pad_left / $pad_string_length);
        $pad_right_repeat = (int)ceil($pad_right / $pad_string_length);

        return mb_substr(str_repeat($pad_string, $pad_left_repeat), 0, $pad_left, $encoding) .
               $string .
               mb_substr(str_repeat($pad_string, $pad_right_repeat), 0, $pad_right, $encoding);
    }
}
