<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\Cache\LegacyRedisCache;
use App\Support\CurrentUser;
use App\Support\DestructiveEnvironmentGuard;
use App\Support\Env;
use App\Support\Environment;
use App\Support\Globals;
use App\Support\Html\SafeHtml;
use App\Support\Language;
use App\Support\LegacyHeaderBag;
use App\Support\Locale;
use App\Support\UserUpdateBatch;
use Filament\Facades\Filament;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
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
        // T-11: Per-request header bag for the legacy bridge — replaces
        // SAPI globals headers_list()/http_response_code()/header_remove()
        // that leak state across Octane worker requests.
        $this->app->singleton(LegacyHeaderBag::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (class_exists(Sanctum::class)) {
            Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
        }
        // Production guard: refuse to serve traffic with a placeholder or
        // missing APP_KEY. The installer generates a CSPRNG key, but if the
        // .env was copied manually or the key was cleared, encrypted cookies
        // and sessions would be silently broken or use a known key.
        if (app()->isProduction()) {
            $key = (string) Env::get('APP_KEY', '');
            if ($key === '' || $key === 'ChangeMeToYourGeneratedAppKeyNow') {
                throw new \RuntimeException(
                    'APP_KEY is missing or set to a placeholder. '
                    .'Run "php artisan key:generate" to generate a secure key.'
                );
            }
        }
        // Production startup validation: warn about missing/weak secrets.
        // Does not throw — the app may still function (e.g. cron is loopback
        // only without a token) — but logs a warning so operators notice.
        if (app()->isProduction()) {
            $cronToken = (string) Env::get('CRON_TOKEN', '');
            if ($cronToken !== '' && strlen($cronToken) < 32) {
                logger()->warning('CRON_TOKEN is set but shorter than 32 characters — consider using a stronger token.');
            }
        }
        // Query log only in non-production (avoids memory leak in prod)
        if (! app()->isProduction()) {
            DB::connection(config('database.default'))->enableQueryLog();
        }

        // Strict models: catch lazy loading and silently discarded attributes
        // in non-production. shouldBeStrict() (which also enables
        // preventAccessingMissingAttributes) is intentionally not enabled
        // because legacy code accesses virtual properties not declared as
        // accessors (e.g. Poll::options before migration, dynamic columns).
        Model::preventLazyLoading(! app()->isProduction());
        Model::preventSilentlyDiscardingAttributes(! app()->isProduction());
        $forceScheme = strtolower((string) Env::get('FORCE_SCHEME', ''));
        if (app()->environment('production') && in_array($forceScheme, ['https', 'http'], true)) {
            URL::forceScheme($forceScheme);
        }
        $this->customScheduleTask();
        $this->guardDestructiveCommands();

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

        // SafeHtml Blade directive: @safeHtml($var) renders a SafeHtml
        // value object's sanitized HTML. This replaces {!! !!} for
        // user-controlled content, creating a type boundary between
        // untrusted strings and sanitized HTML.
        Blade::directive('safeHtml', static function (string $expression): string {
            return "<?php \$__safeHtmlVal = $expression; echo \$__safeHtmlVal instanceof \\App\\Support\\Html\\SafeHtml ? \$__safeHtmlVal->toHtml() : htmlspecialchars((string) \$__safeHtmlVal, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8'); ?>";
        });

        // Register SafeHtml as a stringable type so {{ $safeHtml }}
        // automatically calls __toString() → toHtml()
        Blade::stringable(SafeHtml::class, static fn (SafeHtml $html): string => $html->toHtml());
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

    /**
     * Block destructive Artisan commands (migrate:fresh, migrate:refresh,
     * migrate:reset, db:wipe) from running against a non-test database when
     * APP_ENV=testing. This prevents accidental data loss if a developer
     * runs "php artisan migrate:fresh" with the testing env loaded but the
     * DB_DATABASE pointing at the dev/production database.
     */
    private function guardDestructiveCommands(): void
    {
        if (! Environment::isConsole()) {
            return;
        }

        $destructiveCommands = [
            'migrate:fresh',
            'migrate:refresh',
            'migrate:reset',
            'db:wipe',
        ];

        /** @var Dispatcher $eventDispatcher */
        $eventDispatcher = $this->app->make(Dispatcher::class);

        $eventDispatcher->listen(
            events: [CommandStarting::class],
            listener: static function (CommandStarting $event) use ($destructiveCommands): void {
                if (! in_array($event->command, $destructiveCommands, true)) {
                    return;
                }

                DestructiveEnvironmentGuard::assertTestingEnvironment();
            }
        );
    }
}
