<?php

/**
 * Legacy entry point for logout.
 *
 * Forwards the request to the Laravel /logout route so both /logout
 * and the old /logout.php URL clear the auth cookie and session.
 */

require __DIR__ . '/nexus.php';
