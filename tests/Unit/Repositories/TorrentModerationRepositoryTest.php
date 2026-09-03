<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Enums\PromotionTimeType;
use App\Enums\TorrentApprovalStatus;
use App\Enums\TorrentPosState;
use App\Enums\TorrentPromotion;
use App\Enums\UserClass;
use App\Models\Torrent;
use App\Models\User;
use App\Repositories\MeiliSearchRepository;
use App\Repositories\SearchBoxRepository;
use App\Repositories\TorrentDownloadRepository;
use App\Repositories\TorrentModerationRepository;
use App\Support\Permissions;
use App\Support\Settings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

/**
 * Unit tests for TorrentModerationRepository.
 *
 * Covers renderApprovalStatus(), shouldShowApprovalStatusIcon(),
 * getApprovalDenyCount(), syncTags(), setPosState(), setHr(),
 * setSpState(), and approval().
 *
 * Permission-gated methods use a STAFFLEADER user which bypasses
 * the permission table lookup.
 */
final class TorrentModerationRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private TorrentModerationRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        Permissions::resetState();

        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('torrent_tags')->delete();
        DB::table('torrent_operation_logs')->delete();
        DB::table('torrent_extras')->delete();
        DB::table('torrents')->delete();
        DB::table('tags')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        $searchBoxMock = Mockery::mock(SearchBoxRepository::class);
        $downloadMock = Mockery::mock(TorrentDownloadRepository::class);
        $meiliMock = Mockery::mock(MeiliSearchRepository::class);
        $meiliMock->shouldReceive('deleteDocuments')->andReturnNull();

        /** @phpstan-ignore-next-line */
        $this->repository = new TorrentModerationRepository($searchBoxMock, $downloadMock, $meiliMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_render_approval_status_returns_empty_when_not_shown(): void
    {
        $result = $this->repository->renderApprovalStatus(TorrentApprovalStatus::ALLOW->value, false);

        $this->assertSame('', $result);
    }

    public function test_render_approval_status_returns_html_when_shown(): void
    {
        $result = $this->repository->renderApprovalStatus(TorrentApprovalStatus::DENY->value, true);

        $this->assertStringContainsString('<span', $result);
        $this->assertStringContainsString('title=', $result);
    }

    public function test_should_show_approval_status_icon_returns_true_for_non_allow_when_not_visible(): void
    {
        // When approval status icon is disabled and torrent is not ALLOW
        // and approval status none is not visible, should show
        $this->setSetting('torrent.approval_status_icon_enabled', 'no');
        $this->setSetting('torrent.approval_status_none_visible', 'no');

        $result = $this->repository->shouldShowApprovalStatusIcon(TorrentApprovalStatus::DENY->value);

        $this->assertTrue($result);
    }

    public function test_get_approval_deny_count_returns_zero_when_none(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $count = $this->repository->getApprovalDenyCount($user->id);

        $this->assertSame(0, $count);
    }

    public function test_get_approval_deny_count_counts_denied_torrents(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        Torrent::factory()->owner($user)->create(['approval_status' => TorrentApprovalStatus::DENY->value]);
        Torrent::factory()->owner($user)->create(['approval_status' => TorrentApprovalStatus::ALLOW->value]);
        Torrent::factory()->owner($user)->create(['approval_status' => TorrentApprovalStatus::DENY->value]);

        $count = $this->repository->getApprovalDenyCount($user->id);

        $this->assertSame(2, $count);
    }

    public function test_sync_tags_inserts_records(): void
    {
        /** @var User $user */
        $user = User::factory()->class(UserClass::STAFFLEADER->value)->create();
        Auth::login($user);

        /** @var Torrent $torrent */
        $torrent = Torrent::factory()->create();
        $tagId1 = (int) DB::table('tags')->insertGetId(['name' => 'Tag1', 'color' => '#fff', 'priority' => 0, 'mode' => 0]);
        $tagId2 = (int) DB::table('tags')->insertGetId(['name' => 'Tag2', 'color' => '#fff', 'priority' => 0, 'mode' => 0]);

        $count = $this->repository->syncTags($torrent->id, [$tagId1, $tagId2], true);

        $this->assertSame(2, $count);
        $this->assertSame(2, DB::table('torrent_tags')->where('torrent_id', $torrent->id)->count());
    }

    public function test_sync_tags_removes_existing_when_remove_true(): void
    {
        /** @var User $user */
        $user = User::factory()->class(UserClass::STAFFLEADER->value)->create();
        Auth::login($user);

        /** @var Torrent $torrent */
        $torrent = Torrent::factory()->create();
        $tagId1 = (int) DB::table('tags')->insertGetId(['name' => 'Tag1', 'color' => '#fff', 'priority' => 0, 'mode' => 0]);
        DB::table('torrent_tags')->insert([
            'torrent_id' => $torrent->id,
            'tag_id' => $tagId1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tagId2 = (int) DB::table('tags')->insertGetId(['name' => 'Tag2', 'color' => '#fff', 'priority' => 0, 'mode' => 0]);
        $this->repository->syncTags($torrent->id, [$tagId2], true);

        $this->assertSame(1, DB::table('torrent_tags')->where('torrent_id', $torrent->id)->count());
        $this->assertSame(1, DB::table('torrent_tags')->where('torrent_id', $torrent->id)->where('tag_id', $tagId2)->count());
    }

    public function test_sync_tags_appends_when_remove_false(): void
    {
        /** @var User $user */
        $user = User::factory()->class(UserClass::STAFFLEADER->value)->create();
        Auth::login($user);

        /** @var Torrent $torrent */
        $torrent = Torrent::factory()->create();
        $tagId1 = (int) DB::table('tags')->insertGetId(['name' => 'Tag1', 'color' => '#fff', 'priority' => 0, 'mode' => 0]);
        DB::table('torrent_tags')->insert([
            'torrent_id' => $torrent->id,
            'tag_id' => $tagId1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tagId2 = (int) DB::table('tags')->insertGetId(['name' => 'Tag2', 'color' => '#fff', 'priority' => 0, 'mode' => 0]);
        $this->repository->syncTags($torrent->id, [$tagId2], false);

        $this->assertSame(2, DB::table('torrent_tags')->where('torrent_id', $torrent->id)->count());
    }

    public function test_set_pos_state_updates_torrent(): void
    {
        /** @var User $user */
        $user = User::factory()->class(UserClass::STAFFLEADER->value)->create();
        Auth::login($user);

        /** @var Torrent $torrent */
        $torrent = Torrent::factory()->create();

        $affected = $this->repository->setPosState($torrent->id, TorrentPosState::STICKY_FIRST->value);

        $this->assertSame(1, $affected);
        $torrent->refresh();
        $this->assertSame(TorrentPosState::STICKY_FIRST->value, (string) $torrent->pos_state);
    }

    public function test_set_pos_state_resets_until_when_none(): void
    {
        /** @var User $user */
        $user = User::factory()->class(UserClass::STAFFLEADER->value)->create();
        Auth::login($user);

        /** @var Torrent $torrent */
        $torrent = Torrent::factory()->create(['pos_state_until' => now()->addDay()]);

        $this->repository->setPosState($torrent->id, TorrentPosState::NONE->value);

        $torrent->refresh();
        $this->assertSame(TorrentPosState::NONE->value, (string) $torrent->pos_state);
        $this->assertNull($torrent->pos_state_until);
    }

    public function test_set_pos_state_resets_when_until_is_past(): void
    {
        /** @var User $user */
        $user = User::factory()->class(UserClass::STAFFLEADER->value)->create();
        Auth::login($user);

        /** @var Torrent $torrent */
        $torrent = Torrent::factory()->create();

        $this->repository->setPosState($torrent->id, TorrentPosState::STICKY_FIRST->value, now()->subDay()->toDateTimeString());

        $torrent->refresh();
        $this->assertSame(TorrentPosState::NONE->value, (string) $torrent->pos_state);
    }

    public function test_set_pos_state_supports_array_of_ids(): void
    {
        /** @var User $user */
        $user = User::factory()->class(UserClass::STAFFLEADER->value)->create();
        Auth::login($user);

        /** @var Torrent $torrent1 */
        $torrent1 = Torrent::factory()->create();
        /** @var Torrent $torrent2 */
        $torrent2 = Torrent::factory()->create();

        $affected = $this->repository->setPosState([$torrent1->id, $torrent2->id], TorrentPosState::STICKY_FIRST->value);

        $this->assertSame(2, $affected);
    }

    public function test_set_hr_updates_torrent(): void
    {
        /** @var User $user */
        $user = User::factory()->class(UserClass::STAFFLEADER->value)->create();
        Auth::login($user);

        /** @var Torrent $torrent */
        $torrent = Torrent::factory()->create(['hr' => 0]);

        $affected = $this->repository->setHr($torrent->id, 1);

        $this->assertSame(1, $affected);
        $torrent->refresh();
        $this->assertSame(1, (int) $torrent->hr);
    }

    public function test_set_hr_throws_for_invalid_status(): void
    {
        /** @var User $user */
        $user = User::factory()->class(UserClass::STAFFLEADER->value)->create();
        Auth::login($user);

        /** @var Torrent $torrent */
        $torrent = Torrent::factory()->create();

        $this->expectException(\InvalidArgumentException::class);

        $this->repository->setHr($torrent->id, 999);
    }

    public function test_set_sp_state_updates_torrent_with_global_time(): void
    {
        /** @var User $user */
        $user = User::factory()->class(UserClass::STAFFLEADER->value)->create();
        Auth::login($user);

        /** @var Torrent $torrent */
        $torrent = Torrent::factory()->create();

        $affected = $this->repository->setSpState($torrent->id, TorrentPromotion::TWO_TIMES_UP->value, PromotionTimeType::GLOBAL->value);

        $this->assertSame(1, $affected);
        $torrent->refresh();
        $this->assertSame(TorrentPromotion::TWO_TIMES_UP->value, (int) $torrent->sp_state);
    }

    public function test_set_sp_state_throws_for_invalid_sp_state(): void
    {
        /** @var User $user */
        $user = User::factory()->class(UserClass::STAFFLEADER->value)->create();
        Auth::login($user);

        /** @var Torrent $torrent */
        $torrent = Torrent::factory()->create();

        $this->expectException(\InvalidArgumentException::class);

        $this->repository->setSpState($torrent->id, 999, PromotionTimeType::GLOBAL->value);
    }

    public function test_set_sp_state_throws_for_invalid_time_type(): void
    {
        /** @var User $user */
        $user = User::factory()->class(UserClass::STAFFLEADER->value)->create();
        Auth::login($user);

        /** @var Torrent $torrent */
        $torrent = Torrent::factory()->create();

        $this->expectException(\InvalidArgumentException::class);

        $this->repository->setSpState($torrent->id, TorrentPromotion::TWO_TIMES_UP->value, 999);
    }

    public function test_set_sp_state_with_deadline_requires_valid_until(): void
    {
        /** @var User $user */
        $user = User::factory()->class(UserClass::STAFFLEADER->value)->create();
        Auth::login($user);

        /** @var Torrent $torrent */
        $torrent = Torrent::factory()->create();

        $this->expectException(\InvalidArgumentException::class);

        $this->repository->setSpState($torrent->id, TorrentPromotion::TWO_TIMES_UP->value, PromotionTimeType::DEADLINE->value, null);
    }

    public function test_set_sp_state_with_deadline_and_future_until(): void
    {
        /** @var User $user */
        $user = User::factory()->class(UserClass::STAFFLEADER->value)->create();
        Auth::login($user);

        /** @var Torrent $torrent */
        $torrent = Torrent::factory()->create();

        $affected = $this->repository->setSpState(
            $torrent->id,
            TorrentPromotion::TWO_TIMES_UP->value,
            PromotionTimeType::DEADLINE->value,
            now()->addDays(7)->toDateTimeString()
        );

        $this->assertSame(1, $affected);
        $torrent->refresh();
        $this->assertNotNull($torrent->promotion_until);
    }

    public function test_approval_sets_status_to_allow(): void
    {
        /** @var User $user */
        $user = User::factory()->class(UserClass::STAFFLEADER->value)->create();
        Auth::login($user);

        /** @var Torrent $torrent */
        $torrent = Torrent::factory()->create([
            'approval_status' => TorrentApprovalStatus::NONE->value,
            'banned' => 1,
            'visible' => 0,
        ]);

        $result = $this->repository->approval($user, [
            'torrent_id' => $torrent->id,
            'approval_status' => TorrentApprovalStatus::ALLOW->value,
            'comment' => 'approved',
        ]);

        $this->assertSame(TorrentApprovalStatus::ALLOW->value, $result['approval_status']);
        $torrent->refresh();
        $this->assertSame(TorrentApprovalStatus::ALLOW->value, (int) $torrent->approval_status);
        $this->assertSame(0, (int) $torrent->banned);
        $this->assertSame(1, (int) $torrent->visible);
    }

    public function test_approval_sets_status_to_deny(): void
    {
        /** @var User $user */
        $user = User::factory()->class(UserClass::STAFFLEADER->value)->create();
        Auth::login($user);

        /** @var Torrent $torrent */
        $torrent = Torrent::factory()->create([
            'approval_status' => TorrentApprovalStatus::ALLOW->value,
            'banned' => 0,
            'visible' => 1,
        ]);

        $this->repository->approval($user, [
            'torrent_id' => $torrent->id,
            'approval_status' => TorrentApprovalStatus::DENY->value,
            'comment' => 'denied',
        ]);

        $torrent->refresh();
        $this->assertSame(TorrentApprovalStatus::DENY->value, (int) $torrent->approval_status);
        $this->assertSame(1, (int) $torrent->banned);
        $this->assertSame(0, (int) $torrent->visible);
    }

    public function test_approval_returns_unchanged_when_same_status_and_comment(): void
    {
        /** @var User $user */
        $user = User::factory()->class(UserClass::STAFFLEADER->value)->create();
        Auth::login($user);

        /** @var Torrent $torrent */
        $torrent = Torrent::factory()->create(['approval_status' => TorrentApprovalStatus::ALLOW->value]);

        DB::table('torrent_operation_logs')->insert([
            'torrent_id' => $torrent->id,
            'uid' => $user->id,
            'action_type' => 'approval_allow',
            'comment' => 'same comment',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = $this->repository->approval($user, [
            'torrent_id' => $torrent->id,
            'approval_status' => TorrentApprovalStatus::ALLOW->value,
            'comment' => 'same comment',
        ]);

        $this->assertSame(TorrentApprovalStatus::ALLOW->value, $result['approval_status']);
    }

    public function test_approval_throws_for_invalid_status(): void
    {
        /** @var User $user */
        $user = User::factory()->class(UserClass::STAFFLEADER->value)->create();
        Auth::login($user);

        /** @var Torrent $torrent */
        $torrent = Torrent::factory()->create();

        $this->expectException(\InvalidArgumentException::class);

        $this->repository->approval($user, [
            'torrent_id' => $torrent->id,
            'approval_status' => 999,
            'comment' => '',
        ]);
    }

    private function setSetting(string $name, string $value): void
    {
        DB::table('settings')->updateOrInsert(
            ['name' => $name],
            ['value' => $value, 'autoload' => 1, 'updated_at' => now()]
        );
        Settings::resetCache();
    }
}
