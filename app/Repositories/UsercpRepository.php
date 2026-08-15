<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Comment;
use App\Models\Post;
use App\Models\SeedBoxRecord;
use App\Models\User;
use App\Support\Cache;
use App\Support\Locale;
use App\Support\SupportContext;
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
     * @return  array<int, array<string, mixed>>
     */
    public static function getUserTokens(User $user): array
    {
        $tokens = [];
        foreach ($user->tokens()->orderBy('id', 'desc')->get() as $token) {
            $abilities = $token->abilities ?? [];
            if (in_array('*', $abilities, true)) {
                $abilitiesText = 'ALL';
            } else {
                $parts = [];
                foreach ($abilities as $ability) {
                    $parts[] = \App\Support\Locale::trans("route-permission.{$ability}.text", [], null);
                }
                $abilitiesText = implode(', ', $parts);
            }

            $tokens[] = [
                'id' => $token->id,
                'name' => $token->name,
                'abilitiesText' => $abilitiesText,
                'created_at' => $token->created_at,
            ];
        }

        return $tokens;
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

    /**
     * Update tracker/browse settings for the authenticated user.
     *
     * @return array<string, mixed>
     */
    public function updateTracker(Request $request): array
    {
        /** @var User $user */
        $user = Auth::user();

        $notifsString = (string) $user->notifs;
        preg_match_all('/\[(.*)\]/Ui', $notifsString, $matches);
        $notifsArr = array_fill_keys($matches[1], 1);

        foreach (array_keys($notifsArr) as $key) {
            foreach (['incldead', 'spstate', 'inclbookmarked'] as $prefix) {
                if (str_starts_with((string) $key, $prefix)) {
                    unset($notifsArr[$key]);
                    break;
                }
            }
        }

        if ($request->input('pmnotif') === 'yes') {
            $notifsArr['pm'] = 1;
        } else {
            unset($notifsArr['pm']);
        }

        if ($request->input('emailnotif') === 'yes') {
            $notifsArr['email'] = 1;
        } else {
            unset($notifsArr['email']);
        }

        foreach ([
            'categories' => 'cat',
            'sources' => 'sou',
            'media' => 'med',
            'codecs' => 'cod',
            'standards' => 'sta',
            'processings' => 'pro',
            'audiocodecs' => 'aud',
        ] as $table => $cbname) {
            foreach (self::getTableIds($table) as $id) {
                if ($request->input($cbname . $id) === 'yes') {
                    $notifsArr[$cbname . $id] = 1;
                } else {
                    unset($notifsArr[$cbname . $id]);
                }
            }
        }

        $incldead = $request->input('incldead');
        if ($incldead !== null && $incldead != 1) {
            $notifsArr["incldead=$incldead"] = 1;
        }

        $spstate = $request->input('spstate');
        if ($spstate) {
            $notifsArr["spstate=$spstate"] = 1;
        }

        $inclbookmarked = $request->input('inclbookmarked');
        if ($inclbookmarked) {
            $notifsArr["inclbookmarked=$inclbookmarked"] = 1;
        }

        $data = [
            'notifs' => '[' . implode('][', array_keys($notifsArr)) . ']',
        ];

        $stylesheet = (int) $request->input('stylesheet', 0);
        if (Validators::isId($stylesheet)) {
            $data['stylesheet'] = $stylesheet;
        }

        $sitelanguage = (int) $request->input('sitelanguage', 0);
        if (Validators::isId($sitelanguage)) {
            $langFolder = Locale::folderForIdWithContext($sitelanguage);
            $currentFolder = Locale::folderFromCookie($request->cookie('c_lang_folder') ?? '', false);
            if ($currentFolder !== $langFolder) {
                Locale::setFolderCookie($langFolder, 0x7fffffff);
            }
            $data['lang'] = $sitelanguage;
        }

        $data['torrentsperpage'] = max(0, min(100, (int) $request->input('torrentsperpage', 0)));
        $data['timetype'] = (string) $request->input('timetype', '');
        $data['appendsticky'] = $request->input('appendsticky') === 'yes' ? 'yes' : 'no';
        $data['appendnew'] = $request->input('appendnew') === 'yes' ? 'yes' : 'no';
        $data['appendpromotion'] = (string) $request->input('appendpromotion', '');
        $data['appendpicked'] = $request->input('appendpicked') === 'yes' ? 'yes' : 'no';
        $data['dlicon'] = $request->input('dlicon') === 'yes' ? 'yes' : 'no';
        $data['bmicon'] = $request->input('bmicon') === 'yes' ? 'yes' : 'no';
        $data['showcomnum'] = $request->input('showcomnum') === 'yes' ? 'yes' : 'no';
        $data['showdescription'] = $request->input('showdescription') === 'yes' ? 'yes' : 'no';
        $data['showsmalldescr'] = $request->input('smalldescr') === 'yes' ? 'yes' : 'no';
        $data['showcomment'] = $request->input('showcomment') === 'yes' ? 'yes' : 'no';
        $data['pmnum'] = max(1, min(100, (int) $request->input('pmnum', 20)));
        $data['sbnum'] = max(10, min(500, (int) $request->input('sbnum', 70)));
        $data['sbrefresh'] = max(10, min(3600, (int) $request->input('sbrefresh', 120)));

        $showTooltip = (string) SupportContext::getGlobal('enabletooltip_tweak', '') === 'yes';
        if ($showTooltip) {
            $data['tooltip'] = (string) $request->input('tooltip', '');
            $data['showlastcom'] = $request->input('showlastcom') === 'yes' ? 'yes' : 'no';
        }

        $fontsize = (string) $request->input('fontsize', '');
        if (in_array($fontsize, ['small', 'medium', 'large'], true)) {
            $data['fontsize'] = $fontsize;
        } else {
            $data['fontsize'] = 'medium';
        }

        User::query()->where('id', $user->id)->update($data);
        Cache::clearUser($user->id, $user->passkey);

        return User::query()->find($user->id)?->toArray() ?? [];
    }
}
