<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Jobs\BulkUserIncrementJob;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * @covers \App\Jobs\BulkUserIncrementJob
 *
 * @group w1-07
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class BulkUserIncrementJobTest extends TestCase
{
    use DatabaseTransactions;

    private const CONFIRMED = 1;

    private const PENDING = 0;

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
            'uploaded' => 1000,
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

    public function test_job_increments_uploaded_for_matching_users(): void
    {
        $actor = $this->createUser(['class' => 99]);
        $user1 = $this->createUser(['class' => 1, 'uploaded' => 1000]);
        $user2 = $this->createUser(['class' => 1, 'uploaded' => 2000]);
        $otherClass = $this->createUser(['class' => 5, 'uploaded' => 5000]);

        $job = new BulkUserIncrementJob(
            classIds: [1],
            field: 'uploaded',
            amount: 500,
            actorId: $actor,
            idempotencyKey: 'test-key-1',
            message: ['sender' => null, 'subject' => '', 'msg' => ''],
            dryRun: false,
        );
        $job->handle();

        $this->assertSame(1500, (int) DB::table('users')->where('id', $user1)->value('uploaded'));
        $this->assertSame(2500, (int) DB::table('users')->where('id', $user2)->value('uploaded'));
        $this->assertSame(5000, (int) DB::table('users')->where('id', $otherClass)->value('uploaded'));
    }

    public function test_job_sends_messages_to_affected_users(): void
    {
        $actor = $this->createUser(['class' => 99]);
        $user = $this->createUser(['class' => 2, 'seedbonus' => 100]);

        $job = new BulkUserIncrementJob(
            classIds: [2],
            field: 'seedbonus',
            amount: 50,
            actorId: $actor,
            idempotencyKey: 'test-key-2',
            message: ['sender' => $actor, 'subject' => 'Bonus added', 'msg' => 'You got bonus'],
            dryRun: false,
        );
        $job->handle();

        $this->assertSame(150, (int) DB::table('users')->where('id', $user)->value('seedbonus'));
        $this->assertSame(1, DB::table('messages')->where('receiver', $user)->count());
        $msg = DB::table('messages')->where('receiver', $user)->first();
        $this->assertSame('Bonus added', $msg->subject);
        $this->assertSame('You got bonus', $msg->msg);
    }

    public function test_dry_run_does_not_modify_users_or_send_messages(): void
    {
        $actor = $this->createUser(['class' => 98]);
        $user = $this->createUser(['class' => 3, 'uploaded' => 1000]);

        $job = new BulkUserIncrementJob(
            classIds: [3],
            field: 'uploaded',
            amount: 500,
            actorId: $actor,
            idempotencyKey: 'test-key-dry',
            message: ['sender' => $actor, 'subject' => 'Test', 'msg' => 'Dry run'],
            dryRun: true,
        );
        $job->handle();

        $this->assertSame(1000, (int) DB::table('users')->where('id', $user)->value('uploaded'));
        $this->assertSame(0, DB::table('messages')->where('receiver', $user)->count());
    }

    public function test_dry_run_does_not_write_audit_log(): void
    {
        $actor = $this->createUser(['class' => 97]);
        $this->createUser(['class' => 4]);

        $job = new BulkUserIncrementJob(
            classIds: [4],
            field: 'seedbonus',
            amount: 10,
            actorId: $actor,
            idempotencyKey: 'test-key-dry-audit',
            message: ['sender' => null, 'subject' => '', 'msg' => ''],
            dryRun: true,
        );
        $job->handle();

        $this->assertSame(0, DB::table('user_modify_logs')->where('user_id', $actor)->count());
    }

    public function test_job_writes_audit_log(): void
    {
        $actor = $this->createUser(['class' => 96]);
        $this->createUser(['class' => 5]);

        $job = new BulkUserIncrementJob(
            classIds: [5],
            field: 'seedbonus',
            amount: 10,
            actorId: $actor,
            idempotencyKey: 'test-key-audit',
            message: ['sender' => null, 'subject' => '', 'msg' => ''],
            dryRun: false,
        );
        $job->handle();

        $this->assertSame(1, DB::table('user_modify_logs')->where('user_id', $actor)->count());
        $log = DB::table('user_modify_logs')->where('user_id', $actor)->first();
        $this->assertStringContainsString('Bulk increment seedbonus by 10', $log->content);
        $this->assertStringContainsString('idempotency=test-key-audit', $log->content);
    }

    public function test_idempotency_prevents_duplicate_execution(): void
    {
        $actor = $this->createUser(['class' => 95]);
        $user = $this->createUser(['class' => 6, 'uploaded' => 1000]);

        $job = new BulkUserIncrementJob(
            classIds: [6],
            field: 'uploaded',
            amount: 500,
            actorId: $actor,
            idempotencyKey: 'dup-key',
            message: ['sender' => null, 'subject' => '', 'msg' => ''],
            dryRun: false,
        );

        $job->handle();
        $this->assertSame(1500, (int) DB::table('users')->where('id', $user)->value('uploaded'));

        $job->handle();
        $this->assertSame(1500, (int) DB::table('users')->where('id', $user)->value('uploaded'));
    }

    public function test_job_skips_disabled_and_unconfirmed_users(): void
    {
        $actor = $this->createUser(['class' => 94]);
        $enabled = $this->createUser(['class' => 7, 'enabled' => true, 'status' => self::CONFIRMED, 'uploaded' => 1000]);
        $disabled = $this->createUser(['class' => 7, 'enabled' => false, 'status' => self::CONFIRMED, 'uploaded' => 2000]);
        $pending = $this->createUser(['class' => 7, 'enabled' => true, 'status' => self::PENDING, 'uploaded' => 3000]);

        $job = new BulkUserIncrementJob(
            classIds: [7],
            field: 'uploaded',
            amount: 100,
            actorId: $actor,
            idempotencyKey: 'test-key-filter',
            message: ['sender' => null, 'subject' => '', 'msg' => ''],
            dryRun: false,
        );
        $job->handle();

        $this->assertSame(1100, (int) DB::table('users')->where('id', $enabled)->value('uploaded'));
        $this->assertSame(2000, (int) DB::table('users')->where('id', $disabled)->value('uploaded'));
        $this->assertSame(3000, (int) DB::table('users')->where('id', $pending)->value('uploaded'));
    }

    protected function tearDown(): void
    {
        DB::table('user_modify_logs')->delete();
        parent::tearDown();
    }
}
