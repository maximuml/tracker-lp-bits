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

function textbbcode($form, $text, $content = "", $hastitle = false, $col_num = 130, $withPreview = false)
{
    echo \App\Support\Form::bbcodeEditor((string) $form, (string) $text, (string) $content, (bool) $hastitle, (int) $col_num, (bool) $withPreview);
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
    echo \App\Support\Comment::table($rows, (string) $type, $parent_id, (bool) $review);
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
    echo \App\Support\TorrentTable::render($rows, (string) $variant, (int) $searchBoxId);
}


function get_username($id, $big = false, $link = true, $bold = true, $target = false, $bracket = false, $withtitle = false, $link_ext = "", $underline = false)
{
	return \App\Support\UserDisplay::username($id, $big, $link, $bold, $target, $bracket, $withtitle, $link_ext, $underline);
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
	return \App\Support\Promotion::backgroundStyle((int) $promotion, (string) $posState, $torrent, (string) $CURUSER['appendpromotion']);
}

function get_torrent_promotion_append($promotion = 1, $forcemode = "", $showtimeleft = false, $added = "", $promotionTimeType = 0, $promotionUntil = '', $ignoreGlobal = false)
{
	global $CURUSER, $lang_functions;
	global $expirehalfleech_torrent, $expirefree_torrent, $expiretwoup_torrent, $expiretwoupfree_torrent, $expiretwouphalfleech_torrent, $expirethirtypercentleech_torrent;

	return \App\Support\Promotion::append(
		(int) $promotion,
		(string) $forcemode,
		(bool) $showtimeleft,
		(string) $added,
		(int) $promotionTimeType,
		(string) $promotionUntil,
		(bool) $ignoreGlobal,
		(string) $CURUSER['appendpromotion'],
		$lang_functions,
		[
			'expirefree_torrent' => $expirefree_torrent,
			'expiretwoup_torrent' => $expiretwoup_torrent,
			'expiretwoupfree_torrent' => $expiretwoupfree_torrent,
			'expirehalfleech_torrent' => $expirehalfleech_torrent,
			'expiretwouphalfleech_torrent' => $expiretwouphalfleech_torrent,
			'expirethirtypercentleech_torrent' => $expirethirtypercentleech_torrent,
		]
	);
}

function get_torrent_promotion_append_sub($promotion = 1, $forcemode = "", $showtimeleft = false, $added = "", $promotionTimeType = 0, $promotionUntil = '', $ignoreGlobal = false)
{
	global $CURUSER, $lang_functions;
	global $expirehalfleech_torrent, $expirefree_torrent, $expiretwoup_torrent, $expiretwoupfree_torrent, $expiretwouphalfleech_torrent, $expirethirtypercentleech_torrent;

	return \App\Support\Promotion::appendSub(
		(int) $promotion,
		(string) $forcemode,
		(bool) $showtimeleft,
		(string) $added,
		(int) $promotionTimeType,
		(string) $promotionUntil,
		(bool) $ignoreGlobal,
		(string) $CURUSER['appendpromotion'],
		$lang_functions,
		[
			'expirefree_torrent' => $expirefree_torrent,
			'expiretwoup_torrent' => $expiretwoup_torrent,
			'expiretwoupfree_torrent' => $expiretwoupfree_torrent,
			'expirehalfleech_torrent' => $expirehalfleech_torrent,
			'expiretwouphalfleech_torrent' => $expiretwouphalfleech_torrent,
			'expirethirtypercentleech_torrent' => $expirethirtypercentleech_torrent,
		]
	);
}

function get_hr_img(array $torrent, $searchBoxId)
{
    return \App\Support\TorrentAccess::hrImage($torrent, $searchBoxId);
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
	return \App\Support\Strings::stripAllTags((string) $text);
}

function format_description($description)
{
	return \App\Support\Description::parse((string) $description);
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
    return \App\Support\Image::weserv((string) $url, $with !== null ? (int) $with : null, $height !== null ? (int) $height : null, (string) $fit);
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
    return \App\Support\SearchBox::requiredIds();
}

function can_access_torrent($torrent, $uid)
{
    return \App\Support\TorrentAccess::canAccess($torrent, $uid);
}

function get_ip_location_from_geoip($ip): bool|array
{
    return \App\Support\Network::geoIpInfo((string) $ip);
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
