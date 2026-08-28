<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Post;
use App\Support\Time;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Post
 */
class PostResource extends JsonResource
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
            'added' => Time::formatDateTime($this->added),
            'body' => $this->body,
            'user' => new UserResource($this->whenLoaded('user')),
        ];
    }
}
