<?php
namespace Cma\Services;

/**
 * StartwaardeMigratie — de startwaarden uit repository.mdb alsnog in de JSON zetten.
 *
 * WAT ER MIS GING. Formulierdefinities stonden vroeger in repository.mdb; tblControls
 * had daar een kolom schema_default met de standaardwaarde van het veld. De omzetting
 * naar JSON (tools_generate_forms.php) las die kolom niet. 430 velden verloren daarmee
 * hun startwaarde — waaronder 73 vinkjes die bij een nieuw record aan hoorden te staan
 * en sindsdien uit stonden. Zonder foutmelding: de waarde was er gewoon niet meer.
 *
 * WAAROM NIET ALLES OVERGENOMEN WORDT. schema_default is een Access-uitdrukking, geen
 * waarde. Naast True en 14 staan er dingen als GenGUID(), Now() en Date()+60 in. Die
 * worden door de database ingevuld op het moment van opslaan; ze hebben in het scherm
 * niets te zoeken — als data-default zou de gebruiker letterlijk de tekst "Now()" in
 * het veld krijgen. Overgenomen wordt daarom alleen wat als losse waarde te lezen is:
 *
 *   True/False/-1/0   → een schakelaar aan of uit
 *   een getal         → dat getal
 *   "tekst" of 'tekst' → die tekst, zonder de aanhalingstekens
 *   #01-01-2014#      → die datum
 *
 * en van die groep blijven twee soorten alsnog liggen omdat ze niets toevoegen:
 * een lege tekst ("" of ''), en een uit-waarde — een veld zonder startwaarde staat al
 * leeg en een schakelaar staat al uit. Wat overblijft is de winst; de rest wordt
 * gerapporteerd met de reden, zodat er niets ongezien verdwijnt.
 *
 * De klasse doet zelf geen database en geen bestanden: hij beoordeelt waarden en
 * bepaalt wat een formulier zou worden. tools_form_defaults.php haalt de gegevens op,
 * toont het verslag en voert het pas door als daarom wordt gevraagd.
 */
class StartwaardeMigratie
{
    /** Uitkomsten van beoordeel(). */
    public const OVERNEMEN  = 'overnemen';
    public const OVERSLAAN  = 'overslaan';

    /**
     * Wat betekent deze schema_default, en nemen we hem over?
     *
     * @param mixed $ruw De waarde zoals hij in tblControls.schema_default staat.
     * @param bool  $isSchakelaar Gaat het om een vinkje/schakelaar (controltype 5)?
     * @return array{besluit:string, waarde:mixed, soort:string, reden:string}
     */
    public static function beoordeel($ruw, bool $isSchakelaar): array
    {
        $tekst = trim((string) ($ruw ?? ''));

        if ($tekst === '') {
            return self::overslaan(null, 'leeg', 'geen startwaarde vastgelegd');
        }

        $klein = strtolower($tekst);

        // Waar/onwaar, in alle schrijfwijzen die Access gebruikt.
        if (in_array($klein, ['true', '-1', 'yes', 'ja'], true)) {
            return self::overnemen(true, 'aan');
        }
        if (in_array($klein, ['false', '0', 'no', 'nee'], true)) {
            return self::overslaan(false, 'uit', 'uit is al het gedrag zonder startwaarde');
        }

        // Een kaal getal.
        if (preg_match('/^-?\d+(\.\d+)?$/', $tekst) === 1) {
            $getal = strpos($tekst, '.') === false ? (int) $tekst : (float) $tekst;
            // Op een schakelaar is een getal alsnog aan of uit.
            if ($isSchakelaar) {
                return $getal == 0
                    ? self::overslaan(false, 'uit', 'uit is al het gedrag zonder startwaarde')
                    : self::overnemen(true, 'aan');
            }
            return self::overnemen($getal, 'getal');
        }

        // Een tekst tussen aanhalingstekens: die horen bij de Access-uitdrukking,
        // niet bij de waarde.
        if (preg_match('/^"(.*)"$/s', $tekst, $m) === 1 || preg_match("/^'(.*)'\$/s", $tekst, $m) === 1) {
            $inhoud = $m[1];
            if ($inhoud === '') {
                return self::overslaan('', 'lege tekst', 'een veld zonder startwaarde is al leeg');
            }
            return self::overnemen($inhoud, 'tekst');
        }

        // Een datumliteraal: #01-01-2014#
        if (preg_match('/^#(.+)#$/', $tekst, $m) === 1) {
            return self::overnemen($m[1], 'datum');
        }

        // Al het overige is een uitdrukking die de database uitrekent.
        return self::overslaan(null, 'uitdrukking', 'wordt door de database ingevuld, niet door het scherm');
    }

