<?php

/**
 * PHPStan stub for legacy constants that are defined at runtime
 * in include/core.php and other bootstrap files.
 */

if (!defined('TIMENOW')) {
    define('TIMENOW', 0);
}

if (!defined('UC_PEASANT')) {
    define('UC_PEASANT', 0);
    define('UC_USER', 1);
    define('UC_POWER_USER', 2);
    define('UC_ELITE_USER', 3);
    define('UC_CRAZY_USER', 4);
    define('UC_INSANE_USER', 5);
    define('UC_VETERAN_USER', 6);
    define('UC_EXTREME_USER', 7);
    define('UC_ULTIMATE_USER', 8);
    define('UC_NEXUS_MASTER', 9);
    define('UC_VIP', 10);
    define('UC_RETIREE', 11);
    define('UC_UPLOADER', 12);
    define('UC_MODERATOR', 13);
    define('UC_ADMINISTRATOR', 14);
    define('UC_SYSOP', 15);
    define('UC_STAFFLEADER', 16);
}
