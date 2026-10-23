<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\DTOs\Usercp\SecuritySettingsDto;
use App\Models\User;
use App\Repositories\UsercpSecurityCommand;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Pins UsercpSecurityCommand — including the CAS email-change update
 * that must only apply while the expected editsecret still matches.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class UsercpSecurityCommandTest extends TestCase
{
    use DatabaseTransactions;

    private UsercpSecurityCommand $command;

    protected function setUp(): void
    {
        parent::setUp();
        $this->command = app(UsercpSecurityCommand::class);
    }

    public function test_email_exists_for_other(): void
    {
        /** @var User $first */
        $first = User::factory()->create(['email' => 'taken@example.com']);
        /** @var User $second */
        $second = User::factory()->create(['email' => 'free@example.com']);

        $this->assertTrue($this->command->emailExistsForOther('taken@example.com', (int) $second->id));
        $this->assertFalse($this->command->emailExistsForOther('taken@example.com', (int) $first->id));
        $this->assertFalse($this->command->emailExistsForOther('unused@example.com', (int) $first->id));
    }

    public function test_apply_email_change_requires_matching_secret(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['editsecret' => 'expected-secret', 'email' => 'old@example.com']);

        $this->assertSame(0, $this->command->applyEmailChange((int) $user->id, 'wrong-secret', 'new@example.com'));
        $this->assertSame('old@example.com', $user->fresh()->email);

        $this->assertSame(1, $this->command->applyEmailChange((int) $user->id, 'expected-secret', 'new@example.com'));

        $fresh = $user->fresh();
        $this->assertSame('new@example.com', $fresh->email);
        $this->assertSame('', (string) $fresh->editsecret);
    }

    public function test_update_user_writes_fields(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['auth_key' => 'old-key']);

        $this->assertTrue($this->command->updateUser((int) $user->id, ['auth_key' => 'rotated-key']));
        $this->assertSame('rotated-key', $user->fresh()->auth_key);
    }

    public function test_update_security_bumps_auth_version_when_revoking(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['auth_version' => 1]);

        $this->command->updateSecurity((int) $user->id, ['auth_key' => 'new-key'], false);

        $fresh = $user->fresh();
        $this->assertSame('new-key', $fresh->auth_key);
        $this->assertSame(2, (int) $fresh->auth_version);
    }

    public function test_challenge_roundtrip(): void
    {
        $this->assertNull($this->command->getChallenge('nouser'));
        $this->assertTrue($this->command->deleteChallenge('nouser'));
    }

    /**
     * The actor snapshot hydrates from the legacy array, which hides
     * credential columns (passhash/secret). Password validation must
     * reload the row — a correct password has to pass even when the
     * carried model lacks the hash.
     */
    public function test_update_security_api_validates_against_full_row(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->be($user);

        $result = $this->command->updateSecurityApi(new SecuritySettingsDto(
            currentPassword: '123456',
            email: null,
            newPassword: null,
            privacy: 'normal',
            resetpasskey: false,
            resetauthkey: false,
            twoStepSecret: null,
            twoStepCode: null,
            ip: '127.0.0.1',
        ));

        $this->assertNotEmpty($result);
    }

    public function test_update_security_api_rejects_wrong_password(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->be($user);

        $this->expectException(ValidationException::class);

        $this->command->updateSecurityApi(new SecuritySettingsDto(
            currentPassword: 'not-the-password',
            email: null,
            newPassword: null,
            privacy: null,
            resetpasskey: false,
            resetauthkey: false,
            twoStepSecret: null,
            twoStepCode: null,
            ip: '127.0.0.1',
        ));
    }
}
