<?php

declare(strict_types=1);

/**
 * @property int $id
 * @property int $link_id
 * @property int $lang_id
 * @property string $type
 * @property string $question
 * @property string $answer
 * @property int $flag
 * @property int $categ
 * @property int $order
 */

namespace App\Models;

class Faq extends NexusModel
{
    /** @var string */
    protected $table = 'faq';

    /** @var list<string> */
    protected $fillable = ['id', 'link_id', 'lang_id', 'type', 'question', 'answer', 'flag', 'categ', 'order'];

    /** @var array<string, string> */
    protected $casts = [
        'link_id' => 'integer',
        'lang_id' => 'integer',
        'type' => 'integer',
        'flag' => 'integer',
        'categ' => 'integer',
        'order' => 'integer',
    ];
}
