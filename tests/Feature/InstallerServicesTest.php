<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Installer\InstallService;
use App\Services\Installer\UpgradeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Step 1.4: `app:install`/`app:upgrade` replaced the legacy web installer
 * (`app/Support/Install`). These tests pin the service-level contract on a
 * current-schema database: every conditional fixup must be a no-op, the
 * settings resolver must return defaults + symlink targets, and install
 * must refuse to clobber a populated database without --force.
 *
 * The peers-index check is covered deliberately: the legacy name-based
 * probe never matched Laravel's auto-generated index name, which made
 * `removeDuplicatePeer()` spin in an unbounded `while (true)` loop until
 * OOM on every upgrade of a clean database.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class InstallerServicesTest extends TestCase
{
    use DatabaseTransactions;

    public function test_resolve_settings_returns_defaults_and_symlinks(): void
    {
        $resolved = app(InstallService::class)->resolveSettings();

        $this->assertArrayHasKey('settings', $resolved);
        $this->assertArrayHasKey('symbolic_links', $resolved);
        $this->assertIsList($resolved['symbolic_links']);
        foreach (['basic', 'main', 'security', 'torrent'] as $prefix) {
            $this->assertArrayHasKey($prefix, $resolved['settings'], "settings.default.php must contain prefix '$prefix'");
        }
    }

    public function test_legacy_fixups_are_noop_on_current_schema(): void
    {
        $messages = [];
        app(UpgradeService::class)->runLegacyFixups(static function (string $message) use (&$messages): void {
            $messages[] = $message;
        });

        // On a current schema the dedupe paths must not run — this assertion
        // fails with an OOM hang (not a clean failure) if the peers unique
        // index check regresses back to the legacy name probe.
        $this->assertNotContains('removeDuplicatePeer and migrate 2023_04_01_005409_add_unique_torrent_peer_user_to_peers_table', $messages);
        $this->assertNotContains('removeDuplicateSnatch and migrate 2023_03_29_021950_handle_snatched_user_torrent_unique', $messages);
    }

    public function test_extra_migrate_is_noop_without_tags_column(): void
    {
        $messages = [];
        app(UpgradeService::class)->runExtraMigrate(static function (string $message) use (&$messages): void {
            $messages[] = $message;
        });

        $this->assertContains('torrents table does not has column: tags', $messages);
    }

    public function test_install_refuses_populated_database_without_force(): void
    {
        DB::table('users')->count() > 0 || $this->markTestSkipped('users table is empty — refusal path needs a populated database');

        $exitCode = Artisan::call('app:install', [
            '--skip-requirements' => true,
            '--username' => 'testadmin',
            '--email' => 'testadmin@example.com',
            '--password' => 'TestInstall2026!',
        ]);

        $this->assertSame(1, $exitCode, 'app:install must refuse a populated database without --force');
        $this->assertStringContainsString('already installed', Artisan::output());
    }
}
