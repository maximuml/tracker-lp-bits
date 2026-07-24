<?php

use App\Models\SearchBox;
use App\Models\TorrentExtra;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

function get_langfolder_cookie($transToLocale = false)
{
	return \App\Support\Locale::folderFromCookie($_COOKIE["c_lang_folder"] ?? null, (bool) $transToLocale);
}

function get_user_lang($user_id)
{
	return \App\Support\Locale::userFolder($user_id);
}

function get_langfile_path($script_name ="", $target = false, $lang_folder = "")
{
	global $CURLANGDIR;
	$CURLANGDIR = get_langfolder_cookie();
	if($lang_folder == "")
	{
		$lang_folder = $CURLANGDIR;
	}
    return \App\Support\Locale::filePath($lang_folder, (string) $script_name, $_SERVER['SCRIPT_NAME'] ?? '', (bool) $target);
}

function get_row_sum($table, $field, $suffix = "")
{
	return \App\Support\LegacyDb::sum((string) $table, (string) $field, (string) $suffix);
}

function get_single_value($table, $field, $suffix = ""){
	return \App\Support\LegacyDb::singleValue((string) $table, (string) $field, (string) $suffix);
}

function stdmsg($heading, $text, $htmlstrip = false) {
	echo \App\Support\Frame::stdMessage($heading, $text, $htmlstrip);
}

function stderr($heading, $text, $htmlstrip = true, $head = true, $foot = true, $die = true)
{
	\App\Support\LegacyResponse::abort($heading, $text, $htmlstrip, $head, $foot, $die);
}

function sqlerr($file = '', $line = '')
{
	print(\App\Support\Frame::sqlError(mysql_error(), (string) $file, (string) $line));
	die;
}

function format_quotes($s)
{
    return \App\Support\BBCode::quotes((string) $s, (string) nexus_trans("label.text_quote"));
}


function print_attachment($dlkey, $enableimage = true, $imageresizer = true)
{
	$httpdirectory_attachment = get_setting('attachment.httpdirectory');
	if (strlen($dlkey) == 32) {
		if (!$row = \Nexus\Database\NexusDB::cache_get('attachment_'.$dlkey.'_content')) {
			$res = sql_query("SELECT * FROM attachments WHERE dlkey=".sqlesc($dlkey)." LIMIT 1") or sqlerr(__FILE__,__LINE__);
			$row = mysql_fetch_array($res);
			\Nexus\Database\NexusDB::cache_put('attachment_'.$dlkey.'_content', $row, 86400);
		}
	}
	if (!$row) {
		return "<div style=\"text-decoration: line-through; font-size: 7pt\">".nexus_trans('attachment.text_key').$dlkey.nexus_trans('attachment.not_found')."</div>";
	}

	$driver = $row['driver'] ?? 'local';
	if ($driver == "local") {
		if ($row['thumb'] == 1) {
			$url = $httpdirectory_attachment."/".$row['location'].".thumb.jpg";
		} else {
			$url = $httpdirectory_attachment."/".$row['location'];
		}
	} else {
		$url = \Nexus\Attachment\Storage::getDriver($driver)->getImageUrl($row['location']);
	}
	do_log(sprintf("driver: %s, location: %s, url: %s", $driver, $row['location'], $url));

	return \App\Support\Attachment::render(
		$row,
		$dlkey,
		(bool) $enableimage,
		(bool) $imageresizer,
		(string) $url,
		mksize($row['filesize']),
		gettime($row['added']),
		[
			'size' => nexus_trans('attachment.size'),
			'downloads' => nexus_trans('attachment.downloads'),
		]
	);
}
function addTempCode($value) {
	return \App\Support\Comment::addTempCode((string) $value);
}

function formatUrl($url, $newWindow = false, $text = '', $linkClass = '') {
    if (! $text) {
        $text = $url;
    }
    return addTempCode(\App\Support\BBCode::url((string) $url, (bool) $newWindow, (string) $text, (string) $linkClass));
}

function formatCode($text) {
    return addTempCode(\App\Support\BBCode::code((string) $text, (string) nexus_trans("label.text_code")));
}


function formatImg($src, $enableImageResizer, $image_max_width, $image_max_height, $imgId = "") {
    $src = filter_src($src);
    if (empty($src)) {
        return "";
    }
    return addTempCode(\App\Support\BBCode::img((string) $src, (bool) $enableImageResizer, (int) $image_max_width, (int) $image_max_height, (string) $imgId));
}


function formatFlash($src, $width, $height) {
    $src = filter_src($src);
    if (empty($src)) {
        return "";
    }
    return addTempCode(\App\Support\BBCode::flash((string) $src, $width, $height));
}

function formatFlv($src, $width, $height) {
    $src = filter_src($src);
    if (empty($src)) {
        return "";
    }
    return addTempCode(\App\Support\BBCode::flv((string) $src, $width, $height));
}

function formatYoutube($src, $width = '', $height = ''): string
{
    $src = filter_src($src);
    if (empty($src)) {
        return "";
    }
    return addTempCode(\App\Support\BBCode::youtube((string) $src, $width, $height));
}


function formatVideo($src, $width, $height) {
    $src = filter_src($src);
    if (empty($src)) {
        return "";
    }
    return addTempCode(\App\Support\BBCode::video((string) $src, $width, $height));
}


function formatAudio($src) {
    $src = filter_src($src);
    if (empty($src)) {
        return "";
    }
    return addTempCode(\App\Support\BBCode::audio((string) $src));
}


function formatSpoiler($content, $title = '', $defaultCollapsed = true): string
{
    global $lang_functions;
    return addTempCode(\App\Support\BBCode::spoiler((string) $content, (string) $title, (string) ($lang_functions['spoiler_default_title'] ?? ''), (bool) $defaultCollapsed));
}


function formatHidden($content): string
{
    return addTempCode(\App\Support\BBCode::hidden((string) $content));
}


function formatTextAlign($text, $align): string
{
    return addTempCode(\App\Support\BBCode::textAlign((string) $text, (string) $align));
}


function format_urls($text, $newWindow = false)
{
	return \App\Support\BBCode::formatUrls((string) $text, (bool) $newWindow);
}
function format_comment($text, $strip_html = true, $xssclean = false, $newtab = true, $imageresizer = true, $image_max_width = 700, $enableimage = true, $enableflash = true , $imagenum = -1, $image_max_height = 0)
{
	return \App\Support\Comment::format(
		(string) $text,
		(bool) $strip_html,
		(bool) $xssclean,
		(bool) $newtab,
		(bool) $imageresizer,
		(int) $image_max_width,
		(bool) $enableimage,
		(bool) $enableflash,
		(int) $imagenum,
		(int) $image_max_height,
	);
}
function highlight($search,$subject,$hlstart='<b><font class="striking">',$hlend="</font></b>")
{
	return \App\Support\Strings::highlight((string)$search, (string)$subject, $hlstart, $hlend);
}


function get_user_class_name($class, $compact = false, $b_colored = false, $I18N = false, array $options = [])
{
	return \App\Support\UserClass::name($class, $compact, $b_colored, $I18N, $options);
}

function is_valid_user_class($class)
{
	return \App\Support\Validators::isUserClass($class);
}

function int_check($value,$stdhead = false, $stdfood = true, $die = true, $log = true) {
	return \App\Support\LegacyResponse::assertId($value, $stdhead, $stdfood, $die, $log);
}

function is_valid_id($id)
{
	return \App\Support\Validators::isId($id);
}


//-------- Begins a main frame
function begin_main_frame($caption = "", $center = false, $width = 100) {
	echo \App\Support\Frame::mainOpen($caption, $center, $width, CONTENT_WIDTH);
}

function end_main_frame() {
	echo \App\Support\Frame::CLOSE;
}

function begin_frame($caption = "", $center = false, $padding = 10, $width="100%", $caption_center="left") {
	echo \App\Support\Frame::open($caption, $center, $padding, $width, $caption_center);
}

function end_frame() {
	echo \App\Support\Frame::CLOSE;
}

function begin_table($fullwidth = false, $padding = 5) {
	echo \App\Support\Frame::tableOpen($fullwidth, $padding);
}

function end_table() {
	echo \App\Support\Frame::TABLE_CLOSE;
}

//-------- Inserts a smilies frame
//         (move to globals)

function insert_smilies_frame()
{
	global $lang_functions;
	echo \App\Support\Smilies::framedTable($lang_functions['text_smilies'], $lang_functions['col_type_something'], $lang_functions['col_to_make_a']);
}

function get_ratio_color($ratio)
{
	return \App\Support\Ratio::color((float)$ratio);
}

function get_slr_color($ratio)
{
	return \App\Support\Ratio::seedLeechColor((float)$ratio);
}

function write_log($text, $security = "normal")
{
    \App\Support\Log::write((string) $text, (string) $security, get_user_id());
}


function get_elapsed_time($ts,$shortunit = false)
{
	global $lang_functions;
	return \App\Support\Time::elapsedSince((int)$ts, TIMENOW, [
		'year' => $lang_functions['text_year'] ?? '',
		'year_short' => $lang_functions['text_short_year'] ?? '',
		'month' => $lang_functions['text_month'] ?? '',
		'month_short' => $lang_functions['text_short_month'] ?? '',
		'day' => $lang_functions['text_day'] ?? '',
		'day_short' => $lang_functions['text_short_day'] ?? '',
		'hour' => $lang_functions['text_hour'] ?? '',
		'hour_short' => $lang_functions['text_short_hour'] ?? '',
		'min' => $lang_functions['text_min'] ?? '',
		'min_short' => $lang_functions['text_short_min'] ?? '',
		'plural_suffix' => $lang_functions['text_s'] ?? '',
	], (bool)$shortunit);
}

