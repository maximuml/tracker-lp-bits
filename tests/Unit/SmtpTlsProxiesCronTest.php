<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Wave 2 Step 9: SMTP TLS, trusted proxies, cron token, security headers.
 *
 * Verifies that:
 * - SMTP transport enforces TLS peer verification
 * - TRUSTED_PROXIES defaults to empty (trust no proxy) in production
 * - /cron endpoint is protected by CronToken middleware
 * - CronToken uses REMOTE_ADDR (not X-Forwarded-For) for loopback check
 * - HSTS header is set on HTTPS responses
 * - SESSION_SECURE_COOKIE is configurable
 */
final class SmtpTlsProxiesCronTest extends TestCase
{
    /**
     * SMTP transport uses verify_peer and verify_peer_name.
     */
    public function test_smtp_transport_enforces_tls_peer_verification(): void
    {
        $source = file_get_contents(app_path('Repositories/ToolRepository.php'));
        $this->assertStringContainsString('verify_peer', $source, 'SMTP must verify TLS peer');
        $this->assertStringContainsString('verify_peer_name', $source, 'SMTP must verify TLS peer name');
        $this->assertStringContainsString("'allow_self_signed' => false", $source, 'SMTP must not allow self-signed certs');
    }

    /**
     * config/mail.php has verify_peer defaulting to true.
     */
    public function test_mail_config_defaults_verify_peer_to_true(): void
    {
        $this->assertTrue(config('mail.verify_peer', true));
        $source = file_get_contents(base_path('config/mail.php'));
        $this->assertStringContainsString("'verify_peer' => env('MAIL_VERIFY_PEER', true)", $source);
    }

    /**
     * config/nexus.php defaults trusted_proxies to empty string.
     */
    public function test_trusted_proxies_defaults_to_empty(): void
    {
        $source = file_get_contents(base_path('config/nexus.php'));
        $this->assertStringContainsString(
            "Env::get('TRUSTED_PROXIES', '')",
            $source,
            'config/nexus.php must default TRUSTED_PROXIES to empty string'
        );
    }

    /**
     * Network::getTrustedProxies() defaults to empty (no proxies trusted).
     */
    public function test_network_trusted_proxies_defaults_to_empty(): void
    {
        $source = file_get_contents(app_path('Support/Network.php'));
        $this->assertStringContainsString(
            "Env::get('TRUSTED_PROXIES', '')",
            $source,
            'Network.php must default TRUSTED_PROXIES to empty string (not private ranges)'
        );
        $this->assertStringNotContainsString(
            "Env::get('TRUSTED_PROXIES', '10.0.0.0/8",
            $source,
            'Network.php must not default to private IP ranges — explicit config required'
        );
    }

    /**
     * TrustProxies middleware treats empty string as "trust no proxy".
     */
    public function test_trust_proxies_middleware_rejects_empty_string(): void
    {
        $source = file_get_contents(app_path('Http/Middleware/TrustProxies.php'));
        $this->assertStringContainsString("''", $source, 'TrustProxies must handle empty string as "trust no proxy"');
        $this->assertStringContainsString('null', $source, 'TrustProxies must return null for empty config');
    }

    /**
     * .env.example documents TRUSTED_PROXIES with empty default.
     */
    public function test_env_example_documents_trusted_proxies_empty_default(): void
    {
        $source = file_get_contents(base_path('.env.example'));
        $this->assertStringContainsString('TRUSTED_PROXIES', $source);
        $this->assertStringContainsString('empty', $source, '.env.example must document that default is empty');
    }

    /**
     * CronToken middleware exists and is applied to /cron route.
     */
    public function test_cron_route_protected_by_cron_token_middleware(): void
    {
        $route = null;
        foreach (app('router')->getRoutes() as $r) {
            if ($r->uri() === 'cron') {
                $route = $r;
                break;
            }
        }
        $this->assertNotNull($route, '/cron route must exist');
        $middleware = $route->gatherMiddleware();
        $this->assertContains('cron.token', $middleware, '/cron must be protected by cron.token middleware');
    }

    /**
     * CronToken uses REMOTE_ADDR (not $request->ip()) for loopback check.
     * This prevents X-Forwarded-For spoofing from bypassing the token check.
     */
    public function test_cron_token_uses_remote_addr_not_request_ip(): void
    {
        $source = file_get_contents(app_path('Http/Middleware/CronToken.php'));
        $this->assertStringContainsString('REMOTE_ADDR', $source, 'CronToken must use REMOTE_ADDR for loopback check');
        // Check the isLoopback method body only (not comments) for $request->ip()
        $this->assertStringContainsString('isLoopback', $source);
        // Extract method body
        preg_match('/private function isLoopback.*?\{(.+)\}/s', $source, $matches);
        $this->assertNotEmpty($matches, 'isLoopback method must exist');
        $methodBody = $matches[1];
        // The method body must use REMOTE_ADDR, not $request->ip()
        $this->assertStringContainsString('REMOTE_ADDR', $methodBody);
        $this->assertStringNotContainsString('$request->ip()', $methodBody, 'CronToken must NOT use $request->ip() — vulnerable to X-Forwarded-For spoofing');
    }

    /**
     * CronToken uses hash_equals for constant-time token comparison.
     */
    public function test_cron_token_uses_hash_equals(): void
    {
        $source = file_get_contents(app_path('Http/Middleware/CronToken.php'));
        $this->assertStringContainsString('hash_equals', $source, 'CronToken must use hash_equals for constant-time comparison');
    }

    /**
     * SecurityHeaders middleware sets HSTS on HTTPS responses.
     */
    public function test_hsts_header_set_on_https(): void
    {
        $source = file_get_contents(app_path('Http/Middleware/SecurityHeaders.php'));
        $this->assertStringContainsString('Strict-Transport-Security', $source, 'SecurityHeaders must set HSTS');
        $this->assertStringContainsString('31536000', $source, 'HSTS max-age must be at least 1 year');
        $this->assertStringContainsString('includeSubDomains', $source, 'HSTS must include subdomains');
        $this->assertStringContainsString('isSecure()', $source, 'HSTS must only be set on HTTPS responses');
    }

    /**
     * Session config supports SESSION_SECURE_COOKIE.
     */
    public function test_session_secure_cookie_configurable(): void
    {
        $source = file_get_contents(base_path('config/session.php'));
        $this->assertStringContainsString('SESSION_SECURE_COOKIE', $source, 'session.php must support SESSION_SECURE_COOKIE');
    }

    /**
     * AppServiceProvider warns about weak CRON_TOKEN in production.
     */
    public function test_app_service_provider_warns_about_weak_cron_token(): void
    {
        $source = file_get_contents(app_path('Providers/AppServiceProvider.php'));
        $this->assertStringContainsString('CRON_TOKEN', $source, 'AppServiceProvider must check CRON_TOKEN strength');
        $this->assertStringContainsString('32', $source, 'AppServiceProvider must warn if CRON_TOKEN < 32 chars');
    }
}
