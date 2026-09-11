/**
 * Shoutbox toolbar, edit/delete, reactions and SSE live-updates.
 */

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
            alert(response && response.msg ? response.msg : 'Request failed');
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
                alert('Invalid response');
            }
        } else {
            alert('Request failed');
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
    var title = prompt('Spoiler title (optional):');
    var start = title ? '[spoiler=' + title + ']' : '[spoiler]';
    var end = '[/spoiler]';
    shoutboxInsertAt(ta, start, end);
    ta.focus();
}

function shoutboxQuote(form, field) {
    var ta = document.forms[form].elements[field];
    if (!ta) { return; }
    var author = prompt('Quote author (optional):');
    var start = author ? '[quote=' + author + ']' : '[quote]';
    var end = '[/quote]';
    shoutboxInsertAt(ta, start, end);
    ta.focus();
}

function shoutboxLink(form, field) {
    var ta = document.forms[form].elements[field];
    if (!ta) { return; }
    var url = prompt('URL:');
    if (!url) { return; }
    var text = prompt('Link text (optional):', '') || url;
    var ins = '[url=' + url + ']' + text + '[/url]';
    if (typeof ta.selectionStart !== 'undefined') {
        var ss = ta.selectionStart;
        var se = ta.selectionEnd;
        ta.value = ta.value.substring(0, ss) + ins + ta.value.substring(se);
        ta.setSelectionRange(ss + ins.length, ss + ins.length);
    } else {
        ta.value += ins;
    }
    ta.focus();
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
        '<button type="button" class="btn" onclick="shoutboxSaveEdit(' + id + ')">Save</button>' +
        '<button type="button" class="btn" onclick="shoutboxCancelEdit(' + id + ')">Cancel</button>' +
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
    if (!confirm('Delete this shout?')) { return; }
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
    } else {
        window.location.reload();
    }
}

var shoutboxEventSource = null;

function shoutboxInitSSE(type, lastId) {
    if (typeof EventSource === 'undefined' || !lastId) {
        if (typeof schedulePoll === 'function') { schedulePoll(); }
        return;
    }
    var url = 'shoutbox_sse.php?type=' + encodeURIComponent(type || 'shoutbox') + '&last_id=' + encodeURIComponent(lastId);
    try {
        shoutboxEventSource = new EventSource(url);
        shoutboxEventSource.addEventListener('refresh', function (e) {
            if (typeof shoutPoll === 'function') { shoutPoll(); }
            if (typeof startcountdown === 'function') { try { startcountdown(SHOUT_REFRESH); } catch (err) {} }
        });
        shoutboxEventSource.addEventListener('ping', function () {});
        shoutboxEventSource.onerror = function () {
            if (!shoutboxEventSource) { return; }
            if (shoutboxEventSource.readyState === EventSource.CLOSED) {
                shoutboxEventSource.close();
                shoutboxEventSource = null;
                if (typeof schedulePoll === 'function') { schedulePoll(); }
            }
        };
    } catch (err) {
        if (typeof schedulePoll === 'function') { schedulePoll(); }
    }
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
});
