<?php

namespace Tests\Integration\Support;

use App\Support\Strings;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
class StringsRenderTest extends TestCase
{
    public function test_hidden_wraps_in_span(): void
    {
        $this->assertSame('<span class="hidden-text">1.2.3.4</span>', (string) Strings::hidden('1.2.3.4'));
        $this->assertSame('<span class="hidden-text"></span>', (string) Strings::hidden(''));
    }

    public function test_hidden_does_not_escape_input(): void
    {
        // Pinned legacy contract: hide_text() does not escape — every
        // existing call site already passes pre-escaped or
        // application-controlled text. A "safe" refactor that
        // wraps the input in htmlspecialchars() would double-escape
        // every existing call site.
        $this->assertSame(
            '<span class="hidden-text"><b>raw</b></span>',
            (string) Strings::hidden('<b>raw</b>'),
        );
    }

    public function test_highlight_wraps_single_match(): void
    {
        $this->assertSame(
            'before <b><span class="striking">match</span></b> after',
            Strings::highlight('match', 'before match after'),
        );
    }

    public function test_highlight_is_case_insensitive_but_preserves_matched_case(): void
    {
        $this->assertSame(
            'a <b><span class="striking">Foo</span></b> b <b><span class="striking">FOO</span></b> c',
            Strings::highlight('foo', 'a Foo b FOO c'),
        );
    }

    public function test_highlight_empty_needle_returns_subject_unchanged(): void
    {
        $this->assertSame('unchanged', Strings::highlight('', 'unchanged'));
    }

    public function test_highlight_no_match_returns_subject_unchanged(): void
    {
        $this->assertSame('nothing here', Strings::highlight('xyz', 'nothing here'));
    }

    public function test_highlight_double_wraps_repeated_matches_legacy_quirk(): void
    {
        // Legacy quirk: each `stristr` iteration runs `str_replace` on
        // the current `$subject`, which already contains the wrapper
        // from the previous iteration. Two matches → two passes → the
        // first wrapper gets re-wrapped. A "safe" refactor would emit
        // each match wrapped only once, but call sites have been
        // rendering this nested HTML for years and we keep it.
        $this->assertSame(
            'a<b><span class="striking"><b><span class="striking">x</span></b></span></b>'
                .'b<b><span class="striking"><b><span class="striking">x</span></b></span></b>c',
            Strings::highlight('x', 'axbxc'),
        );
    }

    public function test_highlight_does_not_treat_needle_as_regex(): void
    {
        // Legacy contract: needle is a literal substring. A regex
        // metacharacter survives intact.
        $this->assertSame(
            'before <b><span class="striking">a.b</span></b> after',
            Strings::highlight('a.b', 'before a.b after'),
        );
    }
}
