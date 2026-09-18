<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Attributes\TestCategory;

/**
 * Stage-0 ratchet: markup-bearing legacy language values must not be
 * rendered through escaping contexts.
 *
 * Some `resources/lang/en/legacy/*.php` values still contain HTML tags
 * or `&nbsp;`-style entities (legacy string-building heritage — they are
 * concatenated into raw HTML in services, which renders them fine).
 * Passing such a value through an escaping channel produces visible
 * markup text (`&lt;b&gt;`, `&amp;nbsp;`) — the bug class fixed in the
 * Stage-0 hotfix.
 *
 * Rules:
 *   - Count ratchet: the number of markup-bearing values may only
 *     shrink. New plain-text strings must not carry markup; markup that
 *     is needed should live in Blade, not in language files.
 *   - A markup-bearing key must never appear inside `{{ ... }}` in a
 *     Blade view unless wrapped in `SafeHtml::from*()` (which renders
 *     the sanitized/trusted markup instead of escaping it).
 *   - A markup-bearing key must never be passed through
 *     `htmlspecialchars()`/`htmlentities()`/`e()` in app/ — that
 *     double-escapes the markup into visible text.
 *
 * To list markup-bearing values:
 *   grep -rnoE "['\"][a-z_]+['\"] *=> *['\"][^'\"]*<[^>]+>" resources/lang/en/legacy
 */
#[TestCategory(TestCategory::ARCHITECTURE)]
final class LegacyLangMarkupTest extends TestCase
{
    private const BASE_DIR = __DIR__.'/../..';

    private const LANG_DIR = self::BASE_DIR.'/resources/lang/en/legacy';

    /** Baseline: markup-bearing values across resources/lang/en/legacy. */
    private const BASELINE_MARKUP_VALUES = 222;

    private const MARKUP_PATTERN = '/<[a-zA-Z\/][^>]*>|&(?:nbsp|lt|gt|amp|quot);/';

    /**
     * @var array<string, true>|null
     *                               Cache of `legacy/group.key` identifiers whose value carries markup.
     */
    private ?array $markupKeys = null;

    public function test_markup_value_count_does_not_exceed_baseline(): void
    {
        $count = count($this->markupKeys());

        $this->assertLessThanOrEqual(
            self::BASELINE_MARKUP_VALUES,
            $count,
            sprintf(
                'Markup-bearing legacy language values increased from %d to %d. '
               .'Keep language strings plain text; put markup in Blade. '
               .'Lower the baseline — never raise it.',
                self::BASELINE_MARKUP_VALUES,
                $count,
            ),
        );
    }

    public function test_no_markup_key_is_rendered_escaped_in_views(): void
    {
        $offenders = [];

        foreach ($this->phpFiles(self::BASE_DIR.'/resources/views') as $path) {
            $content = (string) file_get_contents($path);
            $relative = substr($path, strlen(self::BASE_DIR) + 1);

            if (preg_match_all('/\{\{(.*?)\}\}/s', $content, $blocks) === 0) {
                continue;
            }

            foreach ($blocks[0] as $block) {
                if (! str_contains($block, 'SafeHtml::')) {
                    foreach ($this->markupKeys() as $key => $_) {
                        if (str_contains($block, "'".$key."'") || str_contains($block, '"'.$key.'"')) {
                            $offenders[] = $relative.': '.$key;
                        }
                    }
                }
            }
        }

        $this->assertSame(
            [],
            array_values(array_unique($offenders)),
            "Markup-bearing language values rendered through {{ }} without SafeHtml — visible escaped markup.\n"
            .implode("\n", array_slice(array_values(array_unique($offenders)), 0, 40))
            ."\nWrap in \\App\\Support\\Html\\SafeHtml::fromUntrustedHtml() or make the value plain text.",
        );
    }

    public function test_no_markup_key_is_double_escaped_in_app(): void
    {
        $offenders = [];

        foreach ($this->phpFiles(self::BASE_DIR.'/app') as $path) {
            $content = (string) file_get_contents($path);
            $relative = substr($path, strlen(self::BASE_DIR) + 1);

            foreach ($this->markupKeys() as $key => $_) {
                if (preg_match('/(?:htmlspecialchars|htmlentities|e)\s*\(\s*(?:__|trans)\s*\(\s*[\'\"]'.preg_quote($key, '/').'[\'\"]/', $content) === 1) {
                    $offenders[] = $relative.': '.$key;
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Markup-bearing language values passed through htmlspecialchars()/e() — double-escaped to visible text.\n"
            .implode("\n", $offenders)
            ."\nUse SafeHtml::fromUntrustedHtml() or make the value plain text.",
        );
    }

    /**
     * `Time::format()` returns an HTML string in the legacy branch
     * (`<span title>` / `<br />`); `{{ }}` escapes it into visible text.
     * Views must use `<x-time>` (SafeHtml) or `Time::formatText()`.
     */
    public function test_no_escaped_time_format_in_views(): void
    {
        $offenders = [];

        foreach ($this->phpFiles(self::BASE_DIR.'/resources/views') as $path) {
            $content = (string) file_get_contents($path);
            $relative = substr($path, strlen(self::BASE_DIR) + 1);

            foreach (explode("\n", $content) as $lineNo => $line) {
                if (preg_match('/\{\{[^}]*(?:\\\\?App\\\\Support\\\\)?Time::format\s*\(/', $line) === 1) {
                    $offenders[] = sprintf('%s:%d', $relative, $lineNo + 1);
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "HTML-returning Time::format() rendered through {{ }} — use <x-time> or Time::formatText().\n"
            .implode("\n", $offenders),
        );
    }

    /**
     * @return array<string, true>
     */
    private function markupKeys(): array
    {
        if ($this->markupKeys !== null) {
            return $this->markupKeys;
        }

        $keys = [];
        foreach (glob(self::LANG_DIR.'/*.php') ?: [] as $path) {
            $group = basename($path, '.php');
            $content = (string) file_get_contents($path);

            // 'key' => 'value' and 'key' => "value" (single-line entries).
            preg_match_all(
                '/[\'"]([a-zA-Z0-9_]+)[\'"]\s*=>\s*\'((?:[^\'\\\\]|\\\\.)*)\'/s',
                $content,
                $single,
                PREG_SET_ORDER,
            );
            preg_match_all(
                '/[\'"]([a-zA-Z0-9_]+)[\'"]\s*=>\s*"((?:[^"\\\\]|\\\\.)*)"/s',
                $content,
                $double,
                PREG_SET_ORDER,
            );

            foreach (array_merge($single, $double) as $m) {
                if (preg_match(self::MARKUP_PATTERN, (string) $m[2]) === 1) {
                    $keys['legacy/'.$group.'.'.$m[1]] = true;
                }
            }
        }

        return $this->markupKeys = $keys;
    }

    /**
     * @return list<string>
     */
    private function phpFiles(string $dir): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getPathname(), '.php')) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
