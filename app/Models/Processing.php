<?php

/**
 * @property int $id
 * @property string $name
 * @property int $sort_index
 * @property int $mode
 */
namespace App\Models;


use App\Models\Traits\NexusActivityLogTrait;

class Processing extends NexusModel
{
    use NexusActivityLogTrait;

    /** @var  string */
    protected $table = 'processings';

    /** @var  list<string> */
    protected $fillable = ['name', 'sort_index', 'mode',];

    /** @return  mixed */
    public static function getLabelName()
    {
        return nexus_trans('searchbox.sub_category_processing_label');
    }

    /** @return  \Illuminate\Database\Eloquent\Relations\BelongsTo<SearchBox, $this> */
    public function search_box()
    {
        return $this->belongsTo(SearchBox::class, 'mode', 'id');
    }
}