function textbbcode($form,$text,$content="",$hastitle=false, $col_num = 130, $withPreview = false)
{
	global $lang_functions;
	global $subject, $BASEURL, $CURUSER, $enableattach_attachment;
	$editTbodyId = "$form-$text-edit";
	$previewTbodyId = "$form-$text-preview";
	$btnEditId = "$form-$text-btn-edit";
    $btnPreviewId = "$form-$text-btn-preview";
?>

<script type="text/javascript">
    let textareaId = "<?php echo $text?>"
    let editTbodyId = "<?php echo $editTbodyId?>"
    let previewTbodyId = "<?php echo $previewTbodyId?>"
    let btnEditId = "<?php echo $btnEditId?>"
    let btnPreviewId = "<?php echo $btnPreviewId?>"
//<![CDATA[
var b_open = 0;
var i_open = 0;
var u_open = 0;
var color_open = 0;
var list_open = 0;
var quote_open = 0;
var html_open = 0;

var myAgent = navigator.userAgent.toLowerCase();
var myVersion = parseInt(navigator.appVersion);

var is_ie = ((myAgent.indexOf("msie") != -1) && (myAgent.indexOf("opera") == -1));
var is_nav = ((myAgent.indexOf('mozilla')!=-1) && (myAgent.indexOf('spoofer')==-1)
&& (myAgent.indexOf('compatible') == -1) && (myAgent.indexOf('opera')==-1)
&& (myAgent.indexOf('webtv') ==-1) && (myAgent.indexOf('hotjava')==-1));

var is_win = ((myAgent.indexOf("win")!=-1) || (myAgent.indexOf("16bit")!=-1));
var is_mac = (myAgent.indexOf("mac")!=-1);
var bbtags = new Array();
function cstat() {
	var c = stacksize(bbtags);
	if ( (c < 1) || (c == null) ) {c = 0;}
	if ( ! bbtags[0] ) {c = 0;}
	document.<?php echo $form?>.tagcount.value = "Close last, Open "+c;
}
function stacksize(thearray) {
	for (i = 0; i < thearray.length; i++ ) {
		if ( (thearray[i] == "") || (thearray[i] == null) || (thearray == 'undefined') ) {return i;}
	}
	return thearray.length;
}
function pushstack(thearray, newval) {
	arraysize = stacksize(thearray);
	thearray[arraysize] = newval;
}
function popstackd(thearray) {
	arraysize = stacksize(thearray);
	theval = thearray[arraysize - 1];
	return theval;
}
function popstack(thearray) {
	arraysize = stacksize(thearray);
	theval = thearray[arraysize - 1];
	delete thearray[arraysize - 1];
	return theval;
}
function closeall() {
	if (bbtags[0]) {
		while (bbtags[0]) {
			tagRemove = popstack(bbtags)
			if ( (tagRemove != 'color') ) {
				doInsert("[/"+tagRemove+"]", "", false);
				eval("document.<?php echo $form?>." + tagRemove + ".value = ' " + tagRemove.toUpperCase() + " '");
				eval(tagRemove + "_open = 0");
			} else {
				doInsert("[/"+tagRemove+"]", "", false);
			}
			cstat();
			return;
		}
	}
	document.<?php echo $form?>.tagcount.value = "Close last, Open 0";
	bbtags = new Array();
	document.<?php echo $form?>.<?php echo $text?>.focus();
}
function add_code(NewCode) {
	document.<?php echo $form?>.<?php echo $text?>.value += NewCode;
	document.<?php echo $form?>.<?php echo $text?>.focus();
}
function alterfont(theval, thetag) {
	if (theval == 0) return;
	if(doInsert("[" + thetag + "=" + theval + "]", "[/" + thetag + "]", true)) pushstack(bbtags, thetag);
	document.<?php echo $form?>.color.selectedIndex = 0;
	cstat();
}

function tag_url(PromptURL, PromptTitle, PromptError) {
	var FoundErrors = '';
	var enterURL = prompt(PromptURL, "http://");
	var enterTITLE = prompt(PromptTitle, "");
	if (!enterURL || enterURL=="") {FoundErrors += " " + PromptURL + ",";}
	if (!enterTITLE) {FoundErrors += " " + PromptTitle;}
	if (FoundErrors) {alert(PromptError+FoundErrors);return;}
	doInsert("[url="+enterURL+"]"+enterTITLE+"[/url]", "", false);
}

function tag_list(PromptEnterItem, PromptError) {
	var FoundErrors = '';
	var enterTITLE = prompt(PromptEnterItem, "");
	if (!enterTITLE) {FoundErrors += " " + PromptEnterItem;}
	if (FoundErrors) {alert(PromptError+FoundErrors);return;}
	doInsert("[*]"+enterTITLE+"", "", false);
}

function tag_image(PromptImageURL, PromptError) {
	var FoundErrors = '';
	var enterURL = prompt(PromptImageURL, "http://");
	if (!enterURL || enterURL=="http://") {
		alert(PromptError+PromptImageURL);
		return;
	}
	doInsert("[img]"+enterURL+"[/img]", "", false);
}

function tag_extimage(content) {
	doInsert(content, "", false);
}

function tag_email(PromptEmail, PromptError) {
	var emailAddress = prompt(PromptEmail, "");
	if (!emailAddress) {
		alert(PromptError+PromptEmail);
		return;
	}
	doInsert("[email]"+emailAddress+"[/email]", "", false);
}

function doInsert(ibTag, ibClsTag, isSingle)
{
	var isClose = false;
	var obj_ta = document.<?php echo $form?>.<?php echo $text?>;
	if ( (myVersion >= 4) && is_ie && is_win)
	{
		if(obj_ta.isTextEdit)
		{
			obj_ta.focus();
			var sel = document.selection;
			var rng = sel.createRange();
			rng.colapse;
			if((sel.type == "Text" || sel.type == "None") && rng != null)
			{
				if(ibClsTag != "" && rng.text.length > 0)
				ibTag += rng.text + ibClsTag;
				else if(isSingle) isClose = true;
				rng.text = ibTag;
			}
		}
		else
		{
			if(isSingle) isClose = true;
			obj_ta.value += ibTag;
		}
	}
	else if (obj_ta.selectionStart || obj_ta.selectionStart == '0')
	{
		var startPos = obj_ta.selectionStart;
		var endPos = obj_ta.selectionEnd;
		obj_ta.value = obj_ta.value.substring(0, startPos) + ibTag + obj_ta.value.substring(endPos, obj_ta.value.length);
		obj_ta.selectionEnd = startPos + ibTag.length;
		if(isSingle) isClose = true;
	}
	else
	{
		if(isSingle) isClose = true;
		obj_ta.value += ibTag;
	}
	obj_ta.focus();
	// obj_ta.value = obj_ta.value.replace(/ /, " ");
	return isClose;
}

function clearContent()
{
    document.<?php echo $form?>.<?php echo $text?>.value = '';
}

function winop()
{
	windop = window.open("moresmilies.php?form=<?php echo $form?>&text=<?php echo $text?>","mywin","height=500,width=500,resizable=no,scrollbars=yes");
}

function simpletag(thetag)
{
	var tagOpen = eval(thetag + "_open");
	if (tagOpen == 0) {
		if(doInsert("[" + thetag + "]", "[/" + thetag + "]", true))
		{
			eval(thetag + "_open = 1");
			eval("document.<?php echo $form?>." + thetag + ".value += '*'");
			pushstack(bbtags, thetag);
			cstat();
		}
	}
	else {
		lastindex = 0;
		for (i = 0; i < bbtags.length; i++ ) {
			if ( bbtags[i] == thetag ) {
				lastindex = i;
			}
		}

		while (bbtags[lastindex]) {
			tagRemove = popstack(bbtags);
			doInsert("[/" + tagRemove + "]", "", false)
			if ((tagRemove != 'COLOR') ){
				eval("document.<?php echo $form?>." + tagRemove + ".value = '" + tagRemove.toUpperCase() + "'");
				eval(tagRemove + "_open = 0");
			}
		}
		cstat();
	}
}

function textBBCodePreview() {
    let poststr = encodeURIComponent( document.getElementById(textareaId).value );
    let result=ajax.posts('preview.php','body='+poststr);
    jQuery('#' + editTbodyId).hide()
    jQuery('#' + previewTbodyId).html(result).show()
    jQuery('#' + btnPreviewId).hide()
    jQuery('#' + btnEditId).show()
}
function textBBCodeEdit() {
    jQuery('#' + editTbodyId).show()
    jQuery('#' + previewTbodyId).hide()
    jQuery('#' + btnPreviewId).show()
    jQuery('#' + btnEditId).hide()
}
//]]>
</script>
<table width="100%" cellspacing="0" cellpadding="5" border="0">
    <tbody id="<?php echo $editTbodyId?>">
<tr><td align="left" colspan="2">
<table cellspacing="1" cellpadding="2" border="0">
<tr>
<td class="embedded"><input style="font-weight: bold;font-size:11px; margin-right:3px" type="button" name="b" value="B" onclick="javascript: simpletag('b')" /></td>
<td class="embedded"><input class="codebuttons" style="font-style: italic;font-size:11px;margin-right:3px" type="button" name="i" value="I" onclick="javascript: simpletag('i')" /></td>
<td class="embedded"><input class="codebuttons" style="text-decoration: underline;font-size:11px;margin-right:3px" type="button" name="u" value="U" onclick="javascript: simpletag('u')" /></td>
<?php
print("<td class=\"embedded\"><input class=\"codebuttons\" style=\"font-size:11px;margin-right:3px\" type=\"button\" name='url' value='URL' onclick=\"javascript:tag_url('" . $lang_functions['js_prompt_enter_url'] . "','" . $lang_functions['js_prompt_enter_title'] . "','" . $lang_functions['js_prompt_error'] . "')\" /></td>");
print("<td class=\"embedded\"><input class=\"codebuttons\" style=\"font-size:11px;margin-right:3px\" type=\"button\" name=\"IMG\" value=\"IMG\" onclick=\"javascript: tag_image('" . $lang_functions['js_prompt_enter_image_url'] . "','" . $lang_functions['js_prompt_error'] . "')\" /></td>");
print("<td class=\"embedded\"><input type=\"button\" style=\"font-size:11px;margin-right:3px\" name=\"list\" value=\"List\" onclick=\"tag_list('" . addslashes($lang_functions['js_prompt_enter_item']) . "','" . $lang_functions['js_prompt_error'] . "')\" /></td>");
?>
<td class="embedded"><input class="codebuttons" style="font-size:11px;margin-right:3px" type="button" name="quote" value="QUOTE" onclick="javascript: simpletag('quote')" /></td>
<td class="embedded"><input style="font-size:11px;margin-right:3px" type="button" onclick='javascript:closeall();' name='tagcount' value="Close all tags" /></td>
<td class="embedded"><select class="med codebuttons" style="margin-right:3px" name='color' onchange="alterfont(this.options[this.selectedIndex].value, 'color')">
<option value='0'>--- <?php echo $lang_functions['select_color'] ?> ---</option>
<option style="background-color: black" value="Black">Black</option>
<option style="background-color: sienna" value="Sienna">Sienna</option>
<option style="background-color: darkolivegreen" value="DarkOliveGreen">Dark Olive Green</option>
<option style="background-color: darkgreen" value="DarkGreen">Dark Green</option>
<option style="background-color: darkslateblue" value="DarkSlateBlue">Dark Slate Blue</option>
<option style="background-color: navy" value="Navy">Navy</option>
<option style="background-color: indigo" value="Indigo">Indigo</option>
<option style="background-color: darkslategray" value="DarkSlateGray">Dark Slate Gray</option>
<option style="background-color: darkred" value="DarkRed">Dark Red</option>
<option style="background-color: darkorange" value="DarkOrange">Dark Orange</option>
<option style="background-color: olive" value="Olive">Olive</option>
<option style="background-color: green" value="Green">Green</option>
<option style="background-color: teal" value="Teal">Teal</option>
<option style="background-color: blue" value="Blue">Blue</option>
<option style="background-color: slategray" value="SlateGray">Slate Gray</option>
<option style="background-color: dimgray" value="DimGray">Dim Gray</option>
<option style="background-color: red" value="Red">Red</option>
<option style="background-color: sandybrown" value="SandyBrown">Sandy Brown</option>
<option style="background-color: yellowgreen" value="YellowGreen">Yellow Green</option>
<option style="background-color: seagreen" value="SeaGreen">Sea Green</option>
<option style="background-color: mediumturquoise" value="MediumTurquoise">Medium Turquoise</option>
<option style="background-color: royalblue" value="RoyalBlue">Royal Blue</option>
<option style="background-color: purple" value="Purple">Purple</option>
<option style="background-color: gray" value="Gray">Gray</option>
<option style="background-color: magenta" value="Magenta">Magenta</option>
<option style="background-color: orange" value="Orange">Orange</option>
<option style="background-color: yellow" value="Yellow">Yellow</option>
<option style="background-color: lime" value="Lime">Lime</option>
<option style="background-color: cyan" value="Cyan">Cyan</option>
<option style="background-color: deepskyblue" value="DeepSkyBlue">Deep Sky Blue</option>
<option style="background-color: darkorchid" value="DarkOrchid">Dark Orchid</option>
<option style="background-color: silver" value="Silver">Silver</option>
<option style="background-color: pink" value="Pink">Pink</option>
<option style="background-color: wheat" value="Wheat">Wheat</option>
<option style="background-color: lemonchiffon" value="LemonChiffon">Lemon Chiffon</option>
<option style="background-color: palegreen" value="PaleGreen">Pale Green</option>
<option style="background-color: paleturquoise" value="PaleTurquoise">Pale Turquoise</option>
<option style="background-color: lightblue" value="LightBlue">Light Blue</option>
<option style="background-color: plum" value="Plum">Plum</option>
<option style="background-color: white" value="White">White</option>
</select></td>
<td class="embedded">
<select class="med codebuttons" name='font' onchange="alterfont(this.options[this.selectedIndex].value, 'font')">
<option value="0">--- <?php echo $lang_functions['select_font'] ?> ---</option>
<option value="Arial">Arial</option>
<option value="Arial Black">Arial Black</option>
<option value="Arial Narrow">Arial Narrow</option>
<option value="Book Antiqua">Book Antiqua</option>
<option value="Century Gothic">Century Gothic</option>
<option value="Comic Sans MS">Comic Sans MS</option>
<option value="Courier New">Courier New</option>
<option value="Fixedsys">Fixedsys</option>
<option value="Garamond">Garamond</option>
<option value="Georgia">Georgia</option>
<option value="Impact">Impact</option>
<option value="Lucida Console">Lucida Console</option>
<option value="Lucida Sans Unicode">Lucida Sans Unicode</option>
<option value="Microsoft Sans Serif">Microsoft Sans Serif</option>
<option value="Palatino Linotype">Palatino Linotype</option>
<option value="System">System</option>
<option value="Tahoma">Tahoma</option>
<option value="Times New Roman">Times New Roman</option>
<option value="Trebuchet MS">Trebuchet MS</option>
<option value="Verdana">Verdana</option>
</select>
</td>
<td class="embedded">
<select class="med codebuttons" name='size' onchange="alterfont(this.options[this.selectedIndex].value, 'size')">
<option value="0">--- <?php echo $lang_functions['select_size'] ?> ---</option>
<option value="1">1</option>
<option value="2">2</option>
<option value="3">3</option>
<option value="4">4</option>
<option value="5">5</option>
<option value="6">6</option>
<option value="7">7</option>
</select></td></tr>
</table>
</td>
</tr>
<?php
if ($enableattach_attachment == 'yes'){
?>
<tr>
<td colspan="2" valign="middle">
<iframe src="<?php echo getSchemeAndHttpHost()?>/attachment.php" width="100%" height="24" frameborder="0" scrolling="no" marginheight="0" marginwidth="0"></iframe>
</td>
</tr>
<?php
}
print("<tr>");
print("<td align=\"left\"><textarea class=\"bbcode\" cols=\"100\" style=\"width: 100%;\" name=\"".$text."\" id=\"".$text."\" rows=\"20\" onkeydown=\"ctrlenter(event,'compose','qr')\">".$content."</textarea>");
?>
</td>
<td align="center" width="">
<table cellspacing="1" cellpadding="3">
<tr>
<?php
$i = 0;
$quickSmilies = array(1, 2, 3, 5, 6, 7, 8, 9, 10, 11, 13, 16, 17, 19, 20, 21, 24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 34, 35, 36, 39, 40, 41);
foreach ($quickSmilies as $smily) {
	if ($i%4 == 0 && $i > 0) {
		print('</tr><tr>');
	}
	print("<td class=\"embedded\" style=\"padding: 3px;\">".getSmileIt($form, $text, $smily)."</td>");
	$i++;
}
?>
</tr></table>
<br />
<a href="javascript:winop();"><?php echo $lang_functions['text_more_smilies'] ?></a>
</td></tr></tobdy>
    <?php if($withPreview) {?>
    <tbody id="<?php echo $previewTbodyId?>"></tbody>
    <tbody>
        <tr><td colspan="2" style="text-align: center;border: none">
            <input id="<?php echo $btnPreviewId ?>" type="button" class="btn" value="<?php echo $lang_functions['submit_preview']?>" onclick="javascript:textBBCodePreview()">
            <input id="<?php echo $btnEditId ?>" type="button" class="btn" style="display: none" value="<?php echo $lang_functions['submit_edit']?>" onclick="javascript:textBBCodeEdit()">
        </td></tr>
    </tbody>
    <?php }?>
</table>
<?php
}

function begin_compose($title = "", $type = "new", $body = "", $hassubject = true, $subject = "", $maxsubjectlength = 100)
{
	global $lang_functions;
	print(\App\Support\Frame::composeOpen((string) $title, (string) $type, (bool) $hassubject, (string) $subject, (int) $maxsubjectlength, (array) $lang_functions));
	textbbcode("compose", "body", $body, false);
}

function end_compose()
{
	global $lang_functions;
	print(\App\Support\Frame::composeClose((array) $lang_functions));
}

function insert_suggest($keyword, $userid, $pre_escaped = true)
{
	\App\Support\SearchSuggest::add((string) $keyword, $userid, (bool) $pre_escaped);
}


// it's a stub implemetation here, we need more acurate regression analysis to complete our algorithm
function get_torrent_2_user_value($user_snatched_arr)
{
	return \App\Support\TorrentOps::userValue((array) $user_snatched_arr);
}

function cur_user_check()
{
	\App\Support\LegacyAuth::currentUserCheck();
}

function KPS($type = "+", $point = "1.0", $id = "")
{
	global $bonus_tweak;
	\App\Support\Bonus::updatePoints((string) $type, (float) $point, $id, (string) $bonus_tweak);
}

function get_agent($peer_id, $agent)
{
	return \App\Support\Strings::userAgentClient((string)$agent);
}

function EmailBanned($newEmail)
{
	$newEmail = trim(strtolower((string) $newEmail));
	return \App\Support\Email::matchesRegexList($newEmail, \App\Support\EmailDomain::banned());
}

function EmailAllowed($newEmail)
{
	global $restrictemaildomain;
	if ($restrictemaildomain != 'yes') {
		return true;
	}
	$newEmail = trim(strtolower((string) $newEmail));
	return \App\Support\Email::matchesRegexList($newEmail, \App\Support\EmailDomain::allowed());
}

function allowedemails()
{
	return \App\Support\EmailDomain::allowed();
}

function nexus_redirect($url)
{
    \App\Support\LegacyResponse::redirect((string) $url);
}

function set_cachetimestamp($id, $field = "cache_stamp")
{
	\App\Support\Cache::touchTorrent($id, $field);
}
function reset_cachetimestamp($id, $field = "cache_stamp")
{
	\App\Support\Cache::resetTorrent($id, $field);
}

function cache_check ($file = 'cachefile',$endpage = true, $cachetime = 600) {
	global $lang_functions, $rootpath, $cache, $CURLANGDIR;
	$cachefile = \App\Support\Cache::path($rootpath, $cache, $CURLANGDIR, $file);
	if (\App\Support\Cache::isFresh($cachefile, $cachetime)) {
		include($cachefile);
		if ($endpage) {
			echo "<p align=\"center\"><font class=\"small\">" . $lang_functions['text_page_last_updated'] . date('Y-m-d H:i:s', filemtime($cachefile)) . "</font></p>";
			end_main_frame();
			stdfoot();
			exit;
		}
		return false;
	}
	ob_start();
	return true;
}

function cache_save  ($file = 'cachefile') {
	global $rootpath, $cache, $CURLANGDIR;
	$cachefile = \App\Support\Cache::path($rootpath, $cache, $CURLANGDIR, $file);
	\App\Support\Cache::writeBuffer($cachefile, ob_get_contents());
	ob_end_flush();
}

function get_email_encode($lang)
{
	return \App\Support\Email::charsetFor((string) $lang);
}

function change_email_encode($lang, $content)
{
	return \App\Support\Email::convertCharset((string) $lang, (string) $content);
}

function safe_email($email) {
	return \App\Support\Email::sanitizeForDisplay((string) $email);
}

