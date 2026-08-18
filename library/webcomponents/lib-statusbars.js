/**
 * lib-statusbars Web Component
 *
 * Horizontal stacked status bars: one row per category, each row split into
 * coloured segments (done / busy / backlog / failed, whatever the caller
 * chooses). Built for pipeline/status dashboards where "how far along is each
 * kind of data" must be readable at a glance — the chart the CMA dashboard
 * renders for site-injected status cards (see documentation.php topic
 * "dashboard_cards").
 *
 * Usage (declarative, e.g. in the storybook):
 *   <lib-statusbars data='{
 *       "rows": [
 *           { "label": "Huizen", "link": "/tools/stats.php", "segments": [
 *               { "label": "actief",    "value": 812, "kind": "success" },
 *               { "label": "verkocht",  "value": 15,  "kind": "info" },
 *               { "label": "verborgen", "value": 310, "kind": "muted" } ] },
 *           { "label": "Foto's", "segments": [
 *               { "label": "lokaal",  "value": 3200, "kind": "success" },
 *               { "label": "te doen", "value": 640,  "kind": "warning" },
 *               { "label": "dood",    "value": 12,   "kind": "error" } ] }
 *       ],
 *       "legend": true
 *   }'></lib-statusbars>
 *
 * Usage (programmatic, the dashboard path — avoids attribute JSON escaping):
 *   const chart = document.createElement('lib-statusbars');
 *   chart.data = payload;            // same shape as the attribute JSON
 *   container.replaceChildren(chart);
 *
 * Data shape:
 *   - rows: [{ label, link?, segments: [{ label, value, kind? }] }]
 *   - kind: "success" | "info" | "warning" | "error" | "muted"
 *     (unknown kinds fall back to muted; unknown keys anywhere are ignored,
 *     which is the forward-compatibility contract)
 *   - legend: boolean — overrides the show-legend attribute when present
 *
 * Attributes:
 *   - data: JSON object as above (alternative to the data property)
 *   - bar-height: Height of each bar in pixels (default: 18)
 *   - show-legend: Show the deduplicated legend under the rows (default: true)
 *   - show-totals: Show the row total at the right edge (default: true)
 *   - title: Chart title above the rows (optional)
 *
 * Properties:
 *   - data (get/set): the parsed data object; setting re-renders
 *
 * Methods:
 *   - update(data): replace the data object and re-render
 */

