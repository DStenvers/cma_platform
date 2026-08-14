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
