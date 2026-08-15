<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Comment;
use App\Models\Post;
use App\Models\SeedBoxRecord;
use App\Models\User;
use App\Support\Cache;
use App\Support\Validators;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Nexus\Database\NexusDB;

final class UsercpRepository extends BaseRepository
{
    public static function getUserById(int $userId): User
    {
        return User::query()->findOrFail($userId);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function updateUser(int $userId, array $data): bool
    {
        return (bool) User::query()->where('id', $userId)->update($data);
    }

    public static function updateLastOffer(int $userId): bool
    {
        return (bool) User::query()->where('id', $userId)->update(['last_offer' => date("Y-m-d H:i:s")]);
    }

    public static function emailExistsForOther(string $email, int $userId): bool
    {
        return User::query()->where('email', $email)->where('id', '!=', $userId)->exists();
    }

    public static function getChallenge(string $username): ?string
    {
        return \Nexus\Database\NexusDB::cache_get(\App\Support\Token::challengeKey($username));
    }

    public static function deleteChallenge(string $username): bool
    {
        return (bool) \Nexus\Database\NexusDB::cache_del(\App\Support\Token::challengeKey($username));
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $allPost
     */
    public static function updateSecurity(int $userId, array $data, bool $resetAuthKey, array $allPost): bool
    {
        return (bool) \Nexus\Database\NexusDB::transaction(function () use ($userId, $data, $resetAuthKey, $allPost) {
            self::updateUser($userId, $data);
            if ($resetAuthKey) {
                $torrentRep = new \App\Repositories\TorrentRepository();
                $torrentRep->resetTrackerReportAuthKeySecret($userId);
            }
            \App\Support\Hooks::doAction("usercp_security_update", $allPost);

            return true;
        });
    }

    public static function getCommentCount(int $userId): int
    {
        return (int) Comment::query()->where('user', $userId)->count();
    }

    public static function getForumPostCount(int $userId): int
    {
        return (int) Post::query()->where('userid', $userId)->count();
    }

    public static function getTotalPostCount(): int
    {
        return (int) Post::query()->count();
    }

    public static function getTopicPostCount(int $topicId): int
    {
        return (int) Post::query()->where('topicid', $topicId)->count();
    }

    /**
     * @return  array<int, int>
     */
    public static function getTableIds(string $table): array
    {
        return NexusDB::table($table)->pluck('id')->all();
    }

    /**
     * @return  Collection<int, SeedBoxRecord>
     */
    public static function getSeedBoxRecords(int $userId): Collection
    {
        return SeedBoxRecord::query()
            ->where('uid', $userId)
            ->where('type', SeedBoxRecord::TYPE_USER)
            ->get();
    }

    /**
     * @return  array<int, array<string, mixed>>
     */
    public static function getReadTopics(int $userId, int $limit = 5): array
    {
        return NexusDB::table('readposts')
            ->join('topics', 'topics.id', '=', 'readposts.topicid')
            ->where('readposts.userid', $userId)
            ->orderByDesc('readposts.id')
            ->limit($limit)
            ->get(['topics.id as id', 'topics.userid', 'topics.subject', 'topics.lastpost', 'topics.views'])
            ->map(fn ($row) => (array) $row)
            ->all();
    }
    /**
     * @return  array<int, \stdClass>
     */
    public static function getCountryOptions(): array
    {
        return NexusDB::table('countries')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->all();
    }

    /**
     * @return  array<int, \stdClass>
     */
    public static function getBitbucketOptions(): array
    {
        return NexusDB::table('bitbucket')
            ->where('public', '1')
            ->get()
            ->all();
    }

    /**
     * @return  array<string, int>
     */
    public static function getStylesheetOptions(): array
    {
        return NexusDB::table('stylesheets')
            ->orderBy('name')
            ->pluck('id', 'name')
            ->all();
    }
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
