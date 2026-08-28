<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\TorrentOperationLog;
use App\Support\Time;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TorrentOperationLog
 */
class TorrentOperationLogResource extends JsonResource
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
            'action_type' => $this->action_type,
            'action_type_text' => $this->actionTypeText,
            'uid' => $this->uid,
            'username' => $this->user->username,
            'comment' => $this->comment,
            'created_at' => Time::formatDateTime($this->created_at),
        ];
    }
}
