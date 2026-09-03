<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\User;
use App\Repositories\InviteRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Unit tests for InviteRepository.
 *
 * Covers getUserArray(), countPendingInvitees(), countInvitees(),
 * getInvitees(), countInvites(), and getInvites().
 */
final class InviteRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private InviteRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('invites')->delete();
        DB::table('torrents')->delete();
        DB::table('users')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        $this->repository = new InviteRepository;
    }

    public function test_get_user_array_returns_null_when_user_not_found(): void
    {
        $this->assertNull($this->repository->getUserArray(99999));
    }

    public function test_get_user_array_returns_array_when_user_found(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $result = $this->repository->getUserArray($user->id);

        $this->assertIsArray($result);
        $this->assertSame($user->id, (int) $result['id']);
        $this->assertSame($user->username, $result['username']);
    }

    public function test_count_pending_invitees_returns_zero_when_none(): void
    {
        $this->assertSame(0, $this->repository->countPendingInvitees(123));
    }

    public function test_count_pending_invitees_counts_only_pending(): void
    {
        /** @var User $inviter */
        $inviter = User::factory()->create();

        User::factory()->create(['invited_by' => $inviter->id, 'status' => 'pending']);
        User::factory()->create(['invited_by' => $inviter->id, 'status' => 'pending']);
        User::factory()->create(['invited_by' => $inviter->id, 'status' => 'confirmed']);

        $this->assertSame(2, $this->repository->countPendingInvitees($inviter->id));
    }

    public function test_count_pending_invitees_only_counts_given_inviter(): void
    {
        /** @var User $inviter */
        $inviter = User::factory()->create();
        /** @var User $other */
        $other = User::factory()->create();

        User::factory()->create(['invited_by' => $inviter->id, 'status' => 'pending']);
        User::factory()->create(['invited_by' => $other->id, 'status' => 'pending']);

        $this->assertSame(1, $this->repository->countPendingInvitees($inviter->id));
    }

    public function test_count_invitees_without_filters_returns_all(): void
    {
        /** @var User $inviter */
        $inviter = User::factory()->create();

        User::factory()->create(['invited_by' => $inviter->id, 'status' => 'confirmed']);
        User::factory()->create(['invited_by' => $inviter->id, 'status' => 'pending']);

        $this->assertSame(2, $this->repository->countInvitees($inviter->id, []));
    }

    public function test_count_invitees_with_status_filter(): void
    {
        /** @var User $inviter */
        $inviter = User::factory()->create();

        User::factory()->create(['invited_by' => $inviter->id, 'status' => 'confirmed']);
        User::factory()->create(['invited_by' => $inviter->id, 'status' => 'pending']);

        $this->assertSame(1, $this->repository->countInvitees($inviter->id, ['status' => 'pending']));
    }

    public function test_count_invitees_with_enabled_filter(): void
    {
        /** @var User $inviter */
        $inviter = User::factory()->create();

        User::factory()->create(['invited_by' => $inviter->id, 'enabled' => 1]);
        User::factory()->disabled()->create(['invited_by' => $inviter->id]);

        $this->assertSame(1, $this->repository->countInvitees($inviter->id, ['enabled' => 'yes']));
        $this->assertSame(1, $this->repository->countInvitees($inviter->id, ['enabled' => 'no']));
    }

    public function test_get_invitees_returns_paginated_rows_with_torrent_count(): void
    {
        /** @var User $inviter */
        $inviter = User::factory()->create();
        /** @var User $invitee */
        $invitee = User::factory()->create(['invited_by' => $inviter->id]);

        DB::table('torrents')->insert([
            'name' => 'Test Torrent',
            'filename' => 'test.torrent',
            'save_as' => 'test',
            'category' => 1,
            'size' => 1024,
            'type' => 'single',
            'numfiles' => 1,
            'owner' => $invitee->id,
            'info_hash' => random_bytes(20),
            'visible' => 1,
            'banned' => 0,
            'added' => now()->toDateTimeString(),
        ]);

        $result = $this->repository->getInvitees($inviter->id, [], 0, 25);

        $this->assertCount(1, $result);
        $this->assertSame($invitee->id, (int) $result[0]['id']);
        $this->assertSame(1, (int) $result[0]['torrent_count']);
    }

    public function test_get_invitees_respects_offset_and_limit(): void
    {
        /** @var User $inviter */
        $inviter = User::factory()->create();

        for ($i = 0; $i < 3; $i++) {
            User::factory()->create(['invited_by' => $inviter->id]);
        }

        $page1 = $this->repository->getInvitees($inviter->id, [], 0, 2);
        $page2 = $this->repository->getInvitees($inviter->id, [], 2, 2);

        $this->assertCount(2, $page1);
        $this->assertCount(1, $page2);
    }

    public function test_count_invites_returns_all_when_type_is_empty(): void
    {
        DB::table('invites')->insert([
            ['inviter' => 10, 'invitee' => 'user@example.com', 'hash' => md5('a'), 'time_invited' => now()->toDateTimeString(), 'expired_at' => null],
            ['inviter' => 10, 'invitee' => '', 'hash' => md5('b'), 'time_invited' => now()->toDateTimeString(), 'expired_at' => now()->addDay()->toDateTimeString()],
        ]);

        $this->assertSame(2, $this->repository->countInvites(10, ''));
    }

    public function test_count_invites_sent(): void
    {
        DB::table('invites')->insert([
            ['inviter' => 10, 'invitee' => 'user@example.com', 'hash' => md5('a'), 'time_invited' => now()->toDateTimeString(), 'expired_at' => null],
            ['inviter' => 10, 'invitee' => '', 'hash' => md5('b'), 'time_invited' => now()->toDateTimeString(), 'expired_at' => now()->addDay()->toDateTimeString()],
        ]);

        $this->assertSame(1, $this->repository->countInvites(10, 'sent'));
    }

    public function test_count_invites_tmp(): void
    {
        DB::table('invites')->insert([
            ['inviter' => 10, 'invitee' => 'user@example.com', 'hash' => md5('a'), 'time_invited' => now()->toDateTimeString(), 'expired_at' => null],
            ['inviter' => 10, 'invitee' => '', 'hash' => md5('b'), 'time_invited' => now()->toDateTimeString(), 'expired_at' => now()->addDay()->toDateTimeString()],
            ['inviter' => 10, 'invitee' => '', 'hash' => md5('c'), 'time_invited' => now()->toDateTimeString(), 'expired_at' => null],
        ]);

        $this->assertSame(1, $this->repository->countInvites(10, 'tmp'));
    }

    public function test_count_invites_only_counts_given_inviter(): void
    {
        DB::table('invites')->insert([
            ['inviter' => 10, 'invitee' => 'user@example.com', 'hash' => md5('a'), 'time_invited' => now()->toDateTimeString(), 'expired_at' => null],
            ['inviter' => 20, 'invitee' => 'other@example.com', 'hash' => md5('b'), 'time_invited' => now()->toDateTimeString(), 'expired_at' => null],
        ]);

        $this->assertSame(1, $this->repository->countInvites(10, 'sent'));
    }

    public function test_get_invites_returns_paginated_rows(): void
    {
        DB::table('invites')->insert([
            ['inviter' => 10, 'invitee' => 'user@example.com', 'hash' => md5('a'), 'time_invited' => now()->toDateTimeString(), 'expired_at' => null],
            ['inviter' => 10, 'invitee' => '', 'hash' => md5('b'), 'time_invited' => now()->toDateTimeString(), 'expired_at' => now()->addDay()->toDateTimeString()],
        ]);

        $result = $this->repository->getInvites(10, 'sent', 0, 25);

        $this->assertCount(1, $result);
        $this->assertSame('user@example.com', $result[0]['invitee']);
    }

    public function test_get_invites_respects_offset_and_limit(): void
    {
        for ($i = 0; $i < 3; $i++) {
            DB::table('invites')->insert([
                'inviter' => 10,
                'invitee' => "user{$i}@example.com",
                'hash' => md5((string) $i),
                'time_invited' => now()->toDateTimeString(),
            ]);
        }

        $page1 = $this->repository->getInvites(10, 'sent', 0, 2);
        $page2 = $this->repository->getInvites(10, 'sent', 2, 2);

        $this->assertCount(2, $page1);
        $this->assertCount(1, $page2);
    }
}
