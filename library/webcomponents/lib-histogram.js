/**
 * lib-histogram Web Component
 *
 * A histogram/bar chart component for displaying frequency distributions or raw values.
 * Based on the my_histogram function from evaluaties.js.
 *
 * Usage (frequency mode - default):
 *   <lib-histogram
 *       data="1,2,3,4,5,3,4,5,4,3"
 *       min-value="1"
 *       max-value="5"
 *       show-stats="right"
 *       labels="Slecht,Matig,Voldoende,Goed,Uitstekend">
 *   </lib-histogram>
 *
 * Usage (values mode - for response times, measurements, etc.):
 *   <lib-histogram
 *       mode="values"
 *       data="120,150,130,140,125,135,145,128,132,138"
 *       colors="success,success,error,success,success,success,success,success,success,success"
 *       show-stats="bottom"
 *       unit="ms">
 *   </lib-histogram>
 *
 * Attributes:
 *   - mode: "frequency" (default) or "values"
 *   - data: Comma-separated values (required)
 *   - min-value: Minimum value on x-axis (frequency mode, default: 1)
 *   - max-value: Maximum value on x-axis (frequency mode, default: 5)
 *   - show-stats: Position of statistics panel: "right", "bottom", or "none" (default: "right")
 *   - labels: Comma-separated labels for each bar (optional)
 *   - colors: Comma-separated colors/status for each bar in values mode ("success", "error", or hex color)
 *   - height: Height of the chart in pixels (default: 140)
 *   - nvt-value: Value to treat as "not applicable" (default: 0)
 *   - title: Chart title (optional)
 *   - unit: Unit suffix for values (e.g., "ms", "%")
 *   - bar-color: Default color for bars (default: var(--color-primary, #0066cc))
 *   - bar-hover-color: Color for bar hover (default: var(--color-primary-dark, #004499))
 *   - show-average-line: Show average line in values mode (default: true)
 *   - show-labels: Show labels below bars (default: true, set to "false" to hide)
 */

