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
    var cb = function (response) {
        if (response && response.ret === 0) {
            if (typeof onSuccess === 'function') { onSuccess(response); }
            else if (onSuccess === true) { shoutboxRefresh(); }
        } else {
            alert(response && response.msg ? response.msg : 'Request failed');
        }
    };

    if (typeof jQuery !== 'undefined') {
        jQuery.ajax({
            url: 'ajax.php',
            type: 'POST',
            dataType: 'json',
            data: { action: action, params: params || {} },
            success: cb,
            error: function () { alert('Request failed'); }
        });
        return;
    }

    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'ajax.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
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

function shoutboxRefresh() {
    var c = document.getElementById('shoutbox-content');
    if (!c) { return; }
    if (typeof shoutPoll === 'function') {
        shoutPoll();
    } else {
        c.innerHTML = '<div style="text-align:center">Reloading…</div>';
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
            if (shoutboxEventSource) { shoutboxEventSource.close(); shoutboxEventSource = null; }
            if (typeof schedulePoll === 'function') { schedulePoll(); }
        };
    } catch (err) {
        if (typeof schedulePoll === 'function') { schedulePoll(); }
    }
}
