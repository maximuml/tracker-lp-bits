<?php

defined('VERSION_NUMBER') || define('VERSION_NUMBER', '1.10.3');
defined('RELEASE_DATE') || define('RELEASE_DATE', '2026-06-28');
defined('IN_TRACKER') || define('IN_TRACKER', false);
defined('PROJECTNAME') || define('PROJECTNAME', 'NexusPHP');
defined('NEXUSPHPURL') || define('NEXUSPHPURL', 'https://nexusphp.org');
defined('NEXUSWIKIURL') || define('NEXUSWIKIURL', 'https://doc.nexusphp.org');
defined('VERSION') || define('VERSION', 'Powered by <a href="aboutnexus.php">'.PROJECTNAME.'</a>');
defined('THISTRACKER') || define('THISTRACKER', 'General');
defined('CONTENT_WIDTH') || define('CONTENT_WIDTH', 1200);
defined('ROOT_PATH') || define('ROOT_PATH', dirname(__DIR__).'/');
defined('DEFAULT_TRACKER_URI') || define('DEFAULT_TRACKER_URI', '/announce.php');
defined('MIN_ANNOUNCE_WAIT_SECOND') || define('MIN_ANNOUNCE_WAIT_SECOND', 300);

// Legacy user-class constants. Moved here from include/core.php so they are
// available for all entry points (web, artisan, tests) without duplicating the
// definitions in the per-request legacy bootstrap.
defined('UC_PEASANT') || define('UC_PEASANT', 0);
defined('UC_USER') || define('UC_USER', 1);
defined('UC_POWER_USER') || define('UC_POWER_USER', 2);
defined('UC_ELITE_USER') || define('UC_ELITE_USER', 3);
defined('UC_CRAZY_USER') || define('UC_CRAZY_USER', 4);
defined('UC_INSANE_USER') || define('UC_INSANE_USER', 5);
defined('UC_VETERAN_USER') || define('UC_VETERAN_USER', 6);
defined('UC_EXTREME_USER') || define('UC_EXTREME_USER', 7);
defined('UC_ULTIMATE_USER') || define('UC_ULTIMATE_USER', 8);
defined('UC_NEXUS_MASTER') || define('UC_NEXUS_MASTER', 9);
defined('UC_VIP') || define('UC_VIP', 10);
defined('UC_RETIREE') || define('UC_RETIREE', 11);
defined('UC_UPLOADER') || define('UC_UPLOADER', 12);
defined('UC_MODERATOR') || define('UC_MODERATOR', 13);
defined('UC_ADMINISTRATOR') || define('UC_ADMINISTRATOR', 14);
defined('UC_SYSOP') || define('UC_SYSOP', 15);
defined('UC_STAFFLEADER') || define('UC_STAFFLEADER', 16);

if (! defined('RUNNING_IN_OCTANE')) {
    $runningInOctane = false;
    foreach (($_SERVER['argv'] ?? []) as $command) {
        if (preg_match('/swoole|roadrunner/i', $command)) {
            $runningInOctane = true;
            break;
        }
    }
    define('RUNNING_IN_OCTANE', $runningInOctane);
}
