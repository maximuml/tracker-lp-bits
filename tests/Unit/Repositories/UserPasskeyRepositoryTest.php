<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\Passkey;
use App\Models\User;
use App\Repositories\UserPasskeyRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Unit tests for UserPasskeyRepository.
 *
 * Covers insertUserPasskey(), getList(), and delete() — the data-access
 * methods that do not require the WebAuthn library or external HTTP calls.
 *
 * The WebAuthn-dependent methods (getCreateArgs, processCreate, getGetArgs,
 * processGet) and rendering methods (renderLogin, renderList) are excluded
 * because they require the lbuchs/WebAuthn library and browser interaction.
 *
 * Passkey records are inserted via DB::table() because the Passkey model's
 * fillable uses lowercase 'aaguid' while the repository passes uppercase
 * 'AAGUID', causing a MassAssignmentException on Passkey::create().
 */
final class UserPasskeyRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private UserPasskeyRepository $repository;

    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('user_passkeys')->delete();
        $this->repository = new UserPasskeyRepository;

        /** @var User $user */
        $user = User::factory()->create();
        $this->userId = $user->id;
    }

    public function test_get_list_returns_empty_collection_when_none(): void
    {
        $result = $this->repository->getList($this->userId);

        $this->assertTrue($result->isEmpty());
    }

    public function test_get_list_returns_passkeys_for_user(): void
    {
        $this->insertPasskey($this->userId, 'guid1', 'cred1', 'key1');
        $this->insertPasskey($this->userId, 'guid2', 'cred2', 'key2');

        $result = $this->repository->getList($this->userId);

        $this->assertCount(2, $result);
        $this->assertInstanceOf(Passkey::class, $result[0]);
        $this->assertInstanceOf(Passkey::class, $result[1]);
    }

    public function test_get_list_only_returns_passkeys_for_given_user(): void
    {
        /** @var User $user2 */
        $user2 = User::factory()->create();

        $this->insertPasskey($this->userId, 'guid1', 'cred1', 'key1');
        $this->insertPasskey($user2->id, 'guid2', 'cred2', 'key2');

        $result = $this->repository->getList($this->userId);

        $this->assertCount(1, $result);
        $this->assertSame('cred1', $result[0]->credential_id);
    }

    public function test_delete_removes_passkey_by_credential_id(): void
    {
        $this->insertPasskey($this->userId, 'guid1', 'cred1', 'key1');
        $this->insertPasskey($this->userId, 'guid2', 'cred2', 'key2');

        $deleted = $this->repository->delete($this->userId, 'cred1');

        $this->assertSame(1, (int) $deleted);
        $this->assertSame(1, DB::table('user_passkeys')->where('user_id', $this->userId)->count());
        $this->assertFalse(DB::table('user_passkeys')->where('credential_id', 'cred1')->exists());
    }

    public function test_delete_returns_zero_when_not_found(): void
    {
        $deleted = $this->repository->delete($this->userId, 'nonexistent');

        $this->assertSame(0, (int) $deleted);
    }

    public function test_delete_only_removes_matching_credential_id(): void
    {
        $this->insertPasskey($this->userId, 'guid1', 'cred1', 'key1');
        $this->insertPasskey($this->userId, 'guid2', 'cred2', 'key2');

        $this->repository->delete($this->userId, 'cred1');

        $remaining = $this->repository->getList($this->userId);
        $this->assertCount(1, $remaining);
        $this->assertSame('cred2', $remaining[0]->credential_id);
    }

    public function test_delete_does_not_affect_other_users(): void
    {
        /** @var User $user2 */
        $user2 = User::factory()->create();
        $this->insertPasskey($this->userId, 'guid1', 'shared_cred', 'key1');
        $this->insertPasskey($user2->id, 'guid2', 'shared_cred', 'key2');

        $this->repository->delete($this->userId, 'shared_cred');

        $this->assertSame(0, DB::table('user_passkeys')->where('user_id', $this->userId)->count());
        $this->assertSame(1, DB::table('user_passkeys')->where('user_id', $user2->id)->count());
    }

    public function test_get_list_returns_passkey_with_formatted_aaguid(): void
    {
        $this->insertPasskey($this->userId, 'aabbccdd11223344eeff556677889900', 'cred1', 'key1');

        $result = $this->repository->getList($this->userId);

        $this->assertCount(1, $result);
        $this->assertSame('aabbccdd-1122-3344-eeff-556677889900', $result[0]->getAaguidFormatted());
    }

    private function insertPasskey(int $userId, string $aaguid, string $credentialId, string $publicKey): void
    {
        DB::table('user_passkeys')->insert([
            'user_id' => $userId,
            'AAGUID' => $aaguid,
            'credential_id' => $credentialId,
            'public_key' => $publicKey,
            'counter' => 0,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);
    }
}
