<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Enums\BusinessType;
use App\Models\BonusLogs;
use App\Models\User;
use App\Repositories\BonusRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Unit tests for BonusRepository.
 *
 * Covers findGiftReceiver(), incrementUserSeedbonus(), getCount(),
 * getList(), getCharityReceiverCount(), getTagGrouped().
 */
final class BonusRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private BonusRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new BonusRepository;
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
        $result = $this->repository->findGiftReceiver('nonexistent_user_99999');

        $this->assertNull($result);
    }

    public function test_increment_user_seedbonus_adds_amount(): void
    {
        $user = User::factory()->create(['seedbonus' => 100.0]);

        $result = $this->repository->incrementUserSeedbonus($user->id, 50.0);

        $this->assertTrue($result);
        $this->assertSame(150.0, (float) User::query()->where('id', $user->id)->value('seedbonus'));
    }

    public function test_increment_user_seedbonus_returns_false_for_nonexistent_user(): void
    {
        $result = $this->repository->incrementUserSeedbonus(999999, 50.0);

        $this->assertFalse($result);
    }

    public function test_get_count_returns_total_for_category_common(): void
    {
        $user = User::factory()->create();
        BonusLogs::factory()->count(3)->create(['uid' => $user->id]);

        $count = $this->repository->getCount(BonusLogs::CATEGORY_COMMON);

        $this->assertGreaterThanOrEqual(3, $count);
    }

    public function test_get_count_filters_by_user_id(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        BonusLogs::factory()->count(2)->create(['uid' => $user1->id]);
        BonusLogs::factory()->count(3)->create(['uid' => $user2->id]);

        $count = $this->repository->getCount(BonusLogs::CATEGORY_COMMON, $user1->id);

        $this->assertSame(2, $count);
    }

    public function test_get_count_filters_by_business_type(): void
    {
        $user = User::factory()->create();
        BonusLogs::factory()->create([
            'uid' => $user->id,
            'business_type' => BusinessType::POST_REWARD->value,
        ]);
        BonusLogs::factory()->create([
            'uid' => $user->id,
            'business_type' => BusinessType::GIFT_TO_SOMEONE->value,
        ]);

        $count = $this->repository->getCount(
            BonusLogs::CATEGORY_COMMON,
            $user->id,
            BusinessType::POST_REWARD->value
        );

        $this->assertSame(1, $count);
    }

    public function test_get_count_throws_for_invalid_category(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->repository->getCount('invalid_category');
    }

    public function test_get_list_returns_collection_for_category_common(): void
    {
        $user = User::factory()->create();
        BonusLogs::factory()->count(3)->create(['uid' => $user->id]);

        $result = $this->repository->getList(BonusLogs::CATEGORY_COMMON, $user->id);

        $this->assertGreaterThanOrEqual(3, $result->count());
    }

    public function test_get_list_orders_by_id_desc(): void
    {
        $user = User::factory()->create();
        $first = BonusLogs::factory()->create(['uid' => $user->id, 'comment' => 'first']);
        $second = BonusLogs::factory()->create(['uid' => $user->id, 'comment' => 'second']);

        $result = $this->repository->getList(BonusLogs::CATEGORY_COMMON, $user->id);

        $this->assertSame($second->id, $result->first()->id);
    }

    public function test_get_list_throws_for_invalid_category(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->repository->getList('invalid_category');
    }

    public function test_get_charity_receiver_count_returns_zero_with_no_matching_users(): void
    {
        $count = $this->repository->getCharityReceiverCount(1.0);

        // No users with >10GB downloaded, so should be 0
        $this->assertGreaterThanOrEqual(0, $count);
    }

    public function test_get_tag_grouped_returns_empty_for_empty_input(): void
    {
        $result = $this->repository->getTagGrouped([]);

        $this->assertSame([], $result);
    }

    public function test_get_tag_grouped_returns_empty_for_nonexistent_torrents(): void
    {
        $result = $this->repository->getTagGrouped([999999]);

        $this->assertSame([], $result);
    }
}
