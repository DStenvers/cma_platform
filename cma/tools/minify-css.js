/**
 * minify-css.js — minify one CSS file with the lightningcss LIBRARY.
 *
 * Invoked like terser (node <script> <in> <out>) by the cache-clear tool
 * (tools_clearcache.php). Using the library + this wrapper
 * instead of `lightningcss-cli` avoids the CLI's fragile native-binary
 * postinstall ("Failed to move binary into place") on Windows — the library
 * loads its napi binding directly, no move step.
 *
 *   Usage:  node minify-css.js <source.css> <output.min.css>
 *   Exit:   0 ok · 1 minify error · 2 bad args · 3 lightningcss not installed
 *
 * Pure minify: transform() does NOT bundle @import and does NOT inline images,
 * so a sibling .min.css keeps every relative url() valid. No `targets` = no
 * downleveling/prefixing (the CSS is served as-authored, only smaller).
 */
'use strict';
const fs = require('fs');

const src = process.argv[2];
const out = process.argv[3];
if (!src || !out) {
    console.error('usage: node minify-css.js <source.css> <output.min.css>');
    process.exit(2);
}

let transform;
try {
    ({ transform } = require('lightningcss'));
} catch (e) {
    console.error('lightningcss not installed (cd cma && npm install): ' + e.message);
    process.exit(3);
}

try {
    const { code } = transform({
        filename: src,
        code: fs.readFileSync(src),
        minify: true,
    });
    fs.writeFileSync(out, code);
} catch (e) {
    console.error('minify failed for ' + src + ': ' + e.message);
    process.exit(1);
}
