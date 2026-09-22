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
        return parseInt(localStorage.getItem(lsKey(channel)) || '0', 10);
    }

    function setCursor(channel, id) {
        localStorage.setItem(lsKey(channel), String(id));
    }

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

        var firstFetch = fetchNotifications(localStorage.getItem(lsKey('pm')) === null);
        Promise.resolve(firstFetch).then(connectSse, connectSse);

        refreshBadge();
        initBell();
    }

    function connectSse() {
        if (typeof EventSource === 'undefined') {
            startPolling();
            return;
        }
        try {
            var url = 'shoutbox_sse.php?type=notifications'
                + '&last_pm_id=' + encodeURIComponent(getCursor('pm'))
                + '&last_shout_id=' + encodeURIComponent(getCursor('shout'))
                + '&last_comment_id=' + encodeURIComponent(getCursor('comment'))
                + '&last_reply_id=' + encodeURIComponent(getCursor('topic_reply'))
                + '&last_staff_id=' + encodeURIComponent(getCursor('staff'));
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
        notifications.forEach(function (n) {
            showToast(n);
        });
        if (notifications.length > 0) {
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
        fetch('notifications', {
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
        fetch('notifications?offset=' + encodeURIComponent(panelFetched), {
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
        fetch('notifications', {
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
        fetch('notifications', {
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
