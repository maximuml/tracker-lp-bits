<?php

declare(strict_types=1);

namespace App\Models\Traits;

use App\Auth\Permission;
use App\Models\AudioCodec;
use App\Models\Category;
use App\Models\Codec;
use App\Models\Media;
use App\Models\Processing;
use App\Models\SearchBox;
use App\Models\Source;
use App\Models\Standard;
use App\Models\Tag;
use App\Repositories\TagRepository;
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

    /** @return HasMany<Source, $this> */
    public function taxonomy_source(): HasMany
    {
        return $this->hasMany(Source::class, 'mode');
    }

    /** @return HasMany<Media, $this> */
    public function taxonomy_medium(): HasMany
    {
        return $this->hasMany(Media::class, 'mode');
    }

    /** @return HasMany<Standard, $this> */
    public function taxonomy_standard(): HasMany
    {
        return $this->hasMany(Standard::class, 'mode');
    }

    /** @return HasMany<Codec, $this> */
    public function taxonomy_codec(): HasMany
    {
        return $this->hasMany(Codec::class, 'mode');
    }

    /** @return HasMany<AudioCodec, $this> */
    public function taxonomy_audiocodec(): HasMany
    {
        return $this->hasMany(AudioCodec::class, 'mode');
    }

    /** @return HasMany<Processing, $this> */
    public function taxonomy_processing(): HasMany
    {
        return $this->hasMany(Processing::class, 'mode');
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
        $allTags = app(TagRepository::class)->listAll($this->getKey());
        if (! Permission::canSetTorrentSpecialTag()) {
            $specialTagIdList = Tag::listSpecial();
            $allTags = $allTags->filter(fn ($item) => ! in_array($item->id, $specialTagIdList));
        }
        $this->setRelation('tags', $allTags);
    }
}
