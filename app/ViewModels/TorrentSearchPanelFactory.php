<?php

declare(strict_types=1);

namespace App\ViewModels;

use App\Contracts\Repositories\SearchBoxRepositoryInterface;
use App\Models\Category;
use App\Models\SearchBox;
use App\Models\Tag;
use App\Models\Torrent;
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
        $queryFilters = $filterInput;
        /** @var array<string, mixed> $filterInput */
        $filterInput = $filterInput !== [] ? $filterInput : $checkedValuesArr;

        $appliedFilters = [];
        $search = $queryFilters['search'] ?? null;
        if (is_scalar($search) && trim((string) $search) !== '') {
            $term = trim((string) $search);
            $appliedFilters[] = $this->appliedFilter(
                (string) __('torrents.applied_search', ['term' => $term]),
                $queryFilters,
                ['search', 'search_area', 'notnewword'],
            );
        }

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
                if ($this->isChecked($queryFilters["cat{$item->id}"] ?? null) || $this->matchesId($queryFilters['cat'] ?? null, (int) $item->id)) {
                    $appliedFilters[] = $this->appliedFilter(
                        (string) $item->name !== '' ? (string) $item->name : '#'.$item->id,
                        $queryFilters,
                        $this->matchesId($queryFilters['cat'] ?? null, (int) $item->id)
                            ? ["cat{$item->id}", 'cat'] : ["cat{$item->id}"],
                    );
                }

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
                    $fieldValue = $queryFilters[$torrentField] ?? null;
                    if ($this->isChecked($queryFilters["{$torrentField}{$item->id}"] ?? null) || $this->matchesId($fieldValue, (int) $item->id)) {
                        $sectionLabel = (string) $searchBox->getTaxonomyLabel($torrentField);
                        $appliedFilters[] = $this->appliedFilter(
                            $sectionLabel.': '.((string) $item->name !== '' ? (string) $item->name : '#'.$item->id),
                            $queryFilters,
                            $this->matchesId($fieldValue, (int) $item->id)
                                ? ["{$torrentField}{$item->id}", $torrentField] : ["{$torrentField}{$item->id}"],
                        );
                    }

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
        $promotionOptions = $this->promotionOptions();
        $this->appendStatusFilters($appliedFilters, $queryFilters, $searchModes, $promotionOptions);
        $this->appendRangeFilters($appliedFilters, $queryFilters);
        $this->appendTagFilter($appliedFilters, $queryFilters);

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
            promotionOptions: $promotionOptions,
            advancedFilterCount: $advancedFilterCount,
            sortValue: $sortValue,
            sortType: $sortType,
            appliedFilters: $appliedFilters,
            resetUrl: '/web/torrents',
        );
    }

    /**
     * @param  array<string, mixed>  $queryFilters
     * @param  list<string>  $removeKeys
     * @return array{label: string, removeUrl: string}
     */
    private function appliedFilter(string $label, array $queryFilters, array $removeKeys): array
    {
        $remaining = $queryFilters;
        foreach ([...$removeKeys, 'page'] as $key) {
            unset($remaining[$key]);
        }

        return [
            'label' => $label,
            'removeUrl' => '/web/torrents'.($remaining === [] ? '' : '?'.http_build_query($remaining)),
        ];
    }

    private function isChecked(mixed $value): bool
    {
        return is_scalar($value) && (string) $value === '1';
    }

    private function matchesId(mixed $value, int $id): bool
    {
        return is_scalar($value) && (string) $value === (string) $id;
    }

    /**
     * @param  list<array{label: string, removeUrl: string}>  $filters
     * @param  array<string, mixed>  $query
     * @param  array<int|string, string>  $searchModes
     * @param  array<int, string>  $promotionOptions
     */
    private function appendStatusFilters(array &$filters, array $query, array $searchModes, array $promotionOptions): void
    {
        $statuses = [
            'incldead' => [
                'default' => 1,
                'allowed' => [0, 1, 2],
                'label' => __('torrents.text_show_dead_active'),
                'options' => [0 => __('torrents.select_including_dead'), 1 => __('torrents.select_active'), 2 => __('torrents.select_dead')],
            ],
            'spstate' => [
                'default' => 0,
                'allowed' => range(0, 7),
                'label' => __('torrents.text_show_special_torrents'),
                'options' => [0 => __('torrents.select_all')] + $promotionOptions,
            ],
            'inclbookmarked' => [
                'default' => 0,
                'allowed' => [0, 1, 2],
                'label' => __('torrents.text_show_bookmarked'),
                'options' => [0 => __('torrents.select_all'), 1 => __('torrents.select_bookmarked'), 2 => __('torrents.select_bookmarked_exclude')],
            ],
        ];
        foreach ($statuses as $field => $settings) {
            if (! array_key_exists($field, $query)) {
                continue;
            }
            $raw = $query[$field];
            $value = is_scalar($raw) && is_numeric($raw) ? (int) $raw : 0;
            if (! in_array($value, $settings['allowed'], true)) {
                $value = 0;
            }
            if ($value !== $settings['default']) {
                $filters[] = $this->appliedFilter($settings['label'].': '.$settings['options'][$value], $query, [$field]);
            }
        }

        if (($query['approval_status'] ?? null) !== null
            && $query['approval_status'] !== ''
            && is_scalar($query['approval_status'])
            && is_numeric($query['approval_status'])) {
            $statuses = Torrent::listApprovalStatus(true);
            $value = (int) $query['approval_status'];
            if (isset($statuses[$value])) {
                $filters[] = $this->appliedFilter(__('torrents.text_approval_status').' '.$statuses[$value], $query, ['approval_status']);
            }
        }

        if (array_key_exists('search_mode', $query)) {
            $raw = $query['search_mode'];
            $value = is_scalar($raw) ? (string) $raw : '';
            if (! in_array($value, ['0', '2'], true)) {
                $value = '0';
            }
            if ($value !== (string) SearchBox::getDefaultSearchMode() && isset($searchModes[$value])) {
                $filters[] = $this->appliedFilter(__('torrents.label_search_mode').': '.$searchModes[$value], $query, ['search_mode']);
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function promotionOptions(): array
    {
        return [
            1 => (string) __('functions.text_normal'),
            2 => (string) __('functions.text_free'),
            3 => (string) __('functions.text_two_times_up'),
            4 => (string) __('functions.text_free_two_times_up'),
            5 => (string) __('functions.text_half_down'),
            6 => (string) __('functions.text_half_down_two_up'),
            7 => (string) __('functions.text_thirty_percent_down'),
        ];
    }

    /**
     * @param  list<array{label: string, removeUrl: string}>  $filters
     * @param  array<string, mixed>  $query
     */
    private function appendRangeFilters(array &$filters, array $query): void
    {
        foreach ([
            'size' => 'torrents.size_range',
            'seeders' => 'torrents.seeders_range',
            'leechers' => 'torrents.leechers_range',
            'times_completed' => 'torrents.times_completed_range',
            'added' => 'torrents.added_range',
        ] as $prefix => $label) {
            $beginKey = $prefix.'_begin';
            $endKey = $prefix.'_end';
            $begin = $this->rangeValue($query[$beginKey] ?? null);
            $end = $this->rangeValue($query[$endKey] ?? null);
            if ($begin === '' && $end === '') {
                continue;
            }
            $range = $begin !== '' && $end !== '' ? $begin.' – '.$end
                : ($begin !== '' ? '≥ '.$begin : '≤ '.$end);
            $filters[] = $this->appliedFilter(rtrim((string) __($label), ': ').': '.$range, $query, [$beginKey, $endKey]);
        }
    }

    private function rangeValue(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    /**
     * @param  list<array{label: string, removeUrl: string}>  $filters
     * @param  array<string, mixed>  $query
     */
    private function appendTagFilter(array &$filters, array $query): void
    {
        $tagId = $query['tag_id'] ?? null;
        if (! is_scalar($tagId) || ! is_numeric($tagId)) {
            return;
        }

        $tagName = Tag::query()->whereKey((int) $tagId)->value('name');
        if (is_string($tagName) && $tagName !== '') {
            $filters[] = $this->appliedFilter($tagName, $query, ['tag_id']);
        }
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
