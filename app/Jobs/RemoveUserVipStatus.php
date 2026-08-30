<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ModelEventEnum;
use App\Enums\UserClass as UserClassEnum;
use App\Models\Message;
use App\Models\User;
use App\Models\UserModifyLog;
use App\Support\Cache;
use App\Support\Events;
use App\Support\Locale;
use App\Support\Logger;

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
    public function handle(): void
    {
        $users = User::query()
            ->with('language')
            ->where('vip_added', true)
            ->where('vip_until', '<', now())
            ->get();
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
            Events::publishModel(ModelEventEnum::USER_UPDATED, $user->id, '');
            if (! empty($message)) {
                Message::add($message);
            }
        }
        if (! empty($userModifyLogs)) {
            UserModifyLog::query()->insert($userModifyLogs);
        }
        Logger::writeWithContext((string) ("remove VIP status if time's up, success handle user count: ".$users->count()), (string) 'info', (bool) false);
    }
}
