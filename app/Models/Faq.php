<?php

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
    /** @var  string */
    protected $table = 'faq';
}