// Guard against double declaration when script is loaded multiple times
if (!customElements.get('lib-statusbars')) {

class LibStatusbars extends HTMLElement {
    static get observedAttributes() {
        return ['data', 'bar-height', 'show-legend', 'show-totals', 'title'];
    }

    constructor() {
        super();
        this.attachShadow({ mode: 'open' });
        this._data = null;
        this._rendered = false;
    }

    connectedCallback() {
        this.render();
        this._rendered = true;
    }

    attributeChangedCallback(name, oldValue, newValue) {
        if (name === 'data') {
            this._data = null;      // attribute becomes the source again
        }
        if (this._rendered && oldValue !== newValue) {
            this.render();
        }
    }

    get data() {
        return this._data !== null ? this._data : this._parseData(this.getAttribute('data'));
    }

    set data(value) {
        this._data = (value && typeof value === 'object') ? value : null;
        this.render();
    }

    update(value) {
        this.data = value;
    }

    /**
     * Attribute JSON -> object, or null when absent/broken. Broken JSON is a
     * caller bug, but a chart that renders "geen gegevens" beats a component
     * that throws halfway through a dashboard.
     */
    _parseData(raw) {
        if (!raw) return null;
        try {
            const parsed = JSON.parse(raw);
            return (parsed && typeof parsed === 'object') ? parsed : null;
        } catch (e) {
            return null;
        }
    }

    /** kind -> CSS color. Unknown kinds are muted, never invisible. */
    _kindColor(kind) {
        const colors = {
            success: 'var(--color-success, #00a04a)',
            info: 'var(--color-info, #077ab2)',
            warning: 'var(--color-warning, #fddc0d)',
            error: 'var(--color-error, #e01f3d)',
            muted: 'var(--color-text-secondary, #999999)'
        };
        return colors[kind] || colors.muted;
    }

    _formatValue(value) {
        return Number(value).toLocaleString('nl-NL');
    }

    _escape(text) {
        const div = document.createElement('div');
        div.textContent = String(text == null ? '' : text);
        return div.innerHTML;
    }

    /**
     * One row: label (optionally a link), the stacked track, the total.
     * Zero-value segments are skipped; a row whose segments sum to zero shows
     * an empty muted track with "0" — nothing to report is still a report.
     */
    _buildRow(row) {
        const segments = Array.isArray(row.segments) ? row.segments.filter(function (s) {
            return s && Number(s.value) > 0;
        }) : [];
        const total = segments.reduce(function (sum, s) { return sum + Number(s.value); }, 0);

        const label = this._escape(row.label || '');
        const labelHtml = row.link
            ? '<a class="row-label" href="' + this._escape(row.link) + '">' + label + '</a>'
            : '<span class="row-label">' + label + '</span>';

        let track = '';
        for (const segment of segments) {
            const pct = (Number(segment.value) / total) * 100;
            track += '<span class="segment" style="width:' + pct.toFixed(3) + '%;'
                + 'background:' + this._kindColor(segment.kind) + '" title="'
                + this._escape(segment.label || segment.kind || '') + ': '
                + this._formatValue(segment.value) + '"></span>';
        }

        const totalHtml = this._showTotals()
            ? '<span class="row-total">' + this._formatValue(total) + '</span>'
            : '';

        return '<div class="row">' + labelHtml
            + '<span class="track' + (total === 0 ? ' track-empty' : '') + '">' + track + '</span>'
            + totalHtml + '</div>';
    }

    /**
     * The legend: every distinct label+kind pair across all rows, once, in
     * order of first appearance. Deduplicated so seven rows that all carry
     * "te doen" explain themselves with one swatch.
     */
    _buildLegend(rows) {
        const seen = {};
        let html = '';
        for (const row of rows) {
            for (const segment of (Array.isArray(row.segments) ? row.segments : [])) {
                if (!segment) continue;
                const key = (segment.label || '') + '|' + (segment.kind || '');
                if (seen[key]) continue;
                seen[key] = true;
                html += '<span class="legend-item"><span class="swatch" style="background:'
                    + this._kindColor(segment.kind) + '"></span>'
                    + this._escape(segment.label || segment.kind || '') + '</span>';
            }
        }
        return html === '' ? '' : '<div class="legend">' + html + '</div>';
    }

    _showTotals() {
        return this.getAttribute('show-totals') !== 'false';
    }

    _showLegend(data) {
        if (data && typeof data.legend === 'boolean') return data.legend;
        return this.getAttribute('show-legend') !== 'false';
    }

    render() {
        const data = this.data;
        const barHeight = parseInt(this.getAttribute('bar-height'), 10) || 18;
        const title = this.getAttribute('title');
        const rows = (data && Array.isArray(data.rows)) ? data.rows : [];

        let body = '';
        if (rows.length === 0) {
            body = '<div class="empty">Geen gegevens</div>';
        } else {
            body = rows.map(this._buildRow.bind(this)).join('');
            if (this._showLegend(data)) {
                body += this._buildLegend(rows);
            }
        }

        this.shadowRoot.innerHTML = '<style>'
            + ':host { display: block; font-family: inherit; font-size: 13px; color: var(--color-text, #333333); }'
            + '.title { font-weight: 600; margin: 0 0 8px; }'
            + '.row { display: flex; align-items: center; gap: 10px; margin: 6px 0; }'
            + '.row-label { flex: 0 0 110px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }'
            + 'a.row-label { color: var(--color-primary, #204496); text-decoration: none; }'
            + 'a.row-label:hover { text-decoration: underline; }'
            + '.track { flex: 1 1 auto; display: flex; height: ' + barHeight + 'px;'
            + ' border-radius: ' + Math.round(barHeight / 2) + 'px; overflow: hidden;'
            + ' background: var(--color-bg-secondary, #f0f0f0); }'
            + '.segment { display: block; height: 100%; min-width: 3px; }'
            + '.row-total { flex: 0 0 auto; min-width: 3.5em; text-align: right;'
            + ' font-variant-numeric: tabular-nums; color: var(--color-text-secondary, #666666); }'
            + '.legend { display: flex; flex-wrap: wrap; gap: 4px 14px; margin-top: 10px; }'
            + '.legend-item { display: inline-flex; align-items: center; gap: 5px;'
            + ' color: var(--color-text-secondary, #666666); font-size: 12px; }'
            + '.swatch { width: 10px; height: 10px; border-radius: 3px; display: inline-block; }'
            + '.empty { color: var(--color-text-secondary, #999999); font-style: italic; padding: 8px 0; }'
            + '</style>'
            + (title ? '<div class="title">' + this._escape(title) + '</div>' : '')
            + body;
    }
}

customElements.define('lib-statusbars', LibStatusbars);

}
