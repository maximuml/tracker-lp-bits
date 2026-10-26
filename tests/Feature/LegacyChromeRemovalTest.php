<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\TorrentController;
use App\Support\Style;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Phase 10 ratchet: the `legacy` chrome variant, its vendor libs
 * (layui, DataTables) and the `addiCode` raw-CSS injection path stay
 * removed. FullCalendar is intentionally retained for attendance.
 */
#[TestCategory(TestCategory::HTTP_FEATURE)]
final class LegacyChromeRemovalTest extends TestCase
{
    public function test_vendor_libs_removed_and_fullcalendar_retained(): void
    {
        $this->assertDirectoryDoesNotExist(public_path('vendor/layui'));
        $this->assertDirectoryDoesNotExist(public_path('vendor/datatables-1.12.1'));
        $this->assertDirectoryExists(public_path('vendor/fullcalendar-5.10.2'));
    }

    public function test_dead_views_and_endpoints_removed(): void
    {
        $this->assertFileDoesNotExist(resource_path('views/layui-page.blade.php'));
        $this->assertFileDoesNotExist(resource_path('views/data-table.blade.php'));
        $this->assertFalse(method_exists(TorrentController::class, 'approvalLogs'));
        $this->assertStringNotContainsString(
            'torrent-approval-logs',
            file_get_contents(base_path('routes/web.php')) ?: ''
        );
    }

    public function test_addicode_injection_path_removed(): void
    {
        $this->assertFalse(method_exists(Style::class, 'addiCode'));
        $this->assertFalse(method_exists(Style::class, 'addiCodeWithContext'));

        $shoutbox = file_get_contents(
            resource_path('views/shoutbox/index.blade.php')
        );
        $this->assertIsString($shoutbox);
        $this->assertStringNotContainsString('addiCode', $shoutbox);
    }

    public function test_no_legacy_variant_references_remain(): void
    {
        foreach (
            [
                app_path('View/Composers/SiteChromeComposer.php'),
                app_path('ViewModels/Chrome/ChromeHead.php'),
                app_path('Support/PageRenderer.php'),
                resource_path('views/torrent/approval.blade.php'),
            ] as $file
        ) {
            $source = file_get_contents($file);
            $this->assertIsString($source);
            $this->assertStringNotContainsString("'legacy'", $source, $file);
            $this->assertStringNotContainsString('layui', $source, $file);
        }
    }
}
