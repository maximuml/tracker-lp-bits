<?php

declare(strict_types=1);

namespace Tests\Feature\View;

use App\Support\Html\SafeHtml;
use App\View\Components\FormField;
use App\View\Components\Pagination;
use Illuminate\Support\Facades\Blade;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * W7-01 component layer: every component in resources/views/components
 * is covered for output escaping and required ARIA/semantic attributes.
 */
#[TestCategory(TestCategory::HTTP_FEATURE)]
final class ComponentLayerTest extends TestCase
{
    /** @param array<string, mixed> $data */
    private function render(string $template, array $data = []): string
    {
        return Blade::render($template, $data);
    }

    // --- x-page-header ---------------------------------------------------

    public function test_page_header_escapes_plain_title(): void
    {
        $html = $this->render('<x-page-header title="A <b>bold</b> title" />');

        $this->assertStringContainsString('A &lt;b&gt;bold&lt;/b&gt; title', $html);
        $this->assertStringNotContainsString('<b>bold</b>', $html);
        $this->assertStringContainsString('nx-page-header__title', $html);
    }

    public function test_page_header_renders_trusted_html_title(): void
    {
        $title = SafeHtml::fromTrustedHtml('User <a href="/user/1">alice</a>');
        $html = $this->render('<x-page-header :title="$title" />', ['title' => $title]);

        $this->assertStringContainsString('<a href="/user/1">alice</a>', $html);
    }

    // --- x-alert ---------------------------------------------------------

    public function test_alert_escapes_title_and_uses_status_role_for_info(): void
    {
        $html = $this->render('<x-alert type="info" :title="$t">Body text</x-alert>', ['t' => '<i>x</i>']);

        $this->assertStringContainsString('role="status"', $html);
        $this->assertStringContainsString('&lt;i&gt;x&lt;/i&gt;', $html);
        $this->assertStringContainsString('Body text', $html);
    }

    public function test_alert_uses_assertive_role_for_error_and_warning(): void
    {
        $this->assertStringContainsString('role="alert"', $this->render('<x-alert type="error">E</x-alert>'));
        $this->assertStringContainsString('role="alert"', $this->render('<x-alert type="warning">W</x-alert>'));
        $this->assertStringContainsString('role="status"', $this->render('<x-alert type="success">S</x-alert>'));
    }

    // --- x-form-field ----------------------------------------------------

    public function test_form_field_pairs_label_input_and_describedby(): void
    {
        $html = $this->render(
            '<x-form-field label="Email" name="email" type="email" :required="true" error="Invalid" help="We never share" />'
        );

        $this->assertStringContainsString('for="email"', $html);
        $this->assertStringContainsString('id="email"', $html);
        $this->assertStringContainsString('name="email"', $html);
        $this->assertStringContainsString('required', $html);
        $this->assertStringContainsString('aria-invalid="true"', $html);
        $this->assertStringContainsString('aria-describedby="email-error email-help"', $html);
        $this->assertStringContainsString('id="email-error"', $html);
        $this->assertStringContainsString('id="email-help"', $html);
        $this->assertStringContainsString('aria-hidden="true"', $html);
    }

    public function test_form_field_escapes_label_value_and_error(): void
    {
        $html = $this->render(
            '<x-form-field :label="$l" name="f" :value="$v" :error="$e" />',
            ['l' => 'L<b>', 'v' => '"><script>', 'e' => '<b>bad</b>'],
        );

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;b&gt;bad&lt;/b&gt;', $html);
        $this->assertStringContainsString('value="&quot;&gt;&lt;script&gt;', $html);
    }

    public function test_form_field_class_state(): void
    {
        $f = new FormField(label: 'L', name: 'email', error: 'bad', help: 'hint');
        $this->assertSame('email', $f->fieldId);
        $this->assertTrue($f->hasError);
        $this->assertTrue($f->hasHelp);
        $this->assertSame(['email-error', 'email-help'], $f->describedBy);

        $f = new FormField(label: 'L', name: 'n', id: 'custom', error: 'e');
        $this->assertSame('custom', $f->fieldId);
        $this->assertFalse($f->hasHelp);
        $this->assertSame(['custom-error'], $f->describedBy);

        $f = new FormField(label: 'L', name: 'n');
        $this->assertFalse($f->hasError);
        $this->assertSame([], $f->describedBy);

        $f = new FormField(label: 'L', name: 'n', error: '', help: '');
        $this->assertFalse($f->hasError);
        $this->assertFalse($f->hasHelp);
    }

