<?php

namespace App\Models;


use App\Models\Traits\NexusActivityLogTrait;

/**
 * @property int $answers_count
 * @property array $options
 */
class Poll extends NexusModel
{
    use NexusActivityLogTrait;

    protected $fillable = ['added', 'question', 'option0', 'option1', 'option2', 'option3', 'option4', 'option5'];

    protected $casts = [
        'added' => 'datetime'
    ];

    const MAX_OPTION_INDEX = 19;

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<PollAnswer, $this>
     */
    public function answers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PollAnswer::class, 'pollid');
    }

}
