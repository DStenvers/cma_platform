/**
 * <vertical-timeline> Web Component (No Shadow DOM)
 *
 * Vertical timeline with the line on the left side.
 * Data is grouped by year > month > day, all levels expandable/collapsible.
 * Designed for displaying draaiboek (schedule) items.
 * Uses shared CSS classes for styling (see lib-timeline.css).
 *
 * Usage:
 *   <vertical-timeline></vertical-timeline>
 *   <script>
 *     document.querySelector('vertical-timeline').data = [
 *       {
 *         date: '2026-04-03',           // required - used for grouping
 *         tijd: '09:00',                // start time
 *         eindtijd: '12:00',            // end time
 *         title: 'Introductie module',  // main title
 *         description: 'Dag 1 van...',  // subtitle / omschrijving
 *         content: '<p>HTML content</p>',  // rich HTML body (draaiboektext)
 *         locatie: 'Zaal 201',
 *         docenten: 'Dr. Jansen',
 *         hybrid: false,
 *         downloads: [
 *           { title: 'Reader H1-3', url: '/files/reader.pdf' },
 *           { title: 'Slides', url: '/files/slides.pptx' }
 *         ],
 *         roosterId: 123,              // roosterID - for linking / callbacks
 *         visible: true                 // visibility state
 *       }
 *     ];
 *   </script>
 *
 * Attributes:
 *   expanded  - "all" (default), "none", or comma-separated years
 *   locale    - locale for month/day names (default: "nl-NL")
 *   sort      - "desc" (default, newest first) or "asc"
 *
 * Events:
 *   tl-item-click  - fired when an item card is clicked, detail contains the item data
 */
class VerticalTimeline extends HTMLElement {
  constructor() {
    super();
    this._data = [];
    this._rendered = false;
  }

  static get observedAttributes() {
    return ['expanded', 'locale', 'sort'];
  }

  get data() { return this._data; }
  set data(val) {
    this._data = Array.isArray(val) ? val : [];
    this._render();
  }

  get locale() { return this.getAttribute('locale') || 'nl-NL'; }
  get sortDir() { return this.getAttribute('sort') || 'desc'; }

  connectedCallback() {
    if (!this._rendered) {
      this._render();
      this._rendered = true;
    }
  }

  attributeChangedCallback(name, oldVal, newVal) {
    if (oldVal !== newVal && this._rendered) {
      this._render();
    }
  }

  /* ------------------------------------------------------------------ */
  /*  Grouping                                                          */
  /* ------------------------------------------------------------------ */

  _group(items) {
    var years = {};
    items.forEach(function (item) {
      var d = new Date(item.date);
      if (isNaN(d.getTime())) return;
      var y = d.getFullYear();
      var m = d.getMonth();
      var day = d.getDate();
      if (!years[y]) years[y] = {};
      if (!years[y][m]) years[y][m] = {};
      if (!years[y][m][day]) years[y][m][day] = [];
      years[y][m][day].push(item);
    });
    return years;
  }

  _sortKeys(obj, dir) {
    return Object.keys(obj).map(Number).sort(function (a, b) {
      return dir === 'asc' ? a - b : b - a;
    });
  }

  /* ------------------------------------------------------------------ */
  /*  Rendering                                                         */
  /* ------------------------------------------------------------------ */

