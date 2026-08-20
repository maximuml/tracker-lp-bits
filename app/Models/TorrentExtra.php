<?php

/**
 * @property int $id
 * @property int $torrent_id
 * @property string $descr
 * @property string|null $media_info
 * @property string|null $nfo
 * @property string|null $pt_gen
 * @property string|null $created_at
 * @property string|null $updated_at
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Nexus\Torrent\TechnicalInformation;

/**
 * @property string|null $descr
 */
class TorrentExtra extends NexusModel
{
    /** @var bool */
    public $timestamps = true;

    /** @var list<string> */
    protected $fillable = ['torrent_id', 'descr', 'ori_descr', 'media_info', 'nfo'];

    /** @return  BelongsTo<Torrent, $this> */
    public function torrent()
    {
        return $this->belongsTo(Torrent::class, 'torrent_id');
    }

    /** @var list<string> */
    protected $appends = ['media_info_summary'];

    /** @return  array<int|string, mixed> */
    public function getMediaInfoSummaryAttribute(): array
    {
        $technicalInfo = new TechnicalInformation($this->media_info ?? '');

        return $technicalInfo->getSummaryInfo();
    }
}
