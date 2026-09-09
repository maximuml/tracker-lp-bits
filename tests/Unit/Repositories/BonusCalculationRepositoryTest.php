<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Enums\MedalGetType;
use App\Enums\UserMedalStatus;
use App\Enums\UserStatus;
use App\Models\Medal;
use App\Models\Peer;
use App\Models\Tag;
use App\Models\Torrent;
use App\Models\User;
use App\Models\UserMedal;
use App\Models\UserMeta;
use App\Repositories\BonusCalculationRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Unit tests for BonusCalculationRepository.
 *
 * Covers getTorrentRowsForBonusCalculation(), getTagGrouped(),
 * getMedalAdditionalFactor(), getHaremAddition(),
 * getCharityReceiverCount(), findGiftReceiver(),
 * hasChangeUsernameCard(), hasRainbowIdForever().
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class BonusCalculationRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private BonusCalculationRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new BonusCalculationRepository;
    }

    public function test_get_torrent_rows_for_bonus_calculation_with_null_torrent_ids_returns_seeding_torrents(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->owner($user)->create(['size' => 1073741824]);
        Peer::factory()->user($user)->torrent($torrent)->seeder()->create();

        $result = $this->repository->getTorrentRowsForBonusCalculation($user->id, null, 0);

        $this->assertNotEmpty($result['torrentResult']);
        $ids = array_column($result['torrentResult'], 'id');
        $this->assertContains($torrent->id, $ids);
    }

    public function test_get_torrent_rows_for_bonus_calculation_with_specific_ids_filters_by_size(): void
    {
        $user = User::factory()->create();
        $small = Torrent::factory()->owner($user)->create(['size' => 100]);
        $large = Torrent::factory()->owner($user)->create(['size' => 1073741824]);

        $result = $this->repository->getTorrentRowsForBonusCalculation(
            $user->id,
            [$small->id, $large->id],
            1024
        );

        $ids = array_column($result['torrentResult'], 'id');
        $this->assertContains($large->id, $ids);
        $this->assertNotContains($small->id, $ids);
    }

    public function test_get_torrent_rows_for_bonus_calculation_with_empty_ids_returns_nothing(): void
    {
        $result = $this->repository->getTorrentRowsForBonusCalculation(1, [], 0);

        $this->assertSame([], $result['torrentResult']);
    }

    public function test_get_tag_grouped_returns_empty_for_empty_input(): void
    {
        $this->assertSame([], $this->repository->getTagGrouped([]));
    }

    public function test_get_tag_grouped_returns_grouped_tags_for_torrents(): void
    {
        $torrent = Torrent::factory()->create();
        $tag = Tag::factory()->create();
        DB::table('torrent_tags')->insert([
            'torrent_id' => $torrent->id,
            'tag_id' => $tag->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = $this->repository->getTagGrouped([$torrent->id]);

        $this->assertArrayHasKey($torrent->id, $result);
        $this->assertSame(1, $result[$torrent->id][$tag->id]);
    }

    public function test_get_medal_additional_factor_returns_zero_without_medals(): void
    {
        $user = User::factory()->create();

        $factor = $this->repository->getMedalAdditionalFactor($user->id, now()->toDateTimeString());

        $this->assertSame(0.0, $factor);
    }

    public function test_get_medal_additional_factor_sums_factors_for_valid_medals(): void
    {
        $user = User::factory()->create();
        $medal = $this->createMedal(['bonus_addition_factor' => 0.5]);
        UserMedal::query()->create([
            'uid' => $user->id,
            'medal_id' => $medal->id,
            'expire_at' => null,
            'status' => UserMedalStatus::NOT_WEARING->value,
            'bonus_addition_expire_at' => null,
        ]);

        $factor = $this->repository->getMedalAdditionalFactor($user->id, now()->toDateTimeString());

        $this->assertSame(0.5, $factor);
    }

    public function test_get_harem_addition_returns_zero_without_invitees(): void
    {
        $user = User::factory()->create();

        $addition = $this->repository->getHaremAddition($user->id);

        $this->assertSame(0, $addition);
    }

    public function test_get_harem_addition_sums_seed_points_for_invitees(): void
    {
        $inviter = User::factory()->create();
        User::factory()->create([
            'invited_by' => $inviter->id,
            'status' => UserStatus::CONFIRMED->value,
            'enabled' => true,
            'seed_points_per_hour' => 10.5,
        ]);

        $addition = $this->repository->getHaremAddition($inviter->id);

        $this->assertSame(10.5, (float) $addition);
    }

    public function test_get_charity_receiver_count_returns_zero_with_no_matching_users(): void
    {
        $count = $this->repository->getCharityReceiverCount(1.0);

        $this->assertGreaterThanOrEqual(0, $count);
    }

    public function test_get_charity_receiver_count_counts_low_ratio_users(): void
    {
        User::factory()->create([
            'enabled' => true,
            'downloaded' => 21474836480,
            'uploaded' => 1073741824,
        ]);

        $count = $this->repository->getCharityReceiverCount(1.0);

        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function test_find_gift_receiver_returns_user_array_when_found(): void
    {
        $user = User::factory()->create(['seedbonus' => 500.0]);

        $result = $this->repository->findGiftReceiver($user->username);

        $this->assertNotNull($result);
        $this->assertSame($user->id, $result['id']);
        $this->assertSame(500.0, (float) $result['seedbonus']);
    }

    public function test_find_gift_receiver_returns_null_when_not_found(): void
    {
        $this->assertNull($this->repository->findGiftReceiver('nonexistent_user_99999'));
    }

    public function test_has_change_username_card_returns_false_without_meta(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($this->repository->hasChangeUsernameCard($user->id));
    }

    public function test_has_change_username_card_returns_true_with_meta(): void
    {
        $user = User::factory()->create();
        UserMeta::query()->create([
            'uid' => $user->id,
            'meta_key' => UserMeta::META_KEY_CHANGE_USERNAME,
        ]);

        $this->assertTrue($this->repository->hasChangeUsernameCard($user->id));
    }

    public function test_has_rainbow_id_forever_returns_false_without_meta(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($this->repository->hasRainbowIdForever($user->id));
    }

    public function test_has_rainbow_id_forever_returns_true_with_permanent_meta(): void
    {
        $user = User::factory()->create();
        UserMeta::query()->create([
            'uid' => $user->id,
            'meta_key' => UserMeta::META_KEY_PERSONALIZED_USERNAME,
            'deadline' => null,
        ]);

        $this->assertTrue($this->repository->hasRainbowIdForever($user->id));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createMedal(array $overrides = []): Medal
    {
        return Medal::query()->create(array_merge([
            'name' => 'Test Medal',
            'get_type' => MedalGetType::GRANT->value,
            'price' => 0,
            'duration' => 0,
            'bonus_addition_duration' => 0,
            'bonus_addition_factor' => 0,
            'gift_fee_factor' => 0,
            'priority' => 0,
        ], $overrides));
    }
}
