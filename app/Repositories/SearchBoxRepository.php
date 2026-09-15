<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\SearchBoxRepositoryInterface;
use App\Enums\UserClass as UserClassEnum;
use App\Exceptions\InsufficientPermissionException;
use App\Http\Middleware\Locale;
use App\Models\Category;
use App\Models\Icon;
use App\Models\SearchBox;
use App\Models\Torrent;
use App\Support\Cache;
use App\Support\UserDisplay;
use Filament\Schemas\Components\Section;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SearchBoxRepository extends BaseRepository implements SearchBoxRepositoryInterface
{
    public function __construct(
        private readonly SearchBoxSchemaBuilder $schemaBuilder = new SearchBoxSchemaBuilder,
    ) {}

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

    /**
     * @param  mixed  $searchBox
     * @param  array<int|string, mixed>  $torrentInfo
     */
    public function renderTaxonomySelect($searchBox, array $torrentInfo = []): string
    {
        return $this->schemaBuilder->renderTaxonomySelect($searchBox, $torrentInfo);
    }

    /**
     * @param  mixed  $searchBox
     * @param  array<int|string, mixed>  $torrentWithTaxonomy
     * @return array<int|string, mixed>
     */
    public function listTaxonomyInfo($searchBox, array $torrentWithTaxonomy): array
    {
        return $this->schemaBuilder->listTaxonomyInfo($searchBox, $torrentWithTaxonomy);
    }

    /**
     * @param  mixed  $searchBox
     * @return array<int|string, mixed>
     */
    public function listTaxonomyFormSchema($searchBox): array
    {
        return $this->schemaBuilder->listTaxonomyFormSchema($searchBox);
    }

    public function buildSearchBoxFormSchema(SearchBox $searchBox, string $namePrefix): Section
    {
        return $this->schemaBuilder->buildSearchBoxFormSchema($searchBox, $namePrefix);
    }

    /**
     * @return array<int|string, mixed>
     */
    public function buildCategoryTaxonomyTagSchema(SearchBox $searchBox, bool $multiple, string $namePrefix): array
    {
        return $this->schemaBuilder->buildCategoryTaxonomyTagSchema($searchBox, $multiple, $namePrefix);
    }
}
