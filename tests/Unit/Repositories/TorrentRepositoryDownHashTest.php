<?php

namespace Tests\Unit\Repositories;

use App\Repositories\MeiliSearchRepository;
use App\Repositories\SearchBoxRepository;
use App\Repositories\TorrentDownloadRepository;
use App\Repositories\TorrentModerationRepository;
use App\Repositories\TorrentPurchaseRepository;
use App\Repositories\TorrentRepository;
use Firebase\JWT\JWT;
use PHPUnit\Framework\TestCase;

class TorrentRepositoryDownHashTest extends TestCase
{
    private TorrentRepository $repository;

    private TorrentDownloadRepository $downloadRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->downloadRepository = new TorrentDownloadRepository;
        $this->repository = new TorrentRepository(
            $this->downloadRepository,
            new TorrentPurchaseRepository,
            new TorrentModerationRepository(
                new SearchBoxRepository,
                $this->downloadRepository,
                new MeiliSearchRepository,
            ),
        );
    }

    public function test_hkdf_downhash_roundtrip(): void
    {
        $user = ['id' => 42, 'passkey' => 'abc123def456ghi789jkl012mno345pq'];

        $hash = $this->repository->encryptDownHash(123, $user);

        $this->assertNotEmpty($hash);
        $this->assertSame([123], $this->repository->decryptDownHash($hash, $user));
    }

    public function test_legacy_md5_downhash_still_decrypts(): void
    {
        $user = ['id' => 42, 'passkey' => 'abc123def456ghi789jkl012mno345pq'];
        $legacyKey = md5($user['passkey'].date('Ymd').$user['id']);
        $legacyHash = JWT::encode(['id' => 456, 'exp' => time() + 3600], $legacyKey, 'HS256');

        $this->assertSame([456], $this->repository->decryptDownHash($legacyHash, $user));
    }

    public function test_downhash_fails_after_passkey_change(): void
    {
        $oldUser = ['id' => 42, 'passkey' => 'oldpasskey1234567890123456789012'];
        $legacyKey = md5($oldUser['passkey'].date('Ymd').$oldUser['id']);
        $legacyHash = JWT::encode(['id' => 789, 'exp' => time() + 3600], $legacyKey, 'HS256');

        $newUser = ['id' => 42, 'passkey' => 'newpasskey1234567890123456789012'];

        $this->assertSame([], $this->repository->decryptDownHash($legacyHash, $newUser));
    }

    public function test_tampered_downhash_fails(): void
    {
        $user = ['id' => 42, 'passkey' => 'abc123def456ghi789jkl012mno345pq'];
        $hash = $this->repository->encryptDownHash(123, $user);

        $tampered = substr($hash, 0, -4).'xxxx';

        $this->assertSame([], $this->repository->decryptDownHash($tampered, $user));
    }

    public function test_expired_downhash_fails(): void
    {
        $user = ['id' => 42, 'passkey' => 'abc123def456ghi789jkl012mno345pq'];
        $key = $this->invokeHkdfKey($user);
        $expiredHash = JWT::encode(['id' => 999, 'exp' => time() - 10], $key, 'HS256');

        $this->assertSame([], $this->repository->decryptDownHash($expiredHash, $user));
    }

    /**
     * @param  array<string, mixed>  $user
     */
    private function invokeHkdfKey(array $user): string
    {
        $reflection = new \ReflectionClass($this->downloadRepository);
        $method = $reflection->getMethod('getHkdfDownHashKey');

        return $method->invoke($this->downloadRepository, (int) $user['id'], (string) $user['passkey']);
    }
}
