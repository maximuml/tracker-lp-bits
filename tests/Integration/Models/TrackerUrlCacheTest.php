<?php

declare(strict_types=1);

namespace Tests\Integration\Models;

use App\Models\TrackerUrl;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Redis;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION, TestCategory::MUTATION)]
class TrackerUrlCacheTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        TrackerUrl::query()->delete();
        $this->flushTrackerCache();
    }

    protected function tearDown(): void
    {
        $this->flushTrackerCache();
        parent::tearDown();
    }

    private function flushTrackerCache(): void
    {
        $redis = Redis::connection()->client();
        $redis->unlink(TrackerUrl::TRACKER_URL_CACHE_KEY, TrackerUrl::TRACKER_URL_DEFAULT_CACHE_KEY);
        foreach ([0, 1, 2, 3, 4, 5] as $id) {
            $redis->unlink("TRACKER_URL_NOT_FOUND:$id");
        }
        foreach (TrackerUrl::query()->pluck('id') as $id) {
            $redis->unlink("TRACKER_URL_NOT_FOUND:$id");
        }
    }

    private function make(string $url, int $isDefault = 0): TrackerUrl
    {
        return TrackerUrl::create(['url' => $url, 'enabled' => 1, 'is_default' => $isDefault, 'priority' => 0]);
    }

    public function test_deleting_a_tracker_url_drops_it_from_the_id_cache(): void
    {
        $this->make('https://main.example.com', 1);
        $extra = $this->make('https://extra.example.com');
        $this->assertSame('https://extra.example.com', TrackerUrl::getById($extra->id));

        $extra->delete();

        $this->assertSame('https://main.example.com', TrackerUrl::getById($extra->id));
    }

    public function test_deleting_the_default_promotes_the_next_url(): void
    {
        $main = $this->make('https://main.example.com', 1);
        $this->make('https://extra.example.com');
        $this->assertSame('https://main.example.com', TrackerUrl::getById(0));

        $main->delete();

        $this->assertSame('https://extra.example.com', TrackerUrl::getById(0));
    }

    public function test_deleting_the_last_url_clears_the_default(): void
    {
        $only = $this->make('https://e2e-tracker.example.com:8443/announce.php');
        $this->assertSame('https://e2e-tracker.example.com:8443/announce.php', TrackerUrl::getById(0));

        $only->delete();

        $this->assertFalse(TrackerUrl::getById(0));
        $this->assertFalse(TrackerUrl::getById($only->id));
    }

    public function test_disabling_the_last_url_clears_the_default(): void
    {
        $only = $this->make('https://main.example.com');
        $this->assertSame('https://main.example.com', TrackerUrl::getById(0));

        $only->update(['enabled' => 0]);

        $this->assertFalse(TrackerUrl::getById(0));
    }
}
