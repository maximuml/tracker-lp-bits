<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

// Feature/E2E suites must exercise the same IN_NEXUS=true code paths as
// production (public/index.php). Unit/Integration suites do not set this
// variable: they define the constant themselves where a legacy branch is
// under test, so the process-wide constant stays false for them.
if (getenv('NEXUS_LEGACY_CONTEXT') === '1') {
    defined('IN_NEXUS') || define('IN_NEXUS', true);
}
