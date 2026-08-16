<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\News
 */
class NewsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     * @param  mixed  $request
     * @return  array<int|string, mixed>
     */
    public function toArray($request)
    {
        $descriptionArr = \App\Support\Description::parse((string) $this->body);
        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $descriptionArr,
            'images' => \App\Support\Description::imageFromDescription($descriptionArr),
            'added' => \App\Support\Time::formatDateTime($this->added, 'Y.m.d'),
            'user' => new UserResource($this->whenLoaded('user'))
        ];
    }
}
