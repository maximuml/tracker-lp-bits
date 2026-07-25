<?php

function get_global_sp_state()
{
    return \App\Support\Promotion::globalSpecialState();
}

// IP Validation
function validip($ip)
{
	return \App\Support\Network::isValid($ip);
}

function getip($real = true) {
	return \App\Support\Network::clientIp((bool) $real);
}

function sql_query($query)
{
    return \App\Support\LegacyDb::query($query);
}

function sqlesc($value) {
    return \App\Support\LegacyDb::escape($value);
}

function hash_pad($hash) {
    return \App\Support\Strings::padHash($hash);
}

function hash_where($name, $hash) {
    return \App\Support\LegacyDb::hashWhere($name, $hash);
}

//no need any more...
/*
function strip_magic_quotes($arr)
{
	foreach ($arr as $k => $v)
	{
		if (is_array($v))
		{
			$arr[$k] = strip_magic_quotes($v);
		} else {
			$arr[$k] = stripslashes($v);
		}
	}
	return $arr;
}

if (function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc())
{
	if (!empty($_GET)) {
		$_GET = strip_magic_quotes($_GET);
	}
	if (!empty($_POST)) {
		$_POST = strip_magic_quotes($_POST);
	}
	if (!empty($_COOKIE)) {
		$_COOKIE = strip_magic_quotes($_COOKIE);
	}
}
*/

function get_langfolder_list()
{
    return \App\Support\Locale::available();
}

function printLine($line, $exist = false)
{
    \App\Support\Debug::printLine($line, (bool) $exist);
}

function nexus_dd($vars)
{
    \App\Support\Debug::dumpAndExit(...func_get_args());
}

/**
 * write log, use in both pure nexus and inside laravel
 *
 * @param $log
 * @param string $level
 */
function do_log($log, $level = 'info', $echo = false)
{
    \App\Support\Logger::write($log, $level, (bool) $echo);
}

function getDtMillis($withTimeZone = false): string {
    return \App\Support\Time::millis((bool) $withTimeZone);
}

function getDtMicro($withTimeZone = false): string {
    return \App\Support\Time::micro((bool) $withTimeZone);
}

function getLogFile($append = '')
{
    return \App\Support\Logger::filePath($append);
}

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


function nexus_env($key = null, $default = null)
{
    return \App\Support\Env::get($key, $default);
}

function readEnvFile($envFile)
{
    return \App\Support\Env::load($envFile);
}

function normalize_env($value)
{
    $normalized = \App\Support\Env::normalize($value);
    return match (strtolower($normalized)) {
        'true' => true,
        'false' => false,
        'null' => null,
        default => $normalized,
    };
}

function arr_get($array, $key, $default = null)
{
    return \App\Support\Arrays::get($array, $key, $default);
}

function arr_set(&$array, $key, $value)
{
    return \App\Support\Arrays::set($array, $key, $value);
}

function isHttps(): bool
{
    return \App\Support\Url::isSecure();
}


function getSchemeAndHttpHost(bool $fromConfig = false): string
{
    return \App\Support\Url::schemeAndHost($fromConfig);
}

function getBaseUrl()
{
    return \App\Support\Url::baseUrl();
}


function nexus_json_encode($data)
{
    return \App\Support\Json::encode($data);
}

function api(...$args)
{
    return \App\Support\Api::call(...$args);
}

function success(...$args)
{
    return \App\Support\Api::success(...$args);
}

function fail(...$args)
{
    return \App\Support\Api::fail(...$args);
}

function last_query($all = false, $format = 'json')
{
    return \App\Support\LegacyDb::lastQuery($all, $format);
}

function format_datetime($datetime, $format = 'Y-m-d H:i')
{
    return \App\Support\Time::formatDateTime($datetime, $format);
}

function nexus_trans($key, $replace = [], $locale = null)
{
    return \App\Support\Locale::trans($key, $replace, $locale);
}

function isRunningInConsole(): bool
{
    return \App\Support\Environment::isConsole();
}

function isRunningOnWindows(): bool
{
    return \App\Support\Environment::isWindows();
}

