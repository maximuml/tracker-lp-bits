<?php

declare(strict_types=1);

/**
 * @property int $id
 * @property string $name
 * @property string $folder
 * @property string|null $cssfile
 * @property string $multilang
 * @property string $secondicon
 * @property string|null $designer
 * @property string|null $comment
 */

namespace App\Models;

use App\Models\Traits\NexusActivityLogTrait;

/**
 * @property int $id
 * @property string $folder
 * @property string $multilang
 */
class Icon extends NexusModel
{
    use NexusActivityLogTrait;

    /** @var string */
    protected $table = 'caticons';

    /** @var list<string> */
    protected $fillable = ['name', 'folder', 'cssfile', 'multilang', 'secondicon', 'designer', 'comment'];
}
