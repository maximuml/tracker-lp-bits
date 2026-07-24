<?php

use App\Models\SearchBox;
use App\Models\TorrentExtra;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

function get_langfolder_cookie($transToLocale = false)
{
    $deflang = \App\Models\Setting::getDefaultLang();
	$lang = "";
	if (!isset($_COOKIE["c_lang_folder"])) {
		$lang = $deflang;
	} else {
		$langfolder_array = get_langfolder_list();
		$enabled = \App\Models\Language::listEnabled();
		foreach($langfolder_array as $lf)
		{
			if($lf == $_COOKIE["c_lang_folder"] && in_array($lf, $enabled)) {
                $lang = $_COOKIE["c_lang_folder"];
                break;
            }
		}
	}
	if (!$lang) {
	    $lang = $deflang;
    }
	if (!$transToLocale) {
	    return $lang;
    }
	return \App\Http\Middleware\Locale::$languageMaps[$lang] ?? 'en';
}

function get_user_lang($user_id)
{
	$lang = mysql_fetch_assoc(sql_query("SELECT site_lang_folder FROM language LEFT JOIN users ON language.id = users.lang WHERE language.site_lang=1 AND users.id= ". sqlesc($user_id) ." LIMIT 1"));
	return $lang['site_lang_folder'] ?: 'en';
}

function get_langfile_path($script_name ="", $target = false, $lang_folder = "")
{
	global $CURLANGDIR;
	$CURLANGDIR = get_langfolder_cookie();
	if($lang_folder == "")
	{
		$lang_folder = $CURLANGDIR;
	}
	$result = "lang/" . ($target == false ? $lang_folder : "_target") ."/lang_". ( $script_name == "" ? substr(strrchr($_SERVER['SCRIPT_NAME'],'/'),1) : $script_name);
    return $result;
}

function get_row_sum($table, $field, $suffix = "")
{
	$r = sql_query("SELECT SUM($field) FROM $table $suffix") or sqlerr(__FILE__, __LINE__);
	$a = mysql_fetch_row($r);
	return $a[0];
}

function get_single_value($table, $field, $suffix = ""){
	$r = sql_query("SELECT $field FROM $table $suffix LIMIT 1") or sqlerr(__FILE__, __LINE__);
	$a = mysql_fetch_row($r);
	if ($a) {
		return $a[0];
	} else {
		return false;
	}
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
	print("<table border=\"0\" bgcolor=\"blue\" align=\"left\" cellspacing=\"0\" cellpadding=\"10\" style=\"background: blue;\">" .
	"<tr><td class=\"embedded\"><font color=\"white\"><h1>SQL Error</h1>\n" .
	"<b>" . mysql_error() . ($file != '' && $line != '' ? "<p>in $file, line $line</p>" : "") . "</b></font></td></tr></table>");
	die;
}

function format_quotes($s)
{
    return \App\Support\BBCode::quotes((string) $s, (string) nexus_trans("label.text_quote"));
}


function print_attachment($dlkey, $enableimage = true, $imageresizer = true)
{
	$httpdirectory_attachment = get_setting('attachment.httpdirectory');
	if (strlen($dlkey) == 32){
	if (!$row = \Nexus\Database\NexusDB::cache_get('attachment_'.$dlkey.'_content')){
		$res = sql_query("SELECT * FROM attachments WHERE dlkey=".sqlesc($dlkey)." LIMIT 1") or sqlerr(__FILE__,__LINE__);
		$row = mysql_fetch_array($res);
        \Nexus\Database\NexusDB::cache_put('attachment_'.$dlkey.'_content', $row, 86400);
	}
	}
	if (!$row)
	{
		return "<div style=\"text-decoration: line-through; font-size: 7pt\">".nexus_trans('attachment.text_key').$dlkey.nexus_trans('attachment.not_found')."</div>";
	}
	else{
	$id = $row['id'];
	if ($row['isimage'] == 1)
	{
		if ($enableimage){
            $driver = $row['driver'] ?? 'local';
            if ($driver == "local") {
                if ($row['thumb'] == 1){
                    $url = $httpdirectory_attachment."/".$row['location'].".thumb.jpg";
                } else {
                    $url = $httpdirectory_attachment."/".$row['location'];
                }
            } else {
                $url = \Nexus\Attachment\Storage::getDriver($driver)->getImageUrl($row['location']);
            }
            do_log(sprintf("driver: %s, location: %s, url: %s", $driver, $row['location'], $url));
			if($imageresizer == true)
				$onclick = " data-zoomable data-zoom-src=\"".$url."\"";
			else $onclick = "";
			$return = "<img id=\"attach".$id."\" style=\"max-width: 700px\" alt=\"".htmlspecialchars($row['filename'])."\" src=\"".$url."\"". $onclick .  " onmouseover=\"domTT_activate(this, event, 'content', '".htmlspecialchars("<strong>".nexus_trans('attachment.size')."</strong>: ".mksize($row['filesize'])."<br />".gettime($row['added']))."', 'styleClass', 'attach', 'x', findPosition(this)[0], 'y', findPosition(this)[1]-58);\" />";
		}
		else $return = "";
	}
	else
	{
		switch($row['filetype'])
		{
			case 'application/x-bittorrent': {
				$icon = "<img alt=\"torrent\" src=\"pic/attachicons/torrent.gif\" />";
				break;
			}
			case 'application/zip':{
				$icon = "<img alt=\"zip\" src=\"pic/attachicons/archive.gif\" />";
				break;
			}
			case 'application/rar':{
				$icon = "<img alt=\"rar\" src=\"pic/attachicons/archive.gif\" />";
				break;
			}
			case 'application/x-7z-compressed':{
				$icon = "<img alt=\"7z\" src=\"pic/attachicons/archive.gif\" />";
				break;
			}
			case 'application/x-gzip':{
				$icon = "<img alt=\"gzip\" src=\"pic/attachicons/archive.gif\" />";
				break;
			}
			case 'audio/mpeg':{
			}
			case 'audio/ogg':{
				$icon = "<img alt=\"audio\" src=\"pic/attachicons/audio.gif\" />";
				break;
			}
			case 'video/x-flv':{
				$icon = "<img alt=\"flv\" src=\"pic/attachicons/flv.gif\" />";
				break;
			}
			default: {
				$icon = "<img alt=\"other\" src=\"pic/attachicons/common.gif\" />";
			}
		}
		$return = "<div class=\"attach\">".$icon."&nbsp;&nbsp;<a href=\"".htmlspecialchars("getattachment.php?id=".$id."&dlkey=".$dlkey)."\" target=\"_blank\" id=\"attach".$id."\" onmouseover=\"domTT_activate(this, event, 'content', '".htmlspecialchars("<strong>".nexus_trans('attachment.downloads')."</strong>: ".number_format($row['downloads'])."<br />".gettime($row['added']))."', 'styleClass', 'attach', 'x', findPosition(this)[0], 'y', findPosition(this)[1]-58);\">".htmlspecialchars($row['filename'])."</a>&nbsp;&nbsp;<font class=\"size\">(".mksize($row['filesize']).")</font></div>";
	}
	return $return;
	}
}

