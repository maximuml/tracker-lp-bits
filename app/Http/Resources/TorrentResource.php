<?php

namespace App\Http\Resources;

use App\Models\Attachment;
use App\Models\SearchBox;
use App\Models\Torrent;
use App\Repositories\TorrentRepository;
use Carbon\CarbonInterface;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use Nexus\Nexus;
use Illuminate\Http\Request;

/**
 * @mixin \App\Models\Torrent
 */
class TorrentResource extends BaseResource
{
    const NAME = 'torrent';

    /** @var  TorrentRepository */
    /** @var  TorrentRepository */
    /** @var  TorrentRepository */
    /** @var  TorrentRepository */
    /** @var  TorrentRepository */
    protected static TorrentRepository $torrentRep;

    /**
     * Transform the resource into an array.
     * @param  mixed  $request
     * @return  array<int|string, mixed>
     */
    public function toArray($request)
    {
        $out = [
            'id' => $this->id,
            'name' => $this->name,
            'filename' => $this->filename,
            'hash' => preg_replace_callback('/./s', [$this, "hex_esc"], $this->info_hash),
            'cover' => $this->cover,
            'category' => $this->category,
            'category_info' => new CategoryResource($this->whenLoaded('basic_category')),
            'size' => $this->size,
            'size_human' => \App\Support\Format::size($this->size),
            'added' => format_datetime($this->added),
            'added_human' => \App\Support\Time::format($this->added),
            'numfiles' => $this->numfiles ?: 0,
            'leechers' => $this->leechers ?: 0,
            'seeders' => $this->seeders ?: 0,
            'times_completed' => $this->times_completed ?: 0,
            'views' => $this->views ?: 0,
            'hits' => $this->hits ?: 0,
            'comments' => $this->comments ?: 0,
            'pos_state' => $this->pos_state,
            'pos_state_until' => format_datetime($this->pos_state_until),
            'pos_state_until_human' => \App\Support\Time::format($this->pos_state_until),
            'sp_state' => $this->sp_state,
            'sp_state_real' => $this->sp_state_real,
            'promotion_info' => $this->promotionInfo,
            'hr' => $this->hr ?: 0,
            'anonymous' => $this->anonymous,
            'last_action' => format_datetime($this->last_action),
            'last_action_human' => \App\Support\Time::format($this->last_action),
            'thank_users_count' => $this->whenCounted('thank_users'),
            'reward_logs_count' => $this->whenCounted('reward_logs'),
            'has_bookmarked' => $this->whenHas('has_bookmarked'),
            'has_thanked' => $this->whenHas('has_thanked'),
            'has_rewarded' => $this->whenHas('has_rewarded'),
            'description' => $this->whenHas('description'),
            'images' => $this->whenHas('images'),
            'download_url' => $this->whenHas('download_url'),
            'active_status' => $this->whenHas('active_status'),
            'user' => new UserResource($this->whenLoaded('user')),
            'extra' => new TorrentExtraResource($this->whenLoaded('extra')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'thanks' => ThankResource::collection($this->whenLoaded('thanks')),
            'reward_logs' => RewardResource::collection($this->whenLoaded('reward_logs')),
        ];
        $subCategories = [];
        foreach (SearchBox::$taxonomies as $field => $info) {
            $relation = "basic_$field";
            if ($this->resource->{$field} > 0 && $this->resource->relationLoaded($relation)) {
                $subCategories[$field] = [
                    'label' => $this->resource->getSubCategoryLabel($field),
                    'value' => $this->resource->{$relation}->name ?? '',
                ];
            }
        }
        $out['sub_categories'] = empty($subCategories) ? null : $subCategories;
        return $out;

    }


    protected function getResourceName(): string
    {
        return self::NAME;
    }

    /**
     * @param  mixed  $matches
     * @return  mixed
     */
    protected function hex_esc($matches) {
        return sprintf("%02x", ord($matches[0]));
    }

}
