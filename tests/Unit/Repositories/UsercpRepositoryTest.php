<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\User;
use App\Repositories\UsercpRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Unit tests for UsercpRepository.
 *
 * Covers getUserById(), updateUser(), updateLastOffer(), emailExistsForOther().
 *
 * Lookup/count methods are covered in UsercpLookupRepositoryTest.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class UsercpRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private UsercpRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = app(UsercpRepository::class);
    }

    public function test_get_user_by_id_returns_user(): void
    {
        $user = User::factory()->create();

        $found = $this->repository->getUserById($user->id);

        $this->assertSame($user->id, $found->id);
        $this->assertSame($user->username, $found->username);
    }

    public function test_get_user_by_id_throws_for_nonexistent(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->repository->getUserById(999999);
    }

    public function test_update_user_modifies_fields(): void
    {
        $user = User::factory()->create(['title' => '']);

        $result = $this->repository->updateUser($user->id, ['title' => 'New Title']);

        $this->assertTrue($result);
        $this->assertSame('New Title', User::query()->where('id', $user->id)->value('title'));
    }

    public function test_update_last_offer_sets_timestamp(): void
    {
        $user = User::factory()->create();

        $result = $this->repository->updateLastOffer($user->id);

        $this->assertTrue($result);
        $lastOffer = User::query()->where('id', $user->id)->value('last_offer');
        $this->assertNotNull($lastOffer);
    }

    public function test_email_exists_for_other_returns_true_when_email_used_by_different_user(): void
    {
        $user1 = User::factory()->create(['email' => 'shared@example.com']);
        $user2 = User::factory()->create();

        $result = $this->repository->emailExistsForOther('shared@example.com', $user2->id);

        $this->assertTrue($result);
    }

    public function test_email_exists_for_other_returns_false_when_email_used_by_same_user(): void
    {
        $user = User::factory()->create(['email' => 'mine@example.com']);

        $result = $this->repository->emailExistsForOther('mine@example.com', $user->id);

        $this->assertFalse($result);
    }

    public function test_email_exists_for_other_returns_false_when_email_not_used(): void
    {
        $user = User::factory()->create();

        $result = $this->repository->emailExistsForOther('unused@example.com', $user->id);

        $this->assertFalse($result);
    }
}
