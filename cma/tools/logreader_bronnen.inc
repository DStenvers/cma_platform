<?php
/**
 * Welke logbronnen bestaan er op deze site?
 *
 * Losgetrokken van logreader.php zodat de keuze te testen is zonder bootstrap,
 * inlog en een draaiende site: het is pure logica over bestandsnamen, en juist
 * daar zaten de randgevallen (een bron met datumrotatie, een leeg pad, de bron
 * die op dat moment bekeken wordt).
 */

/**
 * Bestaat deze logbron?
 *
 * Een ontbrekend bestand is hier geen fout maar een normale toestand: de
 * cache-log verschijnt pas als er iets gecachet is, en de log voor
 * ongeautoriseerde toegang op een site waar dat nooit gebeurt helemaal nooit.
 *
 * @param string        $key        Sleutel van de bron ('perf', 'unauthorized', …).
 * @param array         $bron       De brondefinitie uit logreader.php.
 * @param array         $datumLogs  Per bron met datumrotatie de gevonden datums. Zo'n
 *                                  bron telt als aanwezig zodra er één bestand van is —
 *                                  `path` wijst namelijk naar de gekozen datum, en dat
 *                                  die dag leeg was zegt niets over de bron.
 * @param callable|null $tabelCheck Beantwoordt of de tabel achter een database-bron
 *                                  bestaat. Ontbreekt hij, dan blijft zo'n bron staan:
 *                                  niet kunnen controleren is geen reden om een
 *                                  werkende keuze weg te halen.
 */
function cma_logreader_bron_bestaat(string $key, array $bron, array $datumLogs, ?callable $tabelCheck = null): bool
{
    if (array_key_exists($key, $datumLogs)) {
        return !empty($datumLogs[$key]);
    }

    if (!empty($bron['isDatabase'])) {
        return $tabelCheck === null ? true : (bool) $tabelCheck($key, $bron);
    }

    // Een leeg pad is geen pad: ini_get('error_log') levert een lege string op
    // als er geen PHP-log is ingesteld.
    $pad = (string) ($bron['path'] ?? '');
    return $pad !== '' && is_file($pad);
}

/**
 * De bronnen die getoond mogen worden, in de volgorde waarin ze gedefinieerd zijn.
 *
 * De gekozen bron blijft er altijd bij staan, ook als het bestand er niet (meer)
 * is — anders wijst de keuzelijst iets anders aan dan wat eronder getoond wordt,
 * bijvoorbeeld vlak nadat je een log geleegd en verwijderd hebt.
 *
 * @return array Dezelfde vorm als $bronnen, alleen minder rijen.
 */
function cma_logreader_beschikbare_bronnen(array $bronnen, string $selectedLog, array $datumLogs, ?callable $tabelCheck = null): array
{
    $beschikbaar = [];
    foreach ($bronnen as $key => $bron) {
        if ((string) $key === $selectedLog
            || cma_logreader_bron_bestaat((string) $key, $bron, $datumLogs, $tabelCheck)) {
            $beschikbaar[$key] = $bron;
        }
    }
    return $beschikbaar;
}

/**
 * De laatste $aantal niet-lege regels van een logbestand, oudste eerst. Leest hoogstens $maxBytes van het einde van het bestand.
 *
 * Met $filter blijven alleen de regels over waarin die tekst voorkomt (hoofdletterongevoelig), gekozen uit het hele gelezen stuk en
 * niet alleen uit de laatste regels: zoeken moet ook iets vinden dat een paar honderd regels terug staat.
 *
 * Vroeger las de logreader vanaf het BEGIN van dat stuk en stopte na drie keer het aantal gevraagde regels; een log met meer regels
 * (het deploy-log met de composer-uitvoer is zo'n log) toonde daardoor oude regels in plaats van de nieuwste.
 *
 * @return list<string>
 */
function LogreaderLaatsteRegels(string $pad, int $aantal, string $filter = '', int $maxBytes = 2097152): array
{
    $grootte = @filesize($pad);
    if ($grootte === false || $grootte === 0 || $aantal < 1) {
        return [];
    }
    $handle = @fopen($pad, 'r');
    if (!$handle) {
        return [];
    }
    if ($grootte > $maxBytes) {
        fseek($handle, -$maxBytes, SEEK_END);
        fgets($handle); // de aangesneden eerste regel
    }
    $regels = [];
    while (($regel = fgets($handle)) !== false) {
        if (trim($regel) === '' || ($filter !== '' && stripos($regel, $filter) === false)) {
            continue;
        }
        $regels[] = $regel;
        if (count($regels) > $aantal * 2) {
            $regels = array_slice($regels, -$aantal); // houd het geheugen klein: alleen de staart telt
        }
    }
    fclose($handle);
    return count($regels) > $aantal ? array_slice($regels, -$aantal) : $regels;
}
