<?php

declare(strict_types=1);

namespace App\ViewModels\Search;

use App\Contracts\Repositories\SearchBoxRepositoryInterface;
use App\Models\SearchBox;
use App\Support\RequestValues;
use App\Support\Locale;
use App\Support\Path;

/**
 * Builds {@see SearchCategoryTableViewModel} — the typed counterpart of
 * the removed `SearchBox::buildCategoryTable()`. Same queries, same
 * checked-state resolution (`checkedValues` query-string bracket keys or
 * the user's notifs string), same select-all sentinel cells; the markup
 * now lives in the `x-search-category-table` component.
 */
final class SearchCategoryTableFactory
{
    public function __construct(
        private readonly SearchBoxRepositoryInterface $searchBoxRep,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     */
    public function create(
        int|string $mode,
        int|string $checkboxValue,
        string $categoryHrefPrefix,
        string $taxonomyHrefPrefix,
        int|string $taxonomyNameLength,
        ?string $checkedValues = '',
        array $options = [],
    ): SearchCategoryTableViewModel {
        $mode = (int) $mode;
        // Legacy cast: buildCategoryTable rendered `(int) $checkboxValue`
        // ('yes' → value="0"); the form handlers only test isset(name).
        $checkboxValue = (string) (int) $checkboxValue;
        $taxonomyNameLength = (int) $taxonomyNameLength;
        $checkedValues = (string) $checkedValues;

        parse_str($checkedValues, $checkedValuesArr);
        $searchBox = $this->searchBoxRep->findForCategoryTable($mode);
        $lang = Locale::folderFromCookie(RequestValues::cookieValue('c_lang_folder'));

        $withTaxonomies = [];
        if ($searchBox->showsubcat) {
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
        }

        $groups = [];

        $categoryCollection = $this->searchBoxRep->getCategoriesForTable($searchBox, ! empty($options['select_unselect']));
        $categoryRows = [];
        foreach ($categoryCollection->chunk($searchBox->catsperrow) as $chunk) {
            $cells = [];
            foreach ($chunk as $item) {
                if ($item->mode != -1) {
                    $checked = false;
                    if ($checkedValues !== '') {
                        $checked = str_contains($checkedValues, "[cat{$item->id}]")
                            || (isset($checkedValuesArr["cat{$item->id}"]) && $checkedValuesArr["cat{$item->id}"] == 1)
                            || (isset($checkedValuesArr['cat']) && $checkedValuesArr['cat'] == $item->id);
                    } elseif (! empty($options['user_notifs'])) {
                        $checked = str_contains($options['user_notifs'], sprintf('[%s%s]', 'cat', $item->id));
                    }

                    $icon = $item->icon;
                    if ($icon && (string) ($icon->cssfile ?? '') !== '') {
                        // Sprite icon sets draw through the c_* CSS class on
                        // a transparent pixel — pointing <img> at the sheet
                        // itself shows the whole sprite scaled into the cell.
                        $iconImagePath = 'pic/cattrans.gif';
                    } elseif ($icon) {
                        $iconFolder = trim($icon->folder, '/');
                        $langAndFile = sprintf('%s%s', $icon->multilang ? "$lang/" : '', $item->image);
                        $fullDir = Path::resolve("pic/category/$iconFolder/$langAndFile", public_path());
                        $iconImagePath = file_exists($fullDir)
                            ? "pic/category/$iconFolder/$langAndFile"
                            : "pic/category/{$searchBox->name}/$iconFolder/$langAndFile";
                    } else {
                        $iconImagePath = 'pic/cattrans.gif';
                    }

                    $cells[] = SearchCategoryCell::category(
                        $item->id,
                        $checkboxValue,
                        $checked,
                        $iconImagePath,
                        (string) $item->class_name,
                        (string) $item->name,
                        $categoryHrefPrefix.'cat='.$item->id,
                    );
                } else {
                    $cells[] = SearchCategoryCell::selectAll('cat');
                }
            }
            $categoryRows[] = $cells;
        }
        $groups[] = new SearchCategoryTableGroup(
            label: (string) Locale::trans('label.search_box.category', [], null),
            rows: $categoryRows,
        );

        foreach ($withTaxonomies as $torrentField => $tableName) {
            $namePrefix = $taxonomyNameLength > 0 ? substr($torrentField, 0, $taxonomyNameLength) : $torrentField;
            $taxonomyCollection = $this->searchBoxRep->getTaxonomyRows($tableName, $mode);

            if (! empty($options['select_unselect'])) {
                $selectAll = new \stdClass;
                $selectAll->mode = -1;
                $taxonomyCollection->push($selectAll);
            }

            $taxonomyRows = [];
            foreach ($taxonomyCollection->chunk($searchBox->catsperrow) as $chunk) {
                $cells = [];
                foreach ($chunk as $item) {
                    if ($item->mode != -1) {
                        $checked = false;
                        if ($checkedValues !== '') {
                            $checked = str_contains($checkedValues, "[{$namePrefix}{$item->id}]")
                                || (isset($checkedValuesArr["{$namePrefix}{$item->id}"]) && $checkedValuesArr["{$namePrefix}{$item->id}"] == 1)
                                || (isset($checkedValuesArr[$namePrefix]) && $checkedValuesArr[$namePrefix] == $item->id);
                        } elseif (! empty($options['user_notifs'])) {
                            $checked = str_contains($options['user_notifs'], sprintf('[%s%s]', substr($torrentField, 0, 3), $item->id));
                        }

                        $cells[] = SearchCategoryCell::taxonomy(
                            $namePrefix.$item->id,
                            $checkboxValue,
                            $checked,
                            (string) $item->name,
                            $taxonomyHrefPrefix !== '' ? $taxonomyHrefPrefix.$namePrefix.'='.$item->id : null,
                        );
                    } else {
                        $cells[] = SearchCategoryCell::selectAll($torrentField);
                    }
                }
                $taxonomyRows[] = $cells;
            }
            $groups[] = new SearchCategoryTableGroup(
                label: (string) $searchBox->getTaxonomyLabel($torrentField),
                rows: $taxonomyRows,
            );
        }

        return new SearchCategoryTableViewModel(
            sectionName: ! empty($options['section_name']) ? (($searchBox->section_name[$lang] ?? '') ?: null) : null,
            groups: $groups,
        );
    }
}
