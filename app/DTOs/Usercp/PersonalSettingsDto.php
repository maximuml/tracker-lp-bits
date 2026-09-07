<?php

declare(strict_types=1);

namespace App\DTOs\Usercp;

use App\Enums\UserAcceptPms;
use App\Enums\UserGender;
use App\Models\User;
use App\Support\Validators;
use Illuminate\Http\Request;

/**
 * Immutable DTO for personal settings on the user control panel.
 */
final readonly class PersonalSettingsDto
{
    public function __construct(
        public bool $parked,
        public int $acceptpms,
        public bool $deletepms,
        public bool $savepms,
        public bool $commentpm,
        public int $gender,
        public ?int $country,
        public ?int $trackerUrlId,
        public ?string $avatar,
        public string $info,
        public ?string $notifs,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $parked = $request->input('parked') === 'yes';
        $acceptpmsRaw = $request->input('acceptpms', 'yes');
        $acceptpms = is_int($acceptpmsRaw) || (is_string($acceptpmsRaw) && ctype_digit($acceptpmsRaw))
            ? (int) $acceptpmsRaw
            : UserAcceptPms::fromStringSafe((string) $acceptpmsRaw)->value;
        $deletepms = $request->has('deletepms');
        $savepms = $request->has('savepms');
        $commentpm = $request->input('commentpm') === 'yes';
        $genderRaw = $request->input('gender', 'N/A');
        $gender = is_int($genderRaw) || (is_string($genderRaw) && ctype_digit($genderRaw))
            ? (int) $genderRaw
            : UserGender::fromStringSafe((string) $genderRaw)->value;

        $country = (int) $request->input('country', 0);
        $country = Validators::isId($country) ? $country : null;

        $trackerUrlId = (int) $request->input('tracker_url_id', 0);
        $trackerUrlId = Validators::isId($trackerUrlId) ? $trackerUrlId : null;

        $avatar = self::sanitizeAvatar($request);
        $info = htmlspecialchars(trim((string) $request->input('info', '')));

        $notifs = self::buildNotifs($request);

        return new self(
            $parked,
            $acceptpms,
            $deletepms,
            $savepms,
            $commentpm,
            $gender,
            $country,
            $trackerUrlId,
            $avatar,
            $info,
            $notifs,
        );
    }

    private static function sanitizeAvatar(Request $request): ?string
    {
        $avatar = (string) $request->input('avatar', '');
        if ($avatar === '') {
            $avatar = (string) $request->input('savatar', '');
        }

        if (
            preg_match('/^https?:\/\/[^\s\'"<>]+\.(jpg|gif|png|jpeg)$/i', $avatar)
            && ! preg_match('/\.php/i', $avatar)
            && ! preg_match('/\.js/i', $avatar)
            && ! preg_match('/\.cgi/i', $avatar)
        ) {
            return htmlspecialchars(trim($avatar));
        }

        return null;
    }

    private static function buildNotifs(Request $request): ?string
    {
        $notifs = $request->input('notifs');
        if (! is_array($notifs)) {
            return null;
        }

        $active = [];
        foreach (User::$notificationOptions as $option) {
            if (! empty($notifs[$option])) {
                $active[$option] = 1;
            }
        }

        return $active === [] ? '' : '['.implode('][', array_keys($active)).']';
    }
}
