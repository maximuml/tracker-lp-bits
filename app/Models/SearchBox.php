<?php

/**
 * @property int $id
 * @property string|null $name
 * @property array|null $section_name
 * @property int $showsubcat
 * @property int $showsource
 * @property int $showmedium
 * @property int $showcodec
 * @property int $showstandard
 * @property int $showprocessing
 * @property int $showaudiocodec
 * @property int $catsperrow
 * @property int $catpadding
 * @property string|null $custom_fields
 * @property string|null $custom_fields_display_name
 * @property string|null $custom_fields_display
 * @property array|null $extra
 */

namespace App\Models;

use App\Auth\Permission;
use App\Http\Middleware\Locale;
use App\Models\Traits\NexusActivityLogTrait;
use App\Repositories\TagRepository;
use App\Support\Config\SiteConfig;
use App\Support\Logger;
use App\Support\SupportContext;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Nexus\Database\NexusDB;

/**
 * @property int $id
 * @property string|null $name
 * @property int $catsperrow
 * @property bool $showsubcat
 * @property bool $showsource
 * @property bool $showmedium
 * @property bool $showcodec
 * @property bool $showstandard
 * @property bool $showprocessing
 * @property bool $showaudiocodec
 */
class SearchBox extends NexusModel
{
    use NexusActivityLogTrait;

    /** @var array<int|string, mixed> */
    private static array $instances = [];

    /** @var array<int|string, mixed> */
    private static array $modeOptions = [];

    /** @var string */
    protected $table = 'searchbox';

    /** @var list<string> */
    protected $fillable = [
        'name', 'catsperrow', 'catpadding', 'showsubcat', 'section_name', 'is_default',
        'showsource', 'showmedium', 'showcodec', 'showstandard', 'showprocessing', 'showaudiocodec',
        'custom_fields', 'custom_fields_display_name', 'custom_fields_display',
        'extra->'.self::EXTRA_TAXONOMY_LABELS,
        'extra->'.self::EXTRA_DISPLAY_COVER_ON_TORRENT_LIST,
        'extra->'.self::EXTRA_DISPLAY_SEED_BOX_ICON_ON_TORRENT_LIST,
    ];

    /** @var array<string, string> */
    protected $casts = [
        'extra' => 'array',
        'is_default' => 'boolean',
        'showsubcat' => 'boolean',
        'section_name' => 'json',
    ];

    const SEARCH_MODE_AND = '0';

    const SEARCH_MODE_EXACT = '2';

    /** @var array<int|string, mixed> */
    public static array $searchModes = [
        self::SEARCH_MODE_AND => ['text' => 'and'],
        self::SEARCH_MODE_EXACT => ['text' => 'exact'],
    ];

    const EXTRA_TAXONOMY_LABELS = 'taxonomy_labels';

    const SECTION_BROWSE = 'browse';

    const SECTION_SPECIAL = 'special';

    /** @var array<int|string, mixed> */
    public static array $sections = [
        self::SECTION_BROWSE => ['text' => 'Browse'],
        self::SECTION_SPECIAL => ['text' => 'Special'],
    ];

    const EXTRA_DISPLAY_COVER_ON_TORRENT_LIST = 'display_cover_on_torrent_list';

    const EXTRA_DISPLAY_SEED_BOX_ICON_ON_TORRENT_LIST = 'display_seed_box_icon_on_torrent_list';

    /** @var array<string, array<string, string>> */
    public static array $taxonomies = [
        'source' => ['table' => 'sources', 'model' => Source::class],
        'medium' => ['table' => 'media', 'model' => Media::class],
        'codec' => ['table' => 'codecs', 'model' => Codec::class],
        'audiocodec' => ['table' => 'audiocodecs', 'model' => AudioCodec::class],
        'standard' => ['table' => 'standards', 'model' => Standard::class],
        'processing' => ['table' => 'processings', 'model' => Processing::class],
    ];

    /** @var array<string, array<string, string>> */
    public static array $extras = [
        self::EXTRA_DISPLAY_COVER_ON_TORRENT_LIST => ['text' => 'Display cover on torrent list'],
        self::EXTRA_DISPLAY_SEED_BOX_ICON_ON_TORRENT_LIST => ['text' => 'Display seed box icon on torrent list'],
    ];

