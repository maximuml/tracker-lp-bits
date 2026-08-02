<?php

use App\Models\SearchBox;
use App\Models\TorrentExtra;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
/**
 * @param bool $transToLocale
 * @return string
 */
function get_langfolder_cookie($transToLocale = false)
{
	return \App\Support\Locale::folderFromCookie($_COOKIE["c_lang_folder"] ?? null, (bool) $transToLocale);
}
/**
 * @param string|int $user_id
 * @return string
 */
function get_user_lang($user_id)
{
	return \App\Support\Locale::userFolder($user_id);
}
/**
 * Assemble the legacy auth context from the current request/global state.
 *
 * This helper lives in the procedural wrapper layer so `App\Support\LegacyAuth`
 * can stay free of `$GLOBALS` and super-globals.
 */
function legacy_auth_context(): \App\Support\LegacyAuthContext
{
    $script = '';
    if (\function_exists('nexus')) {
        $script = \nexus()->getScript();
    } else {
        $scriptFile = $_SERVER['SCRIPT_FILENAME'] ?? '';
        $script = basename($scriptFile);
        if (str_contains($script, '.')) {
            $script = strstr($script, '.', true);
        }
    }

    return new \App\Support\LegacyAuthContext(
        user: $GLOBALS['CURUSER'] ?? null,
        lang: $GLOBALS['lang_functions'] ?? [],
        cache: $GLOBALS['Cache'] ?? null,
        ip: \function_exists('getip') ? \getip() : \App\Support\Network::clientIp(),
        requestUri: $_SERVER['REQUEST_URI'] ?? null,
        requestBody: $_POST,
        queryParams: $_GET,
        request: array_merge((array) $_POST, (array) $_GET),
        cookies: $_COOKIE,
        maxLoginAttempts: (int) ($GLOBALS['maxloginattempts'] ?? 0),
        captchaEnabled: ($GLOBALS['iv'] ?? '') === 'yes',
        registration: [
            'invitesystem' => $GLOBALS['invitesystem'] ?? '',
            'registration' => $GLOBALS['registration'] ?? '',
            'maxusers' => (int) ($GLOBALS['maxusers'] ?? 0),
            'maxip' => (int) ($GLOBALS['maxip'] ?? 0),
        ],
        langFolder: $_COOKIE['c_lang_folder'] ?? null,
        moderatorClass: defined('UC_MODERATOR') ? (int) \constant('UC_MODERATOR') : 0,
        script: $script,
    );
}

/**
 * Assemble the legacy page-layout context from the current request/global state.
 *
 * This helper lives in the procedural wrapper layer so `App\Support\PageLayout`
 * can stay free of `$GLOBALS` and super-globals.
 */
function page_layout_context(): \App\Support\PageLayoutContext
{
    $userUpdateSet = &$GLOBALS['USERUPDATESET'];
    if (! is_array($userUpdateSet)) {
        $userUpdateSet = [];
    }

    $script = '';
    if (\function_exists('nexus')) {
        $script = \nexus()->getScript();
    } else {
        $scriptFile = $_SERVER['SCRIPT_FILENAME'] ?? '';
        $script = basename($scriptFile);
        if (str_contains($script, '.')) {
            $script = strstr($script, '.', true);
        }
    }

    return new \App\Support\PageLayoutContext(
        user: $GLOBALS['CURUSER'] ?? null,
        lang: $GLOBALS['lang_functions'] ?? [],
        cache: $GLOBALS['Cache'] ?? null,
        defaultStylesheet: (int) ($GLOBALS['defcss'] ?? 0),
        langDir: $GLOBALS['CURLANGDIR'] ?? '',
        siteName: $GLOBALS['SITENAME'] ?? '',
        slogan: $GLOBALS['SLOGAN'] ?? '',
        logoMain: $GLOBALS['logo_main'] ?? '',
        baseUrl: $GLOBALS['BASEURL'] ?? '',
        siteOnline: $GLOBALS['SITE_ONLINE'] ?? 'yes',
        enableDonation: $GLOBALS['enabledonation'] ?? 'no',
        titleKeywordsTweak: $GLOBALS['titlekeywords_tweak'] ?? '',
        metaKeywordsTweak: $GLOBALS['metakeywords_tweak'] ?? '',
        metaDescriptionTweak: $GLOBALS['metadescription_tweak'] ?? '',
        cssDateTweak: $GLOBALS['cssdate_tweak'] ?? '',
        deleteNotTransferTwoAccount: (int) ($GLOBALS['deletenotransfertwo_account'] ?? 0),
        neverDeleteAccount: (int) ($GLOBALS['neverdelete_account'] ?? 0),
        iniUploadMain: (int) ($GLOBALS['iniupload_main'] ?? 0),
        dateFounded: $GLOBALS['datefounded'] ?? '',
        icpLicenseMain: $GLOBALS['icplicense_main'] ?? '',
        addKeyShortcut: $GLOBALS['add_key_shortcut'] ?? '',
        queryName: $GLOBALS['query_name'] ?? [],
        enableSqlDebugTweak: $GLOBALS['enablesqldebug_tweak'] ?? 'no',
        sqlDebugTweak: (int) ($GLOBALS['sqldebug_tweak'] ?? 0),
        analyticsCodeTweak: $GLOBALS['analyticscode_tweak'] ?? '',
        requestSearch: is_scalar($_GET['search'] ?? '') ? (string) ($_GET['search'] ?? '') : '',
        requestSearchArea: is_scalar($_GET['search_area'] ?? '') ? (string) ($_GET['search_area'] ?? '') : '',
        scriptFileName: $_SERVER['SCRIPT_FILENAME'] ?? '',
        script: $script,
        enableOffer: $GLOBALS['enableoffer'] ?? '',
        enableSpecial: $GLOBALS['enablespecial'] ?? '',
        customMenu: (string) \apply_filter('nexus_menu') ?: null,
        maxdlSystem: $GLOBALS['maxdlsystem'] ?? '',
        whereTweak: $GLOBALS['where_tweak'] ?? '',
        adminClass: defined('UC_ADMINISTRATOR') ? (int) \constant('UC_ADMINISTRATOR') : 0,
        moderatorClass: defined('UC_MODERATOR') ? (int) \constant('UC_MODERATOR') : 0,
        sysopClass: defined('UC_SYSOP') ? (int) \constant('UC_SYSOP') : 0,
        vipClass: defined('UC_VIP') ? (int) \constant('UC_VIP') : 0,
        userUpdateSet: $userUpdateSet,
    );
}

/**
 * @param string $script_name
 * @param bool $target
 * @param string $lang_folder
 * @return string
 */
function get_langfile_path($script_name ="", $target = false, $lang_folder = "")
{
	return \App\Support\Locale::scriptFilePath((string) $script_name, (bool) $target, (string) $lang_folder);
}
/**
 * @param string $heading
 * @param string $text
 * @param bool $htmlstrip
 * @return void
 */
