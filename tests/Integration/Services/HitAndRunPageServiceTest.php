<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Enums\HitAndRunStatus;
use App\Models\HitAndRun;
use App\Models\Snatch;
use App\Models\Torrent;
use App\Models\User;
use App\Repositories\HitAndRunRepository;
use App\Services\HitAndRunPageService;
use App\Support\Ratio;
use App\Support\Settings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Attributes\TestCategory;
use Tests\Concerns\MakesCurrentUser;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class HitAndRunPageServiceTest extends TestCase
{
    use DatabaseTransactions;
    use MakesCurrentUser;

    private User $owner;

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
        $owner = User::factory()->create(['class' => 1, 'seedbonus' => 10000]);
        $this->owner = $owner;
    }

    protected function tearDown(): void
    {
        Settings::resetCache();
        parent::tearDown();
    }

    public function test_build_maps_rows_configuration_and_status_counts(): void
    {
        $inspecting = $this->createRow(
            HitAndRunStatus::INSPECTING->value,
            uploaded: 12000,
            downloaded: 16384,
            seedtime: 18 * 3600,
            comment: 'Review this torrent',
        );
        $reached = $this->createRow(HitAndRunStatus::REACHED->value);
        $unreached = $this->createRow(HitAndRunStatus::UNREACHED->value);
        $pardoned = $this->createRow(HitAndRunStatus::PARDONED->value);

        $page = $this->serviceForBalance(10000)->build(
            userId: (int) $this->owner->id,
            viewerId: (int) $this->owner->id,
            rows: [$inspecting, $reached, $unreached, $pardoned],
        );
        $row = $page->rows[0];

        $this->assertTrue($page->isEnabled);
        $this->assertSame(10000.0, $page->price);
        $this->assertSame(10000.0, $page->balance);
        $this->assertSame([1 => 1, 2 => 1, 3 => 1, 4 => 1], $page->statusCounts);
        $this->assertSame(72.0, $page->seedTimeMinimum);
        $this->assertSame(1.0, $page->ignoreWhenRatioReach);
        $this->assertSame(168.0, $page->inspectTime);
        $this->assertSame(5.0, $page->banUserWhenCountsReach);

        $this->assertSame('H&R service fixture', $row->torrentName);
        $this->assertSame('0.732', $row->ratio);
        $this->assertTrue($row->ratioLow);
        $this->assertSame('Review this torrent', $row->comment);
        $this->assertSame(18 * 3600, $row->seedDone);
        $this->assertSame(259200.0, $row->seedRequired);
        $this->assertNotNull($row->completedAt);
        $this->assertNotNull($row->inspectEndsIn);
        $this->assertTrue($row->canRemove);
        $this->assertFalse($row->removeDisabled);
        $this->assertNull($row->pointsShort);
    }

    public function test_build_enforces_ratio_threshold_balance_and_remove_eligibility(): void
    {
        $inspecting = $this->createRow(HitAndRunStatus::INSPECTING->value);
        $unreached = $this->createRow(
            HitAndRunStatus::UNREACHED->value,
            uploaded: 10000,
            downloaded: 10000,
        );
        $reached = $this->createRow(HitAndRunStatus::REACHED->value);
        $pardoned = $this->createRow(HitAndRunStatus::PARDONED->value);
        $rows = [$inspecting, $unreached, $reached, $pardoned];

        $page = $this->serviceForBalance(9999.5)->build(
            userId: (int) $this->owner->id,
            viewerId: (int) $this->owner->id,
            rows: $rows,
        );

        $this->assertTrue($page->rows[0]->ratioLow);
        $this->assertTrue($page->rows[0]->canRemove);
        $this->assertTrue($page->rows[0]->removeDisabled);
        $this->assertSame(1, $page->rows[0]->pointsShort);
        $this->assertFalse($page->rows[1]->ratioLow);
        $this->assertTrue($page->rows[1]->canRemove);
        $this->assertFalse($page->rows[2]->canRemove);
        $this->assertFalse($page->rows[3]->canRemove);

        $otherViewerPage = $this->serviceForBalance(20000)->build(
            userId: (int) $this->owner->id,
            viewerId: (int) $this->owner->id + 1,
            rows: $rows,
        );

        $this->assertFalse($otherViewerPage->rows[0]->canRemove);
        $this->assertFalse($otherViewerPage->rows[1]->canRemove);
    }

    public function test_build_omits_optional_configuration_and_handles_missing_snatch(): void
    {
        foreach ([
            'hr.mode' => 'disabled',
            'hr.seed_time_minimum' => '',
            'hr.ignore_when_ratio_reach' => '',
            'hr.inspect_time' => null,
            'hr.ban_user_when_counts_reach' => null,
        ] as $name => $value) {
            DB::table('settings')->updateOrInsert(
                ['name' => $name],
                ['value' => $value, 'autoload' => 1],
            );
        }
        Settings::resetCache();

        $row = $this->createRow(
            HitAndRunStatus::INSPECTING->value,
            uploaded: 1000,
            downloaded: 0,
        );
        $missingSnatch = $this->createRow(
            HitAndRunStatus::INSPECTING->value,
            uploaded: 1000,
            downloaded: 0,
            includeSnatchRelation: false,
        );
        $page = $this->serviceForBalance(0)->build(
            userId: (int) $this->owner->id,
            viewerId: (int) $this->owner->id,
            rows: [$row, $missingSnatch],
        );
        $viewRow = $page->rows[0];
        $missingSnatchRow = $page->rows[1];

        $this->assertFalse($page->isEnabled);
        $this->assertNull($page->seedTimeMinimum);
        $this->assertNull($page->ignoreWhenRatioReach);
        $this->assertNull($page->inspectTime);
        $this->assertNull($page->banUserWhenCountsReach);
        $this->assertSame([1 => 2, 2 => 0, 3 => 0, 4 => 0], $page->statusCounts);
        $this->assertSame(strip_tags(Ratio::hr(1000, 0)->toHtml()), $viewRow->ratio);
        $this->assertStringNotContainsString('<', $viewRow->ratio);
        $this->assertFalse($viewRow->ratioLow);
        $this->assertNotNull($viewRow->completedAt);
        $this->assertNull($viewRow->seedDone);
        $this->assertNull($viewRow->seedRequired);
        $this->assertNull($viewRow->inspectEndsIn);
        $this->assertSame(strip_tags(Ratio::hr(0, 0)->toHtml()), $missingSnatchRow->ratio);
        $this->assertNull($missingSnatchRow->completedAt);
        $this->assertNull($missingSnatchRow->seedDone);
        $this->assertNull($missingSnatchRow->seedRequired);
        $this->assertNull($missingSnatchRow->inspectEndsIn);
    }

    private function serviceForBalance(float $balance): HitAndRunPageService
    {
        $actor = $this->actor([
            'id' => (int) $this->owner->id,
            'username' => 'hr-owner',
            'class' => 1,
            'seedbonus' => $balance,
        ]);

        return new HitAndRunPageService($actor, app(HitAndRunRepository::class));
    }

    private function createRow(
        int $status,
        int $uploaded = 12000,
        int $downloaded = 16384,
        int $seedtime = 18 * 3600,
        string $comment = '',
        bool $includeSnatchRelation = true,
    ): HitAndRun {
        /** @var Torrent $torrent */
        $torrent = Torrent::factory()->owner($this->owner)->create([
            'name' => 'H&R service fixture',
        ]);
        $completedAt = now()->subDays(2)->toDateTimeString();
        /** @var Snatch $snatch */
        $snatch = Snatch::query()->create([
            'torrentid' => $torrent->id,
            'userid' => $this->owner->id,
            'ip' => '127.0.0.1',
            'port' => 54321,
            'uploaded' => $uploaded,
            'downloaded' => $downloaded,
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
        $row->setRelation('torrent', $torrent);
        $row->setRelation('snatch', $includeSnatchRelation ? $snatch : null);

        return $row;
    }
}
