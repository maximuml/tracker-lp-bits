<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Repositories\AuthRepositoryInterface;
use App\Contracts\Repositories\ExamRepositoryInterface;
use App\Contracts\Repositories\ForumRepositoryInterface;
use App\Contracts\Repositories\MeiliSearchRepositoryInterface;
use App\Contracts\Repositories\PageLayoutRepositoryInterface;
use App\Contracts\Repositories\PostRepositoryInterface;
use App\Contracts\Repositories\SearchBoxRepositoryInterface;
use App\Contracts\Repositories\TagRepositoryInterface;
use App\Contracts\Repositories\ToolRepositoryInterface;
use App\Contracts\Repositories\TorrentDownloadRepositoryInterface;
use App\Contracts\Repositories\TorrentRepositoryInterface;
use App\Contracts\Repositories\UserModerationRepositoryInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\DTOs\Auth\ActorContext;
use App\Models\User;
use App\Observers\UserPartitionObserver;
use App\Repositories\AuthRepository;
use App\Repositories\ExamRepository;
use App\Repositories\ForumRepository;
use App\Repositories\MeiliSearchRepository;
use App\Repositories\PageLayoutRepository;
use App\Repositories\PostRepository;
use App\Repositories\SearchBoxRepository;
use App\Repositories\TagRepository;
use App\Repositories\ToolRepository;
use App\Repositories\TorrentDownloadRepository;
use App\Repositories\TorrentRepository;
use App\Repositories\UserModerationRepository;
use App\Repositories\UserRepository;
use App\Support\Cache\LegacyRedisCache;
use App\Support\Cache\TaggedCacheService;
use App\Support\CurrentUser;
use App\Support\DestructiveEnvironmentGuard;
use App\Support\Env;
use App\Support\Environment;
use App\Support\Globals;
use App\Support\Html\SafeHtml;
use App\Support\Language;
use App\Support\LegacyHeaderBag;
use App\Support\Locale;
use App\Support\Metrics\Collectors;
use App\Support\Metrics\MetricsRegistry;
use App\Support\Metrics\PrometheusFormatter;
use App\Support\RequestContext;
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
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Laravel\Horizon\Contracts\JobRepository;
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
        $this->app->singleton(TaggedCacheService::class);
        $this->app->singleton(CurrentUser::class);
        $this->app->singleton(Language::class);
        $this->app->singleton(Globals::class);
        $this->app->singleton(UserUpdateBatch::class);
        // T-20: ActorContext is a per-request singleton — the instance is
        // resolved lazily from the authenticated user and must be flushed
        // between requests under Octane. See ResetNexus listener.
        $this->app->singleton(ActorContext::class, static function (): ActorContext {
            return ActorContext::fromAuth();
        });
        // T-11: Per-request header bag for the legacy bridge — replaces
        // SAPI globals headers_list()/http_response_code()/header_remove()
        // that leak state across Octane worker requests.
        $this->app->singleton(LegacyHeaderBag::class);

        // W3-07: Repository contracts for the 10 most-used repositories.
        $this->app->bind(AuthRepositoryInterface::class, AuthRepository::class);
        $this->app->bind(ExamRepositoryInterface::class, ExamRepository::class);
        $this->app->bind(ForumRepositoryInterface::class, ForumRepository::class);
        $this->app->bind(MeiliSearchRepositoryInterface::class, MeiliSearchRepository::class);
        $this->app->bind(PageLayoutRepositoryInterface::class, PageLayoutRepository::class);
        $this->app->bind(PostRepositoryInterface::class, PostRepository::class);
        $this->app->bind(SearchBoxRepositoryInterface::class, SearchBoxRepository::class);
        $this->app->bind(TagRepositoryInterface::class, TagRepository::class);
        $this->app->bind(ToolRepositoryInterface::class, ToolRepository::class);
        $this->app->bind(TorrentDownloadRepositoryInterface::class, TorrentDownloadRepository::class);
        $this->app->bind(TorrentRepositoryInterface::class, TorrentRepository::class);
        $this->app->bind(UserModerationRepositoryInterface::class, UserModerationRepository::class);
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);

        // W6-01: /metrics collectors — one per domain, resolved once per request.
        $this->app->singleton(MetricsRegistry::class, static function ($app): MetricsRegistry {
            $fmt = new PrometheusFormatter;

            return new MetricsRegistry([
                new Collectors\HttpMetricsCollector($fmt),
                new Collectors\DatabaseMetricsCollector($fmt),
                new Collectors\RedisMetricsCollector($fmt),
                new Collectors\CacheMetricsCollector($fmt),
                new Collectors\SchedulerMetricsCollector($fmt),
                new Collectors\QueueMetricsCollector(
                    $fmt,
                    $app->bound(JobRepository::class) ? $app->make(JobRepository::class) : null,
                ),
                new Collectors\TrackerMetricsCollector($fmt),
                new Collectors\SearchMetricsCollector($fmt),
                new Collectors\OutboxMetricsCollector($fmt),
                new Collectors\AppInfoCollector($fmt),
            ]);
        });
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
        // Query log only in non-production (avoids memory leak in prod).
        // The DB::listen counter is registered unconditionally so that
        // nexus_db_query_count stays truthful when the query log is off.
        if (! app()->isProduction()) {
            DB::connection(config('database.default'))->enableQueryLog();
        }
        DB::listen(static function (): void {
            RequestContext::instance()->incrementDbQueryCount();
        });

        // W1-03: Share the per-request CSP nonce with Vite/Livewire so
        // that injected scripts and styles use nonce-based CSP instead
        // of 'unsafe-inline'/'unsafe-eval'.
        $cspNonce = (string) request()->attributes->get('csp_nonce', '');
        if ($cspNonce !== '') {
            Vite::useCspNonce($cspNonce);
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
        // renders its sanitized HTML verbatim (SafeHtml is Htmlable,
        // so e() passes it through unescaped).
        Blade::stringable(SafeHtml::class, static fn (SafeHtml $html): SafeHtml => $html);

        // W3-01: Register the dual-write observer for user partitioning.
        User::observe(UserPartitionObserver::class);
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
