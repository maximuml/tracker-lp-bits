<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\NexusException;
use App\Models\Torrent;
use App\Models\User;
use App\Services\ThankService;
use App\Support\Config\SiteConfig;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

/**
 * Unit tests for ThankService.
 *
 * Covers thankTorrent: self-thank rejection, already-thanked rejection,
 * disabled owner rejection, successful thank with bonus grant, and
 * bonus increment verification.
 */
final class ThankServiceTest extends TestCase
{
    use DatabaseTransactions;

    private ThankService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::connection()->flushdb();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('thanks')->truncate();
        DB::table('torrents')->truncate();
        DB::table('users')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        $this->service = new ThankService;
    }

    /** @param  array<string, mixed>  $overrides */
    private function createUser(array $overrides = []): User
    {
        $id = (int) DB::table('users')->insertGetId(array_merge([
            'username' => 'user_'.uniqid(),
            'email' => 'user_'.uniqid().'@test.com',
            'passhash' => 'hash',
            'secret' => 'secret',
            'passkey' => str_pad((string) mt_rand(1, 999999), 32, '0'),
            'class' => 1,
            'added' => now()->toDateTimeString(),
            'last_access' => now()->toDateTimeString(),
            'status' => 'confirmed',
            'enabled' => 1,
            'parked' => 0,
            'downloadpos' => 1,
            'seedbonus' => 100.0,
        ], $overrides));

        return User::query()->findOrFail($id);
    }

    private function createTorrent(int $ownerId): Torrent
    {
        // Insert via DB to avoid Scout/MeiliSearch indexing on save
        $id = (int) DB::table('torrents')->insertGetId([
            'name' => 'Test Torrent',
            'filename' => 'test.torrent',
            'save_as' => 'test',
            'category' => 1,
            'size' => 1024,
            'type' => 'single',
            'numfiles' => 1,
            'owner' => $ownerId,
            'info_hash' => random_bytes(20),
            'visible' => 1,
            'banned' => 0,
            'added' => now()->toDateTimeString(),
        ]);

        return Torrent::query()->findOrFail($id);
    }

    // --- self-thank ---

    public function test_thank_self_throws_logic_exception(): void
    {
        $user = $this->createUser();
        $torrent = $this->createTorrent($user->id);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage("you can't thank to yourself");

        $this->service->thankTorrent($user, $torrent);
    }

    // --- already thanked ---

    public function test_thank_already_thanked_throws_logic_exception(): void
    {
        $user = $this->createUser();
        $owner = $this->createUser();
        $torrent = $this->createTorrent($owner->id);

        // Insert an existing thanks record
        DB::table('thanks')->insert([
            'torrentid' => $torrent->id,
            'userid' => $user->id,
        ]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('you already thank this torrent');

        $this->service->thankTorrent($user, $torrent);
    }

    // --- disabled owner ---

    public function test_thank_disabled_owner_throws_nexus_exception(): void
    {
        $user = $this->createUser();
        $owner = $this->createUser(['enabled' => 0]);
        $torrent = $this->createTorrent($owner->id);

        $this->expectException(NexusException::class);

        $this->service->thankTorrent($user, $torrent);
    }

    // --- successful thank ---

    public function test_thank_succeeds_and_creates_thanks_record(): void
    {
        $user = $this->createUser();
        $owner = $this->createUser();
        $torrent = $this->createTorrent($owner->id);

        $thank = $this->service->thankTorrent($user, $torrent);

        $this->assertSame($torrent->id, (int) $thank->torrentid);
        $this->assertSame($user->id, (int) $thank->userid);
        $this->assertSame(1, DB::table('thanks')->count());
    }

    public function test_thank_grants_saythanks_bonus_to_user(): void
    {
        $user = $this->createUser(['seedbonus' => 100.0]);
        $owner = $this->createUser(['seedbonus' => 50.0]);
        $torrent = $this->createTorrent($owner->id);

        $sayThanks = SiteConfig::current()->bonus->sayThanks(0.0);

        $this->service->thankTorrent($user, $torrent);

        $userBonus = (float) DB::table('users')->where('id', $user->id)->value('seedbonus');
        $this->assertSame(100.0 + $sayThanks, $userBonus);
    }

    public function test_thank_grants_receivethanks_bonus_to_owner(): void
    {
        $user = $this->createUser(['seedbonus' => 100.0]);
        $owner = $this->createUser(['seedbonus' => 50.0]);
        $torrent = $this->createTorrent($owner->id);

        $receiveThanks = SiteConfig::current()->bonus->receiveThanks(0.0);

        $this->service->thankTorrent($user, $torrent);

        $ownerBonus = (float) DB::table('users')->where('id', $owner->id)->value('seedbonus');
        $this->assertSame(50.0 + $receiveThanks, $ownerBonus);
    }

    public function test_thank_with_zero_receivethanks_does_not_increment_owner(): void
    {
        // receivethanks is 0 by default in the test DB
        $user = $this->createUser(['seedbonus' => 100.0]);
        $owner = $this->createUser(['seedbonus' => 50.0]);
        $torrent = $this->createTorrent($owner->id);

        $this->service->thankTorrent($user, $torrent);

        // Owner bonus should remain unchanged (receivethanks = 0)
        $ownerBonus = (float) DB::table('users')->where('id', $owner->id)->value('seedbonus');
        $this->assertSame(50.0, $ownerBonus);
    }
}
