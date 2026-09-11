<?php

declare(strict_types=1);

namespace App\DTOs\Usercp;

use App\Enums\UserAppendPromotion;
use App\Enums\UserFontsize;
use App\Enums\UserTimeType;
use App\Enums\UserTooltip;
use App\Support\Validators;
use Illuminate\Http\Request;

/**
 * Immutable DTO for tracker/browse settings on the user control panel.
 */
final readonly class TrackerSettingsDto
{
    private const NOTIF_PREFIXES = ['cat', 'sou', 'med', 'cod', 'sta', 'pro', 'aud'];

    /**
     * @param  array<string, true>  $notifPreferences  Checked per-category notification keys (e.g. cat1, sou2).
     * @param  ?string  $currentLangFolder  Current language cookie value, used to decide whether to refresh it.
     */
    public function __construct(
        public bool $pmnotif,
        public bool $emailnotif,
        public ?int $incldead,
        public ?string $spstate,
        public ?string $inclbookmarked,
        public array $notifPreferences,
        public ?int $stylesheet,
        public ?int $sitelanguage,
        public ?string $currentLangFolder,
        public int $torrentsperpage,
        public int $timetype,
        public bool $appendsticky,
        public bool $appendnew,
        public int $appendpromotion,
        public bool $appendpicked,
        public bool $dlicon,
        public bool $bmicon,
        public bool $showcomnum,
        public bool $showdescription,
        public bool $showsmalldescr,
        public bool $showcomment,
        public int $pmnum,
        public int $sbnum,
        public int $sbrefresh,
        public ?int $tooltip,
        public ?bool $showlastcom,
        public int $fontsize,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $incldead = self::numericInt($request->input('incldead'));
        $incldead = ($incldead !== null && $incldead != 1) ? $incldead : null;

        return new self(
            self::isYes($request->input('pmnotif')),
            self::isYes($request->input('emailnotif')),
            $incldead,
            self::stringOrNull($request->input('spstate')),
            self::stringOrNull($request->input('inclbookmarked')),
            self::collectNotifPreferences($request),
            self::intId($request->input('stylesheet', 0)),
            self::intId($request->input('sitelanguage', 0)),
            self::stringOrEmpty($request->cookie('c_lang_folder', '')),
            max(0, min(100, self::intInput($request->input('torrentsperpage', 0)))),
            self::enumIntOrString($request->input('timetype', ''), UserTimeType::class),
            self::yesNo($request->input('appendsticky')),
            self::yesNo($request->input('appendnew')),
            self::enumIntOrString($request->input('appendpromotion', ''), UserAppendPromotion::class),
            self::yesNo($request->input('appendpicked')),
            self::yesNo($request->input('dlicon')),
            self::yesNo($request->input('bmicon')),
            self::yesNo($request->input('showcomnum')),
            self::yesNo($request->input('showdescription')),
            self::yesNo($request->input('smalldescr')),
            self::yesNo($request->input('showcomment')),
            max(1, min(100, self::intInput($request->input('pmnum', 20)))),
            max(10, min(500, self::intInput($request->input('sbnum', 70)))),
            max(10, min(3600, self::intInput($request->input('sbrefresh', 120)))),
            $request->has('tooltip') ? self::enumIntOrString($request->input('tooltip', ''), UserTooltip::class) : null,
            $request->has('showlastcom') ? self::yesNo($request->input('showlastcom')) : null,
            self::fontSize($request),
        );
    }

    private static function intId(mixed $value): ?int
    {
        $id = (int) $value;

        return Validators::isId($id) ? $id : null;
    }

    private static function intInput(mixed $value): int
    {
        return (int) $value;
    }

    private static function isYes(mixed $value): bool
    {
        return is_string($value) && $value === 'yes';
    }

    private static function yesNo(mixed $value): bool
    {
        return self::isYes($value);
    }

    private static function stringOrNull(mixed $value): ?string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return null;
    }

    private static function stringOrEmpty(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return '';
    }

    /**
     * @return array<string, true>
     */
    private static function collectNotifPreferences(Request $request): array
    {
        // Notification keys are dynamic (<prefix><event>), so they cannot be
        // enumerated in validation rules; the whole input map is required here.
        // nosemgrep: laravel-request-all-in-controller
        $inputs = $request->all();
        $preferences = [];
        foreach (array_keys($inputs) as $key) {
            if (! is_string($key)) {
                continue;
            }
            foreach (self::NOTIF_PREFIXES as $prefix) {
                if (str_starts_with($key, $prefix) && $inputs[$key] === 'yes') {
                    $preferences[$key] = true;
                    break;
                }
            }
        }

        return $preferences;
    }

    private static function numericInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }

    /**
     * @param  class-string  $enumClass
     */
    private static function enumIntOrString(mixed $value, string $enumClass): int
    {
        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            return (int) $value;
        }

        return $enumClass::fromStringSafe(self::stringOrEmpty($value))->value;
    }

    private static function fontSize(Request $request): int
    {
        $raw = $request->input('fontsize', '');
        if (is_int($raw) || (is_string($raw) && ctype_digit($raw))) {
            return (int) $raw;
        }

        return UserFontsize::fromStringSafe(self::stringOrEmpty($raw))->value;
    }
}
