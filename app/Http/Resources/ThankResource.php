<?php

namespace App\Http\Resources;

use App\Models\Thank;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Thank
 */
class ThankResource extends JsonResource
{
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
            'torrent_id' => $this->torrentid,
            'user_id' => $this->userid,
            'user' => new UserResource($this->whenLoaded('user')),
        ];
    }
}
