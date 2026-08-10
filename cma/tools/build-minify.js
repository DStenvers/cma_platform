/**
 * build-minify.js — pre-build the .min.js and .min.css files.
 *
 * Usage: cd cma && npm install && npm run build:minify
 *
 * Node rather than a shell script because the sites that need this most run on
 * Windows/IIS, where there is no bash to run it with. It calls terser and
 * lightningcss as libraries, not as binaries, so there is also no dependency on
 * a .bin shim, a PATH entry or a native postinstall having placed a CLI
 * correctly. The versions pinned in package.json are the ones that get used: a
 * terser that happens to be on PATH is usually a different release, and then
 * every bundle it touches differs from the committed one — turning any build
 * into a repo-wide diff and making two machines disagree about the artifacts.
 *
 * Exit code is the number of failures, so a build step can act on it.
 */
'use strict';

const fs = require('fs');
const path = require('path');

const CMA_DIR = path.resolve(__dirname, '..');
const SITE_DIR = path.resolve(CMA_DIR, '..');

// Every directory that ships a .min must be listed here. The serving layer
// prefers a .min whenever it is not OLDER than its source, and the Installer
// gives both files the same mtime on a consumer site — so a .min that never
// gets rebuilt wins forever and edits to the source stay invisible on every
// site. Vendor trees (jcrop, select2, fineuploader) are deliberately absent:
// their .min files are shipped as-is and are not ours to rebuild.
const JS_DIRS = [
    path.join(CMA_DIR, 'webcomponents'),
    path.join(CMA_DIR, 'assets/js'),
    path.join(SITE_DIR, 'library/webcomponents'),
    path.join(SITE_DIR, 'library/assets/js'),
    path.join(SITE_DIR, 'library'),
    SITE_DIR,
    path.join(SITE_DIR, 'assets/js'),
];

const CSS_DIRS = [
    path.join(CMA_DIR, 'webcomponents'),
    path.join(CMA_DIR, 'assets/css'),
    path.join(SITE_DIR, 'library/webcomponents'),
    path.join(SITE_DIR, 'library'),
    path.join(SITE_DIR, 'library/css'),
    path.join(SITE_DIR, 'assets/css'),
];

function need(pkg, what) {
    try {
        return require(pkg);
    } catch (e) {
        console.error('ERROR: ' + pkg + ' not installed — ' + what);
        console.error('       Run: cd cma && npm install');
        process.exit(1);
    }
}

const terser = need('terser', 'needed to minify JavaScript');
const lightningcss = need('lightningcss', 'needed to minify CSS');

const stats = { built: 0, skipped: 0, errors: 0, original: 0, minified: 0 };

/** Direct .css/.js children of a directory, sorted, excluding the .min ones. */
function sources(dir, ext) {
    let names;
    try {
        names = fs.readdirSync(dir);
    } catch (e) {
        return null;                     // not a directory here — caller reports
    }
    return names
        .filter((n) => n.endsWith('.' + ext) && !n.endsWith('.min.' + ext))
        .filter((n) => fs.statSync(path.join(dir, n)).isFile())
        .sort();
}

/**
 * Write the freshly minified bytes, but only when they differ from what is
 * already there.
 *
 * When they are identical the old file stays and only its mtime moves up to the
 * source's. That touch is not cosmetic: the serving layer chooses on mtime, not
 * on content, and ignores a .min that is older than its source. Without it a
 * file with a perfectly usable .min would be served unminified forever.
 */
function writeIfChanged(src, min, bytes) {
    const before = fs.existsSync(min) ? fs.readFileSync(min) : null;
    if (before && before.equals(bytes)) {
        const s = fs.statSync(src);
        fs.utimesSync(min, s.atime, s.mtime);
        stats.skipped++;
        return null;
    }
    fs.writeFileSync(min, bytes);
    return bytes.length;
}

function report(name, originalSize, minSize) {
    const saved = originalSize - minSize;
    const pct = originalSize > 0 ? Math.floor((saved * 100) / originalSize) : 0;
    console.log('  ' + name.padEnd(40) + String(originalSize).padStart(7) +
        ' ->' + String(minSize).padStart(7) + ' (' + pct + '% saved)');
}

async function buildJs() {
    console.log('=== JS Minification Build ===\n');
    for (const dir of JS_DIRS) {
        const files = sources(dir, 'js');
        if (files === null) {
            console.log('SKIP: ' + path.relative(SITE_DIR, dir) + ' (not found)');
            continue;
        }
        console.log('Processing: ' + (path.relative(SITE_DIR, dir) || '.') + '/');
        for (const name of files) {
            const src = path.join(dir, name);
            const min = src.replace(/\.js$/, '.min.js');
            const code = fs.readFileSync(src, 'utf8');
            let out;
            try {
                out = await terser.minify(code, { compress: true, mangle: true });
            } catch (e) {
                // The previous .min stays put — a broken build must not leave
                // the site serving a truncated bundle.
                console.log('  ERROR: ' + name + ' — ' + e.message);
                stats.errors++;
                continue;
            }
            const written = writeIfChanged(src, min, Buffer.from(out.code, 'utf8'));
            if (written === null) {
                continue;
            }
            stats.original += Buffer.byteLength(code);
            stats.minified += written;
            stats.built++;
            report(name, Buffer.byteLength(code), written);
        }
        console.log('');
    }
}

function buildCss() {
    console.log('Processing CSS files...');
    for (const dir of CSS_DIRS) {
        const files = sources(dir, 'css');
        if (files === null) {
            continue;
        }
        for (const name of files) {
            const src = path.join(dir, name);
            const min = src.replace(/\.css$/, '.min.css');
            const code = fs.readFileSync(src);
            let out;
            try {
                // Pure minify: no @import bundling and no image inlining, so a
                // sibling .min.css keeps every relative url() valid. No targets
                // means no downleveling — the CSS is served as authored, smaller.
                out = lightningcss.transform({ filename: src, code: code, minify: true });
            } catch (e) {
                console.log('  ERROR: ' + name + ' — ' + e.message);
                stats.errors++;
                continue;
            }
            const written = writeIfChanged(src, min, Buffer.from(out.code));
            if (written === null) {
                continue;
            }
            stats.original += code.length;
            stats.minified += written;
            stats.built++;
            report(name, code.length, written);
        }
    }
    console.log('');
}

function iec(n) {
    const units = ['B', 'K', 'M', 'G'];
    let i = 0;
    let v = n;
    while (v >= 1024 && i < units.length - 1) { v /= 1024; i++; }
    return (i === 0 ? String(v) : v.toFixed(1)) + units[i];
}

async function main() {
    await buildJs();
    buildCss();

    const saved = stats.original - stats.minified;
    const pct = stats.original > 0 ? Math.floor((saved * 100) / stats.original) : 0;
    console.log('=== Summary ===');
    console.log('Files minified: ' + stats.built);
    console.log('Files skipped (up to date): ' + stats.skipped);
    console.log('Errors: ' + stats.errors);
    if (stats.built > 0) {
        console.log('Total original: ' + iec(stats.original));
        console.log('Total minified: ' + iec(stats.minified));
        console.log('Total savings:  ' + iec(saved) + ' (' + pct + '%)');
    }
    process.exit(stats.errors);
}

main().catch((e) => {
    console.error('build-minify failed: ' + (e && e.stack ? e.stack : e));
    process.exit(1);
});
