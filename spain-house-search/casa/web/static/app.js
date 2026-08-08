/* Casa en España — frontend behaviour (vanilla JS, no build step). */
(function () {
  "use strict";

  // Inline SVG fallback so the layout survives when a remote photo can't load
  // (e.g. offline demo data). Referenced from onerror handlers in the HTML.
  window.casaPlaceholder = function () {
    var ns = "http://www.w3.org/2000/svg";
    var svg = document.createElementNS(ns, "svg");
    svg.setAttribute("viewBox", "0 0 400 300");
    svg.setAttribute("width", "100%");
    svg.setAttribute("height", "100%");
    svg.setAttribute("preserveAspectRatio", "xMidYMid slice");
    svg.innerHTML =
      '<rect width="400" height="300" fill="#dfe6ec"/>' +
      '<text x="200" y="150" font-size="64" text-anchor="middle" ' +
      'dominant-baseline="central" fill="#9fb0bd">🏠</text>';
    svg.style.display = "block";
    return svg;
  };

  function euro(n) {
    if (n === null || n === undefined) return "—";
    return "€ " + String(n).replace(/\B(?=(\d{3})+(?!\d))/g, ".");
  }

  // --- Favorite toggle on cards (no page reload) ---------------------------
  document.addEventListener("click", function (ev) {
    var btn = ev.target.closest(".casa-card__fav");
    if (!btn) return;
    ev.preventDefault();
    var id = btn.getAttribute("data-id");
    var on = btn.classList.contains("casa-card__fav--on");
    var url = on ? "/unfavorite" : "/favorite";
    var body = new URLSearchParams();
    body.set("listing_id", id);
    body.set("next", "/__noop");
    fetch(url, { method: "POST", body: body, redirect: "manual" })
      .then(function () {
        btn.classList.toggle("casa-card__fav--on");
        btn.textContent = on ? "☆" : "★";
      })
      .catch(function () {});
  });

  // --- Photo gallery -------------------------------------------------------
  var gallery = document.getElementById("casa-gallery");
  if (gallery) {
    var main = document.getElementById("casa-gallery-main");
    var thumbs = gallery.querySelectorAll(".casa-gallery__thumb");
    thumbs.forEach(function (t, i) {
      if (i === 0) t.classList.add("casa-gallery__thumb--active");
      t.addEventListener("click", function () {
        var src = t.getAttribute("data-src");
        if (main && src) main.src = src;
        thumbs.forEach(function (o) { o.classList.remove("casa-gallery__thumb--active"); });
        t.classList.add("casa-gallery__thumb--active");
      });
    });
  }

  // --- Single-listing map --------------------------------------------------
  var detailMap = document.getElementById("casa-detail-map");
  if (detailMap && window.L) {
    var lat = parseFloat(detailMap.getAttribute("data-lat"));
    var lon = parseFloat(detailMap.getAttribute("data-lon"));
    var dm = L.map(detailMap).setView([lat, lon], 13);
    baseLayer().addTo(dm);
    L.marker([lat, lon]).addTo(dm)
      .bindPopup(detailMap.getAttribute("data-title") || "");
  }

  // --- Full result map -----------------------------------------------------
  var mapEl = document.getElementById("casa-map");
  if (mapEl && window.L) {
    var map = L.map(mapEl).setView([39.5, -3.5], 6); // roughly centre of Spain
    baseLayer().addTo(map);
    fetch(mapEl.getAttribute("data-endpoint"))
      .then(function (r) { return r.json(); })
      .then(function (points) {
        var markers = [];
        points.forEach(function (p) {
          if (p.lat == null || p.lon == null) return;
          var m = L.marker([p.lat, p.lon]).addTo(map);
          m.bindPopup(popupHtml(p), { minWidth: 200 });
          markers.push(m);
        });
        if (markers.length) {
          var group = L.featureGroup(markers);
          map.fitBounds(group.getBounds().pad(0.15));
        }
      })
      .catch(function () {});
  }

  function baseLayer() {
    return L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
      maxZoom: 19,
      attribution: "&copy; OpenStreetMap"
    });
  }

  function popupHtml(p) {
    var photo = p.photo
      ? '<img src="' + p.photo + '" alt="" onerror="this.replaceWith(window.casaPlaceholder())">'
      : "";
    var elev = p.elevation != null ? " · ⛰ " + p.elevation + " m" : "";
    var sold = p.status === "sold" ? " (verkocht)" : "";
    return (
      '<a class="casa-pop" href="/listing/' + p.id + '">' +
      photo +
      '<div class="casa-pop__price">' + euro(p.price) + "</div>" +
      '<div class="casa-pop__title">' + (p.title || "") + sold + "</div>" +
      '<div class="casa-pop__meta">🛏 ' + (p.beds || "—") +
      " · 📐 " + (p.area ? p.area + " m²" : "—") + elev + "</div>" +
      "</a>"
    );
  }
})();
