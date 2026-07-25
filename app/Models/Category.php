<?php

namespace App\Models;


use App\Models\Traits\NexusActivityLogTrait;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $mode
 * @property string $name
 * @property string|null $class_name
 * @property string|null $image
 * @property-read Icon|null $icon
 * @property-read SearchBox|null $search_box
 */
class Category extends NexusModel
{
    use NexusActivityLogTrait;

    protected $table = 'categories';

    protected $fillable = ['mode', 'name', 'class_name', 'image', 'sort_index', 'icon_id'];

    public static function getLabelName()
    {
        return nexus_trans('searchbox.category_label');
    }

    /**
     * @return BelongsTo<Icon, $this>
     */
    public function icon(): BelongsTo
    {
        return $this->belongsTo(Icon::class, 'icon_id');
    }

    /**
     * @return BelongsTo<SearchBox, $this>
     */
    public function search_box(): BelongsTo
    {
        return $this->belongsTo(SearchBox::class, 'mode', 'id');
    }
}
