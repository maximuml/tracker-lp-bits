/**
 * CSRF auto-protection for legacy forms and AJAX.
 *
 * Reads the token from <meta name="csrf-token"> and:
 * 1. Injects a hidden _token field into every <form method="POST"> on the page.
 * 2. Patches window.fetch to include the header in same-origin POST/PUT/DELETE/PATCH requests.
 * 3. Patches XMLHttpRequest to include the header in same-origin mutating requests.
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

    // 2. Patch fetch — add X-CSRF-TOKEN header to same-origin mutating requests
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

    // 3. Patch XMLHttpRequest — add X-CSRF-TOKEN header to mutating requests
    var OriginalXHR = window.XMLHttpRequest;
    if (OriginalXHR && OriginalXHR.prototype) {
        var originalOpen = OriginalXHR.prototype.open;
        OriginalXHR.prototype.open = function (method, url) {
            this._csrfMethod = (method || 'GET').toUpperCase();
            return originalOpen.apply(this, arguments);
        };
        var originalSend = OriginalXHR.prototype.send;
        OriginalXHR.prototype.send = function () {
            if (this._csrfMethod === 'POST' || this._csrfMethod === 'PUT' ||
                this._csrfMethod === 'PATCH' || this._csrfMethod === 'DELETE') {
                try {
                    this.setRequestHeader('X-CSRF-TOKEN', token);
                } catch (e) { /* headers already sent */ }
            }
            return originalSend.apply(this, arguments);
        };
    }
})();
