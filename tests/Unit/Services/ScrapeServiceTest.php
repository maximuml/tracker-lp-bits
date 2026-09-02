<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\DTOs\ScrapeRequestDto;
use App\Exceptions\TrackerException;
use App\Exceptions\TrackerWarningException;
use App\Services\ScrapeService;
use App\ValueObjects\InfoHash;
use App\ValueObjects\Passkey;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

/**
 * Unit tests for ScrapeService.
 *
 * Covers scrape authentication (invalid/disabled/parked/no-downloadpos
 * passkeys), empty info_hash warning, successful scrape with cache,
 * and torrent-not-registered warning.
 */
final class ScrapeServiceTest extends TestCase
{
    use DatabaseTransactions;

    private ScrapeService $service;

    /** @var non-empty-string */
    private string $passkey = 'abcdefghijklmnopqrstuvwxyz012345';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Redis::connection()->flushdb();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('torrents')->truncate();
        DB::table('users')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        $this->service = new ScrapeService;
    }

    /** @param  array<string, mixed>  $overrides */
    private function insertUser(array $overrides = []): int
    {
        $defaults = [
            'username' => 'testuser',
            'email' => 'test@test.com',
            'passhash' => 'hash',
            'secret' => 'secret',
            'passkey' => $this->passkey,
            'class' => 1,
            'added' => now()->toDateTimeString(),
            'last_access' => now()->toDateTimeString(),
            'status' => 'confirmed',
            'enabled' => 1,
            'parked' => 0,
            'downloadpos' => 1,
        ];

        return (int) DB::table('users')->insertGetId(array_merge($defaults, $overrides));
    }

    private function insertTorrent(string $hexHash, int $seeders = 5, int $leechers = 2, int $completed = 10): int
    {
        return (int) DB::table('torrents')->insertGetId([
            'name' => 'Test Torrent '.$hexHash,
            'info_hash' => hex2bin($hexHash),
            'filename' => 'test.torrent',
            'save_as' => 'test',
            'added' => now()->toDateTimeString(),
            'size' => 1024,
            'category' => 1,
            'visible' => 1,
            'banned' => 0,
            'type' => 'single',
            'numfiles' => 1,
            'seeders' => $seeders,
            'leechers' => $leechers,
            'times_completed' => $completed,
            'owner' => 1,
        ]);
    }

    /** @param  list<InfoHash>  $infoHashes */
    private function makeDto(array $infoHashes): ScrapeRequestDto
    {
        return new ScrapeRequestDto(
            passkey: Passkey::fromString($this->passkey),
            infoHashes: $infoHashes,
            userAgent: 'uTorrent/1.0',
            ip: '127.0.0.1',
            ipv4: '127.0.0.1',
            ipv6: null,
        );
    }

    private function infoHashFromHex(string $hex): InfoHash
    {
        return InfoHash::fromHex($hex);
    }

    // --- authentication ---

    public function test_scrape_throws_for_invalid_passkey(): void
    {
        $this->expectException(TrackerException::class);
        $this->expectExceptionMessage('Invalid passkey!');

        $dto = $this->makeDto([$this->infoHashFromHex(str_repeat('a', 40))]);

        $this->service->scrape($dto);
    }

    public function test_scrape_throws_for_disabled_user(): void
    {
        $this->insertUser(['enabled' => 0]);

        $this->expectException(TrackerException::class);
        $this->expectExceptionMessage('disabled');

        $dto = $this->makeDto([$this->infoHashFromHex(str_repeat('a', 40))]);

        $this->service->scrape($dto);
    }

    public function test_scrape_throws_for_parked_user(): void
    {
        $this->insertUser(['parked' => 1]);

        $this->expectException(TrackerException::class);
        $this->expectExceptionMessage('parked');

        $dto = $this->makeDto([$this->infoHashFromHex(str_repeat('a', 40))]);

        $this->service->scrape($dto);
    }

    public function test_scrape_throws_for_no_downloadpos(): void
    {
        $this->insertUser(['downloadpos' => 0]);

        $this->expectException(TrackerException::class);
        $this->expectExceptionMessage('downloading privileges');

        $dto = $this->makeDto([$this->infoHashFromHex(str_repeat('a', 40))]);

        $this->service->scrape($dto);
    }

    // --- empty info_hash ---

    public function test_scrape_with_empty_info_hashes_throws_warning(): void
    {
        $this->insertUser();

        $this->expectException(TrackerWarningException::class);
        $this->expectExceptionMessage('Require info_hash.');

        $dto = $this->makeDto([]);

        $this->service->scrape($dto);
    }

    // --- torrent not registered ---

    public function test_scrape_for_unregistered_torrent_throws_warning(): void
    {
        $this->insertUser();

        $this->expectException(TrackerWarningException::class);
        $this->expectExceptionMessage('not registered');

        $dto = $this->makeDto([$this->infoHashFromHex(str_repeat('b', 40))]);

        $this->service->scrape($dto);
    }

    // --- successful scrape ---

    public function test_scrape_returns_torrent_data(): void
    {
        $this->insertUser();
        $hexHash = str_repeat('a', 40);
        $this->insertTorrent($hexHash, 5, 2, 10);

        $dto = $this->makeDto([$this->infoHashFromHex($hexHash)]);

        $result = $this->service->scrape($dto);

        $binHash = hex2bin($hexHash);
        $this->assertArrayHasKey('files', $result);
        $this->assertArrayHasKey($binHash, $result['files']);
        $this->assertSame(5, $result['files'][$binHash]['complete']);
        $this->assertSame(10, $result['files'][$binHash]['downloaded']);
        $this->assertSame(2, $result['files'][$binHash]['incomplete']);
    }

    public function test_scrape_multiple_torrents(): void
    {
        $this->insertUser();
        $hex1 = str_repeat('a', 40);
        $hex2 = str_repeat('b', 40);
        $this->insertTorrent($hex1, 3, 1, 7);
        $this->insertTorrent($hex2, 8, 4, 15);

        $dto = $this->makeDto([
            $this->infoHashFromHex($hex1),
            $this->infoHashFromHex($hex2),
        ]);

        $result = $this->service->scrape($dto);

        $bin1 = hex2bin($hex1);
        $bin2 = hex2bin($hex2);
        $this->assertCount(2, $result['files']);
        $this->assertSame(3, $result['files'][$bin1]['complete']);
        $this->assertSame(8, $result['files'][$bin2]['complete']);
    }

    public function test_scrape_caches_result(): void
    {
        $this->insertUser();
        $hexHash = str_repeat('c', 40);
        $this->insertTorrent($hexHash, 5, 2, 10);

        $dto = $this->makeDto([$this->infoHashFromHex($hexHash)]);

        // First call — populates cache
        $result1 = $this->service->scrape($dto);

        // Modify torrent data — cached result should not reflect this
        DB::table('torrents')->where('info_hash', hex2bin($hexHash))->update(['seeders' => 999]);

        $result2 = $this->service->scrape($dto);

        // Cached result should still have the original seeders count
        $binHash = hex2bin($hexHash);
        $this->assertSame($result1['files'][$binHash]['complete'], $result2['files'][$binHash]['complete']);
        $this->assertSame(5, $result2['files'][$binHash]['complete']);
    }

    public function test_scrape_partial_match_returns_only_registered(): void
    {
        $this->insertUser();
        $hex1 = str_repeat('d', 40);
        $hex2 = str_repeat('e', 40); // not inserted
        $this->insertTorrent($hex1, 3, 1, 7);

        $dto = $this->makeDto([
            $this->infoHashFromHex($hex1),
            $this->infoHashFromHex($hex2),
        ]);

        $result = $this->service->scrape($dto);

        $bin1 = hex2bin($hex1);
        $this->assertCount(1, $result['files']);
        $this->assertArrayHasKey($bin1, $result['files']);
    }
}
