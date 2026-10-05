<?php

namespace Tests\Integration\Support;

use App\Support\Smilies;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class SmiliesTest extends TestCase
{
    // ---------- link ----------

    public function test_link_emits_csp_safe_data_attribute_anchor(): void
    {
        // Inline javascript: URLs and on*= handlers are blocked by the
        // nonce-strict CSP — the link carries data-* attributes that the
        // delegated listeners in public/js/common.js dispatch to SmileIT();
        // the tooltip renders via js/nx-tooltip.js from the inline
        // <template> (no HTML strings cross an attribute boundary).
        $expected = '<a href="#" data-smile="[em4]"'
            .' data-smile-form="myform"'
            .' data-smile-text="myta"'
            .' data-domtt-content>'
            .'<img class="nx-smiley" src="pic/smilies/4.gif" alt="[em4]" />'
            .'<template class="nx-tt"><img src="pic/smilies/4.gif" alt="" /></template></a>';
        $this->assertSame($expected, (string) Smilies::link('myform', 'myta', 4));
    }

    public function test_link_escapes_formname_and_taname(): void
    {
        // form/ta names land in quoted data attributes — quotes must be
        // escaped so they cannot break out of the attribute.
        $result = Smilies::link("o'malley", "ta'name", 1);
        $this->assertStringContainsString('data-smile-form="o&#039;malley"', $result);
        $this->assertStringContainsString('data-smile-text="ta&#039;name"', $result);
    }

    public function test_link_uses_smily_number_in_three_places(): void
    {
        $result = Smilies::link('f', 't', 42);
        $this->assertStringContainsString('[em42]', $result);
        $this->assertStringContainsString('pic/smilies/42.gif', $result);
        // Two `pic/smilies/N.gif` occurrences — one in the tooltip body
        // (HTML-escaped) and one in the visible <img src>.
        $this->assertSame(2, substr_count($result, 'pic/smilies/42.gif'));
    }

    // ---------- quickRow ----------

    public function test_quick_row_wraps_in_centered_div(): void
    {
        $result = Smilies::quickRow('myform', 'myta');
        $this->assertStringStartsWith('<div class="text-center">', $result);
        $this->assertStringEndsWith('</div>', $result);
    }

    public function test_quick_row_emits_seventeen_links_in_legacy_order(): void
    {
        // Pinned order from the original `smile_row()` array literal —
        // existing pages depend on this exact sequence.
        $expectedNumbers = [4, 5, 39, 25, 11, 8, 10, 15, 27, 57, 42, 122, 52, 28, 29, 30, 176];
        $result = Smilies::quickRow('f', 't');

        $matches = [];
        preg_match_all('/data-smile="\[em(\d+)\]"/', $result, $matches);
        $this->assertSame(array_map('strval', $expectedNumbers), $matches[1]);
    }

    public function test_quick_row_passes_form_and_taname_to_each_link(): void
    {
        $result = Smilies::quickRow('formX', 'taX');
        // 17 links × 1 data attribute pair each.
        $this->assertSame(17, substr_count($result, 'data-smile-form="formX" data-smile-text="taX"'));
    }
}
