<?php

declare(strict_types=1);

namespace App\Models\Traits;

use App\Enums\UserStatus;
use App\Exceptions\NexusException;
use App\Support\Locale;
use Illuminate\Support\Facades\DB;

/**
 * Moderation helpers for the User model (mod comments and status checks).
 */
trait HasUserModeration
{
    /**
     * @param  list<string>  $fields
     */
    public function checkIsNormal(array $fields = ['status', 'enabled']): bool
    {
        $params = [
            'user_id' => $this->id,
            'username' => $this->username,
        ];
        if (in_array('status', $fields) && $this->getAttribute('status') !== UserStatus::CONFIRMED) {
            throw new NexusException(Locale::trans('user.user_is_not_confirmed', $params, null));
        }
        if (in_array('enabled', $fields) && ! $this->getAttribute('enabled')) {
            throw new NexusException(Locale::trans('user.user_is_disabled', $params, null));
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $update
     * @param  string  $modComment
     */
    public function updateWithModComment(array $update, $modComment): bool
    {
        return $this->updateWithComment($update, $modComment, 'modcomment');
    }

    /**
     * @param  array<string, mixed>  $update
     * @param  string  $comment
     * @param  string  $commentField
     */
    public function updateWithComment(array $update, $comment, $commentField): bool
    {
        if (! $this->exists) {
            throw new \RuntimeException('This method only works when user exists !');
        }

        if ($commentField != 'modcomment') {
            throw new \RuntimeException("unsupported commentField: $commentField !");
        }

        return DB::transaction(function () use ($update, $comment) {
            $this->modifyLogs()->create(['content' => $comment]);

            return $this->update($update);
        });
    }
}
