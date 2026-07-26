<?php

/**
 * @property int $id
 * @property int $source
 * @property int $medium
 * @property int $codec
 * @property int $standard
 * @property int $processing
 * @property int $audiocodec
 * @property string $name
 * @property string|null $class_name
 * @property string $image
 * @property int $mode
 */
namespace App\Models;


use App\Models\Traits\NexusActivityLogTrait;

class SecondIcon extends NexusModel
{
    use NexusActivityLogTrait;

    protected $table = 'secondicons';

    protected $fillable = [
        'name', 'class_name', 'image', 'mode',
        'source', 'medium', 'codec', 'audiocodec', 'standard', 'processing'
    ];

    public static function formatFormData(array $data): array
    {
        foreach (SearchBox::$taxonomies as $torrentField => $taxonomyTableModel) {
            $mode = $data['mode'];
            if ($mode === null || empty($data[$torrentField][$mode])) {
                $data[$torrentField] = 0;
            } else {
                $data[$torrentField] = $data[$torrentField][$mode];
            }
        }
        if ($data['mode'] === null) {
            $data['mode'] = 0;
        }
        return $data;
    }

    public function search_box()
    {
        return $this->belongsTo(SearchBox::class, 'mode', 'id');
    }
}
