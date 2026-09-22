<?php

declare(strict_types=1);

namespace Tests\Integration\Support;

use App\Models\TrackerUrl;
use App\Support\RequestContext;
use App\Support\Settings;
use App\Support\Tracker;
use App\Support\Url;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
class SiteUrlContractTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Request::setTrustedProxies([], -1);
        Redis::connection()->client()->unlink(
            TrackerUrl::TRACKER_URL_CACHE_KEY,
            TrackerUrl::TRACKER_URL_DEFAULT_CACHE_KEY
        );
        Settings::resetCache();
        parent::tearDown();
    }

    public function test_normalize_accepts_host_only_and_full_urls(): void
    {
        $this->assertSame('http://tracker.example.com', Url::normalize('tracker.example.com', false));
        $this->assertSame('https://tracker.example.com/announce.php', Url::normalize('https://tracker.example.com/announce.php', false));
        $this->assertNull(Url::normalize('javascript://x', false));
    }

    public function test_site_base_uses_config_only_not_request_host(): void
    {
        Settings::saveBatch('basic', ['BASEURL' => 'https://configured.example.com']);
        Settings::resetCache();
        $_SERVER['HTTP_HOST'] = 'attacker.example.org';

        $this->assertSame('https://configured.example.com', Url::siteBase());

        unset($_SERVER['HTTP_HOST']);
    }

    public function test_site_base_normalizes_legacy_host_only_config(): void
    {
        Settings::saveBatch('basic', ['BASEURL' => 'configured.example.com']);
        Settings::saveBatch('security', ['securelogin' => 'no']);
        Settings::resetCache();

        $this->assertSame('http://configured.example.com', Url::siteBase());
    }

    public function test_scheme_and_host_from_config_never_doubles_scheme(): void
    {
        Settings::saveBatch('basic', ['BASEURL' => 'https://configured.example.com']);
        Settings::resetCache();

        $this->assertSame('https://configured.example.com', Url::schemeAndHost(true));
    }

    public function test_tracker_schema_and_host_accepts_legacy_host_only(): void
    {
        Settings::saveBatch('security', ['securelogin' => 'yes']);
        Settings::resetCache();
        $row = TrackerUrl::create(['url' => 'tracker.example.com', 'enabled' => 1, 'is_default' => 0, 'priority' => 0]);

        $parts = Tracker::schemaAndHost($row->id);

        $this->assertSame('https://', $parts['ssl_torrent']);
        $this->assertSame('tracker.example.com', $parts['base_announce_url']);
        $this->assertSame('https://tracker.example.com', Tracker::schemaAndHost($row->id, true));
    }

    public function test_tracker_schema_and_host_preserves_full_url(): void
    {
        $row = TrackerUrl::create(['url' => 'http://tracker.example.com:8080/announce.php', 'enabled' => 1, 'is_default' => 0, 'priority' => 0]);

        $parts = Tracker::schemaAndHost($row->id);

        $this->assertSame('http://', $parts['ssl_torrent']);
        $this->assertSame('tracker.example.com:8080/announce.php', $parts['base_announce_url']);
        $this->assertSame('http://tracker.example.com:8080/announce.php', Tracker::schemaAndHost($row->id, true));
    }

    public function test_tracker_schema_and_host_fallback_has_no_double_scheme(): void
    {
        // The fallback reads Setting::getBaseUrl() whose per-process static
        // cache cannot be reset — so assert the structural invariant instead
        // of a concrete host: scheme lives only in ssl_torrent, never inside
        // base_announce_url, and combined output is a single well-formed URL.
        Settings::saveBatch('security', ['securelogin' => 'no']);
        Settings::resetCache();
        TrackerUrl::query()->delete();
        Redis::connection()->client()->unlink(TrackerUrl::TRACKER_URL_CACHE_KEY, TrackerUrl::TRACKER_URL_DEFAULT_CACHE_KEY);

        $parts = Tracker::schemaAndHost(999999);
        $combined = Tracker::schemaAndHost(999999, true);

        $this->assertSame('http://', $parts['ssl_torrent']);
        $this->assertStringNotContainsString('://', $parts['base_announce_url']);
        $this->assertStringEndsWith('/announce.php', $parts['base_announce_url']);
        $this->assertSame($parts['ssl_torrent'].$parts['base_announce_url'], $combined);
        $this->assertMatchesRegularExpression('#^https?://[^/]+/announce\.php$#', $combined);
    }

    public function test_request_host_ignores_untrusted_forwarded_header(): void
    {
        $request = Request::create('http://real.example.com/path', 'GET', [], [], [], [
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_X_FORWARDED_HOST' => 'attacker.example.org',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ]);
        app()->instance('request', $request);

        $this->assertSame('real.example.com', RequestContext::instance()->getRequestHost());
        $this->assertSame('http', RequestContext::instance()->getRequestSchema());
    }

    public function test_request_host_honors_forwarded_header_from_trusted_proxy(): void
    {
        Request::setTrustedProxies(
            ['10.0.0.1'],
            Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_PORT
        );
        $request = Request::create('http://internal/path', 'GET', [], [], [], [
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_X_FORWARDED_HOST' => 'public.example.com',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ]);
        app()->instance('request', $request);

        $this->assertSame('public.example.com', RequestContext::instance()->getRequestHost());
        $this->assertSame('https', RequestContext::instance()->getRequestSchema());
    }
}
