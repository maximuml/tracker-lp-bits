<?php

/**
 * @property int $family_id
 * @property string $name
 * @property string $peer_id
 * @property string $agent
 * @property string|null $comment
 * @property int $id
 */

namespace App\Models;

use App\Enums\ModelEventEnum;
use App\Models\Traits\NexusActivityLogTrait;
use App\Support\Events;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentDeny extends NexusModel
{
    use NexusActivityLogTrait;

    /** @var string */
    protected $table = 'agent_allowed_exception';

    /** @var list<string> */
    protected $fillable = [
        'family_id', 'name', 'peer_id', 'agent', 'comment',
    ];

    /** @return  mixed */
    protected static function booted()
    {
        static::created(function ($model) {
            Events::fire(ModelEventEnum::AGENT_DENY_CREATED, $model, null);
        });
        static::updated(function ($model) {
            Events::fire(ModelEventEnum::AGENT_DENY_UPDATED, $model, null);
        });
        static::deleted(function ($model) {
            Events::fire(ModelEventEnum::AGENT_DENY_DELETED, $model, null);
        });
    }

    /** @return  BelongsTo<AgentAllow, $this> */
    public function family(): BelongsTo
    {
        return $this->belongsTo(AgentAllow::class, 'family_id');
    }
}
