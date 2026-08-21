/**
 * CSRF auto-protection for legacy forms and AJAX.
 *
 * Reads the token from <meta name="csrf-token"> and:
 * 1. Injects a hidden _token field into every <form method="POST"> on the page.
 * 2. Sets up $.ajaxSetup so all jQuery AJAX requests send X-CSRF-TOKEN header.
 * 3. Patches window.fetch to include the header in same-origin POST/PUT/DELETE/PATCH requests.
 */
(function () {
    'use strict';

    var meta = document.querySelector('meta[name="csrf-token"]');
    if (!meta) {
        return;
    }
    var token = meta.getAttribute('content');
    if (!token) {
        return;
    }

    // 1. Inject _token into all POST forms on DOMContentLoaded
    function injectTokens() {
        var forms = document.querySelectorAll('form');
        for (var i = 0; i < forms.length; i++) {
            var form = forms[i];
            var method = (form.getAttribute('method') || 'GET').toUpperCase();
            if (method !== 'POST' && method !== 'PUT' && method !== 'PATCH' && method !== 'DELETE') {
                continue;
            }
            // Skip if already has a _token field
            if (form.querySelector('input[name="_token"]')) {
                continue;
            }
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = '_token';
            input.value = token;
            form.appendChild(input);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', injectTokens);
    } else {
        injectTokens();
    }

    // 2. jQuery ajaxSetup — add X-CSRF-TOKEN header to all jQuery AJAX requests
    if (typeof jQuery !== 'undefined') {
        jQuery.ajaxSetup({
            headers: { 'X-CSRF-TOKEN': token }
        });
    }

    // 3. Patch fetch — add X-CSRF-TOKEN header to same-origin mutating requests
    var originalFetch = window.fetch;
    if (originalFetch && typeof originalFetch === 'function') {
        window.fetch = function (input, init) {
            init = init || {};
            var method = (init.method || 'GET').toUpperCase();
            if (method === 'POST' || method === 'PUT' || method === 'PATCH' || method === 'DELETE') {
                init.headers = init.headers || {};
                // Handle Headers object
                if (init.headers instanceof Headers) {
                    if (!init.headers.has('X-CSRF-TOKEN')) {
                        init.headers.set('X-CSRF-TOKEN', token);
                    }
                } else if (typeof init.headers === 'object') {
                    if (!init.headers['X-CSRF-TOKEN']) {
                        init.headers['X-CSRF-TOKEN'] = token;
                    }
                }
            }
            return originalFetch.call(this, input, init);
        };
    }
})();
