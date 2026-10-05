/* site.js — consolidated always-on toolkit (no build step).
   Sections in dependency order: ajax, ajaxbasic, nx-tooltip, nx-zoom,
   common, goup, theme-toggle, nx-chrome, nexus.
   nx-layer.js stays standalone (head-loaded for non-auth chrome);
   csrf.js and toast.js stay standalone too: layui admin pages load
   csrf.js alone and toast.js is appended only where TOAST_LANG is set. */

/* ===== ajax.js ===== */
/**
 * Native AJAX helper functions (replaces jQuery.ajax / jQuery.post).
 *
 * Provides a simple promise-based API for POST requests that
 * automatically includes the CSRF token.
 */
window.nativePost = function (url, data, callback) {
    var formData = new FormData();
    for (var key in data) {
        if (data.hasOwnProperty(key)) {
            if (typeof data[key] === 'object' && data[key] !== null) {
                for (var subKey in data[key]) {
                    if (data[key].hasOwnProperty(subKey)) {
                        formData.append(key + '[' + subKey + ']', data[key][subKey]);
                    }
                }
            } else {
                formData.append(key, data[key]);
            }
        }
    }
    fetch(url, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    }).then(function (res) { return res.json(); }).then(function (response) {
        if (callback) callback(response);
    }).catch(function () {
        if (callback) callback({ ret: 1, msg: 'Request failed' });
    });
};

/**
 * Serialize a form element's data into a plain object.
 * (replaces jQuery(form).serialize())
 */
window.serializeForm = function (form) {
    var data = {};
    var elements = form.querySelectorAll('input, select, textarea');
    for (var i = 0; i < elements.length; i++) {
        var el = elements[i];
        if (!el.name) continue;
        if (el.type === 'checkbox' && !el.checked) continue;
        if (el.type === 'radio' && !el.checked) continue;
        data[el.name] = el.value;
    }
    return data;
};

/* ===== ajaxbasic.js ===== */
function $(e){if(typeof e=='string')e=document.getElementById(e);return e};