    public function test_pagination_url_template(): void
    {
        $p = new Pagination(page: 2, pages: 5, href: '/t?a=1&page={page}');

        $this->assertSame('/t?a=1&page=3', $p->url(3));
        $this->assertSame('/t?a=1&page=10', $p->url(10));
    }

    // --- x-button --------------------------------------------------------

    public function test_button_renders_button_and_link_variants(): void
    {
        $html = $this->render('<x-button type="submit" variant="primary">Save</x-button>');
        $this->assertStringContainsString('<button', $html);
        $this->assertStringContainsString('type="submit"', $html);
        $this->assertStringContainsString('nx-btn--primary', $html);

        $html = $this->render('<x-button href="/next">Go</x-button>');
        $this->assertStringContainsString('<a', $html);
        $this->assertStringContainsString('href="/next"', $html);
    }

    // --- x-data-table ----------------------------------------------------

    public function test_data_table_renders_caption_and_scoped_headers(): void
    {
        $html = $this->render(
            '<x-data-table caption="Peers" :headers="$h"><tr><td>x</td></tr></x-data-table>',
            ['h' => ['Name', '<Size>']],
        );

        $this->assertStringContainsString('<caption', $html);
        $this->assertStringContainsString('Peers', $html);
        $this->assertStringContainsString('scope="col"', $html);
        $this->assertStringContainsString('&lt;Size&gt;', $html);
        $this->assertStringContainsString('<td>x</td>', $html);
    }

    // --- x-pagination ----------------------------------------------------

    public function test_pagination_marks_current_page_and_nav_landmark(): void
    {
        $html = $this->render('<x-pagination :page="2" :pages="5" href="/list?page={page}" />');

        $this->assertStringContainsString('<nav', $html);
        $this->assertStringContainsString('aria-label="Pagination"', $html);
        $this->assertStringContainsString('aria-current="page"', $html);
        $this->assertStringContainsString('rel="prev"', $html);
        $this->assertStringContainsString('rel="next"', $html);
        $this->assertStringContainsString('href="/list?page=1"', $html);
        $this->assertStringContainsString('href="/list?page=3"', $html);
    }

    public function test_pagination_renders_nothing_for_single_page(): void
    {
        $html = $this->render('<x-pagination :page="1" :pages="1" href="/list?page={page}" />');

        $this->assertStringNotContainsString('nx-pagination__list', $html);
    }

    public function test_pagination_window_class_logic(): void
    {
        $this->assertSame([1], Pagination::window(1, 1));
        $this->assertSame([1, 2, 3, 4, 5], Pagination::window(3, 5));
        $this->assertSame([1, '…', 8, 9, 10, 11, 12, '…', 20], Pagination::window(10, 20));
        $this->assertSame([1, 2, 3, '…', 20], Pagination::window(1, 20));
        $this->assertSame([], Pagination::window(1, 0));
    }

    // --- x-tabs ----------------------------------------------------------

    public function test_tabs_mark_active_link_with_aria_current(): void
    {
        $html = $this->render(
            '<x-tabs :tabs="$tabs" active="b" label="Torrent sections" />',
            ['tabs' => [
                ['id' => 'a', 'label' => 'First', 'url' => '/t?sec=a'],
                ['id' => 'b', 'label' => 'Sec<b>', 'url' => '/t?sec=b'],
            ]],
        );

        $this->assertStringContainsString('<nav', $html);
        $this->assertStringContainsString('aria-label="Torrent sections"', $html);
        $this->assertStringContainsString('aria-current="page"', $html);
        $this->assertStringContainsString('Sec&lt;b&gt;', $html);
    }

    // --- x-modal ---------------------------------------------------------

    public function test_modal_has_dialog_semantics_and_labelled_close(): void
    {
        $html = $this->render('<x-modal id="dlg" title="Confirm">Body</x-modal>');

        $this->assertStringContainsString('role="dialog"', $html);
        $this->assertStringContainsString('aria-modal="true"', $html);
        $this->assertStringContainsString('aria-labelledby="dlg-title"', $html);
        $this->assertStringContainsString('id="dlg-title"', $html);
        $this->assertStringContainsString('aria-label="Close"', $html);
        $this->assertStringContainsString('hidden', $html);
    }

    // --- x-empty-state ---------------------------------------------------

    public function test_empty_state_renders_title_and_escapes(): void
    {
        $html = $this->render('<x-empty-state :title="$t" description="try later" />', ['t' => 'No <b>results</b>']);

        $this->assertStringContainsString('No &lt;b&gt;results&lt;/b&gt;', $html);
        $this->assertStringContainsString('try later', $html);
    }

