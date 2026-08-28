<?php

declare(strict_types=1);

/**
 * @property int $id
 * @property string $family
 * @property string $start_name
 * @property string $peer_id_pattern
 * @property int $peer_id_match_num
 * @property string $peer_id_matchtype
 * @property string $peer_id_start
 * @property string $agent_pattern
 * @property int $agent_match_num
 * @property string $agent_matchtype
 * @property string $agent_start
 * @property string $exception
 * @property string $allowhttps
 * @property string|null $comment
 * @property int $hits
 */

namespace App\Models;

use App\Enums\ModelEventEnum;
use App\Models\Traits\NexusActivityLogTrait;
use App\Support\Events;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgentAllow extends NexusModel
{
    use NexusActivityLogTrait;

    /** @var string */
    protected $table = 'agent_allowed_family';

    /** @var list<string> */
    protected $fillable = [
        'family', 'start_name', 'exception', 'allowhttps', 'comment',
        'peer_id_pattern', 'peer_id_match_num', 'peer_id_matchtype', 'peer_id_start',
        'agent_pattern', 'agent_match_num', 'agent_matchtype', 'agent_start',
    ];

    /** @deprecated Use App\Enums\AgentAllowMatchType enum instead. */
    const MATCH_TYPE_DEC = 'dec';

    /** @deprecated Use App\Enums\AgentAllowMatchType enum instead. */
    const MATCH_TYPE_HEX = 'hex';

    /** @var array<int|string, mixed> */
    public static $matchTypes = [
        self::MATCH_TYPE_DEC => 'dec',
        self::MATCH_TYPE_HEX => 'hex',
    ];

    /** @return  mixed */
    protected static function booted()
    {
        static::created(function ($model) {
            Events::fire(ModelEventEnum::AGENT_ALLOW_CREATED, $model, null);
        });
        static::updated(function ($model) {
            Events::fire(ModelEventEnum::AGENT_ALLOW_UPDATED, $model, null);
        });
        static::deleted(function ($model) {
            Events::fire(ModelEventEnum::AGENT_ALLOW_DELETED, $model, null);
        });
    }

    /** @return  HasMany<AgentDeny, $this> */
    public function denies(): HasMany
    {
        return $this->hasMany(AgentDeny::class, 'family_id');
    }
}
