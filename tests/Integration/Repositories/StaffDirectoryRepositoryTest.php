<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Repositories\StaffDirectoryRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Panel menu URLs: legacy rows may hold bare script names which must be
 * exposed/stored as root-relative URLs, and lookups/deletes must match
 * both spellings (R3-01).
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class StaffDirectoryRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private const TABLES = ['sysoppanel', 'adminpanel', 'modpanel'];

    private StaffDirectoryRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (self::TABLES as $table) {
            DB::table($table)->delete();
        }
        $this->repository = new StaffDirectoryRepository;
    }

    public function test_list_panels_normalizes_relative_urls(): void
    {
        DB::table('sysoppanel')->insert(['name' => 'Forums', 'url' => 'forummanage.php', 'info' => 'i']);
        DB::table('adminpanel')->insert(['name' => 'Canonical', 'url' => '/fields', 'info' => 'i']);
        DB::table('modpanel')->insert(['name' => 'Ext', 'url' => 'https://example.com/x', 'info' => 'i']);

        $this->assertSame('/forummanage.php', $this->repository->listSysopPanels()[0]['url']);
        $this->assertSame('/fields', $this->repository->listAdminPanels()[0]['url']);
        $this->assertSame('https://example.com/x', $this->repository->listModPanels()[0]['url']);
    }

    public function test_insert_panel_menu_stores_root_relative_url(): void
    {
        $id = $this->repository->insertPanelMenu('adminpanel', ['name' => 'New', 'url' => 'adduser.php', 'info' => 'i']);

        $this->assertSame('/adduser.php', DB::table('adminpanel')->where('id', $id)->value('url'));
    }

    public function test_panel_menu_exists_matches_legacy_and_canonical(): void
    {
        DB::table('sysoppanel')->insert(['name' => 'Bans', 'url' => 'bans.php', 'info' => 'i']);
        DB::table('sysoppanel')->insert(['name' => 'Search', 'url' => '/web/usersearch', 'info' => 'i']);

        $this->assertTrue($this->repository->panelMenuExists('sysoppanel', 'bans.php'));
        $this->assertTrue($this->repository->panelMenuExists('sysoppanel', '/bans.php'));
        $this->assertTrue($this->repository->panelMenuExists('sysoppanel', '/web/usersearch'));
        $this->assertFalse($this->repository->panelMenuExists('sysoppanel', 'absent.php'));
    }

    public function test_delete_panel_menus_by_urls_matches_both_spellings(): void
    {
        DB::table('modpanel')->insert(['name' => 'Stats', 'url' => 'stats.php', 'info' => 'i']);
        DB::table('modpanel')->insert(['name' => 'Ip', 'url' => '/ipcheck.php', 'info' => 'i']);

        $this->assertSame(1, $this->repository->deletePanelMenusByUrls(['/stats.php'], ['modpanel']));
        $this->assertSame(1, $this->repository->deletePanelMenusByUrls(['ipcheck.php'], ['modpanel']));
        $this->assertSame(0, DB::table('modpanel')->count());
    }
}
