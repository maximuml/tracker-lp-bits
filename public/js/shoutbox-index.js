/**
 * Index-page shoutbox helpers — DOM-only concerns the Livewire
 * component cannot own: BBCode toolbar cursor insertion (plus the
 * `input` event wire:model needs to see DOM writes), the collapsed
 * mentions badge, long-message clamp toggling, @nick reply and avatar
 * fallbacks. All bindings are delegated on document so Livewire morph
 * cannot detach them.
 *
 * The full legacy script (ajax actions + polling + SSE) is loaded only
 * by the standalone /web/shoutbox document.
 */

var SHOUT_LANG_DATA = window.SHOUT_LANG || {};

function shoutboxT(key, fallback) {
    return SHOUT_LANG_DATA[key] || fallback;
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

// Delegated bindings — document-level so Livewire morph cannot detach them.
document.addEventListener('click', function (e) {
    var link = e.target && e.target.closest ? e.target.closest('a.shout-nick-reply') : null;
    if (link) {
        shoutReply(link.getAttribute('data-nick') || '');
        e.preventDefault();
        return;
    }

    var toggle = e.target && e.target.closest ? e.target.closest('.shout-msg-toggle') : null;
    if (toggle) {
        var msg = toggle.previousSibling;
        while (msg && msg.nodeType === 3) { msg = msg.previousSibling; }
        if (msg) {
            var clamped = msg.classList.toggle('shout-msg-clamped');
            toggle.textContent = clamped ? toggle.getAttribute('data-off') : toggle.getAttribute('data-on');
        }
        e.preventDefault();
        return;
    }

    var t = e.target;
    if (!t || !t.closest) { return; }
    var tool = t.closest('button[data-shout-tool]');
    if (tool) {
        var kind = tool.getAttribute('data-shout-tool');
        var form = tool.getAttribute('data-form');
        var field = tool.getAttribute('data-field');
        if (kind === 'wrap') {
            shoutboxWrap(tool.getAttribute('data-tag'), form, field);
        } else if (kind === 'spoiler') {
            shoutboxSpoiler(form, field);
        } else if (kind === 'quote') {
            shoutboxQuote(form, field);
        } else if (kind === 'link') {
            shoutboxLink(form, field);
        } else if (kind === 'emoji') {
            shoutboxToggleEmoji(form, field);
        }
        e.preventDefault();
    }
});

document.addEventListener('error', function (e) {
    var img = e.target;
    if (img && img.tagName === 'IMG' && img.classList && img.classList.contains('shout-avatar')) {
        var fb = img.getAttribute('data-fallback');
        if (fb && img.src.indexOf(fb) === -1) {
            try {
                var u = new URL(fb, window.location.href);
                if (u.protocol === 'http:' || u.protocol === 'https:') { img.src = u.href; }
            } catch (err) { /* not a URL — ignore */ }
        }
    }
}, true);

// Collapsed mentions badge: while the Livewire card is closed, count
// shoutrow-mentions-me rows added since the collapse. Clicking the
// badge dispatches 'shoutbox-expand' back to the component.
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

    // Livewire poll morphs in new rows — scan shortly after each render
    // cycle is pointless to time exactly, so use the same poll interval
    // plus a slow safety scan. The shout BroadcastChannel carries refresh
    // pings from other tabs/the iframe — scan on those too.
    if (window.Livewire && typeof window.Livewire.hook === 'function') {
        try {
            window.Livewire.hook('morph.updated', function () {
                setTimeout(shoutCollapseScan, 0);
            });
        } catch (e) {}
    }
    if (typeof BroadcastChannel !== 'undefined') {
        try {
            new BroadcastChannel('nx-shout-shoutbox').addEventListener('message', function () {
                shoutCollapseScan();
            });
        } catch (e) {}
    }
    setInterval(shoutCollapseScan, 15000);

    shoutCollapseScan();
})();

// Livewire confirm dialogs render as inert <dialog> markup; promote them
// to modal so the native focus trap applies. Escape is kept in sync via
// wire:keydown.escape.window — suppress the native cancel default, which
// would close the dialog while Livewire still considers it open.
(function () {
    function promote(node) {
        if (node.nodeType !== 1) { return; }
        var list = [];
        if (node.matches && node.matches('dialog.nx-modal')) { list.push(node); }
        if (node.querySelectorAll) {
            list = list.concat([].slice.call(node.querySelectorAll('dialog.nx-modal')));
        }
        list.forEach(function (dlg) {
            if (!dlg.open) {
                try { dlg.showModal(); } catch (e) {}
            }
        });
    }

    new MutationObserver(function (records) {
        records.forEach(function (r) {
            [].forEach.call(r.addedNodes, promote);
        });
    }).observe(document.body, { childList: true, subtree: true });

    document.addEventListener('cancel', function (e) {
        if (e.target && e.target.matches && e.target.matches('dialog.nx-modal')) {
            e.preventDefault();
        }
    }, true);
})();