function addTempCode($value) {
	global $tempCode, $tempCodeCount;
	$tempCode[$tempCodeCount] = $value;
	$return = "<tempCode_$tempCodeCount>";
	$tempCodeCount++;
	return $return;
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


function format_urls($text, $newWindow = false) {
//	return preg_replace("/((https?|ftp|gopher|news|telnet|mms|rtsp):\/\/[^()\[\]<>\s]+)/ei", "formatUrl('\\1', ".($newWindow==true ? 1 : 0).", '', 'faqlink')", $text);
	return preg_replace_callback("/((https?|ftp|gopher|news|telnet|mms|rtsp):\/\/[^()\[\]<>\s]+)/i", function ($matches) use ($newWindow) {
	    return formatUrl($matches[1], $newWindow, '', 'faqlink');
    }, $text);
}
function format_comment($text, $strip_html = true, $xssclean = false, $newtab = true, $imageresizer = true, $image_max_width = 700, $enableimage = true, $enableflash = true , $imagenum = -1, $image_max_height = 0)
{
	global $lang_functions;
	global $CURUSER, $SITENAME, $BASEURL;
	global $tempCode, $tempCodeCount;
    if ($text == '') {
        return "";
    }
    $enableattach_attachment = get_setting('attachment.enableattach');
	$tempCode = array();
	$tempCodeCount = 0;
	$imageresizer = $imageresizer ? 1 : 0;
	$s = $text;

	if ($strip_html) {
		$s = htmlspecialchars($s);
	}

	if (strpos($s,"[code]") !== false && strpos($s,"[/code]") !== false) {
//		$s = preg_replace("/\[code\](.+?)\[\/code\]/eis","formatCode('\\1')", $s);
		$s = preg_replace_callback("/\[code\](.+?)\[\/code\]/is",function ($matches) {
		    return formatCode($matches[1]);
        }, $s);
	}

    if (strpos($s,"[raw]") !== false && strpos($s,"[/raw]") !== false) {
        $s = preg_replace_callback("/\[raw\](.+?)\[\/raw\]/is",function ($matches) {
            return addTempCode($matches[1]);
        }, $s);
    }

    // Linebreaks
    $s = nl2br($s);

	$originalBbTagArray = array('[siteurl]', '[site]','[*]', '[b]', '[/b]', '[i]', '[/i]', '[u]', '[/u]', '[s]', '[/s]', '[pre]', '[/pre]', '[/color]', '[/font]', '[/size]', '[hr]', "  ");
	$replaceXhtmlTagArray = array(get_protocol_prefix().get_setting('basic.BASEURL'), get_setting('basic.SITENAME'), '&#x2022; ', '<b>', '</b>', '<i>', '</i>', '<u>', '</u>', '<s>', '</s>', '<pre>', '</pre>', '</span>', '</font>', '</font>', '<hr>', ' &nbsp;');
	$s = str_replace($originalBbTagArray, $replaceXhtmlTagArray, $s);

	$originalBbTagArray = array("/\[font=([^\[\(&\\;]+?)\]/is", "/\[color=([#0-9a-z]{1,15})\]/is", "/\[color=([a-z]+)\]/is", "/\[size=([1-7])\]/is");
	$replaceXhtmlTagArray = array("<font face=\"\\1\">", "<span style=\"color: \\1;word-break: break-word\">", "<span style=\"color: \\1;word-break: break-word\">", "<font size=\"\\1\">");
	$s = preg_replace($originalBbTagArray, $replaceXhtmlTagArray, $s);


	if ($enableimage) {
//		$s = preg_replace("/\[img\]([^\<\r\n\"']+?)\[\/img\]/ei", "formatImg('\\1',".$imageresizer.",".$image_max_width.",".$image_max_height.")", $s, $imagenum, $imgReplaceCount);
		$s = preg_replace_callback("/\[img\]([^\<\r\n\"']+?)\[\/img\]/i", function ($matches) use ($imageresizer, $image_max_width, $image_max_height) {
		    return formatImg($matches[1],$imageresizer,$image_max_width,$image_max_height);
        }, $s, $imagenum, $imgReplaceCount);

//		$s = preg_replace("/\[img=([^\<\r\n\"']+?)\]/ei", "formatImg('\\1',".$imageresizer.",".$image_max_width.",".$image_max_height.")", $s, ($imagenum != -1 ? max($imagenum-$imgReplaceCount, 0) : -1));
		$s = preg_replace_callback("/\[img=([^\<\r\n\"']+?)\]/i", function ($matches) use ($imageresizer, $image_max_width, $image_max_height) {
		    return formatImg($matches[1],$imageresizer,$image_max_width,$image_max_height);
        }, $s, ($imagenum != -1 ? max($imagenum-$imgReplaceCount, 0) : -1));
	} else {
		$s = preg_replace("/\[img\]([^\<\r\n\"']+?)\[\/img\]/i", '', $s, -1);
		$s = preg_replace("/\[img=([^\<\r\n\"']+?)\]/i", '', $s, -1);
	}

    //[youtube,560,315]https://www.youtube.com/watch?v=DWDL3VTCcCg&ab_channel=ESPNMMA[/youtube]
	if (str_contains($s, '[youtube') && str_contains($s, 'v=')) {
        $s = preg_replace_callback("/\[youtube(\,([1-9][0-9]*)\,([1-9][0-9]*))?\]((http|https):\/\/[^\s'\"<>]+)\[\/youtube\]/i", function ($matches) {
            return formatYoutube($matches[4], $matches[2], $matches[3]);
        }, $s);
    }
    if (str_contains($s, "[video")) {
        $s = preg_replace_callback("/\[video(\,([1-9][0-9]*)\,([1-9][0-9]*))?\]((http|https):\/\/[^\s'\"<>]+)\[\/video\]/i", function ($matches) {
            return formatVideo($matches[4], $matches[2], $matches[3]);
        }, $s);
    }
    if (str_contains($s, "[audio")) {
        $s = preg_replace_callback("/\[audio\]((http|https):\/\/[^\s'\"<>]+)\[\/audio\]/i", function ($matches) {
            return formatAudio($matches[1]);
        }, $s);

    }

	// [url=http://www.example.com]Text[/url]
	$s = preg_replace_callback("/\[url=([^\[\s]+?)\](.+?)\[\/url\]/i", function ($matches) use ($newtab) {
	    return formatUrl($matches[1], $newtab, $matches[2], 'faqlink');
    }, $s);

	// [url]http://www.example.com[/url]
//	$s = preg_replace("/\[url\]([^\[\s]+?)\[\/url\]/ei", "formatUrl('\\1', ".($newtab==true ? 1 : 0).", '', 'faqlink')", $s);
	$s = preg_replace_callback("/\[url\]([^\[\s]+?)\[\/url\]/i", function ($matches) use ($newtab) {
	    return formatUrl($matches[1], $newtab, '', 'faqlink');
    }, $s);

    // [left]Left text[/left]
    $s = preg_replace_callback("/\[left\](.*)\[\/left\]/isU", function ($matches) {
        return formatTextAlign($matches[1], 'left');
    }, $s);

    // [center]Center text[/center]
    $s = preg_replace_callback("/\[center\](.*)\[\/center\]/isU", function ($matches) {
        return formatTextAlign($matches[1], 'center');
    }, $s);

    // [right]Right text[/right]
    $s = preg_replace_callback("/\[right\](.*)\[\/right\]/isU", function ($matches) {
        return formatTextAlign($matches[1], 'right');
    }, $s);

    // [hide]Hidden text[/hide]
    $s = preg_replace_callback("/\[hide\](.*)\[\/hide\]/isU", function ($matches) {
        return formatHidden($matches[1]);
    }, $s);


	$s = format_urls($s, $newtab);
	// Quotes
	if (strpos($s,"[quote") !== false && strpos($s,"[/quote]") !== false) { //format_quote is kind of slow. Better check if [quote] exists beforehand
		$s = format_quotes($s);
	}

//	$s = preg_replace("/\[em([1-9][0-9]*)\]/ie", "(\\1 < 192 ? '<img src=\"pic/smilies/\\1.gif\" alt=\"[em\\1]\" />' : '[em\\1]')", $s);
	$s = preg_replace_callback("/\[em([1-9][0-9]*)\]/i", function ($matches) {
	    $smile = get_smile($matches[1]);
	    return $smile ? '<img src="'.$smile.'" alt="[em' . $matches[1] . ']" />' : '[em' . $matches[1] . ']';
    }, $s);

    //[spoiler=What happens to the hero?]The hero dies at the end![/spoiler]
    if (str_contains($s, '[spoiler')) {
        $s = preg_replace_callback("/\[spoiler(=(.*))?\](.*)\[\/spoiler\]/isU", function ($matches) {
            return formatSpoiler($matches[3], $matches[2], nexus()->getScript() != 'preview');
        }, $s);
    }

    if ($enableattach_attachment == 'yes' && $imagenum != 1){
        $limit = 20;
//		$s = preg_replace("/\[attach\]([0-9a-zA-z][0-9a-zA-z]*)\[\/attach\]/ies", "print_attachment('\\1', ".($enableimage ? 1 : 0).", ".($imageresizer ? 1 : 0).")", $s, $limit);
        $s = preg_replace_callback("/\[attach\]([0-9a-zA-z][0-9a-zA-z]*)\[\/attach\]/is", function ($matches) use ($enableimage, $imageresizer) {
            return print_attachment($matches[1], ".($enableimage ? 1 : 0).", ".($imageresizer ? 1 : 0).");
        }, $s, $limit);
    }

	reset($tempCode);
	$j = $i = 0;
	while(count($tempCode) || $j > 5) {
		foreach($tempCode as $key=>$code) {
			$s = str_replace("<tempCode_$key>", $code, $s, $count);
			if ($count) {
				unset($tempCode[$key]);
				$i = $i+$count;
			}
		}
		$j++;
	}
    return str_replace('', '', $s);
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
    \App\Models\SiteLog::query()->insert([
        'added' => now(),
        'txt' => $text,
        'security_level' => $security,
        'uid' => get_user_id(),
    ]);
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

function begin_compose($title = "",$type="new", $body="", $hassubject=true, $subject="", $maxsubjectlength=100){
	global $lang_functions;
	if ($title)
		print("<h1 align=\"center\">".$title."</h1>");
	switch ($type){
		case 'new':
		{
			$framename = $lang_functions['text_new'];
			break;
		}
		case 'reply':
		{
			$framename = $lang_functions['text_reply'];
			break;
		}
		case 'quote':
		{
			$framename = $lang_functions['text_quote'];
			break;
		}
		case 'edit':
		{
			$framename = $lang_functions['text_edit'];
			break;
		}
		default:
		{
			$framename = $lang_functions['text_new'];
			break;
		}
	}
	begin_frame($framename, true);
	print("<table class=\"main\" width=\"100%\" border=\"1\" cellspacing=\"0\" cellpadding=\"5\">\n");
	if ($hassubject)
		print("<tr><td class=\"rowhead\">".$lang_functions['row_subject']."</td>" .
"<td class=\"rowfollow\" align=\"left\"><input type=\"text\" style=\"width: 99%;\" name=\"subject\" maxlength=\"".$maxsubjectlength."\" value=\"".htmlspecialchars($subject)."\" /></td></tr>\n");
	print("<tr><td class=\"rowhead\" valign=\"top\">".$lang_functions['row_body']."</td><td class=\"rowfollow\" align=\"left\"><span style=\"display: none;\" id=\"previewouter\"></span><div id=\"editorouter\">");
	textbbcode("compose","body", $body, false);
	print("</div></td></tr>");
}

function end_compose(){
	global $lang_functions;
	print("<tr><td colspan=\"2\" align=\"center\"><table><tr><td class=\"embedded\"><input id=\"qr\" type=\"submit\" class=\"btn\" value=\"".$lang_functions['submit_submit']."\" /></td><td class=\"embedded\">");
	print("<input type=\"button\" class=\"btn2\" name=\"previewbutton\" id=\"previewbutton\" value=\"".$lang_functions['submit_preview']."\" onclick=\"javascript:preview(this.parentNode);\" />");
	print("<input type=\"button\" class=\"btn2\" style=\"display: none;\" name=\"unpreviewbutton\" id=\"unpreviewbutton\" value=\"".$lang_functions['submit_edit']."\" onclick=\"javascript:unpreview(this.parentNode);\" />");
	print("</td></tr></table>");
	print("</td></tr>");
	print("</table>\n");
	end_frame();
	print("<p align=\"center\"><a href=\"tags.php\" target=\"_blank\">".$lang_functions['text_tags']."</a> | <a href=\"smilies.php\" target=\"_blank\">".$lang_functions['text_smilies']."</a></p>\n");
}

function insert_suggest($keyword, $userid, $pre_escaped = true)
{
	if(mb_strlen($keyword,"UTF-8") >= 2)
	{
		$userid = intval($userid ?? 0);
		if($userid)
		sql_query("INSERT INTO suggest(keywords, userid, adddate) VALUES (" . ($pre_escaped == true ? "'" . $keyword . "'" : sqlesc($keyword)) . "," . sqlesc($userid) . ", NOW())") or sqlerr(__FILE__,__LINE__);
	}
}

function get_external_tr($imdb_url = "")
{
    return '';
}

function get_torrent_extinfo_identifier($torrentid)
{
	$torrentid = intval($torrentid ?? 0);

	$result = array('imdb_id');
	unset($result);

	if($torrentid)
	{
		$res = sql_query("SELECT url FROM torrents WHERE id=" . $torrentid) or sqlerr(__FILE__,__LINE__);
		if(mysql_num_rows($res) == 1)
		{
			$arr = mysql_fetch_array($res) or sqlerr(__FILE__,__LINE__);

			$imdb_id = parse_imdb_id($arr["url"]);
			$result['imdb_id'] = $imdb_id;
		}
	}
	return $result;
}

function parse_imdb_id($url)
{
    if ($url && is_numeric($url) && strlen($url) < 7) {
        $url = str_pad($url, 7, '0', STR_PAD_LEFT);
    }
	if ($url != "" && preg_match("/[0-9]+/i", $url, $matches)) {
		return intval($matches[0]);
	}
	return null;
}

function build_imdb_url($imdb_id)
{
	return $imdb_id == "" ? "" : "https://www.imdb.com/title/tt" . $imdb_id . "/";
}

// it's a stub implemetation here, we need more acurate regression analysis to complete our algorithm
function get_torrent_2_user_value($user_snatched_arr)
{
	// check if it's current user's torrent
	$torrent_2_user_value = 1.0;

	$torrent_res = sql_query("SELECT * FROM torrents WHERE id = " . $user_snatched_arr['torrentid']) or sqlerr(__FILE__, __LINE__);
	if(mysql_num_rows($torrent_res) == 1)	// torrent still exists
	{
		$torrent_arr = mysql_fetch_array($torrent_res) or sqlerr(__FILE__, __LINE__);
		if($torrent_arr['owner'] == $user_snatched_arr['userid'])	// owner's torrent
		{
			$torrent_2_user_value *= 0.7;	// owner's torrent
			$torrent_2_user_value += ($user_snatched_arr['uploaded'] / $torrent_arr['size'] ) -1 > 0 ? 0.2 - exp(-(($user_snatched_arr['uploaded'] / $torrent_arr['size'] ) -1)) : ($user_snatched_arr['uploaded'] / $torrent_arr['size'] ) -1;
			$torrent_2_user_value += min(0.1 , ($user_snatched_arr['seedtime'] / 37*60*60 ) * 0.1);
		}
		else
		{
			if($user_snatched_arr['finished'] == 'yes')
			{
				$torrent_2_user_value *= 0.5;
				$torrent_2_user_value += ($user_snatched_arr['uploaded'] / $torrent_arr['size'] ) -1 > 0 ? 0.4 - exp(-(($user_snatched_arr['uploaded'] / $torrent_arr['size'] ) -1)) : ($user_snatched_arr['uploaded'] / $torrent_arr['size'] ) -1;
				$torrent_2_user_value += min(0.1, ($user_snatched_arr['seedtime'] / 22*60*60 ) * 0.1);
			}
			else
			{
				$torrent_2_user_value *= 0.2;
				$torrent_2_user_value += min(0.05, ($user_snatched_arr['leechtime'] / 24*60*60 ) * 0.1);	// usually leechtime could not explain much
			}
		}
	}
	else	// torrent already deleted, half blind guess, be conservative
	{

		if($user_snatched_arr['finished'] == 'no' && $user_snatched_arr['uploaded'] > 0 && $user_snatched_arr['downloaded'] == 0)	// possibly owner
		{
			$torrent_2_user_value *= 0.55;	//conservative
			$torrent_2_user_value += min(0.05, ($user_snatched_arr['leechtime'] / 31*60*60 ) * 0.1);
			$torrent_2_user_value += min(0.1, ($user_snatched_arr['seedtime'] / 31*60*60 ) * 0.1);
		}
		else if($user_snatched_arr['downloaded'] > 0)	// possibly leecher
		{
			$torrent_2_user_value *= 0.38;	//conservative
			$torrent_2_user_value *= min(0.22, 0.1 * $user_snatched_arr['uploaded'] / $user_snatched_arr['downloaded']);	// 0.3 for conservative
			$torrent_2_user_value += min(0.05, ($user_snatched_arr['leechtime'] / 22*60*60 ) * 0.1);
			$torrent_2_user_value += min(0.12, ($user_snatched_arr['seedtime'] / 22*60*60 ) * 0.1);
		}
		else
			$torrent_2_user_value *= 0.0;
	}
	return $torrent_2_user_value;
}

function cur_user_check () {
	global $lang_functions;
	global $CURUSER;
	if ($CURUSER)
	{
		sql_query("UPDATE users SET lang=" . get_langid_from_langcookie() . " WHERE id = ". $CURUSER['id']);
		stderr ($lang_functions['std_permission_denied'], $lang_functions['std_already_logged_in']);
	}
}

function KPS($type = "+", $point = "1.0", $id = "") {
	global $bonus_tweak;
	if ($point != 0){
		$point = sqlesc($point);
		if ($bonus_tweak == "enable" || $bonus_tweak == "disablesave"){
			sql_query("UPDATE users SET seedbonus = seedbonus$type$point WHERE id = ".sqlesc($id)) or sqlerr(__FILE__, __LINE__);
		}
	}
	else return;
}

function get_agent($peer_id, $agent)
{
	return \App\Support\Strings::userAgentClient((string)$agent);
}

function EmailBanned($newEmail)
{
	$newEmail = trim(strtolower((string) $newEmail));
	$sql = sql_query("SELECT * FROM bannedemails") or sqlerr(__FILE__, __LINE__);
	$list = mysql_fetch_array($sql);
	return \App\Support\Email::matchesRegexList($newEmail, (string) ($list['value'] ?? ''));
}

function EmailAllowed($newEmail)
{
	global $restrictemaildomain;
	if ($restrictemaildomain != 'yes') {
		return true;
	}
	$newEmail = trim(strtolower((string) $newEmail));
	$sql = sql_query("SELECT * FROM allowedemails") or sqlerr(__FILE__, __LINE__);
	$list = mysql_fetch_array($sql);
	return \App\Support\Email::matchesRegexList($newEmail, (string) ($list['value'] ?? ''));
}

function allowedemails()
{
	$sql = sql_query("SELECT * FROM allowedemails") or sqlerr(__FILE__, __LINE__);
	$list = mysql_fetch_array($sql);
	return $list['value'];
}

function nexus_redirect($url)
{
    if (substr($url, 0, 4) != 'http') {
        $url = getSchemeAndHttpHost() . '/' . trim($url, '/');
    }
	if(!headers_sent()){
	    header("Location: $url", true, 302);
	} else {
        echo "<script type=\"text/javascript\">window.location.href = '$url';</script>";
    }
	exit;
}

function set_cachetimestamp($id, $field = "cache_stamp")
{
	sql_query("UPDATE torrents SET $field = " . time() . " WHERE id = " . sqlesc($id)) or sqlerr(__FILE__, __LINE__);
}
function reset_cachetimestamp($id, $field = "cache_stamp")
{
	sql_query("UPDATE torrents SET $field = 0 WHERE id = " . sqlesc($id)) or sqlerr(__FILE__, __LINE__);
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
    do_log("to: $to, fromname: $fromname, fromemail: $fromemail, subject: $subject, body: $body. type: $type");
	global $lang_functions;
	global $rootpath,$SITENAME,$SITEEMAIL,$smtptype,$smtp,$smtp_host,$smtp_port,$smtp_from,$smtpaddress,$smtpport,$accountname,$accountpassword;
	# Is the OS Windows or Mac or Linux?
	if (strtoupper(substr(PHP_OS,0,3)=='WIN')) {
		$eol="\r\n";
		$windows = true;
	}
	elseif (strtoupper(substr(PHP_OS,0,3)=='MAC'))
		$eol="\r";
	else
		$eol="\n";
	if ($smtptype == 'none')
		return false;
	if ($smtptype == 'default') {
		@mail($to, "=?".$hdr_encoding."?B?".base64_encode($subject)."?=", $body, "From: ".$SITEEMAIL.$eol."Content-type: text/html; charset=".$hdr_encoding.$eol, "-f$SITEEMAIL") or stderr($lang_functions['std_error'], $lang_functions['text_unable_to_send_mail']);
	}
	elseif ($smtptype == 'advanced') {
		$mid = md5(getip() . $fromname);
		$name = $_SERVER["SERVER_NAME"];
        $headers = '';
		$headers .= "From: $fromname <$fromemail>".$eol;
		$headers .= "Reply-To: $fromname <$fromemail>".$eol;
		$headers .= "Return-Path: $fromname <$fromemail>".$eol;
		$headers .= "Message-ID: <$mid thesystem@$name>".$eol;
		$headers .= "X-Mailer: PHP v".phpversion().$eol;
		$headers .= "MIME-Version: 1.0".$eol;
		$headers .= "Content-type: text/html; charset=".$hdr_encoding.$eol;
		$headers .= "X-Sender: PHP".$eol;
		if ($multiple)
		{
			$bcc_multiplemail = "";
			foreach ($multiplemail as $toemail)
			$bcc_multiplemail = $bcc_multiplemail . ( $bcc_multiplemail != "" ? "," : "") . $toemail;

			$headers .= "Bcc: $multiplemail.$eol";
		}
		if ($smtp == "yes") {
			ini_set('SMTP', $smtp_host);
			ini_set('smtp_port', $smtp_port);
			if ($windows)
			ini_set('sendmail_from', $smtp_from);
		}

		@mail($to,"=?".$hdr_encoding."?B?".base64_encode($subject)."?=",$body,$headers) or stderr($lang_functions['std_error'], $lang_functions['text_unable_to_send_mail']);

		ini_restore('SMTP');
		ini_restore('smtp_port');
		if ($windows)
		ini_restore('sendmail_from');
	}
	elseif ($smtptype == 'external') {
	    /*
		require_once ($rootpath . 'include/smtp/smtp.lib.php');
		$mail = new smtp($hdr_encoding,'eYou');
		$mail->debug(true);
		$mail->open($smtpaddress, $smtpport);
		$mail->auth($accountname, $accountpassword);
		//	$mail->bcc($multiplemail);
		$mail->from($SITEEMAIL);
		if ($multiple)
		{
			$mail->multi_to_head($to);
			foreach ($multiplemail as $toemail)
			$mail->multi_to($toemail);
		}
		else
		$mail->to($to);
		$mail->mime_content_transfer_encoding();
		$mail->mime_charset('text/html', $hdr_encoding);
		$mail->subject($subject);
		$mail->body($body);
		$mail->send() or stderr($lang_functions['std_error'], $lang_functions['text_unable_to_send_mail']);
		$mail->close();
	    */

        /**
         * use Symfony Mailer instead
         *
         * @since 1.7
         * @author xiaomlove<1939737565@qq.com>
         */

        $toolRep = new \App\Repositories\ToolRepository();
        $sendResult = $toolRep->sendMail($to, $subject, $body);
        if ($sendResult === false) {
            stderr($lang_functions['std_error'], $lang_functions['text_unable_to_send_mail']);
        }
	}
	if ($showmsg) {
		if ($type == "confirmation")
		stderr($lang_functions['std_success'], $lang_functions['std_confirmation_email_sent']."<b>". htmlspecialchars($to) ."</b>.\n" .
		$lang_functions['std_please_wait'],false);
		elseif ($type == "details")
		stderr($lang_functions['std_success'], $lang_functions['std_account_details_sent']."<b>". htmlspecialchars($to) ."</b>.\n" .
		$lang_functions['std_please_wait'],false);
	}else
	return true;
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


function remaining ($type = 'login') {
	global $maxloginattempts;
	$total = 0;
	$ip = sqlesc(getip());
	$Query = sql_query("SELECT SUM(attempts) FROM loginattempts WHERE ip=$ip") or sqlerr(__FILE__, __LINE__);
	list($total) = mysql_fetch_array($Query);
	$remaining = $maxloginattempts - $total;
	if ($remaining <= 2 )
	$remaining = "<font color=\"red\" size=\"2\">[".$remaining."]</font>";
	else
	$remaining = "<font color=\"green\" size=\"2\">[".$remaining."]</font>";

	return $remaining;
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
    static $manager;

    if (!$manager) {
        $manager = new \App\Services\Captcha\CaptchaManager();
    }

    return $manager;
}

function image_code () {
    $driver = captcha_manager()->driver('image');

    if (!method_exists($driver, 'issue')) {
        throw new \RuntimeException('Image captcha driver is unavailable.');
    }

    return $driver->issue();
}

function check_code ($imagehash, $imagestring, $where = 'signup.php', $maxattemptlog = false, $head = true) {
    return \App\Support\LegacyAuth::checkCode((string) $imagehash, (string) $imagestring, (string) $where, (bool) $maxattemptlog, (bool) $head);
}


function show_image_code () {
    global $lang_functions;
    global $iv;

    if ($iv !== 'yes') {
        return;
    }

    $manager = captcha_manager();
    $driver = $manager->driver();

    if (!$driver->isEnabled()) {
        return;
    }

    $labelKey = $driver instanceof \App\Services\Captcha\Drivers\ImageCaptchaDriver
        ? 'row_security_image'
        : 'row_security_challenge';

    $labels = [
        'image' => $lang_functions[$labelKey] ?? $lang_functions['row_security_image'],
        'code' => $lang_functions['row_security_code'],
    ];

    $markup = $driver->render([
        'labels' => $labels,
        'secret' => $_GET['secret'] ?? '',
    ]);

    if ($markup !== '') {
        echo $markup;
    }
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
	$ipPattern =
	'/\b(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.' .
	'(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.' .
	'(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.' .
	'(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\b/';

	return preg_match($ipPattern, $ip);
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

function WriteConfig ($configname = NULL, $config = NULL) {
	global $lang_functions, $CONFIGURATIONS;

	if (file_exists('config/allconfig.php')) {
		require('config/allconfig.php');
	}
	if ($configname) {
		$$configname=$config;
	}
	$path = './config/allconfig.php';
	if (!file_exists($path) || !is_writable ($path)) {
		stdmsg($lang_functions['std_error'], $lang_functions['std_cannot_read_file']."[<b>".htmlspecialchars($path)."</b>]".$lang_functions['std_access_permission_note']);
	}
	$data = "<?php\n";
	foreach ($CONFIGURATIONS as $CONFIGURATION) {
		$data .= "\$$CONFIGURATION=".getExportedValue($$CONFIGURATION).";\n";
	}
	$fp = @fopen ($path, 'w');
	if (!$fp) {
		stdmsg($lang_functions['std_error'], $lang_functions['std_cannot_open_file']."[<b>".htmlspecialchars($path)."</b>]".$lang_functions['std_to_save_info'].$lang_functions['std_access_permission_note']);
	}
	$Res = @fwrite($fp, $data);
	if (empty($Res)) {
		stdmsg($lang_functions['std_error'], $lang_functions['text_cannot_save_info_in']."[<b>".htmlspecialchars($path)."</b>]".$lang_functions['std_access_permission_note']);
	}
	fclose($fp);
	return true;
}

function getExportedValue($input,$t = null) {
	return \App\Support\Codec::phpExport($input, $t);
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

function unesc($x) {
	return $x;
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
	if (!is_array($vars))
	$vars = explode(":", $vars);
	foreach ($vars as $v) {
		if (isset($_GET[$v]))
		$GLOBALS[$v] = unesc($_GET[$v]);
		elseif (isset($_POST[$v]))
		$GLOBALS[$v] = unesc($_POST[$v]);
		else
		return 0;
	}
	return 1;
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
	$langid = intval($langid ?? 0);
	$res = sql_query("SELECT * FROM language WHERE site_lang = 1 AND id = " . sqlesc($langid)) or sqlerr(__FILE__, __LINE__);
	if(mysql_num_rows($res) == 1)
	{
		$arr = mysql_fetch_array($res)  or sqlerr(__FILE__, __LINE__);
		return $arr['site_lang_folder'];
	}
	else return $deflang;
}

function get_if_restricted_is_open()
{
	// it's sunday
	if(\App\Models\Setting::getIsUploadOpenAtWeekend() && (date("w",time()) == '0' || (date("w",time()) == 6) && (date("G",time()) >=12 && date("G",time()) <=23)))
	{
		return true;
	}
	else
	return false;
}

function menu ($selected = "home") {
	global $lang_functions;
	global $BASEURL,$CURUSER;
	global $enableoffer, $enablespecial, $where_tweak;
	global $USERUPDATESET;
	//no this option in config.php
    $enablerequest = 'yes';
	$script_name = $_SERVER["SCRIPT_NAME"];
	if (preg_match("/index/i", $script_name)) {
		$selected = "home";
	}elseif (preg_match("/forums/i", $script_name)) {
		$selected = "forums";
	}elseif (preg_match("/torrents/i", $script_name)) {
		$selected = "torrents";
	}elseif (preg_match("/special/i", $script_name)) {
		$selected = "special";
	}elseif (preg_match("/offers/i", $script_name) OR preg_match("/offcomment/i", $script_name)) {
		$selected = "offers";
	}elseif (preg_match("/upload/i", $script_name)) {
		$selected = "upload";
	}elseif (preg_match("/usercp/i", $script_name)) {
		$selected = "usercp";
	}elseif (preg_match("/topten/i", $script_name)) {
		$selected = "topten";
	}elseif (preg_match("/log/i", $script_name)) {
		$selected = "log";
	}elseif (preg_match("/rules/i", $script_name)) {
		$selected = "rules";
	}elseif (preg_match("/faq/i", $script_name)) {
		$selected = "faq";
    }elseif (preg_match("/contactstaff/i", $script_name)) {
        $selected = "contactstaff";
    }elseif (preg_match("/staff/i", $script_name)) {
        $selected = "staff";
	}else
	$selected = "";
	$menu = apply_filter('nexus_menu');
	print ("<div id=\"nav\">");
	if ($menu) {
	    print $menu;
    } else {
	    $lang = get_langfolder_cookie();
        $normalSectionName = get_searchbox_value(get_setting('main.browsecat'), 'section_name');
        $specialSectionName = get_searchbox_value(get_setting('main.specialcat'), 'section_name');
        print ("<ul id=\"mainmenu\" class=\"menu\">");
        print ("<li" . ($selected == "home" ? " class=\"selected\"" : "") . "><a href=\"index.php\">" . $lang_functions['text_home'] . "</a></li>");
        print ("<li" . ($selected == "forums" ? " class=\"selected\"" : "") . "><a href=\"forums.php\">".$lang_functions['text_forums']."</a></li>");
        print ("<li" . ($selected == "torrents" ? " class=\"selected\"" : "") . "><a href=\"torrents.php\" rel='sub-menu'>".($normalSectionName[$lang] ?? $lang_functions['text_torrents'])."</a></li>");
        if ($enablespecial == 'yes' && user_can('view_special_torrent'))
            print ("<li" . ($selected == "special" ? " class=\"selected\"" : "") . "><a href=\"special.php\">".($specialSectionName[$lang] ?? $lang_functions['text_special'])."</a></li>");
        if ($enableoffer == 'yes')
            print ("<li" . ($selected == "offers" ? " class=\"selected\"" : "") . "><a href=\"offers.php\">".$lang_functions['text_offers']."</a></li>");
        print ("<li" . ($selected == "upload" ? " class=\"selected\"" : "") . "><a href=\"upload.php\">".$lang_functions['text_upload']."</a></li>");
        //	print ("<li" . ($selected == "usercp" ? " class=\"selected\"" : "") . "><a href=\"usercp.php\">".$lang_functions['text_user_cp']."</a></li>");
        if (user_can('topten')) {
            print ("<li" . ($selected == "topten" ? " class=\"selected\"" : "") . "><a href=\"topten.php\">".$lang_functions['text_top_ten']."</a></li>");
        }
        if (user_can('log')) {
            print ("<li" . ($selected == "log" ? " class=\"selected\"" : "") . "><a href=\"log.php\">".$lang_functions['text_log']."</a></li>");
        }
        print ("<li" . ($selected == "rules" ? " class=\"selected\"" : "") . "><a href=\"rules.php\">".$lang_functions['text_rules']."</a></li>");
        print ("<li" . ($selected == "faq" ? " class=\"selected\"" : "") . "><a href=\"faq.php\">".$lang_functions['text_faq']."</a></li>");
        if (user_can('staffmem')) {
            print ("<li" . ($selected == "staff" ? " class=\"selected\"" : "") . "><a href=\"staff.php\">".$lang_functions['text_staff']."</a></li>");
        }
        print ("<li" . ($selected == "contactstaff" ? " class=\"selected\"" : "") . "><a href=\"contactstaff.php\">".$lang_functions['text_contactstaff']."</a></li>");
        print ("</ul>");
    }
	print ("</div>");
	if ($CURUSER){
		if ($where_tweak == 'yes')
			$USERUPDATESET[] = "page = ".sqlesc($selected);
	}
}
function get_css_row() {
	global $CURUSER, $defcss, $Cache;
	static $rows;
	$cssid = $CURUSER ? $CURUSER["stylesheet"] : $defcss;
	if (!$rows && !$rows = $Cache->get_value('stylesheet_content')){
		$rows = array();
		$res = sql_query("SELECT * FROM stylesheets ORDER BY id ASC");
		while($row = mysql_fetch_array($res)) {
			$rows[$row['id']] = $row;
		}
		$Cache->cache_value('stylesheet_content', $rows, 95400);
	}
	return $rows[$cssid] ?? $rows[$defcss];
}
function get_css_uri($file = "")
{
    global $defcss;
	$cssRow = get_css_row();
	$ss_uri = $cssRow['uri'];
	if (!$ss_uri)
		$ss_uri = get_single_value("stylesheets","uri","WHERE id=".sqlesc($defcss));
	if ($file == "")
		return $ss_uri;
	else return $ss_uri.$file;
}

function get_font_css_uri(){
	global $CURUSER;
    $file = 'mediumfont.css';
    if ($CURUSER && isset($CURUSER['fontsize'])) {
        if ($CURUSER['fontsize'] == 'large')
            $file = 'largefont.css';
        elseif ($CURUSER['fontsize'] == 'small')
            $file = 'smallfont.css';
    }
	return "styles/".$file;
}

function get_style_addicode()
{
	$cssRow = get_css_row();
	return $cssRow['addicode'];
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
	if ($CURUSER)
	{
		$ss_a = @mysql_fetch_array(@sql_query("select hltr from stylesheets where id=" . $CURUSER["stylesheet"]));
		if ($ss_a) $hltr = $ss_a["hltr"];
	}
	if (!$hltr)
	{
		$r = sql_query("SELECT hltr FROM stylesheets WHERE id=5");
		$a = mysql_fetch_array($r);
		$hltr = $a["hltr"];
	}
	return $hltr;
}

function stdhead($title = "", $msgalert = true, $script = "", $place = "")
{
	global $lang_functions;
	global $CURUSER, $CURLANGDIR, $USERUPDATESET, $iplog1, $oldip, $SITE_ONLINE, $FUNDS, $SITENAME, $SLOGAN, $logo_main, $BASEURL, $offlinemsg,$enabledonation, $staffmem_class, $titlekeywords_tweak, $metakeywords_tweak, $metadescription_tweak, $cssdate_tweak, $deletenotransfertwo_account, $neverdelete_account, $iniupload_main;
	global $tstart;
	global $Cache;

	$Cache->setLanguage($CURLANGDIR);

	$cssupdatedate = $cssdate_tweak;
	// Variable for Start Time
	$tstart = getmicrotime(); // Start time
	//Insert old ip into iplog
	if ($CURUSER){
//		if ($iplog1 == "yes") {
//			if (($oldip != $CURUSER["ip"]) && $CURUSER["ip"])
//			sql_query("INSERT INTO iplog (ip, userid, access) VALUES (" . sqlesc($CURUSER['ip']) . ", " . $CURUSER['id'] . ", '" . $CURUSER['last_access'] . "')");
//		}
        //record always
        \App\Repositories\IpLogRepository::saveToCache($CURUSER['id']);
		$USERUPDATESET[] = "last_access = ".sqlesc(date("Y-m-d H:i:s"));
		$USERUPDATESET[] = "ip = ".sqlesc($CURUSER['ip']);
	}
	header("Content-Type: text/html; charset=utf-8; Cache-control:private");
	//header("Pragma: No-cache");
	if ($title == "")
	$title = $SITENAME;
	else
	$title = $SITENAME." :: " . htmlspecialchars($title);
	if ($titlekeywords_tweak)
		$title .= " ".htmlspecialchars($titlekeywords_tweak);
	$title .= " - Powered by ".PROJECTNAME;
	if ($SITE_ONLINE == "no") {
		if (get_user_class() < UC_ADMINISTRATOR) {
			die($lang_functions['std_site_down_for_maintenance']);
		}
		else
		{
			$offlinemsg = true;
		}
	}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<?php
if ($metakeywords_tweak){
?>
<meta name="keywords" content="<?php echo htmlspecialchars($metakeywords_tweak)?>" />
<?php
}
if ($metadescription_tweak){
?>
<meta name="description" content="<?php echo htmlspecialchars($metadescription_tweak)?>" />
<?php
}
?>
<meta name="generator" content="<?php echo PROJECTNAME?>" />
<?php
print(get_style_addicode());
$css_uri = get_css_uri();
$cssupdatedate=($cssupdatedate ? "?".htmlspecialchars($cssupdatedate) : "");
?>
<title><?php echo $title?></title>
<link rel="shortcut icon" href="favicon.ico" type="image/x-icon" />
<link rel="search" type="application/opensearchdescription+xml" title="<?php echo $SITENAME?> Torrents" href="opensearch.php" />
<link rel="stylesheet" href="<?php echo get_font_css_uri().$cssupdatedate?>" type="text/css" />
<link rel="stylesheet" href="styles/sprites.css<?php echo $cssupdatedate?>" type="text/css" />
<link rel="stylesheet" href="<?php echo get_forum_pic_folder()."/forumsprites.css".$cssupdatedate?>" type="text/css" />
<link rel="stylesheet" href="<?php echo $css_uri."theme.css".$cssupdatedate?>" type="text/css" />
<link rel="stylesheet" href="<?php echo $css_uri."DomTT.css".$cssupdatedate?>" type="text/css" />
<link rel="stylesheet" href="styles/nexus.css<?php echo $cssupdatedate?>" type="text/css" />
<?php
if ($CURUSER){
//	$caticonrow = get_category_icon_row($CURUSER['caticon']);
//	if($caticonrow['cssfile']){
    $requireSearchBoxIdAr = list_require_search_box_id();
    if (!empty($requireSearchBoxIdAr)) {
        $icons = (new \App\Repositories\SearchBoxRepository())->listIcon($requireSearchBoxIdAr);
        foreach ($icons as $icon) {

?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(trim($icon['cssfile'] ?? '', '/')).$cssupdatedate?>" type="text/css" />
<?php
	}}
}
?>
<link rel="alternate" type="application/rss+xml" title="Latest Torrents" href="torrentrss.php" />
<script type="text/javascript" src="js/curtain_imageresizer.js<?php echo $cssupdatedate?>"></script>
<script type="text/javascript" src="js/ajaxbasic.js<?php echo $cssupdatedate?>"></script>
<script type="text/javascript" src="js/common.js<?php echo $cssupdatedate?>"></script>
<script type="text/javascript" src="js/domLib.js<?php echo $cssupdatedate?>"></script>
<script type="text/javascript" src="js/domTT.js<?php echo $cssupdatedate?>"></script>
<script type="text/javascript" src="js/domTT_drag.js<?php echo $cssupdatedate?>"></script>
<script type="text/javascript" src="js/fadomatic.js<?php echo $cssupdatedate?>"></script>
<?php
do_action('nexus_header');
foreach (\Nexus\Nexus::getAppendHeaders() as $value) {
    print($value);
}
?>
<script type="text/javascript" src="js/jquery-1.12.4.min.js<?php echo $cssupdatedate?>"></script>
<script type="text/javascript">
    jQuery.noConflict();
    window.nexusLayerOptions = {
        confirm: {btnAlign: 'c', title: 'Confirm', btn: ['OK', 'Cancel']},
        alert: {btnAlign: 'c', title: 'Info', btn: ['OK', 'Cancel']}
    }
</script>
<script type="text/javascript" src="vendor/layer-v3.5.1/layer/layer.js<?php echo $cssupdatedate?>"></script>
</head>
<body>
<table class="head" cellspacing="0" cellpadding="0" align="center" style="width: <?php echo isset($GLOBALS['CURUSER']) ? CONTENT_WIDTH + 28.66 : CONTENT_WIDTH ?>px">
	<tr>
		<td class="clear">
<?php
if ($logo_main == "")
{
?>
			<div class="logo"><?php echo htmlspecialchars($SITENAME)?></div>
			<div class="slogan"><?php echo htmlspecialchars($SLOGAN)?></div>
<?php
}
else
{
?>
			<div class="logo_img"><img src="<?php echo $logo_main?>" alt="<?php echo htmlspecialchars($SITENAME)?>" title="<?php echo htmlspecialchars($SITENAME)?> - <?php echo htmlspecialchars($SLOGAN)?>" /></div>
<?php
}
?>
		</td>
		<td class="clear nowrap" align="right" valign="middle">
<?php if ($enabledonation == 'yes'){?>
			<a href="donate.php"><img src="<?php echo get_forum_pic_folder()?>/donate.gif" alt="Make a donation" style="margin-left: 5px; margin-top: 50px;" /></a>
<?php
}
?>
		</td>
	</tr>
</table>

<table class="mainouter" width="<?php echo CONTENT_WIDTH ?>" cellspacing="0" cellpadding="5" align="center">
	<tr><td id="nav_block" class="text" align="center">
<?php if (!$CURUSER) { ?>
			<a href="login.php"><font class="big"><b><?php echo $lang_functions['text_login'] ?></b></font></a> / <a href="signup.php"><font class="big"><b><?php echo $lang_functions['text_signup'] ?></b></font></a>
<?php }
else {
	begin_main_frame();
	menu ();
	end_main_frame();

	$datum = getdate();
	$datum["hours"] = sprintf("%02.0f", $datum["hours"]);
	$datum["minutes"] = sprintf("%02.0f", $datum["minutes"]);
	$ratio = get_ratio($CURUSER['id']);

	//// check every 15 minutes //////////////////
	$messages = $Cache->get_value('user_'.$CURUSER["id"].'_inbox_count');
	if ($messages == ""){
		$messages = get_row_count("messages", "WHERE receiver=" . sqlesc($CURUSER["id"]) . " AND location<>0");
		$Cache->cache_value('user_'.$CURUSER["id"].'_inbox_count', $messages, 900);
	}
	$outmessages = $Cache->get_value('user_'.$CURUSER["id"].'_outbox_count');
	if ($outmessages == ""){
		$outmessages = get_row_count("messages","WHERE sender=" . sqlesc($CURUSER["id"]) . " AND saved='yes'");
		$Cache->cache_value('user_'.$CURUSER["id"].'_outbox_count', $outmessages, 900);
	}
	if (!$connect = $Cache->get_value('user_'.$CURUSER["id"].'_connect')){
		$res3 = sql_query("SELECT connectable FROM peers WHERE userid=" . sqlesc($CURUSER["id"]) . " order by id desc LIMIT 1");
		if($row = mysql_fetch_row($res3))
			$connect = $row[0];
		else $connect = 'unknown';
		$Cache->cache_value('user_'.$CURUSER["id"].'_connect', $connect, 900);
	}

	if($connect == "yes")
		$connectable = "<b><font color=\"green\">".$lang_functions['text_yes']."</font></b>";
	elseif ($connect == 'no')
		$connectable = "<a href=\"faq.php#id21\"><b><font color=\"red\">".$lang_functions['text_no']."</font></b></a>";
	else
		$connectable = $lang_functions['text_unknown'];

	//// check every 60 seconds //////////////////
	$activeseed = $Cache->get_value('user_'.$CURUSER["id"].'_active_seed_count');
	if ($activeseed == ""){
		$activeseed = get_row_count("peers","WHERE userid=" . sqlesc($CURUSER["id"]) . " AND seeder='yes'");
		$Cache->cache_value('user_'.$CURUSER["id"].'_active_seed_count', $activeseed, 60);
	}
	$activeleech = $Cache->get_value('user_'.$CURUSER["id"].'_active_leech_count');
	if ($activeleech == ""){
		$activeleech = get_row_count("peers","WHERE userid=" . sqlesc($CURUSER["id"]) . " AND seeder='no'");
		$Cache->cache_value('user_'.$CURUSER["id"].'_active_leech_count', $activeleech, 60);
	}
	$unread = $Cache->get_value('user_'.$CURUSER["id"].'_unread_message_count');
	if ($unread == ""){
		$unread = get_row_count("messages","WHERE receiver=" . sqlesc($CURUSER["id"]) . " AND unread='yes'");
		$Cache->cache_value('user_'.$CURUSER["id"].'_unread_message_count', $unread, 60);
	}

	$inboxpic = "<img class=\"".($unread ? "inboxnew" : "inbox")."\" src=\"pic/trans.gif\" alt=\"inbox\" title=\"".($unread ? $lang_functions['title_inbox_new_messages'] : $lang_functions['title_inbox_no_new_messages'])."\" />";
//    $attend_desk = new Attendance($CURUSER['id']);
//    $attendance = $attend_desk->check();
    $attendanceRep = new \App\Repositories\AttendanceRepository();
    $attendance = $attendanceRep->getAttendance($CURUSER['id'], date('Ymd'))
?>

<table id="info_block" cellpadding="4" cellspacing="0" border="0" width="100%"><tr>
	<td><table width="100%" cellspacing="0" cellpadding="0" border="0"><tr>
		<td class="bottom" align="left">
            <span class="medium">
                <?php echo $lang_functions['text_welcome_back'] ?>, <?php echo get_username($CURUSER['id'])?>
                [<a href="logout.php"><?php echo $lang_functions['text_logout'] ?></a>]
                [<a href="usercp.php"><?php echo $lang_functions['text_user_cp'] ?></a>]
                <?php if (get_user_class() >= UC_MODERATOR) { ?> [<a href="staffpanel.php"><?php echo $lang_functions['text_staff_panel'] ?></a>] <?php }?>
                <?php if (get_user_class() >= UC_SYSOP) { ?> [<a href="settings.php"><?php echo $lang_functions['text_site_settings'] ?></a>]<?php } ?>
                [<a href="torrents.php?inclbookmarked=1&amp;allsec=1&amp;incldead=0"><?php echo $lang_functions['text_bookmarks'] ?></a>]
                <font class = 'color_bonus'><?php echo $lang_functions['text_bonus'] ?></font>[<a href="mybonus.php"><?php echo $lang_functions['text_use'] ?></a>]: <?php echo number_format($CURUSER['seedbonus'], 1)?>
                <?php if($attendance){ printf(' <a href="attendance.php" class="">'.$lang_functions['text_attended'].'</a>', $attendance->points, $CURUSER['attendance_card']); }else{ printf(' <a href="attendance.php" class="faqlink">%s</a>', $lang_functions['text_attendance']);}?>
                <a href="medal.php">[<?php echo nexus_trans('medal.label')?>]</a>
                <a href="task.php">[<?php echo nexus_trans('exam.type_task')?>]</a>
                <font class = 'color_invite'><?php echo $lang_functions['text_invite'] ?></font>[<a href="invite.php?id=<?php echo $CURUSER['id']?>"><?php echo $lang_functions['text_send'] ?></a>]: <?php echo sprintf('%s(%s)', $CURUSER['invites'], \App\Models\Invite::query()->where('inviter', $CURUSER['id'])->where('invitee', '')->where('expired_at', '>', now())->count())?>
                <?php if(get_user_class() >= \App\Models\User::getAccessAdminClassMin()) printf('[<a href="%s" target="_blank">%s</a>]', nexus_env('FILAMENT_PATH', 'nexusphp'), $lang_functions['text_management_system'])?>
                <br />
	            <font class="color_ratio"><?php echo $lang_functions['text_ratio'] ?></font> <?php echo $ratio?>
                <font class='color_uploaded'><?php echo $lang_functions['text_uploaded'] ?></font> <?php echo mksize($CURUSER['uploaded'])?>
                <font class='color_downloaded'> <?php echo $lang_functions['text_downloaded'] ?></font> <?php echo mksize($CURUSER['downloaded'])?>
                <font class='color_active'><?php echo $lang_functions['text_active_torrents'] ?></font> <img class="arrowup" alt="Torrents seeding" title="<?php echo $lang_functions['title_torrents_seeding'] ?>" src="pic/trans.gif" /><?php echo $activeseed?>  <img class="arrowdown" alt="Torrents leeching" title="<?php echo $lang_functions['title_torrents_leeching'] ?>" src="pic/trans.gif" /><?php echo $activeleech?>&nbsp;&nbsp;
                <font class='color_connectable'><?php echo $lang_functions['text_connectable'] ?></font><?php echo $connectable?> <?php echo maxslots();?>
                <?php if(\App\Models\HitAndRun::getIsEnabled()) { ?><font class='color_bonus'>H&R: </font> <?php echo sprintf('[<a href="myhr.php">%s</a>]', (new \App\Repositories\HitAndRunRepository())->getStatusStats($CURUSER['id']))?><?php }?>
            </span>
        </td>
                <?php if(SearchBox::isSpecialEnabled() && get_setting('main.enable_global_search') == 'yes'){?>
        <td class="bottom" align="left" style="border: none">
            <form action="search.php" method="get" target="<?php echo nexus()->getScript() == 'search' ? '_self' : '_blank'?>">
                <div style="display: flex;align-items: center">
                    <div style="display: flex;flex-direction: column">
                        <div>
                            <span><input type="text" name="search" style="width: 80px;height: 12px" value="<?php echo $_GET['search'] ?? '' ?>" placeholder="<?php echo nexus_trans('search.search_keyword')?>"/></span>
                        </div>
                        <div>
                            <span><?php echo build_search_area($_GET['search_area'] ?? '', ['style' => 'width: 88px'])?></span>
                        </div>
                    </div>
                    <div><input type="submit" value="<?php echo nexus_trans('search.global_search')?>" style="width: 39px;white-space: break-spaces;padding: 0" /></div>
                </div>
            </form>
        </td>
                <?php }?>
	<td class="bottom" align="right"><span class="medium">
<?php
if (user_can('staffmem')) {
    $totalreports = $Cache->get_value('staff_report_count');
    if ($totalreports == ""){
        $totalreports = get_row_count("reports");
        $Cache->cache_value('staff_report_count', $totalreports, 900);
    }
    $totalcheaters = $Cache->get_value('staff_cheater_count');
    if ($totalcheaters == ""){
        $totalcheaters = get_row_count("cheaters");
        $Cache->cache_value('staff_cheater_count', $totalcheaters, 900);
    }
    print(
        "<a href=\"cheaterbox.php\"><img class=\"cheaterbox\" alt=\"cheaterbox\" title=\"".$lang_functions['title_cheaterbox']."\" src=\"pic/trans.gif\" />  </a>".$totalcheaters
        ."  <a href=\"reports.php\"><img class=\"reportbox\" alt=\"reportbox\" title=\"".$lang_functions['title_reportbox']."\" src=\"pic/trans.gif\" />  </a>".$totalreports
    );
}
print(" <a href=\"friends.php\"><img class=\"buddylist\" alt=\"Buddylist\" title=\"".$lang_functions['title_buddylist']."\" src=\"pic/trans.gif\" /></a>");
print(" <a href=\"getrss.php\"><img class=\"rss\" alt=\"RSS\" title=\"".$lang_functions['title_get_rss']."\" src=\"pic/trans.gif\" /></a>");
print '<br/>';
//echo $lang_functions['text_the_time_is_now'].$datum['hours'].":".$datum['minutes'] . '<br />';
//	$cacheKey = "staff_message_count_" . $CURUSER['id'];
//    $totalsm = $Cache->get_value($cacheKey);
    $totalsm = \App\Repositories\MessageRepository::getStaffMessageCountCache($CURUSER['id'], 'total');
    if ($totalsm === false){
        $totalsm = \App\Repositories\MessageRepository::countStaffMessage($CURUSER['id']);
//        $Cache->cache_value($cacheKey, $totalsm, 900);
        \App\Repositories\MessageRepository::updateStaffMessageCountCache($CURUSER['id'], 'total', $totalsm);
    }
    if ($totalsm > 0) {
        print ("  <a href=\"staffbox.php\"><img class=\"staffbox\" alt=\"staffbox\" title=\"".$lang_functions['title_staffbox']."\" src=\"pic/trans.gif\" />  </a>".$totalsm."  ");
    }

	print("<a href=\"messages.php\">".$inboxpic."</a> ".($messages ? $messages." (".$unread.$lang_functions['text_message_new'].")" : "0"));
	print("  <a href=\"messages.php?action=viewmailbox&amp;box=-1\"><img class=\"sentbox\" alt=\"sentbox\" title=\"".$lang_functions['title_sentbox']."\" src=\"pic/trans.gif\" /></a> ".($outmessages ? $outmessages : "0"));

?>

	</span></td>
	</tr></table></td>
</tr></table>

</td></tr>

<tr><td id="outer" align="center" class="outer" style="padding-top: 20px; padding-bottom: 20px">
<?php
if ($msgalert)
{
    $timeline = \App\Models\TorrentState::resolveTimeline();
    $currentPromotion = $timeline['current'] ?? null;
    $upcomingPromotion = $timeline['upcoming'] ?? null;
    $remarkTpl = $lang_functions['full_site_promotion_remark'] ?? 'Remark: %s';

    if ($currentPromotion) {
        $promotionText = \App\Models\Torrent::$promotionTypes[$currentPromotion['global_sp_state']]['text'] ?? '';
        $msg = sprintf($lang_functions['full_site_promotion_in_effect'], $promotionText);
        if (!empty($currentPromotion['begin']) || !empty($currentPromotion['deadline'])) {
            $timeRange = sprintf($lang_functions['full_site_promotion_time_range'], $currentPromotion['begin'] ?? '-∞', $currentPromotion['deadline'] ?? '∞');
            $msg .= '<br/>' . $timeRange;
        }
        if (!empty($currentPromotion['remark'])) {
            $msg .= '<br/>' . sprintf($remarkTpl, $currentPromotion['remark']);
        }
        msgalert("torrents.php", $msg, "green");
    }
    if ($upcomingPromotion) {
        $promotionText = \App\Models\Torrent::$promotionTypes[$upcomingPromotion['global_sp_state']]['text'] ?? '';
        $msg = sprintf($lang_functions['full_site_promotion_upcoming'] ?? 'Upcoming full site [%s]', $promotionText);
        if (!empty($upcomingPromotion['begin']) || !empty($upcomingPromotion['deadline'])) {
            $timeRange = sprintf($lang_functions['full_site_promotion_time_range'], $upcomingPromotion['begin'] ?? '-∞', $upcomingPromotion['deadline'] ?? '∞');
            $msg .= '<br/>' . $timeRange;
        }
        if (!empty($upcomingPromotion['remark'])) {
            $msg .= '<br/>' . sprintf($remarkTpl, $upcomingPromotion['remark']);
        }
        msgalert("torrents.php", $msg, "blue");
    }
	if($CURUSER['leechwarn'] == 'yes')
	{
		$kicktimeout = gettime($CURUSER['leechwarnuntil'], false, false, true);
		$text = $lang_functions['text_please_improve_ratio_within'].$kicktimeout.$lang_functions['text_or_you_will_be_banned'];
		msgalert("faq.php#id17", $text, "orange");
	}
	if($deletenotransfertwo_account) //inactive account deletion notice
	{
		if ($CURUSER['downloaded'] == 0 && ($CURUSER['uploaded'] == 0 || $CURUSER['uploaded'] == $iniupload_main))
		{
			$neverdelete_account = ($neverdelete_account <= UC_VIP ? $neverdelete_account : UC_VIP);
			if (get_user_class() < $neverdelete_account)
			{
				$secs = $deletenotransfertwo_account*24*60*60;
				$addedtime = strtotime($CURUSER['added']);
				if (TIMENOW > $addedtime+($secs/3)) // start notification if one third of the time has passed
				{
					$kicktimeout = gettime(date("Y-m-d H:i:s", $addedtime+$secs), false, false, true);
					$text = $lang_functions['text_please_download_something_within'].$kicktimeout.$lang_functions['text_inactive_account_be_deleted'];
					msgalert("rules.php", $text, "gray");
				}
			}
		}
	}
	if($CURUSER['showclienterror'] == 'yes')
	{
		$text = $lang_functions['text_banned_client_warning'];
		msgalert("faq.php#id29", $text, "black");
	}
	if ($unread)
	{
		$text = $lang_functions['text_you_have'].$unread.$lang_functions['text_new_message'] . add_s($unread) . $lang_functions['text_click_here_to_read'];
		msgalert("messages.php",$text, "red");
	}
    \App\Utils\MsgAlert::getInstance()->render();

/*
	$pending_invitee = $Cache->get_value('user_'.$CURUSER["id"].'_pending_invitee_count');
	if ($pending_invitee == ""){
		$pending_invitee = get_row_count("users","WHERE status = 'pending' AND invited_by = ".sqlesc($CURUSER['id']));
		$Cache->cache_value('user_'.$CURUSER["id"].'_pending_invitee_count', $pending_invitee, 900);
	}
	if ($pending_invitee > 0)
	{
		$text = $lang_functions['text_your_friends'].add_s($pending_invitee).is_or_are($pending_invitee).$lang_functions['text_awaiting_confirmation'];
		msgalert("invite.php?id=".$CURUSER['id'],$text, "red");
	}*/
	$settings_script_name = $_SERVER["SCRIPT_FILENAME"];
	if (!preg_match("/index/i", $settings_script_name))
	{
		$new_news = $Cache->get_value('user_'.$CURUSER["id"].'_unread_news_count');
		if ($new_news == ""){
			$new_news = get_row_count("news","WHERE notify = 'yes' AND added > ".sqlesc($CURUSER['last_home']));
			$Cache->cache_value('user_'.$CURUSER["id"].'_unread_news_count', $new_news, 300);
		}
		if ($new_news > 0)
		{
			$text = $lang_functions['text_there_is'].is_or_are($new_news).$new_news.$lang_functions['text_new_news'];
			msgalert("index.php",$text, "green");
		}
	}

	//Staff message, not only staff member
//    $cacheKey = 'staff_new_message_count_' . $CURUSER['id'];
//    $nummessages = $Cache->get_value($cacheKey);
    $nummessages = \App\Repositories\MessageRepository::getStaffMessageCountCache($CURUSER['id'], 'new');

    if ($nummessages === false){
        $nummessages = \App\Repositories\MessageRepository::countStaffMessage($CURUSER['id'], 0);
//        $Cache->cache_value($cacheKey, $nummessages, 900);
        \App\Repositories\MessageRepository::updateStaffMessageCountCache($CURUSER['id'], 'new', $nummessages);
    }
    if ($nummessages > 0) {
        $text = $lang_functions['text_there_is'].is_or_are($nummessages).$nummessages.$lang_functions['text_new_staff_message'] . add_s($nummessages);
        msgalert("staffbox.php",$text, "blue");
    }

    //torrent approval
    if (user_can('torrent-approval') && get_setting('torrent.approval_status_none_visible') == 'no') {
        $cacheKey = 'TORRENT_APPROVAL_NONE';
        $toApprovalCounts = $Cache->get_value($cacheKey);
        if ($toApprovalCounts === false) {
            $toApprovalCounts = get_row_count('torrents', 'where approval_status = 0');
            $Cache->cache_value($cacheKey, $toApprovalCounts, 60);
        }
        if ($toApprovalCounts) {
            msgalert('torrents.php?approval_status=0&incldead=0', sprintf($lang_functions['text_torrent_to_approval'], is_or_are($toApprovalCounts), $toApprovalCounts, add_s($toApprovalCounts)), 'darkred');
        }
    }

    //seed box approval
    if (get_user_class() >= \App\Models\User::CLASS_ADMINISTRATOR && get_setting('seed_box.enabled') == 'yes') {
        $cacheKey = \App\Repositories\SeedBoxRepository::APPROVAL_COUNT_CACHE_KEY;
        $toApprovalCounts = $Cache->get_value($cacheKey);
        if ($toApprovalCounts === false) {
            $toApprovalCounts = get_row_count('seed_box_records', 'where status = 0');
            $Cache->cache_value($cacheKey, $toApprovalCounts, 60);
        }
        if ($toApprovalCounts) {
            msgalert('/nexusphp/system/seed-box-records?tableFilters[status][value]=0', sprintf($lang_functions['text_seed_box_record_to_approval'], is_or_are($toApprovalCounts), $toApprovalCounts, add_s($toApprovalCounts)), 'darkred');
        }
    }

	if (user_can('staffmem'))
	{

        if(($complaints = $Cache->get_value('COMPLAINTS_COUNT_CACHE')) === false){
            $complaints = get_row_count('complains', 'WHERE answered = 0');
            $Cache->cache_value('COMPLAINTS_COUNT_CACHE', $complaints, 600);
        }
        if($complaints) {
            msgalert('complains.php?action=list', sprintf($lang_functions['text_complains'], is_or_are($complaints), $complaints, add_s($complaints)), 'darkred');
        }

		$numreports = $Cache->get_value('staff_new_report_count');
		if ($numreports == ""){
			$numreports = get_row_count("reports","WHERE dealtwith=0");
			$Cache->cache_value('staff_new_report_count', $numreports, 900);
		}
		if ($numreports){
			$text = $lang_functions['text_there_is'].is_or_are($numreports).$numreports.$lang_functions['text_new_report'] .add_s($numreports);
			msgalert("reports.php",$text, "blue");
		}

		$numcheaters = $Cache->get_value('staff_new_cheater_count');
		if ($numcheaters == ""){
			$numcheaters = get_row_count("cheaters","WHERE dealtwith=0");
			$Cache->cache_value('staff_new_cheater_count', $numcheaters, 900);
		}
		if ($numcheaters){
			$text = $lang_functions['text_there_is'].is_or_are($numcheaters).$numcheaters.$lang_functions['text_new_suspected_cheater'] .add_s($numcheaters);
			msgalert("cheaterbox.php",$text, "blue");
		}
	}

	//show the exam info
    $exam = new \Nexus\Exam\Exam();
    $currentExam = $exam->getCurrent($CURUSER['id']);
    if (!empty($currentExam['html'])) {
        msgalert($currentExam['exam']->type==\App\Models\Exam::TYPE_TASK ? "task.php" : "messages.php", $currentExam['html'], $currentExam['exam']->background_color ?? 'blue');
    }
}
		if ($offlinemsg)
		{
			print("<p><table width=\"737\" border=\"1\" cellspacing=\"0\" cellpadding=\"10\"><tr><td style='padding: 10px; background: red' class=\"text\" align=\"center\">\n");
			print("<font color=\"white\">".$lang_functions['text_website_offline_warning']."</font>");
			print("</td></tr></table></p><br />\n");
		}
}
}


function stdfoot() {
	global $SITENAME,$BASEURL,$Cache,$datefounded,$tstart,$icplicense_main,$add_key_shortcut,$query_name, $USERUPDATESET, $CURUSER, $enablesqldebug_tweak, $sqldebug_tweak, $analyticscode_tweak;
	global $hook;
	print("</td></tr></table>");
	print("<div id=\"footer\">");
	print("<div style=\"margin-top: 10px; margin-bottom: 30px;\" align=\"center\">");
	if ($CURUSER) {
        if (count($USERUPDATESET)) {
            sql_query("UPDATE users SET " . join(",", $USERUPDATESET) . " WHERE id = ".$CURUSER['id']);
        }
	}
	// Variables for End Time
	$tend = microtime(true);
	$totaltime = ($tend - nexus()->getStartTimestamp());
	$year = substr($datefounded, 0, 4);
	$yearfounded = ($year ? $year : 2007);
	print(" (c) "." <a href=\"" . get_protocol_prefix() . $BASEURL."\" target=\"_self\">".$SITENAME."</a> ".($icplicense_main ? " ".$icplicense_main." " : "").(date("Y") != $yearfounded ? $yearfounded."-" : "").date("Y")." ".VERSION."<br /><br />");
	printf ("[page created in <b> %s </b> sec", sprintf("%.3f", $totaltime));
    $debugQuery = $enablesqldebug_tweak == 'yes' && get_user_class() >= $sqldebug_tweak;
    if ($debugQuery) {
        $query_name_laravel = last_query(true);
        $dbQueryCount = count($query_name) + count($query_name_laravel);
    } else {
        $query_name_laravel = [];
        $dbQueryCount = count($query_name) + last_query('COUNT');
    }
    print (" with <b>".$dbQueryCount."</b> db queries, <b>".$Cache->getCacheReadTimes()."</b> reads and <b>".$Cache->getCacheWriteTimes()."</b> writes of Redis and <b>".mksize(memory_get_usage())."</b> ram]");
	print ("</div>\n");
	if ($debugQuery) {
		print("<div id=\"sql_debug\" style='text-align: left;'>SQL query list: <ul>");
		foreach($query_name as $query) {
			print(sprintf('<li>%s [%s]</li>', htmlspecialchars($query['query']), $query['time']));
		}
        foreach($query_name_laravel as $query) {
            print(sprintf('<li>%s [%s ms]</li>', htmlspecialchars($query['raw_query']), $query['time']));
        }
		print("</ul>");
		print("Redis key read: <ul>");
		foreach($Cache->getKeyHits('read') as $keyName => $hits) {
			print("<li>".htmlspecialchars($keyName)." : ".$hits."</li>");
		}
		print("</ul>");
		print("Redis key write: <ul>");
		foreach($Cache->getKeyHits('write') as $keyName => $hits) {
			print("<li>".htmlspecialchars($keyName)." : ".$hits."</li>");
		}
		print("</ul>");
		print("</div>");
	}
	if ($add_key_shortcut != "")
	print($add_key_shortcut);
	print("</div>");
	if ($analyticscode_tweak)
		print("\n".$analyticscode_tweak."\n");
//	$hook->dump();
    do_action('nexus_footer');
	foreach (\Nexus\Nexus::getAppendFooters() as $value) {
	    print($value);
    }
	$js = <<<JS
<script type="application/javascript" src="js/nexus.js"></script>
<script type="application/javascript" src="js/medium-zoom.min.js"></script>
<script type="application/javascript" src="vendor/jquery-goup-1.1.3/jquery.goup.min.js"></script>
<script>
jQuery(document).ready(function(){
    jQuery.goup()
    mediumZoom('[data-zoomable]')
});
</script>
JS;
    print($js);
    print('<img id="nexus-preview" style="display: none; position: absolute" src="" />');
	print("</body></html>");

	//echo replacePngTags(ob_get_clean());
//	unset($_SESSION['queries']);
}

function genbark($x,$y) {
	stdhead($y);
	print("<h1>" . htmlspecialchars($y) . "</h1>\n");
	print("<p>" . htmlspecialchars($x) . "</p>\n");
	stdfoot();
	exit();
}

function mksecret($len = 20) {
	return \App\Support\Token::randomHex((int) $len);
}

function httperr($code = 404) {
	header("HTTP/1.1 404 Not found");
	print("<h1>Not Found</h1>\n");
	exit();
}

function logincookie($id, $authKey, $duration = 0)
{
    \App\Support\AuthCookie::setLoginCookie((int) $id, (string) $authKey, (int) $duration);
}

function set_langfolder_cookie($folder, $expires = 0x7fffffff)
{
	if ($expires != 0x7fffffff)
	$expires = time()+$expires;

	setcookie("c_lang_folder", $folder, $expires, "/", "", false, true);
}

function get_protocol_prefix() {
	return \App\Support\Http::protocolPrefix(isHttps());
}

function get_langid_from_langcookie($lang = '')
{
    if (empty($lang)) {
        $lang = get_langfolder_cookie();
    }
    $row = \App\Models\Language::query()->where('site_lang', 1)->where("site_lang_folder", $lang)->orderBy("id")->first();
    return $row->id ?? 0;
//	$row = mysql_fetch_array(sql_query("SELECT id FROM language WHERE site_lang = 1 AND site_lang_folder = " . sqlesc($lang) . "ORDER BY id ASC")) or sqlerr(__FILE__, __LINE__);
//	return $row['id'];
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
	global $savedirectory_attachment, $httpdirectory_attachment;
	$url = trim((string)$url);
	if ($url === '') {
		return '';
	}
	if (!extension_loaded('gd')) {
		return $url;
	}
	$saveDir = $savedirectory_attachment ?: 'attachments';
	$httpDir = $httpdirectory_attachment ?: 'attachments';
	$key = md5($url . '|' . (int)$maxWidth . 'x' . (int)$maxHeight);
	$relativeDir = 'covers/' . substr($key, 0, 2);
	$filename = $key . '.jpg';
	$absoluteDir = make_folder($saveDir . '/', $relativeDir);
	$absolutePath = rtrim($absoluteDir, '/') . '/' . $filename;
	$publicUrl = $httpDir . '/' . $relativeDir . '/' . $filename;
	if (is_file($absolutePath) && filesize($absolutePath) > 0) {
		return $publicUrl;
	}

	// Remote covers are thumbnailed asynchronously. The heavy lifting
	// (SSRF-safe validation, fetch, resize) is done in the queue job so the
	// homepage render never blocks on a slow or unreachable cover host.
	// A cheap cache lock prevents duplicate dispatches for the same thumbnail.
	if (preg_match('#^https?://#i', $url)) {
		global $Cache;
		$lockKey = 'cover_thumb:' . $absolutePath;
		$lockSet = false;
		if (isset($Cache) && is_object($Cache) && property_exists($Cache, 'redis')) {
			$lockSet = (bool) $Cache->redis->set($lockKey, 1, ['nx', 'ex' => 300]);
		}
		if ($lockSet) {
			\Nexus\Nexus::dispatchQueueJob(new \App\Jobs\GenerateCoverThumbnail($url, $absolutePath, (int)$maxWidth, (int)$maxHeight, (int)$quality));
		}

		return $url;
	}

	// Local paths are processed synchronously.
	$data = false;
	$localPath = ROOT_PATH . ltrim($url, '/');
	if (is_file($localPath)) {
		$data = @file_get_contents($localPath);
	}
	if (!$data) {
		return $url;
	}
	$src = @imagecreatefromstring($data);
	if (!$src) {
		return $url;
	}
	$srcWidth  = imagesx($src);
	$srcHeight = imagesy($src);
	if ($srcWidth <= 0 || $srcHeight <= 0) {
		imagedestroy($src);
		return $url;
	}
	$scale = min(1.0, $maxWidth / $srcWidth, $maxHeight / $srcHeight);
	$dstWidth  = max(1, (int) floor($srcWidth * $scale));
	$dstHeight = max(1, (int) floor($srcHeight * $scale));
	$dst = imagecreatetruecolor($dstWidth, $dstHeight);
	imagecopyresampled($dst, $src, 0, 0, 0, 0, $dstWidth, $dstHeight, $srcWidth, $srcHeight);
	$ok = @imagejpeg($dst, $absolutePath, max(1, min(100, (int)$quality)));
	imagedestroy($src);
	imagedestroy($dst);
	if (!$ok) {
		return $url;
	}
	return $publicUrl;
}

function logoutcookie() {
//	setcookie("c_secure_uid", "", 0x7fffffff, "/", "", false, true);
	setcookie("c_secure_pass", "", 0x7fffffff, "/", "", isHttps(), true);
// setcookie("c_secure_ssl", "", 0x7fffffff, "/", "", false, true);
//	setcookie("c_secure_tracker_ssl", "", 0x7fffffff, "/", "", false, true);
//	setcookie("c_secure_login", "", 0x7fffffff, "/", "", false, true);
//	setcookie("c_lang_folder", "", 0x7fffffff, "/", "", false, true);
}

function base64 ($string, $encode=true) {
	return $encode ? \App\Support\Codec::base64Encode((string) $string) : \App\Support\Codec::base64Decode((string) $string);
}

function loggedinorreturn($mainpage = false) {
	global $CURUSER,$BASEURL;
    $script = nexus()->getScript();
	if (!$CURUSER) {
	    if ($script == 'ajax') {
	        exit(fail('Not login!', $_POST));
        }
		if ($mainpage) {
            nexus_redirect("login.php");
        } else {
			$to = $_SERVER["REQUEST_URI"];
			$to = basename($to);
            nexus_redirect("login.php?returnto=" . rawurlencode($to));
		}
		exit();
	}
    if ($CURUSER['enabled'] != 'yes' && $script != 'self-enable') {
        nexus_redirect('self-enable.php');
    }
}

function deletetorrent($id, $notify = false) {
    $idArr = is_array($id) ? $id : [$id];
    $torrentInfo = \App\Models\Torrent::query()
        ->whereIn("id", $idArr)
        ->get()
        ->KeyBy("id")
    ;
    $torrentRep = new \App\Repositories\TorrentRepository();
	$idStr = implode(', ', $idArr ?: [0]);
	$torrent_dir = get_setting('main.torrent_dir');
    \Nexus\Database\NexusDB::statement("DELETE FROM torrents WHERE id in ($idStr)");
    \Nexus\Database\NexusDB::statement("DELETE FROM torrent_extras WHERE torrent_id in ($idStr)");
    //delete by torrent, make sure user is deleted
    \Nexus\Database\NexusDB::statement("DELETE FROM snatched WHERE torrentid in ($idStr) and not exists (select 1 from users where id = snatched.userid)");
	foreach(array("peers", "files", "comments") as $x) {
        \Nexus\Database\NexusDB::statement("DELETE FROM $x WHERE torrent in ($idStr)");
	}
    \Nexus\Database\NexusDB::statement("DELETE FROM hit_and_runs WHERE torrent_id in ($idStr)");
    foreach ($torrentInfo as $_id => $info) {
        if ($torrentInfo->has($_id)) {
            $torrentRep->delPiecesHashCache($torrentInfo->get($_id)->pieces_hash);
        }
        do_log("delete torrent: $_id", "error");
        unlink(getFullDirectory("$torrent_dir/$_id.torrent"));
        \App\Models\TorrentOperationLog::add([
            'torrent_id' => $_id,
            'uid' => get_user_id(),
            'action_type' => \App\Models\TorrentOperationLog::ACTION_TYPE_DELETE,
            'comment' => '',
        ], $notify);
        do_action("torrent_delete", $_id);
        fire_event("torrent_deleted", $torrentInfo->get($_id));
    }
    $meiliSearchRep = new \App\Repositories\MeiliSearchRepository();
    $meiliSearchRep->deleteDocuments($idArr);
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
	if (!$ret = $Cache->get_value('category_list_mode_'.$catmode)){
		$ret = array();
		$res = sql_query("SELECT id, mode, name, image FROM categories WHERE mode = ".sqlesc($catmode)." ORDER BY sort_index desc");
		while ($row = mysql_fetch_array($res))
			$ret[] = $row;
		$Cache->cache_value('category_list_mode_'.$catmode, $ret, 3600);
	}
	return $ret;
}

function searchbox_item_list(string $table, int $mode){
	global $Cache;
	$cacheKey = "{$table}_list_mode_{$mode}";
	if (!$ret = $Cache->get_value($cacheKey)){
		$ret = array();
		$sql = "SELECT * FROM $table";
		if ($mode > 0) {
		    $sql .= " where (mode = '$mode' or mode = 0)";
        }
		$sql .= " ORDER BY sort_index, id";
		$res = sql_query($sql);
		while ($row = mysql_fetch_array($res))
			$ret[] = $row;
		$Cache->cache_value($cacheKey, $ret, 3600);
	}
	return $ret;
}

function langlist($type, $enabled = null) {
	global $Cache;
	$cacheKey = $type.'_lang_list';
	return  \Nexus\Database\NexusDB::remember($cacheKey, 600, function () use ($type, $enabled) {
        $query = \App\Models\Language::query()->where($type, 1);
        if ($enabled !== null) {
            $query->whereIn('site_lang_folder', \App\Models\Language::listEnabled(true));
        }
        return $query->get()->toArray();
    });
//    if (!$ret = $Cache->get_value($type.'_lang_list')){
//        $ret = array();
//        $res = sql_query("SELECT id, lang_name, flagpic, site_lang_folder FROM language WHERE ". $type ."=1 ORDER BY site_lang DESC, id ASC");
//        while ($row = mysql_fetch_array($res))
//            $ret[] = $row;
//        $Cache->cache_value($type.'_lang_list', $ret, 152800);
//    }
//	return $ret;
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
	static $ret;
	if (!$ret){
		if (!$ret = $Cache->get_value('user_'.$userid.'_bookmark_array')){
			$ret = array();
			$res = sql_query("SELECT * FROM bookmarks WHERE userid=" . sqlesc($userid));
			if (mysql_num_rows($res) != 0){
				while ($row = mysql_fetch_array($res))
					$ret[] = $row['torrentid'];
				$Cache->cache_value('user_'.$userid.'_bookmark_array', $ret, 132800);
			} else {
				$Cache->cache_value('user_'.$userid.'_bookmark_array', array(0), 132800);
                $ret[] = 0;
			}
		}
	}
	return $ret;
}
function get_torrent_bookmark_state($userid, $torrentid, $text = false)
{
	global $lang_functions;
	$userid = intval($userid ?? 0);
	$torrentid = intval($torrentid ?? 0);
	$ret = array();
	$ret = return_torrent_bookmark_array($userid);
	if (!count($ret) || !in_array($torrentid, $ret, false)) // already bookmarked
		$act = ($text == true ?  $lang_functions['title_bookmark_torrent']  : "<img class=\"delbookmark\" src=\"pic/trans.gif\" alt=\"Unbookmarked\" title=\"".$lang_functions['title_bookmark_torrent']."\" />");
	else
		$act = ($text == true ? $lang_functions['title_delbookmark_torrent'] : "<img class=\"bookmark\" src=\"pic/trans.gif\" alt=\"Bookmarked\" title=\"".$lang_functions['title_delbookmark_torrent']."\" />");
	return $act;
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
	$maxpx = "45"; // Maximum amount of pixels for the progress bar

	if ($p == 0) $progress = "<img class=\"progbarrest\" src=\"pic/trans.gif\" style=\"width: " . ($maxpx) . "px;\" alt=\"\" />";
	if ($p == 100) $progress = "<img class=\"progbargreen\" src=\"pic/trans.gif\" style=\"width: " . ($maxpx) . "px;\" alt=\"\" />";
	if ($p >= 1 && $p <= 30) $progress = "<img class=\"progbarred\" src=\"pic/trans.gif\" style=\"width: " . ($p*($maxpx/100)) . "px;\" alt=\"\" /><img class=\"progbarrest\" src=\"pic/trans.gif\" style=\"width: " . ((100-$p)*($maxpx/100)) . "px;\" alt=\"\" />";
	if ($p >= 31 && $p <= 65) $progress = "<img class=\"progbaryellow\" src=\"pic/trans.gif\" style=\"width: " . ($p*($maxpx/100)) . "px;\" alt=\"\" /><img class=\"progbarrest\" src=\"pic/trans.gif\" style=\"width: " . ((100-$p)*($maxpx/100)) . "px;\" alt=\"\" />";
	if ($p >= 66 && $p <= 99) $progress = "<img class=\"progbargreen\" src=\"pic/trans.gif\" style=\"width: " . ($p*($maxpx/100)) . "px;\" alt=\"\" /><img class=\"progbarrest\" src=\"pic/trans.gif\" style=\"width: " . ((100-$p)*($maxpx/100)) . "px;\" alt=\"\" />";
	return "<img class=\"bar_left\" src=\"pic/trans.gif\" alt=\"\" />" . $progress ."<img class=\"bar_right\" src=\"pic/trans.gif\" alt=\"\" />";
}

function get_ratio_img($ratio)
{
	return \App\Support\Ratio::image((float)$ratio);
}

function GetVar ($name) {
	if ( is_array($name) ) {
		foreach ($name as $var) GetVar ($var);
	} else {
		if ( !isset($_REQUEST[$name]) )
		return false;
		$GLOBALS[$name] = $_REQUEST[$name];
		return $GLOBALS[$name];
	}
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
	print("<textarea name='".$taname."' cols=\"100\" rows=\"8\" style=\"width: 450px\" onkeydown=\"ctrlenter(event,'compose','qr')\"></textarea>");
	print(smile_row($formname, $taname));
	print("<br />");
 	print("<input type=\"submit\" id=\"qr\" class=\"btn\" value=\"".$submit."\" />");
}

function smile_row($formname, $taname){
	return \App\Support\Smilies::quickRow($formname, $taname);
}
function getSmileIt($formname, $taname, $smilyNumber) {
	return \App\Support\Smilies::link($formname, $taname, (int) $smilyNumber);
}

function classlist($selectname,$maxclass, $selected, $minClass = 0, $includeNoClass = false, $disabled = false){
    global $lang_functions;
    $disabledText = '';
    if ($disabled) {
        $disabledText = ' disabled = "disabled"';
    }
	$list = "<select name=\"".$selectname."\"$disabledText>";
	if ($includeNoClass) {
        $list .= sprintf('<option value="%s">%s</option>', \App\Models\Setting::PERMISSION_NO_CLASS, $lang_functions['select_an_user_class']);
    }
	for ($i = $minClass; $i <= $maxclass; $i++)
		$list .= "<option value=\"".$i."\"" . ($selected == $i ? " selected=\"selected\"" : "") . ">" . get_user_class_name($i,false,false,true) . "</option>\n";
	$list .= "</select>";
	return $list;
}

function permissiondenied($allowMinimumClass = null){
	\App\Support\LegacyResponse::permissionDenied($allowMinimumClass);
}

function gettime($time, $withago = true, $twoline = false, $forceago = false, $oneunit = false, $isfuturetime = false){
	return \App\Support\Time::format($time, $withago, $twoline, $forceago, $oneunit, $isfuturetime);
}

function get_forum_pic_folder(){
	global $CURLANGDIR;
	return "pic/forum_pic/".$CURLANGDIR;
}

function get_category_icon_row($typeid)
{
	global $Cache;
	static $rows;
	if (!$typeid) {
		$typeid=1;
	}
	if (!$rows && !$rows = $Cache->get_value('category_icon_content')){
		$rows = array();
		$res = sql_query("SELECT * FROM caticons ORDER BY id ASC");
		while($row = mysql_fetch_array($res)) {
			$rows[$row['id']] = $row;
		}
		$Cache->cache_value('category_icon_content', $rows, 156400);
	}
	return $rows[$typeid];
}
function get_category_row($catid = NULL)
{
	global $Cache;
	static $rows;
	if (!$rows && !$rows = $Cache->get_value('category_content')){
        $rows = [];
		$res = sql_query("SELECT categories.*, searchbox.name AS catmodename FROM categories LEFT JOIN searchbox ON categories.mode=searchbox.id");
		while($row = mysql_fetch_array($res)) {
			$rows[$row['id']] = $row;
		}
		$Cache->cache_value('category_content', $rows, 126400);
	}
	if ($catid) {
		return $rows[$catid];
	} else {
		return $rows;
	}
}

function get_second_icon($row) //for CHDBits
{
	global $CURUSER, $Cache;
	$source=$row['source'];
	$medium=$row['medium'];
	$codec=$row['codec'];
	$standard=$row['standard'];
	$processing=$row['processing'];
	$audiocodec=$row['audiocodec'];
	$mode = $row['search_box_id'];
	$cacheKey = 'secondicon_'.$source.'_'.$medium.'_'.$codec.'_'.$standard.'_'.$processing.'_'.$audiocodec.'_content';
	if (!$sirow = $Cache->get_value($cacheKey)){
		$res = sql_query("SELECT * FROM secondicons WHERE (mode = ".sqlesc($mode)." OR mode = 0) AND (source = ".sqlesc($source)." OR source=0) AND (medium = ".sqlesc($medium)." OR medium=0) AND (codec = ".sqlesc($codec)." OR codec = 0) AND (standard = ".sqlesc($standard)." OR standard = 0) AND (processing = ".sqlesc($processing)." OR processing = 0) AND (audiocodec = ".sqlesc($audiocodec)." OR audiocodec = 0) LIMIT 1");
		$sirow = mysql_fetch_array($res);
		if (!$sirow)
			$sirow = 'not allowed';
		$Cache->cache_value($cacheKey, $sirow, 600);
	}
	$catimgurl = get_cat_folder($row['category']);
	if ($sirow == 'not allowed')
		return "<img src=\"pic/cattrans.gif\" style=\"background-image: url(pic/". $catimgurl. "/additional/notallowed.png);\" title=\"Not Allowed\" alt=\"Not Allowed\" />";
	else {
		return "<img".($sirow['class_name'] ? " class=\"".$sirow['class_name']."\"" : "")." src=\"pic/cattrans.gif\" style=\"background-image: url(pic/". $catimgurl. "/additional/". $sirow['image'].");\" alt=\"" . $sirow["name"] . "\" title=\"".$sirow['name']."\" />";
	}
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
	global $lang_functions;
	$res = sql_query("SELECT id FROM users WHERE LOWER(username)=LOWER(" . sqlesc($username).")");
	$arr = mysql_fetch_array($res);
	if (!$arr){
		stderr($lang_functions['std_error'],$lang_functions['std_no_user_named']."'".$username."'");
	}
	else return $arr['id'];
}

function is_forum_moderator($id, $in = 'post'){
	global $CURUSER;
	switch($in){
		case 'post':{
			$res = sql_query("SELECT topicid FROM posts WHERE id=$id") or sqlerr(__FILE__, __LINE__);
			if ($arr = mysql_fetch_array($res)){
				if (is_forum_moderator($arr['topicid'],'topic'))
					return true;
			}
			return false;
			break;
		}
		case 'topic':{
			$modcount = sql_query("SELECT COUNT(forummods.userid) FROM forummods LEFT JOIN topics ON forummods.forumid = topics.forumid WHERE topics.id=$id AND forummods.userid=".sqlesc($CURUSER['id'])) or sqlerr(__FILE__, __LINE__);
			$arr = mysql_fetch_array($modcount);
			if ($arr[0])
				return true;
			else return false;
			break;
		}
		case 'forum':{
			$modcount = get_row_count("forummods","WHERE forumid=$id AND userid=".sqlesc($CURUSER['id']));
			if ($modcount)
				return true;
			else return false;
			break;
		}
		default: {
		return false;
		}
	}
}

function get_guest_lang_id(){
	global $CURLANGDIR;
	$langfolder=$CURLANGDIR;
	$res = sql_query("SELECT id FROM language WHERE site_lang_folder=".sqlesc($langfolder)." AND site_lang=1");
	$row = mysql_fetch_array($res);
	if ($row){
		return $row['id'];
	}
	else return 6;//return English
}

function set_forum_moderators($name, $forumid, $limit=3){
	$name = rtrim(trim($name), ",");
	$users = explode(",", $name);
	$userids = array();
	foreach ($users as $user){
		$userids[]=get_user_id_from_name(trim($user));
	}
	$max = count($userids);
	sql_query("DELETE FROM forummods WHERE forumid=".sqlesc($forumid)) or sqlerr(__FILE__, __LINE__);
	for($i=0; $i < $limit && $i < $max; $i++){
		sql_query("INSERT INTO forummods (forumid, userid) VALUES (".sqlesc($forumid).",".sqlesc($userids[$i]).")") or sqlerr(__FILE__, __LINE__);
	}
}

function get_plain_username($id){
	$row = get_user_row($id);
	if ($row)
		$username = $row['username'];
	else $username = "";
	return $username;
}

function get_searchbox_value($mode = 1, $item = 'showsubcat'){
	global $Cache;
	static $rows;
	$cacheKey = "search_box_content";
	if (!$rows && !$rows = $Cache->get_value($cacheKey)){
		$rows = array();
		$res = sql_query("SELECT * FROM searchbox ORDER BY id ASC");
		while ($row = mysql_fetch_array($res)) {
		    if (isset($row['extra'])) {
		        $row['extra'] = json_decode($row['extra'], true);
            }
            if (isset($row['section_name'])) {
                $row['section_name'] = json_decode($row['section_name'], true);
            }
			$rows[$row['id']] = $row;
		}
		$Cache->cache_value($cacheKey, $rows, 100500);
	}
	return $rows[$mode][$item] ?? '';
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
	static $moderatorsArray;

	if (!$moderatorsArray && !$moderatorsArray = $Cache->get_value('forum_moderator_array')) {
		$moderatorsArray = array();
		$res = sql_query("SELECT forumid, userid FROM forummods ORDER BY forumid ASC") or sqlerr(__FILE__, __LINE__);
		while ($row = mysql_fetch_array($res)) {
			$moderatorsArray[$row['forumid']][] = $row['userid'];
		}
		$Cache->cache_value('forum_moderator_array', $moderatorsArray, 86200);
	}
	$ret = $moderatorsArray[$forumid] ?? [];

	$moderators = "";
	foreach($ret as $userid) {
		if ($plaintext)
			$moderators .= get_plain_username($userid).", ";
		else $moderators .= get_username($userid).", ";
	}
	$moderators = rtrim(trim($moderators), ",");
	return $moderators;
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
	if (!$row = $Cache->get_value('post_'.$postid.'_content')){
		$res = sql_query("SELECT * FROM posts WHERE id=".sqlesc($postid)." LIMIT 1") or sqlerr(__FILE__,__LINE__);
		$row = mysql_fetch_array($res);
		$Cache->cache_value('post_'.$postid.'_content', $row, 7200);
	}
	if (!$row)
		return false;
	else return $row;
}

function get_country_row($id)
{
	global $Cache;
	if (!$row = $Cache->get_value('country_'.$id.'_content')){
		$res = sql_query("SELECT * FROM countries WHERE id=".sqlesc($id)." LIMIT 1") or sqlerr(__FILE__,__LINE__);
		$row = mysql_fetch_array($res);
		$Cache->cache_value('country_'.$id.'_content', $row, 86400);
	}
	if (!$row)
		return false;
	else return $row;
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
	return "<img src=\"".$url."\" alt=\"avatar\" width=\"150px\" onload=\"check_avatar(this, '".$CURLANGDIR."');\" />";
}
function return_category_image($categoryid, $link="")
{
	static $catImg = array();
	if (isset($catImg[$categoryid])) {
		$catimg = $catImg[$categoryid];
	} else {
		$categoryrow = get_category_row($categoryid);
		$catimgurl = get_cat_folder($categoryid);
		$catImg[$categoryid] = $catimg = "<img".($categoryrow['class_name'] ? " class=\"".$categoryrow['class_name']."\"" : "")." src=\"pic/cattrans.gif\" alt=\"" . $categoryrow["name"] . "\" title=\"" .$categoryrow["name"]. "\" style=\"background-image: url(pic/" . $catimgurl . '/' . $categoryrow["image"].");\" />";
	}
	if ($link) {
		$catimg = "<a href=\"".$link."cat=" . $categoryid . "\">".$catimg."</a>";
	}
	return $catimg;
}

/******************************************** bellow functioons avaliable since v1.6 ***********************************************************/

function torrentTags($tags = 0, $type = 'checkbox')
{
    global $lang_functions;
    $tagsOptions = [
        [
            'text' => $lang_functions['text_tag_no_release_to_any_other'],
            'color' => '#ff0000',
        ],
        [
            'text' => $lang_functions['text_tag_first_release'],
            'color' => '#8F77B5',
        ],
        [
            'text' => $lang_functions['text_tag_official'],
            'color' => '#0000ff',
        ],
        [
            'text' => $lang_functions['text_tag_diy'],
            'color' => '#46d5ff',
        ],
        [
            'text' => $lang_functions['text_tag_mother_language'],
            'color' => '#6a3906',
        ],
        [
            'text' => $lang_functions['text_tag_mother_language_subtitle'],
            'color' => '#006400',
        ],
        [
            'text' => $lang_functions['text_tag_hdr'],
            'color' => '#38b03f',
        ],
    ];
    $html = '';
    foreach ($tagsOptions as $key => $value) {
        $currentValue = pow(2, $key);
        if ($type == 'checkbox') {
            $checked = '';
            if ($currentValue & $tags) {
                $checked = 'checked';
            }
            $html .= sprintf(
                '<label><input type="checkbox" name="tags[]" value="%s" %s />%s</label>',
                $currentValue, $checked, $value['text']
            );
        }
        if ($type == 'span' && ($currentValue & $tags)) {
            $html .= "<span style=\"background-color:{$value['color']};color:white;border-radius:15%\">{$value['text']}</span> ";
        }
    }
    return $html;
}

function saveSetting(string $prefix, array $nameAndValue, string $autoload = 'yes'): void
{
    $prefix = strtolower($prefix);
    $datetimeNow = date('Y-m-d H:i:s');
    $sql = "insert into settings (name, value, created_at, updated_at, autoload) values ";
    $data = [];
    foreach ($nameAndValue as $name => $value) {
        if (is_array($value)) {
            $value = json_encode($value);
        }
        $data[] = sprintf("(%s, %s, %s, %s, '%s')", sqlesc("$prefix.$name"), sqlesc($value), sqlesc($datetimeNow), sqlesc($datetimeNow), $autoload);
    }
    $sql .= implode(",", $data) . " " . \Nexus\Database\NexusDB::upsertField(['name'], ['value']);
    \Nexus\Database\NexusDB::statement($sql);
    clear_setting_cache();
    do_action("nexus_setting_update");
}

function getFullDirectory($dir)
{
	return \App\Support\Path::resolve($dir, ROOT_PATH);
}

function checkGuestVisit()
{
    if (userlogin()) {
        //already login
        return;
    }
    $setting = get_setting('security');
    //all type: normal, static_page, custom_content, redirect
    $guestVisitType = $setting['guest_visit_type'] ?? '';
    if (empty($guestVisitType) || $guestVisitType == 'normal') {
        return;
    }

    if (in_array(nexus()->getScript(), ['login', 'takelogin', 'image']) && canDoLogin()) {
        return;
    }

    $valueKey = "guest_visit_value_$guestVisitType";
    if (empty($setting[$valueKey])) {
        do_log("setting: security.$valueKey empty");
        die(0);
    }
    $guestVisitValue = $setting[$valueKey];
    if ($guestVisitType == 'static_page') {
        $pageFile = ROOT_PATH . 'resources/static-pages/' . $guestVisitValue;
        if (!file_exists($pageFile) || !is_readable($pageFile)) {
            do_log("pageFile: $pageFile is not exists or readable");
            die(0);
        }
        $content = file_get_contents($pageFile);
        die($content);
    }
    if ($guestVisitType == 'custom_content') {
        $content = format_comment($guestVisitValue);
        render('resources/templates/guest-visit-custom-content', ['content' => $content]);
    }
    if ($guestVisitType == 'redirect') {
        header('Location: ' . $guestVisitValue);
        die(0);
    }

}

function render($view, $data = [], $return = false)
{
    extract($data);
    if (!file_exists($view)) {
        $view = ROOT_PATH . $view;
    }
    if (substr($view, -4) !== '.php') {
        $view .= ".php";
    }
    ob_start();
    ob_implicit_flush(0);
    require $view;
    $result = ob_get_clean();
    if ($return) {
        return $result;
    }
    die($result);
}

function canDoLogin()
{
    $setting = get_setting('security');
    if (empty($setting['login_type']) || $setting['login_type'] == 'normal') {
        return true;
    }
    $loginType = $setting['login_type'];
    if ($loginType == 'secret') {
        if (empty($_REQUEST['secret'])) {
            do_log("no secret");
            return false;
        }
        if ($_REQUEST['secret'] != $setting['login_secret']) {
            do_log("invlaid secret: " . $_REQUEST['secret']);
            return false;
        }
        if ($setting['login_secret_deadline'] < date('Y-m-d H:i:s')) {
            do_log("secret: {$_REQUEST['secret']} expires(deadline: {$setting['login_secret_deadline']})");
            return false;
        }
        return true;
    }
    if ($loginType == 'passkey') {
        return false;
    }
    return true;
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
    if (!filter_var($url, FILTER_VALIDATE_URL))
    {
        throw new \InvalidArgumentException("URL: '$url' invalid.");
    }
    $parsed = parse_url($url);
    $driver = config('admin.upload.disk');
    if ($driver == 'qiniu') {
        return trim($parsed['path'], "/");
    } elseif ($driver == 'cloudinary') {
        $parts = explode('/', $parsed['path']);
        $key = end($parts);
        if (\Illuminate\Support\Str::contains($key,'.')) {
            $key = strstr($key, '.', true);
        }
        return $key;

    } else {
        throw new \RuntimeException('不支持的云盘驱动');
    }

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
    return sprintf('%s/attachments/%s', getSchemeAndHttpHost(), trim($location, '/'));
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
		$defaultUrl = $useDefault ? getSchemeAndHttpHost() . "/pic/imdb_pic/nophoto.gif" : '';
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
    global $specialcatmode;
    if (get_setting('main.spsct') != 'yes') {
        return true;
    }
    if (is_array($torrent) && isset($torrent['search_box_id'])) {
        $searchBoxId = $torrent['search_box_id'];
    } elseif (is_numeric($torrent)) {
        $torrent = \App\Models\Torrent::query()->findOrFail(intval($torrent), ['id', 'category']);
        $searchBoxId = $torrent->basic_category->mode ?? 0;
        if ($searchBoxId == 0) {
            do_log("[INVALID_CATEGORY], torrent: " . $torrent->id, 'error');
            return false;
        }
    } else {
        throw new \InvalidArgumentException("Unsupported argument: " . json_encode($torrent));
    }
    if ($searchBoxId != $specialcatmode) {
        return true;
    }
    if (user_can('view_special_torrent', false, $uid)) {
        return true;
    }
    return false;
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
    $medalImages = [];
    $wrapBefore = '<form><div style="display: flex;flex-wrap: wrap;justify-content: center;margin-top: 10px;">';
    $wrapAfter = '</div></form>';
    foreach ($medals as $medal) {
        $html = sprintf('<div style="display: flex;flex-direction: column;justify-content: space-between;margin-right: 10px"><div><img src="%s" title="%s" class="preview" style="max-height: %spx;max-width: %spx"/></div>', $medal->image_large, $medal->name, $maxHeight, $maxHeight);
        if ($withActions) {
            $html .= sprintf(
                '<div style="display: flex;flex-direction: column;align-items:flex-start"><span>%s: %s</span><span>%s: %s</span><span>%s: %s</span><label>%s: <input type="number" name="priority_%s" value="%s" style="width: 50px" placeholder="%s"></label>',
                nexus_trans('label.expire_at'),
                $medal->pivot->expire_at ? format_datetime($medal->pivot->expire_at) : nexus_trans('label.permanent'),
                nexus_trans('medal.fields.bonus_addition_factor'),
                $medal->bonus_addition_factor ?? 0,
                nexus_trans('medal.bonus_addition_expire_at'),
                $medal->pivot->bonus_addition_expire_at ? format_datetime($medal->pivot->bonus_addition_expire_at) : nexus_trans('label.permanent'),
                nexus_trans('label.priority'),
                $medal->pivot->id,
                $medal->pivot->priority ?? 0,
                nexus_trans('label.priority_help')
            );
            $checked = '';
            if ($medal->pivot->status == \App\Models\UserMedal::STATUS_WEARING) {
                $checked = ' checked';
            }
            $html .= sprintf('<label>%s<input type="checkbox" name="status_%s" value="1"%s></label>', nexus_trans('medal.action_wearing'), $medal->pivot->id, $checked);
            $html .= '</div>';
        }
        $html .= '</div>';
        $medalImages[] = $html;
    }
    if ($withActions) {
        $medalImages[] = sprintf('<div style="display: flex;flex-direction: column;justify-content: space-between;margin-right: 10px"><div></div><div><input type="button" id="save-user-medal-btn" value="%s"/></div></div>', nexus_trans('label.save'));
    }
    return $wrapBefore . implode('', $medalImages) . $wrapAfter;
}

function insert_torrent_tags($torrentId, $tagIdArr, $sync = false)
{
    $specialTags = \App\Models\Tag::listSpecial();
    $canSetSpecialTag = \App\Auth\Permission::canSetTorrentSpecialTag();
    $dateTimeStringNow = date('Y-m-d H:i:s');
    if ($sync) {
        $delQuery = \App\Models\TorrentTag::query()->where("torrent_id", $torrentId);
        if (!$canSetSpecialTag) {
            $delQuery->whereNotIn("tag_id", $specialTags);
        }
        $delQuery->delete();
    }
    if (empty($tagIdArr)) {
        return;
    }
    $insertTagsSql = 'insert into torrent_tags (torrent_id, tag_id, created_at, updated_at) values ';
    $values = [];
    foreach ($tagIdArr as $tagId) {
        if (in_array($tagId, $specialTags) && !$canSetSpecialTag) {
            do_log("special tag: $tagId, and user no permission");
            continue;
        }
        if (!isset($values[$tagId])) {
            $values[$tagId] = sprintf("(%s, %s, '%s', '%s')", $torrentId, $tagId, $dateTimeStringNow, $dateTimeStringNow);
        }
    }
    $insertTagsSql .= implode(', ', $values);
    do_log("[INSERT_TAGS], torrent: $torrentId with tags: " . nexus_json_encode($tagIdArr));
    \Nexus\Database\NexusDB::statement($insertTagsSql);
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
    $settingBonus = \App\Models\Setting::get('bonus');
    $minSize = $settingBonus['min_size'] ?? 0;
    $nowStr = date('Y-m-d H:i:s');
    $logPrefix = "[CALCULATE_SEED_BONUS], uid: $uid, torrentIdArr: " . json_encode($torrentIdArr);
    if ($torrentIdArr !== null) {
        if (empty($torrentIdArr)) {
            $torrentIdArr = [-1];
        }
        $idStr = implode(',', \Illuminate\Support\Arr::wrap($torrentIdArr));
        $sql = "select torrents.id, torrents.added, torrents.size, torrents.seeders, 'NO_PEER_ID' as peerID, '' as last_action, '' as ip from torrents  WHERE id in ($idStr) and size >= $minSize";
    } else {
        $sql = "select torrents.id, torrents.added, torrents.size, torrents.seeders, peers.id as peerID, peers.last_action, peers.ip from torrents LEFT JOIN peers ON peers.torrent = torrents.id WHERE peers.userid = $uid AND peers.seeder ='yes' and torrents.size > $minSize group by torrents.id, peers.id";
    }
    $tagGrouped = [];
    $torrentResult = \Nexus\Database\NexusDB::select($sql);
    if (!empty($torrentResult)) {
        $torrentIdArrReal = array_column($torrentResult, 'id');
        $tagResult = \Nexus\Database\NexusDB::select(sprintf("select torrent_id, tag_id from torrent_tags where torrent_id in (%s)", implode(',', $torrentIdArrReal)));
        foreach ($tagResult as $tagItem) {
            $tagGrouped[$tagItem['torrent_id']][$tagItem['tag_id']] = 1;
        }
    }
    $officialTag = \App\Models\Setting::get('bonus.official_tag');
    $officialAdditionalFactor = \App\Models\Setting::get('bonus.official_addition');
    $zeroBonusTag = \App\Models\Setting::get('bonus.zero_bonus_tag');
    $zeroBonusFactor = \App\Models\Setting::get('bonus.zero_bonus_factor');
    if (\Nexus\Database\NexusDB::isMysql()) {
        $factorField = "round(sum(bonus_addition_factor), 5)";
    } elseif (\Nexus\Database\NexusDB::isPgsql()) {
        $factorField = "round(sum(bonus_addition_factor)::numeric, 5)";
    } else {
        throw new \RuntimeException("Not supported database");
    }
    $userMedalResult = \Nexus\Database\NexusDB::select("select $factorField as factor from medals where id in (select medal_id from user_medals where uid = $uid and (expire_at is null or expire_at > '$nowStr') and (bonus_addition_expire_at is null or bonus_addition_expire_at > '$nowStr'))");
    $medalAdditionalFactor = floatval($userMedalResult[0]['factor'] ?? 0);
    do_log("$logPrefix, sql: $sql, count: " . count($torrentResult) . ", officialTag: $officialTag, officialAdditionalFactor: $officialAdditionalFactor, zeroBonusTag: $zeroBonusTag, zeroBonusFactor: $zeroBonusFactor, medalAdditionalFactor: $medalAdditionalFactor");

    $result = \App\Support\Bonus::aggregateSeedBonus(
        $torrentResult,
        $settingBonus,
        $tagGrouped,
        $officialTag,
        $zeroBonusTag,
        $zeroBonusFactor,
        $medalAdditionalFactor,
        $officialAdditionalFactor,
        function ($torrent, $weeks_alive, $gb_size_raw, $gb_size, $temp, $officialAIncrease) use ($logPrefix) {
            do_log(sprintf(
                "$logPrefix, torrent: %s, peer ID: %s, weeks: %s, size_raw: %s GB, size: %s GB, increase A: %s, increase official A: %s",
                $torrent['id'], $torrent['peerID'] ?? '', $weeks_alive, $gb_size_raw, $gb_size, $temp, $officialAIncrease
            ), "debug");
        },
    );
    do_log("$logPrefix, result: " . json_encode($result));

    return $result;
}


function calculate_harem_addition($uid)
{
//    $harems = \App\Models\User::query()
//        ->where('invited_by', $uid)
//        ->where('status', \App\Models\User::STATUS_CONFIRMED)
//        ->where('enabled', \App\Models\User::ENABLED_YES)
//        ->get(['id']);
//    $addition = 0;
//    $haremsCount = $harems->count();
//    foreach ($harems as $harem) {
//        $result = calculate_seed_bonus($harem->id);
//        $addition += $result['seed_points'];
//    }
//    do_log("[HAREM_ADDITION], user: $uid, haremsCount: $haremsCount ,addition: $addition");

    $addition = \Nexus\Database\NexusDB::table("users")
        ->where("invited_by", $uid)
        ->where('status', \App\Models\User::STATUS_CONFIRMED)
        ->where('enabled', \App\Models\User::ENABLED_YES)
        ->sum("seed_points_per_hour")
    ;
    do_log("[HAREM_ADDITION], user: $uid, addition: $addition");
    return $addition;
}


function build_search_box_category_table($mode, $checkboxValue, $categoryHrefPrefix, $taxonomyHrefPrefix, $taxonomyNameLength, $checkedValues = '', array $options = [])
{
    parse_str($checkedValues, $checkedValuesArr);
    $searchBox = \App\Models\SearchBox::query()->with(['categories', 'categories.icon'])->findOrFail($mode);
    $lang = get_langfolder_cookie();
    $withTaxonomies = [];
    if ($searchBox->showsubcat) {
        //Keep the order
        if (!empty($searchBox->extra[SearchBox::EXTRA_TAXONOMY_LABELS])) {
            foreach ($searchBox->extra[SearchBox::EXTRA_TAXONOMY_LABELS] as $taxonomyLabelInfo) {
                $torrentField = $taxonomyLabelInfo["torrent_field"];
                $showField = "show" . $torrentField;
                if ($searchBox->{$showField}) {
                    $withTaxonomies[$torrentField] = \App\Models\SearchBox::$taxonomies[$torrentField]['table'];
                }
            }
        } else {
            foreach (\App\Models\SearchBox::$taxonomies as $torrentField => $taxonomyTableModel) {
                $showField = "show" . $torrentField;
                if ($searchBox->{$showField}) {
                    $withTaxonomies[$torrentField] = $taxonomyTableModel['table'];
                }
            }
        }
    }
    $html = '<table>';
    if (!empty($options['section_name'])) {
        $html .= sprintf('<caption><font class="big">%s</font></caption>', $searchBox->section_name[$lang] ?? '');
    }
    //Category
    $html .= sprintf('<tr><td class="embedded" align="left">%s</td></tr>', nexus_trans('label.search_box.category'));
    /** @var \Illuminate\DataBase\Eloquent\Collection $categoryCollection */
    $categoryCollection = $searchBox->categories()->with('icon')->orderBy('sort_index', 'desc')->get();
    if (!empty($options['select_unselect'])) {
        $categoryCollection->push(new \App\Models\Category(['mode' => -1]));
    }
    $categoryChunks = $categoryCollection->chunk($searchBox->catsperrow);
    $checkPrefix = 'cat';
    foreach ($categoryChunks as $chunk) {
        $html .= '<tr>';
        foreach ($chunk as $item) {
            if ($item->mode != -1) {
                $checked = '';
                if ($checkedValues) {
                    if (
                        str_contains($checkedValues, "[cat{$item->id}]")
                        || (isset($checkedValuesArr["cat{$item->id}"]) && $checkedValuesArr["cat{$item->id}"] == 1)
                        || (isset($checkedValuesArr["cat"]) && $checkedValuesArr["cat"] == $item->id)
                    ) {
                        $checked = " checked";
                    }
                } elseif (!empty($options['user_notifs'])) {
                    $userNotifsKey = sprintf('[%s%s]', 'cat', $item->id);
                    if (str_contains($options['user_notifs'], $userNotifsKey)) {
                        $checked = ' checked';
                    }
                }
                $icon = $item->icon;
                $iconFolder = trim($icon->folder, '/');
                $langAndFile = sprintf('%s%s',  $icon->multilang == 'yes' ? "$lang/" : "", $item->image);
                if (file_exists(getFullDirectory("pic/category/$iconFolder/$langAndFile"))) {
                    $backgroundImagePath = "pic/category/$iconFolder/$langAndFile";
                } else {
                    $backgroundImagePath = "pic/category/{$searchBox->name}/$iconFolder/$langAndFile";
                }
                $tdContent = <<<TDCONTENT
<input type="checkbox" id="cat{$item->id}" name="cat{$item->id}" value="{$checkboxValue}"{$checked} />
<a href="{$categoryHrefPrefix}cat={$item->id}"><img src="pic/cattrans.gif" class="{$item->class_name}" alt="{$item->name}" title="{$item->name}" style="background-image: url({$backgroundImagePath})" /></a>
TDCONTENT;
            } else {
                $tdContent = sprintf(
                    "<input name=\"%s_check\" value=\"%s\" class=\"btn medium\" type=\"button\" onclick=\"javascript:SetChecked('%s','%s_check','%s','%s',-1,10)\">",
                    $checkPrefix, nexus_trans('nexus.select_all'), $checkPrefix, $checkPrefix, nexus_trans('nexus.select_all'), nexus_trans('nexus.unselect_all')
                );
            }
            $td = <<<TD
<td align="left" class="bottom" style="padding-bottom: 4px;padding-left: {$searchBox->catpadding}px">
    $tdContent
</td>
TD;
            $html .= $td;
        }
        $html .= '</tr>';
    }
    //Taxonomy
    foreach ($withTaxonomies as $torrentField => $tableName) {
        if ($taxonomyNameLength > 0) {
            $namePrefix = substr($torrentField, 0, $taxonomyNameLength);
        } else {
            $namePrefix = $torrentField;
        }
        $html .= sprintf('<tr><td class="embedded" align="left">%s</td></tr>', $searchBox->getTaxonomyLabel($torrentField));
        /** @var \Illuminate\DataBase\Eloquent\Collection $taxonomyCollection */
        $taxonomyCollection = \Nexus\Database\NexusDB::table($tableName)
            ->where(function (\Illuminate\Database\Query\Builder $query) use ($mode) {
                return $query->whereIn('mode', [$mode, 0]);
            })
            ->orderBy('sort_index', 'desc')
            ->get()
        ;
        $modelName = \App\Models\SearchBox::$taxonomies[$torrentField]['model'];
        $checkPrefix = $torrentField;
        if (!empty($options['select_unselect'])) {
            $taxonomyCollection->push(new $modelName(['mode' => -1]));
        }
        $taxonomyChunks = $taxonomyCollection->chunk($searchBox->catsperrow);
        foreach ($taxonomyChunks as $chunk) {
            $html .= '<tr>';
            foreach ($chunk as $item) {
                if ($item->mode != -1) {
                    if ($taxonomyHrefPrefix) {
                        $afterInput = sprintf('<a href="%s%s=%s">%s</a>', $taxonomyHrefPrefix, $namePrefix, $item->id, $item->name);
                    } else {
                        $afterInput = $item->name;
                    }
                    $checked = '';
                    do_log("toCheck: $checkedValues, $namePrefix - {$item->id}", 'debug');
                    if ($checkedValues) {
                        if (
                            str_contains($checkedValues, "[{$namePrefix}{$item->id}]")
                            || (isset($checkedValuesArr["{$namePrefix}{$item->id}"]) && $checkedValuesArr["{$namePrefix}{$item->id}"] == 1)
                            || (isset($checkedValuesArr[$namePrefix]) && $checkedValuesArr[$namePrefix] == $item->id)
                        ) {
                            $checked = ' checked';
                        }
                    } elseif (!empty($options['user_notifs'])) {
                        $userNotifsKey = sprintf('[%s%s]', substr($torrentField, 0, 3), $item->id);
                        if (str_contains($options['user_notifs'], $userNotifsKey)) {
                            $checked = ' checked';
                        }
                    }
                    $tdContent = <<<TDCONTENT
<label><input type="checkbox" id="{$namePrefix}{$item->id}" name="{$namePrefix}{$item->id}" value="{$checkboxValue}"{$checked} />$afterInput</label>
TDCONTENT;
                } else {
                    $tdContent = sprintf(
                        "<input name=\"%s_check\" value=\"%s\" class=\"btn medium\" type=\"button\" onclick=\"javascript:SetChecked('%s','%s_check','%s','%s',-1,10)\">",
                        $checkPrefix, nexus_trans('nexus.select_all'), $checkPrefix, $checkPrefix, nexus_trans('nexus.select_all'), nexus_trans('nexus.unselect_all')
                    );
                }
                $td = <<<TD
<td align="left" class="bottom" style="padding-bottom: 4px;padding-left: {$searchBox->catpadding}px">
    $tdContent
</td>
TD;
                $html .= $td;
            }
            $html .= '</tr>';
        }
        $html .= '</tr>';
    }
    $html .= '</table>';
    return $html;
}

function datetimepicker_input($name, $value = '', $label = '', array $options = [])
{
    $lang = get_langfolder_cookie(true);
    if ($lang == 'zh_CN') {
        $lang = 'zh';
    }
    $lang = str_replace('_', '-', $lang);
    $js = '';
    if (!empty($options['require_files'])) {
        \Nexus\Nexus::css('vendor/jquery-datetimepicker/jquery.datetimepicker.min.css', 'footer', true);
        \Nexus\Nexus::js('vendor/jquery-datetimepicker/jquery.datetimepicker.full.min.js', 'footer', true);
        $js = "jQuery.datetimepicker.setLocale('{$lang}');";
    }
    $id = "datetime-picker-$name";
    $input = sprintf('%s<input type="text" id="%s" name="%s" value="%s" autocomplete="off" style="%s">', $label, $id, $name, $value, $options['style'] ?? '');
    $format = $options['format'] ?? 'Y-m-d H:i';
    $js .= <<<JS
jQuery("#{$id}").datetimepicker({
    format: '{$format}'
})
JS;
    \Nexus\Nexus::js($js, 'footer', false);
    return $input;
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
    $result = sprintf('<select name="search_area" style="%s">', $options['style'] ?? '');
    foreach ([0, 1, 3, 4] as $item) {
        $result .= sprintf(
            '<option value="%s"%s>%s</option>',
            $item, $item == $searchArea ? ' selected' : '', nexus_trans("search.search_area_options.$item")
        );
    }
    $result .= '</select>';
    return $result;
}

function torrent_name_for_admin(\App\Models\Torrent|null $torrent, $withTags = false, $length = 40)
{
    if (empty($torrent)) {
        return '';
    }
    $name = sprintf(
        '<div class="fi-color fi-color-primary fi-text-color-600 dark:fi-text-color-300 fi-link fi-size-sm fi-ac-link-action"><a href="/details.php?id=%s" target="_blank" title="%s">%s</a></div>',
        $torrent->id, $torrent->name, Str::limit($torrent->name, $length)
    );
    $tags = '';
    if ($withTags) {
        $tags = sprintf('&nbsp;<div>%s</div>', $torrent->tagsFormatted);
    }
    return new HtmlString('<div style="display:flex">' . $name . $tags . '</div>');
}

function username_for_admin(int $id)
{
    if (empty($id)) {
        return '';
    }
    return new HtmlString(get_username($id, false, true, true, true));
}

function can_view_post($uid, $post)
{
    static $topics = [];
    static $protectedForumIdArr;
    static $forumMods;
    if (!is_array($post)) {
        $post = \App\Models\Post::query()->findOrFail(intval($post))->toArray();
    }
    $topicId = $post['topicid'];
    if (!isset($topics[$topicId])) {
        $topics[$topicId] = \App\Models\Topic::query()->findOrFail($topicId);
    }
    /** @var \App\Models\Topic $topicInfo */
    $topicInfo = $topics[$topicId];

    $forumId = $topicInfo->forumid;

    if (is_null($protectedForumIdArr)) {
        $protectedForumIdArr = [];
        $protectedForumIds = \Nexus\Database\NexusDB::remember("setting_protected_forum", 600, function () {
            return \App\Models\Setting::getByName('misc.protected_forum');
        });
        $protectedForumIdArr = $protectedForumIds ? preg_split("/[,\s]+/", $protectedForumIds) : [];
    }
    if (is_null($forumMods)) {
        $forumMods = [];
        $results = \App\Models\ForumMod::query()->get();
        foreach ($results as $item) {
            $forumMods[$item->forumid] = $item->userid;
        }
    }
    $isForumMod = isset($forumMods[$forumId]) && $forumMods[$forumId] == $uid;
    $log = sprintf(
        "uid: $uid, class: %s,  post: {$post['id']}, forumId: $forumId, protectedForumIdArr: %s, forumMods: %s, isForumMod: %s",
        get_user_class(), json_encode($protectedForumIdArr), json_encode($forumMods), $isForumMod
    );
    if (
        in_array($forumId, $protectedForumIdArr)
        && get_user_class() < \App\Models\User::CLASS_ADMINISTRATOR
        && $uid != $post['userid']
        && $uid != $topicInfo->userid
        && !$isForumMod
    ) {
        do_log("$log, FALSE");
        return false;
    }
    do_log("$log, TRUE");
    return true;
}

function hide_text($text) {
	return \App\Support\Strings::hidden((string)$text);
}

function make_content_disposition(string $filename, string $disposition = 'attachment'): string {
	return \App\Support\Http::contentDisposition($filename, $disposition);
}

function bbcode_attach_to_img(string $text) {
    $pattern = "/\[attach\]([0-9a-zA-z][0-9a-zA-z]*)\[\/attach\]/is";
    return preg_replace_callback($pattern, function ($matches) {
        $dlkey = $matches[1];
        $httpdirectory_attachment = get_setting('attachment.httpdirectory');
        $row = \Nexus\Database\NexusDB::remember('attachment_'.$dlkey.'_content', 86400, function() use ($dlkey) {
            $record =  \App\Models\Attachment::query()->where("dlkey", $dlkey)->first();
            if ($record) {
                return $record->toArray();
            }
            return [];
        });
        if (empty($row) || $row['isimage'] != 1) {
            do_log(sprintf("dlkey: %s get attachment %s not exists or not image", $dlkey, json_encode($row)));
            return $matches[0];
        }
        $driver = $row['driver'] ?? 'local';
        if ($driver == "local") {
            if ($row['thumb'] == 1){
                $url = $httpdirectory_attachment."/".$row['location'].".thumb.jpg";
            } else {
                $url = $httpdirectory_attachment."/".$row['location'];
            }
            $url = sprintf("%s/%s", getSchemeAndHttpHost(true), trim($url, "/"));
        } else {
            $url = \Nexus\Attachment\Storage::getDriver($driver)->getImageUrl($row['location']);
        }
        return "[img]" . $url . "[/img]";
    }, $text, 20);
}

?>