<?php

declare(strict_types=1);

namespace App\Models\Traits;

use App\Enums\UserDonate;
use App\Http\Middleware\Locale;
use App\Support\Format;
use App\Support\Logger;
use App\Support\Url;
use App\Support\UserDisplay;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * Eloquent attribute accessors / mutators for the User model.
 */
trait HasUserAccessors
{
    public function getDonateStatusAttribute(): string
    {
        if ($this->isDonating()) {
            return UserDonate::YES->value;
        }

        return UserDonate::NO->value;
    }

    public function getLocaleAttribute(): string
    {
        $locale = null;
        $log = 'user: '.$this->id;
        if (UserDisplay::currentId() == $this->id) {
            $locale = Locale::getLocaleFromCookie();
            $log .= ", locale from cookie: $locale";
        }
        if (! $locale) {
            $lang = $this->language?->site_lang_folder ?: 'en';
            $locale = Locale::$languageMaps[$lang] ?? 'en';
            $log .= ", [NO_DATA_FROM_COOKIE], lang from database: $lang, locale: $locale";
        }
        Logger::writeWithContext((string) $log, (string) 'info', (bool) false);

        return $locale;
    }

    public function getSiteLangFolderAttribute(): string
    {
        return 'en';
    }

    /** @return Attribute<string, mixed> */
    protected function uploadedText(): Attribute
    {
        return new Attribute(
            get: fn ($value, $attributes) => Format::size($attributes['uploaded'])
        );
    }

    /** @return Attribute<string, mixed> */
    protected function downloadedText(): Attribute
    {
        return new Attribute(
            get: fn ($value, $attributes) => Format::size($attributes['downloaded'])
        );
    }

    /** @return Attribute<string, mixed> */
    protected function genderText(): Attribute
    {
        return new Attribute(
            get: fn ($value, $attributes) => \App\Support\Locale::trans('user.genders.'.$attributes['gender'], [], null)
        );
    }

    protected function getTwoFactorAuthenticationStatusAttribute(): string
    {
        return $this->two_step_secret != '' ? 'yes' : 'no';
    }

    /**
     * @param  mixed  $value
     */
    public function getAvatarAttribute($value): string
    {
        if ($value) {
            if (substr($value, 0, 4) == 'http') {
                return $value;
            } else {
                Logger::writeWithContext((string) "user: {$this->id} avatar: {$value} is not valid url.", (string) 'info', (bool) false);
            }
        }

        return Url::schemeAndHost(false).'/pic/default_avatar.png';

    }
}
