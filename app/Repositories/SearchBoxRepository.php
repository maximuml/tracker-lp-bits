<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\UserClass as UserClassEnum;
use App\Exceptions\InsufficientPermissionException;
use App\Http\Middleware\Locale;
use App\Models\Category;
use App\Models\Icon;
use App\Models\SearchBox;
use App\Models\Torrent;
use App\Support\Cache;
use App\Support\Input;
use App\Support\UserDisplay;
use Filament\Forms;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SearchBoxRepository extends BaseRepository
{
    /** @return list<string> */
    protected function allowedSortColumns(): array
    {
        return ['id', 'name', 'sort'];
    }

    /**
     * Fetch all search-box rows, decoding JSON columns.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAllRows(): array
    {
        $rows = [];
        foreach (DB::table('searchbox')->orderBy('id')->get() as $row) {
            $row = (array) $row;
            if (isset($row['extra'])) {
                $row['extra'] = json_decode($row['extra'], true);
            }
            if (isset($row['section_name'])) {
                $row['section_name'] = json_decode($row['section_name'], true);
            }
            $rows[(int) $row['id']] = $row;
        }

        return $rows;
    }

    /**
     * Fetch taxonomy rows for a search-box mode.
     *
     * @return Collection<int, \stdClass>
     */
    public function getTaxonomyRows(string $tableName, int $mode)
    {
        return DB::table($tableName)
            ->where(function (Builder $query) use ($mode) {
                return $query->whereIn('mode', [$mode, 0]);
            })
            ->orderBy('sort_index', 'desc')
            ->get();
    }

    /**
     * Fetch taxonomy rows as an array for legacy item list.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTaxonomyList(string $tableName, int $mode): array
    {
        return $this->getTaxonomyRows($tableName, $mode)->map(fn ($row) => (array) $row)->all();
    }

    /**
     * @param  array<int|string, mixed>  $params
     * @return LengthAwarePaginator<int, SearchBox>
     */
    public function getList(array $params): LengthAwarePaginator
    {
        $query = SearchBox::query();
        [$sortField, $sortType] = $this->getSortFieldAndType($params);
        $query->orderBy($sortField, $sortType);

        return $query->paginate();
    }

    /**
     * @param  array<int|string, mixed>  $params
     * @return mixed
     */
    public function store(array $params)
    {
        /** @var array<string, mixed> $data */
        $data = $params;
        $result = SearchBox::query()->create($data);

        return $result;
    }

    /**
     * @param  array<int|string, mixed>  $params
     * @return mixed
     */
    public function update(array $params, int $id)
    {
        $result = SearchBox::query()->findOrFail($id);
        /** @var array<string, mixed> $data */
        $data = $params;
        $result->update($data);

        return $result;
    }

    /**
     * @return mixed
     */
    public function getDetail(int $id)
    {
        $result = SearchBox::query()->findOrFail($id);

        return $result;
    }

    /**
     * @return mixed
     */
    public function delete(int $id)
    {
        $result = SearchBox::query()->findOrFail($id);
        $success = $result->delete();

        return $success;
    }

    /**
     * @param  array<int|string, mixed>  $idArr
     * @return mixed
     */
    public function listIcon(array $idArr)
    {
        $searchBoxList = SearchBox::query()->with('categories')->find($idArr);
        if ($searchBoxList->isEmpty()) {
            return $searchBoxList;
        }
        $iconIdArr = [];
        foreach ($searchBoxList as $value) {
            foreach ($value->categories as $category) {
                $iconId = $category->icon_id;
                if (! isset($iconIdArr[$iconId])) {
                    $iconIdArr[$iconId] = $iconId;
                }
            }
        }

        return Icon::query()->find(array_keys($iconIdArr));
    }

    /** @return  mixed */
    public function migrateToModeRelated()
    {
        $searchBoxList = SearchBox::query()->get();
        foreach ($searchBoxList as $searchBox) {
            $taxonomies = [];
            foreach (SearchBox::$taxonomies as $torrentField => $taxonomyTableModel) {
                $searchBoxField = 'show'.$torrentField;
                if ($searchBox->showsubcat && $searchBox->{$searchBoxField}) {
                    $taxonomies[] = [
                        'torrent_field' => $torrentField,
                        'display_text' => [
                            'en' => \App\Support\Locale::trans("searchbox.sub_category_{$torrentField}_label", [], Locale::$languageMaps['en']),
                        ],
                    ];
                }
            }
            if (! empty($taxonomies)) {
                $searchBox->update(['extra->'.SearchBox::EXTRA_TAXONOMY_LABELS => $taxonomies]);
            }
            Cache::clearSearchBox();
        }
    }

    /**
     * @param  mixed  $searchBox
     * @param  array<int|string, mixed>  $torrentInfo
     */
    public function renderTaxonomySelect($searchBox, array $torrentInfo = []): string
    {
        if (! $searchBox instanceof SearchBox) {
            $searchBox = SearchBox::get(intval($searchBox));
        }
        if (! $searchBox instanceof SearchBox) {
            return '';
        }
        $results = [];
        // Keep the order
        if (! empty($searchBox->extra[SearchBox::EXTRA_TAXONOMY_LABELS])) {
            foreach ($searchBox->extra[SearchBox::EXTRA_TAXONOMY_LABELS] as $taxonomy) {
                $select = $this->buildTaxonomySelect($searchBox, $taxonomy['torrent_field'], $torrentInfo);
                if ($select) {
                    $results[] = $select;
                }
            }
        } else {
            foreach (SearchBox::$taxonomies as $torrentField => $taxonomyTableModel) {
                $select = $this->buildTaxonomySelect($searchBox, $torrentField, $torrentInfo);
                if ($select) {
                    $results[] = $select;
                }
            }
        }

        return implode('&nbsp;&nbsp;', $results);
    }

    /**
     * @param  mixed  $searchBox
     * @param  array<int|string, mixed>  $torrentWithTaxonomy
     * @return array<int|string, mixed>
     */
    public function listTaxonomyInfo($searchBox, array $torrentWithTaxonomy): array
    {
        if (! $searchBox instanceof SearchBox) {
            $searchBox = SearchBox::get(intval($searchBox));
        }
        if (! $searchBox instanceof SearchBox) {
            return [];
        }
        $results = [];
        // Keep the order
        if (! empty($searchBox->extra[SearchBox::EXTRA_TAXONOMY_LABELS])) {
            foreach ($searchBox->extra[SearchBox::EXTRA_TAXONOMY_LABELS] as $item) {
                $taxonomy = $this->getTaxonomyInfo($searchBox, $torrentWithTaxonomy, $item['torrent_field']);
                if ($taxonomy) {
                    $results[] = $taxonomy;
                }
            }
        } else {
            foreach (SearchBox::$taxonomies as $torrentField => $taxonomyTableModel) {
                $taxonomy = $this->getTaxonomyInfo($searchBox, $torrentWithTaxonomy, $torrentField);
                if ($taxonomy) {
                    $results[] = $taxonomy;
                }
            }
        }

        return $results;
    }

    /**
     * @param  array<int|string, mixed>  $torrentWithTaxonomy
     * @param  mixed  $torrentField
     * @return array<int|string, mixed>|null
     */
    private function getTaxonomyInfo(SearchBox $searchBox, array $torrentWithTaxonomy, $torrentField)
    {
        if (! isset(SearchBox::$taxonomies[$torrentField])) {
            return null;
        }
        $searchBoxField = 'show'.$torrentField;
        $torrentTaxonomyField = $torrentField.'_name';
        if ($searchBox->showsubcat && $searchBox->{$searchBoxField} && ! empty($torrentWithTaxonomy[$torrentTaxonomyField])) {
            return [
                'field' => $torrentField,
                'label' => $searchBox->getTaxonomyLabel($torrentField),
                'value' => $torrentWithTaxonomy[$torrentTaxonomyField],
            ];
        }

        return null;
    }

    /**
     * @param  mixed  $torrentField
     * @param  array<int|string, mixed>  $torrentInfo
     * @return mixed
     */
    private function buildTaxonomySelect(SearchBox $searchBox, $torrentField, array $torrentInfo)
    {
        if (! isset(SearchBox::$taxonomies[$torrentField])) {
            return '';
        }
        $searchBoxId = $searchBox->id;
        $searchBoxField = 'show'.$torrentField;
        if ($searchBox->showsubcat && $searchBox->{$searchBoxField}) {
            $table = SearchBox::$taxonomies[$torrentField]['table'];
            $select = sprintf('<b>%s: </b>', $searchBox->getTaxonomyLabel($torrentField));
            $select .= sprintf('<select name="%s_sel[%s]" data-mode="%s_%s">', $torrentField, $searchBoxId, $torrentField, $searchBoxId);
            $select .= sprintf('<option value="%s">%s</option>', 0, \App\Support\Locale::trans('nexus.select_one_please', [], null));
            $list = DB::table($table)->where(function (Builder $query) use ($searchBox) {
                return $query->where('mode', $searchBox->id)->orWhere('mode', 0);
            })->orderBy('sort_index', 'desc')->get();
            foreach ($list as $item) {
                $selected = '';
                if (isset($torrentInfo[$torrentField]) && $torrentInfo[$torrentField] == $item->id) {
                    $selected = ' selected';
                }
                $select .= sprintf('<option value="%s"%s>%s</option>', $item->id, $selected, $item->name);
            }
            $select .= '</select>';

            return $select;
        }
    }

    /**
     * @param  mixed  $searchBox
     * @return array<int|string, mixed>
     */
    public function listTaxonomyFormSchema($searchBox): array
    {
        if (! $searchBox instanceof SearchBox) {
            $searchBox = SearchBox::get(intval($searchBox));
        }
        if (! $searchBox instanceof SearchBox) {
            return [];
        }
        $results = [];
        // Keep the order
        if (! empty($searchBox->extra[SearchBox::EXTRA_TAXONOMY_LABELS])) {
            foreach ($searchBox->extra[SearchBox::EXTRA_TAXONOMY_LABELS] as $taxonomy) {
                $select = $this->buildTaxonomyFormSchema($searchBox, $taxonomy['torrent_field']);
                if ($select) {
                    $results[] = $select;
                }
            }
        } else {
            foreach (SearchBox::$taxonomies as $torrentField => $taxonomyTableModel) {
                $select = $this->buildTaxonomyFormSchema($searchBox, $torrentField);
                if ($select) {
                    $results[] = $select;
                }
            }
        }

        return $results;
    }

    /**
     * @param  mixed  $torrentField
     * @return mixed
     */
    private function buildTaxonomyFormSchema(SearchBox $searchBox, $torrentField)
    {
        if (! isset(SearchBox::$taxonomies[$torrentField])) {
            return null;
        }
        $searchBoxId = $searchBox->id;
        $searchBoxField = 'show'.$torrentField;
        $name = sprintf('%s.%s', $torrentField, $searchBoxId);
        if ($searchBox->showsubcat && $searchBox->{$searchBoxField}) {
            $items = SearchBox::listTaxonomyItems($searchBox, $torrentField);

            return Forms\Components\Select::make($name)
                ->options($items->pluck('name', 'id')->toArray())
                ->label($searchBox->getTaxonomyLabel($torrentField));
        }
    }

    /**
     * @param  mixed  $id
     * @return mixed
     */
    public function deleteCategory($id)
    {
        if (UserDisplay::currentClass() < UserClassEnum::SYSOP->value) {
            throw new InsufficientPermissionException;
        }
        $idArr = Arr::wrap($id);
        $exists = Torrent::query()->whereHas('basic_category', function (\Illuminate\Database\Eloquent\Builder $query) use ($idArr) {
            return $query->whereIn('id', $idArr);
        })->exists();
        if ($exists) {
            throw new \RuntimeException('There are torrents that belong to this category and cannot be deleted!');
        }

        return Category::query()->whereIn('id', $idArr)->delete();
    }

    /**
     * @param  array<int>|int  $id
     * @param  bool  $withCategoryAndTags
     * @return \Illuminate\Database\Eloquent\Collection<int, SearchBox>
     */
    public function listSections($id, $withCategoryAndTags = true)
    {
        $searchBoxList = SearchBox::query()->with($withCategoryAndTags ? ['categories'] : [])->whereIn('id', Arr::wrap($id))->get();
        if ($withCategoryAndTags) {
            foreach ($searchBoxList as $searchBox) {
                if ($searchBox->showsubcat) {
                    $searchBox->loadSubCategories();
                }
                $searchBox->loadTags();
            }
        }

        return $searchBoxList;
    }

    public function buildSearchBoxFormSchema(SearchBox $searchBox, string $namePrefix): Section
    {
        $lang = \App\Support\Locale::folderFromCookie(Input::cookieValue('c_lang_folder', ''), (bool) false);
        $heading = $searchBox->section_name[$lang] ?? \App\Support\Locale::trans('searchbox.sections.browse', [], null);

        return Section::make($heading)
            ->schema($this->buildCategoryTaxonomyTagSchema($searchBox, false, $namePrefix));
    }

    /**
     * @return array<int|string, mixed>
     */
    public function buildCategoryTaxonomyTagSchema(SearchBox $searchBox, bool $multiple, string $namePrefix): array
    {
        $schema = [];
        $mode = $searchBox->id;
        $namePrefix .= ".section.$mode";
        if ($multiple) {
            $schema[] = Forms\Components\CheckboxList::make("$namePrefix.category")
                ->options($searchBox->categories()->orderBy('sort_index', 'desc')->orderBy('id')->pluck('name', 'id'))
                ->label(\App\Support\Locale::trans('label.search_box.category', [], null))
                ->columns(6);
        } else {
            $schema[] = Forms\Components\Radio::make("$namePrefix.category")
                ->options($searchBox->categories()->orderBy('sort_index', 'desc')->orderBy('id')->pluck('name', 'id'))
                ->label(\App\Support\Locale::trans('label.search_box.category', [], null))
                ->columns(6);
        }

        $fieldset = Fieldset::make(\App\Support\Locale::trans('searchbox.sub_categories_label', [], null));
        $fieldsetSchema = [];
        // Keep the order
        if (! empty($searchBox->extra[SearchBox::EXTRA_TAXONOMY_LABELS])) {
            foreach ($searchBox->extra[SearchBox::EXTRA_TAXONOMY_LABELS] as $taxonomy) {
                $torrentField = $taxonomy['torrent_field'];
                $showField = 'show'.$torrentField;
                if ($searchBox->showsubcat && $searchBox->{$showField} && isset(SearchBox::$taxonomies[$torrentField])) {
                    if ($multiple) {
                        $fieldsetSchema[] = Forms\Components\CheckboxList::make("$namePrefix.$torrentField")
                            ->options($this->listTaxonomies($torrentField, $mode))
                            ->label($searchBox->getTaxonomyLabel($torrentField))
                            ->columns(6);
                    } else {
                        $fieldsetSchema[] = Forms\Components\Radio::make("$namePrefix.$torrentField")
                            ->options($this->listTaxonomies($torrentField, $mode))
                            ->label($searchBox->getTaxonomyLabel($torrentField))
                            ->columns(6);
                    }
                }
            }
        } else {
            foreach (SearchBox::$taxonomies as $torrentField => $taxonomyTableModel) {
                $showField = 'show'.$torrentField;
                if ($searchBox->showsubcat && $searchBox->{$showField}) {
                    $fieldsetSchema[] = Forms\Components\CheckboxList::make("$namePrefix.$torrentField")
                        ->options($this->listTaxonomies($torrentField, $mode))
                        ->label($searchBox->getTaxonomyLabel($torrentField))
                        ->columns(6);
                }
            }
        }
        $fieldset->schema($fieldsetSchema)->columns(1);
        $schema[] = $fieldset;

        $tagRep = app(TagRepository::class);
        $tags = $tagRep->listAll($searchBox->id);
        $schema[] = Forms\Components\CheckboxList::make("$namePrefix.tag")
            ->options($tags->pluck('name', 'id'))
            ->label(\App\Support\Locale::trans('label.tag.label', [], null))
            ->columns(6);

        return $schema;
    }

    /**
     * @param  mixed  $torrentField
     * @param  mixed  $mode
     * @return Collection<int, mixed>
     */
    private function listTaxonomies($torrentField, $mode)
    {
        if (! isset(SearchBox::$taxonomies[$torrentField])) {
            return collect();
        }
        $tableName = SearchBox::$taxonomies[$torrentField]['table'];

        return DB::table($tableName)
            ->where(function (Builder $query) use ($mode) {
                return $query->where('mode', $mode)->orWhere('mode', 0);
            })
            ->orderBy('sort_index', 'desc')
            ->orderBy('id', 'desc')
            ->pluck('name', 'id');
    }

    /**
     * @return list<int>
     */
    public function getOrderedIds(): array
    {
        return array_values(array_map('intval', SearchBox::query()->orderBy('id')->pluck('id')->all()));
    }

    public function findForCategoryTable(int|string $mode): SearchBox
    {
        return SearchBox::query()->with(['categories', 'categories.icon'])->findOrFail((int) $mode);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Category>
     */
    public function getCategoriesForTable(SearchBox $searchBox, bool $selectUnselect = false): \Illuminate\Database\Eloquent\Collection
    {
        $categories = $searchBox->categories()->with('icon')->orderBy('sort_index', 'desc')->get();
        if ($selectUnselect) {
            $categories->push(new Category(['mode' => -1]));
        }

        return $categories;
    }
}
