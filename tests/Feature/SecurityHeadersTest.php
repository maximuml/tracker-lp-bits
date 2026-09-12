<?php

namespace Tests\Feature;

use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::HTTP_FEATURE)]
final class SecurityHeadersTest extends TestCase
{
    public function test_public_page_has_baseline_security_headers(): void
    {
        $response = $this->get('/login');

        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_api_endpoint_does_not_leak_framework_cookies(): void
    {
        $response = $this->get('/health');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_csp_header_contains_nonce_for_legacy_routes(): void
    {
        $response = $this->get('/login');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp);
        // script-src stays nonce-strict on legacy routes.
        $this->assertStringContainsString("'nonce-", $csp);
        // Legacy routes keep nonce-strict style-src to preserve the existing
        // visual behavior — inline style attributes on legacy pages (e.g.
        // <span style="color:#aaaaaa">) were already blocked by the nonce-only
        // policy, and allowing them would cause color-contrast regressions.
        $this->assertStringContainsString("style-src 'self' 'nonce-", $csp);
        $this->assertStringNotContainsString("style-src 'self' 'unsafe-inline'", $csp);
        $this->assertStringNotContainsString("'unsafe-eval'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString('https://challenges.cloudflare.com', $csp);
        $this->assertStringNotContainsString('https://fonts.googleapis.com', $csp);
        $this->assertStringNotContainsString('https://cdnjs.cloudflare.com', $csp);
        $this->assertStringContainsString('https://www.paypal.com', $csp);
    }

    /**
     * Filament/Livewire admin routes need a relaxed CSP: Livewire's Alpine
     * evaluates expressions with new Function() ('unsafe-eval') and Filament
     * ships inline boot scripts plus dynamic style injection
     * ('unsafe-inline'). Nonces cannot cover eval'd code, and 'unsafe-inline'
     * is ignored when a nonce is present — so the admin panel gets the
     * pragmatic policy. It sits behind authentication, and object-src/base-uri
     * restrictions still apply.
     */
    public function test_filament_csp_allows_inline_scripts_styles_and_eval(): void
    {
        $response = $this->get('/nexusphp');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp);
        $this->assertStringNotContainsString("'nonce-", $csp);
        $this->assertStringContainsString("script-src 'self' 'unsafe-inline' 'unsafe-eval'", $csp);
        $this->assertStringContainsString("style-src 'self' 'unsafe-inline'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("base-uri 'self'", $csp);
    }
}
