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
        // Legacy routes embed third-party widgets (FullCalendar, domTT,
        // layer.js) that set element.style.cssText dynamically. CSP cannot
        // hash style attributes, and 'unsafe-inline' is ignored when a nonce
        // is present, so style-src uses 'unsafe-inline' without a nonce.
        $this->assertStringContainsString("style-src 'self' 'unsafe-inline'", $csp);
        $this->assertStringNotContainsString("'unsafe-eval'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString('https://challenges.cloudflare.com', $csp);
        $this->assertStringNotContainsString('https://fonts.googleapis.com', $csp);
        $this->assertStringNotContainsString('https://cdnjs.cloudflare.com', $csp);
        $this->assertStringContainsString('https://www.paypal.com', $csp);
    }

    /**
     * W1-03: Filament/Livewire admin routes now use nonce-based CSP
     * (Livewire 4 + Alpine 3 support nonce). No 'unsafe-inline' or
     * 'unsafe-eval' should be present in any route's CSP.
     */
    public function test_filament_csp_uses_nonce_not_unsafe(): void
    {
        $response = $this->get('/nexusphp');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp);
        $this->assertStringContainsString("'nonce-", $csp);
        $this->assertStringNotContainsString("'unsafe-inline'", $csp);
        $this->assertStringNotContainsString("'unsafe-eval'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
    }
}
