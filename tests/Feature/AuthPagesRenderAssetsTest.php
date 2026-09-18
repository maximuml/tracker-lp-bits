<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Settings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Regression test for the Stage-0 auth-layout regression:
 * `layouts/auth` rendered `AssetAppender` output through `{{ }}`,
 * which escaped the appended `<script>`/`<link>` tags into visible
 * text — signup (`auth-form.js`) and passkey login (`passkey.js`)
 * silently broke while every test suite stayed green.
 *
 * The layout now uses the SafeHtml accessors; these tests pin the
 * markup contract: appended assets must appear as real tags and
 * never as `&lt;script` text.
 */
#[TestCategory(TestCategory::HTTP_FEATURE)]
final class AuthPagesRenderAssetsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_signup_page_renders_auth_form_script_as_markup(): void
    {
        $response = $this->get('/signup');

        $response->assertOk();
        $html = (string) $response->getContent();

        $this->assertStringContainsString(
            '<script type="text/javascript" src="js/auth-form.js"></script>',
            $html,
            'auth-form.js must be a real <script> tag — the signup submit handler lives there',
        );
        $this->assertStringNotContainsString('&lt;script', $html);
        $this->assertStringNotContainsString('&lt;link', $html);
    }

    public function test_login_page_renders_passkey_script_as_markup(): void
    {
        Settings::saveBatch('security', ['login_type' => 'passkey']);
        Settings::resetCache();

        $response = $this->get('/login');

        $response->assertOk();
        $html = (string) $response->getContent();

        $this->assertStringContainsString(
            '<script type="text/javascript" src="js/passkey.js"></script>',
            $html,
            'passkey.js must be a real <script> tag — passkey login depends on it',
        );
        $this->assertStringNotContainsString('&lt;script', $html);
        $this->assertStringNotContainsString('&lt;link', $html);
    }

    public function test_login_page_renders_no_escaped_markup_text(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $html = (string) $response->getContent();

        $this->assertStringNotContainsString('&lt;script', $html);
        $this->assertStringNotContainsString('&lt;style', $html);
    }
}
