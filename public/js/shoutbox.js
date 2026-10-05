/**
 * Shoutbox toolbar, edit/delete, reactions and SSE live-updates.
 */

var SHOUT_LANG_DATA = window.SHOUT_LANG || {};

function shoutboxT(key, fallback) {
    return SHOUT_LANG_DATA[key] || fallback;
}

function shoutboxSerialize(obj, prefix) {
    var str = [];
    for (var p in obj) {
        if (!obj.hasOwnProperty(p)) { continue; }
        var k = prefix ? prefix + '[' + encodeURIComponent(p) + ']' : encodeURIComponent(p);
        var v = obj[p];
        if (v === null || v === undefined) {
            str.push(k + '=');
        } else if (typeof v === 'object') {
            str.push(shoutboxSerialize(v, k));
        } else {
            str.push(k + '=' + encodeURIComponent(v));
        }
    }
    return str.join('&');
}

function shoutboxPost(action, params, onSuccess) {
    if (typeof params === 'object' && params !== null && typeof SHOUT_CSRF !== 'undefined') {
        params.csrf = SHOUT_CSRF;
    }
    var cb = function (response) {
        if (response && response.ret === 0) {
            if (typeof onSuccess === 'function') { onSuccess(response); }
            else if (onSuccess === true) { shoutboxRefresh(); }
        } else {
            alert(response && response.msg ? response.msg : shoutboxT('requestFailed', 'Request failed'));
        }
    };

    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'ajax.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    if (csrfMeta) {
        xhr.setRequestHeader('X-CSRF-TOKEN', csrfMeta.getAttribute('content'));
    }
    xhr.onreadystatechange = function () {
        if (xhr.readyState !== 4) { return; }
        if (xhr.status >= 200 && xhr.status < 300) {
            try {
                cb(JSON.parse(xhr.responseText));
            } catch (e) {
                alert(shoutboxT('invalidResponse', 'Invalid response'));
            }
        } else {
            alert(shoutboxT('requestFailed', 'Request failed'));
        }
    };
    var data = { action: action, params: params || {} };
    xhr.send(shoutboxSerialize(data));
}

function shoutboxWrap(tag, form, field) {
    var ta = document.forms[form].elements[field];
    if (!ta) { return; }
    var start = '[' + tag + ']';
    var end = '[/' + tag + ']';
    shoutboxInsertAt(ta, start, end);
    ta.focus();
}

function shoutboxSpoiler(form, field) {
    var ta = document.forms[form].elements[field];
    if (!ta) { return; }
    var title = prompt(shoutboxT('spoilerTitle', 'Spoiler title (optional):'));
    var start = title ? '[spoiler=' + title + ']' : '[spoiler]';
    var end = '[/spoiler]';
    shoutboxInsertAt(ta, start, end);
    ta.focus();
}

function shoutboxQuote(form, field) {
    var ta = document.forms[form].elements[field];
    if (!ta) { return; }
    var author = prompt(shoutboxT('quoteAuthor', 'Quote author (optional):'));
    var start = author ? '[quote=' + author + ']' : '[quote]';
    var end = '[/quote]';
    shoutboxInsertAt(ta, start, end);
    ta.focus();
}

function shoutboxLink(form, field) {
    var ta = document.forms[form].elements[field];
    if (!ta) { return; }
    var url = prompt(shoutboxT('url', 'URL:'));
    if (!url) { return; }
    var text = prompt(shoutboxT('linkText', 'Link text (optional):'), '') || url;
    var ins = '[url=' + url + ']' + text + '[/url]';
    if (typeof ta.selectionStart !== 'undefined') {
        var ss = ta.selectionStart;
        var se = ta.selectionEnd;
        ta.value = ta.value.substring(0, ss) + ins + ta.value.substring(se);
        ta.setSelectionRange(ss + ins.length, ss + ins.length);
    } else {
        ta.value += ins;
    }
    shoutboxNotify(ta);
    ta.focus();
}

function shoutboxNotify(ta) {
    // wire:model only syncs on a real input event — DOM-level writes must re-fire it.
    ta.dispatchEvent(new Event('input', { bubbles: true }));
}

