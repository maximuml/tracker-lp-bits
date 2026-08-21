<?php

namespace App\Http\Resources;

use App\Models\Standard;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Standard
 */
class StandardResource extends JsonResource
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
            'name' => $this->name,
        ];
    }
}