    // --- x-user-badge ----------------------------------------------------

    public function test_user_badge_escapes_username_and_applies_level(): void
    {
        $html = $this->render('<x-user-badge :username="$u" level="staff" />', ['u' => 'ad<min>']);

        $this->assertStringContainsString('ad&lt;min&gt;', $html);
        $this->assertStringContainsString('nx-user-badge--staff', $html);
    }

    public function test_user_badge_links_when_href_given(): void
    {
        $html = $this->render('<x-user-badge username="bob" href="/user/2" />');

        $this->assertStringContainsString('href="/user/2"', $html);
        $this->assertStringContainsString('>bob</a>', $html);
    }

    // --- x-torrent-badge -------------------------------------------------

    public function test_torrent_badge_renders_type_modifier(): void
    {
        $html = $this->render('<x-torrent-badge type="sticky">Sticky</x-torrent-badge>');

        $this->assertStringContainsString('nx-tbadge--sticky', $html);
        $this->assertStringContainsString('Sticky', $html);
    }

    // --- settings row components (W7-02) ---------------------------------

    public function test_settings_row_escapes_label_and_keeps_legacy_classes(): void
    {
        $html = $this->render('<x-settings-row :label="$l"><input name="x"></x-settings-row>', ['l' => 'Row <b>x</b>']);

        $this->assertStringContainsString('class="rowhead nowrap"', $html);
        $this->assertStringContainsString('class="rowfollow"', $html);
        $this->assertStringContainsString('Row &lt;b&gt;x&lt;/b&gt;', $html);
        $this->assertStringContainsString('<input name="x">', $html);
    }

    public function test_settings_yesno_marks_checked_and_pairs_labels(): void
    {
        $html = $this->render(
            '<x-settings-yesno label="Feat" name="flag" value="yes" :note="$n" yes-label="Da" no-label="Net" />',
            ['n' => 'n<b>'],
        );

        $this->assertStringContainsString('id="flagyes"', $html);
        $this->assertStringContainsString('for="flagyes"', $html);
        $this->assertStringContainsString('id="flagno"', $html);
        $this->assertStringContainsString('for="flagno"', $html);
        $this->assertStringContainsString('Da', $html);
        $this->assertStringContainsString('n&lt;b&gt;', $html);
        $this->assertMatchesRegularExpression('/id="flagyes"[^>]*checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/id="flagno"[^>]*checked/', $html);
    }

    public function test_settings_yesno_renders_trusted_html_note(): void
    {
        $html = $this->render(
            '<x-settings-yesno label="F" name="flag" value="yes" :note="$n" />',
            ['n' => SafeHtml::fromTrustedHtml('see <a href="/faq">FAQ</a>')],
        );

        $this->assertStringContainsString('<a href="/faq">FAQ</a>', $html);
    }

    public function test_settings_yesno_checks_no_when_value_is_no(): void
    {
        $html = $this->render('<x-settings-yesno label="F" name="flag" value="no" />');

        $this->assertMatchesRegularExpression('/id="flagno"[^>]*checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/id="flagyes"[^>]*checked/', $html);
    }