function command_exists($command): bool
{
    return !(trim(exec("command -v $command")) == '');
}

function get_tracker_schema_and_host($trackerUrlId, $combine = false): array|string
{
    return \App\Support\Tracker::schemaAndHost((int) $trackerUrlId, (bool) $combine);
}


function get_hr_ratio($uped, $downed)
{
    return \App\Support\Ratio::hr($uped, $downed);
}

function get_row_count($table, $suffix = "")
{
    return \App\Support\LegacyDb::count($table, $suffix);
}

function get_user_row($id)
{
    return \App\Support\UserDisplay::row($id);
}

function get_user_class()
{
    return \App\Support\UserDisplay::currentClass();
}

function get_user_id()
{
    return \App\Support\UserDisplay::currentId();
}

function get_user_passkey()
{
    return \App\Support\UserDisplay::currentPasskey();
}

function get_pure_username()
{
    return \App\Support\UserDisplay::currentUsername();
}

function nexus()
{
    return \Nexus\Nexus::instance();
}

function site_info()
{
    return \App\Support\Site::info();
}

function isIPV4 ($ip)
{
    return \App\Support\Network::isIpv4($ip);
}

function isIPV6 ($ip)
{
    return \App\Support\Network::isIpv6($ip);
}

function add_filter($name, $function, $priority = 10, $argc = 1)
{
    global $hook;
    $hook->addFilter($name, $function, $priority, $argc);
}

function apply_filter($name, ...$args)
{
    global $hook;
//    do_log("[APPLY_FILTER]: $name");
    return $hook->applyFilter(...func_get_args());
}

function add_action($name, $function, $priority = 10, $argc = 1)
{
    global $hook;
    $hook->addAction($name, $function, $priority, $argc);
}

function do_action($name, ...$args)
{
    global $hook;
//    do_log("[DO_ACTION]: $name");
    return $hook->doAction(...func_get_args());
}

function isIPSeedBoxFromASN($ip, $exceptionWhenYes = false): bool
{
    return \App\Support\Network::isSeedBoxFromASN($ip, (bool) $exceptionWhenYes);
}

function isIPSeedBox($ip, $uid): bool
{
    return \App\Support\Network::isSeedBox($ip, (int) $uid);
}

function getDataTraffic(array $torrent, array $queries, array $user, $peer, $snatch, $promotionInfo)
{
    return \App\Support\TorrentOps::dataTraffic($torrent, $queries, $user, $peer, $snatch, $promotionInfo);
}

function clear_user_cache($uid, $passkey = '')
{
    do_log("clear_user_cache, uid: $uid, passkey: $passkey");
    \Nexus\Database\NexusDB::cache_del("user_{$uid}_content");
    \Nexus\Database\NexusDB::cache_del("user_{$uid}_roles");
    \Nexus\Database\NexusDB::cache_del("announce_user_passkey_$uid");//announce.php
    \Nexus\Database\NexusDB::cache_del(\App\Models\Setting::DIRECT_PERMISSION_CACHE_KEY_PREFIX . $uid);
    \Nexus\Database\NexusDB::cache_del("user_role_ids:$uid");
    \Nexus\Database\NexusDB::cache_del("direct_permissions:$uid");
    if ($passkey) {
        \Nexus\Database\NexusDB::cache_del('user_passkey_'.$passkey.'_content');//announce.php
        \Nexus\Database\NexusDB::cache_del('user_passkey_'.$passkey.'_rss');//torrentrss.php
    }
    $userInfo = \App\Models\User::query()->find($uid, \App\Models\User::$commonFields);
    if ($userInfo) {
        fire_event("user_updated", $userInfo);
    }
}

function clear_setting_cache()
{
    do_log("clear_setting_cache");
    \Nexus\Database\NexusDB::cache_del('nexus_settings_in_laravel');
    \Nexus\Database\NexusDB::cache_del('nexus_settings_in_nexus');
    \Nexus\Database\NexusDB::cache_del('setting_protected_forum');
    $channel = nexus_env("CHANNEL_NAME_SETTING");
    if (!empty($channel)) {
        \Nexus\Database\NexusDB::redis()->publish($channel, "update");
    }
}

