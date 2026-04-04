/**
 * <vertical-timeline> Web Component (No Shadow DOM)
 *
 * Two view modes: date view (year > month > day) and block view (by blok).
 * Designed for displaying draaiboek (schedule) items.
 * Uses lib-timeline.css for styling, lib-dialog for popups, cma-htmledit for editing.
 *
 * Attributes:
 *   expanded  - "all" (default), "none", "smart" (today-focused), or comma-separated years
 *   locale    - locale for month/day names (default: "nl-NL")
 *   sort      - "asc" (default) or "desc"
 *   view      - "date" (default) or "block"
 *
 * Events:
 *   tl-item-click, tl-visibility-change, tl-save, tl-edit,
 *   tl-download-add, tl-download-delete, tl-view-change
 */
class VerticalTimeline extends HTMLElement {
  constructor() {
    super();
    this._data = [];
    this._rendered = false;
    this._view = 'date';
    this._blokPopups = {};
  }

  static get observedAttributes() {
    return ['expanded', 'locale', 'sort', 'view'];
  }

  get data() { return this._data; }
  set data(val) {
    this._data = Array.isArray(val) ? val : [];
    this._render();
  }

  get locale() { return this.getAttribute('locale') || 'nl-NL'; }
  get sortDir() { return this.getAttribute('sort') || 'asc'; }
  get viewMode() { return this._view; }
  set viewMode(v) { this._view = v; this._render(); }

  connectedCallback() {
    if (!this._rendered) { this._render(); this._rendered = true; }
  }

  attributeChangedCallback(name, oldVal, newVal) {
    if (name === 'view') this._view = newVal || 'date';
    if (oldVal !== newVal && this._rendered) this._render();
  }

  /* ------------------------------------------------------------------ */
  /*  Helpers                                                           */
  /* ------------------------------------------------------------------ */

