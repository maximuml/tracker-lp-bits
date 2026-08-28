<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Reward;
use App\Support\Time;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Reward
 */
class RewardResource extends JsonResource
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
            'user_id' => $this->userid,
            'torrent_id' => $this->torrentid,
            'value' => $this->value,
            'created_at' => Time::formatDateTime($this->created_at),
            'updated_at' => Time::formatDateTime($this->updated_at),
            'user' => new UserResource($this->whenLoaded('user')),
        ];
    }
}