/**
 * @see functions.php::get_category_row(), genrelist()
 */
function clear_category_cache()
{
    do_log("clear_category_cache");
    \Nexus\Database\NexusDB::cache_del('category_content');
    $searchBoxList = \App\Models\SearchBox::query()->get(['id']);
    foreach ($searchBoxList as $item) {
        \Nexus\Database\NexusDB::cache_del("category_list_mode_{$item->id}");
    }

}

/**
 * @see functions.php::searchbox_item_list()
 */
function clear_taxonomy_cache($table)
{
    do_log("clear_taxonomy_cache: $table");
    $list = \App\Models\SearchBox::query()->get(['id']);
    foreach ($list as $item) {
        \Nexus\Database\NexusDB::cache_del("{$table}_list_mode_{$item->id}");
    }
    \Nexus\Database\NexusDB::cache_del("{$table}_list_mode_0");
}

function clear_staff_message_cache()
{
    do_log("clear_staff_message_cache");
    \App\Repositories\MessageRepository::updateStaffMessageCountCache(false);
}

/**
 * @see functions.php::get_searchbox_value()
 */
function clear_search_box_cache()
{
    do_log("clear_search_box_cache");
    \Nexus\Database\NexusDB::cache_del("search_box_content");
}

/**
 * @see functions.php::get_category_icon_row()
 */
function clear_icon_cache()
{
    do_log("clear_icon_cache");
    \Nexus\Database\NexusDB::cache_del("category_icon_content");
}

function clear_inbox_count_cache($uid)
{
    do_log("clear_inbox_count_cache");
    foreach (\Illuminate\Support\Arr::wrap($uid) as $id) {
        \Nexus\Database\NexusDB::cache_del('user_'.$id.'_inbox_count');
        \Nexus\Database\NexusDB::cache_del('user_'.$id.'_unread_message_count');
    }
}

function clear_agent_allow_deny_cache()
{
    do_log("clear_agent_allow_deny_cache");
    $allowCacheKey = nexus_env("CACHE_KEY_AGENT_ALLOW", "all_agent_allows");
    $denyCacheKey = nexus_env("CACHE_KEY_AGENT_DENY", "all_agent_denies");
    foreach (["", ":php", ":go"] as $suffix) {
        \Nexus\Database\NexusDB::cache_del($allowCacheKey . $suffix);
        \Nexus\Database\NexusDB::cache_del($denyCacheKey . $suffix);
    }
}

/**
 * @see announce.php
 * @param $infoHash
 * @return void
 */
function clear_torrent_cache($infoHash)
{
    do_log("clear_torrent_cache");
    \Nexus\Database\NexusDB::cache_del('torrent_hash_'.$infoHash.'_content');
    \Nexus\Database\NexusDB::cache_del("torrent_not_exists:$infoHash");
}

function user_can($permission, $fail = false, $uid = 0): bool
{
    return \App\Support\Permissions::userCan($permission, (bool) $fail, (int) $uid);
}

function assert_has_permission(bool $permissionCheckResult): void
{
    \App\Support\Permissions::assertHasPermission($permissionCheckResult);
}



function is_donor(array $userInfo): bool
{
    return \App\Support\UserDisplay::isDonor($userInfo);
}

/**
 * @deprecated
 * @param $authkey
 * @return false|int|mixed|string|null
 * @throws \App\Exceptions\NexusException
 * @see download.php
 */
function get_passkey_by_authkey($authkey)
{
    return \Nexus\Database\NexusDB::remember("authkey2passkey:$authkey", 3600*24, function () use ($authkey) {
        $arr = explode('|', $authkey);
        if (count($arr) != 3) {
            throw new \InvalidArgumentException("Invalid authkey: $authkey, format error");
        }
        $uid = $arr[1];
        $torrentRep = new \App\Repositories\TorrentRepository();
        $decrypted = $torrentRep->checkTrackerReportAuthKey($authkey);
        if (empty($decrypted)) {
            throw new \InvalidArgumentException("Invalid authkey: $authkey");
        }
        $userInfo = \Nexus\Database\NexusDB::remember("announce_user_passkey_$uid", 3600, function () use ($uid) {
            return \App\Models\User::query()->where('id', $uid)->first(['id', 'passkey']);
        });
        return $userInfo->passkey;
    });
}

