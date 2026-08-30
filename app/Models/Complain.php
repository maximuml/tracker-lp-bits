<?php

declare(strict_types=1);

/**
 * @property int $id
 * @property string $uuid
 * @property string $email
 * @property string $body
 * @property string $added
 * @property int $answered
 * @property string|null $ip
 */

namespace App\Models;

class Complain extends NexusModel
{
    /** @var list<string> */
    protected $fillable = ['id', 'uuid', 'email', 'body', 'added', 'answered', 'ip'];
}
