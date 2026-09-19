/**
 * Theme toggle (ADR 0019): cycles auto → light → dark on the
 * .nxm-theme-toggle button. Logged-in users persist via POST
 * /web/usercp/theme; anonymous users persist via localStorage only.
 */
(function () {
    var ORDER = ['auto', 'light', 'dark'];
    var LABELS = {auto: 'Auto', light: 'Light', dark: 'Dark'};
    var KEY = 'nxm-theme';
    var root = document.documentElement;

    function currentTheme() {
        var t = root.getAttribute('data-theme') || 'auto';
        return ORDER.indexOf(t) >= 0 ? t : 'auto';
    }

    function applyTheme(theme) {
        root.setAttribute('data-theme', theme);
    }

    function storedTheme() {
        try {
            return localStorage.getItem(KEY);
        } catch (e) {
            return null;
        }
    }

    function storeTheme(theme) {
        try {
            localStorage.setItem(KEY, theme);
        } catch (e) {
            /* storage unavailable — ignore */
        }
    }

    var buttons = document.querySelectorAll('.nxm-theme-toggle');
    var persistUrl = '';
    for (var i = 0; i < buttons.length; i++) {
        var u = buttons[i].getAttribute('data-persist-url');
        if (u) {
            persistUrl = u;
            break;
        }
    }

    // Anonymous pages render data-theme="auto" server-side; honour a
    // previously stored choice there. Logged-in pages already render the
    // user's saved theme — server value wins.
    if (!persistUrl) {
        var saved = storedTheme();
        if (saved && ORDER.indexOf(saved) >= 0) {
            applyTheme(saved);
        }
    }

    function paint(button) {
        var label = 'Theme: ' + LABELS[currentTheme()];
        if (button.hasAttribute('data-persist-url') || !persistUrl) {
            button.textContent = '[' + label + ']';
        } else {
            button.textContent = '[Theme]';
        }
        button.setAttribute('title', label);
    }

    function paintAll() {
        for (var i = 0; i < buttons.length; i++) {
            paint(buttons[i]);
        }
    }

    for (var j = 0; j < buttons.length; j++) {
        buttons[j].addEventListener('click', function () {
            var next = ORDER[(ORDER.indexOf(currentTheme()) + 1) % ORDER.length];
            applyTheme(next);
            storeTheme(next);
            paintAll();
            if (persistUrl) {
                var meta = document.querySelector('meta[name="csrf-token"]');
                fetch(persistUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-TOKEN': meta ? meta.content : '',
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    body: 'theme=' + encodeURIComponent(next)
                });
            }
        });
    }

    paintAll();
})();
