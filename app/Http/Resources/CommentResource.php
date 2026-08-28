<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Comment;
use App\Support\Attachment;
use App\Support\Time;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Comment
 */
class CommentResource extends JsonResource
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
            'text' => Attachment::bbcodeToImg((string) $this->text),
            'updated_at' => Time::formatDateTime($this->editdate),
            'created_at' => Time::formatDateTime($this->added),
            'create_user' => new UserResource($this->whenLoaded('create_user')),
            'update_user' => $this->when($this->editedby > 0, new UserResource($this->whenLoaded('update_user'))),
        ];
    }
}
