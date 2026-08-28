<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\File;
use App\Support\Format;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin File
 */
class FileResource extends JsonResource
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
            'filename' => $this->filename,
            'size_human' => Format::size($this->size),
        ];
    }
}
