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

class AgentDeny extends NexusModel
{
    use NexusActivityLogTrait;

    /** @var  string */
    protected $table = 'agent_allowed_exception';

    /** @var  list<string> */
    protected $fillable = [
        'family_id', 'name', 'peer_id', 'agent', 'comment'
    ];

    /** @return  mixed */
    protected static function booted()
    {
        static::created(function ($model) {
            \App\Support\Events::fire(ModelEventEnum::AGENT_DENY_CREATED, $model, null);
        });
        static::updated(function ($model) {
            \App\Support\Events::fire(ModelEventEnum::AGENT_DENY_UPDATED, $model, null);
        });
        static::deleted(function ($model) {
            \App\Support\Events::fire(ModelEventEnum::AGENT_DENY_DELETED, $model, null);
        });
    }

    /** @return  \Illuminate\Database\Eloquent\Relations\BelongsTo<AgentAllow, $this> */
    public function family(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(AgentAllow::class, 'family_id');
    }
}
