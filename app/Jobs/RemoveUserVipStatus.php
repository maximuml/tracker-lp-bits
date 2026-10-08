<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ModelEvent;
use App\Enums\UserClass as UserClassEnum;
use App\Repositories\MessageRepository;
use App\Repositories\UserCleanupRepository;
use App\Repositories\UserDetailRepository;
use App\Support\Cache;
use App\Support\Locale;
use App\Support\Logger;
use App\Support\ModelEventPublisher;

class RemoveUserVipStatus
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
        $users = $userCleanupRepository->listExpiredVips();
        $userModifyLogs = [];
        foreach ($users as $user) {
            $locale = $user->locale;
            $userModifyLogs[] = [
                'user_id' => $user->id,
                'content' => 'VIP status removed by - AutoSystem',
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $message = [];
            $user->vip_added = false;
            $user->vip_until = null;
            if ($user->class <= (int) UserClassEnum::VIP->value) {
                $user->class = (int) UserClassEnum::USER->value;
                $subject = Locale::trans('cleanup.msg_vip_status_removed', [], $locale);
                $msg = Locale::trans('cleanup.msg_vip_status_removed_body', [], $locale);
                $message = [
                    'sender' => null,
                    'receiver' => $user->id,
                    'added' => now(),
                    'subject' => $subject,
                    'msg' => $msg,
                ];
            }
            Logger::writeWithContext((string) sprintf('update user %s => %s', $user->id, json_encode($user->getDirty())), (string) 'info', (bool) false);
            $user->save();
            Cache::clearUser($user->id, '');
            ModelEventPublisher::publish(ModelEvent::UserUpdated, $user->id, '');
            if (! empty($message)) {
                $messageRepository->add($message);
            }
        }
        if (! empty($userModifyLogs)) {
            $userDetailRepository->insertUserModifyLogs($userModifyLogs);
        }
        Logger::writeWithContext((string) ("remove VIP status if time's up, success handle user count: ".$users->count()), (string) 'info', (bool) false);
    }
}
