<?php

/**
 * W3-01: Auto-classify all test files and add #[TestCategory] attribute.
 *
 * Usage: php tests/classify-tests.php [--dry-run]
 *
 * Heuristics:
 *   tests/Architecture/*Test.php        → architecture
 *   tests/Feature/Concurrency/*Test.php → concurrency
 *   tests/Performance/*.js              → (skipped, not PHP)
 *   CriticalPathTest / LegacySmokeTest  → docker-e2e
 *   tests/Feature/*Test.php with HTTP   → http-feature
 *   tests/Feature/*Test.php without HTTP → service-integration
 *   tests/Unit/*Test.php with DatabaseTransactions → service-integration
 *   tests/Unit/*Test.php without DB     → pure-unit
 *
 * Multiple categories are assigned when appropriate (e.g. concurrency
 * tests that also make HTTP requests get both).
 */

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use Tests\Attributes\TestCategory;

$dryRun = in_array('--dry-run', $argv, true);

$testsDir = __DIR__;
$stats = ['classified' => 0, 'skipped' => 0, 'already' => 0];

$directory = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($testsDir, FilesystemIterator::SKIP_DOTS),
);

foreach ($directory as $file) {
    if (! $file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }

    $path = $file->getPathname();
    $content = file_get_contents($path);
    if ($content === false) {
        continue;
    }

    // Skip non-test files (traits, attributes, helpers, this script).
    $basename = basename($path);
    if ($basename === 'classify-tests.php' || $basename === 'TestCategory.php') {
        continue;
    }

    // Skip files that don't contain a test class.
    if (! preg_match('/class\s+\w+Test\b/', $content)) {
        continue;
    }

    // Skip if already has TestCategory attribute.
    if (preg_match('/#\[TestCategory\s*\(/', $content)) {
        $stats['already']++;

        continue;
    }

    // Skip the classification ratchet test itself.
    if ($basename === 'TestClassificationRatchetTest.php') {
        continue;
    }

    $relativePath = str_replace($testsDir.'/', '', $path);
    $categories = classifyTest($relativePath, $content);

    if (empty($categories)) {
        echo "WARNING: Could not classify $relativePath\n";
        $stats['skipped']++;

        continue;
    }

    // Build the attribute string.
    $attrArgs = implode(', ', array_map(
        fn ($cat) => 'TestCategory::'.$cat,
        $categories,
    ));

    // Add the import for TestCategory if not present.
    if (! str_contains($content, 'use Tests\\Attributes\\TestCategory;')) {
        // Find the last "use" statement and add after it.
        if (preg_match_all('/^use [^;]+;$/m', $content, $matches, PREG_OFFSET_CAPTURE)) {
            $lastUse = end($matches[0]);
            $insertPos = $lastUse[1] + strlen($lastUse[0]);
            $content = substr($content, 0, $insertPos)
                ."\nuse Tests\\Attributes\\TestCategory;"
                .substr($content, $insertPos);
        } else {
            // No use statements; add after namespace declaration.
            if (preg_match('/^namespace\s+[^\s;]+;/m', $content, $nsMatch, PREG_OFFSET_CAPTURE)) {
                $insertPos = $nsMatch[0][1] + strlen($nsMatch[0][0]);
                $content = substr($content, 0, $insertPos)
                    ."\n\nuse Tests\\Attributes\\TestCategory;"
                    .substr($content, $insertPos);
            }
        }
    }

    // Add the attribute before the class declaration.
    // Find the class declaration line.
    if (preg_match('/^(\s*)(?:final\s+|abstract\s+)?class\s+\w+/m', $content, $classMatch, PREG_OFFSET_CAPTURE)) {
        $classPos = $classMatch[0][1];
        $indent = $classMatch[1][0];
        $attrLine = "{$indent}#[TestCategory({$attrArgs})]\n";

        // Check if there's a doc comment before the class.
        // Find the position just before the class declaration.
        $beforeClass = substr($content, 0, $classPos);

        // Find the last /** ... */ doc comment before the class.
        if (preg_match_all('/\/\*\*.*?\*\//s', $beforeClass, $docMatches, PREG_OFFSET_CAPTURE)) {
            $lastDoc = end($docMatches[0]);
            $docEnd = $lastDoc[1] + strlen($lastDoc[0]);
            // Check if there's only whitespace between doc end and class.
            $between = substr($content, $docEnd, $classPos - $docEnd);
            if (trim($between) === '') {
                // Insert after the doc comment.
                $content = substr($content, 0, $docEnd)
                    ."\n".$attrLine
                    .substr($content, $docEnd);
            } else {
                // Insert before the class.
                $content = substr($content, 0, $classPos)
                    .$attrLine
                    .substr($content, $classPos);
            }
        } else {
            // No doc comment; insert before the class.
            $content = substr($content, 0, $classPos)
                .$attrLine
                .substr($content, $classPos);
        }
    } else {
        echo "WARNING: Could not find class declaration in $relativePath\n";
        $stats['skipped']++;

        continue;
    }

    if (! $dryRun) {
        file_put_contents($path, $content);
    }

    $stats['classified']++;
    echo "  $relativePath → ".implode(', ', $categories)."\n";
}

