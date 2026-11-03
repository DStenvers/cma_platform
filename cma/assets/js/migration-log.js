/**
 * Colour the execution log of Platform updates (tools_migrations.php), shown
 * in a .log-output box.
 *
 * A log entry can hold several lines: a failing script's whole output arrives as
 * one entry. Each physical line gets its own colour from its marker:
 *   ✓ ok · ✗ / MISLUKT error · ⊘ / ⚠ / Waarschuwing warning · ━ / ═ heading ·
 *   "Versie …" version header.
 * An indented line without a marker continues the line above it and takes that
 * colour, so "  - <reason>" under "✗ Fout:" stays red. A skipped file (⊘) is
 * not an error and stays yellow, also inside a failed migration's output.
 */
(function () {
    'use strict';

    function escapeHtml(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    // The class for a line, from its marker (classes of .log-output in style.css)
    function soort(regel) {
        if (regel.indexOf('✓') !== -1) return 'log-success';
        if (regel.indexOf('✗') !== -1 || regel.indexOf('MISLUKT') !== -1) return 'log-error';
        if (regel.indexOf('⊘') !== -1 || regel.indexOf('⚠') !== -1 || regel.indexOf('Waarschuwing') !== -1) return 'log-warning';
        if (regel.indexOf('━') !== -1 || regel.indexOf('═') !== -1) return 'log-heading';
        if (regel.indexOf('Versie ') === 0) return 'log-version';
        return null;
    }

    /**
     * @param {string[]} entries Log entries, each possibly multi-line
     * @return {string} HTML, one line per physical line
     */
    window.cmaMigrationLogHtml = function (entries) {
        var vorige = null;
        var uit = [];
        (entries || []).forEach(function (entry) {
            String(entry).split('\n').forEach(function (regel) {
                var s = soort(regel);
                if (s === null) {
                    s = /^\s+\S/.test(regel) ? vorige : null;
                }
                vorige = s;
                uit.push(s ? '<span class="' + s + '">' + escapeHtml(regel) + '</span>' : escapeHtml(regel));
            });
        });
        return uit.join('\n');
    };
})();
