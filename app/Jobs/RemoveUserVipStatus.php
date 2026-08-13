<?php

namespace App\Jobs;

use App\Enums\ModelEventEnum;
use App\Models\Message;
use App\Models\User;
use App\Models\UserModifyLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

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
            ->where('vip_added', 'yes')
            ->where('vip_until', '<', now())
            ->get();
        $userModifyLogs = [];
        foreach ($users as $user) {
            $locale = $user->locale;
            $userModifyLogs[] = [
                'user_id' => $user->id,
                'content' => "VIP status removed by - AutoSystem",
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $message = [];
            $user->vip_added = 'no';
            $user->vip_until = null;
            if ($user->class <= User::CLASS_VIP) {
                $user->class = User::CLASS_USER;
                $subject = \App\Support\Locale::trans("cleanup.msg_vip_status_removed", [], $locale);
                $msg = \App\Support\Locale::trans("cleanup.msg_vip_status_removed_body", [], $locale);
                $message = [
                    'sender' => 0,
                    'receiver' => $user->id,
                    'added' => now(),
                    'subject' => $subject,
                    'msg' => $msg,
                ];
            }
            \App\Support\Logger::writeWithContext((string) sprintf("update user %s => %s", $user->id, json_encode($user->getDirty())), (string) 'info', (bool) false);
            $user->save();
            \App\Support\Cache::clearUser($user->id, '');
            \App\Support\Events::publishModel(ModelEventEnum::USER_UPDATED, $user->id, "");
            if (!empty($message)) {
                Message::add($message);
            }
        }
        if (!empty($userModifyLogs)) {
            UserModifyLog::query()->insert($userModifyLogs);
        }
        \App\Support\Logger::writeWithContext((string) ("remove VIP status if time's up, success handle user count: " . $users->count()), (string) 'info', (bool) false);
    }
}
