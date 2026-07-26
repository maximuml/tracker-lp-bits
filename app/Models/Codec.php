<?php

/**
 * @property int $id
 * @property string $name
 * @property int $sort_index
 * @property int $mode
 */
namespace App\Models;


use App\Models\Traits\NexusActivityLogTrait;

class Codec extends NexusModel
{
    use NexusActivityLogTrait;

    protected $table = 'codecs';

    protected $fillable = ['name', 'sort_index', 'mode',];

    public static function getLabelName()
    {
        return nexus_trans('searchbox.sub_category_codec_label');
    }

    public function search_box()
    {
        return $this->belongsTo(SearchBox::class, 'mode', 'id');
    }
}
