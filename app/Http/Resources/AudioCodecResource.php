<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\AudioCodec;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AudioCodec
 */
class AudioCodecResource extends JsonResource
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
        ];
    }
}
