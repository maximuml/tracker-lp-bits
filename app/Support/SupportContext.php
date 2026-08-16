<?php

namespace App\Support;

use App\Support\Cache\LegacyRedisCache;
use Illuminate\Http\Request;

/**
 * Static facade for the per-request NexusContext value object.
 *
 * Legacy helpers and Blade/PHP partials still call these static methods while
 * the implementation lives in NexusContext. The context must be populated
 * explicitly via fromRequest(); no PHP superglobals or $GLOBALS are used.
 */
final class SupportContext
{
    public static function fromRequest(Request $request): void
    {
        self::context()->setFromRequest($request);
    }

    public static function reset(): void
    {
        app()->instance(NexusContext::class, new NexusContext());
    }

    public static function getContext(): NexusContext
    {
        return self::context();
    }

    private static function context(): NexusContext
    {
        if (! app()->bound(NexusContext::class)) {
            app()->instance(NexusContext::class, new NexusContext());
        }

        return app(NexusContext::class);
    }

    /** @param array<string, mixed>|null $user */
    public static function setUser(?array $user): void
    {
        self::context()->setUser($user);
    }

    /** @return array<string, mixed>|null */
    public static function getUser(): ?array
    {
        return self::context()->getUser();
    }

    /**
     * Return a reference to the legacy per-request user update set.
     *
     * @return array<string, mixed>
     */
    public static function &getUserUpdateSet(): array
    {
        return self::context()->userUpdateSet;
    }

    /** @param array<string, mixed> $data */
    public static function setUserUpdateSet(array $data): void
    {
        self::context()->setUserUpdateSet($data);
    }

    public static function addUserUpdate(string $key, mixed $value): void
    {
        self::context()->addUserUpdate($key, $value);
    }

    /** @param array<string, string> $lang */
    public static function setLangFunctions(array $lang): void
    {
        self::context()->setLangFunctions($lang);
    }

    /** @return array<string, string> */
    public static function getLangFunctions(): array
    {
        return self::context()->getLangFunctions();
    }

    /** @param array<string, string> $lang */
    public static function setLangShoutbox(array $lang): void
    {
        self::context()->setLangShoutbox($lang);
    }

    /** @return array<string, string> */
    public static function getLangShoutbox(): array
    {
        return self::context()->getLangShoutbox();
    }

    public static function setCache(?LegacyRedisCache $cache): void
    {
        self::context()->setCache($cache);
    }

    public static function getCache(): ?LegacyRedisCache
    {
        return self::context()->getCache();
    }

    public static function setBonusTweak(string $value): void
    {
        self::context()->setBonusTweak($value);
    }

    public static function getBonusTweak(): string
    {
        return self::context()->getBonusTweak();
    }

    /** @param array<string, mixed> $config */
    public static function setSiteConfig(array $config): void
    {
        self::context()->setSiteConfig($config);
    }

    /** @return array<string, mixed> */
    public static function getSiteConfig(): array
    {
        return self::context()->getSiteConfig();
    }

    public static function setGlobal(string $key, mixed $value): void
    {
        self::context()->setGlobal($key, $value);
    }

    public static function getGlobal(string $key, mixed $default = null): mixed
    {
        return self::context()->getGlobal($key, $default);
    }

    /** @return array<string, mixed> */
    public static function getGlobalsForView(): array
    {
        return self::context()->getGlobalsForView();
    }

    public static function setServerValue(string $key, mixed $value): void
    {
        self::context()->setServerValue($key, $value);
    }

    public static function getServerValue(string $key, mixed $default = null): mixed
    {
        return self::context()->getServerValue($key, $default);
    }

    /** @param array<string, mixed> $cookie */
    public static function setCookie(array $cookie): void
    {
        self::context()->setCookie($cookie);
    }

    public static function getCookieValue(string $key, ?string $default = null): ?string
    {
        return self::context()->getCookieValue($key, $default);
    }

    /** @return array<string, mixed> */
    public static function allCookie(): array
    {
        return self::context()->allCookie();
    }

    /** @param array<string, mixed> $get */
    public static function setGet(array $get): void
    {
        self::context()->setGet($get);
    }

    public static function getQuery(string $key, mixed $default = null): mixed
    {
        return self::context()->getQuery($key, $default);
    }

    public static function removeQuery(string $key): void
    {
        self::context()->removeQuery($key);
    }

    /** @return array<string, mixed> */
    public static function allQuery(): array
    {
        return self::context()->allQuery();
    }

    /** @param array<string, mixed> $post */
    public static function setPost(array $post): void
    {
        self::context()->setPost($post);
    }

    public static function getPost(string $key, mixed $default = null): mixed
    {
        return self::context()->getPost($key, $default);
    }

    public static function removePost(string $key): void
    {
        self::context()->removePost($key);
    }

    /** @return array<string, mixed> */
    public static function allPost(): array
    {
        return self::context()->allPost();
    }

    /** @param array<string, mixed> $request */
    public static function setRequest(array $request): void
    {
        self::context()->setRequest($request);
    }

    public static function getRequestInput(string $key, mixed $default = null): mixed
    {
        return self::context()->getRequestInput($key, $default);
    }

    public static function removeRequestInput(string $key): void
    {
        self::context()->removeRequestInput($key);
    }

    /** @return array<string, mixed> */
    public static function allRequest(): array
    {
        return self::context()->allRequest();
    }

    /** @param array<string, mixed> $files */
    public static function setFiles(array $files): void
    {
        self::context()->setFiles($files);
    }

    public static function getFile(string $key, mixed $default = null): mixed
    {
        return self::context()->getFile($key, $default);
    }

    /** @return array<string, mixed> */
    public static function allFiles(): array
    {
        return self::context()->allFiles();
    }

    public static function setLaravelRequest(?Request $request): void
    {
        self::context()->setLaravelRequest($request);
    }

    public static function getLaravelRequest(): ?Request
    {
        return self::context()->getLaravelRequest();
    }
}
