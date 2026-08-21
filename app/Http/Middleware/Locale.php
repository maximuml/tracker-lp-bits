<?php

namespace App\Http\Middleware;

use App\Support\Config\SiteConfig;
use App\Support\Logger;
use App\Support\SupportContext;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;
use Nexus\Nexus;

class Locale
{
    /** @var array<string, string> */
    public static array $languageMaps = [
        'en' => 'en',
    ];

    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user) {
            $locale = $user->locale;
            Logger::writeWithContext((string) "locale from user: {$user->id}, set locale: {$locale}", (string) 'info', (bool) false);
        } else {
            $locale = self::getLocaleFromCookie() ?? self::getDefault();
            Logger::writeWithContext((string) "locale from cookie, set locale: {$locale}", (string) 'info', (bool) false);
        }
        App::setLocale($locale);
        Carbon::setLocale($locale);

        /** @var Response $response */
        $response = $next($request);
        if ($response instanceof Response || $response instanceof JsonResponse) {
            $response->header('X-Request-Id', Nexus::instance()->getRequestId())->header('X-Nexusphp-Version', VERSION_NUMBER);
        }

        return $response;
    }

    /** @return  mixed */
    public static function getLocaleFromCookie()
    {
        if (IN_NEXUS) {
            $lang = IN_TRACKER ? null : \App\Support\Locale::folderFromCookie(SupportContext::getCookieValue('c_lang_folder', ''), (bool) false);
            $log = "IN_NEXUS, get_langfolder_cookie() or IN_TRACKER use null: $lang";
        } else {
            $lang = Cookie::get('c_lang_folder');
            $log = "Cookie::get(): $lang";
        }
        Logger::writeWithContext((string) $log, (string) 'info', (bool) false);
        $lang = $lang ?: 'en';

        return self::$languageMaps[$lang] ?? 'en';
    }

    /** @return  mixed */
    public static function getDefault()
    {
        $defaultLang = SiteConfig::current()->main->defaultLang();

        return self::$languageMaps[$defaultLang] ?? 'en';
    }
}
