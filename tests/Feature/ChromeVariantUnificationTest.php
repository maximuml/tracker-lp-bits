<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserClass;
use App\Models\Setting;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Phase 2.3 regression: `layouts.app` renders every chrome variant
 * through the shared composer/partials — the `boxed` default moved from
 * per-view `@extends` data to the composer default, and the maintenance
 * check moved from `PageRenderer` into `SiteChromeViewModel::load`.
 */
#[TestCategory(TestCategory::HTTP_FEATURE)]
final class ChromeVariantUnificationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_default_page_renders_boxed_shell_without_view_flag(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->withNexusCookie($user)->get('/web/faq');
        $response->assertOk();
        $html = (string) $response->getContent();

        // The boxed variant is the composer default: boxed shell, pack
        // theme and sprite head styles still reach boxed page bodies.
        $this->assertStringContainsString('data-chrome="boxed"', $html);
        $this->assertStringContainsString('class="nx-pagebox nx-main"', $html);
        $this->assertStringContainsString('styles/Classic/theme.css', $html);
        $this->assertStringContainsString('styles/sprites.css', $html);
    }

    public function test_explicit_modern_variant_renders_modern_chrome(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->withNexusCookie($user)->get('/web/index');
        $response->assertOk();
        $html = (string) $response->getContent();

        $this->assertStringContainsString('data-chrome="modern"', $html);
        $this->assertStringNotContainsString('nx-pagebox', $html);
    }

    public function test_explicit_bare_shell_survives_default_variant(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->withNexusCookie($user)->get('/web/ok');
        $response->assertOk();
        $html = (string) $response->getContent();

        // ok/index declares shell => 'bare' only: boxed chrome, no wrapper.
        $this->assertStringContainsString('data-chrome="boxed"', $html);
        $this->assertStringNotContainsString('nx-pagebox', $html);
    }

    public function test_offline_site_blocks_regular_users_with_503(): void
    {
        $this->setSiteOnline('no');
        try {
            /** @var User $user */
            $user = User::factory()->create(['class' => UserClass::USER->value]);

            $response = $this->withNexusCookie($user)->get('/web/faq');
            $response->assertStatus(503);
            $this->assertStringContainsString(
                (string) __('functions.std_site_down_for_maintenance'),
                (string) $response->getContent(),
            );
        } finally {
            $this->setSiteOnline('yes');
        }
    }

    public function test_offline_site_shows_admin_banner_instead_of_blocking(): void
    {
        $this->setSiteOnline('no');
        try {
            /** @var User $admin */
            $admin = User::factory()->create(['class' => UserClass::ADMINISTRATOR->value]);

            $response = $this->withNexusCookie($admin)->get('/web/faq');
            $response->assertOk();
            $this->assertStringContainsString('nxm-alert-offline', (string) $response->getContent());
        } finally {
            $this->setSiteOnline('yes');
        }
    }

    public function test_auth_page_stays_reachable_while_offline(): void
    {
        $this->setSiteOnline('no');
        try {
            // The login page must stay reachable so staff can get back in.
            $response = $this->get('/login');
            $response->assertOk();
        } finally {
            $this->setSiteOnline('yes');
        }
    }

    public function test_stdhead_msgalert_false_suppresses_alerts(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'leechwarn' => 1,
            'leechwarnuntil' => now()->addDays(7)->toDateTimeString(),
        ]);
        /** @var User $receiver */
        $receiver = User::factory()->create();

        // A warned user sees the alert banner on a normal page…
        $faqHtml = (string) $this->withNexusCookie($user)->get('/web/faq')->getContent();
        $this->assertStringContainsString('nxm-alert--ribbon', $faqHtml);

        // …but not on sendmessage, which passes stdheadMsgalert => false.
        $msgResponse = $this->withNexusCookie($user)
            ->get('/web/sendmessage?receiver='.$receiver->id);
        $msgResponse->assertOk();
        $msgHtml = (string) $msgResponse->getContent();
        $this->assertStringContainsString('name="compose"', $msgHtml);
        $this->assertStringNotContainsString('nxm-alert--ribbon', $msgHtml);
    }

    private function setSiteOnline(string $value): void
    {
        Setting::query()->where('name', 'main.site_online')->update(['value' => $value]);
        Settings::resetCache();
    }
}
