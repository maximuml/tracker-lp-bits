<?php

declare(strict_types=1);

namespace Tests\Unit\ViewModels;

use App\Support\Html\SafeHtml;
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
#[TestCategory(TestCategory::UNIT)]
final class TorrentListViewModelsTest extends TestCase
{
    public function test_list_row_exposes_prepared_fields(): void
    {
        $row = new TorrentListRow(
            id: 42,
            rowAttrs: SafeHtml::fromTrustedHtml(' class="free"'),
            categoryCell: SafeHtml::fromTrustedHtml('<img />'),
            coverSrc: 'cover.jpg',
            stickyCount: 2,
            stickyTitle: 'Sticky level 1',
            nameUrl: 'details.php?id=42&hit=1',
            displayName: 'Name…',
            nameTitle: 'Full name',
            isNew: true,
            isBanned: false,
            badges: SafeHtml::fromTrustedHtml('<img class="pro_free" />'),
            tags: SafeHtml::fromTrustedHtml(''),
            progressBar: SafeHtml::fromTrustedHtml(''),
            showDownload: true,
            downloadUrl: 'download.php?id=42',
            showBookmark: true,
            bookmarkElementId: 'bookmark0',
            bookmarkCounter: 0,
            bookmarkMarkup: SafeHtml::fromTrustedHtml('<img />'),
            waitText: '5h',
            waitColor: 'ff0000',
            commentsUrl: 'details.php?id=42&cmtpage=1',
            comments: 7,
            commentIsNew: true,
            lastCommentTooltipId: 'lastcom_0',
            time: SafeHtml::fromTrustedHtml('<span>1h</span>'),
            size: SafeHtml::fromTrustedHtml('4.00<br />GB'),
            seedersUrl: 'details.php?id=42&dllist=1#seeders',
            seeders: 12,
            seedersColor: '#00ff00',
            seedersZeroClass: '',
            leechersUrl: 'details.php?id=42&dllist=1#leechers',
            leechers: 3,
            snatchedUrl: 'viewsnatches.php?id=42',
            snatched: 9,
            uploaderAnonymous: false,
            uploaderShowOwner: false,
            uploaderName: SafeHtml::fromTrustedHtml('<b>sysop</b>'),
            staffDeleteUrl: 'fastdelete.php?id=42',
            staffEditUrl: 'edit.php?id=42',
        );

        $this->assertSame(42, $row->id);
        $this->assertSame(2, $row->stickyCount);
        $this->assertTrue($row->isNew);
        $this->assertFalse($row->isBanned);
        $this->assertSame('5h', $row->waitText);
        $this->assertSame('ff0000', $row->waitColor);
        $this->assertSame('fastdelete.php?id=42', $row->staffDeleteUrl);
        $this->assertSame('edit.php?id=42', $row->staffEditUrl);
        $this->assertStringContainsString('4.00', $row->size->toHtml());
    }

    public function test_list_view_model_carries_columns_rows_and_flags(): void
    {
        $vm = new TorrentListViewModel(
            columns: [['key' => 'name', 'label' => 'Name', 'iconClass' => '', 'iconTitle' => '', 'sortUrl' => '?sort=1&type=asc']],
            rows: [],
            showComments: true,
            canManage: false,
            showPromotionNote: true,
            lastCommentTooltips: SafeHtml::fromTrustedHtml(''),
            lang: ['col_name' => 'Name'],
        );

        $this->assertCount(1, $vm->columns);
        $this->assertSame([], $vm->rows);
        $this->assertTrue($vm->showComments);
        $this->assertFalse($vm->canManage);
        $this->assertTrue($vm->showPromotionNote);
        $this->assertSame('Name', $vm->lang['col_name']);
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
