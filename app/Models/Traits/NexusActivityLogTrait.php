<?php

declare(strict_types=1);

namespace App\Models\Traits;

use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

trait NexusActivityLogTrait
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->logAll();
    }
}