echo "\n=== Summary ===\n";
echo "Classified: {$stats['classified']}\n";
echo "Already had attribute: {$stats['already']}\n";
echo "Skipped: {$stats['skipped']}\n";

/**
 * Classify a test file based on path and content heuristics.
 *
 * @return list<string> Category constant names (without prefix).
 */
function classifyTest(string $relativePath, string $content): array
{
    $categories = [];

    // Architecture tests.
    if (str_starts_with($relativePath, 'Architecture/')) {
        $categories[] = 'ARCHITECTURE';

        return $categories;
    }

    // Concurrency tests.
    if (str_contains($relativePath, 'Concurrency/')) {
        $categories[] = 'CONCURRENCY';
    }

    // Performance / query budget tests.
    if (str_contains($relativePath, 'Performance/') || str_contains($relativePath, 'QueryBudget')) {
        $categories[] = 'PERFORMANCE';
    }

    // Docker E2E tests (CriticalPathTest, LegacySmokeTest use real web server).
    if (str_contains($relativePath, 'CriticalPathTest') || str_contains($relativePath, 'LegacySmokeTest')) {
        if (! in_array('PERFORMANCE', $categories, true)) {
            $categories[] = 'DOCKER_E2E';
        }

        return $categories;
    }

    // Feature tests.
    if (str_starts_with($relativePath, 'Feature/')) {
        // Check if it makes HTTP calls.
        $hasHttp = preg_match('/->get\(|->post\(|->put\(|->delete\(|->patch\(|->call\(/', $content);
        if ($hasHttp) {
            if (empty($categories)) {
                $categories[] = 'HTTP_FEATURE';
            }
        } else {
            $categories[] = 'SERVICE_INTEGRATION';
        }

        return $categories;
    }

    // Unit tests.
    if (str_starts_with($relativePath, 'Unit/')) {
        // Check if it uses DatabaseTransactions or RefreshDatabase.
        $hasDb = preg_match('/DatabaseTransactions|RefreshDatabase/', $content);
        // Check if it uses Mockery (pure unit with mocks).
        $hasMockery = preg_match('/Mockery::mock|Mockery::close/', $content);

        if ($hasDb) {
            $categories[] = 'SERVICE_INTEGRATION';
        } else {
            $categories[] = 'PURE_UNIT';
        }

        // Check for Infection target files (controllers/services in infection.json5).
        if (preg_match('/Controller|AuthenticateController|TokenController/', $relativePath)) {
            // These are Infection targets.
            if (! in_array('MUTATION', $categories, true)) {
                $categories[] = 'MUTATION';
            }
        }

        return $categories;
    }

    return $categories;
}