function stdmsg($heading, $text, $htmlstrip = false) {
	echo \App\Support\Frame::stdMessage($heading, $text, $htmlstrip);
}
/**
 * @param string $heading
 * @param string $text
 * @param bool $htmlstrip
 * @param bool $head
 * @param bool $foot
 * @param bool $die
 * @return void
 */
function stderr($heading, $text, $htmlstrip = true, $head = true, $foot = true, $die = true)
{
	\App\Support\LegacyResponse::abort($heading, $text, $htmlstrip, $head, $foot, $die);
}
/**
 * @param string $file
 * @param string $line
 * @return void
 */
function sqlerr($file = '', $line = '')
{
	\App\Support\LegacyResponse::sqlError((string) $file, (string) $line);
}
/**
 * @param string $s
 * @return string
 */
function format_quotes($s)
{
    return \App\Support\BBCode::quotes((string) $s, (string) nexus_trans("label.text_quote"));
}

/**
 * @param string $dlkey
 * @param bool $enableimage
 * @param bool $imageresizer
 * @return string
 */
function print_attachment($dlkey, $enableimage = true, $imageresizer = true)
{
	return \App\Support\Attachment::renderByKey((string) $dlkey, (bool) $enableimage, (bool) $imageresizer);
}
/**
 * @param string $value
 * @return string
 */
function addTempCode($value) {
	return \App\Support\Comment::addTempCode((string) $value);
}
/**
 * @param string $url
 * @param bool $newWindow
 * @param string $text
 * @param string $linkClass
 * @return string
 */
function formatUrl($url, $newWindow = false, $text = '', $linkClass = '') {
    return \App\Support\Html::formatUrl((string) $url, (bool) $newWindow, (string) $text, (string) $linkClass);
}
/**
 * @param string $text
 * @return string
 */
function formatCode($text) {
    return addTempCode(\App\Support\BBCode::code((string) $text, (string) nexus_trans("label.text_code")));
}

/**
 * @param string $src
 * @param bool $enableImageResizer
 * @param int $image_max_width
 * @param int $image_max_height
 * @param string $imgId
 * @return string
 */
function formatImg($src, $enableImageResizer, $image_max_width, $image_max_height, $imgId = "") {
    return \App\Support\Html::formatImg((string) $src, (bool) $enableImageResizer, (int) $image_max_width, (int) $image_max_height, (string) $imgId);
}

/**
 * @param string $src
 * @param string|int $width
 * @param string|int $height
 * @return string
 */
function formatFlash($src, $width, $height) {
    return \App\Support\Html::formatFlash((string) $src, $width, $height);
}
/**
 * @param string $src
 * @param string|int $width
 * @param string|int $height
 * @return string
 */
function formatFlv($src, $width, $height) {
    return \App\Support\Html::formatFlv((string) $src, $width, $height);
}
/**
 * @param string $src
 * @param string|int $width
 * @param string|int $height
 * @return string
 */
function formatYoutube($src, $width = '', $height = ''): string
{
    return \App\Support\Html::formatYoutube((string) $src, $width, $height);
}

/**
 * @param string $src
 * @param string|int $width
 * @param string|int $height
 * @return string
 */
function formatVideo($src, $width, $height) {
    return \App\Support\Html::formatVideo((string) $src, $width, $height);
}

/**
 * @param string $src
 * @return string
 */
function formatAudio($src) {
    return \App\Support\Html::formatAudio((string) $src);
}

/**
 * @param string $content
 * @param string $title
 * @param bool $defaultCollapsed
 * @return string
 */
function formatSpoiler($content, $title = '', $defaultCollapsed = true): string
{
    return \App\Support\Html::formatSpoiler((string) $content, (string) $title, (bool) $defaultCollapsed);
}

/**
 * @param string $content
 * @return string
 */
function formatHidden($content): string
{
    return addTempCode(\App\Support\BBCode::hidden((string) $content));
}

/**
 * @param string $text
 * @param string $align
 * @return string
 */
function formatTextAlign($text, $align): string
{
    return addTempCode(\App\Support\BBCode::textAlign((string) $text, (string) $align));
}

/**
 * @param string $text
 * @param bool $newWindow
 * @return string
 */
function format_urls($text, $newWindow = false)
{
	return \App\Support\BBCode::formatUrls((string) $text, (bool) $newWindow);
}
/**
 * @param string $text
 * @param bool $strip_html
 * @param bool $xssclean
 * @param bool $newtab
 * @param bool $imageresizer
 * @param int $image_max_width
 * @param bool $enableimage
 * @param bool $enableflash
 * @param int $imagenum
 * @param int $image_max_height
 * @return string
 */
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
/**
 * @param string $search
 * @param string $subject
 * @param string $hlstart
 * @param string $hlend
 * @return string
 */
function highlight($search,$subject,$hlstart='<b><font class="striking">',$hlend="</font></b>")
{
	return \App\Support\Strings::highlight((string)$search, (string)$subject, $hlstart, $hlend);
}

/**
 * @param string|int $class
 * @param bool $compact
 * @param bool $b_colored
 * @param bool $I18N
 * @param array<array-key, mixed> $options
 * @return string
 */
function get_user_class_name($class, $compact = false, $b_colored = false, $I18N = false, array $options = [])
{
	return \App\Support\UserClass::name($class, $compact, $b_colored, $I18N, $options);
}
/**
 * @param mixed $class
 * @return bool
 */
function is_valid_user_class($class)
{
	return \App\Support\Validators::isUserClass($class);
}
/**
 * @param mixed $value
 * @param bool $stdhead
 * @param bool $stdfood
 * @param bool $die
 * @param bool $log
 * @return bool
 */
function int_check($value,$stdhead = false, $stdfood = true, $die = true, $log = true) {
	return \App\Support\LegacyResponse::assertId($value, $stdhead, $stdfood, $die, $log);
}
/**
 * @param mixed $id
 * @return bool
 */
function is_valid_id($id)
{
	return \App\Support\Validators::isId($id);
}


//-------- Begins a main frame
/**
 * @param string $caption
 * @param bool $center
 * @param string|int $width
 * @return void
 */
function begin_main_frame($caption = "", $center = false, $width = 100) {
	echo \App\Support\Frame::mainOpen($caption, $center, $width, CONTENT_WIDTH);
}
/**
 * @return void
 */
function end_main_frame() {
	echo \App\Support\Frame::CLOSE;
}
/**
 * @param string $caption
 * @param bool $center
 * @param int $padding
 * @param string $width
 * @param string $caption_center
 * @return void
 */
function begin_frame($caption = "", $center = false, $padding = 10, $width="100%", $caption_center="left") {
	echo \App\Support\Frame::open($caption, $center, $padding, $width, $caption_center);
}
/**
 * @return void
 */
function end_frame() {
	echo \App\Support\Frame::CLOSE;
}
/**
 * @param bool $fullwidth
 * @param int $padding
 * @return void
 */
