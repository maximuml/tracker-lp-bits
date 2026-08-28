<?php

declare(strict_types=1);

/**
 * @property int $id
 * @property string|null $added
 * @property string $question
 * @property string $option0
 * @property string $option1
 * @property string $option2
 * @property string $option3
 * @property string $option4
 * @property string $option5
 * @property string $option6
 * @property string $option7
 * @property string $option8
 * @property string $option9
 * @property string $option10
 * @property string $option11
 * @property string $option12
 * @property string $option13
 * @property string $option14
 * @property string $option15
 * @property string $option16
 * @property string $option17
 * @property string $option18
 * @property string $option19
 */

namespace App\Models;

use App\Models\Traits\NexusActivityLogTrait;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $answers_count
 * @property array<int, string> $options
 */
class Poll extends NexusModel
{
    use NexusActivityLogTrait;

    /** @var list<string> */
    protected $fillable = ['added', 'question', 'option0', 'option1', 'option2', 'option3', 'option4', 'option5'];

    /** @var array<string, string> */
    protected $casts = [
        'added' => 'datetime',
    ];

    const MAX_OPTION_INDEX = 19;

    /** @return  HasMany<PollAnswer, $this> */
    public function answers(): HasMany
    {
        return $this->hasMany(PollAnswer::class, 'pollid');
    }
}
