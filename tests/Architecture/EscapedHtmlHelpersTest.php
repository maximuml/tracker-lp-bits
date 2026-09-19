<?php

declare(strict_types=1);

namespace Tests\Architecture;

use Illuminate\Contracts\Support\Htmlable;
use PHPUnit\Framework\TestCase;
use Tests\Attributes\TestCategory;

/**
 * Stage-1 ratchet: HTML-producing helpers must not render through `{{ }}`
 * unless they return an {@see Htmlable} (SafeHtml/HtmlString) value.
 *
 * `{{ }}` escapes plain strings — a helper that returns raw HTML as a
 * `string` silently turns into visible `&lt;tag&gt;` text (the Stage-0
 * bug class). Helpers returning `SafeHtml`/`HtmlString` are exempt by
 * design: `e()` calls `toHtml()` on Htmlable instead of escaping, which
 * is exactly why `Frame::*`, `UserDisplay::username()`,
 * `Format::formatComment()`, `Comment::format()`, `PageLayout::*Html()`,
 * `TorrentTable::render()`, `Ratio::*`, `UserClass::*` and
 * `Smilies::link()` are legitimate inside `{{ }}` today.
 *
 * The check is reflection-driven, so a signature regression
 * (`SafeHtml` → `string`) on any of those helpers — or a brand-new
 * string-returning HTML helper — fails the suite without editing this
 * file. `Html::*`, `Time::format()` and `UserDisplay::avatarImage*()`
 * return raw HTML strings and can never appear under `{{ }}`.
 *
 * Markup-bearing legacy language keys under `{{ }}` are pinned by
 * {@see LegacyLangMarkupTest} — not duplicated here.
 */
#[TestCategory(TestCategory::ARCHITECTURE)]
final class EscapedHtmlHelpersTest extends TestCase
{
    private const BASE_DIR = __DIR__.'/../..';

    private const VIEWS_DIR = self::BASE_DIR.'/resources/views';

    /**
     * `App\Support` helpers proven to return markup-free plain text —
     * the only `string`-returning calls allowed inside `{{ }}`.
     *
     * Add an entry only when the value can never contain markup
     * (numbers, dates, URLs, colour codes, translations — lang keys
     * carrying markup are still forbidden by LegacyLangMarkupTest).
     *
     * @var array<string, true>
     */
    private const PLAIN_TEXT_ALLOWLIST = [
        'Format::size' => true,
        'Format::prettyTimeWithLocale' => true,
        'Time::formatDateTime' => true,
        'Ratio::color' => true,
        'UserClass::imagePath' => true,
        'UserDisplay::plainUsername' => true,
        'Style::cssUriWithContext' => true,
        'Url::schemeAndHost' => true,
        'Http::protocolPrefix' => true,
        'Strings::addS' => true,
        'Locale::trans' => true,
    ];

    /**
     * PHP types that cannot carry HTML markup.
     *
     * @var list<string>
     */
    private const SAFE_SCALAR_TYPES = ['int', 'float', 'bool', 'array', 'void', 'iterable', 'false', 'true', 'null'];

