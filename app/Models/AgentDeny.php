<?php

declare(strict_types=1);

/**
 * @property int $family_id
 * @property string $name
 * @property string $peer_id
 * @property string $agent
 * @property string|null $comment
 * @property int $id
 */

namespace App\Models;

use App\Events\AgentDenyCreated;
use App\Events\AgentDenyDeleted;
use App\Events\AgentDenyUpdated;
use App\Models\Traits\NexusActivityLogTrait;
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
            event(new AgentDenyCreated($model));
        });
        static::updated(function ($model) {
            event(new AgentDenyUpdated($model));
        });
        static::deleted(function ($model) {
            event(new AgentDenyDeleted($model->toArray()));
        });
    }

    /** @return  BelongsTo<AgentAllow, $this> */
    public function family(): BelongsTo
    {
        return $this->belongsTo(AgentAllow::class, 'family_id');
    }
}
