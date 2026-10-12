<?php

declare(strict_types=1);

namespace App\ViewModels;

use App\Contracts\Repositories\SearchBoxRepositoryInterface;
use App\Models\Category;
use App\Models\SearchBox;
use App\Support\Locale;
use App\Support\Logger;

/**
 * Builds TorrentSearchPanelViewModel — the data half of
 * `SearchBox::buildCategoryTable()` (Variant A, ADR 0014).
 *
 * Same queries, same checked-state rules (query-string parse with
 * user_notifs fallback), same chunking — but returns structured rows
 * for the Blade template instead of concatenating <table> HTML.
 */
final class TorrentSearchPanelFactory
{
    public function __construct(
        private readonly SearchBoxRepositoryInterface $searchBoxes,
    ) {}

    /**
     * @param  list<string>  $hotSearches
     * @param  array<string, mixed>  $filterInput
     */
    public function create(
        int $mode,
        string $checkedValues,
        ?string $userNotifs,
        array $hotSearches,
        array $filterInput = [],
        bool $showApprovalStatusFilter = false,
    ): TorrentSearchPanelViewModel {
        $searchBox = $this->searchBoxes->findForCategoryTable($mode);

        parse_str($checkedValues, $checkedValuesArr);
        /** @var array<string, mixed> $filterInput */
        $filterInput = $filterInput !== [] ? $filterInput : $checkedValuesArr;

        $categoryRows = [];
        $categories = $this->searchBoxes->getCategoriesForTable($searchBox, true);
        foreach ($categories->chunk(max(1, (int) $searchBox->catsperrow)) as $chunk) {
            $cells = [];
            foreach ($chunk as $item) {
                /** @var Category $item */
                if ($item->mode == -1) {
                    $cells[] = ['selectAll' => true, 'checkPrefix' => 'cat'];

                    continue;
                }
                $checked = $checkedValues !== ''
                    ? str_contains($checkedValues, "[cat{$item->id}]")
                        || (isset($checkedValuesArr["cat{$item->id}"]) && $checkedValuesArr["cat{$item->id}"] == 1)
                        || (isset($checkedValuesArr['cat']) && $checkedValuesArr['cat'] == $item->id)
                    : ($userNotifs !== null && $userNotifs !== '' && str_contains($userNotifs, "[cat{$item->id}]"));

                $cells[] = [
                    'selectAll' => false,
                    'checkPrefix' => 'cat',
                    'id' => (int) $item->id,
                    'name' => (string) $item->name,
                    'checked' => $checked,
                    'checkboxName' => "cat{$item->id}",
                ];
            }
            $categoryRows[] = $cells;
        }

        $taxonomySections = [];
        $advancedFilterCount = 0;
        foreach ($this->withTaxonomies($searchBox) as $torrentField => $tableName) {
            $taxonomyCollection = $this->searchBoxes->getTaxonomyRows($tableName, $mode);
            $selectAll = new \stdClass;
            $selectAll->mode = -1;
            $taxonomyCollection->push($selectAll);

            $rows = [];
            foreach ($taxonomyCollection->chunk(max(1, (int) $searchBox->catsperrow)) as $chunk) {
                $cells = [];
                foreach ($chunk as $item) {
                    if ($item->mode == -1) {
                        $cells[] = ['selectAll' => true, 'checkPrefix' => $torrentField];

                        continue;
                    }
                    Logger::writeWithContext("toCheck: $checkedValues, $torrentField - {$item->id}", 'debug');
                    $checked = $checkedValues !== ''
                        ? str_contains($checkedValues, "[{$torrentField}{$item->id}]")
                            || (isset($checkedValuesArr["{$torrentField}{$item->id}"]) && $checkedValuesArr["{$torrentField}{$item->id}"] == 1)
                            || (isset($checkedValuesArr[$torrentField]) && $checkedValuesArr[$torrentField] == $item->id)
                        : ($userNotifs !== null && $userNotifs !== '' && str_contains($userNotifs, sprintf('[%s%s]', substr($torrentField, 0, 3), $item->id)));

                    $cells[] = [
                        'selectAll' => false,
                        'checkPrefix' => $torrentField,
                        'id' => (int) $item->id,
                        'name' => (string) $item->name,
                        'checked' => $checked,
                        'checkboxName' => "{$torrentField}{$item->id}",
                    ];
                    if ($checked) {
                        $advancedFilterCount++;
                    }
                }
                $rows[] = $cells;
            }
            $taxonomySections[] = ['label' => $searchBox->getTaxonomyLabel($torrentField), 'rows' => $rows];
        }

        $searchModes = [];
        foreach (SearchBox::listSearchModes() as $modeValue => $modeLabel) {
            $searchModes[(string) $modeValue] = (string) $modeLabel;
        }

        $advancedFilterCount += $this->countAdvancedFilters($filterInput, $showApprovalStatusFilter);
        $sortValue = $filterInput['sort'] ?? null;
        $sortType = $filterInput['type'] ?? null;
        $sortValue = is_scalar($sortValue) ? (string) $sortValue : null;
        $sortType = is_scalar($sortType) ? (string) $sortType : null;
        if (! in_array($sortValue, ['1', '2', '3', '4', '5', '6', '7', '8', '9'], true)
            || ! in_array($sortType, ['asc', 'desc'], true)) {
            $sortValue = null;
            $sortType = null;
        }

        return new TorrentSearchPanelViewModel(
            categoryRows: $categoryRows,
            taxonomySections: $taxonomySections,
            hotSearches: $hotSearches,
            categoryLabel: (string) Locale::trans('label.search_box.category', [], null),
            searchModes: $searchModes,
            promotionOptions: [
                1 => (string) (__('functions.text_normal')),
                2 => (string) (__('functions.text_free')),
                3 => (string) (__('functions.text_two_times_up')),
                4 => (string) (__('functions.text_free_two_times_up')),
                5 => (string) (__('functions.text_half_down')),
                6 => (string) (__('functions.text_half_down_two_up')),
                7 => (string) (__('functions.text_thirty_percent_down')),
            ],
            advancedFilterCount: $advancedFilterCount,
            sortValue: $sortValue,
            sortType: $sortType,
        );
    }

