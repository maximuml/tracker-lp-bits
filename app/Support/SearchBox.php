<?php

namespace App\Support;

use Nexus\Database\NexusDB;

/**
 * Legacy searchbox helper extracted from `include/functions.php`.
 *
 * Backs `get_searchbox_value()`.
 */
final class SearchBox
{
    private static ?array $rows = null;

    /**
     * Return a value from the searchbox configuration row.
     *
     * Mirrors `get_searchbox_value($mode, $item)`.
     */
    public static function value($cache, int|string $mode, string $item): mixed
    {
        if (self::$rows === null) {
            $cached = method_exists($cache, 'get_value') ? $cache->get_value('search_box_content') : false;
            if ($cached !== false && is_array($cached)) {
                self::$rows = $cached;
            } else {
                self::$rows = [];
                $result = NexusDB::getInstance()->query('SELECT * FROM searchbox ORDER BY id ASC');
                while ($row = NexusDB::getInstance()->fetchAssoc($result)) {
                    if (isset($row['extra'])) {
                        $row['extra'] = json_decode($row['extra'], true);
                    }
                    if (isset($row['section_name'])) {
                        $row['section_name'] = json_decode($row['section_name'], true);
                    }
                    self::$rows[$row['id']] = $row;
                }
                if (method_exists($cache, 'cache_value')) {
                    $cache->cache_value('search_box_content', self::$rows, 100500);
                }
            }
        }

        return self::$rows[$mode][$item] ?? '';
    }

    /**
     * Return the rows for a search-box taxonomy table.
     *
     * Mirrors `searchbox_item_list()`.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function itemList($cache, string $table, int|string $mode): array
    {
        $mode = (int) $mode;
        $cacheKey = "{$table}_list_mode_{$mode}";

        if (method_exists($cache, 'get_value')) {
            $ret = $cache->get_value($cacheKey);
            if ($ret !== false && is_array($ret)) {
                return $ret;
            }
        }

        $sql = "SELECT * FROM {$table}";
        if ($mode > 0) {
            $sql .= " WHERE (mode = {$mode} OR mode = 0)";
        }
        $sql .= ' ORDER BY sort_index, id';
        $ret = NexusDB::select($sql);

        if (method_exists($cache, 'cache_value')) {
            $cache->cache_value($cacheKey, $ret, 3600);
        }

        return $ret;
    }

    /**
     * Build a `<select>` of search-area options.
     *
     * Mirrors `build_search_area()`.
     */
    public static function areaSelect(int|string $searchArea, array $options = []): string
    {
        $result = sprintf('<select name="search_area" style="%s">', $options['style'] ?? '');
        foreach ([0, 1, 3] as $item) {
            $result .= sprintf(
                '<option value="%s"%s>%s</option>',
                $item,
                (int) $item === (int) $searchArea ? ' selected' : '',
                nexus_trans("search.search_area_options.$item")
            );
        }
        $result .= '</select>';

        return $result;
    }

