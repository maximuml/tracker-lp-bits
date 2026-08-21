<?php

/**
 * @property string $arg
 * @property string $value_s
 * @property int $value_i
 * @property int $value_u
 */

namespace App\Models;

use App\Models\Traits\NexusActivityLogTrait;

/**
 * @property string|int|null $value_u
 */
class Avp extends NexusModel
{
    use NexusActivityLogTrait;
}
