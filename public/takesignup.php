<?php

/**
 * Legacy signup form handler.
 *
 * Forwards both GET and POST requests to the Laravel /signup route so
 * the old URL keeps working while the new Blade form posts directly to
 * /signup (or here) with a CSRF token.
 */

$nexusRoute = '/signup';

require __DIR__ . '/nexus.php';
