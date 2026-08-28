<?php

declare(strict_types=1);

/**
 * @property int $id
 * @property string $name
 * @property string $color
 * @property int $priority
 * @property string|null $created_at
 * @property string|null $updated_at
 * @property string $padding
 * @property string $margin
 * @property string $border_radius
 * @property string $font_size
 * @property string $font_color
 * @property string|null $description
 * @property int $mode
 */

namespace App\Models;

use App\Models\Traits\NexusActivityLogTrait;
use App\Support\Config\SiteConfig;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $color
 * @property string|null $font_color
 * @property string|null $font_size
 * @property string|null $padding
 * @property string|null $margin
 * @property string|null $border_radius
 * @property int $mode
 */
class Tag extends NexusModel
{
    use NexusActivityLogTrait;

    /** @var bool */
    public $timestamps = true;

    /** @var list<string> */
    protected $fillable = [
        'id', 'name', 'color', 'priority', 'created_at', 'updated_at',
        'font_size', 'font_color', 'padding', 'margin', 'border_radius',
        'mode', 'description',
    ];

    const DEFAULTS = [
        [
            'id' => 1,
            'name' => '禁转',
            'color' => '#ff0000',
        ],
        [
            'id' => 2,
            'name' => '首发',
            'color' => '#8F77B5',
        ],
        [
            'id' => 3,
            'name' => '官方',
            'color' => '#0000ff',
        ],
        [
            'id' => 4,
            'name' => 'DIY',
            'color' => '#46d5ff',
        ],
        [
            'id' => 5,
            'name' => '国语',
            'color' => '#6a3906',
        ],
        [
            'id' => 6,
            'name' => '中字',
            'color' => '#006400',
        ],
        [
            'id' => 7,
            'name' => 'HDR',
            'color' => '#38b03f',
        ],
    ];

    /** @return  array<int|string, mixed> */
    public static function listSpecial(): array
    {
        $config = SiteConfig::current()->bonus;

        return array_filter([
            $config->officialTag(),
            $config->zeroBonusTag(),
        ]);
    }

    /** @return  BelongsToMany<Torrent, $this> */
    public function torrents(): BelongsToMany
    {
        return $this->belongsToMany(Torrent::class, 'torrent_tags', 'tag_id', 'torrent_id');
    }

    /** @return  HasMany<TorrentTag, $this> */
    public function torrent_tags(): HasMany
    {
        return $this->hasMany(TorrentTag::class, 'tag_id');
    }

    /** @return  BelongsTo<SearchBox, $this> */
    public function search_box(): BelongsTo
    {
        return $this->belongsTo(SearchBox::class, 'mode', 'id');
    }
}
