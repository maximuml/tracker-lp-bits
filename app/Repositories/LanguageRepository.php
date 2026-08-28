<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Http\Middleware\Locale;
use App\Models\Language;
use Illuminate\Support\Facades\Cache;

class LanguageRepository extends BaseRepository
{
    /**
     * Return the site language folder for a user.
     */
    public function getUserFolder(int $userId): string
    {
        $folder = Language::query()
            ->select('language.site_lang_folder')
            ->leftJoin('users', 'language.id', '=', 'users.lang')
            ->where('language.site_lang', 1)
            ->where('users.id', $userId)
            ->value('site_lang_folder');

        return $folder ?? 'en';
    }

    /**
     * Return the site language folder for a language id.
     */
    public function getFolderForId(int $langId, string $default = 'en'): string
    {
        return Language::query()
            ->where('site_lang', 1)
            ->where('id', $langId)
            ->value('site_lang_folder') ?? $default;
    }

    /**
     * Return the language id for a folder name.
     */
    public function getIdFromFolder(string $folder): int
    {
        $row = Language::query()
            ->where('site_lang', 1)
            ->where('site_lang_folder', $folder)
            ->orderBy('id')
            ->first();

        return (int) ($row->id ?? 0);
    }

    /**
     * Return the list of languages for a given scope.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getLanguageList(string $type, ?bool $enabled = null): array
    {
        $cacheKey = $type.'_lang_list';

        return Cache::remember($cacheKey, 600, function () use ($type, $enabled) {
            $query = Language::query()->where($type, 1);
            if ($enabled !== null) {
                $query->whereIn('site_lang_folder', Language::listEnabled(true));
            }

            return $query->get()->toArray();
        });
    }

    /**
     * Return the language id for a guest language folder, defaulting to 6.
     */
    public function getGuestId(string $langFolder): int
    {
        return (int) (Language::query()
            ->where('site_lang_folder', $langFolder)
            ->where('site_lang', 1)
            ->value('id') ?? 6);
    }

    /**
     * Return the locale (e.g. zh-CN, en) for a user id.
     */
    public function getUserLocale(int $userId): string
    {
        $folder = $this->getUserFolder($userId);

        if (empty($folder)) {
            return 'en';
        }

        return Locale::$languageMaps[$folder] ?? $folder;
    }
}
