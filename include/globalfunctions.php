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

function hash_pad($hash) {
    return \App\Support\Strings::padHash($hash);
}

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
    return \App\Support\Env::cast($value);
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
    return \App\Support\Environment::commandExists($command);
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
    return (int) \Nexus\Database\NexusDB::table($table)->count();
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
    \App\Support\Hooks::addFilter($name, $function, (int) $priority, (int) $argc);
}

function apply_filter($name, ...$args)
{
    return \App\Support\Hooks::applyFilter($name, ...$args);
}

function add_action($name, $function, $priority = 10, $argc = 1)
{
    \App\Support\Hooks::addAction($name, $function, (int) $priority, (int) $argc);
}

function do_action($name, ...$args)
{
    return \App\Support\Hooks::doAction($name, ...$args);
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
    \App\Support\Cache::clearUser($uid, $passkey);
}

function clear_setting_cache()
{
    \App\Support\Cache::clearSettings();
}

/**
 * @see functions.php::get_category_row(), genrelist()
 */
function clear_category_cache()
{
    \App\Support\Cache::clearCategory();
}

/**
 * @see functions.php::searchbox_item_list()
 */
function clear_taxonomy_cache($table)
{
    \App\Support\Cache::clearTaxonomy($table);
}

function clear_staff_message_cache()
{
    \App\Support\Cache::clearStaffMessage();
}

/**
 * @see functions.php::get_searchbox_value()
 */
function clear_search_box_cache()
{
    \App\Support\Cache::clearSearchBox();
}

/**
 * @see functions.php::get_category_icon_row()
 */
function clear_icon_cache()
{
    \App\Support\Cache::clearIcon();
}

function clear_inbox_count_cache($uid)
{
    \App\Support\Cache::clearInboxCount($uid);
}

function clear_agent_allow_deny_cache()
{
    \App\Support\Cache::clearAgentAllowDeny();
}

/**
 * @see announce.php
 * @param $infoHash
 * @return void
 */
function clear_torrent_cache($infoHash)
{
    \App\Support\Cache::clearTorrent($infoHash);
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
    return \App\Support\AuthCookie::passkeyByAuthkey($authkey);
}

function executeCommand($command, $format = 'string', $artisan = false, $exception = true): string|array
{
    return \App\Support\Environment::run($command, $format, (bool) $artisan, (bool) $exception);
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
    \App\Support\Events::fire($name, $model, $oldModel);
}

/**
 * 仅仅是往 redis 发布事件, php 端无监听者仅在其他平台有需要的触发这个即可, 较轻量
 */
function publish_model_event(string $event, int $id, string $json = ""): void
{
    \App\Support\Events::publishModel($event, $id, $json);
}

function convertNamespaceToSnake(string $str): string
{
    return \App\Support\Strings::namespaceToSnake($str);
}

function get_user_locale(int $uid): string
{
    return \App\Support\Locale::userLocale($uid);
}

function send_admin_success_notification(string $msg = ""): void {
    \App\Support\Admin::successNotification($msg);
}

function send_admin_fail_notification(string $msg = ""): void {
    \App\Support\Admin::failNotification($msg);
}

function ability(\App\Enums\Permission\RoutePermissionEnum $permission): string {
    return \App\Support\Permissions::abilityLabel($permission);
}

function get_challenge_key(string $challenge): string {
    return \App\Support\Token::challengeKey($challenge);
}

function get_user_from_cookie(array $cookie, $isArray = true): array|\App\Models\User|null {
    return \App\Support\AuthCookie::userFromCookie($cookie, (bool) $isArray);
}

function get_user_id_and_signature_from_cookie(array $cookie): array|null
{
    return \App\Support\AuthCookie::decodeCookie($cookie);
}

function render_password_hash_js(string $formId, string $passwordOriginalClass, string $passwordHashedName, bool $passwordRequired, string $passwordConfirmClass = "password_confirmation", string $usernameName = "username"): void {
    \App\Support\Form::passwordHashJs($formId, $passwordOriginalClass, $passwordHashedName, $passwordRequired, $passwordConfirmClass, $usernameName);
}

function render_password_challenge_js(string $formId, string $usernameName, string $passwordOriginalClass): void {
    \App\Support\Form::passwordChallengeJs($formId, $usernameName, $passwordOriginalClass);
}

function nexus_escape($data): array|string
{
    return \App\Support\Strings::escapeHtml($data);
}

function is_fpm_mode(): bool
{
    return \App\Support\Environment::isFpm();
}
