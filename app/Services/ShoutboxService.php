<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Permission;
use App\Enums\Permission\PermissionEnum;
use App\Models\User;
use App\Support\Shoutbox;
use Illuminate\Support\Facades\DB;
use Nexus\Database\NexusLock;

/**
 * Handles shoutbox message and reaction mutations.
 *
 * Read-side (history listing, SSE stream) stays in the controller and
 * ShoutboxRepository. This service owns all write operations: posting,
 * editing, deleting, clearing, and toggling reactions.
 */
final class ShoutboxService
{
    private const POST_LOCK_SECONDS = 60;

    private const EDIT_LOCK_SECONDS = 10;

    private const DELETE_LOCK_SECONDS = 10;

    private const REACT_LOCK_SECONDS = 5;

    /**
     * Post a new shoutbox message.
     *
     * @param  array<string, mixed>  $currentUser
     * @return bool True if the message was posted.
     */
    public function postMessage(array $currentUser, string $text): bool
    {
        $userId = (int) ($currentUser['id'] ?? 0);
        if ($userId <= 0 || $text === '') {
            return false;
        }
        if (mb_strlen($text) > Shoutbox::MAX_MESSAGE_LENGTH) {
            return false;
        }

        $lock = new NexusLock("shoutbox:{$userId}", self::POST_LOCK_SECONDS);
        if (! $lock->acquire()) {
            return false;
        }

        DB::table('shoutbox')->insert([
            'userid' => $userId,
            'date' => time(),
            'text' => $text,
            'type' => 'sb',
        ]);

        return true;
    }

    /**
     * Delete a shoutbox message (and its reactions) by id.
     *
     * @param  array<string, mixed>  $currentUser
     */
    public function deleteMessage(array $currentUser, int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        $msg = DB::table('shoutbox')->where('id', $id)->first();
        if (! $msg) {
            return true;
        }

        $userId = (int) ($currentUser['id'] ?? 0);
        $msgUserId = (int) ($msg->userid ?? 0);
        $msgDate = (int) ($msg->date ?? 0);

        if ($msgUserId !== $userId && ! Permission::can(PermissionEnum::SB_MANAGE)) {
            return false;
        }
        if ((time() - $msgDate) > Shoutbox::EDIT_WINDOW && ! Permission::can(PermissionEnum::SB_MANAGE)) {
            return false;
        }

        $lock = new NexusLock('shoutbox_delete:'.$userId, self::DELETE_LOCK_SECONDS);
        if (! $lock->acquire()) {
            return false;
        }
        try {
            DB::table('shoutbox')->where('id', $id)->delete();
            DB::table('shoutbox_reactions')->where('shoutbox_id', $id)->delete();

            return true;
        } finally {
            $lock->release();
        }
    }

    /**
     * Edit a shoutbox message's text.
     *
     * @param  array<string, mixed>  $currentUser
     */
    public function editMessage(array $currentUser, int $id, string $text): bool
    {
        $userId = (int) ($currentUser['id'] ?? 0);
        if ($id <= 0 || $text === '') {
            return false;
        }
        if (mb_strlen($text) > Shoutbox::MAX_MESSAGE_LENGTH) {
            return false;
        }

        $msg = DB::table('shoutbox')->where('id', $id)->first();
        if (! $msg) {
            return false;
        }

        $msgUserId = (int) ($msg->userid ?? 0);
        $msgDate = (int) ($msg->date ?? 0);

        if ($msgUserId !== $userId && ! Permission::can(PermissionEnum::SB_MANAGE)) {
            return false;
        }
        if ((time() - $msgDate) > Shoutbox::EDIT_WINDOW && ! Permission::can(PermissionEnum::SB_MANAGE)) {
            return false;
        }

        $lock = new NexusLock('shoutbox_edit:'.$userId, self::EDIT_LOCK_SECONDS);
        if (! $lock->acquire()) {
            return false;
        }
        try {
            DB::table('shoutbox')->where('id', $id)->update([
                'text' => $text,
                'edited_by' => $userId,
                'edited_at' => time(),
            ]);

            return true;
        } finally {
            $lock->release();
        }
    }

    /**
     * Clear all shoutbox messages and reactions. Staff only.
     *
     * @param  array<string, mixed>  $currentUser
     */
    public function clearAll(array $currentUser): bool
    {
        $user = User::query()->find($currentUser['id'] ?? 0);
        if (! $user instanceof User || ! Permission::can(PermissionEnum::SB_MANAGE, $user)) {
            return false;
        }

        DB::table('shoutbox')->delete();
        DB::table('shoutbox_reactions')->delete();

        return true;
    }

    /**
     * Toggle a reaction on a shoutbox message.
     *
     * @param  array<string, mixed>  $currentUser
     * @return string|null 'added', 'removed', or null on failure.
     */
    public function toggleReaction(array $currentUser, int $id, string $reaction): ?string
    {
        $userId = (int) ($currentUser['id'] ?? 0);
        if ($id <= 0 || ! in_array($reaction, Shoutbox::REACTIONS, true)) {
            return null;
        }

        $lock = new NexusLock('shoutbox_react:'.$userId, self::REACT_LOCK_SECONDS);
        if (! $lock->acquire()) {
            return null;
        }
        try {
            $table = DB::table('shoutbox_reactions');
            $existing = $table
                ->where('shoutbox_id', $id)
                ->where('user_id', $userId)
                ->where('reaction', $reaction)
                ->first();

            if ($existing) {
                $table->where('id', $existing->id)->delete();

                return 'removed';
            }

            $table->insert([
                'shoutbox_id' => $id,
                'user_id' => $userId,
                'reaction' => $reaction,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            return 'added';
        } finally {
            $lock->release();
        }
    }
}