    /**
     * @param  array<string, mixed>  $filterInput
     */
    private function countAdvancedFilters(array $filterInput, bool $showApprovalStatusFilter): int
    {
        $count = 0;
        foreach ([
            'incldead' => ['default' => 1, 'allowed' => [0, 1, 2]],
            'spstate' => ['default' => 0, 'allowed' => [0, 1, 2, 3, 4, 5, 6, 7]],
            'inclbookmarked' => ['default' => 0, 'allowed' => [0, 1, 2]],
        ] as $field => $settings) {
            if (! array_key_exists($field, $filterInput)) {
                continue;
            }
            $rawValue = $filterInput[$field];
            $value = is_scalar($rawValue) && is_numeric($rawValue) ? (int) $rawValue : 0;
            if (! in_array($value, $settings['allowed'], true)) {
                $value = 0;
            }
            if ($value !== $settings['default']) {
                $count++;
            }
        }

        if (array_key_exists('search_mode', $filterInput)) {
            $rawSearchMode = $filterInput['search_mode'];
            $searchMode = is_scalar($rawSearchMode) ? (string) $rawSearchMode : '';
            if (! in_array($searchMode, ['0', '2'], true)) {
                $searchMode = '0';
            }
            if ($searchMode !== (string) SearchBox::getDefaultSearchMode()) {
                $count++;
            }
        }

        $approvalStatus = $filterInput['approval_status'] ?? null;
        if ($showApprovalStatusFilter && is_scalar($approvalStatus) && $approvalStatus !== '' && is_numeric($approvalStatus)) {
            $count++;
        }

        foreach ([
            ['size_begin', 'size_end'],
            ['seeders_begin', 'seeders_end'],
            ['leechers_begin', 'leechers_end'],
            ['times_completed_begin', 'times_completed_end'],
            ['added_begin', 'added_end'],
        ] as $range) {
            foreach ($range as $field) {
                $value = $filterInput[$field] ?? null;
                if (is_scalar($value) && trim((string) $value) !== '') {
                    $count++;
                    break;
                }
            }
        }

        return $count;
    }

    /**
     * @return array<string, string> torrent field => taxonomy table
     */
    private function withTaxonomies(SearchBox $searchBox): array
    {
        $withTaxonomies = [];
        if (! $searchBox->showsubcat) {
            return $withTaxonomies;
        }
        if (! empty($searchBox->extra[SearchBox::EXTRA_TAXONOMY_LABELS])) {
            foreach ($searchBox->extra[SearchBox::EXTRA_TAXONOMY_LABELS] as $taxonomyLabelInfo) {
                $torrentField = $taxonomyLabelInfo['torrent_field'];
                $showField = 'show'.$torrentField;
                if ($searchBox->{$showField}) {
                    $withTaxonomies[$torrentField] = SearchBox::$taxonomies[$torrentField]['table'];
                }
            }
        } else {
            foreach (SearchBox::$taxonomies as $torrentField => $taxonomyTableModel) {
                $showField = 'show'.$torrentField;
                if ($searchBox->{$showField}) {
                    $withTaxonomies[$torrentField] = $taxonomyTableModel['table'];
                }
            }
        }

        return $withTaxonomies;
    }
}
