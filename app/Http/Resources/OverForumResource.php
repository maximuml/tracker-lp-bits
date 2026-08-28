<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\OverForum;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OverForum
 */
class OverForumResource extends JsonResource
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
            'name' => $this->name,
            'description' => $this->description,
        ];
    }
}