    /**
     * De startwaarden in een formulierdefinitie zetten.
     *
     * Raakt alleen velden die nog géén defaultValue hebben — een definitie die het
     * inmiddels zelf vastlegt wint, want die is bewust zo gezet.
     *
     * @param array $definitie De JSON-definitie (wordt niet gewijzigd).
     * @param array $perVeld   veldnaam (kleine letters) => waarde
     * @return array{definitie:array, gezet:array, overgeslagen:array}
     */
    public static function toepassen(array $definitie, array $perVeld): array
    {
        $gezet = [];
        $overgeslagen = [];
        $velden = $definitie['fields'] ?? [];

        foreach ($velden as $i => $veld) {
            $naam = strtolower((string) ($veld['name'] ?? ''));
            if ($naam === '' || !array_key_exists($naam, $perVeld)) {
                continue;
            }
            if (array_key_exists('defaultValue', $veld)) {
                $overgeslagen[$naam] = 'het formulier legt de startwaarde zelf al vast';
                continue;
            }
            // Waar/onwaar hoort op een schakelaar. Staat er in de JSON een tekstvak
            // (dat komt voor: een Ja/Nee-kolom die als textbox is omgezet), dan zou
            // de gebruiker letterlijk een "1" in het invoervak krijgen. Liever
            // niets dan dat, mét vermelding — het veld zelf klopt dan namelijk niet.
            if (is_bool($perVeld[$naam]) && ($veld['type'] ?? '') !== 'checkbox') {
                $overgeslagen[$naam] = 'de repository zegt vinkje, de definitie zegt '
                    . ($veld['type'] ?? '(geen type)');
                continue;
            }
            // Achter 'caption' invoegen leest het prettigst; staat die er niet,
            // dan achteraan.
            $nieuw = [];
            $geplaatst = false;
            foreach ($veld as $sleutel => $waarde) {
                $nieuw[$sleutel] = $waarde;
                if ($sleutel === 'caption') {
                    $nieuw['defaultValue'] = $perVeld[$naam];
                    $geplaatst = true;
                }
            }
            if (!$geplaatst) {
                $nieuw['defaultValue'] = $perVeld[$naam];
            }
            $velden[$i] = $nieuw;
            $gezet[$naam] = $perVeld[$naam];
        }

        $definitie['fields'] = $velden;
        return ['definitie' => $definitie, 'gezet' => $gezet, 'overgeslagen' => $overgeslagen];
    }




