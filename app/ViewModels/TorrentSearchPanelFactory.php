<?php

declare(strict_types=1);

namespace App\ViewModels;

use App\Contracts\Repositories\SearchBoxRepositoryInterface;
use App\Models\Category;
use App\Models\SearchBox;
use App\Support\Input;
use App\Support\Language;
use App\Support\Locale;
use App\Support\Logger;
use App\Support\Path;

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
        private readonly Language $language,
    ) {}

    /**
     * @param  list<string>  $hotSearches
     */
    public function create(int $mode, string $checkedValues, ?string $userNotifs, array $hotSearches): TorrentSearchPanelViewModel
    {
        $searchBox = $this->searchBoxes->findForCategoryTable($mode);
        $lang = Locale::folderFromCookie(Input::cookieValue('c_lang_folder'));

        parse_str($checkedValues, $checkedValuesArr);

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

                $iconStyle = '';
                if ($item->icon) {
                    $iconFolder = trim($item->icon->folder, '/');
                    $langAndFile = ($item->icon->multilang ? "$lang/" : '').$item->image;
                    $iconStyle = 'background-image: url('.(
                        file_exists(Path::resolve("pic/category/$iconFolder/$langAndFile", ROOT_PATH))
                            ? "pic/category/$iconFolder/$langAndFile"
                            : "pic/category/{$searchBox->name}/$iconFolder/$langAndFile"
                    ).')';
                }

                $cells[] = [
                    'selectAll' => false,
                    'checkPrefix' => 'cat',
                    'id' => (int) $item->id,
                    'name' => (string) $item->name,
                    'checked' => $checked,
                    'checkboxName' => "cat{$item->id}",
                    'href' => '?cat='.$item->id,
                    'iconClass' => (string) $item->class_name,
                    'iconStyle' => $iconStyle,
                ];
            }
            $categoryRows[] = $cells;
        }

        $taxonomySections = [];
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
                        'href' => "?{$torrentField}={$item->id}",
                        'iconClass' => '',
                        'iconStyle' => '',
                    ];
                }
                $rows[] = $cells;
            }
            $taxonomySections[] = ['label' => $searchBox->getTaxonomyLabel($torrentField), 'rows' => $rows];
        }

        $langFunctions = $this->language->functions();

        $searchModes = [];
        foreach (SearchBox::listSearchModes() as $modeValue => $modeLabel) {
            $searchModes[(string) $modeValue] = (string) $modeLabel;
        }

        return new TorrentSearchPanelViewModel(
            categoryRows: $categoryRows,
            taxonomySections: $taxonomySections,
            hotSearches: $hotSearches,
            categoryLabel: (string) Locale::trans('label.search_box.category', [], null),
            catPadding: (int) $searchBox->catpadding,
            searchModes: $searchModes,
            promotionOptions: [
                1 => (string) ($langFunctions['text_normal'] ?? ''),
                2 => (string) ($langFunctions['text_free'] ?? ''),
                3 => (string) ($langFunctions['text_two_times_up'] ?? ''),
                4 => (string) ($langFunctions['text_free_two_times_up'] ?? ''),
                5 => (string) ($langFunctions['text_half_down'] ?? ''),
                6 => (string) ($langFunctions['text_half_down_two_up'] ?? ''),
                7 => (string) ($langFunctions['text_thirty_percent_down'] ?? ''),
            ],
            selectAllLabel: (string) Locale::trans('nexus.select_all', [], null),
            unselectAllLabel: (string) Locale::trans('nexus.unselect_all', [], null),
        );
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
