<?php

namespace App\Http\Resources;

use App\Models\Poll;
use App\Support\Time;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Poll
 */
class PollResource extends JsonResource
{
    /** @var bool */
    public $preserveKeys = true;

    /**
     * Transform the resource into an array.
     *
     * @param  mixed  $request
     * @return array<int|string, mixed>
     */
    public function toArray($request)
    {
        $out = [
            'id' => $this->id,
            'added' => Time::formatDateTime($this->added),
            'question' => $this->question,
            'answers_count' => $this->answers_count,
            'options' => $this->options,
        ];

        return $out;
    }
}
