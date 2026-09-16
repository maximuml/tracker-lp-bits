<?php

declare(strict_types=1);

namespace Tests\Integration\Models;

use App\Enums\TorrentOperationAction;
use App\Models\TorrentOperationLog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Unit tests for TorrentOperationLog::add() owner notification.
 *
 * Regression: editing a torrent whose owner no longer exists made
 * notifyUser() insert a messages row with receiver=0, violating the
 * messages_receiver_foreign constraint.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class TorrentOperationLogTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('messages')->delete();
        DB::table('torrent_operation_logs')->delete();
        DB::table('torrents')->delete();
        DB::table('users')->delete();
    }

    protected function tearDown(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
        parent::tearDown();
    }

    public function test_notify_user_skipped_when_owner_missing(): void
    {
        $torrentId = $this->createTorrent(['owner' => 999999]);
        $operatorId = $this->createUser();

        TorrentOperationLog::add([
            'torrent_id' => $torrentId,
            'uid' => $operatorId,
            'action_type' => TorrentOperationAction::EDIT->value,
            'comment' => '',
        ], true);

        $this->assertSame(0, DB::table('messages')->count());
    }

    public function test_notify_user_sends_message_to_existing_owner(): void
    {
        $ownerId = $this->createUser(['username' => 'owner_'.uniqid()]);
        $operatorId = $this->createUser(['username' => 'operator_'.uniqid()]);
        $torrentId = $this->createTorrent(['owner' => $ownerId]);

        TorrentOperationLog::add([
            'torrent_id' => $torrentId,
            'uid' => $operatorId,
            'action_type' => TorrentOperationAction::EDIT->value,
            'comment' => '',
        ], true);

        $message = DB::table('messages')->first();
        $this->assertNotNull($message);
        $this->assertSame($ownerId, (int) $message->receiver);
    }

    /** @param  array<string, mixed>  $overrides */
    private function createTorrent(array $overrides = []): int
    {
        return (int) DB::table('torrents')->insertGetId(array_merge([
            'name' => 'Test Torrent',
            'filename' => 'test.torrent',
            'save_as' => 'test',
            'category' => 1,
            'size' => 1024,
            'type' => 0,
            'numfiles' => 1,
            'owner' => 1,
            'info_hash' => random_bytes(20),
            'visible' => 1,
            'banned' => 0,
            'views' => 0,
            'added' => now()->toDateTimeString(),
        ], $overrides));
    }

    /** @param  array<string, mixed>  $overrides */
    private function createUser(array $overrides = []): int
    {
        return (int) DB::table('users')->insertGetId(array_merge([
            'username' => 'user_'.uniqid(),
            'email' => 'user_'.uniqid().'@test.com',
            'passhash' => 'hash',
            'secret' => 'secret',
            'passkey' => str_pad((string) mt_rand(1, 999999), 32, '0'),
            'class' => 1,
            'added' => now()->toDateTimeString(),
            'last_access' => now()->toDateTimeString(),
            'status' => 1,
            'enabled' => 1,
            'parked' => 0,
            'downloadpos' => 1,
            'seedbonus' => 100.0,
            'acceptpms' => 0,
            'notifs' => '',
            'last_pm' => null,
        ], $overrides));
    }
}