function check_email ($email) {
	if (!\App\Support\Email::isWellFormed((string) $email)) {
		return false;
	}
	$bannedEmails = \Nexus\Database\NexusDB::select('select * from bannedemails');
	$bannedValue = $bannedEmails[0]['value'] ?? '';
	if (\App\Support\Email::matchesSuffixList((string) $email, (string) $bannedValue)) {
		$bannedEmailsArr = array_filter(preg_split('/[\s]+/', $bannedValue));
		foreach ($bannedEmailsArr as $ban) {
			if (str_ends_with((string) $email, (string) $ban)) {
				do_log("[BANNED_EMAIL] email: $email is banned by record: $ban");
				break;
			}
		}
		return false;
	}
	return true;
}

function sent_mail($to,$fromname,$fromemail,$subject,$body,$type = "confirmation",$showmsg=true,$multiple=false,$multiplemail='',$hdr_encoding = 'UTF-8', $specialcase = '') {
	global $lang_functions, $SITENAME, $SITEEMAIL, $smtptype, $smtp, $smtp_host, $smtp_port, $smtp_from;
	return \App\Support\Mail::sent(
		(string) $to,
		(string) $fromname,
		(string) $fromemail,
		(string) $subject,
		(string) $body,
		(string) $type,
		(bool) $showmsg,
		(bool) $multiple,
		(string) (is_array($multiplemail) ? implode(',', $multiplemail) : $multiplemail),
		(string) $hdr_encoding,
		[
			'site_name' => (string) $SITENAME,
			'site_email' => (string) $SITEEMAIL,
			'smtp_type' => (string) $smtptype,
			'smtp' => (string) $smtp,
			'smtp_host' => (string) $smtp_host,
			'smtp_port' => (string) $smtp_port,
			'smtp_from' => (string) $smtp_from,
		],
		[
			'error' => $lang_functions['std_error'] ?? 'Error',
			'success' => $lang_functions['std_success'] ?? 'Success',
			'unable_to_send_mail' => $lang_functions['text_unable_to_send_mail'] ?? 'Unable to send mail',
			'confirmation_email_sent' => $lang_functions['std_confirmation_email_sent'] ?? 'Confirmation email sent to ',
			'account_details_sent' => $lang_functions['std_account_details_sent'] ?? 'Account details sent to ',
			'please_wait' => $lang_functions['std_please_wait'] ?? 'Please wait...',
		]
	);
}

function failedloginscheck ($type = 'Login') {
    \App\Support\LegacyAuth::failedLoginsCheck((string) $type);
}

function failedlogins ($type = 'login', $recover = false, $head = true)
{
    \App\Support\LegacyAuth::failedLogins((string) $type, (bool) $recover, (bool) $head);
}


function login_failedlogins($type = 'login', $recover = false, $head = true)
{
    \App\Support\LegacyAuth::loginFailedLogins((string) $type, (bool) $recover, (bool) $head);
}


function remaining($type = 'login')
{
	global $maxloginattempts;
	return \App\Support\LegacyAuth::remainingAttempts((string) $type, (int) $maxloginattempts, \getip());
}

function registration_check($type = "invitesystem", $maxuserscheck = true, $ipcheck = true) {
    return \App\Support\LegacyAuth::registrationCheck((string) $type, (bool) $maxuserscheck, (bool) $ipcheck);
}


function random_str($length="6")
{
	return \App\Support\Strings::randomCode((int)$length);
}
function captcha_manager(): \App\Services\Captcha\CaptchaManager
{
    return \App\Support\Captcha::manager();
}

function image_code () {
    return \App\Support\Captcha::imageCode();
}

function check_code ($imagehash, $imagestring, $where = 'signup.php', $maxattemptlog = false, $head = true) {
    return \App\Support\LegacyAuth::checkCode((string) $imagehash, (string) $imagestring, (string) $where, (bool) $maxattemptlog, (bool) $head);
}


function show_image_code () {
    global $lang_functions, $iv;
    \App\Support\Captcha::render((string) $iv, [
        'row_security_image' => $lang_functions['row_security_image'] ?? '',
        'row_security_challenge' => $lang_functions['row_security_challenge'] ?? '',
        'row_security_code' => $lang_functions['row_security_code'] ?? '',
    ]);
}

function get_ip_location($ip)
{
	global $lang_functions;

	static $locations;
	if (isset($locations[$ip])) {
		return $locations[$ip];
	}

	$geoName = get_ip_location_from_geoip($ip)['name'] ?? null;
	$result = \App\Support\Network::ipLocationLabels(
		$geoName,
		$ip,
		$lang_functions['text_unknown'] ?? '',
		$lang_functions['text_user_ip'] ?? 'User IP'
	);

	return $locations[$ip] = $result;
}

function in_ip_range($long, $targetip, $ip_one, $ip_two=false) {
	return \App\Support\Network::ipInRange($long, $targetip, $ip_one, $ip_two);
}


function validip_format($ip)
{
	return \App\Support\Network::isValidIpv4Format((string) $ip);
}

function maxslots () {
	global $lang_functions, $CURUSER, $maxdlsystem;
	$max = \App\Support\Slots::maxDownloadSlots((float) $CURUSER["uploaded"], (float) $CURUSER["downloaded"]);
	if ($maxdlsystem == "yes") {
		if (get_user_class() < UC_VIP) {
			if ($max > 0)
				print ("<font class='color_slots'>".$lang_functions['text_slots']."</font><a href='faq.php#id215'>$max</a>");
			else
				print ("<font class='color_slots'>".$lang_functions['text_slots']."</font>".$lang_functions['text_unlimited']);
		} else {
			print ("<font class='color_slots'>".$lang_functions['text_slots']."</font>".$lang_functions['text_unlimited']);
		}
	} else {
		print ("<font class='color_slots'>".$lang_functions['text_slots']."</font>".$lang_functions['text_unlimited']);
	}
}


function dbconn($autoclean = false, $doLogin = true)
{
    global $useCronTriggerCleanUp;
    \Nexus\Database\NexusDB::getInstance()->autoConnect();
	if ($doLogin) {
        userlogin();
    }
	if (!$useCronTriggerCleanUp && $autoclean) {
		register_shutdown_function("autoclean");
	}
}

function userlogin() {
    static $loginResult;
    if (!is_null($loginResult)) {
        return $loginResult;
    }
	global $lang_functions;
	global $Cache;
	global $SITE_ONLINE, $oldip;
	global $enablesqldebug_tweak, $sqldebug_tweak;
	unset($GLOBALS["CURUSER"]);

	$ip = getip();
	$nip = ip2long($ip);
	if ($nip) //$nip would be false for IPv6 address
	{
		$res = sql_query("SELECT * FROM bans WHERE first <= $nip AND last >= $nip") or sqlerr(__FILE__, __LINE__);
        if (mysql_num_rows($res) > 0)
		{
			header("HTTP/1.1 403 Forbidden");
			print("<html><head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\"></head><body>".$lang_functions['text_unauthorized_ip']."</body></html>\n");
			die;
		}
	}

	$row = get_user_from_cookie($_COOKIE);
    if (empty($row)) {
        return $loginResult = false;
    }
	if (!$row["passkey"]){
		$passkey = md5($row['username'].date("Y-m-d H:i:s").$row['passhash']);
		sql_query("UPDATE users SET passkey = ".sqlesc($passkey)." WHERE id=" . sqlesc($row["id"]));
	}

	$oldip = $row['ip'];
	$row['ip'] = $ip;
    $row['seedbonus'] = floatval($row['seedbonus']);
	$GLOBALS["CURUSER"] = $row;
	if (isset($_GET['clearcache']) && $_GET['clearcache'] && get_user_class() >= UC_MODERATOR) {
	    $Cache->setClearCache(1);
	}
    /**
     * no need any more, already set in core.php
     * @since v1.6
     */
//	if ($enablesqldebug_tweak == 'yes' && get_user_class() >= $sqldebug_tweak) {
//		error_reporting(E_ALL & ~E_NOTICE);
//		error_reporting(-1);
//	}
    return $loginResult = true;
}

function autoclean($printProgress = false) {
	global $autoclean_interval_one, $rootpath;
	$now = TIMENOW;
	$res = sql_query("SELECT value_u FROM avps WHERE arg = 'lastcleantime'");
	$row = mysql_fetch_array($res);
	if (!$row) {
	    do_log("SELECT value_u FROM avps WHERE arg = 'lastcleantime', empty");
		sql_query("INSERT INTO avps (arg, value_u) VALUES ('lastcleantime',$now)") or sqlerr(__FILE__, __LINE__);
		return false;
	}
	$ts = $row[0];
	if ($ts + $autoclean_interval_one > $now) {
	    do_log("ts: {$ts} + autoclean_interval_one: $autoclean_interval_one > now: $now");
		return false;
	}
	sql_query("UPDATE avps SET value_u=$now WHERE arg='lastcleantime' AND value_u = $ts") or sqlerr(__FILE__, __LINE__);
	if (!mysql_affected_rows()) {
	    do_log("UPDATE avps SET value_u=$now WHERE arg='lastcleantime' AND value_u = $ts, affectedRows = 0");
		return false;
	}
	require_once($rootpath . 'include/cleanup.php');
	return docleanup(0, $printProgress);
}

function getsize_int($amount, $unit = "G")
{
	return \App\Support\Format::bytesFromUnit((float)$amount, $unit);
}

function mksize_compact($bytes)
{
	return \App\Support\Format::sizeCompact((float)$bytes);
}

function mksize_loose($bytes)
{
	return \App\Support\Format::sizeLoose((float)$bytes);
}

function mksize($bytes)
{
	return \App\Support\Format::size((float)$bytes);
}


function mksizeint($bytes)
{
	return \App\Support\Format::sizeInt((float)$bytes);
}

function deadtime() {
	return \App\Support\Time::deadThreshold((int)get_setting("main.anninterthree"));
}

function mkprettytime($s) {
	global $lang_functions;
	return \App\Support\Format::prettyTime((float)$s, $lang_functions['text_day'] ?? 'day(s)');
}

function mkglobal($vars) {
	return \App\Support\Input::globalize($vars, $_GET, $_POST);
}

function unesc($x) {
	return \App\Support\Input::unescape($x);
}

function tr($x,$y,$noesc=0,$relation='', $return = false) {
	$result = \App\Support\Html::settingsRow($x, $y, !$noesc, $relation);
	if ($return) {
		return $result;
	}
	print $result;
}

function tr_small($x,$y,$noesc=0,$relation='',$return = false) {
	$result = \App\Support\Html::settingsRowSmall($x, $y, !$noesc, $relation);
	if ($return) {
		return $result;
	}
	print($result);
}

function twotd($x,$y,$nosec=0){
	echo \App\Support\Html::settingsCells($x, $y);
}

function validfilename($name) {
	return \App\Support\Validators::isUploadFilename($name);
}

function validemail($email) {
    return \App\Support\Validators::isEmail($email);
}

function validlang($langid) {
	global $deflang;
	return \App\Support\Locale::folderForId($langid, (string) $deflang);
}

function get_if_restricted_is_open()
{
	return \App\Support\Time::isWeekendUploadOpen(\App\Models\Setting::getIsUploadOpenAtWeekend(), time());
}

function menu ($selected = "home") {
	global $lang_functions, $CURUSER, $enableoffer, $enablespecial, $where_tweak, $USERUPDATESET;
	$result = \App\Support\Menu::render(
		$_SERVER['SCRIPT_NAME'] ?? '',
		(array) $lang_functions,
		(string) $enableoffer,
		(string) $enablespecial,
		(string) apply_filter('nexus_menu'),
	);
	echo $result['html'];
	if ($CURUSER && $where_tweak == 'yes') {
		$USERUPDATESET[] = "page = ".sqlesc($result['selected']);
	}
}
function get_css_row() {
	global $CURUSER, $defcss, $Cache;
	return \App\Support\Style::cssRow($Cache, $CURUSER ? $CURUSER["stylesheet"] : $defcss, $defcss);
}
function get_css_uri($file = "")
{
    global $defcss, $Cache, $CURUSER;
	return \App\Support\Style::cssUri($Cache, $CURUSER ? $CURUSER["stylesheet"] : $defcss, $defcss, (string) $file);
}

function get_font_css_uri(){
	global $CURUSER;
	return \App\Support\Style::fontCssUri($CURUSER['fontsize'] ?? null);
}

function get_style_addicode()
{
	global $defcss, $Cache, $CURUSER;
	return \App\Support\Style::addiCode($Cache, $CURUSER ? $CURUSER["stylesheet"] : $defcss, $defcss);
}

function get_cat_folder($cat = 101)
{
	static $catPath = array();
	if (!isset($catPath[$cat])) {
		global $CURUSER, $CURLANGDIR;
        $catrow = get_category_row($cat);
		$catmode = $catrow['catmodename'];
		$caticonrow = get_category_icon_row($catrow['icon_id'] ?: 1);
		$catPath[$cat] = \App\Support\Path::categoryFolder(
			$catmode,
			$caticonrow['folder'],
			($caticonrow['multilang'] ?? '') == 'yes',
			$CURLANGDIR
		);
	}
	return $catPath[$cat];
}

function get_style_highlight()
{
	global $CURUSER;
	return \App\Support\Style::highlightColor($CURUSER ? (int) $CURUSER["stylesheet"] : null);
}

function stdhead($title = "", $msgalert = true, $script = "", $place = "")
{
    \App\Support\PageLayout::header($title, $msgalert, $script, $place);
}


function stdfoot()
{
    \App\Support\PageLayout::footer();
}


function genbark($x,$y) {
	\App\Support\LegacyResponse::bark((string) $y, (string) $x);
}

function mksecret($len = 20) {
	return \App\Support\Token::randomHex((int) $len);
}

function httperr($code = 404) {
	\App\Support\LegacyResponse::notFound();
}

function logincookie($id, $authKey, $duration = 0)
{
    \App\Support\AuthCookie::setLoginCookie((int) $id, (string) $authKey, (int) $duration);
}

function set_langfolder_cookie($folder, $expires = 0x7fffffff)
{
	\App\Support\Locale::setFolderCookie((string) $folder, (int) $expires);
}

function get_protocol_prefix() {
	return \App\Support\Http::protocolPrefix(isHttps());
}

function get_langid_from_langcookie($lang = '')
{
    if (empty($lang)) {
        $lang = get_langfolder_cookie();
    }
    return \App\Support\Locale::idFromFolder((string) $lang);
}

function make_folder($pre, $folder_name)
{
	$path = \App\Support\Path::makeFolder($pre, $folder_name, ROOT_PATH);
	do_log($path);
	return $path;
}

/**
 * Resize a cover image (remote URL or local path) to fit within $maxWidth x $maxHeight
 * and persist the JPEG thumbnail under "<attachments>/covers/".
 *
 * For remote URLs the download/resizing is dispatched to a queue job so that the
 * homepage render is never blocked on a slow or unreachable cover host. While the
 * thumbnail is being generated the original $url is returned, so the caller can
 * still render something. When the cached thumbnail exists it is returned instead.
 *
 * @param string $url        Cover image URL (http/https) or local path
 * @param int    $maxWidth   Maximum thumbnail width in pixels
 * @param int    $maxHeight  Maximum thumbnail height in pixels
 * @param int    $quality    JPEG quality (1-100)
 *
 * @return string Public URL of the cached thumbnail, or original $url on failure
 */
function cover_thumb_url($url, $maxWidth = 240, $maxHeight = 360, $quality = 82)
{
	global $savedirectory_attachment, $httpdirectory_attachment, $Cache;
	return \App\Support\CoverThumb::url(
		(string) $url,
		(int) $maxWidth,
		(int) $maxHeight,
		(int) $quality,
		(string) ($savedirectory_attachment ?: 'attachments'),
		(string) ($httpdirectory_attachment ?: 'attachments'),
		ROOT_PATH,
		$Cache ?? null,
	);
}
function logoutcookie() {
	\App\Support\AuthCookie::clear();
}

