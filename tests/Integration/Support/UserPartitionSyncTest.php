<?php

declare(strict_types=1);

namespace Tests\Integration\Support;

use App\Models\User;
use App\Models\UserActivity;
use App\Models\UserPreference;
use App\Repositories\PageLayoutRepository;
use App\Repositories\UserRepository;
use App\Support\UserPartitionSync;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * M-03/B-02: query-builder updates on the users table bypass the
 * UserPartitionObserver, so repositories must mirror partitioned
 * columns through UserPartitionSync.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class UserPartitionSyncTest extends TestCase
{
    use DatabaseTransactions;

    public function test_update_fields_mirrors_activity_columns(): void
    {
        $user = User::factory()->create();
        $stamp = '2026-10-26 12:00:00';

        app(UserRepository::class)->updateFields($user->id, ['last_access' => $stamp]);

        $this->assertSame(
            $stamp,
            UserActivity::query()->where('user_id', $user->id)->value('last_access')?->toDateTimeString()
        );
    }

    public function test_update_fields_mirrors_preference_columns(): void
    {
        $user = User::factory()->create();

        app(UserRepository::class)->updateFields($user->id, ['signature' => 'sig-text']);

        $this->assertSame('sig-text', UserPreference::query()->where('user_id', $user->id)->value('signature'));
    }

    public function test_update_fields_ignores_unpartitioned_columns(): void
    {
        $user = User::factory()->create();
        UserPreference::query()->where('user_id', $user->id)->delete();

        app(UserRepository::class)->updateFields($user->id, ['username' => 'renamed-'.$user->id]);

        $this->assertSame(0, UserPreference::query()->where('user_id', $user->id)->count());
    }

    public function test_sync_updates_existing_partition_row(): void
    {
        $user = User::factory()->create();
        UserPreference::query()->updateOrCreate(['user_id' => $user->id], ['signature' => 'first']);

        UserPartitionSync::sync($user->id, ['signature' => 'second']);

        $this->assertSame('second', UserPreference::query()->where('user_id', $user->id)->value('signature'));
        $this->assertSame(1, UserPreference::query()->where('user_id', $user->id)->count());
    }

    public function test_page_layout_update_user_mirrors_columns(): void
    {
        $user = User::factory()->create();
        $stamp = '2026-10-26 13:00:00';

        app(PageLayoutRepository::class)->updateUser($user->id, [
            'last_access' => $stamp,
            'page' => 'browse',
        ]);

        $this->assertSame(
            $stamp,
            UserActivity::query()->where('user_id', $user->id)->value('last_access')?->toDateTimeString()
        );
        $this->assertSame('browse', UserPreference::query()->where('user_id', $user->id)->value('page'));
    }
}