  _esc(str) { var el = document.createElement('span'); el.textContent = str; return el.innerHTML; }
  _escAttr(str) { return str.replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

  _sortKeys(obj, dir) {
    return Object.keys(obj).map(Number).sort(function(a,b) { return dir === 'asc' ? a - b : b - a; });
  }

  _monthNames() {
    if (!this._mn) { this._mn = []; for (var i=0;i<12;i++) this._mn.push(new Date(2000,i,1).toLocaleString(this.locale,{month:'long'})); }
    return this._mn;
  }

  _groupByDate(items) {
    var years = {};
    items.forEach(function(item) {
      var d = new Date(item.date); if (isNaN(d.getTime())) return;
      var y=d.getFullYear(), m=d.getMonth(), day=d.getDate();
      if (!years[y]) years[y]={}; if (!years[y][m]) years[y][m]={};
      if (!years[y][m][day]) years[y][m][day]=[]; years[y][m][day].push(item);
    });
    return years;
  }

  _groupByBlok(items) {
    var bloks = {}, order = [];
    items.forEach(function(item) {
      var key = item.blokNaam || item.description || 'Overig';
      if (!bloks[key]) { bloks[key] = []; order.push(key); }
      bloks[key].push(item);
    });
    // Sort items within each blok by date
    Object.keys(bloks).forEach(function(k) {
      bloks[k].sort(function(a,b) { return a.date < b.date ? -1 : a.date > b.date ? 1 : (a.tijd||'').localeCompare(b.tijd||''); });
    });
    return { bloks: bloks, order: order };
  }

  /* ------------------------------------------------------------------ */
  /*  Smart expand logic                                                */
  /* ------------------------------------------------------------------ */

  _getExpandMode() {
    var attr = (this.getAttribute('expanded') || 'smart').trim();
    if (attr === 'smart') return 'smart';
    if (attr === 'all') return 'all';
    if (attr === 'none') return 'none';
    return attr; // comma-separated years
  }

  _courseStatus() {
    var today = new Date(); today.setHours(0,0,0,0);
    var minDate = null, maxDate = null;
    this._data.forEach(function(item) {
      var d = new Date(item.date); if (isNaN(d.getTime())) return;
      if (!minDate || d < minDate) minDate = d;
      if (!maxDate || d > maxDate) maxDate = d;
    });
    if (!minDate) return 'empty';
    if (maxDate < today) return 'finished';
    if (minDate > today) return 'not_started';
    return 'running';
  }

  /* ------------------------------------------------------------------ */
  /*  Main render                                                       */
  /* ------------------------------------------------------------------ */

  _render() {
    var self = this;
    this._mn = null; // reset month name cache

    // View tabs
    var tabsHtml = '<div class="tl-view-tabs">';
    tabsHtml += '<button class="tl-view-tab' + (this._view === 'date' ? ' tl-view-tab--active' : '') + '" data-view="date">Datum</button>';
    tabsHtml += '<button class="tl-view-tab' + (this._view === 'block' ? ' tl-view-tab--active' : '') + '" data-view="block">Blokken</button>';
    tabsHtml += '</div>';

    var html;
    if (this._view === 'block') {
      html = this._renderBlockView();
    } else {
      html = this._renderDateView();
    }

    if (!html) html = '<div class="tl-empty">Geen items om weer te geven.</div>';

    this.innerHTML = tabsHtml + '<div class="tl-root">' + html + '</div>';
    this._bindEvents();

    // Smart scroll for running courses
    if (this._view === 'date' && this._getExpandMode() === 'smart' && this._courseStatus() === 'running') {
      var todayEl = this.querySelector('.tl-day--today');
      if (todayEl) {
        setTimeout(function() { todayEl.scrollIntoView({ behavior: 'smooth', block: 'center' }); }, 100);
      }
    }
  }

  /* ------------------------------------------------------------------ */
  /*  Date view                                                         */
  /* ------------------------------------------------------------------ */

  _renderDateView() {
    var self = this;
    var grouped = this._groupByDate(this._data);
    var dir = this.sortDir;
    var monthNames = this._monthNames();
    var today = new Date(); today.setHours(0,0,0,0);
    var expandMode = this._getExpandMode();
    var status = this._courseStatus();
    var showPastButton = (status === 'running' && expandMode === 'smart');

    var html = '';
    this._blokPopups = {};

    // "Toon eerdere lesdagen" button
    if (showPastButton) {
      html += '<button class="tl-show-past">Toon eerdere lesdagen</button>';
    }

    var sortedYears = this._sortKeys(grouped, dir);
    sortedYears.forEach(function(year) {
      var yearExpanded;
      if (expandMode === 'all' || status === 'finished' || status === 'not_started') yearExpanded = true;
      else if (expandMode === 'none') yearExpanded = false;
      else if (expandMode === 'smart') yearExpanded = (year === today.getFullYear());
      else yearExpanded = expandMode.split(',').indexOf(String(year)) !== -1;

      html += '<div class="tl-year">';
      html += '<div class="tl-row tl-toggle" data-level="year" aria-expanded="' + yearExpanded + '">';
      html += '<span class="tl-line-node tl-node--year"></span>';
      html += '<span class="tl-label tl-label--year">' + year + '</span>';
      html += self._groupActionsHtml('year');
      html += '</div>';
      html += '<div class="tl-children"' + (yearExpanded ? '' : ' style="display:none"') + '>';

      var sortedMonths = self._sortKeys(grouped[year], dir);
      sortedMonths.forEach(function(month) {
        var monthExpanded;
        if (expandMode === 'smart') monthExpanded = yearExpanded && (month === today.getMonth() && year === today.getFullYear());
        else monthExpanded = yearExpanded;

        html += '<div class="tl-month">';
        html += '<div class="tl-row tl-toggle" data-level="month" aria-expanded="' + monthExpanded + '">';
        html += '<span class="tl-line-node tl-node--month"></span>';
        html += '<span class="tl-label tl-label--month">' + monthNames[month] + '</span>';
        html += self._groupActionsHtml('month');
        html += '</div>';
        html += '<div class="tl-children"' + (monthExpanded ? '' : ' style="display:none"') + '>';

        var sortedDays = self._sortKeys(grouped[year][month], dir);
        sortedDays.forEach(function(day) {
          var dateObj = new Date(year, month, day);
          var isPast = dateObj < today;
          var isToday = dateObj.getTime() === today.getTime();
          var dayExpanded;
          if (expandMode === 'smart') dayExpanded = monthExpanded && (isToday || dateObj > today);
          else dayExpanded = monthExpanded;

          // Hide past items behind button
          var pastHidden = showPastButton && isPast && !isToday;

          html += '<div class="tl-day' + (isPast ? ' tl-day--past' : '') + (isToday ? ' tl-day--today' : '') + (pastHidden ? ' tl-day--hidden-past' : '') + '"' + (pastHidden ? ' style="display:none"' : '') + '>';
          html += '<div class="tl-row tl-toggle" data-level="day" aria-expanded="' + dayExpanded + '">';

          var shortDay = dateObj.toLocaleString(self.locale, { weekday: 'short' });
          var shortMonth = dateObj.toLocaleString(self.locale, { month: 'short' });
          html += '<div class="tl-day-header">';
          html += '<span class="tl-day-line"></span>';
          html += '<span class="tl-day-circle">';
          html += '<span class="tl-day-weekname">' + shortDay + '</span>';
          html += '<span class="tl-day-num">' + day + '</span>';
          html += '<span class="tl-day-monthname">' + shortMonth + '</span>';
          html += '</span>';
          if (isToday) html += '<span class="tl-badge--today">vandaag</span>';
          html += '</div>';
          html += '</div>';
          html += '<div class="tl-children"' + (dayExpanded ? '' : ' style="display:none"') + '>';

          var dayItems = grouped[year][month][day].slice();
          dayItems.sort(function(a,b) { return (a.tijd||'').localeCompare(b.tijd||''); });

          // Track bloks shown on this day for blok headers
          var seenBloks = {};

          dayItems.forEach(function(item, idx) {
            // Blok header: first occurrence of this blok on this day
            var blokKey = item.blokNaam || item.description || '';
            if (blokKey && !seenBloks[blokKey]) {
              seenBloks[blokKey] = true;
              // Show blok header with optional info icon
              html += '<div class="tl-blok-header" data-blok-key="' + self._escAttr(blokKey) + '">';
              html += '<span class="tl-blok-header-label">blok</span> ';
              html += '<span>' + self._esc(blokKey) + '</span>';
              // Info icon if blokTekst available
              if (item.blokTekst) {
                var blokId = 'tl-blok-' + year + '-' + month + '-' + day + '-' + blokKey.replace(/\W/g,'_');
                html += ' <span class="tl-blok-info-icon" data-blok="' + blokId + '" data-tooltip="Toon blokinformatie">i</span>';
                self._blokPopups[blokId] = { id: blokId, naam: blokKey, tekst: item.blokTekst };
              }
              html += '</div>';
            }

            html += self._renderItem(item, idx, 'tl-item-' + year + '-' + month + '-' + day + '-' + idx);
          });

          html += '</div></div>'; // day children + day
        });
        html += '</div></div>'; // month children + month
      });
      html += '</div></div>'; // year children + year
    });

    return html;
  }

  /* ------------------------------------------------------------------ */
  /*  Block view                                                        */
  /* ------------------------------------------------------------------ */

  _renderBlockView() {
    var self = this;
    var data = this._groupByBlok(this._data);
    var monthNames = this._monthNames();
    var html = '';
    this._blokPopups = {};

    data.order.forEach(function(blokName, bi) {
      var items = data.bloks[blokName];
      var blokExpanded = true;

      html += '<div class="tl-year tl-blok-group">';
      html += '<div class="tl-row tl-toggle" data-level="year" aria-expanded="' + blokExpanded + '">';
      html += '<span class="tl-line-node tl-node--month"></span>';
      html += '<span class="tl-label tl-label--month">' + self._esc(blokName) + '</span>';

      // Block-level visibility controls for assistenten
      html += '<span class="tl-group-actions tl-blok-vis-actions">';
      html += '<button class="tl-action-btn tl-blok-vis-btn" data-blok="' + self._escAttr(blokName) + '" data-vis="-1" data-tooltip="Niet tonen">&times;</button>';
      html += '<button class="tl-action-btn tl-blok-vis-btn" data-blok="' + self._escAttr(blokName) + '" data-vis="0" data-tooltip="Automatisch">A</button>';
      html += '<button class="tl-action-btn tl-blok-vis-btn" data-blok="' + self._escAttr(blokName) + '" data-vis="1" data-tooltip="Alvast tonen">&check;</button>';
      html += self._groupActionsHtml('year');
      html += '</span>';

      html += '</div>';
      html += '<div class="tl-children"' + (blokExpanded ? '' : ' style="display:none"') + '>';

      // Blok intro text
      var blokTekst = '';
      for (var ti=0; ti<items.length; ti++) { if (items[ti].blokTekst) { blokTekst = items[ti].blokTekst; break; } }
      if (blokTekst) {
        var blokId = 'tl-blokview-' + bi;
        html += '<div class="tl-blok-info" data-blok="' + blokId + '">';
        html += '<span class="tl-blok-info-icon">i</span>';
        html += '<span>Blokinformatie</span>';
        html += '</div>';
        self._blokPopups[blokId] = { id: blokId, naam: blokName, tekst: blokTekst };
      }

      items.forEach(function(item, idx) {
        var d = new Date(item.date);
        var dayNum = d.getDate();
        var shortMonth = d.toLocaleString(self.locale, { month: 'short' });
        var shortDay = d.toLocaleString(self.locale, { weekday: 'short' });

        html += '<div class="tl-day" style="margin-bottom:8px">';
        // Mini date circle
        html += '<span class="tl-day-line" style="margin-top:16px"></span>';
        html += '<span class="tl-day-circle" style="width:36px;height:36px;min-width:36px;font-size:12px;margin-left:-6px">';
        html += '<span class="tl-day-weekname">' + shortDay + '</span>';
        html += '<span class="tl-day-num" style="font-size:13px">' + dayNum + '</span>';
        html += '<span class="tl-day-monthname">' + shortMonth + '</span>';
        html += '</span>';
        html += '<div style="padding-left:10px;padding-top:2px;grid-row:1;grid-column:3;min-width:0">';
        html += self._renderItem(item, idx, 'tl-bitem-' + bi + '-' + idx);
        html += '</div></div>';
      });

      html += '</div></div>'; // children + blok group
    });

    return html;
  }

  /* ------------------------------------------------------------------ */
  /*  Render single item                                                */
  /* ------------------------------------------------------------------ */

  _renderItem(item, idx, itemId) {
    var self = this;
    var hasContent = item.content || (item.downloads && item.downloads.length);
    var visible = item.visible !== false;
    var html = '';

    // Skip description if same as title
    var showDesc = item.description && item.description !== '-' && item.description !== item.title;

    html += '<div class="tl-item' + (!visible ? ' tl-item--hidden' : '') + '" data-idx="' + idx + '">';

    // Item header
    html += '<div class="tl-item-header' + (hasContent ? ' tl-item-header--expandable' : '') + '"' +
             (hasContent ? ' data-target="' + itemId + '" data-tooltip="Klik om in of uit te klappen"' : '') + '>';
    html += '<div class="tl-item-summary">';

    // Title + time
    html += '<div class="tl-item-headline">';
    html += '<span class="tl-item-title">' + self._esc(item.title || '') + '</span>';
    if (item.tijd) {
      html += '<span class="tl-time">' + self._esc(item.tijd);
      if (item.eindtijd) html += ' - ' + self._esc(item.eindtijd);
      html += '</span>';
    }
    html += '</div>';

    // Location + Docenten
    if (item.locatie || item.docenten) {
      html += '<span class="tl-meta">';
      if (item.locatie) html += '<span class="tl-meta-item tl-meta-loc">' + self._esc(item.locatie) + '</span>';
      if (item.docenten) html += '<span class="tl-meta-item tl-meta-doc">' + self._esc(item.docenten) + '</span>';
      html += '</span>';
    }

    if (showDesc) html += '<span class="tl-item-desc">' + self._esc(item.description) + '</span>';

    html += '</div></div>'; // summary + header

    // Expandable body
    if (hasContent) {
      html += '<div class="tl-item-body" id="' + itemId + '" style="display:none">';

      // Visibility
      var vs = item.visibleState !== undefined ? item.visibleState : 0;
      html += '<div class="tl-visibility" data-roosterid="' + (item.roosterId||'') + '">';
      html += '<span class="tl-visibility-label">Zichtbaarheid:</span>';
      html += '<label class="tl-vis-option tl-vis-off' + (vs==-1?' tl-vis-active':'') + '"><input type="radio" name="vis_'+itemId+'" value="-1"'+(vs==-1?' checked':'')+'> Niet tonen</label>';
      html += '<label class="tl-vis-option tl-vis-auto' + (vs==0?' tl-vis-active':'') + '"><input type="radio" name="vis_'+itemId+'" value="0"'+(vs==0?' checked':'')+'> Automatisch</label>';
      html += '<label class="tl-vis-option tl-vis-on' + (vs==1?' tl-vis-active':'') + '"><input type="radio" name="vis_'+itemId+'" value="1"'+(vs==1?' checked':'')+'> Alvast tonen</label>';
      html += '</div>';

      // Edit bar
      html += '<div class="tl-edit-bar" data-roosterid="'+(item.roosterId||'')+'">';
      html += '<button class="tl-edit-btn" data-roosterid="'+(item.roosterId||'')+'">Wijzig</button>';
      html += '<button class="tl-save-btn" data-roosterid="'+(item.roosterId||'')+'" style="display:none">Opslaan</button>';
      html += '<button class="tl-cancel-btn" data-roosterid="'+(item.roosterId||'')+'" style="display:none">Annuleren</button>';
      html += '</div>';

      // Content
      if (item.content) html += '<div class="tl-content" data-roosterid="'+(item.roosterId||'')+'">' + item.content + '</div>';
      html += '<div class="tl-editor-container" data-roosterid="'+(item.roosterId||'')+'" style="display:none"></div>';

      // Downloads
      html += '<div class="tl-downloads" data-roosterid="'+(item.roosterId||'')+'">';
      if (item.downloads && item.downloads.length) {
        html += '<span class="tl-downloads-label">Materiaal:</span>';
        item.downloads.forEach(function(dl) {
          html += '<div class="tl-download-row">';
          html += '<a class="tl-download" href="'+self._escAttr(dl.url||'#')+'" target="_blank">'+self._esc(dl.title||dl.url||'Download')+'</a>';
          html += '<button class="tl-dl-delete" data-dlid="'+(dl.id||'')+'" data-tooltip="Verwijder download">&times;</button>';
          html += '</div>';
        });
      }
      html += '<button class="tl-dl-add" data-roosterid="'+(item.roosterId||'')+'" data-tooltip="Upload een bestand">+ Plaats download</button>';
      html += '</div>';

      html += '</div>'; // item-body
    }

    html += '</div>'; // tl-item
    return html;
  }

  _groupActionsHtml(scope) {
    return '<span class="tl-group-actions">' +
      '<button class="tl-action-btn tl-action-expand" data-scope="'+scope+'" data-tooltip="Alles uitklappen">+</button>' +
      '<button class="tl-action-btn tl-action-collapse" data-scope="'+scope+'" data-tooltip="Alles inklappen">&minus;</button>' +
      '</span>';
  }

  /* ------------------------------------------------------------------ */
  /*  Events                                                            */
  /* ------------------------------------------------------------------ */

  _bindEvents() {
    var self = this;

    // View tab switching
    this.querySelectorAll('.tl-view-tab').forEach(function(tab) {
      tab.addEventListener('click', function() {
        self._view = this.getAttribute('data-view');
        self.dispatchEvent(new CustomEvent('tl-view-change', { detail: { view: self._view }, bubbles: true }));
        self._render();
      });
    });

    // "Toon eerdere lesdagen" button
    var pastBtn = this.querySelector('.tl-show-past');
    if (pastBtn) {
      pastBtn.addEventListener('click', function() {
        self.querySelectorAll('.tl-day--hidden-past').forEach(function(d) { d.style.display = ''; d.classList.remove('tl-day--hidden-past'); });
        this.style.display = 'none';
      });
    }

    // Year / Month / Day toggles
    this.querySelectorAll('.tl-toggle').forEach(function(toggle) {
      toggle.addEventListener('click', function(e) {
        if (e.target.closest('.tl-group-actions')) return;
        if (e.target.closest('.tl-blok-vis-actions')) return;
        var expanded = this.getAttribute('aria-expanded') === 'true';
        var children = this.nextElementSibling;
        var level = this.getAttribute('data-level');
        if (expanded) {
          children.style.display = 'none';
          this.setAttribute('aria-expanded', 'false');
        } else {
          children.style.display = 'block';
          this.setAttribute('aria-expanded', 'true');
          if (level === 'month') {
            children.querySelectorAll('.tl-day > .tl-toggle').forEach(function(dt) {
              dt.setAttribute('aria-expanded', 'true');
              var dc = dt.nextElementSibling; if (dc) dc.style.display = 'block';
            });
          }
        }
      });
    });

    // Group expand/collapse
    var expandWithin = function(container, expand) {
      container.querySelectorAll('.tl-toggle').forEach(function(t) {
        t.setAttribute('aria-expanded', expand?'true':'false');
        var ch = t.nextElementSibling;
        if (ch && ch.classList.contains('tl-children')) ch.style.display = expand?'block':'none';
      });
      container.querySelectorAll('.tl-item-header--expandable').forEach(function(h) {
        var body = document.getElementById(h.getAttribute('data-target'));
        if (body) { body.style.display = expand?'block':'none'; if(expand) h.classList.add('tl-item-header--open'); else h.classList.remove('tl-item-header--open'); }
      });
    };

    this.querySelectorAll('.tl-action-expand').forEach(function(btn) {
      btn.addEventListener('click', function(e) {
        e.stopPropagation();
        var group = this.closest('.tl-'+this.getAttribute('data-scope'));
        if (!group) return;
        var toggle = group.querySelector(':scope > .tl-toggle');
        if (toggle) { toggle.setAttribute('aria-expanded','true'); var ch=toggle.nextElementSibling; if(ch) ch.style.display='block'; }
        expandWithin(group, true);
      });
    });

    this.querySelectorAll('.tl-action-collapse').forEach(function(btn) {
      btn.addEventListener('click', function(e) {
        e.stopPropagation();
        var group = this.closest('.tl-'+this.getAttribute('data-scope'));
        if (!group) return;
        var children = group.querySelector(':scope > .tl-toggle + .tl-children');
        if (children) expandWithin(children, false);
      });
    });

    // Item expand/collapse
    this.querySelectorAll('.tl-item-header--expandable').forEach(function(header) {
      header.addEventListener('click', function(e) {
        if (e.target.tagName === 'A') return;
        var body = document.getElementById(this.getAttribute('data-target'));
        if (!body) return;
        if (body.style.display === 'none') { body.style.display='block'; this.classList.add('tl-item-header--open'); }
        else { body.style.display='none'; this.classList.remove('tl-item-header--open'); }
      });
    });

    // Blok info - open via lib-dialog
    this.querySelectorAll('[data-blok].tl-blok-info-icon, .tl-blok-info').forEach(function(info) {
      info.addEventListener('click', function(e) {
        e.stopPropagation();
        var blokId = this.getAttribute('data-blok');
        var bp = self._blokPopups[blokId];
        if (!bp) return;
        if (typeof LibDialog !== 'undefined' && LibDialog.alert) {
          LibDialog.alert(bp.tekst, { title: bp.naam, size: 'large' });
        } else {
          var w = window.open('','_blank','width=640,height=480,scrollbars=yes');
          w.document.write('<html><head><title>'+bp.naam+'</title></head><body style="font-family:sans-serif;padding:20px"><h2>'+bp.naam+'</h2>'+bp.tekst+'</body></html>');
        }
      });
    });

    // Blok-level visibility (block view)
    this.querySelectorAll('.tl-blok-vis-btn').forEach(function(btn) {
      btn.addEventListener('click', function(e) {
        e.stopPropagation();
        var blokName = this.getAttribute('data-blok');
        var vis = parseInt(this.getAttribute('data-vis'));
        // Set all visibility radios within this blok group
        var group = this.closest('.tl-blok-group');
        if (group) {
          group.querySelectorAll('.tl-vis-option').forEach(function(opt) { opt.classList.remove('tl-vis-active'); });
          group.querySelectorAll('.tl-vis-option input[value="'+vis+'"]').forEach(function(radio) {
            radio.checked = true; radio.closest('.tl-vis-option').classList.add('tl-vis-active');
          });
        }
        self.dispatchEvent(new CustomEvent('tl-blok-visibility-change', {
          detail: { blokName: blokName, visibleState: vis }, bubbles: true
        }));
      });
    });

    // Visibility radio toggle
    this.querySelectorAll('.tl-vis-option').forEach(function(label) {
      label.addEventListener('click', function() {
        var group = this.closest('.tl-visibility');
        group.querySelectorAll('.tl-vis-option').forEach(function(l) { l.classList.remove('tl-vis-active'); });
        this.classList.add('tl-vis-active');
        this.querySelector('input').checked = true;
        self.dispatchEvent(new CustomEvent('tl-visibility-change', {
          detail: { roosterId: group.getAttribute('data-roosterid'), visibleState: parseInt(this.querySelector('input').value) }, bubbles: true
        }));
      });
    });

    // Edit/Save/Cancel
    this.querySelectorAll('.tl-edit-btn').forEach(function(btn) {
      btn.addEventListener('click', function(e) {
        e.stopPropagation();
        var rid = this.getAttribute('data-roosterid'), bar = this.closest('.tl-edit-bar'), body = bar.closest('.tl-item-body');
        var cd = body.querySelector('.tl-content[data-roosterid="'+rid+'"]'), ec = body.querySelector('.tl-editor-container[data-roosterid="'+rid+'"]');
        if(cd) cd.style.display='none'; ec.style.display='block'; this.style.display='none';
        bar.querySelector('.tl-save-btn').style.display=''; bar.querySelector('.tl-cancel-btn').style.display='';
        if (!ec.querySelector('cma-htmledit')) {
          var el = document.createElement('cma-htmledit');
          el.setAttribute('name','draaiboek_'+rid); el.setAttribute('height','350'); el.setAttribute('mode','full'); el.setAttribute('allow-br','');
          el.setAttribute('value', cd ? cd.innerHTML : ''); ec.appendChild(el);
        }
      });
    });

    this.querySelectorAll('.tl-save-btn').forEach(function(btn) {
      btn.addEventListener('click', function(e) {
        e.stopPropagation();
        var rid = this.getAttribute('data-roosterid'), bar = this.closest('.tl-edit-bar'), body = bar.closest('.tl-item-body');
        var cd = body.querySelector('.tl-content[data-roosterid="'+rid+'"]'), ec = body.querySelector('.tl-editor-container[data-roosterid="'+rid+'"]');
        var el = ec.querySelector('cma-htmledit'), newContent = el ? el.value : '';
        if(cd) { cd.innerHTML = newContent; cd.style.display=''; }
        if(el) { if(el.editor) try{el.editor.destroy(true);}catch(ex){} el.remove(); }
        ec.style.display='none'; bar.querySelector('.tl-edit-btn').style.display=''; this.style.display='none'; bar.querySelector('.tl-cancel-btn').style.display='none';
        self.dispatchEvent(new CustomEvent('tl-save', { detail: { roosterId: rid, field: 'draaiboektext', content: newContent }, bubbles: true }));
      });
    });

    this.querySelectorAll('.tl-cancel-btn').forEach(function(btn) {
      btn.addEventListener('click', function(e) {
        e.stopPropagation();
        var rid = this.getAttribute('data-roosterid'), bar = this.closest('.tl-edit-bar'), body = bar.closest('.tl-item-body');
        var cd = body.querySelector('.tl-content[data-roosterid="'+rid+'"]'), ec = body.querySelector('.tl-editor-container[data-roosterid="'+rid+'"]');
        var el = ec.querySelector('cma-htmledit');
        if(el) { if(el.editor) try{el.editor.destroy(true);}catch(ex){} el.remove(); }
        ec.style.display='none'; if(cd) cd.style.display='';
        bar.querySelector('.tl-edit-btn').style.display=''; this.style.display='none'; bar.querySelector('.tl-save-btn').style.display='none';
      });
    });

    // Downloads
    this.querySelectorAll('.tl-dl-add').forEach(function(btn) {
      btn.addEventListener('click', function(e) { e.stopPropagation(); self.dispatchEvent(new CustomEvent('tl-download-add', { detail: { roosterId: this.getAttribute('data-roosterid') }, bubbles: true })); });
    });
    this.querySelectorAll('.tl-dl-delete').forEach(function(btn) {
      btn.addEventListener('click', function(e) { e.stopPropagation(); self.dispatchEvent(new CustomEvent('tl-download-delete', { detail: { downloadId: this.getAttribute('data-dlid'), roosterId: this.closest('.tl-downloads').getAttribute('data-roosterid') }, bubbles: true })); });
    });
  }

  /* ------------------------------------------------------------------ */
  /*  Public API                                                        */
  /* ------------------------------------------------------------------ */

  expandAll() { this.querySelectorAll('.tl-toggle').forEach(function(t) { t.setAttribute('aria-expanded','true'); t.nextElementSibling.style.display='block'; }); }
  collapseAll() { this.querySelectorAll('.tl-toggle').forEach(function(t) { t.setAttribute('aria-expanded','false'); t.nextElementSibling.style.display='none'; }); }

  goToDate(dateStr) {
    var d = new Date(dateStr); if (isNaN(d.getTime())) return;
    var self = this, monthNames = this._monthNames();

    var yearEl = null;
    this.querySelectorAll('.tl-year').forEach(function(el) { var l=el.querySelector('.tl-label--year'); if(l && parseInt(l.textContent,10)===d.getFullYear()) yearEl=el; });
    if (!yearEl) return;
    var expand = function(t) { if(t && t.getAttribute('aria-expanded')!=='true') t.click(); };
    expand(yearEl.querySelector(':scope > .tl-toggle'));

    var monthEl = null;
    yearEl.querySelectorAll('.tl-month').forEach(function(el) { var l=el.querySelector('.tl-label--month'); if(l && l.textContent===monthNames[d.getMonth()]) monthEl=el; });
    if (!monthEl) return;
    expand(monthEl.querySelector(':scope > .tl-toggle'));

    var dayEl = null;
    monthEl.querySelectorAll('.tl-day').forEach(function(el) { var n=el.querySelector('.tl-day-num'); if(n && parseInt(n.textContent,10)===d.getDate()) dayEl=el; });
    if (!dayEl) return;
    expand(dayEl.querySelector(':scope > .tl-toggle'));
    dayEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }
}

customElements.define('vertical-timeline', VerticalTimeline);
