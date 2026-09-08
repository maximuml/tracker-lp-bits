<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Jobs\BulkUserMessageJob;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * @covers \App\Jobs\BulkUserMessageJob
 *
 * @group w1-07
 */
final class BulkUserMessageJobTest extends TestCase
{
    use DatabaseTransactions;

    private const CONFIRMED = 'confirmed';

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('user_modify_logs')->delete();
    }

    private function createUser(array $overrides = []): int
    {
        $row = array_merge([
            'username' => 'testuser_'.uniqid(),
            'email' => 'test_'.uniqid().'@example.com',
            'passhash' => 'x',
            'secret' => 'x',
            'editsecret' => '',
            'passkey' => bin2hex(random_bytes(16)),
            'class' => 1,
            'enabled' => true,
            'status' => self::CONFIRMED,
            'uploaded' => 0,
            'downloaded' => 0,
            'seedbonus' => 0,
            'invites' => 0,
            'added' => now()->toDateTimeString(),
            'last_access' => now()->toDateTimeString(),
            'stylesheet' => 1,
            'lang' => 1,
            'torrentsperpage' => 25,
            'topicsperpage' => 25,
            'postsperpage' => 25,
        ], $overrides);

        return DB::table('users')->insertGetId($row);
    }

    public function test_job_sends_messages_to_matching_users(): void
    {
        $actor = $this->createUser(['class' => 99]);
        $user1 = $this->createUser(['class' => 1]);
        $user2 = $this->createUser(['class' => 1]);
        $otherClass = $this->createUser(['class' => 5]);

        $job = new BulkUserMessageJob(
            classIds: [1],
            senderId: $actor,
            subject: 'Staff announcement',
            body: 'Important message',
            actorId: $actor,
            idempotencyKey: 'msg-key-1',
            dryRun: false,
        );
        $job->handle();

        $this->assertSame(1, DB::table('messages')->where('receiver', $user1)->count());
        $this->assertSame(1, DB::table('messages')->where('receiver', $user2)->count());
        $this->assertSame(0, DB::table('messages')->where('receiver', $otherClass)->count());

        $msg = DB::table('messages')->where('receiver', $user1)->first();
        $this->assertSame($actor, (int) $msg->sender);
        $this->assertSame('Staff announcement', $msg->subject);
        $this->assertSame('Important message', $msg->msg);
    }

    public function test_dry_run_does_not_send_messages(): void
    {
        $actor = $this->createUser(['class' => 98]);
        $user = $this->createUser(['class' => 2]);

        $job = new BulkUserMessageJob(
            classIds: [2],
            senderId: $actor,
            subject: 'Test',
            body: 'Dry run',
            actorId: $actor,
            idempotencyKey: 'msg-key-dry',
            dryRun: true,
        );
        $job->handle();

        $this->assertSame(0, DB::table('messages')->where('receiver', $user)->count());
    }

    public function test_dry_run_does_not_write_audit_log(): void
    {
        $actor = $this->createUser(['class' => 97]);
        $this->createUser(['class' => 3]);

        $job = new BulkUserMessageJob(
            classIds: [3],
            senderId: $actor,
            subject: 'Test',
            body: 'Dry',
            actorId: $actor,
            idempotencyKey: 'msg-key-dry-audit',
            dryRun: true,
        );
        $job->handle();

        $this->assertSame(0, DB::table('user_modify_logs')->where('user_id', $actor)->count());
    }

    public function test_job_writes_audit_log(): void
    {
        $actor = $this->createUser(['class' => 96]);
        $this->createUser(['class' => 4]);

        $job = new BulkUserMessageJob(
            classIds: [4],
            senderId: $actor,
            subject: 'Audit test',
            body: 'Audit body',
            actorId: $actor,
            idempotencyKey: 'msg-key-audit',
            dryRun: false,
        );
        $job->handle();

        $this->assertSame(1, DB::table('user_modify_logs')->where('user_id', $actor)->count());
        $log = DB::table('user_modify_logs')->where('user_id', $actor)->first();
        $this->assertStringContainsString('Bulk staff message', $log->content);
        $this->assertStringContainsString('idempotency=msg-key-audit', $log->content);
    }

    public function test_idempotency_prevents_duplicate_messages(): void
    {
        $actor = $this->createUser(['class' => 95]);
        $user = $this->createUser(['class' => 5]);

        $job = new BulkUserMessageJob(
            classIds: [5],
            senderId: $actor,
            subject: 'Dup test',
            body: 'Dup body',
            actorId: $actor,
            idempotencyKey: 'dup-msg-key',
            dryRun: false,
        );

        $job->handle();
        $this->assertSame(1, DB::table('messages')->where('receiver', $user)->count());

        $job->handle();
        $this->assertSame(1, DB::table('messages')->where('receiver', $user)->count());
    }

    public function test_job_skips_disabled_and_unconfirmed_users(): void
    {
        $actor = $this->createUser(['class' => 94]);
        $enabled = $this->createUser(['class' => 6, 'enabled' => true, 'status' => self::CONFIRMED]);
        $disabled = $this->createUser(['class' => 6, 'enabled' => false, 'status' => self::CONFIRMED]);
        $pending = $this->createUser(['class' => 6, 'enabled' => true, 'status' => 'pending']);

        $job = new BulkUserMessageJob(
            classIds: [6],
            senderId: $actor,
            subject: 'Filter test',
            body: 'Body',
            actorId: $actor,
            idempotencyKey: 'msg-key-filter',
            dryRun: false,
        );
        $job->handle();

        $this->assertSame(1, DB::table('messages')->where('receiver', $enabled)->count());
        $this->assertSame(0, DB::table('messages')->where('receiver', $disabled)->count());
        $this->assertSame(0, DB::table('messages')->where('receiver', $pending)->count());
    }

    protected function tearDown(): void
    {
        DB::table('user_modify_logs')->delete();
        parent::tearDown();
    }
}
