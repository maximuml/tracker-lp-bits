<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\HitAndRunStatus;
use App\Models\HitAndRun;
use App\Models\Snatch;
use App\Models\Torrent;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::HTTP_FEATURE)]
final class MyHrPageTest extends TestCase
{
    use DatabaseTransactions;

    private User $owner;

    /** @var array<int, HitAndRun> */
    private array $rows = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'bonus.cancel_hr' => '10000',
            'hr.mode' => 'global',
            'hr.seed_time_minimum' => '72',
            'hr.ignore_when_ratio_reach' => '1',
            'hr.inspect_time' => '168',
            'hr.ban_user_when_counts_reach' => '5',
        ] as $name => $value) {
            DB::table('settings')->updateOrInsert(
                ['name' => $name],
                ['value' => $value, 'autoload' => 1],
            );
        }
        Settings::resetCache();

        /** @var User $owner */
        $owner = User::factory()->create(['class' => 1, 'seedbonus' => 20000]);
        $this->owner = $owner;
        $this->rows = [
            $this->createRow(HitAndRunStatus::INSPECTING->value, 18 * 3600, 'First inspecting row', true),
            $this->createRow(HitAndRunStatus::INSPECTING->value, 36 * 3600),
            $this->createRow(HitAndRunStatus::INSPECTING->value, 72 * 3600),
            $this->createRow(HitAndRunStatus::REACHED->value, 72 * 3600),
            $this->createRow(HitAndRunStatus::UNREACHED->value, 2 * 3600, "First line\nSecond line"),
            $this->createRow(HitAndRunStatus::PARDONED->value, 0),
        ];
    }

    protected function tearDown(): void
    {
        Settings::resetCache();
        parent::tearDown();
    }

    public function test_status_cards_show_all_counts_active_state_and_preserve_userid(): void
    {
        /** @var User $staff */
        $staff = User::factory()->create(['class' => 8]);

        $this->withNexusCookie($staff)
            ->get('/web/myhr?userid='.$this->owner->id)
            ->assertOk()
            ->assertSee('aria-label="H&amp;R status"', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('class="nx-hr__status-count">3</span>', false)
            ->assertSee('class="nx-hr__status-count">1</span>', false)
            ->assertSee('href="/web/myhr?userid='.$this->owner->id.'&amp;status=2"', false)
            ->assertSee('href="/web/myhr?userid='.$this->owner->id.'&amp;status=3"', false)
            ->assertSee('ban at 5');
    }

    public function test_search_form_keeps_status_and_userid(): void
    {
        /** @var User $staff */
        $staff = User::factory()->create(['class' => 8]);

        $this->withNexusCookie($staff)
            ->get('/web/myhr?userid='.$this->owner->id.'&status=3')
            ->assertOk()
            ->assertSee('<form id="filterForm"', false)
            ->assertSee('<label for="q">H&amp;R ID</label>', false)
            ->assertSee('<input type="hidden" name="status" value="3">', false)
            ->assertSee('<input type="hidden" name="userid" value="'.$this->owner->id.'">', false)
            ->assertSee('href="/web/myhr?userid='.$this->owner->id.'&amp;status=3"', false)
            ->assertDontSee('type="reset"', false)
            ->assertSee('<input id="q" type="number" name="q"', false);
    }

    public function test_rule_plaque_is_enabled_only_when_hit_and_run_is_enabled(): void
    {
        $this->withNexusCookie($this->owner)
            ->get('/web/myhr')
            ->assertOk()
            ->assertSee('After a download completes:')
            ->assertSee('seed 72 h')
            ->assertSee('or reach ratio 1.0')
            ->assertSee('within 7 days')
            ->assertSee('Inspecting and unreached H&R can be removed for 10,000 bonus points.');

        DB::table('settings')->where('name', 'hr.mode')->update(['value' => 'disabled']);
        Settings::resetCache();

        $this->withNexusCookie($this->owner)
            ->get('/web/myhr')
            ->assertOk()
            ->assertDontSee('class="nx-hr__rules"', false);
    }

    public function test_inspecting_row_shows_seed_progress_and_inspection_end(): void
    {
        $response = $this->withNexusCookie($this->owner)->get('/web/myhr')->assertOk();
        $html = (string) $response->getContent();

        $this->assertStringContainsString('18 h of 72 h', $html);
        $this->assertStringContainsString('max="259200"', $html);
        $this->assertStringContainsString('value="64800"', $html);
        $this->assertStringContainsString('ends in', $html);
        $this->assertStringContainsString('nx-hr__ratio--low', $html);
    }

    public function test_remove_button_is_enabled_for_an_affordable_own_row_and_appends_confirmation_script(): void
    {
        $response = $this->withNexusCookie($this->owner)->get('/web/myhr')->assertOk();
        $html = (string) $response->getContent();
        $this->assertMatchesRegularExpression(
            '/<input type="button" class="remove-hr nx-btn nx-btn--primary" value="Remove · 10,000 pts" data-id="'.$this->rows[0]->id.'"/',
            $html,
        );
        $this->assertStringContainsString('layer.confirm(', $html);
        $this->assertStringContainsString('/web/hit-and-runs/remove', $html);
    }

    public function test_remove_button_is_disabled_with_exact_shortage_when_balance_is_low(): void
    {
        $this->owner->update(['seedbonus' => 153]);
        $html = (string) $this->withNexusCookie($this->owner)->get('/web/myhr')->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/<input type="button" class="remove-hr nx-btn" value="Remove · 10,000 pts" data-id="'.$this->rows[0]->id.'" disabled/',
            $html,
        );
        $this->assertStringContainsString('9,847 more points needed', $html);
    }

    public function test_remove_is_absent_for_reached_pardoned_and_other_users_rows(): void
    {
        foreach ([HitAndRunStatus::REACHED->value, HitAndRunStatus::PARDONED->value] as $status) {
            $this->withNexusCookie($this->owner)
                ->get('/web/myhr?status='.$status)
                ->assertOk()
                ->assertDontSee('class="remove-hr', false);
        }

        /** @var User $staff */
        $staff = User::factory()->create(['class' => 8]);
        $this->withNexusCookie($staff)
            ->get('/web/myhr?userid='.$this->owner->id)
            ->assertOk()
            ->assertDontSee('class="remove-hr', false);
    }

    public function test_multiline_comment_is_rendered_as_breaks(): void
    {
        $this->withNexusCookie($this->owner)
            ->get('/web/myhr?status='.HitAndRunStatus::UNREACHED->value)
            ->assertOk()
            ->assertSee('First line<br>Second line', false);
    }

    private function createRow(int $status, int $seedtime, string $comment = '', bool $lowRatio = false): HitAndRun
    {
        /** @var Torrent $torrent */
        $torrent = Torrent::factory()->owner($this->owner)->create([
            'name' => 'H&R feature fixture torrent',
        ]);
        $completedAt = now()->subDays(2)->toDateTimeString();
        $lowRatio = $lowRatio || $status === HitAndRunStatus::UNREACHED->value;
        /** @var Snatch $snatch */
        $snatch = Snatch::query()->create([
            'torrentid' => $torrent->id,
            'userid' => $this->owner->id,
            'ip' => '127.0.0.1',
            'port' => 54321,
            'uploaded' => $lowRatio ? 12000 : 900000000,
            'downloaded' => $lowRatio ? 16384 : 647865608,
            'to_go' => 0,
            'seedtime' => $seedtime,
            'leechtime' => 3600,
            'last_action' => $completedAt,
            'startdat' => now()->subDays(3)->toDateTimeString(),
            'completedat' => $completedAt,
            'finished' => 1,
        ]);
        $row = HitAndRun::query()->create([
            'uid' => $this->owner->id,
            'torrent_id' => $torrent->id,
            'snatched_id' => $snatch->id,
            'status' => $status,
            'comment' => $comment,
        ]);
        $row->forceFill(['created_at' => $completedAt, 'updated_at' => $completedAt])->save();
        $snatch->update(['hit_and_run_id' => $row->id]);

        return $row;
    }
}
