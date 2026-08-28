<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Topic;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Topic
 */
class TopicResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  mixed  $request
     * @return array<int|string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'subject' => $this->subject,
            'locked' => $this->locked,
            'forumid' => $this->forumid,
            'sticky' => $this->sticky,
            'hlcolor' => $this->hlcolor,
            'views' => $this->views,
            'user' => new UserResource($this->whenLoaded('user')),
            'lastPost' => new PostResource($this->whenLoaded('lastPost')),
            'firstPost' => new PostResource($this->whenLoaded('firstPost')),
        ];
    }
}
