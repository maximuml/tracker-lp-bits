<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Message;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Mailbox repository: management of user PM mailboxes (pmboxes).
 */
class MailboxRepository extends BaseRepository
{
    /**
     * @return Collection<int, \stdClass>
     */
    public function getUserMailboxes(int $userId): Collection
    {
        return DB::table('pmboxes')
            ->where('userid', $userId)
            ->orderBy('boxnumber')
            ->get(['id', 'boxnumber', 'name']);
    }

    public function getMailboxName(int $userId, int $mailbox): ?string
    {
        return DB::table('pmboxes')
            ->where('userid', $userId)
            ->where('boxnumber', $mailbox)
            ->value('name');
    }

    public function getNextMailboxNumber(int $userId): int
    {
        $max = (int) DB::table('pmboxes')->where('userid', $userId)->max('boxnumber');

        return max(1, $max);
    }

    /**
     * @param  array<int|string, mixed>  $names
     */
    public function addMailboxes(int $userId, array $names): void
    {
        $box = $this->getNextMailboxNumber($userId);
        foreach ($names as $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }
            $box++;
            DB::table('pmboxes')->insert(['userid' => $userId, 'name' => $name, 'boxnumber' => $box]);
        }
    }

    public function updateMailbox(int $userId, int $boxId, string $newName): void
    {
        DB::table('pmboxes')->where('id', $boxId)->where('userid', $userId)->update(['name' => $newName]);
    }

    public function deleteMailbox(int $userId, int $boxId, int $boxNumber): void
    {
        DB::table('pmboxes')->where('id', $boxId)->where('userid', $userId)->delete();
        Message::query()->where('saved', true)->where('location', $boxNumber)->where('receiver', $userId)->update(['location' => 0]);
        Message::query()->where('saved', true)->where('sender', $userId)->update(['saved' => false]);
        Message::query()->where('saved', false)->where('location', $boxNumber)->where('receiver', $userId)->delete();
        Message::query()->where('location', 0)->where('saved', true)->where('sender', $userId)->delete();
    }
}