function shoutboxInsertAt(ta, before, after) {
    if (typeof ta.selectionStart !== 'undefined') {
        var ss = ta.selectionStart;
        var se = ta.selectionEnd;
        var sel = ta.value.substring(ss, se);
        var ins = before + sel + after;
        ta.value = ta.value.substring(0, ss) + ins + ta.value.substring(se);
        if (sel === '') {
            ta.setSelectionRange(ss + before.length, ss + before.length);
        } else {
            ta.setSelectionRange(ss, ss + ins.length);
        }
    } else {
        ta.value += before + after;
    }
    shoutboxNotify(ta);
}

function shoutReply(nick) {
    try {
        var input = document.forms && document.forms['shbox'] && document.forms['shbox'].shbox_text;
        if (!input) { return false; }
        var prefix = '@' + nick + ', ';
        var val = input.value || '';
        if (val.indexOf(prefix) !== 0) {
            input.value = prefix + val;
            shoutboxNotify(input);
        }
        input.focus();
        try { input.setSelectionRange(input.value.length, input.value.length); } catch (e) {}
    } catch (e) {}
    return false;
}

function shoutboxToggleEmoji(form, field) {
    var panel = document.getElementById('shoutbox-emoji-panel');
    if (!panel) { return; }
    panel.style.display = (panel.style.display === 'none' || panel.style.display === '') ? 'block' : 'none';
}

function shoutboxEdit(id) {
    var row = document.getElementById('shout-msg-' + id);
    if (!row) { return; }
    if (row.getAttribute('data-editing') === '1') { return; }
    row.setAttribute('data-editing', '1');
    row.setAttribute('data-original', row.innerHTML);

    var raw = row.getAttribute('data-raw');
    var text = (raw !== null && raw !== '') ? raw : '';
    if (text === '') {
        var tmp = document.createElement('div');
        tmp.innerHTML = row.innerHTML;
        text = tmp.textContent || tmp.innerText || '';
    }

    var html = '<span class="shoutbox-editing">' +
        '<input type="text" id="shout-edit-text-' + id + '" value="' + shoutboxEscapeHtml(text) + '" />' +
        '<button type="button" class="btn" data-shout-save="' + id + '">Save</button>' +
        '<button type="button" class="btn" data-shout-cancel="' + id + '">Cancel</button>' +
        '</span>';
    row.innerHTML = html;
    var input = document.getElementById('shout-edit-text-' + id);
    if (input) { input.focus(); input.setSelectionRange(input.value.length, input.value.length); }
}

