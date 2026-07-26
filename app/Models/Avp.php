<?php

namespace App\Models;

use App\Models\Traits\NexusActivityLogTrait;

/**
 * @property string|int|null $value_u
 */
class Avp extends NexusModel
{
    use NexusActivityLogTrait;
}
