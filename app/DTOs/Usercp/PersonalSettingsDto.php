<?php

declare(strict_types=1);

namespace App\DTOs\Usercp;

use App\Models\User;
use App\Support\Validators;
use Illuminate\Http\Request;

/**
 * Immutable DTO for personal settings on the user control panel.
 */
final readonly class PersonalSettingsDto
{
    public function __construct(
        public string $parked,
        public string $acceptpms,
        public string $deletepms,
        public string $savepms,
        public string $commentpm,
        public string $gender,
        public ?int $country,
        public ?int $trackerUrlId,
        public ?string $avatar,
        public string $info,
        public ?string $notifs,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $parked = $request->input('parked') === 'yes' ? 'yes' : 'no';
        $acceptpmsRaw = (string) $request->input('acceptpms', 'yes');
        $acceptpms = in_array($acceptpmsRaw, ['yes', 'friends', 'no'], true) ? $acceptpmsRaw : 'yes';
        $deletepms = $request->has('deletepms') ? 'yes' : 'no';
        $savepms = $request->has('savepms') ? 'yes' : 'no';
        $commentpm = $request->input('commentpm') === 'yes' ? 'yes' : 'no';
        $genderRaw = (string) $request->input('gender', 'N/A');
        $gender = in_array($genderRaw, ['N/A', 'Male', 'Female'], true) ? $genderRaw : 'N/A';

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

        return $active === [] ? '' : '[' . implode('][', array_keys($active)) . ']';
    }
}
