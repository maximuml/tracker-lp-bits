<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Message;
use App\Support\Strings;
use App\Support\Time;
use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Message
 */
class MessageResource extends JsonResource
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
            'msg' => htmlspecialchars_decode(Strings::stripAllTags((string) $this->msg)),
            'added_human' => $this->added instanceof Carbon ? $this->added->diffForHumans() : '',
            'added' => $this->added instanceof Carbon ? Time::formatDateTime($this->added) : '',
            'send_user' => new UserResource($this->whenLoaded('send_user')),
        ];
    }
}
