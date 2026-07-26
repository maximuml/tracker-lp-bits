<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Message
 */
class MessageResource extends JsonResource
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
            'subject' => $this->subject,
            'msg' => htmlspecialchars_decode(strip_all_tags($this->msg)),
            'added_human' => $this->added->diffForHumans(),
            'added' => format_datetime($this->added),
            'send_user' => new UserResource($this->whenLoaded('send_user')),
        ];
    }
}