function executeCommand($command, $format = 'string', $artisan = false, $exception = true): string|array
{
    $append = " 2>&1";
    if (!str_ends_with($command, $append)) {
        $command .= $append;
    }
    if ($artisan) {
        $phpPath = nexus_env('PHP_PATH') ?: 'php';
        $webRoot = rtrim(ROOT_PATH, '/');
        $command = "$phpPath $webRoot/artisan $command";
    }
    do_log("command: $command");
    $result = exec($command, $output, $result_code);
    $outputString = implode("\n", $output);
    $log = sprintf('result_code: %s, result: %s, output: %s', $result_code, $result, $outputString);
    if ($result_code != 0) {
        do_log($log, "error");
        if ($exception) {
            throw new \RuntimeException($outputString);
        }
    } else {
        do_log($log);
    }
    return $format == 'string' ? $outputString : $output;
}

function has_role_work_seeding($uid)
{
    return \App\Support\Permissions::hasRoleWorkSeeding((int) $uid);
}

function filter_src($src)
{
    return \App\Support\Security::filterSrc($src);
}

//here must retrieve the real time info, no cache!!!
function get_snatch_info($torrentId, $userId)
{
    return \App\Support\LegacyDb::snatchInfo($torrentId, $userId);
}

/**
 * 完整的 Laravel 事件, 在 php 端有监听者的需要触发. 同样会执行 publish_model_event()
 */
function fire_event(string $name, \Illuminate\Database\Eloquent\Model $model, ?\Illuminate\Database\Eloquent\Model $oldModel = null): void
{
    if (!isset(\App\Enums\ModelEventEnum::$eventMaps[$name])) {
        throw new \InvalidArgumentException("Event $name is not a valid event enumeration");
    }
    if (IN_NEXUS) {
        $prefix = "fire_event:";
        $idKey = $prefix . \Illuminate\Support\Str::random();
        $idKeyOld = "";
        \Nexus\Database\NexusDB::cache_put($idKey, serialize($model->toArray()), 3600*24*30);
        if ($oldModel) {
            $idKeyOld = $prefix . \Illuminate\Support\Str::random();
            \Nexus\Database\NexusDB::cache_put($idKeyOld, serialize($oldModel->toArray()), 3600*24*30);
        }
//        executeCommand("event:fire --name=$name --idKey=$idKey --idKeyOld=$idKeyOld", "string", true, false);
        \Nexus\Nexus::dispatchQueueJob(new \App\Jobs\FireEvent($name, $idKey, $idKeyOld));
        do_log("success fire_event in nexus, name: $name, idKey: $idKey, idKeyOld: $idKeyOld");
    } else {
        $eventClass = \App\Enums\ModelEventEnum::$eventMaps[$name]['event'];
        if (str_ends_with($name, '_deleted')) {
            //if deleted from database, can not pass model instance, use array
            $params = [$model->toArray()];
            if ($oldModel) {
                $params[] = $oldModel->toArray();
            }
        } else {
            $params = [$model];
            if ($oldModel) {
                $params[] = $oldModel;
            }
        }
        call_user_func_array([$eventClass, "dispatch"], $params);
        publish_model_event($name, $model->id, $model->toJson());
        do_log("success fire_event in laravel, name: $name, id: $model->id, oldId: " . ($oldModel ? $oldModel->id : ""));
    }
}

/**
 * 仅仅是往 redis 发布事件, php 端无监听者仅在其他平台有需要的触发这个即可, 较轻量
 */
function publish_model_event(string $event, int $id, string $json = ""): void
{
    $channel = nexus_env("CHANNEL_NAME_MODEL_EVENT");
    if (!empty($channel)) {
        \Nexus\Database\NexusDB::redis()->publish($channel, json_encode(["event" => $event, "id" => $id, "json" => $json]));
    } else {
        do_log("event: $event, id: $id, channel: $channel, channel is empty!", "error");
    }
}

