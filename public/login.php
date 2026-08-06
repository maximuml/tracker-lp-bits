<?php

/**
 * Legacy entry point for the login page.
 *
 * This thin wrapper forwards the request to the Laravel /login route so
 * that both /login and the old /login.php URL render the same Blade view.
 */

require __DIR__ . '/nexus.php';
