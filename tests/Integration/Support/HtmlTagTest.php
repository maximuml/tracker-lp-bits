<?php

namespace Tests\Integration\Support;

use App\Support\Html;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
class HtmlTagTest extends TestCase
{
    public function test_key_shortcut_default_args(): void
    {
        $this->assertSame(
            "\n<script type=\"text/javascript\">\n//<![CDATA[\nvar maxpage=1;\nvar currentpage=1;\n//]]>\n</script>\n",
            Html::keyShortcutScript(),
        );
    }

    public function test_key_shortcut_emits_max_before_current(): void
    {
        // Legacy quirk: the JS block declares `maxpage` BEFORE
        // `currentpage`, even though the function parameters are
        // `$page` first then `$pages`. `pic/key_shortcut.js` depends
        // on this order — preserved verbatim.
        $expected = "\n<script type=\"text/javascript\">\n//<![CDATA[\nvar maxpage=10;\nvar currentpage=3;\n//]]>\n</script>\n";
        $this->assertSame($expected, Html::keyShortcutScript(3, 10));
    }

    public function test_key_shortcut_zero_pages(): void
    {
        $expected = "\n<script type=\"text/javascript\">\n//<![CDATA[\nvar maxpage=0;\nvar currentpage=0;\n//]]>\n</script>\n";
        $this->assertSame($expected, Html::keyShortcutScript(0, 0));
    }

    public function test_promotion_select_options_emits_all_seven_when_hide_is_zero(): void
    {
        $expected = '<option value="1">Normal</option>'
            .'<option value="2">Free</option>'
            .'<option value="3">2× Up</option>'
            .'<option value="4">Free 2× Up</option>'
            .'<option value="5">50% Down</option>'
            .'<option value="6">50% Down 2× Up</option>'
            .'<option value="7">30% Down</option>';
        $this->assertSame($expected, Html::promotionSelectOptions(0, 0, self::promoLabels())->toHtml());
    }

    public function test_promotion_select_options_marks_selected(): void
    {
        $result = Html::promotionSelectOptions(3, 0, self::promoLabels());
        $this->assertStringContainsString('<option value="3" selected="selected">2× Up</option>', $result);
        // Only the requested id gets the selected attribute.
        $this->assertStringContainsString('<option value="1">Normal</option>', $result);
        $this->assertStringContainsString('<option value="7">30% Down</option>', $result);
    }

    public function test_promotion_select_options_hides_requested_id(): void
    {
        // Legacy contract: `$hide` removes a single option entirely
        // from the emitted list (call sites pass the current torrent
        // promotion type to remove "becomes X again" from the menu).
        $result = Html::promotionSelectOptions(0, 1, self::promoLabels());
        $this->assertStringNotContainsString('value="1"', $result);
        $this->assertStringContainsString('value="2"', $result);
        $this->assertStringContainsString('value="7"', $result);
    }

    public function test_promotion_select_options_hide_zero_keeps_all(): void
    {
        // `$hide = 0` matches no real id, so every option survives.
        $result = Html::promotionSelectOptions(0, 0, self::promoLabels());
        for ($id = 1; $id <= 7; $id++) {
            $this->assertStringContainsString('value="'.$id.'"', $result);
        }
    }

    public function test_promotion_select_options_hide_out_of_range_keeps_all(): void
    {
        $result = Html::promotionSelectOptions(0, 99, self::promoLabels());
        for ($id = 1; $id <= 7; $id++) {
            $this->assertStringContainsString('value="'.$id.'"', $result);
        }
    }

    public function test_promotion_select_options_missing_labels_degrade_to_empty(): void
    {
        // If a translation is absent the proxy `?? ''` coercion lands
        // an empty string in the helper — we render the `<option>`
        // with no label text rather than crashing.
        $result = Html::promotionSelectOptions(0, 0, []);
        $this->assertStringContainsString('<option value="1"></option>', $result);
        $this->assertStringContainsString('<option value="7"></option>', $result);
    }

    public function test_promotion_select_options_escapes_labels(): void
    {
        $result = Html::promotionSelectOptions(0, 0, ['normal' => '<b>bold</b>'] + self::promoLabels());
        $this->assertStringContainsString('<option value="1">&lt;b&gt;bold&lt;/b&gt;</option>', $result);
    }

    public function test_settings_row_default_escapes_and_replaces_newlines(): void
    {
        // Legacy `tr()` default branch: htmlspecialchars + `\n` → `<br />\n`.
        $this->assertSame(
            '<tr><td class="whitespace-nowrap align-top px-2.5 py-1.5 text-right font-semibold text-nxm-text-dim">Label</td>'
            .'<td class="align-top px-2.5 py-1.5">a &amp; b<br />'."\n".'c</td></tr>',
            Html::settingsRow('Label', "a & b\nc"),
        );
    }