// Guard against double declaration when script is loaded multiple times
if (!customElements.get('lib-histogram')) {

class LibHistogram extends HTMLElement {
    static get observedAttributes() {
        return ['mode', 'data', 'min-value', 'max-value', 'show-stats', 'labels', 'colors', 'height', 'nvt-value', 'title', 'unit', 'bar-color', 'bar-hover-color', 'show-average-line', 'show-labels'];
    }

    constructor() {
        super();
        this.attachShadow({ mode: 'open' });
        this._rendered = false;
    }

    connectedCallback() {
        this.render();
        this._rendered = true;
    }

    attributeChangedCallback(name, oldValue, newValue) {
        if (oldValue !== newValue && this._rendered) {
            this.render();
        }
    }

    /**
     * Calculate statistics for the data
     */
    calculateStats(values) {
        if (values.length === 0) {
            return { count: 0, mean: 0, deviation: 0, modus: 0, modusCount: 0, min: 0, max: 0 };
        }

        const count = values.length;
        const sum = values.reduce((a, b) => a + b, 0);
        const mean = sum / count;
        const min = Math.min(...values);
        const max = Math.max(...values);

        // Standard deviation
        const squaredDiffs = values.map(v => Math.pow(v - mean, 2));
        const avgSquaredDiff = squaredDiffs.reduce((a, b) => a + b, 0) / count;
        const deviation = Math.sqrt(avgSquaredDiff);

        // Modus (most frequent value)
        const frequency = {};
        values.forEach(v => {
            frequency[v] = (frequency[v] || 0) + 1;
        });
        let modus = values[0];
        let modusCount = 0;
        Object.entries(frequency).forEach(([val, freq]) => {
            if (freq > modusCount) {
                modus = parseInt(val);
                modusCount = freq;
            }
        });

        return { count, mean, deviation, modus, modusCount, min, max };
    }

    render() {
        const mode = this.getAttribute('mode') || 'frequency';
        const dataAttr = this.getAttribute('data') || '';
        const showStats = (this.getAttribute('show-stats') || 'right').toLowerCase();
        const height = parseInt(this.getAttribute('height')) || 140;
        const title = this.getAttribute('title') || '';
        const unit = this.getAttribute('unit') || '';
        const barColor = this.getAttribute('bar-color') || 'var(--color-primary, #0066cc)';
        const barHoverColor = this.getAttribute('bar-hover-color') || 'var(--color-primary-dark, #004499)';
        const showAverageLine = this.getAttribute('show-average-line') !== 'false';

        // Parse data
        const rawData = dataAttr.split(',')
            .map(v => parseFloat(v.trim()))
            .filter(v => !isNaN(v));

        if (rawData.length === 0) {
            this.shadowRoot.innerHTML = this.getStyles(height, barColor, barHoverColor) +
                `<div class="histogram-empty">Geen gegevens beschikbaar</div>`;
            return;
        }

        if (mode === 'values') {
            this.renderValuesMode(rawData, height, title, unit, barColor, showStats, showAverageLine);
        } else {
            this.renderFrequencyMode(rawData, height, title, barColor, showStats);
        }
    }

    /**
     * Render values mode - each data point is a bar
     */
    renderValuesMode(data, height, title, unit, defaultBarColor, showStats, showAverageLine) {
        const colorsAttr = this.getAttribute('colors') || '';
        const labelsAttr = this.getAttribute('labels') || '';
        const showLabels = this.getAttribute('show-labels') !== 'false';
        const colors = colorsAttr ? colorsAttr.split(',').map(c => c.trim()) : [];
        const labels = labelsAttr ? labelsAttr.split(',').map(l => l.trim()) : [];

        const stats = this.calculateStats(data);
        const maxValue = stats.max;
        const headerHeight = 20;
        const bottomHeight = showStats === 'bottom' ? 60 : 0;
        const barAreaHeight = height - headerHeight - 20 - bottomHeight;

        let html = this.getStyles(height, defaultBarColor, this.getAttribute('bar-hover-color') || 'var(--color-primary-dark, #004499)');

        html += `<div class="histogram-container${showStats === 'right' ? ' has-stats-right' : ''}">`;
        html += `<div class="histogram-chart">`;

        if (title) {
            html += `<div class="histogram-title">${this.escapeHtml(title)}</div>`;
        }

        // Bars
        html += `<div class="histogram-bars" style="height: ${barAreaHeight}px;">`;
        data.forEach((value, idx) => {
            const barHeight = maxValue > 0 ? (value / maxValue) * 100 : 0;
            let barColor = defaultBarColor;

            // Determine bar color
            if (colors[idx]) {
                const colorValue = colors[idx].toLowerCase();
                if (colorValue === 'success') {
                    barColor = 'var(--color-success, #28a745)';
                } else if (colorValue === 'error') {
                    barColor = 'var(--color-error, #dc3545)';
                } else if (colorValue === 'warning') {
                    barColor = 'var(--color-warning, #ffc107)';
                } else if (colorValue.startsWith('#') || colorValue.startsWith('var(')) {
                    barColor = colorValue;
                }
            }

            const label = labels[idx] || (idx + 1).toString();
            const tooltipText = `${label}: ${Math.round(value)}${unit}`;
            const showValue = barHeight > 15; // Only show value inside if bar is tall enough

            html += `
                <div class="histogram-bar-wrapper" title="${this.escapeHtml(tooltipText)}">
                    <div class="histogram-bar-container">
                        <div class="histogram-bar" style="height: ${barHeight}%; background-color: ${barColor};">
                            ${showValue ? `<span class="histogram-value-inside">${Math.round(value)}</span>` : ''}
                        </div>
                    </div>
                    ${showLabels ? `<div class="histogram-label">${this.escapeHtml(label)}</div>` : ''}
                </div>
            `;
        });
        html += `</div>`;

        // Average line - position as percentage of bar area from bottom
        // The line is inside histogram-bars which has the correct height
        if (showAverageLine && maxValue > 0) {
            const avgLinePercent = (stats.mean / maxValue) * 100;
            const labelHeight = showLabels ? 20 : 0; // Account for label space
            html += `<div class="histogram-avg-line" style="bottom: calc(${avgLinePercent}% + ${labelHeight}px);">
                <span class="avg-label">gem: ${Math.round(stats.mean)}${unit}</span>
            </div>`;
        }

        // Bottom stats
        if (showStats === 'bottom') {
            html += this.renderValuesStats(stats, unit, 'bottom');
        }

        html += `</div>`; // close histogram-chart

        // Right stats
        if (showStats === 'right') {
            html += this.renderValuesStats(stats, unit, 'right');
        }

        html += `</div>`; // close histogram-container

        this.shadowRoot.innerHTML = html;
    }

    /**
     * Render frequency mode - histogram of value frequencies
     */
    renderFrequencyMode(rawData, height, title, barColor, showStats) {
        const minValue = parseInt(this.getAttribute('min-value')) || 1;
        const maxValue = parseInt(this.getAttribute('max-value')) || 5;
        const nvtValue = parseInt(this.getAttribute('nvt-value')) || 0;
        const labelsAttr = this.getAttribute('labels') || '';
        const labels = labelsAttr ? labelsAttr.split(',').map(l => l.trim()) : [];

        // Separate real values from NVT values
        const realValues = rawData.filter(v => v !== nvtValue);
        const nvtCount = rawData.length - realValues.length;

        // Calculate frequency for each column
        const numColumns = maxValue - minValue + 1;
        const columns = {};
        for (let i = minValue; i <= maxValue; i++) {
            columns[i] = 0;
        }
        realValues.forEach(v => {
            if (v >= minValue && v <= maxValue) {
                columns[v]++;
            }
        });

        // Calculate percentages
        const percentages = {};
        let maxPercentage = 0;
        for (let i = minValue; i <= maxValue; i++) {
            percentages[i] = realValues.length > 0 ? (columns[i] / realValues.length) * 100 : 0;
            maxPercentage = Math.max(maxPercentage, percentages[i]);
        }

        // Calculate statistics
        const stats = this.calculateStats(realValues);

        const headerHeight = 20;
        const bottomHeight = showStats === 'bottom' ? 50 : 0;
        const barAreaHeight = height - headerHeight - headerHeight - bottomHeight;

        let html = this.getStyles(height, barColor, this.getAttribute('bar-hover-color') || 'var(--color-primary-dark, #004499)');

        html += `<div class="histogram-container${showStats === 'right' ? ' has-stats-right' : ''}">`;
        html += `<div class="histogram-chart">`;

        if (title) {
            html += `<div class="histogram-title">${this.escapeHtml(title)}</div>`;
        }

        // Bars
        html += `<div class="histogram-bars" style="height: ${barAreaHeight}px;">`;
        for (let i = minValue; i <= maxValue; i++) {
            const pct = percentages[i];
            const barHeight = maxPercentage > 0 ? (pct / maxPercentage) * 100 : 0;
            const count = columns[i];
            const label = labels[i - minValue] || '';
            const tooltipText = label ? `${label}: ${count} (${Math.round(pct)}%)` : `${i}: ${count} (${Math.round(pct)}%)`;

            html += `
                <div class="histogram-bar-wrapper" title="${this.escapeHtml(tooltipText)}">
                    <div class="histogram-value">${pct > 0 ? Math.round(pct) + '%' : ''}</div>
                    <div class="histogram-bar-container">
                        <div class="histogram-bar ${count === 0 ? 'empty' : ''}" style="height: ${barHeight}%;"></div>
                    </div>
                    <div class="histogram-label">
                        <span>${i}</span>
                        ${label ? `<span class="histogram-label-text" title="${this.escapeHtml(label)}">${this.escapeHtml(label)}</span>` : ''}
                    </div>
                </div>
            `;
        }
        html += `</div>`;

        // Bottom stats
        if (showStats === 'bottom') {
            html += this.renderFrequencyStats(stats, nvtCount, 'bottom');
        }

        html += `</div>`; // close histogram-chart

        // Right stats
        if (showStats === 'right') {
            html += this.renderFrequencyStats(stats, nvtCount, 'right');
        }

        html += `</div>`; // close histogram-container

        this.shadowRoot.innerHTML = html;
    }

    renderValuesStats(stats, unit, position) {
        let html = `<div class="histogram-stats ${position}">`;
        html += `
            <div class="stat-row">
                <span class="stat-label">Aantal</span>
                <span class="stat-value">${stats.count}</span>
            </div>
            <div class="stat-row">
                <span class="stat-label">Gemiddeld</span>
                <span class="stat-value">${Math.round(stats.mean)}${unit}</span>
            </div>
            <div class="stat-row">
                <span class="stat-label">Min</span>
                <span class="stat-value">${Math.round(stats.min)}${unit}</span>
            </div>
            <div class="stat-row">
                <span class="stat-label">Max</span>
                <span class="stat-value">${Math.round(stats.max)}${unit}</span>
            </div>
        `;
        html += `</div>`;
        return html;
    }

    renderFrequencyStats(stats, nvtCount, position) {
        let html = `<div class="histogram-stats ${position}">`;
        html += `
            <div class="stat-row">
                <span class="stat-label">Aantal</span>
                <span class="stat-value">${stats.count}</span>
            </div>
            <div class="stat-row">
                <span class="stat-label">Gemiddelde</span>
                <span class="stat-value">${(Math.round(stats.mean * 100) / 100).toFixed(2)}</span>
            </div>
        `;
        if (stats.deviation > 0) {
            html += `
                <div class="stat-row">
                    <span class="stat-label">Std. afw.</span>
                    <span class="stat-value">${(Math.round(stats.deviation * 100) / 100).toFixed(2)}</span>
                </div>
            `;
        }
        html += `
            <div class="stat-row">
                <span class="stat-label">Modus</span>
                <span class="stat-value">${stats.modus}</span>
            </div>
        `;
        if (nvtCount > 0) {
            html += `
                <div class="stat-row stat-nvt">
                    <span class="stat-label">N.v.t.</span>
                    <span class="stat-value">${nvtCount}</span>
                </div>
            `;
        }
        html += `</div>`;
        return html;
    }

    getStyles(height, barColor, barHoverColor) {
        return `
            <style>
                :host {
                    display: block;
                    font-family: var(--font-family);
                    font-size: var(--font-size-sm);
                }
                .histogram-container {
                    display: flex;
                    gap: 16px;
                }
                .histogram-container.has-stats-right {
                    flex-direction: row;
                }
                .histogram-chart {
                    flex: 1;
                    min-width: 200px;
                    position: relative;
                }
                .histogram-title {
                    font-weight: 600;
                    font-size: var(--font-size);
                    margin-bottom: 8px;
                    color: var(--text-color, #333);
                }
                .histogram-bars {
                    display: flex;
                    align-items: flex-end;
                    gap: 2px;
                    border-bottom: 1px solid var(--border-color, #ccc);
                    position: relative;
                }
                .histogram-bar-wrapper {
                    flex: 1;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    height: 100%;
                    min-width: 20px;
                    cursor: pointer;
                }
                .histogram-bar-container {
                    flex: 1;
                    display: flex;
                    align-items: flex-end;
                    justify-content: center;
                    width: 100%;
                }
                .histogram-bar {
                    width: 80%;
                    background-color: ${barColor};
                    border-radius: 2px 2px 0 0;
                    transition: all 0.2s ease;
                    min-height: 2px;
                    display: flex;
                    align-items: flex-start;
                    justify-content: center;
                    position: relative;
                }
                .histogram-bar-wrapper:hover .histogram-bar {
                    background-color: ${barHoverColor};
                    transform: scaleX(1.1);
                }
                .histogram-bar.empty {
                    background-color: var(--border-color, #e0e0e0);
                    min-height: 1px;
                }
                .histogram-value-inside {
                    font-size: 9px;
                    color: #fff;
                    text-shadow: 0 1px 1px rgba(0,0,0,0.3);
                    padding-top: 2px;
                    font-weight: 600;
                }
                .histogram-label {
                    padding-top: 4px;
                    text-align: center;
                    font-size: var(--font-size-xs);
                    color: var(--text-color, #333);
                }
                .histogram-label-text {
                    display: block;
                    font-size: 9px;
                    color: var(--text-muted, #666);
                    white-space: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    max-width: 50px;
                }
                .histogram-avg-line {
                    position: absolute;
                    left: 0;
                    right: 0;
                    border-top: 1px dashed var(--color-primary, #007bff);
                    pointer-events: none;
                }
                .histogram-avg-line .avg-label {
                    position: absolute;
                    right: 0;
                    top: -14px;
                    font-size: var(--font-size-2xs);
                    color: var(--color-primary, #007bff);
                    background: var(--bg-color, #fff);
                    padding: 0 4px;
                }
                .histogram-stats {
                    min-width: 90px;
                    font-size: var(--font-size-xs);
                }
                .histogram-stats.right {
                    padding-top: 20px;
                }
                .histogram-stats.bottom {
                    margin-top: 12px;
                    display: flex;
                    gap: 16px;
                    flex-wrap: wrap;
                }
                .stat-row {
                    display: flex;
                    justify-content: space-between;
                    padding: 2px 0;
                    gap: 8px;
                }
                .histogram-stats.right .stat-row {
                    border-bottom: 1px dotted var(--border-color, #e0e0e0);
                }
                .histogram-stats.right .stat-row:last-child {
                    border-bottom: none;
                }
                .stat-label {
                    color: var(--text-muted, #666);
                }
                .stat-value {
                    font-weight: 600;
                    color: var(--text-color, #333);
                }
                .stat-nvt {
                    margin-top: 8px;
                    padding-top: 8px;
                    border-top: 1px solid var(--border-color, #e0e0e0);
                }
                .histogram-stats.bottom .stat-nvt {
                    margin-top: 0;
                    padding-top: 0;
                    border-top: none;
                }
                .histogram-empty {
                    text-align: center;
                    padding: 20px;
                    color: var(--text-muted, #666);
                }
            </style>
        `;
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Programmatic API to set data
     */
    setData(values, options = {}) {
        if (Array.isArray(values)) {
            this.setAttribute('data', values.join(','));
        }
        if (options.mode !== undefined) {
            this.setAttribute('mode', options.mode);
        }
        if (options.minValue !== undefined) {
            this.setAttribute('min-value', options.minValue);
        }
        if (options.maxValue !== undefined) {
            this.setAttribute('max-value', options.maxValue);
        }
        if (options.labels !== undefined) {
            this.setAttribute('labels', Array.isArray(options.labels) ? options.labels.join(',') : options.labels);
        }
        if (options.colors !== undefined) {
            this.setAttribute('colors', Array.isArray(options.colors) ? options.colors.join(',') : options.colors);
        }
        if (options.showStats !== undefined) {
            this.setAttribute('show-stats', options.showStats);
        }
        if (options.title !== undefined) {
            this.setAttribute('title', options.title);
        }
        if (options.unit !== undefined) {
            this.setAttribute('unit', options.unit);
        }
    }

    /**
     * Get current data as array
     */
    getData() {
        const dataAttr = this.getAttribute('data') || '';
        return dataAttr.split(',')
            .map(v => parseFloat(v.trim()))
            .filter(v => !isNaN(v));
    }

    /**
     * Get calculated statistics
     */
    getStats() {
        const nvtValue = parseInt(this.getAttribute('nvt-value')) || 0;
        const data = this.getData();
        const realValues = data.filter(v => v !== nvtValue);
        return this.calculateStats(realValues);
    }
}

// Register the component
customElements.define('lib-histogram', LibHistogram);

} // End of LibHistogram guard
