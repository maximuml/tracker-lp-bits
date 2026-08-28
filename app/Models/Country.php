<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string $flagpic
 */
class Country extends Model
{
    protected $table = 'countries';

    public $timestamps = false;

    protected $fillable = ['name', 'flagpic'];
}
