<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Auth\Permission;
use App\Enums\UserPrivacy;
use App\Models\Peer;
use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Peer
 */
class PeerResource extends JsonResource
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
            'connectable_text' => $this->connectableText,
            'upload_text' => $this->upload_text,
            'download_text' => $this->download_text,
            'share_ratio' => $this->share_ratio,
            'download_progress' => $this->download_progress,
            'connect_time_total' => $this->connect_time_total,
            'last_action_human' => $this->last_action_human,
            'agent_human' => $this->agent_human,
            'user' => $this->revealPeerUser($request) ? new UserResource($this->whenLoaded('user')) : null,

        ];
    }

    /**
     * @param  mixed  $request
     */
    private function revealPeerUser($request): bool
    {
        // whenLoaded keeps the old absent-key semantics for callers that
        // never eager-load the relation — and avoids a lazy-load N+1.
        $peerUser = $this->whenLoaded('user');
        if (! $peerUser instanceof User) {
            return true;
        }
        if ($peerUser->privacy !== UserPrivacy::STRONG) {
            return true;
        }
        $viewer = $request->user();

        return $viewer instanceof User
            && ((int) $peerUser->id === (int) $viewer->id || Permission::canViewAnonymous($viewer));
    }
}
