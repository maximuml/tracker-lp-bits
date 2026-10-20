<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Exceptions\AuthenticationException;
use App\Models\User;
use App\Services\InviteValidator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Regression tests for invite handling fixes: a wrong-inviter probe
 * must not burn someone else's invite, and markConsumed() only fires
 * while the invite is still valid so a raced double-registration
 * cannot consume the same hash twice.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class InviteValidatorTest extends TestCase
{
    use DatabaseTransactions;

    private InviteValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = app(InviteValidator::class);
    }

    private function createInvite(int $inviterId, string $hash): int
    {
        return (int) DB::table('invites')->insertGetId([
            'inviter' => $inviterId,
            'invitee' => '',
            'hash' => $hash,
            'time_invited' => now()->toDateTimeString(),
            'valid' => 1,
        ]);
    }

    public function test_wrong_inviter_does_not_invalidate_the_invite(): void
    {
        /** @var User $owner */
        $owner = User::factory()->create();
        $hash = str_repeat('ab', 16);
        $inviteId = $this->createInvite((int) $owner->id, $hash);

        try {
            $this->validator->validate($hash, (int) $owner->id + 999, 'en');
            $this->fail('Expected AuthenticationException for wrong inviter');
        } catch (AuthenticationException) {
            // expected
        }

        $this->assertSame(1, (int) DB::table('invites')->where('id', $inviteId)->value('valid'));
    }

    public function test_consume_marks_invite_invalid_once(): void
    {
        /** @var User $owner */
        $owner = User::factory()->create();
        $hash = str_repeat('cd', 16);
        $inviteId = $this->createInvite((int) $owner->id, $hash);
        $invite = $this->validator->validate($hash, (int) $owner->id, 'en');

        $this->validator->consume($invite, 12345, 'a@b.c', 'newuser');
        $this->assertSame(0, (int) DB::table('invites')->where('id', $inviteId)->value('valid'));
        $this->assertSame(12345, (int) DB::table('invites')->where('id', $inviteId)->value('invitee_register_uid'));
    }

    public function test_consume_on_already_consumed_invite_throws(): void
    {
        /** @var User $owner */
        $owner = User::factory()->create();
        $hash = str_repeat('ef', 16);
        $inviteId = $this->createInvite((int) $owner->id, $hash);
        $invite = $this->validator->validate($hash, (int) $owner->id, 'en');

        $this->validator->consume($invite, 111, 'a@b.c', 'first');

        $this->expectException(AuthenticationException::class);
        $this->validator->consume($invite, 222, 'x@y.z', 'second');
    }
}