function base64 ($string, $encode=true) {
	return $encode ? \App\Support\Codec::base64Encode((string) $string) : \App\Support\Codec::base64Decode((string) $string);
}

function loggedinorreturn($mainpage = false) {
	\App\Support\LegacyAuth::requireLogin((bool) $mainpage);
}

function deletetorrent($id, $notify = false) {
    \App\Support\TorrentOps::deleteTorrents($id, (bool) $notify);
}

function pager($rpp, $count, $href, $opts = array(), $pagename = "page") {
	global $lang_functions, $add_key_shortcut;

	$pages = (int) ceil($count / $rpp);
	$rawPage = $_GET[$pagename] ?? null;
	if (!is_scalar($rawPage)) {
		$rawPage = null;
	}
	$page = \App\Support\Pagination::resolvePage(
		$rawPage,
		(int) $count,
		(int) $rpp,
		!empty($opts['lastpagedefault']),
	);

	$isPresto = isset($_SERVER['HTTP_USER_AGENT']) && str_contains($_SERVER['HTTP_USER_AGENT'], 'Presto');
	$labels = [
		'prev' => $lang_functions['text_prev'] ?? '',
		'next' => $lang_functions['text_next'] ?? '',
		'alt_prev_title' => $lang_functions['text_alt_pageup_shortcut'] ?? '',
		'alt_next_title' => $lang_functions['text_alt_pagedown_shortcut'] ?? '',
		'shift_prev_title' => $lang_functions['text_shift_pageup_shortcut'] ?? '',
		'shift_next_title' => $lang_functions['text_shift_pagedown_shortcut'] ?? '',
	];

	$result = \App\Support\Pagination::render(
		(int) $rpp,
		(int) $count,
		(string) $href,
		$page,
		$pages,
		$labels,
		(string) $pagename,
		$isPresto,
	);

	$add_key_shortcut = key_shortcut((int) $page, (int) ($pages - 1));

	return $result;
}

function commenttable($rows, $type, $parent_id, $review = false)
{
	global $lang_functions;
	global $CURUSER, $commanage_class;
	begin_main_frame();
	begin_frame();

	$uidArr = array_unique(array_column($rows, 'user'));
    $neededColumns = array('id', 'class', 'enabled', 'privacy', 'avatar', 'signature', 'uploaded', 'downloaded', 'last_access', 'username', 'donor', 'leechwarn', 'warned', 'title');
	$userInfoArr = \App\Models\User::query()->find($uidArr, $neededColumns)->keyBy('id');

	foreach ($rows as $row)
	{
//		$userRow = get_user_row($row['user']);
        $userInfo = $userInfoArr->get($row['user'], \App\Models\User::defaultUser());
		$userRow = $userInfo->toArray();
		print("<div style=\"margin-top: 8pt; margin-bottom: 8pt;\"><table id=\"cid".$row["id"]."\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" width=\"100%\"><tr><td class=\"embedded\" width=\"99%\">#" . $row["id"] . "&nbsp;&nbsp;<font color=\"gray\">".$lang_functions['text_by']."</font>");
		print(get_username($row["user"],false,true,true,false,false,true));
		print("&nbsp;&nbsp;<font color=\"gray\">".$lang_functions['text_at']."</font>".gettime($row["added"]).
		($row["editedby"] && user_can('commanage') ? " - [<a href=\"comment.php?action=vieworiginal&amp;cid=".$row['id']."&amp;type=".$type."\">".$lang_functions['text_view_original']."</a>]" : "") . "</td><td class=\"embedded nowrap\" width=\"1%\"><a href=\"#top\"><img class=\"top\" src=\"pic/trans.gif\" alt=\"Top\" title=\"Top\" /></a>&nbsp;&nbsp;</td></tr></table></div>");
		$avatar = ($CURUSER["avatars"] == "yes" ? htmlspecialchars(trim($userRow["avatar"])) : "");
		if (!$avatar)
			$avatar = "pic/default_avatar.png";
		$text = format_comment($row["text"]);
		$text_editby = "";
		if ($row["editedby"]){
			$lastedittime = gettime($row['editdate'],true,false);
			$text_editby = "<br /><p><font class=\"small\">".$lang_functions['text_last_edited_by'].get_username($row['editedby']).$lang_functions['text_edited_at'].$lastedittime."</font></p>\n";
		}

		print("<table class=\"main\" width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"5\">\n");
		$secs = 900;
		$dt = sqlesc(date("Y-m-d H:i:s",(TIMENOW - $secs))); // calculate date.
		print("<tr>\n");
		print("<td class=\"rowfollow\" width=\"150\" valign=\"top\" style=\"padding: 0px;\">".return_avatar_image($avatar)."</td>\n");
		print("<td class=\"rowfollow word-break-all\" valign=\"top\"><br />".$text.$text_editby."</td>\n");
		print("</tr>\n");
		$actionbar = "<a href=\"comment.php?action=add&amp;sub=quote&amp;cid=".$row['id']."&amp;pid=".$parent_id."&amp;type=".$type."\"><img class=\"f_quote\" src=\"pic/trans.gif\" alt=\"Quote\" title=\"".$lang_functions['title_reply_with_quote']."\" /></a>".
		"<a href=\"comment.php?action=add&amp;pid=".$parent_id."&amp;type=".$type."\"><img class=\"f_reply\" src=\"pic/trans.gif\" alt=\"Add Reply\" title=\"".$lang_functions['title_add_reply']."\" /></a>".(user_can('commanage') ? "<a href=\"comment.php?action=delete&amp;cid=".$row['id']."&amp;type=".$type."\"><img class=\"f_delete\" src=\"pic/trans.gif\" alt=\"Delete\" title=\"".$lang_functions['title_delete']."\" /></a>" : "").($row["user"] == $CURUSER["id"] || get_user_class() >= $commanage_class ? "<a href=\"comment.php?action=edit&amp;cid=".$row['id']."&amp;type=".$type."\"><img class=\"f_edit\" src=\"pic/trans.gif\" alt=\"Edit\" title=\"".$lang_functions['title_edit']."\" />"."</a>" : "");
		print("<tr><td class=\"toolbox\"> ".("'".$userRow['last_access']."'"> $dt ? "<img class=\"f_online\" src=\"pic/trans.gif\" alt=\"Online\" title=\"".$lang_functions['title_online']."\" />":"<img class=\"f_offline\" src=\"pic/trans.gif\" alt=\"Offline\" title=\"".$lang_functions['title_offline']."\" />" )."<a href=\"sendmessage.php?receiver=".htmlspecialchars(trim($row["user"]))."\"><img class=\"f_pm\" src=\"pic/trans.gif\" alt=\"PM\" title=\"".$lang_functions['title_send_message_to'].htmlspecialchars($userRow["username"])."\" /></a><a href=\"report.php?commentid=".htmlspecialchars(trim($row["id"]))."\"><img class=\"f_report\" src=\"pic/trans.gif\" alt=\"Report\" title=\"".$lang_functions['title_report_this_comment']."\" /></a></td><td class=\"toolbox\" align=\"right\">".$actionbar."</td>");

		print("</tr></table>\n");
	}
	end_frame();
	end_main_frame();
}

function searchfield($s) {
	return \App\Support\Strings::normalizeSearchTerm((string)$s);
}

function genrelist($catmode = 1) {
	global $Cache;
	return \App\Support\Category::listByMode($Cache, $catmode);
}

function searchbox_item_list(string $table, int $mode){
	global $Cache;
	return \App\Support\SearchBox::itemList($Cache, $table, $mode);
}

function langlist($type, $enabled = null) {
	return \App\Support\Locale::languageList($type, $enabled);
}

function linkcolor($num) {
	return \App\Support\Palette::seederLink($num);
}

function writecomment($userid, $comment, $oldModcomment = null) {
    \App\Models\UserModifyLog::query()->create(['user_id' => $userid, 'content' => $comment]);
//    if (is_null($oldModcomment)) {
//        $res = sql_query("SELECT modcomment FROM users WHERE id = '$userid'") or sqlerr(__FILE__, __LINE__);
//        $arr = mysql_fetch_assoc($res);
//        $modcomment = date("Y-m-d") . " - " . $comment . "" . ($arr['modcomment'] != "" ? "\n" : "") . $arr['modcomment'];
//    } else {
//        $modcomment = date("Y-m-d") . " - " . $comment . "" . ($oldModcomment != "" ? "\n" : "") .$oldModcomment;
//    }
//	$modcom = sqlesc($modcomment);
//    do_log("update user: $userid prepend modcomment: $comment, with oldModcomment: $oldModcomment");
//	return sql_query("UPDATE users SET modcomment = $modcom WHERE id = '$userid'") or sqlerr(__FILE__, __LINE__);
}

function return_torrent_bookmark_array($userid)
{
	global $Cache;
	return \App\Support\TorrentBookmark::bookmarkArray($Cache, $userid);
}
function get_torrent_bookmark_state($userid, $torrentid, $text = false)
{
	global $Cache, $lang_functions;
	return \App\Support\TorrentBookmark::stateMarkup($Cache, $userid, $torrentid, (bool) $text, [
		'title_bookmark_torrent' => $lang_functions['title_bookmark_torrent'] ?? '',
		'title_delbookmark_torrent' => $lang_functions['title_delbookmark_torrent'] ?? '',
	]);
}

