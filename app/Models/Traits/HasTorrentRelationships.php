<?php

declare(strict_types=1);

namespace App\Models\Traits;

use App\Enums\PeerSeeder;
use App\Models\AudioCodec;
use App\Models\Bookmark;
use App\Models\Category;
use App\Models\Codec;
use App\Models\File;
use App\Models\Media;
use App\Models\Peer;
use App\Models\Processing;
use App\Models\Reward;
use App\Models\Snatch;
use App\Models\Source;
use App\Models\Standard;
use App\Models\Tag;
use App\Models\Thank;
use App\Models\Torrent;
use App\Models\TorrentExtra;
use App\Models\TorrentOperationLog;
use App\Models\TorrentTag;
use App\Models\User;
use App\Repositories\TagRepository;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

/**
 * Eloquent relationships for the Torrent model.
 */
trait HasTorrentRelationships
{
    /** @return HasMany<Bookmark, $this> */
    public function bookmarks(): HasMany
    {
        return $this->hasMany(Bookmark::class, 'torrentid');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner')->withDefault(User::getDefaultUserAttributes());
    }

    /** @return HasMany<Thank, $this> */
    public function thanks()
    {
        return $this->hasMany(Thank::class, 'torrentid');
    }

    /** @return BelongsToMany<User, $this> */
    public function thank_users()
    {
        return $this->belongsToMany(User::class, 'thanks', 'torrentid', 'userid');
    }

    /**
     * 同伴
     *
     * @return HasMany<Peer, $this>
     */
    public function peers()
    {
        return $this->hasMany(Peer::class, 'torrent');
    }

    /**
     * 完成情况
     *
     * @return HasMany<Snatch, $this>
     */
    public function snatches()
    {
        return $this->hasMany(Snatch::class, 'torrentid');
    }

    /** @return mixed */
    public function upload_peers()
    {
        return $this->peers()->where('seeder', PeerSeeder::YES->value);
    }

    /** @return mixed */
    public function download_peers()
    {
        return $this->peers()->where('seeder', PeerSeeder::NO->value);
    }

    /** @return mixed */
    public function finish_peers()
    {
        return $this->peers()->where('finishedat', '>', 0);
    }

    /** @return HasMany<File, $this> */
    public function files(): HasMany
    {
        return $this->hasMany(File::class, 'torrent');
    }

    /** @return BelongsTo<Category, $this> */
    public function basic_category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category');
    }

    /** @return BelongsTo<Source, $this> */
    public function basic_source()
    {
        return $this->belongsTo(Source::class, 'source');
    }

    /** @return BelongsTo<Media, $this> */
    public function basic_medium()
    {
        return $this->belongsTo(Media::class, 'medium');
    }

    /** @return BelongsTo<Codec, $this> */
    public function basic_codec()
    {
        return $this->belongsTo(Codec::class, 'codec');
    }

    /** @return BelongsTo<Standard, $this> */
    public function basic_standard()
    {
        return $this->belongsTo(Standard::class, 'standard');
    }

    /** @return BelongsTo<Processing, $this> */
    public function basic_processing()
    {
        return $this->belongsTo(Processing::class, 'processing');
    }

    /** @return BelongsTo<AudioCodec, $this> */
    public function basic_audiocodec()
    {
        return $this->belongsTo(AudioCodec::class, 'audiocodec');
    }

    /** @return HasMany<TorrentTag, $this> */
    public function torrent_tags(): HasMany
    {
        return $this->hasMany(TorrentTag::class, 'torrent_id');
    }

    /** @return BelongsToMany<Tag, $this> */
    public function tags(): BelongsToMany
    {
        $idsString = app(TagRepository::class)->getOrderByFieldIdString();
        if (DB::connection()->getDriverName() === 'pgsql') {
            $orderByRaw = "array_position(ARRAY[$idsString]::int[], tags.id)";
        } elseif (DB::connection()->getDriverName() === 'mysql') {
            $orderByRaw = "FIELD(tags.id, $idsString)";
        } else {
            throw new \RuntimeException('Unsupported database');
        }

        return $this->belongsToMany(Tag::class, 'torrent_tags', 'torrent_id', 'tag_id')
            ->orderByRaw($orderByRaw); // @phpstan-ignore argument.type
    }

    /** @return HasMany<Reward, $this> */
    public function reward_logs(): HasMany
    {
        return $this->hasMany(Reward::class, 'torrentid');
    }

    /** @return HasMany<TorrentOperationLog, $this> */
    public function operationLogs(): HasMany
    {
        return $this->hasMany(TorrentOperationLog::class, 'torrent_id');
    }

    /** @return HasOne<TorrentExtra, $this> */
    public function extra(): HasOne
    {
        return $this->hasOne(TorrentExtra::class, 'torrent_id');
    }
}
