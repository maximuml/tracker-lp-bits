<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Message;
use App\Models\TorrentOperationLog;
use App\Support\Cache;
use App\Support\Locale;
use App\Support\Logger;

class TorrentOperationLogRepository extends BaseRepository
{
    /**
     * Write an operation log row; optionally notify the torrent owner
     * by PM. Mirrors the former `TorrentOperationLog::add()` static
     * helper.
     *
     * @param  array<string, mixed>  $params
     */
    public function add(array $params, bool $notifyUser = false): TorrentOperationLog
    {
        $log = TorrentOperationLog::query()->create($params);
        if ($notifyUser) {
            $this->notifyUser($log);
        }

        return $log;
    }

    private function notifyUser(TorrentOperationLog $torrentOperationLog): void
    {
        $actionType = $torrentOperationLog->action_type;
        $receiver = $torrentOperationLog->torrent->user;
        if (! $receiver->exists || (int) $receiver->id <= 0) {
            Logger::writeWithContext((string) "skip notify user: torrent {$torrentOperationLog->torrent_id} has no existing owner", (string) 'info', (bool) false);

            return;
        }
        $locale = $receiver->locale;
        $subject = Locale::trans("torrent.operation_log.{$actionType}.notify_subject", [], $locale);
        $msg = Locale::trans("torrent.operation_log.{$actionType}.notify_msg", ['torrent_name' => $torrentOperationLog->torrent->name, 'detail_url' => sprintf('/web/details/%s', $torrentOperationLog->torrent_id), 'operator' => $torrentOperationLog->user->username, 'reason' => $torrentOperationLog->comment], $locale);
        $message = [
            'sender' => null,
            'receiver' => $receiver->id,
            'subject' => $subject,
            'msg' => $msg,
            'added' => now(),
        ];
        Message::query()->insert($message);
        Cache::forgetWithLocales("user_{$receiver->id}_unread_message_count");
        Cache::forgetWithLocales("user_{$receiver->id}_inbox_count");
        Logger::writeWithContext((string) "notify user: {$receiver->id}, {$subject}", (string) 'info', (bool) false);
    }
}
