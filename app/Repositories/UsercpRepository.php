<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\User;
use App\Support\Cache;
use App\Support\Validators;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class UsercpRepository extends BaseRepository
{
    /**
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        /** @var User $user */
        $user = Auth::user();

        return $user->toArray();
    }

    /**
     * Update personal settings for the authenticated user.
     *
     * @return array<string, mixed>
     */
    public function updatePersonal(Request $request): array
    {
        /** @var User $user */
        $user = Auth::user();

        $data = [];

        $data['parked'] = $request->input('parked') === 'yes' ? 'yes' : 'no';
        $data['acceptpms'] = in_array((string) $request->input('acceptpms'), ['yes', 'friends', 'no'], true)
            ? (string) $request->input('acceptpms')
            : 'yes';
        $data['deletepms'] = $request->has('deletepms') ? 'yes' : 'no';
        $data['savepms'] = $request->has('savepms') ? 'yes' : 'no';
        $data['commentpm'] = $request->input('commentpm') === 'yes' ? 'yes' : 'no';
        $data['gender'] = in_array((string) $request->input('gender'), ['N/A', 'Male', 'Female'], true)
            ? (string) $request->input('gender')
            : 'N/A';

        $country = (int) $request->input('country', 0);
        if (Validators::isId($country)) {
            $data['country'] = $country;
        }

        $trackerUrlId = (int) $request->input('tracker_url_id', 0);
        if (Validators::isId($trackerUrlId)) {
            $data['tracker_url_id'] = $trackerUrlId;
        }

        $avatar = (string) $request->input('avatar', '');
        if ($avatar === '') {
            $avatar = (string) $request->input('savatar', '');
        }
        if (preg_match('/^https?:\/\/[^\s\'"<>]+\.(jpg|gif|png|jpeg)$/i', $avatar)
            && ! preg_match('/\.php/i', $avatar)
            && ! preg_match('/\.js/i', $avatar)
            && ! preg_match('/\.cgi/i', $avatar)) {
            $data['avatar'] = htmlspecialchars(trim($avatar));
        }

        $data['info'] = htmlspecialchars(trim((string) $request->input('info', '')));

        $notifs = $request->input('notifs');
        if (is_array($notifs) || is_string($notifs)) {
            $notifsArr = [];
            if (is_array($notifs)) {
                foreach (User::$notificationOptions as $option) {
                    if (isset($notifs[$option]) && $notifs[$option]) {
                        $notifsArr[$option] = 1;
                    }
                }
            }
            $data['notifs'] = '[' . implode('][', array_keys($notifsArr)) . ']';
        }

        User::query()->where('id', $user->id)->update($data);
        Cache::clearUser($user->id, $user->passkey);

        return User::query()->find($user->id)?->toArray() ?? [];
    }

    /**
     * Update forum settings for the authenticated user.
     *
     * @return array<string, mixed>
     */
    public function updateForum(Request $request): array
    {
        /** @var User $user */
        $user = Auth::user();

        $data = [
            'topicsperpage' => max(0, min(100, (int) $request->input('topicsperpage', 0))),
            'postsperpage' => max(0, min(100, (int) $request->input('postsperpage', 0))),
            'avatars' => $request->input('avatars') === 'yes' ? 'yes' : 'no',
            'signatures' => $request->input('signatures') === 'yes' ? 'yes' : 'no',
            'clicktopic' => in_array((string) $request->input('clicktopic'), ['firstpage', 'lastpage'], true)
                ? (string) $request->input('clicktopic')
                : $user->clicktopic,
            'signature' => htmlspecialchars(trim((string) $request->input('signature', ''))),
        ];

        if ($request->has('ttlastpost')) {
            $data['showlastpost'] = $request->input('ttlastpost') === 'yes' ? 'yes' : 'no';
        }

        User::query()->where('id', $user->id)->update($data);
        Cache::clearUser($user->id, $user->passkey);

        return User::query()->find($user->id)?->toArray() ?? [];
    }
}
