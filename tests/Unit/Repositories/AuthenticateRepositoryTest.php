<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\User;
use App\Repositories\AuthenticateRepository;
use App\Services\WebAuthService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Unit tests for AuthenticateRepository.
 *
 * Covers login(), logout(), nasToolsApprove(), iyuuApprove(), and ammdsApprove().
 */
final class AuthenticateRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private AuthenticateRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('personal_access_tokens')->delete();
        DB::table('users')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        $this->repository = new AuthenticateRepository;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_login_throws_when_user_not_found(): void
    {
        $this->mockWebAuthService();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Username or password invalid.');

        $this->repository->login('nonexistent_user', 'password');
    }

    public function test_login_throws_when_password_invalid(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->mockWebAuthService(false);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Username or password invalid.');

        $this->repository->login($user->username, 'wrongpassword');
    }

    public function test_login_succeeds_and_returns_token(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user, 'nexus-web');

        $this->mockWebAuthService(true);

        $result = $this->repository->login($user->username, '123456');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('token', $result);
        $this->assertNotEmpty($result['token']);
        $this->assertSame($user->id, (int) $result['id']);
    }

    public function test_login_throws_when_two_step_required_but_code_empty(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['two_step_secret' => 'JBSWY3DPEHPK3PXP']);

        $this->mockWebAuthService(true);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Require two-step code.');

        $this->repository->login($user->username, '123456');
    }

    public function test_login_throws_when_two_step_code_invalid(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['two_step_secret' => 'JBSWY3DPEHPK3PXP']);

        $this->mockWebAuthService(true);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid two-step code.');

        $this->repository->login($user->username, '123456', '000000');
    }

    public function test_logout_deletes_user_tokens(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $user->createToken('test-token');

        $this->assertSame(1, DB::table('personal_access_tokens')->where('tokenable_id', $user->id)->count());

        $result = $this->repository->logout($user->id);

        $this->assertSame(1, $result);
        $this->assertSame(0, DB::table('personal_access_tokens')->where('tokenable_id', $user->id)->count());
    }

    public function test_logout_throws_when_user_not_found(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->repository->logout(99999);
    }

    public function test_nas_tools_approve_returns_user_when_valid(): void
    {
        config(['nexus.nas_tools_key' => str_repeat('a', 16)]);
        /** @var User $user */
        $user = User::factory()->withPasskey('testpasskey123')->create();

        $encrypter = new Encrypter(str_repeat('a', 16));
        $payload = json_encode(['uid' => $user->id, 'passkey' => 'testpasskey123']);
        $json = $encrypter->encryptString(is_string($payload) ? $payload : '');

        $result = $this->repository->nasToolsApprove($json);

        $this->assertInstanceOf(User::class, $result);
        $this->assertSame($user->id, $result->id);
    }

    public function test_nas_tools_approve_throws_when_data_format_invalid(): void
    {
        config(['nexus.nas_tools_key' => str_repeat('a', 16)]);

        $encrypter = new Encrypter(str_repeat('a', 16));
        $payload = json_encode(['foo' => 'bar']);
        $json = $encrypter->encryptString(is_string($payload) ? $payload : '');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid data format.');

        $this->repository->nasToolsApprove($json);
    }

    public function test_nas_tools_approve_throws_when_uid_or_passkey_invalid(): void
    {
        config(['nexus.nas_tools_key' => str_repeat('a', 16)]);
        /** @var User $user */
        $user = User::factory()->withPasskey('realpasskey')->create();

        $encrypter = new Encrypter(str_repeat('a', 16));
        $payload = json_encode(['uid' => $user->id, 'passkey' => 'wrongpasskey']);
        $json = $encrypter->encryptString(is_string($payload) ? $payload : '');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid uid or passkey.');

        $this->repository->nasToolsApprove($json);
    }

    public function test_iyuu_approve_returns_true_when_verity_matches(): void
    {
        config(['nexus.iyuu_secret' => 'test_secret']);
        /** @var User $user */
        $user = User::factory()->create();

        $token = 'test_token';
        $verity = md5($token.$user->id.sha1((string) $user->passkey).'test_secret');

        $result = $this->repository->iyuuApprove($token, $user->id, $verity);

        $this->assertTrue($result);
    }

    public function test_iyuu_approve_throws_when_verity_does_not_match(): void
    {
        config(['nexus.iyuu_secret' => 'test_secret']);
        /** @var User $user */
        $user = User::factory()->create();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid uid or passkey');

        $this->repository->iyuuApprove('token', $user->id, 'wrong_verity');
    }

    public function test_iyuu_approve_throws_when_user_not_found(): void
    {
        config(['nexus.iyuu_secret' => 'test_secret']);

        $this->expectException(ModelNotFoundException::class);

        $this->repository->iyuuApprove('token', 99999, 'verity');
    }

    public function test_ammds_approve_returns_user_when_signature_valid(): void
    {
        config(['nexus.ammds_secret' => 'ammds_secret_key']);
        /** @var User $user */
        $user = User::factory()->create();

        $timestamp = now()->getTimestampMs();
        $nonce = 'test_nonce_'.uniqid();
        $passkeyHash = hash('sha256', (string) $user->passkey);
        $dataToSign = sprintf('%s%s%s%s', $user->id, $passkeyHash, $timestamp, $nonce);
        $signature = hash_hmac('sha256', $dataToSign, 'ammds_secret_key');

        $request = Request::create('/api/v1/ammds/approve', 'POST', [
            'uid' => $user->id,
            'timestamp' => $timestamp,
            'nonce' => $nonce,
            'signature' => $signature,
        ]);

        $result = $this->repository->ammdsApprove($request);

        $this->assertInstanceOf(User::class, $result);
        $this->assertSame($user->id, $result->id);
    }

    public function test_ammds_approve_throws_when_timestamp_expired(): void
    {
        config(['nexus.ammds_secret' => 'ammds_secret_key']);
        /** @var User $user */
        $user = User::factory()->create();

        $expiredTimestamp = now()->subSeconds(400)->getTimestampMs();
        $request = Request::create('/api/v1/ammds/approve', 'POST', [
            'uid' => $user->id,
            'timestamp' => $expiredTimestamp,
            'nonce' => 'nonce',
            'signature' => 'sig',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('expired.');

        $this->repository->ammdsApprove($request);
    }

    public function test_ammds_approve_throws_when_duplicate_nonce(): void
    {
        config(['nexus.ammds_secret' => 'ammds_secret_key']);
        /** @var User $user */
        $user = User::factory()->create();

        $timestamp = now()->getTimestampMs();
        $nonce = 'dup_nonce_'.uniqid();
        $passkeyHash = hash('sha256', (string) $user->passkey);
        $dataToSign = sprintf('%s%s%s%s', $user->id, $passkeyHash, $timestamp, $nonce);
        $signature = hash_hmac('sha256', $dataToSign, 'ammds_secret_key');

        $request = Request::create('/api/v1/ammds/approve', 'POST', [
            'uid' => $user->id,
            'timestamp' => $timestamp,
            'nonce' => $nonce,
            'signature' => $signature,
        ]);

        // First call succeeds.
        $this->repository->ammdsApprove($request);

        // Second call with same nonce should fail.
        $request2 = Request::create('/api/v1/ammds/approve', 'POST', [
            'uid' => $user->id,
            'timestamp' => $timestamp,
            'nonce' => $nonce,
            'signature' => $signature,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('duplicate.');

        $this->repository->ammdsApprove($request2);
    }

    public function test_ammds_approve_throws_when_signature_invalid(): void
    {
        config(['nexus.ammds_secret' => 'ammds_secret_key']);
        /** @var User $user */
        $user = User::factory()->create();

        $timestamp = now()->getTimestampMs();
        $nonce = 'bad_sig_nonce_'.uniqid();

        $request = Request::create('/api/v1/ammds/approve', 'POST', [
            'uid' => $user->id,
            'timestamp' => $timestamp,
            'nonce' => $nonce,
            'signature' => 'invalid_signature',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid signature.');

        $this->repository->ammdsApprove($request);
    }

    public function test_ammds_approve_throws_when_user_not_found(): void
    {
        config(['nexus.ammds_secret' => 'ammds_secret_key']);

        $timestamp = now()->getTimestampMs();
        $nonce = 'no_user_nonce_'.uniqid();

        $request = Request::create('/api/v1/ammds/approve', 'POST', [
            'uid' => 99999,
            'timestamp' => $timestamp,
            'nonce' => $nonce,
            'signature' => 'sig',
        ]);

        $this->expectException(ModelNotFoundException::class);

        $this->repository->ammdsApprove($request);
    }

    /**
     * Bind a mock WebAuthService into the container.
     * When $validateResult is null, the mock is not configured (use for
     * tests where validatePassword is never reached).
     */
    private function mockWebAuthService(?bool $validateResult = null): void
    {
        /** @var WebAuthService&MockInterface $mock */
        $mock = Mockery::mock(WebAuthService::class);

        if ($validateResult !== null) {
            $mock->shouldReceive('validatePassword')
                ->andReturn($validateResult);
        }

        $this->app->instance(WebAuthService::class, $mock);
    }
}