function shoutboxEscapeHtml(text) {
    return text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function shoutboxCancelEdit(id) {
    var row = document.getElementById('shout-msg-' + id);
    if (!row) { return; }
    row.innerHTML = row.getAttribute('data-original') || '';
    row.removeAttribute('data-editing');
    row.removeAttribute('data-original');
}

function shoutboxSaveEdit(id) {
    var input = document.getElementById('shout-edit-text-' + id);
    if (!input) { return; }
    var text = input.value.replace(/^\s+|\s+$/g, '');
    if (text === '') { return; }
    shoutboxPost('shoutboxEdit', { id: id, text: text }, true);
}

function shoutboxDelete(id) {
    if (!confirm(shoutboxT('confirmDelete', 'Delete this shout?'))) { return; }
    shoutboxPost('shoutboxDelete', { id: id }, true);
}

function shoutboxReact(id, emoji) {
    shoutboxPost('shoutboxReact', { id: id, reaction: emoji }, true);
}

function shoutboxToggleReactionPicker(id) {
    var picker = document.getElementById('shout-reaction-picker-' + id);
    if (!picker) { return; }
    var isHidden = picker.style.display === 'none' || picker.style.display === '';
    if (isHidden) {
        // Close any other open picker first.
        var openPickers = document.querySelectorAll('.shout-reaction-picker');
        for (var i = 0; i < openPickers.length; i++) {
            openPickers[i].style.display = 'none';
        }
        picker.style.display = 'inline-flex';
    } else {
        picker.style.display = 'none';
    }
}

function shoutboxRefresh() {
    var c = document.getElementById('shoutbox-content');
    if (c && typeof shoutPoll === 'function') {
        shoutPoll();
        return;
    }
    if (window.Livewire && typeof window.Livewire.dispatch === 'function') {
        window.Livewire.dispatch('shout-refresh');
        return;
    }
    window.location.reload();
}

var shoutboxEventSource = null;
var shoutSseFails = 0;
var shoutReconnectTimer = null;
var shoutLastId = 0;
var shoutType = 'shoutbox';
var shoutBus = null;
var shoutIsLeader = false;
var shoutHbTimer = null;
var SHOUT_TAB_ID = 's' + Math.random().toString(36).slice(2) + Date.now().toString(36);
var SHOUT_LEADER_TTL = 12000;
var SHOUT_SSE_MAX_FAILURES = 5;

function shoutLeaderKey() {
    return 'nx_shout_leader_' + shoutType;
}

function shoutReadLeader() {
    try {
        return JSON.parse(localStorage.getItem(shoutLeaderKey()) || 'null');
    } catch (e) {
        return null;
    }
}

function shoutClaim() {
    var now = Date.now();
    var l = shoutReadLeader();
    if (!l || (now - (l.ts || 0)) > SHOUT_LEADER_TTL || l.id === SHOUT_TAB_ID) {
        try {
            localStorage.setItem(shoutLeaderKey(), JSON.stringify({ id: SHOUT_TAB_ID, ts: now }));
        } catch (e) {}
        l = shoutReadLeader();
    }
    return !!(l && l.id === SHOUT_TAB_ID);
}

function shoutRelease() {
    var l = shoutReadLeader();
    if (l && l.id === SHOUT_TAB_ID) {
        try { localStorage.removeItem(shoutLeaderKey()); } catch (e) {}
    }
}

function shoutHeartbeat() {
    var was = shoutIsLeader;
    if (!shoutBus) {
        shoutIsLeader = true;
    } else if (document.hidden) {
        shoutIsLeader = false;
    } else {
        shoutIsLeader = shoutClaim();
    }
    if (shoutIsLeader && !was) {
        shoutSseFails = 0;
        shoutConnect();
    } else if (!shoutIsLeader && was) {
        shoutClose();
        if (shoutReconnectTimer) { clearTimeout(shoutReconnectTimer); shoutReconnectTimer = null; }
    }
}

function shoutClose() {
    if (shoutboxEventSource) {
        try { shoutboxEventSource.close(); } catch (e) {}
        shoutboxEventSource = null;
    }
}

function shoutScheduleReconnect() {
    if (shoutReconnectTimer || !shoutIsLeader || document.hidden) { return; }
    var delay = Math.min(60000, 1000 * Math.pow(2, shoutSseFails));
    delay = Math.floor(delay * (0.5 + Math.random()));
    shoutReconnectTimer = setTimeout(function () {
        shoutReconnectTimer = null;
        shoutConnect();
    }, delay);
}

function shoutConnect() {
    if (!shoutIsLeader || document.hidden) { return; }
    var url = 'shoutbox_sse.php?type=' + encodeURIComponent(shoutType) + '&last_id=' + encodeURIComponent(shoutLastId);
    try {
        shoutboxEventSource = new EventSource(url);
        shoutboxEventSource.onopen = function () { shoutSseFails = 0; };
        shoutboxEventSource.addEventListener('refresh', function (e) {
            // The event id is the newest delivered shout id — keep it as
            // the resume cursor for manual reconnects.
            if (e.lastEventId && /^\d+$/.test(e.lastEventId)) {
                shoutLastId = parseInt(e.lastEventId, 10);
            }
            if (shoutBus) { try { shoutBus.postMessage('refresh'); } catch (err) {} }
            if (typeof shoutPoll === 'function') { shoutPoll(); }
            if (typeof startcountdown === 'function') { try { startcountdown(SHOUT_REFRESH); } catch (err) {} }
        });
        shoutboxEventSource.addEventListener('ping', function () {});
        shoutboxEventSource.onerror = function () {
            shoutClose();
            shoutSseFails++;
            if (shoutSseFails >= SHOUT_SSE_MAX_FAILURES) {
                if (typeof schedulePoll === 'function') { schedulePoll(); }
                return;
            }
            shoutScheduleReconnect();
        };
    } catch (err) {
        shoutSseFails++;
        if (shoutSseFails >= SHOUT_SSE_MAX_FAILURES) {
            if (typeof schedulePoll === 'function') { schedulePoll(); }
            return;
        }
        shoutScheduleReconnect();
    }
}

function shoutboxInitSSE(type, lastId) {
    shoutType = type || 'shoutbox';
    shoutLastId = lastId || 0;
    if (typeof EventSource === 'undefined' || !shoutLastId) {
        if (typeof schedulePoll === 'function') { schedulePoll(); }
        return;
    }
    if (typeof BroadcastChannel !== 'undefined') {
        try {
            shoutBus = new BroadcastChannel('nx-shout-' + shoutType);
            shoutBus.onmessage = function (e) {
                if (e && e.data === 'refresh' && typeof shoutPoll === 'function') {
                    shoutPoll();
                }
            };
        } catch (err) {
            shoutBus = null;
        }
    }
    shoutHeartbeat();
    shoutHbTimer = setInterval(shoutHeartbeat, 4000);
    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            shoutRelease();
            shoutClose();
            if (shoutReconnectTimer) { clearTimeout(shoutReconnectTimer); shoutReconnectTimer = null; }
        } else {
            shoutHeartbeat();
        }
    });
    window.addEventListener('pagehide', shoutRelease);
}