function torrenttable($rows, $variant = "torrent", $searchBoxId = 0) {
	global $Cache;
	global $lang_functions;
	global $CURUSER, $waitsystem;
	global $torrentmanage_class, $smalldescription_main, $enabletooltip_tweak, $staffmem_class;
	global $CURLANGDIR;

	$torrent = new Nexus\Torrent\Torrent();
	$torrentRep = new \App\Repositories\TorrentRepository();
	$torrentIdArr = $ownerIdArr = [];
	foreach($rows as $row) {
	    $torrentIdArr[] = $row['id'];
        $ownerIdArr[] = $row['owner'];
    }
	unset($row);

	$torrentSeedingLeechingStatus = $torrent->listLeechingSeedingStatus($CURUSER['id'], $torrentIdArr);
    $tagRep = new \App\Repositories\TagRepository();
	$torrentTagCollection = \App\Models\TorrentTag::query()->whereIn('torrent_id', $torrentIdArr)->get();
	$torrentTagResult = $torrentTagCollection->groupBy('torrent_id');
	$showCover = false;
    $showSeedBoxIcon = false;
	if ($searchBoxId) {
	    $searchBoxExtra = get_searchbox_value($searchBoxId, "extra");
	    if (!empty($searchBoxExtra[\App\Models\SearchBox::EXTRA_DISPLAY_COVER_ON_TORRENT_LIST])) {
	        $showCover = true;
        }
        $showSeedBoxIcon = get_setting('seed_box.enabled') == 'yes';
        if (empty($searchBoxExtra[\App\Models\SearchBox::EXTRA_DISPLAY_SEED_BOX_ICON_ON_TORRENT_LIST])) {
            $showSeedBoxIcon = false;
        }
    }
	//seedBoxIcon
	if ($showSeedBoxIcon) {
	    $seedBoxRep = new \App\Repositories\SeedBoxRepository();
	    $seedBoxPeerInfo = \App\Models\Peer::query()
            ->whereIn('torrent', $torrentIdArr)
            ->where('seeder', 'yes')
            ->where('is_seed_box', '1')
            ->get(['torrent', 'is_seed_box'])
            ->keyBy('torrent');
    }


    $last_browse = $CURUSER['last_browse'];
//	if ($variant == "torrent"){
//		$last_browse = $CURUSER['last_browse'];
//		$sectiontype = $browsecatmode;
//	}
//	elseif($variant == "music"){
//		$last_browse = $CURUSER['last_music'];
//		$sectiontype = $specialcatmode;
//	}
//	else{
//		$last_browse = $CURUSER['last_browse'];
//		$sectiontype = "";
//	}

	$time_now = TIMENOW;
	if ($last_browse > $time_now) {
		$last_browse=$time_now;
	}
    $wait = 0;
	if (get_user_class() < UC_VIP && $waitsystem == "yes") {
		$ratio = get_ratio($CURUSER["id"], false);
		$gigs = $CURUSER["uploaded"] / (1024*1024*1024);
		if($gigs > 10)
		{
			if ($ratio < 0.4) $wait = 24;
			elseif ($ratio < 0.5) $wait = 12;
			elseif ($ratio < 0.6) $wait = 6;
			elseif ($ratio < 0.8) $wait = 3;
			else $wait = 0;
		}
		else $wait = 0;
	}
?>
<table class="torrents" cellspacing="0" cellpadding="5" width="100%">
<tr>
<?php
$count_get = 0;
$oldlink = "";
foreach ($_GET as $get_name => $get_value) {
	$get_name = mysql_real_escape_string(strip_tags(str_replace(array("\"","'"),array("",""),$get_name)));
	$get_value = mysql_real_escape_string(strip_tags(str_replace(array("\"","'"),array("",""),$get_value)));

	if ($get_name != "sort" && $get_name != "type") {
		if ($count_get > 0) {
			$oldlink .= "&amp;" . $get_name . "=" . $get_value;
		}
		else {
			$oldlink .= $get_name . "=" . $get_value;
		}
		$count_get++;
	}
}
if ($count_get > 0) {
	$oldlink = $oldlink . "&amp;";
}
$sort = $_GET['sort'] ?? '';
$link = array();
for ($i=1; $i<=9; $i++){
	if ($sort == $i)
		$link[$i] = ($_GET['type'] == "desc" ? "asc" : "desc");
	else $link[$i] = ($i == 1 ? "asc" : "desc");
}
?>
<td class="colhead" style="padding: 0px"><?php echo $lang_functions['col_type'] ?></td>
<td class="colhead"><a href="?<?php echo $oldlink?>sort=1&amp;type=<?php echo $link[1]?>"><?php echo $lang_functions['col_name'] ?></a></td>
<?php

if ($wait)
{
	print("<td class=\"colhead\">".$lang_functions['col_wait']."</td>\n");
}
if ($CURUSER['showcomnum'] != 'no') { ?>
<td class="colhead"><a href="?<?php echo $oldlink?>sort=3&amp;type=<?php echo $link[3]?>"><img class="comments" src="pic/trans.gif" alt="comments" title="<?php echo $lang_functions['title_number_of_comments'] ?>" /></a></td>
<?php } ?>

<td class="colhead"><a href="?<?php echo $oldlink?>sort=4&amp;type=<?php echo $link[4]?>"><img class="time" src="pic/trans.gif" alt="time" title="<?php echo ($CURUSER['timetype'] != 'timealive' ? $lang_functions['title_time_added'] : $lang_functions['title_time_alive'])?>" /></a></td>
<td class="colhead"><a href="?<?php echo $oldlink?>sort=5&amp;type=<?php echo $link[5]?>"><img class="size" src="pic/trans.gif" alt="size" title="<?php echo $lang_functions['title_size'] ?>" /></a></td>
<td class="colhead"><a href="?<?php echo $oldlink?>sort=7&amp;type=<?php echo $link[7]?>"><img class="seeders" src="pic/trans.gif" alt="seeders" title="<?php echo $lang_functions['title_number_of_seeders'] ?>" /></a></td>
<td class="colhead"><a href="?<?php echo $oldlink?>sort=8&amp;type=<?php echo $link[8]?>"><img class="leechers" src="pic/trans.gif" alt="leechers" title="<?php echo $lang_functions['title_number_of_leechers'] ?>" /></a></td>
<td class="colhead"><a href="?<?php echo $oldlink?>sort=6&amp;type=<?php echo $link[6]?>"><img class="snatched" src="pic/trans.gif" alt="snatched" title="<?php echo $lang_functions['title_number_of_snatched']?>" /></a></td>
<td class="colhead"><a href="?<?php echo $oldlink?>sort=9&amp;type=<?php echo $link[9]?>"><?php echo $lang_functions['col_uploader']?></a></td>
<?php
if (user_can('torrentmanage')) { ?>
	<td class="colhead"><?php echo $lang_functions['col_action'] ?></td>
<?php } ?>
</tr>
<?php
$caticonrow = get_category_icon_row($CURUSER['caticon']);
if ($caticonrow['secondicon'] == 'yes')
$has_secondicon = true;
else $has_secondicon = false;
$counter = 0;
if ($smalldescription_main == 'no' || $CURUSER['showsmalldescr'] == 'no')
	$displaysmalldescr = false;
else $displaysmalldescr = true;
//while ($row = mysql_fetch_assoc($res))
$lastcom_tooltip = [];
$torrent_tooltip = [];
foreach ($rows as $row)
{
	$id = $row["id"];
	$sphighlight = get_torrent_bg_color($row['sp_state'], $row['pos_state'], $row);
	print("<tr" . $sphighlight . ">\n");

	print("<td class=\"rowfollow nowrap\" valign=\"middle\" style='padding: 0px'>");
	if (isset($row["category"])) {
		print(return_category_image($row["category"], "?"));
		if ($has_secondicon){
			print(get_second_icon($row));
		}
	}
	else
		print("-");
	print("</td>\n");

	//torrent name
	$dispname = trim($row["name"]);
	$short_torrent_name_alt = "title=\"".htmlspecialchars($dispname)."\"";
	$mouseovertorrent = "";
	$count_dispname=mb_strlen($dispname,"UTF-8");
	if (!$displaysmalldescr || $row["small_descr"] == "")// maximum length of torrent name
		$max_length_of_torrent_name = 200;
	elseif ($CURUSER['fontsize'] == 'large')
		$max_length_of_torrent_name = 120;
	elseif ($CURUSER['fontsize'] == 'small')
		$max_length_of_torrent_name = 160;
	else $max_length_of_torrent_name = 140;

	if($count_dispname > $max_length_of_torrent_name)
		$dispname=mb_substr($dispname, 0, $max_length_of_torrent_name-2,"UTF-8") . "..";
	if ($CURUSER['appendsticky'] == 'yes') {
        $posStates = \App\Models\Torrent::listPosStates();
        $stickyicon = str_repeat("<img class=\"sticky\" src=\"pic/trans.gif\" alt=\"Sticky\" title=\"".$posStates[$row['pos_state']]['text']."\" />&nbsp;", $posStates[$row['pos_state']]['icon_counts'] ?? 0);
    } else {
        $stickyicon = "";
    }
	$stickyicon = apply_filter('sticky_icon', $stickyicon, $row);
    $sp_torrent = get_torrent_promotion_append($row['sp_state'],"",true,$row["added"], $row['promotion_time_type'], $row['promotion_until'], $row['__ignore_global_sp_state'] ?? false);
	$hrImg = get_hr_img($row, $row['search_box_id']);

	//cover
    $coverSrc = $tdCover = '';

    if ($showCover) {
        if (!empty($row['cover'])) {
            $coverSrc = $row['cover'];
        }
        $tdCover = sprintf('<td class="embedded" style="text-align: center;width: 46px;height: 46px"><img src="pic/misc/spinner.svg" data-src="%s" class="nexus-lazy-load" style="max-height: 46px;max-width: 46px" /></td>', $coverSrc);
    }

	print("<td class=\"rowfollow\" width=\"100%\" align=\"left\" style='padding: 0px'><table class=\"torrentname\" width=\"100%\"><tr" . $sphighlight . ">$tdCover<td class=\"embedded\" style='padding-left: 5px'>".$stickyicon."<a $short_torrent_name_alt $mouseovertorrent href=\"details.php?id=".$id."&amp;hit=1\"><b>".htmlspecialchars($dispname)."</b></a>");
	if ($CURUSER['appendnew'] != 'no' && strtotime($row["added"]) >= $last_browse)
		print("<b> (<font class='new'>".$lang_functions['text_new_uppercase']."</font>)</b>");

	$banned_torrent = ($row["banned"] == 'yes' ? " <b>(<font class=\"striking\">".$lang_functions['text_banned']."</font>)</b>" : "");
	$sp_torrent_sub = get_torrent_promotion_append_sub($row['sp_state'],"",true,$row['added'], $row['promotion_time_type'], $row['promotion_until'], $row['__ignore_global_sp_state'] ?? false);
    $approvalStatusIcon = $torrentRep->renderApprovalStatus($row['approval_status']);
    if ($showSeedBoxIcon && $seedBoxPeerInfo->has($row['id'])) {
        $seedBoxIcon = $seedBoxRep->getSeedBoxIcon();
    } else {
        $seedBoxIcon = '';
    }
    $paidIcon = $torrentRep->getPaidIcon($row);
	$titleSuffix = $banned_torrent.$paidIcon.$sp_torrent.$sp_torrent_sub. $hrImg . $seedBoxIcon . $approvalStatusIcon;
	$titleSuffix = apply_filter('torrent_title_suffix', $titleSuffix, $row);
	print($titleSuffix);
    /**
     * render tags
     */
    $tagOwns = $torrentTagResult->get($id);
    if ($tagOwns) {
        $tags = $tagRep->renderSpan($row['search_box_id'], $tagOwns->pluck('tag_id')->toArray());
    } else {
        $tags = '';
    }

	if ($displaysmalldescr){
		//small descr
		$dissmall_descr = trim($row["small_descr"]);
		$count_dissmall_descr=mb_strlen($dissmall_descr,"UTF-8");
		$max_lenght_of_small_descr=$max_length_of_torrent_name; // maximum length
		if($count_dissmall_descr > $max_lenght_of_small_descr)
		{
			$dissmall_descr=mb_substr($dissmall_descr, 0, $max_lenght_of_small_descr-2,"UTF-8") . "..";
		}
		$dissmall_descr = $tags . htmlspecialchars($dissmall_descr);
		print($dissmall_descr == "" ? "" : "<br />".$dissmall_descr);
	} else {
	    print($tags ? "<br />$tags" : "");
    }
	//progress bar
	if (isset($torrentSeedingLeechingStatus[$row['id']])) {
	    echo $torrent->renderProgressBar($torrentSeedingLeechingStatus[$row['id']]['active_status'], $torrentSeedingLeechingStatus[$row['id']]['progress']);
    }
	print("</td>");

		$act = "";
		if ($CURUSER["dlicon"] != 'no' && $CURUSER["downloadpos"] != "no")
		$act .= "<a href=\"download.php?id=".$id."\"><img class=\"download\" src=\"pic/trans.gif\" style='padding-bottom: 2px;' alt=\"download\" title=\"".$lang_functions['title_download_torrent']."\" /></a>" ;
		if ($CURUSER["bmicon"] == 'yes'){
			$bookmark = " href=\"javascript: bookmark(".$id.",".$counter.");\"";
			$act .= ($act ? "<br />" : "")."<a id=\"bookmark".$counter."\" ".$bookmark." >".get_torrent_bookmark_state($CURUSER['id'], $id)."</a>";
		}

	print("<td width=\"20\" class=\"embedded\" style=\"text-align: right;padding-right: 5px\" valign=\"middle\">".$act."</td>\n");

	print("</tr></table></td>");
	if ($wait)
	{
		$elapsed = floor((TIMENOW - strtotime($row["added"])) / 3600);
		if ($elapsed < $wait)
		{
			$color = dechex(floor(127*($wait - $elapsed)/48 + 128)*65536);
			print("<td class=\"rowfollow nowrap\"><a href=\"faq.php#id46\"><font color=\"".$color."\">" . number_format($wait - $elapsed) . $lang_functions['text_h']."</font></a></td>\n");
		}
		else
		print("<td class=\"rowfollow nowrap\">".$lang_functions['text_none']."</td>\n");
	}

	if ($CURUSER['showcomnum'] != 'no')
	{
	print("<td class=\"rowfollow\">");
	$nl = "";

	//comments

	$nl = "<br />";
	if (!$row["comments"]) {
		print("<a href=\"comment.php?action=add&amp;pid=".$id."&amp;type=torrent\" title=\"".$lang_functions['title_add_comments']."\">" . $row["comments"] .  "</a>");
	} else {
		if ($enabletooltip_tweak == 'yes' && $CURUSER['showlastcom'] != 'no')
		{
			if (!$lastcom = $Cache->get_value('torrent_'.$id.'_last_comment_content')){
				$res2 = sql_query("SELECT user, added, text FROM comments WHERE torrent = $id ORDER BY id DESC LIMIT 1");
				$lastcom = mysql_fetch_array($res2);
				$Cache->cache_value('torrent_'.$id.'_last_comment_content', $lastcom, 1855);
			}
			$timestamp = strtotime($lastcom["added"]);
			$hasnewcom = ($lastcom['user'] != $CURUSER['id'] && $timestamp >= $last_browse);
			if ($lastcom)
			{
				if ($CURUSER['timetype'] != 'timealive')
					$lastcomtime = $lang_functions['text_at_time'].$lastcom['added'];
				else
					$lastcomtime = $lang_functions['text_blank'].gettime($lastcom["added"],true,false,true);
					$lastcom_tooltip[$counter]['id'] = "lastcom_" . $counter;
					$lastcom_tooltip[$counter]['content'] = ($hasnewcom ? "<b>(<font class='new'>".$lang_functions['text_new_uppercase']."</font>)</b> " : "").$lang_functions['text_last_commented_by'].get_username($lastcom['user']) . $lastcomtime."<br />". format_comment(mb_substr($lastcom['text'],0,100,"UTF-8") . (mb_strlen($lastcom['text'],"UTF-8") > 100 ? " ......" : "" ),true,false,false,true,600,false,false);
					$onmouseover = "onmouseover=\"domTT_activate(this, event, 'content', document.getElementById('" . $lastcom_tooltip[$counter]['id'] . "'), 'trail', false, 'delay', 500,'lifetime',3000,'fade','both','styleClass','niceTitle','fadeMax', 87,'maxWidth', 400);\"";
			}
		} else {
			$hasnewcom = false;
			$onmouseover = "";
		}
		print("<b><a href=\"details.php?id=".$id."&amp;hit=1&amp;cmtpage=1#startcomments\" ".$onmouseover.">". ($hasnewcom ? "<font class='new'>" : ""). $row["comments"] .($hasnewcom ? "</font>" : ""). "</a></b>");
	}

	print("</td>");
	}

	$time = $row["added"];
	$time = gettime($time,false,true);
	print("<td class=\"rowfollow nowrap\">". $time. "</td>");

	//size
	print("<td class=\"rowfollow\">" . mksize_compact($row["size"])."</td>");

	if ($row["seeders"]) {
			$ratio = ($row["leechers"] ? ($row["seeders"] / $row["leechers"]) : 1);
			$ratiocolor = get_slr_color($ratio);
			print("<td class=\"rowfollow\" align=\"center\"><b><a href=\"details.php?id=".$id."&amp;hit=1&amp;dllist=1#seeders\">".($ratiocolor ? "<font color=\"" .
			$ratiocolor . "\">" . number_format($row["seeders"]) . "</font>" : number_format($row["seeders"]))."</a></b></td>\n");
	}
	else
		print("<td class=\"rowfollow\"><span class=\"" . linkcolor($row["seeders"]) . "\">" . number_format($row["seeders"]) . "</span></td>\n");

	if ($row["leechers"]) {
		print("<td class=\"rowfollow\"><b><a href=\"details.php?id=".$id."&amp;hit=1&amp;dllist=1#leechers\">" .
		number_format($row["leechers"]) . "</a></b></td>\n");
	}
	else
		print("<td class=\"rowfollow\">0</td>\n");

	if ($row["times_completed"] >=1)
	print("<td class=\"rowfollow\"><a href=\"viewsnatches.php?id=".$row['id']."\"><b>" . number_format($row["times_completed"]) . "</b></a></td>\n");
	else
	print("<td class=\"rowfollow\">" . number_format($row["times_completed"]) . "</td>\n");

		if (
		    $row["anonymous"] == "yes"
            && (user_can('viewanonymous') || (isset($row['owner']) && $row['owner'] == $CURUSER['id']))
        ) {
			print("<td class=\"rowfollow\" align=\"center\"><i>".$lang_functions['text_anonymous']."</i><br />".(isset($row["owner"]) ? "(" . get_username($row["owner"]) .")" : "<i>".$lang_functions['text_orphaned']."</i>") . "</td>\n");
		}
		elseif ($row["anonymous"] == "yes")
		{
			print("<td class=\"rowfollow\"><i>".$lang_functions['text_anonymous']."</i></td>\n");
		}
		else
		{
			print("<td class=\"rowfollow\">" . (isset($row["owner"]) ? get_username($row["owner"]) : "<i>".$lang_functions['text_orphaned']."</i>") . "</td>\n");
		}

	if (user_can('torrentmanage'))
	{
        $actions = [];
        if (user_can('torrent-delete')) {
            $actions[] = "<a href=\"".htmlspecialchars("fastdelete.php?id=".$row['id'])."\"><img class=\"staff_delete\" src=\"pic/trans.gif\" alt=\"D\" title=\"".$lang_functions['text_delete']."\" /></a>";
        }
        $actions[] = "<a href=\"edit.php?returnto=" . rawurlencode($_SERVER["REQUEST_URI"]) . "&amp;id=" . $row["id"] . "\"><img class=\"staff_edit\" src=\"pic/trans.gif\" alt=\"E\" title=\"".$lang_functions['text_edit']."\" /></a>";
		echo sprintf("<td class=\"rowfollow\">%s</td>", implode("<br />", $actions));
	}
	print("</tr>\n");
	$counter++;
}
print("</table>");
if ($CURUSER['appendpromotion'] == 'highlight')
	print("<p align=\"center\"> ".$lang_functions['text_promoted_torrents_note']."</p>\n");

if($enabletooltip_tweak == 'yes' && (!isset($CURUSER) || $CURUSER['showlastcom'] == 'yes'))
create_tooltip_container($lastcom_tooltip, 400);
create_tooltip_container($torrent_tooltip, 500);
}

