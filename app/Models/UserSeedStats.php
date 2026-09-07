<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Config;

/**
 * @property int $user_id
 * @property float $seed_points
 * @property float $seed_points_per_hour
 * @property float $seed_bonus_per_hour
 * @property Carbon|null $seed_points_updated_at
 * @property Carbon|null $seed_time_updated_at
 * @property int $seeding_torrent_count
 * @property int $seeding_torrent_size
 * @property int $attendance_card
 * @property int $offer_allowed_count
 */
class UserSeedStats extends Model
{
    protected $table = 'user_seed_stats';

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'user_id', 'seed_points', 'seed_points_per_hour', 'seed_bonus_per_hour',
        'seed_points_updated_at', 'seed_time_updated_at',
        'seeding_torrent_count', 'seeding_torrent_size',
        'attendance_card', 'offer_allowed_count',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'seed_points_updated_at' => 'datetime',
        'seed_time_updated_at' => 'datetime',
    ];

    public function getConnectionName(): string
    {
        return Config::get('nexus.database.default', null);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
