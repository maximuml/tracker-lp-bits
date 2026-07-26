<?php

/**
 * @property int $id
 * @property string $name
 * @property string $label
 * @property string $type
 * @property int $required
 * @property int $is_single_row
 * @property string|null $options
 * @property string|null $help
 * @property string|null $display
 * @property int $priority
 * @property string $created_at
 * @property string $updated_at
 */
namespace App\Models;

use Nexus\Database\NexusDB;

class TorrentCustomField extends NexusModel
{
    protected $table = 'torrents_custom_fields';

    public $timestamps = true;

    protected $fillable = [
        'name', 'label', 'type', 'required', 'is_single_row', 'options', 'help', 'display', 'priority'
    ];

    public static function getCheckboxOptions(): array
    {
        $result = [];
        $records = self::query()->get();
        foreach ($records as $value) {
            $result[$value->id] = sprintf('%s[%s]', $value->name, $value->label);
        }
        return $result;
    }

}
