<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Auth\NexusWebUserProvider;
use App\Models\User;
use App\Repositories\TorrentDownloadRepository;
use App\Repositories\UsercpSecurityCommand;
use App\Repositories\UserRepository;
use App\Services\SecureTokenService;
use App\Services\WebAuthService;
use App\Support\AuthCookie;
use App\Support\PasswordHasher;
use App\Support\Security\PasskeyGenerator;
use App\Support\Token;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::HTTP_FEATURE)]
final class AuthSessionRevocationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_password_change_revokes_existing_auth_cookie(): void
    {
        $user = User::factory()->create([
            'auth_key' => Token::randomHex(20),
        ]);
        $credentials = [AuthCookie::COOKIE_NAME => $this->issueToken($user)];
        $provider = app(NexusWebUserProvider::class);

        $authenticated = $provider->retrieveByCredentials($credentials);
        $this->assertInstanceOf(User::class, $authenticated);
        $this->assertTrue($provider->validateCredentials($authenticated, $credentials));

        app(UsercpSecurityCommand::class)->updateSecurity((int) $user->id, [
            'passhash' => PasswordHasher::hash('new-password-123'),
            'passhash_algo' => PasswordHasher::ALGO_ARGON2ID,
            'must_change_password' => 0,
            'auth_key' => Token::randomHex(20),
        ], false);

        $freshUser = $user->fresh();
        $this->assertInstanceOf(User::class, $freshUser);
        $this->assertFalse($provider->validateCredentials($user, $credentials));
        $this->assertFalse($provider->validateCredentials($freshUser, $credentials));
        $this->assertNull($provider->retrieveByCredentials($credentials));

        $newCredentials = [AuthCookie::COOKIE_NAME => $this->issueToken($freshUser)];
        $newUser = $provider->retrieveByCredentials($newCredentials);
        $this->assertInstanceOf(User::class, $newUser);
        $this->assertTrue($provider->validateCredentials($newUser, $newCredentials));
    }

    public function test_cookie_without_auth_version_is_rejected(): void
    {
        $user = User::factory()->create([
            'auth_key' => Token::randomHex(20),
        ]);

        $credentials = [
            AuthCookie::COOKIE_NAME => Crypt::encryptString((string) json_encode([
                'user_id' => (int) $user->id,
                'expires' => time() + 3600,
            ])),
        ];
        $provider = app(NexusWebUserProvider::class);

        $this->assertNull($provider->retrieveByCredentials($credentials));
        $this->assertFalse($provider->validateCredentials($user, $credentials));
    }

    public function test_legacy_hmac_cookie_is_rejected_after_version_increment(): void
    {
        $authKey = Token::randomHex(20);
        $user = User::factory()->create([
            'auth_key' => $authKey,
        ]);
        $credentials = [
            AuthCookie::COOKIE_NAME => $this->buildLegacyToken(
                (int) $user->id,
                $authKey,
                (int) $user->auth_version,
            ),
        ];
        $provider = app(NexusWebUserProvider::class);

        $this->assertInstanceOf(User::class, $provider->retrieveByCredentials($credentials));

        DB::table('users')->where('id', $user->id)->increment('auth_version');

        $this->assertNull($provider->retrieveByCredentials($credentials));
    }

    public function test_admin_password_reset_revokes_existing_cookie(): void
    {
        $user = User::factory()->create([
            'auth_key' => Token::randomHex(20),
        ]);
        $credentials = [AuthCookie::COOKIE_NAME => $this->issueToken($user)];
        $provider = app(NexusWebUserProvider::class);

        $this->assertInstanceOf(User::class, $provider->retrieveByCredentials($credentials));

        app(UserRepository::class)->resetPassword($user->id, 'NewPass123', 'NewPass123');

        $this->assertNull($provider->retrieveByCredentials($credentials));
    }

    public function test_password_hash_upgrade_revokes_existing_cookie(): void
    {
        $user = User::factory()->create([
            'auth_key' => Token::randomHex(20),
        ]);
        $credentials = [AuthCookie::COOKIE_NAME => $this->issueToken($user)];
        $provider = app(NexusWebUserProvider::class);

        $this->assertInstanceOf(User::class, $provider->retrieveByCredentials($credentials));

        $provider->rehashPasswordIfRequired($user, ['password' => '123456'], true);

        $this->assertNull($provider->retrieveByCredentials($credentials));
    }

    public function test_failed_security_update_rolls_back_auth_version(): void
    {
        $user = User::factory()->create([
            'auth_key' => Token::randomHex(20),
        ]);
        $credentials = [AuthCookie::COOKIE_NAME => $this->issueToken($user)];
        $provider = app(NexusWebUserProvider::class);

        $this->assertInstanceOf(User::class, $provider->retrieveByCredentials($credentials));

        $torrentDownloads = $this->createMock(TorrentDownloadRepository::class);
        $torrentDownloads->expects($this->once())
            ->method('resetTrackerReportAuthKeySecret')
            ->willThrowException(new \RuntimeException('tracker reset failed'));
        $command = new UsercpSecurityCommand(
            app(PasskeyGenerator::class),
            app(SecureTokenService::class),
            $torrentDownloads,
            app(WebAuthService::class),
        );

        try {
            $command->updateSecurity((int) $user->id, [
                'passhash' => PasswordHasher::hash('new-password-123'),
            ], true);
            $this->fail('Expected the tracker auth-key reset failure to propagate');
        } catch (\RuntimeException) {
            $freshUser = $user->fresh();
            $this->assertInstanceOf(User::class, $freshUser);
            $this->assertSame((int) $user->auth_version, (int) $freshUser->auth_version);
            $this->assertInstanceOf(User::class, $provider->retrieveByCredentials($credentials));
        }
    }

    public function test_logout_all_devices_revokes_existing_cookie(): void
    {
        $user = User::factory()->create([
            'auth_key' => Token::randomHex(20),
        ]);
        $credentials = [AuthCookie::COOKIE_NAME => $this->issueToken($user)];
        $provider = app(NexusWebUserProvider::class);

        $this->assertInstanceOf(User::class, $provider->retrieveByCredentials($credentials));

        $response = $this->withSession(['_token' => 'sec-01-token'])
            ->withNexusCookie($user)
            ->post('/web/usercp/logout-all', ['_token' => 'sec-01-token']);

        $response->assertRedirect('/login');
        $freshUser = $user->fresh();
        $this->assertInstanceOf(User::class, $freshUser);
        $this->assertSame(
            (int) $user->auth_version + 1,
            (int) $freshUser->auth_version,
        );
        $this->assertNull($provider->retrieveByCredentials($credentials));
    }

    private function issueToken(User $user): string
    {
        return AuthCookie::buildToken(
            (int) $user->id,
            null,
            time() + 3600,
            (int) $user->auth_version,
        );
    }

    private function buildLegacyToken(int $userId, string $authKey, int $authVersion): string
    {
        $json = json_encode([
            'user_id' => $userId,
            'expires' => time() + 3600,
            'auth_version' => $authVersion,
        ], JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', (string) $json, $authKey);

        return base64_encode($json.'.'.$signature);
    }
}