ajax={};
ajax.csrfToken=function(){var m=document.querySelector('meta[name="csrf-token"]');return m?m.getAttribute('content'):''};
ajax.fetchText=function(url){return fetch(url,{credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest'}}).then(function(r){if(!r.ok)throw new Error('HTTP '+r.status);return r.text()})};
ajax.postText=function(url,body){return fetch(url,{method:'POST',credentials:'same-origin',headers:{'Content-type':'application/x-www-form-urlencoded','X-CSRF-TOKEN':ajax.csrfToken(),'X-Requested-With':'XMLHttpRequest'},body:body}).then(function(r){if(!r.ok)throw new Error('HTTP '+r.status);return r.text()})};

/* ===== nx-tooltip.js ===== */
/**
 * Delegated hover tooltips for data-domtt-* markup — replaces
 * domTT/domLib/domTT_drag/fadomatic (~57 KB of 2005-era code) with a
 * single positioned div. All content arrives as DOM nodes cloned into
 * the popup — no HTML strings are ever re-parsed:
 *   data-domtt-content  bare flag; body is a <template class="nx-tt">
 *                       child or next-sibling (smiley pickers)
 *   data-domtt-src="id" clone of an on-page hidden preview container
 *   data-domtt-promo    bare flag, inline <template>, delayed + auto-hide
 */
(function () {
    var tip = null, timer = null, hideTimer = null, current = null;

    function hide() {
        if (timer) { clearTimeout(timer); timer = null; }
        if (hideTimer) { clearTimeout(hideTimer); hideTimer = null; }
        if (tip) { tip.remove(); tip = null; }
        current = null;
    }

    function place(el) {
        var r = el.getBoundingClientRect();
        var x = r.left + window.scrollX;
        var y = r.bottom + window.scrollY + 6;
        var maxX = window.scrollX + document.documentElement.clientWidth - tip.offsetWidth - 4;
        tip.style.left = Math.max(window.scrollX + 4, Math.min(x, maxX)) + 'px';
        tip.style.top = y + 'px';
    }

    function inlineContent(el) {
        var t = el.querySelector(':scope > template.nx-tt');
        if (!t) {
            var next = el.nextElementSibling;
            t = next && next.tagName === 'TEMPLATE' ? next : null;
        }
        return t ? t.content : null;
    }

    function show(el, content, maxWidth, delay, lifetime) {
        if (el === current || !content) { return; }
        hide();
        current = el;
        timer = setTimeout(function () {
            tip = document.createElement('div');
            tip.className = 'nx-tt-pop';
            tip.style.maxWidth = maxWidth + 'px';
            tip.appendChild(content.cloneNode(true));
            document.body.appendChild(tip);
            place(el);
            if (lifetime > 0) {
                hideTimer = setTimeout(hide, lifetime);
            }
        }, delay);
    }

    var SELECTOR = '[data-domtt-content],[data-domtt-src],[data-domtt-promo]';

    document.addEventListener('mouseover', function (e) {
        var el = e.target && e.target.closest ? e.target.closest(SELECTOR) : null;
        if (!el) { return; }
        if (el.hasAttribute('data-domtt-src')) {
            var src = document.getElementById(el.getAttribute('data-domtt-src'));
            if (src) { show(el, src, 400, 500, 3000); }
            return;
        }
        if (el.hasAttribute('data-domtt-promo')) {
            show(el, inlineContent(el), 300, 500, 3000);
            return;
        }
        show(el, inlineContent(el), 400, 0, 10000);
    });

    document.addEventListener('mouseout', function (e) {
        if (!current || !e.target || !e.target.closest) { return; }
        if (e.target.closest(SELECTOR) !== current) { return; }
        var into = e.relatedTarget;
        if (into && (current.contains(into) || (tip && tip.contains(into)))) { return; }
        hide();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { hide(); }
    });
})();

/* ===== nx-zoom.js ===== */
/**
 * CSP-safe image lightbox — replacement for vendored medium-zoom.
 *
 * medium-zoom cannot run under the nonce-strict `style-src` policy: it
 * injects a <style> block at load and writes `el.style.*`/`cssText` at
 * runtime, all of which are blocked. This implementation uses class
 * toggles for static styling and the Web Animations API for the zoom
 * transform — neither is governed by style-src.
 *
 * Usage: any <img data-zoomable> (optionally with data-zoom-src for a
 * full-size variant) zooms on click; overlay click, image click, Escape
 * or scroll closes it.
 */
(function () {
    'use strict';

    var MARGIN = 24;
    var DURATION = 280;
    var EASING = 'cubic-bezier(.2,0,.2,1)';

    var overlay = null;
    var activeImg = null;
    var clone = null;
    var animation = null;

    function isOpen() {
        return overlay !== null;
    }

    function open(img) {
        if (isOpen()) {
            close();
            return;
        }
        var rect = img.getBoundingClientRect();
        if (rect.width === 0 || rect.height === 0) {
            return;
        }

        var vw = window.innerWidth;
        var vh = window.innerHeight;
        var scale = Math.min((vw - MARGIN * 2) / rect.width, (vh - MARGIN * 2) / rect.height);
        var targetLeft = (vw - rect.width * scale) / 2;
        var targetTop = (vh - rect.height * scale) / 2;

        overlay = document.createElement('div');
        overlay.className = 'nxz-overlay';
        document.body.appendChild(overlay);
        overlay.addEventListener('click', close);

        activeImg = img;
        var zoomSrc = img.getAttribute('data-zoom-src');
        var fromTransform;
        var toTransform;
        var mover = img;
        var zoomUrl = null;
        if (zoomSrc) {
            try {
                var parsed = new URL(zoomSrc, window.location.href);
                if (parsed.protocol === 'https:' || parsed.protocol === 'http:') {
                    zoomUrl = parsed.href;
                }
            } catch (e) {
                zoomUrl = null;
            }
        }
        if (zoomUrl !== null && zoomUrl !== img.src) {
            clone = img.cloneNode(false);
            clone.removeAttribute('id');
            clone.removeAttribute('data-zoomable');
            clone.className = 'nxz-clone';
            clone.src = zoomUrl;
            clone.width = Math.round(rect.width);
            clone.height = Math.round(rect.height);
            img.classList.add('nxz-source-hidden');
            document.body.appendChild(clone);
            clone.addEventListener('click', close);
            // The clone is fixed at (0,0) — animate it from the thumb's
            // rect to the centered target rect.
            mover = clone;
            fromTransform = 'translate(' + rect.left + 'px, ' + rect.top + 'px) scale(1)';
            toTransform = 'translate(' + targetLeft + 'px, ' + targetTop + 'px) scale(' + scale + ')';
        } else {
            var dx = targetLeft - rect.left;
            var dy = targetTop - rect.top;
            fromTransform = 'translate(0px, 0px) scale(1)';
            toTransform = 'translate(' + dx + 'px, ' + dy + 'px) scale(' + scale + ')';
        }
        img.classList.add('nxz-zoomed');

        animation = mover.animate(
            [{ transform: fromTransform }, { transform: toTransform }],
            { duration: DURATION, easing: EASING, fill: 'forwards' },
        );

        requestAnimationFrame(function () {
            overlay.classList.add('nxz-overlay--open');
        });
        document.addEventListener('keydown', onKeydown, true);
        window.addEventListener('scroll', close, { once: true, capture: true });
    }

    function close() {
        if (!isOpen()) {
            return;
        }
        document.removeEventListener('keydown', onKeydown, true);
        var done = function () {
            if (overlay !== null) {
                overlay.remove();
                overlay = null;
            }
            if (activeImg !== null) {
                activeImg.classList.remove('nxz-zoomed', 'nxz-source-hidden');
                activeImg = null;
            }
            if (clone !== null) {
                clone.remove();
                clone = null;
            }
            animation = null;
        };
        overlay.classList.remove('nxz-overlay--open');
        if (animation !== null) {
            animation.reverse();
            animation.onfinish = done;
            return;
        }
        setTimeout(done, DURATION);
    }

    function onKeydown(e) {
        if (e.key === 'Escape') {
            e.preventDefault();
            close();
        }
    }

    document.addEventListener('click', function (e) {
        var img = e.target.closest('img[data-zoomable]');
        if (img === null) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        if (img === activeImg) {
            close();
        } else {
            open(img);
        }
    }, true);
})();

/* ===== common.js ===== */
function postvalid(form){
	$('qr').disabled = true;
	return true;
}

function dropmenu(obj){
var list = document.getElementById(obj.id + 'list');
if (list) { list.classList.toggle('nx-hidden'); }
}

//viewfilelist.js

function viewfilelist(torrentid)
{
var filelist=document.getElementById("filelist");
if (!filelist) { return; }
filelist.innerHTML='<i>Loading...</i>';
document.getElementById("showfl").style.display = 'none';
document.getElementById("hidefl").style.display = 'block';
ajax.fetchText('viewfilelist.php?id='+torrentid).then(function(result){
showlist(result);
}).catch(function(){
filelist.innerHTML="";
document.getElementById("hidefl").style.display = 'none';
document.getElementById("showfl").style.display = 'block';
});
}

function showlist(filelist)
{
document.getElementById("filelist").innerHTML=filelist;
}

function hidefilelist()
{
document.getElementById("hidefl").style.display = 'none';
document.getElementById("showfl").style.display = 'block';
document.getElementById("filelist").innerHTML="";
}

//viewpeerlist.js

function viewpeerlist(torrentid)
{
var peerlist=document.getElementById("peerlist");
if (!peerlist) { return; }
peerlist.innerHTML='<i>Loading...</i>';
document.getElementById("showpeer").style.display = 'none';
document.getElementById("hidepeer").style.display = 'block';
document.getElementById("peercount").style.display = 'none';
ajax.fetchText('viewpeerlist.php?id='+torrentid).then(function(list){
peerlist.innerHTML=list;
}).catch(function(){
peerlist.innerHTML="";
document.getElementById("hidepeer").style.display = 'none';
document.getElementById("showpeer").style.display = 'block';
document.getElementById("peercount").style.display = 'block';
});
}
function hidepeerlist()
{
document.getElementById("hidepeer").style.display = 'none';
document.getElementById("peerlist").innerHTML="";
document.getElementById("showpeer").style.display = 'block';
document.getElementById("peercount").style.display = 'block';
}

// smileit.js

function SmileIT(smile,form,text){
   var el = document.forms[form].elements[text];
   el.value = el.value+" "+smile+" ";
   el.dispatchEvent(new Event('input', { bubbles: true }));
   el.focus();
}

// saythanks.js

function saythanks(torrentid)
{
ajax.postText('thanks.php','id='+torrentid).then(function(){
document.getElementById("thanksbutton").innerHTML = document.getElementById("thanksadded").innerHTML;
document.getElementById("nothanks").innerHTML = "";
document.getElementById("addcuruser").innerHTML = document.getElementById("curuser").innerHTML;
}).catch(function(){});
}

// preview.js

function preview(obj) {
	var poststr = encodeURIComponent( document.getElementById("body").value );
	ajax.postText('preview.php','body='+poststr).then(function(result){
	document.getElementById("previewouter").innerHTML=result;
	document.getElementById("previewouter").style.display = 'block';
	document.getElementById("editorouter").style.display = 'none';
	document.getElementById("unpreviewbutton").style.display = 'block';
	document.getElementById("previewbutton").style.display = 'none';
	});
}

function unpreview(obj){
	document.getElementById("previewouter").style.display = 'none';
	document.getElementById("editorouter").style.display = 'block';
	document.getElementById("unpreviewbutton").style.display = 'none';
	document.getElementById("previewbutton").style.display = 'block';
}

function saveMagicValue(torrentid,value)
{
    nativePost("magic.php", {"value": value, "id": torrentid}, function(res) {
        if (res.ret !== 0) {
            alert(res.msg)
            return
        }
        document.getElementById("magic_add").value += value;
        document.getElementById("magic_add").style.display = '';
        document.getElementById("listNumber").style.display = 'none';
        document.getElementById("current_user_magic").style.display = '';
        var sumAll = document.getElementById("spanSumAll").innerHTML;
        document.getElementById("spanSumAll").innerHTML = sumAll*1 + value;
        if(document.getElementById("count_user_spa")){
            var userAll = document.getElementById("count_user_spa").innerHTML;
            document.getElementById("count_user_spa").innerHTML = userAll*1 + 1;
        }
    })
}

// java_klappe.js — only klappe_news survives: delegated data-klappe
// emitters all render the plus/minus icon variant.

function klappe_news(id)
{
var klappText = document.getElementById('k' + id);
var klappBild = document.getElementById('pic' + id);
if (!klappText) { return; }
var hidden = klappText.classList.toggle('nx-hidden');
if (klappBild) { klappBild.className = hidden ? 'plus' : 'minus'; }
var trigger = document.querySelector('[data-klappe="' + id + '"]');
if (trigger) { trigger.setAttribute('aria-expanded', hidden ? 'false' : 'true'); }
}

// ctrlenter.js
var submitted = false;
function ctrlenter(event,formname,submitname){
	if (submitted == false){
	var keynum;
	if (event.keyCode){
		keynum = event.keyCode;
	}
	else if (event.which){
		keynum = event.which;
	}
	if (event.ctrlKey && keynum == 13){
		submitted = true;
		document.getElementById(formname).submit();
		}
	}
}
function gotothepage(page){
var url=window.location.href;
var end=url.lastIndexOf("page");
url = url.replace(/#[0-9]+/g,"");
if (end == -1){
if (url.lastIndexOf("?") == -1)
window.location.href=url+"?page="+page;
else
window.location.href=url+"&page="+page;
}
else{
url = url.replace(/page=.+/g,"");
window.location.href=url+"page="+page;
}
}
function changepage(event){
if (typeof currentpage === 'undefined' || typeof maxpage === 'undefined') { return; }
var gotopage;
var keynum;
var altkey;
if (navigator.userAgent.toLowerCase().indexOf('presto') != -1)
altkey = event.shiftKey;
else altkey = event.altKey;
if (event.keyCode){
	keynum = event.keyCode;
}
else if (event.which){
	keynum = event.which;
}
if(altkey && keynum==33){
if(currentpage<=0) return;
gotopage=currentpage-1;
gotothepage(gotopage);
}
else if (altkey && keynum == 34){
if(currentpage>=maxpage) return;
gotopage=currentpage+1;
gotothepage(gotopage);
}
}
if(window.document.addEventListener){
window.addEventListener("keydown",changepage,false);
}
else{
window.attachEvent("onkeydown",changepage,false);
}

// bookmark.js
function bookmark(torrentid,counter)
{
ajax.fetchText('bookmark.php?torrentid='+torrentid).then(function(result){bmicon(result,counter)}).catch(function(){});
}
function bmicon(status,counter)
{
	if (status=="added")
		document.getElementById("bookmark"+counter).innerHTML="<img class=\"bookmark\" src=\"pic/trans.gif\" alt=\"Bookmarked\" />";
	else if (status=="deleted")
		document.getElementById("bookmark"+counter).innerHTML="<img class=\"delbookmark\" src=\"pic/trans.gif\" src=\"pic/trans.gif\" alt=\"Unbookmarked\" />";
}

// check.js
var checkflag = "false";
function check(field,checkall_name,uncheckall_name) {
	if (checkflag == "false") {
		for (i = 0; i < field.length; i++) {
			field[i].checked = true;}
			checkflag = "true";
			return uncheckall_name; }
			else {
				for (i = 0; i < field.length; i++) {
					field[i].checked = false; }
					checkflag = "false";
					return checkall_name; }
}

// in torrents.php
var form='searchbox';
function SetChecked(chkName,ctrlName,checkall_name,uncheckall_name,start,count) {
	dml=document.forms[form];
	len = dml.elements.length;
	var begin;
	var end;
	if (start == -1){
	begin = 0;
	end = len;
	}
	else{
	begin = start;
	end = start + count;
	}
	var check_state;
	for( i=0 ; i<len ; i++) {
		if(dml.elements[i].name==ctrlName)
		{
			if(dml.elements[i].value == checkall_name)
			{
				dml.elements[i].value = uncheckall_name;
				check_state=1;
			}
			else
			{
				dml.elements[i].value = checkall_name;
				check_state=0;
			}
		}

	}
	for( i=begin ; i<end ; i++) {
		if (dml.elements[i].name.indexOf(chkName) == 0) {
			dml.elements[i].checked=check_state;
		}
	}
}


// in upload.php
function getname()
{
var filename = document.getElementById("torrent").value;
var filename = filename.toString();
var lowcase = filename.toLowerCase();
var start = lowcase.lastIndexOf("\\"); //for Google Chrome on windows
if (start == -1){
start = lowcase.lastIndexOf("\/"); // for Google Chrome on linux
if (start == -1)
start == 0;
else start = start + 1;
}
else start = start + 1;
var end = lowcase.lastIndexOf("torrent");
var noext = filename.substring(start,end-1);
noext = noext.replace(/H\.264/ig,"H_264");
noext = noext.replace(/5\.1/g,"5_1");
noext = noext.replace(/2\.1/g,"2_1");
noext = noext.replace(/\./g," ");
noext = noext.replace(/H_264/g,"H.264");
noext = noext.replace(/5_1/g,"5.1");
noext = noext.replace(/2_1/g,"2.1");
document.getElementById("name").value=noext;
}

// in userdetails.php
function getusertorrentlistajax(userid, type, blockid)
{
var block=document.getElementById(blockid);
if (!block) { return true; }
if (block.innerHTML=="" && !block.getAttribute('data-utl-loading')){
block.setAttribute('data-utl-loading','1');
ajax.fetchText('getusertorrentlistajax.php?userid='+userid+'&type='+type).then(function(infoblock){
block.innerHTML=infoblock;
block.removeAttribute('data-utl-loading');
}).catch(function(){
block.removeAttribute('data-utl-loading');
});
}
return true;
}

// in userdetails.php
function enabledel(msg){
document.deluser.submit.disabled=document.deluser.submit.checked;
alert (msg);
}

function disabledel(){
document.deluser.submit.disabled=!document.deluser.submit.checked;
}

// settings.php
function NewRow(anchor,up){
	var thisRow = anchor.parentNode.parentNode;
	var newRow = thisRow.cloneNode(true);
	var InputBoxes = newRow.getElementsByTagName("input");
	for(i=0; i<InputBoxes.length; i++) InputBoxes.item(i).value = "";
	var position = up ? "beforeBegin" : "afterEnd";
	thisRow.insertAdjacentElement(position,newRow);
}
function DelRow(anchor){
	anchor.parentNode.parentNode.parentNode.parentNode.deleteRow(anchor.parentNode.parentNode.rowIndex);
}



// setlist lookup from torrent name on upload.php
function lookupSetlist() {
    var nameInput = document.getElementById('name');
    if (!nameInput) return;
    var name = nameInput.value.trim();
    if (!name) {
        alert('Enter the torrent name first.');
        return;
    }
    var btn = document.getElementById('setlistLookupBtn');
    if (btn) {
        btn.value = 'Loading...';
        btn.disabled = true;
    }
    ajax.fetchText('setlist_lookup.php?name=' + encodeURIComponent(name)).then(function (response) {
        if (btn) {
            btn.value = 'Fill setlist';
            btn.disabled = false;
        }
        try {
            var data = JSON.parse(response);
            if (data.success && data.text) {
                var descr = document.getElementById('descr');
                if (descr) {
                    descr.value = (descr.value ? descr.value + "\n\n" : "") + data.text;
                }
            } else {
                alert(data.error || 'Setlist not found.');
            }
        } catch (e) {
            alert('Setlist lookup failed.');
        }
    }).catch(function () {
        if (btn) {
            btn.value = 'Fill setlist';
            btn.disabled = false;
        }
        alert('Setlist lookup failed.');
    });
}

// CSP-safe delegated bindings for legacy bbcode editor controls.
// Inline on*= handlers and javascript: URLs are blocked by the nonce-based
// Content-Security-Policy, so legacy markup carries data-* attributes that
// these document-level listeners dispatch to the existing global functions.
document.addEventListener('click', function (e) {
    var target = e.target;
    if (!target || !target.closest) { return; }

    var action = target.closest('[data-bbcode-action]');
    if (action) {
        var name = action.getAttribute('data-bbcode-action');
        var handled = true;
        if (name === 'simpletag' && typeof simpletag === 'function') {
            simpletag(action.getAttribute('data-bbcode-tag'));
        } else if (name === 'closeall' && typeof closeall === 'function') {
            closeall();
        } else if (name === 'tag_url' && typeof tag_url === 'function') {
            tag_url(action.getAttribute('data-prompt1'), action.getAttribute('data-prompt2'), action.getAttribute('data-prompt3'));
        } else if (name === 'tag_image' && typeof tag_image === 'function') {
            tag_image(action.getAttribute('data-prompt1'), action.getAttribute('data-prompt2'));
        } else if (name === 'tag_list' && typeof tag_list === 'function') {
            tag_list(action.getAttribute('data-prompt1'), action.getAttribute('data-prompt2'));
        } else if (name === 'winop' && typeof winop === 'function') {
            winop();
        } else if (name === 'preview' && typeof textBBCodePreview === 'function') {
            textBBCodePreview();
        } else if (name === 'edit' && typeof textBBCodeEdit === 'function') {
            textBBCodeEdit();
        } else {
            handled = false;
        }
        if (handled) { e.preventDefault(); }
        return;
    }

    var toggle = target.closest('[data-preview-toggle]');
    if (toggle) {
        var mode = toggle.getAttribute('data-preview-toggle');
        if (mode === 'preview' && typeof preview === 'function') { preview(toggle.parentNode); }
        if (mode === 'unpreview' && typeof unpreview === 'function') { unpreview(toggle.parentNode); }
        e.preventDefault();
        return;
    }

    var smile = target.closest('[data-smile]');
    if (smile) {
        if (typeof SmileIT === 'function') {
            SmileIT(smile.getAttribute('data-smile'), smile.getAttribute('data-smile-form'), smile.getAttribute('data-smile-text'));
        }
        e.preventDefault();
    }
});

document.addEventListener('change', function (e) {
    var el = e.target && e.target.closest ? e.target.closest('[data-bbcode-alterfont]') : null;
    if (el && typeof alterfont === 'function') {
        alterfont(el.value, el.getAttribute('data-bbcode-alterfont'));
    }
});

document.addEventListener('keydown', function (e) {
    var el = e.target && e.target.closest ? e.target.closest('[data-ctrlenter]') : null;
    if (el && typeof ctrlenter === 'function') {
        var parts = el.getAttribute('data-ctrlenter').split(':');
        ctrlenter(e, parts[0], parts[1]);
    }
});

// data-domtt-* hover tooltips are handled by js/nx-tooltip.js.

// CSP-safe delegated bindings for legacy interactive controls.
// Inline on*= handlers and javascript: URLs are blocked by the
// nonce-based Content-Security-Policy; markup carries data-* hooks
// dispatched here to the existing global functions.
document.addEventListener('click', function (e) {
    var target = e.target;
    if (!target || !target.closest) { return; }

    var confirmEl = target.closest('[data-confirm]');
    if (confirmEl) {
        if (!window.confirm(confirmEl.getAttribute('data-confirm'))) {
            e.preventDefault();
        }
        return;
    }

    var setChecked = target.closest('input[data-setchecked]');
    if (setChecked && typeof SetChecked === 'function') {
        SetChecked(setChecked.getAttribute('data-setchecked'), setChecked.getAttribute('data-setchecked-ctrl'), setChecked.getAttribute('data-checkall'), setChecked.getAttribute('data-uncheckall'), -1, 10);
        return;
    }

    var newRow = target.closest('a.js-newrow');
    if (newRow && typeof NewRow === 'function') {
        NewRow(newRow, newRow.getAttribute('data-newrow') === 'before');
        e.preventDefault();
        return;
    }

    var utlLink = target.closest('a[data-utl]');
    if (utlLink) {
        if (typeof getusertorrentlistajax === 'function') {
            getusertorrentlistajax(utlLink.getAttribute('data-utl-user'), utlLink.getAttribute('data-utl'), utlLink.getAttribute('data-utl-block'));
        }
        if (utlLink.hasAttribute('data-klappe') && typeof klappe_news === 'function') {
            klappe_news(utlLink.getAttribute('data-klappe'));
        }
        e.preventDefault();
        return;
    }

    var klappeLink = target.closest('[data-klappe]');
    if (klappeLink) {
        if (typeof klappe_news === 'function') { klappe_news(klappeLink.getAttribute('data-klappe')); }
        e.preventDefault();
        return;
    }

    var checkAll = target.closest('input[data-checkall]');
    if (checkAll && typeof check === 'function') {
        var form = checkAll.form || document.forms['form'];
        if (form) {
            checkAll.value = check(form, checkAll.getAttribute('data-label-check'), checkAll.getAttribute('data-label-uncheck'));
        }
        return;
    }

    var resetFilter = target.closest('input.js-filter-reset');
    if (resetFilter) {
        var q = document.getElementById('q');
        if (q) { q.value = ''; }
        var filterForm = document.getElementById('filterForm');
        if (filterForm) { filterForm.submit(); }
        e.preventDefault();
        return;
    }

    var orderBtn = target.closest('#order');
    if (orderBtn && typeof dropmenu === 'function') {
        dropmenu(orderBtn);
        return;
    }

    var bmLink = target.closest('a[data-bookmark-torrent]');
    if (bmLink && typeof bookmark === 'function') {
        bookmark(parseInt(bmLink.getAttribute('data-bookmark-torrent'), 10), bmLink.getAttribute('data-bookmark-counter') || '0');
        e.preventDefault();
        return;
    }

    var setlistBtn = target.closest('#setlistLookupBtn');
    if (setlistBtn && typeof lookupSetlist === 'function') {
        lookupSetlist();
        return;
    }

    var magicItem = target.closest('li[data-magic-value]');
    if (magicItem && typeof saveMagicValue === 'function') {
        saveMagicValue(parseInt(magicItem.getAttribute('data-torrent-id'), 10), parseInt(magicItem.getAttribute('data-magic-value'), 10));
        return;
    }

    var showAll = target.closest('#magic_show_all');
    if (showAll) {
        var other = document.getElementById('other_user_list');
        var ellipsis = document.getElementById('ellipsis');
        if (other) { other.classList.remove('nx-hidden'); }
        if (ellipsis) { ellipsis.classList.add('nx-hidden'); }
        showAll.classList.add('nx-hidden');
        e.preventDefault();
        return;
    }

    var thanksBtn = target.closest('#saythanks');
    if (thanksBtn && typeof saythanks === 'function') {
        saythanks(parseInt(thanksBtn.getAttribute('data-torrent-id'), 10));
        return;
    }

    var delRow = target.closest('a.js-delrow');
    if (delRow && typeof DelRow === 'function') {
        DelRow(delRow);
        e.preventDefault();
        return;
    }

    var infoToggle = target.closest('a.js-info-toggle');
    if (infoToggle) {
        var ul = infoToggle.parentNode && infoToggle.parentNode.nextElementSibling;
        if (ul) { ul.classList.toggle('nx-hidden'); }
        e.preventDefault();
        return;
    }

    var fileListLink = target.closest('a[data-filelist]');
    if (fileListLink) {
        var tid = parseInt(fileListLink.getAttribute('data-filelist'), 10);
        if (fileListLink.getAttribute('data-filelist-mode') === 'hide' && typeof hidefilelist === 'function') {
            hidefilelist();
        } else if (typeof viewfilelist === 'function') {
            viewfilelist(tid);
        }
        e.preventDefault();
        return;
    }

    var peerListLink = target.closest('a[data-peerlist]');
    if (peerListLink) {
        var pid = parseInt(peerListLink.getAttribute('data-peerlist'), 10);
        if (peerListLink.getAttribute('data-peerlist-mode') === 'hide' && typeof hidepeerlist === 'function') {
            hidepeerlist();
        } else if (typeof viewpeerlist === 'function') {
            viewpeerlist(pid);
        }
        e.preventDefault();
        return;
    }

    var backLink = target.closest('a.js-history-back');
    if (backLink) {
        history.back();
        e.preventDefault();
        return;
    }
});

document.addEventListener('submit', function (e) {
    var form = e.target;
    if (form && form.matches && (form.id === 'compose' || form.id === 'reply') && typeof postvalid === 'function') {
        if (!postvalid(form)) { e.preventDefault(); }
    }
});

document.addEventListener('change', function (e) {
    var el = e.target;
    if (!el || !el.name) { return; }

    if (el.id === 'torrent' && el.type === 'file' && typeof getname === 'function') {
        getname();
        return;
    }

    if (el.name === 'delenable') {
        if (el.checked && typeof enabledel === 'function') {
            enabledel(el.getAttribute('data-del-msg') || '');
        } else if (!el.checked && typeof disabledel === 'function') {
            disabledel();
        }
        return;
    }

    if (el.name === 'promotion_time_type') {
        var note = document.getElementById('promotion_until_note');
        if (note) { note.classList.toggle('nx-hidden', el.value !== '2'); }
        return;
    }

    if (el.name === 'promotionaddedtime') {
        var until = document.getElementById('promotionuntiltime');
        if (until) { until.value = el.value; }
        return;
    }

    if (el.name === 'smtptype') {
        var adv = document.getElementById('smtp_advanced');
        var ext = document.getElementById('smtp_external');
        if (adv) { adv.classList.toggle('nx-hidden', el.value !== 'advanced'); }
        if (ext) { ext.classList.toggle('nx-hidden', el.value !== 'external'); }
        return;
    }

    if (el.name === 'savatar') {
        var avatarImg = document.getElementById('avatarimg');
        if (avatarImg) { avatarImg.src = el.value; }
        if (el.form && el.form.avatar) { el.form.avatar.value = el.value; }
        return;
    }

    if (el.id === 'letmedown') {
        var cont = document.getElementById('continuedownload');
        if (cont) { cont.disabled = !el.checked; }
        return;
    }

    if (el.name === 'sitelanguage' && el.form) {
        el.form.submit();
        return;
    }
});

// Cover images on the index grid: swap to the text fallback on load error.
// Capture phase is required — error events do not bubble.
document.addEventListener('error', function (e) {
    var img = e.target;
    if (img && img.tagName === 'IMG' && img.closest && img.closest('.lt-cover')) {
        img.classList.add('lt-broken');
        img.closest('.lt-cover').classList.add('lt-cover-empty');
    }
}, true);

// Image helpers formerly in curtain_imageresizer.js: Scale() shrinks
// oversized BBCode images, check_avatar() swaps oversized avatars for the
// placeholder, handleImageError() retries doubanio covers on mirror
// domains. The curtain lightbox (Preview/showPreviewImage/Previewurl) was
// dead code — no #curtain/#lightbox elements exist — js-previewable now
// uses data-zoomable (nx-zoom).
function Scale(image, max_width, max_height) {
    var tempimage = new Image();
    tempimage.src = image.src;
    var tempwidth = tempimage.width;
    var tempheight = tempimage.height;
    if (tempwidth > max_width) {
        image.height = tempheight = Math.round((max_width / tempwidth) * tempheight);
        image.width = tempwidth = max_width;
    }
    if (max_height !== 0 && tempheight > max_height) {
        image.width = Math.round((max_height / tempheight) * tempwidth);
        image.height = max_height;
    }
}

function check_avatar(image, langfolder) {
    var tempimage = new Image();
    tempimage.src = image.src;
    var displayheight = image.height;
    var tempwidth = tempimage.width;
    var tempheight = tempimage.height;
    if (tempwidth > 250 || tempheight > 250 || displayheight > 250) {
        var folder = /^[a-z_]+$/i.test(langfolder) ? langfolder : 'en';
        image.src = 'pic/forum_pic/' + folder + '/avatartoobig.png';
    }
}

function handleImageError(img, currentSrc) {
    var url;
    try {
        url = new URL(currentSrc);
    } catch (e) {
        return;
    }
    var host = url.hostname;
    if (url.protocol !== 'https:' || (host !== 'doubanio.com' && !host.endsWith('.doubanio.com'))) {
        return;
    }
    var path = url.pathname + url.search;
    var domainList = ['img1.doubanio.com', 'img2.doubanio.com', 'img3.doubanio.com', 'img9.doubanio.com'];
    var index = 0;
    function tryNextDomain() {
        if (index >= domainList.length) {
            return;
        }
        var next = 'https://' + domainList[index] + path;
        if (!/^https:\/\/[a-z0-9-]+\.doubanio\.com\//i.test(next)) {
            return;
        }
        img.src = encodeURI(next);
        img.onload = function () { img.onload = img.onerror = null; };
        img.onerror = tryNextDomain;
        index++;
    }
    tryNextDomain();
}

// img load handlers (capture phase — load does not bubble):
// data-scale="WxH" resizes large BBCode images, data-avatar-check swaps
// oversized avatars for the placeholder.
document.addEventListener('load', function (e) {
    var img = e.target;
    if (!img || img.tagName !== 'IMG') { return; }
    var scale = img.getAttribute('data-scale');
    if (scale) {
        var parts = scale.split('x');
        Scale(img, parseInt(parts[0], 10), parseInt(parts[1], 10));
    }
    var avatarCheck = img.getAttribute('data-avatar-check');
    if (avatarCheck !== null) {
        check_avatar(img, avatarCheck);
    }
}, true);

document.addEventListener('error', function (e) {
    var img = e.target;
    if (img && img.tagName === 'IMG') {
        var fb = img.getAttribute('data-img-fallback');
        if (fb) {
            handleImageError(img, fb);
        }
    }
}, true);

/* ===== goup.js ===== */
/**
 * Native scroll-to-top button (replaces jquery-goup plugin).
 *
 * Shows a floating button when the user scrolls down, clicking it
 * smoothly scrolls back to the top of the page.
 */
(function () {
    'use strict';

    var button = document.createElement('div');
    button.id = 'goup-btn';
    button.setAttribute('role', 'button');
    button.setAttribute('aria-label', (window.NX_SITE_LANG && window.NX_SITE_LANG.scrollTop) || 'Scroll to top');
    button.setAttribute('tabindex', '0');
    Object.assign(button.style, {
        position: 'fixed',
        bottom: '20px',
        right: '20px',
        width: '40px',
        height: '40px',
        borderRadius: '50%',
        background: 'rgba(0, 0, 0, 0.5)',
        color: '#fff',
        cursor: 'pointer',
        display: 'none',
        zIndex: '9999',
        alignItems: 'center',
        justifyContent: 'center',
        fontSize: '20px',
        lineHeight: '40px',
        textAlign: 'center',
        transition: 'opacity 0.3s'
    });
    button.innerHTML = '&uarr;';
    document.body.appendChild(button);

    function toggleVisibility() {
        if (window.pageYOffset > 200) {
            button.style.display = 'flex';
        } else {
            button.style.display = 'none';
        }
    }

    window.addEventListener('scroll', toggleVisibility, { passive: true });
    toggleVisibility();

    button.addEventListener('click', function () {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    button.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    });
})();

/* ===== theme-toggle.js ===== */
/**
 * Theme toggle (ADR 0019): cycles auto → light → dark on the
 * .nxm-theme-toggle button. Logged-in users persist via POST
 * /web/usercp/theme; anonymous users persist via localStorage only.
 */
(function () {
    var ORDER = ['auto', 'light', 'dark'];
    var LABELS = {auto: 'Auto', light: 'Light', dark: 'Dark'};
    var KEY = 'nxm-theme';
    var root = document.documentElement;

    function currentTheme() {
        var t = root.getAttribute('data-theme') || 'auto';
        return ORDER.indexOf(t) >= 0 ? t : 'auto';
    }

    function applyTheme(theme) {
        root.setAttribute('data-theme', theme);
    }

    function applyToFrames(theme) {
        try {
            var frames = document.querySelectorAll('iframe');
            for (var k = 0; k < frames.length; k++) {
                var doc = frames[k].contentDocument;
                if (doc && doc.documentElement && doc.documentElement.hasAttribute('data-theme')) {
                    doc.documentElement.setAttribute('data-theme', theme);
                }
            }
        } catch (e) {
            /* cross-origin frame — ignore */
        }
    }

    function storedTheme() {
        try {
            return localStorage.getItem(KEY);
        } catch (e) {
            return null;
        }
    }

    function storeTheme(theme) {
        try {
            localStorage.setItem(KEY, theme);
        } catch (e) {
            /* storage unavailable — ignore */
        }
    }

    var buttons = document.querySelectorAll('.nxm-theme-toggle');
    var persistUrl = '';
    for (var i = 0; i < buttons.length; i++) {
        var u = buttons[i].getAttribute('data-persist-url');
        if (u) {
            persistUrl = u;
            break;
        }
    }

    // Anonymous pages render data-theme="auto" server-side; honour a
    // previously stored choice there. Logged-in pages already render the
    // user's saved theme — server value wins.
    if (!persistUrl) {
        var saved = storedTheme();
        if (saved && ORDER.indexOf(saved) >= 0) {
            applyTheme(saved);
        }
    }

    function paint(button) {
        var label = 'Theme: ' + LABELS[currentTheme()];
        if (button.querySelector('.nxm-theme-ic')) {
            button.setAttribute('data-theme-state', currentTheme());
        } else if (button.hasAttribute('data-persist-url') || !persistUrl) {
            button.textContent = '[' + label + ']';
        } else {
            button.textContent = '[Theme]';
        }
        button.setAttribute('title', label);
        button.setAttribute('aria-label', label);
    }

    function paintAll() {
        for (var i = 0; i < buttons.length; i++) {
            paint(buttons[i]);
        }
    }

    for (var j = 0; j < buttons.length; j++) {
        buttons[j].addEventListener('click', function () {
            var next = ORDER[(ORDER.indexOf(currentTheme()) + 1) % ORDER.length];
            applyTheme(next);
            storeTheme(next);
            paintAll();
            applyToFrames(next);
            if (persistUrl) {
                var meta = document.querySelector('meta[name="csrf-token"]');
                fetch(persistUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-TOKEN': meta ? meta.content : '',
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    body: 'theme=' + encodeURIComponent(next)
                });
            }
        });
    }

    // Re-apply the active theme to iframes as they finish loading —
    // anonymous pages restore it from localStorage and the shoutbox
    // iframe re-renders server-side on its own refresh cycle.
    var themedFrames = document.querySelectorAll('iframe');
    for (var f = 0; f < themedFrames.length; f++) {
        themedFrames[f].addEventListener('load', function () {
            applyToFrames(currentTheme());
        });
    }
    applyToFrames(currentTheme());

    // Keep iframes in sync when data-theme changes outside this toggle
    // (tests, other scripts) — the click path already propagates itself.
    if (typeof MutationObserver === 'function') {
        new MutationObserver(function () {
            applyToFrames(currentTheme());
        }).observe(root, { attributes: true, attributeFilter: ['data-theme'] });
    }

    paintAll();
})();

/* ===== nx-chrome.js ===== */
/**
 * Mobile chrome toggle (Stage 4.2): the burger button shows/hides the
 * combined nav+userbar container at narrow widths. Progressive
 * enhancement — without JS the container never receives the
 * nxm-collapse--ready class, so the menu stays fully expanded.
 */
(function () {
    var btn = document.querySelector('.nxm-burger');
    var panel = btn && document.getElementById(btn.getAttribute('aria-controls'));
    if (!btn || !panel) {
        return;
    }

    panel.classList.add('nxm-collapse--ready');

    function setOpen(open) {
        panel.classList.toggle('nxm-open', open);
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    btn.addEventListener('click', function () {
        setOpen(!panel.classList.contains('nxm-open'));
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && panel.classList.contains('nxm-open')) {
            setOpen(false);
            btn.focus();
        }
    });
})();

/* ===== search shortcut + mid-width overlay ===== */
// Cmd/Ctrl+K focuses the header search field (mirrors the ⌘K hint in
// the search pill). On mid-widths the pill is hidden behind the
// magnifier toggle — open the overlay first, then focus.
(function () {
    var header = document.querySelector('.nxm-header');
    var toggle = document.querySelector('.nxm-searchbtn');
    function input() { return document.querySelector('.nxm-search input[name="search"]'); }

    function setOpen(open) {
        if (!header || !toggle) { return; }
        header.classList.toggle('nxm-search--open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
    function openAndFocus() {
        setOpen(true);
        var el = input();
        if (el) { el.focus(); el.select(); }
    }

    if (toggle && header) {
        toggle.addEventListener('click', function () {
            if (header.classList.contains('nxm-search--open')) { setOpen(false); return; }
            openAndFocus();
        });
        document.addEventListener('click', function (e) {
            if (!header.classList.contains('nxm-search--open')) { return; }
            if (e.target.closest('.nxm-search') || e.target.closest('.nxm-searchbtn')) { return; }
            setOpen(false);
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { setOpen(false); }
        });
    }

    document.addEventListener('keydown', function (e) {
        if (!(e.metaKey || e.ctrlKey) || e.altKey || e.key.toLowerCase() !== 'k') { return; }
        var el = input();
        if (!el) { return; }
        e.preventDefault();
        if (el.offsetParent === null && toggle && toggle.offsetParent !== null) {
            openAndFocus();
        } else {
            el.focus();
            el.select();
        }
    });
})();

/* ===== nx-menus.js ===== */
/**
 * Header <details> dropdowns (nav overflow + user menu): clicking
 * outside or pressing Escape closes an open menu, and opening one
 * closes the others.
 */
(function () {
    var menus = document.querySelectorAll('details.nxm-more, details.nxm-usermenu');
    if (!menus.length) {
        return;
    }

    function closeAll(except) {
        for (var i = 0; i < menus.length; i++) {
            if (menus[i] !== except && menus[i].hasAttribute('open')) {
                menus[i].removeAttribute('open');
            }
        }
    }

    for (var i = 0; i < menus.length; i++) {
        menus[i].addEventListener('toggle', function () {
            if (this.hasAttribute('open')) {
                closeAll(this);
            }
        });
    }

    document.addEventListener('click', function (e) {
        var target = e.target;
        for (var i = 0; i < menus.length; i++) {
            if (menus[i].hasAttribute('open') && !menus[i].contains(target)) {
                menus[i].removeAttribute('open');
            }
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeAll(null);
        }
    });
})();

/* ===== nexus.js ===== */
/**
 * Image preview + lazy-load (native JS, no jQuery).
 */
document.addEventListener('DOMContentLoaded', function () {
    function getImgPosition(e, imgEle) {
        let imgWidth = imgEle.naturalWidth;
        let imgHeight = imgEle.naturalHeight;
        let ratio = imgWidth / imgHeight;
        let offsetX = 10;
        let offsetY = 10;
        let width = window.innerWidth - e.clientX;
        let height = window.innerHeight - e.clientY;
        let changeOffsetY = 0;
        let changeOffsetX = false;
        if (e.clientX > window.innerWidth / 2 && e.clientX + imgWidth > window.innerWidth) {
            changeOffsetX = true;
            width = e.clientX;
        }
        if (e.clientY > window.innerHeight / 2) {
            if (e.clientY + imgHeight / 2 > window.innerHeight) {
                changeOffsetY = 1;
                height = e.clientY;
            } else if (e.clientY + imgHeight > window.innerHeight) {
                changeOffsetY = 2;
                height = e.clientY;
            }
        }
        if (imgWidth > width) {
            imgWidth = width;
            imgHeight = imgWidth / ratio;
        }
        if (imgHeight > height) {
            imgHeight = height;
            imgWidth = imgHeight * ratio;
        }
        if (changeOffsetX) {
            offsetX = -(e.clientX - width + 10);
        }
        if (changeOffsetY === 1) {
            offsetY = -(imgHeight - (window.innerHeight - e.clientY));
        } else if (changeOffsetY === 2) {
            offsetY = -imgHeight / 2;
        }
        return { imgWidth, imgHeight, offsetX, offsetY };
    }

    function getPosition(e, position) {
        if (!position) {
            return {};
        }
        return {
            left: e.pageX + position.offsetX,
            top: e.pageY + position.offsetY,
            width: position.imgWidth,
            height: position.imgHeight
        };
    }

    let previewEle = document.getElementById('nexus-preview');
    let imgEle = null;
    let imgPosition = null;
    let selector = 'img.preview';

    document.body.addEventListener('mouseover', function (e) {
        let target = e.target;
        if (!target || !target.matches || !target.matches(selector)) return;
        imgEle = target;
        imgPosition = getImgPosition(e, imgEle);
        let position = getPosition(e, imgPosition);
        let src = imgEle.getAttribute('src');
        if (src && previewEle) {
            previewEle.setAttribute('src', src);
            Object.assign(previewEle.style, {
                display: 'block',
                left: position.left + 'px',
                top: position.top + 'px',
                width: position.width + 'px',
                height: position.height + 'px'
            });
            previewEle.style.opacity = '1';
        }
    });

    document.body.addEventListener('mouseout', function (e) {
        let target = e.target;
        if (!target || !target.matches || !target.matches(selector)) return;
        if (previewEle) {
            previewEle.style.display = 'none';
        }
    });

    document.body.addEventListener('mousemove', function (e) {
        let target = e.target;
        if (!target || !target.matches || !target.matches(selector)) return;
        if (previewEle && imgPosition) {
            let position = getPosition(e, imgPosition);
            Object.assign(previewEle.style, {
                left: position.left + 'px',
                top: position.top + 'px'
            });
        }
    });

    // lazy load
    if ("IntersectionObserver" in window) {
        const fallbackImage = 'pic/misc/cover.svg';
        const domainList = ['img1.doubanio.com', 'img2.doubanio.com', 'img3.doubanio.com', 'img9.doubanio.com'];
        const imgList = [...document.querySelectorAll('.nexus-lazy-load')];
        const loadedImages = {};
        const io = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                const el = entry.target;
                const intersectionRatio = entry.intersectionRatio;
                if (intersectionRatio > 0 && intersectionRatio <= 1 && !el.classList.contains('preview')) {
                    let src = el.dataset.src;
                    if (src) {
                        try {
                            const u = new URL(src, location.href);
                            if ((u.hostname === 'doubanio.com' || u.hostname.endsWith('.doubanio.com')) && u.pathname.includes('l_ratio_poster')) {
                                u.pathname = u.pathname.replace('l_ratio_poster', 's_ratio_poster');
                                src = u.href;
                                el.dataset.src = src;
                            }
                        } catch (e) { /* keep src as-is */ }
                    }
                    el.src = src;
                    el.classList.add('preview');
                    loadedImages[src] = true;
                    el.onload = el.onerror = () => io.unobserve(el);
                    el.onerror = () => handleImageError(el, src);
                }
            });
        });
        imgList.forEach(img => io.observe(img));
        function handleImageError(img, currentSrc) {
            let url = null;
            try { url = new URL(currentSrc); } catch (e) { /* not a URL */ }
            if (!url || url.protocol !== 'https:' || !(url.hostname === 'doubanio.com' || url.hostname.endsWith('.doubanio.com'))) {
                img.src = fallbackImage;
            } else {
                tryNextDomain(img, url, 0);
            }
        }
        function tryNextDomain(img, url, index = 0) {
            if (index >= domainList.length) {
                img.src = fallbackImage;
                return;
            }
            const next = `https://${domainList[index]}${url.pathname}${url.search}`;
            if (!/^https:\/\/[a-z0-9-]+\.doubanio\.com\//i.test(next)) {
                img.src = fallbackImage;
                return;
            }
            img.src = encodeURI(next);
            img.onerror = () => tryNextDomain(img, url, index + 1);
        }
    }
});

/* ===== nx-action-menu.js ===== */
/**
 * <details>-based action menus (components/action-menu.blade.php).
 * Delegated handlers: Esc closes and refocuses the toggle, click outside
 * closes, ArrowUp/ArrowDown move focus between menu items.
 */
(function () {
    'use strict';

    document.addEventListener('keydown', function (e) {
        var menu = e.target && e.target.closest ? e.target.closest('.nx-action-menu') : null;
        if (!menu || !menu.open) { return; }
        if (e.key === 'Escape') {
            e.preventDefault();
            menu.open = false;
            var toggle = menu.querySelector('.nx-action-menu__toggle');
            if (toggle) { toggle.focus(); }
            return;
        }
        if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp') { return; }
        var items = menu.querySelectorAll('.nx-action-menu__list a, .nx-action-menu__list button');
        if (!items.length) { return; }
        e.preventDefault();
        var idx = Array.prototype.indexOf.call(items, document.activeElement);
        if (e.key === 'ArrowDown') {
            items[(idx + 1) % items.length].focus();
        } else {
            items[(idx <= 0 ? items.length : idx) - 1].focus();
        }
    });

    document.addEventListener('click', function (e) {
        document.querySelectorAll('.nx-action-menu[open]').forEach(function (menu) {
            if (!menu.contains(e.target)) {
                menu.open = false;
            }
        });
    });
})();

// Alert banners (promo/staff/news): dismissal persists in localStorage keyed
// by a hash of the banner content, so a changed banner reappears.
(function () {
    var storeKey = 'nxm-alert-hide';
    function readStore() {
        try {
            var raw = localStorage.getItem(storeKey);
            return raw ? (JSON.parse(raw) || {}) : {};
        } catch (e) { return {}; }
    }
    var hidden = readStore();
    document.querySelectorAll('.nxm-alert[data-alert-key]').forEach(function (el) {
        if (hidden[el.getAttribute('data-alert-key')]) { el.hidden = true; }
    });
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-alert-dismiss]');
        if (!btn) { return; }
        var el = btn.closest('.nxm-alert');
        if (!el) { return; }
        hidden = readStore();
        hidden[el.getAttribute('data-alert-key')] = 1;
        try { localStorage.setItem(storeKey, JSON.stringify(hidden)); } catch (e) {}
        el.hidden = true;
    });
})();