    public function test_settings_text_escapes_value_and_renders_width(): void
    {
        $html = $this->render(
            '<x-settings-text label="T" :name="$n" :value="$v" note="hint" width="50px" />',
            ['n' => 'f"><b>', 'v' => '"><script>'],
        );

        $this->assertStringContainsString('width: 50px', $html);
        $this->assertStringContainsString('hint', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('value="&quot;&gt;&lt;script&gt;', $html);
    }

    public function test_settings_radios_marks_selected_and_escapes(): void
    {
        $html = $this->render(
            '<x-settings-radios label="V" name="ver" :options="$o" selected="admin" :note="$n" />',
            ['o' => ['email' => 'E<b>', 'admin' => 'Admin'], 'n' => 'note'],
        );

        $this->assertStringContainsString('value="admin" checked', $html);
        $this->assertStringContainsString('E&lt;b&gt;', $html);
        $this->assertStringContainsString('note', $html);
        $this->assertDoesNotMatchRegularExpression('/value="email"[^>]*checked/', $html);
    }

    public function test_settings_radios_supports_disabled_array_options(): void
    {
        $html = $this->render(
            '<x-settings-radios label="L" name="lang" :options="$o" selected="en" />',
            ['o' => [
                ['value' => 'en', 'label' => 'English', 'disabled' => false],
                ['value' => 'ru', 'label' => 'Russian', 'disabled' => true],
            ]],
        );

        $this->assertMatchesRegularExpression('/value="en"[^>]*checked/', $html);
        $this->assertMatchesRegularExpression('/value="ru"[^>]*disabled/', $html);
        $this->assertStringContainsString('Russian', $html);
    }

    public function test_settings_select_marks_selected_and_escapes(): void
    {
        $html = $this->render(
            '<x-settings-select label="S" name="css" :options="$o" selected="2" note="pick" />',
            ['o' => ['1' => 'A<', '2' => 'B']],
        );

        $this->assertStringContainsString('value="2" selected', $html);
        $this->assertStringContainsString('A&lt;', $html);
        $this->assertStringContainsString('pick', $html);
        $this->assertDoesNotMatchRegularExpression('/value="1"[^>]*selected/', $html);
    }

    public function test_settings_checkboxes_marks_checked_and_disabled(): void
    {
        $html = $this->render(
            '<x-settings-checkboxes label="L" name="l[]" :options="$o" />',
            ['o' => [
                ['value' => 'en', 'label' => 'En', 'checked' => true],
                ['value' => 'ru', 'label' => 'Ru<', 'checked' => false, 'disabled' => true],
            ]],
        );

        $this->assertMatchesRegularExpression('/value="en"[^>]*checked/', $html);
        $this->assertMatchesRegularExpression('/value="ru"[^>]*disabled/', $html);
        $this->assertDoesNotMatchRegularExpression('/value="ru"[^>]*checked/', $html);
        $this->assertStringContainsString('Ru&lt;', $html);
    }

    public function test_settings_save_renders_submit(): void
    {
        $html = $this->render('<x-settings-save :label="$l" text="Save now" />', ['l' => 'S<b>']);

        $this->assertStringContainsString('type="submit"', $html);
        $this->assertStringContainsString('name="save"', $html);
        $this->assertStringContainsString('value="Save now"', $html);
        $this->assertStringContainsString('S&lt;b&gt;', $html);
    }

    // --- settings/index integration ---------------------------------------

    public function test_settings_main_section_renders_component_inputs(): void
    {
        $html = view('settings.index', [
            'action' => 'mainsettings',
            'lang' => [],
            'scriptName' => '/settings.php',
            'config' => ['site_online' => 'no', 'verification' => 'admin'],
            'searchboxes' => [],
            'allSiteLanguages' => [],
            'allEnabledLangs' => [],
            'stylesheets' => [],
        ])->render();

        $this->assertStringContainsString('name="site_online"', $html);
        $this->assertMatchesRegularExpression('/id="site_onlineno"[^>]*checked/', $html);
        $this->assertStringContainsString('name="verification"', $html);
        $this->assertStringContainsString('value="admin" checked', $html);
        $this->assertStringContainsString('name="save"', $html);
        $this->assertStringNotContainsString('{!!', $html);
    }

    public function test_settings_bonus_section_renders_cost_fields(): void
    {
        $html = view('settings.index', [
            'action' => 'bonussettings',
            'lang' => [],
            'scriptName' => '/settings.php',
            'config' => ['oneinvite' => 42, 'bonusgift' => 'yes'],
            'attendance_continuous' => [7 => 100],
        ])->render();

        $this->assertStringContainsString('name="oneinvite"', $html);
        $this->assertStringContainsString('value="42"', $html);
        $this->assertStringContainsString('name="attendance_continuous_day[]"', $html);
        $this->assertStringContainsString('value="7"', $html);
        $this->assertMatchesRegularExpression('/id="bonusgiftyes"[^>]*checked/', $html);
    }

    public function test_settings_smtp_hides_conditional_tbody(): void
    {
        $html = view('settings.index', [
            'action' => 'smtpsettings',
            'lang' => [],
            'scriptName' => '/settings.php',
            'config' => ['smtptype' => 'external'],
        ])->render();

        $this->assertMatchesRegularExpression('/id="smtp_advanced"[^>]*nx-hidden/', $html);
        $this->assertDoesNotMatchRegularExpression('/id="smtp_external"[^>]*nx-hidden/', $html);
        $this->assertStringContainsString('name="smtpaddress"', $html);
    }

    public function test_settings_escapes_config_values(): void
    {
        $html = view('settings.index', [
            'action' => 'basicsettings',
            'lang' => [],
            'scriptName' => '/settings.php',
            'config' => ['SITENAME' => '"><script>alert(1)</script>'],
        ])->render();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }
}
