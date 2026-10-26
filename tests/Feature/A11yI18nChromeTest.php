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
}
