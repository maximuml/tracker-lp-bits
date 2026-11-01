<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Structure contract for the standalone auth pages (login, signup,
 * recover, confirm_resend): visible h1, info plaque, grouped help
 * links and the password-visibility toggle markup that auth.js
 * binds to — all without changing the posted field names.
 */
#[TestCategory(TestCategory::HTTP_FEATURE)]
final class AuthPagesStructureTest extends TestCase
{
    use DatabaseTransactions;

    public function test_login_page_has_heading_info_links_and_password_toggle(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $html = (string) $response->getContent();

        foreach ([
            '<h1',
            'nx-auth__info',
            'nx-auth__links',
            'nx-auth__divider',
            'data-pw-toggle',
            'name="username"',
            'name="password"',
            'name="two_step_code"',
            'name="logout"',
            'action="/login"',
        ] as $marker) {
            $this->assertStringContainsString($marker, $html);
        }
    }

    public function test_signup_page_groups_fields_and_keeps_hash_contract(): void
    {
        $response = $this->get('/signup');

        $response->assertOk();
        $html = (string) $response->getContent();

        foreach ([
            '<h1',
            'nx-auth__group',
            'nx-auth__radios',
            'data-pw-toggle',
            'data-auth-form="hash"',
            'name="wantusername"',
            'class="nx-field__input wantpassword',
            'class="nx-field__input passagain',
            'name="email"',
            'name="country"',
            'name="gender"',
            'name="rulesverify"',
            'name="faqverify"',
            'name="ageverify"',
            'id="submit-btn"',
            'type="hidden" name="wantpassword"',
            'type="hidden" name="passagain"',
        ] as $marker) {
            $this->assertStringContainsString($marker, $html);
        }
    }

    public function test_recover_page_has_heading_info_and_back_link(): void
    {
        $response = $this->get('/recover');

        $response->assertOk();
        $html = (string) $response->getContent();

        foreach ([
            '<h1',
            'nx-auth__info',
            'nx-auth__links',
            'name="email"',
            'action="/recover"',
            'href="/login"',
        ] as $marker) {
            $this->assertStringContainsString($marker, $html);
        }
    }

    public function test_confirm_resend_page_has_info_plaque_and_back_link(): void
    {
        $response = $this->get('/confirm_resend');

        $response->assertOk();
        $html = (string) $response->getContent();

        foreach (['nx-auth__info', 'nx-auth__links', 'name="email"', 'action="/confirm_resend"', 'href="/login"'] as $marker) {
            $this->assertStringContainsString($marker, $html);
        }
    }
}
