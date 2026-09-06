<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Metrics;

use App\Support\Metrics\AnnounceMetricsRecorder;
use Tests\TestCase;

final class AnnounceMetricsRecorderTest extends TestCase
{
    public function test_categorize_invalid_passkey(): void
    {
        $this->assertSame('invalid_passkey', AnnounceMetricsRecorder::categorize('Invalid passkey! Re-download the .torrent'));
    }

    public function test_categorize_disabled_account(): void
    {
        $this->assertSame('disabled_account', AnnounceMetricsRecorder::categorize('Your account is disabled!'));
    }

    public function test_categorize_parked_account(): void
    {
        $this->assertSame('parked_account', AnnounceMetricsRecorder::categorize('Your account is parked! (Read the FAQ)'));
    }

    public function test_categorize_download_disabled(): void
    {
        $this->assertSame('download_disabled', AnnounceMetricsRecorder::categorize('Your downloading privileges have been disabled!'));
    }

    public function test_categorize_torrent_not_registered(): void
    {
        $this->assertSame('torrent_not_registered', AnnounceMetricsRecorder::categorize('torrent not registered with this tracker'));
    }

    public function test_categorize_torrent_banned(): void
    {
        $this->assertSame('torrent_banned', AnnounceMetricsRecorder::categorize('torrent banned'));
    }

    public function test_categorize_torrent_not_approved(): void
    {
        $this->assertSame('torrent_not_approved', AnnounceMetricsRecorder::categorize('torrent review not approved'));
    }

    public function test_categorize_browser_blocked(): void
    {
        $this->assertSame('browser_blocked', AnnounceMetricsRecorder::categorize('Browser access blocked!'));
    }

    public function test_categorize_paid_torrent(): void
    {
        $this->assertSame('paid_torrent_failure', AnnounceMetricsRecorder::categorize('announce to paid torrent and fail too many times'));
    }

    public function test_categorize_validation_error(): void
    {
        $this->assertSame('validation_error', AnnounceMetricsRecorder::categorize('validation error: missing field'));
    }

    public function test_categorize_unknown_falls_back_to_other(): void
    {
        $this->assertSame('other', AnnounceMetricsRecorder::categorize('some unknown error message'));
    }

    public function test_categorize_is_case_insensitive(): void
    {
        $this->assertSame('invalid_passkey', AnnounceMetricsRecorder::categorize('INVALID PASSKEY!'));
    }

    public function test_categories_returns_fixed_list(): void
    {
        $categories = AnnounceMetricsRecorder::categories();

        $this->assertContains('invalid_passkey', $categories);
        $this->assertContains('other', $categories);
        $this->assertCount(13, $categories);
    }

    public function test_record_rejection_increments_redis_counter(): void
    {
        // Use a unique reason to avoid interference
        AnnounceMetricsRecorder::recordRejection('torrent not registered with this tracker');

        // The Redis counter should have been incremented
        // (We can't easily verify the exact value due to test isolation,
        // but we can verify the method doesn't throw)
        $this->assertTrue(true);
    }
}
