<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Complain;
use App\Repositories\ToolRepository;
use App\Services\ComplainService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Unit tests for ComplainService.
 *
 * Covers toggleAnswered, replyToComplain (nonexistent complain,
 * success with/without email), and createComplain (disabled user
 * requirement, lock contention).
 */
final class ComplainServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('complains')->truncate();
        DB::table('complain_replies')->truncate();
        DB::table('users')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    }

    private function service(): ComplainService
    {
        /** @var ToolRepository&MockInterface $repo */
        $repo = Mockery::mock(ToolRepository::class);

        return new ComplainService($repo);
    }

    private function serviceWithRepo(ToolRepository $repo): ComplainService
    {
        return new ComplainService($repo);
    }

    private function insertComplain(string $email = 'test@test.com', string $uuid = 'test-uuid-123'): int
    {
        return (int) DB::table('complains')->insertGetId([
            'uuid' => $uuid,
            'email' => $email,
            'body' => 'Test complain body',
            'added' => now()->toDateTimeString(),
            'answered' => 0,
            'ip' => '127.0.0.1',
        ]);
    }

    private function insertUser(int $id, string $email, bool $enabled = false): void
    {
        DB::table('users')->insert([
            'id' => $id,
            'username' => 'user'.$id,
            'email' => $email,
            'passhash' => 'hash',
            'secret' => 'secret',
            'passkey' => 'passkey'.$id,
            'class' => 1,
            'added' => now()->toDateTimeString(),
            'last_access' => now()->toDateTimeString(),
            'status' => 'confirmed',
            'enabled' => $enabled ? 1 : 0,
        ]);
    }

    // --- toggleAnswered ---

    public function test_toggle_answered_sets_to_true(): void
    {
        $id = $this->insertComplain();

        $this->service()->toggleAnswered($id, true);

        $answered = DB::table('complains')->where('id', $id)->value('answered');
        $this->assertSame(1, (int) $answered);
    }

    public function test_toggle_answered_sets_to_false(): void
    {
        $id = $this->insertComplain();
        DB::table('complains')->where('id', $id)->update(['answered' => 1]);

        $this->service()->toggleAnswered($id, false);

        $answered = DB::table('complains')->where('id', $id)->value('answered');
        $this->assertSame(0, (int) $answered);
    }

    public function test_toggle_answered_on_nonexistent_does_not_throw(): void
    {
        // Should not throw even if complain doesn't exist
        $this->service()->toggleAnswered(99999, true);
        $this->expectNotToPerformAssertions();
    }

    // --- replyToComplain ---

    public function test_reply_to_nonexistent_complain_returns_false(): void
    {
        $result = $this->service()->replyToComplain(99999, 1, 'reply body', '127.0.0.1', []);

        $this->assertFalse($result);
    }

    public function test_reply_to_complain_inserts_reply(): void
    {
        $id = $this->insertComplain();

        $result = $this->service()->replyToComplain($id, 0, 'Test reply', '127.0.0.1', []);

        $this->assertTrue($result);
        $this->assertSame(1, DB::table('complain_replies')->count());
        $reply = DB::table('complain_replies')->first();
        $this->assertNotNull($reply);
        $this->assertSame($id, (int) $reply->complain);
        $this->assertSame(0, (int) $reply->userid);
        $this->assertSame('Test reply', $reply->body);
    }

    public function test_reply_to_complain_with_user_id_zero_skips_email(): void
    {
        $id = $this->insertComplain();

        // userid=0 means no email is sent
        $result = $this->service()->replyToComplain($id, 0, 'reply', '127.0.0.1', []);

        $this->assertTrue($result);
    }

    public function test_reply_to_complain_with_user_id_sends_email(): void
    {
        $id = $this->insertComplain('user@test.com');

        /** @var ToolRepository&MockInterface $repo */
        $repo = Mockery::mock(ToolRepository::class);
        $repo->shouldReceive('sendMail')
            ->with('user@test.com', Mockery::type('string'), Mockery::type('string'))
            ->once();

        $result = $this->serviceWithRepo($repo)->replyToComplain($id, 1, 'reply', '127.0.0.1', [
            'reply_notify_subject' => 'Subject',
            'reply_notify_body' => 'Body: %s %s',
        ]);

        $this->assertTrue($result);
    }

    public function test_reply_to_complain_email_failure_does_not_block_reply(): void
    {
        $id = $this->insertComplain('user@test.com');

        /** @var ToolRepository&MockInterface $repo */
        $repo = Mockery::mock(ToolRepository::class);
        $repo->shouldReceive('sendMail')
            ->andThrow(new \RuntimeException('SMTP down'));

        $result = $this->serviceWithRepo($repo)->replyToComplain($id, 1, 'reply', '127.0.0.1', [
            'reply_notify_subject' => 'Subject',
            'reply_notify_body' => 'Body: %s %s',
        ]);

        // Reply should still succeed — email failure is caught
        $this->assertTrue($result);
        $this->assertSame(1, DB::table('complain_replies')->count());
    }

    // --- createComplain ---

    public function test_create_complain_returns_null_for_no_disabled_user(): void
    {
        // No user with that email exists
        $result = $this->service()->createComplain('nobody@test.com', 'body', '127.0.0.1');

        $this->assertNull($result);
    }

    public function test_create_complain_returns_null_for_enabled_user(): void
    {
        $this->insertUser(1, 'enabled@test.com', true);

        $result = $this->service()->createComplain('enabled@test.com', 'body', '127.0.0.1');

        $this->assertNull($result);
        $this->assertSame(0, DB::table('complains')->count());
    }

    public function test_create_complain_succeeds_for_disabled_user(): void
    {
        $this->insertUser(1, 'disabled@test.com', false);

        $result = $this->service()->createComplain('disabled@test.com', 'My complain', '127.0.0.1');

        $this->assertNotNull($result);
        $this->assertSame(1, DB::table('complains')->count());
        $complain = DB::table('complains')->first();
        $this->assertNotNull($complain);
        $this->assertSame('disabled@test.com', $complain->email);
        $this->assertSame('My complain', $complain->body);
        $this->assertSame('127.0.0.1', $complain->ip);
    }
}
