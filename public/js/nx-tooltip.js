/**
 * Delegated hover tooltips for data-domtt-* markup — replaces
 * domTT/domLib/domTT_drag/fadomatic (~57 KB of 2005-era code) with a
 * single positioned div. All content arrives as DOM nodes cloned into
 * the popup — no HTML strings are ever re-parsed:
 *   data-domtt-content  bare flag; body is a <template class="nx-tt">
 *                       child or next-sibling (smiley pickers)
 *   data-domtt-src="id" clone of an on-page hidden preview container
 *   data-domtt-promo    bare flag, inline <template>, delayed + auto-hide
 */
(function () {
    var tip = null, timer = null, hideTimer = null, current = null;

    function hide() {
        if (timer) { clearTimeout(timer); timer = null; }
        if (hideTimer) { clearTimeout(hideTimer); hideTimer = null; }
        if (tip) { tip.remove(); tip = null; }
        current = null;
    }

    function place(el) {
        var r = el.getBoundingClientRect();
        var x = r.left + window.scrollX;
        var y = r.bottom + window.scrollY + 6;
        var maxX = window.scrollX + document.documentElement.clientWidth - tip.offsetWidth - 4;
        tip.style.left = Math.max(window.scrollX + 4, Math.min(x, maxX)) + 'px';
        tip.style.top = y + 'px';
    }

    function inlineContent(el) {
        var t = el.querySelector(':scope > template.nx-tt');
        if (!t) {
            var next = el.nextElementSibling;
            t = next && next.tagName === 'TEMPLATE' ? next : null;
        }
        return t ? t.content : null;
    }

    function show(el, content, maxWidth, delay, lifetime) {
        if (el === current || !content) { return; }
        hide();
        current = el;
        timer = setTimeout(function () {
            tip = document.createElement('div');
            tip.className = 'nx-tt-pop';
            tip.style.maxWidth = maxWidth + 'px';
            tip.appendChild(content.cloneNode(true));
            document.body.appendChild(tip);
            place(el);
            if (lifetime > 0) {
                hideTimer = setTimeout(hide, lifetime);
            }
        }, delay);
    }

    var SELECTOR = '[data-domtt-content],[data-domtt-src],[data-domtt-promo]';

    document.addEventListener('mouseover', function (e) {
        var el = e.target && e.target.closest ? e.target.closest(SELECTOR) : null;
        if (!el) { return; }
        if (el.hasAttribute('data-domtt-src')) {
            var src = document.getElementById(el.getAttribute('data-domtt-src'));
            if (src) { show(el, src, 400, 500, 3000); }
            return;
        }
        if (el.hasAttribute('data-domtt-promo')) {
            show(el, inlineContent(el), 300, 500, 3000);
            return;
        }
        show(el, inlineContent(el), 400, 0, 10000);
    });

    document.addEventListener('mouseout', function (e) {
        if (!current || !e.target || !e.target.closest) { return; }
        if (e.target.closest(SELECTOR) !== current) { return; }
        var into = e.relatedTarget;
        if (into && (current.contains(into) || (tip && tip.contains(into)))) { return; }
        hide();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { hide(); }
    });
})();
