(function () {
    'use strict';

    var lang = window.TOAST_LANG || {};
    var USER_ID = lang.userId || 0;
    var LS_PM = 'toast_last_pm_id_' + USER_ID;
    var LS_SHOUT = 'toast_last_shout_id_' + USER_ID;
    var INTERVAL = 30000;
    var CONTAINER_ID = 'nexus-toast-container';

    function t(key, fallback) {
        return lang[key] || fallback;
    }

    var eventSource = null;
    var pollTimer = null;

    function init() {
        var container = document.getElementById(CONTAINER_ID);
        if (!container) {
            container = document.createElement('div');
            container.id = CONTAINER_ID;
            document.body.appendChild(container);
        }

        var firstFetch = fetchNotifications(localStorage.getItem(LS_PM) === null);
        Promise.resolve(firstFetch).then(connectSse, connectSse);
    }

    function connectSse() {
        if (typeof EventSource === 'undefined') {
            startPolling();
            return;
        }
        try {
            var lastPmId = parseInt(localStorage.getItem(LS_PM) || '0', 10);
            var lastShoutId = parseInt(localStorage.getItem(LS_SHOUT) || '0', 10);
            var url = 'shoutbox_sse.php?type=notifications'
                + '&last_pm_id=' + encodeURIComponent(lastPmId)
                + '&last_shout_id=' + encodeURIComponent(lastShoutId);
            eventSource = new EventSource(url);
            eventSource.addEventListener('notifications', function (e) {
                try {
                    handleData(JSON.parse(e.data));
                } catch (err) {}
            });
            eventSource.addEventListener('ping', function () {});
            eventSource.onerror = function () {
                if (!eventSource) { return; }
                if (eventSource.readyState === EventSource.CLOSED) {
                    eventSource.close();
                    eventSource = null;
                    startPolling();
                }
            };
        } catch (err) {
            startPolling();
        }
    }

    function startPolling() {
        if (pollTimer) { return; }
        pollTimer = setInterval(function () {
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

        var formData = new FormData();
        formData.append('action', 'getToastNotifications');
        for (var key in params) {
            if (params.hasOwnProperty(key)) {
                formData.append('params[' + key + ']', params[key]);
            }
        }
        return fetch('ajax.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        }).then(function (res) { return res.json(); }).then(function (response) {
            if (!response || response.ret !== 0 || !response.data) {
                return;
            }
            handleData(response.data, init);
        }).catch(function () {});
    }

    function handleData(data, init) {
        if (!data) {
            return;
        }
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
        if (n.type === 'pm') {
            title.textContent = t('newMessage', n.title || 'New message');
        } else if (n.type === 'shoutbox-mention') {
            title.textContent = t('shoutboxMention', n.title || 'Shoutbox mention');
        } else {
            title.textContent = n.title || '';
        }

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
