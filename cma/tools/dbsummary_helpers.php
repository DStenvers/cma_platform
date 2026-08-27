<?php
/**
 * dbsummary_helpers.php — de recordweergave van de database-structuurtool.
 *
 * Apart bestand zodat het zonder de tool (en dus zonder bootstrap, sessie of database)
 * te laden en te testen is: cma/tests/DbSummarySampleTest.php doet dat.
 */

// Hoeveel records de uitklapper toont. Tien is genoeg om te zien hoe de gegevens eruitzien
// en klein genoeg om een tabel met veel kolommen leesbaar te houden.
const DBSUMMARY_SAMPLE_ROWS = 10;

/**
 * Eén cel van de recordweergave.
 *
 * NULL en een lege tekst zien er in HTML allebei uit als niets, terwijl juist dat verschil
 * is waar je naar kijkt - vandaar dat NULL als zodanig wordt benoemd. Lange waarden worden
 * afgekapt: een memo- of blobkolom maakt de tabel anders onleesbaar. De volledige waarde
 * staat in de tooltip, ook afgekapt, want een blob van megabytes hoort niet in de HTML.
 */
function dbsummary_sample_cell($waarde): string
{
    if ($waarde === null) {
        return '<span class="db-sample-null">NULL</span>';
    }
    $tekst = (string)$waarde;
    if ($tekst === '') {
        return '';
    }
    if (!mb_check_encoding($tekst, 'UTF-8')) {
        $tekst = mb_convert_encoding($tekst, 'UTF-8', 'Windows-1252');
    }
    $tekst = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $tekst) ?? $tekst;

    $kort = mb_substr($tekst, 0, 80);
    if (mb_strlen($tekst) > 80) {
        return '<span title="' . htmlspecialchars(mb_substr($tekst, 0, 500)) . '">'
            . htmlspecialchars($kort) . '&hellip;</span>';
    }
    return htmlspecialchars($kort);
}
