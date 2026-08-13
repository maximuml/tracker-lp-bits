<?php

namespace App\Jobs;

use App\Enums\SeedBoxRecord\IpAsnEnum;
use App\Enums\SeedBoxRecord\IsAllowedEnum;
use App\Repositories\SeedBoxRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class UpdateIsSeedBoxFromUserRecordsCache implements ShouldQueue
{
    use Queueable;

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
        $rep = new SeedBoxRepository();
        foreach (IpAsnEnum::cases() as $field) {
            foreach (IsAllowedEnum::cases() as $isAllowed) {
                $rep->updateUserCacheCronjob($isAllowed, $field);
                \App\Support\Logger::writeWithContext((string) "SeedBoxRepository::updateUserCacheCronjob isAllowed: {$isAllowed->name}, field: {$field->name} success", (string) 'info', (bool) false);
                $rep->updateAdminCacheCronjob($isAllowed, $field);
                \App\Support\Logger::writeWithContext((string) "SeedBoxRepository::updateAdminCacheCronjob isAllowed: {$isAllowed->name}, field: {$field->name} success", (string) 'info', (bool) false);
            }
        }
        \App\Support\Logger::writeWithContext((string) "UpdateIsSeedBoxFromUserRecordsCache done!", (string) 'info', (bool) false);
    }
}