    /**
     * @param  mixed  $fullName
     * @return array<string, string>
     */
    public static function listExtraText($fullName = false): array
    {
        $result = [];
        foreach (self::$extras as $field => $info) {
            if ($fullName) {
                $name = "extra[$field]";
            } else {
                $name = $field;
            }
            $result[$name] = \App\Support\Locale::trans("searchbox.extras.{$field}", [], null);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function formatTaxonomyExtra(array $data): array
    {
        Logger::writeWithContext((string) ('data: '.json_encode($data)), (string) 'info', (bool) false);
        foreach (self::$taxonomies as $field => $table) {
            $data["show{$field}"] = 0;
            foreach ($data['extra'][self::EXTRA_TAXONOMY_LABELS] ?? [] as $item) {
                if ($field == $item['torrent_field']) {
                    $data["show{$field}"] = 1;
                }
            }
        }
        $data['extra->'.self::EXTRA_TAXONOMY_LABELS] = $data['extra'][self::EXTRA_TAXONOMY_LABELS];
        $other = $data['other'] ?? [];
        $data['extra->'.self::EXTRA_DISPLAY_COVER_ON_TORRENT_LIST] = in_array(self::EXTRA_DISPLAY_COVER_ON_TORRENT_LIST, $other) ? 1 : 0;
        $data['extra->'.self::EXTRA_DISPLAY_SEED_BOX_ICON_ON_TORRENT_LIST] = in_array(self::EXTRA_DISPLAY_SEED_BOX_ICON_ON_TORRENT_LIST, $other) ? 1 : 0;
        $data['custom_fields'] = array_filter($data['custom_fields']);

        return $data;
    }

    /**
     * @param  mixed  $torrentField
     * @return mixed
     */
    public function getTaxonomyLabel($torrentField)
    {
        $lang = \App\Support\Locale::folderFromCookie(SupportContext::getCookieValue('c_lang_folder', ''), (bool) false);
        foreach ($this->extra[self::EXTRA_TAXONOMY_LABELS] ?? [] as $item) {
            if ($item['torrent_field'] == $torrentField) {
                if (! empty($item['display_text'][$lang])) {
                    return $item['display_text'][$lang];
                }
            }
        }

        return \App\Support\Locale::trans("searchbox.sub_category_{$torrentField}_label", [], null) ?: ucfirst($torrentField);
    }

    /** @return  Attribute<mixed, mixed> */
    protected function customFields(): Attribute
    {
        return new Attribute(
            get: fn ($value) => is_string($value) ? explode(',', $value) : $value,
            set: fn ($value) => is_array($value) ? implode(',', $value) : $value,
        );
    }

    /** @return  array<int|string, mixed> */
    public static function getSubCatOptions(): array
    {
        return array_combine(array_keys(self::$taxonomies), array_keys(self::$taxonomies));
    }

    /**
     * @param  mixed  $field
     * @return array<int|string, mixed>
     */
    public static function listSections($field = null): array
    {
        $result = [];
        foreach (self::$sections as $key => $value) {
            $value['text'] = \App\Support\Locale::trans("searchbox.sections.{$key}", [], null);
            $value['mode'] = SiteConfig::current()->main->category((string) $key);
            if ($field !== null && isset($value[$field])) {
                $result[$key] = $value[$field];
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * @return mixed
     */
    public static function get(int $id)
    {
        if (! isset(self::$instances[$id])) {
            self::$instances[$id] = self::query()->find($id);
        }

        return self::$instances[$id];
    }

    /**
     * @param  mixed  $searchBox
     * @param  mixed  $torrentField
     * @return Collection<int, \stdClass>
     */
    public static function listTaxonomyItems($searchBox, $torrentField): Collection
    {
        if (! $searchBox instanceof self) {
            $searchBox = self::get(intval($searchBox));
        }
        if (! isset(self::$taxonomies[$torrentField])) {
            return collect();
        }
        $table = self::$taxonomies[$torrentField]['table'];

        return NexusDB::table($table)->where(function (Builder $query) use ($searchBox) {
            return $query->whereIn('mode', [$searchBox->id, 0]);
        })->orderBy('sort_index', 'desc')->orderBy('id', 'desc')->get();
    }

    /** @return  array<int|string, mixed> */
    public static function listModeOptions(): array
    {
        if (! empty(self::$modeOptions)) {
            return self::$modeOptions;
        }
        self::$modeOptions = SearchBox::query()
            ->pluck('name', 'id')
            ->toArray();

        return self::$modeOptions;
    }

    /**
     * @param  mixed  $value
     * @return array<int|string, mixed>
     */
    public function getCustomFieldsAttribute($value): array
    {
        if (! is_array($value)) {
            return explode(',', $value);
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

    /** @return  mixed */
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

    /** @return  array<int|string, mixed> */
    public static function listSearchModes(): array
    {
        $result = [];
        foreach (self::$searchModes as $key => $value) {
            $result[$key] = \App\Support\Locale::trans("search.search_modes.{$value['text']}", [], null);
        }

        return $result;
    }

    /** @return  mixed */
    public static function getBrowseMode()
    {
        return SiteConfig::current()->main->browseCat();
    }

    /** @return  mixed */
    public static function getBrowseSearchBox()
    {
        return self::query()->find(self::getBrowseMode());
    }

    public function isSectionBrowse(): bool
    {
        return $this->id == self::getBrowseMode();
    }

    /** @return  HasMany<Category, $this> */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class, 'mode');
    }

    /** @return  HasMany<Source, $this> */
    public function taxonomy_source(): HasMany
    {
        return $this->hasMany(Source::class, 'mode');
    }

    /** @return  HasMany<Media, $this> */
    public function taxonomy_medium(): HasMany
    {
        return $this->hasMany(Media::class, 'mode');
    }

    /** @return  HasMany<Standard, $this> */
    public function taxonomy_standard(): HasMany
    {
        return $this->hasMany(Standard::class, 'mode');
    }

    /** @return  HasMany<Codec, $this> */
    public function taxonomy_codec(): HasMany
    {
        return $this->hasMany(Codec::class, 'mode');
    }

    /** @return  HasMany<AudioCodec, $this> */
    public function taxonomy_audiocodec(): HasMany
    {
        return $this->hasMany(AudioCodec::class, 'mode');
    }

    /** @return  HasMany<Processing, $this> */
    public function taxonomy_processing(): HasMany
    {
        return $this->hasMany(Processing::class, 'mode');
    }

    public function loadSubCategories(): void
    {
        foreach (self::$taxonomies as $name => $info) {
            $relationName = 'taxonomy_'.$name;
            $show = 'show'.$name;
            if ($this->{$show} && isset(self::$taxonomies[$name])) {
                $modelName = self::$taxonomies[$name]['model'];
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

    /** @return  HasMany<Tag, $this> */
    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class, 'mode');
    }

    public function loadTags(): void
    {
        $allTags = TagRepository::listAll($this->getKey());
        if (! Permission::canSetTorrentSpecialTag()) {
            $specialTagIdList = Tag::listSpecial();
            $allTags = $allTags->filter(fn ($item) => ! in_array($item->id, $specialTagIdList));
        }
        $this->setRelation('tags', $allTags);
    }

    /** @return  mixed */
    public static function getDefaultSearchMode()
    {
        $meiliConf = SiteConfig::current()->meiliSearch->toArray();
        if ($meiliConf['enabled'] == 'yes') {
            return $meiliConf['default_search_mode'];
        } else {
            return self::SEARCH_MODE_AND;
        }
    }

    /** @param  mixed  $selectedValue */
    public static function listSelectModeOptions($selectedValue): string
    {
        $options = [];
        if (! is_numeric($selectedValue)) {
            // set default
            $selectedValue = self::getDefaultSearchMode();
        }
        foreach (self::listSearchModes() as $key => $text) {
            $selected = '';
            if ((string) $key === (string) $selectedValue) {
                $selected = ' selected';
            }
            $options[] = sprintf('<option value="%s"%s>%s</option>', $key, $selected, $text);
        }

        return implode('', $options);
    }

    /**
     * @param  mixed  $searchBoxId
     * @param  mixed  $glue
     * @return array<int|string, mixed>|string|null
     */
    public static function listCategoryId($searchBoxId, $glue = null): array|string|null
    {
        static $results = null;
        if (is_null($results)) {
            $results = [];
            $res = \App\Support\Category::listByModeWithContext($searchBoxId);
            foreach ($res as $item) {
                $results[] = $item['id'];
            }
        }
        if (! is_null($glue)) {
            $results = implode($glue, $results);
        }

        return $results;
    }

    /** @return  array<int|string, mixed> */
    public static function listAuthorizedSectionId(): array
    {
        return [self::getBrowseMode()];
    }

    /** @return  array<int|string, mixed> */
    public static function listAllSectionId(): array
    {
        return [self::getBrowseMode()];
    }
}
