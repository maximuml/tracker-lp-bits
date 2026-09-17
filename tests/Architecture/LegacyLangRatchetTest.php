<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Attributes\TestCategory;

/**
 * Ratchet on the legacy per-page language arrays (step 1.2).
 *
 * The custom system — lang/en/lang_<script>.php files required into
 * $lang_<script> globals — was converted to Laravel translations under
 * resources/lang/en/legacy/*.php. Reads still go through array access
 * ($lang_x['key'] in PHP / views) until each section is converted to
 * __('legacy/x.key'). This test keeps the remaining count from growing.
 *
 * Baselines captured on 2026-09-17 (post-conversion): 3205
 * Lowered on 2026-09-17 after forums + messages sections: 2883
 * Lowered after the bulk section migration (controllers, services,
 * builders, views): 11 — all remaining matches are docblock prose and
 * locale-folder `$lang` variables in Locale/LanguageRepository, not
 * legacy language-array reads.
 *
 * Hard rules (not baselines):
 *   - lang/ directory must not come back
 *   - Globals::get('lang_…') / globals->get('lang_…') reads are banned —
 *     use trans('legacy/<suffix>') for the whole array or __('legacy/x.k')
 *     for a single key.
 *
 * To check the current count:
 *   grep -roh '\$lang[a-zA-Z0-9_]*\s*\[' app/ resources/views/ | wc -l
 */
#[TestCategory(TestCategory::ARCHITECTURE)]
final class LegacyLangRatchetTest extends TestCase
{
    private const BASE_DIR = __DIR__.'/../..';

    /** Baseline: `$lang…[` reads across app/ and resources/views. */
    private const BASELINE_LANG_ARRAY_REFS = 11;

    public function test_lang_array_reads_do_not_exceed_baseline(): void
    {
        $offenders = $this->matchingLines('/\$lang[a-zA-Z0-9_]*\s*\[/');

        $this->assertLessThanOrEqual(
            self::BASELINE_LANG_ARRAY_REFS,
            count($offenders),
            $this->failureMessage(
                '$lang…[ reads',
                self::BASELINE_LANG_ARRAY_REFS,
                $offenders,
            ),
        );
    }

    public function test_legacy_lang_directory_is_gone(): void
    {
        $this->assertDirectoryDoesNotExist(
            self::BASE_DIR.'/lang',
            'lang/en/lang_*.php was replaced by resources/lang/en/legacy/*.php — do not reintroduce the custom loader.',
        );
    }

    public function test_no_globals_lang_reads(): void
    {
        $offenders = $this->matchingLines("/->get\\(['\"]lang_/");

        $this->assertSame(
            [],
            $offenders,
            $this->failureMessage('globals->get(\'lang_…\') reads', 0, $offenders),
        );
    }

    /**
     * Collect matching lines across app/ and resources/views/.
     *
     * @return list<string> file:line entries for the failure message
     */
    private function matchingLines(string $pattern): array
    {
        $found = [];

        foreach ([self::BASE_DIR.'/app', self::BASE_DIR.'/resources/views'] as $dir) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $path = $file->getPathname();
                $relative = substr($path, strlen(self::BASE_DIR) + 1);

                $content = file_get_contents($path);
                if ($content === false) {
                    continue;
                }

                foreach (explode("\n", $content) as $lineNo => $line) {
                    if (preg_match_all($pattern, $line, $m) > 0) {
                        foreach ($m[0] as $_) {
                            $found[] = sprintf('%s:%d', $relative, $lineNo + 1);
                        }
                    }
                }
            }
        }

        return $found;
    }

    /**
     * @param  list<string>  $offenders
     */
    private function failureMessage(string $label, int $baseline, array $offenders): string
    {
        $preview = implode("\n", array_slice($offenders, 0, 40));
        $extra = count($offenders) > 40 ? sprintf("\n… and %d more", count($offenders) - 40) : '';

        return sprintf(
            "%s: %d found, max allowed %d.\n%s%s\nLower the baseline in %s as conversions land — never raise it.",
            $label,
            count($offenders),
            $baseline,
            $preview,
            $extra,
            self::class,
        );
    }
}