  _render() {
    var self = this;
    var grouped = this._group(this._data);
    var expandedAttr = (this.getAttribute('expanded') || 'all').trim();
    var expandAll = expandedAttr === 'all';
    var expandNone = expandedAttr === 'none';
    var dir = this.sortDir;

    var monthNames = [];
    for (var i = 0; i < 12; i++) {
      monthNames.push(new Date(2000, i, 1).toLocaleString(this.locale, { month: 'long' }));
    }

    var today = new Date();
    today.setHours(0, 0, 0, 0);
    var sortedYears = this._sortKeys(grouped, dir);

    var html = '';
    var blokPopups = [];
    sortedYears.forEach(function (year) {
      var yearExpanded = expandNone ? false : (expandAll || expandedAttr.split(',').indexOf(String(year)) !== -1);

      html += '<div class="tl-year">';
      html += '<div class="tl-row tl-toggle" data-level="year" aria-expanded="' + yearExpanded + '">';
      html += '<span class="tl-line-node tl-node--year"></span>';
      html += '<span class="tl-label tl-label--year">' + year + '</span>';
      html += '<span class="tl-group-actions">';
      html += '<button class="tl-action-btn tl-action-expand" data-scope="year" title="Alles uitklappen">+</button>';
      html += '<button class="tl-action-btn tl-action-collapse" data-scope="year" title="Alles inklappen">&minus;</button>';
      html += '</span>';
      html += '</div>';
      html += '<div class="tl-children"' + (yearExpanded ? '' : ' style="display:none"') + '>';

      var sortedMonths = self._sortKeys(grouped[year], dir);
      sortedMonths.forEach(function (month) {
        var monthExpanded = yearExpanded;
        html += '<div class="tl-month">';
        html += '<div class="tl-row tl-toggle" data-level="month" aria-expanded="' + monthExpanded + '">';
        html += '<span class="tl-line-node tl-node--month"></span>';
        html += '<span class="tl-label tl-label--month">' + monthNames[month] + '</span>';
        html += '<span class="tl-group-actions">';
        html += '<button class="tl-action-btn tl-action-expand" data-scope="month" title="Alles uitklappen">+</button>';
        html += '<button class="tl-action-btn tl-action-collapse" data-scope="month" title="Alles inklappen">&minus;</button>';
        html += '</span>';
        html += '</div>';
        html += '<div class="tl-children"' + (monthExpanded ? '' : ' style="display:none"') + '>';

        var sortedDays = self._sortKeys(grouped[year][month], dir);
        sortedDays.forEach(function (day) {
          var dayExpanded = monthExpanded;
          var dateObj = new Date(year, month, day);
          var dayOfWeek = dateObj.toLocaleString(self.locale, { weekday: 'long' });
          var isPast = dateObj < today;
          var isToday = dateObj.getTime() === today.getTime();

          html += '<div class="tl-day' + (isPast ? ' tl-day--past' : '') + (isToday ? ' tl-day--today' : '') + '">';
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
          if (isToday) html += '<span class="tl-badge tl-badge--today">vandaag</span>';
          html += '</div>';
          html += '</div>';
          html += '<div class="tl-children"' + (dayExpanded ? '' : ' style="display:none"') + '>';

          // Block text info icon (show if any item in this day has blokTekst)
          var dayItems = grouped[year][month][day].slice();
          var blokTekst = '';
          var blokNaam = '';
          for (var bi = 0; bi < dayItems.length; bi++) {
            if (dayItems[bi].blokTekst) { blokTekst = dayItems[bi].blokTekst; blokNaam = dayItems[bi].blokNaam || dayItems[bi].description || ''; break; }
          }
          if (blokTekst) {
            var blokId = 'tl-blok-' + year + '-' + month + '-' + day;
            html += '<div class="tl-blok-info" data-blok="' + blokId + '">';
            html += '<span class="tl-blok-info-icon">i</span>';
            html += '<span>' + self._esc(blokNaam) + '</span>';
            html += '</div>';
            blokPopups.push({ id: blokId, naam: blokNaam, tekst: blokTekst });
          }

          // Sort items within a day by time
          dayItems.sort(function (a, b) {
            var ta = a.tijd || '';
            var tb = b.tijd || '';
            return dir === 'asc' ? ta.localeCompare(tb) : tb.localeCompare(ta);
          });

          dayItems.forEach(function (item, idx) {
            var hasContent = item.content || item.downloads;
            var itemId = 'tl-item-' + year + '-' + month + '-' + day + '-' + idx;
            var visible = item.visible !== false;

            html += '<div class="tl-item' + (!visible ? ' tl-item--hidden' : '') + '" data-idx="' + idx + '">';

            // Item header (always visible)
            html += '<div class="tl-row tl-item-header' + (hasContent ? ' tl-item-header--expandable' : '') + '"' +
                     (hasContent ? ' data-target="' + itemId + '"' : '') + '>';
            html += '<span class="tl-line-node tl-node--item"></span>';
            html += '<div class="tl-item-summary">';

            // Title + time on one line
            html += '<div class="tl-item-headline">';
            html += '<span class="tl-item-title">' + self._esc(item.title || '') + '</span>';
            if (item.tijd) {
              html += '<span class="tl-time">' + self._esc(item.tijd);
              if (item.eindtijd) html += ' - ' + self._esc(item.eindtijd);
              html += '</span>';
            }
            html += '</div>';

            // Location + Docenten on one line
            if (item.locatie || item.hybrid || item.docenten) {
              html += '<span class="tl-meta">';
              if (item.locatie) html += '<span class="tl-meta-item tl-meta-loc">' + self._esc(item.locatie) + '</span>';
              if (item.hybrid) html += '<span class="tl-badge tl-badge--hybrid" style="display:none">hybrid</span>';
              if (item.docenten) html += '<span class="tl-meta-item tl-meta-doc">' + self._esc(item.docenten) + '</span>';
              html += '</span>';
            }

            // Description / subtitle
            if (item.description && item.description !== '-') {
              html += '<span class="tl-item-desc">' + self._esc(item.description) + '</span>';
            }

            if (hasContent) {
              html += '<span class="tl-expand-hint">&#9654; details</span>';
            }

            html += '</div>'; // summary
            html += '</div>'; // row header

            // Expandable content
            if (hasContent) {
              html += '<div class="tl-item-body" id="' + itemId + '" style="display:none">';

              // Visibility controls (for editing mode)
              var vs = item.visibleState !== undefined ? item.visibleState : 0;
              html += '<div class="tl-visibility" data-roosterid="' + (item.roosterId || '') + '">';
              html += '<span class="tl-visibility-label">Zichtbaarheid:</span>';
              html += '<label class="tl-vis-option tl-vis-off' + (vs == -1 ? ' tl-vis-active' : '') + '">';
              html += '<input type="radio" name="vis_' + itemId + '" value="-1"' + (vs == -1 ? ' checked' : '') + '> Niet tonen</label>';
              html += '<label class="tl-vis-option tl-vis-auto' + (vs == 0 ? ' tl-vis-active' : '') + '">';
              html += '<input type="radio" name="vis_' + itemId + '" value="0"' + (vs == 0 ? ' checked' : '') + '> Automatisch</label>';
              html += '<label class="tl-vis-option tl-vis-on' + (vs == 1 ? ' tl-vis-active' : '') + '">';
              html += '<input type="radio" name="vis_' + itemId + '" value="1"' + (vs == 1 ? ' checked' : '') + '> Alvast tonen</label>';
              html += '</div>';

              // Edit bar with save button
              html += '<div class="tl-edit-bar" data-roosterid="' + (item.roosterId || '') + '">';
              html += '<button class="tl-edit-btn" data-roosterid="' + (item.roosterId || '') + '">Wijzig</button>';
              html += '<button class="tl-save-btn" data-roosterid="' + (item.roosterId || '') + '" style="display:none">Opslaan</button>';
              html += '<button class="tl-cancel-btn" data-roosterid="' + (item.roosterId || '') + '" style="display:none">Annuleren</button>';
              html += '</div>';

              // Content (view mode) and editor container
              if (item.content) {
                html += '<div class="tl-content" data-roosterid="' + (item.roosterId || '') + '">' + item.content + '</div>';
              }
              html += '<div class="tl-editor-container" data-roosterid="' + (item.roosterId || '') + '" style="display:none"></div>';

              // Downloads
              html += '<div class="tl-downloads" data-roosterid="' + (item.roosterId || '') + '">';
              if (item.downloads && item.downloads.length) {
                html += '<span class="tl-downloads-label">Materiaal:</span>';
                item.downloads.forEach(function (dl) {
                  html += '<div class="tl-download-row">';
                  html += '<a class="tl-download" href="' + self._escAttr(dl.url || '#') + '" target="_blank">';
                  html += self._esc(dl.title || dl.url || 'Download');
                  html += '</a>';
                  html += '<button class="tl-dl-delete" data-dlid="' + (dl.id || '') + '" title="Verwijder">&times;</button>';
                  html += '</div>';
                });
              }
              html += '<button class="tl-dl-add" data-roosterid="' + (item.roosterId || '') + '">+ Plaats download</button>';
              html += '</div>';

              html += '</div>';
            }

            html += '</div>'; // tl-item
          });

          html += '</div>'; // day children
          html += '</div>'; // tl-day
        });

        html += '</div>'; // month children
        html += '</div>'; // tl-month
      });

      html += '</div>'; // year children
      html += '</div>'; // tl-year
    });

    if (!html) {
      html = '<div class="tl-empty">Geen items om weer te geven.</div>';
    }

    // Store blok popup data for lib-dialog
    this._blokPopups = {};
    blokPopups.forEach(function (bp) {
      self._blokPopups[bp.id] = bp;
    });

    this.innerHTML = '<div class="tl-root">' + html + '</div>';
    this._bindEvents();
  }

