<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Wave 5 Step 35: Schema drift — migrations are the single source of truth.
 *
 * Verifies that:
 * - _db/dbstructure.sql (legacy 2010 dump) is removed
 * - All tables in _db/dbstructure_v1.6.sql have corresponding migrations
 * - Key tables (peers, snatched, torrents, users) exist after migration
 * - Legacy lookup tables (downloadspeed, uploadspeed, isp, teams) are seeded
 */
final class SchemaDriftTest extends TestCase
{
    /**
     * The legacy _db/dbstructure.sql (2010 dump) is removed.
     */
    public function test_legacy_dbstructure_sql_removed(): void
    {
        $this->assertFileDoesNotExist(base_path('_db/dbstructure.sql'), 'Legacy _db/dbstructure.sql should be removed — migrations are the source of truth');
    }

    /**
     * _db/dbstructure_v1.6.sql still exists (used for seed data import).
     */
    public function test_dbstructure_v1_6_sql_exists(): void
    {
        $this->assertFileExists(base_path('_db/dbstructure_v1.6.sql'), 'dbstructure_v1.6.sql should still exist for seed data import');
    }

    /**
     * All tables in dbstructure_v1.6.sql have corresponding migrations
     * (either a create_*_table migration or the legacy tables migration).
     */
    public function test_all_sql_tables_have_migrations(): void
    {
        $sqlFile = base_path('_db/dbstructure_v1.6.sql');
        $content = file_get_contents($sqlFile);
        preg_match_all('/CREATE TABLE `(\w+)`/i', $content, $matches);
        $sqlTables = $matches[1];

        // Tables from SQL that are allowed to NOT have a create_*_table migration
        // (they're created by the legacy tables migration or Laravel itself)
        $exceptions = ['migrations'];

        $migrationDir = base_path('database/migrations');
        $migrationFiles = array_map('basename', glob($migrationDir.'/*.php'));

        $missing = [];
        foreach ($sqlTables as $table) {
            if (in_array($table, $exceptions)) {
                continue;
            }
            // Check for create_{table}_table migration
            $found = false;
            foreach ($migrationFiles as $file) {
                if (str_contains($file, "create_{$table}_table")) {
                    $found = true;
                    break;
                }
            }
            // Also check the legacy tables migration
            if (! $found && str_contains(file_get_contents(base_path('database/migrations/2026_09_01_000001_create_legacy_tables_without_migrations.php')), "'{$table}'")) {
                $found = true;
            }
            if (! $found) {
                $missing[] = $table;
            }
        }

        $this->assertEmpty($missing, 'Tables in dbstructure_v1.6.sql without migrations: '.implode(', ', $missing));
    }

    /**
     * Key tables exist after running migrations.
     */
    public function test_key_tables_exist(): void
    {
        foreach (['peers', 'snatched', 'torrents', 'users'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Table {$table} must exist after migration");
        }
    }

    /**
     * Legacy lookup tables exist after migration.
     */
    public function test_legacy_lookup_tables_exist(): void
    {
        foreach (['downloadspeed', 'uploadspeed', 'isp', 'teams', 'schools', 'advertisements', 'fun', 'funvotes', 'links', 'prolinkclicks', 'requests', 'subs'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Legacy table {$table} must exist after migration");
        }
    }

    /**
     * LegacyLookupTableSeeder seeds downloadspeed with 18 entries.
     */
    public function test_downloadspeed_seeded(): void
    {
        $this->assertDatabaseCount('downloadspeed', 18);
    }

    /**
     * LegacyLookupTableSeeder seeds uploadspeed with 18 entries.
     */
    public function test_uploadspeed_seeded(): void
    {
        $this->assertDatabaseCount('uploadspeed', 18);
    }

    /**
     * LegacyLookupTableSeeder seeds isp with 7 entries.
     */
    public function test_isp_seeded(): void
    {
        $this->assertDatabaseCount('isp', 7);
    }

    /**
     * LegacyLookupTableSeeder seeds teams with 5 entries.
     */
    public function test_teams_seeded(): void
    {
        $this->assertDatabaseCount('teams', 5);
    }

    /**
     * The installer's listAllTableCreateFromMigrations() returns tables
     * from migration files, not from the SQL dump.
     */
    public function test_installer_uses_migrations_not_sql(): void
    {
        $source = file_get_contents(app_path('Support/Install/Install.php'));
        $this->assertStringContainsString('listAllTableCreateFromMigrations', $source);
        // listShouldCreateTable should use migrations, not SQL
        $this->assertStringContainsString('$tableCreate = $this->listAllTableCreateFromMigrations()', $source);
    }
}
