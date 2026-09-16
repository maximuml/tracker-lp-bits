// Legacy BBCode editor behaviour, previously emitted per-page by
// App\Support\Form::bbcodeEditor() as an inline nonce'd script.
//
// The delegated listeners in common.js dispatch data-bbcode-action /
// data-bbcode-alterfont clicks to the globals defined here. External
// callers: upload.js -> clearContent(), the attachment iframe ->
// parent.tag_extimage().
//
// Configuration comes from the editor container's data-* attributes
// instead of JSON baked into the page. Only defined when an editor is
// present, preserving the typeof-guard contract used by common.js.
(function () {
    var container = document.querySelector('[data-bbcode-editor]');
    if (!container) { return; }

    var form = container.getAttribute('data-form');
    var text = container.getAttribute('data-text');
    var textareaId = text;
    var editTbodyId = container.getAttribute('data-edit-id');
    var previewTbodyId = container.getAttribute('data-preview-id');
    var btnEditId = container.getAttribute('data-btn-edit-id');
    var btnPreviewId = container.getAttribute('data-btn-preview-id');

    window.b_open = 0;
    window.i_open = 0;
    window.u_open = 0;
    window.color_open = 0;
    window.list_open = 0;
    window.quote_open = 0;
    window.html_open = 0;

    var myAgent = navigator.userAgent.toLowerCase();
    var myVersion = parseInt(navigator.appVersion);

    var is_ie = ((myAgent.indexOf("msie") != -1) && (myAgent.indexOf("opera") == -1));
    var is_nav = ((myAgent.indexOf('mozilla') != -1) && (myAgent.indexOf('spoofer') == -1)
        && (myAgent.indexOf('compatible') == -1) && (myAgent.indexOf('opera') == -1)
        && (myAgent.indexOf('webtv') == -1) && (myAgent.indexOf('hotjava') == -1));

    var is_win = ((myAgent.indexOf("win") != -1) || (myAgent.indexOf("16bit") != -1));
    var is_mac = (myAgent.indexOf("mac") != -1);

    // These were declared as top-level vars (globals) by the old inline
    // script; mirror them on window in case other legacy code reads them.
    window.myAgent = myAgent;
    window.myVersion = myVersion;
    window.is_ie = is_ie;
    window.is_nav = is_nav;
    window.is_win = is_win;
    window.is_mac = is_mac;
    window.bbtags = [];

    window.cstat = function () {
        var c = stacksize(window.bbtags);
        if ((c < 1) || (c == null)) { c = 0; }
        if (!window.bbtags[0]) { c = 0; }
        document.forms[form].tagcount.value = "Close last, Open " + c;
    };

    function stacksize(thearray) {
        for (var i = 0; i < thearray.length; i++) {
            if ((thearray[i] == "") || (thearray[i] == null) || (thearray == 'undefined')) { return i; }
        }
        return thearray.length;
    }

    function pushstack(thearray, newval) {
        var arraysize = stacksize(thearray);
        thearray[arraysize] = newval;
    }

    function popstack(thearray) {
        var arraysize = stacksize(thearray);
        var theval = thearray[arraysize - 1];
        delete thearray[arraysize - 1];
        return theval;
    }

    window.closeall = function () {
        if (window.bbtags[0]) {
            while (window.bbtags[0]) {
                var tagRemove = popstack(window.bbtags);
                if ((tagRemove != 'color')) {
                    doInsert("[/" + tagRemove + "]", "", false);
                    document.forms[form][tagRemove].value = ' ' + tagRemove.toUpperCase() + ' ';
                    window[tagRemove + '_open'] = 0;
                } else {
                    doInsert("[/" + tagRemove + "]", "", false);
                }
                cstat();
                return;
            }
        }
        document.forms[form].tagcount.value = "Close last, Open 0";
        window.bbtags = [];
        document.forms[form][text].focus();
    };

    window.add_code = function (NewCode) {
        document.forms[form][text].value += NewCode;
        document.forms[form][text].focus();
    };

    window.alterfont = function (theval, thetag) {
        if (theval == 0) return;
        if (doInsert("[" + thetag + "=" + theval + "]", "[/" + thetag + "]", true)) pushstack(window.bbtags, thetag);
        document.forms[form].color.selectedIndex = 0;
        cstat();
    };

    window.tag_url = function (PromptURL, PromptTitle, PromptError) {
        var FoundErrors = '';
        var enterURL = prompt(PromptURL, "http://");
        var enterTITLE = prompt(PromptTitle, "");
        if (!enterURL || enterURL == "") { FoundErrors += " " + PromptURL + ","; }
        if (!enterTITLE) { FoundErrors += " " + PromptTitle; }
        if (FoundErrors) { alert(PromptError + FoundErrors); return; }
        doInsert("[url=" + enterURL + "]" + enterTITLE + "[/url]", "", false);
    };

    window.tag_list = function (PromptEnterItem, PromptError) {
        var FoundErrors = '';
        var enterTITLE = prompt(PromptEnterItem, "");
        if (!enterTITLE) { FoundErrors += " " + PromptEnterItem; }
        if (FoundErrors) { alert(PromptError + FoundErrors); return; }
        doInsert("[*]" + enterTITLE + "", "", false);
    };

    window.tag_image = function (PromptImageURL, PromptError) {
        var FoundErrors = '';
        var enterURL = prompt(PromptImageURL, "http://");
        if (!enterURL || enterURL == "http://") {
            alert(PromptError + PromptImageURL);
            return;
        }
        doInsert("[img]" + enterURL + "[/img]", "", false);
    };

    window.tag_extimage = function (content) {
        doInsert(content, "", false);
    };

    window.tag_email = function (PromptEmail, PromptError) {
        var emailAddress = prompt(PromptEmail, "");
        if (!emailAddress) {
            alert(PromptError + PromptEmail);
            return;
        }
        doInsert("[email]" + emailAddress + "[/email]", "", false);
    };

    // upload.js calls this globally after an attachment upload completes.
    window.doInsert = function (ibTag, ibClsTag, isSingle) {
        var isClose = false;
        var obj_ta = document.forms[form][text];
        if ((myVersion >= 4) && is_ie && is_win) {
            if (obj_ta.isTextEdit) {
                obj_ta.focus();
                var sel = document.selection;
                var rng = sel.createRange();
                rng.colapse;
                if ((sel.type == "Text" || sel.type == "None") && rng != null) {
                    if (ibClsTag != "" && rng.text.length > 0)
                        ibTag += rng.text + ibClsTag;
                    else if (isSingle) isClose = true;
                    rng.text = ibTag;
                }
            } else {
                if (isSingle) isClose = true;
                obj_ta.value += ibTag;
            }
        } else if (obj_ta.selectionStart || obj_ta.selectionStart == '0') {
            var startPos = obj_ta.selectionStart;
            var endPos = obj_ta.selectionEnd;
            obj_ta.value = obj_ta.value.substring(0, startPos) + ibTag + obj_ta.value.substring(endPos, obj_ta.value.length);
            obj_ta.selectionEnd = startPos + ibTag.length;
            if (isSingle) isClose = true;
        } else {
            if (isSingle) isClose = true;
            obj_ta.value += ibTag;
        }
        obj_ta.focus();
        return isClose;
    };

    window.clearContent = function () {
        document.forms[form][text].value = '';
    };

    window.winop = function () {
        window.open("moresmilies.php?form=" + encodeURIComponent(form) + "&text=" + encodeURIComponent(text), "mywin", "height=500,width=500,resizable=no,scrollbars=yes");
    };

    window.simpletag = function (thetag) {
        var tagOpen = window[thetag + '_open'];
        if (tagOpen == 0) {
            if (doInsert("[" + thetag + "]", "[/" + thetag + "]", true)) {
                window[thetag + '_open'] = 1;
                document.forms[form][thetag].value += '*';
                pushstack(window.bbtags, thetag);
                cstat();
            }
        } else {
            var lastindex = 0;
            for (var i = 0; i < window.bbtags.length; i++) {
                if (window.bbtags[i] == thetag) {
                    lastindex = i;
                }
            }

            while (window.bbtags[lastindex]) {
                var tagRemove = popstack(window.bbtags);
                doInsert("[/" + tagRemove + "]", "", false);
                if ((tagRemove != 'COLOR')) {
                    document.forms[form][tagRemove].value = tagRemove.toUpperCase();
                    window[tagRemove + '_open'] = 0;
                }
            }
            cstat();
        }
    };

    window.textBBCodePreview = function () {
        var poststr = encodeURIComponent(document.getElementById(textareaId).value);
        var result = ajax.posts('preview.php', 'body=' + poststr);
        document.getElementById(editTbodyId).style.display = 'none';
        var previewEl = document.getElementById(previewTbodyId);
        previewEl.innerHTML = result;
        previewEl.style.display = '';
        document.getElementById(btnPreviewId).style.display = 'none';
        document.getElementById(btnEditId).style.display = '';
    };

    window.textBBCodeEdit = function () {
        document.getElementById(editTbodyId).style.display = '';
        document.getElementById(previewTbodyId).style.display = 'none';
        document.getElementById(btnPreviewId).style.display = '';
        document.getElementById(btnEditId).style.display = 'none';
    };
})();
