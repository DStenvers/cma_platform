/**
 * lib-arrowsteps Web Component
 *
 * A horizontal "arrow / chevron" step indicator for multi-step flows
 * (checkout, wizards). Each step is past (done, clickable link), current
 * (highlighted) or future (inert). Self-contained via Shadow DOM, so it
 * carries its own styling and works on any page (CMA or webshop) without
 * extra CSS.
 *
 * Usage (declarative):
 *   <lib-arrowsteps current="2"
 *       steps='[{"label":"Winkelwagen","url":"/winkelwagen"},
 *               {"label":"Verzendkosten","url":"/verzendkosten"},
 *               {"label":"Afrekenen","url":"/afrekenen"},
 *               {"label":"Betaling","url":"/betaling"}]'></lib-arrowsteps>
 *
 * Shorthand (labels only, no links):
 *   <lib-arrowsteps current="1" labels="Winkelwagen|Verzendkosten|Afrekenen|Betaling"></lib-arrowsteps>
 *
 * Attributes:
 *   - current : 1-based index of the active step (default 1)
 *   - steps   : JSON array of {label, url} objects
 *   - labels  : pipe-separated labels (alternative to steps; no urls)
 *   - linkable: "future" to also allow clicking steps ahead of current
 *               (default: only past/done steps link)
 *
 * Property:
 *   el.current = 3            // re-renders
 *   el.steps = [{...}, ...]   // re-renders
 *
 * Events:
 *   - step-click : fired when a step link is activated,
 *                  detail: { step: <1-based>, label, url }
 */

// Guard against double registration
if (!customElements.get('lib-arrowsteps')) {

    class LibArrowSteps extends HTMLElement {
        static get observedAttributes() {
            return ['current', 'steps', 'labels', 'linkable'];
        }

        constructor() {
            super();
            this.attachShadow({ mode: 'open' });
            this._steps = [];
            this._current = 1;
            this._rendered = false;
        }

        connectedCallback() {
            this._syncFromAttributes();
            this._render();
        }

        attributeChangedCallback() {
            if (!this._rendered) return;
            this._syncFromAttributes();
            this._render();
        }

        // ---- properties ----
        get current() { return this._current; }
        set current(v) {
            this._current = Math.max(1, parseInt(v, 10) || 1);
            this._render();
        }

        get steps() { return this._steps.slice(); }
        set steps(arr) {
            this._steps = Array.isArray(arr) ? arr.slice() : [];
            this._render();
        }

        // ---- internals ----
        _syncFromAttributes() {
            this._current = Math.max(1, parseInt(this.getAttribute('current'), 10) || 1);

            const stepsAttr = this.getAttribute('steps');
            if (stepsAttr) {
                try {
                    const parsed = JSON.parse(stepsAttr);
                    if (Array.isArray(parsed)) this._steps = parsed;
                } catch (e) {
                    // Malformed JSON -> render nothing rather than throw.
                    console.warn('lib-arrowsteps: invalid steps JSON', e);
                    this._steps = [];
                }
            } else {
                const labels = this.getAttribute('labels');
                this._steps = labels
                    ? labels.split('|').map(function (l) { return { label: l.trim(), url: '' }; })
                    : [];
            }
        }

        _render() {
            this._rendered = true;
            const linkFuture = (this.getAttribute('linkable') === 'future');
            const cur = this._current;

            const items = this._steps.map((info, i) => {
                const step = i + 1;
                const state = step < cur ? 'done' : (step === cur ? 'current' : 'future');
                const canLink = (state === 'done' || (linkFuture && state === 'future')) && info.url;
                const label = this._esc(info.label || '');
                const inner = canLink
                    ? `<a href="${this._esc(info.url)}" data-step="${step}">${label}</a>`
                    : `<span data-step="${step}">${label}</span>`;
                return `<li class="step ${state}" data-step="${step}">${inner}</li>`;
            }).join('');

            this.shadowRoot.innerHTML = this._styles() + `<ol class="arrow-steps" role="list">${items}</ol>`;

            this.shadowRoot.querySelectorAll('a[data-step]').forEach((a) => {
                a.addEventListener('click', (e) => {
                    const step = parseInt(a.getAttribute('data-step'), 10);
                    const info = this._steps[step - 1] || {};
                    this.dispatchEvent(new CustomEvent('step-click', {
                        bubbles: true,
                        composed: true,
                        detail: { step: step, label: info.label, url: info.url }
                    }));
                });
            });
        }

        _esc(s) {
            return (s + '').replace(/&/g, '&amp;').replace(/</g, '&lt;')
                .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        _styles() {
            return `<style>
                :host { display: block; --as-done: #0872c9; --as-current: #236ab4;
                        --as-future: #e4e9f0; --as-future-text: #8a93a3; --as-text: #ffffff; }
                .arrow-steps { display: flex; flex-wrap: wrap; gap: 4px; margin: 0; padding: 0;
                               list-style: none; font-size: 14px; }
                .step { position: relative; flex: 1 1 0; min-width: 90px; }
                .step a, .step span { display: block; text-align: center; padding: 10px 14px;
                               text-decoration: none; line-height: 1.2;
                               clip-path: polygon(0 0, calc(100% - 12px) 0, 100% 50%, calc(100% - 12px) 100%, 0 100%, 12px 50%); }
                .step:first-child a, .step:first-child span {
                               clip-path: polygon(0 0, calc(100% - 12px) 0, 100% 50%, calc(100% - 12px) 100%, 0 100%); }
                .step:last-child a, .step:last-child span {
                               clip-path: polygon(0 0, 100% 0, 100% 100%, 0 100%, 12px 50%); }
                .step.done a { background: var(--as-done); color: var(--as-text); }
                .step.done a:hover { filter: brightness(1.08); }
                .step.current span, .step.current a { background: var(--as-current); color: var(--as-text); font-weight: 700; }
                .step.future span, .step.future a { background: var(--as-future); color: var(--as-future-text); cursor: default; }
                @media (max-width: 600px) {
                    .arrow-steps { font-size: 12px; gap: 6px; }
                    .step { min-width: 64px; }
                    .step a, .step span { padding: 8px 6px; }
                }
            </style>`;
        }
    }

    customElements.define('lib-arrowsteps', LibArrowSteps);
}