    /**
     * `{{ e(...) }}`/`{{ htmlspecialchars(...) }}` applies escaping on top
     * of Blade's own `e()` — a double-escape producing visible entities.
     */
    public function test_no_escaping_wrapper_inside_escaped_echo(): void
    {
        $offenders = [];

        foreach ($this->phpFiles(self::VIEWS_DIR) as $path) {
            $content = (string) file_get_contents($path);
            $relative = substr($path, strlen(self::BASE_DIR) + 1);

            if (preg_match_all('/\{\{\s*(e|htmlspecialchars|htmlentities)\s*\(/', $content, $m, PREG_OFFSET_CAPTURE) === 0) {
                continue;
            }

            foreach ($m[0] as [$text, $offset]) {
                $offenders[] = sprintf('%s:%d: %s', $relative, substr_count($content, "\n", 0, $offset) + 1, trim($text));
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Escaping helpers called inside {{ }} — double-escaped output.\n"
            .implode("\n", $offenders)
            ."\nRemove the wrapper; escape once — or wrap in SafeHtml if the value is markup.",
        );
    }

    /**
     * Every `App\Support\X::method()` call inside `{{ }}` must resolve to
     * a method whose declared return type is `Htmlable` (SafeHtml etc.),
     * a markup-free scalar — or be explicitly allowlisted as plain text.
     */
    public function test_no_raw_string_helper_calls_in_escaped_echo(): void
    {
        $offenders = [];

        foreach ($this->phpFiles(self::VIEWS_DIR) as $path) {
            $content = (string) file_get_contents($path);
            $relative = substr($path, strlen(self::BASE_DIR) + 1);

            if (preg_match_all('/\{\{(.*?)\}\}/s', $content, $blocks) === 0) {
                continue;
            }

            foreach ($blocks[1] as $expr) {
                if (preg_match_all(
                    '/(?<![A-Za-z0-9_\\\\])(\\\\?App\\\\Support\\\\[A-Za-z_][A-Za-z0-9_\\\\]*|[A-Z][A-Za-z0-9_]*)::([A-Za-z_][A-Za-z0-9_]*)\s*\(/',
                    $expr,
                    $calls,
                    PREG_SET_ORDER,
                ) === 0) {
                    continue;
                }

                foreach ($calls as $call) {
                    $class = ltrim($call[1], '\\');
                    if (! str_starts_with($class, 'App\\Support\\')) {
                        $class = 'App\\Support\\'.$class;
                    }
                    $method = $call[2];

                    $verdict = $this->verdict($class, $method);
                    if ($verdict !== null) {
                        $offenders[] = sprintf('%s: {{ %s::%s(...) }} — %s', $relative, $call[1], $method, $verdict);
                    }
                }
            }
        }

        $this->assertSame(
            [],
            array_values(array_unique($offenders)),
            "Raw-string helper calls inside {{ }} would render as visible escaped markup.\n"
            .implode("\n", array_slice(array_values(array_unique($offenders)), 0, 40))
            ."\nReturn SafeHtml/Htmlable, use <x-*> components, or prove the value is plain "
            .'text by adding it to PLAIN_TEXT_ALLOWLIST.',
        );
    }

    /**
     * Why a call is forbidden, or null when it is safe.
     */
    private function verdict(string $class, string $method): ?string
    {
        $short = ($pos = strrpos($class, '\\')) !== false ? substr($class, $pos + 1) : $class;

        if (! class_exists($class)) {
            return null; // Not a Support helper (model, facade, enum…) — out of scope.
        }
        if (! method_exists($class, $method)) {
            return sprintf('%s::%s does not exist — cannot prove it is safe under {{ }}', $class, $method);
        }

        if (isset(self::PLAIN_TEXT_ALLOWLIST[$short.'::'.$method])) {
            return null;
        }

        $type = (new \ReflectionMethod($class, $method))->getReturnType();

        if ($type === null) {
            return sprintf('%s::%s has no return type — may return raw HTML', $short, $method);
        }

        if ($type instanceof \ReflectionNamedType) {
            $name = $type->getName();

            if (in_array($name, self::SAFE_SCALAR_TYPES, true)) {
                return null;
            }
            if ($name === 'string' || $name === 'mixed') {
                return sprintf('%s::%s returns %s — raw HTML under {{ }} is escaped to visible text', $short, $method, $name);
            }
            if (is_subclass_of($name, Htmlable::class, true) || $name === Htmlable::class) {
                return null;
            }
            if (class_exists($name) || interface_exists($name)) {
                return sprintf('%s::%s returns %s — not Htmlable; prove plain text or return SafeHtml', $short, $method, $name);
            }

            return null;
        }

        // Union/intersection types: allow only if every member is safe.
        if ($type instanceof \ReflectionUnionType) {
            foreach ($type->getTypes() as $member) {
                $name = $member->getName();
                if ($name === 'string' || $name === 'mixed') {
                    return sprintf('%s::%s returns a union containing %s — may carry raw HTML', $short, $method, $name);
                }
            }

            return null;
        }

        return sprintf('%s::%s has a complex return type — cannot prove it is safe', $short, $method);
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
