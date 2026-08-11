<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Snatch
 */
class SnatchResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     * @param  mixed  $request
     * @return  array<int|string, mixed>
     * @see viewsnatches.php
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'upload_text' => $this->upload_text,
            'download_text' => $this->download_text,
            'share_ratio' => $this->share_ratio,
            'seed_time' => \App\Support\Format::prettyTimeWithLocale($this->seedtime),
            'leech_time' => \App\Support\Format::prettyTimeWithLocale($this->leechtime),
            'completed_at_human' => $this->completedat ? $this->completedat->diffForHumans() : '',
            'last_action_human' => $this->last_action ? $this->last_action->diffForHumans() : '',
            'user' => new UserResource($this->whenLoaded('user')),
        ];
    }
}
