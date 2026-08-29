<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\HitAndRun;
use App\Support\RequestContext;
use App\Support\Time;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin HitAndRun
 */
class HitAndRunResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  mixed  $request
     * @return array<int|string, mixed>
     */
    public function toArray($request): array
    {
        $out = [
            'id' => $this->id,
            'uid' => $this->uid,
            'user' => new UserResource($this->whenLoaded('user')),
            'torrent_id' => $this->torrent_id,
            'torrent' => new TorrentResource($this->whenLoaded('torrent')),
            'snatched_id' => $this->snatched_id,
            'snatch' => new SnatchResource($this->whenLoaded('snatch')),
            'status' => $this->status,
            'status_text' => $this->status_text,
            'comment' => $this->comment,
            'created_at' => Time::formatDateTime($this->created_at),
            'updated_at' => Time::formatDateTime($this->updated_at),
            'seed_time_required' => $this->seedTimeRequired,
            'inspect_time_left' => $this->inspectTimeLeft,
        ];
        if (RequestContext::instance()->isPlatformAdmin()) {
            $out['comment'] = nl2br(trim($out['comment']));
        }

        return $out;
    }
}
