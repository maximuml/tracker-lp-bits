<?php

namespace App\Http\Resources;

use App\Models\ExamUser;
use App\Support\Time;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ExamUser
 */
class ExamUserResource extends JsonResource
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
        return [
            'id' => $this->id,
            'status' => $this->status,
            'status_text' => $this->statusText,
            'created_at' => Time::formatDateTime($this->created_at),
            'progress' => $this->when(! empty($this->progress), $this->progress),
            'progress_formatted' => $this->when(! empty($this->progress_formatted), $this->progress_formatted),
            'begin' => Time::formatDateTime($this->begin),
            'end' => Time::formatDateTime($this->end),
            'uid' => $this->uid,
            'exam_id' => $this->exam_id,
            'is_done' => $this->is_done,
            'is_done_text' => $this->is_done_text,
            'user' => new UserResource($this->whenLoaded('user')),
            'exam' => new ExamResource($this->whenLoaded('exam')),
        ];
    }
}
