<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ModelEvent;
use App\Repositories\MessageRepository;
use App\Repositories\UserCleanupRepository;
use App\Repositories\UserDetailRepository;
use App\Support\Cache;
use App\Support\Events;
use App\Support\Locale;
use App\Support\Logger;

class RemoveUserDonorStatus
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
        $users = $userCleanupRepository->listExpiredDonors();
        $userModifyLogs = [];
        foreach ($users as $user) {
            $locale = $user->locale;
            $userModifyLogs[] = [
                'user_id' => $user->id,
                'content' => 'donor status removed by - AutoSystem',
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $user->donor = false;
            Logger::writeWithContext((string) sprintf('update user %s => %s', $user->id, json_encode($user->getDirty())), (string) 'info', (bool) false);
            $user->save();
            Cache::clearUser($user->id, '');
            Events::publishModel(ModelEvent::UserUpdated, $user->id, '');
            $subject = Locale::trans('cleanup.msg_donor_status_removed', [], $locale);
            $msg = Locale::trans('cleanup.msg_donor_status_removed_body', [], $locale);
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
        Logger::writeWithContext((string) ("remove donor status if time's up, success handle user count: ".$users->count()), (string) 'info', (bool) false);
    }
}
