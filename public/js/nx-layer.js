/**
 * window.layer compatibility shim — replaces layer.js v3.5.1 (24 KB +
 * its injected theme css) with native <dialog> elements. Covers the
 * subset legacy pages call:
 *   layer.alert(msg, opts?, yes?)            — Info dialog, OK button
 *   layer.confirm(msg, opts, yes)            — OK/Cancel
 *   layer.open({type:1, title, content, btn, btnAlign, yes})
 *                                            — content is a DOM Node
 *     (element or <template>.content fragment) cloned into the body
 *   layer.open({type:2, title, content:url, area:[w,h]}) — iframe
 *   layer.load(2, {shade}) / layer.closeAll('loading')   — spinner
 *   layer.close(index)
 * HTML strings are never re-parsed (CodeQL js/xss-through-dom): string
 * content renders as text — callers pass <template>.content instead.
 * A yes/callback returning false keeps the dialog open.
 */
(function () {
    var seq = 0;
    var dialogs = {};
    var loaders = [];

    function mk(tag, cls, text) {
        var el = document.createElement(tag);
        if (cls) { el.className = cls; }
        if (text !== undefined) { el.textContent = text; }
        return el;
    }

    function closeDlg(dlg) {
        if (dlg && dlg.open) { dlg.close(); }
    }

    function register(dlg) {
        var idx = ++seq;
        dlg.setAttribute('data-nx-layer-index', String(idx));
        dialogs[idx] = dlg;
        dlg.addEventListener('close', function () { delete dialogs[idx]; dlg.remove(); });
        document.body.appendChild(dlg);
        dlg.showModal();
        return idx;
    }

    function shell(title) {
        var dlg = mk('dialog', 'nx-modal');
        var box = mk('div', 'nx-modal__box');
        var header = mk('div', 'nx-modal__header');
        header.appendChild(mk('h2', 'nx-modal__title', title || 'Info'));
        var x = mk('button', 'nx-modal__close', '×');
        x.type = 'button';
        x.setAttribute('aria-label', 'Close');
        x.addEventListener('click', function () { closeDlg(dlg); });
        header.appendChild(x);
        box.appendChild(header);
        var body = mk('div', 'nx-modal__body');
        box.appendChild(body);
        var btns = mk('div', 'nx-modal__btns');
        box.appendChild(btns);
        dlg.appendChild(box);
        return { dlg: dlg, body: body, btns: btns };
    }

    function fillBody(body, content) {
        if (content && typeof content.cloneNode === 'function') {
            body.appendChild(content.cloneNode(true));
        } else if (typeof content === 'string') {
            body.textContent = content;
        }
    }

    function addBtn(btns, label, align, onClick) {
        var b = mk('button', 'nx-modal__btn', label);
        b.type = 'button';
        b.addEventListener('click', onClick);
        btns.appendChild(b);
        btns.className = 'nx-modal__btns nx-modal__btns--' + (align === 'c' ? 'center' : 'right');
    }

    function open(opts) {
        var o = opts || {};
        var s = shell(o.title);
        var idx;
        var btnList = o.btn || [];
        btnList.forEach(function (label, i) {
            addBtn(s.btns, label, o.btnAlign, function () {
                var cb = i === 0 ? o.yes : o['btn' + (i + 1)];
                var res = cb ? cb(idx, s.dlg) : undefined;
                if (res !== false) { closeDlg(s.dlg); }
            });
        });
        if (o.type === 2) {
            var fr = mk('iframe', 'nx-modal__frame');
            fr.src = String(o.content || '');
            if (o.title) { fr.title = o.title; }
            s.body.appendChild(fr);
            if (o.area) {
                s.dlg.style.width = o.area[0];
                s.dlg.style.height = o.area[1] || 'auto';
            }
        } else {
            fillBody(s.body, o.content);
        }
        idx = register(s.dlg);
        return idx;
    }

    window.layer = {
        open: open,
        alert: function (msg, opts, yes) {
            var o = opts || {};
            var idx;
            var s = shell(o.title || 'Info');
            fillBody(s.body, typeof msg === 'string' ? msg : String(msg));
            addBtn(s.btns, (o.btn && o.btn[0]) || 'OK', o.btnAlign, function () {
                var res = yes ? yes(idx) : undefined;
                if (res !== false) { closeDlg(s.dlg); }
            });
            idx = register(s.dlg);
            return idx;
        },
        confirm: function (msg, opts, yes, cancel) {
            var o = opts || {};
            var idx;
            var s = shell(o.title || 'Confirm');
            fillBody(s.body, typeof msg === 'string' ? msg : String(msg));
            var labels = o.btn || ['OK', 'Cancel'];
            addBtn(s.btns, labels[0], o.btnAlign, function () {
                var res = yes ? yes(idx) : undefined;
                if (res !== false) { closeDlg(s.dlg); }
            });
            addBtn(s.btns, labels[1] || 'Cancel', o.btnAlign, function () {
                var res = cancel ? cancel(idx) : undefined;
                if (res !== false) { closeDlg(s.dlg); }
            });
            idx = register(s.dlg);
            return idx;
        },
        load: function () {
            var dlg = mk('dialog', 'nx-modal nx-modal--loading');
            dlg.appendChild(mk('div', 'nx-modal__spinner'));
            var idx = register(dlg);
            loaders.push(dlg);
            return idx;
        },
        close: function (idx) {
            closeDlg(dialogs[idx]);
        },
        closeAll: function (type) {
            if (type === 'loading') {
                loaders.forEach(closeDlg);
                loaders = [];
                return;
            }
            Object.keys(dialogs).forEach(function (k) { closeDlg(dialogs[k]); });
        }
    };
})();
