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

# Discover terser binary - PATH may not include npm/nvm bin dirs
TERSER=""
if command -v terser &> /dev/null; then
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
    "$SITE_DIR/library"
    "$SITE_DIR"
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

        # Skip if .min.js is already newer than source
        if [ -f "$minfile" ] && [ "$minfile" -nt "$jsfile" ]; then
            skipped=$((skipped + 1))
            continue
        fi

        # Get original size
        orig_size=$(wc -c < "$jsfile")
        total_original=$((total_original + orig_size))

        # Run terser
        if "$TERSER" "$jsfile" --compress --mangle -o "$minfile" 2>/dev/null; then
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
            # Remove failed .min.js if it exists
            rm -f "$minfile"
        fi
    done < <(find "$dir" -maxdepth 1 -name "*.js" ! -name "*.min.js" -print0 | sort -z)

    echo ""
done

# CSS minification (simple whitespace/comment strip)
echo "Processing CSS files..."
for cssfile in "$CMA_DIR"/webcomponents/*.css; do
    [ -f "$cssfile" ] || continue
    basename=$(basename "$cssfile")
    minfile="${cssfile%.css}.min.css"

    if [ -f "$minfile" ] && [ "$minfile" -nt "$cssfile" ]; then
        skipped=$((skipped + 1))
        continue
    fi

    orig_size=$(wc -c < "$cssfile")
    # Simple CSS minification: strip comments, collapse whitespace
    sed -e 's|/\*[^*]*\*\+\([^/][^*]*\*\+\)*/||g' \
        -e 's/^[[:space:]]*//g' \
        -e 's/[[:space:]]*$//g' \
        -e '/^$/d' \
        -e 's/[[:space:]]\{2,\}/ /g' \
        "$cssfile" > "$minfile"
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