  _esc(str) {
    var el = document.createElement('span');
    el.textContent = str;
    return el.innerHTML;
  }

  _escAttr(str) {
    return str.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  /* ------------------------------------------------------------------ */
  /*  Events                                                            */
  /* ------------------------------------------------------------------ */

  _bindEvents() {
    var self = this;

    // Year / Month / Day toggles
    this.querySelectorAll('.tl-toggle').forEach(function (toggle) {
      toggle.addEventListener('click', function (e) {
        // Don't toggle if clicking action buttons
        if (e.target.closest('.tl-group-actions')) return;
        var expanded = this.getAttribute('aria-expanded') === 'true';
        var children = this.nextElementSibling;
        var level = this.getAttribute('data-level');
        if (expanded) {
          children.style.display = 'none';
          this.setAttribute('aria-expanded', 'false');
        } else {
          children.style.display = 'block';
          this.setAttribute('aria-expanded', 'true');
          // When expanding a month, also expand all days within
          if (level === 'month') {
            children.querySelectorAll('.tl-day > .tl-toggle').forEach(function (dt) {
              dt.setAttribute('aria-expanded', 'true');
              var dc = dt.nextElementSibling;
              if (dc) dc.style.display = 'block';
            });
          }
        }
      });
    });

    // Group expand/collapse action buttons (year & month)
    var expandWithin = function (container, expand) {
      container.querySelectorAll('.tl-toggle').forEach(function (t) {
        t.setAttribute('aria-expanded', expand ? 'true' : 'false');
        var ch = t.nextElementSibling;
        if (ch && ch.classList.contains('tl-children')) {
          ch.style.display = expand ? 'block' : 'none';
        }
      });
      container.querySelectorAll('.tl-item-header--expandable').forEach(function (h) {
        var body = document.getElementById(h.getAttribute('data-target'));
        if (body) {
          body.style.display = expand ? 'block' : 'none';
          if (expand) h.classList.add('tl-item-header--open');
          else h.classList.remove('tl-item-header--open');
        }
      });
    };

    this.querySelectorAll('.tl-action-expand').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.stopPropagation();
        var scope = this.getAttribute('data-scope');
        var group = this.closest('.tl-' + scope);
        if (!group) return;
        // First make sure this group itself is expanded
        var toggle = group.querySelector(':scope > .tl-toggle');
        if (toggle) {
          toggle.setAttribute('aria-expanded', 'true');
          var ch = toggle.nextElementSibling;
          if (ch) ch.style.display = 'block';
        }
        expandWithin(group, true);
      });
    });

    this.querySelectorAll('.tl-action-collapse').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.stopPropagation();
        var scope = this.getAttribute('data-scope');
        var group = this.closest('.tl-' + scope);
        if (!group) return;
        // Collapse everything inside, but keep this level open
        var children = group.querySelector(':scope > .tl-toggle + .tl-children');
        if (children) expandWithin(children, false);
      });
    });

    // Item expand/collapse (content area)
    this.querySelectorAll('.tl-item-header--expandable').forEach(function (header) {
      header.addEventListener('click', function (e) {
        if (e.target.tagName === 'A') return;
        var targetId = this.getAttribute('data-target');
        var body = document.getElementById(targetId);
        var hint = this.querySelector('.tl-expand-hint');
        if (!body) return;
        if (body.style.display === 'none') {
          body.style.display = 'block';
          if (hint) hint.innerHTML = '&#9660; details';
          this.classList.add('tl-item-header--open');
        } else {
          body.style.display = 'none';
          if (hint) hint.innerHTML = '&#9654; details';
          this.classList.remove('tl-item-header--open');
        }
      });
    });

    // Item click event dispatch
    this.querySelectorAll('.tl-item').forEach(function (itemEl) {
      itemEl.addEventListener('click', function (e) {
        if (e.target.tagName === 'A') return;
        var idx = parseInt(itemEl.getAttribute('data-idx'), 10);
        self.dispatchEvent(new CustomEvent('tl-item-click', {
          detail: { index: idx, element: itemEl },
          bubbles: true
        }));
      });
    });

    // Blok info - open via lib-dialog or fallback popup
    this.querySelectorAll('.tl-blok-info').forEach(function (info) {
      info.addEventListener('click', function (e) {
        e.stopPropagation();
        var blokId = this.getAttribute('data-blok');
        var bp = self._blokPopups[blokId];
        if (!bp) return;
        if (typeof LibDialog !== 'undefined' && LibDialog.alert) {
          LibDialog.alert(bp.tekst, { title: bp.naam, size: 'large' });
        } else {
          // Fallback: simple popup
          var w = window.open('', '_blank', 'width=640,height=480,scrollbars=yes');
          w.document.write('<html><head><title>' + bp.naam + '</title></head><body style="font-family:sans-serif;padding:20px">' +
            '<h2>' + bp.naam + '</h2>' + bp.tekst + '</body></html>');
        }
      });
    });

    // Visibility radio toggle
    this.querySelectorAll('.tl-vis-option').forEach(function (label) {
      label.addEventListener('click', function () {
        var group = this.closest('.tl-visibility');
        group.querySelectorAll('.tl-vis-option').forEach(function (l) { l.classList.remove('tl-vis-active'); });
        this.classList.add('tl-vis-active');
        this.querySelector('input').checked = true;
        var roosterId = group.getAttribute('data-roosterid');
        var value = this.querySelector('input').value;
        self.dispatchEvent(new CustomEvent('tl-visibility-change', {
          detail: { roosterId: roosterId, visibleState: parseInt(value) },
          bubbles: true
        }));
      });
    });

    // Edit button: toggle CKEditor via cma-htmledit
    this.querySelectorAll('.tl-edit-btn').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.stopPropagation();
        var roosterId = this.getAttribute('data-roosterid');
        var bar = this.closest('.tl-edit-bar');
        var body = bar.closest('.tl-item-body');
        var contentDiv = body.querySelector('.tl-content[data-roosterid="' + roosterId + '"]');
        var editorContainer = body.querySelector('.tl-editor-container[data-roosterid="' + roosterId + '"]');
        var saveBtn = bar.querySelector('.tl-save-btn');
        var cancelBtn = bar.querySelector('.tl-cancel-btn');

        // Switch to edit mode
        if (contentDiv) contentDiv.style.display = 'none';
        editorContainer.style.display = 'block';
        this.style.display = 'none';
        saveBtn.style.display = '';
        cancelBtn.style.display = '';

        // Create cma-htmledit if not already present
        if (!editorContainer.querySelector('cma-htmledit')) {
          var htmlContent = contentDiv ? contentDiv.innerHTML : '';
          var editorEl = document.createElement('cma-htmledit');
          editorEl.setAttribute('name', 'draaiboek_' + roosterId);
          editorEl.setAttribute('height', '350');
          editorEl.setAttribute('mode', 'full');
          editorEl.setAttribute('allow-br', '');
          editorEl.setAttribute('value', htmlContent);
          editorContainer.appendChild(editorEl);
        }
      });
    });

    // Save button: get editor content, fire event, switch back to view
    this.querySelectorAll('.tl-save-btn').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.stopPropagation();
        var roosterId = this.getAttribute('data-roosterid');
        var bar = this.closest('.tl-edit-bar');
        var body = bar.closest('.tl-item-body');
        var contentDiv = body.querySelector('.tl-content[data-roosterid="' + roosterId + '"]');
        var editorContainer = body.querySelector('.tl-editor-container[data-roosterid="' + roosterId + '"]');
        var editorEl = editorContainer.querySelector('cma-htmledit');
        var editBtn = bar.querySelector('.tl-edit-btn');

        // Get content from editor
        var newContent = editorEl ? editorEl.value : '';

        // Update view
        if (contentDiv) {
          contentDiv.innerHTML = newContent;
          contentDiv.style.display = '';
        }

        // Destroy editor
        if (editorEl) {
          if (editorEl.editor) { try { editorEl.editor.destroy(true); } catch(ex) {} }
          editorEl.remove();
        }
        editorContainer.style.display = 'none';

        // Switch buttons back
        editBtn.style.display = '';
        this.style.display = 'none';
        bar.querySelector('.tl-cancel-btn').style.display = 'none';

        // Fire save event
        self.dispatchEvent(new CustomEvent('tl-save', {
          detail: { roosterId: roosterId, field: 'draaiboektext', content: newContent },
          bubbles: true
        }));
      });
    });

    // Cancel button: discard changes, switch back to view
    this.querySelectorAll('.tl-cancel-btn').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.stopPropagation();
        var roosterId = this.getAttribute('data-roosterid');
        var bar = this.closest('.tl-edit-bar');
        var body = bar.closest('.tl-item-body');
        var contentDiv = body.querySelector('.tl-content[data-roosterid="' + roosterId + '"]');
        var editorContainer = body.querySelector('.tl-editor-container[data-roosterid="' + roosterId + '"]');
        var editorEl = editorContainer.querySelector('cma-htmledit');
        var editBtn = bar.querySelector('.tl-edit-btn');

        // Destroy editor without saving
        if (editorEl) {
          if (editorEl.editor) { try { editorEl.editor.destroy(true); } catch(ex) {} }
          editorEl.remove();
        }
        editorContainer.style.display = 'none';
        if (contentDiv) contentDiv.style.display = '';

        // Switch buttons back
        editBtn.style.display = '';
        this.style.display = 'none';
        bar.querySelector('.tl-save-btn').style.display = 'none';
      });
    });

    // Download add button
    this.querySelectorAll('.tl-dl-add').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.stopPropagation();
        var roosterId = this.getAttribute('data-roosterid');
        self.dispatchEvent(new CustomEvent('tl-download-add', {
          detail: { roosterId: roosterId },
          bubbles: true
        }));
      });
    });

    // Download delete button
    this.querySelectorAll('.tl-dl-delete').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.stopPropagation();
        var dlId = this.getAttribute('data-dlid');
        var roosterId = this.closest('.tl-downloads').getAttribute('data-roosterid');
        self.dispatchEvent(new CustomEvent('tl-download-delete', {
          detail: { downloadId: dlId, roosterId: roosterId },
          bubbles: true
        }));
      });
    });
  }

  /* ------------------------------------------------------------------ */
  /*  Public API                                                        */
  /* ------------------------------------------------------------------ */

  expandAll() {
    this.querySelectorAll('.tl-toggle').forEach(function (t) {
      t.setAttribute('aria-expanded', 'true');
      t.nextElementSibling.style.display = 'block';
    });
  }

  collapseAll() {
    this.querySelectorAll('.tl-toggle').forEach(function (t) {
      t.setAttribute('aria-expanded', 'false');
      t.nextElementSibling.style.display = 'none';
    });
  }

  goToDate(dateStr) {
    var d = new Date(dateStr);
    if (isNaN(d.getTime())) return;
    var self = this;

    var yearEl = null;
    this.querySelectorAll('.tl-year').forEach(function (el) {
      var label = el.querySelector('.tl-label--year');
      if (label && parseInt(label.textContent, 10) === d.getFullYear()) yearEl = el;
    });
    if (!yearEl) return;

    var expand = function (toggleEl) {
      if (toggleEl.getAttribute('aria-expanded') !== 'true') {
        toggleEl.click();
      }
    };

    expand(yearEl.querySelector('.tl-toggle'));

    var monthNames = [];
    for (var i = 0; i < 12; i++) {
      monthNames.push(new Date(2000, i, 1).toLocaleString(self.locale, { month: 'long' }));
    }
    var targetMonth = monthNames[d.getMonth()];
    var monthEl = null;
    yearEl.querySelectorAll('.tl-month').forEach(function (el) {
      var label = el.querySelector('.tl-label--month');
      if (label && label.textContent === targetMonth) monthEl = el;
    });
    if (!monthEl) return;
    expand(monthEl.querySelector('.tl-toggle'));

    var dayEl = null;
    monthEl.querySelectorAll('.tl-day').forEach(function (el) {
      var num = el.querySelector('.tl-day-num');
      if (num && parseInt(num.textContent, 10) === d.getDate()) dayEl = el;
    });
    if (!dayEl) return;
    expand(dayEl.querySelector('.tl-toggle'));
    dayEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }
}

customElements.define('vertical-timeline', VerticalTimeline);
