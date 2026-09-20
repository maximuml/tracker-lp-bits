/**
 * CSP-safe image lightbox — replacement for vendored medium-zoom.
 *
 * medium-zoom cannot run under the nonce-strict `style-src` policy: it
 * injects a <style> block at load and writes `el.style.*`/`cssText` at
 * runtime, all of which are blocked. This implementation uses class
 * toggles for static styling and the Web Animations API for the zoom
 * transform — neither is governed by style-src.
 *
 * Usage: any <img data-zoomable> (optionally with data-zoom-src for a
 * full-size variant) zooms on click; overlay click, image click, Escape
 * or scroll closes it.
 */
(function () {
    'use strict';

    var MARGIN = 24;
    var DURATION = 280;
    var EASING = 'cubic-bezier(.2,0,.2,1)';

    var overlay = null;
    var activeImg = null;
    var clone = null;
    var animation = null;

    function isOpen() {
        return overlay !== null;
    }

    function open(img) {
        if (isOpen()) {
            close();
            return;
        }
        var rect = img.getBoundingClientRect();
        if (rect.width === 0 || rect.height === 0) {
            return;
        }

        var vw = window.innerWidth;
        var vh = window.innerHeight;
        var scale = Math.min((vw - MARGIN * 2) / rect.width, (vh - MARGIN * 2) / rect.height);
        var targetLeft = (vw - rect.width * scale) / 2;
        var targetTop = (vh - rect.height * scale) / 2;

        overlay = document.createElement('div');
        overlay.className = 'nxz-overlay';
        document.body.appendChild(overlay);
        overlay.addEventListener('click', close);

        activeImg = img;
        var zoomSrc = img.getAttribute('data-zoom-src');
        var fromTransform;
        var toTransform;
        var mover = img;
        var zoomUrl = null;
        if (zoomSrc) {
            try {
                var parsed = new URL(zoomSrc, window.location.href);
                if (parsed.protocol === 'https:' || parsed.protocol === 'http:') {
                    zoomUrl = parsed.href;
                }
            } catch (e) {
                zoomUrl = null;
            }
        }
        if (zoomUrl !== null && zoomUrl !== img.src) {
            clone = img.cloneNode(false);
            clone.removeAttribute('id');
            clone.removeAttribute('data-zoomable');
            clone.className = 'nxz-clone';
            clone.src = zoomUrl;
            clone.width = Math.round(rect.width);
            clone.height = Math.round(rect.height);
            img.classList.add('nxz-source-hidden');
            document.body.appendChild(clone);
            clone.addEventListener('click', close);
            // The clone is fixed at (0,0) — animate it from the thumb's
            // rect to the centered target rect.
            mover = clone;
            fromTransform = 'translate(' + rect.left + 'px, ' + rect.top + 'px) scale(1)';
            toTransform = 'translate(' + targetLeft + 'px, ' + targetTop + 'px) scale(' + scale + ')';
        } else {
            var dx = targetLeft - rect.left;
            var dy = targetTop - rect.top;
            fromTransform = 'translate(0px, 0px) scale(1)';
            toTransform = 'translate(' + dx + 'px, ' + dy + 'px) scale(' + scale + ')';
        }
        img.classList.add('nxz-zoomed');

        animation = mover.animate(
            [{ transform: fromTransform }, { transform: toTransform }],
            { duration: DURATION, easing: EASING, fill: 'forwards' },
        );

        requestAnimationFrame(function () {
            overlay.classList.add('nxz-overlay--open');
        });
        document.addEventListener('keydown', onKeydown, true);
        window.addEventListener('scroll', close, { once: true, capture: true });
    }

    function close() {
        if (!isOpen()) {
            return;
        }
        document.removeEventListener('keydown', onKeydown, true);
        var done = function () {
            if (overlay !== null) {
                overlay.remove();
                overlay = null;
            }
            if (activeImg !== null) {
                activeImg.classList.remove('nxz-zoomed', 'nxz-source-hidden');
                activeImg = null;
            }
            if (clone !== null) {
                clone.remove();
                clone = null;
            }
            animation = null;
        };
        overlay.classList.remove('nxz-overlay--open');
        if (animation !== null) {
            animation.reverse();
            animation.onfinish = done;
            return;
        }
        setTimeout(done, DURATION);
    }

    function onKeydown(e) {
        if (e.key === 'Escape') {
            e.preventDefault();
            close();
        }
    }

    document.addEventListener('click', function (e) {
        var img = e.target.closest('img[data-zoomable]');
        if (img === null) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        if (img === activeImg) {
            close();
        } else {
            open(img);
        }
    }, true);
})();
