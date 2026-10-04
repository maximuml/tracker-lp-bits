<?php

declare(strict_types=1);

/**
 * @property int $id
 * @property int $torrent_id
 * @property int $uid
 * @property string $action_type
 * @property string $comment
 * @property string|null $created_at
 * @property string|null $updated_at
 */

namespace App\Models;

use App\Enums\TorrentOperationAction;
use App\Support\Locale;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read Torrent $torrent
 * @property-read User $user
 */
class TorrentOperationLog extends NexusModel
{
    /** @var string */
    protected $table = 'torrent_operation_logs';

    /** @var bool */
    public $timestamps = true;

    /** @var list<string> */
    protected $fillable = ['uid', 'torrent_id', 'action_type', 'comment'];

    /** @var array<int|string, mixed> */
    public static array $actionTypes = [
        TorrentOperationAction::APPROVAL_NONE->value => ['text' => 'Approval none'],
        TorrentOperationAction::APPROVAL_ALLOW->value => ['text' => 'Approval allow'],
        TorrentOperationAction::APPROVAL_DENY->value => ['text' => 'Approval deny'],
        TorrentOperationAction::EDIT->value => ['text' => 'Edit'],
        TorrentOperationAction::DELETE->value => ['text' => 'Delete'],
    ];

    /** @return  mixed */
    public function getActionTypeTextAttribute()
    {
        return Locale::trans("torrent.operation_log.{$this->action_type}.type_text", [], null);
    }

    /** @return  BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uid')->select(User::$commonFields);
    }

    /** @return  BelongsTo<Torrent, $this> */
    public function torrent(): BelongsTo
    {
        return $this->belongsTo(Torrent::class, 'torrent_id')->select(Torrent::$commentFields);
    }
}
