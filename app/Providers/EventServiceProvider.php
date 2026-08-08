<?php

namespace App\Providers;

use App\Events\SeedBoxRecordUpdated;
use App\Events\TorrentCreated;
use App\Events\TorrentDeleted;
use App\Events\TorrentUpdated;
use App\Events\UserDeleted;
use App\Events\UserDisabled;
use App\Listeners\ClearTorrentCache;
use App\Listeners\DeductUserBonusWhenTorrentDeleted;
use App\Listeners\RemoveOauthTokens;
use App\Listeners\RemoveSeedBoxRecordCache;
use App\Listeners\SendEmailNotificationWhenTorrentCreated;
use App\Listeners\SyncTorrentToElasticsearch;
use App\Listeners\ResetNexus;
use App\Listeners\SyncTorrentToMeilisearch;
use App\Listeners\TestTorrentUpdated;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        SeedBoxRecordUpdated::class => [
            RemoveSeedBoxRecordCache::class,
        ],
        TorrentUpdated::class => [
            SyncTorrentToElasticsearch::class,
            SyncTorrentToMeilisearch::class,
        ],
        TorrentCreated::class => [
            SyncTorrentToElasticsearch::class,
            SyncTorrentToMeilisearch::class,
            SendEmailNotificationWhenTorrentCreated::class,
            ClearTorrentCache::class,
        ],
        TorrentDeleted::class => [
            DeductUserBonusWhenTorrentDeleted::class,
        ],
        UserDisabled::class => [
            RemoveOauthTokens::class,
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
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