function get_username($id, $big = false, $link = true, $bold = true, $target = false, $bracket = false, $withtitle = false, $link_ext = "", $underline = false)
{
	static $usernameArray = array();
	$id = (int)$id;

	if (func_num_args() == 1 && isset($usernameArray[$id])) {  //One argument=is default display of username. Get it directly from static array if available
		return $usernameArray[$id];
	}
	$arr = get_user_row($id);
	if ($arr){
		if ($big)
		{
			$donorpic = "starbig";
			$leechwarnpic = "leechwarnedbig";
			$warnedpic = "warnedbig";
			$disabledpic = "disabledbig";
			$marginLeft = '4pt';
			$medalSize = '16px';
			$medalClass = 'nexus-username-medal-big';
			$style = "style='margin-left: $marginLeft'";
		}
		else
		{
			$donorpic = "star";
			$leechwarnpic = "leechwarned";
			$warnedpic = "warned";
			$disabledpic = "disabled";
            $marginLeft = '2pt';
            $medalSize = '11px';
            $medalClass = 'nexus-username-medal';
			$style = "style='margin-left: $marginLeft'";
		}
		$pics = $arr["donor"] == "yes" && ($arr['donoruntil'] === null || $arr['donoruntil'] < '1970' || $arr['donoruntil'] >= date('Y-m-d H:i:s')) ? "<img class=\"".$donorpic."\" src=\"/pic/trans.gif\" alt=\"Donor\" ".$style." />" : "";

		if ($arr["enabled"] == "yes")
			$pics .= ($arr["leechwarn"] == "yes" ? "<img class=\"".$leechwarnpic."\" src=\"/pic/trans.gif\" alt=\"Leechwarned\" ".$style." />" : "") . ($arr["warned"] == "yes" ? "<img class=\"".$warnedpic."\" src=\"/pic/trans.gif\" alt=\"Warned\" ".$style." />" : "");
		else
			$pics .= "<img class=\"".$disabledpic."\" src=\"/pic/trans.gif\" alt=\"Disabled\" ".$style." />\n";

		//Rainbow effect
		$username = $arr['username'];
		$rainbow = "";
		$hasSetRainbow = false;
		if (isset($arr['__is_rainbow']) && $arr['__is_rainbow']) {
		    $rainbow = ' class="rainbow"';
        }
		if ($underline) {
		    $hasSetRainbow = true;
		    $username = "<u{$rainbow}>{$username}</u>";
        }
		if ($bold) {
		    if ($hasSetRainbow) {
		        $username = "<b>{$username}</b>";
            } else {
                $hasSetRainbow = true;
		        $username = "<b{$rainbow}>{$username}</b>";
            }
        }
//        $username = ($underline == true ? "<u>" . $arr['username'] . "</u>" : $arr['username']);
//        $username = ($bold == true ? "<b>" . $username . "</b>" : $username);

        //medal
        $medalHtml = '';
		foreach ($arr['wearing_medals'] ?? [] as $medal) {
            $medalHtml .= sprintf(
                '<img src="%s" title="%s" class="%s preview" style="max-height: %s;max-width: %s;margin-left: %s"/>',
                $medal['image_large'], $medal['name'], $medalClass, $medalSize, $medalSize, $marginLeft
            );
        }

		$href = getSchemeAndHttpHost() . "/userdetails.php?id=$id";
		$username = ($link == true ? "<a ". $link_ext . " href=\"" . $href . "\"" . ($target == true ? " target=\"_blank\"" : "") . " class='". get_user_class_name($arr['class'],true, false, false) . "_Name'>" . $username . "</a>" : $username) . $pics . ($withtitle == true ? " (" . ($arr['title'] == "" ?  get_user_class_name($arr['class'],false,true,true, ['with_alias' => true]) : "<span class='".get_user_class_name($arr['class'],true, false, false) . "_Name'><b>".htmlspecialchars($arr['title'])) . "</b></span>)" : "");

		$username = "<span class=\"nowrap\">" . ( $bracket == true ? "(" . $username . ")" : $username) . "$medalHtml</span>";
	}
	else
	{
		$username = "<i>".nexus_trans('nexus.user_not_exists')."</i>";
		$username = "<span class=\"nowrap\">" . ( $bracket == true ? "(" . $username . ")" : $username) . "</span>";
	}
	if (func_num_args() == 1) { //One argument=is default display of username, save it in static array
		$usernameArray[$id] = $username;
	}
	return $username;
}

function get_percent_completed_image($p) {
	return \App\Support\Progress::percentImage($p);
}

function get_ratio_img($ratio)
{
	return \App\Support\Ratio::image((float)$ratio);
}

function GetVar ($name) {
	return \App\Support\Input::getVar($name);
}

function ssr ($arg) {
	return \App\Support\Strings::stripSlashesDeep(is_array($arg) ? $arg : (string)$arg);
}

function parked()
{
    \App\Support\LegacyAuth::parked();
}


function validusername($username)
{
	return \App\Support\Validators::isUsername($username);
}

//Code for Viewing NFO file

// code: Takes a string and does a IBM-437-to-HTML-Unicode-Entities-conversion.
// swedishmagic specifies special behavior for Swedish characters.
// Some Swedish Latin-1 letters collide with popular DOS glyphs. If these
// characters are between ASCII-characters (a-zA-Z and more) they are
// treated like the Swedish letters, otherwise like the DOS glyphs.
function code($ibm_437, $view) {
	return \App\Support\Codec::ibm437ToEntitiesLegacy((string) $ibm_437, (string) $view);
}

/**
 * @param $ibm_437
 * @param $view
 * @return array|string|string[]
 * @ref https://github.com/HDInnovations/UNIT3D-Community-Edition/blob/master/app/Helpers/Nfo.php
 */
function code_new($ibm_437, $view)
{
	return \App\Support\Codec::ibm437ToEntities((string) $ibm_437, (string) $view);
}


//Tooltip container for hot movie, classic movie, etc
function create_tooltip_container($id_content_arr, $width = 400)
{
	echo \App\Support\Html::tooltipContainer($id_content_arr);
}


function quickreply($formname, $taname,$submit){
	echo \App\Support\Html::quickReply((string) $formname, (string) $taname, (string) $submit);
}

function smile_row($formname, $taname){
	return \App\Support\Smilies::quickRow($formname, $taname);
}
function getSmileIt($formname, $taname, $smilyNumber) {
	return \App\Support\Smilies::link($formname, $taname, (int) $smilyNumber);
}

function classlist($selectname,$maxclass, $selected, $minClass = 0, $includeNoClass = false, $disabled = false){
    global $lang_functions;
    return \App\Support\UserClass::classSelect(
        (string) $selectname,
        (int) $maxclass,
        $selected,
        (int) $minClass,
        (bool) $includeNoClass,
        (bool) $disabled,
        ['select_an_user_class' => $lang_functions['select_an_user_class'] ?? '---']
    );
}

function permissiondenied($allowMinimumClass = null){
	\App\Support\LegacyResponse::permissionDenied($allowMinimumClass);
}

function gettime($time, $withago = true, $twoline = false, $forceago = false, $oneunit = false, $isfuturetime = false){
	return \App\Support\Time::format($time, $withago, $twoline, $forceago, $oneunit, $isfuturetime);
}

function get_forum_pic_folder(){
	global $CURLANGDIR;
	return \App\Support\Forum::picFolder((string) $CURLANGDIR);
}

function get_category_icon_row($typeid)
{
	global $Cache;
	return \App\Support\Category::iconRow($Cache, $typeid);
}
function get_category_row($catid = NULL)
{
	global $Cache;
	return \App\Support\Category::row($Cache, $catid);
}

function get_second_icon($row) //for CHDBits
{
	global $Cache;
	return \App\Support\Category::secondIcon($Cache, (array) $row, get_cat_folder($row['category']));
}

function get_torrent_bg_color($promotion = 1, $posState = "", array $torrent = [])
{
	global $CURUSER;
	$sphighlight = null;
	if ($CURUSER['appendpromotion'] == 'highlight') {
		$global_promotion_state = get_global_sp_state();
		$code = ($global_promotion_state == 1) ? $promotion : $global_promotion_state;
		$sphighlight = \App\Support\Promotion::backgroundClass((int) $code);
	}
	if (is_null($sphighlight)) {
        $torrentSettings = get_setting('torrent');
	    if ($posState == \App\Models\Torrent::POS_STATE_STICKY_FIRST && !empty($torrentSettings['sticky_first_level_background_color'])) {
	        $sphighlight = sprintf(' style="background-color: %s"', $torrentSettings['sticky_first_level_background_color']);
        } elseif ($posState == \App\Models\Torrent::POS_STATE_STICKY_SECOND && !empty($torrentSettings['sticky_second_level_background_color'])) {
            $sphighlight = sprintf(' style="background-color: %s"', $torrentSettings['sticky_second_level_background_color']);
        }
    }
	return apply_filter('torrent_background_color', (string)$sphighlight, $torrent);
}

function get_torrent_promotion_append($promotion = 1,$forcemode = "",$showtimeleft = false, $added = "", $promotionTimeType = 0, $promotionUntil = '', $ignoreGlobal = false){
	global $CURUSER,$lang_functions;
	global $expirehalfleech_torrent, $expirefree_torrent, $expiretwoup_torrent, $expiretwoupfree_torrent, $expiretwouphalfleech_torrent, $expirethirtypercentleech_torrent;

	$globalSpState = get_global_sp_state();
	$sp_torrent = "";
	$onmouseover = "";
	$log = "[GET_PROMOTION], promotion: $promotion, forcemode: $forcemode, showtimeleft: $showtimeleft, added: $added, promotionTimeType: $promotionTimeType, promotionUntil: $promotionUntil";
    if ($ignoreGlobal) {
        $globalSpState = 1;
        $log .= ", [IGNORE_GLOBAL]";
    }
	$log .= ", globalSpState == " . $globalSpState;
	if ($globalSpState == 1) {
	switch ($promotion){
		case 2:
		{
			if ($showtimeleft && (($expirefree_torrent && $promotionTimeType == 0) || $promotionTimeType == 2))
			{
				if ($promotionTimeType == 2) {
					$futuretime = strtotime($promotionUntil);
				} else {
					$futuretime = strtotime($added) + $expirefree_torrent * 86400;
				}
				$timeout = gettime(date("Y-m-d H:i:s", $futuretime), false, false, true, false, true);
				if ($timeout)
				$onmouseover = " onmouseover=\"domTT_activate(this, event, 'content', '".htmlspecialchars("<b><font class=\"free\">".$lang_functions['text_free']."</font></b>".$lang_functions['text_will_end_in']."<b>".$timeout."</b>")."', 'trail', false, 'delay',500,'lifetime',3000,'fade','both','styleClass','niceTitle', 'fadeMax',87, 'maxWidth', 300);\"";
				else $promotion = 1;
			}
			break;
		}
		case 3:
		{
			if ($showtimeleft && (($expiretwoup_torrent && $promotionTimeType == 0) || $promotionTimeType == 2))
			{
				if ($promotionTimeType == 2) {
					$futuretime = strtotime($promotionUntil);
				} else {
					$futuretime = strtotime($added) + $expiretwoup_torrent * 86400;
				}
				$timeout = gettime(date("Y-m-d H:i:s", $futuretime), false, false, true, false, true);
				if ($timeout)
				$onmouseover = " onmouseover=\"domTT_activate(this, event, 'content', '".htmlspecialchars("<b><font class=\"twoup\">".$lang_functions['text_two_times_up']."</font></b>".$lang_functions['text_will_end_in']."<b>".$timeout."</b>")."', 'trail', false, 'delay',500,'lifetime',3000,'fade','both','styleClass','niceTitle', 'fadeMax',87, 'maxWidth', 300);\"";
				else $promotion = 1;
			}
			break;
		}
		case 4:
		{
			if ($showtimeleft && (($expiretwoupfree_torrent && $promotionTimeType == 0) || $promotionTimeType == 2))
			{
				if ($promotionTimeType == 2) {
					$futuretime = strtotime($promotionUntil);
				} else {
					$futuretime = strtotime($added) + $expiretwoupfree_torrent * 86400;
				}
				$timeout = gettime(date("Y-m-d H:i:s", $futuretime), false, false, true, false, true);
				if ($timeout)
				$onmouseover = " onmouseover=\"domTT_activate(this, event, 'content', '".htmlspecialchars("<b><font class=\"twoupfree\">".$lang_functions['text_free_two_times_up']."</font></b>".$lang_functions['text_will_end_in']."<b>".$timeout."</b>")."', 'trail', false, 'delay',500,'lifetime',3000,'fade','both','styleClass','niceTitle', 'fadeMax',87, 'maxWidth', 300);\"";
				else $promotion = 1;
			}
			break;
		}
		case 5:
		{
			if ($showtimeleft && (($expirehalfleech_torrent && $promotionTimeType == 0) || $promotionTimeType == 2))
			{
				if ($promotionTimeType == 2) {
					$futuretime = strtotime($promotionUntil);
				} else {
					$futuretime = strtotime($added) + $expirehalfleech_torrent * 86400;
				}
				$timeout = gettime(date("Y-m-d H:i:s", $futuretime), false, false, true, false, true);
				if ($timeout)
				$onmouseover = " onmouseover=\"domTT_activate(this, event, 'content', '".htmlspecialchars("<b><font class=\"halfdown\">".$lang_functions['text_half_down']."</font></b>".$lang_functions['text_will_end_in']."<b>".$timeout."</b>")."', 'trail', false, 'delay',500,'lifetime',3000,'fade','both','styleClass','niceTitle', 'fadeMax',87, 'maxWidth', 300);\"";
				else $promotion = 1;
			}
			break;
		}
		case 6:
		{
			if ($showtimeleft && (($expiretwouphalfleech_torrent && $promotionTimeType == 0) || $promotionTimeType == 2))
			{
				if ($promotionTimeType == 2) {
					$futuretime = strtotime($promotionUntil);
				} else {
					$futuretime = strtotime($added) + $expiretwouphalfleech_torrent * 86400;
				}
				$timeout = gettime(date("Y-m-d H:i:s", $futuretime), false, false, true, false, true);
				if ($timeout)
				$onmouseover = " onmouseover=\"domTT_activate(this, event, 'content', '".htmlspecialchars("<b><font class=\"twouphalfdown\">".$lang_functions['text_half_down_two_up']."</font></b>".$lang_functions['text_will_end_in']."<b>".$timeout."</b>")."', 'trail', false, 'delay',500,'lifetime',3000,'fade','both','styleClass','niceTitle', 'fadeMax',87, 'maxWidth', 300);\"";
				else $promotion = 1;
			}
			break;
		}
		case 7:
		{
			if ($showtimeleft && (($expirethirtypercentleech_torrent && $promotionTimeType == 0) || $promotionTimeType == 2))
			{
				if ($promotionTimeType == 2) {
					$futuretime = strtotime($promotionUntil);
				} else {
					$futuretime = strtotime($added) + $expirethirtypercentleech_torrent * 86400;
				}
				$timeout = gettime(date("Y-m-d H:i:s", $futuretime), false, false, true, false, true);
				if ($timeout)
				$onmouseover = " onmouseover=\"domTT_activate(this, event, 'content', '".htmlspecialchars("<b><font class=\"thirtypercent\">".$lang_functions['text_thirty_percent_down']."</font></b>".$lang_functions['text_will_end_in']."<b>".$timeout."</b>")."', 'trail', false, 'delay',500,'lifetime',3000,'fade','both','styleClass','niceTitle', 'fadeMax',87, 'maxWidth', 300);\"";
				else $promotion = 1;
			}
			break;
		}
	}
	}
	if (($CURUSER['appendpromotion'] == 'word' && $forcemode == "" ) || $forcemode == 'word'){
        $log .= ", user appendpromotion = word";
		if(($promotion==2 && $globalSpState == 1) || $globalSpState == 2){
		    $log .= ", promotion or global_sp_state = 2";
			$sp_torrent = " <b>[<font class='free' ".$onmouseover.">".$lang_functions['text_free']."</font>]</b>";
		}
		elseif(($promotion==3 && $globalSpState == 1) || $globalSpState == 3){
            $log .= ", promotion or global_sp_state = 3";
			$sp_torrent = " <b>[<font class='twoup' ".$onmouseover.">".$lang_functions['text_two_times_up']."</font>]</b>";
		}
		elseif(($promotion==4 && $globalSpState == 1) || $globalSpState == 4){
            $log .= ", promotion or global_sp_state = 4";
			$sp_torrent = " <b>[<font class='twoupfree' ".$onmouseover.">".$lang_functions['text_free_two_times_up']."</font>]</b>";
		}
		elseif(($promotion==5 && $globalSpState == 1) || $globalSpState == 5){
            $log .= ", promotion or global_sp_state = 5";
			$sp_torrent = " <b>[<font class='halfdown' ".$onmouseover.">".$lang_functions['text_half_down']."</font>]</b>";
		}
		elseif(($promotion==6 && $globalSpState == 1) || $globalSpState == 6){
            $log .= ", promotion or global_sp_state = 6";
			$sp_torrent = " <b>[<font class='twouphalfdown' ".$onmouseover.">".$lang_functions['text_half_down_two_up']."</font>]</b>";
		}
		elseif(($promotion==7 && $globalSpState == 1) || $globalSpState == 7){
            $log .= ", promotion or global_sp_state = 7";
			$sp_torrent = " <b>[<font class='thirtypercent' ".$onmouseover.">".$lang_functions['text_thirty_percent_down']."</font>]</b>";
		}
	}
	elseif (($CURUSER['appendpromotion'] == 'icon' && $forcemode == "") || $forcemode == 'icon'){
        $log .= ", user appendpromotion = icon";
		if(($promotion==2 && $globalSpState == 1) || $globalSpState == 2) {
            $log .= ", promotion or global_sp_state = 2";
            $sp_torrent = " <img class=\"pro_free\" src=\"pic/trans.gif\" alt=\"Free\" ".($onmouseover ? $onmouseover : "title=\"".$lang_functions['text_free']."\"")." />";
        }
		elseif(($promotion==3 && $globalSpState == 1) || $globalSpState == 3) {
            $log .= ", promotion or global_sp_state = 3";
            $sp_torrent = " <img class=\"pro_2up\" src=\"pic/trans.gif\" alt=\"2X\" ".($onmouseover ? $onmouseover : "title=\"".$lang_functions['text_two_times_up']."\"")." />";
        }
		elseif(($promotion==4 && $globalSpState == 1) || $globalSpState == 4) {
            $log .= ", promotion or global_sp_state = 4";
            $sp_torrent = " <img class=\"pro_free2up\" src=\"pic/trans.gif\" alt=\"2X Free\" ".($onmouseover ? $onmouseover : "title=\"".$lang_functions['text_free_two_times_up']."\"")." />";
        }
		elseif(($promotion==5 && $globalSpState == 1) || $globalSpState == 5) {
            $log .= ", promotion or global_sp_state = 5";
            $sp_torrent = " <img class=\"pro_50pctdown\" src=\"pic/trans.gif\" alt=\"50%\" ".($onmouseover ? $onmouseover : "title=\"".$lang_functions['text_half_down']."\"")." />";
        }
		elseif(($promotion==6 && $globalSpState == 1) || $globalSpState == 6) {
            $log .= ", promotion or global_sp_state = 6";
            $sp_torrent = " <img class=\"pro_50pctdown2up\" src=\"pic/trans.gif\" alt=\"2X 50%\" ".($onmouseover ? $onmouseover : "title=\"".$lang_functions['text_half_down_two_up']."\"")." />";
        }
		elseif(($promotion==7 && $globalSpState == 1) || $globalSpState == 7) {
            $log .= ", promotion or global_sp_state = 7";
            $sp_torrent = " <img class=\"pro_30pctdown\" src=\"pic/trans.gif\" alt=\"30%\" ".($onmouseover ? $onmouseover : "title=\"".$lang_functions['text_thirty_percent_down']."\"")." />";
        }
	}
	do_log("$log, sp_torrent: $sp_torrent");
	return $sp_torrent;
}