function convertNamespaceToSnake(string $str): string
{
    return str_replace(["\\", "::"], ["_", "."], $str);
}

function get_user_locale(int $uid): string
{
    $sql = "select language.site_lang_folder from users inner join language on users.lang = language.id where users.id = $uid limit 1";
    $result = \Nexus\Database\NexusDB::select($sql);
    if (empty($result) || empty($result[0]['site_lang_folder'])) {
        return "en";
    }
    return \App\Http\Middleware\Locale::$languageMaps[$result[0]['site_lang_folder']] ?? $result[0]['site_lang_folder'];
}

function send_admin_success_notification(string $msg = ""): void {
    \Filament\Notifications\Notification::make()->success()->title($msg ?: "Success!")->send();
}

function send_admin_fail_notification(string $msg = ""): void {
    \Filament\Notifications\Notification::make()->danger()->title($msg ?: "Fail!")->send();
}

function ability(\App\Enums\Permission\RoutePermissionEnum $permission): string {
    return \App\Support\Permissions::abilityLabel($permission);
}

function get_challenge_key(string $challenge): string {
    return "challenge:".$challenge;
}

function get_user_from_cookie(array $cookie, $isArray = true): array|\App\Models\User|null {
    $log = "cookie: " . json_encode($cookie);
    $result = get_user_id_and_signature_from_cookie($cookie);
    if (empty($result)) {
        return null;
    }
    $id = $result['user_id'];
    $tokenJson = $result['token_json'];
    $signature = $result['signature'];
    $log .= ", uid = $id";
    $isAjax = nexus()->isAjax();
    $selfEnableBonus = \App\Models\Setting::getSelfEnableBonus();
    //only in nexus web can self-enable, and require bonus > 0
    $shouldIgnoreEnabled = IN_NEXUS && !$isAjax && $selfEnableBonus > 0;
    if ($isArray) {
        $whereStr = sprintf("id = %d and status = 'confirmed'", $id);
        if (!$shouldIgnoreEnabled) {
            $whereStr .= " and enabled = 'yes'";
        }
        $res = sql_query("SELECT * FROM users WHERE $whereStr LIMIT 1");
        $row = mysql_fetch_array($res);
        if (!$row) {
            do_log("$log, user not exists");
            return null;
        }
        $authKey = $row["auth_key"];
        unset($row['auth_key'], $row['passhash']);
    } else {
        $row = \App\Models\User::query()->find($id);
        if (!$row) {
            do_log("$log, user not exists");
            return null;
        }
        $checkFields = ['status'];
        if (!$shouldIgnoreEnabled) {
            $checkFields[] = 'enabled';
        }
        $row->checkIsNormal($checkFields);
        $authKey = $row->auth_key;
    }
    $expectedSignature = hash_hmac('sha256', $tokenJson, $authKey);
    if (!hash_equals($expectedSignature, $signature)) {
        do_log("$log, !hash_equals, expectedSignature: $expectedSignature, actualSignature: $signature");
        return null;
    }
    return $row;
}

function get_user_id_and_signature_from_cookie(array $cookie): array|null
{
    $log = "cookie: " . json_encode($cookie);
    if (empty($cookie["c_secure_pass"])) {
        do_log("$log, param not enough");
        return null;
    }
    $base64Decoded = base64_decode($cookie["c_secure_pass"]);
    if (empty($base64Decoded)) {
        do_log("$log, invalid c_secure_pass");
        return null;
    }
    $log .= ", base64 decoded: " . $base64Decoded;
    $tokenJsonAndSignature = explode(".", $base64Decoded);
    if (count($tokenJsonAndSignature) != 2) {
        do_log("$log, invalid c_secure_pass base64_decoded");
        return null;
    }
    $tokenJson = $tokenJsonAndSignature[0];
    $signature = $tokenJsonAndSignature[1];
    if (empty($tokenJson) || empty($signature)) {
        do_log("$log, no tokenJson or signature");
        return null;
    }
    $tokenData = json_decode($tokenJson, true);
    if (!isset($tokenData['user_id'])) {
        do_log("$log, no user_id");
        return null;
    }
    if (!isset($tokenData['expires']) || $tokenData['expires'] < time()) {
        do_log("$log, signature expired");
        return null;
    }
    return [
        "user_id" => $tokenData['user_id'],
        'token_json' => $tokenJson,
        'signature' => $signature,
    ];
}

