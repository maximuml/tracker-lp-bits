/**
 * Native scroll-to-top button (replaces jquery-goup plugin).
 *
 * Shows a floating button when the user scrolls down, clicking it
 * smoothly scrolls back to the top of the page.
 */
(function () {
    'use strict';

    var button = document.createElement('div');
    button.id = 'goup-btn';
    button.setAttribute('role', 'button');
    button.setAttribute('aria-label', 'Scroll to top');
    button.setAttribute('tabindex', '0');
    Object.assign(button.style, {
        position: 'fixed',
        bottom: '20px',
        right: '20px',
        width: '40px',
        height: '40px',
        borderRadius: '50%',
        background: 'rgba(0, 0, 0, 0.5)',
        color: '#fff',
        cursor: 'pointer',
        display: 'none',
        zIndex: '9999',
        alignItems: 'center',
        justifyContent: 'center',
        fontSize: '20px',
        lineHeight: '40px',
        textAlign: 'center',
        transition: 'opacity 0.3s'
    });
    button.innerHTML = '&uarr;';
    document.body.appendChild(button);

    function toggleVisibility() {
        if (window.pageYOffset > 200) {
            button.style.display = 'flex';
        } else {
            button.style.display = 'none';
        }
    }

    window.addEventListener('scroll', toggleVisibility, { passive: true });
    toggleVisibility();

    button.addEventListener('click', function () {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    button.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    });
})();
