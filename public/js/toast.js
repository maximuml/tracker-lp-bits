(function () {
    'use strict';

    var LS_PM = 'toast_last_pm_id';
    var LS_SHOUT = 'toast_last_shout_id';
    var INTERVAL = 30000;
    var CONTAINER_ID = 'nexus-toast-container';
    var lang = window.TOAST_LANG || {};

    function t(key, fallback) {
        return lang[key] || fallback;
    }

    function init() {
        var container = document.getElementById(CONTAINER_ID);
        if (!container) {
            container = document.createElement('div');
            container.id = CONTAINER_ID;
            document.body.appendChild(container);
        }

        if (localStorage.getItem(LS_PM) === null) {
            fetchNotifications(true);
        } else {
            fetchNotifications(false);
        }

        setInterval(function () {
            fetchNotifications(false);
        }, INTERVAL);
    }

    function fetchNotifications(init) {
        var lastPmId = parseInt(localStorage.getItem(LS_PM) || '0', 10);
        var lastShoutId = parseInt(localStorage.getItem(LS_SHOUT) || '0', 10);
        var params = { last_pm_id: lastPmId, last_shout_id: lastShoutId };
        if (init) {
            params.init = 1;
        }

        jQuery.ajax({
            url: 'ajax.php',
            type: 'POST',
            data: { action: 'getToastNotifications', params: params },
            dataType: 'json',
            success: function (response) {
                if (!response || response.ret !== 0 || !response.data) {
                    return;
                }
                var data = response.data;
                if (data.cursors) {
                    localStorage.setItem(LS_PM, data.cursors.last_pm_id);
                    localStorage.setItem(LS_SHOUT, data.cursors.last_shout_id);
                }
                if (init) {
                    return;
                }
                var notifications = data.notifications || [];
                notifications.forEach(function (n) {
                    showToast(n);
                });
            }
        });
    }

    function showToast(n) {
        var container = document.getElementById(CONTAINER_ID);
        if (!container) {
            return;
        }

        var typeClass = 'nexus-toast-' + (n.type ? n.type.replace(/_/g, '-') : 'info');
        var el = document.createElement('div');
        el.className = 'nexus-toast ' + typeClass;

        var title = document.createElement('div');
        title.className = 'nexus-toast-title';
        title.textContent = n.title || '';

        var body = document.createElement('div');
        body.className = 'nexus-toast-body';
        body.textContent = (n.from ? t('from', 'From') + ' ' + n.from + ': ' : '') + (n.body || '');

        var close = document.createElement('button');
        close.className = 'nexus-toast-close';
        close.setAttribute('aria-label', t('close', 'Close'));
        close.innerHTML = '&times;';
        close.onclick = function (e) {
            e.stopPropagation();
            removeToast(el);
        };

        el.appendChild(close);
        el.appendChild(title);
        el.appendChild(body);

        if (n.url) {
            el.style.cursor = 'pointer';
            el.addEventListener('click', function (e) {
                if (e.target === close) {
                    return;
                }
                window.location.href = n.url;
            });
        }

        container.appendChild(el);

        setTimeout(function () {
            removeToast(el);
        }, 6000);
    }

    function removeToast(el) {
        if (!el || !el.parentNode) {
            return;
        }
        el.classList.add('nexus-toast-hide');
        setTimeout(function () {
            if (el.parentNode) {
                el.parentNode.removeChild(el);
            }
        }, 300);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
