(function () {
    var KEY = 'nx-stats-open';
    document.addEventListener('DOMContentLoaded', function () {
        var d = document.querySelector('details.nx-stats__details');
        if (!d) {
            return;
        }
        try {
            if (localStorage.getItem(KEY) === '1') {
                d.open = true;
            }
        } catch (e) { /* storage unavailable */ }
        d.addEventListener('toggle', function () {
            try {
                localStorage.setItem(KEY, d.open ? '1' : '0');
            } catch (e) { /* storage unavailable */ }
        });
    });
})();
