// Persist collapse state of the index stats <details> block.
(function () {
    var STATS_KEY = 'nx-stats-open';

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
    });
})();
