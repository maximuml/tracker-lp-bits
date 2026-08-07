<?php
/**
 * @var string $rootpath
 */
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 0);
require_once __DIR__ . '/constants.php';
require_once $rootpath . 'vendor/autoload.php';
\App\Support\SupportContext::setUserUpdateSet([]);
$USERUPDATESET = &\App\Support\SupportContext::getUserUpdateSet();
$query_name=array();
\Nexus\Nexus::boot();
if (is_fpm_mode()) {
    if (!file_exists($rootpath . '.env')
        || (getenv('RUNNING_IN_DOCKER') && !file_exists($rootpath . \Nexus\Install\Install::INSTALL_LOCK_FILE))
    ) {
        $installScriptRelativePath = 'install/install.php';
        $installScriptFile = $rootpath . "public/$installScriptRelativePath";
        if (file_exists($installScriptFile)) {
            nexus_redirect($installScriptRelativePath);
        }
    }
}
require $rootpath . 'classes/class_cache_redis.php';
require $rootpath . 'include/eloquent.php';
ini_set('date.timezone', nexus_config('nexus.timezone'));
$Cache = new class_cache_redis(); //Load the caching class
$Cache->setLanguageFolderArray(get_langfolder_list());
require $rootpath . 'include/config.php';
$script = nexus()->getScript();
if (!in_array($script, ['announce', 'scrape'])) {
    require $rootpath . get_langfile_path("functions.php");
}
if (!isRunningInConsole() && !in_array($script, ['announce', 'scrape', 'torrentrss', 'download'])) {
    checkGuestVisit();
}

defined('TIMENOW') || define('TIMENOW', time());

// UC_* constants are now defined in include/constants.php so they are
// available to all entry points (CLI, tests, web) without duplication.
ignore_user_abort(true);
@set_time_limit(60);

$hook = new \Nexus\Plugin\Hook();
$plugin = new \Nexus\Plugin\Plugin();
$plugin->start();
