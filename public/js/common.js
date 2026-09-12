function postvalid(form){
	$('qr').disabled = true;
	return true;
}

function dropmenu(obj){
var list = document.getElementById(obj.id + 'list');
if (list) { list.classList.toggle('nx-hidden'); }
}

function confirm_delete(id, note, addon)
{
   if(confirm(note))
   {
      self.location.href='?action=del'+(addon ? '&'+addon : '')+'&id='+id;
   }
}

//viewfilelist.js

function viewfilelist(torrentid)
{
var result=ajax.gets('viewfilelist.php?id='+torrentid);
document.getElementById("showfl").style.display = 'none';
document.getElementById("hidefl").style.display = 'block';
showlist(result);
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
var list=ajax.gets('viewpeerlist.php?id='+torrentid);
document.getElementById("showpeer").style.display = 'none';
document.getElementById("hidepeer").style.display = 'block';
document.getElementById("peercount").style.display = 'none';
document.getElementById("peerlist").innerHTML=list;
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
   document.forms[form].elements[text].value = document.forms[form].elements[text].value+" "+smile+" ";
   document.forms[form].elements[text].focus();
}

// saythanks.js

function saythanks(torrentid)
{
var list=ajax.post('thanks.php','','id='+torrentid);
document.getElementById("thanksbutton").innerHTML = document.getElementById("thanksadded").innerHTML;
document.getElementById("nothanks").innerHTML = "";
document.getElementById("addcuruser").innerHTML = document.getElementById("curuser").innerHTML;
}

// preview.js

function preview(obj) {
	var poststr = encodeURIComponent( document.getElementById("body").value );
	var result=ajax.posts('preview.php','body='+poststr);
	document.getElementById("previewouter").innerHTML=result;
	document.getElementById("previewouter").style.display = 'block';
	document.getElementById("editorouter").style.display = 'none';
	document.getElementById("unpreviewbutton").style.display = 'block';
	document.getElementById("previewbutton").style.display = 'none';
}

function unpreview(obj){
	document.getElementById("previewouter").style.display = 'none';
	document.getElementById("editorouter").style.display = 'block';
	document.getElementById("unpreviewbutton").style.display = 'none';
	document.getElementById("previewbutton").style.display = 'block';
}

function saveMagicValue(torrentid,value)
{
    jQuery.post("magic.php", {"value": value, "id": torrentid}, function(res) {
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
    }, "json")
}

// java_klappe.js

function klappe(id)
{
var klappText = document.getElementById('k' + id);
if (!klappText) { return; }
klappText.classList.toggle('nx-hidden');
}

function klappe_news(id)
{
var klappText = document.getElementById('k' + id);
var klappBild = document.getElementById('pic' + id);
if (!klappText) { return; }
var hidden = klappText.classList.toggle('nx-hidden');
if (klappBild) { klappBild.className = hidden ? 'plus' : 'minus'; }
}
function klappe_ext(id)
{
var klappText = document.getElementById('k' + id);
var klappBild = document.getElementById('pic' + id);
var klappPoster = document.getElementById('poster' + id);
if (!klappText) { return; }
var hidden = klappText.classList.toggle('nx-hidden');
if (klappPoster) { klappPoster.classList.toggle('nx-hidden', hidden); }
if (klappBild) { klappBild.className = hidden ? 'plus' : 'minus'; }
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
var result=ajax.gets('bookmark.php?torrentid='+torrentid);
bmicon(result,counter);
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
if (document.getElementById(blockid).innerHTML==""){
var infoblock=ajax.gets('getusertorrentlistajax.php?userid='+userid+'&type='+type);
document.getElementById(blockid).innerHTML=infoblock;
}
return true;
}

