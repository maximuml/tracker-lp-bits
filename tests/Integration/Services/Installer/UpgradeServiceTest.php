<?php

declare(strict_types=1);

namespace Tests\Integration\Services\Installer;

use App\Services\Installer\UpgradeService;
use Illuminate\Support\Facades\Redis;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class UpgradeServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Redis::connection()->flushdb();
    }

    public function test_run_legacy_fixups_is_idempotent_on_current_schema(): void
    {
        $service = $this->app->make(UpgradeService::class);

        $logs = [];
        $service->runLegacyFixups(static function (string $message) use (&$logs): void {
            $logs[] = $message;
        });

        // On a fully-migrated schema every fixup block is a no-op; the run
        // completes and reports schema probes (pos_state is already varchar).
        $this->assertNotEmpty($logs);
        $removeMenus = array_values(array_filter($logs, static fn (string $m): bool => str_starts_with($m, '[REMOVE MENU]: ')));
        $this->assertNotEmpty($removeMenus);
        $posState = array_values(array_filter($logs, static fn (string $m): bool => str_starts_with($m, '[TORRENT POS_STATE]')));
        $this->assertNotEmpty($posState);
    }

    public function test_run_extra_migrate_reports_missing_tags_column(): void
    {
        $service = $this->app->make(UpgradeService::class);

        $logs = [];
        $service->runExtraMigrate(static function (string $message) use (&$logs): void {
            $logs[] = $message;
        });

        $this->assertContains('torrents table does not has column: tags', $logs);
    }
}