function get_torrent_promotion_append_sub($promotion = 1,$forcemode = "",$showtimeleft = false, $added = "", $promotionTimeType = 0, $promotionUntil = '', $ignoreGlobal = false){
	global $CURUSER,$lang_functions;
	global $expirehalfleech_torrent, $expirefree_torrent, $expiretwoup_torrent, $expiretwoupfree_torrent, $expiretwouphalfleech_torrent, $expirethirtypercentleech_torrent;

    $globalSpState = get_global_sp_state();
	$sp_torrent = "";
	$onmouseover = "";
	$log = "[GET_PROMOTION], promotion: $promotion, forcemode: $forcemode, showtimeleft: $showtimeleft, added: $added, promotionTimeType: $promotionTimeType, promotionUntil: $promotionUntil";
    if ($ignoreGlobal) {
        $globalSpState = 1;
        $log .= ", [IGNORE_GLOBAL]";
    }
	$log .= ", globalSpState == " . $globalSpState;
	if ($globalSpState == 1) {
	switch ($promotion){
		case 2:
		{
			if ($showtimeleft && (($expirefree_torrent && $promotionTimeType == 0) || $promotionTimeType == 2))
			{
				if ($promotionTimeType == 2) {
					$futuretime = strtotime($promotionUntil);
				} else {
					$futuretime = strtotime($added) + $expirefree_torrent * 86400;
				}
				$timeout = gettime(date("Y-m-d H:i:s", $futuretime), false, false, true, false, true);
				if ($timeout)
				$onmouseover = " <font color='#0000FF'>".$lang_functions['text_will_end_in'].$timeout."</font>"; //free类型字符显示为蓝色，可以更改它
				else $promotion = 1;
			}
			break;
		}
		case 3:
		{
			if ($showtimeleft && (($expiretwoup_torrent && $promotionTimeType == 0) || $promotionTimeType == 2))
			{
				if ($promotionTimeType == 2) {
					$futuretime = strtotime($promotionUntil);
				} else {
					$futuretime = strtotime($added) + $expiretwoup_torrent * 86400;
				}
				$timeout = gettime(date("Y-m-d H:i:s", $futuretime), false, false, true, false, true);
				if ($timeout)
				$onmouseover = " ".$lang_functions['text_will_end_in'].$timeout;
				else $promotion = 1;
			}
			break;
		}
		case 4:
		{
			if ($showtimeleft && (($expiretwoupfree_torrent && $promotionTimeType == 0) || $promotionTimeType == 2))
			{
				if ($promotionTimeType == 2) {
					$futuretime = strtotime($promotionUntil);
				} else {
					$futuretime = strtotime($added) + $expiretwoupfree_torrent * 86400;
				}
				$timeout = gettime(date("Y-m-d H:i:s", $futuretime), false, false, true, false, true);
				if ($timeout)
				$onmouseover = " <font color='#00CC66'>".$lang_functions['text_will_end_in'].$timeout."</font>"; //2XFree 显示为青色，可以更改它
				else $promotion = 1;
			}
			break;
		}
		case 5:
		{
			if ($showtimeleft && (($expirehalfleech_torrent && $promotionTimeType == 0) || $promotionTimeType == 2))
			{
				if ($promotionTimeType == 2) {
					$futuretime = strtotime($promotionUntil);
				} else {
					$futuretime = strtotime($added) + $expirehalfleech_torrent * 86400;
				}
				$timeout = gettime(date("Y-m-d H:i:s", $futuretime), false, false, true, false, true);
				if ($timeout)
				$onmouseover = " ".$lang_functions['text_will_end_in'].$timeout;
				else $promotion = 1;
			}
			break;
		}
		case 6:
		{
			if ($showtimeleft && (($expiretwouphalfleech_torrent && $promotionTimeType == 0) || $promotionTimeType == 2))
			{
				if ($promotionTimeType == 2) {
					$futuretime = strtotime($promotionUntil);
				} else {
					$futuretime = strtotime($added) + $expiretwouphalfleech_torrent * 86400;
				}
				$timeout = gettime(date("Y-m-d H:i:s", $futuretime), false, false, true, false, true);
				if ($timeout)
				$onmouseover = " ".$lang_functions['text_will_end_in'].$timeout;
				else $promotion = 1;
			}
			break;
		}
		case 7:
		{
			if ($showtimeleft && (($expirethirtypercentleech_torrent && $promotionTimeType == 0) || $promotionTimeType == 2))
			{
				if ($promotionTimeType == 2) {
					$futuretime = strtotime($promotionUntil);
				} else {
					$futuretime = strtotime($added) + $expirethirtypercentleech_torrent * 86400;
				}
				$timeout = gettime(date("Y-m-d H:i:s", $futuretime), false, false, true, false, true);
				if ($timeout)
				$onmouseover = " ".$lang_functions['text_will_end_in'].$timeout;
				else $promotion = 1;
			}
			break;
		}
	}
	}
	if (($CURUSER['appendpromotion'] == 'word' && $forcemode == "" ) || $forcemode == 'word'){
        $log .= ", user appendpromotion = word";
		if(($promotion==2 && $globalSpState == 1) || $globalSpState == 2){
		    $log .= ", promotion or global_sp_state = 2";
			$sp_torrent = $onmouseover;
		}
		elseif(($promotion==3 && $globalSpState == 1) || $globalSpState == 3){
            $log .= ", promotion or global_sp_state = 3";
			$sp_torrent = $onmouseover;
		}
		elseif(($promotion==4 && $globalSpState == 1) || $globalSpState == 4){
            $log .= ", promotion or global_sp_state = 4";
			$sp_torrent = $onmouseover;
		}
		elseif(($promotion==5 && $globalSpState == 1) || $globalSpState == 5){
            $log .= ", promotion or global_sp_state = 5";
			$sp_torrent = $onmouseover;
		}
		elseif(($promotion==6 && $globalSpState == 1) || $globalSpState == 6){
            $log .= ", promotion or global_sp_state = 6";
			$sp_torrent = $onmouseover;
		}
		elseif(($promotion==7 && $globalSpState == 1) || $globalSpState == 7){
            $log .= ", promotion or global_sp_state = 7";
			$sp_torrent = $onmouseover;
		}
	}
	elseif (($CURUSER['appendpromotion'] == 'icon' && $forcemode == "") || $forcemode == 'icon'){
        $log .= ", user appendpromotion = icon";
		if(($promotion==2 && $globalSpState == 1) || $globalSpState == 2) {
            $log .= ", promotion or global_sp_state = 2";
            $sp_torrent = $onmouseover;
        }
		elseif(($promotion==3 && $globalSpState == 1) || $globalSpState == 3) {
            $log .= ", promotion or global_sp_state = 3";
            $sp_torrent = $onmouseover;
        }
		elseif(($promotion==4 && $globalSpState == 1) || $globalSpState == 4) {
            $log .= ", promotion or global_sp_state = 4";
            $sp_torrent = $onmouseover;
        }
		elseif(($promotion==5 && $globalSpState == 1) || $globalSpState == 5) {
            $log .= ", promotion or global_sp_state = 5";
            $sp_torrent = $onmouseover;
        }
		elseif(($promotion==6 && $globalSpState == 1) || $globalSpState == 6) {
            $log .= ", promotion or global_sp_state = 6";
            $sp_torrent = $onmouseover;
        }
		elseif(($promotion==7 && $globalSpState == 1) || $globalSpState == 7) {
            $log .= ", promotion or global_sp_state = 7";
            $sp_torrent = $onmouseover;
        }
	}
	do_log("$log, sp_torrent: $sp_torrent");
	return $sp_torrent;
}

function get_hr_img(array $torrent, $searchBoxId)
{
    $mode = \App\Models\HitAndRun::getConfig('mode', $searchBoxId);
    $result = '';
    if ($mode == \App\Models\HitAndRun::MODE_GLOBAL || ($mode == \App\Models\HitAndRun::MODE_MANUAL && isset($torrent['hr']) && $torrent['hr'] == \App\Models\Torrent::HR_YES)) {
        $result = '<img class="hitandrun" src="pic/trans.gif" alt="H&R" title="H&R" />';
    }
    return $result;
}

function get_user_id_from_name($username){
	return \App\Support\LegacyAuth::userIdFromName((string) $username);
}

function is_forum_moderator($id, $in = 'post'){
	return \App\Support\Forum::isModerator($id, (string) $in);
}

function get_guest_lang_id(){
	global $CURLANGDIR;
	return \App\Support\Locale::guestId((string) $CURLANGDIR);
}

function set_forum_moderators($name, $forumid, $limit=3){
	\App\Support\Forum::setModerators((string) $name, $forumid, (int) $limit);
}

function get_plain_username($id){
	return \App\Support\UserDisplay::plainUsername((int) $id);
}

function get_searchbox_value($mode = 1, $item = 'showsubcat'){
	global $Cache;
	return \App\Support\SearchBox::value($Cache, $mode, (string) $item);
}

function get_ratio($userid, $html = true){
	$row = get_user_row($userid);
    if (empty($row)) {
        return "---";
    }
	$uped = (float)($row['uploaded'] ?? 0);
	$downed = (float)($row['downloaded'] ?? 0);

	if ($html) {
		return \App\Support\Ratio::userRatioHtml($uped, $downed, nexus_trans("label.ratio"), nexus_trans("label.infinite"));
	}

	return \App\Support\Ratio::userRatioNumeric($uped, $downed);
}

function add_s($num, $es = false)
{
	global $lang_functions;
	return \App\Support\Strings::pluralize((float)$num, '', $es ? ($lang_functions['text_es'] ?? '') : ($lang_functions['text_s'] ?? ''));
}

function is_or_are($num)
{
	global $lang_functions;
	return \App\Support\Strings::pluralize((float)$num, $lang_functions['text_is'] ?? '', $lang_functions['text_are'] ?? '');
}

function getmicrotime(){
	return \App\Support\Time::microtimeFloat();
}

function get_user_class_image($class){
	return \App\Support\UserClass::imagePath($class ?? null);
}

function user_can_upload($where = "torrents"){
	return \App\Support\LegacyResponse::canUpload($where);
}

function torrent_selection($name,$selname,$listname,$selectedid = 0, $mode = 0)
{
	global $lang_functions;
	$items = searchbox_item_list($listname, $mode);
	return \App\Support\Html::torrentSelect($name, $selname, $lang_functions['select_choose_one'] ?? '', (int) $selectedid, $items);
}

function get_hl_color($color=0)
{
	return \App\Support\Palette::forumHighlight((int) $color);
}

function get_forum_moderators($forumid, $plaintext = true)
{
	global $Cache;
	return \App\Support\Forum::moderators($Cache, $forumid, (bool) $plaintext);
}
function key_shortcut($page=1,$pages=1)
{
	return \App\Support\Html::keyShortcutScript((int) $page, (int) $pages);
}
function promotion_selection($selected = 0, $hide = 0)
{
	global $lang_functions;
	$labels = [
		'normal' => $lang_functions['text_normal'] ?? '',
		'free' => $lang_functions['text_free'] ?? '',
		'two_times_up' => $lang_functions['text_two_times_up'] ?? '',
		'free_two_times_up' => $lang_functions['text_free_two_times_up'] ?? '',
		'half_down' => $lang_functions['text_half_down'] ?? '',
		'half_down_two_up' => $lang_functions['text_half_down_two_up'] ?? '',
		'thirty_percent_down' => $lang_functions['text_thirty_percent_down'] ?? '',
	];
	return \App\Support\Html::promotionSelectOptions((int) $selected, (int) $hide, $labels);
}

function get_post_row($postid)
{
	global $Cache;
	return \App\Support\Forum::postRow($Cache, $postid);
}

function get_country_row($id)
{
	global $Cache;
	return \App\Support\Country::row($Cache, $id);
}


function valid_file_name($filename)
{
	return \App\Support\Validators::isFileName($filename);
}

function valid_class_name($filename)
{
	return \App\Support\Validators::isClassName($filename);
}

function return_avatar_image($url)
{
	global $CURLANGDIR;
	return \App\Support\UserDisplay::avatarImage((string) $url, (string) $CURLANGDIR);
}
function return_category_image($categoryid, $link="")
{
	return \App\Support\Category::imageTag((int) $categoryid, (string) $link);
}

