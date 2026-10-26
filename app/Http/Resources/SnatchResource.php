<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Auth\Permission;
use App\Enums\UserPrivacy;
use App\Models\Snatch;
use App\Models\User;
use App\Support\Format;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Snatch
 */
class SnatchResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  mixed  $request
     * @return array<int|string, mixed>
     *
     * @see viewsnatches.php
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'upload_text' => $this->upload_text,
            'download_text' => $this->download_text,
            'share_ratio' => $this->share_ratio,
            'seed_time' => Format::prettyTimeWithLocale($this->seedtime),
            'leech_time' => Format::prettyTimeWithLocale($this->leechtime),
            'completed_at_human' => $this->completedat ? $this->completedat->diffForHumans() : '',
            'last_action_human' => $this->last_action ? $this->last_action->diffForHumans() : '',
            'user' => $this->revealSnatchUser($request) ? new UserResource($this->whenLoaded('user')) : null,
        ];
    }

    /**
     * @param  mixed  $request
     */
    private function revealSnatchUser($request): bool
    {
        // whenLoaded keeps the old absent-key semantics for callers that
        // never eager-load the relation — and avoids a lazy-load N+1.
        $snatchUser = $this->whenLoaded('user');
        if (! $snatchUser instanceof User) {
            return true;
        }
        if ($snatchUser->privacy !== UserPrivacy::STRONG) {
            return true;
        }
        $viewer = $request->user();

        return $viewer instanceof User
            && ((int) $snatchUser->id === (int) $viewer->id || Permission::canViewAnonymous($viewer));
    }
}
