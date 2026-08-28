<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\News;
use App\Support\Description;
use App\Support\Time;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin News
 */
class NewsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  mixed  $request
     * @return array<int|string, mixed>
     */
    public function toArray($request): array
    {
        $descriptionArr = Description::parse((string) $this->body);

        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $descriptionArr,
            'images' => Description::imageFromDescription($descriptionArr),
            'added' => Time::formatDateTime($this->added, 'Y.m.d'),
            'user' => new UserResource($this->whenLoaded('user')),
        ];
    }
}
