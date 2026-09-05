<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\User;
use App\Models\UserMeta;
use App\Models\UserModifyLog;
use App\Repositories\UserModerationRepository;
use App\Repositories\UserRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Unit tests for UserRepository.
 *
 * Covers getBase(), findForCacheClear(), findForDisplay(), getByIds(),
 * listMetas(), logModify().
 */
final class UserRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private UserRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new UserRepository(app(UserModerationRepository::class));
    }

    public function test_get_base_returns_user_with_selected_columns(): void
    {
        $user = User::factory()->create();

        $result = $this->repository->getBase($user->id);

        $this->assertSame($user->id, $result->id);
        $this->assertSame($user->username, $result->username);
    }

    public function test_get_base_throws_for_nonexistent_user(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->repository->getBase(999999);
    }

    public function test_find_for_cache_clear_returns_user_when_found(): void
    {
        $user = User::factory()->create();

        $result = $this->repository->findForCacheClear($user->id);

        $this->assertNotNull($result);
        $this->assertSame($user->id, $result->id);
    }

    public function test_find_for_cache_clear_returns_null_when_not_found(): void
    {
        $result = $this->repository->findForCacheClear(999999);

        $this->assertNull($result);
    }

    public function test_find_for_display_returns_user_when_found(): void
    {
        $user = User::factory()->create();

        $result = $this->repository->findForDisplay($user->id);

        $this->assertNotNull($result);
        $this->assertSame($user->id, $result->id);
        $this->assertSame($user->username, $result->username);
    }

    public function test_find_for_display_returns_null_when_not_found(): void
    {
        $result = $this->repository->findForDisplay(999999);

        $this->assertNull($result);
    }

    public function test_get_by_ids_returns_collection_keyed_by_id(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $result = $this->repository->getByIds([$user1->id, $user2->id]);

        $this->assertTrue($result->has($user1->id));
        $this->assertTrue($result->has($user2->id));
    }

    public function test_get_by_ids_returns_empty_collection_for_nonexistent_ids(): void
    {
        $result = $this->repository->getByIds([999999]);

        $this->assertTrue($result->isEmpty());
    }

    public function test_list_metas_returns_empty_collection_when_no_metas(): void
    {
        $user = User::factory()->create();

        $result = $this->repository->listMetas($user->id);

        $this->assertTrue($result->isEmpty());
    }

    public function test_list_metas_returns_metas_grouped_by_key(): void
    {
        $user = User::factory()->create();
        UserMeta::query()->create([
            'uid' => $user->id,
            'meta_key' => 'test_key',
            'meta_value' => 'test_value',
            'status' => 0,
        ]);

        $result = $this->repository->listMetas($user->id);

        $this->assertTrue($result->has('test_key'));
        $this->assertCount(1, $result->get('test_key'));
    }

    public function test_list_metas_filters_by_meta_keys(): void
    {
        $user = User::factory()->create();
        UserMeta::query()->create([
            'uid' => $user->id,
            'meta_key' => 'key_a',
            'meta_value' => 'value_a',
            'status' => 0,
        ]);
        UserMeta::query()->create([
            'uid' => $user->id,
            'meta_key' => 'key_b',
            'meta_value' => 'value_b',
            'status' => 0,
        ]);

        $result = $this->repository->listMetas($user->id, ['key_a']);

        $this->assertTrue($result->has('key_a'));
        $this->assertFalse($result->has('key_b'));
    }

    public function test_list_metas_filters_out_expired_metas_when_valid_true(): void
    {
        $user = User::factory()->create();
        UserMeta::query()->create([
            'uid' => $user->id,
            'meta_key' => 'expired_key',
            'meta_value' => 'value',
            'status' => 0,
            'deadline' => now()->subDay()->toDateTimeString(),
        ]);
        UserMeta::query()->create([
            'uid' => $user->id,
            'meta_key' => 'valid_key',
            'meta_value' => 'value',
            'status' => 0,
            'deadline' => now()->addDay()->toDateTimeString(),
        ]);

        $result = $this->repository->listMetas($user->id);

        $this->assertFalse($result->has('expired_key'));
        $this->assertTrue($result->has('valid_key'));
    }

    public function test_log_modify_creates_modify_log(): void
    {
        $user = User::factory()->create();

        $this->repository->logModify($user->id, 'Test modification');

        $log = UserModifyLog::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($log);
        $this->assertSame('Test modification', $log->content);
    }
}
