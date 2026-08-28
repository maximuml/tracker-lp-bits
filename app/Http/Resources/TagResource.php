<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Tag;
use App\Support\Time;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Tag
 */
class TagResource extends JsonResource
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
            'color' => $this->color,
            'font_color' => $this->font_color,
            'font_size' => $this->font_size,
            'padding' => $this->padding,
            'margin' => $this->margin,
            'border_radius' => $this->border_radius,
            'priority' => $this->priority,
            'created_at' => Time::formatDateTime($this->created_at),
            'updated_at' => Time::formatDateTime($this->updated_at),
        ];
    }
}
