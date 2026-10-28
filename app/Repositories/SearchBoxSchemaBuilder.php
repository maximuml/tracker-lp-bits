<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\TagRepositoryInterface;
use App\Models\SearchBox;
use App\Support\Html\SafeHtml;
use App\Support\Locale;
use Filament\Forms;
use Illuminate\Database\Query\Builder;
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
    public function renderTaxonomySelect($searchBox, array $torrentInfo = []): SafeHtml
    {
        if (! $searchBox instanceof SearchBox) {
            $searchBox = SearchBox::get(intval($searchBox));
        }
        if (! $searchBox instanceof SearchBox) {
            return SafeHtml::fromPlainText('');
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

        return SafeHtml::fromTrustedHtml(implode('&nbsp;&nbsp;', $results));
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
            $list = DB::table($table)->where(function (Builder $query) use ($searchBox) {
                return $query->where('mode', $searchBox->id)->orWhere('mode', 0);
            })->orderBy('sort_index', 'desc')->get();
            $options = [[
                'value' => 0,
                'label' => Locale::trans('nexus.select_one_please', [], null),
                'selected' => false,
            ]];
            foreach ($list as $item) {
                $options[] = [
                    'value' => $item->id,
                    'label' => (string) $item->name,
                    'selected' => isset($torrentInfo[$torrentField]) && $torrentInfo[$torrentField] == $item->id,
                ];
            }

            return view('components.upload.taxonomy_select', [
                'label' => $searchBox->getTaxonomyLabel($torrentField),
                'field' => $torrentField,
                'searchBoxId' => $searchBoxId,
                'options' => $options,
            ])->render();
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
                ->options($items->pluck('name', 'id')->all())
                ->label($searchBox->getTaxonomyLabel($torrentField));
        }
    }
}
