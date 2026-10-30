<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Regression coverage for Phase 8 (a11y + i18n): localized chrome
 * strings, semantic controls, and keyboard-accessible JS surfaces.
 */
#[TestCategory(TestCategory::HTTP_FEATURE)]
final class A11yI18nChromeTest extends TestCase
{
    use DatabaseTransactions;

    public function test_notification_bell_is_a_button_not_an_anchor(): void
    {
        $user = User::factory()->create();

        $response = $this->withNexusCookie($user)->get('/web/index');
        $response->assertOk();
        $html = (string) $response->getContent();

        $this->assertStringContainsString(
            '<button type="button" id="nx-notif-bell" class="nx-notif-bell" aria-label=',
            $html,
        );
        $this->assertMatchesRegularExpression(
            '/id="nx-notif-bell"[^>]*aria-expanded="false"/',
            $html,
        );
        $this->assertStringNotContainsString('href="#" id="nx-notif-bell"', $html);
    }

    public function test_layer_options_pass_localized_labels(): void
    {
        $user = User::factory()->create();

        $response = $this->withNexusCookie($user)->get('/web/index');
        $html = (string) $response->getContent();

        $this->assertStringContainsString('window.nexusLayerOptions', $html);
        $this->assertMatchesRegularExpression(
            '/close:\s*"Close"/',
            $html,
            'Localized close label must reach the layer JS config',
        );
        $this->assertMatchesRegularExpression(
            '/loading:\s*"Loading\.\.\."/',
            $html,
        );
        $this->assertMatchesRegularExpression('/title:\s*"Confirm"/', $html);
    }

    public function test_header_view_has_no_hardcoded_literals(): void
    {
        $view = file_get_contents(base_path('resources/views/layouts/partials/header.blade.php'));
        $this->assertIsString($view);
        $this->assertDoesNotMatchRegularExpression(
            "/\{\{ '/",
            $view,
            'header.blade.php must not contain hardcoded {{ \'...\' }} literals — use __() keys',
        );
    }

    public function test_tooltips_expose_keyboard_and_aria_semantics(): void
    {
        $js = file_get_contents(base_path('public/js/site.js'));
        $this->assertIsString($js);
        $this->assertStringContainsString("addEventListener('focusin'", $js);
        $this->assertStringContainsString("addEventListener('focusout'", $js);
        $this->assertStringContainsString("'role', 'tooltip'", $js);
    }

    public function test_shoutbox_dialogs_promote_to_modal_and_sync_escape(): void
    {
        $view = file_get_contents(base_path('resources/views/livewire/shoutbox.blade.php'));
        $this->assertIsString($view);
        $this->assertStringContainsString('wire:keydown.escape.window="cancelConfirm"', $view);
        $this->assertStringContainsString('aria-label="{{ __(\'functions.text_close\') }}"', $view);
        $this->assertStringNotContainsString('<dialog class="nx-modal" open', $view);
        $this->assertStringNotContainsString('<dialog class="nx-modal"\n            open', $view);

        $js = file_get_contents(base_path('public/js/shoutbox-index.js'));
        $this->assertIsString($js);
        $this->assertStringContainsString('dlg.showModal()', $js);
        $this->assertStringContainsString("addEventListener('cancel'", $js);
    }

    public function test_autocomplete_js_exposes_combobox_semantics(): void
    {
        $js = file_get_contents(base_path('public/js/meili_autocomplete.js'));
        $this->assertIsString($js);
        $this->assertStringContainsString("'role', 'combobox'", $js);
        $this->assertStringContainsString("'aria-autocomplete', 'list'", $js);
        $this->assertStringContainsString("'aria-expanded'", $js);
        $this->assertStringContainsString("'aria-controls', 'meili-autocomplete-listbox'", $js);
        $this->assertStringContainsString("'role', 'listbox'", $js);
        $this->assertStringContainsString("'role', 'option'", $js);
        $this->assertStringContainsString("'aria-activedescendant'", $js);
        $this->assertStringContainsString("'aria-selected'", $js);
    }

    public function test_repeated_and_below_fold_images_lazy_load(): void
    {
        foreach ([
            'resources/views/components/shoutbox/avatar.blade.php',
            'resources/views/components/support/smiley-img.blade.php',
            'resources/views/components/support/bbcode-img.blade.php',
            'resources/views/bitbucketlog/index.blade.php',
            'resources/views/users/index.blade.php',
        ] as $viewPath) {
            $view = file_get_contents(base_path($viewPath));
            $this->assertIsString($view);
            $this->assertMatchesRegularExpression(
                '/<img\b[^>]*loading="lazy"/',
                $view,
                $viewPath.' must lazy-load repeated/below-fold images',
            );
        }

        // Above-the-fold chrome must stay eager.
        $header = file_get_contents(base_path('resources/views/layouts/partials/header.blade.php'));
        $this->assertIsString($header);
        $this->assertStringNotContainsString('nxm-logo" href="/web/index"><img src="{{ $chrome->logoMain }}" alt="{{ $chrome->siteName }}" loading="lazy"', $header);
    }

    public function test_security_and_reset_forms_expose_autocomplete_hints(): void
    {
        $security = file_get_contents(base_path('resources/views/usercp/sections/security.blade.php'));
        $this->assertIsString($security);
        $this->assertStringContainsString('autocomplete="current-password"', $security);
        $this->assertStringContainsString('autocomplete="new-password"', $security);
        $this->assertStringContainsString('autocomplete="one-time-code"', $security);
        $this->assertStringContainsString('autocomplete="email"', $security);

        $reset = file_get_contents(base_path('resources/views/reset/index.blade.php'));
        $this->assertIsString($reset);
        $this->assertStringContainsString('autocomplete="username"', $reset);
        $this->assertStringContainsString('autocomplete="new-password"', $reset);

        $invite = file_get_contents(base_path('resources/views/invite/index.blade.php'));
        $this->assertIsString($invite);
        $this->assertStringContainsString('type="email"', $invite);
        $this->assertStringContainsString('autocomplete="email"', $invite);
    }
}
