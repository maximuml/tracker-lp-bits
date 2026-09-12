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
}
