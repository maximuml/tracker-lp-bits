<?php

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\MetricsAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::PURE_UNIT)]
class MetricsAccessTest extends TestCase
{
    private function pass(Request $request, string $ip, array $headers = []): Response
    {
        $request->server->set('REMOTE_ADDR', $ip);
        foreach ($headers as $name => $value) {
            $request->headers->set($name, $value);
        }

        return (new MetricsAccess)->handle($request, fn () => response('metrics', 200));
    }

    private function request(): Request
    {
        return Request::create('http://localhost/metrics');
    }

    public function test_private_ipv4_allowed_in_non_production(): void
    {
        Config::set('metrics.token', '');
        foreach (['127.0.0.1', '10.1.2.3', '172.16.5.5', '172.31.9.9', '192.168.1.10'] as $ip) {
            $this->assertSame(200, $this->pass($this->request(), $ip)->getStatusCode(), "ip $ip");
        }
    }

    public function test_private_ipv6_allowed_in_non_production(): void
    {
        Config::set('metrics.token', '');
        foreach (['::1', 'fd00::1234', 'fc00::abcd'] as $ip) {
            $this->assertSame(200, $this->pass($this->request(), $ip)->getStatusCode(), "ip $ip");
        }
    }

    public function test_public_ip_denied_in_non_production(): void
    {
        Config::set('metrics.token', '');
        $this->assertSame(403, $this->pass($this->request(), '203.0.113.10')->getStatusCode());
        $this->assertSame(403, $this->pass($this->request(), '2001:db8::1')->getStatusCode());
    }

    public function test_bearer_token_grants_access_from_public_ip(): void
    {
        Config::set('metrics.token', 's3cr3t');
        $request = $this->request();
        $response = $this->pass($request, '203.0.113.10', ['Authorization' => 'Bearer s3cr3t']);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_wrong_bearer_denied(): void
    {
        Config::set('metrics.token', 's3cr3t');
        $response = $this->pass($this->request(), '203.0.113.10', ['Authorization' => 'Bearer wrong']);
        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_private_ip_still_allowed_with_token_set_in_non_production(): void
    {
        Config::set('metrics.token', 's3cr3t');
        $this->assertSame(200, $this->pass($this->request(), '10.0.0.5')->getStatusCode());
    }

    public function test_production_requires_bearer_even_from_private_ip(): void
    {
        Config::set('metrics.token', 's3cr3t');
        app()->detectEnvironment(fn () => 'production');

        // Reverse proxy's private IP must not bypass the token in production.
        $this->assertSame(403, $this->pass($this->request(), '172.18.0.4')->getStatusCode());

        $response = $this->pass($this->request(), '172.18.0.4', ['Authorization' => 'Bearer s3cr3t']);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_production_fails_closed_when_token_unset(): void
    {
        Config::set('metrics.token', '');
        app()->detectEnvironment(fn () => 'production');

        $this->assertSame(403, $this->pass($this->request(), '127.0.0.1')->getStatusCode());
        $this->assertSame(403, $this->pass($this->request(), '10.0.0.5')->getStatusCode());
    }

    public function test_spoofed_forwarded_for_does_not_bypass(): void
    {
        Config::set('metrics.token', '');
        // Attacker forges X-Forwarded-For to a private IP. REMOTE_ADDR is
        // public, and the proxy is not trusted — the real IP stays public.
        $response = $this->pass($this->request(), '203.0.113.10', [
            'X-Forwarded-For' => '10.0.0.1',
            'X-Real-IP' => '10.0.0.1',
        ]);
        $this->assertSame(403, $response->getStatusCode());
    }
}
