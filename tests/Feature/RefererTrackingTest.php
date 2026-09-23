<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\RefererHit;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * TrackReferer middleware: external GET referers aggregate into
 * referer_hits (host+day, one row); internal/self, excluded paths and
 * non-GET requests are ignored.
 */
#[TestCategory(TestCategory::HTTP_FEATURE)]
final class RefererTrackingTest extends TestCase
{
    use DatabaseTransactions;

    public function test_external_referer_is_aggregated(): void
    {
        $this->get('/login', ['Referer' => 'https://www.google.com/search?q=x']);

        $this->assertDatabaseHas('referer_hits', [
            'host' => 'google.com',
            'hits' => 1,
            'last_path' => '/login',
        ]);
    }

    public function test_second_hit_same_day_increments(): void
    {
        $this->get('/login', ['Referer' => 'https://google.com/a']);
        $this->get('/login', ['Referer' => 'https://google.com/b']);

        $this->assertDatabaseHas('referer_hits', [
            'host' => 'google.com',
            'hits' => 2,
        ]);
    }

    public function test_self_referer_is_ignored(): void
    {
        $selfHost = (string) parse_url((string) config('app.url'), PHP_URL_HOST);
        $selfHost = str_starts_with($selfHost, 'www.') ? substr($selfHost, 4) : $selfHost;

        $this->get('/login', ['Referer' => 'https://'.$selfHost.'/index.php']);

        $this->assertDatabaseMissing('referer_hits', ['host' => $selfHost]);
    }

    public function test_missing_referer_records_nothing(): void
    {
        $this->get('/login');

        $this->assertSame(0, RefererHit::query()->count());
    }

    public function test_excluded_path_records_nothing(): void
    {
        $this->get('/announce', ['Referer' => 'https://google.com/x']);
        $this->get('/pic/logo.png', ['Referer' => 'https://google.com/x']);
        $this->get('/nexusphp', ['Referer' => 'https://google.com/x']);

        $this->assertSame(0, RefererHit::query()->count());
    }

    public function test_post_referer_is_ignored(): void
    {
        $this->post('/login', [], ['Referer' => 'https://google.com/x']);

        $this->assertSame(0, RefererHit::query()->count());
    }
}