// in functions.php
function get_ext_info_ajax(blockid,url,cache,type)
{
if (document.getElementById(blockid).innerHTML==""){
var infoblock=ajax.gets('getextinfoajax.php?url='+url+'&cache='+cache+'&type='+type);
document.getElementById(blockid).innerHTML=infoblock;
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

// in mybonus.php
function customgift()
{
if (document.getElementById("giftselect").value == '0'){
document.getElementById("giftselect").disabled = true;
document.getElementById("giftcustom").disabled = false;
}
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

// 工具函数：SHA-256哈希
// 因 crypto.subtle 在 http 下不可用，故引入三方库
function sha256(message) {
    // const msgBuffer = new TextEncoder().encode(message);
    // const hashBuffer = await crypto.subtle.digest('SHA-256', msgBuffer);
    // const hashArray = Array.from(new Uint8Array(hashBuffer));
    // return hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
    return CryptoJS.SHA256(message).toString(CryptoJS.enc.Hex);
}

// 工具函数：HMAC-SHA256
function hmacSha256(key, message) {
    // const encoder = new TextEncoder();
    // const keyData = encoder.encode(key);
    // const messageData = encoder.encode(message);
    //
    // const cryptoKey = await crypto.subtle.importKey(
    //     'raw', keyData, { name: 'HMAC', hash: 'SHA-256' }, false, ['sign']
    // );
    //
    // const signature = await crypto.subtle.sign('HMAC', cryptoKey, messageData);
    // const hashArray = Array.from(new Uint8Array(signature));
    // return hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
    return CryptoJS.HmacSHA256(message, key).toString(CryptoJS.enc.Hex);
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
    ajax.get('setlist_lookup.php?name=' + encodeURIComponent(name), function (response) {
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

document.addEventListener('mouseover', function (e) {
    var el = e.target && e.target.closest ? e.target.closest('[data-domtt-content]') : null;
    if (el && typeof domTT_activate === 'function') {
        domTT_activate(el, e, 'content', el.getAttribute('data-domtt-content'),
            'trail', false, 'delay', 0, 'lifetime', 10000, 'styleClass', 'smilies', 'maxWidth', 400);
    }
});

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

    var confirmDel = target.closest('a[data-confirm-del]');
    if (confirmDel && typeof confirm_delete === 'function') {
        confirm_delete(confirmDel.getAttribute('data-confirm-del'), confirmDel.getAttribute('data-confirm-note') || '', confirmDel.getAttribute('data-confirm-addon') || '');
        e.preventDefault();
        return;
    }

    var newRow = target.closest('a.js-newrow');
    if (newRow && typeof NewRow === 'function') {
        NewRow(newRow, newRow.getAttribute('data-newrow') === 'before');
        e.preventDefault();
        return;
    }

    var klappeLink = target.closest('a[data-klappe]');
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
    }
}, true);

// domTT tooltips whose content is another element on the page
// (torrent last-comment / last-post previews) or a literal string
// (promotion expiry hints). Replaces inline onmouseover= attributes.
document.addEventListener('mouseover', function (e) {
    var el = e.target && e.target.closest ? e.target.closest('[data-domtt-src]') : null;
    if (el && typeof domTT_activate === 'function') {
        var src = document.getElementById(el.getAttribute('data-domtt-src'));
        if (src) {
            domTT_activate(el, e, 'content', src, 'trail', false, 'delay', 500, 'lifetime', 3000, 'fade', 'both', 'styleClass', 'niceTitle', 'fadeMax', 87, 'maxWidth', 400);
        }
        return;
    }
    var promo = e.target && e.target.closest ? e.target.closest('[data-domtt-promo]') : null;
    if (promo && typeof domTT_activate === 'function') {
        domTT_activate(promo, e, 'content', promo.getAttribute('data-domtt-promo'), 'trail', false, 'delay', 500, 'lifetime', 3000, 'fade', 'both', 'styleClass', 'niceTitle', 'fadeMax', 87, 'maxWidth', 300);
    }
});

// img load handlers (capture phase — load does not bubble):
// data-scale="WxH" resizes large BBCode images, data-avatar-check swaps
// oversized avatars for the placeholder.
document.addEventListener('load', function (e) {
    var img = e.target;
    if (!img || img.tagName !== 'IMG') { return; }
    var scale = img.getAttribute('data-scale');
    if (scale && typeof Scale === 'function') {
        var parts = scale.split('x');
        Scale(img, parseInt(parts[0], 10), parseInt(parts[1], 10));
    }
    var avatarCheck = img.getAttribute('data-avatar-check');
    if (avatarCheck !== null && typeof check_avatar === 'function') {
        check_avatar(img, avatarCheck);
    }
}, true);

document.addEventListener('error', function (e) {
    var img = e.target;
    if (img && img.tagName === 'IMG') {
        var fb = img.getAttribute('data-img-fallback');
        if (fb && typeof handleImageError === 'function') {
            handleImageError(img, fb);
        }
    }
}, true);

// Attachment/custom-field preview images open in the lightbox.
document.addEventListener('click', function (e) {
    var img = e.target && e.target.closest ? e.target.closest('img.js-previewable') : null;
    if (img && typeof Preview === 'function') {
        Preview(img);
    }
});
