<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\TagRepositoryInterface;
use App\Models\SearchBox;
use App\Support\Input;
use App\Support\Locale;
use Filament\Forms;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Search-box taxonomy/form schema builder: legacy <select> HTML, taxonomy
 * info lists and Filament form schemas for sections/categories/tags.
 * Extracted from SearchBoxRepository to keep both classes under the
 * 400-line ratchet.
 */
class SearchBoxSchemaBuilder
{
    public function __construct(
        private readonly TagRepositoryInterface $tagRepository,
    ) {}

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
            $select .= sprintf('<option value="%s">%s</option>', 0, Locale::trans('nexus.select_one_please', [], null));
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

    public function buildSearchBoxFormSchema(SearchBox $searchBox, string $namePrefix): Section
    {
        $lang = Locale::folderFromCookie(Input::cookieValue('c_lang_folder', ''), (bool) false);
        $heading = $searchBox->section_name[$lang] ?? Locale::trans('searchbox.sections.browse', [], null);

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
                ->label(Locale::trans('label.search_box.category', [], null))
                ->columns(6);
        } else {
            $schema[] = Forms\Components\Radio::make("$namePrefix.category")
                ->options($searchBox->categories()->orderBy('sort_index', 'desc')->orderBy('id')->pluck('name', 'id'))
                ->label(Locale::trans('label.search_box.category', [], null))
                ->columns(6);
        }

        $fieldset = Fieldset::make(Locale::trans('searchbox.sub_categories_label', [], null));
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

        $tagRep = $this->tagRepository;
        $tags = $tagRep->listAll($searchBox->id);
        $schema[] = Forms\Components\CheckboxList::make("$namePrefix.tag")
            ->options($tags->pluck('name', 'id'))
            ->label(Locale::trans('label.tag.label', [], null))
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
}
