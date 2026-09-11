<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTOs\Usercp\ForumSettingsDto;
use App\DTOs\Usercp\PersonalSettingsDto;
use App\DTOs\Usercp\SecuritySettingsDto;
use App\DTOs\Usercp\TrackerSettingsDto;
use App\Enums\BitbucketPublic;
use App\Enums\UserTooltip;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Support\Cache;
use App\Support\Config\SiteConfig;
use App\Support\Locale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class UsercpRepository extends BaseRepository
{
    public function __construct(
        private readonly UsercpSecurityCommand $security,
    ) {}

    public function getUserById(int $userId): User
    {
        return User::query()->findOrFail($userId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getUserTokens(User $user): array
    {
        $tokens = [];
        foreach ($user->tokens()->orderBy('id', 'desc')->get() as $token) {
            $abilities = $token->abilities ?? [];
            if (in_array('*', $abilities, true)) {
                $abilitiesText = 'ALL';
            } else {
                $parts = [];
                foreach ($abilities as $ability) {
                    $parts[] = Locale::trans("route-permission.{$ability}.text", [], null);
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
    public function updateUser(int $userId, array $data): bool
    {
        return $this->security->updateUser($userId, $data);
    }

    public function updateLastOffer(int $userId): bool
    {
        return (bool) User::query()->where('id', $userId)->update(['last_offer' => date('Y-m-d H:i:s')]);
    }

    public function emailExistsForOther(string $email, int $userId): bool
    {
        return $this->security->emailExistsForOther($email, $userId);
    }

    public function getChallenge(string $username): ?string
    {
        return $this->security->getChallenge($username);
    }

    public function deleteChallenge(string $username): bool
    {
        return $this->security->deleteChallenge($username);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateSecurity(int $userId, array $data, bool $resetAuthKey): bool
    {
        return $this->security->updateSecurity($userId, $data, $resetAuthKey);
    }

    public function getCommentCount(int $userId): int
    {
        return (int) Comment::query()->where('user', $userId)->count();
    }

    public function getForumPostCount(int $userId): int
    {
        return (int) Post::query()->where('userid', $userId)->count();
    }

    public function getTotalPostCount(): int
    {
        return (int) Post::query()->count();
    }

    public function getTopicPostCount(int $topicId): int
    {
        return (int) Post::query()->where('topicid', $topicId)->count();
    }

    /**
     * @return array<int, int>
     */
    public function getTableIds(string $table): array
    {
        return DB::table($table)->pluck('id')->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getReadTopics(int $userId, int $limit = 5): array
    {
        return DB::table('readposts')
            ->join('topics', 'topics.id', '=', 'readposts.topicid')
            ->where('readposts.userid', $userId)
            ->orderByDesc('readposts.id')
            ->limit($limit)
            ->get(['topics.id as id', 'topics.userid', 'topics.subject', 'topics.lastpost', 'topics.views'])
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /**
     * @return array<int, \stdClass>
     */
    public function getCountryOptions(): array
    {
        return DB::table('countries')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->all();
    }

    /**
     * @return array<int, \stdClass>
     */
    public function getBitbucketOptions(): array
    {
        return DB::table('bitbucket')
            ->where('public', BitbucketPublic::YES->value)
            ->get()
            ->all();
    }

    /**
     * @return array<string, int>
     */
    public function getStylesheetOptions(): array
    {
        return DB::table('stylesheets')
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

        return $user->toApiArray();
    }

    /**
     * Update personal settings for the authenticated user.
     *
     * @return array<string, mixed>
     */
    public function updatePersonal(PersonalSettingsDto $dto): array
    {
        /** @var User $user */
        $user = Auth::user();

        $data = [
            'parked' => $dto->parked,
            'acceptpms' => $dto->acceptpms,
            'deletepms' => $dto->deletepms,
            'savepms' => $dto->savepms,
            'commentpm' => $dto->commentpm,
            'gender' => $dto->gender,
            'info' => $dto->info,
        ];

        if ($dto->notifs !== null) {
            $data['notifs'] = $dto->notifs;
        }

        if ($dto->country !== null) {
            $data['country'] = $dto->country;
        }

        if ($dto->trackerUrlId !== null) {
            $data['tracker_url_id'] = $dto->trackerUrlId;
        }

        if ($dto->avatar !== null) {
            $data['avatar'] = $dto->avatar;
        }

        User::query()->where('id', $user->id)->update($data);
        Cache::clearUser($user->id, (string) $user->passkey);

        return User::query()->find($user->id)?->toApiArray() ?? [];
    }

    /**
     * Update forum settings for the authenticated user.
     *
     * @return array<string, mixed>
     */
    public function updateForum(ForumSettingsDto $dto): array
    {
        /** @var User $user */
        $user = Auth::user();
        if (! $user instanceof User) {
            throw new \RuntimeException('Unauthenticated');
        }

        $data = [
            'topicsperpage' => $dto->topicsperpage,
            'postsperpage' => $dto->postsperpage,
            'avatars' => $dto->avatars,
            'signatures' => $dto->signatures,
            'clicktopic' => $dto->clicktopic !== null ? $dto->clicktopic : $user->clicktopic,
            'signature' => $dto->signature,
        ];

        if ($dto->showlastpost !== null) {
            $data['showlastpost'] = $dto->showlastpost;
        }

        User::query()->where('id', $user->id)->update($data);
        Cache::clearUser($user->id, (string) $user->passkey);

        return User::query()->find($user->id)?->toApiArray() ?? [];
    }

    /**
     * Update tracker/browse settings for the authenticated user.
     *
     * @return array<string, mixed>
     */
    public function updateTracker(TrackerSettingsDto $dto): array
    {
        /** @var User $user */
        $user = Auth::user();
        if (! $user instanceof User) {
            throw new \RuntimeException('Unauthenticated');
        }

        $notifsString = (string) $user->notifs;
        preg_match_all('/\[(.*)\]/Ui', $notifsString, $matches);
        $notifsArr = array_fill_keys($matches[1], 1);

        $dynamicPrefixes = array_merge(
            ['incldead=', 'spstate=', 'inclbookmarked='],
            ['cat', 'sou', 'med', 'cod', 'sta', 'pro', 'aud']
        );
        foreach (array_keys($notifsArr) as $key) {
            foreach ($dynamicPrefixes as $prefix) {
                if (str_starts_with((string) $key, $prefix)) {
                    unset($notifsArr[$key]);
                    break;
                }
            }
        }

        if ($dto->pmnotif) {
            $notifsArr['pm'] = 1;
        } else {
            unset($notifsArr['pm']);
        }

        if ($dto->emailnotif) {
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
            foreach ($this->getTableIds($table) as $id) {
                if ($dto->notifPreferences[$cbname.$id] ?? false) {
                    $notifsArr[$cbname.$id] = 1;
                }
            }
        }

        if ($dto->incldead !== null) {
            $notifsArr["incldead={$dto->incldead}"] = 1;
        }

        if ($dto->spstate !== null && $dto->spstate !== '') {
            $notifsArr["spstate={$dto->spstate}"] = 1;
        }

        if ($dto->inclbookmarked !== null && $dto->inclbookmarked !== '') {
            $notifsArr["inclbookmarked={$dto->inclbookmarked}"] = 1;
        }

        $data = [
            'notifs' => '['.implode('][', array_keys($notifsArr)).']',
            'torrentsperpage' => $dto->torrentsperpage,
            'timetype' => $dto->timetype,
            'appendsticky' => $dto->appendsticky,
            'appendnew' => $dto->appendnew,
            'appendpromotion' => $dto->appendpromotion,
            'appendpicked' => $dto->appendpicked,
            'dlicon' => $dto->dlicon,
            'bmicon' => $dto->bmicon,
            'showcomnum' => $dto->showcomnum,
            'showdescription' => $dto->showdescription,
            'showsmalldescr' => $dto->showsmalldescr,
            'showcomment' => $dto->showcomment,
            'pmnum' => $dto->pmnum,
            'sbnum' => $dto->sbnum,
            'sbrefresh' => $dto->sbrefresh,
            'fontsize' => $dto->fontsize,
        ];

        if ($dto->stylesheet !== null) {
            $data['stylesheet'] = $dto->stylesheet;
        }

        if ($dto->sitelanguage !== null) {
            $langFolder = Locale::folderForIdWithContext($dto->sitelanguage);
            $currentFolder = Locale::folderFromCookie($dto->currentLangFolder, false);
            if ($currentFolder !== $langFolder) {
                Locale::setFolderCookie($langFolder, 0x7FFFFFFF);
            }
            $data['lang'] = $dto->sitelanguage;
        }

        $showTooltip = SiteConfig::current()->tweak->enableTooltip(false);
        if ($showTooltip) {
            $data['tooltip'] = $dto->tooltip ?? UserTooltip::OFF->value;
            $data['showlastcom'] = $dto->showlastcom ?? false;
        }

        User::query()->where('id', $user->id)->update($data);
        Cache::clearUser($user->id, (string) $user->passkey);

        return User::query()->find($user->id)?->toApiArray() ?? [];
    }

    /**
     * Process the legacy usercp security "confirm" form and return the redirect URL.
     */
    public function updateSecurityFromLegacyRequest(Request $request): string
    {
        return $this->security->updateSecurityFromLegacyRequest($request);
    }

    /**
     * Update security settings for the authenticated user via API.
     *
     * @return array<string, mixed>
     */
    public function updateSecurityApi(SecuritySettingsDto $dto): array
    {
        return $this->security->updateSecurityApi($dto);
    }
}
