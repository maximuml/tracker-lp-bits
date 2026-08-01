(function () {
    'use strict';

    document.querySelectorAll('.t2-switcher__btn').forEach(function (btn) {
        btn.addEventListener('click', function (event) {
            var href = btn.getAttribute('href');
            if (!href) {
                return;
            }
            event.preventDefault();
            var url = new URL(href, window.location.href);
            var current = new URL(window.location.href);
            current.searchParams.set('view', url.searchParams.get('view') || 'card');
            window.location.href = current.toString();
        });
    });
})();
