<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Repositories\MysqlStatsRepository;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * Unit tests for MysqlStatsRepository.
 *
 * Covers status(), formatByteDown(), timespanFormat(), and localisedDate().
 * These methods are pure computation or read-only MySQL status queries, so
 * no DatabaseTransactions trait is needed.
 */
final class MysqlStatsRepositoryTest extends TestCase
{
    private MysqlStatsRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new MysqlStatsRepository;
    }

    public function test_status_returns_array_with_expected_keys(): void
    {
        $result = $this->repository->status();

        $this->assertArrayHasKey('uptimeSeconds', $result);
        $this->assertArrayHasKey('startTime', $result);
        $this->assertArrayHasKey('bytesReceived', $result);
        $this->assertArrayHasKey('bytesSent', $result);
        $this->assertArrayHasKey('totalBytes', $result);
        $this->assertArrayHasKey('connections', $result);
        $this->assertArrayHasKey('abortedConnects', $result);
        $this->assertArrayHasKey('abortedClients', $result);
        $this->assertArrayHasKey('questions', $result);
        $this->assertArrayHasKey('queryStats', $result);
        $this->assertArrayHasKey('serverStatus', $result);
    }

    public function test_status_total_bytes_is_sum_of_received_and_sent(): void
    {
        $result = $this->repository->status();

        $expected = $result['bytesReceived'] + $result['bytesSent'];
        $this->assertSame($expected, $result['totalBytes']);
    }

    public function test_status_uptime_is_non_negative_integer(): void
    {
        $result = $this->repository->status();

        $this->assertIsInt($result['uptimeSeconds']);
        $this->assertGreaterThanOrEqual(0, $result['uptimeSeconds']);
    }

    public function test_format_byte_down_returns_bytes_for_small_value(): void
    {
        [$value, $unit] = $this->repository->formatByteDown(500.0);

        $this->assertSame('500', $value);
        $this->assertSame('Bytes', $unit);
    }

    public function test_format_byte_down_returns_gb_for_terabyte_range(): void
    {
        // 1024^4 bytes ≈ 1.1 TB, but with default limes=6 the TB threshold
        // is 10^15, so this value is displayed as 1024 GB.
        [$value, $unit] = $this->repository->formatByteDown(1024.0 * 1024 * 1024 * 1024);

        $this->assertSame('GB', $unit);
    }

    public function test_format_byte_down_with_zero_returns_zero_bytes(): void
    {
        [$value, $unit] = $this->repository->formatByteDown(0.0);

        $this->assertSame('0', $value);
        $this->assertSame('Bytes', $unit);
    }

    public function test_format_byte_down_with_comma_precision_for_non_bytes(): void
    {
        // 1024^4 bytes ≈ 1.1 TB; with limes=6 the GB threshold is 10^12,
        // so this value is displayed as 1024.00 GB with comma=2.
        [$value, $unit] = $this->repository->formatByteDown(1024.0 * 1024 * 1024 * 1024, 6, 2);

        $this->assertSame('GB', $unit);
        $this->assertSame('1,024.00', $value);
    }

    public function test_timespan_format_formats_seconds_only(): void
    {
        $result = $this->repository->timespanFormat(45);

        $this->assertSame('0 Days 0 Hours 0 Minutes 45 Seconds ', $result);
    }

    public function test_timespan_format_formats_minutes_and_seconds(): void
    {
        $result = $this->repository->timespanFormat(125);

        // 125 seconds = 2 minutes 5 seconds
        $this->assertSame('0 Days 0 Hours 2 Minutes 5 Seconds ', $result);
    }

    public function test_timespan_format_formats_hours_minutes_seconds(): void
    {
        $result = $this->repository->timespanFormat(3725);

        // 3725 seconds = 1 hour 2 minutes 5 seconds
        $this->assertSame('0 Days 1 Hours 2 Minutes 5 Seconds ', $result);
    }

    public function test_timespan_format_formats_days_hours_minutes_seconds(): void
    {
        $result = $this->repository->timespanFormat(90061);

        // 90061 seconds = 1 day 1 hour 1 minute 1 second
        $this->assertSame('1 Days 1 Hours 1 Minutes 1 Seconds ', $result);
    }

    public function test_timespan_format_with_zero(): void
    {
        $result = $this->repository->timespanFormat(0);

        $this->assertSame('0 Days 0 Hours 0 Minutes 0 Seconds ', $result);
    }

    public function test_localised_date_with_default_format_returns_formatted_string(): void
    {
        $timestamp = Carbon::parse('2025-06-15 14:30:00')->getTimestamp();

        $result = $this->repository->localisedDate($timestamp);

        $this->assertNotEmpty($result);
        $this->assertStringContainsString('2025', $result);
    }

    public function test_localised_date_with_custom_format(): void
    {
        $timestamp = Carbon::parse('2025-06-15 14:30:45')->getTimestamp();

        $result = $this->repository->localisedDate($timestamp, '%Y-%m-%d %H:%M:%S');

        $this->assertSame('2025-06-15 14:30:45', $result);
    }

    public function test_localised_date_with_date_only_format(): void
    {
        $timestamp = Carbon::parse('2025-06-15 14:30:00')->getTimestamp();

        $result = $this->repository->localisedDate($timestamp, '%F');

        $this->assertSame('2025-06-15', $result);
    }

    public function test_localised_date_with_time_only_format(): void
    {
        $timestamp = Carbon::parse('2025-06-15 14:30:45')->getTimestamp();

        $result = $this->repository->localisedDate($timestamp, '%T');

        $this->assertSame('14:30:45', $result);
    }

    public function test_localised_date_includes_month_name(): void
    {
        $timestamp = Carbon::parse('2025-01-15 14:30:00')->getTimestamp();

        $result = $this->repository->localisedDate($timestamp, '%b');

        $this->assertSame('Jan', $result);
    }

    public function test_localised_date_includes_day_of_week(): void
    {
        // 2025-06-15 is a Sunday
        $timestamp = Carbon::parse('2025-06-15 14:30:00')->getTimestamp();

        $result = $this->repository->localisedDate($timestamp, '%a');

        $this->assertSame('Sun', $result);
    }

    public function test_localised_date_with_percent_escape(): void
    {
        $result = $this->repository->localisedDate(0, '100%%');

        $this->assertSame('100%', $result);
    }

    public function test_localised_date_defaults_to_current_time_when_negative(): void
    {
        $before = time();
        $result = $this->repository->localisedDate(-1, '%s');
        $after = time();

        $ts = (int) $result;
        $this->assertGreaterThanOrEqual($before, $ts);
        $this->assertLessThanOrEqual($after, $ts);
    }
}
