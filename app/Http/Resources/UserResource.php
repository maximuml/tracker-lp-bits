<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use App\Support\Format;
use App\Support\Ratio;
use App\Support\Time;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    const NAME = 'user';

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
            'username' => $this->username,
            'email' => $this->when(Gate::allows('viewEmail', $this->resource), $this->email),
            'status' => $this->status,
            'enabled' => $this->enabled,
            'added' => Time::formatDateTime($this->added),
            'added_human' => Time::format($this->added),
            'last_access' => Time::formatDateTime($this->last_access),
            'last_access_human' => Time::format($this->last_access),
            'last_login' => Time::formatDateTime($this->last_login),
            'last_login_human' => Time::format($this->last_login),
            'class' => $this->class,
            'class_text' => $this->class_text,
            'avatar' => $this->avatar,
            'invites' => $this->invites,
            'attendance_card' => $this->attendance_card,
            'uploaded' => (int) ($this->uploaded ?? 0),
            'uploaded_text' => Format::size((int) ($this->uploaded ?? 0)),
            'downloaded' => (int) ($this->downloaded ?? 0),
            'downloaded_text' => Format::size((int) ($this->downloaded ?? 0)),
            'bonus' => floatval($this->seedbonus ?? 0),
            'bonus_human' => number_format((float) ($this->seedbonus ?? 0), 1),
            'seed_points' => floatval($this->seed_points ?? 0),
            'seed_points_human' => number_format((float) ($this->seed_points ?? 0), 1),
            'seed_points_per_hour' => floatval($this->seed_points_per_hour ?? 0),
            'seed_points_per_hour_human' => number_format((float) ($this->seed_points_per_hour ?? 0), 1),
            'seed_bonus_per_hour' => floatval($this->seed_bonus_per_hour ?? 0),
            'seed_bonus_per_hour_human' => number_format((float) ($this->seed_bonus_per_hour ?? 0), 1),
            'seedtime' => (int) ($this->seedtime ?? 0),
            'seedtime_text' => Format::prettyTimeWithLocale((int) ($this->seedtime ?? 0)),
            'leechtime' => (int) ($this->leechtime ?? 0),
            'leechtime_text' => Format::prettyTimeWithLocale((int) ($this->leechtime ?? 0)),
            'share_ratio' => Ratio::forUserId($this->id),
            'seeding_leeching_data' => $this->whenHas('seeding_leeching_data'),
            'inviter' => new UserResource($this->whenLoaded('inviter')),
            'valid_medals' => MedalResource::collection($this->whenLoaded('valid_medals')),
        ];

        return $out;
    }
}