/* data-copy buttons: copy the referenced input's value to the clipboard
   and briefly confirm on the button label. */
(function () {
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-copy]');
        if (!btn) { return; }
        var input = document.querySelector(btn.getAttribute('data-copy'));
        if (!input) { return; }
        var real = input.getAttribute('data-copy-value');
        var shown = input.value;
        if (real !== null) { input.value = real; }
        input.select();
        input.setSelectionRange(0, input.value.length);
        try { document.execCommand('copy'); } catch (err) {}
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(input.value).catch(function () {});
        }
        if (real !== null) { input.value = shown; }
        var original = btn.textContent;
        btn.textContent = btn.getAttribute('data-copy-done') || 'Copied';
        setTimeout(function () { btn.textContent = original; }, 1500);
    });
})();

/* messages.php: reveal the bulk-action bar once any message checkbox is
   checked, and keep the selected counter in sync. */
(function () {
    function update() {
        var bars = document.querySelectorAll('[data-bulkbar]');
        if (!bars.length) { return; }
        var n = document.querySelectorAll('input[name="messages[]"]:checked').length;
        bars.forEach(function (bar) {
            bar.hidden = n === 0;
            var c = bar.querySelector('[data-bulk-count]');
            if (c) { c.textContent = n; }
        });
    }
    document.addEventListener('change', function (e) {
        if (e.target.matches('input[name="messages[]"]')) { update(); }
    });
    document.addEventListener('click', function (e) {
        if (e.target.closest('input[data-checkall]')) { setTimeout(update, 0); }
    });
})();

