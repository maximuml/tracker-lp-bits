// Persist collapse state of the index stats <details> and news klappe items.
(function () {
    var STATS_KEY = 'nx-stats-open';
    var KLAPPE_PREFIX = 'nx-klappe:';

    function klappeSave(id) {
        try {
            var el = document.getElementById('k' + id);
            if (el) {
                localStorage.setItem(KLAPPE_PREFIX + id, el.classList.contains('nx-hidden') ? '0' : '1');
            }
        } catch (e) {}
    }

    document.addEventListener('DOMContentLoaded', function () {
        var d = document.querySelector('details.nx-stats__details');
        if (d) {
            try {
                if (localStorage.getItem(STATS_KEY) === '1') { d.open = true; }
            } catch (e) {}
            d.addEventListener('toggle', function () {
                try { localStorage.setItem(STATS_KEY, d.open ? '1' : '0'); } catch (e) {}
            });
        }

        if (typeof window.klappe_news === 'function' && !window.klappe_news.__nxPersist) {
            var orig = window.klappe_news;
            window.klappe_news = function (id) {
                orig(id);
                klappeSave(id);
            };
            window.klappe_news.__nxPersist = true;
        }

        var links = document.querySelectorAll('[data-klappe]');
        for (var i = 0; i < links.length; i++) {
            var id = links[i].getAttribute('data-klappe');
            var stored;
            try {
                stored = localStorage.getItem(KLAPPE_PREFIX + id);
            } catch (e) {
                stored = null;
            }
            var el = document.getElementById('k' + id);
            if (stored === null || !el) { continue; }
            var hidden = el.classList.contains('nx-hidden');
            if ((stored === '1') === hidden && typeof window.klappe_news === 'function') {
                window.klappe_news(id);
            }
        }
    });
})();
