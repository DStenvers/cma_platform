/**
 * cma-list-thumb — hover-enlarge preview for image thumbnails in list/table views.
 *
 * The server renders image columns as <img class="cma-list-thumb" data-full="...">
 * inside .listtable cells (see JsonFormService::renderImageCell). Hovering a
 * thumbnail shows a larger floating preview next to the cursor. A single shared
 * preview element is appended to <body> with position:fixed, so it is never clipped
 * by the list's overflow:auto scroll container and works for every consumer (form
 * lists, tools, any .listtable). Hover only — no lightbox / click overlay.
 */
(function () {
    if (window.__cmaListThumbInit) return;
    window.__cmaListThumbInit = true;

    let preview = null;

    function getPreview() {
        if (!preview) {
            preview = document.createElement('img');
            preview.className = 'cma-list-thumb-preview';
            preview.alt = '';
            document.body.appendChild(preview);
        }
        return preview;
    }

    function position(e) {
        if (!preview) return;
        const pad = 16;
        const w = preview.offsetWidth || 240;
        const h = preview.offsetHeight || 240;
        let x = e.clientX + pad;
        let y = e.clientY + pad;
        // Flip / clamp to keep the preview inside the viewport
        if (x + w > window.innerWidth - pad) x = e.clientX - w - pad;
        if (y + h > window.innerHeight - pad) y = window.innerHeight - h - pad;
        if (x < pad) x = pad;
        if (y < pad) y = pad;
        preview.style.left = x + 'px';
        preview.style.top = y + 'px';
    }

    document.addEventListener('mouseover', function (e) {
        const thumb = e.target.closest && e.target.closest('img.cma-list-thumb');
        if (!thumb) return;
        const src = thumb.getAttribute('data-full') || thumb.getAttribute('src');
        if (!src) return;
        const p = getPreview();
        p.src = src;
        p.classList.add('visible');
        position(e);
    });

    document.addEventListener('mousemove', function (e) {
        if (preview && preview.classList.contains('visible')) position(e);
    });

    document.addEventListener('mouseout', function (e) {
        const thumb = e.target.closest && e.target.closest('img.cma-list-thumb');
        if (!thumb) return;
        if (e.relatedTarget && thumb.contains(e.relatedTarget)) return;
        if (preview) preview.classList.remove('visible');
    });
})();