function begin_table($fullwidth = false, $padding = 5) {
	echo \App\Support\Frame::tableOpen($fullwidth, $padding);
}
/**
 * @return void
 */
function end_table() {
	echo \App\Support\Frame::TABLE_CLOSE;
}

//-------- Inserts a smilies frame
//         (move to globals)
/**
 * @return void
 */
function insert_smilies_frame()
{
	global $lang_functions;
	echo \App\Support\Smilies::framedTable($lang_functions['text_smilies'], $lang_functions['col_type_something'], $lang_functions['col_to_make_a']);
}
/**
 * @param mixed $ratio
 * @return string
 */
function get_ratio_color($ratio)
{
	return \App\Support\Ratio::color((float)$ratio);
}
/**
 * @param mixed $ratio
 * @return string
 */
function get_slr_color($ratio)
{
	return \App\Support\Ratio::seedLeechColor((float)$ratio);
}
/**
 * @param string $text
 * @param string $security
 * @return void
 */
function write_log($text, $security = "normal")
{
    \App\Support\Log::write((string) $text, (string) $security, get_user_id());
}

/**
 * @param int $ts
 * @param bool $shortunit
 * @return string
 */
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
/**
 * @param string $form
 * @param string $text
 * @param string $content
 * @param bool $hastitle
 * @param int $col_num
 * @param bool $withPreview
 * @return void
 */
function textbbcode($form, $text, $content = "", $hastitle = false, $col_num = 130, $withPreview = false)
{
    echo \App\Support\Form::bbcodeEditor((string) $form, (string) $text, (string) $content, (bool) $hastitle, (int) $col_num, (bool) $withPreview);
}

/**
 * @param string $title
 * @param string $type
 * @param string $body
 * @param bool $hassubject
 * @param string $subject
 * @param int $maxsubjectlength
 * @return void
 */
function begin_compose($title = "", $type = "new", $body = "", $hassubject = true, $subject = "", $maxsubjectlength = 100)
{
	echo \App\Support\Frame::composeBegin((string) $title, (string) $type, (string) $body, (bool) $hassubject, (string) $subject, (int) $maxsubjectlength);
}
/**
 * @return void
 */
function end_compose()
{
	echo \App\Support\Frame::composeEnd();
}
/**
 * @param string $keyword
 * @param string|int $userid
 * @param bool $pre_escaped
 * @return void
 */
function insert_suggest($keyword, $userid, $pre_escaped = true)
{
	\App\Support\SearchSuggest::add((string) $keyword, $userid, (bool) $pre_escaped);
}


// it's a stub implemetation here, we need more acurate regression analysis to complete our algorithm
/**
 * @param array<array-key, mixed> $user_snatched_arr
 * @return float
 */
function get_torrent_2_user_value($user_snatched_arr)
{
	return \App\Support\TorrentOps::userValue((array) $user_snatched_arr);
}
/**
 * @return void
 */
function cur_user_check()
{
    \App\Support\LegacyAuth::currentUserCheck(legacy_auth_context());
}
/**
 * @param string $type
 * @param float $point
 * @param string|int $id
 * @return void
 */
function KPS($type = "+", $point = 1.0, $id = "")
{
	\App\Support\Bonus::updatePoints((string) $type, (float) $point, $id);
}
/**
 * @param mixed $peer_id
 * @param string $agent
 * @return string
 */
function get_agent($peer_id, $agent)
{
	return \App\Support\Strings::userAgentClient((string)$agent);
}
/**
 * @param string $url
 * @return void
 */
function nexus_redirect($url)
{
    \App\Support\LegacyResponse::redirect((string) $url);
}
/**
 * @param string|int $id
 * @param string $field
 * @return void
 */
function set_cachetimestamp($id, $field = "cache_stamp")
{
	\App\Support\Cache::touchTorrent($id, $field);
}
/**
 * @param string|int $id
 * @param string $field
 * @return void
 */
function reset_cachetimestamp($id, $field = "cache_stamp")
{
	\App\Support\Cache::resetTorrent($id, $field);
}
/**
 * @param string $file
 * @param bool $endpage
 * @param int $cachetime
 * @return bool
 */
function cache_check ($file = 'cachefile',$endpage = true, $cachetime = 600) {
	return \App\Support\Cache::pageCheck((string) $file, (bool) $endpage, (int) $cachetime);
}
/**
 * @param string $file
 * @return void
 */
function cache_save  ($file = 'cachefile') {
	\App\Support\Cache::pageSave((string) $file);
}
/**
 * @param string $lang
 * @return string
 */
function get_email_encode($lang)
{
	return \App\Support\Email::charsetFor((string) $lang);
}
/**
 * @param string $lang
 * @param string $content
 * @return string|false
 */
function change_email_encode($lang, $content)
{
	return \App\Support\Email::convertCharset((string) $lang, (string) $content);
}
/**
 * @param string $email
 * @return string
 */
function safe_email($email) {
	return \App\Support\Email::sanitizeForDisplay((string) $email);
}
/**
 * @param string $email
 * @return bool
 */
function check_email ($email) {
	return \App\Support\Email::isWellFormed((string) $email);
}
/**
 * @param string $to
 * @param string $fromname
 * @param string $fromemail
 * @param string $subject
 * @param string $body
 * @param string $type
 * @param bool $showmsg
 * @param bool $multiple
 * @param string $multiplemail
 * @param string $hdr_encoding
 * @param mixed $specialcase
 * @return bool
 */
function sent_mail($to,$fromname,$fromemail,$subject,$body,$type = "confirmation",$showmsg=true,$multiple=false,$multiplemail='',$hdr_encoding = 'UTF-8', $specialcase = '') {
	return \App\Support\Mail::sentLegacy(
		(string) $to,
		(string) $fromname,
		(string) $fromemail,
		(string) $subject,
		(string) $body,
		(string) $type,
		(bool) $showmsg,
		(bool) $multiple,
		(string) (is_array($multiplemail) ? implode(',', $multiplemail) : $multiplemail),
		(string) $hdr_encoding
	);
}
/**
 * @param string $type
 * @return void
 */
function failedloginscheck ($type = 'Login') {
    \App\Support\LegacyAuth::failedLoginsCheck((string) $type, legacy_auth_context());
}
/**
 * @param string $type
 * @param bool $recover
 * @param bool $head
 * @return void
 */
function failedlogins ($type = 'login', $recover = false, $head = true)
{
    \App\Support\LegacyAuth::failedLogins((string) $type, (bool) $recover, (bool) $head, legacy_auth_context());
}

/**
 * @param string $type
 * @param bool $recover
 * @param bool $head
 * @return void
 */
function login_failedlogins($type = 'login', $recover = false, $head = true)
{
    \App\Support\LegacyAuth::loginFailedLogins((string) $type, (bool) $recover, (bool) $head, legacy_auth_context());
}

