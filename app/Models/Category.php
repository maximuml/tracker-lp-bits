<?php

/**
 * @property int $id
 * @property int $mode
 * @property string|null $class_name
 * @property string $name
 * @property string|null $image
 * @property int $sort_index
 * @property int $icon_id
 */
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

    /** @var  string */
    protected $table = 'categories';

    /** @var  list<string> */
    protected $fillable = ['mode', 'name', 'class_name', 'image', 'sort_index', 'icon_id'];

    /** @return  mixed */
    public static function getLabelName()
    {
        return \App\Support\Locale::trans('searchbox.category_label', [], null);
    }

    /** @return  \Illuminate\Database\Eloquent\Relations\BelongsTo<Icon, $this> */
    public function icon(): BelongsTo
    {
        return $this->belongsTo(Icon::class, 'icon_id');
    }

    /** @return  \Illuminate\Database\Eloquent\Relations\BelongsTo<SearchBox, $this> */
    public function search_box(): BelongsTo
    {
        return $this->belongsTo(SearchBox::class, 'mode', 'id');
    }
}
