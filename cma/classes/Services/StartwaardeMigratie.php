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

    private static function overnemen($waarde, string $soort): array
    {
        return ['besluit' => self::OVERNEMEN, 'waarde' => $waarde, 'soort' => $soort, 'reden' => ''];
    }

    private static function overslaan($waarde, string $soort, string $reden): array
    {
        return ['besluit' => self::OVERSLAAN, 'waarde' => $waarde, 'soort' => $soort, 'reden' => $reden];
    }
}
