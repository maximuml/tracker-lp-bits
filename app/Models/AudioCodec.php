<?php

/**
 * @property int $id
 * @property string $name
 * @property string $image
 * @property int $sort_index
 * @property int $mode
 */
namespace App\Models;


use App\Models\Traits\NexusActivityLogTrait;

class AudioCodec extends NexusModel
{
    use NexusActivityLogTrait;

    /** @var  string */
    protected $table = 'audiocodecs';

    /** @var  list<string> */
    protected $fillable = ['name', 'sort_index', 'mode',];

    /** @return  mixed */
    public static function getLabelName()
    {
        return \App\Support\Locale::trans('searchbox.sub_category_audio_codec_label', [], null);
    }

    /** @return  \Illuminate\Database\Eloquent\Relations\BelongsTo<SearchBox, $this> */
    public function search_box()
    {
        return $this->belongsTo(SearchBox::class, 'mode', 'id');
    }
}