    /**
     * De startwaarden als losse regels in de bestandstekst zetten.
     *
     * WAAROM NIET GEWOON OPNIEUW CODEREN. Dat is wat er eerst gebeurde, en het
     * herschreef elk bestand van de eerste tot de laatste regel: json_encode springt
     * met vier spaties in terwijl de meeste definities er acht gebruiken, en over de
     * schuine strepen zijn de bestanden het onderling niet eens — acht ervan zijn het
     * zelfs met zichzelf oneens. Het gevolg is dat de ene regel die er werkelijk toe
     * doet verdwijnt in duizenden regels die niets veranderen, en dan is een wijziging
     * niet meer na te kijken.
     *
     * Dus: de tekst blijft zoals hij is, op één toegevoegde regel per veld na. De
     * regel komt achter "caption" (of achter "name" als er geen caption is), met de
     * inspringing van zijn buren. Staat de ankerregel als laatste in het blok, dan
     * krijgt die alsnog zijn komma en de nieuwe regel niet.
     *
     * Er wordt alleen gekeken binnen "fields": een veldnaam kan ook in listColumns of
     * in een subformulier voorkomen, en daar hoort geen startwaarde.
     *
     * @param string $origineel De inhoud van het bestand.
     * @param array  $perVeld   veldnaam (kleine letters) => waarde
     * @param array  $gezet     De velden die volgens toepassen() gezet mogen worden.
     * @return array{tekst:string, gezet:array, mislukt:array}
     */
    public static function invoegen(string $origineel, array $perVeld, array $gezet): array
    {
        $regels = preg_split('/\r\n|\n|\r/', $origineel);
        $eindeRegel = strpos($origineel, "\r\n") !== false ? "\r\n" : "\n";

        [$van, $tot] = self::veldenBereik($regels);
        if ($van === null) {
            return ['tekst' => $origineel, 'gezet' => [], 'mislukt' => array_keys($gezet)];
        }

        $klaar = [];
        $mislukt = [];

        foreach ($gezet as $naam => $waarde) {
            $anker = self::ankerRegel($regels, $van, $tot, $naam);
            if ($anker === null) {
                $mislukt[] = $naam;
                continue;
            }

            preg_match('/^(\s*)/', $regels[$anker], $m);
            $inspring = $m[1];
            $heeftKomma = substr(rtrim($regels[$anker]), -1) === ',';
            $nieuw = $inspring . '"defaultValue": ' . json_encode($waarde) . ($heeftKomma ? ',' : '');
            if (!$heeftKomma) {
                $regels[$anker] = rtrim($regels[$anker]) . ',';
            }

            array_splice($regels, $anker + 1, 0, [$nieuw]);
            $tot++;
            $klaar[$naam] = $waarde;
        }

        return ['tekst' => implode($eindeRegel, $regels), 'gezet' => $klaar, 'mislukt' => $mislukt];
    }

    /**
     * De regelnummers waartussen de "fields"-lijst staat, op haakjesdiepte geteld.
     *
     * @return array{0:?int,1:?int}
     */
    private static function veldenBereik(array $regels): array
    {
        $van = null;
        foreach ($regels as $i => $regel) {
            if (preg_match('/^\s*"fields"\s*:\s*\[/', $regel) === 1) {
                $van = $i;
                break;
            }
        }
        if ($van === null) {
            return [null, null];
        }

        $diepte = 0;
        for ($i = $van; $i < count($regels); $i++) {
            $diepte += substr_count($regels[$i], '[') - substr_count($regels[$i], ']');
            if ($i > $van && $diepte <= 0) {
                return [$van, $i];
            }
        }
        return [$van, count($regels) - 1];
    }

    /**
     * De regel waarachter de startwaarde van dit veld hoort: zijn caption, anders
     * zijn naam. Null als het veld er niet staat, of al een defaultValue heeft.
     */
    private static function ankerRegel(array $regels, int $van, int $tot, string $naam): ?int
    {
        for ($i = $van; $i <= $tot; $i++) {
            if (preg_match('/^\s*"name"\s*:\s*"(.*)"\s*,?\s*$/', $regels[$i], $m) !== 1) {
                continue;
            }
            if (strcasecmp($m[1], $naam) !== 0) {
                continue;
            }

            // Binnen dit veldblok: tot de volgende "name" of het einde van de lijst.
            $eind = $tot;
            for ($j = $i + 1; $j <= $tot; $j++) {
                if (preg_match('/^\s*"name"\s*:/', $regels[$j]) === 1) {
                    $eind = $j - 1;
                    break;
                }
            }

            $anker = $i;
            for ($j = $i + 1; $j <= $eind; $j++) {
                if (preg_match('/^\s*"defaultValue"\s*:/', $regels[$j]) === 1) {
                    return null;
                }
                if (preg_match('/^\s*"caption"\s*:/', $regels[$j]) === 1) {
                    $anker = $j;
                }
            }
            return $anker;
        }
        return null;
    }

    private static function overnemen($waarde, string $soort): array
    {
        return ['besluit' => self::OVERNEMEN, 'waarde' => $waarde, 'soort' => $soort, 'reden' => ''];
    }

    private static function overslaan($waarde, string $soort, string $reden): array
    {
        return ['besluit' => self::OVERSLAAN, 'waarde' => $waarde, 'soort' => $soort, 'reden' => $reden];
    }
}
