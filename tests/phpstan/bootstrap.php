<?php

/**
 * PHPStan bootstrap for legacy procedural code.
 *
 * Many include/*.php files guard against direct access with
 * `if (!defined('IN_TRACKER')) die;`. Defining the constant here lets
 * PHPStan parse them for symbol discovery without actually executing
 * the legacy boot process.
 *
 * We intentionally do *not* define `IN_NEXUS` here, because its value
 * differs between web (true) and console (false) contexts and the
 * code under analysis branches on it.
 */
if (! defined('IN_TRACKER')) {
    define('IN_TRACKER', true);
}
