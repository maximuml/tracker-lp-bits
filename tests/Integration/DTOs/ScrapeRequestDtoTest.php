<?php

declare(strict_types=1);

namespace Tests\Integration\DTOs;

use App\DTOs\ScrapeRequestDto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Regression: parseInfoHashes() accepted every valid info_hash in the
 * query string — no deduplication, no count cap — so a single request
 * could force a giant WHERE IN list and a guaranteed cache miss.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class ScrapeRequestDtoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Redis::connection()->flushdb();
    }

    /**
     * @param  list<string>  $hexHashes
     */
    private function requestWithHashes(array $hexHashes): Request
    {
        $query = 'passkey='.str_repeat('a', 32);
        foreach ($hexHashes as $hex) {
            $query .= '&info_hash='.urlencode(hex2bin($hex));
        }

        $request = Request::create('/scrape?'.$query, 'GET', [], [], [], ['HTTP_USER_AGENT' => 'uTorrent/1.0']);
        app()->instance('request', $request);

        return $request;
    }

    public function test_from_request_deduplicates_info_hashes(): void
    {
        $hex1 = str_repeat('a', 40);
        $hex2 = str_repeat('b', 40);

        $dto = ScrapeRequestDto::fromRequest($this->requestWithHashes([$hex1, $hex2, $hex1]));

        $this->assertCount(2, $dto->infoHashes);
    }

    public function test_from_request_caps_info_hashes_count(): void
    {
        $hashes = [];
        for ($i = 0; $i < 150; $i++) {
            $hashes[] = sha1('hash-'.$i);
        }

        $dto = ScrapeRequestDto::fromRequest($this->requestWithHashes($hashes));

        $this->assertCount(100, $dto->infoHashes);
    }
}
