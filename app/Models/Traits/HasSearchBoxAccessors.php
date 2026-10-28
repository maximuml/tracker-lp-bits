<?php

declare(strict_types=1);

namespace App\Models\Traits;

use App\Support\Locale;
use App\Support\RequestValues;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * Eloquent attribute accessors / mutators for the SearchBox model.
 */
trait HasSearchBoxAccessors
{
    /** @return Attribute<mixed, mixed> */
    protected function customFields(): Attribute
    {
        return new Attribute(
            get: fn ($value) => is_string($value) ? explode(',', $value) : $value,
            set: fn ($value) => is_array($value) ? implode(',', $value) : $value,
        );
    }

    /**
     * @param  mixed  $value
     * @return array<int|string, mixed>
     */
    public function getCustomFieldsAttribute($value): array
    {
        if (! is_array($value)) {
            return explode(',', (string) $value);
        }

        return $value;
    }

    /**
     * @param  mixed  $value
     * @return mixed
     */
    public function setCustomFieldsAttribute($value)
    {
        if (is_array($value)) {
            $this->attributes['custom_fields'] = implode(',', $value);
        }
    }

    /**
     * @param  mixed  $torrentField
     * @return mixed
     */
    public function getTaxonomyLabel($torrentField)
    {
        $lang = Locale::folderFromCookie(RequestValues::cookieValue('c_lang_folder', ''), (bool) false);
        foreach ($this->extra[self::EXTRA_TAXONOMY_LABELS] ?? [] as $item) {
            if ($item['torrent_field'] == $torrentField) {
                if (! empty($item['display_text'][$lang])) {
                    return $item['display_text'][$lang];
                }
            }
        }

        return Locale::trans("searchbox.sub_category_{$torrentField}_label", [], null) ?: ucfirst($torrentField);
    }
}
