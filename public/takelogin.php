<?php

/**
 * Legacy login form handler.
 *
 * Forwards both GET and POST requests to the Laravel /login route so
 * the old URL keeps working while the new Blade form posts directly to
 * /login (or here) with a CSRF token.
 */

$nexusRoute = '/login';

require __DIR__ . '/nexus.php';
