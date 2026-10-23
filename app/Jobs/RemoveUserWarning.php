<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ModelEvent;
use App\Models\User;
use App\Repositories\MessageRepository;
use App\Repositories\UserCleanupRepository;
use App\Repositories\UserDetailRepository;
use App\Support\Cache;
use App\Support\Locale;
use App\Support\Logger;
use App\Support\ModelEventPublisher;

class RemoveUserWarning
{
    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(UserDetailRepository $userDetailRepository, UserCleanupRepository $userCleanupRepository, MessageRepository $messageRepository): void
    {
        $users = $userCleanupRepository->listExpiredWarnings();
        $userModifyLogs = [];
        foreach ($users as $user) {
            $locale = $user->locale;
            $user->warned = false;
            $user->warneduntil = null;
            Logger::writeWithContext((string) sprintf('update user %s => %s', $user->id, json_encode($user->getDirty())), (string) 'info', (bool) false);
            // Overlapping sweeps select the same expired users; only the
            // worker that wins the conditional flag-clear may emit the
            // message, event and modify log.
            $claimed = User::query()
                ->where('id', $user->id)
                ->where('warned', true)
                ->where('warneduntil', '<', now())
                ->update($user->getDirty()) > 0;
            if (! $claimed) {
                continue;
            }
            $userModifyLogs[] = [
                'user_id' => $user->id,
                'content' => 'Warning removed by System.',
                'created_at' => now(),
                'updated_at' => now(),
            ];
            Cache::clearUser($user->id, '');
            ModelEventPublisher::publish(ModelEvent::UserUpdated, $user->id, '');
            $subject = Locale::trans('cleanup.msg_warning_removed', [], $locale);
            $msg = Locale::trans('cleanup.msg_your_warning_removed', [], $locale);
            $messageRepository->add([
                'sender' => null,
                'receiver' => $user->id,
                'added' => now(),
                'subject' => $subject,
                'msg' => $msg,
            ]);
        }
        if (! empty($userModifyLogs)) {
            $userDetailRepository->insertUserModifyLogs($userModifyLogs);
        }
        Logger::writeWithContext((string) ('remove warning of users, success handle user count: '.$users->count()), (string) 'info', (bool) false);
    }
}