/**
 * @param string $type
 * @return string
 */
function remaining($type = 'login')
{
    $context = legacy_auth_context();
    return \App\Support\LegacyAuth::remainingAttempts((string) $type, $context->maxLoginAttempts, $context->ip);
}
/**
 * @param string $type
 * @param bool $maxuserscheck
 * @param bool $ipcheck
 * @return bool
 */
function registration_check($type = "invitesystem", $maxuserscheck = true, $ipcheck = true) {
    return \App\Support\LegacyAuth::registrationCheck((string) $type, (bool) $maxuserscheck, (bool) $ipcheck, legacy_auth_context());
}

/**
 * @param int $length
 * @return string
 */
function random_str($length = 6)
{
	return \App\Support\Strings::randomCode((int)$length);
}
/**
 * @return App\Services\Captcha\CaptchaManager
 */
function captcha_manager(): \App\Services\Captcha\CaptchaManager
{
    return \App\Support\Captcha::manager();
}
/**
 * @return mixed
 */
function image_code () {
    return \App\Support\Captcha::imageCode();
}
/**
 * @param string $imagehash
 * @param string $imagestring
 * @param string $where
 * @param bool $maxattemptlog
 * @param bool $head
 * @return bool
 */
function check_code ($imagehash, $imagestring, $where = 'signup.php', $maxattemptlog = false, $head = true) {
    return \App\Support\LegacyAuth::checkCode((string) $imagehash, (string) $imagestring, (string) $where, (bool) $maxattemptlog, (bool) $head, legacy_auth_context());
}

/**
 * @return void
 */
function show_image_code () {
    global $lang_functions, $iv;
    \App\Support\Captcha::render((string) $iv, [
        'row_security_image' => $lang_functions['row_security_image'] ?? '',
        'row_security_challenge' => $lang_functions['row_security_challenge'] ?? '',
        'row_security_code' => $lang_functions['row_security_code'] ?? '',
    ], (string) ($_GET['secret'] ?? ''));
}
/**
 * @param string $ip
 * @return array<array-key, mixed>
 */
function get_ip_location($ip)
{
	global $lang_functions;

	return \App\Support\Network::ipLocation(
		(string) $ip,
		(string) ($lang_functions['text_unknown'] ?? ''),
		(string) ($lang_functions['text_user_ip'] ?? 'User IP')
	);
}
/**
 * @param bool $long
 * @param string $targetip
 * @param mixed $ip_one
 * @param mixed $ip_two
 * @return bool
 */
function in_ip_range($long, $targetip, $ip_one, $ip_two=false) {
	return \App\Support\Network::ipInRange($long, $targetip, $ip_one, $ip_two);
}

/**
 * @param string $ip
 * @return int|false
 */
function validip_format($ip)
{
	return \App\Support\Network::isValidIpv4Format((string) $ip);
}
/**
 * @return string
 */
function maxslots () {
	global $lang_functions, $CURUSER, $maxdlsystem;
	return \App\Support\Slots::display(
		(float) $CURUSER["uploaded"],
		(float) $CURUSER["downloaded"],
		(string) $maxdlsystem,
		(int) \get_user_class(),
		(int) UC_VIP,
		(string) ($lang_functions['text_slots'] ?? ''),
		(string) ($lang_functions['text_unlimited'] ?? '')
	);
}


/**
 * @param bool $autoclean
 * @param bool $doLogin
 * @return void
 */
function dbconn($autoclean = false, $doLogin = true)
{
    \App\Support\Bootstrap::connect((bool) $autoclean, (bool) $doLogin);
}
/**
 * @return bool
 */
function userlogin() {
    $context = legacy_auth_context();
    $user = \App\Support\LegacyAuth::loginFromCookie($context);
    if ($user !== null) {
        $GLOBALS['oldip'] = $user['old_ip'] ?? $user['ip'] ?? '';
        $GLOBALS['CURUSER'] = $user;
        return true;
    }
    unset($GLOBALS['CURUSER']);
    return false;
}
/**
 * @param bool $printProgress
 * @return string|bool
 */
function autoclean($printProgress = false) {
	return \App\Support\Bootstrap::autoClean((bool) $printProgress);
}
/**
 * @param string|int|float $amount
 * @param string $unit
 * @return float
 */
function getsize_int($amount, $unit = "G")
{
	return \App\Support\Format::bytesFromUnit((float)$amount, $unit);
}
/**
 * @param int|float $bytes
 * @return string
 */
function mksize_compact($bytes)
{
	return \App\Support\Format::sizeCompact((float)$bytes);
}
/**
 * @param int|float $bytes
 * @return string
 */
function mksize_loose($bytes)
{
	return \App\Support\Format::sizeLoose((float)$bytes);
}
/**
 * @param int|float $bytes
 * @return string
 */
function mksize($bytes)
{
	return \App\Support\Format::size((float)$bytes);
}

/**
 * @param int|float $bytes
 * @return string
 */
function mksizeint($bytes)
{
	return \App\Support\Format::sizeInt((float)$bytes);
}
/**
 * @return int
 */
function deadtime() {
	return \App\Support\Time::deadThreshold((int)get_setting("main.anninterthree"));
}
/**
 * @param int|float $s
 * @return string
 */
function mkprettytime($s) {
	global $lang_functions;
	return \App\Support\Format::prettyTime((float)$s, $lang_functions['text_day'] ?? 'day(s)');
}
/**
 * @param array<array-key, mixed>|string $vars
 * @return int
 */
function mkglobal($vars) {
	return \App\Support\Input::globalize($vars, $_GET, $_POST);
}
/**
 * @param mixed $x
 * @return mixed
 */
function unesc($x) {
	return \App\Support\Input::unescape($x);
}
/**
 * @param string $x
 * @param string $y
 * @param bool|int $noesc
 * @param string $relation
 * @param bool $return
 * @return string|null
 */
function tr($x, $y, $noesc = false, $relation = '', $return = false) {
	return \App\Support\Html::emitSettingsRow($x, $y, !(bool) $noesc, $relation, $return);
}
/**
 * @param string $x
 * @param string $y
 * @param bool|int $noesc
 * @param string $relation
 * @param bool $return
 * @return string|null
 */
function tr_small($x, $y, $noesc = false, $relation = '', $return = false) {
	return \App\Support\Html::emitSettingsRowSmall($x, $y, !(bool) $noesc, $relation, $return);
}
/**
 * @param string $x
 * @param string $y
 * @param mixed $nosec
 * @return void
 */
function twotd($x,$y,$nosec=0){
	echo \App\Support\Html::settingsCells($x, $y);
}
/**
 * @param string $name
 * @return bool
 */
function validfilename($name) {
	return \App\Support\Validators::isUploadFilename($name);
}
/**
 * @param string $email
 * @return bool
 */
function validemail($email) {
    return \App\Support\Validators::isEmail($email);
}
/**
 * @param string|int $langid
 * @return string
 */
