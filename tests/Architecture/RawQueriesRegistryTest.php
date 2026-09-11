<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Attributes\TestCategory;

/**
 * W5-01: raw-queries.json is the structured registry of every raw-SQL call
 * site in app/ and the source of truth for the CI "Raw SQL ratchet" job.
 *
 * The test rescans app/ with the same patterns as CI —
 *   DB::(select|statement|unprepared)\s*\(
 *   DB::table\s*\(\s*DB::raw
 * excluding app/Support/Install/ — and keeps the JSON in sync both ways:
 * every live call site must be registered and every registry entry must
 * point at a live call site.
 *
 * Call sites are matched by file + line on purpose: when a registered call
 * site moves, this test fails loudly and updating the `line` field in
 * raw-queries.json is part of the ritual of touching that code.
 *
 * To fix a failure: either replace the raw call with the query builder and
 * drop the registry entry, or add/update the entry (file, line, method,
 * pattern, purpose, bindings, expected_index, reason) after review.
 */
#[TestCategory(TestCategory::ARCHITECTURE)]
final class RawQueriesRegistryTest extends TestCase
{
    private const REGISTRY_PATH = __DIR__.'/../../raw-queries.json';

    private const APP_DIR = __DIR__.'/../../app';

    /** @var list<string> */
    private const REQUIRED_FIELDS = [
        'file',
        'line',
        'method',
        'pattern',
        'purpose',
        'bindings',
        'expected_index',
        'reason',
    ];

    /** @var list<string> */
    private const ALLOWED_PATTERNS = [
        'DB::select',
        'DB::statement',
        'DB::unprepared',
        'DB::table(DB::raw',
    ];

    public function test_registry_entries_have_valid_schema(): void
    {
        $entries = $this->registryEntries();

        $this->assertNotEmpty($entries, 'raw-queries.json contains no entries under "queries".');

        foreach ($entries as $index => $entry) {
            $label = sprintf('entry #%d (%s)', $index, $entry['file'] ?? 'unknown file');

            foreach (self::REQUIRED_FIELDS as $field) {
                $this->assertArrayHasKey($field, $entry, "Registry $label is missing required field '$field'.");
            }

            $this->assertIsString($entry['file'], "Registry $label: 'file' must be a string.");
            $this->assertNotSame('', $entry['file'], "Registry $label: 'file' must not be empty.");
            $this->assertFileExists(
                dirname(self::APP_DIR).'/'.$entry['file'],
                "Registry $label: file does not exist.",
            );

            $this->assertIsInt($entry['line'], "Registry $label: 'line' must be an int.");
            $this->assertGreaterThan(0, $entry['line'], "Registry $label: 'line' must be positive.");

            foreach (['method', 'pattern', 'purpose', 'expected_index', 'reason'] as $field) {
                $this->assertIsString($entry[$field], "Registry $label: '$field' must be a string.");
                $this->assertNotSame('', trim($entry[$field]), "Registry $label: '$field' must not be empty.");
            }

            $this->assertIsBool($entry['bindings'], "Registry $label: 'bindings' must be a boolean.");

            $this->assertContains(
                $entry['pattern'],
                self::ALLOWED_PATTERNS,
                "Registry $label: 'pattern' must be one of ".implode(', ', self::ALLOWED_PATTERNS).'.',
            );
        }
    }

    public function test_every_current_call_site_is_registered(): void
    {
        $registered = array_map(
            static fn (array $entry): string => $entry['file'].':'.$entry['line'],
            $this->registryEntries(),
        );

        $missing = [];
        foreach ($this->scanCallSites() as $site) {
            $key = $site['file'].':'.$site['line'];
            if (! in_array($key, $registered, true)) {
                $missing[] = $key;
            }
        }

        $this->assertSame(
            [],
            $missing,
            'Raw SQL call sites missing from raw-queries.json: '.implode(', ', $missing)
            .'. Add an entry per call site or replace the raw call with the query builder.',
        );
    }

    public function test_every_entry_has_bindings_or_reason(): void
    {
        foreach ($this->registryEntries() as $index => $entry) {
            $label = sprintf('entry #%d (%s:%s)', $index, $entry['file'] ?? '?', $entry['line'] ?? '?');
            $hasReason = isset($entry['reason']) && is_string($entry['reason']) && trim($entry['reason']) !== '';

            $this->assertTrue(
                ($entry['bindings'] ?? false) === true || $hasReason,
                "Registry $label must either use bound parameters (bindings=true) or explain why not (reason).",
            );
        }
    }

    public function test_registry_count_matches_call_site_count(): void
    {
        $scanned = array_map(
            static fn (array $site): string => $site['file'].':'.$site['line'],
            $this->scanCallSites(),
        );
        $registered = array_map(
            static fn (array $entry): string => $entry['file'].':'.$entry['line'],
            $this->registryEntries(),
        );

        $this->assertEqualsCanonicalizing(
            $scanned,
            $registered,
            'raw-queries.json is out of sync with app/. Update stale entries '
            .'(file/line drift) or remove entries for deleted call sites. '
            .'Scanned: '.implode(', ', $scanned).' — Registered: '.implode(', ', $registered),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function registryEntries(): array
    {
        $this->assertFileExists(self::REGISTRY_PATH, 'raw-queries.json is missing at the repo root.');

        $decoded = json_decode((string) file_get_contents(self::REGISTRY_PATH), true);

        $this->assertIsArray($decoded, 'raw-queries.json is not valid JSON.');
        $this->assertArrayHasKey('queries', $decoded, 'raw-queries.json must have a top-level "queries" array.');
        $this->assertIsList($decoded['queries'], '"queries" must be a list.');

        return $decoded['queries'];
    }

    /**
     * Rescan app/ with the same logic as the CI "Raw SQL ratchet" step:
     * lines matching DB::(select|statement|unprepared)\s*\( or
     * DB::table\s*\(\s*DB::raw, excluding app/Support/Install/.
     *
     * @return list<array{file: string, line: int}>
     */
    private function scanCallSites(): array
    {
        $sites = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(self::APP_DIR, \RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            // Skip Install scripts (standalone, IN_NEXUS=true context)
            $relativePath = str_replace(self::APP_DIR.'/', '', $file->getPathname());
            if (str_starts_with($relativePath, 'Support/Install/')) {
                continue;
            }

            $content = file_get_contents($file->getPathname());
            if ($content === false) {
                continue;
            }

            foreach (explode("\n", $content) as $lineNumber => $line) {
                if (preg_match('/DB::(select|statement|unprepared)\s*\(/', $line)
                    || preg_match('/DB::table\s*\(\s*DB::raw/', $line)
                ) {
                    $sites[] = [
                        'file' => 'app/'.$relativePath,
                        'line' => $lineNumber + 1,
                    ];
                }
            }
        }

        return $sites;
    }
}
