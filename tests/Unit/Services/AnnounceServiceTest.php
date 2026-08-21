<?php

namespace Tests\Unit\Services;

use App\Exceptions\TrackerException;
use App\Exceptions\TrackerWarningException;
use App\Http\Requests\AnnounceRequest;
use PHPUnit\Framework\TestCase;

class AnnounceServiceTest extends TestCase
{
    public function test_tracker_exception_failure(): void
    {
        $e = TrackerException::failure('torrent not registered');

        $this->assertInstanceOf(TrackerException::class, $e);
        $this->assertSame('torrent not registered', $e->getMessage());
    }

    public function test_tracker_warning_exception_includes_message_and_interval(): void
    {
        $base = [
            'interval' => 1800,
            'min interval' => 300,
            'complete' => 1,
            'incomplete' => 2,
            'downloaded' => 3,
            'peers' => '',
            'peers6' => '',
        ];

        $e = new TrackerWarningException('port 6881 is blacklisted', $base, 7200);

        $this->assertSame('port 6881 is blacklisted', $e->getMessage());

        $response = $e->getResponse();
        $this->assertSame(7200, $response['interval']);
        $this->assertSame(7200, $response['min interval']);
        $this->assertSame('port 6881 is blacklisted', $response['warning message']);
        $this->assertSame(1, $response['complete']);
    }

    public function test_announce_request_validation_rules(): void
    {
        $rules = AnnounceRequest::announceRules();

        $this->assertArrayHasKey('passkey', $rules);
        $this->assertArrayHasKey('info_hash', $rules);
        $this->assertArrayHasKey('peer_id', $rules);
        $this->assertArrayHasKey('port', $rules);
        $this->assertArrayHasKey('uploaded', $rules);
        $this->assertArrayHasKey('downloaded', $rules);
        $this->assertArrayHasKey('left', $rules);
        $this->assertArrayHasKey('event', $rules);
        $this->assertArrayHasKey('numwant', $rules);
    }
}