function validlang($langid) {
	global $deflang;
	return \App\Support\Locale::folderForId($langid, (string) $deflang);
}
/**
 * @return bool
 */
function get_if_restricted_is_open()
{
	return \App\Support\Time::isWeekendUploadOpen(\App\Models\Setting::getIsUploadOpenAtWeekend(), time());
}
/**
 * @param mixed $selected
 * @return void
 */
function menu ($selected = "home") {
    $langFunctions = $GLOBALS['lang_functions'] ?? [];
    $customMenu = (string) \apply_filter('nexus_menu');

    $result = \App\Support\Menu::render(
        \function_exists('nexus') ? \nexus()->getScript() : '',
        (array) $langFunctions,
        (string) ($GLOBALS['enableoffer'] ?? ''),
        (string) ($GLOBALS['enablespecial'] ?? ''),
        $customMenu !== '' ? $customMenu : null,
        $GLOBALS['CURUSER'] ?? null,
        $GLOBALS['Cache'] ?? null,
        $GLOBALS['CURLANGDIR'] ?? '',
    );

    $CURUSER = $GLOBALS['CURUSER'] ?? null;
    if ($CURUSER && ($GLOBALS['where_tweak'] ?? '') === 'yes') {
        $GLOBALS['USERUPDATESET']['page'] = $result['selected'];
    }

    echo $result['html'];
}
/**
 * @return array<array-key, mixed>|null
 */
function get_css_row() {
	global $CURUSER, $defcss, $Cache;
	return \App\Support\Style::cssRow($Cache, $CURUSER ? $CURUSER["stylesheet"] : $defcss, $defcss);
}
/**
 * @param string $file
 * @return string
 */
function get_css_uri($file = "")
{
    global $defcss, $Cache, $CURUSER;
	return \App\Support\Style::cssUri($Cache, $CURUSER ? $CURUSER["stylesheet"] : $defcss, $defcss, (string) $file);
}
/**
 * @return string
 */
function get_font_css_uri(){
	global $CURUSER;
	return \App\Support\Style::fontCssUri($CURUSER['fontsize'] ?? null);
}
/**
 * @return string
 */
function get_style_addicode()
{
	global $defcss, $Cache, $CURUSER;
	return \App\Support\Style::addiCode($Cache, $CURUSER ? $CURUSER["stylesheet"] : $defcss, $defcss);
}
/**
 * @param string|int $cat
 * @return string
 */
function get_cat_folder($cat = 101)
{
	global $CURLANGDIR;

	return \App\Support\Path::categoryFolderForId($cat, (string) $CURLANGDIR);
}
/**
 * @return string
 */
function get_style_highlight()
{
	global $CURUSER;
	return \App\Support\Style::highlightColor($CURUSER ? (int) $CURUSER["stylesheet"] : null);
}
/**
 * @param string $title
 * @param bool $msgalert
 * @param string $script
 * @param string $place
 * @return void
 */
function stdhead($title = "", $msgalert = true, $script = "", $place = "")
{
    $context = page_layout_context();
    \App\Support\PageLayout::setContext($context);
    \App\Support\PageLayout::header($title, $msgalert, $script, $place);
}

/**
 * @return void
 */
function stdfoot()
{
    \App\Support\PageLayout::footer();
}

/**
 * @param string $x
 * @param string $y
 * @return void
 */
function genbark($x,$y) {
	\App\Support\LegacyResponse::bark((string) $y, (string) $x);
}
/**
 * @param int $len
 * @return string
 */
function mksecret($len = 20) {
	return \App\Support\Token::randomHex((int) $len);
}
/**
 * @param mixed $code
 * @return void
 */
function httperr($code = 404) {
	\App\Support\LegacyResponse::notFound();
}
/**
 * @param int $id
 * @param string $authKey
 * @param int $duration
 * @return void
 */
function logincookie($id, $authKey, $duration = 0)
{
    \App\Support\AuthCookie::setLoginCookie((int) $id, (string) $authKey, (int) $duration);
}
/**
 * @param string $folder
 * @param int $expires
 * @return void
 */
function set_langfolder_cookie($folder, $expires = 0x7fffffff)
{
	\App\Support\Locale::setFolderCookie((string) $folder, (int) $expires);
}
/**
 * @return string
 */
function get_protocol_prefix() {
	return \App\Support\Http::protocolPrefix(isHttps());
}
/**
 * @param string $lang
 * @return int
 */
function get_langid_from_langcookie($lang = '')
{
    return \App\Support\Locale::idFromCookie((string) $lang);
}
/**
 * @param string $pre
 * @param string $folder_name
 * @return string
 */