// Init (replaces CSP-blocked <body onload>) + delegated handlers for
// nick-reply links and avatar fallbacks (replacing inline on*=).
document.addEventListener('DOMContentLoaded', function () {
    if (typeof SHOUT_TYPE !== 'undefined') {
        try { if (typeof startcountdown === 'function') { startcountdown(SHOUT_REFRESH); } } catch (e) {}
        try { if (typeof shoutboxInitSSE === 'function') { shoutboxInitSSE(SHOUT_TYPE, SHOUT_LASTID); } } catch (e) {}
        try { if (typeof shoutAttachToggleHandler === 'function') { shoutAttachToggleHandler(); } } catch (e) {}
    }
});

document.addEventListener('click', function (e) {
    var link = e.target && e.target.closest ? e.target.closest('a.shout-nick-reply') : null;
    if (link) {
        if (typeof shoutReply === 'function') { shoutReply(link.getAttribute('data-nick') || ''); }
        e.preventDefault();
    }
});

document.addEventListener('error', function (e) {
    var img = e.target;
    if (img && img.tagName === 'IMG' && img.classList && img.classList.contains('shout-avatar')) {
        var fb = img.getAttribute('data-fallback');
        if (fb && img.src.indexOf(fb) === -1) { img.src = fb; }
    }
}, true);

// Toolbar + reaction delegated bindings (CSP-safe replacements for the
// inline onclick handlers previously emitted by Shoutbox::toolbar()
// and renderReactions()).
document.addEventListener('click', function (e) {
    var t = e.target;
    if (!t || !t.closest) { return; }

    var tool = t.closest('button[data-shout-tool]');
    if (tool) {
        var kind = tool.getAttribute('data-shout-tool');
        var form = tool.getAttribute('data-form');
        var field = tool.getAttribute('data-field');
        if (kind === 'wrap' && typeof shoutboxWrap === 'function') {
            shoutboxWrap(tool.getAttribute('data-tag'), form, field);
        } else if (kind === 'spoiler' && typeof shoutboxSpoiler === 'function') {
            shoutboxSpoiler(form, field);
        } else if (kind === 'quote' && typeof shoutboxQuote === 'function') {
            shoutboxQuote(form, field);
        } else if (kind === 'link' && typeof shoutboxLink === 'function') {
            shoutboxLink(form, field);
        } else if (kind === 'emoji' && typeof shoutboxToggleEmoji === 'function') {
            shoutboxToggleEmoji(form, field);
        }
        e.preventDefault();
        return;
    }

    var react = t.closest('button[data-shout-react]');
    if (react) {
        if (typeof shoutboxReact === 'function') {
            shoutboxReact(parseInt(react.getAttribute('data-shout-react'), 10), react.getAttribute('data-emoji'));
        }
        var closePicker = react.getAttribute('data-close-picker');
        if (closePicker && typeof shoutboxToggleReactionPicker === 'function') {
            shoutboxToggleReactionPicker(parseInt(closePicker, 10));
        }
        e.preventDefault();
        return;
    }

    var picker = t.closest('button[data-shout-picker]');
    if (picker && typeof shoutboxToggleReactionPicker === 'function') {
        shoutboxToggleReactionPicker(parseInt(picker.getAttribute('data-shout-picker'), 10));
        e.preventDefault();
        return;
    }
});

