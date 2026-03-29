/**
 * lib-gauge Web Component (No Shadow DOM)
 *
 * A ratio/percentage gauge bar for comparing two values.
 * Shows the ratio as a colored bar with labels.
 *
 * Usage:
 *   <lib-gauge value="20000" max="50000"></lib-gauge>
 *   <lib-gauge value="20000" max="50000" label="WebP" format="size"></lib-gauge>
 *   <lib-gauge value="20000" max="50000" type="success"></lib-gauge>
 *   <lib-gauge value="37" max="403" size="lg" type="info" format="raw" label="37 / 403 endpoints"></lib-gauge>
 *
 * Attributes:
 *   - value: Current value (numerator)
 *   - max: Maximum/reference value (denominator)
 *   - type: Color theme — auto (default), info, success, warning, error
 *           auto: green if saving >=50%, orange if saving <50%, red if larger
 *   - size: sm (default) = compact 6px bar, lg = tall 20px bar with text inside
 *   - label: Label for the value (shown left, or inside bar for lg)
 *   - format: Display format — percent (default), size, raw
 *             percent: shows -XX% or +XX%
 *             size: formats as KB/MB (treats values as bytes)
 *             raw: shows raw value
 *   - show-bar: Show the bar (default: true, set to "false" to hide)
 *   - show-pct: Show percentage text on right (default: true, set to "false" to hide)
 *   - min-width: Minimum width in px (default: 100)
 *
 * Properties:
 *   - ratio: (readonly) value / max
 *   - percentage: (readonly) savings percentage (1 - ratio) * 100
 *
 * Methods:
 *   - update(value, max): Update values programmatically
 */
// Guard against double registration
if (!customElements.get('lib-gauge')) {

class LibGauge extends HTMLElement {
    static get observedAttributes() {
        return ['value', 'max', 'type', 'label', 'format', 'show-bar', 'show-pct', 'min-width', 'size'];
    }

    constructor() {
        super();
        this._rendered = false;
    }

    connectedCallback() {
        if (!this._rendered) {
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => this._initRender(), { once: true });
            } else {
                requestAnimationFrame(() => this._initRender());
            }
        }
    }

    _initRender() {
        if (this._rendered) return;
        this._rendered = true;
        this.render();
    }

    attributeChangedCallback() {
        if (this._rendered) {
            this.render();
        }
    }

    get ratio() {
        var max = parseFloat(this.getAttribute('max')) || 0;
        var value = parseFloat(this.getAttribute('value')) || 0;
        return max > 0 ? value / max : 1;
    }

    get percentage() {
        return Math.round((1 - this.ratio) * 100);
    }

    /**
     * Update values programmatically
     * @param {number} value
     * @param {number} max
     */
    update(value, max) {
        this.setAttribute('value', String(value));
        this.setAttribute('max', String(max));
    }

    _formatSize(bytes) {
        bytes = parseFloat(bytes) || 0;
        if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + ' MB';
        if (bytes >= 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return bytes + ' B';
    }

    _resolveType() {
        var type = (this.getAttribute('type') || 'auto').toLowerCase();
        if (type !== 'auto') return type;
        var pct = this.percentage;
        if (pct < 0) return 'error';
        if (pct >= 50) return 'success';
        return 'warning';
    }

    render() {
        var value = parseFloat(this.getAttribute('value')) || 0;
        var max = parseFloat(this.getAttribute('max')) || 0;
        var format = (this.getAttribute('format') || 'percent').toLowerCase();
        var showBar = this.getAttribute('show-bar') !== 'false';
        var showPct = this.getAttribute('show-pct') !== 'false';
        var minWidth = parseInt(this.getAttribute('min-width')) || 100;
        var label = this.getAttribute('label') || '';
        var size = (this.getAttribute('size') || 'sm').toLowerCase();
        var type = this._resolveType();

        var ratio = max > 0 ? value / max : 1;
        var barWidth = Math.min(ratio * 100, 100);

        // Build percentage text
        var pctText = '';
        if (showPct) {
            if (format === 'raw') {
                // Raw: show simple ratio percentage (8/403 = 2%)
                pctText = Math.round(ratio * 100) + '%';
            } else {
                // Size/percent: show savings percentage (1 - ratio)
                var pct = Math.round((1 - ratio) * 100);
                pctText = pct < 0 ? ('+' + Math.abs(pct) + '%') : ('-' + pct + '%');
            }
        }

        // Build value text
        var valueText = '';
        if (label) {
            valueText = label;
            if (format === 'size') valueText += ': ' + this._formatSize(value);
        } else if (format === 'size') {
            valueText = this._formatSize(value);
        } else if (format === 'raw') {
            valueText = String(value);
        }

        var typeClass = 'lib-gauge--' + type;
        var sizeClass = 'lib-gauge--' + size;
        var html = '';

        var rightLabel = pctText ? '<span class="lib-gauge__label-right">' + pctText + '</span>' : '';

        if (size === 'lg') {
            // Large: text below the bar
            html = '<div class="lib-gauge ' + typeClass + ' ' + sizeClass + '" style="min-width:' + minWidth + 'px;">';
            if (showBar) {
                html += '<div class="lib-gauge__track">';
                html += '<div class="lib-gauge__bar" style="width:' + barWidth + '%;"></div>';
                html += '</div>';
            }
            html += '<div class="lib-gauge__labels">';
            html += '<span class="lib-gauge__label-left">' + valueText + '</span>';
            html += rightLabel;
            html += '</div>';
            html += '</div>';
        } else {
            // Small (default): labels above, thin bar below
            html = '<div class="lib-gauge ' + typeClass + ' ' + sizeClass + '" style="min-width:' + minWidth + 'px;">';
            html += '<div class="lib-gauge__labels">';
            html += '<span class="lib-gauge__label-left">' + valueText + '</span>';
            html += rightLabel;
            html += '</div>';
            if (showBar) {
                html += '<div class="lib-gauge__track">';
                html += '<div class="lib-gauge__bar" style="width:' + barWidth + '%;"></div>';
                html += '</div>';
            }
            html += '</div>';
        }

        this.innerHTML = html;
    }
}

customElements.define('lib-gauge', LibGauge);

} // end guard
