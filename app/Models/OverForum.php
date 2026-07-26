<?php

/**
 * @property int $id
 * @property string $name
 * @property string $description
 * @property int $minclassview
 * @property int $sort
 */
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OverForum extends NexusModel
{
    protected $table = "overforums";


}
