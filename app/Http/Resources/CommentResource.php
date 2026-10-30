<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Auth\Permission;
use App\Models\Comment;
use App\Models\User;
use App\Services\AttachmentRenderService;
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
        $viewer = $request->user();
        $revealAuthor = ! $this->anonymous
            || ($viewer instanceof User
                && ((int) $this->user === (int) $viewer->id || Permission::canViewAnonymous($viewer)));

        return [
            'id' => $this->id,
            'text' => app(AttachmentRenderService::class)->bbcodeToImg((string) $this->text),
            'updated_at' => Time::formatDateTime($this->editdate),
            'created_at' => Time::formatDateTime($this->added),
            'create_user' => $revealAuthor ? new UserResource($this->whenLoaded('create_user')) : null,
            'update_user' => $this->when($this->editedby > 0, new UserResource($this->whenLoaded('update_user'))),
        ];
    }
}
