<?php

declare(strict_types=1);

namespace App\Models\Traits;

use App\Http\Middleware\Locale;
use App\Support\Config\SiteConfig;
use App\Support\Input;
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

    /** @return mixed */
    public function getDisplaySectionNameAttribute()
    {
        $locale = Locale::getDefault();
        if (! empty($this->section_name[$locale])) {
            return $this->section_name[$locale];
        }
        $defaultLang = SiteConfig::current()->main->defaultLang();
        if (! empty($this->section_name[$defaultLang])) {
            return $this->section_name[$defaultLang];
        }
        if ($this->isSectionBrowse()) {
            return \App\Support\Locale::trans('searchbox.sections.browse', [], null);
        }

        return $this->name;
    }

    /**
     * @param  mixed  $torrentField
     * @return mixed
     */
    public function getTaxonomyLabel($torrentField)
    {
        $lang = \App\Support\Locale::folderFromCookie(Input::cookieValue('c_lang_folder', ''), (bool) false);
        foreach ($this->extra[self::EXTRA_TAXONOMY_LABELS] ?? [] as $item) {
            if ($item['torrent_field'] == $torrentField) {
                if (! empty($item['display_text'][$lang])) {
                    return $item['display_text'][$lang];
                }
            }
        }

        return \App\Support\Locale::trans("searchbox.sub_category_{$torrentField}_label", [], null) ?: ucfirst($torrentField);
    }
}
