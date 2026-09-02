<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Repositories\TorrentRepository;
use App\Support\Cache\LegacyRedisCache;
use App\Support\TorrentBookmark;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Tests\TestCase;

/**
 * Unit tests for TorrentBookmark.
 *
 * Covers bookmarkArray (cache hit/miss) and stateMarkup (bookmarked/
 * unbookmarked, text mode, icon mode).
 */
final class TorrentBookmarkTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @return array<string, string> */
    private function labels(): array
    {
        return [
            'title_bookmark_torrent' => 'Bookmark this torrent',
            'title_delbookmark_torrent' => 'Remove bookmark',
        ];
    }

    public function test_bookmark_array_returns_cached_value(): void
    {
        $cache = Mockery::mock(LegacyRedisCache::class);
        $cache->shouldReceive('get_value')
            ->with('user_42_bookmark_array')
            ->once()
            ->andReturn([10, 20, 30]);

        $result = TorrentBookmark::bookmarkArray($cache, 42);

        $this->assertSame([10, 20, 30], $result);
    }

    public function test_bookmark_array_falls_back_to_repository(): void
    {
        $cache = Mockery::mock(LegacyRedisCache::class);
        $cache->shouldReceive('get_value')
            ->with('user_99_bookmark_array')
            ->once()
            ->andReturn(false);
        $cache->shouldReceive('cache_value')
            ->with('user_99_bookmark_array', Mockery::type('array'), Mockery::type('int'))
            ->once();

        $repo = Mockery::mock(TorrentRepository::class);
        $repo->shouldReceive('getBookmarkTorrentIds')
            ->with(99)
            ->once()
            ->andReturn([5, 15]);
        $this->app->instance(TorrentRepository::class, $repo);

        $result = TorrentBookmark::bookmarkArray($cache, 99);

        $this->assertSame([5, 15], $result);
    }

    public function test_bookmark_array_with_null_cache_falls_back_to_repo(): void
    {
        $repo = Mockery::mock(TorrentRepository::class);
        $repo->shouldReceive('getBookmarkTorrentIds')
            ->with(7)
            ->once()
            ->andReturn([]);
        $this->app->instance(TorrentRepository::class, $repo);

        $result = TorrentBookmark::bookmarkArray(null, 7);

        $this->assertSame([], $result);
    }

    public function test_state_markup_unbookmarked_icon(): void
    {
        $cache = Mockery::mock(LegacyRedisCache::class);
        $cache->shouldReceive('get_value')
            ->with('user_1_bookmark_array')
            ->once()
            ->andReturn([10, 20]);

        $html = TorrentBookmark::stateMarkup($cache, 1, 30, false, $this->labels());

        $this->assertStringContainsString('class="delbookmark"', $html);
        $this->assertStringContainsString('alt="Unbookmarked"', $html);
        $this->assertStringContainsString('Bookmark this torrent', $html);
    }

    public function test_state_markup_bookmarked_icon(): void
    {
        $cache = Mockery::mock(LegacyRedisCache::class);
        $cache->shouldReceive('get_value')
            ->with('user_1_bookmark_array')
            ->once()
            ->andReturn([10, 20, 30]);

        $html = TorrentBookmark::stateMarkup($cache, 1, 30, false, $this->labels());

        $this->assertStringContainsString('class="bookmark"', $html);
        $this->assertStringContainsString('alt="Bookmarked"', $html);
        $this->assertStringContainsString('Remove bookmark', $html);
    }

    public function test_state_markup_unbookmarked_text(): void
    {
        $cache = Mockery::mock(LegacyRedisCache::class);
        $cache->shouldReceive('get_value')
            ->with('user_1_bookmark_array')
            ->once()
            ->andReturn([]);

        $text = TorrentBookmark::stateMarkup($cache, 1, 30, true, $this->labels());

        $this->assertSame('Bookmark this torrent', $text);
    }

    public function test_state_markup_bookmarked_text(): void
    {
        $cache = Mockery::mock(LegacyRedisCache::class);
        $cache->shouldReceive('get_value')
            ->with('user_1_bookmark_array')
            ->once()
            ->andReturn([30]);

        $text = TorrentBookmark::stateMarkup($cache, 1, 30, true, $this->labels());

        $this->assertSame('Remove bookmark', $text);
    }

    public function test_state_markup_with_empty_labels(): void
    {
        $cache = Mockery::mock(LegacyRedisCache::class);
        $cache->shouldReceive('get_value')
            ->with('user_1_bookmark_array')
            ->once()
            ->andReturn([]);

        $text = TorrentBookmark::stateMarkup($cache, 1, 30, true, []);

        $this->assertSame('', $text);
    }

    public function test_state_markup_accepts_string_user_id(): void
    {
        $cache = Mockery::mock(LegacyRedisCache::class);
        $cache->shouldReceive('get_value')
            ->with('user_42_bookmark_array')
            ->once()
            ->andReturn([30]);

        $text = TorrentBookmark::stateMarkup($cache, '42', '30', true, $this->labels());

        $this->assertSame('Remove bookmark', $text);
    }
}
