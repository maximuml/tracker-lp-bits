<?php

declare(strict_types=1);

namespace Tests\Unit\ViewModels;

use App\Models\Tag;
use App\Support\Html\SafeHtml;
use App\ViewModels\Torrent\CategoryIcon;
use App\ViewModels\Torrent\PromotionBadge;
use App\ViewModels\Torrent\TorrentBadgeSet;
use App\ViewModels\Torrent\TorrentProgress;
use App\ViewModels\TorrentListRow;
use App\ViewModels\TorrentListViewModel;
use App\ViewModels\TorrentSearchPanelViewModel;
use PHPUnit\Framework\TestCase;
use Tests\Attributes\TestCategory;

/**
 * Pure-DTO construction for the modern torrents page view models
 * (Variant A, ADR 0014). The factories are exercised by the Feature
 * suite; the DTOs are covered here.
 */
#[TestCategory(TestCategory::PURE_UNIT)]
final class TorrentListViewModelsTest extends TestCase
{
    public function test_list_row_exposes_prepared_fields(): void
    {
        $row = new TorrentListRow(
            id: 42,
            rowClass: 'free_bg',
            categoryIcon: new CategoryIcon('cat_movies', 'Movies', '?cat=101'),
            secondIcon: new CategoryIcon('cat_src', 'BluRay'),
            coverSrc: 'cover.jpg',
            stickyCount: 2,
            stickyTitle: 'Sticky level 1',
            nameUrl: '/web/details/42?hit=1',
            displayName: 'Name…',
            nameTitle: 'Full name',
            isNew: true,
            isBanned: false,
            badges: new TorrentBadgeSet(
                paid: true,
                promotion: new PromotionBadge('icon', 'free', 'pro_free', 'Free', 'Free', null, null, null),
                hitAndRun: true,
                approval: null,
            ),
            tags: [new Tag],
            progress: new TorrentProgress('seeding', 75.0),
            showDownload: true,
            downloadUrl: '/download?id=42',
            showBookmark: true,
            waitText: '5h',
            waitClass: 'nx-wait-10',
            commentsUrl: '/web/details/42?hit=1&cmtpage=1#startcomments',
            comments: 7,
            commentIsNew: true,
            lastCommentTooltipId: 'lastcom_0',
            added: '2024-01-01 12:00:00',
            addedDate: '2024-01-01',
            addedTime: '12:00:00',
            size: ['value' => '4.00', 'unit' => 'GB'],
            seedersUrl: '/web/details/42?hit=1&dllist=1#seeders',
            seeders: 12,
            seedersClass: 'nx-sl-1',
            seedersZeroClass: '',
            leechersUrl: '/web/details/42?hit=1&dllist=1#leechers',
            leechers: 3,
            snatchedUrl: '/web/viewsnatches?id=42',
            snatched: 9,
            uploaderAnonymous: false,
            uploaderShowOwner: false,
            uploaderName: SafeHtml::fromTrustedHtml('<b>sysop</b>'),
        );

        $this->assertSame(42, $row->id);
        $this->assertSame('free_bg', $row->rowClass);
        $this->assertSame('Movies', $row->categoryIcon->name);
        $this->assertSame(2, $row->stickyCount);
        $this->assertTrue($row->isNew);
        $this->assertFalse($row->isBanned);
        $this->assertTrue($row->badges->paid);
        $this->assertSame('pro_free', $row->badges->promotion->iconClass);
        $this->assertTrue($row->badges->hitAndRun);
        $this->assertTrue($row->badges->approval === null);
        $this->assertFalse($row->badges->isEmpty());
        $this->assertSame(75.0, $row->progress->percent);
        $this->assertSame('5h', $row->waitText);
        $this->assertSame('nx-wait-10', $row->waitClass);
        $this->assertSame('4.00', $row->size['value']);
        $this->assertSame('GB', $row->size['unit']);
    }

    public function test_badge_set_reports_empty_when_nothing_applies(): void
    {
        $this->assertTrue((new TorrentBadgeSet)->isEmpty());
        $this->assertFalse((new TorrentBadgeSet(hitAndRun: true))->isEmpty());
    }

    public function test_list_view_model_carries_columns_rows_and_flags(): void
    {
        $vm = new TorrentListViewModel(
            columns: [['key' => 'name', 'label' => 'Name', 'iconClass' => '', 'iconTitle' => '', 'sortUrl' => '?sort=1&type=asc']],
            rows: [],
            showComments: true,
            showPromotionNote: true,
            lastCommentTooltips: [['id' => 'lastcom_0', 'content' => SafeHtml::fromTrustedHtml('<b>x</b>')]],
        );

        $this->assertCount(1, $vm->columns);
        $this->assertSame([], $vm->rows);
        $this->assertTrue($vm->showComments);
        $this->assertTrue($vm->showPromotionNote);
    }

    public function test_search_panel_view_model_carries_structured_sections(): void
    {
        $vm = new TorrentSearchPanelViewModel(
            categoryRows: [[
                ['selectAll' => false, 'checkPrefix' => 'cat', 'id' => 1, 'name' => 'Movies', 'checked' => true, 'checkboxName' => 'cat1', 'href' => '?cat=1', 'iconClass' => 'movies', 'iconStyle' => ''],
                ['selectAll' => true, 'checkPrefix' => 'cat'],
            ]],
            taxonomySections: [['label' => 'Source', 'rows' => [[['selectAll' => true, 'checkPrefix' => 'source']]]]],
            hotSearches: ['bluray', 'dvdr'],
            categoryLabel: 'Category',
            catPadding: 7,
            searchModes: ['0' => 'And', '2' => 'Exact'],
            promotionOptions: [1 => 'Normal', 2 => 'Free'],
            selectAllLabel: 'Select all',
            unselectAllLabel: 'Unselect all',
        );

        $this->assertCount(2, $vm->categoryRows[0]);
        $this->assertSame('Source', $vm->taxonomySections[0]['label']);
        $this->assertSame(['bluray', 'dvdr'], $vm->hotSearches);
        $this->assertSame('Category', $vm->categoryLabel);
        $this->assertSame(7, $vm->catPadding);
        $this->assertSame('Exact', $vm->searchModes['2']);
        $this->assertSame('Free', $vm->promotionOptions[2]);
        $this->assertSame('Select all', $vm->selectAllLabel);
        $this->assertSame('Unselect all', $vm->unselectAllLabel);
    }
}