    /**
     * Build the category/taxonomy checkbox table for a search box.
     *
     * Mirrors `build_search_box_category_table()`.
     */
    public static function buildCategoryTable(
        $cache,
        int|string $mode,
        int|string $checkboxValue,
        string $categoryHrefPrefix,
        string $taxonomyHrefPrefix,
        int|string $taxonomyNameLength,
        string $checkedValues = '',
        array $options = [],
    ): string {
        $mode = (int) $mode;
        $checkboxValue = (int) $checkboxValue;
        $taxonomyNameLength = (int) $taxonomyNameLength;

        parse_str($checkedValues, $checkedValuesArr);
        $searchBox = \App\Models\SearchBox::query()->with(['categories', 'categories.icon'])->findOrFail($mode);
        $lang = Locale::folderFromCookie($_COOKIE['c_lang_folder'] ?? null);
        $withTaxonomies = [];

        if ($searchBox->showsubcat) {
            if (! empty($searchBox->extra[\App\Models\SearchBox::EXTRA_TAXONOMY_LABELS])) {
                foreach ($searchBox->extra[\App\Models\SearchBox::EXTRA_TAXONOMY_LABELS] as $taxonomyLabelInfo) {
                    $torrentField = $taxonomyLabelInfo['torrent_field'];
                    $showField = 'show' . $torrentField;
                    if ($searchBox->{$showField}) {
                        $withTaxonomies[$torrentField] = \App\Models\SearchBox::$taxonomies[$torrentField]['table'];
                    }
                }
            } else {
                foreach (\App\Models\SearchBox::$taxonomies as $torrentField => $taxonomyTableModel) {
                    $showField = 'show' . $torrentField;
                    if ($searchBox->{$showField}) {
                        $withTaxonomies[$torrentField] = $taxonomyTableModel['table'];
                    }
                }
            }
        }

        $html = '<table>';
        if (! empty($options['section_name'])) {
            $html .= sprintf('<caption><font class="big">%s</font></caption>', $searchBox->section_name[$lang] ?? '');
        }

        $html .= sprintf('<tr><td class="embedded" align="left">%s</td></tr>', nexus_trans('label.search_box.category'));

        $categoryCollection = $searchBox->categories()->with('icon')->orderBy('sort_index', 'desc')->get();
        if (! empty($options['select_unselect'])) {
            $categoryCollection->push(new \App\Models\Category(['mode' => -1]));
        }
        $categoryChunks = $categoryCollection->chunk($searchBox->catsperrow);
        $checkPrefix = 'cat';

        foreach ($categoryChunks as $chunk) {
            $html .= '<tr>';
            foreach ($chunk as $item) {
                if ($item->mode != -1) {
                    $checked = '';
                    if ($checkedValues) {
                        if (
                            str_contains($checkedValues, "[cat{$item->id}]")
                            || (isset($checkedValuesArr["cat{$item->id}"]) && $checkedValuesArr["cat{$item->id}"] == 1)
                            || (isset($checkedValuesArr['cat']) && $checkedValuesArr['cat'] == $item->id)
                        ) {
                            $checked = ' checked';
                        }
                    } elseif (! empty($options['user_notifs'])) {
                        $userNotifsKey = sprintf('[%s%s]', 'cat', $item->id);
                        if (str_contains($options['user_notifs'], $userNotifsKey)) {
                            $checked = ' checked';
                        }
                    }

                    $icon = $item->icon;
                    if ($icon) {
                        $iconFolder = trim($icon->folder, '/');
                        $langAndFile = sprintf('%s%s', $icon->multilang == 'yes' ? "$lang/" : '', $item->image);
                        $fullDir = Path::resolve("pic/category/$iconFolder/$langAndFile", ROOT_PATH);
                        if (file_exists($fullDir)) {
                            $backgroundImagePath = "pic/category/$iconFolder/$langAndFile";
                        } else {
                            $backgroundImagePath = "pic/category/{$searchBox->name}/$iconFolder/$langAndFile";
                        }
                        $styleAttr = "background-image: url({$backgroundImagePath})";
                    } else {
                        $styleAttr = '';
                    }
                    $style = $styleAttr ? " style=\"{$styleAttr}\"" : '';

                    $tdContent = <<<TDCONTENT
<input type="checkbox" id="cat{$item->id}" name="cat{$item->id}" value="{$checkboxValue}"{$checked} />
<a href="{$categoryHrefPrefix}cat={$item->id}"><img src="pic/cattrans.gif" class="{$item->class_name}" alt="{$item->name}" title="{$item->name}"{$style} /></a>
TDCONTENT;
                } else {
                    $tdContent = sprintf(
                        "<input name=\"%s_check\" value=\"%s\" class=\"btn medium\" type=\"button\" onclick=\"javascript:SetChecked('%s','%s_check','%s','%s',-1,10)\">",
                        $checkPrefix,
                        nexus_trans('nexus.select_all'),
                        $checkPrefix,
                        $checkPrefix,
                        nexus_trans('nexus.select_all'),
                        nexus_trans('nexus.unselect_all')
                    );
                }

                $td = <<<TD
<td align="left" class="bottom" style="padding-bottom: 4px;padding-left: {$searchBox->catpadding}px">
    $tdContent
</td>
TD;
                $html .= $td;
            }
            $html .= '</tr>';
        }

        foreach ($withTaxonomies as $torrentField => $tableName) {
            $namePrefix = $taxonomyNameLength > 0 ? substr($torrentField, 0, $taxonomyNameLength) : $torrentField;
            $html .= sprintf('<tr><td class="embedded" align="left">%s</td></tr>', $searchBox->getTaxonomyLabel($torrentField));

            $taxonomyCollection = NexusDB::table($tableName)
                ->where(function (\Illuminate\Database\Query\Builder $query) use ($mode) {
                    return $query->whereIn('mode', [$mode, 0]);
                })
                ->orderBy('sort_index', 'desc')
                ->get();

            $modelName = \App\Models\SearchBox::$taxonomies[$torrentField]['model'];
            $checkPrefix = $torrentField;
            if (! empty($options['select_unselect'])) {
                $taxonomyCollection->push(new $modelName(['mode' => -1]));
            }
            $taxonomyChunks = $taxonomyCollection->chunk($searchBox->catsperrow);

            foreach ($taxonomyChunks as $chunk) {
                $html .= '<tr>';
                foreach ($chunk as $item) {
                    if ($item->mode != -1) {
                        if ($taxonomyHrefPrefix) {
                            $afterInput = sprintf('<a href="%s%s=%s">%s</a>', $taxonomyHrefPrefix, $namePrefix, $item->id, $item->name);
                        } else {
                            $afterInput = $item->name;
                        }

                        $checked = '';
                        \do_log("toCheck: $checkedValues, $namePrefix - {$item->id}", 'debug');
                        if ($checkedValues) {
                            if (
                                str_contains($checkedValues, "[{$namePrefix}{$item->id}]")
                                || (isset($checkedValuesArr["{$namePrefix}{$item->id}"]) && $checkedValuesArr["{$namePrefix}{$item->id}"] == 1)
                                || (isset($checkedValuesArr[$namePrefix]) && $checkedValuesArr[$namePrefix] == $item->id)
                            ) {
                                $checked = ' checked';
                            }
                        } elseif (! empty($options['user_notifs'])) {
                            $userNotifsKey = sprintf('[%s%s]', substr($torrentField, 0, 3), $item->id);
                            if (str_contains($options['user_notifs'], $userNotifsKey)) {
                                $checked = ' checked';
                            }
                        }

                        $tdContent = <<<TDCONTENT
<label><input type="checkbox" id="{$namePrefix}{$item->id}" name="{$namePrefix}{$item->id}" value="{$checkboxValue}"{$checked} />$afterInput</label>
TDCONTENT;
                    } else {
                        $tdContent = sprintf(
                            "<input name=\"%s_check\" value=\"%s\" class=\"btn medium\" type=\"button\" onclick=\"javascript:SetChecked('%s','%s_check','%s','%s',-1,10)\">",
                            $checkPrefix,
                            nexus_trans('nexus.select_all'),
                            $checkPrefix,
                            $checkPrefix,
                            nexus_trans('nexus.select_all'),
                            nexus_trans('nexus.unselect_all')
                        );
                    }

                    $td = <<<TD
<td align="left" class="bottom" style="padding-bottom: 4px;padding-left: {$searchBox->catpadding}px">
    $tdContent
</td>
TD;
                    $html .= $td;
                }
                $html .= '</tr>';
            }
            $html .= '</tr>';
        }

        $html .= '</table>';

        return $html;
    }

    /**
     * Return the search-box IDs that must be loaded for the current script.
     *
     * Mirrors `list_require_search_box_id()`.
     *
     * @return list<int>
     */
    public static function requiredIds(): array
    {
        $setting = \get_setting('main');
        $maps = [
            'torrents' => [$setting['browsecat']],
            'special' => [$setting['specialcat']],
            'usercp' => [$setting['browsecat'], $setting['specialcat']],
            'getrss' => [$setting['browsecat'], $setting['specialcat']],
            'userdetails' => [$setting['browsecat'], $setting['specialcat']],
            'offers' => [$setting['browsecat'], $setting['specialcat']],
            'details' => [$setting['browsecat'], $setting['specialcat']],
            'search' => [$setting['browsecat'], $setting['specialcat']],
        ];
        $script = \nexus()->getScript();

        return array_map('intval', \Illuminate\Support\Arr::wrap($maps[$script] ?? []));
    }
}
