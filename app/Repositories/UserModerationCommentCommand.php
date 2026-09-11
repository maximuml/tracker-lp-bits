<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\ModerationAction;
use App\Exceptions\NexusException;
use App\Models\Message;
use App\Models\User;
use App\Models\UserModifyLog;
use App\Support\Format;
use App\Support\Locale;
use App\Support\Logger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Moderation comment and numeric field adjustment commands.
 */
final class UserModerationCommentCommand
{
    /**
     * Get the latest moderation comment for a user.
     *
     * Since the `modcomment` column was dropped in migration
     * 2025_01_18_235747, moderation comments are stored in
     * `user_modify_logs` via the `modifyLogs()` relationship.
     */
    public function getModComment(int $id): string
    {
        $user = User::query()->findOrFail((int) $id);

        return (string) $user->modifyLogs()->orderByDesc('id')->value('content');
    }

    /**
     * @param  mixed  $action
     * @param  mixed  $field
     * @param  mixed  $value
     * @param  mixed  $reason
     */
    public function incrementDecrement(User $operator, User $targetUser, $action, $field, $value, $reason = ''): bool
    {
        $fieldMap = [
            'uploaded' => 'uploaded',
            'downloaded' => 'downloaded',
            'seedbonus' => 'seedbonus',
            'invites' => 'invites',
            'attendance_card' => 'attendance_card',
        ];
        if (! isset($fieldMap[$field])) {
            throw new \InvalidArgumentException("Invalid field: $field, only support: ".implode(', ', array_keys($fieldMap)));
        }
        $sourceField = $fieldMap[$field];
        $uid = (int) $targetUser->id;
        $old = (float) $targetUser->{$sourceField};
        $valueAtomic = (float) $value;
        $formatSize = false;
        if (in_array($field, ['uploaded', 'downloaded'])) {
            // Frontend unit: GB
            $valueAtomic = $valueAtomic * 1024 * 1024 * 1024;
            $formatSize = true;
        }
        $actionEnum = ModerationAction::tryFrom((string) $action);
        if ($actionEnum === null) {
            throw new \InvalidArgumentException("Invalid action: $action.");
        }
        if ($actionEnum === ModerationAction::INCREMENT) {
            $new = $old + abs($valueAtomic);
        } else {
            $new = $old - abs($valueAtomic);
        }
        if ($new < 0) {
            throw new NexusException("New value($new) lte 0");
        }
        // for administrator, use english
        $modCommentText = Locale::trans('message.field_value_change_message_body', ['field' => Locale::trans("user.labels.{$sourceField}", [], 'en'), 'operator' => $operator->username, 'old' => $formatSize ? Format::size((float) $old) : $old, 'new' => $formatSize ? Format::size((float) $new) : $new, 'reason' => $reason], 'en');
        Logger::writeWithContext((string) "user: {$uid}, {$modCommentText}", (string) 'alert', (bool) false);
        $update = [
            $sourceField => $new,
            //            'modcomment' => DB::raw("if(modcomment = '', '$modCommentText', concat_ws('\n', '$modCommentText', modcomment))"),
        ];
        $locale = $targetUser->locale;
        $fieldLabel = Locale::trans("user.labels.{$sourceField}", [], $locale);
        $msg = Locale::trans('message.field_value_change_message_body', ['field' => $fieldLabel, 'operator' => $operator->username, 'old' => $formatSize ? Format::size((float) $old) : $old, 'new' => $formatSize ? Format::size((float) $new) : $new, 'reason' => $reason], $locale);
        $message = [
            'sender' => null,
            'receiver' => $targetUser->id,
            'subject' => Locale::trans('message.field_value_change_message_subject', ['field' => $fieldLabel], $locale),
            'msg' => $msg,
            'added' => Carbon::now(),
        ];
        DB::transaction(function () use ($uid, $sourceField, $old, $update, $message, $modCommentText) {
            $affectedRows = User::query()
                ->where('id', $uid)
                ->where($sourceField, $old)
                ->update($update);
            if ($affectedRows != 1) {
                throw new \RuntimeException("Change fail, affected rows != 1($affectedRows)");
            }
            Message::query()->insert($message);
            UserModifyLog::query()->insert([
                'user_id' => $uid,
                'content' => $modCommentText,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        });

        return true;
    }
}
