<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Attributes\TestCategory;

/**
 * W3-01: Every test class must carry a #[TestCategory] attribute.
 *
 * This ratchet ensures that all test files are explicitly classified
 * by the kind of behaviour they verify. The classification makes
 * coverage gaps visible (e.g. a critical flow with only pure-unit
 * tests but no HTTP or concurrency coverage).
 *
 * To add a new test file, add `#[TestCategory(TestCategory::*)]` above
 * the class declaration. Use the appropriate category:
 *
 *   pure-unit          — no DB, no HTTP; tests pure logic
 *   service-integration — uses DB/Redis; tests service or repository layer
 *   http-feature       — makes HTTP requests through the Laravel test kernel
 *   docker-e2e         — exercises the real web server (openresty) via curl
 *   concurrency        — tests parallel / concurrent operations
 *   mutation           — Infection mutation testing target
 *   performance        — performance budget / query budget / k6
 *   architecture       — architecture ratchet / invariant test
 *
 * A test class may carry multiple categories. The primary category
 * should be listed first.
 */
final class TestClassificationRatchetTest extends TestCase
{
    private const TESTS_DIR = __DIR__.'/..';

    public function test_all_test_classes_have_category_attribute(): void
    {
        $missing = [];
        $directory = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(self::TESTS_DIR, \FilesystemIterator::SKIP_DOTS),
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

            // Skip non-test files (traits, attributes, helpers).
            if (! preg_match('/class\s+\w+\s*(?:extends|implements|\{)/', $content)) {
                continue;
            }

            // Skip files that don't contain a test class.
            if (! preg_match('/class\s+\w+Test\b/', $content)) {
                continue;
            }

            // Skip this file and the attribute itself.
            $basename = basename($path);
            if ($basename === 'TestClassificationRatchetTest.php' || $basename === 'TestCategory.php') {
                continue;
            }

            // Check for #[TestCategory(...)] attribute.
            if (! preg_match('/#\[TestCategory\s*\(/', $content)) {
                $relativePath = str_replace(__DIR__.'/../', '', $path);
                $missing[] = $relativePath;
            }
        }

        sort($missing);

        $this->assertSame(
            [],
            $missing,
            sprintf(
                "%d test class(es) are missing the #[TestCategory] attribute.\n"
                ."Add `#[TestCategory(TestCategory::*)]` above each class declaration.\n"
                ."Missing files:\n  %s",
                count($missing),
                implode("\n  ", $missing),
            ),
        );
    }

    public function test_category_values_are_valid(): void
    {
        $invalid = [];
        $directory = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(self::TESTS_DIR, \FilesystemIterator::SKIP_DOTS),
        );

        $validCategories = TestCategory::all();

        foreach ($directory as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $content = file_get_contents($file->getPathname());
            if ($content === false) {
                continue;
            }

            // Find all #[TestCategory(...)] attributes.
            if (! preg_match_all('/#\[TestCategory\s*\(([^]]*)\)\]/', $content, $matches)) {
                continue;
            }

            foreach ($matches[1] as $args) {
                // Extract category constants like TestCategory::PURE_UNIT.
                preg_match_all('/TestCategory::(\w+)/', $args, $constMatches);
                foreach ($constMatches[1] as $const) {
                    $value = constant(TestCategory::class.'::'.$const);
                    if (! in_array($value, $validCategories, true)) {
                        $invalid[] = $file->getPathname().': '.$const;
                    }
                }
            }
        }

        $this->assertSame(
            [],
            $invalid,
            sprintf(
                "Invalid TestCategory values found:\n  %s",
                implode("\n  ", $invalid),
            ),
        );
    }
}