// Shout edit/delete action links (were onclick= attributes).
document.addEventListener('click', function (e) {
    var t = e.target;
    if (!t || !t.closest) { return; }
    var editLink = t.closest('a[data-shout-edit]');
    if (editLink) {
        if (typeof shoutboxEdit === 'function') { shoutboxEdit(parseInt(editLink.getAttribute('data-shout-edit'), 10)); }
        e.preventDefault();
        return;
    }
    var delLink = t.closest('a[data-shout-del]');
    if (delLink) {
        if (typeof shoutboxDelete === 'function') { shoutboxDelete(parseInt(delLink.getAttribute('data-shout-del'), 10)); }
        e.preventDefault();
        return;
    }

    var saveBtn = t.closest('button[data-shout-save]');
    if (saveBtn) {
        if (typeof shoutboxSaveEdit === 'function') { shoutboxSaveEdit(parseInt(saveBtn.getAttribute('data-shout-save'), 10)); }
        e.preventDefault();
        return;
    }
    var cancelBtn = t.closest('button[data-shout-cancel]');
    if (cancelBtn) {
        if (typeof shoutboxCancelEdit === 'function') { shoutboxCancelEdit(parseInt(cancelBtn.getAttribute('data-shout-cancel'), 10)); }
        e.preventDefault();
        return;
    }
});

// Index-page collapse is a server-side Livewire toggle: morph swaps the
// plus/minus icon and .nx-hidden on #kshoutbox. This block only surfaces
// new @-mentions via a badge while the panel is collapsed. Runs only in
// the parent page — the iframe document is detected via
// window.self !== window.top.
(function () {
    if (window.self !== window.top) { return; }

    var panel = document.getElementById('kshoutbox');
    var badge = document.getElementById('shoutbox-mentions');
    if (!panel || !badge) { return; }

    var mentionBaseline = -1;

    function isCollapsed() {
        return panel.classList.contains('nx-hidden');
    }

    function shoutMentionCount() {
        return panel.querySelectorAll('.shoutrow-mentions-me').length;
    }

    function shoutCollapseScan() {
        if (!isCollapsed()) {
            badge.hidden = true;
            mentionBaseline = -1;
            return;
        }
        if (mentionBaseline < 0) { mentionBaseline = shoutMentionCount(); return; }
        var delta = shoutMentionCount() - mentionBaseline;
        if (delta > 0) {
            badge.hidden = false;
            badge.textContent = shoutboxT('newMentions', '%d new mentions').replace('%d', String(delta));
        } else {
            badge.hidden = true;
        }
    }

    badge.addEventListener('click', function () {
        if (isCollapsed() && window.Livewire && typeof window.Livewire.dispatch === 'function') {
            window.Livewire.dispatch('shoutbox-expand');
        }
    });

    // Capture the mention baseline at the moment the panel collapses — a
    // scan-scheduled capture would let mentions arriving between collapse
    // and the first scan slip into the baseline unseen.
    if (typeof MutationObserver !== 'undefined') {
        var observer = new MutationObserver(function () {
            mentionBaseline = isCollapsed() ? shoutMentionCount() : -1;
            badge.hidden = true;
        });
        observer.observe(panel, { attributes: true, attributeFilter: ['class'] });
    }

    // New shouts arrive inside the iframe via shoutPoll() DOM updates — no
    // iframe 'load' event fires. The iframe broadcasts 'refresh' on this
    // channel when SSE delivers; scan shortly after (poll is async).
    if (typeof BroadcastChannel !== 'undefined') {
        try {
            var bus = new BroadcastChannel('nx-shout-shoutbox');
            bus.onmessage = function (e) {
                if (e && e.data === 'refresh') {
                    setTimeout(shoutCollapseScan, 500);
                    setTimeout(shoutCollapseScan, 2500);
                }
            };
        } catch (e) {}
    }
    setInterval(shoutCollapseScan, 15000);

    shoutCollapseScan();
})();