    public function test_settings_row_with_escape_disabled_emits_value_verbatim(): void
    {
        // Legacy `tr(..., $noesc=1)`: caller already produced HTML
        // (radio buttons, `<input>` markup). Pass-through with no
        // escape and no `<br />` substitution.
        $this->assertSame(
            '<tr><td class="whitespace-nowrap align-top px-2.5 py-1.5 text-right font-semibold text-nxm-text-dim">L</td>'
            .'<td class="align-top px-2.5 py-1.5"><input name="x" value="y"/></td></tr>',
            Html::settingsRow('L', '<input name="x" value="y"/>', escape: false),
        );
    }

    public function test_settings_row_head_is_never_escaped(): void
    {
        // Legacy quirk: `$x` is interpolated raw. Call sites pass
        // lang strings that may contain a literal `<font color>` or
        // `&nbsp;` — those must reach the browser unescaped.
        $this->assertSame(
            '<tr><td class="whitespace-nowrap align-top px-2.5 py-1.5 text-right font-semibold text-nxm-text-dim">Name<font color="red">*</font></td>'
            .'<td class="align-top px-2.5 py-1.5">value</td></tr>',
            Html::settingsRow('Name<font color="red">*</font>', 'value', escape: false),
        );
    }

    public function test_settings_row_relation_emits_double_attribute(): void
    {
        // Legacy quirk: a non-empty `$relation` emits BOTH
        // `relation="X"` AND `class="X"` on the `<tr>`. The dual
        // attribute drives row-show/hide JS in `settings.php`.
        $this->assertSame(
            '<tr relation="mode_1" class="mode_1"><td class="whitespace-nowrap align-top px-2.5 py-1.5 text-right font-semibold text-nxm-text-dim">L</td>'
            .'<td class="align-top px-2.5 py-1.5">v</td></tr>',
            Html::settingsRow('L', 'v', relation: 'mode_1'),
        );
    }

    public function test_settings_row_empty_relation_omits_attribute(): void
    {
        $this->assertStringStartsWith('<tr><td', Html::settingsRow('L', 'v', relation: ''));
    }

    public function test_settings_row_has_no_trailing_newline(): void
    {
        // Legacy `tr()` produces no trailing newline — call sites
        // concatenate rows directly (`$html .= tr(...)`).
        $row = Html::settingsRow('L', 'v');
        $this->assertStringEndsWith('</tr>', $row);
        $this->assertStringNotContainsString("</tr>\n", $row);
    }

    public function test_settings_row_newline_replacement_only_applies_when_escaping(): void
    {
        // Pair test: with $escape=false the `\n` must survive verbatim
        // (the caller is shipping pre-built HTML and presumably means
        // those newlines). With $escape=true newlines become `<br />\n`.
        $withEscape = Html::settingsRow('L', "a\nb", escape: true);
        $this->assertStringContainsString('<br />'."\n".'b', $withEscape);

        $noEscape = Html::settingsRow('L', "a\nb", escape: false);
        $this->assertStringContainsString("a\nb", $noEscape);
        $this->assertStringNotContainsString('<br />', $noEscape);
    }

    public function test_tooltip_container_single_child(): void
    {
        $this->assertSame(
            '<div class="nx-hidden"><div id="lastpost-1">Post body</div></div>',
            Html::tooltipContainer([['id' => 'lastpost-1', 'content' => 'Post body']]),
        );
    }

    public function test_tooltip_container_multiple_children_preserved_in_order(): void
    {
        $items = [
            ['id' => 'a', 'content' => '<b>first</b>'],
            ['id' => 'b', 'content' => '<i>second</i>'],
            ['id' => 'c', 'content' => 'third'],
        ];

        $this->assertSame(
            '<div class="nx-hidden"><div id="a"><b>first</b></div><div id="b"><i>second</i></div><div id="c">third</div></div>',
            Html::tooltipContainer($items),
        );
    }

    public function test_tooltip_container_escapes_id_but_preserves_content(): void
    {
        $this->assertSame(
            '<div class="nx-hidden"><div id="row &amp; 1"><table><tr><td>raw</td></tr></table></div></div>',
            Html::tooltipContainer([
                ['id' => 'row & 1', 'content' => '<table><tr><td>raw</td></tr></table>'],
            ]),
        );
    }

    public function test_tooltip_container_missing_keys_degrade_to_empty_strings(): void
    {
        $this->assertSame(
            '<div class="nx-hidden"><div id=""></div></div>',
            Html::tooltipContainer([[]]),
        );
    }

    public function test_tooltip_container_accepts_iterators(): void
    {
        $generator = (function () {
            yield ['id' => 'x', 'content' => '1'];
            yield ['id' => 'y', 'content' => '2'];
        })();

        $this->assertSame(
            '<div class="nx-hidden"><div id="x">1</div><div id="y">2</div></div>',
            Html::tooltipContainer($generator),
        );
    }

    private static function promoLabels(): array
    {
        return [
            'normal' => 'Normal',
            'free' => 'Free',
            'two_times_up' => '2× Up',
            'free_two_times_up' => 'Free 2× Up',
            'half_down' => '50% Down',
            'half_down_two_up' => '50% Down 2× Up',
            'thirty_percent_down' => '30% Down',
        ];
    }
}