/* data-reveal buttons: toggle a masked input between its mask and the real
   value stored in data-copy-value (e.g. the usercp passkey). */
(function () {
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-reveal]');
        if (!btn) { return; }
        var input = document.querySelector(btn.getAttribute('data-reveal'));
        if (!input) { return; }
        var real = input.getAttribute('data-copy-value');
        if (real === null) { return; }
        var masked = input.getAttribute('data-mask-value') || input.value;
        var showing = input.value !== masked;
        input.value = showing ? masked : real;
        btn.textContent = showing
            ? (btn.getAttribute('data-label-show') || 'Show')
            : (btn.getAttribute('data-label-hide') || 'Hide');
    });
})();

/* E5 — FAQ accordion: filter items by search box, auto-open hash target. */
(function () {
    var input = document.querySelector('[data-faq-search]');
    var items = document.querySelectorAll('.nx-faq__item');
    var count = document.querySelector('[data-faq-count]');
    function applyHash() {
        var id = location.hash.slice(1);
        var el = id ? document.getElementById(id) : null;
        if (el && el.tagName === 'DETAILS' && !el.open) { el.open = true; }
    }
    if (input && items.length) {
        input.addEventListener('input', function () {
            var q = input.value.trim().toLowerCase();
            var shown = 0;
            items.forEach(function (item) {
                var hit = !q || item.textContent.toLowerCase().indexOf(q) !== -1;
                item.classList.toggle('nx-faq--hidden', !hit);
                if (hit) { shown++; if (q) { item.open = true; } }
            });
            document.querySelectorAll('.nx-faq').forEach(function (g) {
                g.classList.toggle('nx-faq--hidden', !!q && !g.querySelector('.nx-faq__item:not(.nx-faq--hidden)'));
            });
            if (count) {
                count.hidden = !q;
                count.textContent = q ? shown + ' / ' + items.length : '';
            }
        });
    }
    applyHash();
    window.addEventListener('hashchange', applyHash);
})();
