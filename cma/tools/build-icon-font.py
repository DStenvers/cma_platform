#!/usr/bin/env python3
"""
Build optimized Linearicons icon font subset.

Reads icon codes from shared-icons.js and creates an optimized subset font
containing only the glyphs actually used. The full font is preserved in
library/fonts/Linearicons/Font/ for the storybook.

Usage:
    python3 tools/build-icon-font.py

Requirements:
    pip install fonttools brotli
"""

import os
import re
import sys
import shutil
import subprocess

# Paths relative to CMA root
SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
CMA_ROOT = os.path.dirname(SCRIPT_DIR)
SITE_ROOT = os.path.dirname(CMA_ROOT)

SHARED_ICONS_JS = os.path.join(CMA_ROOT, 'webcomponents', 'shared-icons.js')
FULL_TTF = os.path.join(SITE_ROOT, 'library', 'fonts', 'Linearicons', 'Font', 'Linearicons.ttf')
FONTS_DIR = os.path.join(SITE_ROOT, 'library', 'fonts')
FULL_FONT_DIR = os.path.join(FONTS_DIR, 'Linearicons', 'Font')

# Output paths
OUT_TTF = os.path.join(FONTS_DIR, 'Linearicons.ttf')
OUT_WOFF = os.path.join(FONTS_DIR, 'Linearicons.woff')
OUT_WOFF2 = os.path.join(FONTS_DIR, 'Linearicons.woff2')
FULL_WOFF = os.path.join(FULL_FONT_DIR, 'Linearicons.woff')
FULL_WOFF2 = os.path.join(FULL_FONT_DIR, 'Linearicons.woff2')


def parse_icon_codes():
    """Extract unique hex codes from shared-icons.js ICON_CODES."""
    with open(SHARED_ICONS_JS, 'r') as f:
        content = f.read()

    # Match patterns like: 'icon-name': 'e6b4'
    codes = set(re.findall(r"'[a-z0-9-]+':\s*'([a-f0-9]+)'", content))

    if not codes:
        print("ERROR: No icon codes found in shared-icons.js")
        sys.exit(1)

    return sorted(codes)


def format_size(size_bytes):
    """Format file size for display."""
    if size_bytes < 1024:
        return f"{size_bytes} B"
    elif size_bytes < 1024 * 1024:
        return f"{size_bytes / 1024:.1f} KB"
    else:
        return f"{size_bytes / (1024 * 1024):.1f} MB"


def get_file_size(path):
    """Get file size, return 0 if file doesn't exist."""
    try:
        return os.path.getsize(path)
    except OSError:
        return 0


def run_pyftsubset(input_ttf, output_path, unicodes, flavor=None, full=False):
    """Run pyftsubset to create a font subset."""
    cmd = [
        sys.executable, '-m', 'fontTools.subset',
        input_ttf,
        f'--unicodes={unicodes}',
        f'--output-file={output_path}',
        '--notdef-glyph',
        '--notdef-outline',
    ]
    if full:
        # Keep everything for the full font conversion
        cmd.extend([
            '--layout-features=*',
            '--glyph-names',
            '--symbol-cmap',
            '--legacy-cmap',
            '--recommended-glyphs',
        ])
    else:
        # Aggressive subset for production
        cmd.extend([
            '--layout-features=',
            '--no-glyph-names',
            '--no-symbol-cmap',
            '--no-legacy-cmap',
            '--no-recommended-glyphs',
            '--desubroutinize',
        ])
    if flavor:
        cmd.append(f'--flavor={flavor}')

    result = subprocess.run(cmd, capture_output=True, text=True)
    if result.returncode != 0:
        print(f"ERROR running pyftsubset: {result.stderr}")
        sys.exit(1)


def generate_full_web_fonts():
    """Generate WOFF and WOFF2 from the full TTF for storybook use."""
    print("\n--- Full font (for storybook) ---")

    # WOFF
    if not os.path.exists(FULL_WOFF):
        print(f"  Generating full WOFF...")
        run_pyftsubset(FULL_TTF, FULL_WOFF, 'U+0000-FFFF', flavor='woff', full=True)
    else:
        print(f"  Full WOFF already exists")
    print(f"  WOFF:  {format_size(get_file_size(FULL_WOFF))}")

    # WOFF2
    if not os.path.exists(FULL_WOFF2):
        print(f"  Generating full WOFF2...")
        run_pyftsubset(FULL_TTF, FULL_WOFF2, 'U+0000-FFFF', flavor='woff2', full=True)
    else:
        print(f"  Full WOFF2 already exists")
    print(f"  WOFF2: {format_size(get_file_size(FULL_WOFF2))}")


def generate_subset(codes):
    """Generate optimized subset fonts from the icon codes."""
    # Build unicode range string: U+e600,U+e601,...
    # Also include basic ASCII range for notdef/space
    unicode_str = 'U+0020-007E,' + ','.join(f'U+{c}' for c in codes)

    print(f"\n--- Optimized subset ({len(codes)} unique glyphs) ---")

    # Record original sizes
    orig_ttf_size = get_file_size(OUT_TTF)
    orig_woff_size = get_file_size(OUT_WOFF)

    # TTF subset
    print(f"  Generating subset TTF...")
    run_pyftsubset(FULL_TTF, OUT_TTF, unicode_str)
    new_ttf_size = get_file_size(OUT_TTF)
    print(f"  TTF:   {format_size(new_ttf_size)}", end='')
    if orig_ttf_size:
        print(f"  (was {format_size(orig_ttf_size)}, {100 - new_ttf_size * 100 // orig_ttf_size}% smaller)")
    else:
        print()

    # WOFF subset
    print(f"  Generating subset WOFF...")
    run_pyftsubset(FULL_TTF, OUT_WOFF, unicode_str, flavor='woff')
    new_woff_size = get_file_size(OUT_WOFF)
    print(f"  WOFF:  {format_size(new_woff_size)}", end='')
    if orig_woff_size:
        print(f"  (was {format_size(orig_woff_size)}, {100 - new_woff_size * 100 // orig_woff_size}% smaller)")
    else:
        print()

    # WOFF2 subset
    print(f"  Generating subset WOFF2...")
    run_pyftsubset(FULL_TTF, OUT_WOFF2, unicode_str, flavor='woff2')
    new_woff2_size = get_file_size(OUT_WOFF2)
    print(f"  WOFF2: {format_size(new_woff2_size)}")


def main():
    print("=== Linearicons Font Optimizer ===\n")

    # Verify source font exists
    if not os.path.exists(FULL_TTF):
        print(f"ERROR: Source TTF not found at {FULL_TTF}")
        sys.exit(1)

    # Verify shared-icons.js exists
    if not os.path.exists(SHARED_ICONS_JS):
        print(f"ERROR: shared-icons.js not found at {SHARED_ICONS_JS}")
        sys.exit(1)

    # Parse icon codes
    codes = parse_icon_codes()
    print(f"Found {len(codes)} unique icon codes in shared-icons.js")

    # Generate full web fonts for storybook
    generate_full_web_fonts()

    # Generate optimized subset
    generate_subset(codes)

    print(f"\nDone! Font files updated in {FONTS_DIR}")
    print(f"Full font preserved in {FULL_FONT_DIR}")


if __name__ == '__main__':
    main()
