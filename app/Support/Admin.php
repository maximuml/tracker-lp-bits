<?php

namespace App\Support;

use Filament\Notifications\Notification;

/**
 * Filament admin-area notification helpers extracted from
 * `include/globalfunctions.php`.
 *
 * Backs `send_admin_success_notification()` and
 * `send_admin_fail_notification()`.
 */
final class Admin
{
    public static function successNotification(string $msg = ''): void
    {
        Notification::make()
            ->success()
            ->title($msg ?: 'Success!')
            ->send();
    }

    public static function failNotification(string $msg = ''): void
    {
        Notification::make()
            ->danger()
            ->title($msg ?: 'Fail!')
            ->send();
    }
}
