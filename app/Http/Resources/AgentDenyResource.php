<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\AgentDeny
 */
class AgentDenyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     * @param  mixed  $request
     * @return  array<int|string, mixed>
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'family_id' => $this->family_id,
            'agent' => $this->agent,
            'peer_id' => $this->peer_id,
            'comment' => $this->comment,
            'name' => $this->name,
            'family' => new AgentAllowResource($this->whenLoaded('family'))
        ];
    }
}
