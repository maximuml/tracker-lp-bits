<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\TorrentCreated;
use App\Events\TorrentDeleted;
use App\Events\TorrentUpdated;
use App\Listeners\AppendQueryCountHeader;
use App\Listeners\ClearTorrentCache;
use App\Listeners\DeductUserBonusWhenTorrentDeleted;
use App\Listeners\ResetNexus;
use App\Listeners\ResetQueryLog;
use App\Listeners\SendEmailNotificationWhenTorrentCreated;
use App\Listeners\SyncTorrentToMeilisearch;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\Looping;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        TorrentUpdated::class => [
            SyncTorrentToMeilisearch::class,
        ],
        TorrentCreated::class => [
            SyncTorrentToMeilisearch::class,
            SendEmailNotificationWhenTorrentCreated::class,
            ClearTorrentCache::class,
        ],
        TorrentDeleted::class => [
            DeductUserBonusWhenTorrentDeleted::class,
        ],
        Looping::class => [
            ResetNexus::class,
        ],
        JobProcessing::class => [
            ResetNexus::class,
        ],
        'Laravel\Octane\Events\RequestReceived' => [
            ResetNexus::class,
        ],
        'Laravel\Octane\Events\TaskReceived' => [
            ResetNexus::class,
        ],
        'Laravel\Octane\Events\TickReceived' => [
            ResetNexus::class,
        ],
        RouteMatched::class => [
            ResetQueryLog::class,
        ],
        RequestHandled::class => [
            AppendQueryCountHeader::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }
}
