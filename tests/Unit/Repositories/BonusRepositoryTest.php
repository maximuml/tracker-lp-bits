<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\User;
use App\Repositories\BonusRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Unit tests for BonusRepository.
 *
 * Covers incrementUserSeedbonus().
 *
 * Read/calculation methods (getCount, getList, findGiftReceiver,
 * getCharityReceiverCount, getTagGrouped, etc.) are tested in
 * BonusCalculationRepositoryTest.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class BonusRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private BonusRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new BonusRepository;
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
}
