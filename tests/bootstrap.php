<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

// Feature/E2E suites must exercise the same legacy-runtime code paths as
// production (public/index.php). NEXUS_LEGACY_CONTEXT=1 is consumed by
// TestCase::setUp(), which boots the request-scoped LegacyRuntime into the
// legacy entry context per test. Unit/Integration suites do not set this
// variable: they call bootEntry(true) themselves where a legacy branch is
// under test, so the flag stays false for them.
