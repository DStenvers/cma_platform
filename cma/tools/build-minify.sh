#!/bin/bash
# build-minify.sh - Pre-build .min.js files using terser
#
# Usage: bash tools/build-minify.sh
#
# Finds all .js files in webcomponents/ and ../library/webcomponents/,
# runs terser --compress --mangle on each, outputting .min.js alongside the original.
# Also minifies CSS files in webcomponents/.
#
# If terser is not available, exits with an error.

set -e

# Resolve script directory to find project root
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
CMA_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
SITE_DIR="$(cd "$CMA_DIR/.." && pwd)"

# Discover terser binary. The version pinned in package.json comes FIRST: a
# terser that happens to be on PATH is usually a different (older) release, and
# then every bundle it touches differs from the committed one — turning any build
# into a repo-wide diff and making two machines disagree about the artifacts.
TERSER=""
if [ -x "$CMA_DIR/node_modules/.bin/terser" ]; then
    TERSER="$CMA_DIR/node_modules/.bin/terser"
elif command -v terser &> /dev/null; then
    TERSER="$(command -v terser)"
else
    # Check known install locations
    for candidate in /usr/bin/terser /usr/local/bin/terser /usr/lib/node_modules/.bin/terser; do
        if [ -x "$candidate" ]; then
            TERSER="$candidate"
            break
        fi
    done
    # Check NVM paths
    if [ -z "$TERSER" ] && [ -n "$HOME" ]; then
        for candidate in "$HOME"/.nvm/versions/node/*/bin/terser; do
            if [ -x "$candidate" ]; then
                TERSER="$candidate"
                break
            fi
        done
    fi
fi

if [ -z "$TERSER" ]; then
    echo "ERROR: terser not found. Install with: npm install -g terser"
    echo "Checked: PATH, /usr/bin, /usr/local/bin, NVM paths"
    exit 1
fi
echo "Using terser: $TERSER"

# CSS minifier: the lightningcss LIBRARY via a node wrapper (tools/minify-css.js),
# invoked like terser (node <wrapper> <src> <out>). Using the library avoids
# lightningcss-cli's fragile native-binary postinstall. Needs node + the wrapper +
# the lightningcss package (cd cma && npm install).
NODE_BIN=""
if command -v node &> /dev/null; then
    NODE_BIN="$(command -v node)"
fi
CSS_WRAPPER="$CMA_DIR/tools/minify-css.js"
CSS_MINIFY_OK=""
if [ -n "$NODE_BIN" ] && [ -f "$CSS_WRAPPER" ] && [ -d "$CMA_DIR/node_modules/lightningcss" ]; then
    CSS_MINIFY_OK="1"
    echo "Using lightningcss (library) via node $CSS_WRAPPER"
else
    echo "WARN: lightningcss/node not available (run: cd cma && npm install) — CSS will NOT be minified."
fi

# Counters
total_files=0
total_original=0
total_minified=0
skipped=0
errors=0

# Directories to process
JS_DIRS=(
    "$CMA_DIR/webcomponents"
    "$CMA_DIR/assets/js"
    "$SITE_DIR/library/webcomponents"
    "$SITE_DIR/library/assets/js"
    "$SITE_DIR/library"
    "$SITE_DIR"
    "$SITE_DIR/assets/js"
)

echo "=== JS Minification Build ==="
echo ""

for dir in "${JS_DIRS[@]}"; do
    if [ ! -d "$dir" ]; then
        echo "SKIP: $dir (not found)"
        continue
    fi

    rel_dir="${dir#$SITE_DIR/}"
    echo "Processing: $rel_dir/"

    # Find all .js files, excluding .min.js
    while IFS= read -r -d '' jsfile; do
        basename=$(basename "$jsfile")
        minfile="${jsfile%.js}.min.js"

        # Always minify, then keep the result only when it differs. mtimes are
        # not a usable freshness signal: a git checkout stamps every file with
        # the checkout time in arbitrary order, so an mtime comparison silently
        # skipped sources whose .min was months out of date.
        orig_size=$(wc -c < "$jsfile")
        total_original=$((total_original + orig_size))

        # Run terser
        if "$TERSER" "$jsfile" --compress --mangle -o "$minfile.tmp" 2>/dev/null; then
            if [ -f "$minfile" ] && cmp -s "$minfile.tmp" "$minfile"; then
                rm -f "$minfile.tmp"
                # Inhoud gelijk, maar de mtime moet alsnog kloppen: de serveerlaag
                # kiest op mtime, niet op inhoud. Een .min die ouder is dan zijn bron
                # wordt genegeerd, dus zonder deze touch blijft een bestand met een
                # perfect bruikbare .min voor altijd onverkleind uitgeserveerd.
                touch -r "$jsfile" "$minfile"
                total_original=$((total_original - orig_size))
                skipped=$((skipped + 1))
                continue
            fi
            mv -f "$minfile.tmp" "$minfile"
            min_size=$(wc -c < "$minfile")
            total_minified=$((total_minified + min_size))
            savings=$((orig_size - min_size))
            if [ "$orig_size" -gt 0 ]; then
                pct=$((savings * 100 / orig_size))
            else
                pct=0
            fi
            printf "  %-40s %6d -> %6d (%d%% saved)\n" "$basename" "$orig_size" "$min_size" "$pct"
            total_files=$((total_files + 1))
        else
            echo "  ERROR: $basename"
            errors=$((errors + 1))
            total_original=$((total_original - orig_size))
            # Leave the previous .min.js in place; only the failed attempt goes.
            rm -f "$minfile.tmp"
        fi
    done < <(find "$dir" -maxdepth 1 -name "*.js" ! -name "*.min.js" -print0 | sort -z)

    echo ""
done

# CSS minification (lightningcss library via node wrapper — the single CSS minifier,
# counterpart of terser). Pure minify (no @import inlining, no downleveling); a
# sibling .min.css keeps relative url() valid. Skipped entirely if unavailable
# (JS is still built above).
# Every directory that ships a .min.css must be listed here. minify.php serves
# the .min.css whenever it is not OLDER than the source, and the Installer gives
# both files the same mtime on a consumer site — so a min that never gets rebuilt
# wins forever and the source edits are invisible on every site. Vendor trees
# (jcrop, select2) are deliberately absent: their min files are shipped as-is.
CSS_DIRS=(
    "$CMA_DIR/webcomponents"
    "$CMA_DIR/assets/css"
    "$SITE_DIR/library/webcomponents"
    "$SITE_DIR/library"
    "$SITE_DIR/library/css"
    "$SITE_DIR/assets/css"
)

echo "Processing CSS files..."
for dir in "${CSS_DIRS[@]}"; do
    [ -d "$dir" ] || continue
    for cssfile in "$dir"/*.css; do
    [ -f "$cssfile" ] || continue
    case "$cssfile" in *.min.css) continue;; esac
    [ -z "$CSS_MINIFY_OK" ] && continue
    basename=$(basename "$cssfile")
    minfile="${cssfile%.css}.min.css"

    orig_size=$(wc -c < "$cssfile")
    # In een `if` en MET de melding erbij, net als de terser-lus hierboven. Kaal aanroepen
    # met 2>/dev/null was dodelijk: dit script draait onder `set -e`, dus een wrapper die
    # afbreekt nam het hele bouwproces mee vóórdat de ERROR-tak eronder kon melden waarom.
    # Waargenomen op een site waar lightningcss wél in node_modules stond maar zonder zijn
    # native binding (lightningcss.linux-x64-gnu.node, een optionele dependency die bij een
    # install op een ander platform overgeslagen wordt): `npm run build:minify` stopte na
    # "Processing CSS files..." met exit 3, zonder één regel uitleg, en elke .min.css bleef
    # maanden verouderd achter — onzichtbaar, want de serveerlaag valt dan netjes terug op
    # de bron.
    css_fout=""
    if ! css_fout=$("$NODE_BIN" "$CSS_WRAPPER" "$cssfile" "$minfile.tmp" 2>&1) \
       || [ ! -s "$minfile.tmp" ]; then
        echo "  ERROR: $basename${css_fout:+ — ${css_fout%%$'\n'*}}"
        errors=$((errors + 1))
        rm -f "$minfile.tmp"
        continue
    fi
    if [ -f "$minfile" ] && cmp -s "$minfile.tmp" "$minfile"; then
        rm -f "$minfile.tmp"
        touch -r "$cssfile" "$minfile"   # zie de toelichting bij de JS-lus hierboven
        skipped=$((skipped + 1))
        continue
    fi
    mv -f "$minfile.tmp" "$minfile"
    min_size=$(wc -c < "$minfile")
    savings=$((orig_size - min_size))
    if [ "$orig_size" -gt 0 ]; then
        pct=$((savings * 100 / orig_size))
    else
        pct=0
    fi
    printf "  %-40s %6d -> %6d (%d%% saved)\n" "$basename" "$orig_size" "$min_size" "$pct"
    total_files=$((total_files + 1))
    done
done
echo ""

# Summary
total_savings=$((total_original - total_minified))
if [ "$total_original" -gt 0 ]; then
    total_pct=$((total_savings * 100 / total_original))
else
    total_pct=0
fi

echo "=== Summary ==="
echo "Files minified: $total_files"
echo "Files skipped (up to date): $skipped"
echo "Errors: $errors"
if [ "$total_files" -gt 0 ]; then
    echo "Total original: $(numfmt --to=iec $total_original 2>/dev/null || echo "${total_original} bytes")"
    echo "Total minified: $(numfmt --to=iec $total_minified 2>/dev/null || echo "${total_minified} bytes")"
    echo "Total savings:  $(numfmt --to=iec $total_savings 2>/dev/null || echo "${total_savings} bytes") (${total_pct}%)"
fi

exit $errors
