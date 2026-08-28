<?php

declare(strict_types=1);

/**
 * @property int $id
 * @property string $name
 * @property int $sort_index
 * @property int $mode
 */

namespace App\Models;

use App\Models\Traits\NexusActivityLogTrait;
use App\Support\Locale;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Source extends NexusModel
{
    use NexusActivityLogTrait;

    /** @var list<string> */
    protected $fillable = ['name', 'sort_index', 'mode'];

    /** @return  mixed */
    public static function getLabelName()
    {
        return Locale::trans('searchbox.sub_category_source_label', [], null);
    }

    /** @return  BelongsTo<SearchBox, $this> */
    public function search_box()
    {
        return $this->belongsTo(SearchBox::class, 'mode', 'id');
    }
}
