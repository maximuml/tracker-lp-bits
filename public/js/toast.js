(function () {
    'use strict';

    var lang = window.TOAST_LANG || {};
    var USER_ID = lang.userId || 0;
    var INTERVAL = 30000;
    var CONTAINER_ID = 'nexus-toast-container';

    function lsKey(channel) {
        return 'toast_last_' + channel + '_id_' + USER_ID;
    }

    function getCursor(channel) {
        try {
            return parseInt(localStorage.getItem(lsKey(channel)) || '0', 10) || 0;
        } catch (e) {
            return 0;
        }
    }

    function setCursor(channel, id) {
        if (!isFinite(id)) { return; }
        try {
            localStorage.setItem(lsKey(channel), String(id));
        } catch (e) {}
    }

    function hasCursor(channel) {
        try {
            return localStorage.getItem(lsKey(channel)) !== null;
        } catch (e) {
            return true;
        }
    }

    function t(key, fallback) {
        return lang[key] || fallback;
    }

    var eventSource = null;
    var pollTimer = null;
    var reconnectTimer = null;
    var sseFailures = 0;
    var sessionDead = false;
    var SSE_MAX_FAILURES = 5;
    var SSE_BACKOFF_MAX = 60000;

    // ---- multi-tab leadership: one live stream per user ----
    // The leader holds the SSE/polling transport and fans payloads out
    // over BroadcastChannel; followers render from the bus and never
    // open a stream (the server also enforces one stream per user).
    var TAB_ID = 't' + Math.random().toString(36).slice(2) + Date.now().toString(36);
    var LEADER_TTL = 12000;
    var heartbeatTimer = null;
    var isLeader = false;
    var bus = null;
    var seenIds = {};
    var seenCount = 0;

    if (typeof BroadcastChannel !== 'undefined') {
        try {
            bus = new BroadcastChannel('nx-notif-' + USER_ID);
            bus.onmessage = function (e) {
                var msg = e && e.data;
                if (!msg) { return; }
                if (msg.kind === 'data') {
                    handleData(msg.payload, false);
                    refreshBadge();
                } else if (msg.kind === 'read') {
                    setBadge(0);
                    var els = bellElements();
                    if (els.panel && !els.panel.classList.contains('nx-hidden')) {
                        panelFetched = 0;
                        panelHasMore = false;
                        renderPanel(els.panel, { items: [], counts: { total: 0 } });
                    }
                }
            };
        } catch (err) {
            bus = null;
        }
    }

    function leaderKey() {
        return 'nx_notif_leader_' + USER_ID;
    }

    function readLeader() {
        try {
            return JSON.parse(localStorage.getItem(leaderKey()) || 'null');
        } catch (e) {
            return null;
        }
    }

    function claimLeadership() {
        var now = Date.now();
        var l = readLeader();
        if (!l || (now - (l.ts || 0)) > LEADER_TTL || l.id === TAB_ID) {
            try {
                localStorage.setItem(leaderKey(), JSON.stringify({ id: TAB_ID, ts: now }));
            } catch (e) {}
            l = readLeader();
        }
        return !!(l && l.id === TAB_ID);
    }

    function releaseLeadership() {
        var l = readLeader();
        if (l && l.id === TAB_ID) {
            try {
                localStorage.removeItem(leaderKey());
            } catch (e) {}
        }
    }

    function heartbeat() {
        var was = isLeader;
        if (!bus) {
            isLeader = true;
        } else if (document.hidden) {
            isLeader = false;
        } else {
            isLeader = claimLeadership();
        }
        if (isLeader && !was) {
            sseFailures = 0;
            startTransport();
        } else if (!isLeader && was) {
            stopTransport();
        }
    }

    function init() {
        var container = document.getElementById(CONTAINER_ID);
        if (!container) {
            container = document.createElement('div');
            container.id = CONTAINER_ID;
            document.body.appendChild(container);
        }

        // The init fetch seeds cursors; only the leader then opens the
        // stream. Every tab fetches once on load so a returning tab with
        // a live leader still converges.
        var firstFetch = fetchNotifications(!hasCursor('pm'));
        Promise.resolve(firstFetch).then(function () {
            heartbeat();
            heartbeatTimer = setInterval(heartbeat, 4000);
        }, function () {
            heartbeat();
            heartbeatTimer = setInterval(heartbeat, 4000);
        });

        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                releaseLeadership();
                stopTransport();
            } else {
                heartbeat();
                refreshBadge();
            }
        });
        window.addEventListener('pagehide', function () {
            releaseLeadership();
        });

        refreshBadge();
        initBell();
    }

    function startTransport() {
        if (sessionDead || document.hidden || !isLeader) {
            return;
        }
        connectSse();
    }

    function stopTransport() {
        closeSse();
        stopPolling();
        if (reconnectTimer) {
            clearTimeout(reconnectTimer);
            reconnectTimer = null;
        }
    }

    function closeSse() {
        if (eventSource) {
            try { eventSource.close(); } catch (e) {}
            eventSource = null;
        }
    }

    function scheduleReconnect() {
        if (reconnectTimer || !isLeader || sessionDead) {
            return;
        }
        var delay = Math.min(SSE_BACKOFF_MAX, 1000 * Math.pow(2, sseFailures));
        delay = Math.floor(delay * (0.5 + Math.random()));
        reconnectTimer = setTimeout(function () {
            reconnectTimer = null;
            connectSse();
        }, delay);
    }

    function connectSse() {
        if (!isLeader || sessionDead || document.hidden) {
            return;
        }
        if (typeof EventSource === 'undefined') {
            startPolling();
            return;
        }
        try {
            var url = '/web/shoutbox_sse?type=notifications'
                + '&last_pm_id=' + encodeURIComponent(getCursor('pm'))
                + '&last_shout_id=' + encodeURIComponent(getCursor('shout'))
                + '&last_comment_id=' + encodeURIComponent(getCursor('comment'))
                + '&last_reply_id=' + encodeURIComponent(getCursor('topic_reply'))
                + '&last_staff_id=' + encodeURIComponent(getCursor('staff'));
            eventSource = new EventSource(url);
            eventSource.onopen = function () {
                sseFailures = 0;
            };
            eventSource.addEventListener('notifications', function (e) {
                try {
                    handleData(JSON.parse(e.data));
                } catch (err) {}
            });
            eventSource.addEventListener('ping', function () {});
            eventSource.onerror = function () {
                // Take over reconnect control from the browser: jittered
                // backoff with a failure cap, then polling fallback.
                closeSse();
                sseFailures++;
                if (sseFailures >= SSE_MAX_FAILURES) {
                    startPolling();
                    return;
                }
                scheduleReconnect();
            };
        } catch (err) {
            sseFailures++;
            if (sseFailures >= SSE_MAX_FAILURES) {
                startPolling();
                return;
            }
            scheduleReconnect();
        }
    }

    function startPolling() {
        if (pollTimer) { return; }
        pollTimer = setInterval(function () {
            if (!isLeader || sessionDead || document.hidden) { return; }
            fetchNotifications(false);
        }, INTERVAL);
    }

    function stopPolling() {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    function fetchNotifications(init) {
        var params = {
            last_pm_id: getCursor('pm'),
            last_shout_id: getCursor('shout'),
            last_comment_id: getCursor('comment'),
            last_reply_id: getCursor('topic_reply'),
            last_staff_id: getCursor('staff')
        };
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
        return fetch('/ajax', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        }).then(function (res) {
            if (res.status === 401 || res.status === 403) {
                sessionDead = true;
                stopTransport();
                return null;
            }
            return res.json();
        }).then(function (response) {
            if (!response || response.ret !== 0 || !response.data) {
                return;
            }
            handleData(response.data, init);
        }).catch(function () {});
    }

    function alreadySeen(id) {
        if (!id) { return false; }
        if (seenIds[id]) { return true; }
        seenIds[id] = true;
        seenCount++;
        if (seenCount > 500) {
            seenIds = {};
            seenIds[id] = true;
            seenCount = 1;
        }
        return false;
    }

    function handleData(data, init) {
        if (!data) {
            return;
        }
        if (data.cursors) {
            setCursor('pm', data.cursors.pm);
            setCursor('shout', data.cursors.shout);
            setCursor('comment', data.cursors.comment);
            setCursor('topic_reply', data.cursors.topic_reply);
            setCursor('staff', data.cursors.staff);
        }
        if (init) {
            return;
        }
        var notifications = data.notifications || [];
        var fresh = 0;
        notifications.forEach(function (n) {
            if (!alreadySeen(n.id)) {
                fresh++;
                showToast(n);
            }
        });
        if (isLeader && bus) {
            try {
                bus.postMessage({ kind: 'data', payload: data });
            } catch (e) {}
        }
        if (fresh > 0) {
            refreshBadge();
        }
    }

    // ---- header bell ----

    // Snapshot returned by the last panel fetch — echoed back on
    // "mark all read" so items that arrived after the panel opened stay
    // unread instead of being silently swallowed.
    var panelWatermark = null;
    var panelFetched = 0;
    var panelHasMore = false;

    function bellElements() {
        return {
            bell: document.getElementById('nx-notif-bell'),
            badge: document.getElementById('nx-notif-badge'),
            panel: document.getElementById('nx-notif-panel')
        };
    }

    function initBell() {
        var els = bellElements();
        if (!els.bell || !els.panel) { return; }

        els.bell.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (els.panel.classList.contains('nx-hidden')) {
                els.bell.setAttribute('aria-expanded', 'true');
                openPanel(els.panel);
            } else {
                els.bell.setAttribute('aria-expanded', 'false');
                closePanel(els.panel);
            }
        });

        document.addEventListener('click', function (e) {
            if (!els.panel.classList.contains('nx-hidden')
                && !els.panel.contains(e.target)
                && !els.bell.contains(e.target)) {
                els.bell.setAttribute('aria-expanded', 'false');
                closePanel(els.panel);
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                els.bell.setAttribute('aria-expanded', 'false');
                closePanel(els.panel);
            }
        });
    }

    function openPanel(panel) {
        panel.classList.remove('nx-hidden');
        renderLoading(panel);
        fetch('/web/notifications', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        }).then(function (res) { return res.json(); }).then(function (response) {
            if (!response || response.ret !== 0 || !response.data) {
                renderError(panel);
                return;
            }
            panelWatermark = response.data.watermark || null;
            panelFetched = (response.data.items || []).length;
            panelHasMore = !!response.data.has_more;
            renderPanel(panel, response.data);
            setBadge(response.data.counts.total || 0);
        }).catch(function () {
            renderError(panel);
        });
    }

    function loadMore(panel, wrap) {
        var btn = wrap.querySelector('button');
        if (btn) { btn.disabled = true; }
        fetch('/web/notifications?offset=' + encodeURIComponent(panelFetched), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        }).then(function (res) { return res.json(); }).then(function (response) {
            if (!response || response.ret !== 0 || !response.data) {
                if (btn) { btn.disabled = false; }
                return;
            }
            var items = response.data.items || [];
            panelFetched += items.length;
            panelHasMore = !!response.data.has_more;
            var list = panel.querySelector('.nx-notif-list');
            if (list) {
                items.forEach(function (n) {
                    list.appendChild(renderItem(n));
                });
            }
            wrap.remove();
            if (panelHasMore && items.length > 0) {
                appendShowMore(panel);
            }
        }).catch(function () {
            if (btn) { btn.disabled = false; }
        });
    }

    function appendShowMore(panel) {
        var wrap = document.createElement('div');
        wrap.className = 'nx-notif-more';
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'nx-notif-mark';
        btn.textContent = t('showMore', 'Show more');
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            loadMore(panel, wrap);
        });
        wrap.appendChild(btn);
        panel.appendChild(wrap);
    }

    function closePanel(panel) {
        panel.classList.add('nx-hidden');
    }

    function renderLoading(panel) {
        panel.innerHTML = '';
        var div = document.createElement('div');
        div.className = 'nx-notif-empty';
        div.textContent = '…';
        panel.appendChild(div);
    }

    function renderError(panel) {
        panel.innerHTML = '';
        var div = document.createElement('div');
        div.className = 'nx-notif-empty';
        div.textContent = t('loadError', 'Failed to load');
        panel.appendChild(div);
    }

    function renderPanel(panel, data) {
        panel.innerHTML = '';

        var head = document.createElement('div');
        head.className = 'nx-notif-head';
        var title = document.createElement('span');
        title.className = 'nx-notif-title';
        title.textContent = t('bell', 'Notifications');
        var mark = document.createElement('button');
        mark.type = 'button';
        mark.className = 'nx-notif-mark';
        mark.textContent = t('markAllRead', 'Mark all read');
        mark.addEventListener('click', function (e) {
            e.stopPropagation();
            markAllRead(panel);
        });
        head.appendChild(title);
        head.appendChild(mark);
        panel.appendChild(head);

        var list = document.createElement('div');
        list.className = 'nx-notif-list';
        var items = data.items || [];
        if (items.length === 0) {
            var empty = document.createElement('div');
            empty.className = 'nx-notif-empty';
            empty.textContent = t('empty', 'No new notifications');
            list.appendChild(empty);
        }
        items.forEach(function (n) {
            list.appendChild(renderItem(n));
        });
        panel.appendChild(list);

        if (panelHasMore && items.length > 0) {
            appendShowMore(panel);
        }
    }

    function renderItem(n) {
        var a = document.createElement('a');
        a.className = 'nx-notif-item nx-notif-' + (n.type || 'info');
        a.href = n.url || '#';

        var title = document.createElement('div');
        title.className = 'nx-notif-item-title';
        title.textContent = n.context ? n.context : (n.title || '');
        a.appendChild(title);

        var meta = document.createElement('div');
        meta.className = 'nx-notif-item-meta';
        var bits = [];
        if (n.title) { bits.push(n.title); }
        if (n.from) { bits.push(n.from); }
        if (n.timestamp) { bits.push(relativeTime(n.timestamp)); }
        meta.textContent = bits.join(' · ');
        a.appendChild(meta);

        if (n.body) {
            var body = document.createElement('div');
            body.className = 'nx-notif-item-body';
            body.textContent = n.body;
            a.appendChild(body);
        }
        return a;
    }

    function markAllRead(panel) {
        var csrfMeta = document.querySelector('meta[name="csrf-token"]');
        fetch('/notifications', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfMeta ? csrfMeta.getAttribute('content') : '',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ watermark: panelWatermark }),
            credentials: 'same-origin'
        }).then(function (res) { return res.json(); }).then(function (response) {
            if (response && response.ret === 0) {
                var cursors = (response.data && response.data.cursors) || null;
                if (cursors) {
                    ['pm', 'shout', 'comment', 'topic_reply', 'staff'].forEach(function (ch) {
                        if (isFinite(cursors[ch])) {
                            setCursor(ch, cursors[ch]);
                        }
                    });
                }
                panelFetched = 0;
                panelHasMore = false;
                setBadge(0);
                renderPanel(panel, { items: [], counts: { total: 0 } });
                if (bus) {
                    try { bus.postMessage({ kind: 'read' }); } catch (e) {}
                }
            }
        }).catch(function () {});
    }

    function setBadge(count) {
        var els = bellElements();
        if (!els.badge) { return; }
        if (count > 0) {
            els.badge.textContent = count > 99 ? '99+' : String(count);
            els.badge.classList.remove('nx-hidden');
        } else {
            els.badge.textContent = '0';
            els.badge.classList.add('nx-hidden');
        }
    }

    function refreshBadge() {
        var els = bellElements();
        if (!els.badge) { return; }
        fetch('/web/notifications', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        }).then(function (res) { return res.json(); }).then(function (response) {
            if (response && response.ret === 0 && response.data && response.data.counts) {
                setBadge(response.data.counts.total || 0);
            }
        }).catch(function () {});
    }

    function relativeTime(ts) {
        var diff = Math.max(0, Math.floor(Date.now() / 1000) - ts);
        if (diff < 60) { return '<1m'; }
        if (diff < 3600) { return Math.floor(diff / 60) + 'm'; }
        if (diff < 86400) { return Math.floor(diff / 3600) + 'h'; }
        return Math.floor(diff / 86400) + 'd';
    }

    // ---- toasts ----

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