function make_folder($pre, $folder_name)
{
	return \App\Support\Path::makeFolder($pre, $folder_name, ROOT_PATH);
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
/**
 * @return void
 */
function logoutcookie() {
	\App\Support\AuthCookie::clear();
}
/**
 * @param string $string
 * @param mixed $encode
 * @return string
 */
function base64 ($string, $encode=true) {
	return $encode ? \App\Support\Codec::base64Encode((string) $string) : \App\Support\Codec::base64Decode((string) $string);
}
/**
 * @param bool $mainpage
 * @return void
 */
function loggedinorreturn($mainpage = false) {
    \App\Support\LegacyAuth::requireLogin((bool) $mainpage, legacy_auth_context());
}
/**
 * @param mixed $id
 * @param bool $notify
 * @return void
 */
function deletetorrent($id, $notify = false) {
    \App\Support\TorrentOps::deleteTorrents($id, (bool) $notify);
}
/**
 * @param int $rpp
 * @param int $count
 * @param string $href
 * @param array<array-key, mixed> $opts
 * @param string $pagename
 * @return array<array-key, mixed>
 */
function pager($rpp, $count, $href, $opts = array(), $pagename = "page") {
	return \App\Support\Pagination::pager((int) $rpp, (int) $count, (string) $href, (array) $opts, (string) $pagename);
}
/**
 * @param array<array-key, mixed> $rows
 * @param string $type
 * @param string|int $parent_id
 * @param bool $review
 * @return void
 */
function commenttable($rows, $type, $parent_id, $review = false)
{
    echo \App\Support\Comment::table($rows, (string) $type, $parent_id, (bool) $review);
}

/**
 * @param string $s
 * @return string
 */
function searchfield($s) {
	return \App\Support\Strings::normalizeSearchTerm((string)$s);
}
/**
 * @param string|int $catmode
 * @return array<array-key, mixed>
 */
function genrelist($catmode = 1) {
	global $Cache;
	return \App\Support\Category::listByMode($Cache, $catmode);
}
/**
 * @param string $table
 * @param int $mode
 * @return array<array-key, mixed>
 */
function searchbox_item_list(string $table, int $mode){
	global $Cache;
	return \App\Support\SearchBox::itemList($Cache, $table, $mode);
}
/**
 * @param string $type
 * @param bool|null $enabled
 * @return array<array-key, mixed>
 */
function langlist($type, $enabled = null) {
	return \App\Support\Locale::languageList($type, $enabled);
}
/**
 * @param string|int|null $num
 * @return string
 */
function linkcolor($num) {
	return \App\Support\Palette::seederLink($num);
}
/**
 * @param string|int $userid
 * @param string $comment
 * @param mixed $oldModcomment
 * @return void
 */
function writecomment($userid, $comment, $oldModcomment = null) {
    \App\Support\UserOps::logModify($userid, (string) $comment);
}
/**
 * @param string|int $userid
 * @return array<array-key, mixed>
 */
function return_torrent_bookmark_array($userid)
{
	global $Cache;
	return \App\Support\TorrentBookmark::bookmarkArray($Cache, $userid);
}
/**
 * @param string|int $userid
 * @param string|int $torrentid
 * @param bool $text
 * @return string
 */
function get_torrent_bookmark_state($userid, $torrentid, $text = false)
{
	global $Cache, $lang_functions;
	return \App\Support\TorrentBookmark::stateMarkup($Cache, $userid, $torrentid, (bool) $text, [
		'title_bookmark_torrent' => $lang_functions['title_bookmark_torrent'] ?? '',
		'title_delbookmark_torrent' => $lang_functions['title_delbookmark_torrent'] ?? '',
	]);
}
/**
 * @param array<array-key, mixed> $rows
 * @param string $variant
 * @param int $searchBoxId
 * @return void
 */
function torrenttable($rows, $variant = "torrent", $searchBoxId = 0) {
    echo \App\Support\TorrentTable::render($rows, (string) $variant, (int) $searchBoxId);
}

/**
 * @param string|int $id
 * @param bool $big
 * @param bool $link
 * @param bool $bold
 * @param bool $target
 * @param bool $bracket
 * @param bool $withtitle
 * @param string $link_ext
 * @param bool $underline
 * @return string
 */
function get_username($id, $big = false, $link = true, $bold = true, $target = false, $bracket = false, $withtitle = false, $link_ext = "", $underline = false)
{
	return \App\Support\UserDisplay::username($id, $big, $link, $bold, $target, $bracket, $withtitle, $link_ext, $underline);
}
/**
 * @param string|int|float $p
 * @return string
 */
function get_percent_completed_image($p) {
	return \App\Support\Progress::percentImage($p);
}
/**
 * @param mixed $ratio
 * @return string
 */
function get_ratio_img($ratio)
{
	return \App\Support\Ratio::image((float)$ratio);
}
/**
 * @param array<array-key, mixed>|string $name
 * @return mixed
 */
function GetVar ($name) {
	return \App\Support\Input::getVar($name);
}
/**
 * @param array<array-key, mixed>|string $arg
 * @return array<array-key, mixed>|string
 */
function ssr ($arg) {
	return \App\Support\Strings::stripSlashesDeep(is_array($arg) ? $arg : (string)$arg);
}
/**
 * @return void
 */
function parked()
{
    \App\Support\LegacyAuth::parked(legacy_auth_context());
}

/**
 * @param string $username
 * @return bool
 */
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
/**
 * @param string $ibm_437
 * @param string $view
 * @return string
 */
function code($ibm_437, $view) {
	return \App\Support\Codec::ibm437ToEntitiesLegacy((string) $ibm_437, (string) $view);
}

/**
 * @param string $ibm_437
 * @param string $view
 * @return string
 * @ref https://github.com/HDInnovations/UNIT3D-Community-Edition/blob/master/app/Helpers/Nfo.php
 */
function code_new($ibm_437, $view)
{
	return \App\Support\Codec::ibm437ToEntities((string) $ibm_437, (string) $view);
}


//Tooltip container for hot movie, classic movie, etc
/**
 * @param iterable<array-key, mixed> $id_content_arr
 * @param mixed $width
 * @return void
 */
function create_tooltip_container($id_content_arr, $width = 400)
{
	echo \App\Support\Html::tooltipContainer($id_content_arr);
}

/**
 * @param string $formname
 * @param string $taname
 * @param string $submit
 * @return void
 */
function quickreply($formname, $taname,$submit){
	echo \App\Support\Html::quickReply((string) $formname, (string) $taname, (string) $submit);
}
/**
 * @param string $formname
 * @param string $taname
 * @return string
 */
function smile_row($formname, $taname){
	return \App\Support\Smilies::quickRow($formname, $taname);
}
/**
 * @param string $formname
 * @param string $taname
 * @param int $smilyNumber
 * @return string
 */
function getSmileIt($formname, $taname, $smilyNumber) {
	return \App\Support\Smilies::link($formname, $taname, (int) $smilyNumber);
}
/**
 * @param string $selectname
 * @param int $maxclass
 * @param string|int $selected
 * @param int $minClass
 * @param bool $includeNoClass
 * @param bool $disabled
 * @return string
 */
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
/**
 * @param int|null $allowMinimumClass
 * @return void
 */
function permissiondenied($allowMinimumClass = null){
	\App\Support\LegacyResponse::permissionDenied($allowMinimumClass);
}
/**
 * @param mixed $time
 * @param bool $withago
 * @param bool $twoline
 * @param bool $forceago
 * @param bool $oneunit
 * @param bool $isfuturetime
 * @return mixed
 */
function gettime($time, $withago = true, $twoline = false, $forceago = false, $oneunit = false, $isfuturetime = false){
	return \App\Support\Time::format($time, $withago, $twoline, $forceago, $oneunit, $isfuturetime);
}
/**
 * @return string
 */
function get_forum_pic_folder(){
	global $CURLANGDIR;
	return \App\Support\Forum::picFolder((string) $CURLANGDIR);
}
/**
 * @param string|int $typeid
 * @return array<array-key, mixed>|null
 */
function get_category_icon_row($typeid)
{
	global $Cache;
	return \App\Support\Category::iconRow($Cache, $typeid);
}
/**
 * @param string|int|null $catid
 * @return array<array-key, mixed>|null
 */
function get_category_row($catid = NULL)
{
	global $Cache;
	return \App\Support\Category::row($Cache, $catid);
}
/**
 * @param array<array-key, mixed> $row
 * @return string
 */
function get_second_icon($row) //for CHDBits
{
	global $Cache;
	return \App\Support\Category::secondIcon($Cache, $row, get_cat_folder($row['category'] ?? ''));
}
/**
 * @param int $promotion
 * @param string $posState
 * @param array<array-key, mixed> $torrent
 * @return string
 */
function get_torrent_bg_color($promotion = 1, $posState = "", array $torrent = [])
{
	global $CURUSER;
	return \App\Support\Promotion::backgroundStyle((int) $promotion, (string) $posState, $torrent, (string) $CURUSER['appendpromotion']);
}
/**
 * @param int $promotion
 * @param string $forcemode
 * @param bool $showtimeleft
 * @param string $added
 * @param int $promotionTimeType
 * @param string $promotionUntil
 * @param bool $ignoreGlobal
 * @return string
 */
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
/**
 * @param int $promotion
 * @param string $forcemode
 * @param bool $showtimeleft
 * @param string $added
 * @param int $promotionTimeType
 * @param string $promotionUntil
 * @param bool $ignoreGlobal
 * @return string
 */
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
/**
 * @param array<array-key, mixed> $torrent
 * @param string|int $searchBoxId
 * @return string
 */
function get_hr_img(array $torrent, $searchBoxId)
{
    return \App\Support\TorrentAccess::hrImage($torrent, $searchBoxId);
}
/**
 * @param string $username
 * @return int
 */
function get_user_id_from_name($username){
    return \App\Support\LegacyAuth::userIdFromName((string) $username, legacy_auth_context());
}
/**
 * @param string|int $id
 * @param string $in
 * @return bool
 */
function is_forum_moderator($id, $in = 'post'){
	return \App\Support\Forum::isModerator($id, (string) $in);
}
/**
 * @return int
 */
function get_guest_lang_id(){
	global $CURLANGDIR;
	return \App\Support\Locale::guestId((string) $CURLANGDIR);
}
/**
 * @param string $name
 * @param string|int $forumid
 * @param int $limit
 * @return void
 */
function set_forum_moderators($name, $forumid, $limit=3){
	\App\Support\Forum::setModerators((string) $name, $forumid, (int) $limit);
}
/**
 * @param string|int $id
 * @return string
 */
function get_plain_username($id){
	return \App\Support\UserDisplay::plainUsername((int) $id);
}
/**
 * @param string|int $mode
 * @param string $item
 * @return mixed
 */
function get_searchbox_value($mode = 1, $item = 'showsubcat'){
	global $Cache;
	return \App\Support\SearchBox::value($Cache, $mode, (string) $item);
}
/**
 * @param string|int $userid
 * @param bool $html
 * @return string|int|float
 */
function get_ratio($userid, $html = true){
	return \App\Support\Ratio::forUserId($userid, (bool) $html);
}
/**
 * @param int|float $num
 * @param bool $es
 * @return string
 */
function add_s($num, $es = false)
{
	global $lang_functions;
	return \App\Support\Strings::pluralize((float)$num, '', $es ? ($lang_functions['text_es'] ?? '') : ($lang_functions['text_s'] ?? ''));
}
/**
 * @param int|float $num
 * @return string
 */
function is_or_are($num)
{
	global $lang_functions;
	return \App\Support\Strings::pluralize((float)$num, $lang_functions['text_is'] ?? '', $lang_functions['text_are'] ?? '');
}
/**
 * @return float
 */
function getmicrotime(){
	return \App\Support\Time::microtimeFloat();
}
/**
 * @param string|int|null $class
 * @return string
 */
function get_user_class_image($class){
	return \App\Support\UserClass::imagePath($class ?? null);
}
/**
 * @param string $where
 * @return bool
 */
function user_can_upload($where = "torrents"){
	return \App\Support\LegacyResponse::canUpload($where);
}
/**
 * @param string $name
 * @param string $selname
 * @param string $listname
 * @param int $selectedid
 * @param int $mode
 * @return string
 */
function torrent_selection($name,$selname,$listname,$selectedid = 0, $mode = 0)
{
	return \App\Support\Html::torrentSelection((string) $name, (string) $selname, (string) $listname, (int) $selectedid, (int) $mode);
}
/**
 * @param int $color
 * @return string|false
 */
function get_hl_color($color=0)
{
	return \App\Support\Palette::forumHighlight((int) $color);
}
/**
 * @param string|int $forumid
 * @param bool $plaintext
 * @return string
 */
function get_forum_moderators($forumid, $plaintext = true)
{
	global $Cache;
	return \App\Support\Forum::moderators($Cache, $forumid, (bool) $plaintext);
}
/**
 * @param int $page
 * @param int $pages
 * @return string
 */
function key_shortcut($page=1,$pages=1)
{
	return \App\Support\Html::keyShortcutScript((int) $page, (int) $pages);
}
/**
 * @param int $selected
 * @param int $hide
 * @return string
 */
function promotion_selection($selected = 0, $hide = 0)
{
	return \App\Support\Html::promotionSelection((int) $selected, (int) $hide);
}
/**
 * @param string|int $postid
 * @return array<array-key, mixed>|null
 */
function get_post_row($postid)
{
	global $Cache;
	return \App\Support\Forum::postRow($Cache, $postid);
}
/**
 * @param string|int $id
 * @return array<array-key, mixed>|null
 */
function get_country_row($id)
{
	global $Cache;
	return \App\Support\Country::row($Cache, $id);
}

/**
 * @param string $filename
 * @return bool
 */
function valid_file_name($filename)
{
	return \App\Support\Validators::isFileName($filename);
}
/**
 * @param string $filename
 * @return bool
 */
function valid_class_name($filename)
{
	return \App\Support\Validators::isClassName($filename);
}
/**
 * @param string $url
 * @return string
 */
function return_avatar_image($url)
{
	global $CURLANGDIR;
	return \App\Support\UserDisplay::avatarImage((string) $url, (string) $CURLANGDIR);
}
/**
 * @param string|int $categoryid
 * @param string $link
 * @return string
 */
function return_category_image($categoryid, $link="")
{
	return \App\Support\Category::imageTag((int) $categoryid, (string) $link);
}

/******************************************** bellow functioons avaliable since v1.6 ***********************************************************/
/**
 * @param string|int $tags
 * @param string $type
 * @return string
 */
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
/**
 * @param string $prefix
 * @param array<array-key, mixed> $nameAndValue
 * @param string $autoload
 * @return void
 */
function saveSetting(string $prefix, array $nameAndValue, string $autoload = 'yes'): void
{
    \App\Support\Settings::saveBatch($prefix, $nameAndValue, $autoload);
}
/**
 * @param string $dir
 * @return string
 */
function getFullDirectory($dir)
{
	return \App\Support\Path::resolve($dir, ROOT_PATH);
}
/**
 * @return void
 */
function checkGuestVisit()
{
    \App\Support\SiteAccess::checkGuestVisit();
}
/**
 * @param string $view
 * @param array<array-key, mixed> $data
 * @param bool $return
 * @return mixed
 */
function render($view, $data = [], $return = false)
{
    return \App\Support\View::render((string) $view, (array) $data, (bool) $return, ROOT_PATH);
}
/**
 * @return bool
 */
function canDoLogin()
{
    return \App\Support\SiteAccess::canDoLogin();
}
/**
 * @param array<array-key, mixed> $header
 * @param array<array-key, mixed> $rows
 * @param array<array-key, mixed> $options
 * @return string
 */
function build_table(array $header, array $rows, array $options = [])
{
	return \App\Support\Html::buildTable($header, $rows, $options);
}

/**
 * 返回链接中附件的key
 *
 * @param string $url
 * @return string
 */
function attachmentKey($url)
{
    return \App\Support\Attachment::keyFromUrl((string) $url);
}

/**
 * 根据key返回链接
 *
 * @param string $location
 * @param int|null $width
 * @param int|null $height
 * @param array<array-key, mixed> $options
 * @return string
 */
function attachmentUrl($location, $width = null, $height = null, $options = [])
{
    return \App\Support\Attachment::publicUrl((string) $location);
}

/**
 * @param string $text
 * @return string
 */
function strip_all_tags($text)
{
	return \App\Support\Strings::stripAllTags((string) $text);
}
/**
 * @param string $description
 * @return array<array-key, mixed>
 */
function format_description($description)
{
	return \App\Support\Description::parse((string) $description);
}
/**
 * @param array<array-key, mixed> $descriptionArr
 * @param bool $first
 * @param bool $useDefault
 * @return array<array-key, mixed>|string
 */
function get_image_from_description(array $descriptionArr, $first = false, $useDefault = true)
{
	return \App\Support\Description::imageFromDescription($descriptionArr, (bool) $first, (bool) $useDefault);
}
/**
 * @param string $url
 * @param int|null $with
 * @param int|null $height
 * @param string $fit
 * @return string
 */
function resize_image($url, $with = null, $height = null, $fit = "cover")
{
    return \App\Support\Image::weserv((string) $url, $with !== null ? (int) $with : null, $height !== null ? (int) $height : null, (string) $fit);
}
/**
 * @param int|float $uploaded
 * @param int|float $downloaded
 * @return string|float
 */
function get_share_ratio($uploaded, $downloaded)
{
    return \App\Support\Ratio::share((float)$uploaded, (float)$downloaded);
}
/**
 * @param string $class
 * @param string $cells
 * @return string
 */
function EchoRow($class = '', ...$cells){
	return \App\Support\Html::tableRow((string) $class, ...$cells);
}
/**
 * @return array<array-key, mixed>
 */
function list_require_search_box_id()
{
    return \App\Support\SearchBox::requiredIds();
}
/**
 * @param array<array-key, mixed>|string|int $torrent
 * @param string|int $uid
 * @return bool
 */
function can_access_torrent($torrent, $uid)
{
    return \App\Support\TorrentAccess::canAccess($torrent, $uid);
}

/**
 * @param string $ip
 * @return array<string, mixed>|bool
 */
function get_ip_location_from_geoip($ip): bool|array
{
    return \App\Support\Network::geoIpInfo((string) $ip);
}
/**
 * @param string $url
 * @param string $text
 * @param string $bgcolor
 * @return void
 */
function msgalert($url, $text, $bgcolor = "red")
{
	echo \App\Support\Html::messageAlert($url, $text, $bgcolor);
}
/**
 * @param Illuminate\Support\Collection<array-key, mixed> $medals
 * @param string|int $maxHeight
 * @param bool $withActions
 * @return string
 */
function build_medal_image(\Illuminate\Support\Collection $medals, $maxHeight = 200, $withActions = false): string
{
    return \App\Support\Medal::buildImages($medals, $maxHeight, (bool) $withActions);
}
/**
 * @param string|int $torrentId
 * @param array<array-key, mixed> $tagIdArr
 * @param bool $sync
 * @return void
 */
function insert_torrent_tags($torrentId, $tagIdArr, $sync = false)
{
    \App\Support\TorrentTags::insert($torrentId, $tagIdArr, (bool) $sync);
}
/**
 * @param int $num
 * @return string|null
 */
function get_smile($num)
{
	return \App\Support\Smilies::pathFor((int) $num);
}
/**
 * @param string $class
 * @return string
 */
function get_filament_class_alias($class): string
{
    return \App\Support\Strings::filamentAlias((string) $class);
}

/**
 * Calculate user seed bonus per hour
 *
 * @param int|string $uid
 * @param array<int>|null $torrentIdArr
 * @return array<string, mixed>
 * @throws \Nexus\Database\DatabaseException
 */
function calculate_seed_bonus($uid, $torrentIdArr = null): array
{
    return \App\Support\Bonus::calculateForUser($uid, $torrentIdArr);
}

/**
 * @param string|int $uid
 * @return string|int|float
 */
function calculate_harem_addition($uid)
{
    return \App\Support\Bonus::haremAddition($uid);
}

/**
 * @param string|int $mode
 * @param string|int $checkboxValue
 * @param string $categoryHrefPrefix
 * @param string $taxonomyHrefPrefix
 * @param string|int $taxonomyNameLength
 * @param string $checkedValues
 * @param array<array-key, mixed> $options
 * @return string
 */
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
/**
 * @param string $name
 * @param string $value
 * @param string $label
 * @param array<array-key, mixed> $options
 * @return string
 */
function datetimepicker_input($name, $value = '', $label = '', array $options = [])
{
    return \App\Support\Form::datetimepickerInput($name, (string) $value, $label, $options);
}
/**
 * @param array<array-key, mixed> $user
 * @param array<array-key, mixed> $bonusResult
 * @param array<array-key, mixed> $options
 * @return array<array-key, mixed>
 */
function build_bonus_table(array $user, array $bonusResult = [], array $options = [])
{
    return \App\Support\Bonus::buildBonusTableForUser($user, $bonusResult, $options);
}

/**
 * @param string|int $searchArea
 * @param array<array-key, mixed> $options
 * @return string
 */
function build_search_area($searchArea, array $options = [])
{
    return \App\Support\SearchBox::areaSelect($searchArea, $options);
}
/**
 * @param App\Models\Torrent|null $torrent
 * @param bool $withTags
 * @param int $length
 * @return Illuminate\Support\HtmlString
 */
function torrent_name_for_admin(\App\Models\Torrent|null $torrent, $withTags = false, $length = 40)
{
    return \App\Support\TorrentAccess::adminName($torrent, (bool) $withTags, (int) $length);
}
/**
 * @param int $id
 * @return Illuminate\Support\HtmlString
 */
function username_for_admin(int $id)
{
    return \App\Support\UserDisplay::adminUsername($id);
}
/**
 * @param string|int $uid
 * @param array<array-key, mixed>|string|int $post
 * @return bool
 */
function can_view_post($uid, $post)
{
    return \App\Support\Forum::canViewPost($uid, $post);
}
/**
 * @param string $text
 * @return string
 */
function hide_text($text) {
	return \App\Support\Strings::hidden((string)$text);
}
/**
 * @param string $filename
 * @param string $disposition
 * @return string
 */
function make_content_disposition(string $filename, string $disposition = 'attachment'): string {
	return \App\Support\Http::contentDisposition($filename, $disposition);
}
/**
 * @param string $text
 * @return string
 */
function bbcode_attach_to_img(string $text) {
    return \App\Support\Attachment::bbcodeToImg($text);
}

?>
