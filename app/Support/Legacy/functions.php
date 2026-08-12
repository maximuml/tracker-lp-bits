<?php

use App\Models\SearchBox;
use App\Models\TorrentExtra;
use App\Support\SupportContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
/**
 * @param bool $transToLocale
 * @return string
 */
function get_langfolder_cookie($transToLocale = false)
{
	return \App\Support\Locale::folderFromCookie(SupportContext::getCookieValue('c_lang_folder', ''), (bool) $transToLocale);
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
        $scriptFile = SupportContext::getServerValue('SCRIPT_FILENAME', '');
        $script = basename($scriptFile);
        if (str_contains($script, '.')) {
            $script = strstr($script, '.', true);
        }
    }

    return new \App\Support\LegacyAuthContext(
        user: SupportContext::getUser(),
        lang: SupportContext::getLangFunctions(),
        cache: SupportContext::getCache(),
        ip: \function_exists('getip') ? \getip() : \App\Support\Network::clientIp(),
        requestUri: SupportContext::getServerValue('REQUEST_URI'),
        requestBody: SupportContext::allPost(),
        queryParams: SupportContext::allQuery(),
        request: array_merge(SupportContext::allPost(), SupportContext::allQuery()),
        cookies: SupportContext::allCookie(),
        maxLoginAttempts: (int) SupportContext::getGlobal('maxloginattempts', 0),
        captchaEnabled: SupportContext::getGlobal('iv', '') === 'yes',
        registration: [
            'invitesystem' => (string) SupportContext::getGlobal('invitesystem', ''),
            'registration' => (string) SupportContext::getGlobal('registration', ''),
            'maxusers' => (int) SupportContext::getGlobal('maxusers', 0),
            'maxip' => (int) SupportContext::getGlobal('maxip', 0),
        ],
        langFolder: SupportContext::getCookieValue('c_lang_folder'),
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
    $userUpdateSet = &\App\Support\SupportContext::getUserUpdateSet();

    $script = '';
    if (\function_exists('nexus')) {
        $script = \nexus()->getScript();
    } else {
        $scriptFile = SupportContext::getServerValue('SCRIPT_FILENAME', '');
        $script = basename($scriptFile);
        if (str_contains($script, '.')) {
            $script = strstr($script, '.', true);
        }
    }

    return new \App\Support\PageLayoutContext(
        user: SupportContext::getUser(),
        lang: SupportContext::getLangFunctions(),
        cache: SupportContext::getCache(),
        defaultStylesheet: (int) SupportContext::getGlobal('defcss', 0),
        langDir: (string) SupportContext::getGlobal('CURLANGDIR', ''),
        siteName: (string) SupportContext::getGlobal('SITENAME', ''),
        slogan: (string) SupportContext::getGlobal('SLOGAN', ''),
        logoMain: (string) SupportContext::getGlobal('logo_main', ''),
        baseUrl: (string) SupportContext::getGlobal('BASEURL', ''),
        siteOnline: (string) SupportContext::getGlobal('SITE_ONLINE', 'yes'),
        enableDonation: (string) SupportContext::getGlobal('enabledonation', 'no'),
        titleKeywordsTweak: (string) SupportContext::getGlobal('titlekeywords_tweak', ''),
        metaKeywordsTweak: (string) SupportContext::getGlobal('metakeywords_tweak', ''),
        metaDescriptionTweak: (string) SupportContext::getGlobal('metadescription_tweak', ''),
        cssDateTweak: (string) SupportContext::getGlobal('cssdate_tweak', ''),
        deleteNotTransferTwoAccount: (int) SupportContext::getGlobal('deletenotransfertwo_account', 0),
        neverDeleteAccount: (int) SupportContext::getGlobal('neverdelete_account', 0),
        iniUploadMain: (int) SupportContext::getGlobal('iniupload_main', 0),
        dateFounded: (string) SupportContext::getGlobal('datefounded', ''),
        icpLicenseMain: (string) SupportContext::getGlobal('icplicense_main', ''),
        addKeyShortcut: (string) SupportContext::getGlobal('add_key_shortcut', ''),
        queryName: (array) SupportContext::getGlobal('query_name', []),
        enableSqlDebugTweak: (string) SupportContext::getGlobal('enablesqldebug_tweak', 'no'),
        sqlDebugTweak: (int) SupportContext::getGlobal('sqldebug_tweak', 0),
        analyticsCodeTweak: (string) SupportContext::getGlobal('analyticscode_tweak', ''),
        requestSearch: is_scalar(SupportContext::getQuery('search', '')) ? (string) SupportContext::getQuery('search', '') : '',
        requestSearchArea: is_scalar(SupportContext::getQuery('search_area', '')) ? (string) SupportContext::getQuery('search_area', '') : '',
        scriptFileName: SupportContext::getServerValue('SCRIPT_FILENAME', ''),
        script: $script,
        enableOffer: (string) SupportContext::getGlobal('enableoffer', ''),
        customMenu: (string) \apply_filter('nexus_menu') ?: null,
        maxdlSystem: (string) SupportContext::getGlobal('maxdlsystem', ''),
        whereTweak: (string) SupportContext::getGlobal('where_tweak', ''),
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
	\App\Support\Html::stdMessage((string) $heading, (string) $text, (bool) $htmlstrip);
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
    trigger_deprecation('maximuml/tracker-lp-bits', '2.0', 'The %s() function is deprecated, use %s::%s() instead.', __FUNCTION__, '\App\Support\HtmlRenderer', 'formatUrl');
    return \App\Support\HtmlRenderer::formatUrl($url, $newWindow, $text, $linkClass);
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
    trigger_deprecation('maximuml/tracker-lp-bits', '2.0', 'The %s() function is deprecated, use %s::%s() instead.', __FUNCTION__, '\App\Support\HtmlRenderer', 'formatImg');
    return \App\Support\HtmlRenderer::formatImg($src, $enableImageResizer, $image_max_width, $image_max_height, $imgId);
}

/**
 * @param string $src
 * @param string|int $width
 * @param string|int $height
 * @return string
 */
function formatFlash($src, $width, $height) {
    trigger_deprecation('maximuml/tracker-lp-bits', '2.0', 'The %s() function is deprecated, use %s::%s() instead.', __FUNCTION__, '\App\Support\HtmlRenderer', 'formatFlash');
    return \App\Support\HtmlRenderer::formatFlash($src, $width, $height);
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
    trigger_deprecation('maximuml/tracker-lp-bits', '2.0', 'The %s() function is deprecated, use %s::%s() instead.', __FUNCTION__, '\App\Support\HtmlRenderer', 'formatYoutube');
    return \App\Support\HtmlRenderer::formatYoutube($src, $width, $height);
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
    trigger_deprecation('maximuml/tracker-lp-bits', '2.0', 'The %s() function is deprecated, use %s::%s() instead.', __FUNCTION__, '\App\Support\HtmlRenderer', 'formatAudio');
    return \App\Support\HtmlRenderer::formatAudio($src);
}

/**
 * @param string $content
 * @param string $title
 * @param bool $defaultCollapsed
 * @return string
 */
function formatSpoiler($content, $title = '', $defaultCollapsed = true): string
{
    trigger_deprecation('maximuml/tracker-lp-bits', '2.0', 'The %s() function is deprecated, use %s::%s() instead.', __FUNCTION__, '\App\Support\HtmlRenderer', 'formatSpoiler');
    return \App\Support\HtmlRenderer::formatSpoiler($content, $title, $defaultCollapsed);
}

/**
 * @param string $content
 * @return string
 */
function formatHidden($content): string
{
    trigger_deprecation('maximuml/tracker-lp-bits', '2.0', 'The %s() function is deprecated, use %s::%s() instead.', __FUNCTION__, '\App\Support\HtmlRenderer', 'formatHidden');
    return \App\Support\HtmlRenderer::formatHidden($content);
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
    trigger_deprecation('maximuml/tracker-lp-bits', '2.0', 'The %s() function is deprecated, use %s::%s() instead.', __FUNCTION__, '\App\Support\Format', 'formatUrls');
    return \App\Support\Format::formatUrls($text, $newWindow);
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
    trigger_deprecation('maximuml/tracker-lp-bits', '2.0', 'The %s() function is deprecated, use %s::%s() instead.', __FUNCTION__, '\App\Support\Format', 'formatComment');
    return \App\Support\Format::formatComment($text, $strip_html, $xssclean, $newtab, $imageresizer, $image_max_width, $enableimage, $enableflash, $imagenum, $image_max_height);
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
    trigger_deprecation('maximuml/tracker-lp-bits', '2.0', 'The %s() function is deprecated, use %s::%s() instead.', __FUNCTION__, '\App\Support\Format', 'highlight');
    return \App\Support\Format::highlight($search, $subject, $hlstart, $hlend);
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
    trigger_deprecation('maximuml/tracker-lp-bits', '2.0', 'The %s() function is deprecated, use %s::%s() instead.', __FUNCTION__, '\App\Support\User', 'getUserClassName');
    return \App\Support\User::getUserClassName($class, $compact, $b_colored, $I18N, $options);
}
/**
 * @param mixed $class
 * @return bool
 */
function is_valid_user_class($class)
{
    trigger_deprecation('maximuml/tracker-lp-bits', '2.0', 'The %s() function is deprecated, use %s::%s() instead.', __FUNCTION__, '\App\Support\User', 'isValidUserClass');
    return \App\Support\User::isValidUserClass($class);
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
	\App\Support\Frame::mainFrameOpen((string) $caption, (bool) $center, $width);
}
/**
 * @return void
 */
function end_main_frame() {
	\App\Support\Frame::mainFrameClose();
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
    trigger_deprecation('maximuml/tracker-lp-bits', '2.0', 'The %s() function is deprecated, use %s::%s() instead.', __FUNCTION__, '\App\Support\Html', 'beginFrame');
    \App\Support\Html::beginFrame($caption, $center, $padding, $width, $caption_center);
}
/**
 * @return void
 */
function end_frame() {
    trigger_deprecation('maximuml/tracker-lp-bits', '2.0', 'The %s() function is deprecated, use %s::%s() instead.', __FUNCTION__, '\App\Support\Html', 'endFrame');
    \App\Support\Html::endFrame();
}
/**
 * @param bool $fullwidth
 * @param int $padding
 * @return void
 */
function begin_table($fullwidth = false, $padding = 5) {
    trigger_deprecation('maximuml/tracker-lp-bits', '2.0', 'The %s() function is deprecated, use %s::%s() instead.', __FUNCTION__, '\App\Support\Html', 'beginTable');
    \App\Support\Html::beginTable($fullwidth, $padding);
}
/**
 * @return void
 */
function end_table() {
    trigger_deprecation('maximuml/tracker-lp-bits', '2.0', 'The %s() function is deprecated, use %s::%s() instead.', __FUNCTION__, '\App\Support\Html', 'endTable');
    \App\Support\Html::endTable();
}

//-------- Inserts a smilies frame
//         (move to globals)
/**
 * @return void
 */
function insert_smilies_frame()
{
	$lang_functions = \App\Support\SupportContext::getLangFunctions();
	echo \App\Support\Smilies::framedTable($lang_functions['text_smilies'], $lang_functions['col_type_something'], $lang_functions['col_to_make_a']);
}
/**
 * @param mixed $ratio
 * @return string
 */
function get_ratio_color($ratio)
{
    trigger_deprecation('maximuml/tracker-lp-bits', '2.0', 'The %s() function is deprecated, use %s::%s() instead.', __FUNCTION__, '\App\Support\Format', 'getRatioColor');
    return \App\Support\Format::getRatioColor($ratio);
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
    trigger_deprecation('maximuml/tracker-lp-bits', '2.0', 'The %s() function is deprecated, use %s::%s() instead.', __FUNCTION__, '\App\Support\Log', 'write');
    \App\Support\Log::write($text, $security, get_user_id());
}

/**
 * @param int $ts
 * @param bool $shortunit
 * @return string
 */
function get_elapsed_time($ts,$shortunit = false)
{
    trigger_deprecation('maximuml/tracker-lp-bits', '2.0', 'The %s() function is deprecated, use %s::%s() instead.', __FUNCTION__, '\App\Support\Format', 'getElapsedTime');
    return \App\Support\Format::getElapsedTime($ts, $shortunit);
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
	\App\Support\Frame::composeBeginVoid((string) $title, (string) $type, (string) $body, (bool) $hassubject, (string) $subject, (int) $maxsubjectlength);
}
/**
 * @return void
 */
function end_compose()
{
	\App\Support\Frame::composeEndVoid();
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
    trigger_deprecation('maximuml/tracker-lp-bits', '2.0', 'The %s() function is deprecated, use %s::%s() instead.', __FUNCTION__, '\App\Support\User', 'currentUserCheck');
    \App\Support\User::currentUserCheck();
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
    trigger_deprecation('maximuml/tracker-lp-bits', '2.0', 'The %s() function is deprecated, use %s::%s() instead.', __FUNCTION__, '\App\Support\CacheHelper', 'setCacheTimestamp');
    \App\Support\CacheHelper::setCacheTimestamp($id, $field);
}
/**
 * @param string|int $id
 * @param string $field
 * @return void
 */
function reset_cachetimestamp($id, $field = "cache_stamp")
{
    trigger_deprecation('maximuml/tracker-lp-bits', '2.0', 'The %s() function is deprecated, use %s::%s() instead.', __FUNCTION__, '\App\Support\CacheHelper', 'resetCacheTimestamp');
    \App\Support\CacheHelper::resetCacheTimestamp($id, $field);
}
/**
 * @param string $file
 * @param bool $endpage
 * @param int $cachetime
 * @return bool
 */
function cache_check ($file = 'cachefile',$endpage = true, $cachetime = 600) {
    trigger_deprecation('maximuml/tracker-lp-bits', '2.0', 'The %s() function is deprecated, use %s::%s() instead.', __FUNCTION__, '\App\Support\CacheHelper', 'cacheCheck');
    return \App\Support\CacheHelper::cacheCheck($file, $endpage, $cachetime);
}
/**
 * @param string $file
 * @return void
 */
function cache_save  ($file = 'cachefile') {
    trigger_deprecation('maximuml/tracker-lp-bits', '2.0', 'The %s() function is deprecated, use %s::%s() instead.', __FUNCTION__, '\App\Support\CacheHelper', 'cacheSave');
    \App\Support\CacheHelper::cacheSave($file);
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
 * @return string
 */
function remaining($type = 'login')
{
    return \App\Support\LegacyAuth::remainingAttemptsFromContext((string) $type);
}
/**
 * @param string $type
 * @param bool $maxuserscheck
 * @param bool $ipcheck
 * @return bool
 */
function registration_check($type = "invitesystem", $maxuserscheck = true, $ipcheck = true) {
    return \App\Support\LegacyAuth::registrationCheckFromContext((string) $type, (bool) $maxuserscheck, (bool) $ipcheck);
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
    trigger_deprecation('maximuml/tracker-lp-bits', '2.0', 'The %s() function is deprecated, use %s::%s() instead.', __FUNCTION__, '\App\Support\Captcha', 'checkCode');
    return \App\Support\Captcha::checkCode($imagehash, $imagestring, $where, $maxattemptlog, $head, \legacy_auth_context());
}

/**
 * @return void
 */
function show_image_code () {
    trigger_deprecation('maximuml/tracker-lp-bits', '2.0', 'The %s() function is deprecated, use %s::%s() instead.', __FUNCTION__, '\App\Support\Captcha', 'showImageCode');
    \App\Support\Captcha::showImageCode();
}
/**
 * @param string $ip
 * @return array<array-key, mixed>
 */
function get_ip_location($ip)
{
	return \App\Support\Network::ipLocationWithContext((string) $ip);
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
	return \App\Support\Slots::displayWithContext();
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
    return \App\Support\LegacyAuth::loginFromContext();
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
    trigger_deprecation('maximuml/tracker-lp-bits', '2.0', 'The %s() function is deprecated, use %s::%s() instead.', __FUNCTION__, '\App\Support\Format', 'mksize');
    return \App\Support\Format::mksize($bytes);
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
	return \App\Support\Format::prettyTimeWithLocale((float) $s);
}
/**
 * @param array<array-key, mixed>|string $vars
 * @return int
 */
function mkglobal($vars) {
	return \App\Support\Input::globalize($vars, SupportContext::allQuery(), SupportContext::allPost());
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
    trigger_deprecation('maximuml/tracker-lp-bits', '2.0', 'The %s() function is deprecated, use %s::%s() instead.', __FUNCTION__, '\App\Support\Html', 'tr');
    return \App\Support\Html::tr($x, $y, $noesc, $relation, $return);
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
	return \App\Support\Locale::folderForIdWithContext($langid);
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
    $customMenu = (string) \apply_filter('nexus_menu');

    $result = \App\Support\Menu::render(
        \function_exists('nexus') ? \nexus()->getScript() : '',
        SupportContext::getLangFunctions(),
        (string) SupportContext::getGlobal('enableoffer', ''),
        $customMenu !== '' ? $customMenu : null,
        SupportContext::getUser(),
        SupportContext::getCache(),
        (string) SupportContext::getGlobal('CURLANGDIR', ''),
    );

    $CURUSER = SupportContext::getUser();
    if ($CURUSER && SupportContext::getGlobal('where_tweak', '') === 'yes') {
        SupportContext::addUserUpdate('page', $result['selected']);
    }

    echo $result['html'];
}
/**
 * @return array<array-key, mixed>|null
 */
function get_css_row() {
	return \App\Support\Style::cssRowWithContext();
}
/**
 * @param string $file
 * @return string
 */
function get_css_uri($file = "")
{
	return \App\Support\Style::cssUriWithContext((string) $file);
}
/**
 * @return string
 */
function get_font_css_uri(){
	return \App\Support\Style::fontCssUriWithContext();
}
/**
 * @return string
 */
function get_style_addicode()
{
	return \App\Support\Style::addiCodeWithContext();
}
/**
 * @param string|int $cat
 * @return string
 */
function get_cat_folder($cat = 101)
{
	return \App\Support\Path::categoryFolderForIdWithContext($cat);
}
/**
 * @return string
 */
function get_style_highlight()
{
	return \App\Support\Style::highlightColorWithContext();
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
    trigger_deprecation('maximuml/tracker-lp-bits', '2.0', 'The %s() function is deprecated, use %s::%s() instead.', __FUNCTION__, '\App\Support\Html', 'stdhead');
    \App\Support\Html::stdhead($title, $msgalert, $script, $place);
}

/**
 * @return void
 */
function stdfoot()
{
    trigger_deprecation('maximuml/tracker-lp-bits', '2.0', 'The %s() function is deprecated, use %s::%s() instead.', __FUNCTION__, '\App\Support\Html', 'stdfoot');
    \App\Support\Html::stdfoot();
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
	$savedirectory_attachment = (string) \App\Support\SupportContext::getGlobal('savedirectory_attachment', '');
	$httpdirectory_attachment = (string) \App\Support\SupportContext::getGlobal('httpdirectory_attachment', '');
	$Cache = \App\Support\SupportContext::getCache();
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
    \App\Support\LegacyAuth::requireLoginFromContext((bool) $mainpage);
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
	return \App\Support\Category::listByModeWithContext($catmode);
}
/**
 * @param string $table
 * @param int $mode
 * @return array<array-key, mixed>
 */
function searchbox_item_list(string $table, int $mode){
	return \App\Support\SearchBox::itemListWithContext($table, $mode);
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
	$Cache = \App\Support\SupportContext::getCache();
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
	$Cache = \App\Support\SupportContext::getCache();
	$lang_functions = \App\Support\SupportContext::getLangFunctions();
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
    \App\Support\LegacyAuth::parkedFromContext();
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
	echo \App\Support\Html::tooltipContainer($id_content_arr, (int) $width);
}

/**
 * @param string $formname
 * @param string $taname
 * @param string $submit
 * @return void
 */
function quickreply($formname, $taname,$submit){
	\App\Support\Html::quickReplyVoid((string) $formname, (string) $taname, (string) $submit);
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
    $lang_functions = \App\Support\SupportContext::getLangFunctions();
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
	$CURLANGDIR = (string) \App\Support\SupportContext::getGlobal('CURLANGDIR', '');
	return \App\Support\Forum::picFolder((string) $CURLANGDIR);
}
/**
 * @param string|int $typeid
 * @return array<array-key, mixed>|null
 */
function get_category_icon_row($typeid)
{
	return \App\Support\Category::iconRowWithContext($typeid);
}
/**
 * @param string|int|null $catid
 * @return array<array-key, mixed>|null
 */
function get_category_row($catid = NULL)
{
	return \App\Support\Category::rowWithContext($catid);
}
/**
 * @param array<array-key, mixed> $row
 * @return string
 */
function get_second_icon($row) //for CHDBits
{
	return \App\Support\Category::secondIconWithContext($row);
}
/**
 * @param int $promotion
 * @param string $posState
 * @param array<array-key, mixed> $torrent
 * @return string
 */
function get_torrent_bg_color($promotion = 1, $posState = "", array $torrent = [])
{
	$CURUSER = \App\Support\SupportContext::getUser() ?? [];
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
	$CURUSER = \App\Support\SupportContext::getUser() ?? [];
	$lang_functions = \App\Support\SupportContext::getLangFunctions();
	$expirehalfleech_torrent = (int) \App\Support\SupportContext::getGlobal('expirehalfleech_torrent', 0);
	$expirefree_torrent = (int) \App\Support\SupportContext::getGlobal('expirefree_torrent', 0);
	$expiretwoup_torrent = (int) \App\Support\SupportContext::getGlobal('expiretwoup_torrent', 0);
	$expiretwoupfree_torrent = (int) \App\Support\SupportContext::getGlobal('expiretwoupfree_torrent', 0);
	$expiretwouphalfleech_torrent = (int) \App\Support\SupportContext::getGlobal('expiretwouphalfleech_torrent', 0);
	$expirethirtypercentleech_torrent = (int) \App\Support\SupportContext::getGlobal('expirethirtypercentleech_torrent', 0);

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
	$CURUSER = \App\Support\SupportContext::getUser() ?? [];
	$lang_functions = \App\Support\SupportContext::getLangFunctions();
	$expirehalfleech_torrent = (int) \App\Support\SupportContext::getGlobal('expirehalfleech_torrent', 0);
	$expirefree_torrent = (int) \App\Support\SupportContext::getGlobal('expirefree_torrent', 0);
	$expiretwoup_torrent = (int) \App\Support\SupportContext::getGlobal('expiretwoup_torrent', 0);
	$expiretwoupfree_torrent = (int) \App\Support\SupportContext::getGlobal('expiretwoupfree_torrent', 0);
	$expiretwouphalfleech_torrent = (int) \App\Support\SupportContext::getGlobal('expiretwouphalfleech_torrent', 0);
	$expirethirtypercentleech_torrent = (int) \App\Support\SupportContext::getGlobal('expirethirtypercentleech_torrent', 0);

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
	return \App\Support\Locale::guestIdWithContext();
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
	$Cache = \App\Support\SupportContext::getCache();
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
	return \App\Support\Strings::addS((float)$num, (bool)$es);
}
/**
 * @param int|float $num
 * @return string
 */
function is_or_are($num)
{
	return \App\Support\Strings::isOrAre((float)$num);
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
	return \App\Support\Forum::moderatorsWithContext($forumid, (bool) $plaintext);
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
	return \App\Support\Forum::postRowWithContext($postid);
}
/**
 * @param string|int $id
 * @return array<array-key, mixed>|null
 */
function get_country_row($id)
{
	return \App\Support\Country::rowWithContext($id);
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
	return \App\Support\UserDisplay::avatarImageWithContext((string) $url);
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
    $lang_functions = \App\Support\SupportContext::getLangFunctions();
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
    trigger_deprecation('maximuml/tracker-lp-bits', '2.0', 'The %s() function is deprecated, use %s::%s() instead.', __FUNCTION__, '\App\Support\User', 'canAccessTorrent');
    return \App\Support\User::canAccessTorrent($torrent, $uid);
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
	\App\Support\Html::messageAlertVoid((string) $url, (string) $text, (string) $bgcolor);
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
    $Cache = \App\Support\SupportContext::getCache();
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


/**
 * @return int
 */
function get_global_sp_state()
{
    return \App\Support\Promotion::globalSpecialState();
}

// IP Validation
/**
 * @param string|null $ip
 * @return bool
 */
function validip($ip)
{
	return \App\Support\Network::isValid($ip);
}
/**
 * @param bool $real
 * @return string
 */
function getip($real = true) {
	return \App\Support\Network::clientIp((bool) $real);
}
/**
 * @param mixed $hash
 * @return string
 */
function hash_pad($hash) {
    return \App\Support\Strings::padHash($hash);
}
/**
 * @return array<array-key, mixed>
 */
function get_langfolder_list()
{
    return \App\Support\Locale::available();
}
/**
 * @param string $line
 * @param bool $exist
 * @return void
 */
function printLine($line, $exist = false)
{
    \App\Support\Debug::printLine($line, (bool) $exist);
}
/**
 * @param mixed $vars
 * @return void
 */
function nexus_dd($vars)
{
    \App\Support\Debug::dumpAndExit(...func_get_args());
}

/**
 * write log, use in both pure nexus and inside laravel
 *
 * @param string $log
 * @param string $level
 * @param bool $echo
 * @return void
 */
function do_log($log, $level = 'info', $echo = false)
{
    $user = null;
    $passkey = '';

    if (defined('IN_NEXUS') && IN_NEXUS) {
        $CURUSER = SupportContext::getUser();
        if (is_array($CURUSER) && ! empty($CURUSER)) {
            $user = $CURUSER;
            $passkey = (string) ($CURUSER['passkey'] ?? '');
        }
        if ($passkey === '') {
            $passkey = (string) (SupportContext::getRequestInput('passkey') ?? SupportContext::getRequestInput('authkey') ?? '');
        }
    } else {
        try {
            $authUser = Auth::user();
            if ($authUser instanceof \Illuminate\Database\Eloquent\Model) {
                $user = $authUser->getAttributes();
                $passkey = (string) ($authUser->getAttribute('passkey') ?? '');
            }
        } catch (\Throwable $exception) {
            $passkey = '!NO_AUTH';
        }
    }

    \App\Support\Logger::write((string) $log, $level, (bool) $echo, $user, $passkey);
}
/**
 * @param bool $withTimeZone
 * @return string
 */
function getDtMillis($withTimeZone = false): string {
    return \App\Support\Time::millis((bool) $withTimeZone);
}
/**
 * @param bool $withTimeZone
 * @return string
 */
function getDtMicro($withTimeZone = false): string {
    return \App\Support\Time::micro((bool) $withTimeZone);
}
/**
 * @param string $append
 * @return string
 */
function getLogFile($append = '')
{
    return \App\Support\Logger::filePath($append);
}
/**
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function nexus_config($key, $default = null)
{
    return \App\Support\Config::get($key, $default);
}


/**
 * get setting for given name and prefix
 *
 * @date 2021/1/11
 * @param string|null $name
 * @param mixed $default
 * @return mixed
 */
function get_setting(?string $name = null, mixed $default = null): mixed
{
    return \App\Support\Settings::get($name, $default);
}

/**
 * get setting autoload = yes without cache
 *
 * @param string|null $name
 * @param mixed $default
 * @return mixed
 */
function get_setting_from_db(?string $name = null, mixed $default = null): mixed
{
    return \App\Support\Settings::fromDb($name, $default);
}

/**
 * @param string|null $key
 * @param mixed $default
 * @return mixed
 */
function nexus_env($key = null, $default = null)
{
    return \App\Support\Env::get($key, $default);
}
/**
 * @param string $envFile
 * @return array<array-key, mixed>
 */
function readEnvFile($envFile)
{
    return \App\Support\Env::load($envFile);
}
/**
 * @param mixed $value
 * @return mixed
 */
function normalize_env($value)
{
    return \App\Support\Env::cast($value);
}
/**
 * @param array<array-key, mixed>|ArrayAccess<array-key, mixed> $array
 * @param string|int|null $key
 * @param mixed $default
 * @return mixed
 */
function arr_get($array, $key, $default = null)
{
    return \App\Support\Arrays::get($array, $key, $default);
}
/**
 * @param array<array-key, mixed> $array
 * @param string|int|null $key
 * @param mixed $value
 * @return array<array-key, mixed>
 */
function arr_set(&$array, $key, $value)
{
    return \App\Support\Arrays::set($array, $key, $value);
}
/**
 * @return bool
 */
function isHttps(): bool
{
    return \App\Support\Url::isSecure();
}

/**
 * @param bool $fromConfig
 * @return string
 */
function getSchemeAndHttpHost(bool $fromConfig = false): string
{
    return \App\Support\Url::schemeAndHost($fromConfig);
}
/**
 * @return string
 */
function getBaseUrl()
{
    return \App\Support\Url::baseUrl();
}

/**
 * @param mixed $data
 * @return string
 */
function nexus_json_encode($data)
{
    return \App\Support\Json::encode($data);
}
/**
 * @param  mixed  $data
 * @return array<array-key, mixed>
 */
function api(int $ret, string $msg, $data = [])
{
    return \App\Support\Api::call($ret, $msg, $data, SupportContext::allRequest());
}
/**
 * @param mixed $msgOrData
 * @param mixed $data
 * @return array<array-key, mixed>
 */
function success($msgOrData = 'OK', $data = [])
{
    if (func_num_args() === 1) {
        return \App\Support\Api::success('OK', $msgOrData, SupportContext::allRequest());
    }

    return \App\Support\Api::success((string) $msgOrData, $data, SupportContext::allRequest());
}
/**
 * @param mixed $msgOrData
 * @param mixed $data
 * @return array<array-key, mixed>
 */
function fail($msgOrData = 'ERROR', $data = [])
{
    if (func_num_args() === 1) {
        return \App\Support\Api::fail('ERROR', $msgOrData, SupportContext::allRequest());
    }

    return \App\Support\Api::fail((string) $msgOrData, $data, SupportContext::allRequest());
}
/**
 * @param string|bool $all
 * @param string $format
 * @return mixed
 */
function last_query($all = false, $format = 'json')
{
    return \App\Support\LegacyDb::lastQuery($all, $format);
}
/**
 * @param mixed $datetime
 * @param string $format
 * @return string|null
 */
function format_datetime($datetime, $format = 'Y-m-d H:i')
{
    return \App\Support\Time::formatDateTime($datetime, $format);
}
/**
 * @param string $key
 * @param array<array-key, mixed> $replace
 * @param string|null $locale
 * @return string
 */
function nexus_trans($key, $replace = [], $locale = null)
{
    return \App\Support\Locale::trans($key, $replace, $locale);
}
/**
 * @return bool
 */
function isRunningInConsole(): bool
{
    return \App\Support\Environment::isConsole();
}
/**
 * @return bool
 */
function isRunningOnWindows(): bool
{
    return \App\Support\Environment::isWindows();
}
/**
 * @param string $command
 * @return bool
 */
function command_exists($command): bool
{
    return \App\Support\Environment::commandExists($command);
}

/**
 * @param int|string $trackerUrlId
 * @param bool $combine
 * @return array<string, string>|string
 */
function get_tracker_schema_and_host($trackerUrlId, $combine = false): array|string
{
    return \App\Support\Tracker::schemaAndHost((int) $trackerUrlId, (bool) $combine);
}

/**
 * @param int|float $uped
 * @param int|float $downed
 * @return string
 */
function get_hr_ratio($uped, $downed)
{
    return \App\Support\Ratio::hr($uped, $downed);
}
/**
 * @param string $table
 * @param string $suffix
 * @return int
 */
function get_row_count($table, $suffix = "")
{
    return (int) \Nexus\Database\NexusDB::table($table)->count();
}
/**
 * @param string|int $id
 * @return array<array-key, mixed>|false
 */
function get_user_row($id)
{
    return \App\Support\UserDisplay::row($id);
}
/**
 * @return string|int
 */
function get_user_class()
{
    return \App\Support\UserDisplay::currentClass();
}
/**
 * @return int
 */
function get_user_id()
{
    return \App\Support\UserDisplay::currentId();
}
/**
 * @return string
 */
function get_user_passkey()
{
    return \App\Support\UserDisplay::currentPasskey();
}
/**
 * @return string
 */
function get_pure_username()
{
    return \App\Support\UserDisplay::currentUsername();
}
/**
 * @return mixed
 */
function nexus()
{
    return \Nexus\Nexus::instance();
}
/**
 * @return array<array-key, mixed>
 */
function site_info()
{
    return \App\Support\Site::info();
}
/**
 * @param string|null $ip
 * @return bool
 */
function isIPV4 ($ip)
{
    return \App\Support\Network::isIpv4($ip);
}
/**
 * @param string|null $ip
 * @return bool
 */
function isIPV6 ($ip)
{
    return \App\Support\Network::isIpv6($ip);
}
/**
 * @param string $name
 * @param callable $function
 * @param int $priority
 * @param int $argc
 * @return void
 */
function add_filter($name, $function, $priority = 10, $argc = 1)
{
    \App\Support\Hooks::addFilter($name, $function, (int) $priority, (int) $argc);
}
/**
 * @param string $name
 * @param mixed $args
 * @return mixed
 */
function apply_filter($name, ...$args)
{
    return \App\Support\Hooks::applyFilter($name, ...$args);
}
/**
 * @param string $name
 * @param callable $function
 * @param int $priority
 * @param int $argc
 * @return void
 */
function add_action($name, $function, $priority = 10, $argc = 1)
{
    \App\Support\Hooks::addAction($name, $function, (int) $priority, (int) $argc);
}
/**
 * @param string $name
 * @param mixed $args
 * @return mixed
 */
function do_action($name, ...$args)
{
    return \App\Support\Hooks::doAction($name, ...$args);
}
/**
 * @param string $ip
 * @param bool $exceptionWhenYes
 * @return bool
 */
function isIPSeedBoxFromASN($ip, $exceptionWhenYes = false): bool
{
    return \App\Support\Network::isSeedBoxFromASN($ip, (bool) $exceptionWhenYes);
}
/**
 * @param string $ip
 * @param int $uid
 * @return bool
 */
function isIPSeedBox($ip, $uid): bool
{
    return \App\Support\Network::isSeedBox($ip, (int) $uid);
}
/**
 * @param array<array-key, mixed> $torrent
 * @param array<array-key, mixed> $queries
 * @param array<array-key, mixed> $user
 * @param mixed $peer
 * @param mixed $snatch
 * @param mixed $promotionInfo
 * @return array<array-key, mixed>
 */
function getDataTraffic(array $torrent, array $queries, array $user, $peer, $snatch, $promotionInfo)
{
    return \App\Support\TorrentOps::dataTraffic($torrent, $queries, $user, $peer, $snatch, $promotionInfo);
}
/**
 * @param string|int $uid
 * @param string $passkey
 * @return void
 */
function clear_user_cache($uid, $passkey = '')
{
    \App\Support\Cache::clearUser($uid, $passkey);
}
/**
 * @return void
 */
function clear_setting_cache()
{
    \App\Support\Cache::clearSettings();
}

/**
 * @see functions.php::get_category_row(), genrelist()
 * @return void
 */
function clear_category_cache()
{
    \App\Support\Cache::clearCategory();
}

/**
 * @see functions.php::searchbox_item_list()
 * @param string $table
 * @return void
 */
function clear_taxonomy_cache($table)
{
    \App\Support\Cache::clearTaxonomy($table);
}
/**
 * @return void
 */
function clear_staff_message_cache()
{
    \App\Support\Cache::clearStaffMessage();
}

/**
 * @see functions.php::get_searchbox_value()
 * @return void
 */
function clear_search_box_cache()
{
    \App\Support\Cache::clearSearchBox();
}

/**
 * @see functions.php::get_category_icon_row()
 * @return void
 */
function clear_icon_cache()
{
    \App\Support\Cache::clearIcon();
}
/**
 * @param mixed $uid
 * @return void
 */
function clear_inbox_count_cache($uid)
{
    \App\Support\Cache::clearInboxCount($uid);
}
/**
 * @return void
 */
function clear_agent_allow_deny_cache()
{
    \App\Support\Cache::clearAgentAllowDeny();
}

/**
 * @see announce.php
 * @param string $infoHash
 * @return void
 */
function clear_torrent_cache($infoHash)
{
    \App\Support\Cache::clearTorrent($infoHash);
}
/**
 * @param string $permission
 * @param bool $fail
 * @param int $uid
 * @return bool
 */
function user_can($permission, $fail = false, $uid = 0): bool
{
    $enum = \App\Enums\Permission\PermissionEnum::tryFrom((string) $permission);
    if ($enum === null) {
        \do_log("Unknown permission string: $permission", 'error');
        if ($fail) {
            \App\Support\Permissions::assertHasPermission(false);
        }
        return false;
    }

    if ((int) $uid <= 0) {
        $uid = (int) \get_user_id();
    }
    if ($uid <= 0) {
        if ($fail) {
            \App\Support\Permissions::assertHasPermission(false);
        }
        return false;
    }

    $user = \App\Models\User::find($uid);
    if (!$user) {
        if ($fail) {
            \App\Support\Permissions::assertHasPermission(false);
        }
        return false;
    }

    $result = \App\Auth\Permission::can($enum, $user);
    if ($fail && !$result) {
        \App\Support\Permissions::assertHasPermission(false);
    }

    return $result;
}
/**
 * @param bool $permissionCheckResult
 * @return void
 */
function assert_has_permission(bool $permissionCheckResult): void
{
    \App\Support\Permissions::assertHasPermission($permissionCheckResult);
}


/**
 * @param array<array-key, mixed> $userInfo
 * @return bool
 */
function is_donor(array $userInfo): bool
{
    return \App\Support\UserDisplay::isDonor($userInfo);
}

/**
 * @deprecated
 * @param string $authkey
 * @return false|int|mixed|string|null
 * @throws \App\Exceptions\NexusException
 * @see download.php
 */
function get_passkey_by_authkey($authkey)
{
    return \App\Support\AuthCookie::passkeyByAuthkey($authkey);
}

/**
 * @param string $command
 * @param string $format
 * @param bool $artisan
 * @param bool $exception
 * @return string|array<int, string>
 */
function executeCommand($command, $format = 'string', $artisan = false, $exception = true): string|array
{
    return \App\Support\Environment::run($command, $format, (bool) $artisan, (bool) $exception);
}
/**
 * @param int $uid
 * @return mixed
 */
function has_role_work_seeding($uid)
{
    return \App\Support\Permissions::hasRoleWorkSeeding((int) $uid);
}
/**
 * @param string $src
 * @return string
 */
function filter_src($src)
{
    return \App\Support\Security::filterSrc($src);
}

//here must retrieve the real time info, no cache!!!
/**
 * @param string|int $torrentId
 * @param string|int $userId
 * @return array<array-key, mixed>|false
 */
function get_snatch_info($torrentId, $userId)
{
    return \App\Support\LegacyDb::snatchInfo($torrentId, $userId);
}

/**
 * 完整的 Laravel 事件, 在 php 端有监听者的需要触发. 同样会执行 publish_model_event()
 */
function fire_event(string $name, \Illuminate\Database\Eloquent\Model $model, ?\Illuminate\Database\Eloquent\Model $oldModel = null): void
{
    \App\Support\Events::fire($name, $model, $oldModel);
}

/**
 * 仅仅是往 redis 发布事件, php 端无监听者仅在其他平台有需要的触发这个即可, 较轻量
 */
function publish_model_event(string $event, int $id, string $json = ""): void
{
    \App\Support\Events::publishModel($event, $id, $json);
}
/**
 * @param string $str
 * @return string
 */
function convertNamespaceToSnake(string $str): string
{
    return \App\Support\Strings::namespaceToSnake($str);
}
/**
 * @param int $uid
 * @return string
 */
function get_user_locale(int $uid): string
{
    return \App\Support\Locale::userLocale($uid);
}
/**
 * @param string $msg
 * @return void
 */
function send_admin_success_notification(string $msg = ""): void {
    \App\Support\Admin::successNotification($msg);
}
/**
 * @param string $msg
 * @return void
 */
function send_admin_fail_notification(string $msg = ""): void {
    \App\Support\Admin::failNotification($msg);
}
/**
 * @param App\Enums\Permission\RoutePermissionEnum $permission
 * @return string
 */
function ability(\App\Enums\Permission\RoutePermissionEnum $permission): string {
    return \App\Support\Permissions::abilityLabel($permission);
}
/**
 * @param string $challenge
 * @return string
 */
function get_challenge_key(string $challenge): string {
    return \App\Support\Token::challengeKey($challenge);
}

/**
 * @param array<string, mixed> $cookie
 * @param bool $isArray
 * @return array<string, mixed>|\App\Models\User|null
 */
function get_user_from_cookie(array $cookie, $isArray = true): array|\App\Models\User|null {
    return \App\Support\AuthCookie::userFromCookie($cookie, (bool) $isArray);
}

/**
 * @param array<string, mixed> $cookie
 * @return array{user_id: int, token_json: string, signature: string}|null
 */
function get_user_id_and_signature_from_cookie(array $cookie): array|null
{
    return \App\Support\AuthCookie::decodeCookie($cookie);
}
/**
 * @param string $formId
 * @param string $passwordOriginalClass
 * @param string $passwordHashedName
 * @param bool $passwordRequired
 * @param string $passwordConfirmClass
 * @param string $usernameName
 * @return void
 */
function render_password_hash_js(string $formId, string $passwordOriginalClass, string $passwordHashedName, bool $passwordRequired, string $passwordConfirmClass = "password_confirmation", string $usernameName = "username"): void {
    \App\Support\Form::passwordHashJs($formId, $passwordOriginalClass, $passwordHashedName, $passwordRequired, $passwordConfirmClass, $usernameName);
}
/**
 * @param string $formId
 * @param string $usernameName
 * @param string $passwordOriginalClass
 * @return void
 */
function render_password_challenge_js(string $formId, string $usernameName, string $passwordOriginalClass): void {
    \App\Support\Form::passwordChallengeJs($formId, $usernameName, $passwordOriginalClass);
}

/**
 * @param string|array<array-key, mixed> $data
 * @return string|array<array-key, string>
 */
function nexus_escape($data): array|string
{
    return \App\Support\Strings::escapeHtml($data);
}
/**
 * @return bool
 */
function is_fpm_mode(): bool
{
    return \App\Support\Environment::isFpm();
}
