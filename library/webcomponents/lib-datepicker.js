/**
 * lib-datepicker Web Component
 *
 * A reusable date picker web component with calendar popup.
 * Generic component that can be used outside of CMA.
 *
 * Usage:
 *   <lib-datepicker name="mydate" value="2024-01-15" format="dd-mm-yyyy"></lib-datepicker>
 *
 * Attributes:
 *   - name: Form field name
 *   - value: Date value (YYYY-MM-DD format)
 *   - format: Display format (dd-mm-yyyy, mm-dd-yyyy, yyyy-mm-dd)
 *   - min: Minimum date
 *   - max: Maximum date
 *   - required: Field is required
 *   - disabled: Field is disabled
 *   - readonly: Field is readonly
 *   - placeholder: Input placeholder
 *   - locale: Language locale (nl, en)
 *
 * Events:
 *   - change: Fired when date changes
 */
// Guard against double registration
if (!customElements.get('lib-datepicker')) {

class LibDatepicker extends HTMLElement {
    static get observedAttributes() {
        return ['value', 'min', 'max', 'disabled', 'readonly', 'format', 'locale', 'required'];
    }

    constructor() {
        super();
        this.attachShadow({ mode: 'open' });
        this._value = '';
        this._isOpen = false;
        this._currentMonth = new Date();
        this._locale = 'nl';
        this._format = 'dd-mm-yyyy';
    }

    connectedCallback() {
        this.render();
        this.setupEventListeners();

        // Apply shared styles (adoptedStyleSheets or inline fallback)
        this._applySharedStyles();
    }

    _applySharedStyles() {
        if (typeof LibSharedStyles !== 'undefined') {
            if (LibSharedStyles.isSupported()) {
                LibSharedStyles.adopt(this.shadowRoot, 'base', 'input', 'button');
            } else {
                // Fallback: inject as inline style
                const css = LibSharedStyles.getInlineCSS('base', 'input', 'button');
                const style = document.createElement('style');
                style.textContent = css;
                this.shadowRoot.insertBefore(style, this.shadowRoot.firstChild);
            }
        }
    }

    disconnectedCallback() {
        this.removeEventListeners();
    }

    attributeChangedCallback(name, oldValue, newValue) {
        if (oldValue === newValue) return;

        switch (name) {
            case 'value':
                this._value = newValue || '';
                this.updateDisplay();
                break;
            case 'format':
                this._format = newValue || 'dd-mm-yyyy';
                this.updateDisplay();
                break;
            case 'locale':
                this._locale = newValue || 'nl';
                if (this._isOpen) this.renderCalendar();
                break;
            case 'disabled':
            case 'readonly':
                this.updateInputState();
                break;
        }
    }

    get value() {
        return this._value;
    }

    set value(val) {
        this._value = val || '';
        this.setAttribute('value', this._value);
        this.updateDisplay();
    }

    // Expose name property for form field lookup (web components don't have this by default)
    get name() {
        return this.getAttribute('name') || '';
    }

    set name(val) {
        if (val) {
            this.setAttribute('name', val);
        } else {
            this.removeAttribute('name');
        }
    }

    // Expose type property for form field type detection
    get type() {
        return 'date';
    }

    get localeStrings() {
        const locales = {
            nl: {
                months: ['Januari', 'Februari', 'Maart', 'April', 'Mei', 'Juni',
                         'Juli', 'Augustus', 'September', 'Oktober', 'November', 'December'],
                monthsShort: ['Jan', 'Feb', 'Mrt', 'Apr', 'Mei', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dec'],
                days: ['Zo', 'Ma', 'Di', 'Wo', 'Do', 'Vr', 'Za'],
                today: 'Vandaag',
                clear: 'Wissen'
            },
            en: {
                months: ['January', 'February', 'March', 'April', 'May', 'June',
                         'July', 'August', 'September', 'October', 'November', 'December'],
                monthsShort: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                days: ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'],
                today: 'Today',
                clear: 'Clear'
            }
        };
        return locales[this._locale] || locales.nl;
    }

    render() {
        const name = this.getAttribute('name') || '';
        const placeholder = this.getAttribute('placeholder') || 'dd-mm-yyyy';
        const disabled = this.hasAttribute('disabled');
        const readonly = this.hasAttribute('readonly');
        const required = this.hasAttribute('required');

        this.shadowRoot.innerHTML = `
            <style>
                @font-face {
                    font-family: 'Linearicons';
                    src: url('../library/fonts/Linearicons/Font/Linearicons.woff2') format('woff2'),
                         url('../library/fonts/Linearicons/Font/Linearicons.woff') format('woff');
                    font-weight: normal;
                    font-style: normal;
                }

                :host {
                    display: inline-block;
                    position: relative;
                    font-family: "Trebuchet MS", Verdana, sans-serif;
                    font-size: var(--font-size);
                }

                /* Small variant for filters */
                :host([small]) .datepicker-wrapper {
                    border-radius: 3px;
                }

                :host([small]) .datepicker-input {
                    width: 75px;
                    height: 22px;
                    line-height: 22px;
                    font-size: var(--font-size-sm);
                    padding-left: 6px;
                }

                :host([small]) .datepicker-icon {
                    width: 20px;
                }

                :host([small]) .datepicker-icon::before {
                    font-size: var(--font-size-sm);
                }

                :host([small]) .datepicker-calendar {
                    min-width: 240px;
                }

                :host([small]) .datepicker-day {
                    font-size: var(--font-size-xs);
                }

                :host([small]) .datepicker-weekday {
                    font-size: var(--font-size-2xs);
                }

                :host([small]) .datepicker-header {
                    padding: 8px;
                }

                :host([small]) .datepicker-title {
                    font-size: var(--font-size-sm);
                }

                .datepicker-wrapper {
                    position: relative;
                    display: inline-flex;
                    align-items: stretch;
                    border: 1px solid var(--input-border, #ddd);
                    border-radius: 4px;
                    background: var(--input-bg, #fff);
                }

                /* Required indicator - red left border when empty */
                :host([data-required="true"]) .datepicker-wrapper {
                    border-left: 3px solid var(--color-error, #dc3545);
                }

                /* Required with value - standard border */
                :host([data-required="true"]) .datepicker-wrapper:has(.datepicker-input:not(:placeholder-shown)) {
                    border-left: 3px solid var(--border-color, #ddd);
                }

                /* Readonly required - no border */
                :host([data-required="true"][readonly]) .datepicker-wrapper {
                    border-left: none;
                }

                .datepicker-input {
                    padding-left: 8px;
                    padding-right: 4px;
                    border: none;
                    border-radius: 4px 0 0 4px;
                    font-size: var(--font-size);
                    font-family: "Trebuchet MS", Verdana, sans-serif;
                    width: 90px;
                    box-sizing: border-box;
                    color: var(--text-primary, #333);
                    height: 24px;
                    line-height: 24px;
                    background: transparent;
                }

                .datepicker-input:focus {
                    outline: none;
                }

                .datepicker-input:disabled {
                    color: #999;
                    cursor: not-allowed;
                }

                .datepicker-input[readonly] {
                    background-color: var(--input-bg-readonly, transparent);
                    border-color: transparent;
                    color: var(--text-secondary, #666);
                    cursor: default;
                }

                .datepicker-input[readonly]:focus {
                    border-color: transparent;
                    outline: none;
                    box-shadow: none;
                }

                .datepicker-input::placeholder {
                    color: var(--text-muted, #999);
                    font-style: italic;
                }

                /* Readonly mode: hide icon and remove wrapper styling */
                :host([readonly]) .datepicker-wrapper {
                    border: none;
                    background: transparent;
                }

                :host([readonly]) .datepicker-icon {
                    display: none;
                }

                :host([readonly]) .datepicker-input {
                    border-radius: 0;
                    width: auto;
                }

                .datepicker-icon {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    width: 24px;
                    cursor: pointer;
                    background-color: transparent;
                    border-left: 1px solid var(--input-border, #ddd);
                    border-radius: 0 4px 4px 0;
                }

                .datepicker-icon::before {
                    font-family: 'Linearicons';
                    content: "\\e789";
                    color: var(--text-secondary, #666);
                    font-size: var(--font-size-md);
                }

                .datepicker-icon:hover {
                    background-color: transparent;
                }

                .datepicker-icon:hover::before {
                    color: var(--color-primary, #204496);
                }

                /* Disabled state */
                :host([disabled]) .datepicker-wrapper {
                    background: var(--bg-disabled, #f5f5f5);
                }

                :host([disabled]) .datepicker-icon {
                    background: transparent;
                    cursor: not-allowed;
                }

                :host([disabled]) .datepicker-icon::before {
                    color: var(--text-disabled, #999);
                }

                :host([disabled]) .datepicker-icon:hover {
                    background: var(--bg-disabled, #f5f5f5);
                }

                :host([disabled]) .datepicker-icon:hover::before {
                    color: var(--text-disabled, #999);
                }

                .datepicker-calendar {
                    position: fixed;
                    background: #fff;
                    border: 1px solid #ddd;
                    border-radius: 0 8px 8px 8px;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                    z-index: 10000;
                    display: none;
                    min-width: 280px;
                    overflow: hidden;
                }

                .datepicker-calendar.open {
                    display: block;
                    animation: slideDown 0.15s ease;
                }

                @keyframes slideDown {
                    from { opacity: 0; transform: translateY(-8px); }
                    to { opacity: 1; transform: translateY(0); }
                }

                .datepicker-header {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    padding: 12px;
                    background: #f8f9fa;
                    border-bottom: 1px solid #e0e0e0;
                }

                .datepicker-nav {
                    background: #fff;
                    border: 1px solid transparent;
                    cursor: pointer;
                    padding: 0;
                    width: 24px;
                    height: 24px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: #888;
                    border-radius: 4px;
                    font-size: 0;
                }

                .datepicker-nav::before {
                    font-family: 'Linearicons';
                    font-size: var(--font-size-md);
                }

                .datepicker-nav[data-action="prev-month"]::before {
                    content: "\\e93b";
                }

                .datepicker-nav[data-action="next-month"]::before {
                    content: "\\e93c";
                }

                .datepicker-nav:hover {
                    background: #f5f5f5;
                    border-color: #077ab2;
                    color: #077ab2;
                }

                .datepicker-title {
                    font-weight: 600;
                    color: #333;
                    cursor: pointer;
                }

                .datepicker-title:hover {
                    color: #204496;
                }

                .datepicker-weekdays {
                    display: grid;
                    grid-template-columns: repeat(7, 1fr);
                    padding: 8px 12px 4px;
                    background: #fafafa;
                }

                .datepicker-weekday {
                    text-align: center;
                    font-size: var(--font-size-xs);
                    font-weight: 600;
                    color: #999;
                    padding: 4px;
                }

                .datepicker-days {
                    display: grid;
                    grid-template-columns: repeat(7, 1fr);
                    gap: 2px;
                    padding: 8px 12px 12px;
                }

                .datepicker-day {
                    aspect-ratio: 1;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    border: none;
                    background: none;
                    cursor: pointer;
                    border-radius: 6px;
                    font-size: var(--font-size);
                    color: #333;
                    transition: all 0.1s ease;
                }

                .datepicker-day:hover:not(.disabled):not(.selected) {
                    background: #e8f0fe;
                    border: 1px solid #204496;
                }

                .datepicker-day.other-month {
                    color: #ccc;
                }

                .datepicker-day.today {
                    font-weight: 700;
                    color: #204496;
                }

                .datepicker-day.selected {
                    background: #204496;
                    color: #fff;
                }

                .datepicker-day.disabled {
                    color: #ddd;
                    cursor: not-allowed;
                }

                .datepicker-footer {
                    display: flex;
                    justify-content: space-between;
                    padding: 8px 12px;
                    border-top: 1px solid #e0e0e0;
                    background: #fafafa;
                }

                .datepicker-btn {
                    background: #fff;
                    border: 1px solid transparent;
                    color: #888;
                    cursor: pointer;
                    font-size: var(--font-size);
                    font-weight: normal;
                    padding: 4px 8px;
                    border-radius: 4px;
                }

                .datepicker-btn:hover {
                    background: #f5f5f5;
                    border-color: #077ab2;
                    color: #077ab2;
                }

                /* Hidden input for form submission */
                input[type="hidden"] {
                    display: none;
                }
            </style>

            <div class="datepicker-wrapper">
                <input type="text"
                       class="datepicker-input"
                       placeholder="${placeholder}"
                       ${disabled ? 'disabled' : ''}
                       ${readonly ? 'readonly' : ''}
                       ${required ? 'required' : ''}>
                <span class="datepicker-icon"></span>
                <input type="hidden" name="${name}">
                <div class="datepicker-calendar" id="calendar"></div>
            </div>
        `;

        this.updateDisplay();
    }

    setupEventListeners() {
        const input = this.shadowRoot.querySelector('.datepicker-input');
        const icon = this.shadowRoot.querySelector('.datepicker-icon');
        const calendar = this.shadowRoot.querySelector('.datepicker-calendar');

        // Input click (not focus - to allow tabbing through form without opening)
        input.addEventListener('click', () => this.open());

        // Icon click
        icon.addEventListener('click', (e) => {
            e.stopPropagation();
            this.toggle();
        });

        // Input change (manual entry)
        input.addEventListener('change', (e) => {
            this.parseInputValue(e.target.value);
        });

        input.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.close();
            } else if (e.key === 'Enter') {
                this.parseInputValue(input.value);
                this.close();
            }
        });

        // Close on outside click
        this._outsideClickHandler = (e) => {
            if (!this.contains(e.target)) {
                this.close();
            }
        };
        document.addEventListener('click', this._outsideClickHandler);
    }

    removeEventListeners() {
        if (this._outsideClickHandler) {
            document.removeEventListener('click', this._outsideClickHandler);
        }
    }

    open() {
        if (this.hasAttribute('disabled') || this.hasAttribute('readonly')) return;

        // Close all other open datepickers
        document.querySelectorAll('lib-datepicker').forEach(dp => {
            if (dp !== this && dp._isOpen) dp.close();
        });

        if (this._value) {
            const date = this.parseDate(this._value);
            if (date) {
                this._currentMonth = new Date(date.getFullYear(), date.getMonth(), 1);
            }
        } else {
            this._currentMonth = new Date();
        }

        this.renderCalendar();
        const calendar = this.shadowRoot.querySelector('.datepicker-calendar');

        // Use unified z-index manager if available
        if (typeof lib_zindex_manager !== 'undefined') {
            this._zIndexId = 'datepicker_' + Date.now();
            const zIndex = lib_zindex_manager.push(this._zIndexId, 'datepicker');
            calendar.style.zIndex = zIndex;
        }

        // Position the calendar BEFORE showing it with the animation.
        // Make it measurable (display:block) but invisible so getBoundingClientRect
        // returns accurate values without interference from the slideDown animation.
        calendar.style.display = 'block';
        calendar.style.visibility = 'hidden';
        calendar.style.animation = 'none';
        this._positionCalendar(calendar);

        // Now show with animation
        calendar.style.display = '';
        calendar.style.visibility = '';
        calendar.style.animation = '';
        calendar.classList.add('open');
        this._isOpen = true;
    }

    close() {
        const calendar = this.shadowRoot.querySelector('.datepicker-calendar');
        calendar.classList.remove('open');
        // Remove from z-index manager
        if (typeof lib_zindex_manager !== 'undefined' && this._zIndexId) {
            lib_zindex_manager.pop(this._zIndexId);
            this._zIndexId = null;
        }
        this._isOpen = false;
    }

    toggle() {
        if (this._isOpen) {
            this.close();
        } else {
            this.open();
        }
    }

    _positionCalendar(calendar) {
        // Use the visible wrapper element for positioning (not the host,
        // which may have different dimensions depending on layout context)
        const wrapper = this.shadowRoot.querySelector('.datepicker-wrapper');
        const wrapperRect = wrapper.getBoundingClientRect();

        // Reset position to origin to measure the containing block offset.
        // Transforms on ancestors shift the fixed-positioning origin away
        // from the viewport, so we need to detect and compensate for that.
        calendar.style.top = '0';
        calendar.style.left = '0';
        calendar.offsetHeight; // force reflow
        const calendarRect = calendar.getBoundingClientRect();
        const offsetX = calendarRect.left;
        const offsetY = calendarRect.top;

        const calendarHeight = calendar.offsetHeight || 300;
        const calendarWidth = calendar.offsetWidth || 280;

        // Position: left-aligned with the input field, directly below it
        let top = wrapperRect.bottom - offsetY;
        let left = wrapperRect.left - offsetX;

        // Flip above if it would overflow the viewport bottom
        if (wrapperRect.bottom + calendarHeight > window.innerHeight) {
            top = wrapperRect.top - calendarHeight - offsetY;
            calendar.style.borderRadius = '8px 8px 0 0';
        } else {
            calendar.style.borderRadius = '0 8px 8px 8px';
        }

        // Prevent overflow right
        if (wrapperRect.left + calendarWidth > window.innerWidth) {
            left = window.innerWidth - calendarWidth - 10 - offsetX;
        }

        // Prevent overflow left
        if (left + offsetX < 0) {
            left = 10 - offsetX;
        }

        calendar.style.top = top + 'px';
        calendar.style.left = left + 'px';
    }

    renderCalendar() {
        const calendar = this.shadowRoot.querySelector('.datepicker-calendar');
        const strings = this.localeStrings;
        const year = this._currentMonth.getFullYear();
        const month = this._currentMonth.getMonth();

        // Build days
        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        const startDay = firstDay.getDay(); // 0 = Sunday
        const daysInMonth = lastDay.getDate();

        const today = new Date();
        today.setHours(0, 0, 0, 0);

        const selectedDate = this._value ? this.parseDate(this._value) : null;
        const minDate = this.getAttribute('min') ? this.parseDate(this.getAttribute('min')) : null;
        const maxDate = this.getAttribute('max') ? this.parseDate(this.getAttribute('max')) : null;

        let daysHtml = '';

        // Previous month days
        const prevMonthDays = startDay;
        const prevMonth = new Date(year, month, 0);
        for (let i = prevMonthDays - 1; i >= 0; i--) {
            const day = prevMonth.getDate() - i;
            daysHtml += `<button class="datepicker-day other-month" data-date="${year}-${month}-${day}">${day}</button>`;
        }

        // Current month days
        for (let day = 1; day <= daysInMonth; day++) {
            const date = new Date(year, month, day);
            let classes = ['datepicker-day'];

            if (date.getTime() === today.getTime()) {
                classes.push('today');
            }

            if (selectedDate && date.getTime() === selectedDate.getTime()) {
                classes.push('selected');
            }

            if ((minDate && date < minDate) || (maxDate && date > maxDate)) {
                classes.push('disabled');
            }

            const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            daysHtml += `<button class="${classes.join(' ')}" data-date="${dateStr}">${day}</button>`;
        }

        // Next month days
        const totalCells = Math.ceil((prevMonthDays + daysInMonth) / 7) * 7;
        const nextMonthDays = totalCells - prevMonthDays - daysInMonth;
        for (let day = 1; day <= nextMonthDays; day++) {
            daysHtml += `<button class="datepicker-day other-month" data-date="${year}-${month + 2}-${day}">${day}</button>`;
        }

        calendar.innerHTML = `
            <div class="datepicker-header">
                <button class="datepicker-nav" data-action="prev-month"></button>
                <span class="datepicker-title">${strings.months[month]} ${year}</span>
                <button class="datepicker-nav" data-action="next-month"></button>
            </div>
            <div class="datepicker-weekdays">
                ${strings.days.map(d => `<span class="datepicker-weekday">${d}</span>`).join('')}
            </div>
            <div class="datepicker-days">
                ${daysHtml}
            </div>
            <div class="datepicker-footer">
                <button class="datepicker-btn" data-action="today">${strings.today}</button>
                <button class="datepicker-btn" data-action="clear">${strings.clear}</button>
            </div>
        `;

        // Event listeners for calendar
        calendar.querySelectorAll('.datepicker-day:not(.disabled)').forEach(btn => {
            btn.addEventListener('click', (e) => {
                this.selectDate(e.target.dataset.date);
            });
        });

        calendar.querySelector('[data-action="prev-month"]').addEventListener('click', () => {
            this._currentMonth.setMonth(this._currentMonth.getMonth() - 1);
            this.renderCalendar();
        });

        calendar.querySelector('[data-action="next-month"]').addEventListener('click', () => {
            this._currentMonth.setMonth(this._currentMonth.getMonth() + 1);
            this.renderCalendar();
        });

        calendar.querySelector('[data-action="today"]').addEventListener('click', () => {
            const today = new Date();
            this.selectDate(`${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`);
        });

        calendar.querySelector('[data-action="clear"]').addEventListener('click', () => {
            this.selectDate('');
        });
    }

    selectDate(dateStr) {
        this._value = dateStr;
        this.setAttribute('value', dateStr);
        this.updateDisplay();
        this.close();

        // Update hidden input
        const hidden = this.shadowRoot.querySelector('input[type="hidden"]');
        hidden.value = dateStr;

        // Dispatch change event
        this.dispatchEvent(new CustomEvent('change', {
            detail: { value: dateStr },
            bubbles: true,
            composed: true
        }));
    }

    parseInputValue(inputValue) {
        if (!inputValue) {
            this.selectDate('');
            return;
        }

        // Try to parse the input value based on format
        let date = null;
        const parts = inputValue.split(/[-/.]/);
        const currentYear = new Date().getFullYear();

        // Handle partial date (day-month only, no year) - auto-add current year
        if (parts.length === 2) {
            let day, month;

            switch (this._format) {
                case 'mm-dd-yyyy':
                    month = parseInt(parts[0], 10);
                    day = parseInt(parts[1], 10);
                    break;
                default: // dd-mm-yyyy and yyyy-mm-dd (partial makes less sense for yyyy-mm-dd)
                    day = parseInt(parts[0], 10);
                    month = parseInt(parts[1], 10);
                    break;
            }

            if (day >= 1 && day <= 31 && month >= 1 && month <= 12) {
                date = new Date(currentYear, month - 1, day);
                if (!isNaN(date.getTime())) {
                    const dateStr = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
                    this.selectDate(dateStr);
                }
            }
            return;
        }

        if (parts.length === 3) {
            let day, month, year;

            switch (this._format) {
                case 'mm-dd-yyyy':
                    month = parseInt(parts[0], 10);
                    day = parseInt(parts[1], 10);
                    year = parseInt(parts[2], 10);
                    break;
                case 'yyyy-mm-dd':
                    year = parseInt(parts[0], 10);
                    month = parseInt(parts[1], 10);
                    day = parseInt(parts[2], 10);
                    break;
                default: // dd-mm-yyyy
                    day = parseInt(parts[0], 10);
                    month = parseInt(parts[1], 10);
                    year = parseInt(parts[2], 10);
                    break;
            }

            // Smart year expansion (matching formval_nl.js logic)
            // Years 0-40 become 2000-2040, years 41-99 become 1941-1999
            if (year <= 40) {
                year += 2000;
            } else if (year < 100) {
                year += 1900;
            } else if (year < 1000) {
                year += 1000;
            }

            date = new Date(year, month - 1, day);
            if (!isNaN(date.getTime())) {
                const dateStr = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
                this.selectDate(dateStr);
            }
        }
    }

    parseDate(dateStr) {
        if (!dateStr) return null;
        const parts = dateStr.split('-');
        if (parts.length === 3) {
            // Detect format: if first part is 4 digits, it's YYYY-MM-DD, otherwise DD-MM-YYYY
            if (parts[0].length === 4) {
                // YYYY-MM-DD format
                return new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
            } else {
                // DD-MM-YYYY format
                return new Date(parseInt(parts[2]), parseInt(parts[1]) - 1, parseInt(parts[0]));
            }
        }
        return null;
    }

    updateDisplay() {
        const input = this.shadowRoot.querySelector('.datepicker-input');
        const hidden = this.shadowRoot.querySelector('input[type="hidden"]');

        if (!input) return;

        if (this._value) {
            const date = this.parseDate(this._value);
            if (date) {
                const day = String(date.getDate()).padStart(2, '0');
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const year = date.getFullYear();

                switch (this._format) {
                    case 'mm-dd-yyyy':
                        input.value = `${month}-${day}-${year}`;
                        break;
                    case 'yyyy-mm-dd':
                        input.value = `${year}-${month}-${day}`;
                        break;
                    default: // dd-mm-yyyy
                        input.value = `${day}-${month}-${year}`;
                        break;
                }
            }
        } else {
            input.value = '';
        }

        if (hidden) {
            hidden.value = this._value;
        }
    }

    updateInputState() {
        const input = this.shadowRoot.querySelector('.datepicker-input');
        if (input) {
            input.disabled = this.hasAttribute('disabled');
            input.readOnly = this.hasAttribute('readonly');
        }
    }
}

// Register the component
customElements.define('lib-datepicker', LibDatepicker);

} // end guard
