<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ModelEventEnum;
use App\Models\Message;
use App\Models\User;
use App\Models\UserModifyLog;
use App\Support\Cache;
use App\Support\Events;
use App\Support\Locale;
use App\Support\Logger;

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
    public function handle(): void
    {
        $users = User::query()
            ->with('language')
            ->where('enabled', true)
            ->where('warned', true)
            ->where('warneduntil', '<', now())
            ->get();
        $userModifyLogs = [];
        foreach ($users as $user) {
            $locale = $user->locale;
            $userModifyLogs[] = [
                'user_id' => $user->id,
                'content' => 'Warning removed by System.',
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $user->warned = false;
            $user->warneduntil = null;
            Logger::writeWithContext((string) sprintf('update user %s => %s', $user->id, json_encode($user->getDirty())), (string) 'info', (bool) false);
            $user->save();
            Cache::clearUser($user->id, '');
            Events::publishModel(ModelEventEnum::USER_UPDATED, $user->id, '');
            $subject = Locale::trans('cleanup.msg_warning_removed', [], $locale);
            $msg = Locale::trans('cleanup.msg_your_warning_removed', [], $locale);
            Message::add([
                'sender' => null,
                'receiver' => $user->id,
                'added' => now(),
                'subject' => $subject,
                'msg' => $msg,
            ]);
        }
        if (! empty($userModifyLogs)) {
            UserModifyLog::query()->insert($userModifyLogs);
        }
        Logger::writeWithContext((string) ('remove warning of users, success handle user count: '.$users->count()), (string) 'info', (bool) false);
    }
}
