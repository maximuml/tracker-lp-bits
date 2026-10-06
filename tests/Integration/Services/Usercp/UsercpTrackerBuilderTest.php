<?php

declare(strict_types=1);

namespace Tests\Integration\Services\Usercp;

use App\Services\Usercp\UsercpTrackerBuilder;
use App\ViewModels\Search\SearchCategoryTableFactory;
use App\ViewModels\Usercp\UsercpTrackerSection;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Attributes\TestCategory;
use Tests\Concerns\SeedsLegacySettings;
use Tests\TestCase;

/**
 * Kills escaped mutants in the tracker-section builder (formerly
 * UsercpPageService::buildTrackerSection): notifs regex parses
 * (spstate loop, incldead, inclbookmarked), pm/email notification flags,
 * enum-to-form-string mappings, yes/no browse flags.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class UsercpTrackerBuilderTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsLegacySettings;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('searchbox')->insert(['id' => 1, 'name' => 'test']);
        $this->seedTestSettings([
            'browsecatmode' => 1,
            'emailnotify_smtp' => 'yes',
            'smtptype' => 'internal',
            'showshoutbox_main' => 'yes',
            'enabletooltip_tweak' => 'yes',
        ]);
    }

    private function builder(): UsercpTrackerBuilder
    {
        return new UsercpTrackerBuilder(app(SearchCategoryTableFactory::class));
    }

    /** @param  array<string, mixed>  $overrides */
    /** @param  array<string, mixed>  $overrides */
    private function section(array $overrides = []): UsercpTrackerSection
    {
        return $this->builder()->build(array_merge([
            'notifs' => '',
            'stylesheet' => 1,
            'pmnum' => 20,
            'sbnum' => 15,
            'sbrefresh' => 30,
            'torrentsperpage' => 25,
            'showdescription' => 'yes',
            'showcomment' => 'no',
            'timetype' => 0,
            'tooltip' => 0,
            'appendsticky' => 'yes',
            'appendnew' => 'no',
            'appendpromotion' => 1,
            'appendpicked' => 'yes',
            'dlicon' => 'yes',
            'bmicon' => 'no',
            'showcomnum' => 'yes',
            'showlastcom' => 'yes',
            'fontsize' => 2,
            'theme' => 'light',
        ], $overrides));
    }

    public function test_notifs_markers_drive_flags_and_special_state(): void
    {
        $s = $this->section(['notifs' => '[pm][email][spstate=5][incldead=0][inclbookmarked=2]']);

        $this->assertTrue($s->pmnotif);
        $this->assertTrue($s->emailnotif);
        $this->assertSame(5, $s->specialState);
        $this->assertSame(0, $s->incldead);
        $this->assertSame(2, $s->inclbookmarked);
    }

    public function test_special_state_picks_highest_marker(): void
    {
        // The loop scans 7..0 and keeps the first found — [spstate=7] wins
        // over a lower marker also present in the string.
        $s = $this->section(['notifs' => '[spstate=2][spstate=7]']);

        $this->assertSame(7, $s->specialState);
    }

    public function test_empty_notifs_defaults(): void
    {
        $s = $this->section(['notifs' => '']);

        $this->assertFalse($s->pmnotif);
        $this->assertFalse($s->emailnotif);
        $this->assertSame(0, $s->specialState);
        $this->assertSame(1, $s->incldead);
        $this->assertSame(0, $s->inclbookmarked);
    }

    public function test_enum_and_flag_mapping(): void
    {
        $s = $this->section();

        $this->assertSame('timeadded', $s->timetype);
        $this->assertSame('minorimdb', $s->tooltip);
        $this->assertSame('word', $s->appendpromotion);
        $this->assertSame('large', $s->fontsize);
        $this->assertTrue($s->showdescription);
        $this->assertFalse($s->showcomment);
        $this->assertTrue($s->appendsticky);
        $this->assertFalse($s->appendnew);
        $this->assertTrue($s->appendpicked);
        $this->assertTrue($s->dlicon);
        $this->assertFalse($s->bmicon);
        $this->assertTrue($s->showcomnum);
        $this->assertTrue($s->showlastcom);
        $this->assertTrue($s->showShoutbox);
        $this->assertTrue($s->showEmailNotify);
        $this->assertTrue($s->showTooltipSetting);
        $this->assertSame(20, $s->pmnum);
        $this->assertSame(15, $s->sbnum);
        $this->assertSame(30, $s->sbrefresh);
        $this->assertSame(25, $s->torrentsperpage);
    }

    public function test_showlastcom_false_only_on_explicit_no(): void
    {
        // showlastcom uses !isNo() — missing key must default to true.
        $this->assertTrue($this->section(['showlastcom' => null])->showlastcom);
        $this->assertFalse($this->section(['showlastcom' => 'no'])->showlastcom);
    }

    public function test_email_notify_off_when_smtp_none(): void
    {
        $this->seedTestSettings(['emailnotify_smtp' => 'yes', 'smtptype' => 'none']);

        $this->assertFalse($this->section()->showEmailNotify);
    }

    public function test_theme_and_stylesheet_options_built(): void
    {
        $s = $this->section(['theme' => 'dark', 'stylesheet' => 2]);

        $this->assertSame('dark', $s->currentTheme);
        $this->assertSame(2, $s->currentStylesheet);
        $this->assertNotEmpty($s->themeOptions);
    }
}
