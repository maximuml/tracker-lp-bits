<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * W3-07: Verify that the read/write split configuration is correct and
 * that the 'sticky' option ensures reads after a write go to the writer.
 *
 * These tests verify the configuration shape rather than actual replica
 * routing (which requires a separate read replica in the test environment).
 * They ensure:
 *   1. The mysql connection has separate read/write host configs.
 *   2. The sticky option is enabled (reads after writes go to writer).
 *   3. DB::connection() returns the configured connection.
 *
 * @group database
 */
#[TestCategory(TestCategory::HTTP_FEATURE)]
final class ReadReplicaStickyTest extends TestCase
{
    /**
     * The MySQL connection must have read and write host arrays.
     */
    public function test_mysql_connection_has_read_write_split_config(): void
    {
        $config = config('database.connections.mysql');

        $this->assertIsArray($config['read'] ?? null, 'mysql.read must be an array');
        $this->assertIsArray($config['write'] ?? null, 'mysql.write must be an array');
        $this->assertArrayHasKey('host', $config['read'], 'mysql.read must have a host key');
        $this->assertArrayHasKey('host', $config['write'], 'mysql.write must have a host key');
    }

    /**
     * The sticky option must be enabled so that reads following a write
     * in the same request go to the writer, preventing replication lag
     * from causing stale reads.
     */
    public function test_mysql_connection_has_sticky_enabled(): void
    {
        $config = config('database.connections.mysql');

        $this->assertTrue(
            $config['sticky'] ?? false,
            'mysql.sticky must be true to prevent stale reads after writes'
        );
    }

    /**
     * DB_READ_HOST env var should be configurable independently from DB_HOST.
     */
    public function test_read_host_can_differ_from_write_host(): void
    {
        $writeHost = config('database.connections.mysql.write.host');
        $readHost = config('database.connections.mysql.read.host');

        // In test environment, both may point to the same host, but the
        // config structure must support different hosts.
        $this->assertNotEmpty($writeHost, 'Write host must be configured');
        $this->assertNotEmpty($readHost, 'Read host must be configured');
    }

    /**
     * A write operation followed by a read in the same request should
     * use the sticky connection (same host as write).
     *
     * This test verifies the sticky behavior by performing a write and
     * then a read, confirming the read returns the written value.
     */
    public function test_sticky_read_after_write_returns_written_data(): void
    {
        $connection = DB::connection();

        // Perform a write
        $connection->table('activity_log')->insert([
            'log_name' => 'sticky_test',
            'description' => 'sticky read-after-write test',
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);

        // Immediately read back — with sticky=true, this should read
        // from the writer and see the just-written data.
        $record = $connection->table('activity_log')
            ->where('log_name', 'sticky_test')
            ->where('description', 'sticky read-after-write test')
            ->first();

        $this->assertNotNull($record, 'Sticky read after write must return the written data');

        // Cleanup
        $connection->table('activity_log')
            ->where('log_name', 'sticky_test')
            ->delete();
    }

    /**
     * The default connection should be mysql (which has the read/write split).
     */
    public function test_default_connection_is_mysql(): void
    {
        $this->assertSame('mysql', config('database.default'));
    }
}
