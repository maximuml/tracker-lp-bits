<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\Cache\LegacyRedisCache;
use App\Support\CurrentUser;
use App\Support\Env;
use App\Support\Environment;
use App\Support\Globals;
use App\Support\Language;
use App\Support\Locale;
use App\Support\UserUpdateBatch;
use Filament\Facades\Filament;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LegacyRedisCache::class, static function (): LegacyRedisCache {
            $cache = new LegacyRedisCache;
            $cache->setLanguageFolderArray(Locale::available());

            return $cache;
        });
        $this->app->singleton(CurrentUser::class);
        $this->app->singleton(Language::class);
        $this->app->singleton(Globals::class);
        $this->app->singleton(UserUpdateBatch::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (class_exists(Sanctum::class)) {
            Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
        }
        DB::connection(config('database.default'))->enableQueryLog();

        Model::preventLazyLoading(! app()->isProduction());
        $forceScheme = strtolower((string) Env::get('FORCE_SCHEME', ''));
        if (app()->environment('production') && in_array($forceScheme, ['https', 'http'], true)) {
            URL::forceScheme($forceScheme);
        }
        $this->customScheduleTask();

        Filament::serving(function () {
            Filament::registerNavigationGroups([
                'User',
                'Torrent',
                'Tracker',
                'Role & Permission',
                'Other',
                'Section',
                'System',
            ]);
        });

        FilamentAsset::register([
            Css::make('sprites', asset('styles/sprites.css')),
            Css::make('admin', asset('styles/admin.css')),
        ]);

        // Pass the legacy global context into every view as individual variables
        // so Blade/PHP partials no longer need extract($context, EXTR_SKIP).
        View::composer('*', static function (\Illuminate\View\View $view): void {
            $context = app(Globals::class)->forView();
            foreach ($context as $key => $value) {
                if (! array_key_exists($key, $view->getData())) {
                    $view->with($key, $value);
                }
            }
            $view->with('context', $context);
        });
    }

    private function customScheduleTask(): void
    {
        if (! Environment::isConsole()) {
            return;
        }
        /** @var Dispatcher $eventDispatcher */
        $eventDispatcher = $this->app->make(Dispatcher::class);

        $eventDispatcher->listen(
            events: [ScheduledTaskStarting::class],
            listener: static function (ScheduledTaskStarting $event): void {
                $event->task->onOneServer()->withoutOverlapping();
                // When we are using stterr as output for logs then schedule tasks will not output
                // any logs  due the /dev/null usage. Let's fix this by appending the output to
                // the docker process.
                if (getenv('RUNNING_IN_DOCKER') == '1' && $event->task->output === $event->task->getDefaultOutput()) {
                    $event->task->appendOutputTo('/proc/1/fd/1');
                }
            }
        );
    }
}
