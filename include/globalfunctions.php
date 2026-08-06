<?php

use App\Support\SupportContext;
use Illuminate\Support\Facades\Auth;

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
    return \App\Support\Permissions::userCan($permission, (bool) $fail, (int) $uid);
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