function render_password_hash_js(string $formId, string $passwordOriginalClass, string $passwordHashedName, bool $passwordRequired, string $passwordConfirmClass = "password_confirmation", string $usernameName = "username"): void {
    $tipTooShort = nexus_trans('signup.password_too_short');
    $tipTooLong = nexus_trans('signup.password_too_long');
    $tipEqualUsername = nexus_trans('signup.password_equals_username');
    $tipNotMatch = nexus_trans('signup.passwords_unmatched');
    $passwordValidateJS = "";
    if ($passwordRequired) {
        $passwordValidateJS = <<<JS
if (password.length < 6) {
    layer.alert("$tipTooShort")
    return
}
if (password.length > 40) {
    layer.alert("$tipTooLong")
    return
}
JS;
    }
    $formVar = "jqForm" . md5($formId);
    $js = <<<JS
var $formVar = jQuery("#{$formId}");
$formVar.on("click", "input[type=button]", function() {
    let jqUsername = $formVar.find("[name={$usernameName}]")
    let jqPassword = $formVar.find(".{$passwordOriginalClass}")
    let jqPasswordConfirm = $formVar.find(".{$passwordConfirmClass}")
    let password = jqPassword.val()
    $passwordValidateJS
    if (jqUsername.length > 0 && jqUsername.val() === password) {
        layer.alert("$tipEqualUsername")
        return
    }
    if (jqPasswordConfirm.length > 0 && password !== jqPasswordConfirm.val()) {
        layer.alert("$tipNotMatch")
        return
    }
    if (password !== "") {
        const passwordHashed = sha256(password)
        $formVar.find("input[name={$passwordHashedName}]").val(passwordHashed)
        $formVar.submit()
    } else {
        $formVar.submit()
    }
})
JS;
    \Nexus\Nexus::js("js/crypto-js.js", 'footer', true);
    \Nexus\Nexus::js($js, 'footer', false);
}

function render_password_challenge_js(string $formId, string $usernameName, string $passwordOriginalClass): void {
    $formVar = "jqForm" . md5($formId);
    $js = <<<JS
var $formVar = jQuery("#{$formId}");
$formVar.on("click", "input[type=button]", function() {
    let useChallengeResponseAuthentication = $formVar.find("input[name=response]").length > 0
    if (!useChallengeResponseAuthentication) {
        return $formVar.submit()
    }
    let jqUsername = $formVar.find("[name={$usernameName}]")
    let jqPassword = $formVar.find(".{$passwordOriginalClass}")
    let username = jqUsername.val()
    let password = jqPassword.val()
    login(username, password, $formVar)
})
async function login(username, password, jqForm) {
    try {
        jQuery('body').loading({stoppable: false});
        const challengeResponse = await fetch('/api/challenge', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ username: username })
        });
        jQuery('body').loading('stop');

        const challengeData = await challengeResponse.json();
        if (challengeData.ret !== 0) {
            layer.alert(challengeData.msg)
            return
        }

        const clientHashedPassword = sha256(password);

        const serverSideHash = sha256(challengeData.data.secret + clientHashedPassword);

        const clientResponse = hmacSha256(challengeData.data.challenge, serverSideHash);
        jqForm.find("input[name=response]").val(clientResponse)
        jqForm.submit()
    } catch (error) {
        console.error(error);
        layer.alert(error.toString())
    }
}
JS;
    \Nexus\Nexus::js("vendor/jquery-loading/jquery.loading.min.js", 'footer', true);
    \Nexus\Nexus::js("js/crypto-js.js", 'footer', true);
    \Nexus\Nexus::js($js, 'footer', false);
}

function nexus_escape($data): array|string
{
    if (is_array($data)) {
        return array_map('nexus_escape', $data);
    }
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

function is_fpm_mode(): bool
{
    return php_sapi_name() === 'fpm-fcgi';
}