/******************************************** bellow functioons avaliable since v1.6 ***********************************************************/

function torrentTags($tags = 0, $type = 'checkbox')
{
    global $lang_functions;
    return \App\Support\TorrentTags::render($tags, $type, [
        'text_tag_no_release_to_any_other' => $lang_functions['text_tag_no_release_to_any_other'] ?? '',
        'text_tag_first_release' => $lang_functions['text_tag_first_release'] ?? '',
        'text_tag_official' => $lang_functions['text_tag_official'] ?? '',
        'text_tag_diy' => $lang_functions['text_tag_diy'] ?? '',
        'text_tag_mother_language' => $lang_functions['text_tag_mother_language'] ?? '',
        'text_tag_mother_language_subtitle' => $lang_functions['text_tag_mother_language_subtitle'] ?? '',
        'text_tag_hdr' => $lang_functions['text_tag_hdr'] ?? '',
    ]);
}

function saveSetting(string $prefix, array $nameAndValue, string $autoload = 'yes'): void
{
    \App\Support\Settings::saveBatch($prefix, $nameAndValue, $autoload);
}

function getFullDirectory($dir)
{
	return \App\Support\Path::resolve($dir, ROOT_PATH);
}

function checkGuestVisit()
{
    \App\Support\SiteAccess::checkGuestVisit();
}

function render($view, $data = [], $return = false)
{
    return \App\Support\View::render((string) $view, (array) $data, (bool) $return, ROOT_PATH);
}

function canDoLogin()
{
    return \App\Support\SiteAccess::canDoLogin();
}

function build_table(array $header, array $rows, array $options = [])
{
	return \App\Support\Html::buildTable($header, $rows, $options);
}

/**
 * 返回链接中附件的key
 *
 * @param $url
 * @return string
 */
function attachmentKey($url)
{
    return \App\Support\Attachment::keyFromUrl((string) $url);
}

/**
 * 根据key返回链接
 *
 * @param $location
 * @param null $width
 * @param null $height
 * @param array $options
 * @return string
 */
function attachmentUrl($location, $width = null, $height = null, $options = [])
{
    return \App\Support\Attachment::publicUrl((string) $location);
}


function strip_all_tags($text)
{
    //替换掉无参数标签
    $bbTags = [
        '[*]', '[b]', '[/b]', '[i]', '[/i]', '[u]', '[/u]', '[s]', '[/s]', '[pre]', '[/pre]', '[quote]', '[/quote]',
        '[/color]', '[/font]', '[/size]', '[/url]', '[/youtube]', '[/spoiler]',
    ];
    $text = str_replace($bbTags, '', $text);
    //替换掉有参数标签
    $pattern = '/\[url=.*\]|\[color=.*\]|\[font=.*\]|\[size=.*\]|\[youtube.*\]|\[spoiler.*\]/isU';
    $text = preg_replace($pattern, "", $text);
    //去掉表情
    static $emoji = null;
    if (is_null($emoji)) {
        $emoji = nexus_config('emoji');
    }
//    $text = preg_replace("/\[em([1-9][0-9]*)\]/isU", "", $text);
    $text = preg_replace_callback("/\[em([1-9][0-9]*)\]/isU", function ($matches) use ($emoji) {
        return $emoji[$matches[1]] ?? '';
    }, $text);

    $text = strip_tags($text);

    return trim($text);
}

function format_description($description)
{
    //替换附件
    $pattern = '/(\[attach\](.*)\[\/attach\])/isU';
    $matchCount = preg_match_all($pattern, $description, $matches);
    if ($matchCount) {
        $attachments = \App\Models\Attachment::query()->whereIn('dlkey', $matches[2])->get()->keyBy('dlkey');
        if ($attachments->isNotEmpty()) {
            $description = preg_replace_callback($pattern, function ($matches) use ($attachments) {
                $item = $attachments->get($matches[2]);
                $url = \Nexus\Attachment\Storage::getDriver($item->driver)->getImageUrl($item->location);
                do_log(sprintf("location: %s, driver: %s, url: %s", $item->location, $item->driver, $url));
                return str_replace($matches[2], $url, $matches[1]);
            }, $description);
        }
    }
    //去除引用
//    $pattern = '/\[quote.*\].*\[\/quote\]/is';
//    $description = preg_replace($pattern, '', $description);

    //去掉引用自
    $pattern = '/\[quote=.*\]/isU';
    $description = preg_replace_callback($pattern, function ($matches) {
        return '[quote]';
    }, $description);

    //过虑多层引用
    $delimiter = '__CYLX__';
    $pattern = '/(\[quote\]){2,}(((?!\[quote\]).)*)\[\/quote\]/isU';
    $description = preg_replace_callback($pattern, function ($matches) use ($delimiter) {
        return $delimiter;
    }, $description);

    $pattern = "/$delimiter(((?!\[quote\]).)+)\[\/quote\]/is";
    $description = preg_replace_callback($pattern, function ($matches) use ($delimiter) {
        $arr = array_reverse(explode('[/quote]', $matches[0]));
        foreach ($arr as $value) {
            $value = trim(str_replace($delimiter, '', $value));
            if (!empty($value)) {
                return "[quote]{$value}[/quote]";
            }
        }
    }, $description);


    //匹配不同块
    $attachPattern = '\[attach\].*\[\/attach\]';
    $imgPattern = '\[img\].*\[\/img\]';
    $imgPattern2 = '\[img=.*\]';
    $urlPattern = '\[url=.*\].*\[\/url\]';
    $quotePattern = '\[quote.*\].*\[\/quote\]';
    $pattern = "/($attachPattern)|($imgPattern)|($imgPattern2)|($urlPattern)|($quotePattern)/isU";
//    $pattern = "/($attachPattern)|($imgPattern)|($urlPattern)/isU";
    $delimiter = '{{{}}}';
    $description = preg_replace_callback($pattern, function ($matches) use ($delimiter) {
        return $delimiter . $matches[0] . $delimiter;
    }, $description);

    //再进行分割
    $descriptionArr = preg_split("/[$delimiter]+/", $description);
    $results = [];
    foreach ($descriptionArr as $item) {
        if (preg_match('/\[attach\](.*)\[\/attach\]/isU', $item, $matches)) {
            //是否附件
            $results[] = [
                'type' => 'attachment',
                'data' => [
                    'url' => $matches[1]
                ]
            ];
        } elseif (preg_match('/\[img\](.*)\[\/img\]/isU', $item, $matches)) {
            //是否图片
            $results[] = [
                'type' => 'image',
                'data' => [
                    'url' => $matches[1]
                ]
            ];
        } elseif (preg_match('/\[img=(.*)\]/isU', $item, $matches)) {
            //是否图片
            $results[] = [
                'type' => 'image',
                'data' => [
                    'url' => $matches[1]
                ]
            ];
        } elseif (preg_match('/\[url=(.*)\](.*)\[\/url\]/isU', $item, $matches)) {
            $results[] = [
                'type' => 'url',
                'data' => [
                    'url' => $matches[1],
                    'text' => strip_all_tags($matches[2])
                ]
            ];
        } elseif (preg_match('/\[quote=?(.*)\](.*)\[\/quote\]/isU', $item, $matches)) {
            $results[] = [
                'type' => 'quote',
                'data' => [
                    'quote_text' => $matches[1],
                    'text' => strip_all_tags($matches[2]),
                ]
            ];
        } elseif (!empty($item)) {
            $results[] = [
                'type' => 'text',
                'data' => [
                    'text' => strip_all_tags($item)
                ]
            ];
        }
    }
//        dd($description, $results);
    return $results;
}

function get_image_from_description(array $descriptionArr, $first = false, $useDefault = true)
{
	if ($first) {
		$defaultUrl = $useDefault ? getSchemeAndHttpHost() . "/pic/nophoto.gif" : '';
		return \App\Support\Description::firstImageUrl($descriptionArr, $defaultUrl);
	}
	return \App\Support\Description::imageUrls($descriptionArr);
}

function resize_image($url, $with = null, $height = null, $fit = "cover")
{
    $scheme = parse_url($url, PHP_URL_SCHEME);
    if ($scheme === false) {
        return $url;
    }
    $url = "$scheme://images.weserv.nl/?url=$url";
    if ($with !== null) {
        $url .= "&w=$with";
    }
    if ($height !== null) {
        $url .= "&h=$height";
    }
    $url .= "&fit=$fit";
    return $url;
}

function get_share_ratio($uploaded, $downloaded)
{
    return \App\Support\Ratio::share((float)$uploaded, (float)$downloaded);
}

function EchoRow($class = ''){
	$args = func_get_args();
	$class = array_shift($args);
	if (count($args) === 0) {
		return \App\Support\Html::tableRow('');
	}
	return \App\Support\Html::tableRow((string) $class, ...$args);
}

function list_require_search_box_id()
{
    $setting = get_setting('main');
    $maps = [
        'torrents' => [$setting['browsecat']],
        'special' => [$setting['specialcat']],
        'usercp' => [$setting['browsecat'], $setting['specialcat']],
        'getrss' => [$setting['browsecat'], $setting['specialcat']],
        'userdetails' => [$setting['browsecat'], $setting['specialcat']],
        'offers' => [$setting['browsecat'], $setting['specialcat']],
        'details' => [$setting['browsecat'], $setting['specialcat']],
        'search' => [$setting['browsecat'], $setting['specialcat']],
    ];
    return $maps[nexus()->getScript()] ?? [];
}

function can_access_torrent($torrent, $uid)
{
    return \App\Support\TorrentAccess::canAccess($torrent, $uid);
}

function get_ip_location_from_geoip($ip): bool|array
{
    $locationInfo = \Nexus\Database\NexusDB::remember("locations_{$ip}", 864000, function () use ($ip) {
        $lang = get_langfolder_cookie();
        $langMap = [
            'chs' => 'zh-CN',
            'cht' => 'zh-CN',
            'en' => 'en',
        ];
        $locale = $langMap[$lang] ?? $lang;
        $info = [
            'ip' => $ip,
            'version' => '',
            'country' => '',
            'city' => '',
            'country_en' => '',
            'city_en' => '',
            'continent_en' => '',
        ];
        try {
            $database = nexus_env('GEOIP2_DATABASE');
            if (empty($database)) {
                do_log("no geoip2 database.");
                return false;
            }
            if (!is_readable($database)) {
                do_log("geoip2 database: $database is not readable.");
                return false;
            }
            $reader = new \GeoIp2\Database\Reader($database);
            $record = $reader->city($ip);
            $countryName =  $record->country->names[$locale] ?? $record->country->names['en'] ?? '';
            $cityName = $record->city->names[$locale] ?? $record->city->names['en'] ?? '';
            $continentName = $record->continent->names[$locale] ?? $record->continent->names['en'] ?? '';
            if (isIPV4($ip)) {
                $info['version'] = 4;
            } elseif (isIPV6($ip)) {
                $info['version'] = 6;
            }
            $info['country'] = $countryName;
            $info['country_en'] = $record->country->names['en'] ?? '';
            $info['city'] = $cityName;
            $info['city_en'] = $record->city->names['en'] ?? '';
            $info['continent'] = $continentName;
            $info['continent_en'] = $record->continent->names['en'] ?? '';
        } catch (\Exception $exception) {
            do_log($exception->getMessage() . ", trace: " .  $exception->getTraceAsString(), 'error');
        }
        return $info;
    });
    do_log("ip: $ip, result: " . nexus_json_encode($locationInfo));
    if ($locationInfo === false) {
        return false;
    }
    $name = sprintf('%s[v%s]', $locationInfo['city'] ? ($locationInfo['city'] . "·" . $locationInfo['country']) : $locationInfo['country'], $locationInfo['version']);
    return [
        'name' => $name,
        'location_main' => '',
        'location_sub' => '',
        'flagpic' => '',
        'start_ip' => $ip,
        'end_ip' => $ip,
        'ip_version' => $locationInfo['version'],
        'country_en' => $locationInfo['country_en'],
        'city_en' => $locationInfo['city_en'],
        'continent_en' => $locationInfo['continent_en'],
    ];
}

function msgalert($url, $text, $bgcolor = "red")
{
	echo \App\Support\Html::messageAlert($url, $text, $bgcolor);
}

function build_medal_image(\Illuminate\Support\Collection $medals, $maxHeight = 200, $withActions = false): string
{
    return \App\Support\Medal::buildImages($medals, $maxHeight, (bool) $withActions);
}

function insert_torrent_tags($torrentId, $tagIdArr, $sync = false)
{
    \App\Support\TorrentTags::insert($torrentId, $tagIdArr, (bool) $sync);
}

function get_smile($num)
{
	return \App\Support\Smilies::pathFor((int) $num);
}

function get_filament_class_alias($class): string
{
    return Str::of($class)
        ->replace(['/', '\\'], '.')
        ->explode('.')
        ->map([Str::class, 'kebab'])
        ->implode('.');
}

/**
 * Calculate user seed bonus per hour
 *
 * @param $uid
 * @param $torrentIdArr
 * @return array
 * @throws \Nexus\Database\DatabaseException
 */
function calculate_seed_bonus($uid, $torrentIdArr = null): array
{
    return \App\Support\Bonus::calculateForUser($uid, $torrentIdArr);
}


function calculate_harem_addition($uid)
{
    return \App\Support\Bonus::haremAddition($uid);
}


function build_search_box_category_table($mode, $checkboxValue, $categoryHrefPrefix, $taxonomyHrefPrefix, $taxonomyNameLength, $checkedValues = '', array $options = [])
{
    global $Cache;
    return \App\Support\SearchBox::buildCategoryTable(
        $Cache,
        $mode,
        $checkboxValue,
        $categoryHrefPrefix,
        $taxonomyHrefPrefix,
        $taxonomyNameLength,
        $checkedValues,
        $options,
    );
}

function datetimepicker_input($name, $value = '', $label = '', array $options = [])
{
    return \App\Support\Form::datetimepickerInput($name, $value, $label, $options);
}

function build_bonus_table(array $user, array $bonusResult = [], array $options = [])
{
    if (empty($bonusResult)) {
        $bonusResult = calculate_seed_bonus($user['id']);
    }
    $officialTag = get_setting('bonus.official_tag');
    $officialAdditionalFactor = get_setting('bonus.official_addition', 0);
    $haremFactor = get_setting('bonus.harem_addition');
    $haremAddition = calculate_harem_addition($user['id']);
    $isDonor = is_donor($user);
    $donortimes_bonus = get_setting('bonus.donortimes');

    return \App\Support\Bonus::buildBonusTable(
        $bonusResult,
        $isDonor,
        $donortimes_bonus,
        $officialTag,
        $officialAdditionalFactor,
        $haremFactor,
        $haremAddition,
        $options,
    );
}


function build_search_area($searchArea, array $options = [])
{
    return \App\Support\SearchBox::areaSelect($searchArea, $options);
}

function torrent_name_for_admin(\App\Models\Torrent|null $torrent, $withTags = false, $length = 40)
{
    return \App\Support\TorrentAccess::adminName($torrent, (bool) $withTags, (int) $length);
}

function username_for_admin(int $id)
{
    return \App\Support\UserDisplay::adminUsername($id);
}

function can_view_post($uid, $post)
{
    return \App\Support\Forum::canViewPost($uid, $post);
}

function hide_text($text) {
	return \App\Support\Strings::hidden((string)$text);
}

function make_content_disposition(string $filename, string $disposition = 'attachment'): string {
	return \App\Support\Http::contentDisposition($filename, $disposition);
}

function bbcode_attach_to_img(string $text) {
    return \App\Support\Attachment::bbcodeToImg($text);
}

?>
