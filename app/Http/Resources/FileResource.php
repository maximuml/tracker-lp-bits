<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\File
 */
class FileResource extends JsonResource
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
            'filename' => $this->filename,
            'size_human' => \App\Support\Format::size($this->size),
        ];
    }
}
