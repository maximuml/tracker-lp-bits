<?php

declare(strict_types=1);

namespace Tests\Integration\Services\Cleanup\Tasks;

use App\Enums\TorrentPromotion;
use App\Services\Cleanup\Tasks\TorrentPromotionCleanupTask;
use App\Support\RedisGuard;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Pin test for the publish-per-expired-torrent contract in
 * TorrentPromotionCleanupTask: each expired global promotion emits one
 * torrent_updated ping on the model-event channel.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class TorrentPromotionCleanupTaskTest extends TestCase
{
    private TorrentPromotionCleanupTask $task;

    protected function setUp(): void
    {
        parent::setUp();

        DB::beginTransaction();
        $this->task = app(TorrentPromotionCleanupTask::class);
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        unset($_SERVER['CHANNEL_NAME_MODEL_EVENT'], $_ENV['CHANNEL_NAME_MODEL_EVENT']);
        Mockery::close();

        parent::tearDown();
    }

    public function test_expired_global_promotions_publish_one_event_per_torrent(): void
    {
        $_SERVER['CHANNEL_NAME_MODEL_EVENT'] = 'test_model_events';
        $_ENV['CHANNEL_NAME_MODEL_EVENT'] = 'test_model_events';

        DB::table('settings')->updateOrInsert(
            ['name' => 'torrent.expirehalfleech'],
            ['value' => '2', 'autoload' => 1],
        );
        DB::table('settings')->updateOrInsert(
            ['name' => 'torrent.halfleechbecome'],
            ['value' => (string) TorrentPromotion::NORMAL->value, 'autoload' => 1],
        );
        Settings::resetCache();

        $ownerId = (int) DB::table('users')->insertGetId([
            'username' => 'promo-owner',
            'email' => 'promo-owner@test.com',
            'passhash' => 'hash',
            'secret' => 'secret',
            'passkey' => str_repeat('1', 32),
            'added' => now()->toDateTimeString(),
        ]);

        $expiredId = $this->insertTorrent($ownerId, TorrentPromotion::HALF_DOWN->value, '-10 days');
        $freshId = $this->insertTorrent($ownerId, TorrentPromotion::HALF_DOWN->value, '-1 day');
        $deadlineId = $this->insertTorrent($ownerId, TorrentPromotion::FREE->value, '-1 day', 2, '-1 hour');

        $published = [];
        $client = Mockery::mock();
        $client->shouldReceive('publish')
            ->andReturnUsing(function (string $channel, string $payload) use (&$published): int {
                $published[] = [$channel, $payload];

                return 1;
            });
        $connection = Mockery::mock();
        $connection->shouldReceive('client')->andReturn($client);
        Redis::shouldReceive('connection')->withNoArgs()->andReturn($connection);
        RedisGuard::reset();

        $this->task->expireTorrentPromotions();

        $this->assertCount(2, $published);
        $decoded = array_map(static fn (array $p): array => json_decode($p[1], true), $published);
        $publishedIds = array_column($decoded, 'id');
        sort($publishedIds);
        $this->assertSame([$expiredId, $deadlineId], $publishedIds);
        foreach ($decoded as $row) {
            $this->assertSame('torrent_updated', $row['event']);
        }
        $this->assertSame('test_model_events', $published[0][0]);

        $this->assertSame(
            TorrentPromotion::NORMAL->value,
            (int) DB::table('torrents')->where('id', $expiredId)->value('sp_state'),
        );
        $this->assertSame(
            TorrentPromotion::HALF_DOWN->value,
            (int) DB::table('torrents')->where('id', $freshId)->value('sp_state'),
        );
        $deadlineRow = DB::table('torrents')->where('id', $deadlineId)->first();
        $this->assertNotNull($deadlineRow);
        $this->assertSame(TorrentPromotion::NORMAL->value, (int) $deadlineRow->sp_state);
        $this->assertSame(0, (int) $deadlineRow->promotion_time_type);
        $this->assertNull($deadlineRow->promotion_until);
    }

    private function insertTorrent(int $ownerId, int $spState, string $added, int $promotionTimeType = 0, ?string $promotionUntil = null): int
    {
        return (int) DB::table('torrents')->insertGetId([
            'name' => 'Promo Torrent '.uniqid(),
            'filename' => 'promo.torrent',
            'save_as' => 'promo',
            'category' => 1,
            'size' => 1024,
            'type' => 0,
            'numfiles' => 1,
            'owner' => $ownerId,
            'info_hash' => random_bytes(20),
            'visible' => 1,
            'banned' => 0,
            'sp_state' => $spState,
            'promotion_time_type' => $promotionTimeType,
            'promotion_until' => $promotionUntil === null ? null : now()->modify($promotionUntil)->toDateTimeString(),
            'added' => now()->modify($added)->toDateTimeString(),
        ]);
    }
}
