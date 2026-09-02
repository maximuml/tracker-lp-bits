<?php

declare(strict_types=1);

namespace App\Http;

use App\Http\Middleware\Authenticate;
use App\Http\Middleware\CheckSiteStatus;
use App\Http\Middleware\CheckUserStatus;
use App\Http\Middleware\CronToken;
use App\Http\Middleware\EncryptCookies;
use App\Http\Middleware\Filament;
use App\Http\Middleware\LegacyRequestMiddleware;
use App\Http\Middleware\Locale;
use App\Http\Middleware\LogUserIp;
use App\Http\Middleware\NexusAuth;
use App\Http\Middleware\PreventRequestsDuringMaintenance;
use App\Http\Middleware\RecordHttpMetrics;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Middleware\RejectGetMutations;
use App\Http\Middleware\ResponseTimeHeader;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\TrimStrings;
use App\Http\Middleware\TrustProxies;
use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Auth\Middleware\AuthenticateWithBasicAuth;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Middleware\ValidatePostSize;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Middleware\SetCacheHeaders;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\ValidateSignature;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;

class Kernel extends HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     *
     * These middleware are run during every request to your application.
     *
     * @var array<int, string>
     */
    protected $middleware = [
        // \App\Http\Middleware\TrustHosts::class,
        TrustProxies::class,
        HandleCors::class,
        PreventRequestsDuringMaintenance::class,
        ValidatePostSize::class,
        TrimStrings::class,
        //        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
        LegacyRequestMiddleware::class,
        Locale::class,
        LogUserIp::class,
        SecurityHeaders::class,
        ResponseTimeHeader::class,
        RecordHttpMetrics::class,
    ];

    /**
     * The application's route middleware groups.
     *
     * @var array<string, array<int, string>>
     */
    protected $middlewareGroups = [
        'web' => [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            // \Illuminate\Session\Middleware\AuthenticateSession::class,
            ShareErrorsFromSession::class,
            VerifyCsrfToken::class,
            SubstituteBindings::class,
        ],

        'api' => [
            'throttle:api',
            SubstituteBindings::class,
            CheckSiteStatus::class,
        ],
        'filament' => [
            StartSession::class,
            //            \Filament\Http\Middleware\Authenticate::class,
            Filament::class,
        ],
    ];

    /**
     * The application's route middleware.
     * These middleware may be assigned to groups or used individually.
     *
     * @var array<string, string>
     */
    protected $routeMiddleware = [
        'auth' => Authenticate::class,
        'auth.basic' => AuthenticateWithBasicAuth::class,
        'auth.nexus' => NexusAuth::class,
        'cache.headers' => SetCacheHeaders::class,
        'can' => Authorize::class,
        'guest' => RedirectIfAuthenticated::class,
        'password.confirm' => RequirePassword::class,
        'signed' => ValidateSignature::class,
        'throttle' => ThrottleRequests::class,
        'verified' => EnsureEmailIsVerified::class,
        'locale' => Locale::class,
        'checkUserStatus' => CheckUserStatus::class,
        'cron.token' => CronToken::class,
        'reject.get.mutations' => RejectGetMutations::class,
    ];

    /** @var array<string, string> */
    protected $middlewareAliases = [
        'abilities' => CheckAbilities::class,
        'ability' => CheckForAnyAbility::class,
    ];
}
