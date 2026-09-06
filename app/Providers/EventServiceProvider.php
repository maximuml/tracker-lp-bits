<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\AgentAllowCreated;
use App\Events\AgentAllowDeleted;
use App\Events\AgentAllowUpdated;
use App\Events\AgentDenyCreated;
use App\Events\AgentDenyDeleted;
use App\Events\AgentDenyUpdated;
use App\Events\HitAndRunCreated;
use App\Events\HitAndRunDeleted;
use App\Events\HitAndRunUpdated;
use App\Events\MessageCreated;
use App\Events\NewsCreated;
use App\Events\SnatchedUpdated;
use App\Events\StaffMessageCreated;
use App\Events\TorrentCreated;
use App\Events\TorrentDeleted;
use App\Events\TorrentUpdated;
use App\Events\UserCreated;
use App\Events\UserDeleted;
use App\Events\UserDisabled;
use App\Events\UserEnabled;
use App\Events\UserUpdated;
use App\Listeners\AppendQueryCountHeader;
use App\Listeners\ClearTorrentCache;
use App\Listeners\DeductUserBonusWhenTorrentDeleted;
use App\Listeners\RecordCacheMetrics;
use App\Listeners\RecordModelEventToOutbox;
use App\Listeners\ResetNexus;
use App\Listeners\ResetQueryLog;
use App\Listeners\SendEmailNotificationWhenTorrentCreated;
use App\Listeners\SyncTorrentToMeilisearch;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
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
            RecordModelEventToOutbox::class,
        ],
        TorrentCreated::class => [
            SyncTorrentToMeilisearch::class,
            SendEmailNotificationWhenTorrentCreated::class,
            ClearTorrentCache::class,
            RecordModelEventToOutbox::class,
        ],
        TorrentDeleted::class => [
            DeductUserBonusWhenTorrentDeleted::class,
            RecordModelEventToOutbox::class,
        ],
        // W2-10: Record all model events to the outbox
        UserCreated::class => [RecordModelEventToOutbox::class],
        UserUpdated::class => [RecordModelEventToOutbox::class],
        UserDeleted::class => [RecordModelEventToOutbox::class],
        UserEnabled::class => [RecordModelEventToOutbox::class],
        UserDisabled::class => [RecordModelEventToOutbox::class],
        NewsCreated::class => [RecordModelEventToOutbox::class],
        HitAndRunCreated::class => [RecordModelEventToOutbox::class],
        HitAndRunUpdated::class => [RecordModelEventToOutbox::class],
        HitAndRunDeleted::class => [RecordModelEventToOutbox::class],
        MessageCreated::class => [RecordModelEventToOutbox::class],
        StaffMessageCreated::class => [RecordModelEventToOutbox::class],
        SnatchedUpdated::class => [RecordModelEventToOutbox::class],
        AgentAllowCreated::class => [RecordModelEventToOutbox::class],
        AgentAllowUpdated::class => [RecordModelEventToOutbox::class],
        AgentAllowDeleted::class => [RecordModelEventToOutbox::class],
        AgentDenyCreated::class => [RecordModelEventToOutbox::class],
        AgentDenyUpdated::class => [RecordModelEventToOutbox::class],
        AgentDenyDeleted::class => [RecordModelEventToOutbox::class],
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
        // T-23: Cache hit/miss metrics recording
        Event::listen(CacheHit::class, [RecordCacheMetrics::class, 'handleHit']);
        Event::listen(CacheMissed::class, [RecordCacheMetrics::class, 'handleMiss']);
    }
}
