<?php

declare(strict_types=1);

namespace App\Models\Traits;

use App\Auth\Permission;
use App\Contracts\Repositories\TagRepositoryInterface;
use App\Models\Category;
use App\Models\SearchBox;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Eloquent relationships for the SearchBox model.
 */
trait HasSearchBoxRelationships
{
    /** @return HasMany<Category, $this> */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class, 'mode');
    }

    public function loadSubCategories(): void
    {
        foreach (SearchBox::$taxonomies as $name => $info) {
            $relationName = 'taxonomy_'.$name;
            $show = 'show'.$name;
            if ($this->{$show} && isset(SearchBox::$taxonomies[$name])) {
                $modelName = SearchBox::$taxonomies[$name]['model'];
                $this->setRelation(
                    $relationName,
                    $modelName::query()->whereIn('mode', [$this->getKey(), 0])
                        ->orderBy('sort_index', 'desc')
                        ->orderBy('id', 'desc')
                        ->get()
                );
            }
        }
    }

    /** @return HasMany<Tag, $this> */
    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class, 'mode');
    }

    public function loadTags(): void
    {
        $allTags = app(TagRepositoryInterface::class)->listAll($this->getKey());
        if (! Permission::canSetTorrentSpecialTag()) {
            $specialTagIdList = Tag::listSpecial();
            $allTags = $allTags->filter(fn ($item) => ! in_array($item->id, $specialTagIdList));
        }
        $this->setRelation('tags', $allTags);
    }
}
