<?php

/**
 * @property int $id
 * @property int $torrentid
 * @property int $userid
 * @property string $ip
 * @property int $port
 * @property int $uploaded
 * @property int $downloaded
 * @property int $to_go
 * @property int $seedtime
 * @property int $leechtime
 * @property string|null $last_action
 * @property string|null $startdat
 * @property string|null $completedat
 * @property string $finished
 * @property int $hit_and_run_id
 * @property int $buy_log_id
 * @property int $leech_time_no_seeder
 */
namespace App\Models;


use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use JetBrains\PhpStorm\Pure;

/**
 * @property int $id
 * @property int $seedtime
 * @property int $counts
 * @property int $leech_time_no_seeder
 */
class Snatch extends NexusModel
{
    /** @var  string */
    protected $table = 'snatched';

    /** @var  list<string> */
    protected $fillable = [
        'torrentid', 'userid', 'ip', 'port', 'uploaded', 'downloaded', 'to_go', 'seedtime', 'leechtime',
        'last_action', 'startdat', 'completedat', 'finished', 'hit_and_run_id', 'buy_log_id',
    ];

    /** @var  array<string, string> */
    protected $casts = [
        'last_action' => 'datetime',
        'startdat' => 'datetime',
        'completedat' => 'datetime',
    ];

    /** @var  array<int|string, mixed> */
    public static $cardTitles = [
        'upload_text' => '上传',
        'download_text' => '下载',
        'share_ratio' => '分享率',
        'seed_time' => '做种时间',
        'leech_time' => '下载时间',
        'completed_at_human' => '完成',
    ];

    const FINISHED_YES = 'yes';

    const FINISHED_NO = 'no';

    /**
     * @return  \Illuminate\Database\Eloquent\Casts\Attribute<mixed, mixed>
     * @deprecated Use uploadedText instead
     */
    protected function uploadText(): Attribute
    {
        return new Attribute(
            get: fn($value, $attributes) => sprintf('%s@%s', \App\Support\Format::size($attributes['uploaded']), $this->getUploadSpeed())
        );
    }

    /**
     * @return  \Illuminate\Database\Eloquent\Casts\Attribute<mixed, mixed>
     * @deprecated Use downloadedText instead
     */
    protected function downloadText(): Attribute
    {
        return new Attribute(
            get: fn($value, $attributes) => sprintf('%s@%s', \App\Support\Format::size($attributes['downloaded']), $this->getDownloadSpeed())
        );
    }

    /** @return  \Illuminate\Database\Eloquent\Casts\Attribute<mixed, mixed> */
    protected function uploadedText(): Attribute
    {
        return new Attribute(
            get: fn($value, $attributes) => sprintf('%s@%s', \App\Support\Format::size($attributes['uploaded']), $this->getUploadSpeed())
        );
    }

    /** @return  \Illuminate\Database\Eloquent\Casts\Attribute<mixed, mixed> */
    protected function downloadedText(): Attribute
    {
        return new Attribute(
            get: fn($value, $attributes) => sprintf('%s@%s', \App\Support\Format::size($attributes['downloaded']), $this->getDownloadSpeed())
        );
    }

    /** @return  \Illuminate\Database\Eloquent\Casts\Attribute<mixed, mixed> */
    protected function shareRatio(): Attribute
    {
        return new Attribute(
            get: fn($value, $attributes) => $this->getShareRatio()
        );
    }

    public function getUploadSpeed(): string
    {
        if ($this->seedtime <= 0) {
            $speed =  \App\Support\Format::size(0);
        } else {
            $speed = \App\Support\Format::size($this->uploaded / ($this->seedtime + $this->leechtime));
        }
        return "$speed/s";
    }

    public function getDownloadSpeed(): string
    {
        if ($this->leechtime <= 0) {
            $speed = \App\Support\Format::size(0);
        } else {
            $speed = \App\Support\Format::size($this->downloaded / $this->leechtime);
        }
        return "$speed/s";
    }

    /** @return  mixed */
    public function getShareRatio()
    {
        if ($this->downloaded) {
            $ratio = floor(($this->uploaded / $this->downloaded) * 1000) / 1000;
        } elseif ($this->uploaded) {
            $ratio = nexus_trans('snatch.share_ratio_infinity');
        } else {
            $ratio = '---';
        }
        return $ratio;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Snatch>  $builder
     * @return  mixed
     */
    public function scopeIsFinished(Builder $builder)
    {
        return $builder->where('finished', self::FINISHED_YES);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Snatch>  $builder
     * @return  mixed
     */
    public function scopeIsNotFinished(Builder $builder)
    {
        return $builder->where('finished', self::FINISHED_NO);
    }

    /** @return  \Illuminate\Database\Eloquent\Relations\BelongsTo<Torrent, $this> */
    public function torrent(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Torrent::class, 'torrentid');
    }

    /** @return  \Illuminate\Database\Eloquent\Relations\BelongsTo<User, $this> */
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'userid');
    }
}
